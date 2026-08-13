<?php

declare(strict_types=1);

namespace WasapBot\Core;

/**
 * Minimalist PSR-3 compatible logger writing to stdout/stderr with timestamps.
 *
 * Format: "[Y-m-d H:i:s] LEVEL: message {context_json}"
 *   - STDERR for: emergency, alert, critical, error
 *   - STDOUT for: warning, notice, info, debug
 *   - context JSON only printed when context array is non-empty
 *   - flushes after every message
 *
 * Zero external dependencies. Uses built-in fwrite + fflush.
 */
final class Logger implements LoggerInterface
{
    // ──────────────────────────────────────────────
    //  Level → stream mapping
    // ──────────────────────────────────────────────

    private const array STDERR_LEVELS = [
        'emergency' => true,
        'alert'     => true,
        'critical'  => true,
        'error'     => true,
    ];

    // ──────────────────────────────────────────────
    //  PSR-3 convenience methods
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

    // ──────────────────────────────────────────────
    //  Core log method
    // ──────────────────────────────────────────────

    public function log(string $level, string $message, array $context = []): void
    {
        $line = $this->formatLine($level, $message, $context);
        $stream = $this->chooseStream($level);

        fwrite($stream, $line);
        fflush($stream);
    }

    // ──────────────────────────────────────────────
    //  Internals
    // ──────────────────────────────────────────────

    /**
     * Builds the formatted log line including trailing newline.
     */
    private function formatLine(string $level, string $message, array $context): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $upperLevel = strtoupper($level);

        $line = "[{$timestamp}] {$upperLevel}: {$message}";

        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $line . "\n";
    }

    /**
     * Returns the appropriate stream resource for the given log level.
     *
     * STDOUT/STDERR are not always available under PHP-FPM web context,
     * so we fall back to php://stderr and php://stdout respectively.
     *
     * @return resource
     */
    private function chooseStream(string $level): mixed
    {
        if (isset(self::STDERR_LEVELS[$level])) {
            return defined('STDERR') ? STDERR : fopen('php://stderr', 'w');
        }
        return defined('STDOUT') ? STDOUT : fopen('php://stdout', 'w');
    }
}
