<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\DedupGate;
use WasapBot\Tests\Support\PayloadFactory;
use WasapBot\Tests\Support\TmpEnv;

/**
 * DedupGate — prevents duplicate message processing using lock files.
 */
final class DedupGateTest extends TestCase
{
    private TmpEnv $env;
    private string $dedupDir;

    protected function setUp(): void
    {
        $this->env = new TmpEnv();
        $this->dedupDir = (string) $this->env->config->get('files.event_dedup_dir', '');
    }

    protected function tearDown(): void
    {
        $this->env->cleanup();
    }

    /** First message (new wamid) — passes and creates lock file. */
    public function test_first_message_passes_and_creates_lock(): void
    {
        $gate = new DedupGate($this->env->config);
        $ctx = ['body' => PayloadFactory::text('hola', '34600123456', '000000000', 'WAMID_UNIQUE_001')];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame('OK', $result['__dedup_status'] ?? null);

        // Lock file should exist
        $lockFile = $this->dedupDir . '/WAMID_UNIQUE_001.lock';
        $this->assertFileExists($lockFile);
    }

    /** Same wamid processed twice — second call returns null (dedup). */
    public function test_duplicate_message_returns_null(): void
    {
        $gate = new DedupGate($this->env->config);
        $ctx = ['body' => PayloadFactory::text('hola', '34600123456', '000000000', 'WAMID_DUP_001')];

        // First pass
        $result1 = $gate->process($ctx);
        $this->assertNotNull($result1);
        $this->assertSame('OK', $result1['__dedup_status'] ?? null);

        // Second pass (same wamid)
        $result2 = $gate->process($ctx);
        $this->assertNull($result2);
    }

    /** Different wamid — passes (not a duplicate). */
    public function test_different_wamid_passes(): void
    {
        $gate = new DedupGate($this->env->config);

        $ctx1 = ['body' => PayloadFactory::text('msg1', '34600123456', '000000000', 'WAMID_A')];
        $ctx2 = ['body' => PayloadFactory::text('msg2', '34600123456', '000000000', 'WAMID_B')];

        $result1 = $gate->process($ctx1);
        $this->assertNotNull($result1);

        $result2 = $gate->process($ctx2);
        $this->assertNotNull($result2); // Different wamid, should pass
    }

    /** No messageId in payload and no identifiable payload — fail-open, passes with OK_NOKEY. */
    public function test_no_message_id_passes_with_ok_nokey(): void
    {
        $gate = new DedupGate($this->env->config);
        // ctx with no body at all — extractMessageId returns empty string
        $ctx = [];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame('OK_NOKEY', $result['__dedup_status'] ?? null);
    }

    /** Fallback compound key when no id but has from+timestamp+body — still passes. */
    public function test_compound_key_when_no_id(): void
    {
        $gate = new DedupGate($this->env->config);

        $ts = time();
        $ctx = [
            'body' => [
                'event'   => 'message',
                'payload' => [
                    'from'      => '34600123456@c.us',
                    'timestamp' => $ts,
                    'body'      => 'hola que tal',
                ],
            ],
        ];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        // Should NOT be OK_NOKEY — extractMessageId builds a compound key
        $this->assertSame('OK', $result['__dedup_status'] ?? null);
    }

    /** Compound key: same compound key processed twice — second is dedup. */
    public function test_compound_key_dedup_on_repeat(): void
    {
        $gate = new DedupGate($this->env->config);

        $ts = time();
        $ctx = [
            'body' => [
                'event'   => 'message',
                'payload' => [
                    'from'      => '34600123456@c.us',
                    'timestamp' => $ts,
                    'body'      => 'hola que tal',
                ],
            ],
        ];

        $result1 = $gate->process($ctx);
        $this->assertNotNull($result1);

        $result2 = $gate->process($ctx);
        $this->assertNull($result2);
    }

    /** Gate name */
    public function test_gate_name_is_correct(): void
    {
        $gate = new DedupGate($this->env->config);
        $this->assertSame('DedupGate', $gate->name());
    }
}
