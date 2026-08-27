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
        private readonly ?\WasapBot\Pipeline\ConversationStateMachine $stateMachine = null,
        private readonly ?\WasapBot\SideEffects\ResponseScorer $responseScorer = null,
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
                    \WasapBot\Pipeline\InflightGate::cleanup($inflightLockDir, $fromPhone, $lineLast9);
                    return null; // ContextAssembler detected an error state
                }
            }

            // ── 6a. Score previous bot reply ──────────────────────────
            // Evaluate how effective the LAST bot reply was based on
            // what the client just sent us now. This feeds the learning
            // engine to improve the playbook over time.
            if ($this->responseScorer !== null && !empty($ctx['__is_new_conversation']) === false) {
                try {
                    $this->responseScorer->scorePreviousReply($ctx);
                } catch (\Throwable $e) {
                    // Scoring is best-effort; never block the pipeline
                    if (isset($this->logger)) {
                        $this->logger->warning('Bot::responseScorer — failed: ' . $e->getMessage());
                    }
                }
            }

            // ── 6b. IntentRouter — lightweight intent classification ──
            // Runs after ContextAssembler (needs context flags) and BEFORE
            // the LLM.  Common intents (greeting, price, location, photos,
            // goodbye, confirm) are served from templates without an LLM
            // call, cutting latency and cost for ~60% of messages.
            if (isset($this->processors[1]) && $this->processors[1]->name() === 'IntentRouter') {
                $ctx = $this->processors[1]->process($ctx);
                if ($ctx === null) {
                    \WasapBot\Pipeline\InflightGate::cleanup($inflightLockDir, $fromPhone, $lineLast9);
                    return null;
                }
            }

            // ── 6c. Conversation State Machine ─────────────────────────
            // Compute the logical conversation stage from history and
            // inject a state hint for the LLM. This helps the LLM stay
            // on track and avoid regressions (re-asking "cual te gusta?"
            // after the client already chose a girl).
            if ($this->stateMachine !== null) {
                $threadId = (string) ($ctx['thread_id'] ?? '');
                $fsmHistory = [];
                if ($threadId !== '') {
                    try {
                        $fsmHistory = $this->sessionMemory->readThread($threadId);
                        $fsmHistory = array_values(array_filter($fsmHistory, static fn(array $r): bool =>
                            empty($r['_pending'])
                        ));
                    } catch (\Throwable) {}
                }
                $ctx['__conversation_state'] = $this->stateMachine->computeState($fsmHistory, $ctx);
                $ctx['__state_hint'] = $this->stateMachine->getStateHint($ctx['__conversation_state']);

                if ($this->logger !== null) {
                    $this->logger->debug('Bot::handleWebhook — FSM state computed', [
                        'state' => $ctx['__conversation_state'],
                        'phone' => $fromPhone ?? '?',
                    ]);
                }
            }

            // ── 6c. Conversation-ended fast path ──────────────────────
            // If IntentRouter or ContextAssembler detected that the
            // conversation should end (goodbye, filler loop, dead), send
            // the farewell/emoji directly without touching the LLM.
            $skipLlm  = !empty($ctx['__skip_llm']);
            $convEnded = !empty($ctx['__conversation_ended']);

            if ($skipLlm && !empty($ctx['output_text'])) {
                $fromPhone = (string) ($ctx['from_phone'] ?? '');
                $lineLast9 = (string) ($ctx['line_last9'] ?? '');
                $inflightLockDir = $this->inflightLockDir();

                // ── Run catalog formatter if photo_action is set ─────
                if (!empty($ctx['photo_action']) && $ctx['photo_action'] !== 'none') {
                    if (isset($this->processors[4]) && $this->processors[4]->name() === 'CatalogFormatter') {
                        $ctx = $this->processors[4]->process($ctx);
                    }
                }

                // ── Bot-mode re-check: stop if bot was turned off during pipeline ──
                if (!$this->isRunning()) {
                    $this->logger->info('Bot::handleWebhook — bot stopped before send (IntentRouter)', [
                        'phone' => $fromPhone,
                    ]);
                    $ctx['_cancelled'] = true;
                    $ctx['_send_ok']   = false;
                    \WasapBot\Pipeline\InflightGate::cleanup($inflightLockDir, $fromPhone, $lineLast9);
                    return $ctx;
                }

                $ctx['_send_ok'] = true;
                $this->sendMessages($ctx, [$ctx['output_text'] ?? '']);

                if (!empty($ctx['_send_ok']) && empty($ctx['_cancelled'])) {
                    $this->sessionMemory->appendMessage(
                        (string) ($ctx['thread_id'] ?? ''),
                        $fromPhone,
                        (string) ($ctx['message_text'] ?? ''),
                        (string) ($ctx['output_text'] ?? ''),
                        $ctx,
                    );
                }

                \WasapBot\Pipeline\InflightGate::cleanup($inflightLockDir, $fromPhone, $lineLast9);

                $this->logger->info('Bot::handleWebhook — pipeline completed (intent router fast path)', [
                    'phone'     => $fromPhone,
                    'thread_id' => $ctx['thread_id'] ?? '?',
                    'intent'    => $ctx['__intent'] ?? '?',
                ]);

                return $ctx;
            }

            // ── 7. Client profile context ─────────────────────────────
            if ($this->clientProfileService !== null) {
                $ctx['client_profile_hint'] = $this->clientProfileService->getClientContextHint($fromPhone);
            }

            // ── 8. Audio auto-reply shortcut ─────────────────────────
            $isAudio = (int) ($ctx['is_audio_i'] ?? 0);
            $transcription = trim((string) ($ctx['transcription'] ?? ''));
            // Si hay transcripción, el bot SÍ entiende el audio: dejamos que el LLM responda.
            if ($isAudio === 1 && $transcription === '') {
                $variants = (array) $this->config->get('message_variants.audio_auto_reply', []);
                $audioReply = $variants !== [] ? $variants[array_rand($variants)] : 'no puedo escuchar audios amor, me lo escribes mejor?';

                $ctx['output_text']   = $audioReply;
                $ctx['lead_detected'] = false;

                $this->logger->info('Bot::handleWebhook — audio auto-reply sent', [
                    'phone' => $fromPhone,
                ]);

                // ── Bot-mode re-check: stop if bot was turned off during pipeline ──
                if (!$this->isRunning()) {
                    $this->logger->info('Bot::handleWebhook — bot stopped before send (audio)', [
                        'phone' => $fromPhone,
                    ]);
                    $ctx['_cancelled'] = true;
                    $ctx['_send_ok']   = false;
                    \WasapBot\Pipeline\InflightGate::cleanup($inflightLockDir, $fromPhone, $lineLast9);
                    return $ctx;
                }

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

                \WasapBot\Pipeline\InflightGate::cleanup($inflightLockDir, $fromPhone, $lineLast9);
                return $ctx;
            }

            // ── 8b. First-contact greeting shortcut ──────────────────
            // New conversation, no speaker girl yet, not audio: send a
            // minimal greeting and WAIT. Do NOT call the LLM, do NOT
            // send catalog, do NOT offer girls. Deterministic gate.
            //
            // NOVA: también se activa cuando __is_ad_intro=true
            // (cliente viene de anuncio concreto, NO debe ver catálogo).
            $isNewConversation = !empty($ctx['__is_new_conversation']);
            $isAdIntro         = !empty($ctx['__is_ad_intro']);
            $speakerMode = (string) ($ctx['speaker_mode'] ?? '');
            if ($isNewConversation && ($speakerMode === 'encargada' || $isAdIntro)) {
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

                // ── Bot-mode re-check: stop if bot was turned off during pipeline ──
                if (!$this->isRunning()) {
                    $this->logger->info('Bot::handleWebhook — bot stopped before send (first-contact)', [
                        'phone' => $fromPhone,
                    ]);
                    $ctx['_cancelled'] = true;
                    $ctx['_send_ok']   = false;
                    \WasapBot\Pipeline\InflightGate::cleanup($inflightLockDir, $fromPhone, $lineLast9);
                    return $ctx;
                }

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
            if (isset($this->processors[2]) && $this->processors[2]->name() === 'ToneBuilder') {
                $ctx = $this->processors[2]->process($ctx);
            }

            // ── 10. Call AI chat completion (OpenAI or DeepSeek) ──────
            $systemPrompt = $this->buildSystemPrompt($ctx);
            $userMessage  = $ctx['__llm_user_message'] ?? $ctx['user_message'] ?? $messageText;

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
            if (isset($this->processors[3]) && $this->processors[3]->name() === 'ResponseNormalizer') {
                $ctx = $this->processors[3]->process($ctx);
            } elseif (!empty($openaiResponse['choices'])) {
                $ctx['output_text'] = $openaiResponse['choices'][0]['message']['content'] ?? '';
            }

            // ── 11b. POST-LLM: apply LLM-derived semantic fields ───────
            // The LLM now handles conversation understanding (girl matching,
            // conversation health, buying intent, etc.). These fields override
            // or supplement the regex-derived defaults from ContextAssembler.
            $this->applyLlmSemanticFields($ctx);

            // ── 11c. Fallback: if LLM returned empty, send contingency text ──
            if (trim((string) ($ctx['output_text'] ?? '')) === '') {
                // ── DIAGNÓSTICO: loggear estado del raw response ──
                $raw = $ctx['openai_raw_response'] ?? null;
                $this->logger->warning('Bot::handleWebhook — output_text empty after ResponseNormalizer', [
                    'phone'         => $fromPhone,
                    'thread_id'     => $ctx['thread_id'] ?? '?',
                    'raw_type'      => gettype($raw),
                    'raw_keys'      => is_array($raw) ? implode(',', array_keys($raw)) : 'N/A',
                    'raw_count'     => is_array($raw) ? count($raw) : 'N/A',
                    'has_user_vr'   => is_array($raw) && array_key_exists('user_visible_reply', $raw) ? 'yes' : 'no',
                    'user_vr_val'   => is_array($raw) ? json_encode($raw['user_visible_reply'] ?? null, JSON_UNESCAPED_UNICODE) : 'N/A',
                    'raw_json_head' => is_string($raw) ? mb_substr($raw, 0, 120) : (is_array($raw) ? 'array(' . count($raw) . ')' : 'N/A'),
                ]);

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

            // ── 11d. POST-AI guard: strip "todas comparten casita" ───
            // The LLM sometimes mentions that all girls share the same
            // house even when the client didn't ask about independence.
            // This guard strips those phrases unless the client explicitly
            // asked whether girls are independent or share a location.
            $userMessageText = (string) ($ctx['message_text'] ?? '');
            $askedAboutIndependence = (bool) preg_match(
                '/\b(independiente|particular|sola|compart[eí]s|cada\s+una|vuestra\s+casa|tu\s+casa|piso\s+compartido|eres\s+tu\s+sola)/iu',
                $userMessageText
            );
            if (!$askedAboutIndependence) {
                $outputText = (string) ($ctx['output_text'] ?? '');
                if ($outputText !== '') {
                    $original = $outputText;
                    $outputText = preg_replace(
                        '/tod[ao]s?\s+compart[ei]n\s+casita\s+en\s+/iu',
                        '',
                        $outputText
                    );
                    $outputText = preg_replace(
                        '/tod[ao]s?\s+est[áa]n?\s+en\s+la\s+misma\s+casa[^.]*\.?\s*/iu',
                        '',
                        $outputText
                    );
                    $outputText = preg_replace(
                        '/compartimos\s+casa[^.]*\.?\s*/iu',
                        '',
                        $outputText
                    );
                    $outputText = preg_replace(
                        '/estamos\s+tod[ao]s\s+en\s+el\s+mismo\s+piso[^.]*\.?\s*/iu',
                        '',
                        $outputText
                    );
                    $outputText = trim(preg_replace('/\s{2,}/', ' ', $outputText));
                    if ($outputText !== $original && $outputText !== '') {
                        $ctx['output_text'] = $outputText;
                        if (isset($this->logger)) {
                            $this->logger->info('Bot::POST-AI guard — stripped "todas comparten casita"', [
                                'phone' => $fromPhone ?? '?',
                            ]);
                        }
                    }
                }
            }

            // ── 12. Catalog formatter (CatalogFormatter) ─────────────
            $textBeforeCatalog = $ctx['output_text'] ?? '';
            if (isset($this->processors[4]) && $this->processors[4]->name() === 'CatalogFormatter') {
                $ctx = $this->processors[4]->process($ctx);
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
            if (isset($this->processors[5]) && $this->processors[5]->name() === 'DedupeReply') {
                $ctx = $this->processors[5]->process($ctx);
            }

            // ── 14. Image splitter (ImageSplitter) ───────────────────
            if (isset($this->processors[6]) && $this->processors[6]->name() === 'ImageSplitter') {
                $ctx = $this->processors[6]->process($ctx);
            }

            // ── 14b. POST-AI: inyectar location_url si el AI dice que envía mapa pero no incluye URL ──
            $ctx = $this->injectLocationUrl($ctx);

            // ── 14c. POST-AI: inyectar fotos si el AI promete enviarlas pero no llegaron ──
            $ctx = $this->injectPhotoUrls($ctx);

            // ── 14c2. POST-AI: anti-catalog gate ─────────────────────
            // Si ya se ha mostrado catálogo demasiadas veces o el cliente
            // rechazó las fotos, eliminar cualquier URL de compartir.site
            // que pudiera haber quedado en output_text.
            $catalogCount   = (int) ($ctx['catalog_count'] ?? 0);
            $photoRejected  = !empty($ctx['photo_rejected']);
            $photoAction    = (string) ($ctx['photo_action'] ?? 'none');

            if (($catalogCount >= 2 || $photoRejected) && $photoAction === 'catalog') {
                // Force strip photo URLs and reset photo_action
                $outputText = (string) ($ctx['output_text'] ?? '');
                $cleaned = preg_replace(
                    '/(?:https?:\/\/)?compartir\.site\/[a-zA-Z0-9]+\/?\s*/i',
                    '',
                    $outputText
                );
                $cleaned = trim(preg_replace('/\n{3,}/', "\n\n", $cleaned));
                if ($cleaned !== '' && $cleaned !== $outputText) {
                    $ctx['output_text']   = $cleaned;
                    $ctx['photo_action']  = 'none';
                    $ctx['splitted_messages'] = [$cleaned];
                    $this->logger->info('Bot::anti-catalog gate — stripped catalog photos', [
                        'phone'          => $fromPhone,
                        'thread_id'      => $ctx['thread_id'] ?? '?',
                        'catalog_count'  => $catalogCount,
                        'photo_rejected' => $photoRejected,
                    ]);
                }
            }

            $messages = $ctx['splitted_messages'] ?? [$ctx['output_text'] ?? ''];

            // ── 14d. Pre-send: check for messages that arrived during processing ──
            $ctx = $this->handleIncomingWhileProcessing($ctx, $inflightLockDir, $fromPhone);
            $messages = $ctx['splitted_messages'] ?? [$ctx['output_text'] ?? ''];

            // ── 15. Send via WAHA with humanization ──────────────────
            // ── Bot-mode re-check: stop if bot was turned off during pipeline ──
            if (!$this->isRunning()) {
                $this->logger->info('Bot::handleWebhook — bot stopped before send (LLM path)', [
                    'phone' => $fromPhone,
                ]);
                $ctx['_cancelled'] = true;
                $ctx['_send_ok']   = false;
                \WasapBot\Pipeline\InflightGate::cleanup($inflightLockDir, $fromPhone, $lineLast9);
                return $ctx;
            }

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
        return mb_strtolower($content) !== 'stop';
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
     * The $relative path may optionally include a leading "data/" prefix
     * (e.g. "data/.bot_mode" from config). This prefix is automatically
     * stripped to prevent double-nesting like data/users/{id}/data/....
     *
     * @param string $rootDir   Project root (WASAPBOT_ROOT)
     * @param int    $userId    User ID (0 = legacy / default)
     * @param string $relative  Relative path, optionally with "data/" prefix
     * @return string Absolute path
     */
    public static function resolveUserDataPath(string $rootDir, int $userId, string $relative): string
    {
        // ── Strip leading "data/" prefix from relative path ────────────────
        // Config values use data-prefixed paths (e.g. "data/.bot_mode",
        // "data/session_memory.ndjson") because they are relative to the
        // project root. But this function already prefixes with either
        // data/users/{id}/ or data/, so we must strip the extra data/
        // to prevent double-nesting (data/users/{id}/data/.bot_mode).
        $relative = ltrim($relative, '/');
        if (str_starts_with($relative, 'data/')) {
            $relative = substr($relative, 5); // Remove "data/" prefix
        }

        // For non-admin users (ID > 1), ALWAYS use user-specific path
        // to prevent data mixing between users
        if ($userId > 1) {
            $userDataDir = rtrim($rootDir, '/') . '/data/users/' . $userId;
            if (!is_dir($userDataDir)) {
                @mkdir($userDataDir, 0700, true);
            }
            // If the path is absolute, extract only the basename to prevent
            // recursive nesting: each bootstrap() call would otherwise
            // prepend the user data dir again, creating paths like
            // .../users/9/var/www/.../users/9/var/www/... (ADR-011)
            if (str_starts_with($relative, '/')) {
                $relative = basename($relative);
            }
            $fullPath = $userDataDir . '/' . ltrim($relative, '/');

            // Ensure parent directory exists (e.g., data/ subdir)
            // so that downstream code (webhook, API) can write immediately.
            // Ownership is set by whoever runs bootstrap (www-data for web).
            $parentDir = dirname($fullPath);
            if (!is_dir($parentDir)) {
                @mkdir($parentDir, 0700, true);
            }
            return $fullPath;
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
     * A tenant directory is always created for userId > 0. It is intentionally
     * not initialized from the root config.local.json; Config overlays only
     * approved central runtime settings when it is constructed.
     */
    public static function resolveUserConfigDir(string $rootDir, int $userId): string
    {
        if ($userId > 0) {
            $userDir = rtrim($rootDir, '/') . '/data/users/' . $userId;
            if (!is_dir($userDir)) {
                @mkdir($userDir, 0700, true);
            }
            if (!is_dir($userDir)) {
                throw new \RuntimeException('Cannot initialize tenant data directory.');
            }
            return $userDir;
        }
        return $rootDir;
    }

    /**
     * Ensure a user's runtime config has routing.lines populated.
     *
     * RoutingGate reads routing.lines from config.local.json, while lines are
     * managed per-user in data/users/{id}/lines.json. When the config's routing
     * is empty (e.g. initialized from the dist template), seed it from the
     * user's lines.json so the bot can process incoming messages. Admin
     * (userId === 1) additionally falls back to the root config's routing.lines
     * when no per-user lines.json exists (legacy/panel-managed casa lines).
     *
     * @param string                  $rootDir Project root (WASAPBOT_ROOT)
     * @param int                     $userId  User ID (0 = legacy/default)
     * @param ConfigInterface         $config  Runtime config to mutate
     */
    public static function seedRoutingLines(string $rootDir, int $userId, ConfigInterface $config): void
    {
        if ($userId < 1) {
            return;
        }

        $routingLines = $config->get('routing.lines', []);
        if (is_array($routingLines) && $routingLines !== []) {
            return; // Already configured — do not overwrite tenant edits.
        }

        $injected = [];
        $linesJsonPath = self::resolveUserDataPath($rootDir, $userId, 'lines.json');
        if (file_exists($linesJsonPath)) {
            $linesData = @json_decode((string) @file_get_contents($linesJsonPath), true);
            if (is_array($linesData) && $linesData !== []) {
                foreach ($linesData as $line) {
                    if (!is_array($line)) {
                        continue;
                    }
                    $injected[] = [
                        'last9'       => (string) ($line['last9'] ?? ''),
                        'port'        => (int) ($line['port'] ?? 0),
                        'label'       => (string) ($line['label'] ?? ''),
                        'enabled'     => true,
                        'ai_provider' => (string) ($line['ai_provider'] ?? 'openai'),
                        'ai_model'    => $line['ai_model'] ?? null,
                    ];
                }
            }
        }

        // Admin (user 1): legacy/panel-managed lines live in the root config.
        // Fall back to them when there is no per-user lines.json (or it is empty).
        if ($injected === [] && $userId === 1) {
            $rootConfig = new \WasapBot\Core\Config($rootDir);
            $rootLines = $rootConfig->get('routing.lines', []);
            if (is_array($rootLines)) {
                $injected = $rootLines;
            }
        }

        if ($injected !== []) {
            $config->set('routing.lines', $injected);
            // Persist so panel.php routing tab shows the lines too
            try {
                $config->save();
            } catch (\Throwable) {
                // Best-effort: in-memory set() is sufficient for the current request
            }
        }
    }

    public static function bootstrap(string $rootDir, int $userId = 0): array
    {
        // ── Resolve config directory for this user ───────────────────
        $configDir = self::resolveUserConfigDir($rootDir, $userId);

        // ── Core ─────────────────────────────────────────────────────
        $config = new \WasapBot\Core\Config($configDir, $userId > 0 ? $rootDir : null);

        // ── Override relative data paths to be user-specific ─────────
        if ($userId > 0) {
            $fileKeys = [
                'files.session_memory', 'files.leads', 'files.reminders',
                'files.playbook', 'files.wa_raw_payload', 'files.bot_log',
                'files.paused_threads',
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

        // ── Ensure routing.lines is populated for the user's runtime ──
        // api/lines.php manages WAHA lines in data/users/{id}/lines.json, but
        // RoutingGate reads from config.local.json → routing.lines. When routing
        // is empty (config initialized from dist template), seed it from the
        // user's lines.json. Admin (user 1) additionally falls back to the root
        // config's routing.lines when there is no per-user lines.json yet, so
        // the owner's casa lines keep working.
        self::seedRoutingLines($rootDir, $userId, $config);

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

        // ── Conversation State Machine ────────────────────────────────
        $stateMachine = new \WasapBot\Pipeline\ConversationStateMachine($config, $logger);

        // ── Response Scorer ───────────────────────────────────────────
        $responseScorer = new \WasapBot\SideEffects\ResponseScorer($config, $logger, $sessionMemory);

        // ── Pipeline: Input gates ────────────────────────────────────
        $pauseGate = new \WasapBot\Pipeline\PauseGate($config);
        $inputGates = [
            new \WasapBot\Pipeline\BotModeGate($config),
            new \WasapBot\Pipeline\RoutingGate($config),
            new \WasapBot\Pipeline\DedupGate($config),
            new \WasapBot\Pipeline\Coalescer($config),
            new \WasapBot\Pipeline\MessageExtractor($config),
            $pauseGate,
            new \WasapBot\Pipeline\InflightGate($config),
        ];

        // ── Pipeline: Processors ─────────────────────────────────────
        $processors = [
            new \WasapBot\Pipeline\ContextAssembler($config, $logger, $memory, $sessionMemory),
            new \WasapBot\Pipeline\IntentRouter($config, $logger),
            new \WasapBot\Pipeline\ToneBuilder($config),
            new \WasapBot\Pipeline\ResponseNormalizer(),
            new \WasapBot\Pipeline\CatalogFormatter($config),
            new \WasapBot\Pipeline\DedupeReply($config),
            new \WasapBot\Pipeline\ImageSplitter($config),
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
            stateMachine:          $stateMachine,
            responseScorer:        $responseScorer,
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
                // Clean playbook: remove English meta-analysis written by
                // the LLM that generated it, keeping only Spanish pattern content.
                // The spanglish confuses the chat model and degrades Spanish quality.
                $playbookContent = $this->cleanPlaybook($playbookContent);
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

        // ── SEMANTIC FIELDS: the LLM now handles conversation understanding ──
        // These fields replace regex-based dead detection, girl matching,
        // intent classification, and filler detection. The LLM sees the full
        // conversation history and the list of active girls, so it can make
        // better decisions than hardcoded patterns.
        $base .= "\n\n### CAMPOS SEMÁNTICOS ADICIONALES (añadir al JSON de respuesta)\n"
               . "Además de los campos obligatorios, incluye estos campos en tu JSON:\n\n"
               . "- mentioned_girl: string | null. Nombre EXACTO de la chica mencionada en el mensaje del cliente.\n"
               . "  Usa los nombres de CHICAS ACTIVAS que ves en el contexto. Si el cliente dice 'la rubia',\n"
               . "  'ella', 'esa', o menciona un nombre con typo (ej: 'carima' por 'carina'), identifica a qué\n"
               . "  chica se refiere. Si no menciona ninguna, pon null.\n\n"
               . "- girl_selection_intent: boolean. true si el cliente está eligiendo explícitamente a una chica\n"
               . "  para el servicio (ej: 'me quedo con sandra', 'prefiero a la rubia', 'pues carina',\n"
               . "  'la sandra mejor'). false si solo menciona un nombre de pasada o no menciona ninguna.\n\n"
                . "- conversation_health: 'alive' | 'fading' | 'dead'. Evalúa si la conversación sigue viva.\n"
                . "  'alive': el cliente muestra interés genuino. 'fading': el cliente está perdiendo interés\n"
                . "  (muchos monosílabos, respuestas cortas sin preguntas). 'dead': el cliente claramente\n"
                . "  no va a convertir (se despidió, es hostil, solo hace preguntas sin intención real).\n"
                . "  Si el cliente acaba de escribir un mensaje nuevo después de días de silencio,\n"
                . "  trata la conversación como 'alive' — es un retorno, no una conversación muerta.\n"
                . "  ⚠️ IMPORTANTE: Si el cliente pide fotos (normales o normales), precios, ubicación,\n"
                . "  o cualquier información del servicio, la conversación NO está 'fading' ni 'dead'.\n"
                . "  Pedir fotos/INFO es interés real. 'fading' solo aplica cuando el cliente lleva\n"
                . "  varios mensajes diciendo solo 'vale', 'ok', 'gracias', 'jeje' o monosílabos\n"
                . "  SIN ninguna pregunta ni petición de información.\n\n"
               . "- tarifa_elegida: null | '40' | '50' | '100'. Si el cliente ha elegido una tarifa,\n"
               . "  extráela. '40' para rapidito/10min, '50' para media hora, '100' para 1h o más.\n\n"
               . "- buying_intent: 'none' | 'exploring' | 'strong'. Intención de compra del cliente.\n"
               . "  'strong': dice que viene, da ETA, pide ubicación para venir ya.\n"
               . "  'exploring': pregunta precios, servicios, fotos pero sin confirmar.\n"
               . "  'none': solo saluda, smalltalk, o claramente no va a comprar.\n\n"
               . "- wants_more_girls: boolean. true si el cliente pide ver más chicas o el catálogo completo.\n\n"
               . "- hot_curious: boolean. true si el mensaje tiene contenido sexual/picante (no confundir\n"
               . "  'fotos' normales con contenido sexual — 'tienes fotos?' NO es picante,\n"
               . "  'que tetas mas buenas' o 'me pones cachondo' SÍ lo es).\n\n";

        return $base;
    }

    /**
     * Remove English meta-analysis lines from the playbook.
     *
     * The playbook is auto-generated by an LLM that writes analysis in English
     * interspersed with Spanish patterns. The English confuses the chat model
     * and degrades Spanish output quality.
     *
     * Strategy: strip lines that are predominantly English analysis while
     * keeping Spanish pattern descriptions and section headers.
     */
    private function cleanPlaybook(string $content): string
    {
        if (trim($content) === '') {
            return '';
        }

        $lines = explode("\n", $content);
        $cleaned = [];
        $englishBlock = false;

        // Keywords that signal English meta-analysis (not Spanish patterns)
        $englishAnalysisMarkers = [
            '/^We need to/',
            "/^Let'?s extract/",
            '/^First, /',
            '/^Now, /',
            '/^The lead was /',
            '/^But we can /',
            '/^Overall, /',
            '/^However, /',
            '/^This is /',
            '/^It suggests/',
            '/^I\'ll /',
            '/^So I\'ll /',
            '/^Not much\.$/',
            '/^I note /',
            '/^From the /',
            '/^The key /',
            '/^The bot\'s /',
            '/^This /',
            '/^The human /',
            '/^Also, /',
            '/^From /',
            '/^Specifically, /',
            '/^The client /',
            '/^Ghosting often /',
            '/^The human uses /',
            '/^But the /',
            '/^Another /',
            '/^The point of/',
            '/^This suggests/',
            '/^This is messy/',
            '/^Not much/',
            '/^So the/',
            '/^That shows/',
            '/^That creates/',
            '/^This matches/',
            '/^So new insight/',
            '/^The playbook already/',
            '/^The mareo/',
            '/^The tone is/',
            '/^The human also/',
            '/^The human did/',
            '/^Not a strong/',
            '/^The inflexion/',
            '/^So that/',
            '/^The success/',
        ];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Always keep blank lines and markdown headers
            if ($trimmed === '') {
                $cleaned[] = $line;
                $englishBlock = false;
                continue;
            }

            // Always keep markdown headers (##, ###)
            if (str_starts_with($trimmed, '#')) {
                $cleaned[] = $line;
                $englishBlock = false;
                continue;
            }

            // Keep metadata header lines (start with >)
            if (str_starts_with($trimmed, '>')) {
                $cleaned[] = $line;
                continue;
            }

            // Keep separator lines (---)
            if ($trimmed === '---') {
                $cleaned[] = $line;
                continue;
            }

            // Detect English analysis lines
            $isEnglishAnalysis = false;
            foreach ($englishAnalysisMarkers as $pattern) {
                if (preg_match($pattern, $trimmed)) {
                    $isEnglishAnalysis = true;
                    break;
                }
            }

            if ($isEnglishAnalysis) {
                $englishBlock = true;
                // Skip this line (English meta-analysis)
                continue;
            }

            // If we're in an English paragraph, check if it's ending
            if ($englishBlock) {
                // Check if this line has Spanish content
                $hasSpanish = (bool) preg_match('/[áéíóúñü¡¿]/iu', $trimmed);
                if (!$hasSpanish) {
                    // Check for indented continuation or list items without Spanish
                    if (preg_match('/^\s+/', $line) || str_starts_with($trimmed, '-')) {
                        continue; // continuation of English block
                    }
                }
                // Spanish content found → exit English block
                $englishBlock = false;
            }

            // Keep the line
            $cleaned[] = $line;
        }

        return implode("\n", $cleaned);
    }

    /**
     * Apply LLM-derived semantic fields to the pipeline context AFTER the LLM
     * has responded. These fields replace or supplement regex-based decisions
     * made earlier in ContextAssembler.
     *
     * The LLM sees the full conversation history and the list of active girls,
     * so its judgments about girl matching, conversation health, buying intent,
     * etc. are more accurate than regex patterns.
     *
     * @param array<string, mixed> $ctx  Pipeline context (modified in-place)
     */
    private function applyLlmSemanticFields(array &$ctx): void
    {
        // ── mentioned_girl: override selected_girl if LLM identified a girl ──
        $llmGirl = (string) ($ctx['__llm_mentioned_girl'] ?? '');
        if ($llmGirl !== '') {
            // Find the girl in girls_config to get her ID
            $girlsConfig = (array) ($ctx['girls_config'] ?? []);
            $girlId = '';
            foreach ($girlsConfig as $g) {
                $name = trim((string) ($g['nombre'] ?? ''));
                if ($name !== '' && mb_strtolower($name, 'UTF-8') === mb_strtolower($llmGirl, 'UTF-8')) {
                    $girlId = (string) ($g['id'] ?? '');
                    break;
                }
            }
            // If LLM identified a girl AND sets selection intent, update selected_girl
            $hasSelectionIntent = !empty($ctx['__llm_girl_selection_intent']);
            $currentSelected = (string) ($ctx['selected_girl_name'] ?? '');

            if ($hasSelectionIntent && mb_strtolower($llmGirl, 'UTF-8') !== mb_strtolower($currentSelected, 'UTF-8')) {
                $ctx['selected_girl_name'] = $llmGirl;
                if ($girlId !== '') {
                    $ctx['selected_girl_id'] = $girlId;
                }
                // If no speaker yet, this becomes the speaker too
                if (empty($ctx['speaker_girl_name'])) {
                    $ctx['speaker_girl_name'] = $llmGirl;
                    if ($girlId !== '') {
                        $ctx['speaker_girl_id'] = $girlId;
                    }
                    $ctx['speaker_mode'] = 'chica';
                }
                if (isset($this->logger)) {
                    $this->logger->info('Bot::applyLlmSemanticFields — LLM identified girl selection', [
                        'girl'  => $llmGirl,
                        'phone' => $ctx['from_phone'] ?? '?',
                    ]);
                }
            } elseif ($currentSelected === '' && $girlId !== '') {
                // No current selection → pick up the LLM's girl mention
                $ctx['selected_girl_name'] = $llmGirl;
                $ctx['selected_girl_id']   = $girlId;
                if (empty($ctx['speaker_girl_name'])) {
                    $ctx['speaker_girl_name'] = $llmGirl;
                    $ctx['speaker_girl_id']   = $girlId;
                    $ctx['speaker_mode'] = 'chica';
                }
            }
        }

        // ── conversation_health: handle dead/fading conversations ────
        $health = (string) ($ctx['__llm_conversation_health'] ?? '');
        if ($health === 'dead') {
            // LLM says conversation is dead → set ended flag
            $ctx['__conversation_ended'] = true;
            $ctx['lead_detected'] = false; // Dead conversation can't be a lead
            if (isset($this->logger)) {
                $this->logger->info('Bot::applyLlmSemanticFields — LLM marked conversation as dead', [
                    'phone'     => $ctx['from_phone'] ?? '?',
                    'thread_id' => $ctx['thread_id'] ?? '?',
                ]);
            }
        } elseif ($health === 'fading') {
            // Conversation is fading → let the LLM's reply handle it naturally
            // The LLM already wrote a short/closing reply in user_visible_reply
            if (isset($this->logger)) {
                $this->logger->info('Bot::applyLlmSemanticFields — LLM marked conversation as fading', [
                    'phone'     => $ctx['from_phone'] ?? '?',
                    'thread_id' => $ctx['thread_id'] ?? '?',
                ]);
            }
        }

        // ── tarifa_elegida: use LLM's price extraction ───────────────
        $llmTarifa = (string) ($ctx['__llm_tarifa_elegida'] ?? '');
        if ($llmTarifa !== '' && empty($ctx['tarifa_elegida'])) {
            $ctx['tarifa_elegida'] = $llmTarifa;
        }

        // ── buying_intent: override regex-based interes_fuerte ───────
        $llmIntent = (string) ($ctx['__llm_buying_intent'] ?? '');
        if ($llmIntent === 'strong') {
            $ctx['interes_fuerte'] = true;
        }

        // ── hot_curious: use LLM's judgment instead of word list ─────
        if (isset($ctx['__llm_hot_curious'])) {
            $ctx['hot_curious_chat_current'] = (bool) $ctx['__llm_hot_curious'];
        }
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
        $compressAfter = (int) $this->config->get('memory.compress_after_turns', 10);
        $now           = time();

        $recent = [];
        $lastTs  = 0;
        foreach ($rawHistory as $rec) {
            $ts = strtotime((string) ($rec['ts'] ?? ''));
            if ($ts !== false && ($now - $ts) <= $recentWindowH * 3600) {
                $recent[] = $rec;
                $lastTs = max($lastTs, $ts);
            }
        }

        // If no recent history, fallback to last N of all history
        if ($recent === []) {
            $recent = array_slice($rawHistory, -$maxTurns);
        } else {
            $recent = array_slice($recent, -$maxTurns);
        }

        // ── NOVA P4: History compression ────────────────────────────
        // When conversation exceeds compressAfter turns, summarize older
        // messages into a single compressed context block so the LLM
        // keeps focus on the most recent exchange without losing earlier
        // decisions (which girl was chosen, prices given, maps sent, etc.)
        $historySummary = '';
        if (count($recent) > $compressAfter) {
            $olderTurns = array_slice($recent, 0, count($recent) - $compressAfter);
            $recent     = array_slice($recent, -$compressAfter);
            $historySummary = $this->summarizeHistoryTurns($olderTurns);
        }

        // Build alternating user/assistant messages
        $history = [];

        // Prepend summary as a synthetic system context if available
        if ($historySummary !== '') {
            $history[] = ['role' => 'user', 'content' => '[RESUMEN ANTERIOR] ' . $historySummary];
            $history[] = ['role' => 'assistant', 'content' => '✓'];
        }

        // ── Confusion pattern: detect when bot said "no entiendo" ────
        // If the LLM sees its own confusion in history, it replicates
        // the pattern. Replace with neutral "ok" to break the loop.
        $confusionRegex = '/\b(?:no\s+(?:entiendo|entend[ií]|te\s+entiendo|te\s+entend[ií]|te\s+he\s+entendido|s[eé]\b|se\b|se\s+que|tengo\s+ni\s+idea)|'
            . 'eso\s+no\s+(?:es\s+lo\s+m[ií]o|te\s+lo\s+s[eé])|'
            . 'de\s+eso\s+no\s+(?:entiendo|entend[ií]|s[eé]|tengo\s+ni\s+idea))\b/iu';

        foreach ($recent as $rec) {
            $userMsg = (string) ($rec['user_msg'] ?? '');
            $botMsg  = (string) ($rec['bot_reply'] ?? '');

            if ($userMsg !== '') {
                $history[] = ['role' => 'user', 'content' => $userMsg];
            }
            if ($botMsg !== '') {
                // Filter out confusion patterns from history — prevents
                // the LLM from learning and repeating "no entiendo"
                if (preg_match($confusionRegex, $botMsg)) {
                    $history[] = ['role' => 'assistant', 'content' => 'ok'];
                } else {
                    $history[] = ['role' => 'assistant', 'content' => $botMsg];
                }
            }
        }

        return $history;
    }

    /**
     * Summarize older conversation turns into a compact text block.
     *
     * Extracts key decisions and state changes so the LLM doesn't lose
     * context when older messages are compressed out of the chat window.
     *
     * @param list<array<string, mixed>> $turns
     */
    private function summarizeHistoryTurns(array $turns): string
    {
        if ($turns === []) return '';

        $firstTs = strtotime((string) ($turns[0]['ts'] ?? ''));
        $lastTs  = strtotime((string) ($turns[count($turns) - 1]['ts'] ?? ''));
        $durationMin = ($firstTs && $lastTs) ? round(($lastTs - $firstTs) / 60) : 0;

        $parts = [];
        if ($durationMin > 0) {
            $parts[] = "conversación de {$durationMin} minutos";
        }
        $parts[] = count($turns) . ' mensajes';

        // Find key state decisions in the summary window
        $lastSpeaker  = '';
        $lastSelected = '';
        $speakerMode  = '';
        $sentPhotos   = false;
        $sentPrices   = false;
        $sentMaps     = false;
        $gaveEta      = false;
        $lastUserQuestion = '';  // última pregunta sustantiva del cliente

        foreach ($turns as $rec) {
            $sn = trim((string) ($rec['speaker_girl_name'] ?? ''));
            $sel = trim((string) ($rec['selected_girl_name'] ?? ''));
            $sm = (string) ($rec['speaker_mode'] ?? '');
            if ($sn !== '') $lastSpeaker = $sn;
            if ($sel !== '') $lastSelected = $sel;
            if ($sm !== '') $speakerMode = $sm;

            $ya = (array) ($rec['ya_enviado'] ?? []);
            if (in_array('fotos', $ya, true)) $sentPhotos = true;
            if (in_array('precios', $ya, true)) $sentPrices = true;
            if (in_array('ubicacion', $ya, true) || in_array('ubicacion_precisa', $ya, true)) $sentMaps = true;

            if (!empty($rec['eta_from_user_flag'])) $gaveEta = true;

            // Track last substantive question from user (not filler)
            $um = trim((string) ($rec['user_msg'] ?? ''));
            $umLen = mb_strlen($um);
            if ($umLen > 10 && !preg_match('/^(ok|vale|oka|oki|vle|okey|gracias|genial|dime|dimelo|si|no|jj|jeje|mmm|ah|ya)[\s!😊😘😏🔥]*$/iu', $um)) {
                $lastUserQuestion = $umLen > 80 ? (mb_substr($um, 0, 80) . '…') : $um;
            }
        }

        if ($lastSpeaker !== '') {
            $modeLabel = ($speakerMode === 'chica') ? ' (eres ella)' : ' (encargada)';
            $parts[] = "chica que habla: {$lastSpeaker}{$modeLabel}";
        }
        if ($lastSelected !== '' && $lastSelected !== $lastSpeaker) {
            $parts[] = "cliente eligió a: {$lastSelected}";
        } elseif ($lastSelected !== '') {
            $parts[] = "cliente eligió a: {$lastSelected}";
        }

        $sent = [];
        if ($sentPhotos) $sent[] = 'fotos';
        if ($sentPrices) $sent[] = 'precios';
        if ($sentMaps)   $sent[] = 'mapa';
        if ($sent !== []) $parts[] = 'enviado: ' . implode(', ', $sent);
        if ($gaveEta) $parts[] = 'cliente dio ETA';

        // Include last substantive user question so the LLM keeps the thread
        if ($lastUserQuestion !== '') {
            $parts[] = "última pregunta del cliente: \"{$lastUserQuestion}\"";
        }

        return implode(' | ', $parts) . '.';
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

    /**
     * Cliente EvolutionApi (compartido con el CRM) para líneas en transport=evolution.
     */
    private function evoApiClient(array $ctx): ?\EvolutionApi
    {
        static $clients = [];
        $instance = (string) ($ctx['evo_instance'] ?? '');
        if (isset($clients[$instance])) {
            return $clients[$instance];
        }
        $evoFile = dirname(__DIR__, 2) . '/data/evolution_config.json';
        if (!is_file($evoFile)) {
            return null;
        }
        $cfg = json_decode((string) file_get_contents($evoFile), true);
        if (!is_array($cfg) || empty($cfg['api_key']) || empty($cfg['host'])) {
            return null;
        }
        require_once dirname(__DIR__, 2) . '/app/evolution/EvolutionApi.php';
        $clients[$instance] = new \EvolutionApi(
            (string) $cfg['host'],
            (string) $cfg['api_key'],
            $instance,
            30
        );
        return $clients[$instance];
    }

    /**
     * Envía un mensaje por Evolution (foto nativa, texto humano, audio rápido).
     */
    private function sendMessageEvo(array $ctx, string $msgStr, bool $isFirst, bool $isAudioReply): bool
    {
        $evo = $this->evoApiClient($ctx);
        $chatId = (string) ($ctx['evo_chat_id'] ?? '');
        if ($evo === null || $chatId === '') {
            return false;
        }
        // Foto → nativa (1 por mensaje)
        if ($this->isImageUrl($msgStr)) {
            $res = $evo->sendMedia($chatId, \EvolutionApi::MEDIA_IMAGE, $this->directImageUrl($msgStr), null, null, 0);
            return (bool) ($res['ok'] ?? false);
        }
        // Audio transcrito → respuesta rápida (delays mínimos por la latencia de whisper)
        if ($isAudioReply) {
            $res = $evo->sendHumanized($chatId, $msgStr, ['seen_delay_ms' => 0, 'typing_ms' => 600, 'after_ms' => 0]);
            return (bool) ($res['ok'] ?? false);
        }
        // Texto normal humano
        if ($isFirst) {
            $res = $evo->sendHumanized($chatId, $msgStr, ['seen_delay_ms' => 350, 'typing_ms' => 1300, 'after_ms' => 500]);
        } else {
            $res = $evo->sendText($chatId, $msgStr);
        }
        return (bool) ($res['ok'] ?? false);
    }

    /**
     * Detección de URL de imagen (compartir.site / extensiones / image-proxy).
     */
    private function isImageUrl(string $text): bool
    {
        if (preg_match('#https?://compartir\.site/#i', $text)) {
            return true;
        }
        if (preg_match('#https?://[^\s]+\.(jpe?g|png|webp|gif)(\?[^\s]*)?#i', $text)) {
            return true;
        }
        return str_contains($text, '/api/image-proxy.php');
    }

    /**
     * Convierte un shortlink compartir.site a la URL directa de la imagen.
     */
    private function directImageUrl(string $url): string
    {
        if (preg_match('#^https?://compartir\.site/([a-zA-Z0-9_-]+)/?$#i', $url, $m)) {
            return 'https://compartir.site/' . $m[1] . '/' . $m[1] . '.jpg';
        }
        return $url;
    }

    private function sendMessages(array &$ctx, array $messages, string $lockDir = '', string $fromPhone = ''): void
    {        // ── Cancel + pause check: if user paused this thread, abort send ──
        $threadId = (string) ($ctx['thread_id'] ?? $ctx['__thread_id'] ?? '');
        $pauseGate = $this->getPauseGate();
        if ($threadId !== '' && $pauseGate !== null) {
            // Single-use cancel file (created at pause moment, consumed once)
            if ($pauseGate->hasCancelRequest($threadId)) {
                $this->logger->info('Bot::sendMessages — response cancelled by user pause', [
                    'thread_id' => $threadId,
                ]);
                $pauseGate->clearCancelRequest($threadId);
                $ctx['_cancelled'] = true;
                $ctx['_send_ok']   = false;
                return;
            }
            // Persistent pause flag — survives cancel file consumption
            if ($pauseGate->isThreadPaused($threadId)) {
                $this->logger->info('Bot::sendMessages — thread is paused, aborting send', [
                    'thread_id' => $threadId,
                ]);
                $ctx['_cancelled'] = true;
                $ctx['_send_ok']   = false;
                return;
            }
        }

        // ── Bot-mode re-check: abort send if bot was stopped mid-pipeline ──
        if (!$this->isRunning()) {
            $this->logger->info('Bot::sendMessages — bot stopped, aborting send', [
                'thread_id' => $threadId,
            ]);
            $ctx['_cancelled'] = true;
            $ctx['_send_ok']   = false;
            return;
        }

        // ── Anti-metralleta: last-moment check BEFORE typing simulation ──
        // Catches messages that arrived during the LLM call + pipeline processing
        // but before we committed to sending via WAHA.
        static $sendDepth = 0;
        $sendDepth++;
        $hitDepthLimit = ($sendDepth > 5);
        if ($hitDepthLimit) {
            $this->logger->warning('Bot::sendMessages — depth limit reached, sending current messages without further re-processing', [
                'send_depth' => $sendDepth,
                'phone'      => $fromPhone,
            ]);
            $sendDepth = 0;
            // fall through to send below, but skip drainPending to avoid more recursion
        }

        if (!$hitDepthLimit && $lockDir !== '' && $fromPhone !== '') {
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

                    // ── Guard: re-verificar estado tras merge sin LLM ──
                    // Cubre el gap entre el top-check (línea 1553) y el intra-loop (línea 1670)
                    if ($threadId !== '' && $pauseGate !== null) {
                        $sendAborted = false;
                        if ($pauseGate->hasCancelRequest($threadId)) {
                            $this->logger->info('Bot::sendMessages — cancelled mid-merge by user pause');
                            $pauseGate->clearCancelRequest($threadId);
                            $sendAborted = true;
                        }
                        if (!$sendAborted && $pauseGate->isThreadPaused($threadId)) {
                            $this->logger->info('Bot::sendMessages — thread paused mid-merge, aborting');
                            $sendAborted = true;
                        }
                        if ($sendAborted) {
                            $ctx['_cancelled'] = true;
                            $ctx['_send_ok']   = false;
                            $sendDepth--;
                            return;
                        }
                    }
                    if (!$this->isRunning()) {
                        $this->logger->info('Bot::sendMessages — bot stopped mid-merge');
                        $ctx['_cancelled'] = true;
                        $ctx['_send_ok']   = false;
                        $sendDepth--;
                        return;
                    }
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

            // ── Intra-loop cancel/pause check: user may have paused during typing ──
            if ($threadId !== '' && $pauseGate !== null) {
                $aborted = false;
                if ($pauseGate->hasCancelRequest($threadId)) {
                    $this->logger->info('Bot::sendMessages — response cancelled mid-send by user pause', [
                        'thread_id' => $threadId,
                    ]);
                    $pauseGate->clearCancelRequest($threadId);
                    $aborted = true;
                }
                if (!$aborted && $pauseGate->isThreadPaused($threadId)) {
                    $this->logger->info('Bot::sendMessages — thread paused mid-send, aborting', [
                        'thread_id' => $threadId,
                    ]);
                    $aborted = true;
                }
                if ($aborted) {
                    $ctx['_cancelled'] = true;
                    $ctx['_send_ok']   = false;
                    break;
                }
            }
            // ── Intra-loop bot-mode check: bot may have been stopped during typing ──
            if (!$this->isRunning()) {
                $this->logger->info('Bot::sendMessages — bot stopped mid-send, aborting', [
                    'thread_id' => $threadId,
                ]);
                $ctx['_cancelled'] = true;
                $ctx['_send_ok']   = false;
                break;
            }

            // Dispatch por transporte: evolution (fotos nativas/audio) o WAHA (URLs)
            $isAudioReply = ((int) ($ctx['is_audio_i'] ?? 0) === 1) && trim((string) ($ctx['transcription'] ?? '')) !== '';
            if (($ctx['transport'] ?? 'waha') === 'evolution') {
                $ok = $this->sendMessageEvo($ctx, $msgStr, $isFirst, $isAudioReply);
            } elseif (!$isFirst) {
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
                    (float) ($ctx['user_response_time_sec'] ?? 60),
                    (bool)  ($ctx['__is_burst'] ?? false),
                    (bool)  ($ctx['__is_urgent'] ?? false),
                    (bool)  ($ctx['__is_reprocess'] ?? false),
                );
            }

            if (!$ok) {
                $ctx['_send_ok'] = false;
            }

            // Inter-message delay between split messages (presend_sleep_sec seconds)
            // Apply burst and urgent factors so inter-message pauses also speed up
            $presendSleep = (float) $this->config->get('human_delays.presend_sleep_sec', 4);
            if ($ctx['__is_burst'] ?? false) {
                $burstCfg = $humanDelays['burst'] ?? [];
                $presendSleep *= (float) ($burstCfg['rapid_factor'] ?? 0.33);
            }
            if ($ctx['__is_urgent'] ?? false) {
                $urgentCfg = $humanDelays['urgent'] ?? [];
                $presendSleep *= (float) ($urgentCfg['factor'] ?? 0.25);
            }
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

        // ── NOVA: anti-double-maps guard ──────────────────────────────
        // If ya_enviado already contains ubicacion or ubicacion_precisa,
        // the map was already sent in a previous turn. Only re-send if the
        // client EXPLICITLY asked for the location again in their CURRENT
        // message (the LLM will have included the URL in output_text).
        $yaEnviado = (array) ($ctx['ya_enviado'] ?? []);
        $mapAlreadySent = in_array('ubicacion', $yaEnviado, true)
                       || in_array('ubicacion_precisa', $yaEnviado, true);
        if ($mapAlreadySent) {
            $outputText = (string) ($ctx['output_text'] ?? '');
            $hasMapsInOutput = (bool) preg_match(
                '/(?:maps\.app\.goo\.gl|goo\.gl\/maps|google\.com\/maps)/i',
                $outputText
            );
            // If the LLM already embedded the URL in its reply, let it
            // through (the LLM decided to re-send).  If there's no URL
            // in the text, skip injection — this prevents the "double
            // maps" bug where the safety net re-injects after a pipeline
            // crash corrupted state.
            if ($hasMapsInOutput) {
                return $ctx;
            }
            $userMsg = (string) ($ctx['message_text'] ?? '');
            $askedAgain = (bool) preg_match(
                '/(?:ubicaci[oó]n|maps?|mapa|gps|direcci[oó]n|donde\s+est[aá]|calle|piso)/iu',
                $userMsg
            );
            if (!$askedAgain) {
                // Map already sent, client didn't ask again → skip injection
                $this->logger->info('Bot::injectLocationUrl — skipped (map already sent, client did not ask again)', [
                    'phone'     => $ctx['from_phone'] ?? '?',
                    'thread_id' => $ctx['thread_id'] ?? '?',
                ]);
                return $ctx;
            }
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
        $locationWords = '/(?:te\s*paso\s*(?:la\s*)?ubicaci[oó]n|te\s*paso\s*(?:el|la)\s*(?:maps?\b|mapa\b|gps\b|direcci[oó]n|dire\b|ubicacion\b|ubicación\b)|aqui\s*(?:va|tienes|esta|tiene)\s*(?:el|la)\s*(?:mapa|ubicaci[oó]n|direcci[oó]n|gps|ubicacion|dire\b)|te\s*(?:mando|env[ií]o)\s*(?:la\s*)?(?:ubicaci[oó]n|direcci[oó]n|mapa|gps|ubicacion|dire\b)|ubicaci[oó]n\s*exacta|punto\s*exacto|te\s*env[ií]o\s*(?:el|la)\s*(?:mapa|ubicaci[oó]n|direcci[oó]n|dire\b|ubicacion\b)|directo\s+al\s+grano|ah[ií]\s+te\s+va|toma\s+la\s+(?:ubicaci[oó]n|direcci[oó]n|dire\b|ubicacion\b))/iu';
        // Also trigger if the context flags predict maps is being sent now (deterministic fallback)
        $mapsBeingSentNow = !empty($ctx['maps_being_sent_now']);

        // ── NOVA SAFETY 2026-06-17: bloqueo anti-non-sequitur ──────────
        // Si el usuario está haciendo una pregunta (lleva '?') sobre algo que
        // NO es ubicación, y el LLM ha decidido enviar mapa de todas formas,
        // bloqueamos la inyección para no ignorar la pregunta del cliente.
        if (!preg_match($locationWords, $outputText) && $mapsBeingSentNow) {
            $userMsg = (string) ($ctx['message_text'] ?? '');
            $hasQuestion = (mb_strpos($userMsg, '?') !== false);
            $userAsksLocation = (bool) preg_match(
                '/(?:d[oó]nde|ubicaci[oó]n|direcci[oó]n|mapa|dire\b|calle|plaza|zona|piso|sitio|lugar)/iu',
                $userMsg
            );
            // Si el usuario preguntó algo que NO es ubicación, no inyectar mapa
            if ($hasQuestion && !$userAsksLocation) {
                if ($this->logger !== null) {
                    $this->logger->info('Bot::injectLocationUrl — blocked maps (user asking non-location question)', [
                        'phone'     => $ctx['from_phone'] ?? '?',
                        'thread_id' => $ctx['thread_id'] ?? '?',
                        'user_msg'  => mb_substr($userMsg, 0, 80),
                    ]);
                }
                return $ctx;
            }
        }

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

        // Check if the AI output contains photo-promising language FIRST
        // (before the ya_enviado guard, so explicit promises always trigger injection)
        $promisePatterns = [
            '/te\s*paso\s*fotos?\b/iu',
            '/te\s*(?:las\s*)?(?:mando|env[ií]o|ense[ñn]o)\s*fotos?\b/iu',
            '/te\s*las\s*(?:mando|env[ií]o|paso|ense[ñn]o)\s*otra\s*vez\b/iu',
            '/mira\s*te\s*ense[ñn]o\s*a\b/iu',
            '/te\s*las\s*env[ií]o\b/iu',
            '/te\s*las\s*mando\b/iu',
            '/te\s*paso\s*(?:otra\s*vez\s*)?las\s*chicas\b/iu',
            '/aqui\s*(?:las\s*)?tienes\s*(?:de\s*nuevo\s*)?(?:las\s*chicas|las\s*fotos?|el\s*cat[aá]logo)\b/iu',
            '/mira\s*te\s*las?\s*(?:mando|paso|ense[ñn]o)\s*otra\s*vez\b/iu',
            '/toma\s*te\s*las?\s*paso\b/iu',
            '/todas\s*est[aá]n\s*bien\s*papi\b/iu', // "todas estan bien papi" con contexto de fotos
            // ── NOVA: promesas condicionales de "más fotos" que el LLM usa ──
            '/te\s*ense[ñn]o\s*(?:m[aá]s|otras?)\s*fotos?\b/iu',
            '/tendr[ií]a\s+(?:m[aá]s|otras?)\s+fotos?\b/iu',
            '/(?:tengo|hay|tiene)\s+(?:m[aá]s|otras?)\s+fotos?\b/iu',
            '/te\s*(?:mando|paso|env[ií]o|ense[ñn]o)\s+(?:m[aá]s|otras?)\s+fotos?\b/iu',
            '/si\s+quieres\s+te\s+(?:mando|paso|env[ií]o|ense[ñn]o)\s+(?:m[aá]s|otras?)\s+fotos?\b/iu',
            '/a[uú]n\s+tengo\s+(?:m[aá]s|otras?)\s+fotos?\b/iu',
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

        // ── NOVA: si el texto es una NEGACIÓN de fotos ("no tengo más fotos",
        // "solo tengo esas"), NO es una promesa → no inyectar nada. ──
        if (preg_match(
            '/(?:no\s+tengo\s+(?:m[aá]s|otras?)\s+fotos?|no\s+(?:hay|tengo|mando|paso|env[ií]o)\s+(?:m[aá]s\s+)?fotos?|solo\s+tengo\s+(?:esas|estas|una|dos|tres|pocas)\s+fotos?)/iu',
            $outputText
        )) {
            return $ctx;
        }

        // Skip if photos were already sent, client hasn't insisted 2+ times,
        // AND there is NO selected girl (the promise is about a specific girl's
        // photos, not generic catalog). When selected_girl is set, the client
        // is asking about THAT girl's photos specifically — inject them.
        $yaEnviado = (array) ($ctx['ya_enviado'] ?? []);
        $photoInsistCount = (int) ($ctx['photo_insist_count'] ?? 0);
        $hasSelectedGirl = !empty($ctx['selected_girl_name']);
        if (in_array('fotos', $yaEnviado, true) && $photoInsistCount < 1 && !$hasSelectedGirl) {
            // Generic photos already sent and no specific girl request → skip
            return $ctx;
        }

        // Must have girls configured
        $girlsConfig = $ctx['girls_config'] ?? [];
        if (!is_array($girlsConfig) || $girlsConfig === []) {
            return $ctx;
        }

        // Build photos: if selected_girl is set → ALL her photos; else → catalog
        $sentUrls = $ctx['sent_photo_urls'] ?? [];
        $lines = [];

        if ($hasSelectedGirl) {
            // Find the selected girl in girls_config and use ALL her photos
            $selectedGirlName = (string) ($ctx['selected_girl_name'] ?? '');
            $selectedGirl = null;
            foreach ($girlsConfig as $girl) {
                if (!is_array($girl)) continue;
                if (mb_strtolower(trim((string) ($girl['nombre'] ?? '')), 'UTF-8') === mb_strtolower($selectedGirlName, 'UTF-8')) {
                    $selectedGirl = $girl;
                    break;
                }
            }
            if ($selectedGirl !== null) {
                $photos = $selectedGirl['fotos'] ?? [];
                if (is_array($photos) && $photos !== []) {
                    foreach ($photos as $photo) {
                        $photo = trim((string) $photo);
                        if ($photo === '') continue;
                        if (str_contains($photo, 'maps.google') || str_contains($photo, 'maps.app.goo.gl') || str_contains($photo, 'goo.gl/maps')) continue;
                        // Filter already-sent
                        if (in_array($photo, $sentUrls, true)) continue;
                        $lines[] = $photo;
                    }
                    // If all photos were already sent, include them anyway
                    // (client insists OR the bot itself promised photos).
                    if ($lines === [] && ($photoInsistCount >= 1 || $hasPromise)) {
                        foreach ($photos as $photo) {
                            $photo = trim((string) $photo);
                            if ($photo === '') continue;
                            $lines[] = $photo;
                        }
                    }
                }
            }
        }

        // Fallback: if no selected girl or her photos failed, build catalog
        if ($lines === [] && !$hasSelectedGirl) {
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

        $this->logger->info('Bot::injectPhotoUrls — injected photos as safety net', [
            'phone'     => $ctx['from_phone'] ?? '?',
            'thread_id' => $ctx['thread_id'] ?? '?',
            'num_photos' => count($lines),
            'selected_girl' => $hasSelectedGirl ? ($ctx['selected_girl_name'] ?? '?') : 'none',
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
        // ── Escenario 1: bot parado globalmente ──
        if (!$this->isRunning()) {
            $this->logger->info('Bot::handleIncomingWhileProcessing — bot stopped globally, aborting reprocess');
            $ctx['_cancelled'] = true;
            $ctx['_send_ok']   = false;
            return $ctx;
        }

        // ── Escenario 2 y 3: conversación pausada o cancelada mid-generación ──
        $threadId = (string) ($ctx['thread_id'] ?? $ctx['__thread_id'] ?? '');
        $pauseGate = $this->getPauseGate();
        if ($threadId !== '' && $pauseGate !== null) {
            if ($pauseGate->hasCancelRequest($threadId)) {
                $this->logger->info('Bot::handleIncomingWhileProcessing — cancelled by user pause, aborting reprocess');
                $pauseGate->clearCancelRequest($threadId);
                $ctx['_cancelled'] = true;
                $ctx['_send_ok']   = false;
                return $ctx;
            }
            if ($pauseGate->isThreadPaused($threadId)) {
                $this->logger->info('Bot::handleIncomingWhileProcessing — thread paused, aborting reprocess');
                $ctx['_cancelled'] = true;
                $ctx['_send_ok']   = false;
                return $ctx;
            }
        }

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

        // ── Build LLM-friendly structured message ─────────────────
        // When messages are coalesced, the LLM has recency bias and
        // tends to respond to the LAST message. We structure the
        // prompt so the first (original) message is the PRIMARY one
        // and subsequent messages are just additional context.
        $llmMessage = $originalText;
        if (count($coalescedParts) > 1) {
            $additional = array_slice($coalescedParts, 1);
            $llmMessage  = "[MENSAJE PRINCIPAL]: {$originalText}\n\n";
            $llmMessage .= "[MENSAJES ADICIONALES DEL CLIENTE (llegaron mientras procesabas — NO respondas a estos como mensaje principal, solo tenlos en cuenta como contexto)]:\n";
            foreach ($additional as $i => $add) {
                $llmMessage .= "- {$add}\n";
            }
        }

        // Update context with coalesced message
        $ctx['message_text']           = $coalescedText;
        $ctx['__coalesced_text']       = $coalescedText;
        $ctx['__llm_user_message']     = $llmMessage;
        $ctx['__reprocess_depth']      = $depth + 1;
        $ctx['__is_reprocess']         = true;
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
        if (isset($this->processors[2]) && $this->processors[2]->name() === 'ToneBuilder') {
            $ctx = $this->processors[2]->process($ctx);
        }

        // Step 10: re-run LLM call with coalesced message
        $systemPrompt = $this->buildSystemPrompt($ctx);
        $userMessage  = $ctx['__llm_user_message'] ?? $ctx['user_message'] ?? $messageText;

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
        if (isset($this->processors[3]) && $this->processors[3]->name() === 'ResponseNormalizer') {
            $ctx = $this->processors[3]->process($ctx);
        }

        // ── Fallback: if re-processing LLM returned empty ──
        if (trim((string) ($ctx['output_text'] ?? '')) === '') {
            $fallbackRaw = $this->config->get('message_variants.fallback_empty_text', ['vale cari']);
            $fallbackVariants = is_string($fallbackRaw) ? [$fallbackRaw] : (array) $fallbackRaw;
            $fallback = $fallbackVariants !== [] ? $fallbackVariants[array_rand($fallbackVariants)] : 'vale cari';
            $ctx['output_text']   = $fallback;
            $ctx['lead_detected'] = false;
        }
        if (isset($this->processors[4]) && $this->processors[4]->name() === 'CatalogFormatter') {
            $ctx = $this->processors[4]->process($ctx);
        }
        if (isset($this->processors[5]) && $this->processors[5]->name() === 'DedupeReply') {
            $ctx = $this->processors[5]->process($ctx);
        }
        if (isset($this->processors[6]) && $this->processors[6]->name() === 'ImageSplitter') {
            $ctx = $this->processors[6]->process($ctx);
        }

        // Re-run safety nets
        $ctx = $this->injectLocationUrl($ctx);
        $ctx = $this->injectPhotoUrls($ctx);

        // ── Recursive: check again for even newer messages ────────────
        return $this->handleIncomingWhileProcessing($ctx, $lockDir, $fromPhone);
    }
}
