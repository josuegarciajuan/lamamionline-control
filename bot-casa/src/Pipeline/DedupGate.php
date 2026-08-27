<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

use WasapBot\Core\ConfigInterface;

/**
 * DedupGate — prevents duplicate message processing using lock files.
 *
 * Each unique message (identified by messageId) writes a .lock file.
 * If the file already exists, the message has been processed before
 * and the pipeline halts (returns null).
 *
 * Pattern: based on "Early Dedup Event" node in bot.json (~lines 1130-1140).
 */
final readonly class DedupGate implements PipelineStageInterface
{
    public function __construct(
        private ConfigInterface $config,
    ) {}

    public function name(): string
    {
        return 'DedupGate';
    }

    /**
     * @param  array<string, mixed> $ctx
     * @return array<string, mixed>|null
     */
    public function process(array $ctx): ?array
    {
        try {
            $dedupDir = (string) $this->config->get('files.event_dedup_dir', 'data/locks/event_dedup');
            $ttlMinutes = (int) $this->config->get('dedup_coalesce.dedup_file_ttl_minutes', 5);

            // Ensure directory exists
            if (!is_dir($dedupDir)) {
                @mkdir($dedupDir, 0755, true);
            }

            $messageId = $this->extractMessageId($ctx);

            if ($messageId === '') {
                // Cannot deduplicate without an ID — let it pass
                $ctx['__dedup_status'] = 'OK_NOKEY';
                return $ctx;
            }

            // Sanitize the key for filesystem
            $safeKey = mb_substr(
                (string) preg_replace('/[^A-Za-z0-9_\-]/', '_', $messageId),
                0,
                80
            );

            $lockFile = $dedupDir . '/' . $safeKey . '.lock';

            // Clean old lock files (background cleanup)
            $this->cleanupOldFiles($dedupDir, $ttlMinutes);

            // Atomic file creation — if file already exists, it's a duplicate
            if (file_exists($lockFile)) {
                $ctx['__dedup_status'] = 'DUP';
                return null;
            }

            // Create the lock file
            if (@file_put_contents($lockFile, (string) time(), LOCK_EX) === false) {
                // Could not create lock — let message pass (fail-open for safety)
                $ctx['__dedup_status'] = 'OK_NOLOCK';
                return $ctx;
            }

            $ctx['__dedup_status'] = 'OK';
            return $ctx;
        } catch (\Throwable) {
            // Never throw — fail-open on unexpected errors
            return $ctx;
        }
    }

    /**
     * Extract a unique message identifier from the context.
     *
     * Tries multiple known field paths used by WAHA payloads.
     */
    /** @param array<string, mixed> $ctx */
    private function extractMessageId(array $ctx): string
    {
        /** @var array<string, mixed>|null $body */
        $body = $ctx['body'] ?? null;

        if (!is_array($body)) {
            return '';
        }

        /** @var array<string, mixed>|null $payload */
        $payload = $body['payload'] ?? null;

        if (is_array($payload)) {
            $id = $payload['id']
                ?? $payload['wamid']
                ?? $payload['messageId']
                ?? null;

            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        // Fallback: build a compound key from available fields
        $from = '';
        $ts = '';
        $text = '';

        if (is_array($payload)) {
            $from = (string) ($payload['from'] ?? $payload['chatId'] ?? '');
            $ts = (string) ($payload['timestamp'] ?? $body['timestamp'] ?? '');
            $text = (string) ($payload['body']
                ?? ($payload['text']['body'] ?? ($payload['message'] ?? '')));
        }

        $compound = 'noid:' . preg_replace('/[^A-Za-z0-9]/', '', $from . '|' . $ts . '|' . $text);

        // Truncate to reasonable key length
        $compound = mb_substr($compound, 0, 80);

        return $compound !== 'noid:' ? $compound : 'noid:' . time();
    }

    /**
     * Remove lock files older than the configured TTL.
     *
     * Uses scatter-gather approach: only cleans ~10% of invocations
     * to avoid stat() storm on every message.
     */
    private function cleanupOldFiles(string $dedupDir, int $ttlMinutes): void
    {
        // Probabilistic cleanup — avoid scanning directory on every single message
        if (random_int(0, 9) !== 0) {
            return;
        }

        $now = time();
        $cutoff = $now - ($ttlMinutes * 60);

        $handle = @opendir($dedupDir);
        if ($handle === false) {
            return;
        }

        while (($file = readdir($handle)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $dedupDir . '/' . $file;

            if (is_file($filePath)) {
                $mtime = @filemtime($filePath);

                if ($mtime !== false && $mtime < $cutoff) {
                    @unlink($filePath);
                }
            }
        }

        closedir($handle);
    }
}
