<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\ResponseNormalizer;
use WasapBot\Tests\Support\TmpEnv;

/**
 * Unit tests for ResponseNormalizer — JSON response parsing and field extraction.
 */
final class ResponseNormalizerTest extends TestCase
{
    private ?TmpEnv $env = null;

    protected function setUp(): void
    {
        $this->env = new TmpEnv();
    }

    protected function tearDown(): void
    {
        $this->env?->cleanup();
        unset($this->env);
    }

    private function newNormalizer(): ResponseNormalizer
    {
        return new ResponseNormalizer();
    }

    // ── Tests ─────────────────────────────────────────────────────────

    public function test_valid_json_with_user_visible_reply_extracts_output_text(): void
    {
        $normalizer = $this->newNormalizer();
        $ctx = [
            'openai_raw_response' => json_encode([
                'user_visible_reply' => 'hola cariño 😘',
                'lead_detected'      => false,
                'lead_confidence'    => 0,
                'eta_minutes'        => null,
                'photo_action'       => 'none',
                'lead_signals'       => ['none'],
            ]),
        ];

        $result = $normalizer->process($ctx);

        $this->assertSame('hola cariño 😘', $result['output_text']);
    }

    public function test_json_with_lead_detected_true_extracts_lead_fields(): void
    {
        $normalizer = $this->newNormalizer();
        $ctx = [
            'openai_raw_response' => json_encode([
                'user_visible_reply' => 'vale papi, te espero 😘',
                'lead_detected'      => true,
                'lead_confidence'    => 0.95,
                'eta_minutes'        => 20,
                'photo_action'       => 'none',
                'lead_signals'       => ['eta_explicit', 'selected_girl'],
            ]),
        ];

        $result = $normalizer->process($ctx);

        $this->assertTrue($result['lead_detected']);
        $this->assertSame(0.95, $result['lead_confidence']);
        $this->assertSame(20, $result['eta_minutes']);
        $this->assertSame('none', $result['photo_action']);
    }

    public function test_json_with_selected_all_photo_action_is_respected(): void
    {
        $normalizer = $this->newNormalizer();
        $ctx = [
            'openai_raw_response' => json_encode([
                'user_visible_reply' => 'aqui las fotos',
                'lead_detected'      => false,
                'lead_confidence'    => 0,
                'eta_minutes'        => null,
                'photo_action'       => 'selected_all',
                'lead_signals'       => ['none'],
            ]),
        ];

        $result = $normalizer->process($ctx);

        $this->assertSame('selected_all', $result['photo_action']);
    }

    public function test_valid_lead_signals_are_kept_invalid_filtered(): void
    {
        $normalizer = $this->newNormalizer();
        $ctx = [
            'openai_raw_response' => json_encode([
                'user_visible_reply' => 'test',
                'lead_detected'      => false,
                'lead_confidence'    => 0,
                'eta_minutes'        => null,
                'photo_action'       => 'none',
                'lead_signals'       => ['eta_explicit', 'invalid_signal', 'price_asked', 'fake'],
            ]),
        ];

        $result = $normalizer->process($ctx);

        $this->assertContains('eta_explicit', $result['lead_signals']);
        $this->assertContains('price_asked', $result['lead_signals']);
        $this->assertNotContains('invalid_signal', $result['lead_signals']);
        $this->assertNotContains('fake', $result['lead_signals']);
    }

    public function test_malformed_json_uses_raw_content_as_output(): void
    {
        $normalizer = $this->newNormalizer();
        $malformedJson = '{ esto no es valido';

        $ctx = [
            'openai_raw_response' => $malformedJson,
        ];

        $result = $normalizer->process($ctx);

        $this->assertSame($malformedJson, $result['output_text']);
        $this->assertFalse($result['lead_detected']);
        $this->assertSame(0.0, $result['lead_confidence']);
        $this->assertSame(0, $result['eta_minutes']);
    }

    public function test_plain_text_non_json_is_used_as_output(): void
    {
        $normalizer = $this->newNormalizer();
        $plainText = 'esto es un texto normal sin formato json';

        $ctx = [
            'openai_raw_response' => $plainText,
        ];

        $result = $normalizer->process($ctx);

        $this->assertSame($plainText, $result['output_text']);
        $this->assertFalse($result['lead_detected']);
    }

    public function test_text_starting_with_brace_but_not_json_falls_back_to_raw(): void
    {
        $normalizer = $this->newNormalizer();
        // Something that looks like it could be JSON but isn't
        $notJson = '{texto que parece json pero no lo es';

        $ctx = [
            'openai_raw_response' => $notJson,
        ];

        $result = $normalizer->process($ctx);

        // Starts with { but decode fails → used as raw output
        $this->assertSame($notJson, $result['output_text']);
    }

    public function test_truncated_compartir_url_is_repaired(): void
    {
        $normalizer = $this->newNormalizer();
        $ctx = [
            'openai_raw_response' => json_encode([
                'user_visible_reply' => 'mira aqui site/pnb8l/ para las fotos',
                'lead_detected'      => false,
                'lead_confidence'    => 0,
                'eta_minutes'        => null,
                'photo_action'       => 'none',
                'lead_signals'       => ['none'],
            ]),
        ];

        $result = $normalizer->process($ctx);

        $this->assertStringContainsString('https://compartir.site/pnb8l/', $result['output_text']);
    }

    public function test_semantic_fields_are_extracted(): void
    {
        $normalizer = $this->newNormalizer();
        $ctx = [
            'openai_raw_response' => json_encode([
                'user_visible_reply'    => 'ok',
                'lead_detected'         => false,
                'lead_confidence'       => 0,
                'eta_minutes'           => null,
                'photo_action'          => 'none',
                'lead_signals'          => ['none'],
                'mentioned_girl'        => 'Carina',
                'girl_selection_intent' => true,
                'conversation_health'   => 'fading',
                'tarifa_elegida'        => '50',
                'buying_intent'         => 'exploring',
                'hot_curious'           => false,
            ]),
        ];

        $result = $normalizer->process($ctx);

        $this->assertSame('Carina', $result['__llm_mentioned_girl']);
        $this->assertTrue($result['__llm_girl_selection_intent']);
        $this->assertSame('fading', $result['__llm_conversation_health']);
        $this->assertSame('50', $result['__llm_tarifa_elegida']);
        $this->assertSame('exploring', $result['__llm_buying_intent']);
        $this->assertFalse($result['__llm_hot_curious']);
    }

    public function test_missing_response_sets_default_fields(): void
    {
        $normalizer = $this->newNormalizer();
        $ctx = [];

        $result = $normalizer->process($ctx);

        $this->assertSame('', $result['output_text']);
        $this->assertFalse($result['lead_detected']);
        $this->assertSame(0.0, $result['lead_confidence']);
        $this->assertSame(0, $result['eta_minutes']);
    }

    public function test_already_parsed_array_passed_directly(): void
    {
        $normalizer = $this->newNormalizer();
        // Simulate OpenAiClient returning a parsed array directly (no 'choices' key)
        $ctx = [
            'openai_raw_response' => [
                'user_visible_reply' => 'directo desde array',
                'lead_detected'      => false,
                'lead_confidence'    => 0.5,
                'eta_minutes'        => 10,
                'photo_action'       => 'catalog',
                'lead_signals'       => ['price_asked'],
            ],
        ];

        $result = $normalizer->process($ctx);

        $this->assertSame('directo desde array', $result['output_text']);
        $this->assertSame(0.5, $result['lead_confidence']);
        $this->assertSame(10, $result['eta_minutes']);
        $this->assertSame('catalog', $result['photo_action']);
    }
}
