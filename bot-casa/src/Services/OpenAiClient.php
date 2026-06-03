<?php

declare(strict_types=1);

namespace WasapBot\Services;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\HttpClientInterface;
use WasapBot\Core\LoggerInterface;

/**
 * OpenAI API client — Chat completions and tone classification.
 */
final class OpenAiClient implements OpenAiClientInterface
{
    /** @var array<string, mixed>|null */
    private ?array $lastRawResponse = null;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Send a chat completion request.
     *
     * @param string      $systemPrompt The system-level instruction prompt.
     * @param string      $userMessage  The user's message text.
     * @param array       $context      Additional context key-value pairs.
     * @param string|null $model        Override model; falls back to config openai.chat_model.
     * @param array       $history      Previous conversation turns [{role, content}, ...] for multi-turn memory.
     * @return array Parsed JSON from the assistant response.
     *               Falls back to ['user_visible_reply' => rawContent] on parse failure.
     */
    public function chat(string $systemPrompt, string $userMessage, array $context = [], string $model = null, array $history = []): array
    {
        $model    ??= $this->config->get('openai.chat_model', 'gpt-5.1');
        $chatUrl    = $this->config->get('openai.chat_url', 'https://api.openai.com/v1/chat/completions');
        $temperature = (float) $this->config->get('openai.temperature', 0);

        $userContent = $userMessage;
        if ($context !== []) {
            $userContent .= "\n\n### CONTEXTO\n" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
        ];

        $headers = $this->buildAuthHeaders();

        try {
            [$httpCode, $rawBody] = $this->http->post($chatUrl, $body, $headers, 60);

            $this->lastRawResponse = ['http_code' => $httpCode, 'body' => $rawBody];

            if ($httpCode < 200 || $httpCode >= 300) {
                $this->logger->warning("OpenAI chat returned HTTP {$httpCode}", [
                    'error' => $this->http->lastError(),
                    'body'  => mb_substr($rawBody, 0, 200),
                ]);
                return [];
            }

            $parsed = $this->parseChoicesContent($rawBody);

        } catch (\Throwable $e) {
            $this->logger->error("OpenAI chat exception: {$e->getMessage()}");
            return [];
        }

        return $parsed;
    }

    /**
     * Classify the sentiment, register, and urgency of a user message.
     *
     * @return array{sentiment: string, register: string, urgency: string}
     */
    public function classifyTone(string $userMessage): array
    {
        $defaults = ['sentiment' => 'neutro', 'register' => 'coloquial', 'urgency' => 'media'];

        $model     = $this->config->get('openai.tone_classifier_model', 'gpt-4o-mini');
        $chatUrl   = $this->config->get('openai.chat_url', 'https://api.openai.com/v1/chat/completions');
        $maxTokens = (int) $this->config->get('openai.tone_max_tokens', 50);
        $temperature = (float) $this->config->get('openai.tone_temperature', 0);

        $systemPrompt = 'Clasifica el mensaje en JSON:{"sentiment":"positivo|neutro|negativo","register":"formal|coloquial","urgency":"baja|media|alta"}. Devuelve SOLO JSON.';

        $body = [
            'model'           => $model,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user',   'content' => $userMessage],
            ],
            'temperature'     => $temperature,
            'max_tokens'      => $maxTokens,
        ];

        $headers = $this->buildAuthHeaders();

        try {
            [$httpCode, $rawBody] = $this->http->post($chatUrl, $body, $headers, 30);

            if ($httpCode < 200 || $httpCode >= 300) {
                $this->logger->warning("Tone classifier returned HTTP {$httpCode}");
                return $defaults;
            }

            $parsed = $this->parseChoicesContent($rawBody);

            return [
                'sentiment' => (string) ($parsed['sentiment'] ?? $defaults['sentiment']),
                'register'  => (string) ($parsed['register'] ?? $defaults['register']),
                'urgency'   => (string) ($parsed['urgency'] ?? $defaults['urgency']),
            ];

        } catch (\Throwable $e) {
            $this->logger->error("Tone classifier exception: {$e->getMessage()}");
        }

        return $defaults;
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
     * Parse choices[0].message.content from the OpenAI API response.
     */
    private function parseChoicesContent(string $rawBody): array
    {
        try {
            $apiResponse = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->warning("OpenAI response is not valid JSON: {$e->getMessage()}");
            return ['user_visible_reply' => $rawBody];
        }

        $content = $apiResponse['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
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
                        $this->logger->info('OpenAI response recovered via regex extraction');
                        return $recovered;
                    }
                } catch (\JsonException $e) {
                    // Try next candidate
                }
            }
        }

        return ['user_visible_reply' => (string) $content];
    }

    /**
     * Build Authorization + Content-Type headers for OpenAI.
     *
     * @return list<string>
     */
    private function buildAuthHeaders(): array
    {
        $apiKey = $this->config->get('openai.api_key', '');

        return [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ];
    }
}
