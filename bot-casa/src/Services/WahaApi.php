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
     * @param array  $delayConfig          Full human_delays config section from config.json.
     *                                     Expected nested keys: seen, read, typing, habituation,
     *                                     pace, correction, pattern_variation, burst, urgent,
     *                                     after_send_fallback_sec.
     *                                     Also accepts pre-computed read_ms/type_ms/after_ms (legacy).
     * @param string $incomingText         The text received from user (used to compute read delay).
     * @param int    $turnCount            Number of bot messages in this session (for habituation).
     * @param float  $userResponseTimeSec  Seconds since last bot reply (for pace matching).
     * @param bool   $isBurst              True if user sent a rapid burst of messages.
     * @param bool   $isUrgent             True if user used urgent/impatient language.
     */
    /** @param array<string, mixed> $delayConfig */
    public function sendHumanized(
        string $baseUrl,
        string $chatId,
        string $text,
        string $session,
        array $delayConfig,
        string $incomingText = '',
        int $turnCount = 1,
        float $userResponseTimeSec = 60.0,
        bool $isBurst = false,
        bool $isUrgent = false,
        bool $isReprocess = false,
    ): bool {
        // ── Extract feature configs ───────────────────────────────────────
        $paceCfg   = is_array($delayConfig['pace']               ?? null) ? $delayConfig['pace']               : [];
        $corrCfg   = is_array($delayConfig['correction']         ?? null) ? $delayConfig['correction']         : [];
        $patCfg    = is_array($delayConfig['pattern_variation']  ?? null) ? $delayConfig['pattern_variation']  : [];
        $burstCfg  = is_array($delayConfig['burst']              ?? null) ? $delayConfig['burst']              : [];
        $urgentCfg = is_array($delayConfig['urgent']             ?? null) ? $delayConfig['urgent']             : [];

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

            // ── Habituation multiplier ─────────────────────────────────
            $startBoost = (float) ($habCfg['start_boost'] ?? 4.5);
            $decay      = (float) ($habCfg['decay']       ?? 0.95);
            $floor      = (float) ($habCfg['floor']       ?? 1.5);
            $habRaw     = $startBoost * pow($decay, max(0, $turnCount - 1));
            $hab        = max($floor, $habRaw);

            // ── B0: Pace factor ────────────────────────────────────────
            $paceEnabled = (bool) ($paceCfg['enabled'] ?? true);
            $paceFactor = 1.0;
            if ($paceEnabled) {
                $paceMin   = (float) ($paceCfg['min_factor']   ?? 0.5);
                $paceMax   = (float) ($paceCfg['max_factor']   ?? 2.0);
                $paceRef   = max(1.0, (float) ($paceCfg['reference_sec'] ?? 60));
                $paceSteep = (float) ($paceCfg['steepness']    ?? 0.2);
                $ratio     = max(0.01, $userResponseTimeSec / $paceRef);
                $paceFactor = max($paceMin, min($paceMax, pow($ratio, $paceSteep)));
            }

            // ── B9: Burst factor ───────────────────────────────────────
            $burstEnabled = (bool) ($burstCfg['enabled'] ?? true);
            $burstFactor = 1.0;
            if ($burstEnabled && $isBurst) {
                $burstFactor = (float) ($burstCfg['rapid_factor'] ?? 0.33);
            }

            // ── B10: Urgent factor (applied to ALL delays) ─────────────
            $urgentEnabled = (bool) ($urgentCfg['enabled'] ?? true);
            $urgentFactor = 1.0;
            if ($urgentEnabled && $isUrgent) {
                $urgentFactor = (float) ($urgentCfg['factor'] ?? 0.25);
            }

            // ── Combined multipliers ────────────────────────────────────
            // typing+after: habituation × pace × burst × urgent × reprocess
            // seen+read:    urgent × pace (B10)
            $typingMult = $hab * $paceFactor * $burstFactor;
            if ($isReprocess) {
                $typingMult *= 0.5;
            }
            $globalMult = $urgentFactor; // B10 only
            $typingMult *= $globalMult;  // urgent also applies to typing+after

            // ── Read delay: base_random + (in_chars × per_char_ms), clamped ──
            $perChar    = (int) ($readCfg['per_char_ms']  ?? 28);
            $clampMinR  = (int) ($readCfg['clamp_min_ms'] ?? 1500);
            $clampMaxR  = (int) ($readCfg['clamp_max_ms'] ?? 25000);
            $maxInChars = (int) ($typingCfg['max_incoming_chars'] ?? 180);

            $inChars = min(mb_strlen($incomingText), $maxInChars);

            // B5: short message → reduced read base
            $shortThreshold = (int) ($readCfg['short_threshold_chars'] ?? 15);
            if ($inChars < $shortThreshold) {
                $baseMin = (int) ($readCfg['short_base_min_ms'] ?? 300);
                $baseMax = (int) ($readCfg['short_base_max_ms'] ?? 800);
            } else {
                $baseMin = (int) ($readCfg['base_min_ms'] ?? 1500);
                $baseMax = (int) ($readCfg['base_max_ms'] ?? 3500);
            }

            $readBase = random_int($baseMin, max($baseMin, $baseMax));
            $readRaw  = ($readBase + ($inChars * $perChar));
            $readMs   = (int) max($clampMinR, min($clampMaxR, $readRaw));
            // Apply urgent + pace multiplier to read (reprocess halves)
            $readMs   = (int) max(200, $readMs * $globalMult * $paceFactor * ($isReprocess ? 0.5 : 1.0));

            // ── Type delay: start + (chars/cps) + chunk_pauses, clamped ──
            $startMin    = (int)   ($typingCfg['start_min_ms']      ?? 600);
            $startMax    = (int)   ($typingCfg['start_max_ms']      ?? 2000);
            $cpsMin      = (int)   ($typingCfg['chars_per_sec_min'] ?? 28);
            $cpsMax      = (int)   ($typingCfg['chars_per_sec_max'] ?? 60);
            $chunkSize   = (int)   ($typingCfg['chunk_size']        ?? 22);
            $chunkFactor = (float) ($typingCfg['chunk_pause_factor'] ?? 0.70);

            $outChars   = mb_strlen($text);
            $cps        = random_int($cpsMin, max($cpsMin, $cpsMax));
            if ($cps <= 0) { $cps = 1; }
            $typeStart  = random_int($startMin, max($startMin, $startMax));
            $chunks     = $chunkSize > 0 ? (int) floor($outChars / $chunkSize) : 0;
            $chunkPause = $chunks * $chunkFactor * 270;
            $typeRaw    = ($typeStart + ($outChars > 0 ? ($outChars / $cps) * 1000 : 0) + $chunkPause) * $typingMult;
            $typeClampMax = (int) ($typingCfg['clamp_max_ms'] ?? 45000);
            $typeMs     = (int) max(1200, min($typeClampMax, $typeRaw));

            // ── B7: Emoji-only reply → ultra-fast ──────────────────────
            $isEmojiOnly = (bool) preg_match('/^[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}\x{200D}\x{FE0F}\s]+$/u', $text);
            if ($isEmojiOnly) {
                $typeMs = min($typeMs, 600);
                $readMs = min($readMs, 600);
            }

            // ── After-send delay ───────────────────────────────────────
            $afterSec    = (float) ($delayConfig['after_send_fallback_sec'] ?? 0.4);
            $afterMsBase = (int) ($afterSec * 1000);
            $afterMin    = max(250, (int) ($afterMsBase * 0.5));
            $afterMax    = max($afterMin + 100, (int) ($afterMsBase * 1.8));
            $afterRaw    = random_int($afterMin, $afterMax) * $typingMult;
            $afterCap    = max($afterMsBase * 4, 2500);
            $afterMs     = (int) max(250, min($afterCap, $afterRaw));
        }

        // ── Compute seen delay ────────────────────────────────────────────
        $seenCfg = is_array($delayConfig['seen'] ?? null) ? $delayConfig['seen'] : [];
        $seenMin = max(1, (int) ($seenCfg['random_min_sec'] ?? 1));
        $seenMax = max($seenMin, (int) ($seenCfg['random_max_sec'] ?? 3));
        $seenMs  = $seenMin === $seenMax ? $seenMin * 1000 : random_int($seenMin * 1000, $seenMax * 1000);
        // B10: urgent applies to seen too
        if ($isUrgent && isset($urgentCfg) && ($urgentCfg['enabled'] ?? true)) {
            $seenMs = max(200, (int) ($seenMs * (float) ($urgentCfg['factor'] ?? 0.25)));
        }
        // Reprocess: halve seen delay when catching up after late-arriving messages
        if ($isReprocess) {
            $seenMs = max(200, (int) ($seenMs * 0.5));
        }

        // ── B6: Choose sequence pattern ──────────────────────────────────
        $pattern = $this->choosePattern($patCfg);

        // ── B1: Correction simulation config ──────────────────────────────
        $corrEnabled    = (bool) ($corrCfg['enabled'] ?? true);
        $corrProb       = (float) ($corrCfg['probability'] ?? 0.12);
        $corrPauseMin   = (int) ($corrCfg['pause_min_ms'] ?? 400);
        $corrPauseMax   = (int) ($corrCfg['pause_max_ms'] ?? 1800);

        // ── Execute selected pattern ──────────────────────────────────────
        $ok = $this->executePattern(
            $pattern,
            $baseUrl, $chatId, $text, $session,
            $seenMs, $readMs, $typeMs, $afterMs,
            $corrEnabled, $corrProb, $corrPauseMin, $corrPauseMax,
        );

        return $ok;
    }

    /**
     * Choose a humanization sequence pattern via weighted random.
     *
     * @param array $patCfg  pattern_variation config section.
     * @return string  'standard', 'skip_read', or 'read_first'.
     */
    /** @param array<string, mixed> $patCfg */
    private function choosePattern(array $patCfg): string
    {
        $enabled = (bool) ($patCfg['enabled'] ?? true);
        if (!$enabled) {
            return 'standard';
        }

        $w1 = max(0, (int) ($patCfg['weight_standard']  ?? 70));
        $w2 = max(0, (int) ($patCfg['weight_skip_read'] ?? 20));
        $w3 = max(0, (int) ($patCfg['weight_read_first'] ?? 10));

        $total = $w1 + $w2 + $w3;
        if ($total <= 0) {
            return 'standard';
        }

        $r = random_int(1, $total);
        if ($r <= $w1) {
            return 'standard';
        }
        if ($r <= $w1 + $w2) {
            return 'skip_read';
        }
        return 'read_first';
    }

    /**
     * Execute the chosen humanization pattern.
     *
     * B1: Correction simulation (occasional stop/restart typing) applies
     *     to any pattern that includes a typing phase.
     */
    private function executePattern(
        string $pattern,
        string $baseUrl, string $chatId, string $text, string $session,
        int $seenMs, int $readMs, int $typeMs, int $afterMs,
        bool $corrEnabled, float $corrProb, int $corrPauseMin, int $corrPauseMax,
    ): bool {
        switch ($pattern) {
            case 'skip_read':
                // Pattern B: seen → typing → send
                if ($seenMs > 0) {
                    usleep($seenMs * 1000);
                }
                $this->sendSeen($baseUrl, $chatId, $session);

                $this->startTyping($baseUrl, $chatId, $session);
                // B1: correction simulation
                if ($corrEnabled && $corrProb > 0) {
                    $this->maybeCorrect($corrProb, $corrPauseMin, $corrPauseMax, $baseUrl, $chatId, $session);
                }

                if ($typeMs > 0) {
                    usleep($typeMs * 1000);
                }

                $ok = $this->sendText($baseUrl, $chatId, $text, $session);
                $this->stopTyping($baseUrl, $chatId, $session);

                if ($afterMs > 0) {
                    usleep($afterMs * 1000);
                }
                return $ok;

            case 'read_first':
                // Pattern C: read → seen → typing → send
                if ($readMs > 0) {
                    usleep($readMs * 1000);
                }

                if ($seenMs > 0) {
                    usleep($seenMs * 1000);
                }
                $this->sendSeen($baseUrl, $chatId, $session);

                if ($readMs > 0) {
                    usleep($readMs * 1000);
                }

                $this->startTyping($baseUrl, $chatId, $session);
                // B1: correction simulation
                if ($corrEnabled && $corrProb > 0) {
                    $this->maybeCorrect($corrProb, $corrPauseMin, $corrPauseMax, $baseUrl, $chatId, $session);
                }

                if ($typeMs > 0) {
                    usleep($typeMs * 1000);
                }

                $ok = $this->sendText($baseUrl, $chatId, $text, $session);
                $this->stopTyping($baseUrl, $chatId, $session);

                if ($afterMs > 0) {
                    usleep($afterMs * 1000);
                }
                return $ok;

            default: // 'standard'
                // Pattern A (default): seen → read×2 → typing → send
                if ($seenMs > 0) {
                    usleep($seenMs * 1000);
                }
                $this->sendSeen($baseUrl, $chatId, $session);

                if ($readMs > 0) {
                    usleep($readMs * 1000);
                }
                if ($readMs > 0) {
                    usleep($readMs * 1000);
                }

                $this->startTyping($baseUrl, $chatId, $session);
                // B1: correction simulation
                if ($corrEnabled && $corrProb > 0) {
                    $this->maybeCorrect($corrProb, $corrPauseMin, $corrPauseMax, $baseUrl, $chatId, $session);
                }

                if ($typeMs > 0) {
                    usleep($typeMs * 1000);
                }

                $ok = $this->sendText($baseUrl, $chatId, $text, $session);
                $this->stopTyping($baseUrl, $chatId, $session);

                if ($afterMs > 0) {
                    usleep($afterMs * 1000);
                }
                return $ok;
        }
    }

    /**
     * B1: Correction simulation — occasionally stop typing, pause, restart.
     * Simulates deleting and re-writing a message, which humans do naturally.
     */
    private function maybeCorrect(
        float $probability, int $pauseMinMs, int $pauseMaxMs,
        string $baseUrl, string $chatId, string $session,
    ): void {
        $roll = mt_rand(0, 10000) / 10000.0; // float 0..1 with decent precision
        if ($roll >= $probability) {
            return;
        }

        $pauseMs = random_int($pauseMinMs, max($pauseMinMs, $pauseMaxMs));
        if ($pauseMs > 0) {
            usleep($pauseMs * 1000);
        }

        $this->stopTyping($baseUrl, $chatId, $session);

        // Shorter pause before restarting
        $halfPause = max(200, (int) ($pauseMs * 0.4));
        usleep($halfPause * 1000);

        $this->startTyping($baseUrl, $chatId, $session);
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
