<?php
/**
 * cron_runner.php — Ejecuta crons para todos los usuarios activos.
 *
 * Itera sobre todos los usuarios en users.json y ejecuta el cron especificado
 * con la configuración y datos de cada usuario.
 *
 * Uso:
 *   php bot-casa/cron/cron_runner.php followup
 *   php bot-casa/cron/cron_runner.php reminder
 *   php bot-casa/cron/cron_runner.php learn --days=7
 *   php bot-casa/cron/cron_runner.php classify
 */
declare(strict_types=1);

$phpBotRoot = dirname(__DIR__);

$cronType = $argv[1] ?? '';
if (!in_array($cronType, ['followup', 'reminder', 'learn', 'classify'], true)) {
    echo "Usage: php cron_runner.php [followup|reminder|learn|classify] [--days=N]\n";
    exit(1);
}

// Extra args to forward to the per-user worker (e.g. --days=7 for learn)
$extraArgs = '';
foreach ($argv as $i => $arg) {
    if ($i >= 2) {
        $extraArgs .= ' ' . escapeshellarg($arg);
    }
}

// ── Single-user mode (worker) ────────────────────────────────
// cron_runner.php <type> --user=<id> [--days=N]  → runs the cron for ONE user.
// Se ejecuta como subproceso por cada usuario desde el modo multi-usuario.
// El cron se incluye A NIVEL TOP-LEVEL (no dentro de una función) para que las
// variables que declara (p.ej. $cfgLockFile) queden en scope global y las
// funciones internas (acquire_process_lock) puedan leerlas con `global`.
if (in_array('--user', $argv, true) || array_any($argv, static fn (string $a): bool => str_starts_with($a, '--user='))) {
    $userId = 0;
    foreach ($argv as $arg) {
        if (str_starts_with((string) $arg, '--user=')) {
            $userId = (int) substr((string) $arg, 7);
        }
    }
    if ($userId <= 0) {
        fwrite(STDERR, "[cron_runner] Invalid --user id\n");
        exit(2);
    }
    require_once $phpBotRoot . '/src/Core/ConfigInterface.php';
    require_once $phpBotRoot . '/src/Core/Config.php';
    require_once $phpBotRoot . '/src/BotInterface.php';
    require_once $phpBotRoot . '/src/Bot.php';

    $configDir = \WasapBot\Bot::resolveUserConfigDir($phpBotRoot, $userId);
    $config = new \WasapBot\Core\Config($configDir, $phpBotRoot);

    // Override data paths for this user (misma lógica que el runner multi-user)
    if ($userId > 0) {
        $userDataDir = $phpBotRoot . '/data/users/' . $userId;
        if (is_dir($userDataDir)) {
            $fileKeys = [
                'files.session_memory', 'files.leads', 'files.reminders',
                'files.playbook', 'files.wa_raw_payload', 'files.bot_log',
                'bot.mode_file', 'files.followups_log',
                'cron.followup.leads_file', 'cron.followup.followups_log_file',
                'cron.reminder.reminders_file',
            ];
            foreach ($fileKeys as $key) {
                $val = $config->get($key, '');
                if (is_string($val) && $val !== '') {
                    $resolved = \WasapBot\Bot::resolveUserDataPath($phpBotRoot, $userId, $val);
                    $config->set($key, $resolved);
                }
            }
            $lockKeys = ['cron.followup.lock_file', 'cron.reminder.lock_file'];
            foreach ($lockKeys as $lockKey) {
                $val = $config->get($lockKey, '');
                if (is_string($val) && $val !== '') {
                    $resolved = \WasapBot\Bot::resolveUserDataPath($phpBotRoot, $userId, $val);
                    $config->set($lockKey, $resolved);
                }
            }
        }
    }

    $cronFiles = [
        'followup'  => 'lead_followup.php',
        'reminder'  => 'reminder.php',
        'learn'     => 'learn.php',
        'classify'  => 'classify_outcomes.php',
    ];
    $cronFile = $phpBotRoot . '/cron/' . $cronFiles[$cronType];
    if (!file_exists($cronFile)) {
        fwrite(STDERR, "[cron_runner] Cron file not found: {$cronFile}\n");
        exit(2);
    }

    // Inyectar el config del usuario para que el cron lo detecte y ejecutarlo
    // en scope global (nivel top-level), no dentro de una función.
    $GLOBALS['_cron_runner_config'] = $config;
    $GLOBALS['_cron_runner_user_id'] = $userId;

    require $cronFile;
    exit(0);
}

// Get all active users
$usersFile = $phpBotRoot . '/data/users.json';
if (!file_exists($usersFile)) {
    // Legacy mode: just run the cron for admin
    echo "[cron_runner] Legacy mode — running {$cronType} for root config\n";
    $cmd = sprintf(
        '%s %s %s --user=1%s 2>&1',
        escapeshellarg((string) (PHP_BINARY ?: 'php')),
        escapeshellarg(__FILE__),
        escapeshellarg($cronType),
        $extraArgs
    );
    echo shell_exec($cmd) ?: 'OK';
    echo "\n";
    exit(0);
}

$usersData = @json_decode((string)@file_get_contents($usersFile), true);
if (!is_array($usersData) || empty($usersData['users'])) {
    echo "[cron_runner] No users found\n";
    exit(0);
}

echo "[cron_runner] Starting {$cronType} for " . count($usersData['users']) . " users\n";

$phpBinary = (string) (PHP_BINARY ?: 'php');
$script = __FILE__;

foreach ($usersData['users'] as $user) {
    $userId = (int) ($user['id'] ?? 0);
    $active = (bool) ($user['active'] ?? true);
    if ($userId <= 0 || !$active) continue;

    $username = (string) ($user['username'] ?? "user_{$userId}");
    echo "[cron_runner] User {$userId} ({$username}): ";

    // Aislamiento por usuario: cada cron se ejecuta en un subproceso PHP
    // separado. Los archivos cron declaran funciones top-level y NO pueden
    // incluirse varias veces en el mismo proceso (declaración duplicada).
    $cmd = sprintf(
        '%s %s %s --user=%d%s 2>&1',
        escapeshellarg($phpBinary),
        escapeshellarg($script),
        escapeshellarg($cronType),
        $userId,
        $extraArgs
    );
    $output = shell_exec($cmd);
    if ($output === null) {
        echo "ERROR: no se pudo ejecutar\n";
        continue;
    }
    $trimmed = trim($output);
    echo ($trimmed !== '' ? $trimmed : 'OK') . "\n";
}

echo "[cron_runner] Done\n";

