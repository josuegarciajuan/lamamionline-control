<?php

declare(strict_types=1);

namespace WasapBot\Core;

/**
 * HTTP client contract — wraps cURL with retry, timeout, error handling.
 */
interface HttpClientInterface
{
    /**
     * @param array<string, mixed> $headers
     * @return array{0: int, 1: string, 2: string}
     */
    public function get(string $url, array $headers = [], int $timeoutSec = 10): array;

    /**
     * @param array<string, mixed> $body
     * @param array<string, mixed> $headers
     * @return array{0: int, 1: string, 2: string}
     */
    public function post(string $url, array $body, array $headers = [], int $timeoutSec = 10): array;
    public function lastHttpCode(): int;
    public function lastError(): string;
}
