<?php

declare(strict_types=1);

namespace WasapBot\Core;

use Psr\Log\LoggerInterface;

/**
 * Memory contract — NDJSON session memory with file locking.
 * Reuses patterns from lead_followup_cron.php and reminder_cron.php.
 */
interface MemoryInterface
{
    /** @return array<int, array<string, mixed>> */
    public function read(): array;
    /** @param array<string, mixed> $record */
    public function append(array $record): void;
    public function deleteByThreadId(string $threadId): int;
    public function deleteByLineIndex(int $index): bool;
    /** @return array<int, array<string, mixed>> */
    public function getLines(): array;
    public function countMessages(): int;
    public function getLastBotReply(): string;
    /** @return list<string> */
    public function getRecentBotRepliesNorm(int $limit): array;
    public function hasGreeted(string $threadId): bool;
    public function clear(): void;
}
