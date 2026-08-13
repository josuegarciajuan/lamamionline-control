<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\Coalescer;
use WasapBot\Tests\Support\PayloadFactory;
use WasapBot\Tests\Support\TmpEnv;

/**
 * Coalescer — groups rapid-fire messages from the same sender.
 */
final class CoalescerTest extends TestCase
{
    private TmpEnv $env;
    private string $coalesceDir;

    protected function setUp(): void
    {
        $this->env = new TmpEnv();
        $this->coalesceDir = (string) $this->env->config->get('files.coalesce_dir', '');

        // Silence sleep — set coalesce sleep to 0 for tests
        $this->env->config->set('dedup_coalesce.coalesce_sleep_before_send_sec', 0);
    }

    protected function tearDown(): void
    {
        $this->env->cleanup();
    }

    /** Single message (no burst) — returns ctx with __coalesced_text set to original text. */
    public function test_single_message_returns_coalesced_text(): void
    {
        $gate = new Coalescer($this->env->config);
        $ctx = ['body' => PayloadFactory::text('hola', '34600123456', '000000000', 'WAMID_C1')];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertArrayHasKey('__coalesced_text', $result);
        $this->assertSame('hola', $result['__coalesced_text']);
        $this->assertTrue($result['__is_first'] ?? false);
    }

    /** Follower message when burst is active — returns null. */
    public function test_follower_message_returns_null(): void
    {
        // Create a fake active burst: write meta and buf files manually
        $compositeKey = '34600123456_000000000';
        $metaFile = $this->coalesceDir . '/' . $compositeKey . '.meta';
        $bufFile  = $this->coalesceDir . '/' . $compositeKey . '.buf';

        $now = time();
        file_put_contents($metaFile, 'WAMID_LEADER');
        file_put_contents($bufFile, $now . "\t" . 'WAMID_LEADER' . "\t" . 'primer mensaje' . "\n");

        // Ensure lock dir doesn't exist (so acquireLock succeeds)
        $lockDir = $this->coalesceDir . '/' . $compositeKey . '.lock';
        if (is_dir($lockDir)) {
            rmdir($lockDir);
        }

        $gate = new Coalescer($this->env->config);
        $ctx = ['body' => PayloadFactory::text('segundo mensaje', '34600123456', '000000000', 'WAMID_FOLLOWER')];

        $result = $gate->process($ctx);

        $this->assertNull($result);

        // Clean up
        @unlink($metaFile);
        @unlink($bufFile);
        if (is_dir($lockDir)) {
            rmdir($lockDir);
        }
    }

    /**
     * When burst is active, follower appends to buf and returns null.
     * We verify the follower's text appears in the buf file.
     */
    public function test_follower_appends_to_buf_when_burst_active(): void
    {
        $compositeKey = '34600123456_000000000';
        $metaFile = $this->coalesceDir . '/' . $compositeKey . '.meta';
        $bufFile  = $this->coalesceDir . '/' . $compositeKey . '.buf';
        $lockDir  = $this->coalesceDir . '/' . $compositeKey . '.lock';

        // Simulate an active burst: meta + 1 entry in buf
        $now = time();
        file_put_contents($metaFile, 'WAMID_LEADER');
        file_put_contents(
            $bufFile,
            $now . "\t" . 'WAMID_LEADER' . "\t" . 'primer mensaje' . "\n"
        );

        if (is_dir($lockDir)) {
            rmdir($lockDir);
        }

        $gate = new Coalescer($this->env->config);
        $ctx = ['body' => PayloadFactory::text('segundo mensaje', '34600123456', '000000000', 'WAMID_FOLLOWER')];

        $result = $gate->process($ctx);

        // Follower returns null
        $this->assertNull($result);

        // Buf should now have 2 entries
        $this->assertFileExists($bufFile);
        $lines = @file($bufFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('segundo mensaje', implode('', $lines));

        // Clean up
        @unlink($metaFile);
        @unlink($bufFile);
        if (is_dir($lockDir)) {
            rmdir($lockDir);
        }
    }

    /** Text containing opening burst patterns (auto msg + greeting) is detected. */
    public function test_opening_burst_detected(): void
    {
        // Ensure clean state
        $compositeKey = '34600123456_000000000';
        $lockDir = $this->coalesceDir . '/' . $compositeKey . '.lock';
        if (is_dir($lockDir)) {
            rmdir($lockDir);
        }

        $gate = new Coalescer($this->env->config);
        $ctx = ['body' => PayloadFactory::openingBurst(
            'he visto tu anuncio hola',
            '34600123456',
            '000000000',
            'WAMID_BURST'
        )];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertTrue($result['__is_opening_burst'] ?? false);

        // Clean up meta/buf
        @unlink($this->coalesceDir . '/' . $compositeKey . '.meta');
        @unlink($this->coalesceDir . '/' . $compositeKey . '.buf');
    }

    /** Text without opening burst patterns — __is_opening_burst is false. */
    public function test_no_opening_burst(): void
    {
        $compositeKey = '34600123456_000000000';
        $lockDir = $this->coalesceDir . '/' . $compositeKey . '.lock';
        if (is_dir($lockDir)) {
            rmdir($lockDir);
        }

        $gate = new Coalescer($this->env->config);
        $ctx = ['body' => PayloadFactory::text('cuanto cobras?', '34600123456', '000000000', 'WAMID_NONBURST')];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertFalse($result['__is_opening_burst'] ?? true);

        // Clean up
        @unlink($this->coalesceDir . '/' . $compositeKey . '.meta');
        @unlink($this->coalesceDir . '/' . $compositeKey . '.buf');
    }

    /** Empty phone — pass through unchanged, no coalescing. */
    public function test_empty_phone_passes_through(): void
    {
        $gate = new Coalescer($this->env->config);
        $ctx = [
            'body' => [
                'event'   => 'message',
                'payload' => [
                    'body' => 'hola',
                    'type' => 'text',
                ],
            ],
        ];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertArrayNotHasKey('__coalesced_text', $result);
    }

    /** Gate name */
    public function test_gate_name_is_correct(): void
    {
        $gate = new Coalescer($this->env->config);
        $this->assertSame('Coalescer', $gate->name());
    }
}
