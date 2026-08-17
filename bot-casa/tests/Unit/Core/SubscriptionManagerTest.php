<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WasapBot\Core\SubscriptionManager;
use WasapBot\Core\UserManager;

/**
 * Unit tests for SubscriptionManager — trials, weekly activation/renewal,
 * expiry and payment recording (with transaction idempotency support).
 *
 * Uses an isolated temp dir so production data/users.json is never touched.
 */
final class SubscriptionManagerTest extends TestCase
{
    private string $tmpDir;
    private UserManager $um;
    private SubscriptionManager $subs;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/wasapbot_sub_' . uniqid();
        @mkdir($this->tmpDir . '/data', 0700, true);
        $this->um = new UserManager($this->tmpDir);
        $this->subs = new SubscriptionManager($this->um);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $this->removeTree($this->tmpDir);
        }
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $f) {
            $p = $dir . '/' . $f;
            is_dir($p) ? $this->removeTree($p) : unlink($p);
        }
        rmdir($dir);
    }

    public function testNewUserStartsWithTrial(): void
    {
        $res = $this->um->createUser('34600000000', 'pass123', 'user', 'Test User');
        $this->assertTrue($res['ok'] ?? false);
        $user = $res['user'] ?? [];
        $this->assertSame('trial', $user['subscription_status'] ?? '');

        $status = $this->subs->getStatus((int) $user['id']);
        $this->assertSame('trial', $status['status']);
        $this->assertFalse($status['isExpired']);
        $this->assertTrue($status['canUseBot']);
        $this->assertSame(10, $status['totalDays']);
    }

    public function testExpiredTrialBlocksBot(): void
    {
        $res = $this->um->createUser('34600000001', 'pass123', 'user', 'Old User');
        $this->assertTrue($res['ok'] ?? false);
        $userId = (int) ($res['user']['id'] ?? 0);

        // Force trial_end in the past
        $this->um->updateUser($userId, ['trial_end' => '2020-01-01T00:00:00+00:00']);

        $status = $this->subs->getStatus($userId);
        $this->assertSame('expired', $status['status']);
        $this->assertTrue($status['isExpired']);
        $this->assertFalse($status['canUseBot']);
        $this->assertTrue($this->subs->isExpired($userId));
    }

    public function testActivateWeeklySetsActive(): void
    {
        $res = $this->um->createUser('34600000002', 'pass123', 'user', 'Payer');
        $userId = (int) ($res['user']['id'] ?? 0);

        $act = $this->subs->activateWeekly($userId, 1);
        $this->assertTrue($act['ok'] ?? false);

        $status = $this->subs->getStatus($userId);
        $this->assertSame('active', $status['status']);
        $this->assertFalse($status['isExpired']);
        $this->assertTrue($status['canUseBot']);
        $this->assertSame(7, $status['totalDays']);
    }

    public function testRenewalExtendsEndDate(): void
    {
        $res = $this->um->createUser('34600000003', 'pass123', 'user', 'Renewer');
        $userId = (int) ($res['user']['id'] ?? 0);

        $this->subs->activateWeekly($userId, 1);
        $first = $this->subs->getStatus($userId);
        $end1 = new \DateTimeImmutable((string) $first['subscriptionEnd']);

        $this->subs->activateWeekly($userId, 1);
        $second = $this->subs->getStatus($userId);
        $end2 = new \DateTimeImmutable((string) $second['subscriptionEnd']);

        // Second week must extend beyond the first week end
        $this->assertGreaterThan($end1, $end2);
        $this->assertSame('active', $second['status']);
    }

    public function testPaymentRecordedWithTransactionId(): void
    {
        $res = $this->um->createUser('34600000004', 'pass123', 'user', 'Payer2');
        $userId = (int) ($res['user']['id'] ?? 0);

        $pay = $this->subs->recordPayment($userId, 100.0, 'paypal', 'TXN-12345');
        $this->assertTrue($pay['ok'] ?? false);

        $user = $this->um->getUser($userId);
        $payments = $user['payments'] ?? [];
        $this->assertCount(1, $payments);
        $this->assertSame('TXN-12345', $payments[0]['transaction_id'] ?? '');
        $this->assertSame('paypal', $payments[0]['method'] ?? '');
    }

    public function testExpiredPayerActivationRestoresAccess(): void
    {
        $res = $this->um->createUser('34600000005', 'pass123', 'user', 'Lapsed');
        $userId = (int) ($res['user']['id'] ?? 0);

        // Force expiry
        $this->um->updateUser($userId, ['trial_end' => '2020-01-01T00:00:00+00:00']);
        $this->assertTrue($this->subs->isExpired($userId));

        // Pay → access restored
        $this->subs->activateWeekly($userId, 1);
        $this->subs->recordPayment($userId, 100.0, 'paypal', 'TXN-RENEW');

        $status = $this->subs->getStatus($userId);
        $this->assertSame('active', $status['status']);
        $this->assertFalse($status['isExpired']);
        $this->assertTrue($status['canUseBot']);
    }

    public function testAdminAlwaysUnlimited(): void
    {
        $res = $this->um->createUser('admin', 'pass123', 'admin', 'Admin');
        $this->assertTrue($res['ok'] ?? false);
        $userId = (int) ($res['user']['id'] ?? 0);

        $status = $this->subs->getStatus($userId);
        $this->assertSame('unlimited', $status['status']);
        $this->assertTrue($status['canUseBot']);
        $this->assertFalse($status['isExpired']);
    }
}
