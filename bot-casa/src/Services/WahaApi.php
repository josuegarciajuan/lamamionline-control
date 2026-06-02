<?php

declare(strict_types=1);

namespace WasapBot\Services;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\HttpClientInterface;
use WasapBot\Core\LoggerInterface;

/**
 * WAHA (WhatsApp HTTP API) client implementation.
 *
 * Wraps the WAHA REST API endpoints with anti-ban humanization and never-error semantics.
 */
final class WahaApi implements WahaApiInterface
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
    ) {}

    public function sendText(string $baseUrl, string $chatId, string $text, string $session): bool
    {
        $body = [
            'session' => $session,
            'chatId'  => $chatId,
            'text'    => $text,
        ];

        return $this->call($baseUrl, '/api/sendText', $body);
    }

    public function sendSeen(string $baseUrl, string $chatId, string $session): bool
    {
        $body = [
            'session' => $session,
            'chatId'  => $chatId,
        ];

        return $this->call($baseUrl, '/api/sendSeen', $body);
    }

    public function startTyping(string $baseUrl, string $chatId, string $session): bool
    {
        $body = [
            'session' => $session,
            'chatId'  => $chatId,
        ];

        return $this->call($baseUrl, '/api/startTyping', $body);
    }

    public function stopTyping(string $baseUrl, string $chatId, string $session): bool
    {
        $body = [
            'session' => $session,
            'chatId'  => $chatId,
        ];

        return $this->call($baseUrl, '/api/stopTyping', $body);
    }

    public function sendImage(string $baseUrl, string $chatId, string $imageUrl, string $caption, string $session): bool
    {
        $body = [
            'session'   => $session,
            'chatId'    => $chatId,
            'imageUrl'  => $imageUrl,
            'caption'   => $caption,
        ];

        return $this->call($baseUrl, '/api/sendImage', $body);
    }

    /**
     * Quick send — no humanization. Used for follow-up URL-only messages.
     * Just sends the text immediately. Suitable for solo-link messages where
     * typing simulation is not needed.
     */
    public function sendQuick(
        string $baseUrl,
        string $chatId,
        string $text,
        string $session,
    ): bool {
        return $this->sendText($baseUrl, $chatId, $text, $session);
    }

    /**
     * Humanized message sequence with typing indicators and natural delays.
     *
     * Replicates the n8n "Compute Human Delays" + anti-ban flow:
     *   1. sendSeen
     *   2. delay read_ms  (simulate reading the incoming message)
     *   3. delay read_ms  (before typing — two read delays like n8n)
     *   4. startTyping
     *   5. delay type_ms  (simulate writing the reply)
     *   6. sendText
     *   7. stopTyping
     *   8. delay after_ms
     *
     * @param array $delayConfig  Full human_delays config section from config.json.
     *                            Expected nested keys: seen, read, typing, habituation,
     *                            short_typing_sec, after_send_fallback_sec.
     *                            Also accepts pre-computed read_ms/type_ms/after_ms (legacy).
     * @param string $incomingText  The text received from user (used to compute read delay).
     * @param int    $turnCount     Number of bot messages in this session (for habituation).
     */
    public function sendHumanized(
        string $baseUrl,
        string $chatId,
        string $text,
        string $session,
        array $delayConfig,
        string $incomingText = '',
        int $turnCount = 1,
    ): bool {
        // ── Compute delays ────────────────────────────────────────────────
        // Support both legacy flat keys (read_ms/type_ms/after_ms in ms)
        // and the nested config structure used in config.json.
        $readMs  = 0;
        $typeMs  = 0;
        $afterMs = 0;

        if (isset($delayConfig['read_ms']) || isset($delayConfig['type_ms'])) {
            // Legacy: already computed milliseconds
            $readMs  = (int) ($delayConfig['read_ms']  ?? 0);
            $typeMs  = (int) ($delayConfig['type_ms']  ?? 0);
            $afterMs = (int) ($delayConfig['after_ms'] ?? 0);
        } else {
            // Nested config — compute like n8n "Compute Human Delays"
            $readCfg   = is_array($delayConfig['read']       ?? null) ? $delayConfig['read']       : [];
            $typingCfg = is_array($delayConfig['typing']     ?? null) ? $delayConfig['typing']     : [];
            $habCfg    = is_array($delayConfig['habituation'] ?? null) ? $delayConfig['habituation'] : [];

            // Habituation multiplier
            $startBoost = (float) ($habCfg['start_boost'] ?? 4.5);
            $decay      = (float) ($habCfg['decay']       ?? 0.95);
            $floor      = (float) ($habCfg['floor']       ?? 1.5);
            $habRaw     = $startBoost * pow($decay, max(0, $turnCount - 1));
            $hab        = max($floor, $habRaw);

            // Read delay: base_random + (in_chars × per_char_ms), clamped
            $baseMin    = (int) ($readCfg['base_min_ms']  ?? 1500);
            $baseMax    = (int) ($readCfg['base_max_ms']  ?? 3500);
            $perChar    = (int) ($readCfg['per_char_ms']  ?? 28);
            $clampMin   = (int) ($readCfg['clamp_min_ms'] ?? 1500);
            $clampMax   = (int) ($readCfg['clamp_max_ms'] ?? 25000);
            $maxInChars = (int) ($typingCfg['max_incoming_chars'] ?? 180);

            $inChars  = min(mb_strlen($incomingText), $maxInChars);
            $readBase = random_int($baseMin, max($baseMin, $baseMax));
            $readRaw  = ($readBase + ($inChars * $perChar)) * $hab;
            $readMs   = (int) max($clampMin, min($clampMax, $readRaw));

            // Type delay: start_delay + (out_chars / chars_per_sec) + chunk_pauses, clamped
            $startMin     = (int)   ($typingCfg['start_min_ms']      ?? 600);
            $startMax     = (int)   ($typingCfg['start_max_ms']      ?? 2000);
            $cpsMin       = (int)   ($typingCfg['chars_per_sec_min'] ?? 28);
            $cpsMax       = (int)   ($typingCfg['chars_per_sec_max'] ?? 60);
            $chunkSize    = (int)   ($typingCfg['chunk_size']        ?? 22);
            $chunkFactor  = (float) ($typingCfg['chunk_pause_factor'] ?? 0.70);

            $outChars   = mb_strlen($text);
            $cps        = random_int($cpsMin, max($cpsMin, $cpsMax));
            if ($cps <= 0) { $cps = 1; } // guard division by zero
            $typeStart  = random_int($startMin, max($startMin, $startMax));
            $chunks     = $chunkSize > 0 ? (int) floor($outChars / $chunkSize) : 0;
            $chunkPause = $chunks * $chunkFactor * 270;
            $typeRaw    = ($typeStart + ($outChars > 0 ? ($outChars / $cps) * 1000 : 0) + $chunkPause) * $hab;
            $typeMs     = (int) max(1200, min(45000, $typeRaw));

            // After-send delay — uses after_send_fallback_sec from config as base
            // (previously hardcoded as random_int(250,900) ignoring the config value)
            $afterSec    = (float) ($delayConfig['after_send_fallback_sec'] ?? 0.4);
            $afterMsBase = (int) ($afterSec * 1000);
            $afterMin    = max(250, (int) ($afterMsBase * 0.5));
            $afterMax    = max($afterMin + 100, (int) ($afterMsBase * 1.8));
            $afterRaw    = random_int($afterMin, $afterMax) * $hab;
            $afterCap    = max($afterMsBase * 4, 2500);
            $afterMs     = (int) max(250, min($afterCap, $afterRaw));
        }

        // ── Compute seen delay ────────────────────────────────────────────
        // Uses human_delays.seen config (previously ignored — now wired)
        $seenCfg = is_array($delayConfig['seen'] ?? null) ? $delayConfig['seen'] : [];
        $seenMin = max(1, (int) ($seenCfg['random_min_sec'] ?? 1));
        $seenMax = max($seenMin, (int) ($seenCfg['random_max_sec'] ?? 3));
        $seenMs  = $seenMin === $seenMax ? $seenMin * 1000 : random_int($seenMin * 1000, $seenMax * 1000);

        // ── Sequence: seen_delay → sendSeen → read_delay → read_delay → startTyping → type_delay → send → stop → after ──
        if ($seenMs > 0) {
            usleep($seenMs * 1000); // ms → µs — simulate reading before marking as seen
        }
        $this->sendSeen($baseUrl, $chatId, $session);

        // Two read delays (simulate reading the full message + pause before typing)
        if ($readMs > 0) {
            usleep($readMs * 1000); // ms → µs
        }
        if ($readMs > 0) {
            usleep($readMs * 1000);
        }

        $this->startTyping($baseUrl, $chatId, $session);

        if ($typeMs > 0) {
            usleep($typeMs * 1000); // ms → µs
        }

        $ok = $this->sendText($baseUrl, $chatId, $text, $session);

        $this->stopTyping($baseUrl, $chatId, $session);

        if ($afterMs > 0) {
            usleep($afterMs * 1000); // ms → µs
        }

        return $ok;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Execute a WAHA POST call with neverError semantics.
     *
     * Uses the x-api-key header from config. Returns true on HTTP 2xx,
     * false on any error (never throws).
     */
    private function call(string $baseUrl, string $endpoint, array $body): bool
    {
        $url  = rtrim($baseUrl, '/') . $endpoint;
        $key  = $this->getApiKey();

        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $key,
        ];

        try {
            $this->http->post($url, $body, $headers, 20);
            $httpCode = $this->http->lastHttpCode();

            if ($httpCode >= 200 && $httpCode < 300) {
                return true;
            }

            $this->logger->warning("WAHA {$endpoint} returned HTTP {$httpCode}", [
                'chatId' => $body['chatId'] ?? '?',
                'error'  => $this->http->lastError(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error("WAHA {$endpoint} exception: {$e->getMessage()}", [
                'chatId' => $body['chatId'] ?? '?',
            ]);
        }

        return false;
    }

    private function getApiKey(): string
    {
        return (string) ($this->config->get('waha.api_key') ?? '');
    }

    /**
     * Resolve session name with fallback to config default.
     */
    private function resolveSession(string $session): string
    {
        if ($session !== '' && $session !== 'default') {
            return $session;
        }

        return (string) ($this->config->get('waha.session') ?? 'default');
    }
}
