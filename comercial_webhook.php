<?php

$comercialWebhookRequestId = 'cwreq_' . substr(bin2hex(random_bytes(8)), 0, 12);
$comercialWebhookRaw = (string)file_get_contents('php://input');
$comercialWebhookBody = json_decode($comercialWebhookRaw, true);
if (!is_array($comercialWebhookBody)) {
    $comercialWebhookBody = array();
}

$comercialWebhookExtract = function ($body, $keyPaths) {
    foreach ((array)$keyPaths as $path) {
        $cursor = $body;
        $ok = true;
        foreach ((array)$path as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                $ok = false;
                break;
            }
            $cursor = $cursor[$segment];
        }
        if ($ok && !is_array($cursor) && trim((string)$cursor) !== '') {
            return (string)$cursor;
        }
    }
    return '';
};

$comercialWebhookDigits = function ($value) {
    return preg_replace('/\D+/', '', (string)$value) ?: '';
};

$comercialWebhookFrom = $comercialWebhookDigits($comercialWebhookExtract($comercialWebhookBody, array(
    array('from'),
    array('payload', 'from'),
    array('payload', '_data', 'from'),
    array('payload', 'chatId'),
)));
$comercialWebhookTo = $comercialWebhookDigits($comercialWebhookExtract($comercialWebhookBody, array(
    array('to'),
    array('payload', 'to'),
    array('me', 'id'),
)));
$comercialWebhookText = $comercialWebhookExtract($comercialWebhookBody, array(
    array('text'),
    array('message'),
    array('body'),
    array('payload', 'text'),
    array('payload', 'body'),
    array('payload', 'message'),
    array('payload', '_data', 'body'),
));
$comercialWebhookMessageId = $comercialWebhookExtract($comercialWebhookBody, array(
    array('message_id'),
    array('payload', 'message_id'),
    array('payload', 'messageId'),
    array('payload', '_data', 'id', '_serialized'),
));

$comercialWebhookLogPath = __DIR__ . '/data/comercial_webhook_log.jsonl';
@file_put_contents($comercialWebhookLogPath, json_encode(array(
    'ts' => date('Y-m-d H:i:s'),
    'date' => date('Y-m-d'),
    'type' => 'request_received_raw',
    'request_id' => $comercialWebhookRequestId,
    'payload' => array(
        'from' => $comercialWebhookFrom,
        'to' => $comercialWebhookTo,
        'message_id' => $comercialWebhookMessageId,
        'text_preview' => function_exists('mb_substr') ? mb_substr((string)$comercialWebhookText, 0, 400, 'UTF-8') : substr((string)$comercialWebhookText, 0, 400),
        'remote_ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'POST'),
        'raw_preview' => function_exists('mb_substr') ? mb_substr($comercialWebhookRaw, 0, 2000, 'UTF-8') : substr($comercialWebhookRaw, 0, 2000),
    ),
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);

$GLOBALS['comercial_webhook_request_id'] = $comercialWebhookRequestId;
$GLOBALS['comercial_webhook_raw_body'] = $comercialWebhookRaw;

register_shutdown_function(function () use ($comercialWebhookLogPath, $comercialWebhookRequestId, $comercialWebhookFrom, $comercialWebhookTo, $comercialWebhookMessageId) {
    $error = error_get_last();
    if (!$error) {
        return;
    }
    if (!in_array((int)($error['type'] ?? 0), array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true)) {
        return;
    }
    @file_put_contents($comercialWebhookLogPath, json_encode(array(
        'ts' => date('Y-m-d H:i:s'),
        'date' => date('Y-m-d'),
        'type' => 'request_shutdown_error',
        'request_id' => $comercialWebhookRequestId,
        'payload' => array(
            'from' => $comercialWebhookFrom,
            'to' => $comercialWebhookTo,
            'message_id' => $comercialWebhookMessageId,
            'http_status' => http_response_code(),
            'php_error_message' => (string)($error['message'] ?? ''),
            'php_error_file' => (string)($error['file'] ?? ''),
            'php_error_line' => (int)($error['line'] ?? 0),
        ),
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
});

require_once __DIR__ . '/app/bootstrap.php';

comercial_handle_webhook_http();
