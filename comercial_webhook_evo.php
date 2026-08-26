<?php
/**
 * comercial_webhook_evo.php — Webhook receptor de EVOLUTION para el bot comercial.
 *
 * Recibe MESSAGES_UPSERT/SEND_MESSAGE de Evolution API, identifica la línea por
 * el nombre de instancia, comprueba que opera por Evolution (gate anti-doble),
 * traduce el payload al formato comercial y lo procesa.
 *
 * Solo entrantes (fromMe=false); los salientes nativos se capturan por sondeo
 * (comercial_sync_native_replies) como con WAHA.
 */

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

function comercial_evo_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function comercial_evo_handle(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        comercial_evo_json(['ok' => false, 'error' => 'Method not allowed'], 405);
        return;
    }
    $rawBody = (string) file_get_contents('php://input');
    if ($rawBody === '') {
        comercial_evo_json(['ok' => true, 'ignored' => 'empty']);
        return;
    }
    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        comercial_evo_json(['ok' => true, 'ignored' => 'invalid_json']);
        return;
    }

    $event = (string) ($payload['event'] ?? '');
    if ($event !== '' && !in_array($event, ['MESSAGES_UPSERT', 'SEND_MESSAGE'], true)) {
        comercial_evo_json(['ok' => true, 'ignored' => 'event:' . $event]);
        return;
    }

    $instance = strtolower(trim((string) ($payload['instance'] ?? '')));
    // Identificar línea por nombre de instancia Evolution
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
        comercial_evo_json(['ok' => true, 'skipped' => 'line_not_found']);
        return;
    }
    // Gate anti-doble: solo procesa si la línea opera por Evolution
    if (whatsapp_transport_for($line) !== 'evolution') {
        comercial_evo_json(['ok' => true, 'skipped' => 'transport_waha']);
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
        $conv = comercial_evo_translate($msg, $line);
        if ($conv === null) continue;
        // Solo entrantes (fromMe=false); los salientes se capturan por sondeo
        if (!empty($conv['from_me'])) continue;
        comercial_handle_inbound_message($conv);
        $processed++;
    }

    comercial_evo_json(['ok' => true, 'processed' => $processed]);
}

/**
 * Traduce un mensaje de Evolution al payload que espera comercial_handle_inbound_message.
 * @param array<string,mixed> $msg
 * @param array<string,mixed> $line
 * @return array<string,mixed>|null
 */
function comercial_evo_translate(array $msg, array $line): ?array
{
    $key = $msg['key'] ?? [];
    $remoteJid = (string) ($key['remoteJid'] ?? '');
    $fromMe = (bool) ($key['fromMe'] ?? false);
    $messageId = (string) ($key['id'] ?? '');
    $message = $msg['message'] ?? [];
    if (!is_array($message)) $message = [];

    if ($remoteJid === '') return null;
    $remoteDigits = preg_replace('/[^0-9]/', '', explode('@', $remoteJid)[0]);
    if ($remoteDigits === '') return null;
    $linePhone = comercial_only_digits((string) ($line['tfono'] ?? ''));

    $text = comercial_evo_text($message);

    return [
        'from' => $fromMe ? $linePhone : $remoteDigits,
        'to' => $fromMe ? $remoteDigits : $linePhone,
        'text' => $text,
        'message_id' => $messageId,
        'from_me' => $fromMe ? 1 : 0,
        'port' => trim((string) ($line['waha_port'] ?? '')),
        'from_lid' => str_contains($remoteJid, '@lid') ? $remoteJid : '',
        'line_id' => $line['id'] ?? '',
        'raw' => $msg,
    ];
}

/** @param array<string,mixed> $message */
function comercial_evo_text(array $message): string
{
    if (isset($message['conversation']) && is_string($message['conversation'])) return $message['conversation'];
    foreach (['extendedTextMessage', 'buttonResponseMessage', 'listResponseMessage'] as $k) {
        if (isset($message[$k]) && is_array($message[$k])) {
            $t = $message[$k]['text'] ?? $message[$k]['selectedButtonText'] ?? $message[$k]['title'] ?? '';
            if (is_string($t) && $t !== '') return $t;
        }
    }
    return '';
}

if (!defined('COMERCIAL_WEBHOOK_EVO_NO_DISPATCH')) {
    comercial_evo_handle();
}
