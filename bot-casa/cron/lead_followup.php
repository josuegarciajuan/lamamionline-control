<?php

declare(strict_types=1);

/**
 * WhatsApp Lead Follow-Up Cron Script
 *
 * Reads its configuration from php-bot's Config (config.dist.json + config.local.json).
 * Runs every 6 hours via cron. Reads eligible leads from an NDJSON file,
 * sends humanized WhatsApp follow-up messages with photos of available girls,
 * and logs all activity.
 *
 * Usage: php /root/wasapbot/php-bot/cron/lead_followup.php
 * Stdout is piped to: /var/log/wasapbot_followups.log
 *
 * Requirements: PHP 8.1+, curl extension, json extension
 */

// ─────────────────────────────────────────────────────────────────────────────
// Bootstrap: load Config from php-bot (standalone — no Composer autoload)
// Supports multi-user override via cron_runner.php global
// ─────────────────────────────────────────────────────────────────────────────

$phpBotRoot = dirname(__DIR__);                                 // php-bot root
require_once $phpBotRoot . '/src/Core/ConfigInterface.php';
require_once $phpBotRoot . '/src/Core/Config.php';
require_once $phpBotRoot . '/src/BotInterface.php';
require_once $phpBotRoot . '/src/Bot.php';

// Multi-user support: if cron_runner.php set a global config, use it
if (isset($GLOBALS['_cron_runner_config']) && $GLOBALS['_cron_runner_config'] instanceof \WasapBot\Core\Config) {
    $config = $GLOBALS['_cron_runner_config'];
} else {
    $config = new \WasapBot\Core\Config($phpBotRoot);
}

/**
 * Shorthand for $config->get() — accesses the global $config instance.
 */
function _cfg(string $key, mixed $default = null): mixed
{
    global $config;
    return $config->get($key, $default);
}

/**
 * Resolve a file path from config with path traversal protection.
 * Relative paths are resolved against $phpBotRoot; absolute paths are returned as-is.
 * Throws on path traversal attempts.
 */
function _cfg_path(string $key, string $default = ''): string
{
    $path = _cfg($key, $default);
    if ($path === '' || str_starts_with($path, '/')) {
        return $path;
    }
    global $phpBotRoot;
    $resolved = realpath($phpBotRoot);
    if ($resolved === false) {
        throw new \RuntimeException("Cannot resolve phpBotRoot: {$phpBotRoot}");
    }
    $fullPath = $resolved . '/' . $path;
    // Normalize to prevent ../ traversal
    $normalized = realpath($fullPath);
    if ($normalized === false) {
        // File may not exist yet — validate parent directory
        $parent = realpath(dirname($fullPath));
        if ($parent === false || !str_starts_with($parent, $resolved)) {
            throw new \RuntimeException("Path traversal blocked for key '{$key}': {$path}");
        }
        return $fullPath;
    }
    if (!str_starts_with($normalized, $resolved)) {
        throw new \RuntimeException("Path traversal blocked for key '{$key}': {$path}");
    }
    return $normalized;
}

// ─────────────────────────────────────────────────────────────────────────────
// Timezone — set globally so date/time functions behave correctly
// ─────────────────────────────────────────────────────────────────────────────

$tz = _cfg('cron.timezone', 'Europe/Madrid');
date_default_timezone_set($tz);

// ─────────────────────────────────────────────────────────────────────────────
// Run as CLI script only (skip if included/required by another script)
// ─────────────────────────────────────────────────────────────────────────────

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    main();
}

function main(): void
{
    // ── Check if cron is enabled ────────────────────────────────────────
    if (!(bool) _cfg('cron.followup.enabled', true)) {
        logMessage('INFO', 'cron followup disabled via config (cron.followup.enabled=false), exiting');
        return;
    }

    $lockHandle = acquireLock();
    if ($lockHandle === null) {
        return; // Another instance is running; exit silently
    }

    $startTime = time();

    try {
        logMessage('START', 'checking leads for follow-up');

        // ── Check time window ──────────────────────────────────────────
        if (!isWithinSendWindow()) {
            $now = now();
            logMessage('INFO', "outside time window ({$now}), exiting");
            releaseLock($lockHandle);
            return;
        }

        // ── Fetch active girls ──────────────────────────────────────────
        $girls = fetchActiveGirls();
        $activeGirlCount = count($girls);
        if ($activeGirlCount === 0) {
            logMessage('INFO', 'no active girls available, exiting');
            releaseLock($lockHandle);
            return;
        }

        // ── Get phones already sent today ───────────────────────────────
        $alreadySentToday = getTodaySentPhones();

        // ── Read and filter eligible leads ──────────────────────────────
        $maxLeads = (int) _cfg('cron.followup.max_leads_per_run', 10);
        $eligibleLeads = getEligibleLeads($alreadySentToday, $maxLeads);
        $eligibleCount = count($eligibleLeads);

        logMessage('INFO', "{$eligibleCount} leads eligible, {$activeGirlCount} active girls, time window OK");

        if ($eligibleCount === 0) {
            logMessage('DONE', "processed 0 leads, total time: 0 min");
            releaseLock($lockHandle);
            return;
        }

        // ── Send follow-ups ─────────────────────────────────────────────
        $introVariants  = _cfg('cron.followup.intro_variants', []);
        $closingVariants = _cfg('cron.followup.closing_variants', []);

        $sentLeads    = [];
        $lastIntroIdx = -1;
        $lastCloseIdx = -1;

        foreach ($eligibleLeads as $idx => $lead) {
            // Pick a different intro variant from the previous lead
            $introIdx = pickRandomIndex(count($introVariants), $lastIntroIdx);
            $lastIntroIdx = $introIdx;

            // Pick closing variant (track to avoid consecutive repeats)
            $closeIdx = pickRandomIndex(count($closingVariants), $lastCloseIdx);
            $lastCloseIdx = $closeIdx;

            $sentGirls = sendFollowupToLead($lead, $girls, $introIdx, $closeIdx, $introVariants, $closingVariants);

            if ($sentGirls !== null) {
                $sentLeads[] = [
                    'lead'      => $lead,
                    'girls'     => $sentGirls,
                    'introIdx'  => $introIdx,
                    'closeIdx'  => $closeIdx,
                ];

                // Log the follow-up
                logFollowup($lead, $sentGirls, $introIdx, $closeIdx);

                // Update the lead's last_followup_ts in the leads file
                updateLeadFollowupTimestamp($lead['phone'], $lead['ts'] ?? null);

                logMessage('OK', "sent to {$lead['phone']} - girls: [" . implode(', ', $sentGirls) . "]");
            }

            // Wait between leads (skip for the last one)
            if ($idx < $eligibleCount - 1) {
                $waitMinSec = (int) _cfg('cron.followup.inter_lead_wait_min_sec', 60);
                $waitMaxSec = (int) _cfg('cron.followup.inter_lead_wait_max_sec', 180);
                $waitSeconds = random_int($waitMinSec, $waitMaxSec);
                logMessage('WAIT', "{$waitSeconds}s until next lead...");
                usleep($waitSeconds * 1_000_000);
            }
        }

        $totalTime = round((time() - $startTime) / 60, 1);
        $sentCount = count($sentLeads);
        logMessage('DONE', "processed {$sentCount} leads, total time: {$totalTime} min");

    } catch (\Throwable $e) {
        logMessage('ERROR', $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    } finally {
        releaseLock($lockHandle);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Locking
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Acquire an exclusive lock to prevent concurrent executions.
 *
 * @return resource|null File handle if lock acquired, null otherwise.
 */
function acquireLock(): mixed
{
    $lockFile = _cfg_path('cron.followup.lock_file');
    $dir = dirname($lockFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $handle = @fopen($lockFile, 'c');
    if ($handle === false) {
        logMessage('ERROR', "cannot open lock file: " . $lockFile);
        return null;
    }

    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        // Another instance is already running
        fclose($handle);
        return null;
    }

    // Write PID for debugging
    ftruncate($handle, 0);
    fwrite($handle, (string) getmypid());
    fflush($handle);

    return $handle;
}

/**
 * Release the lock and close the file handle.
 *
 * @param resource|null $handle The lock file handle.
 */
function releaseLock(mixed $handle): void
{
    if ($handle === null || !is_resource($handle)) {
        return;
    }

    flock($handle, LOCK_UN);
    fclose($handle);
}

// ─────────────────────────────────────────────────────────────────────────────
// Time Window
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Check if current time is within the allowed sending window.
 */
function isWithinSendWindow(): bool
{
    $tz    = new DateTimeZone(_cfg('cron.timezone', 'Europe/Madrid'));
    $now   = new DateTime('now', $tz);

    $start = new DateTime(_cfg('cron.followup.send_window_start', '10:00'), $tz);
    $end   = new DateTime(_cfg('cron.followup.send_window_end', '22:00'), $tz);

    return $now >= $start && $now <= $end;
}

/**
 * Get the current time formatted for the configured timezone.
 */
function now(): string
{
    $tz  = new DateTimeZone(_cfg('cron.timezone', 'Europe/Madrid'));
    $now = new DateTime('now', $tz);
    return $now->format('H:i');
}

/**
 * Get today's date string in the configured timezone (Y-m-d format).
 */
function todaySpain(): string
{
    $tz  = new DateTimeZone(_cfg('cron.timezone', 'Europe/Madrid'));
    $now = new DateTime('now', $tz);
    return $now->format('Y-m-d');
}

// ─────────────────────────────────────────────────────────────────────────────
// Girls Fetching
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Fetch active girls from the remote API, with caching.
 * Falls back to cached data on fetch failure.
 *
 * @return array<int, array<string, mixed>> List of active girls.
 */
function fetchActiveGirls(): array
{
    // Try to fetch from API
    $raw = fetchGirlsFromApi();

    if ($raw !== null) {
        // Cache the successful response
        cacheGirls($raw);
    } else {
        // Fetch failed — try cache
        logMessage('INFO', 'girls API fetch failed, trying cache');
        $raw = getCachedGirls();
        if ($raw === null) {
            logMessage('ERROR', 'no cached girls data available, cannot proceed');
            return [];
        }
    }

    return parseActiveGirls($raw);
}

/**
 * Fetch girls.json from the remote API.
 *
 * @return string|null Raw JSON string or null on failure.
 */
function fetchGirlsFromApi(): ?string
{
    $url = _cfg('urls.girls_json');
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int) _cfg('cron.followup.curl_timeout_sec', 20),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'User-Agent: WasapBot-FollowUp/1.0',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        logMessage('ERROR', "girls API fetch failed: HTTP {$httpCode} — {$error}");
        return null;
    }

    return $response;
}

/**
 * Cache the girls API response to disk.
 */
function cacheGirls(string $raw): void
{
    $cacheFile = _cfg_path('cron.followup.girls_cache_file');
    $cacheDir  = dirname($cacheFile);
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0777, true);
    }

    $data = json_encode([
        'cached_at' => time(),
        'data'      => $raw,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($data !== false) {
        file_put_contents($cacheFile, $data, LOCK_EX);
    }
}

/**
 * Get cached girls data if within TTL.
 *
 * @return string|null Raw JSON or null if cache expired/missing.
 */
function getCachedGirls(): ?string
{
    $cacheFile = _cfg_path('cron.followup.girls_cache_file');
    if (!file_exists($cacheFile)) {
        return null;
    }

    $content = file_get_contents($cacheFile);
    if ($content === false) {
        return null;
    }

    try {
        $cache = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        return null;
    }

    if (!is_array($cache) || !isset($cache['cached_at'], $cache['data'])) {
        return null;
    }

    $ttl = (int) _cfg('cron.followup.girls_cache_ttl_sec', 3600);
    $age = time() - (int) $cache['cached_at'];
    if ($age > $ttl) {
        logMessage('INFO', "girls cache expired ({$age}s old)");
        return null;
    }

    logMessage('INFO', "using girls cache ({$age}s old)");
    return $cache['data'];
}

/**
 * Parse the girls API response and return only active girls.
 *
 * @return array<int, array{nombre: string, fotos: list<string>}>
 */
function parseActiveGirls(string $raw): array
{
    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        logMessage('ERROR', 'failed to parse girls JSON: ' . $e->getMessage());
        return [];
    }

    if (!is_array($data) || !isset($data['girls']) || !is_array($data['girls'])) {
        logMessage('ERROR', 'girls JSON missing "girls" array');
        return [];
    }

    $active = [];
    foreach ($data['girls'] as $girl) {
        if (!is_array($girl)) {
            continue;
        }

        // Skip inactive girls
        if (!($girl['activa'] ?? false)) {
            continue;
        }

        // Require name and at least one photo
        $name  = $girl['nombre'] ?? null;
        $fotos = $girl['fotos'] ?? [];

        if ($name === null || $name === '' || !is_array($fotos) || count($fotos) === 0) {
            continue;
        }

        // Pick one random photo
        $randomPhoto = $fotos[array_rand($fotos)];

        $active[] = [
            'nombre' => (string) $name,
            'photo'  => (string) $randomPhoto,
        ];
    }

    return $active;
}

// ─────────────────────────────────────────────────────────────────────────────
// Lead Processing
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Get the set of phone numbers that already received a follow-up today.
 *
 * @return array<string, true> Phone numbers as keys.
 */
function getTodaySentPhones(): array
{
    $today = todaySpain();
    $sent  = [];

    $followupsLogFile = _cfg_path('cron.followup.followups_log_file');
    if (!file_exists($followupsLogFile)) {
        return $sent;
    }

    $lines = file($followupsLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return $sent;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        try {
            $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            continue;
        }

        if (!is_array($entry) || !isset($entry['ts_sent'], $entry['phone'])) {
            continue;
        }

        $entryDate = substr((string) $entry['ts_sent'], 0, 10);
        if ($entryDate === $today) {
            $sent[(string) $entry['phone']] = true;
        }
    }

    return $sent;
}

/**
 * Read and filter eligible leads from the NDJSON file.
 *
 * Eligibility criteria:
 * - Has non-empty waha_port and waha_base_url
 * - last_followup_ts is null OR older than the configured min interval (randomized)
 * - Not already sent to today
 *
 * @param array<string, true> $alreadySentToday Phones already contacted today.
 * @param int                  $maxLeads        Maximum number of leads to return.
 * @return list<array<string, mixed>> Eligible leads.
 */
function getEligibleLeads(array $alreadySentToday, int $maxLeads): array
{
    $leadsFile = _cfg_path('cron.followup.leads_file');
    if (!file_exists($leadsFile)) {
        logMessage('ERROR', "leads file not found: " . $leadsFile);
        return [];
    }

    // Locked read to prevent torn/corrupt data during concurrent n8n writes
    $fp = @fopen($leadsFile, 'r');
    if ($fp === false) {
        return [];
    }
    if (!flock($fp, LOCK_SH)) {
        fclose($fp);
        return [];
    }
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    if ($raw === false || $raw === '') {
        return [];
    }

    $records  = parseLeadsFile($raw);
    $eligible = [];
    $now      = time();

    foreach ($records as $record) {
        if (count($eligible) >= $maxLeads) {
            break;
        }

        if (!is_array($record)) {
            continue;
        }

        // ── Must have WAHA config ──────────────────────────────────
        $phone       = (string) ($record['phone'] ?? '');
        $wahaBaseUrl = (string) ($record['waha_base_url'] ?? '');
        $wahaPort    = $record['waha_port'] ?? null;
        $lineLabel   = (string) ($record['line_label'] ?? '');

        if ($phone === '' || $wahaBaseUrl === '' || $wahaPort === null) {
            continue;
        }

        // ── Skip leads marked as "arrived" (cliente ya fue) ─────────
        if (!empty($record['arrived'])) {
            continue;
        }

        // ── Must not have been sent to today ───────────────────────
        if (isset($alreadySentToday[$phone])) {
            logMessage('SKIP', "{$phone} already had followup today");
            continue;
        }

        // ── Check last_followup_ts ─────────────────────────────────
        $lastFollowupTs = $record['last_followup_ts'] ?? null;

        if ($lastFollowupTs !== null && $lastFollowupTs !== '' && $lastFollowupTs !== 'null') {
            $lastTs = strtotime((string) $lastFollowupTs);
            if ($lastTs === false) {
                // Unparseable timestamp — treat as eligible (never contacted)
            } else {
                $minHoursMin = (int) _cfg('cron.followup.min_interval_hours_min', 48);
                $minHoursMax = (int) _cfg('cron.followup.min_interval_hours_max', 72);
                $minInterval = random_int($minHoursMin * 3600, $minHoursMax * 3600);
                if (($now - $lastTs) < $minInterval) {
                    continue; // Too soon for another follow-up
                }
            }
        }

        $eligible[] = $record;
    }

    return $eligible;
}

/**
 * Parse the leads file, handling both:
 * 1. Standard NDJSON (proper JSON, one per line)
 * 2. Legacy `n`-separated format with unquoted keys
 *
 * @param string $raw Raw file contents.
 * @return list<array<string, mixed>> Parsed lead records.
 */
function parseLeadsFile(string $raw): array
{
    $raw = ltrim($raw, "\n\r");

    // Detect format: if the first non-whitespace char after '{' is '"', it's proper JSON
    $firstBracePos = strpos($raw, '{');
    if ($firstBracePos !== false && isset($raw[$firstBracePos + 1]) && $raw[$firstBracePos + 1] === '"') {
        return parseNdjson($raw);
    }

    // Legacy format: split on 'n' character between records
    return parseLegacyLeads($raw);
}

/**
 * Parse standard NDJSON format (proper JSON, one per line).
 *
 * @return list<array<string, mixed>>
 */
function parseNdjson(string $raw): array
{
    $records = [];
    $lines   = explode("\n", $raw);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        try {
            $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            continue;
        }

        if (is_array($record)) {
            $records[] = $record;
        }
    }

    return $records;
}

/**
 * Parse legacy `n`-separated format with unquoted JSON keys.
 * Format: {key:value,key2:value2}n{key:value,...}n
 *
 * @return list<array<string, mixed>>
 */
function parseLegacyLeads(string $raw): array
{
    $records = [];

    // Strip trailing 'n' if present (the legacy format ends with }n)
    $raw = rtrim($raw, "n");

    // Split on 'n' character that separates } and { between records
    $chunks = explode('}n{', $raw);

    foreach ($chunks as $chunk) {
        $chunk = trim($chunk);
        if ($chunk === '') {
            continue;
        }

        // Restore missing braces (pairs may have been split across the '}n{' boundary)
        if (!str_starts_with($chunk, '{')) {
            $chunk = '{' . $chunk;
        }
        if (!str_ends_with($chunk, '}')) {
            $chunk .= '}';
        }

        $record = parseLegacyJsonLine($chunk);
        if ($record !== null) {
            $records[] = $record;
        }
    }

    return $records;
}

/**
 * Parse a single legacy-format JSON-like line with unquoted keys.
 *
 * @param string $line E.g. {ts:2026-02-13T16:16:35+00:00,phone:34654464023,...}
 * @return array<string, mixed>|null
 */
function parseLegacyJsonLine(string $line): ?array
{
    // Remove outer braces
    $inner = trim($line, " \t\n\r\0\x0B{}");
    if ($inner === '') {
        return null;
    }

    $record = [];

    // Split by commas, but be careful with commas inside values
    // Simple approach: split on commas that are not inside colons
    // Since this is a simple key:value format without nested objects/arrays, we can split safely
    $pairs = explode(',', $inner);

    foreach ($pairs as $pair) {
        $pair = trim($pair);
        if ($pair === '' || !str_contains($pair, ':')) {
            continue;
        }

        // Split on first colon only
        $colonPos = strpos($pair, ':');
        if ($colonPos === false) {
            continue;
        }

        $key   = trim(substr($pair, 0, $colonPos));
        $value = trim(substr($pair, $colonPos + 1));

        // Remove surrounding quotes if present
        if (strlen($value) >= 2 && (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        )) {
            $value = substr($value, 1, -1);
        }

        // Convert typed values
        $record[$key] = coerceValue($value);
    }

    return count($record) > 0 ? $record : null;
}

/**
 * Coerce a string value to int, float, bool, null, or keep as string.
 */
function coerceValue(string $value): mixed
{
    if ($value === 'null' || $value === 'NULL') {
        return null;
    }
    if ($value === 'true' || $value === 'TRUE') {
        return true;
    }
    if ($value === 'false' || $value === 'FALSE') {
        return false;
    }
    if (is_numeric($value)) {
        // Preserve integer-like values as int
        if (ctype_digit($value) || (str_starts_with($value, '-') && ctype_digit(substr($value, 1)))) {
            return (int) $value;
        }
        return (float) $value;
    }
    return $value;
}

// ─────────────────────────────────────────────────────────────────────────────
// Follow-Up Sending
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Send a follow-up sequence to a single lead.
 *
 * Sequence: intro → girl photos → closing.
 * Girl order is shuffled. Each message includes typing indicators and delays.
 *
 * @param array<string, mixed> $lead            Lead record.
 * @param array<int, array>    $girls           Active girls with 'nombre' and 'photo'.
 * @param int                  $introIdx        Index into intro variants.
 * @param int                  $closeIdx        Index into closing variants.
 * @param list<string>         $introVariants   Intro message variants from config.
 * @param list<string>         $closingVariants Closing message variants from config.
 * @return list<string>|null Names of girls sent, or null on failure.
 */
function sendFollowupToLead(
    array $lead,
    array $girls,
    int $introIdx,
    int $closeIdx,
    array $introVariants,
    array $closingVariants,
): ?array {
    $phone       = (string) ($lead['phone'] ?? '');
    $wahaBaseUrl = (string) ($lead['waha_base_url'] ?? '');
    $chatId      = $phone . '@c.us';
    $lineLabel   = (string) ($lead['line_label'] ?? 'desconocida');

    if ($phone === '' || $wahaBaseUrl === '') {
        logMessage('ERROR', "invalid lead data for phone: {$phone}");
        return null;
    }

    $introText = $introVariants[$introIdx] ?? ($introVariants[0] ?? '');
    $closeText = $closingVariants[$closeIdx] ?? ($closingVariants[0] ?? '');

    // Shuffle girls for variety
    $shuffledGirls = $girls;
    shuffle($shuffledGirls);

    $girlCount = count($shuffledGirls);
    logMessage('SENDING', "lead {$phone} on {$lineLabel} - {$girlCount} girls");

    // ── 1. Send intro message ─────────────────────────────────────
    if (!wahaStartTyping($wahaBaseUrl, $chatId)) {
        logMessage('ERROR', "startTyping failed for {$phone}");
        // Continue anyway — try to send the message
    }

    // Intro typing delay: 2-5 seconds (configurable)
    $introTypingMin = (int) _cfg('cron.followup.intro_typing_min_us', 2_000_000);
    $introTypingMax = (int) _cfg('cron.followup.intro_typing_max_us', 5_000_000);
    usleep(random_int($introTypingMin, $introTypingMax));

    if (!wahaSendText($wahaBaseUrl, $chatId, $introText)) {
        logMessage('ERROR', "failed to send intro to {$phone}");
        wahaStopTyping($wahaBaseUrl, $chatId);
        return null;
    }

    wahaStopTyping($wahaBaseUrl, $chatId);

    // Pause between intro and girl photos: 5-12 seconds (configurable)
    $introPauseMin = (int) _cfg('cron.followup.intro_to_girls_pause_min_us', 5_000_000);
    $introPauseMax = (int) _cfg('cron.followup.intro_to_girls_pause_max_us', 12_000_000);
    usleep(random_int($introPauseMin, $introPauseMax));

    // ── 2. Send each girl's photo ──────────────────────────────────
    $sentGirlNames = [];
    $lastIdx = count($shuffledGirls) - 1;

    foreach ($shuffledGirls as $idx => $girl) {
        wahaStartTyping($wahaBaseUrl, $chatId);

        // Per-girl typing delay: 3-7 seconds (configurable)
        $girlTypingMin = (int) _cfg('cron.followup.per_girl_typing_min_us', 3_000_000);
        $girlTypingMax = (int) _cfg('cron.followup.per_girl_typing_max_us', 7_000_000);
        usleep(random_int($girlTypingMin, $girlTypingMax));

        $message = $girl['nombre'] . "\n" . $girl['photo'];

        if (wahaSendText($wahaBaseUrl, $chatId, $message)) {
            $sentGirlNames[] = $girl['nombre'];
            logMessage('INFO', "sent girl '{$girl['nombre']}' to {$phone}");
        } else {
            logMessage('ERROR', "failed to send girl '{$girl['nombre']}' to {$phone}");
        }

        wahaStopTyping($wahaBaseUrl, $chatId);

        // Wait between girls (skip after the last one)
        if ($idx < $lastIdx) {
            $interGirlMin = (int) _cfg('cron.followup.inter_girl_pause_min_us', 5_000_000);
            $interGirlMax = (int) _cfg('cron.followup.inter_girl_pause_max_us', 15_000_000);
            usleep(random_int($interGirlMin, $interGirlMax));
        }
    }

    if (count($sentGirlNames) === 0) {
        logMessage('ERROR', "no girls successfully sent to {$phone}");
        return null;
    }

    // ── 3. Send closing message ────────────────────────────────────
    wahaStartTyping($wahaBaseUrl, $chatId);

    // Closing typing delay: 2-4 seconds (configurable)
    $closeTypingMin = (int) _cfg('cron.followup.closing_typing_min_us', 2_000_000);
    $closeTypingMax = (int) _cfg('cron.followup.closing_typing_max_us', 4_000_000);
    usleep(random_int($closeTypingMin, $closeTypingMax));

    if (!wahaSendText($wahaBaseUrl, $chatId, $closeText)) {
        logMessage('ERROR', "failed to send closing to {$phone}");
    }

    wahaStopTyping($wahaBaseUrl, $chatId);

    return $sentGirlNames;
}

// ─────────────────────────────────────────────────────────────────────────────
// WAHA API Calls
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Call a WAHA API endpoint.
 *
 * @param string               $baseUrl  Full base URL including port (e.g. http://100.117.92.74:3000)
 * @param string               $endpoint E.g. /api/sendText
 * @param array<string, mixed> $body     JSON body payload.
 * @return array{http_code: int, body: string}|null Response or null on failure.
 */
function wahaApiCall(string $baseUrl, string $endpoint, array $body): ?array
{
    // SSRF protection: validate URL scheme and restrict cURL protocols
    $parts = parse_url($baseUrl);
    if ($parts === false || !isset($parts['scheme']) || !in_array($parts['scheme'], ['http', 'https'], true)) {
        logMessage('ERROR', "WAHA invalid base URL scheme: {$baseUrl}");
        return null;
    }

    $url = rtrim($baseUrl, '/') . $endpoint;

    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }

    $jsonBody = json_encode($body, JSON_UNESCAPED_UNICODE);
    if ($jsonBody === false) {
        curl_close($ch);
        return null;
    }

    $curlTimeout = (int) _cfg('cron.followup.curl_timeout_sec', 20);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $curlTimeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonBody,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT        => (int) _cfg('cron.followup.curl_timeout_sec', 20),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonBody,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . _cfg('waha.api_key'),
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        logMessage('ERROR', "WAHA {$endpoint} failed: HTTP {$httpCode} — {$error}");
        return null;
    }

    return [
        'http_code' => $httpCode,
        'body'      => $response,
    ];
}

/**
 * Send startTyping indicator via WAHA.
 */
function wahaStartTyping(string $baseUrl, string $chatId): bool
{
    $result = wahaApiCall($baseUrl, '/api/startTyping', [
        'session' => 'default',
        'chatId'  => $chatId,
    ]);
    return $result !== null;
}

/**
 * Send a text message via WAHA.
 */
function wahaSendText(string $baseUrl, string $chatId, string $text): bool
{
    $result = wahaApiCall($baseUrl, '/api/sendText', [
        'session' => 'default',
        'chatId'  => $chatId,
        'text'    => $text,
    ]);
    return $result !== null;
}

/**
 * Send stopTyping indicator via WAHA.
 */
function wahaStopTyping(string $baseUrl, string $chatId): bool
{
    $result = wahaApiCall($baseUrl, '/api/stopTyping', [
        'session' => 'default',
        'chatId'  => $chatId,
    ]);
    return $result !== null;
}

// ─────────────────────────────────────────────────────────────────────────────
// Logging
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Log a follow-up entry to the NDJSON log file.
 */
function logFollowup(array $lead, array $sentGirls, int $introIdx, int $closeIdx): void
{
    $tz  = new DateTimeZone(_cfg('cron.timezone', 'Europe/Madrid'));
    $now = new DateTime('now', $tz);

    $entry = [
        'phone'          => (string) ($lead['phone'] ?? ''),
        'ts_sent'        => $now->format('Y-m-d\TH:i:s\Z'),
        'line_label'     => (string) ($lead['line_label'] ?? ''),
        'girls_sent'     => $sentGirls,
        'variant_intro'  => $introIdx,
        'variant_cierre' => $closeIdx,
    ];

    $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        return;
    }

    $followupsLogFile = _cfg_path('cron.followup.followups_log_file');
    $dir = dirname($followupsLogFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    file_put_contents($followupsLogFile, $json . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Update a lead's last_followup_ts in the leads.ndjson file.
 *
 * This reads the entire file, updates the matching lead, and writes back.
 * Only works with the standard NDJSON format (proper JSON).
 *
 * @param string      $phone       Phone number to match.
 * @param string|null $originalTs  Original timestamp for dedup (optional).
 */
function updateLeadFollowupTimestamp(string $phone, ?string $originalTs): void
{
    $leadsFile = _cfg_path('cron.followup.leads_file');
    if (!file_exists($leadsFile)) {
        return;
    }

    // Open with exclusive lock BEFORE reading to prevent TOCTOU race with concurrent n8n writes
    $fp = @fopen($leadsFile, 'c+');
    if ($fp === false) {
        return;
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        return;
    }

    try {
        rewind($fp);
        $raw = stream_get_contents($fp);
        if ($raw === false || $raw === '') {
            return;
        }

        $tz    = new DateTimeZone(_cfg('cron.timezone', 'Europe/Madrid'));
        $now   = new DateTime('now', $tz);
        $newTs = $now->format('Y-m-d\TH:i:s\Z');

        // Detect format
        $firstBracePos = strpos($raw, '{');
        $isNdjson = ($firstBracePos !== false && isset($raw[$firstBracePos + 1]) && $raw[$firstBracePos + 1] === '"');

        $output = $raw;
        $updated = false;

        if ($isNdjson) {
            $lines   = explode("\n", $raw);
            $updated = false;

            foreach ($lines as $i => &$line) {
                $line = trim($line);
                if ($line === '') continue;

                try {
                    $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    continue;
                }

                if (!is_array($record)) continue;

                $recordPhone = (string) ($record['phone'] ?? '');
                $recordTs    = $record['ts'] ?? null;

                if ($recordPhone === $phone && ($originalTs === null || ($recordTs !== null && (string) $recordTs === $originalTs))) {
                    $record['last_followup_ts'] = $newTs;
                    $newJson = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($newJson !== false) {
                        $line = $newJson;
                        $updated = true;
                    }
                    break;
                }
            }
            unset($line);

            if ($updated) {
                $output = implode("\n", array_filter($lines, fn(string $l): bool => $l !== '')) . "\n";
            }
        } else {
            // Legacy format — simple regex-based update
            $pattern = '/\{ts:[^,}]+,phone:' . preg_quote($phone, '/') . ',[^}]*\}/';
            $replacement = function (array $matches) use ($newTs): string {
                $record = $matches[0];
                return rtrim($record, '}') . ',last_followup_ts:' . $newTs . '}';
            };
            $replaced = preg_replace_callback($pattern, $replacement, $raw, 1);
            if ($replaced !== null && $replaced !== $raw) {
                $output = $replaced;
                $updated = true;
            }
        }

        if ($updated) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $output);
            fflush($fp);
        }
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// Utility Functions
// ─────────────────────────────────────────────────────────────────────────────
// Utility Functions
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Pick a random index that differs from the excluded index.
 *
 * @param int $total   Total number of options.
 * @param int $exclude Index to avoid (or -1 for no exclusion).
 * @return int Selected index.
 */
function pickRandomIndex(int $total, int $exclude): int
{
    if ($total <= 1) {
        return 0;
    }

    if ($exclude < 0 || $exclude >= $total) {
        return random_int(0, $total - 1);
    }

    // Pick from all indices except the excluded one
    $available = [];
    for ($i = 0; $i < $total; $i++) {
        if ($i !== $exclude) {
            $available[] = $i;
        }
    }

    return $available[array_rand($available)];
}

/**
 * Log a message to stdout with timestamp.
 *
 * Output is piped to /var/log/wasapbot_followups.log via cron.
 */
function logMessage(string $level, string $message): void
{
    $tz   = new DateTimeZone(_cfg('cron.timezone', 'Europe/Madrid'));
    $now  = new DateTime('now', $tz);
    $date = $now->format('Y-m-d H:i:s');

    // Sanitize log message (remove newlines that could break log format)
    $message = str_replace(["\n", "\r"], ' ', $message);

    printf("[%s] %s: %s\n", $date, $level, $message);
}
