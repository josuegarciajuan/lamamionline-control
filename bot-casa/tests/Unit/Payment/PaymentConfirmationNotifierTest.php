<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use WasapBot\Core\Config;
use WasapBot\Core\UserManager;
use WasapBot\Payment\PaymentConfirmationNotifier;

/**
 * Unit tests for PaymentConfirmationNotifier — lógica pura de destino, chatId
 * y plantilla. Sin red (WAHA) ni dependencia de config.local.json.
 */
final class PaymentConfirmationNotifierTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/wasapbot_pcn_' . uniqid();
        @mkdir($this->tmpDir . '/data', 0700, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $this->removeTree($this->tmpDir);
        }
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $f) {
            $p = $dir . '/' . $f;
            is_dir($p) ? $this->removeTree($p) : unlink($p);
        }
        rmdir($dir);
    }

    private function notifier(): PaymentConfirmationNotifier
    {
        $config = new Config($this->tmpDir);
        return new PaymentConfirmationNotifier($config, new UserManager($this->tmpDir));
    }

    // ── buildChatId ─────────────────────────────────────────────

    public function testBuildChatIdNationalMobileGets34Prefix(): void
    {
        $this->assertSame('34654464023@c.us', PaymentConfirmationNotifier::buildChatId('654464023'));
    }

    public function testBuildChatIdKeepsInternationalFormat(): void
    {
        $this->assertSame('34654464023@c.us', PaymentConfirmationNotifier::buildChatId('34654464023'));
    }

    public function testBuildChatIdWithSpacesAndDots(): void
    {
        $this->assertSame('34654464023@c.us', PaymentConfirmationNotifier::buildChatId('654 464 023'));
    }

    public function testBuildChatIdEmptyWhenNoDigits(): void
    {
        $this->assertSame('', PaymentConfirmationNotifier::buildChatId('abc'));
    }

    // ── normalizeDigits ─────────────────────────────────────────

    public function testNormalizeDigitsStripsNonDigits(): void
    {
        $this->assertSame('34654464023', PaymentConfirmationNotifier::normalizeDigits('+34 654-464-023'));
    }

    // ── formatMessage ───────────────────────────────────────────

    public function testFormatMessageReplacesPlaceholders(): void
    {
        $text = PaymentConfirmationNotifier::formatMessage(
            '✅ {name}, hemos recibido tu pago de {amount}€. Plan activo {days} días.',
            'Prueba',
            1.0
        );
        $this->assertStringContainsString('Prueba', $text);
        $this->assertStringContainsString('1,00', $text);
        $this->assertStringContainsString('7 días', $text);
        $this->assertStringNotContainsString('{name}', $text);
    }

    // ── resolveTargetPhone ──────────────────────────────────────

    public function testResolveTargetPhonePrefersOverride(): void
    {
        $um = new UserManager($this->tmpDir);
        $um->createUser('654464023', 'pass123', 'user', 'Prueba');
        $notifier = new PaymentConfirmationNotifier(new Config($this->tmpDir), $um);

        $target = $notifier->resolveTargetPhone(1, ['to_phone_override' => '600000001']);
        $this->assertSame('600000001', $target);
    }

    public function testResolveTargetPhoneFallsBackToUsername(): void
    {
        $um = new UserManager($this->tmpDir);
        $um->createUser('654464023', 'pass123', 'user', 'Prueba');
        $notifier = new PaymentConfirmationNotifier(new Config($this->tmpDir), $um);

        $target = $notifier->resolveTargetPhone(1, []);
        $this->assertSame('654464023', $target);
    }

    public function testResolveTargetPhoneEmptyForNonNumericUsername(): void
    {
        $um = new UserManager($this->tmpDir);
        $um->createUser('admin', 'pass123', 'admin', 'Admin');
        $notifier = new PaymentConfirmationNotifier(new Config($this->tmpDir), $um);

        $this->assertSame('', $notifier->resolveTargetPhone(1, []));
    }

    public function testNotifyDisabledDoesNothing(): void
    {
        // enabled no está → notify() debe retornar sin lanzar y sin red
        $um = new UserManager($this->tmpDir);
        $um->createUser('654464023', 'pass123', 'user', 'Prueba');
        $notifier = new PaymentConfirmationNotifier(new Config($this->tmpDir), $um);

        $notifier->notify(1, 1.0);
        $this->addToAssertionCount(1);
    }
}
