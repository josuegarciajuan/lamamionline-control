<?php

declare(strict_types=1);

/**
 * api/bot-mode.php — Consulta y toggle del modo bot.
 *
 * GET  → { ok: true, mode: "start"|"stop" }
 * POST → { ok: true, mode: "start"|"stop" }  (action=toggle, CSRF required)
 */

define('WASAPBOT_ROOT', dirname(__DIR__, 2));

// ── Autoload ──
spl_autoload_register(function (string $class): void {
    $prefix = 'WasapBot\\';
    $prefixLen = strlen($prefix);
    if (strncmp($prefix, $class, $prefixLen) !== 0) return;
    $file = WASAPBOT_ROOT . '/src/' . str_replace('\\', '/', substr($class, $prefixLen)) . '.php';
    if (file_exists($file)) require_once $file;
});

// ── Session ──
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Auth check (chat.php sets session before calling APIs) ──
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? ($_POST['action'] ?? ''));

// ── Resolve mode file path ──
// Admin (userId=1): data/.bot_mode
// Per-user: data/users/{userId}/.bot_mode
$config = new \WasapBot\Core\Config(WASAPBOT_ROOT, $userId);
$modeFilePath = (string) $config->get('bot.mode_file', 'data/.bot_mode');
if (!str_starts_with($modeFilePath, '/')) {
    $modeFilePath = WASAPBOT_ROOT . '/' . ltrim($modeFilePath, '/');
}

// For per-user isolation (userId>1), override with user-specific path
if ($userId > 1) {
    $modeFilePath = WASAPBOT_ROOT . '/data/users/' . $userId . '/.bot_mode';
}

/**
 * Read bot mode ('start', 'stop', or 'unknown')
 */
function getBotMode(string $path): string
{
    if (!file_exists($path)) return 'unknown';
    $content = trim((string) @file_get_contents($path));
    if ($content === 'start') return 'start';
    if ($content === 'stop') return 'stop';
    return 'unknown';
}

/**
 * Write bot mode. Returns true on success.
 */
function setBotMode(string $path, string $mode): bool
{
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0750, true);

    $payload = ($mode === 'stop') ? 'stop' : 'start';
    clearstatcache(true, $path);

    // Strategy 1: direct write
    if (@file_put_contents($path, $payload, LOCK_EX) !== false) {
        @chmod($path, 0664);
        return true;
    }

    // Strategy 2: chmod + write
    @chmod($path, 0664);
    clearstatcache(true, $path);
    if (@file_put_contents($path, $payload, LOCK_EX) !== false) {
        @chmod($path, 0664);
        return true;
    }

    // Strategy 3: temp file + rename (atomic)
    if (is_dir($dir) && is_writable($dir)) {
        $tmpPath = $dir . '/.bot_mode_tmp_' . uniqid('', true);
        if (@file_put_contents($tmpPath, $payload, LOCK_EX) !== false) {
            @chmod($tmpPath, 0664);
            if (@rename($tmpPath, $path)) {
                @chmod($path, 0664);
                return true;
            }
            if (@unlink($path) && @rename($tmpPath, $path)) {
                @chmod($path, 0664);
                return true;
            }
            @unlink($tmpPath);
        }
    }

    return false;
}

// ── Route ──
if ($method === 'GET') {
    // Return current mode
    $mode = getBotMode($modeFilePath);
    echo json_encode(['ok' => true, 'mode' => $mode], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method === 'POST' && $action === 'toggle') {
    // CSRF validation
    $secretFile = WASAPBOT_ROOT . '/data/.csrf_secret';
    $secret = '';
    if (file_exists($secretFile)) $secret = trim((string) @file_get_contents($secretFile));
    if (strlen($secret) < 32) $secret = bin2hex(random_bytes(32));

    $token = (string) ($_POST['csrf_token'] ?? '');
    $now = time();
    $valid = false;
    for ($offset = 0; $offset <= 5; $offset++) {
        $t = $now - ($offset * 600);
        $expected = hash_hmac('sha256', $userId . '|' . date('Y-m-d-H', $t) . (int) floor((int) date('i', $t) / 10), $secret);
        if (hash_equals($expected, $token)) { $valid = true; break; }
    }
    if (!$valid) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'CSRF invalid']);
        exit;
    }

    // Toggle mode
    $current = getBotMode($modeFilePath);
    $newMode = ($current === 'start') ? 'stop' : 'start';
    $success = setBotMode($modeFilePath, $newMode);

    if ($success) {
        echo json_encode(['ok' => true, 'mode' => $newMode], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'No se pudo escribir el archivo de modo'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Fallback for unsupported methods
http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
