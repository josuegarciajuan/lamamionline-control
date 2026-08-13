<?php

declare(strict_types=1);

namespace WasapBot\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for api/mensajes.php
 *
 * Tests each API action with temporary NDJSON data files.
 * Runs mensajes.php in a subprocess with path overrides.
 */
final class MensajesApiTest extends TestCase
{
    private string $tmpRoot;
    private string $dataDir;
    private string $memoryFile;
    private string $readStatusFile;
    private string $leadsFile;
    private string $pausedFile;
    private string $cancelDir;

    protected function setUp(): void
    {
        $this->tmpRoot = sys_get_temp_dir() . '/mtest_' . uniqid();
        $this->dataDir = $this->tmpRoot . '/data';
        @mkdir($this->dataDir, 0700, true);

        $this->memoryFile = $this->dataDir . '/session_memory.ndjson';
        $this->readStatusFile = $this->dataDir . '/read_status.json';
        $this->leadsFile = $this->dataDir . '/leads.ndjson';
        $this->pausedFile = $this->dataDir . '/paused_threads.ndjson';
        $this->cancelDir = $this->dataDir . '/cancel';
        @mkdir($this->cancelDir, 0700, true);

        // Create config so Config constructor works
        file_put_contents($this->tmpRoot . '/config.dist.json', json_encode([
            'files' => [
                'session_memory' => $this->memoryFile,
                'leads' => $this->leadsFile,
            ],
            'waha' => [
                'base_ip' => '127.0.0.1',
                'api_key' => 'test',
                'session' => 'default',
            ],
            'human_delays' => [
                'typing' => [
                    'chars_per_sec_min' => 100,
                    'chars_per_sec_max' => 200,
                    'start_min_ms' => 10,
                    'start_max_ms' => 20,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE));

        // CSRF secret
        file_put_contents($this->dataDir . '/.csrf_secret', str_repeat('x', 32));
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpRoot);
    }

    // ── Execute the API ──

    private function callApi(array $getParams = [], array $postData = [], int $userId = 1): array
    {
        $wrapper = __DIR__ . '/mensajes_api_wrapper.php';

        // Build command args
        $args = '';
        foreach ($getParams as $k => $v) {
            $args .= ' ' . escapeshellarg($k . '=' . $v);
        }

        $env = [
            'MENS_API_TMP_ROOT' => $this->tmpRoot,
            'MENS_API_TEST_USER_ID' => (string)$userId,
            'MENS_API_BYPASS_CSRF' => '1',
        ];

        if (!empty($postData)) {
            $env['MENS_API_POST_DATA'] = json_encode($postData, JSON_UNESCAPED_UNICODE);
        }

        // Build env string for exec
        $envStr = '';
        foreach ($env as $k => $v) {
            $envStr .= $k . '=' . escapeshellarg($v) . ' ';
        }

        $cmd = $envStr . 'php ' . escapeshellarg($wrapper) . $args . ' 2>/dev/null';
        $output = [];
        $retCode = 0;
        exec($cmd, $output, $retCode);

        $raw = implode("\n", $output);
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            return ['ok' => false, 'status' => $retCode, 'body' => ['_raw' => $raw]];
        }

        return [
            'ok' => (bool)($body['ok'] ?? false),
            'status' => $retCode,
            'body' => $body,
        ];
    }

    // ── Data helpers ──

    private function writeMemory(array $records): void
    {
        $lines = [];
        foreach ($records as $r) {
            $lines[] = json_encode($r, JSON_UNESCAPED_UNICODE);
        }
        file_put_contents($this->memoryFile, implode("\n", $lines) . "\n");
    }

    private function writeReadStatus(array $data): void
    {
        file_put_contents($this->readStatusFile, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    private function m(string $ts, string $thread, string $userMsg, string $botReply = '', bool $pending = false): array
    {
        return [
            '_seq' => 1, 'ts' => $ts, 'thread_id' => $thread,
            'phone' => explode('_', $thread, 2)[1] ?? '', 'user_msg' => $userMsg,
            'bot_reply' => $botReply, '_pending' => $pending, 'ya_enviado' => [],
        ];
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') continue;
            $p = $dir . '/' . $item;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    // ═══════════════════════════════════════════════
    //  threads
    // ═══════════════════════════════════════════════

    public function test_threads_sorted_by_last_ts(): void
    {
        $this->writeMemory([
            $this->m('2026-06-29T10:00:00Z', 'AAA_346001', 'Hola', ''),
            $this->m('2026-06-29T09:00:00Z', 'BBB_346002', 'Buenas', 'Hola!'),
            $this->m('2026-06-29T10:30:00Z', 'AAA_346001', 'Precio?', '50'),
        ]);

        $r = $this->callApi(['action' => 'threads']);
        $this->assertTrue($r['ok'], 'threads should succeed');
        $threads = $r['body']['threads'] ?? [];
        $this->assertCount(2, $threads);
        $this->assertSame('AAA_346001', $threads[0]['thread_id'], 'first = latest ts');
    }

    public function test_threads_unread_count(): void
    {
        $this->writeMemory([
            $this->m('2026-06-29T10:00:00Z', 'AAA_346001', 'Hola', ''),
            $this->m('2026-06-29T10:01:00Z', 'AAA_346001', 'Info', 'Te cuento'),
            $this->m('2026-06-29T10:02:00Z', 'AAA_346001', 'Gracias', 'De nada'),
        ]);
        $this->writeReadStatus(['AAA_346001' => '2026-06-29T10:01:00Z']);

        $r = $this->callApi(['action' => 'threads']);
        $threads = $r['body']['threads'] ?? [];
        $this->assertSame(1, $threads[0]['unread'] ?? -1);
    }

    public function test_threads_filter_by_last9(): void
    {
        $this->writeMemory([
            $this->m('2026-06-29T10:00:00Z', 'AAA_346001', 'Hola', ''),
            $this->m('2026-06-29T10:00:00Z', 'BBB_346002', 'Otro', ''),
        ]);
        $r = $this->callApi(['action' => 'threads', 'last9' => 'AAA']);
        $this->assertCount(1, $r['body']['threads'] ?? []);
    }

    public function test_threads_empty_memory(): void
    {
        $r = $this->callApi(['action' => 'threads']);
        $this->assertTrue($r['ok']);
        $this->assertCount(0, $r['body']['threads'] ?? []);
    }

    public function test_threads_propagates_sender_lid(): void
    {
        $this->writeMemory([
            ['ts' => '2026-06-29T10:00:00Z', 'thread_id' => 'AAA_346001', 'phone' => '346001', 'user_msg' => 'Hola', '_seq' => 1],
            ['ts' => '2026-06-29T10:01:00Z', 'thread_id' => 'AAA_346001', 'phone' => '346001', 'user_msg' => 'Info', 'sender_lid' => 'lid123', '_seq' => 2],
        ]);
        $r = $this->callApi(['action' => 'threads']);
        $threads = $r['body']['threads'] ?? [];
        $this->assertSame('lid123', $threads[0]['sender_lid'] ?? '');
    }

    // ═══════════════════════════════════════════════
    //  conversation
    // ═══════════════════════════════════════════════

    public function test_conversation_returns_messages(): void
    {
        $this->writeMemory([
            $this->m('2026-06-29T10:00:00Z', 'AAA_346001', 'Hola', 'Hola cari'),
            $this->m('2026-06-29T10:01:00Z', 'AAA_346001', 'Precio?', '50€'),
        ]);
        $r = $this->callApi(['action' => 'conversation', 'thread_id' => 'AAA_346001']);
        $this->assertTrue($r['ok']);
        $this->assertCount(2, $r['body']['conversation'] ?? []);
    }

    public function test_conversation_empty_thread(): void
    {
        $this->writeMemory([
            $this->m('2026-06-29T10:00:00Z', 'AAA_346001', 'Hola', ''),
        ]);
        $r = $this->callApi(['action' => 'conversation', 'thread_id' => 'BBB_nope']);
        $this->assertCount(0, $r['body']['conversation'] ?? []);
    }

    public function test_conversation_requires_thread_id(): void
    {
        $r = $this->callApi(['action' => 'conversation']);
        $this->assertFalse($r['ok']);
    }

    // ═══════════════════════════════════════════════
    //  mark_read
    // ═══════════════════════════════════════════════

    public function test_mark_read_updates_file(): void
    {
        $r = $this->callApi(['action' => 'mark_read'], ['thread_id' => 'AAA_346001']);
        $this->assertTrue($r['ok']);
        $this->assertSame('AAA_346001', $r['body']['thread_id'] ?? '');
        $this->assertFileExists($this->readStatusFile);
    }

    public function test_mark_read_requires_thread_id(): void
    {
        $r = $this->callApi(['action' => 'mark_read'], []);
        $this->assertFalse($r['ok']);
    }

    // ═══════════════════════════════════════════════
    //  mark_all_read
    // ═══════════════════════════════════════════════

    public function test_mark_all_read_requires_post(): void
    {
        $r = $this->callApi(['action' => 'mark_all_read']);
        $this->assertFalse($r['ok']);
    }

    public function test_mark_all_read_updates_only_matching_line(): void
    {
        $this->writeMemory([
            $this->m('2026-06-29T10:00:00Z', 'AAA_346001', 'Hola', ''),
            $this->m('2026-06-29T10:00:00Z', 'BBB_346002', 'Hey', ''),
        ]);
        $this->writeReadStatus([
            'AAA_346001' => '2026-06-29T09:00:00Z',
            'BBB_346002' => '2026-06-29T09:00:00Z',
        ]);

        $r = $this->callApi(['action' => 'mark_all_read'], ['last9' => 'AAA']);
        $this->assertTrue($r['ok']);
        $this->assertSame(1, $r['body']['marked'] ?? 0);

        $rs = json_decode((string) file_get_contents($this->readStatusFile), true);
        // AAA marked to near-now; BBB left untouched
        $this->assertNotSame('2026-06-29T09:00:00Z', $rs['AAA_346001'] ?? '');
        $this->assertSame('2026-06-29T09:00:00Z', $rs['BBB_346002'] ?? '');
    }

    public function test_mark_all_read_marks_every_line_without_last9(): void
    {
        $this->writeMemory([
            $this->m('2026-06-29T10:00:00Z', 'AAA_346001', 'Hola', ''),
            $this->m('2026-06-29T10:00:00Z', 'BBB_346002', 'Hey', ''),
        ]);
        $this->writeReadStatus([
            'AAA_346001' => '2026-06-29T09:00:00Z',
            'BBB_346002' => '2026-06-29T09:00:00Z',
        ]);

        $r = $this->callApi(['action' => 'mark_all_read'], ['last9' => '']);
        $this->assertTrue($r['ok']);
        $this->assertSame(2, $r['body']['marked'] ?? 0);

        $rs = json_decode((string) file_get_contents($this->readStatusFile), true);
        $this->assertNotSame('2026-06-29T09:00:00Z', $rs['AAA_346001'] ?? '');
        $this->assertNotSame('2026-06-29T09:00:00Z', $rs['BBB_346002'] ?? '');
    }

    // ═══════════════════════════════════════════════
    //  read_status
    // ═══════════════════════════════════════════════

    public function test_read_status_returns_data(): void
    {
        $this->writeReadStatus(['A' => 'T1', 'B' => 'T2']);
        $r = $this->callApi(['action' => 'read_status']);
        $this->assertTrue($r['ok']);
        $this->assertCount(2, $r['body']['read_status'] ?? []);
    }

    public function test_read_status_empty_file(): void
    {
        $r = $this->callApi(['action' => 'read_status']);
        $this->assertTrue($r['ok']);
        $this->assertCount(0, $r['body']['read_status'] ?? []);
    }

    // ═══════════════════════════════════════════════
    //  threads_summary
    // ═══════════════════════════════════════════════

    public function test_threads_summary_aggregates(): void
    {
        $this->writeMemory([
            $this->m('2026-06-29T10:00:00Z', 'AAA_346001', 'Hola', ''),
            $this->m('2026-06-29T10:00:00Z', 'AAA_346002', 'Buenas', ''),
            $this->m('2026-06-29T10:00:00Z', 'BBB_346003', 'Hey', ''),
        ]);
        $r = $this->callApi(['action' => 'threads_summary']);
        $s = $r['body']['summary'] ?? [];
        $this->assertArrayHasKey('AAA', $s);
        $this->assertSame(2, $s['AAA']['total_convos'] ?? 0);
    }

    public function test_threads_summary_filter_by_last9(): void
    {
        $this->writeMemory([
            $this->m('2026-06-29T10:00:00Z', 'AAA_346001', 'Hola', ''),
            $this->m('2026-06-29T10:00:00Z', 'BBB_346002', 'Hey', ''),
        ]);
        $r = $this->callApi(['action' => 'threads_summary', 'last9' => 'AAA']);
        $s = $r['body']['summary'] ?? [];
        $this->assertCount(1, $s);
        $this->assertArrayHasKey('AAA', $s);
    }

    // ═══════════════════════════════════════════════
    //  send
    // ═══════════════════════════════════════════════

    public function test_send_requires_params(): void
    {
        $r = $this->callApi(['action' => 'send'], []);
        $this->assertFalse($r['ok']);
    }

    public function test_send_requires_post(): void
    {
        $r = $this->callApi(['action' => 'send']);
        $this->assertFalse($r['ok']);
    }

    // ═══════════════════════════════════════════════
    //  mark_lead
    // ═══════════════════════════════════════════════

    public function test_mark_lead_requires_post(): void
    {
        $r = $this->callApi(['action' => 'mark_lead']);
        $this->assertFalse($r['ok']);
    }

    // ═══════════════════════════════════════════════
    //  paused_list
    // ═══════════════════════════════════════════════

    public function test_paused_list_empty(): void
    {
        $r = $this->callApi(['action' => 'paused_list']);
        $this->assertTrue($r['ok']);
        $this->assertCount(0, $r['body']['paused'] ?? []);
    }

    // ═══════════════════════════════════════════════
    //  Error cases
    // ═══════════════════════════════════════════════

    public function test_unknown_action(): void
    {
        $r = $this->callApi(['action' => 'nonexistent']);
        $this->assertFalse($r['ok']);
    }

    public function test_default_action_is_list(): void
    {
        $r = $this->callApi([]);
        $this->assertFalse($r['ok']);
    }
}
