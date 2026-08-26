<?php
/**
 * media_proxy.php — Sirve media (imágenes/audio/vídeo) recibida por Evolution al navegador.
 *
 * Evolution (con S3/MinIO) guarda la media descifrada en un bucket y el mensaje
 * referencia la URL de MinIO (p.ej. http://minio:9000/evolution/<ruta>). El
 * navegador del usuario no alcanza MinIO directamente, así que este endpoint:
 *  1) valida que la URL apunte a nuestro MinIO (anti-SSRF),
 *  2) reescribe el host interno (minio) por la IP alcanzable,
 *  3) descarga y devuelve los bytes con el Content-Type correcto.
 *
 * Uso: /control/media_proxy.php?url=<url_encoded>&type=image|audio|video
 */

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

// El navegador manda la cookie de sesión; exigimos sesión iniciada.
if (!is_logged_in()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$url = (string) ($_GET['url'] ?? '');
if ($url === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'url required']);
    exit;
}

$type = strtolower(trim((string) ($_GET['type'] ?? '')));
$allowHosts = ['minio:9000', '127.0.0.1:9000', '100.117.92.74:9000', 'localhost:9000'];
$allowedType = in_array($type, ['image', 'audio', 'video', 'document'], true) ? $type : '';

// ── Validar URL (anti-SSRF): solo nuestro MinIO ──
$parsed = parse_url($url);
$host = strtolower((string) ($parsed['host'] ?? ''));
$port = (string) ($parsed['port'] ?? '');
$hostPort = $port !== '' ? $host . ':' . $port : $host;
if (!in_array($hostPort, $allowHosts, true) || !isset($parsed['path'])) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'invalid host']);
    exit;
}

// Reescritura: host interno minio:9000 → IP alcanzable de oficina
$fetchUrl = 'http://100.117.92.74:9000' . $parsed['path'];
if (!empty($parsed['query'])) {
    $fetchUrl .= '?' . $parsed['query'];
}

$ch = curl_init($fetchUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_MAXREDIRS => 3,
]);
$bytes = curl_exec($ch);
$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($bytes === false || $http !== 200) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'fetch failed', 'http' => $http, 'curl' => $err]);
    exit;
}

// Content-Type según tipo
$mime = 'application/octet-stream';
if ($allowedType === 'image') $mime = 'image/jpeg';
elseif ($allowedType === 'audio') $mime = 'audio/ogg';
elseif ($allowedType === 'video') $mime = 'video/mp4';

header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
