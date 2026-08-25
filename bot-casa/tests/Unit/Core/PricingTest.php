<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WasapBot\Core\Pricing;

final class PricingTest extends TestCase
{
    public function testSharedProductPricingUsesApprovedWeeklyAmounts(): void
    {
        self::assertSame(50.0, Pricing::weeklyBase(0));
        self::assertSame(10.0, Pricing::extraLine());
    }

    public function testExtraLineProrationUsesWholeCalendarDaysAndRoundsToCents(): void
    {
        self::assertSame(10.0, Pricing::proratedExtraLine(7));
        self::assertSame(8.57, Pricing::proratedExtraLine(6));
        self::assertSame(1.43, Pricing::proratedExtraLine(1));
        self::assertSame(0.0, Pricing::proratedExtraLine(0));
    }

    public function testWholeDaysRemainingIgnoresTimeOfDay(): void
    {
        $end = '2026-09-01T15:30:00+02:00';

        self::assertSame(7, Pricing::wholeDaysRemaining(
            new \DateTimeImmutable('2026-08-25T00:01:00+02:00'),
            $end
        ));
        self::assertSame(7, Pricing::wholeDaysRemaining(
            new \DateTimeImmutable('2026-08-25T23:59:59+02:00'),
            $end
        ));
        self::assertSame(0, Pricing::wholeDaysRemaining(
            new \DateTimeImmutable('2026-09-01T00:01:00+02:00'),
            $end
        ));
    }
}
