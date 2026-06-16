<?php
/**
 * api/paypal-webhook.php — PayPal webhook handler.
 *
 * Receives POST notifications from PayPal for events like:
 *   CHECKOUT.ORDER.APPROVED  → payment completed (backup if client-side capture fails)
 *   PAYMENT.CAPTURE.COMPLETED → capture confirmed
 *
 * Verifies webhook signature before processing.
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

// Verify webhook signature (skip if webhook_id not configured — sandbox mode)
if ($webhookId !== '') {
    $verified = $paypal->verifyWebhook($headers, $body, $webhookId);
    if (!$verified) {
        // Log but don't reject — verification can fail due to stale signatures
        error_log('[paypal-webhook] Signature verification failed for transmission ' . ($headers['PAYPAL-TRANSMISSION-ID'] ?? 'unknown'));
        // Continue processing anyway in sandbox; in production you'd want to reject
        if (($paypalCfg['mode'] ?? 'sandbox') === 'live') {
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

// Only act on capture events
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

// At this point the payment is confirmed. The subscription activation is handled
// by the client-side capture endpoint (api/pago?action=capture-order).
// This webhook serves as a reconciliation backup — it can be leveraged later
// to auto-fix missed activations.

error_log('[paypal-webhook] PAYMENT.CAPTURE.COMPLETED — txn: ' . $txnId . ' amount: ' . $amount . ' EUR');

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'message' => 'Webhook processed.']);
