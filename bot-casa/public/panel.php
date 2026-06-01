<?php

declare(strict_types=1);

/**
 * panel.php — Admin panel for wasapBot (standalone, no login required).
 *
 * Access: GET /panel
 * Actions: ?action=save_config | toggle_bot | delete_memory_thread
 *           | delete_memory_line | clear_memory
 */

// ─────────────────────────────────────────────────────────────────────
//  Bootstrap
// ─────────────────────────────────────────────────────────────────────

define('WASAPBOT_ROOT', dirname(__DIR__));

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
 * Write the bot mode file.
 */
function setBotMode(string $mode): void
{
    global $modeFilePath;
    $dir = dirname($modeFilePath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    @file_put_contents($modeFilePath, $mode, LOCK_EX);
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
//  CSRF protection (time-based token, no sessions needed)
// ─────────────────────────────────────────────────────────────────────

function generateCsrfToken(): string
{
    // Rotates every 8 hours; no storage needed
    $secret = defined('WASAPBOT_ROOT') ? WASAPBOT_ROOT : __DIR__;
    return hash_hmac('sha256', date('Y-m-d-H') . floor((int) date('i') / 10), $secret);
}

function validateCsrfToken(string $token): bool
{
    return hash_equals(generateCsrfToken(), $token);
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
function buildRedirectUrl(string $baseUrl, string $extraParam): string
{
    $allowedTabs = [
        'tab-status', 'tab-descripcion', 'tab-prompt', 'tab-leads',
        'tab-waha', 'tab-openai', 'tab-routing', 'tab-delays',
        'tab-variants', 'tab-followup', 'tab-reminder', 'tab-urls',
        'tab-memory', 'tab-logs',
    ];
    $tab = (string) ($_POST['active_tab'] ?? $_GET['tab'] ?? '');
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
    setBotMode($current === 'start' ? 'stop' : 'start');
    header('Location: ' . buildRedirectUrl($baseUrl, 'toggled=1'));
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
        'openai.tone_temperature', 'human_delays.typing.chunk_pause_factor',
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
 * Render routing lines table rows as HTML.
 *
 * @param list<array<string, mixed>> $lines
 */
function renderRoutingLines(array $lines): string
{
    $html = '';
    foreach ($lines as $idx => $line) {
        $last9   = h((string) ($line['last9'] ?? ''));
        $port    = h((string) ($line['port'] ?? ''));
        $label   = h((string) ($line['label'] ?? ''));
        $enabled = (bool) ($line['enabled'] ?? true);
        $chk     = $enabled ? 'checked' : '';

        $notas  = h((string) ($line['notas'] ?? ''));
        $html .= <<<ROW
        <tr class="routing-row">
            <td><input type="text" name="routing[lines][{$idx}][last9]" value="{$last9}" placeholder="Últimos 9 dígitos" class="input-cell"></td>
            <td><input type="number" name="routing[lines][{$idx}][port]" value="{$port}" placeholder="3000" class="input-cell" style="width:80px"></td>
            <td><input type="text" name="routing[lines][{$idx}][label]" value="{$label}" placeholder="linea_3000" class="input-cell"></td>
            <td style="text-align:center"><input type="hidden" name="routing[lines][{$idx}][enabled]" value="0"><input type="checkbox" name="routing[lines][{$idx}][enabled]" value="1" {$chk}></td>
            <td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button><input type="hidden" name="routing[lines][{$idx}][notas]" value="{$notas}"></td>
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

// ─── Data for the view ───
$memoryLines  = getMemoryDisplayLines($memory);
$memoryGroups = getMemoryGroups($memoryLines);
$routingLines = config_val_array('routing.lines');
$botStats     = getBotStats($config);
$leadsDisplay = getLeadsForDisplay($config);

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
<style>
/* ── Luxe Sapphire + Warm Gold theme ── */
:root {
    --bg:              #080d17;
    --bg-surface:      #0c1522;
    --panel:           #111b2e;
    --panel-hover:     #152036;
    --text:            #f0f3fa;
    --text-muted:      #8b9ec0;
    --accent:          #f59e0b;
    --accent-light:    #fbbf24;
    --accent-dark:     #d97706;
    --accent2:         #f97316;
    --danger:          #f87171;
    --danger-bg:       rgba(248,113,113,.10);
    --ok:              #34d399;
    --ok-bg:           rgba(52,211,153,.10);
    --warn:            #fbbf24;
    --warn-bg:         rgba(251,191,36,.10);
    --info:            #60a5fa;
    --info-bg:         rgba(96,165,250,.10);
    --money:           #34d399;
    --border:          #1c2d4a;
    --border-soft:     #243758;
    --input-bg:        #0c1522;
    --tab-active:      #1a2e4a;
    --shadow-sm:       0 4px 12px rgba(0,0,0,.25);
    --shadow-md:       0 8px 28px rgba(0,0,0,.35);
    --radius-sm:       10px;
    --radius-md:       14px;
    --radius-lg:       18px;
    --font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
@import url('https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap');
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: var(--font);
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    line-height: 1.5;
}
a { color: var(--accent-light); text-decoration: none; }
a:hover { text-decoration: underline; }

/* ── Header ── */
.header {
    background: var(--panel);
    border-bottom: 1px solid var(--border);
    padding: 14px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    border-radius: var(--radius-md) var(--radius-md) 0 0;
}
.header h1 { font-size: 1.3rem; font-weight: 700; color: var(--text-bright, #fff); }
.header .subtitle { color: var(--text-muted); font-size: .85rem; font-weight: 400; }

/* ── Notification ── */
.alert { padding: 10px 18px; margin: 0 20px 10px; border-radius: var(--radius-sm); font-size: .9rem; font-weight: 500; }
.alert-success { background: var(--ok-bg); color: var(--ok); border: 1px solid var(--ok); }
.alert-info { background: var(--info-bg); color: var(--info); border: 1px solid var(--info); }
.alert-warning { background: var(--warn-bg); color: var(--warn); border: 1px solid var(--warn); }

/* ── Tabs ── */
.tab-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 2px;
    padding: 0 20px;
    background: var(--panel);
    border-bottom: 1px solid var(--border);
    overflow-x: auto;
}
.tab-nav button {
    background: transparent;
    border: none;
    color: var(--text-muted);
    padding: 10px 16px;
    cursor: pointer;
    font-size: .85rem;
    font-weight: 500;
    font-family: var(--font);
    white-space: nowrap;
    border-bottom: 2px solid transparent;
    transition: all .2s;
}
.tab-nav button:hover { color: var(--text); }
.tab-nav button.active {
    color: var(--accent);
    border-bottom-color: var(--accent);
    background: var(--tab-active);
}

/* ── Tab content ── */
.tab-content { display: none; padding: 20px; }
.tab-content.active { display: block; }

/* ── Main form container ── */
.main-form { max-width: 1100px; margin: 0 auto; }

/* ── Cards ── */
.card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 20px;
    margin-bottom: 16px;
    box-shadow: var(--shadow-sm);
}
.card h2 {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 14px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
}
.card h3 {
    font-size: .95rem;
    font-weight: 600;
    color: var(--text-muted);
    margin: 14px 0 8px;
}

/* ── Form elements ── */
.form-group { margin-bottom: 12px; }
.form-group label {
    display: block;
    font-size: .82rem;
    color: var(--text-muted);
    margin-bottom: 4px;
    font-weight: 500;
}
.form-row { display: flex; gap: 12px; flex-wrap: wrap; }
.form-row .form-group { flex: 1; min-width: 140px; }

input[type="text"],
input[type="number"],
input[type="password"],
input[type="url"],
select,
textarea {
    width: 100%;
    padding: 8px 10px;
    background: var(--input-bg);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    color: var(--text);
    font-size: .9rem;
    font-family: var(--font);
    transition: border-color .2s;
}
input:focus, textarea:focus, select:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 2px var(--accent-glow, rgba(245,158,11,.22));
}
textarea { resize: vertical; min-height: 60px; }
textarea.code-area { font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace; font-size: .82rem; }
input[type="checkbox"] { width: auto; margin-right: 6px; }
.checkbox-label { display: inline-flex; align-items: center; cursor: pointer; font-size: .85rem; font-weight: 500; }

/* ── Buttons ── */
.btn {
    display: inline-block;
    padding: 8px 18px;
    border: none;
    border-radius: var(--radius-sm);
    cursor: pointer;
    font-size: .88rem;
    font-weight: 600;
    font-family: var(--font);
    transition: all .2s;
    box-shadow: var(--shadow-sm);
}
.btn-primary { background: linear-gradient(135deg, var(--accent), var(--accent-dark)); color: #1a1206; }
.btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(245,158,11,.35); }
.btn-danger { background: var(--danger); color: #fff; }
.btn-danger:hover { background: #ef4444; box-shadow: 0 6px 20px rgba(248,113,113,.35); }
.btn-success { background: linear-gradient(135deg, var(--ok), #10b981); color: #fff; }
.btn-success:hover { box-shadow: 0 6px 20px rgba(52,211,153,.35); }
.btn-warning { background: var(--warn); color: #1a1206; }
.btn-warning:hover { box-shadow: 0 6px 20px rgba(251,191,36,.35); }
.btn-sm { padding: 4px 10px; font-size: .78rem; }
.btn-lg { padding: 12px 28px; font-size: 1rem; }
.btn-block { display: block; width: 100%; text-align: center; }

/* ── Bot status badge ── */
.bot-status {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}
.bot-indicator {
    display: inline-block;
    width: 14px; height: 14px;
    border-radius: 50%;
    margin-right: 6px;
    animation: pulse 2s infinite;
}
.status-on { background: var(--ok); box-shadow: 0 0 12px rgba(52,211,153,.5); }
.status-off { background: var(--danger); box-shadow: 0 0 12px rgba(248,113,113,.5); }
.status-unknown { background: var(--text-muted); }
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .5; }
}
.bot-status-text { font-size: 1.1rem; font-weight: 600; }

/* ── Memory table ── */
.memory-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.memory-table th {
    background: var(--input-bg);
    color: var(--text-muted);
    padding: 8px 10px;
    text-align: left;
    font-weight: 600;
    font-size: .78rem;
    white-space: nowrap;
}
.memory-table td {
    padding: 6px 10px;
    border-bottom: 1px solid var(--border);
    vertical-align: top;
}
.memory-table tr:hover td { background: rgba(245,158,11,.04); }
.memory-table .mono { font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace; font-size: .78rem; }
.memory-table .preview { max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* ── Routing table ── */
.routing-table { width: 100%; border-collapse: collapse; font-size: .85rem; }
.routing-table th {
    background: var(--input-bg);
    color: var(--text-muted);
    padding: 6px 8px;
    text-align: left;
    font-weight: 600;
    font-size: .78rem;
}
.routing-table td { padding: 4px 6px; }
.input-cell { padding: 5px 8px !important; font-size: .82rem !important; width: 100%; }

/* ── Responsive ── */
@media (max-width: 768px) {
    .tab-nav { gap: 0; }
    .tab-nav button { padding: 8px 10px; font-size: .75rem; }
    .header { padding: 10px 14px; }
    .tab-content { padding: 12px; }
    .prompt-layout { flex-direction: column !important; }
    .prompt-edit-col, .prompt-preview-col { flex: 1 1 100% !important; max-width: 100% !important; }
    .prompt-preview-card { position: static !important; max-height: 50vh !important; }
}

/* ── Prompt parametrizado ── */
.prompt-layout { display: flex; gap: 20px; align-items: flex-start; }
.prompt-edit-col { flex: 0 0 60%; min-width: 0; }
.prompt-preview-col { flex: 0 0 40%; min-width: 0; }
.prompt-preview-card { position: sticky; top: 20px; max-height: 92vh; display: flex; flex-direction: column; }
.prompt-preview-box {
    flex: 1;
    overflow-y: auto;
    background: #0a0e17;
    color: #c9d1d9;
    padding: 14px 16px;
    border-radius: var(--radius-sm);
    font-family: 'SF Mono', 'Fira Code', 'Consolas', monospace;
    font-size: .76rem;
    line-height: 1.55;
    white-space: pre-wrap;
    word-break: break-word;
    margin: 0;
    max-height: calc(92vh - 120px);
    border: 1px solid var(--border);
}
.prompt-stats {
    margin-top: 8px;
    font-size: .76rem;
    color: var(--text-muted);
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}
.prompt-stats .stat-ok { color: var(--ok); }
.prompt-stats .stat-warn { color: var(--warn); }
.prompt-chip {
    display: inline-block;
    padding: 3px 8px;
    background: var(--input-bg);
    border: 1px solid var(--border);
    border-radius: 4px;
    font-family: monospace;
    font-size: .78rem;
    color: var(--accent-light);
    cursor: pointer;
    user-select: none;
    transition: all .15s;
}
.prompt-chip:hover { background: var(--accent); color: #1a1206; border-color: var(--accent); }
.prompt-chip-empty { opacity: .45; border-style: dashed; }
.prompt-details {
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    margin-bottom: 6px;
    overflow: hidden;
}
.prompt-details[open] { border-color: var(--accent); }
.prompt-summary {
    padding: 10px 14px;
    cursor: pointer;
    font-size: .84rem;
    font-weight: 600;
    color: var(--text);
    display: flex;
    justify-content: space-between;
    align-items: center;
    user-select: none;
}
.prompt-summary:hover { background: rgba(245,158,11,.06); }
.prompt-summary-badge {
    font-size: .72rem;
    color: var(--text-muted);
    font-weight: 400;
    font-family: monospace;
}
.prompt-section-ta {
    margin: 0;
    border: none;
    border-top: 1px solid var(--border);
    border-radius: 0;
    display: block;
}
</style>
</head>
<body>

<div class="header">
    <div>
        <h1>wasapBot — Panel de Administración</h1>
        <span class="subtitle">PHP <?php echo h(PHP_VERSION); ?> &middot; <?php echo h(date('Y-m-d H:i:s')); ?></span>
    </div>
    <div style="display:flex;gap:8px">
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
    <button type="button" data-tab="tab-openai">OpenAI</button>
    <button type="button" data-tab="tab-routing">Routing</button>
    <button type="button" data-tab="tab-delays">Human Delays</button>
    <button type="button" data-tab="tab-variants">Variantes</button>
    <button type="button" data-tab="tab-followup">Cron Follow-up</button>
    <button type="button" data-tab="tab-reminder">Cron Reminder</button>
    <button type="button" data-tab="tab-urls">URLs</button>
    <button type="button" data-tab="tab-memory">Memoria</button>
    <button type="button" data-tab="tab-logs">Logs</button>
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
                        <button type="button" class="btn btn-sm btn-primary" onclick="openConversationModal(<?php echo json_encode($threadId, JSON_HEX_APOS | JSON_HEX_QUOT); ?>, <?php echo json_encode($phoneDisp, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)">Ver</button>
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

<!-- ===== TAB 5: Configuración OpenAI ===== -->
<div class="tab-content" id="tab-openai">
    <div class="card">
        <h2>Configuración OpenAI</h2>
        <div class="form-group">
            <label>API Key</label>
            <input type="password" name="openai[api_key]" value="<?php echo config_val('openai.api_key'); ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Chat Model</label>
                <input type="text" name="openai[chat_model]" value="<?php echo config_val('openai.chat_model', 'gpt-5.1'); ?>">
            </div>
            <div class="form-group">
                <label>Tone Classifier Model</label>
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
        <div style="margin-top:10px">
            <button type="submit" class="btn btn-primary btn-lg">Guardar OpenAI</button>
        </div>
    </div>
</div>

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
                    <th>Activa</th>
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
    tr.innerHTML = [
        '<td><input type="text" name="routing[lines][' + idx + '][last9]" value="" placeholder="Últimos 9 dígitos" class="input-cell"></td>',
        '<td><input type="number" name="routing[lines][' + idx + '][port]" value="" placeholder="3000" class="input-cell" style="width:80px"></td>',
        '<td><input type="text" name="routing[lines][' + idx + '][label]" value="" placeholder="linea_3000" class="input-cell"></td>',
        '<td style="text-align:center"><input type="hidden" name="routing[lines][' + idx + '][enabled]" value="0"><input type="checkbox" name="routing[lines][' + idx + '][enabled]" value="1" checked></td>',
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

    var tbody = document.querySelector('#routingTable tbody');
    var idx = routingRowCount;
    routingRowCount++;

    var notas  = line.notas || '';
    var tr = document.createElement('tr');
    tr.className = 'routing-row';
    tr.innerHTML = [
        '<td><input type="text" name="routing[lines][' + idx + '][last9]" value="' + escHtml(last9) + '" placeholder="Últimos 9 dígitos" class="input-cell"></td>',
        '<td><input type="number" name="routing[lines][' + idx + '][port]" value="' + escHtml(port) + '" placeholder="3000" class="input-cell" style="width:80px"></td>',
        '<td><input type="text" name="routing[lines][' + idx + '][label]" value="' + escHtml(lbl) + '" placeholder="linea_3000" class="input-cell"></td>',
        '<td style="text-align:center"><input type="hidden" name="routing[lines][' + idx + '][enabled]" value="0"><input type="checkbox" name="routing[lines][' + idx + '][enabled]" value="1" checked></td>',
        '<td><button type="button" class="btn btn-danger btn-sm" onclick="this.closest(\'tr\').remove()">X</button><input type="hidden" name="routing[lines][' + idx + '][notas]" value="' + escHtml(notas) + '"></td>'
    ].join('');
    tbody.appendChild(tr);

    // Reset selector
    sel.value = '';
}

// Load telefonos on page load (only when routing tab is visible or eagerly)
loadTelefonosIntoSelector();

// ── Conversation modal: consolidated in first script block (uses #conversationModal) ──
</script>

</body>
</html>
