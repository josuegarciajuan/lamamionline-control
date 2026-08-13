<?php

declare(strict_types=1);

namespace WasapBot\Tests\Support;

use WasapBot\Services\OpenAiClientInterface;
use WasapBot\Services\WahaApiInterface;
use WasapBot\Services\GirlsServiceInterface;
use WasapBot\Services\TelegramServiceInterface;
use WasapBot\Services\BlacklistServiceInterface;
use WasapBot\Memory\SessionMemoryInterface;

/**
 * Fake implementations of all external service interfaces.
 *
 * These are fully scriptable, make no network calls, and use no production data.
 * They record calls so tests can assert on what was sent/received.
 */

final class FakeOpenAiClient implements OpenAiClientInterface
{
    /** @var array<string, mixed>|null */
    public ?array $nextChatResponse = null;

    /** @var array<string, mixed> */
    public array $lastChatArgs = [];

    /** @var array<string, mixed>|null */
    public ?array $nextToneResponse = null;

    public function chat(string $systemPrompt, string $userMessage, array $context = [], string $model = null, array $history = []): array
    {
        $this->lastChatArgs = [
            'systemPrompt' => $systemPrompt,
            'userMessage'  => $userMessage,
            'context'      => $context,
            'model'        => $model,
            'history'      => $history,
        ];
        return $this->nextChatResponse ?? ['choices' => [
            ['message' => ['content' => json_encode(['user_visible_reply' => 'ok'])]],
        ]];
    }

    public function classifyTone(string $userMessage): array
    {
        return $this->nextToneResponse ?? ['sentiment' => 'neutral', 'register' => 'normal', 'urgency' => 'baja'];
    }

    public function getLastRawResponse(): ?array
    {
        return $this->nextChatResponse;
    }
}

final class FakeWahaApi implements WahaApiInterface
{
    /** @var list<array{method: string, args: array}> */
    public array $calls = [];

    public bool $sendTextResult = true;
    public bool $sendHumanizedResult = true;
    public bool $startTypingResult = true;
    public bool $stopTypingResult = true;
    public bool $sendSeenResult = true;
    public bool $sendImageResult = true;

    /** Converts to sendText call (preserves the internal sendQuick alias used by Bot). */
    public function sendQuick(string $baseUrl, string $chatId, string $text, string $session = 'default'): bool
    {
        return $this->sendText($baseUrl, $chatId, $text, $session);
    }

    public function sendText(string $baseUrl, string $chatId, string $text, string $session): bool
    {
        $this->calls[] = ['method' => 'sendText', 'args' => [$baseUrl, $chatId, $text, $session]];
        return $this->sendTextResult;
    }

    public function sendImage(string $baseUrl, string $chatId, string $imageUrl, string $caption, string $session): bool
    {
        $this->calls[] = ['method' => 'sendImage', 'args' => [$baseUrl, $chatId, $imageUrl, $caption, $session]];
        return $this->sendImageResult;
    }

    public function sendHumanized(string $baseUrl, string $chatId, string $text, string $session, array $delayConfig, string $incomingText = '', int $turnCount = 1, float $userResponseTimeSec = 60.0, bool $isBurst = false, bool $isUrgent = false, bool $isReprocess = false): bool
    {
        $this->calls[] = [
            'method' => 'sendHumanized',
            'args'   => [$baseUrl, $chatId, $text, $session, $delayConfig, $incomingText, $turnCount, $userResponseTimeSec],
        ];
        return $this->sendHumanizedResult;
    }

    public function startTyping(string $baseUrl, string $chatId, string $session = 'default'): bool
    {
        $this->calls[] = ['method' => 'startTyping', 'args' => [$baseUrl, $chatId, $session]];
        return $this->startTypingResult;
    }

    public function stopTyping(string $baseUrl, string $chatId, string $session = 'default'): bool
    {
        $this->calls[] = ['method' => 'stopTyping', 'args' => [$baseUrl, $chatId, $session]];
        return $this->stopTypingResult;
    }

    public function sendSeen(string $baseUrl, string $chatId, string $session = 'default'): bool
    {
        $this->calls[] = ['method' => 'sendSeen', 'args' => [$baseUrl, $chatId, $session]];
        return $this->sendSeenResult;
    }
}

final class FakeGirlsService implements GirlsServiceInterface
{
    /** @var list<array{id: string, nombre: string, activa: bool}> */
    public array $girls = [];

    public function fetchActive(): array
    {
        return array_values(array_filter($this->girls, fn(array $g): bool => $g['activa'] ?? false));
    }

    public function fetchAll(): array
    {
        return $this->girls;
    }

    public function getRandomSample(int $count): array
    {
        return array_slice($this->girls, 0, $count);
    }

    public function findByName(string $name): ?array
    {
        foreach ($this->girls as $girl) {
            if (($girl['nombre'] ?? '') === $name) {
                return $girl;
            }
        }
        return null;
    }

    public function reload(): void
    {
        // no-op in fake
    }
}

final class FakeTelegramService implements TelegramServiceInterface
{
    public bool $enabled = true;
    public int $alertCount = 0;

    /** @var list<array<string, mixed>> */
    public array $alerts = [];

    /** @var list<array{chatId: string, text: string}> */
    public array $messages = [];

    public function sendLeadAlert(array $ctx): void
    {
        if (!$this->enabled) return;
        $this->alertCount++;
        $this->alerts[] = $ctx;
    }

    public function sendMessage(string $chatId, string $text): bool
    {
        $this->messages[] = ['chatId' => $chatId, 'text' => $text];
        return true;
    }
}

final class FakeBlacklistService implements BlacklistServiceInterface
{
    /** @var list<string> */
    public array $blockedPhones = [];

    public bool $allowAll = false;

    public function isBlacklisted(string $phone): bool
    {
        if ($this->allowAll) return true;
        return in_array($phone, $this->blockedPhones, true);
    }
}

/**
 * Memory used by ContextAssembler — backed by a temporary file.
 */
final class FakeMemory implements \WasapBot\Core\MemoryInterface
{
    private string $lastReply = '';
    /** @var list<string> */
    private array $recentNorm = [];

    public function getLastBotReply(): string
    {
        return $this->lastReply;
    }

    /** @return list<string> */
    public function getRecentBotRepliesNorm(int $limit = 5): array
    {
        return array_slice($this->recentNorm, -$limit);
    }

    public function setLastReply(string $reply): void
    {
        $this->lastReply = $reply;
    }

    /** @param list<string> $norms */
    public function setRecentNorm(array $norms): void
    {
        $this->recentNorm = $norms;
    }

    // ── Remaining MemoryInterface stubs ──
    public function read(): array { return []; }
    public function append(array $record): void {}
    public function deleteByThreadId(string $threadId): int { return 0; }
    public function deleteByLineIndex(int $index): bool { return false; }
    public function getLines(): array { return []; }
    public function countMessages(): int { return 0; }
    public function hasGreeted(string $threadId): bool { return false; }
    public function clear(): void {}
}

/**
 * SessionMemory backed by a temporary file (for ContextAssembler and FSM testing).
 */
final class FakeSessionMemory implements SessionMemoryInterface
{
    private string $path;

    /** @var list<array<string, mixed>> */
    private array $records = [];

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function readThread(string $threadId): array
    {
        return array_values(array_filter(
            $this->records,
            fn(array $r) => ($r['thread_id'] ?? '') === $threadId
        ));
    }

    public function appendMessage(string $threadId, string $phone, string $userMsg, string $botReply, array $extra = []): void
    {
        $this->records[] = array_merge($extra, [
            'thread_id'  => $threadId,
            'phone'      => $phone,
            'user_msg'   => $userMsg,
            'bot_reply'  => $botReply,
            'ts'         => gmdate('Y-m-d\TH:i:s\Z'),
            '_pending'   => false,
        ]);
    }

    /** @param list<array<string, mixed>> $records */
    public function setRecords(array $records): void
    {
        $this->records = $records;
    }

    public function getLastNMessages(string $threadId, int $n): array
    {
        $thread = $this->readThread($threadId);
        return array_slice($thread, -$n);
    }

    public function deleteThread(string $threadId): int
    {
        $before = count($this->records);
        $this->records = array_values(array_filter(
            $this->records,
            fn(array $r) => ($r['thread_id'] ?? '') !== $threadId
        ));
        return $before - count($this->records);
    }

    /** @return list<string> */
    public function listThreadIds(): array
    {
        $ids = [];
        foreach ($this->records as $r) {
            $id = (string) ($r['thread_id'] ?? '');
            if ($id !== '' && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    public function path(): string
    {
        return $this->path;
    }
}
