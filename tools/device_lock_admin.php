<?php

/**
 * tools/device_lock_admin.php — Administración del candado de dispositivos.
 *
 * SOLO CLI. Gestiona data/device_lock.php (devices + candidates + mode).
 *
 * Uso:
 *   php tools/device_lock_admin.php status
 *   php tools/device_lock_admin.php candidates
 *   php tools/device_lock_admin.php authorize <fingerprint> [label]
 *   php tools/device_lock_admin.php auto [label]     # el fingerprint visto en ambas apps
 *   php tools/device_lock_admin.php enforce
 *   php tools/device_lock_admin.php observe
 *   php tools/device_lock_admin.php revoke <device_id>
 *   php tools/device_lock_admin.php reset
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../bot-casa/src/Core/DeviceLock.php';

$stateFile = dirname(__DIR__) . '/data/device_lock.php';
$lock = new \WasapBot\Core\DeviceLock($stateFile, 'dlk_dev');

$command = $argv[1] ?? 'status';

function dl_print_devices(array $state): void
{
    $devices = is_array($state['devices'] ?? null) ? $state['devices'] : [];
    if ($devices === []) {
        echo "  (sin dispositivos autorizados)\n";
        return;
    }
    foreach ($devices as $device) {
        if (!is_array($device)) {
            continue;
        }
        $id = (string)($device['id'] ?? '?');
        $label = (string)($device['label'] ?? '');
        $authorized = !empty($device['authorized']) ? 'AUTORIZADO' : 'revocado';
        $fp = (string)($device['fingerprint'] ?? '');
        $apps = is_array($device['apps'] ?? null) ? implode(',', array_keys($device['apps'])) : '';
        $tokens = is_array($device['tokens'] ?? null) ? count($device['tokens']) : 0;
        $last = (string)($device['last_seen_at'] ?? '');
        echo "  [{$authorized}] {$id} '{$label}'\n";
        echo "      fp: {$fp}\n";
        echo "      apps: {$apps} | tokens: {$tokens} | last: {$last}\n";
    }
}

if ($command === 'status') {
    $state = $lock->snapshot();
    echo "device_lock\n";
    echo "  estado: " . $stateFile . (is_file($stateFile) ? " (existe)\n" : " (aún no creado)\n");
    echo "  modo: " . (string)($state['mode'] ?? 'observe') . "\n";
    echo "  updated_at: " . (string)($state['updated_at'] ?? '') . "\n";
    echo "  candidatos: " . count(is_array($state['candidates'] ?? null) ? $state['candidates'] : []) . "\n";
    echo "  dispositivos:\n";
    dl_print_devices($state);
    exit(0);
}

if ($command === 'candidates') {
    $state = $lock->snapshot();
    $candidates = is_array($state['candidates'] ?? null) ? $state['candidates'] : [];
    if ($candidates === []) {
        echo "Sin candidatos registrados.\n";
        exit(0);
    }
    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        $fp = (string)($candidate['fingerprint'] ?? '');
        $apps = is_array($candidate['apps'] ?? null) ? implode(',', array_keys($candidate['apps'])) : '';
        $hits = (int)($candidate['hits'] ?? 0);
        $last = (string)($candidate['last_seen_at'] ?? '');
        $ua = (string)($candidate['user_agent'] ?? '');
        echo "fp={$fp}\n  apps={$apps} hits={$hits} last={$last}\n  ua={$ua}\n";
    }
    exit(0);
}

if ($command === 'authorize') {
    $fingerprint = (string)($argv[2] ?? '');
    $label = (string)($argv[3] ?? '');
    if ($fingerprint === '') {
        fwrite(STDERR, "Falta el fingerprint. Uso: authorize <fingerprint> [label]\n");
        exit(1);
    }
    $result = $lock->authorizeFingerprint($fingerprint, $label);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(!empty($result['ok']) ? 0 : 1);
}

if ($command === 'auto') {
    $label = (string)($argv[2] ?? '');
    $state = $lock->snapshot();
    $candidates = is_array($state['candidates'] ?? null) ? $state['candidates'] : [];
    $best = null;
    $bestApps = 0;
    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        $apps = is_array($candidate['apps'] ?? null) ? $candidate['apps'] : [];
        $count = count($apps);
        if ($count > $bestApps) {
            $bestApps = $count;
            $best = (string)($candidate['fingerprint'] ?? '');
        }
    }
    if ($best === null || $best === '') {
        fwrite(STDERR, "No hay candidatos. Abre las dos apps desde tu dispositivo primero.\n");
        exit(1);
    }
    if ($bestApps < 2) {
        fwrite(STDERR, "Aviso: el mejor candidato solo aparece en {$bestApps} app(s).\n");
    }
    $result = $lock->authorizeFingerprint($best, $label);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    if (!empty($result['ok'])) {
        echo "Fingerprint autorizado: {$best} (aparecía en {$bestApps} app(s)).\n";
        echo "Siguiente paso: php tools/device_lock_admin.php enforce\n";
    }
    exit(!empty($result['ok']) ? 0 : 1);
}

if ($command === 'enforce' || $command === 'observe') {
    $ok = $lock->setMode($command);
    echo $ok ? "Modo cambiado a {$command}.\n" : "No se pudo cambiar el modo.\n";
    exit($ok ? 0 : 1);
}

if ($command === 'revoke') {
    $id = (string)($argv[2] ?? '');
    if ($id === '') {
        fwrite(STDERR, "Falta el device_id. Uso: revoke <device_id>\n");
        exit(1);
    }
    $ok = $lock->revokeDevice($id);
    echo $ok ? "Dispositivo {$id} revocado.\n" : "No encontrado: {$id}\n";
    exit($ok ? 0 : 1);
}

if ($command === 'reset') {
    $empty = [
        'version' => 1,
        'mode' => \WasapBot\Core\DeviceLock::MODE_OBSERVE,
        'updated_at' => \WasapBot\Core\DeviceLock::now(),
        'devices' => [],
        'candidates' => [],
    ];
    $ok = $lock->save($empty);
    echo $ok ? "Estado reseteado (modo observe, sin dispositivos).\n" : "No se pudo resetear.\n";
    exit($ok ? 0 : 1);
}

fwrite(STDERR, "Comando desconocido: {$command}\n");
exit(1);
