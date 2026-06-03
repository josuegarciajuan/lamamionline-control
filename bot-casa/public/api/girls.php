<?php
/**
 * api/girls.php — CRUD de chicas para bot-casa multi-usuario.
 *
 * Almacena en data/users/{userId}/girls.json
 * Las fotos se almacenan como URLs (compartir.site).
 */
declare(strict_types=1);

define('WASAPBOT_ROOT', dirname(__DIR__, 2));
$isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
session_set_cookie_params(["lifetime"=>0,"path"=>"/","secure"=>$isHttps,"httponly"=>true,"samesite"=>"Lax"]);
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? 'list');
$userId = (int) ($_SESSION['user_id'] ?? 0);
if (($_SESSION['role']??'') === 'admin' && !empty($_SESSION['suplantar_user_id'])) {
    $userId = (int) $_SESSION['suplantar_user_id'];
}

// ── CSRF protection for POST requests ──
function requireValidCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'CSRF token required']); exit; }
    $secretFile = WASAPBOT_ROOT . '/data/.csrf_secret';
    $secret = '';
    if (file_exists($secretFile)) {
        $secret = trim((string) @file_get_contents($secretFile));
    }
    if (strlen($secret) < 32) {
        $secret = bin2hex(random_bytes(32));
        $dir = dirname($secretFile);
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        @file_put_contents($secretFile, $secret, LOCK_EX);
        @chmod($secretFile, 0600);
    }
    $realUserId = (int) ($_SESSION['user_id'] ?? 0);
    $current = hash_hmac('sha256', $realUserId . '|' . date('Y-m-d-H') . floor((int) date('i') / 10), $secret);
    if (hash_equals($current, $token)) return;
    $prevSlot = max(0, floor((int) date('i') / 10) - 1);
    $previous = hash_hmac('sha256', $realUserId . '|' . date('Y-m-d-H') . $prevSlot, $secret);
    if (hash_equals($previous, $token)) return;
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'CSRF token invalid']);
    exit;
}

$girlsFile = WASAPBOT_ROOT . '/data/users/' . $userId . '/girls.json';

function loadGirls(): array {
    global $girlsFile;
    if (!file_exists($girlsFile)) return ['girls' => []];
    $data = @json_decode((string)@file_get_contents($girlsFile), true);
    return is_array($data) ? $data : ['girls' => []];
}
function saveGirls(array $data): void {
    global $girlsFile;
    $dir = dirname($girlsFile);
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    @file_put_contents($girlsFile, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);
}

header('Content-Type: application/json; charset=utf-8');

// Validate CSRF for all POST requests
if ($method === 'POST') requireValidCsrf();

try {
    switch ($action) {
        case 'list':
            $data = loadGirls();
            echo json_encode(['ok' => true, 'girls' => $data['girls'] ?? []]);
            break;

        case 'save':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }

            $gid    = trim((string)($_POST['id'] ?? ''));
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $desc   = trim((string)($_POST['descripcion'] ?? ''));
            $activa = isset($_POST['activa']) ? (bool)$_POST['activa'] : true;

            if ($nombre === '') { echo json_encode(['ok'=>false,'error'=>'Nombre requerido']); break; }

            $data = loadGirls();
            $girls = &$data['girls'];

            if ($gid !== '') {
                // Update
                foreach ($girls as &$g) {
                    if (($g['id'] ?? '') === $gid) {
                        $g['nombre'] = $nombre;
                        $g['descripcion_corta'] = $desc;
                        $g['activa'] = $activa;
                        break;
                    }
                }
                unset($g);
            } else {
                // Create
                $gid = substr(bin2hex(random_bytes(3)), 0, 5);
                $girls[] = [
                    'id' => $gid,
                    'nombre' => $nombre,
                    'descripcion_corta' => $desc,
                    'fotos' => [],
                    'activa' => $activa,
                ];
            }

            saveGirls($data);
            echo json_encode(['ok' => true, 'id' => $gid]);
            break;

        case 'delete':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $gid = trim((string)($_POST['id'] ?? ''));
            if ($gid === '') { echo json_encode(['ok'=>false,'error'=>'ID requerido']); break; }

            $data = loadGirls();
            $data['girls'] = array_values(array_filter($data['girls'], fn($g) => ($g['id']??'') !== $gid));
            saveGirls($data);
            echo json_encode(['ok' => true]);
            break;

        case 'add_photo':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $gid = trim((string)($_POST['id'] ?? ''));
            $url = trim((string)($_POST['photo_url'] ?? ''));
            if ($gid === '' || $url === '') { echo json_encode(['ok'=>false,'error'=>'ID y URL requeridos']); break; }

            $data = loadGirls();
            foreach ($data['girls'] as &$g) {
                if (($g['id'] ?? '') === $gid) {
                    if (!isset($g['fotos']) || !is_array($g['fotos'])) $g['fotos'] = [];
                    $g['fotos'][] = $url;
                    break;
                }
            }
            unset($g);
            saveGirls($data);
            echo json_encode(['ok' => true]);
            break;

        case 'remove_photo':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $gid   = trim((string)($_POST['id'] ?? ''));
            $index = (int) ($_POST['photo_index'] ?? -1);
            if ($gid === '' || $index < 0) { echo json_encode(['ok'=>false,'error'=>'Parámetros requeridos']); break; }

            $data = loadGirls();
            foreach ($data['girls'] as &$g) {
                if (($g['id'] ?? '') === $gid) {
                    if (isset($g['fotos'][$index])) {
                        array_splice($g['fotos'], $index, 1);
                    }
                    break;
                }
            }
            unset($g);
            saveGirls($data);
            echo json_encode(['ok' => true]);
            break;

        case 'toggle':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $gid = trim((string)($_POST['id'] ?? ''));
            if ($gid === '') { echo json_encode(['ok'=>false,'error'=>'ID requerido']); break; }

            $data = loadGirls();
            foreach ($data['girls'] as &$g) {
                if (($g['id'] ?? '') === $gid) {
                    $g['activa'] = !($g['activa'] ?? true);
                    break;
                }
            }
            unset($g);
            saveGirls($data);
            echo json_encode(['ok' => true]);
            break;

        case 'upload_photo':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $gid = trim((string)($_POST['id'] ?? ''));
            if ($gid === '') { echo json_encode(['ok'=>false,'error'=>'ID requerido']); break; }
            if (empty($_FILES['photo'])) { echo json_encode(['ok'=>false,'error'=>'No se recibió archivo']); break; }

            $file = $_FILES['photo'];
            $allowed = ['image/jpeg', 'image/png', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowed, true)) { echo json_encode(['ok'=>false,'error'=>'Formato no permitido. Usa JPG, PNG o WebP.']); break; }
            if ($file['size'] > 5 * 1024 * 1024) { echo json_encode(['ok'=>false,'error'=>'Imagen demasiado grande (máx 5MB).']); break; }

            // Create random folder name (like girlsconf)
            $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
            $folder = '';
            for ($i = 0; $i < 5; $i++) { $folder .= $chars[random_int(0, strlen($chars)-1)]; }
            $imgDir = WASAPBOT_ROOT . '/data/users/' . $userId . '/imgs/' . $folder;
            if (!is_dir($imgDir)) @mkdir($imgDir, 0755, true);

            $ext = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');
            $destPath = $imgDir . '/' . $folder . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                echo json_encode(['ok'=>false,'error'=>'Error al guardar la imagen.']); break;
            }

            // The image is served from the same domain via a public symlink or direct path
            // Store relative URL: /control/bot-casa/data/users/{userId}/imgs/{folder}/{folder}.{ext}
            $photoUrl = '/control/bot-casa/data/users/' . $userId . '/imgs/' . $folder . '/' . $folder . '.' . $ext;

            $data = loadGirls();
            foreach ($data['girls'] as &$g) {
                if (($g['id'] ?? '') === $gid) {
                    if (!isset($g['fotos']) || !is_array($g['fotos'])) $g['fotos'] = [];
                    $g['fotos'][] = $photoUrl;
                    break;
                }
            }
            unset($g);
            saveGirls($data);
            echo json_encode(['ok' => true, 'url' => $photoUrl]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (\Throwable $e) {
    error_log('girls.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
