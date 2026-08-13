<?php
/**
 * compartir_media.php — Helpers compartidos para el sistema público de imágenes
 * bajo el dominio https://compartir.site (el mismo que usa bot-casa para las chicas).
 *
 * Cada imagen vive en una carpeta aleatoria de 5 caracteres dentro de
 * GIRLSCONF_IMGS_DIR, con un index.php con meta OG (Open Graph) para que
 * WhatsApp genere la previsualización al enviar el enlace como texto.
 *
 * - Shortlink (se envía por WhatsApp): https://compartir.site/{codigo}/
 * - Imagen directa (para <img> en el navegador): https://compartir.site/{codigo}/{codigo}.jpg
 */

declare(strict_types=1);

if (!defined('GIRLSCONF_IMGS_DIR')) {
    define('GIRLSCONF_IMGS_DIR', '/var/www/html/wasapbot/landing/girlsconf/imgs');
}
if (!defined('GIRLSCONF_BASE_URL')) {
    define('GIRLSCONF_BASE_URL', 'https://compartir.site/');
}
const COMPARTIR_PHOTO_MAX_BYTES = 5 * 1024 * 1024; // 5 MB
const COMPARTIR_ALLOWED_MIMES = array('image/jpeg', 'image/png', 'image/webp');

function compartir_random_alnum_lower(int $len = 5): string {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $max = strlen($chars) - 1;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

function compartir_ensure_dir(string $dir): void {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException("No se pudo crear el directorio: {$dir}");
        }
    }
}

function compartir_rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $items = @scandir($dir);
    if (!$items) return;
    foreach ($items as $it) {
        if ($it === '.' || $it === '..') continue;
        $p = $dir . DIRECTORY_SEPARATOR . $it;
        if (is_dir($p)) compartir_rrmdir($p);
        else @unlink($p);
    }
    @rmdir($dir);
}

function compartir_next_img_folder(string $imgsDir): string {
    compartir_ensure_dir($imgsDir);
    for ($i = 0; $i < 200; $i++) {
        $name = compartir_random_alnum_lower(5);
        $path = $imgsDir . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($path)) return $name;
    }
    for ($i = 0; $i < 200; $i++) {
        $name = compartir_random_alnum_lower(6);
        $path = $imgsDir . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($path)) return $name;
    }
    throw new RuntimeException('No se pudo generar un nombre de carpeta único para la imagen.');
}

function compartir_ext_from_mime(string $mime): string {
    $map = array(
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/webp' => 'webp',
    );
    return $map[$mime] ?? 'jpg';
}

function compartir_build_og_index_php(string $title, string $desc, string $publicFolderUrl, string $publicImageUrl, string $mime, int $w = 1200, int $h = 1200): string {
    if (substr($publicFolderUrl, -1) !== '/') $publicFolderUrl .= '/';
    $t = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $d = htmlspecialchars($desc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $folder = htmlspecialchars($publicFolderUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $img = htmlspecialchars($publicImageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mime = htmlspecialchars($mime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return "<!doctype html>\n<html lang=\"es\">\n<head>\n"
        . "  <meta charset=\"utf-8\">\n"
        . "  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n\n"
        . "  <!-- Open Graph (WhatsApp) -->\n"
        . "  <meta property=\"og:type\" content=\"website\">\n"
        . "  <meta property=\"og:title\" content=\"{$t}\">\n"
        . "  <meta property=\"og:description\" content=\"{$d}\">\n"
        . "  <meta property=\"og:image\" content=\"{$img}\">\n"
        . "  <meta property=\"og:image:secure_url\" content=\"{$img}\">\n"
        . "  <meta property=\"og:image:type\" content=\"{$mime}\">\n"
        . "  <meta property=\"og:image:width\" content=\"{$w}\">\n"
        . "  <meta property=\"og:image:height\" content=\"{$h}\">\n"
        . "  <meta property=\"og:url\" content=\"{$folder}\">\n\n"
        . "  <meta name=\"twitter:card\" content=\"summary_large_image\">\n"
        . "  <meta name=\"twitter:image\" content=\"{$img}\">\n\n"
        . "  <title>Foto</title>\n</head>\n<body>\n"
        . "  <img src=\"{$img}\" alt=\"Foto\" style=\"max-width:100%;height:auto\">\n"
        . "</body>\n</html>\n";
}

/**
 * Extrae el código de carpeta de un shortlink de compartir.site.
 * Ej: https://compartir.site/ab12c/ → "ab12c". Devuelve null si no matchea.
 */
function compartir_folder_name_from_url(string $url): ?string {
    $url = trim($url);
    if ($url === '') return null;
    $u = parse_url($url);
    if (!is_array($u) || empty($u['path'])) return null;
    if (!preg_match('~/([a-z0-9]{5,32})/?$~', (string)$u['path'], $m)) return null;
    return $m[1];
}

/**
 * Elimina de disco la carpeta de compartir.site correspondiente a un shortlink.
 */
function compartir_delete_folder_by_url(string $url): void {
    $folderName = compartir_folder_name_from_url($url);
    if (!$folderName) return;
    $folderPath = GIRLSCONF_IMGS_DIR . DIRECTORY_SEPARATOR . $folderName;
    if (!is_dir($folderPath)) return;
    // Seguridad: verificar que la carpeta está dentro de GIRLSCONF_IMGS_DIR
    $realImgs = realpath(GIRLSCONF_IMGS_DIR);
    $realFolder = realpath($folderPath);
    if ($realImgs === false || $realFolder === false) return;
    if (strpos($realFolder, $realImgs) !== 0) return;
    compartir_rrmdir($realFolder);
}

/**
 * Guarda una imagen en compartir.site a partir de una ruta temporal de subida.
 *
 * @param string $tmpPath Ruta del archivo temporal (subida PHP o archivo local).
 * @param string $mime    MIME ya validado (image/jpeg, image/png, image/webp).
 * @param string $title   Título para el meta OG.
 * @param string $desc    Descripción corta para el meta OG.
 * @return array ['ok' => true, 'url' => shortlink, 'img' => imagen directa] | ['ok' => false, 'error' => ...]
 */
function compartir_store_image(string $tmpPath, string $mime, string $title = 'Habitación', string $desc = 'Habitación disponible'): array {
    compartir_ensure_dir(GIRLSCONF_IMGS_DIR);

    $folderName  = compartir_next_img_folder(GIRLSCONF_IMGS_DIR);
    $folderLocal = GIRLSCONF_IMGS_DIR . DIRECTORY_SEPARATOR . $folderName;
    compartir_ensure_dir($folderLocal);

    $ext         = compartir_ext_from_mime($mime);
    $imgFileName = "{$folderName}.{$ext}";
    $imgLocalPath = $folderLocal . DIRECTORY_SEPARATOR . $imgFileName;

    if (!@move_uploaded_file($tmpPath, $imgLocalPath) && !@copy($tmpPath, $imgLocalPath)) {
        compartir_rrmdir($folderLocal);
        return array('ok' => false, 'error' => 'No se pudo guardar la imagen en el servidor.');
    }

    $publicFolderUrl = GIRLSCONF_BASE_URL . rawurlencode($folderName) . '/';
    $publicImageUrl  = GIRLSCONF_BASE_URL . rawurlencode($folderName) . '/' . rawurlencode($imgFileName);

    $indexContent = compartir_build_og_index_php($title, $desc, $publicFolderUrl, $publicImageUrl, $mime);
    $indexPath    = $folderLocal . DIRECTORY_SEPARATOR . 'index.php';
    if (@file_put_contents($indexPath, $indexContent) === false) {
        compartir_rrmdir($folderLocal);
        return array('ok' => false, 'error' => 'No se pudo generar el index de la imagen.');
    }

    return array('ok' => true, 'url' => $publicFolderUrl, 'img' => $publicImageUrl);
}
