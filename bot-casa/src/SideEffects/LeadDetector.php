<?php

declare(strict_types=1);

namespace WasapBot\SideEffects;

use WasapBot\Core\Config;
use WasapBot\Core\LoggerInterface;

/**
 * Lead detector — determines if a conversation has produced a lead
 * by inspecting the parsed OpenAI JSON response.
 *
 * Pattern: "Gate Lead → Telegram" and "Normalize Output" nodes from bot.json.
 */
final class LeadDetector implements LeadDetectorInterface
{
    /**
     * Confidence threshold above which a lead is considered "high confidence".
     */
    private const float HIGH_CONFIDENCE_THRESHOLD = 0.7;

    /**
     * @param Config|null $config  Bot configuration (optional, for future use).
     * @param LoggerInterface|null $logger  Logger (optional, for future use).
     */
    public function __construct(
        private ?Config $config = null,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Determine whether the OpenAI response indicates a lead.
     *
     * A lead is detected when:
     *  - lead_detected is explicitly true in the LLM response (PRIMARY), OR
     *  - lead_confidence >= 0.95 AND eta_from_user_flag is set (user explicitly stated ETA)
     *
     * ⚠️ The old fallback (eta_minutes > 0 && confidence >= 0.7) was removed because
     * it caused massive false positives: the LLM infers eta_minutes from the bot's
     * own questions ("cuanto tardas?") instead of from genuine user statements.
     *
     * @param array<string, mixed> $openAiResponse  Parsed OpenAI JSON response.
     * @return bool
     */
    public function isLead(array $openAiResponse): bool
    {
        // Primary flag: explicit lead_detected boolean from LLM
        $leadDetected = !empty($openAiResponse['lead_detected']);

        if ($leadDetected) {
            return true;
        }

        // Secondary (strict): only if user explicitly gave ETA AND confidence >= 0.95
        $etaFromUser = !empty($openAiResponse['eta_from_user_flag']);
        $conf = $this->confidence($openAiResponse);

        if ($etaFromUser && $conf >= 0.95) {
            return true;
        }

        return false;
    }

    /**
     * Extract lead confidence from the parsed response.
     *
     * @param array<string, mixed> $openAiResponse
     * @return float  0.0 to 1.0 confidence.
     */
    public function confidence(array $openAiResponse): float
    {
        if (!isset($openAiResponse['lead_confidence'])) {
            return 0.0;
        }

        $v = (float) $openAiResponse['lead_confidence'];

        if (!is_finite($v)) {
            return 0.0;
        }

        return max(0.0, min(1.0, $v));
    }

    /**
     * Extract ETA in minutes from the parsed response.
     *
     * @param array<string, mixed> $openAiResponse
     * @return int  ETA in minutes, 0 if not specified.
     */
    public function etaMinutes(array $openAiResponse): int
    {
        if (!isset($openAiResponse['eta_minutes'])) {
            return 0;
        }

        $v = $openAiResponse['eta_minutes'];

        if ($v === null || $v === '') {
            return 0;
        }

        $n = (int) $v;

        return ($n > 0) ? $n : 0;
    }
}
