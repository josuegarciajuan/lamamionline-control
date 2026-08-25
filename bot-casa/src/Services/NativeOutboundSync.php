<?php

declare(strict_types=1);

namespace WasapBot\Services;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\HttpClientInterface;
use WasapBot\Core\LoggerInterface;
use WasapBot\Memory\SessionMemoryInterface;

/**
 * Imports human messages sent from WhatsApp native UI for marked tenant lines.
 */
final class NativeOutboundSync
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly HttpClientInterface $http,
        private readonly SessionMemoryInterface $memory,
        private readonly LoggerInterface $logger,
        private readonly string $memoryFile,
        private readonly string $pausedFile,
    ) {
    }

    /**
     * @param array<string, mixed> $line
     * @return array{ok: bool, synced: int, paused: bool, chat_id?: string}
     */
    public function sync(
        int $userId,
        array $line,
        string $threadId,
        string $phone,
        string $senderLid = '',
    ): array {
        if ($userId <= 1 || empty($line['capture_native_outbound']) || $threadId === '') {
            return ['ok' => true, 'synced' => 0, 'paused' => false];
        }

        $port = (int) ($line['port'] ?? 0);
        if ($port <= 0) return ['ok' => true, 'synced' => 0, 'paused' => false];

        $chatId = trim($senderLid) !== '' ? trim($senderLid) : $this->phoneChatId($phone);
        if ($chatId === '') return ['ok' => true, 'synced' => 0, 'paused' => false];

        $server = (string) $this->config->get('waha.base_ip', '100.117.92.74');
        $apiKey = (string) $this->config->get('waha.api_key', '');
        $session = (string) $this->config->get('waha.session', 'default');
        $url = 'http://' . $server . ':' . $port . '/api/messages?' . http_build_query([
            'session' => $session,
            'chatId' => $chatId,
            'limit' => 100,
        ]);

        try {
            [$status, $body] = $this->http->get(
                $url,
                array_filter(['Accept: application/json', $apiKey !== '' ? 'X-Api-Key: ' . $apiKey : '']),
                8,
            );
            if ($status < 200 || $status >= 300) {
                $this->logger->warning('Native outbound sync: WAHA messages request failed', ['status' => $status]);
                return ['ok' => false, 'synced' => 0, 'paused' => false, 'chat_id' => $chatId];
            }
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->logger->warning('Native outbound sync: request/JSON failure', ['error' => $e->getMessage()]);
            return ['ok' => false, 'synced' => 0, 'paused' => false, 'chat_id' => $chatId];
        }

        $messages = $decoded;
        if (is_array($decoded) && isset($decoded['messages']) && is_array($decoded['messages'])) {
            $messages = $decoded['messages'];
        }
        if (!is_array($messages)) return ['ok' => true, 'synced' => 0, 'paused' => false, 'chat_id' => $chatId];

        $knownIds = $this->knownMessageIds();
        $synced = 0;
        foreach ($messages as $message) {
            if (!is_array($message)) continue;
            $normalized = $this->normalize($message);
            if ($normalized === null || isset($knownIds[$normalized['waha_message_id']])) continue;

            $this->memory->appendMessage(
                $threadId,
                $phone,
                '',
                $normalized['bot_reply'],
                [
                    'waha_message_id' => $normalized['waha_message_id'],
                    'sender_lid' => $normalized['sender_lid'],
                    'from_me' => true,
                    'ts' => $normalized['ts'],
                ],
            );
            $knownIds[$normalized['waha_message_id']] = true;
            $synced++;
        }

        $paused = $synced > 0 && $this->pauseThread($threadId);
        return ['ok' => true, 'synced' => $synced, 'paused' => $paused, 'chat_id' => $chatId];
    }

    private function phoneChatId(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        return $digits === '' ? '' : $digits . '@c.us';
    }

    /** @return array<string, true> */
    private function knownMessageIds(): array
    {
        if ($this->memoryFile === '' || !file_exists($this->memoryFile)) return [];
        $known = [];
        foreach (@file($this->memoryFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $record = json_decode($line, true);
            $id = is_array($record) ? trim((string) ($record['waha_message_id'] ?? '')) : '';
            if ($id !== '') $known[$id] = true;
        }
        return $known;
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, string>|null
     */
    private function normalize(array $message): ?array
    {
        $fromMe = (bool) ($message['fromMe'] ?? $message['from_me'] ?? $message['_data']['Info']['IsFromMe'] ?? false);
        $source = strtolower(trim((string) ($message['source'] ?? $message['_data']['source'] ?? '')));
        $id = $message['id'] ?? $message['_data']['id'] ?? '';
        if (is_array($id)) $id = $id['_serialized'] ?? $id['id'] ?? '';
        $id = trim((string) $id);
        $text = $message['body'] ?? $message['text'] ?? $message['_data']['body'] ?? '';
        if (is_array($text)) $text = $text['body'] ?? $text['text'] ?? '';
        $text = trim((string) $text);
        if (!$fromMe || $source === 'api' || $id === '' || $text === '') return null;

        $senderLid = (string) ($message['from'] ?? $message['chatId'] ?? '');
        if (!str_contains($senderLid, '@lid')) $senderLid = '';
        $timestamp = $message['timestamp'] ?? $message['ts'] ?? '';
        $ts = is_numeric($timestamp)
            ? gmdate('Y-m-d\TH:i:s\Z', (int) $timestamp)
            : trim((string) $timestamp);
        return ['waha_message_id' => $id, 'bot_reply' => $text, 'sender_lid' => $senderLid, 'ts' => $ts];
    }

    private function pauseThread(string $threadId): bool
    {
        if ($this->pausedFile === '') return false;
        $dir = dirname($this->pausedFile);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return false;
        foreach (@file($this->pausedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $record = json_decode($line, true);
            if (is_array($record) && (string) ($record['thread_id'] ?? '') === $threadId) return false;
        }
        $record = json_encode(['thread_id' => $threadId, 'paused_at' => gmdate('c')], JSON_UNESCAPED_UNICODE);
        return $record !== false && @file_put_contents($this->pausedFile, $record . "\n", FILE_APPEND | LOCK_EX) !== false;
    }
}
