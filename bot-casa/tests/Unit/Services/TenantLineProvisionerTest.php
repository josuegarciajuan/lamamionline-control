<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WasapBot\Core\LineProvisioningWahaInterface;
use WasapBot\Services\TenantLineProvisioner;

final class TenantLineProvisionerTest extends TestCase
{
    private string $root;
    private string $mapFile;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/line_provisioner_' . uniqid();
        @mkdir($this->root . '/data', 0700, true);
        $this->mapFile = $this->root . '/data/lines_map.json';
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function test_persists_marked_tenant_line_after_webhook_configuration(): void
    {
        $waha = new FakeProvisioningWaha();
        $result = $this->provisioner($waha)->create('34600123456', 'Casa', 7);

        self::assertTrue($result['ok']);
        self::assertSame(3020, $waha->configuredPort);
        self::assertSame('https://example.test/webhook.php', $waha->configuredUrl);

        $lineFile = $this->root . '/data/users/7/lines.json';
        $line = json_decode((string) file_get_contents($lineFile), true)[0];
        self::assertTrue($line['capture_native_outbound']);
        self::assertTrue($line['webhook_configured']);
        self::assertSame(7, json_decode((string) file_get_contents($this->mapFile), true)['600123456']);
    }

    public function test_configuration_failure_rolls_back_without_persisting_anything(): void
    {
        $waha = new FakeProvisioningWaha();
        $waha->configureResult = ['ok' => false];

        $result = $this->provisioner($waha)->create('34600123456', 'Casa', 7);

        self::assertFalse($result['ok']);
        self::assertStringContainsString('webhook', strtolower((string) ($result['error'] ?? '')));
        self::assertSame(3020, $waha->deletedPort);
        self::assertFileDoesNotExist($this->root . '/data/users/7/lines.json');
        self::assertFileDoesNotExist($this->mapFile);
        self::assertFileDoesNotExist($this->root . '/data/users/7/config.local.json');
    }

    private function provisioner(FakeProvisioningWaha $waha): TenantLineProvisioner
    {
        return new TenantLineProvisioner($this->root, $waha, $this->mapFile, 'https://example.test/webhook.php');
    }

    private function removeTree(string $directory): void
    {
        if (!is_dir($directory)) return;
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $directory . '/' . $entry;
            is_dir($path) ? $this->removeTree($path) : @unlink($path);
        }
        @rmdir($directory);
    }
}

final class FakeProvisioningWaha implements LineProvisioningWahaInterface
{
    /** @var array<string, mixed> */
    public array $configureResult = ['ok' => true];
    public int $configuredPort = 0;
    public string $configuredUrl = '';
    public int $deletedPort = 0;

    public function getStatus(): array { return ['next_port' => 3020]; }
    public function createInstance(int $port = 0): array { return ['ok' => true, 'port' => $port]; }
    public function configureSession(int $port, string $webhookUrl): array
    {
        $this->configuredPort = $port;
        $this->configuredUrl = $webhookUrl;
        return $this->configureResult;
    }
    public function deleteInstance(int $port): array
    {
        $this->deletedPort = $port;
        return ['ok' => true];
    }
    public function resetInstance(int $port): array { return ['ok' => true]; }
}
