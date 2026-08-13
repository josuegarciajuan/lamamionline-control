<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\PauseGate;
use WasapBot\Tests\Support\TmpEnv;

/**
 * PauseGate — per-conversation bot pause gate.
 *
 * Regression coverage for: isThreadPaused working independently of CWD.
 */
final class PauseGateTest extends TestCase
{
    private TmpEnv $env;

    protected function setUp(): void
    {
        $this->env = new TmpEnv();
    }

    protected function tearDown(): void
    {
        $this->env->cleanup();
    }

    /** Thread not paused — gate passes ctx through. */
    public function test_thread_not_paused_passes_through(): void
    {
        $gate = new PauseGate($this->env->config);
        $ctx = [
            'from_phone' => '123456789',
            'line_last9' => '000000000',
        ];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertArrayNotHasKey('_pause_halted', $result ?? []);
    }

    /** Thread is paused — gate halts pipeline (returns null). */
    public function test_thread_paused_returns_null(): void
    {
        $threadId = '000000000_123456789';
        $this->env->pauseThread($threadId);

        $gate = new PauseGate($this->env->config);
        $ctx = [
            'from_phone' => '123456789',
            'line_last9' => '000000000',
        ];

        $result = $gate->process($ctx);

        $this->assertNull($result);
    }

    /** isThreadPaused returns true for a paused thread. */
    public function test_is_thread_paused_returns_true(): void
    {
        $threadId = '000000000_987654321';
        $this->env->pauseThread($threadId);

        $gate = new PauseGate($this->env->config);
        $this->assertTrue($gate->isThreadPaused($threadId));
    }

    /** isThreadPaused returns false for an unpaused thread. */
    public function test_is_thread_paused_returns_false(): void
    {
        $gate = new PauseGate($this->env->config);
        $this->assertFalse($gate->isThreadPaused('000000000_nonexistent'));
    }

    /**
     * Regression: isThreadPaused works regardless of the original CWD.
     * The old bug was that PauseGate resolved paths relative to CWD.
     * With the fix, paths are absolute, so this is independent.
     */
    public function test_is_thread_paused_independent_of_cwd(): void
    {
        $threadId = '000000000_regression';
        $this->env->pauseThread($threadId);

        $gate = new PauseGate($this->env->config);

        // Change CWD to /tmp (simulating the old bug scenario)
        $originalCwd = getcwd();
        chdir('/tmp');

        try {
            $this->assertTrue(
                $gate->isThreadPaused($threadId),
                'isThreadPaused should return true regardless of CWD'
            );
        } finally {
            chdir($originalCwd);
        }
    }

    /** Thread_id from ctx is used directly when present (line_last9 + from_phone not needed). */
    public function test_uses_existing_thread_id(): void
    {
        $threadId = '000000000_111222333';
        $this->env->pauseThread($threadId);

        $gate = new PauseGate($this->env->config);
        $ctx = ['thread_id' => $threadId];

        $result = $gate->process($ctx);

        $this->assertNull($result); // Thread is paused, should halt
    }

    /** __thread_id is also recognized for backwards compatibility. */
    public function test_uses_underscore_thread_id(): void
    {
        $threadId = '000000000_444555666';
        $this->env->pauseThread($threadId);

        $gate = new PauseGate($this->env->config);
        $ctx = ['__thread_id' => $threadId];

        $result = $gate->process($ctx);

        $this->assertNull($result);
    }

    /** No thread info at all — pass through. */
    public function test_no_thread_info_passes_through(): void
    {
        $gate = new PauseGate($this->env->config);
        $ctx = []; // No from_phone, no thread_id

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
    }

    /** hasCancelRequest returns false when no cancel file exists. */
    public function test_has_cancel_request_returns_false(): void
    {
        $gate = new PauseGate($this->env->config);
        $this->assertFalse($gate->hasCancelRequest('some_thread'));
    }

    /** Gate name */
    public function test_gate_name_is_correct(): void
    {
        $gate = new PauseGate($this->env->config);
        $this->assertSame('PauseGate', $gate->name());
    }
}
