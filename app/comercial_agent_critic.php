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

Mensaje a evaluar:
"{$text}"

Devuelve SOLO este JSON (sin ``` ni backticks):
{"score":0-100,"checks":{"line_count_ok":bool,"single_topic_ok":bool,"no_bot_tells_ok":bool,"no_disclosure_ok":bool,"natural_tone_ok":bool,"emoji_ok":bool,"no_premature_info_ok":bool,"question_end_ok":bool,"answers_question_ok":bool},"rewritten":"texto corregido manteniendo la misma info o null","reason":"breve explicación en español"}
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

    // Si pasa (>89), devolver el original
    if ($score >= 89 && ($rewritten === null || $rewritten === '')) {
        return array(
            'score' => $score,
            'text' => $text,
            'rewritten' => null,
            'reason' => $reason !== '' ? $reason : 'passed',
            'checks' => $parsed['checks'] ?? array(),
        );
    }

    // Si DeepSeek reescribió, usar su versión
    if ($rewritten !== null && $rewritten !== '') {
        return array(
            'score' => $score,
            'text' => $rewritten,
            'rewritten' => $rewritten,
            'reason' => $reason !== '' ? $reason : 'auto_rewritten',
            'checks' => $parsed['checks'] ?? array(),
        );
    }

    // Score bajo y sin reescritura — devolver original con warning
    return array(
        'score' => $score,
        'text' => $text,
        'rewritten' => null,
        'reason' => $reason !== '' ? $reason : 'failed_no_rewrite',
        'checks' => $parsed['checks'] ?? array(),
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
