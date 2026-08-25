<?php

declare(strict_types=1);

namespace WasapBot\Core;

/**
 * Configuration manager — reads config.dist.json first, then overlays the
 * local config. Tenant configs may additionally read a small, explicit set of
 * platform settings from the central config at runtime.
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
     * @param string      $configDir          Directory containing tenant config.local.json.
     * @param string|null $centralConfigDir   Root directory for shared runtime settings.
     */
    public function __construct(
        private readonly string $configDir,
        private readonly ?string $centralConfigDir = null,
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
        $data = $this->data;
        if ($this->centralConfigDir !== null) {
            $data = $this->tenantLocalData($data);
        }

        $json = json_encode(
            $data,
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
        $distBaseDir = $this->centralConfigDir ?? $this->configDir;
        $distPath  = $distBaseDir . '/config.dist.json';
        $localPath = $this->configDir . '/config.local.json';

        // Start with dist
        $this->data = [];
        $this->data = $this->readJsonFile($distPath);

        // Overlay local
        $local = $this->readJsonFile($localPath);
        if ($this->centralConfigDir !== null) {
            $local = $this->removeLegacyTenantInheritance($local);
        }
        if ($local !== []) {
            $this->data = array_replace_recursive($this->data, $local);
        }

        if ($this->centralConfigDir !== null) {
            $central = $this->readJsonFile($this->centralConfigDir . '/config.local.json');
            $this->data = array_replace_recursive($this->data, $this->centralRuntimeData($central));
        }
    }

    /**
     * Only these root-level sections are shared with tenants at runtime.
     * Tenant-local routing, notifications, prompts, URLs and file paths must
     * never be populated from the central local config.
     *
     * @param array<string, mixed> $central
     * @return array<string, mixed>
     */
    private function centralRuntimeData(array $central): array
    {
        $shared = [];
        foreach (['openai', 'deepseek', 'waha', 'global_providers', 'ai_retry', 'catalog', 'log', 'dedup_coalesce', 'paypal', 'payment_confirmation_whatsapp'] as $key) {
            if (array_key_exists($key, $central)) {
                $shared[$key] = $central[$key];
            }
        }

        // The bot token is platform-wide; tenant notification recipients are not.
        if (isset($central['telegram']) && is_array($central['telegram']) && array_key_exists('bot_token', $central['telegram'])) {
            $shared['telegram'] = ['bot_token' => $central['telegram']['bot_token']];
        }
        if (isset($central['telegram']) && is_array($central['telegram']) && array_key_exists('alert_dedup_window_sec', $central['telegram'])) {
            $shared['telegram']['alert_dedup_window_sec'] = $central['telegram']['alert_dedup_window_sec'];
        }
        if (isset($central['urls']) && is_array($central['urls']) && array_key_exists('blacklist_ws', $central['urls'])) {
            $shared['urls'] = ['blacklist_ws' => $central['urls']['blacklist_ws']];
        }

        return $shared;
    }

    /**
     * Remove runtime/platform and server-derived values before persisting a
     * tenant config. They remain available through centralRuntimeData().
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function tenantLocalData(array $data): array
    {
        foreach (['openai', 'deepseek', 'waha', 'files', 'bot', 'routing', 'paypal', 'payment_confirmation_whatsapp', 'global_providers', 'ai_retry', 'catalog', 'log', 'dedup_coalesce'] as $key) {
            unset($data[$key]);
        }

        if (isset($data['telegram']) && is_array($data['telegram'])) {
            unset($data['telegram']['bot_token']);
            if ($data['telegram'] === []) unset($data['telegram']);
        }

        if (isset($data['urls']) && is_array($data['urls'])) {
            unset($data['urls']['blacklist_ws']);
            if ($data['urls'] === []) unset($data['urls']);
        }

        return $data;
    }

    /**
     * Older tenant files were cloned from the root local config. Presence of
     * platform credentials identifies that format; discard its copied
     * tenant-specific and server-derived sections before they are read.
     *
     * @param array<string, mixed> $local
     * @return array<string, mixed>
     */
    private function removeLegacyTenantInheritance(array $local): array
    {
        $isLegacyClone = isset($local['openai']) || isset($local['deepseek']) || isset($local['waha']) || isset($local['paypal']);
        if (!$isLegacyClone) return $local;

        foreach (['telegram', 'routing', 'files', 'bot'] as $key) unset($local[$key]);
        if (isset($local['urls']) && is_array($local['urls'])) {
            unset($local['urls']['google_maps_location'], $local['urls']['blacklist_ws']);
        }
        return $local;
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
