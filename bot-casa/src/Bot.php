<?php

declare(strict_types=1);

namespace WasapBot;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\LoggerInterface;
use WasapBot\Core\HttpClientInterface;
use WasapBot\Core\MemoryInterface;
use WasapBot\Services\WahaApiInterface;
use WasapBot\Services\OpenAiClientInterface;
use WasapBot\Services\GirlsServiceInterface;
use WasapBot\Services\BlacklistServiceInterface;
use WasapBot\Services\TelegramServiceInterface;
use WasapBot\Memory\SessionMemoryInterface;
use WasapBot\Pipeline\PipelineStageInterface;

/**
 * WasapBot — main orchestrator of the WhatsApp message processing pipeline.
 *
 * Coordinates input gates, processors, side effects, and external services
 * following the 18-step pipeline defined in the spec.
 */
final class Bot implements BotInterface
{
    /** @var array<int, PipelineStageInterface> */
    private readonly array $inputGates;

    /** @var array<int, PipelineStageInterface> */
    private readonly array $processors;

    /** @var array<string, object> */
    private readonly array $sideEffects;

    /**
     * @param ConfigInterface              $config
     * @param LoggerInterface              $logger
     * @param HttpClientInterface          $http
     * @param MemoryInterface              $memory
     * @param WahaApiInterface             $wahaApi
     * @param OpenAiClientInterface        $openaiClient
     * @param OpenAiClientInterface        $deepseekClient
     * @param GirlsServiceInterface        $girlsService
     * @param BlacklistServiceInterface    $blacklistService
     * @param TelegramServiceInterface     $telegramService
     * @param SessionMemoryInterface       $sessionMemory
     * @param PipelineStageInterface[]     $inputGates   Input gate stages executed in order.
     * @param PipelineStageInterface[]     $processors   Processing stages executed in order.
     * @param array<string, object>        $sideEffects  Named side-effect handlers.
     */
    public function __construct(
        private readonly ConfigInterface           $config,
        private readonly LoggerInterface           $logger,
        private readonly HttpClientInterface       $http,
        private readonly MemoryInterface           $memory,
        private readonly WahaApiInterface          $wahaApi,
        private readonly OpenAiClientInterface     $openaiClient,
        private readonly OpenAiClientInterface     $deepseekClient,
        private readonly GirlsServiceInterface     $girlsService,
        private readonly BlacklistServiceInterface $blacklistService,
        private readonly TelegramServiceInterface  $telegramService,
        private readonly SessionMemoryInterface    $sessionMemory,
        array                                      $inputGates = [],
        array                                      $processors = [],
        array                                      $sideEffects = [],
    ) {
        $this->inputGates  = $inputGates;
        $this->processors  = $processors;
        $this->sideEffects = $sideEffects;
    }

    // ─────────────────────────────────────────────────────────────────
    //  BotInterface
    // ─────────────────────────────────────────────────────────────────

    /**
     * Process an incoming WAHA webhook payload through the full pipeline.
     *
     * @param array<string, mixed> $webhookPayload  Raw WAHA webhook body.
     * @return array<string, mixed>|null  Response data or null if message was rejected/ignored.
     */
    public function handleWebhook(array $webhookPayload): ?array
    {
        $this->logger->info('Bot::handleWebhook — received payload', [
            'event' => $webhookPayload['event'] ?? 'unknown',
        ]);

        try {
            // ── 1. Initialize context ─────────────────────────────────
            $ctx = [
                'raw_payload' => $webhookPayload,
                'body'        => $webhookPayload,
            ];

            // ── 2. Execute input gates in order ──────────────────────
            foreach ($this->inputGates as $gate) {
                $ctx = $gate->process($ctx);

                if ($ctx === null) {
                    $this->logger->debug('Bot::handleWebhook — pipeline halted by gate', [
                        'gate' => $gate->name(),
                    ]);
                    return null;
                }
            }

            // ── 3. Verify blacklist ──────────────────────────────────
            $fromPhone = (string) ($ctx['from_phone'] ?? '');
            if ($fromPhone !== '' && $this->blacklistService->isBlacklisted($fromPhone)) {
                $this->logger->info('Bot::handleWebhook — sender blacklisted', [
                    'phone' => $fromPhone,
                ]);
                return null;
            }

            // ── 4. Fetch girls catalog ───────────────────────────────
            $girls = $this->girlsService->fetchActive();
            $ctx['girls_config'] = $girls;

            // ── 5. Read session memory ───────────────────────────────
            $ctx['memory_text'] = (string) $this->memory->getLastBotReply();
            $ctx['recent_bot_replies_norm'] = $this->memory->getRecentBotRepliesNorm(5);

            // ── 6. Format memory + context (ContextAssembler) ────────
            if (isset($this->processors[0]) && $this->processors[0]->name() === 'ContextAssembler') {
                $ctx = $this->processors[0]->process($ctx);
                if ($ctx === null) {
                    return null; // conversation_dead — stop, no OpenAI call
                }
            }

            // ── 7. Audio auto-reply shortcut ─────────────────────────
            $isAudio = (int) ($ctx['is_audio_i'] ?? 0);
            if ($isAudio === 1) {
                $variants = (array) $this->config->get('message_variants.audio_auto_reply', []);
                $audioReply = $variants !== [] ? $variants[array_rand($variants)] : 'no puedo escuchar audios amor, me lo escribes mejor?';

                $ctx['output_text']   = $audioReply;
                $ctx['lead_detected'] = false;

                $this->logger->info('Bot::handleWebhook — audio auto-reply sent', [
                    'phone' => $fromPhone,
                ]);

                // Send the audio reply
                $this->sendMessages($ctx, [$audioReply]);

                // Append memory
                $this->sessionMemory->appendMessage(
                    (string) ($ctx['thread_id'] ?? ''),
                    $fromPhone,
                    (string) ($ctx['message_text'] ?? ''),
                    $audioReply,
                    $ctx,
                );

                return $ctx;
            }

            // ── 8. Classify tone via OpenAI ──────────────────────────
            $messageText = (string) ($ctx['message_text'] ?? '');
            $tone = $this->openaiClient->classifyTone($messageText);

            $ctx['sentiment'] = $tone['sentiment'] ?? 'neutral';
            $ctx['register']  = $tone['register']  ?? 'normal';
            $ctx['urgency']   = $tone['urgency']   ?? 'baja';

            // ── 9. Build tone directives (ToneBuilder) ───────────────
            if (isset($this->processors[1]) && $this->processors[1]->name() === 'ToneBuilder') {
                $ctx = $this->processors[1]->process($ctx);
            }

            // ── 10. Call AI chat completion (OpenAI or DeepSeek) ──────
            $systemPrompt = $this->buildSystemPrompt($ctx);
            $userMessage  = $ctx['user_message'] ?? $messageText;

            // Select AI client based on routing line config
            $aiProvider = (string) ($ctx['ai_provider'] ?? 'openai');
            $aiModel    = !empty($ctx['ai_model']) ? (string) $ctx['ai_model'] : null;

            if ($aiProvider === 'deepseek') {
                $aiClient = $this->deepseekClient;
            } else {
                $aiClient = $this->openaiClient;
            }

            $openaiResponse = $aiClient->chat(
                $systemPrompt,
                (string) $userMessage,
                $ctx,
                $aiModel,
            );

            $ctx['openai_raw_response'] = $openaiResponse;
            $ctx['openai_choices']      = $openaiResponse['choices'] ?? [];

            // ── 11. Normalize response (ResponseNormalizer) ──────────
            if (isset($this->processors[2]) && $this->processors[2]->name() === 'ResponseNormalizer') {
                $ctx = $this->processors[2]->process($ctx);
            } elseif (!empty($openaiResponse['choices'])) {
                $ctx['output_text'] = $openaiResponse['choices'][0]['message']['content'] ?? '';
            }

            // ── 12. Catalog formatter (CatalogFormatter) ─────────────
            if (isset($this->processors[3]) && $this->processors[3]->name() === 'CatalogFormatter') {
                $ctx = $this->processors[3]->process($ctx);
            }

            // ── 13. Dedupe reply (DedupeReply) ───────────────────────
            if (isset($this->processors[4]) && $this->processors[4]->name() === 'DedupeReply') {
                $ctx = $this->processors[4]->process($ctx);
            }

            // ── 14. Image splitter (ImageSplitter) ───────────────────
            if (isset($this->processors[5]) && $this->processors[5]->name() === 'ImageSplitter') {
                $ctx = $this->processors[5]->process($ctx);
            }

            $messages = $ctx['splitted_messages'] ?? [$ctx['output_text'] ?? ''];

            // ── 15. Send via WAHA with humanization ──────────────────
            $this->sendMessages($ctx, (array) $messages);

            // ── 16. Append to session memory ─────────────────────────
            $this->sessionMemory->appendMessage(
                (string) ($ctx['thread_id'] ?? ''),
                $fromPhone,
                $messageText,
                (string) ($ctx['output_text'] ?? ''),
                $ctx,
            );

            // ── 17. Side effects ─────────────────────────────────────
            $this->runSideEffects($ctx);

            // ── 18. Return full context ──────────────────────────────
            $this->logger->info('Bot::handleWebhook — pipeline completed', [
                'phone'     => $fromPhone,
                'thread_id' => $ctx['thread_id'] ?? '?',
            ]);

            return $ctx;
        } catch (\Throwable $e) {
            $this->logger->error('Bot::handleWebhook — exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    public function isRunning(): bool
    {
        $modeFile = (string) $this->config->get('bot.mode_file', '.bot_mode');

        // Resolve relative paths safely against the data directory
        $rootDir = defined('WASAPBOT_ROOT') ? WASAPBOT_ROOT : dirname(__DIR__);
        if (!str_starts_with($modeFile, '/')) {
            $modeFile = $rootDir . '/' . ltrim($modeFile, '/');
        }

        // Path traversal protection: resolve and verify within project root
        $resolved = realpath($modeFile);
        if ($resolved === false) {
            // File doesn't exist yet — at least ensure parent dir is within project
            $parent = realpath(dirname($modeFile));
            if ($parent === false || !str_starts_with($parent, (string) realpath($rootDir))) {
                $this->logger->warning('Bot::isRunning — path traversal blocked', ['path' => $modeFile]);
                return true;
            }
        } elseif (!str_starts_with($resolved, (string) realpath($rootDir))) {
            $this->logger->warning('Bot::isRunning — path traversal blocked', ['path' => $modeFile]);
            return true;
        }

        if (!file_exists($modeFile) || !is_readable($modeFile)) {
            $this->logger->warning('Bot::isRunning — mode file missing or unreadable', [
                'path' => $modeFile,
            ]);
            return true; // Default to running if file missing
        }

        $content = trim((string) @file_get_contents($modeFile));
        return $content !== 'stop';
    }

    public function getConfig(): ConfigInterface
    {
        return $this->config;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Bootstrap factory
    // ─────────────────────────────────────────────────────────────────

    /**
     * Bootstrap all services, pipeline stages, and the Bot instance.
     *
     * Call once from public/webhook.php or public/index.php after setting up autoloading.
     *
     * @param string $rootDir Absolute path to the project root (php-bot/).
     * @return array<string, object>  Associative array keyed by instance name.
     */
    public static function bootstrap(string $rootDir): array
    {
        // ── Core ─────────────────────────────────────────────────────
        $config = new \WasapBot\Core\Config($rootDir);
        $logger = new \WasapBot\Core\FileLogger($config);

        // ── HTTP Client ──────────────────────────────────────────────
        $http = new \WasapBot\Core\HttpClient($logger);

        // ── Memory ───────────────────────────────────────────────────
        $memory = new \WasapBot\Core\Memory($config, $logger);

        // ── WAHA API ─────────────────────────────────────────────────
        $wahaApi = new \WasapBot\Services\WahaApi($config, $http, $logger);

        // ── AI Clients ────────────────────────────────────────────────
        $openaiClient   = new \WasapBot\Services\OpenAiClient($config, $http, $logger);
        $deepseekClient = new \WasapBot\Services\DeepSeekClient($config, $http, $logger);

        // ── Services ─────────────────────────────────────────────────
        $girlsService    = new \WasapBot\Services\GirlsService($config, $http, $logger);
        $blacklistService = new \WasapBot\Services\BlacklistService($config, $http, $logger);
        $telegramService = new \WasapBot\Services\TelegramService($config, $http, $logger);

        // ── Session Memory ───────────────────────────────────────────
        $sessionMemory = new \WasapBot\Memory\SessionMemory($config, $logger);

        // ── Pipeline: Input gates ────────────────────────────────────
        $inputGates = [
            new \WasapBot\Pipeline\BotModeGate($config, $logger),
            new \WasapBot\Pipeline\RoutingGate($config, $logger),
            new \WasapBot\Pipeline\DedupGate($config, $logger),
            new \WasapBot\Pipeline\Coalescer($config, $logger),
            new \WasapBot\Pipeline\MessageExtractor($config, $logger),
        ];

        // ── Pipeline: Processors ─────────────────────────────────────
        $processors = [
            new \WasapBot\Pipeline\ContextAssembler($config, $logger, $memory, $sessionMemory),
            new \WasapBot\Pipeline\ToneBuilder($config, $logger),
            new \WasapBot\Pipeline\ResponseNormalizer($config, $logger),
            new \WasapBot\Pipeline\CatalogFormatter($config, $logger),
            new \WasapBot\Pipeline\DedupeReply($config, $logger),
            new \WasapBot\Pipeline\ImageSplitter($config, $logger),
        ];

        // ── Side effects ─────────────────────────────────────────────
        $sideEffects = [
            'leadDetector'   => new \WasapBot\SideEffects\LeadDetector($config, $logger),
            'leadLogger'     => new \WasapBot\SideEffects\LeadLogger($config, $logger),
            'autoOff'        => new \WasapBot\SideEffects\AutoOff($config, $logger),
            'reminderWriter' => new \WasapBot\SideEffects\ReminderWriter($config, $logger),
        ];

        // ── Bot ──────────────────────────────────────────────────────
        $bot = new self(
            config:           $config,
            logger:           $logger,
            http:             $http,
            memory:           $memory,
            wahaApi:          $wahaApi,
            openaiClient:     $openaiClient,
            deepseekClient:   $deepseekClient,
            girlsService:     $girlsService,
            blacklistService: $blacklistService,
            telegramService:  $telegramService,
            sessionMemory:    $sessionMemory,
            inputGates:       $inputGates,
            processors:       $processors,
            sideEffects:      $sideEffects,
        );

        return [
            'config'           => $config,
            'logger'           => $logger,
            'http'             => $http,
            'memory'           => $memory,
            'wahaApi'          => $wahaApi,
            'openaiClient'     => $openaiClient,
            'deepseekClient'   => $deepseekClient,
            'girlsService'     => $girlsService,
            'blacklistService' => $blacklistService,
            'telegramService'  => $telegramService,
            'sessionMemory'    => $sessionMemory,
            'inputGates'       => $inputGates,
            'processors'       => $processors,
            'sideEffects'      => $sideEffects,
            'bot'              => $bot,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Private helpers
    // ─────────────────────────────────────────────────────────────────

    /**
     * Build the full system prompt by combining the base prompt,
     * tone directives, and optional playbook.
     *
     * @param array<string, mixed> $ctx
     */
    private function buildSystemPrompt(array $ctx): string
    {
        // 1. Respect prompt mode: use v2 sections if mode is natural_v2
        $mode = (string) $this->config->get('prompt.mode', '');
        $isV2 = ($mode === 'natural_v2');

        $templateKey = $isV2 ? 'prompt.template_v2' : 'prompt.template';
        $sectionsKey = $isV2 ? 'prompt.sections_v2' : 'prompt.sections';

        $template = (string) $this->config->get($templateKey, '');
        $sections = (array) $this->config->get($sectionsKey, []);

        $base = $template;
        if ($template !== '' && !empty($sections)) {
            foreach ($sections as $key => $value) {
                $base = str_replace('[' . $key . ']', (string) $value, $base);
            }
        }

        // 2. Fallback: if no template, use legacy monolithic prompt
        if (trim($base) === '') {
            $base = (string) $this->config->get('prompt.system_prompt', '');
        }

        // 3. Append tone directives if present
        $toneDirectives = (string) ($ctx['tone_directives'] ?? '');
        if ($toneDirectives !== '') {
            $base .= "\n\n### TONO ACTUAL\n" . $toneDirectives;
        }

        // 4. Append playbook if it exists
        $playbookPath = (string) $this->config->get('files.playbook', 'data/playbook.md');
        if (file_exists($playbookPath) && is_readable($playbookPath)) {
            $playbookContent = @file_get_contents($playbookPath);
            if ($playbookContent !== false && $playbookContent !== '') {
                $base .= "\n\n### PLAYBOOK\n" . $playbookContent;
            }
        }

        return $base;
    }

    /**
     * Send messages via WAHA. First message uses humanization (typing indicators,
     * natural delays). Follow-up URL-only messages (from ImageSplitter) use
     * quick sends without humanization to stay within PHP-FPM timeout.
     *
     * @param array<string, mixed>          $ctx
     * @param array<int, string|array>      $messages  Strings or array entries from ImageSplitter
     *                                                  with keys: text, __is_first, __split_index, etc.
     */
    private function sendMessages(array $ctx, array $messages): void
    {
        $wahaBaseUrl = (string) ($ctx['waha_base_url'] ?? '');
        $wahaChatId  = (string) ($ctx['waha_chat_id'] ?? '');
        $wahaSession = (string) ($ctx['waha_session'] ?? $this->config->get('waha.session', 'default'));

        if ($wahaBaseUrl === '' || $wahaChatId === '') {
            $this->logger->warning('Bot::sendMessages — missing WAHA base URL or chat ID');
            return;
        }

        $humanDelays = (array) $this->config->get('human_delays', []);

        // Pass incoming text and turn count for accurate delay computation
        $incomingText = (string) ($ctx['message_text'] ?? '');
        $turnCount    = (int)   ($ctx['bot_msg_count_recent'] ?? 1);
        if ($turnCount < 1) {
            $turnCount = 1;
        }

        foreach ($messages as $msg) {
            // ImageSplitter returns array entries with a 'text' key; extract it.
            $isFirst = true;
            if (is_array($msg)) {
                $msgStr  = (string) ($msg['text'] ?? '');
                $isFirst = (bool) ($msg['__is_first'] ?? true);
            } else {
                $msgStr = (string) $msg;
            }
            if ($msgStr === '') {
                continue;
            }

            // Use quick send for URL-only follow-up messages (no humanization)
            if (!$isFirst) {
                $this->wahaApi->sendQuick(
                    $wahaBaseUrl,
                    $wahaChatId,
                    $msgStr,
                    $wahaSession,
                );
            } else {
                $this->wahaApi->sendHumanized(
                    $wahaBaseUrl,
                    $wahaChatId,
                    $msgStr,
                    $wahaSession,
                    $humanDelays,
                    $incomingText,
                    $turnCount,
                );
            }

            // Inter-message delay between split messages (presend_sleep_sec seconds)
            $presendSleep = (float) $this->config->get('human_delays.presend_sleep_sec', 4);
            if ($presendSleep > 0) {
                usleep((int) ($presendSleep * 1_000_000));
            }

            $turnCount++; // each sent message counts as a turn for habituation
        }
    }

    /**
     * Execute side effects: lead detection, logging, auto-off, reminders.
     *
     * @param array<string, mixed> $ctx
     */
    private function runSideEffects(array $ctx): void
    {
        $openaiResponse = $ctx['openai_raw_response'] ?? [];

        // ── LeadDetector → TelegramService.sendLeadAlert ─────────────
        if (isset($this->sideEffects['leadDetector'])) {
            /** @var \WasapBot\SideEffects\LeadDetectorInterface $leadDetector */
            $leadDetector = $this->sideEffects['leadDetector'];
            if ($leadDetector instanceof \WasapBot\SideEffects\LeadDetectorInterface
                && $leadDetector->isLead((array) $openaiResponse)
            ) {
                $this->telegramService->sendLeadAlert($ctx);
            }
        }

        // ── LeadLogger ───────────────────────────────────────────────
        if (isset($this->sideEffects['leadLogger'])) {
            $leadLogger = $this->sideEffects['leadLogger'];
            if (method_exists($leadLogger, 'logLead')) {
                $leadLogger->logLead($ctx);
            }
        }

        // ── AutoOff ──────────────────────────────────────────────────
        if (isset($this->sideEffects['autoOff'])) {
            $autoOff = $this->sideEffects['autoOff'];
            if (method_exists($autoOff, 'autoOffIfLead')) {
                $autoOff->autoOffIfLead($ctx);
            }
        }

        // ── ReminderWriter ───────────────────────────────────────────
        if (isset($this->sideEffects['reminderWriter'])) {
            $reminderWriter = $this->sideEffects['reminderWriter'];
            if (method_exists($reminderWriter, 'writeReminder')) {
                $reminderWriter->writeReminder($ctx);
            }
        }
    }
}
