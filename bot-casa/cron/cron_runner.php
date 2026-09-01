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
    runCron($phpBotRoot, $userId, $cronType, $extraArgs);
    exit(0);
}

// Get all active users
$usersFile = $phpBotRoot . '/data/users.json';
if (!file_exists($usersFile)) {
    // Legacy mode: just run the cron for admin
    echo "[cron_runner] Legacy mode — running {$cronType} for root config\n";
    require_once $phpBotRoot . '/src/Core/ConfigInterface.php';
    require_once $phpBotRoot . '/src/Core/Config.php';
    require_once $phpBotRoot . '/src/BotInterface.php';
    require_once $phpBotRoot . '/src/Bot.php';
    runCron($phpBotRoot, 0, $cronType, $extraArgs);
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

// ─────────────────────────────────────────────────────────

function runCron(string $rootDir, int $userId, string $type, string $extraArgs = ''): void
{
    $configDir = \WasapBot\Bot::resolveUserConfigDir($rootDir, $userId);
    $config = new \WasapBot\Core\Config($configDir, $rootDir);

    // Override data paths for this user if multi-user mode
    if ($userId > 0) {
        $userDataDir = $rootDir . '/data/users/' . $userId;
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
                    $resolved = \WasapBot\Bot::resolveUserDataPath($rootDir, $userId, $val);
                    $config->set($key, $resolved);
                }
            }
            // Override lock files
            $lockKeys = ['cron.followup.lock_file', 'cron.reminder.lock_file'];
            foreach ($lockKeys as $lockKey) {
                $val = $config->get($lockKey, '');
                if (is_string($val) && $val !== '') {
                    $resolved = \WasapBot\Bot::resolveUserDataPath($rootDir, $userId, $val);
                    $config->set($lockKey, $resolved);
                }
            }
        }
    }

    // Parse --days for learn
    if ($type === 'learn' && $extraArgs !== '') {
        if (preg_match('/--days=(\d+)/', $extraArgs, $m)) {
            $extraArgs = ' --days=' . (int) $m[1];
        }
    }

    $cronFiles = [
        'followup'  => 'lead_followup.php',
        'reminder'  => 'reminder.php',
        'learn'     => 'learn.php',
        'classify'  => 'classify_outcomes.php',
    ];

    $cronFile = $rootDir . '/cron/' . $cronFiles[$type];
    if (!file_exists($cronFile)) {
        throw new \RuntimeException("Cron file not found: {$cronFile}");
    }

    // Run the cron with the pre-configured Config
    // We use a trick: set a global that the cron will detect
    $GLOBALS['_cron_runner_config'] = $config;
    $GLOBALS['_cron_runner_user_id'] = $userId;

    ob_start();
    require $cronFile;
    $output = ob_get_clean();
    if ($output) echo " → " . trim(str_replace("\n", " ", $output));
}
