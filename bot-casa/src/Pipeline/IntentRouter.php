<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\LoggerInterface;

/**
 * Pre-LLM intent router — classifies user messages into intent categories
 * and dispatches template responses for common intents, skipping the LLM
 * entirely for ~60% of messages.
 *
 * Intents handled without LLM:
 *   greeting   → "hola", "buenas", "hey", etc.
 *   price      → "precio?", "tarifas?", "cuánto?", etc.
 *   location   → "dónde?", "ubicación?", "zona?", etc.
 *   photos     → "fotos?", "ver más?", "enseñame", etc.
 *   goodbye    → "adios", "gracias", "hasta luego", etc.
 *   confirm    → "ok", "vale", "si" (when conversation should end)
 *   fallback   → everything else → LLM
 */
final class IntentRouter implements PipelineStageInterface
{
    /** @var array<string, list<string>> */
    private array $templates;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->templates = [
            'greeting' => (array) $this->config->get('message_variants.intent_greetings', [
                'hola cari 😊',
                'hola guapo 😘',
                'buenas 😏',
                'dime papi 🔥',
                'hola cielo 😊',
            ]),
            'price' => (array) $this->config->get('message_variants.intent_prices', [
                '40 rapidito, 50 media hora, 100 la hora 😏 dime cual te gusta?',
                '50€ media hora y 100€ la hora cari, elige chica y te cuento mas 😘',
                'tengo 40€ 10 min, 50€ media hora, 100€ 1h 😊 cual prefieres?',
            ]),
            'location' => (array) $this->config->get('message_variants.intent_locations', [
                'Burriana centro, piso discreto 😊 dime cual chica te gusta y te paso la ubicacion exacta',
                'en Burriana centro cielo, dime cual te gusta y te paso el maps 😘',
            ]),
            'photos' => (array) $this->config->get('message_variants.intent_photos', [
                'claro, mira cual te gusta 😏',
                'te paso las chicas que tengo 😘',
            ]),
            'goodbye' => (array) $this->config->get('message_variants.intent_goodbyes', [
                'hablamos 😘',
                'vale, ya me diras 😊',
                'cuando quieras papi 😏',
            ]),
            'confirm' => (array) $this->config->get('message_variants.intent_confirms', [
                '😘',
                '😊',
                '😏',
            ]),
        ];
    }

    public function process(array $ctx): ?array
    {
        // Already skipped LLM? Don't re-route
        if (!empty($ctx['__skip_llm'])) {
            return $ctx;
        }

        $messageText = (string) ($ctx['message_text'] ?? '');
        if ($messageText === '') {
            return $ctx;
        }

        $normalized = mb_strtolower(trim($messageText), 'UTF-8');
        $isNewConv  = !empty($ctx['__is_new_conversation']);

        // ── Detect intent ────────────────────────────────────────────
        $intent = $this->classify($normalized, $isNewConv, $ctx);
        $ctx['__intent'] = $intent;

        if ($intent === 'fallback') {
            return $ctx; // Let LLM handle it
        }

        // ── Templates that trigger photo_action ──────────────────────
        $photoAction = 'none';
        if ($intent === 'photos') {
            $photoAction = empty($ctx['selected_girl_name']) ? 'catalog' : 'selected_all';
        }

        // ── Pick a template response ─────────────────────────────────
        $variants = $this->templates[$intent] ?? [];
        if ($variants === []) {
            return $ctx; // No variants configured → let LLM handle
        }

        $reply = $variants[array_rand($variants)];

        // ── Skip LLM and use template ────────────────────────────────
        $ctx['output_text']   = $reply;
        $ctx['lead_detected'] = false;
        $ctx['photo_action']  = $photoAction;
        $ctx['__skip_llm']    = true;
        $ctx['__intent']      = $intent;

        if ($intent === 'goodbye' || $intent === 'confirm') {
            $ctx['__conversation_ended'] = true;
        }

        if ($this->logger !== null) {
            $this->logger->info('IntentRouter: matched intent, skipping LLM', [
                'intent' => $intent,
                'phone'  => $ctx['from_phone'] ?? '?',
            ]);
        }

        return $ctx;
    }

    public function name(): string
    {
        return 'IntentRouter';
    }

    // ─────────────────────────────────────────────────────────────────
    //  Classification
    // ─────────────────────────────────────────────────────────────────

    /**
     * Classify a normalized message into one of the known intents.
     *
     * Priority order (first match wins):
     *   1. goodbye  — explicit farewells
     *   2. confirm  — monosyllabic acknowledgments when conversation is winding down
     *   3. price    — price/service inquiries
     *   4. location — location/direction inquiries
     *   5. photos   — photo/catalog requests
     *   6. greeting — first-contact hellos
     *   7. fallback — everything else
     */
    private function classify(string $normalized, bool $isNewConv, array $ctx): string
    {
        // 1. Goodbye — explicit farewell words
        if ($this->isGoodbye($normalized)) {
            return 'goodbye';
        }

        // 2. Confirm — filler words when conversation should already be ending
        if ($this->isConfirm($normalized, $ctx)) {
            return 'confirm';
        }

        // 3. Price
        if ($this->isPrice($normalized)) {
            return 'price';
        }

        // 4. Location
        if ($this->isLocation($normalized)) {
            return 'location';
        }

        // 5. Photos
        if ($this->isPhotos($normalized)) {
            return 'photos';
        }

        // 6. Greeting — only on new conversations
        if ($isNewConv && $this->isGreeting($normalized)) {
            return 'greeting';
        }

        return 'fallback';
    }

    private function isGoodbye(string $t): bool
    {
        return (bool) preg_match(
            '/\b(adios|chao|bye|hasta\s+luego|nos\s+vemos|me\s+despido|gracias\s+por\s+todo'
            . '|te\s+llamo\s+mañana|hablamos\s+mañana|otro\s+dia|me\s+voy\s+ya)\b/iu',
            $t
        );
    }

    /**
     * Monosyllabic filler words that should NOT trigger a full LLM response,
     * especially when the conversation is already winding down.
     */
    private function isConfirm(string $t, array $ctx): bool
    {
        // Only route to "confirm" intent when the conversation has recent
        // history (not a brand-new conversation asking "ok?" about something)
        $fillerCount = (int) ($ctx['__filler_loop_count'] ?? 0);
        $isNewConv   = !empty($ctx['__is_new_conversation']);
        $justEnded   = !empty($ctx['__conversation_ended_recently']);

        // Never treat first messages as confirm
        if ($isNewConv) {
            return false;
        }

        // If conversation just ended, any short message = confirm
        if ($justEnded && mb_strlen($t) <= 5) {
            return true;
        }

        // If we already have 2+ filler loops, treat as confirm (stop the loop)
        if ($fillerCount >= 2 && mb_strlen($t) <= 5) {
            return true;
        }

        // Pure filler words in established conversations
        if (preg_match('/^(ok|oki|okey|oka|vale|vle|si|sip|yas|da|dale|ta\s*bien|claro|perfecto|genial|guay|de\s*acuerdo)$/iu', $t)) {
            return $fillerCount >= 1 || $justEnded;
        }

        return false;
    }

    private function isPrice(string $t): bool
    {
        return (bool) preg_match(
            '/\b(precio|tarifa|cu[aá]nto|cobras|vale|cuesta|cu[aá]l\s+es\s+el|servicios?\s+y\s+precios?'
            . '|informacion|informes|me\s+informas|q\s+haces|que\s+haces|qu[eé]\s+ofreces)\b/iu',
            $t
        );
    }

    private function isLocation(string $t): bool
    {
        return (bool) preg_match(
            '/\b(d[oó]nde|ubicaci[oó]n|zona|direcci[oó]n|calle|piso|maps|mapa|gps'
            . '|donde\s+est[aá]s?|cerca\s+de|por\s+donde|en\s+que\s+zona)\b/iu',
            $t
        );
    }

    private function isPhotos(string $t): bool
    {
        return (bool) preg_match(
            '/\b(fotos?|ver|ense[ñn]|cat[aá]logo|m[aá]s\s+chicas?|a\s+ver|mu[eé]strame'
            . '|pasame\s+fotos?|quiero\s+ver|ens[eé][ñn]ame|fotos?\s+de)\b/iu',
            $t
        );
    }

    private function isGreeting(string $t): bool
    {
        return (bool) preg_match(
            '/^(hola|holis|buenas?|hey|saludos?|ola|ola\s+k\s+ase|alo|aló|buen\s*d[ií]a|buenas\s*(tardes|noches)?)[\s!😊😘😏🔥]*$/iu',
            $t
        );
    }
}
