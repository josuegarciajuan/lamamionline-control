<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\RoutingGate;
use WasapBot\Tests\Support\PayloadFactory;
use WasapBot\Tests\Support\TmpEnv;

/**
 * RoutingGate — verifies line enabled and sender not blacklisted,
 * and builds WAHA connection details.
 */
final class RoutingGateTest extends TestCase
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

    /** Line enabled with last9 that matches the receiver — gate passes and builds WAHA details. */
    public function test_enabled_line_matches_last9(): void
    {
        $gate = new RoutingGate($this->env->config);
        $ctx = ['body' => PayloadFactory::text('hola', '34600123456', '000000000')];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertNotNull($result['waha_line'] ?? null);
        $this->assertTrue($result['waha_enabled'] ?? false);
        $this->assertSame('test-line', $result['line_label'] ?? null);
        $this->assertSame('http://127.0.0.1:3000', $result['waha_base_url'] ?? null);
        $this->assertSame('34600123456@c.us', $result['waha_chat_id'] ?? null);
        $this->assertSame('', $result['waha_api_key'] ?? null);
        $this->assertSame('test', $result['waha_session'] ?? null);
    }

    /** Line not found in config and default_enabled_if_not_found=false — gate halts (null). */
    public function test_line_not_found_and_default_disabled(): void
    {
        $this->env->config->set('routing.default_enabled_if_not_found', false);

        $gate = new RoutingGate($this->env->config);
        // me.id has last9 '999999999' which is NOT in the test line config (only '000000000')
        $ctx = ['body' => PayloadFactory::text('hola', '34600123456', '999999999')];

        $result = $gate->process($ctx);

        $this->assertNull($result);
    }

    /** Line not found but default_enabled_if_not_found=true — gate passes. */
    public function test_line_not_found_and_default_enabled(): void
    {
        // TmpEnv already defaults default_enabled_if_not_found=true
        $gate = new RoutingGate($this->env->config);
        $ctx = ['body' => PayloadFactory::text('hola', '34600123456', '999999999')];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
    }

    /** Sender is blacklisted — gate halts. */
    public function test_sender_blacklisted(): void
    {
        $this->env->config->set('routing.sender_blacklist', ['34600123456']);

        $gate = new RoutingGate($this->env->config);
        $ctx = ['body' => PayloadFactory::text('hola', '34600123456', '000000000')];

        $result = $gate->process($ctx);

        $this->assertNull($result);
    }

    /** Sender not blacklisted — gate passes. */
    public function test_sender_not_blacklisted(): void
    {
        $this->env->config->set('routing.sender_blacklist', ['999999999']);

        $gate = new RoutingGate($this->env->config);
        $ctx = ['body' => PayloadFactory::text('hola', '34600123456', '000000000')];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
    }

    /** WAHA chat ID is correctly built with @c.us suffix. */
    public function test_waha_chat_id_with_c_us_suffix(): void
    {
        $gate = new RoutingGate($this->env->config);
        $ctx = ['body' => PayloadFactory::text('hola', '34654464023', '000000000')];

        $result = $gate->process($ctx);

        $this->assertSame('34654464023@c.us', $result['waha_chat_id'] ?? null);
    }

    /** LID detection: when rawFrom contains @lid, suffix becomes @lid. */
    public function test_lid_detection_suffix_is_at_lid(): void
    {
        $gate = new RoutingGate($this->env->config);
        $ctx = ['body' => PayloadFactory::lid('hola', '34654464023', '277476546711679@lid', '000000000')];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertStringEndsWith('@lid', $result['waha_chat_id'] ?? '');
        // fromPhone for LID is the numeric part of the LID
        $this->assertStringStartsWith('277476546711679', $result['waha_chat_id'] ?? '');
    }

    /** Sender LID is stored for sendSeen in manual chat replies. */
    public function test_sender_lid_is_stored(): void
    {
        $gate = new RoutingGate($this->env->config);
        $lid = '277476546711679@lid';
        $ctx = ['body' => PayloadFactory::lid('hola', '34654464023', $lid, '000000000')];

        $result = $gate->process($ctx);

        $this->assertSame($lid, $result['sender_lid'] ?? null);
    }

    /** WAHA API key is NOT propagated — must be empty string. */
    public function test_waha_api_key_not_propagated(): void
    {
        // Set a fake api key in the config
        $this->env->config->set('waha.api_key', 'secret_should_not_leak');

        $gate = new RoutingGate($this->env->config);
        $ctx = ['body' => PayloadFactory::text('hola', '34600123456', '000000000')];

        $result = $gate->process($ctx);

        $this->assertSame('', $result['waha_api_key'] ?? null);
    }

    /** AI provider and model are injected from the routing line config. */
    public function test_ai_provider_and_model_from_routing_entry(): void
    {
        $gate = new RoutingGate($this->env->config);
        $ctx = ['body' => PayloadFactory::text('hola', '34600123456', '000000000')];

        $result = $gate->process($ctx);

        $this->assertArrayHasKey('ai_provider', $result);
        $this->assertArrayHasKey('ai_model', $result);
        // Default from config if line doesn't specify: 'openai' and null
        $this->assertSame('openai', $result['ai_provider']);
    }

    /** from_phone already in ctx — RoutingGate should use it for blacklist check and chat_id. */
    public function test_uses_existing_from_phone_in_ctx(): void
    {
        $this->env->config->set('routing.sender_blacklist', ['34600123456']);

        $gate = new RoutingGate($this->env->config);
        $ctx = [
            'body'       => PayloadFactory::text('hola', '111111111', '000000000'),
            'from_phone' => '34600123456',
        ];

        $result = $gate->process($ctx);

        $this->assertNull($result); // Blacklisted via ctx.from_phone
    }

    /** line_last9 stored in ctx when line found. */
    public function test_line_last9_is_stored(): void
    {
        $gate = new RoutingGate($this->env->config);
        $ctx = ['body' => PayloadFactory::text('hola', '34600123456', '000000000')];

        $result = $gate->process($ctx);

        $this->assertSame('000000000', $result['line_last9'] ?? null);
    }

    /** line_notas is populated from telefonos.json (empty string for test env since file doesn't exist). */
    public function test_line_notas_is_set(): void
    {
        $gate = new RoutingGate($this->env->config);
        $ctx = ['body' => PayloadFactory::text('hola', '34600123456', '000000000')];

        $result = $gate->process($ctx);

        $this->assertArrayHasKey('line_notas', $result);
    }

    /** gate name */
    public function test_gate_name_is_correct(): void
    {
        $gate = new RoutingGate($this->env->config);
        $this->assertSame('RoutingGate', $gate->name());
    }
}
