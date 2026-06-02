<?php

declare(strict_types=1);

namespace WasapBot\Core;

/**
 * Configuration manager — reads config.dist.json first, then overlays
 * config.local.json (gitignored) via array_replace_recursive.
 *
 * Strategy:
 *   config.dist.json  → committed to git, all secrets = "CHANGEME_*"
 *   config.local.json → gitignored, contains real secrets per deployment
 *
 * Dot-notation key access: get('waha.base_ip') navigates nested arrays.
 * Setting values is in-memory only; call save() to persist as config.local.json.
 */
final class Config implements ConfigInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * @param string $configDir Base directory containing config.dist.json and config.local.json.
     */
    public function __construct(
        private readonly string $configDir,
    ) {
        $this->load();
    }

    // ──────────────────────────────────────────────
    //  ConfigInterface
    // ──────────────────────────────────────────────

    public function get(string $keyPath, mixed $default = null): mixed
    {
        $keys = explode('.', $keyPath);
        $current = $this->data;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return $default;
            }
            $current = $current[$key];
        }

        return $current;
    }

    public function set(string $keyPath, mixed $value): void
    {
        $keys = explode('.', $keyPath);
        $current = &$this->data;

        $lastKey = array_pop($keys);

        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }

        $current[$lastKey] = $value;
    }

    public function save(): void
    {
        $localPath = $this->configDir . '/config.local.json';

        $json = json_encode(
            $this->data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            throw new \RuntimeException(
                'Config::save() — JSON encode failed: ' . json_last_error_msg()
            );
        }

        $written = @file_put_contents($localPath, $json . "\n", LOCK_EX);

        if ($written === false) {
            throw new \RuntimeException(
                "Config::save() — cannot write to {$localPath}"
            );
        }
    }

    public function all(): array
    {
        return $this->data;
    }

    public function reload(): void
    {
        $this->load();
    }

    /**
     * Returns the base config directory (the directory containing config.dist.json).
     *
     * Exposed as a concrete method (not in ConfigInterface) so that other Core
     * implementations (e.g., Memory) can resolve relative file paths against it.
     */
    public function getConfigDir(): string
    {
        return $this->configDir;
    }

    // ──────────────────────────────────────────────
    //  Internals
    // ──────────────────────────────────────────────

    /**
     * Loads config.dist.json then overlays config.local.json.
     */
    private function load(): void
    {
        $distPath  = $this->configDir . '/config.dist.json';
        $localPath = $this->configDir . '/config.local.json';

        // Start with dist
        $this->data = [];
        $this->data = $this->readJsonFile($distPath);

        // Overlay local
        $local = $this->readJsonFile($localPath);
        if ($local !== []) {
            $this->data = array_replace_recursive($this->data, $local);
        }
    }

    /**
     * Reads and decodes a JSON file. Returns empty array on failure (file missing, invalid JSON, etc.).
     *
     * @return array<string, mixed>
     */
    private function readJsonFile(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
