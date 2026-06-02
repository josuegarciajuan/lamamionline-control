<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

use WasapBot\Core\ConfigInterface;

/**
 * Builds tone directives for the LLM based on sentiment, register, urgency,
 * and contextual flags.
 *
 * Includes anti-repetition rules (ya_enviado, last_bot_reply,
 * recent_bot_replies_norm), speaker identity, and haggle management.
 *
 * Pattern: node "Build Tone" from bot.json (lines ~808-818)
 */
final class ToneBuilder implements PipelineStageInterface
{
    public function __construct(
        private readonly ConfigInterface $config,
    ) {}

    public function process(array $ctx): ?array
    {
        // ------------------------------------------------------------------ //
        //  1. Read and sanitise scalar context values                         //
        // ------------------------------------------------------------------ //
        $sentiment = $ctx['sentiment'] ?? 'neutral';
        $register  = $ctx['register']  ?? 'coloquial';
        $urgency   = $ctx['urgency']   ?? 'normal';

        if (!is_string($sentiment)) { $sentiment = 'neutral'; }
        if (!is_string($register))  { $register  = 'coloquial'; }
        if (!is_string($urgency))   { $urgency   = 'normal'; }

        /** @var list<string> $directives */
        $directives = [];

        // ------------------------------------------------------------------ //
        //  2. Base tone directive                                              //
        // ------------------------------------------------------------------ //
        $directives[] = "Usa registro {$register}, tono {$sentiment}, urgencia {$urgency}.";

        // ------------------------------------------------------------------ //
        //  3. Greeting suppression                                            //
        // ------------------------------------------------------------------ //
        $hasGreeted     = !empty($ctx['__has_greeted']);
        $isOpeningBurst = !empty($ctx['__is_opening_burst']);

        if ($hasGreeted || $isOpeningBurst) {
            $directives[] = 'NO vuelvas a saludar. Ve directo al grano.';
        }

        // ------------------------------------------------------------------ //
        //  4. Speaker-mode: encargada → don't self-identify                  //
        // ------------------------------------------------------------------ //
        $speakerMode = is_string($ctx['speaker_mode'] ?? null) ? $ctx['speaker_mode'] : '';

        if ($speakerMode === 'encargada') {
            $directives[] = 'NO digas que eres la encargada ni que atiendes tú. Habla como si fueras la chica.';
        }

        // ------------------------------------------------------------------ //
        //  5. Catalog / info-dump flags                                       //
        // ------------------------------------------------------------------ //
        $wantsMoreGirls = !empty($ctx['wants_more_girls']);

        if ($wantsMoreGirls) {
            $directives[] = 'El cliente quiere ver TODAS las chicas. Muestra el catálogo completo.';
        }

        if (!empty($ctx['info_pack_ready'])) {
            $directives[] = 'INFO DUMP: Suelta TODO de golpe (precios, chicas, zona, servicios). No preguntes nada.';
        }

        // ------------------------------------------------------------------ //
        //  6. Conversation state flags                                        //
        // ------------------------------------------------------------------ //
        if (!empty($ctx['conversation_dead'])) {
            $directives[] = 'CONVERSACION MUERTA. Silencio total. No respondas.';
        }

        if (!empty($ctx['conversation_end_intent'])) {
            $directives[] = 'DESPEDIDA FINAL: max 4 palabras. SIN preguntas. No intentes continuar la conversación.';
        }

        // ------------------------------------------------------------------ //
        //  7. Anti-repetición basada en ya_enviado                           //
        // ------------------------------------------------------------------ //
        /** @var list<string> $yaEnviado */
        $yaEnviado = is_array($ctx['ya_enviado'] ?? null) ? $ctx['ya_enviado'] : [];

        if (in_array('fotos', $yaEnviado, true)) {
            if ($wantsMoreGirls) {
                $directives[] = 'El cliente pide MÁS fotos o chicas que aún no ha visto. '
                    . 'Muestra las chicas que AÚN NO se han mostrado (unshown_girls). '
                    . 'NO repitas las mismas fotos.';
            } else {
                $directives[] = "Ya se mandaron fotos antes. "
                    . "Si piden las MISMAS fotos: responde 'ya te las mande amor, mira arriba'. "
                    . 'NO las vuelvas a mandar. '
                    . 'Si piden MÁS fotos o chicas nuevas: muéstralas.';
            }
        }

        if (
            in_array('ubicacion', $yaEnviado, true)
            || in_array('ubicacion_precisa', $yaEnviado, true)
        ) {
            $directives[] = 'Ya se mandó la ubicación/maps antes. '
                . "Si el cliente vuelve a preguntar por la ubicación SIN añadir nada nuevo: "
                . "responde 'ya te mande el maps cari, mira arriba 👆'. "
                . 'Varía la redacción para sonar natural, no repitas siempre igual.';
        }

        if (in_array('precios', $yaEnviado, true)) {
            $directives[] = 'Las tarifas ya se explicaron antes. '
                . 'Si el cliente ya las conoce y no pregunta de nuevo: NO las repitas. '
                . 'Si pregunta de nuevo por precios: recuérdalos muy brevemente (1 línea).';
        }

        // ------------------------------------------------------------------ //
        //  8. Anti-repetición crítica (directiva base siempre presente)      //
        // ------------------------------------------------------------------ //
        $directives[] = 'ANTI-REPETICION CRITICA: Si tu respuesta se parece mucho a last_bot_reply '
            . 'o aparece en recent_bot_replies_norm, REESCRIBELA completamente con otras palabras '
            . 'manteniendo la misma info. NUNCA repitas frases literales.';

        // ------------------------------------------------------------------ //
        //  9. Ejemplos de respuestas recientes a evitar                      //
        // ------------------------------------------------------------------ //
        /** @var list<string> $recentReplies */
        $recentReplies = is_array($ctx['recent_bot_replies_norm'] ?? null)
            ? $ctx['recent_bot_replies_norm']
            : [];

        if ($recentReplies !== []) {
            $samples = array_slice($recentReplies, 0, 5);
            $samples = array_map(
                static fn(mixed $r): string => mb_substr((string) $r, 0, 80),
                $samples,
            );
            $directives[] = 'Respuestas recientes a evitar (no repetir): '
                . implode(' | ', $samples);
        }

        // ------------------------------------------------------------------ //
        //  10. Speaker identity                                               //
        // ------------------------------------------------------------------ //
        $speakerGirlName  = isset($ctx['speaker_girl_name'])  && is_string($ctx['speaker_girl_name'])
            ? trim($ctx['speaker_girl_name'])
            : '';

        $selectedGirlName = isset($ctx['selected_girl_name']) && is_string($ctx['selected_girl_name'])
            ? trim($ctx['selected_girl_name'])
            : '';

        if ($speakerGirlName !== '') {
            $directives[] = "Tu identidad ES {$speakerGirlName}. "
                . "Hablas SIEMPRE en primera persona como {$speakerGirlName}. "
                . 'NUNCA cambies de identidad aunque el cliente pregunte por otra chica.';
        }

        if ($selectedGirlName !== '' && $selectedGirlName !== $speakerGirlName) {
            $directives[] = "El cliente ha elegido a {$selectedGirlName} para el servicio. "
                . "Habla de ella como 'mi amiga' en 3ª persona. "
                . "Los datos de cita/ubicación son de {$selectedGirlName}.";
        }

        // ------------------------------------------------------------------ //
        //  11. Haggle (regateo)                                               //
        // ------------------------------------------------------------------ //
        $haggleCount = isset($ctx['haggle_count_recent']) && is_int($ctx['haggle_count_recent'])
            ? $ctx['haggle_count_recent']
            : 0;

        if ($haggleCount >= 3) {
            $directives[] = 'Regateo repetido: modo TAJANTE. '
                . 'No des descuentos. Frases cortas y firmes. '
                . 'Si sigue: cierra la conversación.';
        } elseif ($haggleCount >= 2) {
            $directives[] = 'Ya hubo regateo. Sé más firme. '
                . 'Sin descuentos. Reconducir a las tarifas establecidas.';
        }

        // ------------------------------------------------------------------ //
        //  12. NOVA: Post-maps ETA mode (rotating variants)                  //
        // ------------------------------------------------------------------ //
        $mapsSent        = !empty($ctx['maps_sent']);
        $etaFromUserFlag = !empty($ctx['eta_from_user_flag']);

        if ($mapsSent && !$etaFromUserFlag) {
            $variants = (array) $this->config->get('message_variants.eta_request_variants', []);
            if ($variants === []) {
                $variants = ['cuanto tardas amor?', 'avisame cuando salgas', 'dime cuantos min?'];
            }
            $botMsgCount = isset($ctx['bot_msg_count_recent']) && is_int($ctx['bot_msg_count_recent'])
                ? $ctx['bot_msg_count_recent'] : 0;
            $pick = $variants[$botMsgCount % count($variants)];
            $directives[] = "POST-MAPS: Maps ya enviado. Prioridad ÚNICA = ETA. "
                . "Cada mensaje DEBE terminar con una variante: '{$pick}'. "
                . 'Respuestas ultra-cortas (1 frase). No abras temas nuevos.';
        }

        // ------------------------------------------------------------------ //
        //  13. NOVA: Cierre suave progresivo (info_pack_ready, sin ETA aún)  //
        // ------------------------------------------------------------------ //
        $infoPackReady = !empty($ctx['info_pack_ready']);

        if ($infoPackReady && !$etaFromUserFlag) {
            $directives[] = 'Cierre suave: el cliente YA tiene precios+chica+ubicación. '
                . 'Responde corto y termina con una variante de cierre (te espero, '
                . 'avisame cuando salgas, dime cuanto tardas). No alargues la charla. '
                . 'No hagas preguntas abiertas. Si el cliente no avanza en 2 mensajes, '
                . 'responde muy corto (2-3 palabras) o despídete.';
        }

        // ------------------------------------------------------------------ //
        //  14. NOVA: Indecisión + escasez suave                                //
        // ------------------------------------------------------------------ //
        $chooseLoopCount = isset($ctx['choose_loop_count']) && is_int($ctx['choose_loop_count'])
            ? $ctx['choose_loop_count'] : 0;
        $selectedGirlName = isset($ctx['selected_girl_name']) && is_string($ctx['selected_girl_name'])
            ? trim($ctx['selected_girl_name']) : '';
        $escasezYaUsada = in_array('escasez', $yaEnviado, true);

        if ($chooseLoopCount >= 2 && $selectedGirlName === '' && !$escasezYaUsada) {
            $directives[] = "El cliente lleva {$chooseLoopCount} vueltas preguntando ubicación/maps "
                . 'sin elegir chica. Usa escasez suave UNA sola vez: '
                . '"esta noche hay poca disponibilidad" o "hay alguien mas preguntando ahora". '
                . 'Después pide que elija chica sin presión.';
        }

        // ------------------------------------------------------------------ //
        //  15. NOVA: User sent an image                                         //
        // ------------------------------------------------------------------ //
        $isImageFromUser = !empty($ctx['is_image_sent_by_user']);
        if ($isImageFromUser) {
            $directives[] = 'El cliente acaba de mandar una imagen/foto. '
                . 'Reacciona MUY breve: 1 frase corta (ej: "buenas vistas 😏", "foto maja"). '
                . 'Después sigue el hilo normal de la conversación. NO preguntes por la foto.';
        }

        // ------------------------------------------------------------------ //
        //  16. Generic human-voice directives                                  //
        // ------------------------------------------------------------------ //
        $directives[] = 'Saludo minimalista: Saludo MÁX 4 palabras. No preguntes "cómo estás" ni similares.';
        $directives[] = 'Reactivo: Responde SOLO lo que preguntan. NO hagas preguntas de follow-up. NO sondees.';
        $directives[] = 'Sin auto-identificación: NUNCA digas "soy la encargada", "atiendo yo", "soy la que está aquí" ni similares.';
        $directives[] = 'Sin signos de apertura (¿ ¡) y faltas de ortografía leves para sonar humana.';
        $directives[] = 'Emojis: usa 1 emoji en ~2 de cada 3 mensajes, como parte natural de la conversación. Si el anterior tuyo llevó, este puede llevarlo o no. Lo importante es no forzar: a veces sí, a veces no.';

        // ------------------------------------------------------------------ //
        //  13. Inject into context                                            //
        // ------------------------------------------------------------------ //
        $ctx['tone_directives'] = implode("\n", $directives);

        return $ctx;
    }

    public function name(): string
    {
        return 'ToneBuilder';
    }
}
