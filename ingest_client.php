<?php

declare(strict_types=1);

/**
 * Cliente mínimo para scrapers. Configuración exclusivamente por entorno o
 * por un PHP local indicado mediante SCRAPER_INGEST_CONFIG (fuera de Git).
 */

function scraper_ingest_client_config(): array
{
    $config = array();
    $local = getenv('SCRAPER_INGEST_CONFIG');
    if (!is_string($local) || trim($local) === '') $local = __DIR__ . '/ingest_config.php';
    if (is_string($local) && trim($local) !== '' && is_file($local)) {
        $loaded = require $local;
        if (is_array($loaded)) $config = $loaded;
    }
    $endpoint = getenv('SCRAPER_INGEST_ENDPOINT');
    $secret = getenv('SCRAPER_INGEST_HMAC_SECRET');
    if (is_string($endpoint) && trim($endpoint) !== '') $config['endpoint'] = trim($endpoint);
    if (is_string($secret) && trim($secret) !== '') $config['secret'] = trim($secret);
    return $config;
}

if (!function_exists('scraper_ingest_normalize_phone')) {
    function scraper_ingest_normalize_phone($value): string
    {
        $digits = preg_replace('/\D+/', '', trim((string)$value));
        if (!is_string($digits)) return '';
        $national = (strpos($digits, '34') === 0) ? substr($digits, 2) : $digits;
        return preg_match('/^[67][0-9]{8}$/', $national) ? '34' . $national : '';
    }
}

function scraper_ingest_client_nonce(): string
{
    if (function_exists('random_bytes')) return bin2hex(random_bytes(16));
    if (function_exists('openssl_random_pseudo_bytes')) return bin2hex(openssl_random_pseudo_bytes(16));
    return hash('sha256', uniqid('', true) . mt_rand());
}

function scraper_ingest_client_http_post(string $endpoint, string $body, array $headers, int $timeout): array
{
    $headerText = implode("\r\n", $headers);
    if (function_exists('curl_init')) {
        $curl = curl_init($endpoint);
        curl_setopt_array($curl, array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));
        $responseBody = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        return array('status' => $status, 'body' => is_string($responseBody) ? $responseBody : '', 'error' => $error);
    }

    $context = stream_context_create(array('http' => array(
        'method' => 'POST',
        'header' => $headerText,
        'content' => $body,
        'timeout' => $timeout,
        'ignore_errors' => true,
    )));
    $responseBody = @file_get_contents($endpoint, false, $context);
    $status = 0;
    foreach ((array)($http_response_header ?? array()) as $line) {
        if (preg_match('/^HTTP\/[^ ]+ ([0-9]+)/', $line, $match)) $status = (int)$match[1];
    }
    return array('status' => $status, 'body' => is_string($responseBody) ? $responseBody : '', 'error' => $responseBody === false ? 'HTTP request failed' : '');
}

function scraper_ingest_client_spool(string $spoolDir, string $eventId, string $body, string $error): string
{
    if (!is_dir($spoolDir)) @mkdir($spoolDir, 0700, true);
    $path = rtrim($spoolDir, '/') . '/' . hash('sha256', $eventId) . '.json';
    $record = json_encode(array('event_id' => $eventId, 'body' => $body, 'error' => $error, 'stored_at' => date('c')),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($record === false || @file_put_contents($path, $record, LOCK_EX) === false) {
        return '';
    }
    return $path;
}

function scraper_ingest_client_drain_spool(string $spoolDir, int $limit = 20, $transport = null): array
{
    $result = array('attempted' => 0, 'sent' => 0, 'pending' => 0);
    if (!is_dir($spoolDir)) return $result;
    $files = glob(rtrim($spoolDir, '/') . '/*.json');
    if (!is_array($files)) return $result;
    sort($files, SORT_STRING);
    foreach (array_slice($files, 0, max(1, $limit)) as $file) {
        $record = json_decode((string)@file_get_contents($file), true);
        if (!is_array($record) || !isset($record['body'])) {
            @unlink($file);
            continue;
        }
        $payload = json_decode((string)$record['body'], true);
        if (!is_array($payload)) continue;
        $result['attempted']++;
        $sent = scraper_ingest_client_send($payload, $spoolDir, 1, $transport);
        if (!empty($sent['ok'])) {
            @unlink($file);
            $result['sent']++;
        }
    }
    $remaining = glob(rtrim($spoolDir, '/') . '/*.json');
    $result['pending'] = is_array($remaining) ? count($remaining) : 0;
    return $result;
}

/** Sends one stable event; retries use a fresh nonce to satisfy replay protection. */
function scraper_ingest_client_send(array $payload, $spoolDir = null, int $retries = 3, $transport = null): array
{
    $config = scraper_ingest_client_config();
    $endpoint = trim((string)($config['endpoint'] ?? ''));
    $secret = (string)($config['secret'] ?? '');
    if (!isset($payload['event_id']) || trim((string)$payload['event_id']) === '') {
        $payload['event_id'] = hash('sha256', json_encode($payload));
    }
    $eventId = (string)$payload['event_id'];
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) throw new InvalidArgumentException('Payload no serializable');
    if ($endpoint === '' || $secret === '') {
        $spooled = ($spoolDir !== null && trim($spoolDir) !== '')
            ? scraper_ingest_client_spool($spoolDir, $eventId, $body, 'Cliente sin endpoint o secreto') : '';
        return array('ok' => false, 'attempts' => 0, 'event_id' => $eventId,
            'error' => 'Cliente de ingestión sin endpoint o secreto', 'spooled' => $spooled);
    }
    $retries = max(1, min(8, $retries));
    $lastError = 'Sin respuesta';
    for ($attempt = 1; $attempt <= $retries; $attempt++) {
        $timestamp = (string)time();
        $nonce = scraper_ingest_client_nonce();
        $signature = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, $secret);
        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Scraper-Timestamp: ' . $timestamp,
            'X-Scraper-Nonce: ' . $nonce,
            'X-Scraper-Signature: ' . $signature,
        );
        $response = is_callable($transport)
            ? call_user_func($transport, $endpoint, $body, $headers, 15)
            : scraper_ingest_client_http_post($endpoint, $body, $headers, 15);
        $decoded = json_decode((string)$response['body'], true);
        if (($response['status'] >= 200 && $response['status'] < 300) && is_array($decoded) && !empty($decoded['ok'])) {
            return array('ok' => true, 'attempts' => $attempt, 'response' => $decoded, 'event_id' => $eventId);
        }
        $lastError = 'HTTP ' . (int)$response['status'] . ': ' . (string)($response['error'] ?? 'respuesta inválida');
        if ($attempt < $retries) usleep(250000 * $attempt);
    }
    $spooled = '';
    if ($spoolDir !== null && trim($spoolDir) !== '') $spooled = scraper_ingest_client_spool($spoolDir, $eventId, $body, $lastError);
    return array('ok' => false, 'attempts' => $retries, 'event_id' => $eventId, 'error' => $lastError, 'spooled' => $spooled);
}
