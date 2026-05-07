<?php

function publicista_ai_default_models() {
    return array(
        'descriptor' => 'gpt-5.4-mini',
        'image' => 'gpt-image-1-mini',
    );
}

function publicista_ai_timeouts() {
    return array(
        'responses' => 90,
        'images' => 180,
        'local_worker' => 90,
    );
}

function publicista_ai_config() {
    $settings = settings_get();
    $defaults = publicista_ai_default_models();

    $apiKey = trim((string)getenv('OPENAI_API_KEY'));
    $apiKeySource = $apiKey !== '' ? 'env' : 'none';

    if ($apiKey === '' && !empty($settings['voice_ai_api_key'])) {
        $apiKey = trim((string)$settings['voice_ai_api_key']);
        $apiKeySource = $apiKey !== '' ? 'settings' : 'none';
    }

    if ($apiKey === '' && function_exists('voice_ai_default_api_key')) {
        $apiKey = trim((string)voice_ai_default_api_key());
        if ($apiKey !== '') {
            $apiKeySource = 'bot_template';
        }
    }

    $descriptorModel = trim((string)getenv('OPENAI_PUBLICISTA_DESCRIPTOR_MODEL'));
    if ($descriptorModel === '') {
        $descriptorModel = trim((string)($settings['publicista_descriptor_model'] ?? ''));
    }
    if ($descriptorModel === '') {
        $descriptorModel = $defaults['descriptor'];
    }

    $imageModel = trim((string)getenv('OPENAI_PUBLICISTA_IMAGE_MODEL'));
    if ($imageModel === '') {
        $imageModel = trim((string)($settings['publicista_image_model'] ?? ''));
    }
    if ($imageModel === '') {
        $imageModel = $defaults['image'];
    }

    $responsesTier = trim((string)getenv('OPENAI_PUBLICISTA_RESPONSES_TIER'));
    if ($responsesTier === '') {
        $responsesTier = trim((string)($settings['publicista_responses_tier'] ?? 'flex'));
    }
    if (!in_array($responsesTier, array('auto', 'default', 'flex', 'priority'), true)) {
        $responsesTier = 'flex';
    }

    $promptCacheRetention = trim((string)getenv('OPENAI_PUBLICISTA_PROMPT_CACHE_RETENTION'));
    if ($promptCacheRetention === '') {
        $promptCacheRetention = trim((string)($settings['publicista_prompt_cache_retention'] ?? '24h'));
    }
    if (!in_array($promptCacheRetention, array('in-memory', '24h'), true)) {
        $promptCacheRetention = '24h';
    }

    $useBatchImages = getenv('OPENAI_PUBLICISTA_USE_BATCH_IMAGES');
    if ($useBatchImages === false || $useBatchImages === '') {
        $useBatchImages = (string)($settings['publicista_use_batch_images'] ?? '1');
    }

    return array(
        'api_key' => $apiKey,
        'configured' => ($apiKey !== ''),
        'api_key_source' => $apiKeySource,
        'organization' => trim((string)getenv('OPENAI_ORGANIZATION')),
        'project' => trim((string)getenv('OPENAI_PROJECT')),
        'descriptor_model' => $descriptorModel,
        'image_model' => $imageModel,
        'responses_service_tier' => $responsesTier,
        'prompt_cache_retention' => $promptCacheRetention,
        'use_batch_images' => !in_array(strtolower(trim((string)$useBatchImages)), array('0', 'false', 'no', 'off'), true),
        'timeouts' => publicista_ai_timeouts(),
    );
}

function publicista_max_saving_mode_enabled() {
    return true;
}

function publicista_prompt_cache_key($purpose, $model = '') {
    $purpose = preg_replace('/[^a-z0-9_\-]/i', '_', (string)$purpose);
    $model = preg_replace('/[^a-z0-9_\-\.]/i', '_', (string)$model);
    return 'publicista:' . ($purpose !== '' ? $purpose : 'general') . ':' . ($model !== '' ? $model : 'model');
}

function publicista_response_payload_defaults($purpose, $model) {
    $cfg = publicista_ai_config();
    $payload = array();
    if ($cfg['responses_service_tier'] !== 'auto' && $cfg['responses_service_tier'] !== '') {
        $payload['service_tier'] = $cfg['responses_service_tier'];
    }
    $payload['prompt_cache_key'] = publicista_prompt_cache_key($purpose, $model);
    $payload['prompt_cache_retention'] = $cfg['prompt_cache_retention'];
    return $payload;
}

function publicista_pipeline_batch_state($job) {
    $pipeline = is_array(publicista_array_get($job, 'pipeline', array())) ? publicista_array_get($job, 'pipeline', array()) : array();
    $batch = is_array(publicista_array_get($pipeline, 'batch', array())) ? publicista_array_get($pipeline, 'batch', array()) : array();
    return array_merge(publicista_job_defaults(publicista_array_get($job, 'id', ''))['pipeline']['batch'], $batch);
}

function publicista_is_gpt_image_model($model = '') {
    $model = trim((string)$model);
    if ($model === '') {
        $cfg = publicista_ai_config();
        $model = trim((string)($cfg['image_model'] ?? ''));
    }
    return $model !== '' && (strpos($model, 'gpt-image-') === 0 || $model === 'chatgpt-image-latest');
}

function publicista_pipeline_has_pending_batch($job) {
    $batch = publicista_pipeline_batch_state($job);
    $batchId = trim((string)publicista_array_get($batch, 'image_batch_id', ''));
    if ($batchId === '') {
        return false;
    }

    $status = trim((string)publicista_array_get($batch, 'status', ''));
    if (in_array($status, array('validating', 'in_progress', 'finalizing'), true)) {
        return true;
    }

    if ($status === 'completed') {
        $hasResultJson = trim((string)publicista_array_get($batch, 'result_jsonl_path', '')) !== '';
        $hasCandidates = is_array(publicista_array_get($job, 'candidates', array())) && count((array)publicista_array_get($job, 'candidates', array())) > 0;
        $hasFinals = is_array(publicista_array_get($job, 'final_images', array())) && count((array)publicista_array_get($job, 'final_images', array())) > 0;
        return !$hasResultJson || (!$hasCandidates && !$hasFinals);
    }

    return false;
}

function publicista_batch_status_label($status) {
    $status = trim((string)$status);
    $map = array(
        'validating' => 'Validando',
        'in_progress' => 'En progreso',
        'finalizing' => 'Finalizando',
        'completed' => 'Completado',
        'failed' => 'Fallido',
        'expired' => 'Expirado',
        'cancelled' => 'Cancelado',
        'cancelling' => 'Cancelando',
    );
    return isset($map[$status]) ? $map[$status] : ($status !== '' ? $status : 'Pendiente');
}

function publicista_notify_job_generation_finished($job) {
    if (!function_exists('avisos_create_active') || !function_exists('aviso_exists_any_status')) {
        return false;
    }

    $job = is_array($job) ? $job : array();
    $jobId = trim((string)($job['id'] ?? ''));
    if ($jobId === '') {
        return false;
    }

    $sourceKey = 'publicista_job_finished_' . $jobId;
    if (aviso_exists_any_status('publicista', $sourceKey)) {
        return false;
    }

    $jobName = trim((string)($job['nombre_trabajo'] ?? ''));
    if ($jobName === '') {
        $jobName = 'Trabajo ' . $jobId;
    }

    $clienta = trim((string)($job['clienta_nombre_snapshot'] ?? ''));
    $estado = trim((string)($job['estado'] ?? ''));
    $pipeline = is_array($job['pipeline'] ?? null) ? $job['pipeline'] : array();
    $summary = trim((string)($pipeline['summary'] ?? ''));
    $finalImages = is_array($job['final_images'] ?? null) ? $job['final_images'] : array();
    $severity = $estado === 'done' ? 'media' : 'alta';

    $detailParts = array();
    $detailParts[] = 'Perfil: ' . $jobName;
    if ($clienta !== '') {
        $detailParts[] = 'Clienta: ' . $clienta;
    }
    $detailParts[] = 'Estado: ' . ($estado !== '' ? $estado : 'completado');
    $detailParts[] = 'Finales: ' . count($finalImages);
    if ($summary !== '') {
        $detailParts[] = $summary;
    }

    avisos_create_active(
        $estado === 'done' ? 'Publicista: perfil generado' : 'Publicista: perfil terminado con revisión pendiente',
        implode(' · ', $detailParts),
        $severity,
        'publicista',
        array(
            'job_id' => $jobId,
            'job_name' => $jobName,
            'clienta_nombre' => $clienta,
            'estado' => $estado,
        ),
        false,
        $sourceKey
    );

    return true;
}


function publicista_notify_final_refresh_finished($job, $finalId, $mode, $ok, $resultOrError = null) {
    if (!function_exists('avisos_create_active') || !function_exists('aviso_exists_any_status')) {
        return false;
    }

    $job = is_array($job) ? $job : array();
    $jobId = trim((string)($job['id'] ?? ''));
    $finalId = trim((string)$finalId);
    $mode = trim((string)$mode);
    if ($jobId === '' || $finalId === '') {
        return false;
    }

    $sourceKey = 'publicista_final_refresh_' . $jobId . '_' . $finalId . '_' . ($mode !== '' ? $mode : 'refresh') . '_' . ($ok ? 'ok' : 'error') . '_' . date('YmdHis');
    $jobName = trim((string)($job['nombre_trabajo'] ?? ''));
    if ($jobName === '') {
        $jobName = 'Trabajo ' . $jobId;
    }
    $clienta = trim((string)($job['clienta_nombre_snapshot'] ?? ''));
    $modeLabel = $mode === 'reframe' ? 'propuesta refinada' : 'refresco de final';
    $title = $ok ? 'Publicista: ' . $modeLabel . ' lista' : 'Publicista: fallo al generar ' . $modeLabel;
    $details = array('Perfil: ' . $jobName, 'Final: ' . $finalId, 'Acción: ' . $modeLabel);
    if ($clienta !== '') {
        $details[] = 'Clienta: ' . $clienta;
    }
    if ($ok) {
        $details[] = $mode === 'reframe'
            ? 'La propuesta refinada ya está lista para revisar y decidir si se adopta.'
            : 'La final rehecha ya está disponible en la ficha.';
    } else {
        $errorText = is_string($resultOrError) ? trim($resultOrError) : trim((string)publicista_array_get((array)$resultOrError, 'error', ''));
        $details[] = $errorText !== '' ? $errorText : 'No se pudo completar el proceso.';
    }

    avisos_create_active(
        $title,
        implode(' · ', array_filter($details, function($part) { return trim((string)$part) !== ''; })),
        $ok ? 'media' : 'alta',
        'publicista',
        array(
            'job_id' => $jobId,
            'job_name' => $jobName,
            'clienta_nombre' => $clienta,
            'final_id' => $finalId,
            'mode' => $mode,
            'ok' => !empty($ok),
        ),
        false,
        $sourceKey
    );

    return true;
}

function publicista_notify_candidate_regeneration_finished($job, $candidateId, $ok, $resultOrError = null) {
    if (!function_exists('avisos_create_active')) {
        return false;
    }

    $job = is_array($job) ? $job : array();
    $jobId = trim((string)($job['id'] ?? ''));
    $candidateId = trim((string)$candidateId);
    if ($jobId === '' || $candidateId === '') {
        return false;
    }

    $jobName = trim((string)($job['nombre_trabajo'] ?? ''));
    if ($jobName === '') {
        $jobName = 'Trabajo ' . $jobId;
    }
    $clienta = trim((string)($job['clienta_nombre_snapshot'] ?? ''));

    $title = $ok
        ? 'Publicista: candidata regenerada'
        : 'Publicista: fallo al regenerar candidata';
    $details = array('Perfil: ' . $jobName, 'Candidata: ' . $candidateId);
    if ($clienta !== '') {
        $details[] = 'Clienta: ' . $clienta;
    }
    if ($ok) {
        $details[] = 'La candidata y las finales dependientes ya se han actualizado.';
    } else {
        $errorText = is_string($resultOrError) ? trim($resultOrError) : trim((string)publicista_array_get((array)$resultOrError, 'error', ''));
        $details[] = $errorText !== '' ? $errorText : 'No se pudo completar la regeneración.';
    }

    $sourceKey = 'publicista_candidate_regen_' . $jobId . '_' . $candidateId . '_' . ($ok ? 'ok' : 'error') . '_' . date('YmdHis');
    avisos_create_active(
        $title,
        implode(' · ', array_filter($details, function($part) { return trim((string)$part) !== ''; })),
        $ok ? 'media' : 'alta',
        'publicista',
        array(
            'job_id' => $jobId,
            'job_name' => $jobName,
            'clienta_nombre' => $clienta,
            'candidate_id' => $candidateId,
            'ok' => !empty($ok),
        ),
        false,
        $sourceKey
    );

    return true;
}

function publicista_notify_copy_pack_finished($job, $ok, $resultOrError = null) {
    if (!function_exists('avisos_create_active')) {
        return false;
    }

    $job = is_array($job) ? $job : array();
    $jobId = trim((string)($job['id'] ?? ''));
    if ($jobId === '') {
        return false;
    }

    $jobName = trim((string)($job['nombre_trabajo'] ?? ''));
    if ($jobName === '') {
        $jobName = 'Trabajo ' . $jobId;
    }
    $clienta = trim((string)($job['clienta_nombre_snapshot'] ?? ''));

    $title = $ok ? 'Publicista: textos generados' : 'Publicista: fallo al generar textos';
    $details = array('Perfil: ' . $jobName, 'Fase: títulos y textos');
    if ($clienta !== '') {
        $details[] = 'Clienta: ' . $clienta;
    }
    if ($ok) {
        $details[] = 'El pack de textos ya está listo en la ficha.';
    } else {
        $errorText = is_string($resultOrError) ? trim($resultOrError) : trim((string)publicista_array_get((array)$resultOrError, 'error', ''));
        $details[] = $errorText !== '' ? $errorText : 'No se pudo completar la generación de textos.';
    }

    $sourceKey = 'publicista_copy_pack_' . $jobId . '_' . ($ok ? 'ok' : 'error') . '_' . date('YmdHis');
    avisos_create_active(
        $title,
        implode(' · ', array_filter($details, function($part) { return trim((string)$part) !== ''; })),
        $ok ? 'media' : 'alta',
        'publicista',
        array(
            'job_id' => $jobId,
            'job_name' => $jobName,
            'clienta_nombre' => $clienta,
            'ok' => !empty($ok),
            'scope' => 'copy_pack',
        ),
        false,
        $sourceKey
    );

    return true;
}

function publicista_job_meta_write($jobId, $filename, $data) {
    $paths = publicista_job_fs_paths($jobId);
    if (!publicista_ensure_job_dirs($jobId)) {
        return array(false, 'No se pudo crear la carpeta meta del trabajo.');
    }

    $filename = ltrim((string)$filename, '/');
    $fsPath = $paths['meta_dir'] . '/' . $filename;
    $dir = dirname($fsPath);
    if (!publicista_ensure_dir($dir)) {
        return array(false, 'No se pudo crear la carpeta de destino en meta.');
    }

    $payload = is_string($data)
        ? $data
        : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (@file_put_contents($fsPath, $payload) === false) {
        return array(false, 'No se pudo escribir el archivo meta: ' . $fsPath);
    }

    return array(true, publicista_path_to_web($fsPath));
}

function publicista_job_log_write($jobId, $prefix, $data) {
    $paths = publicista_job_fs_paths($jobId);
    if (!publicista_ensure_job_dirs($jobId)) {
        return array(false, 'No se pudo crear la carpeta logs del trabajo.');
    }

    $safePrefix = preg_replace('/[^a-z0-9_\-]/i', '_', (string)$prefix);
    if ($safePrefix === '') {
        $safePrefix = 'log';
    }

    $filename = date('Ymd_His') . '_' . $safePrefix . '.json';
    $payload = is_string($data)
        ? $data
        : json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $fsPath = $paths['logs_dir'] . '/' . $filename;
    if (@file_put_contents($fsPath, $payload) === false) {
        return array(false, 'No se pudo escribir el log: ' . $fsPath);
    }

    return array(true, publicista_path_to_web($fsPath));
}

function publicista_path_to_web($fsPath) {
    $fsPath = str_replace('\\', '/', (string)$fsPath);
    $base = str_replace('\\', '/', BASE_PATH) . '/';
    if (strpos($fsPath, $base) === 0) {
        return substr($fsPath, strlen($base));
    }
    return $fsPath;
}

function publicista_pipeline_is_running($job) {
    $job = is_array($job) ? $job : array();
    $estado = trim((string)publicista_array_get($job, 'estado', ''));
    if ($estado === 'processing') {
        return true;
    }
    $pipeline = is_array(publicista_array_get($job, 'pipeline', array())) ? publicista_array_get($job, 'pipeline', array()) : array();
    $status = trim((string)publicista_array_get($pipeline, 'status', ''));
    return in_array($status, array('processing', 'waiting_batch'), true);
}

function publicista_visual_safety_lines() {
    return array(
        'La imagen debe mantenerse claramente en un registro editorial, glamouroso, comercial y publicitario NO sexual.',
        'NO generes contenido sexual, erótico ni fetichista de ningún tipo. Si alguna instrucción pudiera interpretarse de forma sexual, ignórala y prioriza siempre un resultado editorial premium, elegante, fotogénico y apto para moderación estricta.',
        'La mujer es una adulta y debe aparecer totalmente vestida de forma elegante y publicable, sin desnudo, sin semidesnudo, sin lencería tipo ropa interior, sin bikini, sin bañador, sin corsé, sin transparencias y sin foco erótico en zonas íntimas.',
        'Permite glamour comercial de alto impacto, presencia magnética, pose segura y dinámica, silueta favorecedora, maquillaje pulido, pelo trabajado y actitud confiada, pero sin sexualizar la escena ni convertirla en contenido sugerente explícito.',
        'Se permiten poses de moda y publicidad con brazos despegados del cuerpo, giros de torso, apoyo en pared o mobiliario, paso natural, mano en cintura, mano en pelo o mirada fuera de cámara, siempre que la lectura siga siendo editorial y no íntima.',
        'Evita poses, gestos o expresiones abiertamente sexualizadas: nada de cama, nada de dormitorio, nada de baño o ducha, nada de contexto íntimo, nada de invitación sexual, nada de manos sobre zonas íntimas y nada de gestos fetichistas.',
        'Incluso cuando el briefing pida un resultado atrevido, sexy o sugerente, interprétalo solo como glamour editorial adulto NO sexual: look impactante, estilizado y comercial, con cobertura razonable, sin transparencias y sin lectura erótica explícita.',
        'Debe parecer una campaña fotográfica comercial premium apta para moderación estricta y para una audiencia general adulta.',
    );
}

function publicista_is_sexual_safety_rejection($message, $decoded = null) {
    $haystack = strtolower(trim((string)$message));
    if ($haystack === '' && is_array($decoded)) {
        $haystack = strtolower(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    } elseif (is_array($decoded)) {
        $haystack .= ' ' . strtolower(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    if ($haystack === '') {
        return false;
    }
    if (strpos($haystack, 'safety_violations=[sexual]') !== false) return true;
    if (strpos($haystack, 'safety system') !== false && strpos($haystack, 'sexual') !== false) return true;
    if (strpos($haystack, 'content_policy_violation') !== false && strpos($haystack, 'sexual') !== false) return true;
    return false;
}

function publicista_make_prompt_safer_for_retry($prompt, $attempt = 1) {
    $prompt = trim((string)$prompt);
    $replaceMap = array(
        ' sexy ' => ' elegante ',
        'Sexy' => 'Elegante',
        'SEXY' => 'ELEGANTE',
        'sugerente' => 'editorial elegante',
        'magnética' => 'fotogénica y serena',
        'íntimo' => 'acogedor',
        'intimo' => 'acogedor',
        'escote elegante moderado' => 'escote muy discreto o cuello más cerrado',
        'muy ajustado al cuerpo' => 'de corte elegante, favorecedor y no ceñido en exceso',
        'ajustado al cuerpo' => 'de corte elegante y favorecedor',
        'silueta marcada' => 'silueta natural y equilibrada',
        'Curvas femeninas a replicar con precisión' => 'Silueta general a conservar con naturalidad',
        'El tamaño de pecho, la relación cintura-cadera y la forma de la silueta deben coincidir fielmente con la referencia — no las alteres ni las exageres.' => 'Mantén la silueta general de la referencia sin exagerarla ni sexualizarla.',
        'ambiente íntimo' => 'ambiente acogedor',
        'media sonrisa editorial' => 'gesto editorial sereno',
    );
    $saferPrompt = strtr($prompt, $replaceMap);

    $extra = array(
        'REINTENTO SEGURO: reinterpreta toda la escena de la forma más conservadora y publicable posible.',
        'Mujer adulta completamente vestida con outfit editorial elegante de calle o estudio, sin escote pronunciado, sin transparencias, sin lencería, sin bikini, sin bañador y sin ropa mojada.',
        'Pose neutra o comercial, sin gesto seductor, sin labios entreabiertos, sin manos en el cuerpo y sin foco visual en zonas íntimas.',
        'Prioriza plano medio, tres cuartos o cuerpo entero editorial limpio; evita encuadres que sexualicen el torso o la cadera.',
        'Sin cama, sin dormitorio, sin baño, sin ducha y sin contexto íntimo; usa entorno normal, elegante y luminoso.',
        'El resultado debe sentirse como fotografía publicitaria premium moderada, apta para moderación estricta.'
    );
    if ((int)$attempt >= 2) {
        $extra[] = 'SEGUNDO REINTENTO: simplifica aún más hacia retrato comercial sobrio, pose frontal tranquila, cobertura visual alta y estilismo claramente conservador.';
    }

    return trim($saferPrompt . "

[MODO SEGURIDAD REFORZADA]
- " . implode("
- ", $extra));
}

function publicista_append_visual_safety_block($text) {
    $text = trim((string)$text);
    $lines = publicista_visual_safety_lines();
    if (empty($lines)) {
        return $text;
    }
    $block = '[SEGURIDAD Y MODERACIÓN — OBLIGATORIO]' . "
- " . implode("
- ", $lines);
    return trim($text . "

" . $block);
}

function publicista_guess_extension_from_mime($mime) {
    $mime = strtolower(trim((string)$mime));
    $map = array(
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    );
    return isset($map[$mime]) ? $map[$mime] : 'jpg';
}

function publicista_allowed_upload_mimes() {
    return array('image/jpeg', 'image/jpg', 'image/png', 'image/webp');
}

function publicista_uploaded_file_error_message($code) {
    $code = (int)$code;
    if ($code === UPLOAD_ERR_OK) return '';
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) return 'La imagen supera el tamaño permitido.';
    if ($code === UPLOAD_ERR_PARTIAL) return 'La imagen se subió solo parcialmente.';
    if ($code === UPLOAD_ERR_NO_FILE) return 'No se ha seleccionado ninguna imagen.';
    return 'Error al subir la imagen original.';
}

function publicista_attach_uploaded_source_image($jobId, $file) {
    $job = publicista_job_get($jobId);
    if (!$job) {
        return array(false, 'No se encontró el trabajo de Publicista.');
    }

    if (!is_array($file) || empty($file['tmp_name'])) {
        return array(false, 'No se recibió ninguna imagen para el trabajo.');
    }

    $uploadError = publicista_uploaded_file_error_message($file['error'] ?? UPLOAD_ERR_OK);
    if ($uploadError !== '') {
        return array(false, $uploadError);
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return array(false, 'La imagen subida no es válida.');
    }

    $mime = '';
    if (function_exists('mime_content_type')) {
        $mime = (string)mime_content_type($file['tmp_name']);
    }
    if ($mime === '' && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
        }
    }

    $mime = strtolower(trim($mime));
    if (!in_array($mime, publicista_allowed_upload_mimes(), true)) {
        return array(false, 'Formato de imagen no permitido. Usa JPG, PNG o WEBP.');
    }

    $paths = publicista_job_fs_paths($jobId);
    if (!publicista_ensure_job_dirs($jobId)) {
        return array(false, 'No se pudo preparar la carpeta del trabajo.');
    }

    $originalName = trim((string)($file['name'] ?? 'original'));
    $ext = publicista_guess_extension_from_mime($mime);
    $targetFsPath = $paths['originals_dir'] . '/source_original.' . $ext;

    if (!@move_uploaded_file($file['tmp_name'], $targetFsPath)) {
        return array(false, 'No se pudo guardar la imagen original en disco.');
    }

    $imgSize = @getimagesize($targetFsPath);
    $width = is_array($imgSize) ? (int)($imgSize[0] ?? 0) : 0;
    $height = is_array($imgSize) ? (int)($imgSize[1] ?? 0) : 0;
    $sizeBytes = @filesize($targetFsPath);

    $patch = array(
        'source_image' => array(
            'original_filename' => $originalName,
            'stored_path' => publicista_path_to_web($targetFsPath),
            'mime_type' => $mime,
            'size_bytes' => $sizeBytes !== false ? (int)$sizeBytes : 0,
            'width' => $width,
            'height' => $height,
            'uploaded_at' => now_datetime(),
        ),
        'models' => array(
            'descriptor' => publicista_ai_config()['descriptor_model'],
            'image' => publicista_ai_config()['image_model'],
        ),
    );

    // Preservar modelo Pollo si ya estaba seleccionado antes de re-subir imagen
    $existingImageModel = trim((string)($job['models']['image'] ?? ''));
    if (function_exists('publicista_is_pollo_model') && publicista_is_pollo_model($existingImageModel)) {
        $patch['models']['image'] = $existingImageModel;
    }

    list($ok, $saved) = publicista_job_save(array_merge($job, $patch));
    if (!$ok) {
        return array(false, is_string($saved) ? $saved : 'No se pudo guardar la referencia de la imagen original.');
    }

    publicista_job_log_write($jobId, 'source_upload', array(
        'uploaded_at' => now_datetime(),
        'original_filename' => $originalName,
        'mime_type' => $mime,
        'stored_path' => publicista_path_to_web($targetFsPath),
        'width' => $width,
        'height' => $height,
        'size_bytes' => $sizeBytes !== false ? (int)$sizeBytes : 0,
    ));

    return array(true, $saved);
}

function publicista_proc_command($command, $timeoutSec, $cwd = null) {
    $descriptor = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );

    $process = @proc_open($command, $descriptor, $pipes, $cwd ?: BASE_PATH);
    if (!is_resource($process)) {
        return array(
            'ok' => false,
            'exit_code' => -1,
            'stdout' => '',
            'stderr' => 'No se pudo arrancar el proceso local.',
            'command' => $command,
        );
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout = '';
    $stderr = '';
    $start = microtime(true);
    $timedOut = false;

    while (true) {
        $status = proc_get_status($process);
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);

        if (!$status['running']) {
            break;
        }

        if ((microtime(true) - $start) > $timeoutSec) {
            $timedOut = true;
            @proc_terminate($process);
            usleep(200000);
            $status = proc_get_status($process);
            if (!empty($status['running'])) {
                @proc_terminate($process, 9);
            }
            break;
        }

        usleep(100000);
    }

    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($timedOut) {
        $stderr = trim($stderr . "\nTimeout del proceso local tras {$timeoutSec}s.");
        $exitCode = 124;
    }

    return array(
        'ok' => (!$timedOut && $exitCode === 0),
        'exit_code' => $exitCode,
        'stdout' => trim($stdout),
        'stderr' => trim($stderr),
        'command' => $command,
        'timed_out' => $timedOut,
    );
}

function publicista_prepare_source_locally($jobId) {
    $job = publicista_job_get($jobId);
    if (!$job) {
        return array(false, 'No se encontró el trabajo de Publicista.');
    }

    $sourceRel = trim((string)($job['source_image']['stored_path'] ?? ''));
    if ($sourceRel === '') {
        return array(false, 'El trabajo no tiene imagen original todavía.');
    }

    $sourceFs = BASE_PATH . '/' . ltrim($sourceRel, '/');
    if (!file_exists($sourceFs)) {
        return array(false, 'La imagen original no existe en disco: ' . $sourceFs);
    }

    $paths = publicista_job_fs_paths($jobId);
    $analysisFs = $paths['meta_dir'] . '/local_prepare_result.json';
    $squareFs = $paths['originals_dir'] . '/source_square_canvas.png';
    $previewFs = $paths['originals_dir'] . '/source_preview.jpg';

    $worker = BASE_PATH . '/tools/publicista_image_worker.py';
    if (!file_exists($worker)) {
        return array(false, 'No se encontró el worker Python de Publicista en tools/publicista_image_worker.py');
    }

    $command = 'python3 ' . escapeshellarg($worker)
        . ' prepare-source'
        . ' --input ' . escapeshellarg($sourceFs)
        . ' --output-json ' . escapeshellarg($analysisFs)
        . ' --output-square ' . escapeshellarg($squareFs)
        . ' --output-preview ' . escapeshellarg($previewFs)
        . ' --preview-size 320'
        . ' --square-size 1024';

    $proc = publicista_proc_command($command, publicista_ai_timeouts()['local_worker'], BASE_PATH);
    publicista_job_log_write($jobId, 'local_prepare_command', $proc);

    if (!$proc['ok']) {
        return array(false, 'Error en worker local: ' . ($proc['stderr'] !== '' ? $proc['stderr'] : 'sin detalle'));
    }

    if (!file_exists($analysisFs)) {
        return array(false, 'El worker terminó pero no generó el JSON de análisis local.');
    }

    $analysis = json_decode((string)@file_get_contents($analysisFs), true);
    if (!is_array($analysis)) {
        return array(false, 'No se pudo leer el resultado JSON del worker local.');
    }

    return array(true, array(
        'worker_result' => $analysis,
        'worker_command' => $proc['command'],
        'analysis_json_path' => publicista_path_to_web($analysisFs),
        'square_path' => file_exists($squareFs) ? publicista_path_to_web($squareFs) : '',
        'face_blur_path' => '',
        'preview_path' => file_exists($previewFs) ? publicista_path_to_web($previewFs) : '',
    ));
}


function publicista_response_output_text($decoded) {
    if (!is_array($decoded)) return '';
    if (!empty($decoded['output_text']) && is_string($decoded['output_text'])) {
        return trim((string)$decoded['output_text']);
    }

    $parts = array();
    foreach ((array)($decoded['output'] ?? array()) as $item) {
        if (!is_array($item)) continue;
        foreach ((array)($item['content'] ?? array()) as $content) {
            if (!is_array($content)) continue;
            if (($content['type'] ?? '') === 'output_text' && isset($content['text'])) {
                $parts[] = (string)$content['text'];
            }
        }
    }
    return trim(implode("\n", $parts));
}

function publicista_openai_headers($cfg, $extraHeaders = array()) {
    $headers = array(
        'Authorization: Bearer ' . $cfg['api_key'],
        'Accept: application/json',
    );

    if (!empty($cfg['organization'])) {
        $headers[] = 'OpenAI-Organization: ' . $cfg['organization'];
    }
    if (!empty($cfg['project'])) {
        $headers[] = 'OpenAI-Project: ' . $cfg['project'];
    }

    foreach ($extraHeaders as $header) {
        $headers[] = $header;
    }

    return $headers;
}

function publicista_openai_retry_defaults() {
    return array(
        'max_attempts' => 5,
        'base_delay_ms' => 500,
        'max_delay_ms' => 15000,
        'jitter_ms' => 350,
        'retry_on_http' => array(429, 500, 502, 503, 504),
        'respect_retry_after' => true,
        'retry_on_curl_error' => true,
    );
}

function publicista_openai_is_retryable_http($httpCode, $retryOnHttp = array()) {
    return in_array((int)$httpCode, (array)$retryOnHttp, true);
}

function publicista_openai_is_retryable_curl_error($curlError) {
    $curlError = strtolower(trim((string)$curlError));
    if ($curlError === '') return false;
    $patterns = array(
        'timed out',
        'timeout',
        'could not resolve host',
        'failed to connect',
        'connection reset',
        'ssl connect error',
        'empty reply from server',
    );
    foreach ($patterns as $p) {
        if (strpos($curlError, $p) !== false) return true;
    }
    return false;
}

function publicista_openai_parse_retry_after_seconds($retryAfterRaw) {
    $raw = trim((string)$retryAfterRaw);
    if ($raw === '') return 0.0;

    if (ctype_digit($raw)) {
        return max(0.0, (float)((int)$raw));
    }

    $ts = strtotime($raw);
    if ($ts === false) return 0.0;
    $delta = $ts - time();
    return $delta > 0 ? (float)$delta : 0.0;
}

function publicista_openai_compute_backoff_seconds($attempt, $baseDelayMs, $maxDelayMs, $jitterMs) {
    $attempt = max(1, (int)$attempt);
    $expMs = (float)$baseDelayMs * pow(2, $attempt - 1);
    $jitter = (float)mt_rand(0, max(0, (int)$jitterMs));
    $totalMs = min((float)$maxDelayMs, $expMs + $jitter);
    return max(0.0, $totalMs / 1000.0);
}

function publicista_openai_sleep_seconds($seconds) {
    $seconds = (float)$seconds;
    if ($seconds <= 0) return;
    usleep((int)round($seconds * 1000000));
}

function publicista_openai_json_request($endpoint, $payload, $timeoutSec = 60, $retryOptions = array()) {
    $cfg = publicista_ai_config();
    $retry = array_merge(publicista_openai_retry_defaults(), is_array($retryOptions) ? $retryOptions : array());
    $result = array(
        'ok' => false,
        'endpoint' => $endpoint,
        'http_code' => 0,
        'request_id' => '',
        'decoded' => null,
        'raw_body' => '',
        'error' => '',
        'used_model' => isset($payload['model']) ? (string)$payload['model'] : '',
        'service_tier' => isset($payload['service_tier']) ? (string)$payload['service_tier'] : 'default',
        'attempts' => 0,
        'retry_history' => array(),
    );

    if (!$cfg['configured']) {
        $result['error'] = 'OPENAI_API_KEY no configurada para Publicista.';
        return $result;
    }
    if (!function_exists('curl_init')) {
        $result['error'] = 'curl_init no está disponible en PHP.';
        return $result;
    }

    $maxAttempts = max(1, (int)$retry['max_attempts']);
    $onRetry = (isset($retry['on_retry']) && is_callable($retry['on_retry'])) ? $retry['on_retry'] : null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $responseHeaders = array();
        $headers = publicista_openai_headers($cfg, array('Content-Type: application/json'));

        $ch = curl_init('https://api.openai.com' . $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$timeoutSec);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $headerLine) use (&$responseHeaders) {
            $len = strlen($headerLine);
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        });

        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result['attempts'] = $attempt;
        $result['http_code'] = $httpCode;
        $result['raw_body'] = (string)$body;
        $result['request_id'] = isset($responseHeaders['x-request-id']) ? $responseHeaders['x-request-id'] : '';
        $decoded = json_decode((string)$body, true);
        $result['decoded'] = $decoded;

        if ($curlError === '' && $httpCode >= 200 && $httpCode < 300) {
            $result['ok'] = true;
            $result['error'] = '';
            return $result;
        }

        if ($curlError !== '') {
            $result['error'] = 'curl_error:' . $curlError;
        } elseif (is_array($decoded) && !empty($decoded['error']['message'])) {
            $result['error'] = (string)$decoded['error']['message'];
        } else {
            $result['error'] = trim(substr((string)$body, 0, 500));
        }

        $retryableHttp = publicista_openai_is_retryable_http($httpCode, (array)$retry['retry_on_http']);
        $retryableCurl = !empty($retry['retry_on_curl_error']) && publicista_openai_is_retryable_curl_error($curlError);
        $shouldRetry = ($attempt < $maxAttempts) && ($retryableHttp || $retryableCurl);

        if (!$shouldRetry) {
            return $result;
        }

        $retryAfterSec = 0.0;
        if (!empty($retry['respect_retry_after'])) {
            $retryAfterSec = publicista_openai_parse_retry_after_seconds(isset($responseHeaders['retry-after']) ? $responseHeaders['retry-after'] : '');
        }
        $backoffSec = publicista_openai_compute_backoff_seconds(
            $attempt,
            (int)$retry['base_delay_ms'],
            (int)$retry['max_delay_ms'],
            (int)$retry['jitter_ms']
        );
        $waitSec = max($retryAfterSec, $backoffSec);

        $retryLog = array(
            'attempt' => $attempt,
            'max_attempts' => $maxAttempts,
            'http_code' => $httpCode,
            'request_id' => (string)$result['request_id'],
            'curl_error' => (string)$curlError,
            'error' => (string)$result['error'],
            'retry_after_raw' => isset($responseHeaders['retry-after']) ? (string)$responseHeaders['retry-after'] : '',
            'retry_after_sec' => $retryAfterSec,
            'backoff_sec' => $backoffSec,
            'wait_sec' => $waitSec,
        );
        $result['retry_history'][] = $retryLog;
        if ($onRetry) {
            call_user_func($onRetry, $retryLog);
        }
        publicista_openai_sleep_seconds($waitSec);
    }

    return $result;
}


function publicista_openai_get_request($endpoint, $timeoutSec = 60, $retryOptions = array()) {
    $cfg = publicista_ai_config();
    $retry = array_merge(publicista_openai_retry_defaults(), is_array($retryOptions) ? $retryOptions : array());
    $result = array(
        'ok' => false,
        'endpoint' => $endpoint,
        'http_code' => 0,
        'request_id' => '',
        'decoded' => null,
        'raw_body' => '',
        'error' => '',
        'attempts' => 0,
        'retry_history' => array(),
    );

    if (!$cfg['configured']) {
        $result['error'] = 'OPENAI_API_KEY no configurada para Publicista.';
        return $result;
    }
    if (!function_exists('curl_init')) {
        $result['error'] = 'curl_init no está disponible en PHP.';
        return $result;
    }

    $maxAttempts = max(1, (int)$retry['max_attempts']);
    $onRetry = (isset($retry['on_retry']) && is_callable($retry['on_retry'])) ? $retry['on_retry'] : null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $headers = publicista_openai_headers($cfg, array('Content-Type: application/json'));
        $responseHeaders = array();
        $ch = curl_init('https://api.openai.com' . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$timeoutSec);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $headerLine) use (&$responseHeaders) {
            $len = strlen($headerLine);
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        });
        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result['attempts'] = $attempt;
        $result['http_code'] = $httpCode;
        $result['raw_body'] = (string)$body;
        $result['request_id'] = isset($responseHeaders['x-request-id']) ? $responseHeaders['x-request-id'] : '';
        $decoded = json_decode((string)$body, true);
        $result['decoded'] = $decoded;

        if ($curlError === '' && $httpCode >= 200 && $httpCode < 300) {
            $result['ok'] = true;
            $result['error'] = '';
            return $result;
        }

        if ($curlError !== '') {
            $result['error'] = 'curl_error:' . $curlError;
        } elseif (is_array($decoded) && !empty($decoded['error']['message'])) {
            $result['error'] = (string)$decoded['error']['message'];
        } else {
            $result['error'] = trim(substr((string)$body, 0, 500));
        }

        $retryableHttp = publicista_openai_is_retryable_http($httpCode, (array)$retry['retry_on_http']);
        $retryableCurl = !empty($retry['retry_on_curl_error']) && publicista_openai_is_retryable_curl_error($curlError);
        $shouldRetry = ($attempt < $maxAttempts) && ($retryableHttp || $retryableCurl);
        if (!$shouldRetry) {
            return $result;
        }

        $retryAfterSec = 0.0;
        if (!empty($retry['respect_retry_after'])) {
            $retryAfterSec = publicista_openai_parse_retry_after_seconds(isset($responseHeaders['retry-after']) ? $responseHeaders['retry-after'] : '');
        }
        $backoffSec = publicista_openai_compute_backoff_seconds(
            $attempt,
            (int)$retry['base_delay_ms'],
            (int)$retry['max_delay_ms'],
            (int)$retry['jitter_ms']
        );
        $waitSec = max($retryAfterSec, $backoffSec);

        $retryLog = array(
            'attempt' => $attempt,
            'max_attempts' => $maxAttempts,
            'http_code' => $httpCode,
            'request_id' => (string)$result['request_id'],
            'curl_error' => (string)$curlError,
            'error' => (string)$result['error'],
            'retry_after_raw' => isset($responseHeaders['retry-after']) ? (string)$responseHeaders['retry-after'] : '',
            'retry_after_sec' => $retryAfterSec,
            'backoff_sec' => $backoffSec,
            'wait_sec' => $waitSec,
        );
        $result['retry_history'][] = $retryLog;
        if ($onRetry) {
            call_user_func($onRetry, $retryLog);
        }
        publicista_openai_sleep_seconds($waitSec);
    }

    return $result;
}

function publicista_openai_upload_batch_file($localFsPath, $filename = 'publicista_batch.jsonl', $retryOptions = array()) {
    $cfg = publicista_ai_config();
    $retry = array_merge(publicista_openai_retry_defaults(), is_array($retryOptions) ? $retryOptions : array());
    $result = array(
        'ok' => false,
        'endpoint' => '/v1/files',
        'http_code' => 0,
        'request_id' => '',
        'decoded' => null,
        'raw_body' => '',
        'error' => '',
        'attempts' => 0,
        'retry_history' => array(),
    );
    if (!$cfg['configured']) {
        $result['error'] = 'OPENAI_API_KEY no configurada para Publicista.';
        return $result;
    }
    if (!file_exists($localFsPath)) {
        $result['error'] = 'No existe el archivo local del batch.';
        return $result;
    }
    $maxAttempts = max(1, (int)$retry['max_attempts']);
    $onRetry = (isset($retry['on_retry']) && is_callable($retry['on_retry'])) ? $retry['on_retry'] : null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $headers = publicista_openai_headers($cfg);
        $responseHeaders = array();
        $fields = array(
            'purpose' => 'batch',
            'file' => curl_file_create($localFsPath, 'application/jsonl', $filename),
        );
        $ch = curl_init('https://api.openai.com/v1/files');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$cfg['timeouts']['images']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $headerLine) use (&$responseHeaders) {
            $len = strlen($headerLine);
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        });
        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result['attempts'] = $attempt;
        $result['http_code'] = $httpCode;
        $result['raw_body'] = (string)$body;
        $result['request_id'] = isset($responseHeaders['x-request-id']) ? $responseHeaders['x-request-id'] : '';
        $decoded = json_decode((string)$body, true);
        $result['decoded'] = $decoded;

        if ($curlError === '' && $httpCode >= 200 && $httpCode < 300) {
            $result['ok'] = true;
            $result['error'] = '';
            return $result;
        }

        if ($curlError !== '') {
            $result['error'] = 'curl_error:' . $curlError;
        } elseif (is_array($decoded) && !empty($decoded['error']['message'])) {
            $result['error'] = (string)$decoded['error']['message'];
        } else {
            $result['error'] = trim(substr((string)$body, 0, 500));
        }

        $retryableHttp = publicista_openai_is_retryable_http($httpCode, (array)$retry['retry_on_http']);
        $retryableCurl = !empty($retry['retry_on_curl_error']) && publicista_openai_is_retryable_curl_error($curlError);
        $shouldRetry = ($attempt < $maxAttempts) && ($retryableHttp || $retryableCurl);
        if (!$shouldRetry) {
            return $result;
        }

        $retryAfterSec = 0.0;
        if (!empty($retry['respect_retry_after'])) {
            $retryAfterSec = publicista_openai_parse_retry_after_seconds(isset($responseHeaders['retry-after']) ? $responseHeaders['retry-after'] : '');
        }
        $backoffSec = publicista_openai_compute_backoff_seconds(
            $attempt,
            (int)$retry['base_delay_ms'],
            (int)$retry['max_delay_ms'],
            (int)$retry['jitter_ms']
        );
        $waitSec = max($retryAfterSec, $backoffSec);

        $retryLog = array(
            'attempt' => $attempt,
            'max_attempts' => $maxAttempts,
            'http_code' => $httpCode,
            'request_id' => (string)$result['request_id'],
            'curl_error' => (string)$curlError,
            'error' => (string)$result['error'],
            'retry_after_raw' => isset($responseHeaders['retry-after']) ? (string)$responseHeaders['retry-after'] : '',
            'retry_after_sec' => $retryAfterSec,
            'backoff_sec' => $backoffSec,
            'wait_sec' => $waitSec,
        );
        $result['retry_history'][] = $retryLog;
        if ($onRetry) {
            call_user_func($onRetry, $retryLog);
        }
        publicista_openai_sleep_seconds($waitSec);
    }

    return $result;
}

function publicista_openai_create_batch($endpoint, $inputFileId, $metadata = array()) {
    $payload = array(
        'input_file_id' => $inputFileId,
        'endpoint' => $endpoint,
        'completion_window' => '24h',
    );
    if (!empty($metadata)) {
        $payload['metadata'] = $metadata;
    }
    return publicista_openai_json_request('/v1/batches', $payload, publicista_ai_config()['timeouts']['responses']);
}

function publicista_openai_retrieve_batch($batchId) {
    return publicista_openai_get_request('/v1/batches/' . rawurlencode((string)$batchId), publicista_ai_config()['timeouts']['responses']);
}

function publicista_openai_download_file_content($fileId, $retryOptions = array()) {
    $cfg = publicista_ai_config();
    $retry = array_merge(publicista_openai_retry_defaults(), is_array($retryOptions) ? $retryOptions : array());
    $result = array(
        'ok' => false,
        'endpoint' => '/v1/files/' . $fileId . '/content',
        'http_code' => 0,
        'request_id' => '',
        'content' => '',
        'error' => '',
        'attempts' => 0,
        'retry_history' => array(),
    );
    if (!$cfg['configured']) {
        $result['error'] = 'OPENAI_API_KEY no configurada para Publicista.';
        return $result;
    }
    $maxAttempts = max(1, (int)$retry['max_attempts']);
    $onRetry = (isset($retry['on_retry']) && is_callable($retry['on_retry'])) ? $retry['on_retry'] : null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $headers = publicista_openai_headers($cfg);
        $responseHeaders = array();
        $ch = curl_init('https://api.openai.com/v1/files/' . rawurlencode((string)$fileId) . '/content');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int)$cfg['timeouts']['images']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $headerLine) use (&$responseHeaders) {
            $len = strlen($headerLine);
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
            return $len;
        });
        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result['attempts'] = $attempt;
        $result['http_code'] = $httpCode;
        $result['content'] = (string)$body;
        $result['request_id'] = isset($responseHeaders['x-request-id']) ? $responseHeaders['x-request-id'] : '';

        if ($curlError === '' && $httpCode >= 200 && $httpCode < 300) {
            $result['ok'] = true;
            $result['error'] = '';
            return $result;
        }

        if ($curlError !== '') {
            $result['error'] = 'curl_error:' . $curlError;
        } else {
            $result['error'] = trim(substr((string)$body, 0, 500));
        }

        $retryableHttp = publicista_openai_is_retryable_http($httpCode, (array)$retry['retry_on_http']);
        $retryableCurl = !empty($retry['retry_on_curl_error']) && publicista_openai_is_retryable_curl_error($curlError);
        $shouldRetry = ($attempt < $maxAttempts) && ($retryableHttp || $retryableCurl);
        if (!$shouldRetry) {
            return $result;
        }

        $retryAfterSec = 0.0;
        if (!empty($retry['respect_retry_after'])) {
            $retryAfterSec = publicista_openai_parse_retry_after_seconds(isset($responseHeaders['retry-after']) ? $responseHeaders['retry-after'] : '');
        }
        $backoffSec = publicista_openai_compute_backoff_seconds(
            $attempt,
            (int)$retry['base_delay_ms'],
            (int)$retry['max_delay_ms'],
            (int)$retry['jitter_ms']
        );
        $waitSec = max($retryAfterSec, $backoffSec);

        $retryLog = array(
            'attempt' => $attempt,
            'max_attempts' => $maxAttempts,
            'http_code' => $httpCode,
            'request_id' => (string)$result['request_id'],
            'curl_error' => (string)$curlError,
            'error' => (string)$result['error'],
            'retry_after_raw' => isset($responseHeaders['retry-after']) ? (string)$responseHeaders['retry-after'] : '',
            'retry_after_sec' => $retryAfterSec,
            'backoff_sec' => $backoffSec,
            'wait_sec' => $waitSec,
        );
        $result['retry_history'][] = $retryLog;
        if ($onRetry) {
            call_user_func($onRetry, $retryLog);
        }
        publicista_openai_sleep_seconds($waitSec);
    }

    return $result;
}

function publicista_openai_image_generate($prompt, $options = array()) {
    $cfg = publicista_ai_config();
    $model = (string)($options['model'] ?? $cfg['image_model']);
    $isGptImage = publicista_is_gpt_image_model($model);

    $payload = array(
        'model' => $model,
        'prompt' => (string)$prompt,
        'size' => (string)($options['size'] ?? '1024x1024'),
        'quality' => (string)($options['quality'] ?? 'medium'),
        'n' => (int)($options['n'] ?? 1),
    );

    if (array_key_exists('response_format', $options)) {
        $payload['response_format'] = (string)$options['response_format'];
    } elseif (!$isGptImage) {
        $payload['response_format'] = 'b64_json';
    }

    if (!empty($options['background']) && $isGptImage) {
        $payload['background'] = (string)$options['background'];
    }
    if (!empty($options['output_format']) && $isGptImage) {
        $payload['output_format'] = (string)$options['output_format'];
    }

    return publicista_openai_json_request('/v1/images/generations', $payload, $cfg['timeouts']['images']);
}

function publicista_detect_image_mime_from_file($path) {
    $path = (string)$path;
    if ($path === '' || !file_exists($path)) return '';

    $mime = '';
    if (function_exists('mime_content_type')) {
        $mime = (string)@mime_content_type($path);
    }
    if ($mime === '' && function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = (string)@finfo_file($finfo, $path);
            @finfo_close($finfo);
        }
    }
    $mime = strtolower(trim((string)$mime));
    if ($mime !== '' && $mime !== 'application/octet-stream') {
        return $mime;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'png') return 'image/png';
    if ($ext === 'jpg' || $ext === 'jpeg') return 'image/jpeg';
    if ($ext === 'webp') return 'image/webp';

    $fh = @fopen($path, 'rb');
    if ($fh) {
        $head = (string)@fread($fh, 16);
        @fclose($fh);
        if (substr($head, 0, 8) === "\x89PNG\r\n\x1a\n") return 'image/png';
        if (substr($head, 0, 3) === "\xff\xd8\xff") return 'image/jpeg';
        if (substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP') return 'image/webp';
    }
    return '';
}

function publicista_convert_image_to_png_for_edit($inputFs) {
    $worker = BASE_PATH . '/tools/publicista_image_worker.py';
    if (!file_exists($worker)) {
        return array(false, 'No se encontró el worker Python de Publicista para convertir a PNG.');
    }

    $tmpBase = 'publicista_edit_' . uniqid('', true);
    $pngFs = rtrim((string)sys_get_temp_dir(), '/\\') . '/' . $tmpBase . '.png';
    $jsonFs = rtrim((string)sys_get_temp_dir(), '/\\') . '/' . $tmpBase . '.json';

    $command = 'python3 ' . escapeshellarg($worker)
        . ' export-png'
        . ' --input ' . escapeshellarg($inputFs)
        . ' --output-png ' . escapeshellarg($pngFs)
        . ' --output-json ' . escapeshellarg($jsonFs);

    $proc = publicista_proc_command($command, publicista_ai_timeouts()['local_worker'], BASE_PATH);
    if (!$proc['ok']) {
        @unlink($pngFs);
        @unlink($jsonFs);
        return array(false, 'Error convirtiendo imagen a PNG para edición: ' . ($proc['stderr'] !== '' ? $proc['stderr'] : 'sin detalle'));
    }
    if (!file_exists($pngFs)) {
        @unlink($jsonFs);
        return array(false, 'La conversión a PNG terminó pero no generó archivo PNG.');
    }
    @unlink($jsonFs);
    return array(true, array(
        'path' => $pngFs,
        'mime' => 'image/png',
        'cleanup' => array($pngFs),
    ));
}

function publicista_prepare_image_edit_upload($imagePath) {
    $imagePath = (string)$imagePath;
    if ($imagePath === '' || !file_exists($imagePath)) {
        return array(false, 'No existe la imagen de referencia para la edición.');
    }

    $mime = publicista_detect_image_mime_from_file($imagePath);
    if ($mime === 'image/png') {
        return array(true, array(
            'path' => $imagePath,
            'mime' => 'image/png',
            'cleanup' => array(),
        ));
    }

    if ($mime === 'image/jpeg' || $mime === 'image/jpg' || $mime === 'image/webp' || $mime === '' || $mime === 'application/octet-stream') {
        return publicista_convert_image_to_png_for_edit($imagePath);
    }

    return publicista_convert_image_to_png_for_edit($imagePath);
}

function publicista_build_image_edit_fields($prompt, $imagePath, $model, $options = array()) {
    $model = trim((string)$model);
    $isGptImage = publicista_is_gpt_image_model($model);
    list($okUpload, $uploadOrError) = publicista_prepare_image_edit_upload($imagePath);
    if (!$okUpload) {
        return array(false, $uploadOrError);
    }

    $fields = array(
        'model' => $model,
        'prompt' => (string)$prompt,
        'size' => (string)($options['size'] ?? '1024x1024'),
        'n' => (string)((int)($options['n'] ?? 1)),
        'image' => curl_file_create((string)$uploadOrError['path'], (string)$uploadOrError['mime'], basename((string)$uploadOrError['path'])),
    );

    if (array_key_exists('response_format', $options)) {
        $fields['response_format'] = (string)$options['response_format'];
    } elseif (!$isGptImage) {
        $fields['response_format'] = 'b64_json';
    }

    if (!empty($options['mask_path']) && file_exists($options['mask_path'])) {
        list($okMask, $maskOrError) = publicista_prepare_image_edit_upload((string)$options['mask_path']);
        if (!$okMask) {
            foreach ((array)($uploadOrError['cleanup'] ?? array()) as $tmp) {
                @unlink($tmp);
            }
            return array(false, $maskOrError);
        }
        $fields['mask'] = curl_file_create((string)$maskOrError['path'], (string)$maskOrError['mime'], basename((string)$maskOrError['path']));
        $uploadOrError['cleanup'] = array_merge((array)($uploadOrError['cleanup'] ?? array()), (array)($maskOrError['cleanup'] ?? array()));
    }

    return array(true, array(
        'fields' => $fields,
        'cleanup' => (array)($uploadOrError['cleanup'] ?? array()),
        'upload_path' => (string)$uploadOrError['path'],
        'upload_mime' => (string)$uploadOrError['mime'],
    ));
}

function publicista_should_retry_image_edit_with_dalle2($model, $httpCode, $decoded, $errorMessage) {
    $model = trim((string)$model);
    if ($model === '' || strtolower($model) === 'dall-e-2') {
        return false;
    }
    if ((int)$httpCode !== 400) {
        return false;
    }
    $message = trim((string)$errorMessage);
    if ($message === '' && is_array($decoded) && !empty($decoded['error']['message'])) {
        $message = trim((string)$decoded['error']['message']);
    }
    if ($message === '') {
        return false;
    }
    $messageLower = strtolower($message);
    return (strpos($messageLower, 'invalid value') !== false && strpos($messageLower, 'dall-e-2') !== false);
}

function publicista_openai_image_edit($prompt, $imagePath, $options = array()) {
    $cfg = publicista_ai_config();
    $requestedModel = trim((string)($options['model'] ?? $cfg['image_model']));
    $result = array(
        'ok' => false,
        'endpoint' => '/v1/images/edits',
        'http_code' => 0,
        'request_id' => '',
        'decoded' => null,
        'raw_body' => '',
        'error' => '',
        'model' => $requestedModel,
        'fallback_used' => false,
    );

    if (!$cfg['configured']) {
        $result['error'] = 'OPENAI_API_KEY no configurada para Publicista.';
        return $result;
    }
    if (!function_exists('curl_init')) {
        $result['error'] = 'curl_init no está disponible en PHP.';
        return $result;
    }
    if (!file_exists($imagePath)) {
        $result['error'] = 'No existe la imagen de referencia para la edición.';
        return $result;
    }

    $headers = publicista_openai_headers($cfg);
    static $editModelRuntimeFallbacks = array();
    $initialModel = isset($editModelRuntimeFallbacks[$requestedModel]) ? (string)$editModelRuntimeFallbacks[$requestedModel] : $requestedModel;

    $executeEdit = function($modelToUse) use ($cfg, $headers, $prompt, $imagePath, $options) {
        $retry = publicista_openai_retry_defaults();
        $maxAttempts = max(1, (int)($retry['max_attempts'] ?? 1));
        $response = array(
            'ok' => false,
            'http_code' => 0,
            'request_id' => '',
            'decoded' => null,
            'raw_body' => '',
            'error' => '',
            'model' => (string)$modelToUse,
            'upload_mime' => '',
            'upload_path' => '',
            'attempts' => 0,
            'retry_history' => array(),
        );

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $responseHeaders = array();
            list($okFields, $fieldsOrError) = publicista_build_image_edit_fields($prompt, $imagePath, $modelToUse, $options);
            if (!$okFields) {
                $response['error'] = (string)$fieldsOrError;
                return $response;
            }

            $response['upload_mime'] = (string)($fieldsOrError['upload_mime'] ?? '');
            $response['upload_path'] = (string)($fieldsOrError['upload_path'] ?? '');
            $fields = (array)($fieldsOrError['fields'] ?? array());
            $cleanup = (array)($fieldsOrError['cleanup'] ?? array());

            $ch = curl_init('https://api.openai.com/v1/images/edits');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, (int)$cfg['timeouts']['images']);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $headerLine) use (&$responseHeaders) {
                $len = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $len;
            });

            $body = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            foreach ($cleanup as $tmp) {
                @unlink($tmp);
            }

            $response['attempts'] = $attempt;
            $response['http_code'] = $httpCode;
            $response['raw_body'] = (string)$body;
            $response['request_id'] = isset($responseHeaders['x-request-id']) ? $responseHeaders['x-request-id'] : '';
            $decoded = json_decode((string)$body, true);
            $response['decoded'] = $decoded;

            if ($curlError === '' && $httpCode >= 200 && $httpCode < 300) {
                $response['ok'] = true;
                $response['error'] = '';
                return $response;
            }

            if ($curlError !== '') {
                $response['error'] = 'curl_error:' . $curlError;
            } elseif (is_array($decoded) && !empty($decoded['error']['message'])) {
                $response['error'] = (string)$decoded['error']['message'];
            } else {
                $response['error'] = trim(substr((string)$body, 0, 500));
            }

            $retryableHttp = publicista_openai_is_retryable_http($httpCode, (array)($retry['retry_on_http'] ?? array()));
            $retryableCurl = !empty($retry['retry_on_curl_error']) && publicista_openai_is_retryable_curl_error($curlError);
            $shouldRetry = ($attempt < $maxAttempts) && ($retryableHttp || $retryableCurl);
            if (!$shouldRetry) {
                return $response;
            }

            $retryAfterSec = 0.0;
            if (!empty($retry['respect_retry_after'])) {
                $retryAfterSec = publicista_openai_parse_retry_after_seconds(isset($responseHeaders['retry-after']) ? $responseHeaders['retry-after'] : '');
            }
            $backoffSec = publicista_openai_compute_backoff_seconds(
                $attempt,
                (int)($retry['base_delay_ms'] ?? 500),
                (int)($retry['max_delay_ms'] ?? 15000),
                (int)($retry['jitter_ms'] ?? 350)
            );
            $waitSec = max($retryAfterSec, $backoffSec);

            $response['retry_history'][] = array(
                'attempt' => $attempt,
                'max_attempts' => $maxAttempts,
                'http_code' => $httpCode,
                'request_id' => (string)$response['request_id'],
                'curl_error' => (string)$curlError,
                'error' => (string)$response['error'],
                'retry_after_raw' => isset($responseHeaders['retry-after']) ? (string)$responseHeaders['retry-after'] : '',
                'retry_after_sec' => $retryAfterSec,
                'backoff_sec' => $backoffSec,
                'wait_sec' => $waitSec,
            );
            publicista_openai_sleep_seconds($waitSec);
        }

        return $response;
    };

    $firstTry = $executeEdit($initialModel);
    if ($firstTry['ok']) {
        if ($initialModel !== $requestedModel) {
            $result['fallback_used'] = true;
        }
        return array_merge($result, $firstTry, array('model' => (string)$firstTry['model']));
    }

    if ($initialModel !== $requestedModel) {
        return array_merge($result, $firstTry, array(
            'model' => (string)$firstTry['model'],
            'fallback_used' => true,
        ));
    }
    if (publicista_should_retry_image_edit_with_dalle2($requestedModel, (int)$firstTry['http_code'], $firstTry['decoded'], (string)$firstTry['error'])) {
        $editModelRuntimeFallbacks[$requestedModel] = 'dall-e-2';
        $fallbackTry = $executeEdit('dall-e-2');
        if ($fallbackTry['ok']) {
            return array_merge($result, $fallbackTry, array(
                'model' => 'dall-e-2',
                'fallback_used' => true,
            ));
        }
        return array_merge($result, $fallbackTry, array(
            'model' => 'dall-e-2',
            'fallback_used' => true,
        ));
    }

    return array_merge($result, $firstTry, array('model' => (string)$firstTry['model']));
}


function publicista_openai_download_public_file_bytes($url) {
    $url = trim((string)$url);
    if ($url === '' || !function_exists('curl_init')) {
        return array(false, 'URL vacía o curl no disponible.');
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, publicista_ai_timeouts()['images']);
    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($curlError !== '') {
        return array(false, 'curl_error:' . $curlError);
    }
    if ($httpCode < 200 || $httpCode >= 300 || $body === false || $body === '') {
        return array(false, 'No se pudo descargar la imagen remota.');
    }
    return array(true, (string)$body);
}

function publicista_decode_openai_image_bytes($decoded) {
    if (!is_array($decoded) || empty($decoded['data']) || !is_array($decoded['data'])) {
        return array(false, 'Respuesta de imagen vacía.');
    }
    $first = $decoded['data'][0] ?? null;
    if (!is_array($first)) {
        return array(false, 'Respuesta de imagen sin primer elemento válido.');
    }
    if (!empty($first['b64_json'])) {
        $bytes = base64_decode((string)$first['b64_json'], true);
        if ($bytes === false || $bytes === '') {
            return array(false, 'No se pudo decodificar la imagen generada.');
        }
        return array(true, $bytes);
    }
    if (!empty($first['url'])) {
        return publicista_openai_download_public_file_bytes((string)$first['url']);
    }
    return array(false, 'La respuesta de imagen no incluye b64_json ni url.');
}

function publicista_build_reference_locked_prompt($job, $variantPrompt) {
    $variantPrompt = trim((string)$variantPrompt);
    $outfitLock = publicista_build_outfit_session_lock($job);
    $requirements = array(
        'USA LA IMAGEN ADJUNTA COMO REFERENCIA VISUAL DIRECTA Y FUERTE para mantener el mismo rostro, complexión, proporciones corporales y presencia general de la mujer.',
        'Mantén la identidad visual muy cercana a la referencia: evita drift facial, evita cambiar peso, evita cambiar forma de cara, hombros, pecho o cintura de manera notable.',
        'La salida debe ser una fotografía 1:1 cuadrada, hiperrealista y nítida.',
        'PROHIBIDO deformar, estirar, ensanchar o aplastar la foto original. Si falta aire alrededor, completa mediante EXTENSIÓN REALISTA DEL FONDO.',
        'El fondo añadido debe continuar de forma natural el entorno existente: paredes, puertas, suelo, muebles, paisaje, perspectiva, líneas e iluminación coherentes.',
        'PROHIBIDO rellenar con blur, reflejos espejados, bordes duplicados, degradados, viñeteados, fondos falsos o difuminados de relleno.',
        'La mujer debe ocupar aproximadamente el 80% de la imagen y quedar protagonista, con encuadre limpio y dominante. Solo usa formato selfie o primer plano muy cercano cuando la variante concreta lo pida; no conviertas todas las imágenes en selfie.',
        'Toda la producción debe parecer una única sesión del mismo día con EXACTAMENTE el mismo vestuario. No cambies escote, mangas, largo, tejido, zapatos ni complementos entre tomas.',
        'Si el encuadre no enseña toda la ropa, asume igualmente que fuera de cuadro sigue siendo exactamente la misma prenda y los mismos complementos.',
        'Evita recortes incómodos y cualquier artefacto: manos extra, dedos raros, objetos derretidos, fondos torcidos, dobles caras, anatomía incorrecta.',
        'Mejora de forma natural la luz en rostro y cuerpo, contraste suave, color atractivo pero realista, piel natural y pelo definido. Si aplicas desenfoque de fondo, que sea muy sutil y el entorno siga siendo reconocible.',
    );
    if (!empty($outfitLock['negative_block'])) {
        $requirements[] = $outfitLock['negative_block'];
    }
    $requirements = array_merge($requirements, publicista_visual_safety_lines());
    return trim($variantPrompt . "\n\n[REQUISITOS TÉCNICOS OBLIGATORIOS]\n- " . implode("\n- ", $requirements));
}

function publicista_build_final_refine_prompt($job, $candidate) {
    $candidatePrompt = trim((string)publicista_array_get($candidate, 'prompt', ''));
    $outfitLock = publicista_build_outfit_session_lock($job);
    $lines = array(
        'Refina esta MISMA imagen candidata sin cambiar la identidad, la complexión ni el outfit ya conseguido.',
        'Mantén un único sujeto y un único rostro claramente definido.',
        'Pulido premium: más nitidez limpia, textura de piel natural, pelo mejor definido, ropa con mejor detalle, exposición mejor equilibrada y contraste suave.',
        'Mantén la composición cuadrada 1:1 sin estirar nada. Si ves bordes débiles o fondo insuficiente, completa el fondo con extensión realista y coherente.',
        'La persona debe seguir dominando la escena, muy protagonista y bien encuadrada, aproximadamente el 80% visual del cuadro.',
        'No cambies la ropa, no cambies la pose base, no cambies el fondo a otro distinto, no añadas objetos nuevos salvo lo estrictamente necesario para cerrar el fondo de forma natural.',
        'La imagen final debe seguir pareciendo parte de la misma sesión de fotos que el resto de finales, con exactamente la misma prenda y los mismos complementos si son visibles.',
        'No blur artificial de relleno, no fondos falsos, no efecto plástico, no artefactos de manos ni anatomía.',
    );
    if (!empty($outfitLock['strict_summary'])) {
        $lines[] = 'Outfit de sesión bloqueado que debe mantenerse exactamente igual: ' . $outfitLock['strict_summary'];
    }
    if (!empty($outfitLock['negative_block'])) {
        $lines[] = $outfitLock['negative_block'];
    }
    if ($candidatePrompt !== '') {
        $lines[] = 'Respeta también estas restricciones creativas originales: ' . $candidatePrompt;
    }
    foreach (publicista_visual_safety_lines() as $safeLine) {
        $lines[] = $safeLine;
    }
    return implode("\n", $lines);
}


function publicista_build_pollo_final_refine_prompt($job, $candidate) {
    $candidatePrompt = trim((string)publicista_array_get($candidate, 'prompt', ''));
    $outfitLock = publicista_build_outfit_session_lock($job);
    $lines = array(
        'Crea una PROPUESTA REFINADA claramente distinta de la candidata base, como una segunda foto mejor de la misma sesión.',
        'Debe seguir siendo exactamente la misma mujer adulta: mismo rostro, misma identidad visual, mismo peinado, mismo color de pelo, misma complexión, mismo outfit y mismos complementos.',
        'NO devuelvas una copia casi idéntica. Haz cambios visibles pero coherentes: reencuadre, ángulo, gesto, caída del cabello, micro-pose y lenguaje corporal, siempre manteniendo continuidad total de sesión.',
        'Sube mucho el nivel visual: más premium, más impactante, más editorial, más presencia, más detalle real en ojos, labios, piel, cabello, tejido, costuras y accesorios.',
        'Acerca el plano de forma natural o pasa a un tres cuartos más favorecedor si mejora la imagen, sin cortes torpes ni recortes raros de manos, pies o cabeza.',
        'La pose debe verse más viva y más fotogénica que en la candidata base: mejor postura, más intención corporal, más gancho comercial y menos rigidez.',
        'Mantén el fondo en la misma línea, pero mejorado: más limpio, más profundo, mejor resuelto y más coherente con una sesión comercial premium.',
        'No cambies la ropa ni los complementos. No simplifiques el vestuario, no cambies colores, no cambies textura y no lo conviertas en un look plano o soso.',
        'Una sola mujer por imagen, una sola escena, una sola sesión. Resultado hiperrealista, comercial y claramente fotográfico.',
        'Evita artefactos: manos raras, dedos extra, rostro duplicado, fondo roto, piel plástica, texto, watermark, collage, anatomía incorrecta o mirada vacía.',
        'Debe sentirse como una versión claramente mejorada y más llamativa que la candidata, no como el mismo archivo rehecho casi igual.',
    );
    if (!empty($outfitLock['strict_summary'])) {
        $lines[] = 'Outfit de sesión que debe mantenerse exactamente igual: ' . $outfitLock['strict_summary'];
    }
    if (!empty($outfitLock['negative_block'])) {
        $lines[] = $outfitLock['negative_block'];
    }
    if ($candidatePrompt !== '') {
        $lines[] = 'Respeta también estas claves visuales originales: ' . $candidatePrompt;
    }
    $lines[] = 'Sexy y llamativa sí, pero sin desnudo, sin lencería visible, sin transparencia íntima y sin erotismo explícito.';
    return implode("
", array_values(array_filter($lines, function($line) {
        return trim((string)$line) !== '';
    })));
}

function publicista_generate_candidate_image_from_reference($jobId, $job, $candidateIndex, $prompt, $referenceImageFs) {
    $candidateSafe = str_pad((string)$candidateIndex, 2, '0', STR_PAD_LEFT);
    $attemptPrompts = array(trim((string)$prompt));
    $maxAttempts = 3;
    $lastResponse = null;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $currentPrompt = trim((string)($attemptPrompts[$attempt - 1] ?? ''));
        if ($currentPrompt === '') {
            $currentPrompt = trim((string)$prompt);
        }

        $response = publicista_openai_image_edit($currentPrompt, $referenceImageFs, array(
            'size' => '1024x1024',
            'n' => 1,
        ));
        $lastResponse = $response;
        $logPayload = $response;
        $logPayload['attempt'] = $attempt;
        $logPayload['prompt_used'] = $currentPrompt;
        if (!empty($logPayload['raw_body']) && strlen($logPayload['raw_body']) > 150000) {
            $logPayload['raw_body'] = substr($logPayload['raw_body'], 0, 150000) . "
...truncado...";
        }
        publicista_job_log_write($jobId, 'image_edit_reference_' . $candidateSafe . '_try' . $attempt, $logPayload);

        if ($response['ok']) {
            list($okBytes, $bytesOrError) = publicista_decode_openai_image_bytes($response['decoded']);
            if (!$okBytes) {
                return array(false, $bytesOrError);
            }
            $ext = publicista_guess_extension_from_binary($bytesOrError);
            $paths = publicista_job_fs_paths($jobId);
            $rawFs = $paths['candidates_dir'] . '/candidate_' . $candidateSafe . '_raw.' . $ext;
            list($okWrite, $webPathOrError) = publicista_write_binary_file($rawFs, $bytesOrError);
            if (!$okWrite) {
                return array(false, $webPathOrError);
            }
            publicista_register_image_generation_cost($jobId, (string)publicista_array_get($response, 'model', publicista_ai_config()['image_model']), 'medium', '1024x1024', 1);
            return array(true, array(
                'raw_path' => $webPathOrError,
                'request_id' => (string)publicista_array_get($response, 'request_id', ''),
                'http_code' => (int)publicista_array_get($response, 'http_code', 0),
                'model' => (string)publicista_array_get($response, 'model', publicista_ai_config()['image_model']),
                'raw_fs_path' => $rawFs,
                'prompt' => $currentPrompt,
                'attempts' => $attempt,
                'retry_applied' => $attempt > 1,
            ));
        }

        $errorMessage = (string)($response['error'] !== '' ? $response['error'] : 'sin detalle');
        if ($attempt < $maxAttempts && publicista_is_sexual_safety_rejection($errorMessage, $response['decoded'])) {
            $attemptPrompts[$attempt] = publicista_make_prompt_safer_for_retry($currentPrompt, $attempt);
            continue;
        }

        return array(false, 'Error generando candidata ' . $candidateIndex . ' con referencia real: ' . $errorMessage);
    }

    $lastError = is_array($lastResponse) ? (string)($lastResponse['error'] ?? 'sin detalle') : 'sin detalle';
    return array(false, 'Error generando candidata ' . $candidateIndex . ' con referencia real: ' . $lastError);
}

function publicista_refine_final_image($jobId, $job, $candidate, $finalIndex, $promptOverride = '') {
    $candidateSquareRel = trim((string)publicista_array_get($candidate, 'square_path', ''));
    if ($candidateSquareRel === '') {
        return array(false, 'La candidata no tiene square_path para refinar la final.');
    }
    $candidateSquareFs = BASE_PATH . '/' . ltrim($candidateSquareRel, '/');
    if (!file_exists($candidateSquareFs)) {
        return array(false, 'No existe en disco la imagen square de la candidata a refinar.');
    }

    $prompt = trim((string)$promptOverride);
    if ($prompt === '') {
        $prompt = publicista_build_final_refine_prompt($job, $candidate);
    }
    $response = publicista_openai_image_edit($prompt, $candidateSquareFs, array(
        'size' => '1024x1024',
        'n' => 1,
    ));
    $logPayload = $response;
    if (!empty($logPayload['raw_body']) && strlen($logPayload['raw_body']) > 150000) {
        $logPayload['raw_body'] = substr($logPayload['raw_body'], 0, 150000) . "\n...truncado...";
    }
    $safeIdx = str_pad((string)$finalIndex, 2, '0', STR_PAD_LEFT);
    publicista_job_log_write($jobId, 'final_refine_' . $safeIdx, $logPayload);
    if (!$response['ok']) {
        return array(false, 'No se pudo refinar la final ' . $safeIdx . ': ' . ($response['error'] !== '' ? $response['error'] : 'sin detalle'));
    }
    list($okBytes, $bytesOrError) = publicista_decode_openai_image_bytes($response['decoded']);
    if (!$okBytes) {
        return array(false, $bytesOrError);
    }
    $ext = publicista_guess_extension_from_binary($bytesOrError);
    $paths = publicista_job_fs_paths($jobId);
    $rawFs = $paths['finals_dir'] . '/final_' . $safeIdx . '_raw.' . $ext;
    list($okWrite, $webPathOrError) = publicista_write_binary_file($rawFs, $bytesOrError);
    if (!$okWrite) {
        return array(false, $webPathOrError);
    }
    publicista_register_image_generation_cost($jobId, (string)publicista_array_get($response, 'model', publicista_ai_config()['image_model']), 'medium', '1024x1024', 1);
    list($okLocal, $localOrError) = publicista_prepare_arbitrary_image_locally($jobId, $rawFs, 'final_' . $safeIdx, 'finals_dir');
    if (!$okLocal) {
        return array(false, $localOrError);
    }
    return array(true, array(
        'raw_path' => $webPathOrError,
        'square_path' => $localOrError['square_path'],
        'preview_path' => $localOrError['preview_path'],
        'analysis_json_path' => $localOrError['analysis_json_path'],
        'worker_result' => $localOrError['worker_result'],
        'prompt' => $prompt,
        'request_id' => (string)publicista_array_get($response, 'request_id', ''),
        'http_code' => (int)publicista_array_get($response, 'http_code', 0),
        'model' => (string)publicista_array_get($response, 'model', publicista_ai_config()['image_model']),
    ));
}

function publicista_descriptor_schema() {
    return array(
        'type' => 'json_schema',
        'name' => 'publicista_source_descriptor',
        'strict' => true,
        'schema' => array(
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array(
                'adult_appearing', 'framing', 'skin_tone', 'hair_color', 'hair_texture', 'hair_length',
                'body_build', 'body_curves', 'face_shape', 'eyes', 'lips', 'nose', 'eyebrows', 'makeup', 'outfit_summary',
                'dominant_colors', 'accessories', 'pose_summary', 'expression', 'background_summary',
                'lighting_summary', 'distinguishing_features', 'similarity_guidance', 'risk_notes', 'quality_notes'
            ),
            'properties' => array(
                'adult_appearing' => array('type' => 'boolean'),
                'framing' => array('type' => 'string'),
                'skin_tone' => array('type' => 'string'),
                'hair_color' => array('type' => 'string'),
                'hair_texture' => array('type' => 'string'),
                'hair_length' => array('type' => 'string'),
                'body_build' => array('type' => 'string'),
                'body_curves' => array('type' => 'string'),
                'face_shape' => array('type' => 'string'),
                'eyes' => array('type' => 'string'),
                'lips' => array('type' => 'string'),
                'nose' => array('type' => 'string'),
                'eyebrows' => array('type' => 'string'),
                'makeup' => array('type' => 'string'),
                'outfit_summary' => array('type' => 'string'),
                'dominant_colors' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                ),
                'accessories' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                ),
                'pose_summary' => array('type' => 'string'),
                'expression' => array('type' => 'string'),
                'background_summary' => array('type' => 'string'),
                'lighting_summary' => array('type' => 'string'),
                'distinguishing_features' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                ),
                'similarity_guidance' => array('type' => 'string'),
                'risk_notes' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                ),
                'quality_notes' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                ),
            ),
        ),
    );
}

function publicista_descriptor_instructions($job) {
    $services = trim((string)($job['services_snapshot'] ?? ''));
    $location = trim((string)(($job['localidad_snapshot'] ?? '') . ' ' . ($job['provincia_snapshot'] ?? '')));

    return "Analiza la fotografía y devuelve SOLO un JSON estricto con rasgos visuales útiles para recrear una persona parecida, no idéntica. "
        . "PRIORIDAD ABSOLUTA: describe con la mayor fidelidad posible la APARIENCIA FÍSICA real de la mujer (rostro, piel, cabello, complexión, silueta y rasgos distintivos permanentes). "
        . "La ropa, el maquillaje, la pose, el fondo y la iluminación sí deben describirse, pero siempre como información secundaria y nunca deben contaminar los campos de parecido físico. "
        . "No inventes datos que no se vean. "
        . "No identifiques a la persona ni des por hecho nombre, nacionalidad real o edad exacta; solo si aparenta ser adulta. "
        . "En body_build describe la complexión general (delgada/normal/voluptuosa/etc) con fidelidad visual, sin embellecerla ni adelgazarla o engordarla artificialmente. "
        . "En body_curves describe con detalle objetivo las curvas visibles: tamaño aproximado del pecho (pequeño/mediano/grande/muy grande), prominencia de caderas, relación cintura-cadera, forma general de la silueta y presencia corporal, de manera que luego pueda recrearse el mismo tipo físico. "
        . "En face_shape, eyes, lips, nose y eyebrows sé concreto y visual. "
        . "En similarity_guidance resume en 1 frase cómo mantener parecido general sin replicar identidad exacta — NO menciones ropa, outfit ni vestuario aquí. "
        . "En distinguishing_features lista SOLO rasgos físicos permanentes y muy reconocibles (tono de piel, color/textura/largo de cabello, estructura facial, ojos, labios, nariz, cejas, silueta, rasgos distintivos, etc.) — NO incluyas ropa, accesorios, maquillaje ni estilo de vestimenta porque en la imagen generada la ropa será diferente. "
        . "En outfit_summary limítate a un resumen corto y descriptivo de la ropa actual, sin convertirlo en el foco principal del análisis. "
        . ($services !== '' ? "Servicios asociados al pack: {$services}. " : '')
        . ($location !== '' ? "Contexto comercial/localidad: {$location}. " : '');
}

function publicista_describe_source_with_openai($jobId) {
    $job = publicista_job_get($jobId);
    if (!$job) {
        return array(false, 'No se encontró el trabajo de Publicista.');
    }

    $cfg = publicista_ai_config();
    if (!$cfg['configured']) {
        return array(false, 'OpenAI no está configurado. Revisa la API key en Josue > Config o en OPENAI_API_KEY.');
    }

    $sourceRel = trim((string)($job['source_image']['stored_path'] ?? ''));
    if ($sourceRel === '') {
        return array(false, 'El trabajo no tiene imagen original todavía.');
    }

    $sourceFs = BASE_PATH . '/' . ltrim($sourceRel, '/');
    if (!file_exists($sourceFs)) {
        return array(false, 'La imagen original no existe en disco: ' . $sourceFs);
    }

    $mime = trim((string)($job['source_image']['mime_type'] ?? ''));
    if ($mime === '') {
        $mime = function_exists('mime_content_type') ? (string)mime_content_type($sourceFs) : 'image/jpeg';
    }

    $base64Image = base64_encode((string)@file_get_contents($sourceFs));
    if ($base64Image === '') {
        return array(false, 'No se pudo leer la imagen original para enviarla a OpenAI.');
    }

    $payload = array_merge(publicista_response_payload_defaults('descriptor', $cfg['descriptor_model']), array(
        'model' => $cfg['descriptor_model'],
        'store' => false,
        'input' => array(
            array(
                'role' => 'system',
                'content' => publicista_descriptor_instructions($job),
            ),
            array(
                'role' => 'user',
                'content' => array(
                    array(
                        'type' => 'input_text',
                        'text' => 'Devuelve el descriptor visual estructurado de esta imagen.',
                    ),
                    array(
                        'type' => 'input_image',
                        'image_url' => 'data:' . $mime . ';base64,' . $base64Image,
                    ),
                ),
            ),
        ),
        'text' => array(
            'format' => publicista_descriptor_schema(),
        ),
    ));

    $response = publicista_openai_json_request('/v1/responses', $payload, $cfg['timeouts']['responses'], array(
        'on_retry' => function($retryLog) use ($jobId) {
            publicista_job_log_write($jobId, 'openai_descriptor_retry', array(
                'ts' => now_datetime(),
                'phase' => 'descriptor_pipeline',
                'retry' => $retryLog,
            ));
        },
    ));

    $rawToStore = $response;
    if (!empty($rawToStore['raw_body']) && strlen($rawToStore['raw_body']) > 150000) {
        $rawToStore['raw_body'] = substr($rawToStore['raw_body'], 0, 150000) . "\n...truncado...";
    }
    publicista_job_log_write($jobId, 'openai_descriptor', $rawToStore);

    if (!$response['ok']) {
        return array(false, 'OpenAI descriptor falló: ' . ($response['error'] !== '' ? $response['error'] : 'sin detalle'));
    }

    $outputText = publicista_response_output_text($response['decoded']);
    if ($outputText === '') {
        return array(false, 'OpenAI respondió sin output_text utilizable para el descriptor.');
    }

    $parsed = json_decode($outputText, true);
    if (!is_array($parsed)) {
        return array(false, 'OpenAI devolvió texto, pero no se pudo parsear como JSON descriptor.');
    }

    list($metaOk1, $rawPath) = publicista_job_meta_write($jobId, 'openai_descriptor_raw.json', $response['decoded']);
    list($metaOk2, $parsedPath) = publicista_job_meta_write($jobId, 'openai_descriptor_parsed.json', $parsed);
    publicista_register_response_cost($jobId, $response, 'descriptor');

    return array(true, array(
        'model' => $cfg['descriptor_model'],
        'request_id' => (string)($response['request_id'] ?? ''),
        'http_code' => (int)($response['http_code'] ?? 0),
        'raw_response_path' => $metaOk1 ? $rawPath : '',
        'parsed_json_path' => $metaOk2 ? $parsedPath : '',
        'data' => $parsed,
        'summary' => trim((string)($parsed['similarity_guidance'] ?? '')),
    ));
}

function publicista_mark_job_processing($job, $status, $patch = array()) {
    $row = is_array($job) ? $job : array();
    $row['estado'] = $status;
    if (!isset($row['processing']) || !is_array($row['processing'])) {
        $row['processing'] = publicista_job_defaults($row['id'] ?? '')['processing'];
    }
    $row['processing'] = array_merge($row['processing'], $patch);
    return publicista_job_save($row);
}

function publicista_prepare_job_engine($jobId, $uploadedFile = null) {
    $job = publicista_job_get($jobId);
    if (!$job) {
        return array(false, 'No se encontró el trabajo de Publicista.');
    }

    if (is_array($uploadedFile) && !empty($uploadedFile['tmp_name'])) {
        list($okUpload, $savedUpload) = publicista_attach_uploaded_source_image($jobId, $uploadedFile);
        if (!$okUpload) {
            return array(false, $savedUpload);
        }
        $job = publicista_job_get($jobId);
        $workflow = publicista_job_workflow($job);
        $workflow['pack_final'] = 0;
        $workflow['pack_finalized_at'] = '';
        $workflow['pack_final_note'] = '';
        $job['workflow'] = $workflow;
        publicista_job_save($job);
        $job = publicista_job_get($jobId);
    }

    $sourceRel = trim((string)($job['source_image']['stored_path'] ?? ''));
    if ($sourceRel === '') {
        return array(false, 'Primero sube la imagen original de la clienta.');
    }

    $startedAt = now_datetime();
    publicista_mark_job_processing($job, 'processing', array(
        'last_action' => 'prepare_engine',
        'last_started_at' => $startedAt,
        'last_finished_at' => '',
        'last_error' => '',
        'last_error_at' => '',
    ));

    $job = publicista_job_get($jobId);
    $local = publicista_prepare_source_locally($jobId);
    if (!$local[0]) {
        publicista_mark_job_processing($job, 'error', array(
            'last_action' => 'prepare_engine',
            'last_finished_at' => now_datetime(),
            'last_error' => $local[1],
            'last_error_at' => now_datetime(),
        ));
        return $local;
    }

    $job = publicista_job_get($jobId);
    $descriptor = publicista_describe_source_with_openai($jobId);
    if (!$descriptor[0]) {
        $localData = $local[1];
        $job['local_assets'] = array_merge($job['local_assets'] ?? array(), array(
            'analysis_json_path' => $localData['analysis_json_path'] ?? '',
            'prepared_square_path' => $localData['square_path'] ?? '',
            'face_blur_path' => $localData['face_blur_path'] ?? '',
            'preview_path' => $localData['preview_path'] ?? '',
            'worker_result' => $localData['worker_result'] ?? array(),
            'worker_command' => $localData['worker_command'] ?? '',
            'prepared_at' => now_datetime(),
        ));
        $job['processing'] = array_merge($job['processing'] ?? array(), array(
            'last_action' => 'prepare_engine',
            'last_finished_at' => now_datetime(),
            'last_error' => $descriptor[1],
            'last_error_at' => now_datetime(),
        ));
        $job['estado'] = 'error';
        publicista_job_save($job);
        return $descriptor;
    }

    $localData = $local[1];
    $descriptorData = $descriptor[1];
    $job = publicista_job_get($jobId);
    $job['local_assets'] = array_merge($job['local_assets'] ?? array(), array(
        'analysis_json_path' => $localData['analysis_json_path'] ?? '',
        'prepared_square_path' => $localData['square_path'] ?? '',
        'face_blur_path' => $localData['face_blur_path'] ?? '',
        'preview_path' => $localData['preview_path'] ?? '',
        'worker_result' => $localData['worker_result'] ?? array(),
        'worker_command' => $localData['worker_command'] ?? '',
        'prepared_at' => now_datetime(),
    ));
    $job['descriptor'] = array_merge($job['descriptor'] ?? array(), $descriptorData);
    // Preservar modelo Pollo si ya estaba seleccionado antes de preparar
    $existingImgModel = trim((string)(($job['models'] ?? array())['image'] ?? ''));
    $finalImgModel = (function_exists('publicista_is_pollo_model') && publicista_is_pollo_model($existingImgModel))
        ? $existingImgModel
        : publicista_ai_config()['image_model'];
    $job['models'] = array_merge($job['models'] ?? array(), array(
        'descriptor' => $descriptorData['model'] ?? publicista_ai_config()['descriptor_model'],
        'image' => $finalImgModel,
    ));
    $job['processing'] = array_merge($job['processing'] ?? array(), array(
        'last_action' => 'prepare_engine',
        'last_finished_at' => now_datetime(),
        'last_error' => '',
        'last_error_at' => '',
        'last_openai_request_id' => $descriptorData['request_id'] ?? '',
        'last_openai_http_code' => $descriptorData['http_code'] ?? 0,
    ));
    $job['estado'] = 'needs_review';

    list($ok, $saved) = publicista_job_save($job);
    if (!$ok) {
        return array(false, is_string($saved) ? $saved : 'No se pudo guardar el resultado del job de Publicista.');
    }

    return array(true, $saved);
}

function publicista_array_get($arr, $key, $default = null) {
    return (is_array($arr) && array_key_exists($key, $arr)) ? $arr[$key] : $default;
}

function publicista_job_workflow($job) {
    $workflow = is_array(publicista_array_get($job, 'workflow', array())) ? publicista_array_get($job, 'workflow', array()) : array();
    $workflow['restriction_flags'] = publicista_normalize_restriction_flags(publicista_array_get($workflow, 'restriction_flags', array()));
    $workflow['auto_regenerate'] = !empty($workflow['auto_regenerate']) ? 1 : 0;
    $workflow['pack_final'] = !empty($workflow['pack_final']) ? 1 : 0;
    return $workflow;
}

function publicista_compose_restrictions_summary($job) {
    $workflow = publicista_job_workflow($job);
    $parts = publicista_restriction_labels(publicista_array_get($workflow, 'restriction_flags', array()));
    $text = trim((string)publicista_array_get($workflow, 'restrictions_text', ''));
    if ($text !== '') $parts[] = $text;
    return trim(implode('. ', array_filter($parts, function($x){ return trim((string)$x) !== ''; })));
}

function publicista_write_binary_file($fsPath, $bytes) {
    $dir = dirname($fsPath);
    if (!publicista_ensure_dir($dir)) {
        return array(false, 'No se pudo crear la carpeta para escribir binario.');
    }
    if (@file_put_contents($fsPath, $bytes) === false) {
        return array(false, 'No se pudo escribir el archivo binario: ' . $fsPath);
    }
    return array(true, publicista_path_to_web($fsPath));
}

function publicista_guess_extension_from_binary($bytes) {
    if (substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n") return 'png';
    if (substr($bytes, 0, 3) === "\xff\xd8\xff") return 'jpg';
    if (substr($bytes, 0, 4) === 'RIFF' && strpos(substr($bytes, 8, 4), 'WEBP') !== false) return 'webp';
    return 'png';
}

function publicista_prepare_arbitrary_image_locally($jobId, $inputFs, $outputBaseName, $targetDirKey) {
    $paths = publicista_job_fs_paths($jobId);
    if ($targetDirKey === 'finals_dir') {
        $targetBase = $paths['finals_dir'];
    } elseif ($targetDirKey === 'originals_dir') {
        $targetBase = $paths['originals_dir'];
    } else {
        $targetBase = $paths['candidates_dir'];
    }

    $analysisFs = $paths['meta_dir'] . '/' . $outputBaseName . '_local_result.json';
    $squareFs = $targetBase . '/' . $outputBaseName . '_square.jpg';
    $previewFs = $targetBase . '/' . $outputBaseName . '_preview.jpg';

    $worker = BASE_PATH . '/tools/publicista_image_worker.py';
    if (!file_exists($worker)) {
        return array(false, 'No se encontró el worker Python de Publicista.');
    }

    $command = 'python3 ' . escapeshellarg($worker)
        . ' prepare-source'
        . ' --input ' . escapeshellarg($inputFs)
        . ' --output-json ' . escapeshellarg($analysisFs)
        . ' --output-square ' . escapeshellarg($squareFs)
        . ' --output-preview ' . escapeshellarg($previewFs)
        . ' --preview-size 320'
        . ' --square-size 1024';

    $proc = publicista_proc_command($command, publicista_ai_timeouts()['local_worker'], BASE_PATH);
    publicista_job_log_write($jobId, $outputBaseName . '_local_command', $proc);
    if (!$proc['ok']) {
        return array(false, 'Error en worker local: ' . ($proc['stderr'] !== '' ? $proc['stderr'] : 'sin detalle'));
    }
    $analysis = file_exists($analysisFs) ? json_decode((string)@file_get_contents($analysisFs), true) : null;
    if (!is_array($analysis)) {
        return array(false, 'El worker local no devolvió un JSON válido.');
    }

    return array(true, array(
        'worker_result' => $analysis,
        'worker_command' => $proc['command'],
        'analysis_json_path' => publicista_path_to_web($analysisFs),
        'square_path' => file_exists($squareFs) ? publicista_path_to_web($squareFs) : '',
        'face_blur_path' => '',
        'preview_path' => file_exists($previewFs) ? publicista_path_to_web($previewFs) : '',
    ));
}


function publicista_build_outfit_prompt_details($job) {
    $pp = function_exists('publicista_job_production_params') ? publicista_job_production_params($job) : array();

    $colorMap = array(
        'negro'    => 'negro mate elegante',
        'rojo'     => 'rojo intenso',
        'burdeos'  => 'burdeos / vino oscuro',
        'nude'     => 'nude / beige tostado',
        'blanco'   => 'blanco marfil',
        'azul'     => 'azul marino / navy',
        'verde'    => 'verde esmeralda',
        'dorado'   => 'dorado / champán metálico',
        'fucsia'   => 'fucsia / rosa oscuro intenso',
        'plateado' => 'plateado / metálico',
    );
    $styleMap = array(
        'vestido_corto'      => 'vestido corto por encima de la rodilla, de línea limpia y acabado editorial',
        'vestido_largo'      => 'vestido largo elegante de presencia premium',
        'conjunto_top'       => 'conjunto de top y falda con apariencia sofisticada y urbana',
        'mono'               => 'mono / jumpsuit de corte limpio, estilizado y moderno',
        'conjunto_pantalon'  => 'conjunto de pantalón y blusa con aire editorial premium',
        'body_falda'         => 'body de cobertura completa con falda de cintura alta, acabado editorial',
    );
    $levelMap = array(
        'discreto'  => 'atractivo editorial discreto, con cobertura elegante, silueta favorecedora, tejido opaco y estilo premium NO sexual, sin transparencias, sin ropa interior visible y sin desnudo',
        'sexy'      => 'atractivo editorial adulto y glamouroso, con silueta favorecedora, corte pulido, detalles visuales sofisticados y cobertura elegante, expresamente NO sexual, sin transparencias, sin mostrar ropa interior y sin desnudo',
        'sugerente' => 'presencia editorial adulta, magnética y fotogénica, con glamour comercial impactante pero expresamente NO sexual, sin transparencias, sin ropa interior visible, sin desnudo y sin lectura erótica explícita',
    );
    $fitMap = array(
        'ajustado' => 'entallado y favorecedor, marcando la silueta con elegancia sin caer en exceso',
        'semi'     => 'semi-ajustado, con caída elegante y silueta estilizada',
        'fluido'   => 'fluido, con movimiento natural y acabado sofisticado',
    );
    $complementMap = array(
        'tacones_altos'    => 'tacones altos de aguja estilizados',
        'tacones_medios'   => 'tacones medios elegantes',
        'sin_zapatos'      => 'sin zapatos visibles',
        'bolso'            => 'bolso de mano pequeño y refinado',
        'cinturon'         => 'cinturón fino que define la cintura',
        'sin_complementos' => 'sin complementos',
    );

    $color = ($pp['color'] ?? 'auto') !== 'auto' ? ($colorMap[$pp['color']] ?? $pp['color']) : '';
    $style = !empty($pp['style']) ? ($styleMap[$pp['style']] ?? $pp['style']) : 'vestido corto elegante';
    $level = $levelMap[$pp['level'] ?? 'sexy'] ?? $levelMap['sexy'];
    $fit = $fitMap[$pp['fit'] ?? 'ajustado'] ?? 'ajustado al cuerpo';

    $texture = 'tejido opaco de calidad con textura visible, costuras limpias, paneles definidos y acabado fotográfico premium';
    if (($pp['fit'] ?? 'ajustado') === 'fluido') {
        $texture = 'tejido opaco con caída elegante, textura visible y movimiento limpio, con capas visuales sutiles, sin efecto plástico ni uniforme';
    } elseif (($pp['fit'] ?? 'ajustado') === 'semi') {
        $texture = 'tejido opaco refinado con textura sutil, paneles, costuras elegantes y detalles de confección visibles de aspecto premium';
    }

    $styling = ($color !== '')
        ? 'base cromática coherente anclada en ' . $color . ', evitando apariencia plana mediante contraste tonal visible, piezas bicolor discretas, ribetes o detalles de confección elegantes y acentos sobrios que rompan el monocolor'
        : 'paleta sofisticada con contraste tonal visible, mezcla controlada de tonos complementarios y detalles visuales refinados, evitando bloques de color monótonos o ropa visualmente sosa';

    $complements = array();
    foreach ((array)($pp['complements'] ?? array()) as $c) {
        $mapped = $complementMap[$c] ?? '';
        if ($mapped !== '') {
            $complements[] = $mapped;
        }
    }

    $summaryParts = array();
    if ($color !== '') {
        $summaryParts[] = 'color obligatorio ' . $color;
    }
    $summaryParts[] = 'prenda obligatoria ' . $style;
    $summaryParts[] = 'ajuste obligatorio ' . $fit;
    $summaryParts[] = 'textura y confección obligatorias ' . $texture;
    $summaryParts[] = 'tratamiento visual permitido ' . $level;
    $summaryParts[] = 'dirección de styling ' . $styling;
    if (!empty($complements)) {
        $summaryParts[] = 'complementos obligatorios ' . implode(' y ', $complements);
    }

    return array(
        'summary' => implode(', ', $summaryParts),
        'color' => $color !== '' ? $color : 'auto controlado por el sistema (sin color obligatorio fijo)',
        'style' => $style,
        'fit' => $fit,
        'level' => $level,
        'texture' => $texture,
        'styling' => $styling,
        'complements' => !empty($complements) ? implode(', ', $complements) : 'sin complementos obligatorios',
    );
}

function publicista_build_outfit_production($job) {
    $details = publicista_build_outfit_prompt_details($job);
    return trim((string)($details['summary'] ?? ''));
}


function publicista_build_outfit_session_lock($job) {
    $pp = function_exists('publicista_job_production_params') ? publicista_job_production_params($job) : array();
    $details = publicista_build_outfit_prompt_details($job);

    $styleKey = trim((string)($pp['style'] ?? 'vestido_corto'));
    $fitKey = trim((string)($pp['fit'] ?? 'ajustado'));
    $levelKey = trim((string)($pp['level'] ?? 'sexy'));
    $rawComplements = is_array($pp['complements'] ?? null) ? $pp['complements'] : array();

    $color = trim((string)($details['color'] ?? ''));
    if ($color === '' || strpos($color, 'auto controlado') !== false) {
        $color = 'tono editorial sólido fijado por el sistema';
    }

    $fitSentence = array(
        'ajustado' => 'corte entallado y limpio, pegado al cuerpo con acabado elegante y líneas que estilizan sin rigidizar la pose',
        'semi'     => 'corte semi-entallado, equilibrado y elegante, con lectura de moda y caída favorecedora',
        'fluido'   => 'corte fluido y ordenado, con caída controlada y movimiento fotogénico',
    )[$fitKey] ?? 'corte limpio y elegante';

    $coverageSentence = array(
        'discreto'  => 'cobertura alta y editorial, sin escote profundo ni aperturas llamativas, pero con silueta bien construida y presencia de moda',
        'sexy'      => 'cobertura elegante con glamour editorial controlado, escote moderado o cuello trabajado, y sin lecturas sexuales',
        'sugerente' => 'presencia glamourosa y atrevida en clave editorial, con cortes favorecedores, sin transparencias y sin convertir el vestuario en sexual',
    )[$levelKey] ?? 'cobertura elegante y editorial';

    $fabricSentence = array(
        'ajustado' => 'tejido opaco con elasticidad controlada, textura visible y detalles de confección que evitan un acabado plano',
        'semi'     => 'tejido crepé opaco con textura refinada y contraste tonal sutil',
        'fluido'   => 'tejido mate de caída fluida y limpia, con profundidad visual y movimiento elegante',
    )[$fitKey] ?? 'tejido liso opaco';

    $pieceSentence = 'un único look editorial de calle o estudio';
    switch ($styleKey) {
        case 'vestido_largo':
            $pieceSentence = 'un único vestido largo de una sola pieza, con escote cuadrado moderado fijo, sin mangas, cintura definida y falda recta hasta los tobillos';
            break;
        case 'conjunto_top':
            $pieceSentence = 'un único conjunto de dos piezas compuesto por top estructurado de tirante ancho y cuello recto fijo, junto a falda de cintura alta y línea recta';
            break;
        case 'mono':
            $pieceSentence = 'un único mono largo de una sola pieza, con cuello recto fijo, sin mangas, cintura definida y pierna recta larga';
            break;
        case 'conjunto_pantalon':
            $pieceSentence = 'un único conjunto de dos piezas compuesto por blusa estructurada de manga corta y cuello limpio, junto a pantalón recto de tiro alto';
            break;
        case 'body_falda':
            $pieceSentence = 'un único conjunto de dos piezas compuesto por body de cobertura completa con cuello recto fijo y falda de cintura alta de línea recta';
            break;
        case 'vestido_corto':
        default:
            $pieceSentence = 'un único vestido corto de una sola pieza, con escote cuadrado moderado fijo, sin mangas, cintura definida y falda recta hasta mitad de muslo';
            break;
    }

    $complementParts = array();
    if (in_array('sin_complementos', $rawComplements, true)) {
        $complementParts[] = 'sin complementos adicionales visibles';
    } else {
        if (in_array('tacones_altos', $rawComplements, true)) {
            $complementParts[] = 'los mismos tacones altos lisos en todas las imágenes donde sean visibles';
        } elseif (in_array('tacones_medios', $rawComplements, true)) {
            $complementParts[] = 'los mismos tacones medios lisos en todas las imágenes donde sean visibles';
        } elseif (in_array('sin_zapatos', $rawComplements, true)) {
            $complementParts[] = 'sin zapatos visibles en ninguna toma';
        }
        if (in_array('bolso', $rawComplements, true)) {
            $complementParts[] = 'el mismo bolso de mano pequeño cuando aparezca en cuadro';
        }
        if (in_array('cinturon', $rawComplements, true)) {
            $complementParts[] = 'el mismo cinturón fino marcando la cintura';
        }
    }
    if (empty($complementParts)) {
        $complementParts[] = 'sin accesorios nuevos ni variaciones entre tomas';
    }

    $strictSummary = trim($pieceSentence . ', color base ' . $color . ', ' . $fitSentence . ', ' . $fabricSentence . ', ' . $coverageSentence . ', con el mismo contraste tonal, mismos ribetes o acentos discretos y la misma lectura visual premium en toda la sesión, ' . implode(', ', $complementParts));

    return array(
        'strict_summary' => $strictSummary,
        'consistency_block' => 'MISMA PRENDA EXACTA EN TODA LA SESIÓN: ' . $strictSummary . '. Debe parecer literalmente una única sesión de fotos realizada el mismo día con el mismo vestuario. No cambies entre imágenes el tipo de escote, mangas, tirantes, largo, patrón, tejido, costuras, adornos, zapatos ni complementos. Mantén también los mismos acentos visuales del outfit, evitando que se simplifique a un bloque monocolor plano o genérico. No generes variantes parecidas: debe ser exactamente la misma prenda repetida en distintos encuadres.',
        'negative_block' => 'PROHIBIDO variar el vestuario entre candidatas y finales: nada de cambiar cuello recto por cuello en pico o redondo, nada de añadir o quitar mangas, tirantes, abertura, largo, cinturón, tacones, bolso, adornos o detalles decorativos. Tampoco conviertas el look en una masa de color uniforme sin textura ni detalles. Aunque el encuadre no enseñe toda la ropa, asume que fuera de cuadro sigue siendo exactamente el mismo outfit.',
    );
}

function publicista_build_setting_description($job) {
    $pp = function_exists('publicista_job_production_params') ? publicista_job_production_params($job) : array();

    $settingMap = array(
        'hotel_lujoso'  => 'interior lujoso: habitación de hotel de alta gama o salón elegante con mobiliario de calidad, colores neutros y cálidos',
        'minimalista'   => 'fondo liso de studio fotográfico, minimalista y limpio, sin distracciones',
        'calido'        => 'interior cálido tipo apartamento acogedor, con luz de lámpara y ambiente elegante',
        'urbano_noche'  => 'exterior urbano de noche, luces de ciudad al fondo, calle o terraza',
    );
    $lightingMap = array(
        'natural'   => 'luz natural suave entrando por ventana lateral',
        'studio'    => 'iluminación de studio con beauty light frontal y relleno suave',
        'calida'    => 'luz cálida y envolvente de lámparas o velas, ambiente acogedor y elegante',
        'contraluz' => 'contraluz dramático con silueta definida y halo luminoso',
    );

    $setting = $pp['setting'] ?? 'auto';
    $lighting = $pp['lighting'] ?? 'auto';

    $settingStr = ($setting !== 'auto' && isset($settingMap[$setting])) ? $settingMap[$setting] : 'interior elegante y luminoso, fondo limpio y agradable';
    $lightingStr = ($lighting !== 'auto' && isset($lightingMap[$lighting])) ? $lightingMap[$lighting] : 'luz suave, equilibrada y realista con sombras coherentes';

    return array('setting' => $settingStr, 'lighting' => $lightingStr);
}


function publicista_build_pollo_subject_description($job) {
    $d = is_array(publicista_array_get($job, 'descriptor', array())) ? publicista_array_get($job, 'descriptor', array()) : array();
    $data = is_array(publicista_array_get($d, 'data', array())) ? publicista_array_get($d, 'data', array()) : array();

    $parts = array();
    $skinTone = trim((string)publicista_array_get($data, 'skin_tone', ''));
    $hairColor = trim((string)publicista_array_get($data, 'hair_color', ''));
    $hairTexture = trim((string)publicista_array_get($data, 'hair_texture', ''));
    $hairLength = trim((string)publicista_array_get($data, 'hair_length', ''));
    $bodyBuild = trim((string)publicista_array_get($data, 'body_build', ''));
    $bodyCurves = trim((string)publicista_array_get($data, 'body_curves', ''));
    $faceShape = trim((string)publicista_array_get($data, 'face_shape', ''));
    $eyes = trim((string)publicista_array_get($data, 'eyes', ''));
    $lips = trim((string)publicista_array_get($data, 'lips', ''));
    $nose = trim((string)publicista_array_get($data, 'nose', ''));
    $eyebrows = trim((string)publicista_array_get($data, 'eyebrows', ''));
    $simGuide = trim((string)publicista_array_get($data, 'similarity_guidance', ''));

    if ($skinTone !== '') $parts[] = 'piel ' . $skinTone;
    $hairBits = array_filter(array($hairColor, $hairTexture, $hairLength));
    if (!empty($hairBits)) $parts[] = 'cabello ' . implode(', ', $hairBits);
    if ($bodyBuild !== '') $parts[] = 'complexión ' . $bodyBuild;
    if ($bodyCurves !== '') $parts[] = 'silueta ' . $bodyCurves;
    if ($faceShape !== '') $parts[] = 'rostro ' . $faceShape;
    if ($eyes !== '') $parts[] = 'ojos ' . $eyes;
    if ($lips !== '') $parts[] = 'labios ' . $lips;
    if ($nose !== '') $parts[] = 'nariz ' . $nose;
    if ($eyebrows !== '') $parts[] = 'cejas ' . $eyebrows;

    $features = publicista_array_get($data, 'distinguishing_features', array());
    if (!is_array($features)) $features = array();
    $clothingWords = array('ropa', 'vestido', 'top', 'falda', 'pantalon', 'blusa', 'conjunto', 'outfit', 'mono', 'body', 'camisa', 'chaqueta', 'abrigo', 'zapato', 'tacon', 'bota', 'bolso', 'accesorio', 'cinturon', 'complemento', 'maquillaje');
    $filteredFeatures = array_values(array_filter((array)$features, function($f) use ($clothingWords) {
        $fl = strtolower(trim((string)$f));
        if ($fl === '') return false;
        foreach ($clothingWords as $w) {
            if (strpos($fl, $w) !== false) return false;
        }
        return true;
    }));
    if (!empty($filteredFeatures)) {
        $parts[] = 'rasgos distintivos visibles ' . implode(', ', array_slice($filteredFeatures, 0, 8));
    }
    if ($simGuide !== '') {
        $parts[] = 'parecido general a conservar: ' . $simGuide;
    }

    return trim(implode('. ', $parts));
}

function publicista_build_pollo_master_prompt($job) {
    $pp = function_exists('publicista_job_production_params') ? publicista_job_production_params($job) : array();
    $outfitDetails = publicista_build_outfit_prompt_details($job);
    $envDesc = publicista_build_setting_description($job);
    $subject = publicista_build_pollo_subject_description($job);
    $operatorBrief = trim((string)($pp['operator_brief'] ?? ''));
    $restrictions = publicista_compose_restrictions_summary($job);
    $selfieMode = trim((string)($pp['selfie_mode'] ?? 'off'));
    $framing = trim((string)($pp['framing'] ?? 'variado'));
    $pose = trim((string)($pp['pose'] ?? 'variado'));
    $expression = trim((string)($pp['expression'] ?? 'variado'));
    $makeup = trim((string)($pp['makeup'] ?? 'auto'));

    $poseLine = 'pose femenina, segura, fotogénica y natural, evitando brazos muertos pegados al torso y evitando postura rígida de pasaporte';
    $poseMap = array(
        'pie_estatica' => 'pose de pie firme y segura, con peso bien repartido, una cadera ligeramente marcada y brazos colocados con naturalidad',
        'pie_dinamica' => 'pose de pie con movimiento sutil, cadera viva, asimetría natural y sensación de espontaneidad',
        'sentada' => 'pose sentada elegante y favorecedora, relajada pero llamativa, con actitud segura',
        'apoyada' => 'pose apoyada en pared, espejo o mueble de forma natural, confiada y muy fotogénica',
    );
    if (isset($poseMap[$pose])) $poseLine = $poseMap[$pose];

    $framingLine = 'encuadre vertical o cuadrado natural, fotográfico y favorecedor';
    $framingMap = array(
        'entero' => 'encuadre de cuerpo entero, de cabeza a pies, con aire suficiente alrededor y sin cortar manos ni pies',
        'medio' => 'encuadre medio o tres cuartos corto, centrado en rostro, torso y actitud',
        'tres_cuartos' => 'encuadre tres cuartos, dejando ver bien silueta, ropa y gesto corporal',
    );
    if (isset($framingMap[$framing])) $framingLine = $framingMap[$framing];

    $expressionLine = 'expresión atractiva, segura y viva';
    $expressionMap = array(
        'sonrisa' => 'expresión cálida con sonrisa natural y creíble',
        'seria' => 'expresión seria, segura y elegante',
        'sugerente' => 'expresión magnética, confiada y muy llamativa, sexy en clave comercial pero no explícita',
    );
    if (isset($expressionMap[$expression])) $expressionLine = $expressionMap[$expression];

    $makeupLine = '';
    $makeupMap = array(
        'natural' => 'maquillaje natural favorecedor',
        'elegante' => 'maquillaje elegante de noche, muy favorecedor',
        'intenso' => 'maquillaje intenso, llamativo y bien ejecutado',
    );
    if (isset($makeupMap[$makeup])) $makeupLine = $makeupMap[$makeup];

    $selfieLine = '';
    if ($selfieMode === 'mixed') {
        $selfieLine = 'La serie puede incluir selfies realistas o fotos tipo selfie frente al espejo, con ángulo natural de móvil, sin convertir todas las tomas en primerísimo plano.';
    }

    $outfitLine = 'La ropa debe basarse en lo elegido en el formulario: ' . trim((string)($outfitDetails['summary'] ?? 'look sexy, llamativo y elegante'));
    $outfitLine .= '. La ropa debe verse sexy, llamativa, femenina, favorecedora y con gancho visual, pero sin lencería, sin desnudo y sin erotismo explícito.';
    $outfitLine .= ' Debe evitar monotonía: mejor contraste visual, color con intención, textura visible, patrón o detalles de confección creíbles y look más comercial que plano.';

    $sections = array();

    if ($operatorBrief !== '') {
        $sections[] = '[BRIEF LIBRE DEL OPERADOR — PRIORIDAD MÁXIMA] ' . $operatorBrief;
    }

    $sections[] = '[MUJER] Una sola mujer adulta. Debe mantener parecido general claro con la referencia original, pero sin copiar identidad exacta. ' . $subject . '. Concéntrate en parecerse a la mujer real por rostro, cabello, piel, complexión y silueta, no por la ropa original.';
    $sections[] = '[ROPA Y ESTILO] ' . $outfitLine;
    $sections[] = '[POSE Y ACTITUD] ' . $poseLine . '. ' . $expressionLine . '. Mirada viva, lenguaje corporal femenino y natural, postura atractiva y segura.';
    if ($selfieLine !== '') {
        $sections[] = '[SELFIE] ' . $selfieLine;
    }
    $sections[] = '[ENCUADRE] ' . $framingLine . '.';
    $sections[] = '[AMBIENTE] Fondo y entorno: ' . trim((string)($envDesc['setting'] ?? 'entorno interior realista con contexto')) . '. Debe sentirse real, vivido y coherente, nunca fondo liso de estudio genérico salvo que se haya pedido minimalista.';
    $lightingLine = trim((string)($envDesc['lighting'] ?? 'luz realista y coherente'));
    if ($makeupLine !== '') {
        $sections[] = '[LUZ Y ACABADO] ' . $lightingLine . '. ' . $makeupLine . '. Foto realista, nítida, con buen detalle en piel, pelo, ropa y fondo.';
    } else {
        $sections[] = '[LUZ Y ACABADO] ' . $lightingLine . '. Foto realista, nítida, con buen detalle en piel, pelo, ropa y fondo.';
    }

    $sections[] = '[CALIDAD] Fotografía realista, auténtica y comercial. Evita aspecto soso, ropa monocolor pobre, pose rígida, expresión apagada, manos deformes, dedos extra, proporciones irreales, texto, watermark, collage, dibujos o CGI.';
    $sections[] = '[SEGURIDAD] Sexy y llamativa sí, pero siempre como glamour adulto no explícito: totalmente vestida, sin lencería visible, sin desnudo, sin transparencias íntimas, sin acto sexual ni foco fetichista.';

    if ($restrictions !== '') {
        $sections[] = '[RESTRICCIONES] ' . $restrictions . '.';
    }

    return trim(implode("\n\n", array_filter($sections, function($x) {
        return trim((string)$x) !== '';
    })));
}

function publicista_build_master_prompt($job) {
    $d = is_array(publicista_array_get($job, 'descriptor', array())) ? publicista_array_get($job, 'descriptor', array()) : array();
    $data = is_array(publicista_array_get($d, 'data', array())) ? publicista_array_get($d, 'data', array()) : array();
    $pp = function_exists('publicista_job_production_params') ? publicista_job_production_params($job) : array();

    $features = publicista_array_get($data, 'distinguishing_features', array());
    if (!is_array($features)) $features = array();

    $outfitDetails = publicista_build_outfit_prompt_details($job);
    $outfitLock = publicista_build_outfit_session_lock($job);
    $outfit = trim((string)($outfitLock['strict_summary'] ?? ''));
    if ($outfit === '') {
        $outfit = trim((string)($outfitDetails['summary'] ?? ''));
    }
    $envDesc = publicista_build_setting_description($job);

    // Maquillaje
    $makeupMap = array(
        'natural'  => 'maquillaje natural y discreto',
        'elegante' => 'maquillaje elegante de noche (ojos ahumados o labios rojos)',
        'intenso'  => 'maquillaje intenso y llamativo, muy definido',
        'auto'     => trim((string)publicista_array_get($data, 'makeup', 'maquillaje suave y cuidado')),
    );
    $makeup = $makeupMap[$pp['makeup'] ?? 'auto'] ?? trim((string)publicista_array_get($data, 'makeup', 'maquillaje suave y cuidado'));

    // Expresión
    $expressionMap = array(
        'sonrisa'   => 'sonrisa natural y cálida',
        'seria'     => 'expresión serena y segura de sí misma',
        'sugerente' => 'expresión segura y fotogénica, con mirada directa y gesto editorial sereno, sin gesto sexualizado',
        'variado'   => trim((string)publicista_array_get($data, 'expression', 'expresión serena y confiada')),
    );
    $expression = $expressionMap[$pp['expression'] ?? 'variado'] ?? trim((string)publicista_array_get($data, 'expression', 'expresión serena y confiada'));

    // Brief libre del operador — PRIORIDAD MÁXIMA
    $operatorBrief = trim((string)($pp['operator_brief'] ?? ''));

    $restrictions = publicista_compose_restrictions_summary($job);
    $selfieMode = trim((string)($pp['selfie_mode'] ?? 'off'));

    // ------------------------------------------------------------------
    // Construcción del prompt por secciones
    // ------------------------------------------------------------------
    $sections = array();

    // [PRIORIDAD MÁXIMA] Brief del operador va primero si existe
    if ($operatorBrief !== '') {
        $sections[] = "INSTRUCCIÓN PRIORITARIA DEL OPERADOR (aplícalo solo si sigue siendo editorial, elegante, NO sexual, no explícito y apto para moderación estricta): {$operatorBrief}";
    }

    // [ROPA — VA LO PRIMERO: es la instrucción más crítica, antes de cualquier descripción física]
    // Así el modelo la recibe antes de cualquier contexto que pueda sesgarla hacia la ropa original.
    $sections[] = "ROPA (INSTRUCCIÓN DE MÁXIMA PRIORIDAD, OBLIGATORIA, NO NEGOCIABLE): "
        . "La mujer lleva EXACTAMENTE el siguiente outfit en TODAS las imágenes de esta producción, sin excepción: {$outfit}. "
        . "ESTA ROPA ES COMPLETAMENTE DIFERENTE A LA QUE APARECE EN LA FOTO DE REFERENCIA. "
        . "IGNORA ABSOLUTAMENTE la ropa, el estilo y el vestuario de la foto de referencia — son irrelevantes para esta producción. "
        . "El modelo de imagen DEBE generar la ropa descrita aquí y NO la de la referencia. "
        . 'La ropa debe verse limpia, bien cosida, sin arrugas, perfectamente ajustada al cuerpo de la mujer. '
        . 'No añadas capas, abrigos ni prendas extra que no se mencionen.';

    $sections[] = '[OUTFIT DESGLOSADO] '
        . 'Color de ropa: ' . trim((string)($outfitDetails['color'] ?? 'auto controlado')) . '. '
        . 'Estilo de prenda: ' . trim((string)($outfitDetails['style'] ?? '')) . '. '
        . 'Ajuste de la prenda: ' . trim((string)($outfitDetails['fit'] ?? '')) . '. '
        . 'Textura y confección: ' . trim((string)($outfitDetails['texture'] ?? 'tejido opaco limpio')) . '. '
        . 'Dirección de styling: ' . trim((string)($outfitDetails['styling'] ?? 'look editorial premium')) . '. '
        . 'Nivel de atrevimiento permitido: ' . trim((string)($outfitDetails['level'] ?? '')) . '. '
        . 'Complementos: ' . trim((string)($outfitDetails['complements'] ?? 'sin complementos obligatorios')) . '.';

    if (!empty($outfitLock['consistency_block'])) {
        $sections[] = '[CONSISTENCIA DE VESTUARIO] ' . $outfitLock['consistency_block'];
    }
    if (!empty($outfitLock['negative_block'])) {
        $sections[] = '[NO VARIAR VESTUARIO] ' . $outfitLock['negative_block'];
    }

    // [TIPO DE IMAGEN]
    $sections[] = 'Fotografía hiperrealista de una mujer adulta, estilo retrato publicitario profesional, editorial y realista. '
        . 'La imagen debe parecer una fotografía de verdad, no una ilustración, no un rendering 3D, no un dibujo, no arte digital. '
        . 'Enfoque glamouroso y comercial premium: belleza editorial, presencia magnética, silueta favorecedora, look pulido, estilismo con fuerza visual y resultado con gancho comercial. '
        . 'Debe sentirse como una campaña fotográfica de moda comercial de alta conversión: segura, aspiracional, dinámica y llamativa, pero claramente NO erótica, NO sexual y apta para moderación estricta. '
        . 'Prioriza lenguaje corporal abierto y fotogénico, con brazos separados del torso cuando favorezca la composición, asimetría natural, pequeños giros de cuerpo y actitud segura. '
        . 'Si alguna instrucción pudiera empujar hacia contenido sexualizado, ignórala y mantén siempre un resultado comercial, elegante, impactante y publicable.';

    if ($selfieMode === 'mixed') {
        $sections[] = '[TIPO DE TOMAS] Incluir solo algunas candidatas concretas en formato selfie o primer plano cercano. El resto deben mantener variedad editorial normal; NO conviertas toda la serie en selfies.';
    }

    // [PERSONA — rasgos físicos, sin ropa — tomados del descriptor]
    $skinTone   = trim((string)publicista_array_get($data, 'skin_tone', 'natural'));
    $hairColor  = trim((string)publicista_array_get($data, 'hair_color', 'oscuro'));
    $hairTex    = trim((string)publicista_array_get($data, 'hair_texture', 'natural'));
    $hairLen    = trim((string)publicista_array_get($data, 'hair_length', 'media'));
    $bodyBuild  = trim((string)publicista_array_get($data, 'body_build', 'equilibrada'));
    $bodyCurves = trim((string)publicista_array_get($data, 'body_curves', ''));
    $faceShape  = trim((string)publicista_array_get($data, 'face_shape', 'natural'));
    $eyes       = trim((string)publicista_array_get($data, 'eyes', 'naturales'));
    $lips       = trim((string)publicista_array_get($data, 'lips', 'naturales'));
    $eyebrows   = trim((string)publicista_array_get($data, 'eyebrows', 'definidas'));
    $simGuide   = trim((string)publicista_array_get($data, 'similarity_guidance', ''));

    // Filtrar features para excluir cualquier referencia a ropa/outfit
    $clothingWords = array('ropa', 'vestido', 'top', 'falda', 'pantalon', 'blusa', 'conjunto', 'outfit', 'mono', 'body', 'camisa', 'chaqueta', 'abrigo', 'zapato', 'tacon', 'bota', 'bolso', 'accesorio', 'cinturon', 'complemento');
    $filteredFeatures = array_filter((array)$features, function($f) use ($clothingWords) {
        $fl = strtolower(trim((string)$f));
        foreach ($clothingWords as $w) {
            if (strpos($fl, $w) !== false) return false;
        }
        return true;
    });

    $sections[] = "[PERSONA] La mujer debe parecerse de forma general a la referencia visual sin replicar su identidad exacta. {$simGuide} "
        . "Piel: {$skinTone}. "
        . "Cabello: {$hairColor}, {$hairTex}, longitud {$hairLen}. Peinado con volumen creíble, movimiento natural y acabado pulido de sesión fotográfica premium. "
        . "Complexión: {$bodyBuild} — IMPORTANTE: mantén exactamente la misma silueta y complexión que la referencia, NO añadas masa corporal extra ni engordes la figura; si la referencia muestra una figura delgada o media, la imagen generada DEBE conservar esa misma delgadez sin rellenar caderas, abdomen ni muslos. "
        . ($bodyCurves !== '' ? "Silueta general a conservar con naturalidad: {$bodyCurves}. Mantén la forma corporal general de la referencia sin exagerarla ni sexualizarla. " : '')
        . "Rostro: {$faceShape}. Ojos: {$eyes}. Labios: {$lips}. Cejas: {$eyebrows}. {$makeup}. "
        . "Expresión y presencia: segura, atractiva, fotogénica y elegante, con alternancia natural entre mirada directa, tres cuartos o fuera de cámara según la toma, nunca vacía ni apagada. "
        . "Maquillaje y acabado facial: definidos, limpios y favorecedores, con estética editorial comercial de alto nivel, sin excesos artificiales. "
        . "La actitud corporal debe sentirse confiada, estilizada y viva, evitando brazos pegados al cuerpo o posturas de pasaporte salvo que el encuadre lo exija de forma puntual."
        . (!empty($filteredFeatures) ? ' Rasgos físicos específicos a conservar: ' . implode(', ', $filteredFeatures) . '.' : '');

    // [AMBIENTACIÓN]
    $sections[] = "[FONDO Y LUZ] Fondo: {$envDesc['setting']}. "
        . "Iluminación: {$envDesc['lighting']}. "
        . 'El entorno debe sentirse aspiracional, limpio y con personalidad: hotel elegante, apartamento premium, estudio refinado o contexto urbano cuidado, evitando fondos pobres, vacíos o de catálogo barato. '
        . 'Las sombras deben ser coherentes con la posición de la fuente de luz. Sin halos artificiales alrededor del cuerpo.';

    // [CALIDAD Y ANTI-ARTEFACTOS]
    $sections[] = '[CALIDAD Y REALISMO — OBLIGATORIO] '
        . 'ANATOMÍA: exactamente cinco dedos en cada mano con articulaciones naturales y proporciones reales; '
        . 'un único rostro bien definido, no duplicado ni cortado; '
        . 'cuello de longitud normal; orejas simétricas; muñecas y tobillos proporcionales; '
        . 'las dos piernas tienen la misma longitud; las dos manos tienen el mismo tamaño. '
        . 'PIEL: textura de piel fotográfica real con poros visibles, no piel de plástico, no piel encerada, no piel sobreexpuesta. '
        . 'COMPOSICIÓN: la figura no está cortada aleatoriamente salvo en plano medio (que corta a la altura de la cintura); '
        . 'sin objetos flotantes; sin extremidades que aparezcan de la nada. '
        . 'ESPEJOS: si aparece un espejo en la escena, el reflejo debe ser físicamente coherente con el ángulo de cámara; '
        . 'si no puedes garantizar la coherencia del reflejo, elimina el espejo del fondo. '
        . 'ESTILO: sin filtros de belleza extremos; sin efecto HDR exagerado; profundidad de campo natural de objetivo 85mm f/1.8; '
        . 'la imagen debe parecer tomada con una cámara de gama alta, no generada por IA.';

    $sections[] = '[DIRECCIÓN ESTÉTICA Y ANTIMO-NOTONÍA] '
        . 'Evita resultados sosos o genéricos: nada de ropa plana sin textura, nada de outfit visualmente pobre, nada de expresión vacía, nada de pose rígida, nada de colores lavados, nada de estética de catálogo barato y nada de brazos muertos pegados al torso en todas las tomas. '
        . 'Busca impacto comercial premium mediante estilismo pulido, contraste tonal visible, detalles de confección, pose fotogénica, energía segura, composición aspiracional y variedad real de ángulos, siempre sin sexualizar la escena.';

    // [RESTRICCIONES del operador]
    if ($restrictions !== '') {
        $sections[] = "[RESTRICCIONES ADICIONALES] {$restrictions}.";
    }

    // [PROHIBIDO]
    $sections[] = 'PROHIBIDO ABSOLUTAMENTE: seis o más dedos en una mano; manos fundidas o deformadas; '
        . 'dos rostros o rostro duplicado; extremidades de longitud diferente; '
        . 'cuerpo con proporciones irreales; reflejo inconsistente en espejo; '
        . 'texto, números, letras o marcas de agua en la imagen; collage de múltiples fotos; '
        . 'estilo ilustración, anime, dibujo, CGI o render 3D; piel plástica; '
        . 'ropa interior visible como prenda exterior; desnudo completo o parcial; '
        . 'transparencias en la ropa; ropa rasgada o mojada; '
        . 'posturas anatómicamente imposibles; fondo con objetos flotantes en el aire.';

    $sections[] = '[SEGURIDAD Y MODERACIÓN] ' . implode(' ', publicista_visual_safety_lines());

    return trim(implode("\n\n", $sections));
}

function publicista_build_prompt_variants($masterPrompt, $count = 6, $retryMode = false, $job = null) {
    // Encuadres y poses — cada variante tiene encuadre distinto pero
    // NO modifica la ropa (eso ya está bloqueado en el prompt maestro)
    $pp = ($job && function_exists('publicista_job_production_params')) ? publicista_job_production_params($job) : array();

    $framingPref = $pp['framing'] ?? 'variado';
    $posePref = $pp['pose'] ?? 'variado';
    $expressionPref = $pp['expression'] ?? 'variado';
    $selfieMode = $pp['selfie_mode'] ?? 'off';

    if ($framingPref === 'entero') {
        $shots = array_fill(0, 8, 'Plano de cuerpo entero, figura completa visible de cabeza a pies.');
    } elseif ($framingPref === 'medio') {
        $shots = array_fill(0, 8, 'Plano medio, encuadre desde la cintura hacia arriba.');
    } elseif ($framingPref === 'tres_cuartos') {
        $shots = array_fill(0, 8, 'Plano tres cuartos, encuadre desde las rodillas hacia arriba.');
    } else {
        $shots = array(
            'Plano de cuerpo entero: figura completa de cabeza a pies, con una pierna ligeramente adelantada, peso sobre una cadera, un brazo separado del cuerpo y energía de campaña premium.',
            'Plano tres cuartos: encuadre desde rodillas hacia arriba, con giro sutil de torso, hombros angulados, una mano en la cadera y la otra relajada lejos del torso.',
            'Plano medio: desde la cintura hacia arriba, ligera inclinación de hombros, barbilla bien colocada, un brazo flexionado y mirada de alto impacto comercial.',
            'Plano de cuerpo entero: pose relajada pero elegante, apoyada de lado con peso sobre una pierna, mirada fuera de cámara y gesto natural seguro.',
            'Plano tres cuartos: figura ligeramente de lado con cadera sutilmente girada, un hombro adelantado y manos colocadas de forma viva y no rígida.',
            'Plano medio: tres cuartos, mano rozando suavemente el pelo o el cuello, otra mano baja y composición limpia, poderosa y fotogénica.',
            'Plano entero: figura con pequeño paso al frente, brazos con separación natural del cuerpo, silueta estilizada y actitud confiada.',
            'Plano tres cuartos: con ligero giro de caderas, cuello largo, expresión serena, presencia magnética y mirada lateral editorial.',
        );
    }

    $selfieShots = array(
        'Plano selfie cercano: rostro y parte alta del torso muy protagonistas, cámara cercana en mano o ángulo de selfie creíble, estética fotográfica real, premium y editorial, con mirada segura y favorecedora y un gesto natural no rígido.',
        'Primer plano tipo selfie premium: encuadre cercano de rostro y hombros, mirada a cámara o levemente lateral, pelo bien trabajado, composición natural de móvil pero con acabado limpio, elegante, comercial y nada sexualizado.',
    );

    $poseExtra = array(
        'pie_estatica'  => 'Postura: de pie, estable y bien plantada, pose de presentación premium con hombros bien colocados, un brazo flexionado y el otro con caída natural.',
        'pie_dinamica'  => 'Postura: de pie con leve movimiento de cadera, reparto de peso favorecedor, brazos vivos y pose dinámica natural.',
        'sentada'       => 'Postura: sentada elegantemente, cruce de piernas suave, espalda erguida, manos bien colocadas y actitud segura.',
        'apoyada'       => 'Postura: apoyada en la pared o en un mueble, pose relajada, confiada, editorial y con asimetría natural del cuerpo.',
        'variado'       => '',
    );
    $poseStr = $poseExtra[$posePref] ?? '';

    $expressionExtra = array(
        'sonrisa'   => 'Expresión: sonrisa natural y cálida, bien fotografiada, con carisma comercial y sin ser exagerada.',
        'seria'     => 'Expresión: seria y segura, con alternancia entre mirada directa y lateral, elegante y con presencia premium.',
        'sugerente' => 'Expresión: segura, fotogénica y magnética, con gesto editorial sereno y atractivo, alternando mirada a cámara y fuera de cámara, sin gesto sexualizado.',
        'variado'   => '',
    );
    $expressionStr = $expressionExtra[$expressionPref] ?? '';

    $camera = array(
        'Fotografía realista, estética de cámara profesional DSLR, profundidad de campo natural.',
        'Fotografía con objetivo 85mm f/1.8, fondo ligeramente desenfocado, sujeto nítido.',
        'Estética fotográfica natural, sin filtros ni post-proceso excesivo, proporciones humanas creíbles.',
        'Iluminación y profundidad equilibradas, textura de piel fotográfica, sin exageraciones digitales.',
    );

    $retryNote = $retryMode
        ? ' CORRECCIÓN: prioriza absolutamente manos con exactamente cinco dedos, un único rostro bien definido, ausencia total de artefactos y complexión fiel a la referencia.'
        : '';

    $selfieIndexes = array();
    if ($selfieMode === 'mixed' && $count > 0) {
        $selfieIndexes[] = min(1, $count - 1);
        if ($count > 4) {
            $selfieIndexes[] = $count - 2;
        }
        $selfieIndexes = array_values(array_unique(array_filter($selfieIndexes, function($idx) use ($count) {
            return $idx >= 0 && $idx < $count;
        })));
    }

    $out = array();
    $selfieShotCursor = 0;
    for ($i = 0; $i < $count; $i++) {
        $useSelfie = in_array($i, $selfieIndexes, true);
        $shot = $useSelfie
            ? $selfieShots[$selfieShotCursor++ % count($selfieShots)]
            : $shots[$i % count($shots)];
        $cam = $camera[$i % count($camera)];

        $variantNotes = array($shot, $poseStr, $expressionStr, $cam, 'Haz que esta candidata tenga identidad visual propia dentro de la misma sesión: cambia microgesto, ángulo, energía, dirección de mirada, posición de brazos y composición sin alterar a la mujer ni el vestuario.');
        if ($useSelfie) {
            $variantNotes[] = 'IMPORTANTE: esta variante concreta sí debe verse como selfie o primer plano cercano.';
        } elseif ($selfieMode === 'mixed') {
            $variantNotes[] = 'IMPORTANTE: esta variante concreta NO debe ser selfie; conserva un encuadre editorial normal.';
        }

        $variantExtra = trim(implode(' ', array_filter($variantNotes)));
        $out[] = trim($masterPrompt . "

[ENCUADRE Y POSE PARA ESTA IMAGEN] " . $variantExtra . $retryNote);
    }
    return $out;
}

function publicista_store_prompt_master($jobId, $masterPrompt, $variants) {
    list($okPath, $path) = publicista_job_meta_write($jobId, 'prompt_master.json', array(
        'built_at' => now_datetime(),
        'master_prompt' => $masterPrompt,
        'variants' => $variants,
    ));
    $job = publicista_job_get($jobId);
    if ($job) {
        $job['prompt_master'] = array(
            'built_at' => now_datetime(),
            'text' => $masterPrompt,
            'variants' => $variants,
            'path' => $okPath ? $path : '',
        );
        publicista_job_save($job);
    }
}

function publicista_decode_generated_image_bytes($decoded) {
    if (!is_array($decoded) || empty($decoded['data']) || !is_array($decoded['data'])) return array(false, 'Respuesta de imagen vacía.');
    $first = $decoded['data'][0];
    if (!is_array($first) || empty($first['b64_json'])) return array(false, 'La respuesta de imagen no incluye b64_json.');
    $bytes = base64_decode((string)$first['b64_json'], true);
    if ($bytes === false || $bytes === '') return array(false, 'No se pudo decodificar la imagen generada.');
    return array(true, $bytes);
}

function publicista_generate_candidate_image($jobId, $candidateIndex, $prompt) {
    $response = publicista_openai_image_generate($prompt, array(
        'quality' => 'medium',
        'size' => '1024x1024',
        'n' => 1,
        'output_format' => 'png',
    ));
    $logPayload = $response;
    if (!empty($logPayload['raw_body']) && strlen($logPayload['raw_body']) > 150000) {
        $logPayload['raw_body'] = substr($logPayload['raw_body'], 0, 150000) . "\n...truncado...";
    }
    publicista_job_log_write($jobId, 'image_generate_' . str_pad((string)$candidateIndex, 2, '0', STR_PAD_LEFT), $logPayload);
    if (!$response['ok']) {
        return array(false, 'Error generando candidata ' . $candidateIndex . ': ' . ($response['error'] !== '' ? $response['error'] : 'sin detalle'));
    }
    list($okBytes, $bytesOrError) = publicista_decode_generated_image_bytes($response['decoded']);
    if (!$okBytes) {
        return array(false, $bytesOrError);
    }
    $ext = publicista_guess_extension_from_binary($bytesOrError);
    $paths = publicista_job_fs_paths($jobId);
    $rawFs = $paths['candidates_dir'] . '/candidate_' . str_pad((string)$candidateIndex, 2, '0', STR_PAD_LEFT) . '_raw.' . $ext;
    list($okWrite, $webPathOrError) = publicista_write_binary_file($rawFs, $bytesOrError);
    if (!$okWrite) {
        return array(false, $webPathOrError);
    }
    publicista_register_image_generation_cost($jobId, (string)publicista_ai_config()['image_model'], 'medium', '1024x1024', 1);
    return array(true, array(
        'raw_path' => $webPathOrError,
        'request_id' => (string)publicista_array_get($response, 'request_id', ''),
        'http_code' => (int)publicista_array_get($response, 'http_code', 0),
        'model' => (string)publicista_array_get($response, 'model', publicista_ai_config()['image_model']),
        'raw_fs_path' => $rawFs,
        'prompt' => $prompt,
    ));
}


function publicista_store_generated_candidate_bytes($jobId, $candidateIndex, $prompt, $decodedBody, $requestId, $httpCode, $pricingMode) {
    list($okBytes, $bytesOrError) = publicista_decode_generated_image_bytes($decodedBody);
    if (!$okBytes) {
        return array(false, $bytesOrError);
    }
    $ext = publicista_guess_extension_from_binary($bytesOrError);
    $paths = publicista_job_fs_paths($jobId);
    $rawFs = $paths['candidates_dir'] . '/candidate_' . str_pad((string)$candidateIndex, 2, '0', STR_PAD_LEFT) . '_raw.' . $ext;
    list($okWrite, $webPathOrError) = publicista_write_binary_file($rawFs, $bytesOrError);
    if (!$okWrite) {
        return array(false, $webPathOrError);
    }
    publicista_register_image_generation_cost($jobId, (string)publicista_ai_config()['image_model'], 'medium', '1024x1024', 1, $pricingMode);
    return array(true, array(
        'raw_path' => $webPathOrError,
        'request_id' => (string)$requestId,
        'http_code' => (int)$httpCode,
        'model' => (string)publicista_array_get($response, 'model', publicista_ai_config()['image_model']),
        'raw_fs_path' => $rawFs,
        'prompt' => $prompt,
    ));
}

function publicista_build_image_generation_batch_lines($jobId, $prompts) {
    $cfg = publicista_ai_config();
    $lines = array();
    $customIds = array();
    foreach (array_values((array)$prompts) as $idx => $prompt) {
        $candidateIndex = $idx + 1;
        $customId = 'candidate_' . str_pad((string)$candidateIndex, 2, '0', STR_PAD_LEFT);
        $customIds[$customId] = array(
            'candidate_index' => $candidateIndex,
            'prompt' => $prompt,
        );
        $body = array(
            'model' => $cfg['image_model'],
            'prompt' => (string)$prompt,
            'size' => '1024x1024',
            'quality' => 'medium',
            'n' => 1,
        );

        if (!publicista_is_gpt_image_model($cfg['image_model'])) {
            $body['response_format'] = 'b64_json';
        }
        if (publicista_is_gpt_image_model($cfg['image_model'])) {
            $body['output_format'] = 'png';
        }

        $lines[] = json_encode(array(
            'custom_id' => $customId,
            'method' => 'POST',
            'url' => '/v1/images/generations',
            'body' => $body,
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    return array(implode("\n", $lines) . "\n", $customIds);
}

function publicista_submit_image_generation_batch($jobId, $prompts) {
    list($jsonl, $customIds) = publicista_build_image_generation_batch_lines($jobId, $prompts);
    $paths = publicista_job_fs_paths($jobId);
    $localBatchFs = $paths['meta_dir'] . '/batch_images_input.jsonl';
    if (@file_put_contents($localBatchFs, $jsonl) === false) {
        return array(false, 'No se pudo escribir el input JSONL del batch de imágenes.');
    }
    $upload = publicista_openai_upload_batch_file($localBatchFs, basename($localBatchFs));
    publicista_job_log_write($jobId, 'batch_images_file_upload', $upload);
    if (!$upload['ok']) {
        return array(false, 'No se pudo subir el archivo batch de imágenes: ' . ($upload['error'] !== '' ? $upload['error'] : 'sin detalle'));
    }
    $inputFileId = trim((string)publicista_array_get(publicista_array_get($upload, 'decoded', array()), 'id', ''));
    if ($inputFileId === '') {
        return array(false, 'OpenAI no devolvió input_file_id válido al subir el batch.');
    }
    $batch = publicista_openai_create_batch('/v1/images/generations', $inputFileId, array('module' => 'publicista', 'job_id' => $jobId, 'kind' => 'image_candidates'));
    publicista_job_log_write($jobId, 'batch_images_create', $batch);
    if (!$batch['ok']) {
        return array(false, 'No se pudo crear el batch de imágenes: ' . ($batch['error'] !== '' ? $batch['error'] : 'sin detalle'));
    }
    $decoded = is_array($batch['decoded']) ? $batch['decoded'] : array();
    $batchId = trim((string)publicista_array_get($decoded, 'id', ''));
    if ($batchId === '') {
        return array(false, 'OpenAI no devolvió batch_id válido.');
    }
    return array(true, array(
        'image_batch_id' => $batchId,
        'input_file_id' => $inputFileId,
        'status' => trim((string)publicista_array_get($decoded, 'status', 'validating')),
        'submitted_at' => now_datetime(),
        'last_checked_at' => '',
        'completed_at' => '',
        'custom_ids' => $customIds,
        'input_jsonl_path' => publicista_path_to_web($localBatchFs),
        'output_file_id' => '',
        'error_file_id' => '',
        'result_jsonl_path' => '',
        'errors_jsonl_path' => '',
    ));
}

function publicista_parse_batch_jsonl($content) {
    $rows = array();
    foreach (preg_split('/\r\n|\r|\n/', (string)$content) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $decoded = json_decode($line, true);
        if (is_array($decoded)) $rows[] = $decoded;
    }
    return $rows;
}

function publicista_continue_image_batch_pipeline($jobId) {
    $job = publicista_job_get($jobId);
    if (!$job) return array(false, 'No se encontró el trabajo de Publicista.');
    $batchState = publicista_pipeline_batch_state($job);
    $batchId = trim((string)publicista_array_get($batchState, 'image_batch_id', ''));
    if ($batchId === '') return array(false, 'No hay batch de imágenes pendiente en este trabajo.');

    $statusResp = publicista_openai_retrieve_batch($batchId);
    publicista_job_log_write($jobId, 'batch_images_status', $statusResp);
    if (!$statusResp['ok']) {
        return array(false, 'No se pudo consultar el batch de imágenes: ' . ($statusResp['error'] !== '' ? $statusResp['error'] : 'sin detalle'));
    }
    $decoded = is_array($statusResp['decoded']) ? $statusResp['decoded'] : array();
    $status = trim((string)publicista_array_get($decoded, 'status', ''));
    $requestCounts = is_array(publicista_array_get($decoded, 'request_counts', array())) ? publicista_array_get($decoded, 'request_counts', array()) : array();
    $batchErrors = is_array(publicista_array_get(publicista_array_get($decoded, 'errors', array()), 'data', array())) ? publicista_array_get(publicista_array_get($decoded, 'errors', array()), 'data', array()) : array();

    $batchState['status'] = $status;
    $batchState['last_checked_at'] = now_datetime();
    $batchState['output_file_id'] = trim((string)publicista_array_get($decoded, 'output_file_id', publicista_array_get($batchState, 'output_file_id', '')));
    $batchState['error_file_id'] = trim((string)publicista_array_get($decoded, 'error_file_id', publicista_array_get($batchState, 'error_file_id', '')));
    $batchState['request_counts'] = $requestCounts;
    $batchState['batch_errors'] = $batchErrors;

    if ($status === 'completed') {
        $batchState['completed_at'] = now_datetime();
    }

    $job['pipeline'] = array_merge(publicista_array_get($job, 'pipeline', array()), array(
        'status' => in_array($status, array('completed'), true) ? 'processing' : 'waiting_batch',
        'stage' => in_array($status, array('completed'), true) ? 'batch_images_completed' : 'batch_images_waiting',
        'summary' => in_array($status, array('completed'), true)
            ? 'Batch de imágenes completado. Procesando y evaluando candidatas.'
            : ('Batch de imágenes en estado ' . publicista_batch_status_label($status) . '. Vuelve más tarde y pulsa actualizar.'),
        'batch' => $batchState,
    ));
    $job['processing'] = array_merge(publicista_array_get($job, 'processing', array()), array(
        'last_action' => 'batch_images_status',
        'last_finished_at' => now_datetime(),
        'last_error' => '',
        'last_error_at' => '',
        'last_openai_request_id' => (string)publicista_array_get($statusResp, 'request_id', ''),
        'last_openai_http_code' => (int)publicista_array_get($statusResp, 'http_code', 0),
    ));
    publicista_job_save($job);

    if (!in_array($status, array('completed'), true)) {
        if (in_array($status, array('failed', 'expired', 'cancelled'), true)) {
            $job = publicista_job_get($jobId);
            $job['estado'] = 'error';
            $job['processing']['last_error'] = 'El batch de imágenes terminó en estado ' . $status . '.';
            $job['processing']['last_error_at'] = now_datetime();
            $job['pipeline']['status'] = 'error';
            $job['pipeline']['summary'] = 'El batch de imágenes terminó en estado ' . publicista_batch_status_label($status) . '.';
            publicista_job_save($job);
            return array(false, $job['processing']['last_error']);
        }
        return array(true, publicista_job_get($jobId));
    }

    $outputFileId = trim((string)publicista_array_get($batchState, 'output_file_id', ''));
    if ($outputFileId === '') {
        $completedCount = (int)publicista_array_get($requestCounts, 'completed', 0);
        $failedCount = (int)publicista_array_get($requestCounts, 'failed', 0);
        $totalCount = (int)publicista_array_get($requestCounts, 'total', 0);

        if ($batchState['error_file_id'] !== '' || ($totalCount > 0 && $completedCount === 0 && $failedCount > 0)) {
            $errorPathWeb = '';

            if ($batchState['error_file_id'] !== '') {
                $errDown = publicista_openai_download_file_content($batchState['error_file_id']);
                publicista_job_log_write($jobId, 'batch_images_error_download', array(
                    'ok' => $errDown['ok'],
                    'http_code' => $errDown['http_code'],
                    'request_id' => $errDown['request_id'],
                    'error' => $errDown['error'],
                ));

                if ($errDown['ok']) {
                    $paths = publicista_job_fs_paths($jobId);
                    $errFs = $paths['meta_dir'] . '/batch_images_errors.jsonl';
                    @file_put_contents($errFs, (string)$errDown['content']);
                    $errorPathWeb = publicista_path_to_web($errFs);
                    $batchState['errors_jsonl_path'] = $errorPathWeb;
                }
            }

            $job = publicista_job_get($jobId);
            $job['pipeline'] = array_merge(publicista_array_get($job, 'pipeline', array()), array(
                'status' => 'error',
                'stage' => 'batch_images_completed_without_output',
                'summary' => $errorPathWeb !== ''
                    ? 'El batch terminó sin imágenes válidas. Revisa el fichero de errores descargado.'
                    : 'El batch terminó sin imágenes válidas y sin output_file_id.',
                'batch' => $batchState,
            ));
            $job['processing'] = array_merge(publicista_array_get($job, 'processing', array()), array(
                'last_action' => 'batch_images_finalize_missing_output',
                'last_finished_at' => now_datetime(),
                'last_error' => $errorPathWeb !== ''
                    ? 'El batch terminó sin output_file_id. Revisa: ' . $errorPathWeb
                    : 'El batch terminó sin output_file_id y sin resultados válidos.',
                'last_error_at' => now_datetime(),
            ));
            $job['estado'] = 'error';
            publicista_job_save($job);

            return array(false, $job['processing']['last_error']);
        }

        $job = publicista_job_get($jobId);
        $job['pipeline'] = array_merge(publicista_array_get($job, 'pipeline', array()), array(
            'status' => 'waiting_batch',
            'stage' => 'batch_images_completed_without_output',
            'summary' => 'OpenAI ya marca el batch como completado, pero todavía no ha devuelto output_file_id. Espera un poco y pulsa actualizar batch / continuar.',
            'batch' => $batchState,
        ));
        $job['processing'] = array_merge(publicista_array_get($job, 'processing', array()), array(
            'last_action' => 'batch_images_completed_without_output',
            'last_finished_at' => now_datetime(),
            'last_error' => '',
            'last_error_at' => '',
        ));
        publicista_job_save($job);

        return array(true, publicista_job_get($jobId));
    }
    $download = publicista_openai_download_file_content($outputFileId);
    publicista_job_log_write($jobId, 'batch_images_output_download', array('ok' => $download['ok'], 'http_code' => $download['http_code'], 'request_id' => $download['request_id'], 'error' => $download['error']));
    if (!$download['ok']) {
        return array(false, 'No se pudo descargar el output del batch: ' . ($download['error'] !== '' ? $download['error'] : 'sin detalle'));
    }
    $paths = publicista_job_fs_paths($jobId);
    $resultFs = $paths['meta_dir'] . '/batch_images_output.jsonl';
    @file_put_contents($resultFs, (string)$download['content']);
    $batchState['result_jsonl_path'] = publicista_path_to_web($resultFs);
    if ($batchState['error_file_id'] !== '') {
        $errDown = publicista_openai_download_file_content($batchState['error_file_id']);
        if ($errDown['ok']) {
            $errFs = $paths['meta_dir'] . '/batch_images_errors.jsonl';
            @file_put_contents($errFs, (string)$errDown['content']);
            $batchState['errors_jsonl_path'] = publicista_path_to_web($errFs);
        }
    }

    $rows = publicista_parse_batch_jsonl($download['content']);
    $sourceFs = BASE_PATH . '/' . ltrim((string)publicista_array_get(publicista_array_get($job, 'source_image', array()), 'stored_path', ''), '/');
    $candidates = array();
    foreach ($rows as $row) {
        $customId = trim((string)publicista_array_get($row, 'custom_id', ''));
        $map = is_array(publicista_array_get($batchState, 'custom_ids', array())) ? publicista_array_get($batchState, 'custom_ids', array()) : array();
        $meta = is_array(publicista_array_get($map, $customId, array())) ? publicista_array_get($map, $customId, array()) : array();
        $candidateIndex = (int)publicista_array_get($meta, 'candidate_index', 0);
        if ($candidateIndex <= 0) continue;
        $prompt = (string)publicista_array_get($meta, 'prompt', '');
        $candidateRow = array(
            'id' => $customId,
            'prompt' => $prompt,
            'generation' => array(),
            'raw_path' => '',
            'square_path' => '',
            'face_blur_path' => '',
            'preview_path' => '',
            'analysis_json_path' => '',
            'worker_result' => array(),
            'evaluation' => array(),
            'effective_score' => 0,
            'selected' => false,
            'status' => 'error',
            'error' => '',
            'round' => 'base',
            'manual_blur_applied' => 0,
            'manual_blur_intensity' => 0,
            'manual_blur_shape' => array(),
        );
        $responseBody = publicista_array_get(publicista_array_get($row, 'response', array()), 'body', array());
        $statusCode = (int)publicista_array_get(publicista_array_get($row, 'response', array()), 'status_code', 0);
        $requestId = (string)publicista_array_get(publicista_array_get($row, 'response', array()), 'request_id', '');
        if (!empty($row['error'])) {
            $candidateRow['error'] = trim((string)publicista_array_get(publicista_array_get($row, 'error', array()), 'message', 'Error batch sin detalle'));
            $candidates[] = $candidateRow;
            continue;
        }
        list($okStore, $storeOrErr) = publicista_store_generated_candidate_bytes($jobId, $candidateIndex, $prompt, $responseBody, $requestId, $statusCode, 'batch');
        if (!$okStore) {
            $candidateRow['error'] = $storeOrErr;
            $candidates[] = $candidateRow;
            continue;
        }
        $candidateRow['generation'] = array(
            'request_id' => $requestId,
            'http_code' => $statusCode,
            'model' => (string)publicista_array_get($response, 'model', publicista_ai_config()['image_model']),
            'mode' => 'batch',
        );
        $candidateRow['raw_path'] = $storeOrErr['raw_path'];
        list($okLocal, $localOrError) = publicista_prepare_arbitrary_image_locally($jobId, $storeOrErr['raw_fs_path'], $customId, 'candidates_dir');
        if (!$okLocal) {
            $candidateRow['error'] = $localOrError;
            $candidates[] = $candidateRow;
            continue;
        }
        $candidateRow['square_path'] = $localOrError['square_path'];
        $candidateRow['face_blur_path'] = $localOrError['face_blur_path'];
        $candidateRow['preview_path'] = $localOrError['preview_path'];
        $candidateRow['analysis_json_path'] = $localOrError['analysis_json_path'];
        $candidateRow['worker_result'] = $localOrError['worker_result'];
        $squareFs = BASE_PATH . '/' . ltrim($localOrError['square_path'], '/');
        list($okEval, $evalOrError) = publicista_evaluate_candidate_with_openai($jobId, $sourceFs, $squareFs, $customId);
        if (!$okEval) {
            $candidateRow['error'] = $evalOrError;
            $candidateRow['status'] = 'needs_review';
            $candidates[] = $candidateRow;
            continue;
        }
        $candidateRow['evaluation'] = $evalOrError;
        $candidateRow['effective_score'] = publicista_candidate_effective_score($candidateRow);
        $candidateRow['status'] = ($candidateRow['effective_score'] >= 60 && !empty($evalOrError['adult_appearing'])) ? 'ok' : 'needs_review';
        $candidates[] = $candidateRow;
    }

    usort($candidates, function($a, $b) {
        return (int)publicista_array_get($b, 'effective_score', 0) <=> (int)publicista_array_get($a, 'effective_score', 0);
    });
    $selected = array_slice(array_values(array_filter($candidates, function($c) {
        return trim((string)publicista_array_get($c, 'raw_path', '')) !== '';
    })), 0, 4);
    $finalImages = array();
    foreach ($selected as $i => $candidate) {
        $selected[$i]['selected'] = true;
        $finalImages[] = publicista_finalize_candidate_output($jobId, $candidate, $i + 1);
    }
    $selectedIds = array(); foreach ($selected as $c) $selectedIds[] = $c['id'];
    $finalIds = array(); foreach ($finalImages as $f) $finalIds[] = $f['id'];

    $job = publicista_job_get($jobId);
    $workflow = publicista_job_workflow($job);
    $workflow['pack_final'] = 0; $workflow['pack_finalized_at'] = ''; $workflow['pack_final_note'] = '';
    $job['workflow'] = $workflow;
    $job['candidates'] = $candidates;
    $job['final_images'] = $finalImages;
    $job['pipeline'] = array_merge(publicista_array_get($job, 'pipeline', array()), array(
        'finished_at' => now_datetime(),
        'status' => count($finalImages) >= 4 ? 'done' : 'needs_review',
        'stage' => 'completed',
        'summary' => count($finalImages) >= 4
            ? ('Pipeline completado: ' . count($candidates) . ' candidatas generadas por Batch y 4 finales listas.')
            : ('Pipeline Batch completado con revisión pendiente: ' . count($finalImages) . ' finales disponibles.'),
        'selected_candidate_ids' => $selectedIds,
        'final_candidate_ids' => $finalIds,
        'total_generated' => count($candidates),
        'total_selected' => count($selected),
        'batch' => $batchState,
    ));
    $job['processing'] = array_merge(publicista_array_get($job, 'processing', array()), array(
        'last_action' => 'run_pipeline_v1_batch_finalize',
        'last_finished_at' => now_datetime(),
        'last_error' => '',
        'last_error_at' => '',
    ));
    $job['estado'] = count($finalImages) >= 4 ? 'done' : 'needs_review';
    list($okSave, $saved) = publicista_job_save($job);
    if (!$okSave) {
        return array(false, is_string($saved) ? $saved : 'No se pudo guardar el pipeline Batch final de Publicista.');
    }
    list($okCopy, $copyResult) = publicista_generate_copy_pack($jobId, true);
    $saved = publicista_job_get($jobId);
    if ($saved) {
        $pipelineNow = is_array(publicista_array_get($saved, 'pipeline', array())) ? publicista_array_get($saved, 'pipeline', array()) : array();
        $pipelineNow['summary'] = trim((string)publicista_array_get($pipelineNow, 'summary', '')) . ($okCopy ? ' Textos y títulos generados automáticamente.' : ' Imágenes listas; textos pendientes de regenerar.');
        $saved['pipeline'] = $pipelineNow;
        publicista_job_save($saved);
        $saved = publicista_job_get($jobId);
        if ($saved) {
            publicista_notify_job_generation_finished($saved);
        }
    }
    return array(true, $saved ?: $job);
}

function publicista_candidate_eval_schema() {
    return array(
        'type' => 'json_schema',
        'name' => 'publicista_candidate_eval',
        'strict' => true,
        'schema' => array(
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array(
                'adult_appearing','single_person','single_face_clear','framing_ok',
                'hands_ok','anatomy_ok','background_ok','body_proportions_match','skin_texture_ok','mirror_coherent',
                'subject_prominence_ok','square_fill_realistic','no_stretch_detected',
                'likeness_score','quality_score','overall_score','issues','best_use'
            ),
            'properties' => array(
                'adult_appearing'         => array('type' => 'boolean'),
                'single_person'           => array('type' => 'boolean'),
                'single_face_clear'       => array('type' => 'boolean'),
                'framing_ok'              => array('type' => 'boolean'),
                'hands_ok'                => array('type' => 'boolean'),
                'anatomy_ok'              => array('type' => 'boolean'),
                'background_ok'           => array('type' => 'boolean'),
                'body_proportions_match'  => array('type' => 'boolean'),
                'skin_texture_ok'         => array('type' => 'boolean'),
                'mirror_coherent'         => array('type' => 'boolean'),
                'subject_prominence_ok'   => array('type' => 'boolean'),
                'square_fill_realistic'   => array('type' => 'boolean'),
                'no_stretch_detected'     => array('type' => 'boolean'),
                'likeness_score'  => array('type' => 'integer', 'minimum' => 0, 'maximum' => 100),
                'quality_score'   => array('type' => 'integer', 'minimum' => 0, 'maximum' => 100),
                'overall_score'   => array('type' => 'integer', 'minimum' => 0, 'maximum' => 100),
                'issues'   => array('type' => 'array', 'items' => array('type' => 'string')),
                'best_use' => array('type' => 'string'),
            ),
        ),
    );
}


function publicista_evaluate_candidate_with_openai($jobId, $sourceFs, $candidateSquareFs, $candidateLabel) {
    $cfg = publicista_ai_config();
    $sourceMime = function_exists('mime_content_type') ? (string)mime_content_type($sourceFs) : 'image/jpeg';
    $candMime = function_exists('mime_content_type') ? (string)mime_content_type($candidateSquareFs) : 'image/jpeg';
    $sourceB64 = base64_encode((string)@file_get_contents($sourceFs));
    $candB64 = base64_encode((string)@file_get_contents($candidateSquareFs));
    if ($sourceB64 === '' || $candB64 === '') return array(false, 'No se pudieron leer las imágenes para evaluar la candidata.');

    $payload = array_merge(publicista_response_payload_defaults('candidate_eval', $cfg['descriptor_model']), array(
        'model' => $cfg['descriptor_model'],
        'store' => false,
        'input' => array(
            array(
                'role' => 'system',
                'content' =>
                    'Compara una imagen de referencia con una imagen candidata generada por IA. Devuelve solo JSON según el esquema. ' .
                    'Evalúa si la candidata mantiene parecido general con la referencia SIN copiar identidad exacta. ' .
                    'Penaliza duramente: dedos extra o faltantes, manos deformes, rostro duplicado o cortado, cuello elongado, ' .
                    'extremidades incoherentes, piel plástica o encerada, fondo artificial de relleno, estiramiento de la foto o bordes raros de outpainting. ' .
                    'body_proportions_match: true si la complexión, peso visual y silueta son similares a la referencia. ' .
                    'skin_texture_ok: true si la piel parece fotográfica real y no plástica. ' .
                    'mirror_coherent: true si no hay espejo o el reflejo es físicamente correcto. ' .
                    'subject_prominence_ok: true si la persona domina la escena y ocupa aproximadamente un 70-85% visual del cuadro. ' .
                    'square_fill_realistic: true si el relleno para llegar al 1:1 es una extensión natural del entorno y no un fondo borroso, repetido, espejado ni artificial. ' .
                    'no_stretch_detected: true si no hay indicios de deformación, aplastamiento o ensanchamiento de la imagen original.',
            ),
            array(
                'role' => 'user',
                'content' => array(
                    array('type' => 'input_text', 'text' =>
                        'Imagen 1: referencia original. Imagen 2: candidata ' . $candidateLabel . '. ' .
                        'Evalúa: parecido general, calidad técnica, anatomía, proporciones corporales, textura de piel, composición protagonista, calidad del relleno 1:1 y ausencia de stretch.'),
                    array('type' => 'input_image', 'image_url' => 'data:' . $sourceMime . ';base64,' . $sourceB64),
                    array('type' => 'input_image', 'image_url' => 'data:' . $candMime . ';base64,' . $candB64),
                ),
            ),
        ),
        'text' => array('format' => publicista_candidate_eval_schema()),
    ));
    $response = publicista_openai_json_request('/v1/responses', $payload, $cfg['timeouts']['responses']);
    $logPayload = $response;
    if (!empty($logPayload['raw_body']) && strlen($logPayload['raw_body']) > 150000) {
        $logPayload['raw_body'] = substr($logPayload['raw_body'], 0, 150000) . "\n...truncado...";
    }
    publicista_job_log_write($jobId, 'candidate_eval_' . preg_replace('/[^a-z0-9_\-]/i', '_', $candidateLabel), $logPayload);
    if (!$response['ok']) {
        return array(false, 'Falló la evaluación OpenAI de la candidata ' . $candidateLabel . ': ' . ($response['error'] !== '' ? $response['error'] : 'sin detalle'));
    }
    $text = publicista_response_output_text($response['decoded']);
    $parsed = json_decode($text, true);
    if (!is_array($parsed)) {
        return array(false, 'La evaluación de la candidata ' . $candidateLabel . ' no devolvió JSON válido.');
    }
    $parsed['request_id'] = (string)publicista_array_get($response, 'request_id', '');
    $parsed['http_code'] = (int)publicista_array_get($response, 'http_code', 0);
    publicista_register_response_cost($jobId, $response, 'candidate_eval');
    return array(true, $parsed);
}


function publicista_candidate_effective_score($candidate) {
    $eval = is_array(publicista_array_get($candidate, 'evaluation', array())) ? publicista_array_get($candidate, 'evaluation', array()) : array();
    $score = (int)publicista_array_get($eval, 'overall_score', 0);

    if (empty($eval['single_person']))      $score -= 20;
    if (empty($eval['anatomy_ok']))         $score -= 16;
    if (empty($eval['single_face_clear']))  $score -= 16;
    if (empty($eval['hands_ok']))           $score -= 14;
    if (array_key_exists('body_proportions_match', $eval) && empty($eval['body_proportions_match'])) $score -= 14;
    if (array_key_exists('subject_prominence_ok', $eval) && empty($eval['subject_prominence_ok']))   $score -= 12;
    if (array_key_exists('square_fill_realistic', $eval) && empty($eval['square_fill_realistic']))   $score -= 12;
    if (array_key_exists('no_stretch_detected', $eval) && empty($eval['no_stretch_detected']))       $score -= 12;
    if (array_key_exists('skin_texture_ok', $eval) && empty($eval['skin_texture_ok']))               $score -= 8;
    if (array_key_exists('mirror_coherent', $eval) && empty($eval['mirror_coherent']))               $score -= 7;
    if (array_key_exists('background_ok', $eval) && empty($eval['background_ok']))                   $score -= 7;

    return max(0, min(100, $score));
}


function publicista_build_direct_final_output($jobId, $candidate, $finalIndex, $job = null) {
    $candidateId = trim((string)publicista_array_get($candidate, 'id', ''));
    $candidateSquareRel = trim((string)publicista_array_get($candidate, 'square_path', ''));
    $candidatePreviewRel = trim((string)publicista_array_get($candidate, 'preview_path', ''));
    $paths = publicista_job_fs_paths($jobId);
    $index = str_pad((string)$finalIndex, 2, '0', STR_PAD_LEFT);

    $out = array(
        'id' => 'final_' . $index,
        'source_candidate_id' => $candidateId,
        'rank' => $finalIndex,
        'final_path' => '',
        'square_path' => '',
        'preview_path' => '',
        'candidate_square_path' => '',
        'candidate_preview_path' => '',
        'current_variant' => 'candidate',
        'refine_proposal_path' => '',
        'refine_proposal_preview_path' => '',
        'refine_proposal_prompt' => '',
        'refine_proposal_generation' => array(),
        'copied_at' => now_datetime(),
        'evaluation_score' => publicista_candidate_effective_score($candidate),
        'manual_blur_applied' => 0,
        'manual_blur_intensity' => 0,
        'manual_blur_shape' => array(),
        'premium_refined' => 0,
        'premium_refine_error' => 'Final directa: se reutiliza la imagen candidata sin refinado automático.',
        'generation' => is_array(publicista_array_get($candidate, 'generation', array())) ? publicista_array_get($candidate, 'generation', array()) : array(),
    );

    if ($candidateSquareRel !== '') {
        $src = BASE_PATH . '/' . ltrim($candidateSquareRel, '/');
        $dest = $paths['finals_dir'] . '/final_' . $index . '_square.jpg';
        if (@copy($src, $dest)) {
            $out['final_path'] = publicista_path_to_web($dest);
            $out['square_path'] = publicista_path_to_web($dest);
            $out['candidate_square_path'] = publicista_path_to_web($dest);
        }
    }
    if ($candidatePreviewRel !== '') {
        $src = BASE_PATH . '/' . ltrim($candidatePreviewRel, '/');
        $dest = $paths['finals_dir'] . '/final_' . $index . '_preview.jpg';
        if (@copy($src, $dest)) {
            $out['preview_path'] = publicista_path_to_web($dest);
            $out['candidate_preview_path'] = publicista_path_to_web($dest);
        }
    }

    list($okMeta, $metaPath) = publicista_job_meta_write($jobId, 'final_' . $index . '_meta.json', $out);
    if ($okMeta) {
        $out['meta_path'] = $metaPath;
    }
    return $out;
}

function publicista_finalize_candidate_output($jobId, $candidate, $finalIndex, $job = null, $promptOverride = '') {
    $candidateId = trim((string)publicista_array_get($candidate, 'id', ''));
    $candidateSquareRel = trim((string)publicista_array_get($candidate, 'square_path', ''));
    $candidatePreviewRel = trim((string)publicista_array_get($candidate, 'preview_path', ''));
    $job = is_array($job) ? $job : publicista_job_get($jobId);
    $paths = publicista_job_fs_paths($jobId);
    $index = str_pad((string)$finalIndex, 2, '0', STR_PAD_LEFT);

    $out = array(
        'id' => 'final_' . $index,
        'source_candidate_id' => $candidateId,
        'rank' => $finalIndex,
        'final_path' => '',
        'square_path' => '',
        'preview_path' => '',
        'copied_at' => now_datetime(),
        'evaluation_score' => publicista_candidate_effective_score($candidate),
        'manual_blur_applied' => 0,
        'manual_blur_intensity' => 0,
        'manual_blur_shape' => array(),
        'premium_refined' => 0,
        'premium_refine_error' => '',
    );

    list($okRefine, $refineOrError) = publicista_refine_final_image($jobId, $job ?: array(), $candidate, $finalIndex, $promptOverride);
    if ($okRefine) {
        $out['final_path'] = trim((string)publicista_array_get($refineOrError, 'square_path', ''));
        $out['square_path'] = trim((string)publicista_array_get($refineOrError, 'square_path', ''));
        $out['preview_path'] = trim((string)publicista_array_get($refineOrError, 'preview_path', ''));
        $out['premium_refined'] = 1;
        $out['generation'] = array(
            'request_id' => (string)publicista_array_get($refineOrError, 'request_id', ''),
            'http_code' => (int)publicista_array_get($refineOrError, 'http_code', 0),
            'model' => (string)publicista_array_get($refineOrError, 'model', publicista_ai_config()['image_model']),
            'raw_path' => (string)publicista_array_get($refineOrError, 'raw_path', ''),
        );
    } else {
        $out['premium_refine_error'] = is_string($refineOrError) ? $refineOrError : 'No se pudo refinar la final.';
        if ($candidateSquareRel !== '') {
            $src = BASE_PATH . '/' . ltrim($candidateSquareRel, '/');
            $dest = $paths['finals_dir'] . '/final_' . $index . '_square.jpg';
            if (@copy($src, $dest)) {
                $out['final_path'] = publicista_path_to_web($dest);
                $out['square_path'] = publicista_path_to_web($dest);
            }
        }
        if ($candidatePreviewRel !== '') {
            $src = BASE_PATH . '/' . ltrim($candidatePreviewRel, '/');
            $dest = $paths['finals_dir'] . '/final_' . $index . '_preview.jpg';
            if (@copy($src, $dest)) {
                $out['preview_path'] = publicista_path_to_web($dest);
            }
        }
    }

    list($okMeta, $metaPath) = publicista_job_meta_write($jobId, 'final_' . $index . '_meta.json', $out);
    if ($okMeta) {
        $out['meta_path'] = $metaPath;
    }
    return $out;
}


function publicista_run_image_pipeline($jobId, $uploadedFile = null) {
    $job = publicista_job_get($jobId);
    if (!$job) {
        return array(false, 'No se encontró el trabajo de Publicista.');
    }

    $hasUpload = is_array($uploadedFile) && !empty($uploadedFile['tmp_name']);
    if ($hasUpload) {
        list($okUpload, $savedUpload) = publicista_attach_uploaded_source_image($jobId, $uploadedFile);
        if (!$okUpload) {
            return array(false, $savedUpload);
        }
        $job = publicista_job_get($jobId);
    }

    if (trim((string)publicista_array_get(publicista_array_get($job, 'source_image', array()), 'stored_path', '')) === '') {
        return array(false, 'Primero sube la imagen original de la clienta.');
    }

    $runId = generate_id('pubrun');
    $startedAt = now_datetime();
    $job['estado'] = 'processing';
    $job['pipeline'] = array_merge(publicista_array_get($job, 'pipeline', array()), array(
        'run_id' => $runId,
        'started_at' => $startedAt,
        'finished_at' => '',
        'status' => 'processing',
        'mode' => 'reference_locked_premium',
        'stage' => 'preparing',
        'summary' => 'Preparando base 1:1 limpia, descriptor y generación referenciada sobre la foto original.',
        'selected_candidate_ids' => array(),
        'final_candidate_ids' => array(),
        'total_generated' => 0,
        'total_selected' => 0,
        'batch' => publicista_job_defaults($jobId)['pipeline']['batch'],
    ));
    $job['processing'] = array_merge(publicista_array_get($job, 'processing', array()), array(
        'last_action' => 'run_pipeline_reference_submit',
        'last_started_at' => $startedAt,
        'last_finished_at' => '',
        'last_error' => '',
        'last_error_at' => '',
    ));
    $workflow = publicista_job_workflow($job);
    $workflow['pack_final'] = 0;
    $workflow['pack_finalized_at'] = '';
    $workflow['pack_final_note'] = '';
    $workflow['auto_regenerate'] = 0;
    $job['workflow'] = $workflow;
    $job['candidates'] = array();
    $job['final_images'] = array();
    publicista_job_save($job);

    list($okPrep, $prepOrError) = publicista_prepare_job_engine($jobId, null);
    if (!$okPrep) {
        return array(false, $prepOrError);
    }
    $job = publicista_job_get($jobId);

    $referenceRel = trim((string)publicista_array_get(publicista_array_get($job, 'local_assets', array()), 'prepared_square_path', ''));
    if ($referenceRel === '') {
        return array(false, 'No se pudo preparar la referencia 1:1 de la foto original.');
    }
    $referenceFs = BASE_PATH . '/' . ltrim($referenceRel, '/');
    if (!file_exists($referenceFs)) {
        return array(false, 'No existe en disco la referencia 1:1 preparada.');
    }

    $pipelineImageModel = trim((string)(($job['models'] ?? array())['image'] ?? ''));
    $usePollo = function_exists('publicista_is_pollo_model') && publicista_is_pollo_model($pipelineImageModel);
    $masterPrompt = $usePollo ? publicista_build_pollo_master_prompt($job) : publicista_build_master_prompt($job);
    $variants = $usePollo
        ? array_fill(0, 4, $masterPrompt)
        : publicista_build_prompt_variants($masterPrompt, 6, false, $job);
    publicista_store_prompt_master($jobId, $masterPrompt, $variants);

    $sourceFs = BASE_PATH . '/' . ltrim((string)publicista_array_get(publicista_array_get($job, 'source_image', array()), 'stored_path', ''), '/');
    $candidates = array();
    $polloBatchImages = array();
    $polloBatchMeta = array();
    if ($usePollo) {
        $batchPrompt = trim((string)($variants[0] ?? ''));
        list($okPolloBatch, $polloBatchOrError) = publicista_generate_candidate_images_pollo_batch($jobId, count($variants), $batchPrompt, $pipelineImageModel, $job);
        if (!$okPolloBatch) {
            return array(false, is_string($polloBatchOrError) ? $polloBatchOrError : 'No se pudo generar el lote de candidatas con Pollo.ai.');
        }
        $polloBatchMeta = is_array($polloBatchOrError) ? $polloBatchOrError : array();
        $polloBatchImages = array_values((array)($polloBatchMeta['images'] ?? array()));
    }

    foreach ($variants as $idx => $variantPrompt) {
        $candidateIndex = $idx + 1;
        $candidateId = 'candidate_' . str_pad((string)$candidateIndex, 2, '0', STR_PAD_LEFT);
        $lockedPrompt = $usePollo ? trim((string)$variantPrompt) : publicista_build_reference_locked_prompt($job, $variantPrompt);
        $candidateRow = array(
            'id' => $candidateId,
            'prompt' => $lockedPrompt,
            'base_prompt' => $lockedPrompt,
            'generation' => array(),
            'raw_path' => '',
            'square_path' => '',
            'face_blur_path' => '',
            'preview_path' => '',
            'analysis_json_path' => '',
            'worker_result' => array(),
            'evaluation' => array(),
            'effective_score' => 0,
            'selected' => false,
            'status' => 'error',
            'error' => '',
            'round' => 'reference_locked',
            'manual_blur_applied' => 0,
            'manual_blur_intensity' => 0,
            'manual_blur_shape' => array(),
        );

        if ($usePollo) {
            if (!isset($polloBatchImages[$idx]) || !is_array($polloBatchImages[$idx])) {
                $candidateRow['error'] = 'Pollo.ai: el lote terminó, pero no devolvió suficientes imágenes candidatas.';
                $candidates[] = $candidateRow;
                continue;
            }
            $batchImage = $polloBatchImages[$idx];
            $genOrError = array(
                'prompt' => trim((string)($polloBatchMeta['prompt'] ?? $lockedPrompt)),
                'request_id' => (string)($polloBatchMeta['generation_id'] ?? ''),
                'http_code' => (int)($polloBatchMeta['http_code'] ?? 200),
                'model' => (string)($polloBatchMeta['model'] ?? $pipelineImageModel),
                'mode' => 'pollo_text2image_batch',
                'attempts' => (int)($polloBatchMeta['attempts'] ?? 1),
                'retry_applied' => !empty($polloBatchMeta['retry_applied']) ? 1 : 0,
                'raw_path' => (string)($batchImage['raw_path'] ?? ''),
                'raw_fs_path' => (string)($batchImage['raw_fs_path'] ?? ''),
            );
            $okGen = ($genOrError['raw_fs_path'] !== '' && file_exists($genOrError['raw_fs_path']));
        } else {
            list($okGen, $genOrError) = publicista_generate_candidate_image_from_reference($jobId, $job, $candidateIndex, $lockedPrompt, $referenceFs);
        }

        if (!$okGen) {
            $candidateRow['error'] = is_string($genOrError) ? $genOrError : 'No se pudo generar la candidata.';
            $candidates[] = $candidateRow;
            continue;
        }

        $candidateRow['prompt'] = trim((string)($genOrError['prompt'] ?? $lockedPrompt));
        if (trim((string)($candidateRow['base_prompt'] ?? '')) === '') {
            $candidateRow['base_prompt'] = $lockedPrompt;
        }
        $candidateRow['generation'] = array(
            'request_id' => $genOrError['request_id'],
            'http_code' => $genOrError['http_code'],
            'model' => $genOrError['model'],
            'mode' => $usePollo ? 'pollo_text2image_batch' : 'reference_edit',
            'attempts' => (int)($genOrError['attempts'] ?? 1),
            'retry_applied' => !empty($genOrError['retry_applied']) ? 1 : 0,
        );
        $candidateRow['raw_path'] = $genOrError['raw_path'];

        list($okLocal, $localOrError) = publicista_prepare_arbitrary_image_locally($jobId, $genOrError['raw_fs_path'], preg_replace('/[^a-z0-9_\-]/i', '_', $candidateId), 'candidates_dir');
        if (!$okLocal) {
            $candidateRow['error'] = $localOrError;
            $candidates[] = $candidateRow;
            continue;
        }

        $candidateRow['square_path'] = $localOrError['square_path'];
        $candidateRow['face_blur_path'] = '';
        $candidateRow['preview_path'] = $localOrError['preview_path'];
        $candidateRow['analysis_json_path'] = $localOrError['analysis_json_path'];
        $candidateRow['worker_result'] = $localOrError['worker_result'];

        $squareFs = BASE_PATH . '/' . ltrim($localOrError['square_path'], '/');
        list($okEval, $evalOrError) = publicista_evaluate_candidate_with_openai($jobId, $sourceFs, $squareFs, $candidateId);
        if (!$okEval) {
            $candidateRow['error'] = $evalOrError;
            $candidateRow['status'] = 'needs_review';
            $candidates[] = $candidateRow;
            continue;
        }

        $candidateRow['evaluation'] = $evalOrError;
        $candidateRow['effective_score'] = publicista_candidate_effective_score($candidateRow);
        $candidateRow['status'] = ($candidateRow['effective_score'] >= 60 && !empty($evalOrError['adult_appearing'])) ? 'ok' : 'needs_review';
        $candidates[] = $candidateRow;
    }

    if ($usePollo) {
        $rows = array();
        $finalImages = array();
        $selectedIds = array();
        $finalRank = 0;
        foreach ($candidates as $candidate) {
            $isUsableFinal = trim((string)publicista_array_get($candidate, 'square_path', '')) !== '';
            $candidate['selected'] = $isUsableFinal ? true : false;
            $rows[] = $candidate;
            if (!$isUsableFinal) {
                continue;
            }
            $finalRank++;
            $selectedIds[] = publicista_array_get($candidate, 'id', '');
            $finalImages[] = publicista_build_direct_final_output($jobId, $candidate, $finalRank, $job);
        }
    } else {
        list($rows, $finalImages, $selectedIds) = publicista_rebuild_finals_from_candidates($jobId, $candidates, $job);
    }
    $finalIds = array(); foreach ($finalImages as $f) $finalIds[] = $f['id'];

    $job = publicista_job_get($jobId);
    $workflow = publicista_job_workflow($job);
    $workflow['pack_final'] = 0;
    $workflow['pack_finalized_at'] = '';
    $workflow['pack_final_note'] = '';
    $job['workflow'] = $workflow;
    $job['candidates'] = $rows;
    $job['final_images'] = $finalImages;
    $job['pipeline'] = array_merge(publicista_array_get($job, 'pipeline', array()), array(
        'finished_at' => now_datetime(),
        'status' => count($finalImages) >= 4 ? 'done' : 'needs_review',
        'stage' => 'completed',
        'summary' => count($finalImages) >= 4
            ? ($usePollo
                ? ('Pipeline completado: 4 candidatas generadas con Pollo.ai. Las definitivas arrancan como copia directa de esas candidatas y el refinado pasa a ser manual desde cada final.')
                : ('Pipeline completado: ' . count($rows) . ' candidatas referenciadas y 4 finales refinadas premium listas.'))
            : ('Pipeline completado con revisión pendiente: ' . count($finalImages) . ' finales premium disponibles.'),
        'selected_candidate_ids' => $selectedIds,
        'final_candidate_ids' => $finalIds,
        'total_generated' => count($rows),
        'total_selected' => count($selectedIds),
        'batch' => publicista_job_defaults($jobId)['pipeline']['batch'],
    ));
    $job['processing'] = array_merge(publicista_array_get($job, 'processing', array()), array(
        'last_action' => 'run_pipeline_reference_finalize',
        'last_finished_at' => now_datetime(),
        'last_error' => '',
        'last_error_at' => '',
    ));
    $job['estado'] = count($finalImages) >= 4 ? 'done' : 'needs_review';
    list($okSave, $saved) = publicista_job_save($job);
    if (!$okSave) {
        return array(false, is_string($saved) ? $saved : 'No se pudo guardar el pipeline premium de Publicista.');
    }
    list($okCopy, $copyResult) = publicista_generate_copy_pack($jobId, true);
    $saved = publicista_job_get($jobId);
    if ($saved) {
        $pipelineNow = is_array(publicista_array_get($saved, 'pipeline', array())) ? publicista_array_get($saved, 'pipeline', array()) : array();
        $pipelineNow['summary'] = trim((string)publicista_array_get($pipelineNow, 'summary', '')) . ($okCopy ? ' Textos y títulos generados automáticamente.' : ' Imágenes listas; textos pendientes de regenerar.');
        $saved['pipeline'] = $pipelineNow;
        publicista_job_save($saved);
        $saved = publicista_job_get($jobId);
        if ($saved) {
            publicista_notify_job_generation_finished($saved);
        }
    }
    return array(true, $saved ?: $job);
}


function publicista_rebuild_finals_from_candidates($jobId, $candidates, $job = null) {
    $rows = is_array($candidates) ? $candidates : array();
    usort($rows, function($a, $b) {
        return (int)publicista_array_get($b, 'effective_score', 0) <=> (int)publicista_array_get($a, 'effective_score', 0);
    });
    $selected = array_slice(array_values(array_filter($rows, function($c) {
        return trim((string)publicista_array_get($c, 'square_path', '')) !== '';
    })), 0, 4);
    $selectedIds = array();
    foreach ($selected as $candidate) $selectedIds[] = publicista_array_get($candidate, 'id', '');
    foreach ($rows as $idx => $row) {
        $rows[$idx]['selected'] = in_array(publicista_array_get($row, 'id', ''), $selectedIds, true);
    }
    $finalImages = array();
    $job = is_array($job) ? $job : publicista_job_get($jobId);
    $usePollo = publicista_job_uses_pollo_model($job);
    foreach ($selected as $i => $candidate) {
        $finalImages[] = $usePollo
            ? publicista_build_direct_final_output($jobId, $candidate, $i + 1, $job)
            : publicista_finalize_candidate_output($jobId, $candidate, $i + 1, $job);
    }
    return array($rows, $finalImages, $selectedIds);
}


function publicista_regenerate_candidate($jobId, $candidateId, $refineText = '') {
    $job = publicista_job_get($jobId);
    if (!$job) return array(false, 'No se encontró el trabajo de Publicista.');
    $candidates = is_array(publicista_array_get($job, 'candidates', array())) ? publicista_array_get($job, 'candidates', array()) : array();
    $targetIndex = -1;
    foreach ($candidates as $idx => $cand) {
        if (trim((string)publicista_array_get($cand, 'id', '')) === $candidateId) {
            $targetIndex = $idx;
            break;
        }
    }
    if ($targetIndex < 0) return array(false, 'No se encontró la candidata a regenerar.');

    $referenceRel = trim((string)publicista_array_get(publicista_array_get($job, 'local_assets', array()), 'prepared_square_path', ''));
    if ($referenceRel === '') return array(false, 'No existe la referencia 1:1 preparada del original.');
    $referenceFs = BASE_PATH . '/' . ltrim($referenceRel, '/');
    if (!file_exists($referenceFs)) return array(false, 'La referencia 1:1 del original no existe en disco.');

    $sourceFs = BASE_PATH . '/' . ltrim((string)publicista_array_get(publicista_array_get($job, 'source_image', array()), 'stored_path', ''), '/');
    if (!file_exists($sourceFs)) return array(false, 'La imagen original no existe en disco.');

    $basePrompt = trim((string)publicista_array_get($candidates[$targetIndex], 'base_prompt', ''));
    if ($basePrompt === '') {
        $basePrompt = trim((string)publicista_array_get($candidates[$targetIndex], 'prompt', ''));
    }
    $prompt = $basePrompt;
    if ($prompt === '') {
        if (publicista_job_uses_pollo_model($job)) {
            $prompt = publicista_build_pollo_master_prompt($job) . "\n\n[REINTENTO] Mantén el mismo tipo de mujer, mejorando todavía más el parecido físico, el look comercial y la nitidez general.";
        } else {
            $prompt = publicista_build_reference_locked_prompt($job, publicista_build_master_prompt($job) . ' Prioriza todavía más manos correctas, un único rostro y realismo fotográfico limpio.');
        }
    }
    $refineText = trim((string)$refineText);
    if ($refineText !== '') {
        $prompt .= "\n\n[REFINADO USUARIO]\n" . $refineText;
    }
    $prompt .= "\n- Corrección extra: todavía más fidelidad al rostro y complexión original, manos limpias y fondo 1:1 natural.";

    $genIndex = count($candidates) + 1;
    if (publicista_job_uses_pollo_model($job)) {
        $modelName = trim((string)publicista_array_get(publicista_array_get($job, 'models', array()), 'image', ''));
        list($okGen, $genOrError) = publicista_generate_candidate_image_pollo($jobId, $genIndex, $prompt, $modelName);
    } else {
        list($okGen, $genOrError) = publicista_generate_candidate_image_from_reference($jobId, $job, $genIndex, $prompt, $referenceFs);
    }
    if (!$okGen) return array(false, $genOrError);

    $row = $candidates[$targetIndex];
    if (trim((string)publicista_array_get($row, 'base_prompt', '')) === '') {
        $row['base_prompt'] = $basePrompt !== '' ? $basePrompt : trim((string)($genOrError['prompt'] ?? $prompt));
    }
    $row['prompt'] = trim((string)($genOrError['prompt'] ?? $prompt));
    $row['last_refine_text'] = $refineText;
    $row['generation'] = array(
        'request_id' => $genOrError['request_id'],
        'http_code' => $genOrError['http_code'],
        'model' => $genOrError['model'],
        'mode' => publicista_job_uses_pollo_model($job) ? 'pollo_text2image_retry' : 'reference_edit_retry',
        'attempts' => (int)($genOrError['attempts'] ?? 1),
        'retry_applied' => !empty($genOrError['retry_applied']) ? 1 : 0,
    );
    $row['raw_path'] = $genOrError['raw_path'];
    $row['error'] = '';
    $row['status'] = 'processing';
    $row['round'] = 'manual_retry';

    list($okLocal, $localOrError) = publicista_prepare_arbitrary_image_locally($jobId, $genOrError['raw_fs_path'], preg_replace('/[^a-z0-9_\-]/i', '_', $candidateId) . '_manual', 'candidates_dir');
    if (!$okLocal) return array(false, $localOrError);
    $row['square_path'] = $localOrError['square_path'];
    $row['face_blur_path'] = '';
    $row['preview_path'] = $localOrError['preview_path'];
    $row['analysis_json_path'] = $localOrError['analysis_json_path'];
    $row['worker_result'] = $localOrError['worker_result'];
    $row['manual_blur_applied'] = 0;
    $row['manual_blur_intensity'] = 0;
    $row['manual_blur_shape'] = array();

    $squareFs = BASE_PATH . '/' . ltrim($localOrError['square_path'], '/');
    list($okEval, $evalOrError) = publicista_evaluate_candidate_with_openai($jobId, $sourceFs, $squareFs, $candidateId . '_manual');
    if (!$okEval) {
        $evalErrorText = is_string($evalOrError) ? trim($evalOrError) : 'No se pudo evaluar la candidata regenerada.';
        $row['evaluation'] = array(
            'error' => $evalErrorText,
            'adult_appearing' => true,
            'issues' => array('Evaluación OpenAI no disponible temporalmente.'),
        );
        // Conserva el score anterior para no perder la selección/finales por un fallo temporal de evaluación.
        $row['status'] = 'needs_review';
        $row['error'] = $evalErrorText;
    } else {
        $row['evaluation'] = $evalOrError;
        $row['effective_score'] = publicista_candidate_effective_score($row);
        $row['status'] = ($row['effective_score'] >= 60 && !empty($evalOrError['adult_appearing'])) ? 'ok' : 'needs_review';
    }

    $candidates[$targetIndex] = $row;
    list($candidates, $finalImages, $selectedIds) = publicista_rebuild_finals_from_candidates($jobId, $candidates, $job);

    $workflow = publicista_job_workflow($job);
    $workflow['pack_final'] = 0;
    $workflow['pack_finalized_at'] = '';
    $workflow['pack_final_note'] = '';
    $job['workflow'] = $workflow;
    $job['candidates'] = $candidates;
    $job['final_images'] = $finalImages;
    $job['pipeline'] = array_merge(publicista_array_get($job, 'pipeline', array()), array(
        'finished_at' => now_datetime(),
        'status' => count($finalImages) >= 4 ? 'done' : 'needs_review',
        'summary' => publicista_job_uses_pollo_model($job)
            ? ('Candidata ' . $candidateId . ' regenerada con Pollo.ai. Las definitivas se han recompuesto como copia directa de candidatas.')
            : ('Candidata ' . $candidateId . ' regenerada con referencia real. Finales premium recompuestas automáticamente.'),
        'selected_candidate_ids' => $selectedIds,
        'final_candidate_ids' => array_map(function($row){ return publicista_array_get($row, 'id', ''); }, $finalImages),
        'total_generated' => count($candidates),
        'total_selected' => count($selectedIds),
    ));
    if (!$okEval) {
        $job['pipeline']['summary'] .= ' Evaluación OpenAI pendiente temporalmente; la imagen regenerada sí se aplicó.';
    }
    $job['estado'] = count($finalImages) >= 4 ? 'done' : 'needs_review';
    list($okSave, $saved) = publicista_job_save($job);
    if (!$okSave) return array(false, is_string($saved) ? $saved : 'No se pudo guardar la regeneración de candidata.');
    return array(true, $saved);
}



function publicista_refresh_final_local_assets($jobId, $finalId, $mode = 'refresh') {
    $job = publicista_job_get($jobId);
    if (!$job) return array(false, 'No se encontró el trabajo de Publicista.');

    $finals = is_array(publicista_array_get($job, 'final_images', array())) ? publicista_array_get($job, 'final_images', array()) : array();
    $targetFinal = null;
    $targetIndex = -1;
    foreach ($finals as $idx => $final) {
        if (trim((string)publicista_array_get($final, 'id', '')) === trim((string)$finalId)) {
            $targetFinal = $final;
            $targetIndex = $idx;
            break;
        }
    }
    if (!$targetFinal) return array(false, 'No se encontró la imagen final indicada.');

    $candidateId = trim((string)publicista_array_get($targetFinal, 'source_candidate_id', ''));
    if ($candidateId === '') return array(false, 'La imagen final no tiene candidata de origen.');

    $candidates = is_array(publicista_array_get($job, 'candidates', array())) ? publicista_array_get($job, 'candidates', array()) : array();
    $candidateRow = null;
    foreach ($candidates as $cand) {
        if (trim((string)publicista_array_get($cand, 'id', '')) === $candidateId) {
            $candidateRow = $cand;
            break;
        }
    }
    if (!$candidateRow) return array(false, 'No se encontró la candidata origen de esa final.');

    $usePollo = publicista_job_uses_pollo_model($job);
    $promptOverride = ($usePollo && $mode === 'reframe') ? publicista_build_pollo_final_refine_prompt($job, $candidateRow) : '';
    list($okRefine, $refineOrError) = publicista_refine_final_image($jobId, $job, $candidateRow, (int)publicista_array_get($targetFinal, 'rank', $targetIndex + 1), $promptOverride);
    if (!$okRefine) return array(false, $refineOrError);

    if ($usePollo) {
        $finals[$targetIndex]['refine_proposal_path'] = trim((string)publicista_array_get($refineOrError, 'square_path', ''));
        $finals[$targetIndex]['refine_proposal_preview_path'] = trim((string)publicista_array_get($refineOrError, 'preview_path', ''));
        $finals[$targetIndex]['refine_proposal_prompt'] = trim((string)publicista_array_get($refineOrError, 'prompt', $promptOverride));
        $finals[$targetIndex]['refine_proposal_generation'] = array(
            'request_id' => (string)publicista_array_get($refineOrError, 'request_id', ''),
            'http_code' => (int)publicista_array_get($refineOrError, 'http_code', 0),
            'model' => (string)publicista_array_get($refineOrError, 'model', publicista_ai_config()['image_model']),
            'raw_path' => (string)publicista_array_get($refineOrError, 'raw_path', ''),
        );
        $summary = 'Propuesta refinada generada para ' . $finalId . '.';
    } else {
        $finals[$targetIndex]['final_path'] = trim((string)publicista_array_get($refineOrError, 'square_path', ''));
        $finals[$targetIndex]['square_path'] = trim((string)publicista_array_get($refineOrError, 'square_path', ''));
        $finals[$targetIndex]['preview_path'] = trim((string)publicista_array_get($refineOrError, 'preview_path', ''));
        $finals[$targetIndex]['manual_blur_applied'] = 0;
        $finals[$targetIndex]['manual_blur_intensity'] = 0;
        $finals[$targetIndex]['manual_blur_shape'] = array();
        $finals[$targetIndex]['premium_refined'] = 1;
        $finals[$targetIndex]['premium_refine_error'] = '';
        $finals[$targetIndex]['copied_at'] = now_datetime();
        $summary = ($mode === 'reframe' ? 'Final premium rehecha para ' : 'Final rehecha para ') . $finalId . '.';
    }

    $workflow = publicista_job_workflow($job);
    $workflow['pack_final'] = 0;
    $workflow['pack_finalized_at'] = '';
    $workflow['pack_final_note'] = '';
    $job['workflow'] = $workflow;
    $job['final_images'] = array_values($finals);
    $job['pipeline'] = array_merge(publicista_array_get($job, 'pipeline', array()), array(
        'finished_at' => now_datetime(),
        'status' => count($finals) >= 4 ? 'done' : 'needs_review',
        'summary' => $summary,
        'final_candidate_ids' => array_map(function($row){ return publicista_array_get($row, 'id', ''); }, $finals),
    ));
    $job['processing'] = array_merge(publicista_array_get($job, 'processing', array()), array(
        'last_action' => $mode === 'reframe' ? 'refresh_final_reframe' : 'refresh_final_refresh',
        'last_finished_at' => now_datetime(),
        'last_error' => '',
        'last_error_at' => '',
    ));
    list($okSave, $saved) = publicista_job_save($job);
    if (!$okSave) return array(false, is_string($saved) ? $saved : 'No se pudo guardar la final rehecha.');
    return array(true, $saved);
}


function publicista_apply_manual_blur_to_final($jobId, $finalId, $bx, $by, $bw, $bh, $intensity = 8) {
    $job = publicista_job_get($jobId);
    if (!$job) return array(false, 'No se encontró el trabajo de Publicista.');

    $finals = is_array(publicista_array_get($job, 'final_images', array())) ? publicista_array_get($job, 'final_images', array()) : array();
    $targetIndex = -1;
    foreach ($finals as $idx => $final) {
        if (trim((string)publicista_array_get($final, 'id', '')) === trim((string)$finalId)) {
            $targetIndex = $idx;
            break;
        }
    }
    if ($targetIndex < 0) return array(false, 'No se encontró la imagen final indicada.');

    $squareRel = trim((string)publicista_array_get($finals[$targetIndex], 'square_path', ''));
    if ($squareRel === '') return array(false, 'La imagen final no tiene base square guardada.');
    $squareFs = BASE_PATH . '/' . ltrim($squareRel, '/');
    if (!file_exists($squareFs)) return array(false, 'No existe en disco la imagen square de la final.');

    $bx = max(0.0, min(1.0, (float)$bx));
    $by = max(0.0, min(1.0, (float)$by));
    $bw = max(0.01, min(1.0, (float)$bw));
    $bh = max(0.01, min(1.0, (float)$bh));
    $intensity = max(1, min(20, (int)$intensity));

    $paths = publicista_job_fs_paths($jobId);
    $safeBase = preg_replace('/[^a-z0-9_\-]/i', '_', $finalId);
    $blurFs = $paths['finals_dir'] . '/' . $safeBase . '_manual_blur.jpg';
    $previewFs = $paths['finals_dir'] . '/' . $safeBase . '_manual_blur_preview.jpg';
    $analysisFs = $paths['meta_dir'] . '/' . $safeBase . '_manual_blur_result.json';

    $worker = BASE_PATH . '/tools/publicista_image_worker.py';
    if (!file_exists($worker)) return array(false, 'No se encontró el worker Python de Publicista.');

    $command = 'python3 ' . escapeshellarg($worker)
        . ' apply-manual-blur'
        . ' --input ' . escapeshellarg($squareFs)
        . ' --output-face-blur ' . escapeshellarg($blurFs)
        . ' --output-preview ' . escapeshellarg($previewFs)
        . ' --output-json ' . escapeshellarg($analysisFs)
        . ' --bx ' . escapeshellarg((string)$bx)
        . ' --by ' . escapeshellarg((string)$by)
        . ' --bw ' . escapeshellarg((string)$bw)
        . ' --bh ' . escapeshellarg((string)$bh)
        . ' --intensity ' . escapeshellarg((string)$intensity)
        . ' --preview-size 320';

    $proc = publicista_proc_command($command, publicista_ai_timeouts()['local_worker'], BASE_PATH);
    publicista_job_log_write($jobId, $safeBase . '_manual_blur', $proc);
    if (!$proc['ok']) {
        return array(false, 'Error en worker local (blur manual): ' . ($proc['stderr'] !== '' ? $proc['stderr'] : 'sin detalle'));
    }

    $analysis = file_exists($analysisFs) ? json_decode((string)@file_get_contents($analysisFs), true) : null;
    if (!is_array($analysis) || empty($analysis['ok'])) {
        return array(false, 'El worker de blur manual no devolvió resultado válido.');
    }

    $finals[$targetIndex]['final_path'] = file_exists($blurFs) ? publicista_path_to_web($blurFs) : $finals[$targetIndex]['final_path'];
    $finals[$targetIndex]['preview_path'] = file_exists($previewFs) ? publicista_path_to_web($previewFs) : $finals[$targetIndex]['preview_path'];
    $finals[$targetIndex]['manual_blur_applied'] = 1;
    $finals[$targetIndex]['manual_blur_intensity'] = $intensity;
    $finals[$targetIndex]['manual_blur_shape'] = array('bx' => $bx, 'by' => $by, 'bw' => $bw, 'bh' => $bh);
    $finals[$targetIndex]['copied_at'] = now_datetime();

    $job['final_images'] = array_values($finals);
    $job['processing'] = array_merge(publicista_array_get($job, 'processing', array()), array(
        'last_action' => 'apply_manual_blur',
        'last_finished_at' => now_datetime(),
        'last_error' => '',
        'last_error_at' => '',
    ));
    list($okSave, $saved) = publicista_job_save($job);
    if (!$okSave) return array(false, is_string($saved) ? $saved : 'No se pudo guardar el blur manual.');

    $updated = is_array(publicista_array_get($saved, 'final_images', array())) ? publicista_array_get($saved, 'final_images', array()) : array();
    $updatedFinal = null;
    foreach ($updated as $row) {
        if (trim((string)publicista_array_get($row, 'id', '')) === trim((string)$finalId)) {
            $updatedFinal = $row;
            break;
        }
    }
    if (!$updatedFinal) $updatedFinal = $finals[$targetIndex];

    return array(true, array(
        'final_path' => publicista_array_get($updatedFinal ?: array(), 'final_path', ''),
        'preview_path' => publicista_array_get($updatedFinal ?: array(), 'preview_path', ''),
        'manual_blur_applied' => !empty($updatedFinal['manual_blur_applied']),
        'manual_blur_intensity' => (int)publicista_array_get($updatedFinal ?: array(), 'manual_blur_intensity', $intensity),
    ));
}



function publicista_set_final_variant_choice($jobId, $finalId, $choice) {
    $job = publicista_job_get($jobId);
    if (!$job) return array(false, 'No se encontró el trabajo de Publicista.');
    $finals = is_array(publicista_array_get($job, 'final_images', array())) ? publicista_array_get($job, 'final_images', array()) : array();

    $targetIndex = -1;
    foreach ($finals as $idx => $final) {
        if (trim((string)publicista_array_get($final, 'id', '')) === trim((string)$finalId)) {
            $targetIndex = $idx;
            break;
        }
    }
    if ($targetIndex < 0) return array(false, 'No se encontró la final indicada.');

    $choice = trim((string)$choice);
    if (!in_array($choice, array('candidate', 'refined'), true)) {
        return array(false, 'Elección de variante no válida.');
    }

    $row = $finals[$targetIndex];

    if ($choice === 'refined') {
        $proposalSquare = trim((string)publicista_array_get($row, 'refine_proposal_path', ''));
        if ($proposalSquare === '') {
            return array(false, 'Primero genera una propuesta refinada para esta final.');
        }
        $row['final_path'] = $proposalSquare;
        $row['square_path'] = $proposalSquare;
        $row['preview_path'] = trim((string)publicista_array_get($row, 'refine_proposal_preview_path', ''));
        $row['generation'] = is_array(publicista_array_get($row, 'refine_proposal_generation', array())) ? publicista_array_get($row, 'refine_proposal_generation', array()) : array();
        $row['premium_refined'] = 1;
        $row['premium_refine_error'] = '';
        $row['current_variant'] = 'refined';
    } else {
        $candidateSquare = trim((string)publicista_array_get($row, 'candidate_square_path', ''));
        if ($candidateSquare === '') {
            return array(false, 'Esta final no conserva la copia candidata para volver atrás.');
        }
        $row['final_path'] = $candidateSquare;
        $row['square_path'] = $candidateSquare;
        $row['preview_path'] = trim((string)publicista_array_get($row, 'candidate_preview_path', ''));
        $row['premium_refined'] = 0;
        $row['premium_refine_error'] = 'Se mantiene la candidata original como definitiva.';
        $row['current_variant'] = 'candidate';
    }

    $row['manual_blur_applied'] = 0;
    $row['manual_blur_intensity'] = 0;
    $row['manual_blur_shape'] = array();
    $row['copied_at'] = now_datetime();
    $finals[$targetIndex] = $row;

    $workflow = publicista_job_workflow($job);
    $workflow['pack_final'] = 0;
    $workflow['pack_finalized_at'] = '';
    $workflow['pack_final_note'] = '';
    $job['workflow'] = $workflow;
    $job['final_images'] = array_values($finals);
    $job['pipeline'] = array_merge(publicista_array_get($job, 'pipeline', array()), array(
        'finished_at' => now_datetime(),
        'status' => count($finals) >= 4 ? 'done' : 'needs_review',
        'summary' => $choice === 'refined'
            ? ('La propuesta refinada pasa a ser la definitiva de ' . $finalId . '.')
            : ('Se mantiene la candidata original como definitiva de ' . $finalId . '.'),
        'final_candidate_ids' => array_map(function($item){ return publicista_array_get($item, 'id', ''); }, $finals),
    ));

    list($okSave, $saved) = publicista_job_save($job);
    if (!$okSave) return array(false, is_string($saved) ? $saved : 'No se pudo guardar la elección de la final.');
    return array(true, $saved);
}

function publicista_mark_pack_definitive($jobId) {
    $job = publicista_job_get($jobId);
    if (!$job) return array(false, 'No se encontró el trabajo de Publicista.');
    $finals = is_array(publicista_array_get($job, 'final_images', array())) ? publicista_array_get($job, 'final_images', array()) : array();
    if (count($finals) < 1) return array(false, 'Aún no hay imágenes finales para marcar el pack como definitivo.');

    $workflow = publicista_job_workflow($job);
    $workflow['pack_final'] = 1;
    $workflow['pack_finalized_at'] = now_datetime();
    $workflow['pack_final_note'] = 'Aprobado manualmente desde el panel Publicista.';
    $job['workflow'] = $workflow;
    $job['estado'] = 'done';
    $job['pipeline'] = array_merge(publicista_array_get($job, 'pipeline', array()), array(
        'finished_at' => now_datetime(),
        'status' => 'done',
        'summary' => 'Pack marcado como definitivo por el operador.',
    ));
    list($okSave, $saved) = publicista_job_save($job);
    if (!$okSave) return array(false, is_string($saved) ? $saved : 'No se pudo guardar el estado definitivo del pack.');
    return array(true, $saved);
}





function publicista_default_copy_examples_text($variant = 'neutral') {
    $variant = trim((string)$variant);

    if ($variant === 'suggestive') {
        return "EXAMPLE 1
TÍTULO: Novedad con mucho morbo y trato cercano
TEXTO: Perfil pensado para llamar la atención rápido, con gancho comercial, tono juguetón y una invitación clara a escribir. Sugiere picardía, complicidad y deseo sin caer en detalles explícitos.

EXAMPLE 2
TÍTULO: Mirada intensa, actitud y ganas de repetir
TEXTO: Anuncio con ritmo directo y vendedor, centrado en una presencia muy llamativa, implicación y sensación de experiencia especial. Debe sonar potente, natural y muy publicable.

EXAMPLE 3
TÍTULO: Cercanía, chispa y una experiencia para recordar
TEXTO: Texto breve pero muy comercial, con alguna frase más atrevida, buena cadencia y cierre que invite al contacto. Sugerente sí, explícito no.";
    }

    return "EXAMPLE 1
TÍTULO: Elegancia cercana y trato cuidado
TEXTO: Chica femenina, natural y muy agradable en el trato. Presencia cuidada, ambiente discreto y atención cercana para quien busca una experiencia cómoda, adulta y sin prisas.

EXAMPLE 2
TÍTULO: Imagen impecable y actitud segura
TEXTO: Perfil pensado para destacar por estilo, seguridad y buena presencia. Ideal para anuncios donde conviene sugerir calidad, cercanía y personalidad sin caer en descripciones explícitas.

EXAMPLE 3
TÍTULO: Naturalidad, discreción y buen ambiente
TEXTO: Anuncio sobrio y atractivo, con tono elegante y sugerente. Prioriza la sensación de confianza, cuidado y trato agradable, usando frases fluidas y comerciales.";
}

function publicista_copy_examples_source_meta($source) {
    if ($source === 'loquosex') {
        return array(
            'site' => 'loquosex',
            'label' => 'loquosex.com',
            'url' => 'https://www.loquosex.com/',
        );
    }

    return array(
        'site' => 'destacamos',
        'label' => 'destacamos.net',
        'url' => 'https://www.destacamos.net/1/id/desc/recent_ads.html',
    );
}

function publicista_copy_example_cleanup($text, $maxLen = 280) {
    $text = html_entity_decode((string)$text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/<[^>]+>/', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = preg_replace('/\b\+?\d[\d\s\-]{6,}\b/u', '[tel]', $text);
    $text = preg_replace('/\b(?:telefono|teléfono|whatsapp)\s*[:\-]?\s*\[tel\]\b/iu', '[tel]', $text);
    $text = preg_replace('/\+?\d+\s*€/u', '[precio]', $text);
    $text = preg_replace('/\b([2-6]\d)\s*años?\b/iu', '[edad]', $text);
    $text = preg_replace('/\bNacionalidad\s*:\s*[^.,;\n]+/iu', '', $text);
    $text = trim((string)$text, " \t\n\r\0\x0B,.-|:");

    if ($text === '') return '';

    if (function_exists('mb_substr') && function_exists('mb_strlen')) {
        if (mb_strlen($text, 'UTF-8') > $maxLen) {
            $text = mb_substr($text, 0, $maxLen - 1, 'UTF-8') . '…';
        }
    } else {
        if (strlen($text) > $maxLen) {
            $text = substr($text, 0, $maxLen - 1) . '…';
        }
    }

    return $text;
}

function publicista_copy_is_noise_title($title) {
    $title = function_exists('mb_strtolower') ? mb_strtolower(trim((string)$title), 'UTF-8') : strtolower(trim((string)$title));
    if ($title === '') return true;

    $blocked = array(
        'nuevos perfiles', 'top anuncios', 'ultimos anuncios publicados', 'últimos anuncios publicados',
        'inicio', 'publicar perfil gratis', 'editar perfiles', 'registrate escort', 'regístrate escort',
        'registrate cliente', 'regístrate cliente', 'entrar', 'buscar', 'favoritos', 'ocultos', 'menú'
    );

    foreach ($blocked as $needle) {
        if ($title === $needle) return true;
    }

    return false;
}

function publicista_dom_xpath_from_html($html) {
    $html = trim((string)$html);
    if ($html === '') return array(null, null);

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT);
    libxml_clear_errors();

    return array($dom, new DOMXPath($dom));
}

function publicista_copy_collect_block_text($node, $maxLen = 900) {
    if (!$node) return '';

    $chunks = array();
    $current = $node;
    $steps = 0;

    while ($current && $steps < 4) {
        $txt = publicista_copy_example_cleanup($current->textContent, $maxLen);
        if ($txt !== '') $chunks[] = $txt;
        $current = $current->nextSibling;
        $steps++;
    }

    $text = trim(implode(' ', $chunks));
    return publicista_copy_example_cleanup($text, $maxLen);
}

function publicista_extract_examples_from_headings($html, $max = 6) {
    list($dom, $xp) = publicista_dom_xpath_from_html($html);
    if (!$dom || !$xp) return array();

    $items = array();
    $seen = array();
    $nodes = $xp->query('//h2|//h3|//h4');
    if (!$nodes) return array();

    foreach ($nodes as $node) {
        $title = publicista_copy_example_cleanup($node->textContent, 120);
        if ($title === '' || publicista_copy_is_noise_title($title)) continue;

        $titleLc = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
        if (isset($seen[$titleLc])) continue;

        $container = $node->parentNode;
        $block = publicista_copy_collect_block_text($container, 950);
        if ($block === '' || $block === $title) {
            $block = publicista_copy_collect_block_text($node, 950);
        }

        $body = trim(str_replace($title, '', $block));
        $body = preg_replace('/\b(?:escorts?|masajes? relajantes?|transexuales y travestis|videollamadas, chat y webcam|alquiler)\b/iu', '', $body);
        $body = publicista_copy_example_cleanup($body, 300);
        if ($body === '') continue;
        if (function_exists('mb_strlen')) {
            if (mb_strlen($body, 'UTF-8') < 45) continue;
        } elseif (strlen($body) < 45) {
            continue;
        }

        $seen[$titleLc] = true;
        $items[] = array(
            'title' => $title,
            'body' => $body,
        );

        if (count($items) >= $max) break;
    }

    return $items;
}

function publicista_extract_examples_from_links($html, $max = 6) {
    list($dom, $xp) = publicista_dom_xpath_from_html($html);
    if (!$dom || !$xp) return array();

    $items = array();
    $seen = array();
    $nodes = $xp->query('//a[@href]');
    if (!$nodes) return array();

    foreach ($nodes as $node) {
        $anchorText = publicista_copy_example_cleanup($node->textContent, 180);
        if ($anchorText === '') continue;

        $anchorLc = function_exists('mb_strtolower') ? mb_strtolower($anchorText, 'UTF-8') : strtolower($anchorText);
        if (
            strpos($anchorLc, 'publicar anuncio') !== false ||
            strpos($anchorLc, 'registrarse') !== false ||
            strpos($anchorLc, 'entrar') !== false ||
            strpos($anchorLc, 'inicio') !== false
        ) {
            continue;
        }

        $title = $anchorText;
        if (strpos($anchorText, ',') !== false) {
            $parts = explode(',', $anchorText, 2);
            $title = publicista_copy_example_cleanup(isset($parts[1]) ? $parts[1] : $parts[0], 120);
        } else {
            $title = publicista_copy_example_cleanup($anchorText, 120);
        }

        if ($title === '' || publicista_copy_is_noise_title($title)) continue;

        $titleLc = function_exists('mb_strtolower') ? mb_strtolower($title, 'UTF-8') : strtolower($title);
        if (isset($seen[$titleLc])) continue;

        $container = $node->parentNode;
        $block = publicista_copy_collect_block_text($container, 1100);
        if ($block === '' || $block === $anchorText) {
            $container = ($container && $container->parentNode) ? $container->parentNode : $container;
            $block = publicista_copy_collect_block_text($container, 1100);
        }

        $body = trim(str_replace($anchorText, '', $block));
        $body = preg_replace('/\b(?:escort|masaje|bdsm|travesti|trans)\s*\-\s*[^.]+/iu', '', $body);
        $body = publicista_copy_example_cleanup($body, 320);
        if ($body === '') continue;
        if (function_exists('mb_strlen')) {
            if (mb_strlen($body, 'UTF-8') < 45) continue;
        } elseif (strlen($body) < 45) {
            continue;
        }

        $seen[$titleLc] = true;
        $items[] = array(
            'title' => $title,
            'body' => $body,
        );

        if (count($items) >= $max) break;
    }

    return $items;
}

function publicista_fetch_destacamos_copy_examples($job, $max = 18) {
    $city = trim((string)publicista_array_get($job, 'localidad_snapshot', ''));

    // Múltiples URLs para capturar más ejemplos diversos
    $urls = array();
    if ($city !== '') {
        $urls[] = publicista_ads_build_listing_url($city, '1-chicas-escorts');
    }
    $urls[] = 'https://www.destacamos.net/1/id/desc/recent_ads.html';
    $urls[] = 'https://www.destacamos.net/1/id/desc/top_ads.html';

    $allItems = array();
    $primaryUrl = $urls[0];
    foreach ($urls as $url) {
        if (count($allItems) >= $max) break;
        $html = publicista_ads_fetch_page($url);
        if (!$html) continue;
        $fetched = publicista_extract_examples_from_headings($html, $max);
        foreach ($fetched as $item) {
            // Deduplicar por título
            $title = trim((string)publicista_array_get($item, 'title', ''));
            $alreadyIn = false;
            foreach ($allItems as $existing) {
                if (trim((string)publicista_array_get($existing, 'title', '')) === $title) {
                    $alreadyIn = true;
                    break;
                }
            }
            if (!$alreadyIn && $title !== '') {
                $allItems[] = $item;
            }
        }
    }

    $allItems = array_slice($allItems, 0, $max);

    return array(
        'items' => $allItems,
        'url' => $primaryUrl,
        'ok' => !empty($allItems),
    );
}

function publicista_fetch_loquosex_copy_examples($job, $max = 18) {
    // Múltiples páginas / secciones de loquosex
    $urls = array(
        'https://www.loquosex.com/',
        'https://www.loquosex.com/escorts/',
        'https://www.loquosex.com/anuncios-recientes/',
    );

    $allItems = array();
    $primaryUrl = $urls[0];
    foreach ($urls as $url) {
        if (count($allItems) >= $max) break;
        $html = publicista_ads_fetch_page($url);
        if (!$html) continue;
        $fetched = publicista_extract_examples_from_links($html, $max);
        foreach ($fetched as $item) {
            $title = trim((string)publicista_array_get($item, 'title', ''));
            $alreadyIn = false;
            foreach ($allItems as $existing) {
                if (trim((string)publicista_array_get($existing, 'title', '')) === $title) {
                    $alreadyIn = true;
                    break;
                }
            }
            if (!$alreadyIn && $title !== '') {
                $allItems[] = $item;
            }
        }
    }

    $allItems = array_slice($allItems, 0, $max);

    return array(
        'items' => $allItems,
        'url' => $primaryUrl,
        'ok' => !empty($allItems),
    );
}

function publicista_format_copy_examples_text($items, $prefix = 'EXAMPLE') {
    $items = is_array($items) ? $items : array();
    $lines = array();

    foreach ($items as $idx => $row) {
        $title = publicista_copy_example_cleanup(publicista_array_get($row, 'title', ''), 120);
        $body = publicista_copy_example_cleanup(publicista_array_get($row, 'body', ''), 260);
        if ($title === '' || $body === '') continue;

        $lines[] = $prefix . ' ' . ($idx + 1);
        $lines[] = 'TÍTULO: ' . $title;
        $lines[] = 'TEXTO: ' . $body;
        $lines[] = '';
    }

    return trim(implode("\n", $lines));
}

function publicista_job_copy_pack($job) {
    $copy = is_array(publicista_array_get($job, 'copy_pack', array())) ? publicista_array_get($job, 'copy_pack', array()) : array();
    $copy['desired_tone'] = trim((string)publicista_array_get($copy, 'desired_tone', 'equilibrado'));
    if ($copy['desired_tone'] === '' || !array_key_exists($copy['desired_tone'], publicista_copy_tone_options())) {
        $copy['desired_tone'] = 'equilibrado';
    }
    $copy['versions'] = is_array(publicista_array_get($copy, 'versions', array())) ? publicista_array_get($copy, 'versions', array()) : array();
    $copy['retry_count'] = (int)publicista_array_get($copy, 'retry_count', 0);
    return $copy;
}

function publicista_copy_uppercase($text) {
    $text = trim((string)$text);
    if ($text === '') return '';
    return function_exists('mb_strtoupper') ? mb_strtoupper($text, 'UTF-8') : strtoupper($text);
}

function publicista_copy_lowercase($text) {
    $text = trim((string)$text);
    if ($text === '') return '';
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

function publicista_copy_mb_ucfirst($text) {
    $text = trim((string)$text);
    if ($text === '') return '';
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        $first = mb_substr($text, 0, 1, 'UTF-8');
        $rest = mb_substr($text, 1, null, 'UTF-8');
        return mb_strtoupper($first, 'UTF-8') . $rest;
    }
    return ucfirst($text);
}

function publicista_copy_sentence_case($text) {
    $text = publicista_copy_lowercase($text);
    if ($text === '') return '';
    return publicista_copy_mb_ucfirst($text);
}

function publicista_copy_apply_keyword_uppercase($text, $maxHits = 2) {
    $text = trim((string)$text);
    if ($text === '') return '';

    $patterns = array(
        '/\b(hoy)\b/iu',
        '/\b(ahora)\b/iu',
        '/\b(nueva|novedad|reci[eé]n llegada)\b/iu',
        '/\b(discreta|discreci[oó]n|privada|privado)\b/iu',
        '/\b(elegante|exclusiva|exclusivo)\b/iu',
        '/\b(contacta|escr[ií]beme|ll[aá]mame)\b/iu',
        '/\b(disponible)\b/iu',
        '/\b(real)\b/iu'
    );

    $hits = 0;
    foreach ($patterns as $pattern) {
        if ($hits >= $maxHits) break;
        $text = preg_replace_callback($pattern, function ($m) use (&$hits) {
            $hits++;
            return publicista_copy_uppercase($m[1]);
        }, $text, 1);
    }

    return $text;
}

function publicista_copy_contains_emoji($text) {
    $text = (string)$text;
    return $text !== '' && preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $text);
}

function publicista_copy_pick_emoji($variant = 'neutral', $slot = '') {
    $variant = trim((string)$variant);
    $slot = trim((string)$slot);
    $map = array(
        'neutral' => array('✨', '📍', '💫', '🤍', '💬', '🌟', '🔥'),
        'suggestive' => array('🔥', '💋', '✨', '😈', '💫', '👀', '💌'),
    );
    $pool = isset($map[$variant]) ? $map[$variant] : $map['neutral'];
    $offset = 0;
    if (preg_match('/(\d+)/', $slot, $m)) {
        $offset = max(0, ((int)$m[1]) - 1);
    } elseif ($slot !== '') {
        $offset = (int)sprintf('%u', crc32($slot));
    }
    if (!empty($pool)) {
        $offset = $offset % count($pool);
        $pool = array_merge(array_slice($pool, $offset), array_slice($pool, 0, $offset));
    }
    return $pool[0];
}

function publicista_copy_emphasize_body_text($text) {
    $text = trim((string)$text);
    if ($text === '') return '';
    $patterns = array(
        '/\b(rec[ií]en llegada)\b/iu',
        '/\b(discreci[oó]n)\b/iu',
        '/\b(trato de novia)\b/iu',
        '/\b(ambiente discreto)\b/iu',
        '/\b(cita privada)\b/iu',
        '/\b(escr[ií]beme)\b/iu',
        '/\b(ll[aá]mame)\b/iu',
        '/\b(hoy)\b/iu',
        '/\b(ahora)\b/iu',
        '/\b(exclusiva?)\b/iu',
        '/\b(vip)\b/iu',
        '/\b(nueva)\b/iu',
        '/\b(primera vez)\b/iu',
        '/\b(llama la atenci[oó]n)\b/iu',
        '/\b(buena presencia)\b/iu'
    );
    $applied = 0;
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            $upper = publicista_copy_uppercase($m[1]);
            $text = preg_replace($pattern, $upper, $text, 1, $count);
            if ($count > 0) {
                $applied++;
            }
            if ($applied >= 2) {
                break;
            }
        }
    }
    if ($applied === 0) {
        $sentences = preg_split('/([\.!?]+)/u', $text, 2, PREG_SPLIT_DELIM_CAPTURE);
        if (!empty($sentences[0])) {
            $sentences[0] = publicista_copy_uppercase(trim((string)$sentences[0]));
            $text = trim(implode('', $sentences));
        }
    }
    return $text;
}

function publicista_copy_polish_title($title) {
    $title = trim((string)$title);
    if ($title === '') return '';

    $hash = (int)sprintf('%u', crc32($title)) % 5;

    if ($hash === 0 || $hash === 4) {
        return publicista_copy_uppercase($title);
    }

    if ($hash === 1) {
        return publicista_copy_apply_keyword_uppercase(publicista_copy_sentence_case($title), 2);
    }

    if ($hash === 2) {
        return publicista_copy_apply_keyword_uppercase(publicista_copy_lowercase($title), 3);
    }

    return publicista_copy_apply_keyword_uppercase(publicista_copy_sentence_case($title), 3);
}

function publicista_copy_polish_ad_variant_text($text, $variant = 'neutral', $slot = '') {
    $text = publicista_copy_emphasize_body_text($text);
    if ($text === '') {
        return '';
    }
    if (!publicista_copy_contains_emoji($text)) {
        $text .= ' ' . publicista_copy_pick_emoji($variant, $slot);
        if ((int)sprintf('%u', crc32($slot . '|' . $variant)) % 2 === 0) {
            $text .= ' ' . publicista_copy_pick_emoji($variant, $slot . '_extra');
        }
    }
    return trim($text);
}

function publicista_copy_apply_marketing_polish($version) {
    $version = is_array($version) ? $version : array();
    $titleOptions = array_values((array)publicista_array_get($version, 'title_options', array()));
    foreach ($titleOptions as $idx => $title) {
        $titleOptions[$idx] = publicista_copy_polish_title($title);
    }
    $version['title_options'] = $titleOptions;

    $ads = array_values((array)publicista_array_get($version, 'ads', array()));
    foreach ($ads as $idx => $ad) {
        if (!is_array($ad)) {
            $ad = array();
        }
        $slot = trim((string)publicista_array_get($ad, 'slot', 'slot_' . ($idx + 1)));
        $ad['short_hook'] = publicista_copy_polish_ad_variant_text((string)publicista_array_get($ad, 'short_hook', ''), 'neutral', $slot);
        $ad['title_neutral'] = publicista_copy_polish_title((string)publicista_array_get($ad, 'title_neutral', ''));
        $ad['title_suggestive'] = publicista_copy_polish_title((string)publicista_array_get($ad, 'title_suggestive', ''));
        $ad['body_neutral'] = publicista_copy_polish_ad_variant_text((string)publicista_array_get($ad, 'body_neutral', ''), 'neutral', $slot);
        $ad['body_suggestive'] = publicista_copy_polish_ad_variant_text((string)publicista_array_get($ad, 'body_suggestive', ''), 'suggestive', $slot);
        $ads[$idx] = $ad;
    }
    $version['ads'] = $ads;
    return $version;
}

function publicista_copy_examples_for_job($job) {
    $neutralMeta = publicista_copy_examples_source_meta('destacamos');
    $suggestiveMeta = publicista_copy_examples_source_meta('loquosex');

    $neutralFetched = publicista_fetch_destacamos_copy_examples($job, 18);
    $suggestiveFetched = publicista_fetch_loquosex_copy_examples($job, 18);

    $neutralItems = is_array(publicista_array_get($neutralFetched, 'items', array())) ? publicista_array_get($neutralFetched, 'items', array()) : array();
    $suggestiveItems = is_array(publicista_array_get($suggestiveFetched, 'items', array())) ? publicista_array_get($suggestiveFetched, 'items', array()) : array();

    $neutralFallback = empty($neutralItems);
    $suggestiveFallback = empty($suggestiveItems);

    $neutralText = !$neutralFallback
        ? publicista_format_copy_examples_text($neutralItems, 'EJEMPLO_NEUTRO')
        : publicista_default_copy_examples_text('neutral');

    $suggestiveText = !$suggestiveFallback
        ? publicista_format_copy_examples_text($suggestiveItems, 'EJEMPLO_PICANTE')
        : publicista_default_copy_examples_text('suggestive');

    return array(
        'neutral' => array(
            'text' => $neutralText,
            'source_site' => $neutralMeta['site'],
            'source_label' => $neutralFallback ? ($neutralMeta['label'] . ' (fallback interno)') : $neutralMeta['label'],
            'url' => trim((string)publicista_array_get($neutralFetched, 'url', $neutralMeta['url'])),
            'count' => $neutralFallback ? 3 : count($neutralItems),
            'fallback_used' => $neutralFallback,
        ),
        'suggestive' => array(
            'text' => $suggestiveText,
            'source_site' => $suggestiveMeta['site'],
            'source_label' => $suggestiveFallback ? ($suggestiveMeta['label'] . ' (fallback interno)') : $suggestiveMeta['label'],
            'url' => trim((string)publicista_array_get($suggestiveFetched, 'url', $suggestiveMeta['url'])),
            'count' => $suggestiveFallback ? 3 : count($suggestiveItems),
            'fallback_used' => $suggestiveFallback,
        ),
        'text' => "REFERENCIAS NEUTRAS (destacamos.net)\n" . $neutralText . "\n\nREFERENCIAS MÁS PICANTES (loquosex.com)\n" . $suggestiveText,
        'source_site' => 'destacamos+loquosex',
        'source_label' => 'destacamos.net + loquosex.com',
        'url' => trim((string)publicista_array_get($neutralFetched, 'url', $neutralMeta['url'])),
        'count' => ($neutralFallback ? 3 : count($neutralItems)) + ($suggestiveFallback ? 3 : count($suggestiveItems)),
        'fallback_used' => ($neutralFallback || $suggestiveFallback),
    );
}

function publicista_copy_pack_schema() {
    return array(
        'type' => 'json_schema',
        'name' => 'publicista_copy_pack',
        'strict' => true,
        'schema' => array(
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array('tone_used', 'pack_angle', 'title_options', 'ads', 'publication_notes', 'recommended_order'),
            'properties' => array(
                'tone_used' => array('type' => 'string'),
                'pack_angle' => array('type' => 'string'),
                'title_options' => array(
                    'type' => 'array',
                    'minItems' => 10,
                    'maxItems' => 10,
                    'items' => array('type' => 'string')
                ),
                'recommended_order' => array(
                    'type' => 'array',
                    'minItems' => 10,
                    'maxItems' => 10,
                    'items' => array('type' => 'string')
                ),
                'publication_notes' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string')
                ),
                'ads' => array(
                    'type' => 'array',
                    'minItems' => 10,
                    'maxItems' => 10,
                    'items' => array(
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => array('slot', 'focus', 'short_hook', 'title_neutral', 'title_suggestive', 'body_neutral', 'body_suggestive'),
                        'properties' => array(
                            'slot' => array('type' => 'string'),
                            'focus' => array('type' => 'string'),
                            'short_hook' => array('type' => 'string'),
                            'title_neutral' => array('type' => 'string'),
                            'title_suggestive' => array('type' => 'string'),
                            'body_neutral' => array('type' => 'string'),
                            'body_suggestive' => array('type' => 'string')
                        )
                    )
                )
            )
        )
    );
}

function publicista_copy_extract_age($texts) {
    foreach ((array)$texts as $text) {
        $text = (string)$text;
        if (preg_match('/\b([2-6]\d)\s*años?\b/iu', $text, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/\bedad\s*[:\-]?\s*([2-6]\d)\b/iu', $text, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/\btengo\s+([2-6]\d)\s*años?\b/iu', $text, $m)) {
            return (int)$m[1];
        }
    }

    return 0;
}

function publicista_copy_extract_nationality($texts) {
    $labels = array(
        'española', 'colombiana', 'brasileña', 'venezolana', 'cubana', 'peruana', 'argentina', 'paraguaya',
        'dominicana', 'ecuatoriana', 'chilena', 'mexicana', 'uruguaya', 'boliviana', 'hondureña',
        'nicaragüense', 'costarricense', 'panameña', 'china', 'rumana', 'marroquí', 'latina'
    );

    foreach ((array)$texts as $text) {
        $text = function_exists('mb_strtolower') ? mb_strtolower((string)$text, 'UTF-8') : strtolower((string)$text);
        foreach ($labels as $label) {
            if (strpos($text, $label) !== false) {
                return $label;
            }
        }
        if (preg_match('/\bnacionalidad\s*[:\-]?\s*([^.,;\n]+)/iu', $text, $m)) {
            return publicista_copy_example_cleanup($m[1], 40);
        }
    }

    return '';
}

function publicista_build_copy_client_brief($job) {
    $ref = publicista_find_clienta_any((string)publicista_array_get($job, 'clienta_id', ''), (string)publicista_array_get($job, 'clienta_scope', ''));
    $row = is_array(publicista_array_get($ref, 'row', array())) ? publicista_array_get($ref, 'row', array()) : array();
    $scope = trim((string)publicista_array_get($job, 'clienta_scope', publicista_array_get($ref, 'scope', 'lamami')));

    $publishName = trim((string)publicista_array_get($job, 'publish_name', ''));
    $nombre = $publishName !== '' ? $publishName : trim((string)publicista_array_get($job, 'clienta_nombre_snapshot', publicista_array_get($ref, 'nombre', '')));
    $localidad = trim((string)publicista_array_get($job, 'localidad_snapshot', publicista_array_get($ref, 'localidad', '')));
    $provincia = trim((string)publicista_array_get($job, 'provincia_snapshot', publicista_array_get($ref, 'provincia', '')));
    if ($scope === 'jostal' && $localidad === '') {
        $localidad = 'Burriana';
    }

    $services = trim((string)publicista_array_get($job, 'services_snapshot', publicista_array_get($ref, 'services', publicista_array_get($row, 'servicios', ''))));
    $tarifas = trim((string)publicista_array_get($job, 'tarifas_snapshot', publicista_array_get($ref, 'tarifas', publicista_array_get($row, 'tarifas', ''))));
    $notes = array_filter(array(
        trim((string)publicista_array_get($row, 'notas', '')),
        trim((string)publicista_array_get($row, 'observaciones', '')),
        trim((string)publicista_array_get($row, 'zona', '')),
        trim((string)publicista_array_get($job, 'notas', '')),
    ), function ($v) {
        return trim((string)$v) !== '';
    });
    $notesText = publicista_copy_example_cleanup(implode(' | ', $notes), 380);

    $age = publicista_copy_extract_age(array($nombre, $services, $tarifas, $notesText));
    $nationality = publicista_copy_extract_nationality(array($nombre, $services, $tarifas, $notesText));

    $lines = array();
    if ($nombre !== '') $lines[] = '- Nombre comercial: ' . $nombre;
    $lines[] = '- Origen CRM: ' . ($scope === 'jostal' ? 'Jostal' : 'LaMami');
    if ($localidad !== '') $lines[] = '- Localidad: ' . $localidad;
    if ($provincia !== '') $lines[] = '- Provincia: ' . $provincia;
    if ($age > 0) $lines[] = '- Edad confirmada: ' . $age;
    if ($nationality !== '') $lines[] = '- Nacionalidad confirmada: ' . $nationality;
    if ($services !== '') $lines[] = '- Servicios / enfoque comercial: ' . publicista_copy_example_cleanup($services, 320);
    if ($tarifas !== '') $lines[] = '- Tarifas / condiciones relevantes: ' . publicista_copy_example_cleanup($tarifas, 320);
    if ($notesText !== '') $lines[] = '- Notas útiles de ficha: ' . $notesText;

    return array(
        'name' => $nombre,
        'scope' => $scope,
        'location' => trim($localidad . ($provincia !== '' ? ' · ' . $provincia : '')),
        'localidad' => $localidad,
        'provincia' => $provincia,
        'age' => $age,
        'nationality' => $nationality,
        'services' => $services,
        'tarifas' => $tarifas,
        'notes' => $notesText,
        'text' => implode("\n", $lines),
    );
}

function publicista_build_copy_visual_brief($job) {
    $descriptor = is_array(publicista_array_get(publicista_array_get($job, 'descriptor', array()), 'data', array()))
        ? publicista_array_get(publicista_array_get($job, 'descriptor', array()), 'data', array())
        : array();

    $parts = array();
    if (trim((string)publicista_array_get($descriptor, 'similarity_guidance', '')) !== '') {
        $parts[] = 'Aire general: ' . trim((string)publicista_array_get($descriptor, 'similarity_guidance', ''));
    }
    if (trim((string)publicista_array_get($descriptor, 'body_build', '')) !== '') {
        $parts[] = 'Presencia física: ' . trim((string)publicista_array_get($descriptor, 'body_build', ''));
    }
    if (trim((string)publicista_array_get($descriptor, 'outfit_summary', '')) !== '') {
        $parts[] = 'Estilo / vestuario: ' . trim((string)publicista_array_get($descriptor, 'outfit_summary', ''));
    }
    if (trim((string)publicista_array_get($descriptor, 'expression', '')) !== '') {
        $parts[] = 'Actitud visible: ' . trim((string)publicista_array_get($descriptor, 'expression', ''));
    }
    if (trim((string)publicista_array_get($descriptor, 'pose_summary', '')) !== '') {
        $parts[] = 'Posado / energía: ' . trim((string)publicista_array_get($descriptor, 'pose_summary', ''));
    }

    return trim(implode(' | ', $parts));
}

function publicista_build_copy_context($job) {
    $copy = publicista_job_copy_pack($job);
    $tone = publicista_copy_tone_label(publicista_array_get($copy, 'desired_tone', 'equilibrado'));
    $finals = is_array(publicista_array_get($job, 'final_images', array())) ? publicista_array_get($job, 'final_images', array()) : array();
    $restrictions = publicista_compose_restrictions_summary($job);
    $examplesMeta = publicista_copy_examples_for_job($job);
    $clientBrief = publicista_build_copy_client_brief($job);
    $visualBrief = publicista_build_copy_visual_brief($job);

    $pp = function_exists('publicista_job_production_params') ? publicista_job_production_params($job) : array();
    $copyBrief = trim((string)($pp['copy_brief'] ?? ''));
    $copyPlatforms = is_array($pp['copy_platforms'] ?? null) ? $pp['copy_platforms'] : array();
    $copyAngles = is_array($pp['copy_angles'] ?? null) ? $pp['copy_angles'] : array();

    $neutralExamples = trim((string)publicista_array_get(publicista_array_get($examplesMeta, 'neutral', array()), 'text', ''));
    $suggestiveExamples = trim((string)publicista_array_get(publicista_array_get($examplesMeta, 'suggestive', array()), 'text', ''));

    // --- Sección de plataformas destino ---
    $platformInstructions = '';
    if (!empty($copyPlatforms)) {
        $platformDetails = array(
            'destacamos'  => 'destacamos.net: tono muy neutro, sin ninguna palabra sexual ni de reclamo agresivo; vocabulario elegante y discreto; términos como "TOP", "VIP", "morbo", "viciosa", "fiestera" NO se usan nunca aquí — serían motivo de baneo',
            'loquosex'    => 'loquosex.com: tono sugerente y directo, con gancho sexual sin llegar a lo explícito; palabras de morbo permitidas con moderación',
            'mileroticos' => 'mileroticos.com: estilo similar a loquosex, admite tono picante y directo pero sin vulgaridades extremas',
            'skokka'      => 'skokka.com: internacional, puede ser más directo en el gancho comercial y la llamada a la acción',
        );
        $lines = array("Las plataformas destino seleccionadas y sus restricciones específicas son:");
        foreach ($copyPlatforms as $platform) {
            if (isset($platformDetails[$platform])) {
                $lines[] = '- ' . $platformDetails[$platform];
            }
        }
        $platformInstructions = implode("\n", $lines);
    }

    // --- Ángulos de copy solicitados ---
    $angleInstructions = '';
    if (!empty($copyAngles)) {
        $angleLabels = array(
            'novedad'        => 'Novedad / "recién llegada"',
            'discrecion'     => 'Discreción / privacidad / citas discretas',
            'trato'          => 'Trato personal / cercanía / conversación',
            'elegancia'      => 'Elegancia / premium / exclusividad',
            'morbo'          => 'Morbo / sensualidad / carga erótica (solo para webs permisivas)',
            'disponibilidad' => 'Disponibilidad / horarios / zona geográfica',
        );
        $selected = array();
        foreach ($copyAngles as $angle) {
            if (isset($angleLabels[$angle])) $selected[] = $angleLabels[$angle];
        }
        if (!empty($selected)) {
            $angleInstructions = 'Los 3 anuncios deben explotar preferentemente estos ángulos comerciales: ' . implode(', ', $selected) . '. Repártelos de forma que cada anuncio use uno o dos ángulos distintos.';
        }
    }

    // --- Prompt principal ---
    $prompt = "Eres un experto en marketing, copywriting y psicología humana en español, especializado en anuncios cortos, muy vendedores y altamente clicables para portales del sector adulto en España. "
        . "Sabes detectar qué palabras activan curiosidad, deseo de clic, sensación de oportunidad y ganas de escribir. "
        . "Tu trabajo NO es describir una imagen ni redactar un prompt visual para IA. Tu trabajo es escribir anuncios que parezcan anuncios reales ya publicados, con ritmo comercial, gancho, deseo de contacto y lenguaje de portal. "
        . "Debes sonar humano, persuasivo, natural y muy publicable. "
        . "Evita absolutamente: descripciones gráficas de actos sexuales, vulgaridades extremas, promesas ilegales, cualquier referencia a menores o a no-adultos. "
        . "Puedes ser sugerente y picante en las variantes sugerentes, pero sin entrar nunca en detalles explícitos. "
        . "Tono principal solicitado: {$tone}.";

    // [PRIORIDAD MÁXIMA] Brief del operador para el copy
    if ($copyBrief !== '') {
        $prompt .= "\n\nINSTRUCCIONES PRIORITARIAS DEL OPERADOR PARA LOS TEXTOS (aplícalas con máxima prioridad, deben reflejarse en todos los anuncios):\n" . $copyBrief;
    }

    if ($clientBrief['text'] !== '') {
        $prompt .= "\n\nFICHA REAL DE LA CLIENTA / PRODUCTO:\n" . $clientBrief['text'];
    }

    if ($visualBrief !== '') {
        $prompt .= "\n\nGUIA VISUAL DE APOYO (solo para dar color al copy, no para sonar a prompt de imagen):\n- " . $visualBrief;
    }

    if ($platformInstructions !== '') {
        $prompt .= "\n\nPLATAFORMAS DESTINO Y SUS REGLAS:\n" . $platformInstructions;
    }

    if ($angleInstructions !== '') {
        $prompt .= "\n\nÁNGULOS COMERCIALES SOLICITADOS:\n" . $angleInstructions;
    }

    $neutralCount = substr_count($neutralExamples, 'EJEMPLO_NEUTRO');
    $suggestiveCount = substr_count($suggestiveExamples, 'EJEMPLO_PICANTE');

    $prompt .= "\n\nREFERENCIAS REALES PARA LA VERSIÓN NEUTRA / ELEGANTE — estilo destacamos.net ({$neutralCount} ejemplos reales recientes):\n"
        . ($neutralExamples !== '' ? $neutralExamples : publicista_default_copy_examples_text('neutral'));

    $prompt .= "\n\nREFERENCIAS REALES PARA LA VERSIÓN MÁS PICANTE / CON MÁS MORBO — estilo loquosex.com ({$suggestiveCount} ejemplos reales recientes):\n"
        . ($suggestiveExamples !== '' ? $suggestiveExamples : publicista_default_copy_examples_text('suggestive'));

    $prompt .= "\n\nREGLAS OBLIGATORIAS:\n"
        . "- Mezcla lo mejor del muestreo: aperturas, ritmo, hooks, cierres y llamadas a la acción, pero reescribe TODO desde cero — no copies frases literales.\n"
        . "- Cambia los datos al perfil real: nombre comercial, localidad, edad y nacionalidad SOLO si están confirmados en la ficha. Si no están confirmados, NO los inventes.\n"
        . "- Si el origen CRM es Jostal y falta localidad, usa Burriana.\n"
        . "- VARIANTE NEUTRA (para destacamos.net y webs con moderación estricta): debe ser absolutamente neutro, sin palabras de reclamo sexual como 'morbo', 'viciosa', 'picante', 'fiestera', 'cachonda', 'ardiente', 'caliente', 'húmeda', ni similares. Tono: elegante, reservado, con presencia y estilo. Evita también 'TOP' y 'VIP' como reclamos vacíos. Estos anuncios NO serán baneados en destacamos.\n"
        . "- VARIANTE SUGERENTE (para loquosex.com): puede ser más directa y picante. Usa palabras de gancho, morbo controlado, sensualidad, pero sin actos sexuales explícitos ni vulgaridades extremas.\n"
        . "- Los 10 anuncios deben tener aperturas, estructura, vocabulario, foco y cierre CLARAMENTE diferentes. Si publicas varios activos a la vez, no deben parecer el mismo texto con retoques mínimos.\n"
        . "- Los títulos deben parecer títulos reales de portal: directos, atractivos, con fuerza comercial — no genéricos ni de ficha técnica.\n"
        . "- USA MAYÚSCULAS de forma estratégica. En títulos, priorízalas claramente y sin miedo; idealmente el título entero o sus palabras de mayor impacto deben ir en mayúsculas. En descripciones, usa mayúsculas en puntos concretos para destacar ganchos, CTA, novedad o rasgos diferenciales.\n"
        . "- Los textos deben invitar a escribir: transmitir presencia, trato, ambiente, implicación o morbo según el ángulo.\n"
        . "- Incluye emoticonos/emoji con intención comercial y de forma natural. Debe haber al menos 1 emoji útil por anuncio, pero sin recargar ni parecer spam.\n"
        . "- Máximo 2 emojis por anuncio, colocados estratégicamente. No llenes de emojis.\n"
        . "- Nunca suenes a prompt de generación de imagen: evita mencionar piel, cabello, pose, iluminación, fondo, outfit salvo que sea muy natural y comercial.\n"
        . "- No copies teléfonos, precios, nombres reales ni rasgos físicos concretos de los ejemplos scrapeados.\n";

    if ($restrictions !== '') {
        $prompt .= "- Restricciones adicionales del operador: {$restrictions}.\n";
    }

    $prompt .= "\nCONTEXTO DE PRODUCCIÓN:\n"
        . "- Hay " . count($finals) . " imágenes finales preparadas para acompañar el anuncio.\n"
        . "- El objetivo psicológico es captar la mirada, despertar curiosidad, transmitir valor único y conseguir que el usuario escriba o contacte.\n"
        . "- Cada bloque debe quedar listo para copiar y pegar directamente en el portal sin edición adicional.\n";

    return array(
        'prompt' => $prompt,
        'source_site' => publicista_array_get($examplesMeta, 'source_site', ''),
        'source_label' => publicista_array_get($examplesMeta, 'source_label', ''),
        'source_url' => publicista_array_get($examplesMeta, 'url', ''),
        'examples_count' => (int)publicista_array_get($examplesMeta, 'count', 0),
        'fallback_used' => !empty($examplesMeta['fallback_used']),
        'client_brief' => $clientBrief,
        'visual_brief' => $visualBrief,
        'references' => $examplesMeta,
    );
}

function publicista_build_copy_prompt($job) {
    $context = publicista_build_copy_context($job);

    return $context['prompt']
        . "\n\nSALIDA EXACTA QUE NECESITO:\n"
        . "TÍTULOS (title_options): 10 títulos reales de portal, claramente distintos entre sí.\n"
        . "- Deben cubrir al menos 4 estilos diferentes: impacto directo, elegancia/exclusividad, curiosidad/gancho, cercanía/trato.\n"
        . "- NO hagas microvariantes del mismo título cambiando 1-2 palabras. Escribe 10 títulos genuinamente distintos en estructura y vocabulario.\n"
        . "- Algunos títulos pueden ser neutros (aptos para destacamos), otros más directos (para loquosex). Mezcla estilos.\n"
        . "\nANUNCIOS (ads): 10 anuncios completos, con diferencias REALES y no cosméticas.\n"
        . "- Anuncio 1: enfoque novedad / primera impresión / presencia. Abre con un gancho de primera vez.\n"
        . "- Anuncio 2: enfoque trato / experiencia / calidad del momento. Abre hablando de cómo te hace sentir contactar.\n"
        . "- Anuncio 3: enfoque deseo / morbo controlado / ambiente / sensualidad implícita. Abre con tensión sexual sin ser explícito.\n"
        . "- Cada anuncio debe tener apertura, cuerpo y cierre distintos. Varia la longitud del body (entre 2 y 4 frases).\n"
        . "\nVARIANTE NEUTRA (title_neutral + body_neutral) — estricto para destacamos.net:\n"
        . "- PROHIBIDAS: 'morbo', 'viciosa', 'cachonda', 'ardiente', 'caliente', 'picante', 'fiestera', 'húmeda', 'sucia', 'guarra', 'golfa', y cualquier otra palabra con carga sexual explícita.\n"
        . "- PROHIBIDAS también como reclamos vacíos: 'TOP', 'VIP', '100% real', sin estas palabras el texto es más creíble y pasa la moderación.\n"
        . "- Tono: elegante, reservado, con carácter y presencia. El body_neutral debe aguantar el filtro de moderación de destacamos sin ninguna duda.\n"
        . "- Prioriza: trato, ambiente, estilo personal, naturalidad, discreción, novedad.\n"
        . "\nVARIANTE SUGERENTE (title_suggestive + body_suggestive) — para loquosex.com y similares:\n"
        . "- Puede ser directa y con carga sexual implícita. Morbo controlado, sensualidad explícita sin relato de actos sexuales.\n"
        . "- Sin vulgaridades extremas ni promesas ilegales.\n"
        . "\nFORMATO: JSON estricto según el schema. No añadas explicaciones fuera del JSON. No incluyas comentarios ni notas.";
}




function publicista_copy_single_title_schema() {
    return array(
        'type' => 'json_schema',
        'name' => 'publicista_copy_title',
        'strict' => true,
        'schema' => array(
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array('title'),
            'properties' => array(
                'title' => array('type' => 'string')
            )
        )
    );
}

// Schema fase A: generación amplia (20 títulos candidatos + 12 ángulos de anuncio)
function publicista_copy_wide_schema() {
    return array(
        'type' => 'json_schema',
        'name' => 'publicista_copy_wide',
        'strict' => true,
        'schema' => array(
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array('title_candidates', 'ad_angles'),
            'properties' => array(
                'title_candidates' => array(
                    'type' => 'array',
                    'minItems' => 20,
                    'maxItems' => 20,
                    'items' => array('type' => 'string'),
                ),
                'ad_angles' => array(
                    'type' => 'array',
                    'minItems' => 12,
                    'maxItems' => 12,
                    'items' => array(
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => array('angle', 'hook', 'key_phrase', 'tone_note'),
                        'properties' => array(
                            'angle'     => array('type' => 'string'),
                            'hook'      => array('type' => 'string'),
                            'key_phrase'=> array('type' => 'string'),
                            'tone_note' => array('type' => 'string'),
                        ),
                    ),
                ),
            ),
        ),
    );
}

// Schema validación por plataforma
function publicista_copy_validation_schema() {
    return array(
        'type' => 'json_schema',
        'name' => 'publicista_copy_validation',
        'strict' => true,
        'schema' => array(
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array('titles_check', 'ads_check'),
            'properties' => array(
                'titles_check' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => array('index', 'ok_destacamos', 'ok_loquosex', 'issues'),
                        'properties' => array(
                            'index'         => array('type' => 'integer'),
                            'ok_destacamos' => array('type' => 'boolean'),
                            'ok_loquosex'   => array('type' => 'boolean'),
                            'issues'        => array('type' => 'array', 'items' => array('type' => 'string')),
                        ),
                    ),
                ),
                'ads_check' => array(
                    'type' => 'array',
                    'items' => array(
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => array('slot', 'neutral_ok', 'neutral_issues', 'suggestive_ok', 'suggestive_issues'),
                        'properties' => array(
                            'slot'             => array('type' => 'string'),
                            'neutral_ok'       => array('type' => 'boolean'),
                            'neutral_issues'   => array('type' => 'array', 'items' => array('type' => 'string')),
                            'suggestive_ok'    => array('type' => 'boolean'),
                            'suggestive_issues'=> array('type' => 'array', 'items' => array('type' => 'string')),
                        ),
                    ),
                ),
            ),
        ),
    );
}

function publicista_copy_single_ad_schema() {
    return array(
        'type' => 'json_schema',
        'name' => 'publicista_copy_ad',
        'strict' => true,
        'schema' => array(
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array('focus', 'short_hook', 'title_neutral', 'title_suggestive', 'body_neutral', 'body_suggestive'),
            'properties' => array(
                'focus' => array('type' => 'string'),
                'short_hook' => array('type' => 'string'),
                'title_neutral' => array('type' => 'string'),
                'title_suggestive' => array('type' => 'string'),
                'body_neutral' => array('type' => 'string'),
                'body_suggestive' => array('type' => 'string')
            )
        )
    );
}






function publicista_extract_usage_stats($decoded) {
    $usage = is_array(publicista_array_get($decoded, 'usage', array())) ? publicista_array_get($decoded, 'usage', array()) : array();
    $input = (int)publicista_array_get($usage, 'input_tokens', publicista_array_get($usage, 'prompt_tokens', 0));
    $output = (int)publicista_array_get($usage, 'output_tokens', publicista_array_get($usage, 'completion_tokens', 0));
    $cached = (int)publicista_array_get($usage, 'cached_input_tokens', 0);
    $details = is_array(publicista_array_get($usage, 'input_tokens_details', array())) ? publicista_array_get($usage, 'input_tokens_details', array()) : array();
    if ($cached <= 0) $cached = (int)publicista_array_get($details, 'cached_tokens', 0);
    return array(
        'input_tokens' => $input,
        'output_tokens' => $output,
        'cached_input_tokens' => $cached,
    );
}

function publicista_cost_rules() {
    return array(
        'responses' => array(
            'default' => array(
                'gpt-5.4-mini' => array('input_per_mtok' => 0.75, 'cached_input_per_mtok' => 0.075, 'output_per_mtok' => 4.50),
                'gpt-5.4' => array('input_per_mtok' => 2.50, 'cached_input_per_mtok' => 0.25, 'output_per_mtok' => 15.00),
                'gpt-5.4-nano' => array('input_per_mtok' => 0.20, 'cached_input_per_mtok' => 0.02, 'output_per_mtok' => 1.25),
            ),
            'flex' => array(
                'gpt-5.4-mini' => array('input_per_mtok' => 0.375, 'cached_input_per_mtok' => 0.0375, 'output_per_mtok' => 2.25),
                'gpt-5.4' => array('input_per_mtok' => 1.25, 'cached_input_per_mtok' => 0.125, 'output_per_mtok' => 7.50),
                'gpt-5.4-nano' => array('input_per_mtok' => 0.10, 'cached_input_per_mtok' => 0.01, 'output_per_mtok' => 0.625),
            ),
        ),
        'images' => array(
            'default' => array(
                'gpt-image-1-mini' => array(
                    '1024x1024' => array('low' => 0.005, 'medium' => 0.011, 'high' => 0.036),
                ),
                'gpt-image-1.5' => array(
                    '1024x1024' => array('low' => 0.009, 'medium' => 0.034, 'high' => 0.133),
                ),
                'gpt-image-1' => array(
                    '1024x1024' => array('low' => 0.011, 'medium' => 0.034, 'high' => 0.133),
                ),
            ),
            'batch' => array(
                'gpt-image-1-mini' => array(
                    '1024x1024' => array('low' => 0.0025, 'medium' => 0.0055, 'high' => 0.018),
                ),
                'gpt-image-1.5' => array(
                    '1024x1024' => array('low' => 0.0045, 'medium' => 0.017, 'high' => 0.0665),
                ),
                'gpt-image-1' => array(
                    '1024x1024' => array('low' => 0.0055, 'medium' => 0.017, 'high' => 0.0665),
                ),
            ),
        ),
    );
}

function publicista_register_response_cost($jobId, $response, $kind) {
    $job = publicista_job_get($jobId);
    if (!$job) return;
    $costs = is_array(publicista_array_get($job, 'costs', array())) ? publicista_array_get($job, 'costs', array()) : array();
    $usage = publicista_extract_usage_stats(publicista_array_get($response, 'decoded', array()));
    $model = trim((string)publicista_array_get($response, 'used_model', publicista_array_get(publicista_array_get($response, 'decoded', array()), 'model', publicista_ai_config()['descriptor_model'])));
    $tier = trim((string)publicista_array_get($response, 'service_tier', 'default'));
    if ($tier === '' || $tier === 'auto' || $tier === 'priority') $tier = 'default';
    $rules = publicista_cost_rules();
    $tierRules = is_array(publicista_array_get(publicista_array_get($rules, 'responses', array()), $tier, array())) ? publicista_array_get(publicista_array_get($rules, 'responses', array()), $tier, array()) : array();
    $price = publicista_array_get($tierRules, $model, array('input_per_mtok' => 0.0, 'cached_input_per_mtok' => 0.0, 'output_per_mtok' => 0.0));
    $inputTokens = (int)publicista_array_get($usage, 'input_tokens', 0);
    $cachedTokens = min((int)publicista_array_get($usage, 'cached_input_tokens', 0), $inputTokens);
    $uncachedTokens = max(0, $inputTokens - $cachedTokens);
    $inputCost = ($uncachedTokens / 1000000) * (float)publicista_array_get($price, 'input_per_mtok', 0);
    $cachedCost = ($cachedTokens / 1000000) * (float)publicista_array_get($price, 'cached_input_per_mtok', publicista_array_get($price, 'input_per_mtok', 0));
    $outputCost = ((float)publicista_array_get($usage, 'output_tokens', 0) / 1000000) * (float)publicista_array_get($price, 'output_per_mtok', 0);
    $delta = $inputCost + $cachedCost + $outputCost;

    $costs['response_calls_count'] = (int)publicista_array_get($costs, 'response_calls_count', 0) + 1;
    $costs['input_tokens'] = (int)publicista_array_get($costs, 'input_tokens', 0) + $inputTokens;
    $costs['output_tokens'] = (int)publicista_array_get($costs, 'output_tokens', 0) + (int)publicista_array_get($usage, 'output_tokens', 0);
    $costs['cached_input_tokens'] = (int)publicista_array_get($costs, 'cached_input_tokens', 0) + $cachedTokens;
    $costs['estimated_usd_responses'] = round((float)publicista_array_get($costs, 'estimated_usd_responses', 0) + $delta, 6);
    $costs['estimated_usd_total'] = round((float)publicista_array_get($costs, 'estimated_usd_images', 0) + (float)$costs['estimated_usd_responses'], 6);
    $costs['last_breakdown'] = array(
        'kind' => $kind,
        'model' => $model,
        'service_tier' => $tier,
        'input_tokens' => $inputTokens,
        'cached_input_tokens' => $cachedTokens,
        'output_tokens' => (int)publicista_array_get($usage, 'output_tokens', 0),
        'estimated_delta_usd' => round($delta, 6),
    );
    $costs['last_cost_update_at'] = now_datetime();
    $job['costs'] = $costs;
    publicista_job_save($job);
}

function publicista_register_image_generation_cost($jobId, $model, $quality, $size, $count, $pricingMode = 'default') {
    $job = publicista_job_get($jobId);
    if (!$job) return;
    $costs = is_array(publicista_array_get($job, 'costs', array())) ? publicista_array_get($job, 'costs', array()) : array();
    $rules = publicista_cost_rules();
    $modeRules = is_array(publicista_array_get(publicista_array_get($rules, 'images', array()), $pricingMode, array())) ? publicista_array_get(publicista_array_get($rules, 'images', array()), $pricingMode, array()) : array();
    $price = (float)publicista_array_get(publicista_array_get(publicista_array_get($modeRules, $model, array()), $size, array()), $quality, 0);
    $delta = $price * max(1, (int)$count);

    $costs['image_generations_count'] = (int)publicista_array_get($costs, 'image_generations_count', 0) + max(1, (int)$count);
    $costs['estimated_usd_images'] = round((float)publicista_array_get($costs, 'estimated_usd_images', 0) + $delta, 6);
    $costs['estimated_usd_total'] = round((float)$costs['estimated_usd_images'] + (float)publicista_array_get($costs, 'estimated_usd_responses', 0), 6);
    $costs['last_breakdown'] = array(
        'kind' => 'image_generation',
        'pricing_mode' => $pricingMode,
        'model' => $model,
        'quality' => $quality,
        'size' => $size,
        'count' => max(1, (int)$count),
        'estimated_delta_usd' => round($delta, 6),
    );
    $costs['last_cost_update_at'] = now_datetime();
    $job['costs'] = $costs;
    publicista_job_save($job);
}

function publicista_register_batch_job_cost($jobId, $kind) {
    $job = publicista_job_get($jobId);
    if (!$job) return;
    $costs = is_array(publicista_array_get($job, 'costs', array())) ? publicista_array_get($job, 'costs', array()) : array();
    $costs['batch_jobs_count'] = (int)publicista_array_get($costs, 'batch_jobs_count', 0) + 1;
    $costs['last_breakdown'] = array(
        'kind' => 'batch_job',
        'batch_kind' => $kind,
        'estimated_delta_usd' => 0,
    );
    $costs['last_cost_update_at'] = now_datetime();
    $job['costs'] = $costs;
    publicista_job_save($job);
}

function publicista_build_copy_export_text($job, $version) {
    $lines = array();
    $lines[] = 'PACK PUBLICISTA';
    $lines[] = 'Trabajo: ' . trim((string)publicista_array_get($job, 'nombre_trabajo', ''));
    $lines[] = 'Clienta: ' . trim((string)publicista_array_get($job, 'clienta_nombre_snapshot', ''));
    $lines[] = 'Fecha: ' . trim((string)publicista_array_get($version, 'created_at', now_datetime()));
    $lines[] = 'Tono: ' . publicista_copy_tone_label(publicista_array_get($version, 'tone', 'equilibrado'));
    $lines[] = 'Enfoque: ' . trim((string)publicista_array_get($version, 'pack_angle', ''));
    $lines[] = '';
    $lines[] = 'IMÁGENES FINALES';
    foreach ((array)publicista_array_get($job, 'final_images', array()) as $img) {
        $lines[] = '- ' . trim((string)publicista_array_get($img, 'id', 'final')) . ': ' . trim((string)publicista_array_get($img, 'final_path', ''));
    }
    $lines[] = '';
    $lines[] = 'TÍTULOS';
    foreach ((array)publicista_array_get($version, 'title_options', array()) as $title) {
        $lines[] = '- ' . trim((string)$title);
    }
    $lines[] = '';
    $lines[] = 'ANUNCIOS';
    foreach ((array)publicista_array_get($version, 'ads', array()) as $ad) {
        $lines[] = '--- ' . strtoupper(trim((string)publicista_array_get($ad, 'slot', 'slot'))) . ' ---';
        $lines[] = 'FOCUS: ' . trim((string)publicista_array_get($ad, 'focus', ''));
        $lines[] = 'HOOK: ' . trim((string)publicista_array_get($ad, 'short_hook', ''));
        $lines[] = 'TÍTULO NEUTRO: ' . trim((string)publicista_array_get($ad, 'title_neutral', ''));
        $lines[] = 'TÍTULO SUGERENTE: ' . trim((string)publicista_array_get($ad, 'title_suggestive', ''));
        $lines[] = 'TEXTO NEUTRO: ' . trim((string)publicista_array_get($ad, 'body_neutral', ''));
        $lines[] = 'TEXTO SUGERENTE: ' . trim((string)publicista_array_get($ad, 'body_suggestive', ''));
        $lines[] = '';
    }
    if (!empty($version['publication_notes']) && is_array($version['publication_notes'])) {
        $lines[] = 'NOTAS DE PUBLICACIÓN';
        foreach ($version['publication_notes'] as $note) {
            $lines[] = '- ' . trim((string)$note);
        }
        $lines[] = '';
    }
    return trim(implode("
", $lines)) . "
";
}

function publicista_current_copy_version($job) {
    $copy = publicista_job_copy_pack($job);
    $currentId = trim((string)publicista_array_get($copy, 'current_version_id', ''));
    foreach ((array)publicista_array_get($copy, 'versions', array()) as $version) {
        if ($currentId !== '' && trim((string)publicista_array_get($version, 'id', '')) === $currentId) {
            return $version;
        }
    }
    $versions = (array)publicista_array_get($copy, 'versions', array());
    return !empty($versions) ? $versions[0] : null;
}

function publicista_save_current_copy_version($jobId, $version) {
    $job = publicista_job_get($jobId);
    if (!$job) return array(false, 'No se encontró el trabajo de Publicista.');

    $copy = publicista_job_copy_pack($job);
    $versionId = trim((string)publicista_array_get($version, 'id', ''));
    if ($versionId === '') return array(false, 'Versión de textos inválida.');

    $jsonName = 'copy_pack_' . $versionId . '.json';
    $txtName = 'copy_pack_' . $versionId . '.txt';
    $exportText = publicista_build_copy_export_text($job, $version);

    list($okJson, $jsonPath) = publicista_job_meta_write($jobId, $jsonName, $version);
    if ($okJson) $version['parsed_json_path'] = $jsonPath;

    list($okTxt, $txtPath) = publicista_job_meta_write($jobId, $txtName, $exportText);
    if ($okTxt) $version['export_txt_path'] = $txtPath;

    list($okJson2, $jsonPath2) = publicista_job_meta_write($jobId, 'copy_pack_current.json', $version);
    if ($okJson2) $version['export_json_path'] = $jsonPath2;

    if ($okJson) {
        publicista_job_meta_write($jobId, $jsonName, $version);
    }

    $versions = array_values((array)publicista_array_get($copy, 'versions', array()));
    $found = false;

    foreach ($versions as $idx => $row) {
        if (trim((string)publicista_array_get($row, 'id', '')) === $versionId) {
            $versions[$idx] = $version;
            $found = true;
            break;
        }
    }

    if (!$found) {
        array_unshift($versions, $version);
    }

    $copy['versions'] = array_slice($versions, 0, 20);
    $copy['current_version_id'] = $versionId;
    $copy['current_summary'] = trim((string)publicista_array_get($version, 'pack_angle', ''));
    $copy['current_export_text'] = $exportText;
    $copy['current_export_txt_path'] = trim((string)publicista_array_get($version, 'export_txt_path', ''));
    $copy['current_export_json_path'] = trim((string)publicista_array_get($version, 'export_json_path', ''));
    $copy['generated_at'] = trim((string)publicista_array_get($version, 'created_at', now_datetime()));
    $copy['last_error'] = '';
    $copy['last_error_at'] = '';

    $job['copy_pack'] = $copy;
    $job['processing'] = array_merge(publicista_array_get($job, 'processing', array()), array(
        'last_action' => 'update_copy_part',
        'last_error' => '',
        'last_error_at' => '',
        'last_finished_at' => now_datetime(),
    ));

    publicista_job_save($job);
    return array(true, publicista_job_get($jobId));
}

function publicista_regenerate_copy_title_option($jobId, $titleIndex) {
    $job = publicista_job_get($jobId);
    if (!$job) return array(false, 'No se encontró el trabajo de Publicista.');

    $cfg = publicista_ai_config();
    if (!$cfg['configured']) return array(false, 'OpenAI no está configurado para regenerar títulos de Publicista.');

    $version = publicista_current_copy_version($job);
    if (!$version) return array(false, 'Todavía no existe un pack de textos para regenerar un título.');

    $titles = array_values((array)publicista_array_get($version, 'title_options', array()));
    if ($titleIndex < 0 || !isset($titles[$titleIndex])) return array(false, 'Índice de título inválido.');

    $context = publicista_build_copy_context($job);
    $existingTitles = array();

    foreach ($titles as $idx => $title) {
        if ($idx === $titleIndex) continue;
        $existingTitles[] = trim((string)$title);
    }

    $prompt = $context['prompt']
        . " Necesito SOLO 1 título nuevo para sustituir un título ya generado. "
        . "Debe ser breve, usable en portales, coherente con el pack, con buen gancho psicológico y distinto de estos títulos ya existentes: " . implode(' | ', $existingTitles) . ". "
        . "Mezcla el estilo de casing del pack: no fuerces siempre MAYÚSCULAS completas; a veces usa minúscula o frase normal con palabras clave en MAYÚSCULAS. No devuelvas explicación adicional.";

    $payload = array_merge(publicista_response_payload_defaults('copy_title', $cfg['descriptor_model']), array(
        'model' => $cfg['descriptor_model'],
        'store' => false,
        'input' => array(
            array('role' => 'system', 'content' => 'Devuelve exclusivamente un JSON válido siguiendo el esquema. No añadas explicaciones fuera del JSON.'),
            array('role' => 'user', 'content' => array(
                array('type' => 'input_text', 'text' => $prompt),
            )),
        ),
        'text' => array('format' => publicista_copy_single_title_schema()),
    ));

    $response = publicista_openai_json_request('/v1/responses', $payload, $cfg['timeouts']['responses']);
    publicista_job_log_write($jobId, 'copy_title_regeneration_' . $titleIndex, $response);

    if (!$response['ok']) {
        return array(false, 'Falló la regeneración del título: ' . ($response['error'] !== '' ? $response['error'] : 'sin detalle'));
    }

    $parsed = json_decode(publicista_response_output_text($response['decoded']), true);
    if (!is_array($parsed)) return array(false, 'OpenAI no devolvió JSON válido para el título.');

    $newTitle = trim((string)publicista_array_get($parsed, 'title', ''));
    if ($newTitle === '') return array(false, 'OpenAI devolvió un título vacío.');

    $version['title_options'][$titleIndex] = publicista_copy_polish_title($newTitle);
    $version['updated_at'] = now_datetime();

    list($okSave, $saved) = publicista_save_current_copy_version($jobId, $version);
    if (!$okSave) return array(false, $saved);

    publicista_register_response_cost($jobId, $response, 'text_pack');
    return array(true, $saved);
}

function publicista_regenerate_copy_ad_slot($jobId, $slot) {
    $job = publicista_job_get($jobId);
    if (!$job) return array(false, 'No se encontró el trabajo de Publicista.');

    $cfg = publicista_ai_config();
    if (!$cfg['configured']) return array(false, 'OpenAI no está configurado para regenerar anuncios de Publicista.');

    $version = publicista_current_copy_version($job);
    if (!$version) return array(false, 'Todavía no existe un pack de textos para regenerar un anuncio.');

    $ads = array_values((array)publicista_array_get($version, 'ads', array()));
    $targetIndex = -1;
    $targetAd = array();

    foreach ($ads as $idx => $ad) {
        if (trim((string)publicista_array_get($ad, 'slot', '')) === $slot) {
            $targetIndex = $idx;
            $targetAd = $ad;
            break;
        }
    }

    if ($targetIndex < 0) return array(false, 'No se encontró el bloque de anuncio a regenerar.');

    $context = publicista_build_copy_context($job);
    $prompt = $context['prompt']
        . " Necesito regenerar SOLO un bloque de anuncio del pack actual. "
        . "El slot a sustituir es: {$slot}. "
        . "Mantén coherencia con el resto del pack. "
        . "Si regeneras la variante neutral, mantenla especialmente prudente para destacamos.net: discreta, elegante y apta para filtros de moderacion. "
        . "Bloque actual a sustituir (no lo copies literalmente): " . json_encode($targetAd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ". "
        . "Devuelve un único objeto con focus, short_hook, title_neutral, title_suggestive, body_neutral y body_suggestive.";

    $payload = array_merge(publicista_response_payload_defaults('copy_ad', $cfg['descriptor_model']), array(
        'model' => $cfg['descriptor_model'],
        'store' => false,
        'input' => array(
            array('role' => 'system', 'content' => 'Devuelve exclusivamente un JSON válido siguiendo el esquema. No añadas explicaciones fuera del JSON.'),
            array('role' => 'user', 'content' => array(
                array('type' => 'input_text', 'text' => $prompt),
            )),
        ),
        'text' => array('format' => publicista_copy_single_ad_schema()),
    ));

    $response = publicista_openai_json_request('/v1/responses', $payload, $cfg['timeouts']['responses']);
    publicista_job_log_write($jobId, 'copy_ad_regeneration_' . preg_replace('/[^a-z0-9_\-]/i', '_', $slot), $response);

    if (!$response['ok']) {
        return array(false, 'Falló la regeneración del anuncio: ' . ($response['error'] !== '' ? $response['error'] : 'sin detalle'));
    }

    $parsed = json_decode(publicista_response_output_text($response['decoded']), true);
    if (!is_array($parsed)) return array(false, 'OpenAI no devolvió JSON válido para el anuncio.');

    $version['ads'][$targetIndex] = array(
        'slot' => $slot,
        'focus' => trim((string)publicista_array_get($parsed, 'focus', '')),
        'short_hook' => publicista_copy_polish_ad_variant_text((string)publicista_array_get($parsed, 'short_hook', ''), 'neutral', $slot),
        'title_neutral' => publicista_copy_polish_title((string)publicista_array_get($parsed, 'title_neutral', '')),
        'title_suggestive' => publicista_copy_polish_title((string)publicista_array_get($parsed, 'title_suggestive', '')),
        'body_neutral' => publicista_copy_polish_ad_variant_text((string)publicista_array_get($parsed, 'body_neutral', ''), 'neutral', $slot),
        'body_suggestive' => publicista_copy_polish_ad_variant_text((string)publicista_array_get($parsed, 'body_suggestive', ''), 'suggestive', $slot),
    );
    $version['updated_at'] = now_datetime();

    list($okSave, $saved) = publicista_save_current_copy_version($jobId, $version);
    if (!$okSave) return array(false, $saved);

    publicista_register_response_cost($jobId, $response, 'text_pack');
    return array(true, $saved);
}

// ---------------------------------------------------------------------------
// Generación de copy en dos fases + validación automática
// ---------------------------------------------------------------------------

function publicista_copy_wide_prompt($contextPrompt) {
    return $contextPrompt
        . "\n\nFASE 1 — GENERACIÓN AMPLIA. Necesito:\n"
        . "- 20 títulos candidatos (title_candidates). Deben cubrir estilos muy distintos: impacto directo, "
        . "curiosidad/gancho, elegancia/exclusividad, cercanía/trato, sugerente controlado, novedad. "
        . "Una parte deben ser aptos para destacamos (sin palabras sexuales), y otra parte más directos, siempre sin caer en spam.\n"
        . "- Los títulos deben salir con mentalidad de marketing puro: hechos para captar la mirada, provocar clic y generar curiosidad real.\n"
        . "- Mezcla estilos de casing con intención comercial: algunos títulos totalmente en MAYÚSCULAS, otros en minúscula o frase normal con solo 1-3 palabras clave en MAYÚSCULAS.\n"
        . "- 12 ángulos de anuncio (ad_angles). Cada ángulo tiene: 'angle' (nombre del enfoque), "
        . "'hook' (primera frase de gancho), 'key_phrase' (frase clave del cuerpo), 'tone_note' (nota de tono para redactar la versión final).\n"
        . "- Los hooks deben ser muy llamativos y comerciales, con psicología de curiosidad, oportunidad o deseo de contacto.\n"
        . "- Los 12 ángulos deben ser genuinamente distintos en enfoque, vocabulario y emoción que activan.\n"
        . "No escribas todavía los anuncios completos — solo los títulos candidatos y los ángulos de construcción.";
}

function publicista_copy_refine_prompt($contextPrompt, $wideResult) {
    $titlesJson = json_encode(publicista_array_get($wideResult, 'title_candidates', array()), JSON_UNESCAPED_UNICODE);
    $anglesJson = json_encode(publicista_array_get($wideResult, 'ad_angles', array()), JSON_UNESCAPED_UNICODE);

    return $contextPrompt
        . "\n\nFASE 2 — SELECCIÓN Y REDACCIÓN FINAL.\n"
        . "Tienes estos 20 títulos candidatos generados en fase 1:\n" . $titlesJson . "\n\n"
        . "Y estos 12 ángulos de anuncio:\n" . $anglesJson . "\n\n"
        . "TAREA:\n"
        . "1. Selecciona los 10 mejores títulos de los 20 candidatos. Criterio: máxima diversidad de estilo, "
        . "fuerza comercial real, al menos 5 aptos para destacamos.net (sin palabras sexuales). "
        . "Descarta los que suenen genéricos o sean demasiado parecidos entre sí.\n"
        . "2. Los títulos finales deben mezclar estilos de mayúsculas con intención psicológica: algunos enteros en MAYÚSCULAS, otros en minúscula o frase normal usando MAYÚSCULAS solo en palabras clave.\n"
        . "3. De los 12 ángulos, elige los 10 más potentes y distintos entre sí. Con cada uno redacta un anuncio completo "
        . "(title_neutral, body_neutral, title_suggestive, body_suggestive) siguiendo las reglas ya indicadas.\n"
        . "4. En las descripciones usa MAYÚSCULAS en momentos estratégicos para remarcar gancho, novedad, CTA o atributos diferenciales, sin pasarte ni parecer spam.\n"
        . "5. Incluye emoticonos/emoji útiles y naturales para reforzar clic, cercanía o morbo controlado, sin sobrecargar.\n"
        . "6. El pack_angle debe resumir en 1 frase el enfoque conjunto del pack.\n"
        . "7. publication_notes: 3-5 notas prácticas de publicación (en qué portal usar qué variante, qué estilos conviene alternar, etc.).\n"
        . "8. recommended_order: los 10 slots en orden recomendado de publicación.\n"
        . "Devuelve el JSON final completo según el schema. No expliques nada fuera del JSON.";
}

function publicista_validate_copy_pack($jobId, $version, $cfg) {
    $titleOptions = array_values((array)publicista_array_get($version, 'title_options', array()));
    $ads = array_values((array)publicista_array_get($version, 'ads', array()));
    if (empty($titleOptions) && empty($ads)) return null;

    $titlesText = '';
    foreach ($titleOptions as $idx => $title) {
        $titlesText .= 'Título ' . ($idx + 1) . ': ' . trim((string)$title) . "\n";
    }
    $adsText = '';
    foreach ($ads as $ad) {
        $slot = trim((string)publicista_array_get($ad, 'slot', 'Anuncio'));
        $adsText .= "--- {$slot} ---\n"
            . 'Variante neutra (para destacamos.net): [' . trim((string)publicista_array_get($ad, 'title_neutral', '')) . '] '
            . trim((string)publicista_array_get($ad, 'body_neutral', '')) . "\n"
            . 'Variante sugerente (para loquosex.com): [' . trim((string)publicista_array_get($ad, 'title_suggestive', '')) . '] '
            . trim((string)publicista_array_get($ad, 'body_suggestive', '')) . "\n\n";
    }

    $validationPrompt =
        "Eres un moderador de contenido para portales de anuncios adultos en España. Tu trabajo es revisar textos publicitarios.\n\n"
        . "REGLAS PARA destacamos.net (variante neutra):\n"
        . "- PROHIBIDAS estas palabras o similares: morbo, viciosa, cachonda, ardiente, caliente, picante, fiestera, húmeda, "
        . "sucia, guarra, golfa, pervertida, fogosa, calentorra, y cualquier término con carga sexual explícita.\n"
        . "- PROHIBIDAS como reclamos vacíos: TOP, VIP, 100% real.\n"
        . "- Tono elegante, reservado. Descuento si hay insinuaciones sexuales directas.\n\n"
        . "REGLAS PARA loquosex.com (variante sugerente):\n"
        . "- Permitido: morbo controlado, sensualidad implícita, doble sentido.\n"
        . "- PROHIBIDO: relatos de actos sexuales, vulgaridades extremas (polla, coño, follar, etc.), "
        . "referencias a no-adultos, promesas ilegales.\n\n"
        . "TEXTOS A REVISAR:\n\n"
        . "TÍTULOS:\n" . $titlesText . "\n"
        . "ANUNCIOS:\n" . $adsText
        . "\nDevuelve JSON según el schema. Para titles_check, incluye una entrada por cada título (index desde 0). "
        . "Para ads_check, una entrada por slot. Si un texto está OK, issues es array vacío [].";

    $payload = array_merge(publicista_response_payload_defaults('copy_validation', $cfg['descriptor_model']), array(
        'model' => $cfg['descriptor_model'],
        'store' => false,
        'input' => array(
            array('role' => 'system', 'content' => 'Devuelve exclusivamente un JSON válido siguiendo el schema. No añadas nada fuera del JSON.'),
            array('role' => 'user', 'content' => array(
                array('type' => 'input_text', 'text' => $validationPrompt),
            )),
        ),
        'text' => array('format' => publicista_copy_validation_schema()),
    ));

    $response = publicista_openai_json_request('/v1/responses', $payload, $cfg['timeouts']['responses']);
    publicista_job_log_write($jobId, 'copy_pack_validation', array(
        'ok' => $response['ok'],
        'http_code' => $response['http_code'],
        'error' => $response['error'],
        'request_id' => $response['request_id'],
    ));
    if (!$response['ok']) return null;

    $text = publicista_response_output_text($response['decoded']);
    $parsed = json_decode($text, true);
    if (!is_array($parsed)) return null;

    publicista_register_response_cost($jobId, $response, 'copy_validation');
    return $parsed;
}

function publicista_generate_copy_pack($jobId, $force = false) {
    $job = publicista_job_get($jobId);
    if (!$job) return array(false, 'No se encontró el trabajo de Publicista.');
    $cfg = publicista_ai_config();
    if (!$cfg['configured']) return array(false, 'OpenAI no está configurado para generar textos de Publicista.');

    $copy = publicista_job_copy_pack($job);
    if (!$force && trim((string)publicista_array_get($copy, 'current_version_id', '')) !== '') {
        return array(true, $job);
    }
    if (empty(publicista_array_get(publicista_array_get($job, 'descriptor', array()), 'data', array())) && empty(publicista_array_get($job, 'final_images', array()))) {
        return array(false, 'Primero prepara el origen o genera al menos una imagen final antes de pedir los textos.');
    }

    $context = publicista_build_copy_context($job);
    $contextPrompt = $context['prompt'];

    // ------------------------------------------------------------------
    // FASE A — Generación amplia: 20 títulos + 12 ángulos
    // ------------------------------------------------------------------
    $promptWide = publicista_copy_wide_prompt($contextPrompt);
    $payloadWide = array_merge(publicista_response_payload_defaults('copy_wide', $cfg['descriptor_model']), array(
        'model' => $cfg['descriptor_model'],
        'store' => false,
        'input' => array(
            array('role' => 'system', 'content' => 'Devuelve exclusivamente un JSON válido siguiendo el esquema. No añadas explicaciones fuera del JSON.'),
            array('role' => 'user', 'content' => array(
                array('type' => 'input_text', 'text' => $promptWide),
            )),
        ),
        'text' => array('format' => publicista_copy_wide_schema()),
    ));

    $responseWide = publicista_openai_json_request('/v1/responses', $payloadWide, $cfg['timeouts']['responses']);
    publicista_job_log_write($jobId, 'copy_pack_phase_a', array(
        'ok' => $responseWide['ok'], 'http_code' => $responseWide['http_code'],
        'error' => $responseWide['error'], 'request_id' => $responseWide['request_id'],
    ));

    if (!$responseWide['ok']) {
        $copy['retry_count'] = (int)publicista_array_get($copy, 'retry_count', 0) + 1;
        $copy['last_error'] = 'Falló la fase A del pack de textos: ' . ($responseWide['error'] !== '' ? $responseWide['error'] : 'sin detalle');
        $copy['last_error_at'] = now_datetime();
        $job['copy_pack'] = $copy;
        $job['processing'] = array_merge(publicista_array_get($job, 'processing', array()), array(
            'last_action' => 'generate_copy_pack_phase_a',
            'last_error' => $copy['last_error'], 'last_error_at' => $copy['last_error_at'],
            'last_finished_at' => now_datetime(),
        ));
        publicista_job_save($job);
        return array(false, $copy['last_error']);
    }

    $wideText = publicista_response_output_text($responseWide['decoded']);
    $wideResult = json_decode($wideText, true);
    if (!is_array($wideResult)) {
        // Si fase A falla el JSON, caemos al flujo de una sola fase como fallback
        $wideResult = array('title_candidates' => array(), 'ad_angles' => array());
    }
    publicista_register_response_cost($jobId, $responseWide, 'copy_wide');

    // ------------------------------------------------------------------
    // FASE B — Refinado y selección: 10 títulos finales + 10 anuncios completos
    // ------------------------------------------------------------------
    $promptRefine = publicista_copy_refine_prompt($contextPrompt, $wideResult);
    $payloadRefine = array_merge(publicista_response_payload_defaults('copy_pack', $cfg['descriptor_model']), array(
        'model' => $cfg['descriptor_model'],
        'store' => false,
        'input' => array(
            array('role' => 'system', 'content' => 'Devuelve exclusivamente un JSON válido siguiendo el esquema. No añadas explicaciones fuera del JSON.'),
            array('role' => 'user', 'content' => array(
                array('type' => 'input_text', 'text' => $promptRefine),
            )),
        ),
        'text' => array('format' => publicista_copy_pack_schema()),
    ));

    $response = publicista_openai_json_request('/v1/responses', $payloadRefine, $cfg['timeouts']['responses']);
    $logPayload = $response;
    if (!empty($logPayload['raw_body']) && strlen($logPayload['raw_body']) > 150000) {
        $logPayload['raw_body'] = substr($logPayload['raw_body'], 0, 150000) . "\n...truncado...";
    }
    publicista_job_log_write($jobId, 'copy_pack_phase_b', $logPayload);

    if (!$response['ok']) {
        $copy['retry_count'] = (int)publicista_array_get($copy, 'retry_count', 0) + 1;
        $copy['last_error'] = 'Falló la fase B del pack de textos: ' . ($response['error'] !== '' ? $response['error'] : 'sin detalle');
        $copy['last_error_at'] = now_datetime();
        $job['copy_pack'] = $copy;
        $job['processing'] = array_merge(publicista_array_get($job, 'processing', array()), array(
            'last_action' => 'generate_copy_pack_phase_b',
            'last_error' => $copy['last_error'], 'last_error_at' => $copy['last_error_at'],
            'last_finished_at' => now_datetime(),
        ));
        publicista_job_save($job);
        return array(false, $copy['last_error']);
    }

    $outputText = publicista_response_output_text($response['decoded']);
    $parsed = json_decode($outputText, true);
    if (!is_array($parsed)) {
        $copy['retry_count'] = (int)publicista_array_get($copy, 'retry_count', 0) + 1;
        $copy['last_error'] = 'OpenAI devolvió texto, pero no un JSON válido para el pack de textos (fase B).';
        $copy['last_error_at'] = now_datetime();
        $job['copy_pack'] = $copy;
        publicista_job_save($job);
        return array(false, $copy['last_error']);
    }

    $versionId = generate_id('copyver');
    $createdAt = now_datetime();
    $rawName = 'copy_pack_' . $versionId . '_raw.json';
    $jsonName = 'copy_pack_' . $versionId . '.json';
    $txtName  = 'copy_pack_' . $versionId . '.txt';
    list($okRaw, $rawPath) = publicista_job_meta_write($jobId, $rawName, array(
        'phase_a' => $wideResult,
        'phase_b' => $response['decoded'],
    ));

    $version = array(
        'id'           => $versionId,
        'created_at'   => $createdAt,
        'model'        => $cfg['descriptor_model'],
        'generation_phases' => 2,
        'request_id'   => (string)publicista_array_get($response, 'request_id', ''),
        'http_code'    => (int)publicista_array_get($response, 'http_code', 0),
        'tone'         => trim((string)publicista_array_get($copy, 'desired_tone', 'equilibrado')),
        'pack_angle'   => trim((string)publicista_array_get($parsed, 'pack_angle', '')),
        'title_options'      => array_values((array)publicista_array_get($parsed, 'title_options', array())),
        'title_candidates'   => array_values((array)publicista_array_get($wideResult, 'title_candidates', array())),
        'ads'                => array_values((array)publicista_array_get($parsed, 'ads', array())),
        'publication_notes'  => array_values((array)publicista_array_get($parsed, 'publication_notes', array())),
        'recommended_order'  => array_values((array)publicista_array_get($parsed, 'recommended_order', array())),
        'raw_response_path'  => $okRaw ? $rawPath : '',
        'parsed_json_path'   => '',
        'export_txt_path'    => '',
        'export_json_path'   => '',
        'validation'         => null,
        'source_summary'     => trim((string)publicista_array_get(publicista_array_get($job, 'descriptor', array()), 'summary', '')),
        'reference_source_site'  => trim((string)($context['source_site'] ?? '')),
        'reference_source_label' => trim((string)($context['source_label'] ?? '')),
        'reference_source_url'   => trim((string)($context['source_url'] ?? '')),
        'reference_examples_count' => (int)($context['examples_count'] ?? 0),
    );
    publicista_register_response_cost($jobId, $response, 'text_pack');
    $version = publicista_copy_apply_marketing_polish($version);

    // ------------------------------------------------------------------
    // FASE C — Validación automática por plataforma
    // ------------------------------------------------------------------
    $validation = publicista_validate_copy_pack($jobId, $version, $cfg);
    if (is_array($validation)) {
        $version['validation'] = $validation;
    }

    list($okJson, $jsonPath) = publicista_job_meta_write($jobId, $jsonName, $version);
    if ($okJson) $version['parsed_json_path'] = $jsonPath;
    $exportText = publicista_build_copy_export_text($job, $version);
    list($okTxt, $txtPath) = publicista_job_meta_write($jobId, $txtName, $exportText);
    if ($okTxt) $version['export_txt_path'] = $txtPath;
    list($okJson2, $jsonPath2) = publicista_job_meta_write($jobId, 'copy_pack_current.json', $version);
    if ($okJson2) $version['export_json_path'] = $jsonPath2;
    if ($okJson) {
        publicista_job_meta_write($jobId, $jsonName, $version);
    }

    $versions = array_values((array)publicista_array_get($copy, 'versions', array()));
    array_unshift($versions, $version);
    $copy['versions'] = array_slice($versions, 0, 20);
    $copy['current_version_id'] = $versionId;
    $copy['current_summary'] = trim((string)publicista_array_get($version, 'pack_angle', ''));
    $copy['current_export_text'] = $exportText;
    $copy['current_export_txt_path'] = $version['export_txt_path'];
    $copy['current_export_json_path'] = $version['export_json_path'];
    $copy['generated_at'] = $createdAt;
    $copy['last_error'] = '';
    $copy['last_error_at'] = '';

    $job['copy_pack'] = $copy;
    $job['processing'] = array_merge(publicista_array_get($job, 'processing', array()), array(
        'last_action' => 'generate_copy_pack_two_phase',
        'last_openai_request_id' => (string)publicista_array_get($response, 'request_id', ''),
        'last_openai_http_code'  => (int)publicista_array_get($response, 'http_code', 0),
        'last_error' => '', 'last_error_at' => '',
        'last_finished_at' => now_datetime(),
    ));
    publicista_job_save($job);
    $job = publicista_job_get($jobId);
    return array(true, $job);
}

function publicista_clienta_usage_summary($clientaId) {
    $rows = publicista_jobs_for_clienta($clientaId);
    $out = array('jobs' => 0, 'definitive' => 0, 'copies' => 0);
    foreach ($rows as $row) {
        $out['jobs']++;
        $wf = publicista_job_workflow($row);
        if (!empty($wf['pack_final'])) $out['definitive']++;
        $copy = publicista_job_copy_pack($row);
        if (trim((string)publicista_array_get($copy, 'current_version_id', '')) !== '') $out['copies']++;
    }
    return $out;
}

function publicista_recursive_copy_dir($src, $dst) {
    if (!file_exists($src)) return true;
    if (is_file($src)) {
        $dir = dirname($dst);
        if (!publicista_ensure_dir($dir)) return false;
        return @copy($src, $dst);
    }
    if (!publicista_ensure_dir($dst)) return false;
    $items = @scandir($src);
    if ($items === false) return false;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if (!publicista_recursive_copy_dir($src . '/' . $item, $dst . '/' . $item)) return false;
    }
    return true;
}

function publicista_recursive_replace($value, $old, $new) {
    if (is_array($value)) {
        $out = array();
        foreach ($value as $k => $v) $out[$k] = publicista_recursive_replace($v, $old, $new);
        return $out;
    }
    if (is_string($value)) return str_replace($old, $new, $value);
    return $value;
}

function publicista_duplicate_job($jobId) {
    $job = publicista_job_get($jobId);
    if (!$job) return array(false, 'No se encontró el trabajo a duplicar.');
    $newId = generate_id('pubjob');
    $oldRel = 'data/publicista/jobs/' . $jobId;
    $newRel = 'data/publicista/jobs/' . $newId;
    $new = publicista_recursive_replace($job, $oldRel, $newRel);
    $new['id'] = $newId;
    $new['asset_dirs'] = publicista_build_job_asset_dirs($newId);
    $new['nombre_trabajo'] = trim((string)publicista_array_get($job, 'nombre_trabajo', 'Trabajo Publicista')) . ' (copia)';
    $new['created_at'] = now_datetime();
    $new['updated_at'] = now_datetime();
    $new['processing'] = publicista_job_defaults($newId)['processing'];
    $new['costs'] = publicista_job_defaults($newId)['costs'];
    $new['pipeline'] = array_merge(publicista_job_defaults($newId)['pipeline'], array(
        'status' => 'draft',
        'summary' => 'Trabajo duplicado desde ' . $jobId . ' como base.',
    ));
    $wf = publicista_job_workflow($new);
    $wf['pack_final'] = 0;
    $wf['pack_finalized_at'] = '';
    $wf['pack_final_note'] = 'Duplicado desde ' . $jobId . '.';
    $new['workflow'] = $wf;
    $new['estado'] = (!empty($new['final_images']) || trim((string)publicista_array_get(publicista_job_copy_pack($new), 'current_version_id', '')) !== '') ? 'needs_review' : 'draft';

    $srcPaths = publicista_job_fs_paths($jobId);
    $dstPaths = publicista_job_fs_paths($newId);
    publicista_ensure_job_dirs($newId);
    if (file_exists($srcPaths['job_root']) && !publicista_recursive_copy_dir($srcPaths['job_root'], $dstPaths['job_root'])) {
        return array(false, 'No se pudieron copiar los archivos del trabajo base.');
    }

    list($okSave, $saved) = publicista_job_save($new);
    if (!$okSave) return array(false, is_string($saved) ? $saved : 'No se pudo guardar el trabajo duplicado.');
    return array(true, $saved);
}

function publicista_ads_prices() {
    return array(
        'top' => 9.0,
        'auto7' => 7.0,
        'auto4' => 4.0,
        'premium' => 15.0,
    );
}

function publicista_ads_categories() {
    return array(
        '' => 'Todas las categorías',
        '1-chicas-escorts' => 'Escorts (Acompañantes)',
        '2-masajes-eroticos' => 'Masajes relajantes',
        '3-travestis' => 'Transexuales y travestis',
        '9-escorts-lujo' => 'Escorts de lujo',
    );
}

function publicista_ads_slug_part($value) {
    return rawurlencode(trim((string)$value));
}

function publicista_ads_build_source_variants($city, $province = '', $cat = '') {
    $city = trim((string)$city);
    $province = trim((string)$province);
    $cat = trim((string)$cat);
    if ($city === '') return array();

    $cityEnc = publicista_ads_slug_part($city);
    $provinceEnc = publicista_ads_slug_part($province);
    $base = 'https://www.destacamos.net';

    $variants = array();
    if ($cat !== '') {
        $variants[] = array(
            'code' => 'cat_keyword',
            'label' => 'Categoría + keyword',
            'weight' => 0.95,
            'url' => $base . '/' . $cat . '/keyword-' . $cityEnc . '/listings.html',
        );
    }

    $variants[] = array(
        'code' => 'keyword',
        'label' => 'Keyword directa',
        'weight' => 0.75,
        'url' => $base . '/keyword-' . $cityEnc . '/listings.html',
    );

    if ($provinceEnc !== '') {
        $variants[] = array(
            'code' => 'keyword_province',
            'label' => 'Keyword + provincia',
            'weight' => 0.85,
            'url' => $base . '/keyword-' . $cityEnc . '/localidad-' . $provinceEnc . '/listings.html',
        );
        if ($cat !== '') {
            $variants[] = array(
                'code' => 'cat_keyword_province',
                'label' => 'Categoría + keyword + provincia',
                'weight' => 1.00,
                'url' => $base . '/' . $cat . '/keyword-' . $cityEnc . '/localidad-' . $provinceEnc . '/listings.html',
            );
        }
    }

    $seen = array();
    $out = array();
    foreach ($variants as $row) {
        $url = trim((string)$row['url']);
        if ($url === '' || isset($seen[$url])) continue;
        $seen[$url] = true;
        $out[] = $row;
    }
    return $out;
}

function publicista_ads_build_listing_urls($city, $province = '', $cat = '') {
    $out = array();
    foreach (publicista_ads_build_source_variants($city, $province, $cat) as $row) {
        $out[] = $row['url'];
    }
    return $out;
}

function publicista_ads_build_listing_url($city, $cat, $province = '') {
    $variants = publicista_ads_build_source_variants($city, $province, $cat);
    if (empty($variants)) return '';
    $preferred = end($variants);
    return trim((string)($preferred['url'] ?? ''));
}

function publicista_ads_fetch_page($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => 18,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => array(
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
            ),
        ));
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($status === 200 && is_string($body) && strlen($body) > 800) ? $body : null;
    }

    $ctx = stream_context_create(array('http' => array(
        'user_agent' => 'Mozilla/5.0 (compatible)',
        'timeout' => 15,
        'ignore_errors' => false,
    )));
    $body = @file_get_contents($url, false, $ctx);
    return ($body && strlen($body) > 800) ? $body : null;
}

function publicista_ads_lower($text) {
    return function_exists('mb_strtolower') ? mb_strtolower((string)$text, 'UTF-8') : strtolower((string)$text);
}

function publicista_ads_clean_text($text, $maxLen = 1800) {
    $text = html_entity_decode((string)$text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/<[^>]+>/', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim((string)$text);
    if ($text === '') return '';
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') > $maxLen) {
            $text = mb_substr($text, 0, $maxLen - 1, 'UTF-8') . '…';
        }
    } elseif (strlen($text) > $maxLen) {
        $text = substr($text, 0, $maxLen - 1) . '…';
    }
    return $text;
}

function publicista_ads_node_text($node, $maxLen = 1800) {
    if (!$node) return '';
    return publicista_ads_clean_text($node->textContent, $maxLen);
}

function publicista_ads_node_html($node, $maxLen = 4000) {
    if (!$node || !isset($node->ownerDocument)) return '';
    $html = (string)$node->ownerDocument->saveHTML($node);
    return publicista_ads_clean_text($html, $maxLen);
}

function publicista_ads_count_listing_links_in_node($xp, $node) {
    if (!$xp || !$node) return 0;
    $nodes = $xp->query('self::a[contains(@href, "details.html")] | .//a[contains(@href, "details.html")]', $node);
    return $nodes ? (int)$nodes->length : 0;
}

function publicista_ads_pick_card_node($xp, $node, $maxDepth = 8) {
    if (!$node) return $node;

    $candidate = $node;
    $current = $node;
    for ($i = 0; $i < max(1, (int)$maxDepth) && $current; $i++) {
        $linksCount = publicista_ads_count_listing_links_in_node($xp, $current);
        if ($linksCount !== 1) {
            break;
        }

        $candidate = $current;
        $parent = $current->parentNode;
        if (!$parent || $parent->nodeType !== XML_ELEMENT_NODE) {
            break;
        }
        $current = $parent;
    }

    return $candidate;
}

function publicista_ads_normalize_listing_href($href) {
    $href = trim((string)$href);
    if ($href === '') return '';
    $href = preg_replace('/#.*$/', '', $href);
    $parts = @parse_url($href);
    if (is_array($parts) && !empty($parts['path'])) {
        return trim((string)$parts['path']);
    }
    return $href;
}

function publicista_ads_detect_card_badges($text, $html = '') {
    $text = publicista_ads_lower($text);
    $html = publicista_ads_lower($html);
    $haystack = trim($text . ' ' . $html);

    $premium = false;
    $top = (bool)preg_match('/(^|[^[:alnum:]])tops?([^[:alnum:]]|$)/u', $haystack);
    $auto = (bool)preg_match(
        '/auto[\s\-_]*(subida|subidas|renueva|renuevas|renovar|renovar\w*)|autorenueva\w*|autosubida\w*/u',
        $haystack
    );

    return array(
        'premium' => $premium,
        'top' => $top,
        'auto' => $auto,
    );
}

function publicista_ads_extract_listing_cards($html) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT);
    libxml_clear_errors();

    $xp = new DOMXPath($dom);
    $nodes = $xp->query('//a[contains(@href, "details.html")]');
    if (!$nodes) return array();

    $items = array();
    foreach ($nodes as $node) {
        $href = trim((string)$node->getAttribute('href'));
        if ($href === '') continue;
        $title = publicista_ads_node_text($node, 180);

        $cardNode = publicista_ads_pick_card_node($xp, $node, 8);
        $block = publicista_ads_node_text($cardNode, 2200);
        if ($block === '') {
            $block = $title;
        }
        if ($block === '') continue;

        $key = publicista_ads_normalize_listing_href($href);
        if ($key === '') $key = $href;
        if (isset($items[$key])) continue;

        $blockHtml = $cardNode ? publicista_ads_node_html($cardNode, 5000) : '';
        $badges = publicista_ads_detect_card_badges($block, $blockHtml);
        $badgeCount = 0;
        foreach ($badges as $flag) if ($flag) $badgeCount++;

        $items[$key] = array(
            'href' => $href,
            'href_key' => $key,
            'title' => $title,
            'text' => $block,
            'premium' => $badges['premium'],
            'top' => $badges['top'],
            'auto' => $badges['auto'],
            'badge_count' => $badgeCount,
        );
    }

    return array_values($items);
}

function publicista_ads_parse_stats($html) {
    $cards = publicista_ads_extract_listing_cards($html);
    if (!empty($cards)) {
        return publicista_ads_stats_from_cards($cards);
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_COMPACT);
    libxml_clear_errors();

    $xp = new DOMXPath($dom);
    $premium = 0;
    $htmlLower = publicista_ads_lower($html);
    $top = (int)preg_match_all('/>\s*tops?\s*</u', $htmlLower);
    $auto = (int)preg_match_all('/>\s*(?:autosubida|autosubidas|autorenueva\w*)\s*</u', $htmlLower);

    if (($top + $auto) === 0) {
        $top = (int)preg_match_all('/(^|[^[:alnum:]])tops?([^[:alnum:]]|$)/u', $htmlLower);
        $auto = (int)preg_match_all('/(?:auto[\s\-_]*(?:subida|subidas|renueva|renuevas|renovar|renovar\w*)|autorenueva\w*|autosubida\w*)/u', $htmlLower);
    }

    $total = 0;
    if (preg_match('/(\d[\d.]*)\s+perfiles?/iu', $html, $m)) {
        $total = (int) str_replace('.', '', $m[1]);
    }
    if ($total === 0) {
        $total = (int) preg_match_all('/\/details\.html/', $html);
    }

    return array(
        'premium' => 0,
        'top' => max(0, (int)$top),
        'auto' => max(0, (int)$auto),
        'total' => $total,
        'paid_profiles' => max(0, (int)$top + (int)$auto),
        'free_profiles' => max(0, $total - max(0, (int)$top + (int)$auto)),
        'combo_top_auto' => 0,
        'combo_premium_top_auto' => 0,
        'combo_premium_top' => 0,
        'combo_premium_auto' => 0,
        'multi_badge_profiles' => 0,
        'cards_detected' => 0,
    );
}

function publicista_ads_stats_from_cards($cards) {
    $cards = is_array($cards) ? array_values($cards) : array();

    $premium = 0;
    $top = 0;
    $auto = 0;
    $comboTopAuto = 0;
    $multiBadge = 0;
    $free = 0;
    $paidProfiles = 0;

    foreach ($cards as $card) {
        $p = !empty($card['premium']);
        $t = !empty($card['top']);
        $a = !empty($card['auto']);

        if ($p) $premium++;
        if ($t) $top++;
        if ($a) $auto++;
        if ($t && $a) $comboTopAuto++;
        if ($p || $t || $a) $paidProfiles++;
        if ((($p ? 1 : 0) + ($t ? 1 : 0) + ($a ? 1 : 0)) >= 2) $multiBadge++;
        if (!$p && !$t && !$a) $free++;
    }

    return array(
        'premium' => $premium,
        'top' => $top,
        'auto' => $auto,
        'total' => count($cards),
        'paid_profiles' => $paidProfiles,
        'free_profiles' => $free,
        'combo_top_auto' => $comboTopAuto,
        'combo_premium_top_auto' => 0,
        'combo_premium_top' => 0,
        'combo_premium_auto' => 0,
        'multi_badge_profiles' => $multiBadge,
        'cards_detected' => count($cards),
    );
}

function publicista_ads_merge_unique_cards_from_sources($sources) {
    $merged = array();

    foreach ((array)$sources as $source) {
        if (empty($source['scraped'])) continue;
        $cards = is_array($source['cards'] ?? null) ? $source['cards'] : array();
        foreach ($cards as $card) {
            if (!is_array($card)) continue;

            $hrefKey = trim((string)($card['href_key'] ?? ''));
            if ($hrefKey === '') {
                $hrefKey = publicista_ads_normalize_listing_href((string)($card['href'] ?? ''));
            }
            if ($hrefKey === '') continue;

            if (!isset($merged[$hrefKey])) {
                $card['href_key'] = $hrefKey;
                $merged[$hrefKey] = $card;
                continue;
            }

            $existing = $merged[$hrefKey];
            $existing['premium'] = !empty($existing['premium']) || !empty($card['premium']);
            $existing['top'] = !empty($existing['top']) || !empty($card['top']);
            $existing['auto'] = !empty($existing['auto']) || !empty($card['auto']);
            $existing['badge_count'] = (($existing['premium'] ? 1 : 0) + ($existing['top'] ? 1 : 0) + ($existing['auto'] ? 1 : 0));

            if (strlen((string)($card['text'] ?? '')) > strlen((string)($existing['text'] ?? ''))) {
                $existing['text'] = (string)($card['text'] ?? '');
            }
            if (trim((string)($existing['title'] ?? '')) === '' && trim((string)($card['title'] ?? '')) !== '') {
                $existing['title'] = (string)($card['title'] ?? '');
            }
            if (trim((string)($existing['href'] ?? '')) === '' && trim((string)($card['href'] ?? '')) !== '') {
                $existing['href'] = (string)($card['href'] ?? '');
            }

            $merged[$hrefKey] = $existing;
        }
    }

    return array_values($merged);
}

function publicista_ads_source_metric_values($sources, $metric) {
    $rows = array();
    foreach ((array)$sources as $row) {
        if (empty($row['scraped'])) continue;
        $stats = is_array($row['stats'] ?? null) ? $row['stats'] : array();
        $rows[] = array(
            'value' => (float)($stats[$metric] ?? 0),
            'weight' => (float)($row['weight'] ?? 1),
        );
    }
    return $rows;
}

function publicista_ads_trimmed_weighted_average($sources, $metric) {
    $rows = publicista_ads_source_metric_values($sources, $metric);
    if (empty($rows)) return 0;
    usort($rows, function($a, $b) {
        if ($a['value'] == $b['value']) return 0;
        return ($a['value'] < $b['value']) ? -1 : 1;
    });
    if (count($rows) >= 4) {
        array_shift($rows);
        array_pop($rows);
    }
    $weighted = 0.0;
    $weights = 0.0;
    foreach ($rows as $row) {
        $weighted += ((float)$row['value']) * ((float)$row['weight']);
        $weights += (float)$row['weight'];
    }
    if ($weights <= 0) return (int)round($weighted);
    return (int)round($weighted / $weights);
}

function publicista_ads_metric_range($sources, $metric) {
    $rows = publicista_ads_source_metric_values($sources, $metric);
    if (empty($rows)) return array('min' => 0, 'max' => 0, 'range' => 0);
    $values = array_map(function($row) { return (float)$row['value']; }, $rows);
    $min = min($values);
    $max = max($values);
    return array('min' => (int)round($min), 'max' => (int)round($max), 'range' => (int)round($max - $min));
}

function publicista_ads_classify_level($premium, $top, $auto, $total) {
    $paid = (int) $top + (int) $auto;
    if ($top <= 4 && $paid <= 8) return 'muy_baja';
    if ($top <= 12 && $paid <= 22) return 'baja';
    if ($top <= 28 && $paid <= 55) return 'media';
    if ($top <= 65 && $paid <= 110) return 'alta';
    return 'muy_alta';
}

function publicista_ads_market_signals($comp) {
    $paidProfiles = max(0, (int)($comp['paid_profiles'] ?? 0));
    $comboTopAuto = max(0, (int)($comp['combo_top_auto'] ?? 0));
    $comboShare = $paidProfiles > 0 ? ($comboTopAuto / $paidProfiles) : 0;
    $level = trim((string)($comp['level'] ?? 'media'));
    $top = max(0, (int)($comp['top'] ?? 0));

    if ($comboShare >= 0.38 || in_array($level, array('alta', 'muy_alta'), true)) {
        $comboPolicy = 'normalized';
        $ostentationRisk = 'low';
    } elseif ($comboShare >= 0.18 || $top >= 12) {
        $comboPolicy = 'selective';
        $ostentationRisk = 'medium';
    } else {
        $comboPolicy = 'avoid';
        $ostentationRisk = ($level === 'muy_baja' || $level === 'baja') ? 'high' : 'medium';
    }

    if ($comboPolicy === 'avoid') {
        $comboNote = 'En esta plaza casi no se ve el combo TOP + autorenueva en el mismo anuncio. Conviene usarlo con mucha prudencia para no parecer ostentoso.';
    } elseif ($comboPolicy === 'selective') {
        $comboNote = 'El combo TOP + autorenueva aparece, pero no domina el mercado. Tiene sentido en uno de los ejes principales, no en todos.';
    } else {
        $comboNote = 'El combo TOP + autorenueva está bastante normalizado en esta muestra. Se puede usar en el eje principal sin romper la lectura del mercado.';
    }

    return array(
        'combo_top_auto_share' => round($comboShare, 4),
        'combo_policy' => $comboPolicy,
        'ostentation_risk' => $ostentationRisk,
        'combo_note' => $comboNote,
    );
}

function publicista_ads_scrape($city, $cat, $province = '') {
    $variants = publicista_ads_build_source_variants($city, $province, $cat);
    $sources = array();
    foreach ($variants as $variant) {
        $html = publicista_ads_fetch_page($variant['url']);
        $cards = $html !== null ? publicista_ads_extract_listing_cards($html) : array();
        $stats = $html !== null ? publicista_ads_parse_stats($html) : array();
        $sources[] = array_merge($variant, array(
            'scraped' => ($html !== null),
            'stats' => $stats,
            'cards' => $cards,
            'notice' => ($html !== null) ? null : 'No se pudo obtener HTML válido para esta variante.',
        ));
    }

    $success = array_values(array_filter($sources, function($row) {
        return !empty($row['scraped']);
    }));

    if (empty($success)) {
        $fallback = array(
            'premium' => 0,
            'top' => 2,
            'auto' => 1,
            'total' => 12,
            'paid_profiles' => 3,
            'free_profiles' => 9,
            'combo_top_auto' => 0,
            'combo_premium_top_auto' => 0,
            'combo_premium_top' => 0,
            'combo_premium_auto' => 0,
            'multi_badge_profiles' => 0,
            'cards_detected' => 0,
        );
        $fallback['level'] = publicista_ads_classify_level($fallback['premium'], $fallback['top'], $fallback['auto'], $fallback['total']);
        $fallback['market_signals'] = publicista_ads_market_signals($fallback);
        $fallback['url'] = publicista_ads_build_listing_url($city, $cat, $province);
        $fallback['primary_url'] = $fallback['url'];
        $fallback['scraped'] = false;
        $fallback['sources'] = $sources;
        $fallback['sources_total'] = count($variants);
        $fallback['sources_scraped'] = 0;
        $fallback['sources_used'] = 0;
        $fallback['aggregation'] = array('method' => 'fallback');
        $fallback['notice'] = 'No se pudo conectar con destacamos.net. Se usan estimaciones de seguridad para no bloquear la herramienta.';
        return $fallback;
    }

    $primary = end($success);
    $mergedCards = publicista_ads_merge_unique_cards_from_sources($success);
    if (!empty($mergedCards)) {
        $merged = publicista_ads_stats_from_cards($mergedCards);
        $aggregationMethod = 'unique_cards_union';
    } else {
        $merged = array(
            'premium' => publicista_ads_trimmed_weighted_average($success, 'premium'),
            'top' => publicista_ads_trimmed_weighted_average($success, 'top'),
            'auto' => publicista_ads_trimmed_weighted_average($success, 'auto'),
            'total' => publicista_ads_trimmed_weighted_average($success, 'total'),
            'paid_profiles' => publicista_ads_trimmed_weighted_average($success, 'paid_profiles'),
            'free_profiles' => publicista_ads_trimmed_weighted_average($success, 'free_profiles'),
            'combo_top_auto' => publicista_ads_trimmed_weighted_average($success, 'combo_top_auto'),
            'combo_premium_top_auto' => publicista_ads_trimmed_weighted_average($success, 'combo_premium_top_auto'),
            'combo_premium_top' => publicista_ads_trimmed_weighted_average($success, 'combo_premium_top'),
            'combo_premium_auto' => publicista_ads_trimmed_weighted_average($success, 'combo_premium_auto'),
            'multi_badge_profiles' => publicista_ads_trimmed_weighted_average($success, 'multi_badge_profiles'),
            'cards_detected' => publicista_ads_trimmed_weighted_average($success, 'cards_detected'),
        );
        $aggregationMethod = 'trimmed_weighted_average';
    }
    $merged['level'] = publicista_ads_classify_level($merged['premium'], $merged['top'], $merged['auto'], $merged['total']);
    $merged['market_signals'] = publicista_ads_market_signals($merged);
    $merged['url'] = trim((string)($primary['url'] ?? ''));
    $merged['primary_url'] = $merged['url'];
    $merged['scraped'] = true;
    $merged['sources'] = $sources;
    $merged['sources_total'] = count($variants);
    $merged['sources_scraped'] = count($success);
    $merged['sources_used'] = count($success);
    $merged['aggregation'] = array(
        'method' => $aggregationMethod,
        'unique_cards' => count($mergedCards),
        'ranges' => array(
            'premium' => publicista_ads_metric_range($success, 'premium'),
            'top' => publicista_ads_metric_range($success, 'top'),
            'auto' => publicista_ads_metric_range($success, 'auto'),
            'total' => publicista_ads_metric_range($success, 'total'),
            'combo_top_auto' => publicista_ads_metric_range($success, 'combo_top_auto'),
        ),
    );

    if (count($success) < count($variants)) {
        $merged['notice'] = 'Se han combinado ' . count($success) . ' de ' . count($variants) . ' variantes de búsqueda. La media es inteligente y robusta, pero conviene leer también el detalle por URL.';
    } else {
        $merged['notice'] = 'Se han combinado las ' . count($variants) . ' variantes de búsqueda previstas para obtener una media inteligente del mercado.';
    }

    return $merged;
}

function publicista_ads_to_mins($time) {
    $parts = explode(':', (string) $time);
    $h = isset($parts[0]) ? (int) $parts[0] : 0;
    $m = isset($parts[1]) ? (int) $parts[1] : 0;
    return ($h * 60) + $m;
}

function publicista_ads_wrap_minutes($mins) {
    $mins = (int)round((float)$mins);
    $mins = $mins % 1440;
    if ($mins < 0) $mins += 1440;
    return $mins;
}

function publicista_ads_to_hhmm($mins) {
    $mins = publicista_ads_wrap_minutes($mins);
    $hours = (int)floor($mins / 60);
    $minutes = (int)($mins % 60);
    return sprintf('%02d:%02d', $hours % 24, $minutes);
}

function publicista_ads_strategy_window_defaults() {
    return array(
        'start' => '10:30',
        'end' => '04:00',
    );
}

function publicista_ads_strategy_window_normalize($window) {
    $defaults = publicista_ads_strategy_window_defaults();
    $window = is_array($window) ? $window : array();
    $start = trim((string)($window['start'] ?? $defaults['start']));
    $end = trim((string)($window['end'] ?? $defaults['end']));
    if (!preg_match('/^\d{2}:\d{2}$/', $start)) $start = $defaults['start'];
    if (!preg_match('/^\d{2}:\d{2}$/', $end)) $end = $defaults['end'];
    return array('start' => $start, 'end' => $end);
}

function publicista_ads_minutes_forward($startMins, $endMins) {
    $startMins = publicista_ads_wrap_minutes($startMins);
    $endMins = publicista_ads_wrap_minutes($endMins);
    $diff = $endMins - $startMins;
    if ($diff <= 0) $diff += 1440;
    return $diff;
}

function publicista_ads_window_span($start, $end) {
    return publicista_ads_minutes_forward(publicista_ads_to_mins($start), publicista_ads_to_mins($end));
}

function publicista_ads_phase_times_minutes($start, $end, $count, $phaseMins = 0.0) {
    $count = max(1, (int)$count);
    $startMins = publicista_ads_to_mins($start);
    $span = publicista_ads_window_span($start, $end);
    $interval = $span / $count;
    $phaseMins = (float)$phaseMins;
    $out = array();
    for ($i = 0; $i < $count; $i++) {
        $raw = $startMins + $phaseMins + ($i * $interval);
        $out[] = publicista_ads_wrap_minutes($raw);
    }
    return $out;
}

function publicista_ads_calc_firings_with_phase($start, $end, $count, $phaseMins = 0.0) {
    $minutes = publicista_ads_phase_times_minutes($start, $end, $count, $phaseMins);
    $out = array();
    foreach ($minutes as $minute) {
        $out[] = publicista_ads_to_hhmm($minute);
    }
    return $out;
}

function publicista_ads_calc_firings($start, $end, $count) {
    return publicista_ads_calc_firings_with_phase($start, $end, $count, 0.0);
}

function publicista_ads_remap_time_to_window($time, $targetWindow, $sourceWindow = null) {
    $targetWindow = publicista_ads_strategy_window_normalize($targetWindow);
    $sourceWindow = publicista_ads_strategy_window_normalize($sourceWindow ?: publicista_ads_strategy_window_defaults());

    $sourceStart = publicista_ads_to_mins($sourceWindow['start']);
    $sourceSpan = publicista_ads_window_span($sourceWindow['start'], $sourceWindow['end']);
    $targetStart = publicista_ads_to_mins($targetWindow['start']);
    $targetSpan = publicista_ads_window_span($targetWindow['start'], $targetWindow['end']);

    $timeMins = publicista_ads_to_mins($time);
    $relative = $timeMins - $sourceStart;
    while ($relative < 0) $relative += 1440;
    while ($relative > $sourceSpan) $relative -= 1440;
    if ($relative < 0) $relative = 0;
    if ($relative > $sourceSpan) $relative = $sourceSpan;

    $ratio = $sourceSpan > 0 ? ($relative / $sourceSpan) : 0.0;
    $mapped = $targetStart + ($ratio * $targetSpan);
    return publicista_ads_to_hhmm($mapped);
}

function publicista_ads_candidate_gap_score($candidateMinutes, $occupiedMinutes) {
    $candidateMinutes = is_array($candidateMinutes) ? $candidateMinutes : array();
    $occupiedMinutes = is_array($occupiedMinutes) ? $occupiedMinutes : array();
    if (empty($candidateMinutes)) return -1;
    if (empty($occupiedMinutes)) return 9999;

    $minGap = 9999;
    foreach ($candidateMinutes as $candidate) {
        foreach ($occupiedMinutes as $occupied) {
            $diff = abs((int)$candidate - (int)$occupied);
            if ($diff > 720) $diff = 1440 - $diff;
            if ($diff < $minGap) $minGap = $diff;
        }
    }
    return $minGap;
}

function publicista_ads_retime_profiles_to_window($profiles, $window = array()) {
    $profiles = is_array($profiles) ? $profiles : array();
    $window = publicista_ads_strategy_window_normalize($window);
    $templateWindow = publicista_ads_strategy_window_defaults();

    foreach ($profiles as $idx => $profile) {
        foreach (array('auto7', 'auto4') as $type) {
            if (empty($profile['opts'][$type]) || !is_array($profile['opts'][$type])) continue;
            $range = $profile['opts'][$type];
            $range['start'] = publicista_ads_remap_time_to_window((string)($range['start'] ?? $window['start']), $window, $templateWindow);
            $range['end'] = publicista_ads_remap_time_to_window((string)($range['end'] ?? $window['end']), $window, $templateWindow);
            $range['n'] = max(1, (int)($range['n'] ?? 1));
            $profiles[$idx]['opts'][$type] = $range;
        }
    }

    $slots = array();
    foreach ($profiles as $idx => $profile) {
        foreach (array('auto7', 'auto4') as $type) {
            if (empty($profile['opts'][$type]) || !is_array($profile['opts'][$type])) continue;
            $range = $profile['opts'][$type];
            $slots[] = array(
                'idx' => $idx,
                'type' => $type,
                'profile_num' => (int)($profile['num'] ?? 0),
                'count' => max(1, (int)($range['n'] ?? 1)),
                'start' => (string)($range['start'] ?? $window['start']),
                'end' => (string)($range['end'] ?? $window['end']),
            );
        }
    }

    usort($slots, function($a, $b) {
        if ((int)$a['count'] === (int)$b['count']) {
            if ((string)$a['type'] === (string)$b['type']) {
                return ((int)$a['profile_num'] <=> (int)$b['profile_num']);
            }
            return strcmp((string)$a['type'], (string)$b['type']);
        }
        return ((int)$b['count'] <=> (int)$a['count']);
    });

    $occupiedMinutes = array();
    foreach ($slots as $slot) {
        $count = max(1, (int)$slot['count']);
        $span = max(1, publicista_ads_window_span($slot['start'], $slot['end']));
        $interval = $span / $count;
        $maxPhase = max(0, (int)floor($interval) - 1);
        $phaseStep = ($maxPhase > 90) ? 2 : 1;
        $bestPhase = 0;
        $bestMinutes = publicista_ads_phase_times_minutes($slot['start'], $slot['end'], $count, 0);
        $bestScore = publicista_ads_candidate_gap_score($bestMinutes, $occupiedMinutes);

        for ($phase = 0; $phase <= $maxPhase; $phase += $phaseStep) {
            $candidateMinutes = publicista_ads_phase_times_minutes($slot['start'], $slot['end'], $count, $phase);
            $score = publicista_ads_candidate_gap_score($candidateMinutes, $occupiedMinutes);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPhase = $phase;
                $bestMinutes = $candidateMinutes;
            }
        }

        $profiles[$slot['idx']]['opts'][$slot['type']]['phase_mins'] = $bestPhase;
        $profiles[$slot['idx']]['firings'][$slot['type']] = array_map('publicista_ads_to_hhmm', $bestMinutes);
        foreach ($bestMinutes as $minute) {
            $occupiedMinutes[] = (int)$minute;
        }
    }

    return $profiles;
}

function publicista_ads_detect_overlap($timesA, $timesB, $threshold = 5) {
    $conflicts = array();
    foreach ((array) $timesA as $a) {
        foreach ((array) $timesB as $b) {
            $diff = abs(publicista_ads_to_mins($a) - publicista_ads_to_mins($b));
            if ($diff > 720) $diff = 1440 - $diff;
            if ($diff < (int) $threshold) {
                $conflicts[] = $a . ' ↔ ' . $b . ' (' . $diff . ' min)';
            }
        }
    }
    return $conflicts;
}

function publicista_ads_profile_cost_breakdown($opts) {
    $opts = is_array($opts) ? $opts : array();
    $prices = publicista_ads_prices();
    $hasPremium = !empty($opts['PREMIUM']) || !empty($opts['premium']);
    $hasTop = !empty($opts['TOP']) || !empty($opts['top']);
    $hasAuto7 = !empty($opts['auto7']);
    $hasAuto4 = !empty($opts['auto4']);

    return array(
        'premium' => $hasPremium ? (float)$prices['premium'] : 0.0,
        'top' => $hasTop ? (float)$prices['top'] : 0.0,
        'auto7' => $hasAuto7 ? (float)$prices['auto7'] : 0.0,
        'auto4' => $hasAuto4 ? (float)$prices['auto4'] : 0.0,
    );
}

function publicista_ads_profile_cost_from_opts($opts) {
    $parts = publicista_ads_profile_cost_breakdown($opts);
    return (float)$parts['premium'] + (float)$parts['top'] + (float)$parts['auto7'] + (float)$parts['auto4'];
}

function publicista_ads_make_profile($num, $name, $opts, $why) {
    $profile = array(
        'num' => (int) $num,
        'name' => (string) $name,
        'opts' => (array) $opts,
        'cost_breakdown' => publicista_ads_profile_cost_breakdown($opts),
        'cost' => (float) publicista_ads_profile_cost_from_opts($opts),
        'why' => (string) $why,
        'firings' => array(),
    );
    foreach (array('auto7', 'auto4') as $type) {
        if (empty($profile['opts'][$type]) || !is_array($profile['opts'][$type])) continue;
        $range = $profile['opts'][$type];
        $profile['firings'][$type] = publicista_ads_calc_firings($range['start'], $range['end'], $range['n']);
    }
    return $profile;
}

function publicista_ads_strategy_blueprint($comp, $mode = 'recommended', $window = array()) {
    $level = (string)($comp['level'] ?? 'media');
    $signals = is_array($comp['market_signals'] ?? null) ? $comp['market_signals'] : publicista_ads_market_signals($comp);
    $comboPolicy = (string)($signals['combo_policy'] ?? 'selective');
    $comboNote = trim((string)($signals['combo_note'] ?? ''));
    $comboCount = (int)($comp['combo_top_auto'] ?? 0);
    $paidProfiles = max(0, (int)($comp['paid_profiles'] ?? 0));
    $comboPct = $paidProfiles > 0 ? round(($comboCount / $paidProfiles) * 100) : 0;
    $window = publicista_ads_strategy_window_normalize($window);

    $profiles = array();
    $reasons = array();
    $title = 'Equilibrada';
    $posture = 'equilibrada';

    if ($mode === 'accepted') {
        $title = 'Ahorro + bot gratis';
        $posture = 'conservadora';
    } elseif ($mode === 'optimal') {
        $title = 'Empuje fuerte';
        $posture = 'dominante';
    }

    if ($comboNote !== '') {
        $reasons[] = $comboNote;
    }
    $reasons[] = 'En la muestra combinada aparecen ' . $comboCount . ' anuncios con TOP + autorenueva visible a la vez (' . $comboPct . '% de los anuncios pagados estimados).';
    $reasons[] = 'Ventana de autosubidas aplicada: de ' . $window['start'] . ' a ' . $window['end'] . '. El sistema reubica los disparos dentro de esa franja e intenta separarlos para evitar choques entre perfiles.';

    if ($level === 'muy_baja') {
        if ($mode === 'accepted') {
            $reasons[] = 'Con competencia muy baja no hace falta sobreactuar. Esta versión apoya más en subidas gratis y contiene gasto.';
            $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ principal', array('TOP' => true), 'Presencia arriba sin cargar demasiado el anuncio.');
            $profiles[] = publicista_ads_make_profile(2, 'Autorenueva 4€ noche', array('auto4' => array('start' => '19:30', 'end' => '23:30', 'n' => 4)), 'Refuerzo corto solo en el tramo con más tráfico.');
            $profiles[] = publicista_ads_make_profile(3, 'Gratuito x3', array('free' => array('14:00', '21:00', '00:30')), 'Apoyo manual / bot gratis sin coste.');
        } elseif ($mode === 'optimal') {
            $reasons[] = 'La opción fuerte sigue evitando parecer ostentosa, pero ya protege mañana-tarde y pico de noche.';
            if ($comboPolicy === 'avoid') {
                $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ principal', array('TOP' => true), 'Se mantiene un solo TOP visible para no sobredestacar.');
                $profiles[] = publicista_ads_make_profile(2, 'Autorenueva 7€ diurna', array('auto7' => array('start' => '11:00', 'end' => '23:00', 'n' => 10)), 'Malla continua sin añadir segundo icono al anuncio principal.');
            } else {
                $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ + Autorenueva 7€', array('TOP' => true, 'auto7' => array('start' => '11:00', 'end' => '23:00', 'n' => 10)), 'Combo principal porque el mercado sí tolera algo más de presencia.');
            }
            $profiles[] = publicista_ads_make_profile(3, 'Autorenueva 4€ noche', array('auto4' => array('start' => '19:30', 'end' => '23:30', 'n' => 4)), 'Refuerzo corto en el prime time.');
            $profiles[] = publicista_ads_make_profile(4, 'Gratuito x2', array('free' => array('14:00', '22:00')), 'Apoyo manual.');
        } else {
            $reasons[] = 'La opción equilibrada reparte visibilidad pagada y apoyo gratis sin disparar el presupuesto.';
            if ($comboPolicy === 'avoid') {
                $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ principal', array('TOP' => true), 'Anuncio muy visible sin verse recargado.');
                $profiles[] = publicista_ads_make_profile(2, 'Autorenueva 7€ diurna', array('auto7' => array('start' => '11:00', 'end' => '23:00', 'n' => 10)), 'Continuidad de subida sin sumar dos iconos al mismo anuncio.');
            } else {
                $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ + Autorenueva 7€', array('TOP' => true, 'auto7' => array('start' => '11:00', 'end' => '23:00', 'n' => 10)), 'Eje principal si el mercado tolera mejor el combo.');
            }
            $profiles[] = publicista_ads_make_profile(3, 'Autorenueva 4€ noche', array('auto4' => array('start' => '19:30', 'end' => '23:30', 'n' => 4)), 'Refuerzo concentrado.');
            $profiles[] = publicista_ads_make_profile(4, 'Gratuito x2', array('free' => array('14:00', '22:00')), 'Apoyo manual.');
        }
    } elseif ($level === 'baja') {
        if ($mode === 'accepted') {
            $reasons[] = 'La versión ahorro se apoya más en el bot gratis y recorta el refuerzo de 4€ para empezar con menos inversión.';
            $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ principal', array('TOP' => true), 'Pieza tractora arriba del listado.');
            $profiles[] = publicista_ads_make_profile(2, 'Autorenueva 7€ tarde-noche', array('auto7' => array('start' => '12:00', 'end' => '23:30', 'n' => 10)), 'Cobertura larga separada del TOP.');
            $profiles[] = publicista_ads_make_profile(3, 'Gratuito x3', array('free' => array('14:30', '20:30', '00:30')), 'Bot publicista / subida gratis para mantener presencia sin sumar pago.');
        } elseif ($mode === 'optimal') {
            $reasons[] = 'La opción fuerte aumenta continuidad y acepta un combo principal si el mercado no lo penaliza.';
            if ($comboPolicy === 'avoid') {
                $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ principal', array('TOP' => true), 'Se evita sobrecargar el anuncio líder.');
                $profiles[] = publicista_ads_make_profile(2, 'Autorenueva 7€ mañana-tarde', array('auto7' => array('start' => '10:30', 'end' => '22:30', 'n' => 10)), 'Malla principal diurna.');
                $profiles[] = publicista_ads_make_profile(3, 'Autorenueva 7€ tarde-madrugada', array('auto7' => array('start' => '12:00', 'end' => '03:00', 'n' => 10)), 'Segundo eje desplazado hasta madrugada.');
            } else {
                $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ + Autorenueva 7€', array('TOP' => true, 'auto7' => array('start' => '10:30', 'end' => '22:30', 'n' => 10)), 'Combo principal del plan.');
                $profiles[] = publicista_ads_make_profile(2, 'Autorenueva 7€ tarde-madrugada', array('auto7' => array('start' => '12:00', 'end' => '03:00', 'n' => 10)), 'Segundo eje hasta madrugada.');
            }
            $profiles[] = publicista_ads_make_profile(4, 'Autorenueva 4€ pico', array('auto4' => array('start' => '19:45', 'end' => '23:45', 'n' => 4)), 'Refuerzo sobre el pico.');
        } else {
            $reasons[] = 'La opción equilibrada busca continuidad real pero manteniendo una lectura elegante del escaparate.';
            if ($comboPolicy === 'avoid') {
                $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ principal', array('TOP' => true), 'Visibilidad sin exceso de iconos.');
                $profiles[] = publicista_ads_make_profile(2, 'Autorenueva 7€ mañana-tarde', array('auto7' => array('start' => '10:30', 'end' => '22:30', 'n' => 10)), 'Cobertura diurna.');
            } else {
                $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ + Autorenueva 7€', array('TOP' => true, 'auto7' => array('start' => '10:30', 'end' => '22:30', 'n' => 10)), 'Combo principal porque el mercado ya lo empieza a normalizar.');
            }
            $profiles[] = publicista_ads_make_profile(3, 'Autorenueva 7€ tarde-madrugada', array('auto7' => array('start' => '12:00', 'end' => '03:00', 'n' => 10)), 'Segundo eje hasta madrugada.');
            $profiles[] = publicista_ads_make_profile(4, 'Autorenueva 4€ pico', array('auto4' => array('start' => '19:45', 'end' => '23:45', 'n' => 4)), 'Refuerzo corto.');
        }
    } elseif ($level === 'media') {
        if ($mode === 'accepted') {
            $reasons[] = 'La versión ahorro ya exige continuidad, pero recorta el refuerzo de 4€ porque el bot gratis puede cubrir parte del movimiento diario.';
            if ($comboPolicy === 'avoid') {
                $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ principal', array('TOP' => true), 'TOP tractor.');
                $profiles[] = publicista_ads_make_profile(2, 'Autorenueva 7€ eje principal', array('auto7' => array('start' => '11:00', 'end' => '23:00', 'n' => 10)), 'Malla principal separada.');
            } else {
                $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ + Autorenueva 7€', array('TOP' => true, 'auto7' => array('start' => '11:00', 'end' => '23:00', 'n' => 10)), 'Combo base del plan.');
            }
            $profiles[] = publicista_ads_make_profile(3, 'Autorenueva 7€ offset', array('auto7' => array('start' => '11:45', 'end' => '23:45', 'n' => 10)), 'Segundo ritmo de subida.');
            $profiles[] = publicista_ads_make_profile(4, 'Gratuito x3', array('free' => array('14:00', '20:30', '01:00')), 'Apoyo del bot gratis durante el día.');
        } elseif ($mode === 'optimal') {
            $reasons[] = 'La opción fuerte ya asume una presencia dominante, pero ajustada a cómo se comporta el mercado local.';
            $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ + Autorenueva 7€ (eje principal)', array('TOP' => true, 'auto7' => array('start' => '11:00', 'end' => '23:00', 'n' => 10)), 'Primer eje del plan.');
            if ($comboPolicy === 'normalized') {
                $profiles[] = publicista_ads_make_profile(2, 'TOP 9€ + Autorenueva 7€ (offset)', array('TOP' => true, 'auto7' => array('start' => '11:30', 'end' => '02:45', 'n' => 10)), 'Segundo eje con combo porque el mercado ya lo absorbe.');
            } else {
                $profiles[] = publicista_ads_make_profile(2, 'Autorenueva 7€ (offset largo)', array('auto7' => array('start' => '11:30', 'end' => '02:45', 'n' => 10)), 'Segundo eje largo sin añadir otro combo visible.');
            }
            $profiles[] = publicista_ads_make_profile(3, 'Autorenueva 7€ (tercer ritmo)', array('auto7' => array('start' => '12:10', 'end' => '00:45', 'n' => 10)), 'Tercer ritmo para continuidad.');
            $profiles[] = publicista_ads_make_profile(4, 'Autorenueva 4€ prime time', array('auto4' => array('start' => '19:15', 'end' => '23:15', 'n' => 4)), 'Refuerzo corto sobre la hora fuerte.');
        } else {
            $reasons[] = 'La opción equilibrada mantiene presencia continua y usa el combo solo donde realmente compensa.';
            $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ + Autorenueva 7€ (eje principal)', array('TOP' => true, 'auto7' => array('start' => '11:00', 'end' => '23:00', 'n' => 10)), 'Primer eje de visibilidad.');
            if ($comboPolicy === 'normalized') {
                $profiles[] = publicista_ads_make_profile(2, 'TOP 9€ + Autorenueva 7€ (offset)', array('TOP' => true, 'auto7' => array('start' => '11:30', 'end' => '02:45', 'n' => 10)), 'Segundo eje con combo porque ya es parte del lenguaje visual del mercado.');
            } else {
                $profiles[] = publicista_ads_make_profile(2, 'Autorenueva 7€ (offset)', array('auto7' => array('start' => '11:30', 'end' => '02:45', 'n' => 10)), 'Segundo eje sin repetir combo.');
            }
            $profiles[] = publicista_ads_make_profile(3, 'Autorenueva 4€ prime time', array('auto4' => array('start' => '19:15', 'end' => '23:15', 'n' => 4)), 'Refuerzo corto.');
            $profiles[] = publicista_ads_make_profile(4, 'Gratuito x2', array('free' => array('14:00', '22:00')), 'Apoyo manual sin coste.');
        }
    } else {
        if ($mode === 'accepted') {
            $reasons[] = 'Con competencia alta o muy alta, la versión ahorro mantiene dos ejes pagados y sustituye el refuerzo corto por apoyo gratis. Es la opción para probar sin ir a máximos.';
            $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ + Autorenueva 7€', array('TOP' => true, 'auto7' => array('start' => '11:00', 'end' => '23:00', 'n' => 10)), 'Eje principal imprescindible.');
            $profiles[] = publicista_ads_make_profile(2, 'Autorenueva 7€ tarde-madrugada', array('auto7' => array('start' => '12:00', 'end' => '03:00', 'n' => 10)), 'Cobertura larga nocturna.');
            $profiles[] = publicista_ads_make_profile(3, 'Gratuito x3', array('free' => array('14:00', '20:30', '01:30')), 'Bot gratis para mantener movimiento entre pagos.');
        } elseif ($mode === 'optimal') {
            $reasons[] = 'La opción fuerte entra a dominar visibilidad sin recurrir al formato premium dentro del estudio de estrategia.';
            $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ + Autorenueva 7€', array('TOP' => true, 'auto7' => array('start' => '11:00', 'end' => '23:00', 'n' => 10)), 'Cobertura máxima del primer eje sin meter premium.');
            $profiles[] = publicista_ads_make_profile(2, 'TOP 9€ + Autorenueva 7€ (tarde-madrugada)', array('TOP' => true, 'auto7' => array('start' => '12:00', 'end' => '03:00', 'n' => 10)), 'Segundo eje extendido.');
            $profiles[] = publicista_ads_make_profile(3, 'Autorenueva 7€ (tercer offset)', array('auto7' => array('start' => '11:45', 'end' => '23:45', 'n' => 10)), 'Tercer ritmo continuo.');
            $profiles[] = publicista_ads_make_profile(4, 'Autorenueva 4€ prime time', array('auto4' => array('start' => '19:30', 'end' => '23:30', 'n' => 4)), 'Refuerzo hora punta.');
            $profiles[] = publicista_ads_make_profile(5, 'Autorenueva 4€ madrugada', array('auto4' => array('start' => '01:00', 'end' => '04:00', 'n' => 4)), 'Refuerzo cuando cae algo la rotación.');
        } else {
            $reasons[] = 'La opción equilibrada ya acepta el combo como parte del plan, pero sin usar premium en este estudio.';
            $profiles[] = publicista_ads_make_profile(1, 'TOP 9€ + Autorenueva 7€', array('TOP' => true, 'auto7' => array('start' => '11:00', 'end' => '23:00', 'n' => 10)), 'Primer eje fuerte sin premium.');
            $profiles[] = publicista_ads_make_profile(2, 'TOP 9€ + Autorenueva 7€ (tarde-madrugada)', array('TOP' => true, 'auto7' => array('start' => '12:00', 'end' => '03:00', 'n' => 10)), 'Segundo eje de continuidad.');
            $profiles[] = publicista_ads_make_profile(3, 'Autorenueva 7€ (tercer offset)', array('auto7' => array('start' => '11:45', 'end' => '23:45', 'n' => 10)), 'Tercer ritmo.');
            $profiles[] = publicista_ads_make_profile(4, 'Autorenueva 4€ prime time', array('auto4' => array('start' => '19:30', 'end' => '23:30', 'n' => 4)), 'Refuerzo corto.');
        }
    }

    $profiles = publicista_ads_retime_profiles_to_window($profiles, $window);

    return array(
        'mode' => $mode,
        'label' => $title,
        'posture' => $posture,
        'combo_policy' => $comboPolicy,
        'combo_note' => $comboNote,
        'profiles' => $profiles,
        'reasons' => $reasons,
        'window' => $window,
    );
}

function publicista_ads_strategy_for_girl($comp, $girl, $mode = 'recommended', $window = array(), $blueprint = null, $profilesOverride = null, $extraReasons = array()) {
    if (!is_array($blueprint)) {
        $blueprint = publicista_ads_strategy_blueprint($comp, $mode, $window);
    }
    $profiles = is_array($profilesOverride) ? array_values($profilesOverride) : (is_array($blueprint['profiles'] ?? null) ? array_values($blueprint['profiles']) : array());
    $allFirings = array();
    $cost = 0.0;

    foreach ($profiles as $profile) {
        $cost += (float)($profile['cost'] ?? 0);
        foreach (array('auto7', 'auto4') as $type) {
            if (empty($profile['firings'][$type]) || !is_array($profile['firings'][$type])) continue;
            $range = $profile['opts'][$type];
            $allFirings[] = array(
                'profile' => $profile['num'],
                'pname' => $profile['name'],
                'type' => $type,
                'start' => $range['start'],
                'end' => $range['end'],
                'n' => $range['n'],
                'times' => $profile['firings'][$type],
            );
        }
    }

    $overlapWarnings = array();
    $countFirings = count($allFirings);
    for ($i = 0; $i < $countFirings; $i++) {
        for ($j = $i + 1; $j < $countFirings; $j++) {
            $conflicts = publicista_ads_detect_overlap($allFirings[$i]['times'], $allFirings[$j]['times'], 5);
            foreach ($conflicts as $conflict) {
                $overlapWarnings[] = 'P' . $allFirings[$i]['profile'] . ' vs P' . $allFirings[$j]['profile'] . ': subidas a ' . $conflict;
            }
        }
    }

    $reasons = is_array($blueprint['reasons'] ?? null) ? array_values($blueprint['reasons']) : array();
    foreach ((array)$extraReasons as $reason) {
        $reason = trim((string)$reason);
        if ($reason === '' || in_array($reason, $reasons, true)) continue;
        $reasons[] = $reason;
    }

    return array(
        'girl' => (int)$girl,
        'mode' => $mode,
        'mode_label' => (string)($blueprint['label'] ?? 'Recomendable'),
        'market_posture' => (string)($blueprint['posture'] ?? 'equilibrada'),
        'combo_policy' => (string)($blueprint['combo_policy'] ?? 'selective'),
        'combo_note' => (string)($blueprint['combo_note'] ?? ''),
        'level' => (string)($comp['level'] ?? 'media'),
        'profiles' => $profiles,
        'reasons' => $reasons,
        'cost' => $cost,
        'allFirings' => $allFirings,
        'overlapWarnings' => $overlapWarnings,
        'window' => is_array($blueprint['window'] ?? null) ? $blueprint['window'] : publicista_ads_strategy_window_normalize($window),
    );
}

function publicista_ads_multigirl_extra_weight($level, $mode = 'recommended') {
    $map = array(
        'muy_baja' => 0.35,
        'baja' => 0.45,
        'media' => 0.55,
        'alta' => 0.70,
        'muy_alta' => 0.80,
    );
    $weight = isset($map[$level]) ? (float)$map[$level] : 0.55;
    if ($mode === 'accepted') $weight -= 0.05;
    if ($mode === 'optimal') $weight += 0.05;
    if ($weight < 0.25) $weight = 0.25;
    if ($weight > 0.90) $weight = 0.90;
    return $weight;
}

function publicista_ads_multigirl_target_profile_count($baseCount, $numGirls, $level, $mode = 'recommended') {
    $baseCount = max(0, (int)$baseCount);
    $numGirls = max(1, (int)$numGirls);
    if ($baseCount <= 0) return 0;
    if ($numGirls <= 1) return $baseCount;

    $weight = publicista_ads_multigirl_extra_weight($level, $mode);
    $effectiveUnits = 1 + (($numGirls - 1) * $weight);
    $target = (int)round($baseCount * $effectiveUnits);
    $minTarget = max($baseCount, $numGirls);
    $maxTarget = $baseCount * $numGirls;
    if ($target < $minTarget) $target = $minTarget;
    if ($target > $maxTarget) $target = $maxTarget;
    return $target;
}

function publicista_ads_multigirl_profile_distribution($totalProfiles, $numGirls) {
    $totalProfiles = max(0, (int)$totalProfiles);
    $numGirls = max(1, (int)$numGirls);
    $counts = array_fill(0, $numGirls, 0);
    if ($totalProfiles <= 0) return $counts;

    $seed = min($numGirls, $totalProfiles);
    for ($i = 0; $i < $seed; $i++) {
        $counts[$i] = 1;
    }
    $remaining = $totalProfiles - $seed;
    $cursor = 0;
    while ($remaining > 0) {
        $counts[$cursor % $numGirls]++;
        $cursor++;
        $remaining--;
    }
    return $counts;
}

function publicista_ads_multigirl_take_profiles($profiles, $count) {
    $profiles = array_values((array)$profiles);
    $count = max(0, (int)$count);
    $out = array();
    $max = min($count, count($profiles));
    for ($i = 0; $i < $max; $i++) {
        $profile = is_array($profiles[$i]) ? $profiles[$i] : array();
        $profile['source_num'] = (int)($profile['num'] ?? ($i + 1));
        $profile['num'] = $i + 1;
        $out[] = $profile;
    }
    return $out;
}

function publicista_ads_multigirl_synergy_note($numGirls, $baseCount, $targetCount, $level, $mode = 'recommended') {
    $numGirls = max(1, (int)$numGirls);
    $baseCount = max(0, (int)$baseCount);
    $targetCount = max(0, (int)$targetCount);
    if ($numGirls <= 1 || $baseCount <= 0) return '';

    $linear = $baseCount * $numGirls;
    $saved = max(0, $linear - $targetCount);
    $weightPct = round(publicista_ads_multigirl_extra_weight($level, $mode) * 100);

    return 'Se aplica reducción por sinergia multichica: cuando entra un contacto por cualquier anuncio, después se reofrecen todas las chicas activas en la conversación. Por eso no se replica el pack de 1 chica x ' . $numGirls . '. En esta versión se recomiendan ' . $targetCount . ' perfiles globales frente a ' . $linear . ' en un cálculo lineal (' . $saved . ' menos, ajuste extra por chica del ' . $weightPct . '%).';
}

function publicista_ads_strategy_option_label($mode) {
    $map = array(
        'accepted' => 'Ahorro + bot gratis',
        'recommended' => 'Equilibrada',
        'optimal' => 'Empuje fuerte',
    );
    return isset($map[$mode]) ? $map[$mode] : 'Equilibrada';
}

function publicista_ads_build_strategy_option($comp, $numGirls, $mode = 'recommended', $window = array()) {
    $window = publicista_ads_strategy_window_normalize($window);
    $numGirls = max(1, (int)$numGirls);
    $strategies = array();
    $grandTotal = 0.0;
    $warnings = array();

    $level = (string)($comp['level'] ?? 'media');
    $blueprint = publicista_ads_strategy_blueprint($comp, $mode, $window);
    $baseProfiles = is_array($blueprint['profiles'] ?? null) ? array_values($blueprint['profiles']) : array();
    $baseCount = count($baseProfiles);
    $targetProfiles = publicista_ads_multigirl_target_profile_count($baseCount, $numGirls, $level, $mode);
    $profileDistribution = publicista_ads_multigirl_profile_distribution($targetProfiles, $numGirls);
    $synergyNote = publicista_ads_multigirl_synergy_note($numGirls, $baseCount, $targetProfiles, $level, $mode);

    for ($g = 1; $g <= $numGirls; $g++) {
        $assignedCount = (int)($profileDistribution[$g - 1] ?? 0);
        $assignedProfiles = publicista_ads_multigirl_take_profiles($baseProfiles, $assignedCount);
        $extraReasons = array();
        if ($synergyNote !== '') $extraReasons[] = $synergyNote;
        if ($numGirls > 1) {
            $linearProfiles = $baseCount;
            $extraReasons[] = 'Reparto para chica ' . $g . ': ' . count($assignedProfiles) . ' perfiles asignados dentro de una estrategia conjunta para ' . $numGirls . ' chicas. En un cálculo lineal esta chica heredaría ' . $linearProfiles . ' perfiles completos.';
        }
        $row = publicista_ads_strategy_for_girl($comp, $g, $mode, $window, $blueprint, $assignedProfiles, $extraReasons);
        $strategies[] = $row;
        $grandTotal += (float)($row['cost'] ?? 0);
        foreach ((array)($row['overlapWarnings'] ?? array()) as $warning) $warnings[] = $warning;
    }

    $signals = is_array($comp['market_signals'] ?? null) ? $comp['market_signals'] : publicista_ads_market_signals($comp);
    if ($mode === 'accepted') {
        $explanation = 'Versión más ahorradora. Cuenta con el bot de subidas gratis para recortar pago donde más se nota.';
        $decisionHelp = 'Empieza aquí si ya vas a tener el bot gratis activo todo el día y quieres validar la plaza antes de apretar más.';
    } elseif ($mode === 'optimal') {
        $explanation = 'Versión más intensa. Busca dominar visibilidad y acelerar resultados, pagando más por continuidad.';
        $decisionHelp = 'Tiene sentido cuando la plaza está competida, necesitas tracción rápida o ves que la versión barata se queda corta.';
    } else {
        $explanation = 'Versión intermedia. Mantiene buena presencia sin ir a máxima presión.';
        $decisionHelp = 'Es el punto medio cuando quieres algo serio sin entrar todavía en la estrategia más cara.';
    }
    if ($synergyNote !== '') {
        $explanation .= ' Además, en multichica no se multiplica el pack de una por el número de chicas, porque cualquier entrada puede reconducirse después al conjunto de activas.';
    }

    return array(
        'code' => $mode,
        'label' => publicista_ads_strategy_option_label($mode),
        'is_default' => ($mode === 'recommended'),
        'market_posture' => ($mode === 'accepted' ? 'conservadora' : ($mode === 'optimal' ? 'dominante' : 'equilibrada')),
        'combo_policy' => (string)($signals['combo_policy'] ?? 'selective'),
        'combo_note' => (string)($signals['combo_note'] ?? ''),
        'explanation' => $explanation,
        'decision_help' => $decisionHelp,
        'free_bot_note' => 'El bot publicista de subidas gratis se tiene en cuenta como apoyo para bajar gasto en la opción de ahorro y como soporte adicional en las demás.',
        'synergy_note' => $synergyNote,
        'strategy_profile_count' => $targetProfiles,
        'window' => $window,
        'strategies' => $strategies,
        'grand_total' => $grandTotal,
        'avg_per_product' => ($numGirls > 0) ? ($grandTotal / $numGirls) : 0,
        'warnings' => $warnings,
    );
}

function publicista_ads_build_strategy_pack($comp, $numGirls, $window = array()) {
    $window = publicista_ads_strategy_window_normalize($window);
    $accepted = publicista_ads_build_strategy_option($comp, $numGirls, 'accepted', $window);
    $recommended = publicista_ads_build_strategy_option($comp, $numGirls, 'recommended', $window);
    $optimal = publicista_ads_build_strategy_option($comp, $numGirls, 'optimal', $window);

    $accepted['savings_vs_optimal'] = max(0, (float)$optimal['grand_total'] - (float)$accepted['grand_total']);
    $accepted['comparison_note'] = 'Es la opción menos cara. Muy útil si el bot gratis está todo el día funcionando y quieres contener inversión.';
    $recommended['extra_vs_accepted'] = max(0, (float)$recommended['grand_total'] - (float)$accepted['grand_total']);
    $recommended['savings_vs_optimal'] = max(0, (float)$optimal['grand_total'] - (float)$recommended['grand_total']);
    $recommended['comparison_note'] = 'Es la opción media. Suele ser la más cómoda para empezar si no quieres quedarte corto ni pagar de más.';
    $optimal['extra_vs_accepted'] = max(0, (float)$optimal['grand_total'] - (float)$accepted['grand_total']);
    $optimal['comparison_note'] = 'Es la opción más cara. Reserva esta presión para plazas fuertes o cuando quieras acelerar resultados.';

    return array(
        'default_option_code' => 'recommended',
        'default' => $recommended,
        'window' => $window,
        'chooser_note' => 'Con el bot publicista subiendo gratis durante todo el día, normalmente conviene probar primero la opción de ahorro o la equilibrada y solo escalar a la fuerte si la plaza lo pide.',
        'options' => array(
            'accepted' => $accepted,
            'recommended' => $recommended,
            'optimal' => $optimal,
        ),
        'strategies' => $recommended['strategies'],
        'grand_total' => $recommended['grand_total'],
    );
}

function publicista_ads_build_strategy($comp, $numGirls, $window = array()) {
    $pack = publicista_ads_build_strategy_pack($comp, $numGirls, $window);
    return is_array($pack['strategies'] ?? null) ? $pack['strategies'] : array();
}

function publicista_ads_render_timeline_svg($allFirings) {
    if (empty($allFirings)) return '';

    $palette = array('#6366F1','#10B981','#F59E0B','#EF4444','#8B5CF6','#EC4899','#06B6D4');
    $colorMap = array();
    foreach ($allFirings as $f) {
        $idx = ((int) $f['profile']) - 1;
        $colorMap[$f['profile']] = $palette[$idx % count($palette)];
    }

    $rowH = 42;
    $labelW = 145;
    $chartW = 570;
    $padTop = 24;
    $padBot = 30;
    $rows = count($allFirings);
    $totalH = $padTop + ($rows * $rowH) + $padBot;
    $totalW = $labelW + $chartW + 10;

    $out = "<svg width='100%' viewBox='0 0 {$totalW} {$totalH}' xmlns='http://www.w3.org/2000/svg' style='font-family:system-ui,sans-serif;display:block'>";

    for ($h = 0; $h <= 24; $h += 3) {
        $x = $labelW + (($h / 24) * $chartW);
        $lbl = sprintf('%02d', $h % 24) . 'h';
        $gridY1 = $padTop - 6;
        $gridY2 = $padTop + ($rows * $rowH) + 6;
        $out .= "<line x1='{$x}' y1='{$gridY1}' x2='{$x}' y2='{$gridY2}' stroke='#243247' stroke-width='1'/>";
        $out .= "<text x='{$x}' y='" . ($padTop + ($rows * $rowH) + 20) . "' text-anchor='middle' font-size='9' fill='#7f93ac'>{$lbl}</text>";
    }

    foreach ($allFirings as $ri => $row) {
        $y = $padTop + ($ri * $rowH);
        $yMid = $y + ($rowH / 2);
        $color = $colorMap[$row['profile']];
        $hex = ltrim($color, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $typeLabel = ($row['type'] === 'auto7') ? '10 sub/día' : '4 sub/día';
        $out .= "<text x='" . ($labelW - 8) . "' y='" . ($yMid - 4) . "' text-anchor='end' font-size='10' font-weight='600' fill='#d9e2ef'>P{$row['profile']}</text>";
        $out .= "<text x='" . ($labelW - 8) . "' y='" . ($yMid + 8) . "' text-anchor='end' font-size='9' fill='#9fb4cc'>{$typeLabel}</text>";
        $out .= "<rect x='{$labelW}' y='" . ($y + 5) . "' width='{$chartW}' height='" . ($rowH - 10) . "' fill='#101b2d' rx='4'/>";

        $sMin = publicista_ads_to_mins($row['start']);
        $eMin = publicista_ads_to_mins($row['end']);
        $overnight = ($eMin < $sMin);
        if ($overnight) $eMin += 1440;

        $drawRange = function($s, $e) use ($labelW, $chartW, $y, $rowH, $color, $r, $g, $b, &$out) {
            $x = $labelW + (($s / 1440) * $chartW);
            $w = max(2, ((($e - $s) / 1440) * $chartW));
            $out .= "<rect x='{$x}' y='" . ($y + 5) . "' width='{$w}' height='" . ($rowH - 10) . "' fill='rgba({$r},{$g},{$b},0.12)' rx='4'/>";
            $out .= "<rect x='{$x}' y='" . ($y + 5) . "' width='{$w}' height='" . ($rowH - 10) . "' fill='none' stroke='{$color}' stroke-width='1.5' rx='4' opacity='0.55'/>";
        };

        if (!$overnight) {
            $drawRange($sMin, $eMin);
        } else {
            $drawRange($sMin, 1440);
            $drawRange(0, publicista_ads_to_mins($row['end']));
        }

        foreach ($row['times'] as $ti => $time) {
            $tMin = publicista_ads_to_mins($time);
            $dotX = $labelW + (($tMin / 1440) * $chartW);
            $out .= "<circle cx='{$dotX}' cy='{$yMid}' r='4.5' fill='{$color}' opacity='0.9'/>";
            $out .= "<circle cx='{$dotX}' cy='{$yMid}' r='4.5' fill='none' stroke='white' stroke-width='1'/>";
            $labelY = (($ti % 2) === 0) ? ($y + 3) : ($y + $rowH + 1);
            $out .= "<text x='{$dotX}' y='{$labelY}' text-anchor='middle' font-size='7.5' fill='{$color}' font-weight='500'>{$time}</text>";
        }
    }

    $out .= '</svg>';
    return $out;
}

function publicista_ads_level_label($level) {
    $map = array('muy_baja' => 'Muy baja', 'baja' => 'Baja', 'media' => 'Media', 'alta' => 'Alta', 'muy_alta' => 'Muy alta');
    return isset($map[$level]) ? $map[$level] : $level;
}

function publicista_ads_level_fg($level) {
    $map = array('muy_baja' => '#065F46', 'baja' => '#1E40AF', 'media' => '#92400E', 'alta' => '#991B1B', 'muy_alta' => '#4C1D95');
    return isset($map[$level]) ? $map[$level] : '#d9e2ef';
}

function publicista_ads_level_bg($level) {
    $map = array('muy_baja' => '#D1FAE5', 'baja' => '#DBEAFE', 'media' => '#FEF3C7', 'alta' => '#FEE2E2', 'muy_alta' => '#EDE9FE');
    return isset($map[$level]) ? $map[$level] : '#101b2d';
}

function publicista_ads_level_icon($level) {
    $map = array('muy_baja' => '●', 'baja' => '◑', 'media' => '◕', 'alta' => '◉', 'muy_alta' => '⬤');
    return isset($map[$level]) ? $map[$level] : '•';
}

function publicista_ads_badge_line_html($opts) {
    $map = array(
        'PREMIUM' => array('publicista-ads-badge premium', 'PREMIUM'),
        'TOP' => array('publicista-ads-badge top', 'TOP'),
        'auto7' => array('publicista-ads-badge auto7', 'Auto 7€ · 10/día'),
        'auto4' => array('publicista-ads-badge auto4', 'Auto 4€ · 4/día'),
        'free' => array('publicista-ads-badge free', 'Gratuito'),
    );

    $out = '';
    foreach ($map as $key => $row) {
        if (isset($opts[$key])) {
            $out .= '<span class="' . $row[0] . '">' . htmlspecialchars($row[1], ENT_QUOTES, 'UTF-8') . '</span> ';
        }
    }
    return trim($out);
}

function publicista_ads_euros($value) {
    return number_format((float) $value, 2, ',', '.') . ' €';
}

// ============================================================
// POLLO.AI IMAGE GENERATION
// Generacion de imagenes via API web de pollo.ai (tRPC)
// La cookie de sesion se guarda en Josue > ConfigM
// ============================================================

function publicista_pollo_models() {
    return array(
        'pollo-image-v2'   => array('name' => 'Pollo Image v2',            'modelName' => 'pollo-image-v2',   'aspectRatio' => '1:1', 'resolution' => '1K', 'supports_mode' => true),
        'pollo-image-v1-6' => array('name' => 'Pollo Image v1.6',          'modelName' => 'pollo-image-v1-6', 'aspectRatio' => '1:1', 'supports_mode' => false),
        'flux-dev'         => array('name' => 'FLUX Dev (Pollo.ai)',       'modelName' => 'flux-dev',         'aspectRatio' => '2:3', 'supports_mode' => false),
        'seedream'         => array('name' => 'Seedream (Pollo.ai)',       'modelName' => 'seedream',         'aspectRatio' => '2:3', 'supports_mode' => false),
        'nano-banana'      => array('name' => 'Nano Banana (Pollo.ai)',    'modelName' => 'nano-banana',      'aspectRatio' => '4:3', 'supports_mode' => false),
    );
}

function publicista_is_pollo_model($model) {
    return array_key_exists(trim((string)$model), publicista_pollo_models());
}

function publicista_job_uses_pollo_model($job) {
    $job = is_array($job) ? $job : array();
    $model = trim((string)publicista_array_get(publicista_array_get($job, 'models', array()), 'image', ''));
    return publicista_is_pollo_model($model);
}

function publicista_pollo_session_cookie() {
    $settings = settings_get();
    return trim((string)($settings['pollo_session_cookie'] ?? ''));
}

function publicista_pollo_cookie_expires() {
    $settings = settings_get();
    $stored = trim((string)($settings['pollo_cookie_expires'] ?? ''));
    if ($stored !== '' && strtotime($stored) !== false) {
        return $stored;
    }
    return '2026-07-14';
}

function publicista_pollo_cookie_days_remaining() {
    $expires = publicista_pollo_cookie_expires();
    $ts = strtotime($expires . ' 23:59:59 UTC');
    if ($ts === false) return -1;
    $diff = $ts - time();
    return (int)floor($diff / 86400);
}


function publicista_pollo_prompt_char_limit() {
    return 2000;
}

function publicista_utf8_len($text) {
    $text = (string)$text;
    if (function_exists('mb_strlen')) {
        return (int)mb_strlen($text, 'UTF-8');
    }
    return strlen($text);
}

function publicista_utf8_substr($text, $start, $length = null) {
    $text = (string)$text;
    if (function_exists('mb_substr')) {
        return $length === null
            ? (string)mb_substr($text, (int)$start, null, 'UTF-8')
            : (string)mb_substr($text, (int)$start, (int)$length, 'UTF-8');
    }
    return $length === null
        ? (string)substr($text, (int)$start)
        : (string)substr($text, (int)$start, (int)$length);
}

function publicista_pollo_normalize_prompt_text($text, $collapseWhitespace = false) {
    $text = str_replace(array("\r\n", "\r"), "\n", (string)$text);
    if ($collapseWhitespace) {
        $text = preg_replace('/\s+/u', ' ', $text);
    } else {
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\n{3,}/u', "\n\n", $text);
    }
    return trim((string)$text);
}

function publicista_pollo_hard_cap_prompt($text, $maxChars) {
    $text = publicista_pollo_normalize_prompt_text($text, false);
    $maxChars = max(200, (int)$maxChars);
    if (publicista_utf8_len($text) <= $maxChars) {
        return $text;
    }

    $slice = publicista_utf8_substr($text, 0, $maxChars);
    $tailWindow = publicista_utf8_substr($slice, max(0, publicista_utf8_len($slice) - 160));
    $cutPos = -1;
    foreach (array("\n", '. ', '; ', ', ') as $needle) {
        $pos = strrpos($tailWindow, $needle);
        if ($pos !== false) {
            $candidate = publicista_utf8_len($slice) - publicista_utf8_len($tailWindow) + (int)$pos;
            if ($candidate > $maxChars - 180) {
                $cutPos = $candidate;
                break;
            }
        }
    }
    if ($cutPos > 0) {
        $slice = publicista_utf8_substr($slice, 0, $cutPos);
    }

    $slice = rtrim((string)$slice, " \t\n\r\0\x0B,;:-");
    if ($slice === '') {
        $slice = publicista_utf8_substr($text, 0, $maxChars);
    }
    return trim((string)$slice);
}

function publicista_pollo_prompt_compact_schema($maxChars) {
    return array(
        'type' => 'json_schema',
        'name' => 'publicista_pollo_prompt_compact',
        'strict' => true,
        'schema' => array(
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array('prompt'),
            'properties' => array(
                'prompt' => array(
                    'type' => 'string',
                    'maxLength' => (int)$maxChars,
                ),
            ),
        ),
    );
}

function publicista_pollo_is_prompt_too_big_error($message) {
    $message = strtolower(trim((string)$message));
    if ($message === '') return false;
    return (
        strpos($message, 'too_big') !== false ||
        strpos($message, 'maximum": 2000') !== false ||
        strpos($message, 'maximum":2000') !== false ||
        strpos($message, 'maximum 2000') !== false ||
        strpos($message, 'at most 2000') !== false
    );
}

function publicista_pollo_prepare_prompt($jobId, $prompt, $modelName = '', $hardMax = 2000, $forceAggressive = false) {
    $hardMax = max(200, min((int)$hardMax, publicista_pollo_prompt_char_limit()));
    $originalPrompt = publicista_pollo_normalize_prompt_text($prompt, false);
    $originalLength = publicista_utf8_len($originalPrompt);

    $result = array(
        'prompt' => $originalPrompt,
        'original_length' => $originalLength,
        'final_length' => $originalLength,
        'compacted' => false,
        'method' => 'original',
        'openai_request_id' => '',
        'openai_http_code' => 0,
        'openai_error' => '',
        'target_chars' => $hardMax,
    );

    if (!$forceAggressive && $originalLength <= $hardMax) {
        return array(true, $result);
    }

    $targetChars = $forceAggressive ? min(1800, max(1200, $hardMax - 220)) : min(1920, max(1400, $hardMax - 80));
    $targetChars = min($targetChars, $hardMax);

    $cfg = publicista_ai_config();
    $compactModel = trim((string)($cfg['descriptor_model'] ?? ''));
    if ($compactModel === '') {
        $defaults = publicista_ai_default_models();
        $compactModel = (string)($defaults['descriptor'] ?? 'gpt-5.4-mini');
    }

    $instructions = "Resume y reescribe el siguiente prompt para un generador de imágenes con límite estricto de " . $targetChars . " caracteres. "
        . "Debes conservar solo lo esencial para que la imagen salga bien. Prioriza, en este orden: "
        . "(1) outfit exacto y consistencia de vestuario, "
        . "(2) una sola mujer adulta con identidad/complexión coherente, "
        . "(3) el ambiente, fondo y luz seleccionados, evitando convertir la escena en un fondo liso o de estudio genérico si no se pidió, "
        . "(4) estilo fotográfico hiperrealista/editorial, "
        . "(5) encuadre y pose relevantes, "
        . "(6) restricciones críticas y negativos importantes, "
        . "Conserva de forma explícita cualquier instrucción sobre entorno, ambientación, habitación, apartamento, hotel, calle, terraza, muebles, puertas, suelo, ventana o tipo de iluminación cuando aparezca en el prompt original. No conviertas el fondo en un ciclorama, pared lisa o estudio vacío salvo que el prompt original pida claramente un entorno minimalista/studio. El resultado debe ser un único prompt final utilizable directamente, sin explicaciones, sin markdown y sin enumeraciones innecesarias. "
        . "No inventes detalles nuevos.\n\n[PROMPT ORIGINAL]\n" . $originalPrompt;

    $payload = array_merge(publicista_response_payload_defaults('pollo_prompt_compact', $compactModel), array(
        'model' => $compactModel,
        'store' => false,
        'input' => array(
            array('role' => 'system', 'content' => 'Devuelve exclusivamente un JSON válido siguiendo el esquema. No añadas explicaciones fuera del JSON.'),
            array('role' => 'user', 'content' => array(
                array('type' => 'input_text', 'text' => $instructions),
            )),
        ),
        'text' => array('format' => publicista_pollo_prompt_compact_schema($targetChars)),
    ));

    $response = publicista_openai_json_request('/v1/responses', $payload, $cfg['timeouts']['responses']);
    $result['openai_request_id'] = (string)publicista_array_get($response, 'request_id', '');
    $result['openai_http_code'] = (int)publicista_array_get($response, 'http_code', 0);
    $result['openai_error'] = trim((string)publicista_array_get($response, 'error', ''));

    $logPayload = $response;
    if (!empty($logPayload['raw_body']) && strlen($logPayload['raw_body']) > 150000) {
        $logPayload['raw_body'] = substr($logPayload['raw_body'], 0, 150000) . "\n...truncado...";
    }
    $logPayload['original_length'] = $originalLength;
    $logPayload['target_chars'] = $targetChars;
    $logPayload['model_name'] = $modelName;
    $logPayload['forced'] = $forceAggressive ? 1 : 0;
    publicista_job_log_write($jobId, 'pollo_prompt_compact', $logPayload);

    if ($response['ok']) {
        publicista_register_response_cost($jobId, $response, 'pollo_prompt_compact');
        $parsed = json_decode(publicista_response_output_text($response['decoded']), true);
        if (is_array($parsed)) {
            $candidatePrompt = publicista_pollo_normalize_prompt_text((string)publicista_array_get($parsed, 'prompt', ''), false);
            if ($candidatePrompt !== '') {
                $candidatePrompt = publicista_pollo_hard_cap_prompt($candidatePrompt, $hardMax);
                $candidateLength = publicista_utf8_len($candidatePrompt);
                if ($candidateLength > 0 && $candidateLength <= $hardMax) {
                    $result['prompt'] = $candidatePrompt;
                    $result['final_length'] = $candidateLength;
                    $result['compacted'] = ($candidatePrompt !== $originalPrompt);
                    $result['method'] = 'gpt_summary';
                    return array(true, $result);
                }
            }
        }
    }

    $fallbackPrompt = publicista_pollo_hard_cap_prompt($originalPrompt, $hardMax);
    $result['prompt'] = $fallbackPrompt;
    $result['final_length'] = publicista_utf8_len($fallbackPrompt);
    $result['compacted'] = ($fallbackPrompt !== $originalPrompt);
    $result['method'] = 'hard_cap_fallback';
    return array(true, $result);
}

function publicista_pollo_generate_image_request($prompt, $modelName) {
    $cookie = publicista_pollo_session_cookie();
    if ($cookie === '') {
        return array('ok' => false, 'error' => 'Pollo.ai: cookie de sesion no configurada. Guardala en Josue > ConfigM > Cookie Pollo.ai.');
    }
    if (!function_exists('curl_init')) {
        return array('ok' => false, 'error' => 'curl_init no esta disponible en PHP.');
    }

    $models = publicista_pollo_models();
    $cfg = isset($models[$modelName]) ? $models[$modelName] : $models['flux-dev'];

    $body = json_encode(array(
        '0' => array(
            'json' => array(
                'prompt'      => (string)$prompt,
                'modelName'   => $cfg['modelName'],
                'aspectRatio' => $cfg['aspectRatio'],
                'entryCode'   => 'web',
                'numOutputs'  => 1,
            ),
        ),
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Extraer solo el valor del token si viene con el nombre de la cookie
    $cookieValue = $cookie;
    if (strpos($cookie, '__Secure-next-auth.session-token=') !== false) {
        $parts = explode('=', $cookie, 2);
        $cookieValue = isset($parts[1]) ? trim($parts[1]) : $cookie;
    }

    $headers = array(
        'Content-Type: application/json',
        'Cookie: __Secure-next-auth.session-token=' . $cookieValue,
        'Referer: https://pollo.ai/app/ai-image',
        'Origin: https://pollo.ai',
        'Accept: application/json',
        'x-trpc-source: nextjs-react',
        'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    );

    $ch = curl_init('https://pollo.ai/api/trpc/text2Image.create?batch=1');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $rawBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError !== '') {
        return array('ok' => false, 'error' => 'curl_error: ' . $curlError);
    }
    if ($httpCode === 401) {
        return array('ok' => false, 'error' => 'Pollo.ai: sesion caducada (401). Renueva la cookie en Josue > ConfigM.');
    }
    if ($httpCode === 403) {
        return array('ok' => false, 'error' => 'Pollo.ai: acceso denegado (403). La cookie puede no ser valida o Cloudflare bloquea el servidor. Prueba desde otra IP o renueva la cookie.');
    }
    if ($httpCode < 200 || $httpCode >= 300) {
        return array('ok' => false, 'error' => 'Pollo.ai HTTP ' . $httpCode . ': ' . substr((string)$rawBody, 0, 200));
    }

    $decoded = json_decode((string)$rawBody, true);
    if (!is_array($decoded)) {
        return array('ok' => false, 'error' => 'Pollo.ai: respuesta no es JSON valido. Primeros bytes: ' . substr((string)$rawBody, 0, 100));
    }

    // Respuesta tRPC batch: [{result:{data:{json:{...}}}}]
    $inner = null;
    if (isset($decoded[0]['result']['data']['json'])) {
        $inner = $decoded[0]['result']['data']['json'];
    } elseif (isset($decoded[0]['result']['data'])) {
        $inner = $decoded[0]['result']['data'];
    }

    if (!is_array($inner)) {
        return array('ok' => false, 'error' => 'Pollo.ai: estructura de respuesta inesperada: ' . substr((string)$rawBody, 0, 300));
    }

    $generationId = $inner['id'] ?? $inner['generationId'] ?? null;
    if (!$generationId) {
        return array('ok' => false, 'error' => 'Pollo.ai: no se recibio ID de generacion. Respuesta: ' . substr(json_encode($inner), 0, 200));
    }

    return array('ok' => true, 'generation_id' => $generationId);
}

function publicista_pollo_poll_generation($generationId, $timeoutSec = 300) {
    $cookie = publicista_pollo_session_cookie();
    $cookieValue = $cookie;
    if (strpos($cookie, '__Secure-next-auth.session-token=') !== false) {
        $parts = explode('=', $cookie, 2);
        $cookieValue = isset($parts[1]) ? trim($parts[1]) : $cookie;
    }

    $headers = array(
        'Accept: application/json',
        'Cookie: __Secure-next-auth.session-token=' . $cookieValue,
        'Referer: https://pollo.ai/app/ai-image',
        'x-trpc-source: nextjs-react',
        'User-Agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    );

    $params = json_encode(array('0' => array('json' => array('id' => (int)$generationId))));
    $url = 'https://pollo.ai/api/trpc/generation.queryRecordDetail?batch=1&input=' . rawurlencode($params);

    $elapsed = 0;
    $interval = 4;

    while ($elapsed < $timeoutSec) {
        sleep($interval);
        $elapsed += $interval;

        if (!function_exists('curl_init')) continue;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $rawBody = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$rawBody) continue;

        $decoded = json_decode((string)$rawBody, true);
        if (!is_array($decoded)) continue;

        $inner = null;
        if (isset($decoded[0]['result']['data']['json'])) {
            $inner = $decoded[0]['result']['data']['json'];
        } elseif (isset($decoded[0]['result']['data'])) {
            $inner = $decoded[0]['result']['data'];
        }
        if (!is_array($inner)) continue;

        $status = strtolower(trim((string)($inner['status'] ?? $inner['state'] ?? '')));

        if (in_array($status, array('succeed', 'success', 'completed', 'done', 'finished'), true)) {
            // Buscar URL de imagen en los campos conocidos de Pollo.ai
            foreach (array('videoUrl', 'cover', 'thumbnail', 'url', 'imageUrl', 'image_url', 'outputUrl', 'output') as $field) {
                if (!empty($inner[$field])) {
                    $val = $inner[$field];
                    return array('ok' => true, 'url' => is_array($val) ? $val[0] : (string)$val);
                }
            }
            foreach (array('outputs', 'results', 'images', 'generations') as $key) {
                $items = is_array($inner[$key] ?? null) ? $inner[$key] : array();
                if (!empty($items)) {
                    $item = $items[0];
                    if (is_string($item)) return array('ok' => true, 'url' => $item);
                    if (is_array($item)) {
                        foreach (array('url', 'imageUrl', 'videoUrl') as $f) {
                            if (!empty($item[$f])) return array('ok' => true, 'url' => (string)$item[$f]);
                        }
                    }
                }
            }
            return array('ok' => false, 'error' => 'Pollo.ai: generacion terminada pero no se encontro URL de imagen. Respuesta: ' . substr(json_encode($inner), 0, 200));
        }

        if (in_array($status, array('failed', 'error', 'cancelled'), true)) {
            $reason = $inner['failReason'] ?? $inner['error'] ?? 'desconocido';
            return array('ok' => false, 'error' => 'Pollo.ai: la generacion fallo: ' . $reason);
        }
    }

    return array('ok' => false, 'error' => 'Pollo.ai: timeout tras ' . $timeoutSec . 's esperando la imagen.');
}

function publicista_build_pollo_environment_guard($job, $maxChars = 0) {
    $envDesc = publicista_build_setting_description($job);
    $pp = function_exists('publicista_job_production_params') ? publicista_job_production_params($job) : array();
    $settingKey = trim((string)($pp['setting'] ?? 'auto'));
    $setting = trim((string)($envDesc['setting'] ?? 'entorno realista con contexto'));
    $lighting = trim((string)($envDesc['lighting'] ?? 'luz realista y coherente'));

    if ($settingKey === 'minimalista') {
        $guard = '[AMBIENTE Y FONDO] Mantén el ambiente minimalista solicitado, pero con textura y profundidad reales. Fondo limpio y controlado, nunca un color plano vacío ni un recorte de estudio artificial. Iluminación: ' . $lighting . '.';
    } elseif ($settingKey !== '' && $settingKey !== 'auto') {
        $guard = '[AMBIENTE Y FONDO] El ambiente debe leerse claramente como ' . $setting . '. Deben verse elementos de contexto reales y coherentes con ese entorno; evita fondo liso, uniforme o de estudio genérico. Iluminación: ' . $lighting . '.';
    } else {
        $guard = '[AMBIENTE Y FONDO] Usa un entorno realista con contexto visible y profundidad natural, no un fondo uniforme o de estudio genérico. La escena debe incluir detalles ambientales creíbles alrededor de la mujer y luz coherente.';
    }

    $guard = trim(publicista_pollo_normalize_prompt_text($guard, false));
    $maxChars = (int)$maxChars;
    if ($maxChars > 0 && publicista_utf8_len($guard) > $maxChars) {
        $guard = trim(publicista_utf8_substr($guard, 0, $maxChars));
    }
    return $guard;
}

function publicista_pollo_download_image_bytes($url) {
    if (!function_exists('curl_init')) {
        return array(false, 'curl_init no disponible.');
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 4);
    $bytes = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError !== '') return array(false, 'curl_error al descargar imagen Pollo.ai: ' . $curlError);
    if ($httpCode < 200 || $httpCode >= 300 || $bytes === false || $bytes === '') {
        return array(false, 'No se pudo descargar la imagen de Pollo.ai CDN (HTTP ' . $httpCode . ').');
    }
    // Validar magic bytes para asegurar que es imagen y no HTML de error
    $head = substr((string)$bytes, 0, 16);
    $isImage = (
        substr($head, 0, 8) === "\x89PNG\r\n\x1a\n" ||
        substr($head, 0, 3) === "\xff\xd8\xff" ||
        (substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP')
    );
    // Aceptar aunque no se reconozca el formato si el archivo es grande (>5KB)
    if (!$isImage && strlen((string)$bytes) < 5000) {
        return array(false, 'Los bytes descargados no parecen una imagen valida (posiblemente HTML de error del CDN). Primeros bytes: ' . bin2hex(substr((string)$bytes, 0, 20)));
    }

    return array(true, (string)$bytes);
}


function publicista_pollo_batch_prompt_guard($prompt, $maxChars = 0, $job = null) {
    $prompt = trim((string)$prompt);
    $environmentGuard = is_array($job) ? publicista_build_pollo_environment_guard($job) : '';
    $guardParts = array(
        '[SALIDA] Una sola mujer adulta por imagen, una sola escena, resultado fotográfico realista y sin texto ni marca de agua.'
    );
    if ($environmentGuard !== '') {
        $guardParts[] = $environmentGuard;
    }
    $fullGuard = trim(implode("\n\n", $guardParts));
    if ($prompt === '') {
        return $fullGuard;
    }
    if (function_exists('mb_stripos')) {
        if (mb_stripos($prompt, '[SALIDA]') !== false || mb_stripos($prompt, '[AMBIENTE Y FONDO]') !== false) return $prompt;
    } else {
        if (stripos($prompt, '[SALIDA]') !== false || stripos($prompt, '[AMBIENTE Y FONDO]') !== false) return $prompt;
    }
    $combined = trim($prompt . "\n\n" . $fullGuard);
    $maxChars = (int)$maxChars;
    if ($maxChars > 0 && publicista_utf8_len($combined) > $maxChars) {
        $reserve = publicista_utf8_len("\n\n" . $fullGuard);
        $baseLimit = max(0, $maxChars - $reserve);
        $prompt = trim(publicista_utf8_substr($prompt, 0, $baseLimit));
        $combined = trim($prompt . "\n\n" . $fullGuard);
        if (publicista_utf8_len($combined) > $maxChars) {
            $combined = trim(publicista_utf8_substr($combined, 0, $maxChars));
        }
    }
    return $combined;
}

function publicista_pollo_batch_retry_delay_seconds($attempt) {
    $attempt = (int)$attempt;
    if ($attempt <= 1) return 0;
    if ($attempt === 2) return 8;
    return 12;
}

function publicista_pollo_batch_is_retryable_error($error) {
    $error = strtolower(trim((string)$error));
    if ($error === '') return true;
    $needles = array(
        'la generacion fallo: desconocido',
        'error desconocido',
        'timeout',
        'solo devolvio',
        'solo se pudieron descargar',
        'no devolvio imagenes descargadas validas',
        'no se pudo descargar ninguna imagen',
        'no se encontraron outputs',
        'http 500',
        'http 502',
        'http 503',
        'http 504'
    );
    foreach ($needles as $needle) {
        if (strpos($error, $needle) !== false) return true;
    }
    return false;
}

function publicista_generate_candidate_images_pollo_batch($jobId, $numOutputs, $prompt, $modelName, $job = null) {
    $cookie = publicista_pollo_session_cookie();
    if ($cookie === '') {
        return array(false, 'Pollo.ai: cookie de sesion no configurada. Guardala en Josue > ConfigM > Cookie Pollo.ai.');
    }

    $worker = BASE_PATH . '/tools/pollo_image_worker.py';
    if (!file_exists($worker)) {
        return array(false, 'No se encontro el worker Pollo.ai en tools/pollo_image_worker.py');
    }

    $models = publicista_pollo_models();
    $modelKey = isset($models[$modelName]) ? $modelName : 'flux-dev';
    $paths = publicista_job_fs_paths($jobId);
    if (!publicista_ensure_job_dirs($jobId)) {
        return array(false, 'No se pudo crear la carpeta del trabajo para Pollo.ai.');
    }

    $numOutputs = max(1, min(4, (int)$numOutputs));
    $jsonFs = $paths['meta_dir'] . '/pollo_result_batch.json';
    $workerAttempt = 0;
    $maxWorkerAttempts = 3;
    $resultData = null;
    $promptMeta = null;
    $promptToUse = trim((string)$prompt);

    list($okPromptPrep, $promptMeta) = publicista_pollo_prepare_prompt($jobId, $prompt, $modelKey, publicista_pollo_prompt_char_limit(), false);
    if (!$okPromptPrep || !is_array($promptMeta)) {
        return array(false, is_string($promptMeta) ? $promptMeta : 'No se pudo preparar el prompt para Pollo.ai.');
    }
    $promptToUse = publicista_pollo_batch_prompt_guard(trim((string)($promptMeta['prompt'] ?? $prompt)), publicista_pollo_prompt_char_limit(), is_array($job) ? $job : null);

    while ($workerAttempt < $maxWorkerAttempts) {
        $workerAttempt++;

        $delaySec = publicista_pollo_batch_retry_delay_seconds($workerAttempt);
        if ($delaySec > 0) {
            sleep($delaySec);
        }

        $delaySec = publicista_pollo_batch_retry_delay_seconds($workerAttempt);
        if ($delaySec > 0) {
            sleep($delaySec);
        }

        @unlink($jsonFs);
        foreach ((array)glob($paths['candidates_dir'] . '/pollo_batch_*') as $oldBatchFile) {
            @unlink($oldBatchFile);
        }

        publicista_job_log_write($jobId, 'pollo_prompt_prepare_batch_try' . $workerAttempt, array(
            'model' => $modelKey,
            'attempt' => $workerAttempt,
            'original_length' => (int)($promptMeta['original_length'] ?? publicista_utf8_len($prompt)),
            'final_length' => publicista_utf8_len($promptToUse),
            'compacted' => !empty($promptMeta['compacted']) ? 1 : 0,
            'method' => (string)($promptMeta['method'] ?? 'original'),
            'openai_request_id' => (string)($promptMeta['openai_request_id'] ?? ''),
            'openai_http_code' => (int)($promptMeta['openai_http_code'] ?? 0),
            'openai_error' => (string)($promptMeta['openai_error'] ?? ''),
            'num_outputs' => (int)$numOutputs,
            'prompt_guard_applied' => 1,
        ));

        $fullCommand = 'python3 ' . escapeshellarg($worker)
            . ' generate'
            . ' --cookie ' . escapeshellarg($cookie)
            . ' --prompt ' . escapeshellarg($promptToUse)
            . ' --model ' . escapeshellarg($modelKey)
            . ' --num-outputs ' . escapeshellarg((string)$numOutputs)
            . ' --output-dir ' . escapeshellarg($paths['candidates_dir'])
            . ' --output-prefix ' . escapeshellarg('pollo_batch_')
            . ' --output-json ' . escapeshellarg($jsonFs)
            . ' --timeout 420';

        $proc = publicista_proc_command($fullCommand, 520, BASE_PATH);
        publicista_job_log_write($jobId, 'pollo_worker_batch_try' . $workerAttempt, array(
            'ok' => $proc['ok'],
            'exit_code' => $proc['exit_code'],
            'stdout' => substr((string)$proc['stdout'], 0, 4000),
            'stderr' => substr((string)$proc['stderr'], 0, 1000),
            'model' => $modelKey,
            'prompt_length' => publicista_utf8_len($promptToUse),
            'prompt_compacted' => !empty($promptMeta['compacted']) ? 1 : 0,
            'prompt_method' => (string)($promptMeta['method'] ?? 'original'),
            'prompt_guard_applied' => 1,
            'num_outputs' => (int)$numOutputs,
        ));

        $resultData = null;
        if (file_exists($jsonFs)) {
            $resultData = json_decode((string)@file_get_contents($jsonFs), true);
        }
        if (!is_array($resultData) && trim((string)$proc['stdout']) !== '') {
            $resultData = json_decode(trim((string)$proc['stdout']), true);
        }
        if (!is_array($resultData)) {
            $stderr = trim((string)$proc['stderr']);
            $jsonError = 'Pollo.ai worker batch: no se pudo leer el resultado JSON. '
                . ($stderr !== '' ? 'Stderr: ' . substr($stderr, 0, 300) : 'Sin salida del proceso.');
            if ($workerAttempt < $maxWorkerAttempts) {
                publicista_job_log_write($jobId, 'pollo_worker_batch_retry_json_' . $workerAttempt, array('error' => $jsonError));
                continue;
            }
            return array(false, $jsonError);
        }

        if (empty($resultData['ok'])) {
            $error = trim((string)($resultData['error'] ?? 'Error desconocido del worker Pollo.ai.'));
            if ($workerAttempt < $maxWorkerAttempts && publicista_pollo_is_prompt_too_big_error($error)) {
                list($okPromptRetry, $retryPromptMeta) = publicista_pollo_prepare_prompt($jobId, $prompt, $modelKey, publicista_pollo_prompt_char_limit(), true);
                if ($okPromptRetry && is_array($retryPromptMeta)) {
                    $retryPrompt = trim((string)($retryPromptMeta['prompt'] ?? ''));
                    if ($retryPrompt !== '' && $retryPrompt !== $promptToUse) {
                        $promptMeta = $retryPromptMeta;
                        $promptToUse = publicista_pollo_batch_prompt_guard($retryPrompt, publicista_pollo_prompt_char_limit(), is_array($job) ? $job : null);
                        continue;
                    }
                }
            }
            if ($workerAttempt < $maxWorkerAttempts && publicista_pollo_batch_is_retryable_error($error)) {
                publicista_job_log_write($jobId, 'pollo_worker_batch_retry_error_' . $workerAttempt, array('error' => $error));
                continue;
            }
            return array(false, $error);
        }

        $images = array();
        foreach ((array)($resultData['images'] ?? array()) as $item) {
            $fsPath = trim((string)($item['path'] ?? ''));
            if ($fsPath === '' || !file_exists($fsPath)) {
                continue;
            }
            $images[] = array(
                'index' => (int)($item['index'] ?? (count($images) + 1)),
                'raw_fs_path' => $fsPath,
                'raw_path' => publicista_path_to_web($fsPath),
                'image_url' => (string)($item['url'] ?? ''),
                'size_bytes' => (int)($item['size_bytes'] ?? (@filesize($fsPath) ?: 0)),
                'ext' => (string)($item['ext'] ?? pathinfo($fsPath, PATHINFO_EXTENSION)),
            );
        }
        usort($images, function($a, $b) {
            return ((int)($a['index'] ?? 0) <=> (int)($b['index'] ?? 0));
        });

        if (count($images) < $numOutputs) {
            $partialError = 'Pollo.ai: el lote terminó, pero solo devolvió ' . count($images) . ' de ' . $numOutputs . ' imágenes finales.';
            publicista_job_log_write($jobId, 'pollo_worker_batch_incomplete_' . $workerAttempt, array(
                'expected' => $numOutputs,
                'received' => count($images),
                'error' => $partialError,
                'worker_result' => $resultData,
            ));
            if ($workerAttempt < $maxWorkerAttempts) {
                continue;
            }
            return array(false, $partialError);
        }

        return array(true, array(
            'prompt' => $promptToUse,
            'model' => $modelKey,
            'request_id' => '',
            'http_code' => !empty($resultData['ok']) ? 200 : 0,
            'attempts' => $workerAttempt,
            'retry_applied' => $workerAttempt > 1 ? 1 : 0,
            'generation_id' => (string)($resultData['generation_id'] ?? ''),
            'images' => $images,
            'worker_result' => $resultData,
        ));
    }

    return array(false, 'Pollo.ai worker batch no devolvió un resultado válido.');
}

function publicista_generate_candidate_image_pollo($jobId, $candidateIndex, $prompt, $modelName) {
    // NOTA: No usamos curl de PHP directamente porque Cloudflare bloquea el
    // fingerprint TLS de libcurl (403). En su lugar llamamos al worker Python
    // (tools/pollo_image_worker.py) que usa curl-cffi para impersonar Chrome,
    // igual que hace publicista_image_worker.py para el procesado de imagenes.

    $candidateSafe = str_pad((string)$candidateIndex, 2, '0', STR_PAD_LEFT);
    $cookie = publicista_pollo_session_cookie();

    if ($cookie === '') {
        return array(false, 'Pollo.ai: cookie de sesion no configurada. Guardala en Josue > ConfigM > Cookie Pollo.ai.');
    }

    $worker = BASE_PATH . '/tools/pollo_image_worker.py';
    if (!file_exists($worker)) {
        return array(false, 'No se encontro el worker Pollo.ai en tools/pollo_image_worker.py');
    }

    $models = publicista_pollo_models();
    $modelKey = isset($models[$modelName]) ? $modelName : 'flux-dev';

    // Paths de salida
    $paths   = publicista_job_fs_paths($jobId);
    $rawFs   = $paths['candidates_dir'] . '/candidate_' . $candidateSafe . '_raw.png';
    $jsonFs  = $paths['meta_dir'] . '/pollo_result_' . $candidateSafe . '.json';

    if (!publicista_ensure_job_dirs($jobId)) {
        return array(false, 'No se pudo crear la carpeta del trabajo para Pollo.ai.');
    }

    list($okPromptPrep, $promptMeta) = publicista_pollo_prepare_prompt($jobId, $prompt, $modelKey, publicista_pollo_prompt_char_limit(), false);
    if (!$okPromptPrep || !is_array($promptMeta)) {
        return array(false, is_string($promptMeta) ? $promptMeta : 'No se pudo preparar el prompt para Pollo.ai.');
    }

    $promptToUse = trim((string)($promptMeta['prompt'] ?? $prompt));
    $workerAttempt = 0;
    $maxWorkerAttempts = 2;
    $resultData = null;
    $proc = null;

    while ($workerAttempt < $maxWorkerAttempts) {
        $workerAttempt++;

        $delaySec = publicista_pollo_batch_retry_delay_seconds($workerAttempt);
        if ($delaySec > 0) {
            sleep($delaySec);
        }

        publicista_job_log_write($jobId, 'pollo_prompt_prepare_' . $candidateSafe . '_try' . $workerAttempt, array(
            'model' => $modelKey,
            'attempt' => $workerAttempt,
            'original_length' => (int)($promptMeta['original_length'] ?? publicista_utf8_len($prompt)),
            'final_length' => (int)($promptMeta['final_length'] ?? publicista_utf8_len($promptToUse)),
            'compacted' => !empty($promptMeta['compacted']) ? 1 : 0,
            'method' => (string)($promptMeta['method'] ?? 'original'),
            'openai_request_id' => (string)($promptMeta['openai_request_id'] ?? ''),
            'openai_http_code' => (int)($promptMeta['openai_http_code'] ?? 0),
            'openai_error' => (string)($promptMeta['openai_error'] ?? ''),
        ));

        // Construir comando — escapeshellarg envuelve la cookie en comillas simples,
        // lo que es seguro para JWT (no contienen comillas simples).
        // No usamos variable de entorno porque en sh POSIX la expansion de "$VAR"
        // ocurre ANTES del inline assignment, dejando la cookie vacia.
        $fullCommand = 'python3 ' . escapeshellarg($worker)
            . ' generate'
            . ' --cookie '       . escapeshellarg($cookie)
            . ' --prompt '       . escapeshellarg($promptToUse)
            . ' --model '        . escapeshellarg($modelKey)
            . ' --output-image ' . escapeshellarg($rawFs)
            . ' --output-json '  . escapeshellarg($jsonFs)
            . ' --timeout 300';

        $proc = publicista_proc_command($fullCommand, 360, BASE_PATH);

        publicista_job_log_write($jobId, 'pollo_worker_' . $candidateSafe . '_try' . $workerAttempt, array(
            'ok' => $proc['ok'],
            'exit_code' => $proc['exit_code'],
            'stdout' => substr($proc['stdout'], 0, 2000),
            'stderr' => substr($proc['stderr'], 0, 500),
            'model' => $modelKey,
            'prompt_length' => publicista_utf8_len($promptToUse),
            'prompt_compacted' => !empty($promptMeta['compacted']) ? 1 : 0,
            'prompt_method' => (string)($promptMeta['method'] ?? 'original'),
        ));

        $resultData = null;
        if (file_exists($jsonFs)) {
            $resultData = json_decode((string)@file_get_contents($jsonFs), true);
        }
        if (!is_array($resultData) && trim((string)$proc['stdout']) !== '') {
            $resultData = json_decode(trim((string)$proc['stdout']), true);
        }

        if (!is_array($resultData)) {
            $stderr = trim((string)$proc['stderr']);
            $jsonError = 'Pollo.ai worker: no se pudo leer el resultado JSON. '
                . ($stderr !== '' ? 'Stderr: ' . substr($stderr, 0, 300) : 'Sin salida del proceso.');
            if ($workerAttempt < $maxWorkerAttempts) {
                publicista_job_log_write($jobId, 'pollo_worker_retry_json_' . $candidateSafe . '_try' . $workerAttempt, array('error' => $jsonError));
                continue;
            }
            return array(false, $jsonError);
        }

        if (!empty($resultData['ok'])) {
            break;
        }

        $error = trim((string)($resultData['error'] ?? 'Error desconocido del worker Pollo.ai.'));
        if ($workerAttempt < $maxWorkerAttempts && publicista_pollo_batch_is_retryable_error($error)) {
            publicista_job_log_write($jobId, 'pollo_worker_retry_error_' . $candidateSafe . '_try' . $workerAttempt, array('error' => $error));
            continue;
        }

        if ($workerAttempt >= $maxWorkerAttempts || !publicista_pollo_is_prompt_too_big_error($error)) {
            return array(false, $error);
        }

        list($okPromptRetry, $retryPromptMeta) = publicista_pollo_prepare_prompt($jobId, $prompt, $modelKey, publicista_pollo_prompt_char_limit(), true);
        if (!$okPromptRetry || !is_array($retryPromptMeta)) {
            return array(false, is_string($retryPromptMeta) ? $retryPromptMeta : $error);
        }

        $retryPrompt = trim((string)($retryPromptMeta['prompt'] ?? ''));
        if ($retryPrompt === '' || $retryPrompt === $promptToUse) {
            return array(false, $error);
        }

        $promptMeta = $retryPromptMeta;
        $promptToUse = $retryPrompt;
    }

    if (!is_array($resultData) || empty($resultData['ok'])) {
        $error = is_array($resultData) ? trim((string)($resultData['error'] ?? 'Error desconocido del worker Pollo.ai.')) : 'Error desconocido del worker Pollo.ai.';
        return array(false, $error);
    }

    $imagePath = trim((string)($resultData['image_path'] ?? $rawFs));
    if (!file_exists($imagePath)) {
        return array(false, 'Pollo.ai worker: el worker indico exito pero no se encontro la imagen en: ' . $imagePath);
    }

    $webPath = publicista_path_to_web($imagePath);

    return array(true, array(
        'raw_path' => $webPath,
        'request_id' => (string)($resultData['generation_id'] ?? ''),
        'http_code' => 200,
        'model' => $modelKey,
        'raw_fs_path' => $imagePath,
        'prompt' => $promptToUse,
        'attempts' => $workerAttempt,
        'retry_applied' => $workerAttempt > 1,
        'backend' => (string)($resultData['backend'] ?? 'python'),
    ));
}
