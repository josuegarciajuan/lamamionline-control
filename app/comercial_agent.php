<?php
/**
 * comercial_agent.php — Pipeline LLM unificado para el inbox comercial.
 *
 * Reemplaza el sistema anterior de templates + keyword classification + límites.
 * Una sola función principal que maneja todos los modos:
 *   - 'opener'   → genera mensaje de apertura único por conversación
 *   - 'reply'    → genera respuesta contextual al usuario
 *   - 'classify' → clasifica intención, sentimiento, lead score (LLM)
 *   - 'summary'  → genera resumen de escalación para el agente humano
 *
 * Principios:
 *   - Sin plantillas: todas las respuestas son generadas por LLM
 *   - Sin límites de turnos: el bot responde hasta conseguir lead o descalificar
 *   - Sin cross-selling: cada conversación usa UN solo knowledge base
 *   - Auto-aprendizaje: feedback loop vía comercial_ai_memory
 *   - Human-like: delays vía comercial_humanize.php
 */

declare(strict_types=1);

/** Devuelve la URL oficial configurada para un proceso comercial. */
function comercial_official_url_for_slug(string $processSlug): string {
    $slug = trim($processSlug);
    $kb = function_exists('comercial_knowledge_get') ? comercial_knowledge_get($slug) : array();
    $url = trim((string)($kb['official_url'] ?? ''));
    if ($url !== '') return $url;
    if (function_exists('comercial_knowledge_v2_get')) {
        $v2 = comercial_knowledge_v2_get($slug, 'common');
        return trim((string)($v2['official_url'] ?? ''));
    }
    return '';
}

/** Comprueba si un dominio oficial ya aparece en cualquier entrada del historial. */
function comercial_official_url_was_shared(array $history, string $officialUrl): bool {
    $host = (string)parse_url(trim($officialUrl), PHP_URL_HOST);
    if ($host === '') return false;
    foreach ($history as $entry) {
        $text = is_array($entry) ? trim((string)($entry['text'] ?? '')) : trim((string)$entry);
        if ($text !== '' && preg_match('/(?<![a-z0-9-])(?:https?:\/\/)?(?:www\.)?' . preg_quote($host, '/') . '(?=[:\/\s?&#.,!)]|$)/iu', $text) === 1) {
            return true;
        }
    }
    return false;
}

/** Añade una mención breve a la web si el opener del LLM la omitió. */
function comercial_ensure_official_url_in_opener(string $text, string $officialUrl): string {
    $text = trim($text);
    $officialUrl = trim($officialUrl);
    if ($text === '' || $officialUrl === '' || comercial_official_url_was_shared(array(array('text' => $text)), $officialUrl)) {
        return $text;
    }
    return rtrim($text, " \t\r\n") . " Si te apetece, puedes verlo en " . $officialUrl . ".";
}

/** Genera la política contextual que se inyecta en replies/follow-ups. */
function comercial_reply_url_guidance(string $officialUrl, bool $alreadyShared, string $inboundText): string {
    if ($officialUrl === '' || $alreadyShared) return '';
    $lower = function_exists('mb_strtolower') ? mb_strtolower($inboundText, 'UTF-8') : strtolower($inboundText);
    if (preg_match('/\b(no me interesa|no gracias|para ya|deja de escribirme|no quiero recibir|denuncia|polic[ií]a|estafa|fraude)\b/ui', $lower) === 1) {
        return '';
    }
    return "La web oficial aún no aparece en el historial: {$officialUrl}. Inclúyela solo si responde al tema o facilita el siguiente paso; si el cliente hace una pregunta incompatible, rechaza el contacto o sonaría a spam, no la menciones.";
}

// ═══════════════════════════════════════════════════════════════
//  FUNCIÓN PRINCIPAL
// ═══════════════════════════════════════════════════════════════

/**
 * Procesa una conversación comercial con el LLM.
 *
 * @param array  $thread      Thread normalizado
 * @param string $processSlug Slug del negocio (plaza, lamami, casawasap, publicista, publiscort, inbound)
 * @param string $mode        'opener', 'reply', 'classify', 'summary'
 * @param array  $opts        Opciones adicionales (inbound_text, objective, etc.)
 * @return array ['ok' => bool, 'text' => string, 'classification' => array, ...]
 */
function comercial_agent_process(array $thread, string $processSlug, string $mode, array $opts = array()): array {
    $thread = comercial_normalize_thread($thread);
    $processSlug = trim($processSlug);
    $mode = trim($mode);

    // Validar que las dependencias existen (DeepSeek vía crítico)
    if (!function_exists('comercial_agent_critic_get_config')) {
        return array('ok' => false, 'error' => 'ai_utilities_unavailable');
    }

    $cfg = comercial_agent_critic_get_config();
    if (empty($cfg['enabled']) || empty($cfg['api_key'])) {
        return array('ok' => false, 'error' => 'ai_not_configured');
    }

    $model = trim((string)($cfg['model'] ?? 'deepseek-v4-pro'));

    switch ($mode) {
        case 'opener':
            return comercial_agent_generate_opener($thread, $processSlug, $model, $cfg, $opts);
        case 'reply':
            return comercial_agent_generate_reply($thread, $processSlug, $model, $cfg, $opts);
        case 'classify':
            return comercial_agent_classify($thread, $processSlug, $model, $cfg, $opts);
        case 'summary':
            return comercial_agent_escalation_summary($thread, $processSlug, $model, $cfg, $opts);
        default:
            return array('ok' => false, 'error' => 'unknown_mode: ' . $mode);
    }
}

// ═══════════════════════════════════════════════════════════════
//  CONSTRUCTOR DE PROMPT
// ═══════════════════════════════════════════════════════════════

/**
 * Carga el playbook de aprendizaje del proceso (si existe).
 * El playbook se genera con cron/comercial_learn.php en data/comercial_playbooks/{slug}.md.
 */
function comercial_agent_load_playbook(string $processSlug): string {
    $slug = trim((string)$processSlug);
    if ($slug === '') return '';
    $file = DATA_PATH . '/comercial_playbooks/' . $slug . '.md';
    if (!file_exists($file)) return '';
    $content = trim((string)@file_get_contents($file));
    if ($content === '') return '';
    // Limitar lo que se inyecta al prompt por mensaje (el playbook completo sigue en disco
    // para el pipeline de learn). Evita coste/latencia excesivos sin perder el grueso del insight.
    $maxChars = 5000;
    if (function_exists('mb_strlen') && mb_strlen($content, 'UTF-8') > $maxChars) {
        $content = mb_substr($content, 0, $maxChars, 'UTF-8');
        // Cortar en el último salto de línea para no dejar una frase a medias
        $lastNl = mb_strrpos($content, "\n", 0, 'UTF-8');
        if ($lastNl !== false && $lastNl > 100) {
            $content = mb_substr($content, 0, $lastNl, 'UTF-8');
        }
    }
    return $content;
}

/**
 * Construye el system prompt completo para una llamada LLM.
 */
function comercial_agent_build_system_prompt(string $processSlug, string $mode, array $thread, array $opts = array(), string $phase = ''): string {
    // ── AGENT V2: Si hay KB v2 disponible, usar prompt fase-específico ──
    $phase = $phase !== '' ? $phase : (string)($thread['conversation_phase'] ?? '');
    if ($phase !== '' && function_exists('comercial_knowledge_v2_get')) {
        return comercial_agent_build_phase_prompt($processSlug, $mode, $phase, $thread, $opts);
    }

    // Fallback: KB legacy (sin cambios)
    $kb = comercial_knowledge_get($processSlug);

    // ── Sección 1: Identidad ──
    $sections = array();
    $sections[] = trim($kb['identity'] ?? '');

    $officialUrl = comercial_official_url_for_slug($processSlug);
    if ($officialUrl !== '') {
        $sections[] = "═══ URL OFICIAL ═══\n{$officialUrl}\nMenciónala de forma natural cuando aporte valor. No la repitas si ya aparece en el historial y no la fuerces ante preguntas incompatibles o rechazo.";
    }

    // ── Sección 2: Producto y precios ──
    if (!empty($kb['product'])) {
        $sections[] = "═══ LO QUE VENDES ═══\n" . trim($kb['product']);
    }
    if (!empty($kb['pricing'])) {
        $sections[] = "═══ PRECIOS (FIJOS, NO NEGOCIABLES) ═══\n" . trim($kb['pricing']);
    }

    // ── Sección 3: Restricciones DURAS ──
    $restrictions = $kb['restrictions'] ?? array();
    if (!empty($restrictions)) {
        $sections[] = "═══ REGLAS OBLIGATORIAS ═══\n" . implode("\n", array_map(function($r) { return "- " . $r; }, $restrictions));
    }

    // ── Sección 4: FAQ ──
    $faq = $kb['faq'] ?? array();
    if (!empty($faq)) {
        $faqLines = array();
        foreach ($faq as $q => $a) {
            $faqLines[] = "P: " . $q . "\nR: " . $a;
        }
        $sections[] = "═══ PREGUNTAS FRECUENTES ═══\n" . implode("\n\n", $faqLines);
    }

    // ── Sección 5: Objeciones ──
    $objections = $kb['objections'] ?? array();
    if (!empty($objections)) {
        $objLines = array();
        foreach ($objections as $obj => $response) {
            $objLines[] = "Objeción: \"" . $obj . "\" → " . $response;
        }
        $sections[] = "═══ CÓMO MANEJAR OBJECIONES ═══\n" . implode("\n", $objLines);
    }

    // ── Sección 5b: Datos de las habitaciones (rama plaza) ──
    $roomFacts = $kb['room_facts'] ?? array();
    if (!empty($roomFacts)) {
        $sections[] = "═══ LAS HABITACIONES / FOTOS ═══\n" . implode("\n", array_map(function($f) { return '- ' . $f; }, $roomFacts));
    }

    // ── Sección 5c: Ocupación de la casa en tiempo real (rama plaza) ──
    if ($processSlug === 'plaza' && function_exists('jostal_en_casa_count')) {
        $enCasa = (int)jostal_en_casa_count();
        $capacidad = (int)(defined('JOSTAL_CASA_CAPACIDAD') ? constant('JOSTAL_CASA_CAPACIDAD') : 5);
        $libres = max(0, $capacidad - $enCasa);
        if ($libres > 0) {
            $occLine = "Ahora mismo hay {$enCasa} de {$capacidad} plazas ocupadas (quedan {$libres} libres). Como hay hueco, sé más directa y empuja a cerrar para copar el aforo.";
        } else {
            $occLine = "Ahora mismo hay {$enCasa} de {$capacidad} plazas ocupadas (casa completa). Enseña las fotos igual y mantén el buen rollo; dile que le avisarás en unos días cuando quede libre.";
        }
        $sections[] = "═══ OCUPACIÓN DE LA CASA (EN TIEMPO REAL) ═══\n" . $occLine;
    }

    // ── Sección 6: Tono ──
    if (!empty($kb['tone'])) {
        $maxLines = (int)($kb['max_lines'] ?? 4);
        $sections[] = "═══ TONO Y ESTILO ═══\n" . trim($kb['tone']) . "\n"
            . "- Habla como una persona real por WhatsApp: frases cortas y cálidas, tuteo natural.\n"
            . "- PROHIBIDO lenguaje corporativo o de atención al cliente: 'Entiendo que...', 'podemos coordinar', 'Siempre hay rotación', 'es bueno tener el contacto', 'avísame', 'quedamos', 'me alegra que preguntes', 'buena pregunta'.\n"
            . "- Máximo 1 emoji por mensaje.\n"
            . "- Sin markdown, sin listas, sin formato especial.\n"
            . "- Natural, como un WhatsApp real.\n"
            . "- Entre 1 y {$maxLines} líneas.\n"
            . "- NUNCA coletillas infantiles (guapa, cariño, reina, Holaaa).\n"
            . "- Varía la estructura de tus mensajes, no uses siempre el mismo patrón.\n"
            . "- NUNCA uses los signos de apertura ¿ ni ¡.\n"
            . "- NUNCA presiones ni fuerces el cierre ni fabriques urgencia.\n"
            . "- Si el cliente pidió SOLO información, dásela y termina en el dato útil. NUNCA cierres con 'quieres que te explique algo más', 'te ayudo en algo más' ni equivalentes. No pidas activar/empezar sin que haya mostrado intención clara.\n"
            . "- Responde primero a su duda, con lenguaje cercano y breve. Despierta interés solo con un detalle real y útil; no ocultes precios, condiciones o límites cuando los pida.";
    }

    // ── Sección 7: Señales de lead ──
    $leadSignals = $kb['lead_signals'] ?? array();
    if (!empty($leadSignals) && $mode !== 'opener') {
        $sections[] = "═══ CUÁNDO ESCALAR A HUMANO (LEAD CONSEGUIDO) ═══\nCuando detectes ALGUNA de estas señales, la conversación debe pasar a un compañero:\n- " . implode("\n- ", $leadSignals);
    }

    $disqualify = $kb['disqualify_signals'] ?? array();
    if (!empty($disqualify) && $mode !== 'opener') {
        $sections[] = "═══ CUÁNDO ABANDONAR (NO ES LEAD) ═══\nSi detectas estas señales, despídete cordialmente y no insistas:\n- " . implode("\n- ", $disqualify);
    }

    // ── Sección 8: Ejemplos que funcionaron (memoria, selección por relevancia) ──
    $inboundForMem = trim((string)($opts['inbound_text'] ?? ''));
    $examples = comercial_ai_memory_relevant_examples($processSlug, $inboundForMem, $phase, 5);
    if (!empty($examples)) {
        $exampleLines = array();
        foreach ($examples as $i => $ex) {
            $exampleLines[] = ($i + 1) . ') ' . trim((string)($ex['text'] ?? ''));
        }
        $sections[] = "═══ RESPUESTAS QUE FUNCIONARON EN CONVERSACIONES SIMILARES ═══\n" . implode("\n\n", $exampleLines) . "\n\nÚsalas como inspiración de tono y enfoque, NO las copies textualmente.";
    }

    // ── Sección 9: Playbook de aprendizaje del proceso ──
    $playbook = comercial_agent_load_playbook($processSlug);
    if ($playbook !== '') {
        $sections[] = "═══ APRENDIZAJES DE CONVERSACIONES REALES (playbook) ═══\n" . $playbook;
    }

    return implode("\n\n", $sections);
}

// ═══════════════════════════════════════════════════════════════
//  AGENT V2: Prompt por fase con few-shot (KB v2)
// ═══════════════════════════════════════════════════════════════

/**
 * Construye el prompt del LLM con solo la info de la fase actual.
 * Cada fase incluye ejemplos concretos de respuestas buenas y malas.
 */
function comercial_agent_build_phase_prompt(string $processSlug, string $mode, string $phase, array $thread, array $opts = array()): string {
    $kb = comercial_knowledge_v2_get($processSlug, $phase);
    $common = comercial_knowledge_v2_get($processSlug, 'common');
    $sections = array();

    $officialUrl = comercial_official_url_for_slug($processSlug);
    if ($officialUrl !== '') {
        $sections[] = "═══ POLÍTICA DE URL OFICIAL ═══\nURL oficial: {$officialUrl}\nEn aperturas debe aparecer. En replies/follow-ups solo menciónala si encaja y no consta ya en el historial; nunca la fuerces si la pregunta es incompatible o sonaría a spam.";
    }

    // Identidad (sin autoreferencia, directo al producto)
    $pl = $kb['product_line'] ?? $processSlug;
    $sections[] = "Vendes el servicio de {$pl}. NO digas 'somos del equipo', 'soy X', 'nuestro servicio es'. Ve directo al tema.";

    // Reglas globales
    $globalRules = $common['global_rules'] ?? array();
    if (!empty($globalRules)) {
        $sections[] = "═══ REGLAS OBLIGATORIAS ═══\n"
        . "- NUNCA uses los signos de apertura ¿ ni ¡.\n"
        . "- Responde primero a la duda concreta, breve, cercana y sin condescendencia.\n"
        . "- No cierres con 'quieres que te explique algo más', 'te ayudo en algo más' ni equivalentes. Termina en el dato útil, una opción concreta o el siguiente paso natural.\n"
        . "- Dosifica solo detalles secundarios: si te piden precio, condiciones o límites, dales la información completa y real.\n"
        . implode("\n", array_map(function($r) { return '- ' . $r; }, $globalRules));
    }

    // Contexto de negocio (si el proceso lo define): ayuda al LLM a entender el nicho
    // y usar argumentos reales (económicos, de urgencia) en cualquier fase.
    $businessContext = $common['contexto_negocio'] ?? array();
    if (!empty($businessContext)) {
        $sections[] = "═══ CONTEXTO DEL NEGOCIO (ÚSALO COMO ESTRATEGIA CUANDO ENCAJE) ═══\n" . implode("\n", array_map(function($c) { return '- ' . $c; }, $businessContext));
    }

    $inboundText = trim((string)($opts['inbound_text'] ?? ''));

    // ── Contenido fase-específico ──
    switch ($phase) {
        case 'SALUDO_INICIAL':
            $openingGuidance = $kb['opening_guidance'] ?? array();
            if (!empty($openingGuidance)) {
                $sections[] = "═══ IDEAS PARA LA APERTURA ═══\n- " . implode("\n- ", $openingGuidance);
            }
            $maxLines = (int)($kb['max_lines'] ?? 4);
            $sections[] = "═══ REGLAS DE ESTA FASE ═══\n- Máximo {$maxLines} líneas.\n- Un solo tema.\n- Ir directo al producto, sin presentación ni 'hola'.\n- NUNCA 'somos del equipo', 'soy X', autoreferencia.\n- NUNCA precio ni porcentajes. Solo bajo demanda.\n- No usar preguntas retóricas ni encadenar preguntas.\n- Terminar con una invitación natural a responder.\n- 1 emoji máximo.";
            $sections[] = "═══ EJEMPLO MALO ═══\n\"Hola, soy de Casa Burriana. Ofrecemos habitaciones y plazas con wifi, smartTV, limpieza diaria, sábanas incluidas. Dos modalidades: plaza 60/40 y alquiler...\" → DEMASIADO LARGO, autoreferencia, suelta todo de golpe.";
            break;

        case 'DESCUBRIMIENTO':
            $pitch = $kb['pitch'] ?? '';
            if ($pitch !== '') {
                $sections[] = "═══ QUÉ SABES DEL PRODUCTO ═══\n{$pitch}";
            }
            $questions = $kb['qualifying_questions'] ?? array();
            if (!empty($questions)) {
                $sections[] = "═══ PREGUNTAS PARA CUALIFICAR ═══\n" . implode("\n", array_map(function($q) { return '- ' . $q; }, $questions));
            }
            $sections[] = "═══ REGLAS DE ESTA FASE ═══\n- Máximo 5 líneas.\n- Responder SOLO a lo que preguntó el prospecto.\n- No introducir web o demo por rutina: solo si responde al tema y no se ha compartido ya.\n- 1 pregunta cualificadora al final.\n- Si preguntó algo concreto, responde ESO y nada más.";
            $sections[] = "═══ EJEMPLO MALO ═══\n\"La Mami Online es un nuevo concepto de publicista digital. Te conseguimos clientes extra. Alta única 29€. Pagas 10€/30min cuando llega cliente. Sin cuotas. Sin permanencia. Web lamami.online.\" → SOLTÓ PRECIO Y WEB DEMASIADO PRONTO, CERO PREGUNTAS.";
            break;

        case 'PRESENTACION':
            $pricing = $kb['pricing'] ?? '';
            $features = $kb['features'] ?? '';
            $nextSteps = $kb['next_steps'] ?? array();
            if ($pricing !== '') {
                $sections[] = "═══ PRECIO ═══\n{$pricing}";
            }
            if ($features !== '') {
                $sections[] = "═══ BENEFICIO CLAVE ═══\n{$features}";
            }
            if (!empty($nextSteps)) {
                $sections[] = "═══ SIGUIENTE PASO ═══\n" . implode("\n", array_map(function($s) { return '- ' . $s; }, array_slice($nextSteps, 0, 2)));
            }
            $sections[] = "═══ REGLAS DE ESTA FASE ═══\n- Máximo 5 líneas.\n- Precio + 1 beneficio clave + siguiente paso.\n- NUNCA responder objeciones que no hayan hecho.\n- NUNCA 'si no te gusta...', 'sin compromiso'.\n- NUNCA presiones el cierre: prohibido '¿Te activo hoy mismo?', '¿Te activo ya?', '¿Empezamos?', urgencia fabricada.\n- Si el cliente pidió SOLO información, responde y termina en el dato útil, sin invitarle a pedir una explicación genérica.\n- Si hace falta avanzar, usa una pregunta concreta y sin presión.";
            $casawasapPricing = function_exists('comercial_casawasap_pricing') ? comercial_casawasap_pricing() : ['weekly_price' => 50.0, 'extra_line_price' => 10.0];
            $sections[] = "═══ EJEMPLO MALO ═══\n\"" . number_format($casawasapPricing['weekly_price'], 0, ',', '.') . "€/semana. También tenemos líneas extra a " . number_format($casawasapPricing['extra_line_price'], 0, ',', '.') . "€, dashboard de estadísticas, recordatorios ETA, anti-regateo, publicación de estados, memoria de clientes recurrentes...\" → SOLTÓ TODAS LAS FEATURES EN VEZ DE IR PASO A PASO.";
            $sections[] = "═══ EJEMPLO MALO ═══\n\"...Alta única 29€ y luego 10€ por cada 30 min de cliente que te llegue. ¿Te activo hoy mismo?\" → CIERRE AGRESIVO CON PRESIÓN: el cliente solo pidió información y se le pide activar ya. Sustitúyelo por un CTA suave sin urgencia.";
            break;

        case 'MANEJO_OBJECIONES':
            // Detectar qué objeción aplica
            $inboundLower = trim(mb_strtolower($inboundText, 'UTF-8'));
            $objResponse = '';
            foreach (array('caro', 'no_confio', 'ya_tengo', 'no_se_si_funciona', 'no_tengo_tiempo',
                'no_conozco', 'y_si_no_me_gusta', 'ya_publico_yo', 'quiero_pensarlo',
                'prefiero_contratar_persona', 'mis_clientes_quieren_hablar_conmigo', 'no_tengo_volumen',
                'no_quiero_dar_soporte', 'precio_muy_alto', 'tengo_publicista',
                'no_conozco_la_zona', 'ya_tengo_donde_estar', 'no_tengo_dinero_ahora') as $objKey) {
                if (isset($kb[$objKey]) && $kb[$objKey] !== '') {
                    // Solo usar si el texto entrante contiene keywords relevantes
                    $found = false;
                    $objWords = explode('_', $objKey);
                    foreach ($objWords as $w) {
                        if (mb_stripos($inboundLower, $w) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    // Si no hay texto entrante específico, usar la primera objeción disponible como fallback
                    if ($found || $objResponse === '') {
                        $objResponse = $kb[$objKey];
                    }
                }
            }
            if ($objResponse !== '') {
                $sections[] = "═══ RESPUESTA A LA OBJECIÓN ═══\n{$objResponse}";
            }
            $sections[] = "═══ REGLAS DE ESTA FASE ═══\n- Máximo 4 líneas.\n- SOLO responder a la objeción que ha hecho.\n- NUNCA añadir nuevas features ni precios.\n- Terminar con pregunta de reenganche.\n- Si ya has respondido 2 objeciones en esta conversación, mueve a cierre.";
            $sections[] = "═══ EJEMPLO MALO ═══\n\"No es caro si lo comparas con otras opciones. Además tenemos smartTV, buen ambiente, sábanas incluidas, varios baños...\" → AÑADE FEATURES EN VEZ DE MANEJAR LA OBJECIÓN.";
            break;

        case 'CIERRE':
            $escalation = $kb['escalation'] ?? '';
            if ($escalation !== '') {
                $sections[] = "═══ MENSAJE DE CIERRE ═══\n{$escalation}";
            }
            $sections[] = "═══ REGLAS DE ESTA FASE ═══\n- Máximo 4 líneas.\n- Confirmar interés del prospecto.\n- Dar el siguiente paso concreto.\n- NUNCA seguir vendiendo ni añadir features.\n- NO terminar con pregunta.\n- 0 emojis o máximo 1.";
            $sections[] = "═══ EJEMPLO MALO ═══\n\"Genial, te activo la prueba. Además recuerda que tienes dashboard, soporte 24/7, publicación de estados...\" → SIGUE VENDIENDO EN VEZ DE CERRAR.";
            break;

        default:
            $sections[] = "═══ REGLAS ═══\n- Máximo 5 líneas.\n- Responde con naturalidad.\n- Una pregunta para avanzar la conversación.";
            break;
    }

    // Tono
    $tone = $common['tone'] ?? 'Natural, WhatsApp real, frases cortas. NADA formalismos.';
    $sections[] = "═══ TONO ═══\n{$tone}\n- Sin markdown, sin listas, sin formato especial.\n- Varía la estructura de tus mensajes, no uses siempre el mismo patrón.\n- NUNCA uses los signos de apertura ¿ ni ¡.\n- NUNCA presiones ni fuerces el cierre (prohibido '¿Te activo hoy mismo?', '¿Te activo ya?', '¿Empezamos?', urgencia fabricada).\n- Si el cliente pidió solo información, responde de forma suficiente y termina en el dato útil, nunca con una invitación genérica a explicar más.\n- Picardía suave: un detalle concreto, real y útil que dé ganas de seguir; nunca sarcasmo, promesas inventadas ni misterio vacío.";

    // ── Ejemplos humanos relevantes (selección por keyword + fase) ──
    $examples = comercial_ai_memory_relevant_examples($processSlug, $inboundText, $phase, 3);
    if (!empty($examples)) {
        $exampleLines = array();
        foreach ($examples as $i => $ex) {
            $exampleLines[] = ($i + 1) . ') ' . trim((string)($ex['text'] ?? ''));
        }
        $sections[] = "═══ RESPUESTAS QUE FUNCIONARON EN CONVERSACIONES SIMILARES ═══\n" . implode("\n\n", $exampleLines) . "\n\nÚsalas como inspiración de tono y enfoque, NO las copies textualmente.";
    }

    // ── Playbook de aprendizaje del proceso ──
    $playbook = comercial_agent_load_playbook($processSlug);
    if ($playbook !== '') {
        $sections[] = "═══ APRENDIZAJES DE CONVERSACIONES REALES (playbook) ═══\n" . $playbook;
    }

    // Instrucción final
    $sections[] = 'Responde SOLO con el texto del mensaje de WhatsApp. Sin comillas, sin prefijos, sin "Respuesta:". Solo el texto.';

    return implode("\n\n", $sections);
}

// ═══════════════════════════════════════════════════════════════
//  MODO: OPENER — Generar primer mensaje de apertura
// ═══════════════════════════════════════════════════════════════

function comercial_agent_generate_opener(array $thread, string $processSlug, string $model, array $cfg, array $opts): array {
    $kb = comercial_knowledge_get($processSlug);
    // AGENT V2: pasar fase SALUDO_INICIAL si KB v2 está disponible
    $phase = 'SALUDO_INICIAL';
    $thread['conversation_phase'] = $phase;
    $systemPrompt = comercial_agent_build_system_prompt($processSlug, 'opener', $thread, array(), $phase);

    // ── Anti-repetición: inyectar aperturas recientes como ejemplos negativos ──
    $antiRepeatNote = '';
    if (function_exists('comercial_anti_spam_recent_openers')) {
        $recentOpeners = comercial_anti_spam_recent_openers($processSlug, 30);
        if (!empty($recentOpeners)) {
            $antiRepeatNote = "\n\n═══ MENSAJES RECIENTES QUE NO DEBES REPETIR ═══\nEstos son mensajes que ya se enviaron antes a otras personas. NO uses nada parecido a estos. Sé CREATIVO y genera algo completamente NUEVO:\n" . implode("\n---\n", array_map(function($o) { return '"' . $o . '"'; }, $recentOpeners));
        }
    }

    // ── Estilos de apertura ──
    $styles = $kb['opening_styles'] ?? array('Presentación natural y cercana');
    $styleNote = "\n\n═══ ESTILOS DE APERTURA SUGERIDOS ═══\nElige UNO de estos enfoques (o combina sutilmente) para tu mensaje. NO los uses como plantilla, solo como inspiración:\n- " . implode("\n- ", $styles);

    // ── Máx. líneas configurables por negocio (LaMami desmenuza el concepto) ──
    $maxOpenerLines = 4;
    if (function_exists('comercial_knowledge_v2_get')) {
        $openerKb = comercial_knowledge_v2_get($processSlug, 'SALUDO_INICIAL');
        if (isset($openerKb['max_lines']) && (int)$openerKb['max_lines'] > 0) {
            $maxOpenerLines = (int)$openerKb['max_lines'];
        }
    } elseif (isset($kb['max_lines']) && (int)$kb['max_lines'] > 0) {
        $maxOpenerLines = (int)$kb['max_lines'];
    }

    $prompt = $systemPrompt . $antiRepeatNote . $styleNote . "\n\n" .
        "═══ TAREA ═══\n" .
        "Genera UN mensaje de apertura para iniciar una conversación de WhatsApp.\n" .
        "Es la PRIMERA vez que contactas a esta persona. NO sabes nada de ella.\n" .
        "Reglas adicionales:\n" .
        "- Sé NATURAL. Parece que una persona real escribe, no un guion de ventas.\n" .
        "- NO uses frases genéricas de telemarketing.\n" .
        "- NO empieces siempre con 'Hola'. Varía el saludo.\n" .
        "- Sé breve (máximo {$maxOpenerLines} líneas de WhatsApp).\n" .
         "- Incluye una invitación suave y concreta a responder, sin pregunta retórica ni cierre genérico.\n" .
        "- NO preguntes '¿cómo estás?' de forma genérica.\n" .
         "- Usa la información de la sección LO QUE VENDES con naturalidad.\n" .
         "- No menciones precios ni porcentajes en la apertura, aunque estén en la base; solo bajo demanda.\n" .
         "- PROHIBIDO usar los signos de apertura ¿ y ¡.\n" .
        "- RESPONDE ÚNICAMENTE con el texto del mensaje. Nada más.";

    // reasoning_effort low: el opener de campaña no es hot-path (se envía por cron),
    // así que primamos rapidez para no ralentizar el tick.
    $result = comercial_agent_call_llm($prompt, $model, $cfg, 400, 'low');
    if (!empty($result['ok'])) {
        $result['text'] = comercial_ensure_official_url_in_opener(
            (string)($result['text'] ?? ''),
            comercial_official_url_for_slug($processSlug)
        );
    }
    return $result;
}

// ═══════════════════════════════════════════════════════════════
//  MODO: REPLY — Generar respuesta a un mensaje del usuario
// ═══════════════════════════════════════════════════════════════

function comercial_agent_generate_reply(array $thread, string $processSlug, string $model, array $cfg, array $opts): array {
    // AGENT V2: pasar fase de conversación al prompt builder
    $phase = (string)($opts['phase'] ?? $thread['conversation_phase'] ?? 'DESCUBRIMIENTO');
    $systemPrompt = comercial_agent_build_system_prompt($processSlug, 'reply', $thread, $opts, $phase);
    $inboundText = trim((string)($opts['inbound_text'] ?? ''));

    // ── Historial de conversación ──
    $history = comercial_thread_history($thread, 20);
    $historyLines = array();
    foreach ((array)$history as $entry) {
        $text = trim((string)($entry['text'] ?? ''));
        if ($text === '') continue;
        $direction = (string)($entry['direction'] ?? '') === 'in' ? 'CLIENTE' : 'TÚ (COMERCIAL)';
        $historyLines[] = $direction . ': ' . $text;
    }

    // ── Clasificación previa para contexto ──
    $classificationNote = '';
    $classification = $opts['classification'] ?? null;
    if (is_array($classification) && !empty($classification['intent'])) {
        $classificationNote = "\n\n═══ CONTEXTO DE CLASIFICACIÓN ═══\n" .
            "Intención del cliente: " . ($classification['intent'] ?? 'desconocida') . "\n" .
            "Sentimiento: " . ($classification['sentiment'] ?? 'neutral') . "\n" .
            "Lead score: " . ($classification['lead_score'] ?? 0) . "/100\n" .
            "Acción sugerida: " . ($classification['next_action'] ?? 'continue') . "\n" .
            "Estrategia: " . ($classification['suggested_strategy'] ?? 'responder con naturalidad');
    }

    // ── Detectar saludo simple ──
    $greetingOnlyNote = '';
    if (!empty($opts['greeting_only'])) {
        $greetingOnlyNote = "\n\nIMPORTANTE: El cliente SOLO saludó, NO hizo ninguna pregunta concreta. NO uses frases como 'me alegra que preguntes', 'gracias por preguntar', 'buena pregunta' ni similares. Responde al saludo de forma natural y haz una pregunta abierta suave para iniciar conversación.";
    }

    $turnCount = (int)($thread['auto_turn_count'] ?? 0) + 1;
    $officialUrl = comercial_official_url_for_slug($processSlug);
    $urlGuidance = comercial_reply_url_guidance(
        $officialUrl,
        comercial_official_url_was_shared($history, $officialUrl),
        $inboundText
    );
    $urlGuidanceNote = $urlGuidance !== '' ? "\n\n═══ URL OFICIAL Y CONTEXTO ═══\n{$urlGuidance}" : '';

    $prompt = $systemPrompt . $classificationNote . $greetingOnlyNote . $urlGuidanceNote . "\n\n" .
        "═══ CONVERSACIÓN (turno " . $turnCount . ") ═══\n" .
        (empty($historyLines) ? "(inicio de conversación)" : implode("\n", $historyLines)) . "\n\n" .
        "═══ ÚLTIMO MENSAJE DEL CLIENTE ═══\n" .
        "\"" . ($inboundText !== '' ? $inboundText : '(sin texto)') . "\"\n\n" .
        "═══ TAREA ═══\n" .
        "Responde al cliente de forma NATURAL y CONCISA.\n" .
        "Objetivo: avanzar la conversación hacia un lead (señal de compra real).\n" .
        "Reglas adicionales:\n" .
        "- Responde PRIMERO a lo que preguntó el cliente.\n" .
        "- Si preguntó algo que está en el FAQ, usa esa información.\n" .
        "- Si es una objeción, aplica la estrategia de objeción correspondiente.\n" .
        "- Si el cliente da señales de lead, prepara el cierre.\n" .
        "- Si el cliente claramente NO está interesado (3+ mensajes sin avance), despídete cordialmente.\n" .
        "- NO insistas más de 2 veces sobre el mismo punto.\n" .
        "- NO inventes datos, precios ni URLs.\n" .
        "- NO uses el mismo patrón de respuesta que los mensajes anteriores tuyos.\n" .
        "- PROHIBIDO usar los signos de apertura ¿ y ¡; escribe con puntuación natural de WhatsApp.\n" .
        "- Evita preguntas retóricas y no encadenes preguntas innecesarias.\n" .
        "- NUNCA presiones ni fuerces el cierre: prohibido '¿Te activo hoy mismo?', '¿Te activo ya?', '¿Empezamos?', 'te lo dejo funcionando hoy', urgencia fabricada ('hoy mismo', 'ya').\n" .
        "- Si el cliente pidió SOLO información, responde esa información y termina en el dato útil. PROHIBIDO cerrar con 'quieres que te explique algo más', 'te ayudo en algo más' o equivalentes. No pidas activar/empezar sin intención clara de compra.\n" .
        "- Tono cercano y escueto, con picardía suave basada en un detalle real. No seas condescendiente, no ocultes información relevante pedida y no uses misterio artificial.\n" .
        "- RESPONDE ÚNICAMENTE con el texto del mensaje. Nada más.";

    // reasoning_effort medium: reduce latencia en el hot-path real-time del reply
    return comercial_agent_call_llm($prompt, $model, $cfg, 500, 'medium');
}

// ═══════════════════════════════════════════════════════════════
//  MODO: CLASSIFY — Clasificar intención y lead potential (LLM)
// ═══════════════════════════════════════════════════════════════

function comercial_agent_classify(array $thread, string $processSlug, string $model, array $cfg, array $opts): array {
    $kb = comercial_knowledge_get($processSlug);
    $inboundText = trim((string)($opts['inbound_text'] ?? ''));

    // ── Historial ──
    $history = comercial_thread_history($thread, 20);
    $historyLines = array();
    foreach ((array)$history as $entry) {
        $text = trim((string)($entry['text'] ?? ''));
        if ($text === '') continue;
        $direction = (string)($entry['direction'] ?? '') === 'in' ? 'CLIENTE' : 'COMERCIAL';
        $historyLines[] = $direction . ': ' . $text;
    }

    $leadSignals = $kb['lead_signals'] ?? array();
    $disqualifySignals = $kb['disqualify_signals'] ?? array();

    $prompt = "Eres un clasificador de conversaciones de WhatsApp para ventas. Analiza el último mensaje del cliente y clasifícalo.\n\n" .
        "═══ NEGOCIO ═══\n" . $kb['business_name'] . " (" . $processSlug . ")\n\n" .
        "═══ SEÑALES DE LEAD (compra real) ═══\n- " . implode("\n- ", $leadSignals) . "\n\n" .
        "═══ SEÑALES DE NO INTERÉS ═══\n- " . implode("\n- ", $disqualifySignals) . "\n\n" .
        "═══ HISTORIAL ═══\n" . (empty($historyLines) ? "(sin historial)" : implode("\n", $historyLines)) . "\n\n" .
        "═══ ÚLTIMO MENSAJE DEL CLIENTE ═══\n\"" . ($inboundText !== '' ? $inboundText : '(sin texto)') . "\"\n\n" .
        "═══ TAREA ═══\n" .
        "Lee el HISTORIAL COMPLETO de la conversacion, NO solo el ultimo mensaje. " .
        "Evalua si hay INTERES GENUINO DE COMPRA: mira trayectoria global, subtexto e intencion real. " .
        "Una misma necesidad se expresa de muchas formas, no uses patrones fijos. " .
        "Devuelve ÚNICAMENTE un JSON valido (sin markdown, sin explicaciones) con esta estructura:\n" .
        "{\n" .
        "  \"intent\": \"greeting|asking_info|asking_price|negotiating|objection|interested|ready_to_buy|not_interested|off_topic|ack_only\",\n" .
        "  \"sentiment\": \"positive|neutral|negative|excited\",\n" .
        "  \"lead_score\": 0-100,\n" .
        "  \"next_action\": \"continue|escalate_to_human|disqualify|ask_discovery|close\",\n" .
        "  \"reasoning\": \"breve explicacion de la clasificacion\",\n" .
        "  \"suggested_strategy\": \"enfoque recomendado para responder\",\n" .
        "  \"genuine_interest\": true,\n" .
        "  \"interest_evidence\": \"explica por que si o no hay interes real en 1 linea\"\n" .
        "}";

    $resp = comercial_agent_call_classify($prompt, $cfg);

    if (empty($resp['ok'])) {
        // Fallback: clasificación básica si el LLM falla
        return array(
            'ok' => true,
            'intent' => 'responded',
            'sentiment' => 'neutral',
            'lead_score' => 0,
            'next_action' => 'continue',
            'reasoning' => 'fallback: llm_classification_failed',
            'suggested_strategy' => 'responder con naturalidad',
        );
    }

    return $resp;
}

// ═══════════════════════════════════════════════════════════════
//  MODO: SUMMARY — Resumen de escalación para agente humano
// ═══════════════════════════════════════════════════════════════

function comercial_agent_escalation_summary(array $thread, string $processSlug, string $model, array $cfg, array $opts): array {
    $kb = comercial_knowledge_get($processSlug);

    // ── Historial completo ──
    $history = comercial_thread_history($thread, 50);
    $historyLines = array();
    foreach ((array)$history as $entry) {
        $text = trim((string)($entry['text'] ?? ''));
        if ($text === '') continue;
        $direction = (string)($entry['direction'] ?? '') === 'in' ? 'CLIENTE' : 'BOT';
        $historyLines[] = $direction . ': ' . $text;
    }

    $prompt = "Genera un resumen estructurado de esta conversación de WhatsApp para pasárselo a un agente humano.\n\n" .
        "═══ NEGOCIO ═══\n" . $kb['business_name'] . "\n\n" .
        "═══ CONVERSACIÓN COMPLETA ═══\n" . implode("\n", $historyLines) . "\n\n" .
        "═══ TAREA ═══\n" .
        "Devuelve ÚNICAMENTE un JSON válido (sin markdown, sin explicaciones) con esta estructura:\n" .
        "{\n" .
        "  \"nombre_cliente\": \"nombre si se mencionó, si no ''\",\n" .
        "  \"interes\": \"qué quiere el cliente, en una frase\",\n" .
        "  \"presupuesto\": \"si se mencionó presupuesto, si no ''\",\n" .
        "  \"urgencia\": \"alta|media|baja|no_clara\",\n" .
        "  \"objeciones_superadas\": \"qué objeciones se manejaron y cómo\",\n" .
        "  \"proximo_paso\": \"qué se acordó como siguiente paso\",\n" .
        "  \"lead_score\": 0-100,\n" .
        "  \"notas_para_agente\": \"cualquier detalle relevante para el agente humano\"\n" .
        "}";

    $resp = comercial_agent_call_llm_json($prompt, $model, $cfg, 300);

    if (empty($resp['ok'])) {
        return array(
            'ok' => true,
            'summary' => array(
                'nombre_cliente' => '',
                'interes' => 'Conversación escalada desde bot comercial',
                'lead_score' => 70,
                'notas_para_agente' => 'Revisar historial completo de la conversación.',
            ),
        );
    }

    return array('ok' => true, 'summary' => $resp);
}

// ═══════════════════════════════════════════════════════════════
//  LLM CALL HELPERS (DeepSeek con thinking)
// ═══════════════════════════════════════════════════════════════

/**
 * Llama a DeepSeek (chat/completions) con retry y extracción solo de 'content'.
 * NUNCA usa 'reasoning_content' (razonamiento interno en inglés) como salida.
 *
 * @param array  $cfg         Config DeepSeek (de comercial_agent_critic_get_config)
 * @param string $model       Modelo (deepseek-v4-pro para pensar, deepseek-v4-flash para rápido)
 * @param array  $messages    Mensajes chat [{role, content}]
 * @param float  $temperature
 * @param int    $maxTokens   Tokens máximos de salida (razonamiento + respuesta)
 * @param bool   $json        Si true, fuerza response_format json_object
 * @param bool   $thinking    Si true, activa thinking + reasoning_effort high
 * @param string $reasoningEffort Nivel de razonamiento (low|medium|high)
 * @return array ['ok'=>bool, 'text'=>string, 'model'=>string] o ['ok'=>false, 'error'=>string]
 */
function comercial_agent_deepseek_request(array $cfg, string $model, array $messages, float $temperature, int $maxTokens, bool $json, bool $thinking, string $reasoningEffort = 'high'): array {
    $apiKey = trim((string)($cfg['api_key'] ?? ''));
    $apiUrl = rtrim((string)($cfg['api_url'] ?? 'https://api.deepseek.com'), '/') . '/chat/completions';
    if ($apiKey === '') {
        return array('ok' => false, 'error' => 'deepseek_not_configured');
    }

    $payload = array(
        'model' => $model,
        'messages' => $messages,
        'temperature' => $temperature,
        'max_tokens' => $maxTokens,
    );
    $payload['thinking'] = array('type' => $thinking ? 'enabled' : 'disabled');
    if ($thinking) {
        $payload['reasoning_effort'] = $reasoningEffort;
    }
    if ($json) {
        $payload['response_format'] = array('type' => 'json_object');
    }

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 15,
        ));

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '' || $httpCode < 200 || $httpCode >= 300) {
            continue; // reintentar
        }

        $resp = json_decode((string)$raw, true);
        if (!is_array($resp) || !empty($resp['error'])) {
            continue;
        }

        $content = $resp['choices'][0]['message']['content'] ?? null;
        if ($content !== null && trim((string)$content) !== '') {
            return array('ok' => true, 'text' => trim((string)$content), 'model' => $model);
        }
    }

    return array('ok' => false, 'error' => 'deepseek_empty_content');
}

/**
 * Llama al LLM (DeepSeek con thinking) y devuelve texto plano.
 */
function comercial_agent_call_llm(string $prompt, string $model, array $cfg, int $maxTokens = 400, string $reasoningEffort = 'high'): array {
    $resp = comercial_agent_deepseek_request($cfg, $model, array(array('role' => 'user', 'content' => $prompt)), 0.7, 4096, false, true, $reasoningEffort);
    if (empty($resp['ok'])) {
        return array('ok' => false, 'error' => trim((string)($resp['error'] ?? 'ai_request_failed')));
    }

    $text = trim((string)$resp['text']);
    if ($text === '') {
        return array('ok' => false, 'error' => 'ai_empty_output');
    }

    // Limitar a 800 chars máximo
    if (function_exists('comercial_safe_len') && comercial_safe_len($text) > 800) {
        $text = function_exists('mb_substr')
            ? trim((string)mb_substr($text, 0, 800, 'UTF-8'))
            : trim(substr($text, 0, 800));
    }

    return array('ok' => true, 'text' => $text, 'model' => $model);
}

/**
 * Llama al LLM (DeepSeek con thinking) y devuelve JSON parseado.
 */
function comercial_agent_call_llm_json(string $prompt, string $model, array $cfg, int $maxTokens = 200): array {
    $resp = comercial_agent_deepseek_request($cfg, $model, array(array('role' => 'user', 'content' => $prompt)), 0.3, 4096, true, true);
    if (empty($resp['ok'])) {
        return array('ok' => false, 'error' => trim((string)($resp['error'] ?? 'ai_request_failed')));
    }

    $text = trim((string)$resp['text']);
    if ($text === '') {
        return array('ok' => false, 'error' => 'ai_empty_output');
    }

    return comercial_agent_parse_json_response($text);
}

/**
 * Clasificación rápida (deepseek-v4-flash, sin thinking) para el hot-path de cada mensaje.
 */
function comercial_agent_call_classify(string $prompt, array $cfg): array {
    $resp = comercial_agent_deepseek_request($cfg, 'deepseek-v4-flash', array(array('role' => 'user', 'content' => $prompt)), 0.3, 600, true, false);
    if (empty($resp['ok'])) {
        return array('ok' => false, 'error' => trim((string)($resp['error'] ?? 'ai_request_failed')));
    }

    $text = trim((string)$resp['text']);
    if ($text === '') {
        return array('ok' => false, 'error' => 'ai_empty_output');
    }

    return comercial_agent_parse_json_response($text);
}

/**
 * Limpia markdown y parsea JSON de una respuesta LLM.
 */
function comercial_agent_parse_json_response(string $text): array {
    $text = preg_replace('/^```(?:json)?\s*\n?/i', '', $text);
    $text = preg_replace('/\n?```\s*$/i', '', $text);
    $text = trim($text);

    $decoded = json_decode($text, true);
    if (!is_array($decoded)) {
        return array('ok' => false, 'error' => 'ai_invalid_json', 'raw' => $text);
    }

    return array_merge(array('ok' => true), $decoded);
}

// ═══════════════════════════════════════════════════════════════
//  LEAD DETECTION (post-classify)
// ═══════════════════════════════════════════════════════════════

/**
 * Decide si escalar a humano basado en la clasificación LLM.
 * Reemplaza comercial_decide_inbound_action().
 */
function comercial_agent_decide_escalation(array $classification, array $thread, array $process = array()): array {
    $leadScore = (int)($classification['lead_score'] ?? 0);
    $intent = (string)($classification['intent'] ?? '');
    $nextAction = (string)($classification['next_action'] ?? 'continue');

    $threshold = (int)($process['escalation_score_threshold'] ?? 78);

    // Escalar si:
    // 1. El LLM dice explícitamente 'escalate_to_human'
    // 2. El lead_score supera el threshold
    // 3. La intención es 'ready_to_buy'
    if ($nextAction === 'escalate_to_human' || $leadScore >= $threshold || $intent === 'ready_to_buy') {
        return array(
            'action' => 'escalate_to_human',
            'lead_score' => $leadScore,
            'reason' => $leadScore >= $threshold ? 'lead_score_threshold' : 'llm_explicit_escalation',
        );
    }

    // Descalificar si el LLM dice 'disqualify' o 'not_interested'
    if ($nextAction === 'disqualify' || $intent === 'not_interested') {
        return array(
            'action' => 'disqualify',
            'lead_score' => $leadScore,
            'reason' => $intent === 'not_interested' ? 'not_interested' : 'llm_disqualify',
        );
    }

    // Si es 'ack_only' (solo "ok", "vale", etc.), no responder
    if ($intent === 'ack_only') {
        return array(
            'action' => 'skip_ack',
            'lead_score' => $leadScore,
            'reason' => 'ack_only_message',
        );
    }

    // Continuar conversación
    return array(
        'action' => 'continue',
        'lead_score' => $leadScore,
        'reason' => 'continue_conversation',
    );
}

// ═══════════════════════════════════════════════════════════════
//  CONTRATO COMÚN DE DECISIÓN (AGENT V3)
//  Sustituye el antiguo flujo "max_auto_turns + intent → handler".
//  El LLM solo sugiere; el servidor valida y recalcula con guardas.
// ═══════════════════════════════════════════════════════════════

/**
 * Enum de estados de automatización del bot comercial.
 * - continue              : seguir conversando, sin aviso.
 * - lead_hot              : avisar al dueño pero permitir seguir respondiendo.
 * - human_intervention    : aviso crítico + pausar el bot.
 * - disqualified          : desinterés → despedida única y cerrar.
 * - opted_out             : baja explícita → cero mensajes futuros.
 */
function comercial_automation_states(): array {
    return array('continue', 'lead_hot', 'human_intervention', 'disqualified', 'opted_out');
}

function comercial_automation_is_terminal_state(string $state): bool {
    return in_array($state, array('opted_out', 'disqualified'), true);
}

/**
 * Normaliza y valida la decisión semántica devuelta por el LLM.
 * El servidor recalcula reply_allowed / pause_bot a partir del estado:
 * el modelo nunca decide directamente si puede enviar.
 */
function comercial_normalize_agent_decision(array $raw): array {
    $states = comercial_automation_states();
    $state = trim((string)($raw['state'] ?? 'continue'));
    if (!in_array($state, $states, true)) {
        return array('ok' => false, 'state' => 'continue', 'reply_allowed' => false, 'pause_bot' => false, 'error' => 'invalid_automation_state');
    }
    $confidence = max(0.0, min(1.0, (float)($raw['confidence'] ?? 0)));
    $leadScore = max(0, min(100, (int)($raw['lead_score'] ?? 0)));
    $intent = trim((string)($raw['intent'] ?? ''));
    $reason = trim((string)($raw['reason'] ?? ''));

    $replyAllowed = true;
    $pauseBot = false;
    $notificationRequired = false;
    $notificationPriority = 'none';

    if ($state === 'lead_hot') {
        $replyAllowed = true;
        $pauseBot = false;
        $notificationRequired = true;
        $notificationPriority = 'high';
    } elseif ($state === 'human_intervention') {
        $replyAllowed = false;
        $pauseBot = true;
        $notificationRequired = true;
        $notificationPriority = 'critical';
    } elseif ($state === 'disqualified') {
        $replyAllowed = false;
        $pauseBot = true;
        $notificationRequired = false;
    } elseif ($state === 'opted_out') {
        $replyAllowed = false;
        $pauseBot = true;
        $notificationRequired = false;
    }

    return array(
        'ok' => true,
        'state' => $state,
        'confidence' => $confidence,
        'lead_score' => $leadScore,
        'intent' => $intent,
        'reason' => $reason,
        'reply_allowed' => $replyAllowed,
        'pause_bot' => $pauseBot,
        'notification_required' => $notificationRequired,
        'notification_priority' => $notificationPriority,
        'legacy_stage' => $state === 'lead_hot' ? 'very_hot' : ($state === 'disqualified' ? 'discarded' : ($state === 'human_intervention' ? 'very_hot' : 'responded')),
    );
}

/**
 * Aplica las guardas server-side POR ENCIMA de lo que sugiera el LLM.
 * Precedencia: intervención manual > baja explícita > pausa previa >
 * estado terminal persistido > decisión del LLM.
 */
function comercial_resolve_automation_decision(array $thread, array $decision): array {
    $thread = is_array($thread) ? $thread : array();
    $decision = is_array($decision) ? $decision : array();

    $manual = !empty($thread['human_taken']) || !empty($thread['inbox_paused']);
    $persistedState = trim((string)($thread['automation_state'] ?? ''));

    // 1. Intervención manual / pausa previa
    if ($manual) {
        return array(
            'ok' => true,
            'state' => 'human_intervention',
            'reply_allowed' => false,
            'pause_bot' => true,
            'notification_required' => false,
            'notification_priority' => 'none',
            'reason' => 'manual_intervention',
            'guard' => 'manual',
        );
    }
    // 2. Baja explícita persistida
    if ($persistedState === 'opted_out') {
        return array(
            'ok' => true,
            'state' => 'opted_out',
            'reply_allowed' => false,
            'pause_bot' => true,
            'notification_required' => false,
            'notification_priority' => 'none',
            'reason' => 'opted_out',
            'guard' => 'opt_out',
        );
    }
    // 3. Estado terminal persistido
    if ($persistedState !== '' && comercial_automation_is_terminal_state($persistedState) && $persistedState !== 'disqualified') {
        return array(
            'ok' => true,
            'state' => $persistedState,
            'reply_allowed' => false,
            'pause_bot' => true,
            'notification_required' => false,
            'notification_priority' => 'none',
            'reason' => 'terminal_state',
            'guard' => 'terminal',
        );
    }

    // 4. Si el LLM no devolvió un contrato válido → no responder automáticamente
    if (empty($decision['ok'])) {
        return array(
            'ok' => true,
            'state' => 'continue',
            'reply_allowed' => false,
            'pause_bot' => false,
            'notification_required' => false,
            'notification_priority' => 'none',
            'reason' => 'invalid_decision_safe_fallback',
            'guard' => 'technical',
        );
    }

    $state = (string)($decision['state'] ?? 'continue');
    // Si ya hubo una intervención inmediata persistida, no reabrir solo por un mensaje.
    if ($persistedState === 'human_intervention' && $state !== 'opted_out' && $state !== 'human_intervention') {
        return array(
            'ok' => true,
            'state' => 'human_intervention',
            'reply_allowed' => false,
            'pause_bot' => true,
            'notification_required' => false,
            'notification_priority' => 'none',
            'reason' => 'pending_human_intervention',
            'guard' => 'persisted',
        );
    }

    return $decision;
}

/**
 * Clave estable de notificación por transición.
 * thread_id + message_id + estado normalizado + tipo de aviso.
 */
function comercial_automation_notification_key(string $threadId, string $state, string $messageId = '', string $type = 'owner'): string {
    $key = 'automation_notify|' . $threadId . '|' . $state . '|' . $type;
    if ($messageId !== '') {
        $key .= '|' . $messageId;
    }
    return $key;
}

/**
 * Traduce el resultado de clasificación LLM + decisión legacy al estado común
 * del contrato AGENT V3. Nunca es autoridad final: solo sugiere.
 */
function comercial_agent_map_classify_to_state(array $classify, array $decision): string {
    $intent = (string)($classify['intent'] ?? '');
    $nextAction = (string)($classify['next_action'] ?? '');
    $leadScore = (int)($classify['lead_score'] ?? 0);
    $action = (string)($decision['action'] ?? '');

    // Escalado explícito o compra clara → intervención inmediata
    if ($action === 'escalate_human' || $nextAction === 'escalate_to_human' || $intent === 'ready_to_buy') {
        return 'human_intervention';
    }
    // Desinterés / no procede → descalificado
    if ($intent === 'not_interested' || $nextAction === 'disqualify' || $action === 'disqualify') {
        return 'disqualified';
    }
    // Interés real → caliente (avisa y sigue hablando)
    if ($intent === 'interested' || $action === 'escalate_human' || $leadScore >= 78) {
        return 'lead_hot';
    }
    // Cualquier otro caso → seguir conversando
    return 'continue';
}
