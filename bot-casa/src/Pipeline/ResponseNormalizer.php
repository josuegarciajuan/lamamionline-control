<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

/**
 * Parses the raw OpenAI JSON response and extracts structured fields.
 *
 * Pattern: node "Normalize Output" from bot.json
 */
final class ResponseNormalizer implements PipelineStageInterface
{
    public function process(array $ctx): ?array
    {
        $rawResponse = $ctx['openai_raw_response'] ?? null;

        if (!is_array($rawResponse) && !is_string($rawResponse)) {
            // No response to normalize; pass through
            $ctx['output_text'] = '';
            $ctx['lead_detected'] = false;
            $ctx['lead_confidence']  = 0.0;
            $ctx['lead_numeric']     = 0;
            $ctx['lead_flag']        = '0';
            $ctx['eta_minutes']      = 0;
            return $ctx;
        }

        // If string, decode JSON
        if (is_string($rawResponse)) {
            $parsed = json_decode($rawResponse, true);
            if (!is_array($parsed)) {
                // Could not parse as JSON; use raw content as reply
                $ctx['output_text']      = $rawResponse;
                $ctx['lead_detected']    = false;
                $ctx['lead_confidence']  = 0.0;
                $ctx['lead_numeric']     = 0;
                $ctx['lead_flag']        = '0';
                $ctx['eta_minutes']      = 0;
                return $ctx;
            }
            $rawResponse = $parsed;
        }

        // ── Case A: already-parsed inner object from OpenAiClient::chat()
        // OpenAiClient returns the parsed content directly (e.g. ['user_visible_reply' => '...'])
        // Detect this by presence of 'user_visible_reply' OR absence of 'choices'.
        if (is_array($rawResponse) && !isset($rawResponse['choices'])) {
            $inner = $rawResponse;
            $content = null; // handled below via $inner
        } else {
            // ── Case B: raw OpenAI API structure with choices[] (legacy / future use)
            // Extract choices[0].message.content
            $content = null;
            if (is_array($rawResponse) && isset($rawResponse['choices']) && is_array($rawResponse['choices'])) {
                $firstChoice = $rawResponse['choices'][0] ?? null;
                if (is_array($firstChoice) && isset($firstChoice['message']) && is_array($firstChoice['message'])) {
                    $content = $firstChoice['message']['content'] ?? null;
                }
            }

            if ($content === null || !is_string($content) || $content === '') {
                $ctx['output_text']      = '';
                $ctx['lead_detected']    = false;
                $ctx['lead_confidence']  = 0.0;
                $ctx['lead_numeric']     = 0;
                $ctx['lead_flag']        = '0';
                $ctx['eta_minutes']      = 0;
                return $ctx;
            }

            $inner = json_decode($content, true);
            if (!is_array($inner)) {
                $ctx['output_text'] = trim($content);
                $ctx['lead_detected']   = false;
                $ctx['lead_confidence'] = 0.0;
                $ctx['lead_numeric']    = 0;
                $ctx['lead_flag']       = '0';
                $ctx['eta_minutes']     = 0;
                return $ctx;
            }
        }

        $userVisibleReply = null;

        if (is_array($inner)) {
            $userVisibleReply = $inner['user_visible_reply'] ?? null;

            // --- lead_detected ---
            $leadDetected = $inner['lead_detected'] ?? null;
            if ($leadDetected !== null) {
                $leadDetected = filter_var($leadDetected, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            } else {
                $leadDetected = false;
            }
            $ctx['lead_detected'] = $leadDetected;

            // --- lead_confidence (0-1 float) ---
            $leadConf = $inner['lead_confidence'] ?? null;
            if (is_numeric($leadConf)) {
                $leadConf = (float) $leadConf;
                $leadConf = max(0.0, min(1.0, $leadConf));
            } else {
                $leadConf = 0.0;
            }
            $ctx['lead_confidence'] = $leadConf;

            // Numeric and flag representations
            $ctx['lead_numeric'] = $leadDetected ? 1 : 0;
            $ctx['lead_flag']    = $leadDetected ? '1' : '0';

            // --- eta_minutes ---
            $etaMinutes = $inner['eta_minutes'] ?? null;
            if (is_numeric($etaMinutes)) {
                $ctx['eta_minutes'] = (int) $etaMinutes;
            } else {
                $ctx['eta_minutes'] = 0;
            }

            // --- photo_action (LLM decides when to send photos) ---
            $photoAction = $inner['photo_action'] ?? 'none';
            $validActions = ['none', 'catalog', 'selected_all'];
            $ctx['photo_action'] = is_string($photoAction) && in_array($photoAction, $validActions, true)
                ? $photoAction
                : 'none';

            // --- lead_signals (LLM explains why it thinks it's a lead) ---
            $leadSignals = $inner['lead_signals'] ?? null;
            $validSignals = [
                'eta_explicit', 'eta_implicit', 'coming_soon', 'selected_girl',
                'maps_requested', 'maps_sent', 'price_asked', 'urgent_tone',
                'recurring_client', 'coordination_phase', 'none',
            ];
            if (is_array($leadSignals)) {
                $ctx['lead_signals'] = array_values(array_filter(
                    array_map('strval', $leadSignals),
                    static fn(string $s) => in_array($s, $validSignals, true)
                ));
            } else {
                $ctx['lead_signals'] = ['none'];
            }

            // --- selected_girl (determinista: ContextAssembler ya lo resolvió) ---
            // Solo usamos OpenAI como fallback si ContextAssembler no lo detectó
            if (empty($ctx['selected_girl_id']) && isset($inner['selected_girl_id']) && $inner['selected_girl_id'] !== null) {
                $ctx['selected_girl_id'] = (string) $inner['selected_girl_id'];
            }
            if (empty($ctx['selected_girl_name']) && !empty($inner['selected_girl_name'])) {
                $ctx['selected_girl_name'] = (string) $inner['selected_girl_name'];
            }

            // --- speaker_girl (determinista: ContextAssembler ya lo resolvió) ---
            if (empty($ctx['speaker_girl_id']) && isset($inner['speaker_girl_id']) && $inner['speaker_girl_id'] !== null) {
                $ctx['speaker_girl_id'] = (string) $inner['speaker_girl_id'];
            }
            if (empty($ctx['speaker_girl_name']) && !empty($inner['speaker_girl_name'])) {
                $ctx['speaker_girl_name'] = (string) $inner['speaker_girl_name'];
            }
            if (empty($ctx['speaker_mode']) && !empty($inner['speaker_mode'])) {
                $ctx['speaker_mode'] = (string) $inner['speaker_mode'];
            }

            // --- wants_more_girls ---
            if (isset($inner['wants_more_girls'])) {
                $ctx['wants_more_girls'] = filter_var($inner['wants_more_girls'], FILTER_VALIDATE_BOOLEAN);
            }

            // --- shown_girls / unshown_girls ---
            if (isset($inner['shown_girls']) && is_array($inner['shown_girls'])) {
                $ctx['__response_shown_girls'] = $inner['shown_girls'];
            }
            if (isset($inner['unshown_girls']) && is_array($inner['unshown_girls'])) {
                $ctx['__response_unshown_girls'] = $inner['unshown_girls'];
            }

            // --- ya_enviado (already sent) ---
            if (isset($inner['ya_enviado'])) {
                $ctx['ya_enviado'] = filter_var($inner['ya_enviado'], FILTER_VALIDATE_BOOLEAN);
            }
        }

        // Fallback: if we couldn't parse JSON, use raw content
        if ($userVisibleReply === null || $userVisibleReply === '') {
            $userVisibleReply = $content;
        }

        // Clean up — remove redundant newlines and trim, but preserve paragraphs
        $userVisibleReply = preg_replace('/\n{3,}/', "\n\n", (string) $userVisibleReply);
        $userVisibleReply = trim((string) $userVisibleReply);

        // ── Defensive: if reply looks like leaked JSON, extract the inner text ──
        if ($userVisibleReply !== '' && str_starts_with($userVisibleReply, '{')) {
            try {
                $inner = json_decode($userVisibleReply, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($inner) && !empty($inner['user_visible_reply'])) {
                    $userVisibleReply = (string) $inner['user_visible_reply'];
                }
            } catch (\JsonException $e) {
                // Not JSON, leave as-is
            }
        }

        $ctx['output_text'] = $userVisibleReply;

        return $ctx;
    }

    public function name(): string
    {
        return 'ResponseNormalizer';
    }
}
