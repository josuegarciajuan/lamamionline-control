<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WasapBot\Core\Pricing;

/**
 * Unit tests for Pricing — descuentos por usuario y cálculo del total semanal.
 *
 * Solo se prueba la lógica pura (resolveOverride / weeklyTotal) para no depender
 * de config.local.json ni de data/users.json (que no existen en un checkout limpio).
 */
final class PricingTest extends TestCase
{
    public function testResolveOverrideReturnsDefaultWhenNoOverrides(): void
    {
        $this->assertSame(100.0, Pricing::resolveOverride([], '34604829142', 17, 100.0));
    }

    public function testResolveOverrideByUsername(): void
    {
        $overrides = ['34604829142' => 1];
        $this->assertSame(1.0, Pricing::resolveOverride($overrides, '34604829142', 17, 100.0));
    }

    public function testResolveOverrideByUserId(): void
    {
        $overrides = ['17' => 2];
        $this->assertSame(2.0, Pricing::resolveOverride($overrides, 'otro_usuario', 17, 100.0));
    }

    public function testResolveOverrideMatchesNationalFormatWithoutCountryPrefix(): void
    {
        // Username con prefijo 34 → la clave de override en formato nacional (sin 34) hace match.
        $overrides = ['654464023' => 1];
        $this->assertSame(1.0, Pricing::resolveOverride($overrides, '34654464023', 12, 100.0));
    }

    public function testResolveOverrideNationalKeyDoesNotMatchOther34Users(): void
    {
        // 34604829142 sin el 34 → 604829142, que no está en los overrides → sin descuento.
        $overrides = ['654464023' => 1];
        $this->assertSame(100.0, Pricing::resolveOverride($overrides, '34604829142', 17, 100.0));
    }

    public function testResolveOverrideRawUsernameStillMatches(): void
    {
        $overrides = ['654464023' => 1];
        $this->assertSame(1.0, Pricing::resolveOverride($overrides, '654464023', 999, 100.0));
    }

    public function testResolveOverrideUsernameTakesPrecedenceOverUserId(): void
    {
        $overrides = ['34604829142' => 1, '17' => 2];
        $this->assertSame(1.0, Pricing::resolveOverride($overrides, '34604829142', 17, 100.0));
    }

    public function testResolveOverrideIgnoresOtherUsers(): void
    {
        $overrides = ['34604829142' => 1];
        $this->assertSame(100.0, Pricing::resolveOverride($overrides, 'otro_usuario', 99, 100.0));
    }

    public function testWeeklyTotalWithZeroOrOneLineIsBasePrice(): void
    {
        $this->assertSame(100.0, Pricing::weeklyTotal(99999, 0));
        $this->assertSame(100.0, Pricing::weeklyTotal(99999, 1));
    }

    public function testWeeklyTotalAddsExtraLines(): void
    {
        // 1 incluida + 2 extra × 25 = 150
        $this->assertSame(150.0, Pricing::weeklyTotal(99999, 3));
    }
}
