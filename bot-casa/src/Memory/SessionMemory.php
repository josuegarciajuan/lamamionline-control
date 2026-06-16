<?php

declare(strict_types=1);

namespace WasapBot\Memory;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\LoggerInterface;

/**
 * Session memory implementation with soft locking (mkdir lock directory).
 *
 * Pattern: "Acquire Soft Lock" → "Read Memory For Append" → "Append Memory"
 * → "Write Memory (TMP)" → "Atomic Move TMP→FINAL" → "Release Soft Lock"
 * from bot.json.
 *
 * The soft-lock pattern:
 *  1. Acquire lock:  mkdir <lock_dir>   (retry up to N tries)
 *  2. Read current memory file
 *  3. Append new entry, compute sequence number
 *  4. Write entire content to .tmp file
 *  5. Atomic rename .tmp → final file
 *  6. Release lock:  rmdir <lock_dir>
 */
final class SessionMemory implements SessionMemoryInterface
{
    /** Max retries for acquiring the soft lock. */
    private const int LOCK_TRIES = 50;

    /** Sleep between lock retries in microseconds (0.1s). */
    private const int LOCK_SLEEP_US = 100_000;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    // -------------------------------------------------------------------------
    // SessionMemoryInterface implementation
    // -------------------------------------------------------------------------

    /**
     * Append a user-bot message pair to the session memory.
     *
     * @param string               $threadId    Conversation thread ID.
     * @param string               $phone       User's phone number.
     * @param string               $userMessage The user's incoming message.
     * @param string               $botReply    The bot's reply.
     * @param array<string, mixed> $meta        Additional metadata
     *                                          (speaker_girl_id, selected_girl_name, etc.).
     */
    public function appendMessage(
        string $threadId,
        string $phone,
        string $userMessage,
        string $botReply,
        array $meta = []
    ): void {
        $memoryFile     = (string) $this->config->get('files.session_memory', 'data/session_memory.ndjson');
        $tmpFile        = (string) $this->config->get('files.session_memory_tmp', 'data/session_memory.ndjson.tmp');
        $lockDir        = (string) $this->config->get('files.session_memory_lock', 'data/locks/session_memory.lock');

        // Ensure base directories exist
        $this->ensureDirectory(dirname($memoryFile));
        $this->ensureDirectory(dirname($lockDir));

        // 1. Acquire soft lock
        if (!$this->acquireSoftLock($lockDir)) {
            $this->logger->error("SessionMemory: failed to acquire soft lock after " . self::LOCK_TRIES . " tries");

            return;
        }

        try {
            // 2. Read existing memory
            $existingLines = $this->readAllLines($memoryFile);
            $records = $this->parseLines($existingLines);

            // 2b. Clean matching _pending records for this thread.
            // When the bot responds, remove orphaned _pending flag records
            // so the chat UI doesn't show a stuck typing indicator. We do
            // this atomically inside the same soft lock — no race condition.
            $pendingCleaned = 0;
            $records = array_values(array_filter($records, function (array $rec) use ($threadId, $userMessage, &$pendingCleaned): bool {
                if (!empty($rec['_pending'])
                    && ((string) ($rec['thread_id'] ?? '')) === $threadId
                ) {
                    $pendingMsg = (string) ($rec['user_msg'] ?? '');
                    // Match if the pending user_msg is contained within the
                    // current (possibly coalesced) message being replied to.
                    if ($pendingMsg !== '' && mb_strpos($userMessage, $pendingMsg) !== false) {
                        $pendingCleaned++;
                        return false; // drop this _pending record
                    }
                }
                return true;
            }));
            if ($pendingCleaned > 0) {
                $this->logger->info("SessionMemory: cleaned {$pendingCleaned} matching _pending records for thread={$threadId}");
            }

            // 3. Calculate next sequence number
            $nextSeq = $this->nextSequence($records);

            // 4. Build the new record
            $newRecord = $this->buildRecord($nextSeq, $threadId, $phone, $userMessage, $botReply, $meta);

            // 5. Append new record to lines
            $records[] = $newRecord;

            // 6. Write to tmp file
            $tmpContent = '';
            foreach ($records as $rec) {
                $encoded = json_encode($rec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($encoded !== false) {
                    $tmpContent .= $encoded . "\n";
                }
            }

            $written = @file_put_contents($tmpFile, $tmpContent, LOCK_EX);
            if ($written === false) {
                $this->logger->error("SessionMemory: failed to write tmp file: {$tmpFile}");

                return;
            }

            // 7. Atomic move: rename tmp → final
            if (!@rename($tmpFile, $memoryFile)) {
                $this->logger->error("SessionMemory: atomic rename failed: {$tmpFile} → {$memoryFile}");
            }
        } finally {
            // 8. Release soft lock
            $this->releaseSoftLock($lockDir);
        }
    }

    /**
     * Read all entries for a given thread.
     *
     * @param string $threadId
     * @return list<array<string, mixed>>
     */
    public function readThread(string $threadId): array
    {
        $memoryFile = (string) $this->config->get('files.session_memory', 'data/session_memory.ndjson');
        $all = $this->parseLines($this->readAllLines($memoryFile));

        return array_values(array_filter($all, function (array $rec) use ($threadId): bool {
            return ($rec['thread_id'] ?? '') === $threadId;
        }));
    }

    /**
     * List all unique thread IDs in the session memory file.
     *
     * @return list<string>
     */
    public function listThreadIds(): array
    {
        $memoryFile = (string) $this->config->get('files.session_memory', 'data/session_memory.ndjson');
        $all = $this->parseLines($this->readAllLines($memoryFile));

        $ids = [];
        foreach ($all as $rec) {
            $tid = (string) ($rec['thread_id'] ?? '');
            if ($tid !== '') {
                $ids[] = $tid;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Get the last N messages for a thread.
     *
     * @param string $threadId
     * @param int    $n
     * @return list<array<string, mixed>>
     */
    public function getLastNMessages(string $threadId, int $n): array
    {
        $threadEntries = $this->readThread($threadId);

        if ($n <= 0) {
            return [];
        }

        return array_slice($threadEntries, -$n);
    }

    /**
     * Delete all entries for a given thread.
     *
     * @param string $threadId
     * @return int  Number of entries deleted.
     */
    public function deleteThread(string $threadId): int
    {
        $memoryFile = (string) $this->config->get('files.session_memory', 'data/session_memory.ndjson');
        $lockDir    = (string) $this->config->get('files.session_memory_lock', 'data/locks/session_memory.lock');

        $this->ensureDirectory(dirname($lockDir));

        if (!$this->acquireSoftLock($lockDir)) {
            $this->logger->error("SessionMemory: deleteThread — cannot acquire soft lock");

            return 0;
        }

        try {
            $existingLines = $this->readAllLines($memoryFile);
            $allRecords = $this->parseLines($existingLines);

            $kept = [];
            $removed = 0;

            foreach ($allRecords as $rec) {
                if (($rec['thread_id'] ?? '') === $threadId) {
                    $removed++;
                } else {
                    $kept[] = $rec;
                }
            }

            $tmpFile = (string) $this->config->get('files.session_memory_tmp', 'data/session_memory.ndjson.tmp');

            $tmpContent = '';
            foreach ($kept as $rec) {
                $encoded = json_encode($rec, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($encoded !== false) {
                    $tmpContent .= $encoded . "\n";
                }
            }

            @file_put_contents($tmpFile, $tmpContent, LOCK_EX);
            @rename($tmpFile, $memoryFile);

            $this->logger->info("SessionMemory: deleted {$removed} entries for thread={$threadId}");

            return $removed;
        } finally {
            $this->releaseSoftLock($lockDir);
        }
    }

    // -------------------------------------------------------------------------
    // Soft Locking (mkdir-based, mirroring n8n "Acquire Soft Lock" node)
    // -------------------------------------------------------------------------

    /**
     * Acquire the soft lock by creating a lock directory.
     *
     * Retries up to LOCK_TRIES times with LOCK_SLEEP_US microsecond pauses.
     *
     * @param string $lockDir  Path to the lock directory.
     * @return bool  True if lock was acquired.
     */
    private function acquireSoftLock(string $lockDir): bool
    {
        for ($i = 0; $i < self::LOCK_TRIES; $i++) {
            if (@mkdir($lockDir, 0777, true)) {
                return true;
            }
            usleep(self::LOCK_SLEEP_US);
        }

        return false;
    }

    /**
     * Release the soft lock by removing the lock directory.
     *
     * @param string $lockDir  Path to the lock directory.
     */
    private function releaseSoftLock(string $lockDir): void
    {
        @rmdir($lockDir);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Read all lines from a file, returning an empty array if the file doesn't exist.
     *
     * @param string $filePath
     * @return list<string>
     */
    private function readAllLines(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $content = @file_get_contents($filePath);
        if ($content === false || $content === '') {
            return [];
        }

        $lines = explode("\n", $content);
        $filtered = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed !== '') {
                $filtered[] = $trimmed;
            }
        }

        return $filtered;
    }

    /**
     * Parse a list of NDJSON lines into records.
     *
     * @param list<string> $lines
     * @return list<array<string, mixed>>
     */
    private function parseLines(array $lines): array
    {
        $records = [];

        foreach ($lines as $line) {
            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->logger->warning("SessionMemory: skipping invalid JSON line: {$e->getMessage()}");
                continue;
            }

            if (is_array($decoded)) {
                $records[] = $decoded;
            }
        }

        return $records;
    }

    /**
     * Compute the next sequence number from existing records.
     *
     * @param list<array<string, mixed>> $records
     * @return int
     */
    private function nextSequence(array $records): int
    {
        $maxSeq = 0;

        foreach ($records as $rec) {
            $seq = (int) ($rec['_seq'] ?? 0);
            if ($seq > $maxSeq) {
                $maxSeq = $seq;
            }
        }

        return $maxSeq + 1;
    }

    /**
     * Build the NDJSON record for a message pair.
     *
     * @param int                  $seq
     * @param string               $threadId
     * @param string               $phone
     * @param string               $userMessage
     * @param string               $botReply
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function buildRecord(
        int $seq,
        string $threadId,
        string $phone,
        string $userMessage,
        string $botReply,
        array $meta
    ): array {
        return [
            '_seq'               => $seq,
            'ts'                 => $this->nowIso8601(),
            'thread_id'          => $threadId,
            'phone'              => $phone,
            'user_msg'           => $userMessage,
            'bot_reply'          => $botReply,
            'speaker_girl_id'    => (string) ($meta['speaker_girl_id'] ?? ''),
            'speaker_girl_name'  => (string) ($meta['speaker_girl_name'] ?? ''),
            'speaker_mode'       => (string) ($meta['speaker_mode'] ?? ''),
            'selected_girl_id'   => (string) ($meta['selected_girl_id'] ?? ''),
            'selected_girl_name' => (string) ($meta['selected_girl_name'] ?? ''),
            'shown_girls'        => $meta['shown_girls'] ?? $meta['__shown_girls'] ?? [],
            'unshown_girls'      => $meta['unshown_girls'] ?? $meta['__unshown_girls'] ?? [],
            'wants_more_girls'   => !empty($meta['wants_more_girls']),
            'ya_enviado'         => $meta['ya_enviado'] ?? [],
            'sender_lid'         => (string) ($meta['sender_lid'] ?? ''),
        ];
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     */
    private function ensureDirectory(string $dir): void
    {
        if ($dir !== '' && !is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    /**
     * Return the current timestamp in ISO 8601 format.
     */
    private function nowIso8601(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
    }
}
