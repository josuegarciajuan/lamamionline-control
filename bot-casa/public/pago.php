<?php
/**
 * pago.php — Payment page for CasaWasap.
 *
 * Two modes:
 *   1. PayPal (default) — real payment via PayPal REST API Orders v2.
 *   2. Mock (?mock=1, admin only) — fake card form for testing.
 *
 * Mock mode is preserved verbatim from the original implementation.
 */
declare(strict_types=1);

define('WASAPBOT_ROOT', dirname(__DIR__));

// Autoload
spl_autoload_register(function (string $class): void {
    $prefix = 'WasapBot\\';
    $prefixLen = strlen($prefix);
    if (strncmp($prefix, $class, $prefixLen) !== 0) return;
    $file = WASAPBOT_ROOT . '/src/' . str_replace('\\', '/', substr($class, $prefixLen)) . '.php';
    if (file_exists($file)) require_once $file;
});

// Session check
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { header('Location: login'); exit; }

$userId = (int) ($_SESSION['user_id'] ?? 0);
$userName = h((string) ($_SESSION['name'] ?? $_SESSION['username'] ?? 'Usuario'));
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// Handle suplantar
if ($isAdmin && !empty($_SESSION['suplantar_user_id'])) {
    $userId = (int) $_SESSION['suplantar_user_id'];
    $um = new \WasapBot\Core\UserManager(WASAPBOT_ROOT);
    $su = $um->getUser($userId);
    if ($su !== null) $userName = h((string) ($su['name'] ?? $su['username'] ?? 'Usuario'));
}

// Get subscription status
$um = new \WasapBot\Core\UserManager(WASAPBOT_ROOT);
$subManager = new \WasapBot\Core\SubscriptionManager($um);
$subStatus = $subManager->getStatus($userId);

// Detect pending line creation (redirected from lines tab after extra-line payment)
$pendingLine = $_SESSION['pending_line'] ?? null;
$isExtraLine = ($pendingLine !== null && is_array($pendingLine));

// Prevent payment for unlimited/demo users
if (in_array($subStatus['status'], ['unlimited', 'demo'], true)) {
    header('Location: cliente');
    exit;
}

// ── Mode selection ──
$useMock = (($_GET['mock'] ?? '') === '1') && $isAdmin;

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ── Pricing ──
$amount = 100; // €/week base (1 line included)
$lineCount = 0;
$extraLines = 0;
$extraCost = 25; // € per extra line/week

if ($isExtraLine) {
    $amount = $extraCost;
    $lineCount = 1;
    $extraLines = 0;
} else {
    $linesMapFile = WASAPBOT_ROOT . '/data/lines_map.json';
    if (file_exists($linesMapFile)) {
        $linesMap = @json_decode((string) @file_get_contents($linesMapFile), true);
        if (is_array($linesMap)) {
            foreach ($linesMap as $last9 => $uid) {
                if ((int) $uid === $userId) $lineCount++;
            }
        }
    }
    if ($lineCount <= 0) {
        $userLinesFile = WASAPBOT_ROOT . '/data/users/' . $userId . '/lines.json';
        if (file_exists($userLinesFile)) {
            $userLines = @json_decode((string) @file_get_contents($userLinesFile), true);
            if (is_array($userLines)) $lineCount = count($userLines);
        }
    }
    $lineCount = max($lineCount, 1);
    $extraLines = $lineCount - 1;
    $amount = 100 + ($extraLines * $extraCost);
}

$error = '';
$success = false;

// ── Mock mode POST handler (original logic, untouched) ──
if ($useMock && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cardNumber  = trim((string) ($_POST['card_number'] ?? ''));
    $cardExpiry  = trim((string) ($_POST['card_expiry'] ?? ''));
    $cardCvv     = trim((string) ($_POST['card_cvv'] ?? ''));

    if ($cardNumber === '' || strlen(preg_replace('/\s/', '', $cardNumber)) < 13) {
        $error = 'Número de tarjeta inválido.';
    } elseif (!preg_match('/^\d{2}\/\d{2}$/', $cardExpiry)) {
        $error = 'Fecha de caducidad inválida (formato MM/AA).';
    } elseif (!preg_match('/^\d{3,4}$/', $cardCvv)) {
        $error = 'CVV inválido.';
    } else {
        if ($isExtraLine) {
            $phone = (string) ($pendingLine['phone'] ?? '');
            $label = (string) ($pendingLine['label'] ?? '');
            if ($phone === '') {
                $error = 'Error: datos de línea incompletos.';
            } else {
                $wahaCfg = [
                    'waha_server' => '100.117.92.74',
                    'waha_api_key' => 'local321',
                    'webhook_url' => 'https://lamami.online/control/bot-casa/public/webhook.php',
                ];
                $wmLines = new \WasapBot\Core\WahaManager($wahaCfg);
                $linesMapFile = WASAPBOT_ROOT . '/data/lines_map.json';

                // wasapbot_create_line_local is defined below only when mock is used
                $createResult = wasapbot_create_line_local($phone, $label, $userId, $wmLines, $linesMapFile);
                if ($createResult['ok']) {
                    $subManager->recordPayment($userId, (float) $amount, 'card');
                    unset($_SESSION['pending_line']);
                    $success = true;
                } else {
                    $error = 'Error al crear la línea: ' . ($createResult['error'] ?? 'desconocido');
                }
            }
        } else {
            $activateResult = $subManager->activateWeekly($userId, 1);
            if ($activateResult['ok']) {
                $subManager->recordPayment($userId, (float) $amount, 'card');
                $success = true;
            } else {
                $error = 'Error al activar: ' . ($activateResult['error'] ?? 'desconocido');
            }
        }
    }
}

// ── wasapbot_create_line_local (only needed when mock mode is active) ──
if ($useMock) {
    /**
     * Create a line for a user (WAHA instance + persist + lines_map).
     */
    function wasapbot_create_line_local(string $phone, string $label, int $userId, object $wm, string $linesMapFile): array {
        $last9 = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($last9) < 9) $last9 = str_pad($last9, 9, '0', STR_PAD_LEFT);
        $last9 = mb_substr($last9, -9);

        $linesFile = WASAPBOT_ROOT . '/data/users/' . $userId . '/lines.json';
        $lines = [];
        if (file_exists($linesFile)) {
            $data = @json_decode((string)@file_get_contents($linesFile), true);
            if (is_array($data)) $lines = $data;
        }

        $status = $wm->getStatus();
        $port = (int) ($status['next_port'] ?? 3020);

        $result = ['ok' => false, 'error' => 'WAHA no disponible'];
        try {
            $result = $wm->createInstance($port);
        } catch (\Throwable) {
            $result = ['ok' => false, 'error' => 'WAHA no disponible'];
        }

        if (!$result['ok']) {
            return ['ok' => false, 'error' => $result['error'] ?? 'Error al crear instancia WAHA'];
        }

        $nextId = count($lines) > 0 ? max(array_column($lines, 'id')) + 1 : 1;
        $line = [
            'id' => $nextId, 'last9' => $last9, 'phone' => $phone,
            'label' => $label !== '' ? $label : ('Línea ' . $nextId),
            'port' => $result['port'] ?? $port, 'container_port' => $port,
            'created_at' => date('c'), 'health_status' => 'starting', 'error' => '',
        ];
        $lines[] = $line;

        $dir = dirname($linesFile);
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        if (file_exists($linesFile) && !is_writable($linesFile)) {
            @unlink($linesFile);
            clearstatcache(true, $linesFile);
        }
        @file_put_contents($linesFile, json_encode($lines, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);

        $map = [];
        if (file_exists($linesMapFile)) {
            $map = @json_decode((string)@file_get_contents($linesMapFile), true);
            if (!is_array($map)) $map = [];
        }
        $map[$last9] = $userId;
        if (file_exists($linesMapFile) && !is_writable($linesMapFile)) {
            @unlink($linesMapFile);
            clearstatcache(true, $linesMapFile);
        }
        @file_put_contents($linesMapFile, json_encode($map, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n", LOCK_EX);

        return ['ok' => true, 'line' => $line];
    }
}

// ── PayPal config (only needed in PayPal mode) ──
$paypalClientId = '';
$paypalMode = 'sandbox';
if (!$useMock) {
    try {
        $config = new \WasapBot\Core\Config(WASAPBOT_ROOT);
        $paypalCfg = $config->get('paypal');
        if (is_array($paypalCfg)) {
            $paypalClientId = (string) ($paypalCfg['client_id'] ?? '');
            $paypalMode     = (string) ($paypalCfg['mode'] ?? 'sandbox');
        }
    } catch (\Throwable) {
        // Config not available; fallback handled client-side
    }
    $paypalConfigured = ($paypalClientId !== '' && $paypalClientId !== 'PAYPAL_CLIENT_ID');
} else {
    $paypalConfigured = false;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CasaWasap — Activar plan</title>
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: #0a0a12; color: #f0f3fa;
        min-height: 100vh; display: flex; align-items: center; justify-content: center;
        padding: 20px;
    }
    .payment-card {
        background: #14141f; border: 1px solid rgba(255,255,255,0.08);
        border-radius: 16px; padding: 40px 32px;
        max-width: 420px; width: 100%;
    }
    .payment-card h1 {
        font-size: 1.3rem; margin-bottom: 4px; color: #fff;
    }
    .payment-card .subtitle {
        font-size: .82rem; color: #9d9dad; margin-bottom: 24px;
    }
    .amount-box {
        background: rgba(255,59,141,0.1); border: 1px solid rgba(255,59,141,0.2);
        border-radius: 10px; padding: 16px; margin-bottom: 24px;
        text-align: center;
    }
    .amount-box .price {
        font-size: 2rem; font-weight: 700; color: #ff3b8d;
    }
    .amount-box .period {
        font-size: .8rem; color: #ff6ba8; margin-top: 2px;
    }
    .amount-box .line-breakdown {
        font-size: .72rem; color: #9d9dad; margin-top: 6px;
        border-top: 1px solid rgba(255,255,255,0.06); padding-top: 6px;
    }
    .form-group {
        margin-bottom: 16px;
    }
    .form-group label {
        display: block; font-size: .78rem; color: #9d9dad; margin-bottom: 6px;
    }
    .form-group input {
        width: 100%; padding: 12px 14px;
        background: #0a0a12; border: 1px solid rgba(255,255,255,0.08);
        border-radius: 8px; color: #f0f3fa; font-size: .95rem;
        outline: none; transition: border-color .2s;
    }
    .form-group input:focus {
        border-color: #ff3b8d;
    }
    .form-row {
        display: flex; gap: 12px;
    }
    .form-row .form-group { flex: 1; }
    .btn-pay {
        width: 100%; padding: 14px;
        background: #ff3b8d; color: #fff; border: none;
        border-radius: 10px; font-size: 1rem; font-weight: 600;
        cursor: pointer; transition: background .2s;
        margin-top: 8px;
    }
    .btn-pay:hover { background: #e02170; }
    .btn-pay:disabled { opacity: .6; cursor: not-allowed; }
    .error-msg {
        background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.3);
        border-radius: 8px; padding: 12px; color: #f87171; font-size: .82rem;
        margin-bottom: 16px;
    }
    .success-msg {
        text-align: center; padding: 32px 0;
    }
    .success-msg .check {
        font-size: 3rem; margin-bottom: 12px;
    }
    .success-msg h2 { color: #4ade80; margin-bottom: 8px; font-size: 1.2rem; }
    .success-msg p { color: #9d9dad; font-size: .85rem; margin-bottom: 20px; }
    .btn-back {
        display: inline-block; padding: 12px 28px;
        background: #ff3b8d; color: #fff; border-radius: 10px;
        text-decoration: none; font-weight: 600; font-size: .9rem;
    }
    .btn-back:hover { background: #e02170; }
    .back-link {
        display: block; text-align: center; margin-top: 16px;
        font-size: .8rem;
    }
    .back-link a { color: #9d9dad; text-decoration: none; }
    .back-link a:hover { color: #ff3b8d; }
    .test-note {
        margin-top: 20px; padding: 12px; background: rgba(124,92,255,0.08);
        border-radius: 8px; font-size: .72rem; color: #7c5cff;
        text-align: center; line-height: 1.5;
    }
    /* PayPal specific */
    .paypal-wrapper {
        margin-top: 8px; min-height: 50px;
    }
    .paypal-unconfigured {
        text-align: center; padding: 20px; color: #f59e0b; font-size: .85rem;
        border: 1px dashed rgba(245,158,11,0.3); border-radius: 10px;
        margin-top: 8px;
    }
    .processing-overlay {
        display: none; position: fixed; top:0; left:0; width:100%; height:100%;
        background: rgba(10,10,18,0.9); z-index: 100;
        align-items: center; justify-content: center; flex-direction: column;
    }
    .processing-overlay.active { display: flex; }
    .spinner {
        width: 48px; height: 48px; border: 3px solid rgba(255,255,255,0.1);
        border-top-color: #ff3b8d; border-radius: 50%;
        animation: spin .7s linear infinite; margin-bottom: 16px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .processing-text { color: #9d9dad; font-size: .9rem; }
</style>
</head>
<body>

<div class="processing-overlay" id="processing-overlay">
    <div class="spinner"></div>
    <p class="processing-text" id="processing-text">Procesando pago...</p>
</div>

<div class="payment-card">
    <?php if ($success): ?>
    <div class="success-msg">
        <div class="check">✅</div>
        <h2><?php echo $isExtraLine ? '¡Línea creada!' : '¡Plan activado!'; ?></h2>
        <p><?php echo $isExtraLine ? 'Tu línea extra de WhatsApp ya está activa.' : 'Tu plan semanal de CasaWasap ya está activo.'; ?><br>Ya puedes volver a usar el bot.</p>
        <a href="cliente" class="btn-back">Volver al panel</a>
    </div>
    <?php else: ?>
    <h1><?php echo $isExtraLine ? 'Línea extra WhatsApp' : 'Activar CasaWasap'; ?></h1>
    <p class="subtitle"><?php echo $isExtraLine ? '+25€/semana por línea adicional' : 'Plan semanal — ' . h($userName); ?></p>

    <div class="amount-box">
        <div class="price"><?php echo $amount; ?>€</div>
        <div class="period">por semana · sin permanencia</div>
        <?php if ($isExtraLine): ?>
        <div class="line-breakdown">Línea extra: <?php echo h($pendingLine['label'] ?? $pendingLine['phone'] ?? ''); ?></div>
        <?php else: ?>
        <div class="line-breakdown">
            <?php echo $lineCount; ?> línea<?php echo $lineCount > 1 ? 's' : ''; ?>
            <?php if ($extraLines > 0): ?>
            (1 incluida + <?php echo $extraLines; ?> extra<?php echo $extraLines > 1 ? 's' : ''; ?> × <?php echo $extraCost; ?>€)
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($error !== ''): ?>
    <div class="error-msg"><?php echo h($error); ?></div>
    <?php endif; ?>

    <?php if ($useMock): ?>
    <!-- ═══════════ MOCK MODE (admin only) ═══════════ -->
    <form method="post" action="pago?mock=1" id="payment-form" onsubmit="handleMockSubmit(event)">
        <div class="form-group">
            <label>Número de tarjeta</label>
            <input type="text" name="card_number" placeholder="1234 5678 9012 3456"
                   maxlength="19" autocomplete="off" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Caducidad</label>
                <input type="text" name="card_expiry" placeholder="MM/AA"
                       maxlength="5" autocomplete="off" required>
            </div>
            <div class="form-group">
                <label>CVV</label>
                <input type="text" name="card_cvv" placeholder="123"
                       maxlength="4" autocomplete="off" required>
            </div>
        </div>
        <button type="submit" class="btn-pay" id="btn-pay">Pagar <?php echo $amount; ?>€ (mock)</button>
    </form>

    <div class="test-note">
        ⚠️ <strong>Modo pruebas:</strong> No se realiza ningún cobro real.<br>
        Esta página simula el proceso de pago. Al aceptar, se activará el plan automáticamente.
    </div>
    <script>
    function handleMockSubmit(e) {
        e.preventDefault();
        var btn = document.getElementById('btn-pay');
        var overlay = document.getElementById('processing-overlay');
        btn.disabled = true;
        btn.textContent = 'Procesando...';
        overlay.classList.add('active');
        setTimeout(function() { e.target.submit(); }, 2000);
    }
    </script>

    <?php else: ?>
    <!-- ═══════════ PAYPAL MODE ═══════════ -->

    <?php if (!$paypalConfigured): ?>
    <div class="paypal-unconfigured">
        ⚠️ PayPal no configurado.<br>
        Añade <code>client_id</code> y <code>secret</code> en <code>config.local.json</code>.
    </div>
    <?php endif; ?>

    <div class="paypal-wrapper" id="paypal-button-container"></div>

    <p class="back-link"><a href="cliente">← Volver al panel</a></p>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php if (!$useMock): ?>
<!-- PayPal JS SDK -->
<script src="https://www.paypal.com/sdk/js?client-id=<?php echo h($paypalClientId); ?>&currency=EUR&intent=capture" data-namespace="paypal_sdk"></script>
<script>
(function() {
    var amount = <?php echo (int) $amount; ?>;
    var isExtraLine = <?php echo $isExtraLine ? 'true' : 'false'; ?>;
    var overlay = document.getElementById('processing-overlay');
    var procText = document.getElementById('processing-text');
    var container = document.getElementById('paypal-button-container');

    function showProcessing(msg) {
        procText.textContent = msg || 'Procesando pago...';
        overlay.classList.add('active');
    }
    function hideProcessing() {
        overlay.classList.remove('active');
        procText.textContent = 'Procesando pago...';
    }

    if (typeof paypal_sdk === 'undefined') {
        container.innerHTML = '<div class="paypal-unconfigured">ⓘ No se pudo cargar PayPal. Recarga la página o contacta con soporte.</div>';
    } else {
        paypal_sdk.Buttons({
            style: {
                layout: 'vertical',
                color:  'gold',
                shape:  'rect',
                label:  'paypal',
            },
            createOrder: function() {
                return fetch('api/pago?action=create-order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        amount: amount,
                        description: isExtraLine ? 'CasaWasap - Linea extra WhatsApp' : 'CasaWasap - Plan semanal',
                        is_extra_line: isExtraLine
                    })
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (!data.ok) {
                        throw new Error(data.error || 'Error al crear la orden.');
                    }
                    return data.order_id;
                });
            },
            onApprove: function(data) {
                showProcessing('Confirmando pago...');
                return fetch('api/pago?action=capture-order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_id: data.orderID })
                })
                .then(function(res) { return res.json(); })
                .then(function(result) {
                    hideProcessing();
                    if (result.ok) {
                        window.location.reload();
                    } else {
                        var card = document.querySelector('.payment-card');
                        var errDiv = document.createElement('div');
                        errDiv.className = 'error-msg';
                        errDiv.textContent = 'Error: ' + (result.error || 'Pago no completado.');
                        card.insertBefore(errDiv, container);
                    }
                })
                .catch(function(err) {
                    hideProcessing();
                    alert('Error al procesar el pago: ' + err.message);
                });
            },
            onCancel: function() {
                // User closed the PayPal popup
            },
            onError: function(err) {
                hideProcessing();
                var card = document.querySelector('.payment-card');
                var errDiv = document.createElement('div');
                errDiv.className = 'error-msg';
                errDiv.textContent = 'Error de PayPal. Inténtalo de nuevo.';
                card.insertBefore(errDiv, container);
            }
        }).render('#paypal-button-container');
    }
})();
</script>
<?php endif; ?>

</body>
</html>
