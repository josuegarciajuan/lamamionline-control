<?php

/**
 * mundosex.php — Adaptador de automatización para MundosexAnuncio
 *
 * Delega en un script Node.js + Playwright que ejecuta Chrome headless
 * para hacer login, rellenar formulario, subir fotos y guardar en
 * mundosexanuncio.com.
 *
 * Contrato de ejecutarAutomatizacion(array $payload): array
 *   Mismo contrato que destacamos.php — recibe y devuelve el mismo formato.
 */

define('MUNDOSEX_DESC_SEO_SUFFIX', "\n\nBurriana, Vila-real, Vila real, Villareal, Vilareal, Borriana, Castellon, Almazora, Nules, Castellon");

function mundosex_ejecutar_automatizacion(array $payload): array
{
    $username = trim((string)($payload['username'] ?? ''));
    $password = trim((string)($payload['password'] ?? ''));
    $listingId = trim((string)($payload['listingId'] ?? ''));

    $result = array(
        'ok' => false,
        'loginOk' => false,
        'editPageOk' => false,
        'saveAttempted' => !empty($payload['fields']),
        'saveClicked' => false,
        'touchedFields' => array(),
        'photosPageOk' => false,
        'photosDeleted' => 0,
        'photosUploaded' => 0,
        'warnings' => array(),
        'currentUrl' => null,
        'saveResult' => null,
    );

    if ($username === '' || $password === '' || $listingId === '') {
        $result['error'] = 'Faltan username, password o listingId';
        return $result;
    }

    $fields = is_array($payload['fields'] ?? null) ? $payload['fields'] : array();
    $photoPaths = array_values(array_filter(array_map('strval', (array)($payload['photos'] ?? array()))));

    // Validar que las fotos existen
    $validPhotos = array();
    foreach ($photoPaths as $p) {
        if (is_file($p)) {
            $validPhotos[] = $p;
        } else {
            $result['warnings'][] = 'Imagen no encontrada: ' . $p;
        }
    }

    // Mapear campos del payload PHP al formato del script Node.js
    $browserFields = array();

    // Título y descripción → directos
    if (isset($fields['title']) && $fields['title'] !== '') {
        $browserFields['title'] = (string)$fields['title'];
    }
    if (isset($fields['description']) && $fields['description'] !== '') {
        $desc = (string)$fields['description'];
        // Append SEO keywords if not already present (avoid duplicates on re-uploads)
        if (!str_ends_with(trim($desc), trim(MUNDOSEX_DESC_SEO_SUFFIX))) {
            $desc .= MUNDOSEX_DESC_SEO_SUFFIX;
        }
        $browserFields['description'] = $desc;
    }

    // Teléfono
    if (!empty($fields['telefono'])) {
        $browserFields['phone'] = (string)$fields['telefono'];
    }

    // Email = el mismo username de login
    $browserFields['email'] = $username;

    // WhatsApp siempre activado para Mundosex
    $browserFields['whatsapp'] = true;

    // Provincia / Ciudad — NO se modifican. El usuario quiere mantener los valores existentes.

    $result['touchedFields'] = array_keys($browserFields);

    // Construir payload para el script Node
    $nodePayload = array(
        'username' => $username,
        'password' => $password,
        'listingId' => $listingId,
        'fields' => $browserFields,
        'photos' => $validPhotos,
    );

    $jsonPayload = json_encode($nodePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($jsonPayload === false) {
        $result['error'] = 'Error al codificar el payload JSON';
        return $result;
    }

    // Guardar payload en fichero temporal para evitar exponer credenciales en la línea de comandos
    $tmpDir = DATA_PATH . '/publicista/tmp';
    if (!is_dir($tmpDir)) {
        @mkdir($tmpDir, 0775, true);
    }
    $tmpFile = $tmpDir . '/mundosex_payload_' . generate_id('pl') . '.json';
    if (file_put_contents($tmpFile, $jsonPayload, LOCK_EX) === false) {
        $result['error'] = 'No se pudo escribir el fichero temporal de payload';
        return $result;
    }
    // Restringir permisos para que solo el usuario actual pueda leer
    @chmod($tmpFile, 0600);

    // Ruta al script Node.js
    $nodeScript = dirname(__FILE__) . '/mundosex_browser.js';
    if (!is_file($nodeScript)) {
        @unlink($tmpFile);
        $result['error'] = 'Script browser no encontrado: ' . $nodeScript;
        return $result;
    }

    // Verificar que Node.js está disponible
    $nodeBin = 'node';
    $whichNode = trim((string)shell_exec('which node 2>/dev/null'));
    if ($whichNode !== '') {
        $nodeBin = $whichNode;
    }

    // Timeout: 5 minutos máximo
    $prevTimeLimit = (int)ini_get('max_execution_time');
    @set_time_limit(300);

    // Ejecutar Node.js pasando la ruta al fichero de payload (no las credenciales)
    $command = $nodeBin . ' ' . escapeshellarg($nodeScript) . ' --file=' . escapeshellarg($tmpFile) . ' 2>&1';

    $output = array();
    $exitCode = -1;
    exec($command, $output, $exitCode);

    // Restaurar time limit y limpiar fichero temporal
    @set_time_limit($prevTimeLimit);
    @unlink($tmpFile);

    // El script Node escribe stderr para logs y stdout para el JSON final
    $stdout = implode("\n", $output);

    // Extraer la última línea JSON del output (ignorando logs de stderr)
    $lines = array_filter($output, function($line) {
        $trimmed = trim($line);
        return $trimmed !== '' && $trimmed[0] === '{';
    });

    if (empty($lines)) {
        $result['error'] = 'El script browser no devolvió JSON válido. Exit code: ' . $exitCode . '. Output: ' . substr($stdout, -500);
        return $result;
    }

    $lastJson = end($lines);
    $browserResult = json_decode($lastJson, true);

    if (!is_array($browserResult)) {
        $result['error'] = 'Respuesta JSON inválida del browser: ' . substr($lastJson, 0, 200);
        return $result;
    }

    // Mapear resultado del browser al formato esperado por el CRM
    $result['ok'] = !empty($browserResult['ok']);
    $result['loginOk'] = !empty($browserResult['loginOk']);
    $result['editPageOk'] = !empty($browserResult['loginOk']); // Si login OK, edit page también OK
    $result['saveClicked'] = !empty($browserResult['saveClicked']);
    $result['photosPageOk'] = ($browserResult['photosProcessed'] ?? 0) > 0;
    $result['photosUploaded'] = (int)($browserResult['photosProcessed'] ?? 0);

    if (!empty($browserResult['error'])) {
        $result['error'] = $browserResult['error'];
    }

    if (is_array($browserResult['saveResult'] ?? null)) {
        $result['saveResult'] = $browserResult['saveResult'];
    }

    // Incorporar warnings del browser
    if (is_array($browserResult['warnings'] ?? null)) {
        foreach ($browserResult['warnings'] as $w) {
            $result['warnings'][] = is_string($w) ? $w : json_encode($w, JSON_UNESCAPED_UNICODE);
        }
    }

    // Añadir información de debug
    $result['adapter_meta'] = array(
        'node_exit_code' => $exitCode,
        'photos_requested' => count($photoPaths),
        'photos_valid' => count($validPhotos),
    );

    return $result;
}
