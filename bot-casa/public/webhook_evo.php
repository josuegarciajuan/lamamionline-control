<?php
/**
 * webhook_evo.php — Webhook receptor de EVOLUTION para bot-casa.
 *
 * Recibe MESSAGES_UPSERT/SEND_MESSAGE de Evolution API, identifica el usuario por
 * el nombre de instancia (línea bot casa), comprueba transport=evolution (gate
 * anti-doble), traduce el payload a la forma que ya entiende webhook.php y
 * reutiliza todo su flujo (dedup, memoria, pipeline) vía override del body.
 *
 * Requiere que webhook.php admita $GLOBALS['WASAPBOT_OVERRIDE_BODY'].
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/evolution/transcribe.php';

function wasapbot_evo_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function wasapbot_evo_handle(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        wasapbot_evo_json(['ok' => false, 'error' => 'Method not allowed'], 405);
        return;
    }
    $rawBody = (string) file_get_contents('php://input');
    if ($rawBody === '') {
        wasapbot_evo_json(['ok' => true, 'ignored' => 'empty']);
        return;
    }
    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        wasapbot_evo_json(['ok' => true, 'ignored' => 'invalid_json']);
        return;
    }

    $event = (string) ($payload['event'] ?? '');
    if ($event !== '' && !in_array($event, ['MESSAGES_UPSERT', 'SEND_MESSAGE'], true)) {
        wasapbot_evo_json(['ok' => true, 'ignored' => 'event:' . $event]);
        return;
    }

    $instance = strtolower(trim((string) ($payload['instance'] ?? '')));
    // Buscar línea bot casa por nombre de instancia Evolution
    $line = null;
    $rows = storage_read('telefonos.json');
    if (is_array($rows)) {
        foreach ($rows as $r) {
            if (evolution_instance_name($r) === $instance) {
                $line = $r;
                break;
            }
        }
    }
    if (!is_array($line)) {
        wasapbot_evo_json(['ok' => true, 'skipped' => 'line_not_found']);
        return;
    }
    if (whatsapp_transport_for($line) !== 'evolution') {
        wasapbot_evo_json(['ok' => true, 'skipped' => 'transport_waha']);
        return;
    }

    $data = $payload['data'] ?? $payload;
    $messages = [];
    if (isset($data['key'], $data['message'])) {
        $messages[] = $data;
    } elseif (is_array($data) && array_is_list($data)) {
        $messages = $data;
    } elseif (isset($data['messages']) && is_array($data['messages'])) {
        $messages = $data['messages'];
    }

    $processed = 0;
    foreach ($messages as $msg) {
        if (!is_array($msg)) continue;
        $waha = wasapbot_evo_translate($msg, $line);
        if ($waha === null) continue;
        $GLOBALS['WASAPBOT_OVERRIDE_BODY'] = json_encode($waha, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        require __DIR__ . '/webhook.php';
        $processed++;
    }

    wasapbot_evo_json(['ok' => true, 'processed' => $processed]);
}

/**
 * Traduce un mensaje de Evolution a la forma WAHA que entiende webhook.php.
 * @param array<string,mixed> $msg
 * @param array<string,mixed> $line
 * @return array<string,mixed>|null
 */
function wasapbot_evo_translate(array $msg, array $line): ?array
{
    $key = $msg['key'] ?? [];
    $remoteJid = (string) ($key['remoteJid'] ?? '');
    $fromMe = (bool) ($key['fromMe'] ?? false);
    $messageId = (string) ($key['id'] ?? '');
    $message = $msg['message'] ?? [];
    if (!is_array($message)) $message = [];
    $pushName = (string) ($msg['pushName'] ?? '');

    if ($remoteJid === '') return null;
    $linePhone = preg_replace('/[^0-9]/', '', (string) ($line['tfono'] ?? ''));

    // Media (imagen/audio/vídeo) con transcripción de audio
    $mediaInfo = null;
    foreach (['audioMessage' => 'audio', 'imageMessage' => 'image', 'videoMessage' => 'video', 'documentMessage' => 'document'] as $k => $type) {
        if (isset($message[$k]) && is_array($message[$k])) {
            $m = $message[$k];
            $mediaInfo = ['type' => $type, 'url' => $m['url'] ?? null, 'mimetype' => $m['mimetype'] ?? null, 'fileName' => $m['fileName'] ?? null];
            break;
        }
    }
    $transcription = '';
    if ($mediaInfo !== null && ($mediaInfo['type'] ?? '') === 'audio' && function_exists('whatsapp_transcribe_media')) {
        $trans = whatsapp_transcribe_media($mediaInfo);
        if ($trans === null && function_exists('whatsapp_transcribe_media_message')) {
            // Media recibida cifrada (CDN): descifrar vía Evolution y transcribir
            $trans = whatsapp_transcribe_media_message($msg, evolution_instance_name($line));
        }
        if ($trans !== null) $transcription = trim($trans);
    }

    return [
        'event' => 'message',
        'payload' => [
            'from' => $remoteJid,
            'to' => $linePhone,
            'me' => ['id' => $linePhone . '@s.whatsapp.net', 'pushName' => $pushName],
            'message_id' => $messageId,
            'from_me' => $fromMe,
            'message' => $message,
            'media' => $mediaInfo,
            'transcription' => $transcription,
        ],
    ];
}

if (!defined('WASAPBOT_WEBHOOK_EVO_NO_DISPATCH')) {
    wasapbot_evo_handle();
}
