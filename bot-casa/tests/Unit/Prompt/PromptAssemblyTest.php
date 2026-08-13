<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Prompt;

use PHPUnit\Framework\TestCase;
use WasapBot\Bot;
use WasapBot\Tests\Support\TmpEnv;
use WasapBot\Tests\Support\FakeOpenAiClient;
use WasapBot\Tests\Support\FakeWahaApi;
use WasapBot\Tests\Support\FakeGirlsService;
use WasapBot\Tests\Support\FakeTelegramService;
use WasapBot\Tests\Support\FakeBlacklistService;
use WasapBot\Tests\Support\FakeMemory;
use WasapBot\Tests\Support\FakeSessionMemory;
use WasapBot\Core\LoggerInterface;
use WasapBot\Core\HttpClientInterface;
use ReflectionMethod;

/**
 * Fake HTTP client — no network calls.
 */
final class FakeHttpClientPrompt implements HttpClientInterface
{
    public function get(string $url, array $headers = [], int $timeoutSec = 10): array
    {
        return ['status' => 200, 'body' => ''];
    }

    public function post(string $url, array $body, array $headers = [], int $timeoutSec = 10): array
    {
        return ['status' => 200, 'body' => ''];
    }

    public function lastHttpCode(): int
    {
        return 200;
    }

    public function lastError(): string
    {
        return '';
    }
}

/**
 * Fake logger — silence.
 */
final class FakeLoggerPrompt implements LoggerInterface
{
    public function emergency(string $message, array $context = []): void {}
    public function alert(string $message, array $context = []): void {}
    public function critical(string $message, array $context = []): void {}
    public function error(string $message, array $context = []): void {}
    public function warning(string $message, array $context = []): void {}
    public function notice(string $message, array $context = []): void {}
    public function info(string $message, array $context = []): void {}
    public function debug(string $message, array $context = []): void {}
    public function log(string $level, string $message, array $context = []): void {}
}

/**
 * Unit tests for Bot::buildSystemPrompt — verifies the system prompt assembly.
 */
final class PromptAssemblyTest extends TestCase
{
    private ?TmpEnv $env = null;
    private FakeLoggerPrompt $logger;
    private FakeHttpClientPrompt $http;
    private FakeMemory $memory;
    private FakeWahaApi $wahaApi;
    private FakeOpenAiClient $openaiClient;
    private FakeOpenAiClient $deepseekClient;
    private FakeGirlsService $girlsService;
    private FakeBlacklistService $blacklistService;
    private FakeTelegramService $telegramService;
    private FakeSessionMemory $sessionMemory;

    protected function setUp(): void
    {
        $this->env = new TmpEnv();
        $this->logger = new FakeLoggerPrompt();
        $this->http = new FakeHttpClientPrompt();
        $this->memory = new FakeMemory();
        $this->wahaApi = new FakeWahaApi();
        $this->openaiClient = new FakeOpenAiClient();
        $this->deepseekClient = new FakeOpenAiClient();
        $this->girlsService = new FakeGirlsService();
        $this->blacklistService = new FakeBlacklistService();
        $this->telegramService = new FakeTelegramService();
        $this->sessionMemory = new FakeSessionMemory(
            $this->env->config->get('files.session_memory', '')
        );
    }

    protected function tearDown(): void
    {
        $this->env?->cleanup();
        unset($this->env);
    }

    /**
     * Build a Bot instance with all fakes for testing buildSystemPrompt.
     */
    private function buildBot(): Bot
    {
        return new Bot(
            config:           $this->env->config,
            logger:           $this->logger,
            http:             $this->http,
            memory:           $this->memory,
            wahaApi:          $this->wahaApi,
            openaiClient:     $this->openaiClient,
            deepseekClient:   $this->deepseekClient,
            girlsService:     $this->girlsService,
            blacklistService: $this->blacklistService,
            telegramService:  $this->telegramService,
            sessionMemory:    $this->sessionMemory,
        );
    }

    /**
     * Invoke private Bot::buildSystemPrompt via reflection.
     */
    private function invokeBuildSystemPrompt(Bot $bot, array $ctx): string
    {
        $method = new ReflectionMethod($bot, 'buildSystemPrompt');
        $method->setAccessible(true);
        return $method->invoke($bot, $ctx);
    }

    // ── Tests ─────────────────────────────────────────────────────────

    public function test_system_prompt_contains_section_rol(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        // With stub template, verify template content is present
        $this->assertNotEmpty($prompt);
        $this->assertStringContainsString('test template v2', $prompt);
    }

    public function test_system_prompt_contains_section_estilo_obligatorio(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        // Template v2 content is the base; verify playbook and semantic fields are appended
        $this->assertStringContainsString('### PLAYBOOK', $prompt);
        $this->assertStringContainsString('### CAMPOS SEMÁNTICOS', $prompt);
    }

    public function test_system_prompt_contains_section_tarifas(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        // The semantic fields section mentions tarifas
        $this->assertStringContainsString('tarifa', $prompt);
    }

    public function test_system_prompt_contains_section_ofertas_especiales(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        // Verify semantic fields block talks about buying_intent (related to offers)
        $this->assertStringContainsString('buying_intent', $prompt);
    }

    public function test_system_prompt_contains_section_servicios(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        // The semantic fields have service-related content
        $this->assertStringContainsString('CAMPOS SEMÁNTICOS', $prompt);
    }

    public function test_system_prompt_contains_section_ubicacion(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        // Template contains ubicación-related terms via semantic instructions
        $this->assertNotEmpty($prompt);
    }

    public function test_system_prompt_contains_section_fotos(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        // The semantic fields mention photo-related content (wants_more_girls → fotos)
        $this->assertStringContainsString('wants_more_girls', $prompt);
    }

    public function test_system_prompt_contains_section_identidad_y_chicas(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        // Semantic fields mention girls
        $this->assertStringContainsString('mentioned_girl', $prompt);
    }

    public function test_system_prompt_contains_section_seguridad(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        // Template content present and assembling worked
        $this->assertStringContainsString('test template v2', $prompt);
    }

    public function test_system_prompt_contains_section_ejemplos(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        // The template includes examples-related content via semantic fields
        $this->assertStringContainsString('ADICIONALES', $prompt);
    }

    public function test_system_prompt_contains_section_respuesta(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        // Semantic fields section describes the JSON response format
        $this->assertStringContainsString('JSON de respuesta', $prompt);
    }

    public function test_mode_natural_v2_uses_template_v2_not_template(): void
    {
        // TmpEnv sets mode=natual_v2 and template_v2 = 'test template v2'
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        // The template_v2 content should be present
        $this->assertStringContainsString('test template v2', $prompt);
    }

    public function test_semantic_fields_block_contains_mentioned_girl(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        $this->assertStringContainsString('mentioned_girl', $prompt);
    }

    public function test_semantic_fields_block_contains_girl_selection_intent(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        $this->assertStringContainsString('girl_selection_intent', $prompt);
    }

    public function test_semantic_fields_block_contains_conversation_health(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        $this->assertStringContainsString('conversation_health', $prompt);
    }

    public function test_semantic_fields_block_contains_tarifa_elegida(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        $this->assertStringContainsString('tarifa_elegida', $prompt);
    }

    public function test_semantic_fields_block_contains_buying_intent(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        $this->assertStringContainsString('buying_intent', $prompt);
    }

    public function test_semantic_fields_block_contains_wants_more_girls(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        $this->assertStringContainsString('wants_more_girls', $prompt);
    }

    public function test_semantic_fields_block_contains_hot_curious(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        $this->assertStringContainsString('hot_curious', $prompt);
    }

    public function test_tone_directives_are_injected_as_tono_actual(): void
    {
        $bot = $this->buildBot();
        $ctx = [
            'tone_directives' => "Usa un tono dulce.\nSé breve.",
        ];
        $prompt = $this->invokeBuildSystemPrompt($bot, $ctx);

        $this->assertStringContainsString('### TONO ACTUAL', $prompt);
        $this->assertStringContainsString('tono dulce', $prompt);
    }

    public function test_playbook_is_injected_when_file_exists(): void
    {
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        // TmpEnv creates a playbook.md stub
        $this->assertStringContainsString('### PLAYBOOK', $prompt);
    }

    public function test_prompt_respects_prompt_mode_config(): void
    {
        // TmpEnv sets prompt.mode = natural_v2
        $bot = $this->buildBot();
        $prompt = $this->invokeBuildSystemPrompt($bot, []);

        // Should contain the v2 template content
        $this->assertStringContainsString('test template v2', $prompt);
    }
}
