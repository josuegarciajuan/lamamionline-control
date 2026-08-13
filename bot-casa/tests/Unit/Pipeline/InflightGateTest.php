<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\InflightGate;
use WasapBot\Tests\Support\TmpEnv;

/**
 * InflightGate — intercepts messages that arrive while the bot is
 * already processing a previous message from the same phone.
 */
final class InflightGateTest extends TestCase
{
    private TmpEnv $env;
    private string $lockDir;

    protected function setUp(): void
    {
        $this->env = new TmpEnv();
        $this->lockDir = (string) $this->env->config->get('files.lock_dir', '') . '/inflight';
    }

    protected function tearDown(): void
    {
        $this->env->cleanup();
    }

    /** No inflight lock — gate passes ctx through. */
    public function test_no_active_lock_returns_ctx(): void
    {
        $gate = new InflightGate($this->env->config);
        $ctx = [
            'from_phone' => '34600123456',
            'line_last9' => '000000000',
            'message_text' => 'hola',
            'timestamp' => (string) time(),
            'thread_id' => '000000000_34600123456',
        ];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame('hola', $result['message_text'] ?? null);
    }

    /** Active lock for same phone+line — enqueues message and returns null. */
    public function test_active_lock_enqueues_and_returns_null(): void
    {
        // Create an active lock
        InflightGate::createLock($this->lockDir, '34600123456', '000000000');

        $gate = new InflightGate($this->env->config);
        $ctx = [
            'from_phone'   => '34600123456',
            'line_last9'   => '000000000',
            'message_text' => 'segundo mensaje',
            'timestamp'    => (string) time(),
            'thread_id'    => '000000000_34600123456',
        ];

        $result = $gate->process($ctx);

        $this->assertNull($result);

        // Verify pending file was created
        $pendingFile = $this->lockDir . '/34600123456_000000000_pending.json';
        $this->assertFileExists($pendingFile);

        // Clean up
        InflightGate::cleanup($this->lockDir, '34600123456', '000000000');
    }

    /** Stale lock (expired TTL > 60s) — lock is ignored, ctx passes through. */
    public function test_stale_lock_is_ignored(): void
    {
        // Create lock file with old modification time
        $lockFile = $this->lockDir . '/34600123456_000000000.lock';
        @mkdir($this->lockDir, 0755, true);
        file_put_contents($lockFile, (string) time());
        touch($lockFile, time() - 61); // Simulate old lock

        $gate = new InflightGate($this->env->config);
        $ctx = [
            'from_phone'   => '34600123456',
            'line_last9'   => '000000000',
            'message_text' => 'hola tras stale',
            'timestamp'    => (string) time(),
            'thread_id'    => '000000000_34600123456',
        ];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame('hola tras stale', $result['message_text'] ?? null);

        // Lock should have been deleted
        $this->assertFileDoesNotExist($lockFile);
    }

    /** drainPending: writes pending messages, drains them, verifies recovery and cleanup. */
    public function test_drain_pending_recovers_and_cleans(): void
    {
        // Create lock + enqueue messages by simulating an inflight arrival
        InflightGate::createLock($this->lockDir, '34600123456', '000000000');

        $gate = new InflightGate($this->env->config);

        // Enqueue first message
        $gate->process([
            'from_phone'   => '34600123456',
            'line_last9'   => '000000000',
            'message_text' => 'msg pendiente 1',
            'timestamp'    => (string) time(),
            'thread_id'    => 'thread_a',
        ]);

        // Enqueue second message
        $gate->process([
            'from_phone'   => '34600123456',
            'line_last9'   => '000000000',
            'message_text' => 'msg pendiente 2',
            'timestamp'    => (string) time(),
            'thread_id'    => 'thread_b',
        ]);

        // Drain pending
        $drained = InflightGate::drainPending($this->lockDir, '34600123456', '000000000');

        $this->assertCount(2, $drained);
        $this->assertSame('msg pendiente 1', $drained[0]['message_text'] ?? null);
        $this->assertSame('msg pendiente 2', $drained[1]['message_text'] ?? null);

        // Pending file should be gone after drain
        $pendingFile = $this->lockDir . '/34600123456_000000000_pending.json';
        $this->assertFileDoesNotExist($pendingFile);

        // Clean up lock
        @unlink($this->lockDir . '/34600123456_000000000.lock');
    }

    /** compositeKey with lineLast9 vs without. */
    public function test_composite_key_with_and_without_line(): void
    {
        // Create lock with lineLast9
        InflightGate::createLock($this->lockDir, '34600123456', '000000000');
        $this->assertFileExists($this->lockDir . '/34600123456_000000000.lock');

        // Create lock without lineLast9
        InflightGate::createLock($this->lockDir, '999999999');
        $this->assertFileExists($this->lockDir . '/999999999.lock');

        // Clean up
        InflightGate::cleanup($this->lockDir, '34600123456', '000000000');
        InflightGate::cleanup($this->lockDir, '999999999');

        $this->assertFileDoesNotExist($this->lockDir . '/34600123456_000000000.lock');
        $this->assertFileDoesNotExist($this->lockDir . '/999999999.lock');
    }

    /** cleanup removes both lock and pending files. */
    public function test_cleanup_removes_lock_and_pending(): void
    {
        // Create lock
        InflightGate::createLock($this->lockDir, '34600123456', '000000000');

        // Create pending file manually
        $pendingFile = $this->lockDir . '/34600123456_000000000_pending.json';
        file_put_contents($pendingFile, json_encode([
            ['message_text' => 'test', 'ts' => (string) time(), 'thread_id' => 't1'],
        ]));

        $this->assertFileExists($pendingFile);

        // Cleanup
        InflightGate::cleanup($this->lockDir, '34600123456', '000000000');

        $this->assertFileDoesNotExist($this->lockDir . '/34600123456_000000000.lock');
        $this->assertFileDoesNotExist($pendingFile);
    }

    /** No from_phone — pass through (can't determine). */
    public function test_no_from_phone_passes_through(): void
    {
        $gate = new InflightGate($this->env->config);
        $ctx = ['message_text' => 'sin telefono'];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame('sin telefono', $result['message_text'] ?? null);
    }

    /** Gate name */
    public function test_gate_name_is_correct(): void
    {
        $gate = new InflightGate($this->env->config);
        $this->assertSame('InflightGate', $gate->name());
    }
}
