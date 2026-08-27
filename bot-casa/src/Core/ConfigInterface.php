<?php

declare(strict_types=1);

namespace WasapBot\Core;

/**
 * Config contract — reads config.dist.json first, then overlays config.local.json (gitignored).
 *
 * Strategy:
 *   config.dist.json  → committed to git, all secrets = "CHANGEME_*"
 *   config.local.json → gitignored, contains real secrets per deployment
 *
 * The Config implementation merges: dist as base, local overrides any matching keys.
 * Panel saves to config.local.json to never touch the dist template.
 */
interface ConfigInterface
{
    public function get(string $keyPath, mixed $default = null): mixed;
    public function set(string $keyPath, mixed $value): void;
    public function save(): void;
    /** @return array<string, mixed> */
    public function all(): array;
    public function reload(): void;

    /**
     * Returns the base config directory (the directory containing config.dist.json).
     */
    public function getConfigDir(): string;
}
