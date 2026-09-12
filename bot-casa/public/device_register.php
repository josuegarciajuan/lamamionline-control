<?php

/**
 * device_register.php — endpoint de handshake del candado (bot-casa).
 *
 * No está tras el gate: es el canal por el que un dispositivo autorizado se
 * identifica por fingerprint. No expone datos de la app.
 */

declare(strict_types=1);

define('WASAPBOT_ROOT', dirname(__DIR__));

require_once WASAPBOT_ROOT . '/src/Core/DeviceLock.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$lock = new \WasapBot\Core\DeviceLock(dirname(WASAPBOT_ROOT) . '/data/device_lock.php', 'dlk_dev');
$app = (string)($_SERVER['HTTP_X_DLK_APP'] ?? $_GET['app'] ?? 'botcasa');

$result = $lock->register($app);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
