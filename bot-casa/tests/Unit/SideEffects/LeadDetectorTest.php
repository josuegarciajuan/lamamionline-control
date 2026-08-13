<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\SideEffects;

use PHPUnit\Framework\TestCase;
use WasapBot\SideEffects\LeadDetector;

/**
 * Unit tests for LeadDetector — detects leads from OpenAI JSON response.
 */
final class LeadDetectorTest extends TestCase
{
    private function newDetector(): LeadDetector
    {
        return new LeadDetector();
    }

    // ── Tests ─────────────────────────────────────────────────────────

    public function test_is_lead_with_lead_detected_true_returns_true(): void
    {
        $detector = $this->newDetector();

        $result = $detector->isLead(['lead_detected' => true]);

        $this->assertTrue($result);
    }

    public function test_is_lead_with_lead_detected_false_returns_false(): void
    {
        $detector = $this->newDetector();

        $result = $detector->isLead(['lead_detected' => false]);

        $this->assertFalse($result);
    }

    public function test_is_lead_with_no_lead_detected_field_returns_false(): void
    {
        $detector = $this->newDetector();

        $result = $detector->isLead(['lead_confidence' => 0.95]);

        $this->assertFalse($result);
    }

    public function test_confidence_with_numeric_value_returns_float(): void
    {
        $detector = $this->newDetector();

        $conf = $detector->confidence(['lead_confidence' => 0.85]);

        $this->assertSame(0.85, $conf);
    }

    public function test_confidence_without_field_returns_zero(): void
    {
        $detector = $this->newDetector();

        $conf = $detector->confidence([]);

        $this->assertSame(0.0, $conf);
    }

    public function test_confidence_clamped_to_range_0_to_1(): void
    {
        $detector = $this->newDetector();

        $conf = $detector->confidence(['lead_confidence' => 1.5]);
        $this->assertSame(1.0, $conf);

        $conf = $detector->confidence(['lead_confidence' => -0.5]);
        $this->assertSame(0.0, $conf);
    }

    public function test_is_lead_with_eta_from_user_and_high_confidence_returns_true(): void
    {
        $detector = $this->newDetector();

        $result = $detector->isLead([
            'lead_detected'    => false,
            'lead_confidence'  => 0.95,
            'eta_from_user_flag' => true,
        ]);

        $this->assertTrue($result);
    }

    public function test_is_lead_with_eta_from_user_but_low_confidence_returns_false(): void
    {
        $detector = $this->newDetector();

        $result = $detector->isLead([
            'lead_detected'     => false,
            'lead_confidence'   => 0.5,
            'eta_from_user_flag' => true,
        ]);

        $this->assertFalse($result);
    }

    public function test_eta_minutes_returns_integer_value(): void
    {
        $detector = $this->newDetector();

        $eta = $detector->etaMinutes(['eta_minutes' => 20]);
        $this->assertSame(20, $eta);
    }

    public function test_eta_minutes_with_null_returns_zero(): void
    {
        $detector = $this->newDetector();

        $eta = $detector->etaMinutes(['eta_minutes' => null]);
        $this->assertSame(0, $eta);
    }

    public function test_eta_minutes_without_field_returns_zero(): void
    {
        $detector = $this->newDetector();

        $eta = $detector->etaMinutes([]);
        $this->assertSame(0, $eta);
    }
}
