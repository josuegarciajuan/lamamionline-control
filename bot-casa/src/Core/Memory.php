<?php

declare(strict_types=1);

namespace WasapBot\Core;

/**
 * NDJSON session-memory manager with file locking (patterns from reminder_cron.php).
 *
 * Constructor receives ConfigInterface (to resolve session_memory path) and LoggerInterface.
 *
 * File path is read from config key 'files.session_memory'. If relative, it is
 * resolved against the project root (the directory containing config files).
 *
 * All read/write operations use flock() with LOCK_SH (reads) or LOCK_EX (writes)
 * to prevent concurrent corruption from parallel bot processes.
 */
final class Memory implements MemoryInterface
{
    /** @var string Absolute path to the session NDJSON file. */
    private string $filePath;

    /** @var string Absolute path to the lock file for process-level coordination. */
    private string $lockPath;

    /** @var LoggerInterface */
    private readonly LoggerInterface $logger;

    /**
     * @param ConfigInterface $config Config instance (used to resolve files.session_memory path).
     * @param LoggerInterface $logger Logger instance.
     */
    public function __construct(
        private readonly ConfigInterface $config,
        LoggerInterface $logger,
    ) {
        $this->logger = $logger; // explicit assignment for PHP < 8.1 readonly + promoted

        // Resolve path from config; relative paths are relative to project root
        $rawPath = $this->config->get('files.session_memory', 'data/session_memory.ndjson');

        if (str_starts_with($rawPath, '/')) {
            $this->filePath = $rawPath;
        } else {
            // Derive project root from Config's internal configDir when available
            $baseDir = ($this->config instanceof Config)
                ? $this->config->getConfigDir()
                : dirname(__DIR__, 2);

            $this->filePath = rtrim($baseDir, '/') . '/' . ltrim((string) $rawPath, '/');
        }

        // Lock file path (same directory, hidden file)
        $dir = dirname($this->filePath);
        $this->lockPath = $dir . '/.session_memory.lock';
    }

    // ──────────────────────────────────────────────
    //  MemoryInterface
    // ──────────────────────────────────────────────

    public function read(): array
    {
        $fp = $this->openFile('rb');
        if ($fp === false) {
            return [];
        }

        try {
            if (!flock($fp, LOCK_SH)) {
                return [];
            }

            return $this->readRecordsFromHandle($fp);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public function append(array $record): void
    {
        $this->ensureDir();

        $fp = $this->openFile('cb+');
        if ($fp === false) {
            $this->logger->error("Memory::append — cannot open file: {$this->filePath}");
            return;
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                $this->logger->error("Memory::append — cannot acquire exclusive lock");
                return;
            }

            // Seek to end (file may have grown since open)
            fseek($fp, 0, SEEK_END);

            $line = json_encode(
                $record,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) . "\n";

            fwrite($fp, $line);
            fflush($fp);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public function deleteByThreadId(string $threadId): int
    {
        if ($threadId === '') {
            return 0;
        }

        $fp = $this->openFile('cb+');
        if ($fp === false) {
            return 0;
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                return 0;
            }

            $all = $this->readRecordsFromHandle($fp);
            $kept = [];
            $removed = 0;

            foreach ($all as $record) {
                $tid = (string) ($record['thread_id'] ?? '');
                if ($tid === $threadId) {
                    $removed++;
                    continue;
                }
                $kept[] = $record;
            }

            $this->writeRecordsToHandle($fp, $kept);

            return $removed;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    public function deleteByLineIndex(int $index): bool
    {
        if ($index < 0) {
            return false;
        }

        $fp = $this->openFile('cb+');
        if ($fp === false) {
            return false;
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                return false;
            }

            $all = $this->readRecordsFromHandle($fp);

            if (!isset($all[$index])) {
                return false;
            }

            array_splice($all, $index, 1);
            $this->writeRecordsToHandle($fp, $all);

            return true;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Returns raw lines (as strings) from the NDJSON file — for panel display.
     *
     * @return string[]
     */
    public function getLines(): array
    {
        $fp = $this->openFile('rb');
        if ($fp === false) {
            return [];
        }

        try {
            if (!flock($fp, LOCK_SH)) {
                return [];
            }

            rewind($fp);
            $lines = [];
            while (($line = fgets($fp)) !== false) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $lines[] = $trimmed;
                }
            }

            return $lines;
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /**
     * Counts bot messages by finding lines where the parsed JSON object
     * contains a '| B:' key (bot reply marker).
     */
    public function countMessages(): int
    {
        $lines = $this->getLines();
        $count = 0;

        foreach ($lines as $line) {
            $record = $this->parseLineToRecord($line);
            if ($record !== null && (array_key_exists('| B:', $record) || array_key_exists('bot_reply', $record))) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Extracts the last bot reply from session memory.
     *
     * Supports both the new format (key 'bot_reply' from SessionMemory::buildRecord)
     * and the legacy format (key '| B:' from old NDJSON records).
     */
    public function getLastBotReply(): string
    {
        $lines = $this->getLines();
        $count = count($lines);

        for ($i = $count - 1; $i >= 0; $i--) {
            $record = $this->parseLineToRecord($lines[$i]);
            if ($record === null) {
                continue;
            }
            // New format: bot_reply key (SessionMemory::buildRecord)
            if (array_key_exists('bot_reply', $record) && (string) $record['bot_reply'] !== '') {
                return (string) $record['bot_reply'];
            }
            // Legacy format: '| B:' key
            if (array_key_exists('| B:', $record) && (string) ($record['| B:'] ?? '') !== '') {
                return (string) $record['| B:'];
            }
        }

        return '';
    }

    /**
     * Returns the last N bot replies, normalized (NFKD, lowercase, no diacritics).
     *
     * Normalization removes combining marks (accents, diacritics) to enable
     * fuzzy matching for duplicate-detection purposes. When ext-intl is unavailable,
     * falls back to manual diacritic stripping.
     *
     * @param int $limit Maximum number of recent bot replies to return.
     * @return string[] Normalized bot reply strings (most recent first).
     */
    public function getRecentBotRepliesNorm(int $limit): array
    {
        $lines = $this->getLines();
        $replies = [];

        // Extract all bot reply values by parsing each line as JSON
        foreach ($lines as $line) {
            $record = $this->parseLineToRecord($line);
            if ($record === null) {
                continue;
            }
            // New format: bot_reply key
            if (array_key_exists('bot_reply', $record) && (string) $record['bot_reply'] !== '') {
                $replies[] = (string) $record['bot_reply'];
            // Legacy format: '| B:' key
            } elseif (array_key_exists('| B:', $record)) {
                $replies[] = (string) $record['| B:'];
            }
        }

        // Keep only the last N
        $replies = array_slice($replies, max(0, count($replies) - $limit), $limit);

        // Reverse so most recent is first
        $replies = array_reverse($replies);

        // Normalize each
        $norm = [];
        foreach ($replies as $reply) {
            $norm[] = $this->normalizeString($reply);
        }

        return $norm;
    }

    /**
     * Checks whether any bot message in the given thread contains a greeting pattern.
     *
     * Supports both the new format (key 'bot_reply') and the legacy format (key '| B:').
     */
    public function hasGreeted(string $threadId): bool
    {
        if ($threadId === '') {
            return false;
        }

        $lines = $this->getLines();

        foreach ($lines as $line) {
            $record = $this->parseLineToRecord($line);
            if ($record === null) {
                continue;
            }

            // Must belong to the requested thread
            $tid = (string) ($record['thread_id'] ?? '');
            if ($tid !== $threadId) {
                continue;
            }

            // New format: bot_reply key
            if (array_key_exists('bot_reply', $record)) {
                $botText = (string) $record['bot_reply'];
                if ($this->containsGreeting($botText)) {
                    return true;
                }
                continue;
            }

            // Legacy format: '| B:' key
            if (array_key_exists('| B:', $record)) {
                $botText = (string) $record['| B:'];
                if ($this->containsGreeting($botText)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Truncates the session memory file (empties it).
     */
    public function clear(): void
    {
        $fp = $this->openFile('cb+');
        if ($fp === false) {
            return;
        }

        try {
            if (!flock($fp, LOCK_EX)) {
                return;
            }

            ftruncate($fp, 0);
            rewind($fp);
            fflush($fp);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    // ──────────────────────────────────────────────
    //  Internals — file I/O
    // ──────────────────────────────────────────────

    /**
     * Opens the NDJSON file and ensures the directory exists.
     *
     * @param string $mode fopen mode ('rb' for read, 'cb+' for read/write).
     * @return resource|false
     */
    private function openFile(string $mode): mixed
    {
        $this->ensureDir();

        $fp = @fopen($this->filePath, $mode);
        if ($fp === false) {
            $this->logger->warning(
                "Memory::openFile — cannot open: {$this->filePath}",
                ['mode' => $mode]
            );
            return false;
        }

        return $fp;
    }

    /**
     * Creates the data directory if it does not exist.
     */
    private function ensureDir(): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        // Ensure locks directory too
        $lockDir = dirname($this->lockPath);
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0777, true);
        }
    }

    /**
     * Reads and parses all NDJSON records from an already-opened handle.
     *
     * Caller must hold the appropriate lock.
     *
     * @param resource $fp Open file handle.
     * @return list<array<string, mixed>>
     */
    private function readRecordsFromHandle($fp): array
    {
        rewind($fp);
        $records = [];
        $lineNum = 0;

        while (($line = fgets($fp)) !== false) {
            $lineNum++;
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            try {
                $data = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->logger->warning(
                    "Memory::readRecords — invalid JSON at line {$lineNum}: {$trimmed}",
                    ['error' => $e->getMessage()]
                );
                continue;
            }

            if (!is_array($data)) {
                $this->logger->warning("Memory::readRecords — non-object JSON at line {$lineNum}");
                continue;
            }

            $records[] = $data;
        }

        return $records;
    }

    /**
     * Writes records to the handle (truncates first).
     *
     * Caller must hold LOCK_EX.
     *
     * @param resource                     $fp       Open file handle with exclusive lock.
     * @param list<array<string, mixed>>   $records  Records to write.
     */
    private function writeRecordsToHandle($fp, array $records): void
    {
        ftruncate($fp, 0);
        rewind($fp);

        foreach ($records as $record) {
            try {
                $line = json_encode(
                    $record,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ) . "\n";

                fwrite($fp, $line);
            } catch (\JsonException $e) {
                $this->logger->warning(
                    "Memory::writeRecords — cannot encode record: {$e->getMessage()}"
                );
            }
        }

        fflush($fp);
    }

    // ──────────────────────────────────────────────
    //  Internals — text processing
    // ──────────────────────────────────────────────

    /**
     * Parses a raw NDJSON line string into an associative array record.
     *
     * Returns null for empty lines, invalid JSON, or non-array decoded values.
     *
     * @return array<string, mixed>|null
     */
    private function parseLineToRecord(string $line): ?array
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return null;
        }

        try {
            $data = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Normalizes a string for duplicate detection: NFKD form, lowercase,
     * stripping combining diacritical marks.
     *
     * When ext-intl is loaded, uses \Normalizer::normalize() for proper NFKD
     * decomposition. Otherwise falls back to manually stripping Latin-1
     * combining marks and common accented characters via strtr().
     */
    private function normalizeString(string $input): string
    {
        // NFKD normalization when intl extension is available
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($input, \Normalizer::FORM_KD);
            if ($decomposed !== false) {
                // Strip combining diacritical marks (Unicode category M)
                $stripped = preg_replace('/\p{M}/u', '', $decomposed);
                return mb_strtolower((string) $stripped, 'UTF-8');
            }
        }

        // Fallback: manual diacritic stripping for common Spanish accented chars
        $lower = mb_strtolower($input, 'UTF-8');

        $map = [
            // Spanish / common Latin-1
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'ē' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ī' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ū' => 'u',
            'ñ' => 'n',
            'ç' => 'c',
            'ý' => 'y', 'ÿ' => 'y',
            // Common emoji-safe: keep as-is
        ];

        return strtr($lower, $map);
    }

    /**
     * Checks whether a string contains a known greeting pattern.
     *
     * Matching is case-insensitive and diacritic-aware.
     */
    private function containsGreeting(string $content): bool
    {
        $normalized = $this->normalizeString($content);

        $greetings = [
            'hola',
            'buenas',
            'hey',
            'holi',
            'saludos',
            'buenos dias',
            'buenas tardes',
            'buenas noches',
            'que tal',
            'como estas',
            'como vas',
            'como andas',
            'como te va',
        ];

        foreach ($greetings as $greeting) {
            if (str_contains($normalized, $greeting)) {
                return true;
            }
        }

        return false;
    }
}
