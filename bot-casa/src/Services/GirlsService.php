<?php

declare(strict_types=1);

namespace WasapBot\Services;

use WasapBot\Core\ConfigInterface;
use WasapBot\Core\HttpClientInterface;
use WasapBot\Core\LoggerInterface;

/**
 * Girls catalog service — fetches and caches the girls catalog from a remote JSON API.
 *
 * @phpstan-type GirlRecord array{id: string, nombre: string, descripcion_corta: string, fotos: list<string>, activa: bool}
 */
final class GirlsService implements GirlsServiceInterface
{
    /** @var array<int, GirlRecord>|null */
    private ?array $cachedAll = null;

    /** @var array<int, GirlRecord>|null */
    private ?array $cachedActive = null;

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Fetch only active girls.
     *
     * @return array<int, GirlRecord>
     */
    public function fetchActive(): array
    {
        if ($this->cachedActive !== null) {
            return $this->cachedActive;
        }

        $all = $this->fetchAll();
        $active = array_values(array_filter($all, static fn(array $g): bool => $g['activa'] === true));

        $this->cachedActive = $active;
        return $active;
    }

    /**
     * Fetch all girls (active + inactive).
     *
     * @return array<int, GirlRecord>
     */
    public function fetchAll(): array
    {
        if ($this->cachedAll !== null) {
            return $this->cachedAll;
        }

        $url      = $this->config->get('urls.girls_json', '');
        $timeout  = (int) ($this->config->get('catalog.girls_json_timeout_ms', 10000)) / 1000;
        $ua       = $this->config->get('catalog.user_agent', 'WasapBot-PHP/1.0');

        if ($url === '') {
            $this->logger->error('Girls JSON URL not configured');
            return [];
        }

        $headers = [
            'Accept: application/json',
            'User-Agent: ' . $ua,
        ];

        try {
            [$httpCode, $body] = $this->http->get($url, $headers, max(1, (int) $timeout));

            if ($httpCode < 200 || $httpCode >= 300) {
                $this->logger->warning("Girls JSON fetch returned HTTP {$httpCode}", [
                    'error' => $this->http->lastError(),
                ]);
                return [];
            }

            $girls = $this->parseGirls($body);

        } catch (\Throwable $e) {
            $this->logger->error("Girls JSON fetch exception: {$e->getMessage()}");
            return [];
        }

        $this->cachedAll    = $girls;
        $this->cachedActive = array_values(array_filter($girls, static fn(array $g): bool => $g['activa'] === true));

        return $girls;
    }

    /**
     * Get a random sample of active girls.
     *
     * @return array<int, GirlRecord>
     */
    public function getRandomSample(int $count): array
    {
        $active = $this->fetchActive();
        if ($active === []) {
            return [];
        }

        shuffle($active);

        return array_slice($active, 0, max(1, $count));
    }

    /**
     * Find a girl by name (case-insensitive, NFKD normalized).
     *
     * @return GirlRecord|null
     */
    public function findByName(string $name): ?array
    {
        $all = $this->fetchAll();
        $search = $this->normalize($name);

        foreach ($all as $girl) {
            if ($this->normalize($girl['nombre']) === $search) {
                return $girl;
            }
        }

        return null;
    }

    /**
     * Clear internal cache and trigger a re-fetch on next access.
     */
    public function reload(): void
    {
        $this->cachedAll    = null;
        $this->cachedActive = null;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Parse the girls JSON response into normalized GirlRecord arrays.
     *
     * @param string $rawBody Raw JSON from the API.
     * @return array<int, GirlRecord>
     */
    private function parseGirls(string $rawBody): array
    {
        try {
            $data = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->warning("Girls JSON parse error: {$e->getMessage()}");
            return [];
        }

        if (!is_array($data)) {
            return [];
        }

        // Support both {girls: [...]} and {girls_config: [...]} or flat array
        $items = [];
        if (isset($data['girls']) && is_array($data['girls'])) {
            $items = $data['girls'];
        } elseif (isset($data['girls_config']) && is_array($data['girls_config'])) {
            $items = $data['girls_config'];
        } elseif (array_is_list($data)) {
            $items = $data;
        }

        $normalized = [];
        foreach ($items as $girl) {
            if (!is_array($girl)) {
                continue;
            }

            $normalized[] = [
                'id'                 => (string) ($girl['id'] ?? ''),
                'nombre'             => (string) ($girl['nombre'] ?? ''),
                'descripcion_corta'  => (string) ($girl['descripcion_corta'] ?? ''),
                'fotos'              => is_array($girl['fotos'] ?? null)
                    ? array_values(array_map('strval', array_filter($girl['fotos'], 'is_string')))
                    : [],
                'activa'             => $this->asBool($girl['activa'] ?? false),
            ];
        }

        return $normalized;
    }

    /**
     * NFKD-normalize and lowercase a string for case-insensitive comparison.
     */
    private function normalize(string $value): string
    {
        $normalized = @normalizer_normalize($value, \Normalizer::NFKD);
        if ($normalized === false) {
            $normalized = $value;
        }

        $normalized = preg_replace('/[\x{0300}-\x{036f}]/u', '', $normalized);
        $normalized = preg_replace('/\s+/u', ' ', (string) $normalized);

        return mb_strtolower(trim((string) $normalized));
    }

    /**
     * Coerce a value to boolean.
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
