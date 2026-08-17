<?php
/**
 * comercial_agent_critic.php — Crítico LLM (DeepSeek) para el bot comercial.
 *
 * Evalúa cada respuesta generada por GPT-4o-mini antes del envío.
 * Si no pasa la checklist, DeepSeek la reescribe.
 * Si aún falla, se usa un fallback determinista por fase.
 *
 * DeepSeek es ~10x más barato que GPT-4o-mini para esta tarea.
 */

declare(strict_types=1);

/**
 * Obtiene la configuración de DeepSeek para el crítico.
 * Busca en: settings comerciales → env vars → settings CRM → config de bot-casa.
 */
function comercial_agent_critic_get_config(): array {
    $settings = function_exists('comercial_get_settings') ? comercial_get_settings() : array();

    // Buscar API key en orden de prioridad
    $apiKey = trim((string)($settings['deepseek_api_key'] ?? getenv('DEEPSEEK_API_KEY') ?? ''));
    if ($apiKey === '') {
        // Buscar en settings generales del CRM (publicista_copy_api_key)
        $settingsGlobal = function_exists('settings_get') ? settings_get() : array();
        $apiKey = trim((string)($settingsGlobal['publicista_copy_api_key'] ?? ''));
    }
    if ($apiKey === '') {
        // Leer del config de bot-casa
        $botCfgPath = dirname(__DIR__) . '/bot-casa/config.json';
        $botCfgLocalPath = dirname(__DIR__) . '/bot-casa/config.local.json';
        foreach (array($botCfgLocalPath, $botCfgPath) as $path) {
            if (file_exists($path)) {
                $botCfg = @json_decode((string)file_get_contents($path), true);
                if (is_array($botCfg)) {
                    $apiKey = trim((string)($botCfg['deepseek']['api_key'] ?? ''));
                    if ($apiKey !== '' && $apiKey !== 'CHANGEME_DEEPSEEK_API_KEY') break;
                    $apiKey = '';
                }
            }
        }
    }

    return array(
        'api_key' => $apiKey,
        'api_url' => trim((string)($settings['deepseek_api_url'] ?? $settings['critic_api_url'] ?? 'https://api.deepseek.com')),
        'model' => trim((string)($settings['deepseek_model'] ?? $settings['critic_model'] ?? 'deepseek-v4-pro')),
        'timeout' => 20,
        'enabled' => $apiKey !== '' && $apiKey !== 'CHANGEME_DEEPSEEK_API_KEY',
    );
}

/**
 * Detección determinista de cierre agresivo / presión (independiente del LLM).
 * Fuera de las fases CIERRE y DESCARTADO, mensajes que piden activar/empezar
 * con urgencia ("¿Te activo hoy mismo?", "¿Te activo ya?", "¿Empezamos?",
 * "te lo dejo funcionando hoy") se consideran agresivos y deben reescribirse.
 */
function comercial_agent_critic_detect_aggressive_close(string $text, string $phase): bool {
    $text = trim((string)$text);
    if ($text === '') return false;
    if (in_array($phase, array('CIERRE', 'DESCARTADO'), true)) return false;

    // CTA de cierre con presión (preguntas o afirmaciones directas de activación)
    $patterns = array(
        '/[¿\s]*Te\s+(?:lo\s+)?(?:activo|activamos|activar\w*)\b[^.!?\n]*\??\s*$/iu',
        '/[¿\s]*(?:Empezamos|Arrancamos|Empiezo|Arranco)\b[^.!?\n]*\??\s*$/iu',
        '/[¿\s]*Doy\s+(?:yo\s+)?el\s+alta\b[^.!?\n]*\??\s*$/iu',
        '/te\s+lo\s+dejo\s+(?:funcionando|activado)\b[^.!?\n]*/iu',
        '/\b(hoy mismo|ya mismo)\b.{0,40}(activ|empez|arranc|funcionando)/iu',
        '/\b(activ|empez|arranc)\w*\b.{0,40}(hoy mismo|ya mismo)/iu',
        '/\b(?:no esperes|aprovecha|última oportunidad|solo por hoy|quedan pocas)\b/iu',
    );
    foreach ($patterns as $p) {
        if (preg_match($p, $text)) return true;
    }
    return false;
}

/**
 * Guard determinista: sustituye el cierre agresivo de un mensaje por un CTA
 * suave sin presión, conservando el resto del contenido y la información.
 */
function comercial_soften_aggressive_close(string $text, string $phase = ''): string {
    $text = trim((string)$text);
    if ($text === '') return $text;
    if (in_array($phase, array('CIERRE', 'DESCARTADO'), true)) return $text;
    if (!comercial_agent_critic_detect_aggressive_close($text, $phase)) return $text;

    // 1. Quitar la pregunta/afirmación de cierre con presión al final
    $patterns = array(
        '/[¿\s]*Te\s+(?:lo\s+)?(?:activo|activamos|activar\w*)\b[^.!?\n]*\??\s*$/iu',
        '/[¿\s]*(?:Empezamos|Arrancamos|Empiezo|Arranco)\b[^.!?\n]*\??\s*$/iu',
        '/[¿\s]*Doy\s+(?:yo\s+)?el\s+alta\b[^.!?\n]*\??\s*$/iu',
        '/te\s+lo\s+dejo\s+(?:funcionando|activado)\b[^.!?\n]*[.!]?\s*$/iu',
    );
    foreach ($patterns as $p) {
        $text = preg_replace($p, '', $text);
    }

    // 2. Neutralizar urgencia "hoy mismo" solo en contexto de activación/arranque
    if (preg_match('/\bhoy\s+mismo\b/iu', $text) && preg_match('/(activ|empez|arranc|funcionando|alta)/iu', $text)) {
        $text = preg_replace('/\bhoy\s+mismo\b/iu', 'cuando quieras', $text);
    }

    $text = rtrim((string)$text, " \t\n\r.,;¿?¡!");
    if ($text !== '' && !preg_match('/[.!?;]$/u', $text)) {
        $text .= '.';
    }

    // 3. Añadir un CTA suave
    $soft = array(
        '¿Te queda alguna duda? Me dices y te ayudo sin problema.',
        'Si te convence, me dices y lo montamos. ¿Quieres que te explique algo más?',
        'Sin prisa: si te surge cualquier duda, aquí estoy.',
        '¿Te gustaría que te lo cuente con más detalle?',
    );
    $tail = $soft[array_rand($soft)];
    return trim($text . ($text !== '' ? ' ' : '') . $tail);
}

/**
 * Evalúa un mensaje generado contra la checklist de calidad.
 * Devuelve score, checks y texto reescrito si aplica.
 */
function comercial_agent_critic_evaluate(string $text, string $phase, array $phaseRules = array()): array {
    $cfg = comercial_agent_critic_get_config();

    // Crítico deshabilitado si no hay API key
    if (empty($cfg['enabled']) || empty($cfg['api_key'])) {
        return array('score' => 100, 'text' => $text, 'rewritten' => null, 'reason' => 'critic_disabled_no_key');
    }

    $maxLines = (int)($phaseRules['max_lines'] ?? 5);
    $endWithQuestion = !empty($phaseRules['end_with_question']);
    $questionRule = $endWithQuestion
        ? 'DEBE terminar con pregunta abierta'
        : 'NO debe terminar con pregunta (es fase de cierre)';

    $prompt = <<<PROMPT
Evalúa este mensaje de WhatsApp comercial. Fase: {$phase} (máx {$maxLines} líneas).
Devuelve SOLO JSON, sin markdown ni backticks.

REGLAS:
- Máximo {$maxLines} líneas
- Un solo tema por mensaje
- Prohibido: "quedo a tu disposición", "un saludo", "cualquier consulta", "estamos para ayudarte", "para cualquier cosa dime", "soy del equipo", "somos", "nuestro servicio", "nuestro equipo"
- Tono WhatsApp real, frases cortas, coloquial. No email ni atención al cliente
- Máximo 1 emoji (0 si es fase CIERRE)
- {$questionRule}
- Responder SOLO a lo que preguntó el prospecto, no añadir info no pedida
- Sin autoreferencia ("soy", "somos", "nuestro equipo", "nuestro servicio")
- Prohibido tono agresivo o presión en el cierre: "¿Te activo hoy mismo?", "¿Te activo ya?", "¿Empezamos?", "te lo dejo funcionando hoy", "hoy mismo", urgencia fabricada o pedir activar/empezar sin que el prospecto haya mostrado intención clara. Si el prospecto solo pidió información, termina con un CTA suave ("si te convence, me dices", "¿quieres que te explique algo más?")

Mensaje a evaluar:
"{$text}"

Devuelve SOLO este JSON (sin ``` ni backticks):
{"score":0-100,"checks":{"line_count_ok":bool,"single_topic_ok":bool,"no_bot_tells_ok":bool,"no_disclosure_ok":bool,"natural_tone_ok":bool,"emoji_ok":bool,"no_premature_info_ok":bool,"question_end_ok":bool,"answers_question_ok":bool,"no_aggressive_close_ok":bool},"rewritten":"texto corregido manteniendo la misma info o null","reason":"breve explicación en español"}
PROMPT;

    $payload = json_encode(array(
        'model' => $cfg['model'],
        'messages' => array(
            array('role' => 'system', 'content' => 'Eres un revisor experto de mensajes de WhatsApp comercial. Evalúas con criterio estricto y corriges solo si es necesario. Responde SIEMPRE en JSON.'),
            array('role' => 'user', 'content' => $prompt),
        ),
        'temperature' => 0.1,
        'max_tokens' => 800,
        // El crítico es un evaluador rápido: desactivar thinking explícitamente.
        // deepseek-v4-pro activa reasoning por defecto y devuelve content vacío (finish_reason=length).
        'thinking' => array('type' => 'disabled'),
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init($cfg['api_url'] . '/chat/completions');
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $cfg['timeout'],
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $cfg['api_key'],
        ),
        CURLOPT_POSTFIELDS => $payload,
    ));

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    // Fallback silencioso: si el crítico falla, usar el texto original
    if ($err || $raw === false || $httpCode >= 400) {
        return array(
            'score' => 100,
            'text' => $text,
            'rewritten' => null,
            'reason' => 'critic_http_error:' . ($err ?: 'HTTP ' . $httpCode),
        );
    }

    $resp = json_decode($raw, true);
    $content = trim((string)($resp['choices'][0]['message']['content'] ?? ''));

    // Limpiar markdown si DeepSeek lo envuelve
    $content = preg_replace('/^```(?:json)?\s*\n?/i', '', $content);
    $content = preg_replace('/\n?```\s*$/i', '', $content);
    $content = trim($content);

    $parsed = json_decode($content, true);

    if (!is_array($parsed)) {
        return array(
            'score' => 100,
            'text' => $text,
            'rewritten' => null,
            'reason' => 'critic_invalid_json:' . voice_safe_substr($content, 0, 100),
        );
    }

    $score = (int)($parsed['score'] ?? 100);
    $rewritten = !empty($parsed['rewritten']) ? trim((string)$parsed['rewritten']) : null;
    $reason = trim((string)($parsed['reason'] ?? ''));
    $checks = (array)($parsed['checks'] ?? array());

    // ── Guard determinista anti-agresividad ──
    // Si el mensaje presiona el cierre fuera de fase CIERRE, forzar reescritura
    // aunque DeepSeek lo hubiera aprobado.
    $aggressive = comercial_agent_critic_detect_aggressive_close($text, $phase);
    if ($aggressive) {
        $score = min($score, 40);
        $checks['no_aggressive_close_ok'] = false;
        $reason = ($reason !== '' ? $reason . ' · ' : '') . 'cierre_agresivo_detectado';
    }

    // Si pasa (>89), devolver el original
    if ($score >= 89 && ($rewritten === null || $rewritten === '')) {
        return array(
            'score' => $score,
            'text' => $text,
            'rewritten' => null,
            'reason' => $reason !== '' ? $reason : 'passed',
            'checks' => $checks,
        );
    }

    // Si DeepSeek reescribió, usar su versión
    if ($rewritten !== null && $rewritten !== '') {
        // El rewrite de DeepSeek también debe pasar por el guard anti-agresividad
        if (comercial_agent_critic_detect_aggressive_close($rewritten, $phase)) {
            $rewritten = comercial_soften_aggressive_close($rewritten, $phase);
        }
        return array(
            'score' => $score,
            'text' => $rewritten,
            'rewritten' => $rewritten,
            'reason' => $reason !== '' ? $reason : 'auto_rewritten',
            'checks' => $checks,
        );
    }

    // Score bajo y sin reescritura — si había agresividad, aplicar guard determinista
    if ($aggressive && function_exists('comercial_soften_aggressive_close')) {
        $softened = comercial_soften_aggressive_close($text, $phase);
        if ($softened !== $text && trim($softened) !== '') {
            return array(
                'score' => $score,
                'text' => $softened,
                'rewritten' => $softened,
                'reason' => $reason !== '' ? $reason : 'aggressive_close_softened',
                'checks' => $checks,
            );
        }
    }

    // Score bajo y sin reescritura — devolver original con warning
    return array(
        'score' => $score,
        'text' => $text,
        'rewritten' => null,
        'reason' => $reason !== '' ? $reason : 'failed_no_rewrite',
        'checks' => $checks,
    );
}

/**
 * Respuesta predefinida de emergencia por fase.
 * Se usa cuando tanto el generator como el crítico fallan.
 */
function comercial_agent_critic_fallback(string $slug, string $phase): string {
    if (!function_exists('comercial_knowledge_v2_get')) {
        // Fallback genérico si KB v2 no existe
        $generics = array(
            'SALUDO_INICIAL'    => 'Hola, ¿te cuento?',
            'DESCUBRIMIENTO'    => 'Cuéntame un poco más, ¿qué es lo que más te interesa?',
            'PRESENTACION'      => '¿Te interesa que te explique más?',
            'MANEJO_OBJECIONES' => 'Entiendo. ¿Hay algo más que te gustaría saber?',
            'CIERRE'            => 'Perfecto, te paso con mi compañera que te lo gestiona. Un placer 😊',
            'DESCARTADO'        => 'De acuerdo, gracias por tu tiempo.',
        );
        return $generics[$phase] ?? 'Dime, ¿en qué puedo ayudarte?';
    }

    $kb = comercial_knowledge_v2_get($slug, $phase);

    switch ($phase) {
        case 'SALUDO_INICIAL':
            $openers = $kb['openers'] ?? array();
            return !empty($openers) ? $openers[array_rand($openers)] : 'Hola, ¿te cuento?';

        case 'DESCUBRIMIENTO':
            $questions = $kb['qualifying_questions'] ?? array();
            if (!empty($questions)) {
                return $questions[array_rand($questions)];
            }
            return 'Cuéntame un poco más, ¿qué es lo que más te interesa?';

        case 'PRESENTACION':
            $pricing = $kb['pricing'] ?? '';
            $nextSteps = $kb['next_steps'] ?? array();
            $next = !empty($nextSteps) ? $nextSteps[array_rand($nextSteps)] : '¿Te interesa?';
            return trim(trim($pricing) . ' ' . $next);

        case 'MANEJO_OBJECIONES':
            // Intentar devolver cualquiera de las objeciones predefinidas
            $objections = array_filter($kb, function($v, $k) {
                return is_string($v) && $v !== '' && !in_array($k, array('product_line', 'tone', 'pitch', 'pricing', 'features', 'hook', 'escalation'));
            }, ARRAY_FILTER_USE_BOTH);
            if (!empty($objections)) {
                $vals = array_values($objections);
                return $vals[array_rand($vals)];
            }
            return 'Entiendo. ¿Hay algo más que te gustaría saber?';

        case 'CIERRE':
            return $kb['escalation'] ?? 'Perfecto, te paso con mi compañera que te lo gestiona. Un placer 😊';

        default:
            return 'Dime, ¿en qué puedo ayudarte?';
    }
}
