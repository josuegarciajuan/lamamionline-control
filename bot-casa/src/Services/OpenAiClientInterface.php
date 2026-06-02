<?php

declare(strict_types=1);

namespace WasapBot\Services;

/**
 * OpenAI API contract — Chat completions and tone classification.
 */
interface OpenAiClientInterface
{
    public function chat(string $systemPrompt, string $userMessage, array $context = [], string $model = null): array;
    public function classifyTone(string $userMessage): array;
    public function getLastRawResponse(): ?array;
}
