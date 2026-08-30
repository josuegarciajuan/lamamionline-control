<?php

function avisos_config() {
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $defaults = array();
    $path = BASE_PATH . '/avisos_config.php';

    if (is_file($path)) {
        $loaded = require $path;
        $defaults = is_array($loaded) ? $loaded : array();
    }

    $settings = storage_read('settings.json');
    $runtime = isset($settings['avisos_config']) && is_array($settings['avisos_config'])
        ? $settings['avisos_config']
        : array();

    $config = array_merge($defaults, $runtime);

    return $config;
}

function aviso_cfg($key, $default = null) {
    $cfg = avisos_config();
    return array_key_exists($key, $cfg) ? $cfg[$key] : $default;
}

function avisos_sender_presets() {
    return array(
        'dulce_oficina' => array(
            'label' => 'dulce - oficina',
            'host' => '100.117.92.74',
            'phone' => '604829142',
            'port' => '3001',
            'waha_name' => 'waha2',
            'session' => 'default',
        ),
        'dulce_josue' => array(
            'label' => 'dulce - josue',
            'host' => '100.113.76.93',
            'phone' => '604829142',
            'port' => '3001',
            'waha_name' => 'waha2',
            'session' => 'default',
        ),
        'salado_oficina' => array(
            'label' => 'salado - oficina',
            'host' => '100.117.92.74',
            'phone' => '631454098',
            'port' => '3006',
            'waha_name' => 'waha6',
            'session' => 'default',
        ),
        'salado_josue' => array(
            'label' => 'salado - josue',
            'host' => '100.113.76.93',
            'phone' => '631454098',
            'port' => '3006',
            'waha_name' => 'waha6',
            'session' => 'default',
        ),
    );
}

function avisos_sender_config_key() {
    $key = trim((string)aviso_cfg('whatsapp_sender_key', 'dulce_oficina'));
    $presets = avisos_sender_presets();

    if (!isset($presets[$key])) {
        $key = 'dulce_oficina';
    }

    return $key;
}

function avisos_sender_config() {
    $presets = avisos_sender_presets();
    $key = avisos_sender_config_key();
    return $presets[$key];
}

function avisos_comercial_sender_lines() {
    if (!function_exists('comercial_list_lines') || !function_exists('comercial_line_is_available')) {
        return array();
    }

    $out = array();
    foreach ((array)comercial_list_lines() as $line) {
        if (!is_array($line)) continue;
        if (trim((string)($line['id'] ?? '')) === '') continue;
        if (function_exists('telefonos_waha_usage_is_inactive') && telefonos_waha_usage_is_inactive($line)) continue;
        if (whatsapp_transport_for($line) !== 'evolution' && trim((string)($line['waha_port'] ?? '')) === '') continue;
        if (!comercial_line_is_available($line)) continue;
        $out[] = $line;
    }

    return array_values($out);
}

function avisos_pick_comercial_sender_line() {
    $lines = avisos_comercial_sender_lines();
    if (empty($lines)) {
        return null;
    }

    $index = array_rand($lines);
    return $lines[$index];
}

function avisos_comercial_sender_line_candidates() {
    $lines = avisos_comercial_sender_lines();
    if (count($lines) <= 1) {
        return $lines;
    }

    shuffle($lines);
    return array_values($lines);
}

function aviso_strtolower($text) {
    $text = (string)$text;
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

function aviso_noise_profile() {
    $profile = aviso_strtolower(trim((string)aviso_cfg('alerts_noise_profile', 'balanceado')));
    if (!in_array($profile, array('conservador', 'balanceado', 'agresivo'), true)) {
        $profile = 'balanceado';
    }
    return $profile;
}

function aviso_type_key($aviso) {
    $aviso = is_array($aviso) ? $aviso : array();
    $engine = trim((string)($aviso['engine'] ?? 'general'));
    if ($engine === '') $engine = 'general';
    $kind = trim((string)($aviso['meta']['kind'] ?? ''));
    if ($kind !== '') {
        return $engine . ':' . $kind;
    }
    return $engine;
}

function aviso_sender_overrides_map() {
    $raw = trim((string)aviso_cfg('whatsapp_sender_overrides', ''));
    if ($raw === '') return array();
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    $map = array();
    foreach ((array)$lines as $line) {
        $line = trim((string)$line);
        if ($line === '' || strpos($line, '=') === false) continue;
        list($k, $v) = array_map('trim', explode('=', $line, 2));
        if ($k === '' || $v === '') continue;
        $map[aviso_strtolower($k)] = $v;
    }
    return $map;
}

function aviso_sender_match_line_from_override($overrideValue, $lines) {
    $overrideValue = trim((string)$overrideValue);
    if ($overrideValue === '' || empty($lines)) return null;
    $overrideDigits = comercial_only_digits($overrideValue);
    foreach ((array)$lines as $line) {
        $lineId = trim((string)($line['id'] ?? ''));
        $linePhone = comercial_only_digits((string)($line['tfono'] ?? ''));
        $lineName = aviso_strtolower(trim((string)($line['nombre'] ?? '')));
        if ($lineId !== '' && $lineId === $overrideValue) return $line;
        if ($overrideDigits !== '' && $linePhone !== '' && substr($linePhone, -9) === substr($overrideDigits, -9)) return $line;
        if ($lineName !== '' && $lineName === aviso_strtolower($overrideValue)) return $line;
    }
    return null;
}

function aviso_sender_line_candidates_for_aviso($aviso, $lines) {
    $lines = array_values((array)$lines);
    if (count($lines) <= 1) return $lines;

    $typeKey = aviso_type_key($aviso);
    $normalizedType = aviso_strtolower($typeKey);
    $overrides = aviso_sender_overrides_map();
    $engine = aviso_strtolower(trim((string)($aviso['engine'] ?? 'general')));
    $overrideValue = '';
    if (isset($overrides[$normalizedType])) {
        $overrideValue = $overrides[$normalizedType];
    } elseif (isset($overrides[$engine])) {
        $overrideValue = $overrides[$engine];
    }

    $picked = aviso_sender_match_line_from_override($overrideValue, $lines);
    if (!$picked) {
        // Emisor por defecto: la línea de dulce (tf_de558a13) para evitar ruido
        // de múltiples remitentes distintos en los avisos de WhatsApp.
        $picked = aviso_sender_match_line_from_override('tf_de558a13', $lines);
    }
    if (!$picked) {
        $seed = abs(crc32($normalizedType));
        $picked = $lines[$seed % count($lines)];
    }

    $pickedId = trim((string)($picked['id'] ?? ''));
    $ordered = array();
    foreach ($lines as $line) {
        if (trim((string)($line['id'] ?? '')) === $pickedId) {
            $ordered[] = $line;
            break;
        }
    }
    foreach ($lines as $line) {
        if (trim((string)($line['id'] ?? '')) === $pickedId) continue;
        $ordered[] = $line;
    }
    return array_values($ordered);
}

function aviso_whatsapp_allowed_for_aviso($aviso) {
    // Los avisos manuales creados por el usuario (panel o planificados) son
    // intencionales: deben enviarse siempre por WhatsApp, sin depender del
    // perfil de ruido, que solo aplica a los avisos automáticos del motor.
    $aviso = is_array($aviso) ? $aviso : array();
    if (trim((string)($aviso['engine'] ?? '')) === 'manual') {
        return true;
    }

    $severity = aviso_normalize_severity($aviso['severity'] ?? 'media');
    $profile = aviso_noise_profile();
    if ($profile === 'agresivo') {
        return $severity === 'alta';
    }
    if ($profile === 'balanceado') {
        return in_array($severity, array('alta', 'media'), true);
    }
    return true;
}

function avisos_sender_retry_rounds() {
    return 3;
}

function avisos_target_phones() {
    $raw = aviso_cfg('whatsapp_target_phones', "654464023\n641993776");

    if (is_array($raw)) {
        $items = $raw;
    } else {
        $items = preg_split('/[\s,;]+/', (string)$raw);
    }

    $out = array();
    foreach ($items as $item) {
        $phone = trim((string)$item);
        if ($phone === '') continue;
        if (!in_array($phone, $out, true)) {
            $out[] = $phone;
        }
    }

    return $out;
}

/**
 * Destinos de las notificaciones informativas al dueño.
 * Devuelve ['primary' => teléfono, 'secondary' => [teléfonos]].
 * - primary: 654464023 (si está configurado; si no, el primero de la lista).
 * - secondary: el resto de whatsapp_target_phones + SIEMPRE 641993776 (dedup).
 * El secondary recibe el mismo aviso parafraseado por LLM y con ~20s de espera
 * para no levantar sospechas de spam/baneo en WhatsApp.
 */
function avisos_owner_notification_phones() {
    $targets = avisos_target_phones();
    $primary = '654464023';
    $secondary = array();

    if (empty($targets)) {
        return array('primary' => $primary, 'secondary' => array('641993776'));
    }

    if (in_array($primary, $targets, true)) {
        $rest = array_values(array_filter($targets, function ($p) use ($primary) {
            return trim((string)$p) !== $primary;
        }));
    } else {
        $primary = trim((string)array_shift($targets));
        $rest = array_values($targets);
    }
    if ($primary === '') {
        $primary = '654464023';
    }

    foreach ($rest as $p) {
        $p = trim((string)$p);
        if ($p === '' || $p === $primary) continue;
        if (!in_array($p, $secondary, true)) $secondary[] = $p;
    }

    if (!in_array('641993776', $secondary, true) && '641993776' !== $primary) {
        $secondary[] = '641993776';
    }

    return array('primary' => $primary, 'secondary' => array_values($secondary));
}

function aviso_pending_target_phones($aviso) {
    $allPhones = avisos_target_phones();
    if (empty($allPhones)) {
        return array();
    }

    $aviso = is_array($aviso) ? $aviso : array();
    $lastStatus = trim((string)($aviso['whatsapp_last_result'] ?? ''));
    $lastLog = isset($aviso['whatsapp_last_log']) && is_array($aviso['whatsapp_last_log']) ? $aviso['whatsapp_last_log'] : array();
    $phoneLogs = isset($lastLog['phones']) && is_array($lastLog['phones']) ? $lastLog['phones'] : array();

    if ($lastStatus === 'sent') {
        return array();
    }

    if ($lastStatus !== 'partial' || empty($phoneLogs)) {
        return $allPhones;
    }

    $pending = array();
    foreach ($allPhones as $phone) {
        $matched = null;
        foreach ($phoneLogs as $logRow) {
            if (!is_array($logRow)) continue;
            if (trim((string)($logRow['phone'] ?? '')) === trim((string)$phone)) {
                $matched = $logRow;
                break;
            }
        }
        if (!$matched || empty($matched['ok'])) {
            $pending[] = $phone;
        }
    }

    return !empty($pending) ? $pending : $allPhones;
}

function aviso_whatsapp_defaults() {
    return array(
        'whatsapp_sent_at' => '',
        'whatsapp_last_attempt_at' => '',
        'whatsapp_last_result' => '',
        'whatsapp_last_error' => '',
        'whatsapp_last_log' => array(),
    );
}

function aviso_whatsapp_log_snapshot($sendResult) {
    $sendResult = is_array($sendResult) ? $sendResult : array();
    $phones = array();

    foreach ((array)($sendResult['phones'] ?? array()) as $phoneRow) {
        if (!is_array($phoneRow)) continue;
        $phones[] = array(
            'phone' => trim((string)($phoneRow['phone'] ?? '')),
            'chat_id' => trim((string)($phoneRow['chat_id'] ?? '')),
            'http_code' => (int)($phoneRow['http_code'] ?? 0),
            'ok' => !empty($phoneRow['ok']),
            'error' => trim((string)($phoneRow['error'] ?? '')),
            'response' => aviso_safe_substr((string)($phoneRow['response'] ?? ''), 0, 1000),
            'line_id' => trim((string)($phoneRow['line_id'] ?? '')),
            'line_name' => trim((string)($phoneRow['line_name'] ?? '')),
            'line_phone' => trim((string)($phoneRow['line_phone'] ?? '')),
        );
    }

    return array(
        'attempted_at' => trim((string)($sendResult['attempted_at'] ?? '')),
        'status' => trim((string)($sendResult['status'] ?? '')),
        'ok' => !empty($sendResult['ok']),
        'error' => trim((string)($sendResult['error'] ?? '')),
        'sender' => is_array($sendResult['sender'] ?? null) ? $sendResult['sender'] : array(),
        'phones' => $phones,
    );
}

function aviso_apply_whatsapp_send_result($row, $sendResult, $now = '') {
    $row = array_merge(aviso_whatsapp_defaults(), is_array($row) ? $row : array());
    $now = trim((string)$now);
    if ($now === '') $now = now_datetime();

    $row['whatsapp_last_attempt_at'] = trim((string)($sendResult['attempted_at'] ?? $now));
    $row['whatsapp_last_result'] = trim((string)($sendResult['status'] ?? 'error'));
    $row['whatsapp_last_error'] = trim((string)($sendResult['error'] ?? ''));
    $row['whatsapp_last_log'] = aviso_whatsapp_log_snapshot($sendResult);

    if (!empty($sendResult['ok'])) {
        $row['whatsapp_sent_at'] = $now;
    }

    return $row;
}

function aviso_whatsapp_has_failure($aviso) {
    $status = trim((string)($aviso['whatsapp_last_result'] ?? ''));
    if ($status === '' || $status === 'sent') return false;
    return true;
}

function aviso_whatsapp_should_retry($aviso) {
    $aviso = is_array($aviso) ? $aviso : array();
    if (trim((string)($aviso['status'] ?? '')) !== 'active') {
        return false;
    }
    if (!empty($aviso['whatsapp_delivery_claim'])) {
        return false;
    }

    if (trim((string)($aviso['whatsapp_sent_at'] ?? '')) === '') {
        return true;
    }

    return aviso_whatsapp_has_failure($aviso);
}

function aviso_whatsapp_count_as_sent($sendResult) {
    $status = trim((string)($sendResult['status'] ?? ''));
    return in_array($status, array('sent', 'partial'), true);
}

function aviso_whatsapp_count_as_failed($sendResult) {
    $status = trim((string)($sendResult['status'] ?? ''));
    if ($status === 'skipped_by_profile') return false;
    return !aviso_whatsapp_count_as_sent($sendResult);
}

function aviso_whatsapp_log_url($avisoId, $params = array()) {
    $query = array_merge(array(
        'avtab' => 'active',
        'wa_log' => trim((string)$avisoId),
    ), is_array($params) ? $params : array());

    return avisos_page_url($query);
}

function avisos_hhmm_normalize($value, $default = '00:00') {
    $raw = trim((string)$value);
    if ($raw === '') return $default;

    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $raw, $m)) {
        return $default;
    }

    $h = max(0, min(23, (int)$m[1]));
    $i = max(0, min(59, (int)$m[2]));

    return sprintf('%02d:%02d', $h, $i);
}

function avisos_hhmm_list($raw, $fallback = array('00:01', '12:01')) {
    if (is_array($raw)) {
        $items = $raw;
    } else {
        $items = preg_split('/[\r\n,;]+/', (string)$raw);
    }

    $out = array();
    foreach ($items as $item) {
        $hhmm = avisos_hhmm_normalize($item, '');
        if ($hhmm === '') continue;
        if (!in_array($hhmm, $out, true)) {
            $out[] = $hhmm;
        }
    }

    if (empty($out)) {
        $out = $fallback;
    }

    sort($out);
    return $out;
}

function avisos_today_hhmm_ts($hhmm) {
    $hhmm = avisos_hhmm_normalize($hhmm, '00:00');
    return strtotime(date('Y-m-d') . ' ' . $hhmm . ':00');
}

function avisos_is_due_now_for_hhmm($hhmm, $windowMinutes = 90) {
    $scheduledTs = avisos_today_hhmm_ts($hhmm);
    if (!$scheduledTs) return false;

    $nowTs = time();
    $windowSeconds = max(1, (int)$windowMinutes) * 60;

    return $nowTs >= $scheduledTs && $nowTs < ($scheduledTs + $windowSeconds);
}

function avisos_times_for_mundosex_window($startTime, $endTime, $intervalHours) {
    $times = array();

    $intervalHours = max(1, (int)$intervalHours);
    $startTime = avisos_hhmm_normalize($startTime, '08:00');
    $endTime = avisos_hhmm_normalize($endTime, '23:00');

    $startTs = strtotime(date('Y-m-d') . ' ' . $startTime . ':00');
    $endTs = strtotime(date('Y-m-d') . ' ' . $endTime . ':00');

    if (!$startTs || !$endTs || $endTs < $startTs) {
        return $times;
    }

    $step = $intervalHours * 3600;
    for ($ts = $startTs; $ts <= $endTs; $ts += $step) {
        $times[] = date('H:i', $ts);
    }

    return $times;
}

function aviso_normalize_phone($phone) {
    $digits = preg_replace('/\D+/', '', (string)$phone);
    if ($digits === '') return '';
    if (strlen($digits) === 9) {
        $digits = '34' . $digits;
    }
    return $digits;
}

function aviso_normalize_severity($severity) {
    if (is_array($severity) || is_object($severity)) return 'media';
    $severity = strtolower(trim((string)$severity));
    return in_array($severity, array('baja', 'media', 'alta'), true) ? $severity : 'media';
}

function aviso_severity_emoji($severity) {
    $severity = aviso_normalize_severity($severity);

    if ($severity === 'alta') {
        return '🚨';
    }

    if ($severity === 'media') {
        return '⚠️';
    }

    return '🔔';
}

function aviso_cli_log($message) {
    if (PHP_SAPI === 'cli') {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "
";
    }
}

function aviso_is_recent_for_event($value) {
    $ts = aviso_ts($value);
    if (!$ts) return false;

    $hours = (int)aviso_cfg('events_recent_hours', 12);
    if ($hours <= 0) return true;

    return (time() - $ts) <= ($hours * 3600);
}

function aviso_build_run_summary($stats) {
    return 'gen=' . (int)($stats['generated'] ?? 0)
        . ', nuevas=' . (int)($stats['created'] ?? 0)
        . ', reactivadas=' . (int)($stats['reactivated'] ?? 0)
        . ', actualizadas=' . (int)($stats['updated'] ?? 0)
        . ', resueltas=' . (int)($stats['resolved'] ?? 0)
        . ', descartadas_persistentes=' . (int)($stats['dismissed_persistent'] ?? 0)
        . ', wa_ok=' . (int)($stats['whatsapp_sent'] ?? 0)
        . ', wa_error=' . (int)($stats['whatsapp_failed'] ?? 0);
}

function aviso_log_run($entry) {
    $rows = storage_read('avisos_runs.json');
    $rows[] = $entry;
    if (count($rows) > 500) {
        $rows = array_slice($rows, -500);
    }
    storage_write('avisos_runs.json', array_values($rows));
}

function aviso_make($engine, $sourceKey, $title, $message, $severity = 'media', $meta = array(), $autoResolve = true) {
    return array(
        'engine' => trim((string)$engine),
        'source_key' => trim((string)$sourceKey),
        'title' => trim((string)$title),
        'message' => trim((string)$message),
        'severity' => aviso_normalize_severity($severity),
        'meta' => is_array($meta) ? $meta : array(),
        'auto_resolve' => $autoResolve ? true : false,
    );
}

function aviso_ts($value) {
    $raw = trim((string)$value);
    if ($raw === '') return 0;
    return strtotime(str_replace('T', ' ', $raw));
}

function aviso_exists_any_status($engine, $sourceKey) {
    $engine = trim((string)$engine);
    $sourceKey = trim((string)$sourceKey);
    if ($engine === '' || $sourceKey === '') {
        return false;
    }

    $snapshot = avisos_snapshot_read();
    if (isset($snapshot['exists_engine_source_any'][$engine . '|' . $sourceKey])) {
        return true;
    }

    foreach (storage_read('avisos.json') as $row) {
        if (($row['engine'] ?? '') === $engine && ($row['source_key'] ?? '') === $sourceKey) {
            return true;
        }
    }
    return false;
}

function avisos_snapshot_path() {
    return DATA_PATH . '/avisos_active_snapshot.json';
}

function avisos_active_rows_sorted($rows) {
    $out = array();
    foreach ((array)$rows as $row) {
        if (($row['status'] ?? '') === 'active') {
            $out[] = $row;
        }
    }

    usort($out, function ($a, $b) {
        $aUnread = empty($a['read_at']) ? 1 : 0;
        $bUnread = empty($b['read_at']) ? 1 : 0;
        if ($aUnread !== $bUnread) return $bUnread <=> $aUnread;
        return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
    });

    return $out;
}

function avisos_snapshot_build_from_rows($rows, $sourceFingerprint = array()) {
    $activeRows = avisos_active_rows_sorted($rows);
    $existsAny = array();
    foreach ((array)$rows as $row) {
        $engine = trim((string)($row['engine'] ?? ''));
        $sourceKey = trim((string)($row['source_key'] ?? ''));
        if ($engine === '' || $sourceKey === '') continue;
        $existsAny[$engine . '|' . $sourceKey] = true;
    }

    return array(
        'generated_at' => now_datetime(),
        'source_hash' => trim((string)($sourceFingerprint['hash'] ?? '')),
        'source_size' => (int)($sourceFingerprint['size'] ?? 0),
        'active_rows' => array_values($activeRows),
        'active_ids' => array_values(array_map(function ($row) {
            return (string)($row['id'] ?? '');
        }, $activeRows)),
        'exists_engine_source_any' => $existsAny,
    );
}

function avisos_canonical_fingerprint() {
    $path = DATA_PATH . '/avisos.json';
    if (!is_file($path) || !is_readable($path)) return array('hash' => '', 'size' => -1);
    $hash = @hash_file('sha256', $path);
    $size = @filesize($path);
    return array('hash' => is_string($hash) ? $hash : '', 'size' => $size === false ? -1 : (int)$size);
}

function avisos_read_canonical_json_strict(&$error = '') {
    $error = '';
    $path = DATA_PATH . '/avisos.json';
    if (!file_exists($path)) return array();
    if (!is_file($path) || !is_readable($path)) {
        $error = 'canonical no legible: ' . $path;
        return false;
    }
    $raw = @file_get_contents($path);
    if ($raw === false) {
        $error = 'falló lectura canonical: ' . $path;
        return false;
    }
    $trimmed = ltrim((string)$raw);
    $rows = json_decode($raw, true);
    if ($trimmed === '' || $trimmed[0] !== '[' || !is_array($rows) || json_last_error() !== JSON_ERROR_NONE) {
        $error = 'canonical JSON malformado: ' . json_last_error_msg();
        return false;
    }
    return $rows;
}

function avisos_snapshot_refresh($rows = null) {
    if (!is_array($rows)) {
        $mode = function_exists('storage_backend_mode') ? storage_backend_mode() : 'json';
        if ($mode === 'json') {
            $error = '';
            $rows = avisos_read_canonical_json_strict($error);
            if ($rows === false) {
                avisos_storage_log_failure($error);
                return array('generated_at' => now_datetime(), 'source_hash' => '', 'source_size' => -1, 'active_rows' => array(), 'active_ids' => array(), 'exists_engine_source_any' => array());
            }
        } else {
            $rows = storage_read('avisos.json');
        }
    }
    $snapshot = avisos_snapshot_build_from_rows($rows, avisos_canonical_fingerprint());
    if (!avisos_json_write_atomic_unlocked(avisos_snapshot_path(), $snapshot, false)) {
        avisos_storage_log_failure('falló actualización de snapshot; se devuelve canonical en memoria');
    }
    return $snapshot;
}

function avisos_snapshot_read() {
    $path = avisos_snapshot_path();
    if (!is_file($path)) return avisos_snapshot_refresh();

    $raw = @file_get_contents($path);
    $decoded = json_decode((string)$raw, true);
    $canonical = avisos_canonical_fingerprint();
    $mode = function_exists('storage_backend_mode') ? storage_backend_mode() : 'json';
    $canonicalUsable = $mode !== 'json' || trim((string)($canonical['hash'] ?? '')) !== '';
    $snapshotFresh = is_array($decoded)
        && $canonicalUsable
        && hash_equals((string)($canonical['hash'] ?? ''), (string)($decoded['source_hash'] ?? ''))
        && (int)($canonical['size'] ?? -1) === (int)($decoded['source_size'] ?? -2);
    if (!$snapshotFresh || !isset($decoded['active_rows']) || !is_array($decoded['active_rows'])) {
        return avisos_snapshot_refresh();
    }

    if (!isset($decoded['exists_engine_source_any']) || !is_array($decoded['exists_engine_source_any'])) {
        $decoded['exists_engine_source_any'] = array();
    }
    return $decoded;
}

function avisos_rows_lock_path() {
    return DATA_PATH . '/avisos.json.lock';
}

function avisos_storage_log_failure($message) {
    $line = 'avisos_storage | ' . trim((string)$message);
    @error_log($line);
}

function avisos_open_shared_lock() {
    $path = avisos_rows_lock_path();
    $created = !file_exists($path);
    $oldUmask = umask(0000);
    $lock = @fopen($path, 'c+');
    umask($oldUmask);
    if ($lock) {
        if ($created || is_writable($path)) @chmod($path, 0666);
        return $lock;
    }

    // Un lock 0644 creado por otro usuario sigue siendo válido para flock.
    $lock = @fopen($path, 'r');
    if ($lock) return $lock;
    avisos_storage_log_failure('no se pudo abrir lock compartido: ' . $path);
    return false;
}

function avisos_json_write_atomic_unlocked($path, $data, $pretty = false) {
    $json = function_exists('storage_json_encode')
        ? storage_json_encode($data, $pretty)
        : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($pretty ? JSON_PRETTY_PRINT : 0));
    if (!is_string($json)) {
        avisos_storage_log_failure('no se pudo codificar JSON: ' . $path);
        return false;
    }
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        avisos_storage_log_failure('no se pudo crear directorio: ' . $dir);
        return false;
    }
    $existingStat = is_file($path) ? @stat($path) : false;
    $mode = is_array($existingStat) ? ((int)$existingStat['mode'] & 0777) : 0664;
    if ($mode <= 0) $mode = 0664;
    $tmpPath = $path . '.tmp.' . getmypid() . '.' . str_replace('.', '', uniqid('', true));
    $written = @file_put_contents($tmpPath, $json);
    if ($written === false) {
        avisos_storage_log_failure('falló escritura temporal: ' . $tmpPath);
        return false;
    }
    if (is_array($existingStat)) {
        @chown($tmpPath, (int)$existingStat['uid']);
        @chgrp($tmpPath, (int)$existingStat['gid']);
    }
    @chmod($tmpPath, $mode);
    if (!@rename($tmpPath, $path)) {
        avisos_storage_log_failure('falló reemplazo atómico: ' . $tmpPath . ' -> ' . $path);
        @unlink($tmpPath);
        return false;
    }
    return true;
}

function avisos_rows_persist_unlocked($rows) {
    $rows = array_values((array)$rows);
    $mode = function_exists('storage_backend_mode') ? storage_backend_mode() : 'json';
    $ok = false;

    if ($mode === 'mysql') {
        $ok = function_exists('storage_mysql_write') && storage_mysql_write('avisos.json', $rows);
    } elseif ($mode === 'dual') {
        $mysqlOk = function_exists('storage_mysql_write') && storage_mysql_write('avisos.json', $rows);
        if (!$mysqlOk) {
            avisos_storage_log_failure('falló persistencia MySQL canónica en modo dual; mirror JSON no modificado');
            $ok = false;
        } else {
            $jsonOk = avisos_json_write_atomic_unlocked(DATA_PATH . '/avisos.json', $rows, false);
            if (!$jsonOk) {
                // MySQL ya confirmó la mutación canónica; el mirror se reparará después.
                avisos_storage_log_failure('MySQL canónico persistido; falló mirror JSON en modo dual');
            }
            $ok = true;
        }
    } else {
        $ok = avisos_json_write_atomic_unlocked(DATA_PATH . '/avisos.json', $rows, false);
    }
    if (function_exists('storage_invalidate_cache')) {
        storage_invalidate_cache('avisos.json');
    }

    return $ok;
}

function avisos_rows_update_atomic($callback) {
    if (!is_callable($callback)) return array('ok' => false, 'result' => false);
    if (!is_dir(DATA_PATH) && !@mkdir(DATA_PATH, 0775, true)) {
        return array('ok' => false, 'result' => false);
    }
    $lock = avisos_open_shared_lock();
    if (!$lock || !@flock($lock, LOCK_EX)) {
        if ($lock) @fclose($lock);
        avisos_storage_log_failure('no se pudo adquirir lock exclusivo');
        return array('ok' => false, 'result' => false);
    }

    try {
        if (function_exists('storage_invalidate_cache')) storage_invalidate_cache('avisos.json');
        $mode = function_exists('storage_backend_mode') ? storage_backend_mode() : 'json';
        if ($mode === 'json') {
            $readError = '';
            $rows = avisos_read_canonical_json_strict($readError);
            if ($rows === false) {
                avisos_storage_log_failure($readError);
                return array('ok' => false, 'result' => false);
            }
        } else {
            if (!function_exists('storage_mysql_read')) {
                avisos_storage_log_failure('storage_mysql_read no disponible para backend ' . $mode);
                return array('ok' => false, 'result' => false);
            }
            $mysqlRead = storage_mysql_read('avisos.json');
            if (!is_array($mysqlRead) || empty($mysqlRead['ok']) || !isset($mysqlRead['data']) || !is_array($mysqlRead['data'])) {
                avisos_storage_log_failure('falló lectura MySQL canónica para backend ' . $mode);
                return array('ok' => false, 'result' => false);
            }
            // Un resultado vacío válido sigue siendo canónico; no se consulta JSON.
            $rows = $mysqlRead['data'];
        }
        $update = call_user_func($callback, is_array($rows) ? $rows : array());
        if (!is_array($update) || !isset($update['rows']) || !is_array($update['rows'])) {
            return array('ok' => false, 'result' => false);
        }
        if (empty($update['changed'])) {
            return array('ok' => true, 'result' => $update['result'] ?? false);
        }
        if (!avisos_rows_persist_unlocked($update['rows'])) {
            return array('ok' => false, 'result' => false);
        }
        $snapshot = avisos_snapshot_build_from_rows($update['rows'], avisos_canonical_fingerprint());
        if (!avisos_json_write_atomic_unlocked(avisos_snapshot_path(), $snapshot, false)) {
            avisos_storage_log_failure('avisos persistidos, pero falló snapshot');
        }
        return array('ok' => true, 'result' => $update['result'] ?? true);
    } finally {
        @flock($lock, LOCK_UN);
        @fclose($lock);
    }
}

function avisos_write_rows($rows) {
    $replacement = array_values((array)$rows);
    $result = avisos_rows_update_atomic(function ($current) use ($replacement) {
        return array('rows' => $replacement, 'changed' => true, 'result' => true);
    });
    return !empty($result['ok']) && !empty($result['result']);
}

function avisos_active_unread_ids() {
    $ids = array();
    foreach (avisos_get_active() as $row) {
        if (!empty($row['read_at'])) continue;
        $id = trim((string)($row['id'] ?? ''));
        if ($id !== '') $ids[] = $id;
    }
    return $ids;
}

function avisos_active_all_ids() {
    $ids = array();
    foreach (avisos_get_active() as $row) {
        $id = trim((string)($row['id'] ?? ''));
        if ($id !== '') $ids[] = $id;
    }
    return $ids;
}

function aviso_income_movements_all() {
    $rows = array();

    foreach (storage_read('clientes.json') as $row) {
        $rows[] = array(
            'branch' => 'lamami',
            'type' => 'alta',
            'amount' => (float)($row['precio_alta'] ?? 0),
            'date' => $row['fecha_alta'] ?? '',
            'id' => $row['id'] ?? '',
            'label' => $row['nombre'] ?? 'Clienta',
        );
    }

    foreach (storage_read('leads.json') as $row) {
        $rows[] = array(
            'branch' => 'lamami',
            'type' => 'lead',
            'amount' => (float)($row['precio_lead'] ?? 0),
            'date' => $row['fecha_hora'] ?? '',
            'id' => $row['id'] ?? '',
            'label' => $row['cliente_nombre'] ?? 'Lead',
        );
    }

    foreach (storage_read('casawasap_pagos.json') as $row) {
        $rows[] = array(
            'branch' => 'casawasap',
            'type' => 'pago',
            'amount' => (float)($row['importe'] ?? 0),
            'date' => $row['fecha_hora'] ?? '',
            'id' => $row['id'] ?? '',
            'label' => $row['cliente_nombre'] ?? 'Pago',
        );
    }

    foreach (storage_read('jostal_leads.json') as $row) {
        $rows[] = array(
            'branch' => 'jostal',
            'type' => 'lead',
            'amount' => (float)($row['precio'] ?? 0),
            'date' => $row['created_at'] ?? '',
            'id' => $row['id'] ?? '',
            'label' => $row['clienta_nombre'] ?? 'Lead Jostal',
        );
    }

    foreach (storage_read('jostal_ventas.json') as $row) {
        $rows[] = array(
            'branch' => 'jostal',
            'type' => 'venta',
            'amount' => (float)($row['precio'] ?? 0),
            'date' => $row['created_at'] ?? '',
            'id' => $row['id'] ?? '',
            'label' => $row['descripcion'] ?? 'Venta Jostal',
        );
    }

    return $rows;
}

function aviso_month_income_total($monthKey) {
    $sum = 0;
    foreach (aviso_income_movements_all() as $row) {
        $ts = aviso_ts($row['date']);
        if (!$ts) continue;
        if (business_month_key_from_ts($ts) !== $monthKey) continue;
        $sum += (float)$row['amount'];
    }
    return $sum;
}

function aviso_month_expense_total($monthKey) {
    $sum = 0;
    foreach (storage_read('gastos.json') as $row) {
        $ts = aviso_ts($row['created_at'] ?? '');
        if (!$ts) continue;
        if (business_month_key_from_ts($ts) !== $monthKey) continue;
        $sum += (float)($row['cantidad'] ?? 0);
    }
    return $sum;
}

function aviso_previous_3_months_expense_avg($monthKey) {
    $baseTs = strtotime($monthKey . '-01');
    if (!$baseTs) return 0;

    $months = array(
        date('Y-m', strtotime('-1 month', $baseTs)),
        date('Y-m', strtotime('-2 month', $baseTs)),
        date('Y-m', strtotime('-3 month', $baseTs)),
    );

    $sum = 0;
    $count = 0;

    foreach ($months as $m) {
        $sum += aviso_month_expense_total($m);
        $count++;
    }

    return $count > 0 ? ($sum / $count) : 0;
}

function aviso_last_income_ts() {
    $maxTs = 0;
    foreach (aviso_income_movements_all() as $row) {
        $ts = aviso_ts($row['date']);
        if ($ts > $maxTs && ((float)$row['amount']) > 0) $maxTs = $ts;
    }
    return $maxTs;
}

function aviso_day_income_total($dayKey) {
    $sum = 0;
    foreach (aviso_income_movements_all() as $row) {
        $ts = aviso_ts($row['date'] ?? '');
        if (!$ts) continue;
        if (business_day_key_from_ts($ts) !== $dayKey) continue;
        $sum += (float)($row['amount'] ?? 0);
    }
    return $sum;
}

function aviso_day_expense_total($dayKey) {
    $sum = 0;
    foreach (storage_read('gastos.json') as $row) {
        $ts = aviso_ts($row['created_at'] ?? '');
        if (!$ts) continue;
        if (business_day_key_from_ts($ts) !== $dayKey) continue;
        $sum += (float)($row['cantidad'] ?? 0);
    }
    return $sum;
}

function aviso_day_profit_total($dayKey) {
    return aviso_day_income_total($dayKey) - aviso_day_expense_total($dayKey);
}

function aviso_month_branch_income_totals($monthKey) {
    $totals = array(
        'lamami' => 0,
        'casawasap' => 0,
        'jostal' => 0,
    );

    foreach (aviso_income_movements_all() as $row) {
        $ts = aviso_ts($row['date'] ?? '');
        if (!$ts) continue;
        if (business_month_key_from_ts($ts) !== $monthKey) continue;
        $branch = $row['branch'] ?? '';
        if (!isset($totals[$branch])) continue;
        $totals[$branch] += (float)($row['amount'] ?? 0);
    }

    return $totals;
}

function aviso_month_total_days($monthKey) {
    $ts = strtotime($monthKey . '-01');
    return $ts ? (int)date('t', $ts) : 30;
}

function aviso_month_elapsed_days($monthKey) {
    return business_month_elapsed_days($monthKey);
}

function aviso_income_rows_for_day($dayKey) {
    $out = array();
    foreach (aviso_income_movements_all() as $row) {
        $ts = aviso_ts($row['date'] ?? '');
        if (!$ts) continue;
        if (business_day_key_from_ts($ts) !== $dayKey) continue;
        if (((float)($row['amount'] ?? 0)) <= 0) continue;
        $row['ts'] = $ts;
        $out[] = $row;
    }

    usort($out, function ($a, $b) {
        return $a['ts'] <=> $b['ts'];
    });

    return $out;
}

function aviso_all_movements_for_day($dayKey) {
    $out = array();

    foreach (aviso_income_movements_all() as $row) {
        $ts = aviso_ts($row['date'] ?? '');
        if (!$ts) continue;
        if (business_day_key_from_ts($ts) !== $dayKey) continue;
        $out[] = $row;
    }

    foreach (storage_read('gastos.json') as $row) {
        $ts = aviso_ts($row['created_at'] ?? '');
        if (!$ts) continue;
        if (business_day_key_from_ts($ts) !== $dayKey) continue;
        $out[] = array(
            'branch' => 'global',
            'type' => 'gasto',
            'amount' => -1 * (float)($row['cantidad'] ?? 0),
            'date' => $row['created_at'] ?? '',
            'id' => $row['id'] ?? '',
            'label' => $row['descripcion'] ?? 'Gasto',
        );
    }

    return $out;
}

function aviso_bot_memory_file_path($bot) {
    $botName = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)($bot['nombre_bot'] ?? ''));
    return '/srv/n8n_data/session_memory_' . $botName . '.ndjson';
}

function aviso_ts_days_ago($value) {
    $ts = aviso_ts($value);
    if (!$ts) return 0;
    return floor((time() - $ts) / 86400);
}

function aviso_jostal_clienta_last_income_ts($clientaId) {
    $maxTs = 0;

    foreach (storage_read('jostal_leads.json') as $row) {
        if (($row['clienta_id'] ?? '') !== $clientaId) continue;
        $ts = aviso_ts($row['created_at'] ?? '');
        if ($ts > $maxTs) $maxTs = $ts;
    }

    return $maxTs;
}

function aviso_casawasap_cliente_last_pago_ts($clienteId) {
    $maxTs = 0;

    foreach (storage_read('casawasap_pagos.json') as $row) {
        if (($row['cliente_id'] ?? '') !== $clienteId) continue;
        $ts = aviso_ts($row['fecha_hora'] ?? '');
        if ($ts > $maxTs) $maxTs = $ts;
    }

    return $maxTs;
}

function avisos_all() {
    // Evita cache de proceso obsoleta frente a escritores concurrentes.
    if (function_exists('storage_invalidate_cache')) storage_invalidate_cache('avisos.json');
    return storage_read('avisos.json');
}

function avisos_get_active() {
    $snapshot = avisos_snapshot_read();
    if (isset($snapshot['active_rows']) && is_array($snapshot['active_rows'])) {
        return $snapshot['active_rows'];
    }
    return avisos_active_rows_sorted(storage_read('avisos.json'));
}

function avisos_get_planned() {
    $rows = storage_read('avisos.json');
    $out = array();

    foreach ($rows as $row) {
        if (($row['status'] ?? '') === 'planned') {
            $out[] = $row;
        }
    }

    usort($out, function ($a, $b) {
        return strcmp((string)($a['scheduled_for'] ?? ''), (string)($b['scheduled_for'] ?? ''));
    });

    return $out;
}

function avisos_get_history() {
    $rows = storage_read('avisos.json');
    $out = array();

    foreach ($rows as $row) {
        $status = $row['status'] ?? '';
        if (in_array($status, array('dismissed', 'resolved'), true)) {
            $out[] = $row;
        }
    }

    usort($out, function ($a, $b) {
        $aTs = aviso_ts($a['updated_at'] ?? ($a['dismissed_at'] ?? ($a['resolved_at'] ?? $a['created_at'] ?? '')));
        $bTs = aviso_ts($b['updated_at'] ?? ($b['dismissed_at'] ?? ($b['resolved_at'] ?? $b['created_at'] ?? '')));
        return $bTs <=> $aTs;
    });

    return $out;
}

function avisos_create_manual_planned($title, $message, $scheduledFor, $severity = 'media') {
    $now = now_datetime();
    $id = generate_id('aviso');

    $row = array(
        'id' => $id,
        'engine' => 'manual',
        'source_key' => 'manual_planned_' . $id,
        'title' => trim((string)$title),
        'message' => trim((string)$message),
        'severity' => aviso_normalize_severity($severity),
        'meta' => array(
            'origin' => 'manual_planned',
        ),
        'status' => 'planned',
        'auto_resolve' => false,
        'scheduled_for' => trim((string)$scheduledFor),
        'created_at' => $now,
        'updated_at' => $now,
        'last_seen_at' => '',
        'last_run_id' => '',
        'last_eval_action' => 'planned_created',
        'occurrences' => 0,
        'read_at' => '',
        'dismissed_at' => '',
        'resolved_at' => '',
        'activated_at' => '',
        'whatsapp_sent_at' => '',
        'whatsapp_last_attempt_at' => '',
        'whatsapp_last_result' => '',
        'whatsapp_last_error' => '',
        'whatsapp_last_log' => array(),
    );

    $result = avisos_rows_update_atomic(function ($rows) use ($row, $id) {
        $rows[] = $row;
        return array('rows' => $rows, 'changed' => true, 'result' => $id);
    });
    return !empty($result['ok']) ? $result['result'] : false;
}

function avisos_create_active($title, $message, $severity = 'media', $engine = 'manual', $meta = array(), $sendWhatsapp = false, $sourceKey = '') {
    $now = now_datetime();
    $id = generate_id('aviso');
    $sourceKey = trim((string)$sourceKey);
    if ($sourceKey === '') {
        $sourceKey = trim((string)$engine) . '_active_' . $id;
    }

    $row = array(
        'id' => $id,
        'engine' => trim((string)$engine),
        'source_key' => $sourceKey,
        'title' => trim((string)$title),
        'message' => trim((string)$message),
        'severity' => aviso_normalize_severity($severity),
        'meta' => is_array($meta) ? $meta : array(),
        'status' => 'active',
        'auto_resolve' => false,
        'scheduled_for' => '',
        'created_at' => $now,
        'updated_at' => $now,
        'last_seen_at' => $now,
        'last_run_id' => '',
        'last_eval_action' => 'created_manual_active',
        'occurrences' => 1,
        'read_at' => '',
        'dismissed_at' => '',
        'resolved_at' => '',
        'activated_at' => $now,
        'whatsapp_sent_at' => '',
        'whatsapp_last_attempt_at' => '',
        'whatsapp_last_result' => '',
        'whatsapp_last_error' => '',
        'whatsapp_last_log' => array(),
    );

    $persisted = avisos_rows_update_atomic(function ($rows) use ($row, $id) {
        $rows[] = $row;
        return array('rows' => $rows, 'changed' => true, 'result' => $id);
    });
    if (empty($persisted['ok']) || ($persisted['result'] ?? false) !== $id) {
        return false;
    }

    if ($sendWhatsapp) {
        avisos_send_and_store_result($row, $now);
    }

    return $id;
}

function avisos_store_whatsapp_result_atomic($id, $row) {
    $claimToken = trim((string)($row['whatsapp_delivery_claim']['token'] ?? ''));
    return avisos_rows_update_atomic(function ($rows) use ($id, $row, $claimToken) {
        foreach ($rows as $index => $current) {
            if (($current['id'] ?? '') !== $id) continue;
            if ($claimToken === '' || trim((string)($current['whatsapp_delivery_claim']['token'] ?? '')) !== $claimToken) {
                return array('rows' => $rows, 'changed' => false, 'result' => false);
            }
            $rows[$index] = array_merge($current, array(
                'whatsapp_sent_at' => $row['whatsapp_sent_at'],
                'whatsapp_last_attempt_at' => $row['whatsapp_last_attempt_at'],
                'whatsapp_last_result' => $row['whatsapp_last_result'],
                'whatsapp_last_error' => $row['whatsapp_last_error'],
                'whatsapp_last_log' => $row['whatsapp_last_log'],
                'whatsapp_delivery_claim' => array(),
            ));
            return array('rows' => $rows, 'changed' => true, 'result' => true);
        }
        return array('rows' => $rows, 'changed' => false, 'result' => false);
    });
}

function avisos_claim_whatsapp_delivery($id, $now = '') {
    $id = trim((string)$id);
    if ($id === '') return false;
    $now = trim((string)$now);
    if ($now === '') $now = now_datetime();
    $token = sha1($id . '|' . uniqid((string)getmypid(), true));
    $result = avisos_rows_update_atomic(function ($rows) use ($id, $now, $token) {
        foreach ($rows as $index => $row) {
            if (($row['id'] ?? '') !== $id) continue;
            if (trim((string)($row['status'] ?? '')) !== 'active') {
                return array('rows' => $rows, 'changed' => false, 'result' => false);
            }
            if (trim((string)($row['whatsapp_sent_at'] ?? '')) !== '') {
                return array('rows' => $rows, 'changed' => false, 'result' => false);
            }
            if (in_array(trim((string)($row['whatsapp_last_result'] ?? '')), array('sent', 'partial'), true)) {
                return array('rows' => $rows, 'changed' => false, 'result' => false);
            }
            // Un claim persistido sin resultado es ambiguo: at-most-once, no se roba.
            if (!empty($row['whatsapp_delivery_claim'])) {
                return array('rows' => $rows, 'changed' => false, 'result' => false);
            }
            $rows[$index]['whatsapp_delivery_claim'] = array(
                'token' => $token,
                'claimed_at' => $now,
                'state' => 'in_flight',
            );
            return array('rows' => $rows, 'changed' => true, 'result' => $rows[$index]);
        }
        return array('rows' => $rows, 'changed' => false, 'result' => false);
    });
    return !empty($result['ok']) && is_array($result['result']) ? $result['result'] : false;
}

function avisos_send_and_store_result($row, $now = '') {
    $now = trim((string)$now);
    if ($now === '') $now = now_datetime();
    $claimedRow = avisos_claim_whatsapp_delivery((string)($row['id'] ?? ''), $now);
    if (!$claimedRow) {
        return array('ok' => false, 'status' => 'skipped_claimed', 'attempted_at' => $now, 'phones' => array(), 'error' => 'Entrega ya enviada o en estado ambiguo.');
    }
    $row = $claimedRow;
    try {
        $sendResult = aviso_send_whatsapp($row);
    } catch (Throwable $e) {
        $sendResult = array(
            'ok' => false,
            'status' => 'error',
            'attempted_at' => $now,
            'phones' => array(),
            'error' => 'Excepción enviando WhatsApp: ' . $e->getMessage(),
        );
    }
    $updated = aviso_apply_whatsapp_send_result($row, $sendResult, $now);
    avisos_store_whatsapp_result_atomic((string)($row['id'] ?? ''), $updated);
    return $sendResult;
}

function avisos_delete_planned($id) {
    avisos_rows_update_atomic(function ($rows) use ($id) {
        $out = array();
        $changed = false;
        foreach ($rows as $row) {
            if (($row['id'] ?? '') === $id && ($row['status'] ?? '') === 'planned') {
                $changed = true;
                continue;
            }
            $out[] = $row;
        }
        return array('rows' => $out, 'changed' => $changed, 'result' => $changed);
    });
}

function avisos_activate_planned_manuals($sendWhatsapp = true) {
    $now = now_datetime();
    $nowTs = time();
    $toSend = array();
    $result = avisos_rows_update_atomic(function ($rows) use ($now, $nowTs, $sendWhatsapp, &$toSend) {
        $activated = 0;
        foreach ($rows as $index => $row) {
            if (($row['status'] ?? '') !== 'planned') continue;
            $scheduledTs = aviso_ts($row['scheduled_for'] ?? '');
            if (!$scheduledTs || $scheduledTs > $nowTs) continue;
            $rows[$index]['status'] = 'active';
            $rows[$index]['activated_at'] = $now;
            $rows[$index]['created_at'] = $now;
            $rows[$index]['updated_at'] = $now;
            $rows[$index]['last_seen_at'] = $now;
            $rows[$index]['last_eval_action'] = 'activated_from_planned';
            $rows[$index]['occurrences'] = max(1, (int)($row['occurrences'] ?? 0));
            if ($sendWhatsapp) $toSend[] = $rows[$index];
            $activated++;
        }
        return array('rows' => $rows, 'changed' => $activated > 0, 'result' => $activated);
    });
    $activated = !empty($result['ok']) ? (int)$result['result'] : 0;
    if (!empty($result['ok'])) {
        foreach ($toSend as $row) {
            avisos_send_and_store_result($row, $now);
        }
    }
    return $activated;
}

function avisos_mark_as_read($ids) {
    if (empty($ids)) return;
    avisos_rows_update_atomic(function ($rows) use ($ids) {
        $changed = false;
        foreach ($rows as $index => $row) {
            if (!in_array($row['id'] ?? '', $ids, true) || ($row['status'] ?? '') !== 'active' || !empty($row['read_at'])) continue;
            $now = now_datetime();
            $rows[$index]['read_at'] = $now;
            $rows[$index]['updated_at'] = $now;
            $changed = true;
        }
        return array('rows' => $rows, 'changed' => $changed, 'result' => $changed);
    });
}

function avisos_mark_as_read_and_dismiss($ids) {
    if (empty($ids)) return;

    avisos_rows_update_atomic(function ($rows) use ($ids) {
        $changed = false;
        foreach ($rows as $index => $row) {
            if (!in_array($row['id'] ?? '', $ids, true) || ($row['status'] ?? '') !== 'active') continue;
            $now = now_datetime();
            if (empty($row['read_at'])) $rows[$index]['read_at'] = $now;
            $rows[$index]['status'] = 'dismissed';
            $rows[$index]['dismissed_at'] = $now;
            $rows[$index]['updated_at'] = $now;
            $changed = true;
        }
        return array('rows' => $rows, 'changed' => $changed, 'result' => $changed);
    });
}

function aviso_dismiss($id) {
    $id = trim((string)$id);
    if ($id === '') return false;
    $result = avisos_rows_update_atomic(function ($rows) use ($id) {
        foreach ($rows as $index => $row) {
            if (($row['id'] ?? '') !== $id) continue;
            $now = now_datetime();
            $rows[$index]['status'] = 'dismissed';
            $rows[$index]['dismissed_at'] = $now;
            $rows[$index]['updated_at'] = $now;
            return array('rows' => $rows, 'changed' => true, 'result' => true);
        }
        return array('rows' => $rows, 'changed' => false, 'result' => false);
    });
    return !empty($result['ok']) && !empty($result['result']);
}

function avisos_dismiss_destacamos_publish_reminders() {
    $now = now_datetime();
    $result = avisos_rows_update_atomic(function ($rows) use ($now) {
        $changed = 0;
        foreach ($rows as $index => $row) {
            $engine = trim((string)($row['engine'] ?? ''));
            $sourceKey = trim((string)($row['source_key'] ?? ''));
            $kind = trim((string)($row['meta']['kind'] ?? ''));
            $status = trim((string)($row['status'] ?? ''));
            if (!in_array($status, array('active', 'planned'), true)) continue;
            if ($kind !== 'destacamos_publish' && strpos($sourceKey, 'destacamos_publish_') !== 0) continue;
            $rows[$index]['status'] = 'dismissed';
            $rows[$index]['dismissed_at'] = $now;
            $rows[$index]['updated_at'] = $now;
            $rows[$index]['last_eval_action'] = 'dismissed_by_publicista_free_bump';
            $rows[$index]['engine'] = $engine !== '' ? $engine : 'recurring';
            $changed++;
        }
        return array('rows' => $rows, 'changed' => $changed > 0, 'result' => $changed);
    });
    return !empty($result['ok']) ? (int)$result['result'] : 0;
}
function aviso_safe_substr($text, $start, $length) {
    if (!is_string($text)) {
        return '';
    }

    if (function_exists('mb_substr')) {
        return mb_substr($text, $start, $length);
    }

    return substr($text, $start, $length);
}

/**
 * Reescribe un texto de aviso con sinónimos (como si lo escribiera otra persona)
 * conservando los datos clave. BEST-EFFORT: si el LLM falla, devuelve el original.
 * Se usa para que el 2º destinatario (secondary) no reciba exactamente el mismo
 * texto que el primero (antiban de WhatsApp).
 */
function avisos_llm_paraphrase($text) {
    $text = trim((string)$text);
    if ($text === '') {
        return '';
    }

    $llmKey = function_exists('aviso_llm_api_key_for_deepseek') ? aviso_llm_api_key_for_deepseek() : '';
    if ($llmKey === '') {
        return $text;
    }

    $prompt = "Reescribe el siguiente aviso cambiando la redacción y usando sinónimos, "
        . "como si lo hubiera escrito otra persona distinta.\n"
        . "Reglas:\n"
        . "- Conserva EXACTAMENTE los mismos datos: cifras, importes, nombres, teléfonos, fechas, enlaces y códigos.\n"
        . "- Conserva los emojis relevantes (puedes añadir alguno si aporta naturalidad).\n"
        . "- No cambies el significado ni la longitud aproximada.\n"
        . "- Devuelve SOLO el texto reescrito, sin comillas, sin prefijos ni explicaciones.\n\n"
        . "AVISO:\n" . $text;

    $payload = array(
        'model' => 'deepseek-v4-flash',
        'messages' => array(array('role' => 'user', 'content' => $prompt)),
        'temperature' => 0.7,
        'max_tokens' => 2048,
        'thinking' => array('type' => 'disabled'),
    );

    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ch = curl_init('https://api.deepseek.com/chat/completions');
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $llmKey,
            'Content-Type: application/json',
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ));
    $raw = @curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $httpCode < 200 || $httpCode >= 300) {
        return $text;
    }

    $resp = json_decode((string)$raw, true);
    $content = is_array($resp) ? trim((string)($resp['choices'][0]['message']['content'] ?? '')) : '';
    if ($content === '') {
        return $text;
    }

    return $content;
}

function aviso_send_whatsapp($aviso) {
    if (!aviso_whatsapp_allowed_for_aviso($aviso)) {
        return array(
            'ok' => true,
            'status' => 'skipped_by_profile',
            'attempted_at' => now_datetime(),
            'phones' => array(),
            'error' => '',
            'sender' => array('profile' => aviso_noise_profile()),
        );
    }

    $phones = aviso_pending_target_phones($aviso);
    $senderLines = avisos_comercial_sender_line_candidates();
    $senderLines = aviso_sender_line_candidates_for_aviso($aviso, $senderLines);
    $retryRounds = max(1, (int)avisos_sender_retry_rounds());

    // Dual-send: el primary recibe el texto original y cada secondary recibe el
    // mismo aviso parafraseado por LLM, con una espera de 15-25s entre destinatarios.
    $ownerPhones = avisos_owner_notification_phones();
    $secondaryByPhone = array_flip($ownerPhones['secondary']);
    $paraphrasedText = null;

    // El secondary garantizado (641993776) recibe el aviso aunque en la config
    // solo esté el primary. No se reenvía si ya consta enviado con éxito.
    if (trim((string)($aviso['whatsapp_last_result'] ?? '')) !== 'sent') {
        foreach ($ownerPhones['secondary'] as $sp) {
            if ($sp === '' || in_array($sp, $phones, true)) continue;
            $alreadyOk = false;
            foreach ((array)($aviso['whatsapp_last_log']['phones'] ?? array()) as $pRow) {
                if (trim((string)($pRow['phone'] ?? '')) === $sp && !empty($pRow['ok'])) {
                    $alreadyOk = true;
                    break;
                }
            }
            if (!$alreadyOk) {
                $phones[] = $sp;
            }
        }
    }

    $result = array(
        'ok' => false,
        'status' => 'not_attempted',
        'attempted_at' => now_datetime(),
        'phones' => array(),
        'error' => '',
        'sender' => array(),
    );

    if (empty($phones)) {
        $result['ok'] = true;
        $result['status'] = 'sent';
        $result['error'] = '';
        return $result;
    }

    if (empty($senderLines)) {
        $result['status'] = 'error';
        $result['error'] = 'No hay líneas activas de Comercial disponibles para enviar avisos';
        return $result;
    }

    $severity = aviso_normalize_severity($aviso['severity'] ?? 'media');
    $emoji = aviso_severity_emoji($severity);
    $title = trim((string)($aviso['title'] ?? 'Aviso'));
    $message = trim((string)($aviso['message'] ?? ''));

    $titleUpper = function_exists('mb_strtoupper')
        ? mb_strtoupper($title, 'UTF-8')
        : strtoupper($title);

    $text = $emoji . ' ' . $titleUpper;
    if ($message !== '') {
        $text .= "\n" . $message;
    }

    $okCount = 0;
    $errors = array();
    $processMeta = array(
        'id' => 'avisos',
        'slug' => 'avisos',
        'nombre' => 'Avisos',
    );
    $usedLineIds = array();

    foreach ($phones as $phone) {
        $phone = trim((string)$phone);
        $send = null;
        $successfulLine = null;
        $lineAttempts = array();
        $attemptCounter = 0;

        $phoneText = $text;
        if (isset($secondaryByPhone[$phone])) {
            if ($paraphrasedText === null) {
                $paraphrasedText = avisos_llm_paraphrase($text);
            }
            $phoneText = $paraphrasedText;
        }

        // Antiban: si en esta misma llamada ya se envió a otro destinatario,
        // esperar un intervalo aleatorio de 15-25s antes del siguiente envío.
        if (count($result['phones']) > 0) {
            sleep(rand(15, 25));
        }

        for ($round = 1; $round <= $retryRounds; $round++) {
            foreach ($senderLines as $senderLine) {
                $attemptCounter++;
                $send = comercial_send_text_via_line($senderLine, $phone, $phoneText, $processMeta);
                $lineAttempt = array(
                    'line_id' => (string)($senderLine['id'] ?? ''),
                    'line_name' => (string)($senderLine['nombre'] ?? ''),
                    'line_phone' => comercial_only_digits((string)($senderLine['tfono'] ?? '')),
                    'port' => trim((string)($senderLine['waha_port'] ?? '')),
                    'ok' => !empty($send['ok']),
                    'http_code' => (int)($send['http_code'] ?? 0),
                    'error' => trim((string)($send['error'] ?? '')),
                    'round' => $round,
                    'attempt' => $attemptCounter,
                );
                $lineAttempts[] = $lineAttempt;

                if (!empty($send['ok'])) {
                    $successfulLine = $senderLine;
                    $usedLineIds[(string)($senderLine['id'] ?? '')] = $lineAttempt;
                    break 2;
                }
            }
        }

        if ($successfulLine === null && !empty($lineAttempts)) {
            $lastAttempt = $lineAttempts[count($lineAttempts) - 1];
            $successfulLine = array(
                'id' => (string)($lastAttempt['line_id'] ?? ''),
                'nombre' => (string)($lastAttempt['line_name'] ?? ''),
                'tfono' => (string)($lastAttempt['line_phone'] ?? ''),
                'waha_port' => (string)($lastAttempt['port'] ?? ''),
            );
        }

        $phoneResult = array(
            'phone' => $phone,
            'chat_id' => comercial_to_chat_id(comercial_normalize_phone_spain($phone)),
            'http_code' => (int)($send['http_code'] ?? 0),
            'ok' => !empty($send['ok']),
            'error' => trim((string)($send['error'] ?? '')),
            'response' => is_string($send['sendText']['body'] ?? null) ? aviso_safe_substr($send['sendText']['body'], 0, 500) : '',
            'line_id' => (string)($successfulLine['id'] ?? ''),
            'line_name' => (string)($successfulLine['nombre'] ?? ''),
            'line_phone' => comercial_only_digits((string)($successfulLine['tfono'] ?? '')),
            'line_attempts' => $lineAttempts,
            'attempt_count' => $attemptCounter,
        );

        if ($phoneResult['ok']) {
            $okCount++;
        } else {
            $errors[] = $phone . ': ' . ($phoneResult['error'] !== '' ? $phoneResult['error'] : ('HTTP ' . $phoneResult['http_code']));
        }

        $result['phones'][] = $phoneResult;
    }

    $result['ok'] = $okCount > 0;
    $result['status'] = $okCount === count($phones) ? 'sent' : ($okCount > 0 ? 'partial' : 'error');
    $result['error'] = implode(' | ', $errors);
    $result['sender'] = array(
        'type' => 'comercial_deterministic_by_aviso_type',
        'profile' => aviso_noise_profile(),
        'route_key' => aviso_type_key($aviso),
        'attempted_count' => count($senderLines),
        'retry_rounds' => $retryRounds,
        'used_lines' => array_values($usedLineIds),
    );

    return $result;
}

function avisos_sync_generated($generated, $engine = 'general', $sendWhatsapp = true, $runId = '') {
    $generated = is_array($generated) ? $generated : array();
    $generatedByKey = array();
    $stats = array(
        'engine' => $engine,
        'generated' => 0,
        'created' => 0,
        'reactivated' => 0,
        'updated' => 0,
        'resolved' => 0,
        'dismissed_persistent' => 0,
        'whatsapp_sent' => 0,
        'whatsapp_failed' => 0,
    );

    foreach ($generated as $item) {
        $sourceKey = trim((string)($item['source_key'] ?? ''));
        if ($sourceKey === '') continue;
        $generatedByKey[$sourceKey] = $item;
    }

    $stats['generated'] = count($generatedByKey);
    $now = now_datetime();
    $toSend = array();
    $persisted = avisos_rows_update_atomic(function ($rows) use ($generatedByKey, $engine, $runId, $now, $sendWhatsapp, &$stats, &$toSend) {
        $changed = false;
        foreach ($generatedByKey as $sourceKey => $item) {
            $foundIndex = null;
            foreach ($rows as $i => $row) {
                if (($row['engine'] ?? '') === $engine && ($row['source_key'] ?? '') === $sourceKey) {
                    $foundIndex = $i;
                    break;
                }
            }
            if ($foundIndex === null) {
                $newRow = array(
                    'id' => generate_id('aviso'), 'engine' => $engine, 'source_key' => $sourceKey,
                    'title' => $item['title'] ?? 'Aviso', 'message' => $item['message'] ?? '',
                    'severity' => aviso_normalize_severity($item['severity'] ?? 'media'),
                    'meta' => $item['meta'] ?? array(), 'status' => 'active',
                    'auto_resolve' => !empty($item['auto_resolve']), 'created_at' => $now,
                    'updated_at' => $now, 'last_seen_at' => $now, 'last_run_id' => $runId,
                    'last_eval_action' => 'created', 'occurrences' => 1, 'read_at' => '',
                    'dismissed_at' => '', 'resolved_at' => '', 'whatsapp_sent_at' => '',
                    'whatsapp_last_attempt_at' => '', 'whatsapp_last_result' => '',
                    'whatsapp_last_error' => '', 'whatsapp_last_log' => array(),
                );
                $rows[] = $newRow;
                if ($sendWhatsapp) $toSend[] = $newRow;
                $stats['created']++;
                $changed = true;
                continue;
            }
            $existing = $rows[$foundIndex];
            $oldStatus = $existing['status'] ?? 'active';
            $existing['title'] = $item['title'] ?? ($existing['title'] ?? 'Aviso');
            $existing['message'] = $item['message'] ?? ($existing['message'] ?? '');
            $existing['severity'] = aviso_normalize_severity($item['severity'] ?? ($existing['severity'] ?? 'media'));
            $existing['meta'] = $item['meta'] ?? ($existing['meta'] ?? array());
            $existing['auto_resolve'] = !empty($item['auto_resolve']);
            $existing['updated_at'] = $now;
            $existing['last_seen_at'] = $now;
            $existing['last_run_id'] = $runId;
            $existing['last_eval_action'] = 'updated';
            if ($oldStatus === 'resolved') {
                $existing['status'] = 'active';
                $existing['read_at'] = '';
                $existing['dismissed_at'] = '';
                $existing['resolved_at'] = '';
                $existing['occurrences'] = (int)($existing['occurrences'] ?? 1) + 1;
                $existing['last_eval_action'] = 'reactivated';
                $stats['reactivated']++;
                if ($sendWhatsapp) $toSend[] = $existing;
            } elseif ($oldStatus === 'dismissed') {
                $existing['last_eval_action'] = 'still_dismissed';
                $stats['dismissed_persistent']++;
            } else {
                $stats['updated']++;
                if ($sendWhatsapp && aviso_noise_profile() !== 'agresivo' && aviso_whatsapp_should_retry($existing)) {
                    $toSend[] = $existing;
                }
            }
            $rows[$foundIndex] = $existing;
            $changed = true;
        }
        foreach ($rows as $index => $row) {
            if (($row['engine'] ?? '') !== $engine) continue;
            if (!in_array(($row['status'] ?? ''), array('active', 'dismissed'), true)) continue;
            if (empty($row['auto_resolve'])) continue;
            $sourceKey = $row['source_key'] ?? '';
            if ($sourceKey === '' || isset($generatedByKey[$sourceKey])) continue;
            $rows[$index]['status'] = 'resolved';
            $rows[$index]['resolved_at'] = $now;
            $rows[$index]['updated_at'] = $now;
            $rows[$index]['last_seen_at'] = $now;
            $rows[$index]['last_run_id'] = $runId;
            $rows[$index]['last_eval_action'] = 'auto_resolved';
            $stats['resolved']++;
            $changed = true;
        }
        return array('rows' => $rows, 'changed' => $changed, 'result' => true);
    });
    if (!empty($persisted['ok'])) {
        foreach ($toSend as $row) {
            $sendResult = avisos_send_and_store_result($row, $now);
            if (aviso_whatsapp_count_as_sent($sendResult)) $stats['whatsapp_sent']++;
            elseif (aviso_whatsapp_count_as_failed($sendResult)) $stats['whatsapp_failed']++;
        }
    }
    aviso_cli_log('[' . $engine . '] ' . aviso_build_run_summary($stats));

    return $stats;
}

function avisos_retry_pending_whatsapp() {
    $now = now_datetime();
    $sent = 0;
    $failed = 0;
    $checked = 0;
    $snapshot = avisos_rows_update_atomic(function ($rows) {
        return array('rows' => $rows, 'changed' => false, 'result' => $rows);
    });
    $rows = !empty($snapshot['ok']) && is_array($snapshot['result']) ? $snapshot['result'] : array();
    foreach ($rows as $row) {
        if (!aviso_whatsapp_should_retry($row)) continue;
        $checked++;
        $sendResult = avisos_send_and_store_result($row, $now);
        if (!empty($sendResult['ok'])) $sent++;
        else $failed++;
    }

    return array(
        'engine' => 'pending_retry',
        'generated' => 0,
        'created' => 0,
        'reactivated' => 0,
        'updated' => 0,
        'resolved' => 0,
        'dismissed_persistent' => 0,
        'whatsapp_sent' => $sent,
        'whatsapp_failed' => $failed,
        'checked' => $checked,
    );
}

function avisos_generate_after_10am() {
    $generated = array();
    $hour = (int)date('G');

    if ($hour >= 10) {
        $todayKey = business_today_date();

        $generated[] = aviso_make(
            'hora',
            'after_10am_' . $todayKey,
            'Ya son más de las 10:00',
            'Aviso de prueba del motor: ya ha pasado la hora de las 10:00 de hoy (' . date('d/m/Y') . ').',
            'media',
            array(
                'rule' => 'after_10am',
                'day' => $todayKey,
            ),
            true
        );
    }

    return $generated;
}

function avisos_generate_month_income_milestones() {
    $generated = array();
    $monthKey = business_current_month_key();
    $total = aviso_month_income_total($monthKey);

    $stepValue = (float)aviso_cfg('income_milestone_step', 1000);
    if ($stepValue <= 0) return $generated;

    if ($total >= $stepValue) {
        $step = floor($total / $stepValue);
        for ($i = 1; $i <= $step; $i++) {
            $target = $i * $stepValue;
            $sourceKey = 'income_milestone_' . $monthKey . '_' . $target;
            if (aviso_exists_any_status('milestones', $sourceKey)) continue;

            $generated[] = aviso_make(
                'milestones',
                $sourceKey,
                'Ingresos del mes superan ' . $target . '€',
                'El total de ingresos del mes actual ya ha superado los ' . $target . '€. Total actual: ' . euro($total) . '.',
                'alta',
                array('month' => $monthKey, 'target' => $target, 'kind' => 'income'),
                false
            );
        }
    }

    return $generated;
}

function avisos_generate_month_profit_milestones() {
    $generated = array();
    $monthKey = business_current_month_key();
    $income = aviso_month_income_total($monthKey);
    $expense = aviso_month_expense_total($monthKey);
    $profit = $income - $expense;

    $stepValue = (float)aviso_cfg('profit_milestone_step', 500);
    if ($stepValue <= 0) return $generated;

    if ($profit >= $stepValue) {
        $step = floor($profit / $stepValue);
        for ($i = 1; $i <= $step; $i++) {
            $target = $i * $stepValue;
            $sourceKey = 'profit_milestone_' . $monthKey . '_' . $target;
            if (aviso_exists_any_status('milestones', $sourceKey)) continue;

            $generated[] = aviso_make(
                'milestones',
                $sourceKey,
                'Beneficio del mes supera ' . $target . '€',
                'El beneficio real del mes actual ya ha superado los ' . $target . '€. Beneficio actual: ' . euro($profit) . '.',
                'alta',
                array('month' => $monthKey, 'target' => $target, 'kind' => 'profit'),
                false
            );
        }
    }

    return $generated;
}

function avisos_generate_month_profit_starts() {
    $generated = array();
    $monthKey = business_current_month_key();
    $income = aviso_month_income_total($monthKey);
    $avgPrevExpenses = aviso_previous_3_months_expense_avg($monthKey);

    if ($avgPrevExpenses <= 0) {
        return $generated;
    }

    if ($income > $avgPrevExpenses) {
        $sourceKey = 'profit_starts_' . $monthKey;

        if (!aviso_exists_any_status('milestones', $sourceKey)) {
            $generated[] = aviso_make(
                'milestones',
                $sourceKey,
                'Empieza el beneficio del mes',
                'Los ingresos del mes actual ya han superado la media de gastos de los 3 meses anteriores (' . euro($avgPrevExpenses) . '). A partir de aquí, en términos globales, ya estarías por encima de ese umbral.',
                'media',
                array(
                    'month' => $monthKey,
                    'income' => $income,
                    'avg_prev_expenses' => $avgPrevExpenses,
                    'kind' => 'profit_starts',
                ),
                false
            );
        }
    }

    return $generated;
}

function avisos_generate_unattended_interesadas_6h() {
    $generated = array();
    $now = time();
    $hours = (int)aviso_cfg('unattended_interesada_hours', 6);
    $limit = $hours * 3600;

    foreach (storage_read('interesadas.json') as $row) {
        if (($row['estado'] ?? '') !== 'nueva') continue;
        $ts = aviso_ts($row['created_at'] ?? '');
        if (!$ts || ($now - $ts) < $limit) continue;

        $generated[] = aviso_make(
            'attention',
            'lamami_unattended_' . ($row['id'] ?? ''),
            'Interesada nueva de LaMami sin atender',
            'La interesada ' . ($row['telefono'] ?? 'sin teléfono') . ' lleva más de ' . $hours . ' horas en estado nueva sin ser atendida.',
            'media',
            array('branch' => 'lamami', 'id' => $row['id'] ?? '', 'kind' => 'unattended'),
            true
        );
    }

    foreach (storage_read('jostal_interesadas.json') as $row) {
        if (($row['estado'] ?? '') !== 'nueva') continue;
        $ts = aviso_ts($row['created_at'] ?? '');
        if (!$ts || ($now - $ts) < $limit) continue;

        $generated[] = aviso_make(
            'attention',
            'jostal_unattended_' . ($row['id'] ?? ''),
            'Interesada nueva de Jostal sin atender',
            'La interesada Jostal ' . ($row['telefono'] ?? 'sin teléfono') . ' lleva más de ' . $hours . ' horas sin convertirse ni descartarse.',
            'media',
            array('branch' => 'jostal', 'id' => $row['id'] ?? '', 'kind' => 'unattended'),
            true
        );
    }

    foreach (storage_read('casawasap_contactos.json') as $row) {
        if (($row['estado'] ?? '') !== 'interesado') continue;
        $ts = aviso_ts($row['created_at'] ?? '');
        if (!$ts || ($now - $ts) < $limit) continue;

        $generated[] = aviso_make(
            'attention',
            'casawasap_unattended_' . ($row['id'] ?? ''),
            'Interesado de Casawasap sin atender',
            'El interesado Casawasap ' . ($row['telefono'] ?? 'sin teléfono') . ' lleva más de ' . $hours . ' horas sin convertirse ni descartarse.',
            'media',
            array('branch' => 'casawasap', 'id' => $row['id'] ?? '', 'kind' => 'unattended'),
            true
        );
    }

    return $generated;
}

function avisos_generate_lamami_atendidas_24h() {
    $generated = array();
    $now = time();
    $hours = (int)aviso_cfg('lamami_attended_without_convert_hours', 24);
    $limit = $hours * 3600;

    foreach (storage_read('interesadas.json') as $row) {
        if (($row['estado'] ?? '') !== 'atendida') continue;
        $ts = aviso_ts($row['updated_at'] ?? ($row['created_at'] ?? ''));
        if (!$ts || ($now - $ts) < $limit) continue;

        $generated[] = aviso_make(
            'attention',
            'lamami_atendida_' . $hours . 'h_' . ($row['id'] ?? ''),
            'Interesada atendida de LaMami sin convertir tras ' . $hours . 'h',
            'La interesada ' . ($row['telefono'] ?? 'sin teléfono') . ' lleva más de ' . $hours . ' horas en estado atendida sin convertirse.',
            'media',
            array('branch' => 'lamami', 'id' => $row['id'] ?? '', 'kind' => 'attended_' . $hours . 'h'),
            true
        );
    }

    return $generated;
}

function avisos_generate_no_income_48h() {
    $generated = array();
    $lastTs = aviso_last_income_ts();
    if (!$lastTs) return $generated;

    $now = time();
    $hoursLimit = (int)aviso_cfg('no_income_hours_2', 48);
    $secondsLimit = $hoursLimit * 3600;

    if (($now - $lastTs) < $secondsLimit) return $generated;

    $hoursWithout = floor(($now - $lastTs) / 3600);
    $slot = floor(($now - $lastTs) / $secondsLimit);
    $sourceKey = 'no_income_' . $hoursLimit . 'h_slot_' . $slot;

    $generated[] = aviso_make(
        'inactivity',
        $sourceKey,
        'Más de ' . $hoursLimit . 'h sin ingresos',
        'No se registra ningún ingreso en ninguna rama desde hace ' . $hoursWithout . ' horas. Último ingreso: ' . date('d/m/Y H:i', $lastTs) . '.',
        'alta',
        array('hours_without' => $hoursWithout, 'last_income_at' => date('Y-m-d H:i:s', $lastTs)),
        true
    );

    return $generated;
}

function avisos_generate_first_income_of_day() {
    $generated = array();
    $todayKey = business_today_date();
    $rows = aviso_income_rows_for_day($todayKey);

    if (empty($rows)) return $generated;
    if (aviso_exists_any_status('events', 'first_income_' . $todayKey)) return $generated;

    $first = $rows[0];
    $generated[] = aviso_make(
        'events',
        'first_income_' . $todayKey,
        'Primer ingreso del día',
        'El primer ingreso del día ha sido en ' . strtoupper($first['branch'] ?? 'sistema') . ' por ' . euro($first['amount'] ?? 0) . ' (' . ($first['label'] ?? 'movimiento') . ').',
        'media',
        array('day' => $todayKey, 'branch' => $first['branch'] ?? '', 'id' => $first['id'] ?? ''),
        false
    );

    return $generated;
}

function avisos_generate_full_day_without_movements() {
    $generated = array();
    $yesterdayKey = date('Y-m-d', strtotime(business_today_date() . ' -1 day'));

    if (!empty(aviso_all_movements_for_day($yesterdayKey))) {
        return $generated;
    }

    if (aviso_exists_any_status('inactivity', 'no_movements_day_' . $yesterdayKey)) {
        return $generated;
    }

    $generated[] = aviso_make(
        'inactivity',
        'no_movements_day_' . $yesterdayKey,
        'Día completo sin movimientos',
        'No se registró ningún movimiento en todo el día ' . date('d/m/Y', strtotime($yesterdayKey)) . '.',
        'alta',
        array('day' => $yesterdayKey),
        false
    );

    return $generated;
}

function avisos_generate_lamami_publicidad_due_today() {
    $generated = array();
    $todayKey = business_today_date();
    $todayTs = strtotime($todayKey);

    $cycleDays = (int)aviso_cfg('weekly_cycle_days', 7);

    foreach (storage_read('clientes.json') as $row) {
        if (($row['estado'] ?? '') !== 'alta') continue;

        $anchorTs = aviso_ts($row['fecha_alta'] ?? '');
        if (!$anchorTs) continue;

        $days = floor(($todayTs - strtotime(date('Y-m-d', $anchorTs))) / 86400);
        if ($days < $cycleDays) continue;
        if (($days % $cycleDays) !== 0) continue;

        $weeks = (int)($days / $cycleDays);

        $generated[] = aviso_make(
            'recurring',
            'lamami_publicidad_due_today_' . ($row['id'] ?? '') . '_' . $todayKey,
            'Renovación de publicidad vence hoy',
            'La clienta "' . ($row['nombre'] ?? 'Clienta') . '" cumple hoy ' . $weeks . ' semana(s) desde el alta y debería renovar hoy su publicidad de 29€.',
            'alta',
            array('branch' => 'lamami', 'id' => $row['id'] ?? '', 'week' => $weeks, 'day' => $todayKey),
            true
        );
    }

    return $generated;
}

function avisos_generate_casawasap_alquiler_due_today() {
    $generated = array();
    $todayKey = business_today_date();
    $todayTs = strtotime($todayKey);
    $cycleDays = (int)aviso_cfg('weekly_cycle_days', 7);

    foreach (storage_read('casawasap_contactos.json') as $row) {
        if (($row['estado'] ?? '') !== 'cliente') continue;
        if (($row['periodicidad_cobro'] ?? '') !== 'semanal') continue;

        $anchorTs = aviso_ts($row['cliente_at'] ?? '');
        if (!$anchorTs) continue;

        $days = floor(($todayTs - strtotime(date('Y-m-d', $anchorTs))) / 86400);
        if ($days < $cycleDays) continue;
        if (($days % $cycleDays) !== 0) continue;

        $weeks = (int)($days / $cycleDays);

        $generated[] = aviso_make(
            'recurring',
            'casawasap_alquiler_due_today_' . ($row['id'] ?? '') . '_' . $todayKey,
            'Cobro semanal Casawasap vence hoy',
            'El cliente Casawasap "' . ($row['nombre'] ?? ($row['telefono'] ?? 'Cliente')) . '" en modo alquiler debería pagar hoy su siguiente semanalidad.',
            'alta',
            array('branch' => 'casawasap', 'id' => $row['id'] ?? '', 'week' => $weeks, 'day' => $todayKey),
            true
        );
    }

    return $generated;
}

function avisos_generate_lamami_clienta_without_bot() {
    $generated = array();

    foreach (storage_read('clientes.json') as $row) {
        if (($row['estado'] ?? '') !== 'alta') continue;
        $clientaId = $row['id'] ?? '';
        if ($clientaId === '') continue;
        if (get_clienta_current_bot($clientaId)) continue;

        $generated[] = aviso_make(
            'integrity',
            'lamami_clienta_without_bot_' . $clientaId,
            'Clienta activa sin bot vinculado',
            'La clienta "' . ($row['nombre'] ?? 'Clienta') . '" está activa pero no tiene ningún bot vinculado.',
            'media',
            array('branch' => 'lamami', 'id' => $clientaId, 'kind' => 'no_bot'),
            true
        );
    }

    return $generated;
}

function avisos_generate_bots_linked_to_baja_clienta() {
    $generated = array();
    $clientesIndex = array();
    foreach (storage_read('clientes.json') as $row) {
        $clientesIndex[$row['id'] ?? ''] = $row;
    }

    foreach (storage_read('bots.json') as $bot) {
        $botId = $bot['id'] ?? '';
        $clientaId = bot_linked_id($bot);
        if ($botId === '' || $clientaId === '') continue;
        if (bot_linked_type($bot) !== 'lamami_clienta') continue;
        if (empty($clientesIndex[$clientaId])) continue;
        $clienta = $clientesIndex[$clientaId];
        if (($clienta['estado'] ?? '') !== 'baja') continue;

        $generated[] = aviso_make(
            'integrity',
            'bot_linked_to_baja_' . $botId . '_' . $clientaId,
            'Bot vinculado a una clienta de baja',
            'El bot "' . ($bot['nombre_bot'] ?? 'Bot') . '" sigue vinculado a la clienta "' . ($clienta['nombre'] ?? 'Clienta') . '" que está de baja.',
            'alta',
            array('branch' => 'lamami', 'bot_id' => $botId, 'clienta_id' => $clientaId, 'kind' => 'bot_baja'),
            true
        );
    }

    return $generated;
}

function avisos_generate_bots_missing_memory_file() {
    $generated = array();

    foreach (storage_read('bots.json') as $bot) {
        $botId = $bot['id'] ?? '';
        if ($botId === '') continue;

        $path = aviso_bot_memory_file_path($bot);
        if (is_file($path)) continue;

        $generated[] = aviso_make(
            'integrity',
            'bot_memory_missing_' . $botId,
            'Bot sin archivo de memoria',
            'El bot "' . ($bot['nombre_bot'] ?? 'Bot') . '" no tiene accesible su archivo de memoria esperado: ' . $path,
            'media',
            array('bot_id' => $botId, 'path' => $path, 'kind' => 'missing_memory'),
            true
        );
    }

    return $generated;
}

function avisos_generate_lamami_clientas_without_leads_7d() {
    $generated = array();
    $now = time();

    foreach (storage_read('clientes.json') as $row) {
        if (($row['estado'] ?? '') !== 'alta') continue;

        $clientaId = $row['id'] ?? '';
        if ($clientaId === '') continue;

        $clientaNombreNorm = strtolower(trim((string)($row['nombre'] ?? '')));
        if ($clientaNombreNorm === 'casawasap-bot') continue;

        $altaTs = aviso_ts($row['fecha_alta'] ?? '');
        $daysLimit = (int)aviso_cfg('lamami_clienta_without_leads_days', 7);
        if (!$altaTs || ($now - $altaTs) < $daysLimit * 86400) continue;

        $hasLead = false;
        foreach (storage_read('leads.json') as $lead) {
            if (($lead['cliente_id'] ?? '') !== $clientaId) continue;
            $hasLead = true;
            break;
        }

        if ($hasLead) continue;

        $generated[] = aviso_make(
            'performance',
            'lamami_no_leads_' . $daysLimit . 'd_' . $clientaId,
            'Clienta de LaMami sin leads tras ' . $daysLimit . ' días',
            'La clienta "' . ($row['nombre'] ?? 'Clienta') . '" lleva más de ' . $daysLimit . ' días de alta sin generar ningún lead.',
            'media',
            array('branch' => 'lamami', 'id' => $clientaId, 'days' => $daysLimit),
            true
        );
    }

    return $generated;
}

function avisos_generate_jostal_clientas_en_casa_without_income_7d() {
    $generated = array();
    $now = time();

    foreach (storage_read('jostal_clientas.json') as $row) {
        $clientaId = $row['id'] ?? '';
        if ($clientaId === '') continue;
        if (!jostal_clienta_en_casa($row)) continue;

        $periodos = jostal_periodos_estancia($row);
        if (empty($periodos)) continue;
        $ultimo = $periodos[count($periodos) - 1];
        $entradaTs = aviso_ts($ultimo['entrada'] ?? '');
        $daysLimit = (int)aviso_cfg('jostal_clienta_en_casa_without_income_days', 7);
        if (!$entradaTs || ($now - $entradaTs) < $daysLimit * 86400) continue;

        $lastIncomeTs = aviso_jostal_clienta_last_income_ts($clientaId);
        if ($lastIncomeTs && $lastIncomeTs >= $entradaTs) continue;

        $generated[] = aviso_make(
            'performance',
            'jostal_en_casa_sin_ingresos_' . $daysLimit . 'd_' . $clientaId,
            'Clienta Jostal en casa sin ingresos tras ' . $daysLimit . ' días',
            'La clienta Jostal "' . ($row['nombre'] ?? 'Clienta') . '" está en la casa y lleva más de ' . $daysLimit . ' días sin registrar ingresos desde su última entrada.',
            'alta',
            array('branch' => 'jostal', 'id' => $clientaId, 'days' => $daysLimit),
            true
        );
    }

    return $generated;
}

function avisos_generate_casawasap_clientes_without_pagos_7d() {
    $generated = array();
    $now = time();

    foreach (storage_read('casawasap_contactos.json') as $row) {
        if (($row['estado'] ?? '') !== 'cliente') continue;

        $clienteId = $row['id'] ?? '';
        if ($clienteId === '') continue;

        $clienteAtTs = aviso_ts($row['cliente_at'] ?? '');
        $daysLimit = (int)aviso_cfg('casawasap_cliente_without_pagos_days', 7);
        if (!$clienteAtTs || ($now - $clienteAtTs) < $daysLimit * 86400) continue;

        $lastPagoTs = aviso_casawasap_cliente_last_pago_ts($clienteId);
        if ($lastPagoTs && $lastPagoTs >= $clienteAtTs) continue;

        $generated[] = aviso_make(
            'performance',
            'casawasap_sin_pagos_' . $daysLimit . 'd_' . $clienteId,
            'Cliente Casawasap sin pagos tras ' . $daysLimit . ' días',
            'El cliente Casawasap "' . ($row['nombre'] ?? ($row['telefono'] ?? 'Cliente')) . '" lleva más de ' . $daysLimit . ' días dado de alta sin registrar pagos.',
            'alta',
            array('branch' => 'casawasap', 'id' => $clienteId, 'days' => $daysLimit),
            true
        );
    }

    return $generated;
}

function avisos_generate_lamami_publicidad_overdue_1w() {
    $generated = array();
    $now = time();

    $cycleDays = (int)aviso_cfg('weekly_cycle_days', 7);
    $extraWeeks = (int)aviso_cfg('overdue_additional_weeks', 1);
    $minDays = $cycleDays * (1 + $extraWeeks);

    foreach (storage_read('clientes.json') as $row) {
        if (($row['estado'] ?? '') !== 'alta') continue;

        $anchorTs = aviso_ts($row['fecha_alta'] ?? '');
        if (!$anchorTs) continue;

        $days = floor(($now - strtotime(date('Y-m-d', $anchorTs))) / 86400);
        if ($days < $minDays) continue;
        if (($days % $cycleDays) !== 0) continue;

        $weeks = (int)($days / $cycleDays);

        $generated[] = aviso_make(
            'recurring',
            'lamami_publicidad_overdue_1w_' . ($row['id'] ?? '') . '_' . date('Y-m-d'),
            'Publicidad LaMami vencida hace al menos 1 semana',
            'La clienta "' . ($row['nombre'] ?? 'Clienta') . '" ya acumula ' . $weeks . ' semanas desde el alta. Revisa si sigue pendiente la renovación de publicidad.',
            'alta',
            array('branch' => 'lamami', 'id' => $row['id'] ?? '', 'weeks' => $weeks),
            true
        );
    }

    return $generated;
}

function avisos_generate_casawasap_alquiler_overdue_1w() {
    $generated = array();
    $now = time();
    $cycleDays = (int)aviso_cfg('weekly_cycle_days', 7);
    $extraWeeks = (int)aviso_cfg('overdue_additional_weeks', 1);
    $minDays = $cycleDays * (1 + $extraWeeks);

    foreach (storage_read('casawasap_contactos.json') as $row) {
        if (($row['estado'] ?? '') !== 'cliente') continue;
        if (($row['periodicidad_cobro'] ?? '') !== 'semanal') continue;

        $anchorTs = aviso_ts($row['cliente_at'] ?? '');
        if (!$anchorTs) continue;

        $days = floor(($now - strtotime(date('Y-m-d', $anchorTs))) / 86400);
        if ($days < $minDays) continue;
        if (($days % $cycleDays) !== 0) continue;

        $weeks = (int)($days / $cycleDays);

        $generated[] = aviso_make(
            'recurring',
            'casawasap_alquiler_overdue_1w_' . ($row['id'] ?? '') . '_' . date('Y-m-d'),
            'Cobro Casawasap vencido hace al menos 1 semana',
            'El cliente Casawasap "' . ($row['nombre'] ?? ($row['telefono'] ?? 'Cliente')) . '" en modo alquiler ya va por la semana ' . $weeks . ' desde su alta. Revisa cobro pendiente.',
            'alta',
            array('branch' => 'casawasap', 'id' => $row['id'] ?? '', 'weeks' => $weeks),
            true
        );
    }

    return $generated;
}

function avisos_generate_destacamos_publish_reminders() {
    return array();
}

function avisos_generate_mundosex_publish_reminders() {
    $generated = array();

    if (!(int)aviso_cfg('mundosex_reminder_enabled', 1)) {
        return $generated;
    }

    $intervalHours = (int)aviso_cfg('mundosex_reminder_interval_hours', 4);
    $startTime = aviso_cfg('mundosex_reminder_start_time', '08:00');
    $endTime = aviso_cfg('mundosex_reminder_end_time', '23:00');
    $windowMinutes = (int)aviso_cfg('mundosex_reminder_window_minutes', 90);

    $times = avisos_times_for_mundosex_window($startTime, $endTime, $intervalHours);
    $todayKey = date('Y-m-d');

    foreach ($times as $hhmm) {
        if (!avisos_is_due_now_for_hhmm($hhmm, $windowMinutes)) {
            continue;
        }

        $slotKey = str_replace(':', '', $hhmm);

        $generated[] = aviso_make(
            'recurring',
            'mundosex_publish_' . $todayKey . '_' . $slotKey,
            'Subir publicidad a MundoSex',
            'Recordatorio operativo: toca subir / renovar / republicar la publicidad de MundoSex. Intervalo: cada ' . $intervalHours . 'h. Franja actual: ' . $hhmm . '. Ventana del día: ' . avisos_hhmm_normalize($startTime, '08:00') . ' → ' . avisos_hhmm_normalize($endTime, '23:00') . '.',
            'media',
            array(
                'kind' => 'mundosex_publish',
                'day' => $todayKey,
                'hhmm' => $hhmm,
                'interval_hours' => $intervalHours,
                'start_time' => avisos_hhmm_normalize($startTime, '08:00'),
                'end_time' => avisos_hhmm_normalize($endTime, '23:00'),
            ),
            false
        );
    }

    return $generated;
}

function avisos_generate_many_renewals_due_today() {
    $generated = array();
    $todayKey = business_today_date();
    $todayTs = strtotime($todayKey);
    $cycleDays = (int)aviso_cfg('weekly_cycle_days', 7);
    $minTotal = (int)aviso_cfg('many_renewals_due_today_min_total', 3);

    $lamamiCount = 0;
    foreach (storage_read('clientes.json') as $row) {
        if (($row['estado'] ?? '') !== 'alta') continue;
        $anchorTs = aviso_ts($row['fecha_alta'] ?? '');
        if (!$anchorTs) continue;
        $days = floor(($todayTs - strtotime(date('Y-m-d', $anchorTs))) / 86400);
        if ($days >= $cycleDays && ($days % $cycleDays) === 0) $lamamiCount++;
    }

    $casaCount = 0;
    foreach (storage_read('casawasap_contactos.json') as $row) {
        if (($row['estado'] ?? '') !== 'cliente') continue;
        if (($row['periodicidad_cobro'] ?? '') !== 'semanal') continue;
        $anchorTs = aviso_ts($row['cliente_at'] ?? '');
        if (!$anchorTs) continue;
        $days = floor(($todayTs - strtotime(date('Y-m-d', $anchorTs))) / 86400);
        if ($days >= $cycleDays && ($days % $cycleDays) === 0) $casaCount++;
    }

    $total = $lamamiCount + $casaCount;
    if ($total < $minTotal) return $generated;

    $generated[] = aviso_make(
        'recurring',
        'many_renewals_due_today_' . $todayKey,
        'Varias renovaciones/cobros vencen hoy',
        'Hoy vencen ' . $lamamiCount . ' renovaciones de LaMami y ' . $casaCount . ' cobros de Casawasap. Total a revisar: ' . $total . '.',
        'media',
        array('day' => $todayKey, 'lamami' => $lamamiCount, 'casawasap' => $casaCount),
        false
    );

    return $generated;
}

function avisos_generate_casawasap_alquiler_missing_cliente_at() {
    $generated = array();

    foreach (storage_read('casawasap_contactos.json') as $row) {
        if (($row['estado'] ?? '') !== 'cliente') continue;
        if (($row['periodicidad_cobro'] ?? '') !== 'semanal') continue;
        if (trim((string)($row['cliente_at'] ?? '')) !== '') continue;

        $generated[] = aviso_make(
            'integrity',
            'casawasap_missing_cliente_at_' . ($row['id'] ?? ''),
            'Cliente Casawasap con cobro semanal sin fecha base',
            'El cliente Casawasap "' . ($row['nombre'] ?? ($row['telefono'] ?? 'Cliente')) . '" está en modo alquiler pero no tiene guardado cliente_at.',
            'alta',
            array('branch' => 'casawasap', 'id' => $row['id'] ?? '', 'kind' => 'missing_cliente_at'),
            true
        );
    }

    return $generated;
}

function avisos_generate_jostal_multiple_open_periods() {
    $generated = array();

    foreach (storage_read('jostal_clientas.json') as $row) {
        $periodos = jostal_periodos_estancia($row);
        $openCount = 0;
        foreach ($periodos as $p) {
            if (trim((string)($p['salida'] ?? '')) === '') $openCount++;
        }
        if ($openCount <= 1) continue;

        $generated[] = aviso_make(
            'integrity',
            'jostal_multiple_open_periods_' . ($row['id'] ?? ''),
            'Clienta Jostal con varios periodos abiertos',
            'La clienta Jostal "' . ($row['nombre'] ?? 'Clienta') . '" tiene ' . $openCount . ' periodos de estancia abiertos al mismo tiempo.',
            'media',
            array('branch' => 'jostal', 'id' => $row['id'] ?? '', 'open_count' => $openCount),
            true
        );
    }

    return $generated;
}

function avisos_generate_jostal_invalid_period_dates() {
    $generated = array();

    foreach (storage_read('jostal_clientas.json') as $row) {
        $periodos = jostal_periodos_estancia($row);
        $hasInvalid = false;

        foreach ($periodos as $p) {
            $entradaTs = aviso_ts($p['entrada'] ?? '');
            $salidaTs = aviso_ts($p['salida'] ?? '');
            if ($entradaTs && $salidaTs && $salidaTs < $entradaTs) {
                $hasInvalid = true;
                break;
            }
        }

        if (!$hasInvalid) continue;

        $generated[] = aviso_make(
            'integrity',
            'jostal_invalid_period_dates_' . ($row['id'] ?? ''),
            'Clienta Jostal con fechas incoherentes',
            'La clienta Jostal "' . ($row['nombre'] ?? 'Clienta') . '" tiene al menos un periodo con salida anterior a la entrada.',
            'alta',
            array('branch' => 'jostal', 'id' => $row['id'] ?? '', 'kind' => 'invalid_period_dates'),
            true
        );
    }

    return $generated;
}

function avisos_generate_telefonos_broken_destacamos_links() {
    $generated = array();
    $anunciosIndex = array();

    foreach (storage_read('anuncios.json') as $anuncio) {
        $anunciosIndex[$anuncio['id'] ?? ''] = true;
    }

    foreach (storage_read('telefonos.json') as $row) {
        $destId = trim((string)($row['destacamos_id'] ?? ''));
        if ($destId === '') continue;
        if (!empty($anunciosIndex[$destId])) continue;

        $generated[] = aviso_make(
            'integrity',
            'telefono_broken_dest_' . ($row['id'] ?? ''),
            'Teléfono vinculado a un anuncio inexistente',
            'El teléfono "' . ($row['nombre'] ?? ($row['tfono'] ?? 'Teléfono')) . '" referencia un anuncio que ya no existe.',
            'media',
            array('id' => $row['id'] ?? '', 'destacamos_id' => $destId),
            true
        );
    }

    return $generated;
}

function avisos_generate_record_daily_income() {
    $generated = array();
    $todayKey = business_today_date();
    $todayIncome = aviso_day_income_total($todayKey);

    if ($todayIncome <= 0) return $generated;

    $bestPast = 0;
    foreach (aviso_income_movements_all() as $row) {
        $ts = aviso_ts($row['date'] ?? '');
        if (!$ts) continue;
        $dayKey = business_day_key_from_ts($ts);
        if ($dayKey === $todayKey) continue;
    }

    $dailyMap = array();
    foreach (aviso_income_movements_all() as $row) {
        $ts = aviso_ts($row['date'] ?? '');
        if (!$ts) continue;
        $dayKey = business_day_key_from_ts($ts);
        if (!isset($dailyMap[$dayKey])) $dailyMap[$dayKey] = 0;
        $dailyMap[$dayKey] += (float)($row['amount'] ?? 0);
    }
    unset($dailyMap[$todayKey]);
    if (!empty($dailyMap)) $bestPast = max($dailyMap);

    if ($todayIncome > $bestPast && !aviso_exists_any_status('milestones', 'record_daily_income_' . $todayKey)) {
        $generated[] = aviso_make(
            'milestones',
            'record_daily_income_' . $todayKey,
            'Récord histórico de ingresos diarios',
            'Hoy se ha alcanzado un nuevo récord de ingresos diarios: ' . euro($todayIncome) . '.',
            'alta',
            array('day' => $todayKey, 'amount' => $todayIncome),
            false
        );
    }

    return $generated;
}

function avisos_generate_record_daily_profit() {
    $generated = array();
    $todayKey = business_today_date();
    $todayProfit = aviso_day_profit_total($todayKey);

    if ($todayProfit <= 0) return $generated;

    $dailyProfitMap = array();
    foreach (aviso_income_movements_all() as $row) {
        $ts = aviso_ts($row['date'] ?? '');
        if (!$ts) continue;
        $dayKey = business_day_key_from_ts($ts);
        if (!isset($dailyProfitMap[$dayKey])) $dailyProfitMap[$dayKey] = 0;
        $dailyProfitMap[$dayKey] += (float)($row['amount'] ?? 0);
    }
    foreach (storage_read('gastos.json') as $row) {
        $ts = aviso_ts($row['created_at'] ?? '');
        if (!$ts) continue;
        $dayKey = business_day_key_from_ts($ts);
        if (!isset($dailyProfitMap[$dayKey])) $dailyProfitMap[$dayKey] = 0;
        $dailyProfitMap[$dayKey] -= (float)($row['cantidad'] ?? 0);
    }

    unset($dailyProfitMap[$todayKey]);
    $bestPast = !empty($dailyProfitMap) ? max($dailyProfitMap) : 0;

    if ($todayProfit > $bestPast && !aviso_exists_any_status('milestones', 'record_daily_profit_' . $todayKey)) {
        $generated[] = aviso_make(
            'milestones',
            'record_daily_profit_' . $todayKey,
            'Récord histórico de beneficio diario',
            'Hoy se ha alcanzado un nuevo récord de beneficio diario: ' . euro($todayProfit) . '.',
            'alta',
            array('day' => $todayKey, 'amount' => $todayProfit),
            false
        );
    }

    return $generated;
}

function avisos_generate_record_month_income() {
    $generated = array();
    $monthKey = business_current_month_key();
    $currentIncome = aviso_month_income_total($monthKey);

    if ($currentIncome <= 0) return $generated;

    $monthMap = array();
    foreach (aviso_income_movements_all() as $row) {
        $ts = aviso_ts($row['date'] ?? '');
        if (!$ts) continue;
        $k = business_month_key_from_ts($ts);
        if (!isset($monthMap[$k])) $monthMap[$k] = 0;
        $monthMap[$k] += (float)($row['amount'] ?? 0);
    }

    unset($monthMap[$monthKey]);
    $bestPast = !empty($monthMap) ? max($monthMap) : 0;

    if ($currentIncome > $bestPast && !aviso_exists_any_status('milestones', 'record_month_income_' . $monthKey)) {
        $generated[] = aviso_make(
            'milestones',
            'record_month_income_' . $monthKey,
            'Récord histórico mensual de ingresos',
            'El mes actual ya ha superado todos los meses anteriores en ingresos: ' . euro($currentIncome) . '.',
            'alta',
            array('month' => $monthKey, 'amount' => $currentIncome),
            false
        );
    }

    return $generated;
}

function avisos_generate_branch_leader_change() {
    $generated = array();
    $monthKey = business_current_month_key();
    $prevMonthKey = date('Y-m', strtotime($monthKey . '-01 -1 month'));

    $current = aviso_month_branch_income_totals($monthKey);
    $previous = aviso_month_branch_income_totals($prevMonthKey);

    arsort($current);
    arsort($previous);

    $currentLeader = key($current);
    $previousLeader = key($previous);

    if (!$currentLeader || !$previousLeader) return $generated;
    if ($currentLeader === $previousLeader) return $generated;
    if (($current[$currentLeader] ?? 0) <= 0) return $generated;

    $generated[] = aviso_make(
        'strategic',
        'branch_leader_change_' . $monthKey,
        'Cambio de rama líder del mes',
        'La rama líder del mes ha cambiado: ahora lidera ' . strtoupper($currentLeader) . ' en lugar de ' . strtoupper($previousLeader) . '.',
        'alta',
        array('month' => $monthKey, 'current' => $currentLeader, 'previous' => $previousLeader),
        false
    );

    return $generated;
}

function avisos_generate_branch_concentration_high() {
    $generated = array();
    $monthKey = business_current_month_key();
    $totals = aviso_month_branch_income_totals($monthKey);
    $sum = array_sum($totals);

    if ($sum <= 0) return $generated;

    arsort($totals);
    $leader = key($totals);
    $share = (($totals[$leader] ?? 0) / $sum) * 100;

    $minPercent = (float)aviso_cfg('branch_concentration_percent', 70);
    if ($share < $minPercent) return $generated;

    $generated[] = aviso_make(
        'strategic',
        'branch_concentration_' . $monthKey . '_' . $leader,
        'Concentración alta en una sola rama',
        'La rama ' . strtoupper($leader) . ' está concentrando el ' . round($share, 1) . '% de los ingresos del mes actual.',
        'media',
        array('month' => $monthKey, 'leader' => $leader, 'share' => round($share, 1)),
        true
    );

    return $generated;
}

function avisos_generate_month_projection_below_previous() {
    $generated = array();
    $monthKey = business_current_month_key();
    $prevMonthKey = date('Y-m', strtotime($monthKey . '-01 -1 month'));

    $factor = (float)aviso_cfg('projection_vs_previous_factor', 0.80);

    $currentIncome = aviso_month_income_total($monthKey);
    $elapsedDays = max(1, aviso_month_elapsed_days($monthKey));
    $totalDays = max(1, aviso_month_total_days($monthKey));
    $projected = ($currentIncome / $elapsedDays) * $totalDays;
    $prevIncome = aviso_month_income_total($prevMonthKey);

    if ($prevIncome <= 0) return $generated;

    // Solo se evalúa en momentos relevantes del mes (días 10, 20 y 30), no en
    // los primeros días donde la proyección aún no es representativa.
    $checkDays = array(10, 20, 30);
    if (!in_array($elapsedDays, $checkDays, true)) return $generated;

    if ($projected >= ($prevIncome * $factor)) return $generated;

    $generated[] = aviso_make(
        'strategic',
        'month_projection_low_' . $monthKey . '_d' . $elapsedDays,
        'Proyección mensual claramente por debajo del mes anterior',
        'La proyección de ingresos del mes actual es ' . euro($projected) . ', claramente por debajo del cierre del mes anterior (' . euro($prevIncome) . ').',
        'alta',
        array('month' => $monthKey, 'projected' => $projected, 'previous' => $prevIncome, 'elapsed_days' => $elapsedDays),
        true
    );

    return $generated;
}

function avisos_generate_negative_trend_3_days() {
    $generated = array();
    $trendDays = max(2, (int)aviso_cfg('negative_trend_days', 3));

    $days = array();
    for ($i = $trendDays - 1; $i >= 0; $i--) {
        $days[] = date('Y-m-d', strtotime(business_today_date() . ' -' . $i . ' day'));
    }

    $profits = array();
    foreach ($days as $day) {
        $profits[] = aviso_day_profit_total($day);
    }

    $isStrictlyWorse = true;
    for ($i = 0; $i < count($profits) - 1; $i++) {
        if (!($profits[$i] > $profits[$i + 1])) {
            $isStrictlyWorse = false;
            break;
        }
    }

    if (!$isStrictlyWorse) return $generated;
    if ($profits[count($profits) - 1] >= 0) return $generated;

    $generated[] = aviso_make(
        'strategic',
        'negative_trend_' . $trendDays . '_days_' . date('Y-m-d'),
        'Tendencia negativa de ' . $trendDays . ' días',
        'El beneficio diario lleva ' . $trendDays . ' días consecutivos empeorando y hoy está en negativo.',
        'alta',
        array('profits' => $profits, 'days' => $trendDays),
        true
    );

    return $generated;
}

function avisos_generate_high_expense_today() {
    $generated = array();
    $todayKey = business_today_date();

    foreach (storage_read('gastos.json') as $row) {
        $id = $row['id'] ?? '';
        if ($id === '') continue;
        if (business_day_key_from_ts(aviso_ts($row['created_at'] ?? '')) !== $todayKey) continue;
        $amount = (float)($row['cantidad'] ?? 0);
        $minAmount = (float)aviso_cfg('high_expense_amount', 200);
        if ($amount < $minAmount) continue;
        if (aviso_exists_any_status('events', 'high_expense_' . $id)) continue;

        $generated[] = aviso_make(
            'events',
            'high_expense_' . $id,
            'Gasto alto registrado',
            'Se ha registrado un gasto alto de ' . euro($amount) . ' con concepto: ' . ($row['descripcion'] ?? 'Sin descripción') . '.',
            'alta',
            array('id' => $id, 'amount' => $amount),
            false
        );
    }

    return $generated;
}

function avisos_generate_too_many_active_alerts() {
    $generated = array();
    $activeCount = count(avisos_get_active());

    $limit = (int)aviso_cfg('too_many_active_alerts_count', 15);
    if ($activeCount < $limit) return $generated;

    $generated[] = aviso_make(
        'integrity',
        'too_many_active_alerts_' . date('Y-m-d-H'),
        'Hay demasiados avisos activos',
        'Actualmente hay ' . $activeCount . ' avisos activos. Conviene revisar el panel para evitar ruido y acumulación.',
        'media',
        array('count' => $activeCount),
        true
    );

    return $generated;
}

function avisos_generate_bots_without_clienta() {
    $generated = array();

    foreach (storage_read('bots.json') as $bot) {
        $botId = $bot['id'] ?? '';
        if ($botId === '') continue;
        if (bot_linked_id($bot) !== '') continue;

        $generated[] = aviso_make(
            'integrity',
            'bot_without_clienta_' . $botId,
            'Bot sin ficha vinculada',
            'El bot "' . ($bot['nombre_bot'] ?? 'Bot') . '" no tiene ninguna ficha vinculada ni de LaMami ni de CasaWasap.',
            'media',
            array('bot_id' => $botId),
            true
        );
    }

    return $generated;
}

function avisos_generate_incomplete_anuncios() {
    $generated = array();

    foreach (storage_read('anuncios.json') as $row) {
        $id = $row['id'] ?? '';
        if ($id === '') continue;

        $missing = array();
        if (trim((string)($row['url'] ?? '')) === '') $missing[] = 'url';
        if (trim((string)($row['user'] ?? '')) === '') $missing[] = 'user';
        if (trim((string)($row['pass'] ?? '')) === '') $missing[] = 'pass';

        if (empty($missing)) continue;

        $generated[] = aviso_make(
            'integrity',
            'anuncio_incomplete_' . $id,
            'Anuncio incompleto',
            'El anuncio #' . $id . ' tiene campos incompletos: ' . implode(', ', $missing) . '.',
            'media',
            array('id' => $id, 'missing' => $missing),
            true
        );
    }

    return $generated;
}

function avisos_generate_lamami_new_clientas() {
    $generated = array();
    foreach (storage_read('clientes.json') as $row) {
        $id = $row['id'] ?? '';
        if ($id === '') continue;
        $sourceKey = 'lamami_clienta_' . $id;
        if (aviso_exists_any_status('events', $sourceKey)) continue;
        if (!aviso_is_recent_for_event($row['created_at'] ?? ($row['updated_at'] ?? ''))) continue;

        $generated[] = aviso_make(
            'events',
            $sourceKey,
            'Nueva clienta en LaMami',
            'Se ha creado la clienta "' . ($row['nombre'] ?? 'Sin nombre') . '". Alta: ' . ($row['fecha_alta'] ?? '-') . '.',
            'media',
            array('branch' => 'lamami', 'id' => $id, 'type' => 'clienta'),
            false
        );
    }
    return $generated;
}

function avisos_generate_lamami_new_leads() {
    $generated = array();
    foreach (storage_read('leads.json') as $row) {
        $id = $row['id'] ?? '';
        if ($id === '') continue;
        $sourceKey = 'lamami_lead_' . $id;
        if (aviso_exists_any_status('events', $sourceKey)) continue;
        if (!aviso_is_recent_for_event($row['fecha_hora'] ?? ($row['created_at'] ?? ''))) continue;

        $generated[] = aviso_make(
            'events',
            $sourceKey,
            'Nuevo lead en LaMami',
            'Lead añadido a "' . ($row['cliente_nombre'] ?? 'Sin clienta') . '" por ' . euro($row['precio_lead'] ?? 0) . '.',
            'media',
            array('branch' => 'lamami', 'id' => $id, 'type' => 'lead'),
            false
        );
    }
    return $generated;
}

function avisos_generate_no_income_24h() {
    $generated = array();
    $lastTs = aviso_last_income_ts();
    if (!$lastTs) return $generated;

    $now = time();
    $hoursLimit = (int)aviso_cfg('no_income_hours_1', 24);
    $secondsLimit = $hoursLimit * 3600;

    if (($now - $lastTs) < $secondsLimit) return $generated;

    $hoursWithout = floor(($now - $lastTs) / 3600);
    $slot = floor(($now - $lastTs) / $secondsLimit);
    $sourceKey = 'no_income_' . $hoursLimit . 'h_slot_' . $slot;

    $generated[] = aviso_make(
        'inactivity',
        $sourceKey,
        'Más de ' . $hoursLimit . 'h sin ingresos',
        'No se registra ningún ingreso en ninguna rama desde hace ' . $hoursWithout . ' horas. Último ingreso: ' . date('d/m/Y H:i', $lastTs) . '.',
        'alta',
        array('hours_without' => $hoursWithout, 'last_income_at' => date('Y-m-d H:i:s', $lastTs)),
        true
    );

    return $generated;
}

function avisos_generate_jostal_new_clientas() {
    $generated = array();
    foreach (storage_read('jostal_clientas.json') as $row) {
        $id = $row['id'] ?? '';
        if ($id === '') continue;
        $sourceKey = 'jostal_clienta_' . $id;
        if (aviso_exists_any_status('events', $sourceKey)) continue;
        if (!aviso_is_recent_for_event($row['created_at'] ?? ($row['updated_at'] ?? ''))) continue;

        $generated[] = aviso_make(
            'events',
            $sourceKey,
            'Nueva clienta en Jostal',
            'Se ha creado la clienta Jostal "' . ($row['nombre'] ?? 'Sin nombre') . '".',
            'media',
            array('branch' => 'jostal', 'id' => $id, 'type' => 'clienta'),
            false
        );
    }
    return $generated;
}

function avisos_generate_jostal_new_leads() {
    $generated = array();
    foreach (storage_read('jostal_leads.json') as $row) {
        $id = $row['id'] ?? '';
        if ($id === '') continue;
        $sourceKey = 'jostal_lead_' . $id;
        if (aviso_exists_any_status('events', $sourceKey)) continue;
        if (!aviso_is_recent_for_event($row['created_at'] ?? ($row['updated_at'] ?? ''))) continue;

        $generated[] = aviso_make(
            'events',
            $sourceKey,
            'Nuevo lead en Jostal',
            'Lead Jostal añadido a "' . ($row['clienta_nombre'] ?? 'Sin clienta') . '" por ' . euro($row['precio'] ?? 0) . '.',
            'media',
            array('branch' => 'jostal', 'id' => $id, 'type' => 'lead'),
            false
        );
    }
    return $generated;
}

function avisos_generate_jostal_new_sales() {
    $generated = array();
    foreach (storage_read('jostal_ventas.json') as $row) {
        $id = $row['id'] ?? '';
        if ($id === '') continue;
        $sourceKey = 'jostal_venta_' . $id;
        if (aviso_exists_any_status('events', $sourceKey)) continue;
        if (!aviso_is_recent_for_event($row['created_at'] ?? ($row['updated_at'] ?? ''))) continue;

        $generated[] = aviso_make(
            'events',
            $sourceKey,
            'Nueva venta en Jostal',
            'Venta registrada: "' . ($row['descripcion'] ?? 'Venta') . '" por ' . euro($row['precio'] ?? 0) . '.',
            'media',
            array('branch' => 'jostal', 'id' => $id, 'type' => 'venta'),
            false
        );
    }
    return $generated;
}

function avisos_generate_casawasap_new_clientes() {
    $generated = array();
    foreach (storage_read('casawasap_contactos.json') as $row) {
        if (($row['estado'] ?? '') !== 'cliente') continue;
        $id = $row['id'] ?? '';
        if ($id === '') continue;
        $sourceKey = 'casawasap_cliente_' . $id;
        if (aviso_exists_any_status('events', $sourceKey)) continue;
        if (!aviso_is_recent_for_event($row['cliente_at'] ?? ($row['created_at'] ?? ($row['updated_at'] ?? '')))) continue;

        $generated[] = aviso_make(
            'events',
            $sourceKey,
            'Nuevo cliente en Casawasap',
            'Se ha dado de alta el cliente Casawasap "' . ($row['nombre'] ?? ($row['telefono'] ?? 'Sin nombre')) . '".',
            'alta',
            array('branch' => 'casawasap', 'id' => $id, 'type' => 'cliente'),
            false
        );
    }
    return $generated;
}

function avisos_generate_casawasap_new_pagos() {
    $generated = array();
    foreach (storage_read('casawasap_pagos.json') as $row) {
        $id = $row['id'] ?? '';
        if ($id === '') continue;
        $sourceKey = 'casawasap_pago_' . $id;
        if (aviso_exists_any_status('events', $sourceKey)) continue;
        if (!aviso_is_recent_for_event($row['fecha_hora'] ?? ($row['created_at'] ?? ''))) continue;

        $generated[] = aviso_make(
            'events',
            $sourceKey,
            'Nuevo ingreso en Casawasap',
            'Pago registrado para "' . ($row['cliente_nombre'] ?? 'Cliente') . '" por ' . euro($row['importe'] ?? 0) . '.',
            'media',
            array('branch' => 'casawasap', 'id' => $id, 'type' => 'pago'),
            false
        );
    }
    return $generated;
}

function avisos_generate_jostal_alquiler_due() {
    $generated = array();
    $todayKey = business_today_date();
    $todayTwoAmTs = strtotime($todayKey . ' 02:00:00');
    if (time() < $todayTwoAmTs) {
        return $generated;
    }

    foreach (storage_read('jostal_clientas.json') as $row) {
        $paymentInfo = function_exists('jostal_alquiler_payment_info') ? jostal_alquiler_payment_info($row) : array();
        if (empty($paymentInfo['enabled']) || empty($paymentInfo['due_today'])) continue;

        $generated[] = aviso_make(
            'recurring',
            'jostal_alquiler_due_today_' . ($row['id'] ?? '') . '_' . $todayKey,
            'Pago semanal alquiler Jostal vence hoy',
            'La clienta Jostal "' . ($row['nombre'] ?? 'Clienta') . '" está en casa, en modo alquiler, y hoy le toca pagar. Entró el ' . ($paymentInfo['entry_date'] ?? '') . ' (' . ($paymentInfo['entry_weekday_label'] ?? '') . ') y su día de cobro es ' . ($paymentInfo['due_weekday_label'] ?? '') . '.',
            'alta',
            array(
                'branch' => 'jostal',
                'id' => $row['id'] ?? '',
                'due_day' => $todayKey,
                'anchor_entry' => $paymentInfo['entry_date'] ?? '',
                'due_today' => true,
                'rent_due_weekday' => $paymentInfo['due_weekday'] ?? 0,
                'kind' => 'jostal_alquiler',
            ),
            false
        );
    }

    return $generated;
}

function avisos_generate_jostal_alquiler_due_tomorrow() {
    $generated = array();
    $todayKey = business_today_date();

    foreach (storage_read('jostal_clientas.json') as $row) {
        $paymentInfo = function_exists('jostal_alquiler_payment_info') ? jostal_alquiler_payment_info($row) : array();
        if (empty($paymentInfo['enabled']) || empty($paymentInfo['due_tomorrow'])) continue;

        $generated[] = aviso_make(
            'recurring',
            'jostal_alquiler_due_tomorrow_' . ($row['id'] ?? '') . '_' . $todayKey,
            'Mañana toca cobrar alquiler Jostal',
            'La clienta Jostal "' . ($row['nombre'] ?? 'Clienta') . '" está en casa, en modo alquiler, y mañana le toca pagar. Su día de cobro es ' . ($paymentInfo['due_weekday_label'] ?? '') . ' y el próximo vencimiento cae el ' . ($paymentInfo['next_due_date'] ?? '') . '.',
            'media',
            array(
                'branch' => 'jostal',
                'id' => $row['id'] ?? '',
                'due_day' => $paymentInfo['next_due_date'] ?? '',
                'due_tomorrow' => true,
                'rent_due_weekday' => $paymentInfo['due_weekday'] ?? 0,
            ),
            false
        );
    }

    return $generated;
}

function avisos_generate_lamami_publicidad_due() {
    $generated = array();
    $now = time();
    $todayKey = business_today_date();
    $todayStart = strtotime($todayKey . ' 00:00:00');
    $cycleDays = (int)aviso_cfg('weekly_cycle_days', 7);
    $cycleSeconds = $cycleDays * 86400;

    foreach (storage_read('clientes.json') as $row) {
        if (($row['estado'] ?? '') !== 'alta') continue;

        $anchorTs = aviso_ts($row['fecha_alta'] ?? '');
        if (!$anchorTs) continue;
        if ($now < $anchorTs + $cycleSeconds) continue;

        $weeks = (int)floor(($now - $anchorTs) / $cycleSeconds);
        if ($weeks < 1) continue;

        $dueTs = strtotime('+' . $weeks . ' week', $anchorTs);
        $dueDayKey = date('Y-m-d', $dueTs);
        $dueDate = date('d/m/Y', $dueTs);

        if ($dueDayKey === $todayKey) {
            $title = 'Renovación de publicidad vence hoy';
            $detail = 'La clienta "' . ($row['nombre'] ?? 'Clienta') . '" cumple hoy ' . $weeks . ' semana(s) desde el alta y debería renovar hoy su publicidad de 29€.';
        } else {
            $title = 'Renovación semanal de publicidad pendiente';
            $detail = 'La clienta "' . ($row['nombre'] ?? 'Clienta') . '" cumple ' . $weeks . ' semana(s) desde el alta. Desde el ' . $dueDate . ' debería volver a pagar 29€ de publicidad.';
        }

        $sourceKey = 'lamami_publicidad_week_' . ($row['id'] ?? '') . '_' . $weeks;

        $generated[] = aviso_make(
            'recurring',
            $sourceKey,
            $title,
            $detail,
            'media',
            array(
                'branch' => 'lamami',
                'id' => $row['id'] ?? '',
                'week' => $weeks,
                'due_day' => $dueDayKey,
                'due_today' => ($dueDayKey === $todayKey),
            ),
            true
        );
    }

    return $generated;
}

function avisos_generate_overdue_interesadas_all() {
    $generated = array();
    $now = time();

    $daysLimit = (int)aviso_cfg('overdue_interesada_days', 2);
    $secondsLimit = $daysLimit * 86400;

    foreach (storage_read('interesadas.json') as $row) {
        $estado = $row['estado'] ?? '';
        if (!in_array($estado, array('nueva', 'atendida'), true)) continue;
        $ts = aviso_ts($row['created_at'] ?? '');
        if (!$ts || ($now - $ts) < $secondsLimit) continue;

        $generated[] = aviso_make(
            'overdue',
            'lamami_interesada_' . ($row['id'] ?? ''),
            'Interesada de LaMami sin convertir > 2 días',
            'La interesada ' . ($row['telefono'] ?? 'sin teléfono') . ' lleva más de ' . $daysLimit . ' días sin pasar a clienta.',
            'media',
            array('branch' => 'lamami', 'id' => $row['id'] ?? ''),
            true
        );
    }

    foreach (storage_read('jostal_interesadas.json') as $row) {
        $estado = $row['estado'] ?? '';
        if (!in_array($estado, array('nueva'), true)) continue;
        $ts = aviso_ts($row['created_at'] ?? '');
        if (!$ts || ($now - $ts) < $secondsLimit) continue;

        $generated[] = aviso_make(
            'overdue',
            'jostal_interesada_' . ($row['id'] ?? ''),
            'Interesada de Jostal sin convertir > 2 días',
            'La interesada Jostal ' . ($row['telefono'] ?? 'sin teléfono') . ' lleva más de ' . $daysLimit . ' días sin pasar a clienta.',
            'media',
            array('branch' => 'jostal', 'id' => $row['id'] ?? ''),
            true
        );
    }

    foreach (storage_read('casawasap_contactos.json') as $row) {
        $estado = $row['estado'] ?? '';
        if ($estado !== 'interesado') continue;
        $ts = aviso_ts($row['created_at'] ?? '');
        if (!$ts || ($now - $ts) < $secondsLimit) continue;

        $generated[] = aviso_make(
            'overdue',
            'casawasap_interesado_' . ($row['id'] ?? ''),
            'Interesado de Casawasap sin convertir > 2 días',
            'El interesado Casawasap ' . ($row['telefono'] ?? 'sin teléfono') . ' lleva más de ' . $daysLimit . ' días sin pasar a cliente.',
            'media',
            array('branch' => 'casawasap', 'id' => $row['id'] ?? ''),
            true
        );
    }

    return $generated;
}

// ── OPS: salud de líneas, backup y saldos de IA ──────────────────────────────

function aviso_ops_line_states() {
    $lines = array();
    if (!function_exists('comercial_list_lines')) return $lines;
    foreach ((array)comercial_list_lines() as $line) {
        if (!is_array($line)) continue;
        if (trim((string)($line['id'] ?? '')) === '') continue;
        if (trim((string)($line['waha_port'] ?? '')) === '') continue;
        $lines[] = $line;
    }
    return $lines;
}

function avisos_generate_line_ban() {
    $generated = array();
    $now = time();
    foreach (aviso_ops_line_states() as $line) {
        $state = isset($line['comercial_state']) && is_array($line['comercial_state']) ? $line['comercial_state'] : array();
        // last_ban_at solo se marca ante señales reales de baneo (ver
        // comercial_line_failure_counts_as_ban): HTTP 401/403 o error explícito.
        // Los fallos transitorios (STARTING, conexión, 5xx, 429) ya no lo fijan.
        $banAt = trim((string)($state['last_ban_at'] ?? ''));
        if ($banAt === '') continue;
        $ts = strtotime($banAt);
        if (!$ts || ($now - $ts) > 24 * 3600) continue;

        $name = trim((string)($line['nombre'] ?? ''));
        $phone = comercial_only_digits((string)($line['tfono'] ?? ''));
        $generated[] = aviso_make(
            'ops',
            'line_ban_' . ($line['id'] ?? '') . '_' . date('Y-m-d'),
            'Línea de WhatsApp baneada',
            'La línea ' . ($name !== '' ? $name . ' (' . $phone . ')' : $phone) . ' ha detectado una señal real de baneo (' . $banAt . '). Revisa si deja de enviar mensajes.',
            'alta',
            array('line_id' => $line['id'] ?? '', 'line_name' => $name, 'line_phone' => $phone, 'kind' => 'line_ban'),
            true
        );
    }
    return $generated;
}

function avisos_generate_line_send_failures() {
    $generated = array();
    foreach (aviso_ops_line_states() as $line) {
        $state = isset($line['comercial_state']) && is_array($line['comercial_state']) ? $line['comercial_state'] : array();
        $failures = (int)($state['consecutive_failures'] ?? 0);
        if ($failures < 3) continue;

        $name = trim((string)($line['nombre'] ?? ''));
        $phone = comercial_only_digits((string)($line['tfono'] ?? ''));
        $lastError = trim((string)($state['last_error'] ?? ''));
        $generated[] = aviso_make(
            'ops',
            'line_send_failures_' . ($line['id'] ?? '') . '_' . date('Y-m-d'),
            'Envíos fallando de forma recurrente en una línea',
            'La línea ' . ($name !== '' ? $name . ' (' . $phone . ')' : $phone) . ' acumula ' . $failures . ' fallos consecutivos de envío.' . ($lastError !== '' ? ' Último error: ' . $lastError : ''),
            'alta',
            array('line_id' => $line['id'] ?? '', 'line_name' => $name, 'line_phone' => $phone, 'consecutive_failures' => $failures, 'kind' => 'send_failures'),
            true
        );
    }
    return $generated;
}

function avisos_generate_waha_down() {
    $generated = array();
    $now = time();
    foreach (aviso_ops_line_states() as $line) {
        $state = isset($line['comercial_state']) && is_array($line['comercial_state']) ? $line['comercial_state'] : array();
        $health = trim((string)($state['health_status'] ?? ''));
        if (!in_array($health, array('down', 'starting'), true)) continue;

        // Solo avisa si la línea llegó a estar sana recientemente (evita avisar
        // cada día de líneas crónicamente apagadas o fuera de uso).
        $lastOkAt = trim((string)($state['last_health_ok_at'] ?? ''));
        if ($lastOkAt === '') continue;
        $lastOkTs = strtotime($lastOkAt);
        if (!$lastOkTs || ($now - $lastOkTs) > 48 * 3600) continue;

        // Solo avisa si el último chequeo es reciente (evita estados viejos)
        $checkAt = trim((string)($state['last_health_check_at'] ?? ''));
        $ts = $checkAt !== '' ? strtotime($checkAt) : 0;
        if (!$ts || ($now - $ts) > 6 * 3600) continue;

        // STARTING es transitorio durante reinicios de WAHA: no generar alerta
        // hasta que lleve más de una hora sin volver a WORKING (evita falsos
        // positivos por reinicios puntuales).
        $startingGraceSec = 3600;
        if ($health === 'starting' && ($now - $lastOkTs) <= $startingGraceSec) {
            continue;
        }

        $name = trim((string)($line['nombre'] ?? ''));
        $phone = comercial_only_digits((string)($line['tfono'] ?? ''));
        $isStarting = ($health === 'starting');
        $minutesDown = (int)round(($now - $lastOkTs) / 60);
        $title = $isStarting ? 'Sesión de WhatsApp atascada en arranque' : 'Sesión de WhatsApp caída';
        $severity = $isStarting ? 'media' : 'alta';
        $message = $isStarting
            ? 'La sesión WAHA de la línea ' . ($name !== '' ? $name . ' (' . $phone . ')' : $phone) . ' lleva ' . $minutesDown . ' min en estado STARTING sin volver a WORKING. Revisa si necesita reinicio manual.'
            : 'La sesión WAHA de la línea ' . ($name !== '' ? $name . ' (' . $phone . ')' : $phone) . ' está en estado ' . strtoupper($health) . '. El bot o la línea puede haber dejado de responder.';

        $generated[] = aviso_make(
            'ops',
            'waha_down_' . ($line['id'] ?? '') . '_' . date('Y-m-d'),
            $title,
            $message,
            $severity,
            array('line_id' => $line['id'] ?? '', 'line_name' => $name, 'line_phone' => $phone, 'health_status' => $health, 'kind' => 'waha_down'),
            true
        );
    }
    return $generated;
}

function avisos_generate_backup_failed() {
    $generated = array();
    $logFile = DATA_PATH . '/cron_backup.log';
    if (!is_file($logFile)) return $generated;

    $raw = @file_get_contents($logFile);
    if ($raw === false || trim($raw) === '') return $generated;

    $lastOkTs = 0;
    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        if (strpos($line, 'Backup finalizado') === false) continue;
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
            $ts = strtotime($m[1]);
            if ($ts) $lastOkTs = max($lastOkTs, $ts);
        }
    }

    if ($lastOkTs > 0 && (time() - $lastOkTs) < 3 * 3600) return $generated;

    $generated[] = aviso_make(
        'ops',
        'backup_failed_' . date('Y-m-d'),
        'Backup automático sin completar',
        'No se detecta un backup completado en las últimas horas (' . ($lastOkTs > 0 ? 'último: ' . date('d/m/Y H:i', $lastOkTs) : 'sin registro en el log') . '). Revisa el cron de respaldo.',
        'alta',
        array('last_ok_at' => $lastOkTs > 0 ? date('Y-m-d H:i:s', $lastOkTs) : '', 'kind' => 'backup_failed'),
        true
    );

    return $generated;
}

function aviso_llm_api_key_for_deepseek() {
    $key = trim((string)getenv('PUBLICISTA_COPY_API_KEY'));
    if ($key === '') {
        $settings = storage_read('settings.json');
        $key = trim((string)($settings['publicista_copy_api_key'] ?? ''));
    }
    return $key;
}

function aviso_llm_api_key_for_openai() {
    $key = trim((string)getenv('OPENAI_API_KEY'));
    if ($key === '') {
        $settings = storage_read('settings.json');
        $key = trim((string)($settings['voice_ai_api_key'] ?? ''));
    }
    return $key;
}

function aviso_llm_throttle_should_run($key, $intervalSec) {
    $path = DATA_PATH . '/avisos_llm_check_state.json';
    $state = array();
    if (is_file($path)) {
        $raw = @file_get_contents($path);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $state = $decoded;
    }
    $last = isset($state[$key]) ? (int)$state[$key] : 0;
    if ($last > 0 && (time() - $last) < (int)$intervalSec) return false;
    $state[$key] = time();
    @file_put_contents($path, json_encode($state), LOCK_EX);
    return true;
}

function avisos_generate_deepseek_balance() {
    $generated = array();
    // Throttle: no consultar el saldo cada minuto; basta cada 6 horas.
    if (!aviso_llm_throttle_should_run('deepseek_balance', 6 * 3600)) return $generated;
    $key = aviso_llm_api_key_for_deepseek();
    if ($key === '') return $generated;

    $ch = curl_init('https://api.deepseek.com/user/balance');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $key, 'Accept: application/json'),
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
    ));
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $http === 0) return $generated; // error de red: reintento en el próximo cron

    $data = json_decode($body, true);
    if (!is_array($data)) return $generated;

    if ($http !== 200) {
        $msg = trim((string)($data['error']['message'] ?? ($data['message'] ?? '')));
        $generated[] = aviso_make(
            'ops',
            'deepseek_balance_error_' . date('Y-m-d'),
            'DeepSeek: error de saldo/API',
            'No se pudo consultar el saldo de DeepSeek (HTTP ' . $http . ').' . ($msg !== '' ? ' ' . $msg : '') . ' El bot-casa y el comercial podrían estar sin créditos.',
            'alta',
            array('http_code' => $http, 'error' => $msg, 'kind' => 'llm_balance'),
            true
        );
        return $generated;
    }

    $available = !empty($data['is_available']);
    $infos = isset($data['balance_infos']) && is_array($data['balance_infos']) ? $data['balance_infos'] : array();
    $usd = null;
    foreach ($infos as $info) {
        if (!is_array($info)) continue;
        $currency = trim((string)($info['currency'] ?? ''));
        $total = (float)($info['total_balance'] ?? 0);
        if ($currency === 'USD') {
            $usd = $total;
            break;
        }
        if ($usd === null) $usd = $total; // fallback a la primera divisa
    }

    if (!$available || ($usd !== null && $usd <= 0.0)) {
        $generated[] = aviso_make(
            'ops',
            'deepseek_balance_low_' . date('Y-m-d'),
            'DeepSeek: saldo agotado',
            'La cuenta de DeepSeek no tiene saldo disponible' . ($usd !== null ? ' (saldo: $' . number_format($usd, 2) . ')' : '') . '. El bot-casa y el comercial dejarán de responder.',
            'alta',
            array('available' => $available, 'balance_usd' => $usd, 'kind' => 'llm_balance'),
            true
        );
    }

    return $generated;
}

function avisos_generate_openai_balance() {
    $generated = array();
    // Throttle: no consultar el saldo cada minuto; basta cada 6 horas.
    if (!aviso_llm_throttle_should_run('openai_balance', 6 * 3600)) return $generated;
    $key = aviso_llm_api_key_for_openai();
    if ($key === '') return $generated;

    // Best-effort: el endpoint de billing de OpenAI no está disponible en todas las cuentas.
    $ch = curl_init('https://api.openai.com/v1/dashboard/billing/credit_grants');
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $key, 'Accept: application/json'),
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
    ));
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $http === 0) return $generated;

    $data = json_decode($body, true);
    if (!is_array($data)) return $generated;

    if ($http === 200) {
        $totalGranted = (float)($data['total_granted'] ?? 0);
        $totalUsed = (float)($data['total_used'] ?? 0);
        $remaining = $totalGranted - $totalUsed;
        if ($remaining <= 0.0) {
            $generated[] = aviso_make(
                'ops',
                'openai_balance_low_' . date('Y-m-d'),
                'OpenAI: saldo agotado',
                'La cuenta de OpenAI no tiene saldo restante. El publicista y la generación de textos dejarán de funcionar.',
                'alta',
                array('remaining' => $remaining, 'kind' => 'llm_balance'),
                true
            );
        }
    }
    // Otros códigos (401/403 por permisos de billing) se ignoran para no generar ruido.

    return $generated;
}

// ── Puentes bot-casa → CRM (solo lectura) ────────────────────────────────────

function aviso_botcasa_base_dir() {
    return BASE_PATH . '/bot-casa/data';
}

function aviso_botcasa_read_json($relPath) {
    $path = aviso_botcasa_base_dir() . '/' . ltrim($relPath, '/');
    if (!is_file($path)) return null;
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function aviso_botcasa_read_ndjson($relPath, $limit = 200) {
    $path = aviso_botcasa_base_dir() . '/' . ltrim($relPath, '/');
    if (!is_file($path)) return array();
    $raw = @file_get_contents($path);
    if ($raw === false) return array();
    $out = array();
    $lines = preg_split('/\r\n|\r|\n/', $raw);
    $lines = array_slice($lines, -max(1, (int)$limit));
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $row = json_decode($line, true);
        if (is_array($row)) $out[] = $row;
    }
    return $out;
}

function avisos_generate_botcasa_paypal() {
    $generated = array();
    $usersData = aviso_botcasa_read_json('users.json');
    $users = isset($usersData['users']) && is_array($usersData['users']) ? $usersData['users'] : array();
    $now = time();

    foreach ($users as $user) {
        if (!is_array($user)) continue;
        $userId = trim((string)($user['id'] ?? ''));
        $payments = isset($user['payments']) && is_array($user['payments']) ? $user['payments'] : array();
        foreach ($payments as $pay) {
            if (!is_array($pay)) continue;
            if (trim((string)($pay['gateway'] ?? '')) !== 'paypal') continue;
            $txnId = trim((string)($pay['transaction_id'] ?? ''));
            $date = trim((string)($pay['date'] ?? ''));
            $ts = $date !== '' ? strtotime($date) : 0;
            if (!$ts || ($now - $ts) > 24 * 3600) continue;

            $key = $txnId !== '' ? $txnId : ('u' . $userId . '_p' . ($pay['id'] ?? ''));
            if (aviso_exists_any_status('botcasa', 'paypal_' . $key)) continue;

            $amount = (float)($pay['amount'] ?? 0);
            $name = trim((string)($user['name'] ?? ($user['username'] ?? '')));
            $generated[] = aviso_make(
                'botcasa',
                'paypal_' . $key,
                'Pago PayPal recibido (bot-casa)',
                'Se ha registrado un pago PayPal de ' . euro($amount) . ' para ' . ($name !== '' ? $name : ('usuario ' . $userId)) . '.',
                'alta',
                array('user_id' => $userId, 'amount' => $amount, 'transaction_id' => $txnId, 'kind' => 'paypal'),
                true
            );
        }
    }

    return $generated;
}

function avisos_generate_botcasa_lead() {
    $generated = array();
    $now = time();
    $usersDir = aviso_botcasa_base_dir() . '/users';
    if (!is_dir($usersDir)) return $generated;

    foreach (glob($usersDir . '/*', GLOB_ONLYDIR) as $userDir) {
        $leadsFile = $userDir . '/leads.ndjson';
        if (!is_file($leadsFile)) continue;
        $leads = aviso_botcasa_read_ndjson('users/' . basename($userDir) . '/leads.ndjson');
        foreach ($leads as $lead) {
            $threadId = trim((string)($lead['thread_id'] ?? ''));
            if ($threadId === '') continue;
            $ts = strtotime((string)($lead['ts'] ?? ''));
            if (!$ts || ($now - $ts) > 24 * 3600) continue;
            if (aviso_exists_any_status('botcasa', 'lead_' . md5($threadId))) continue;

            $phone = preg_replace('/[^0-9]/', '', (string)($lead['phone'] ?? ''));
            $girl = trim((string)($lead['selected_girl_name'] ?? ''));
            $line = trim((string)($lead['line_label'] ?? ''));
            $eta = (int)($lead['eta_minutes'] ?? 0);
            $conf = (float)($lead['lead_confidence'] ?? 0);
            $confPct = $conf > 0 ? round($conf * 100) : 0;
            $msg = trim((string)($lead['user_message'] ?? ($lead['message_text'] ?? '')));

            $detail = '';
            $head = '📱 ' . ($phone !== '' ? $phone : '?');
            if ($girl !== '') $head .= ' · 👤 ' . $girl;
            if ($line !== '') $head .= ' · 📍 ' . $line;
            $detail .= $head . "\n";
            $meta = array();
            if ($confPct > 0) $meta[] = '🎯 Confianza ' . $confPct . '%';
            if ($eta > 0) $meta[] = '⏱ ETA ' . $eta . ' min';
            if (!empty($meta)) $detail .= implode(' · ', $meta) . "\n";
            if ($msg !== '') $detail .= '💬 "' . mb_substr($msg, 0, 120, 'UTF-8') . '"';

            $generated[] = aviso_make(
                'botcasa',
                'lead_' . md5($threadId),
                'Lead bot-casa',
                $detail,
                'alta',
                array('thread_id' => $threadId, 'phone' => $phone, 'kind' => 'lead'),
                true
            );
        }
    }

    return $generated;
}

function avisos_generate_botcasa_new_user() {
    $generated = array();
    $usersData = aviso_botcasa_read_json('users.json');
    $users = isset($usersData['users']) && is_array($usersData['users']) ? $usersData['users'] : array();
    $now = time();

    foreach ($users as $user) {
        if (!is_array($user)) continue;
        $userId = trim((string)($user['id'] ?? ''));
        if ($userId === '') continue;
        $createdAt = trim((string)($user['created_at'] ?? ''));
        $ts = $createdAt !== '' ? strtotime($createdAt) : 0;
        if (!$ts || ($now - $ts) > 24 * 3600) continue;
        if (aviso_exists_any_status('botcasa', 'newuser_' . $userId)) continue;

        $name = trim((string)($user['name'] ?? ($user['username'] ?? '')));
        $generated[] = aviso_make(
            'botcasa',
            'newuser_' . $userId,
            'Nueva alta en bot-casa',
            'Se ha registrado un nuevo usuario en bot-casa: ' . ($name !== '' ? $name : ('usuario ' . $userId)) . '.',
            'alta',
            array('user_id' => $userId, 'name' => $name, 'kind' => 'new_user'),
            true
        );
    }

    return $generated;
}

function avisos_generate_botcasa_reminder() {
    $generated = array();
    $now = time();
    $usersDir = aviso_botcasa_base_dir() . '/users';
    if (!is_dir($usersDir)) return $generated;

    foreach (glob($usersDir . '/*', GLOB_ONLYDIR) as $userDir) {
        $remindersFile = $userDir . '/reminders_pending.ndjson';
        if (!is_file($remindersFile)) continue;
        $reminders = aviso_botcasa_read_ndjson('users/' . basename($userDir) . '/reminders_pending.ndjson');
        foreach ($reminders as $rem) {
            $threadId = trim((string)($rem['thread_id'] ?? ''));
            $tsCreated = trim((string)($rem['ts_created'] ?? ''));
            $ts = $tsCreated !== '' ? strtotime($tsCreated) : 0;
            if (!$ts || ($now - $ts) > 24 * 3600) continue;

            $key = md5(($threadId !== '' ? $threadId : '') . '|' . $tsCreated);
            if (aviso_exists_any_status('botcasa', 'reminder_' . $key)) continue;

            $phone = preg_replace('/[^0-9]/', '', (string)($rem['phone'] ?? ''));
            $eta = (int)($rem['eta_minutes'] ?? 0);
            $line = trim((string)($rem['line_label'] ?? ''));

            $generated[] = aviso_make(
                'botcasa',
                'reminder_' . $key,
                'Visita en camino (bot-casa)',
                'Cliente en camino' . ($phone !== '' ? ' 📞 ' . $phone : '') . ($line !== '' ? ' 📍 ' . $line : '') . ($eta > 0 ? ' ⏱ llega en ~' . $eta . ' min' : '') . '.',
                'alta',
                array('phone' => $phone, 'eta_minutes' => $eta, 'kind' => 'reminder'),
                true
            );
        }
    }

    return $generated;
}

function avisos_run_all_generators($sendWhatsapp = true) {
    $runId = generate_id('arun');
    $startedAt = now_datetime();
    $allStats = array();

    aviso_cli_log('=== Inicio cron avisos | run_id=' . $runId . ' | sendWhatsapp=' . ($sendWhatsapp ? 'yes' : 'no') . ' ===');

    $plannedActivated = avisos_activate_planned_manuals($sendWhatsapp);
    aviso_cli_log('[manual] activados_desde_planificados=' . (int)$plannedActivated);

    if (function_exists('publicista_free_bump_run_due')) {
        $publicistaRun = publicista_free_bump_run_due(false);
        aviso_cli_log('[publicista_free_bump] status=' . trim((string)($publicistaRun['status'] ?? 'unknown')) . ' next=' . trim((string)($publicistaRun['next_run_at'] ?? '')));
    }

    if (function_exists('publicista_estados_wasap_run_due')) {
        $estadosWasapRun = publicista_estados_wasap_run_due();
        aviso_cli_log('[publicista_estados_wasap] published=' . ($estadosWasapRun['published'] ? 'yes' : 'no') . ' reason=' . ($estadosWasapRun['reason'] ?? 'published'));
    }

    if (function_exists('publicista_afiliados_run_due')) {
        $afiliadosRun = publicista_afiliados_run_due();
        aviso_cli_log('[publicista_afiliados] published=' . ($afiliadosRun['published'] ? 'yes' : 'no') . ' reason=' . ($afiliadosRun['reason'] ?? 'published') . ' next=' . trim((string)($afiliadosRun['next_check'] ?? '')));
    }

    if (function_exists('publicista_afiliados_destacamos_run_due')) {
        $afiliadosDestacamosRun = publicista_afiliados_destacamos_run_due();
        aviso_cli_log('[publicista_afiliados_destacamos] published=' . ($afiliadosDestacamosRun['published'] ? 'yes' : 'no') . ' reason=' . trim((string)($afiliadosDestacamosRun['reason'] ?? 'published')));
    }

    if (function_exists('publicista_campaign_auto_rotation_run_due')) {
        $campaignAutoRotationRun = publicista_campaign_auto_rotation_run_due();
        aviso_cli_log('[publicista_campaign_auto_rotation] status=' . trim((string)($campaignAutoRotationRun['status'] ?? 'unknown')) . ' campaign_id=' . trim((string)($campaignAutoRotationRun['campaign_id'] ?? '')) . ' next=' . trim((string)($campaignAutoRotationRun['next_run_at'] ?? '')));
    }

    $allStats[] = avisos_sync_generated(
        //avisos_generate_after_10am(),
        'hora',
        $sendWhatsapp,
        $runId
    );

    $allStats[] = avisos_sync_generated(
        array_merge(
            avisos_generate_month_income_milestones(),
            avisos_generate_month_profit_milestones(),
            avisos_generate_month_profit_starts(),
            avisos_generate_record_daily_income(),
            avisos_generate_record_daily_profit(),
            avisos_generate_record_month_income()
        ),
        'milestones',
        $sendWhatsapp,
        $runId
    );

    $allStats[] = avisos_sync_generated(
        array_merge(
            avisos_generate_no_income_24h(),
            avisos_generate_no_income_48h(),
            avisos_generate_full_day_without_movements()
        ),
        'inactivity',
        $sendWhatsapp,
        $runId
    );

    $allStats[] = avisos_sync_generated(
        array_merge(
            avisos_generate_first_income_of_day(),
            avisos_generate_high_expense_today(),

            avisos_generate_lamami_new_clientas(),
            avisos_generate_lamami_new_leads(),
            avisos_generate_jostal_new_clientas(),
            avisos_generate_jostal_new_leads(),
            avisos_generate_jostal_new_sales(),
            avisos_generate_casawasap_new_clientes(),
            avisos_generate_casawasap_new_pagos()
        ),
        'events',
        $sendWhatsapp,
        $runId
    );

    $allStats[] = avisos_sync_generated(
        array_merge(
            //avisos_generate_casawasap_alquiler_due_today(),
            avisos_generate_jostal_alquiler_due_tomorrow(),
            avisos_generate_jostal_alquiler_due(),
            //avisos_generate_casawasap_alquiler_overdue_1w(),
            //avisos_generate_lamami_publicidad_due_today(),
            avisos_generate_lamami_publicidad_due(),
            //avisos_generate_lamami_publicidad_overdue_1w(),
            avisos_generate_many_renewals_due_today(),
            avisos_generate_mundosex_publish_reminders()
        ),
        'recurring',
        $sendWhatsapp,
        $runId
    );

    $allStats[] = avisos_sync_generated(
        array_merge(
            avisos_generate_unattended_interesadas_6h(),
            avisos_generate_lamami_atendidas_24h()
        ),
        'attention',
        $sendWhatsapp,
        $runId
    );

    $allStats[] = avisos_sync_generated(
        avisos_generate_overdue_interesadas_all(),
        'overdue',
        $sendWhatsapp,
        $runId
    );

    $allStats[] = avisos_sync_generated(
        array_merge(
            avisos_generate_lamami_clienta_without_bot(),
            avisos_generate_bots_linked_to_baja_clienta(),
            avisos_generate_bots_missing_memory_file(),
            avisos_generate_casawasap_alquiler_missing_cliente_at(),
            avisos_generate_jostal_multiple_open_periods(),
            avisos_generate_jostal_invalid_period_dates(),
            avisos_generate_telefonos_broken_destacamos_links(),
            avisos_generate_too_many_active_alerts(),
            avisos_generate_bots_without_clienta(),
            avisos_generate_incomplete_anuncios()
        ),
        'integrity',
        $sendWhatsapp,
        $runId
    );

    $allStats[] = avisos_sync_generated(
        array_merge(
            avisos_generate_lamami_clientas_without_leads_7d(),
            avisos_generate_jostal_clientas_en_casa_without_income_7d(),
            avisos_generate_casawasap_clientes_without_pagos_7d()
        ),
        'performance',
        $sendWhatsapp,
        $runId
    );

    $allStats[] = avisos_sync_generated(
        array_merge(
            avisos_generate_branch_leader_change(),
            avisos_generate_branch_concentration_high(),
            avisos_generate_month_projection_below_previous(),
            avisos_generate_negative_trend_3_days()
        ),
        'strategic',
        $sendWhatsapp,
        $runId
    );

    // ── OPS: salud de líneas, backup y saldos de IA ──
    $allStats[] = avisos_sync_generated(
        array_merge(
            avisos_generate_line_ban(),
            avisos_generate_line_send_failures(),
            avisos_generate_waha_down(),
            avisos_generate_backup_failed(),
            avisos_generate_deepseek_balance(),
            avisos_generate_openai_balance()
        ),
        'ops',
        $sendWhatsapp,
        $runId
    );

    // ── Puentes bot-casa → CRM ──
    $allStats[] = avisos_sync_generated(
        array_merge(
            avisos_generate_botcasa_paypal(),
            avisos_generate_botcasa_lead(),
            avisos_generate_botcasa_new_user(),
            avisos_generate_botcasa_reminder()
        ),
        'botcasa',
        $sendWhatsapp,
        $runId
    );

    // ── Pollo.ai: aviso de cuentas sin créditos (comprobación periódica) ──
    if (function_exists('publicista_pollo_check_and_alert')) {
        publicista_pollo_check_and_alert();
    }

    if ($sendWhatsapp && aviso_noise_profile() !== 'agresivo') {
        $allStats[] = avisos_retry_pending_whatsapp();
    }

    $summary = array(
        'generated' => 0,
        'created' => 0,
        'reactivated' => 0,
        'updated' => 0,
        'resolved' => 0,
        'dismissed_persistent' => 0,
        'whatsapp_sent' => 0,
        'whatsapp_failed' => 0,
    );

    foreach ($allStats as $stats) {
        foreach (array_keys($summary) as $key) {
            $summary[$key] += (int)($stats[$key] ?? 0);
        }
    }

    $finishedAt = now_datetime();
    aviso_log_run(array(
        'id' => $runId,
        'started_at' => $startedAt,
        'finished_at' => $finishedAt,
        'send_whatsapp' => $sendWhatsapp ? true : false,
        'summary' => $summary,
        'engines' => $allStats,
    ));

    aviso_cli_log('=== Fin cron avisos | ' . aviso_build_run_summary($summary) . ' ===');

    return array(
        'run_id' => $runId,
        'started_at' => $startedAt,
        'finished_at' => $finishedAt,
        'summary' => $summary,
        'engines' => $allStats,
    );
}
