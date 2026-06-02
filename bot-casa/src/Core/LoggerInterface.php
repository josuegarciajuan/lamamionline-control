<?php

declare(strict_types=1);

namespace WasapBot\Core;

use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Psr\Log\LogLevel;

/**
 * Logger contract — structured logging with levels and timestamps.
 * Minimal PSR-3 compatible interface.
 */
interface LoggerInterface
{
    public function emergency(string $message, array $context = []): void;
    public function alert(string $message, array $context = []): void;
    public function critical(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function notice(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function debug(string $message, array $context = []): void;
    public function log(string $level, string $message, array $context = []): void;
}
