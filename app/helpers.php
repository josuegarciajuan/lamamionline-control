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
    return array(
        'minifalda_top_ceñido' => 'minifalda vaquera barata muy corta con top ceñido de tirantes finos que marca el pecho, cintura al aire — sexy de barrio, provocador sin ser explícito',
        'vestido_corto_escote' => 'vestido corto de punto barato muy ceñido con escote en V profundo pero sin mostrar sujetador, por encima de la rodilla, poliéster de mercadillo — elegante y sexy',
        'shorts_ceñidos_top' => 'shorts vaqueros muy cortos y ceñidos con camiseta de tirantes ajustada y fina, ombros al aire, look veraniego provocador de tienda barata',
        'leggings_top_largo' => 'leggings negros muy ceñidos que marcan toda la silueta, con top largo de licra ajustado — look deportivo sexy de gimnasio low-cost',
        'body_vaqueros_rotos' => 'body escotado de licra barata muy ceñido con vaqueros ajustados rotos en los muslos, look de discoteca económico y muy llamativo',
        'vestido_lencero_falso' => 'vestido corto imitación satén con tirantes finos y escote en pico, ceñido al cuerpo — parece lencero pero es un VESTIDO real, NO ropa interior',
        'mono_escotado_ceñido' => 'mono corto barato con escote pronunciado en V, sin mangas, tela elástica de poliéster muy ceñido al cuerpo — una sola pieza ultra favorecedora',
        'falda_tubo_top' => 'falda de tubo de imitación cuero barata muy ceñida con top corto de licra que deja ver la cintura, look de noche económico y provocador',
        'conjunto_deportivo_abierto' => 'conjunto de chándal barato con chaqueta abierta sin nada debajo y pantalón muy ceñido de cintura baja — look sporty sexy de barrio',
        'vestido_transparencia_controlada' => 'vestido corto ceñido con mangas translúcidas de gasa barata pero cuerpo opaco — insinuante sin enseñar de más, elegante low-cost',
        'falda_cruzada_top' => 'falda cruzada corta barata con abertura lateral y top halter ajustado de licra que realza el escote — sexy de noche económico',
        'camiseta_mojada_falsa' => 'camiseta blanca ceñida de algodón fino ligeramente húmeda marcando la silueta con vaqueros cortos — efecto mojado sensual sin ser explícito',
        'vestido_escote_espalda' => 'vestido corto barato con escote en la espalda, ceñido al cuerpo, sin mangas — elegante y muy sexy de fiesta low-cost',
        'top_palabra_honor_falda' => 'top palabra de honor ajustado sin tirantes con minifalda de tubo barata — hombros y escote al aire, muy favorecedor',
        'body_transparente_parcial' => 'body de manga larga con paneles de gasa translúcida en mangas y costados pero opaco en zonas íntimas — sugerente sin enseñar',
        'vestido_punto_ceñido' => 'vestido de punto barato muy ceñido tipo venda, escote redondo, largo midi ajustado que marca toda la silueta — look de alfombra roja low-cost',
    );
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
        $picked[] = array(
            'key' => $key,
            'description' => $pool[$key],
        );
        unset($available[$idx]);
        $available = array_values($available);
    }
    return $picked;
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
        'neutro'          => 'Fondo neutro (gris liso, para editar después)',
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
    $out['operator_brief'] = trim((string)($raw['operator_brief'] ?? ''));
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
    return array(
        'id' => 'dashboard_external_bot',
        'nombre_bot' => 'Casawasap externo',
        'bot_mode_path' => '/srv/n8n_data/.bot_mode',
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
