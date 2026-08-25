<?php

declare(strict_types=1);

/**
 * Ingesta de scrapers. PHP 7.x, sin framework ni dependencias externas.
 */

function scraper_ingest_load_local_config(string $defaultPath, string $envKey = 'SCRAPER_INGEST_CONFIG'): array
{
    $path = getenv($envKey);
    if (!is_string($path) || trim($path) === '') $path = $defaultPath;
    if (!is_string($path) || !is_file($path)) return array();
    $config = require $path;
    return is_array($config) ? $config : array();
}

function scraper_ingest_route_for_type(string $type): string
{
    $routes = array(
        'individual' => 'f_clientes',
        'particular' => 'f_clientes',
        'house' => 'casawasap',
        'casa' => 'casawasap',
        'collaborator' => 'publicista',
        'colaborador' => 'publicista',
    );

    return $routes[strtolower(trim($type))] ?? '';
}

function scraper_ingest_signature(string $timestamp, string $nonce, string $rawBody, string $secret): string
{
    return hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $rawBody, $secret);
}

/** Returns E.164 digits for Spanish mobile numbers, or an empty string. */
function scraper_ingest_normalize_phone($value): string
{
    $raw = trim((string)$value);
    $digits = preg_replace('/\D+/', '', $raw);
    if (!is_string($digits)) {
        return '';
    }
    if (strpos($digits, '34') === 0) {
        $national = substr($digits, 2);
    } else {
        $national = $digits;
    }
    if (!preg_match('/^[67][0-9]{8}$/', $national)) {
        return '';
    }
    return '34' . $national;
}

function scraper_ingest_normalize_items(array $payload): array
{
    $items = $payload['items'] ?? $payload['records'] ?? null;
    if ($items === null) {
        $items = array($payload);
    }
    if (!is_array($items)) {
        return array();
    }

    $out = array();
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $phone = scraper_ingest_normalize_phone(
            $item['phone'] ?? $item['telefono'] ?? $item['whatsapp'] ?? $item['group_key'] ?? ''
        );
        if ($phone === '') {
            continue;
        }
        $item['phone'] = $phone;
        $item['group_key'] = $phone;
        $out[] = $item;
    }

    return $out;
}

function scraper_ingest_mkdir(string $path)
{
    if (!is_dir($path) && !@mkdir($path, 0770, true) && !is_dir($path)) {
        throw new RuntimeException('No se pudo preparar el almacenamiento de ingestión');
    }
}

function scraper_ingest_acquire_lock(string $path)
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
function scraper_ingest_register_nonce(string $runtimeDir, string $nonce, int $timestamp, int $ttl = 300): bool
{
    $nonce = trim($nonce);
    if ($nonce === '' || strlen($nonce) > 128) {
        return false;
    }
    scraper_ingest_mkdir($runtimeDir);
    $nonceDir = $runtimeDir . '/nonces';
    scraper_ingest_mkdir($nonceDir);
    $lock = scraper_ingest_acquire_lock($runtimeDir . '/nonce.lock');
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

function scraper_ingest_queue_dir(string $runtimeDir, ?string $queueDir): string
{
    return ($queueDir !== null && trim($queueDir) !== '')
        ? rtrim($queueDir, '/')
        : rtrim($runtimeDir, '/') . '/queues';
}

function scraper_ingest_identifier(string $name): string
{
    return '`' . preg_replace('/[^A-Za-z0-9_]/', '', $name) . '`';
}

function scraper_ingest_individual_values(array $item, array $columns): array
{
    $values = array('telefono' => (string)$item['phone']);
    if (isset($columns['telefono_norm'])) $values['telefono_norm'] = (string)$item['phone'];
    $aliases = array(
        array('nombre_comercial', 'name', 'nombre'),
        array('source', 'fuente'),
        array('provincia', 'province'),
        array('sector', 'category', 'categoria'),
        array('id_usuario', 'usuario_id'),
        array('email', 'correo'),
    );
    foreach ($aliases as $candidateColumns) {
        $target = '';
        foreach ($candidateColumns as $candidate) {
            if (isset($columns[$candidate])) {
                $target = $candidate;
                break;
            }
        }
        if ($target === '') continue;
        foreach ($candidateColumns as $key) {
            if (array_key_exists($key, $item) && trim((string)$item[$key]) !== '') {
                $values[$target] = (string)$item[$key];
                break;
            }
        }
    }
    return $values;
}

/**
 * Direct f_clientes upsert. The column discovery keeps this compatible with
 * the existing production schema without interpolating untrusted identifiers.
 */
function scraper_ingest_write_individual_mysql(array $item, $pdo = null): array
{
    if (!function_exists('crm_db_connect')) {
        require_once dirname(__DIR__) . '/app/db.php';
    }
    $pdo = $pdo instanceof PDO ? $pdo : crm_db_connect(crm_db_default_config());
    if (!$pdo) {
        throw new RuntimeException('No se pudo conectar a telefonosbd');
    }
    $columns = function_exists('crm_db_table_columns')
        ? crm_db_table_columns('f_clientes', $pdo)
        : array();
    if (empty($columns) || !isset($columns['telefono'])) {
        throw new RuntimeException('La tabla f_clientes no tiene la columna telefono');
    }

    $phone = (string)$item['phone'];
    $where = scraper_ingest_identifier('telefono') . ' IN (?, ?)';
    $params = array($phone, substr($phone, 2));
    if (isset($columns['telefono_norm'])) {
        $where .= ' OR ' . scraper_ingest_identifier('telefono_norm') . ' = ?';
        $params[] = $phone;
    }
    $stmt = $pdo->prepare('SELECT * FROM ' . scraper_ingest_identifier('f_clientes') . ' WHERE ' . $where . ' LIMIT 1');
    $stmt->execute($params);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    $values = scraper_ingest_individual_values($item, $columns);
    if (isset($columns['updatedsamp'])) $values['updatedsamp'] = date('Y-m-d H:i:s');
    if (isset($columns['baja']) && !isset($existing)) $values['baja'] = 0;

    if (is_array($existing)) {
        $sets = array();
        $updateParams = array();
        foreach ($values as $column => $value) {
            $sets[] = scraper_ingest_identifier($column) . ' = ?';
            $updateParams[] = $value;
        }
        $updateParams[] = $existing['id'] ?? $existing['telefono'];
        $idColumn = isset($existing['id']) ? 'id' : 'telefono';
        $sql = 'UPDATE ' . scraper_ingest_identifier('f_clientes') . ' SET ' . implode(', ', $sets)
            . ' WHERE ' . scraper_ingest_identifier($idColumn) . ' = ? LIMIT 1';
        $pdo->prepare($sql)->execute($updateParams);
        return array('action' => 'updated', 'phone' => $phone);
    }

    $names = array_keys($values);
    $sql = 'INSERT INTO ' . scraper_ingest_identifier('f_clientes') . ' ('
        . implode(', ', array_map('scraper_ingest_identifier', $names)) . ') VALUES ('
        . implode(', ', array_fill(0, count($names), '?')) . ')';
    $pdo->prepare($sql)->execute(array_values($values));
    return array('action' => 'inserted', 'phone' => $phone);
}

function scraper_ingest_write_queue_item(array $item, string $route, string $queueDir): void
{
    scraper_ingest_mkdir($queueDir);
    $index = abs((int)crc32((string)$item['group_key'])) % 3 + 1;
    $path = $queueDir . '/' . $route . '_' . $index . '.jsonl';
    $handle = @fopen($path, 'ab');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        throw new RuntimeException('No se pudo bloquear la cola de ingestión');
    }
    try {
        $line = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false || fwrite($handle, $line . PHP_EOL) === false || !fflush($handle)) {
            throw new RuntimeException('No se pudo escribir la cola de ingestión');
        }
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function scraper_ingest_store_payload(array $payload, string $runtimeDir, string $eventKey, $queueDir = null, $individualWriter = null): array
{
    $type = strtolower(trim((string)($payload['type'] ?? '')));
    $route = scraper_ingest_route_for_type($type);
    if ($route === '') throw new InvalidArgumentException('Tipo de ingestión no permitido');
    $items = scraper_ingest_normalize_items($payload);
    if (empty($items)) throw new InvalidArgumentException('El evento no contiene móviles españoles válidos');

    scraper_ingest_mkdir($runtimeDir);
    scraper_ingest_mkdir($runtimeDir . '/idempotency');
    $marker = $runtimeDir . '/idempotency/' . hash('sha256', $eventKey) . '.json';
    $lock = scraper_ingest_acquire_lock($runtimeDir . '/ingest.lock');
    try {
        if (is_file($marker)) return array('accepted' => 0, 'duplicate' => true, 'route' => $route, 'event_id' => $eventKey);
        $queuePath = scraper_ingest_queue_dir($runtimeDir, $queueDir);
        foreach ($items as $item) {
            if ($type === 'individual' || $type === 'particular') {
                if (!is_callable($individualWriter)) $individualWriter = 'scraper_ingest_write_individual_mysql';
                call_user_func($individualWriter, $item);
            } else {
                scraper_ingest_write_queue_item($item, $route, $queuePath);
            }
        }
        $data = json_encode(array('event_id' => $eventKey, 'route' => $route, 'accepted' => count($items), 'created_at' => date('c')));
        if ($data === false || @file_put_contents($marker, $data, LOCK_EX) === false) throw new RuntimeException('No se pudo registrar la idempotencia');
        return array('accepted' => count($items), 'duplicate' => false, 'route' => $route, 'event_id' => $eventKey);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
