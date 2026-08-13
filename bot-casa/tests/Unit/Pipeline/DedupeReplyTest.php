<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\DedupeReply;
use WasapBot\Tests\Support\TmpEnv;
use WasapBot\Core\ConfigInterface;

/**
 * Unit tests for DedupeReply — prevents the bot from repeating the same content.
 */
final class DedupeReplyTest extends TestCase
{
    private ConfigInterface $config;
    private ?TmpEnv $env = null;

    protected function setUp(): void
    {
        $this->env = new TmpEnv();
        $this->config = $this->env->config;
    }

    protected function tearDown(): void
    {
        $this->env?->cleanup();
        unset($this->env);
    }

    private function newDedupe(): DedupeReply
    {
        return new DedupeReply($this->config);
    }

    // ── Tests ─────────────────────────────────────────────────────────

    public function test_output_equal_to_last_reply_is_deduped(): void
    {
        $dedupe = $this->newDedupe();
        $ctx = [
            'output_text'  => 'hola cariño 😘',
            'last_bot_reply' => 'hola cariño 😘',
            'recent_bot_replies_norm' => [],
        ];

        $result = $dedupe->process($ctx);

        $this->assertTrue($result['__dedup_applied']);
        $this->assertNotSame('hola cariño 😘', $result['output_text']);
    }

    public function test_output_different_from_last_reply_is_kept(): void
    {
        $dedupe = $this->newDedupe();
        $ctx = [
            'output_text'  => 'nuevo mensaje diferente',
            'last_bot_reply' => 'mensaje anterior distinto',
            'recent_bot_replies_norm' => [],
        ];

        $result = $dedupe->process($ctx);

        $this->assertFalse($result['__dedup_applied']);
        $this->assertSame('nuevo mensaje diferente', $result['output_text']);
    }

    public function test_output_in_recent_replies_is_deduped(): void
    {
        $dedupe = $this->newDedupe();
        $ctx = [
            'output_text'  => 'hola que tal estas',
            'last_bot_reply' => '',
            'recent_bot_replies_norm' => ['hola que tal estas', 'bien y tu'],
        ];

        $result = $dedupe->process($ctx);

        $this->assertTrue($result['__dedup_applied']);
    }

    public function test_empty_output_text_is_passed_through(): void
    {
        $dedupe = $this->newDedupe();
        $ctx = [
            'output_text'  => '',
            'last_bot_reply' => 'hola cariño 😘',
            'recent_bot_replies_norm' => [],
        ];

        $result = $dedupe->process($ctx);

        $this->assertSame('', $result['output_text']);
        $this->assertArrayNotHasKey('__dedup_applied', $result);
    }
}
