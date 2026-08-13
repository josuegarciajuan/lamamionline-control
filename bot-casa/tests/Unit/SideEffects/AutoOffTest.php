<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\SideEffects;

use PHPUnit\Framework\TestCase;
use WasapBot\SideEffects\AutoOff;
use WasapBot\Tests\Support\TmpEnv;
use WasapBot\Core\LoggerInterface;

/**
 * Fake logger that records calls — no IO.
 */
final class FakeLoggerAutoOff implements LoggerInterface
{
    public array $infoLogs = [];
    public array $errorLogs = [];

    public function emergency(string $message, array $context = []): void {}
    public function alert(string $message, array $context = []): void {}
    public function critical(string $message, array $context = []): void {}
    public function warning(string $message, array $context = []): void {}
    public function notice(string $message, array $context = []): void {}
    public function debug(string $message, array $context = []): void {}
    public function log(string $level, string $message, array $context = []): void {}

    public function error(string $message, array $context = []): void
    {
        $this->errorLogs[] = $message;
    }

    public function info(string $message, array $context = []): void
    {
        $this->infoLogs[] = $message;
    }
}

/**
 * Unit tests for AutoOff — stops bot when lead is detected.
 */
final class AutoOffTest extends TestCase
{
    private ?TmpEnv $env = null;
    private FakeLoggerAutoOff $logger;

    protected function setUp(): void
    {
        $this->env = new TmpEnv();
        $this->logger = new FakeLoggerAutoOff();
    }

    protected function tearDown(): void
    {
        $this->env?->cleanup();
        unset($this->env);
    }

    // ── Tests ─────────────────────────────────────────────────────────

    public function test_auto_off_with_lead_writes_stop_to_mode_file(): void
    {
        $autoOff = new AutoOff($this->env->config, $this->logger);
        $ctx = [
            'lead_detected' => true,
            'thread_id'     => 'test-thread-01',
        ];

        $autoOff->autoOffIfLead($ctx);

        $modeFile = $this->env->config->get('bot.mode_file', '');
        $this->assertNotSame('', $modeFile);
        $this->assertFileExists($modeFile);
        $content = file_get_contents($modeFile);
        $this->assertSame('stop', $content);
        $this->assertNotEmpty($this->logger->infoLogs);
    }

    public function test_auto_off_without_lead_does_not_write_stop(): void
    {
        $autoOff = new AutoOff($this->env->config, $this->logger);
        // First ensure bot is running
        $this->env->startBot();
        $modeFile = $this->env->config->get('bot.mode_file', '');

        $initialContent = file_get_contents($modeFile);

        $ctx = [
            'lead_detected' => false,
            'thread_id'     => 'test-thread-02',
        ];

        $autoOff->autoOffIfLead($ctx);

        $content = file_get_contents($modeFile);
        $this->assertSame($initialContent, $content);
    }
}
