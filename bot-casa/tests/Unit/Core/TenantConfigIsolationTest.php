<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WasapBot\Bot;
use WasapBot\Core\Config;

final class TenantConfigIsolationTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/wasapbot_tenant_' . uniqid('', true);
        mkdir($this->rootDir . '/data', 0700, true);
        copy(WASAPBOT_ROOT . '/config.dist.json', $this->rootDir . '/config.dist.json');
        file_put_contents($this->rootDir . '/config.local.json', json_encode([
            'openai' => ['api_key' => 'shared-openai-key'],
            'waha' => ['api_key' => 'shared-waha-key'],
            'telegram' => [
                'chat_ids' => ['root-chat-id'],
                'alert_enabled' => true,
            ],
            'routing' => [
                'lines' => [['last9' => 'root-line']],
                'sender_blacklist' => ['root-blacklisted-number'],
            ],
            'urls' => ['google_maps_location' => 'https://root.example/maps'],
            'files' => ['session_memory' => '/root/production/memory.ndjson'],
            'prompt' => ['sections' => ['tarifas' => 'ROOT PRIVATE TARIFFS']],
            'message_variants' => ['audio_auto_reply' => ['ROOT PRIVATE VARIANT']],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->rootDir);
    }

    public function test_first_tenant_request_initializes_without_copying_root_local_config(): void
    {
        $tenantDir = Bot::resolveUserConfigDir($this->rootDir, 42);
        new Config($tenantDir, $this->rootDir);

        self::assertDirectoryExists($tenantDir);
        self::assertFileExists($tenantDir . '/config.local.json');
        self::assertFileExists($this->rootDir . '/config.local.json');
    }

    public function test_tenant_config_uses_generic_defaults_and_runtime_platform_settings_only(): void
    {
        $tenantDir = Bot::resolveUserConfigDir($this->rootDir, 42);
        $config = new Config($tenantDir, $this->rootDir);

        self::assertSame('shared-openai-key', $config->get('openai.api_key'));
        self::assertSame('shared-waha-key', $config->get('waha.api_key'));
        self::assertNotSame('ROOT PRIVATE TARIFFS', $config->get('prompt.sections.tarifas'));
        self::assertNotSame(['ROOT PRIVATE VARIANT'], $config->get('message_variants.audio_auto_reply'));
        self::assertSame([], $config->get('telegram.chat_ids'));
        self::assertFalse((bool) $config->get('telegram.alert_enabled'));
        self::assertSame([], $config->get('routing.lines'));
        self::assertSame([], $config->get('routing.sender_blacklist'));
        self::assertSame('', $config->get('urls.google_maps_location'));
        self::assertSame('data/session_memory.ndjson', $config->get('files.session_memory'));

        $config->set('telegram.chat_ids', ['tenant-chat-id']);
        $config->set('urls.google_maps_location', 'https://tenant.example/maps');
        $config->save();

        $saved = json_decode((string) file_get_contents($tenantDir . '/config.local.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['tenant-chat-id'], $saved['telegram']['chat_ids']);
        self::assertSame('https://tenant.example/maps', $saved['urls']['google_maps_location']);
        self::assertArrayNotHasKey('openai', $saved);
        self::assertArrayNotHasKey('waha', $saved);
        self::assertArrayNotHasKey('files', $saved);
        self::assertSame([], $saved['routing']['lines']);
        self::assertSame([], $saved['routing']['sender_blacklist']);

        $root = json_decode((string) file_get_contents($this->rootDir . '/config.local.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['root-chat-id'], $root['telegram']['chat_ids']);
        self::assertSame('https://root.example/maps', $root['urls']['google_maps_location']);
    }

    public function test_legacy_cloned_tenant_file_is_scrubbed_before_values_are_used(): void
    {
        $tenantDir = Bot::resolveUserConfigDir($this->rootDir, 42);
        file_put_contents($tenantDir . '/config.local.json', json_encode([
            'openai' => ['api_key' => 'copied-key'],
            'telegram' => ['chat_ids' => ['copied-chat']],
            'routing' => ['lines' => [['last9' => 'copied-line']]],
            'files' => ['session_memory' => '/root/copied.ndjson'],
            'urls' => ['google_maps_location' => 'https://copied.example/maps'],
            'prompt' => [
                'sections' => [
                    'tarifas' => 'ROOT PRIVATE TARIFFS',
                    'servicios' => 'TENANT CUSTOM SERVICES',
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        new Config($tenantDir, $this->rootDir);
        $saved = json_decode((string) file_get_contents($tenantDir . '/config.local.json'), true, 512, JSON_THROW_ON_ERROR);
        $dist = json_decode((string) file_get_contents($this->rootDir . '/config.dist.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('openai', $saved);
        self::assertSame([], $saved['routing']['lines']);
        self::assertArrayNotHasKey('files', $saved);
        self::assertSame([], $saved['telegram']['chat_ids'] ?? null);
        self::assertSame('', $saved['urls']['google_maps_location'] ?? null);
        self::assertSame($dist['prompt']['sections']['tarifas'], $saved['prompt']['sections']['tarifas']);
        self::assertSame('TENANT CUSTOM SERVICES', $saved['prompt']['sections']['servicios']);
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
