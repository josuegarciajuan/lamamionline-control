<?php

declare(strict_types=1);

namespace WasapBot\Services;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\HttpClientInterface;
use WasapBot\Core\LoggerInterface;

/**
 * DeepSeek API client — Chat completions (OpenAI-compatible API).
 *
 * DeepSeek uses the same /v1/chat/completions format as OpenAI,
 * so this implementation mirrors OpenAiClient but reads from
 * the deepseek.* config keys instead of openai.*.
 */
final class DeepSeekClient implements OpenAiClientInterface
{
    /** @var array<string, mixed>|null */
    private ?array $lastRawResponse = null;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Send a chat completion request to DeepSeek.
     *
     * @param string      $systemPrompt The system-level instruction prompt.
     * @param string      $userMessage  The user's message text.
     * @param array       $context      Additional context key-value pairs.
     * @param array<string, mixed> $context   Extra context appended to the user message.
     * @param string|null $model        Override model; falls back to config deepseek.chat_model.
     * @param list<array{role: string, content: string}> $history Previous conversation turns.
     * @return array<string, mixed> Parsed JSON from the assistant response.
     */
    public function chat(string $systemPrompt, string $userMessage, array $context = [], ?string $model = null, array $history = []): array
    {
        $model       ??= $this->config->get('deepseek.chat_model', 'deepseek-v4-pro');
        $chatUrl       = $this->config->get('deepseek.chat_url', 'https://api.deepseek.com/chat/completions');
        $temperature   = (float) $this->config->get('deepseek.temperature', 0.7);

        $userContent = $userMessage;
        if ($context !== []) {
            $userContent .= "\n\n### CONTEXTO\n" . $this->formatContext($context);
        }

        // Build multi-turn messages: system + history + current user message
        $messages = [['role' => 'system', 'content' => $systemPrompt]];

        // Insert previous conversation turns (real multi-turn memory)
        foreach ($history as $turn) {
            $role    = (string) ($turn['role'] ?? '');
            $content = (string) ($turn['content'] ?? '');
            if (($role === 'user' || $role === 'assistant') && $content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        // Current user message goes last
        $messages[] = ['role' => 'user', 'content' => $userContent];

        $body = [
            'model'           => $model,
            'response_format' => ['type' => 'json_object'],
            'messages'        => $messages,
            'temperature'     => $temperature,
            'thinking'        => ['type' => 'enabled'],
            'reasoning_effort' => 'high',
        ];

        $headers = $this->buildAuthHeaders();

        $maxAttempts = (int) $this->config->get('ai_retry.max_attempts', 3);
        $baseDelay   = (int) $this->config->get('ai_retry.base_delay_sec', 2);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                [$httpCode, $rawBody] = $this->http->post($chatUrl, $body, $headers, 60);

                $this->lastRawResponse = ['http_code' => $httpCode, 'body' => $rawBody];

                if ($httpCode >= 200 && $httpCode < 300) {
                    $parsed = $this->parseChoicesContent($rawBody);

                    // ── Retry on empty / fallback responses ────────────
                    // When DeepSeek returns 200 but the content is empty,
                    // whitespace-only, or could not be parsed into a
                    // valid user_visible_reply, retry instead of sending
                    // a generic fallback to the user.
                    $uvr = trim((string) ($parsed['user_visible_reply'] ?? ''));
                    if ($uvr === '' && $attempt < $maxAttempts) {
                        $this->logger->warning("DeepSeek chat — empty/invalid user_visible_reply, retrying (attempt {$attempt}/{$maxAttempts})", [
                            'content_head' => is_string($rawBody) ? mb_substr($rawBody, 0, 150) : 'N/A',
                        ]);
                        usleep(500000); // 500ms pause before retry
                        continue;
                    }
                    return $parsed;
                }

                // Rate limit (429) — honour Retry-After if present, else exponential backoff
                if ($httpCode === 429 && $attempt < $maxAttempts) {
                    $retryAfter = (int) ($headers['Retry-After'] ?? ($baseDelay * (int) pow(2, $attempt)));
                    $this->logger->warning("DeepSeek chat — rate limited, retrying in {$retryAfter}s (attempt {$attempt}/{$maxAttempts})");
                    sleep(max(1, $retryAfter));
                    continue;
                }

                // Server errors (5xx) and timeouts — exponential backoff with jitter
                $isRetryable = ($httpCode === 0) || ($httpCode >= 500);

                if ($isRetryable && $attempt < $maxAttempts) {
                    $jitterMs = random_int(0, 500);
                    $delay = $baseDelay * (int) pow(2, $attempt - 1) + ($jitterMs / 1000);
                    $this->logger->warning("DeepSeek chat — retrying in {$delay}s (attempt {$attempt}/{$maxAttempts})", [
                        'http_code' => $httpCode,
                        'error'     => $this->http->lastError(),
                    ]);
                    usleep((int) ($delay * 1_000_000));
                    continue;
                }

                // Client errors (4xx except 429) — no retry
                $this->logger->warning("DeepSeek chat returned HTTP {$httpCode}", [
                    'error'    => $this->http->lastError(),
                    'body'     => mb_substr($rawBody, 0, 200),
                    'attempts' => $attempt,
                ]);
                return [];

            } catch (\Throwable $e) {
                // Network errors — retry with backoff
                if ($attempt < $maxAttempts) {
                    $jitterMs = random_int(0, 500);
                    $delay = $baseDelay * (int) pow(2, $attempt - 1) + ($jitterMs / 1000);
                    $this->logger->warning("DeepSeek chat exception, retrying in {$delay}s (attempt {$attempt}/{$maxAttempts}): {$e->getMessage()}");
                    usleep((int) ($delay * 1_000_000));
                    continue;
                }
                $this->logger->error("DeepSeek chat exception after {$maxAttempts} attempts: {$e->getMessage()}");
                return [];
            }
        }

        return [];
    }

    /**
     * Classify the sentiment, register, and urgency of a user message
     * by calling the DeepSeek API with a lightweight tone prompt.
     *
     * Returns neutral defaults on any failure so the pipeline never halts.
     *
     * @return array{sentiment: string, register: string, urgency: string}
     */
    public function classifyTone(string $userMessage): array
    {
        $defaults = ['sentiment' => 'neutral', 'register' => 'coloquial', 'urgency' => 'media'];

        $toneUrl  = $this->config->get('deepseek.chat_url', 'https://api.deepseek.com/chat/completions');
        $toneModel = $this->config->get('deepseek.tone_model', 'deepseek-v4-flash');

        $body = [
            'model'           => $toneModel,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                [
                    'role'    => 'system',
                    'content' => "Eres un clasificador de tono para mensajes de WhatsApp en español de España. "
                               . "Analiza el mensaje y devuelve SOLO un JSON con estos 3 campos exactos:\n"
                               . '- sentiment: "positivo", "negativo", "neutro", "hostil", "urgente", "ansioso" o "emocionado"' . "\n"
                               . '- register: "coloquial", "formal", "vulgar", "cortante" o "normal"' . "\n"
                               . '- urgency: "alta", "media" o "baja"' . "\n"
                               . "Ejemplo: {\"sentiment\":\"positivo\",\"register\":\"coloquial\",\"urgency\":\"media\"}",
                ],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'temperature'     => 0.0,
            'max_tokens'      => 80,
            'thinking'        => ['type' => 'disabled'],
        ];

        $headers = $this->buildAuthHeaders();

        try {
            [$httpCode, $rawBody] = $this->http->post($toneUrl, $body, $headers, 15);
        } catch (\Throwable $e) {
            $this->logger->warning('DeepSeek tone classification — HTTP exception: ' . $e->getMessage());
            return $defaults;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->logger->warning("DeepSeek tone classification — HTTP {$httpCode}, using defaults");
            return $defaults;
        }

        try {
            $apiResponse = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
            $content = $apiResponse['choices'][0]['message']['content'] ?? null;
            if ($content === null) {
                return $defaults;
            }
            $parsed = json_decode((string) $content, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($parsed)) {
                return $defaults;
            }

            $validSentiments = ['positivo', 'negativo', 'neutro', 'hostil', 'urgente', 'ansioso', 'emocionado'];
            $validRegisters  = ['coloquial', 'formal', 'vulgar', 'cortante', 'normal'];
            $validUrgencies  = ['alta', 'media', 'baja'];

            $result = $defaults;
            if (isset($parsed['sentiment']) && is_string($parsed['sentiment']) && in_array($parsed['sentiment'], $validSentiments, true)) {
                $result['sentiment'] = $parsed['sentiment'];
            }
            if (isset($parsed['register']) && is_string($parsed['register']) && in_array($parsed['register'], $validRegisters, true)) {
                $result['register'] = $parsed['register'];
            }
            if (isset($parsed['urgency']) && is_string($parsed['urgency']) && in_array($parsed['urgency'], $validUrgencies, true)) {
                $result['urgency'] = $parsed['urgency'];
            }

            return $result;
        } catch (\JsonException $e) {
            $this->logger->warning('DeepSeek tone classification — JSON parse error: ' . $e->getMessage());
            return $defaults;
        }
    }

    /**
     * Returns the last raw API response array, or null if no call has been made.
     *
     * @return array<string, mixed>|null
     */
    public function getLastRawResponse(): ?array
    {
        return $this->lastRawResponse;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Format the full context array as human-readable structured text
     * instead of a raw JSON dump.  This helps the LLM parse key fields
     * faster and reduces token waste on repeated keys / null values.
     *
     * @param array<string, mixed> $ctx
     */
    private function formatContext(array $ctx): string
    {
        $lines = [];

        // ── Identity ──────────────────────────────────────────────────
        $speaker  = trim((string) ($ctx['speaker_girl_name'] ?? ''));
        $selected = trim((string) ($ctx['selected_girl_name'] ?? ''));
        $mode     = (string) ($ctx['speaker_mode'] ?? '');

        if ($speaker !== '') {
            $lines[] = "IDENTIDAD: eres {$speaker}" . ($mode === 'chica' ? ' (chica)' : '');
        } else {
            $lines[] = 'IDENTIDAD: encargada (modo genérico, no digas que eres la encargada)';
        }

        if ($selected !== '' && $selected !== $speaker) {
            $lines[] = "CHICA ELEGIDA: {$selected} (ella es tu amiga, háblale de ella en 3ª persona)";
        } elseif ($selected !== '') {
            $lines[] = "CHICA ELEGIDA: {$selected} (el cliente ya eligió, NO ofrezcas otras)";
        }

        // ── Active girls for name reference ────────────────────────────
        $girlsConfig = (array) ($ctx['girls_config'] ?? []);
        if ($girlsConfig !== []) {
            $girlNames = [];
            foreach ($girlsConfig as $g) {
                $name = trim((string) ($g['nombre'] ?? ''));
                if ($name !== '') {
                    $girlNames[] = $name;
                }
            }
            if ($girlNames !== []) {
                $lines[] = 'CHICAS ACTIVAS: ' . implode(', ', $girlNames);
            }
        }

        // ── Pending questions (cliente pidió algo y no se respondió) ───
        $pendingQuestions = (array) ($ctx['preguntas_pendientes'] ?? []);
        if ($pendingQuestions !== []) {
            $readable = [];
            foreach ($pendingQuestions as $q) {
                $readable[] = match ($q) {
                    'fotos_pendientes'    => 'pidió fotos y NO se las has enviado',
                    'precios_pendientes'  => 'pidió precios y NO se los has dado',
                    'ubicacion_pendiente' => 'pidió ubicación y NO se la has enviado',
                    default               => $q,
                };
            }
            $lines[] = '⚠️ PREGUNTAS PENDIENTES: ' . implode(' | ', $readable);
        }

        // ── Location URL ──────────────────────────────────────────────
        $locationUrl = trim((string) ($ctx['location_url'] ?? ''));
        if ($locationUrl !== '') {
            $lines[] = "MAPS DISPONIBLE: {$locationUrl}";
        }

        // ── Client profile ─────────────────────────────────────────────
        $profileHint = trim((string) ($ctx['client_profile_hint'] ?? ''));
        if ($profileHint !== '') {
            $lines[] = "PERFIL DEL CLIENTE: {$profileHint}";
        }

        return implode("\n", $lines);
    }

    /**
     * Parse choices[0].message.content from the API response.
     *
     * @return array<string, mixed>
     */
    private function parseChoicesContent(string $rawBody): array
    {
        try {
            $apiResponse = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->warning("DeepSeek response is not valid JSON: {$e->getMessage()}", [
                'raw_head' => mb_substr($rawBody, 0, 300),
            ]);
            // SAFEGUARD: never leak raw API response to the user.
            // Return a safe Spanish fallback instead.
            return ['user_visible_reply' => 'dime otra vez cari 😘'];
        }

        $content = $apiResponse['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            $this->logger->warning('DeepSeek chat — choices[0].message.content is null', [
                'choice_count'  => count($apiResponse['choices'] ?? []),
                'finish_reason' => $apiResponse['choices'][0]['finish_reason'] ?? '?',
                'raw_keys'      => array_keys($apiResponse),
                'has_usage'     => isset($apiResponse['usage']),
                'raw_head'      => mb_substr($rawBody, 0, 400),
            ]);
            return [];
        }

        // Attempt to parse the content as JSON
        try {
            $parsed = json_decode((string) $content, true, 512, JSON_THROW_ON_ERROR);
            if (is_array($parsed)) {
                return $parsed;
            }
        } catch (\JsonException $e) {
            // Content is not pure JSON — try regex extraction before giving up
        }

        // ── Recovery: extract any valid JSON object with user_visible_reply ──
        // Handles cases where AI appends extra text like " response{...}" after JSON.
        if (preg_match_all('/\{(?:[^{}]|(?R))*\}/s', (string) $content, $matches)) {
            foreach ($matches[0] as $candidate) {
                try {
                    $recovered = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($recovered) && isset($recovered['user_visible_reply'])) {
                        $this->logger->info('DeepSeek response recovered via regex extraction');
                        return $recovered;
                    }
                } catch (\JsonException $e) {
                    // Try next candidate
                }
            }
        }

        // SAFEGUARD: If LLM response couldn't be parsed as valid JSON
        // (hallucinated text, malformed output), log and return a safe
        // Spanish fallback instead of leaking raw AI output to the user.
        $this->logger->warning('DeepSeek response — content is not valid JSON and could not be recovered', [
            'content_head' => mb_substr((string) $content, 0, 200),
        ]);
        return ['user_visible_reply' => 'a ver, dime de nuevo precioso 😘'];
    }

    /**
     * Build Authorization + Content-Type headers for DeepSeek.
     *
     * @return list<string>
     */
    private function buildAuthHeaders(): array
    {
        $apiKey = $this->config->get('deepseek.api_key', '');

        return [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ];
    }
}
