<?php

declare(strict_types=1);

/**
 * Ingesta externa de avisos. PHP 7.x, sin framework ni dependencias externas.
 * Helpers compartidos entre api/avisos_ingest.php y los tests de tools/.
 */

function avisos_ingest_load_local_config(string $defaultPath, string $envKey = 'AVISOS_INGEST_CONFIG'): array
{
    $path = getenv($envKey);
    if (!is_string($path) || trim($path) === '') $path = $defaultPath;
    if (!is_string($path) || !is_file($path)) return array();
    $config = require $path;
    return is_array($config) ? $config : array();
}

function avisos_ingest_signature(string $timestamp, string $nonce, string $rawBody, string $secret): string
{
    return hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $rawBody, $secret);
}

function avisos_ingest_mkdir(string $path)
{
    if (!is_dir($path) && !@mkdir($path, 0770, true) && !is_dir($path)) {
        throw new RuntimeException('No se pudo preparar el almacenamiento de ingestión');
    }
}

function avisos_ingest_acquire_lock(string $path)
{
    $handle = @fopen($path, 'c');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        throw new RuntimeException('No se pudo adquirir el lock de ingestión');
    }
    return $handle;
}

/** Atomically records a nonce; false means replay. */
function avisos_ingest_register_nonce(string $runtimeDir, string $nonce, int $timestamp, int $ttl = 300): bool
{
    $nonce = trim($nonce);
    if ($nonce === '' || strlen($nonce) > 128) {
        return false;
    }
    avisos_ingest_mkdir($runtimeDir);
    $nonceDir = $runtimeDir . '/nonces';
    avisos_ingest_mkdir($nonceDir);
    $lock = avisos_ingest_acquire_lock($runtimeDir . '/nonce.lock');
    try {
        $now = time();
        foreach ((array)glob($nonceDir . '/*.json') as $oldFile) {
            $old = json_decode((string)@file_get_contents($oldFile), true);
            if (!is_array($old) || $now - (int)($old['seen_at'] ?? 0) > $ttl) {
                @unlink($oldFile);
            }
        }
        $path = $nonceDir . '/' . hash('sha256', $nonce) . '.json';
        if (is_file($path)) {
            return false;
        }
        $data = json_encode(array('nonce' => $nonce, 'timestamp' => $timestamp, 'seen_at' => $now));
        return $data !== false && @file_put_contents($path, $data, LOCK_EX) !== false;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function avisos_ingest_normalize_severity($severity): string
{
    if (is_array($severity) || is_object($severity)) return 'media';
    $severity = strtolower(trim((string)$severity));
    return in_array($severity, array('baja', 'media', 'alta'), true) ? $severity : 'media';
}

/**
 * Valida y normaliza el payload JSON del endpoint.
 * Lanza InvalidArgumentException si el payload no es usable.
 */
function avisos_ingest_validate_payload(array $payload): array
{
    $title = trim((string)($payload['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('title es obligatorio');
    }
    $message = trim((string)($payload['message'] ?? ''));
    if ($message === '') {
        throw new InvalidArgumentException('message es obligatorio');
    }
    $engine = trim((string)($payload['engine'] ?? 'manual'));
    if ($engine === '') {
        $engine = 'manual';
    }
    $meta = $payload['meta'] ?? array();
    if (!is_array($meta)) {
        throw new InvalidArgumentException('meta debe ser un objeto JSON');
    }
    return array(
        'title' => $title,
        'message' => $message,
        'severity' => avisos_ingest_normalize_severity($payload['severity'] ?? 'media'),
        'engine' => $engine,
        'meta' => $meta,
        'source_key' => trim((string)($payload['source_key'] ?? '')),
    );
}
