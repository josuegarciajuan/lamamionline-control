<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\MessageExtractor;
use WasapBot\Tests\Support\PayloadFactory;
use WasapBot\Tests\Support\TmpEnv;

/**
 * MessageExtractor — extracts structured fields from a WAHA webhook payload.
 */
final class MessageExtractorTest extends TestCase
{
    private TmpEnv $env;

    protected function setUp(): void
    {
        $this->env = new TmpEnv();
    }

    protected function tearDown(): void
    {
        $this->env->cleanup();
    }

    /** Normal text message — message_text is the body, type is text. */
    public function test_normal_text_message(): void
    {
        $gate = new MessageExtractor($this->env->config);
        $ctx = ['body' => PayloadFactory::text('hola mundo', '34600123456', '000000000')];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame('hola mundo', $result['message_text'] ?? null);
        $this->assertSame('text', $result['message_type'] ?? null);
        $this->assertSame(0, $result['is_audio_i'] ?? null);
        $this->assertSame(0, $result['is_image_i'] ?? null);
    }

    /** from_phone is normalized: digits only, no @c.us suffix. */
    public function test_from_phone_is_normalized(): void
    {
        $gate = new MessageExtractor($this->env->config);
        $ctx = ['body' => PayloadFactory::text('hola', '34600123456', '000000000')];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame('34600123456', $result['from_phone'] ?? null);
    }

    /** GOWS/LID payload — from_phone is SenderAlt (real phone), not the LID. */
    public function test_lid_payload_from_phone_is_sender_alt(): void
    {
        $gate = new MessageExtractor($this->env->config);
        $ctx = ['body' => PayloadFactory::lid('hola', '34654464023', '277476546711679@lid', '000000000')];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        // from_phone should be SenderAlt (real phone), not the LID
        $this->assertSame('34654464023', $result['from_phone'] ?? null);
    }

    /** Audio message — is_audio_i=1, type=audio, text=placeholder. */
    public function test_audio_message(): void
    {
        $gate = new MessageExtractor($this->env->config);
        $ctx = ['body' => PayloadFactory::audio('34600123456', '000000000')];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame(1, $result['is_audio_i'] ?? null);
        $this->assertTrue($result['is_audio'] ?? false);
        $this->assertSame('audio', $result['message_type'] ?? null);
        // Placeholder from config
        $this->assertSame('[AUDIO]', $result['message_text'] ?? null);
    }

    /** Image without caption — is_image_i=1, type=image. */
    public function test_image_without_caption(): void
    {
        $gate = new MessageExtractor($this->env->config);
        $ctx = ['body' => PayloadFactory::image('34600123456', '000000000')];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame(1, $result['is_image_i'] ?? null);
        $this->assertTrue($result['is_image'] ?? false);
        $this->assertSame('image', $result['message_type'] ?? null);
    }

    /** Location message — is_location_i=1, type=location, text='📍 Ubicación'. */
    public function test_location_message_sets_location_flag_and_placeholder(): void
    {
        $gate = new MessageExtractor($this->env->config);
        $ctx = ['body' => PayloadFactory::location('34600123456', '000000000')];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame(1, $result['is_location_i'] ?? null);
        $this->assertTrue($result['is_location'] ?? false);
        $this->assertSame('location', $result['message_type'] ?? null);
        $this->assertSame('📍 Ubicación', $result['message_text'] ?? null);
    }

    /** Image with caption — is_image_i=0 (treated as text because it has caption). */
    public function test_image_with_caption_is_handled_as_text(): void
    {
        $gate = new MessageExtractor($this->env->config);
        $ctx = [
            'body' => [
                'event'   => 'message',
                'me'      => ['id' => '000000000@c.us'],
                'payload' => [
                    'id'        => 'WAMID_IMG_CAP',
                    'from'      => '34600123456@c.us',
                    'to'        => '000000000@c.us',
                    'type'      => 'image',
                    'caption'   => 'mira esta foto',
                    'timestamp' => time(),
                    '_data' => [
                        'Info'      => ['MediaType' => 'image'],
                        'Message'   => ['imageMessage' => ['url' => 'https://example.com/img.jpg']],
                    ],
                ],
            ],
        ];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        // Image with caption: has text, so is_image_i = 0
        $this->assertSame(0, $result['is_image_i'] ?? null);
        $this->assertFalse($result['is_image'] ?? false);
    }

    /** Coalesced text present — uses that instead of extracting from payload. */
    public function test_uses_coalesced_text_when_present(): void
    {
        $gate = new MessageExtractor($this->env->config);
        $ctx = [
            'body'            => PayloadFactory::text('texto original', '34600123456', '000000000'),
            '__coalesced_text' => 'msg1 | msg2 | msg3',
        ];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame('msg1 | msg2 | msg3', $result['message_text'] ?? null);
    }

    /** message_id is extracted from payload. */
    public function test_message_id_is_extracted(): void
    {
        $gate = new MessageExtractor($this->env->config);
        $ctx = ['body' => PayloadFactory::text('hola', '34600123456', '000000000', 'WAMID_SPECIAL')];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame('WAMID_SPECIAL', $result['message_id'] ?? null);
    }

    /** Timestamp is extracted from payload. */
    public function test_timestamp_is_extracted(): void
    {
        $ts = time();

        $gate = new MessageExtractor($this->env->config);
        $ctx = [
            'body' => [
                'event'   => 'message',
                'me'      => ['id' => '000000000@c.us'],
                'payload' => [
                    'id'        => 'WAMID_TS',
                    'from'      => '34600123456@c.us',
                    'body'      => 'hola',
                    'type'      => 'text',
                    'timestamp' => $ts,
                ],
            ],
        ];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame($ts, $result['timestamp'] ?? null);
    }

    /** Fallback text: no text in payload but text is in ctx['message']. */
    public function test_fallback_text_from_ctx_message(): void
    {
        $gate = new MessageExtractor($this->env->config);
        $ctx = [
            'body' => [
                'event'   => 'message',
                'me'      => ['id' => '000000000@c.us'],
                'payload' => [
                    'id'   => 'WAMID_FALL',
                    'from' => '34600123456@c.us',
                    'type' => 'text',
                ],
            ],
            'message' => 'texto desde ctx',
        ];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame('texto desde ctx', $result['message_text'] ?? null);
    }

    /** No text at all — gets the no_text_placeholder. */
    public function test_no_text_gets_placeholder(): void
    {
        $gate = new MessageExtractor($this->env->config);
        $ctx = [
            'body' => [
                'event'   => 'message',
                'me'      => ['id' => '000000000@c.us'],
                'payload' => [
                    'id'   => 'WAMID_EMPTY',
                    'from' => '34600123456@c.us',
                    'type' => 'text',
                ],
            ],
        ];

        $result = $gate->process($ctx);

        $this->assertNotNull($result);
        $this->assertSame('[SIN_TEXTO]', $result['message_text'] ?? null);
    }

    /** Gate name */
    public function test_gate_name_is_correct(): void
    {
        $gate = new MessageExtractor($this->env->config);
        $this->assertSame('MessageExtractor', $gate->name());
    }
}
