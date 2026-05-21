<?php

declare(strict_types=1);

/**
 * WhatsApp Reminder Cron Script
 *
 * Processes pending WhatsApp reminders from an NDJSON file and sends
 * them via the WAHA API. Designed to run every minute via cron.
 *
 * Usage: php /root/wasapbot/php-bot/cron/reminder.php
 *
 * PHP 8.4+ required. Reads configuration from the php-bot config system.
 */

// ============================================================
// Bootstrap: load configuration from php-bot config system
// ============================================================

$phpBotRoot = dirname(__DIR__);
require_once $phpBotRoot . '/src/Core/ConfigInterface.php';
require_once $phpBotRoot . '/src/Core/Config.php';
$config = new \WasapBot\Core\Config($phpBotRoot);

/**
 * Resolve a config file path safely, preventing path traversal.
 */
function _resolve_path(string $base, string $relative): string
{
    $resolved = realpath($base);
    if ($resolved === false) {
        throw new \RuntimeException("Cannot resolve base: {$base}");
    }
    $fullPath = $resolved . '/' . ltrim($relative, '/');
    $normalized = realpath($fullPath);
    if ($normalized === false) {
        $parent = realpath(dirname($fullPath));
        if ($parent === false || !str_starts_with($parent, $resolved)) {
            throw new \RuntimeException("Path traversal blocked: {$relative}");
        }
        return $fullPath;
    }
    if (!str_starts_with($normalized, $resolved)) {
        throw new \RuntimeException("Path traversal blocked: {$relative}");
    }
    return $normalized;
}

// --- Paths (safely resolved relative to $phpBotRoot) ---
$cfgNdjsonFile         = _resolve_path($phpBotRoot, $config->get('cron.reminder.reminders_file', 'data/reminders_pending.ndjson'));
$cfgLockFile           = _resolve_path($phpBotRoot, $config->get('cron.reminder.lock_file', 'data/locks/reminder.lock'));
$cfgLastMsgFile        = _resolve_path($phpBotRoot, $config->get('cron.reminder.last_msg_file', 'data/locks/reminder_last_msg'));
$cfgCleanupCounterFile = _resolve_path($phpBotRoot, $config->get('cron.reminder.cleanup_counter_file', 'data/locks/reminder_cleanup_counter'));

// --- Runtime values ---
$cfgApiKey            = (string) $config->get('waha.api_key', 'CHANGEME_WAHA_API_KEY');
$cfgMaxPerRun         = (int) $config->get('cron.reminder.max_per_run', 5);
$cfgCurlTimeout       = (int) $config->get('cron.reminder.curl_timeout_sec', 15);
$cfgCleanupInterval   = (int) $config->get('cron.reminder.cleanup_interval', 5);
$cfgCleanupMaxAge     = (int) $config->get('cron.reminder.cleanup_max_age_sec', 86400);
$cfgSleepBetweenMinUs = (int) $config->get('cron.reminder.sleep_between_min_us', 3000000);
$cfgSleepBetweenMaxUs = (int) $config->get('cron.reminder.sleep_between_max_us', 8000000);
$cfgSleepTypingMinUs  = (int) $config->get('cron.reminder.sleep_typing_min_us', 1000000);
$cfgSleepTypingMaxUs  = (int) $config->get('cron.reminder.sleep_typing_max_us', 4000000);

/**
 * Spanish reminder message variants.
 */
$cfgMessages = $config->get('cron.reminder.message_variants', []);

// Include guard: prevent accidental execution when included by other scripts
if (PHP_SAPI !== 'cli') {
    return;
}

// ── Check if cron is enabled ──────────────────────────────────────────
if (!(bool) $config->get('cron.reminder.enabled', true)) {
    fprintf(STDOUT, "[%s] cron reminder disabled via config (cron.reminder.enabled=false), exiting\n", date('Y-m-d H:i:s'));
    exit(0);
}

// ============================================================
// Helper Functions
// ============================================================

/**
 * Write a timestamped log message to stdout.
 */
function log_msg(string $message): void
{
    $date = date('Y-m-d H:i:s');
    fprintf(STDOUT, "[%s] %s\n", $date, $message);
}

/**
 * Acquire the process-level lock file to prevent concurrent executions.
 *
 * @return resource|false The open file handle, or false if another instance is running.
 */
function acquire_process_lock(): mixed
{
    global $cfgLockFile;

    $dir = dirname($cfgLockFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $fp = @fopen($cfgLockFile, 'cb+');
    if ($fp === false) {
        log_msg("ERROR: cannot open lock file: " . $cfgLockFile);
        return false;
    }

    if (!flock($fp, LOCK_EX | LOCK_NB)) {
        fclose($fp);
        return false; // Another instance is running
    }

    // Write PID for debugging
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, (string) getmypid());
    fflush($fp);

    return $fp;
}

/**
 * Release the process-level lock.
 */
function release_process_lock(mixed $fp): void
{
    global $cfgLockFile;

    if (!is_resource($fp)) {
        return;
    }

    flock($fp, LOCK_UN);
    fclose($fp);
    @unlink($cfgLockFile);
}

/**
 * Parse a single line of NDJSON and normalize its fields.
 *
 * @return array<string, mixed>|null The parsed entry, or null on failure.
 */
function parse_ndjson_line(string $line, int $lineNum): ?array
{
    $line = trim($line);
    if ($line === '') {
        return null;
    }

    try {
        $data = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException $e) {
        log_msg("WARNING: invalid JSON at line {$lineNum}: " . $e->getMessage());
        return null;
    }

    if (!is_array($data)) {
        log_msg("WARNING: non-object JSON at line {$lineNum}, skipping");
        return null;
    }

    // Normalize: cast string boolean to actual boolean
    if (isset($data['sent']) && is_string($data['sent'])) {
        $data['sent'] = ($data['sent'] === 'true' || $data['sent'] === '1');
    }

    // Ensure required fields exist with sensible defaults
    $data['sent']          = (bool) ($data['sent'] ?? false);
    $data['phone']         = (string) ($data['phone'] ?? '');
    $data['chat_id']       = (string) ($data['chat_id'] ?? '');
    $data['eta_minutes']   = (int) ($data['eta_minutes'] ?? 0);
    $data['line_label']    = (string) ($data['line_label'] ?? 'unknown');
    $data['waha_port']     = (int) ($data['waha_port'] ?? 0);
    $data['waha_session']  = (string) ($data['waha_session'] ?? 'default');
    $data['waha_base_url'] = (string) ($data['waha_base_url'] ?? '');
    $data['thread_id']     = (string) ($data['thread_id'] ?? '');
    $data['ts_created']    = (string) ($data['ts_created'] ?? '');

    return $data;
}

/**
 * Read all reminders from the NDJSON file.
 *
 * Uses LOCK_SH to allow concurrent reads while blocking writes.
 * Returns an indexed array keyed by thread_id for efficient merging.
 *
 * @param resource $fp    Open file handle (must already be locked by caller).
 * @param bool     $keyed If true, return array keyed by thread_id instead of numeric.
 *
 * @return array<string, array<string, mixed>>
 */
function read_reminders_from_handle($fp, bool $keyed = false): array
{
    rewind($fp);

    $reminders = [];
    $lineNum   = 0;

    while (($line = fgets($fp)) !== false) {
        $lineNum++;
        $data = parse_ndjson_line($line, $lineNum);
        if ($data === null) {
            continue;
        }
        if ($keyed && $data['thread_id'] !== '') {
            $reminders[$data['thread_id']] = $data;
        } else {
            $reminders[] = $data;
        }
    }

    return $reminders;
}

/**
 * Open the NDJSON file and acquire an exclusive lock, returning the file handle.
 *
 * @return resource|false File handle on success, false on failure.
 */
function open_ndjson_exclusive(): mixed
{
    global $cfgNdjsonFile;

    $dir = dirname($cfgNdjsonFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $fp = @fopen($cfgNdjsonFile, 'cb+');
    if ($fp === false) {
        log_msg("ERROR: cannot open NDJSON file: " . $cfgNdjsonFile);
        return false;
    }

    if (!flock($fp, LOCK_EX)) {
        log_msg("ERROR: cannot acquire exclusive lock on NDJSON file");
        fclose($fp);
        return false;
    }

    return $fp;
}

/**
 * Write all reminders back to the NDJSON file (caller must hold LOCK_EX).
 *
 * @param resource                        $fp        Open file handle with exclusive lock.
 * @param array<int|string, array<string, mixed>> $reminders
 */
function write_reminders_to_handle($fp, array $reminders): void
{
    ftruncate($fp, 0);
    rewind($fp);

    foreach ($reminders as $entry) {
        try {
            $line = json_encode($entry, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
            fwrite($fp, $line);
        } catch (\JsonException $e) {
            log_msg("ERROR: cannot encode reminder entry: " . $e->getMessage());
        }
    }

    fflush($fp);
}

/**
 * Merge sent-status updates into the NDJSON file under an exclusive lock.
 *
 * This is a safe merge-back: it re-reads the file under lock to capture any
 * entries that n8n may have appended since our initial read, applies our
 * sent=true changes to matching thread_ids, and writes the merged result.
 *
 * @param array<string, bool> $sentMap Thread ID => true for entries we marked as sent.
 */
function persist_sent_updates(array $sentMap): void
{
    if ($sentMap === []) {
        return;
    }

    $fp = open_ndjson_exclusive();
    if ($fp === false) {
        log_msg("ERROR: cannot persist sent updates — file open failed");
        return;
    }

    try {
        // Re-read current file state (catches n8n additions)
        $all = read_reminders_from_handle($fp, keyed: false);

        // Apply our changes: mark matching entries as sent
        foreach ($all as &$entry) {
            $tid = $entry['thread_id'] ?? '';
            if ($tid !== '' && isset($sentMap[$tid])) {
                $entry['sent'] = true;
            }
        }
        unset($entry);

        write_reminders_to_handle($fp, $all);
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/**
 * Make a single WAHA API request via cURL.
 *
 * All requests use header: x-api-key, content-type: application/json.
 * Returns true on HTTP 2xx response, false otherwise.
 *
 * @param string $baseUrl  WAHA base URL (e.g. http://100.117.92.74:3000)
 * @param string $endpoint API endpoint path (e.g. "sendText", "startTyping")
 * @param array<string, mixed> $body  JSON body payload
 */
function waha_request(string $baseUrl, string $endpoint, array $body): bool
{
    global $cfgApiKey, $cfgCurlTimeout;

    // SSRF protection: validate URL scheme
    $parts = parse_url($baseUrl);
    if ($parts === false || !isset($parts['scheme']) || !in_array($parts['scheme'], ['http', 'https'], true)) {
        log_msg("ERROR: WAHA invalid base URL scheme: {$baseUrl}");
        return false;
    }

    $url = rtrim($baseUrl, '/') . '/api/' . ltrim($endpoint, '/');

    $ch = curl_init();

    if ($ch === false) {
        log_msg("ERROR: curl_init() failed");
        return false;
    }

    try {
        $jsonBody = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (\JsonException $e) {
        log_msg("ERROR: cannot encode WAHA request body: " . $e->getMessage());
        curl_close($ch);
        return false;
    }

    $headers = [
        'Content-Type: application/json',
        'x-api-key: ' . $cfgApiKey,
    ];

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jsonBody,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $cfgCurlTimeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_FAILONERROR    => false, // We check HTTP code manually
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);

    $response   = curl_exec($ch);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError  = curl_error($ch);
    $curlErrno  = curl_errno($ch);

    curl_close($ch);

    if ($curlErrno !== 0) {
        log_msg("ERROR: WAHA {$endpoint} cURL error [{$curlErrno}]: {$curlError} — URL: {$url}");
        return false;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $truncated = is_string($response) ? mb_substr($response, 0, 200) : '(no body)';
        log_msg("ERROR: WAHA {$endpoint} returned HTTP {$httpCode}: {$truncated} — URL: {$url}");
        return false;
    }

    return true;
}

/**
 * Select a random message from the pool, avoiding the last-used variant.
 *
 * Tracks the last-used message text across executions via a small marker file.
 * If the last-used file is missing, invalid, or no other message is available,
 * any message from the pool may be returned.
 */
function select_message(): string
{
    global $cfgMessages, $cfgLastMsgFile;

    $count = count($cfgMessages);
    if ($count === 0) {
        return '';
    }

    // Read last-used message from marker file
    $lastUsed = '';
    if (file_exists($cfgLastMsgFile)) {
        $content = @file_get_contents($cfgLastMsgFile);
        if (is_string($content)) {
            $lastUsed = trim($content);
        }
    }

    // Build list of indices whose message differs from last-used
    $available = [];
    foreach ($cfgMessages as $i => $msg) {
        if ($msg !== $lastUsed) {
            $available[] = $i;
        }
    }

    // If all messages are the same as last-used (e.g. pool of 1), fall back to any
    if ($available === []) {
        $available = array_keys($cfgMessages);
    }

    $index = $available[array_rand($available)];

    // Persist selected message for next run
    @file_put_contents($cfgLastMsgFile, $cfgMessages[$index], LOCK_EX);

    return $cfgMessages[$index];
}

/**
 * Read, increment, and return the cleanup counter.
 *
 * The counter is stored in a simple text file. When it reaches the cleanup
 * interval, it is reset to 1 instead of incrementing, so the caller can
 * detect when cleanup is due by checking if the old value was at threshold.
 *
 * @return int The counter value *before* incrementation.
 */
function bump_cleanup_counter(): int
{
    global $cfgCleanupCounterFile, $cfgCleanupInterval;

    $current = 0;

    if (file_exists($cfgCleanupCounterFile)) {
        $content = @file_get_contents($cfgCleanupCounterFile);
        if (is_string($content)) {
            $current = (int) trim($content);
        }
    }

    $next = ($current >= $cfgCleanupInterval) ? 1 : $current + 1;

    @file_put_contents($cfgCleanupCounterFile, (string) $next, LOCK_EX);

    return $current;
}

/**
 * Remove sent reminders older than the configured max-age from the NDJSON file.
 *
 * Opens the file under exclusive lock, filters out entries where
 * sent === true and ts_created is older than the configured max-age seconds,
 * then writes the remaining entries back.
 */
function cleanup_old_sent(): void
{
    global $cfgCleanupMaxAge;

    $fp = open_ndjson_exclusive();
    if ($fp === false) {
        log_msg("ERROR: cleanup aborted — cannot open NDJSON file");
        return;
    }

    $now     = time();
    $removed = 0;
    $kept    = 0;

    try {
        $all = read_reminders_from_handle($fp, keyed: false);
        $filtered = [];

        foreach ($all as $entry) {
            $isSent = (bool) ($entry['sent'] ?? false);

            if ($isSent
                && isset($entry['ts_created'])
                && is_string($entry['ts_created'])
                && $entry['ts_created'] !== ''
            ) {
                $createdTs = strtotime($entry['ts_created']);
                if ($createdTs !== false && ($now - $createdTs) > $cfgCleanupMaxAge) {
                    $removed++;
                    continue; // Remove this entry
                }
            }

            $filtered[] = $entry;
            $kept++;
        }

        write_reminders_to_handle($fp, $filtered);

        if ($removed > 0) {
            log_msg("CLEANUP: removed {$removed} old sent reminders, {$kept} entries kept");
        } else {
            log_msg("CLEANUP: no old entries to remove ({$kept} total)");
        }
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/**
 * Determine whether a reminder is due to be sent.
 *
 * A reminder is due if it has not been sent and its ETA has elapsed.
 *
 * @param array<string, mixed> $entry
 * @param int                  $now   Current Unix timestamp.
 */
function is_reminder_due(array $entry, int $now): bool
{
    if (!empty($entry['sent'])) {
        return false;
    }

    $tsCreated  = strtotime((string) ($entry['ts_created'] ?? ''));
    $etaMinutes = (int) ($entry['eta_minutes'] ?? 0);

    if ($tsCreated === false || $etaMinutes <= 0) {
        return false;
    }

    $etaTimestamp = $tsCreated + ($etaMinutes * 60);

    return $etaTimestamp <= $now;
}

// ============================================================
// Main Execution
// ============================================================

/**
 * Entry point.
 */
function main(): void
{
    global $cfgMaxPerRun, $cfgSleepTypingMinUs, $cfgSleepTypingMaxUs,
           $cfgSleepBetweenMinUs, $cfgSleepBetweenMaxUs, $cfgCleanupInterval;

    // 1. Acquire process lock (non-blocking)
    $lockFp = acquire_process_lock();
    if ($lockFp === false) {
        log_msg('SKIP: another instance is running, exiting');
        exit(0);
    }

    try {
        log_msg('START: checking reminders...');

        // 2. Open NDJSON file and read current state under exclusive lock.
        //    We hold the lock briefly just to capture a snapshot, then release
        //    so that n8n is not blocked during our WAHA API calls.
        $fp = open_ndjson_exclusive();
        if ($fp === false) {
            log_msg('DONE: cannot open NDJSON file, processed 0 reminders');
            return;
        }

        $allReminders = read_reminders_from_handle($fp, keyed: false);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($allReminders === []) {
            log_msg('DONE: no reminders in file, processed 0 reminders');
            return;
        }

        // 3. Identify due reminders (max $cfgMaxPerRun)
        $now        = time();
        $dueIndices = [];

        foreach ($allReminders as $i => $entry) {
            if (is_reminder_due($entry, $now)) {
                $dueIndices[] = $i;
            }
        }

        $dueIndices = array_slice($dueIndices, 0, $cfgMaxPerRun);
        $dueCount   = count($dueIndices);
        $sentCount  = 0;
        $sentMap    = []; // thread_id => true for entries we mark as sent

        if ($dueCount > 0) {
            // 4. Process each due reminder (WAHA calls happen here)
            foreach ($dueIndices as $idx => $entryIndex) {
                $entry = &$allReminders[$entryIndex];

                log_msg(sprintf(
                    'SENDING: reminder to %s on %s - eta was %dmin',
                    $entry['phone'],
                    $entry['line_label'],
                    $entry['eta_minutes']
                ));

                $message = select_message();

                // Start typing indicator
                waha_request(
                    (string) $entry['waha_base_url'],
                    'startTyping',
                    [
                        'session' => $entry['waha_session'],
                        'chatId'  => $entry['chat_id'],
                    ]
                );

                // Random typing delay (simulate human typing)
                usleep(random_int($cfgSleepTypingMinUs, $cfgSleepTypingMaxUs));

                // Send the message
                $sendOk = waha_request(
                    (string) $entry['waha_base_url'],
                    'sendText',
                    [
                        'session' => $entry['waha_session'],
                        'chatId'  => $entry['chat_id'],
                        'text'    => $message,
                    ]
                );

                // Stop typing indicator
                waha_request(
                    (string) $entry['waha_base_url'],
                    'stopTyping',
                    [
                        'session' => $entry['waha_session'],
                        'chatId'  => $entry['chat_id'],
                    ]
                );

                // Mark as sent regardless of outcome (requirement #8)
                $entry['sent'] = true;
                $tid = $entry['thread_id'] ?? '';
                if ($tid !== '') {
                    $sentMap[$tid] = true;
                }

                if ($sendOk) {
                    log_msg("OK: reminder sent to {$entry['phone']}");
                } else {
                    log_msg("ERROR: failed to send reminder to {$entry['phone']} — marked as sent to avoid retries");
                }

                $sentCount++;

                // Rate-limit: sleep between reminders (but not after the last one)
                if ($sentCount < $dueCount) {
                    usleep(random_int($cfgSleepBetweenMinUs, $cfgSleepBetweenMaxUs));
                }
            }
            unset($entry); // Break reference

            // 5. Persist sent-status updates via safe merge-back.
            //    Re-reads file under LOCK_EX to catch any n8n additions,
            //    applies our sent=true changes, and writes merged result.
            persist_sent_updates($sentMap);
        }

        // 6. Periodic cleanup of old sent entries
        $counter = bump_cleanup_counter();
        if ($counter >= $cfgCleanupInterval) {
            log_msg("CLEANUP: triggered (counter was {$counter})");
            cleanup_old_sent();
        }

        log_msg("DONE: processed {$sentCount} reminders");

    } finally {
        // 7. Always release the process lock
        release_process_lock($lockFp);
    }
}

// ============================================================
// Run
// ============================================================

main();
