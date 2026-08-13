<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

use WasapBot\Core\ConfigInterface;

/**
 * BotModeGate — checks whether the bot is in "start" or "stop" mode.
 *
 * Reads the bot mode file (configured as 'bot.mode_file', typically 'data/.bot_mode').
 *
 *  - If the file contains 'stop'  → pipeline halts (returns null).
 *  - If the file contains 'start' → pipeline continues.
 *  - If the file is missing or unreadable → continue (fail-open: assume start).
 *
 * Pattern: based on "IF Bot Mode Start?" node in bot.json (~lines 1262).
 *
 * This gate allows the admin panel to turn the bot off/on at runtime
 * without restarting the process — just write 'stop' or 'start' to the file.
 */
final readonly class BotModeGate implements PipelineStageInterface
{
    public function __construct(
        private ConfigInterface $config,
    ) {}

    public function name(): string
    {
        return 'BotModeGate';
    }

    /**
     * @param  array<string, mixed> $ctx
     * @return array<string, mixed>|null
     */
    /**
     * Validate that a file path is within the allowed data directory.
     * Prevents path traversal attacks via config injection.
     */
    private function validatePath(string $path, string $rootDir): bool
    {
        $resolved = realpath($path);
        if ($resolved === false) {
            // File doesn't exist yet — resolve parent directory
            $parent = realpath(dirname($path));
            if ($parent === false) {
                return false;
            }
            $resolved = $parent . '/' . basename($path);
        }
        $dataDir = realpath($rootDir . '/data');
        return $dataDir !== false && str_starts_with($resolved, $dataDir);
    }

    public function process(array $ctx): ?array
    {
        try {
            $modeFile = (string) $this->config->get('bot.mode_file', 'data/.bot_mode');

            if ($modeFile === '') {
                $ctx['bot_mode'] = 'start';
                return $ctx;
            }

            // Prevent path traversal: only allow files within data/
            $rootDir = defined('WASAPBOT_ROOT') ? WASAPBOT_ROOT : dirname(__DIR__, 2);
            if (!$this->validatePath($rootDir . '/' . ltrim($modeFile, '/'), $rootDir)) {
                $ctx['bot_mode'] = 'start';
                return $ctx;
            }

            $resolvedPath = $rootDir . '/' . ltrim($modeFile, '/');

            if (!file_exists($resolvedPath) || !is_readable($resolvedPath)) {
                $ctx['bot_mode'] = 'start';
                return $ctx;
            }

            $content = @file_get_contents($resolvedPath);
            if ($content === false) {
                $ctx['bot_mode'] = 'start';
                return $ctx;
            }

            $mode = mb_strtolower(trim($content));
            $ctx['bot_mode'] = $mode;

            if ($mode === 'stop') {
                return null;
            }

            // ── Per-thread lead lock: if this thread already had a lead detected ──
            // (even if bot was restarted back to "start"), block processing
            // to prevent re-triggering alerts/logs for the same conversation.
            $threadId = (string) ($ctx['thread_id'] ?? $ctx['__thread_id'] ?? '');
            if ($threadId !== '') {
                $baseDataDir = (string) $this->config->get('files.base_data_dir', 'data');
                $leadLockFile = rtrim($rootDir, '/') . '/' . rtrim($baseDataDir, '/')
                    . '/locks/lead_detected/lead_' . md5($threadId) . '.lock';
                if (is_file($leadLockFile)) {
                    // Lead already detected for this thread — block to prevent re-trigger
                    $ctx['bot_mode'] = 'stop_thread_lead';
                    return null;
                }
            }

            return $ctx;
        } catch (\Throwable) {
            return $ctx;
        }
    }
}
