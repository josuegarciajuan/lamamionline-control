<?php

declare(strict_types=1);

namespace WasapBot;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\LoggerInterface;

/**
 * Main Bot contract — orchestrates the full message processing pipeline.
 */
interface BotInterface
{
    /**
     * Process an incoming WAHA webhook payload and return the response.
     *
     * @param array<string, mixed> $webhookPayload  Raw WAHA webhook body.
     * @return array<string, mixed>|null  Response data or null if message was rejected/ignored.
     */
    public function handleWebhook(array $webhookPayload): ?array;

    public function isRunning(): bool;

    public function getConfig(): ConfigInterface;

    public function getLogger(): LoggerInterface;
}
