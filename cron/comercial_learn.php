<?php
/**
 * cron/comercial_learn.php — Motor de aprendizaje del bot comercial.
 *
 * Analiza las conversaciones clasificadas de cada proceso y genera un playbook
 * por proceso (data/comercial_playbooks/{slug}.md) usando DeepSeek.
 *
 * El playbook se inyecta luego en el system prompt del agente (comercial_agent.php).
 *
 * Uso:
 *   php cron/comercial_learn.php
 *   php cron/comercial_learn.php --days=14
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once APP_PATH . '/comercial_learning.php';
require_once APP_PATH . '/comercial_agent_critic.php'; // resolver de config DeepSeek

set_time_limit(600);

$days = 7;
$onlySlug = '';
foreach ($argv ?? array() as $arg) {
    if (str_starts_with($arg, '--days=')) {
        $days = max(1, (int)substr($arg, 7));
    }
    if (str_starts_with($arg, '--slug=')) {
        $onlySlug = trim(substr($arg, 7));
    }
}

echo "=== Comercial Learning Engine ===\n";
echo "Analyzing last {$days} day(s)\n";

// ── Config DeepSeek (reutiliza el resolver del crítico) ──
$dsCfg = comercial_agent_critic_get_config();
if (empty($dsCfg['enabled']) || empty($dsCfg['api_key'])) {
    echo "ERROR: DeepSeek API key not configured. Aborting.\n";
    exit(1);
}
echo "Model: {$dsCfg['model']}\n\n";

// ── Helpers locales ──────────────────────────────────────────────────────────

/**
 * Reconstruye el texto de conversación de una lista de outcome records.
 * Devuelve muestras [thread_id, outcome, conversation].
 */
function comercial_learn_sample_threads(array $outcomeList, array $threadsById, int $max, int $maxChars): array {
    $samples = array();
    $count = 0;
    foreach ($outcomeList as $o) {
        if ($count >= $max) break;
        $tid = (string)($o['thread_id'] ?? '');
        if ($tid === '' || !isset($threadsById[$tid])) continue;

        $turns = comercial_learning_thread_turns($threadsById[$tid], 3000);
        if (count($turns) < 2) continue; // conversaciones triviales

        // Recortar a presupuesto de caracteres (desde el final)
        $lines = array();
        $charCount = 0;
        $slice = $turns;
        for ($i = count($turns) - 1; $i >= 0; $i--) {
            $line = $turns[$i]['role'] . ': ' . $turns[$i]['text'];
            $charCount += mb_strlen($line) + 2;
            if ($charCount > $maxChars) {
                $slice = array_slice($turns, $i + 1);
                break;
            }
        }
        foreach ($slice as $t) {
            $lines[] = $t['role'] . ': ' . $t['text'];
        }

        $samples[] = array(
            'thread_id'    => $tid,
            'outcome'      => (string)($o['outcome'] ?? 'desconocido'),
            'conversation' => implode("\n", $lines),
        );
        $count++;
    }
    return $samples;
}

/**
 * Devuelve el bloque determinista de "reglas fijas y datos reales" de un proceso,
 * extraído de su base de conocimiento (restricciones + datos de habitaciones).
 * Se inyecta en el meta-prompt Y se antepone al playbook generado, para que la
 * autogeneración nunca reintroduzca comportamientos prohibidos (visitas, inventar
 * comodidades, etc.).
 */
function comercial_learn_fixed_block(string $slug): string {
    if (!function_exists('comercial_knowledge_get')) return '';
    $kb = comercial_knowledge_get($slug);
    $restrictions = $kb['restrictions'] ?? array();
    $roomFacts = $kb['room_facts'] ?? array();
    if (empty($restrictions) && empty($roomFacts)) return '';

    $lines = array();
    $lines[] = "## Reglas fijas y datos reales del negocio (prioridad máxima)";
    if (!empty($restrictions)) {
        $lines[] = "### Reglas obligatorias";
        foreach ($restrictions as $r) $lines[] = "- " . $r;
    }
    if (!empty($roomFacts)) {
        $lines[] = "### Datos reales de las habitaciones";
        foreach ($roomFacts as $f) $lines[] = "- " . $f;
    }
    if ($slug === 'plaza') {
        $lines[] = "- Agresividad según ocupación: si hay plazas libres, empuja a cerrar para copar aforo; si la casa está llena, enseña las fotos igual y di que avisarás en unos días.";
    }
    $lines[] = "";
    $lines[] = "> Tu análisis y los ejemplos de abajo NO deben contradecir estas reglas."
        . ($slug === 'plaza' ? " No sugieras visitas a la casa ni comodidades inventadas: el bot ofrece fotos de las habitaciones." : " No inventes datos que contradigan estas reglas.");
    return implode("\n", $lines) . "\n\n---\n\n";
}

/**
 * Sanea la salida del LLM: para procesos que prohíben visitas, neutraliza las
 * frases de "venir a verla / visitar" sustituyéndolas por ofrecer fotos.
 * Es una red de seguridad además de la instrucción del meta-prompt.
 */
function comercial_learn_sanitize_content(string $slug, string $content): string {
    if (!function_exists('comercial_knowledge_get')) return $content;
    $kb = comercial_knowledge_get($slug);
    $forbidVisits = false;
    foreach ((array)($kb['restrictions'] ?? array()) as $r) {
        if (mb_stripos((string)$r, 'visita') !== false) { $forbidVisits = true; break; }
    }
    if (!$forbidVisits) return $content;

    $map = array(
        'venir a verla'           => 'ver fotos de las habitaciones',
        'venir a verlo'           => 'ver fotos de las habitaciones',
        'ven a verla'             => 'mira las fotos de las habitaciones',
        'ven a verlo'             => 'mira las fotos de las habitaciones',
        'verla sin compromiso'    => 'ver fotos sin compromiso',
        'verla y hablamos'        => 'ver fotos y hablamos',
        'verla esta semana'       => 'ver fotos esta semana',
        'verla mañana o el viernes' => 'ver fotos mañana o el viernes',
        'visitar la casa'         => 'ver fotos de la casa',
        'Cierre por visita'       => 'Cierre por fotos',
        'la visita'               => 'las fotos',
        'una visita'              => 'ver fotos',
    );
    $content = str_ireplace(array_keys($map), array_values($map), $content);
    $content = preg_replace('/\bvisitas?\b/iu', 'fotos', (string)$content);
    $content = preg_replace('/\bvisitar\b/iu', 'ver fotos de', (string)$content);

    return (string)$content;
}

/**
 * Red de seguridad anti-agresividad para el playbook generado:
 * neutraliza los cierres con presión que el LLM pudiera recomendar a pesar
 * de las instrucciones del meta-prompt. Complementa comercial_learn_meta_prompt.
 */
function comercial_learn_sanitize_aggression(string $content): string {
    $content = (string)$content;

    // Preguntas/afirmaciones de cierre con presión → recomendación de cierre suave
    $map = array(
        '¿Te activo ya?'                             => '¿Quieres que te explique algo más?',
        '¿Te activo hoy mismo?'                      => '¿Quieres que te explique algo más?',
        '¿Te activo ahora mismo?'                    => 'Si te convence, me dices',
        '¿Te activo la demo ahora mismo?'            => '¿Quieres probar la demo cuando te venga bien?',
        '¿Te activo la prueba?'                      => '¿Quieres que te explique cómo sería la prueba?',
        '¿Te activo la prueba de 10 días?'           => '¿Te gustaría probarlo 10 días sin compromiso?',
        '¿Empezamos?'                                => '¿Quieres que te cuente algo más?',
        '¿Empezamos ya?'                             => 'Si te convence, me dices',
        'te lo dejo funcionando hoy'                 => 'te explico cómo funciona cuando quieras',
        'te lo dejo activado y listo'                => 'te lo dejo preparado para cuando decidas',
        'te lo dejo activado'                        => 'te lo dejo preparado para cuando decidas',
        'hoy mismo empieza a funcionar'              => 'cuando quieras, lo dejamos listo',
        'cierre de activación'                       => 'cierre suave sin presión',
        'Cierra siempre con una microacción clara. Usa frases tipo: "Responde INFO y te lo dejo funcionando hoy" o "¿Te activo la demo ahora mismo?".' => 'Cierra siempre con una microacción clara y SIN presión: "¿Quieres que te lo explique algo más?" o "si te convence, me dices". Evita "¿Te activo ya?" y urgencia fabricada.',
    );
    foreach ($map as $from => $to) {
        $content = str_ireplace($from, $to, $content);
    }

    // "hoy mismo" / "ya" con urgencia en contexto de activación → "cuando quieras"
    if (preg_match('/\bhoy\s+mismo\b/iu', $content)) {
        $content = preg_replace('/\bhoy\s+mismo\b/iu', 'cuando quieras', $content);
    }
    $content = preg_replace('/\bactiva(?:r|mos)?\s+(?:el\s+)?servicio\s+ya\b/iu', 'activar el servicio cuando quieras', $content);

    return trim($content);
}

/**
 * Construye el meta-prompt de análisis para un proceso.
 */
function comercial_learn_meta_prompt(string $slug, string $fixedBlock = ''): string {
    $nombre = ucfirst($slug);
    $meta = <<<META
Eres un analista de conversaciones de WhatsApp para un negocio comercial ("{$nombre}").
Tu trabajo es ANALIZAR conversaciones REALES y extraer PATRONES que ayuden al bot
comercial a vender mejor: conseguir más leads, manejar objeciones y cerrar ventas.

RECIBES:
- Conversaciones que acabaron en LEAD (el cliente mostró intención real de compra)
- Conversaciones donde el cliente GHOSTEÓ (mostró interés y desapareció)
- Conversaciones que fueron MAREO (largas, sin resultado, el cliente solo daba vueltas)
- Respuestas del OPERADOR HUMANO (escritas por una persona real)

TU ANÁLISIS DEBE RESPONDER:

1. PATRONES DE ÉXITO (LEADS):
   ¿Qué hicieron bien estas conversaciones? ¿Qué frases o estrategias concretas funcionaron
   para avanzar hacia el cierre?

2. PATRONES DE MAREO:
   ¿Qué secuencias predicen que alguien nunca va a comprar? ¿En qué momento debería el bot
   cambiar de estrategia o abandonar en lugar de seguir conversando?

3. SEÑALES TEMPRANAS DE GHOSTEO:
   ¿Qué diferencia a los que ghostean de los que compran? ¿Se podría haber detectado antes?

4. MANEJO DE OBJECIONES:
   ¿Qué objeciones aparecen (precio, ubicación, desconfianza, "ya tengo", etc.) y cómo se
   resolvieron con éxito? Da frases concretas que funcionaron.

5. TÉCNICAS DE CIERRE:
   ¿Qué frases o enfoques llevaron a una cita/lead confirmado? ¿Qué NO hacer en el cierre?
   IMPORTANTE: prioriza CIERRES SUAVES, sin presión. NUNCA recomiendes frases tipo
   "¿Te activo ya?", "¿Te activo hoy mismo?", "¿Empezamos?", "te lo dejo funcionando hoy",
   urgencia fabricada ("hoy mismo", "última oportunidad" falsa) ni pedir activar/empezar
    cuando el cliente solo pidió información. En ese caso, el bot debe dar la información y
    terminar en el dato útil, una opción concreta o el siguiente paso natural. NUNCA recomiendes
    "quieres que te explique algo más", "te ayudo en algo más" ni equivalentes, ni uses ¿ o ¡.

6. ANÁLISIS DE ESTILO HUMANO:
   En las respuestas del operador humano, analiza:
   - Frases típicas, coletillas y muletillas
   - Tono general (formal/informal, cercano/directo, cálido/serio)
   - Longitud media de los mensajes
   - Uso de emojis (cuáles, cuántos, en qué contexto)
   - Cómo maneja objeciones y preguntas de precio
   - Qué diría el humano en situaciones donde el bot responde mal

FORMATO DE SALIDA:
Escribe MARKDOWN con los siguientes apartados (sin bloque de código, solo texto):

## Patrones de éxito (leads)
[análisis]

## Patrones de mareo detectados
[análisis]

## Señales tempranas de ghosteo
[análisis]

## Manejo de objeciones
[análisis con frases concretas]

## Técnicas de cierre
[análisis con frases concretas]

## Guía de estilo del humano
[frases literales, coletillas, emojis típicos y tono a imitar]

## Nuevos principios para el bot
[3-6 principios concretos en prosa natural]

IMPORTANTE:
- Céntrate en patrones REALES de los datos, no en teorías.
- Da ejemplos concretos: "el cliente dijo X y se respondió Y...".
- Si no ves un patrón claro, dilo honestamente.
- No repitas información genérica; sé específico del negocio "{$nombre}".
- NUNCA recomiendes presión o urgencia fabricada ("¿Te activo ya?", "hoy mismo", "última oportunidad" falsa). El tono del bot debe ser cercano y SIN presión.
META;

    if ($fixedBlock !== '') {
        $meta .= "\n\nREGLAS FIJAS DEL NEGOCIO (respétalas SIEMPRE; NO las contradigas en tu análisis ni en los ejemplos):\n" . $fixedBlock;
    }
    return $meta;
}

/**
 * Llama a DeepSeek con el prompt y devuelve el contenido, o null si falla.
 */
function comercial_learn_call_deepseek(array $dsCfg, string $fullPrompt): ?string {
    $body = json_encode(array(
        'model' => $dsCfg['model'],
        'messages' => array(
            array('role' => 'user', 'content' => $fullPrompt),
        ),
        'temperature' => 0.7,
        'max_tokens' => 16384,
        'thinking' => array('type' => 'enabled'),
        'reasoning_effort' => 'high',
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $ch = curl_init(rtrim($dsCfg['api_url'], '/') . '/chat/completions');
        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $dsCfg['api_key'],
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

        if ($curlError !== '') {
            echo "  cURL failed (attempt {$attempt}): {$curlError}\n";
            continue;
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            echo "  HTTP {$httpCode} (attempt {$attempt}): " . mb_substr((string)$raw, 0, 300) . "\n";
            continue;
        }

        $resp = json_decode((string)$raw, true);
        if (!is_array($resp)) continue;
        if (!empty($resp['error'])) {
            $errMsg = is_array($resp['error']) ? ($resp['error']['message'] ?? 'unknown') : (string)$resp['error'];
            echo "  DeepSeek error (attempt {$attempt}): {$errMsg}\n";
            continue;
        }

        // Preferir SIEMPRE 'content' (respuesta final). NUNCA usar 'reasoning_content'
        // (razonamiento interno en inglés, no sirve como playbook).
        $content = $resp['choices'][0]['message']['content'] ?? null;
        if ($content !== null && trim((string)$content) !== '') {
            return (string)$content;
        }

        $hasReasoning = !empty($resp['choices'][0]['message']['reasoning_content']);
        echo "  Empty content (attempt {$attempt})." . ($hasReasoning ? " reasoning_content present but discarded." : " No content.") . "\n";
    }

    return null;
}

// ── Preparación de datos ─────────────────────────────────────────────────────
$outcomes = comercial_learning_load_outcomes($days);

$threadsById = array();
foreach (comercial_get_threads() as $t) {
    $tid = (string)($t['id'] ?? '');
    if ($tid !== '') $threadsById[$tid] = $t;
}

// Procesos activos + inbound
$processSlugs = array();
foreach (comercial_get_processes() as $p) {
    if (!empty($p['enabled'])) {
        $processSlugs[] = trim((string)($p['slug'] ?? ''));
    }
}
if (!in_array('inbound', $processSlugs, true)) {
    $processSlugs[] = 'inbound';
}

// Filtro opcional para testing/manual: --slug=plaza
if ($onlySlug !== '') {
    $processSlugs = array_values(array_filter($processSlugs, function ($s) use ($onlySlug) {
        return $s === $onlySlug;
    }));
    if (empty($processSlugs)) {
        echo "ERROR: process slug '{$onlySlug}' not found (available: " . implode(', ', array_merge(array('inbound'), array_map(function ($p) { return trim((string)($p['slug'] ?? '')); }, comercial_get_processes()))) . ")\n";
        exit(1);
    }
}

$playbooksDir = DATA_PATH . '/comercial_playbooks';
if (!is_dir($playbooksDir)) {
    @mkdir($playbooksDir, 0775, true);
}

$maxSamplesPerCategory = 3;
$maxCharsPerConversation = 1800;
$maxHumanSamples = 4;

$totalPlaybooks = 0;

foreach ($processSlugs as $slug) {
    if ($slug === '') continue;
    echo "── Proceso: {$slug} ──\n";

    $procOutcomes = array();
    foreach ($outcomes as $rec) {
        if ((string)($rec['process_slug'] ?? '') === $slug) {
            $procOutcomes[] = $rec;
        }
    }
    if (empty($procOutcomes)) {
        echo "  No classified conversations. Skipping.\n\n";
        continue;
    }

    $byOutcome = array();
    foreach ($procOutcomes as $o) {
        $byOutcome[(string)($o['outcome'] ?? 'desconocido')][] = $o;
    }

    // lead_ghosted también es un lead detectado (mostró interés real) — se usa como éxito
    $leadSamples = comercial_learn_sample_threads(array_merge($byOutcome['lead_probable'] ?? array(), $byOutcome['lead_ghosted'] ?? array()), $threadsById, $maxSamplesPerCategory, $maxCharsPerConversation);
    $ghostSamples = comercial_learn_sample_threads($byOutcome['lead_ghosted'] ?? array(), $threadsById, $maxSamplesPerCategory, $maxCharsPerConversation);
    $mareadorSamples = comercial_learn_sample_threads($byOutcome['mareador'] ?? array(), $threadsById, $maxSamplesPerCategory, $maxCharsPerConversation);
    $hostilSamples = comercial_learn_sample_threads($byOutcome['hostil'] ?? array(), $threadsById, 1, $maxCharsPerConversation);

    $humanReplies = comercial_learning_human_replies($slug);
    $humanSamples = array_slice($humanReplies, 0, $maxHumanSamples);

    echo "  lead=" . count($leadSamples) . " ghost=" . count($ghostSamples)
       . " mareador=" . count($mareadorSamples) . " human_replies=" . count($humanReplies) . "\n";

    if (empty($leadSamples) && empty($ghostSamples) && empty($mareadorSamples) && empty($hostilSamples) && empty($humanSamples)) {
        echo "  No samples. Skipping.\n\n";
        continue;
    }

    // ── Construir prompt ──
    $fixedBlock = comercial_learn_fixed_block($slug);
    $promptParts = array();
    $promptParts[] = comercial_learn_meta_prompt($slug, $fixedBlock);

    if (!empty($leadSamples)) {
        $promptParts[] = "\n\n### CONVERSACIONES QUE ACABARON EN LEAD (" . count($leadSamples) . " ejemplos)";
        foreach ($leadSamples as $i => $s) {
            $promptParts[] = "--- LEAD #" . ($i + 1) . " ---\n" . $s['conversation'];
        }
    }
    if (!empty($ghostSamples)) {
        $promptParts[] = "\n\n### CONVERSACIONES DONDE EL CLIENTE GHOSTEÓ (" . count($ghostSamples) . " ejemplos)";
        foreach ($ghostSamples as $i => $s) {
            $promptParts[] = "--- GHOST #" . ($i + 1) . " ---\n" . $s['conversation'];
        }
    }
    if (!empty($mareadorSamples)) {
        $promptParts[] = "\n\n### CONVERSACIONES DE MAREO (" . count($mareadorSamples) . " ejemplos)";
        foreach ($mareadorSamples as $i => $s) {
            $promptParts[] = "--- MAREO #" . ($i + 1) . " ---\n" . $s['conversation'];
        }
    }
    if (!empty($hostilSamples)) {
        $promptParts[] = "\n\n### CONVERSACIONES HOSTILES (" . count($hostilSamples) . " ejemplos)";
        foreach ($hostilSamples as $i => $s) {
            $promptParts[] = "--- HOSTIL #" . ($i + 1) . " ---\n" . $s['conversation'];
        }
    }
    if (!empty($humanSamples)) {
        $promptParts[] = "\n\n### RESPUESTAS DEL OPERADOR HUMANO — analiza su estilo (" . count($humanSamples) . " ejemplos)";
        foreach ($humanSamples as $i => $h) {
            $trigger = trim((string)($h['trigger_text'] ?? ''));
            $promptParts[] = "--- HUMANO #" . ($i + 1) . " ---"
                . ($trigger !== '' ? "\nContexto (mensaje del cliente previo): \"{$trigger}\"" : '')
                . "\nRespuesta del humano: \"" . $h['text'] . "\"";
        }
    }

    $fullPrompt = implode("\n", $promptParts);
    echo "  Prompt size: " . number_format(strlen($fullPrompt)) . " chars\n";

    // ── Llamar a DeepSeek ──
    echo "  Calling DeepSeek...\n";
    $content = comercial_learn_call_deepseek($dsCfg, $fullPrompt);
    if ($content === null) {
        echo "  Skipping playbook for {$slug}.\n\n";
        continue;
    }

    // ── Escribir playbook ──
    $header = "# Playbook comercial — {$slug}\n\n"
        . "> Generado automáticamente mediante análisis de conversaciones reales.\n"
        . "> Última actualización: " . date('Y-m-d H:i:s T') . "\n"
        . "> Motor: {$dsCfg['model']}\n\n---\n\n";
    $content = comercial_learn_sanitize_content($slug, $content);
    $content = comercial_learn_sanitize_aggression($content);

    $file = $playbooksDir . '/' . $slug . '.md';
    $bytes = @file_put_contents($file, $header . $fixedBlock . $content, LOCK_EX);
    if ($bytes === false) {
        echo "  ERROR writing playbook: {$file}\n\n";
        continue;
    }
    echo "  ✅ Playbook written: {$file} ({$bytes} bytes)\n\n";
    $totalPlaybooks++;
}

echo "Done. Playbooks generated: {$totalPlaybooks}\n";
