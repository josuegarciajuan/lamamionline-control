<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

use WasapBot\Core\ConfigInterface;

/**
 * InflightGate — intercepts messages that arrive while the bot is already
 * processing a previous message from the same phone.
 *
 * When a message arrives and there's an active inflight lock for this phone,
 * the message is queued for the active processor instead of starting a new
 * pipeline. The active processor will pick it up before sending its response.
 *
 * This prevents the "metralleta" problem: rapid-fire messages that arrive
 * during LLM processing or typing simulation are now merged and processed
 * together instead of being handled as separate, desynchronised pipelines.
 *
 * Pattern: placed after MessageExtractor, before DedupGate in the pipeline.
 */
final class InflightGate implements PipelineStageInterface
{
    public function __construct(
        private readonly ConfigInterface $config,
    ) {}

    public function process(array $ctx): ?array
    {
        $fromPhone = (string) ($ctx['from_phone'] ?? '');
        if ($fromPhone === '') {
            return $ctx; // Can't determine phone, let through
        }

        $lockDir = $this->lockDir();
        $lockFile = $lockDir . '/' . $fromPhone . '.lock';

        if (!file_exists($lockFile)) {
            return $ctx; // No active processor — let through
        }

        // Check TTL: if lock is stale (> 60s), remove it and let through
        $lockAge = time() - (int) @filemtime($lockFile);
        if ($lockAge > 60) {
            @unlink($lockFile);
            return $ctx;
        }

        // ── Active processor detected — queue this message ──────────
        $pendingFile = $lockDir . '/' . $fromPhone . '_pending.json';

        $pending = $this->readPending($pendingFile);
        $pending[] = [
            'message_text' => (string) ($ctx['message_text'] ?? ''),
            'ts'           => (string) ($ctx['timestamp'] ?? date('c')),
            'thread_id'    => (string) ($ctx['thread_id'] ?? ''),
        ];

        $this->writePending($pendingFile, $pending);

        // Halt this pipeline — the active processor will handle it
        return null;
    }

    public function name(): string
    {
        return 'InflightGate';
    }

    // ── Public helpers (also used by Bot.php) ───────────────────────

    /**
     * Create an inflight lock for this phone.
     */
    public static function createLock(string $lockDir, string $phone): void
    {
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0777, true);
        }
        $lockFile = $lockDir . '/' . $phone . '.lock';
        @file_put_contents($lockFile, (string) time());
    }

    /**
     * Remove inflight lock and pending file for a phone.
     */
    public static function cleanup(string $lockDir, string $phone): void
    {
        @unlink($lockDir . '/' . $phone . '.lock');
        @unlink($lockDir . '/' . $phone . '_pending.json');
    }

    /**
     * Read and atomically delete pending messages for a phone.
     *
     * @return list<array{message_text: string, ts: string, thread_id: string}>
     */
    public static function drainPending(string $lockDir, string $phone): array
    {
        $pendingFile = $lockDir . '/' . $phone . '_pending.json';
        $pending = self::readPending($pendingFile);
        @unlink($pendingFile);
        return $pending;
    }

    // ── Private ─────────────────────────────────────────────────────

    private function lockDir(): string
    {
        $dir = (string) $this->config->get('files.lock_dir', 'data/locks');
        $root = defined('WASAPBOT_ROOT') ? WASAPBOT_ROOT : dirname(__DIR__, 2);
        if (!str_starts_with($dir, '/')) {
            $dir = $root . '/' . ltrim($dir, '/');
        }
        return $dir . '/inflight';
    }

    /**
     * @return list<array{message_text: string, ts: string, thread_id: string}>
     */
    private static function readPending(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return [];
        }
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return [];
        }
        return is_array($data) ? $data : [];
    }

    /**
     * @param list<array{message_text: string, ts: string, thread_id: string}> $pending
     */
    private static function writePending(string $path, array $pending): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $json = json_encode($pending, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return;
        }
        // Atomically write with flock
        $fp = @fopen($path, 'c');
        if ($fp === false) {
            @file_put_contents($path, $json); // fallback
            return;
        }
        try {
            if (flock($fp, LOCK_EX)) {
                ftruncate($fp, 0);
                fwrite($fp, $json);
                fflush($fp);
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }
    }
}
