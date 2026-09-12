<?php

/**
 * device_register.php — endpoint de handshake del candado de dispositivos.
 *
 * Recibe {fingerprint, signals, app} desde device-lock js, asocia el token de
 * dispositivo (cookie) y en modo enforce decide si el dispositivo pasa.
 * Nunca queda tras el gate: es el canal por el que un dispositivo autorizado
 * se identifica. No expone datos de la app.
 */

declare(strict_types=1);

define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');
define('DATA_PATH', BASE_PATH . '/data');

require_once __DIR__ . '/app/device_lock.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$app = (string)($_SERVER['HTTP_X_DLK_APP'] ?? $_GET['app'] ?? 'inbox');

$result = device_lock()->register($app);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
