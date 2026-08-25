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

        $this->writeJsonAtomic($localPath, $json);
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

        // Overlay local. Tenant files are always reduced to the explicit
        // tenant-owned allowlist before they are used or persisted.
        $local = $this->readJsonFile($localPath);
        if ($this->centralConfigDir !== null) {
            $originalLocal = $local;
            $local = $this->isLegacyTenantClone($local)
                ? $this->tenantLocalData($this->data)
                : $this->tenantLocalData($local);
            if ($local !== $originalLocal) {
                $this->writeTenantLocal($local);
            } elseif ($local === [] && !file_exists($localPath)) {
                $this->writeTenantLocal($this->tenantLocalData($this->data));
            }
        }
        if ($local !== []) {
            $this->data = array_replace_recursive($this->data, $local);
        }

        if ($this->centralConfigDir !== null) {
            $central = $this->readJsonFile($this->centralConfigDir . '/config.local.json');
            $this->data = $this->mergeExplicitCentralRuntime($this->data, $central);
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
    private function mergeExplicitCentralRuntime(array $tenantData, array $central): array
    {
        foreach ($this->centralFieldAllowlist() as $path) {
            $value = $this->getFromArray($central, $path);
            if ($value !== null) {
                $this->setInArray($tenantData, $path, $value);
            }
        }
        return $tenantData;
    }

    /** @return list<string> */
    private function centralFieldAllowlist(): array
    {
        $paths = [];
        foreach (['openai', 'deepseek'] as $provider) {
            foreach (['api_key', 'chat_url', 'chat_model', 'temperature', 'tone_classifier_model', 'tone_temperature', 'tone_max_tokens', 'response_format'] as $field) {
                $paths[] = $provider . '.' . $field;
            }
        }
        foreach (['api_key', 'base_ip', 'default_port', 'session', 'endpoints', 'webhook_path', 'webhook_secret', 'chat_id_suffix'] as $field) $paths[] = 'waha.' . $field;
        foreach (['client_id', 'secret', 'mode', 'webhook_id'] as $field) $paths[] = 'paypal.' . $field;
        foreach (['tone_classifier', 'voice_ai', 'publicista_copy', 'publicista_descriptor', 'publicista_image'] as $field) $paths[] = 'global_providers.' . $field;
        foreach (['max_attempts', 'base_delay_sec'] as $field) $paths[] = 'ai_retry.' . $field;
        foreach (['max_girls_without_explicit_request', 'girls_json_timeout_ms', 'user_agent'] as $field) $paths[] = 'catalog.' . $field;
        foreach (['enabled', 'from_port', 'from_phone', 'to_phone_override', 'message'] as $field) $paths[] = 'payment_confirmation_whatsapp.' . $field;
        foreach (['bot_token', 'alert_dedup_window_sec'] as $field) $paths[] = 'telegram.' . $field;
        $paths[] = 'urls.blacklist_ws';
        return $paths;
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
        $tenant = [];
        foreach (['human_delays', 'message_variants', 'personality'] as $namespace) {
            if (isset($data[$namespace]) && is_array($data[$namespace])) $tenant[$namespace] = $data[$namespace];
        }
        if (isset($data['prompt']) && is_array($data['prompt'])) {
            $tenant['prompt'] = $data['prompt'];
            unset($tenant['prompt']['playbook_path']);
        }
        if (isset($data['routing']) && is_array($data['routing'])) {
            foreach (['default_enabled_if_not_found', 'lines', 'sender_blacklist'] as $field) {
                if (array_key_exists($field, $data['routing'])) $tenant['routing'][$field] = $data['routing'][$field];
            }
        }

        if (isset($data['telegram']) && is_array($data['telegram'])) {
            foreach (['chat_ids', 'whatsapp_phones', 'alert_enabled'] as $field) {
                if (array_key_exists($field, $data['telegram'])) $tenant['telegram'][$field] = $data['telegram'][$field];
            }
        }
        if (isset($data['urls']['google_maps_location'])) {
            $tenant['urls']['google_maps_location'] = $data['urls']['google_maps_location'];
        }

        if (isset($data['cron']) && is_array($data['cron'])) {
            foreach (['timezone'] as $field) {
                if (array_key_exists($field, $data['cron'])) $tenant['cron'][$field] = $data['cron'][$field];
            }
            foreach (['followup', 'reminder'] as $job) {
                if (!isset($data['cron'][$job]) || !is_array($data['cron'][$job])) continue;
                foreach ($this->tenantCronFields($job) as $field) {
                    if (array_key_exists($field, $data['cron'][$job])) $tenant['cron'][$job][$field] = $data['cron'][$job][$field];
                }
            }
        }

        return $tenant;
    }

    /** @param array<string, mixed> $data */
    private function isLegacyTenantClone(array $data): bool
    {
        foreach (['openai', 'deepseek', 'waha', 'paypal', 'files', 'bot'] as $namespace) {
            if (array_key_exists($namespace, $data)) return true;
        }
        return false;
    }

    /** @return list<string> */
    private function tenantCronFields(string $job): array
    {
        if ($job === 'followup') {
            return ['enabled', 'max_leads_per_run', 'send_window_start', 'send_window_end', 'min_interval_hours_min', 'min_interval_hours_max', 'inter_lead_wait_min_sec', 'inter_lead_wait_max_sec', 'intro_typing_min_us', 'intro_typing_max_us', 'intro_to_girls_pause_min_us', 'intro_to_girls_pause_max_us', 'per_girl_typing_min_us', 'per_girl_typing_max_us', 'inter_girl_pause_min_us', 'inter_girl_pause_max_us', 'closing_typing_min_us', 'closing_typing_max_us', 'intro_variants', 'closing_variants'];
        }
        return ['enabled', 'max_per_run', 'cleanup_interval', 'cleanup_max_age_sec', 'sleep_between_min_us', 'sleep_between_max_us', 'sleep_typing_min_us', 'sleep_typing_max_us', 'message_variants'];
    }

    /** @param array<string, mixed> $data */
    private function writeTenantLocal(array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new \RuntimeException('Config: cannot encode tenant config: ' . json_last_error_msg());
        $this->writeJsonAtomic($this->configDir . '/config.local.json', $json);
    }

    private function writeJsonAtomic(string $path, string $json): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException("Config: cannot create directory {$dir}");
        }
        $tmp = tempnam($dir, '.config-');
        if ($tmp === false || @file_put_contents($tmp, $json . "\n", LOCK_EX) === false || !@rename($tmp, $path)) {
            if ($tmp !== false) @unlink($tmp);
            throw new \RuntimeException("Config: cannot atomically write {$path}");
        }
    }

    private function getFromArray(array $data, string $path): mixed
    {
        foreach (explode('.', $path) as $key) {
            if (!is_array($data) || !array_key_exists($key, $data)) return null;
            $data = $data[$key];
        }
        return $data;
    }

    private function setInArray(array &$data, string $path, mixed $value): void
    {
        $keys = explode('.', $path);
        $last = array_pop($keys);
        $current = &$data;
        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) $current[$key] = [];
            $current = &$current[$key];
        }
        $current[$last] = $value;
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
