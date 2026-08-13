<?php
/**
 * comercial_learning.php — Clasificación de outcomes y aprendizaje del bot comercial.
 *
 * Proporciona:
 *   - comercial_classify_conversation_outcomes(): clasifica hilos inactivos en outcomes
 *     (lead_probable, lead_ghosted, mareador, muerta, hostil, descartado, indeterminado)
 *     y los escribe en data/comercial_conversation_outcomes.jsonl.
 *   - Helpers de muestreo y extracción de estilo humano para el pipeline learn.
 *
 * Diseñado para ejecutarse desde CLI (cron) con app/bootstrap.php ya cargado.
 */

declare(strict_types=1);

// ── Configuración ────────────────────────────────────────────────────────────
function comercial_learning_inactivity_hours(): int {
    return 3; // horas de inactividad para considerar una conversación cerrada
}

function comercial_learning_mareador_min_messages(): int {
    return 10;
}

function comercial_learning_dead_max_messages(): int {
    return 3;
}

function comercial_learning_hostile_keywords(): array {
    return array('puta', 'zorra', 'mierda', 'cabron', 'hijueputa', 'pendej', 'estaf', 'denunci');
}

function comercial_learning_outcomes_file(): string {
    return DATA_PATH . '/comercial_conversation_outcomes.jsonl';
}

/**
 * Clasifica un hilo comercial en un outcome.
 *
 * @param array $thread  Thread normalizado (comercial_normalize_thread)
 * @param int   $now     Timestamp actual (para tests/CLI)
 * @return array ['outcome'=>string, 'confidence'=>float, 'reason'=>string]
 */
function comercial_classify_thread_outcome(array $thread, int $now = 0): array {
    $thread = comercial_normalize_thread($thread);
    $now = $now > 0 ? $now : time();

    $stage = trim((string)($thread['stage'] ?? ''));
    $replies = (int)($thread['replies_count'] ?? 0);
    $hasLead = trim((string)($thread['lead_id'] ?? '')) !== '';

    // Detección de hostilidad sobre los últimos textos entrantes (barato)
    $hostile = false;
    $hostileKw = comercial_learning_hostile_keywords();
    $checkTexts = array(
        trim((string)($thread['last_inbound_text'] ?? '')),
        trim((string)($thread['prior_inbound_text'] ?? '')),
    );
    foreach ($checkTexts as $txt) {
        if ($txt === '') continue;
        $lower = mb_strtolower($txt, 'UTF-8');
        foreach ($hostileKw as $kw) {
            if (mb_stripos($lower, $kw) !== false) {
                $hostile = true;
                break 2;
            }
        }
    }

    $outcome = 'indeterminado';
    $confidence = 0.4;
    $reason = 'default';

    if ($hostile) {
        $outcome = 'hostil';
        $confidence = 0.9;
        $reason = 'hostile_keywords';
    } elseif ($stage === 'discarded' || $stage === 'autoresponder') {
        $outcome = 'descartado';
        $confidence = 0.85;
        $reason = 'stage_' . $stage;
    } elseif ($hasLead || $stage === 'qualified' || $stage === 'very_hot') {
        // Lead conseguido: ¿ghosteó?
        $lastContactAt = trim((string)($thread['last_contact_at'] ?? $thread['updated_at'] ?? ''));
        $lastTs = $lastContactAt !== '' ? strtotime($lastContactAt) : 0;
        $ghostThreshold = 24 * 3600; // 24h sin actividad tras lead → ghosted
        if ($lastTs > 0 && ($now - $lastTs) > $ghostThreshold) {
            $outcome = 'lead_ghosted';
            $confidence = 0.75;
            $reason = 'lead_inactive_' . (int)round(($now - $lastTs) / 3600) . 'h';
        } else {
            $outcome = 'lead_probable';
            $confidence = 0.7;
            $reason = 'qualified';
        }
    } elseif ($replies >= comercial_learning_mareador_min_messages()) {
        $outcome = 'mareador';
        $confidence = 0.8;
        $reason = 'high_replies_' . $replies;
    } elseif ($replies === 0) {
        // Opener enviado sin respuesta (campaña en frío) → señal de "apertura que no engancha"
        $outcome = 'no_respuesta';
        $confidence = 0.9;
        $reason = 'no_response';
    } elseif ($replies <= comercial_learning_dead_max_messages()) {
        $outcome = 'muerta';
        $confidence = 0.8;
        $reason = 'few_replies_' . $replies;
    }

    return array('outcome' => $outcome, 'confidence' => round((float)$confidence, 2), 'reason' => $reason);
}

/**
 * Clasifica todas las conversaciones inactivas de los últimos N días.
 *
 * @param int $days Ventana de análisis en días (default 7)
 * @return int Número de nuevas clasificaciones escritas
 */
function comercial_classify_conversation_outcomes(int $days = 7): int {
    $outcomesFile = comercial_learning_outcomes_file();
    $now = time();
    $inactivity = comercial_learning_inactivity_hours() * 3600;

    // Cargar outcomes existentes para no reclasificar
    $existing = array();
    if (file_exists($outcomesFile)) {
        $lines = @file($outcomesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $rec = json_decode($line, true);
                if (is_array($rec) && !empty($rec['thread_id'])) {
                    $existing[(string)$rec['thread_id']] = true;
                }
            }
        }
    }

    $threads = comercial_get_threads();
    $newCount = 0;

    foreach ($threads as $thread) {
        $tid = (string)($thread['id'] ?? '');
        if ($tid === '') continue;
        if (isset($existing[$tid])) continue;

        // Última actividad: saltar si la conversación sigue activa
        $lastContactAt = trim((string)($thread['last_contact_at'] ?? $thread['updated_at'] ?? ''));
        $lastTs = $lastContactAt !== '' ? strtotime($lastContactAt) : 0;
        if ($lastTs > 0 && ($now - $lastTs) < $inactivity) continue;

        $result = comercial_classify_thread_outcome($thread, $now);

        $rec = array(
            'thread_id'       => $tid,
            'process_slug'    => trim((string)($thread['process_slug'] ?? '')),
            'phone'           => trim((string)($thread['target_phone'] ?? '')),
            'stage'           => trim((string)($thread['stage'] ?? '')),
            'replies_count'   => (int)($thread['replies_count'] ?? 0),
            'human_taken'     => !empty($thread['human_taken']) ? 1 : 0,
            'lead_id'         => trim((string)($thread['lead_id'] ?? '')),
            'started'         => trim((string)($thread['created_at'] ?? '')),
            'last_activity'   => $lastContactAt,
            'outcome'         => $result['outcome'],
            'confidence'      => $result['confidence'],
            'reason'          => $result['reason'],
            'classified_by'   => 'auto',
            'classified_at'   => date('c', $now),
        );

        @file_put_contents($outcomesFile, json_encode($rec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
        $existing[$tid] = true;
        $newCount++;
    }

    return $newCount;
}

/**
 * Carga los outcomes clasificados de los últimos N días, agrupados por outcome.
 *
 * @param int $days Ventana en días
 * @return array [thread_id => outcome record]
 */
function comercial_learning_load_outcomes(int $days = 7): array {
    $outcomesFile = comercial_learning_outcomes_file();
    $out = array();
    if (!file_exists($outcomesFile)) return $out;
    $lines = @file($outcomesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return $out;
    $cutoff = time() - $days * 86400;
    foreach ($lines as $line) {
        $rec = json_decode($line, true);
        if (!is_array($rec) || empty($rec['thread_id'])) continue;
        $classifiedAt = strtotime((string)($rec['classified_at'] ?? ''));
        if ($classifiedAt !== false && $classifiedAt < $cutoff) continue;
        $out[(string)$rec['thread_id']] = $rec;
    }
    return $out;
}

/**
 * Reconstruye el historial textual de un hilo como líneas "Cliente:" / "Comercial:".
 *
 * @param array $thread Thread normalizado
 * @param int   $limit  Nº máximo de eventos a leer del log global
 * @return array list<array{role:string,text:string}>
 */
function comercial_learning_thread_turns(array $thread, int $limit = 500): array {
    $history = comercial_thread_history($thread, $limit);
    $turns = array();
    foreach ((array)$history as $entry) {
        $text = trim((string)($entry['text'] ?? ''));
        if ($text === '') continue;
        $direction = (string)($entry['direction'] ?? '');
        $role = $direction === 'in' ? 'Cliente' : 'Comercial';
        $turns[] = array('role' => $role, 'text' => $text);
    }
    return $turns;
}

/**
 * Extrae las respuestas humanas guardadas en la memoria IA para un proceso.
 * Devuelve pares [trigger_text, reply_text] ordenados por score.
 *
 * @param string $processSlug Slug del negocio ('' = todos)
 * @return list<array{text:string,trigger_text:string,score:float}>
 */
function comercial_learning_human_replies(string $processSlug = ''): array {
    $rows = comercial_ai_memory_get_rows();
    $out = array();
    foreach ($rows as $row) {
        if ((string)($row['kind'] ?? 'human_reply') !== 'human_reply') continue;
        if ($processSlug !== '' && (string)($row['process_slug'] ?? '') !== $processSlug) continue;
        $text = trim((string)($row['text'] ?? ''));
        if ($text === '') continue;
        $score = ((int)($row['led_to_lead_count'] ?? 0) * 8)
               + ((int)($row['accepted_count'] ?? 0) * 3)
               - ((int)($row['edited_count'] ?? 0))
               + ((int)($row['use_count'] ?? 0));
        $out[] = array(
            'text'         => $text,
            'trigger_text' => trim((string)($row['trigger_text'] ?? '')),
            'score'        => (float)$score,
        );
    }
    usort($out, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    return $out;
}
