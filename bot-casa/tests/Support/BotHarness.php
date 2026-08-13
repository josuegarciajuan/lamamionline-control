<?php

declare(strict_types=1);

namespace WasapBot\Tests\Support;

use WasapBot\Bot;
use WasapBot\Core\ConfigInterface;
use WasapBot\Core\LoggerInterface;
use WasapBot\Core\HttpClient;

/**
 * BotHarness — builds a fully-wired Bot instance for integration tests.
 *
 * All network-bound services are replaced with Fake* implementations so the
 * integration tests can exercise the complete pipeline without touching the
 * network or production data.
 */
final class BotHarness
{
    public readonly FakeOpenAiClient $openaiClient;
    public readonly FakeOpenAiClient $deepseekClient;
    public readonly FakeWahaApi $wahaApi;
    public readonly FakeGirlsService $girlsService;
    public readonly FakeTelegramService $telegramService;
    public readonly FakeBlacklistService $blacklistService;
    public readonly FakeSessionMemory $sessionMemory;
    public readonly Bot $bot;

    public function __construct(ConfigInterface $config, LoggerInterface $logger)
    {
        $http = new HttpClient($logger);

        $this->openaiClient   = new FakeOpenAiClient();
        $this->deepseekClient = new FakeOpenAiClient(); // FakeOpenAiClient also works as DeepSeek (same interface)
        $this->wahaApi        = new FakeWahaApi();
        $this->girlsService   = new FakeGirlsService();
        $this->telegramService = new FakeTelegramService();
        $this->blacklistService = new FakeBlacklistService();

        $memory = new FakeMemory();

        $memPath = $config->get('files.session_memory', '');
        $this->sessionMemory = new FakeSessionMemory($memPath !== '' ? $memPath : '/tmp/wasapbot_test_session_memory.ndjson');

        $clientProfileSvc = new \WasapBot\Services\ClientProfileService($config, $logger);
        $stateMachine     = new \WasapBot\Pipeline\ConversationStateMachine($config, $logger);
        $responseScorer   = new \WasapBot\SideEffects\ResponseScorer($config, $logger, $this->sessionMemory);

        $pauseGate = new \WasapBot\Pipeline\PauseGate($config, $logger);
        $inputGates = [
            new \WasapBot\Pipeline\BotModeGate($config, $logger),
            new \WasapBot\Pipeline\RoutingGate($config),
            new \WasapBot\Pipeline\DedupGate($config),
            new \WasapBot\Pipeline\Coalescer($config),
            new \WasapBot\Pipeline\MessageExtractor($config),
            $pauseGate,
            new \WasapBot\Pipeline\InflightGate($config),
        ];

        $processors = [
            new \WasapBot\Pipeline\ContextAssembler($config, $logger, $memory, $this->sessionMemory),
            new \WasapBot\Pipeline\IntentRouter($config, $logger),
            new \WasapBot\Pipeline\ToneBuilder($config, $logger),
            new \WasapBot\Pipeline\ResponseNormalizer($config),
            new \WasapBot\Pipeline\CatalogFormatter($config, $logger),
            new \WasapBot\Pipeline\DedupeReply($config, $logger),
            new \WasapBot\Pipeline\ImageSplitter($config),
        ];

        $sideEffects = [
            'leadDetector'   => new \WasapBot\SideEffects\LeadDetector($config, $logger),
            'leadLogger'     => new \WasapBot\SideEffects\LeadLogger($config, $logger),
            'autoOff'        => new \WasapBot\SideEffects\AutoOff($config, $logger),
            'reminderWriter' => new \WasapBot\SideEffects\ReminderWriter($config, $logger),
        ];

        $this->bot = new Bot(
            config:                $config,
            logger:                $logger,
            http:                  $http,
            memory:                $memory,
            wahaApi:               $this->wahaApi,
            openaiClient:          $this->openaiClient,
            deepseekClient:        $this->deepseekClient,
            girlsService:          $this->girlsService,
            blacklistService:      $this->blacklistService,
            telegramService:       $this->telegramService,
            sessionMemory:         $this->sessionMemory,
            clientProfileService:  $clientProfileSvc,
            stateMachine:          $stateMachine,
            responseScorer:        $responseScorer,
            inputGates:            $inputGates,
            processors:            $processors,
            sideEffects:           $sideEffects,
        );
    }
}
