<?php

declare(strict_types=1);

namespace WasapBot\Services;

/**
 * OpenAI API contract — Chat completions and tone classification.
 */
interface OpenAiClientInterface
{
    /**
     * @param array<string, mixed> $context
     * @param list<array{role: string, content: string}> $history
     * @return array<string, mixed>
     */
    public function chat(string $systemPrompt, string $userMessage, array $context = [], ?string $model = null, array $history = []): array;
    /** @return array<string, mixed> */
    public function classifyTone(string $userMessage): array;
    /** @return array<string, mixed>|null */
    public function getLastRawResponse(): ?array;
}
