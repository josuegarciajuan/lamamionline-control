<?php

declare(strict_types=1);

/**
 * Conversation Outcome Classifier — Cron Job
 *
 * Scans session_memory.ndjson and leads.ndjson, groups messages by thread_id,
 * and classifies each closed conversation into an outcome category.
 *
 * Runs every 30 minutes via cron. Only classifies conversations that have been
 * inactive for at least 3 hours (to avoid classifying ongoing conversations).
 *
 * Usage: php /root/lamamionline-control/bot-casa/cron/classify_outcomes.php
 */

// ── Bootstrap ────────────────────────────────────────────────────────────────
$phpBotRoot = dirname(__DIR__);
require_once $phpBotRoot . '/src/Core/ConfigInterface.php';
require_once $phpBotRoot . '/src/Core/Config.php';
$config = new \WasapBot\Core\Config($phpBotRoot);

/**
 * Resolve a config key with optional default.
 */
function _cfg(string $key, mixed $default = null): mixed
{
    global $config;
    return $config->get($key, $default);
}

/**
 * Resolve a file path from config, resolving relative paths against project root.
 */
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

// ── Config ───────────────────────────────────────────────────────────────────
$sessionMemoryFile = _cfg_path('files.session_memory', 'public/data/session_memory.ndjson');
$leadsFile         = _cfg_path('files.leads', 'public/data/leads.ndjson');
$outcomesFile      = $phpBotRoot . '/' . (_cfg('files.conversation_outcomes', 'public/data/conversation_outcomes.ndjson'));
$inactivityHours   = (int) _cfg('cron.classify.inactivity_hours', 3);
$mareadorMinMsgs   = (int) _cfg('cron.classify.mareador_min_messages', 15);
$deadMaxMsgs       = (int) _cfg('cron.classify.dead_max_messages', 5);

// Ensure output directory exists
$outcomesDir = dirname($outcomesFile);
if (!is_dir($outcomesDir)) {
    mkdir($outcomesDir, 0755, true);
}

// ── Load existing outcomes (so we don't reclassify) ──────────────────────────
$existingOutcomes = [];
if (file_exists($outcomesFile)) {
    $lines = file($outcomesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $rec = json_decode(trim($line), true);
            if (is_array($rec) && !empty($rec['thread_id'])) {
                $existingOutcomes[(string) $rec['thread_id']] = $rec;
            }
        }
    }
}

// ── Load leads ───────────────────────────────────────────────────────────────
$leadsByThread = [];
if (file_exists($leadsFile)) {
    $lines = file($leadsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            $rec = json_decode(trim($line), true);
            if (is_array($rec) && !empty($rec['thread_id'])) {
                $tid = (string) $rec['thread_id'];
                if (!isset($leadsByThread[$tid])) {
                    $leadsByThread[$tid] = $rec;
                }
            }
        }
    }
}

// ── Load session memory and group by thread ──────────────────────────────────
$threads = [];
if (!file_exists($sessionMemoryFile)) {
    echo "session_memory.ndjson not found at: {$sessionMemoryFile}\n";
    exit(1);
}

$lines = file($sessionMemoryFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if ($lines === false) {
    echo "Cannot read: {$sessionMemoryFile}\n";
    exit(1);
}

foreach ($lines as $line) {
    $rec = json_decode(trim($line), true);
    if (!is_array($rec) || empty($rec['thread_id'])) {
        continue;
    }
    $tid = (string) $rec['thread_id'];
    if (!isset($threads[$tid])) {
        $threads[$tid] = ['messages' => [], 'phone' => '', 'tags' => []];
    }
    $threads[$tid]['messages'][] = $rec;

    // Track phone
    $phone = (string) ($rec['phone'] ?? '');
    if ($phone !== '' && $threads[$tid]['phone'] === '') {
        $threads[$tid]['phone'] = $phone;
    }

    // Track human (manual) replies for style learning
    if (!empty($rec['manual']) && !empty(trim((string) ($rec['bot_reply'] ?? '')))) {
        $threads[$tid]['human_reply_count'] = ($threads[$tid]['human_reply_count'] ?? 0) + 1;
    }

    // Detect patterns for tagging
    $userMsg = mb_strtolower(trim((string) ($rec['user_msg'] ?? '')), 'UTF-8');

    // Pattern: asks for location/map
    if (
        preg_match('/(donde|ubicacion|direccion|mandame.*mapa|gps|maps|location)/iu', $userMsg)
        && empty($rec['selected_girl_name'])
    ) {
        $threads[$tid]['tags']['pide_mapa_sin_chica'] = ($threads[$tid]['tags']['pide_mapa_sin_chica'] ?? 0) + 1;
    }

    // Pattern: asks for phone / personal number
    if (preg_match('/(tu.*telefono|tu.*numero|whatsapp.*personal|hablame.*telefono)/iu', $userMsg)) {
        $threads[$tid]['tags']['pide_telefono_personal'] = true;
    }

    // Pattern: aggressive / hostile
    if (preg_match('/(puta|zorra|mierda|cabron|hijueputa|pendej|estaf)/iu', $userMsg)) {
        $threads[$tid]['tags']['hostil'] = true;
    }

    // Pattern: wants free / no payment
    if (preg_match('/(no.*pago|gratis|sin.*pagar|no.*cobro|invita)/iu', $userMsg)) {
        $threads[$tid]['tags']['no_quiere_pagar'] = true;
    }
}

// ── Classify each thread ─────────────────────────────────────────────────────
$now = time();
$newOutcomes = 0;

// ── Loop: classify each thread ───────────────────────────────────────────────
$newlyClassified = [];
foreach ($threads as $tid => $data) {
    // Skip already classified
    if (isset($existingOutcomes[$tid])) {
        continue;
    }

    $msgs       = $data['messages'];
    $msgCount   = count($msgs);
    $phone      = $data['phone'];
    $tags       = $data['tags'];

    // Find first and last timestamps
    $firstTs = PHP_INT_MAX;
    $lastTs  = 0;
    foreach ($msgs as $m) {
        $ts = strtotime((string) ($m['ts'] ?? ''));
        if ($ts !== false) {
            if ($ts < $firstTs) $firstTs = $ts;
            if ($ts > $lastTs) $lastTs = $ts;
        }
    }

    // Skip if conversation is still active (< inactivityHours since last message)
    if (($now - $lastTs) < $inactivityHours * 3600) {
        continue;
    }

    // Check if this thread was detected as a lead
    $hasLead  = isset($leadsByThread[$tid]);
    $leadData = $leadsByThread[$tid] ?? null;

    // Check for consecutive empty bot_reply (bot gave up)
    $botEmptyCount = 0;
    $emptyReplies  = 0;
    for ($i = count($msgs) - 1; $i >= 0; $i--) {
        $br = trim((string) ($msgs[$i]['bot_reply'] ?? ''));
        if ($br === '') {
            $emptyReplies++;
            if ($emptyReplies > $botEmptyCount) $botEmptyCount = $emptyReplies;
        } else {
            $emptyReplies = 0;
        }
    }

    // ── Determine outcome ────────────────────────────────────────────────
    $outcome  = 'desconocido';
    $confidence = 0.5;

    // Priority 1: Lead detected
    if ($hasLead) {
        // Check if the lead's ETA has passed by more than 3h
        $etaMinutes = (int) ($leadData['eta_minutes'] ?? 0);
        $leadTs = strtotime((string) ($leadData['ts'] ?? ''));
        if ($leadTs !== false && $etaMinutes > 0 && ($now - $leadTs) > (($etaMinutes + 180) * 60)) {
            // ETA passed >3h ago and no follow-up activity → ghosted
            $lastActivityAfterLead = $lastTs;
            if ($lastActivityAfterLead <= $leadTs + 3600) { // <1h activity after lead
                $outcome    = 'lead_ghosted';
                $confidence = 0.85;
            } else {
                $outcome    = 'lead_probable';
                $confidence = (float) ($leadData['lead_confidence'] ?? 0.7);
            }
        } else {
            $outcome    = 'lead_probable';
            $confidence = (float) ($leadData['lead_confidence'] ?? 0.7);
        }
    }
    // Priority 2: Hostile / aggressive
    elseif (!empty($tags['hostil'])) {
        $outcome    = 'hostil';
        $confidence = 0.9;
    }
    // Priority 3: Mareador patterns
    elseif (
        $msgCount >= $mareadorMinMsgs
        || (!empty($tags['pide_mapa_sin_chica']) && $tags['pide_mapa_sin_chica'] >= 3)
        || !empty($tags['pide_telefono_personal'])
        || !empty($tags['no_quiere_pagar'])
        || $botEmptyCount >= 2
    ) {
        $outcome    = 'mareador';
        $confidence = ($msgCount >= $mareadorMinMsgs) ? 0.9 : 0.7;
    }
    // Priority 4: Dead / short conversation
    elseif ($msgCount <= $deadMaxMsgs) {
        $outcome    = 'muerta';
        $confidence = 0.8;
    }
    // Priority 5: Default — unknown outcome
    else {
        $outcome    = 'indeterminado';
        $confidence = 0.4;
    }

    // ── Build tags array ─────────────────────────────────────────────────
    $tagLabels = [];
    foreach ($tags as $key => $val) {
        if ($val === true) {
            $tagLabels[] = $key;
        } elseif (is_int($val) && $val > 0) {
            if ($val >= 3) {
                $tagLabels[] = $key; // significant pattern
            }
        }
    }

    // ── Write outcome ─────────────────────────────────────────────────────
    $outcomeRec = [
        'thread_id'        => $tid,
        'phone'            => $phone,
        'started'          => $firstTs < PHP_INT_MAX ? date('c', $firstTs) : null,
        'last_activity'    => $lastTs > 0 ? date('c', $lastTs) : null,
        'message_count'    => $msgCount,
        'human_reply_count' => (int) ($data['human_reply_count'] ?? 0),
        'has_human_replies' => ($data['human_reply_count'] ?? 0) > 0,
        'selected_girl'    => extractLastSelectedGirl($msgs),
        'outcome'          => $outcome,
        'confidence'       => round($confidence, 2),
        'classified_by'    => 'auto',
        'classified_at'    => date('c', $now),
        'human_confirmed'  => false,
        'tags'             => $tagLabels,
        'bot_empty_replies'=> $botEmptyCount,
    ];

    $jsonLine = json_encode($outcomeRec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    file_put_contents($outcomesFile, $jsonLine, FILE_APPEND | LOCK_EX);
    $newlyClassified[$tid] = $outcomeRec;
    $newOutcomes++;
}

echo date('Y-m-d H:i:s') . " — Threads analyzed: " . count($threads)
   . ", existing outcomes: " . count($existingOutcomes)
   . ", new classifications: {$newOutcomes}\n";

// ── Sync profiles from new outcomes ──────────────────────────────────────────
if (!empty($newlyClassified)) {
    require_once $phpBotRoot . '/src/Services/ClientProfileService.php';
    $profileSvc = new \WasapBot\Services\ClientProfileService($config);
    $profilesUpdated = $profileSvc->syncFromOutcomes($newlyClassified);
    echo "  Profiles synced: {$profilesUpdated}\n";
}

// ── Helper: extract last selected girl from messages ─────────────────────────
function extractLastSelectedGirl(array $msgs): string
{
    for ($i = count($msgs) - 1; $i >= 0; $i--) {
        $name = (string) ($msgs[$i]['selected_girl_name'] ?? '');
        if ($name !== '') {
            return $name;
        }
    }
    return '';
}

// Fix: the closure above was using $msgs which isn't in scope inside the loop.
// Re-declare as a standalone function below.
