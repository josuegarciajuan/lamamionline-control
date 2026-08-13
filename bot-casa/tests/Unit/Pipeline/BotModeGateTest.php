<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\BotModeGate;
use WasapBot\Tests\Support\TmpEnv;

/**
 * BotModeGate — gate that checks if the bot is in "running" or "stop" mode.
 *
 * NOTE: BotModeGate resolves all file paths relative to WASAPBOT_ROOT/data.
 * We create test files in data/ and clean them up. TmpEnv's temp dir cannot
 * be used because BotModeGate hardcodes the data/ path validation.
 */
final class BotModeGateTest extends TestCase
{
    private TmpEnv $env;
    private string $testModeFile;

    protected function setUp(): void
    {
        $this->env = new TmpEnv();

        // BotModeGate constructs paths from WASAPBOT_ROOT + config value.
        // We must use a path within WASAPBOT_ROOT/data/ to pass validatePath().
        $this->testModeFile = 'data/.bot_mode_test_' . uniqid();
        $this->env->config->set('bot.mode_file', $this->testModeFile);
        // Needed for lead lock path resolution
        $this->env->config->set('files.base_data_dir', 'data');

        // Create the mode file with 'running'
        $resolved = WASAPBOT_ROOT . '/' . $this->testModeFile;
        @mkdir(dirname($resolved), 0755, true);
        file_put_contents($resolved, 'running');
    }

    protected function tearDown(): void
    {
        // Clean up test mode file
        $resolved = WASAPBOT_ROOT . '/' . $this->testModeFile;
        if (file_exists($resolved)) {
            unlink($resolved);
        }
        // Clean up lead lock dir
        $leadLockDir = WASAPBOT_ROOT . '/data/locks/lead_detected';
        if (is_dir($leadLockDir)) {
            $files = glob($leadLockDir . '/lead_*');
            if ($files !== false) {
                foreach ($files as $f) {
                    if (file_exists($f)) {
                        unlink($f);
                    }
                }
            }
            @rmdir($leadLockDir);
        }

        $this->env->cleanup();
    }

    /** Bot in "running" mode — gate passes ctx through unchanged. */
    public function test_returns_ctx_when_bot_is_running(): void
    {
        $gate = new BotModeGate($this->env->config);
        $ctx = ['body' => [], 'key' => 'val'];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame('running', $result['bot_mode'] ?? null);
        $this->assertSame('val', $result['key'] ?? null);
    }

    /** Bot in "stop" mode — gate halts the pipeline (returns null). */
    public function test_returns_null_when_bot_is_stopped(): void
    {
        // Write 'stop' to the mode file
        $resolved = WASAPBOT_ROOT . '/' . $this->testModeFile;
        file_put_contents($resolved, 'stop');

        $gate = new BotModeGate($this->env->config);
        $ctx = ['body' => []];

        $result = $gate->process($ctx);

        $this->assertNull($result);
    }

    /** Bot mode file is missing — fail-open, assume "start" and pass through. */
    public function test_returns_ctx_when_mode_file_is_missing(): void
    {
        // Delete the test mode file
        $resolved = WASAPBOT_ROOT . '/' . $this->testModeFile;
        if (file_exists($resolved)) {
            unlink($resolved);
        }

        $gate = new BotModeGate($this->env->config);
        $ctx = ['body' => []];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame('start', $result['bot_mode'] ?? null);
    }

    /** Bot is running but thread has a lead lock — gate halts to prevent re-triggering. */
    public function test_returns_null_when_lead_lock_exists(): void
    {
        // Create lead lock at the path BotModeGate will resolve
        $rootDir = defined('WASAPBOT_ROOT') ? WASAPBOT_ROOT : '';
        $threadId = '000000000_34600123456';
        $leadLockDir = $rootDir . '/data/locks/lead_detected';
        @mkdir($leadLockDir, 0755, true);
        $leadLockFile = $leadLockDir . '/lead_' . md5($threadId) . '.lock';
        file_put_contents($leadLockFile, (string) time());

        $gate = new BotModeGate($this->env->config);
        $ctx = ['body' => [], 'thread_id' => $threadId];

        $result = $gate->process($ctx);

        $this->assertNull($result);
    }

    /** Bot is running and thread has no lead lock — passes through. */
    public function test_returns_ctx_when_no_lead_lock(): void
    {
        $gate = new BotModeGate($this->env->config);
        $threadId = '000000000_123456789';
        $ctx = ['body' => [], 'thread_id' => $threadId];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame('running', $result['bot_mode'] ?? null);
        $this->assertSame($threadId, $result['thread_id'] ?? null);
    }

    /** Gate name */
    public function test_gate_name_is_correct(): void
    {
        $gate = new BotModeGate($this->env->config);
        $this->assertSame('BotModeGate', $gate->name());
    }
}
