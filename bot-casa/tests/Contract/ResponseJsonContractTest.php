<?php

declare(strict_types=1);

namespace WasapBot\Tests\Contract;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\ResponseNormalizer;
use WasapBot\Tests\Support\TmpEnv;

/**
 * Contract tests — verifies that the JSON response format from the LLM
 * always contains the required fields, and that missing fields get defaults.
 *
 * Also validates the allowed enum values for photo_action and lead_signals.
 */
final class ResponseJsonContractTest extends TestCase
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

    private function parseJson(string $json): array
    {
        $normalizer = new ResponseNormalizer();
        return $normalizer->process(['openai_raw_response' => $json]);
    }

    // ── Required fields contract ─────────────────────────────────────

    /**
     * Verifies that a complete JSON with all 6 fields produces no default overrides.
     */
    public function test_complete_json_has_all_required_fields(): void
    {
        $json = json_encode([
            'user_visible_reply' => 'test',
            'lead_detected'      => false,
            'lead_confidence'    => 0.5,
            'eta_minutes'        => 10,
            'photo_action'       => 'catalog',
            'lead_signals'       => ['price_asked'],
        ]);

        $ctx = $this->parseJson($json);

        $this->assertSame('test', $ctx['output_text']);
        $this->assertFalse($ctx['lead_detected']);
        $this->assertSame(0.5, $ctx['lead_confidence']);
        $this->assertSame(10, $ctx['eta_minutes']);
        $this->assertSame('catalog', $ctx['photo_action']);
        $this->assertSame(['price_asked'], $ctx['lead_signals']);
    }

    /**
     * Missing user_visible_reply → output_text defaults to empty or raw content.
     */
    public function test_missing_user_visible_reply_gets_default(): void
    {
        $json = json_encode([
            'lead_detected'   => false,
            'lead_confidence' => 0,
            'eta_minutes'     => null,
            'photo_action'    => 'none',
            'lead_signals'    => ['none'],
        ]);

        $ctx = $this->parseJson($json);

        $this->assertSame('', $ctx['output_text']);
    }

    /**
     * Missing lead_detected → defaults to false.
     */
    public function test_missing_lead_detected_defaults_to_false(): void
    {
        $json = json_encode([
            'user_visible_reply' => 'test',
            'lead_confidence'    => 0,
            'eta_minutes'        => null,
            'photo_action'       => 'none',
            'lead_signals'       => ['none'],
        ]);

        $ctx = $this->parseJson($json);

        $this->assertFalse($ctx['lead_detected']);
    }

    /**
     * Missing lead_confidence → defaults to 0.0.
     */
    public function test_missing_lead_confidence_defaults_to_zero(): void
    {
        $json = json_encode([
            'user_visible_reply' => 'test',
            'lead_detected'      => true,
            'eta_minutes'        => null,
            'photo_action'       => 'none',
            'lead_signals'       => ['none'],
        ]);

        $ctx = $this->parseJson($json);

        $this->assertSame(0.0, $ctx['lead_confidence']);
    }

    /**
     * Missing eta_minutes → defaults to 0.
     */
    public function test_missing_eta_minutes_defaults_to_zero(): void
    {
        $json = json_encode([
            'user_visible_reply' => 'test',
            'lead_detected'      => true,
            'lead_confidence'    => 0.9,
            'photo_action'       => 'none',
            'lead_signals'       => ['none'],
        ]);

        $ctx = $this->parseJson($json);

        $this->assertSame(0, $ctx['eta_minutes']);
    }

    /**
     * Missing photo_action → defaults to 'none'.
     */
    public function test_missing_photo_action_defaults_to_none(): void
    {
        $json = json_encode([
            'user_visible_reply' => 'test',
            'lead_detected'      => false,
            'lead_confidence'    => 0,
            'eta_minutes'        => null,
            'lead_signals'       => ['none'],
        ]);

        $ctx = $this->parseJson($json);

        $this->assertSame('none', $ctx['photo_action']);
    }

    /**
     * Invalid photo_action value → defaults to 'none'.
     */
    public function test_invalid_photo_action_defaults_to_none(): void
    {
        $json = json_encode([
            'user_visible_reply' => 'test',
            'lead_detected'      => false,
            'lead_confidence'    => 0,
            'eta_minutes'        => null,
            'photo_action'       => 'invalid_action',
            'lead_signals'       => ['none'],
        ]);

        $ctx = $this->parseJson($json);

        $this->assertSame('none', $ctx['photo_action']);
    }

    /**
     * Missing lead_signals → defaults to ['none'].
     */
    public function test_missing_lead_signals_defaults_to_none_array(): void
    {
        $json = json_encode([
            'user_visible_reply' => 'test',
            'lead_detected'      => false,
            'lead_confidence'    => 0,
            'eta_minutes'        => null,
            'photo_action'       => 'none',
        ]);

        $ctx = $this->parseJson($json);

        $this->assertSame(['none'], $ctx['lead_signals']);
    }

    // ── Valid enum values for photo_action ────────────────────────────

    public function test_photo_action_none_is_valid(): void
    {
        $ctx = $this->parseJson(json_encode([
            'user_visible_reply' => 'test', 'lead_detected' => false,
            'lead_confidence' => 0, 'eta_minutes' => null,
            'photo_action' => 'none', 'lead_signals' => ['none'],
        ]));
        $this->assertSame('none', $ctx['photo_action']);
    }

    public function test_photo_action_catalog_is_valid(): void
    {
        $ctx = $this->parseJson(json_encode([
            'user_visible_reply' => 'test', 'lead_detected' => false,
            'lead_confidence' => 0, 'eta_minutes' => null,
            'photo_action' => 'catalog', 'lead_signals' => ['none'],
        ]));
        $this->assertSame('catalog', $ctx['photo_action']);
    }

    public function test_photo_action_selected_all_is_valid(): void
    {
        $ctx = $this->parseJson(json_encode([
            'user_visible_reply' => 'test', 'lead_detected' => false,
            'lead_confidence' => 0, 'eta_minutes' => null,
            'photo_action' => 'selected_all', 'lead_signals' => ['none'],
        ]));
        $this->assertSame('selected_all', $ctx['photo_action']);
    }

    // ── Valid enum values for lead_signals ────────────────────────────

    public function test_lead_signals_valid_values_are_preserved(): void
    {
        $validSignals = [
            'eta_explicit', 'eta_implicit', 'coming_soon', 'selected_girl',
            'maps_requested', 'maps_sent', 'price_asked', 'urgent_tone',
            'recurring_client', 'coordination_phase', 'none',
        ];

        foreach ($validSignals as $signal) {
            $ctx = $this->parseJson(json_encode([
                'user_visible_reply' => 'test', 'lead_detected' => false,
                'lead_confidence' => 0, 'eta_minutes' => null,
                'photo_action' => 'none', 'lead_signals' => [$signal],
            ]));
            $this->assertContains($signal, $ctx['lead_signals']);
        }
    }

    public function test_lead_signals_invalid_values_are_filtered(): void
    {
        $ctx = $this->parseJson(json_encode([
            'user_visible_reply' => 'test', 'lead_detected' => false,
            'lead_confidence' => 0, 'eta_minutes' => null,
            'photo_action' => 'none',
            'lead_signals' => ['some_invalid', 'fake_signal'],
        ]));

        // When all signals are invalid, the array is filtered to empty
        $this->assertEmpty($ctx['lead_signals']);
    }
}
