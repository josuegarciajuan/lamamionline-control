<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

use WasapBot\Core\ConfigInterface;

/**
 * Prevents the bot from repeating the same content.
 *
 * Three-priority guard:
 *
 *  Priority 1 – ya_enviado category match
 *    If $ctx['ya_enviado'] already contains a category (fotos, ubicacion,
 *    precios…) AND the current output_text is trying to send that same type
 *    of content again, replace with a natural human "ya te lo mandé" variant.
 *
 *  Priority 2 – Exact / semantic duplicate
 *    If the normalized output_text matches last_bot_reply or any entry in
 *    recent_bot_replies_norm, rewrite with a humanised dedup variant.
 *
 *  Priority 3 – No duplicate
 *    Pass through unchanged.
 *
 * Pattern: node "DeDupe Reply (guard)" from bot.json
 */
final class DedupeReply implements PipelineStageInterface
{
    // ----------------------------------------------------------------
    // Inline fallback pools — used when config has no variants defined.
    // ----------------------------------------------------------------

    /** @var list<string> */
    private const ALREADY_SENT_PHOTOS = [
        'ya t las pase mas arriba amor 😘',
        'mira arriba que ya t las mande cari',
        'te las mande antes, scrollea arriba',
        'ya t las pase amor, mira un poco arriba',
        'ya mandé las fotos antes guapo',
    ];

    /** @var list<string> */
    private const ALREADY_SENT_LOCATION = [
        'ya t mande la ubi guapo, mira arriba 👆',
        'te mande el maps antes amor, busca arriba',
        'la ubicacion ya t la pase, scrollea un poco',
        'ya te mande el pin antes cari',
    ];

    /** @var list<string> */
    private const ALREADY_SENT_PRICES = [
        'ya t explique los precios antes amor',
        'los precios ya t los dije, mira arriba cari',
        'ya te conte las tarifas antes guapo',
    ];

    /** @var list<string> */
    private const DEDUP_PREFIXES_SHORT = [
        'mira,',
        'te repito rapido:',
        'por si acaso:',
        'te lo cuento de nuevo:',
        'oye,',
    ];

    /** @var list<string> */
    private const DEDUP_CLOSINGS = [
        'cualquier duda me dices',
        'dime si te cuadra',
        'me dices lo que necesitas',
        'espero que te sirva',
        'ya sabes, aqui estoy',
    ];

    // ----------------------------------------------------------------

    public function __construct(
        private readonly ConfigInterface $config,
    ) {}

    /**
     * @param  array<string, mixed> $ctx
     * @return array<string, mixed>|null
     */
    public function process(array $ctx): ?array
    {
        $outputText = $ctx['output_text'] ?? '';
        if (!is_string($outputText) || $outputText === '') {
            return $ctx;
        }

        // ----------------------------------------------------------------
        // PRIORITY 1 — ya_enviado category guard (with per-girl awareness)
        // ----------------------------------------------------------------
        $yaEnviado = $ctx['ya_enviado'] ?? [];
        $photosSentPerGirl = $ctx['photos_sent_per_girl'] ?? [];
        if (!is_array($yaEnviado)) {
            $yaEnviado = [];
        }

        $photoInsistCount = $ctx['photo_insist_count'] ?? 0;
        $photoAction      = $ctx['photo_action'] ?? 'none';

        $categoryVariant = $this->resolveCategoryVariant($outputText, $yaEnviado, $photosSentPerGirl, $photoInsistCount, $photoAction);

        if ($categoryVariant !== null) {
            $ctx['output_text']             = $categoryVariant;
            $ctx['__dedup_applied']         = true;
            $ctx['__dedup_reason']          = 'ya_enviado';
            return $ctx;
        }

        // ----------------------------------------------------------------
        // PRIORITY 2 — Exact / semantic duplicate guard
        // ----------------------------------------------------------------
        $norm = $this->normalize($outputText);

        $lastReply = $ctx['last_bot_reply'] ?? '';
        $lastNorm  = '';
        if (is_string($lastReply) && $lastReply !== '') {
            $lastNorm = $this->normalize($lastReply);
        }

        $recentNorm = $ctx['recent_bot_replies_norm'] ?? [];
        if (!is_array($recentNorm)) {
            $recentNorm = [];
        }

        $isDuplicate = false;

        // Exact match with last reply
        if ($lastNorm !== '' && $norm === $lastNorm) {
            $isDuplicate = true;
        }

        // Match against any recent reply
        if (!$isDuplicate && $recentNorm !== []) {
            foreach ($recentNorm as $rn) {
                if (is_string($rn) && $norm === $this->normalize($rn)) {
                    $isDuplicate = true;
                    break;
                }
            }
        }

        if (!$isDuplicate) {
            $ctx['__dedup_applied'] = false;
            return $ctx;
        }

        // Duplicate detected → humanised rewrite
        $rewritten = $this->rewriteHuman($outputText, $lastNorm, $recentNorm);

        $ctx['output_text']     = $rewritten;
        $ctx['__dedup_applied'] = true;
        $ctx['__dedup_reason']  = 'duplicate';

        return $ctx;
    }

    public function name(): string
    {
        return 'DedupeReply';
    }

    // ==================================================================
    // PRIORITY 1 — Category detection helpers
    // ==================================================================

    /**
     * Returns a human "ya te lo mandé" variant if the output_text is trying
     * to send content that is already in ya_enviado, or null if no match.
     *
     * NOVA: photos_sent_per_girl allows new photos (different girl) to pass through.
     * NOVA: photo_insist_count >= 2 means client insisted — cede, let photos through.
     *
     * @param  list<mixed> $yaEnviado
     * @param  list<string> $photosSentPerGirl
     */
    private function resolveCategoryVariant(string $outputText, array $yaEnviado, array $photosSentPerGirl = [], int $photoInsistCount = 0, string $photoAction = 'none'): ?string
    {
        if ($yaEnviado === []) {
            return null;
        }

        // Photos — smarter: check if output mentions a girl whose photos are NEW
        if (
            in_array('fotos', $yaEnviado, true)
            && $this->detectsPhotoUrls($outputText)
        ) {
            // If client insisted 2+ times → CEDE, let photos through
            if ($photoInsistCount >= 2) {
                return null; // Allow photos to go through
            }

            // ── LLM-DRIVEN: if the LLM explicitly set photo_action, trust it ──
            // The LLM knows the context (selected_girl, catalog vs full photos, etc.)
            // and has decided photos should be sent. Don't override with "ya te las mandé".
            if ($photoAction !== 'none') {
                return null; // LLM says send → allow through
            }

            // Legacy per-girl awareness (photos_sent_per_girl — rarely populated)
            $outputNorm = $this->normalize($outputText);
            if ($photosSentPerGirl !== []) {
                $foundKnownGirl = false;
                foreach ($photosSentPerGirl as $gn) {
                    if (str_contains($outputNorm, $gn)) {
                        $foundKnownGirl = true;
                        break;
                    }
                }
                if (!$foundKnownGirl) {
                    // No known girl in output — likely new/different girl photos → allow
                    return null;
                }
            }

            return $this->pickVariant(
                'message_variants.already_sent_photos',
                self::ALREADY_SENT_PHOTOS,
            );
        }

        // Location — only block if an actual maps URL was previously sent (ubicacion_precisa)
        if (
            in_array('ubicacion_precisa', $yaEnviado, true)
            && $this->detectsLocationContent($outputText)
        ) {
            // If client insisted 2+ times → CEDE, let location through
            if ($photoInsistCount >= 2) {
                return null;
            }
            return $this->pickVariant(
                'message_variants.already_sent_location',
                self::ALREADY_SENT_LOCATION,
            );
        }

        // Prices / rates
        if (
            in_array('precios', $yaEnviado, true)
            && $this->detectsPriceContent($outputText)
        ) {
            // If client insisted 2+ times → CEDE, let prices through
            if ($photoInsistCount >= 2) {
                return null;
            }
            return $this->pickVariant(
                'message_variants.already_sent_prices',
                self::ALREADY_SENT_PRICES,
            );
        }

        return null;
    }

    /**
     * Detect photo URLs (ibb.co, imgur, cloudinary, etc.) that are NOT maps.
     */
    private function detectsPhotoUrls(string $text): bool
    {
        // Must contain an image host URL
        $hasImageUrl = (bool) preg_match(
            '/https?:\/\/(?:i\.ibb\.co|ibb\.co|compartir\.site|i\.imgur\.com|imgur\.com|res\.cloudinary\.com|images\.unsplash\.com|cdn\.discordapp\.com)/i',
            $text,
        );

        if (!$hasImageUrl) {
            return false;
        }

        // Exclude if it is actually a maps URL (avoid collision)
        $hasMapsUrl = $this->detectsLocationContent($text);

        return !$hasMapsUrl;
    }

    /**
     * Detect location / maps content in the text.
     */
    private function detectsLocationContent(string $text): bool
    {
        // Maps URLs
        if (preg_match(
            '/(?:https?:\/\/)?(?:goo\.gl\/maps|maps\.app\.goo\.gl|google\.com\/maps|maps\.google\.com)/i',
            $text,
        )) {
            return true;
        }

        // Location keywords (Spanish / informal)
        return (bool) preg_match(
            '/\b(?:ubicaci[oó]n|ubicacion|direcci[oó]n|direccion|calle|mapa|maps|pin|punto\s+exacto|ubi\b|te\s+mando\s+(?:el\s+)?(?:maps|la\s+ubi)|te\s+paso\s+(?:el\s+)?(?:maps|la\s+ubi)|llegar|c[oó]mo\s+llegar)\b/iu',
            $text,
        );
    }

    /**
     * Detect price / rate content in the text.
     */
    private function detectsPriceContent(string $text): bool
    {
        return (bool) preg_match(
            '/\b(?:\d+\s*€|\d+\s*euros?|tarifa[s]?|precio[s]?|cuesta|vale\s+\d+|cob(?:ro|ramos)|coste)\b/iu',
            $text,
        );
    }

    // ==================================================================
    // PRIORITY 2 — Humanised rewrite helpers
    // ==================================================================

    /**
     * Rewrite a duplicate reply in a natural, non-robotic way.
     *
     * Short replies (< 50 chars): just prepend a varied prefix.
     * Long replies: wrap with prefix + closing, lowercased body.
     *
     * @param  list<mixed> $recentNorm
     */
    private function rewriteHuman(string $outputText, string $lastNorm, array $recentNorm): string
    {
        $trimmed  = trim($outputText);
        $isShort  = mb_strlen($trimmed, 'UTF-8') < 50;

        if ($isShort) {
            // Short: only add a varied prefix, keep original casing
            $prefix   = $this->pickVariant('message_variants.dedup_start', self::DEDUP_PREFIXES_SHORT);
            $rewritten = $prefix . ' ' . lcfirst($trimmed);
        } else {
            // Long: prefix + lowercase body + closing
            $prefix   = $this->pickVariant('message_variants.dedup_start', self::DEDUP_PREFIXES_SHORT);
            $closing  = $this->pickVariant('message_variants.dedup_end', self::DEDUP_CLOSINGS);
            $body     = mb_strtolower(rtrim($trimmed, '.'), 'UTF-8');
            $rewritten = $prefix . ' ' . $body . '. ' . $closing . '.';
        }

        // Last-resort: if the rewritten version STILL matches a recent reply,
        // append a small suffix so it is never 100% identical.
        $rewrittenNorm = $this->normalize($rewritten);
        if (
            ($lastNorm !== '' && $rewrittenNorm === $lastNorm)
            || in_array($rewrittenNorm, array_filter($recentNorm, 'is_string'), true)
        ) {
            $suffix    = (string) $this->config->get('message_variants.dedup_suffix', '😊');
            $rewritten = trim($rewritten) . ' ' . $suffix;
        }

        return $rewritten;
    }

    // ==================================================================
    // Shared helpers
    // ==================================================================

    /**
     * Pick a random variant from a config key or a hardcoded fallback pool.
     *
     * @param  list<string> $fallbackPool
     */
    private function pickVariant(string $configPath, array $fallbackPool): string
    {
        $variants = $this->config->get($configPath, []);
        if (is_array($variants) && $variants !== []) {
            return (string) $variants[array_rand($variants)];
        }

        // Use the inline fallback pool
        if ($fallbackPool !== []) {
            return $fallbackPool[array_rand($fallbackPool)];
        }

        return '';
    }

    /**
     * Normalise a string for semantic comparison:
     * lowercase → strip punctuation → collapse whitespace.
     */
    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = (string) preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);
        $text = (string) preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
