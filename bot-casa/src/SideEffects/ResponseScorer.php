<?php

declare(strict_types=1);

namespace WasapBot\SideEffects;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\LoggerInterface;
use WasapBot\Memory\SessionMemoryInterface;

/**
 * Scores bot responses to track which replies lead to continuation,
 * ghosting, or repeated questions.
 *
 * Data is written to response_scores.ndjson for ingestion by the
 * learning cron (learn.php) to improve the playbook over time.
 *
 * Scoring model (per reply, evaluated on the NEXT message):
 *   +1.0  → client continued with meaningful question
 *   +0.5  → client sent filler (ok, vale, si) but stayed in conversation
 *   -0.5  → client repeated the same question (bot didn't understand)
 *   -1.0  → client ghosted (no response in 6h)
 *   -2.0  → client said goodbye / rejected explicitly
 */
final class ResponseScorer
{
    /** NDJSON file for score records. */
    private string $scoresFile;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
        private readonly SessionMemoryInterface $sessionMemory,
    ) {
        $root = defined('WASAPBOT_ROOT') ? WASAPBOT_ROOT : dirname(__DIR__, 2);
        $rel  = (string) $this->config->get('files.response_scores', 'data/response_scores.ndjson');
        $this->scoresFile = str_starts_with($rel, '/') ? $rel : $root . '/' . ltrim($rel, '/');
    }

    /**
     * Score the previous bot reply based on what the client did next.
     *
     * Called at the START of pipeline processing — looks at the
     * PREVIOUS bot reply and scores it based on the current user message.
     *
     * @param array<string, mixed> $ctx  Current pipeline context.
     */
    public function scorePreviousReply(array $ctx): void
    {
        $threadId = (string) ($ctx['thread_id'] ?? '');
        if ($threadId === '') return;

        try {
            $history = $this->sessionMemory->readThread($threadId);
        } catch (\Throwable) {
            return;
        }

        if (count($history) < 2) return; // Need at least 1 bot reply to score

        // Find the most recent bot reply with content
        $lastBotReply = '';
        $lastBotIdx   = -1;
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $botReply = trim((string) ($history[$i]['bot_reply'] ?? ''));
            if ($botReply !== '') {
                $lastBotReply = $botReply;
                $lastBotIdx   = $i;
                break;
            }
        }
        if ($lastBotReply === '') return;

        // Current user message
        $currentUserMsg = trim((string) ($ctx['message_text'] ?? ''));
        if ($currentUserMsg === '') return;

        // Compute score
        $score     = 0.0;
        $scoreType = 'unknown';

        // Check if client explicitly rejected or said goodbye
        if ($this->isRejection($currentUserMsg)) {
            $score     = -2.0;
            $scoreType = 'rejection';
        } elseif ($this->isRepeatedQuestion($currentUserMsg, $history, $lastBotIdx)) {
            $score     = -0.5;
            $scoreType = 'repeated_question';
        } elseif ($this->isMeaningfulContinuation($currentUserMsg)) {
            $score     = 1.0;
            $scoreType = 'continuation';
        } elseif ($this->isFiller($currentUserMsg)) {
            $score     = 0.5;
            $scoreType = 'filler';
        } else {
            $score     = 0.0;
            $scoreType = 'neutral';
        }

        // Write score record
        $record = [
            'ts'              => gmdate('Y-m-d\TH:i:s\Z'),
            'thread_id'       => $threadId,
            'phone'           => (string) ($ctx['from_phone'] ?? ''),
            'bot_reply_hash'  => md5($lastBotReply),
            'bot_reply_preview' => mb_substr($lastBotReply, 0, 60),
            'score'           => $score,
            'score_type'      => $scoreType,
            'personality'     => (string) ($ctx['__personality_style'] ?? ''),
            'conversation_state' => (string) ($ctx['__conversation_state'] ?? ''),
        ];

        $this->appendRecord($record);
    }

    /**
     * Score a reply that was never answered (ghosted).
     *
     * Called by the classify_outcomes cron or when the conversation
     * times out — scores the last bot reply at -1.0.
     */
    public function scoreGhosted(string $threadId, string $phone, string $botReply, string $personality = ''): void
    {
        if ($botReply === '') return;

        $record = [
            'ts'                => gmdate('Y-m-d\TH:i:s\Z'),
            'thread_id'         => $threadId,
            'phone'             => $phone,
            'bot_reply_hash'    => md5($botReply),
            'bot_reply_preview' => mb_substr($botReply, 0, 60),
            'score'             => -1.0,
            'score_type'        => 'ghosted',
            'personality'       => $personality,
            'conversation_state' => '',
        ];

        $this->appendRecord($record);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Private helpers
    // ─────────────────────────────────────────────────────────────────

    private function isRejection(string $text): bool
    {
        return (bool) preg_match(
            '/\b(adios|chao|bye|no\s+me\s+interesa|no\s+gracias|me\s+despido|para\s+ya|deja\s+de\s+escribir|no\s+es\s+lo\s+que\s+busco|no\s+quiero\s+nada)\b/iu',
            $text
        );
    }

    /**
     * Check if the current user message is essentially repeating
     * a question that was asked before the last bot reply.
     */
    private function isRepeatedQuestion(string $currentMsg, array $history, int $lastBotIdx): bool
    {
        // Find the last user message before the bot replied
        $prevUserMsg = '';
        for ($i = $lastBotIdx - 1; $i >= 0; $i--) {
            $um = trim((string) ($history[$i]['user_msg'] ?? ''));
            if ($um !== '') {
                $prevUserMsg = $um;
                break;
            }
        }
        if ($prevUserMsg === '') return false;

        // Normalize and compare
        $norm1 = $this->normalize($currentMsg);
        $norm2 = $this->normalize($prevUserMsg);

        // Exact match or high similarity
        if ($norm1 === $norm2) return true;

        similar_text($norm1, $norm2, $pct);
        return $pct > 70;
    }

    private function isMeaningfulContinuation(string $text): bool
    {
        return (bool) preg_match(
            '/\b(precio|tarifa|cu[aá]nto|ubicaci[oó]n|donde\s+est|maps?|mapa|fotos?|ver|ense[ñn]|'
            . 'chicas?|cual|nombre|servicio|disponible|cu[aá]ndo|voy|llego|tardo\s+\d+|'
            . 'me\s+gusta|quiero\s+a\b|elige|prefiero|me\s+quedo|la\s+que)\b/iu',
            $text
        ) && mb_strlen($text) > 5;
    }

    private function isFiller(string $text): bool
    {
        $t = trim(mb_strtolower($text, 'UTF-8'));
        return mb_strlen($t) <= 4
            || preg_match('/^(ok|oki|okey|vale|vle|si|sip|yas|da|dale|jj+|jaja|jeje)$/iu', $t);
    }

    private function normalize(string $text): string
    {
        $t = mb_strtolower(trim($text), 'UTF-8');
        // Remove punctuation and extra spaces
        $t = preg_replace('/[^\w\sáéíóúüñ]/u', '', $t);
        return trim((string) preg_replace('/\s+/', ' ', $t));
    }

    private function appendRecord(array $record): void
    {
        $dir = dirname($this->scoresFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) return;

        @file_put_contents($this->scoresFile, $json . "\n", FILE_APPEND | LOCK_EX);
    }
}
