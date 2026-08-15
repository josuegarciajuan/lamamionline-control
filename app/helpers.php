<?php

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function crm_phone_digits($value) {
    return preg_replace('/\D+/', '', (string)$value) ?: '';
}

function crm_phone_copy_value($value) {
    $digits = crm_phone_digits($value);
    if ($digits === '') return '';
    if (strlen($digits) >= 11 && substr($digits, 0, 2) === '34') {
        return substr($digits, -9);
    }
    if (strlen($digits) > 9) {
        return substr($digits, -9);
    }
    return $digits;
}

function crm_is_phone_like_value($value) {
    return strlen(crm_phone_digits($value)) >= 6;
}

function crm_is_phone_field_name($name) {
    $name = strtolower(trim((string)$name));
    if ($name === '') return false;
    return (bool)preg_match('/(^|_)(telefono|tfono|phone|movil)(_|$)/', $name);
}

function crm_render_copy_value($text, $options = array()) {
    $text = (string)$text;
    $options = is_array($options) ? $options : array();
    $vertical = !empty($options['vertical']);
    $strong = !empty($options['strong']);
    $telLink = !empty($options['tel_link']);
    $phone = !empty($options['phone']);
    $copyValue = isset($options['copy_value']) ? trim((string)$options['copy_value']) : '';
    if ($copyValue === '') {
        $copyValue = $phone ? crm_phone_copy_value($text) : $text;
    }
    $classes = 'copy-row' . ($vertical ? ' copy-row-vertical' : '');

    echo '<div class="' . e($classes) . '">';

    if ($text === '') {
        echo '<span>-</span>';
    } else {
        if ($telLink) {
            echo '<a href="tel:' . e($text) . '">' . e($text) . '</a>';
        } elseif ($strong) {
            echo '<strong>' . e($text) . '</strong>';
        } elseif ($vertical) {
            echo '<span>' . nl2br(e($text)) . '</span>';
        } else {
            echo '<span>' . e($text) . '</span>';
        }
    }

    if ($text !== '' && $copyValue !== '') {
        echo '<button type="button" class="btn-copy-mini" data-copy="' . e($copyValue) . '">Copiar</button>';
    }
    echo '</div>';
}

function crm_render_phone_value($text, $options = array()) {
    $options = is_array($options) ? $options : array();
    $options['phone'] = true;
    if (!isset($options['copy_value'])) {
        $options['copy_value'] = crm_phone_copy_value($text);
    }
    crm_render_copy_value($text, $options);
}

function now_datetime() {
    return date('Y-m-d H:i:s');
}

function today_date() {
    return date('Y-m-d');
}

function today_datetime_local() {
    return date('Y-m-d\TH:i');
}

function business_cutoff_hour() {
    return 9;
}

function business_parse_ts($value) {
    $raw = trim((string)$value);
    if ($raw === '') return 0;
    return strtotime(str_replace('T', ' ', $raw));
}

function business_shifted_ts($ts) {
    $ts = (int)$ts;
    if ($ts <= 0) return 0;
    return $ts - (business_cutoff_hour() * 3600);
}

function business_day_key_from_ts($ts) {
    $shifted = business_shifted_ts($ts);
    return $shifted > 0 ? date('Y-m-d', $shifted) : '';
}

function business_month_key_from_ts($ts) {
    $shifted = business_shifted_ts($ts);
    return $shifted > 0 ? date('Y-m', $shifted) : '';
}

function business_day_key_from_value($value) {
    return business_day_key_from_ts(business_parse_ts($value));
}

function business_month_key_from_value($value) {
    return business_month_key_from_ts(business_parse_ts($value));
}

function business_today_date() {
    return business_day_key_from_ts(time());
}

function business_current_month_key() {
    return business_month_key_from_ts(time());
}

function business_month_total_days($monthKey) {
    $ts = strtotime($monthKey . '-01');
    return $ts ? (int)date('t', $ts) : 30;
}

function business_month_elapsed_days($monthKey) {
    if ($monthKey === business_current_month_key()) {
        $today = business_today_date();
        return (int)date('j', strtotime($today));
    }
    return business_month_total_days($monthKey);
}

function business_range_bounds($from, $to) {
    $hour = sprintf('%02d', business_cutoff_hour());

    $start = null;
    if ($from !== '') {
        $start = strtotime($from . ' ' . $hour . ':00:00');
    }

    $end = null;
    if ($to !== '') {
        $endStart = strtotime($to . ' ' . $hour . ':00:00');
        if ($endStart) {
            $end = strtotime('+1 day', $endStart) - 1;
        }
    }

    return array($start, $end);
}

function business_month_bounds($monthKey) {
    $hour = sprintf('%02d', business_cutoff_hour());
    $start = strtotime($monthKey . '-01 ' . $hour . ':00:00');
    $end = $start ? (strtotime('+1 month', $start) - 1) : 0;
    return array($start, $end);
}

function generate_id($prefix) {
    try {
        return $prefix . '_' . bin2hex(random_bytes(4));
    } catch (Exception $e) {
        return $prefix . '_' . substr(md5(uniqid('', true)), 0, 8);
    }
}

function to_float($value, $default = 0) {
    if ($value === null || $value === '') return (float)$default;
    $value = str_replace(',', '.', (string)$value);
    return (float)$value;
}

function euro($number) {
    return number_format((float)$number, 2, ',', '.') . ' €';
}

function set_flash($type, $message, $fx = '') {
    $_SESSION['flash'] = array('type' => $type, 'message' => $message, 'fx' => $fx);
}

function get_flash() {
    if (empty($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function request_get($key, $default = '') {
    return isset($_GET[$key]) ? $_GET[$key] : $default;
}

function request_post($key, $default = '') {
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = sha1(uniqid('csrf_', true));
        }
    }
    return (string)$_SESSION['csrf_token'];
}

function csrf_validate($token) {
    $expected = isset($_SESSION['csrf_token']) ? (string)$_SESSION['csrf_token'] : '';
    $token = is_string($token) ? $token : '';
    if ($expected === '' || $token === '') {
        return false;
    }
    return hash_equals($expected, $token);
}

function eureka_build_codex_prompt($descripcion) {
    $descripcion = trim((string)$descripcion);
    if ($descripcion === '') return '';

    return "Quiero implementar esta mejora en el CRM actual.\n\n"
        . "Idea a desarrollar:\n"
        . $descripcion . "\n\n"
        . "Instrucciones para Codex:\n"
        . "- Estudia primero el flujo ya existente relacionado con esta idea.\n"
        . "- Haz cambios mínimos y seguros.\n"
        . "- Reutiliza componentes, helpers y patrones ya presentes en el proyecto.\n"
        . "- Mantén el estilo visual y la estructura actual del CRM.\n"
        . "- Si hay que tocar backend, vistas, datos o automatizaciones, intégralo de forma coherente con la arquitectura existente.\n"
        . "- Si modificas JS o CSS, actualiza la versión de caché en index.php.\n"
        . "- Valida la sintaxis y los checks relevantes al terminar.\n\n"
        . "Resultado esperado:\n"
        . "- Implementación completa de la mejora.\n"
        . "- Explicación breve de qué se ha cambiado.\n"
        . "- Resumen final de archivos tocados, validaciones hechas y riesgos si los hubiera.\n";
}

function eureka_create_row($descripcion, $source = 'manual', $extra = array()) {
    $descripcion = trim((string)$descripcion);
    $source = trim((string)$source);
    $extra = is_array($extra) ? $extra : array();

    return array_merge(array(
        'id' => generate_id('eur'),
        'descripcion' => $descripcion,
        'estado' => 'pendiente',
        'prompt_codex' => '',
        'prompt_generated_at' => '',
        'source' => ($source !== '' ? $source : 'manual'),
        'updated_at' => now_datetime(),
        'created_at' => now_datetime(),
    ), $extra);
}

function redirect_to($url) {
    header('Location: ' . $url);
    exit;
}

/** Devuelve solo redirects internos; evita saltos externos e inyección de cabeceras. */
function safe_internal_redirect_path($url, $fallback = 'index.php?page=dashboard') {
    $url = trim((string)$url);
    $fallback = trim((string)$fallback);
    if ($fallback === '') $fallback = 'index.php?page=dashboard';
    if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) return $fallback;
    if (strpos($url, '//') === 0 || strpos($url, '\\') === 0) return $fallback;

    $parts = @parse_url($url);
    if ($parts === false) return $fallback;
    foreach (array('scheme', 'host', 'user', 'pass') as $externalPart) {
        if (isset($parts[$externalPart]) && $parts[$externalPart] !== '') return $fallback;
    }
    return $url;
}

function lamami_tab_url($tab = 'interesadas', $params = array()) {
    $query = array_merge(array(
        'page' => 'lamami',
        'tab' => $tab,
    ), $params);

    return 'index.php?' . http_build_query($query);
}

function publicista_page_url($tab = 'crear_perfiles', $params = array()) {
    $query = array_merge(array(
        'page' => 'publicista',
        'tab' => $tab,
    ), $params);

    return 'index.php?' . http_build_query($query);
}

function publicista_tab_url($params = array()) {
    return publicista_page_url('crear_perfiles', $params);
}

function comercial_page_url($tab = 'resumen', $params = array()) {
    $query = array_merge(array(
        'page' => 'comercial',
        'tab' => $tab,
    ), $params);

    return 'index.php?' . http_build_query($query);
}

function inbox_page_url($tab = 'conversaciones', $params = array()) {
    $query = array_merge(array(
        'page' => 'inbox',
        'tab' => $tab,
    ), $params);
    return 'index.php?' . http_build_query($query);
}

function inbox_get_settings() {
    $defaults = array(
        'replies_enabled' => true,
        'opener_enabled'  => true,
    );
    $stored = storage_read('inbox_settings.json');
    return is_array($stored) ? array_merge($defaults, $stored) : $defaults;
}

function inbox_save_settings($settings) {
    storage_write('inbox_settings.json', (array)$settings);
}

/**
 * Devuelve array de line_id que están asignados a algún proceso comercial.
 */
function inbox_get_process_line_ids() {
    $processes = comercial_get_processes();
    $ids = array();
    foreach ($processes as $p) {
        foreach ((array)($p['assigned_line_ids'] ?? array()) as $lid) {
            $lid = trim((string)$lid);
            if ($lid !== '' && !in_array($lid, $ids, true)) {
                $ids[] = $lid;
            }
        }
    }
    return $ids;
}

/**
 * Filtra threads cuyo line_id esté en la lista proporcionada.
 */
function inbox_filter_threads_by_lines($threads, $lineIds) {
    if (empty($lineIds)) return $threads; // sin filtro si no hay líneas definidas
    $out = array();
    foreach ($threads as $thread) {
        $lid = trim((string)($thread['line_id'] ?? ''));
        if ($lid !== '' && in_array($lid, $lineIds, true)) {
            $out[] = $thread;
        }
    }
    return $out;
}

function publicista_clienta_source_label($scope) {
    return $scope === 'jostal' ? 'Jostal' : 'LaMami';
}

function publicista_clienta_storage_file($scope) {
    return $scope === 'jostal' ? 'jostal_clientas.json' : 'clientes.json';
}

function publicista_normalize_clienta_row($row, $scope) {
    $row = is_array($row) ? $row : array();
    $scope = $scope === 'jostal' ? 'jostal' : 'lamami';

    $nombre = trim((string)($row['nombre'] ?? ''));
    if ($nombre === '') $nombre = 'Sin nombre';

    $telefono = trim((string)($row['telefono'] ?? ''));
    $localidad = trim((string)($row['localidad'] ?? ''));
    $provincia = trim((string)($row['provincia'] ?? ''));
    $location = trim($localidad . ($provincia !== '' ? ' · ' . $provincia : ''));

    if ($scope === 'jostal') {
        $status = function_exists('jostal_clienta_en_casa') && jostal_clienta_en_casa($row) ? 'En casa' : 'Fuera de casa';
    } else {
        $estado = trim((string)($row['estado'] ?? ''));
        $status = $estado !== '' ? ucfirst($estado) : 'Sin estado';
    }

    $parts = array($nombre, publicista_clienta_source_label($scope));
    if ($telefono !== '') $parts[] = $telefono;
    if ($location !== '') $parts[] = $location;
    if ($status !== '') $parts[] = $status;
    $displayLabel = implode(' · ', $parts);

    $searchParts = array(
        $nombre,
        publicista_clienta_source_label($scope),
        $telefono,
        $localidad,
        $provincia,
        $status,
        trim((string)($row['observaciones'] ?? '')),
        trim((string)($row['notas'] ?? '')),
        trim((string)($row['modo'] ?? '')),
        trim((string)($row['zona'] ?? '')),
    );

    return array(
        'id' => trim((string)($row['id'] ?? '')),
        'scope' => $scope,
        'scope_label' => publicista_clienta_source_label($scope),
        'storage_file' => publicista_clienta_storage_file($scope),
        'picker_value' => $scope . ':' . trim((string)($row['id'] ?? '')),
        'nombre' => $nombre,
        'telefono' => $telefono,
        'localidad' => $localidad,
        'provincia' => $provincia,
        'services' => trim((string)($row['servicios'] ?? '')),
        'tarifas' => trim((string)($row['tarifas'] ?? '')),
        'status_label' => $status,
        'display_label' => $displayLabel,
        'search_text' => strtolower(trim(implode(' ', array_filter($searchParts, function ($v) {
            return trim((string)$v) !== '';
        })))),
        'row' => $row,
    );
}

function publicista_all_clientas() {
    $out = array();

    foreach (storage_read('clientes.json') as $row) {
        if (!is_array($row) || empty($row['id'])) continue;
        $out[] = publicista_normalize_clienta_row($row, 'lamami');
    }

    foreach (storage_read('jostal_clientas.json') as $row) {
        if (!is_array($row) || empty($row['id'])) continue;
        $out[] = publicista_normalize_clienta_row($row, 'jostal');
    }

    usort($out, function ($a, $b) {
        $an = function_exists('mb_strtolower') ? mb_strtolower(trim((string)($a['nombre'] ?? '')), 'UTF-8') : strtolower(trim((string)($a['nombre'] ?? '')));
        $bn = function_exists('mb_strtolower') ? mb_strtolower(trim((string)($b['nombre'] ?? '')), 'UTF-8') : strtolower(trim((string)($b['nombre'] ?? '')));
        if ($an === $bn) {
            $as = trim((string)($a['scope'] ?? ''));
            $bs = trim((string)($b['scope'] ?? ''));
            if ($as === $bs) return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
            return strcmp($as, $bs);
        }
        return strcmp($an, $bn);
    });

    return $out;
}

function publicista_parse_clienta_picker_value($value) {
    $raw = trim((string)$value);
    if ($raw === '') {
        return array('scope' => '', 'id' => '');
    }

    if (strpos($raw, ':') !== false) {
        list($scope, $id) = explode(':', $raw, 2);
        $scope = trim((string)$scope);
        $id = trim((string)$id);
        if (in_array($scope, array('lamami', 'jostal'), true) && $id !== '') {
            return array('scope' => $scope, 'id' => $id);
        }
    }

    return array('scope' => '', 'id' => $raw);
}

function publicista_find_clienta_any($clientaId, $preferredScope = '') {
    $clientaId = trim((string)$clientaId);
    $preferredScope = trim((string)$preferredScope);
    if ($clientaId === '') return null;

    $scopes = array();
    if (in_array($preferredScope, array('lamami', 'jostal'), true)) {
        $scopes[] = $preferredScope;
    }
    foreach (array('lamami', 'jostal') as $scope) {
        if (!in_array($scope, $scopes, true)) $scopes[] = $scope;
    }

    foreach ($scopes as $scope) {
        $row = storage_find_by_id(publicista_clienta_storage_file($scope), $clientaId);
        if ($row) {
            return publicista_normalize_clienta_row($row, $scope);
        }
    }

    return null;
}

function publicista_clienta_picker_selected_value($clientaId, $scope = '') {
    $clientaId = trim((string)$clientaId);
    $scope = trim((string)$scope);
    if ($clientaId === '') return '';

    if (!in_array($scope, array('lamami', 'jostal'), true)) {
        $ref = publicista_find_clienta_any($clientaId);
        $scope = $ref['scope'] ?? 'lamami';
    }

    return $scope . ':' . $clientaId;
}

function publicista_clienta_edit_url($clientaId, $scope = '') {
    $ref = publicista_find_clienta_any($clientaId, $scope);
    if (!$ref) return '';

    if (($ref['scope'] ?? '') === 'jostal') {
        return 'index.php?page=jostal&tab=clientas&edit=' . urlencode($ref['id']);
    }

    return lamami_tab_url('clientas', array('edit' => $ref['id']));
}

function publicista_job_status_options() {
    return array(
        'draft' => 'Borrador',
        'processing' => 'Procesando',
        'needs_review' => 'Revisar',
        'done' => 'Finalizado',
        'error' => 'Error',
        'archived' => 'Archivado',
    );
}

function publicista_job_status_label($status) {
    $options = publicista_job_status_options();
    $status = trim((string)$status);
    if ($status === 'configured') {
        $status = 'needs_review';
    }
    return isset($options[$status]) ? $options[$status] : ($status !== '' ? $status : 'Borrador');
}



function publicista_copy_tone_options() {
    return array(
        'equilibrado' => 'Equilibrado',
        'elegante' => 'Elegante / premium',
        'cercano' => 'Cercano / natural',
        'sugerente' => 'Sugerente (sin explícitos)',
    );
}

// --- Opciones de producción visual (ropa, ambiente, etc.) ---

function publicista_outfit_color_options() {
    return array(
        'auto'      => 'Auto (el modelo elige)',
        'negro'     => 'Negro',
        'rojo'      => 'Rojo',
        'burdeos'   => 'Burdeos / vino',
        'nude'      => 'Nude / beige',
        'blanco'    => 'Blanco',
        'azul'      => 'Azul marino / navy',
        'verde'     => 'Verde esmeralda',
        'dorado'    => 'Dorado / champán',
        'fucsia'    => 'Fucsia / rosa oscuro',
        'plateado'  => 'Plateado / metálico',
    );
}

function publicista_outfit_style_options() {
    return array(
        'auto_random'   => 'Automático (el sistema asigna looks diferentes por foto)',
        'vestido_corto'  => 'Vestido corto (sobre la rodilla)',
        'vestido_largo'  => 'Vestido largo / maxi',
        'conjunto_top'   => 'Conjunto top + falda',
        'mono'           => 'Mono / jumpsuit elegante',
        'conjunto_pantalon' => 'Conjunto pantalón + blusa',
        'body_falda'     => 'Body / bodysuit + falda',
        'vaqueros_top'      => 'Vaqueros + top/blusa casual',
        'pantalon_camisa'   => 'Pantalón + camisa o blusa',
        'falda_casual'      => 'Falda + top casual (look diario)',
        'chaqueta_casual'   => 'Chaqueta/americana + vaqueros',
    );
}

function publicista_cheap_sexy_outfit_pool() {
    // Estructura: [key => [level, category, desc_corta]]
    // Niveles: discreto (tapado), sexy (atrevido barrio), sugerente (glamour max sin sexual)
    // Categorías: usadas para evitar repetición entre imágenes
    return array(
        // ═══ DISCRETO ═══
        'blusa_suelta_pantalon'    => ['discreto','pantalon_top',      'blusa fluida estampada + pantalón ajustado tiro alto, zapatillas blancas'],
        'vestido_midi_manga'       => ['discreto','vestido',           'vestido midi punto fino con mangas tres cuartos, cuello redondo, botines'],
        'america_vaqueros'         => ['discreto','vaqueros_top',      'americana oscura sobre camiseta blanca y vaqueros rectos, look oficina'],
        'jersey_falda_larga'       => ['discreto','jersey_falda',      'jersey de punto fino gris con falda larga plisada, botas planas'],
        'camisa_pantalon_palazzo'  => ['discreto','pantalon_top',      'camisa blanca semientallada + pantalón palazzo fluido negro, sandalias'],
        'mono_largo_elegante'      => ['discreto','mono',              'mono largo negro de corte limpio con cinturón fino, escote barco'],

        // ═══ SEXY ═══
        'minifalda_top_ceñido'     => ['sexy','falda_top',            'minifalda vaquera corta + top ceñido tirantes, cintura al aire, zapatillas'],
        'shorts_ceñidos_camiseta'  => ['sexy','short_top',            'shorts vaqueros cortos ceñidos + camiseta tirantes ajustada, hombros al aire'],
        'leggings_sudadera_corta'  => ['sexy','leggings_top',         'leggings negros ceñidos que marcan silueta + sudadera corta ajustada'],
        'body_vaqueros_rotos'      => ['sexy','body_pantalon',        'body escotado ceñido + vaqueros rotos ajustados, look discoteca económico'],
        'conjunto_chandal_abierto' => ['sexy','conjunto',             'chándal barato chaqueta abierta sin nada debajo + pantalón cintura baja'],
        'falda_tubo_top'           => ['sexy','falda_top',            'falda tubo imitación cuero ceñida + top corto que deja ver cintura'],
        'vestido_punto_ceñido'     => ['sexy','vestido',              'vestido punto ceñido tipo venda, escote redondo, por encima rodilla'],
        'top_halter_falda_cruzada' => ['sexy','falda_top',            'top halter ajustado + falda cruzada corta con abertura lateral'],
        'camiseta_blanca_ceñida_vaqueros' => ['sexy','vaqueros_top',  'camiseta blanca ceñida algodón + vaqueros pitillo, look casual sexy'],
        'vestido_corto_escote_v'   => ['sexy','vestido',              'vestido corto punto ceñido escote V profundo, poliéster mercadillo'],
        'chaleco_vaquero_vestido_blanco' => ['sexy','chaqueta_vestido','chaleco vaquero abierto sobre vestido blanco corto ceñido'],
        'jersey_cuello_alto_falda' => ['sexy','jersey_falda',         'jersey fino cuello alto ajustado + falda corta tubo, botas planas'],

        // ═══ SUGERENTE ═══
        'vestido_lencero_falso'    => ['sugerente','vestido',         'vestido corto imitación satén tirantes finos escote pico, ceñido — parece lencero pero es VESTIDO real'],
        'mono_escotado_ceñido'     => ['sugerente','mono',            'mono corto escote V pronunciado sin mangas, tela elástica muy ceñido'],
        'vestido_escote_espalda'   => ['sugerente','vestido',         'vestido corto escote en la espalda ceñido sin mangas, fiesta low-cost'],
        'top_palabra_honor_falda'  => ['sugerente','falda_top',       'top palabra de honor ajustado sin tirantes + minifalda tubo — hombros y escote al aire'],
        'body_transparente_parcial'=>['sugerente','body_pantalon',    'body manga larga paneles gasa translúcida en mangas, opaco en zonas íntimas + vaqueros'],
        'vestido_transparencia_controlada' => ['sugerente','vestido', 'vestido corto ceñido mangas translúcidas gasa, cuerpo opaco — insinuante'],
        'top_escote_profundo_shorts'=>['sugerente','short_top',       'top escote corazón profundo ceñido + shorts vaqueros muy cortos'],
        'body_palabra_honor_pantalon' => ['sugerente','body_pantalon','body palabra de honor ajustado sin tirantes + pantalón ceñido cintura baja'],
    );
}

/**
 * Pool de outfits eroticos para la variante subida de tono.
 * Estructura: [key => [level, category, desc_corta]]
 * Nivel 'erotico' — lencería, bikinis, transparencias, ropa sexual sugerente.
 */
function publicista_erotic_outfit_pool() {
    return array(
        // ═══ LENCERIA ═══
        'lenceria_negra_encaje'        => ['erotico','lenceria',        'conjunto lencería negra encaje, sujetador push-up balconette + tanga + liguero'],
        'lenceria_roja_saten'          => ['erotico','lenceria',        'conjunto lencería roja satén, sujetador copa media + culotte + medias'],
        'lenceria_blanca_virginal'     => ['erotico','lenceria',        'conjunto lencería blanca gasa, bralette delicado + tanga brasileña'],
        'lenceria_purpura_transparencia'=>['erotico','lenceria',        'conjunto lencería púrpura microfibra traslúcida con apliques florales'],
        'body_lencero_negro'           => ['erotico','body',            'body lencero negro de encaje abierto en el escote, cierre de corchetes delantero'],
        'corset_ligas'                 => ['erotico','corset',          'corset de satén burdeos con ballenas, ligas colgantes y medias de red'],

        // ═══ BIKINIS ═══
        'bikini_hilo_playa'            => ['erotico','bikini',          'bikini brasileño hilo dental, parte superior triángulo mínimo, caderas al aire'],
        'bikini_blanco_ajustado'       => ['erotico','bikini',          'bikini blanco minimalista, top palabra de honor sin tirantes, braguita brasileña'],
        'bikini_metalizado'            => ['erotico','bikini',          'bikini dorado metalizado brillante, top con aro bajo y tanga cintura baja'],

        // ═══ TRANSPARENCIAS ═══
        'body_transparente_gasa'       => ['erotico','transparente',    'body de gasa negra totalmente transparente — pezones y zonas íntimas visibles — espalda descubierta'],
        'vestido_malla_transparente'   => ['erotico','transparente',    'vestido corto de malla de red totalmente transparente, cuerpo desnudo visible — solo malla cubriendo'],
        'top_sheer_humo'               => ['erotico','transparente',    'top de tul transparente color humo, pezones visibles a través, sin nada debajo + minifalda cuerina'],

        // ═══ ROPA SEXUAL / FETICHISTA (sin ser porno explícito) ═══
        'latex_ceñido_negro'           => ['erotico','fetichista',      'conjunto látex negro brillante extremadamente ceñido, body escotado + cinturón metal'],
        'cuero_arnes'                  => ['erotico','fetichista',      'arnés de cuero negro sobre pecho desnudo, pantalón cuero ajustado, tachuelas metálicas'],
        'microbikini_cintas'           => ['erotico','minimo',          'microbikini de solo cintas negras — cubre lo mínimo legal, pezones al borde, tanga hilo'],
        'conjunto_cama_saten'          => ['erotico','cama',            'camisón corto de satén rosa con tirantes finos caídos sobre un hombro, escote profundo'],
        'babydoll_transparente'        => ['erotico','cama',            'babydoll transparente de encaje blanco abierto por delante, tetas visibles, tanga a juego'],
    );
}

/**
 * Fondo sexual/erotico — dormitorio, cama, sofa, luces tenues.
 */
function publicista_erotic_background_pool() {
    return array(
        'dormitorio_luz_tenue'        => 'dormitorio cama grande sábanas revueltas luz cálida lámpara mesilla',
        'cama_saten_rojo'             => 'cama satén rojo almohadas mullidas iluminación tenue íntima',
        'sofa_cuero_negro'            => 'sofá cuero negro ambiente penumbra reflejos brillo luz baja',
        'espejo_suelo'                => 'espejo de cuerpo entero apoyado en suelo dormitorio alfombra mullida',
        'ventana_luz_lunar'           => 'ventanal luz de luna silueta cortinas gasa traslúcida ambiente nocturno',
        'silla_terciopelo'            => 'silla terciopelo burdeos respaldo alto luz dirigida sombras marcadas',
        'alfombra_piel_estudio'       => 'alfombra piel sintética blanca suelo madera oscura luz suave cenital',
    );
}

/**
 * Selecciona N outfits del pool erotico para la variante subida de tono.
 * Similar a publicista_pick_outfits_for_images pero sin filtro de nivel —
 * todo el pool es erotico.
 */
function publicista_pick_erotic_outfits_for_images($count = 4) {
    $pool = publicista_erotic_outfit_pool();
    $colors = array('negro', 'rojo', 'blanco', 'burdeos', 'dorado', 'púrpura');

    $usedCategories = array();
    $outfits = array();

    for ($i = 0; $i < $count; $i++) {
        $filtered = array();
        foreach ($pool as $key => $entry) {
            if (!is_array($entry)) continue;
            if (in_array($entry[1], $usedCategories, true)) continue;
            $filtered[] = array('key' => $key, 'level' => $entry[0], 'category' => $entry[1], 'desc' => $entry[2]);
        }
        if (empty($filtered)) {
            // permitir repetir categoria si ya usamos todas
            foreach ($pool as $key => $entry) {
                if (!is_array($entry)) continue;
                $filtered[] = array('key' => $key, 'level' => $entry[0], 'category' => $entry[1], 'desc' => $entry[2]);
            }
        }
        $pick = $filtered[array_rand($filtered)];
        $usedCategories[] = $pick['category'];
        // No forzar color — dejar que el outfit dicte su propio color
        // truncar en el último espacio antes de 70 chars para no cortar palabras
        $outfitDesc = $pick['desc'];
        if (mb_strlen($outfitDesc) > 70) {
            $outfitDesc = mb_substr($outfitDesc, 0, 70);
            $lastSpace = mb_strrpos($outfitDesc, ' ');
            if ($lastSpace > 20) {
                $outfitDesc = mb_substr($outfitDesc, 0, $lastSpace);
            }
        }
        $outfits[] = $outfitDesc;
    }

    return $outfits;
}

function publicista_pick_random_outfits($count = 4) {
    $pool = publicista_cheap_sexy_outfit_pool();
    $keys = array_keys($pool);
    $count = max(1, min((int)$count, count($keys)));
    $picked = array();
    $available = $keys;
    for ($i = 0; $i < $count; $i++) {
        if (empty($available)) break;
        $idx = array_rand($available);
        $key = $available[$idx];
        $entry = $pool[$key];
        // Compatibilidad: nuevo formato [level, category, desc] o viejo string
        $desc = is_array($entry) ? $entry[2] : $entry;
        $cat  = is_array($entry) ? $entry[1] : '';
        $picked[] = array(
            'key' => $key,
            'category' => $cat,
            'description' => $desc,
        );
        unset($available[$idx]);
        $available = array_values($available);
    }
    return $picked;
}

/**
 * Selecciona N outfits ANTES del prompt, aplicando params del form:
 * - Filtra por nivel (discreto/sexy/sugerente)
 * - Si style != auto_random, genera descripciones coherentes con ese estilo
 * - Si color != auto, lo inyecta en cada descripción
 * - Garantiza categoría de prenda distinta por imagen
 * - Devuelve array de N strings cortos (30-50 chars c/u)
 */
function publicista_pick_outfits_for_images($job, $count = 4) {
    $pp = function_exists('publicista_job_production_params') ? publicista_job_production_params($job) : array();
    $level   = trim((string)($pp['level']   ?? 'sexy'));
    $style   = trim((string)($pp['style']   ?? 'auto_random'));
    $color   = trim((string)($pp['color']   ?? 'auto'));
    $fit     = trim((string)($pp['fit']     ?? 'ajustado'));

    // Mapa rápido de colores a adjetivos cortos
    $colorAdj = array(
        'negro'=>'negro', 'rojo'=>'rojo', 'burdeos'=>'burdeos', 'nude'=>'beige',
        'blanco'=>'blanco', 'azul'=>'azul', 'verde'=>'verde', 'dorado'=>'dorado',
        'fucsia'=>'fucsia', 'plateado'=>'plateado',
    );
    $colorWord = isset($colorAdj[$color]) ? $colorAdj[$color] : '';

    $outfits = array();

    if ($style === 'auto_random') {
        // ── Modo auto: elegir del pool filtrado por nivel, categorías distintas ──
        $pool = publicista_cheap_sexy_outfit_pool();
        $candidates = array();
        foreach ($pool as $key => $entry) {
            if (!is_array($entry)) continue;
            if ($entry[0] === $level || ($level === 'sexy' && $entry[0] === 'sugerente')) {
                $candidates[] = array('key' => $key, 'level' => $entry[0], 'category' => $entry[1], 'desc' => $entry[2]);
            }
        }
        if (empty($candidates)) {
            // fallback: usar todos los del nivel sexy
            foreach ($pool as $key => $entry) {
                if (!is_array($entry)) continue;
                if ($entry[0] === 'sexy') {
                    $candidates[] = array('key' => $key, 'level' => $entry[0], 'category' => $entry[1], 'desc' => $entry[2]);
                }
            }
        }

        $usedCategories = array();
        $remaining = count($candidates);
        for ($i = 0; $i < $count; $i++) {
            $filtered = array_values(array_filter($candidates, function($c) use ($usedCategories) {
                return !in_array($c['category'], $usedCategories, true);
            }));
            if (empty($filtered)) {
                $filtered = $candidates; // si no quedan categorías únicas, repetir
                $usedCategories = array();
            }
            $pick = $filtered[array_rand($filtered)];
            $usedCategories[] = $pick['category'];

            $desc = $pick['desc'];
            if ($colorWord !== '') {
                // Inyectar color si no lo menciona ya
                if (mb_stripos($desc, $colorWord) === false && mb_stripos($desc, 'color') === false) {
                    // Añadir color al final si falta
                    $desc = rtrim($desc, '.') . ', en tonos ' . $colorWord;
                }
            }
            // truncar en el último espacio antes de 60 chars para no cortar palabras
            $outfitDesc = $desc;
            if (mb_strlen($outfitDesc) > 60) {
                $outfitDesc = mb_substr($outfitDesc, 0, 60);
                $lastSpace = mb_strrpos($outfitDesc, ' ');
                if ($lastSpace > 20) {
                    $outfitDesc = mb_substr($outfitDesc, 0, $lastSpace);
                }
            }
            $outfits[] = $outfitDesc;
        }
    } else {
        // ── Modo estilo concreto: generar descripciones coherentes con variaciones ──
        $styleNames = array(
            'vestido_corto'      => 'vestido corto ceñido',
            'vestido_largo'      => 'vestido largo elegante',
            'conjunto_top'       => 'conjunto top y falda',
            'mono'               => 'mono entero',
            'conjunto_pantalon'  => 'conjunto pantalón y blusa',
            'body_falda'         => 'body con falda',
            'vaqueros_top'       => 'vaqueros ajustados + top',
            'pantalon_camisa'    => 'pantalón fluido + camisa',
            'falda_casual'       => 'falda casual + top',
            'chaqueta_casual'    => 'chaqueta + vaqueros',
        );
        $baseDesc = isset($styleNames[$style]) ? $styleNames[$style] : 'look casual de calle';

        $variations = array(
            array('detalle' => 'escote redondo'),
            array('detalle' => 'tirantes finos'),
            array('detalle' => 'manga corta'),
            array('detalle' => 'escote pico'),
            array('detalle' => 'cuello halter'),
            array('detalle' => 'palabra de honor'),
            array('detalle' => 'manga larga'),
            array('detalle' => 'espalda descubierta'),
        );

        for ($i = 0; $i < $count; $i++) {
            $v = $variations[$i % count($variations)];
            $desc = $baseDesc . ', ' . $v['detalle'];
            if ($colorWord !== '') {
                $desc .= ', en ' . $colorWord;
            } else {
                $colorCycle = array('negro', 'rojo', 'azul', 'blanco', 'verde', 'burdeos', 'beige', 'gris');
                $desc .= ', en ' . $colorCycle[$i % count($colorCycle)];
            }
            $outfits[] = $desc;
        }
    }

    return $outfits;
}

/**
 * Selecciona N fondos ANTES del prompt, aplicando params del form.
 * Si setting == random, elige N fondos distintos del pool.
 * Si setting concreto, usa ese fondo con ligeras variaciones.
 * Devuelve array de N strings cortos (~25-35 chars c/u).
 */
function publicista_pick_backgrounds_for_images($job, $count = 4) {
    $pp = function_exists('publicista_job_production_params') ? publicista_job_production_params($job) : array();
    $settingKey = trim((string)($pp['setting'] ?? 'random'));

    $backgrounds = array();

    if ($settingKey === 'random') {
        $pool = publicista_natural_background_pool();
        $keys = array_keys($pool);
        $available = $keys;
        for ($i = 0; $i < $count; $i++) {
            if (empty($available)) {
                $available = $keys; // repetir si no hay más
            }
            $idx = array_rand($available);
            $key = $available[$idx];
            $desc = $pool[$key];
            // Comprimir a ~25-35 chars
            $short = mb_substr($desc, 0, 40);
            // recortar hasta el último espacio para no cortar palabra
            if (mb_strlen($short) >= 35) {
                $lastSpace = mb_strrpos($short, ' ');
                if ($lastSpace > 15) {
                    $short = mb_substr($short, 0, $lastSpace);
                }
            }
            $backgrounds[] = $short;
            unset($available[$idx]);
            $available = array_values($available);
        }
    } else {
        // Setting concreto
        $envDesc = function_exists('publicista_build_setting_description') ? publicista_build_setting_description($job) : array('setting' => 'interior realista');
        $base = mb_substr(trim((string)($envDesc['setting'] ?? 'interior realista')), 0, 35);

        $settingVariants = array(
            'hotel_lujoso'     => array('hotel, cama hecha', 'hotel, ventana luz', 'hotel, sillón', 'hotel, escritorio'),
            'minimalista'      => array('pared neutra, suelo madera', 'pared blanca, planta', 'pared gris, mueble simple', 'pared beige, espejo'),
            'calido'           => array('salón, sofá cojines', 'salón, lámpara pie', 'salón, mesa revistas', 'salón, estantería libros'),
            'urbano_noche'     => array('calle noche, farolas', 'terraza noche, luces', 'calle noche, escaparates', 'calle noche, coches'),
            'dormitorio_real'  => array('dormitorio, cama deshecha', 'dormitorio, ropa silla', 'dormitorio, ventana luz', 'dormitorio, espejo pared'),
            'salon_casa'       => array('salón, sofá mantas', 'salón, mesa centro', 'salón, tele pared', 'salón, planta rincón'),
            'espejo_selfie'    => array('espejo selfie, dormitorio', 'espejo selfie, baño', 'espejo selfie, pasillo', 'espejo selfie, armario'),
        );

        $variants = isset($settingVariants[$settingKey]) ? $settingVariants[$settingKey] : array($base, $base, $base, $base);
        for ($i = 0; $i < $count; $i++) {
            $backgrounds[] = $variants[$i % count($variants)];
        }
    }

    return $backgrounds;
}

function publicista_outfit_level_options() {
    return array(
        'discreto'   => 'Discreto (editorial, apto para webs estrictas)',
        'sexy'       => 'Sexy elegante (glamour editorial, sin sexualizar) — por defecto',
        'sugerente'  => 'Muy sugerente editorial (máximo sin sexualizar)',
    );
}

function publicista_outfit_fit_options() {
    return array(
        'ajustado'  => 'Muy ajustado / bodycon',
        'semi'      => 'Semi-ajustado',
        'fluido'    => 'Fluido / suelto',
    );
}

function publicista_outfit_complement_options() {
    return array(
        'tacones_altos'  => 'Tacones altos',
        'tacones_medios' => 'Tacones medios',
        'sin_zapatos'    => 'Sin zapatos visibles',
        'bolso'          => 'Bolso de mano pequeño',
        'cinturon'       => 'Cinturón',
        'sin_complementos' => 'Sin complementos',
        'zapatillas'        => 'Zapatillas / deportivas',
        'botas_planas'      => 'Botas planas',
    );
}

function publicista_outfit_variety_options() {
    return array(
        'off'       => 'Misma ropa en todas las fotos',
        'mixed'     => 'Variar entre 2-3 looks diferentes (más natural)',
    );
}

function publicista_setting_type_options() {
    return array(
        'auto'          => 'Auto (el modelo elige)',
        'random'        => 'Aleatorio natural (fondos variados automáticamente)',
        'hotel_lujoso'  => 'Interior lujoso (hotel, salón elegante)',
        'minimalista'   => 'Interior minimalista (fondo liso, studio)',
        'calido'        => 'Interior cálido (apartamento acogedor)',
        'urbano_noche'  => 'Exterior urbano de noche',
        'dormitorio_real' => 'Dormitorio real (habitación normal, objetos cotidianos)',
        'salon_casa'    => 'Salón de casa (ambiente doméstico vivido)',
        'espejo_selfie' => 'Selfie de espejo (baño o habitación con espejo)',
    );
}

function publicista_lighting_options() {
    return array(
        'auto'          => 'Auto',
        'natural'       => 'Luz natural suave',
        'studio'        => 'Luz de estudio (beauty light frontal)',
        'calida'        => 'Luz cálida de ambiente (lámparas, velas)',
        'contraluz'     => 'Contraluz dramático',
    );
}

function publicista_framing_options() {
    return array(
        'variado'       => 'Variado (el sistema elige para cada imagen)',
        'entero'        => 'Plano entero (figura completa)',
        'medio'         => 'Plano medio (desde cintura)',
        'tres_cuartos'  => 'Plano tres cuartos',
        'lejano'        => 'Lejano (persona más alejada, se ve entorno)',
        'descentrado'   => 'Descentrado (persona no centrada, foto casual)',
    );
}

function publicista_pose_options() {
    return array(
        'variado'       => 'Variado',
        'pie_estatica'  => 'De pie, estática',
        'pie_dinamica'  => 'De pie, con movimiento sutil',
        'sentada'       => 'Sentada elegante',
        'apoyada'       => 'Apoyada en pared o mueble',
        'casual'        => 'Casual / espontánea (como foto de amigo)',
        'sugerente'     => 'Sugerente / insinuante (sexy sin ser explícita)',
    );
}

function publicista_expression_options() {
    return array(
        'variado'       => 'Variado',
        'sonrisa'       => 'Sonrisa natural',
        'seria'         => 'Expresión seria / segura',
        'sugerente'     => 'Expresión magnética editorial (sin sexualizar)',
    );
}

function publicista_makeup_options() {
    return array(
        'auto'      => 'Acorde a la original',
        'natural'   => 'Natural / minimal',
        'elegante'  => 'Elegante (smokey eye o labios rojos)',
        'intenso'   => 'Maquillaje intenso y llamativo',
    );
}

function publicista_selfie_mode_options() {
    return array(
        'off'   => 'No incluir selfies',
        'mixed' => 'Incluir algunas selfies',
    );
}

function publicista_copy_platform_options() {
    return array(
        'destacamos'  => 'destacamos.net (neutro, sin palabras sexuales)',
        'loquosex'    => 'loquosex.com (sugerente)',
        'mileroticos' => 'mileroticos.com',
        'skokka'      => 'skokka.com',
    );
}

function publicista_copy_angle_options() {
    return array(
        'novedad'      => 'Novedad / "recién llegada"',
        'discrecion'   => 'Discreción / privacidad',
        'trato'        => 'Trato / cercanía / conversación',
        'elegancia'    => 'Elegancia / premium / exclusividad',
        'morbo'        => 'Morbo / sensualidad (solo webs permisivas)',
        'disponibilidad' => 'Disponibilidad / horarios / zona',
    );
}

function publicista_normalize_outfit_params($raw) {
    $out = array();
    $out['color']        = trim((string)($raw['outfit_color'] ?? ($raw['color'] ?? 'auto')));
    $out['style']        = trim((string)($raw['outfit_style'] ?? ($raw['style'] ?? '')));
    $allowedStyles = array_keys(publicista_outfit_style_options());
    if ($out['style'] !== '' && !in_array($out['style'], $allowedStyles, true)) {
        $out['style'] = 'auto_random';
    }
    $out['level']        = trim((string)($raw['outfit_level'] ?? ($raw['level'] ?? 'sexy')));
    $out['fit']          = trim((string)($raw['outfit_fit'] ?? ($raw['fit'] ?? 'ajustado')));
    $out['setting']      = trim((string)($raw['setting_type'] ?? ($raw['setting'] ?? 'auto')));
    $allowedSettings = array_keys(publicista_setting_type_options());
    if (!in_array($out['setting'], $allowedSettings, true)) {
        $out['setting'] = 'auto';
    }
    $out['lighting']     = trim((string)($raw['lighting_type'] ?? ($raw['lighting'] ?? 'auto')));
    $out['framing']      = trim((string)($raw['framing_pref'] ?? ($raw['framing'] ?? 'variado')));
    $out['pose']         = trim((string)($raw['pose_pref'] ?? ($raw['pose'] ?? 'variado')));
    $out['expression']   = trim((string)($raw['expression_pref'] ?? ($raw['expression'] ?? 'variado')));
    $out['makeup']       = trim((string)($raw['makeup_pref'] ?? ($raw['makeup'] ?? 'auto')));
    $out['selfie_mode']  = trim((string)($raw['selfie_mode'] ?? 'off'));
    $out['outfit_variety'] = trim((string)($raw['outfit_variety'] ?? 'off'));
    $rawBrief = trim((string)($raw['operator_brief'] ?? ''));
    // Security: limit 500 chars, strip CAPA-like injection markers
    if (function_exists('mb_substr')) {
        $rawBrief = mb_substr($rawBrief, 0, 500, 'UTF-8');
    } else {
        $rawBrief = substr($rawBrief, 0, 500);
    }
    $rawBrief = preg_replace('/\[CAPA\b/i', '[C4P4-', $rawBrief);
    $out['operator_brief'] = $rawBrief;

    $rawManualDesc = trim((string)($raw['manual_girl_description'] ?? ''));
    if (function_exists('mb_substr')) {
        $rawManualDesc = mb_substr($rawManualDesc, 0, 300, 'UTF-8');
    } else {
        $rawManualDesc = substr($rawManualDesc, 0, 300);
    }
    $rawManualDesc = preg_replace('/\[CAPA\b/i', '[C4P4-', $rawManualDesc);
    $out['manual_girl_description'] = $rawManualDesc;

    $out['copy_brief']   = trim((string)($raw['copy_brief'] ?? ''));

    $allowedSelfieModes = array_keys(publicista_selfie_mode_options());
    if (!in_array($out['selfie_mode'], $allowedSelfieModes, true)) {
        $out['selfie_mode'] = 'off';
    }

    $allowedFraming = array_keys(publicista_framing_options());
    if (!in_array($out['framing'], $allowedFraming, true)) {
        $out['framing'] = 'variado';
    }
    $allowedPose = array_keys(publicista_pose_options());
    if (!in_array($out['pose'], $allowedPose, true)) {
        $out['pose'] = 'variado';
    }
    $allowedExpression = array_keys(publicista_expression_options());
    if (!in_array($out['expression'], $allowedExpression, true)) {
        $out['expression'] = 'variado';
    }
    $allowedMakeup = array_keys(publicista_makeup_options());
    if (!in_array($out['makeup'], $allowedMakeup, true)) {
        $out['makeup'] = 'auto';
    }
    $allowedLighting = array_keys(publicista_lighting_options());
    if (!in_array($out['lighting'], $allowedLighting, true)) {
        $out['lighting'] = 'auto';
    }
    $allowedVariety = array_keys(publicista_outfit_variety_options());
    if (!in_array($out['outfit_variety'], $allowedVariety, true)) {
        $out['outfit_variety'] = 'off';
    }

    $rawComplements = isset($raw['outfit_complements']) && is_array($raw['outfit_complements'])
        ? $raw['outfit_complements']
        : (isset($raw['complements']) && is_array($raw['complements']) ? $raw['complements'] : array());
    $allowed = array_keys(publicista_outfit_complement_options());
    $out['complements'] = array_values(array_filter($rawComplements, function($v) use ($allowed) {
        return in_array(trim((string)$v), $allowed, true);
    }));

    $rawPlatforms = isset($raw['copy_platforms']) && is_array($raw['copy_platforms'])
        ? $raw['copy_platforms']
        : (isset($raw['copy_platforms']) && is_array($raw['copy_platforms']) ? $raw['copy_platforms'] : array());
    $allowedPlatforms = array_keys(publicista_copy_platform_options());
    $out['copy_platforms'] = array_values(array_filter($rawPlatforms, function($v) use ($allowedPlatforms) {
        return in_array(trim((string)$v), $allowedPlatforms, true);
    }));

    $rawAngles = isset($raw['copy_angles']) && is_array($raw['copy_angles'])
        ? $raw['copy_angles']
        : (isset($raw['copy_angles']) && is_array($raw['copy_angles']) ? $raw['copy_angles'] : array());
    $allowedAngles = array_keys(publicista_copy_angle_options());
    $out['copy_angles'] = array_values(array_filter($rawAngles, function($v) use ($allowedAngles) {
        return in_array(trim((string)$v), $allowedAngles, true);
    }));

    // Variante erotica: activada por checkbox en el form
    $out['erotic_mode'] = !empty($raw['erotic_mode']) ? 1 : 0;

    return $out;
}

function publicista_job_production_params($job) {
    $raw = is_array($job['production_params'] ?? null) ? $job['production_params'] : array();
    return publicista_normalize_outfit_params($raw);
}

function publicista_copy_tone_label($tone) {
    $tone = trim((string)$tone);
    $options = publicista_copy_tone_options();
    return isset($options[$tone]) ? $options[$tone] : ($tone !== '' ? $tone : 'Equilibrado');
}
function publicista_restriction_flag_options() {
    return array(
        'keep_hair_color' => 'Mantener color de cabello',
        'keep_body_build' => 'Mantener complexión general',
        'avoid_visible_tattoos' => 'Evitar tatuajes visibles',
        'avoid_glasses' => 'Evitar gafas',
        'avoid_jewelry' => 'Evitar joyería llamativa',
        'neutral_background' => 'Fondo neutro y limpio',
        'discreet_elegant_style' => 'Estilo discreto y elegante',
    );
}

function publicista_normalize_restriction_flags($flags) {
    $flags = is_array($flags) ? $flags : array();
    $allowed = array_keys(publicista_restriction_flag_options());
    $out = array();
    foreach ($flags as $flag) {
        $flag = trim((string)$flag);
        if ($flag !== '' && in_array($flag, $allowed, true) && !in_array($flag, $out, true)) {
            $out[] = $flag;
        }
    }
    return $out;
}

function publicista_restriction_labels($flags) {
    $flags = publicista_normalize_restriction_flags($flags);
    $options = publicista_restriction_flag_options();
    $labels = array();
    foreach ($flags as $flag) {
        if (isset($options[$flag])) $labels[] = $options[$flag];
    }
    return $labels;
}

function avisos_page_url($params = array()) {
    $query = array_merge(array(
        'page' => 'avisos',
    ), $params);

    return 'index.php?' . http_build_query($query);
}

function days_between_dates($from, $to) {
    $a = strtotime((string)$from);
    $b = strtotime((string)$to);
    if (!$a || !$b || $b < $a) return null;
    return round(($b - $a) / 86400, 2);
}

function sort_desc_by_key($items, $key) {
    usort($items, function ($a, $b) use ($key) {
        $av = isset($a[$key]) ? $a[$key] : '';
        $bv = isset($b[$key]) ? $b[$key] : '';
        return strcmp((string)$bv, (string)$av);
    });
    return $items;
}

function clienta_estado_label($estado) {
    if ($estado === 'alta') return 'Alta';
    if ($estado === 'baja') return 'Baja';
    return $estado;
}

function interesada_estado_label($estado) {
    if ($estado === 'nueva') return 'Nueva';
    if ($estado === 'atendida') return 'Atendida';
    if ($estado === 'convertida') return 'Convertida';
    if ($estado === 'descartada') return 'Descartada';
    return $estado;
}

function filter_rows_between_dates($rows, $field, $from, $to) {
    list($fromTs, $toTs) = business_range_bounds($from, $to);

    $out = array();
    foreach ($rows as $row) {
        $raw = isset($row[$field]) ? $row[$field] : '';
        $ts = strtotime(str_replace('T', ' ', $raw));
        if (!$ts) continue;
        if ($fromTs !== null && $ts < $fromTs) continue;
        if ($toTs !== null && $ts > $toTs) continue;
        $out[] = $row;
    }
    return $out;
}

function lead_totals($rows) {
    $count = count($rows);
    $money = 0;
    foreach ($rows as $row) {
        $money += isset($row['precio_lead']) ? (float)$row['precio_lead'] : 0;
    }
    return array('count' => $count, 'money' => $money);
}

function lead_success_message($price) {
    return '¡Lead confirmado! +' . euro($price) . ' al marcador.';
}

function interesada_state_feedback($estado) {
    if ($estado === 'atendida') {
        return array('ok', '¡Bien! Ya está atendida. Vamos a convertirla.', 'motivate');
    }
    if ($estado === 'convertida') {
        return array('ok', '¡Excelente! Interesada convertida en clienta.', 'celebrate');
    }
    if ($estado === 'descartada') {
        return array('ok', 'Interesada marcada como descartada.', '');
    }
    return array('ok', 'Estado actualizado.', '');
}

function format_created_at($value) {
    $raw = trim((string)$value);
    if ($raw === '') return '-';
    $ts = strtotime(str_replace('T', ' ', $raw));
    return $ts ? date('Y-m-d H:i', $ts) : $raw;
}

function format_bytes_human($bytes) {
    $bytes = (float)$bytes;
    if ($bytes <= 0) return '0 B';
    $units = array('B', 'KB', 'MB', 'GB');
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return number_format($bytes, $bytes >= 100 ? 0 : 2, ',', '.') . ' ' . $units[$i];
}

function bot_mode_resolve_host_path($path) {
    $path = trim((string)$path);
    $path = trim($path, " \t\n\r\0\x0B\"'");
    if ($path === '') {
        return '';
    }

    if (preg_match('~(/data(?:/[A-Za-z0-9_./\-]*\.bot_mode_[A-Za-z0-9_\-]+))~', $path, $m)) {
        $path = $m[1];
    } elseif (preg_match('~(/srv/n8n_data(?:/[A-Za-z0-9_./\-]*\.bot_mode_[A-Za-z0-9_\-]+))~', $path, $m)) {
        $path = $m[1];
    }

    if ($path === '/data') {
        return '/srv/n8n_data';
    }

    if (strpos($path, '/data/') === 0) {
        $relative = ltrim(substr($path, strlen('/data/')), '/');
        return '/srv/n8n_data/' . $relative;
    }

    return $path;
}

function lamamibot_clean_generated_assets($generated) {
    if (!is_array($generated)) {
        return array();
    }

    $out = $generated;

    $normalizePaths = function ($paths) {
        $clean = array();

        foreach ((array)$paths as $path) {
            $path = bot_mode_resolve_host_path($path);
            if ($path === '') {
                continue;
            }

            if (!in_array($path, $clean, true)) {
                $clean[] = $path;
            }
        }

        return $clean;
    };

    $out['bot_mode_paths'] = $normalizePaths($out['bot_mode_paths'] ?? array());
    $out['bot_mode_candidates'] = $normalizePaths($out['bot_mode_candidates'] ?? array());

    $primaryPath = '';
    if (!empty($out['bot_mode_paths'][0])) {
        $primaryPath = (string)$out['bot_mode_paths'][0];
    } elseif (!empty($out['bot_mode_candidates'][0])) {
        $primaryPath = (string)$out['bot_mode_candidates'][0];
    }

    $cleanWarnings = array();
    foreach ((array)($out['warnings'] ?? array()) as $warning) {
        $warning = trim((string)$warning);
        if ($warning === '') {
            continue;
        }

        $normalizedWarning = str_replace('\\/', '/', $warning);

        $isLegacyDataWarning =
            $normalizedWarning === 'No se pudo crear la carpeta para el mode file: /data'
            || strpos($normalizedWarning, 'No se pudo crear la carpeta para el mode file: /data') !== false
            || strpos($normalizedWarning, 'No se pudo escribir el mode file: /data/') !== false;

        if ($primaryPath !== '' && strpos($primaryPath, '/srv/n8n_data/') === 0 && $isLegacyDataWarning) {
            continue;
        }

        if (!in_array($warning, $cleanWarnings, true)) {
            $cleanWarnings[] = $warning;
        }
    }

    $out['warnings'] = $cleanWarnings;

    return $out;
}

function bot_mode_file_candidates($bot) {
    $candidates = array();

    $addCandidate = function ($path) use (&$candidates) {
        $path = bot_mode_resolve_host_path($path);
        if ($path === '') {
            return;
        }

        if (!in_array($path, $candidates, true)) {
            $candidates[] = $path;
        }
    };

    $generatedAssets = (isset($bot['generated_assets']) && is_array($bot['generated_assets']))
        ? $bot['generated_assets']
        : array();

    foreach ((array)($generatedAssets['bot_mode_paths'] ?? array()) as $path) {
        $addCandidate($path);
    }
    foreach ((array)($generatedAssets['bot_mode_candidates'] ?? array()) as $path) {
        $addCandidate($path);
    }

    // Intentar sacar la ruta exacta desde generated_assets.texto2 (fuente de verdad)
    $texto2 = (string)($generatedAssets['texto2'] ?? '');
    if ($texto2 !== '') {
        $json = json_decode($texto2, true);

        if (is_array($json) && !empty($json['nodes']) && is_array($json['nodes'])) {
            foreach ($json['nodes'] as $node) {
                $nodeName = (string)($node['name'] ?? '');
                $fileName = trim((string)($node['parameters']['fileName'] ?? ''));
                $command = trim((string)($node['parameters']['command'] ?? ''));

                if (
                    stripos($nodeName, 'Write Bot Mode') !== false ||
                    strpos($fileName, '.bot_mode_') !== false ||
                    strpos($command, '.bot_mode_') !== false
                ) {
                    if ($fileName !== '') {
                        $addCandidate($fileName);
                    }
                    if ($command !== '') {
                        $addCandidate($command);
                    }
                }
            }
        }
    }

    $botName = trim((string)($bot['nombre_bot'] ?? ''));
    if ($botName !== '') {
        $safeBotName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $botName);
        $safeBotName = trim((string)$safeBotName, '_-');
        if ($safeBotName !== '') {
            $safeBotNameLower = strtolower($safeBotName);
            // Fallbacks cuando aún no hay generated_assets o el nombre viene con mayúsculas.
            $addCandidate('/srv/n8n_data/.bot_mode_' . $safeBotNameLower);
            if ($safeBotNameLower !== $safeBotName) {
                $addCandidate('/srv/n8n_data/.bot_mode_' . $safeBotName);
            }
        }
    }

    return $candidates;
}

function bot_mode_file_path($bot) {
    $candidates = bot_mode_file_candidates($bot);
    return $candidates ? $candidates[0] : '';
}

function bot_runtime_mode($bot) {
    $candidates = bot_mode_file_candidates($bot);

    foreach ($candidates as $path) {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            continue;
        }

        $mode = strtolower(trim((string)$raw));
        if ($mode === 'stop') return 'stop';
        if ($mode === 'start') return 'start';
    }

    return 'start';
}

function bot_runtime_write_mode_file($path, $mode, &$error = '') {
    $path = bot_mode_resolve_host_path($path);
    if ($path === '') {
        $error = 'Ruta de mode file vacía.';
        return false;
    }

    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        $error = 'No se pudo crear la carpeta para el mode file: ' . $dir;
        return false;
    }

    $payload = strtolower(trim((string)$mode)) === 'stop' ? 'stop' : 'start';
    clearstatcache(true, $path);

    if (@file_put_contents($path, $payload) !== false) {
        @chmod($path, 0666);
        return true;
    }

    @chmod($path, 0666);
    clearstatcache(true, $path);
    if (@file_put_contents($path, $payload) !== false) {
        @chmod($path, 0666);
        return true;
    }

    if (file_exists($path) && is_writable($dir)) {
        $tmpPath = $dir . '/.bot_mode_tmp_' . uniqid('', true);
        if (@file_put_contents($tmpPath, $payload) !== false) {
            @chmod($tmpPath, 0666);

            if (@rename($tmpPath, $path)) {
                @chmod($path, 0666);
                return true;
            }

            if (@unlink($path) && @rename($tmpPath, $path)) {
                @chmod($path, 0666);
                return true;
            }

            @unlink($tmpPath);
        }
    }

    $parts = array('No se pudo escribir el mode file: ' . $path);
    if (file_exists($path)) {
        $parts[] = 'Existe pero no se pudo sobrescribir';
    }
    if (!is_writable($dir)) {
        $parts[] = 'La carpeta no es escribible: ' . $dir;
    }
    $error = implode(' · ', $parts);
    return false;
}

function bot_mode_prepare_files($bot, $defaultMode = 'start') {
    $candidates = bot_mode_file_candidates($bot);
    $ready = array();
    $errors = array();

    foreach ($candidates as $path) {
        $path = bot_mode_resolve_host_path($path);
        if ($path === '') continue;

        $error = '';
        if (!file_exists($path)) {
            if (!bot_runtime_write_mode_file($path, $defaultMode, $error)) {
                if ($error !== '') {
                    $errors[] = $error;
                }
                continue;
            }
        }

        @chmod($path, 0666);
        $ready[] = $path;
    }

    return array(!empty($ready), $ready, $errors, $candidates);
}

function bot_runtime_set_mode($bot, $mode) {
    $mode = strtolower(trim((string)$mode)) === 'stop' ? 'stop' : 'start';
    $candidates = bot_mode_file_candidates($bot);
    $written = array();
    $errors = array();

    foreach ($candidates as $path) {
        $path = bot_mode_resolve_host_path($path);
        if ($path === '') continue;

        $error = '';
        if (!bot_runtime_write_mode_file($path, $mode, $error)) {
            if ($error !== '') {
                $errors[] = $error;
            }
            continue;
        }

        @chmod($path, 0666);
        $written[] = $path;
    }

    return array(!empty($written), $written, $errors);
}

function bot_runtime_is_on($bot) {
    return bot_runtime_mode($bot) !== 'stop';
}

function bot_runtime_label($bot) {
    return bot_runtime_is_on($bot) ? 'Encendido' : 'Apagado';
}

function bot_runtime_dot_html($bot) {
    $isOn = bot_runtime_is_on($bot);
    $class = $isOn ? 'bot-status-on' : 'bot-status-off';
    $label = $isOn ? 'Encendido' : 'Apagado';

    return '<span class="bot-status-wrap"><span class="bot-status-dot ' . e($class) . '"></span><span class="bot-status-text">' . e($label) . '</span></span>';
}


function bot_girls_panel_url($bot) {
    $generatedAssets = (isset($bot['generated_assets']) && is_array($bot['generated_assets']))
        ? $bot['generated_assets']
        : array();

    $directUrl = trim((string)($generatedAssets['girls_panel_url'] ?? ''));
    if ($directUrl !== '') {
        return $directUrl;
    }

    return trim((string)($generatedAssets['texto4'] ?? ''));
}

function dashboard_external_bot_config() {
    $modePath = '/srv/n8n_data/.bot_mode';
    return array(
        'id' => 'dashboard_external_bot',
        'nombre_bot' => 'Casawasap externo',
        'bot_mode_path' => $modePath,
        'girls_panel_url' => 'https://casawasap.com/girlsconf/',
    );
}

function dashboard_external_bot_virtual() {
    $cfg = dashboard_external_bot_config();

    return array(
        'id' => (string)($cfg['id'] ?? 'dashboard_external_bot'),
        'nombre_bot' => (string)($cfg['nombre_bot'] ?? 'Bot externo'),
        'generated_assets' => array(
            'bot_mode_paths' => array((string)($cfg['bot_mode_path'] ?? '')),
            'girls_panel_url' => (string)($cfg['girls_panel_url'] ?? ''),
        ),
    );
}


function bot_parse_linked_ref($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return array('', '');
    }

    $parts = explode('::', $raw, 2);
    if (count($parts) !== 2) {
        return array('', '');
    }

    $type = trim((string)$parts[0]);
    $id = trim((string)$parts[1]);
    if (!in_array($type, array('lamami_clienta', 'casawasap_cliente'), true)) {
        return array('', '');
    }

    return array($type, $id);
}

function bot_build_linked_ref($type, $id) {
    $type = trim((string)$type);
    $id = trim((string)$id);
    if ($type === '' || $id === '') {
        return '';
    }
    return $type . '::' . $id;
}

function bot_linked_type($bot) {
    $type = trim((string)($bot['linked_type'] ?? ''));
    if ($type !== '') {
        return $type;
    }

    $legacyClientaId = trim((string)($bot['cliente_id'] ?? ''));
    if ($legacyClientaId !== '') {
        return 'lamami_clienta';
    }

    return '';
}

function bot_linked_id($bot) {
    $id = trim((string)($bot['linked_id'] ?? ''));
    if ($id !== '') {
        return $id;
    }

    return trim((string)($bot['cliente_id'] ?? ''));
}

function bot_linked_source_label($bot) {
    $type = bot_linked_type($bot);
    if ($type === 'lamami_clienta') {
        return 'LaMami';
    }
    if ($type === 'casawasap_cliente') {
        return 'CasaWasap';
    }
    return 'Sin vincular';
}

function casawasap_bot_profile_from_contact($row, $bot = array()) {
    $row = is_array($row) ? $row : array();
    $bot = is_array($bot) ? $bot : array();

    $businessName = trim((string)($row['bot_business_name'] ?? ''));
    if ($businessName === '') {
        $businessName = trim((string)($row['nombre'] ?? ''));
    }
    if ($businessName === '') {
        $businessName = trim((string)($bot['nombre_bot'] ?? ''));
    }
    if ($businessName === '') {
        $businessName = 'Cliente CasaWasap';
    }

    $contexto = trim((string)($row['bot_contexto'] ?? ''));
    if ($contexto === '') {
        $contexto = trim((string)($row['notas'] ?? ''));
    }

    $servicios = trim((string)($row['bot_servicios'] ?? ''));
    $tarifas = trim((string)($row['bot_tarifas'] ?? ''));
    $zona = trim((string)($row['bot_zona'] ?? ''));
    $ubicacionMaps = trim((string)($row['bot_ubicacion_maps'] ?? ''));
    $horario = trim((string)($row['bot_horario'] ?? ''));
    $objetivo = trim((string)($row['bot_objetivo'] ?? ''));
    $modoPreferido = trim((string)($row['bot_modo_preferido'] ?? ($row['modo'] ?? '')));
    if ($modoPreferido !== 'personal') {
        $modoPreferido = 'multiple';
    }

    return array(
        'business_name' => $businessName,
        'contexto' => $contexto,
        'servicios' => $servicios,
        'tarifas' => $tarifas,
        'zona' => $zona,
        'ubicacion_maps' => $ubicacionMaps,
        'horario' => $horario,
        'objetivo' => $objetivo,
        'modo_preferido' => $modoPreferido,
    );
}

function bot_resolve_linked_row($bot) {
    $linkedId = bot_linked_id($bot);
    if ($linkedId === '') {
        return null;
    }

    $type = bot_linked_type($bot);
    if ($type === 'lamami_clienta') {
        return storage_find_by_id('clientes.json', $linkedId);
    }
    if ($type === 'casawasap_cliente') {
        return storage_find_by_id('casawasap_contactos.json', $linkedId);
    }

    return null;
}

function bot_linked_display_name($bot) {
    $row = bot_resolve_linked_row($bot);
    if (!is_array($row)) {
        return 'Sin vincular';
    }

    $type = bot_linked_type($bot);
    if ($type === 'casawasap_cliente') {
        $profile = casawasap_bot_profile_from_contact($row, $bot);
        return $profile['business_name'] ?? ($row['nombre'] ?? 'Cliente CasaWasap');
    }

    return (string)($row['nombre'] ?? 'Clienta LaMami');
}

function bot_resolve_profile($bot) {
    $type = bot_linked_type($bot);
    $row = bot_resolve_linked_row($bot);

    if ($type === 'casawasap_cliente') {
        $profile = casawasap_bot_profile_from_contact($row, $bot);
        return array(
            'source_label' => 'CasaWasap',
            'display_name' => $profile['business_name'],
            'contexto' => $profile['contexto'],
            'servicios' => $profile['servicios'],
            'tarifas' => $profile['tarifas'],
            'zona' => $profile['zona'],
            'ubicacion_maps' => $profile['ubicacion_maps'],
            'horario' => $profile['horario'],
            'objetivo' => $profile['objetivo'],
            'modo_preferido' => $profile['modo_preferido'],
            'linked_id' => bot_linked_id($bot),
        );
    }

    if (is_array($row)) {
        return array(
            'source_label' => 'LaMami',
            'display_name' => trim((string)($row['nombre'] ?? '')),
            'contexto' => trim((string)($row['notas'] ?? '')),
            'servicios' => trim((string)($row['servicios'] ?? '')),
            'tarifas' => trim((string)($row['tarifas'] ?? '')),
            'zona' => trim((string)($row['zona'] ?? '')),
            'ubicacion_maps' => trim((string)($row['ubicacion_maps'] ?? '')),
            'horario' => '',
            'objetivo' => '',
            'modo_preferido' => trim((string)($bot['bot_mode'] ?? 'multiple')),
            'linked_id' => bot_linked_id($bot),
        );
    }

    return array(
        'source_label' => bot_linked_source_label($bot),
        'display_name' => '',
        'contexto' => '',
        'servicios' => trim((string)($bot['servicios'] ?? '')),
        'tarifas' => trim((string)($bot['tarifas'] ?? '')),
        'zona' => trim((string)($bot['zona'] ?? '')),
        'ubicacion_maps' => trim((string)($bot['ubicacion_maps'] ?? '')),
        'horario' => '',
        'objetivo' => '',
        'modo_preferido' => trim((string)($bot['bot_mode'] ?? 'multiple')),
        'linked_id' => bot_linked_id($bot),
    );
}

function bot_suggest_name_from_profile($profile) {
    $label = trim((string)($profile['display_name'] ?? ''));
    if ($label === '') {
        return '';
    }

    $slug = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $label);
    $slug = strtolower(trim((string)$slug, '_-'));
    return $slug;
}

function get_casawasap_cliente_current_bot($clienteId) {
    $clienteId = trim((string)$clienteId);
    if ($clienteId === '') {
        return null;
    }

    foreach (storage_read('bots.json') as $bot) {
        if (bot_linked_type($bot) !== 'casawasap_cliente') {
            continue;
        }
        if (bot_linked_id($bot) === $clienteId) {
            return $bot;
        }
    }

    return null;
}

function current_request_url() {
    return (string)($_SERVER['REQUEST_URI'] ?? 'index.php?page=dashboard');
}

function whatsapp_url($phone) {
    $phone = trim((string)$phone);
    if ($phone === '') return '';

    $hasPlus = strpos($phone, '+') === 0;
    $digits = preg_replace('/\D+/', '', $phone);

    if ($digits === '') return '';

    // Si viene un móvil español de 9 cifras, asumimos +34
    if (!$hasPlus && strlen($digits) === 9) {
        $digits = '34' . $digits;
    }

    return 'https://wa.me/' . $digits;
}

function get_casawasap_contactos() {
    return storage_read('casawasap_contactos.json');
}

function get_casawasap_clientes() {
    $rows = storage_read('casawasap_contactos.json');
    $out = array();
    foreach ($rows as $row) {
        if (isset($row['estado']) && $row['estado'] === 'cliente') {
            $out[] = $row;
        }
    }
    return $out;
}

function get_casawasap_contactos_index() {
    $rows = storage_read('casawasap_contactos.json');
    $idx = array();
    foreach ($rows as $row) {
        if (isset($row['id'])) {
            $idx[$row['id']] = $row;
        }
    }
    return $idx;
}

function get_casawasap_pagos_for_cliente($clienteId) {
    $rows = storage_read('casawasap_pagos.json');
    $out = array();
    foreach ($rows as $row) {
        if (isset($row['cliente_id']) && $row['cliente_id'] === $clienteId) {
            $out[] = $row;
        }
    }
    return $out;
}

function casawasap_pago_totals($rows) {
    $count = count($rows);
    $money = 0;
    foreach ($rows as $row) {
        $money += isset($row['importe']) ? (float)$row['importe'] : 0;
    }
    return array('count' => $count, 'money' => $money);
}

function get_jostal_clientas() {
    return storage_read('jostal_clientas.json');
}

function get_jostal_clientas_index() {
    $rows = storage_read('jostal_clientas.json');
    $idx = array();
    foreach ($rows as $row) {
        if (isset($row['id'])) $idx[$row['id']] = $row;
    }
    return $idx;
}

function get_jostal_leads_for_clienta($clientaId) {
    $rows = storage_read('jostal_leads.json');
    $out = array();
    foreach ($rows as $row) {
        if (isset($row['clienta_id']) && $row['clienta_id'] === $clientaId) {
            $out[] = $row;
        }
    }
    return $out;
}

function money_totals_from_importe($rows, $field = 'importe') {
    $count = count($rows);
    $money = 0;
    foreach ($rows as $row) {
        $money += isset($row[$field]) ? (float)$row[$field] : 0;
    }
    return array('count' => $count, 'money' => $money);
}

function get_dashboard_activity_months() {
    $months = array();

    $push = function ($value) use (&$months) {
        $raw = trim((string)$value);
        if ($raw === '') return;
        $ts = strtotime(str_replace('T', ' ', $raw));
        if (!$ts) return;
        $key = business_month_key_from_ts($ts);
        if ($key !== '') {
            $months[$key] = true;
        }
    };

    foreach (storage_read('clientes.json') as $row) $push($row['fecha_alta'] ?? '');
    foreach (storage_read('leads.json') as $row) $push($row['fecha_hora'] ?? '');
    foreach (storage_read('casawasap_pagos.json') as $row) $push($row['fecha_hora'] ?? '');
    foreach (storage_read('jostal_leads.json') as $row) $push($row['created_at'] ?? '');
    foreach (storage_read('jostal_ventas.json') as $row) $push($row['created_at'] ?? '');
    foreach (storage_read('gastos.json') as $row) $push($row['created_at'] ?? '');

    $keys = array_keys($months);
    sort($keys);
    return $keys;
}

function clienta_has_linked_bot($clientaId) {
    return get_clienta_current_bot($clientaId) ? true : false;
}

function get_casawasap_active_clientes() {
    $rows = storage_read('casawasap_contactos.json');
    $out = array();
    foreach ($rows as $row) {
        if (($row['estado'] ?? '') === 'cliente') {
            $out[] = $row;
        }
    }
    return $out;
}

function jostal_periodos_estancia($clienta) {
    return isset($clienta['periodos_estancia']) && is_array($clienta['periodos_estancia'])
        ? $clienta['periodos_estancia']
        : array();
}

function jostal_weekday_options() {
    return array(
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    );
}

function jostal_weekday_label($weekday) {
    $weekday = (int)$weekday;
    $options = jostal_weekday_options();
    return isset($options[$weekday]) ? $options[$weekday] : '';
}

function jostal_weekday_from_date($date) {
    $ts = strtotime(trim((string)$date));
    if (!$ts) return 0;
    return (int)date('N', $ts);
}

function jostal_periodo_actual($clienta) {
    $periodos = jostal_periodos_estancia($clienta);
    if (empty($periodos)) return array();
    return is_array($periodos[count($periodos) - 1]) ? $periodos[count($periodos) - 1] : array();
}

function jostal_alquiler_due_weekday($clienta) {
    $stored = (int)($clienta['rent_due_weekday'] ?? 0);
    if ($stored >= 1 && $stored <= 7) {
        return $stored;
    }

    $periodo = jostal_periodo_actual($clienta);
    return jostal_weekday_from_date((string)($periodo['entrada'] ?? ''));
}

function jostal_alquiler_payment_info($clienta, $nowTs = null) {
    $clienta = is_array($clienta) ? $clienta : array();
    $nowTs = $nowTs ?: time();

    if (($clienta['modo'] ?? '') !== 'alquiler') {
        return array('enabled' => false, 'reason' => 'not_alquiler');
    }
    if (!jostal_clienta_en_casa($clienta)) {
        return array('enabled' => false, 'reason' => 'not_in_house');
    }

    $periodo = jostal_periodo_actual($clienta);
    $entrada = trim((string)($periodo['entrada'] ?? ''));
    $entradaTs = strtotime($entrada !== '' ? ($entrada . ' 00:00:00') : '');
    if (!$entradaTs) {
        return array('enabled' => false, 'reason' => 'missing_entry');
    }

    $entryWeekday = jostal_weekday_from_date($entrada);
    $dueWeekday = jostal_alquiler_due_weekday($clienta);
    if ($entryWeekday < 1 || $dueWeekday < 1) {
        return array('enabled' => false, 'reason' => 'missing_weekday');
    }

    $daysUntilFirstDue = ($dueWeekday - $entryWeekday + 7) % 7;
    if ($daysUntilFirstDue === 0) {
        $daysUntilFirstDue = 7;
    }

    $firstDueTs = strtotime('+' . $daysUntilFirstDue . ' day', $entradaTs);
    $todayStartTs = strtotime(date('Y-m-d', $nowTs) . ' 00:00:00');
    $nextDueTs = $firstDueTs;

    while ($nextDueTs < $todayStartTs) {
        $nextDueTs = strtotime('+7 day', $nextDueTs);
    }

    $daysLeft = (int)floor(($nextDueTs - $todayStartTs) / 86400);
    if ($daysLeft < 0) {
        $daysLeft = 0;
    }

    return array(
        'enabled' => true,
        'entry_date' => $entrada,
        'entry_ts' => $entradaTs,
        'entry_weekday' => $entryWeekday,
        'entry_weekday_label' => jostal_weekday_label($entryWeekday),
        'due_weekday' => $dueWeekday,
        'due_weekday_label' => jostal_weekday_label($dueWeekday),
        'first_due_date' => date('Y-m-d', $firstDueTs),
        'next_due_date' => date('Y-m-d', $nextDueTs),
        'next_due_ts' => $nextDueTs,
        'days_left' => $daysLeft,
        'due_today' => ($daysLeft === 0),
        'due_tomorrow' => ($daysLeft === 1),
    );
}

function jostal_clienta_en_casa($clienta) {
    $periodos = jostal_periodos_estancia($clienta);
    if (empty($periodos)) return false;
    $ultimo = $periodos[count($periodos) - 1];
    return empty($ultimo['salida']);
}

/**
 * Precio semanal de alquiler de una clienta.
 *
 * Resolución por prioridad:
 *   1. Campo `precio_semanal` de la propia clienta.
 *   2. `precio_semanal` del contrato asociado (si existe y > 0).
 *   3. Mapa legacy hardcodeado (clientas históricas que aún no tienen el campo).
 *
 * Devuelve float > 0 o null si no hay precio definido.
 */
function jostal_precio_semanal($clienta) {
    $clienta = is_array($clienta) ? $clienta : array();

    $legacy = array(
        'jcli0013'      => 170,
        'jcli_2bd0670c' => 130,
        'jcli_0428b6e4' => 150,
        'jcli_1e594eda' => 150,
    );

    $propio = (float)($clienta['precio_semanal'] ?? 0);
    if ($propio > 0) return $propio;

    $contrato = contrato_find_by_clienta((string)($clienta['id'] ?? ''));
    if (is_array($contrato)) {
        $delContrato = (float)($contrato['precio_semanal'] ?? 0);
        if ($delContrato > 0) return $delContrato;
    }

    $id = (string)($clienta['id'] ?? '');
    if ($id !== '' && isset($legacy[$id])) return (float)$legacy[$id];

    return null;
}

/**
 * Cambio de precio histórico hardcodeado (para casos puntuales aún no pasados a la ficha).
 * Estructura: clienta_id => ['anterior' => importe, 'desde' => 'YYYY-MM-DD'].
 */
function jostal_precio_historico_legacy($clienta) {
    $id = (string)($clienta['id'] ?? '');
    $map = array(
        'jcli_0428b6e4' => array('anterior' => 130, 'desde' => '2026-08-01'),
    );
    return isset($map[$id]) ? $map[$id] : null;
}

/**
 * Precio semanal aplicable a una fecha concreta (soporta cambio de precio histórico).
 * Si la clienta tiene `precio_semanal_anterior` + `precio_semanal_desde`, se usa para
 * fechas anteriores al cambio. Si no, se usa el mapa legacy hardcodeado.
 */
function jostal_precio_por_fecha($clienta, $fecha) {
    $clienta = is_array($clienta) ? $clienta : array();

    $desde = trim((string)($clienta['precio_semanal_desde'] ?? ''));
    $anterior = (float)($clienta['precio_semanal_anterior'] ?? 0);

    if ($desde === '' || $anterior <= 0) {
        $leg = jostal_precio_historico_legacy($clienta);
        if ($leg) {
            $desde = (string)$leg['desde'];
            $anterior = (float)$leg['anterior'];
        }
    }

    if ($desde !== '' && $anterior > 0) {
        $fechaTs = strtotime($fecha . ' 00:00:00');
        $desdeTs = strtotime($desde . ' 00:00:00');
        if ($fechaTs !== false && $desdeTs !== false && $fechaTs < $desdeTs) {
            return $anterior;
        }
    }

    return jostal_precio_semanal($clienta);
}

/**
 * Pagos de alquiler no registrados como lead (p. ej. reservas por bizum guardadas como venta).
 * Se inyectan como pagos de alquiler para el cálculo de deuda.
 * Clave: clienta_id => lista de ['date' => 'Y-m-d', 'amount' => float, 'desc' => '...'].
 */
function jostal_pagos_extra() {
    return array(
        'jcli_0428b6e4' => array(
            array('date' => '2026-05-11', 'amount' => 30, 'desc' => 'Reserva bizum (venta)'),
        ),
    );
}

/**
 * "Borrón y cuenta nueva" hardcodeado para clientas especiales.
 *
 * Se perdona toda la deuda anterior a `desde` (se reinicia el arrastre y las
 * compensaciones a partir de esa fecha). `ignorar_actual` = true ignora los pagos
 * de la semana en curso (se consideran dinero absorbido por la deuda antigua).
 *
 * Devuelve null si la clienta no tiene perdón especial.
 */
function jostal_perdon_legacy($clienta) {
    $id = (string)($clienta['id'] ?? '');
    $map = array(
        'jcli_0428b6e4' => array('desde' => '2026-08-10'),                         // nisy
        'jcli_2bd0670c' => array('desde' => '2026-08-10', 'ignorar_actual' => true), // Tatiana
    );
    return isset($map[$id]) ? $map[$id] : null;
}

/** Valida fechas opcionales con formato Y-m-d estricto y orden inclusivo. */
function jostal_validar_rango_fechas($desde, $hasta) {
    $desde = trim((string)$desde);
    $hasta = trim((string)$hasta);
    foreach (array('desde' => $desde, 'hasta' => $hasta) as $campo => $valor) {
        if ($valor === '') continue;
        $dt = DateTime::createFromFormat('!Y-m-d', $valor);
        $errors = DateTime::getLastErrors();
        if (!$dt || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $dt->format('Y-m-d') !== $valor) {
            return array('ok' => false, 'error' => 'La fecha ' . $campo . ' debe tener formato YYYY-MM-DD y ser válida.');
        }
    }
    if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
        return array('ok' => false, 'error' => 'La fecha desde no puede ser posterior a la fecha hasta.');
    }
    return array('ok' => true, 'desde' => $desde, 'hasta' => $hasta, 'error' => '');
}

/** Devuelve vacío si un lead puede convertirse permanentemente en alquiler. */
function jostal_validar_compensacion_permanente($lead, $clientaId) {
    if (!is_array($lead)) return 'Lead no encontrado.';
    $clientaId = trim((string)$clientaId);
    if ($clientaId === '' || (string)($lead['clienta_id'] ?? '') !== $clientaId) {
        return 'El pago no pertenece a la clienta mostrada.';
    }
    if ((float)($lead['precio'] ?? 0) <= 0) return 'El pago debe tener un importe positivo.';
    $clasif = jostal_concepto_tipo_efectivo($lead);
    if (($clasif['tipo'] ?? '') !== 'no_alquiler') return 'El pago ya no está clasificado como no alquiler.';
    return '';
}

/** Relee, valida y convierte un pago dentro de una única sección crítica. */
function jostal_compensar_lead_permanente($leadId, $clientaId) {
    $leadId = trim((string)$leadId);
    $clientaId = trim((string)$clientaId);
    if ($leadId === '') return array('ok' => false, 'error' => 'Lead no encontrado.');

    return storage_mutate_row_atomic('jostal_leads.json', $leadId, function ($lead) use ($clientaId) {
        $validationError = jostal_validar_compensacion_permanente($lead, $clientaId);
        if ($validationError !== '') return array('ok' => false, 'error' => $validationError);

        $original = trim((string)($lead['observacion'] ?? ''));
        $sufijo = 'compensación posterior alquiler';
        if (strpos($original, $sufijo) === false) {
            $lead['observacion'] = $original !== '' ? $original . ' + ' . $sufijo : $sufijo;
        }
        $lead['concepto_tipo'] = 'alquiler';
        $lead['concepto_fuente'] = 'manual';
        $lead['concepto_confirmado_at'] = now_datetime();
        $lead['updated_at'] = now_datetime();
        return array('ok' => true, 'row' => $lead);
    });
}

/**
 * Normaliza un texto para comparación: minúsculas, sin acentos, espacios colapsados.
 */
function jostal_normalizar_texto($s) {
    $s = (string)$s;
    if (function_exists('mb_strtolower')) {
        $s = mb_strtolower($s, 'UTF-8');
    } else {
        $s = strtolower($s);
    }
    $map = array(
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    );
    $s = strtr($s, $map);
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

/**
 * Clasifica el concepto de un lead Jostal respecto al alquiler.
 *
 * @param string $observacion  Texto del concepto.
 * @param float  $amount       Importe del lead (para la heurística de "vacío alto").
 * @return array{string, string}  ['tipo' => 'alquiler'|'no_alquiler'|'dudoso', 'razon' => '...']
 */
function jostal_clasificar_concepto($observacion, $amount = 0) {
    $amount = (float)$amount;
    $raw = trim((string)$observacion);

    if ($raw === '') {
        if ($amount > 50) {
            return array('tipo' => 'dudoso', 'razon' => 'Pago alto sin concepto (' . euro($amount) . ')');
        }
        return array('tipo' => 'no_alquiler', 'razon' => 'Sin concepto');
    }

    $d = jostal_normalizar_texto($raw);
    if ($d === '') {
        return $amount > 50
            ? array('tipo' => 'dudoso', 'razon' => 'Sin concepto legible, importe alto')
            : array('tipo' => 'no_alquiler', 'razon' => 'Sin concepto');
    }

    // 1. Stems directos de "alquiler" (y variantes con faltas graves).
    $stems = array('alquil', 'alqil', 'alqul', 'alqler', 'alkil', 'akquil', 'alquile', 'alkile', 'alquler');
    foreach ($stems as $stem) {
        if (mb_strpos($d, $stem) !== false) {
            return array('tipo' => 'alquiler', 'razon' => 'Contiene "' . $stem . '"');
        }
    }

    // 2. Similitud palabra a palabra contra "alquiler" (tolera aliler, alkiler, alquller...).
    $words = preg_split('/[\s\-\.,\/:;]+/u', $d, -1, PREG_SPLIT_NO_EMPTY);
    $best = 0;
    $bestWord = '';
    foreach ((array)$words as $w) {
        if (mb_strlen($w) < 3) continue;
        $dist = levenshtein($w, 'alquiler');
        similar_text($w, 'alquiler', $pct);
        if ($dist <= 2 || $pct >= 80) {
            return array('tipo' => 'alquiler', 'razon' => 'Parecido a "alquiler" ("' . $w . '")');
        }
        if ($pct > $best) {
            $best = $pct;
            $bestWord = $w;
        }
    }

    // 3. Conceptos claramente NO alquiler.
    $noAlquiler = array('fianza', 'taxi', 'bizum', 'regalo', 'cliente', 'servicio', 'condonad', 'ajuste', 'propina', 'reserva', 'despensa');
    foreach ($noAlquiler as $kw) {
        if (mb_strpos($d, $kw) !== false) {
            return array('tipo' => 'no_alquiler', 'razon' => 'Contiene "' . $kw . '"');
        }
    }

    // 4. Zona gris: parecido pero no concluyente.
    if ($best >= 60) {
        return array('tipo' => 'dudoso', 'razon' => 'Parecido a "alquiler" pero no claro ("' . $bestWord . '")');
    }

    // 5. Texto corto no reconocido con importe alto → pedir confirmación.
    if (mb_strlen($d) <= 12 && $amount > 50) {
        return array('tipo' => 'dudoso', 'razon' => 'Concepto corto no reconocido con importe alto');
    }

    return array('tipo' => 'no_alquiler', 'razon' => 'Concepto no relacionado con alquiler');
}

/**
 * Tipo de concepto efectivo de un lead: usa la clasificación persistida en BD
 * (manual o auto) si existe; si no, clasifica automáticamente.
 */
function jostal_concepto_tipo_efectivo($lead) {
    $lead = is_array($lead) ? $lead : array();
    $persistido = trim((string)($lead['concepto_tipo'] ?? ''));
    if ($persistido === 'alquiler' || $persistido === 'no_alquiler') {
        $fuente = (string)($lead['concepto_fuente'] ?? '');
        return array(
            'tipo' => $persistido,
            'razon' => $fuente === 'manual' ? 'Clasificación manual' : 'Clasificación guardada',
            'persistido' => true,
        );
    }
    return jostal_clasificar_concepto((string)($lead['observacion'] ?? ''), (float)($lead['precio'] ?? 0));
}

/**
 * Localiza la línea WAHA "jostal dulce" (desde la que se envían los informes por WhatsApp).
 * Devuelve el array de línea o null si no está configurada.
 */
function jostal_dulce_line() {
    $lines = comercial_list_lines();
    foreach ((array)$lines as $line) {
        $id = (string)($line['id'] ?? '');
        $nombre = jostal_normalizar_texto((string)($line['nombre'] ?? ''));
        if ($id === 'tf_de558a13' || mb_strpos($nombre, 'dulce') !== false) {
            return $line;
        }
    }
    return null;
}

/**
 * Calcula la deuda de alquiler de una clienta en casa.
 *
 * @param array  $clienta    Ficha de la clienta.
 * @param array  $leads      Leads de esa clienta (o todos; se filtran por clienta_id).
 * @param array  $overrides  Lista de lead_id a tratar como alquiler temporalmente.
 * @param string $desde      Fecha de inicio ('' = desde la entrada). Si se indica, la
 *                           primera semana del rango es la "semana 1" y no se arrastra
 *                           deuda ni pagos anteriores.
 * @param string $hasta      Fecha fin ('' = hasta hoy, incluye semana en curso).
 * @param string $asOf       Fecha de cálculo controlada (Y-m-d); vacío usa hoy.
 * @return array  Estructura con weeks, totales y dudosos (o ['error' => ...]).
 */
function jostal_compute_deuda($clienta, $leads = null, $overrides = array(), $desde = '', $hasta = '', $asOf = '') {
    $clienta = is_array($clienta) ? $clienta : array();
    $cid = (string)($clienta['id'] ?? '');
    $overrides = is_array($overrides) ? array_values(array_unique(array_map('strval', $overrides))) : array();

    $precio = jostal_precio_semanal($clienta);
    if ($precio === null || $precio <= 0) {
        return array('error' => 'sin_precio');
    }

    $asOf = trim((string)$asOf);
    $asOfDate = $asOf !== '' ? $asOf : date('Y-m-d');
    $asOfDt = DateTime::createFromFormat('!Y-m-d', $asOfDate);
    if (!$asOfDt || $asOfDt->format('Y-m-d') !== $asOfDate) {
        return array('error' => 'fecha_control_invalida');
    }
    $today_ts = $asOfDt->getTimestamp();

    $pi = jostal_alquiler_payment_info($clienta, $today_ts);
    if (empty($pi['enabled'])) {
        return array('error' => 'sin_alquiler_activo');
    }

    if ($leads === null) {
        $leads = get_jostal_leads_for_clienta($cid);
    } else {
        $leads = array_values(array_filter((array)$leads, function ($l) use ($cid) {
            return is_array($l) && (string)($l['clienta_id'] ?? '') === $cid;
        }));
    }

    $entry_date = (string)$pi['entry_date'];
    $first_due_date = (string)$pi['first_due_date'];
    $first_due_ts = strtotime($first_due_date . ' 00:00:00');
    if (!$first_due_ts || $first_due_ts > $today_ts) {
        return array('error' => 'sin_vencimientos', 'entry_date' => $entry_date);
    }

    // ── Rango de fechas ──
    // "desde" reinicia el cálculo: la primera semana del rango es la "semana 1" y no se
    // arrastra deuda ni pagos anteriores. "hasta" corta el informe (semanas y pagos posteriores).
    $desde = trim((string)$desde);
    $hasta = trim((string)$hasta);
    $rango = jostal_validar_rango_fechas($desde, $hasta);
    if (empty($rango['ok'])) {
        return array('error' => 'rango_fechas_invalido', 'error_message' => $rango['error']);
    }

    $start_date = $entry_date;
    if ($desde !== '' && $desde > $entry_date) {
        $start_date = $desde;
    }

    // El rango de pagos termina en `hasta` inclusive (sin proyectar deuda futura).
    // Las semanas son intervalos [ps, pe): se incluye la que contiene end_date.
    $end_date = date('Y-m-d', $today_ts);
    if ($hasta !== '' && $hasta < $end_date) $end_date = $hasta;
    if ($end_date < $start_date) {
        return array('error' => 'sin_vencimientos', 'entry_date' => $entry_date);
    }

    // Vencimientos hasta el primer límite semanal estrictamente posterior a end_date.
    $due_dates_all = array();
    for ($ts = $first_due_ts; ; $ts = strtotime('+7 day', $ts)) {
        $due = date('Y-m-d', $ts);
        $due_dates_all[] = $due;
        if ($due > $end_date) break;
    }

    // Primer periodo [ps, pe) que contiene start_date; si start_date cae justo en
    // un límite, comienza el periodo nuevo (pe debe ser estrictamente posterior).
    $w0 = 0;
    while ($w0 < count($due_dates_all) && $due_dates_all[$w0] <= $start_date) {
        $w0++;
    }
    if ($w0 >= count($due_dates_all)) {
        return array('error' => 'sin_vencimientos', 'entry_date' => $entry_date);
    }

    $due_dates = array_slice($due_dates_all, $w0);
    $num_weeks = count($due_dates);
    // "semana 1" del informe: su periodo es la semana completa [due anterior, due],
    // solo que se ignoran los pagos anteriores a start_date (reinicio del arrastre).
    $first_ps = ($w0 === 0) ? $entry_date : $due_dates_all[$w0 - 1];

    // Clasificar leads (scoped al rango: pdate entre start_date y end_date).
    $alquiler_payments = array();
    $no_alq_payments = array();
    $dudosos = array();
    $pagos_raw = array();

    foreach ($leads as $lead) {
        $pdate = substr((string)($lead['created_at'] ?? ''), 0, 10);
        $amount = (float)($lead['precio'] ?? 0);
        if ($pdate === '' || $amount <= 0) continue;
        if ($pdate < $start_date) continue;
        if ($pdate > $end_date) continue;

        $leadId = (string)($lead['id'] ?? '');
        $desc = (string)($lead['observacion'] ?? '');

        $clasif = jostal_concepto_tipo_efectivo($lead);
        if (in_array($leadId, $overrides, true)) {
            $clasif = array('tipo' => 'alquiler', 'razon' => 'Compensación temporal');
        }

        $tipo = $clasif['tipo'];
        $pagos_raw[] = array(
            'lead_id' => $leadId,
            'date' => $pdate,
            'amount' => $amount,
            'desc' => $desc,
            'tipo' => $tipo,
        );

        if ($tipo === 'dudoso') {
            $dudosos[] = array(
                'lead_id' => $leadId,
                'date' => $pdate,
                'amount' => $amount,
                'concepto' => $desc,
                'razon' => (string)$clasif['razon'],
            );
        } elseif ($tipo === 'alquiler') {
            $alquiler_payments[] = array(
                'date' => $pdate,
                'amount' => $amount,
                'desc' => $desc,
                'lead_id' => $leadId,
            );
        } else {
            $no_alq_payments[] = array(
                'date' => $pdate,
                'amount' => $amount,
                'desc' => $desc,
                'lead_id' => $leadId,
            );
        }
    }

    // Inyectar pagos extra no registrados como lead (reservas bizum guardadas como venta, etc.).
    $pagosExtra = jostal_pagos_extra();
    if (isset($pagosExtra[$cid])) {
        foreach ($pagosExtra[$cid] as $pe) {
            $pdate = (string)($pe['date'] ?? '');
            $amount = (float)($pe['amount'] ?? 0);
            if ($pdate === '' || $amount <= 0) continue;
            if ($pdate < $start_date || $pdate > $end_date) continue;
            $alquiler_payments[] = array(
                'date' => $pdate,
                'amount' => $amount,
                'desc' => (string)($pe['desc'] ?? ''),
                'lead_id' => '',
            );
            $pagos_raw[] = array(
                'lead_id' => '',
                'date' => $pdate,
                'amount' => $amount,
                'desc' => (string)($pe['desc'] ?? ''),
                'tipo' => 'alquiler',
            );
        }
    }

    usort($alquiler_payments, function ($a, $b) { return strcmp($a['date'], $b['date']); });
    usort($no_alq_payments, function ($a, $b) { return strcmp($a['date'], $b['date']); });

    // Precio por semana (soporta cambio de precio histórico 130 → 150, etc.).
    $precio_por_semana = array_fill(0, $num_weeks, $precio);
    for ($w = 0; $w < $num_weeks; $w++) {
        $ps = ($w === 0) ? $first_ps : $due_dates[$w - 1];
        $precio_por_semana[$w] = jostal_precio_por_fecha($clienta, $ps);
    }

    // Asignación FIFO: cada pago cubre la semana más antigua con deuda pendiente.
    // El sobrante fluye a la semana siguiente; si paga más allá de todas, queda "a favor".
    $remaining = array_fill(0, $num_weeks, 0.0);
    $allocated = array_fill(0, $num_weeks, 0.0);
    $pagos_semana = array_fill(0, $num_weeks, array());
    for ($w = 0; $w < $num_weeks; $w++) $remaining[$w] = $precio_por_semana[$w];

    $saldo_favor = 0.0;
    foreach ($alquiler_payments as $p) {
        $amt = (float)$p['amount'];
        if ($amt <= 0) continue;
        for ($w = 0; $w < $num_weeks; $w++) {
            if ($remaining[$w] <= 0.0005) continue;
            $take = min($amt, $remaining[$w]);
            $allocated[$w] += $take;
            $remaining[$w] -= $take;
            $amt -= $take;
            $entrada = $p;
            $entrada['aplicado'] = $take;
            $pagos_semana[$w][] = $entrada;
            if ($amt <= 0.0005) break;
        }
        if ($amt > 0.0005) $saldo_favor += $amt;
    }

    // Otros ingresos por semana (no descuentan deuda, solo informativos).
    $wnp = array_fill(0, $num_weeks, array());
    foreach ($no_alq_payments as $p) {
        for ($w = 0; $w < $num_weeks; $w++) {
            $ps = ($w === 0) ? $first_ps : $due_dates[$w - 1];
            $pe = $due_dates[$w];
            if (($p['date'] >= $ps) && ($p['date'] < $pe)) {
                $wnp[$w][] = $p;
                break;
            }
        }
    }

    // Pagado por fecha ("Pagó esta semana"): dinero entregado en esa semana, por fecha
    // del pago, independiente de FIFO. Es lo que la clienta recuerda haber pagado.
    $pagos_fecha = array_fill(0, $num_weeks, array());
    $pagado_real = array_fill(0, $num_weeks, 0.0);
    foreach ($alquiler_payments as $p) {
        for ($w = 0; $w < $num_weeks; $w++) {
            $ps = ($w === 0) ? $first_ps : $due_dates[$w - 1];
            $pe = $due_dates[$w];
            $ok = ($p['date'] >= $ps) && ($p['date'] < $pe);
            if ($ok) {
                $pagos_fecha[$w][] = $p;
                $pagado_real[$w] += (float)$p['amount'];
                break;
            }
        }
    }

    $weeks = array();
    $running = 0.0;
    $debe_total = 0.0;
    $pagado_total = 0.0;
    $resumen_meses = array();
    $deuda_vencida = 0.0;
    $pendiente_actual = 0.0;

    for ($w = 0; $w < $num_weeks; $w++) {
        $ps = ($w === 0) ? $first_ps : $due_dates[$w - 1];
        $pe = $due_dates[$w];
        $debe = $precio_por_semana[$w];
        $paid = $allocated[$w];
        $diff = $debe - $paid;
        $running_in = $running;
        $running += $diff;
        $es_actual = (strtotime($pe . ' 00:00:00') > $today_ts);

        $debe_total += $debe;
        $pagado_total += $paid;

        if ($es_actual) {
            $pendiente_actual = $diff > 0 ? $diff : 0.0;
        } else {
            $deuda_vencida += $diff;
        }

        $weeks[] = array(
            'n' => $w + 1,
            'ps' => $ps,
            'pe' => $pe,
            'due' => $pe,
            'debe' => $debe,
            'pagos' => $pagos_semana[$w],
            'pagos_fecha' => $pagos_fecha[$w],
            'pagado' => $paid,
            'pagado_real' => $pagado_real[$w],
            'arrastre' => $running_in,
            'diff' => $diff,
            'running' => $running,
            'otros' => $wnp[$w],
            'es_actual' => $es_actual,
        );

        $mes = substr($pe, 0, 7);
        if (!isset($resumen_meses[$mes])) {
            $resumen_meses[$mes] = array('debe' => 0.0, 'pagado' => 0.0, 'diff' => 0.0, 'running' => 0.0);
        }
        $resumen_meses[$mes]['debe'] += $debe;
        $resumen_meses[$mes]['pagado'] += $paid;
        $resumen_meses[$mes]['diff'] += $diff;
        $resumen_meses[$mes]['running'] = $running;
    }

    // ── Modo "pago esta semana": balance directo + compensación adyacente ──
    // Fuente = pagado_real (dinero entregado por fecha). Cada semana se evalúa por sí
    // misma; el único cruce de semanas es el sobrante de una semana, que cubre primero
    // la deuda de la semana anterior y, si no la hay, la semana siguiente (adelanto).
    $direct_s = array_fill(0, $num_weeks, 0.0);
    $comp_back = array_fill(0, $num_weeks, 0.0);   // sobrante de w que cubre w-1
    $comp_fwd = array_fill(0, $num_weeks, 0.0);    // sobrante de w que cubre w+1
    $comp_favor = array_fill(0, $num_weeks, 0.0);  // sobrante de w que queda a favor
    for ($w = 0; $w < $num_weeks; $w++) {
        $direct_s[$w] = $pagado_real[$w] - $precio_por_semana[$w];
    }

    $saldo_favor_semana = 0.0;
    for ($w = 0; $w < $num_weeks; $w++) {
        if ($direct_s[$w] <= 0.0005) continue;
        $s = $direct_s[$w];
        // Atrás: cubre la deuda directa de la semana anterior.
        if ($w - 1 >= 0 && $direct_s[$w - 1] < -0.0005) {
            $t = min($s, -$direct_s[$w - 1]);
            $direct_s[$w - 1] += $t;
            $direct_s[$w] -= $t;
            $comp_back[$w] += $t;
            $s -= $t;
        }
        // Adelante: cubre la semana siguiente (dinero adelantado).
        if ($s > 0.0005 && $w + 1 < $num_weeks && $direct_s[$w + 1] < -0.0005) {
            $t = min($s, -$direct_s[$w + 1]);
            $direct_s[$w + 1] += $t;
            $direct_s[$w] -= $t;
            $comp_fwd[$w] += $t;
            $s -= $t;
        }
        // Resto → a favor.
        if ($s > 0.0005) {
            $comp_favor[$w] += $s;
            $saldo_favor_semana += $s;
            $direct_s[$w] -= $s;
        }
    }

    // Totales y campos por semana del modo "semana".
    $running_s = 0.0;
    $deuda_total_semana = 0.0;
    $deuda_vencida_semana = 0.0;
    $pendiente_actual_semana = 0.0;
    $resumen_meses_semana = array();
    for ($w = 0; $w < $num_weeks; $w++) {
        $deficit = $direct_s[$w] < -0.0005 ? -$direct_s[$w] : 0.0;
        $running_in_s = $running_s;
        $running_s += $deficit;
        $pe = $due_dates[$w];
        $es_actual = (strtotime($pe . ' 00:00:00') > $today_ts);

        $weeks[$w]['arrastre_semana'] = $running_in_s;
        $weeks[$w]['diff_semana'] = $deficit;
        $weeks[$w]['running_semana'] = $running_s;
        $weeks[$w]['deficit_semana'] = $deficit;
        $weeks[$w]['comp_back'] = $comp_back[$w];
        $weeks[$w]['comp_fwd'] = $comp_fwd[$w];
        $weeks[$w]['comp_favor'] = $comp_favor[$w];

        $deuda_total_semana += $deficit;
        if ($es_actual) {
            $pendiente_actual_semana += $deficit;
        } else {
            $deuda_vencida_semana += $deficit;
        }

        $mes = substr($pe, 0, 7);
        if (!isset($resumen_meses_semana[$mes])) {
            $resumen_meses_semana[$mes] = array('debe' => 0.0, 'pagado' => 0.0, 'diff' => 0.0, 'running' => 0.0);
        }
        $resumen_meses_semana[$mes]['debe'] += $precio_por_semana[$w];
        $resumen_meses_semana[$mes]['pagado'] += $pagado_real[$w];
        $resumen_meses_semana[$mes]['diff'] += $deficit;
        $resumen_meses_semana[$mes]['running'] = $running_s;
    }
    $pagado_total_semana = array_sum($pagado_real);

    $no_alq_total = 0.0;
    foreach ($no_alq_payments as $p) $no_alq_total += $p['amount'];

    // Precios distintos usados (para mostrar en cabecera, p. ej. "130€ → 150€").
    $precios = array();
    foreach ($precio_por_semana as $pr) {
        if ($pr > 0) $precios[(string)$pr] = (float)$pr;
    }
    $precios = array_values($precios);
    sort($precios);

    // ── "Borrón y cuenta nueva" (perdón hardcodeado para clientas especiales) ──
    // Se perdona toda la deuda anterior a `desde`: el arrastre y las compensaciones
    // se reinician a partir de esa semana. `ignorar_actual` ignora los pagos de la
    // semana en curso (dinero absorbido por la deuda antigua perdonada).
    $perdon = jostal_perdon_legacy($clienta);
    $perdon_info = null;
    if ($perdon !== null) {
        $pdesde = (string)$perdon['desde'];
        $pIgnorarActual = !empty($perdon['ignorar_actual']);
        $reset_w = -1;
        foreach ($weeks as $i => $w) {
            if ($w['ps'] >= $pdesde) { $reset_w = $i; break; }
        }
        if ($reset_w >= 0) {
            $deuda_perdonada = ($reset_w > 0) ? (float)$weeks[$reset_w - 1]['running'] : 0.0;

            for ($i = 0; $i < $reset_w; $i++) $weeks[$i]['perdonada'] = true;

            // Re-FIFO solo sobre las semanas post-reset, con pagos desde la fecha
            // exacta configurada. Si procede, los pagos de la semana actual se
            // excluyen antes tanto de FIFO como de la compensación semanal.
            $pagos_post = array();
            $post_pagado_real = array_fill(0, $num_weeks - $reset_w, 0.0);
            $post_pagos_fecha = array_fill(0, $num_weeks - $reset_w, array());
            foreach ($alquiler_payments as $p) {
                if ($p['date'] < $pdesde) continue;
                $ignorado = false;
                if ($pIgnorarActual) {
                    for ($i = $reset_w; $i < $num_weeks; $i++) {
                        if (!empty($weeks[$i]['es_actual']) && $p['date'] >= $weeks[$i]['ps'] && $p['date'] < $weeks[$i]['pe']) {
                            $ignorado = true;
                            break;
                        }
                    }
                }
                if ($ignorado) continue;
                $pagos_post[] = $p;
                for ($i = $reset_w; $i < $num_weeks; $i++) {
                    if ($p['date'] >= $weeks[$i]['ps'] && $p['date'] < $weeks[$i]['pe']) {
                        $k = $i - $reset_w;
                        $post_pagado_real[$k] += (float)$p['amount'];
                        $post_pagos_fecha[$k][] = $p;
                        break;
                    }
                }
            }
            $post_n = $num_weeks - $reset_w;
            $post_debe = array();
            for ($i = $reset_w; $i < $num_weeks; $i++) $post_debe[] = (float)$weeks[$i]['debe'];
            $rem = $post_debe;
            $alloc = array_fill(0, $post_n, 0.0);
            $pagos_sem = array_fill(0, $post_n, array());
            foreach ($pagos_post as $p) {
                $amt = (float)$p['amount'];
                if ($amt <= 0) continue;
                for ($k = 0; $k < $post_n; $k++) {
                    if ($rem[$k] <= 0.0005) continue;
                    $take = min($amt, $rem[$k]);
                    $alloc[$k] += $take; $rem[$k] -= $take; $amt -= $take;
                    $e = $p; $e['aplicado'] = $take;
                    $pagos_sem[$k][] = $e;
                    if ($amt <= 0.0005) break;
                }
            }

            // Compensación adyacente aislada dentro del tramo post-perdón.
            $post_direct = array_fill(0, $post_n, 0.0);
            $post_comp_back = array_fill(0, $post_n, 0.0);
            $post_comp_fwd = array_fill(0, $post_n, 0.0);
            $post_comp_favor = array_fill(0, $post_n, 0.0);
            $post_saldo_favor_semana = 0.0;
            for ($k = 0; $k < $post_n; $k++) $post_direct[$k] = $post_pagado_real[$k] - $post_debe[$k];
            for ($k = 0; $k < $post_n; $k++) {
                if ($post_direct[$k] <= 0.0005) continue;
                $sobrante = $post_direct[$k];
                if ($k > 0 && $post_direct[$k - 1] < -0.0005) {
                    $t = min($sobrante, -$post_direct[$k - 1]);
                    $post_direct[$k - 1] += $t; $post_direct[$k] -= $t;
                    $post_comp_back[$k] += $t; $sobrante -= $t;
                }
                if ($sobrante > 0.0005 && $k + 1 < $post_n && $post_direct[$k + 1] < -0.0005) {
                    $t = min($sobrante, -$post_direct[$k + 1]);
                    $post_direct[$k + 1] += $t; $post_direct[$k] -= $t;
                    $post_comp_fwd[$k] += $t; $sobrante -= $t;
                }
                if ($sobrante > 0.0005) {
                    $post_comp_favor[$k] += $sobrante;
                    $post_saldo_favor_semana += $sobrante;
                    $post_direct[$k] -= $sobrante;
                }
            }

            $run = 0.0;
            $run_s = 0.0;
            $debe_total = 0.0; $pagado_total = 0.0;
            $deuda_vencida = 0.0; $pendiente_actual = 0.0;
            $deuda_vencida_semana = 0.0; $pendiente_actual_semana = 0.0;
            $resumen_meses = array();
            $resumen_meses_semana = array();

            for ($k = 0; $k < $post_n; $k++) {
                $i = $reset_w + $k;
                $w = &$weeks[$i];
                $debe = (float)$w['debe'];
                $paid = $alloc[$k];
                $diff = $debe - $paid;
                $esActual = !empty($w['es_actual']);

                $deficit = $post_direct[$k] < -0.0005 ? -$post_direct[$k] : 0.0;

                $w['pagado'] = $paid;
                $w['diff'] = $diff;
                $w['pagos'] = $pagos_sem[$k];
                $w['pagado_real'] = $post_pagado_real[$k];
                $w['pagos_fecha'] = $post_pagos_fecha[$k];
                $w['arrastre'] = $run;
                $run += $diff;
                $w['running'] = $run;
                $w['es_perdon'] = ($k === 0);

                $w['arrastre_semana'] = $run_s;
                $run_s += $deficit;
                $w['diff_semana'] = $deficit;
                $w['deficit_semana'] = $deficit;
                $w['running_semana'] = $run_s;
                $w['comp_back'] = $post_comp_back[$k];
                $w['comp_fwd'] = $post_comp_fwd[$k];
                $w['comp_favor'] = $post_comp_favor[$k];

                $debe_total += $debe;
                $pagado_total += $paid;
                if ($esActual) {
                    $pendiente_actual = $diff > 0 ? $diff : 0.0;
                    $pendiente_actual_semana = $deficit > 0 ? $deficit : 0.0;
                } else {
                    $deuda_vencida += $diff;
                    $deuda_vencida_semana += $deficit;
                }

                $mes = substr($w['pe'], 0, 7);
                if (!isset($resumen_meses[$mes])) $resumen_meses[$mes] = array('debe' => 0.0, 'pagado' => 0.0, 'diff' => 0.0, 'running' => 0.0);
                $resumen_meses[$mes]['debe'] += $debe;
                $resumen_meses[$mes]['pagado'] += $paid;
                $resumen_meses[$mes]['diff'] += $diff;
                $resumen_meses[$mes]['running'] = $run;
                if (!isset($resumen_meses_semana[$mes])) $resumen_meses_semana[$mes] = array('debe' => 0.0, 'pagado' => 0.0, 'diff' => 0.0, 'running' => 0.0);
                $resumen_meses_semana[$mes]['debe'] += $debe;
                $resumen_meses_semana[$mes]['pagado'] += $post_pagado_real[$k];
                $resumen_meses_semana[$mes]['diff'] += $deficit;
                $resumen_meses_semana[$mes]['running'] = $run_s;
            }

            $deuda_total_semana = $run_s;
            $pagado_total_semana = array_sum($post_pagado_real);
            $saldo_favor = max(0.0, array_sum(array_map(function ($p) { return (float)$p['amount']; }, $pagos_post)) - $pagado_total);
            $saldo_favor_semana = $post_saldo_favor_semana;

            // Pagos NO alquiler post-reset (el resto se considera perdonado).
            $no_alq_total = 0.0;
            foreach ($no_alq_payments as $p) {
                if ($p['date'] >= $pdesde) $no_alq_total += $p['amount'];
            }

            $perdon_info = array(
                'desde' => $pdesde,
                'deuda_perdonada' => $deuda_perdonada,
                'reset_index' => $reset_w,
                'ignorar_actual' => $pIgnorarActual,
            );
        }
    }

    return array(
        'precio' => $precio,
        'precios' => $precios,
        'entry_date' => $entry_date,
        'first_due_date' => $first_due_date,
        'due_weekday_label' => (string)$pi['due_weekday_label'],
        'start_date' => $start_date,
        'end_date' => $end_date,
        'weeks' => $weeks,
        'debe_total' => $debe_total,
        'pagado_total' => $pagado_total,
        'deuda_total' => $debe_total - $pagado_total,
        'deuda_vencida' => $deuda_vencida,
        'pendiente_actual' => $pendiente_actual,
        'saldo_favor' => $saldo_favor,
        'no_alq_total' => $no_alq_total,
        'dudosos' => $dudosos,
        'num_weeks' => $num_weeks,
        'resumen_meses' => $resumen_meses,
        'pagos_raw' => $pagos_raw,
        // Modo "pago esta semana" (fuente: pagado_real, compensación adyacente).
        'debe_total_semana' => $debe_total,
        'pagado_total_semana' => $pagado_total_semana,
        'deuda_total_semana' => $deuda_total_semana,
        'deuda_vencida_semana' => $deuda_vencida_semana,
        'pendiente_actual_semana' => $pendiente_actual_semana,
        'saldo_favor_semana' => $saldo_favor_semana,
        'resumen_meses_semana' => $resumen_meses_semana,
        'perdon' => $perdon_info,
    );
}

/**
 * Filtra semanas por su vencimiento. Se conserva para consumidores legacy; los informes
 * nuevos ya llegan recalculados por jostal_compute_deuda y no deben volver a filtrarse.
 */
function jostal_weeks_en_rango($weeks, $desde, $hasta) {
    $desde = trim((string)$desde);
    $hasta = trim((string)$hasta);
    if ($desde === '' && $hasta === '') return $weeks;

    $desdeTs = $desde !== '' ? strtotime($desde . ' 00:00:00') : null;
    $hastaTs = $hasta !== '' ? strtotime($hasta . ' 00:00:00') : null;

    $out = array();
    foreach ($weeks as $week) {
        $dueTs = strtotime($week['due'] . ' 00:00:00');
        if ($desdeTs !== null && $dueTs < $desdeTs) continue;
        if ($hastaTs !== null && $dueTs > $hastaTs) continue;
        $out[] = $week;
    }
    return $out;
}

/**
 * Construye el texto plano del informe de deuda para enviar por WhatsApp.
 */
function jostal_texto_deuda($nombre, $data, $desde = '', $hasta = '', $fuente = 'alquiler') {
    $nombre = trim((string)$nombre);
    $esSemana = ($fuente === 'semana');
    $precios = (array)($data['precios'] ?? array());
    $precioLabel = count($precios) > 0 ? implode('€ → ', array_unique(array_map(function ($p) { return (int)round((float)$p); }, $precios))) . '€' : (string)round((float)($data['precio'] ?? 0));
    $deuda = (float)($esSemana ? ($data['deuda_total_semana'] ?? 0) : ($data['deuda_total'] ?? 0));
    $saldoFavor = (float)($esSemana ? ($data['saldo_favor_semana'] ?? 0) : ($data['saldo_favor'] ?? 0));
    $weeks = (array)($data['weeks'] ?? array());

    $lineas = array();
    $lineas[] = '🏠 *Informe de alquiler*';
    if ($nombre !== '') $lineas[] = '👤 ' . $nombre;
    $lineas[] = '💰 ' . $precioLabel . '/sem · ' . count($weeks) . ' semana(s)';
    $lineas[] = '🧮 Fuente: ' . ($esSemana ? 'pago esta semana' : 'pago alquiler cubre');

    foreach ($weeks as $w) {
        $debe = (float)$w['debe'];
        $pagado = (float)($esSemana ? ($w['pagado_real'] ?? 0) : ($w['pagado'] ?? 0));
        $run = (float)($esSemana ? ($w['running_semana'] ?? 0) : ($w['running'] ?? 0));
        $dif = (float)($esSemana ? ($w['diff_semana'] ?? 0) : ($w['diff'] ?? 0));
        $esActual = !empty($w['es_actual']);

        if ($esActual) {
            $estado = $dif > 0.005 ? 'pendiente ' . euro($dif) : 'al día esta semana';
            $lineas[] = '· S' . $w['n'] . ' (' . jostal_fecha_corta($w['ps']) . '–' . jostal_fecha_corta(jostal_periodo_fin_inclusivo($w['pe'])) . ' incl.) *en curso*: pagó ' . euro($pagado) . '/' . euro($debe) . ' → ' . $estado;
        } else {
            if ($dif > 0.005) {
                $estado = 'debe ' . euro($dif);
            } else {
                $estado = 'ok';
            }
            $lineas[] = '· S' . $w['n'] . ' (' . jostal_fecha_corta($w['ps']) . '–' . jostal_fecha_corta(jostal_periodo_fin_inclusivo($w['pe'])) . ' incl.): pagó ' . euro($pagado) . '/' . euro($debe) . ' → ' . $estado . ($run > 0.005 ? ' · acum ' . euro($run) : '');
        }
    }

    if ($deuda > 0.005) {
        $lineas[] = '⚠️ *Debe: ' . euro($deuda) . '*';
    } elseif ($saldoFavor > 0.005) {
        $lineas[] = '✅ A favor: ' . euro($saldoFavor);
    } else {
        $lineas[] = '✅ Al día';
    }

    return implode("\n", $lineas);
}

/** Formatea fecha Y-m-d → d/m. */
function jostal_fecha_corta($date) {
    $ts = strtotime((string)$date);
    return $ts ? date('d/m', $ts) : (string)$date;
}

/** Convierte el límite exclusivo de un periodo [ps, pe) en su última fecha incluida. */
function jostal_periodo_fin_inclusivo($periodEndExclusive) {
    $ts = strtotime((string)$periodEndExclusive . ' 00:00:00 -1 day');
    return $ts ? date('Y-m-d', $ts) : (string)$periodEndExclusive;
}

/** Formatea clave de mes 'Y-m' → 'mes YYYY' (ej. '2026-05' → 'mayo 2026'). */
function jostal_mes_label($mesKey) {
    $ts = strtotime((string)$mesKey . '-01');
    if (!$ts) return (string)$mesKey;
    static $m = array(1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
    return $m[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

/**
 * Capacidad máxima de la casa Jostal (plazas disponibles para copar aforo).
 */
if (!defined('JOSTAL_CASA_CAPACIDAD')) {
    define('JOSTAL_CASA_CAPACIDAD', 5);
}

/**
 * Número de clientas Jostal que están ahora mismo "en casa" (ocupación actual).
 */
function jostal_en_casa_count() {
    $rows = storage_read('jostal_clientas.json');
    $count = 0;
    foreach ((array)$rows as $row) {
        if (is_array($row) && jostal_clienta_en_casa($row)) {
            $count++;
        }
    }
    return $count;
}


function lamamibot_girlsconf_base_dir() {
    return '/var/www/html/wasapbot/landing/girlsconf_lamamidef';
}

function lamamibot_girlsconf_json_path() {
    return lamamibot_girlsconf_base_dir() . '/data/girls.json';
}

function lamamibot_girlsconf_base_url() {
    return 'https://casawasap.com/girlsconf_lamamidef';
}

function lamamibot_memory_key_from_clienta($clienta) {
    $raw = trim((string)($clienta['id'] ?? ''));
    if ($raw === '') {
        $raw = trim((string)($clienta['nombre'] ?? 'girl'));
    }
    $raw = preg_replace('/[^A-Za-z0-9_\-]/', '_', $raw);
    $raw = strtolower(trim((string)$raw));
    return $raw !== '' ? $raw : 'girl';
}

function lamamibot_memory_paths_for_clienta($clienta) {
    $key = lamamibot_memory_key_from_clienta($clienta);

    return array(
        'memory_file' => '/data/session_memory_lamamibot_' . $key . '.ndjson',
        'memory_file_tmp' => '/data/session_memory_lamamibot_' . $key . '.ndjson.tmp',
        'memory_lock' => '/data/.session_memory_lamamibot_' . $key . '.lock',
    );
}

function lamamibot_prepare_session_memory_file_by_container_path($containerPath) {
    $hostPath = bot_mode_resolve_host_path($containerPath);
    $hostPath = trim((string)$hostPath);

    if ($hostPath === '') {
        return array(false, 'Ruta de memoria vacía.');
    }

    $dir = dirname($hostPath);
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
        return array(false, 'No se pudo crear la carpeta de memoria: ' . $dir);
    }

    if (!file_exists($hostPath)) {
        $ok = @file_put_contents($hostPath, '');
        if ($ok === false && !file_exists($hostPath)) {
            return array(false, 'No se pudo crear el fichero de memoria: ' . $hostPath);
        }
    }

    @chmod($hostPath, 0777);

    return array(true, $hostPath);
}

function lamamibot_prepare_session_memory_file_for_clienta($clienta) {
    $memory = lamamibot_memory_paths_for_clienta($clienta);
    return lamamibot_prepare_session_memory_file_by_container_path($memory['memory_file'] ?? '');
}

function lamamibot_guess_descripcion_corta($clienta, $existing = '') {
    $existing = trim((string)$existing);
    if ($existing !== '') {
        return $existing;
    }

    $direct = trim((string)($clienta['descripcion_corta'] ?? ''));
    if ($direct !== '') {
        return $direct;
    }

    $notas = trim((string)($clienta['notas'] ?? ''));
    if ($notas !== '') {
        $lines = preg_split('/\r\n|\r|\n/', $notas);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $line = trim((string)$line);
                if ($line !== '') {
                    return mb_substr($line, 0, 180);
                }
            }
        }
    }

    return trim((string)($clienta['nombre'] ?? ''));
}

function lamamibot_canonical_girl_ref($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    $value = strtolower($value);
    return str_replace('-', '_', $value);
}

function lamamibot_existing_field_or_fallback(array $existing, string $key, $fallback = '') {
    if (array_key_exists($key, $existing)) {
        return trim((string)$existing[$key]);
    }
    return trim((string)$fallback);
}

function lamamibot_girlsconf_row_score(array $girl): int {
    $score = 0;

    if (trim((string)($girl['crm_clienta_id'] ?? '')) !== '') $score += 50;
    if (!empty($girl['activa'])) $score += 10;

    foreach (array('id','nombre','descripcion_corta','zona','servicios','tarifas','ubicacion_maps','memory_file','memory_file_tmp','memory_lock') as $k) {
        if (trim((string)($girl[$k] ?? '')) !== '') $score += 2;
    }

    $fotos = isset($girl['fotos']) && is_array($girl['fotos']) ? $girl['fotos'] : array();
    $score += count(array_filter($fotos, function($x){ return trim((string)$x) !== ''; })) * 3;

    return $score;
}

function lamamibot_girlsconf_merge_rows(array $a, array $b): array {
    $scoreA = lamamibot_girlsconf_row_score($a);
    $scoreB = lamamibot_girlsconf_row_score($b);

    if ($scoreB > $scoreA) {
        $base = $b;
        $other = $a;
    } else {
        $base = $a;
        $other = $b;
    }

    foreach (array('crm_clienta_id','id','nombre','descripcion_corta','zona','servicios','tarifas','ubicacion_maps','memory_file','memory_file_tmp','memory_lock') as $k) {
        if (trim((string)($base[$k] ?? '')) === '' && trim((string)($other[$k] ?? '')) !== '') {
            $base[$k] = trim((string)$other[$k]);
        }
    }

    $f1 = isset($a['fotos']) && is_array($a['fotos']) ? $a['fotos'] : array();
    $f2 = isset($b['fotos']) && is_array($b['fotos']) ? $b['fotos'] : array();

    $base['fotos'] = array_values(array_unique(array_filter(array_merge($f1, $f2), function($x){
        return trim((string)$x) !== '';
    })));

    $base['activa'] = !empty($a['activa']) || !empty($b['activa']);

    return $base;
}

function lamamibot_normalize_girlsconf_rows($girls) {
    $girls = is_array($girls) ? array_values($girls) : array();

    $out = array();
    $keyToIndex = array();

    foreach ($girls as $girl) {
        if (!is_array($girl)) continue;

        $keys = array();

        $crm = lamamibot_canonical_girl_ref($girl['crm_clienta_id'] ?? '');
        $id  = lamamibot_canonical_girl_ref($girl['id'] ?? '');

        if ($crm !== '') $keys[] = 'crm:' . $crm;
        if ($id !== '')  $keys[] = 'id:' . $id;

        if (empty($keys)) {
            $out[] = $girl;
            continue;
        }

        $target = null;
        foreach ($keys as $k) {
            if (isset($keyToIndex[$k])) {
                $target = $keyToIndex[$k];
                break;
            }
        }

        if ($target === null) {
            $target = count($out);
            $out[] = $girl;
        } else {
            $out[$target] = lamamibot_girlsconf_merge_rows($out[$target], $girl);
        }

        $merged = $out[$target];
        $mergedKeys = array();

        $mcrm = lamamibot_canonical_girl_ref($merged['crm_clienta_id'] ?? '');
        $mid  = lamamibot_canonical_girl_ref($merged['id'] ?? '');

        if ($mcrm !== '') $mergedKeys[] = 'crm:' . $mcrm;
        if ($mid !== '')  $mergedKeys[] = 'id:' . $mid;

        foreach (array_unique(array_merge($keys, $mergedKeys)) as $k) {
            $keyToIndex[$k] = $target;
        }
    }

    return array_values($out);
}

function lamamibot_find_girl_index_by_clienta_id($girls, $clientaId) {
    $wanted = lamamibot_canonical_girl_ref($clientaId);
    if ($wanted === '') return -1;

    foreach ($girls as $i => $girl) {
        if (!is_array($girl)) continue;

        $crmId = lamamibot_canonical_girl_ref($girl['crm_clienta_id'] ?? '');
        $girlId = lamamibot_canonical_girl_ref($girl['id'] ?? '');

        if ($crmId !== '' && $crmId === $wanted) {
            return $i;
        }

        // fallback legacy para viejas filas sin crm_clienta_id
        if ($crmId === '' && $girlId === $wanted) {
            return $i;
        }
    }

    return -1;
}

function lamamibot_build_girlsconf_entry($clienta, $existing = array()) {
    $memory = lamamibot_memory_paths_for_clienta($clienta);

    $fotos = array();
    if (isset($existing['fotos']) && is_array($existing['fotos'])) {
        $fotos = array_values($existing['fotos']);
    }

    $activa = false;
    if (array_key_exists('activa', $existing)) {
        $activa = !empty($existing['activa']);
    }

    $girlId = trim((string)($existing['id'] ?? ''));
    if ($girlId === '') {
        $girlId = trim((string)($clienta['id'] ?? ''));
    }

    $base = array(
        'id' => $girlId,
        'crm_clienta_id' => trim((string)($clienta['id'] ?? '')),
        'nombre' => lamamibot_existing_field_or_fallback($existing, 'nombre', $clienta['nombre'] ?? ''),
        'descripcion_corta' => lamamibot_existing_field_or_fallback($existing, 'descripcion_corta', lamamibot_guess_descripcion_corta($clienta, '')),
        'zona' => lamamibot_existing_field_or_fallback($existing, 'zona', $clienta['zona'] ?? ''),
        'servicios' => lamamibot_existing_field_or_fallback($existing, 'servicios', $clienta['servicios'] ?? ''),
        'tarifas' => lamamibot_existing_field_or_fallback($existing, 'tarifas', $clienta['tarifas'] ?? ''),
        'ubicacion_maps' => lamamibot_existing_field_or_fallback($existing, 'ubicacion_maps', $clienta['ubicacion_maps'] ?? ''),
        'memory_file' => $memory['memory_file'],
        'memory_file_tmp' => $memory['memory_file_tmp'],
        'memory_lock' => $memory['memory_lock'],
        'fotos' => $fotos,
        'activa' => $activa,
    );

    return array_merge($existing, $base);
}

function lamamibot_read_girlsconf_data() {
    $path = lamamibot_girlsconf_json_path();

    if (!file_exists($path)) {
        return array('girls' => array());
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return array('girls' => array());
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        $json = array();
    }

    if (!isset($json['girls']) || !is_array($json['girls'])) {
        $json['girls'] = array();
    }

    $json['girls'] = lamamibot_normalize_girlsconf_rows($json['girls']);

    return $json;
}

function lamamibot_write_girlsconf_data($data) {
    $path = lamamibot_girlsconf_json_path();
    $dir = dirname($path);

    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }

    $payload = json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($payload === false) {
        return false;
    }

    return @file_put_contents($path, $payload) !== false;
}

function lamamibot_compact_names($names, $limit = 4) {
    $clean = array();
    foreach ((array)$names as $name) {
        $name = trim((string)$name);
        if ($name === '') continue;
        $clean[] = $name;
    }

    if (empty($clean)) return '';

    $slice = array_slice($clean, 0, $limit);
    $text = implode(', ', $slice);

    if (count($clean) > $limit) {
        $text .= '…';
    }

    return $text;
}

function lamamibot_sync_girlsconf($clientasIds) {
    $clientasIds = is_array($clientasIds) ? array_values($clientasIds) : array();

    $data = lamamibot_read_girlsconf_data();
    $girls = isset($data['girls']) && is_array($data['girls']) ? array_values($data['girls']) : array();

    $stats = array(
        'created_inactive' => array(),
        'updated' => array(),
        'deactivated' => array(),
        'already_inactive' => array(),
        'missing_clientas' => array(),
    );

    foreach ($clientasIds as $clientaId) {
        $clientaId = trim((string)$clientaId);
        if ($clientaId === '') continue;

        $clienta = storage_find_by_id('clientes.json', $clientaId);
        if (!$clienta) {
            $stats['missing_clientas'][] = $clientaId;
            continue;
        }

        $idx = lamamibot_find_girl_index_by_clienta_id($girls, $clientaId);
        $nombre = trim((string)($clienta['nombre'] ?? $clientaId));

        if ($idx < 0) {
            $girls[] = lamamibot_build_girlsconf_entry($clienta, array(
                'activa' => false,
                'fotos' => array(),
            ));
            $stats['created_inactive'][] = $nombre;
        } else {
            $girls[$idx] = lamamibot_build_girlsconf_entry($clienta, $girls[$idx]);
            $stats['updated'][] = $nombre;
        }
        list($memoryOk, $memoryInfo) = lamamibot_prepare_session_memory_file_for_clienta($clienta);
        if (!$memoryOk) {
            return array(
                'ok' => false,
                'path' => $memoryInfo,
                'stats' => $stats,
                'message' => 'No se pudo preparar la memoria individual de LamamiBot para "' . $nombre . '": ' . $memoryInfo,
            );
        }
    }

    $selectedSet = array();
    foreach ($clientasIds as $id) {
        $id = trim((string)$id);
        if ($id !== '') $selectedSet[$id] = true;
    }

    foreach ($girls as $i => $girl) {
        if (!is_array($girl)) continue;

        $crmId = trim((string)($girl['crm_clienta_id'] ?? ''));
        if ($crmId === '') continue;

        if (isset($selectedSet[$crmId])) {
            continue;
        }

        $nombre = trim((string)($girl['nombre'] ?? $crmId));

        if (!empty($girl['activa'])) {
            $girls[$i]['activa'] = false;
            $stats['deactivated'][] = $nombre;
        } else {
            $stats['already_inactive'][] = $nombre;
        }
    }

    $data['girls'] = array_values($girls);

    if (!lamamibot_write_girlsconf_data($data)) {
        return array(
            'ok' => false,
            'path' => lamamibot_girlsconf_json_path(),
            'stats' => $stats,
            'message' => 'No se pudo escribir el girls.json de girlsconf_lamamidef.'
        );
    }

    return array(
        'ok' => true,
        'path' => lamamibot_girlsconf_json_path(),
        'stats' => $stats,
        'message' => 'Sincronización completada.'
    );
}

function lamamibot_build_sync_summary($telefonosIds, $clientasIds, $sync) {
    $parts = array();

    $parts[] = 'LamamiBot guardado';
    $parts[] = count((array)$telefonosIds) . ' líneas vinculadas';
    $parts[] = count((array)$clientasIds) . ' clientas vinculadas';

    $stats = isset($sync['stats']) && is_array($sync['stats']) ? $sync['stats'] : array();

    if (!empty($stats['created_inactive'])) {
        $parts[] = 'Añadidas como inactivas: ' . lamamibot_compact_names($stats['created_inactive']);
    }

    if (!empty($stats['updated'])) {
        $parts[] = 'Actualizadas: ' . lamamibot_compact_names($stats['updated']);
    }

    if (!empty($stats['deactivated'])) {
        $parts[] = 'Desactivadas: ' . lamamibot_compact_names($stats['deactivated']) . '. El bot ya no las ofrecerá';
    }

    if (!empty($stats['already_inactive'])) {
        $parts[] = 'Ya estaban inactivas: ' . lamamibot_compact_names($stats['already_inactive']);
    }

    if (!empty($stats['missing_clientas'])) {
        $parts[] = 'No encontradas: ' . lamamibot_compact_names($stats['missing_clientas']);
    }

    return implode(' · ', $parts);
}

// --- Pollo.ai random natural background pool ---

function publicista_natural_background_pool() {
    return array(
        'dormitorio_real' => 'dormitorio normal con la cama deshecha, ropa doblada en una silla, un cargador en la mesilla, ventana con luz natural entrando — aspecto de habitación real vivida, foto tomada con móvil',
        'salon_casa' => 'salón de casa real: sofá con mantas y cojines, mesa de centro con objetos (mando a distancia, taza, revista), televisión o cuadros en la pared, una planta — ambiente doméstico auténtico',
        'espejo_selfie' => 'selfie frente a un espejo de cuerpo entero en un dormitorio o baño real — el teléfono se intuye en la mano, el espejo tiene ligeras marcas o reflejos, se ven objetos de la habitación reflejados',
        'playa' => 'playa o paseo marítimo de día, con gente de fondo desenfocada, arena, palmeras o edificios costeros — luz natural de exterior real',
        'calle' => 'calle normal urbana de día, con tiendas, coches aparcados, farolas, acera — fondo de calle real con profundidad y elementos urbanos cotidianos',
        'pared' => 'primer plano contra una pared de ladrillo visto o una pared pintada con algo de textura y desconchones — fondo urbano sencillo y realista',
        'tienda_ropa' => 'dentro de una tienda de ropa normal, con perchas, estanterías con ropa doblada, y otras prendas colgadas al fondo — ambiente de tienda real con luz fluorescente',
        'probador' => 'dentro de un probador de tienda, con cortina y espejo, luz de fluorescente — espacio pequeño y real, no retocado',
        'parque' => 'banco de parque urbano, con césped y árboles detrás, gente paseando al fondo — exterior natural y relajado',
        'cafeteria' => 'sentada en una mesa de cafetería o bar normal, con una taza delante, servilletas, el mostrador de fondo — ambiente de bar real',
        'coche' => 'dentro de un coche normal, asiento de copiloto, se ve parte del salpicadero y la ventanilla con la calle al fondo — foto espontánea en coche',
        'escaleras' => 'escaleras de edificio o bloque de pisos, barandilla metálica, escalones de mármol o cemento — fondo arquitectónico real de edificio normal',
    );
}

function publicista_pick_random_backgrounds($count = 4) {
    $pool = publicista_natural_background_pool();
    $keys = array_keys($pool);
    $count = max(1, min((int)$count, count($keys)));
    $picked = array();
    $available = $keys;
    for ($i = 0; $i < $count; $i++) {
        if (empty($available)) break;
        $idx = array_rand($available);
        $key = $available[$idx];
        $picked[] = array(
            'key' => $key,
            'description' => $pool[$key],
        );
        unset($available[$idx]);
        $available = array_values($available);
    }
    return $picked;
}

// ─── Jostal Contratos ──────────────────────────────────────────────────────

function contrato_default_row() {
    return array(
        'id' => '',
        'clienta_id' => '',
        'estado' => 'borrador',
        'datos_arrendadora' => array('nombre' => 'Josué', 'dni' => '', 'telefono' => '', 'domicilio' => ''),
        'datos_ocupante' => array('nombre_real' => '', 'dni' => '', 'telefono' => ''),
        'habitacion_plaza' => '',
        'direccion_inmueble' => '',
        'precio_semanal' => '',
        'fianza' => '',
        'contenido_habitacion' => array(),
        'fecha_inicio' => '',
        'fecha_fin' => '',
        'firma_ocupante' => array('data_url' => '', 'fecha_hora' => '', 'ip' => '', 'dispositivo' => '', 'navegador' => ''),
        'firma_arrendadora' => array('data_url' => '', 'fecha_hora' => ''),
        'url_firma_token' => '',
        'created_at' => '',
        'updated_at' => '',
    );
}

function contrato_generar_url_firma($contrato) {
    $token = !empty($contrato['url_firma_token']) ? $contrato['url_firma_token'] : generate_id('ctrtkn');
    $base = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/control';
    return $base . '/contrato_firmado.php?token=' . urlencode($token);
}

function contrato_calcular_ventana_15dias() {
    $hoy = business_today_date();
    $fechaInicio = date('Y-m-d', strtotime($hoy));
    $fechaFin = date('Y-m-d', strtotime($hoy . ' + 14 days'));
    return array('fecha_inicio' => $fechaInicio, 'fecha_fin' => $fechaFin);
}

function contrato_find_by_clienta($clientaId) {
    $contratos = storage_read('contratos.json');
    foreach ($contratos as $c) {
        if (($c['clienta_id'] ?? '') === $clientaId) return $c;
    }
    return null;
}

function contrato_find_by_token($token) {
    $contratos = storage_read('contratos.json');
    foreach ($contratos as $c) {
        if (($c['url_firma_token'] ?? '') === $token) return $c;
    }
    return null;
}

function contrato_clienta_tiene_contrato_firmado($clientaId) {
    $contrato = contrato_find_by_clienta($clientaId);
    return $contrato && ($contrato['estado'] ?? '') === 'firmado';
}

// ═══════════════════════════════════════════════════════════════════════════════
// GPS / Rutas — helpers para la sección de tracking
// ═══════════════════════════════════════════════════════════════════════════════

define('GPS_FILE', __DIR__ . '/../data/gps_positions.jsonl');

/**
 * Lee el archivo JSONL de posiciones GPS y devuelve un array.
 * @param int $days  Días hacia atrás (0 = sin límite).
 * @return array     Array de posiciones ordenadas por timestamp ascendente.
 */
function gps_read_positions($days = 30) {
    $file = GPS_FILE;
    if (!file_exists($file)) return array();

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return array();

    $cutoff = $days > 0 ? time() - ($days * 86400) : 0;
    $positions = array();

    foreach ($lines as $line) {
        $p = json_decode($line, true);
        if (!$p || empty($p['ts']) || empty($p['lat']) || empty($p['lng'])) continue;
        $ts = strtotime($p['ts']);
        if ($ts === false) continue;
        if ($days > 0 && $ts < $cutoff) continue;
        $p['_ts'] = $ts;
        $positions[] = $p;
    }

    // Ordenar por timestamp ascendente
    usort($positions, function ($a, $b) { return $a['_ts'] - $b['_ts']; });
    return $positions;
}

/**
 * Agrupa posiciones por día (YYYY-MM-DD).
 * @return array  [ '2026-07-22' => [pos1, pos2, ...], ... ]
 */
function gps_group_by_day($positions) {
    $days = array();
    foreach ($positions as $p) {
        $date = date('Y-m-d', $p['_ts']);
        $days[$date][] = $p;
    }
    ksort($days);
    return $days;
}

/**
 * Distancia entre dos puntos en metros (fórmula Haversine).
 */
function gps_distance_m($lat1, $lng1, $lat2, $lng2) {
    $r = 6371000; // radio Tierra en metros
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng / 2) * sin($dLng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $r * $c;
}

/**
 * Distancia total de una ruta (secuencia ordenada de posiciones).
 */
function gps_route_total_km($positions) {
    $total = 0;
    $count = count($positions);
    for ($i = 1; $i < $count; $i++) {
        $a = $positions[$i - 1];
        $b = $positions[$i];
        $total += gps_distance_m($a['lat'], $a['lng'], $b['lat'], $b['lng']);
    }
    return round($total / 1000, 1);
}

/**
 * Última posición registrada.
 * @return array|null
 */
function gps_last_position() {
    $positions = gps_read_positions(7);
    return !empty($positions) ? end($positions) : null;
}

/**
 * Formatea minutos a texto legible.
 */
function gps_fmt_duration($minutes) {
    if ($minutes < 1) return 'menos de 1 min';
    if ($minutes < 60) return round($minutes) . ' min';
    $h = floor($minutes / 60);
    $m = round(fmod($minutes, 60));
    if ($m === 0) return $h . 'h';
    return $h . 'h ' . $m . 'min';
}

/**
 * Resumen KPI para las tarjetas superiores.
 * @param array  $positions  Todas las posiciones.
 * @param string $liteUser   Si se pasa, la última posición del coche se filtra por este usuario.
 */
function gps_kpi_summary($positions, $liteUser = null) {
    if (empty($positions)) {
        return array(
            'last'             => null,
            'last_ago'         => '—',
            'last_user'        => '',
            'last_active'      => null,
            'last_active_ago'  => '—',
            'last_active_user' => '',
            'today_km'         => 0,
            'today_trips'      => 0,
            'today_positions'  => 0,
            'week_km'          => 0,
            'week_trips'       => 0,
            'week_days'        => 0,
            'week_avg_km'      => 0,
        );
    }

    // ── Última posición del coche (solo lite, la que importa para el aparcamiento) ──
    $lastLite     = null;
    $lastLiteAgo  = '—';
    $lastLiteUser = '';
    if ($liteUser !== null) {
        $litePositions = array_values(array_filter($positions, function ($p) use ($liteUser) {
            return ($p['user'] ?? '') === $liteUser;
        }));
        if (!empty($litePositions)) {
            $lastLite     = end($litePositions);
            $lastLiteAgo  = gps_fmt_duration((time() - $lastLite['_ts']) / 60);
            $lastLiteUser = $liteUser;
        }
    }
    // Si no hay lite, usamos la última de cualquier cuenta como fallback
    if ($lastLite === null) {
        $lastLite     = end($positions);
        $lastLiteAgo  = gps_fmt_duration((time() - $lastLite['_ts']) / 60);
        $lastLiteUser = $lastLite['user'] ?? '';
    }

    // ── Última posición de CUALQUIER cuenta (última activa) ──
    $lastActive     = end($positions);
    $lastActiveAgo  = gps_fmt_duration((time() - $lastActive['_ts']) / 60);
    $lastActiveUser = $lastActive['user'] ?? 'unknown';

    $today        = date('Y-m-d');
    $todayPos     = array();
    $todayMarkers = array(); // solo las posiciones "enviadas" (debounced), una por poll

    // Ventana de 7 días para la semana
    $weekStart = time() - 7 * 86400;

    $weekPos      = array();
    $weekDaySet   = array();

    foreach ($positions as $p) {
        $d = date('Y-m-d', $p['_ts']);
        // Hoy
        if ($d === $today) {
            $todayPos[] = $p;
            // Solo guardamos un punto por minuto (el último de cada minuto) para la ruta
            $minute = date('Y-m-d H:i', $p['_ts']);
            $todayMarkers[$minute] = $p;
        }
        // Semana (últimos 7 días naturales)
        if ($p['_ts'] >= $weekStart) {
            $weekPos[] = $p;
            $weekDaySet[$d] = true;
        }
    }

    $todayMarkers = array_values($todayMarkers);

    // Contar trayectos: un trayecto = secuencia de movimiento (>500m entre puntos consecutivos)
    $todayTrips = gps_count_trips($todayMarkers);
    $weekTrips  = gps_count_trips($weekPos);

    $weekDays = count($weekDaySet);
    $weekKm   = gps_route_total_km($weekPos);

    return array(
        'last'             => $lastLite,
        'last_ago'         => $lastLiteAgo,
        'last_user'        => $lastLiteUser,
        'last_active'      => $lastActive,
        'last_active_ago'  => $lastActiveAgo,
        'last_active_user' => $lastActiveUser,
        'today_km'         => gps_route_total_km($todayMarkers),
        'today_trips'      => $todayTrips,
        'today_positions'  => count($todayPos),
        'week_km'          => $weekKm,
        'week_trips'       => $weekTrips,
        'week_days'        => $weekDays,
        'week_avg_km'      => $weekDays > 0 ? round($weekKm / $weekDays, 1) : 0,
    );
}

/**
 * Cuenta trayectos: cada vez que hay un salto de >500m entre puntos consecutivos
 * se considera un nuevo trayecto.
 */
function gps_count_trips($positions) {
    $trips = 0;
    $started = false;
    $count = count($positions);
    for ($i = 1; $i < $count; $i++) {
        $a = $positions[$i - 1];
        $b = $positions[$i];
        $d = gps_distance_m($a['lat'], $a['lng'], $b['lat'], $b['lng']);
        if ($d > 500) {
            if ($started) $trips++;
            $started = true;
        }
    }
    // Contar el último trayecto activo
    if ($started) $trips++;
    return $trips;
}

/**
 * Genera eventos de timeline para un día.
 * Cada evento tiene: tipo, hora, descripción, coordenadas.
 */
function gps_timeline_for_day($dayPositions) {
    $events = array();
    $count  = count($dayPositions);
    if ($count === 0) return $events;

    $MOVING_THRESHOLD = 100;   // metros: si avanza >100m en el intervalo, está en movimiento
    $STOP_MIN_MINUTES = 3;     // minutos mínimos parado para considerarlo "aparcado"

    $state     = 'unknown'; // 'moving' | 'stopped'
    $tripStart = null;
    $tripStartIdx = null;   // índice en $dayPositions donde empezó el trayecto
    $stopStart = null;
    $prev      = null;

    foreach ($dayPositions as $idx => $p) {
        if ($prev === null) {
            $prev = $p;
            continue;
        }

        $d = gps_distance_m($prev['lat'], $prev['lng'], $p['lat'], $p['lng']);
        $dt = ($p['_ts'] - $prev['_ts']) / 60; // minutos entre tomas

        if ($d > $MOVING_THRESHOLD) {
            // En movimiento
            if ($state !== 'moving') {
                // Acabamos de empezar a movernos
                if ($stopStart !== null) {
                    $events[] = array(
                        'type'     => 'stop_end',
                        'ts'       => $prev['_ts'],
                        'time'     => date('H:i', $prev['_ts']),
                        'lat'      => $prev['lat'],
                        'lng'      => $prev['lng'],
                        'duration' => ($prev['_ts'] - $stopStart['_ts']) / 60,
                        'label'    => 'Aparcado',
                    );
                    $stopStart = null;
                }
                $tripStart    = $prev;
                $tripStartIdx = $idx - 1; // $prev está en idx-1
                $state        = 'moving';
            }
        } else {
            // Parado
            if ($state === 'moving' && $tripStart !== null) {
                // Acabamos de parar — recoger ruta completa
                $tripDist = gps_distance_m($tripStart['lat'], $tripStart['lng'], $prev['lat'], $prev['lng']);
                $tripTime = ($prev['_ts'] - $tripStart['_ts']) / 60;

                // Extraer puntos intermedios del trayecto
                $routePoints = array();
                for ($ri = $tripStartIdx; $ri <= $idx; $ri++) {
                    $routePoints[] = array(
                        'lat' => (float)$dayPositions[$ri]['lat'],
                        'lng' => (float)$dayPositions[$ri]['lng'],
                    );
                }

                $events[] = array(
                    'type'      => 'trip_end',
                    'ts'        => $prev['_ts'],
                    'time'      => date('H:i', $prev['_ts']),
                    'lat'       => $prev['lat'],
                    'lng'       => $prev['lng'],
                    'start_lat' => $tripStart['lat'],
                    'start_lng' => $tripStart['lng'],
                    'end_lat'   => $prev['lat'],
                    'end_lng'   => $prev['lng'],
                    'duration'  => $tripTime,
                    'distance'  => round($tripDist / 1000, 1),
                    'label'     => 'Trayecto',
                    'route_points' => $routePoints,
                );
                $tripStart    = null;
                $tripStartIdx = null;
                $stopStart    = $prev;
                $state        = 'stopped';
            } elseif ($state !== 'stopped') {
                $stopStart = $prev;
                $state     = 'stopped';
            }
        }
        $prev = $p;
    }

    // Cerrar estado final
    if ($state === 'moving' && $tripStart !== null) {
        $tripDist = gps_distance_m($tripStart['lat'], $tripStart['lng'], $prev['lat'], $prev['lng']);
        $tripTime = ($prev['_ts'] - $tripStart['_ts']) / 60;

        // Extraer puntos intermedios
        $routePoints = array();
        for ($ri = $tripStartIdx; $ri < $count; $ri++) {
            $routePoints[] = array(
                'lat' => (float)$dayPositions[$ri]['lat'],
                'lng' => (float)$dayPositions[$ri]['lng'],
            );
        }

        $events[] = array(
            'type'      => 'trip_end',
            'ts'        => $prev['_ts'],
            'time'      => date('H:i', $prev['_ts']),
            'lat'       => $prev['lat'],
            'lng'       => $prev['lng'],
            'start_lat' => $tripStart['lat'],
            'start_lng' => $tripStart['lng'],
            'end_lat'   => $prev['lat'],
            'end_lng'   => $prev['lng'],
            'duration'  => $tripTime,
            'distance'  => round($tripDist / 1000, 1),
            'label'     => 'Trayecto',
            'route_points' => $routePoints,
        );
    }

    return $events;
}

/**
 * Genera URL de Google Maps para unas coordenadas.
 */
function gps_maps_url($lat, $lng) {
    return 'https://www.google.com/maps?q=' . $lat . ',' . $lng;
}

/**
 * Genera URL de Google Maps directions desde origen a destino.
 */
function gps_maps_directions_url($fromLat, $fromLng, $toLat, $toLng) {
    return 'https://www.google.com/maps/dir/' . $fromLat . ',' . $fromLng . '/' . $toLat . ',' . $toLng;
}

// ═══════════════════════════════════════════════════════════════════════════════
// Detección de lugares (Fase 2)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Detecta lugares frecuentes a partir de posiciones GPS usando clustering
 * online + puntuación por patrones horarios.
 *
 * @param array $positions  Array de posiciones (con _ts, lat, lng).
 * @param int   $min_points Mínimo de puntos para que un clúster sea considerado lugar.
 * @param int   $radius_m   Radio en metros para agrupar posiciones.
 * @return array            Lista de lugares con etiqueta, coordenadas, stats.
 */
function gps_detect_places($positions, $min_points = 3, $radius_m = 100) {
    if (count($positions) < $min_points) return array();

    // ── Paso 1: Clustering online (O(n × k)) ──
    $clusters = array(); // cada cluster: [points => [...], center_lat, center_lng]

    foreach ($positions as $p) {
        $assigned = false;
        foreach ($clusters as &$c) {
            $d = gps_distance_m($c['center_lat'], $c['center_lng'], $p['lat'], $p['lng']);
            if ($d < $radius_m) {
                $c['points'][] = $p;
                // Recalcular centroide como media
                $sumLat = 0; $sumLng = 0;
                foreach ($c['points'] as $cp) { $sumLat += $cp['lat']; $sumLng += $cp['lng']; }
                $n = count($c['points']);
                $c['center_lat'] = $sumLat / $n;
                $c['center_lng'] = $sumLng / $n;
                $assigned = true;
                break;
            }
        }
        unset($c);
        if (!$assigned) {
            $clusters[] = array(
                'points'     => array($p),
                'center_lat' => $p['lat'],
                'center_lng' => $p['lng'],
            );
        }
    }

    // ── Paso 2: Filtrar clústeres con pocos puntos ──
    $clusters = array_filter($clusters, function ($c) use ($min_points) {
        return count($c['points']) >= $min_points;
    });
    if (empty($clusters)) return array();

    // ── Paso 3: Calcular estadísticas por clúster ──
    $scored = array();
    foreach ($clusters as $c) {
        $pts = $c['points'];
        $totalMinutes = 0;
        $nightMinutes = 0;      // 00:00–06:00
        $weekendMinutes = 0;    // sábado + domingo
        $daytimeMinutes = 0;    // 08:00–18:00 lun–vie
        $days = array();
        $arrivalTimes = array(); // horas de llegada (primer punto de cada visita)

        // Ordenar por timestamp
        usort($pts, function ($a, $b) { return $a['_ts'] - $b['_ts']; });

        $prev = null;
        $inVisit = false;

        foreach ($pts as $p) {
            $h = (int)date('G', $p['_ts']);     // 0-23
            $w = (int)date('N', $p['_ts']);     // 1=lun … 7=dom
            $d = date('Y-m-d', $p['_ts']);
            $days[$d] = true;

            // Acumular minutos entre puntos consecutivos dentro del clúster
            if ($prev !== null) {
                $gap = ($p['_ts'] - $prev['_ts']) / 60;
                if ($gap > 0 && $gap < 120) { // ignorar gaps >2h (no es tiempo real en el lugar)
                    $totalMinutes += $gap;
                    if ($h >= 0 && $h < 6)  $nightMinutes += $gap;
                    if ($w >= 6)             $weekendMinutes += $gap;
                    if ($h >= 8 && $h < 18 && $w <= 5) $daytimeMinutes += $gap;

                    // Detectar llegadas: gap > 5min desde el punto anterior
                    if (!$inVisit && $gap > 5) {
                        $arrivalTimes[] = $h + ((int)date('i', $p['_ts']) / 60);
                        $inVisit = true;
                    }
                    if ($gap > 60) $inVisit = false; // break >1h = nueva visita
                }
            }
            $prev = $p;
        }

        // Regularidad horaria: desviación estándar de las horas de llegada
        $regularity = 0;
        if (count($arrivalTimes) >= 3) {
            $avg = array_sum($arrivalTimes) / count($arrivalTimes);
            $variance = 0;
            foreach ($arrivalTimes as $t) { $variance += pow($t - $avg, 2); }
            $stdDev = sqrt($variance / count($arrivalTimes));
            // Regularidad alta = desviación baja → puntuación alta
            // Si stdDev < 0.5h → muy regular, si >2h → nada regular
            $regularity = max(0, 10 - ($stdDev * 5));
        }

        $scored[] = array(
            'center_lat'      => round($c['center_lat'], 6),
            'center_lng'      => round($c['center_lng'], 6),
            'points'          => count($pts),
            'days'            => count($days),
            'total_hours'     => round($totalMinutes / 60, 1),
            'night_hours'     => round($nightMinutes / 60, 1),
            'weekend_hours'   => round($weekendMinutes / 60, 1),
            'daytime_hours'   => round($daytimeMinutes / 60, 1),
            'regularity'      => round($regularity, 1),
            'arrival_count'   => count($arrivalTimes),
            'last_ts'         => $prev ? $prev['_ts'] : time(),
            // Scores
            'score_casa'      => 0,
            'score_trabajo'   => 0,
            'label'           => '',
            'confidence'      => '',
        );
    }

    // ── Paso 4: Cargar nombres personalizados y ocultos ──
    $custom = gps_get_place_names();
    $hidden = gps_get_hidden_places();

    // ── Paso 5: Fuzzy matching (nombres → clústeres) ──
    // Iteramos NOMBRES, no clústeres. Cada nombre busca su clúster más cercano.
    // Así un nombre siempre cae en el clúster que realmente le corresponde
    // y el clúster B no puede "robar" el nombre del clúster A.
    $FUZZY_R = 100;  // mismo radio que el clustering
    $namedPlaces      = array();
    $assignedIndices  = array();  // índices de clústeres ya emparejados

    foreach ($custom as $key => $name) {
        if (trim($name) === '') continue;
        $parts = explode(',', $key);
        if (count($parts) !== 2) continue;
        $savedLat = (float)$parts[0];
        $savedLng = (float)$parts[1];

        // Buscar el clúster MÁS CERCANO a este nombre guardado
        $bestIdx  = -1;
        $bestDist = $FUZZY_R;
        foreach ($scored as $idx => $s) {
            if (isset($assignedIndices[$idx])) continue;
            $dist = gps_distance_m($savedLat, $savedLng, $s['center_lat'], $s['center_lng']);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $bestIdx  = $idx;
            }
        }

        if ($bestIdx >= 0) {
            $scored[$bestIdx]['label']       = $name;
            $scored[$bestIdx]['confidence']  = 'personalizada';
            $scored[$bestIdx]['score_casa']  = 0;
            $scored[$bestIdx]['score_trabajo'] = 0;
            $assignedIndices[$bestIdx] = true;
            $namedPlaces[] = $scored[$bestIdx];
        }
    }

    // Los no emparejados → pasan a auto-etiquetado
    $unlabeled      = array();
    $hasCustomCasa  = false;
    foreach ($scored as $idx => $s) {
        if (isset($assignedIndices[$idx])) {
            if ($s['label'] === 'Casa') $hasCustomCasa = true;
            continue;
        }
        $unlabeled[] = $s;
    }
    if (!empty($unlabeled)) {
        foreach ($unlabeled as &$s) {
            $s['score_casa']    = $s['night_hours'] * 3 + $s['weekend_hours'] * 2 + $s['total_hours'] * 0.5;
            $s['score_trabajo'] = $s['daytime_hours'] * 3 + $s['regularity'] * 2 + $s['total_hours'] * 0.3;
        }
        unset($s);

        if (!$hasCustomCasa) {
            // Determinar casa: mayor score_casa
            usort($unlabeled, function ($a, $b) { return $b['score_casa'] <=> $a['score_casa']; });
            $casaIdx = 0;
            $unlabeled[0]['label'] = '🏠 Casa';
        } else {
            usort($unlabeled, function ($a, $b) { return $b['total_hours'] <=> $a['total_hours']; });
            $casaIdx = -1; // no hay casa auto-detectada
        }

        // Determinar trabajo: mayor score_trabajo entre los restantes
        $bestTrabajoIdx = -1;
        $bestTrabajoScore = -1;
        foreach ($unlabeled as $i => $s) {
            if ($i === $casaIdx) continue;
            if ($s['score_trabajo'] > $bestTrabajoScore) {
                $bestTrabajoScore = $s['score_trabajo'];
                $bestTrabajoIdx = $i;
            }
        }

        if ($bestTrabajoIdx >= 0 && $unlabeled[$bestTrabajoIdx]['daytime_hours'] >= 3 && $unlabeled[$bestTrabajoIdx]['days'] >= 2) {
            $unlabeled[$bestTrabajoIdx]['label'] = '🏢 Trabajo';
        }

        // El resto: 📍 Lugar N
        $lugarN = 1;
        foreach ($unlabeled as $i => &$s) {
            if ($s['label'] === '') {
                $s['label'] = '📍 Lugar ' . $lugarN;
                $lugarN++;
            }
            if ($s['days'] >= 10 && $s['total_hours'] >= 50) {
                $s['confidence'] = 'alta';
            } elseif ($s['days'] >= 4 && $s['total_hours'] >= 10) {
                $s['confidence'] = 'media';
            } elseif ($s['days'] >= 2) {
                $s['confidence'] = 'baja';
            } else {
                $s['confidence'] = 'mínima';
            }
        }
        unset($s);
    }

    // ── Paso 7: Fusionar ──
    $scored = array_merge($namedPlaces, $unlabeled);

    // ── Paso 8: Filtrar lugares ocultos y obsoletos ──
    $now = time();
    $scored = array_filter($scored, function ($s) use ($now, $hidden, $FUZZY_R) {
        // Lugares con nombre personalizado: siempre se mantienen
        if ($s['confidence'] === 'personalizada') return true;
        // Filtrar lugares ocultos manualmente (fuzzy match)
        foreach ($hidden as $hkey => $dummy) {
            $parts = explode(',', $hkey);
            if (count($parts) !== 2) continue;
            $dist = gps_distance_m($s['center_lat'], $s['center_lng'], (float)$parts[0], (float)$parts[1]);
            if ($dist < $FUZZY_R) return false; // oculto por el usuario
        }
        // Recencia
        $daysAgo = round(($now - $s['last_ts']) / 86400, 1);
        if ($s['days'] <= 2 && $daysAgo > 14) return false;
        if ($daysAgo > 60) return false;
        return true;
    });

    // Reordenar por total_hours descendente
    usort($scored, function ($a, $b) { return $b['total_hours'] <=> $a['total_hours']; });

    return $scored;
}

/**
 * Busca el lugar más cercano a unas coordenadas (para enriquecer timeline).
 * @return array|null  El lugar o null si no hay ninguno a <200m.
 */
function gps_match_position_to_place($lat, $lng, $places, $max_distance_m = 200) {
    $best = null;
    $bestDist = $max_distance_m;
    foreach ($places as $place) {
        $d = gps_distance_m($lat, $lng, $place['center_lat'], $place['center_lng']);
        if ($d < $bestDist) {
            $bestDist = $d;
            $best = $place;
        }
    }
    return $best;
}

/**
 * Carga nombres personalizados de lugares desde settings.json.
 * @return array  [ '39.833,-0.192' => 'Casa', ... ]
 */
function gps_get_place_names() {
    $settings = storage_read('settings.json');
    return isset($settings['rutas_place_names']) && is_array($settings['rutas_place_names'])
        ? $settings['rutas_place_names']
        : array();
}

/**
 * Carga lugares ocultos manualmente desde settings.json.
 * @return array  [ '39.833,-0.192' => true, ... ]
 */
function gps_get_hidden_places() {
    $settings = storage_read('settings.json');
    return isset($settings['rutas_hidden_places']) && is_array($settings['rutas_hidden_places'])
        ? $settings['rutas_hidden_places']
        : array();
}

/**
 * Calcula cuántos días distintos de datos hay para un usuario concreto.
 */
function gps_days_for_user($positions, $user = null) {
    $days = array();
    foreach ($positions as $p) {
        if ($user !== null && ($p['user'] ?? '') !== $user) continue;
        $days[date('Y-m-d', $p['_ts'])] = true;
    }
    return count($days);
}

// ═══════════════════════════════════════════════════════════════════════════════
// Curiosidades y estadísticas (Fase 3)
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Calcula rachas: días consecutivos yendo a un lugar (ej: trabajo).
 * @return array  [ 'current' => N, 'longest' => N, 'place' => '🏢 Trabajo' ]
 */
function gps_calculate_streaks($positions, $places) {
    if (empty($places) || empty($positions)) {
        return array('current' => 0, 'longest' => 0, 'place' => null);
    }

    // Buscar el lugar "Trabajo" (o el segundo lugar si no hay etiquetado como trabajo)
    $workPlace = null;
    foreach ($places as $p) {
        if (strpos($p['label'], 'Trabajo') !== false || strpos($p['label'], '🏢') !== false) {
            $workPlace = $p;
            break;
        }
    }
    if (!$workPlace && count($places) >= 2) {
        $workPlace = $places[1]; // segundo lugar más frecuente = probable trabajo
    }
    if (!$workPlace) {
        return array('current' => 0, 'longest' => 0, 'place' => null);
    }

    // Días en los que el coche estuvo en ese lugar
    $grouped = gps_group_by_day($positions);
    $daysAtPlace = array();
    foreach ($grouped as $date => $dayPositions) {
        foreach ($dayPositions as $p) {
            $d = gps_distance_m($p['lat'], $p['lng'], $workPlace['center_lat'], $workPlace['center_lng']);
            if ($d < 200) {
                $daysAtPlace[$date] = true;
                break;
            }
        }
    }

    $dates = array_keys($daysAtPlace);
    sort($dates);
    if (empty($dates)) return array('current' => 0, 'longest' => 0, 'place' => $workPlace['label']);

    // Calcular rachas
    $current = 0;
    $longest = 0;
    $streak = 1;
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    for ($i = 1; $i < count($dates); $i++) {
        $prev = $dates[$i - 1];
        $curr = $dates[$i];
        $expected = date('Y-m-d', strtotime($prev . ' +1 day'));

        if ($curr === $expected) {
            $streak++;
        } else {
            $longest = max($longest, $streak);
            $streak = 1;
        }
    }
    $longest = max($longest, $streak);

    // Racha actual: ¿el último día con visita fue ayer u hoy?
    $lastDate = end($dates);
    if ($lastDate === $today || $lastDate === $yesterday) {
        // Contar hacia atrás desde el último día
        $current = 1;
        for ($i = count($dates) - 2; $i >= 0; $i--) {
            $expected = date('Y-m-d', strtotime($dates[$i + 1] . ' -1 day'));
            if ($dates[$i] === $expected) {
                $current++;
            } else {
                break;
            }
        }
    }

    return array(
        'current' => $current,
        'longest' => $longest,
        'place'   => $workPlace['label'],
    );
}

/**
 * Comparativa mensual: km de este mes vs mes pasado.
 * @return array  [ 'this_month_km' => float, 'last_month_km' => float, 'pct_change' => float|null ]
 */
function gps_monthly_comparison($positions) {
    $now         = time();
    $thisMonth   = date('Y-m', $now);
    $lastMonth   = date('Y-m', strtotime('-1 month', $now));

    $thisMonthPos = array();
    $lastMonthPos = array();

    foreach ($positions as $p) {
        $ym = date('Y-m', $p['_ts']);
        if ($ym === $thisMonth) {
            $thisMonthPos[] = $p;
        } elseif ($ym === $lastMonth) {
            $lastMonthPos[] = $p;
        }
    }

    $thisKm   = gps_route_total_km($thisMonthPos);
    $lastKm   = gps_route_total_km($lastMonthPos);
    $pct      = $lastKm > 0 ? round((($thisKm - $lastKm) / $lastKm) * 100) : null;

    $thisTrips = gps_count_trips($thisMonthPos);
    $lastTrips = gps_count_trips($lastMonthPos);

    return array(
        'this_month_km'    => $thisKm,
        'last_month_km'    => $lastKm,
        'pct_change'       => $pct,
        'this_month_trips' => $thisTrips,
        'last_month_trips' => $lastTrips,
        'this_month_days'  => count(gps_group_by_day($thisMonthPos)),
        'last_month_days'  => count(gps_group_by_day($lastMonthPos)),
    );
}

/**
 * Detecta las horas punta de llegada/salida a un lugar.
 * @return array  [ 'avg_arrival' => '08:15', 'avg_departure' => '13:42', 'place' => '🏢 Trabajo' ]
 */
function gps_peak_hours($positions, $places) {
    if (empty($places) || count($places) < 2) return null;

    // Usar el lugar #2 (probable trabajo)
    $workPlace = null;
    foreach ($places as $p) {
        if (strpos($p['label'], 'Trabajo') !== false || strpos($p['label'], '🏢') !== false) {
            $workPlace = $p;
            break;
        }
    }
    if (!$workPlace && count($places) >= 2) $workPlace = $places[1];
    if (!$workPlace) return null;

    $grouped = gps_group_by_day($positions);
    $arrivals   = array(); // minutos desde medianoche de cada llegada
    $departures = array();

    foreach ($grouped as $date => $dayPositions) {
        $inPlace    = false;
        $arrivedAt  = null;

        for ($i = 1; $i < count($dayPositions); $i++) {
            $prev = $dayPositions[$i - 1];
            $curr = $dayPositions[$i];

            $dPrev = gps_distance_m($prev['lat'], $prev['lng'], $workPlace['center_lat'], $workPlace['center_lng']);
            $dCurr = gps_distance_m($curr['lat'], $curr['lng'], $workPlace['center_lat'], $workPlace['center_lng']);

            if ($dCurr < 200 && !$inPlace) {
                // Llegada
                $h = (int)date('G', $curr['_ts']);
                $m = (int)date('i', $curr['_ts']);
                $arrivals[] = $h * 60 + $m;
                $inPlace = true;
                $arrivedAt = $curr['_ts'];
            } elseif ($dCurr >= 200 && $inPlace) {
                // Salida
                if ($arrivedAt !== null) {
                    $h = (int)date('G', $prev['_ts']);
                    $m = (int)date('i', $prev['_ts']);
                    $departures[] = $h * 60 + $m;
                }
                $inPlace = false;
                $arrivedAt = null;
            }
        }
    }

    if (count($arrivals) < 2 || count($departures) < 2) return null;

    $avgArrival   = round(array_sum($arrivals) / count($arrivals));
    $avgDeparture = round(array_sum($departures) / count($departures));

    return array(
        'avg_arrival'    => sprintf('%02d:%02d', floor($avgArrival / 60), $avgArrival % 60),
        'avg_departure'  => sprintf('%02d:%02d', floor($avgDeparture / 60), $avgDeparture % 60),
        'arrival_range'  => sprintf('%02d:%02d–%02d:%02d',
            floor(min($arrivals) / 60), min($arrivals) % 60,
            floor(max($arrivals) / 60), max($arrivals) % 60),
        'departure_range'=> sprintf('%02d:%02d–%02d:%02d',
            floor(min($departures) / 60), min($departures) % 60,
            floor(max($departures) / 60), max($departures) % 60),
        'sample_size'    => count($arrivals),
        'place'          => $workPlace['label'],
    );
}

/**
 * Estadísticas generales de curiosidades.
 */
function gps_curiosities($positions, $places) {
    $streaks     = gps_calculate_streaks($positions, $places);
    $comparison  = gps_monthly_comparison($positions);
    $peakHours   = gps_peak_hours($positions, $places);

    // Dato curioso: día con más km
    $maxKmDay = null;
    $grouped = gps_group_by_day($positions);
    foreach ($grouped as $date => $dayPositions) {
        $km = gps_route_total_km($dayPositions);
        if ($maxKmDay === null || $km > $maxKmDay['km']) {
            $maxKmDay = array('date' => $date, 'km' => $km);
        }
    }

    // Dato curioso: porcentaje de tiempo en cada lugar
    $totalHours = 0;
    $placeHours = array();
    if (!empty($places)) {
        foreach ($places as $pl) {
            $totalHours += $pl['total_hours'];
        }

        // Tiempo conduciendo = tiempo total entre primer y última posición - tiempo en lugares
        $firstTs = $positions[0]['_ts'] ?? 0;
        $lastTs  = end($positions)['_ts'] ?? 0;
        $totalSpanHours = ($lastTs - $firstTs) / 3600;
        $drivingHours = max(0, $totalSpanHours - $totalHours);

        foreach ($places as $pl) {
            $pct = $totalSpanHours > 0 ? round(($pl['total_hours'] / $totalSpanHours) * 100) : 0;
            $placeHours[] = array(
                'label'     => $pl['label'],
                'hours'     => $pl['total_hours'],
                'pct'       => $pct,
            );
        }
        if ($drivingHours > 0.5) {
            $placeHours[] = array(
                'label' => '🚗 Conduciendo',
                'hours' => round($drivingHours, 1),
                'pct'   => $totalSpanHours > 0 ? round(($drivingHours / $totalSpanHours) * 100) : 0,
            );
        }
    }

    return array(
        'streaks'      => $streaks,
        'comparison'   => $comparison,
        'peak_hours'   => $peakHours,
        'max_km_day'   => $maxKmDay,
        'place_hours'  => $placeHours,
        'total_days'   => count($grouped),
    );
}
