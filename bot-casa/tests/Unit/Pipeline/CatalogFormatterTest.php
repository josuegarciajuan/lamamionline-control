<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\CatalogFormatter;
use WasapBot\Tests\Support\TmpEnv;
use WasapBot\Core\ConfigInterface;

/**
 * Unit tests for CatalogFormatter — adds photo URLs to the reply based on photo_action.
 */
final class CatalogFormatterTest extends TestCase
{
    private ConfigInterface $config;
    private ?TmpEnv $env = null;

    protected function setUp(): void
    {
        $this->env = new TmpEnv();
        $this->config = $this->env->config;
    }

    protected function tearDown(): void
    {
        $this->env?->cleanup();
        unset($this->env);
    }

    private function newFormatter(): CatalogFormatter
    {
        return new CatalogFormatter($this->config);
    }

    // ── Tests ─────────────────────────────────────────────────────────

    public function test_photo_action_none_does_not_modify_output(): void
    {
        $formatter = $this->newFormatter();
        $ctx = [
            'output_text'  => 'hola cariño 😘',
            'photo_action' => 'none',
            'girls_config' => [
                ['id' => 'g1', 'nombre' => 'Ana', 'fotos' => ['https://compartir.site/abc/', 'https://compartir.site/def/']],
            ],
            'message_text'  => '',
            'sent_photo_urls' => [],
        ];

        $result = $formatter->process($ctx);

        // output_text should remain unchanged since photo_action=none
        $this->assertStringNotContainsString('https://compartir.site/', $result['output_text']);
    }

    public function test_photo_action_catalog_adds_catalog_photos(): void
    {
        $formatter = $this->newFormatter();
        $girls = [
            ['id' => 'g1', 'nombre' => 'Ana', 'activa' => true, 'fotos' => ['https://compartir.site/ana1/']],
            ['id' => 'g2', 'nombre' => 'Belen', 'activa' => true, 'fotos' => ['https://compartir.site/belen1/']],
        ];
        $ctx = [
            'output_text'  => 'mira que chicas tengo 😏',
            'photo_action' => 'catalog',
            'girls_config' => $girls,
            'message_text'  => 'quiero ver chicas',
            'sent_photo_urls' => [],
        ];

        $result = $formatter->process($ctx);

        $this->assertStringContainsString('https://compartir.site/', $result['output_text']);
    }

    public function test_photo_action_selected_all_adds_all_photos_of_selected_girl(): void
    {
        $formatter = $this->newFormatter();
        $girls = [
            ['id' => 'g1', 'nombre' => 'Ana', 'fotos' => ['https://compartir.site/ana1/', 'https://compartir.site/ana2/']],
            ['id' => 'g2', 'nombre' => 'Belen', 'fotos' => ['https://compartir.site/belen1/']],
        ];
        $ctx = [
            'output_text'         => 'aqui las fotos de ana',
            'photo_action'        => 'selected_all',
            'girls_config'        => $girls,
            'selected_girl_name'  => 'Ana',
            'message_text'         => 'quiero fotos de ana',
            'sent_photo_urls'      => [],
        ];

        $result = $formatter->process($ctx);

        $this->assertStringContainsString('https://compartir.site/ana1/', $result['output_text']);
        $this->assertStringContainsString('https://compartir.site/ana2/', $result['output_text']);
        // Belen should NOT be in the output
        $this->assertStringNotContainsString('belen1', $result['output_text']);
    }

    public function test_photo_action_catalog_with_selected_girl_overrides_to_selected_all(): void
    {
        $formatter = $this->newFormatter();
        $girls = [
            ['id' => 'g1', 'nombre' => 'Ana', 'fotos' => ['https://compartir.site/ana1/', 'https://compartir.site/ana2/']],
            ['id' => 'g2', 'nombre' => 'Belen', 'fotos' => ['https://compartir.site/belen1/']],
        ];
        $ctx = [
            'output_text'         => 'te paso las fotos',
            'photo_action'        => 'catalog',
            'girls_config'        => $girls,
            'selected_girl_name'  => 'Ana',
            'message_text'         => 'fotos de ana',
            'sent_photo_urls'      => [],
        ];

        $result = $formatter->process($ctx);

        // When photo_action=catalog but selected_girl is set, should override to selected_all
        $this->assertStringContainsString('https://compartir.site/ana1/', $result['output_text']);
        $this->assertStringContainsString('https://compartir.site/ana2/', $result['output_text']);
        $this->assertStringNotContainsString('belen1', $result['output_text']);
    }
}
