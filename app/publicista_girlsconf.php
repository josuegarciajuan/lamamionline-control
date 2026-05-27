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

    // 1. Read current girls.json
    $data = _girlsconf_read_data();

    // 2. Deactivate ALL existing entries that belong to this campaign
    //    This ensures that if profiles are removed from the campaign or
    //    their publish_name changes, old entries don't linger as active.
    foreach ($data['girls'] as $idx => $girl) {
        $girlCampaignId = trim((string)($girl['source_campaign_id'] ?? ''));
        if ($girlCampaignId !== '' && $girlCampaignId === $campaignId) {
            $data['girls'][$idx]['activa'] = false;
        }
    }

    // 3. Fetch published campaign items (estado='published')
    $allItems = publicista_campaign_items_for_campaign($campaignId);
    $publishedItems = array();
    foreach ($allItems as $item) {
        if (trim((string)($item['estado'] ?? '')) === 'published') {
            $publishedItems[] = $item;
        }
    }

    // Collect the product_job_ids from this campaign's published items
    // Used to find legacy entries (from before source_campaign_id was added)
    // and to deactivate old entries that are no longer in the campaign.
    $campaignProductJobIds = array();
    foreach ($publishedItems as $item) {
        $pjid = trim((string)($item['product_job_id'] ?? ''));
        if ($pjid !== '') {
            $campaignProductJobIds[$pjid] = true;
        }
    }

    // Also deactivate legacy entries that share a product_job_id with this
    // campaign but have no source_campaign_id (migration case).
    if (!empty($campaignProductJobIds)) {
        foreach ($data['girls'] as $idx => $girl) {
            $girlProductJobId = trim((string)($girl['product_job_id'] ?? ''));
            if (
                $girlProductJobId !== ''
                && isset($campaignProductJobIds[$girlProductJobId])
                && !empty($girl['activa'])
            ) {
                $girlCampaignId = trim((string)($girl['source_campaign_id'] ?? ''));
                // Only deactivate if not from this campaign (it will be
                // re-activated below if still published).
                if ($girlCampaignId !== $campaignId) {
                    $data['girls'][$idx]['activa'] = false;
                }
            }
        }
    }

    // 4. Keep lookup maps: by slug-based id AND by product_job_id
    $lookupById = array();
    $lookupByProductJobId = array();
    foreach ($data['girls'] as $idx => $girl) {
        $girlId = trim((string)($girl['id'] ?? ''));
        if ($girlId !== '') {
            $lookupById[$girlId] = $idx;
        }
        $girlProductJobId = trim((string)($girl['product_job_id'] ?? ''));
        if ($girlProductJobId !== '') {
            $lookupByProductJobId[$girlProductJobId] = $idx;
        }
    }

    // 5. Process each published item
    foreach ($publishedItems as $item) {
        // FIX Bug A: Use publish_name from the actual product job,
        // NOT nombre_trabajo (which is the internal pack name / apodo).
        // publish_name is the "Nombre de publicación" shown in ads.
        $productJobId = trim((string)($item['product_job_id'] ?? ''));
        $nombre = '';

        // Look up the product job to get publish_name
        if ($productJobId !== '' && function_exists('publicista_job_get')) {
            $productJob = publicista_job_get($productJobId);
            if ($productJob && is_array($productJob)) {
                $nombre = trim((string)($productJob['publish_name'] ?? ''));
            }
        }

        // Fallback: if publish_name is empty, try nombre_trabajo from snapshot
        if ($nombre === '') {
            $productSnapshot = is_array($item['product_snapshot'] ?? null) ? $item['product_snapshot'] : array();
            $dataField = is_array($productSnapshot['data'] ?? null) ? $productSnapshot['data'] : $productSnapshot;
            $nombre = trim((string)($dataField['nombre_trabajo'] ?? ''));
        }

        // Last resort fallback: use product_job_id
        if ($nombre === '') {
            $nombre = $productJobId;
        }

        // Skip items with empty name
        if ($nombre === '') continue;

        // Get description from copy_snapshot
        $descripcion = publicista_campaign_item_copy_body($item);
        if ($descripcion === '') $descripcion = $nombre;

        // Generate ID by slugifying the name
        $id = _girlsconf_slugify($nombre);

        // Get image filesystem paths, take up to MAX_PHOTOS
        $imagePaths = publicista_campaign_item_image_paths($item);
        $imagePaths = array_slice($imagePaths, 0, $maxPhotos);

        // Skip items with no images
        if (empty($imagePaths)) continue;

        // Ensure imgs directory exists
        if (!is_dir($imgsDir)) {
            @mkdir($imgsDir, 0777, true);
        }

        $fotos = array();

        // Process each image
        foreach ($imagePaths as $srcPath) {
            if (!file_exists($srcPath)) continue;

            // Generate unique folder name
            $folderName = _girlsconf_next_img_folder($imgsDir);
            $folderPath = $imgsDir . DIRECTORY_SEPARATOR . $folderName;

            // Create the folder
            if (!@mkdir($folderPath, 0777, true) && !is_dir($folderPath)) {
                continue;
            }

            // Detect MIME and extension
            $mime = _girlsconf_mime_type($srcPath);
            $ext = _girlsconf_mime_to_ext($mime);

            // Destination file: {foldername}.{ext}
            $dstFile = $folderPath . DIRECTORY_SEPARATOR . $folderName . '.' . $ext;

            // Copy the image
            if (!@copy($srcPath, $dstFile)) {
                // Cleanup failed folder
                @unlink($dstFile);
                @rmdir($folderPath);
                continue;
            }

            // Build public URLs
            $publicFolderUrl = $baseUrl . $folderName . '/';
            $publicImageUrl = $baseUrl . $folderName . '/' . $folderName . '.' . $ext;

            // Create OG index.php in the folder
            $descWords = mb_substr(preg_replace('/\s+/u', ' ', trim($descripcion)), 0, 60) ?: $nombre;
            $ogIndex = _girlsconf_build_og_index($nombre, $descWords, $publicFolderUrl, $publicImageUrl, $mime);
            @file_put_contents($folderPath . DIRECTORY_SEPARATOR . 'index.php', $ogIndex);

            $fotos[] = $publicFolderUrl;
        }

        // Skip if no photos were successfully processed
        if (empty($fotos)) continue;

        // Build the entry
        $entry = array(
            'id' => $id,
            'nombre' => $nombre,
            'descripcion_corta' => $descripcion,
            'fotos' => $fotos,
            'activa' => true,
            'source_campaign_id' => $campaignId,
            'product_job_id' => $productJobId,
        );

        // Update or create, preferring product_job_id as the stable key.
        // This handles renames: if publish_name changes, product_job_id
        // still matches the existing entry and updates it in place.
        $targetIndex = null;
        if ($productJobId !== '' && isset($lookupByProductJobId[$productJobId])) {
            // Found by product_job_id — stable across publish_name changes.
            // If the name changed, the ID changes too, which is correct:
            // the slug-based ID follows the display name.
            $targetIndex = $lookupByProductJobId[$productJobId];
        } elseif ($productJobId !== '' && isset($lookupById[$id])) {
            // Found by slug ID but product_job_id not yet in lookup
            // (e.g., legacy entry before product_job_id was tracked).
            $targetIndex = $lookupById[$id];
        } elseif (isset($lookupById[$id])) {
            $targetIndex = $lookupById[$id];
        }

        if ($targetIndex !== null) {
            // Update existing entry in place
            $data['girls'][$targetIndex] = $entry;
            // Update lookup maps
            $lookupById[$id] = $targetIndex;
            if ($productJobId !== '') {
                $lookupByProductJobId[$productJobId] = $targetIndex;
            }
        } else {
            $data['girls'][] = $entry;
            $newIdx = count($data['girls']) - 1;
            $lookupById[$id] = $newIdx;
            if ($productJobId !== '') {
                $lookupByProductJobId[$productJobId] = $newIdx;
            }
        }
    }

    // 6. Write back the JSON atomically
    _girlsconf_write_data($data);

    return true;
}
