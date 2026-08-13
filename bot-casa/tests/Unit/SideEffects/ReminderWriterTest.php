<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\SideEffects;

use PHPUnit\Framework\TestCase;
use WasapBot\SideEffects\ReminderWriter;
use WasapBot\Tests\Support\TmpEnv;
use WasapBot\Core\LoggerInterface;

/**
 * Fake logger for ReminderWriter tests.
 */
final class FakeLoggerReminderWriter implements LoggerInterface
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
 * Unit tests for ReminderWriter — writes pending reminders to NDJSON.
 */
final class ReminderWriterTest extends TestCase
{
    private ?TmpEnv $env = null;
    private FakeLoggerReminderWriter $logger;

    protected function setUp(): void
    {
        $this->env = new TmpEnv();
        $this->logger = new FakeLoggerReminderWriter();
    }

    protected function tearDown(): void
    {
        $this->env?->cleanup();
        unset($this->env);
    }

    // ── Tests ─────────────────────────────────────────────────────────

    public function test_write_reminder_creates_record_file(): void
    {
        $writer = new ReminderWriter($this->env->config, $this->logger);
        $ctx = [
            'eta_minutes' => 30,
            'from_phone'  => '34600123456',
            'thread_id'   => 'thread-123',
            'waha_port'   => 3000,
            'line_label'  => 'test-line',
        ];

        $writer->writeReminder($ctx);

        $remindersPath = $this->env->config->get('files.reminders', '');
        $this->assertFileExists($remindersPath);

        $content = file_get_contents($remindersPath);
        $this->assertNotEmpty($content);

        $record = json_decode(trim($content), true);
        $this->assertIsArray($record);
    }

    public function test_write_reminder_has_correct_format(): void
    {
        $writer = new ReminderWriter($this->env->config, $this->logger);
        $ctx = [
            'eta_minutes' => 30,
            'from_phone'  => '34600123456',
            'phone'       => '34600123456',
            'thread_id'   => 'thread-123',
            'waha_port'   => 3000,
            'line_label'  => 'test-line',
            'waha_session' => 'default',
        ];

        $writer->writeReminder($ctx);

        $remindersPath = $this->env->config->get('files.reminders', '');
        $content = file_get_contents($remindersPath);
        $record = json_decode(trim($content), true);

        $this->assertSame('34600123456', $record['phone']);
        $this->assertSame(30, $record['eta_minutes']);
        $this->assertSame('34600123456@c.us', $record['chat_id']);
        $this->assertSame('thread-123', $record['thread_id']);
        $this->assertFalse($record['sent']);
        $this->assertArrayHasKey('ts_created', $record);
    }

    public function test_write_reminder_without_eta_does_nothing(): void
    {
        $writer = new ReminderWriter($this->env->config, $this->logger);
        $ctx = [
            'eta_minutes' => 0,
            'from_phone'  => '34600123456',
        ];

        $writer->writeReminder($ctx);

        $remindersPath = $this->env->config->get('files.reminders', '');
        $this->assertFileDoesNotExist($remindersPath);
    }

    public function test_write_reminder_without_phone_does_nothing(): void
    {
        $writer = new ReminderWriter($this->env->config, $this->logger);
        $ctx = [
            'eta_minutes' => 15,
            'from_phone'  => '',
        ];

        $writer->writeReminder($ctx);

        $remindersPath = $this->env->config->get('files.reminders', '');
        $this->assertFileDoesNotExist($remindersPath);
    }
}
