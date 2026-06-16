<?php

declare(strict_types=1);

namespace WasapBot\Services;

/**
 * WAHA API contract — WhatsApp HTTP API integration.
 */
interface WahaApiInterface
{
    public function sendText(string $baseUrl, string $chatId, string $text, string $session): bool;
    public function sendSeen(string $baseUrl, string $chatId, string $session): bool;
    public function startTyping(string $baseUrl, string $chatId, string $session): bool;
    public function stopTyping(string $baseUrl, string $chatId, string $session): bool;
    public function sendImage(string $baseUrl, string $chatId, string $imageUrl, string $caption, string $session): bool;

    // Anti-ban: humanized send sequence with typing delays
    public function sendHumanized(
        string $baseUrl,
        string $chatId,
        string $text,
        string $session,
        array $delayConfig,
        string $incomingText = '',
        int $turnCount = 1,
        float $userResponseTimeSec = 60.0,
        bool $isBurst = false,
        bool $isUrgent = false,
    ): bool;
}
