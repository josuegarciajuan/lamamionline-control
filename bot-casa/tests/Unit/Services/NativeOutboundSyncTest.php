<?php

declare(strict_types=1);

namespace WasapBot\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use WasapBot\Core\ConfigInterface;
use WasapBot\Core\HttpClientInterface;
use WasapBot\Core\LoggerInterface;
use WasapBot\Memory\SessionMemory;
use WasapBot\Services\NativeOutboundSync;
use WasapBot\Tests\Support\TmpEnv;

final class NativeOutboundSyncTest extends TestCase
{
    private TmpEnv $env;
    private FakeNativeHttpClient $http;
    private FakeNativeLogger $logger;

    protected function setUp(): void
    {
        $this->env = new TmpEnv();
        $this->http = new FakeNativeHttpClient();
        $this->logger = new FakeNativeLogger();
    }

    protected function tearDown(): void
    {
        $this->env->cleanup();
    }

    public function test_prefers_sender_lid_and_normalizes_native_outbound_message(): void
    {
        $this->http->body = json_encode([
            ['id' => ['_serialized' => 'true_1'], 'fromMe' => true, 'source' => 'web', 'body' => 'Hola desde WhatsApp', 'timestamp' => 1710000000],
        ]);
        $service = $this->service();

        $result = $service->sync(7, $this->markedLine(), '346000000_34611111111', '34611111111', '12345@lid');

        self::assertSame(1, $result['synced']);
        self::assertStringContainsString('chatId=12345%40lid', $this->http->url);
        $records = $this->env->readSessionMemory();
        self::assertSame('Hola desde WhatsApp', $records[0]['bot_reply']);
        self::assertSame('true_1', $records[0]['waha_message_id']);
        self::assertTrue($records[0]['from_me']);
        self::assertFileExists($this->env->config->get('files.paused_threads'));

        $second = $service->sync(7, $this->markedLine(), '346000000_34611111111', '34611111111', '12345@lid');
        self::assertSame(0, $second['synced']);
        self::assertCount(1, $this->env->readSessionMemory());
    }

    public function test_falls_back_to_phone_and_filters_invalid_api_or_duplicate_messages(): void
    {
        $this->env->writeSessionRecord([
            'thread_id' => '346000000_34611111111',
            'waha_message_id' => 'already-seen',
        ]);
        $this->http->body = json_encode([
            ['id' => 'already-seen', 'fromMe' => true, 'source' => 'web', 'body' => 'duplicado'],
            ['id' => 'api-1', 'fromMe' => true, 'source' => 'api', 'body' => 'API'],
            ['id' => 'incoming', 'fromMe' => false, 'source' => 'web', 'body' => 'entrante'],
            ['id' => 'no-text', 'fromMe' => true, 'source' => 'web', 'body' => ''],
            ['id' => 'native-2', 'fromMe' => true, 'source' => 'web', 'body' => 'válido'],
        ]);

        $result = $this->service()->sync(7, $this->markedLine(), '346000000_34611111111', '34611111111');

        self::assertSame(1, $result['synced']);
        self::assertStringContainsString('chatId=34611111111%40c.us', $this->http->url);
        self::assertCount(2, $this->env->readSessionMemory());
    }

    public function test_unmarked_or_internal_lines_do_not_call_waha(): void
    {
        $this->service()->sync(7, ['port' => 3020], 'thread', '34611111111');
        $this->service()->sync(1, $this->markedLine(), 'thread', '34611111111');

        self::assertSame('', $this->http->url);
        self::assertSame([], $this->env->readSessionMemory());
    }

    /** @return array<string, mixed> */
    private function markedLine(): array
    {
        return ['port' => 3020, 'capture_native_outbound' => true];
    }

    private function service(): NativeOutboundSync
    {
        $memory = new SessionMemory($this->env->config, $this->logger);
        return new NativeOutboundSync(
            $this->env->config,
            $this->http,
            $memory,
            $this->logger,
            (string) $this->env->config->get('files.session_memory'),
            (string) $this->env->config->get('files.paused_threads'),
        );
    }
}

final class FakeNativeHttpClient implements HttpClientInterface
{
    public string $body = '[]';
    public string $url = '';

    public function get(string $url, array $headers = [], int $timeoutSec = 10): array
    {
        $this->url = $url;
        return [200, $this->body, ''];
    }

    public function post(string $url, array $body, array $headers = [], int $timeoutSec = 10): array
    {
        return [200, '{}', ''];
    }

    public function lastHttpCode(): int { return 200; }
    public function lastError(): string { return ''; }
}

final class FakeNativeLogger implements LoggerInterface
{
    public function emergency(string $message, array $context = []): void {}
    public function alert(string $message, array $context = []): void {}
    public function critical(string $message, array $context = []): void {}
    public function error(string $message, array $context = []): void {}
    public function warning(string $message, array $context = []): void {}
    public function notice(string $message, array $context = []): void {}
    public function info(string $message, array $context = []): void {}
    public function debug(string $message, array $context = []): void {}
    public function log(string $level, string $message, array $context = []): void {}
}
