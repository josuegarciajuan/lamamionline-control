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
require_once $phpBotRoot . '/src/Core/ConfigInterface.php';
require_once $phpBotRoot . '/src/Core/Config.php';
require_once $phpBotRoot . '/src/Bot.php';

$cronType = $argv[1] ?? '';
if (!in_array($cronType, ['followup', 'reminder', 'learn', 'classify'], true)) {
    echo "Usage: php cron_runner.php [followup|reminder|learn|classify] [--days=N]\n";
    exit(1);
}

// Get all active users
$usersFile = $phpBotRoot . '/data/users.json';
if (!file_exists($usersFile)) {
    // Legacy mode: just run the cron for admin
    echo "[cron_runner] Legacy mode — running {$cronType} for root config\n";
    runCron($phpBotRoot, 0, $cronType);
    exit(0);
}

$usersData = @json_decode((string)@file_get_contents($usersFile), true);
if (!is_array($usersData) || empty($usersData['users'])) {
    echo "[cron_runner] No users found\n";
    exit(0);
}

echo "[cron_runner] Starting {$cronType} for " . count($usersData['users']) . " users\n";

foreach ($usersData['users'] as $user) {
    $userId = (int) ($user['id'] ?? 0);
    $active = (bool) ($user['active'] ?? true);
    if ($userId <= 0 || !$active) continue;

    $username = (string) ($user['username'] ?? "user_{$userId}");
    echo "[cron_runner] User {$userId} ({$username}): ";

    try {
        runCron($phpBotRoot, $userId, $cronType);
        echo "OK\n";
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}

echo "[cron_runner] Done\n";

// ─────────────────────────────────────────────────────────

function runCron(string $rootDir, int $userId, string $type): void
{
    $configDir = \WasapBot\Bot::resolveUserConfigDir($rootDir, $userId);
    $config = new \WasapBot\Core\Config($configDir);

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

    // Run the appropriate cron
    $extraArgs = '';
    if ($type === 'learn') {
        $days = 7;
        for ($i = 2; $i < count($GLOBALS['argv'] ?? []); $i++) {
            if (str_starts_with($GLOBALS['argv'][$i], '--days=')) {
                $days = (int) substr($GLOBALS['argv'][$i], 7);
            }
        }
        $extraArgs = " --days={$days}";
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
