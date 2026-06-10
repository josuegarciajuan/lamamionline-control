<?php
/**
 * api/csrf-token.php — CSRF token refresh endpoint.
 *
 * Returns a fresh CSRF token so long-lived bot-casa panels can keep their
 * tokens valid without a full page reload.
 *
 * GET /api/csrf-token.php → { "ok": true, "token": "..." }
 *
 * Token format (user-bound, 10-min window + 1 grace slot):
 *   hash_hmac('sha256', $userId . '|' . date('Y-m-d-H') . floor(min/10), $secret)
 */
declare(strict_types=1);

define('WASAPBOT_ROOT', dirname(__DIR__, 2));

$isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
session_set_cookie_params([
    "lifetime" => 0,
    "path"     => "/",
    "secure"   => $isHttps,
    "httponly" => true,
    "samesite" => "Lax",
]);
session_start();

header('Content-Type: application/json; charset=utf-8');

// Require authenticated session
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

// ── Load persistent secret ──
$secretFile = WASAPBOT_ROOT . '/data/.csrf_secret';
$secret = '';
if (file_exists($secretFile)) {
    $secret = trim((string) @file_get_contents($secretFile));
}
if (strlen($secret) < 32) {
    $secret = bin2hex(random_bytes(32));
    $dir = dirname($secretFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    @file_put_contents($secretFile, $secret, LOCK_EX);
    @chmod($secretFile, 0600);
}

// Generate user-bound token (same format as panel.php / client.php)
$token = hash_hmac('sha256', $userId . '|' . date('Y-m-d-H') . floor((int) date('i') / 10), $secret);

echo json_encode([
    'ok'    => true,
    'token' => $token,
], JSON_UNESCAPED_UNICODE);
