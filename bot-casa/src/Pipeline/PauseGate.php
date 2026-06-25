<?php
/**
 * PauseGate — Per-conversation bot pause gate.
 *
 * Position: after BotModeGate, before RoutingGate.
 * Reads data/paused_threads.ndjson and halts the pipeline
 * if the incoming thread_id is paused.
 *
 * Also checks for cancel files: if a thread is paused while
 * the bot is mid-generation, a cancel file at
 * data/cancel/{thread_id_hash}.cancel will be detected
 * before the message is sent.
 */
declare(strict_types=1);

namespace WasapBot\Pipeline;

final class PauseGate implements PipelineStageInterface
{
    private string $pausedFile;
    private string $cancelDir;

    public function __construct(
        private readonly ?\WasapBot\Core\ConfigInterface $config = null,
        ?\WasapBot\Core\LoggerInterface $logger = null,
    ) {
        $rootDir = defined('WASAPBOT_ROOT') ? WASAPBOT_ROOT : dirname(__DIR__, 2);
        // Use per-user paused file when config provides it (multi-user isolation).
        // Fall back to shared data/paused_threads.ndjson for legacy/admin.
        $pausedFromConfig = ($this->config !== null)
            ? (string) $this->config->get('files.paused_threads', '')
            : '';
        if ($pausedFromConfig !== '') {
            // Normalize to absolute — eliminates CWD dependency that caused
            // PauseGate to silently read from the wrong file for admin (userId=1).
            // For multi-user: config value is already absolute (resolved by bootstrap).
            // For admin/legacy: config value is relative → prepend rootDir.
            $this->pausedFile = str_starts_with($pausedFromConfig, '/')
                ? $pausedFromConfig
                : $rootDir . '/' . ltrim($pausedFromConfig, '/');
        } else {
            $this->pausedFile = $rootDir . '/data/paused_threads.ndjson';
        }
        // Cancel dir is always shared (temporary signal, not conversation data)
        $this->cancelDir  = $rootDir . '/data/cancel';
        if (!is_dir($this->cancelDir)) {
            @mkdir($this->cancelDir, 0700, true);
        }
    }

    public function name(): string
    {
        return 'PauseGate';
    }

    public function process(array $ctx): ?array
    {
        // Try pre-existing thread_id (from webhook reprocess or earlier stage)
        $threadId = (string) ($ctx['thread_id'] ?? $ctx['__thread_id'] ?? '');

        // If not available, construct from line_last9 + from_phone
        // (available after RoutingGate + MessageExtractor in the pipeline)
        if ($threadId === '') {
            $lineLast9 = (string) ($ctx['line_last9'] ?? '');
            $fromPhone = (string) ($ctx['from_phone'] ?? '');
            if ($fromPhone !== '') {
                // Match ContextAssembler format: when line_last9 is empty, just use phone (no leading underscore)
                $threadId = $lineLast9 !== '' ? ($lineLast9 . '_' . $fromPhone) : $fromPhone;
            }
        }

        if ($threadId === '') {
            // No thread context yet — pass through
            return $ctx;
        }

        // Store the constructed thread_id for downstream stages
        if (empty($ctx['thread_id']) && empty($ctx['__thread_id'])) {
            $ctx['__thread_id'] = $threadId;
        }

        // Check if this thread is paused
        if ($this->isThreadPaused($threadId)) {
            $ctx['_pause_halted'] = true;
            $ctx['_pause_reason'] = 'thread_paused';
            return null; // Halt pipeline
        }

        return $ctx;
    }

    /**
     * Check if a specific thread_id is paused.
     */
    public function isThreadPaused(string $threadId): bool
    {
        $paused = $this->readPausedThreads();
        return in_array($threadId, $paused, true);
    }

    /**
     * Check if a cancel file exists for a thread.
     * This is set when the user pauses mid-generation.
     */
    public function hasCancelRequest(string $threadId): bool
    {
        $hash = $this->threadHash($threadId);
        return file_exists($this->cancelDir . '/' . $hash . '.cancel');
    }

    /**
     * Remove a cancel file (called after we've honoured it).
     */
    public function clearCancelRequest(string $threadId): void
    {
        $hash = $this->threadHash($threadId);
        $path = $this->cancelDir . '/' . $hash . '.cancel';
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    /**
     * Reads (and caches) the paused threads list from NDJSON.
     */
    private function readPausedThreads(): array
    {
        if (!file_exists($this->pausedFile)) {
            return [];
        }
        $lines = @file($this->pausedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return [];
        }
        $threads = [];
        foreach ($lines as $line) {
            $rec = json_decode($line, true);
            if (is_array($rec) && !empty($rec['thread_id'])) {
                $threads[] = (string) $rec['thread_id'];
            }
        }
        return $threads;
    }

    /**
     * Generate a filesystem-safe hash of a thread ID for cancel files.
     */
    private function threadHash(string $threadId): string
    {
        return hash('sha256', $threadId);
    }
}
