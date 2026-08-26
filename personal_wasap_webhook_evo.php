<?php
/**
 * personal_wasap_webhook_evo.php — Webhook receptor de EVOLUTION para el WhatsApp Personal.
 *
 * Recibe los eventos MESSAGES_UPSERT de Evolution API (instancia del número personal),
 * traduce el payload al formato del chat personal y persiste en
 * data/personal_wasap_data.json (misma tienda que el webhook de WAHA).
 *
 * Mientras la línea personal opera por WAHA (transport=waha), este endpoint
 * descarta los eventos para no duplicar (gate anti-doble).
 *
 * Solo acepta POST. Responder 200 rápido.
 *
 * Para pruebas unitarias sin disparar HTTP: definir PERSONAL_WASAP_WEBHOOK_EVO_NO_DISPATCH
 * antes de require y llamar a personal_wasap_webhook_evo_handle()/translate.
 */

declare(strict_types=1);

require_once __DIR__ . '/app/evolution/transport.php';
require_once __DIR__ . '/app/personal_wasap_evo_translate.php';

function personal_wasap_webhook_evo_handle(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json; charset=utf-8');
        header('Allow: POST');
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        return;
    }

    $rawBody = (string) file_get_contents('php://input');
    if ($rawBody === '') {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'ignored' => 'empty']);
        return;
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'ignored' => 'invalid_json']);
        return;
    }

    // ── Gate anti-doble: la línea personal debe operar por Evolution ──
    $personalTransport = 'waha';
    $telefonos = json_decode((string) @file_get_contents(__DIR__ . '/data/telefonos.json'), true);
    if (is_array($telefonos)) {
        foreach ($telefonos as $t) {
            if ((string)($t['uso'] ?? '') === 'personal' || (string)($t['tfono'] ?? '') === '654464023') {
                $personalTransport = whatsapp_transport_for($t);
                break;
            }
        }
    }
    if ($personalTransport !== 'evolution') {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'transport' => $personalTransport, 'skipped' => true]);
        return;
    }

    // ── Extraer mensajes del payload Evolution (MESSAGES_UPSERT) ──
    $event = (string)($payload['event'] ?? '');
    if ($event !== '' && $event !== 'MESSAGES_UPSERT' && $event !== 'SEND_MESSAGE') {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'ignored' => 'event:' . $event]);
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
        $ingest = personal_wasap_evo_translate($msg, (string) ($payload['instance'] ?? ''));
        if ($ingest === null) continue;
        wasap_ingest_message($ingest);
        $processed++;
    }

    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'processed' => $processed]);
}

if (!defined('PERSONAL_WASAP_WEBHOOK_EVO_NO_DISPATCH')) {
    personal_wasap_webhook_evo_handle();
}
