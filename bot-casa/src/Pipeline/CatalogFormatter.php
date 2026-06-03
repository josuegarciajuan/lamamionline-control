<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

use WasapBot\Core\ConfigInterface;

/**
 * Formats the girls catalog with photo URLs for the reply.
 *
 * Selection logic (in priority order):
 *
 * 1. selected_girl known + asks_friends → 1 random photo of EACH active girl
 * 2. selected_girl known + asks photos  → ALL photos of the selected girl
 * 3. selected_girl known + switches to another girl by name → ALL photos of that new girl
 * 4. No selected_girl + asks photos     → 1 random photo of EACH active girl
 * 5. wants_more_girls (explicit)        → same as case 4 (1 per girl, all active)
 *
 * Pattern: node "Post-Format Catalog (hard enforce)" from bot.json
 */
final class CatalogFormatter implements PipelineStageInterface
{
    public function __construct(
        private readonly ConfigInterface $config,
    ) {}

    public function process(array $ctx): ?array
    {
        $girlsConfig = $ctx['girls_config'] ?? [];
        $sentUrls    = $ctx['sent_photo_urls'] ?? [];
        if (!is_array($girlsConfig) || $girlsConfig === []) {
            return $ctx;
        }

        $outputText = $ctx['output_text'] ?? '';
        if (!is_string($outputText) || $outputText === '') {
            return $ctx;
        }

        $messageText     = (string) ($ctx['message_text'] ?? '');
        $normalizedInput = mb_strtolower(trim($messageText), 'UTF-8');

        // ── LLM-DRIVEN PHOTO TRIGGER (primary) ────────────────────────────
        // The LLM now sets photo_action in its JSON response to tell the system
        // what kind of photo delivery is needed. This replaces regex-only detection.
        $photoAction = $ctx['photo_action'] ?? 'none';
        $llmWantsPhotos = $photoAction !== 'none';

        // --- LLM-driven mode: use photo_action directly ---
        if ($llmWantsPhotos) {
            // Bypass regex detection entirely — the LLM knows best
            $mentionsGirls  = true;
            $mentionsFriends = ($photoAction === 'catalog');
            $wantsMore      = ($photoAction === 'catalog');
        } else {
            // --- Fallback: regex-based detection (legacy safety net) ---
            $mentionsGirls  = $this->detectsGirls($normalizedInput);
            $mentionsFriends = $this->detectsFriends($normalizedInput);
            $wantsMore      = !empty($ctx['wants_more_girls']) || $mentionsFriends;

            if (!$mentionsGirls && !$wantsMore) {
                $mentionsGirls = !empty($ctx['wants_more_girls']);
            }
        }

        // If no mention of girls and output doesn't already have photo links, skip
        if (!$mentionsGirls && !$wantsMore && !$this->containsPhotoUrls($outputText)) {
            return $ctx;
        }

        // --- Resolve selected girl ---
        $selectedGirlName = (string) ($ctx['selected_girl_name'] ?? '');
        $selectedGirl     = $this->findGirlByName($girlsConfig, $selectedGirlName);

        // --- Detect if user asks for MORE photos of selected girl ---
        $morePhotosResult = ['matched' => false, 'has_possessive' => false];
        if ($selectedGirl !== null) {
            $morePhotosResult = $this->detectsMorePhotosOfSelected($normalizedInput);
        }

        // CASE: user addresses the speaker girl directly ("mas fotos tuyas")
        // → let the LLM handle it ("solo tengo esas cari") — don't add photos.
        if ($morePhotosResult['matched'] && $morePhotosResult['has_possessive']) {
            return $ctx;
        }

        // --- Determine mode and build photo list ---
        $formattedLines = [];
        $shown          = [];
        $unshown        = [];

        // CASE: LLM-driven selected_all → ALL photos of selected girl
        if ($llmWantsPhotos && $photoAction === 'selected_all' && $selectedGirl !== null) {
            $shown    = [$selectedGirl];
            $unshown  = array_values(array_filter(
                $girlsConfig,
                static fn(array $g): bool => ($g['id'] ?? '') !== ($selectedGirl['id'] ?? '')
            ));
            $formattedLines = $this->formatAllPhotos($selectedGirl);
        // CASE: LLM-driven selected_all but NO selected girl → degrade to catalog
        } elseif ($llmWantsPhotos && $photoAction === 'selected_all' && $selectedGirl === null) {
            foreach ($girlsConfig as $girl) {
                if (!is_array($girl)) continue;
                $shown[] = $girl;
                $line = $this->formatGirlOneLine($girl, $sentUrls);
                if ($line !== '') $formattedLines[] = $line;
            }
        // CASE: user asks for more photos of selected girl (no possessive pronoun)
        // → send ALL photos of the selected girl, NOT the catalog
        } elseif ($morePhotosResult['matched'] && !$morePhotosResult['has_possessive']) {
            $shown    = [$selectedGirl];
            $unshown  = array_values(array_filter(
                $girlsConfig,
                static fn(array $g): bool => ($g['id'] ?? '') !== ($selectedGirl['id'] ?? '')
            ));
            $formattedLines = $this->formatAllPhotos($selectedGirl);
        } elseif ($mentionsFriends || $wantsMore) {
            // Case 1 / 4 / 5: show 1 random photo of EACH active girl
            $shownIds = [];
            foreach ($girlsConfig as $girl) {
                if (!is_array($girl)) {
                    continue;
                }
                $shown[]    = $girl;
                $shownIds[] = $girl['id'] ?? '';
                $line       = $this->formatGirlOneLine($girl, $sentUrls);
                if ($line !== '') {
                    $formattedLines[] = $line;
                }
            }
            // unshown is empty in this mode (we showed all)
        } elseif ($selectedGirl !== null) {
            // Case 2: show ALL photos of the selected girl
            $shown    = [$selectedGirl];
            $unshown  = array_values(array_filter(
                $girlsConfig,
                static fn(array $g): bool => ($g['id'] ?? '') !== ($selectedGirl['id'] ?? '')
            ));
            $formattedLines = $this->formatAllPhotos($selectedGirl);
        } else {
            // Case 4 fallback: no selected girl, show 1 photo per active girl
            $shownIds = [];
            foreach ($girlsConfig as $girl) {
                if (!is_array($girl)) {
                    continue;
                }
                $shown[]    = $girl;
                $shownIds[] = $girl['id'] ?? '';
                $line       = $this->formatGirlOneLine($girl, $sentUrls);
                if ($line !== '') {
                    $formattedLines[] = $line;
                }
            }
        }

        // ── NOVA: Deduplicate URLs that already exist in the LLM's output text ─
        $existingUrls = [];
        if (preg_match_all('/https?:\/\/[^\s<>"\')\]]+/', $outputText, $urlMatches) !== false) {
            $existingUrls = array_map('trim', $urlMatches[0]);
        }
        $formattedLines = array_filter($formattedLines, static function (string $line) use ($existingUrls): bool {
            foreach ($existingUrls as $existing) {
                if ($line === $existing || str_contains($existing, $line) || str_contains($line, $existing)) {
                    return false; // URL already in the LLM text — skip
                }
            }
            return true;
        });

        $catalogBlock = implode("\n", $formattedLines);

        if ($catalogBlock !== '') {
            $outputText = rtrim($outputText);
            if ($outputText !== '') {
                $outputText .= "\n" . $catalogBlock;
            } else {
                $outputText = $catalogBlock;
            }
        }

        $ctx['output_text']          = $outputText;
        // Guardar solo nombres (strings) para persistencia y tracking
        $ctx['shown_girls']          = array_map(
            static fn(array $g): string => (string) ($g['nombre'] ?? ''),
            $shown
        );
        $ctx['unshown_girls']        = $unshown;
        $ctx['__total_active_girls'] = count($girlsConfig);

        return $ctx;
    }

    public function name(): string
    {
        return 'CatalogFormatter';
    }

    // ----------------------------------------------------------------- helpers

    /**
     * Format a girl entry returning ONE random photo URL (no name — WhatsApp preview shows it).
     */
    private function formatGirlOneLine(array $girl, array $sentUrls = []): string
    {
        $name   = $girl['nombre'] ?? 'Chica';
        $photos = $girl['fotos'] ?? [];
        if (!is_array($photos) || $photos === []) {
            return $name;
        }
        // Filter out already-sent URLs to avoid duplicates
        $available = array_filter($photos, static function ($p) use ($sentUrls): bool {
            $p = trim((string) $p);
            if ($p === '') return false;
            return !in_array($p, $sentUrls, true);
        });
        // If all were already sent, fall back to full list
        if ($available === []) {
            $available = array_filter($photos, static fn($p): bool => trim((string) $p) !== '');
        }
        // Pick a random photo from available
        $photo = $available[array_rand($available)];
        if (!is_string($photo) || $photo === '' || $this->isMapsUrl($photo)) {
            return '';
        }
        return $photo;
    }

    /**
     * Format ALL photos of a single girl (URLs only — no name, WhatsApp preview shows it).
     *
     * @return list<string>
     */
    private function formatAllPhotos(array $girl): array
    {
        $photos = $girl['fotos'] ?? [];
        if (!is_array($photos) || $photos === []) {
            return [];
        }

        $lines = [];
        foreach ($photos as $photo) {
            if (is_string($photo) && $photo !== '' && !$this->isMapsUrl($photo)) {
                $lines[] = $photo;
            }
        }
        return $lines;
    }

    /**
     * Find a girl in the catalog by name (case-insensitive, accent-tolerant).
     *
     * @param array<int, array<string, mixed>> $girls
     * @return array<string, mixed>|null
     */
    private function findGirlByName(array $girls, string $name): ?array
    {
        if ($name === '') {
            return null;
        }
        $needle = $this->normalizeStr($name);
        foreach ($girls as $girl) {
            if (!is_array($girl)) {
                continue;
            }
            if ($this->normalizeStr((string) ($girl['nombre'] ?? '')) === $needle) {
                return $girl;
            }
        }
        return null;
    }

    private function normalizeStr(string $value): string
    {
        $n = @normalizer_normalize($value, \Normalizer::NFKD);
        if ($n === false) {
            $n = $value;
        }
        $n = preg_replace('/[\x{0300}-\x{036f}]/u', '', $n) ?? $n;
        return mb_strtolower(trim((string) $n));
    }

    private function detectsGirls(string $normalizedText): bool
    {
        $patterns = [
            '/\bchicas?\b/i',
            '/\b(?:ver|mu[eé]strame?|ens[eé][ñn]ame?|quiero\s+ver|dime\s+de)\b/i',
            '/\bfotos?\b/i',
            '/\bfoto\b/i',
            '/\bcat[aá]logo\b/i',
            '/\b(?:cu[aá]ntas|cuantas)\s+(?:chicas|hay)\b/i',
            '/\b(?:qui[eé]nes?|cuales)\s+(?:hay|est[aá]n?|tienen?)\b/i',
            // NOVA: natural Spanish patterns ("las otras tres", "las que faltan"...)
            '/\blas\s+otras?\b/i',
            '/\blas?\s+que\s+faltan?\b/i',
            '/\bel\s+resto\b/i',
            '/\b(?:env[ií]ame|m[aá]ndame|p[aá]same)\s+(?:las|el|los)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalizedText)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detect if the client asks about other girls / friends.
     */
    private function detectsFriends(string $normalizedText): bool
    {
        $patterns = [
            '/\bamigas?\b/i',
            '/\botras?\s+chicas?\b/i',
            '/\blas?\s+dem[aá]s\b/i',
            '/\bhay\s+m[aá]s\b/i',
            '/\bqu[eé]\s+m[aá]s\s+(?:chicas?|hay)\b/i',
            '/\bcat[aá]logo\s+completo\b/i',
            '/\btodas\s+las?\s+chicas?\b/i',
            '/\bens[eé][ñn]ame\s+(todas|m[aá]s)\b/i',
            '/\bmu[eé]strame\s+(todas|m[aá]s)\b/i',
            '/\bquiero\s+ver\s+(todas|m[aá]s)\b/i',
            // NOVA: natural patterns for "the others" in catalog context
            '/\blas\s+otras?\b/i',
            '/\blas?\s+que\s+faltan?\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalizedText)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detect if the user asks for MORE photos of the currently selected girl.
     *
     * Dos niveles:
     * 1. Con posesivo ("mas fotos tuyas", "tienes otras fotos te") →
     *    El usuario se dirige a la chica que habla. Solo hay esas fotos.
     *    Se deja que el LLM responda "solo tengo esas cari".
     * 2. Sin posesivo ("tienes mas fotos", "pasame mas fotos", "tienes otras") →
     *    El usuario quiere más fotos de la chica seleccionada.
     *    El sistema DEBE adjuntar todas las fotos de esa chica.
     *
     * @return array{matched: bool, has_possessive: bool}
     */
    private function detectsMorePhotosOfSelected(string $normalizedText): array
    {
        // --- Nivel 1: Con posesivo (tuyas, tuya, te, ti, de ella, de [nombre]) ---
        $possessivePatterns = [
            '/\bm[aá]s\s+fotos?\s+(?:tuyas?|tuya|te|ti)\b/i',
            '/\botras?\s+fotos?\s+(?:tuyas?|tuya|te|ti)\b/i',
            '/\b(?:tuyas?|tuya|te|ti)\s+(?:tienes?\s+)?(?:m[aá]s|otras?)\s+fotos?\b/i',
            '/\b(?:m[aá]ndame|env[ií]ame|p[aá]same|dame)\s+(?:m[aá]s|otras?)\s+fotos?\s+(?:tuyas?|tuya|te|ti)\b/i',
            '/\b(?:m[aá]s|otras?)\s+fotos?\s+de\s+ella\b/i',
        ];

        foreach ($possessivePatterns as $pattern) {
            if (preg_match($pattern, $normalizedText)) {
                return ['matched' => true, 'has_possessive' => true];
            }
        }

        // --- Nivel 2: Sin posesivo, pero pide más fotos (solo si hay chica seleccionada) ---

        // ── NOVA: Patterns WITHOUT "más/otras" — natural requests like "fotos de ella" ──
        $simplePatterns = [
            '/\bfotos?\s+de\s+(?:ella|esa|esta)\b/i',
            '/\b(?:ver|mu[eé]strame|ens[eé][ñn]ame|p[aá]same|m[aá]ndame)\s+fotos?\s+de\s+(?:ella|esa|esta)\b/i',
            '/\b(?:ver|mu[eé]strame|ens[eé][ñn]ame)\s+fotos?\s+de\s+\w+\b/i',
            '/\bquiero\s+fotos?\s+de\s+(?:ella|esa|esta)\b/i',
            '/\bquiero\s+ver\s+(?:sus|las)\s+fotos?\b/i',
        ];
        foreach ($simplePatterns as $pattern) {
            if (preg_match($pattern, $normalizedText)) {
                return ['matched' => true, 'has_possessive' => false];
            }
        }

        $generalPatterns = [
            '/\btienes?\s+(?:m[aá]s|otras?)\s+fotos?\b/i',
            '/\b(?:m[aá]s|otras?)\s+fotos?\s+tienes?\b/i',
            '/\b(?:m[aá]ndame|env[ií]ame|p[aá]same|dame)\s+(?:m[aá]s|otras?)\s+fotos?\b/i',
            '/\b(?:m[aá]s|otras?)\s+fotos?\b.*\b(?:de|d)\s*(?:ella|esta|esa)\b/i',
            '/\b(?:mu[eé]strame|ens[eé][ñn]ame)\s+(?:m[aá]s|otras?)\s+fotos?\b/i',
            '/\b(?:tienes|hay|tiene)\s+(?:m[aá]s|otras?)\b.*\bfotos?\b/i',
            '/\bquiero\s+(?:m[aá]s|otras?)\s+fotos?\b/i',
            '/\b(?:m[aá]s|otras?)\s+fotos?\s+(?:de|d)\s*\w+\b/i',
        ];

        foreach ($generalPatterns as $pattern) {
            if (preg_match($pattern, $normalizedText)) {
                return ['matched' => true, 'has_possessive' => false];
            }
        }

        return ['matched' => false, 'has_possessive' => false];
    }

    private function containsPhotoUrls(string $text): bool
    {
        if (preg_match_all('/https?:\/\/[^\s<>"\')\]}]+/', $text, $matches) === false) {
            return false;
        }
        foreach ($matches[0] as $url) {
            if (!$this->isMapsUrl($url)) {
                if (preg_match('/\.(?:jpg|jpeg|png|webp|gif|bmp)(?:\?|$)/i', $url)
                    || str_contains($url, 'photo')
                    || str_contains($url, 'image')
                    || str_contains($url, 'pic')
                    || str_contains($url, 'img')) {
                    return true;
                }
            }
        }
        return false;
    }

    private function isMapsUrl(string $url): bool
    {
        return str_contains($url, 'maps.google')
            || str_contains($url, 'maps.app.goo.gl')
            || str_contains($url, 'goo.gl/maps');
    }
}
