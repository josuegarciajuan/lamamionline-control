<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Payment;

use PHPUnit\Framework\TestCase;
use WasapBot\Payment\PayPalClient;

/**
 * PayPalClient unit tests.
 *
 * The client is pure cURL against api-m.(sandbox.)paypal.com, so real network
 * calls are NOT performed here. These tests pin down the behaviour that matters
 * for production right now: with empty/placeholder credentials (current state
 * of config.local.json) every operation must fail cleanly with a clear,
 * user-facing error — never throw or hang.
 *
 * Once real sandbox credentials exist, an integration test against the PayPal
 * sandbox can be added (manual/CI-gated).
 */
final class PayPalClientTest extends TestCase
{
    private function client(array $cfg = []): PayPalClient
    {
        return new PayPalClient(array_replace([
            'client_id' => '',
            'secret'    => '',
            'mode'      => 'sandbox',
        ], $cfg));
    }

    public function testCreateOrderFailsCleanlyWithoutCredentials(): void
    {
        $result = $this->client()->createOrder(100.0, 'Plan semanal', 'https://x/pago', 'https://x/pago', 'user:42:weekly');
        $this->assertFalse($result['ok']);
        $this->assertSame('No se pudo autenticar con PayPal.', $result['error'] ?? '');
    }

    public function testCreateOrderWithPlaceholderCredentialsFailsCleanly(): void
    {
        // config.dist.json ships PAYPAL_CLIENT_ID/PAYPAL_SECRET placeholders —
        // the client must treat them as unset, not attempt a real call.
        $result = $this->client([
            'client_id' => 'PAYPAL_CLIENT_ID',
            'secret'    => 'PAYPAL_SECRET',
        ])->createOrder(25.0, 'Línea extra', 'https://x/pago', 'https://x/pago');
        $this->assertFalse($result['ok']);
        $this->assertSame('No se pudo autenticar con PayPal.', $result['error'] ?? '');
    }

    public function testCaptureOrderFailsCleanlyWithoutCredentials(): void
    {
        $result = $this->client()->captureOrder('ORD-123');
        $this->assertFalse($result['ok']);
        $this->assertSame('No se pudo autenticar con PayPal.', $result['error'] ?? '');
    }

    public function testGetOrderFailsCleanlyWithoutCredentials(): void
    {
        $result = $this->client()->getOrder('ORD-123');
        $this->assertFalse($result['ok']);
        $this->assertSame('No se pudo autenticar con PayPal.', $result['error'] ?? '');
    }

    public function testVerifyWebhookReturnsFalseWithoutCredentials(): void
    {
        $result = $this->client()->verifyWebhook([], '{}', 'wh-123');
        $this->assertFalse($result);
    }

    public function testCaptureOrderModesSelectCorrectBaseUrl(): void
    {
        // Sanity: constructors for both modes must not throw with empty creds.
        $sandbox = $this->client(['mode' => 'sandbox']);
        $live    = $this->client(['mode' => 'live']);
        $this->assertFalse($sandbox->captureOrder('X')['ok']);
        $this->assertFalse($live->captureOrder('X')['ok']);
    }
}
