<?php

declare(strict_types=1);

namespace WasapBot\SideEffects;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\LoggerInterface;

/**
 * Tracks which personality styles lead to better conversions.
 *
 * Data is written to personality_scores.ndjson. The learning cron
 * (learn.php) aggregates this data to adjust personality weights,
 * making the bot progressively favor styles that convert better.
 *
 * Personality styles (from message_variants.personality_styles):
 *   cariñosa   — Dulce, usa "cari", "amor", "papi"
 *   pícara     — Provocativa, insinuante, doble sentido
 *   directa    — Al grano, frases cortas, profesional
 *   tímida     — Reservada, monosílabos, se hace la dura
 */
final class PersonalityTracker
{
    private string $trackingFile;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
    ) {
        $root = defined('WASAPBOT_ROOT') ? WASAPBOT_ROOT : dirname(__DIR__, 2);
        $rel  = (string) $this->config->get('files.personality_scores', 'data/personality_scores.ndjson');
        $this->trackingFile = str_starts_with($rel, '/') ? $rel : $root . '/' . ltrim($rel, '/');
    }

    /**
     * Record a personality-outcome pair at the end of a conversation.
     *
     * @param string $personality  Style name (cariñosa, pícara, directa, tímida)
     * @param string $outcome      lead_probable, lead_ghosted, muerta, mareador, hostil, indeterminado
     * @param string $threadId     Conversation thread ID
     */
    public function trackOutcome(string $personality, string $outcome, string $threadId): void
    {
        if ($personality === '') return;

        // Map outcome to conversion score
        $conversionScore = match ($outcome) {
            'lead_probable' => 1.0,
            'lead_ghosted'  => 0.3,  // Almost a lead
            'muerta'        => -1.0,
            'mareador'      => -0.5,
            'hostil'        => -2.0,
            default         => 0.0,  // indeterminado
        };

        $record = [
            'ts'               => gmdate('Y-m-d\TH:i:s\Z'),
            'personality'      => $personality,
            'outcome'          => $outcome,
            'conversion_score' => $conversionScore,
            'thread_id'        => $threadId,
        ];

        $this->append($record);

        if ($this->logger !== null) {
            $this->logger->debug("PersonalityTracker: {$personality} → {$outcome} (score: {$conversionScore})");
        }
    }

    /**
     * Calculate weighted personality scores from accumulated tracking data.
     *
     * Returns an associative array of style → weight, where higher
     * weights mean better conversion.  Default weight is 1.0 for any
     * style with no data.
     *
     * @return array<string, float>
     */
    public function getWeights(): array
    {
        $defaultWeights = [
            'cariñosa' => 1.0,
            'pícara'   => 1.0,
            'directa'  => 1.0,
            'tímida'   => 1.0,
        ];

        $lines = $this->readLines();
        if ($lines === []) return $defaultWeights;

        $totals = [];  // style → [sum, count]
        foreach ($lines as $line) {
            $rec = json_decode($line, true);
            if (!is_array($rec)) continue;

            $style  = (string) ($rec['personality'] ?? '');
            $score  = (float) ($rec['conversion_score'] ?? 0);
            if ($style === '') continue;

            if (!isset($totals[$style])) {
                $totals[$style] = ['sum' => 0.0, 'count' => 0];
            }
            $totals[$style]['sum']   += $score;
            $totals[$style]['count'] += 1;
        }

        $weights = $defaultWeights;
        foreach ($totals as $style => $data) {
            if ($data['count'] > 0) {
                // Weight = 1.0 + average score, clamped to [0.1, 3.0]
                $avg = $data['sum'] / $data['count'];
                $weights[$style] = max(0.1, min(3.0, 1.0 + $avg));
            }
        }

        return $weights;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Private helpers
    // ─────────────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function readLines(): array
    {
        if (!file_exists($this->trackingFile)) return [];
        $content = @file_get_contents($this->trackingFile);
        if ($content === false || $content === '') return [];
        return array_filter(explode("\n", trim($content)), static fn(string $l) => trim($l) !== '');
    }

    /** @param array<string, mixed> $record */
    private function append(array $record): void
    {
        $dir = dirname($this->trackingFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $json = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) return;
        @file_put_contents($this->trackingFile, $json . "\n", FILE_APPEND | LOCK_EX);
    }
}
