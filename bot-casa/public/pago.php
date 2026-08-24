<?php
/**
 * pago.php — Payment page for CasaWasap.
 *
 * Pago real vía PayPal REST API Orders v2 (incluye checkout con tarjeta de
 * débito/crédito gestionado por el propio SDK de PayPal).
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

// Already-active plan (fresh payment or existing subscription): nothing to pay.
// Redirect back to the panel so the user sees the green "Plan semanal" banner.
if ($subStatus['status'] === 'active' && !$isExtraLine) {
    header('Location: cliente');
    exit;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ── Pricing ──
$lineCount = 0;
$extraLines = 0;
$extraCost = \WasapBot\Core\Pricing::extraLine(); // € per extra line/week

if ($isExtraLine) {
    $amount = $extraCost;
    $lineCount = 1;
    $extraLines = 0;
} else {
    $lineCount = \WasapBot\Core\Pricing::userLineCount($userId, WASAPBOT_ROOT);
    $lineCount = max($lineCount, 1);
    $extraLines = $lineCount - 1;
    $amount = \WasapBot\Core\Pricing::weeklyTotal($userId, $lineCount);
}

$error = '';
$success = false;

// ── PayPal config ──
$paypalClientId = '';
$paypalMode = 'sandbox';
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>CasaWasap — Activar plan</title>
<style>
    :root {
        --bg: #0b0d10;
        --card: #14161c;
        --card-raised: #1b1e26;
        --text: #f4f6f8;
        --muted: #a6abb6;
        --faint: #767b88;
        --border: rgba(255,255,255,0.07);
        --border-strong: rgba(255,255,255,0.12);
        --green: #34d399;
        --green-hover: #2fc98e;
        --green-soft: rgba(52,211,153,0.12);
        --green-border: rgba(52,211,153,0.22);
        --gold: #f4b740;
        --gold-soft: rgba(244,183,64,0.10);
        --gold-border: rgba(244,183,64,0.22);
        --brand: #ff3b8d;
        --danger: #f87171;
        --danger-bg: rgba(248,113,113,0.10);
        --danger-border: rgba(248,113,113,0.25);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { height: 100%; }
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        background: var(--bg);
        color: var(--text);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        position: relative;
        overflow-x: hidden;
    }
    /* Ambient warmth — kills the "cold" feeling */
    body::before,
    body::after {
        content: '';
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 0;
    }
    body::before {
        background: radial-gradient(600px at 18% -5%, rgba(52,211,153,0.12), transparent 60%);
    }
    body::after {
        background: radial-gradient(700px at 100% 100%, rgba(244,183,64,0.08), transparent 60%);
    }
    .payment-card {
        position: relative;
        z-index: 1;
        background: var(--card);
        border: 1px solid var(--border-strong);
        border-radius: 18px;
        padding: 36px 32px 28px;
        max-width: 460px;
        width: 100%;
        box-shadow: 0 24px 60px rgba(0,0,0,0.45);
        animation: cardIn .5s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes cardIn {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Brand row ── */
    .brand-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 22px;
    }
    .brand {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        font-size: 0.95rem;
        letter-spacing: -0.01em;
    }
    .brand-dot {
        width: 9px; height: 9px;
        background: var(--brand);
        border-radius: 50%;
        box-shadow: 0 0 0 3px rgba(255,59,141,0.18);
    }
    .lock-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--muted);
        background: var(--card-raised);
        border: 1px solid var(--border);
        border-radius: 999px;
        padding: 5px 11px;
    }
    .lock-chip svg { color: var(--green); }

    /* ── Headings ── */
    .eyebrow {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--green);
        margin-bottom: 8px;
    }
    .payment-card h1 {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1.25;
        letter-spacing: -0.01em;
        color: #fff;
        margin-bottom: 10px;
    }
    .subtitle {
        font-size: 0.88rem;
        color: var(--muted);
        line-height: 1.5;
        margin-bottom: 20px;
    }

    /* ── Time-to-value chip ── */
    .speed-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.74rem;
        font-weight: 600;
        color: var(--gold);
        background: var(--gold-soft);
        border: 1px solid var(--gold-border);
        border-radius: 999px;
        padding: 6px 12px;
        margin-bottom: 16px;
    }

    /* ── Amount block ── */
    .amount-block {
        background: var(--card-raised);
        border: 1px solid var(--green-border);
        border-radius: 14px;
        padding: 22px 20px 18px;
        text-align: center;
        margin-bottom: 22px;
    }
    .amount-label {
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--muted);
        margin-bottom: 4px;
    }
    .price {
        font-size: 2.9rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        line-height: 1;
        color: var(--green);
        font-variant-numeric: tabular-nums;
    }
    .period {
        font-size: 0.82rem;
        font-weight: 500;
        color: var(--muted);
        margin-top: 4px;
    }
    .reassurance {
        font-size: 0.78rem;
        font-weight: 600;
        color: var(--green);
        margin-top: 10px;
    }
    .line-breakdown {
        font-size: 0.72rem;
        color: var(--faint);
        margin-top: 10px;
        border-top: 1px solid var(--border);
        padding-top: 10px;
    }

    /* ── Benefits ── */
    .benefits {
        list-style: none;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px 16px;
        margin-bottom: 22px;
    }
    .benefits li {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        font-size: 0.83rem;
        font-weight: 500;
        color: var(--text);
        line-height: 1.35;
        animation: fadeIn .45s ease both;
        animation-delay: calc(var(--i) * 60ms);
    }
    .benefits li svg {
        color: var(--green);
        flex-shrink: 0;
        margin-top: 1px;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(6px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Guarantee strip ── */
    .guarantee {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        background: var(--gold-soft);
        border: 1px solid var(--gold-border);
        border-radius: 12px;
        padding: 12px 14px;
        font-size: 0.78rem;
        line-height: 1.45;
        color: var(--gold);
        margin-bottom: 22px;
    }
    .guarantee svg {
        color: var(--gold);
        flex-shrink: 0;
        margin-top: 1px;
    }
    .guarantee strong { font-weight: 700; }

    /* ── Form (mock) ── */
    .form-group { margin-bottom: 14px; }
    .form-group label {
        display: block;
        font-size: 0.76rem;
        font-weight: 600;
        color: var(--muted);
        margin-bottom: 6px;
    }
    .form-group input {
        width: 100%;
        padding: 12px 14px;
        background: var(--card-raised);
        border: 1px solid var(--border-strong);
        border-radius: 10px;
        color: var(--text);
        font-size: 0.95rem;
        outline: none;
        transition: border-color .2s, box-shadow .2s;
    }
    .form-group input::placeholder { color: var(--faint); }
    .form-group input:focus {
        border-color: var(--green);
        box-shadow: 0 0 0 3px rgba(52,211,153,0.22);
    }
    .form-row { display: flex; gap: 12px; }
    .form-row .form-group { flex: 1; }

    /* ── CTA ── */
    .btn-pay {
        width: 100%;
        padding: 15px;
        background: var(--green);
        color: #0b0d10;
        border: none;
        border-radius: 11px;
        font-size: 1rem;
        font-weight: 700;
        cursor: pointer;
        margin-top: 6px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.28), 0 8px 20px rgba(52,211,153,0.22);
        transition: background .18s, transform .12s, box-shadow .18s;
    }
    .btn-pay:hover {
        background: var(--green-hover);
        transform: translateY(-1px);
    }
    .btn-pay:active { transform: scale(0.98); }
    .btn-pay:disabled { opacity: .65; cursor: not-allowed; transform: none; }
    .btn-pay:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
    .cta-microcopy {
        text-align: center;
        font-size: 0.74rem;
        color: var(--faint);
        margin-top: 12px;
    }

    .error-msg {
        background: var(--danger-bg);
        border: 1px solid var(--danger-border);
        border-radius: 10px;
        padding: 12px 14px;
        color: var(--danger);
        font-size: 0.82rem;
        line-height: 1.4;
        margin-bottom: 16px;
    }

    /* ── Success ── */
    .success-msg { text-align: center; padding: 20px 0; }
    .success-check {
        width: 72px; height: 72px;
        margin-bottom: 14px;
        animation: pop .45s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .success-check circle,
    .success-check path {
        stroke-dasharray: 100;
        stroke-dashoffset: 100;
        animation: draw .6s ease forwards;
    }
    .success-check path { animation-delay: .25s; }
    @keyframes draw { to { stroke-dashoffset: 0; } }
    @keyframes pop {
        from { transform: scale(0.6); opacity: 0; }
        to   { transform: scale(1); opacity: 1; }
    }
    .success-msg h2 { color: var(--green); margin-bottom: 8px; font-size: 1.3rem; }
    .success-msg p { color: var(--muted); font-size: 0.88rem; line-height: 1.5; margin-bottom: 24px; }
    .btn-back {
        display: inline-block;
        padding: 14px 30px;
        background: var(--green);
        color: #0b0d10;
        border-radius: 11px;
        text-decoration: none;
        font-weight: 700;
        font-size: 0.95rem;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.28), 0 8px 20px rgba(52,211,153,0.22);
        transition: background .18s, transform .12s;
    }
    .btn-back:hover { background: var(--green-hover); transform: translateY(-1px); }

    /* ── Trust badges ── */
    .trust-badges {
        display: flex;
        justify-content: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 20px;
    }
    .trust-badges span {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.7rem;
        font-weight: 500;
        color: var(--faint);
    }
    .trust-badges svg { color: var(--green); }

    .back-link {
        display: block;
        text-align: center;
        margin-top: 18px;
        font-size: 0.8rem;
    }
    .back-link a { color: var(--faint); text-decoration: none; transition: color .15s; }
    .back-link a:hover { color: var(--text); }

    .test-note {
        margin-top: 18px;
        padding: 12px;
        background: var(--gold-soft);
        border: 1px solid var(--gold-border);
        border-radius: 10px;
        font-size: 0.74rem;
        color: var(--gold);
        text-align: center;
        line-height: 1.5;
    }
    .test-note strong { font-weight: 700; }

    /* ── PayPal ── */
    .paypal-wrapper { margin-top: 4px; min-height: 50px; }
    .paypal-divider {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--faint);
        font-size: 0.72rem;
        margin: 2px 0 12px;
    }
    .paypal-divider::before,
    .paypal-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: var(--border);
    }
    .paypal-unconfigured {
        text-align: center;
        padding: 20px;
        color: var(--gold);
        font-size: 0.84rem;
        border: 1px dashed var(--gold-border);
        border-radius: 12px;
        margin-top: 8px;
    }

    /* ── Processing overlay ── */
    .processing-overlay {
        display: none;
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(11,13,16,0.92);
        backdrop-filter: blur(4px);
        z-index: 100;
        align-items: center;
        justify-content: center;
        flex-direction: column;
    }
    .processing-overlay.active { display: flex; }
    .spinner {
        width: 48px; height: 48px;
        border: 3px solid rgba(255,255,255,0.12);
        border-top-color: var(--green);
        border-radius: 50%;
        animation: spin .7s linear infinite;
        margin-bottom: 16px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .processing-text { color: var(--muted); font-size: 0.9rem; }

    @media (max-width: 420px) {
        .payment-card { padding: 28px 20px 22px; }
        .benefits { grid-template-columns: 1fr; }
        .price { font-size: 2.5rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        * { animation: none !important; transition: none !important; }
    }
</style>
</head>
<body>

<div class="processing-overlay" id="processing-overlay">
    <div class="spinner"></div>
    <p class="processing-text" id="processing-text">Procesando pago...</p>
</div>

<div class="payment-card">
    <?php if ($success): ?>
    <div class="success-msg" role="alert" aria-live="polite">
        <svg class="success-check" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <path d="M8 12.5 11 15.5 16 9.5"/>
        </svg>
        <h2><?php echo $isExtraLine ? '¡Línea extra activada!' : '¡Tu bot ya está desbloqueado!'; ?></h2>
        <p><?php echo $isExtraLine ? 'Tu nueva línea de WhatsApp ya está activa y respondiendo.' : 'CasaWasap ya responde por ti y sigue a tus clientes automáticamente.'; ?></p>
        <a href="cliente" class="btn-back">Volver al panel</a>
    </div>
    <?php else: ?>
    <div class="brand-row">
        <div class="brand"><span class="brand-dot"></span>CasaWasap</div>
        <span class="lock-chip">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2.5"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Pago seguro
        </span>
    </div>

    <div class="eyebrow"><?php echo $isExtraLine ? 'Nueva línea de WhatsApp' : 'Activación del plan'; ?></div>
    <h1><?php echo $isExtraLine ? 'Añade una línea extra de WhatsApp' : 'Convierte tu WhatsApp en tu mejor vendedor'; ?></h1>
    <p class="subtitle"><?php echo $isExtraLine ? 'Un último paso para tener otra línea respondiendo 24/7.' : 'Hola ' . $userName . ', un último paso para responder a cada cliente al instante, las 24 horas.'; ?></p>

    <?php if (!$isExtraLine): ?>
    <span class="speed-chip">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/></svg>
        Empieza a responder en minutos
    </span>
    <?php endif; ?>

    <div class="amount-block">
        <div class="amount-label">Inversión semanal</div>
        <div class="price"><?php echo $amount; ?>€</div>
        <div class="period">por semana</div>
        <div class="reassurance">Sin permanencia · cancela cuando quieras</div>
        <div class="line-breakdown">
            <?php if ($isExtraLine): ?>
            Línea extra: <?php echo h($pendingLine['label'] ?? $pendingLine['phone'] ?? ''); ?>
            <?php else: ?>
            <?php echo $lineCount; ?> línea<?php echo $lineCount > 1 ? 's' : ''; ?>
            <?php if ($extraLines > 0): ?>
            (1 incluida + <?php echo $extraLines; ?> extra<?php echo $extraLines > 1 ? 's' : ''; ?> × <?php echo $extraCost; ?>€)
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <ul class="benefits">
        <li style="--i:0"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Responde al instante, 24/7</li>
        <li style="--i:1"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>No pierdas ni un cliente</li>
        <li style="--i:2"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Ahorra horas cada día</li>
        <li style="--i:3"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Listo en minutos, sin tecnicismos</li>
    </ul>

    <div class="guarantee">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
        <span><strong>Garantía CasaWasap:</strong> sin permanencia. Si no te encaja, cancelas y no pagas más.</span>
    </div>

    <?php if ($error !== ''): ?>
    <div class="error-msg" role="alert" aria-live="polite"><?php echo h($error); ?></div>
    <?php endif; ?>

    <?php if (!$paypalConfigured): ?>
    <div class="paypal-unconfigured">
        ⚠️ PayPal no configurado.<br>
        Añade <code>client_id</code> y <code>secret</code> en <code>config.local.json</code>.
    </div>
    <?php endif; ?>

    <div class="paypal-divider">Paga de forma segura con</div>

    <div class="paypal-wrapper" id="paypal-button-container"></div>

    <p class="cta-microcopy">Activación inmediata · Sin permanencia</p>

    <p class="back-link"><a href="cliente">← Volver al panel</a></p>

    <div class="trust-badges">
        <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2.5"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>Pago cifrado</span>
        <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>Sin permanencia</span>
        <span><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>Soporte humano</span>
    </div>
    <?php endif; ?>
</div>

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
                        // Payment captured → back to the panel (green active banner).
                        window.location.href = 'cliente';
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

</body>
</html>
