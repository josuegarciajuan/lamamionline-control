<?php
/**
 * api/paypal-webhook.php — PayPal webhook handler.
 *
 * Receives POST notifications from PayPal for events like:
 *   CHECKOUT.ORDER.APPROVED  → payment approved (client-side capture follows)
 *   PAYMENT.CAPTURE.COMPLETED → capture confirmed → ACTIVATE SUBSCRIPTION
 *
 * Reconciliation flow:
 *   1. Verifies webhook signature (live mode rejects invalid signatures).
 *   2. Resolves the user from `custom_id` (set at order creation: "user:<id>:<kind>"),
 *      falling back to fetching the order via the REST API if needed.
 *   3. Idempotency: skips activation if the transaction_id is already recorded.
 *   4. Activates the weekly plan (or creates the pending extra line) and records
 *      the payment. This is the safety net when the client-side capture call
 *      (api/pago?action=capture-order) never completed.
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

// Only accept POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Read raw body
$body = (string) file_get_contents('php://input');
$payload = json_decode($body, true);
if (!is_array($payload)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body.']);
    exit;
}

// Collect PayPal signature headers
$headers = [
    'PAYPAL-AUTH-ALGO'         => (string) ($_SERVER['HTTP_PAYPAL_AUTH_ALGO']         ?? ''),
    'PAYPAL-CERT-URL'          => (string) ($_SERVER['HTTP_PAYPAL_CERT_URL']          ?? ''),
    'PAYPAL-TRANSMISSION-ID'   => (string) ($_SERVER['HTTP_PAYPAL_TRANSMISSION_ID']   ?? ''),
    'PAYPAL-TRANSMISSION-SIG'  => (string) ($_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG']  ?? ''),
    'PAYPAL-TRANSMISSION-TIME' => (string) ($_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? ''),
];

// Load config and PayPal client
$config = new \WasapBot\Core\Config(WASAPBOT_ROOT);
$paypalCfg = $config->get('paypal');
if (!is_array($paypalCfg)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'PayPal config not found.']);
    exit;
}

$webhookId = (string) ($paypalCfg['webhook_id'] ?? '');
$paypal = new \WasapBot\Payment\PayPalClient($paypalCfg);
$mode = (string) ($paypalCfg['mode'] ?? 'sandbox');

// Verify webhook signature (skip only when webhook_id is not configured — sandbox)
if ($webhookId !== '') {
    $verified = $paypal->verifyWebhook($headers, $body, $webhookId);
    if (!$verified) {
        error_log('[paypal-webhook] Signature verification failed for transmission ' . ($headers['PAYPAL-TRANSMISSION-ID'] ?? 'unknown'));
        if ($mode === 'live') {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Invalid webhook signature.']);
            exit;
        }
    }
}

$eventType = (string) ($payload['event_type'] ?? '');

// Log all webhook events
error_log('[paypal-webhook] Received event: ' . $eventType);

// ── Acknowledge non-capture events immediately ──
if ($eventType !== 'PAYMENT.CAPTURE.COMPLETED') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'message' => 'Event acknowledged (no action needed).']);
    exit;
}

// Extract transaction data
$resource = $payload['resource'] ?? [];
$txnId    = (string) ($resource['id'] ?? '');
$amount   = (float) ($resource['amount']['value'] ?? 0);
$customId = (string) ($resource['custom_id'] ?? '');
$status   = (string) ($resource['status'] ?? '');

if ($status !== 'COMPLETED' || $txnId === '') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'message' => 'Capture not completed or missing data.']);
    exit;
}

// ── Resolve user from custom_id ("user:<id>:<kind>") ──
$userId = 0;
$isExtraLine = false;
if ($customId !== '' && preg_match('/^user:(\d+)(?::(extra-line|weekly))?$/', $customId, $m)) {
    $userId = (int) $m[1];
    $isExtraLine = (($m[2] ?? '') === 'extra-line');
}

// Fallback: fetch the order to recover custom_id/amount if the event lacks them
$orderId = (string) ($resource['supplementary_data']['related_ids']['order_id'] ?? '');
if ($userId <= 0 && $orderId !== '') {
    $orderResult = $paypal->getOrder($orderId);
    if ($orderResult['ok'] && isset($orderResult['order']['purchase_units'][0])) {
        $pu = $orderResult['order']['purchase_units'][0];
        $customId = (string) ($pu['custom_id'] ?? '');
        if ($customId !== '' && preg_match('/^user:(\d+)(?::(extra-line|weekly))?$/', $customId, $m)) {
            $userId = (int) $m[1];
            $isExtraLine = (($m[2] ?? '') === 'extra-line');
        }
        if ($amount <= 0) {
            $amount = (float) ($pu['amount']['value'] ?? 0);
        }
    }
}

if ($userId <= 0) {
    error_log('[paypal-webhook] PAYMENT.CAPTURE.COMPLETED — cannot resolve user. txn: ' . $txnId . ' custom_id: ' . $customId);
    http_response_code(200); // Acknowledge; do not retry spam
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'message' => 'Payment captured but user unknown — logged for manual review.']);
    exit;
}

$um = new \WasapBot\Core\UserManager(WASAPBOT_ROOT);
$user = $um->getUser($userId);
if ($user === null) {
    error_log('[paypal-webhook] PAYMENT.CAPTURE.COMPLETED — user ' . $userId . ' not found. txn: ' . $txnId);
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'message' => 'Payment captured but user not found.']);
    exit;
}

// ── Idempotency: skip if this transaction was already recorded ──
$payments = $user['payments'] ?? [];
if (is_array($payments)) {
    foreach ($payments as $p) {
        if (is_array($p) && ($p['transaction_id'] ?? '') === $txnId) {
            error_log('[paypal-webhook] PAYMENT.CAPTURE.COMPLETED — txn already recorded, skipping. txn: ' . $txnId);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'message' => 'Transaction already processed.']);
            exit;
        }
    }
}

// ── Activate subscription (reconciliation safety net) ──
$subManager = new \WasapBot\Core\SubscriptionManager($um);
$activated = false;

if ($isExtraLine) {
    // Extra line: webhook arrives from PayPal servers (no browser session), so the
    // pending line data is NOT available here. Record the payment as a credit; the
    // user creates the extra line from the panel (paid plan allows multiple lines).
    $subManager->recordPayment($userId, $amount > 0 ? $amount : 25.0, 'paypal', $txnId);
    $activated = true;
    error_log('[paypal-webhook] PAYMENT.CAPTURE.COMPLETED — extra-line payment recorded for user ' . $userId . ' txn: ' . $txnId);
} else {
    $activateResult = $subManager->activateWeekly($userId, 1);
    if ($activateResult['ok']) {
        $subManager->recordPayment($userId, $amount > 0 ? $amount : 100.0, 'paypal', $txnId);
        $activated = true;
        error_log('[paypal-webhook] PAYMENT.CAPTURE.COMPLETED — weekly plan activated for user ' . $userId . ' txn: ' . $txnId);
    } else {
        error_log('[paypal-webhook] PAYMENT.CAPTURE.COMPLETED — activation failed for user ' . $userId . ': ' . ($activateResult['error'] ?? 'unknown'));
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'message' => 'Webhook processed.', 'activated' => $activated]);
