<?php

/**
 * _device_guard.php — gate del candado de dispositivos para bot-casa.
 *
 * Cada entrypoint hace `require_once __DIR__ . '/_device_guard.php';` y luego
 * llama a botcasa_device_guard(). El endpoint device_register.php NO lo usa.
 *
 * Exenciones: host demo, /webhook y /health.
 */

declare(strict_types=1);

if (!defined('WASAPBOT_ROOT')) {
    define('WASAPBOT_ROOT', dirname(__DIR__));
}

require_once WASAPBOT_ROOT . '/src/Core/DeviceLock.php';

// No exponer este fichero si se solicita directamente por web.
if (PHP_SAPI !== 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    exit;
}

function botcasa_device_lock(): \WasapBot\Core\DeviceLock
{
    static $lock = null;
    if (!$lock instanceof \WasapBot\Core\DeviceLock) {
        $lock = new \WasapBot\Core\DeviceLock(dirname(WASAPBOT_ROOT) . '/data/device_lock.php', 'dlk_dev');
    }
    return $lock;
}

function botcasa_device_guard(): void
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === 'demo.casawasap.com') {
        return;
    }

    $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $path = rtrim($path, '/');
    if ($path === '/webhook' || $path === '/health') {
        return;
    }

    botcasa_device_lock()->gate('botcasa');
}

function botcasa_device_bootstrap_script(): string
{
    return botcasa_device_lock()->bootstrapScript('botcasa');
}
