<?php

declare(strict_types=1);

namespace WasapBot\Pipeline;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\LoggerInterface;
use WasapBot\Core\MemoryInterface;
use WasapBot\Memory\SessionMemoryInterface;

/**
 * Assembles context for the LLM from memory, flags, and message content.
 *
 * IMPLEMENTA la filosofía del n8n "Format Memory" de forma DETERMINISTA:
 * - speaker_girl: quien HABLA (la 1ª chica mencionada en la sesión, NUNCA cambia)
 * - selected_girl: para qué chica es el servicio (sticky, cambia solo con intención explícita)
 * - wants_more_girls: si el cliente pide ver más chicas (persistente)
 * - shown_girls / unshown_girls: tracking de qué chicas ya se mostraron
 *
 * Pattern: node "Format Memory" + "Assemble Context (No-Merge)" from n8n workflow
 */
final class ContextAssembler implements PipelineStageInterface
{
    /** Máxima distancia Levenshtein para fuzzy matching de nombres */
    private const int FUZZY_LIMIT_SHORT = 1;  // nombres <= 6 chars
    private const int FUZZY_LIMIT_LONG  = 2;  // nombres > 6 chars

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?MemoryInterface $memory = null,
        private readonly ?SessionMemoryInterface $sessionMemory = null,
    ) {}

    public function process(array $ctx): ?array
    {
        // --- thread_id (compound: line + phone — Fix line-mixing bug) ---
        // Antes: thread_id = from_phone. Eso mezclaba conversaciones del mismo
        // cliente en distintas líneas WhatsApp. Ahora se prefija con la línea
        // (last9 del teléfono receptor) para aislar cada conversación.
        $threadId = $ctx['thread_id'] ?? $ctx['__thread_id'] ?? null;
        if ($threadId === null || $threadId === '') {
            $lineLast9 = (string) ($ctx['line_last9'] ?? '');
            $fromPhone = (string) ($ctx['from_phone'] ?? '');
            if ($fromPhone !== '') {
                $threadId = $lineLast9 !== '' ? ($lineLast9 . '_' . $fromPhone) : $fromPhone;
            }
            if ($threadId === '' || $threadId === null) {
                $threadId = 'th-' . floor(microtime(true) * 1000);
            }
            $ctx['thread_id'] = $threadId;
        } else {
            $ctx['thread_id'] = $threadId;
        }

        // --- memory_text ---
        $memoryText = $ctx['memory_text'] ?? '';
        if (!is_string($memoryText)) {
            $memoryText = '';
        }

        // --- bot_msg_count_recent ---
        $botMsgCount = 0;
        if ($memoryText !== '') {
            $botMsgCount = $this->countBotMessages($memoryText);
        }
        $ctx['bot_msg_count_recent'] = $botMsgCount;

        // ── NOVA 2026-06-17: human_msg_count (total de mensajes del humano en el hilo) ──
        // Se calcula más abajo, después de obtener $history filtrado.
        // Inicializado aquí para que esté disponible en todo el pipeline.

        // --- message text ---
        $messageText = (string) ($ctx['message_text'] ?? '');
        $normalizedText = mb_strtolower(trim($messageText), 'UTF-8');

        // ── NOVA: el cliente envió su ubicación (mensaje de tipo location) ──
        // El MessageExtractor fija is_location_i=1 y message_text='📍 Ubicación'.
        // Forzamos el tópico 'ubicacion' para que el flujo maps/ETA
        // (maps_being_sent_now + injectLocationUrl) se dispare de forma
        // determinista: enviar el mapa equivale a "quiero ir, dime dónde".
        $isLocationMsg = ((int) ($ctx['is_location_i'] ?? 0) === 1)
            || !empty($ctx['is_location']);
        if ($isLocationMsg) {
            $ctx['is_location']   = true;
            $ctx['is_location_i'] = 1;
            $normalizedText       = 'ubicacion';
        }

        // --- Coalesced text (burst) detection ---
        $coalesced = $ctx['__coalesced_text'] ?? '';
        $hasOpeningBurst = false;
        if (is_string($coalesced) && $coalesced !== '') {
            $hasOpeningBurst = $this->detectOpeningBurst($coalesced);
        }
        $ctx['__is_opening_burst'] = $hasOpeningBurst;

        // --- Obtener historial completo del hilo (para lógica determinista) ---
        $history = $this->sessionMemory !== null
            ? $this->sessionMemory->readThread($threadId)
            : [];

        // --- Ignorar placeholders _pending (webhook.php los escribe antes del pipeline) ---
        // Si no filtramos, el primer mensaje de cada conversación nueva tendría un
        // registro _pending en el historial → __is_new_conversation siempre sería false
        // → el gate de primer contacto en Bot.php nunca dispararía.
        $history = array_values(array_filter($history, static function (array $rec): bool {
            return empty($rec['_pending']);
        }));

        // ── NOVA 2026-06-17: contar mensajes reales del humano (no pending, no vacíos) ──
        $humanMsgCount = 0;
        foreach ($history as $rec) {
            if (!empty(trim((string) ($rec['user_msg'] ?? '')))) {
                $humanMsgCount++;
            }
        }
        // +1 por el mensaje actual que está siendo procesado
        $ctx['__human_msg_count'] = $humanMsgCount + 1;

        // --- Filtrado temporal (últimas 6h) ---
        $recentWindowH = (int) $this->config->get('memory.recent_window_hours', 6);
        $sessionReset = true;
        $recent = [];
        $now = time();
        foreach ($history as $rec) {
            $ts = strtotime((string) ($rec['ts'] ?? ''));
            if ($ts !== false && ($now - $ts) <= $recentWindowH * 3600) {
                $recent[] = $rec;
                $sessionReset = false;
            }
        }
        // Si no hay historial reciente, usar los últimos 20 de todo el historial
        if ($recent === []) {
            $recent = array_slice($history, -20);
        }

        // --- girls_config ---
        $girlsConfig = $ctx['girls_config'] ?? [];
        if (!is_array($girlsConfig)) {
            $girlsConfig = [];
        }
        $activeGirls = $this->filterActiveGirls($girlsConfig);

        // --- Detección de tópicos ---
        $ctx['topic_actual'] = $this->detectTopic($normalizedText);

        // --- tarifa detection ---
        $ctx['tarifa_elegida'] = $this->detectTarifaElegida($recent);

        // --- hot_curious_chat ---
        $ctx['hot_curious_chat_current'] = $this->detectHotCurious($normalizedText);

        // --- wants_more_girls (persistente pero con reset cuando se cumple) ---
        // El flag solo persiste si el cliente lo pidió Y el bot AÚN no ha enviado
        // fotos DESPUÉS de esa petición. Si ya se cumplió, se resetea para no
        // contaminar mensajes posteriores (ej: "quien eres?", "sandra").
        $wantsMoreCurrent = $this->detectWantsMoreGirls($normalizedText);
        $wantsMorePersisted = $this->hasWantsMoreInHistory($history);

        if ($wantsMoreCurrent) {
            $ctx['wants_more_girls'] = true;
        } elseif ($wantsMorePersisted) {
            // Solo mantener si la petición aún NO se ha cumplido (no se enviaron fotos tras ella)
            $wasFulfilled = $this->wantsMoreGirlsWasFulfilled($history);
            $ctx['wants_more_girls'] = !$wasFulfilled;
        } else {
            $ctx['wants_more_girls'] = false;
        }

        // --- last_bot_reply / last_user_message ---
        $ctx['last_bot_reply']    = $ctx['last_bot_reply']    ?? $this->lastBotReplyFromHistory($recent);
        $ctx['last_user_message'] = $ctx['last_user_message'] ?? $this->lastUserMsgFromHistory($recent);
        $ctx['last_user_meaningful'] = $this->lastUserMeaningfulFromHistory($history);

        // --- ya_enviado (qué info ya se mandó) ---
        $ctx['ya_enviado'] = $this->yaEnviadoFromHistory($history);

        // --- maps_sent ---
        $ctx['maps_sent'] = $this->detectMapsSent($history);

        // ================================================================
        // LÓGICA DETERMINISTA: speaker_girl vs selected_girl
        // (copiada del n8n "Format Memory")
        // ================================================================

        // --- SPEAKER GIRL: quien HABLA (fija, primera mención histórica, NUNCA cambia) ---
        $persistedSpeaker = $this->firstPersistedSpeakerGirl($history);
        $selInCurrent = $this->findMentionedGirl($normalizedText, $activeGirls);

        $speakerGirlName = '';
        $speakerGirlId   = '';

        if ($persistedSpeaker['name'] !== '') {
            // Ya hay speaker girl persistida: NUNCA la cambiamos
            $speakerGirlName = $persistedSpeaker['name'];
            $speakerGirlId   = $persistedSpeaker['id'];
        } else {
            // Primera sesión / sin speaker aún: detectar primera mención
            $firstSel = $selInCurrent
                ?? $this->findMentionedGirl(
                    $ctx['last_user_meaningful'] ?? '',
                    $activeGirls
                );
            if ($firstSel === null) {
                // Buscar en historial (más antigua primero)
                foreach ($recent as $rec) {
                    $um = (string) ($rec['user_msg'] ?? '');
                    $g = $this->findMentionedGirl($um, $activeGirls);
                    if ($g !== null) {
                        $firstSel = $g;
                        break;
                    }
                }
            }
            if ($firstSel !== null) {
                $speakerGirlName = (string) ($firstSel['nombre'] ?? '');
                $speakerGirlId   = (string) ($firstSel['id'] ?? '');
            }
        }

        // --- SELECTED GIRL: chica para el servicio (sticky, cambia solo con intención explícita) ---
        $persistedSelected = $this->lastPersistedSelectedGirl($history);

        $selectedGirlName = '';
        $selectedGirlId   = '';

        if ($persistedSelected['name'] !== '') {
            // Mantener selección previa
            $selectedGirlName = $persistedSelected['name'];
            $selectedGirlId   = $persistedSelected['id'];

            // Solo cambia si el mensaje actual menciona OTRA chica CON intención explícita de servicio
            if (
                $selInCurrent !== null
                && $this->normalizeStr($selInCurrent['nombre'] ?? '') !== $this->normalizeStr($persistedSelected['name'])
                && $this->isExplicitServiceChoice($normalizedText)
            ) {
                $selectedGirlName = (string) ($selInCurrent['nombre'] ?? '');
                $selectedGirlId   = (string) ($selInCurrent['id'] ?? '');
            }
        } else {
            // Sin selección previa: cualquier primera mención vale
            $firstSel = $selInCurrent
                ?? $this->findMentionedGirl(
                    $ctx['last_user_meaningful'] ?? '',
                    $activeGirls
                );
            if ($firstSel === null) {
                // Buscar en historial (más reciente primero)
                for ($i = count($recent) - 1; $i >= 0; $i--) {
                    $um = (string) ($recent[$i]['user_msg'] ?? '');
                    $g = $this->findMentionedGirl($um, $activeGirls);
                    if ($g !== null) {
                        $firstSel = $g;
                        break;
                    }
                }
            }
            if ($firstSel !== null) {
                $selectedGirlName = (string) ($firstSel['nombre'] ?? '');
                $selectedGirlId   = (string) ($firstSel['id'] ?? '');
            }
        }

        // --- speaker_mode: depende de speaker_girl (quién habla) ---
        $speakerMode = ($speakerGirlName !== '') ? 'chica' : 'encargada';

        // ── NOVA: __is_ad_intro ───────────────────────────────────────────
        // Si el primer mensaje del cliente contiene una URL de anuncio,
        // viene buscando a UNA chica concreta. NO debemos asignar speaker_mode
        // 'chica' todavía para que el first-contact gate de Bot.php pueda
        // saludar sin catálogo.
        $isAdIntro = false;
        if ($recent === [] && $history === []) {
            $adUrls = [
                'nuevapasion\.com\/anuncio',
                'milanuncios\.com\/contacto',
                'adultguia\.com',
                'destacamos\.com',
                'pasions\.com',
                'sexoservicios\.com',
                'mundosexanuncio\.com',
                'm\.mundosexanuncio\.com',
                'slumi\.com',
                'erosguia\.com',
                'photokines\.com',
            ];
            if (preg_match('/' . implode('|', $adUrls) . '/i', $messageText)) {
                $isAdIntro = true;
                // Si la chica del anuncio se detectó por nombre en el texto,
                // la guardamos como speaker pero MANTENEMOS speaker_mode='encargada'
                // para que el gate de first-contact dispare (solo saludo, sin catálogo).
                // El speaker_girl se usará en mensajes posteriores cuando se confirme.
                //
                // NOVA FIX: si YA hay historial de conversación con una chica
                // identificada, NO forzar speaker_mode='encargada' — eso
                // causaría que el bot pierda la identidad de la chica y
                // vuelva a ofrecer catálogo a un cliente que ya eligió.
                $hasPriorSpeaker = false;
                foreach ($history as $rec) {
                    $sn = (string) ($rec['speaker_girl_name'] ?? '');
                    if ($sn !== '' && $sn === $speakerGirlName) {
                        $hasPriorSpeaker = true;
                        break;
                    }
                }
                if ($speakerGirlName !== '' && !$hasPriorSpeaker) {
                    // Guardar la chica detectada pero mantener modo encargada
                    // para evitar que el bot envíe catálogo en el primer mensaje
                    $speakerMode = 'encargada';
                }
            }
        }
        $ctx['__is_ad_intro'] = $isAdIntro;

        // Escribir resultados deterministas en $ctx
        $ctx['speaker_girl_id']     = $speakerGirlId;
        $ctx['speaker_girl_name']   = $speakerGirlName;
        $ctx['selected_girl_id']    = $selectedGirlId;
        $ctx['selected_girl_name']  = $selectedGirlName;
        $ctx['speaker_mode']        = $speakerMode;

        // --- additional flags ---
        $ctx['must_choose_girl_now'] = ($selectedGirlName === '')
            && ($this->userWantsMapWords($normalizedText)
                || $ctx['topic_actual'] === 'ubicacion'
                || $ctx['topic_actual'] === 'cita-eta');

        $ctx['photos_sent_recent'] = $this->detectPhotosSentRecent($recent, $recentWindowH);
        $ctx['session_reset'] = $sessionReset;

        // --- Track shown / unshown girls (FOTOS_V3) ---
        [$shownGirls, $unshownGirls] = $this->computeShownUnshown($history, $ctx, $activeGirls);
        $ctx['shown_girls']   = $shownGirls;
        $ctx['unshown_girls'] = $unshownGirls;
        $ctx['__total_active_girls'] = count($activeGirls);

        // --- conversation_end_intent ---
        $ctx['conversation_end_intent'] = $this->detectConversationEndIntent($normalizedText);

        // --- interes_fuerte / ubicacion_pedida_fuerte ---
        $ctx['interes_fuerte'] = $this->detectInteresFuerte($normalizedText);
        $ctx['ubicacion_pedida_fuerte'] = $this->userWantsMapWords($normalizedText);

        // --- recent_bot_replies_norm: build from session history (overrides Bot.php basic version) ---
        $ctx['recent_bot_replies_norm'] = $this->buildRecentBotRepliesNorm($recent);

        // --- __has_greeted: detect if bot already greeted in this session ---
        $ctx['__has_greeted'] = $this->hasBotGreetedInHistory($history);

        // ── NOVA: eta_from_user ──────────────────────────────────────────
        $etaMinutes = $this->extractEtaMinutes($messageText);
        $ctx['eta_from_user_minutes'] = $etaMinutes;
        $ctx['eta_from_user_flag']    = $etaMinutes > 0;

        // ── NOVA: choose_loop_count (indecisión del cliente) ──────────────
        $ctx['choose_loop_count'] = $this->computeChooseLoopCount($history, $selectedGirlName);

        // ── NOVA: photo_insist_count & location_insist_count ─────────────
        // Cuenta cuántas veces ha pedido el usuario fotos/ubicación en
        // TODA la conversación. Si >= 2 y ya_enviado tiene el flag, el bot
        // cede y reenvía en lugar de decir "scrollea arriba".
        $ctx['photo_insist_count']    = $this->computePhotoInsistCount($history);
        $ctx['location_insist_count'] = $this->computeLocationInsistCount($history);

        // ── NOVA: info_pack_ready ─────────────────────────────────────────
        $yaEnviado = $ctx['ya_enviado'] ?? [];
        $pricesSent = in_array('precios', $yaEnviado, true);
        $exactLocationSent = $ctx['maps_sent'] ?? false;
        $girlSelected = $selectedGirlName !== '';
        $ctx['info_pack_ready'] = $girlSelected && $pricesSent && $exactLocationSent;

        // ── NOVA: maps_being_sent_now ────────────────────────────────────
        // Predict if maps is being sent in THIS response (preemptive ETA mode).
        // NOVA FIX 2026-06-17: añadir PRECONDICIÓN de que ya se hayan enviado
        // fotos O precios antes de permitir enviar el mapa. Evita que el bot
        // suelte la dirección en el 2º o 3º mensaje sin haber mostrado nada.
        $photosSent = in_array('fotos', $yaEnviado, true);
        $pricesSent = in_array('precios', $yaEnviado, true);
        $mapsBeingSentNow = false;
        if (!$exactLocationSent && $selectedGirlName !== '') {
            $userAsksLocation = $ctx['topic_actual'] === 'ubicacion'
                || !empty($ctx['ubicacion_pedida_fuerte'])
                || $this->userWantsMapWords($normalizedText);
            // ⛔ Solo activar si el cliente YA ha recibido fotos o precios.
            //    Si no se ha enviado nada, no activar el flag (el LLM decidirá).
            if ($userAsksLocation && ($photosSent || $pricesSent)) {
                $mapsBeingSentNow = true;
            }
        }
        $ctx['maps_being_sent_now'] = $mapsBeingSentNow;

        // Update info_pack_ready to consider maps being sent now (preemptive)
        // NOVA FIX: solo activar info_pack_ready si hay señal real de intención (ETA o interés fuerte).
        // Evita que el bot entre en modo "cierre suave" solo porque el cliente preguntó "dónde estás".
        $interesFuerte = !empty($ctx['interes_fuerte']);
        $etaFromUser = !empty($ctx['eta_from_user_flag']);
        if ($mapsBeingSentNow && ($interesFuerte || $etaFromUser)) {
            $ctx['info_pack_ready'] = $girlSelected && $pricesSent; // location is being sent now
        }

        // ── NOVA: is_image_sent_by_user ───────────────────────────────────
        $ctx['is_image_sent_by_user'] = ($ctx['is_image_i'] ?? 0) === 1
            || !empty($ctx['__is_image']);

        // ── MAPS: location_url from config (para que el AI tenga la URL real de Google Maps) ──
        $ctx['location_url'] = $this->config->get('urls.google_maps_location', '');

        // ── NOVA: __is_new_conversation ──────────────────────────────────────
        // True when there's no history AND no recent messages. Used by ToneBuilder
        // to trigger proactive catalog on first contact.
        $ctx['__is_new_conversation'] = ($recent === [] && $history === []);

        // ── NOVA B2: catalog_count — cuántas veces se ha mostrado catálogo ──
        // (todas las chicas juntas) en esta conversación. Si >= 2, prohibir reenvío.
        $catalogCount = 0;
        foreach ($history as $rec) {
            $shown = $rec['shown_girls'] ?? [];
            if (is_array($shown) && count($shown) >= 2) {
                $catalogCount++;
            }
        }
        $ctx['catalog_count'] = $catalogCount;

        // ── NOVA B3: photo_rejected — cliente rechazó explícitamente las fotos ──
        $photoRejected = false;
        $rejectPatterns = [
            '/\bno\s+(?:es|son)\s+(?:esa|esa chica|las|ellas)\b/i',
            '/\bno\s+(?:est[aá]|est[aá]n)\b/i',
            '/\bno\s+me\s+gusta\b/i',
            '/\bya\s+(?:las?\s+)?(?:vi|he\s+visto)\b/i',
            '/\bno\s+(?:es|era)\s+(?:lo|eso)\s+que\b/i',
            '/\bno\s+estoy\s+interesado\b/i',
        ];
        foreach ($rejectPatterns as $p) {
            if (preg_match($p, $normalizedText)) {
                $photoRejected = true;
                break;
            }
        }
        // También revisar si el historial tiene rechazo previo (persistente)
        if (!$photoRejected) {
            foreach ($history as $rec) {
                $um = (string) ($rec['user_msg'] ?? '');
                foreach ($rejectPatterns as $p) {
                    if (preg_match($p, $um)) {
                        $photoRejected = true;
                        break 2;
                    }
                }
            }
        }
        $ctx['photo_rejected'] = $photoRejected;

        // ── NOVA B4: __filler_loop_count — monosílabos consecutivos ──
        $fillerLoopCount = 0;
        $fillerPatterns = '/\b(ok|vale|oka|oki|vle|okey|dime|dime\s*algo|dimelo)\b/i';
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $um = (string) ($history[$i]['user_msg'] ?? '');
            if ($um === '') continue;
            if (preg_match($fillerPatterns, $this->normalizeStr($um)) || mb_strlen(trim($um)) <= 3) {
                $fillerLoopCount++;
            } else {
                break;
            }
        }
        // También contar el mensaje actual
        if (preg_match($fillerPatterns, $normalizedText) || mb_strlen(trim($normalizedText)) <= 3) {
            $fillerLoopCount++;
        }
        $ctx['__filler_loop_count'] = $fillerLoopCount;

        // ── B0: Pace — user response time (seconds since last bot reply) ──
        $paceCfg = $this->config->get('human_delays.pace', []);
        $paceRef = (float) (is_array($paceCfg) ? ($paceCfg['reference_sec'] ?? 60) : 60);
        $userResponseTimeSec = $paceRef; // default: neutral
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $reply = (string) ($history[$i]['bot_reply'] ?? '');
            if ($reply !== '') {
                $ts = strtotime((string) ($history[$i]['ts'] ?? ''));
                if ($ts !== false) {
                    $userResponseTimeSec = max(1.0, (float) (time() - $ts));
                }
                break;
            }
        }
        $ctx['user_response_time_sec'] = $userResponseTimeSec;

        // ── B9: Burst detection — 3+ user messages in last N seconds ──
        $burstCfg = $this->config->get('human_delays.burst', []);
        $burstWindow    = (int) (is_array($burstCfg) ? ($burstCfg['window_sec']    ?? 30) : 30);
        $burstThreshold = (int) (is_array($burstCfg) ? ($burstCfg['threshold_msgs'] ?? 3)  : 3);
        $burstCount = 0;
        $now = time();
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $ts = strtotime((string) ($history[$i]['ts'] ?? ''));
            if ($ts === false || ($now - $ts) > $burstWindow) {
                break;
            }
            $msg = (string) ($history[$i]['user_msg'] ?? '');
            if ($msg !== '') {
                $burstCount++;
            }
        }
        $ctx['__is_burst'] = ($burstCount >= $burstThreshold);

        // ── B10: Urgent detection — user says "rápido", "date prisa", etc. ──
        $urgentPatterns = '/\br[aá]pido\b|\bdate\s+prisa\b|\bcontesta\s+ya\b|\bresponde\s+ya\b|\bdime\s+ya\b|\btengo\s+prisa\b|\bvoy\s+con\s+prisa\b|\bestoy\s+esperando\b|\?{3,}|!{3,}/iu';
        $ctx['__is_urgent'] = (bool) preg_match($urgentPatterns, $normalizedText);

        // ── NOVA B5: Cross-line context merge ─────────────────────────────
        // Si el mismo teléfono ha hablado con nosotros por otra línea,
        // copiamos speaker_girl, selected_girl y ya_enviado para mantener
        // coherencia de conversación entre líneas.
        if ($ctx['__is_new_conversation'] && $this->sessionMemory !== null) {
            $fromPhone = (string) ($ctx['from_phone'] ?? '');
            if ($fromPhone !== '') {
                $merged = $this->mergeContextFromOtherLines($fromPhone, $ctx['thread_id'] ?? '', $ctx);
                if ($merged !== null) {
                    $ctx = $merged;
                }
            }
        }

        // ── NOVA B6: Conversation end detection (non-blocking hint) ──
        // Explicit farewell flag: informs the LLM but does NOT halt the pipeline.
        // The LLM decides whether to close the conversation or keep going.
        $endIntent = $ctx['conversation_end_intent'] ?? false;
        if ($endIntent) {
            $ctx['__conversation_ended'] = true;
        }

        // ── NOVA B6.5: Pending questions from client ─────────────────────
        // Detecta si el cliente hizo preguntas que aún no han sido respondidas.
        // Esto evita que el LLM ignore preguntas sustantivas cuando llegan
        // mensajes filler adicionales.
        $ctx['preguntas_pendientes'] = $this->detectPendingQuestions($history, $ctx);

        // ── NOVA B6.6: Bot confusion count ─────────────────────────────
        // Cuenta cuántas veces el bot ha expresado confusión en sus
        // últimas respuestas. Si >= 1, ToneBuilder inyecta directiva
        // anti-confusión para romper el bucle.
        $ctx['__bot_confusion_count'] = $this->countBotConfusion($history);

        // ── NOVA B7: Sticky state fallback ───────────────────────────
        // If the current iteration lost speaker/selected girl state
        // (e.g., due to cross-line merge returning null, ad_intro forcing
        // encargada, or ambiguous message), restore from the most recent
        // record in history that has speaker/selected set.
        $currentSpeaker  = trim((string) ($ctx['speaker_girl_name'] ?? ''));
        $currentSelected = trim((string) ($ctx['selected_girl_name'] ?? ''));

        if ($currentSpeaker === '' || $currentSelected === '') {
            for ($i = count($history) - 1; $i >= 0; $i--) {
                $rec = $history[$i];
                $hSpeaker  = trim((string) ($rec['speaker_girl_name'] ?? ''));
                $hSelected = trim((string) ($rec['selected_girl_name'] ?? ''));
                $hMode     = (string) ($rec['speaker_mode'] ?? '');

                if ($currentSpeaker === '' && $hSpeaker !== '') {
                    $ctx['speaker_girl_name'] = $hSpeaker;
                    $ctx['speaker_girl_id']   = (string) ($rec['speaker_girl_id'] ?? '');
                    if ($hMode === 'chica') {
                        $ctx['speaker_mode'] = 'chica';
                    }
                    if ($this->logger !== null) {
                        $this->logger->info('ContextAssembler: sticky state — restored speaker from history', [
                            'speaker' => $hSpeaker,
                            'phone'   => $ctx['from_phone'] ?? '?',
                        ]);
                    }
                    break; // Found speaker, stop scanning
                }
            }

            for ($i = count($history) - 1; $i >= 0; $i--) {
                $rec = $history[$i];
                $hSelected = trim((string) ($rec['selected_girl_name'] ?? ''));

                if ($currentSelected === '' && $hSelected !== '') {
                    $ctx['selected_girl_name'] = $hSelected;
                    $ctx['selected_girl_id']   = (string) ($rec['selected_girl_id'] ?? '');
                    if ($this->logger !== null) {
                        $this->logger->info('ContextAssembler: sticky state — restored selected from history', [
                            'selected' => $hSelected,
                            'phone'    => $ctx['from_phone'] ?? '?',
                        ]);
                    }
                    break;
                }
            }
        }

        return $ctx;
    }

    public function name(): string
    {
        return 'ContextAssembler';
    }

    // ==================================================================
    // HELPERS — Girl name matching
    // ==================================================================

    /**
     * Encontrar una chica mencionada en el texto.
     * 3-pass: exact match → multi-part match → fuzzy Levenshtein.
     *
     * @param string $text
     * @param list<array<string, mixed>> $activeGirls
     * @return array<string, mixed>|null
     */
    private function findMentionedGirl(string $text, array $activeGirls): ?array
    {
        $t = $this->normalizeStr($text);
        if ($t === '') {
            return null;
        }

        $tokens = array_filter(explode(' ', $t), fn(string $s) => $s !== '');

        // PASS 1: exact regex match
        foreach ($activeGirls as $g) {
            $name = (string) ($g['nombre'] ?? '');
            if ($name === '') continue;
            $n = $this->normalizeStr($name);
            if ($n === '') continue;
            $esc = preg_quote($n, '/');
            if (preg_match('/(^|[^a-z0-9])' . $esc . '([^a-z0-9]|$)/i', $t)) {
                return $g;
            }
        }

        // PASS 2: multi-part name match
        foreach ($activeGirls as $g) {
            $name = (string) ($g['nombre'] ?? '');
            if ($name === '') continue;
            $n = $this->normalizeStr($name);
            $parts = array_filter(explode(' ', $n), fn(string $s) => $s !== '');
            if (count($parts) >= 2) {
                $ok = true;
                foreach ($parts as $part) {
                    $esc = preg_quote($part, '/');
                    if (!preg_match('/(^|[^a-z0-9])' . $esc . '([^a-z0-9]|$)/i', $t)) {
                        $ok = false;
                        break;
                    }
                }
                if ($ok) return $g;
            }
        }

        // PASS 3: fuzzy Levenshtein (solo nombres >= 4 chars)
        foreach ($activeGirls as $g) {
            $name = (string) ($g['nombre'] ?? '');
            if ($name === '') continue;
            $n = $this->normalizeStr($name);
            $nameParts = array_filter(explode(' ', $n), fn(string $s) => $s !== '');
            $base = $nameParts[0] ?? $n;
            if (mb_strlen($base) >= 4) {
                foreach ($tokens as $tok) {
                    if (mb_strlen($tok) < 3) continue;
                    if (mb_substr($tok, 0, 1) !== mb_substr($base, 0, 1)) continue;
                    $lim = (mb_strlen($base) <= 6) ? self::FUZZY_LIMIT_SHORT : self::FUZZY_LIMIT_LONG;
                    if ($this->levenshteinLimit($tok, $base, $lim) <= $lim) {
                        return $g;
                    }
                }
            }
        }

        return null;
    }

    /**
     * ¿El mensaje actual indica intención EXPLÍCITA de elegir chica para servicio?
     */
    /**
     * Check if user explicitly chose a girl for service.
     *
     * RELAXED: The regex is intentionally permissive — it accepts any mention
     * of a girl name combined with minimal intent words. The LLM will later
     * confirm via the girl_selection_intent field in its JSON response.
     *
     * If the regex doesn't match but the LLM says girl_selection_intent=true,
     * the post-LLM handler in Bot.php will override.
     */
    private function isExplicitServiceChoice(string $normalizedText): bool
    {
        if ($normalizedText === '') return false;
        return (bool) preg_match(
            '/\b(quiero\s+ir\s+con|quiero\s+a\s+la\s+|quiero\s+con'
            . '|me\s+quedo\s+con|me\s+gusta\s+|prefiero\s+(a\s+)?|elijo\s+a\s+'
            . '|me\s+mola\s+|voy\s+con|reservo\s+con|cita\s+con)\b/iu',
            $normalizedText
        );
    }

    // ==================================================================
    // HELPERS — History analysis (persisted speaker/selected)
    // ==================================================================

    /**
     * Primera speaker_girl persistida en el historial (la más antigua).
     *
     * @param list<array<string, mixed>> $records
     * @return array{name: string, id: string}
     */
    private function firstPersistedSpeakerGirl(array $records): array
    {
        foreach ($records as $rec) {
            $name = (string) ($rec['speaker_girl_name'] ?? '');
            $id   = (string) ($rec['speaker_girl_id'] ?? '');
            if ($name !== '') {
                return ['name' => $name, 'id' => $id];
            }
        }
        return ['name' => '', 'id' => ''];
    }

    /**
     * Última selected_girl persistida en el historial.
     *
     * @param list<array<string, mixed>> $records
     * @return array{name: string, id: string}
     */
    private function lastPersistedSelectedGirl(array $records): array
    {
        for ($i = count($records) - 1; $i >= 0; $i--) {
            $name = (string) ($records[$i]['selected_girl_name'] ?? '');
            $id   = (string) ($records[$i]['selected_girl_id'] ?? '');
            if ($name !== '') {
                return ['name' => $name, 'id' => $id];
            }
        }
        return ['name' => '', 'id' => ''];
    }

    /**
     * ¿El historial ya tiene wants_more_girls=true?
     */
    /** @param array<int, array<string, mixed>> $records */
    private function hasWantsMoreInHistory(array $records): bool
    {
        foreach ($records as $rec) {
            if (!empty($rec['wants_more_girls'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * ¿La petición de wants_more_girls YA fue cumplida?
     *
     * Busca el registro más reciente con wants_more_girls=true y comprueba
     * si alguna respuesta del bot POSTERIOR a ese registro contiene fotos.
     * Si es así, la petición está cumplida y el flag debe resetearse.
     *
     * Esto evita que el flag persista a través de mensajes intermedios
     * (ej: seq 638 "mas fotos de sandra" → cumplido en seq 638,
     * pero seq 639 "pues ella" y seq 640 "y las tarifas" no tienen fotos,
     * lo que antes reactivaba el flag en seq 641).
     */
    /** @param array<int, array<string, mixed>> $records */
    private function wantsMoreGirlsWasFulfilled(array $records): bool
    {
        // 1. Encontrar el registro más reciente con wants_more_girls=true
        $triggerIdx = -1;
        for ($i = count($records) - 1; $i >= 0; $i--) {
            if (!empty($records[$i]['wants_more_girls'])) {
                $triggerIdx = $i;
                break;
            }
        }
        if ($triggerIdx < 0) {
            return false; // No hay petición de wants_more_girls en el historial
        }

        // 2. Comprobar si alguna respuesta del bot DESDE ese registro contiene fotos
        for ($i = $triggerIdx; $i < count($records); $i++) {
            $reply = (string) ($records[$i]['bot_reply'] ?? '');
            if ($reply === '') continue;
            if (preg_match(
                '/https?:\/\/(?:compartir\.site|i\.ibb\.co|ibb\.co|i\.imgur\.com|imgur\.com)/i',
                $reply
            )) {
                return true; // Fotos enviadas → petición cumplida
            }
        }
        return false; // Petición pendiente de cumplir
    }

    // ==================================================================
    // HELPERS — Last messages from history
    // ==================================================================

    /** @param array<int, array<string, mixed>> $recent */
    private function lastBotReplyFromHistory(array $recent): ?string
    {
        for ($i = count($recent) - 1; $i >= 0; $i--) {
            $reply = (string) ($recent[$i]['bot_reply'] ?? $recent[$i]['reply_text'] ?? '');
            if ($reply !== '') return $reply;
        }
        return null;
    }

    /** @param array<int, array<string, mixed>> $recent */
    private function lastUserMsgFromHistory(array $recent): ?string
    {
        for ($i = count($recent) - 1; $i >= 0; $i--) {
            $msg = (string) ($recent[$i]['user_msg'] ?? $recent[$i]['user_message'] ?? '');
            if ($msg !== '') return $msg;
        }
        return null;
    }

    /** @param array<int, array<string, mixed>> $history */
    private function lastUserMeaningfulFromHistory(array $history): string
    {
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $msg = (string) ($history[$i]['user_msg'] ?? $history[$i]['user_message'] ?? '');
            if ($msg === '') continue;
            if (!$this->isFillerUser($msg)) return $msg;
        }
        return '';
    }

    // ==================================================================
    // HELPERS — Topic / tariff / hot-curious / wants-more detection
    // ==================================================================

    private function detectTopic(string $normalizedText): string
    {
        $topics = [
            'precios'   => '/\b(precios?|precio|cu[aá]nto|cuanto\s+(vale|cuesta|sale)|tarifas?|euros?|€|cuanto\s+es)\b/i',
            'ubicacion' => '/\b(d[oó]nde|ubicaci[oó]n|ubicados?|direcci[oó]n|calle|zona|cerca|lejos|donde\s+(est[aá]n?|qued[aá]is?|estais?)|maps?|ubicame|ubicame|llegar|como\s+llego)\b/i',
            'servicios' => '/\b(servicios?|franc[eé]s|griego|besos?|caricias|completo|natural|sin\s+preservativo|que\s+(hacen|haceis|ofrecen))\b/i',
            'pago'      => '/\b(pago|pagar|efectivo|tarjeta|bizum|transferencia|cobr[aá]is|acept[aá]is)\b/i',
            'cita-eta'  => '/\b(cu[aá]ndo|hora|cita|cu[aá]nto\s+tiempo|tard[aá]is?|disponible|disponibilidad|libre|quedamos|voy|llego|estoy\s+(cerca|llegando)|en\s+cu[aá]nto)\b/i',
            'smalltalk' => '/\b(hola|buenas|hey|ola)\b/i',
        ];

        foreach ($topics as $topic => $pattern) {
            if (preg_match($pattern, $normalizedText)) {
                return $topic;
            }
        }
        return 'otro';
    }

    /** @param array<int, array<string, mixed>> $recent */
    private function detectTarifaElegida(array $recent): string
    {
        for ($i = count($recent) - 1; $i >= 0; $i--) {
            $raw = (string) ($recent[$i]['user_msg'] ?? $recent[$i]['user_message'] ?? '');
            $u = $this->normalizeStr($raw);
            if ($u === '') continue;

            $hasAcepta = (bool) preg_match('/\b(vale|ok|de\s+acuerdo|me\s+vale|me\s+cuadra|perfecto|cojo|quiero|me\s+quedo|pillo|prefiero)\b/iu', $u);
            $msgCorta = (bool) preg_match('/^[0-9a-z€\s]{1,25}$/iu', $u);
            $acepta = $hasAcepta || $msgCorta;

            if ($acepta && preg_match('/\b40\s*(?:euros?|eur|€)?\b/iu', $u)) return '40';
            if ($acepta && preg_match('/\b30\s*(?:euros?|eur|€)?\b/iu', $u)) return '40'; // legacy: 30 ya no existe, redirigir a 40
            if ($acepta && preg_match('/\b50\s*(?:euros?|eur|€)?\b/iu', $u)) return '50';
            if ($acepta && preg_match('/\b100\s*(?:euros?|eur|€)?\b/iu', $u)) return '100';

            if ($acepta && preg_match('/(?:rapid|rapidito|10\s*min|diez\s*min)/iu', $u)) return '40';
            if ($acepta && preg_match('/(?:media\s*h|mediahora|media\s+hora|30\s*min)/iu', $u)) return '50';
            if ($acepta && preg_match('/(?:una\s+hora|la\s+hora|1h|60\s*min)/iu', $u)) return '100';
        }
        return '';
    }

    private function detectHotCurious(string $normalizedText): bool
    {
        $hotWords = [
            'tetas', 'culos?', 'culo', 'cachonda', 'cachondo', 'guarrilla', 'guarro',
            'zorra', 'puta', 'polla', 'verga', 'coño', 'cojones', 'follar', 'follamos',
            'sexo', 'sexual', 'chupar', 'chuparla', 'mamada', 'petada', 'corrida',
            'cuerpo', 'buenorra', 'buenorro', 'guapa', 'guapo', 'bonita', 'bonito',
            'hermosa', 'hermoso', 'preciosa', 'precioso', 'rica', 'rico', 'belleza',
            'caliente', 'calentura', 'mojada', 'mojado', 'duro', 'dura', 'empalmado',
            'empalme', 'pajear', 'paja', 'desnuda', 'desnudo', 'foto', 'fotos',
            'video', 'videos', 'intimo', 'intima', 'cama', 'culo', 'nalgas',
            'pecho', 'pechos', 'teta', 'rabbo', 'rabo',
        ];
        foreach ($hotWords as $word) {
            if (preg_match('/\b' . $word . '\b/i', $normalizedText)) {
                return true;
            }
        }
        return false;
    }

    private function detectWantsMoreGirls(string $normalizedText): bool
    {
        $patterns = [
            '/\btodas\s+las\s+chicas?\b/i',
            '/\bhay\s+m[aá]s\b/i',
            '/\bens[eé][ñn]ame\s+todas\b/i',
            '/\bmu[eé]strame\s+todas\b/i',
            '/\bquiero\s+ver\s+(todas|m[aá]s)\b/i',
            '/\bver\s+(todas|m[aá]s)\s+chicas?\b/i',
            '/\bcat[aá]logo\s+completo\b/i',
            '/\btodas\b.*\bchicas\b/i',
            '/\bcu[aá]ntas\s+(chicas|hay)\b/i',
            '/\b(?:y\s+las?\s+dem[aá]s|y\s+qu[eé]\s+m[aá]s)\b/i',
            '/\b(?:alguna|otra)\s+m[aá]s\b/i',
            '/\bens[eé][ñn]ame\s+(?:m[aá]s|todas)\b/i',
            '/\bmu[eé]strame\s+m[aá]s\b/i',
            '/\bmas\s+chicas\b/i',
            '/\botras?\s+chicas?\b/i',
            '/\blas?\s+dem[aá]s\b/i',
            '/\bquiero\s+ver\s+las\s+dem[aá]s\b/i',
            '/\bm[aá]s\s+fotos\b/i',
            '/\bsolo\s+me\s+mandaste\b/i',
            '/\bme\s+faltan\b/i',
            '/\bpasame\s+el\s+resto\b/i',
            // NOVA: natural Spanish patterns ("las otras", "envíame las"...)
            '/\blas\s+otras?\b/i',
            '/\blas?\s+que\s+faltan?\b/i',
            '/\bel\s+resto\b/i',
            '/\b(?:env[ií]ame|m[aá]ndame|p[aá]same)\s+(?:las|el|los)\b/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $normalizedText)) {
                return true;
            }
        }
        return false;
    }

    // ==================================================================
    // HELPERS — ya_enviado, maps_sent, photos_sent
    // ==================================================================

    /** @param array<int, array<string, mixed>> $records @return list<string> */
    private function yaEnviadoFromHistory(array $records): array
    {
        $flags = [];
        foreach ($records as $rec) {
            $replyRaw = (string) ($rec['bot_reply'] ?? $rec['reply_text'] ?? '');
            $replyNorm = $this->normalizeStr($replyRaw);
            if ($replyRaw === '') continue;

            if (preg_match('/\b(?:30\s*€|40\s*€|1h|50\s*€|100\s*€|tarifa|precio|precios)\b/iu', $replyNorm)) {
                $flags[] = 'precios';
            }

            $hasMap = (bool) preg_match('/(?:https?:\/\/)?(?:goo\.gl\/maps|maps\.app\.goo\.gl|google\.com\/maps|maps\.google\.com)/i', $replyRaw);
            // Solo marcar "ubicacion" si se envió una URL de maps real (no basta con mencionar la palabra)
            if ($hasMap) {
                $flags[] = 'ubicacion';
                $flags[] = 'ubicacion_precisa';
            }

            $hasPhoto = (bool) preg_match('/(?:https?:\/\/(?:compartir\.site|ibb\.co|i\.ibb\.co)\/)/i', $replyRaw);
            $ts = strtotime((string) ($rec['ts'] ?? ''));
            $isRecent = $ts !== false && (time() - $ts) <= 6 * 3600;
            if ($hasPhoto && $isRecent) {
                $flags[] = 'fotos';
            }

            if (preg_match('/detalle|incluye|ofrezco|ofrece|servici/iu', $replyNorm)) {
                $flags[] = 'servicios';
            }

            // NOVA: detect scarcity tactic already used
            if (preg_match('/poca disponib|alguien preguntando|quedan pocas horas|hay alguien mas|se esta llenando/iu', $replyNorm)) {
                $flags[] = 'escasez';
            }
        }
        return array_values(array_unique($flags));
    }

    /** @param array<int, array<string, mixed>> $records */
    private function detectMapsSent(array $records): bool
    {
        $re = '/(?:https?:\/\/)?(?:goo\.gl\/maps|maps\.app\.goo\.gl|google\.com\/maps|maps\.google\.com)/i';
        foreach ($records as $rec) {
            if (preg_match($re, (string) ($rec['bot_reply'] ?? $rec['reply_text'] ?? ''))) {
                return true;
            }
        }
        return false;
    }

    /** @param array<int, array<string, mixed>> $recent */
    private function detectPhotosSentRecent(array $recent, int $windowH): bool
    {
        foreach ($recent as $rec) {
            $replyRaw = (string) ($rec['bot_reply'] ?? $rec['reply_text'] ?? '');
            if (preg_match('/(?:https?:\/\/(?:ibb\.co|i\.ibb\.co)\/)/i', $replyRaw)) {
                $ts = strtotime((string) ($rec['ts'] ?? ''));
                if ($ts !== false && (time() - $ts) <= $windowH * 3600) {
                    return true;
                }
            }
        }
        return false;
    }

    // ==================================================================
    // HELPERS — User intent detection
    // ==================================================================

    private function userWantsMapWords(string $normalizedText): bool
    {
        return (bool) preg_match(
            '/(\bubi\b|ubic|maps\b|mapa\b|direccion|pin\b|punto\s+exacto|ubicacion\s*real|pasame\s+la\s+ubi|pasa\s+el\s+maps|mandame\s+la\s+direccion)/iu',
            $normalizedText
        );
    }

    private function detectConversationEndIntent(string $normalizedText): bool
    {
        return (bool) preg_match(
            '/(adios|hasta\s*luego|hasta\s*ahora|me\s*voy|otro\s*dia|luego\s*hablamos|ya\s*te\s*digo|gracias\s*y\s*perdon|vale\s*gracias|ok\s*gracias'
            . '|chao|bye|nos\s*vemos|me\s*despido|te\s*llamo\s*mañana|hablamos\s*mañana|me\s*voy\s*ya|suerte|buenas\s*noches|hasta\s*mañana)/iu',
            $normalizedText
        );
    }

    /**
     * Cuenta cuántas de las últimas respuestas del bot expresan confusión.
     *
     * @param list<array<string, mixed>> $history
     * @return int Número de respuestas del bot con patrones de confusión
     */
    private function countBotConfusion(array $history): int
    {
        $count = 0;
        $confusionRegex = '/\b(?:no\s+(?:entiendo|entend[ií]|te\s+entiendo|te\s+entend[ií]|te\s+he\s+entendido|s[eé]\b|se\b|se\s+que|tengo\s+ni\s+idea)|'
            . 'eso\s+no\s+(?:es\s+lo\s+m[ií]o|te\s+lo\s+s[eé])|'
            . 'de\s+eso\s+no\s+(?:entiendo|entend[ií]|s[eé]|tengo\s+ni\s+idea))\b/iu';

        // Scan from most recent backwards, count consecutive confusion
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $botReply = (string) ($history[$i]['bot_reply'] ?? '');
            if ($botReply === '') continue;
            if (preg_match($confusionRegex, $botReply)) {
                $count++;
            } else {
                // Stop counting at first non-confusion bot reply
                break;
            }
        }
        return $count;
    }

    /**
     * Detecta preguntas del cliente que aún no han sido respondidas.
     *
     * Escanea los mensajes del usuario en el historial y comprueba si el bot
     * ya envió la información solicitada después de cada pregunta.
     *
     * @param list<array<string, mixed>> $history
     * @param array<string, mixed> $ctx
     * @return list<string> Etiquetas de preguntas pendientes (ej: 'fotos', 'precios', 'ubicacion')
     */
    /** @param array<int, array<string, mixed>> $history @param array<string, mixed> $ctx @return list<string> */
    private function detectPendingQuestions(array $history, array $ctx): array
    {
        $pending = [];
        $yaEnviado = (array) ($ctx['ya_enviado'] ?? []);

        if ($history === []) {
            return [];
        }

        // Recorrer historial de más reciente a más antiguo
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $rec  = $history[$i];
            $ts   = strtotime((string) ($rec['ts'] ?? ''));
            $um   = (string) ($rec['user_msg'] ?? '');
            $bot  = (string) ($rec['bot_reply'] ?? '');
            $norm = $this->normalizeStr($um);

            if ($um === '') continue;

            // ── Detectar si el mensaje pide fotos ────────────────────
            $pideFotos = (bool) preg_match(
                '/\b(?:foto|fotos|foto\s*normal|fotito|fotitos|verte\s*mejor|mas\s*fotos?|tienes\s*fotos?|hay\s*fotos?|ens[ée][ñn]ame|m[áa]ndame\s*fotos?|puedo\s*ver(?:te)?)/iu',
                $norm
            );
            if ($pideFotos && $bot === '') {
                // Pregunta del usuario sin respuesta del bot → pendiente
                if (!in_array('fotos_pendientes', $pending, true)) {
                    $pending[] = 'fotos_pendientes';
                }
                break;
            }
            if ($pideFotos && $bot !== '') {
                // El bot respondió — ¿envió fotos en esa respuesta?
                $hasPhotos = (bool) preg_match(
                    '/(?:https?:\/\/(?:compartir\.site|ibb\.co|i\.ibb\.co)\/)/i',
                    $bot
                );
                if (!$hasPhotos && !in_array('fotos_pendientes', $pending, true)) {
                    $pending[] = 'fotos_pendientes';
                }
                break; // El bot respondió a esta pregunta, no seguir hacia atrás
            }

            // ── Detectar si el mensaje pide precios ──────────────────
            $pidePrecios = (bool) preg_match(
                '/\b(?:precio|precios|tarifa|tarifas|cu[áa]nto|cuesta|vale\b(?!\s+reina|\s+gracias)|informaci[óo]n|informarme|cu[ée]ntame|me\s*dices)/iu',
                $norm
            );
            if ($pidePrecios && !in_array('precios', $yaEnviado, true)) {
                if (!in_array('precios_pendientes', $pending, true)) {
                    $pending[] = 'precios_pendientes';
                }
                break;
            }

            // ── Detectar si el mensaje pide ubicación ────────────────
            $pideUbicacion = (bool) preg_match(
                '/\b(?:d[óo]nde|ubicaci[óo]n|direcci[óo]n|maps|mapa|calle|zona|barrio|queda|est[aá]s?|est[aá]n?|c[óo]mo\s+llego|pin|punto\s+exacto)/iu',
                $norm
            );
            if ($pideUbicacion && !in_array('ubicacion', $yaEnviado, true) && !in_array('ubicacion_precisa', $yaEnviado, true)) {
                if (!in_array('ubicacion_pendiente', $pending, true)) {
                    $pending[] = 'ubicacion_pendiente';
                }
                break;
            }
        }

        return $pending;
    }

    /**
     * Early dead conversation detection — evaluado por el LLM en lugar de regex.
     *
     * Los métodos wasConversationEndedRecently(), isPureFiller() e isConversationDead()
     * han sido eliminados. La detección de si una conversación está viva o muerta
     * ahora la hace el LLM mediante el campo conversation_health en su respuesta JSON.
     *
     * @see DeepSeekClient::formatContext() para el contexto que recibe el LLM
     * @see Bot.php::buildSystemPrompt() para el formato de respuesta esperado
     */

    private function detectInteresFuerte(string $normalizedText): bool
    {
        return (bool) preg_match(
            '/(voy\s*ya|voy\s*para\s*alla|salgo\s*para\s*alla|ahora\s*voy|me\s*paso\s*ya|quiero\s*ir\s*ya|ahora\s*mismo|en\s*un\s*rato\s*voy|voy\s*en\s*\d+)/iu',
            $normalizedText
        );
    }

    /**
     * Detectar ráfaga de apertura (mensaje automático web + saludo).
     */
    private function detectOpeningBurst(string $coalesced): bool
    {
        $t = $this->normalizeStr($coalesced);
        if ($t === '') return false;

        $hasAuto = (bool) preg_match('/(he\s+visto\s+tu\s+anuncio|te\s+he\s+visto\s+en|quiero\s+quedar\s+contigo|anuncio\s+en\s+http)/iu', $t);
        $hasGreeting = (bool) preg_match('/\b(hola|buenas|hey|ola)\b/iu', $t);

        return $hasAuto && $hasGreeting;
    }

    // ==================================================================
    // HELPERS — shown / unshown girls tracking
    // ==================================================================

    /**
     * @param list<array<string, mixed>> $history
     * @param array<string, mixed> $ctx
     * @param list<array<string, mixed>> $activeGirls
     * @return array{0: list<string>, 1: list<array<string, mixed>>}
     */
    /** @param array<int, array<string, mixed>> $history @param array<string, mixed> $ctx @param list<array<string, mixed>> $activeGirls @return array{shown: list<string>, unshown: list<string>} */
    private function computeShownUnshown(array $history, array $ctx, array $activeGirls): array
    {
        $shownNames = [];

        // Parse shown_girls from historical records (most recent first)
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $shown = $history[$i]['__shown_girls'] ?? $history[$i]['shown_girls'] ?? null;
            if (is_array($shown) && $shown !== []) {
                $shownNames = $shown;
                break;
            }
        }

        // Merge with current ctx shown_girls
        $currentShown = $ctx['shown_girls'] ?? $ctx['__shown_girls'] ?? [];
        if (is_array($currentShown) && $currentShown !== []) {
            $merged = [];
            foreach (array_merge($shownNames, $currentShown) as $n) {
                $nn = $this->normalizeStr((string) $n);
                if ($nn !== '' && !isset($merged[$nn])) {
                    $merged[$nn] = (string) $n;
                }
            }
            $shownNames = array_values($merged);
        }

        // Compute unshown
        $shownSet = [];
        foreach ($shownNames as $n) {
            $shownSet[$this->normalizeStr((string) $n)] = true;
        }

        $unshown = [];
        foreach ($activeGirls as $g) {
            $gn = $this->normalizeStr((string) ($g['nombre'] ?? ''));
            if ($gn !== '' && !isset($shownSet[$gn])) {
                $unshown[] = [
                    'nombre' => (string) ($g['nombre'] ?? ''),
                    'fotos'  => $g['fotos'] ?? [],
                ];
            }
        }

        return [$shownNames, $unshown];
    }

    // ==================================================================
    // HELPERS — Utility
    // ==================================================================

    private function countBotMessages(string $memoryText): int
    {
        $count = 0;
        if (preg_match_all('/\bBot:\s/', $memoryText, $m) !== false) {
            $count = count($m[0]);
        }
        return $count;
    }

    /**
     * Build a list of recent normalized bot replies (last 8, deduped).
     * Used for anti-repetition in ToneBuilder and DedupeReply.
     *
     * @param list<array<string, mixed>> $recent
     * @return list<string>
     */
    private function buildRecentBotRepliesNorm(array $recent): array
    {
        $norm = [];
        $seen = [];
        $limit = max(0, count($recent) - 10);

        for ($i = count($recent) - 1; $i >= $limit; $i--) {
            $reply = (string) ($recent[$i]['bot_reply'] ?? $recent[$i]['reply_text'] ?? '');
            if ($reply === '') continue;

            $n = $this->normalizeStr($reply);
            if ($n === '' || isset($seen[$n])) continue;

            $seen[$n] = true;
            $norm[]   = $n;

            if (count($norm) >= 8) break;
        }

        return $norm;
    }

    /**
     * Check if the bot has already greeted in the conversation history.
     *
     * @param list<array<string, mixed>> $records
     */
    private function hasBotGreetedInHistory(array $records): bool
    {
        foreach ($records as $rec) {
            $reply = (string) ($rec['bot_reply'] ?? $rec['reply_text'] ?? '');
            if ($reply === '') continue;
            $norm = $this->normalizeStr($reply);
            if (preg_match('/^\s*(?:hola|buenas|hey|ola|buenos\s+d[ií]as|buenas\s+tardes|buenas\s+noches)\b/iu', $norm)) {
                return true;
            }
        }
        return false;
    }

    private function normalizeStr(string $value): string
    {
        $n = @normalizer_normalize($value, \Normalizer::NFKD);
        if ($n === false) {
            $n = $value;
        }
        $n = preg_replace('/[\x{0300}-\x{036f}]/u', '', (string) $n) ?? $value;
        return mb_strtolower(trim((string) $n));
    }

    private function isFillerUser(string $text): bool
    {
        $t = $this->normalizeStr($text);
        if ($t === '') return false;
        // Single-word fillers and emoji-only
        if (preg_match('/^(?:cari|carino|amor|bb|bebe|guapo|hola|buenas|ok|okis|vale|aja|jeje+|jaja+|perfecto|genial|gracias|ok\s+gracias|un\s+saludo|saludos|adios|hasta\s+luego|hasta\s+ahora|de\s+acuerdo|entendido|dale|listo|claro|sip|nop|ya|si\s+si|no\s+no|okey|okey\s+dokey|perfect|bien\s+bien)$/iu', $t)) {
            return true;
        }
        // Emoji-only
        if (preg_match('/^[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F1E0}-\x{1F1FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}\x{1F900}-\x{1F9FF}]+$/u', trim($text))) {
            return true;
        }
        return false;
    }

    /** @param list<array<string, mixed>> $girlsConfig @return list<array<string, mixed>> */
    private function filterActiveGirls(array $girlsConfig): array
    {
        return array_values(array_filter($girlsConfig, function ($g) {
            if (!is_array($g)) return false;
            $activa = $g['activa'] ?? true;
            if (is_string($activa)) {
                $activa = in_array(strtolower(trim($activa)), ['true', '1', 'yes', 'si', 'sí'], true);
            }
            return (bool) $activa && !empty($g['nombre']);
        }));
    }

    /**
     * Levenshtein distance with early exit when exceeding limit.
     */
    private function levenshteinLimit(string $a, string $b, int $limit): int
    {
        $a = (string) $a;
        $b = (string) $b;
        if ($a === $b) return 0;

        $la = mb_strlen($a);
        $lb = mb_strlen($b);
        if (abs($la - $lb) > $limit) return $limit + 1;

        // Use arrays of characters for mb-safe comparison
        $aChars = mb_str_split($a);
        $bChars = mb_str_split($b);

        $prev = range(0, $lb);
        for ($i = 1; $i <= $la; $i++) {
            $cur = [$i];
            $rowMin = $i;
            for ($j = 1; $j <= $lb; $j++) {
                $cost = ($aChars[$i - 1] === $bChars[$j - 1]) ? 0 : 1;
                $v = min($prev[$j] + 1, $cur[$j - 1] + 1, $prev[$j - 1] + $cost);
                $cur[$j] = $v;
                if ($v < $rowMin) $rowMin = $v;
            }
            if ($rowMin > $limit) return $limit + 1;
            $prev = $cur;
        }
        return $prev[$lb];
    }

    // ==================================================================
    // NOVA HELPERS — T4.4 + T4.5
    // ==================================================================

    /**
     * Extract ETA in minutes from user message text.
     *
     * Handles patterns like "en 20 min", "tardo 15", "llego en 10".
     */
    private function extractEtaMinutes(string $text): int
    {
        $t = $this->normalizeStr($text);
        if ($t === '') return 0;

        // Pattern: "en X min", "llego en X", "tardo X", "tardaré X"
        $patterns = [
            '/(?:en|llego\s*en|llegare\s*en|llegaria\s*en|tardo\s*(?:unos\s*)?|tardare\s*(?:unos\s*)?|tardaria\s*(?:unos\s*)?|estoy\s*en)\s*(\d{1,3})\s*(?:min(?:utos?)?|miutos?|mins?|mnts?)?/iu',
            '/(\d{1,3})\s*(?:min(?:utos?)?|miutos?|mins?|mnts?)/iu',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $t, $m)) {
                $v = (int) $m[1];
                if ($v >= 1 && $v <= 180) return $v;
            }
        }
        return 0;
    }

    /**
     * Count consecutive user messages asking for location/maps without
     * a selected girl. Used to detect indecision loops.
     *
     * @param list<array<string, mixed>> $history
     */
    private function computeChooseLoopCount(array $history, string $selectedGirlName): int
    {
        if ($selectedGirlName !== '') return 0;

        $count = 0;
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $um = (string) ($history[$i]['user_msg'] ?? $history[$i]['user_message'] ?? '');
            if ($um === '') continue;
            $n = $this->normalizeStr($um);
            if ($this->userWantsMapWords($n) || $this->detectTopic($n) === 'ubicacion') {
                $count++;
            } else {
                break;
            }
        }
        return $count;
    }

    /**
     * Detect if the user is asking for photos / images.
     */
    private function userWantsPhotosWords(string $normalizedText): bool
    {
        return (bool) preg_match(
            '/(?:\bfotos?\b|\bfotitos?\b|\bfotillos?\b|\bfoto\b|imag[ée]nes?\b|verlas?\b|ens[ée][ñn]ame|mu[ée]strame|quiero\s+ver|a\s+ver|ver\s+m[aá]s|tienes\s+m[aá]s|pasa\s+fotos?|manda\s+fotos?|ense[ñn]a|muestra|fotito|fotillos|pasa\s+las|manda\s+las|ense[ñn]ame\s+las|quiero\s+verlas|ver\s+fotos?|catalogo|cat[aá]logo)/iu',
            $normalizedText,
        );
    }

    /**
     * Count how many times the user has asked for photos across the ENTIRE
     * conversation history. Used to decide whether to cede and re-send.
     *
     * @param  list<array<string, mixed>> $history
     */
    private function computePhotoInsistCount(array $history): int
    {
        $count = 0;
        foreach ($history as $rec) {
            $um = (string) ($rec['user_msg'] ?? $rec['user_message'] ?? '');
            if ($um === '') continue;
            $n = $this->normalizeStr($um);
            if ($this->userWantsPhotosWords($n)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Count how many times the user has asked for location/maps across the
     * ENTIRE conversation history.
     *
     * @param  list<array<string, mixed>> $history
     */
    private function computeLocationInsistCount(array $history): int
    {
        $count = 0;
        foreach ($history as $rec) {
            $um = (string) ($rec['user_msg'] ?? $rec['user_message'] ?? '');
            if ($um === '') continue;
            $n = $this->normalizeStr($um);
            if ($this->userWantsMapWords($n) || $this->detectTopic($n) === 'ubicacion') {
                $count++;
            }
        }
        return $count;
    }

    /**
     * NOVA B5: merge context from other WhatsApp lines for the same phone.
     *
     * When a client contacts us via multiple WhatsApp numbers, each line creates
     * a separate thread_id. This method scans all threads in session memory,
     * finds the most recent one for the same phone, and copies speaker_girl,
     * selected_girl, and ya_enviado to maintain conversation coherence.
     *
     * @param  string               $fromPhone  Client phone number
     * @param  string               $currentTid Current thread_id (to skip)
     * @param  array<string, mixed> $ctx        Current context (reference)
     * @return array<string, mixed>|null        Updated ctx or null if no merge needed
     */
    /** @param array<string, mixed> $ctx @return array<string, mixed>|null */
    private function mergeContextFromOtherLines(string $fromPhone, string $currentTid, array $ctx): ?array
    {
        if ($this->sessionMemory === null) return null;

        // Scan ALL threads in session memory for the same phone
        $allThreads = $this->sessionMemory->listThreadIds();
        if (empty($allThreads)) return null;

        $bestRec = null;
        $bestTs  = 0;
        foreach ($allThreads as $tid) {
            if ($tid === $currentTid) continue;
            if (!str_ends_with((string) $tid, '_' . $fromPhone)) continue;

            $records = $this->sessionMemory->readThread($tid);
            if (empty($records)) continue;

            // Find the most recent record with context
            for ($i = count($records) - 1; $i >= 0; $i--) {
                $rec = $records[$i];
                $ts = strtotime((string) ($rec['ts'] ?? ''));
                if ($ts === false) continue;
                if ($ts > $bestTs && !empty($rec['speaker_girl_name'])) {
                    $bestTs  = $ts;
                    $bestRec = $rec;
                }
            }
        }

        if ($bestRec === null) return null;

        // Merge context fields — NOVA FIX 2026-06-17:
        // Solo heredamos speaker_girl (identidad) y shown/unshown (catálogo).
        // NO heredamos selected_girl (el cliente debe reconfirmar su elección
        // en este hilo nuevo), NO heredamos ya_enviado (son acciones que no
        // ocurrieron en este hilo), y NO forzamos speaker_mode='chica' si el
        // hilo es nuevo (debe arrancar en modo encargada para saludar bien).
        $merged = false;
        $speakerName = (string) ($bestRec['speaker_girl_name'] ?? '');
        $speakerId   = (string) ($bestRec['speaker_girl_id'] ?? '');
        $selectedName = (string) ($bestRec['selected_girl_name'] ?? '');
        $selectedId   = (string) ($bestRec['selected_girl_id'] ?? '');
        $yaEnviado    = (array) ($bestRec['ya_enviado'] ?? []);
        $shownGirls   = (array) ($bestRec['shown_girls'] ?? []);
        $unshownGirls = (array) ($bestRec['unshown_girls'] ?? []);

        // Only inherit speaker identity — NOT selected girl
        if ($speakerName !== '' && ($ctx['speaker_girl_name'] ?? '') === '') {
            $ctx['speaker_girl_name'] = $speakerName;
            $ctx['speaker_girl_id']   = $speakerId;
            // IMPORTANTE: mantener speaker_mode='encargada' si es hilo nuevo
            // (__is_new_conversation). Así el bot saluda sin saltar a modo chica.
            // La sticky state fallback (más abajo) pondrá modo chica cuando toque.
            $isNew = !empty($ctx['__is_new_conversation']);
            if (!$isNew) {
                $ctx['speaker_mode'] = 'chica';
            }
            $merged = true;
        }
        // ⛔ NO heredar selected_girl_name/selected_girl_id entre hilos distintos.
        //    El cliente debe reconfirmar su elección en el nuevo hilo.
        //    Si heredamos selected_girl, el bot arranca enviando la dirección
        //    en el primer mensaje (ver caso 34619045690).

        // ⛔ NO heredar ya_enviado entre hilos — son acciones per-thread.
        //    Si el hilo B hereda que "ya se enviaron fotos" del hilo A, el bot
        //    se salta el flujo de mostrar fotos/precios y va directo a mapa.

        // ✅ Catalog state: still useful to share across lines
        if (!empty($shownGirls)) {
            $ctx['shown_girls'] = $shownGirls;
            $merged = true;
        }
        if (!empty($unshownGirls)) {
            $ctx['unshown_girls'] = $unshownGirls;
            $merged = true;
        }

        if ($merged && $this->logger !== null) {
            $this->logger->info('ContextAssembler::mergeContextFromOtherLines — merged context from other line', [
                'phone'      => $fromPhone,
                'speaker'    => $speakerName,
                'is_new'     => $isNew ?? true,
                'selected'   => 'NOT inherited (cross-line)',
            ]);
        }

        return $ctx;
    }
}
