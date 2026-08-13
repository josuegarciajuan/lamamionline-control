<?php

declare(strict_types=1);

/**
 * API Test Runner — runs api/mensajes.php in a subprocess.
 *
 * Usage: php wrapper.php action=threads [key=value ...]
 * Env: MENS_API_TMP_ROOT, MENS_API_TEST_USER_ID
 */

// ── Read config ──
$tmpRoot = getenv('MENS_API_TMP_ROOT');
if (!$tmpRoot) {
    echo json_encode(['ok' => false, 'error' => 'MENS_API_TMP_ROOT not set']);
    exit(1);
}
$testUserId = (int)(getenv('MENS_API_TEST_USER_ID') ?: 1);
$testUsername = ($testUserId === 999) ? 'demo' : 'test_user';

$dataDir = $tmpRoot . '/data';

// ── Define WASAPBOT_ROOT ──
define('WASAPBOT_ROOT', $tmpRoot);

// ── Autoloader ──
$realSrcRoot = '/root/lamamionline-control/bot-casa/src';
spl_autoload_register(function (string $class) use ($realSrcRoot): void {
    $prefix = 'WasapBot\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) return;
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = $realSrcRoot . '/' . $relative . '.php';
    if (file_exists($file)) require_once $file;
}, prepend: true);

// ── Parse CLI args into $_GET ──
$_GET = [];
for ($i = 1; $i < ($_SERVER['argc'] ?? 0); $i++) {
    $arg = $_SERVER['argv'][$i];
    $eq = strpos($arg, '=');
    if ($eq !== false) {
        $_GET[substr($arg, 0, $eq)] = substr($arg, $eq + 1);
    }
}
if (!isset($_GET['action'])) $_GET['action'] = 'list';

// ── Parse POST from env ──
$_POST = [];
$postEnv = getenv('MENS_API_POST_DATA');
if ($postEnv) {
    $d = json_decode($postEnv, true);
    if (is_array($d)) $_POST = $d;
}

// If POST data exists but no csrf_token, compute a valid one
if (!empty($_POST) && empty($_POST['csrf_token'])) {
    $secretFile = $dataDir . '/.csrf_secret';
    $secret = file_exists($secretFile) ? file_get_contents($secretFile) : 'testsecret32bytes_minimum!';
    $tNow = time();
    $userId = $_SESSION['user_id'] ?? $testUserId;
    $token = hash_hmac('sha256', $userId . '|' . date('Y-m-d-H', $tNow) . (int)floor((int)date('i', $tNow) / 10), $secret);
    $_POST['csrf_token'] = $token;
}

// ── Server globals ──
$_SERVER['REQUEST_METHOD'] = !empty($_POST) ? 'POST' : 'GET';
$_SERVER['HTTPS'] = 'off';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// ── Session ──
// mensajes.php calls session_start(). We use a custom handler that
// writes to a temp file and pre-loads our test user data.
$sessionFile = $dataDir . '/_test_session.sess';
if (file_exists($sessionFile)) @unlink($sessionFile);

session_set_save_handler(
    function () { return true; },
    function () { return true; },
    function ($id) use ($sessionFile, $testUserId, $testUsername) {
        // Return serialized session with our test user
        return 'user_id|i:' . $testUserId . ';username|s:' . strlen($testUsername) . ':"' . $testUsername . '";role|s:5:"admin";';
    },
    function ($id, $data) use ($sessionFile) {
        return (bool)file_put_contents($sessionFile, $data);
    },
    function ($id) use ($sessionFile) {
        @unlink($sessionFile); return true;
    },
    function () { return true; }
);

// Start session (mensajes.php will call it again but our handler handles it)
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Verify session has user_id (should be populated by our custom read handler)
if (empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = $testUserId;
    $_SESSION['username'] = $testUsername;
    $_SESSION['role'] = ($testUserId <= 1) ? 'admin' : 'user';
}

// ── Execute mensajes.php via temp copy (avoids WASAPBOT_ROOT redefinition) ──
$apiFile = dirname(__DIR__, 2) . '/public/api/mensajes.php';
$tmpCopy = $tmpRoot . '/_api_test_copy.php';

$code = file_get_contents($apiFile);
if ($code === false) {
    echo json_encode(['ok' => false, 'error' => 'Cannot read mensajes.php']);
    exit(1);
}

// Replace the WASAPBOT_ROOT definition (we already defined it)
$code = str_replace(
    "define('WASAPBOT_ROOT', dirname(__DIR__, 2));",
    "// WASAPBOT_ROOT already defined by test harness",
    $code
);

// Replace the API's autoloader (we use our own which points at real src/)
$code = str_replace(
    "spl_autoload_register(function (string \$class): void {\n    \$prefix = 'WasapBot\\\\'; \$prefixLen = strlen(\$prefix);\n    if (strncmp(\$prefix, \$class, \$prefixLen) !== 0) return;\n    \$file = WASAPBOT_ROOT . '/src/' . str_replace('\\\\', '/', substr(\$class, \$prefixLen)) . '.php';\n    if (file_exists(\$file)) require_once \$file;\n});",
    "// autoloader handled by test harness",
    $code
);

file_put_contents($tmpCopy, $code);

try {
    require $tmpCopy;
} finally {
    @unlink($tmpCopy);
}
