<?php
declare(strict_types=1);

const GIRLSCONF_IMGS_DIR = '/var/www/html/wasapbot/landing/girlsconf/imgs';
const GIRLSCONF_DATA_FILE = '/var/www/html/wasapbot/landing/girlsconf/data/girls.json';
const GIRLSCONF_BASE_URL = 'https://compartir.site/';
const GIRLSCONF_MAX_PHOTOS = 6;

/**
 * Slugify: lowercase, replace non-alphanumeric (except dash) with dashes, trim dashes.
 */
function _girlsconf_slugify(string $name): string {
    $slug = mb_strtolower(trim($name), 'UTF-8');
    $slug = (string)preg_replace('/[^a-z0-9\-]+/', '-', $slug);
    $slug = trim($slug, '-');
    if ($slug === '') $slug = 'item';
    return $slug;
}

/**
 * Generate a random lowercase alphanumeric string of given length.
 */
function _girlsconf_random_alnum(int $len = 5): string {
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $max = strlen($chars) - 1;
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

/**
 * Generate a unique image folder name (5 lowercase alphanumeric chars).
 */
function _girlsconf_next_img_folder(string $imgsDir): string {
    if (!is_dir($imgsDir)) {
        @mkdir($imgsDir, 0777, true);
    }
    for ($i = 0; $i < 200; $i++) {
        $name = _girlsconf_random_alnum(5);
        if (!is_dir($imgsDir . DIRECTORY_SEPARATOR . $name)) {
            return $name;
        }
    }
    // Fallback with 6 chars if collisions
    for ($i = 0; $i < 200; $i++) {
        $name = _girlsconf_random_alnum(6);
        if (!is_dir($imgsDir . DIRECTORY_SEPARATOR . $name)) {
            return $name;
        }
    }
    throw new RuntimeException('No se pudo generar un nombre de carpeta único para la imagen.');
}

/**
 * Map MIME type to file extension.
 */
function _girlsconf_mime_to_ext(string $mime): string {
    $map = array(
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/avif' => 'avif',
    );
    return $map[$mime] ?? 'jpg';
}

/**
 * Detect MIME type of a file. Uses mime_content_type() with finfo_open() fallback.
 */
function _girlsconf_mime_type(string $filePath): string {
    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($filePath);
        if ($mime !== false && $mime !== '') return $mime;
    }
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = @finfo_file($finfo, $filePath);
            @finfo_close($finfo);
            if ($mime !== false && $mime !== '') return $mime;
        }
    }
    return 'image/jpeg'; // default fallback
}

/**
 * Build the OG index.php content for an image folder (replicates girlsconf's build_og_index_php).
 */
function _girlsconf_build_og_index(string $girlName, string $descShortWords, string $publicFolderUrl, string $publicImageUrl, string $mime, int $w = 1200, int $h = 1200): string {
    if (substr($publicFolderUrl, -1) !== '/') $publicFolderUrl .= '/';
    $t = htmlspecialchars($girlName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $d = htmlspecialchars($descShortWords, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $folder = htmlspecialchars($publicFolderUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $img = htmlspecialchars($publicImageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mimeHtml = htmlspecialchars($mime, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return "<!doctype html>\n<html lang=\"es\">\n<head>\n  <meta charset=\"utf-8\">\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n  <meta property=\"og:type\" content=\"website\">\n  <meta property=\"og:title\" content=\"{$t}\">\n  <meta property=\"og:description\" content=\"{$d}\">\n  <meta property=\"og:image\" content=\"{$img}\">\n  <meta property=\"og:image:secure_url\" content=\"{$img}\">\n  <meta property=\"og:image:type\" content=\"{$mimeHtml}\">\n  <meta property=\"og:image:width\" content=\"{$w}\">\n  <meta property=\"og:image:height\" content=\"{$h}\">\n  <meta property=\"og:url\" content=\"{$folder}\">\n  <meta name=\"twitter:card\" content=\"summary_large_image\">\n  <meta name=\"twitter:image\" content=\"{$img}\">\n  <title>Foto</title>\n</head>\n<body>\n  <img src=\"{$img}\" alt=\"Foto\" style=\"max-width:100%;height:auto\">\n</body>\n</html>\n";
}

/**
 * Read the girls.json data from the girlsconf project.
 */
function _girlsconf_read_data(): array {
    if (!file_exists(GIRLSCONF_DATA_FILE)) {
        return array('girls' => array());
    }
    $raw = @file_get_contents(GIRLSCONF_DATA_FILE);
    if ($raw === false) return array('girls' => array());
    $data = json_decode($raw, true);
    if (!is_array($data)) return array('girls' => array());
    if (!isset($data['girls']) || !is_array($data['girls'])) $data['girls'] = array();
    return $data;
}

/**
 * Write the girls.json data atomically (tmp file + rename).
 */
function _girlsconf_write_data(array $data): void {
    $dir = dirname(GIRLSCONF_DATA_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    $tmp = GIRLSCONF_DATA_FILE . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        throw new RuntimeException('No se pudo serializar JSON para girlsconf.');
    }
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo escribir archivo temporal de girlsconf.');
    }
    if (!@rename($tmp, GIRLSCONF_DATA_FILE)) {
        @unlink($tmp);
        throw new RuntimeException('No se pudo reemplazar el archivo de datos de girlsconf.');
    }
}

/**
 * Renombra una entrada existente cuyo slug colisiona con un product_job_id distinto.
 * Añade barras bajas al id y nombre hasta encontrar uno libre. Modifica $data por ref.
 */
function _girlsconf_avoid_slug_collision(array &$data, string $slugId, string $name, string $myProductJobId): void {
    foreach ($data['girls'] as $idx => $girl) {
        if (trim((string)($girl['id'] ?? '')) !== $slugId) continue;
        if (trim((string)($girl['product_job_id'] ?? '')) === $myProductJobId) continue;
        // Colisión detectada: renombrar la entrada vieja
        $suffix = '_';
        $newId = $slugId . $suffix;
        $newName = $name . $suffix;
        $taken = array();
        foreach ($data['girls'] as $g) {
            $taken[trim((string)($g['id'] ?? ''))] = true;
        }
        while (isset($taken[$newId])) {
            $suffix .= '_';
            $newId = $slugId . $suffix;
            $newName = $name . $suffix;
        }
        $data['girls'][$idx]['id'] = $newId;
        $data['girls'][$idx]['nombre'] = $newName;
        break;
    }
}

/**
 * Sync published campaign items (portal=destacamos) to the girlsconf project's girls.json.
 *
 * - Deactivates ALL previous entries from this campaign before syncing
 * - Creates/updates entries from published campaign items using publish_name
 * - Copies up to 6 images per profile into the girlsconf imgs/ directory
 * - Generates OG meta index.php files for each image folder
 * - Adds source_campaign_id to track which campaign created each entry
 */
function publicista_sync_girlsconf_to_girlsconf(string $campaignId): bool {
    $imgsDir = GIRLSCONF_IMGS_DIR;
    $baseUrl = GIRLSCONF_BASE_URL;
    $maxPhotos = GIRLSCONF_MAX_PHOTOS;

    $campaign = publicista_campaign_get($campaignId);
    if (!$campaign) return false;

    // 1. Read current girls.json
    $data = _girlsconf_read_data();

    // 2. Deactivate ALL currently active profiles (not just from this campaign)
    $deactivated = 0;
    foreach ($data['girls'] as $idx => $girl) {
        if (!empty($girl['activa'])) {
            $data['girls'][$idx]['activa'] = false;
            $deactivated++;
        }
    }

    // 3. Get campaign products — ONE entry per product (not per item)
    $productIds = array_values(array_filter(array_map('trim', (array)($campaign['product_ids'] ?? array()))));
    if (empty($productIds)) return false;

    // Collect unique products from campaign
    $usedProductJobIds = array();

    // Also collect image paths from items for each product (take first item's images)
    $productImages = array();
    $allItems = publicista_campaign_items_for_campaign($campaignId);
    foreach ($allItems as $item) {
        $pjid = trim((string)($item['product_job_id'] ?? ''));
        if ($pjid === '' || isset($productImages[$pjid])) continue; // first item wins for images
        $imagePaths = publicista_campaign_item_image_paths($item);
        if (!empty($imagePaths)) {
            $productImages[$pjid] = $imagePaths;
        }
    }

    // 4. Keep lookup maps for upsert
    $lookupByProductJobId = array();
    foreach ($data['girls'] as $idx => $girl) {
        $girlProductJobId = trim((string)($girl['product_job_id'] ?? ''));
        if ($girlProductJobId !== '') {
            $lookupByProductJobId[$girlProductJobId] = $idx;
        }
    }

    // 5. Process each campaign product
    if (!function_exists('publicista_job_get')) return false;

    foreach ($productIds as $productJobId) {
        $productJobId = trim((string)$productJobId);
        if ($productJobId === '') continue;

        $productJob = publicista_job_get($productJobId);
        if (!$productJob || !is_array($productJob)) continue;

        // Use publish_name (the "Nombre de publicación" shown in ads)
        $nombre = trim((string)($productJob['publish_name'] ?? ''));
        if ($nombre === '') {
            $nombre = trim((string)($productJob['nombre_trabajo'] ?? ''));
        }
        if ($nombre === '') continue;

        // Description: use the copy body from first item of this product
        $descripcion = $nombre;
        foreach ($allItems as $item) {
            if (trim((string)($item['product_job_id'] ?? '')) === $productJobId) {
                $body = publicista_campaign_item_copy_body($item);
                if ($body !== '') { $descripcion = $body; break; }
            }
        }

        // Images: from first item that has them, fallback to product job's finales
        $imagePaths = $productImages[$productJobId] ?? array();
        if (empty($imagePaths) && function_exists('publicista_job_image_paths')) {
            $imagePaths = publicista_job_image_paths($productJob, $maxPhotos);
        }
        $imagePaths = array_values(array_filter($imagePaths, 'file_exists'));
        $imagePaths = array_slice($imagePaths, 0, $maxPhotos);

        if (empty($imagePaths)) continue;

        // Generate slug-based ID
        $id = _girlsconf_slugify($nombre);

        // Evitar colisión: si existe otra entrada con el mismo slug pero distinto product_job_id,
        // renombrar la vieja añadiendo _ para que no se machaquen datos entre perfiles distintos
        _girlsconf_avoid_slug_collision($data, $id, $nombre, $productJobId);

        // Ensure imgs directory exists
        if (!is_dir($imgsDir)) {
            @mkdir($imgsDir, 0777, true);
        }

        $fotos = array();

        // Process each image
        foreach ($imagePaths as $srcPath) {
            if (!file_exists($srcPath)) continue;

            $folderName = _girlsconf_next_img_folder($imgsDir);
            $folderPath = $imgsDir . DIRECTORY_SEPARATOR . $folderName;

            if (!@mkdir($folderPath, 0777, true) && !is_dir($folderPath)) continue;

            $mime = _girlsconf_mime_type($srcPath);
            $ext = _girlsconf_mime_to_ext($mime);
            $dstFile = $folderPath . DIRECTORY_SEPARATOR . $folderName . '.' . $ext;

            if (!@copy($srcPath, $dstFile)) {
                @unlink($dstFile);
                @rmdir($folderPath);
                continue;
            }

            $publicFolderUrl = $baseUrl . $folderName . '/';
            $publicImageUrl = $baseUrl . $folderName . '/' . $folderName . '.' . $ext;
            $descWords = mb_substr(preg_replace('/\s+/u', ' ', trim($descripcion)), 0, 60) ?: $nombre;
            $ogIndex = _girlsconf_build_og_index($nombre, $descWords, $publicFolderUrl, $publicImageUrl, $mime);
            @file_put_contents($folderPath . DIRECTORY_SEPARATOR . 'index.php', $ogIndex);

            $fotos[] = $publicFolderUrl;
        }

        if (empty($fotos)) continue;

        // Build entry
        $entry = array(
            'id' => $id,
            'nombre' => $nombre,
            'descripcion_corta' => $descripcion,
            'fotos' => $fotos,
            'activa' => true,
            'source_campaign_id' => $campaignId,
            'product_job_id' => $productJobId,
        );

        // Upsert by product_job_id
        if (isset($lookupByProductJobId[$productJobId])) {
            $data['girls'][$lookupByProductJobId[$productJobId]] = $entry;
        } else {
            $data['girls'][] = $entry;
            $lookupByProductJobId[$productJobId] = count($data['girls']) - 1;
        }

        $usedProductJobIds[$productJobId] = true;
    }

    // 6. Write back atomically
    _girlsconf_write_data($data);

    return true;
}
