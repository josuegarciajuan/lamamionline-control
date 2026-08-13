<?php

declare(strict_types=1);

namespace WasapBot\Tests\Support;

use WasapBot\Core\Config;
use WasapBot\Core\ConfigInterface;

/**
 * TmpEnv — creates an isolated, temporary environment for tests.
 *
 * Ensures zero contact with the production `data/` directory by redirecting
 * ALL file-based paths (session_memory, leads, locks, bot_mode, etc.) to a
 * temp directory under sys_get_temp_dir().
 *
 * Usage:
 *   $env = new TmpEnv();
 *   $config = $env->config;   // WasapBot\Core\Config pointed at tmp dir
 *   $tmpDir = $env->tmpDir;   // absolute path to temp directory
 *   unset($env);             // cleanup on destructor (or ->cleanup())
 */
final class TmpEnv
{
    public readonly ConfigInterface $config;
    public readonly string $tmpDir;

    public function __construct()
    {
        $this->tmpDir = sys_get_temp_dir() . '/wasapbot_test_' . uniqid();
        @mkdir($this->tmpDir, 0700, true);

        $dataDir = $this->tmpDir . '/data';
        @mkdir($dataDir, 0700, true);
        @mkdir($dataDir . '/locks', 0700, true);
        @mkdir($dataDir . '/locks/event_dedup', 0700, true);
        @mkdir($dataDir . '/locks/coalesce', 0700, true);
        @mkdir($dataDir . '/locks/inflight', 0700, true);

        // Copy config.dist.json as the base template
        $realDist = WASAPBOT_ROOT . '/config.dist.json';
        if (file_exists($realDist)) {
            copy($realDist, $this->tmpDir . '/config.dist.json');
        }

        // Build overrides pointing ALL file paths + locks to tmp dir
        $override = [
            'bot' => [
                'mode_file' => $dataDir . '/.bot_mode',
            ],
            'files' => [
                'base_data_dir'        => $dataDir,
                'session_memory'       => $dataDir . '/session_memory.ndjson',
                'session_memory_tmp'   => $dataDir . '/session_memory.ndjson.tmp',
                'session_memory_lock'  => $dataDir . '/locks/session_memory.lock',
                'playbook'             => $dataDir . '/playbook.md',
                'wa_raw_payload'       => $dataDir . '/wa_last_raw.json',
                'leads'                => $dataDir . '/leads.ndjson',
                'leads_lock'           => $dataDir . '/locks/leads.lock',
                'reminders'            => $dataDir . '/reminders_pending.ndjson',
                'reminders_lock'       => $dataDir . '/locks/reminders.lock',
                'followups_log'        => $dataDir . '/followups_log.ndjson',
                'paused_threads'       => $dataDir . '/paused_threads.ndjson',
                'event_dedup_dir'      => $dataDir . '/locks/event_dedup',
                'coalesce_dir'         => $dataDir . '/locks/coalesce',
                'lock_dir'             => $dataDir . '/locks',
                'bot_log'              => $dataDir . '/bot.log',
            ],
            'routing' => [
                'default_enabled_if_not_found' => true,
                'lines' => [
                    [
                        'last9'   => '000000000',
                        'port'    => 3000,
                        'label'   => 'test-line',
                        'enabled' => true,
                    ],
                ],
                'sender_blacklist' => [],
            ],
            'waha' => [
                'base_ip'      => '127.0.0.1',
                'default_port' => 3000,
                'session'      => 'test',
                'webhook_secret' => '',
            ],
            'dedup_coalesce' => [
                'coalesce_window_sec'             => 12,
                'coalesce_sleep_before_send_sec'  => 0,   // Sleep disabled for tests
                'dedup_file_ttl_minutes'          => 1,
            ],
            'prompt' => [
                'template' => 'test template',
                'sections' => ['rol' => 'test'],
                'mode' => 'natural_v2',
                'template_v2' => 'test template v2',
                'sections_v2' => ['rol' => 'test v2'],
                'system_prompt' => null,
                'playbook_path' => 'data/playbook.md',
            ],
        ];

        $json = json_encode($override, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents($this->tmpDir . '/config.local.json', $json);

        // Create a stub playbook to prevent path not found errors
        file_put_contents($dataDir . '/playbook.md', '# Playbook de prueba');

        $this->config = new Config($this->tmpDir);

        // Ensure bot is 'running' by default
        $modeFile = $dataDir . '/.bot_mode';
        file_put_contents($modeFile, 'running');
    }

    public function __destruct()
    {
        $this->cleanup();
    }

    public function cleanup(): void
    {
        if (isset($this->tmpDir) && is_dir($this->tmpDir)) {
            self::removeTree($this->tmpDir);
        }
    }

    /** Set bot to stopped mode (writes 'stop' to mode file). */
    public function stopBot(): void
    {
        $path = $this->config->get('bot.mode_file', '');
        if ($path !== '') file_put_contents($path, 'stop');
    }

    /** Set bot to running mode. */
    public function startBot(): void
    {
        $path = $this->config->get('bot.mode_file', '');
        if ($path !== '') file_put_contents($path, 'running');
    }

    /** Pause a specific thread. */
    public function pauseThread(string $threadId): void
    {
        $path = $this->config->get('files.paused_threads', '');
        if ($path === '') return;
        $entry = json_encode(['thread_id' => $threadId, 'paused_at' => gmdate('c')]);
        file_put_contents($path, $entry . "\n", FILE_APPEND | LOCK_EX);
    }

    /** Read the session_memory.ndjson file content as array of records. */
    public function readSessionMemory(): array
    {
        $path = $this->config->get('files.session_memory', '');
        if ($path === '' || !file_exists($path)) return [];
        $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return [];
        return array_map(fn(string $l) => json_decode($l, true) ?: [], $lines);
    }

    /** Write a raw message record directly into session_memory (simulating webhook persist). */
    public function writeSessionRecord(array $record): void
    {
        $path = $this->config->get('files.session_memory', '');
        if ($path === '') return;
        file_put_contents($path, json_encode($record, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    }

    private static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? self::removeTree($path) : unlink($path);
        }
        rmdir($dir);
    }
}
