<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\ImageSplitter;
use WasapBot\Tests\Support\TmpEnv;
use WasapBot\Core\ConfigInterface;

/**
 * Unit tests for ImageSplitter — separates image URLs from text.
 */
final class ImageSplitterTest extends TestCase
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

    private function newSplitter(): ImageSplitter
    {
        return new ImageSplitter($this->config);
    }

    // ── Tests ─────────────────────────────────────────────────────────

    public function test_text_without_urls_returns_single_message_with_is_first_true(): void
    {
        $splitter = $this->newSplitter();
        $ctx = ['output_text' => 'hola que tal?'];

        $result = $splitter->process($ctx);

        $this->assertCount(1, $result['splitted_messages']);
        $this->assertTrue($result['splitted_messages'][0]['__is_first']);
        $this->assertSame('hola que tal?', $result['splitted_messages'][0]['text']);
    }

    public function test_text_with_image_url_separates_image_into_its_own_message(): void
    {
        $splitter = $this->newSplitter();
        $ctx = [
            'output_text' => "mira estas fotos\nhttps://compartir.site/abc12/",
        ];

        $result = $splitter->process($ctx);

        $this->assertGreaterThanOrEqual(2, count($result['splitted_messages']));
        $this->assertTrue($result['splitted_messages'][0]['__is_first']);
        // Second message should be the image URL
        $this->assertFalse($result['splitted_messages'][1]['__is_first']);
        $this->assertStringContainsString('compartir.site', $result['splitted_messages'][1]['text']);
    }

    public function test_maps_urls_are_not_separated_from_first_message(): void
    {
        $splitter = $this->newSplitter();
        $ctx = [
            'output_text' => 'aqui la ubicacion https://maps.google.com/?q=Madrid',
        ];

        $result = $splitter->process($ctx);

        $this->assertCount(1, $result['splitted_messages']);
        $this->assertStringContainsString('maps.google.com', $result['splitted_messages'][0]['text']);
    }

    public function test_googl_maps_url_is_not_separated(): void
    {
        $splitter = $this->newSplitter();
        $ctx = [
            'output_text' => 'mira https://goo.gl/maps/abc123',
        ];

        $result = $splitter->process($ctx);

        $this->assertCount(1, $result['splitted_messages']);
    }

    public function test_multiple_images_produce_multiple_image_messages(): void
    {
        $splitter = $this->newSplitter();
        $ctx = [
            'output_text' => "fotos\nhttps://compartir.site/abc12/\nhttps://compartir.site/def34/",
        ];

        $result = $splitter->process($ctx);

        $this->assertGreaterThanOrEqual(3, count($result['splitted_messages']));
        // First message: text only
        $this->assertStringNotContainsString('compartir.site', $result['splitted_messages'][0]['text']);
        // Second and third: image URLs
        $this->assertFalse($result['splitted_messages'][1]['__is_first']);
        $this->assertFalse($result['splitted_messages'][2]['__is_first']);
    }

    public function test_jpg_extension_is_recognized_as_image(): void
    {
        $splitter = $this->newSplitter();
        $ctx = [
            'output_text' => 'foto https://example.com/photo.jpg',
        ];

        $result = $splitter->process($ctx);

        $this->assertGreaterThanOrEqual(2, count($result['splitted_messages']));
    }

    public function test_png_extension_is_recognized_as_image(): void
    {
        $splitter = $this->newSplitter();
        $ctx = [
            'output_text' => 'foto https://example.com/photo.png',
        ];

        $result = $splitter->process($ctx);

        $this->assertGreaterThanOrEqual(2, count($result['splitted_messages']));
    }

    public function test_webp_extension_is_recognized_as_image(): void
    {
        $splitter = $this->newSplitter();
        $ctx = [
            'output_text' => 'foto https://example.com/photo.webp',
        ];

        $result = $splitter->process($ctx);

        $this->assertGreaterThanOrEqual(2, count($result['splitted_messages']));
    }

    public function test_compartir_site_is_recognized_as_image(): void
    {
        $splitter = $this->newSplitter();
        $ctx = [
            'output_text' => 'foto https://compartir.site/xyz12/',
        ];

        $result = $splitter->process($ctx);

        $this->assertGreaterThanOrEqual(2, count($result['splitted_messages']));
    }

    public function test_cdn_hosts_are_recognized_as_image(): void
    {
        $splitter = $this->newSplitter();
        $ctx = [
            'output_text' => 'foto https://res.cloudinary.com/demo/image/upload/sample.jpg',
        ];

        $result = $splitter->process($ctx);

        $this->assertGreaterThanOrEqual(2, count($result['splitted_messages']));
    }

    public function test_no_text_after_removing_urls_only_image_messages(): void
    {
        $splitter = $this->newSplitter();
        $ctx = [
            'output_text' => 'https://compartir.site/abc12/',
        ];

        $result = $splitter->process($ctx);

        $this->assertGreaterThanOrEqual(1, count($result['splitted_messages']));
        // When there's no text left, the first message IS the image URL
        $this->assertStringContainsString('compartir.site', $result['splitted_messages'][0]['text']);
    }

    public function test_empty_output_text_returns_empty_splitted_messages(): void
    {
        $splitter = $this->newSplitter();
        $ctx = ['output_text' => ''];

        $result = $splitter->process($ctx);

        $this->assertEmpty($result['splitted_messages']);
    }
}
