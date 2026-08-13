<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Prompt;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\ResponseNormalizer;
use WasapBot\Tests\Support\TmpEnv;

/**
 * Golden parsing tests — verifies that ResponseNormalizer correctly parses
 * sample JSON responses from the LLM and extracts all expected fields.
 */
final class PromptParseSamplesTest extends TestCase
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

    private function parseSample(string $json): array
    {
        $normalizer = new ResponseNormalizer();
        return $normalizer->process(['openai_raw_response' => $json]);
    }

    // ── Sample A: Greeting ────────────────────────────────────────────

    private const SAMPLE_A = <<<'JSON'
{"user_visible_reply": "hola cariño 😘", "lead_detected": false, "lead_confidence": 0, "eta_minutes": null, "photo_action": "none", "lead_signals": ["none"]}
JSON;

    public function test_sample_a_greeting_output_text(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_A);
        $this->assertSame('hola cariño 😘', $ctx['output_text']);
    }

    public function test_sample_a_greeting_lead_detected(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_A);
        $this->assertFalse($ctx['lead_detected']);
    }

    public function test_sample_a_greeting_lead_confidence(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_A);
        $this->assertSame(0.0, $ctx['lead_confidence']);
    }

    public function test_sample_a_greeting_eta_minutes(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_A);
        $this->assertSame(0, $ctx['eta_minutes']);
    }

    public function test_sample_a_greeting_photo_action(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_A);
        $this->assertSame('none', $ctx['photo_action']);
    }

    // ── Sample B: Lead ────────────────────────────────────────────────

    private const SAMPLE_B = <<<'JSON'
{"user_visible_reply": "vale papi, te espero. avisa al llegar 😘", "lead_detected": true, "lead_confidence": 0.95, "eta_minutes": 20, "photo_action": "none", "lead_signals": ["eta_explicit", "selected_girl"]}
JSON;

    public function test_sample_b_lead_output_text(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_B);
        $this->assertSame('vale papi, te espero. avisa al llegar 😘', $ctx['output_text']);
    }

    public function test_sample_b_lead_lead_detected(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_B);
        $this->assertTrue($ctx['lead_detected']);
    }

    public function test_sample_b_lead_lead_confidence(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_B);
        $this->assertSame(0.95, $ctx['lead_confidence']);
    }

    public function test_sample_b_lead_eta_minutes(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_B);
        $this->assertSame(20, $ctx['eta_minutes']);
    }

    public function test_sample_b_lead_photo_action(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_B);
        $this->assertSame('none', $ctx['photo_action']);
    }

    // ── Sample C: Catalog ─────────────────────────────────────────────

    private const SAMPLE_C = <<<'JSON'
{"user_visible_reply": "mira que chicas tengo 😏", "lead_detected": false, "lead_confidence": 0, "eta_minutes": null, "photo_action": "catalog", "lead_signals": ["none"]}
JSON;

    public function test_sample_c_catalog_output_text(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_C);
        $this->assertSame('mira que chicas tengo 😏', $ctx['output_text']);
    }

    public function test_sample_c_catalog_lead_detected(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_C);
        $this->assertFalse($ctx['lead_detected']);
    }

    public function test_sample_c_catalog_lead_confidence(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_C);
        $this->assertSame(0.0, $ctx['lead_confidence']);
    }

    public function test_sample_c_catalog_eta_minutes(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_C);
        $this->assertSame(0, $ctx['eta_minutes']);
    }

    public function test_sample_c_catalog_photo_action(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_C);
        $this->assertSame('catalog', $ctx['photo_action']);
    }

    // ── Sample D: Dead conversation ───────────────────────────────────

    private const SAMPLE_D = <<<'JSON'
{"user_visible_reply": "bueno cari, suerte 😘", "lead_detected": false, "lead_confidence": 0, "eta_minutes": null, "photo_action": "none", "lead_signals": ["none"], "conversation_health": "dead"}
JSON;

    public function test_sample_d_dead_output_text(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_D);
        $this->assertSame('bueno cari, suerte 😘', $ctx['output_text']);
    }

    public function test_sample_d_dead_lead_detected(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_D);
        $this->assertFalse($ctx['lead_detected']);
    }

    public function test_sample_d_dead_lead_confidence(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_D);
        $this->assertSame(0.0, $ctx['lead_confidence']);
    }

    public function test_sample_d_dead_eta_minutes(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_D);
        $this->assertSame(0, $ctx['eta_minutes']);
    }

    public function test_sample_d_dead_photo_action(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_D);
        $this->assertSame('none', $ctx['photo_action']);
    }

    public function test_sample_d_dead_conversation_health(): void
    {
        $ctx = $this->parseSample(self::SAMPLE_D);
        $this->assertSame('dead', $ctx['__llm_conversation_health']);
    }
}
