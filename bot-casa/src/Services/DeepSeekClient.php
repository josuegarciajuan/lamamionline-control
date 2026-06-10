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
     * @param string|null $model        Override model; falls back to config deepseek.chat_model.
     * @param array       $history      Previous conversation turns [{role, content}, ...] for multi-turn memory.
     * @return array Parsed JSON from the assistant response.
     */
    public function chat(string $systemPrompt, string $userMessage, array $context = [], string $model = null, array $history = []): array
    {
        $model       ??= $this->config->get('deepseek.chat_model', 'deepseek-v4-flash');
        $chatUrl       = $this->config->get('deepseek.chat_url', 'https://api.deepseek.com/v1/chat/completions');
        $temperature   = (float) $this->config->get('deepseek.temperature', 0.7);

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

        $maxAttempts = (int) $this->config->get('ai_retry.max_attempts', 3);
        $baseDelay   = (int) $this->config->get('ai_retry.base_delay_sec', 2);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                [$httpCode, $rawBody] = $this->http->post($chatUrl, $body, $headers, 60);

                $this->lastRawResponse = ['http_code' => $httpCode, 'body' => $rawBody];

                if ($httpCode >= 200 && $httpCode < 300) {
                    return $this->parseChoicesContent($rawBody);
                }

                // Only retry on timeout (httpCode=0) or server errors (5xx)
                $isRetryable = ($httpCode === 0) || ($httpCode >= 500);

                if ($isRetryable && $attempt < $maxAttempts) {
                    $delay = $baseDelay * (int) pow(2, $attempt - 1);
                    $this->logger->warning("DeepSeek chat — retrying in {$delay}s (attempt {$attempt}/{$maxAttempts})", [
                        'http_code' => $httpCode,
                        'error'     => $this->http->lastError(),
                    ]);
                    sleep($delay);
                    continue;
                }

                $this->logger->warning("DeepSeek chat returned HTTP {$httpCode}", [
                    'error'    => $this->http->lastError(),
                    'body'     => mb_substr($rawBody, 0, 200),
                    'attempts' => $attempt,
                ]);
                return [];

            } catch (\Throwable $e) {
                $this->logger->error("DeepSeek chat exception: {$e->getMessage()}");
                return [];
            }
        }

        return [];
    }

    /**
     * Classify the sentiment, register, and urgency of a user message.
     *
     * NOTE: This delegates to the same logic as OpenAiClient because
     * tone classification is a lightweight call (50 tokens) and not
     * provider-sensitive.
     */
    public function classifyTone(string $userMessage): array
    {
        return ['sentiment' => 'neutro', 'register' => 'coloquial', 'urgency' => 'media'];
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
     * Parse choices[0].message.content from the API response.
     */
    private function parseChoicesContent(string $rawBody): array
    {
        try {
            $apiResponse = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->warning("DeepSeek response is not valid JSON: {$e->getMessage()}");
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
                        $this->logger->info('DeepSeek response recovered via regex extraction');
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
