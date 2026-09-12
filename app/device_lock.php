<?php

/**
 * app/device_lock.php — Puente del candado de dispositivos para el CRM.
 *
 * Reutiliza el motor compartido WasapBot\Core\DeviceLock (misma fuente de
 * verdad que bot-casa) y lo configura con el estado en data/device_lock.php.
 *
 * El estado es un .php que devuelve 404 si se solicita por web, de modo que
 * el fingerprint/tokens nunca quedan expuestos aunque data/ sea accesible.
 */

declare(strict_types=1);

require_once __DIR__ . '/../bot-casa/src/Core/DeviceLock.php';

/**
 * Instancia única del candado para el CRM.
 */
function device_lock(): \WasapBot\Core\DeviceLock
{
    static $lock = null;
    if (!$lock instanceof \WasapBot\Core\DeviceLock) {
        $lock = new \WasapBot\Core\DeviceLock(DATA_PATH . '/device_lock.php', 'dlk_dev');
    }
    return $lock;
}

/**
 * Gate del CRM. En modo enforce expulsa con 404 a dispositivos no autorizados.
 */
function device_lock_gate(string $app): void
{
    device_lock()->gate($app);
}

/**
 * Script inline de handshake (fingerprint -> token) para insertar en la app.
 */
function device_lock_bootstrap_script(string $app): string
{
    return device_lock()->bootstrapScript($app);
}
