<?php

declare(strict_types=1);

namespace WasapBot\Services;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\HttpClientInterface;
use WasapBot\Core\LoggerInterface;

/**
 * Telegram bot service — sends lead alerts and messages via the Telegram Bot API.
 *
 * Alerts go to all configured chat_ids when alert_enabled is true.
 */
final class TelegramService implements TelegramServiceInterface
{
    /** @var list<string> */
    private readonly array $chatIds;

    private readonly bool $alertEnabled;

    private readonly string $botToken;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
    ) {
        $this->alertEnabled = (bool) $this->config->get('telegram.alert_enabled', true);
        $this->botToken     = (string) $this->config->get('telegram.bot_token', 'CHANGEME_TELEGRAM_BOT_TOKEN');

        $configuredIds = $this->config->get('telegram.chat_ids', []);
        $this->chatIds = is_array($configuredIds)
            ? array_values(array_filter(array_map('strval', $configuredIds), static fn(string $id): bool => $id !== ''))
            : [];

        if ($this->botToken === 'CHANGEME_TELEGRAM_BOT_TOKEN' || $this->botToken === '') {
            $this->logger->warning('Telegram bot token is not configured — alerts will not be sent');
        }
    }

    /**
     * Send a lead alert to all configured Telegram chat IDs.
     *
     * Format: multiline with phone, girl name, line, confidence %, ETA, conversation excerpt, thread ID.
     *
     * @param array{
     *     from_phone?: string, phone?: string,
     *     line_label?: string, line_last9?: string,
     *     lead_confidence?: float|string, confidence?: float|string,
     *     eta_minutes?: int|string, eta?: int|string,
     *     thread_id?: string, selected_girl_name?: string,
     *     last_user_meaningful?: string, message_text?: string,
     *     last_bot_reply?: string, output_text?: string,
     *     interes_fuerte?: mixed, maps_sent?: mixed,
     *     ya_enviado?: list<string>
     * } $leadData
     */
    public function sendLeadAlert(array $leadData): void
    {
        if (!$this->alertEnabled) {
            $this->logger->debug('Telegram alerts are disabled');
            return;
        }

        if ($this->botToken === 'CHANGEME_TELEGRAM_BOT_TOKEN' || $this->botToken === '') {
            $this->logger->warning('Telegram bot token not configured — skipping alert');
            return;
        }

        $phoneRaw  = (string) ($leadData['from_phone'] ?? $leadData['phone'] ?? '?');
        $phone     = ($phoneRaw !== '' && $phoneRaw !== '?') ? preg_replace('/[^0-9]/', '', $phoneRaw) ?? $phoneRaw : $phoneRaw;
        $lineLabel = (string) ($leadData['line_label'] ?? '');
        $lineNotas = (string) ($leadData['line_notas'] ?? '');
        $lineLast9 = (string) ($leadData['line_last9'] ?? '');
        $conf      = $leadData['lead_confidence'] ?? $leadData['confidence'] ?? 0;
        $eta       = $leadData['eta_minutes'] ?? $leadData['eta'] ?? 0;
        $threadId  = (string) ($leadData['thread_id'] ?? '');
        $selectedGirl = (string) ($leadData['selected_girl_name'] ?? '');
        $lastUser  = (string) ($leadData['last_user_meaningful'] ?? $leadData['message_text'] ?? '');
        $lastBot   = (string) ($leadData['last_bot_reply'] ?? $leadData['output_text'] ?? '');

        // ── Compute confidence as percentage (0-100) ──────────────
        $llmConf = (is_numeric($conf) && (float) $conf > 0) ? round((float) $conf * 100) : 0;
        $detConf = $this->computeDeterministicConfidence($leadData);
        $confidencePct = $llmConf > 0 ? max($llmConf, $detConf) : $detConf;

        $etaMin = is_numeric($eta) ? (int) $eta : 0;

        // ── Format phone with spaces for readability ──────────────
        $phoneFormatted = $phone;
        if (strlen($phone) >= 9) {
            $phoneFormatted = '+' . substr($phone, 0, 2) . ' ' . substr($phone, 2, 3) . ' ' . substr($phone, 5, 3) . ' ' . substr($phone, 8);
        }

        // ── Build line description ───────────────────────────────
        // Prefer "notas" (meaningful CRM info like "pos1", "pos2") over the
        // auto-generated label (e.g. "linea_3000") when available.
        $lineDisplay = ($lineNotas !== '') ? $lineNotas : $lineLabel;
        $lineDesc = $lineDisplay;
        if ($lineLast9 !== '') {
            // Format last9 with spaces: 624934900 → 624 934 900
            $last9Fmt = rtrim(chunk_split($lineLast9, 3, ' '));
            $lineDesc = $last9Fmt . ' (' . $lineDisplay . ')';
        }

        // ── Build the exciting Telegram message ──────────────────
        $text = "💰💰 ¡LEAD DETECTADO! 💰💰\n\n";
        $text .= "📱 {$phoneFormatted}\n";
        if ($selectedGirl !== '') {
            $text .= "👤 {$selectedGirl}\n";
        }
        $text .= "📍 {$lineDesc}\n";
        $text .= "🎯 Confianza: {$confidencePct}%\n";
        if ($etaMin > 0) {
            $text .= "⏱ ETA: {$etaMin} min\n";
        }

        // ── Add recent conversation excerpt ──────────────────────
        if ($lastUser !== '' || $lastBot !== '') {
            $text .= "\n💬 Conversación:\n";
            if ($lastUser !== '') {
                $text .= "Cliente: \"" . mb_substr($lastUser, 0, 120) . "\"\n";
            }
            if ($lastBot !== '') {
                $text .= "Bot: \"" . mb_substr($lastBot, 0, 120) . "\"\n";
            }
        }

        $text .= "\n📋 {$threadId}";

        foreach ($this->chatIds as $chatId) {
            $this->sendMessage($chatId, $text);
        }
    }

    /**
     * Send a text message to a specific Telegram chat.
     *
     * Uses HTTPS POST to https://api.telegram.org/bot{token}/sendMessage
     * with parse_mode=HTML.
     */
    public function sendMessage(string $chatId, string $text): bool
    {
        if ($this->botToken === 'CHANGEME_TELEGRAM_BOT_TOKEN' || $this->botToken === '') {
            $this->logger->warning('Telegram bot token not configured — message not sent');
            return false;
        }

        $url = sprintf('https://api.telegram.org/bot%s/sendMessage', $this->botToken);

        $body = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ];

        $headers = [
            'Content-Type: application/json',
        ];

        try {
            [$httpCode] = $this->http->post($url, $body, $headers, 15);

            if ($httpCode >= 200 && $httpCode < 300) {
                return true;
            }

            $this->logger->warning("Telegram sendMessage returned HTTP {$httpCode} to chat {$chatId}", [
                'error' => $this->http->lastError(),
            ]);

        } catch (\Throwable $e) {
            $this->logger->error("Telegram sendMessage exception to chat {$chatId}: {$e->getMessage()}");
        }

        return false;
    }

    /**
     * Compute a deterministic lead confidence score (0-100) from context signals.
     * Used as fallback when the LLM doesn't provide a confidence value.
     *
     * @param array<string, mixed> $data
     */
    private function computeDeterministicConfidence(array $data): int
    {
        $score = 0;

        // ETA given by client → high confidence
        $eta = (int) ($data['eta_minutes'] ?? $data['eta'] ?? 0);
        if ($eta > 0) {
            $score += 40;
        }

        // Strong intent phrases ("ya voy", "quiero ir ya")
        if (!empty($data['interes_fuerte'])) {
            $score += 30;
        }

        // Girl already selected
        if (!empty($data['selected_girl_name'])) {
            $score += 15;
        }

        // Maps/address already sent
        if (!empty($data['maps_sent'])) {
            $score += 10;
        }

        // Prices already discussed
        $yaEnviado = $data['ya_enviado'] ?? [];
        if (is_array($yaEnviado) && in_array('precios', $yaEnviado, true)) {
            $score += 5;
        }

        return min(100, $score);
    }
}
