<?php

declare(strict_types=1);

/**
 * webhook.php — WAHA webhook endpoint.
 *
 * Receives incoming WhatsApp message payloads from the WAHA HTTP API,
 * passes them through the Bot pipeline, and returns HTTP 200 with JSON.
 *
 * Request:  POST {JSON body from WAHA webhook}
 * Response: 200 {"status":"ok"} or 500 {"status":"error","message":"..."}
 */

// Allow enough time for humanized delays (typing simulation) + OpenAI calls
set_time_limit(180);

// ─────────────────────────────────────────────────────────────────────
//  Autoload
// ─────────────────────────────────────────────────────────────────────

define('WASAPBOT_ROOT', dirname(__DIR__));

$vendorAutoload = WASAPBOT_ROOT . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    // Manual PSR-4 autoloader: WasapBot\ → src/
    spl_autoload_register(function (string $class): void {
        $prefix = 'WasapBot\\';
        $prefixLen = strlen($prefix);

        if (strncmp($prefix, $class, $prefixLen) !== 0) {
            return;
        }

        $relativeClass = substr($class, $prefixLen);
        $file = WASAPBOT_ROOT . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });
}

// ─────────────────────────────────────────────────────────────────────
//  Verify request method
// ─────────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    header('Allow: POST');
    echo json_encode([
        'status'  => 'error',
        'message' => 'Method not allowed. Use POST.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ─────────────────────────────────────────────────────────────────────
//  Main handler
// ─────────────────────────────────────────────────────────────────────

try {
    // ── Resolve user_id from the incoming payload (last9 → user_id) ──
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || $rawBody === '') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Empty request body'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON payload'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!is_array($payload)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => 'Payload must be a JSON object'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── Extract last9 from payload to determine user_id ──────────────
    $userId = 1; // default: admin / legacy
    $body   = $payload['payload'] ?? $payload;
    $me     = $body['me'] ?? null;
    $receiverId = '';
    if (is_array($me) && isset($me['id'])) {
        $receiverId = (string) $me['id'];
    } elseif (isset($body['to'])) {
        $receiverId = (string) $body['to'];
    }
    if ($receiverId !== '') {
        $digits = preg_replace('/[^0-9]/', '', $receiverId) ?? '';
        $last9  = $digits !== '' ? mb_substr($digits, -9) : '';
        if ($last9 !== '') {
            $linesMapPath = WASAPBOT_ROOT . '/data/lines_map.json';
            if (file_exists($linesMapPath)) {
                $linesMap = @json_decode((string) @file_get_contents($linesMapPath), true);
                if (is_array($linesMap) && isset($linesMap[$last9])) {
                    $userId = (int) $linesMap[$last9];
                }
            }
        }
    }

    // Bootstrap with user-specific data
    $instances = \WasapBot\Bot::bootstrap(WASAPBOT_ROOT, $userId);
    $bot       = $instances['bot'];
    $logger    = $instances['logger'];

    // ─────────────────────────────────────────────────────────────────
    //  Optional: shared-secret webhook authentication
    //  Set waha.webhook_secret in config.local.json to enable.
    //  Empty = no auth (trusted network / localhost).
    // ─────────────────────────────────────────────────────────────────

    $webhookSecret = (string) $instances['config']->get('waha.webhook_secret', '');
    if ($webhookSecret !== '') {
        $providedSecret = $_SERVER['HTTP_X_WAHA_SIGNATURE'] ?? $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? '';
        if (!hash_equals($webhookSecret, $providedSecret)) {
            $logger->warning('webhook.php — invalid webhook secret');
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    // Raw body already read and parsed above for user routing

    // Save raw payload for debugging
    $rawPayloadPath = (string) $instances['config']->get('files.wa_raw_payload', '');
    if ($rawPayloadPath !== '') {
        // Resolve relative path against project root
        if (!str_starts_with($rawPayloadPath, '/')) {
            $rawPayloadPath = WASAPBOT_ROOT . '/' . ltrim($rawPayloadPath, '/');
        }
        $rawPayloadDir = dirname($rawPayloadPath);
        if (!is_dir($rawPayloadDir)) {
            @mkdir($rawPayloadDir, 0755, true);
        }
        @file_put_contents(
            $rawPayloadPath,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n",
            LOCK_EX,
        );
    }

    // Check if bot is running
    if (!$bot->isRunning()) {
        $logger->info('webhook.php — bot is stopped, ignoring webhook');
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status'  => 'ok',
            'message' => 'Bot is stopped',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Process through pipeline
    $result = $bot->handleWebhook($payload);

    // Respond OK regardless of result (WAHA needs 200 to mark as delivered)
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'   => 'ok',
        'accepted' => $result !== null,
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    // Catch-all: never expose stack traces to the client
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    if (isset($logger)) {
        $logger->error('webhook.php — unhandled exception: ' . $e->getMessage(), [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    } else {
        error_log('[wasapBot] webhook exception: ' . $e->getMessage());
    }

    echo json_encode([
        'status'  => 'error',
        'message' => 'Internal server error',
    ], JSON_UNESCAPED_UNICODE);
}
