<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\LoggerInterface;

/**
 * Lightweight conversation state machine that tracks the logical stage
 * of each WhatsApp conversation and enforces valid transitions.
 *
 * States:
 *   NEW              → first contact, no messages yet
 *   GREETING_SENT    → bot sent initial greeting
 *   AWAITING_INTEREST → client has been greeted, waiting for first real question
 *   CATALOG_SHOWN     → bot showed the girls catalog
 *   GIRL_SELECTED    → client chose or mentioned a specific girl
 *   PRICE_GIVEN      → bot communicated prices
 *   MAPS_SENT        → bot sent Google Maps location
 *   WAITING_ETA      → maps sent, waiting for client ETA
 *   CONFIRMED        → client gave concrete ETA (lead confirmed)
 *   DEAD             → conversation ended (farewell, filler loop, timeout, hostile)
 *   COMPLETED        → lead was successfully handed off or completed
 *
 * The FSM runs deterministically from session memory history — no LLM
 * involvement.  The resulting state is injected into the context so the
 * LLM and pipeline stages can make state-aware decisions.
 */
final class ConversationStateMachine
{
    /** Valid states and their human-readable labels. */
    public const array STATES = [
        'NEW'               => 'Nueva',
        'GREETING_SENT'     => 'Saludo enviado',
        'AWAITING_INTEREST' => 'Esperando interés',
        'CATALOG_SHOWN'     => 'Catálogo mostrado',
        'GIRL_SELECTED'     => 'Chica elegida',
        'PRICE_GIVEN'       => 'Precios dados',
        'MAPS_SENT'         => 'Mapa enviado',
        'WAITING_ETA'       => 'Esperando ETA',
        'CONFIRMED'         => 'Confirmado',
        'DEAD'              => 'Muerta',
        'COMPLETED'         => 'Completada',
    ];

    /** @var list<array<string, mixed>> */
    private array $history;

    /** @var array<string, mixed> */
    private array $ctx;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * Compute the current conversation state from the session history.
     *
     * @param list<array<string, mixed>> $history  Full filtered history
     * @param array<string, mixed>       $ctx      Current pipeline context
     * @return string  One of the STATES keys.
     */
    public function computeState(array $history, array $ctx): string
    {
        $this->history = $history;
        $this->ctx     = $ctx;

        // No history at all → NEW
        if ($history === []) {
            return 'NEW';
        }

        // First check for terminal states from context flags
        if (!empty($ctx['__conversation_ended'])) {
            return 'DEAD';
        }
        // Also check LLM-derived health field
        $llmHealth = (string) ($ctx['__llm_conversation_health'] ?? '');
        if ($llmHealth === 'dead') {
            return 'DEAD';
        }

        // Walk through the history to determine the current state
        $state = 'NEW';

        foreach ($history as $rec) {
            $botReply = trim((string) ($rec['bot_reply'] ?? ''));
            $userMsg  = trim((string) ($rec['user_msg'] ?? ''));
            $speaker  = trim((string) ($rec['speaker_girl_name'] ?? ''));
            $selected = trim((string) ($rec['selected_girl_name'] ?? ''));
            $shown    = (array) ($rec['shown_girls'] ?? []);
            $ya       = (array) ($rec['ya_enviado'] ?? []);
            $mapsSent = !empty($rec['maps_sent']);

            // Transition: bot sent a greeting
            if ($state === 'NEW' && $botReply !== '' && $this->isGreeting($botReply)) {
                $state = 'GREETING_SENT';
            }

            // Transition: client shows interest
            if (($state === 'NEW' || $state === 'GREETING_SENT' || $state === 'AWAITING_INTEREST')
                && $userMsg !== '' && $this->isShowingInterest($userMsg)
            ) {
                $state = 'AWAITING_INTEREST';
            }

            // Transition: catalog was shown
            if (count($shown) >= 2) {
                $state = 'CATALOG_SHOWN';
            }

            // Transition: girl selected
            if ($selected !== '') {
                $state = 'GIRL_SELECTED';
            }

            // Transition: prices given
            if (in_array('precios', $ya, true)) {
                if ($state === 'GIRL_SELECTED' || $state === 'CATALOG_SHOWN' || $state === 'AWAITING_INTEREST') {
                    $state = 'PRICE_GIVEN';
                }
            }

            // Transition: maps sent
            if ($mapsSent || in_array('ubicacion', $ya, true) || in_array('ubicacion_precisa', $ya, true)) {
                $state = 'MAPS_SENT';
            }

            // Transition: ETA requested → waiting
            if ($state === 'MAPS_SENT' && $botReply !== '' && $this->isEtaRequest($botReply)) {
                $state = 'WAITING_ETA';
            }

            // Transition: ETA received
            if (!empty($rec['eta_from_user_flag'])) {
                $state = 'CONFIRMED';
            }

            // Transition: client explicitly ended
            if ($userMsg !== '' && $this->isFarewell($userMsg)) {
                // Don't override CONFIRMED with DEAD
                if ($state !== 'CONFIRMED') {
                    $state = 'DEAD';
                }
            }
        }

        return $state;
    }

    /**
     * Get a natural-language hint for the LLM based on the current state.
     */
    public function getStateHint(string $state): string
    {
        return match ($state) {
            'NEW'               => 'Acabas de recibir el primer mensaje. Saluda breve (max 4 palabras) y espera.',
            'GREETING_SENT'     => 'Ya saludaste. Espera a que el cliente pregunte algo. No ofrezcas catálogo aún.',
            'AWAITING_INTEREST' => 'El cliente ha mostrado interés. Puedes preguntar qué busca o mostrar chicas si pregunta.',
            'CATALOG_SHOWN'     => 'Ya se mostró el catálogo. Pregunta cuál le gusta. No vuelvas a mostrar todas.',
            'GIRL_SELECTED'     => 'El cliente ya eligió chica. Céntrate en ella. NO ofrezcas otras. Pregunta precios o ubicación si no se ha hecho.',
            'PRICE_GIVEN'       => 'Ya se dieron precios. El siguiente paso natural es ubicación/maps. Si el cliente pregunta dónde, envía el mapa.',
            'MAPS_SENT'         => 'Mapa enviado. AHORA tu ÚNICO objetivo es conseguir el ETA. Responde corto y siempre pregunta cuánto tarda.',
            'WAITING_ETA'       => 'Esperando ETA. No abras temas nuevos. Solo confirma y espera.',
            'CONFIRMED'         => 'Cliente confirmado con ETA. Ya no necesitas vender. Solo coordina llegada.',
            'DEAD'              => 'Conversación terminada. No respondas.',
            'COMPLETED'         => 'Lead completado. No respondas.',
            default             => '',
        };
    }

    /**
     * Check whether a transition from current state to target is valid.
     */
    public function canTransition(string $from, string $to): bool
    {
        // Any state can go to DEAD
        if ($to === 'DEAD') return true;

        // Valid forward transitions
        $validTransitions = [
            'NEW'               => ['NEW', 'GREETING_SENT', 'AWAITING_INTEREST', 'DEAD'],
            'GREETING_SENT'     => ['GREETING_SENT', 'AWAITING_INTEREST', 'CATALOG_SHOWN', 'DEAD'],
            'AWAITING_INTEREST' => ['AWAITING_INTEREST', 'CATALOG_SHOWN', 'GIRL_SELECTED', 'DEAD'],
            'CATALOG_SHOWN'     => ['CATALOG_SHOWN', 'GIRL_SELECTED', 'PRICE_GIVEN', 'DEAD'],
            'GIRL_SELECTED'     => ['GIRL_SELECTED', 'PRICE_GIVEN', 'MAPS_SENT', 'CATALOG_SHOWN', 'DEAD'],
            'PRICE_GIVEN'       => ['PRICE_GIVEN', 'MAPS_SENT', 'GIRL_SELECTED', 'DEAD'],
            'MAPS_SENT'         => ['MAPS_SENT', 'WAITING_ETA', 'CONFIRMED', 'DEAD'],
            'WAITING_ETA'       => ['WAITING_ETA', 'CONFIRMED', 'DEAD'],
            'CONFIRMED'         => ['CONFIRMED', 'COMPLETED', 'DEAD'],
            'DEAD'              => ['DEAD'],
            'COMPLETED'         => ['COMPLETED', 'DEAD'],
        ];

        return isset($validTransitions[$from]) && in_array($to, $validTransitions[$from], true);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Pattern matchers
    // ─────────────────────────────────────────────────────────────────

    private function isGreeting(string $text): bool
    {
        return (bool) preg_match('/^(hola|buenas|hey|saludos|ola|dime|buen\s*d[ií]a)/iu', $text);
    }

    private function isShowingInterest(string $text): bool
    {
        return (bool) preg_match(
            '/\b(precio|tarifa|cu[aá]nto|fotos?|ver|ense[ñn]|chicas?|ubicaci[oó]n|donde|informacion|servicios?|disponible)/iu',
            $text
        );
    }

    private function isEtaRequest(string $text): bool
    {
        return (bool) preg_match(
            '/\b(cu[aá]nto\s+tardas?|cu[aá]ndo\s+llegas?|av[ií]same|dime\s+cu[aá]nto|en\s+cu[aá]ntos?\s+min|tardas\s+mucho|te\s+espero)/iu',
            $text
        );
    }

    private function isFarewell(string $text): bool
    {
        return (bool) preg_match(
            '/\b(adios|chao|bye|hasta\s+luego|nos\s+vemos|me\s+despido|te\s+llamo\s+mañana|hablamos\s+mañana|me\s+voy\s+ya|gracias\s+por\s+todo)\b/iu',
            $text
        );
    }
}
