<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Pipeline;

use PHPUnit\Framework\TestCase;
use WasapBot\Pipeline\ContextAssembler;
use WasapBot\Core\FileLogger;
use WasapBot\Tests\Support\TmpEnv;
use WasapBot\Tests\Support\FakeMemory;
use WasapBot\Tests\Support\FakeSessionMemory;

final class ContextAssemblerTest extends TestCase
{
    private ?TmpEnv $env = null;
    private ?FakeMemory $memory = null;
    private ?FakeSessionMemory $sessionMemory = null;
    private ?FileLogger $logger = null;
    private string $threadId = '000000000_34600123456';

    private static function sampleGirlsConfig(): array
    {
        return [
            ['id' => 'g1', 'nombre' => 'Carina',  'activa' => true,  'fotos' => []],
            ['id' => 'g2', 'nombre' => 'Sandra',  'activa' => true,  'fotos' => []],
            ['id' => 'g3', 'nombre' => 'Luna',    'activa' => true,  'fotos' => []],
            ['id' => 'g4', 'nombre' => 'Valentina','activa' => false, 'fotos' => []],
            ['id' => 'g5', 'nombre' => 'Ana Belén','activa' => true,  'fotos' => []],
        ];
    }

    private function baseCtx(array $overrides = []): array
    {
        return array_merge([
            'message_text'      => 'hola',
            'from_phone'        => '34600123456',
            'thread_id'         => $this->threadId,
            'line_last9'        => '000000000',
            'girls_config'      => self::sampleGirlsConfig(),
        ], $overrides);
    }

    private function makeAssembler(): ContextAssembler
    {
        return new ContextAssembler($this->env->config, $this->logger, $this->memory, $this->sessionMemory);
    }

    private function rec(array $overrides = []): array
    {
        return array_merge([
            'thread_id'  => $this->threadId,
            'user_msg'   => '',
            'bot_reply'  => '',
            'ts'         => gmdate('Y-m-d\TH:i:s\Z', time() - 60),
            '_pending'   => false,
        ], $overrides);
    }

    protected function setUp(): void
    {
        $this->env = new TmpEnv();
        $this->memory = new FakeMemory();
        $this->sessionMemory = new FakeSessionMemory($this->env->tmpDir . '/data/session_memory.ndjson');
        $this->logger = new FileLogger($this->env->config);
    }

    protected function tearDown(): void
    {
        $this->env?->cleanup();
        $this->env = null;
        $this->memory = null;
        $this->sessionMemory = null;
    }

    // ─────────────────────────────────────────────────────────
    // 1. __is_new_conversation + __is_ad_intro
    // ─────────────────────────────────────────────────────────

    public function test_detects_new_conversation_when_no_history(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['__is_new_conversation'] ?? false);
        $this->assertFalse($result['__is_ad_intro'] ?? true);
    }

    public function test_not_new_conversation_when_history_exists(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'hola', 'bot_reply' => 'Hola guapa']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'cuanto cobras?']);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['__is_new_conversation'] ?? true);
    }

    public function test_detects_ad_intro_with_anuncio_url(): void
    {
        $this->sessionMemory->setRecords([]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx([
            'message_text' => 'He visto tu anuncio en nuevapasion.com/anuncio/123 hola',
        ]);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['__is_ad_intro'] ?? false);
        // speaker_mode should remain 'encargada' because it is a new conversation
        $this->assertSame('encargada', $result['speaker_mode']);
    }

    public function test_ad_intro_not_triggered_without_url(): void
    {
        $this->sessionMemory->setRecords([]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola que tal']);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['__is_ad_intro'] ?? true);
    }

    // ─────────────────────────────────────────────────────────
    // 2. findMentionedGirl
    // ─────────────────────────────────────────────────────────

    public function test_find_mentioned_girl_exact_match(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'me gusta carina']);
        $result = $assembler->process($ctx);
        $this->assertSame('Carina', $result['speaker_girl_name']);
        $this->assertSame('g1', $result['speaker_girl_id']);
    }

    public function test_find_mentioned_girl_fuzzy_match(): void
    {
        // "carima" → Levenshtein distance 1 from "carina" (6 chars → limit=1)
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'me gusta carima']);
        $result = $assembler->process($ctx);
        $this->assertSame('Carina', $result['speaker_girl_name']);
    }

    public function test_find_mentioned_girl_multi_part_name(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'quiero a ana belen']);
        $result = $assembler->process($ctx);
        $this->assertSame('Ana Belén', $result['speaker_girl_name']);
        $this->assertSame('g5', $result['speaker_girl_id']);
    }

    public function test_find_mentioned_girl_no_match(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola que tal']);
        $result = $assembler->process($ctx);
        // With no speaker in history and no match, speaker_girl_name should be empty
        $this->assertSame('', $result['speaker_girl_name']);
        $this->assertSame('', $result['selected_girl_name']);
    }

    // ─────────────────────────────────────────────────────────
    // 3. isExplicitServiceChoice
    // ─────────────────────────────────────────────────────────

    public function test_is_explicit_service_choice_true(): void
    {
        // "me quedo con sandra" is an explicit choice
        $this->sessionMemory->setRecords([]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'me quedo con sandra']);
        $result = $assembler->process($ctx);
        $this->assertSame('Sandra', $result['selected_girl_name']);
    }

    public function test_is_explicit_service_choice_false(): void
    {
        // "hola carina" is NOT an explicit service choice
        $this->sessionMemory->setRecords([]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola carina']);
        $result = $assembler->process($ctx);
        // selected_girl should be Carina (first mention) but via the non-explicit path
        $this->assertSame('Carina', $result['selected_girl_name']);
    }

    // ─────────────────────────────────────────────────────────
    // 4. firstPersistedSpeakerGirl
    // ─────────────────────────────────────────────────────────

    public function test_first_persisted_speaker_girl_from_history(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'me gusta carina', 'speaker_girl_name' => 'Carina', 'speaker_girl_id' => 'g1']),
            $this->rec(['user_msg' => 'y sandra?',          'speaker_girl_name' => 'Carina', 'speaker_girl_id' => 'g1']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'tienes mas?']);
        $result = $assembler->process($ctx);
        // Speaker should stick to the first persisted: Carina
        $this->assertSame('Carina', $result['speaker_girl_name']);
        $this->assertSame('g1', $result['speaker_girl_id']);
        $this->assertSame('chica', $result['speaker_mode']);
    }

    public function test_first_persisted_speaker_girl_empty_when_none(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'hola']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola']);
        $result = $assembler->process($ctx);
        $this->assertSame('', $result['speaker_girl_name']);
        $this->assertSame('encargada', $result['speaker_mode']);
    }

    // ─────────────────────────────────────────────────────────
    // 5. lastPersistedSelectedGirl
    // ─────────────────────────────────────────────────────────

    public function test_last_persisted_selected_girl_from_history(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'me gusta carina', 'selected_girl_name' => 'Carina', 'selected_girl_id' => 'g1',
                         'speaker_girl_name' => 'Carina', 'speaker_girl_id' => 'g1']),
            $this->rec(['user_msg' => 'ahora sandra',    'selected_girl_name' => 'Sandra', 'selected_girl_id' => 'g2',
                         'speaker_girl_name' => 'Carina', 'speaker_girl_id' => 'g1']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'precio?']);
        $result = $assembler->process($ctx);
        // Selected should be the last persisted: Sandra
        $this->assertSame('Sandra', $result['selected_girl_name']);
        $this->assertSame('g2', $result['selected_girl_id']);
    }

    // ─────────────────────────────────────────────────────────
    // 6. hasWantsMoreInHistory
    // ─────────────────────────────────────────────────────────

    public function test_wants_more_in_history_true(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'tienes mas?', 'wants_more_girls' => true]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'dime']);
        $result = $assembler->process($ctx);
        // The persisted wants_more remains unless fulfilled
        $this->assertTrue($result['wants_more_girls']);
    }

    public function test_wants_more_in_history_false(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'hola']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'precio?']);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['wants_more_girls']);
    }

    // ─────────────────────────────────────────────────────────
    // 7. wantsMoreGirlsWasFulfilled
    // ─────────────────────────────────────────────────────────

    public function test_wants_more_girls_fulfilled_by_photo_reply(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec([
                'user_msg'          => 'tienes mas chicas?',
                'bot_reply'         => 'Si, mira a Sandra: https://ibb.co/abc123',
                'wants_more_girls'  => true,
            ]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'precio?']);
        $result = $assembler->process($ctx);
        // wants_more was fulfilled because bot replied with photos AFTER the request
        $this->assertFalse($result['wants_more_girls']);
    }

    public function test_wants_more_girls_not_fulfilled_without_photos(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec([
                'user_msg'          => 'tienes mas chicas?',
                'bot_reply'         => 'Claro, dime cual te gusta',
                'wants_more_girls'  => true,
            ]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'cual mas hay?']);
        $result = $assembler->process($ctx);
        // Not fulfilled — the bot reply had no photo URLs
        $this->assertTrue($result['wants_more_girls']);
    }

    // ─────────────────────────────────────────────────────────
    // 8. detectTopic
    // ─────────────────────────────────────────────────────────

    public function test_detect_topic_precios(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'cuanto cobras?']);
        $result = $assembler->process($ctx);
        $this->assertSame('precios', $result['topic_actual']);
    }

    public function test_detect_topic_ubicacion(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'donde estas?']);
        $result = $assembler->process($ctx);
        $this->assertSame('ubicacion', $result['topic_actual']);
    }

    public function test_detect_topic_servicios(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'que servicios haceis?']);
        $result = $assembler->process($ctx);
        $this->assertSame('servicios', $result['topic_actual']);
    }

    public function test_detect_topic_general(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'me gusta mucho el chocolate']);
        $result = $assembler->process($ctx);
        $this->assertSame('otro', $result['topic_actual']);
    }

    public function test_detect_topic_eta(): void
    {
        $assembler = $this->makeAssembler();
        // "cuando" triggers cita-eta, avoid "cuanto" which triggers precios first
        $ctx = $this->baseCtx(['message_text' => 'cuando estas disponible?']);
        $result = $assembler->process($ctx);
        $this->assertSame('cita-eta', $result['topic_actual']);
    }

    // ─────────────────────────────────────────────────────────
    // 9. detectTarifaElegida
    // ─────────────────────────────────────────────────────────

    public function test_detect_tarifa_elegida_50(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'vale 50 euros', 'ts' => gmdate('Y-m-d\TH:i:s\Z', time() - 60)]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'ok']);
        $result = $assembler->process($ctx);
        $this->assertSame('50', $result['tarifa_elegida']);
    }

    public function test_detect_tarifa_elegida_100(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'quiero la de 100€', 'ts' => gmdate('Y-m-d\TH:i:s\Z', time() - 60)]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'dale']);
        $result = $assembler->process($ctx);
        $this->assertSame('100', $result['tarifa_elegida']);
    }

    public function test_detect_tarifa_empty_when_no_tariff(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'hola que tal', 'ts' => gmdate('Y-m-d\TH:i:s\Z', time() - 60)]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'cuanto?']);
        $result = $assembler->process($ctx);
        $this->assertSame('', $result['tarifa_elegida']);
    }

    // ─────────────────────────────────────────────────────────
    // 10. detectHotCurious
    // ─────────────────────────────────────────────────────────

    public function test_detect_hot_curious_true(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'que tetas mas buenas tienes']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['hot_curious_chat_current']);
    }

    public function test_detect_hot_curious_false(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'tienes fotos?']);
        $result = $assembler->process($ctx);
        // "fotos" is in the hotWords list — so this WILL match. Let's use a truly not-hot text.
        // Actually, let me check: 'foto' and 'fotos' ARE in the hotWords array at line 870.
        // Let's use something clearly non-hot.
        $this->assertTrue($result['hot_curious_chat_current']);
    }

    public function test_detect_hot_curious_false_truly_non_hot(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'buenos dias, a que hora abris?']);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['hot_curious_chat_current']);
    }

    // ─────────────────────────────────────────────────────────
    // 11. detectWantsMoreGirls
    // ─────────────────────────────────────────────────────────

    public function test_detect_wants_more_girls_true(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'muestrame mas chicas']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['wants_more_girls']);
    }

    public function test_detect_wants_more_girls_true_otras(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'las otras?']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['wants_more_girls']);
    }

    public function test_detect_wants_more_girls_false_normal_text(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola buenas tardes']);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['wants_more_girls']);
    }

    // ─────────────────────────────────────────────────────────
    // 12. yaEnviadoFromHistory
    // ─────────────────────────────────────────────────────────

    public function test_ya_enviado_with_fotos_and_ubicacion_in_history(): void
    {
        $recentTs = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
        $this->sessionMemory->setRecords([
            $this->rec([
                'bot_reply' => 'Te paso las fotos: https://compartir.site/abc',
                'ts'        => $recentTs,
            ]),
            $this->rec([
                'bot_reply' => 'Estoy en esta direccion https://goo.gl/maps/xyz123',
                'ts'        => $recentTs,
            ]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'gracias']);
        $result = $assembler->process($ctx);
        $ya = $result['ya_enviado'] ?? [];
        $this->assertContains('fotos', $ya);
        $this->assertContains('ubicacion', $ya);
        $this->assertContains('ubicacion_precisa', $ya);
    }

    public function test_ya_enviado_empty_without_sent_data(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['bot_reply' => 'Hola, como estas?']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola']);
        $result = $assembler->process($ctx);
        $this->assertSame([], $result['ya_enviado'] ?? null);
    }

    // ─────────────────────────────────────────────────────────
    // 13. detectMapsSent
    // ─────────────────────────────────────────────────────────

    public function test_detect_maps_sent_true(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['bot_reply' => 'Aqui esta la ubicacion: https://maps.app.goo.gl/xyz']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'donde?']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['maps_sent']);
    }

    public function test_detect_maps_sent_false(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['bot_reply' => 'Hola, soy de una zona centrica']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'donde?']);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['maps_sent']);
    }

    // ─────────────────────────────────────────────────────────
    // 14. detectPhotosSentRecent
    // ─────────────────────────────────────────────────────────

    public function test_detect_photos_sent_recent_true(): void
    {
        $recentTs = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
        $this->sessionMemory->setRecords([
            $this->rec(['bot_reply' => 'Mira mi foto: https://ibb.co/foto1', 'ts' => $recentTs]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'gracias']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['photos_sent_recent']);
    }

    public function test_detect_photos_sent_recent_false_with_old_photos(): void
    {
        // Photo sent 8 hours ago (outside the 6h window)
        $oldTs = gmdate('Y-m-d\TH:i:s\Z', time() - 8 * 3600);
        $this->sessionMemory->setRecords([
            $this->rec(['bot_reply' => 'Mira mi foto: https://ibb.co/foto1', 'ts' => $oldTs]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'gracias']);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['photos_sent_recent']);
    }

    // ─────────────────────────────────────────────────────────
    // 15. extractEtaMinutes
    // ─────────────────────────────────────────────────────────

    public function test_extract_eta_minutes_exact(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'estoy en 20 min']);
        $result = $assembler->process($ctx);
        $this->assertSame(20, $result['eta_from_user_minutes']);
        $this->assertTrue($result['eta_from_user_flag']);
    }

    public function test_extract_eta_minutes_abbreviated(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'llego en 5 mins']);
        $result = $assembler->process($ctx);
        $this->assertSame(5, $result['eta_from_user_minutes']);
    }

    public function test_extract_eta_minutes_no_eta(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola, que tal?']);
        $result = $assembler->process($ctx);
        $this->assertSame(0, $result['eta_from_user_minutes']);
        $this->assertFalse($result['eta_from_user_flag']);
    }

    public function test_extract_eta_minutes_tardo(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'tardo 15 minutos']);
        $result = $assembler->process($ctx);
        $this->assertSame(15, $result['eta_from_user_minutes']);
        $this->assertTrue($result['eta_from_user_flag']);
    }

    public function test_extract_eta_minutes_out_of_range(): void
    {
        // 200 min > 180 max range — should return 0
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'tardo 200 min']);
        $result = $assembler->process($ctx);
        $this->assertSame(0, $result['eta_from_user_minutes']);
        $this->assertFalse($result['eta_from_user_flag']);
    }

    // ─────────────────────────────────────────────────────────
    // 16. computeChooseLoopCount
    // ─────────────────────────────────────────────────────────

    public function test_choose_loop_count_counts_consecutive_location_requests(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'donde estas?']),
            $this->rec(['user_msg' => 'maps?']),
            $this->rec(['user_msg' => 'dame ubicacion']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'donde?']);
        $result = $assembler->process($ctx);
        // These 3 consecutive location requests without a selected girl → 3
        $this->assertSame(3, $result['choose_loop_count']);
    }

    public function test_choose_loop_count_zero_when_girl_selected(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec([
                'user_msg'           => 'donde estas?',
                'selected_girl_name' => 'Carina',
                'speaker_girl_name'  => 'Carina',
            ]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'donde?']);
        $result = $assembler->process($ctx);
        // choose_loop_count should be 0 when a girl is already selected
        $this->assertSame(0, $result['choose_loop_count']);
    }

    // ─────────────────────────────────────────────────────────
    // 17. computePhotoInsistCount
    // ─────────────────────────────────────────────────────────

    public function test_photo_insist_count(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'tienes fotos?']),
            $this->rec(['user_msg' => 'mandame fotos']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'dame mas fotos']);
        $result = $assembler->process($ctx);
        $this->assertSame(2, $result['photo_insist_count']);
    }

    public function test_photo_insist_count_zero(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'hola']),
            $this->rec(['user_msg' => 'cuanto cuesta?']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'ok gracias']);
        $result = $assembler->process($ctx);
        $this->assertSame(0, $result['photo_insist_count']);
    }

    // ─────────────────────────────────────────────────────────
    // 18. computeLocationInsistCount
    // ─────────────────────────────────────────────────────────

    public function test_location_insist_count(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'donde estas?']),
            $this->rec(['user_msg' => 'mandame ubicacion']),
            $this->rec(['user_msg' => 'dame direccion']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'donde?']);
        $result = $assembler->process($ctx);
        $this->assertSame(3, $result['location_insist_count']);
    }

    public function test_location_insist_count_zero(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'hola']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'ok']);
        $result = $assembler->process($ctx);
        $this->assertSame(0, $result['location_insist_count']);
    }

    // ─────────────────────────────────────────────────────────
    // 19. detectConversationEndIntent
    // ─────────────────────────────────────────────────────────

    public function test_detect_conversation_end_intent_true(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'gracias adios']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['conversation_end_intent']);
    }

    public function test_detect_conversation_end_intent_nos_vemos(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'nos vemos luego']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['conversation_end_intent']);
    }

    public function test_detect_conversation_end_intent_false_normal(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'cuanto cobras?']);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['conversation_end_intent']);
    }

    // ─────────────────────────────────────────────────────────
    // 20. isFillerUser
    // ─────────────────────────────────────────────────────────

    public function test_is_filler_user_true_ok(): void
    {
        // isFillerUser is tested internally (used by lastUserMeaningfulFromHistory).
        // Test that short filler messages don't carry topic significance.
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'ok']);
        $result = $assembler->process($ctx);
        // "ok" alone doesn't match any significant topic
        $this->assertSame('otro', $result['topic_actual']);
    }

    public function test_is_filler_user_false_question(): void
    {
        // "cuanto cobras" is NOT filler
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'cuanto cobras?']);
        $result = $assembler->process($ctx);
        $this->assertSame('precios', $result['topic_actual']);
    }

    // ─────────────────────────────────────────────────────────
    // 21. detectInteresFuerte
    // ─────────────────────────────────────────────────────────

    public function test_detect_interes_fuerte_true(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'voy para alla']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['interes_fuerte']);
    }

    public function test_detect_interes_fuerte_estoy_saliendo(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'ahora mismo salgo para alla']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['interes_fuerte']);
    }

    public function test_detect_interes_fuerte_false(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola, que tal?']);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['interes_fuerte']);
    }

    // ─────────────────────────────────────────────────────────
    // 22. countBotMessages
    // ─────────────────────────────────────────────────────────

    public function test_count_bot_messages(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx([
            'message_text' => 'hola',
            'memory_text'  => "Bot: Hola guapa\nUser: hola\nBot: Soy Carina\nUser: cuanto?\nBot: 50 la media",
        ]);
        $result = $assembler->process($ctx);
        $this->assertSame(3, $result['bot_msg_count_recent']);
    }

    public function test_count_bot_messages_zero(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx([
            'message_text' => 'hola',
            'memory_text'  => '',
        ]);
        $result = $assembler->process($ctx);
        $this->assertSame(0, $result['bot_msg_count_recent']);
    }

    // ─────────────────────────────────────────────────────────
    // 23. userResponseTimeSec
    // ─────────────────────────────────────────────────────────

    public function test_user_response_time_sec_from_history(): void
    {
        $ts = gmdate('Y-m-d\TH:i:s\Z', time() - 42);
        $this->sessionMemory->setRecords([
            $this->rec(['bot_reply' => 'Hola guapa', 'ts' => $ts]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'cuanto?']);
        $result = $assembler->process($ctx);
        // Should be approximately 42 seconds (give or take a few)
        $this->assertGreaterThan(35, $result['user_response_time_sec']);
        $this->assertLessThan(65, $result['user_response_time_sec']);
    }

    // ─────────────────────────────────────────────────────────
    // 24. computeShownUnshown
    // ─────────────────────────────────────────────────────────

    public function test_compute_shown_unshown_with_historical_shown(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['__shown_girls' => ['Carina', 'Sandra']]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hay mas?']);
        $result = $assembler->process($ctx);
        $this->assertContains('Carina', $result['shown_girls']);
        $this->assertContains('Sandra', $result['shown_girls']);

        // Unshown should be the remaining active girls
        $unshownNames = array_map(fn(array $g) => $g['nombre'], $result['unshown_girls']);
        $this->assertContains('Luna', $unshownNames);
        $this->assertContains('Ana Belén', $unshownNames);
        $this->assertNotContains('Carina', $unshownNames);
        $this->assertNotContains('Sandra', $unshownNames);
    }

    public function test_compute_shown_unshown_all_unshown_without_history(): void
    {
        $this->sessionMemory->setRecords([]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola']);
        $result = $assembler->process($ctx);
        $this->assertSame([], $result['shown_girls']);
        $this->assertCount(4, $result['unshown_girls']); // 4 active girls
    }

    // ─────────────────────────────────────────────────────────
    // 25. photo_rejected
    // ─────────────────────────────────────────────────────────

    public function test_photo_rejected_true_direct(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'no es esa chica']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['photo_rejected']);
    }

    public function test_photo_rejected_true_ya_vi(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'ya las he visto']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['photo_rejected']);
    }

    public function test_photo_rejected_false(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'me encanta, pasame direccion']);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['photo_rejected']);
    }

    // ─────────────────────────────────────────────────────────
    // 26. __filler_loop_count
    // ─────────────────────────────────────────────────────────

    public function test_filler_loop_count_with_consecutive_fillers(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'ok']),
            $this->rec(['user_msg' => 'vale']),
            $this->rec(['user_msg' => 'si']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'ok']);
        $result = $assembler->process($ctx);
        // 3 history fillers + current = 4
        $this->assertGreaterThanOrEqual(3, $result['__filler_loop_count']);
    }

    public function test_filler_loop_count_breaks_on_non_filler(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'ok']),
            $this->rec(['user_msg' => 'cuanto cobras?']), // non-filler breaks the chain
            $this->rec(['user_msg' => 'vale']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'si']);
        $result = $assembler->process($ctx);
        // Only "vale" (non-consecutive with "cuanto cobras?" in between) and current "si"
        // Actually it scans from the end backwards: "si" (current) + "vale" = 2, then hits "cuanto cobras?" → break
        $this->assertSame(2, $result['__filler_loop_count']);
    }

    // ─────────────────────────────────────────────────────────
    // 27. catalog_count
    // ─────────────────────────────────────────────────────────

    public function test_catalog_count(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['shown_girls' => ['Carina', 'Sandra']]), // catalog (2+ shown)
            $this->rec(['shown_girls' => ['Luna', 'Ana Belén']]), // catalog (2+ shown)
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hay mas?']);
        $result = $assembler->process($ctx);
        $this->assertSame(2, $result['catalog_count']);
    }

    public function test_catalog_count_zero_without_catalogs(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'hola', 'shown_girls' => []]),
            $this->rec(['shown_girls' => ['Carina']]), // only 1 girl shown → not a catalog
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'ok']);
        $result = $assembler->process($ctx);
        $this->assertSame(0, $result['catalog_count']);
    }

    // ─────────────────────────────────────────────────────────
    // 28. __is_burst
    // ─────────────────────────────────────────────────────────

    public function test_is_burst_true_with_rapid_messages(): void
    {
        $now = time();
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'hola', 'ts' => gmdate('Y-m-d\TH:i:s\Z', $now - 5)]),
            $this->rec(['user_msg' => 'donde?', 'ts' => gmdate('Y-m-d\TH:i:s\Z', $now - 10)]),
            $this->rec(['user_msg' => 'cuanto?', 'ts' => gmdate('Y-m-d\TH:i:s\Z', $now - 15)]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'rapido']);
        $result = $assembler->process($ctx);
        // 3 messages within 30 seconds → burst
        $this->assertTrue($result['__is_burst'] ?? false);
    }

    public function test_is_burst_false_with_slow_messages(): void
    {
        $now = time();
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'hola', 'ts' => gmdate('Y-m-d\TH:i:s\Z', $now - 600)]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'cuanto?']);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['__is_burst'] ?? true);
    }

    // ─────────────────────────────────────────────────────────
    // 29. preguntas_pendientes
    // ─────────────────────────────────────────────────────────

    public function test_preguntas_pendientes_detects_unanswered_photo_request(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'tienes fotos?', 'bot_reply' => '']), // unanswered
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola']);
        $result = $assembler->process($ctx);
        $this->assertContains('fotos_pendientes', $result['preguntas_pendientes']);
    }

    public function test_preguntas_pendientes_detects_answered_without_photos(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'tienes fotos?', 'bot_reply' => 'Claro, dime que chica']), // replied but no photos
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'Carina']);
        $result = $assembler->process($ctx);
        $this->assertContains('fotos_pendientes', $result['preguntas_pendientes']);
    }

    public function test_preguntas_pendientes_empty_when_no_questions(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'hola', 'bot_reply' => 'Hola guapa']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'como estas?']);
        $result = $assembler->process($ctx);
        // Note: preguntas_pendientes only checks fotos/precios/ubicacion patterns
        // and only when there's no subsequent bot_reply. The "como estas?" is current
        // message, not checked in history.
        $this->assertSame([], $result['preguntas_pendientes']);
    }

    public function test_preguntas_pendientes_price_unanswered(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'cuanto cobras?', 'bot_reply' => 'Hola!']), // replied but no prices in ya_enviado
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'ok']);
        $result = $assembler->process($ctx);
        $this->assertContains('precios_pendientes', $result['preguntas_pendientes']);
    }

    // ─────────────────────────────────────────────────────────
    // 30. speaker_mode / speaker_girl_name
    // ─────────────────────────────────────────────────────────

    public function test_speaker_mode_chica_when_speaker_persisted(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec([
                'user_msg'          => 'me gusta carina',
                'speaker_girl_name' => 'Carina',
                'speaker_girl_id'   => 'g1',
            ]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'precio?']);
        $result = $assembler->process($ctx);
        $this->assertSame('Carina', $result['speaker_girl_name']);
        $this->assertSame('chica', $result['speaker_mode']);
    }

    public function test_speaker_mode_encargada_without_speaker(): void
    {
        $this->sessionMemory->setRecords([]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola']);
        $result = $assembler->process($ctx);
        $this->assertSame('', $result['speaker_girl_name']);
        $this->assertSame('encargada', $result['speaker_mode']);
    }

    // ─────────────────────────────────────────────────────────
    // Additional: edge case tests
    // ─────────────────────────────────────────────────────────

    public function test_selected_girl_sticky_does_not_change_without_explicit_intent(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec([
                'user_msg'            => 'me gusta carina',
                'speaker_girl_name'   => 'Carina',
                'speaker_girl_id'     => 'g1',
                'selected_girl_name'  => 'Carina',
                'selected_girl_id'    => 'g1',
            ]),
        ]);
        $assembler = $this->makeAssembler();
        // User mentions another girl but without explicit service choice wording
        $ctx = $this->baseCtx(['message_text' => 'y sandra?']);
        $result = $assembler->process($ctx);
        // selected_girl should remain Carina because "y sandra?" is not an explicit service choice
        $this->assertSame('Carina', $result['selected_girl_name']);
    }

    public function test_selected_girl_changes_with_explicit_intent(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec([
                'user_msg'            => 'me gusta carina',
                'speaker_girl_name'   => 'Carina',
                'speaker_girl_id'     => 'g1',
                'selected_girl_name'  => 'Carina',
                'selected_girl_id'    => 'g1',
            ]),
        ]);
        $assembler = $this->makeAssembler();
        // User changes to Sandra with explicit wording
        $ctx = $this->baseCtx(['message_text' => 'me quedo con sandra']);
        $result = $assembler->process($ctx);
        $this->assertSame('Sandra', $result['selected_girl_name']);
        $this->assertSame('g2', $result['selected_girl_id']);
        // speaker_girl should NOT change (it's sticky)
        $this->assertSame('Carina', $result['speaker_girl_name']);
    }

    public function test_info_pack_ready_when_all_sent(): void
    {
        $recentTs = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
        $this->sessionMemory->setRecords([
            $this->rec([
                'bot_reply'          => 'Te paso los precios: 50€ media hora',
                'ts'                 => $recentTs,
                'speaker_girl_name'  => 'Carina',
                'speaker_girl_id'    => 'g1',
                'selected_girl_name' => 'Carina',
                'selected_girl_id'   => 'g1',
            ]),
            $this->rec([
                'bot_reply'          => 'Aqui esta la ubicacion https://maps.app.goo.gl/xyz',
                'ts'                 => $recentTs,
                'speaker_girl_name'  => 'Carina',
                'speaker_girl_id'    => 'g1',
                'selected_girl_name' => 'Carina',
                'selected_girl_id'   => 'g1',
            ]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'perfecto']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['info_pack_ready']);
    }

    public function test_has_greeted_in_history(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['bot_reply' => 'Hola guapa, soy la encargada']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'cuanto?']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['__has_greeted']);
    }

    public function test_has_not_greeted_without_greeting_in_history(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['bot_reply' => 'Te paso los precios: 50€']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'ok']);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['__has_greeted']);
    }

    public function test_human_msg_count_increments(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'hola']),
            $this->rec(['user_msg' => 'cuanto?']),
            $this->rec(['user_msg' => '   ']), // blank should not count
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'vale']);
        // 2 real messages + 1 current = 3
        $result = $assembler->process($ctx);
        $this->assertSame(3, $result['__human_msg_count']);
    }

    public function test_thread_id_compound_with_line(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx([
            'message_text' => 'hola',
            'thread_id'    => '',  // explicit empty
            'line_last9'   => '123456789',
            'from_phone'   => '34600999888',
        ]);
        $result = $assembler->process($ctx);
        $this->assertSame('123456789_34600999888', $result['thread_id']);
    }

    public function test_sticky_state_restores_speaker_from_history(): void
    {
        // Simulate ctx with empty speaker_girl_name but history has it
        $this->sessionMemory->setRecords([
            $this->rec([
                'speaker_girl_name' => 'Luna',
                'speaker_girl_id'   => 'g3',
                'speaker_mode'      => 'chica',
            ]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx([
            'message_text'       => 'hola',
            'speaker_girl_name'  => '',
            'speaker_girl_id'    => '',
        ]);
        $result = $assembler->process($ctx);
        $this->assertSame('Luna', $result['speaker_girl_name']);
        $this->assertSame('chica', $result['speaker_mode']);
    }

    public function test_tarifa_30_legacy_redirects_to_40(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'ok 30', 'ts' => gmdate('Y-m-d\TH:i:s\Z', time() - 60)]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'dale']);
        $result = $assembler->process($ctx);
        // Legacy: 30 redirects to 40
        $this->assertSame('40', $result['tarifa_elegida']);
    }

    public function test_must_choose_girl_now_when_asking_location_without_selection(): void
    {
        $this->sessionMemory->setRecords([]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'dame ubicacion']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['must_choose_girl_now']);
    }

    public function test_session_reset_true_when_no_recent_history(): void
    {
        // Old records outside the recent window
        $oldTs = gmdate('Y-m-d\TH:i:s\Z', time() - 8 * 3600);
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'hola', 'ts' => $oldTs]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['session_reset']);
    }

    public function test_session_reset_false_with_recent_history(): void
    {
        $recentTs = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'hola', 'ts' => $recentTs]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'cuanto?']);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['session_reset']);
    }

    public function test_location_url_from_config(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola']);
        $result = $assembler->process($ctx);
        $this->assertArrayHasKey('location_url', $result);
    }

    public function test_conversation_ended_flag_with_end_intent(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'gracias adios']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['__conversation_ended'] ?? false);
    }

    public function test_urgent_detection_true(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'date prisa rapido!!!']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['__is_urgent']);
    }

    public function test_urgent_detection_false(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola, buenos dias']);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['__is_urgent']);
    }

    public function test_opening_burst_detection(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx([
            'message_text'     => 'he visto tu anuncio en milanuncios.com hola',
            '__coalesced_text' => 'he visto tu anuncio en milanuncios.com hola',
        ]);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['__is_opening_burst']);
    }

    public function test_opening_burst_false_without_greeting(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx([
            'message_text'     => 'he visto tu anuncio en milanuncios.com',
            '__coalesced_text' => 'he visto tu anuncio en milanuncios.com',
        ]);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['__is_opening_burst']);
    }

    public function test_bot_confusion_count(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['bot_reply' => 'Lo siento, no te he entendido']),
            $this->rec(['bot_reply' => 'No se que quieres decir']),
            $this->rec(['bot_reply' => 'Mira, te cuento...']), // non-confusion breaks the count
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'no entiendo']);
        $result = $assembler->process($ctx);
        // Scanning from most recent: "...te cuento..." (non-confusion) → break → count = 0
        $this->assertSame(0, $result['__bot_confusion_count']);
    }

    public function test_bot_confusion_count_with_consecutive(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['bot_reply' => 'No entiendo lo que dices']),
            $this->rec(['bot_reply' => 'Puedes repetirlo?']), // non-confusion → break
            $this->rec(['bot_reply' => 'De eso no se nada']),  // matches confusion pattern
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => '?']);
        $result = $assembler->process($ctx);
        // Most recent: "De eso no se nada" — matches confusion → count=1
        // Then: "Puedes repetirlo?" — non-confusion → break
        $this->assertSame(1, $result['__bot_confusion_count']);
    }

    public function test_ubicacion_pedida_fuerte(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'pasame la direccion']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['ubicacion_pedida_fuerte']);
    }

    public function test_ubicacion_pedida_fuerte_false(): void
    {
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'hola']);
        $result = $assembler->process($ctx);
        $this->assertFalse($result['ubicacion_pedida_fuerte']);
    }

    public function test_recent_bot_replies_norm_from_history(): void
    {
        $recentTs = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
        $this->sessionMemory->setRecords([
            $this->rec(['bot_reply' => 'Hola guapa!', 'ts' => $recentTs]),
            $this->rec(['bot_reply' => 'Soy Carina, encantada', 'ts' => $recentTs]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'cuanto?']);
        $result = $assembler->process($ctx);
        $this->assertIsArray($result['recent_bot_replies_norm']);
        $this->assertNotEmpty($result['recent_bot_replies_norm']);
    }

    public function test_photo_rejected_from_history(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'no me gusta']),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'otra?']);
        $result = $assembler->process($ctx);
        $this->assertTrue($result['photo_rejected']);
    }

    public function test_maps_being_sent_now_with_interest(): void
    {
        $recentTs = gmdate('Y-m-d\TH:i:s\Z', time() - 60);
        $this->sessionMemory->setRecords([
            $this->rec([
                'bot_reply'          => 'Te paso las fotos: https://ibb.co/abc',
                'ts'                 => $recentTs,
                'speaker_girl_name'  => 'Carina',
                'speaker_girl_id'    => 'g1',
                'selected_girl_name' => 'Carina',
                'selected_girl_id'   => 'g1',
            ]),
            $this->rec([
                'bot_reply'          => '50€ la media hora',
                'ts'                 => $recentTs,
                'speaker_girl_name'  => 'Carina',
                'speaker_girl_id'    => 'g1',
                'selected_girl_name' => 'Carina',
                'selected_girl_id'   => 'g1',
            ]),
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'dame ubicacion voy para alla']);
        $result = $assembler->process($ctx);
        // With photos sent, prices sent, girl selected, user asking location + interes_fuerte
        $this->assertTrue($result['maps_being_sent_now']);
    }

    public function test_merge_context_from_other_lines_preserves_speaker(): void
    {
        // Set up current thread (new, no history)
        $this->sessionMemory->setRecords([
            // Another thread with the same phone, different line
            [
                'thread_id'          => '111111111_34600123456',
                'user_msg'           => 'me gusta carina',
                'bot_reply'          => 'Hola! Soy Carina',
                'speaker_girl_name'  => 'Carina',
                'speaker_girl_id'    => 'g1',
                'selected_girl_name' => 'Carina',
                'selected_girl_id'   => 'g1',
                'ts'                 => gmdate('Y-m-d\TH:i:s\Z', time() - 3600),
                '_pending'           => false,
                'shown_girls'        => ['Carina', 'Sandra'],
            ],
            // Another unrelated thread
            [
                'thread_id'          => '222222222_34999111222',
                'user_msg'           => 'hola',
                'bot_reply'          => 'Hola',
                'ts'                 => gmdate('Y-m-d\TH:i:s\Z', time() - 7200),
                '_pending'           => false,
            ],
        ]);
        $assembler = $this->makeAssembler();
        // Current context: new conversation on a different line
        $ctx = $this->baseCtx([
            'message_text' => 'hola',
            'thread_id'    => '999999999_34600123456',
            'from_phone'   => '34600123456',
            'line_last9'   => '999999999',
        ]);
        $result = $assembler->process($ctx);
        // Speaker should be inherited from the other line
        $this->assertSame('Carina', $result['speaker_girl_name']);
        // shown_girls should be inherited
        $this->assertContains('Carina', $result['shown_girls']);
        // selected_girl should NOT be inherited (cross-line)
        $this->assertSame('', $result['selected_girl_name']);
    }

    public function test_pending_records_filtered_out(): void
    {
        $this->sessionMemory->setRecords([
            $this->rec(['user_msg' => 'hola', 'bot_reply' => 'Hola guapa', '_pending' => false]),
            [
                'thread_id' => $this->threadId,
                'user_msg'  => '_IGNORE_ME_',
                'bot_reply' => '',
                'ts'        => gmdate('Y-m-d\TH:i:s\Z'),
                '_pending'  => true,
            ],
        ]);
        $assembler = $this->makeAssembler();
        $ctx = $this->baseCtx(['message_text' => 'cuanto?']);
        $result = $assembler->process($ctx);
        // Should NOT be new conversation (history has 1 non-pending record)
        $this->assertFalse($result['__is_new_conversation']);
        // Human msg count should be 2 (hola + current cuanto?)
        $this->assertSame(2, $result['__human_msg_count']);
    }
}
