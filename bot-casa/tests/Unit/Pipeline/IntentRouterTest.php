<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\IntentRouter;
use WasapBot\Tests\Support\TmpEnv;
use WasapBot\Core\ConfigInterface;

/**
 * Unit tests for IntentRouter — fast-path greeting routing.
 */
final class IntentRouterTest extends TestCase
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

    // ── Helper ─────────────────────────────────────────────────────────

    private function newRouter(): IntentRouter
    {
        return new IntentRouter($this->config);
    }

    // ── Tests ─────────────────────────────────────────────────────────

    public function test_greeting_on_new_conversation_activates_fast_path(): void
    {
        $router = $this->newRouter();
        $ctx = [
            'message_text'          => 'hola',
            '__is_new_conversation' => true,
        ];

        $result = $router->process($ctx);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result['output_text']);
        $this->assertTrue($result['__skip_llm'] ?? false);
        $this->assertSame('greeting', $result['__intent']);
        $this->assertFalse($result['lead_detected']);
        $this->assertSame('none', $result['photo_action']);
    }

    public function test_greeting_on_existing_conversation_does_not_activate_fast_path(): void
    {
        $router = $this->newRouter();
        $ctx = [
            'message_text'          => 'hola',
            '__is_new_conversation' => false,
        ];

        $result = $router->process($ctx);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('__skip_llm', $result);
    }

    public function test_non_greeting_on_new_conversation_does_not_activate_fast_path(): void
    {
        $router = $this->newRouter();
        $ctx = [
            'message_text'          => 'cuanto cobras',
            '__is_new_conversation' => true,
        ];

        $result = $router->process($ctx);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('__skip_llm', $result);
    }

    public function test_empty_text_passes_through(): void
    {
        $router = $this->newRouter();
        $ctx = [
            'message_text'          => '',
            '__is_new_conversation' => true,
        ];

        $result = $router->process($ctx);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('__skip_llm', $result);
    }

    public function test_already_skipped_llm_is_not_rerouted(): void
    {
        $router = $this->newRouter();
        $ctx = [
            'message_text'          => 'hola',
            '__is_new_conversation' => true,
            '__skip_llm'            => true,
            'output_text'           => 'ya respondido',
        ];

        $result = $router->process($ctx);

        $this->assertIsArray($result);
        $this->assertSame('ya respondido', $result['output_text']);
    }

    public function test_greeting_variants_are_recognized(): void
    {
        $router = $this->newRouter();

        $greetings = ['hola', 'buenas', 'hey', 'saludos', 'ola', 'alo', 'buen dia', 'buenas tardes', 'buenas noches'];

        foreach ($greetings as $greeting) {
            $ctx = [
                'message_text'          => $greeting,
                '__is_new_conversation' => true,
            ];
            $result = $router->process($ctx);
            $this->assertTrue(
                $result['__skip_llm'] ?? false,
                "Greeting '{$greeting}' should activate fast-path"
            );
        }
    }

    public function test_non_greeting_phrases_are_ignored(): void
    {
        $router = $this->newRouter();

        $nonGreetings = ['cuanto cobras', 'donde estas', 'quiero fotos', 'que tal', 'adios'];

        foreach ($nonGreetings as $text) {
            $ctx = [
                'message_text'          => $text,
                '__is_new_conversation' => true,
            ];
            $result = $router->process($ctx);
            $this->assertArrayNotHasKey(
                '__skip_llm',
                $result,
                "Non-greeting '{$text}' should not activate fast-path"
            );
        }
    }

    public function test_fallback_greetings_no_longer_contain_papi(): void
    {
        // IntentRouter stores templates at construction time.
        // Use reflection to read the built-in fallback templates directly.
        $router = new IntentRouter($this->config);
        $ref = new \ReflectionClass($router);
        $prop = $ref->getProperty('templates');
        $prop->setAccessible(true);
        $templates = $prop->getValue($router);

        $this->assertIsArray($templates['greeting']);
        // Verify "papi" is absent from all greeting templates
        foreach ($templates['greeting'] as $greeting) {
            $this->assertStringNotContainsString(
                'papi',
                $greeting,
                "Fallback greeting '{$greeting}' must not contain 'papi'"
            );
        }
        // Verify at least one greeting uses "amor"
        $hasAmor = false;
        foreach ($templates['greeting'] as $greeting) {
            if (str_contains($greeting, 'amor')) {
                $hasAmor = true;
                break;
            }
        }
        $this->assertTrue($hasAmor, 'At least one fallback greeting must contain "amor"');
    }
}
