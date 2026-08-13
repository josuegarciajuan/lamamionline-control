<?php
/**
 * personal_wasap_api.php — API para el chat WhatsApp Personal de Josue.
 *
 * Endpoints:
 *   GET  ?action=chats                  → lista de conversaciones
 *   GET  ?action=messages&chat_id=X     → mensajes de un chat
 *   GET  ?action=qr                     → URL del QR para escanear
 *   GET  ?action=status                 → estado de conexión WAHA
 *   POST ?action=send                   → enviar mensaje via WAHA
 *   POST ?action=mark_read&chat_id=X    → marcar mensajes como leídos
 *   POST ?action=rename_contact         → renombrar contacto
 */

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

// Lightweight auth: allow whitelisted IPs + logged-in sessions
// For logged-out users (e.g., first page load before login), allow with a simple token
$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenValid = ($token !== '' && $token === 'wasap_personal_2026');

if (!is_logged_in() && !$tokenValid) {
    auth_auto_login_from_whitelist();
}

if (!is_logged_in() && !$tokenValid) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

// ── Config WAHA ──
define('WASAP_WAHA_PORT', '3031');
define('WASAP_WAHA_HOST', 'http://100.117.92.74');
define('WASAP_WAHA_KEY', 'local321');
define('WASAP_WAHA_SESSION', 'default');

// ── Debug logging ──
function wasap_debug_log(string $action, array $data): void {
    $logPath = __DIR__ . '/data/personal_wasap_debug.log';
    $dir = dirname($logPath);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $entry = date('Y-m-d H:i:s') . ' | ' . $action . ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @file_put_contents($logPath, $entry . "\n", FILE_APPEND | LOCK_EX);
}

// ── Helpers ──
function wasap_store_path(): string {
    return __DIR__ . '/data/personal_wasap_data.json';
}

function wasap_store_read(): array {
    $path = wasap_store_path();
    if (!file_exists($path)) return ['chats' => [], 'contacts_index' => [], 'learning' => [], 'meta' => []];
    $fh = @fopen($path, 'r');
    if (!$fh) return ['chats' => [], 'contacts_index' => [], 'learning' => [], 'meta' => []];
    // Lock compartido para lectura segura (evita leer datos a medio escribir)
    if (!flock($fh, LOCK_SH)) {
        fclose($fh);
        return ['chats' => [], 'contacts_index' => [], 'learning' => [], 'meta' => []];
    }
    $raw = '';
    while (!feof($fh)) {
        $chunk = fread($fh, 8192);
        if ($chunk === false) break;
        $raw .= $chunk;
    }
    flock($fh, LOCK_UN);
    fclose($fh);
    if ($raw === '') return ['chats' => [], 'contacts_index' => [], 'learning' => [], 'meta' => []];
    $data = json_decode($raw, true);
    if (!is_array($data)) return ['chats' => [], 'contacts_index' => [], 'learning' => [], 'meta' => []];
    return $data;
}

function wasap_store_write(array $data): void {
    $path = wasap_store_path();
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    // Lock exclusivo durante todo el ciclo read-merge-write para evitar race conditions
    $fh = @fopen($path, 'c+');
    if (!$fh) return;

    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        return;
    }

    $raw = '';
    while (!feof($fh)) {
        $chunk = fread($fh, 8192);
        if ($chunk === false) break;
        $raw .= $chunk;
    }

    $existing = [];
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $existing = $decoded;
    }

    if (!isset($existing['chats'])) $existing['chats'] = [];
    if (!isset($existing['contacts_index'])) $existing['contacts_index'] = [];
    if (!isset($existing['learning'])) $existing['learning'] = [];
    if (!isset($existing['meta'])) $existing['meta'] = [];

    // Merge chats: combinar por chatId, mensajes por ID (evita duplicados)
    if (isset($data['chats'])) {
        foreach ($data['chats'] as $chatId => $chatData) {
            if (!isset($existing['chats'][$chatId])) {
                $existing['chats'][$chatId] = $chatData;
            } else {
                $existingIds = [];
                foreach ($existing['chats'][$chatId]['messages'] ?? [] as $em) {
                    $existingIds[$em['id'] ?? ''] = true;
                }
                foreach ($chatData['messages'] ?? [] as $nm) {
                    $nid = $nm['id'] ?? '';
                    if ($nid !== '' && !isset($existingIds[$nid])) {
                        $existing['chats'][$chatId]['messages'][] = $nm;
                        $existingIds[$nid] = true;
                    }
                }
                if (isset($chatData['contact_name'])) $existing['chats'][$chatId]['contact_name'] = $chatData['contact_name'];
                if (isset($chatData['contact_phone'])) $existing['chats'][$chatId]['contact_phone'] = $chatData['contact_phone'];
                if (isset($chatData['last_message_at'])) $existing['chats'][$chatId]['last_message_at'] = $chatData['last_message_at'];
                if (isset($chatData['unread_count'])) $existing['chats'][$chatId]['unread_count'] = $chatData['unread_count'];
            }
        }
    }

    // ── Aplicar limite de 500 mensajes por chat en el resultado final del merge ──
    foreach ($existing['chats'] as $chatId => &$chat) {
        if (isset($chat['messages']) && count($chat['messages']) > 500) {
            $chat['messages'] = array_slice($chat['messages'], -500);
        }
    }
    unset($chat);

    if (isset($data['contacts_index'])) {
        foreach ($data['contacts_index'] as $key => $val) {
            $existing['contacts_index'][$key] = $val;
        }
    }

    if (isset($data['learning'])) {
        foreach ($data['learning'] as $key => $val) {
            if (is_array($val) && isset($existing['learning'][$key]) && is_array($existing['learning'][$key])) {
                $existing['learning'][$key] = array_merge($existing['learning'][$key], $val);
            } else {
                $existing['learning'][$key] = $val;
            }
        }
    }

    // ── Limitar pending_classification a 50 en el resultado final del merge ──
    if (isset($existing['learning']['pending_classification']) && count($existing['learning']['pending_classification']) > 50) {
        $existing['learning']['pending_classification'] = array_slice($existing['learning']['pending_classification'], -50);
    }

    $existing['meta'] = array_merge($existing['meta'], $data['meta'] ?? []);

    // ── Memory guard: si el proceso está cerca del límite, abortar la escritura ──
    $memLimitStr = ini_get('memory_limit');
    $memLimitBytes = (int)$memLimitStr;
    if (stripos($memLimitStr, 'G') !== false) { $memLimitBytes = (int)$memLimitStr * 1073741824; }
    elseif (stripos($memLimitStr, 'M') !== false) { $memLimitBytes = (int)$memLimitStr * 1048576; }
    elseif (stripos($memLimitStr, 'K') !== false) { $memLimitBytes = (int)$memLimitStr * 1024; }
    if ($memLimitBytes <= 0) $memLimitBytes = 134217728; // default 128M
    $memUsage = memory_get_usage(true);
    if ($memUsage > $memLimitBytes * 0.85) {
        flock($fh, LOCK_UN);
        fclose($fh);
        return;
    }

    // ── CRITICAL: json_encode BEFORE ftruncate para evitar pérdida de datos ──
    // Si json_encode falla por memory exhaustion, el archivo NO se trunca
    $json = json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) {
        flock($fh, LOCK_UN);
        fclose($fh);
        return;
    }

    // Backup de seguridad: respaldar contenido antiguo antes de truncar (solo si hay memoria)
    if ($raw !== '' && $memUsage < $memLimitBytes * 0.75) {
        @file_put_contents($path . '.bak', $raw, LOCK_EX);
    }

    // Ahora sí, truncar y escribir es seguro porque ya tenemos el JSON listo
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, $json);
    fflush($fh);

    flock($fh, LOCK_UN);
    fclose($fh);
}

function wasap_waha_call(string $method, string $path, ?array $body = null): array {
    $url = WASAP_WAHA_HOST . ':' . WASAP_WAHA_PORT . $path;
    $ch = curl_init($url);
    $headers = ['Accept: application/json', 'X-Api-Key: ' . WASAP_WAHA_KEY];
    $opts = [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    } elseif ($method === 'PUT') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $response === '') {
        return ['ok' => false, 'error' => $error ?: 'Empty response', 'http_code' => $httpCode];
    }
    $decoded = json_decode($response, true);
    return ['ok' => $httpCode >= 200 && $httpCode < 300, 'data' => $decoded, 'http_code' => $httpCode];
}

function wasap_extract_phone_from_chatid(string $chatId): string {
    return preg_replace('/[^0-9]/', '', explode('@', $chatId)[0]);
}

// ── Router ──
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = trim((string)($_GET['action'] ?? 'chats'));

header('Content-Type: application/json; charset=utf-8');

switch ($action) {

    // ── Lista de chats ──
    case 'chats':
        $store = wasap_store_read();
        $chats = [];
        foreach ($store['chats'] as $chatId => $chat) {
            $phone = wasap_extract_phone_from_chatid($chatId);
            $msgs = $chat['messages'] ?? [];
            $lastMsg = !empty($msgs) ? end($msgs) : null;
            $chats[] = [
                'chat_id' => $chatId,
                'phone' => $chat['contact_phone'] ?? $phone,
                'contact_name' => $chat['contact_name'] ?? '',
                'last_message' => $lastMsg ? (mb_strlen($lastMsg['text'] ?? '') > 40 ? mb_substr($lastMsg['text'], 0, 37) . '...' : $lastMsg['text']) : '',
                'last_message_at' => $chat['last_message_at'] ?? '',
                'unread_count' => $chat['unread_count'] ?? 0,
            ];
        }
        // Ordenar: más reciente primero
        usort($chats, function ($a, $b) {
            return ($b['last_message_at'] ?? '') <=> ($a['last_message_at'] ?? '');
        });
        echo json_encode(['ok' => true, 'chats' => $chats, 'count' => count($chats)]);
        break;

    // ── Mensajes de un chat ──
    case 'messages':
        $chatId = trim((string)($_GET['chat_id'] ?? ''));
        if ($chatId === '') {
            echo json_encode(['ok' => false, 'error' => 'chat_id required']);
            break;
        }
        $store = wasap_store_read();
        $chat = $store['chats'][$chatId] ?? null;
        if (!$chat) {
            echo json_encode(['ok' => true, 'messages' => [], 'chat' => ['contact_name' => '', 'contact_phone' => wasap_extract_phone_from_chatid($chatId)]]);
            break;
        }
        $messages = $chat['messages'] ?? [];
        // Ordenar cronológicamente
        usort($messages, function ($a, $b) {
            return ($a['ts'] ?? '') <=> ($b['ts'] ?? '');
        });
        echo json_encode([
            'ok' => true,
            'messages' => $messages,
            'chat' => [
                'chat_id' => $chatId,
                'contact_name' => $chat['contact_name'] ?? '',
                'contact_phone' => $chat['contact_phone'] ?? wasap_extract_phone_from_chatid($chatId),
                'unread_count' => $chat['unread_count'] ?? 0,
            ],
            'count' => count($messages),
        ]);
        break;

    // ── Enviar mensaje via WAHA ──
    case 'send':
        if ($method !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'POST required']);
            break;
        }
        $chatId = trim((string)($_POST['chat_id'] ?? ''));
        $text = trim((string)($_POST['text'] ?? ''));
        if ($chatId === '' || $text === '') {
            echo json_encode(['ok' => false, 'error' => 'chat_id and text required']);
            break;
        }

        // Llamar a WAHA para enviar
        $wahaResult = wasap_waha_call('POST', '/api/sendText', [
            'chatId' => $chatId,
            'text' => $text,
            'session' => WASAP_WAHA_SESSION,
        ]);

        if (empty($wahaResult['ok'])) {
            echo json_encode(['ok' => false, 'error' => 'WAHA send failed: ' . ($wahaResult['error'] ?? 'unknown'), 'waha' => $wahaResult]);
            break;
        }

        // Guardar en store local
        $store = wasap_store_read();
        $now = date('c');
        // Usar el ID real de WAHA para que el webhook y el merge-dedup funcionen
        $msgId = (string)($wahaResult['data']['id'] ?? '');
        if ($msgId === '') $msgId = 'msg_sent_' . bin2hex(random_bytes(8));
        if (!isset($store['chats'][$chatId])) {
            $store['chats'][$chatId] = [
                'contact_name' => '',
                'contact_phone' => wasap_extract_phone_from_chatid($chatId),
                'messages' => [],
            ];
        }
        $store['chats'][$chatId]['messages'][] = [
            'id' => $msgId,
            'direction' => 'out',
            'from_me' => true,
            'text' => $text,
            'ts' => $now,
            'read' => true,
        ];
        $store['chats'][$chatId]['last_message_at'] = $now;

        // Actualizar stats
        $today = date('Y-m-d');
        if (!isset($store['learning']['daily_stats'])) $store['learning']['daily_stats'] = [];
        if (!isset($store['learning']['daily_stats'][$today])) {
            $store['learning']['daily_stats'][$today] = ['messages_sent' => 0, 'messages_received' => 0, 'contacts_talked_to' => []];
        }
        $store['learning']['daily_stats'][$today]['messages_sent']++;

        wasap_store_write($store);

        echo json_encode(['ok' => true, 'message_id' => $msgId, 'waha' => $wahaResult['data'] ?? null]);
        break;

    // ── Marcar como leído ──
    case 'mark_read':
        if ($method !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'POST required']);
            break;
        }
        $chatId = trim((string)($_POST['chat_id'] ?? ''));
        if ($chatId === '') {
            echo json_encode(['ok' => false, 'error' => 'chat_id required']);
            break;
        }
        $store = wasap_store_read();
        if (!isset($store['chats'][$chatId])) {
            echo json_encode(['ok' => true, 'marked' => 0]);
            break;
        }
        $marked = 0;
        foreach ($store['chats'][$chatId]['messages'] as &$msg) {
            if (!($msg['read'] ?? false) && ($msg['direction'] ?? '') === 'in') {
                $msg['read'] = true;
                $marked++;
            }
        }
        unset($msg);
        $store['chats'][$chatId]['unread_count'] = 0;
        wasap_store_write($store);
        echo json_encode(['ok' => true, 'marked' => $marked]);
        break;

    // ── Renombrar contacto ──
    case 'rename_contact':
        if ($method !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'POST required']);
            break;
        }
        $chatId = trim((string)($_POST['chat_id'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        if ($chatId === '' || $name === '') {
            echo json_encode(['ok' => false, 'error' => 'chat_id and name required']);
            break;
        }
        $store = wasap_store_read();
        if (!isset($store['chats'][$chatId])) {
            echo json_encode(['ok' => false, 'error' => 'chat not found']);
            break;
        }
        $store['chats'][$chatId]['contact_name'] = $name;
        $phone = wasap_extract_phone_from_chatid($chatId);
        if (isset($store['contacts_index'][$phone])) {
            $store['contacts_index'][$phone]['name'] = $name;
        }
        wasap_store_write($store);
        echo json_encode(['ok' => true, 'contact_name' => $name]);
        break;

    // ── QR code (PHP proxy: server-side cURL → base64) ──
    case 'qr':
        $wahaPort = WASAP_WAHA_PORT;
        $wahaHost = WASAP_WAHA_HOST;
        $qrUrl = "{$wahaHost}:{$wahaPort}/api/default/auth/qr?format=image";
        $lastError = '';

        wasap_debug_log('qr_attempt', ['port' => $wahaPort, 'url' => $qrUrl]);

        // Retry up to 3 times with 5s timeout — faster feedback if WAHA is down
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init($qrUrl);
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Api-Key: ' . WASAP_WAHA_KEY],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode === 200 && $response !== false && $response !== '') {
                $data = json_decode($response, true);
                if (is_array($data) && !empty($data['data'])) {
                    $b64 = (string)$data['data'];
                    wasap_debug_log('qr_success', ['attempt' => $attempt, 'http_code' => $httpCode, 'base64_len' => strlen($b64)]);
                    echo json_encode(['ok' => true, 'qr_base64' => $b64]);
                    break;
                }
                $lastError = 'WAHA responded but no QR data (keys: ' . implode(',', array_keys($data ?: [])) . ')';
            } elseif ($response === false || $response === '') {
                $lastError = "WAHA no responde (intento {$attempt}/3)";
                if ($curlError !== '') $lastError .= ': ' . $curlError;
            } else {
                $lastError = "HTTP {$httpCode} (intento {$attempt}/3)";
                $preview = mb_substr($response, 0, 200);
                wasap_debug_log('qr_unexpected_response', ['http_code' => $httpCode, 'attempt' => $attempt, 'preview' => $preview]);
            }

            if ($attempt < 3) sleep(1);
        }

        if (empty($data['data'] ?? null)) {
            wasap_debug_log('qr_failed', ['error' => $lastError, 'attempts' => $attempt]);
            echo json_encode(['ok' => false, 'error' => $lastError, 'hint' => 'WAHA parece caído. Prueba el botón "Reiniciar WAHA".']);
        }
        break;

    // ── Estado de conexión WAHA (mejorado con diagnóstico) ──
    case 'status':
        $wahaPort = WASAP_WAHA_PORT;
        $wahaHost = WASAP_WAHA_HOST;
        $statusUrl = "{$wahaHost}:{$wahaPort}/api/sessions/default";

        $ch = curl_init($statusUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Api-Key: ' . WASAP_WAHA_KEY],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false || $response === '') {
            wasap_debug_log('status_failed', ['http_code' => $httpCode, 'curl_error' => $curlError]);
            echo json_encode(['ok' => false, 'error' => 'WAHA unreachable (HTTP ' . $httpCode . ')', 'curl_error' => $curlError]);
            break;
        }

        $session = json_decode($response, true);
        if (!is_array($session)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid WAHA response']);
            break;
        }

        $status = (string)($session['status'] ?? 'UNKNOWN');
        $statusLabel = match ($status) {
            'WORKING', 'CONNECTED' => 'Conectado',
            'SCAN_QR_CODE' => 'Esperando escanear QR',
            'STARTING' => 'Iniciando...',
            'STOPPED' => 'Detenido',
            'FAILED' => 'Fallida — necesita reinicio',
            default => $status,
        };
        $statusIcon = match ($status) {
            'WORKING', 'CONNECTED' => '🟢',
            'SCAN_QR_CODE' => '🟡',
            'STARTING' => '🟠',
            default => '🔴',
        };

        $healthPhone = '';
        $me = $session['me'] ?? null;
        if (is_array($me) && !empty($me['id'])) {
            $healthPhone = preg_replace('/[^0-9]/', '', (string)$me['id']);
        }

        // ── Webhook auto-healing: reconfigurar si se perdió (ej. WAHA se auto-recuperó) ──
        if ($status === 'WORKING' || $status === 'CONNECTED') {
            $wahaConfig = $session['config']['webhooks'] ?? null;
            $expectedWebhook = 'http://100.76.30.118/control/personal_wasap_webhook.php';
            $hasWebhook = false;
            if (is_array($wahaConfig)) {
                foreach ($wahaConfig as $wh) {
                    if (strpos((string)($wh['url'] ?? ''), 'personal_wasap_webhook.php') !== false) {
                        $hasWebhook = true;
                        break;
                    }
                }
            }
            if (!$hasWebhook) {
                wasap_debug_log('webhook_auto_heal', ['status' => $status, 'had_webhook' => false]);
                $fixResult = wasap_waha_call('PUT', '/api/sessions/' . (string)WASAP_WAHA_SESSION, [
                    'config' => ['webhooks' => [['url' => $expectedWebhook, 'events' => ['message', 'message.any']]]],
                ]);
                wasap_debug_log('webhook_auto_heal_result', [
                    'ok' => $fixResult['ok'] ?? false,
                    'http_code' => $fixResult['http_code'] ?? 0,
                ]);
            }
        }

        $store = wasap_store_read();
        $responsePayload = [
            'ok' => true,
            'status' => $status,
            'status_label' => $statusLabel,
            'status_icon' => $statusIcon,
            'qr_available' => ($status === 'SCAN_QR_CODE' || $status === 'STARTING'),
            'is_connected' => ($status === 'WORKING' || $status === 'CONNECTED'),
            'phone' => $healthPhone ?: '654464023',
            'health_phone' => $healthPhone,
            'last_sync' => $store['meta']['last_sync'] ?? null,
        ];
        wasap_debug_log('status_check', ['status' => $status, 'health_phone' => $healthPhone ? 'yes' : 'no']);
        echo json_encode($responsePayload);
        break;

    // ── Obtener todos los mensajes no leídos (para Jefry) ──
    case 'unread':
        $store = wasap_store_read();
        $unread = [];
        foreach ($store['chats'] as $chatId => $chat) {
            $contactName = $chat['contact_name'] ?: wasap_extract_phone_from_chatid($chatId);
            foreach ($chat['messages'] as $msg) {
                if (!($msg['read'] ?? false) && ($msg['direction'] ?? '') === 'in') {
                    $unread[] = [
                        'chat_id' => $chatId,
                        'contact_name' => $contactName,
                        'contact_phone' => $chat['contact_phone'] ?? '',
                        'text' => $msg['text'] ?? '',
                        'ts' => $msg['ts'] ?? '',
                        'message_id' => $msg['id'] ?? '',
                    ];
                }
            }
        }
        echo json_encode(['ok' => true, 'unread' => $unread, 'count' => count($unread)]);
        break;

    // ── Reiniciar sesión WAHA (cuando está FAILED) ──
    case 'restart':
        if ($method !== 'POST' && $method !== 'GET') {
            echo json_encode(['ok' => false, 'error' => 'POST or GET required']);
            break;
        }
        wasap_debug_log('restart_requested', []);
        $wahaPort = WASAP_WAHA_PORT;
        $wahaHost = WASAP_WAHA_HOST;

        // Step 1: Delete current session
        $ch = curl_init("{$wahaHost}:{$wahaPort}/api/sessions/default");
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Api-Key: ' . WASAP_WAHA_KEY],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        curl_exec($ch);
        curl_close($ch);
        sleep(3);

        // Step 2: Recreate session
        $ch = curl_init("{$wahaHost}:{$wahaPort}/api/sessions");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'X-Api-Key: ' . WASAP_WAHA_KEY],
            CURLOPT_POSTFIELDS => json_encode([
                'name' => 'default',
                'config' => [
                    'webhooks' => [['url' => 'http://100.76.30.118/control/personal_wasap_webhook.php', 'events' => ['message', 'message.any']]],
                ],
                'start' => true,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            wasap_debug_log('restart_ok', ['http_code' => $httpCode]);
            echo json_encode(['ok' => true, 'message' => 'WAHA reiniciado. Espera unos segundos...']);
        } else {
            wasap_debug_log('restart_failed', ['http_code' => $httpCode, 'response' => mb_substr($resp ?: '', 0, 200)]);
            echo json_encode(['ok' => false, 'error' => 'Error al reiniciar WAHA (HTTP ' . $httpCode . ')']);
        }
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown action: ' . $action]);
        break;
}
