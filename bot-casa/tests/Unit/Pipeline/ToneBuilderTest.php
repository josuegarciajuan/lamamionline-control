<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\ToneBuilder;
use WasapBot\Tests\Support\TmpEnv;
use WasapBot\Core\ConfigInterface;

/**
 * Unit tests for ToneBuilder — generates tone directives for the LLM.
 */
final class ToneBuilderTest extends TestCase
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

    private function newToneBuilder(): ToneBuilder
    {
        return new ToneBuilder($this->config);
    }

    // ── Tests ─────────────────────────────────────────────────────────

    public function test_with_sentiment_register_urgency_generates_tone_directives(): void
    {
        $builder = $this->newToneBuilder();
        $ctx = [
            'sentiment' => 'positive',
            'register'  => 'coloquial',
            'urgency'   => 'alta',
        ];

        $result = $builder->process($ctx);

        $this->assertIsString($result['tone_directives']);
        $this->assertStringContainsString('positive', $result['tone_directives']);
        $this->assertStringContainsString('coloquial', $result['tone_directives']);
        $this->assertStringContainsString('alta', $result['tone_directives']);
    }

    public function test_default_values_when_no_tone_data_provided(): void
    {
        $builder = $this->newToneBuilder();
        $ctx = [];

        $result = $builder->process($ctx);

        $this->assertIsString($result['tone_directives']);
        $this->assertStringContainsString('neutral', $result['tone_directives']);
    }

    public function test_new_conversation_picks_personality_style(): void
    {
        $builder = $this->newToneBuilder();
        $ctx = [
            'sentiment'             => 'neutral',
            'register'              => 'normal',
            'urgency'               => 'baja',
            '__is_new_conversation' => true,
        ];

        $result = $builder->process($ctx);

        $this->assertIsString($result['tone_directives']);
        $this->assertNotEmpty($result['__personality_style'] ?? '');
    }

    public function test_tone_directives_contain_anti_repetition(): void
    {
        $builder = $this->newToneBuilder();
        $ctx = [
            'sentiment' => 'neutral',
            'register'  => 'normal',
            'urgency'   => 'baja',
        ];

        $result = $builder->process($ctx);

        $this->assertStringContainsString('ANTI-REPETICION', $result['tone_directives']);
    }

    public function test_speaker_name_sets_identity_directive(): void
    {
        $builder = $this->newToneBuilder();
        $ctx = [
            'sentiment'        => 'neutral',
            'register'         => 'normal',
            'urgency'          => 'baja',
            'speaker_girl_name' => 'Carina',
        ];

        $result = $builder->process($ctx);

        $this->assertStringContainsString('Carina', $result['tone_directives']);
    }

    public function test_contains_anti_papi_directive(): void
    {
        $builder = $this->newToneBuilder();
        $ctx = [
            'sentiment' => 'neutral',
            'register'  => 'normal',
            'urgency'   => 'baja',
        ];

        $result = $builder->process($ctx);

        $this->assertStringContainsString('Evita "papi"', $result['tone_directives']);
    }

    public function test_personality_carinosa_no_longer_mentions_papi(): void
    {
        $builder = $this->newToneBuilder();
        $ctx = [
            'sentiment'             => 'neutral',
            'register'              => 'normal',
            'urgency'               => 'baja',
            '__is_new_conversation' => true,
        ];

        // Force cariñosa style by configuring personality weights
        $this->config->set('personality.weights', [
            'cariñosa' => 1000.0,
            'pícara'   => 0.0,
            'directa'  => 0.0,
            'tímida'   => 0.0,
        ]);

        $result = $builder->process($ctx);

        $this->assertSame('cariñosa', $result['__personality_style']);
        // The style description itself must not contain "papi"
        // (the anti-papi reminder elsewhere is fine — it's an instruction to avoid it)
        $styleDirective = '';
        foreach (explode("\n", $result['tone_directives']) as $line) {
            if (str_contains($line, 'Tu estilo de personalidad')) {
                $styleDirective = $line;
                break;
            }
        }
        $this->assertNotEmpty($styleDirective, 'Should contain personality style directive');
        $this->assertStringNotContainsString('papi', $styleDirective, 'Cariñosa style must not mention papi');
    }

    public function test_multi_message_coalesced_text_produces_coherence_directive(): void
    {
        $builder = $this->newToneBuilder();
        $ctx = [
            'sentiment'   => 'neutral',
            'register'    => 'normal',
            'urgency'     => 'baja',
        ];

        $result = $builder->process($ctx);

        $directives = $result['tone_directives'];
        // The Reactivo directive should be extended with multi-message guidance
        $this->assertStringContainsString('responde a TODAS de forma coherente', $directives);
    }
}
