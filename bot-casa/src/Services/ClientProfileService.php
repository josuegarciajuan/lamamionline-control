<?php

declare(strict_types=1);

namespace WasapBot\Services;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\LoggerInterface;

/**
 * Client Profile Service — per-phone long-term memory.
 *
 * Stores and retrieves conversation history profiles for each phone number,
 * enabling the bot to remember past interactions and adapt its behavior.
 *
 * Data is stored in client_profiles.ndjson (one JSON object per line).
 */
final class ClientProfileService
{
    /** @var array<string, array> In-memory cache of profiles indexed by phone */
    private array $cache = [];

    /** @var string Absolute path to the profiles file */
    private readonly string $profilesFile;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $rootDir = defined('WASAPBOT_ROOT') ? WASAPBOT_ROOT : dirname(__DIR__, 2);
        $relativePath = $this->config->get('files.client_profiles', 'public/data/client_profiles.ndjson');
        $this->profilesFile = $rootDir . '/' . ltrim((string) $relativePath, '/');

        // Ensure directory exists
        $dir = dirname($this->profilesFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    /**
     * Get the profile for a phone number.
     *
     * Returns null if the phone has no prior conversations.
     */
    public function getProfile(string $phone): ?array
    {
        $phone = $this->normalizePhone($phone);
        if ($phone === '') return null;

        if (isset($this->cache[$phone])) {
            return $this->cache[$phone];
        }

        // Load all profiles (or use a fast lookup)
        $this->loadIntoCache();
        return $this->cache[$phone] ?? null;
    }

    /**
     * Build a human-readable warning string for the LLM context.
     *
     * Returns empty string if the client is unknown or has a clean record.
     */
    public function getClientContextHint(string $phone): string
    {
        $profile = $this->getProfile($phone);
        if ($profile === null) {
            return '';
        }

        $total      = (int) ($profile['total_conversations'] ?? 0);
        $outcomes   = (array) ($profile['outcomes'] ?? []);
        $lastOutcome = (string) ($profile['last_outcome'] ?? '');
        $dominant   = (string) ($profile['dominant_pattern'] ?? '');
        $tags       = (array) ($profile['tags'] ?? []);

        $hints = [];

        // Known client intro
        if ($total > 0) {
            $hints[] = "Cliente conocido: {$total} conversación(es) previa(s).";
        }

        // Outcome patterns
        $ghosted = (int) ($outcomes['lead_ghosted'] ?? 0);
        $confirmed = (int) ($outcomes['lead_confirmado'] ?? 0);
        $mareador = (int) ($outcomes['mareador'] ?? 0);
        $hostil   = (int) ($outcomes['hostil'] ?? 0);

        if ($confirmed > 0) {
            $hints[] = "Ha venido {$confirmed} vez/veces antes. Es cliente recurrente. Trátale con calidez y familiaridad.";
        }

        if ($ghosted > 0) {
            $hints[] = "Ya dio ETA y ghosteó {$ghosted} vez/veces. Sé directa y no te enrolles. Pregunta ETA pronto.";
        }

        if ($mareador > 0 && $confirmed === 0) {
            $hints[] = "Historial de mareo. Conversaciones largas sin resultado. Ve al grano, no alimentes charla innecesaria.";
        }

        if ($hostil > 0) {
            $hints[] = "Ha sido hostil/agresivo antes. Si vuelve a serlo, corta rápido.";
        }

        // Dominant pattern hint
        if ($dominant === 'mareador' && $confirmed === 0) {
            $hints[] = "PATRÓN DOMINANTE: mareador. Alta probabilidad de que esta conversación tampoco llegue a nada. No inviertas mucha energía.";
        }

        // Specific tag hints
        if (in_array('pide_mapa_sin_chica', $tags, true)) {
            $hints[] = "En el pasado pidió ubicación repetidamente sin elegir chica. Si lo vuelve a hacer, sé firme: 'cada chica atiende de forma independiente, dime cuál te gusta y te paso dirección'.";
        }

        if (in_array('pide_telefono_personal', $tags, true)) {
            $hints[] = "Intentó conseguir teléfono personal antes. No se lo des.";
        }

        if (in_array('no_quiere_pagar', $tags, true)) {
            $hints[] = "Intentó no pagar o regateó fuerte. Precios firmes, sin descuentos.";
        }

        return implode(' ', $hints);
    }

    /**
     * Update a client's profile after a conversation outcome is classified.
     *
     * @param string $phone        Client's phone number
     * @param string $outcome      One of: lead_confirmado, lead_ghosted, mareador, hostil, muerta, etc.
     * @param array  $tags         Pattern tags detected in this conversation
     * @param string $selectedGirl Last selected girl name (if any)
     */
    public function updateProfile(string $phone, string $outcome, array $tags = [], string $selectedGirl = ''): void
    {
        $phone = $this->normalizePhone($phone);
        if ($phone === '') return;

        $profile = $this->getProfile($phone);

        if ($profile === null) {
            $profile = [
                'phone'              => $phone,
                'first_seen'         => date('c'),
                'last_seen'          => date('c'),
                'total_conversations'=> 0,
                'outcomes'           => [
                    'lead_confirmado' => 0,
                    'lead_probable'   => 0,
                    'lead_ghosted'    => 0,
                    'mareador'        => 0,
                    'hostil'          => 0,
                    'muerta'          => 0,
                    'indeterminado'   => 0,
                ],
                'dominant_pattern'   => 'nuevo',
                'tags'               => [],
                'last_selected_girl' => '',
            ];
        }

        // Update counters
        $profile['total_conversations'] = ((int) ($profile['total_conversations'] ?? 0)) + 1;
        $profile['last_seen'] = date('c');

        if (isset($profile['outcomes'][$outcome])) {
            $profile['outcomes'][$outcome] = ((int) ($profile['outcomes'][$outcome])) + 1;
        } elseif ($outcome !== '') {
            $profile['outcomes'][$outcome] = 1;
        }

        $profile['last_outcome'] = $outcome;

        // Merge tags (keep unique)
        $existingTags = (array) ($profile['tags'] ?? []);
        $profile['tags'] = array_values(array_unique(array_merge($existingTags, $tags)));

        // Update dominant pattern
        $profile['dominant_pattern'] = $this->computeDominantPattern($profile['outcomes']);

        // Update selected girl if provided
        if ($selectedGirl !== '') {
            $profile['last_selected_girl'] = $selectedGirl;
        }

        // Write to file
        $this->cache[$phone] = $profile;
        $this->persist($phone, $profile);
    }

    /**
     * Sync profiles from conversation outcomes file.
     *
     * Called by the classify cron after new outcomes are written.
     * Updates all profiles whose outcomes have changed.
     *
     * @param array $newOutcomes  Array of outcome records keyed by thread_id
     */
    public function syncFromOutcomes(array $newOutcomes): int
    {
        $updated = 0;
        foreach ($newOutcomes as $outcome) {
            $phone  = (string) ($outcome['phone'] ?? '');
            $result = (string) ($outcome['outcome'] ?? '');
            $tags   = (array) ($outcome['tags'] ?? []);
            $girl   = (string) ($outcome['selected_girl'] ?? '');

            if ($phone === '' || $result === '') continue;

            $this->updateProfile($phone, $result, $tags, $girl);
            $updated++;
        }

        $this->logger?->info("ClientProfileService::syncFromOutcomes — updated {$updated} profiles");
        return $updated;
    }

    /**
     * Get all known profiles (for admin panel display).
     *
     * @return list<array>
     */
    public function getAllProfiles(): array
    {
        $this->loadIntoCache();
        return array_values($this->cache);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    private function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        // Strip @c.us suffix if present
        if (str_ends_with($phone, '@c.us')) {
            $phone = substr($phone, 0, -4);
        }
        // Only keep digits and leading +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        return $phone;
    }

    private function loadIntoCache(): void
    {
        if (!file_exists($this->profilesFile)) {
            return;
        }

        $lines = @file($this->profilesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return;

        foreach ($lines as $line) {
            $rec = json_decode(trim($line), true);
            if (is_array($rec) && !empty($rec['phone'])) {
                $phone = $this->normalizePhone((string) $rec['phone']);
                if ($phone !== '') {
                    $this->cache[$phone] = $rec;
                }
            }
        }
    }

    private function persist(string $phone, array $profile): void
    {
        // Read all lines, update or append
        $lines = [];
        $found = false;

        if (file_exists($this->profilesFile)) {
            $raw = @file($this->profilesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($raw !== false) {
                foreach ($raw as $line) {
                    $rec = json_decode(trim($line), true);
                    if (is_array($rec) && !empty($rec['phone'])) {
                        $p = $this->normalizePhone((string) $rec['phone']);
                        if ($p === $phone) {
                            $lines[] = json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            $found = true;
                        } else {
                            $lines[] = $line;
                        }
                    }
                }
            }
        }

        if (!$found) {
            $lines[] = json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $content = implode("\n", array_filter($lines, fn($l) => $l !== '')) . "\n";
        @file_put_contents($this->profilesFile, $content, LOCK_EX);
    }

    /**
     * Determine the dominant behavior pattern from outcome counts.
     */
    private function computeDominantPattern(array $outcomes): string
    {
        $mareador  = (int) ($outcomes['mareador'] ?? 0);
        $ghosted   = (int) ($outcomes['lead_ghosted'] ?? 0);
        $confirmed = (int) ($outcomes['lead_confirmado'] ?? 0);
        $hostil    = (int) ($outcomes['hostil'] ?? 0);
        $total     = $mareador + $ghosted + $confirmed + $hostil
                   + (int) ($outcomes['lead_probable'] ?? 0)
                   + (int) ($outcomes['muerta'] ?? 0)
                   + (int) ($outcomes['indeterminado'] ?? 0);

        if ($total === 0) return 'nuevo';

        if ($confirmed >= $total * 0.5) return 'buen_cliente';
        if ($hostil > 0) return 'hostil';
        if ($mareador >= $total * 0.6) return 'mareador';
        if ($ghosted >= $total * 0.5) return 'ghoster';
        if ($mareador > $confirmed) return 'mareador';
        if ($ghosted > $confirmed) return 'ghoster';

        return 'mixto';
    }
}
