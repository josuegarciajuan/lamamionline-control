<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WasapBot\Bot;
use WasapBot\Core\Config;

/**
 * Verifica que seedRoutingLines() rellena routing.lines en la config runtime:
 *  - Tenants (userId > 1): desde data/users/{id}/lines.json.
 *  - Admin (userId === 1): desde su lines.json, con fallback a la config raíz
 *    (líneas de casa gestionadas por el panel).
 *  - Nunca sobrescribe líneas ya configuradas (aislamiento de ediciones tenant).
 */
final class RoutingLinesSeedTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/wasapbot_routing_' . uniqid('', true);
        mkdir($this->rootDir . '/data', 0700, true);
        copy(WASAPBOT_ROOT . '/config.dist.json', $this->rootDir . '/config.dist.json');
        file_put_contents($this->rootDir . '/config.local.json', json_encode([
            'routing' => [
                'lines' => [
                    ['last9' => '111111111', 'port' => 3000, 'label' => 'pos1', 'ai_provider' => 'deepseek', 'enabled' => true],
                ],
                'sender_blacklist' => ['root-blacklisted'],
            ],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->rootDir);
    }

    public function test_admin_seeds_routing_lines_from_root_config_when_own_config_is_empty(): void
    {
        $userDir = Bot::resolveUserConfigDir($this->rootDir, 1);
        $config = new Config($userDir, $this->rootDir);

        self::assertSame([], $config->get('routing.lines'));

        Bot::seedRoutingLines($this->rootDir, 1, $config);

        $lines = $config->get('routing.lines');
        self::assertIsArray($lines);
        self::assertCount(1, $lines);
        self::assertSame('111111111', $lines[0]['last9'] ?? null);

        // Persisted so subsequent requests keep the same runtime config.
        $saved = json_decode((string) file_get_contents($userDir . '/config.local.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $saved['routing']['lines']);
    }

    public function test_admin_prefers_user_lines_json_over_root_fallback(): void
    {
        $userDir = Bot::resolveUserConfigDir($this->rootDir, 1);
        // Simula un lines.json gestionado por api/lines.php.
        file_put_contents($userDir . '/lines.json', json_encode([
            ['last9' => '222222222', 'port' => 3004, 'label' => 'pos2', 'ai_provider' => 'deepseek'],
        ], JSON_THROW_ON_ERROR));
        $config = new Config($userDir, $this->rootDir);

        Bot::seedRoutingLines($this->rootDir, 1, $config);

        $lines = $config->get('routing.lines');
        self::assertIsArray($lines);
        self::assertCount(1, $lines);
        self::assertSame('222222222', $lines[0]['last9'] ?? null);
    }

    public function test_tenant_seeds_from_lines_json_only(): void
    {
        $userDir = Bot::resolveUserConfigDir($this->rootDir, 42);
        file_put_contents($userDir . '/lines.json', json_encode([
            ['last9' => '333333333', 'port' => 3020, 'label' => 'tenant1'],
        ], JSON_THROW_ON_ERROR));
        $config = new Config($userDir, $this->rootDir);

        Bot::seedRoutingLines($this->rootDir, 42, $config);

        $lines = $config->get('routing.lines');
        self::assertIsArray($lines);
        self::assertCount(1, $lines);
        self::assertSame('333333333', $lines[0]['last9'] ?? null);
    }

    public function test_existing_routing_lines_are_not_overwritten(): void
    {
        $userDir = Bot::resolveUserConfigDir($this->rootDir, 7);
        $config = new Config($userDir, $this->rootDir);
        $config->set('routing.lines', [['last9' => '444444444', 'port' => 3030, 'label' => 'existing']]);
        $config->save();

        Bot::seedRoutingLines($this->rootDir, 7, $config);

        $lines = $config->get('routing.lines');
        self::assertIsArray($lines);
        self::assertCount(1, $lines);
        self::assertSame('444444444', $lines[0]['last9'] ?? null);
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $entry) {
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeTree($path) : unlink($path);
        }
        rmdir($dir);
    }
}
