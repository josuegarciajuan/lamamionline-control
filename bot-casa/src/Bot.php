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
        private readonly TelegramServiceInterface     $telegramService,
        private readonly SessionMemoryInterface       $sessionMemory,
        private readonly ?\WasapBot\Services\ClientProfileService $clientProfileService = null,
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
            $fromPhone  = (string) ($ctx['from_phone'] ?? '');
            $lineLast9  = (string) ($ctx['line_last9'] ?? '');
            if ($fromPhone !== '' && $this->blacklistService->isBlacklisted($fromPhone)) {
                $this->logger->info('Bot::handleWebhook — sender blacklisted', [
                    'phone' => $fromPhone,
                ]);
                return null;
            }

            // ── 3b. Create inflight lock (anti-metralleta) ──────────
            $inflightLockDir = $this->inflightLockDir();
            \WasapBot\Pipeline\InflightGate::createLock($inflightLockDir, $fromPhone, $lineLast9);

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

            // ── 7. Client profile context ─────────────────────────────
            if ($this->clientProfileService !== null) {
                $ctx['client_profile_hint'] = $this->clientProfileService->getClientContextHint($fromPhone);
            }

            // ── 8. Audio auto-reply shortcut ─────────────────────────
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
                $ctx['_send_ok'] = true;
                $this->sendMessages($ctx, [$audioReply]);

                // Append memory
                if (!empty($ctx['_send_ok']) && empty($ctx['_cancelled'])) {
                    $this->sessionMemory->appendMessage(
                        (string) ($ctx['thread_id'] ?? ''),
                        $fromPhone,
                        (string) ($ctx['message_text'] ?? ''),
                        $audioReply,
                        $ctx,
                    );
                }

                return $ctx;
            }

            // ── 8b. First-contact greeting shortcut ──────────────────
            // New conversation, no speaker girl yet, not audio: send a
            // minimal greeting and WAIT. Do NOT call the LLM, do NOT
            // send catalog, do NOT offer girls. Deterministic gate.
            $isNewConversation = !empty($ctx['__is_new_conversation']);
            $speakerMode = (string) ($ctx['speaker_mode'] ?? '');
            if ($isNewConversation && $speakerMode === 'encargada') {
                $greetings = (array) $this->config->get('message_variants.first_contact_greetings', []);
                if ($greetings === []) {
                    $greetings = ['hola cari 😊'];
                }
                $greeting = $greetings[array_rand($greetings)];

                $ctx['output_text']   = $greeting;
                $ctx['lead_detected'] = false;
                $ctx['photo_action']  = 'none';

                $this->logger->info('Bot::handleWebhook — first-contact greeting gate', [
                    'phone'     => $fromPhone,
                    'thread_id' => $ctx['thread_id'] ?? '?',
                ]);

                $ctx['_send_ok'] = true;
                $this->sendMessages($ctx, [$greeting]);

                if (!empty($ctx['_send_ok']) && empty($ctx['_cancelled'])) {
                    $this->sessionMemory->appendMessage(
                        (string) ($ctx['thread_id'] ?? ''),
                        $fromPhone,
                        (string) ($ctx['message_text'] ?? ''),
                        $greeting,
                        $ctx,
                    );
                }

                \WasapBot\Pipeline\InflightGate::cleanup($inflightLockDir, $fromPhone, $lineLast9);

                $this->logger->info('Bot::handleWebhook — pipeline completed (first-contact gate)', [
                    'phone'     => $fromPhone,
                    'thread_id' => $ctx['thread_id'] ?? '?',
                ]);

                return $ctx;
            }

            // ── 8c. Classify tone (provider según config global) ──────
            $messageText = (string) ($ctx['message_text'] ?? '');
            $toneProvider = (string) $this->config->get('global_providers.tone_classifier', 'deepseek');
            if ($toneProvider === 'deepseek') {
                $tone = $this->deepseekClient->classifyTone($messageText);
            } else {
                $tone = $this->openaiClient->classifyTone($messageText);
            }

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

            // Build multi-turn chat history from session memory
            $history = $this->buildChatHistory($ctx);

            // Select AI client based on routing line config
            $aiProvider = (string) ($ctx['ai_provider'] ?? 'openai');
            $aiModel    = !empty($ctx['ai_model']) ? (string) $ctx['ai_model'] : null;

            if ($aiProvider === 'deepseek') {
                $aiClient = $this->deepseekClient;
            } else {
                $aiClient = $this->openaiClient;
            }

            // ── Trim context for AI: quitar otras chicas cuando selected_girl está fijado ──
            // Si el LLM ve todas las chicas en girls_config, tiende a ofrecer catálogo
            // incluso con selected_girl_name ≠ "". Esto evita que el cliente se enfade.
            $ctxForAI = $ctx;
            $selectedForAI = trim((string) ($ctx['selected_girl_name'] ?? ''));
            $wantsMore     = !empty($ctx['wants_more_girls']);
            if ($selectedForAI !== '' && !$wantsMore && !empty($ctxForAI['girls_config'])) {
                $trimmed = array_values(array_filter(
                    $ctxForAI['girls_config'],
                    static fn(array $g): bool =>
                        trim((string) ($g['nombre'] ?? '')) === $selectedForAI
                ));
                // Safety: if filtering removed ALL girls (name mismatch), keep original
                if ($trimmed !== []) {
                    $ctxForAI['girls_config'] = $trimmed;
                }
            }

            $openaiResponse = $aiClient->chat(
                $systemPrompt,
                (string) $userMessage,
                $ctxForAI,
                $aiModel,
                $history,
            );

            $ctx['openai_raw_response'] = $openaiResponse;
            $ctx['openai_choices']      = $openaiResponse['choices'] ?? [];

            // ── 11. Normalize response (ResponseNormalizer) ──────────
            if (isset($this->processors[2]) && $this->processors[2]->name() === 'ResponseNormalizer') {
                $ctx = $this->processors[2]->process($ctx);
            } elseif (!empty($openaiResponse['choices'])) {
                $ctx['output_text'] = $openaiResponse['choices'][0]['message']['content'] ?? '';
            }

            // ── 11b. Fallback: if LLM returned empty, send contingency text ──
            if (trim((string) ($ctx['output_text'] ?? '')) === '') {
                $fallbackRaw = $this->config->get('message_variants.fallback_empty_text', ['vale cari']);
                $fallbackVariants = is_string($fallbackRaw) ? [$fallbackRaw] : (array) $fallbackRaw;
                $fallback = $fallbackVariants !== [] ? $fallbackVariants[array_rand($fallbackVariants)] : 'vale cari';
                $ctx['output_text']   = $fallback;
                $ctx['lead_detected'] = false;
                $this->logger->info('Bot::handleWebhook — using fallback empty text (LLM returned no response)', [
                    'phone'     => $fromPhone,
                    'thread_id' => $ctx['thread_id'] ?? '?',
                    'fallback'  => $fallback,
                ]);
            }

            // ── 12. Catalog formatter (CatalogFormatter) ─────────────
            $textBeforeCatalog = $ctx['output_text'] ?? '';
            if (isset($this->processors[3]) && $this->processors[3]->name() === 'CatalogFormatter') {
                $ctx = $this->processors[3]->process($ctx);
            }
            // Diagnostic: log if CatalogFormatter added photos via regex fallback
            $textAfterCatalog = $ctx['output_text'] ?? '';
            $photoAction = $ctx['photo_action'] ?? 'none';
            if ($photoAction === 'none' && $textAfterCatalog !== $textBeforeCatalog) {
                $hasNewPhotos = (bool) preg_match('/(?:https?:\/\/(?:compartir\.site|ibb\.co|i\.ibb\.co)\/)/i', $textAfterCatalog)
                    && !preg_match('/(?:https?:\/\/(?:compartir\.site|ibb\.co|i\.ibb\.co)\/)/i', (string) $textBeforeCatalog);
                if ($hasNewPhotos) {
                    $this->logger->info('Bot::CatalogFormatter — injected photos via regex fallback (photo_action=none)', [
                        'phone'     => $fromPhone,
                        'thread_id' => $ctx['thread_id'] ?? '?',
                    ]);
                }
            }

            // ── 13. Dedupe reply (DedupeReply) ───────────────────────
            if (isset($this->processors[4]) && $this->processors[4]->name() === 'DedupeReply') {
                $ctx = $this->processors[4]->process($ctx);
            }

            // ── 14. Image splitter (ImageSplitter) ───────────────────
            if (isset($this->processors[5]) && $this->processors[5]->name() === 'ImageSplitter') {
                $ctx = $this->processors[5]->process($ctx);
            }

            // ── 14b. POST-AI: inyectar location_url si el AI dice que envía mapa pero no incluye URL ──
            $ctx = $this->injectLocationUrl($ctx);

            // ── 14c. POST-AI: inyectar fotos si el AI promete enviarlas pero no llegaron ──
            $ctx = $this->injectPhotoUrls($ctx);

            $messages = $ctx['splitted_messages'] ?? [$ctx['output_text'] ?? ''];

            // ── 14d. Pre-send: check for messages that arrived during processing ──
            $ctx = $this->handleIncomingWhileProcessing($ctx, $inflightLockDir, $fromPhone);
            $messages = $ctx['splitted_messages'] ?? [$ctx['output_text'] ?? ''];

            // ── 15. Send via WAHA with humanization ──────────────────
            $ctx['_send_ok'] = true;
            $this->sendMessages($ctx, (array) $messages, $inflightLockDir, $fromPhone);

            // ── 16. Append to session memory ─────────────────────────
            if (!empty($ctx['_send_ok']) && empty($ctx['_cancelled'])) {
                $this->sessionMemory->appendMessage(
                    (string) ($ctx['thread_id'] ?? ''),
                    $fromPhone,
                    (string) ($ctx['message_text'] ?? $messageText),
                    (string) ($ctx['output_text'] ?? ''),
                    $ctx,
                );
            } elseif (empty($ctx['_cancel_logged'] ?? false) && !empty($ctx['_cancelled'])) {
                $this->logger->info('Bot::handleWebhook — response cancelled, not saved to memory', [
                    'thread_id' => $ctx['thread_id'] ?? '?',
                    'phone'     => $fromPhone,
                ]);
                $ctx['_cancel_logged'] = true;
            } else {
                $this->logger->error('Bot::handleWebhook — WAHA send failed, response NOT saved', [
                    'thread_id' => $ctx['thread_id'] ?? '?',
                    'phone'     => $fromPhone,
                ]);
            }

            // ── 17. Side effects ─────────────────────────────────────
            $this->runSideEffects($ctx);

            // ── 18. Cleanup inflight lock ────────────────────────────
            \WasapBot\Pipeline\InflightGate::cleanup($inflightLockDir, $fromPhone, $lineLast9);

            // ── 19. Return full context ──────────────────────────────
            $this->logger->info('Bot::handleWebhook — pipeline completed', [
                'phone'     => $fromPhone,
                'thread_id' => $ctx['thread_id'] ?? '?',
            ]);

            return $ctx;
        } catch (\Throwable $e) {
            $this->logger->error('Bot::handleWebhook — exception: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            // Cleanup on error
            $phone     = (string) ($ctx['from_phone'] ?? '');
            $lineLast9 = (string) ($ctx['line_last9'] ?? '');
            if ($phone !== '') {
                \WasapBot\Pipeline\InflightGate::cleanup($this->inflightLockDir(), $phone, $lineLast9);
            }
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
    /**
     * Resolve a data path for a specific user. If userId > 0 and that
     * user has a data directory at data/users/{userId}/, return the
     * user-specific path. Otherwise return the legacy root-level path.
     *
     * @param string $rootDir   Project root (WASAPBOT_ROOT)
     * @param int    $userId    User ID (0 = legacy / default)
     * @param string $relative  Relative path from data/ (e.g. "session_memory.ndjson")
     * @return string Absolute path
     */
    public static function resolveUserDataPath(string $rootDir, int $userId, string $relative): string
    {
        // For non-admin users (ID > 1), ALWAYS use user-specific path
        // to prevent data mixing between users
        if ($userId > 1) {
            $userDataDir = rtrim($rootDir, '/') . '/data/users/' . $userId;
            if (!is_dir($userDataDir)) {
                @mkdir($userDataDir, 0700, true);
            }
            return $userDataDir . '/' . ltrim($relative, '/');
        }

        // For admin (userId=1) or legacy (userId=0): 
        // use user-specific path if it exists, otherwise root
        if ($userId > 0) {
            $userDataDir = rtrim($rootDir, '/') . '/data/users/' . $userId;
            $userFilePath = $userDataDir . '/' . ltrim($relative, '/');
            if (is_dir($userDataDir) || file_exists($userFilePath)) {
                if (!is_dir($userDataDir)) {
                    @mkdir($userDataDir, 0750, true);
                }
                return $userFilePath;
            }
        }
        // Legacy fallback
        return rtrim($rootDir, '/') . '/data/' . ltrim($relative, '/');
    }

    /**
     * Get the config directory for a specific user.
     * If userId > 0 and data/users/{userId}/config.local.json exists,
     * the user dir becomes the config dir (so Config loads from there).
     * Otherwise returns the root config dir.
     */
    public static function resolveUserConfigDir(string $rootDir, int $userId): string
    {
        if ($userId > 0) {
            $userDir = rtrim($rootDir, '/') . '/data/users/' . $userId;
            if (is_dir($userDir) || file_exists($userDir . '/config.local.json')) {
                if (!is_dir($userDir)) {
                    @mkdir($userDir, 0755, true);
                }
                // If no config.local.json in user dir, create from dist template (not root's local)
                if (!file_exists($userDir . '/config.local.json')) {
                    $rootDist = $rootDir . '/config.dist.json';
                    if (file_exists($rootDist)) {
                        @copy($rootDist, $userDir . '/config.local.json');
                    }
                }
                return $userDir;
            }
        }
        return $rootDir;
    }

    public static function bootstrap(string $rootDir, int $userId = 0): array
    {
        // ── Resolve config directory for this user ───────────────────
        $configDir = self::resolveUserConfigDir($rootDir, $userId);

        // ── Core ─────────────────────────────────────────────────────
        $config = new \WasapBot\Core\Config($configDir);

        // ── Override relative data paths to be user-specific ─────────
        if ($userId > 0) {
            $fileKeys = [
                'files.session_memory', 'files.leads', 'files.reminders',
                'files.playbook', 'files.wa_raw_payload', 'files.bot_log',
                'bot.mode_file',
            ];
            foreach ($fileKeys as $key) {
                $val = $config->get($key, '');
                if (is_string($val) && $val !== '') {
                    $resolved = self::resolveUserDataPath($rootDir, $userId, $val);
                    // Only override if the user data dir exists or we're explicitly in multi-user mode
                    $userDataDir = rtrim($rootDir, '/') . '/data/users/' . $userId;
                    if (is_dir($userDataDir)) {
                        $config->set($key, $resolved);
                    }
                }
            }
            // Override lock dirs
            $lockKeys = ['files.session_memory_lock', 'files.leads_lock', 'files.reminders_lock'];
            foreach ($lockKeys as $key) {
                $val = $config->get($key, '');
                if (is_string($val) && $val !== '') {
                    $resolved = self::resolveUserDataPath($rootDir, $userId, $val);
                    $userDataDir = rtrim($rootDir, '/') . '/data/users/' . $userId;
                    if (is_dir($userDataDir)) {
                        $config->set($key, $resolved);
                    }
                }
            }
        }

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
        $girlsService      = new \WasapBot\Services\GirlsService($config, $http, $logger);
        $blacklistService  = new \WasapBot\Services\BlacklistService($config, $http, $logger);
        $telegramService   = new \WasapBot\Services\TelegramService($config, $http, $logger);
        $clientProfileSvc  = new \WasapBot\Services\ClientProfileService($config, $logger);

        // ── Session Memory ───────────────────────────────────────────
        $sessionMemory = new \WasapBot\Memory\SessionMemory($config, $logger);

        // ── Pipeline: Input gates ────────────────────────────────────
        $pauseGate = new \WasapBot\Pipeline\PauseGate($config, $logger);
        $inputGates = [
            new \WasapBot\Pipeline\BotModeGate($config, $logger),
            new \WasapBot\Pipeline\RoutingGate($config, $logger),
            new \WasapBot\Pipeline\DedupGate($config, $logger),
            new \WasapBot\Pipeline\Coalescer($config, $logger),
            new \WasapBot\Pipeline\MessageExtractor($config, $logger),
            $pauseGate,
            new \WasapBot\Pipeline\InflightGate($config),
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
            config:                $config,
            logger:                $logger,
            http:                  $http,
            memory:                $memory,
            wahaApi:               $wahaApi,
            openaiClient:          $openaiClient,
            deepseekClient:        $deepseekClient,
            girlsService:          $girlsService,
            blacklistService:      $blacklistService,
            telegramService:       $telegramService,
            sessionMemory:         $sessionMemory,
            clientProfileService:  $clientProfileSvc,
            inputGates:            $inputGates,
            processors:            $processors,
            sideEffects:           $sideEffects,
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
            'clientProfileService' => $clientProfileSvc,
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
        $rootDir = defined('WASAPBOT_ROOT') ? WASAPBOT_ROOT : dirname(__DIR__);
        if (!str_starts_with($playbookPath, '/')) {
            $playbookPath = $rootDir . '/' . ltrim($playbookPath, '/');
        }
        if (file_exists($playbookPath) && is_readable($playbookPath)) {
            $playbookContent = @file_get_contents($playbookPath);
            if ($playbookContent !== false && $playbookContent !== '') {
                $base .= "\n\n### PLAYBOOK\n" . $playbookContent;

                // If playbook contains human style guide, add explicit adoption directive
                if (strpos($playbookContent, '## Guía de estilo del humano') !== false) {
                    $base .= "\n\n### DIRECTRIZ DE ESTILO HUMANO\n"
                           . "Cuando respondas, adopta el estilo de comunicación descrito en la "
                           . "'Guía de estilo del humano' del playbook. Usa sus mismas coletillas, "
                           . "emojis, tono, longitud de mensaje y estructura. Tu objetivo es que "
                           . "el cliente no note la diferencia entre tú y el operador humano que "
                           . "entrena este bot.\n";
                }
            }
        }

        return $base;
    }

    /**
     * Build a multi-turn chat history array from session memory.
     *
     * Reads the last N conversation turns from session memory for the current
     * thread and returns them as [{role, content}, ...] for the LLM.
     *
     * @param array<string, mixed> $ctx  Current context (must contain thread_id)
     * @return list<array{role: string, content: string}>
     */
    private function buildChatHistory(array $ctx): array
    {
        $threadId = (string) ($ctx['thread_id'] ?? '');
        if ($threadId === '') {
            return [];
        }

        try {
            $rawHistory = $this->sessionMemory->readThread($threadId);
        } catch (\Throwable $e) {
            $this->logger->warning('Bot::buildChatHistory — failed to read thread: ' . $e->getMessage());
            return [];
        }

        if ($rawHistory === []) {
            return [];
        }

        // Filter to recent window (last 6h) and take last N turns
        $recentWindowH = (int) $this->config->get('memory.recent_window_hours', 6);
        $maxTurns      = (int) $this->config->get('memory.max_history_turns', 15);
        $now           = time();

        $recent = [];
        foreach ($rawHistory as $rec) {
            $ts = strtotime((string) ($rec['ts'] ?? ''));
            if ($ts !== false && ($now - $ts) <= $recentWindowH * 3600) {
                $recent[] = $rec;
            }
        }

        // If no recent history, fallback to last N of all history
        if ($recent === []) {
            $recent = array_slice($rawHistory, -$maxTurns);
        } else {
            $recent = array_slice($recent, -$maxTurns);
        }

        // Build alternating user/assistant messages
        $history = [];
        foreach ($recent as $rec) {
            $userMsg = (string) ($rec['user_msg'] ?? '');
            $botMsg  = (string) ($rec['bot_reply'] ?? '');

            if ($userMsg !== '') {
                $history[] = ['role' => 'user', 'content' => $userMsg];
            }
            if ($botMsg !== '') {
                $history[] = ['role' => 'assistant', 'content' => $botMsg];
            }
        }

        return $history;
    }

    /**
     * Send messages via WAHA. First message uses humanization (typing indicators,
     * natural delays). Follow-up URL-only messages (from ImageSplitter) use
     * quick sends without humanization to stay within PHP-FPM timeout.
     *
     * @param array<string, mixed>          $ctx        Pipeline context (updated in-place if re-process needed).
     * @param array<int, string|array>      $messages   Strings or array entries from ImageSplitter.
     * @param string                        $lockDir    Inflight lock directory (for anti-metralleta).
     * @param string                        $fromPhone  Sender phone (for anti-metralleta).
     */

    /**
     * Find the PauseGate instance in the input gates array.
     */
    private function getPauseGate(): ?\WasapBot\Pipeline\PauseGate
    {
        foreach ($this->inputGates as $gate) {
            if ($gate instanceof \WasapBot\Pipeline\PauseGate) {
                return $gate;
            }
        }
        return null;
    }

    private function sendMessages(array &$ctx, array $messages, string $lockDir = '', string $fromPhone = ''): void
    {
        // ── Cancel check: if user paused this thread mid-generation, abort send ──
        $threadId = (string) ($ctx['thread_id'] ?? $ctx['__thread_id'] ?? '');
        $pauseGate = $this->getPauseGate();
        if ($threadId !== '' && $pauseGate !== null && $pauseGate->hasCancelRequest($threadId)) {
            $this->logger->info('Bot::sendMessages — response cancelled by user pause', [
                'thread_id' => $threadId,
            ]);
            $pauseGate->clearCancelRequest($threadId);
            $ctx['_cancelled'] = true;
            $ctx['_send_ok']   = false;
            return;
        }

        // ── Anti-metralleta: last-moment check BEFORE typing simulation ──
        // Catches messages that arrived during the LLM call + pipeline processing
        // but before we committed to sending via WAHA.
        static $sendDepth = 0;
        $sendDepth++;
        if ($sendDepth > 5) {
            $sendDepth = 0;
            return; // Safety valve
        }

        if ($lockDir !== '' && $fromPhone !== '') {
            $lineLast9 = (string) ($ctx['line_last9'] ?? '');
            $pending = \WasapBot\Pipeline\InflightGate::drainPending($lockDir, $fromPhone, $lineLast9);
            if ($pending !== []) {
                // If already re-processed once, merge without full LLM re-run to avoid cascade
                $alreadyReprocessed = ((int) ($ctx['__pending_drained_count'] ?? 0)) >= 1;
                if ($alreadyReprocessed) {
                    $this->logger->info('Bot::sendMessages — new messages arrived but already re-processed, merging without LLM', [
                        'phone'       => $fromPhone,
                        'num_pending' => count($pending),
                        'send_depth'  => $sendDepth,
                    ]);
                    foreach ($pending as $p) {
                        $t = (string) ($p['message_text'] ?? '');
                        if ($t !== '') {
                            $messages[] = ['text' => $t, '__is_first' => false, '__split_index' => count($messages)];
                        }
                    }
                    $ctx['splitted_messages'] = $messages;
                    // Don't recurse — fall through to send current + appended messages
                } else {
                    $this->logger->info('Bot::sendMessages — new messages arrived before send, re-processing', [
                        'phone'       => $fromPhone,
                        'num_pending' => count($pending),
                        'send_depth'  => $sendDepth,
                    ]);
                    // Append pending messages to current message_text and re-process
                    $originalText = (string) ($ctx['message_text'] ?? '');
                    $parts = $originalText !== '' ? [$originalText] : [];
                    foreach ($pending as $p) {
                        $t = (string) ($p['message_text'] ?? '');
                        if ($t !== '' && $t !== $originalText) {
                            $parts[] = $t;
                        }
                    }
                    $ctx['message_text']     = implode(' | ', $parts);
                    $ctx['__coalesced_text'] = $ctx['message_text'];
                    $ctx['__reprocess_depth'] = ((int) ($ctx['__reprocess_depth'] ?? 0)) + 1;
                    $ctx['__is_reprocess']   = true;

                    // Re-run the full LLM pipeline with the coalesced input
                    $ctx = $this->handleIncomingWhileProcessing($ctx, $lockDir, $fromPhone);

                    // Reload messages after re-processing and recurse
                    $newMessages = $ctx['splitted_messages'] ?? [$ctx['output_text'] ?? ''];
                    $this->sendMessages($ctx, $newMessages, $lockDir, $fromPhone);
                    $sendDepth--;
                    return;
                }
            }
        }

        $wahaBaseUrl = (string) ($ctx['waha_base_url'] ?? '');
        $wahaChatId  = (string) ($ctx['waha_chat_id'] ?? '');
        $wahaSession = (string) ($ctx['waha_session'] ?? $this->config->get('waha.session', 'default'));

        if ($wahaBaseUrl === '' || $wahaChatId === '') {
            $this->logger->warning('Bot::sendMessages — missing WAHA base URL or chat ID');
            $sendDepth--;
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

            // ── Intra-loop cancel check: user may have paused during typing simulation ──
            if ($threadId !== '' && $pauseGate !== null && $pauseGate->hasCancelRequest($threadId)) {
                $this->logger->info('Bot::sendMessages — response cancelled mid-send by user pause', [
                    'thread_id' => $threadId,
                ]);
                $pauseGate->clearCancelRequest($threadId);
                $ctx['_cancelled'] = true;
                $ctx['_send_ok']   = false;
                break;
            }

            // Use quick send for URL-only follow-up messages (no humanization)
            if (!$isFirst) {
                $ok = $this->wahaApi->sendQuick(
                    $wahaBaseUrl,
                    $wahaChatId,
                    $msgStr,
                    $wahaSession,
                );
            } else {
                $ok = $this->wahaApi->sendHumanized(
                    $wahaBaseUrl,
                    $wahaChatId,
                    $msgStr,
                    $wahaSession,
                    $humanDelays,
                    $incomingText,
                    $turnCount,
                );
            }

            if (!$ok) {
                $ctx['_send_ok'] = false;
            }

            // Inter-message delay between split messages (presend_sleep_sec seconds)
            $presendSleep = (float) $this->config->get('human_delays.presend_sleep_sec', 4);
            if ($presendSleep > 0) {
                usleep((int) ($presendSleep * 1_000_000));
            }

            $turnCount++; // each sent message counts as a turn for habituation
        }
        $sendDepth--;
    }

    /**
     * Execute side effects: lead detection, logging, auto-off, reminders.
     *
     * @param array<string, mixed> $ctx
     */
    private function runSideEffects(array $ctx): void
    {
        $openaiResponse = $ctx['openai_raw_response'] ?? [];

        // ── 0. Check per-thread lead lock (prevents re-trigger after bot restart) ──
        $threadId = (string) ($ctx['thread_id'] ?? $ctx['__thread_id'] ?? '');
        $threadAlreadyNotified = false;
        if ($threadId !== '') {
            $baseDataDir = (string) $this->config->get('files.base_data_dir', 'data');
            $leadLockDir = rtrim($baseDataDir, '/') . '/locks/lead_detected';
            $leadLockFile = $leadLockDir . '/lead_' . md5($threadId) . '.lock';
            if (is_file($leadLockFile)) {
                $threadAlreadyNotified = true;
                $this->logger->info('runSideEffects: thread already has lead lock, skipping alerts/logging', [
                    'thread_id' => $threadId,
                ]);
            }
        }

        // ── 1. AutoOff — stop bot FIRST (before any alert), so new webhooks see "stop" ──
        if (isset($this->sideEffects['autoOff'])) {
            $autoOff = $this->sideEffects['autoOff'];
            if (method_exists($autoOff, 'autoOffIfLead')) {
                $autoOff->autoOffIfLead($ctx);
            }
        }

        // ── 2. LeadDetector + server-side validation → TelegramService ──
        // NOVA: Don't blindly trust the LLM's lead_detected flag.
        // The DeepSeek model often hallucinates leads from service negotiation
        // ("1h sin parar"), price questions, or first-message interest.
        // Server-side gates validate against actual context state.
        $leadValid = false;
        $leadConfidence = 0.0;
        if (!$threadAlreadyNotified && isset($this->sideEffects['leadDetector'])) {
            /** @var \WasapBot\SideEffects\LeadDetectorInterface $leadDetector */
            $leadDetector = $this->sideEffects['leadDetector'];
            if ($leadDetector instanceof \WasapBot\SideEffects\LeadDetectorInterface
                && $leadDetector->isLead((array) $openaiResponse)
            ) {
                $leadConfidence = $leadDetector->confidence((array) $openaiResponse);
                $mapsSent = (bool) ($ctx['maps_sent'] ?? false);
                $etaFromUser = (bool) ($ctx['eta_from_user_flag'] ?? false);
                $isNewConvo = !empty($ctx['__is_new_conversation']);
                $botMsgCount = (int) ($ctx['bot_msg_count_recent'] ?? 0);

                $leadValid = true; // Start trusting LLM, then gate it

                // Gate A: First contact — user hasn't committed to anything yet.
                // The LLM tends to flag "me gustaría quedar contigo" as lead
                // on the very first message, which is almost never real.
                // EXCEPTION: if the user already gave explicit ETA on first message
                // (e.g., "voy en 20 min" from an auto-generated opener), allow it.
                if ($isNewConvo && $leadConfidence < 0.98 && !$etaFromUser) {
                    $leadValid = false;
                    $this->logger->info('LeadDetector: overridden by first-contact gate (LLM confidence too low for new convo)', [
                        'phone'               => $ctx['from_phone'] ?? '?',
                        'thread_id'           => $threadId,
                        'lead_confidence'     => $leadConfidence,
                        'user_message'        => (string) ($ctx['message_text'] ?? ''),
                    ]);
                }

                // Gate B: No maps sent AND no ETA from user — LLM is hallucinating.
                // The prompt explicitly requires maps_sent=true for media signals,
                // but the LLM often ignores this. Enforce it server-side.
                if ($leadValid && !$mapsSent && !$etaFromUser && $leadConfidence < 0.98) {
                    $leadValid = false;
                    $this->logger->info('LeadDetector: overridden by no-evidence gate (maps_sent=false + no ETA from user)', [
                        'phone'               => $ctx['from_phone'] ?? '?',
                        'thread_id'           => $threadId,
                        'lead_confidence'     => $leadConfidence,
                        'maps_sent'           => $mapsSent,
                        'eta_from_user_flag'  => $etaFromUser,
                        'user_message'        => (string) ($ctx['message_text'] ?? ''),
                    ]);
                }

                // Gate C: Very early conversation (bot has sent < 2 messages).
                // The LLM gets overexcited before enough context exists.
                if ($leadValid && $botMsgCount < 2 && !$etaFromUser && $leadConfidence < 0.98) {
                    $leadValid = false;
                    $this->logger->info('LeadDetector: overridden by early-conversation gate', [
                        'phone'               => $ctx['from_phone'] ?? '?',
                        'thread_id'           => $threadId,
                        'lead_confidence'     => $leadConfidence,
                        'bot_msg_count'       => $botMsgCount,
                    ]);
                }

                if ($leadValid) {
                    $this->telegramService->sendLeadAlert($ctx);
                }
            }
        }

        // ── 3. LeadLogger (only if lead passed server-side validation) ──
        if (!$threadAlreadyNotified && isset($this->sideEffects['leadLogger'])) {
            $leadLogger = $this->sideEffects['leadLogger'];
            if ($leadValid && method_exists($leadLogger, 'logLead')) {
                $leadLogger->logLead($ctx);
            }
        }

        // ── 4. ReminderWriter ───────────────────────────────────────────
        if (isset($this->sideEffects['reminderWriter'])) {
            $reminderWriter = $this->sideEffects['reminderWriter'];
            if (method_exists($reminderWriter, 'writeReminder')) {
                $reminderWriter->writeReminder($ctx);
            }
        }
    }

    /**
     * POST-AI safety net: if the bot text mentions sending location/maps/GPS
     * but no actual maps URL is in the response, inject the location_url
     * from config as a follow-up message.
     *
     * Conditions:
     *  - maps_sent is false (no real maps URL sent yet in this conversation)
     *  - location_url is available from config
     *  - AI response mentions location-related words (ubicación, maps, gps, etc.)
     *  - selected_girl_name is not empty OR choose_loop_count >= 3 (insistencia)
     *
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    private function injectLocationUrl(array $ctx): array
    {
        // Only act if maps hasn't actually been sent yet
        $mapsSent = (bool) ($ctx['maps_sent'] ?? false);
        if ($mapsSent) {
            return $ctx;
        }

        // Only if we have a location URL to send
        $locationUrl = (string) ($ctx['location_url'] ?? '');
        if ($locationUrl === '') {
            return $ctx;
        }

        // Must have a selected girl or high insistence (choose_loop_count >= 3)
        $selectedGirl = (string) ($ctx['selected_girl_name'] ?? '');
        $chooseLoopCount = (int) ($ctx['choose_loop_count'] ?? 0);

        // ── ENFORCE maps_solo_chica: no girl selected → strip any maps URL the AI sent ──
        // The AI sometimes ignores the prompt instruction "PROHIBIDO mandar location_url
        // sin chica seleccionada" and includes the URL anyway. This code-level guard
        // removes it before the message reaches the client.
        if ($selectedGirl === '' && $chooseLoopCount < 3) {
            $outputText = (string) ($ctx['output_text'] ?? '');
            if ($outputText !== '') {
                $mapsUrlPattern = '/(?:https?:\/\/)?(?:goo\.gl\/maps|maps\.app\.goo\.gl|google\.com\/maps|maps\.google\.com)[^\s]*/i';
                $hasMapsUrl = (bool) preg_match($mapsUrlPattern, $outputText);
                $hasLocationWords = (bool) preg_match(
                    '/(?:te\s*paso\s*(?:la\s*)?ubicaci[oó]n|te\s*paso\s*(?:el|la)\s*(?:maps?\b|mapa\b|gps\b|direcci[oó]n)|aqui\s*(?:va|tienes|esta)\s*(?:el|la)\s*(?:mapa|ubicaci[oó]n|direcci[oó]n|gps)|te\s*(?:mando|env[ií]o)\s*(?:la\s*)?(?:ubicaci[oó]n|direcci[oó]n|mapa|gps)|ubicaci[oó]n\s*exacta|punto\s*exacto)/iu',
                    $outputText
                );
                if ($hasMapsUrl || $hasLocationWords) {
                    // Strip maps URL
                    $cleanedText = preg_replace($mapsUrlPattern, '', $outputText);
                    // Replace location-sending language with "choose girl first"
                    $cleanedText = preg_replace(
                        '/(?:te\s*paso\s*(?:la\s*)?ubicaci[oó]n[^.]*\.?|te\s*paso\s*(?:el|la)\s*(?:maps?\b|mapa\b|gps\b|direcci[oó]n)[^.]*\.?|aqui\s*(?:va|tienes|esta)\s*(?:el|la)\s*(?:mapa|ubicaci[oó]n|direcci[oó]n|gps)[^.]*\.?|te\s*(?:mando|env[ií]o)\s*(?:la\s*)?(?:ubicaci[oó]n|direcci[oó]n|mapa|gps)[^.]*\.?|ubicaci[oó]n\s*exacta[^.]*\.?|punto\s*exacto[^.]*\.?)/iu',
                        '',
                        $cleanedText
                    );
                    $cleanedText = trim(preg_replace('/\s{2,}/', ' ', $cleanedText));
                    if ($cleanedText === '' || strlen($cleanedText) < 6) {
                        $cleanedText = 'dime cual te gusta y te paso la ubicacion cari';
                    }
                    $ctx['output_text'] = $cleanedText;
                    // Update splitted_messages too
                    $messages = $ctx['splitted_messages'] ?? [$outputText];
                    foreach ($messages as &$msg) {
                        if (is_array($msg)) {
                            $msg['text'] = $cleanedText;
                        } else {
                            $msg = $cleanedText;
                        }
                    }
                    unset($msg);
                    $ctx['splitted_messages'] = $messages;
                    $this->logger->info('Bot::injectLocationUrl — blocked maps (no girl selected)', [
                        'phone'     => $ctx['from_phone'] ?? '?',
                        'thread_id' => $ctx['thread_id'] ?? '?',
                    ]);
                }
            }
            return $ctx;
        }

        // Check if AI response already contains a maps URL
        $outputText = (string) ($ctx['output_text'] ?? '');
        if ($outputText === '') {
            return $ctx;
        }

        $hasMapsUrl = (bool) preg_match(
            '/(?:https?:\/\/)?(?:goo\.gl\/maps|maps\.app\.goo\.gl|google\.com\/maps|maps\.google\.com)/i',
            $outputText
        );
        if ($hasMapsUrl) {
            return $ctx; // Already has URL, nothing to inject
        }

        // Check if AI response contains location-sending language
        $locationWords = '/(?:te\s*paso\s*(?:la\s*)?ubicaci[oó]n|te\s*paso\s*(?:el|la)\s*(?:maps?\b|mapa\b|gps\b|direcci[oó]n)|aqui\s*(?:va|tienes|esta)\s*(?:el|la)\s*(?:mapa|ubicaci[oó]n|direcci[oó]n|gps)|te\s*(?:mando|env[ií]o)\s*(?:la\s*)?(?:ubicaci[oó]n|direcci[oó]n|mapa|gps)|ubicaci[oó]n\s*exacta|punto\s*exacto)/iu';
        // Also trigger if the context flags predict maps is being sent now (deterministic fallback)
        $mapsBeingSentNow = !empty($ctx['maps_being_sent_now']);
        if (!preg_match($locationWords, $outputText) && !$mapsBeingSentNow) {
            return $ctx; // Neither text pattern nor deterministic flag → skip
        }

        // Inject the maps URL as a follow-up message
        $messages = $ctx['splitted_messages'] ?? [$outputText];
        // Append location URL as a new solo message
        $messages[] = [
            '__split_index' => count($messages),
            '__is_first'    => false,
            'text'          => $locationUrl,
        ];
        $ctx['splitted_messages'] = $messages;

        // Also append URL to output_text for session memory consistency
        $ctx['output_text'] = $outputText . "\n" . $locationUrl;

        // ── NOVA: When injecting maps, also ensure ETA request is in the text ──
        // Modify the first message text to include an ETA request if it doesn't already
        $etaPatterns = [
            '/cu[aá]nto\s+tardas/i',
            '/cu[aá]ndo\s+llegas/i',
            '/av[ií]same\s+cu[aá]ndo\s+salgas/i',
            '/dime\s+cu[aá]nto/i',
            '/en\s+cu[aá]ntos?\s+min/i',
            '/tardas\s+mucho/i',
            '/te\s+espero/i',
        ];
        $hasEta = false;
        foreach ($etaPatterns as $pat) {
            if (preg_match($pat, $outputText)) {
                $hasEta = true;
                break;
            }
        }

        // NOVA FIX: solo inyectar ETA request si el cliente ya ha mostrado interés fuerte
        // o ha insistido (choose_loop >= 2). Evita preguntar "cuánto tardas?" a alguien
        // que solo preguntó la ubicación por curiosidad, sin intención real de venir.
        $interesFuerte = !empty($ctx['interes_fuerte']);
        $chooseLoop = (int) ($ctx['choose_loop_count'] ?? 0);
        $hasEtaFromUser = !empty($ctx['eta_from_user_flag']);
        if (!$hasEta && !empty($messages[0]['text']) && ($interesFuerte || $chooseLoop >= 2 || $hasEtaFromUser)) {
            $etaVariants = (array) $this->config->get('message_variants.eta_request_variants', [
                'dime cuanto tardas?',
                'avisame cuando salgas',
                'en cuantos min vienes?',
            ]);
            $pick = $etaVariants[array_rand($etaVariants)];
            // Append ETA request to the first message text
            $messages[0]['text'] = rtrim((string) $messages[0]['text']) . ' ' . $pick;
            $ctx['splitted_messages'] = $messages;
            // Also update output_text for session memory
            $ctx['output_text'] = rtrim($outputText) . ' ' . $pick . "\n" . $locationUrl;

            $this->logger->info('Bot::injectLocationUrl — added ETA request to maps message', [
                'phone'     => $ctx['from_phone'] ?? '?',
                'eta_pick'  => $pick,
            ]);
        }

        $this->logger->info('Bot::injectLocationUrl — injected maps URL as follow-up', [
            'phone'     => $ctx['from_phone'] ?? '?',
            'thread_id' => $ctx['thread_id'] ?? '?',
        ]);

        return $ctx;
    }

    /**
     * POST-AI safety net: if the bot text promises to send photos
     * but photo_action was "none" (LLM forgot the flag) AND no photos
     * have been sent yet in this conversation, force the catalog.
     *
     * This catches the common pattern where the LLM says "te paso fotos"
     * in its user_visible_reply but didn't set photo_action, breaking the promise.
     *
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    private function injectPhotoUrls(array $ctx): array
    {
        $outputText = (string) ($ctx['output_text'] ?? '');
        if ($outputText === '') {
            return $ctx;
        }

        // Skip if LLM already set a photo_action (CatalogFormatter handled it)
        $photoAction = $ctx['photo_action'] ?? 'none';
        if ($photoAction !== 'none') {
            return $ctx;
        }

        // Skip if CatalogFormatter already injected photo URLs (avoid double injection)
        $alreadyInjected = false;
        $splittedMessages = $ctx['splitted_messages'] ?? [];
        $photoUrlPattern = '/(?:https?:\/\/(?:compartir\.site|ibb\.co|i\.ibb\.co)\/)/i';
        foreach ($splittedMessages as $msg) {
            $text = is_array($msg) ? ((string) ($msg['text'] ?? '')) : (string) $msg;
            if ($text !== '' && preg_match($photoUrlPattern, $text)) {
                $alreadyInjected = true;
                break;
            }
        }
        // Also check the output_text itself
        if (!$alreadyInjected && preg_match($photoUrlPattern, $outputText)) {
            $alreadyInjected = true;
        }
        if ($alreadyInjected) {
            $this->logger->debug('Bot::injectPhotoUrls — skipped (CatalogFormatter already injected photos)', [
                'phone'     => $ctx['from_phone'] ?? '?',
                'thread_id' => $ctx['thread_id'] ?? '?',
            ]);
            return $ctx;
        }

        // Skip if photos were already sent and client hasn't insisted yet
        $yaEnviado = (array) ($ctx['ya_enviado'] ?? []);
        $photoInsistCount = (int) ($ctx['photo_insist_count'] ?? 0);
        if (in_array('fotos', $yaEnviado, true) && $photoInsistCount < 1) {
            // Photos already sent and client hasn't insisted 2+ times → skip
            return $ctx;
        }

        // Check if the AI output contains photo-promising language
        $promisePatterns = [
            '/te\s*paso\s*fotos?\b/iu',
            '/te\s*(?:las\s*)?(?:mando|env[ií]o|ense[ñn]o)\s*fotos?\b/iu',
            '/te\s*las\s*(?:mando|env[ií]o|paso)\s*otra\s*vez\b/iu',
            '/mira\s*te\s*ense[ñn]o\s*a\b/iu',
            '/te\s*las\s*env[ií]o\b/iu',
            '/te\s*las\s*mando\b/iu',
        ];

        $hasPromise = false;
        foreach ($promisePatterns as $pat) {
            if (preg_match($pat, $outputText)) {
                $hasPromise = true;
                break;
            }
        }

        if (!$hasPromise) {
            return $ctx;
        }

        // Must have girls configured
        $girlsConfig = $ctx['girls_config'] ?? [];
        if (!is_array($girlsConfig) || $girlsConfig === []) {
            return $ctx;
        }

        // Build catalog: 1 random photo per active girl
        $sentUrls = $ctx['sent_photo_urls'] ?? [];
        $lines = [];
        foreach ($girlsConfig as $girl) {
            if (!is_array($girl)) continue;
            $photos = $girl['fotos'] ?? [];
            if (!is_array($photos) || $photos === []) continue;

            // Filter already-sent URLs
            $available = array_filter($photos, static function ($p) use ($sentUrls): bool {
                $p = trim((string) $p);
                if ($p === '') return false;
                return !in_array($p, $sentUrls, true);
            });
            if ($available === []) {
                $available = array_filter($photos, static fn($p): bool => trim((string) $p) !== '');
            }
            // Exclude maps URLs
            $available = array_filter($available, function ($u): bool {
                $u = (string) $u;
                return !str_contains($u, 'maps.google')
                    && !str_contains($u, 'maps.app.goo.gl')
                    && !str_contains($u, 'goo.gl/maps');
            });
            if ($available === []) continue;

            $photo = $available[array_rand($available)];
            if (is_string($photo) && $photo !== '') {
                $lines[] = $photo;
            }
        }

        if ($lines === []) {
            return $ctx;
        }

        // Deduplicate URLs already in the output text
        $existingUrls = [];
        if (preg_match_all('/https?:\/\/[^\s<>"\')\]]+/', $outputText, $m) !== false) {
            $existingUrls = array_map('trim', $m[0]);
        }
        $lines = array_filter($lines, static function (string $line) use ($existingUrls): bool {
            foreach ($existingUrls as $existing) {
                if ($line === $existing || str_contains($existing, $line) || str_contains($line, $existing)) {
                    return false;
                }
            }
            return true;
        });

        if ($lines === []) {
            return $ctx;
        }

        $catalogBlock = implode("\n", $lines);
        $newOutput = rtrim($outputText);
        if ($newOutput !== '') {
            $newOutput .= "\n" . $catalogBlock;
        } else {
            $newOutput = $catalogBlock;
        }

        // Inject into splitted_messages
        $messages = $ctx['splitted_messages'] ?? [$outputText];
        // Add each photo URL as a follow-up message
        foreach ($lines as $line) {
            $messages[] = [
                '__split_index' => count($messages),
                '__is_first'    => false,
                'text'          => $line,
            ];
        }
        $ctx['splitted_messages'] = $messages;
        $ctx['output_text'] = $newOutput;

        $this->logger->info('Bot::injectPhotoUrls — injected catalog photos as safety net', [
            'phone'     => $ctx['from_phone'] ?? '?',
            'thread_id' => $ctx['thread_id'] ?? '?',
            'num_photos' => count($lines),
        ]);

        return $ctx;
    }

    /**
     * Resolve the inflight lock directory path.
     */
    private function inflightLockDir(): string
    {
        $dir = (string) $this->config->get('files.lock_dir', 'data/locks');
        $root = defined('WASAPBOT_ROOT') ? WASAPBOT_ROOT : dirname(__DIR__, 2);
        if (!str_starts_with($dir, '/')) {
            $dir = $root . '/' . ltrim($dir, '/');
        }
        return $dir . '/inflight';
    }

    /**
     * Pre-send re-check: if new messages arrived from the same phone
     * while we were processing (LLM call + formatting), merge them and
     * re-run the LLM pipeline with reduced humanization delays.
     *
     * This handles the "metralleta" pattern: user sends rapid-fire
     * messages that arrive during typing simulation. Instead of
     * separate, desynchronised responses, everything is processed
     * together.
     *
     * Recursive up to 3 levels, with progressively shorter delays.
     *
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    private function handleIncomingWhileProcessing(array $ctx, string $lockDir, string $fromPhone): array
    {
        $depth = (int) ($ctx['__reprocess_depth'] ?? 0);
        if ($depth >= 3) {
            return $ctx; // Max recursion depth reached
        }

        $lineLast9 = (string) ($ctx['line_last9'] ?? '');
        $pending = \WasapBot\Pipeline\InflightGate::drainPending($lockDir, $fromPhone, $lineLast9);
        if ($pending === []) {
            return $ctx; // No new messages — proceed normally
        }

        // Track re-process count to prevent cascading LLM re-runs in sendMessages
        $ctx['__pending_drained_count'] = ((int) ($ctx['__pending_drained_count'] ?? 0)) + 1;

        $this->logger->info('Bot::handleIncomingWhileProcessing — re-processing with new messages', [
            'phone'      => $fromPhone,
            'num_pending' => count($pending),
            'reprocess_depth' => $depth + 1,
        ]);

        // ── Build coalesced message ──────────────────────────────────
        $originalText = (string) ($ctx['message_text'] ?? '');
        $coalescedParts = $originalText !== '' ? [$originalText] : [];
        foreach ($pending as $p) {
            $t = (string) ($p['message_text'] ?? '');
            if ($t !== '' && $t !== $originalText) {
                $coalescedParts[] = $t;
            }
        }
        $coalescedText = implode(' | ', $coalescedParts);

        // Update context with coalesced message
        $ctx['message_text']        = $coalescedText;
        $ctx['__coalesced_text']    = $coalescedText;
        $ctx['__reprocess_depth']   = $depth + 1;
        $ctx['__is_reprocess']      = true;
        $ctx['__reprocess_pending_count'] = count($pending);

        // ── Re-run LLM pipeline steps ────────────────────────────────
        // Step 6: re-run ContextAssembler with updated message_text
        if (isset($this->processors[0]) && $this->processors[0]->name() === 'ContextAssembler') {
            $ctx = $this->processors[0]->process($ctx);
            if ($ctx === null) return $ctx;
        }

        // Step 8: re-run tone classification
        $messageText = (string) ($ctx['message_text'] ?? '');
        $toneProvider = (string) $this->config->get('global_providers.tone_classifier', 'deepseek');
        if ($toneProvider === 'deepseek') {
            $tone = $this->deepseekClient->classifyTone($messageText);
        } else {
            $tone = $this->openaiClient->classifyTone($messageText);
        }
        $ctx['sentiment'] = $tone['sentiment'] ?? 'neutral';
        $ctx['register']  = $tone['register']  ?? 'normal';
        $ctx['urgency']   = $tone['urgency']   ?? 'baja';

        // Step 9: re-run ToneBuilder
        if (isset($this->processors[1]) && $this->processors[1]->name() === 'ToneBuilder') {
            $ctx = $this->processors[1]->process($ctx);
        }

        // Step 10: re-run LLM call with coalesced message
        $systemPrompt = $this->buildSystemPrompt($ctx);
        $userMessage  = $ctx['user_message'] ?? $messageText;

        // Build fresh chat history (includes the previous messages now)
        $history = $this->buildChatHistory($ctx);

        $aiProvider = (string) ($ctx['ai_provider'] ?? 'openai');
        $aiModel    = !empty($ctx['ai_model']) ? (string) $ctx['ai_model'] : null;

        if ($aiProvider === 'deepseek') {
            $aiClient = $this->deepseekClient;
        } else {
            $aiClient = $this->openaiClient;
        }

        // ── Trim context for AI (same logic as main pipeline) ──
        $ctxForAI = $ctx;
        $selectedForAI = trim((string) ($ctx['selected_girl_name'] ?? ''));
        $wantsMore     = !empty($ctx['wants_more_girls']);
        if ($selectedForAI !== '' && !$wantsMore && !empty($ctxForAI['girls_config'])) {
            $trimmed = array_values(array_filter(
                $ctxForAI['girls_config'],
                static fn(array $g): bool =>
                    trim((string) ($g['nombre'] ?? '')) === $selectedForAI
            ));
            // Safety: if filtering removed ALL girls (name mismatch), keep original
            if ($trimmed !== []) {
                $ctxForAI['girls_config'] = $trimmed;
            }
        }

        $openaiResponse = $aiClient->chat(
            $systemPrompt,
            (string) $userMessage,
            $ctxForAI,
            $aiModel,
            $history,
        );

        $ctx['openai_raw_response'] = $openaiResponse;
        $ctx['openai_choices']      = $openaiResponse['choices'] ?? [];

        // Steps 11-14: re-run normalizers and formatters
        if (isset($this->processors[2]) && $this->processors[2]->name() === 'ResponseNormalizer') {
            $ctx = $this->processors[2]->process($ctx);
        }
        if (isset($this->processors[3]) && $this->processors[3]->name() === 'CatalogFormatter') {
            $ctx = $this->processors[3]->process($ctx);
        }
        if (isset($this->processors[4]) && $this->processors[4]->name() === 'DedupeReply') {
            $ctx = $this->processors[4]->process($ctx);
        }
        if (isset($this->processors[5]) && $this->processors[5]->name() === 'ImageSplitter') {
            $ctx = $this->processors[5]->process($ctx);
        }

        // Re-run safety nets
        $ctx = $this->injectLocationUrl($ctx);
        $ctx = $this->injectPhotoUrls($ctx);

        // ── Recursive: check again for even newer messages ────────────
        return $this->handleIncomingWhileProcessing($ctx, $lockDir, $fromPhone);
    }
}
