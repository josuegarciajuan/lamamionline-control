<?php

declare(strict_types=1);

namespace WasapBot\Services;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\HttpClientInterface;
use WasapBot\Core\LoggerInterface;

/**
 * Blacklist web-service client — checks whether a phone number is blacklisted.
 *
 * Fails open: returns false (not blacklisted) on any error to avoid
 * blocking legitimate traffic.
 */
final class BlacklistService implements BlacklistServiceInterface
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Check if a phone number is on the blacklist.
     *
     * Fails open — returns false if the request cannot be completed.
     */
    public function isBlacklisted(string $phone): bool
    {
        $baseUrl = $this->config->get('urls.blacklist_ws', '');

        if ($baseUrl === '') {
            $this->logger->warning('Blacklist WS URL not configured');
            return false;
        }

        $phone = (string) preg_replace('/[^0-9]/', '', $phone);
        if ($phone === '' || $phone === '0') {
            return false;
        }

        $url = rtrim($baseUrl, '/') . '?mode=check&phone=' . urlencode($phone);

        $headers = [
            'Accept: application/json',
        ];

        try {
            [$httpCode, $body] = $this->http->get($url, $headers, 10);

            if ($httpCode < 200 || $httpCode >= 300) {
                $this->logger->warning("Blacklist WS returned HTTP {$httpCode} for phone {$phone}", [
                    'error' => $this->http->lastError(),
                ]);
                return false; // fail-open
            }

            try {
                $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $this->logger->warning("Blacklist WS non-JSON response for phone {$phone}: {$e->getMessage()}");
                return false; // fail-open
            }

            if (!is_array($data)) {
                return false;
            }

            return $this->asBool($data['blacklisted'] ?? false);

        } catch (\Throwable $e) {
            $this->logger->error("Blacklist WS exception for phone {$phone}: {$e->getMessage()}");
        }

        return false; // fail-open
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Coerce a value to boolean (matches the n8n bot.json pattern).
     */
    private function asBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['true', '1', 'yes', 'y', 'si', 'sí'], true);
        }
        return false;
    }
}
