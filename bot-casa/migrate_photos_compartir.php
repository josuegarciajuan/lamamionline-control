#!/usr/bin/env php
<?php
/**
 * migrate_photos_compartir.php
 *
 * Migra las fotos de chicas existentes (almacenadas vía image-proxy.php)
 * al sistema público de compartir.site, generando el index.php con
 * meta OG (Open Graph) para previsualización en WhatsApp.
 *
 * Idempotente: solo migra URLs que empiezan por /api/image-proxy.php.
 *
 * Uso: php migrate_photos_compartir.php [--dry-run]
 */

declare(strict_types=1);

const GIRLSCONF_IMGS_DIR = '/var/www/html/wasapbot/landing/girlsconf/imgs';
const GIRLSCONF_BASE_URL = 'https://compartir.site/';
const DATA_USERS_DIR    = __DIR__ . '/data/users';

// ── Helpers (copiados de api/girls.php) ──

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
    for ($i = 0; $i < 200; $i++) {
        $name = random_alnum_lower(6);
        $path = $imgsDir . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($path)) return $name;
    }
    throw new RuntimeException('No se pudo generar un nombre de carpeta único.');
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

function ext_from_mime(string $mime): string {
    $map = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/webp' => 'webp',
    ];
    return $map[$mime] ?? 'jpg';
}

function build_og_index_php(string $girlName, string $descWords, string $folderUrl, string $imgUrl, string $mime): string {
    if (substr($folderUrl, -1) !== '/') $folderUrl .= '/';
    $t      = htmlspecialchars($girlName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $d      = htmlspecialchars($descWords, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $folder = htmlspecialchars($folderUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $img    = htmlspecialchars($imgUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mime   = htmlspecialchars($mime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
         . "  <meta property=\"og:image:width\" content=\"1200\">\n"
         . "  <meta property=\"og:image:height\" content=\"1200\">\n"
         . "  <meta property=\"og:url\" content=\"{$folder}\">\n\n"
         . "  <meta name=\"twitter:card\" content=\"summary_large_image\">\n"
         . "  <meta name=\"twitter:image\" content=\"{$img}\">\n\n"
         . "  <title>Foto</title>\n</head>\n<body>\n"
         . "  <img src=\"{$img}\" alt=\"Foto\" style=\"max-width:100%;height:auto\">\n"
         . "</body>\n</html>\n";
}

// ── Main ──

$dryRun = in_array('--dry-run', $argv, true);

echo "=== Migración de fotos a compartir.site ===\n";
echo "Directorio destino: " . GIRLSCONF_IMGS_DIR . "\n";
echo "URL base:            " . GIRLSCONF_BASE_URL . "\n";
echo "Modo:                " . ($dryRun ? "DRY-RUN (sin cambios)" : "LIVE") . "\n\n";

if (!is_dir(DATA_USERS_DIR)) {
    die("ERROR: No se encuentra el directorio de usuarios: " . DATA_USERS_DIR . "\n");
}

ensure_dir(GIRLSCONF_IMGS_DIR);

$migrated  = 0;
$total     = 0;
$skipped   = 0;
$errors    = 0;

foreach (new DirectoryIterator(DATA_USERS_DIR) as $userDir) {
    if ($userDir->isDot() || !$userDir->isDir()) continue;
    $uid = $userDir->getFilename();
    if (!ctype_digit($uid)) continue;

    $girlsFile = $userDir->getPathname() . '/girls.json';
    if (!file_exists($girlsFile)) continue;
    if (!is_readable($girlsFile)) {
        echo "WARN: No se puede leer $girlsFile\n";
        continue;
    }

    $raw = @file_get_contents($girlsFile);
    if ($raw === false) continue;
    $data = @json_decode($raw, true);
    if (!is_array($data) || !isset($data['girls'])) continue;

    $changed = false;

    foreach ($data['girls'] as $girlIdx => &$girl) {
        if (!is_array($girl)) continue;
        $girlName = (string) ($girl['nombre'] ?? 'Sin nombre');
        $girlDesc = (string) ($girl['descripcion_corta'] ?? '');
        $descWords = pick_keywords_short($girlDesc, 4);

        if (!isset($girl['fotos']) || !is_array($girl['fotos'])) continue;

        foreach ($girl['fotos'] as $fotoIdx => &$fotoUrl) {
            $fotoUrl = (string) $fotoUrl;
            // Solo migrar URLs de image-proxy.php
            if (!str_starts_with($fotoUrl, '/api/image-proxy.php')) continue;

            $total++;
            echo "  [UID=$uid] {$girlName} foto #$fotoIdx: $fotoUrl\n";

            // Parsear URL: /api/image-proxy.php?uid=X&img=folder/file.ext&...
            $query = parse_url($fotoUrl, PHP_URL_QUERY);
            if (!$query) {
                echo "    SKIP: No se pudo parsear la URL\n";
                $skipped++;
                continue;
            }
            parse_str($query, $params);
            $imgPath = $params['img'] ?? '';
            if ($imgPath === '') {
                echo "    SKIP: Parámetro 'img' no encontrado\n";
                $skipped++;
                continue;
            }

            // img = "folder/filename"  → separar
            $slashPos = strpos($imgPath, '/');
            if ($slashPos === false) {
                echo "    SKIP: Formato de img inesperado: $imgPath\n";
                $skipped++;
                continue;
            }
            $folderName = substr($imgPath, 0, $slashPos);
            $fileName   = substr($imgPath, $slashPos + 1);

            // Ruta local del archivo original
            $localPath = $userDir->getPathname() . '/imgs/' . $folderName . '/' . $fileName;
            if (!file_exists($localPath)) {
                echo "    SKIP: Archivo local no encontrado: $localPath\n";
                $skipped++;
                continue;
            }

            // Detectar MIME
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            $mime  = $finfo ? (string) @finfo_file($finfo, $localPath) : 'image/jpeg';
            if ($finfo) @finfo_close($finfo);
            if (strpos($mime, 'image/') !== 0) {
                echo "    SKIP: No parece una imagen (MIME: $mime)\n";
                $skipped++;
                continue;
            }

            $ext = ext_from_mime($mime);

            if ($dryRun) {
                echo "    DRY-RUN: Se migraría a compartir.site (MIME: $mime, ext: $ext)\n";
                $migrated++;
                $changed = true;
                continue;
            }

            // ── Migrar ──
            try {
                $newFolder = next_img_folder(GIRLSCONF_IMGS_DIR);
                $newFolderLocal = GIRLSCONF_IMGS_DIR . DIRECTORY_SEPARATOR . $newFolder;
                ensure_dir($newFolderLocal);

                $newFileName   = "{$newFolder}.{$ext}";
                $newLocalPath  = $newFolderLocal . DIRECTORY_SEPARATOR . $newFileName;

                if (!@copy($localPath, $newLocalPath)) {
                    rrmdir($newFolderLocal);
                    throw new RuntimeException("No se pudo copiar la imagen a $newLocalPath");
                }

                $publicFolderUrl = GIRLSCONF_BASE_URL . rawurlencode($newFolder) . '/';
                $publicImageUrl  = GIRLSCONF_BASE_URL . rawurlencode($newFolder) . '/' . rawurlencode($newFileName);

                $indexContent = build_og_index_php($girlName, $descWords, $publicFolderUrl, $publicImageUrl, $mime);
                $indexPath    = $newFolderLocal . DIRECTORY_SEPARATOR . 'index.php';
                if (@file_put_contents($indexPath, $indexContent) === false) {
                    rrmdir($newFolderLocal);
                    throw new RuntimeException("No se pudo escribir index.php en $newFolderLocal");
                }

                // Reemplazar URL
                $fotoUrl = $publicFolderUrl;
                $changed = true;
                $migrated++;
                echo "    OK → $publicFolderUrl\n";

            } catch (Throwable $e) {
                echo "    ERROR: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
        unset($fotoUrl);
    }
    unset($girl);

    // Guardar cambios
    if ($changed && !$dryRun) {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        if (@file_put_contents($girlsFile, $json, LOCK_EX) === false) {
            echo "  ERROR: No se pudo guardar $girlsFile\n";
            $errors++;
        } else {
            echo "  GUARDADO: $girlsFile\n";
        }
    }
}

echo "\n=== Resumen ===\n";
echo "Total fotos candidatas: $total\n";
echo "Migradas:               $migrated\n";
echo "Saltadas:               $skipped\n";
echo "Errores:                $errors\n";
echo "Modo:                   " . ($dryRun ? "DRY-RUN" : "LIVE") . "\n";

if ($dryRun) {
    echo "\n✅ Dry-run completado. Para ejecutar la migración real, quita --dry-run.\n";
} else {
    echo "\n✅ Migración completada.\n";
}
