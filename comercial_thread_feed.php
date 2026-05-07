<?php
require_once __DIR__ . '/app/bootstrap.php';

auth_auto_login_from_whitelist();

if (!is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'error' => 'unauthorized'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$threadId = trim((string)($_GET['thread_id'] ?? ''));
if ($threadId === '') {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'error' => 'missing_thread_id'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$payload = comercial_thread_live_payload($threadId);
if (!$payload) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => false, 'error' => 'thread_not_found'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo json_encode(array('ok' => true, 'thread' => $payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
