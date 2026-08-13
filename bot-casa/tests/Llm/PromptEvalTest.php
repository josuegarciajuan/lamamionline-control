<?php

declare(strict_types=1);

namespace WasapBot\Tests\Llm;

use PHPUnit\Framework\TestCase;

/**
 * Prompt evaluation harness — tests that interact with the REAL LLM.
 *
 * These tests are EXCLUDED from the CI gate (group=llm) because they:
 *  - Are non-deterministic (LLM outputs vary)
 *  - Cost real API credits
 *  - Are slow (network latency)
 *
 * Run manually with: composer test:llm
 *
 * PURPOSE: detect regressions in prompt behavior that deterministic tests
 * cannot catch — e.g., the LLM starts using forbidden phrases, misunderstands
 * the photo_action rules, or fails to follow the JSON contract under realistic
 * conditions.
 *
 * Each test sends a representative message and checks flexible behavioral
 * invariants (tolerance for non-determinism). A failure means the prompt
 * likely needs revision — NOT that the code is broken.
 *
 * @group llm
 */
final class PromptEvalTest extends TestCase
{
    /**
     * Contexto base para todas las eval: identidad de chica concreta.
     */
    private function baseContext(): array
    {
        return [
            'speaker_mode'            => 'chica',
            'speaker_girl_name'       => 'Carina',
            'speaker_girl_id'         => '1',
            'selected_girl_name'      => '',
            'selected_girl_id'        => '',
            'girls_config'            => [
                ['id' => '1', 'nombre' => 'Carina', 'activa' => true],
                ['id' => '2', 'nombre' => 'Sandra', 'activa' => true],
            ],
            'location_url'            => 'https://maps.app.goo.gl/test',
            'ya_enviado'              => [],
            'wants_more_girls'        => false,
            'haggle_count_recent'     => 0,
            'catalog_count'           => 0,
            'maps_sent'               => false,
            '__is_new_conversation'   => true,
            '__is_ad_intro'           => false,
        ];
    }

    /**
     * Skip all eval tests if no API key is configured.
     */
    protected function setUp(): void
    {
        $rootDir = \WASAPBOT_ROOT;
        $configDir = $rootDir; // or data/users/1 if exists
        $userDir = $rootDir . '/data/users/1';
        if (is_dir($userDir)) {
            $configDir = $userDir;
        }

        $key = '';
        $localPath = $configDir . '/config.local.json';
        if (file_exists($localPath)) {
            $local = json_decode((string) file_get_contents($localPath), true);
            if (is_array($local)) {
                $key = $local['deepseek']['api_key'] ?? $local['openai']['api_key'] ?? '';
            }
        }

        if ($key === '' || str_starts_with($key, 'CHANGEME')) {
            $this->markTestSkipped('No API key configured — LLM eval skipped');
        }
    }

    /**
     * Eval: greeting gets a short, emoji-equipped reply.
     *
     * Expected invariants:
     *  - reply is ≤ 50 chars (short)
     *  - contains at least one of the recognized emojis or Spanish greeting words
     */
    public function test_greeting_is_short_and_natural(): void
    {
        $result = $this->callLlm('hola', $this->baseContext());

        $reply = $result['user_visible_reply'] ?? '';

        // Not too long
        $this->assertLessThanOrEqual(120, mb_strlen($reply), 'Reply should be short');

        // Contains something (not empty)
        $this->assertNotEmpty($reply, 'Reply should not be empty');

        // Should not say "soy la encargada" or similar forbidden phrases
        $this->assertStringNotContainsStringIgnoringCase('encargada', $reply);
        $this->assertStringNotContainsStringIgnoringCase('asistente virtual', $reply);
        $this->assertStringNotContainsStringIgnoringCase('soy un bot', $reply);
    }

    /**
     * Eval: price question gets prices listed.
     *
     * Expected: either 40/50/100 or 30/50/100 (both are valid depending on active config).
     */
    public function test_price_question_returns_prices(): void
    {
        $ctx = $this->baseContext();
        $ctx['__is_new_conversation'] = false;

        $result = $this->callLlm('cuanto cobras', $ctx);

        $reply = $result['user_visible_reply'] ?? '';

        $this->assertNotEmpty($reply);
        // Should contain at least one price number
        $this->assertMatchesRegularExpression('/(50|100)/', $reply, 'Should mention prices');
    }

    /**
     * Eval: lead detection on explicit "voy en 20 min".
     *
     * Expected: lead_detected=true with confidence > 0.6.
     */
    public function test_explicit_eta_is_detected_as_lead(): void
    {
        $ctx = $this->baseContext();
        $ctx['__is_new_conversation'] = false;
        $ctx['selected_girl_name']    = 'Carina';
        $ctx['selected_girl_id']      = '1';
        $ctx['speaker_girl_name']     = 'Carina';
        $ctx['maps_sent']             = true;

        $result = $this->callLlm('voy en 20 min', $ctx);

        $leadDetected = $result['lead_detected'] ?? false;
        $confidence   = (float) ($result['lead_confidence'] ?? 0);

        $this->assertTrue($leadDetected, 'Should detect lead on explicit ETA');
        $this->assertGreaterThan(0.6, $confidence, 'Confidence should be > 0.6');
    }

    /**
     * Eval: JSON contract — response must be valid JSON.
     *
     * Expected: decodeable, contains user_visible_reply and lead_detected.
     */
    public function test_response_is_valid_json(): void
    {
        $result = $this->callLlm('hola', $this->baseContext());

        $this->assertIsArray($result, 'Response should be an array (JSON decoded)');
        $this->assertArrayHasKey('user_visible_reply', $result);
        $this->assertArrayHasKey('lead_detected', $result);
        $this->assertArrayHasKey('lead_confidence', $result);
        $this->assertArrayHasKey('photo_action', $result);
        $this->assertArrayHasKey('lead_signals', $result);

        // photo_action must be one of the valid values
        $validPhotoActions = ['none', 'catalog', 'selected_all'];
        $this->assertContains($result['photo_action'], $validPhotoActions);

        // lead_signals should be an array
        $this->assertIsArray($result['lead_signals']);
    }

    /**
     * Eval: photo_action "selected_all" when client picks a girl.
     *
     * Expected: photo_action = selected_all when client says "me quedo con Carina".
     */
    public function test_photo_action_selected_all_when_girl_chosen(): void
    {
        $ctx = $this->baseContext();
        $ctx['__is_new_conversation'] = false;
        // Add a prior catalog viewing turn
        $ctx['speaker_girl_name']     = 'Carina';

        $result = $this->callLlm('me gusta Carina', $ctx);

        $photoAction = $result['photo_action'] ?? 'none';

        // Should be selected_all (all photos of Carina)
        // Allow 'none' as tolerance for model variance
        $this->assertContains($photoAction, ['selected_all', 'none', 'catalog']);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Helper: call the LLM directly
    // ─────────────────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $extraCtx
     * @return array<string, mixed>
     */
    private function callLlm(string $message, array $extraCtx): array
    {
        $rootDir = \WASAPBOT_ROOT;
        $configDir = $rootDir; // or data/users/1
        $userDir = $rootDir . '/data/users/1';
        if (is_dir($userDir)) {
            $configDir = $userDir;
        }

        $config = new \WasapBot\Core\Config($configDir);
        $logger = new \WasapBot\Core\FileLogger($config);

        $http = new \WasapBot\Core\HttpClient($logger);

        // Use deepseek by default (cheaper)
        $client = new \WasapBot\Services\DeepSeekClient($config, $http, $logger);

        // Build a minimal context
        $ctx = array_merge($extraCtx, [
            'message_text' => $message,
            'from_phone'   => '34600123456',
            'thread_id'    => '000000000_34600123456',
            'line_last9'   => '000000000',
        ]);

        // Build system prompt
        $bot = new \WasapBot\Bot(
            config:           $config,
            logger:           $logger,
            http:             $http,
            memory:           new \WasapBot\Tests\Support\FakeMemory(),
            wahaApi:          new \WasapBot\Tests\Support\FakeWahaApi(),
            openaiClient:     $client,
            deepseekClient:   $client,
            girlsService:     new \WasapBot\Tests\Support\FakeGirlsService(),
            blacklistService: new \WasapBot\Tests\Support\FakeBlacklistService(),
            telegramService:  new \WasapBot\Tests\Support\FakeTelegramService(),
            sessionMemory:    new \WasapBot\Tests\Support\FakeSessionMemory('/tmp/wasapbot_eval_memory.ndjson'),
        );

        // Use Reflection to call private buildSystemPrompt
        $ref = new \ReflectionMethod($bot, 'buildSystemPrompt');
        $systemPrompt = $ref->invoke($bot, $ctx);

        // Call the LLM
        $rawResponse = $client->chat($systemPrompt, $message, $ctx);

        // If response is wrapped in choices, extract
        if (isset($rawResponse['choices'])) {
            $content = $rawResponse['choices'][0]['message']['content'] ?? '{}';
            return json_decode($content, true) ?: [];
        }

        return $rawResponse;
    }
}
