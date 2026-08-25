<?php
/**
 * api/girls.php — CRUD de chicas para bot-casa multi-usuario.
 *
 * Almacena en data/users/{userId}/girls.json
 * Las fotos se guardan en el directorio compartido de girlsconf
 * y se sirven públicamente como URLs de compartir.site con
 * meta OG (Open Graph) para previsualización en WhatsApp.
 */
declare(strict_types=1);

// ── Directorio y URL base de compartir.site ──
const GIRLSCONF_IMGS_DIR  = '/var/www/html/wasapbot/landing/girlsconf/imgs';
const GIRLSCONF_BASE_URL  = 'https://compartir.site/';
const MAX_PHOTOS           = 4;
const PHOTO_MAX_BYTES      = 5 * 1024 * 1024; // 5 MB

define('WASAPBOT_ROOT', dirname(__DIR__, 2));
spl_autoload_register(function (string $class): void {
    $prefix = 'WasapBot\\'; $prefixLen = strlen($prefix);
    if (strncmp($prefix, $class, $prefixLen) !== 0) return;
    $file = WASAPBOT_ROOT . '/src/' . str_replace('\\', '/', substr($class, $prefixLen)) . '.php';
    if (file_exists($file)) require_once $file;
});
$isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
session_set_cookie_params(["lifetime"=>0,"path"=>"/","secure"=>$isHttps,"httponly"=>true,"samesite"=>"Lax"]);
$isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
session_set_cookie_params(["lifetime"=>0,"path"=>"/","secure"=>$isHttps,"httponly"=>true,"samesite"=>"Lax"]);
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? 'list');
$userId = (int) ($_SESSION['user_id'] ?? 0);
$isDemo = (($_SESSION['username'] ?? '') === 'demo');
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
    $now = time();
    for ($offset = 0; $offset <= 5; $offset++) {
        $t = $now - ($offset * 600);
        $expected = hash_hmac('sha256', $realUserId . '|' . date('Y-m-d-H', $t) . (int) floor((int) date('i', $t) / 10), $secret);
        if (hash_equals($expected, $token)) return;
    }
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'CSRF token invalid']);
    exit;
}

// ── Funciones auxiliares para compartir.site ──

function random_alnum_lower(int $len = 5): string {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $max   = strlen($chars) - 1;
    $out   = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

function ensure_dir(string $dir): void {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("No se pudo crear el directorio: {$dir}");
        }
    }
}

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $items = @scandir($dir);
    if (!$items) return;
    foreach ($items as $it) {
        if ($it === '.' || $it === '..') continue;
        $p = $dir . DIRECTORY_SEPARATOR . $it;
        if (is_dir($p)) rrmdir($p);
        else @unlink($p);
    }
    @rmdir($dir);
}

function next_img_folder(string $imgsDir): string {
    ensure_dir($imgsDir);
    for ($i = 0; $i < 200; $i++) {
        $name = random_alnum_lower(5);
        $path = $imgsDir . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($path)) return $name;
    }
    // Fallback ultra raro: aumentamos longitud
    for ($i = 0; $i < 200; $i++) {
        $name = random_alnum_lower(6);
        $path = $imgsDir . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($path)) return $name;
    }
    throw new RuntimeException('No se pudo generar un nombre de carpeta único para la imagen.');
}

function pick_keywords_short(string $desc, int $maxWords = 4): string {
    $s = mb_strtolower(trim($desc), 'UTF-8');
    $s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
    $parts = preg_split('/\s+/u', $s, -1, PREG_SPLIT_NO_EMPTY);
    if (!$parts) return '';
    $stop = [
        'de','la','el','los','las','y','o','u','a','en','con','sin','para','por','un','una','unos','unas',
        'muy','mas','más','que','del','al','se','te','tu','tus','su','sus','lo','es','soy','eres','ser',
        'mi','mis','yo','ella','él','si','sí','no','como','tiene','tengo','hacer','puede','puedo',
        'ademas','además','tambien','también','ven','venir','anímate','animate'
    ];
    $out = [];
    foreach ($parts as $w) {
        $w = trim($w);
        if ($w === '' || mb_strlen($w, 'UTF-8') < 3) continue;
        if (in_array($w, $stop, true)) continue;
        if (!in_array($w, $out, true)) $out[] = $w;
        if (count($out) >= $maxWords) break;
    }
    if (!$out) {
        $raw = preg_split('/\s+/u', trim($desc), -1, PREG_SPLIT_NO_EMPTY);
        $raw = array_slice($raw ?: [], 0, $maxWords);
        return trim(implode(' ', $raw));
    }
    $txt = implode(' ', $out);
    $txt = mb_strtoupper(mb_substr($txt, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($txt, 1, null, 'UTF-8');
    return $txt;
}

function build_og_index_php(string $girlName, string $descShortWords, string $publicFolderUrl, string $publicImageUrl, string $mime, int $w = 1200, int $h = 1200): string {
    if (substr($publicFolderUrl, -1) !== '/') $publicFolderUrl .= '/';
    $t    = htmlspecialchars($girlName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $d    = htmlspecialchars($descShortWords, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $folder = htmlspecialchars($publicFolderUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $img  = htmlspecialchars($publicImageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mime = htmlspecialchars($mime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return "<!doctype html>\n<html lang=\"es\">\n<head>\n  <meta charset=\"utf-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n\n  <!-- Open Graph (WhatsApp) -->\n  <meta property=\"og:type\" content=\"website\">\n  <meta property=\"og:title\" content=\"{$t}\">\n  <meta property=\"og:description\" content=\"{$d}\">\n  <meta property=\"og:image\" content=\"{$img}\">\n  <meta property=\"og:image:secure_url\" content=\"{$img}\">\n  <meta property=\"og:image:type\" content=\"{$mime}\">\n  <meta property=\"og:image:width\" content=\"{$w}\">\n  <meta property=\"og:image:height\" content=\"{$h}\">\n  <meta property=\"og:url\" content=\"{$folder}\">\n\n  <meta name=\"twitter:card\" content=\"summary_large_image\">\n  <meta name=\"twitter:image\" content=\"{$img}\">\n\n  <title>Foto</title>\n</head>\n<body>\n  <img src=\"{$img}\" alt=\"Foto\" style=\"max-width:100%;height:auto\">\n</body>\n</html>\n";
}

function ext_from_mime(string $mime): string {
    $map = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/webp' => 'webp',
    ];
    return $map[$mime] ?? 'jpg';
}

function is_uploaded_image(array $file): bool {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) return false;
    if (!isset($file['tmp_name']) || !is_file($file['tmp_name'])) return false;
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? (string)@finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) @finfo_close($finfo);
    return (strpos($mime, 'image/') === 0);
}

function folder_name_from_compartir_url(string $url): ?string {
    $url = trim($url);
    if ($url === '') return null;
    $u = parse_url($url);
    if (!is_array($u) || empty($u['path'])) return null;
    if (!preg_match('~/([a-z0-9]{5,32})/?$~', (string)$u['path'], $m)) return null;
    return $m[1];
}

function delete_compartir_folder(string $url): void {
    $folderName = folder_name_from_compartir_url($url);
    if (!$folderName) return;
    $folderPath = GIRLSCONF_IMGS_DIR . DIRECTORY_SEPARATOR . $folderName;
    if (!is_dir($folderPath)) return;
    // Seguridad: verificar que está dentro de GIRLSCONF_IMGS_DIR
    $realImgs  = realpath(GIRLSCONF_IMGS_DIR);
    $realFolder = realpath($folderPath);
    if ($realImgs === false || $realFolder === false) return;
    if (strpos($realFolder, $realImgs) !== 0) return;
    rrmdir($realFolder);
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
if ($method === 'POST') {
    if ($isDemo) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Modo demo: solo lectura']); exit; }
    requireValidCsrf();
}

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
            $hasActiva = isset($_POST['activa']); // track whether activa was explicitly sent

            if ($nombre === '') { echo json_encode(['ok'=>false,'error'=>'Nombre requerido']); break; }

            $data = loadGirls();
            $girls = &$data['girls'];

            if ($gid !== '') {
                // Update
                foreach ($girls as &$g) {
                    if (($g['id'] ?? '') === $gid) {
                        $g['nombre'] = $nombre;
                        $g['descripcion_corta'] = $desc;
                        if ($hasActiva) $g['activa'] = $activa;
                        if (!isset($g['fotos']) || !is_array($g['fotos'])) $g['fotos'] = [];
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

            // ── Process uploaded photos → compartir.site ──
            // Find the girl reference for adding photos
            $girlRef = null;
            foreach ($girls as &$gr) {
                if (($gr['id'] ?? '') === $gid) { $girlRef = &$gr; break; }
            }
            unset($gr);

            if ($girlRef !== null && !empty($_FILES['photos'])) {
                $existingCount = count($girlRef['fotos'] ?? []);
                $maxNew = max(0, MAX_PHOTOS - $existingCount);

                $files = $_FILES['photos'];
                // Normalize: if single file, wrap in array structure
                if (!is_array($files['name'])) {
                    $files = [
                        'name'     => [$files['name']],
                        'type'     => [$files['type']],
                        'tmp_name' => [$files['tmp_name']],
                        'error'    => [$files['error']],
                        'size'     => [$files['size']],
                    ];
                }

                $descWords = pick_keywords_short($desc, 4);
                ensure_dir(GIRLSCONF_IMGS_DIR);

                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                for ($i = 0; $i < count($files['name']) && $i < $maxNew; $i++) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                    if ($files['size'][$i] > PHOTO_MAX_BYTES) continue;

                    $mime = @finfo_file($finfo, $files['tmp_name'][$i]);
                    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) continue;

                    // Carpeta aleatoria de 5 chars en el directorio de girlsconf
                    $folderName  = next_img_folder(GIRLSCONF_IMGS_DIR);
                    $folderLocal = GIRLSCONF_IMGS_DIR . DIRECTORY_SEPARATOR . $folderName;
                    ensure_dir($folderLocal);

                    $ext         = ext_from_mime($mime);
                    $imgFileName = "{$folderName}.{$ext}";
                    $imgLocalPath = $folderLocal . DIRECTORY_SEPARATOR . $imgFileName;

                    if (!@move_uploaded_file($files['tmp_name'][$i], $imgLocalPath)) {
                        rrmdir($folderLocal);
                        continue;
                    }

                    // URLs públicas
                    $publicFolderUrl = GIRLSCONF_BASE_URL . rawurlencode($folderName) . '/';
                    $publicImageUrl  = GIRLSCONF_BASE_URL . rawurlencode($folderName) . '/' . rawurlencode($imgFileName);

                    // Generar index.php con meta OG (previsualización WhatsApp)
                    $indexContent = build_og_index_php($nombre, $descWords, $publicFolderUrl, $publicImageUrl, $mime);
                    $indexPath    = $folderLocal . DIRECTORY_SEPARATOR . 'index.php';
                    if (@file_put_contents($indexPath, $indexContent) === false) {
                        rrmdir($folderLocal);
                        continue;
                    }

                    // Guardar la URL pública de la carpeta (shortlink)
                    $girlRef['fotos'][] = $publicFolderUrl;
                }
                finfo_close($finfo);
            }

            saveGirls($data);
            echo json_encode(['ok' => true, 'id' => $gid]);
            break;

        case 'delete':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $gid = trim((string)($_POST['id'] ?? ''));
            if ($gid === '') { echo json_encode(['ok'=>false,'error'=>'ID requerido']); break; }

            $data = loadGirls();
            // Limpiar carpetas de compartir.site antes de borrar la chica
            foreach ($data['girls'] as $g) {
                if (($g['id'] ?? '') === $gid) {
                    foreach (($g['fotos'] ?? []) as $fu) {
                        delete_compartir_folder((string) $fu);
                    }
                    break;
                }
            }
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
                        // Limpiar la carpeta de compartir.site de la foto eliminada
                        delete_compartir_folder((string) $g['fotos'][$index]);
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
            if ($file['size'] > PHOTO_MAX_BYTES) { echo json_encode(['ok'=>false,'error'=>'Imagen demasiado grande (máx 5MB).']); break; }

            // Guardar en compartir.site
            $data = loadGirls();
            $girlName = '';
            $girlDesc = '';
            $girlRef = null;
            foreach ($data['girls'] as &$g) {
                if (($g['id'] ?? '') === $gid) {
                    $girlName = (string) ($g['nombre'] ?? '');
                    $girlDesc = (string) ($g['descripcion_corta'] ?? '');
                    if (!isset($g['fotos']) || !is_array($g['fotos'])) $g['fotos'] = [];
                    $girlRef = &$g;
                    break;
                }
            }
            unset($g);
            if ($girlRef === null) { echo json_encode(['ok'=>false,'error'=>'Chica no encontrada']); break; }
            if (count($girlRef['fotos']) >= MAX_PHOTOS) { echo json_encode(['ok'=>false,'error'=>'Máximo ' . MAX_PHOTOS . ' fotos alcanzado']); break; }

            ensure_dir(GIRLSCONF_IMGS_DIR);
            $folderName  = next_img_folder(GIRLSCONF_IMGS_DIR);
            $folderLocal = GIRLSCONF_IMGS_DIR . DIRECTORY_SEPARATOR . $folderName;
            ensure_dir($folderLocal);

            $ext         = ext_from_mime($mime);
            $imgFileName = "{$folderName}.{$ext}";
            $imgLocalPath = $folderLocal . DIRECTORY_SEPARATOR . $imgFileName;

            if (!move_uploaded_file($file['tmp_name'], $imgLocalPath)) {
                rrmdir($folderLocal);
                echo json_encode(['ok'=>false,'error'=>'Error al guardar la imagen.']); break;
            }

            $descWords       = pick_keywords_short($girlDesc, 4);
            $publicFolderUrl = GIRLSCONF_BASE_URL . rawurlencode($folderName) . '/';
            $publicImageUrl  = GIRLSCONF_BASE_URL . rawurlencode($folderName) . '/' . rawurlencode($imgFileName);

            $indexContent = build_og_index_php($girlName, $descWords, $publicFolderUrl, $publicImageUrl, $mime);
            $indexPath    = $folderLocal . DIRECTORY_SEPARATOR . 'index.php';
            if (@file_put_contents($indexPath, $indexContent) === false) {
                rrmdir($folderLocal);
                echo json_encode(['ok'=>false,'error'=>'Error al generar el index de la imagen.']); break;
            }

            $girlRef['fotos'][] = $publicFolderUrl;
            saveGirls($data);
            echo json_encode(['ok' => true, 'url' => $publicFolderUrl]);
            break;

        case 'reorder_photos':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $gid = trim((string)($_POST['id'] ?? ''));
            $orderJson = trim((string)($_POST['order'] ?? ''));
            if ($gid === '' || $orderJson === '') { echo json_encode(['ok'=>false,'error'=>'Parámetros requeridos (id, order)']); break; }

            $newOrder = json_decode($orderJson, true);
            if (!is_array($newOrder)) { echo json_encode(['ok'=>false,'error'=>'Orden inválido: no es un array JSON']); break; }

            $data = loadGirls();
            $found = false;
            foreach ($data['girls'] as &$g) {
                if (($g['id'] ?? '') === $gid) {
                    $currentFotos = $g['fotos'] ?? [];
                    if (count($newOrder) !== count($currentFotos)) {
                        echo json_encode(['ok'=>false,'error'=>'El número de fotos no coincide con el actual']); break 2;
                    }
                    $currentSet = [];
                    foreach ($currentFotos as $url) { $currentSet[$url] = true; }
                    foreach ($newOrder as $url) {
                        if (!isset($currentSet[$url])) {
                            echo json_encode(['ok'=>false,'error'=>'El orden contiene URLs que no pertenecen a esta chica']); break 3;
                        }
                    }
                    $g['fotos'] = $newOrder;
                    $found = true;
                    break;
                }
            }
            unset($g);
            if (!$found) { echo json_encode(['ok'=>false,'error'=>'Chica no encontrada']); break; }
            saveGirls($data);
            echo json_encode(['ok' => true]);
            break;

        case 'get_catalog':
            // Return active girls with photos for the image attachment picker.
            // Admin panel (no suplantar) → remote GirlsService (girls_json URL).
            // Client panel or admin suplantando → local girls.json.
            $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
            $isSuplantando = $isAdmin && !empty($_SESSION['suplantar_user_id']);

            if ($isAdmin && !$isSuplantando) {
                // Admin → fetch from remote GirlsService
                $cfg   = new \WasapBot\Core\Config(WASAPBOT_ROOT);
                $logger = new \WasapBot\Core\Logger();
                $http  = new \WasapBot\Core\HttpClient($logger);
                $gs    = new \WasapBot\Services\GirlsService($cfg, $http, $logger);
                $allGirls = $gs->fetchActive();
            } else {
                // Client → local girls.json
                $data = loadGirls();
                $allGirls = array_values(array_filter(
                    $data['girls'] ?? [],
                    fn($g) => ($g['activa'] ?? false)
                ));
            }

            // Return only id, nombre, fotos (only girls with at least 1 photo)
            $catalog = [];
            foreach ($allGirls as $g) {
                $fotos = $g['fotos'] ?? [];
                if (!is_array($fotos) || count($fotos) === 0) continue;
                $catalog[] = [
                    'id'     => $g['id'] ?? '',
                    'nombre' => $g['nombre'] ?? '',
                    'fotos'  => array_values($fotos),
                ];
            }
            echo json_encode(['ok' => true, 'girls' => $catalog]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (\Throwable $e) {
    error_log('girls.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
