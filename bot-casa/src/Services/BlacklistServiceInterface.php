<?php

declare(strict_types=1);

namespace WasapBot\Services;

/**
 * Blacklist web service contract — checks if a phone is blacklisted.
 */
interface BlacklistServiceInterface
{
    public function isBlacklisted(string $phone): bool;
}
