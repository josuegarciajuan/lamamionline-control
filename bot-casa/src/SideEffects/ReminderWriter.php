<?php

declare(strict_types=1);

namespace WasapBot\SideEffects;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\LoggerInterface;

/**
 * Writes pending reminders to an NDJSON file with atomic append and file locking.
 *
 * Pattern: "Prepare Reminder" and "Write Reminder Pending" nodes from bot.json,
 * plus the flock-based locking pattern from reminder_cron.php.
 */
final class ReminderWriter
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Write a pending reminder record if the conditions are met.
     *
     * Only writes when eta_minutes > 0 and from_phone is not empty.
     *
     * @param array<string, mixed> $ctx  Pipeline context with reminder data.
     */
    public function writeReminder(array $ctx): void
    {
        // Guard: must have a positive ETA and a phone number
        $etaMinutes = (int) ($ctx['eta_minutes'] ?? 0);
        if ($etaMinutes <= 0) {
            return;
        }

        $phone = (string) ($ctx['from_phone'] ?? $ctx['phone'] ?? '');
        $phone = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if ($phone === '') {
            return;
        }

        $filePath = (string) $this->config->get('files.reminders', 'data/reminders_pending.ndjson');

        $this->ensureDirectory(dirname($filePath));

        $record = $this->buildRecord($ctx, $phone, $etaMinutes);
        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($line === false) {
            $this->logger->error('ReminderWriter: failed to encode reminder record to JSON');

            return;
        }

        $line .= "\n";

        // Atomic append with exclusive file lock
        $fp = @fopen($filePath, 'a');
        if ($fp === false) {
            $this->logger->error("ReminderWriter: cannot open reminders file: {$filePath}");

            return;
        }

        try {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $line);
                fflush($fp);
                flock($fp, LOCK_UN);
                $this->logger->info("ReminderWriter: reminder written for phone={$phone}");
            } else {
                $this->logger->error("ReminderWriter: cannot acquire lock on reminders file: {$filePath}");
            }
        } finally {
            fclose($fp);
        }
    }

    /**
     * Build the NDJSON record from pipeline context.
     *
     * @param array<string, mixed> $ctx
     * @param string               $phone
     * @param int                  $etaMinutes
     * @return array<string, mixed>
     */
    private function buildRecord(array $ctx, string $phone, int $etaMinutes): array
    {
        $wahaPort = (int) ($ctx['waha_port'] ?? 3000);

        return [
            'phone'         => $phone,
            'from_phone'    => (string) ($ctx['from_phone'] ?? $ctx['phone'] ?? ''),
            'chat_id'       => $phone . '@c.us',
            'eta_minutes'   => $etaMinutes,
            'line_label'    => (string) ($ctx['line_label'] ?? ''),
            'waha_port'     => $wahaPort,
            'waha_session'  => (string) ($ctx['waha_session'] ?? 'default'),
            'waha_base_url' => (string) ($ctx['waha_base_url'] ?? $this->buildBaseUrl($wahaPort)),
            'thread_id'     => (string) ($ctx['thread_id'] ?? ''),
            'ts_created'    => $this->nowIso8601(),
            'sent'          => false,
        ];
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     */
    private function ensureDirectory(string $dir): void
    {
        if ($dir !== '' && !is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    /**
     * Return the current timestamp in ISO 8601 format.
     */
    private function buildBaseUrl(int $wahaPort): string
    {
        $baseIp = (string) $this->config->get('waha.base_ip', '127.0.0.1');
        return 'http://' . $baseIp . ':' . $wahaPort;
    }

    private function nowIso8601(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
