<?php

declare(strict_types=1);

/**
 * Retry Pending Messages — Cron Job
 *
 * Scans session_memory.ndjson for records with _pending=true older than
 * a configurable threshold (default: 30 minutes). These are messages that
 * were written to session memory but never received a bot reply (e.g.,
 * due to a pipeline crash, LLM timeout, or Coalescer orphaned records).
 *
 * For stale pending records: removes the _pending flag so they don't
 * clutter the chat UI or confuse the pipeline on next run.
 *
 * Runs every 15 minutes via cron.
 *
 * Usage: php /root/lamamionline-control/bot-casa/cron/retry_pending.php
 */

// ── Bootstrap ────────────────────────────────────────────────────────────────
$phpBotRoot = dirname(__DIR__);
require_once $phpBotRoot . '/src/Core/ConfigInterface.php';
require_once $phpBotRoot . '/src/Core/Config.php';
$config = new \WasapBot\Core\Config($phpBotRoot);

/**
 * Resolve a config key with optional default.
 */
function _cfg(string $key, mixed $default = null): mixed
{
    global $config;
    return $config->get($key, $default);
}

// ── Configuración ────────────────────────────────────────────────────────────
$sessionMemoryFile = (string) _cfg('files.session_memory',
    'public/data/session_memory.ndjson');
if (!str_starts_with($sessionMemoryFile, '/')) {
    $sessionMemoryFile = $phpBotRoot . '/' . ltrim($sessionMemoryFile, '/');
}

$staleThresholdMin = (int) _cfg('pending.stale_threshold_minutes', 10);
$dryRun = in_array('--dry-run', $argv ?? [], true);

if (!file_exists($sessionMemoryFile)) {
    echo "[retry_pending] Session memory file not found: {$sessionMemoryFile}\n";
    exit(0);
}

// ── Leer todas las líneas ────────────────────────────────────────────────────
$lines = @file($sessionMemoryFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false || $lines === []) {
    echo "[retry_pending] Session memory is empty\n";
    exit(0);
}

$now = time();
$cleaned = 0;
$kept = 0;
$newLines = [];

foreach ($lines as $line) {
    $rec = json_decode($line, true);
    if (!is_array($rec)) {
        $newLines[] = $line; // Keep invalid lines
        $kept++;
        continue;
    }

    $isPending = !empty($rec['_pending']);
    if (!$isPending) {
        $newLines[] = $line;
        $kept++;
        continue;
    }

    // Check if the pending record is stale
    $ts = strtotime((string) ($rec['ts'] ?? ''));
    if ($ts === false || ($now - $ts) > $staleThresholdMin * 60) {
        // Stale: remove _pending flag, keep the record for history
        unset($rec['_pending']);
        $newLines[] = json_encode($rec, JSON_UNESCAPED_UNICODE);
        $cleaned++;
        if (!$dryRun) {
            echo "[retry_pending] CLEANED: phone={$rec['phone']} ts={$rec['ts']} msg="
                . mb_substr((string) ($rec['user_msg'] ?? ''), 0, 50) . "...\n";
        } else {
            echo "[retry_pending] WOULD CLEAN: phone={$rec['phone']} ts={$rec['ts']} msg="
                . mb_substr((string) ($rec['user_msg'] ?? ''), 0, 50) . "...\n";
        }
    } else {
        $newLines[] = $line; // Still fresh, keep as is
        $kept++;
    }
}

// ── Write back ───────────────────────────────────────────────────────────────
if ($cleaned > 0 && !$dryRun) {
    $tempFile = $sessionMemoryFile . '.tmp.' . getmypid();
    $written = @file_put_contents($tempFile, implode("\n", $newLines) . "\n", LOCK_EX);
    if ($written === false) {
        echo "[retry_pending] ERROR: Failed to write temp file\n";
        exit(1);
    }
    if (!@rename($tempFile, $sessionMemoryFile)) {
        echo "[retry_pending] ERROR: Failed to rename temp file\n";
        @unlink($tempFile);
        exit(1);
    }
    // Ensure the file is writable by www-data (web process).
    // The rename creates a new inode owned by whoever runs this cron (root).
    @chmod($sessionMemoryFile, 0664);
    @chown($sessionMemoryFile, 'www-data');
    echo "[retry_pending] Done. Cleaned: {$cleaned}, Kept: {$kept}\n";
} elseif ($cleaned > 0 && $dryRun) {
    echo "[retry_pending] DRY RUN — would clean: {$cleaned}, kept: {$kept}\n";
} else {
    echo "[retry_pending] No stale pending records found. Total records: {$kept}\n";
}
