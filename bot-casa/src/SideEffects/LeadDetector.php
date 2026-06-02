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
    private const float HIGH_CONFIDENCE_THRESHOLD = 0.5;

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
     *  - lead_detected is truthy, OR
     *  - There is an ETA (eta_minutes > 0) AND confidence is high.
     *
     * @param array<string, mixed> $openAiResponse  Parsed OpenAI JSON response.
     * @return bool
     */
    public function isLead(array $openAiResponse): bool
    {
        // Primary flag: explicit lead_detected boolean
        $leadDetected = !empty($openAiResponse['lead_detected']);

        if ($leadDetected) {
            return true;
        }

        // Secondary: ETA > 0 combined with high confidence
        $eta = $this->etaMinutes($openAiResponse);
        $conf = $this->confidence($openAiResponse);

        if ($eta > 0 && $conf >= self::HIGH_CONFIDENCE_THRESHOLD) {
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
