<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

use WasapBot\Core\ConfigInterface;

/**
 * RoutingGate — verifies the receiving line is enabled and the sender is not blacklisted.
 *
 * Checks:
 *  1. Whether the destination phone line (receiver / "me") is enabled in config.
 *  2. Whether the sender phone is on the blacklist.
 *
 * If either check fails, the pipeline halts (returns null).
 *
 * Also builds WAHA connection details for downstream stages:
 *  - waha_line / waha_enabled / line_label / waha_port
 *  - waha_base_url / waha_chat_id / waha_api_key / waha_session
 *
 * Pattern: based on "Gate: Enabled + Blacklist" node in bot.json (~lines 1223-1262).
 */
final readonly class RoutingGate implements PipelineStageInterface
{
    public function __construct(
        private ConfigInterface $config,
    ) {}

    public function name(): string
    {
        return 'RoutingGate';
    }

    /**
     * @param  array<string, mixed> $ctx
     * @return array<string, mixed>|null
     */
    public function process(array $ctx): ?array
    {
        try {
            /** @var array<string, mixed>|null $body */
            $body = $ctx['body'] ?? null;
            $body = is_array($body) ? $body : [];

            /** @var array<string, mixed>|null $payload */
            $payload = $body['payload'] ?? null;
            $payload = is_array($payload) ? $payload : [];

            /** @var array<string, mixed>|null $me */
            $me = $body['me'] ?? null;
            $me = is_array($me) ? $me : [];

            if ($me === [] && $payload !== []) {
                $me = $payload['me'] ?? null;
                $me = is_array($me) ? $me : [];
            }

            // ── 1) Gate by receiver (destination line) ───────────────────
            $receiverDigits = $this->onlyDigits(
                (string) ($me['id'] ?? $payload['to'] ?? $body['to'] ?? '')
            );
            $receiverLast9 = $receiverDigits !== '' ? mb_substr($receiverDigits, -9) : '';

            /** @var list<array{last9?: string, port?: int, enabled?: bool, label?: string}> $lines */
            $lines = (array) $this->config->get('routing.lines', []);
            $defaultEnabled = (bool) $this->config->get('routing.default_enabled_if_not_found', false);

            $entry = null;

            if ($receiverLast9 !== '') {
                foreach ($lines as $line) {
                    if (!is_array($line)) {
                        continue;
                    }

                    $lineLast9 = $this->onlyDigits((string) ($line['last9'] ?? ''));

                    if ($lineLast9 !== '' && $lineLast9 === $receiverLast9) {
                        $entry = $line;
                        break;
                    }
                }
            }

            $lineEnabled = $entry !== null ? (bool) ($entry['enabled'] ?? false) : $defaultEnabled;

            if (!$lineEnabled) {
                return null; // Line not enabled — halt
            }

            // ── 2) Gate by sender blacklist ──────────────────────────────
            $fromPhone = $ctx['from_phone'] ?? $this->extractFromPhone($ctx, $body, $payload);

            if ($fromPhone !== '' && $this->isBlacklisted($fromPhone)) {
                return null; // Sender blocked — halt
            }

            // ── 3) Build WAHA connection details ─────────────────────────
            $wahaPort = $entry !== null
                ? (int) ($entry['port'] ?? $this->config->get('waha.default_port', 3000))
                : (int) $this->config->get('waha.default_port', 3000);

            $wahaBaseIp = (string) $this->config->get('waha.base_ip', '127.0.0.1');
            $wahaApiKey = (string) $this->config->get('waha.api_key', '');
            $wahaSession = (string) $this->config->get('waha.session', 'default');
            $chatIdSuffix = (string) $this->config->get('waha.chat_id_suffix', '@c.us');

            // ── Determine correct chatId suffix for GOWS engine ──────────
            // GOWS uses LIDs (e.g. "277476546711679@lid") as the `from` field.
            // We must reply to that LID, not to a fake @c.us address.
            // If the raw `from` in payload contains "@lid", use "@lid" suffix.
            $rawFrom = (string) (
                $payload['from']
                ?? $payload['chatId']
                ?? $body['from']
                ?? ''
            );
            if (str_contains(mb_strtolower($rawFrom), '@lid')) {
                $chatIdSuffix = '@lid';
                // Extract numeric part of the LID for the chat ID
                $fromPhone = $this->onlyDigits($rawFrom);
            }

            $ctx['waha_line']    = $entry;
            $ctx['waha_enabled'] = $lineEnabled;
            $ctx['line_label']   = $entry !== null ? ((string) ($entry['label'] ?? '')) : '';
            $ctx['line_notas']   = $this->lookupNotasByPort($wahaPort);
            $ctx['waha_port']    = $wahaPort;
            $ctx['line_last9']   = $entry !== null ? ((string) ($entry['last9'] ?? '')) : '';
            $ctx['ai_provider']  = $entry !== null ? ((string) ($entry['ai_provider'] ?? 'openai')) : 'openai';
            $ctx['ai_model']     = $entry !== null ? ($entry['ai_model'] ?? null) : null;
            $ctx['waha_base_url'] = 'http://' . $wahaBaseIp . ':' . $wahaPort;
            $ctx['waha_chat_id']  = $fromPhone . $chatIdSuffix;
            // Security: Do NOT propagate API key in context array.
            // Key is injected directly into WahaApi service constructor, not via ctx.
            $ctx['waha_api_key']  = '';
            $ctx['waha_session']  = $wahaSession;

            return $ctx;
        } catch (\Throwable) {
            // Never throw — halt pipeline on error (fail-closed for security gate)
            return null;
        }
    }

    /**
     * Strip all non-digit characters from a string.
     */
    private function onlyDigits(string $value): string
    {
        $digits = (string) preg_replace('/[^0-9]/', '', $value);

        return $digits;
    }

    /**
     * Look up the "notas" field from telefonos.json for the line whose
     * waha_port matches the given port number.
     *
     * Returns the notas string (e.g. "pos1", "pos2") or empty string if
     * not found or if the file is unreachable.
     */
    private function lookupNotasByPort(int $port): string
    {
        $configDir = $this->config->getConfigDir();
        $candidates = [
            $configDir . '/../../data/telefonos.json',
            $configDir . '/../data/telefonos.json',
            $configDir . '/data/telefonos.json',
        ];

        $raw = null;
        foreach ($candidates as $path) {
            $real = @realpath($path);
            if ($real !== false && file_exists($real)) {
                $contents = @file_get_contents($real);
                if ($contents !== false) {
                    $raw = $contents;
                    break;
                }
            }
        }

        if ($raw === null) {
            return '';
        }

        try {
            $lines = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return '';
        }

        if (!is_array($lines)) {
            return '';
        }

        $portStr = (string) $port;
        foreach ($lines as $line) {
            if (!is_array($line)) {
                continue;
            }
            if (((string) ($line['waha_port'] ?? '')) === $portStr) {
                return trim((string) ($line['notas'] ?? ''));
            }
        }

        return '';
    }

    /**
     * Check whether a phone number is on the sender blacklist.
     *
     * Matches by:
     *  - Full phone number (digits)
     *  - Last 9 digits
     *  - Normalized with @c.us / @lid suffixes
     */
    private function isBlacklisted(string $senderDigits): bool
    {
        if ($senderDigits === '') {
            return false;
        }

        /** @var list<string> $blacklist */
        $blacklist = (array) $this->config->get('routing.sender_blacklist', []);

        if ($blacklist === []) {
            return false;
        }

        $senderLast9 = mb_substr($senderDigits, -9);

        foreach ($blacklist as $entry) {
            if (!is_string($entry) || $entry === '') {
                continue;
            }

            $entryLower = mb_strtolower(trim($entry));

            // Contains @ suffix (e.g., "12345@lid", "12345@c.us")
            if (str_contains($entryLower, '@')) {
                // Match against senderDigits + known suffixes
                if ($entryLower === $senderDigits . '@c.us') {
                    return true;
                }
                if ($entryLower === $senderDigits . '@lid') {
                    return true;
                }

                continue;
            }

            // Numeric entry
            $entryDigits = $this->onlyDigits($entry);

            if ($entryDigits === '') {
                continue;
            }

            // Full match
            if ($senderDigits === $entryDigits) {
                return true;
            }

            // Ends-with match (e.g., blacklist entry "34654464023" matches sender "654464023")
            if (str_ends_with($senderDigits, $entryDigits)) {
                return true;
            }

            // Last-9 match
            if (mb_strlen($entryDigits) >= 9 && $senderLast9 === mb_substr($entryDigits, -9)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract sender phone from the context/payload.
     *
     * @param array<string, mixed> $body
     * @param array<string, mixed> $payload
     */
    private function extractFromPhone(array $ctx, array $body, array $payload): string
    {
        // Already available from MessageExtractor
        if (isset($ctx['from_phone']) && is_string($ctx['from_phone']) && $ctx['from_phone'] !== '') {
            return $ctx['from_phone'];
        }

        // Try payload fields
        if ($payload !== []) {
            $data = [];
            if (isset($payload['_data']) && is_array($payload['_data'])) {
                $data = $payload['_data'];
            }

            $raw = (string) (
                $payload['from']
                ?? $payload['chatId']
                ?? $payload['author']
                ?? $payload['participant']
                ?? ($payload['sender']['id'] ?? '')
                ?? ($data['from'] ?? '')
                ?? ($data['author'] ?? '')
                ?? ''
            );

            if ($raw !== '') {
                return $this->onlyDigits($raw);
            }
        }

        // Fallback to body/context level
        $raw = (string) ($body['from'] ?? $ctx['from'] ?? '');

        return $this->onlyDigits($raw);
    }
}
