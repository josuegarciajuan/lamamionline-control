<?php

declare(strict_types=1);

/**
 * Endpoint externo para crear avisos en el panel lamami.online/control y
 * enviarlos por WhatsApp. Replica el patrón HMAC-SHA256 de scraper_ingest.
 */

require_once dirname(__DIR__) . '/app/avisos_ingest.php';

function avisos_ingest_json_response(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function avisos_ingest_header(string $name): string
{
    $serverName = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return trim((string)($_SERVER[$serverName] ?? ''));
}

$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
if (!$isHttps && PHP_SAPI !== 'cli') {
    avisos_ingest_json_response(400, array('ok' => false, 'error' => 'HTTPS required'));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
    header('Allow: POST');
    avisos_ingest_json_response(405, array('ok' => false, 'error' => 'POST required'));
}

$localConfig = avisos_ingest_load_local_config(dirname(__DIR__) . '/data/avisos_ingest_config.php');

$envSecret = getenv('AVISOS_INGEST_HMAC_SECRET');
$secret = trim((string)(is_string($envSecret) && trim($envSecret) !== '' ? $envSecret : ($localConfig['secret'] ?? '')));
if ($secret === '') {
    avisos_ingest_json_response(503, array('ok' => false, 'error' => 'Ingestión no configurada'));
}

$rawBody = PHP_SAPI === 'cli'
    ? (string)stream_get_contents(STDIN)
    : (string)file_get_contents('php://input');
if ($rawBody === '' || strlen($rawBody) > 1048576) {
    avisos_ingest_json_response(413, array('ok' => false, 'error' => 'Cuerpo inválido o demasiado grande'));
}

$timestamp = avisos_ingest_header('X-Avisos-Timestamp');
$nonce = avisos_ingest_header('X-Avisos-Nonce');
$providedSignature = avisos_ingest_header('X-Avisos-Signature');
$envMaxSkew = getenv('AVISOS_INGEST_MAX_SKEW');
$configuredMaxSkew = is_string($envMaxSkew) && trim($envMaxSkew) !== ''
    ? $envMaxSkew : ($localConfig['max_skew'] ?? 300);
$maxSkew = max(30, (int)$configuredMaxSkew);

if (!ctype_digit($timestamp) || $nonce === '' || $providedSignature === ''
    || abs(time() - (int)$timestamp) > $maxSkew) {
    avisos_ingest_json_response(401, array('ok' => false, 'error' => 'Firma no válida'));
}

$expectedSignature = avisos_ingest_signature($timestamp, $nonce, $rawBody, $secret);
$providedSignature = preg_replace('/^sha256=/i', '', $providedSignature);
if (!is_string($providedSignature) || !hash_equals($expectedSignature, $providedSignature)) {
    avisos_ingest_json_response(401, array('ok' => false, 'error' => 'Firma no válida'));
}

$runtimeDir = trim((string)getenv('AVISOS_INGEST_RUNTIME_DIR'));
if ($runtimeDir === '') {
    $runtimeDir = dirname(__DIR__) . '/data/avisos_ingest';
}
if (!avisos_ingest_register_nonce($runtimeDir, $nonce, (int)$timestamp, $maxSkew)) {
    avisos_ingest_json_response(401, array('ok' => false, 'error' => 'Nonce repetido'));
}

$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    avisos_ingest_json_response(400, array('ok' => false, 'error' => 'JSON inválido'));
}

try {
    $aviso = avisos_ingest_validate_payload($payload);
} catch (InvalidArgumentException $e) {
    avisos_ingest_json_response(400, array('ok' => false, 'error' => $e->getMessage()));
}

// Modo test: valida el pipeline completo HMAC/nonce/JSON sin crear aviso
// ni enviar WhatsApp. Para smoke tests, nunca activo en producción.
$testMode = strtolower(trim((string)getenv('AVISOS_INGEST_TEST_MODE')));
if (in_array($testMode, array('1', 'true', 'yes', 'on'), true)) {
    avisos_ingest_json_response(202, array(
        'ok' => true,
        'aviso_id' => '',
        'test_mode' => true,
        'severity' => $aviso['severity'],
        'engine' => $aviso['engine'],
    ));
}

try {
    require_once dirname(__DIR__) . '/app/bootstrap.php';
    $avisoId = avisos_create_active(
        $aviso['title'],
        $aviso['message'],
        $aviso['severity'],
        $aviso['engine'],
        $aviso['meta'],
        true,
        $aviso['source_key']
    );
} catch (Throwable $e) {
    avisos_ingest_json_response(500, array('ok' => false, 'error' => 'No se pudo crear el aviso'));
}

if ($avisoId === false) {
    avisos_ingest_json_response(500, array('ok' => false, 'error' => 'No se pudo crear el aviso'));
}

avisos_ingest_json_response(202, array('ok' => true, 'aviso_id' => $avisoId));
