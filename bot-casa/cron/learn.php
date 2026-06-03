<?php

declare(strict_types=1);

// Allow long execution for DeepSeek API calls
set_time_limit(300);

/**
 * Bot Learning Engine — Cron Job
 *
 * Analyzes classified conversations using an LLM (DeepSeek by default) to extract
 * behavioral patterns and generates a living playbook (playbook.md) that the bot
 * injects into its system prompt.
 *
 * Runs daily via cron. Can also be triggered on-demand from the panel.
 *
 * Usage:
 *   php bot-casa/cron/learn.php                # daily run (analyzes last 7 days)
 *   php bot-casa/cron/learn.php --days=14      # analyze last 14 days
 *   php bot-casa/cron/learn.php --days=1       # analyze last 24h
 *
 * Stdout is human-readable progress; the actual playbook is written to disk.
 */

// ── Bootstrap ────────────────────────────────────────────────────────────────
$phpBotRoot = dirname(__DIR__);
require_once $phpBotRoot . '/src/Core/ConfigInterface.php';
require_once $phpBotRoot . '/src/Core/Config.php';
$config = new \WasapBot\Core\Config($phpBotRoot);

function _cfg(string $key, mixed $default = null): mixed
{
    global $config;
    return $config->get($key, $default);
}

function _cfg_path(string $key, string $default = ''): string
{
    $path = _cfg($key, $default);
    if ($path === '' || str_starts_with($path, '/')) {
        return $path;
    }
    global $phpBotRoot;
    $resolved = realpath($phpBotRoot);
    if ($resolved === false) {
        throw new \RuntimeException("Cannot resolve phpBotRoot: {$phpBotRoot}");
    }
    return $resolved . '/' . ltrim($path, '/');
}

// ── CLI args ──────────────────────────────────────────────────────────────────
$days = 7;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--days=')) {
        $days = max(1, (int) substr($arg, 7));
    }
}

echo "=== Bot Learning Engine ===\n";
echo "Analyzing conversations from the last {$days} day(s)\n";
echo "Model: " . _cfg('deepseek.chat_model', 'deepseek-v4-pro') . "\n\n";

// ── Paths ─────────────────────────────────────────────────────────────────────
$sessionMemoryFile = _cfg_path('files.session_memory', 'public/data/session_memory.ndjson');
$outcomesFile      = $phpBotRoot . '/' . (_cfg('files.conversation_outcomes', 'public/data/conversation_outcomes.ndjson'));
$playbookFile      = $phpBotRoot . '/' . (_cfg('files.playbook', 'public/data/playbook.md'));
$playbookDir       = dirname($playbookFile);

if (!is_dir($playbookDir)) {
    mkdir($playbookDir, 0755, true);
}

// ── Load conversation outcomes ───────────────────────────────────────────────
echo "Loading conversation outcomes...\n";

$outcomes = [];
if (file_exists($outcomesFile)) {
    $lines = file($outcomesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $rec = json_decode(trim($line), true);
            if (is_array($rec) && !empty($rec['thread_id'])) {
                $classifiedAt = strtotime((string) ($rec['classified_at'] ?? ''));
                if ($classifiedAt !== false && $classifiedAt >= (time() - $days * 86400)) {
                    $outcomes[] = $rec;
                }
            }
        }
    }
}

if (empty($outcomes)) {
    echo "No classified conversations found in the last {$days} days. Run classify_outcomes.php first.\n";
    exit(0);
}

echo "  Found " . count($outcomes) . " classified conversations\n";

// Group by outcome
$byOutcome = [];
foreach ($outcomes as $o) {
    $type = (string) ($o['outcome'] ?? 'desconocido');
    $byOutcome[$type][] = $o;
}

foreach ($byOutcome as $type => $list) {
    echo "    {$type}: " . count($list) . "\n";
}

// ── Load actual conversations from session memory ────────────────────────────
echo "\nLoading conversation samples...\n";

$threads = [];
if (file_exists($sessionMemoryFile)) {
    $lines = file($sessionMemoryFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $rec = json_decode(trim($line), true);
            if (is_array($rec) && !empty($rec['thread_id'])) {
                $tid = (string) $rec['thread_id'];
                $threads[$tid][] = $rec;
            }
        }
    }
}

// ── Build sample sets for the LLM ────────────────────────────────────────────
$maxSamplesPerCategory = (int) _cfg('cron.learn.max_samples_per_category', 4);
$maxCharsPerConversation = (int) _cfg('cron.learn.max_chars_per_conversation', 1800);

function sampleConversations(array $outcomeList, array $allThreads, int $max, int $maxChars = 2500): array
{
    $samples = [];
    $count = 0;
    foreach ($outcomeList as $o) {
        if ($count >= $max) break;
        $tid = (string) ($o['thread_id'] ?? '');
        if (!isset($allThreads[$tid])) continue;

        $msgs = $allThreads[$tid];
        if (count($msgs) < 2) continue; // skip trivial conversations

        $convo = [];
        $charCount = 0;
        // Only include last N messages to stay under char limit
        $sliceMsgs = $msgs;
        for ($i = count($msgs) - 1; $i >= 0; $i--) {
            $um = trim((string) ($msgs[$i]['user_msg'] ?? ''));
            $br = trim((string) ($msgs[$i]['bot_reply'] ?? ''));
            $charCount += mb_strlen($um) + mb_strlen($br) + 20; // 20 for labels
            if ($charCount > $maxChars) {
                $sliceMsgs = array_slice($msgs, $i + 1);
                break;
            }
        }

        $convoLines = [];
        foreach ($sliceMsgs as $m) {
            $um = trim((string) ($m['user_msg'] ?? ''));
            $br = trim((string) ($m['bot_reply'] ?? ''));
            if ($um !== '') $convoLines[] = "Cliente: {$um}";
            if ($br !== '') $convoLines[] = "Bot: {$br}";
        }

        $samples[] = [
            'thread_id' => $tid,
            'outcome'   => $o['outcome'],
            'messages'  => count($sliceMsgs) . '/' . count($msgs),
            'conversation' => implode("\n", $convoLines),
        ];
        $count++;
    }
    return $samples;
}

$leadSamples    = sampleConversations(
    array_merge(
        $byOutcome['lead_probable'] ?? [],
        $byOutcome['lead_detectado'] ?? [],
        $byOutcome['lead_ghosted'] ?? []  // ghosted = lead that was detected!
    ),
    $threads,
    $maxSamplesPerCategory
);
$ghostSamples   = sampleConversations($byOutcome['lead_ghosted'] ?? [], $threads, $maxSamplesPerCategory);
$mareadorSamples = sampleConversations($byOutcome['mareador'] ?? [], $threads, $maxSamplesPerCategory);

echo "  Lead conversations sampled: " . count($leadSamples) . "\n";
echo "  Ghosted conversations sampled: " . count($ghostSamples) . "\n";
echo "  Mareador conversations sampled: " . count($mareadorSamples) . "\n";

$totalSamples = count($leadSamples) + count($ghostSamples) + count($mareadorSamples);
if ($totalSamples === 0) {
    echo "\nNo conversation samples available for analysis. Exiting.\n";
    exit(0);
}

// ── Load current playbook ────────────────────────────────────────────────────
$currentPlaybook = '';
if (file_exists($playbookFile)) {
    $currentPlaybook = (string) @file_get_contents($playbookFile);
}
echo "\n  Current playbook size: " . number_format(strlen($currentPlaybook)) . " chars\n";

// ── Build meta-prompt ────────────────────────────────────────────────────────
echo "\nBuilding meta-prompt...\n";

$metaPrompt = <<<'META'
Eres un analista de conversaciones de WhatsApp para un negocio de citas para adultos.
Tu trabajo es ANALIZAR conversaciones REALES y extraer PATRONES que ayuden al bot
a mejorar su forma de conversar.

RECIBES:
- Conversaciones que acabaron en LEAD (el cliente dio ETA, probablemente vino)
- Conversaciones donde el cliente GHOSTEÓ (dio ETA y desapareció)
- Conversaciones que fueron MAREO (largas, sin resultado, el cliente solo daba vueltas)
- El PLAYBOOK actual del bot (lo que ya sabe)

TU ANÁLISIS DEBE RESPONDER:

1. PATRONES DE ÉXITO (LEADS):
   ¿Qué hicieron bien estas conversaciones? ¿Qué patrones ves en los primeros 5 mensajes?
   ¿Hay frases o estrategias concretas del bot que funcionaron?

2. PATRONES DE MAREO:
   ¿Qué secuencias de mensajes predicen que alguien nunca va a venir?
   ¿En qué momento debería el bot cambiar de estrategia en lugar de seguir conversando?
   ¿Hay "puntos de no retorno" donde ya está claro que es mareo?

3. PATRONES DE GHOSTEO:
   ¿Qué diferencia a los que ghostean de los que sí vienen?
   ¿Se podría haber detectado antes?

4. MOMENTOS DE INFLEXIÓN:
   En conversaciones que acabaron mal, ¿en qué mensaje concreto se torció todo?
   ¿Qué podría haber dicho el bot en ese momento para redirigir la conversación?

5. INSIGHTS ACCIONABLES:
   Escribe principios conversacionales que el bot debería seguir.
   NO escribas reglas IF/THEN. Escribe sabiduría conversacional en prosa natural,
   con ejemplos concretos de las conversaciones que has analizado.

FORMATO DE SALIDA:
Escribe MARKDOWN con los siguientes apartados (sin bloque de código, solo texto):

## Patrones de éxito (LEADS)
[tu análisis]

## Patrones de mareo detectados
[tu análisis]

## Señales tempranas de ghosteo
[tu análisis]

## Estrategias que funcionaron
[tu análisis con ejemplos concretos]

## Momentos de inflexión
[tu análisis]

## Nuevos principios para el playbook
[principios concretos en prosa natural, 3-6 puntos]

IMPORTANTE:
- Céntrate en patrones REALES de los datos, no en teorías.
- Da ejemplos concretos: "en la conversación X, el cliente dijo Y y el bot respondió Z..."
- Si no ves un patrón claro, dilo honestamente.
- No repitas lo que ya está en el playbook actual.
META;

// ── Build the full prompt with data ──────────────────────────────────────────
$promptParts = [$metaPrompt];

// Current playbook
if ($currentPlaybook !== '') {
    $promptParts[] = "\n\n### PLAYBOOK ACTUAL DEL BOT (lo que ya sabe, no lo repitas)\n\n" . $currentPlaybook;
} else {
    $promptParts[] = "\n\n### PLAYBOOK ACTUAL\n\n(El bot no tiene playbook todavía. Este será el primero.)\n";
}

// Lead samples
if (!empty($leadSamples)) {
    $promptParts[] = "\n\n### CONVERSACIONES QUE ACABARON EN LEAD (" . count($leadSamples) . " ejemplos)\n";
    foreach ($leadSamples as $i => $s) {
        $promptParts[] = "--- LEAD #" . ($i + 1) . " ---\n" . $s['conversation'] . "\n";
    }
}

// Ghosted samples
if (!empty($ghostSamples)) {
    $promptParts[] = "\n\n### CONVERSACIONES DONDE EL CLIENTE GHOSTEÓ (" . count($ghostSamples) . " ejemplos)\n";
    foreach ($ghostSamples as $i => $s) {
        $promptParts[] = "--- GHOST #" . ($i + 1) . " ---\n" . $s['conversation'] . "\n";
    }
}

// Mareador samples
if (!empty($mareadorSamples)) {
    $promptParts[] = "\n\n### CONVERSACIONES DE MAREO (" . count($mareadorSamples) . " ejemplos)\n";
    foreach ($mareadorSamples as $i => $s) {
        $promptParts[] = "--- MAREO #" . ($i + 1) . " ---\n" . $s['conversation'] . "\n";
    }
}

$fullPrompt = implode("\n", $promptParts);

echo "  Meta-prompt size: " . number_format(strlen($fullPrompt)) . " chars\n";

// ── Call DeepSeek API ─────────────────────────────────────────────────────────
echo "\nCalling DeepSeek API for analysis...\n";

$apiKey     = _cfg('deepseek.api_key', '');
$apiUrl     = _cfg('deepseek.chat_url', 'https://api.deepseek.com/v1/chat/completions');
$model      = _cfg('deepseek.chat_model', 'deepseek-v4-pro');
$temperature = 0.7;

$body = json_encode([
    'model'    => $model,
    'messages' => [
        ['role' => 'user', 'content' => $fullPrompt],
    ],
    'temperature' => $temperature,
    'max_tokens'  => 4096,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 180,
    CURLOPT_CONNECTTIMEOUT => 15,
]);

$rawResponse = curl_exec($ch);
$httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError   = curl_error($ch);
curl_close($ch);

if ($curlError !== '') {
    echo "ERROR: cURL failed: {$curlError}\n";
    exit(1);
}

if ($httpCode < 200 || $httpCode >= 300) {
    echo "ERROR: API returned HTTP {$httpCode}\n";
    $bodyPreview = mb_substr((string) $rawResponse, 0, 800);
    echo "Response preview: {$bodyPreview}\n";
    exit(1);
}

// Parse response
$response = json_decode((string) $rawResponse, true);
if (!is_array($response)) {
    echo "ERROR: Invalid JSON response\n";
    $preview = mb_substr((string) $rawResponse, 0, 500);
    echo "Raw: {$preview}\n";
    exit(1);
}

// Check for API-level errors (DeepSeek sometimes returns error in JSON)
if (!empty($response['error'])) {
    $errMsg = is_array($response['error']) ? ($response['error']['message'] ?? 'unknown') : (string) $response['error'];
    echo "ERROR: DeepSeek API error: {$errMsg}\n";
    exit(1);
}

if ($httpCode < 200 || $httpCode >= 300) {
    echo "ERROR: API returned HTTP {$httpCode}\n";
    echo "Response: " . mb_substr((string) $rawResponse, 0, 500) . "\n";
    exit(1);
}

// Parse response
$response = json_decode((string) $rawResponse, true);
if (!is_array($response)) {
    echo "ERROR: Invalid JSON response\n";
    exit(1);
}

$content = $response['choices'][0]['message']['content'] ?? null;
// DeepSeek V4 Pro sometimes returns reasoning_content but empty content.
// Fallback to reasoning_content if main content is empty.
if (($content === null || trim((string) $content) === '') && !empty($response['choices'][0]['message']['reasoning_content'])) {
    $content = $response['choices'][0]['message']['reasoning_content'];
}
if ($content === null || trim((string) $content) === '') {
    echo "ERROR: No content in API response (prompt may be too large)\n";
    echo "Raw: " . mb_substr((string) $rawResponse, 0, 500) . "\n";
    echo "Playbook NOT overwritten — keeping previous version.\n";
    exit(0);
}

echo "  Response received: " . number_format(strlen($content)) . " chars\n";

// ── Build the new playbook ───────────────────────────────────────────────────
$playbookHeader = <<<'HEADER'
# Playbook del Bot — Aprendizajes acumulados

> Este archivo se genera automáticamente mediante análisis de conversaciones reales.
> Última actualización: {timestamp}
> Conversaciones analizadas: {total_analyzed} | Leads: {leads} | Ghosted: {ghosted} | Mareadores: {mareadores}
> Motor de análisis: {model}

---

HEADER;

$playbookHeader = str_replace(
    ['{timestamp}', '{total_analyzed}', '{leads}', '{ghosted}', '{mareadores}', '{model}'],
    [
        date('Y-m-d H:i:s T'),
        (string) count($outcomes),
        (string) (count($byOutcome['lead_probable'] ?? []) + count($byOutcome['lead_detectado'] ?? [])),
        (string) count($byOutcome['lead_ghosted'] ?? []),
        (string) count($byOutcome['mareador'] ?? []),
        $model,
    ],
    $playbookHeader
);

$newPlaybook = $playbookHeader . $content;

// ── Write playbook ────────────────────────────────────────────────────────────
$bytes = @file_put_contents($playbookFile, $newPlaybook, LOCK_EX);
if ($bytes === false) {
    echo "ERROR: Failed to write playbook to: {$playbookFile}\n";
    exit(1);
}

echo "\n✅ Playbook written: {$playbookFile} ({$bytes} bytes)\n";
echo "   The bot will now use these learnings in its system prompt.\n";
echo "\nDone.\n";
