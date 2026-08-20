<?php
/**
 * inbox_api.php — API JSON para el chat SuperWasap del inbox comercial.
 *
 * Endpoints:
 *   GET  ?action=lines                     → líneas con sus hilos agrupados (incluye unread)
 *   GET  ?action=thread&id=THREAD_ID       → timeline completo de un hilo
 *   POST ?action=send                      → enviar mensaje manual
 *   POST ?action=toggle_thread             → pausar/reactivar bot en un hilo
 *   POST ?action=mark_read&thread_id=X     → marcar hilo como leído
 */

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/comercial_agenda.php';

auth_auto_login_from_whitelist();

if (!is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? ''));

// ── Helpers ──

function inbox_api_json_ok($data) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function inbox_api_json_err($msg, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Read-status helpers (patrón SuperWasap) ──
// Formato: { "comthread_xxx": "2026-08-09T14:30:00Z", ... }
// Clave = thread_id, Valor = ISO8601 UTC (última vez que el operador vio el hilo)

function inbox_read_status_path(): string {
    return __DIR__ . '/data/inbox_read_status.json';
}

function inbox_read_status(): array {
    $path = inbox_read_status_path();
    if (file_exists($path)) {
        $raw = @file_get_contents($path);
        if ($raw !== false) {
            $data = json_decode($raw, true);
            if (is_array($data)) return $data;
        }
    }
    return [];
}

function inbox_save_read_status(array $data): bool {
    $path = inbox_read_status_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (file_exists($path) && !is_writable($path)) {
        @chmod($path, 0664);
        clearstatcache(true, $path);
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log('inbox_save_read_status: json_encode failed: ' . json_last_error_msg());
        return false;
    }
    $written = @file_put_contents($path, $json, LOCK_EX);
    if ($written === false) {
        error_log('inbox_save_read_status: write failed for ' . $path);
        return false;
    }
    return true;
}

function inbox_is_unread(string $threadId, string $updatedAt, array $readStatus): bool {
    if ($updatedAt === '') return false;
    $updatedUnix = strtotime($updatedAt);
    if ($updatedUnix === false) return false;
    $lastRead = $readStatus[$threadId] ?? '';
    if ($lastRead === '') {
        // Hilo nunca visto: si la última actividad es reciente (<30 min), es nuevo → no leído.
        // Si es antiguo, asumimos que ya fue leído (backfill implícito).
        return (time() - $updatedUnix) < 1800;
    }
    $lastReadUnix = strtotime($lastRead);
    if ($lastReadUnix === false) return false;
    return $updatedUnix > $lastReadUnix;
}

// ── GET ?action=lines ──
// Devuelve líneas asignadas a procesos, cada una con sus hilos + info de no leídos

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'lines') {
    $allThreads = comercial_get_threads();
    $allLines = comercial_list_lines();
    $linesIndexed = comercial_list_lines_indexed();
    $processLineIds = inbox_get_process_line_ids();
    $readStatus = inbox_read_status();

    // Filtrar solo líneas de procesos
    $filteredLines = [];
    foreach ($allLines as $line) {
        if (in_array((string)($line['id'] ?? ''), $processLineIds, true)) {
            $filteredLines[] = $line;
        }
    }

    // Agrupar hilos por línea
    $threadsByLine = [];
    foreach ($allThreads as $thread) {
        $lid = trim((string)($thread['line_id'] ?? ''));
        if ($lid === '' || !in_array($lid, $processLineIds, true)) continue;
        if (!isset($threadsByLine[$lid])) $threadsByLine[$lid] = [];
        $threadsByLine[$lid][] = $thread;
    }

    $result = [];
    foreach ($filteredLines as $line) {
        $lid = (string)($line['id'] ?? '');
        $threads = $threadsByLine[$lid] ?? [];

        $threadItems = [];
        $lineLastTs = '';
        $lineUnread = 0;

        foreach ($threads as $thread) {
            $phone = comercial_only_digits((string)($thread['target_phone'] ?? ''));
            $lastMsg = trim((string)($thread['last_inbound_text'] ?? ''));
            if ($lastMsg === '') $lastMsg = trim((string)($thread['last_outbound_text'] ?? ''));
            $stage = trim((string)($thread['stage'] ?? ''));
            $tid = (string)($thread['id'] ?? '');
            $updatedAt = trim((string)($thread['updated_at'] ?? $thread['created_at'] ?? ''));
            $unread = inbox_is_unread($tid, $updatedAt, $readStatus);

            // Agenda lookup
            $agendaEntry = comercial_agenda_find_by_phone($phone);
            $agendaName = $agendaEntry ? (string)($agendaEntry['nombre'] ?? '') : '';
            $agendaId   = $agendaEntry ? (string)($agendaEntry['id'] ?? '') : '';
            $displayName = $agendaName !== '' ? $agendaName : ($phone !== '' ? $phone : 'Sin teléfono');

            $threadItems[] = [
                'id'            => $tid,
                'phone'         => $phone,
                'display_name'  => $displayName,
                'agenda_name'   => $agendaName,
                'agenda_id'     => $agendaId,
                'last_message'  => function_exists('mb_substr') ? mb_substr($lastMsg, 0, 80, 'UTF-8') : substr($lastMsg, 0, 80),
                'last_ts'       => $updatedAt,
                'stage'         => $stage,
                'stage_label'   => function_exists('comercial_thread_stage_label') ? comercial_thread_stage_label($stage) : $stage,
                'paused'        => !empty($thread['inbox_paused']),
                'human_taken'   => !empty($thread['human_taken']),
                'replies_count' => (int)($thread['replies_count'] ?? 0),
                'sent_count'    => (int)($thread['messages_sent_count'] ?? 0),
                'process_slug'  => trim((string)($thread['process_slug'] ?? '')),
                'line_phone'    => trim((string)($thread['line_phone'] ?? '')),
                'unread'        => $unread,
            ];

            // Track line-level: más reciente + unread count
            if ($updatedAt !== '' && ($lineLastTs === '' || $updatedAt > $lineLastTs)) {
                $lineLastTs = $updatedAt;
            }
            if ($unread) $lineUnread++;
        }

        // Ordenar: unread primero, luego por last_ts desc
        usort($threadItems, function ($a, $b) {
            $aUnread = $a['unread'] ? 1 : 0;
            $bUnread = $b['unread'] ? 1 : 0;
            if ($aUnread !== $bUnread) return $bUnread - $aUnread;
            return strcmp((string)($b['last_ts'] ?? ''), (string)($a['last_ts'] ?? ''));
        });

        $result[] = [
            'line_id'          => $lid,
            'line_name'        => trim((string)($line['nombre'] ?? '')),
            'line_phone'       => comercial_only_digits((string)($line['tfono'] ?? '')),
            'waha_port'        => trim((string)($line['waha_port'] ?? '')),
            'threads'          => $threadItems,
            'thread_count'     => count($threadItems),
            'line_last_ts'     => $lineLastTs,
            'line_total_unread'=> $lineUnread,
        ];
    }

    inbox_api_json_ok([
        'lines' => $result,
        'settings' => function_exists('inbox_get_settings') ? inbox_get_settings() : ['replies_enabled' => true, 'opener_enabled' => true],
    ]);
}

// ── GET ?action=thread&id=X ──
// Devuelve timeline completo del hilo (mensajes) + metadatos

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'thread') {
    $threadId = trim((string)($_GET['id'] ?? ''));
    if ($threadId === '') inbox_api_json_err('Falta id del hilo');

    $thread = null;
    foreach (comercial_get_threads() as $t) {
        if ((string)($t['id'] ?? '') === $threadId) { $thread = $t; break; }
    }
    if (!$thread) inbox_api_json_err('Hilo no encontrado', 404);

    $thread = comercial_normalize_thread($thread);

    // ── Respuestas nativas en tiempo real ──
    // GOWS no dispara el webhook message.any para salientes, así que consultamos
    // el historial de WAHA para detectar si el humano respondió desde WhatsApp
    // nativo y reflejarlo (human_taken/paused) sin recargar la app manualmente.
    if (function_exists('comercial_sync_native_replies_for_thread')) {
        try {
            $nativeDetected = comercial_sync_native_replies_for_thread($thread);
            if ($nativeDetected > 0) {
                foreach (comercial_get_threads() as $t) {
                    if ((string)($t['id'] ?? '') === $threadId) { $thread = comercial_normalize_thread($t); break; }
                }
            }
        } catch (Throwable $e) {
            // No romper la carga del hilo por un fallo de polling WAHA
        }
    }

    $linesIndexed = comercial_list_lines_indexed();
    $lineName = isset($linesIndexed[(string)($thread['line_id'] ?? '')]) ? trim((string)($linesIndexed[(string)$thread['line_id']]['nombre'] ?? '')) : '';

    // Reconstruir timeline desde eventos
    $messages = [];
    $history = [];
    if (function_exists('comercial_thread_history')) {
        $history = comercial_thread_history($thread);
    } else {
        // Fallback: usar last_inbound/last_outbound
        $lastIn = trim((string)($thread['last_inbound_text'] ?? ''));
        $lastOut = trim((string)($thread['last_outbound_text'] ?? ''));
        $ts = trim((string)($thread['updated_at'] ?? $thread['created_at'] ?? ''));
        if ($lastIn !== '') {
            $history[] = ['ts' => $ts, 'direction' => 'in', 'text' => $lastIn, 'is_bot' => false];
        }
        if ($lastOut !== '') {
            $history[] = ['ts' => $ts, 'direction' => 'out', 'text' => $lastOut, 'is_bot' => !empty($thread['human_taken']) ? false : true];
        }
    }

    foreach ($history as $h) {
        $messages[] = [
            'ts'        => trim((string)($h['ts'] ?? '')),
            'direction' => trim((string)($h['direction'] ?? 'in')),
            'text'      => trim((string)($h['text'] ?? '')),
            'is_bot'    => !empty($h['is_bot']),
            'event'     => trim((string)($h['event'] ?? '')),
        ];
    }

    // Agenda lookup
    $threadPhone = comercial_only_digits((string)($thread['target_phone'] ?? ''));
    $agendaEntry = comercial_agenda_find_by_phone($threadPhone);
    $agendaData = null;
    if ($agendaEntry) {
        $agendaData = [
            'id'        => (string)($agendaEntry['id'] ?? ''),
            'nombre'    => (string)($agendaEntry['nombre'] ?? ''),
            'telefono'  => (string)($agendaEntry['telefono'] ?? ''),
            'negocio'   => (string)($agendaEntry['negocio'] ?? ''),
            'submode'   => (string)($agendaEntry['submode'] ?? ''),
            'notas'     => (string)($agendaEntry['notas'] ?? ''),
        ];
    }

    inbox_api_json_ok([
        'thread' => [
            'id'            => $threadId,
            'phone'         => $threadPhone,
            'line_phone'    => trim((string)($thread['line_phone'] ?? '')),
            'line_name'     => $lineName,
            'process_slug'  => trim((string)($thread['process_slug'] ?? '')),
            'stage'         => trim((string)($thread['stage'] ?? '')),
            'stage_label'   => function_exists('comercial_thread_stage_label') ? comercial_thread_stage_label(trim((string)($thread['stage'] ?? ''))) : '',
            'paused'        => !empty($thread['inbox_paused']),
            'human_taken'   => !empty($thread['human_taken']),
            'updated_at'    => trim((string)($thread['updated_at'] ?? '')),
            'next_bot_action_at' => trim((string)($thread['next_bot_action_at'] ?? '')),
            'agenda_entry'  => $agendaData,
        ],
        'messages' => $messages,
    ]);
}

// ── POST action=mark_read ──
// Marca un hilo como leído (establece el cursor de lectura a ahora +10s)
// Patrón SuperWasap: evita leer la timeline de eventos; usa gmdate con buffer.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mark_read') {
    $threadId = trim((string)($_POST['thread_id'] ?? ''));
    if ($threadId === '') inbox_api_json_err('thread_id required');

    // Usar hora actual + 10s de buffer (mismo patrón que SuperWasap)
    $lastTs = gmdate('Y-m-d\TH:i:s\Z', time() + 10);

    $readStatus = inbox_read_status();
    $readStatus[$threadId] = $lastTs;
    if (!inbox_save_read_status($readStatus)) {
        inbox_api_json_err('Error al guardar estado de lectura', 500);
    }

    inbox_api_json_ok(['thread_id' => $threadId, 'last_read_ts' => $lastTs]);
}

// ── POST action=send ──
// Envía mensaje manual desde la interfaz

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'send') {
    $threadId = trim((string)($_POST['thread_id'] ?? ''));
    $text = trim((string)($_POST['text'] ?? ''));

    if ($threadId === '' || $text === '') inbox_api_json_err('Faltan thread_id o text');

    $thread = null;
    foreach (comercial_get_threads() as $t) {
        if ((string)($t['id'] ?? '') === $threadId) { $thread = $t; break; }
    }
    if (!$thread) inbox_api_json_err('Hilo no encontrado', 404);

    // El humano interviene desde la app: cancelar cualquier respuesta automática
    // en vuelo y marcar la conversación como parada (human_taken + inbox_paused
    // los aplica comercial_send_thread_message con human_taken=true).
    if (function_exists('comercial_thread_request_cancel')) {
        comercial_thread_request_cancel($threadId);
    }

    $send = comercial_send_thread_message($thread, $text, [
        'human_taken' => true,
        'event_type'  => 'manual_outbound_sent',
    ]);

    if (!empty($send['ok'])) {
        $threadAfter = comercial_normalize_thread((array)($send['thread'] ?? $thread));
        comercial_upsert_thread($threadAfter);
        inbox_api_json_ok(['thread_id' => $threadId, 'sent_at' => now_datetime()]);
    } else {
        inbox_api_json_err((string)($send['error'] ?? 'Error al enviar'), 502);
    }
}

// ── POST action=toggle_thread ──
// Pausa/reactiva el bot para un hilo concreto

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_thread') {
    $threadId = trim((string)($_POST['thread_id'] ?? ''));
    if ($threadId === '') inbox_api_json_err('Falta thread_id');

    $found = false;
    $threads = comercial_get_threads();
    foreach ($threads as $thread) {
        if ((string)($thread['id'] ?? '') !== $threadId) continue;
        $thread = comercial_normalize_thread($thread);

        // Unificamos inbox_paused + human_taken: si cualquiera está activo → reanudar ambos
        $isEffectivelyPaused = !empty($thread['inbox_paused']) || !empty($thread['human_taken']);
        if ($isEffectivelyPaused) {
            $thread['inbox_paused'] = 0;
            $thread['human_taken']  = 0;
            $newPaused = false;
            // Al reactivar el bot, limpiar cualquier cancelación pendiente
            if (function_exists('comercial_thread_clear_cancel')) {
                comercial_thread_clear_cancel($threadId);
            }
        } else {
            $thread['inbox_paused'] = 1;
            $newPaused = true;
        }
        comercial_upsert_thread($thread);
        $found = true;
        inbox_api_json_ok([
            'paused'      => $newPaused,
            'human_taken' => false,
            'label'       => $newPaused ? 'pausada' : 'activa',
        ]);
    }

    if (!$found) inbox_api_json_err('Hilo no encontrado', 404);
}

// ── POST action=toggle_replies ──
// Activa/desactiva respuestas automáticas globales

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_replies') {
    $settings = inbox_get_settings();
    $settings['replies_enabled'] = !empty($settings['replies_enabled']) ? false : true;
    inbox_save_settings($settings);
    inbox_api_json_ok(['enabled' => (bool)$settings['replies_enabled']]);
}

// ── POST action=toggle_opener ──
// Activa/desactiva el abridor de conversaciones (tick)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_opener') {
    $settings = inbox_get_settings();
    $settings['opener_enabled'] = !empty($settings['opener_enabled']) ? false : true;
    inbox_save_settings($settings);
    inbox_api_json_ok(['enabled' => (bool)$settings['opener_enabled']]);
}

// ── GET ?action=pending_count ──
// Devuelve solo el número de hilos pendientes (para badge de notificación).
// Usado por polling ligero desde el botón Panel.

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'pending_count') {
    $allThreads = comercial_get_threads();
    $pending = 0;
    foreach ($allThreads as $thread) {
        $stage = trim((string)($thread['stage'] ?? ''));
        $humanTaken = !empty($thread['human_taken']);
        // Status simple: discarded or qualified/very_hot/human_taken = no pendiente
        if ($stage === 'discarded') continue;
        if ($stage === 'qualified' || $stage === 'very_hot' || $humanTaken) continue;
        $pending++;
    }
    inbox_api_json_ok(['pending' => $pending]);
}

// ── POST action=attend ──
// Marca un hilo como atendido (human_taken = true) y actualiza persistencia.

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'attend') {
    $threadId = trim((string)($_POST['thread_id'] ?? ''));
    if ($threadId === '') inbox_api_json_err('Falta thread_id');

    $found = false;
    $threads = comercial_get_threads();
    foreach ($threads as &$thread) {
        if ((string)($thread['id'] ?? '') !== $threadId) continue;
        $thread = comercial_normalize_thread($thread);
        $thread['human_taken'] = true;
        $thread['updated_at'] = now_datetime();
        comercial_upsert_thread($thread);
        $found = true;
        break;
    }
    unset($thread);

    if (!$found) inbox_api_json_err('Hilo no encontrado', 404);
    inbox_api_json_ok(['thread_id' => $threadId]);
}

// ── POST action=discard ──
// Marca un hilo como descartado

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'discard') {
    $threadId = trim((string)($_POST['thread_id'] ?? ''));
    if ($threadId === '') inbox_api_json_err('Falta thread_id');

    $found = false;
    $threads = comercial_get_threads();
    foreach ($threads as &$thread) {
        if ((string)($thread['id'] ?? '') !== $threadId) continue;
        $thread = comercial_normalize_thread($thread);
        $thread = comercial_thread_apply_stage($thread, 'discarded');
        comercial_upsert_thread($thread);
        $found = true;
        break;
    }
    unset($thread);

    if (!$found) inbox_api_json_err('Hilo no encontrado', 404);
    inbox_api_json_ok(['thread_id' => $threadId]);
}

// ── AGENDA COMERCIAL ──

// GET ?action=comercial_agenda_list
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'comercial_agenda_list') {
    $negocio = trim((string)($_GET['negocio'] ?? ''));
    $entries = comercial_agenda_list();

    // Ordenar descendente por created_at
    usort($entries, function ($a, $b) {
        $tsA = strtotime((string)($a['created_at'] ?? ''));
        $tsB = strtotime((string)($b['created_at'] ?? ''));
        return $tsB - $tsA;
    });

    // Filtrar por negocio
    if ($negocio !== '') {
        $entries = array_values(array_filter($entries, function ($e) use ($negocio) {
            return ((string)($e['negocio'] ?? '')) === $negocio;
        }));
    }

    inbox_api_json_ok(['entries' => $entries, 'negocios' => comercial_agenda_negocios()]);
}

// GET ?action=comercial_agenda_get&id=X
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'comercial_agenda_get') {
    $id = trim((string)($_GET['id'] ?? ''));
    if ($id === '') inbox_api_json_err('Falta id');
    $entry = comercial_agenda_find_by_id($id);
    if (!$entry) inbox_api_json_err('Entrada no encontrada', 404);
    inbox_api_json_ok(['entry' => $entry]);
}

// GET ?action=comercial_agenda_lookup&phone=X
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'comercial_agenda_lookup') {
    $phone = trim((string)($_GET['phone'] ?? ''));
    if ($phone === '') inbox_api_json_err('Falta phone');
    $entry = comercial_agenda_find_by_phone($phone);
    inbox_api_json_ok([
        'found' => $entry !== null,
        'entry' => $entry,
    ]);
}

// POST action=comercial_agenda_save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'comercial_agenda_save') {
    $entry = [
        'id'        => trim((string)($_POST['id'] ?? '')),
        'nombre'    => trim((string)($_POST['nombre'] ?? '')),
        'telefono'  => trim((string)($_POST['telefono'] ?? '')),
        'negocio'   => trim((string)($_POST['negocio'] ?? '')),
        'submode'   => trim((string)($_POST['submode'] ?? '')),
        'notas'     => trim((string)($_POST['notas'] ?? '')),
        'thread_id' => trim((string)($_POST['thread_id'] ?? '')),
    ];

    if ($entry['telefono'] === '') inbox_api_json_err('Falta teléfono');
    if ($entry['negocio'] === '') inbox_api_json_err('Falta negocio');

    $saved = comercial_agenda_save($entry);
    if ($saved === null) inbox_api_json_err('Error al guardar en la agenda (fallo de escritura)', 500);
    inbox_api_json_ok(['entry' => $saved]);
}

// POST action=comercial_agenda_delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'comercial_agenda_delete') {
    $id = trim((string)($_POST['id'] ?? ''));
    if ($id === '') inbox_api_json_err('Falta id');
    $deleted = comercial_agenda_delete($id);
    if (!$deleted) inbox_api_json_err('Entrada no encontrada', 404);
    inbox_api_json_ok(['deleted' => $id]);
}

// GET ?action=find_thread_by_phone&phone=X → buscar thread_id dado un teléfono
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'find_thread_by_phone') {
    $phone = comercial_only_digits(trim((string)($_GET['phone'] ?? '')));
    if ($phone === '') inbox_api_json_err('Falta phone');

    $found = null;
    foreach (comercial_get_threads() as $t) {
        $tphone = comercial_only_digits((string)($t['target_phone'] ?? ''));
        if ($tphone !== '' && $tphone === $phone) {
            $found = (string)($t['id'] ?? '');
            break;
        }
    }
    inbox_api_json_ok(['thread_id' => $found]);
}

// GET ?action=comercial_agenda_panel_phones → devuelve los teléfonos que están en el panel de agente
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'comercial_agenda_panel_phones') {
    $threads = comercial_get_threads();
    $panelThreads = comercial_filter_agent_threads($threads);
    $phones = [];
    foreach ($panelThreads as $t) {
        $phone = comercial_only_digits((string)($t['target_phone'] ?? ''));
        if ($phone !== '') $phones[$phone] = true;
    }
    inbox_api_json_ok(['panel_phones' => array_values(array_keys($phones))]);
}


// ── POST action=manual_panel_add ──
// Anade manualmente un hilo al panel de agente comercial
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'manual_panel_add') {
    $threadId = trim((string)($_POST['thread_id'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    $negocio = trim((string)($_POST['negocio'] ?? ''));

    if ($threadId === '') inbox_api_json_err('Falta thread_id');
    if ($reason === '') inbox_api_json_err('Falta motivo');
    if ($negocio === '') inbox_api_json_err('Falta negocio');

    $negocios = array('lamami', 'jostal', 'casawasap', 'publicista', 'general');
    if (!in_array($negocio, $negocios, true)) {
        inbox_api_json_err('Negocio no valido: ' . $negocio . '. Usa: ' . implode(', ', $negocios));
    }

    $found = false;
    $threads = comercial_get_threads();
    foreach ($threads as &$thread) {
        if ((string)($thread['id'] ?? '') !== $threadId) continue;
        $thread = comercial_normalize_thread($thread);
        $thread['manual_panel_include'] = true;
        $thread['manual_panel_reason'] = $reason;
        $thread['manual_panel_negocio'] = $negocio;
        $thread['manual_panel_at'] = now_datetime();
        $thread['updated_at'] = now_datetime();
        comercial_upsert_thread($thread);
        $found = true;
        inbox_api_json_ok([
            'thread_id' => $threadId,
            'negocio' => $negocio,
            'added_at' => $thread['manual_panel_at'],
        ]);
    }
    unset($thread);

    if (!$found) inbox_api_json_err('Hilo no encontrado', 404);
}

// ── GET ?action=room_photos ──
// Devuelve la lista de fotos de habitaciones (rama plaza) para el selector
// de adjuntar del chat. Vive en data/plaza_room_photos.json (compartir.site).

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'room_photos') {
    $photos = function_exists('plaza_room_photos_get') ? plaza_room_photos_get() : array();
    inbox_api_json_ok(['photos' => $photos]);
}

// ── Sin acción válida ──
inbox_api_json_err('Acción no válida. Usa: lines, thread, send, toggle_thread, toggle_replies, toggle_opener, mark_read, pending_count, attend, discard, manual_panel_add, find_thread_by_phone, room_photos');
