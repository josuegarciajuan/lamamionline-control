<?php

declare(strict_types=1);

namespace WasapBot\Memory;

/**
 * Session memory contract — appends messages to session_memory.ndjson with soft locking.
 */
interface SessionMemoryInterface
{
    /**
     * @param array<string, mixed> $meta
     */
    public function appendMessage(
        string $threadId,
        string $phone,
        string $userMessage,
        string $botReply,
        array $meta = []
    ): void;

    /** @return array<int, array<string, mixed>> */
    public function readThread(string $threadId): array;
    /** @return array<int, array<string, mixed>> */
    public function getLastNMessages(string $threadId, int $n): array;
    public function deleteThread(string $threadId): int;
    /** @return list<string> */
    public function listThreadIds(): array;
}
