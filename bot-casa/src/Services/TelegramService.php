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

    /** Dedup window in seconds: skip alert if one was already sent for the same phone within this window. */
    private readonly int $dedupWindowSec;

    /** Directory for Telegram alert dedup lock files. */
    private readonly string $dedupDir;

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

        $this->dedupWindowSec = (int) $this->config->get('telegram.alert_dedup_window_sec', 21600);

        $baseDataDir = (string) $this->config->get('files.base_data_dir', 'data');
        $this->dedupDir = rtrim($baseDataDir, '/') . '/locks/telegram_alert';
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
    /** @param array<string, mixed> $leadData */
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

        $threadId  = (string) ($leadData['thread_id'] ?? '');

        // ── Dedup: skip if alert was already sent for this THREAD within the dedup window ──
        // Uses thread_id (not phone) to prevent multiple alerts for the same conversation.
        // Default window is 6 hours (21600s) via telegram.alert_dedup_window_sec.
        // Falls back to phone-based dedup if thread_id is empty.
        $dedupKey = ($threadId !== '') ? $threadId : $phone;
        if ($this->isDuplicateAlert($dedupKey)) {
            $this->logger->info("Telegram alert dedup: skipping duplicate for thread {$dedupKey} (last alert < {$this->dedupWindowSec}s ago)");
            return;
        }
        $lineLabel = (string) ($leadData['line_label'] ?? '');
        $lineNotas = (string) ($leadData['line_notas'] ?? '');
        $lineLast9 = (string) ($leadData['line_last9'] ?? '');
        $conf      = $leadData['lead_confidence'] ?? $leadData['confidence'] ?? 0;
        $eta       = $leadData['eta_minutes'] ?? $leadData['eta'] ?? 0;
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

        // ── Add lead signals for transparency ─────────────────────
        $leadSignals = $leadData['lead_signals'] ?? [];
        if (is_array($leadSignals) && $leadSignals !== [] && !in_array('none', $leadSignals, true)) {
            $signalsMap = [
                'eta_explicit'       => '⏱ ETA explícita',
                'eta_implicit'       => '🗣 Intención de venir',
                'coming_soon'        => '🏃 Viene ya',
                'selected_girl'      => '👤 Chica elegida',
                'maps_requested'     => '📍 Pidió ubicación',
                'maps_sent'          => '🗺 Ubicación enviada',
                'price_asked'        => '💶 Preguntó precios',
                'urgent_tone'        => '⚡ Tono urgente',
                'recurring_client'   => '🔄 Cliente recurrente',
                'coordination_phase' => '📋 Fase coordinación',
            ];
            $signalLabels = [];
            foreach ($leadSignals as $s) {
                $signalLabels[] = $signalsMap[$s] ?? $s;
            }
            $text .= "🔍 Señales: " . implode(', ', $signalLabels) . "\n";
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

        // ── Record alert timestamp for dedup ─────────────────────────
        $this->touchAlertSent($dedupKey);
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

        // Maps/address already sent — NOT a reliable signal by itself.
        // Only count if combined with strong intent (eta or interes_fuerte).
        if (!empty($data['maps_sent']) && (!empty($data['interes_fuerte']) || (($data['eta_minutes'] ?? $data['eta'] ?? 0) > 0))) {
            $score += 10;
        }

        // Prices already discussed
        $yaEnviado = $data['ya_enviado'] ?? [];
        if (is_array($yaEnviado) && in_array('precios', $yaEnviado, true)) {
            $score += 5;
        }

        return min(100, $score);
    }

    /**
     * Check whether a Telegram alert was already sent for this key (thread_id or phone) within the dedup window.
     *
     * Uses a lock file in data/locks/telegram_alert/{key_hash}.alert.
     * If the file exists and its mtime is within dedupWindowSec, it's a duplicate.
     */
    private function isDuplicateAlert(string $dedupKey): bool
    {
        if ($dedupKey === '' || $dedupKey === '?') {
            return false; // cannot dedup without a key
        }

        $lockFile = $this->dedupAlertPath($dedupKey);

        if (!is_file($lockFile)) {
            return false;
        }

        $mtime = @filemtime($lockFile);
        if ($mtime === false) {
            return false; // can't read mtime, allow through
        }

        $elapsed = time() - $mtime;
        return $elapsed < $this->dedupWindowSec;
    }

    /**
     * Record that a Telegram alert was sent for this key.
     *
     * Creates/updates a lock file, ensuring the directory exists.
     */
    private function touchAlertSent(string $dedupKey): void
    {
        if ($dedupKey === '' || $dedupKey === '?') {
            return;
        }

        $lockFile = $this->dedupAlertPath($dedupKey);
        $dir = dirname($lockFile);

        $this->ensureDir($dir);

        @touch($lockFile);
    }

    /**
     * Resolve the lock file path for a given dedup key.
     *
     * Uses a hash of the key to avoid filesystem path issues with special characters.
     */
    private function dedupAlertPath(string $dedupKey): string
    {
        // md5 of the key + prefix for grep-friendliness
        $safe = 'alert_' . md5($dedupKey);
        return $this->dedupDir . '/' . $safe . '.alert';
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     */
    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }
}
