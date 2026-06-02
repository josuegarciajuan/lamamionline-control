<?php

declare(strict_types=1);

namespace WasapBot\SideEffects;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\LoggerInterface;

/**
 * Automatically stops the bot when a lead is detected.
 *
 * Pattern: "Auto-Off Lead Trigger" and "Write Bot OFF" nodes from bot.json.
 *
 * When auto_off_on_lead is true and a lead is confirmed, this class writes
 * "stop" to the bot mode file, which causes the bot to shut down.
 */
final class AutoOff
{
    private LeadDetector $detector;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
    ) {
        $this->detector = new LeadDetector();
    }

    /**
     * Check pipeline context and turn off the bot if a lead was detected.
     *
     * Checks for the __auto_off_triggered flag (set by upstream logic,
     * mirroring the "Auto-Off Lead Trigger" n8n node) OR lead_detected
     * directly in the context array.
     *
     * @param array<string, mixed> $ctx  Pipeline context.
     */
    public function autoOffIfLead(array $ctx): void
    {
        // Skip if auto-off is disabled in config
        $autoOffEnabled = (bool) $this->config->get('bot.auto_off_on_lead', true);
        if (!$autoOffEnabled) {
            return;
        }

        // Check if already triggered OR lead is detected
        $triggered = !empty($ctx['__auto_off_triggered']);
        $isLead = $this->detector->isLead($ctx);

        if (!$triggered && !$isLead) {
            return;
        }

        $modeFile = (string) $this->config->get('bot.mode_file', 'data/.bot_mode');
        $dir = dirname($modeFile);

        $this->ensureDirectory($dir);

        $written = @file_put_contents($modeFile, 'stop', LOCK_EX);

        if ($written !== false) {
            $this->logger->info("AutoOff: bot mode set to STOP — lead detected");
        } else {
            $this->logger->error("AutoOff: failed to write 'stop' to mode file: {$modeFile}");
        }
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
}
