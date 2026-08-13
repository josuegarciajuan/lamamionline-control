<?php

declare(strict_types=1);

namespace WasapBot\Core;

/**
 * cURL-based HTTP client implementing HttpClientInterface.
 *
 * Features:
 *   - get()  → returns [http_code, body, error]
 *   - post() → JSON-encodes body, sets Content-Type: application/json
 *   - Tracks last HTTP code and last error for post-request inspection
 *   - Logs a warning via LoggerInterface when HTTP status >= 400
 *   - CURLOPT_RETURNTRANSFER, FOLLOWLOCATION, configurable timeouts
 */
final class HttpClient implements HttpClientInterface
{
    private int $lastHttpCode = 0;
    private string $lastError = '';

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    // ──────────────────────────────────────────────
    //  HttpClientInterface
    // ──────────────────────────────────────────────

    /**
     * Performs a GET request.
     *
     * @param string   $url        Target URL.
     * @param string[] $headers    Extra headers (e.g. ['x-api-key: secret']).
     * @param int      $timeoutSec Total request timeout in seconds.
     *
     * @return array{0: int, 1: string, 2: string}  [http_code, response_body, error_string]
     */
    public function get(string $url, array $headers = [], int $timeoutSec = 10): array
    {
        $ch = $this->initHandle($url, $timeoutSec);
        curl_setopt($ch, CURLOPT_HTTPGET, true);

        if ($headers !== []) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        return $this->execute($ch, 'GET', $url);
    }

    /**
     * Performs a POST request with a JSON body.
     *
     * @param string              $url        Target URL.
     * @param array<string, mixed> $body       Associative array to JSON-encode.
     * @param string[]            $headers    Extra headers (Content-Type is added automatically).
     * @param int                 $timeoutSec Total request timeout in seconds.
     *
     * @return array{0: int, 1: string, 2: string}  [http_code, response_body, error_string]
     */
    public function post(
        string $url,
        array $body,
        array $headers = [],
        int $timeoutSec = 10,
    ): array {
        $ch = $this->initHandle($url, $timeoutSec);
        curl_setopt($ch, CURLOPT_POST, true);

        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $this->lastError = 'JSON encode error: ' . json_last_error_msg();
            $this->lastHttpCode = 0;
            curl_close($ch);
            return [0, '', $this->lastError];
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);

        // Ensure Content-Type is present; prepend if not already in custom headers
        $hasContentType = false;
        foreach ($headers as $h) {
            if (stripos($h, 'Content-Type:') === 0) {
                $hasContentType = true;
                break;
            }
        }
        if (!$hasContentType) {
            array_unshift($headers, 'Content-Type: application/json');
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return $this->execute($ch, 'POST', $url);
    }

    public function lastHttpCode(): int
    {
        return $this->lastHttpCode;
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    // ──────────────────────────────────────────────
    //  Internals
    // ──────────────────────────────────────────────

    /**
     * Initializes a cURL handle with common options.
     *
     * @return \CurlHandle|false
     */
    private function initHandle(string $url, int $timeoutSec): \CurlHandle|false
    {
        $ch = curl_init();

        if ($ch === false) {
            $this->lastError = 'curl_init() failed';
            $this->lastHttpCode = 0;
            return false;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeoutSec,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSec),
            CURLOPT_FAILONERROR    => false, // We inspect HTTP code ourselves
        ]);

        return $ch;
    }

    /**
     * Executes the cURL handle, extracts response data, and updates state.
     *
     * @param \CurlHandle|false $ch
     * @return array{0: int, 1: string, 2: string}
     */
    private function execute(\CurlHandle|false $ch, string $method, string $url): array
    {
        if ($ch === false) {
            return [0, '', $this->lastError];
        }

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);

        curl_close($ch);

        $body = is_string($response) ? $response : '';

        // Update state
        $this->lastHttpCode = $httpCode;

        if ($curlErrno !== 0) {
            $this->lastError = $curlError;
            $this->logger->warning("HTTP {$method} cURL error [{$curlErrno}]: {$curlError} — URL: {$url}");
            return [$httpCode, $body, $curlError];
        }

        if ($httpCode >= 400) {
            $truncated = mb_substr($body, 0, 200);
            $this->lastError = "HTTP {$httpCode}";
            $this->logger->warning("HTTP {$method} returned {$httpCode}: {$truncated} — URL: {$url}");
        } else {
            $this->lastError = '';
        }

        return [$httpCode, $body, $this->lastError];
    }
}
