<?php

declare(strict_types=1);

namespace WasapBot\SideEffects;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\LoggerInterface;

/**
 * Logs detected leads to an NDJSON file with atomic append and file locking.
 *
 * Pattern: "Append Lead Phone Log (/data/leads.ndjson)" node from bot.json,
 * combined with the flock-based locking from lead_followup_cron.php.
 */
final class LeadLogger
{
    private LeadDetector $detector;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
    ) {
        $this->detector = new LeadDetector();
    }

    /**
     * Log a lead to the leads NDJSON file if a lead is confirmed.
     *
     * The context array is expected to contain values from the pipeline
     * (similar to what "Gate Lead → Telegram" enriches the item with).
     *
     * @param array<string, mixed> $ctx  Pipeline context with lead data.
     */
    public function logLead(array $ctx): void
    {
        // Only log if a lead is confirmed
        if (!$this->detector->isLead($ctx)) {
            return;
        }

        $filePath = (string) $this->config->get('files.leads', 'data/leads.ndjson');

        $this->ensureDirectory(dirname($filePath));

        $record = $this->buildRecord($ctx);
        $line = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($line === false) {
            $this->logger->error('LeadLogger: failed to encode lead record to JSON');

            return;
        }

        $line .= "\n";

        // Atomic append with exclusive file lock
        $fp = @fopen($filePath, 'a');
        if ($fp === false) {
            $this->logger->error("LeadLogger: cannot open leads file: {$filePath}");

            return;
        }

        try {
            if (flock($fp, LOCK_EX)) {
                fwrite($fp, $line);
                fflush($fp);
                flock($fp, LOCK_UN);
                $this->logger->info("LeadLogger: lead logged for phone={$record['phone']}");
            } else {
                $this->logger->error("LeadLogger: cannot acquire lock on leads file: {$filePath}");
            }
        } finally {
            fclose($fp);
        }
    }

    /**
     * Build the NDJSON record from pipeline context.
     *
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    private function buildRecord(array $ctx): array
    {
        $phone = (string) ($ctx['from_phone'] ?? $ctx['phone'] ?? '');
        $phone = preg_replace('/[^0-9]/', '', $phone) ?? '';

        $eta = isset($ctx['eta_minutes'])
            ? (int) $ctx['eta_minutes']
            : $this->detector->etaMinutes($ctx);

        $conf = isset($ctx['lead_confidence'])
            ? (float) $ctx['lead_confidence']
            : $this->detector->confidence($ctx);

        return [
            'ts'                 => $this->nowIso8601(),
            'phone'              => $phone,
            'eta_minutes'        => $eta,
            'lead_confidence'    => $conf,
            'thread_id'          => (string) ($ctx['thread_id'] ?? ''),
            'line_label'         => (string) ($ctx['line_label'] ?? ''),
            'waha_port'          => (int) ($ctx['waha_port'] ?? 0),
            'waha_base_url'      => (string) ($ctx['waha_base_url'] ?? ''),
            'waha_session'       => (string) ($ctx['waha_session'] ?? 'default'),
            'user_message'       => (string) ($ctx['user_message'] ?? ''),
            'bot_reply'          => (string) ($ctx['output_text'] ?? $ctx['bot_reply'] ?? ''),
            'selected_girl_name' => (string) ($ctx['selected_girl_name'] ?? ''),
            'last_followup_ts'   => null,
            'cancelled'          => false,
        ];
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     */
    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    /**
     * Return the current timestamp in ISO 8601 format.
     */
    private function nowIso8601(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
