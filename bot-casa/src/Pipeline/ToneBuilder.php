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
            $directives[] = 'NO digas que eres la encargada ni que atiendes tú. '
                . "NUNCA digas 'no soy [nombre]' ni aclares quién NO eres. "
                . 'Simplemente presenta las chicas disponibles sin referirte a ti misma. '
                . 'Habla como si fueras la chica.';
        }

        // ------------------------------------------------------------------ //
        //  4b. First contact — proactive catalog                             //
        // ⛔ NO mostrar catálogo si el cliente viene de un anuncio concreto    //
        //    (viene buscando a UNA chica específica, mostrar otras le enfada)  //
        // ------------------------------------------------------------------ //
        $isNewConversation = !empty($ctx['__is_new_conversation']);
        $messageText = (string) ($ctx['message_text'] ?? '');
        $comesFromAd = (bool) preg_match(
            '/(?:nuevapasion\.com\/anuncio|milanuncios\.com\/contacto|adultguia\.com|destacamos\.com|pasions\.com|sexoservicios\.com)/i',
            $messageText,
        );
        if ($isNewConversation && $speakerMode === 'encargada' && !$comesFromAd) {
            $directives[] = 'PRIMER CONTACTO: El cliente acaba de llegar. '
                . 'Salúdale con MÁXIMO 4 palabras y NO digas nada más. '
                . 'NO menciones chicas. NO presentes catálogo. NO uses photo_action="catalog" ni "selected_all". '
                . 'Usa photo_action="none". Espera a que el cliente te pregunte algo. '
                . 'Solo reacciona a lo que te pregunten.';
        }

        // ── 4c. Cliente viene de anuncio concreto → NO catálogo ──
        if ($comesFromAd) {
            $directives[] = 'ATENCIÓN: El cliente viene del enlace de un anuncio concreto '
                . '(viene buscando a UNA chica específica). NO muestres catálogo de otras chicas. '
                . 'NO uses photo_action="catalog". Céntrate en la chica del anuncio. '
                . 'Si no sabes el nombre de la chica del anuncio, dedúcelo del enlace o pregúntalo '
                . 'de forma natural pero SIN mostrar otras chicas. '
                . 'NUNCA digas "mira que chicas tengo" ni "te presento a mis amigas".';
        }

        // ------------------------------------------------------------------ //
        //  5. Catalog / info-dump flags                                       //
        // ------------------------------------------------------------------ //
        $wantsMoreGirls = !empty($ctx['wants_more_girls']);

        if ($wantsMoreGirls) {
            $directives[] = 'El cliente pidió ver más chicas antes. '
                . 'Si su mensaje actual va de eso, usa photo_action="catalog". '
                . 'Si está preguntando OTRA COSA distinta, NO mandes fotos ni catálogo.';
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
                $directives[] = "Ya se mandaron fotos antes, pero el cliente las pide otra vez. "
                    . 'Sé amable: dile algo natural como "te las vuelvo a pasar amor 😘" '
                    . 'y pon photo_action="catalog" o "selected_all" para que el sistema las reenvíe. '
                    . 'NO le digas que mire arriba ni que scrollee. '
                    . 'Pásaselas de nuevo, igual no las encontró. '
                    . 'Si piden MÁS fotos o chicas nuevas: muéstralas también.';
            }
        }

        if (
            in_array('ubicacion', $yaEnviado, true)
            || in_array('ubicacion_precisa', $yaEnviado, true)
        ) {
            $directives[] = 'Ya se mandó la ubicación/maps antes, pero el cliente la pide otra vez. '
                . 'Sé amable: dile algo como "aqui te la paso otra vez cari 😊" '
                . 'e incluye location_url de nuevo. '
                . 'NO le digas que mire arriba. Pásasela otra vez sin problema.';
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

        // ── 10b. PROHIBICIÓN DE CATÁLOGO cuando hay selected_girl ──
        if ($selectedGirlName !== '') {
            $directives[] = "CLIENTE YA ELIGIÓ A {$selectedGirlName}. "
                . 'PROHIBIDO TOTAL: mencionar otras chicas, decir "mira que chicas tengo", '
                . 'ofrecer catálogo, usar photo_action="catalog", preguntar "cual prefieres", '
                . "ni sugerir que hay más chicas. Céntrate EXCLUSIVAMENTE en {$selectedGirlName}. "
                . "NUNCA escribas frases que empiecen con 'mira que chicas', 'tengo a', "
                . "'estas son', 'te presento a' ni similares.";
        }

        // ------------------------------------------------------------------ //
        //  11. Haggle (regateo) — from config + contextual                      //
        // ------------------------------------------------------------------ //
        $noRegateo = (bool) $this->config->get('prompt.sections.no_regateo', false);
        $haggleCount = isset($ctx['haggle_count_recent']) && is_int($ctx['haggle_count_recent'])
            ? $ctx['haggle_count_recent']
            : 0;

        if ($noRegateo) {
            $directives[] = 'REGATEO: NO aceptes regateos bajo ningún concepto. Precios fijos. Si el cliente insiste, corta la conversación educadamente.';
        } elseif ($haggleCount >= 3) {
            $msg = (string) $this->config->get('prompt.sections.regateo_3', 'si buscas mas barato no soy yo, suerte 😘');
            $directives[] = 'Regateo repetido (3+): ' . $msg . ' NO responder más sobre precios.';
        } elseif ($haggleCount >= 2) {
            $msg = (string) $this->config->get('prompt.sections.regateo_2', 'no puedo bajar mas amor, son los precios que tengo');
            $directives[] = 'Regateo (2º): ' . $msg;
        } elseif ($haggleCount >= 1) {
            $msg = (string) $this->config->get('prompt.sections.regateo_1', 'precio fijo cari, por eso la calidad es buena 😏');
            $directives[] = 'Regateo (1er): ' . $msg;
        }

        // ------------------------------------------------------------------ //
        //  12. NOVA: Post-maps ETA mode (rotating variants)                  //
        // ------------------------------------------------------------------ //
        $mapsSent          = !empty($ctx['maps_sent']);
        $mapsBeingSentNow  = !empty($ctx['maps_being_sent_now']);
        $etaFromUserFlag   = !empty($ctx['eta_from_user_flag']);

        // ── 12a. Preemptive: maps being sent RIGHT NOW → ETA en mismo mensaje ──
        if ($mapsBeingSentNow && !$etaFromUserFlag) {
            $locationUrl = (string) ($ctx['location_url'] ?? '');
            $directives[] = 'ATTA MODE: Vas a enviar el maps/ubicación AHORA MISMO. '
                . 'Incluye el location_url LITERAL y la petición de ETA EN EL MISMO MENSAJE. '
                . "IMPERATIVO: copia '{$locationUrl}' exactamente asi en user_visible_reply. "
                . 'NO envíes el maps sin preguntar ETA.';
        }

        // ── 12b. POST-MAPS ETA mode (rotating variants) ──
        if (($mapsSent || $mapsBeingSentNow) && !$etaFromUserFlag) {
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
        $directives[] = 'Sin auto-identificación: NUNCA digas "soy la encargada", "atiendo yo", "soy la que está aquí", "no soy [nombre]", "yo no soy", ni similares.';
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
