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
        // --- thread_id ---
        $threadId = $ctx['thread_id'] ?? $ctx['__thread_id'] ?? null;
        if ($threadId === null || $threadId === '') {
            $threadId = (string) ($ctx['from_phone'] ?? '');
            if ($threadId === '') {
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

        // --- message text ---
        $messageText = (string) ($ctx['message_text'] ?? '');
        $normalizedText = mb_strtolower(trim($messageText), 'UTF-8');

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

        // --- wants_more_girls (persistente como en n8n) ---
        $wantsMoreCurrent = $this->detectWantsMoreGirls($normalizedText);
        $wantsMorePersisted = $this->hasWantsMoreInHistory($history);
        $ctx['wants_more_girls'] = $wantsMoreCurrent || $wantsMorePersisted;

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

        // ── NOVA: info_pack_ready ─────────────────────────────────────────
        $yaEnviado = $ctx['ya_enviado'] ?? [];
        $pricesSent = in_array('precios', $yaEnviado, true);
        $exactLocationSent = $ctx['maps_sent'] ?? false;
        $girlSelected = $selectedGirlName !== '';
        $ctx['info_pack_ready'] = $girlSelected && $pricesSent && $exactLocationSent;

        // ── NOVA: is_image_sent_by_user ───────────────────────────────────
        $ctx['is_image_sent_by_user'] = ($ctx['is_image_i'] ?? 0) === 1
            || !empty($ctx['__is_image']);

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
    private function isExplicitServiceChoice(string $normalizedText): bool
    {
        $patterns = [
            '/quiero\s+(ir\s+con|a\s+la\s+|con)/iu',
            '/me\s+quedo\s+con/iu',
            '/prefiero\s+(a\s+)?/iu',
            '/reservo\s+con/iu',
            '/cita\s+con/iu',
            '/voy\s+con/iu',
            '/al\s+final\s+(me\s+quedo|quiero|elijo)/iu',
            '/me\s+gusta\s+m[áa]s/iu',
            '/me\s+mola\s+m[áa]s/iu',
            '/esta\s+me\s+gusta/iu',
            '/con\s+esta\s+(quiero|me\s+quedo|prefiero)/iu',
            '/elijo\s+a\s+/iu',
            '/la\s+que\s+(m[áa]s\s+)?me\s+gusta\s+es/iu',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $normalizedText)) {
                return true;
            }
        }
        return false;
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
    private function hasWantsMoreInHistory(array $records): bool
    {
        foreach ($records as $rec) {
            if (!empty($rec['wants_more_girls'])) {
                return true;
            }
        }
        return false;
    }

    // ==================================================================
    // HELPERS — Last messages from history
    // ==================================================================

    private function lastBotReplyFromHistory(array $recent): ?string
    {
        for ($i = count($recent) - 1; $i >= 0; $i--) {
            $reply = (string) ($recent[$i]['bot_reply'] ?? $recent[$i]['reply_text'] ?? '');
            if ($reply !== '') return $reply;
        }
        return null;
    }

    private function lastUserMsgFromHistory(array $recent): ?string
    {
        for ($i = count($recent) - 1; $i >= 0; $i--) {
            $msg = (string) ($recent[$i]['user_msg'] ?? $recent[$i]['user_message'] ?? '');
            if ($msg !== '') return $msg;
        }
        return null;
    }

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

    private function detectTarifaElegida(array $recent): string
    {
        for ($i = count($recent) - 1; $i >= 0; $i--) {
            $raw = (string) ($recent[$i]['user_msg'] ?? $recent[$i]['user_message'] ?? '');
            $u = $this->normalizeStr($raw);
            if ($u === '') continue;

            $hasAcepta = (bool) preg_match('/\b(vale|ok|de\s+acuerdo|me\s+vale|me\s+cuadra|perfecto|cojo|quiero|me\s+quedo|pillo|prefiero)\b/iu', $u);
            $msgCorta = (bool) preg_match('/^[0-9a-z€\s]{1,25}$/iu', $u);
            $acepta = $hasAcepta || $msgCorta;

            if ($acepta && preg_match('/\b30\s*(?:euros?|eur|€)?\b/iu', $u)) return '30';
            if ($acepta && preg_match('/\b50\s*(?:euros?|eur|€)?\b/iu', $u)) return '50';
            if ($acepta && preg_match('/\b100\s*(?:euros?|eur|€)?\b/iu', $u)) return '100';

            if ($acepta && preg_match('/(?:rapid|rapidito|10\s*min|diez\s*min)/iu', $u)) return '30';
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

    private function yaEnviadoFromHistory(array $records): array
    {
        $flags = [];
        foreach ($records as $rec) {
            $replyRaw = (string) ($rec['bot_reply'] ?? $rec['reply_text'] ?? '');
            $replyNorm = $this->normalizeStr($replyRaw);
            if ($replyRaw === '') continue;

            if (preg_match('/\b(?:30\s*€|1h|50\s*€|100\s*€|tarifa|precio|precios)\b/iu', $replyNorm)) {
                $flags[] = 'precios';
            }

            $hasMap = (bool) preg_match('/(?:https?:\/\/)?(?:goo\.gl\/maps|maps\.app\.goo\.gl|google\.com\/maps|maps\.google\.com)/i', $replyRaw);
            if (preg_match('/maps|ubicacion|direccion|calle|punto|ubi\b|pin\b/iu', $replyNorm) || $hasMap) {
                $flags[] = 'ubicacion';
                if ($hasMap) $flags[] = 'ubicacion_precisa';
            }

            $hasPhoto = (bool) preg_match('/(?:https?:\/\/(?:ibb\.co|i\.ibb\.co)\/)/i', $replyRaw);
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
            '/(adios|hasta\s*luego|hasta\s*ahora|me\s*voy|otro\s*dia|luego\s*hablamos|ya\s*te\s*digo|gracias\s*y\s*perdon|vale\s*gracias|ok\s*gracias)/iu',
            $normalizedText
        );
    }

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
}
