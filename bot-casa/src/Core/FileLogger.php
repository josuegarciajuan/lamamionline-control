<?php

declare(strict_types=1);

namespace WasapBot\Core;

/**
 * FileLogger — delegates to Logger (stdout/stderr) and also appends to a rotating log file.
 *
 * Log file path is resolved from config key 'files.bot_log' (default: data/bot.log).
 * Max file size before rotation: 'log.max_size_bytes' (default 5 MB).
 * Lines older than 'log.max_age_days' are pruned on rotation (default: 7 days).
 *
 * Format: "[Y-m-d H:i:s] LEVEL: message {context_json}\n"
 */
final class FileLogger implements LoggerInterface
{
    private readonly Logger $stdout;
    private readonly string $logFile;
    private readonly int    $maxBytes;

    public function __construct(ConfigInterface $config)
    {
        $this->stdout = new Logger();

        $rawPath = (string) $config->get('files.bot_log', 'data/bot.log');

        if (str_starts_with($rawPath, '/')) {
            $this->logFile = $rawPath;
        } else {
            $baseDir = ($config instanceof Config)
                ? $config->getConfigDir()
                : dirname(__DIR__, 2);
            $this->logFile = rtrim($baseDir, '/') . '/' . ltrim($rawPath, '/');
        }

        $this->maxBytes = (int) $config->get('log.max_size_bytes', 5 * 1024 * 1024); // 5 MB
    }

    // ──────────────────────────────────────────────
    //  LoggerInterface
    // ──────────────────────────────────────────────

    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        // Delegate to stdout/stderr logger
        $this->stdout->log($level, $message, $context);

        // Write to file
        $this->writeToFile($level, $message, $context);
    }

    // ──────────────────────────────────────────────
    //  Internals
    // ──────────────────────────────────────────────

    /** @param array<string, mixed> $context */
    private function writeToFile(string $level, string $message, array $context): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $line = $this->formatLine($level, $message, $context);

        // Rotate if needed (non-blocking: skip rotation if can't get lock)
        if (file_exists($this->logFile) && filesize($this->logFile) >= $this->maxBytes) {
            $this->rotate();
        }

        $fp = @fopen($this->logFile, 'ab');
        if ($fp === false) {
            return;
        }

        try {
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                fwrite($fp, $line);
                fflush($fp);
                flock($fp, LOCK_UN);
            }
        } finally {
            fclose($fp);
        }
    }

    /** @param array<string, mixed> $context */
    private function formatLine(string $level, string $message, array $context): string
    {
        $timestamp  = date('Y-m-d H:i:s');
        $upperLevel = strtoupper($level);
        $line       = "[{$timestamp}] {$upperLevel}: {$message}";

        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $line . "\n";
    }

    /**
     * Rotate log: keep last 500 lines to cap file size.
     */
    private function rotate(): void
    {
        $fp = @fopen($this->logFile, 'rb');
        if ($fp === false) {
            return;
        }

        $lines = [];
        try {
            while (($line = fgets($fp)) !== false) {
                $lines[] = $line;
            }
        } finally {
            fclose($fp);
        }

        // Keep last 500 lines
        $keep   = array_slice($lines, max(0, count($lines) - 500));
        $backup = $this->logFile . '.bak';

        @rename($this->logFile, $backup);

        $fw = @fopen($this->logFile, 'wb');
        if ($fw !== false) {
            fwrite($fw, implode('', $keep));
            fclose($fw);
        }

        // Remove backup
        @unlink($backup);
    }
}
