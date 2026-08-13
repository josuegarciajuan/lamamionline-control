<?php
/**
 * personal_wasap_webhook.php — WAHA webhook para el WhatsApp Personal de Josue.
 *
 * Recibe mensajes entrantes y salientes (fromMe=true/false) del WAHA personal
 * (waha3032, puerto 3031) y los persiste en data/personal_wasap_data.json.
 *
 * Soporte BIDIRECCIONAL:
 *   - fromMe=false (entrante) → direction "in", thread_id usa el remitente
 *   - fromMe=true  (saliente desde móvil nativo) → direction "out", thread_id usa el destinatario
 *
 * WAHA envía POST con JSON. Requiere respuesta HTTP 200 rápida.
 */

declare(strict_types=1);

// ── Solo aceptar POST ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// ── Leer payload ──
$rawBody = (string) file_get_contents('php://input');
if ($rawBody === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Empty body']);
    exit;
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

// ── Log raw payload para debugging (primeros 2000 bytes) ──
wasap_log('raw_payload', ['body' => mb_substr($rawBody, 0, 2000)]);

// ── Helpers ──
function wasap_extract_digits(string $value): string {
    // WAHA puede venir con formato "34604829142:95@s.whatsapp.net"
    // Extraer solo el teléfono, ignorando puerto (:95) y dominio (@s.whatsapp.net)
    $local = explode('@', $value)[0];    // quitar @s.whatsapp.net
    $local = explode(':', $local)[0];     // quitar :95 (puerto)
    return preg_replace('/\D+/', '', $local) ?: '';
}

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
    // Asegurar estructura
    if (!isset($data['chats'])) $data['chats'] = [];
    if (!isset($data['contacts_index'])) $data['contacts_index'] = [];
    if (!isset($data['learning'])) $data['learning'] = [];
    if (!isset($data['meta'])) $data['meta'] = [];
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
    $existing['meta']['last_sync'] = date('c');

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

function wasap_log(string $type, array $info): void {
    $logPath = __DIR__ . '/data/personal_wasap_webhook_log.jsonl';
    $dir = dirname($logPath);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $entry = array_merge(['ts' => date('c'), 'type' => $type], $info);
    @file_put_contents($logPath, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

// ── Extraer campos del payload WAHA ──
$body   = $payload['payload'] ?? $payload;
$dataInfo = $body['_data']['Info'] ?? $payload['_data']['Info'] ?? [];

// Detectar fromMe
$fromMe = (bool)($body['fromMe'] ?? $payload['fromMe'] ?? $dataInfo['IsFromMe'] ?? false);

// Número del remitente (el "otro" — la persona con quien se habla)
$rawFrom = (string)($body['from'] ?? $payload['from'] ?? '');
$senderAlt = (string)($dataInfo['SenderAlt'] ?? '');
$recipientAlt = (string)($dataInfo['RecipientAlt'] ?? '');
$rawTo = (string)($body['to'] ?? $payload['to'] ?? '');
$chatField = (string)($dataInfo['Chat'] ?? '');

// Texto del mensaje
$msgText = '';
$bodyText = $body['body'] ?? $payload['body'] ?? null;
if (is_string($bodyText)) {
    $msgText = trim($bodyText);
} elseif (is_array($bodyText)) {
    $msgText = trim((string)($bodyText['text']['body'] ?? $bodyText['text'] ?? $bodyText['message'] ?? ''));
}
// También extraer de _data.Message.conversation (GOWS engine)
if ($msgText === '' && isset($body['_data']['Message']['conversation'])) {
    $msgText = trim((string)$body['_data']['Message']['conversation']);
}

$messageId = (string)($body['id'] ?? $payload['id'] ?? $body['message_id'] ?? $payload['message_id'] ?? '');
if ($messageId === '') $messageId = 'msg_' . bin2hex(random_bytes(8));

// ── Determinar dirección y el número del interlocutor ──
$direction = 'in';
$peerPhone = '';

if ($fromMe) {
    // Mensaje ENVIADO por Josue (desde móvil nativo o desde CRM)
    $direction = 'out';
    // El destinatario es el interlocutor
    $peerPhone = wasap_extract_digits($rawTo);
    if ($peerPhone === '' && $recipientAlt !== '') {
        // RecipientAlt contiene el teléfono real cuando Chat es un LID
        // Ej: "34604829142@s.whatsapp.net"
        $peerPhone = wasap_extract_digits($recipientAlt);
    }
    if ($peerPhone === '' && $chatField !== '') {
        // Último fallback: Chat (puede ser LID, no ideal pero mejor que nada)
        $peerPhone = wasap_extract_digits($chatField);
    }
    if ($peerPhone === '') {
        // fromMe pero no tenemos destinatario claro → ignorar
        wasap_log('ignored_outgoing_no_target', ['from_me' => true, 'raw_to' => $rawTo, 'recipient_alt' => $recipientAlt, 'chat' => $chatField]);
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'ignored' => 'no_target']);
        exit;
    }
} else {
    // Mensaje RECIBIDO
    $direction = 'in';
    $peerPhone = wasap_extract_digits($senderAlt !== '' ? $senderAlt : $rawFrom);
    if ($peerPhone === '') {
        wasap_log('ignored_incoming_no_sender', ['raw_from' => $rawFrom, 'sender_alt' => $senderAlt]);
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'ignored' => 'no_sender']);
        exit;
    }
}

// Sin texto y sin fromMe (podría ser un mensaje de sistema) → ignorar
if ($msgText === '' && !$fromMe) {
    wasap_log('ignored_empty_text', ['peer_phone' => $peerPhone]);
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'ignored' => 'empty_text']);
    exit;
}

// ── Construir chat_id ──
// El chat_id se basa en el número del interlocutor (peerPhone)
$myDigits = '654464023'; // Número de Josue
$chatId = $peerPhone . '@c.us';

// Detectar si es mensaje de grupo
$isGroup = !empty($dataInfo['IsGroup']);
if ($isGroup) {
    $chatId = wasap_extract_digits($rawFrom) . '@g.us';
}

// ── Persistir mensaje ──
$store = wasap_store_read();
$now = date('c');
$nowDate = date('Y-m-d');

// Asegurar que el chat existe
if (!isset($store['chats'][$chatId])) {
    $store['chats'][$chatId] = [
        'contact_name' => '',
        'contact_phone' => $peerPhone,
        'last_message_at' => $now,
        'unread_count' => 0,
        'messages' => [],
    ];
}

$chat = &$store['chats'][$chatId];

// Si el pushName está disponible, usarlo como nombre temporal.
// IMPORTANTE: solo para mensajes entrantes (fromMe=false). En los salientes
// (fromMe=true) el PushName es el nombre propio de Josué, no el del interlocutor,
// y dejaba el listado con "Josué" como remitente en las conversaciones que él abría.
$pushName = (string)($dataInfo['PushName'] ?? $payload['pushName'] ?? $payload['me']['pushName'] ?? '');
if (!$fromMe && $pushName !== '' && ($chat['contact_name'] ?? '') === '') {
    $chat['contact_name'] = $pushName;
}

// Construir registro de mensaje
$msgRecord = [
    'id' => $messageId,
    'direction' => $direction,
    'from_me' => $fromMe,
    'text' => $msgText !== '' ? $msgText : '📎 Mensaje',
    'ts' => $now,
    'read' => $fromMe, // Los mensajes propios ya están "leídos"
];

// Detectar duplicados (mismo message_id)
$duplicate = false;
foreach ($chat['messages'] as $existing) {
    if (($existing['id'] ?? '') === $messageId) {
        $duplicate = true;
        break;
    }
}

    if (!$duplicate) {
        $chat['messages'][] = $msgRecord;
        $chat['last_message_at'] = $now;

        if (!$fromMe) {
            $chat['unread_count'] = ($chat['unread_count'] ?? 0) + 1;
        }

        // Mantener máximo 500 mensajes por chat
        if (count($chat['messages']) > 500) {
            $chat['messages'] = array_slice($chat['messages'], -500);
        }

        // ── Actualizar índice de contactos ──
        $contactKey = $peerPhone;
        if (!isset($store['contacts_index'][$contactKey])) {
            $store['contacts_index'][$contactKey] = [
                'name' => $chat['contact_name'] ?? '',
                'first_seen' => $nowDate,
                'last_seen' => $nowDate,
                'chats_with' => 1,
                'total_messages' => 1,
            ];
        } else {
            $store['contacts_index'][$contactKey]['last_seen'] = $nowDate;
            $store['contacts_index'][$contactKey]['total_messages'] = ($store['contacts_index'][$contactKey]['total_messages'] ?? 0) + 1;
            if (($chat['contact_name'] ?? '') !== '' && ($store['contacts_index'][$contactKey]['name'] ?? '') === '') {
                $store['contacts_index'][$contactKey]['name'] = $chat['contact_name'];
            }
        }

        // ── Actualizar stats diarios ──
        if (!isset($store['learning']['daily_stats'])) $store['learning']['daily_stats'] = [];
        if (!isset($store['learning']['daily_stats'][$nowDate])) {
            $store['learning']['daily_stats'][$nowDate] = [
                'messages_sent' => 0,
                'messages_received' => 0,
                'contacts_talked_to' => [],
            ];
        }
        if ($fromMe) {
            $store['learning']['daily_stats'][$nowDate]['messages_sent']++;
        } else {
            $store['learning']['daily_stats'][$nowDate]['messages_received']++;
        }
        if (!in_array($peerPhone, $store['learning']['daily_stats'][$nowDate]['contacts_talked_to'])) {
            $store['learning']['daily_stats'][$nowDate]['contacts_talked_to'][] = $peerPhone;
        }

        // ── Añadir a pendientes de clasificación (para aprendizaje Jefry) ──
        if (!isset($store['learning']['pending_classification'])) $store['learning']['pending_classification'] = [];
        if ($msgText !== '' && mb_strlen($msgText) > 15) {
            $store['learning']['pending_classification'][] = [
                'message_id' => $messageId,
                'chat_id' => $chatId,
                'direction' => $direction,
                'text' => $msgText,
                'ts' => $now,
            ];
            // Máximo 50 pendientes
            if (count($store['learning']['pending_classification']) > 50) {
                $store['learning']['pending_classification'] = array_slice($store['learning']['pending_classification'], -50);
            }
        }

        // ── Log ──
        wasap_log('message_persisted', [
            'chat_id' => $chatId,
            'peer_phone' => $peerPhone,
            'direction' => $direction,
            'from_me' => $fromMe,
            'text_preview' => mb_substr($msgText, 0, 200),
            'message_id' => $messageId,
            'is_group' => $isGroup,
        ]);
    } else {
        wasap_log('duplicate_skipped', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'direction' => $direction,
        ]);
    }

    wasap_store_write($store);

// ── Responder OK ──
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'direction' => $direction, 'chat_id' => $chatId]);
