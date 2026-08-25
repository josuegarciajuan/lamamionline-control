<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/scraper_ingest.php';

$localConfig = scraper_ingest_load_local_config(dirname(__DIR__) . '/data/scraper_ingest_config.php');

function scraper_ingest_json_response(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function scraper_ingest_header(string $name): string
{
    $serverName = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$serverName] ?? ''));
}

$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
if (!$isHttps && PHP_SAPI !== 'cli') {
    scraper_ingest_json_response(400, array('ok' => false, 'error' => 'HTTPS required'));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
    header('Allow: POST');
    scraper_ingest_json_response(405, array('ok' => false, 'error' => 'POST required'));
}

$envSecret = getenv('SCRAPER_INGEST_HMAC_SECRET');
$secret = trim((string)(is_string($envSecret) && trim($envSecret) !== '' ? $envSecret : ($localConfig['secret'] ?? '')));
if ($secret === '') {
    scraper_ingest_json_response(503, array('ok' => false, 'error' => 'Ingestión no configurada'));
}

$rawBody = (string)file_get_contents('php://input');
if ($rawBody === '' || strlen($rawBody) > 1048576) {
    scraper_ingest_json_response(413, array('ok' => false, 'error' => 'Cuerpo inválido o demasiado grande'));
}

$timestamp = scraper_ingest_header('X-Scraper-Timestamp');
$nonce = scraper_ingest_header('X-Scraper-Nonce');
$providedSignature = scraper_ingest_header('X-Scraper-Signature');
$envMaxSkew = getenv('SCRAPER_INGEST_MAX_SKEW');
$configuredMaxSkew = is_string($envMaxSkew) && trim($envMaxSkew) !== ''
    ? $envMaxSkew : ($localConfig['max_skew'] ?? 300);
$maxSkew = max(30, (int)$configuredMaxSkew);

if (!ctype_digit($timestamp) || $nonce === '' || $providedSignature === ''
    || abs(time() - (int)$timestamp) > $maxSkew) {
    scraper_ingest_json_response(401, array('ok' => false, 'error' => 'Firma no válida'));
}

$expectedSignature = scraper_ingest_signature($timestamp, $nonce, $rawBody, $secret);
$providedSignature = preg_replace('/^sha256=/i', '', $providedSignature);
if (!is_string($providedSignature) || !hash_equals($expectedSignature, $providedSignature)) {
    scraper_ingest_json_response(401, array('ok' => false, 'error' => 'Firma no válida'));
}

$runtimeDir = trim((string)getenv('SCRAPER_INGEST_RUNTIME_DIR'));
if ($runtimeDir === '') {
    $runtimeDir = dirname(__DIR__) . '/data/scraper_ingest';
}
if (!scraper_ingest_register_nonce($runtimeDir, $nonce, (int)$timestamp, $maxSkew)) {
    scraper_ingest_json_response(401, array('ok' => false, 'error' => 'Nonce repetido'));
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    scraper_ingest_json_response(400, array('ok' => false, 'error' => 'JSON inválido'));
}

$eventId = trim((string)($payload['event_id'] ?? $payload['id'] ?? ''));
if ($eventId === '') {
    $eventId = hash('sha256', $rawBody);
}
$eventKey = strtolower(trim((string)($payload['type'] ?? ''))) . ':' . $eventId;

try {
    $result = scraper_ingest_store_payload(
        $payload,
        $runtimeDir,
        $eventKey,
        dirname(__DIR__) . '/data/comercial_queues'
    );
    scraper_ingest_json_response(!empty($result['duplicate']) ? 200 : 202, array('ok' => true) + $result);
} catch (InvalidArgumentException $e) {
    scraper_ingest_json_response(422, array('ok' => false, 'error' => $e->getMessage()));
} catch (Throwable $e) {
    @file_put_contents($runtimeDir . '/error.log', json_encode(array(
        'at' => date('c'),
        'error' => get_class($e),
    ), JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    scraper_ingest_json_response(500, array('ok' => false, 'error' => 'No se pudo procesar la ingestión'));
}
