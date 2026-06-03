<?php

declare(strict_types=1);

/**
 * panel.php — Admin panel for wasapBot (requires admin login via index.php).
 *
 * Access: GET /panel
 * Actions: ?action=save_config | toggle_bot | delete_memory_thread
 *           | delete_memory_line | clear_memory | save_user | delete_user
 */

// ─────────────────────────────────────────────────────────────────────
//  Bootstrap — define root BEFORE any usage
// ─────────────────────────────────────────────────────────────────────
define('WASAPBOT_ROOT', dirname(__DIR__));

// ─────────────────────────────────────────────────────────────────────
//  Session/auth gate (defense in depth)
// ─────────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isAdmin = !empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin';

// Determine if accessed from CRM (lamami.online) or standalone (admin.casawasap.com)
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$fromCRM = (strpos($host, 'lamami.online') !== false);

if (!$isAdmin) {
    $usersFile = WASAPBOT_ROOT . '/data/users.json';
    if (!file_exists($usersFile)) {
        // No users.json → legacy mode, panel open
    } elseif ($fromCRM) {
        // Accessed from CRM iframe → trust CRM auth, panel open
        // CRM user is always admin
        $isAdmin = true;
        $_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;
        $_SESSION['role'] = $_SESSION['role'] ?? 'admin';
        $_SESSION['username'] = $_SESSION['username'] ?? 'admin';
    } else {
        // Standalone access (admin.casawasap.com) → require login
        $loginUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                  . '://' . $host . '/login';
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>403 Prohibido</title></head><body style="background:#080d17;color:#f0f3fa;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0"><div style="text-align:center"><h1 style="color:#f87171;font-size:3rem">403</h1><p>Acceso restringido.</p><p style="margin-top:16px"><a href="' . h($loginUrl) . '" style="color:#f59e0b">Iniciar sesión</a></p></div></body></html>';
        exit;
    }
}
$adminUsername = $_SESSION['username'] ?? '';
$adminRole = $_SESSION['role'] ?? '';

// ─────────────────────────────────────────────────────────────────────
//  Bootstrap
// ─────────────────────────────────────────────────────────────────────

// PSR-4-like autoloader (same as index.php)
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

// Null logger for Memory (admin panel does not need real logging)
$nullLogger = new class implements \WasapBot\Core\LoggerInterface {
    public function emergency(string $message, array $context = []): void {}
    public function alert(string $message, array $context = []): void {}
    public function critical(string $message, array $context = []): void {}
    public function error(string $message, array $context = []): void {}
    public function warning(string $message, array $context = []): void {}
    public function notice(string $message, array $context = []): void {}
    public function info(string $message, array $context = []): void {}
    public function debug(string $message, array $context = []): void {}
    public function log(string $level, string $message, array $context = []): void {}
};

$config = new \WasapBot\Core\Config(WASAPBOT_ROOT);
$memory = new \WasapBot\Core\Memory($config, $nullLogger);

$modeFilePath = resolveConfigPath('bot.mode_file', 'data/.bot_mode');

// ─────────────────────────────────────────────────────────────────────
//  Helper functions
// ─────────────────────────────────────────────────────────────────────

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Get a config value as a safe string for HTML display.
 * Real newlines are preserved (not escaped) so textareas work correctly.
 */
function config_val(string $key, mixed $default = ''): string
{
    global $config;
    $value = $config->get($key, $default);
    if (is_array($value)) {
        return '';
    }
    return h((string) $value);
}

/** Get a config value as an array. */
function config_val_array(string $key, mixed $default = []): array
{
    global $config;
    $value = $config->get($key, $default);
    if (!is_array($value)) {
        return [];
    }
    return $value;
}

function checked(bool $cond): string
{
    return $cond ? 'checked' : '';
}

function selected(bool $cond): string
{
    return $cond ? 'selected' : '';
}

/**
 * Read the bot mode file, returning 'start', 'stop', or 'unknown'.
 */
function getBotMode(): string
{
    global $modeFilePath;
    if (!file_exists($modeFilePath)) {
        return 'unknown';
    }
    $content = trim((string) @file_get_contents($modeFilePath));
    if ($content === 'start') {
        return 'start';
    }
    if ($content === 'stop') {
        return 'stop';
    }
    return 'unknown';
}

/**
 * Write the bot mode file. Returns true on success, false on failure.
 *
 * Uses multiple fallback strategies (direct write, chmod+write, temp+rename)
 * to handle permission edge cases (e.g. file owned by root with 0644).
 */
function setBotMode(string $mode): bool
{
    global $modeFilePath;
    $dir = dirname($modeFilePath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $payload = ($mode === 'stop') ? 'stop' : 'start';
    clearstatcache(true, $modeFilePath);

    // Strategy 1: direct write
    if (@file_put_contents($modeFilePath, $payload, LOCK_EX) !== false) {
        @chmod($modeFilePath, 0664);
        return true;
    }

    // Strategy 2: chmod + write
    @chmod($modeFilePath, 0664);
    clearstatcache(true, $modeFilePath);
    if (@file_put_contents($modeFilePath, $payload, LOCK_EX) !== false) {
        @chmod($modeFilePath, 0664);
        return true;
    }

    // Strategy 3: temp file + rename (atomic, bypasses ownership)
    if (is_dir($dir) && is_writable($dir)) {
        $tmpPath = $dir . '/.bot_mode_tmp_' . uniqid('', true);
        if (@file_put_contents($tmpPath, $payload, LOCK_EX) !== false) {
            @chmod($tmpPath, 0664);
            if (@rename($tmpPath, $modeFilePath)) {
                @chmod($modeFilePath, 0664);
                return true;
            }
            // rename may fail across filesystems; try unlink+rename
            if (@unlink($modeFilePath) && @rename($tmpPath, $modeFilePath)) {
                @chmod($modeFilePath, 0664);
                return true;
            }
            @unlink($tmpPath);
        }
    }

    return false;
}

/**
 * Resolve a config file path: use it directly if absolute, otherwise
 * prepend WASAPBOT_ROOT. Mirrors Memory.php constructor logic.
 */
function resolveConfigPath(string $configKey, string $defaultValue): string
{
    global $config;
    $rawPath = (string) $config->get($configKey, $defaultValue);
    if (str_starts_with($rawPath, '/')) {
        return $rawPath;
    }
    return WASAPBOT_ROOT . '/' . ltrim($rawPath, '/');
}

// ─────────────────────────────────────────────────────────────────────
//  CSRF protection (time-based token with persistent random secret)
// ─────────────────────────────────────────────────────────────────────

/**
 * Load or generate a persistent random CSRF secret stored in a file.
 * This prevents token forgery even if the filesystem path is known.
 *
 * @return string 64-character hex secret
 */
function getCsrfSecret(): string
{
    $secretFile = (defined('WASAPBOT_ROOT') ? WASAPBOT_ROOT : __DIR__) . '/data/.csrf_secret';
    if (file_exists($secretFile)) {
        $secret = trim((string) @file_get_contents($secretFile));
        if (strlen($secret) >= 32) {
            return $secret;
        }
    }
    // Generate a new random secret (cryptographically secure)
    $secret = bin2hex(random_bytes(32));
    $dir = dirname($secretFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    @file_put_contents($secretFile, $secret, LOCK_EX);
    @chmod($secretFile, 0600);
    return $secret;
}

function generateCsrfToken(): string
{
    // Rotates every 10 minutes; uses persistent random secret
    $secret = getCsrfSecret();
    return hash_hmac('sha256', date('Y-m-d-H') . floor((int) date('i') / 10), $secret);
}

function validateCsrfToken(string $token): bool
{
    $secret = getCsrfSecret();

    // Accept current time window
    $current = hash_hmac('sha256', date('Y-m-d-H') . floor((int) date('i') / 10), $secret);
    if (hash_equals($current, $token)) {
        return true;
    }

    // Also accept previous time window — prevents false negatives when
    // the page was loaded just before a 10-minute boundary and submitted
    // shortly after.
    $prevSlot = max(0, floor((int) date('i') / 10) - 1);
    $previous = hash_hmac('sha256', date('Y-m-d-H') . $prevSlot, $secret);
    return hash_equals($previous, $token);
}

function requireValidCsrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '');
    if (!validateCsrfToken($token)) {
        http_response_code(403);
        exit('<h1>403 Forbidden</h1><p>CSRF token inválido o expirado. Recarga la página.</p>');
    }
}

// ─────────────────────────────────────────────────────────────────────
//  Router
// ─────────────────────────────────────────────────────────────────────

$action = (string) ($_GET['action'] ?? '');

// Determine base URL for redirects
$baseUrl = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
$baseUrl = rtrim($baseUrl, '/');

/**
 * Build redirect URL preserving the active tab so the user lands on the
 * same tab after any POST action instead of being thrown back to "Estado".
 * The active tab is sent as a hidden field `active_tab` in every form.
 */
function buildRedirectUrl(string $baseUrl, string $extraParam, string $overrideTab = ''): string
{
    $allowedTabs = [
        'tab-status', 'tab-descripcion', 'tab-prompt', 'tab-leads',
        'tab-waha', 'tab-ia', 'tab-routing', 'tab-delays',
        'tab-variants', 'tab-followup', 'tab-reminder', 'tab-urls',
        'tab-memory', 'tab-logs', 'tab-learning', 'tab-users',
    ];
    $tab = $overrideTab !== '' ? $overrideTab : (string) ($_POST['active_tab'] ?? $_GET['tab'] ?? '');
    if (!in_array($tab, $allowedTabs, true)) {
        $tab = '';
    }
    $tabParam = $tab !== '' ? '&tab=' . urlencode($tab) : '';
    return $baseUrl . '?' . $extraParam . $tabParam;
}

$method = $method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET');

// ── action=save_config (POST only, CSRF protected) ────────────────────
if ($method === 'POST' && $action === 'save_config') {
    requireValidCsrf();
    handleSaveConfig($config, $_POST);
    header('Location: ' . buildRedirectUrl($baseUrl, 'saved=1'));
    exit;
}

// ── action=toggle_bot (POST only, CSRF protected) ─────────────────────
if ($method === 'POST' && $action === 'toggle_bot') {
    requireValidCsrf();
    $current = getBotMode();
    $newMode = $current === 'start' ? 'stop' : 'start';
    $ok = setBotMode($newMode);
    if ($ok) {
        header('Location: ' . buildRedirectUrl($baseUrl, 'toggled=1'));
    } else {
        header('Location: ' . buildRedirectUrl($baseUrl, 'error=toggle_failed'));
    }
    exit;
}

// ── action=delete_memory_thread (POST only, CSRF protected) ────────────
if ($method === 'POST' && $action === 'delete_memory_thread') {
    requireValidCsrf();
    $threadId = (string) ($_POST['thread_id'] ?? '');
    $removed = 0;
    if ($threadId !== '') {
        $removed = $memory->deleteByThreadId($threadId);
    }
    header('Location: ' . buildRedirectUrl($baseUrl, 'deleted=' . $removed));
    exit;
}

// ── action=delete_memory_line (POST only, CSRF protected) ──────────────
if ($method === 'POST' && $action === 'delete_memory_line') {
    requireValidCsrf();
    $lineIndex = (int) ($_POST['line_index'] ?? -1);
    $memory->deleteByLineIndex($lineIndex);
    header('Location: ' . buildRedirectUrl($baseUrl, 'deleted_line=1'));
    exit;
}

// ── action=clear_memory (POST only, CSRF protected) ────────────────────
if ($method === 'POST' && $action === 'clear_memory') {
    requireValidCsrf();
    $memory->clear();
    header('Location: ' . buildRedirectUrl($baseUrl, 'cleared=1'));
    exit;
}

// ── action=delete_memory_line_ajax (POST, AJAX JSON response) ─────────
if ($method === 'POST' && $action === 'delete_memory_line_ajax') {
    requireValidCsrf();
    $lineIndex = (int) ($_POST['line_index'] ?? -1);
    $memory->deleteByLineIndex($lineIndex);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'deleted_line' => $lineIndex], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── action=get_thread_conversation (GET, JSON response) ────────────────
if ($method === 'GET' && $action === 'get_thread_conversation') {
    $threadId = (string) ($_GET['thread_id'] ?? '');
    $records  = getThreadConversation($config, $threadId);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'thread_id' => $threadId, 'records' => $records], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── action=get_telefonos_lines (GET, JSON response) ─────────────────────
if ($method === 'GET' && $action === 'get_telefonos_lines') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'lines' => getTelefonosLines()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── action=get_waha_statuses (GET, JSON response) ──────────────────────
if ($method === 'GET' && $action === 'get_waha_statuses') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok'     => true,
        'status' => getWahaStatusesForRouting($config),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── action=get_learning_stats (GET, JSON response) ────────────────────
if ($method === 'GET' && $action === 'get_learning_stats') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(getLearningStats($config), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── action=get_playbook (GET, JSON response) ──────────────────────────
if ($method === 'GET' && $action === 'get_playbook') {
    header('Content-Type: application/json; charset=utf-8');
    $playbookPath = resolveConfigPath('files.playbook', 'data/playbook.md');
    $content = '';
    $lastModified = null;
    if (file_exists($playbookPath) && is_readable($playbookPath)) {
        $content = (string) @file_get_contents($playbookPath);
        $lastModified = date('c', filemtime($playbookPath));
    }
    echo json_encode([
        'ok'            => true,
        'content'       => $content,
        'last_modified' => $lastModified,
        'exists'        => $content !== '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── action=force_learn (POST only) ────────────────────────────────────
if ($method === 'POST' && $action === 'force_learn') {
    header('Content-Type: application/json; charset=utf-8');

    $learnScript = WASAPBOT_ROOT . '/cron/learn.php';
    if (!file_exists($learnScript)) {
        echo json_encode(['ok' => false, 'error' => 'learn.php not found']);
        exit;
    }

    // Run learn.php in BACKGROUND to avoid web server timeout.
    // Output goes to a temp file; frontend polls get_learn_status until done.
    $outFile = sys_get_temp_dir() . '/learn_output_' . date('Ymd_His') . '.txt';
    $markerFile = sys_get_temp_dir() . '/learn_marker.txt';

    // Mark as "running" (write with trailing newline so shell append works correctly)
    file_put_contents($markerFile, "running\n" . $outFile . "\n");

    // Run learn.php in BACKGROUND via bash -c to ensure DONE marker is written
    $shellCmd = sprintf(
        'php %s --days=1 > %s 2>&1; echo "DONE:$?" >> %s',
        escapeshellarg($learnScript),
        escapeshellarg($outFile),
        escapeshellarg($markerFile)
    );
    exec('bash -c ' . escapeshellarg($shellCmd) . ' > /dev/null 2>&1 &');

    echo json_encode([
        'ok'         => true,
        'status'     => 'started',
        'out_file'   => $outFile,
        'marker_file'=> $markerFile,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── action=get_learn_status (GET, JSON) ──────────────────────────────
if ($method === 'GET' && $action === 'get_learn_status') {
    header('Content-Type: application/json; charset=utf-8');

    $markerFile = sys_get_temp_dir() . '/learn_marker.txt';
    $outFile = '';
    $status = 'idle';
    $output = '';

    if (file_exists($markerFile)) {
        $lines = @file($markerFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines) {
            // Check LAST line for DONE marker (appended after completion)
            $lastLine = end($lines);
            if (str_starts_with($lastLine, 'DONE:')) {
                $status = 'done';
                $exitCode = (int) substr($lastLine, 5);
                // Out file path is on line 2 (index 1)
                $outFile = $lines[1] ?? '';
                if ($outFile && file_exists($outFile)) {
                    $output = (string) @file_get_contents($outFile);
                    @unlink($outFile);
                    @unlink($markerFile);
                }
            } elseif ($lines[0] === 'running') {
                $status = 'running';
                $outFile = $lines[1] ?? '';
            } else {
                @unlink($markerFile);
            }
        } else {
            @unlink($markerFile);
        }
    }

    echo json_encode([
        'ok'       => true,
        'status'   => $status,
        'output'   => $output,
        'is_error' => ($status === 'done' && isset($exitCode) && $exitCode !== 0),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── action=confirm_outcome (POST, JSON) ───────────────────────────────
if ($method === 'POST' && $action === 'confirm_outcome') {
    header('Content-Type: application/json; charset=utf-8');
    $raw = (string) @file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }
    $threadId   = (string) ($body['thread_id'] ?? '');
    $newOutcome = (string) ($body['outcome'] ?? '');
    if ($threadId === '' || !in_array($newOutcome, ['lead_confirmado', 'lead_ghosted', 'mareador'], true)) {
        echo json_encode(['ok' => false, 'error' => 'Missing or invalid thread_id/outcome']);
        exit;
    }
    $updated = updateOutcomeHuman($config, $threadId, $newOutcome);
    echo json_encode(['ok' => $updated, 'thread_id' => $threadId, 'outcome' => $newOutcome]);
    exit;
}

// ── action=get_outcomes (GET, JSON) ───────────────────────────────────
if ($method === 'GET' && $action === 'get_outcomes') {
    header('Content-Type: application/json; charset=utf-8');
    $outcomes = getOutcomesForDisplay($config);
    echo json_encode(['ok' => true, 'outcomes' => $outcomes], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ─────────────────────────────────────────────────────────────────────
//  User management actions (admin only)
// ─────────────────────────────────────────────────────────────────────

$userManager = new \WasapBot\Core\UserManager(WASAPBOT_ROOT);

// ── action=save_user (POST) ──────────────────────────────────────────
if ($method === 'POST' && $action === 'save_user') {
    requireValidCsrf();
    $userId   = (int) ($_POST['user_id'] ?? 0);
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $role     = (string) ($_POST['role'] ?? 'user');
    $name     = trim((string) ($_POST['name'] ?? ''));
    $active   = isset($_POST['active']) ? (bool) $_POST['active'] : true;

    if ($userId > 0) {
        // Update existing user
        $fields = ['username' => $username, 'role' => $role, 'name' => $name, 'active' => $active];
        if ($password !== '') {
            $fields['password'] = $password;
        }
        $result = $userManager->updateUser($userId, $fields);
        if ($result['ok']) {
            $redirectMsg = 'user_updated=1';
        } else {
            $redirectMsg = 'user_error=' . urlencode($result['error'] ?? 'Error desconocido');
        }
    } else {
        // Create new user
        $result = $userManager->createUser($username, $password, $role, $name);
        if ($result['ok']) {
            $redirectMsg = 'user_created=1';
        } else {
            $redirectMsg = 'user_error=' . urlencode($result['error'] ?? 'Error desconocido');
        }
    }
    header('Location: ' . buildRedirectUrl($baseUrl, $redirectMsg, 'tab-users'));
    exit;
}

// ── action=delete_user (POST) ────────────────────────────────────────
if ($method === 'POST' && $action === 'delete_user') {
    requireValidCsrf();
    $userId = (int) ($_POST['user_id'] ?? 0);
    if ($userId > 0 && $userId !== 1) {
        $userManager->deleteUser($userId);
    }
    header('Location: ' . buildRedirectUrl($baseUrl, 'user_deleted=1', 'tab-users'));
    exit;
}

// ─────────────────────────────────────────────────────────────────────
//  Save config handler
// ─────────────────────────────────────────────────────────────────────

/**
 * Keys whose value comes from a textarea (one entry per line) and must
 * be converted to an array on save.
 */
function isTextareaArrayKey(string $key): bool
{
    static $keys = [
        'telegram.chat_ids',
        'message_variants.audio_auto_reply',
        'message_variants.dedup_start',
        'message_variants.dedup_end',
        'cron.followup.intro_variants',
        'cron.followup.closing_variants',
        'cron.reminder.message_variants',
        'routing.sender_blacklist',
    ];
    return in_array($key, $keys, true);
}

/**
 * Recursively save POST data into Config via dotted-notation keys.
 */
function recursiveSave(\WasapBot\Core\Config $config, array $data, string $prefix = ''): array
{
    $savedKeys = [];

    foreach ($data as $key => $value) {
        $fullKey = ($prefix === '') ? (string) $key : $prefix . '.' . (string) $key;

        // array-of-arrays (e.g., routing.lines table rows) — save as a whole.
        // Use array_values() to re-index and handle client-side gaps from row deletion.
        // Only treat as table rows if the original array has exclusively numeric keys.
        // Without this guard, associative arrays whose values happen to be arrays
        // (like cron[followup][...], cron[reminder][...]) would be flattened into an
        // indexed list, breaking the config structure.
        if (is_array($value) && !empty($value)) {
            $reindexed = array_values($value);
            if (is_array($reindexed[0] ?? null)) {
                $isRowList = true;
                foreach (array_keys($value) as $k) {
                    if (!is_int($k) && (!is_string($k) || !ctype_digit($k))) {
                        $isRowList = false;
                        break;
                    }
                }
                if ($isRowList) {
                    $cleanLines = [];
                    foreach ($reindexed as $line) {
                        if (!is_array($line)) {
                            continue;
                        }
                        // Skip completely empty rows
                        $hasContent = false;
                        foreach ($line as $v) {
                            if (is_string($v) && trim($v) !== '') {
                                $hasContent = true;
                                break;
                            }
                            if (is_int($v) || is_float($v)) {
                                $hasContent = true;
                                break;
                            }
                        }
                        if (!$hasContent) {
                            continue;
                        }
                        // Type coercion per field
                        if (isset($line['port'])) {
                            $line['port'] = (int) $line['port'];
                        }
                        if (!isset($line['enabled'])) {
                            $line['enabled'] = false;
                        } else {
                            $line['enabled'] = (bool) $line['enabled'];
                        }
                        // ai_provider: ensure valid values only
                        if (!isset($line['ai_provider']) || !in_array((string) $line['ai_provider'], ['openai', 'deepseek'], true)) {
                            $line['ai_provider'] = 'openai';
                        }
                        // ai_model: null if empty string
                        if (isset($line['ai_model']) && $line['ai_model'] === '') {
                            $line['ai_model'] = null;
                        }
                        $cleanLines[] = $line;
                    }
                    $config->set($fullKey, $cleanLines);
                    $savedKeys[] = $fullKey;
                    continue;
                }
            }
        }

        // nested associative arrays — recurse
        // Guard: skip empty arrays for known structural keys to avoid wiping config sections
        if (is_array($value) && array_keys($value) !== range(0, count($value) - 1)) {
            if (empty($value)) {
                // Empty associative array — do not overwrite existing config section
                continue;
            }
            $nested = recursiveSave($config, $value, $fullKey);
            $savedKeys = array_merge($savedKeys, $nested);
            continue;
        }

        // Scalar or list array — save directly
        $processedValue = processValue($fullKey, $value);
        $config->set($fullKey, $processedValue);
        $savedKeys[] = $fullKey;
    }

    return $savedKeys;
}

/**
 * Process a single config value: textarea → array, CRLF→LF, numeric casting.
 */
function processValue(string $key, mixed $value): mixed
{
    if (!is_string($value)) {
        return $value;
    }

    // Textarea arrays: split by newlines
    if (isTextareaArrayKey($key)) {
        $lines = array_map('trim', explode("\n", str_replace("\r\n", "\n", $value)));
        return array_values(array_filter($lines, static fn(string $line): bool => $line !== ''));
    }

    // Prompt fields (template, sections, legacy): normalize CRLF to LF
    if ($key === 'prompt.system_prompt' || $key === 'prompt.template' || str_starts_with($key, 'prompt.sections.')) {
        return str_replace("\r\n", "\n", $value);
    }

    // Numeric validation: cast numeric config keys server-side
    return castNumericValue($key, $value);
}

/**
 * Numeric config keys that must be cast server-side.
 * Prevents injection of arbitrary strings into numeric fields.
 */
function castNumericValue(string $key, string $value): mixed
{
    static $intKeys = [
        'waha.default_port', 'openai.tone_max_tokens',
        'human_delays.seen.fallback_sec', 'human_delays.seen.random_min_sec', 'human_delays.seen.random_max_sec',
        'human_delays.typing.fallback_sec', 'human_delays.typing.chars_per_sec_min', 'human_delays.typing.chars_per_sec_max',
        'human_delays.typing.chunk_size', 'human_delays.typing.start_min_ms', 'human_delays.typing.start_max_ms',
        'human_delays.typing.max_incoming_chars',
        'human_delays.read.base_min_ms', 'human_delays.read.base_max_ms', 'human_delays.read.per_char_ms',
        'human_delays.read.clamp_min_ms', 'human_delays.read.clamp_max_ms',
        'human_delays.presend_sleep_sec', 'catalog.max_girls_without_explicit_request',
        'catalog.girls_json_timeout_ms',
        'cron.followup.max_leads_per_run', 'cron.followup.curl_timeout_sec',
        'cron.followup.girls_cache_ttl_sec', 'cron.followup.min_interval_hours_min', 'cron.followup.min_interval_hours_max',
        'cron.followup.inter_lead_wait_min_sec', 'cron.followup.inter_lead_wait_max_sec',
        'cron.reminder.max_per_run', 'cron.reminder.curl_timeout_sec', 'cron.reminder.cleanup_interval',
        'cron.reminder.cleanup_max_age_sec', 'dedup_coalesce.lock_acquire_tries', 'dedup_coalesce.lead_log_lock_tries',
        'dedup_coalesce.dedup_file_ttl_minutes',
    ];

    static $floatKeys = [
        'openai.tone_temperature', 'openai.temperature', 'deepseek.temperature',
        'human_delays.typing.chunk_pause_factor',
        'human_delays.habituation.start_boost', 'human_delays.habituation.decay', 'human_delays.habituation.floor',
        'human_delays.short_typing_sec', 'human_delays.after_send_fallback_sec',
        'dedup_coalesce.lock_acquire_sleep_sec', 'dedup_coalesce.lead_log_lock_sleep_sec',
        'dedup_coalesce.coalesce_window_sec', 'dedup_coalesce.coalesce_sleep_before_send_sec',
    ];

    if (in_array($key, $intKeys, true)) {
        return (int) $value;
    }
    if (in_array($key, $floatKeys, true)) {
        return (float) $value;
    }
    // Boolean keys: store as proper bool (not string "0"/"1")
    static $boolKeys = [
        'telegram.alert_enabled',
        'routing.default_enabled_if_not_found',
        'bot.auto_off_on_lead',
        'cron.followup.enabled',
        'cron.reminder.enabled',
    ];
    if (in_array($key, $boolKeys, true)) {
        return (bool) $value;
    }
    return $value;
}

/**
 * Handle POST save_config action.
 */
/**
 * Walk a dotted key into the POST array to see if it exists.
 */
function keyExistsInPost(string $dottedKey, array $postData): bool
{
    $ptr = $postData;
    foreach (explode('.', $dottedKey) as $segment) {
        if (!is_array($ptr) || !array_key_exists($segment, $ptr)) {
            return false;
        }
        $ptr = $ptr[$segment];
    }
    return true;
}

/**
 * Handle POST save_config action.
 */
function handleSaveConfig(\WasapBot\Core\Config $config, array $postData): void
{
    $processedKeys = recursiveSave($config, $postData);

    // Explicitly set false for checkboxes that were not present in POST
    $checkboxKeys = [
        'telegram.alert_enabled',
        'routing.default_enabled_if_not_found',
        'bot.auto_off_on_lead',
        'cron.followup.enabled',
        'cron.reminder.enabled',
    ];

    foreach ($checkboxKeys as $ck) {
        if (!in_array($ck, $processedKeys, true) && !keyExistsInPost($ck, $postData)) {
            $config->set($ck, false);
        }
    }

    $config->save();
}

// ─────────────────────────────────────────────────────────────────────
//  Render the admin panel HTML
// ─────────────────────────────────────────────────────────────────────

// ─────────────────────────────────────────────────────────────────────
//  Statistics helpers
// ─────────────────────────────────────────────────────────────────────

/**
 * Read and parse a NDJSON file, returning array of decoded records.
 *
 * @return list<array<string, mixed>>
 */
function readNdjson(string $filePath): array
{
    if (!file_exists($filePath)) {
        return [];
    }
    $fp = @fopen($filePath, 'rb');
    if ($fp === false) {
        return [];
    }
    $records = [];
    try {
        if (flock($fp, LOCK_SH)) {
            rewind($fp);
            while (($line = fgets($fp)) !== false) {
                $trimmed = trim($line);
                if ($trimmed === '') {
                    continue;
                }
                try {
                    $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        $records[] = $decoded;
                    }
                } catch (\JsonException) {
                    // skip malformed lines
                }
            }
            flock($fp, LOCK_UN);
        }
    } finally {
        fclose($fp);
    }
    return $records;
}

/**
 * Compute global stats from leads.ndjson and session_memory.ndjson.
 *
 * @return array{
 *   conversations_total: int,
 *   conversations_today: int,
 *   leads_total: int,
 *   leads_today: int
 * }
 */
function getBotStats(\WasapBot\Core\Config $config): array
{
    $leadsPath   = resolveConfigPath('files.leads', 'data/leads.ndjson');
    $memoryPath  = resolveConfigPath('files.session_memory', 'data/session_memory.ndjson');

    $todayStr = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))->format('Y-m-d');

    // ── Leads ──
    $leadsTotal = 0;
    $leadsToday = 0;
    foreach (readNdjson($leadsPath) as $lead) {
        $leadsTotal++;
        $ts = (string) ($lead['ts'] ?? '');
        if (str_starts_with($ts, $todayStr)) {
            $leadsToday++;
        }
    }

    // ── Conversations (unique thread_ids in session memory) ──
    $allThreads   = [];
    $todayThreads = [];
    foreach (readNdjson($memoryPath) as $record) {
        $threadId = (string) ($record['thread_id'] ?? '');
        if ($threadId === '') {
            continue;
        }
        $allThreads[$threadId] = true;

        // Detect date from timestamp or ts field
        $ts = (string) ($record['ts'] ?? $record['timestamp'] ?? '');
        if (str_starts_with($ts, $todayStr)) {
            $todayThreads[$threadId] = true;
        }
    }

    return [
        'conversations_total' => count($allThreads),
        'conversations_today' => count($todayThreads),
        'leads_total'         => $leadsTotal,
        'leads_today'         => $leadsToday,
    ];
}

/**
 * Load all leads for display, sorted newest first.
 *
 * @return list<array<string, mixed>>
 */
function getLeadsForDisplay(\WasapBot\Core\Config $config): array
{
    $leadsPath = resolveConfigPath('files.leads', 'data/leads.ndjson');
    $records   = readNdjson($leadsPath);
    // newest first
    usort($records, static function (array $a, array $b): int {
        return strcmp((string) ($b['ts'] ?? ''), (string) ($a['ts'] ?? ''));
    });
    return $records;
}

/**
 * Load all memory records belonging to a given thread_id.
 *
 * @return list<array<string, mixed>>
 */
function getThreadConversation(\WasapBot\Core\Config $config, string $threadId): array
{
    if ($threadId === '') {
        return [];
    }
    $memoryPath = resolveConfigPath('files.session_memory', 'data/session_memory.ndjson');
    if (!file_exists($memoryPath)) {
        return [];
    }
    $result = [];
    $fp = @fopen($memoryPath, 'rb');
    if ($fp === false) {
        return [];
    }
    try {
        if (flock($fp, LOCK_SH)) {
            rewind($fp);
            $lineIdx = 0;
            while (($line = fgets($fp)) !== false) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    try {
                        $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
                        if (is_array($decoded) && (string) ($decoded['thread_id'] ?? '') === $threadId) {
                            $decoded['line_index'] = $lineIdx;
                            $result[] = $decoded;
                        }
                    } catch (\JsonException) {
                        // skip malformed
                    }
                    $lineIdx++;
                }
            }
            flock($fp, LOCK_UN);
        }
    } finally {
        fclose($fp);
    }
    return $result;
}

/**
 * Load all lines from telefonos.json (CRM phone registry).
 * Returns every entry that has a phone number, regardless of 'uso'.
 *
 * @return list<array{id:string, nombre:string, tfono:string, uso:string, waha_port:string, waha:string}>
 */
function getTelefonosLines(): array
{
    // telefonos.json lives two levels up from bot-casa/ (project root /data/)
    $candidates = [
        WASAPBOT_ROOT . '/../../data/telefonos.json',
        WASAPBOT_ROOT . '/../data/telefonos.json',
        WASAPBOT_ROOT . '/data/telefonos.json',
        dirname(WASAPBOT_ROOT, 3) . '/data/telefonos.json',
    ];

    $raw = null;
    foreach ($candidates as $path) {
        $real = realpath($path);
        if ($real !== false && file_exists($real)) {
            $contents = @file_get_contents($real);
            if ($contents !== false) {
                $raw = $contents;
                break;
            }
        }
    }

    if ($raw === null) {
        return [];
    }

    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
        return [];
    }

    if (!is_array($decoded)) {
        return [];
    }

    $result = [];
    foreach ($decoded as $t) {
        if (!is_array($t)) {
            continue;
        }
        $tfono = trim((string) ($t['tfono'] ?? ''));
        if ($tfono === '') {
            continue;
        }
        $result[] = [
            'id'        => (string) ($t['id'] ?? ''),
            'nombre'    => (string) ($t['nombre'] ?? ''),
            'tfono'     => $tfono,
            'uso'       => (string) ($t['uso'] ?? ''),
            'waha_port' => (string) ($t['waha_port'] ?? ''),
            'waha'      => (string) ($t['waha'] ?? ''),
            'notas'     => (string) ($t['notas'] ?? ''),
        ];
    }

    return $result;
}

/**
 * Read all memory lines and parse them for display.
 *
 * @return list<array{line_index:int, thread_id:string, phone:string, timestamp:string, preview:string, raw_json:string}>
 */
function getMemoryDisplayLines(\WasapBot\Core\Memory $memory): array
{
    $rawLines = $memory->getLines();
    $displayLines = [];

    foreach ($rawLines as $i => $rawLine) {
        try {
            $decoded = json_decode($rawLine, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $decoded = null;
        }

        if (!is_array($decoded)) {
            $displayLines[] = [
                'line_index' => $i,
                'thread_id'  => '(invalid)',
                'phone'      => '',
                'timestamp'  => '',
                'preview'    => h(mb_substr($rawLine, 0, 60)),
                'raw_json'   => h($rawLine),
            ];
            continue;
        }

        $record    = $decoded;
        $threadId  = (string) ($record['thread_id'] ?? '');
        $phone     = (string) ($record['phone'] ?? '');
        // Support both new format (ts) and legacy (timestamp)
        $timestamp = (string) ($record['ts'] ?? $record['timestamp'] ?? '');

        // Build preview — new format uses user_msg/bot_reply; legacy uses '| B:' / 'body'
        $previewText = '';
        if (!empty($record['bot_reply'])) {
            $previewText = '[Bot] ' . (string) $record['bot_reply'];
        } elseif (!empty($record['user_msg'])) {
            $previewText = '[User] ' . (string) $record['user_msg'];
        } elseif (isset($record['| B:'])) {
            $previewText = '[Bot] ' . (string) $record['| B:'];
        } elseif (isset($record['body'])) {
            $previewText = '[User] ' . (string) $record['body'];
        } else {
            foreach ($record as $k => $v) {
                if (in_array($k, ['thread_id', 'phone', 'ts', 'timestamp', 'line_index', '_seq',
                                  'speaker_girl_id', 'speaker_girl_name', 'speaker_mode',
                                  'selected_girl_id', 'selected_girl_name'], true)) {
                    continue;
                }
                if (is_string($v) && $v !== '') {
                    $previewText = $v;
                    break;
                }
            }
        }

        // Build full preview: show both user and bot messages on one line
        $userMsg = !empty($record['user_msg']) ? (string) $record['user_msg'] : ((string) ($record['body'] ?? ''));
        $botMsg  = !empty($record['bot_reply']) ? (string) $record['bot_reply'] : ((string) ($record['| B:'] ?? ''));
        $fullPreview = '';
        if ($userMsg !== '') {
            $fullPreview = '[U] ' . $userMsg;
        }
        if ($botMsg !== '') {
            if ($fullPreview !== '') {
                $fullPreview .= '  →  ';
            }
            $fullPreview .= '[B] ' . $botMsg;
        }
        if ($fullPreview === '') {
            $fullPreview = $previewText;
        }

        $displayLines[] = [
            'line_index' => $i,
            'thread_id'  => $threadId,
            'phone'      => $phone,
            'timestamp'  => $timestamp,
            'preview'    => h(mb_substr($fullPreview, 0, 120)),
            'raw_json'   => h($rawLine),
        ];
    }

    return $displayLines;
}

/**
 * Group memory display lines by phone → thread_id.
 *
 * @param list<array{line_index:int, thread_id:string, phone:string, timestamp:string, preview:string, raw_json:string}> $lines
 * @return list<array{phone:string, threads:list<array{thread_id:string, lines:list<array>, last_ts:string}>}>
 */
function getMemoryGroups(array $lines): array
{
    $byPhone = [];
    foreach ($lines as $ml) {
        $phone = $ml['phone'];
        $tid   = $ml['thread_id'];
        if ($phone === '') {
            $phone = '(sin teléfono)';
        }
        if ($tid === '') {
            $tid = '(sin thread)';
        }
        if (!isset($byPhone[$phone])) {
            $byPhone[$phone] = [];
        }
        if (!isset($byPhone[$phone][$tid])) {
            $byPhone[$phone][$tid] = ['thread_id' => $tid, 'lines' => [], 'last_ts' => ''];
        }
        $byPhone[$phone][$tid]['lines'][] = $ml;
        $ts = $ml['timestamp'];
        if ($ts > $byPhone[$phone][$tid]['last_ts']) {
            $byPhone[$phone][$tid]['last_ts'] = $ts;
        }
    }

    // Sort groups: most recent phone first
    $result = [];
    foreach ($byPhone as $phone => $threads) {
        // Sort threads within phone by last_ts descending
        uasort($threads, static fn(array $a, array $b): int => strcmp($b['last_ts'], $a['last_ts']));
        $result[] = ['phone' => (string) $phone, 'threads' => array_values($threads)];
    }
    // Sort phones: max last_ts across threads descending
    usort($result, static function (array $a, array $b): int {
        $maxA = array_reduce($a['threads'], static fn(string $carry, array $t): string => max($carry, $t['last_ts']), '');
        $maxB = array_reduce($b['threads'], static fn(string $carry, array $t): string => max($carry, $t['last_ts']), '');
        return strcmp($maxB, $maxA);
    });

    return $result;
}

/**
 * Look up the "notas" field from telefonos.json for a given last9 phone digits.
 * Falls back to "nombre" if no notas found. Returns empty string if not found.
 *
 * This mirrors the field that arrives via Telegram lead notification (lineNotas).
 */
function getLineDescription(string $last9): string
{
    static $telefonosCache = null;
    if ($telefonosCache === null) {
        $telefonosCache = getTelefonosLines();
    }

    if ($last9 === '') {
        return '';
    }

    foreach ($telefonosCache as $t) {
        $tDigits = preg_replace('/[^0-9]/', '', (string) ($t['tfono'] ?? ''));
        if ($tDigits !== '' && strlen($tDigits) >= 9) {
            $tLast9 = substr($tDigits, -9);
            if ($tLast9 === $last9) {
                $notas = trim((string) ($t['notas'] ?? ''));
                if ($notas !== '') {
                    return $notas;
                }
                return (string) ($t['nombre'] ?? '');
            }
        }
    }

    return '';
}

/**
 * Check WAHA session status for all routing lines.
 *
 * Returns a list of arrays with 'port', 'label', 'last9', 'status', 'status_label'.
 *
 * @param \WasapBot\Core\ConfigInterface $config
 * @return list<array{port: string, label: string, last9: string, status: string, status_label: string}>
 */
function getWahaStatusesForRouting(\WasapBot\Core\ConfigInterface $config): array
{
    $lines     = config_val_array('routing.lines');
    $baseIp    = (string) $config->get('waha.base_ip', '100.117.92.74');
    $apiKey    = (string) $config->get('waha.api_key', '');
    $session   = (string) $config->get('waha.session', 'default');
    $timeout   = 5; // fast timeout — don't block the panel

    $result = [];
    foreach ($lines as $line) {
        if (!is_array($line)) continue;
        $port   = (string) ($line['port'] ?? '');
        $label  = (string) ($line['label'] ?? '');
        $last9  = (string) ($line['last9'] ?? '');

        if ($port === '') {
            $result[] = [
                'port'         => $port,
                'label'        => $label,
                'last9'        => $last9,
                'status'       => 'unknown',
                'status_label' => 'Sin puerto',
            ];
            continue;
        }

        $url = 'http://' . $baseIp . ':' . $port . '/api/sessions/' . rawurlencode($session);
        $ch  = curl_init($url);
        if ($ch === false) {
            $result[] = ['port' => $port, 'label' => $label, 'last9' => $last9, 'status' => 'error', 'status_label' => 'Error'];
            continue;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'X-Api-Key: ' . $apiKey,
            ],
        ]);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($body === false || $err !== '') {
            $result[] = ['port' => $port, 'label' => $label, 'last9' => $last9, 'status' => 'error', 'status_label' => 'Sin respuesta'];
            continue;
        }

        $decoded = json_decode($body, true);
        $sessionStatus = strtoupper(trim((string) (is_array($decoded) ? ($decoded['status'] ?? '') : '')));

        $statusLabel = match ($sessionStatus) {
            'WORKING'  => 'Activa',
            'STARTING' => 'Arrancando',
            'SCAN_QR'  => 'Esperando QR',
            'STOPPED'  => 'Detenida',
            'FAILED'   => 'Fallida',
            default    => ($sessionStatus !== '' ? $sessionStatus : ($httpCode >= 200 && $httpCode < 300 ? 'OK' : 'HTTP ' . $httpCode)),
        };

        $cssStatus = match ($sessionStatus) {
            'WORKING'  => 'up',
            'STARTING' => 'starting',
            default    => 'down',
        };

        $result[] = [
            'port'         => $port,
            'label'        => $label,
            'last9'        => $last9,
            'status'       => $cssStatus,
            'status_label' => $statusLabel,
        ];
    }

    return $result;
}

/**
 * Render routing lines table rows as HTML.
 *
 * @param list<array<string, mixed>> $lines
 */
function renderRoutingLines(array $lines): string
{
    $html = '';
    foreach ($lines as $idx => $line) {
        $last9      = h((string) ($line['last9'] ?? ''));
        $port       = h((string) ($line['port'] ?? ''));
        $label      = h((string) ($line['label'] ?? ''));
        $enabled    = (bool) ($line['enabled'] ?? true);
        $aiProvider = (string) ($line['ai_provider'] ?? 'openai');
        $aiModel    = (string) ($line['ai_model'] ?? '');
        $chk        = $enabled ? 'checked' : '';
        $openaiSel  = ($aiProvider === 'openai') ? 'selected' : '';
        $deepseekSel = ($aiProvider === 'deepseek') ? 'selected' : '';

        // Descripción: lookup notas from telefonos.json (same field Telegram lead uses)
        $descripcion = getLineDescription($last9);

        $html .= <<<ROW
        <tr class="routing-row" data-port="{$port}" data-last9="{$last9}">
            <td><input type="text" name="routing[lines][{$idx}][last9]" value="{$last9}" placeholder="Últimos 9 dígitos" class="input-cell"></td>
            <td><input type="number" name="routing[lines][{$idx}][port]" value="{$port}" placeholder="3000" class="input-cell" style="width:80px"></td>
            <td><input type="text" name="routing[lines][{$idx}][label]" value="{$label}" placeholder="linea_3000" class="input-cell"></td>
            <td class="descripcion-cell" title="{$descripcion}">{$descripcion}</td>
            <td>
                <select name="routing[lines][{$idx}][ai_provider]" class="input-cell" style="width:110px">
                    <option value="openai" {$openaiSel}>OpenAI</option>
                    <option value="deepseek" {$deepseekSel}>DeepSeek</option>
                </select>
                <input type="hidden" name="routing[lines][{$idx}][ai_model]" value="{$aiModel}">
            </td>
            <td style="text-align:center"><input type="hidden" name="routing[lines][{$idx}][enabled]" value="0"><input type="checkbox" name="routing[lines][{$idx}][enabled]" value="1" {$chk}></td>
            <td class="waha-status-cell" data-port="{$port}"><span class="status-dot status-unknown"></span> —</td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button></td>
        </tr>
        ROW;
    }
    return $html;
}

// ─── Build bot status info ───
$botMode = getBotMode();
$botStatusLabel = match ($botMode) {
    'start' => 'ON - Activo',
    'stop'  => 'OFF - Detenido',
    default => 'Desconocido',
};
$botStatusClass = match ($botMode) {
    'start' => 'status-on',
    'stop'  => 'status-off',
    default => 'status-unknown',
};

// ─── Notification messages ───
$notification = '';
if (isset($_GET['saved'])) {
    $notification = '<div class="alert alert-success">Configuración guardada correctamente.</div>';
}
if (isset($_GET['toggled'])) {
    $notification = '<div class="alert alert-info">Estado del bot cambiado.</div>';
}
if (isset($_GET['error']) && $_GET['error'] === 'toggle_failed') {
    $notification = '<div class="alert alert-error">No se pudo cambiar el estado del bot. Revisa los permisos del archivo <code>' . h(basename($modeFilePath)) . '</code> en <code>' . h(dirname($modeFilePath)) . '</code>.</div>';
}
if (isset($_GET['deleted'])) {
    $n = (int) $_GET['deleted'];
    $notification = '<div class="alert alert-info">Eliminadas ' . $n . ' entrada(s) de memoria.</div>';
}
if (isset($_GET['deleted_line'])) {
    $notification = '<div class="alert alert-info">Línea de memoria eliminada.</div>';
}
if (isset($_GET['cleared'])) {
    $notification = '<div class="alert alert-warning">Memoria completamente vaciada.</div>';
}

// ─── Learning helpers ─────────────────────────────────────────────────────

function getLearningStats(\WasapBot\Core\Config $config): array
{
    $outcomesFile = resolveConfigPath('files.conversation_outcomes', 'public/data/conversation_outcomes.ndjson');
    $playbookFile = resolveConfigPath('files.playbook', 'public/data/playbook.md');
    $stats = [
        'total_classified'  => 0,
        'lead_probable'     => 0,
        'lead_confirmado'   => 0,
        'lead_ghosted'      => 0,
        'mareador'          => 0,
        'hostil'            => 0,
        'muerta'            => 0,
        'playbook_exists'   => false,
        'playbook_updated'  => null,
        'playbook_size'     => 0,
        'pending_review'    => 0,
    ];
    if (file_exists($outcomesFile)) {
        $lines = file($outcomesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $rec = json_decode(trim($line), true);
                if (!is_array($rec)) continue;
                $stats['total_classified']++;
                $outcome = (string) ($rec['outcome'] ?? '');
                if (isset($stats[$outcome])) $stats[$outcome]++;
                if ($outcome === 'lead_probable' && empty($rec['human_confirmed'])) $stats['pending_review']++;
            }
        }
    }
    if (file_exists($playbookFile)) {
        $stats['playbook_exists'] = true;
        $stats['playbook_updated'] = date('c', filemtime($playbookFile));
        $stats['playbook_size'] = filesize($playbookFile) ?: 0;
    }
    return $stats;
}

function getOutcomesForDisplay(\WasapBot\Core\Config $config): array
{
    $outcomesFile = resolveConfigPath('files.conversation_outcomes', 'public/data/conversation_outcomes.ndjson');
    $results = [];
    if (!file_exists($outcomesFile)) return $results;
    $lines = file($outcomesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return $results;
    $lines = array_reverse(array_slice($lines, -100));
    foreach ($lines as $line) {
        $rec = json_decode(trim($line), true);
        if (is_array($rec)) $results[] = $rec;
    }
    return $results;
}

function updateOutcomeHuman(\WasapBot\Core\Config $config, string $threadId, string $newOutcome): bool
{
    $outcomesFile = resolveConfigPath('files.conversation_outcomes', 'public/data/conversation_outcomes.ndjson');
    if (!file_exists($outcomesFile)) return false;
    $lines = file($outcomesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return false;
    $found = false;
    $updatedLines = [];
    $updatedRec = null;
    foreach ($lines as $line) {
        $rec = json_decode(trim($line), true);
        if (is_array($rec) && ((string) ($rec['thread_id'] ?? '')) === $threadId) {
            $rec['outcome'] = $newOutcome;
            $rec['human_confirmed'] = true;
            $rec['classified_at'] = date('c');
            $updatedRec = $rec;
            $found = true;
        }
        $updatedLines[] = json_encode($rec ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if ($found) {
        @file_put_contents($outcomesFile, implode("\n", $updatedLines) . "\n", LOCK_EX);
        // Also update client profile
        try {
            require_once WASAPBOT_ROOT . '/src/Services/ClientProfileService.php';
            $profileSvc = new \WasapBot\Services\ClientProfileService($config);
            $profileSvc->updateProfile(
                (string) ($updatedRec['phone'] ?? ''),
                $newOutcome,
                (array) ($updatedRec['tags'] ?? []),
                (string) ($updatedRec['selected_girl'] ?? '')
            );
        } catch (\Throwable $e) {}
    }
    return $found;
}

// ─── Data for the view ───
$memoryLines  = getMemoryDisplayLines($memory);
$memoryGroups = getMemoryGroups($memoryLines);
$routingLines = config_val_array('routing.lines');
$botStats     = getBotStats($config);
$leadsDisplay = getLeadsForDisplay($config);
$learningStats = getLearningStats($config);

// ─── Log file: read last 300 lines ───
$logLines = [];
$logFilePath = resolveConfigPath('files.bot_log', 'data/bot.log');
if (file_exists($logFilePath) && is_readable($logFilePath)) {
    $fp = @fopen($logFilePath, 'rb');
    if ($fp !== false) {
        $allLogLines = [];
        while (($line = fgets($fp)) !== false) {
            $allLogLines[] = rtrim($line);
        }
        fclose($fp);
        $logLines = array_slice($allLogLines, -300);
    }
}

// ─── Now render ───
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>wasapBot — Admin Panel</title>
<link rel="stylesheet" href="assets/style.css?v=20260603_2">
</head>
<body>

<div class="header">
    <div>
        <h1>wasapBot — Panel de Administración</h1>
        <span class="subtitle">PHP <?php echo h(PHP_VERSION); ?> &middot; <?php echo h(date('Y-m-d H:i:s')); ?>
        <?php if ($adminUsername !== ''): ?>
        &middot; 👤 <?php echo h($adminUsername); ?>
        <?php endif; ?>
        </span>
    </div>
    <div style="display:flex;gap:8px">
        <?php if ($adminUsername !== ''): ?>
        <a href="logout" class="btn btn-sm" style="background:var(--input-bg);color:var(--text-muted);text-decoration:none;display:inline-flex;align-items:center;font-size:.8rem">Cerrar sesión</a>
        <?php endif; ?>
        <form method="post" action="<?php echo h($baseUrl); ?>?action=toggle_bot" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
            <input type="hidden" name="active_tab" class="js-active-tab-input" value="tab-status">
            <button type="submit" class="btn <?php echo $botMode === 'start' ? 'btn-danger' : 'btn-success'; ?> btn-lg">
                <?php echo $botMode === 'start' ? 'APAGAR BOT' : 'ENCENDER BOT'; ?>
            </button>
        </form>
    </div>
</div>

<?php echo $notification; ?>

<!-- Tab Navigation -->
<div class="tab-nav" id="tabNav">
    <button type="button" class="active" data-tab="tab-status">Estado</button>
    <button type="button" data-tab="tab-descripcion">📖 Descripción</button>
    <button type="button" data-tab="tab-prompt">System Prompt</button>
    <button type="button" data-tab="tab-leads">Leads</button>
    <button type="button" data-tab="tab-waha">WAHA</button>
    <button type="button" data-tab="tab-ia">🤖 IA</button>
    <button type="button" data-tab="tab-routing">Routing</button>
    <button type="button" data-tab="tab-delays">Human Delays</button>
    <button type="button" data-tab="tab-variants">Variantes</button>
    <button type="button" data-tab="tab-followup">Cron Follow-up</button>
    <button type="button" data-tab="tab-reminder">Cron Reminder</button>
    <button type="button" data-tab="tab-urls">URLs</button>
    <button type="button" data-tab="tab-memory">Memoria</button>
    <button type="button" data-tab="tab-logs">Logs</button>
    <button type="button" data-tab="tab-learning">🧠 Aprendizaje</button>
    <?php if ($isAdmin): ?>
    <button type="button" data-tab="tab-users" style="color:var(--accent)">👥 Usuarios</button>
    <?php endif; ?>
</div>

<!-- ── Main config form ── -->
<!-- Form separado para toggle bot (no puede anidarse dentro del main form) -->
<form id="form-toggle-bot-status" method="post" action="<?php echo h($baseUrl); ?>?action=toggle_bot" style="display:none">
    <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
    <input type="hidden" name="active_tab" class="js-active-tab-input" value="tab-status">
</form>

<form method="post" action="<?php echo h($baseUrl); ?>?action=save_config" class="main-form">
<input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
<input type="hidden" name="active_tab" class="js-active-tab-input" value="tab-status">

<!-- ===== TAB 1: Estado del Bot ===== -->
<div class="tab-content active" id="tab-status">
    <div class="card">
        <h2>Estado del Bot</h2>
        <div class="bot-status">
            <span class="bot-indicator <?php echo $botStatusClass; ?>"></span>
            <span class="bot-status-text"><?php echo h($botStatusLabel); ?></span>
        </div>
        <p style="margin-top:12px;color:var(--text-muted);font-size:.85rem">
            Archivo de control: <code style="background:var(--input-bg);padding:2px 6px;border-radius:3px"><?php echo h($modeFilePath); ?></code>
        </p>
        <div style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap">
            <button type="submit" form="form-toggle-bot-status"
                class="btn btn-lg <?php echo $botMode === 'start' ? 'btn-danger' : 'btn-success'; ?>">
                <?php echo $botMode === 'start' ? 'DETENER Bot' : 'ARRANCAR Bot'; ?>
            </button>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="card">
        <h2>Estadísticas</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-top:4px">

            <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:18px 20px;text-align:center">
                <div style="font-size:2rem;font-weight:800;color:var(--info)"><?php echo $botStats['conversations_total']; ?></div>
                <div style="font-size:.82rem;color:var(--text-muted);margin-top:4px">Conversaciones totales</div>
            </div>

            <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:18px 20px;text-align:center">
                <div style="font-size:2rem;font-weight:800;color:var(--accent)"><?php echo $botStats['conversations_today']; ?></div>
                <div style="font-size:.82rem;color:var(--text-muted);margin-top:4px">Conversaciones hoy</div>
            </div>

            <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:18px 20px;text-align:center">
                <div style="font-size:2rem;font-weight:800;color:var(--ok)"><?php echo $botStats['leads_total']; ?></div>
                <div style="font-size:.82rem;color:var(--text-muted);margin-top:4px">Leads totales</div>
            </div>

            <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:18px 20px;text-align:center">
                <div style="font-size:2rem;font-weight:800;color:var(--money)"><?php echo $botStats['leads_today']; ?></div>
                <div style="font-size:.82rem;color:var(--text-muted);margin-top:4px">Leads hoy</div>
            </div>

        </div>
        <p style="margin-top:12px;color:var(--text-muted);font-size:.78rem">
            * Una "conversación" = un hilo (thread_id) único en memoria de sesión. Un "lead" = visita/cita detectada por el bot.
        </p>
    </div>
</div>

<!-- ===== TAB: Descripción del Bot ===== -->
<div class="tab-content" id="tab-descripcion">

    <div class="card">
        <h2>📡 URL del Webhook (configurar en WAHA)</h2>
        <?php
        $proto  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'TU_SERVIDOR');
        // Path hasta el panel → webhook está en la misma carpeta public/
        $panelPath = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $webhookPath = rtrim(dirname($panelPath), '/') . '/webhook.php';
        $webhookUrl  = $proto . '://' . $host . strtok($webhookPath, '?');
        ?>
        <p style="color:var(--text-muted);font-size:.88rem;margin-bottom:12px">
            En el panel de WAHA, configura el webhook de cada sesión apuntando a esta URL:
        </p>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <code id="webhookUrlCode" style="background:var(--input-bg);border:1px solid var(--border);padding:10px 14px;border-radius:var(--radius-sm);font-size:.95rem;color:var(--accent-light);flex:1;word-break:break-all"><?php echo h($webhookUrl); ?></code>
            <button type="button" class="btn btn-primary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('webhookUrlCode').textContent).then(()=>{this.textContent='✓ Copiado';setTimeout(()=>{this.textContent='Copiar'},2000)})">Copiar</button>
        </div>
        <p style="color:var(--text-muted);font-size:.78rem;margin-top:8px">
            Si usas el front-controller: <code style="font-size:.78rem"><?php echo h(rtrim(dirname($webhookPath), '/') . '/index.php/webhook'); ?></code>
        </p>
        <?php if (!empty((string) $config->get('waha.webhook_secret', ''))): ?>
        <p style="color:var(--text-muted);font-size:.82rem;margin-top:6px">
            🔐 Webhook Secret configurado — WAHA debe enviar la cabecera <code>X-WAHA-Signature</code> o <code>X-Webhook-Secret</code>.
        </p>
        <?php else: ?>
        <p style="color:var(--warn);font-size:.82rem;margin-top:6px">
            ⚠️ No hay Webhook Secret configurado. Cualquiera puede enviar eventos al webhook. Configúralo en el tab <strong>WAHA</strong>.
        </p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>🤖 Funcionamiento Interno — Fase a Fase</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:16px">
            Cuando WAHA recibe un mensaje de WhatsApp, hace un POST al webhook. El bot procesa cada mensaje a través de un pipeline de fases:
        </p>

        <div style="display:flex;flex-direction:column;gap:10px">

            <?php
            $fases = [
                ['🛡️', 'Gate 1 — BotModeGate', 'Comprueba si el bot está encendido (ON/OFF). Si está en modo <em>stop</em>, el mensaje se ignora sin procesar nada. Puedes controlarlo con el botón de arriba.'],
                ['🗺️', 'Gate 2 — RoutingGate', 'Extrae los últimos 9 dígitos del número receptor y los busca en la tabla de Routing. Si la línea no existe o está desactivada, el mensaje se descarta. También comprueba la blacklist de remitentes. Si la línea existe, determina qué instancia de WAHA usar (IP + puerto).'],
                ['🔁', 'Gate 3 — DedupGate', 'Evita procesar el mismo mensaje dos veces. Usa el <em>messageId</em> de WAHA como clave. Si ya se procesó (fichero de lock presente), se descarta silenciosamente. Los locks expiran en 5 minutos.'],
                ['🌊', 'Gate 4 — Coalescer (burst grouping)', 'Agrupa ráfagas de mensajes del mismo usuario. Si el usuario envía varios mensajes seguidos en menos de 12 segundos, el bot espera 4 segundos y los combina en uno solo antes de responder. Así evita responder 5 veces a "hola / qué / precios / fotos / cuánto cuesta".'],
                ['📝', 'Gate 5 — MessageExtractor', 'Extrae el texto real del mensaje de todos los posibles campos del payload de WAHA (texto, botón, reacción, caption…). Detecta si es audio (<code>[AUDIO]</code>), imagen sin texto (<code>[SIN_TEXTO]</code>), o texto normal.'],
                ['🚫', 'Gate 6 — Blacklist externa', 'Consulta una API externa de blacklist para verificar que el remitente no está bloqueado globalmente. Si está en la blacklist, se descarta.'],
                ['🧠', 'Processor 1 — ContextAssembler', 'Recopila todo el contexto necesario para la IA: historial de conversación, tema del mensaje (precios / ubicación / servicios / pago / cita), si el usuario ya llegó a una tarifa, si la conversación está muerta, si pide el catálogo completo…'],
                ['⚡', 'Atajo de audio', 'Si el mensaje es un audio, el bot responde directamente con una variante aleatoria de respuesta de audio (configurable en Variantes) y salta todas las fases de IA. Esto es más rápido y natural.'],
                ['🎭', 'Processor 2 — Tone Classifier (OpenAI gpt-4o-mini)', 'Analiza el tono del mensaje entrante: sentimiento (positivo/neutro/negativo), registro (coloquial/formal) y urgencia (alta/normal/baja). Llama a un modelo ligero para minimizar costes.'],
                ['✍️', 'Processor 3 — ToneBuilder', 'Construye las directivas de tono para el system prompt: qué registro usar, si ya saludó (no repetir saludo), si hay urgencia, si mostrar catálogo, si la conversación está muerta (silencio total)…'],
                ['💬', 'Processor 4 — OpenAI Chat (gpt-5.1)', 'Llama al modelo principal con el system prompt completo + historial + directivas de tono. La respuesta es siempre un JSON con: texto visible, si se detectó un lead, confianza, ETA en minutos, chica seleccionada, modo de la hablante, etc.'],
                ['🔍', 'Processor 5 — ResponseNormalizer', 'Parsea el JSON devuelto por OpenAI y extrae todos los campos estructurados para su uso en las siguientes fases.'],
                ['📸', 'Processor 6 — CatalogFormatter', 'Si el mensaje menciona chicas o fotos, añade al texto respuesta las fotos del catálogo activo (obtenidas de la URL de girls.json). Lógica de selección: (1) Si hay chica seleccionada y pide fotos → todas las fotos de esa chica. (2) Si hay chica seleccionada y pregunta por amigas/más chicas → 1 foto aleatoria de cada chica activa. (3) Sin chica seleccionada y pide fotos → 1 foto aleatoria de cada chica activa. (4) wants_more_girls explícito → igual que caso 3.'],
                ['🔄', 'Processor 7 — DedupeReply', 'Compara la respuesta generada con los últimos 5 mensajes del bot. Si es casi idéntica, la reformula añadiendo un prefijo/sufijo de variación para que no parezca un bucle.'],
                ['🖼️', 'Processor 8 — ImageSplitter', 'Si la respuesta contiene URLs de imágenes junto con texto, las divide en mensajes separados (uno por imagen) para enviarlas como mensajes individuales en WhatsApp.'],
                ['📤', 'Envío humanizado (WAHA)', 'Envía cada mensaje simulando comportamiento humano: 1) marca como leído, 2) espera un tiempo proporcional a la longitud del mensaje entrante, 3) muestra "escribiendo…", 4) espera el tiempo de escritura simulado, 5) envía el texto, 6) para "escribiendo…". Si hay varios mensajes (fotos), espera ~15 segundos entre ellos.'],
                ['💾', 'Memoria de sesión', 'Guarda el mensaje del usuario y la respuesta del bot en session_memory.ndjson, asociados al thread_id y teléfono. Esto permite al bot recordar el hilo de conversación en los siguientes mensajes.'],
                ['🎯', 'Side Effects — Lead + Telegram + Auto-Off + Reminder', 'Si se detectó un lead (usuario con intención de visita): envía alerta por Telegram, lo registra en leads.ndjson y, si está configurado, apaga el bot automáticamente. Si el usuario dio un ETA ("llego en 30 min"), programa un recordatorio para enviárselo cuando ese tiempo expire.'],
            ];
            foreach ($fases as $i => [$icon, $titulo, $desc]):
            ?>
            <details style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:0;overflow:hidden">
                <summary style="padding:12px 16px;cursor:pointer;list-style:none;display:flex;align-items:center;gap:10px;font-weight:600;font-size:.9rem;user-select:none">
                    <span style="font-size:1.1rem"><?php echo $icon; ?></span>
                    <span style="color:var(--text-muted);font-size:.78rem;min-width:24px"><?php echo ($i + 1); ?></span>
                    <span><?php echo h($titulo); ?></span>
                    <span style="margin-left:auto;color:var(--text-muted);font-size:.75rem">▼</span>
                </summary>
                <div style="padding:10px 16px 14px;border-top:1px solid var(--border);color:var(--text-muted);font-size:.85rem;line-height:1.6">
                    <?php echo $desc; ?>
                </div>
            </details>
            <?php endforeach; ?>

        </div>

        <div style="margin-top:16px;padding:12px 16px;background:var(--info-bg);border:1px solid var(--info);border-radius:var(--radius-sm);font-size:.82rem;color:var(--info)">
            <strong>💡 Cron jobs:</strong> Hay dos procesos que corren en segundo plano. El <strong>Cron Follow-up</strong> (cada 6h) re-contacta leads con fotos de chicas disponibles. El <strong>Cron Reminder</strong> (cada minuto) envía recordatorios a usuarios que dieron un ETA. Ambos se configuran en sus respectivos tabs.
        </div>
    </div>

</div>

<!-- ===== TAB 2: System Prompt (Parametrizado) ===== -->
<div class="tab-content" id="tab-prompt">

<div class="prompt-layout">
    <!-- LEFT COLUMN: edit form (60%) -->
    <div class="prompt-edit-col">

        <!-- Template -->
        <div class="card">
            <h2>Estructura del prompt</h2>
            <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:8px">
                Define el orden y estructura con etiquetas <code style="background:var(--input-bg);padding:1px 6px;border-radius:3px">[seccion]</code> como marcadores de posición.
                Click en una etiqueta para insertarla.
            </p>
            <div style="margin-bottom:10px;display:flex;flex-wrap:wrap;gap:5px">
                <?php
                $sectionKeys = ['rol', 'estilo', 'tarifas', 'servicios', 'ubicacion', 'instrucciones_fotos', 'identidad_chicas', 'seguridad', 'ejemplos', 'formato_respuesta'];
                foreach ($sectionKeys as $sk) {
                    $hasContent = strlen((string) ($config->get("prompt.sections.$sk", ''))) > 0;
                    $badge = $hasContent ? '' : ' prompt-chip-empty';
                    echo '<span class="prompt-chip' . $badge . '" onclick="insertTag(\'' . $sk . '\')" title="Insertar [' . $sk . ']">[' . $sk . ']</span>';
                }
                ?>
            </div>
            <textarea name="prompt[template]" id="prompt-template" class="code-area"
                      style="width:100%;min-height:160px"
                      spellcheck="false"
                      oninput="rebuildPreview()"><?php
                echo h((string) $config->get('prompt.template', ''));
            ?></textarea>
        </div>

        <!-- Sections (accordion) -->
        <div class="card" style="margin-top:12px">
            <h2>Secciones editables</h2>
            <p style="color:var(--text-muted);font-size:.8rem;margin-bottom:10px">
                Cada sección corresponde a un bloque del prompt. Ordenadas por frecuencia de edición.
            </p>
            <?php
            $sectionLabels = [
                'tarifas'              => '💰 TARIFAS',
                'ubicacion'            => '📍 UBICACIÓN',
                'servicios'            => '🛏️ SERVICIOS',
                'estilo'               => '✍️ ESTILO',
                'rol'                  => '🎭 ROL',
                'instrucciones_fotos'  => '📸 FOTOS',
                'identidad_chicas'     => '👩 IDENTIDAD Y CHICAS',
                'seguridad'            => '🛡️ SEGURIDAD',
                'ejemplos'             => '💬 EJEMPLOS',
                'formato_respuesta'    => '📋 FORMATO RESPUESTA',
            ];
            foreach ($sectionKeys as $sk) {
                $val = (string) $config->get("prompt.sections.$sk", '');
                $len = strlen($val);
                $label = $sectionLabels[$sk] ?? $sk;
                $openAttr = ($sk === 'tarifas' || $sk === 'ubicacion') ? ' open' : '';
                echo '<details class="prompt-details"' . $openAttr . '>';
                echo '<summary class="prompt-summary">';
                echo '<span>' . h($label) . '</span>';
                echo '<span class="prompt-summary-badge">' . $len . ' chars</span>';
                echo '</summary>';
                echo '<textarea name="prompt[sections][' . $sk . ']" class="code-area prompt-section-ta'
                     . '" style="width:100%;min-height:' . max(80, min($len, 400)) . 'px"'
                     . ' spellcheck="false" oninput="rebuildPreview()">';
                echo h($val);
                echo '</textarea>';
                echo '</details>';
            }
            ?>
        </div>

        <div style="margin-top:16px">
            <button type="submit" class="btn btn-primary btn-lg">💾 Guardar Prompt</button>
        </div>

    </div>

    <!-- RIGHT COLUMN: preview (40%) -->
    <div class="prompt-preview-col">
        <div class="card prompt-preview-card">
            <h2>Prompt ensamblado</h2>
            <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:8px">
                Vista previa de cómo queda el prompt con los valores actuales. Solo lectura.
            </p>
            <pre id="prompt-preview" class="prompt-preview-box"></pre>
            <div id="prompt-stats" class="prompt-stats"></div>
        </div>
    </div>
</div>

</div>

<!-- ===== TAB 3: Leads ===== -->
<div class="tab-content" id="tab-leads">

    <!-- Configuración Telegram -->
    <div class="card">
        <h2>Gestión de Leads — Telegram</h2>
        <div class="form-group">
            <label>Chat IDs (uno por línea)</label>
            <textarea name="telegram[chat_ids]" rows="6" class="code-area" spellcheck="false"><?php
                echo h(implode("\n", config_val_array('telegram.chat_ids')));
            ?></textarea>
        </div>
        <div class="form-group">
            <label class="checkbox-label">
                <input type="hidden" name="telegram[alert_enabled]" value="0">
                <input type="checkbox" name="telegram[alert_enabled]" value="1" <?php echo checked((bool) $config->get('telegram.alert_enabled', false)); ?>>
                Alertas de leads activadas
            </label>
        </div>
        <div style="margin-top:10px">
            <button type="submit" class="btn btn-primary btn-lg">Guardar Leads</button>
        </div>
    </div>

    <!-- Tabla de leads detectados -->
    <div class="card" style="margin-top:0">
        <h2>Leads detectados <span style="font-weight:400;font-size:.85rem;color:var(--text-muted)">(<?php echo count($leadsDisplay); ?> total)</span></h2>

        <?php if (empty($leadsDisplay)): ?>
        <p style="color:var(--text-muted);font-size:.9rem;padding:20px 0;text-align:center">No hay leads registrados todavía.</p>
        <?php else: ?>
        <div style="overflow-x:auto">
            <table class="memory-table" style="font-size:.83rem">
                <thead>
                    <tr>
                        <th>Fecha y hora</th>
                        <th>Teléfono</th>
                        <th>Línea</th>
                        <th>ETA (min)</th>
                        <th>Confianza</th>
                        <th>Thread ID</th>
                        <th>Último followup</th>
                        <th>Conversación</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($leadsDisplay as $lead):
                    $tsRaw  = (string) ($lead['ts'] ?? '');
                    $tsDisp = '';
                    if ($tsRaw !== '') {
                        try {
                            $dt = new \DateTimeImmutable($tsRaw, new \DateTimeZone('UTC'));
                            $dtLocal = $dt->setTimezone(new \DateTimeZone('Europe/Madrid'));
                            $tsDisp  = $dtLocal->format('d/m/Y H:i');
                        } catch (\Exception) {
                            $tsDisp = $tsRaw;
                        }
                    }
                    $phone     = (string) ($lead['phone'] ?? '');
                    $phoneDisp = strlen($phone) > 6 ? '...' . substr($phone, -6) : $phone;
                    $label     = (string) ($lead['line_label'] ?? '');
                    $eta       = (int) ($lead['eta_minutes'] ?? 0);
                    $conf      = isset($lead['lead_confidence']) ? round((float) $lead['lead_confidence'] * 100) . '%' : '—';
                    $threadId  = (string) ($lead['thread_id'] ?? '');
                    $threadDisp = strlen($threadId) > 12 ? substr($threadId, 0, 12) . '…' : $threadId;
                    $lastFu    = (string) ($lead['last_followup_ts'] ?? '');
                    $lastFuDisp = '';
                    if ($lastFu !== '' && $lastFu !== 'null') {
                        try {
                            $dt2 = new \DateTimeImmutable($lastFu, new \DateTimeZone('UTC'));
                            $lastFuDisp = $dt2->setTimezone(new \DateTimeZone('Europe/Madrid'))->format('d/m/Y H:i');
                        } catch (\Exception) {
                            $lastFuDisp = $lastFu;
                        }
                    }
                    $confColor = '';
                    if (isset($lead['lead_confidence'])) {
                        $c = (float) $lead['lead_confidence'];
                        $confColor = $c >= 0.8 ? 'color:var(--ok)' : ($c >= 0.5 ? 'color:var(--warn)' : 'color:var(--danger)');
                    }
                ?>
                <tr>
                    <td class="mono" style="white-space:nowrap"><?php echo h($tsDisp); ?></td>
                    <td class="mono"><?php echo h($phoneDisp); ?></td>
                    <td><span style="background:var(--input-bg);padding:2px 7px;border-radius:4px;font-size:.78rem"><?php echo h($label); ?></span></td>
                    <td style="text-align:center"><?php echo $eta > 0 ? $eta . ' min' : '—'; ?></td>
                    <td style="text-align:center;<?php echo $confColor; ?>"><?php echo h($conf); ?></td>
                    <td class="mono" style="font-size:.75rem" title="<?php echo h($threadId); ?>"><?php echo h($threadDisp); ?></td>
                    <td class="mono" style="font-size:.75rem"><?php echo h($lastFuDisp ?: '—'); ?></td>
                    <td style="text-align:center">
                        <?php if ($threadId !== ''): ?>
                        <button type="button" class="btn btn-sm btn-primary" onclick="openConversationModal('<?php echo h($threadId); ?>')">Ver</button>
                        <?php else: ?>
                        <span style="color:var(--text-muted)">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- (old convModal removed — consolidated into conversationModal above) -->

<!-- ===== TAB 4: Configuración WAHA ===== -->
<div class="tab-content" id="tab-waha">
    <div class="card">
        <h2>Configuración WAHA API</h2>
        <div class="form-row">
            <div class="form-group">
                <label>API Key</label>
                <input type="password" name="waha[api_key]" value="<?php echo config_val('waha.api_key'); ?>">
            </div>
            <div class="form-group">
                <label>Base IP</label>
                <input type="text" name="waha[base_ip]" value="<?php echo config_val('waha.base_ip'); ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Default Port</label>
                <input type="number" name="waha[default_port]" value="<?php echo config_val('waha.default_port', '3000'); ?>">
            </div>
            <div class="form-group">
                <label>Session</label>
                <input type="text" name="waha[session]" value="<?php echo config_val('waha.session', 'default'); ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Webhook Secret</label>
                <input type="text" name="waha[webhook_secret]" value="<?php echo config_val('waha.webhook_secret'); ?>">
            </div>
        </div>
        <h3>Endpoints</h3>
        <div class="form-row">
            <div class="form-group">
                <label>sendText</label>
                <input type="text" name="waha[endpoints][sendText]" value="<?php echo config_val('waha.endpoints.sendText', '/api/sendText'); ?>">
            </div>
            <div class="form-group">
                <label>startTyping</label>
                <input type="text" name="waha[endpoints][startTyping]" value="<?php echo config_val('waha.endpoints.startTyping', '/api/startTyping'); ?>">
            </div>
            <div class="form-group">
                <label>stopTyping</label>
                <input type="text" name="waha[endpoints][stopTyping]" value="<?php echo config_val('waha.endpoints.stopTyping', '/api/stopTyping'); ?>">
            </div>
            <div class="form-group">
                <label>sendSeen</label>
                <input type="text" name="waha[endpoints][sendSeen]" value="<?php echo config_val('waha.endpoints.sendSeen', '/api/sendSeen'); ?>">
            </div>
        </div>
        <div style="margin-top:10px">
            <button type="submit" class="btn btn-primary btn-lg">Guardar WAHA</button>
        </div>
    </div>
</div>

<!-- ===== TAB 5: ConfiguraciÃ³n IA ===== -->
<div class="tab-content" id="tab-ia">
    <div class="card">
        <h2>Configuración IA</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:18px">
            Configura los proveedores de inteligencia artificial que usarÃ¡ el bot.
            Cada lÃ­nea de WhatsApp (en la pestaÃ±a Routing) puede elegir quÃ© proveedor usar.
        </p>

        <!-- ── SECCIÃN OPENAI ── -->
        <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:20px 24px;margin-bottom:20px">
            <h3 style="margin:0 0 4px;display:flex;align-items:center;gap:8px">
                <span style="font-size:1.2rem">🧠</span> OpenAI
            </h3>
            <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:16px">
                Proveedor principal. Modelos de alta calidad con buena comprensiÃ³n del espaÃ±ol coloquial.
            </p>

            <div class="form-group">
                <label>API Key <span style="color:var(--text-muted);font-weight:400">— tu clave secreta de OpenAI</span></label>
                <input type="password" name="openai[api_key]" value="<?php echo config_val('openai.api_key'); ?>" placeholder="sk-proj-...">
            </div>

            <div class="form-row" style="align-items:flex-start">
                <div class="form-group" style="flex:2">
                    <label>Modelo de Chat <span style="color:var(--text-muted);font-weight:400">— el que genera las respuestas del bot</span></label>
                    <select name="openai[chat_model]" id="openaiModelSelect" class="ai-model-select" onchange="showModelInfo('openai')">
                        <?php
                        $openaiModels = [
                            'gpt-5.1'       => 'GPT-5.1 — Flagship. Excelente con prompts largos. ~$1.25/$10 por MTok.',
                            'gpt-4o'        => 'GPT-4o — ClÃ¡sico y estable. Comportamiento 100% predecible. ~$2.50/$10.',
                            'gpt-4o-mini'   => 'GPT-4o-mini — El mÃ¡s barato de OpenAI. Muy rÃ¡pido. ~$0.15/$0.60.',
                            'gpt-5.4'       => 'GPT-5.4 — Nueva generaciÃ³n. Mejor razonamiento que 5.1. ~$2.50/$15.',
                            'gpt-5.4-mini'  => 'GPT-5.4-mini — Nueva gen. econÃ³mica. ~$0.75/$4.50.',
                            'gpt-5.5'       => 'GPT-5.5 — El mÃ¡s potente. Para lÃ­neas premium. ~$5/$30.',
                        ];
                        $currentOpenaiModel = config_val('openai.chat_model', 'gpt-5.1');
                        foreach ($openaiModels as $id => $desc) {
                            $sel = ($id === $currentOpenaiModel) ? 'selected' : '';
                            echo "<option value=\"{$id}\" {$sel}>{$desc}</option>";
                        }
                        ?>
                    </select>
                    <div id="openaiModelInfo" class="model-info-box">
                        <?php echo h($openaiModels[$currentOpenaiModel] ?? ''); ?>
                    </div>
                </div>
            </div>

            <div class="form-row" style="align-items:flex-start">
                <div class="form-group" style="flex:1">
                    <label>Temperature <span style="color:var(--text-muted);font-weight:400">— creatividad del modelo (0-2)</span></label>
                    <div style="display:flex;align-items:center;gap:10px">
                        <input type="range" name="openai[temperature]" id="openaiTemperature" min="0" max="2" step="0.1"
                               value="<?php echo config_val('openai.temperature', '0'); ?>"
                               oninput="document.getElementById('openaiTempVal').textContent=this.value;showTempInfo('openai')"
                               style="flex:1">
                        <span id="openaiTempVal" style="font-weight:700;min-width:30px;text-align:right"><?php echo config_val('openai.temperature', '0'); ?></span>
                    </div>
                    <div id="openaiTempInfo" class="model-info-box" style="margin-top:6px"></div>
                </div>
            </div>

            <details style="margin-top:10px">
                <summary style="cursor:pointer;color:var(--text-muted);font-size:.82rem">▼ ConfiguraciÃ³n avanzada (Tone Classifier)</summary>
                <div class="form-row" style="margin-top:10px">
                    <div class="form-group">
                        <label>Modelo clasificador de tono</label>
                        <input type="text" name="openai[tone_classifier_model]" value="<?php echo config_val('openai.tone_classifier_model', 'gpt-4o-mini'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tone Temperature</label>
                        <input type="number" step="0.1" name="openai[tone_temperature]" value="<?php echo config_val('openai.tone_temperature', '0'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Tone Max Tokens</label>
                        <input type="number" name="openai[tone_max_tokens]" value="<?php echo config_val('openai.tone_max_tokens', '50'); ?>">
                    </div>
                </div>
            </details>
        </div>

        <!-- ── SECCIÃN DEEPSEEK ── -->
        <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:20px 24px;margin-bottom:20px">
            <h3 style="margin:0 0 4px;display:flex;align-items:center;gap:8px">
                <span style="font-size:1.2rem">🐋</span> DeepSeek
            </h3>
            <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:16px">
                Alternativa ultra-econÃ³mica. API compatible con OpenAI. Ideal para lÃ­neas de alto volumen.
                La API key se precarga desde la configuraciÃ³n existente de Publicista si estÃ¡ disponible.
            </p>

            <div class="form-group">
                <label>API Key <span style="color:var(--text-muted);font-weight:400">— tu clave de DeepSeek</span></label>
                <?php
                // Pre-fill DeepSeek API key from CRM publicista settings if available
                $deepseekDefaultKey = '';
                $crmSettingsPath = dirname(__DIR__, 2) . '/data/settings.json';
                if (file_exists($crmSettingsPath) && is_readable($crmSettingsPath)) {
                    $crmSettings = @json_decode((string) @file_get_contents($crmSettingsPath), true);
                    if (is_array($crmSettings) && !empty($crmSettings['publicista_copy_api_key'])) {
                        $deepseekDefaultKey = trim((string) $crmSettings['publicista_copy_api_key']);
                    }
                }
                $currentDsKey = config_val('deepseek.api_key');
                // If no key set yet and we have a default from CRM, use it
                $displayKey = ($currentDsKey === '' || $currentDsKey === 'CHANGEME_DEEPSEEK_API_KEY') ? $deepseekDefaultKey : $currentDsKey;
                ?>
                <input type="password" name="deepseek[api_key]" value="<?php echo h($displayKey); ?>" placeholder="sk-...">
                <?php if ($deepseekDefaultKey !== '' && ($currentDsKey === '' || $currentDsKey === 'CHANGEME_DEEPSEEK_API_KEY')): ?>
                <small style="color:var(--ok)">✓ Precargada desde Publicista</small>
                <?php endif; ?>
            </div>

            <div class="form-row" style="align-items:flex-start">
                <div class="form-group" style="flex:2">
                    <label>Modelo de Chat <span style="color:var(--text-muted);font-weight:400">— el que genera las respuestas</span></label>
                    <select name="deepseek[chat_model]" id="deepseekModelSelect" class="ai-model-select" onchange="showModelInfo('deepseek')">
                        <?php
                        $deepseekModels = [
                            'deepseek-v4-pro'  => 'DeepSeek V4 Pro — El mÃ¡s potente. Calidad cercana a GPT-5.1. ~$0.44/$0.87 por MTok.',
                            'deepseek-v4-flash'=> 'DeepSeek V4 Flash — Ultra-econÃ³mico y rÃ¡pido. ~$0.14/$0.28. Ideal para alto volumen.',
                            'deepseek-chat'    => 'DeepSeek V3 (legacy) — Obsoleto en julio 2026. Solo compatibilidad.',
                        ];
                        $currentDsModel = config_val('deepseek.chat_model', 'deepseek-v4-flash');
                        foreach ($deepseekModels as $id => $desc) {
                            $sel = ($id === $currentDsModel) ? 'selected' : '';
                            echo "<option value=\"{$id}\" {$sel}>{$desc}</option>";
                        }
                        ?>
                    </select>
                    <div id="deepseekModelInfo" class="model-info-box">
                        <?php echo h($deepseekModels[$currentDsModel] ?? ''); ?>
                    </div>
                </div>
            </div>

            <div class="form-row" style="align-items:flex-start">
                <div class="form-group" style="flex:1">
                    <label>Temperature <span style="color:var(--text-muted);font-weight:400">— creatividad (0-2). Default DeepSeek: 0.7</span></label>
                    <div style="display:flex;align-items:center;gap:10px">
                        <input type="range" name="deepseek[temperature]" id="deepseekTemperature" min="0" max="2" step="0.1"
                               value="<?php echo config_val('deepseek.temperature', '0.7'); ?>"
                               oninput="document.getElementById('deepseekTempVal').textContent=this.value;showTempInfo('deepseek')"
                               style="flex:1">
                        <span id="deepseekTempVal" style="font-weight:700;min-width:30px;text-align:right"><?php echo config_val('deepseek.temperature', '0.7'); ?></span>
                    </div>
                    <div id="deepseekTempInfo" class="model-info-box" style="margin-top:6px"></div>
                </div>
            </div>

            <details style="margin-top:10px">
                <summary style="cursor:pointer;color:var(--text-muted);font-size:.82rem">▼ Configuración avanzada (Tone Classifier)</summary>
                <div class="form-row" style="margin-top:10px">
                    <div class="form-group">
                        <label>Modelo clasificador de tono</label>
                        <input type="text" name="deepseek[tone_classifier_model]" value="<?php echo config_val('deepseek.tone_classifier_model', 'deepseek-v4-pro'); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tone Temperature</label>
                        <input type="number" step="0.1" name="deepseek[tone_temperature]" value="<?php echo config_val('deepseek.tone_temperature', '0'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Tone Max Tokens</label>
                        <input type="number" name="deepseek[tone_max_tokens]" value="<?php echo config_val('deepseek.tone_max_tokens', '50'); ?>">
                    </div>
                </div>
            </details>
        </div>

        <!-- ── SELECTOR GLOBAL DE PROVEEDORES ── -->
        <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:20px 24px;margin-bottom:20px">
            <h3 style="margin:0 0 4px;display:flex;align-items:center;gap:8px">
                <span style="font-size:1.2rem">⚙️</span> Proveedores globales
            </h3>
            <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:16px">
                Selecciona qué proveedor usar para cada funcionalidad. DeepSeek es el proveedor por defecto recomendado.
            </p>
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label>Tone Classifier (clasificación de tono)</label>
                    <select name="global_providers[tone_classifier]">
                        <option value="deepseek" <?php echo config_val('global_providers.tone_classifier', 'deepseek') === 'deepseek' ? 'selected' : ''; ?>>DeepSeek</option>
                        <option value="openai" <?php echo config_val('global_providers.tone_classifier', 'deepseek') === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1">
                    <label>Voice AI (órdenes por voz)</label>
                    <select name="global_providers[voice_ai]">
                        <option value="deepseek" <?php echo config_val('global_providers.voice_ai', 'deepseek') === 'deepseek' ? 'selected' : ''; ?>>DeepSeek</option>
                        <option value="openai" <?php echo config_val('global_providers.voice_ai', 'deepseek') === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group" style="flex:1">
                    <label>Publicista — Copy / Descriptor</label>
                    <select name="global_providers[publicista_copy]">
                        <option value="deepseek" <?php echo config_val('global_providers.publicista_copy', 'deepseek') === 'deepseek' ? 'selected' : ''; ?>>DeepSeek</option>
                        <option value="openai" <?php echo config_val('global_providers.publicista_copy', 'deepseek') === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                    </select>
                </div>
                <div class="form-group" style="flex:1">
                    <label>Publicista — Imagen</label>
                    <select name="global_providers[publicista_image]">
                        <option value="pollo" <?php echo config_val('global_providers.publicista_image', 'pollo') === 'pollo' ? 'selected' : ''; ?>>Pollo.ai</option>
                    </select>
                    <small style="color:var(--text-muted)">Solo Pollo.ai disponible para generación de imágenes</small>
                </div>
            </div>
        </div>

        <div style="margin-top:10px">
            <button type="submit" class="btn btn-primary btn-lg">Guardar IA</button>
        </div>
    </div>
</div>

<script>
// ── Model descriptions (static data for JS) ──
const modelDescriptions = {
    openai: {
        'gpt-5.1':       { desc: 'Modelo mÃ¡s equilibrado de OpenAI. Excelente para prompts largos y complejos como el System Prompt del bot.', pros: '✔ Mejor seguimiento de instrucciones largas\n✔ Buen espaÃ±ol coloquial\n✔ Razonamiento configurable', cons: '✘ Latencia media (~2-4s)\n✘ MÃ¡s caro que alternativas ($10/MTok output)', temp: true },
        'gpt-4o':        { desc: 'Modelo clÃ¡sico, muy estable y predecible. Comportamiento consistente en todas las conversaciones.', pros: '✔ 100% predecible\n✔ Bien probado en producciÃ³n\n✔ Respuestas rÃ¡pidas', cons: '✘ TecnologÃ­a anterior\n✘ MÃ¡s caro que 5.1 ($10/MTok)\n✘ Peor con prompts muy largos', temp: true },
        'gpt-4o-mini':   { desc: 'El mÃ¡s barato de OpenAI. Muy rÃ¡pido pero menos sofisticado. Ideal si el presupuesto es la prioridad.', pros: '✔ Coste mÃ­nimo ($0.60/MTok)\n✔ Muy rÃ¡pido (< 1s)\n✔ Bueno para respuestas simples', cons: '✘ Puede sonar robÃ³tico\n✘ Menos creativo\n✘ No ideal para prompts complejos', temp: true },
        'gpt-5.4':       { desc: 'Nueva generaciÃ³n. Mejor razonamiento que 5.1, manteniendo buena relaciÃ³n calidad/precio.', pros: '✔ MÃ¡s inteligente que 5.1\n✔ Mejor con contexto largo\n✔ Buenos matices conversacionales', cons: '✘ MÃ¡s caro en output ($15/MTok)\n✘ Puede ser "demasiado listo" para respuestas simples', temp: true },
        'gpt-5.4-mini':  { desc: 'Nueva generaciÃ³n econÃ³mica. Muy buena relaciÃ³n calidad/precio para uso general.', pros: '✔ Excelente calidad/precio\n✔ Moderno y rÃ¡pido\n✔ Buen espaÃ±ol', cons: '✘ Contexto mÃ¡ximo 400K (vs 1M)\n✘ Menos potente que los modelos grandes', temp: true },
        'gpt-5.5':       { desc: 'El mÃ¡s potente de OpenAI. Para lÃ­neas premium donde la calidad es crÃ­tica.', pros: '✔ MÃ¡xima calidad de respuesta\n✔ El mejor espaÃ±ol\n✔ Contexto de 1M tokens', cons: '✘ Muy caro ($30/MTok output)\n✘ Overkill para respuestas de 1-2 frases\n✘ Mayor latencia', temp: true }
    },
    deepseek: {
        'deepseek-v4-pro':  { desc: 'El mÃ¡s potente de DeepSeek. Calidad comparable a GPT-5.1 por ~1/10 del precio.', pros: '✔ Excelente relaciÃ³n calidad/precio\n✔ API OpenAI-compatible\n✔ Buen espaÃ±ol\n✔ Thinking mode disponible', cons: '✘ Latencia mayor desde Europa (~3-6s)\n✘ Menos conocido/probado que OpenAI', temp: true },
        'deepseek-v4-flash':{ desc: 'Ultra-econÃ³mico y rÃ¡pido. Perfecto para lÃ­neas de alto volumen donde el coste importa.', pros: '✔ Precio imbatible ($0.28/MTok)\n✔ Muy rÃ¡pido\n✔ Ideal para alto volumen', cons: '✘ Menos sofisticado en conversaciones complejas\n✘ Puede fallar con prompts muy largos', temp: true },
        'deepseek-chat':    { desc: 'Modelo legacy (DeepSeek V3). SerÃ¡ deprecado en julio 2026. Solo para compatibilidad.', pros: '✔ Compatible con cÃ³digo existente\n✔ Barato', cons: '✘ Obsoleto pronto\n✘ Migrar a v4-flash recomendado', temp: true }
    }
};

// ── Temperature descriptions ──
function getTempDescription(val) {
    val = parseFloat(val);
    if (val === 0) return '🧊 <strong>0 — Determinista.</strong> El bot responderÃ¡ siempre igual ante la misma pregunta. Predecible pero puede sonar repetitivo entre conversaciones distintas.';
    if (val <= 0.3) return '❄️ <strong>' + val.toFixed(1) + ' — Muy enfocado.</strong> Ligera variaciÃ³n. El bot mantiene consistencia pero evita repetir frases idÃ©nticas. ⭐ <em>Recomendado para este bot.</em>';
    if (val <= 0.6) return '🌤️ <strong>' + val.toFixed(1) + ' — Equilibrado.</strong> Creatividad moderada. El bot improvisa mÃ¡s frases y varÃ­a el vocabulario. Bueno para sonar natural.';
    if (val <= 0.9) return '🔥 <strong>' + val.toFixed(1) + ' — Creativo.</strong> El bot improvisa bastante. Puede inventar frases que no estÃ¡n en el prompt. Ãtil para evitar sonar robÃ³tico pero puede desviarse del guion.';
    if (val <= 1.3) return '🌪️ <strong>' + val.toFixed(1) + ' — Muy creativo.</strong> Respuestas impredecibles. Puede salirse del System Prompt. Usar con precauciÃ³n.';
    return '🎲 <strong>' + val.toFixed(1) + ' — Impredecible.</strong> El bot serÃ¡ muy aleatorio. No recomendado para atenciÃ³n al cliente. Riesgo de respuestas incoherentes.';
}

function showModelInfo(provider) {
    var sel = document.getElementById(provider + 'ModelSelect');
    var infoDiv = document.getElementById(provider + 'ModelInfo');
    if (!sel || !infoDiv) return;
    var model = sel.value;
    var data = modelDescriptions[provider] && modelDescriptions[provider][model];
    if (data) {
        var html = '<div style="margin-top:2px"><strong>' + data.desc + '</strong></div>';
        html += '<div style="display:flex;gap:16px;margin-top:6px;flex-wrap:wrap">';
        html += '<div style="flex:1;min-width:180px"><small style="color:var(--ok)">' + data.pros.replace(/\n/g, '<br>') + '</small></div>';
        html += '<div style="flex:1;min-width:180px"><small style="color:var(--danger)">' + data.cons.replace(/\n/g, '<br>') + '</small></div>';
        html += '</div>';
        infoDiv.innerHTML = html;
    }
}

function showTempInfo(provider) {
    var slider = document.getElementById(provider + 'Temperature');
    var infoDiv = document.getElementById(provider + 'TempInfo');
    if (!slider || !infoDiv) return;
    infoDiv.innerHTML = getTempDescription(slider.value);
}

// Init on page load
document.addEventListener('DOMContentLoaded', function() {
    showModelInfo('openai');
    showModelInfo('deepseek');
    showTempInfo('openai');
    showTempInfo('deepseek');
});
</script>

<style>
.ai-model-select {
    width: 100%;
    padding: 10px 12px;
    background: var(--input-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text);
    font-size: .9rem;
    line-height: 1.5;
}
.ai-model-select option {
    padding: 8px;
    white-space: normal;
}
.model-info-box {
    margin-top: 8px;
    padding: 12px 14px;
    background: var(--bg-surface);
    border: 1px solid var(--border-soft);
    border-radius: var(--radius-sm);
    font-size: .82rem;
    line-height: 1.6;
    color: var(--text);
}
</style>

<!-- ===== TAB 6: Routing de números ===== -->
<div class="tab-content" id="tab-routing">
    <div class="card">
        <h2>Routing de Números</h2>

        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:14px">
            Selecciona qué líneas (teléfonos del sistema) deben ser atendidas por este bot.
            Las líneas disponibles se cargan desde el registro de teléfonos del CRM. Para cada línea activa,
            el bot interceptará los mensajes WhatsApp que lleguen a ese número y los procesará.
        </p>

        <!-- Selector para añadir línea desde telefonos.json -->
        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:14px;padding:12px 14px;background:var(--bg-surface);border:1px solid var(--border-soft);border-radius:var(--radius-sm)">
            <div style="flex:1;min-width:200px">
                <label style="font-size:.8rem;color:var(--text-muted);display:block;margin-bottom:4px">Añadir línea desde el registro de teléfonos</label>
                <select id="telefonoSelector" style="width:100%;padding:8px 10px;background:var(--input-bg);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-size:.9rem">
                    <option value="">— Cargando teléfonos… —</option>
                </select>
            </div>
            <button type="button" class="btn btn-primary" id="addFromSelectorBtn" onclick="addRoutingRowFromSelector()">+ Añadir línea seleccionada</button>
            <button type="button" class="btn btn-sm" onclick="addRoutingRowManual()" style="background:var(--input-bg);color:var(--text-muted);font-size:.78rem">+ Añadir manualmente</button>
        </div>

        <h3>Líneas activas en el routing</h3>
        <table class="routing-table" id="routingTable">
            <thead>
                <tr>
                    <th>Teléfono (last9)</th>
                    <th>Puerto WAHA</th>
                    <th>Etiqueta</th>
                    <th>Descripción</th>
                    <th>IA</th>
                    <th>Activa</th>
                    <th>WAHA</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php echo renderRoutingLines($routingLines); ?>
            </tbody>
        </table>

        <h3 style="margin-top:18px">Sender Blacklist</h3>
        <div class="form-group">
            <label>Números bloqueados — el bot no responderá a estos remitentes (uno por línea)</label>
            <textarea name="routing[sender_blacklist]" rows="8" class="code-area" spellcheck="false"><?php
                $blacklist = config_val_array('routing.sender_blacklist');
                echo h(implode("\n", $blacklist));
            ?></textarea>
        </div>

        <div class="form-group" style="margin-top:10px">
            <label class="checkbox-label">
                <input type="hidden" name="routing[default_enabled_if_not_found]" value="0">
                <input type="checkbox" name="routing[default_enabled_if_not_found]" value="1" <?php echo checked((bool) $config->get('routing.default_enabled_if_not_found', false)); ?>>
                Responder a todos los números aunque no estén en la lista (default_enabled_if_not_found)
            </label>
        </div>

        <div style="margin-top:10px">
            <button type="submit" class="btn btn-primary btn-lg">Guardar Routing</button>
        </div>
    </div>
</div>

<!-- ===== TAB 7: Human Delays ===== -->
<div class="tab-content" id="tab-delays">
    <div class="card">
        <h2>Human Delays — Retrasos humanos simulados</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:16px">
            Estos parámetros controlan cuánto tiempo simula el bot que está leyendo y escribiendo antes de responder,
            para que la respuesta no parezca instantánea (lo que delataría que es un bot). Ajusta con cuidado:
            valores muy bajos son poco creíbles, valores muy altos harán esperar demasiado al usuario.
        </p>

        <!-- Seen -->
        <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:12px">
            <h3 style="margin:0 0 4px">👁️ Seen — Marcar mensaje como "visto"</h3>
            <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:12px">
                Tiempo que espera el bot antes de marcar el mensaje entrante como leído (doble check azul en WhatsApp).
                Demasiado rápido resulta sospechoso. <strong>Recomendado: min=1s, max=3s.</strong>
            </p>
            <div class="form-row">
                <div class="form-group">
                    <label>Fallback (seg) <span style="color:var(--text-muted);font-weight:400">— si falla el cálculo aleatorio</span></label>
                    <input type="number" step="0.1" name="human_delays[seen][fallback_sec]" value="<?php echo config_val('human_delays.seen.fallback_sec', '1'); ?>" placeholder="1">
                </div>
                <div class="form-group">
                    <label>Mínimo aleatorio (seg) <span style="color:var(--text-muted);font-weight:400">— límite inferior</span></label>
                    <input type="number" step="0.1" name="human_delays[seen][random_min_sec]" value="<?php echo config_val('human_delays.seen.random_min_sec', '1'); ?>" placeholder="1">
                </div>
                <div class="form-group">
                    <label>Máximo aleatorio (seg) <span style="color:var(--text-muted);font-weight:400">— límite superior</span></label>
                    <input type="number" step="0.1" name="human_delays[seen][random_max_sec]" value="<?php echo config_val('human_delays.seen.random_max_sec', '3'); ?>" placeholder="3">
                </div>
            </div>
        </div>

        <!-- Read -->
        <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:12px">
            <h3 style="margin:0 0 4px">📖 Read — Tiempo de lectura del mensaje</h3>
            <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:12px">
                Tiempo que el bot "simula leer" el mensaje antes de empezar a escribir. Se calcula como:
                <code>base_aleatorio + (longitud_mensaje × per_char_ms)</code>, luego ajustado entre clamp_min y clamp_max.
                <strong>Recomendado: base 900–2200 ms, 22 ms/carácter, clamp 1200–22000 ms.</strong>
            </p>
            <div class="form-row">
                <div class="form-group">
                    <label>Base mínima (ms) <span style="color:var(--text-muted);font-weight:400">— tiempo mínimo de lectura base</span></label>
                    <input type="number" name="human_delays[read][base_min_ms]" value="<?php echo config_val('human_delays.read.base_min_ms', '900'); ?>" placeholder="900">
                </div>
                <div class="form-group">
                    <label>Base máxima (ms) <span style="color:var(--text-muted);font-weight:400">— tiempo máximo de lectura base</span></label>
                    <input type="number" name="human_delays[read][base_max_ms]" value="<?php echo config_val('human_delays.read.base_max_ms', '2200'); ?>" placeholder="2200">
                </div>
                <div class="form-group">
                    <label>Por carácter (ms) <span style="color:var(--text-muted);font-weight:400">— ms extra por cada carácter del mensaje</span></label>
                    <input type="number" name="human_delays[read][per_char_ms]" value="<?php echo config_val('human_delays.read.per_char_ms', '22'); ?>" placeholder="22">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Clamp mínimo (ms) <span style="color:var(--text-muted);font-weight:400">— nunca menos de esto</span></label>
                    <input type="number" name="human_delays[read][clamp_min_ms]" value="<?php echo config_val('human_delays.read.clamp_min_ms', '1200'); ?>" placeholder="1200">
                </div>
                <div class="form-group">
                    <label>Clamp máximo (ms) <span style="color:var(--text-muted);font-weight:400">— nunca más de esto (≈22 seg)</span></label>
                    <input type="number" name="human_delays[read][clamp_max_ms]" value="<?php echo config_val('human_delays.read.clamp_max_ms', '22000'); ?>" placeholder="22000">
                </div>
            </div>
        </div>

        <!-- Typing -->
        <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:12px">
            <h3 style="margin:0 0 4px">⌨️ Typing — Indicador "escribiendo…"</h3>
            <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:12px">
                Cuánto tiempo aparece el indicador "escribiendo…" antes de enviar la respuesta.
                Se calcula en función de la longitud de la respuesta y una velocidad de tipeo aleatoria.
                La "habituación" hace que el bot escriba más rápido en conversaciones largas (más natural).
                <strong>Recomendado: 38–85 chars/seg, chunk_size 24, start_delay 350–1200 ms.</strong>
            </p>
            <div class="form-row">
                <div class="form-group">
                    <label>Velocidad mínima (chars/seg) <span style="color:var(--text-muted);font-weight:400">— tipeo lento</span></label>
                    <input type="number" name="human_delays[typing][chars_per_sec_min]" value="<?php echo config_val('human_delays.typing.chars_per_sec_min', '38'); ?>" placeholder="38">
                </div>
                <div class="form-group">
                    <label>Velocidad máxima (chars/seg) <span style="color:var(--text-muted);font-weight:400">— tipeo rápido</span></label>
                    <input type="number" name="human_delays[typing][chars_per_sec_max]" value="<?php echo config_val('human_delays.typing.chars_per_sec_max', '85'); ?>" placeholder="85">
                </div>
                <div class="form-group">
                    <label>Fallback (seg) <span style="color:var(--text-muted);font-weight:400">— si falla el cálculo</span></label>
                    <input type="number" step="0.1" name="human_delays[typing][fallback_sec]" value="<?php echo config_val('human_delays.typing.fallback_sec', '4'); ?>" placeholder="4">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Delay inicio mínimo (ms) <span style="color:var(--text-muted);font-weight:400">— pausa antes de empezar a "escribir"</span></label>
                    <input type="number" name="human_delays[typing][start_min_ms]" value="<?php echo config_val('human_delays.typing.start_min_ms', '350'); ?>" placeholder="350">
                </div>
                <div class="form-group">
                    <label>Delay inicio máximo (ms) <span style="color:var(--text-muted);font-weight:400"></span></label>
                    <input type="number" name="human_delays[typing][start_max_ms]" value="<?php echo config_val('human_delays.typing.start_max_ms', '1200'); ?>" placeholder="1200">
                </div>
                <div class="form-group">
                    <label>Chars entrantes máx. <span style="color:var(--text-muted);font-weight:400">— límite chars del mensaje para calcular delays</span></label>
                    <input type="number" name="human_delays[typing][max_incoming_chars]" value="<?php echo config_val('human_delays.typing.max_incoming_chars', '180'); ?>" placeholder="180">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Chunk size (chars) <span style="color:var(--text-muted);font-weight:400">— divide la escritura en bloques para pausas intermedias</span></label>
                    <input type="number" name="human_delays[typing][chunk_size]" value="<?php echo config_val('human_delays.typing.chunk_size', '24'); ?>" placeholder="24">
                </div>
                <div class="form-group">
                    <label>Chunk pause factor <span style="color:var(--text-muted);font-weight:400">— fracción del tiempo chunk para pausar (0.65 = 65%)</span></label>
                    <input type="number" step="0.01" name="human_delays[typing][chunk_pause_factor]" value="<?php echo config_val('human_delays.typing.chunk_pause_factor', '0.65'); ?>" placeholder="0.65">
                </div>
            </div>
        </div>

        <!-- Habituation -->
        <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:12px">
            <h3 style="margin:0 0 4px">📉 Habituation — Reducción progresiva de delays</h3>
            <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:12px">
                Simula que el bot "se adapta" a la conversación y responde más rápido con el tiempo.
                Al inicio de una conversación los delays se multiplican por <code>start_boost</code>.
                Cada turno ese multiplicador se reduce multiplicándolo por <code>decay</code>,
                hasta llegar al mínimo <code>floor</code>. Nunca bajará de ahí.
                <strong>Recomendado: boost=6.2, decay=0.92, floor=1.25.</strong>
            </p>
            <div class="form-row">
                <div class="form-group">
                    <label>Start boost <span style="color:var(--text-muted);font-weight:400">— multiplicador inicial (primer mensaje muy lento)</span></label>
                    <input type="number" step="0.01" name="human_delays[habituation][start_boost]" value="<?php echo config_val('human_delays.habituation.start_boost', '6.2'); ?>" placeholder="6.2">
                </div>
                <div class="form-group">
                    <label>Decay <span style="color:var(--text-muted);font-weight:400">— cuánto se reduce el boost por turno (0.92 = baja un 8%)</span></label>
                    <input type="number" step="0.01" name="human_delays[habituation][decay]" value="<?php echo config_val('human_delays.habituation.decay', '0.92'); ?>" placeholder="0.92">
                </div>
                <div class="form-group">
                    <label>Floor <span style="color:var(--text-muted);font-weight:400">— multiplicador mínimo (nunca será más rápido que esto)</span></label>
                    <input type="number" step="0.01" name="human_delays[habituation][floor]" value="<?php echo config_val('human_delays.habituation.floor', '1.25'); ?>" placeholder="1.25">
                </div>
            </div>
        </div>

        <!-- Generales -->
        <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 16px;margin-bottom:12px">
            <h3 style="margin:0 0 4px">⚙️ Generales</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Pre-send sleep (seg) <span style="color:var(--text-muted);font-weight:400">— pausa entre mensajes cuando hay varios (ej: fotos). <strong>Recom: 15</strong></span></label>
                    <input type="number" step="0.1" name="human_delays[presend_sleep_sec]" value="<?php echo config_val('human_delays.presend_sleep_sec', '15'); ?>" placeholder="15">
                </div>
                <div class="form-group">
                    <label>Short typing (seg) <span style="color:var(--text-muted);font-weight:400">— typing para respuestas de audio. <strong>Recom: 0.8</strong></span></label>
                    <input type="number" step="0.1" name="human_delays[short_typing_sec]" value="<?php echo config_val('human_delays.short_typing_sec', '0.8'); ?>" placeholder="0.8">
                </div>
                <div class="form-group">
                    <label>After send fallback (seg) <span style="color:var(--text-muted);font-weight:400">— pausa mínima tras enviar. <strong>Recom: 0.4</strong></span></label>
                    <input type="number" step="0.1" name="human_delays[after_send_fallback_sec]" value="<?php echo config_val('human_delays.after_send_fallback_sec', '0.4'); ?>" placeholder="0.4">
                </div>
            </div>
        </div>

        <div style="margin-top:10px">
            <button type="submit" class="btn btn-primary btn-lg">Guardar Human Delays</button>
        </div>
    </div>
</div>

<!-- ===== TAB 8: Variantes de mensajes ===== -->
<div class="tab-content" id="tab-variants">
    <div class="card">
        <h2>Variantes de Mensajes</h2>
        <div class="form-group">
            <label>Audio Auto-Reply (8 líneas, una variante por línea)</label>
            <textarea name="message_variants[audio_auto_reply]" rows="10" class="code-area" spellcheck="false"><?php
                echo h(implode("\n", config_val_array('message_variants.audio_auto_reply')));
            ?></textarea>
        </div>
        <div class="form-group">
            <label>Dedup Start (5 líneas)</label>
            <textarea name="message_variants[dedup_start]" rows="6" class="code-area" spellcheck="false"><?php
                echo h(implode("\n", config_val_array('message_variants.dedup_start')));
            ?></textarea>
        </div>
        <div class="form-group">
            <label>Dedup End (4 líneas)</label>
            <textarea name="message_variants[dedup_end]" rows="5" class="code-area" spellcheck="false"><?php
                echo h(implode("\n", config_val_array('message_variants.dedup_end')));
            ?></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Fallback Empty Text</label>
                <input type="text" name="message_variants[fallback_empty_text]" value="<?php echo config_val('message_variants.fallback_empty_text', 'vale cari'); ?>">
            </div>
            <div class="form-group">
                <label>Dedup Suffix</label>
                <input type="text" name="message_variants[dedup_suffix]" value="<?php echo config_val('message_variants.dedup_suffix', '(asi queda mas claro)'); ?>">
            </div>
        </div>
        <div style="margin-top:10px">
            <button type="submit" class="btn btn-primary btn-lg">Guardar Variantes</button>
        </div>
    </div>
</div>

<!-- ===== TAB 9: Cron Follow-up ===== -->
<div class="tab-content" id="tab-followup">
    <div class="card">
        <h2>Cron Follow-up — Configuración</h2>

        <!-- Toggle enabled -->
        <div style="display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:var(--radius-sm);margin-bottom:16px;<?php echo (bool)$config->get('cron.followup.enabled', true) ? 'background:var(--ok-bg);border:1px solid var(--ok)' : 'background:var(--danger-bg);border:1px solid var(--danger)'; ?>">
            <label class="checkbox-label" style="font-size:1rem;font-weight:700;gap:10px">
                <input type="hidden" name="cron[followup][enabled]" value="0">
                <input type="checkbox" name="cron[followup][enabled]" value="1"
                    <?php echo checked((bool) $config->get('cron.followup.enabled', true)); ?>
                    style="width:18px;height:18px"
                    onchange="this.closest('div').style.background=this.checked?'var(--ok-bg)':'var(--danger-bg)';this.closest('div').style.borderColor=this.checked?'var(--ok)':'var(--danger)'">
                <?php if ((bool)$config->get('cron.followup.enabled', true)): ?>
                    <span style="color:var(--ok)">✅ Cron Follow-up ACTIVADO</span>
                <?php else: ?>
                    <span style="color:var(--danger)">⏸️ Cron Follow-up DESACTIVADO</span>
                <?php endif; ?>
            </label>
            <span style="color:var(--text-muted);font-size:.82rem">
                Cuando está activado, cada ~6 horas re-contacta automáticamente a leads pasados enviándoles fotos de las chicas disponibles.
            </span>
        </div>

        <h3>Parámetros</h3>
        <div class="form-row">
            <div class="form-group">
                <label>Máx. leads por ejecución <span style="color:var(--text-muted);font-weight:400">— cuántos leads procesa cada vez que corre el cron. <strong>Recom: 10</strong></span></label>
                <input type="number" name="cron[followup][max_leads_per_run]" value="<?php echo config_val('cron.followup.max_leads_per_run', '10'); ?>" placeholder="10">
            </div>
            <div class="form-group">
                <label>Timeout cURL (seg) <span style="color:var(--text-muted);font-weight:400">— tiempo máx. para cada llamada a WAHA. <strong>Recom: 20</strong></span></label>
                <input type="number" name="cron[followup][curl_timeout_sec]" value="<?php echo config_val('cron.followup.curl_timeout_sec', '20'); ?>" placeholder="20">
            </div>
            <div class="form-group">
                <label>TTL caché girls (seg) <span style="color:var(--text-muted);font-weight:400">— cuánto se guarda el catálogo en caché local. <strong>Recom: 3600</strong></span></label>
                <input type="number" name="cron[followup][girls_cache_ttl_sec]" value="<?php echo config_val('cron.followup.girls_cache_ttl_sec', '3600'); ?>" placeholder="3600">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Ventana envío — inicio <span style="color:var(--text-muted);font-weight:400">— no envía antes de esta hora (Europe/Madrid)</span></label>
                <input type="text" name="cron[followup][send_window_start]" value="<?php echo config_val('cron.followup.send_window_start', '10:00'); ?>" placeholder="10:00">
            </div>
            <div class="form-group">
                <label>Ventana envío — fin <span style="color:var(--text-muted);font-weight:400">— no envía después de esta hora</span></label>
                <input type="text" name="cron[followup][send_window_end]" value="<?php echo config_val('cron.followup.send_window_end', '22:00'); ?>" placeholder="22:00">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Intervalo mínimo entre followups (h) <span style="color:var(--text-muted);font-weight:400">— mínimo tiempo entre re-contactos al mismo lead. <strong>Recom: 48</strong></span></label>
                <input type="number" name="cron[followup][min_interval_hours_min]" value="<?php echo config_val('cron.followup.min_interval_hours_min', '48'); ?>" placeholder="48">
            </div>
            <div class="form-group">
                <label>Intervalo máximo entre followups (h) <span style="color:var(--text-muted);font-weight:400">— aleatorio hasta este máximo. <strong>Recom: 72</strong></span></label>
                <input type="number" name="cron[followup][min_interval_hours_max]" value="<?php echo config_val('cron.followup.min_interval_hours_max', '72'); ?>" placeholder="72">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Espera entre leads mínima (seg) <span style="color:var(--text-muted);font-weight:400">— pausa entre enviar followup a un lead y el siguiente</span></label>
                <input type="number" name="cron[followup][inter_lead_wait_min_sec]" value="<?php echo config_val('cron.followup.inter_lead_wait_min_sec', '60'); ?>" placeholder="60">
            </div>
            <div class="form-group">
                <label>Espera entre leads máxima (seg) <span style="color:var(--text-muted);font-weight:400">— <strong>Recom: 180</strong></span></label>
                <input type="number" name="cron[followup][inter_lead_wait_max_sec]" value="<?php echo config_val('cron.followup.inter_lead_wait_max_sec', '180'); ?>" placeholder="180">
            </div>
        </div>

        <h3>Timings (microsegundos — 1 seg = 1.000.000 µs)</h3>
        <div class="form-row">
            <div class="form-group"><label>Typing intro mín (µs)</label><input type="number" name="cron[followup][intro_typing_min_us]" value="<?php echo config_val('cron.followup.intro_typing_min_us', '2000000'); ?>"></div>
            <div class="form-group"><label>Typing intro máx (µs)</label><input type="number" name="cron[followup][intro_typing_max_us]" value="<?php echo config_val('cron.followup.intro_typing_max_us', '5000000'); ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Pausa intro→fotos mín (µs)</label><input type="number" name="cron[followup][intro_to_girls_pause_min_us]" value="<?php echo config_val('cron.followup.intro_to_girls_pause_min_us', '5000000'); ?>"></div>
            <div class="form-group"><label>Pausa intro→fotos máx (µs)</label><input type="number" name="cron[followup][intro_to_girls_pause_max_us]" value="<?php echo config_val('cron.followup.intro_to_girls_pause_max_us', '12000000'); ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Typing por chica mín (µs)</label><input type="number" name="cron[followup][per_girl_typing_min_us]" value="<?php echo config_val('cron.followup.per_girl_typing_min_us', '3000000'); ?>"></div>
            <div class="form-group"><label>Typing por chica máx (µs)</label><input type="number" name="cron[followup][per_girl_typing_max_us]" value="<?php echo config_val('cron.followup.per_girl_typing_max_us', '7000000'); ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Pausa entre chicas mín (µs)</label><input type="number" name="cron[followup][inter_girl_pause_min_us]" value="<?php echo config_val('cron.followup.inter_girl_pause_min_us', '5000000'); ?>"></div>
            <div class="form-group"><label>Pausa entre chicas máx (µs)</label><input type="number" name="cron[followup][inter_girl_pause_max_us]" value="<?php echo config_val('cron.followup.inter_girl_pause_max_us', '15000000'); ?>"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Typing cierre mín (µs)</label><input type="number" name="cron[followup][closing_typing_min_us]" value="<?php echo config_val('cron.followup.closing_typing_min_us', '2000000'); ?>"></div>
            <div class="form-group"><label>Typing cierre máx (µs)</label><input type="number" name="cron[followup][closing_typing_max_us]" value="<?php echo config_val('cron.followup.closing_typing_max_us', '4000000'); ?>"></div>
        </div>

        <h3>Variantes de intro</h3>
        <textarea name="cron[followup][intro_variants]" rows="14" class="code-area" spellcheck="false"><?php
            echo h(implode("\n", config_val_array('cron.followup.intro_variants')));
        ?></textarea>

        <h3>Variantes de cierre</h3>
        <textarea name="cron[followup][closing_variants]" rows="8" class="code-area" spellcheck="false"><?php
            echo h(implode("\n", config_val_array('cron.followup.closing_variants')));
        ?></textarea>

        <div style="margin-top:10px">
            <button type="submit" class="btn btn-primary btn-lg">Guardar Follow-up</button>
        </div>
    </div>
</div>

<!-- ===== TAB 10: Cron Reminder ===== -->
<div class="tab-content" id="tab-reminder">
    <div class="card">
        <h2>Cron Reminder — Configuración</h2>

        <!-- Toggle enabled -->
        <div style="display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:var(--radius-sm);margin-bottom:16px;<?php echo (bool)$config->get('cron.reminder.enabled', true) ? 'background:var(--ok-bg);border:1px solid var(--ok)' : 'background:var(--danger-bg);border:1px solid var(--danger)'; ?>">
            <label class="checkbox-label" style="font-size:1rem;font-weight:700;gap:10px">
                <input type="hidden" name="cron[reminder][enabled]" value="0">
                <input type="checkbox" name="cron[reminder][enabled]" value="1"
                    <?php echo checked((bool) $config->get('cron.reminder.enabled', true)); ?>
                    style="width:18px;height:18px"
                    onchange="this.closest('div').style.background=this.checked?'var(--ok-bg)':'var(--danger-bg)';this.closest('div').style.borderColor=this.checked?'var(--ok)':'var(--danger)'">
                <?php if ((bool)$config->get('cron.reminder.enabled', true)): ?>
                    <span style="color:var(--ok)">✅ Cron Reminder ACTIVADO</span>
                <?php else: ?>
                    <span style="color:var(--danger)">⏸️ Cron Reminder DESACTIVADO</span>
                <?php endif; ?>
            </label>
            <span style="color:var(--text-muted);font-size:.82rem">
                Cuando está activado, cada minuto revisa si algún usuario dijo que venía en X minutos y, cuando ese tiempo expira, le envía un recordatorio automático.
            </span>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label>Máx. por ejecución <span style="color:var(--text-muted);font-weight:400">— cuántos recordatorios envía como máximo cada vez. <strong>Recom: 5</strong></span></label>
                <input type="number" name="cron[reminder][max_per_run]" value="<?php echo config_val('cron.reminder.max_per_run', '5'); ?>" placeholder="5">
            </div>
            <div class="form-group">
                <label>Timeout cURL (seg) <span style="color:var(--text-muted);font-weight:400">— tiempo máx. para cada llamada a WAHA. <strong>Recom: 15</strong></span></label>
                <input type="number" name="cron[reminder][curl_timeout_sec]" value="<?php echo config_val('cron.reminder.curl_timeout_sec', '15'); ?>" placeholder="15">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Intervalo limpieza <span style="color:var(--text-muted);font-weight:400">— cada cuántas ejecuciones elimina recordatorios enviados viejos. <strong>Recom: 5</strong></span></label>
                <input type="number" name="cron[reminder][cleanup_interval]" value="<?php echo config_val('cron.reminder.cleanup_interval', '5'); ?>" placeholder="5">
            </div>
            <div class="form-group">
                <label>Edad máx. limpieza (seg) <span style="color:var(--text-muted);font-weight:400">— elimina enviados más viejos que esto. <strong>Recom: 86400 (1 día)</strong></span></label>
                <input type="number" name="cron[reminder][cleanup_max_age_sec]" value="<?php echo config_val('cron.reminder.cleanup_max_age_sec', '86400'); ?>" placeholder="86400">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Pausa entre envíos mín (µs) <span style="color:var(--text-muted);font-weight:400">— 3000000 = 3 seg</span></label>
                <input type="number" name="cron[reminder][sleep_between_min_us]" value="<?php echo config_val('cron.reminder.sleep_between_min_us', '3000000'); ?>" placeholder="3000000">
            </div>
            <div class="form-group">
                <label>Pausa entre envíos máx (µs) <span style="color:var(--text-muted);font-weight:400">— 8000000 = 8 seg</span></label>
                <input type="number" name="cron[reminder][sleep_between_max_us]" value="<?php echo config_val('cron.reminder.sleep_between_max_us', '8000000'); ?>" placeholder="8000000">
            </div>
            <div class="form-group">
                <label>Typing mín (µs) <span style="color:var(--text-muted);font-weight:400">— 1000000 = 1 seg</span></label>
                <input type="number" name="cron[reminder][sleep_typing_min_us]" value="<?php echo config_val('cron.reminder.sleep_typing_min_us', '1000000'); ?>" placeholder="1000000">
            </div>
            <div class="form-group">
                <label>Typing máx (µs) <span style="color:var(--text-muted);font-weight:400">— 4000000 = 4 seg</span></label>
                <input type="number" name="cron[reminder][sleep_typing_max_us]" value="<?php echo config_val('cron.reminder.sleep_typing_max_us', '4000000'); ?>" placeholder="4000000">
            </div>
        </div>

        <h3>Variantes de mensajes de reminder</h3>
        <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:8px">Un mensaje por línea. Se elige aleatoriamente sin repetir el anterior.</p>
        <textarea name="cron[reminder][message_variants]" rows="12" class="code-area" spellcheck="false"><?php
            echo h(implode("\n", config_val_array('cron.reminder.message_variants')));
        ?></textarea>

        <div style="margin-top:10px">
            <button type="submit" class="btn btn-primary btn-lg">Guardar Reminder</button>
        </div>
    </div>
</div>

<!-- ===== TAB 11: URLs externas ===== -->
<div class="tab-content" id="tab-urls">
    <div class="card">
        <h2>URLs Externas</h2>
        <div class="form-group">
            <label>Girls JSON URL</label>
            <input type="url" name="urls[girls_json]" value="<?php echo config_val('urls.girls_json'); ?>">
        </div>
        <div class="form-group">
            <label>Blacklist WS URL</label>
            <input type="url" name="urls[blacklist_ws]" value="<?php echo config_val('urls.blacklist_ws'); ?>">
        </div>
        <div class="form-group">
            <label>Google Maps Location URL</label>
            <input type="url" name="urls[google_maps_location]" value="<?php echo config_val('urls.google_maps_location'); ?>">
        </div>
        <div style="margin-top:10px">
            <button type="submit" class="btn btn-primary btn-lg">Guardar URLs</button>
        </div>
    </div>
</div>

</form> <!-- END main config form -->

<!-- ===== TAB 12: Gestión de Memoria (outside main form) ===== -->
<div class="tab-content" id="tab-memory">
    <div class="card">
        <h2>Gestión de Memoria (Session Memory)</h2>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:flex-end">
            <form method="post" action="<?php echo h($baseUrl); ?>?action=delete_memory_thread" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
                <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
                <input type="hidden" name="active_tab" value="tab-memory">
                <div class="form-group" style="margin-bottom:0">
                    <label>Borrar por thread_id</label>
                    <input type="text" name="thread_id" placeholder="Thread ID" required style="width:200px">
                </div>
                <button type="submit" class="btn btn-danger">Eliminar Thread</button>
            </form>

            <form method="post" action="<?php echo h($baseUrl); ?>?action=clear_memory" style="display:inline" onsubmit="return confirm('¿Seguro que quieres VACIAR toda la memoria? Esta acción no se puede deshacer.')">
                <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
                <input type="hidden" name="active_tab" value="tab-memory">
                <button type="submit" class="btn btn-danger">Vaciar TODA la memoria</button>
            </form>
        </div>

        <p style="color:var(--text-muted);font-size:.8rem;margin-bottom:16px">
            Cada fila representa una conversación. Pulsa sobre la fila para ver el diálogo completo.
        </p>

        <?php
        // Flatten threads into a single list of conversations for the new row-per-conversation view
        $allConversations = [];
        foreach ($memoryGroups as $group) {
            foreach ($group['threads'] as $thread) {
                $lastLine   = end($thread['lines']);
                $lastTsRaw  = $lastLine['timestamp'] ?? '';
                $lastPreview = $lastLine['preview'] ?? '';
                // Format last timestamp for display
                $lastTsFormatted = '';
                if ($lastTsRaw && ($tsNum = strtotime(str_replace('T', ' ', $lastTsRaw)))) {
                    $lastTsFormatted = date('d/m H:i', $tsNum);
                }
                if ($lastTsFormatted === '') {
                    $lastTsFormatted = $lastTsRaw;
                }
                $allConversations[] = [
                    'phone'           => (string) $group['phone'],
                    'thread_id'       => $thread['thread_id'],
                    'msg_count'       => count($thread['lines']),
                    'last_ts'         => $lastTsFormatted,
                    'last_ts_raw'     => $lastTsRaw,
                    'last_preview'    => $lastPreview,
                ];
            }
        }
        // Sort conversations by last timestamp descending (newest first)
        usort($allConversations, function($a, $b) {
            $tsA = strtotime(str_replace('T', ' ', $a['last_ts_raw'] ?? ''));
            $tsB = strtotime(str_replace('T', ' ', $b['last_ts_raw'] ?? ''));
            return $tsB <=> $tsA;
        });
        ?>
        <?php if (empty($allConversations)): ?>
        <div style="text-align:center;color:var(--text-muted);padding:20px">
            Sin conversaciones en memoria.
        </div>
        <?php else: ?>
        <div class="memory-conversation-list">
            <?php foreach ($allConversations as $conv): ?>
            <div class="memory-conversation-row"
                 style="display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid var(--border-soft);cursor:pointer;transition:background .15s"
                 data-thread-id="<?php echo h($conv['thread_id']); ?>"
                 onmouseover="this.style.background='rgba(245,158,11,.06)';this.style.borderLeft='3px solid var(--accent)'"
                 onmouseout="this.style.background='';this.style.borderLeft='3px solid transparent'"
                 onclick="openConversationModal('<?php echo h($conv['thread_id']); ?>')">
                <div style="flex:1;min-width:0;display:flex;align-items:center;gap:12px">
                    <span style="font-size:.8rem;color:var(--accent);white-space:nowrap;font-weight:600">📱 <?php echo h($conv['phone']); ?></span>
                    <code style="font-size:.7rem;color:var(--text-muted);white-space:nowrap"><?php echo h(strlen($conv['thread_id']) > 16 ? substr($conv['thread_id'], 0, 16) . '…' : $conv['thread_id']); ?></code>
                    <span style="font-size:.75rem;color:var(--text-muted);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo h($conv['last_preview']); ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:10px;flex-shrink:0;margin-left:12px">
                    <span style="font-size:.7rem;color:var(--text-muted);white-space:nowrap" title="Última actividad"><?php echo h($conv['last_ts']); ?></span>
                    <span style="background:var(--input-bg);color:var(--text-muted);font-size:.7rem;padding:2px 8px;border-radius:10px;white-space:nowrap"><?php echo (int) $conv['msg_count']; ?> msgs</span>
                    <form method="post" action="<?php echo h($baseUrl); ?>?action=delete_memory_thread" style="display:inline;flex-shrink:0" onsubmit="event.stopPropagation(); if(!confirm('¿Eliminar toda la conversación con thread «<?php echo h($conv['thread_id']); ?>»?')) return false;">
                        <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
                        <input type="hidden" name="thread_id" value="<?php echo h($conv['thread_id']); ?>">
                        <input type="hidden" name="active_tab" value="tab-memory">
                        <button type="submit" class="btn btn-danger btn-sm" style="font-size:.7rem;padding:2px 6px" title="Eliminar esta conversación">X</button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <p style="margin-top:10px;color:var(--text-muted);font-size:.8rem">
            Total: <?php echo count($allConversations); ?> conversaciones, <?php echo count($memoryLines); ?> mensajes en <?php echo count($memoryGroups); ?> teléfonos.
        </p>
    </div>
</div>

<!-- ===== MODAL: Conversación Completa ===== -->
<div id="conversationModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.75);z-index:9999;align-items:center;justify-content:center">
    <div style="background:var(--panel);border:1px solid var(--border);border-radius:var(--radius-md);max-width:750px;width:90%;max-height:85vh;display:flex;flex-direction:column;box-shadow:var(--shadow-md)">
        <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--border)">
            <h3 style="margin:0;font-size:1rem">Conversación — <span id="convModalPhone" style="color:var(--accent)"></span></h3>
            <button onclick="closeConversationModal()" style="background:none;border:none;color:var(--text-muted);font-size:1.4rem;cursor:pointer;line-height:1">&times;</button>
        </div>
        <div id="conversationContent" style="flex:1;overflow-y:auto;padding:14px 18px;font-size:.84rem;line-height:1.6;max-height:60vh">
            <p style="color:var(--text-muted);text-align:center">Cargando conversación…</p>
        </div>
    </div>
</div>

<script>
var _convModalCsrf = '<?php echo h(generateCsrfToken()); ?>';
var _convModalBaseUrl = '<?php echo h($baseUrl); ?>';

function openConversationModal(threadId) {
    var modal = document.getElementById('conversationModal');
    modal.style.display = 'flex';
    document.getElementById('convModalPhone').textContent = threadId;
    document.getElementById('conversationContent').innerHTML = '<p style="color:var(--text-muted);text-align:center">Cargando conversación…</p>';

    fetch(_convModalBaseUrl + '?action=get_thread_conversation&thread_id=' + encodeURIComponent(threadId))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var html = '';
            if (!data.ok || !data.records || data.records.length === 0) {
                html = '<p style="color:var(--text-muted);text-align:center">Sin registros para este hilo.</p>';
            } else {
                data.records.forEach(function(rec, idx) {
                    var ts = rec.ts || rec.timestamp || '';
                    var dateStr = '';
                    if (ts) {
                        try { var d = new Date(ts.replace(' ', 'T')); dateStr = d.toLocaleString('es-ES', {day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}); } catch(e) {}
                    }
                    var userMsg = rec.user_msg || rec.body || '';
                    var botMsg  = rec.bot_reply || (rec['| B:'] || '');
                    var lineIdx = rec.line_index !== undefined ? rec.line_index : idx;
                    html += '<div class="conv-message-bubble" id="conv-msg-' + lineIdx + '" style="margin-bottom:10px;padding:8px 10px;background:var(--bg-surface);border-radius:8px;border:1px solid var(--border-soft);position:relative">';
                    // Header with timestamp and X delete button
                    html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">';
                    if (dateStr) html += '<span style="font-size:.7rem;color:var(--text-muted)">' + dateStr + '</span>';
                    else html += '<span></span>';
                    html += '<button onclick="deleteMemoryLine(' + lineIdx + ', this)" title="Eliminar este mensaje" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:.9rem;line-height:1;padding:2px 6px;opacity:.6;transition:opacity .15s" onmouseover="this.style.opacity=\'1\'" onmouseout="this.style.opacity=\'.6\'">&times;</button>';
                    html += '</div>';
                    // Message bodies
                    if (userMsg) html += '<div style="margin-bottom:4px"><span style="color:var(--info);font-size:.72rem">📥 Usuario:</span><br>' + escHtml(userMsg) + '</div>';
                    if (botMsg)  html += '<div><span style="color:var(--ok);font-size:.72rem">📤 Bot:</span><br>' + escHtml(botMsg) + '</div>';
                    html += '</div>';
                });
            }
            document.getElementById('conversationContent').innerHTML = html;
        })
        .catch(function() {
            document.getElementById('conversationContent').innerHTML = '<p style="color:var(--danger);text-align:center">Error al cargar la conversación.</p>';
        });
}

function deleteMemoryLine(lineIndex, btnElement) {
    if (!confirm('¿Eliminar este mensaje?')) return;
    btnElement.disabled = true;
    btnElement.textContent = '…';
    var formData = new FormData();
    formData.append('csrf_token', _convModalCsrf);
    formData.append('line_index', lineIndex);
    fetch(_convModalBaseUrl + '?action=delete_memory_line_ajax', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok) {
                var msgDiv = document.getElementById('conv-msg-' + lineIndex);
                if (msgDiv) {
                    msgDiv.style.opacity = '0.3';
                    msgDiv.style.textDecoration = 'line-through';
                    // Remove after animation
                    setTimeout(function() {
                        if (msgDiv.parentNode) msgDiv.remove();
                        // If no messages left, refresh parent to update counts
                        if (document.querySelectorAll('.conv-message-bubble').length === 0) {
                            location.reload();
                        }
                    }, 400);
                }
            } else {
                btnElement.disabled = false;
                btnElement.textContent = '×';
                alert('Error al eliminar el mensaje.');
            }
        })
        .catch(function() {
            btnElement.disabled = false;
            btnElement.textContent = '×';
            alert('Error de conexión al eliminar el mensaje.');
        });
}

function closeConversationModal() {
    document.getElementById('conversationModal').style.display = 'none';
}

function escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

// Cerrar modal con Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeConversationModal();
});
// Cerrar modal click fuera
document.getElementById('conversationModal').addEventListener('click', function(e) {
    if (e.target === this) closeConversationModal();
});
</script>

<!-- ===== TAB: Aprendizaje ===== -->
<div class="tab-content" id="tab-learning">
    <div class="card">
        <h2>🧠 Aprendizaje del Bot</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:16px">
            El bot analiza sus conversaciones pasadas y extrae patrones que se inyectan en su system prompt (playbook).
            El aprendizaje mejora automáticamente con cada ciclo de análisis.
        </p>
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px">
            <div style="flex:1;min-width:120px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;text-align:center">
                <div style="font-size:1.8rem;font-weight:700;color:var(--primary)"><?php echo $learningStats['total_classified']; ?></div>
                <div style="font-size:.78rem;color:var(--text-muted);margin-top:4px">Conversaciones analizadas</div>
            </div>
            <div style="flex:1;min-width:120px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;text-align:center">
                <div style="font-size:1.8rem;font-weight:700;color:var(--success)"><?php echo $learningStats['lead_probable'] + $learningStats['lead_confirmado']; ?></div>
                <div style="font-size:.78rem;color:var(--text-muted);margin-top:4px">Leads</div>
            </div>
            <div style="flex:1;min-width:120px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;text-align:center">
                <div style="font-size:1.8rem;font-weight:700;color:var(--warn)"><?php echo $learningStats['mareador']; ?></div>
                <div style="font-size:.78rem;color:var(--text-muted);margin-top:4px">Mareadores</div>
            </div>
            <div style="flex:1;min-width:120px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;text-align:center">
                <div style="font-size:1.8rem;font-weight:700;color:var(--danger)"><?php echo $learningStats['lead_ghosted']; ?></div>
                <div style="font-size:.78rem;color:var(--text-muted);margin-top:4px">Ghosteos</div>
            </div>
            <div style="flex:1;min-width:120px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;text-align:center">
                <div style="font-size:1.8rem;font-weight:700;color:var(--info)"><?php echo $learningStats['pending_review']; ?></div>
                <div style="font-size:.78rem;color:var(--text-muted);margin-top:4px">Pendientes revisión</div>
            </div>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center">
            <button type="button" class="btn btn-primary" onclick="forceLearn()" id="btn-force-learn">🚀 Forzar aprendizaje</button>
            <span id="learn-status" style="font-size:.82rem;color:var(--text-muted)"></span>
        </div>
        <div id="learn-output" style="display:none;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;font-family:monospace;font-size:.75rem;max-height:300px;overflow:auto;white-space:pre-wrap;margin-bottom:16px"></div>
        <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;margin-bottom:20px">
            <strong>Playbook:</strong>
            <?php if ($learningStats['playbook_exists']): ?>
                <span style="color:var(--success)">✅ Activo</span>
                <span style="color:var(--text-muted);font-size:.78rem;margin-left:8px">Actualizado: <?php echo h($learningStats['playbook_updated'] ? date('d/m/Y H:i', strtotime((string)$learningStats['playbook_updated'])) : '?'); ?></span>
                <button type="button" class="btn btn-sm btn-info" style="margin-left:12px" onclick="viewPlaybook()">📖 Ver playbook</button>
            <?php else: ?>
                <span style="color:var(--danger)">❌ No generado</span>
            <?php endif; ?>
        </div>
        <div id="playbook-preview" style="display:none;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;max-height:500px;overflow:auto;font-size:.82rem;white-space:pre-wrap;margin-bottom:20px"></div>
        <h3 style="margin-top:24px;margin-bottom:12px">📋 Conversaciones clasificadas</h3>
        <div style="display:flex;gap:8px;margin-bottom:12px;align-items:center">
            <button type="button" class="btn btn-sm" onclick="loadOutcomes()">🔄 Refrescar</button>
            <span id="outcomes-count" style="font-size:.8rem;color:var(--text-muted)"></span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:.82rem">
                <thead><tr style="background:var(--bg)">
                    <th style="padding:8px;text-align:left;border-bottom:1px solid var(--border)">Teléfono</th>
                    <th style="padding:8px;text-align:left;border-bottom:1px solid var(--border)">Outcome</th>
                    <th style="padding:8px;text-align:left;border-bottom:1px solid var(--border)">Msgs</th>
                    <th style="padding:8px;text-align:left;border-bottom:1px solid var(--border)">Acción</th>
                </tr></thead>
                <tbody id="outcomes-tbody">
                    <tr><td colspan="4" style="padding:20px;text-align:center;color:var(--text-muted)">Cargando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===== TAB 13: Logs ===== -->
<div class="tab-content" id="tab-logs">
    <div class="card">
        <h2>Logs del bot <span style="font-weight:400;font-size:.82rem;color:var(--text-muted)">(últimas <?php echo count($logLines); ?> líneas de <code>data/bot.log</code>)</span></h2>

        <?php if (empty($logLines)): ?>
        <p style="color:var(--text-muted);font-size:.9rem;padding:20px 0;text-align:center">
            No hay logs todavía. El archivo <code><?php echo h($logFilePath); ?></code>
            <?php echo file_exists($logFilePath) ? 'existe pero está vacío.' : 'aún no existe (se creará al procesar el primer mensaje).'; ?>
        </p>
        <?php else: ?>
        <div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap;align-items:center">
            <button type="button" class="btn btn-sm btn-primary" onclick="document.getElementById('logBox').scrollTop=document.getElementById('logBox').scrollHeight">↓ Ir al final</button>
            <button type="button" class="btn btn-sm btn-warning" onclick="location.reload()">Refrescar</button>
            <span style="color:var(--text-muted);font-size:.78rem">Se muestran las últimas 300 líneas. Se rota automáticamente al superar 5 MB.</span>
        </div>
        <pre id="logBox" style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;font-family:'SF Mono','Fira Code','Consolas',monospace;font-size:.78rem;line-height:1.55;color:var(--text-muted);overflow:auto;max-height:600px;white-space:pre-wrap;word-break:break-all"><?php
        foreach ($logLines as $ll) {
            // Color coding by level
            $levelColor = 'var(--text-muted)';
            if (strpos($ll, '] ERROR:') !== false || strpos($ll, '] CRITICAL:') !== false || strpos($ll, '] EMERGENCY:') !== false) {
                $levelColor = 'var(--danger)';
            } elseif (strpos($ll, '] WARNING:') !== false) {
                $levelColor = 'var(--warn)';
            } elseif (strpos($ll, '] INFO:') !== false) {
                $levelColor = 'var(--info)';
            } elseif (strpos($ll, '] DEBUG:') !== false) {
                $levelColor = '#6b7280';
            }
            echo '<span style="color:' . $levelColor . '">' . h($ll) . '</span>' . "\n";
        }
        ?></pre>
        <?php endif; ?>
    </div>
</div>

<!-- ===== TAB: Usuarios (solo admin) ===== -->
<?php if ($isAdmin): ?>
<div class="tab-content" id="tab-users">
    <div class="card">
        <h2>Gestión de Usuarios</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:16px">
            Crea, edita y gestiona los usuarios con acceso al sistema bot-casa.
        </p>

        <?php
        // ── Notification messages ──
        $userMsg = '';
        if (isset($_GET['user_created'])) { $userMsg = '<div class="alert alert-success">Usuario creado correctamente.</div>'; }
        if (isset($_GET['user_updated'])) { $userMsg = '<div class="alert alert-success">Usuario actualizado correctamente.</div>'; }
        if (isset($_GET['user_deleted'])) { $userMsg = '<div class="alert alert-info">Usuario desactivado.</div>'; }
        if (isset($_GET['user_error'])) { $userMsg = '<div class="alert alert-warning">Error: ' . h((string) $_GET['user_error']) . '</div>'; }
        echo $userMsg;
        ?>

        <!-- Lista de usuarios -->
        <div style="overflow-x:auto;margin-bottom:24px">
            <table class="memory-table" style="font-size:.83rem">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Activo</th>
                        <th>Creado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $allUsers = $userManager->listUsers();
                foreach ($allUsers as $u):
                    $uid       = (int) ($u['id'] ?? 0);
                    $uname     = h((string) ($u['username'] ?? ''));
                    $unameFull = h((string) ($u['name'] ?? ''));
                    $urole     = (string) ($u['role'] ?? 'user');
                    $uactive   = (bool) ($u['active'] ?? true);
                    $ucreated  = (string) ($u['created_at'] ?? '');
                    $ucreatedDisp = '';
                    if ($ucreated !== '') {
                        try { $dt = new DateTimeImmutable($ucreated); $ucreatedDisp = $dt->format('d/m/Y H:i'); }
                        catch (\Exception) { $ucreatedDisp = $ucreated; }
                    }
                    $roleBadge = $urole === 'admin'
                        ? '<span style="background:var(--accent);color:#1a1206;padding:2px 8px;border-radius:4px;font-size:.75rem;font-weight:600">Admin</span>'
                        : '<span style="background:var(--input-bg);padding:2px 8px;border-radius:4px;font-size:.75rem">Usuario</span>';
                    $activeBadge = $uactive
                        ? '<span style="color:var(--ok);font-weight:600">✅</span>'
                        : '<span style="color:var(--danger)">❌</span>';
                ?>
                <tr>
                    <td class="mono"><?php echo $uid; ?></td>
                    <td><strong><?php echo $uname; ?></strong></td>
                    <td><?php echo $unameFull; ?></td>
                    <td><?php echo $roleBadge; ?></td>
                    <td style="text-align:center"><?php echo $activeBadge; ?></td>
                    <td class="mono" style="font-size:.75rem"><?php echo h($ucreatedDisp); ?></td>
                    <td style="white-space:nowrap">
                        <!-- Suplantar -->
                        <form method="post" action="cliente" style="display:inline" target="_blank">
                            <input type="hidden" name="suplantar_user_id" value="<?php echo $uid; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo h((string)($_SESSION['csrf_token'] ?? '')); ?>">
                            <button type="submit" class="btn btn-sm" style="background:var(--info);color:#fff;margin-right:4px" title="Ver panel como este usuario">🔍 Ver</button>
                        </form>
                        <?php if ($uid !== 1): ?>
                        <!-- Editar / Eliminar -->
                        <button type="button" class="btn btn-sm btn-warning"
                                onclick="editUser(<?php echo $uid; ?>, <?php echo htmlspecialchars(json_encode((string)($u['username'] ?? ''), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode((string)($u['name'] ?? ''), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>, <?php echo htmlspecialchars(json_encode($urole), ENT_QUOTES, 'UTF-8'); ?>, <?php echo $uactive ? 'true' : 'false'; ?>)"
                                style="margin-right:4px">✏️</button>
                        <form method="post" action="<?php echo h($baseUrl); ?>?action=delete_user" style="display:inline" onsubmit="return confirm('¿Desactivar al usuario <?php echo $uname; ?>?')">
                            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
                            <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                            <input type="hidden" name="active_tab" value="tab-users">
                            <button type="submit" class="btn btn-sm btn-danger">🗑️</button>
                        </form>
                        <?php else: ?>
                        <span style="color:var(--text-muted);font-size:.75rem">Principal</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($allUsers)): ?>
                <tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-muted)">No hay usuarios registrados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Formulario crear/editar usuario -->
        <h3 id="user-form-title" style="margin-bottom:12px">➕ Nuevo usuario</h3>
        <form method="post" action="<?php echo h($baseUrl); ?>?action=save_user" style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:20px">
            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
            <input type="hidden" name="user_id" id="user-form-id" value="">
            <input type="hidden" name="active_tab" value="tab-users">

            <div class="form-row">
                <div class="form-group" style="flex:2">
                    <label>Usuario *</label>
                    <input type="text" name="username" id="user-form-username" required placeholder="nombre de usuario">
                </div>
                <div class="form-group" style="flex:1">
                    <label>Rol</label>
                    <select name="role" id="user-form-role">
                        <option value="user">Usuario</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group" style="flex:2">
                    <label>Nombre completo</label>
                    <input type="text" name="name" id="user-form-name" placeholder="Nombre descriptivo">
                </div>
                <div class="form-group" style="flex:1">
                    <label>Contraseña <span style="color:var(--text-muted);font-weight:400">(dejar vacío para no cambiar)</span></label>
                    <input type="password" name="password" id="user-form-password" placeholder="Dejar vacío para no cambiar">
                </div>
            </div>

            <div class="form-group">
                <label class="checkbox-label">
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" id="user-form-active" value="1" checked> Usuario activo
                </label>
            </div>

            <div style="display:flex;gap:10px;margin-top:8px">
                <button type="submit" class="btn btn-primary">💾 Guardar usuario</button>
                <button type="button" class="btn btn-sm" style="background:var(--input-bg);color:var(--text-muted)" onclick="resetUserForm()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── User form helpers ──
function editUser(id, username, name, role, active) {
    document.getElementById('user-form-title').textContent = '✏️ Editar usuario';
    document.getElementById('user-form-id').value = id;
    document.getElementById('user-form-username').value = username;
    document.getElementById('user-form-name').value = name;
    document.getElementById('user-form-role').value = role;
    document.getElementById('user-form-active').checked = active;
    document.getElementById('user-form-password').value = '';
    document.getElementById('user-form-password').placeholder = 'Dejar vacío para no cambiar';
    // Scroll to form
    document.getElementById('user-form-title').scrollIntoView({ behavior: 'smooth' });
}
function resetUserForm() {
    document.getElementById('user-form-title').textContent = '➕ Nuevo usuario';
    document.getElementById('user-form-id').value = '';
    document.getElementById('user-form-username').value = '';
    document.getElementById('user-form-name').value = '';
    document.getElementById('user-form-role').value = 'user';
    document.getElementById('user-form-active').checked = true;
    document.getElementById('user-form-password').value = '';
    document.getElementById('user-form-password').placeholder = 'Nueva contraseña (opcional)';
}
</script>
<?php endif; ?>

<script>
// Auto-scroll log to bottom on load
(function() {
    var logBox = document.getElementById('logBox');
    if (logBox) { logBox.scrollTop = logBox.scrollHeight; }
})();
</script>

<script>
// ── Tab switching with persistence ──
(function() {
    var STORAGE_KEY = 'botcasa_active_tab';
    var nav = document.getElementById('tabNav');
    if (!nav) return;
    var buttons = nav.querySelectorAll('button');
    var tabs = document.querySelectorAll('.tab-content');

    /**
     * Activate a tab by its ID (e.g. "tab-status").
     * Also updates all hidden active_tab inputs so every form POST
     * carries the current tab and the server can redirect back to it.
     */
    function activateTab(tabId) {
        var found = false;
        buttons.forEach(function(b) {
            var isTarget = b.getAttribute('data-tab') === tabId;
            b.classList.toggle('active', isTarget);
            if (isTarget) found = true;
        });
        // Fallback: if tabId not found, activate first tab
        if (!found) {
            tabId = buttons[0] ? buttons[0].getAttribute('data-tab') : 'tab-status';
            buttons.forEach(function(b) {
                b.classList.toggle('active', b.getAttribute('data-tab') === tabId);
            });
        }
        tabs.forEach(function(t) {
            t.classList.toggle('active', t.id === tabId);
        });
        // Persist choice
        try { localStorage.setItem(STORAGE_KEY, tabId); } catch(e) {}
        // Keep all active_tab hidden inputs in sync so every form knows the current tab
        document.querySelectorAll('.js-active-tab-input').forEach(function(inp) {
            inp.value = tabId;
        });
    }

    // Determine initial tab: URL param > localStorage > default
    var initialTab = '';
    try {
        var params = new URLSearchParams(window.location.search);
        initialTab = params.get('tab') || '';
    } catch(e) {}
    if (!initialTab) {
        try { initialTab = localStorage.getItem(STORAGE_KEY) || ''; } catch(e) {}
    }
    if (!initialTab) { initialTab = 'tab-status'; }
    activateTab(initialTab);

    // Wire click handlers
    buttons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            activateTab(this.getAttribute('data-tab'));
        });
    });
})();

// ── Prompt preview: realtime assembly ──
(function() {
    var sectionKeys = ['rol','estilo','tarifas','servicios','ubicacion','instrucciones_fotos','identidad_chicas','seguridad','ejemplos','formato_respuesta'];

    // Known LLM placeholders in prompt examples that are NOT template tags
    var knownPlaceholders = ['selected', 'nombre', 'audio'];

    function getVal(key) {
        // Try matching textarea by name attribute
        var ta = document.querySelector('textarea[name="prompt[sections][' + key + ']"]');
        if (ta) return ta.value;
        // Fallback: try by id
        ta = document.getElementById('prompt-section-' + key);
        return ta ? ta.value : '';
    }

    window.rebuildPreview = function() {
        var template = document.getElementById('prompt-template');
        var preview = document.getElementById('prompt-preview');
        var stats = document.getElementById('prompt-stats');
        if (!template || !preview || !stats) return;

        var text = template.value;
        sectionKeys.forEach(function(k) {
            // Replace all occurrences (global flag needed)
            var re = new RegExp('\\[' + k + '\\]', 'g');
            text = text.replace(re, getVal(k));
        });

        preview.textContent = text;

        // Count unreplaced tags
        var unreplaced = [];
        var tagRe = /\[([a-z_]+)\]/g;
        var m;
        while ((m = tagRe.exec(text)) !== null) {
            if (knownPlaceholders.indexOf(m[1]) === -1 && unreplaced.indexOf(m[1]) === -1) {
                unreplaced.push(m[1]);
            }
        }

        var chars = text.length;
        var hasUnreplaced = unreplaced.length > 0;
        stats.innerHTML =
            '<span class="' + (chars > 0 ? 'stat-ok' : 'stat-warn') + '">' + chars + ' chars</span>' +
            '<span>' + (hasUnreplaced
                ? '<span class="stat-warn">⚠ ' + unreplaced.length + ' sin reemplazar: [' + unreplaced.join(', ') + ']</span>'
                : '<span class="stat-ok">✓ 0 tags sueltos</span>') +
            '</span>';
    };

    window.insertTag = function(key) {
        var ta = document.getElementById('prompt-template');
        if (!ta) return;
        var tag = '[' + key + ']';
        var start = ta.selectionStart;
        var end = ta.selectionEnd;
        var before = ta.value.substring(0, start);
        var after = ta.value.substring(end);
        ta.value = before + tag + after;
        ta.selectionStart = ta.selectionEnd = start + tag.length;
        ta.focus();
        rebuildPreview();
    };

    // Initial preview on page load if the tab is visible
    if (document.getElementById('tab-prompt')) {
        rebuildPreview();
    }
})();

// ── Routing lines: add row manually ──
var routingRowCount = <?php echo count($routingLines); ?>;
function addRoutingRowManual() {
    var tbody = document.querySelector('#routingTable tbody');
    var idx = routingRowCount;
    routingRowCount++;

    var tr = document.createElement('tr');
    tr.className = 'routing-row';
    tr.setAttribute('data-port', '');
    tr.setAttribute('data-last9', '');
    tr.innerHTML = [
        '<td><input type="text" name="routing[lines][' + idx + '][last9]" value="" placeholder="Últimos 9 dígitos" class="input-cell"></td>',
        '<td><input type="number" name="routing[lines][' + idx + '][port]" value="" placeholder="3000" class="input-cell" style="width:80px"></td>',
        '<td><input type="text" name="routing[lines][' + idx + '][label]" value="" placeholder="linea_3000" class="input-cell"></td>',
        '<td class="descripcion-cell"></td>',
        '<td><select name="routing[lines][' + idx + '][ai_provider]" class="input-cell" style="width:110px"><option value="openai">OpenAI</option><option value="deepseek">DeepSeek</option></select><input type="hidden" name="routing[lines][' + idx + '][ai_model]" value=""></td>',
        '<td style="text-align:center"><input type="hidden" name="routing[lines][' + idx + '][enabled]" value="0"><input type="checkbox" name="routing[lines][' + idx + '][enabled]" value="1" checked></td>',
        '<td class="waha-status-cell" data-port=""><span class="status-dot status-unknown"></span> —</td>',
        '<td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'tr\').remove()">X</button></td>'
    ].join('');
    tbody.appendChild(tr);
}

// ── Routing lines: add row from selector ──
var _telefonosData = [];

function loadTelefonosIntoSelector() {
    var sel = document.getElementById('telefonoSelector');
    if (!sel) return;
    fetch('?action=get_telefonos_lines')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            _telefonosData = data.lines || [];
            sel.innerHTML = '<option value="">— Seleccionar línea del CRM —</option>';
            _telefonosData.forEach(function(line, i) {
                var label = line.nombre + ' · ' + line.tfono;
                if (line.uso) label += ' (' + line.uso + ')';
                if (line.waha_port) label += ' — Puerto ' + line.waha_port;
                var opt = document.createElement('option');
                opt.value = i;
                opt.textContent = label;
                sel.appendChild(opt);
            });
        })
        .catch(function() {
            sel.innerHTML = '<option value="">— Error al cargar teléfonos —</option>';
        });
}

function addRoutingRowFromSelector() {
    var sel = document.getElementById('telefonoSelector');
    if (!sel || sel.value === '') {
        alert('Selecciona primero una línea del desplegable.');
        return;
    }
    var line = _telefonosData[parseInt(sel.value, 10)];
    if (!line) return;

    var tfono = line.tfono.replace(/\D/g, '');
    var last9  = tfono.length >= 9 ? tfono.slice(-9) : tfono;
    var port   = line.waha_port || '';
    var lbl    = (line.nombre || 'linea').toLowerCase().replace(/[^a-z0-9_]/g, '_') + (port ? '_' + port : '');
    var desc   = line.notas || line.nombre || '';

    var tbody = document.querySelector('#routingTable tbody');
    var idx = routingRowCount;
    routingRowCount++;

    var tr = document.createElement('tr');
    tr.className = 'routing-row';
    tr.setAttribute('data-port', escHtml(port));
    tr.setAttribute('data-last9', escHtml(last9));
    tr.innerHTML = [
        '<td><input type="text" name="routing[lines][' + idx + '][last9]" value="' + escHtml(last9) + '" placeholder="Últimos 9 dígitos" class="input-cell"></td>',
        '<td><input type="number" name="routing[lines][' + idx + '][port]" value="' + escHtml(port) + '" placeholder="3000" class="input-cell" style="width:80px"></td>',
        '<td><input type="text" name="routing[lines][' + idx + '][label]" value="' + escHtml(lbl) + '" placeholder="linea_3000" class="input-cell"></td>',
        '<td class="descripcion-cell" title="' + escHtml(desc) + '">' + escHtml(desc) + '</td>',
        '<td><select name="routing[lines][' + idx + '][ai_provider]" class="input-cell" style="width:110px"><option value="openai">OpenAI</option><option value="deepseek">DeepSeek</option></select><input type="hidden" name="routing[lines][' + idx + '][ai_model]" value=""></td>',
        '<td style="text-align:center"><input type="hidden" name="routing[lines][' + idx + '][enabled]" value="0"><input type="checkbox" name="routing[lines][' + idx + '][enabled]" value="1" checked></td>',
        '<td class="waha-status-cell" data-port="' + escHtml(port) + '"><span class="status-dot status-unknown"></span> —</td>',
        '<td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'tr\').remove()">X</button></td>'
    ].join('');
    tbody.appendChild(tr);

    // Reset selector
    sel.value = '';
}

// Load telefonos on page load (only when routing tab is visible or eagerly)
loadTelefonosIntoSelector();

// ── WAHA status checker for routing lines ──
var _wahaStatusTimeout = null;
function loadWahaStatuses() {
    fetch('?action=get_waha_statuses')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok || !data.status) return;
            var statusList = data.status;
            // Build a map by port
            var map = {};
            statusList.forEach(function(s) {
                map[s.port] = s;
            });

            var rows = document.querySelectorAll('#routingTable tbody .routing-row');
            rows.forEach(function(row) {
                var port = row.getAttribute('data-port') || '';
                var cell = row.querySelector('.waha-status-cell');
                if (!cell) return;

                var info = map[port];
                if (!info || info.status === 'error' || info.status === 'unknown') {
                    // Try matching by row index if port not found
                    var idx = Array.from(row.parentNode.children).indexOf(row);
                    if (statusList[idx]) info = statusList[idx];
                }

                if (info) {
                    var dot = cell.querySelector('.status-dot');
                    if (dot) {
                        dot.className = 'status-dot status-' + (info.status || 'unknown');
                    }
                    cell.innerHTML = '<span class="status-dot status-' + (info.status || 'unknown') + '"></span> ' + escHtml(info.status_label || '—');
                }
            });
        })
        .catch(function() {
            // Silently ignore — WAHA may be unreachable
        });
}

// Load WAHA statuses when the routing tab becomes visible
function refreshWahaOnTabSwitch() {
    var routingTab = document.getElementById('tab-routing');
    if (routingTab && routingTab.classList.contains('active')) {
        loadWahaStatuses();
    }
}

// Try loading immediately (tab might already be visible)
if (document.getElementById('tab-routing') && document.getElementById('tab-routing').classList.contains('active')) {
    loadWahaStatuses();
}

// Also refresh when switching to the routing tab
document.querySelectorAll('.tab-nav button[data-tab="tab-routing"]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        setTimeout(loadWahaStatuses, 200); // small delay to let tab render
    });
});

// Refresh every 60 seconds when routing tab is visible
setInterval(function() {
    var routingTab = document.getElementById('tab-routing');
    if (routingTab && routingTab.classList.contains('active')) {
        loadWahaStatuses();
    }
}, 60000);

// ── Conversation modal: consolidated in first script block (uses #conversationModal) ──
</script>

<!-- ==== Learning Tab JS ==== -->
<script>
function forceLearn() {
    var btn = document.getElementById('btn-force-learn');
    var status = document.getElementById('learn-status');
    var output = document.getElementById('learn-output');

    btn.disabled = true; btn.textContent = '⏳ Lanzando...';
    status.textContent = 'Iniciando análisis en segundo plano...'; status.style.color = 'var(--info)';
    output.style.display = 'block'; output.textContent = '';

    // Step 1: Launch learning in background
    fetch('?action=force_learn', { method: 'POST' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                output.textContent = 'Error: ' + (data.error || 'desconocido');
                status.textContent = '❌ Error al iniciar'; status.style.color = 'var(--danger)';
                btn.disabled = false; btn.textContent = '🚀 Forzar aprendizaje';
                return;
            }
            status.textContent = 'Analizando conversaciones con DeepSeek...';
            output.textContent = 'Procesando... (esto puede tardar 30-90 segundos)\n';

            // Step 2: Poll for completion
            var attempts = 0, maxAttempts = 75; // 75 * 2s = 2.5 min max
            function poll() {
                fetch('?action=get_learn_status')
                    .then(function(r) { return r.json(); })
                    .then(function(s) {
                        attempts++;
                        if (s.status === 'running') {
                            status.textContent = 'Analizando... (' + attempts + 's)';
                            output.textContent += '.';
                            if (attempts < maxAttempts) setTimeout(poll, 2000);
                            else {
                                status.textContent = '⏰ Timeout. Pero el análisis sigue en segundo plano. Refresca en 2 min.';
                                status.style.color = 'var(--warn)';
                                btn.disabled = false; btn.textContent = '🚀 Forzar aprendizaje';
                            }
                        } else if (s.status === 'done') {
                            output.textContent = s.output || '(sin output)';
                            if (s.is_error) {
                                status.textContent = '❌ Error en el análisis';
                                status.style.color = 'var(--danger)';
                            } else {
                                status.textContent = '✅ Completado. Refrescando...';
                                status.style.color = 'var(--success)';
                                setTimeout(function() { location.reload(); }, 2000);
                            }
                            btn.disabled = false; btn.textContent = '🚀 Forzar aprendizaje';
                        } else {
                            // idle — shouldn't happen, retry
                            status.textContent = 'Esperando...';
                            if (attempts < 5) setTimeout(poll, 2000);
                            else {
                                status.textContent = '⚠️ No se detectó el proceso. ¿Está el servidor ocupado?';
                                status.style.color = 'var(--warn)';
                                btn.disabled = false; btn.textContent = '🚀 Forzar aprendizaje';
                            }
                        }
                    })
                    .catch(function(err) {
                        status.textContent = '❌ Error de conexión al comprobar estado';
                        status.style.color = 'var(--danger)';
                        btn.disabled = false; btn.textContent = '🚀 Forzar aprendizaje';
                    });
            }
            setTimeout(poll, 3000); // Wait 3s before first poll
        })
        .catch(function(err) {
            output.textContent = 'Error: ' + err.message;
            status.textContent = '❌ Error de conexión'; status.style.color = 'var(--danger)';
            btn.disabled = false; btn.textContent = '🚀 Forzar aprendizaje';
        });
}

function viewPlaybook() {
    var preview = document.getElementById('playbook-preview');
    if (preview.style.display === 'block') { preview.style.display = 'none'; return; }
    preview.style.display = 'block'; preview.textContent = 'Cargando...';
    fetch('?action=get_playbook')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            preview.textContent = data.exists ? data.content : '(No hay playbook todavía. Usa "Forzar aprendizaje".)';
        })
        .catch(function(err) { preview.textContent = 'Error: ' + err.message; });
}

function loadOutcomes() {
    var tbody = document.getElementById('outcomes-tbody');
    var countEl = document.getElementById('outcomes-count');
    tbody.innerHTML = '<tr><td colspan="4" style="padding:20px;text-align:center;color:var(--text-muted)">Cargando...</td></tr>';
    fetch('?action=get_outcomes')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok || !data.outcomes) return;
            countEl.textContent = data.outcomes.length + ' resultados';
            if (data.outcomes.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="padding:20px;text-align:center;color:var(--text-muted)">Sin datos. Ejecuta el cron de clasificación.</td></tr>';
                return;
            }
            var colors = {lead_probable:'var(--info)',lead_confirmado:'var(--success)',lead_ghosted:'var(--warn)',mareador:'#f59e0b',hostil:'var(--danger)',muerta:'var(--text-muted)'};
            tbody.innerHTML = data.outcomes.map(function(o) {
                var label = o.human_confirmed ? (o.outcome + ' ✓') : o.outcome;
                var color = colors[o.outcome] || 'var(--text-muted)';
                var btns = '';
                if (o.outcome === 'lead_probable' || o.outcome === 'lead_detectado') {
                    btns = '<button class="btn btn-sm btn-success" style="margin:2px" onclick="confirmOutcome(\''+o.thread_id+'\',\'lead_confirmado\')">✅ Vino</button>' +
                           '<button class="btn btn-sm btn-danger" style="margin:2px" onclick="confirmOutcome(\''+o.thread_id+'\',\'lead_ghosted\')">❌ No vino</button>';
                }
                return '<tr style="border-bottom:1px solid var(--border)">' +
                    '<td style="padding:8px;font-family:monospace;font-size:.75rem">' + (o.phone||'?') + '</td>' +
                    '<td style="padding:8px"><span style="color:'+color+';font-weight:600">'+label+'</span></td>' +
                    '<td style="padding:8px">' + (o.message_count||0) + '</td>' +
                    '<td style="padding:8px">' + btns + '</td></tr>';
            }).join('');
        });
}

function confirmOutcome(threadId, newOutcome) {
    fetch('?action=confirm_outcome', {
        method: 'POST',
        body: JSON.stringify({thread_id: threadId, outcome: newOutcome})
    }).then(function(r) { return r.json(); }).then(function(d) { if (d.ok) loadOutcomes(); });
}

(function() {
    var btn = document.querySelector('.tab-nav button[data-tab="tab-learning"]');
    if (btn) btn.addEventListener('click', function() { setTimeout(loadOutcomes, 200); });
    if (document.getElementById('tab-learning') && document.getElementById('tab-learning').classList.contains('active')) loadOutcomes();
})();
</script>

</body>
</html>
