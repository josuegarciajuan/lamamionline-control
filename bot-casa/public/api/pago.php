<?php
/**
 * api/pago.php — Payment API endpoints.
 *
 * POST /api/pago                      → Mock activation (legacy, for testing)
 * POST /api/pago?action=create-order  → Create PayPal order
 * POST /api/pago?action=capture-order → Capture PayPal order + activate subscription
 */
declare(strict_types=1);

define('WASAPBOT_ROOT', dirname(__DIR__, 2));

spl_autoload_register(function (string $class): void {
    $prefix = 'WasapBot\\';
    $prefixLen = strlen($prefix);
    if (strncmp($prefix, $class, $prefixLen) !== 0) return;
    $file = WASAPBOT_ROOT . '/src/' . str_replace('\\', '/', substr($class, $prefixLen)) . '.php';
    if (file_exists($file)) require_once $file;
});

$isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
session_set_cookie_params(["lifetime"=>0,"path"=>"/","secure"=>$isHttps,"httponly"=>true,"samesite"=>"Lax"]);
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }

$userId = (int) ($_SESSION['user_id'] ?? 0);
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
if ($isAdmin && !empty($_SESSION['suplantar_user_id'])) $userId = (int) $_SESSION['suplantar_user_id'];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use POST.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];

$um = new \WasapBot\Core\UserManager(WASAPBOT_ROOT);
$subManager = new \WasapBot\Core\SubscriptionManager($um);

// ─────────────────────────────────────────────────────────
//  PayPal: create order
// ─────────────────────────────────────────────────────────
if ($action === 'create-order') {
    $amount      = (float) ($input['amount'] ?? 100);
    $description = (string) ($input['description'] ?? 'CasaWasap - Plan semanal');
    $isExtraLine = !empty($input['is_extra_line']);

    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Importe inválido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $config = new \WasapBot\Core\Config(WASAPBOT_ROOT);
    $paypalCfg = $config->get('paypal');
    if (!is_array($paypalCfg)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Configuración de PayPal no encontrada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $paypal = new \WasapBot\Payment\PayPalClient($paypalCfg);

    $scheme = $isHttps ? 'https' : 'http';
    $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base   = $scheme . '://' . $host;
    $returnUrl = $base . '/pago';
    $cancelUrl = $base . '/pago';

    $result = $paypal->createOrder($amount, $description, $returnUrl, $cancelUrl);

    if ($result['ok']) {
        // Store pending order state in session for later verification
        $_SESSION['paypal_order_id'] = $result['order_id'];
        $_SESSION['paypal_amount']   = $amount;
        $_SESSION['paypal_extra']    = $isExtraLine;

        echo json_encode([
            'ok'       => true,
            'order_id' => $result['order_id'],
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Error al crear la orden.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ─────────────────────────────────────────────────────────
//  PayPal: capture order
// ─────────────────────────────────────────────────────────
if ($action === 'capture-order') {
    $orderId = (string) ($input['order_id'] ?? '');

    if ($orderId === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Order ID requerido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $config = new \WasapBot\Core\Config(WASAPBOT_ROOT);
    $paypalCfg = $config->get('paypal');
    if (!is_array($paypalCfg)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Configuración de PayPal no encontrada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $paypal = new \WasapBot\Payment\PayPalClient($paypalCfg);
    $result = $paypal->captureOrder($orderId);

    if (!$result['ok']) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Error al capturar el pago.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Payment captured: activate subscription ──
    $amount      = (float) ($_SESSION['paypal_amount'] ?? 100);
    $isExtraLine = !empty($_SESSION['paypal_extra']);
    $txnId       = (string) ($result['transaction_id'] ?? '');

    if ($isExtraLine) {
        // Extra-line payment on active plan
        $pendingLine = $_SESSION['pending_line'] ?? null;
        if ($pendingLine !== null && is_array($pendingLine)) {
            $phone = (string) ($pendingLine['phone'] ?? '');
            $label = (string) ($pendingLine['label'] ?? '');

            if ($phone !== '') {
                $wahaCfg = [
                    'waha_server' => '100.117.92.74',
                    'waha_api_key' => 'local321',
                    'webhook_url' => 'https://lamami.online/control/bot-casa/public/webhook.php',
                ];
                $wmLines = new \WasapBot\Core\WahaManager($wahaCfg);
                $linesMapFile = WASAPBOT_ROOT . '/data/lines_map.json';

                // Create line (inline logic to avoid duplication)
                $last9 = preg_replace('/[^0-9]/', '', $phone);
                if (strlen($last9) < 9) $last9 = str_pad($last9, 9, '0', STR_PAD_LEFT);
                $last9 = mb_substr($last9, -9);

                $linesFile = WASAPBOT_ROOT . '/data/users/' . $userId . '/lines.json';
                $lines = [];
                if (file_exists($linesFile)) {
                    $ld = @json_decode((string)@file_get_contents($linesFile), true);
                    if (is_array($ld)) $lines = $ld;
                }

                $status = $wmLines->getStatus();
                $port = (int) ($status['next_port'] ?? 3020);
                $createResult = ['ok' => false, 'error' => 'WAHA no disponible'];
                try { $createResult = $wmLines->createInstance($port); } catch (\Throwable) {}

                if ($createResult['ok']) {
                    $nextId = count($lines) > 0 ? max(array_column($lines, 'id')) + 1 : 1;
                    $line = [
                        'id' => $nextId, 'last9' => $last9, 'phone' => $phone,
                        'label' => $label !== '' ? $label : ('Línea ' . $nextId),
                        'port' => $createResult['port'] ?? $port, 'container_port' => $port,
                        'created_at' => date('c'), 'health_status' => 'starting', 'error' => '',
                    ];
                    $lines[] = $line;
                    $dir = dirname($linesFile);
                    if (!is_dir($dir)) @mkdir($dir, 0700, true);
                    if (file_exists($linesFile) && !is_writable($linesFile)) { @unlink($linesFile); clearstatcache(true, $linesFile); }
                    @file_put_contents($linesFile, json_encode($lines, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);

                    $map = [];
                    if (file_exists($linesMapFile)) {
                        $m = @json_decode((string)@file_get_contents($linesMapFile), true);
                        if (is_array($m)) $map = $m;
                    }
                    $map[$last9] = $userId;
                    if (file_exists($linesMapFile) && !is_writable($linesMapFile)) { @unlink($linesMapFile); clearstatcache(true, $linesMapFile); }
                    @file_put_contents($linesMapFile, json_encode($map, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n", LOCK_EX);
                } else {
                    http_response_code(500);
                    echo json_encode(['ok' => false, 'error' => 'Error al crear la línea: ' . ($createResult['error'] ?? 'desconocido')], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
            $subManager->recordPayment($userId, $amount, 'paypal', $txnId);
            unset($_SESSION['pending_line']);
        }
    } else {
        // Normal payment
        $activateResult = $subManager->activateWeekly($userId, 1);
        if (!$activateResult['ok']) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Error al activar: ' . ($activateResult['error'] ?? 'desconocido')], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $subManager->recordPayment($userId, $amount, 'paypal', $txnId);
    }

    // Clean up session
    unset($_SESSION['paypal_order_id'], $_SESSION['paypal_amount'], $_SESSION['paypal_extra']);

    echo json_encode([
        'ok' => true,
        'message' => 'Pago completado. Plan activado.',
        'transaction_id' => $txnId,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─────────────────────────────────────────────────────────
//  Legacy mock POST (no action param)
// ─────────────────────────────────────────────────────────
$weeks = (int) ($input['weeks'] ?? 1);
if ($weeks < 1) $weeks = 1;

$activateResult = $subManager->activateWeekly($userId, $weeks);
if ($activateResult['ok']) {
    $amount = 100.0 * $weeks;
    $subManager->recordPayment($userId, $amount, 'card');

    $newStatus = $subManager->getStatus($userId);
    echo json_encode([
        'ok' => true,
        'message' => 'Plan activado correctamente.',
        'subscription' => $newStatus,
    ], JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $activateResult['error'] ?? 'Error al activar'], JSON_UNESCAPED_UNICODE);
}
