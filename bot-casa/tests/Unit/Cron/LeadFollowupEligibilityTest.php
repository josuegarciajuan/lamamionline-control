<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Cron;

use PHPUnit\Framework\TestCase;
use WasapBot\Cron\LeadFollowupEligibility;

final class LeadFollowupEligibilityTest extends TestCase
{
    public function test_arrived_leads_are_eligible_for_followup(): void
    {
        self::assertTrue(LeadFollowupEligibility::shouldInclude(['arrived' => true]));
    }

    public function test_unconfirmed_leads_are_not_eligible_for_followup(): void
    {
        self::assertFalse(LeadFollowupEligibility::shouldInclude(['arrived' => false]));
        self::assertFalse(LeadFollowupEligibility::shouldInclude([]));
    }
}
