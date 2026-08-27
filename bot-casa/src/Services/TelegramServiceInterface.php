<?php

declare(strict_types=1);

namespace WasapBot\Services;

/**
 * Telegram API contract — send lead alerts to configured chat IDs.
 */
interface TelegramServiceInterface
{
    /** @param array<string, mixed> $leadData */
    public function sendLeadAlert(array $leadData): void;
    public function sendMessage(string $chatId, string $text): bool;
}
