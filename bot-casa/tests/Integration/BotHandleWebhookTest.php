<?php

declare(strict_types=1);

namespace WasapBot\Tests\Integration;

use PHPUnit\Framework\TestCase;
use WasapBot\Tests\Support\TmpEnv;
use WasapBot\Tests\Support\BotHarness;
use WasapBot\Tests\Support\PayloadFactory;

/**
 * Integration tests for Bot::handleWebhook — the full pipeline with faked services.
 */
final class BotHandleWebhookTest extends TestCase
{
    private TmpEnv $env;
    private BotHarness $harness;

    protected function setUp(): void
    {
        $this->env = new TmpEnv();
        $logger   = new \WasapBot\Core\FileLogger($this->env->config);
        $this->harness = new BotHarness($this->env->config, $logger);
    }

    protected function tearDown(): void
    {
        $this->env->cleanup();
    }

    /** Helper: pre-populate session memory so conversation is NOT "new". */
    private function seedConversationHistory(): void
    {
        $this->harness->sessionMemory->setRecords([
            [
                'thread_id'  => '000000000_34600123456',
                'phone'      => '34600123456',
                'user_msg'   => 'hola',
                'bot_reply'  => 'hola cari 😘',
                'ts'         => gmdate('Y-m-d\TH:i:s\Z', time() - 60),
                '_pending'   => false,
                'ya_enviado' => [],
                'bot_msg_count_recent' => 1,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Greeting fast-path (IntentRouter)
    // ─────────────────────────────────────────────────────────────────

    public function test_greeting_on_new_conversation_uses_fast_path(): void
    {
        $payload = PayloadFactory::greeting('hola');
        $this->harness->girlsService->girls = [
            ['id' => '1', 'nombre' => 'Carina', 'activa' => true],
        ];

        $result = $this->harness->bot->handleWebhook($payload);

        $this->assertNotNull($result, 'Should not return null for greeting');
        $this->assertNotEmpty($result['output_text'] ?? '', 'Should have greeting output');
        $this->assertEmpty($result['openai_raw_response'] ?? null, 'Should NOT have called LLM');
    }

    // ─────────────────────────────────────────────────────────────────
    //  Audio auto-reply
    // ─────────────────────────────────────────────────────────────────

    public function test_audio_message_gets_auto_reply_without_llm(): void
    {
        $payload = PayloadFactory::audio();
        $this->harness->girlsService->girls = [
            ['id' => '1', 'nombre' => 'Carina', 'activa' => true],
        ];

        $result = $this->harness->bot->handleWebhook($payload);

        $this->assertNotNull($result);
        $output = $result['output_text'] ?? '';
        // Audio reply can be any of the configured variants
        $this->assertNotEmpty($output, 'Should have audio reply');
        $this->assertEmpty($result['openai_raw_response'] ?? null, 'Should NOT have called LLM');
        $this->assertTrue($result['_send_ok'] ?? false, 'Should have sent the audio reply');
    }

    // ─────────────────────────────────────────────────────────────────
    //  First-contact greeting gate
    // ─────────────────────────────────────────────────────────────────

    public function test_first_contact_greeting_no_llm(): void
    {
        $payload = PayloadFactory::greeting('hola');
        $this->harness->girlsService->girls = [
            ['id' => '1', 'nombre' => 'Carina', 'activa' => true],
        ];

        $result = $this->harness->bot->handleWebhook($payload);

        $this->assertNotNull($result);
        $this->assertEmpty($result['openai_raw_response'] ?? null, 'Should NOT have called LLM for first contact');
    }

    // ─────────────────────────────────────────────────────────────────
    //  Full LLM path
    // ─────────────────────────────────────────────────────────────────

    public function test_full_llm_path_on_non_trivial_message(): void
    {
        $this->seedConversationHistory();

        $payload = PayloadFactory::text('cuanto cobras', '34600123456');
        $this->harness->girlsService->girls = [
            ['id' => '1', 'nombre' => 'Carina', 'activa' => true],
        ];

        // Script the LLM response
        $this->harness->openaiClient->nextChatResponse = [
            'user_visible_reply' => '40 rapidito, 50 media hora, 100 la hora',
            'lead_detected'      => false,
            'lead_confidence'    => 0,
            'eta_minutes'        => null,
            'photo_action'       => 'none',
            'lead_signals'       => ['none'],
        ];

        $result = $this->harness->bot->handleWebhook($payload);

        $this->assertNotNull($result);
        $this->assertStringContainsString('rapidito', $result['output_text'] ?? '');
        $this->assertNotEmpty($this->harness->openaiClient->lastChatArgs, 'Should have called the LLM');
        $this->assertTrue($result['_send_ok'] ?? false, 'Should have sent the reply');
    }

    // ─────────────────────────────────────────────────────────────────
    //  Thread paused
    // ─────────────────────────────────────────────────────────────────

    public function test_paused_thread_is_rejected_by_pause_gate(): void
    {
        $threadId = '000000000_34600123456';
        $this->env->pauseThread($threadId);

        $payload = PayloadFactory::text('hola', '34600123456');
        $this->harness->girlsService->girls = [
            ['id' => '1', 'nombre' => 'Carina', 'activa' => true],
        ];

        $result = $this->harness->bot->handleWebhook($payload);

        // PauseGate is the 6th input gate — it should return null for paused threads
        $this->assertNull($result, 'Paused thread should be rejected by PauseGate');
    }

    // ─────────────────────────────────────────────────────────────────
    //  Blacklist gate
    // ─────────────────────────────────────────────────────────────────

    public function test_blacklisted_sender_is_rejected(): void
    {
        $this->harness->blacklistService->blockedPhones = ['34600123456'];

        $payload = PayloadFactory::text('hola');
        $this->harness->girlsService->girls = [];

        $result = $this->harness->bot->handleWebhook($payload);

        $this->assertNull($result, 'Blacklisted sender should be rejected (null)');
    }

    // ─────────────────────────────────────────────────────────────────
    //  Telegram alert on lead
    // ─────────────────────────────────────────────────────────────────

    public function test_telegram_alert_fires_on_strong_lead(): void
    {
        // Seed full conversation history with girl selected, maps sent, etc.
        $this->harness->sessionMemory->setRecords([
            [
                'thread_id'          => '000000000_34600123456',
                'phone'              => '34600123456',
                'user_msg'           => 'hola',
                'bot_reply'          => 'hola cari 😘',
                'ts'                 => gmdate('Y-m-d\TH:i:s\Z', time() - 300),
                '_pending'           => false,
                'speaker_girl_name'  => 'Carina',
                'selected_girl_name' => 'Carina',
                'maps_sent'          => true,
                'ya_enviado'         => ['fotos', 'precios', 'ubicacion'],
                'bot_msg_count_recent' => 3,
            ],
            [
                'thread_id'          => '000000000_34600123456',
                'phone'              => '34600123456',
                'user_msg'           => 'cuanto cobras',
                'bot_reply'          => '40 rapidito, 50 media hora, 100 la hora',
                'ts'                 => gmdate('Y-m-d\TH:i:s\Z', time() - 120),
                '_pending'           => false,
                'speaker_girl_name'  => 'Carina',
                'selected_girl_name' => 'Carina',
                'maps_sent'          => true,
                'ya_enviado'         => ['fotos', 'precios', 'ubicacion'],
                'bot_msg_count_recent' => 4,
            ],
        ]);

        $payload = PayloadFactory::eta('voy en 20 min', '34600123456');
        $this->harness->girlsService->girls = [
            ['id' => '1', 'nombre' => 'Carina', 'activa' => true],
        ];
        $this->harness->telegramService->enabled = true;

        // Script lead response with confidence >= 0.98 to bypass gates A-C
        $this->harness->openaiClient->nextChatResponse = [
            'user_visible_reply' => 'vale papi te espero 😘',
            'lead_detected'      => true,
            'lead_confidence'    => 0.99,
            'eta_minutes'        => 20,
            'photo_action'       => 'none',
            'lead_signals'       => ['eta_explicit', 'selected_girl'],
        ];

        $result = $this->harness->bot->handleWebhook($payload);

        $this->assertNotNull($result);
        $this->assertGreaterThan(0, $this->harness->telegramService->alertCount, 'Should have fired Telegram alert');
    }

    // ─────────────────────────────────────────────────────────────────
    //  LLM empty response → fallback
    // ─────────────────────────────────────────────────────────────────

    public function test_empty_llm_response_uses_fallback(): void
    {
        $this->seedConversationHistory();

        $payload = PayloadFactory::text('como estas');
        $this->harness->girlsService->girls = [];

        // Script empty LLM response
        $this->harness->openaiClient->nextChatResponse = [
            'user_visible_reply' => '',
            'lead_detected'      => false,
            'lead_confidence'    => 0,
            'eta_minutes'        => null,
            'photo_action'       => 'none',
            'lead_signals'       => ['none'],
        ];

        $result = $this->harness->bot->handleWebhook($payload);

        $this->assertNotNull($result);
        $this->assertNotEmpty($result['output_text'] ?? '', 'Fallback should not be empty');
    }

    // ─────────────────────────────────────────────────────────────────
    //  Location URL injection requires selected_girl
    // ─────────────────────────────────────────────────────────────────

    public function test_location_url_not_injected_without_selected_girl(): void
    {
        $this->seedConversationHistory();

        $payload = PayloadFactory::text('donde estas');
        $this->harness->girlsService->girls = [
            ['id' => '1', 'nombre' => 'Carina', 'activa' => true],
        ];

        $this->harness->openaiClient->nextChatResponse = [
            'user_visible_reply' => 'burriana centro cari',
            'lead_detected'      => false,
            'lead_confidence'    => 0,
            'eta_minutes'        => null,
            'photo_action'       => 'none',
            'lead_signals'       => ['none'],
        ];

        $result = $this->harness->bot->handleWebhook($payload);

        $this->assertNotNull($result);
        $output = $result['output_text'] ?? '';
        $this->assertStringNotContainsString('maps.app.goo.gl', $output, 'Without selected girl, maps URL should not be injected');
        $this->assertStringNotContainsString('maps.google', $output);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Photo promise → inject photos (bot says "tendría más fotos")
    // ─────────────────────────────────────────────────────────────────

    public function test_photo_promise_injects_photos_when_llm_says_tendria_mas_fotos(): void
    {
        // Seed history: girl selected + photos already sent in this thread.
        $this->harness->sessionMemory->setRecords([
            [
                'thread_id'          => '000000000_34600123456',
                'phone'              => '34600123456',
                'user_msg'           => 'hola',
                'bot_reply'          => 'hola cari 😘',
                'ts'                 => gmdate('Y-m-d\TH:i:s\Z', time() - 300),
                '_pending'           => false,
                'speaker_girl_name'  => 'Valentina',
                'selected_girl_name' => 'Valentina',
                'bot_msg_count_recent' => 2,
            ],
        ]);

        $payload = PayloadFactory::text('cuanto tardas', '34600123456');
        $this->harness->girlsService->girls = [
            [
                'id'     => 'v4l3n',
                'nombre' => 'Valentina',
                'activa' => true,
                'fotos'  => [
                    'https://compartir.site/jf84p/',
                    'https://compartir.site/hmif9/',
                ],
            ],
        ];

        // Script the LLM to promise "more photos" WITHOUT setting photo_action
        // (the exact bug: the bot says "tendría más fotos" and sends nothing).
        $this->harness->openaiClient->nextChatResponse = [
            'user_visible_reply' => 'tendría más fotos cari',
            'lead_detected'      => false,
            'lead_confidence'    => 0,
            'eta_minutes'        => null,
            'photo_action'       => 'none',
            'lead_signals'       => ['none'],
        ];

        $result = $this->harness->bot->handleWebhook($payload);

        $this->assertNotNull($result);
        $output = $result['output_text'] ?? '';
        $this->assertStringContainsString('compartir.site', $output, 'Should inject photos when bot promises "tendría más fotos"');
    }

    public function test_no_photo_injection_when_llm_negates_photos(): void
    {
        // Seed history: girl selected.
        $this->harness->sessionMemory->setRecords([
            [
                'thread_id'          => '000000000_34600123456',
                'phone'              => '34600123456',
                'user_msg'           => 'hola',
                'bot_reply'          => 'hola cari 😘',
                'ts'                 => gmdate('Y-m-d\TH:i:s\Z', time() - 300),
                '_pending'           => false,
                'speaker_girl_name'  => 'Valentina',
                'selected_girl_name' => 'Valentina',
                'bot_msg_count_recent' => 2,
            ],
        ]);

        $payload = PayloadFactory::text('cuanto tardas', '34600123456');
        $this->harness->girlsService->girls = [
            [
                'id'     => 'v4l3n',
                'nombre' => 'Valentina',
                'activa' => true,
                'fotos'  => [
                    'https://compartir.site/jf84p/',
                    'https://compartir.site/hmif9/',
                ],
            ],
        ];

        // LLM correctly says it has NO more photos → should NOT inject.
        $this->harness->openaiClient->nextChatResponse = [
            'user_visible_reply' => 'no tengo más fotos cari, solo tengo esas',
            'lead_detected'      => false,
            'lead_confidence'    => 0,
            'eta_minutes'        => null,
            'photo_action'       => 'none',
            'lead_signals'       => ['none'],
        ];

        $result = $this->harness->bot->handleWebhook($payload);

        $this->assertNotNull($result);
        $output = $result['output_text'] ?? '';
        $this->assertStringNotContainsString('compartir.site', $output, 'Should NOT inject photos when bot negates having more');
    }
}
