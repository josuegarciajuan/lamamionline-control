<?php

declare(strict_types=1);

namespace WasapBot\SideEffects;

/**
 * Lead detector contract — determines if a conversation has produced a lead.
 */
interface LeadDetectorInterface
{
    /**
     * @param array<string, mixed> $openAiResponse  Parsed OpenAI JSON response.
     * @return bool
     */
    public function isLead(array $openAiResponse): bool;

    /**
     * @return float  0.0 to 1.0 confidence.
     */
    public function confidence(array $openAiResponse): float;

    /**
     * @return int  ETA in minutes, 0 if not specified.
     */
    public function etaMinutes(array $openAiResponse): int;
}
