<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

use WasapBot\Core\ConfigInterface;

/**
 * Coalescer — groups rapid-fire messages (burst) from the same sender.
 *
 * When multiple messages arrive within a short window (12s by default),
 * they are concatenated with " | " separator so the bot responds to all
 * at once instead of replying separately to each.
 *
 * Uses file-based coordination:
 *  - The first message in a burst becomes the "leader" and sleeps 4s to
 *    capture more messages.
 *  - Subsequent messages append their text to a shared buffer and halt
 *    (return null), letting the leader pick up everything.
 *  - After the sleep, the leader combines all buffered text.
 *
 * Also detects "opening bursts" (automatic web message + client greeting).
 *
 * Pattern: based on "Early Dedup Event" / "Early Dedup Gate" nodes in bot.json.
 */
final readonly class Coalescer implements PipelineStageInterface
{
    /** @var string[] Patterns that indicate an automatic message from a web portal */
    private const array AUTO_MSG_PATTERNS = [
        'he visto tu anuncio',
        'te he visto en',
        'quiero quedar contigo',
        'anuncio en http',
    ];

    /** @var string[] Patterns that indicate a greeting */
    private const array GREETING_PATTERNS = [
        'hola',
        'buenas',
        'hey',
        'ola',
    ];

    public function __construct(
        private ConfigInterface $config,
    ) {}

    public function name(): string
    {
        return 'Coalescer';
    }

    /**
     * @param  array<string, mixed> $ctx
     * @return array<string, mixed>|null
     */
    public function process(array $ctx): ?array
    {
        try {
            $fromPhone = $this->extractFromPhone($ctx);

            if ($fromPhone === '') {
                // Cannot coalesce without a phone — pass through unchanged
                return $ctx;
            }

            // ── Line-aware key: evita agrupar mensajes del mismo remitente
            //     enviados a distintas líneas WA simultáneamente ───────────
            $lineKey = $this->extractLineKey($ctx);
            $compositeKey = $lineKey !== '' ? ($fromPhone . '_' . $lineKey) : $fromPhone;

            $coalesceDir = (string) $this->config->get('files.coalesce_dir', 'data/locks/coalesce');
            $windowSec = (int) $this->config->get('dedup_coalesce.coalesce_window_sec', 12);
            $sleepSec = (int) $this->config->get('dedup_coalesce.coalesce_sleep_before_send_sec', 4);

            // Ensure directory exists
            if (!is_dir($coalesceDir)) {
                @mkdir($coalesceDir, 0755, true);
            }

            $metaFile = $coalesceDir . '/' . $compositeKey . '.meta';
            $bufFile  = $coalesceDir . '/' . $compositeKey . '.buf';
            $lockDir  = $coalesceDir . '/' . $compositeKey . '.lock';

            $wamid = $this->extractMessageId($ctx);
            $text  = $this->extractText($ctx);

            // ── Acquire lock ──────────────────────────────────────────
            if (!$this->acquireLock($lockDir)) {
                return $ctx; // fail-open
            }

            try {
                $now = time();
                $hasActiveBurst = false;

                if (file_exists($metaFile)) {
                    $metaModTime = (int) @filemtime($metaFile);
                    if (($now - $metaModTime) < $windowSec) {
                        $hasActiveBurst = true;
                    }
                }

                if ($hasActiveBurst) {
                    // Another message is the burst leader — append and yield
                    $line = $now . "\t" . $wamid . "\t" . $text . "\n";
                    @file_put_contents($bufFile, $line, FILE_APPEND | LOCK_EX);

                    return null; // Leader will pick us up
                }

                // We become the burst leader
                @file_put_contents($metaFile, $wamid, LOCK_EX);
                $line = $now . "\t" . $wamid . "\t" . $text . "\n";
                @file_put_contents($bufFile, $line, LOCK_EX);
            } finally {
                $this->releaseLock($lockDir);
            }

            // ── Wait for more burst messages ──────────────────────────
            sleep($sleepSec);

            // ── Re-acquire lock to finalize ───────────────────────────
            if (!$this->acquireLock($lockDir, 20)) {
                return $ctx; // fail-open
            }

            try {
                // Check if we're still the leader (no other message took over)
                $curWamid = '';

                if (file_exists($metaFile)) {
                    $curWamid = trim((string) @file_get_contents($metaFile));
                }

                if ($curWamid !== $wamid) {
                    // Another message became leader — skip
                    return null;
                }

                // ── Combine all buffered messages ─────────────────────
                $coalescedText = '';

                if (file_exists($bufFile)) {
                    $raw = @file($bufFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                    if (is_array($raw) && $raw !== []) {
                        $threshold = time() - $windowSec;
                        $parts = [];

                        foreach ($raw as $line) {
                            $chunks = explode("\t", (string) $line, 3);

                            if (count($chunks) >= 3 && (int) ($chunks[0] ?? 0) >= $threshold) {
                                $part = trim((string) ($chunks[2] ?? ''));
                                if ($part !== '') {
                                    $parts[] = $part;
                                }
                            }
                        }

                        $coalescedText = implode(" | ", $parts);
                    }
                }

                // Clean up state files
                @unlink($bufFile);
                @unlink($metaFile);

                if ($coalescedText === '') {
                    // Nothing was captured — pass through
                    return $ctx;
                }

                $ctx['__coalesced_text'] = $coalescedText;
                $ctx['__is_first'] = true;
                $ctx['__is_opening_burst'] = $this->detectOpeningBurst($coalescedText);

                return $ctx;
            } finally {
                $this->releaseLock($lockDir);
            }
        } catch (\Throwable) {
            // Never throw — fail-open
            return $ctx;
        }
    }

    /**
     * Acquire a directory-based mutex lock.
     *
     * @param int $tries   Number of acquisition attempts.
     * @param int $sleepMs Wait time between attempts in microseconds.
     */
    private function acquireLock(string $lockDir, int $tries = 40, int $sleepMs = 50000): bool
    {
        for ($i = 0; $i < $tries; $i++) {
            if (@mkdir($lockDir, 0755, false)) {
                return true;
            }
            usleep($sleepMs);
        }

        return false;
    }

    /**
     * Release a directory-based mutex lock.
     */
    private function releaseLock(string $lockDir): void
    {
        if (is_dir($lockDir)) {
            @rmdir($lockDir);
        }
    }

    /**
     * Extract sender phone number from the context.
     */
    /** @param array<string, mixed> $ctx */
    private function extractFromPhone(array $ctx): string
    {
        $body = $ctx['body'] ?? null;

        if (!is_array($body)) {
            return '';
        }

        $payload = $body['payload'] ?? null;

        if (!is_array($payload)) {
            return '';
        }

        // Try multiple known field paths
        $raw = (string) (
            $payload['from']
            ?? $payload['chatId']
            ?? $payload['sender']['id']
            ?? $body['from']
            ?? ''
        );

        // Normalize: strip everything but digits
        return (string) preg_replace('/[^0-9]/', '', $raw);
    }

    /**
     * Extract receiver line identifier from the context.
     *
     * Prefers ctx['line_last9'] (set by RoutingGate at gate 2).
     * Falls back to extracting from raw payload me.id / to fields.
     *
     * This is used to build a composite coalesce key (sender + receiver)
     * so that concurrent messages from the same sender to DIFFERENT
     * WhatsApp lines are NOT incorrectly grouped together.
     */
    /** @param array<string, mixed> $ctx */
    private function extractLineKey(array $ctx): string
    {
        // ── Prefer line_last9 from RoutingGate (already parsed) ───────
        $lineLast9 = (string) ($ctx['line_last9'] ?? '');
        if ($lineLast9 !== '') {
            return $lineLast9;
        }

        // ── Fallback: extract from raw payload ────────────────────────
        $body = $ctx['body'] ?? null;
        if (!is_array($body)) {
            return '';
        }

        // me.id is the receiver in WAHA webhooks
        $me = $body['me'] ?? null;
        if (is_array($me) && isset($me['id'])) {
            $raw = (string) $me['id'];
            $digits = (string) preg_replace('/[^0-9]/', '', $raw);
            if ($digits !== '') {
                return mb_substr($digits, -9);
            }
        }

        // to field as last resort
        $to = $body['to'] ?? null;
        if (is_string($to) && $to !== '') {
            $digits = (string) preg_replace('/[^0-9]/', '', $to);
            if ($digits !== '') {
                return mb_substr($digits, -9);
            }
        }

        return '';
    }

    /**
     * Extract message identifier from the context.
     */
    /** @param array<string, mixed> $ctx */
    private function extractMessageId(array $ctx): string
    {
        $body = $ctx['body'] ?? null;

        if (!is_array($body)) {
            return (string) time();
        }

        $payload = $body['payload'] ?? null;

        if (!is_array($payload)) {
            return (string) time();
        }

        return (string) (
            $payload['id']
            ?? $payload['wamid']
            ?? $payload['messageId']
            ?? ('k' . time())
        );
    }

    /**
     * Extract message text from the context for buffering.
     */
    /** @param array<string, mixed> $ctx */
    private function extractText(array $ctx): string
    {
        // Use coalesced text if already available from previous stages
        $coalesced = $ctx['__coalesced_text'] ?? null;

        if (is_string($coalesced) && $coalesced !== '') {
            return $coalesced;
        }

        $body = $ctx['body'] ?? null;

        if (!is_array($body)) {
            return '';
        }

        $payload = $body['payload'] ?? null;

        if (!is_array($payload)) {
            return '';
        }

        $text = (string) (
            $payload['body']
            ?? $payload['text']['body']
            ?? $payload['message']
            ?? $payload['caption']
            ?? ''
        );

        // Normalize whitespace
        $text = (string) preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Detect an opening burst: automatic web portal message + client greeting.
     *
     * Pattern example: "He visto tu anuncio en milanuncios.com hola me puedes decir las tarifas?"
     *
     * @see bot.json "Early Dedup Gate" node — detectOpeningBurst function
     */
    private function detectOpeningBurst(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        $lower = mb_strtolower($text);

        $hasAuto = false;
        foreach (self::AUTO_MSG_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                $hasAuto = true;
                break;
            }
        }

        if (!$hasAuto) {
            return false;
        }

        foreach (self::GREETING_PATTERNS as $pattern) {
            // Word-boundary match using simple str_contains + regex
            if (preg_match('/\b' . preg_quote($pattern, '/') . '\b/u', $lower)) {
                return true;
            }
        }

        return false;
    }
}
