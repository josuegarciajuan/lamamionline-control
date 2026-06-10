<?php
/**
 * api/image-proxy.php — Sirve imágenes del catálogo de chicas desde data/users/{uid}/imgs/
 *
 * Uso: /api/image-proxy.php?uid=4&img=ovmb9/ovmb9.jpg
 *
 * Seguridad:
 *   - Requiere autenticación (sesión o token)
 *   - Solo sirve archivos de imagen (JPEG, PNG, WebP)
 *   - Protección contra path traversal
 *   - El usuario solo puede ver sus propias imágenes (o admin suplantando)
 */
declare(strict_types=1);

define('WASAPBOT_ROOT', dirname(__DIR__, 2));

$isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
session_set_cookie_params(["lifetime"=>0,"path"=>"/","secure"=>$isHttps,"httponly"=>true,"samesite"=>"Lax"]);
session_start();

// ── Auth check ──
$isAuth = !empty($_SESSION['user_id']);

// Also accept token-based auth (for WhatsApp webhook / public access)
if (!$isAuth && !empty($_GET['token'])) {
    $secretFile = WASAPBOT_ROOT . '/data/.csrf_secret';
    $secret = '';
    if (file_exists($secretFile)) {
        $secret = trim((string) @file_get_contents($secretFile));
    }
    if (strlen($secret) >= 32) {
        // Try current and previous time slots
        $now = time();
        $slots = [floor($now / 600), floor($now / 600) - 1];
        $uidFromToken = null;
        foreach ($slots as $slot) {
            for ($uid = 1; $uid <= 100; $uid++) {
                $test = hash_hmac('sha256', $uid . '|' . date('Y-m-d-H', $now) . floor((int) date('i', $now) / 10), $secret);
                if (hash_equals($test, (string) $_GET['token'])) {
                    $uidFromToken = $uid;
                    break 2;
                }
                $test = hash_hmac('sha256', $uid . '|' . date('Y-m-d-H', $now) . max(0, floor((int) date('i', $now) / 10) - 1), $secret);
                if (hash_equals($test, (string) $_GET['token'])) {
                    $uidFromToken = $uid;
                    break 2;
                }
            }
        }
        if ($uidFromToken !== null) {
            $_SESSION['user_id'] = $uidFromToken;
            $isAuth = true;
        }
    }
}

if (!$isAuth) {
    http_response_code(401);
    header('Content-Type: text/plain');
    echo 'Unauthorized';
    exit;
}

$uid = (int) ($_GET['uid'] ?? 0);
$img = trim((string) ($_GET['img'] ?? ''));

if ($uid <= 0 || $img === '') {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo 'Bad request: missing uid or img';
    exit;
}

// ── Path traversal protection ──
// Only allow safe characters: a-z, 0-9, /, .
if (preg_match('/[^a-z0-9\/\.\-]/i', $img)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Forbidden: invalid characters in img path';
    exit;
}
// Block directory traversal patterns
if (str_contains($img, '..') || str_starts_with($img, '/') || str_contains($img, '\\')) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Forbidden: path traversal detected';
    exit;
}

// ── Access control: user can only access their own images ──
$sessionUid = (int) ($_SESSION['user_id'] ?? 0);
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// Admin can suplantar
if ($isAdmin && !empty($_SESSION['suplantar_user_id'])) {
    $sessionUid = (int) $_SESSION['suplantar_user_id'];
}

// Allow token-authenticated users to access images for their uid
if ($sessionUid !== $uid && !$isAdmin) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Forbidden: access denied';
    exit;
}

// ── Build file path ──
$filePath = WASAPBOT_ROOT . '/data/users/' . $uid . '/imgs/' . $img;

// Realpath resolution to prevent traversal via symlinks
$realPath = realpath($filePath);
if ($realPath === false) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Not found';
    exit;
}

// Verify the resolved path is within the allowed data directory
$allowedBase = realpath(WASAPBOT_ROOT . '/data/users') ?: WASAPBOT_ROOT . '/data/users';
if (!str_starts_with($realPath, $allowedBase . '/')) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Forbidden';
    exit;
}

// ── Determine MIME type ──
$ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
$mimeMap = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];

if (!isset($mimeMap[$ext])) {
    http_response_code(415);
    header('Content-Type: text/plain');
    echo 'Unsupported image format';
    exit;
}

// ── Serve the image ──
$fileSize = filesize($realPath);
if ($fileSize === false || $fileSize === 0) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Empty file';
    exit;
}

// Cache headers (1 day for static images)
$cacheSeconds = 86400;
header('Content-Type: ' . $mimeMap[$ext]);
header('Content-Length: ' . $fileSize);
header('Cache-Control: public, max-age=' . $cacheSeconds);
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheSeconds) . ' GMT');
header('Pragma: public');

// Stream the file efficiently
$fp = fopen($realPath, 'rb');
if ($fp === false) {
    http_response_code(500);
    exit;
}
fpassthru($fp);
fclose($fp);
exit;
