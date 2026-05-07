<?php

function voice_supported_stages() {
    return array(
        'interpreted',
        'resolved',
        'executed',
        'needs_confirmation',
        'needs_clarification',
        'error',
    );
}

function voice_command_catalog() {
    return array(
        'navigation' => array(
            'open_page' => array('label' => 'Abrir página principal'),
            'open_tab' => array('label' => 'Abrir subsección o pestaña'),
            'open_edit_view' => array('label' => 'Abrir ficha concreta'),
            'go_dashboard' => array('label' => 'Ir al dashboard'),
            'go_back' => array('label' => 'Volver a la pantalla anterior'),
            'open_lamamibot' => array('label' => 'Abrir LaMamiBot'),
            'open_bots' => array('label' => 'Abrir panel de bots'),
            'open_avisos' => array('label' => 'Abrir avisos'),
            'open_agenda' => array('label' => 'Abrir agenda de Josué'),
        ),
        'entities' => array(
            'open_entity' => array('label' => 'Abrir entidad genérica'),
            'search_entity' => array('label' => 'Buscar entidad concreta'),
            'list_entities' => array('label' => 'Abrir listado de entidades'),
            'open_entity_by_phone' => array('label' => 'Abrir entidad por teléfono'),
            'open_entity_by_name' => array('label' => 'Abrir entidad por nombre'),
            'show_recent_entities' => array('label' => 'Mostrar entidades recientes'),
        ),
        'reports' => array(
            'open_informes' => array('label' => 'Abrir informes'),
            'filter_informes' => array('label' => 'Filtrar informes'),
            'show_stats_clienta' => array('label' => 'Mostrar estadísticas de clienta'),
            'show_stats_rama' => array('label' => 'Mostrar estadísticas por rama'),
            'show_gastos_periodo' => array('label' => 'Mostrar gastos por periodo'),
            'show_ingresos_periodo' => array('label' => 'Mostrar ingresos por periodo'),
            'show_gridmensual' => array('label' => 'Abrir grid mensual'),
            'query_analytics' => array('label' => 'Consulta analítica preparada para futuro'),
            'query_comparison' => array('label' => 'Consulta comparativa preparada para futuro'),
        ),
        'lamami' => array(
            'create_interesada' => array('label' => 'Crear interesada'),
            'edit_interesada' => array('label' => 'Editar interesada'),
            'set_interesada_estado' => array('label' => 'Cambiar estado de interesada'),
            'convert_interesada_to_clienta' => array('label' => 'Convertir interesada en clienta'),
            'create_clienta' => array('label' => 'Crear clienta'),
            'edit_clienta' => array('label' => 'Editar clienta'),
            'alta_clienta' => array('label' => 'Dar de alta clienta'),
            'baja_clienta' => array('label' => 'Dar de baja clienta'),
            'create_quick_lead' => array('label' => 'Crear lead rápido'),
        ),
        'casawasap' => array(
            'create_casawasap_contacto' => array('label' => 'Crear contacto de Casawasap'),
            'edit_casawasap_contacto' => array('label' => 'Editar contacto de Casawasap'),
            'convert_casawasap_cliente' => array('label' => 'Convertir contacto en cliente'),
            'set_casawasap_estado' => array('label' => 'Cambiar estado de cliente Casawasap'),
            'alta_casawasap_cliente' => array('label' => 'Alta de cliente Casawasap'),
            'baja_casawasap_cliente' => array('label' => 'Baja de cliente Casawasap'),
            'add_casawasap_pago' => array('label' => 'Registrar pago Casawasap'),
        ),
        'jostal' => array(
            'create_jostal_interesada' => array('label' => 'Crear interesada Jostal'),
            'convert_jostal_clienta' => array('label' => 'Convertir interesada Jostal'),
            'create_jostal_clienta' => array('label' => 'Crear clienta Jostal'),
            'add_jostal_lead' => array('label' => 'Añadir lead Jostal'),
            'add_jostal_venta' => array('label' => 'Añadir venta Jostal'),
            'jostal_salida_casa' => array('label' => 'Marcar salida de casa Jostal'),
            'jostal_reactivar_casa' => array('label' => 'Reactivar casa Jostal'),
        ),
        'gastos' => array(
            'add_gasto' => array('label' => 'Añadir gasto'),
            'delete_gasto_request' => array('label' => 'Solicitar borrado de gasto'),
        ),
        'agenda' => array(
            'create_agenda_contact' => array('label' => 'Crear contacto de agenda'),
            'edit_agenda_contact' => array('label' => 'Editar contacto de agenda'),
            'delete_agenda_request' => array('label' => 'Solicitar borrado de agenda'),
        ),
        'josue' => array(
            'create_eureka' => array('label' => 'Crear eureka'),
        ),
        'bots' => array(
            'set_bot_runtime_mode' => array('label' => 'Cambiar runtime de bot'),
            'set_lamamibot_runtime_mode' => array('label' => 'Cambiar runtime de LaMamiBot'),
            'save_lamamibot_config' => array('label' => 'Guardar configuración de LaMamiBot'),
            'generate_lamamibot_assets' => array('label' => 'Generar assets de LaMamiBot'),
        ),
        'system' => array(
            'ask_clarification' => array('label' => 'Solicitar aclaración'),
            'confirm_pending_action' => array('label' => 'Confirmar acción pendiente'),
            'cancel_pending_action' => array('label' => 'Cancelar acción pendiente'),
            'unsupported_command' => array('label' => 'Orden no soportada todavía'),
        ),
    );
}

function voice_catalog_flat() {
    static $flat = null;
    if ($flat !== null) return $flat;

    $flat = array();
    foreach (voice_command_catalog() as $group => $items) {
        foreach ($items as $intent => $meta) {
            $meta['group'] = $group;
            $flat[$intent] = $meta;
        }
    }

    return $flat;
}

function voice_intent_exists($intent) {
    $flat = voice_catalog_flat();
    return isset($flat[$intent]);
}

function voice_intent_label($intent) {
    $flat = voice_catalog_flat();
    return isset($flat[$intent]['label']) ? $flat[$intent]['label'] : $intent;
}

function voice_normalize_text($text) {
    $text = trim((string)$text);
    $text = str_replace(array("\r\n", "\r"), "\n", $text);
    if (function_exists('mb_strtolower')) {
        $text = mb_strtolower($text, 'UTF-8');
    } else {
        $text = strtolower($text);
    }

    $replacements = array(
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n',
    );
    $text = strtr($text, $replacements);
    $text = preg_replace('/\s+/', ' ', $text);
    return trim((string)$text);
}

function voice_sanitize_speech_noise($text) {
    $text = trim((string)$text);
    if ($text === '') return '';

    $text = preg_replace('/\s+/', ' ', $text);
    $tokens = preg_split('/\s+/u', $text);
    $out = array();

    foreach ($tokens as $token) {
        $token = trim((string)$token);
        if ($token === '') continue;

        $last = end($out);
        if ($last !== false && voice_normalize_text($last) === voice_normalize_text($token)) {
            continue;
        }

        $out[] = $token;
    }

    return trim(implode(' ', $out));
}

function voice_sanitize_speech_meta($speechMeta) {
    $speechMeta = is_array($speechMeta) ? $speechMeta : array();
    $speechMeta['source'] = trim((string)($speechMeta['source'] ?? ''));
    $alternatives = is_array($speechMeta['alternatives'] ?? null) ? $speechMeta['alternatives'] : array();
    $clean = array();

    foreach ($alternatives as $item) {
        $item = voice_sanitize_speech_noise($item);
        if ($item !== '' && !in_array($item, $clean, true)) {
            $clean[] = $item;
        }
    }

    $speechMeta['alternatives'] = $clean;
    return $speechMeta;
}

function voice_clean_context($context) {
    $allowed = array(
        'page', 'tab', 'edit', 'view', 'convert', 'avtab',
        'from', 'to', 'rama', 'tipo', 'cliente_id', 'dashboard_month',
        'query_string', 'request_uri',
    );

    $clean = array();
    if (!is_array($context)) return $clean;

    foreach ($allowed as $key) {
        if (isset($context[$key])) {
            $clean[$key] = trim((string)$context[$key]);
        }
    }

    return $clean;
}

function voice_build_response($data = array()) {
    $defaults = array(
        'ok' => true,
        'stage' => 'interpreted',
        'transcript' => '',
        'normalized_text' => '',
        'intent' => 'unsupported_command',
        'intent_label' => voice_intent_label('unsupported_command'),
        'params' => array(),
        'missing_fields' => array(),
        'context' => array(),
        'message' => '',
        'redirect_url' => '',
        'options' => array(),
        'resolved_entities' => array(),
        'confirmation_required' => false,
        'errors' => array(),
        'catalog_version' => 'voice_v3',
        'pipeline' => array(
            'interpret' => null,
            'resolve' => null,
            'validate' => null,
            'execute' => null,
        ),
        'ai' => array(),
        'execution_mode' => 'preview',
        'pending' => array(),
        'analytics' => array(),
        'ux' => array(
            'headline' => '',
            'detail' => '',
            'suggested_reply' => '',
            'review_required' => false,
        ),
        'log_id' => '',
    );

    $payload = array_replace_recursive($defaults, $data);

    if (!in_array($payload['stage'], voice_supported_stages(), true)) {
        $payload['stage'] = 'error';
        $payload['ok'] = false;
        $payload['errors'][] = 'invalid_stage';
    }

    if (!voice_intent_exists($payload['intent'])) {
        $payload['intent'] = 'unsupported_command';
    }

    $payload['intent_label'] = voice_intent_label($payload['intent']);
    $payload['context'] = voice_clean_context($payload['context']);

    foreach (array('params', 'missing_fields', 'options', 'errors', 'resolved_entities', 'ai', 'pending', 'analytics', 'ux') as $key) {
        if (!is_array($payload[$key])) $payload[$key] = array();
    }

    if (!isset($payload['pending']['token'])) {
        $payload['pending'] = array();
    }

    if (!isset($payload['ux']['headline']) || trim((string)$payload['ux']['headline']) === '') {
        $payload['ux']['headline'] = $payload['intent_label'];
    }
    if (!isset($payload['ux']['detail']) || trim((string)$payload['ux']['detail']) === '') {
        $payload['ux']['detail'] = trim((string)$payload['message']);
    }

    return $payload;
}


function voice_json_response($payload, $statusCode = 200) {
    if (!headers_sent()) {
        http_response_code((int)$statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function voice_safe_substr($value, $start, $length = null) {
    $value = (string)$value;
    if (function_exists('mb_substr')) {
        return $length === null ? mb_substr($value, $start, null, 'UTF-8') : mb_substr($value, $start, $length, 'UTF-8');
    }
    return $length === null ? substr($value, $start) : substr($value, $start, $length);
}

function voice_contains($haystack, $needle) {
    return strpos((string)$haystack, (string)$needle) !== false;
}

function voice_starts_with($haystack, $needle) {
    return strncmp((string)$haystack, (string)$needle, strlen((string)$needle)) === 0;
}

function voice_extract_phone($text) {
    $digits = preg_replace('/\D+/', '', (string)$text);
    if (preg_match('/(6\d{8}|7\d{8}|8\d{8}|9\d{8})/', $digits, $m)) {
        return $m[1];
    }
    return '';
}

function voice_extract_amount($normalizedText) {
    if (preg_match('/(\d+(?:[\.,]\d{1,2})?)\s*(?:e|euros?)/u', (string)$normalizedText, $m)) {
        return str_replace(',', '.', $m[1]);
    }
    if (preg_match('/(?:de|por)\s+(\d+(?:[\.,]\d{1,2})?)/u', (string)$normalizedText, $m)) {
        return str_replace(',', '.', $m[1]);
    }
    return '';
}

function voice_extract_name_hint($text) {
    $patterns = array(
        '/(?:nombre|llamado|llamada|que se llama)\s+([a-zA-ZÁÉÍÓÚÜÑáéíóúüñ0-9\-\s]{2,})/u',
        '/(?:a|para)\s+([a-zA-ZÁÉÍÓÚÜÑáéíóúüñ0-9\-\s]{2,})\s+(?:con|telefono|teléfono)/u',
    );

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, (string)$text, $m)) {
            $name = trim((string)$m[1]);
            $name = preg_replace('/\s+/', ' ', $name);
            return trim((string)$name);
        }
    }

    return '';
}


function voice_normalize_phone($value) {
    $digits = preg_replace('/\D+/', '', (string)$value);
    if ($digits === '') return '';
    if (voice_starts_with($digits, '34') && strlen($digits) > 9) {
        $digits = substr($digits, 2);
    }
    if (strlen($digits) > 9) {
        $digits = substr($digits, -9);
    }
    return $digits;
}

function voice_contains_any($haystack, $needles) {
    foreach ((array)$needles as $needle) {
        if ($needle !== '' && voice_contains($haystack, (string)$needle)) {
            return true;
        }
    }
    return false;
}

function voice_plural_hint_from_text($normalizedText) {
    return voice_contains_any($normalizedText, array('interesadas', 'clientas', 'contactos', 'bots', 'gastos', 'listado', 'lista', 'todas', 'todos', 'ultimas', 'ultimos'));
}

function voice_scope_aliases() {
    return array(
        'lamami' => array('lamami', 'la mami'),
        'jostal' => array('jostal', 'hostal', 'yostal', 'josta'),
        'casawasap' => array('casawasap', 'casa wasap', 'casa whatsapp', 'casa guasap', 'wasap'),
        'josue' => array('josue', 'agenda'),
        'bots' => array('bot', 'bots', 'panel de bots'),
        'lamamibot' => array('lamamibot', 'la mamibot', 'mami bot'),
        'informes' => array('informes', 'informe', 'estadisticas', 'estadistica', 'grid mensual', 'gridmensual'),
        'avisos' => array('avisos', 'alertas'),
        'gastos' => array('gastos', 'gasto'),
    );
}

function voice_normalize_scope($value, $targetType = '') {
    $value = voice_normalize_text($value);
    if ($value === '') return '';
    foreach (voice_scope_aliases() as $scope => $aliases) {
        foreach ($aliases as $alias) {
            if ($value === voice_normalize_text($alias)) return $scope;
        }
    }
    if ($value === 'casa wasap') return 'casawasap';
    if ($value === 'mami') return 'lamami';
    return $value;
}

function voice_default_scope_for_target($targetType, $context = array()) {
    $page = trim((string)($context['page'] ?? ''));
    $tab = trim((string)($context['tab'] ?? ''));
    if ($page === 'jostal') return 'jostal';
    if ($page === 'casawasap') return 'casawasap';
    if ($page === 'bots') return 'bots';
    if ($page === 'josue' && $tab === 'agenda') return 'josue';
    if ($page === 'lamamibot' || ($page === 'lamami' && $tab === 'lamamibot')) return 'lamamibot';
    if ($page === 'lamami' || $page === 'clientas' || $page === 'interesadas') return 'lamami';
    if ($targetType === 'agenda_contact') return 'josue';
    if ($targetType === 'casawasap_contacto') return 'casawasap';
    if ($targetType === 'bot') return 'bots';
    if ($targetType === 'lamamibot') return 'lamamibot';
    return '';
}

function voice_extract_scope_from_text($normalizedText, $context = array(), $targetType = '') {
    foreach (voice_scope_aliases() as $scope => $aliases) {
        if (voice_contains_any($normalizedText, $aliases)) {
            return $scope;
        }
    }
    return voice_default_scope_for_target($targetType, $context);
}

function voice_target_type_aliases() {
    return array(
        'interesada' => array('interesada', 'interesadas', 'lead', 'leads', 'interesado', 'interesados'),
        'clienta' => array('clienta', 'clientas', 'cliente', 'clientes', 'chica', 'chicas'),
        'casawasap_contacto' => array('contacto casawasap', 'cliente casawasap', 'contacto de casawasap', 'contacto casa wasap'),
        'agenda_contact' => array('contacto agenda', 'agenda', 'proveedor', 'proveedores', 'telefono agenda'),
        'bot' => array('bot', 'bots'),
        'lamamibot' => array('lamamibot', 'la mamibot', 'mami bot'),
    );
}

function voice_detect_target_type($normalizedText, $context = array(), $scope = '') {
    if (voice_contains_any($normalizedText, array('lamamibot', 'la mamibot', 'mami bot'))) {
        return 'lamamibot';
    }
    if (voice_contains_any($normalizedText, array('agenda')) && !voice_contains_any($normalizedText, array('lamamibot', 'estadisticas'))) {
        return 'agenda_contact';
    }
    if ($scope === 'casawasap' && voice_contains_any($normalizedText, array('contacto', 'cliente', 'telefono', 'numero', 'movil'))) {
        return 'casawasap_contacto';
    }
    if ($scope === 'josue' && voice_contains_any($normalizedText, array('contacto', 'agenda', 'telefono', 'numero', 'proveedor'))) {
        return 'agenda_contact';
    }
    if ($scope === 'bots' && voice_contains_any($normalizedText, array('bot', 'bots'))) {
        return 'bot';
    }
    foreach (voice_target_type_aliases() as $targetType => $aliases) {
        if (voice_contains_any($normalizedText, $aliases)) {
            return $targetType;
        }
    }
    $page = trim((string)($context['page'] ?? ''));
    $tab = trim((string)($context['tab'] ?? ''));
    if ($page === 'casawasap') return 'casawasap_contacto';
    if ($page === 'bots') return 'bot';
    if ($page === 'josue' && $tab === 'agenda') return 'agenda_contact';
    if ($page === 'jostal' && $tab === 'clientas') return 'clienta';
    if ($page === 'jostal' && $tab === 'interesadas') return 'interesada';
    if (($page === 'lamami' || $page === 'clientas') && $tab === 'clientas') return 'clienta';
    if (($page === 'lamami' || $page === 'interesadas') && $tab === 'interesadas') return 'interesada';
    return 'none';
}

function voice_extract_estado_from_text($normalizedText) {
    if (voice_contains($normalizedText, 'en casa')) return 'en_casa';
    if (voice_contains_any($normalizedText, array('activas', 'activos', 'alta', 'altas'))) return 'alta';
    if (voice_contains_any($normalizedText, array('baja', 'bajas'))) return 'baja';
    if (voice_contains_any($normalizedText, array('nueva', 'nuevas'))) return 'nueva';
    if (voice_contains_any($normalizedText, array('atendida', 'atendidas'))) return 'atendida';
    if (voice_contains_any($normalizedText, array('convertida', 'convertidas'))) return 'convertida';
    if (voice_contains_any($normalizedText, array('descartada', 'descartadas', 'historico', 'historico'))) return 'descartada';
    return '';
}

function voice_is_current_entity_reference($normalizedText) {
    return voice_contains_any($normalizedText, array('esta clienta', 'esta interesada', 'este contacto', 'esta ficha', 'este bot', 'actual', 'la actual', 'el actual'));
}

function voice_detect_action_kind($normalizedText) {
    if (voice_contains_any($normalizedText, array('anade', 'agrega', 'crea', 'registra', 'mete', 'guarda'))) return 'create';
    if (voice_contains_any($normalizedText, array('apaga', 'enciende', 'activa', 'desactiva'))) return 'set';
    if (voice_contains_any($normalizedText, array('compara', 'comparar', 'analiza', 'insights', 'balance', 'resumen'))) return 'analytics';
    if (voice_contains_any($normalizedText, array('lista', 'listado', 'todas', 'todos', 'ultimas', 'ultimos'))) return 'list';
    if (voice_contains_any($normalizedText, array('busca', 'buscar', 'localiza', 'encuentra'))) return 'search';
    if (voice_contains_any($normalizedText, array('abre', 'abrir', 'entra', 'entrar', 've', 'ir', 'muestra', 'mostrar', 'ver', 'ensename'))) return 'open';
    return 'unknown';
}

function voice_wants_new_form($normalizedText) {
    return voice_contains_any($normalizedText, array('form', 'formulario', 'nuevo', 'nueva', 'crear', 'crea', 'anadir', 'agregar', 'alta'));
}

function voice_lookup_bundle($field = '', $value = '', $mode = 'exact') {
    return array(
        'field' => trim((string)$field),
        'value' => trim((string)$value),
        'mode' => trim((string)$mode) !== '' ? trim((string)$mode) : 'exact',
        'query' => trim((string)$value),
    );
}

function voice_clean_entity_query_text($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    $value = preg_replace('/\b(?:de|del|la|el|los|las|para|en|con|que|se|llama|nombre|telefono|numero|movil)\b/iu', ' ', $value);
    $value = preg_replace('/\b(?:jostal|lamami|casawasap|casa wasap|josue|agenda|bot|bots|interesada|interesadas|clienta|clientas|contacto|contactos)\b/iu', ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim((string)$value);
}

function voice_extract_entity_lookup($rawText, $normalizedText, $targetType = 'none') {
    $phone = voice_extract_phone($rawText);
    if ($phone !== '') {
        return voice_lookup_bundle('telefono', voice_normalize_phone($phone), 'exact');
    }

    if (preg_match('/\bid\s+([a-zA-Z0-9_\-]+)/u', $rawText, $m)) {
        return voice_lookup_bundle('id', trim((string)$m[1]), 'exact');
    }

    $name = voice_extract_name_hint($rawText);
    if ($name !== '') {
        return voice_lookup_bundle('nombre', voice_clean_entity_query_text($name), 'partial');
    }

    $patterns = array(
        '/(?:interesada|interesadas|clienta|clientas|contacto|contactos|bot|bots)\s+(?:de\s+[a-zA-ZÁÉÍÓÚÜÑáéíóúüñ]+\s+)?(.+)$/u',
        '/(?:ver|abre|abrir|muestra|mostrar|busca|buscar|localiza|ensename)\s+(.+)$/u',
    );
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $rawText, $m)) {
            $candidate = trim((string)$m[1]);
            $candidate = preg_replace('/\bcon\s+(?:el\s+)?(?:telefono|numero|movil).+$/iu', '', $candidate);
            $candidate = voice_clean_entity_query_text($candidate);
            if ($candidate !== '') {
                return voice_lookup_bundle('nombre', $candidate, 'partial');
            }
        }
    }

    return voice_lookup_bundle('', '', 'exact');
}

function voice_apply_lookup_to_params(&$params, $lookup) {
    $field = trim((string)($lookup['field'] ?? ''));
    $value = trim((string)($lookup['value'] ?? ''));
    $mode = trim((string)($lookup['mode'] ?? 'exact'));
    if ($field === '' || $value === '') return;
    $params['lookup_field'] = $field;
    $params['lookup_value'] = $value;
    $params['lookup_mode'] = $mode;
}

function voice_scope_label($scope) {
    $map = array(
        'lamami' => 'LaMami',
        'jostal' => 'Jostal',
        'casawasap' => 'Casawasap',
        'josue' => 'Agenda',
        'bots' => 'Bots',
        'lamamibot' => 'LamamiBot',
    );
    return $map[$scope] ?? ucfirst((string)$scope);
}


function voice_ai_detect_defaults_from_bot_template() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $cache = array(
        'api_key' => '',
        'model' => 'gpt-5.1',
        'api_key_found' => false,
        'model_found' => false,
        'source' => 'none',
    );

    $path = APP_PATH . '/bot_templates.php';
    if (!is_file($path)) {
        return $cache;
    }

    $raw = (string)@file_get_contents($path);
    if ($raw === '') {
        return $cache;
    }

    if (preg_match('/Bearer\s+(sk-[A-Za-z0-9_\-]+)/', $raw, $m)) {
        $cache['api_key'] = trim((string)$m[1]);
        $cache['api_key_found'] = ($cache['api_key'] !== '');
        $cache['source'] = 'bot_template';
    }

    if (preg_match_all("/model:\\s*['\"]([^'\"]+)['\"]/", $raw, $models)) {
        $flat = array_values(array_unique(array_filter(array_map('trim', (array)$models[1]))));
        if (in_array('gpt-5.1', $flat, true)) {
            $cache['model'] = 'gpt-5.1';
            $cache['model_found'] = true;
        } elseif (!empty($flat)) {
            $cache['model'] = (string)$flat[0];
            $cache['model_found'] = true;
        }
    }

    return $cache;
}

function voice_ai_default_api_key() {
    $defaults = voice_ai_detect_defaults_from_bot_template();
    return (string)($defaults['api_key'] ?? '');
}

function voice_ai_default_model() {
    $defaults = voice_ai_detect_defaults_from_bot_template();
    $model = trim((string)($defaults['model'] ?? ''));
    return $model !== '' ? $model : 'gpt-5.1';
}

function voice_ai_config_form_state() {
    $settings = storage_read('settings.json');
    $defaults = voice_ai_detect_defaults_from_bot_template();
    $envKey = trim((string)getenv('OPENAI_API_KEY'));
    $envModel = trim((string)getenv('OPENAI_VOICE_MODEL'));

    $storedKey = trim((string)($settings['voice_ai_api_key'] ?? ''));
    $storedModel = trim((string)($settings['voice_ai_model'] ?? ''));

    $formKey = $storedKey !== '' ? $storedKey : (string)($defaults['api_key'] ?? '');
    $formModel = $storedModel !== '' ? $storedModel : 'gpt-5.1';
    if ($formModel === '') {
        $formModel = voice_ai_default_model();
    }

    return array(
        'form_api_key' => $formKey,
        'form_model' => $formModel,
        'stored_api_key' => $storedKey,
        'stored_model' => $storedModel,
        'default_api_key' => (string)($defaults['api_key'] ?? ''),
        'default_model' => voice_ai_default_model(),
        'env_api_key' => $envKey,
        'env_model' => $envModel,
        'has_env_api_key' => ($envKey !== ''),
        'has_stored_api_key' => ($storedKey !== ''),
        'has_default_api_key' => !empty($defaults['api_key']),
    );
}

function voice_ai_config() {
    $settings = storage_read('settings.json');

    $apiKey = trim((string)getenv('OPENAI_API_KEY'));
    $apiKeySource = $apiKey !== '' ? 'env' : 'none';
    if ($apiKey === '' && !empty($settings['voice_ai_api_key'])) {
        $apiKey = trim((string)$settings['voice_ai_api_key']);
        $apiKeySource = $apiKey !== '' ? 'settings' : 'none';
    }
    if ($apiKey === '') {
        $apiKey = voice_ai_default_api_key();
        if ($apiKey !== '') {
            $apiKeySource = 'bot_template';
        }
    }

    $model = trim((string)getenv('OPENAI_VOICE_MODEL'));
    $modelSource = $model !== '' ? 'env' : 'none';
    if ($model === '' && !empty($settings['voice_ai_model'])) {
        $model = trim((string)$settings['voice_ai_model']);
        $modelSource = $model !== '' ? 'settings' : 'none';
    }
    if ($model === '') {
        $model = voice_ai_default_model();
        $modelSource = 'bot_template';
    }
    if ($model === '') {
        $model = 'gpt-5.1';
        $modelSource = 'default';
    }

    $org = trim((string)getenv('OPENAI_ORGANIZATION'));
    $project = trim((string)getenv('OPENAI_PROJECT'));

    return array(
        'api_key' => $apiKey,
        'model' => $model,
        'organization' => $org,
        'project' => $project,
        'configured' => ($apiKey !== ''),
        'api_key_source' => $apiKeySource,
        'model_source' => $modelSource,
    );
}

function voice_prompt_current_date_string() {
    return date('Y-m-d');
}

function voice_catalog_prompt_lines() {
    $lines = array();
    foreach (voice_command_catalog() as $group => $items) {
        foreach ($items as $intent => $meta) {
            $lines[] = '- ' . $intent . ' :: ' . ($meta['label'] ?? $intent) . ' [' . $group . ']';
        }
    }
    return implode("\n", $lines);
}

function voice_domain_hints() {
    return array(
        'pages' => array('dashboard', 'lamami', 'casawasap', 'jostal', 'gastos', 'informes', 'bots', 'avisos', 'josue', 'lamamibot', 'publicista', 'comercial'),
        'tabs' => array(
            'interesadas', 'clientas', 'lamamibot', 'agenda', 'telefonos', 'waha', 'publias', 'captacion', 'config', 'configm', 'notas', 'eurekas',
            'ventas', 'informes', 'crear_perfiles', 'estrategias', 'cuentas', 'campanas', 'subir_anuncios',
            'resumen', 'procesos', 'lineas', 'conversaciones', 'leads', 'ajustes', 'logs'
        ),
        'common_terms' => array(
            'lamami', 'la mami', 'lamamibot', 'casawasap', 'casa wasap', 'jostal', 'josue',
            'hostal', 'publicista', 'comercial', 'campanas', 'campañas', 'destacamos', 'agenda', 'eurekas', 'avisos',
            'form', 'formulario', 'nueva clienta', 'nueva interesada', 'alta clienta'
        ),
        'workflow_examples' => array(
            'abrir form de añadir clienta en jostal',
            'quiero crear una nueva interesada en la mami',
            'abre publicista campañas',
            'abre comercial logs',
            'eureka! añadir una vista de resumen para publicista'
        ),
    );
}

function voice_interpreter_system_prompt() {
    $today = voice_prompt_current_date_string();
    $catalog = voice_catalog_prompt_lines();
    $hints = voice_domain_hints();
    $pages = implode(', ', $hints['pages']);
    $tabs = implode(', ', $hints['tabs']);
    $terms = implode(', ', $hints['common_terms']);
    $workflows = implode(', ', $hints['workflow_examples']);

    return "Eres el intérprete de órdenes por voz de un CRM privado.\n"
        . "Tu trabajo NO es ejecutar nada. Solo devolver JSON válido y estricto.\n"
        . "Fecha actual del servidor: {$today}.\n"
        . "\n"
        . "REGLAS CRÍTICAS:\n"
        . "1) Devuelve SOLO JSON válido, sin markdown, sin comentarios, sin texto adicional.\n"
        . "2) Usa SOLO intents del catálogo permitido.\n"
        . "3) No inventes datos. Si faltan, marca needs_clarification=true y rellena missing_fields.\n"
        . "4) Si el usuario habla de la pantalla actual, usa entity_reference='current'.\n"
        . "5) Separa siempre acción, entidad, ámbito y criterio de búsqueda.\n"
        . "6) Cuando haya teléfono, úsalo como lookup_field='telefono' y lookup_value con 9 dígitos limpios.\n"
        . "7) Para búsquedas de ficha usa open_entity, open_entity_by_phone, open_entity_by_name o search_entity.\n"
        . "8) Para abrir listados usa list_entities.\n"
        . "9) Para filtros de informes, intenta devolver from y to en formato YYYY-MM-DD cuando el periodo esté claro.\n"
        . "10) Para cantidades monetarias usa cantidad como string numérico con punto decimal si hace falta.\n"
        . "11) Para consultas avanzadas usa analytics_kind: branches, summary, best_clienta, insights.\n"
        . "12) Si el usuario pide confirmar o cancelar algo pendiente, usa confirm_pending_action o cancel_pending_action.\n"
        . "13) La transcripción puede venir con ruido, cortes, homófonos o palabras mal reconocidas. Si hay speech_alternatives, elige la interpretación MÁS probable dentro del CRM.\n"
        . "14) Prioriza conceptos reales del CRM aunque la transcripción literal sea imperfecta.\n"
        . "15) Si el usuario expresa la intención de abrir un formulario o iniciar una acción, interpreta el objetivo operativo más probable aunque no use el nombre exacto del comando.\n"
        . "16) Ejemplo de inferencia correcta: 'abrir form de añadir clienta en hostal' probablemente significa abrir clientas de jostal en modo nuevo.\n"
        . "17) Si no encaja, usa unsupported_command o ask_clarification.\n"
        . "\n"
        . "CATÁLOGO PERMITIDO:\n{$catalog}\n"
        . "\n"
        . "GLOSARIO CRM:\n"
        . "- páginas habituales: {$pages}\n"
        . "- tabs habituales: {$tabs}\n"
        . "- términos frecuentes: {$terms}\n"
        . "- frases típicas: {$workflows}\n"
        . "\n"
        . "GUÍA DE PARAMS:\n"
        . "- open_page: usa params.page.\n"
        . "- open_tab: usa params.page y params.tab; opcionalmente view o avtab.\n"
        . "- open_entity / search_entity / open_entity_by_phone / open_entity_by_name: usa target_type, target_scope, lookup_field, lookup_value y opcionalmente entity_reference.\n"
        . "- list_entities: usa target_type y target_scope; opcionalmente estado.\n"
        . "- show_stats_clienta: usa from, to, rama, tipo y clienta_query o entity_reference=current.\n"
        . "- create_agenda_contact: usa nombre, telefono, observaciones.\n"
        . "- create_eureka: usa descripcion con la idea completa.\n"
        . "- create_casawasap_contacto: usa nombre si existe, telefono, notas, quien_lo_trae.\n"
        . "- add_gasto: usa cantidad y descripcion.\n"
        . "- set_lamamibot_runtime_mode / set_bot_runtime_mode: usa mode=start o mode=stop.\n"
        . "- query_comparison / query_analytics: usa analytics_kind y si aplica from/to/period_hint/rama.\n"
        . "\n"
        . "VALORES RECOMENDADOS:\n"
        . "- page: dashboard, lamami, casawasap, jostal, gastos, informes, bots, avisos, josue, lamamibot, publicista, comercial\n"
        . "- tab: interesadas, clientas, lamamibot, agenda, ventas, telefonos, config, configm, informes, eurekas, crear_perfiles, estrategias, cuentas, campanas, subir_anuncios, resumen, procesos, lineas, conversaciones, leads, ajustes, logs\n"
        . "- rama: todas, lamami, casawasap, jostal, global\n"
        . "- tipo: todos, ingresos, gastos, lead, alta, pago, venta\n"
        . "- target_type: clienta, interesada, casawasap_contacto, agenda_contact, bot, lamamibot, none\n"
        . "- target_scope: lamami, jostal, casawasap, josue, bots, lamamibot, none\n"
        . "- lookup_field: telefono, nombre, id, none\n"
        . "- lookup_mode: exact, partial\n"
        . "\n"
        . "EJEMPLOS TÍPICOS:\n"
        . "- 'ver interesada de jostal con el telefono 666555666' -> open_entity_by_phone, target_type=interesada, target_scope=jostal, lookup_field=telefono, lookup_value=666555666\n"
        . "- 'abre la clienta Andrea' -> open_entity_by_name, target_type=clienta, lookup_field=nombre, lookup_value=Andrea\n"
        . "- 'muestrame las interesadas de jostal' -> list_entities, target_type=interesada, target_scope=jostal\n"
        . "- 'busca el bot de Paola' -> search_entity, target_type=bot, target_scope=bots, lookup_field=nombre, lookup_value=Paola\n"
        . "- 'abre agenda' -> open_agenda\n"
        . "- 'eureka! añadir una vista de resumen para publicista' -> create_eureka con descripcion='añadir una vista de resumen para publicista'\n"
        . "- 'abre publicista campañas' -> open_tab con page=publicista y tab=campanas\n"
        . "- 'abre comercial logs' -> open_tab con page=comercial y tab=logs\n"
        . "- 'abre eurekas de josue' -> open_tab con page=josue y tab=eurekas\n"
        . "- 'apaga lamamibot' -> set_lamamibot_runtime_mode con mode=stop\n";
}

function voice_interpreter_json_schema() {
    static $schema = null;
    if ($schema !== null) return $schema;

    $intents = array_keys(voice_catalog_flat());
    $schema = array(
        'name' => 'voice_command_interpretation',
        'strict' => true,
        'schema' => array(
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array('intent', 'params', 'needs_clarification', 'clarification_question', 'missing_fields'),
            'properties' => array(
                'intent' => array(
                    'type' => 'string',
                    'enum' => array_values($intents),
                ),
                'params' => array(
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => array(
                        'page', 'tab', 'view', 'avtab', 'rama', 'tipo', 'from', 'to', 'period_hint', 'analytics_kind',
                        'entity_reference', 'target_type', 'target_scope', 'lookup_field', 'lookup_value', 'lookup_mode', 'estado',
                        'clienta_query', 'interesada_query', 'contacto_query', 'agenda_query', 'bot_query',
                        'nombre', 'telefono', 'observaciones', 'notas', 'quien_lo_trae', 'descripcion', 'cantidad', 'mode',
                        'pending_token', 'followup_value'
                    ),
                    'properties' => array(
                        'page' => array('type' => 'string'),
                        'tab' => array('type' => 'string'),
                        'view' => array('type' => 'string'),
                        'avtab' => array('type' => 'string'),
                        'rama' => array('type' => 'string'),
                        'tipo' => array('type' => 'string'),
                        'from' => array('type' => 'string'),
                        'to' => array('type' => 'string'),
                        'period_hint' => array('type' => 'string'),
                        'analytics_kind' => array('type' => 'string'),
                        'entity_reference' => array(
                            'type' => 'string',
                            'enum' => array('current', 'explicit', 'none')
                        ),
                        'target_type' => array(
                            'type' => 'string',
                            'enum' => array('clienta', 'interesada', 'casawasap_contacto', 'agenda_contact', 'bot', 'lamamibot', 'none')
                        ),
                        'target_scope' => array('type' => 'string'),
                        'lookup_field' => array('type' => 'string'),
                        'lookup_value' => array('type' => 'string'),
                        'lookup_mode' => array('type' => 'string'),
                        'estado' => array('type' => 'string'),
                        'clienta_query' => array('type' => 'string'),
                        'interesada_query' => array('type' => 'string'),
                        'contacto_query' => array('type' => 'string'),
                        'agenda_query' => array('type' => 'string'),
                        'bot_query' => array('type' => 'string'),
                        'nombre' => array('type' => 'string'),
                        'telefono' => array('type' => 'string'),
                        'observaciones' => array('type' => 'string'),
                        'notas' => array('type' => 'string'),
                        'quien_lo_trae' => array('type' => 'string'),
                        'descripcion' => array('type' => 'string'),
                        'cantidad' => array('type' => 'string'),
                        'mode' => array(
                            'type' => 'string',
                            'enum' => array('', 'start', 'stop')
                        ),
                        'pending_token' => array('type' => 'string'),
                        'followup_value' => array('type' => 'string'),
                    ),
                ),
                'needs_clarification' => array('type' => 'boolean'),
                'clarification_question' => array('type' => 'string'),
                'missing_fields' => array(
                    'type' => 'array',
                    'items' => array('type' => 'string'),
                ),
            ),
        ),
    );

    return $schema;
}

function voice_candidate_texts($text, $speechMeta = array()) {
    $items = array();
    $primary = trim((string)$text);
    $primaryClean = voice_sanitize_speech_noise($primary);
    foreach (array($primaryClean, $primary) as $candidate) {
        $candidate = trim((string)$candidate);
        if ($candidate !== '' && !in_array($candidate, $items, true)) {
            $items[] = $candidate;
        }
    }

    $alternatives = is_array($speechMeta['alternatives'] ?? null) ? $speechMeta['alternatives'] : array();
    foreach ($alternatives as $candidate) {
        foreach (array(voice_sanitize_speech_noise($candidate), $candidate) as $variant) {
            $variant = trim((string)$variant);
            if ($variant === '') continue;
            if (!in_array($variant, $items, true)) {
                $items[] = $variant;
            }
        }
    }

    return $items;
}

function voice_rule_interpretation_score($payload) {
    $payload = is_array($payload) ? $payload : array();
    $params = is_array($payload['params'] ?? null) ? $payload['params'] : array();
    $intent = trim((string)($payload['intent'] ?? ''));
    $score = 0;

    if ($intent !== '' && $intent !== 'unsupported_command' && $intent !== 'ask_clarification') $score += 12;
    if (!empty($payload['needs_clarification'])) $score -= 4;
    if (!empty($params['page'])) $score += 2;
    if (!empty($params['tab'])) $score += 2;
    if (!empty($params['target_type']) && ($params['target_type'] ?? '') !== 'none') $score += 3;
    if (!empty($params['target_scope'])) $score += 1;
    if (!empty($params['lookup_field'])) $score += 2;
    if (!empty($params['lookup_value'])) $score += 2;
    if (!empty($params['entity_reference']) && ($params['entity_reference'] ?? '') !== 'none') $score += 1;
    if (!empty($params['view'])) $score += 2;
    if (!empty($params['analytics_kind'])) $score += 1;
    if (!empty($params['mode'])) $score += 1;
    if (!empty($params['nombre']) || !empty($params['telefono']) || !empty($params['descripcion']) || !empty($params['cantidad'])) $score += 2;

    return $score;
}

function voice_ai_user_payload($text, $context, $speechMeta = array()) {
    $alternatives = voice_candidate_texts('', $speechMeta);
    return array(
        'command_text' => (string)$text,
        'normalized_text' => voice_normalize_text($text),
        'speech_source' => trim((string)($speechMeta['source'] ?? '')),
        'speech_alternatives' => $alternatives,
        'normalized_alternatives' => array_values(array_filter(array_map('voice_normalize_text', $alternatives))),
        'context' => voice_clean_context($context),
        'crm_hints' => voice_domain_hints(),
    );
}

function voice_extract_chat_completion_content($decoded) {
    if (!is_array($decoded)) return '';
    if (!empty($decoded['choices'][0]['message']['content']) && is_string($decoded['choices'][0]['message']['content'])) {
        return $decoded['choices'][0]['message']['content'];
    }

    if (!empty($decoded['choices'][0]['message']['content']) && is_array($decoded['choices'][0]['message']['content'])) {
        $parts = array();
        foreach ($decoded['choices'][0]['message']['content'] as $item) {
            if (is_array($item) && isset($item['text'])) {
                $parts[] = (string)$item['text'];
            }
        }
        return trim(implode("\n", $parts));
    }

    return '';
}

function voice_interpret_with_ai($text, $context = array(), $speechMeta = array()) {
    $cfg = voice_ai_config();
    $result = array(
        'ok' => false,
        'provider' => 'openai',
        'model' => $cfg['model'],
        'request_id' => '',
        'client_request_id' => generate_id('voiceai'),
        'parsed' => null,
        'raw_text' => '',
        'errors' => array(),
        'http_code' => 0,
        'used_fallback' => false,
    );

    if (!$cfg['configured']) {
        $result['errors'][] = 'openai_api_key_missing';
        return $result;
    }

    if (!function_exists('curl_init')) {
        $result['errors'][] = 'curl_init_not_available';
        return $result;
    }

    $payload = array(
        'model' => $cfg['model'],
        'temperature' => 0.1,
        'messages' => array(
            array(
                'role' => 'developer',
                'content' => voice_interpreter_system_prompt(),
            ),
            array(
                'role' => 'user',
                'content' => json_encode(voice_ai_user_payload($text, $context, $speechMeta), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ),
        ),
        'response_format' => array(
            'type' => 'json_schema',
            'json_schema' => voice_interpreter_json_schema(),
        ),
    );

    $headers = array(
        'Authorization: Bearer ' . $cfg['api_key'],
        'Content-Type: application/json',
        'Accept: application/json',
        'X-Client-Request-Id: ' . $result['client_request_id'],
    );
    if ($cfg['organization'] !== '') {
        $headers[] = 'OpenAI-Organization: ' . $cfg['organization'];
    }
    if ($cfg['project'] !== '') {
        $headers[] = 'OpenAI-Project: ' . $cfg['project'];
    }

    $responseHeaders = array();
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 40);
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

    $result['http_code'] = $httpCode;
    $result['request_id'] = isset($responseHeaders['x-request-id']) ? $responseHeaders['x-request-id'] : '';

    if ($curlError !== '') {
        $result['errors'][] = 'curl_error:' . $curlError;
        return $result;
    }

    $decoded = json_decode((string)$body, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        $errorMessage = '';
        if (is_array($decoded) && !empty($decoded['error']['message'])) {
            $errorMessage = (string)$decoded['error']['message'];
        } else {
            $errorMessage = voice_safe_substr((string)$body, 0, 400);
        }
        $result['errors'][] = 'openai_http_' . $httpCode . ':' . $errorMessage;
        return $result;
    }

    $content = voice_extract_chat_completion_content($decoded);
    $result['raw_text'] = $content;
    if ($content === '') {
        $result['errors'][] = 'empty_ai_content';
        return $result;
    }

    $parsed = json_decode($content, true);
    if (!is_array($parsed)) {
        $result['errors'][] = 'ai_json_decode_failed';
        return $result;
    }

    $result['ok'] = true;
    $result['parsed'] = $parsed;
    return $result;
}

function voice_interpret_with_rules($raw, $context = array()) {
    $raw = trim((string)$raw);
    $normalized = voice_normalize_text($raw);
    $params = voice_default_ai_params();

    if ($normalized === '') {
        return array(
            'intent' => 'unsupported_command',
            'params' => $params,
            'needs_clarification' => false,
            'clarification_question' => '',
            'missing_fields' => array(),
        );
    }

    if (voice_is_confirmation_text($normalized)) {
        return array('intent' => 'confirm_pending_action', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }
    if (voice_is_cancellation_text($normalized)) {
        return array('intent' => 'cancel_pending_action', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if (preg_match('/^\s*[¡!]?\s*eureka[!¡:\.\-,\s]*(.*)$/iu', $raw, $m)) {
        $descripcion = trim((string)($m[1] ?? ''));
        $params['page'] = 'josue';
        $params['tab'] = 'eurekas';
        $params['descripcion'] = $descripcion;
        return array(
            'intent' => 'create_eureka',
            'params' => $params,
            'needs_clarification' => ($descripcion === ''),
            'clarification_question' => $descripcion === '' ? 'Dime la idea después de "eureka" para poder guardarla.' : '',
            'missing_fields' => $descripcion === '' ? array('descripcion') : array(),
        );
    }

    $period = voice_parse_period_from_text($normalized);
    if (!empty($period['from'])) $params['from'] = $period['from'];
    if (!empty($period['to'])) $params['to'] = $period['to'];
    if (!empty($period['period_hint'])) $params['period_hint'] = $period['period_hint'];
    if (!empty($period['ambiguous'])) {
        return array(
            'intent' => 'ask_clarification',
            'params' => $params,
            'needs_clarification' => true,
            'clarification_question' => 'Necesito que concretes el periodo. Por ejemplo: hoy, esta semana, este mes o dos fechas exactas.',
            'missing_fields' => array('periodo'),
        );
    }

    $scope = voice_extract_scope_from_text($normalized, $context);
    $targetType = voice_detect_target_type($normalized, $context, $scope);
    $actionKind = voice_detect_action_kind($normalized);
    $lookup = voice_extract_entity_lookup($raw, $normalized, $targetType);
    $estado = voice_extract_estado_from_text($normalized);
    $isPlural = voice_plural_hint_from_text($normalized);

    if ($scope !== '') $params['target_scope'] = $scope;
    if ($targetType !== 'none') $params['target_type'] = $targetType;
    if ($estado !== '') $params['estado'] = $estado;
    voice_apply_lookup_to_params($params, $lookup);
    if (voice_is_current_entity_reference($normalized)) {
        $params['entity_reference'] = 'current';
    }

    if (voice_contains($normalized, 'compara') || voice_contains($normalized, 'comparar')) {
        $params['analytics_kind'] = 'branches';
        return array('intent' => 'query_comparison', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }
    if (voice_contains_any($normalized, array('mejor clienta', 'quien va mejor', 'quien genera mas'))) {
        $params['analytics_kind'] = 'best_clienta';
        return array('intent' => 'query_analytics', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }
    if ((voice_contains($normalized, 'resumen') || voice_contains($normalized, 'balance') || voice_contains($normalized, 'ingresos y gastos') || voice_contains($normalized, 'ingresos gastos'))
        && !voice_contains($normalized, 'clienta')) {
        $params['analytics_kind'] = 'summary';
        return array('intent' => 'query_analytics', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }
    if (voice_contains_any($normalized, array('analiza', 'lectura analitica', 'insights'))) {
        $params['analytics_kind'] = 'insights';
        return array('intent' => 'query_analytics', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if (voice_contains_any($normalized, array('lamamibot', 'la mamibot', 'mami bot'))
        && voice_contains_any($normalized, array('apaga', 'enciende', 'activa', 'desactiva'))) {
        $params['mode'] = voice_contains_any($normalized, array('apaga', 'desactiva', 'stop')) ? 'stop' : 'start';
        return array('intent' => 'set_lamamibot_runtime_mode', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if (($scope === 'bots' || $targetType === 'bot') && voice_contains_any($normalized, array('apaga', 'enciende', 'activa', 'desactiva'))) {
        $params['mode'] = voice_contains_any($normalized, array('apaga', 'desactiva', 'stop')) ? 'stop' : 'start';
        if ($lookup['value'] !== '') {
            $params['bot_query'] = $lookup['value'];
            $params['entity_reference'] = 'explicit';
        }
        return array('intent' => 'set_bot_runtime_mode', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if (($scope === 'josue' || $targetType === 'agenda_contact') && voice_contains_any($normalized, array('anade', 'crea', 'agrega', 'mete'))) {
        $params['nombre'] = voice_extract_name_hint($raw);
        $params['telefono'] = voice_normalize_phone(voice_extract_phone($raw));
        if (preg_match('/(?:observaciones|nota|notas)\s+(.+)$/u', $raw, $m)) {
            $params['observaciones'] = trim((string)$m[1]);
        }
        return array('intent' => 'create_agenda_contact', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if (($scope === 'casawasap' || $targetType === 'casawasap_contacto')
        && voice_contains_any($normalized, array('anade', 'crea', 'agrega', 'interesado', 'contacto', 'cliente'))) {
        $params['nombre'] = voice_extract_name_hint($raw);
        $params['telefono'] = voice_normalize_phone(voice_extract_phone($raw));
        if (preg_match('/(?:nota|notas)\s+(.+)$/u', $raw, $m)) {
            $params['notas'] = trim((string)$m[1]);
        }
        return array('intent' => 'create_casawasap_contacto', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if (voice_contains($normalized, 'gasto') && voice_contains_any($normalized, array('anade', 'agrega', 'mete', 'registra'))) {
        $params['cantidad'] = voice_extract_amount($normalized);
        if (preg_match('/gasto(?:\s+de)?\s+\d+(?:[\.,]\d{1,2})?\s*(?:e|euros?)?\s+(?:en|de|concepto)?\s*(.+)$/u', $normalized, $m)) {
            $params['descripcion'] = trim((string)$m[1]);
        }
        return array('intent' => 'add_gasto', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if ((voice_contains($normalized, 'estadisticas') || voice_contains($normalized, 'estadistica') || voice_contains($normalized, 'resumen') || voice_contains($normalized, 'informes'))
        && ($targetType === 'clienta' || voice_contains($normalized, 'clienta') || voice_contains($normalized, 'cliente'))) {
        if ($params['entity_reference'] === 'current') {
            $params['target_type'] = 'clienta';
        } elseif ($lookup['value'] !== '') {
            $params['target_type'] = 'clienta';
            $params['clienta_query'] = $lookup['value'];
            $params['entity_reference'] = 'explicit';
        }
        return array('intent' => 'show_stats_clienta', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if (voice_contains($normalized, 'informes') && voice_contains_any($normalized, array('filtra', 'muestra', 'ver', 'abre'))) {
        return array('intent' => 'filter_informes', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if ($targetType !== 'none' && ($actionKind === 'open' || $actionKind === 'search' || $actionKind === 'list' || $isPlural || $lookup['value'] !== '' || $params['entity_reference'] === 'current')) {
        if ($params['entity_reference'] === 'current') {
            return array('intent' => 'open_edit_view', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
        }
        if ($actionKind === 'list' && $lookup['value'] === '') {
            return array('intent' => 'list_entities', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
        }
        if (($lookup['field'] ?? '') === 'telefono') {
            if ($targetType === 'clienta') $params['clienta_query'] = $lookup['value'];
            if ($targetType === 'interesada') $params['interesada_query'] = $lookup['value'];
            if ($targetType === 'casawasap_contacto') $params['contacto_query'] = $lookup['value'];
            if ($targetType === 'agenda_contact') $params['agenda_query'] = $lookup['value'];
            if ($targetType === 'bot') $params['bot_query'] = $lookup['value'];
            $params['entity_reference'] = 'explicit';
            return array('intent' => 'open_entity_by_phone', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
        }
        if (($lookup['field'] ?? '') === 'nombre' || ($lookup['field'] ?? '') === 'id') {
            if ($targetType === 'clienta') $params['clienta_query'] = $lookup['value'];
            if ($targetType === 'interesada') $params['interesada_query'] = $lookup['value'];
            if ($targetType === 'casawasap_contacto') $params['contacto_query'] = $lookup['value'];
            if ($targetType === 'agenda_contact') $params['agenda_query'] = $lookup['value'];
            if ($targetType === 'bot') $params['bot_query'] = $lookup['value'];
            $params['entity_reference'] = 'explicit';
            return array('intent' => ($actionKind === 'search' ? 'search_entity' : 'open_entity_by_name'), 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
        }
        if ($isPlural) {
            return array('intent' => 'list_entities', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
        }
        return array('intent' => ($actionKind === 'search' ? 'search_entity' : 'open_entity'), 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    $pageKeywords = array(
        'dashboard' => array('dashboard', 'inicio', 'resumen principal'),
        'lamami' => array('lamami', 'la mami'),
        'casawasap' => array('casawasap', 'casa wasap'),
        'jostal' => array('jostal', 'hostal', 'yostal', 'josta'),
        'gastos' => array('gastos', 'gasto'),
        'informes' => array('informes', 'informe', 'estadisticas', 'estadistica', 'resumen'),
        'bots' => array('bots', 'panel de bots'),
        'avisos' => array('avisos', 'alertas'),
        'lamamibot' => array('lamamibot', 'la mamibot', 'mami bot'),
        'josue' => array('josue'),
        'publicista' => array('publicista'),
        'comercial' => array('comercial'),
    );

    if ((voice_contains($normalized, 'agenda') && voice_contains_any($normalized, array('abre', 'entra', 've', 'muestra'))) || $normalized === 'agenda') {
        $params['page'] = 'josue';
        $params['tab'] = 'agenda';
        return array('intent' => 'open_agenda', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if ((voice_contains($normalized, 'eurekas') || voice_contains($normalized, 'eureka'))
        && (voice_contains($normalized, 'josue') || ($context['page'] ?? '') === 'josue' || voice_contains_any($normalized, array('abre', 'entra', 've', 'muestra', 'mostrar', 'ir')))) {
        $params['page'] = 'josue';
        $params['tab'] = 'eurekas';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if ((voice_contains($normalized, 'publicista') || ($context['page'] ?? '') === 'publicista') && voice_contains_any($normalized, array('campanas', 'campana'))) {
        $params['page'] = 'publicista';
        $params['tab'] = 'campanas';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if ((voice_contains($normalized, 'publicista') || ($context['page'] ?? '') === 'publicista') && voice_contains_any($normalized, array('cuentas', 'cuenta'))) {
        $params['page'] = 'publicista';
        $params['tab'] = 'cuentas';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if ((voice_contains($normalized, 'publicista') || ($context['page'] ?? '') === 'publicista') && voice_contains_any($normalized, array('estrategias', 'estrategia'))) {
        $params['page'] = 'publicista';
        $params['tab'] = 'estrategias';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if ((voice_contains($normalized, 'publicista') || ($context['page'] ?? '') === 'publicista') && voice_contains_any($normalized, array('perfiles', 'perfil'))) {
        $params['page'] = 'publicista';
        $params['tab'] = 'crear_perfiles';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if ((voice_contains($normalized, 'publicista') || ($context['page'] ?? '') === 'publicista') && voice_contains_any($normalized, array('subir anuncios', 'anuncios', 'subidas'))) {
        $params['page'] = 'publicista';
        $params['tab'] = 'subir_anuncios';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if ((voice_contains($normalized, 'comercial') || ($context['page'] ?? '') === 'comercial') && voice_contains_any($normalized, array('logs', 'log'))) {
        $params['page'] = 'comercial';
        $params['tab'] = 'logs';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if ((voice_contains($normalized, 'comercial') || ($context['page'] ?? '') === 'comercial') && voice_contains_any($normalized, array('procesos', 'proceso'))) {
        $params['page'] = 'comercial';
        $params['tab'] = 'procesos';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if ((voice_contains($normalized, 'comercial') || ($context['page'] ?? '') === 'comercial') && voice_contains_any($normalized, array('lineas', 'linea', 'líneas', 'línea'))) {
        $params['page'] = 'comercial';
        $params['tab'] = 'lineas';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if ((voice_contains($normalized, 'comercial') || ($context['page'] ?? '') === 'comercial') && voice_contains_any($normalized, array('conversaciones', 'conversacion', 'conversación'))) {
        $params['page'] = 'comercial';
        $params['tab'] = 'conversaciones';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if ((voice_contains($normalized, 'comercial') || ($context['page'] ?? '') === 'comercial') && voice_contains_any($normalized, array('leads', 'lead'))) {
        $params['page'] = 'comercial';
        $params['tab'] = 'leads';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if ((voice_contains($normalized, 'comercial') || ($context['page'] ?? '') === 'comercial') && voice_contains_any($normalized, array('ajustes', 'configuracion', 'configuración'))) {
        $params['page'] = 'comercial';
        $params['tab'] = 'ajustes';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    foreach ($pageKeywords as $page => $keywords) {
        foreach ($keywords as $keyword) {
            if (voice_contains_any($normalized, array('abre', 'entra', 've', 'muestra', 'mostrar', 'ir')) && voice_contains($normalized, $keyword)) {
                $params['page'] = $page;
                if ($page === 'lamamibot') return array('intent' => 'open_lamamibot', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
                if ($page === 'bots') return array('intent' => 'open_bots', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
                if ($page === 'informes') return array('intent' => 'open_informes', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
                if ($page === 'dashboard') return array('intent' => 'go_dashboard', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
                return array('intent' => 'open_page', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
            }
        }
    }

    if (voice_contains_any($normalized, array('clientas', 'clienta')) && voice_wants_new_form($normalized)) {
        $params['page'] = ($scope === 'jostal') ? 'jostal' : 'lamami';
        $params['tab'] = 'clientas';
        $params['view'] = 'new';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if (voice_contains_any($normalized, array('interesadas', 'interesada')) && voice_wants_new_form($normalized)) {
        $params['page'] = ($scope === 'jostal') ? 'jostal' : 'lamami';
        $params['tab'] = 'interesadas';
        $params['view'] = 'new';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if (voice_contains_any($normalized, array('clientas', 'clienta')) && voice_contains_any($normalized, array('abre', 'abrir', 'entra', 've', 'muestra'))) {
        $params['page'] = ($scope === 'jostal') ? 'jostal' : 'lamami';
        $params['tab'] = 'clientas';
        if (voice_wants_new_form($normalized)) $params['view'] = 'new';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if (voice_contains_any($normalized, array('interesadas', 'interesada')) && voice_contains_any($normalized, array('abre', 'abrir', 'entra', 've', 'muestra'))) {
        $params['page'] = ($scope === 'jostal') ? 'jostal' : 'lamami';
        $params['tab'] = 'interesadas';
        if (voice_wants_new_form($normalized)) $params['view'] = 'new';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    if (($scope === 'jostal' || voice_contains($normalized, 'jostal')) && voice_contains_any($normalized, array('ventas', 'venta'))) {
        $params['page'] = 'jostal';
        $params['tab'] = 'ventas';
        return array('intent' => 'open_tab', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    return array(
        'intent' => 'unsupported_command',
        'params' => $params,
        'needs_clarification' => false,
        'clarification_question' => '',
        'missing_fields' => array(),
    );
}

function voice_default_ai_params() {
    return array(
        'page' => '',
        'tab' => '',
        'view' => '',
        'avtab' => '',
        'rama' => '',
        'tipo' => '',
        'from' => '',
        'to' => '',
        'period_hint' => '',
        'analytics_kind' => '',
        'entity_reference' => 'none',
        'target_type' => 'none',
        'target_scope' => '',
        'lookup_field' => '',
        'lookup_value' => '',
        'lookup_mode' => 'exact',
        'estado' => '',
        'clienta_query' => '',
        'interesada_query' => '',
        'contacto_query' => '',
        'agenda_query' => '',
        'bot_query' => '',
        'nombre' => '',
        'telefono' => '',
        'observaciones' => '',
        'notas' => '',
        'quien_lo_trae' => '',
        'descripcion' => '',
        'cantidad' => '',
        'mode' => '',
        'pending_token' => '',
        'followup_value' => '',
    );
}

function voice_is_confirmation_text($normalizedText) {
    $normalizedText = voice_normalize_text($normalizedText);
    if ($normalizedText === '') return false;
    $samples = array('si', 'si confirma', 'confirma', 'confirmar', 'dale', 'ok', 'vale', 'adelante', 'ejecuta');
    return in_array($normalizedText, $samples, true) || voice_contains($normalizedText, 'confirma');
}

function voice_is_cancellation_text($normalizedText) {
    $normalizedText = voice_normalize_text($normalizedText);
    if ($normalizedText === '') return false;
    $samples = array('no', 'cancela', 'cancelar', 'anula', 'anular', 'descarta', 'olvidalo', 'olvidalo ya');
    return in_array($normalizedText, $samples, true) || voice_contains($normalizedText, 'cancel');
}

function voice_parse_period_from_text($normalizedText) {
    $out = array('from' => '', 'to' => '', 'period_hint' => '', 'ambiguous' => false);
    $normalizedText = voice_normalize_text($normalizedText);
    if ($normalizedText === '') return $out;

    if (voice_contains($normalizedText, 'hoy')) {
        $out['period_hint'] = 'today';
        $out['from'] = business_today_date();
        $out['to'] = business_today_date();
        return $out;
    }
    if (voice_contains($normalizedText, 'esta semana')) {
        $mondayTs = strtotime('monday this week');
        if (!$mondayTs) $mondayTs = time();
        $out['period_hint'] = 'this_week';
        $out['from'] = date('Y-m-d', $mondayTs);
        $out['to'] = business_today_date();
        return $out;
    }
    if (voice_contains($normalizedText, 'este mes')) {
        $out['period_hint'] = 'this_month';
        $out['from'] = business_current_month_key() . '-01';
        $out['to'] = business_today_date();
        return $out;
    }

    if (preg_match_all('/(\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?)/u', $normalizedText, $m) && count($m[1]) >= 1) {
        $dates = array();
        foreach ($m[1] as $raw) {
            $dates[] = voice_normalize_loose_date($raw);
        }
        $dates = array_values(array_filter($dates));
        if (count($dates) === 1) {
            $out['from'] = $dates[0];
            $out['to'] = $dates[0];
            $out['period_hint'] = 'exact_day';
            return $out;
        }
        if (count($dates) >= 2) {
            $out['from'] = $dates[0];
            $out['to'] = $dates[1];
            $out['period_hint'] = 'range';
            return $out;
        }
    }

    if (voice_contains($normalizedText, 'periodo') || voice_contains($normalizedText, 'periodo')) {
        $out['ambiguous'] = true;
    }

    return $out;
}

function voice_normalize_loose_date($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') return '';
    $parts = preg_split('/[\/\-]+/', $raw);
    if (count($parts) < 2) return '';
    $day = (int)$parts[0];
    $month = (int)$parts[1];
    $year = count($parts) >= 3 ? (int)$parts[2] : (int)date('Y');
    if ($year < 100) $year += 2000;
    if (!checkdate($month, $day, $year)) return '';
    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function voice_sanitize_ai_interpretation($payload) {
    $out = array(
        'intent' => 'unsupported_command',
        'params' => voice_default_ai_params(),
        'needs_clarification' => false,
        'clarification_question' => '',
        'missing_fields' => array(),
    );

    if (!is_array($payload)) {
        return $out;
    }

    $intent = trim((string)($payload['intent'] ?? 'unsupported_command'));
    if (voice_intent_exists($intent)) {
        $out['intent'] = $intent;
    }

    $params = voice_default_ai_params();
    if (!empty($payload['params']) && is_array($payload['params'])) {
        foreach ($params as $key => $value) {
            if (isset($payload['params'][$key])) {
                $params[$key] = trim((string)$payload['params'][$key]);
            }
        }
    }
    $params['target_scope'] = voice_normalize_scope($params['target_scope'], $params['target_type']);
    if ($params['lookup_field'] === 'telefono') {
        $params['lookup_value'] = voice_normalize_phone($params['lookup_value']);
        if ($params['telefono'] === '' && $params['lookup_value'] !== '') {
            $params['telefono'] = $params['lookup_value'];
        }
    }
    if ($params['telefono'] !== '') {
        $params['telefono'] = voice_normalize_phone($params['telefono']);
    }
    if ($params['lookup_field'] !== '' && $params['lookup_value'] !== '' && $params['target_type'] !== 'none') {
        if ($params['target_type'] === 'clienta') $params['clienta_query'] = $params['lookup_value'];
        if ($params['target_type'] === 'interesada') $params['interesada_query'] = $params['lookup_value'];
        if ($params['target_type'] === 'casawasap_contacto') $params['contacto_query'] = $params['lookup_value'];
        if ($params['target_type'] === 'agenda_contact') $params['agenda_query'] = $params['lookup_value'];
        if ($params['target_type'] === 'bot') $params['bot_query'] = $params['lookup_value'];
    }
    $out['params'] = $params;

    $out['needs_clarification'] = !empty($payload['needs_clarification']);
    $out['clarification_question'] = trim((string)($payload['clarification_question'] ?? ''));
    $out['missing_fields'] = array();
    if (!empty($payload['missing_fields']) && is_array($payload['missing_fields'])) {
        foreach ($payload['missing_fields'] as $field) {
            $field = trim((string)$field);
            if ($field !== '' && !in_array($field, $out['missing_fields'], true)) {
                $out['missing_fields'][] = $field;
            }
        }
    }

    return $out;
}

function voice_pipeline_interpret($text, $context = array(), $speechMeta = array()) {
    $normalized = voice_normalize_text($text);

    if ($normalized === '') {
        return array(
            'stage' => 'error',
            'intent' => 'unsupported_command',
            'params' => voice_default_ai_params(),
            'message' => 'No has dictado ni escrito ninguna orden.',
            'errors' => array('empty_command'),
            'ai' => array(),
        );
    }

    $ai = voice_interpret_with_ai($text, $context, $speechMeta);
    if (!empty($ai['ok']) && !empty($ai['parsed']) && is_array($ai['parsed'])) {
        $parsed = voice_sanitize_ai_interpretation($ai['parsed']);
        $stage = !empty($parsed['needs_clarification']) ? 'needs_clarification' : 'interpreted';
        return array(
            'stage' => $stage,
            'intent' => !empty($parsed['needs_clarification']) ? 'ask_clarification' : $parsed['intent'],
            'params' => $parsed['params'],
            'missing_fields' => $parsed['missing_fields'],
            'message' => !empty($parsed['needs_clarification'])
                ? ($parsed['clarification_question'] !== '' ? $parsed['clarification_question'] : 'Necesito un poco más de detalle para completar la orden.')
                : 'Orden interpretada mediante IA.',
            'errors' => array(),
            'ai' => array(
                'enabled' => true,
                'provider' => 'openai',
                'model' => $ai['model'],
                'request_id' => $ai['request_id'],
                'client_request_id' => $ai['client_request_id'],
                'used_fallback' => false,
            ),
        );
    }

    $selectedTranscript = $text;
    $rule = null;
    $bestScore = -9999;
    foreach (voice_candidate_texts($text, $speechMeta) as $candidateText) {
        $candidateRule = voice_sanitize_ai_interpretation(voice_interpret_with_rules($candidateText, $context));
        $candidateScore = voice_rule_interpretation_score($candidateRule);
        if ($rule === null || $candidateScore > $bestScore) {
            $rule = $candidateRule;
            $bestScore = $candidateScore;
            $selectedTranscript = $candidateText;
        }
    }
    if ($rule === null) {
        $rule = voice_sanitize_ai_interpretation(voice_interpret_with_rules($text, $context));
    }

    return array(
        'stage' => !empty($rule['needs_clarification']) ? 'needs_clarification' : 'interpreted',
        'intent' => !empty($rule['needs_clarification']) ? 'ask_clarification' : $rule['intent'],
        'params' => $rule['params'],
        'missing_fields' => $rule['missing_fields'],
        'message' => !empty($rule['needs_clarification'])
            ? ($rule['clarification_question'] !== '' ? $rule['clarification_question'] : 'Necesito un poco más de detalle para completar la orden.')
            : (($selectedTranscript !== $text)
                ? 'Orden interpretada por el parser de respaldo usando la variante de voz más probable.'
                : 'Orden interpretada por el parser de respaldo.'),
        'errors' => isset($ai['errors']) && is_array($ai['errors']) ? $ai['errors'] : array(),
        'ai' => array(
            'enabled' => !empty(voice_ai_config()['configured']),
            'provider' => 'openai',
            'model' => $ai['model'] ?? '',
            'request_id' => $ai['request_id'] ?? '',
            'client_request_id' => $ai['client_request_id'] ?? '',
            'used_fallback' => true,
            'selected_transcript' => $selectedTranscript,
        ),
    );
}

function voice_page_url($page) {
    $page = trim((string)$page);

    switch ($page) {
        case 'dashboard':
            return 'index.php?page=dashboard';
        case 'lamami':
            return 'index.php?page=lamami';
        case 'casawasap':
            return 'index.php?page=casawasap';
        case 'jostal':
            return 'index.php?page=jostal';
        case 'gastos':
            return 'index.php?page=gastos';
        case 'informes':
            return 'index.php?page=informes';
        case 'bots':
            return 'index.php?page=bots';
        case 'avisos':
            return 'index.php?page=avisos';
        case 'josue':
            return 'index.php?page=josue';
        case 'lamamibot':
            return 'index.php?page=lamamibot';
        case 'publicista':
            return 'index.php?page=publicista';
        case 'comercial':
            return 'index.php?page=comercial';
        case 'clientas':
            return lamami_tab_url('clientas');
        case 'interesadas':
            return lamami_tab_url('interesadas');
        case 'agenda':
            return 'index.php?page=josue&tab=agenda';
    }

    return '';
}

function voice_tab_url($page, $tab, $view = '', $avtab = '') {
    $page = trim((string)$page);
    $tab = trim((string)$tab);
    $view = trim((string)$view);
    $avtab = trim((string)$avtab);

    if ($page === 'lamami' || $page === 'clientas' || $page === 'interesadas' || $page === 'lamamibot') {
        if ($tab === '') {
            if ($page === 'clientas') $tab = 'clientas';
            elseif ($page === 'interesadas') $tab = 'interesadas';
            elseif ($page === 'lamamibot') $tab = 'lamamibot';
        }
        $url = lamami_tab_url($tab !== '' ? $tab : 'interesadas');
        if ($view === 'new') $url .= '&new=1';
        return $url;
    }

    if ($page === 'josue') {
        return 'index.php?page=josue' . ($tab !== '' ? '&tab=' . urlencode($tab) : '') . ($view === 'new' ? '&new=1' : '');
    }

    if ($page === 'jostal') {
        return 'index.php?page=jostal' . ($tab !== '' ? '&tab=' . urlencode($tab) : '') . ($view === 'new' ? '&new=1' : '');
    }

    if ($page === 'publicista') {
        return publicista_page_url($tab !== '' ? $tab : 'crear_perfiles');
    }

    if ($page === 'comercial') {
        return comercial_page_url($tab !== '' ? $tab : 'resumen');
    }

    if ($page === 'avisos') {
        return avisos_page_url($avtab !== '' ? array('avtab' => $avtab) : array());
    }

    if ($page === 'informes') {
        $query = array('page' => 'informes');
        if ($view !== '') $query['view'] = $view;
        return 'index.php?' . http_build_query($query);
    }

    return voice_page_url($page);
}

function voice_report_filters_from_params($params, $context) {
    $filters = array(
        'from' => '',
        'to' => '',
        'rama' => 'todas',
        'tipo' => 'todos',
        'cliente_id' => '',
        'view' => 'report',
    );

    if (!empty($context['from'])) $filters['from'] = trim((string)$context['from']);
    if (!empty($context['to'])) $filters['to'] = trim((string)$context['to']);
    if (!empty($context['rama'])) $filters['rama'] = trim((string)$context['rama']);
    if (!empty($context['tipo'])) $filters['tipo'] = trim((string)$context['tipo']);

    if (!empty($params['from'])) $filters['from'] = trim((string)$params['from']);
    if (!empty($params['to'])) $filters['to'] = trim((string)$params['to']);
    if (!empty($params['rama'])) $filters['rama'] = trim((string)$params['rama']);
    if (!empty($params['tipo'])) $filters['tipo'] = trim((string)$params['tipo']);
    if (!empty($params['view'])) $filters['view'] = trim((string)$params['view']);

    $periodHint = trim((string)($params['period_hint'] ?? ''));
    if ($periodHint !== '') {
        if ($periodHint === 'today' || $periodHint === 'hoy') {
            $filters['from'] = business_today_date();
            $filters['to'] = business_today_date();
        } elseif ($periodHint === 'this_month' || $periodHint === 'este_mes') {
            $filters['from'] = business_current_month_key() . '-01';
            $filters['to'] = business_today_date();
        } elseif ($periodHint === 'this_week' || $periodHint === 'esta_semana') {
            $mondayTs = strtotime('monday this week');
            if (!$mondayTs) $mondayTs = time();
            $filters['from'] = date('Y-m-d', $mondayTs);
            $filters['to'] = business_today_date();
        }
    }

    if ($filters['from'] === '') $filters['from'] = business_current_month_key() . '-01';
    if ($filters['to'] === '') $filters['to'] = business_today_date();
    if ($filters['rama'] === '') $filters['rama'] = 'todas';
    if ($filters['tipo'] === '') $filters['tipo'] = 'todos';

    return $filters;
}

function voice_report_url($filters) {
    $query = array('page' => 'informes');
    if (!empty($filters['view']) && $filters['view'] !== 'report') {
        $query['view'] = $filters['view'];
    }
    foreach (array('from', 'to', 'rama', 'tipo', 'cliente_id') as $key) {
        if (!empty($filters[$key])) {
            $query[$key] = $filters[$key];
        }
    }
    return 'index.php?' . http_build_query($query);
}

function voice_entity_label($kind, $row, $scope = '') {
    if (!is_array($row)) return '';

    $prefix = $scope !== '' ? voice_scope_label($scope) . ' · ' : '';
    switch ($kind) {
        case 'clienta':
            $name = trim((string)($row['nombre'] ?? ''));
            if ($name === '') $name = 'Clienta';
            return $prefix . $name . (($row['telefono'] ?? '') !== '' ? ' · ' . trim((string)$row['telefono']) : '');
        case 'interesada':
            $name = trim((string)($row['nombre'] ?? ''));
            $base = $name !== '' ? $name : 'Interesada';
            return $prefix . $base . (($row['telefono'] ?? '') !== '' ? ' · ' . trim((string)$row['telefono']) : '');
        case 'casawasap_contacto':
            $name = trim((string)($row['nombre'] ?? ''));
            if ($name === '') $name = 'Contacto Casawasap';
            return $prefix . $name . (($row['telefono'] ?? '') !== '' ? ' · ' . trim((string)$row['telefono']) : '');
        case 'agenda_contact':
            $name = trim((string)($row['nombre'] ?? ''));
            if ($name === '') $name = 'Contacto agenda';
            return $prefix . $name . (($row['telefono'] ?? '') !== '' ? ' · ' . trim((string)$row['telefono']) : '');
        case 'bot':
            return $prefix . trim((string)($row['nombre_bot'] ?? 'Bot')) . (($row['telefono_bot'] ?? '') !== '' ? ' · ' . trim((string)$row['telefono_bot']) : '');
        case 'lamamibot':
            return trim((string)($row['nombre_bot'] ?? 'LamamiBot'));
    }
    return '';
}

function voice_entity_option($kind, $row, $scope = '') {
    return array(
        'kind' => $kind,
        'id' => trim((string)($row['id'] ?? '')),
        'label' => voice_entity_label($kind, $row, $scope),
        'scope' => $scope,
        'selection_key' => trim((string)$kind) . ':' . trim((string)$scope) . ':' . trim((string)($row['id'] ?? '')),
    );
}

function voice_find_dataset_rows($kind, $scope = '') {
    switch ($kind) {
        case 'clienta':
            if ($scope === 'jostal') return storage_read('jostal_clientas.json');
            return storage_read('clientes.json');
        case 'interesada':
            if ($scope === 'jostal') return storage_read('jostal_interesadas.json');
            return storage_read('interesadas.json');
        case 'casawasap_contacto':
            return storage_read('casawasap_contactos.json');
        case 'agenda_contact':
            return storage_read('agenda.json');
        case 'bot':
            return storage_read('bots.json');
        case 'lamamibot':
            return array(lamamibot_get());
    }
    return array();
}

function voice_entity_search_fields($kind, $scope = '') {
    switch ($kind) {
        case 'clienta':
            return $scope === 'jostal' ? array('nombre', 'telefono', 'observaciones', 'modo') : array('nombre', 'telefono', 'notas', 'provincia', 'localidad', 'zona');
        case 'interesada':
            return $scope === 'jostal' ? array('telefono', 'observaciones', 'interesada_en', 'estado') : array('telefono', 'observaciones', 'movil_origen', 'estado');
        case 'casawasap_contacto':
            return array('nombre', 'telefono', 'notas', 'quien_lo_trae', 'estado');
        case 'agenda_contact':
            return array('nombre', 'telefono', 'observaciones');
        case 'bot':
            return array('nombre_bot', 'telefono_bot', 'cliente_id', 'estado', 'server_ip');
        case 'lamamibot':
            return array('nombre_bot', 'estado');
    }
    return array();
}

function voice_row_exact_match($row, $query, $fields) {
    $queryNorm = voice_normalize_text($query);
    $queryDigits = voice_normalize_phone($query);

    foreach ($fields as $field) {
        $value = trim((string)($row[$field] ?? ''));
        if ($value === '') continue;
        $norm = voice_normalize_text($value);
        if ($queryNorm !== '' && $norm !== '' && $norm === $queryNorm) return true;
        if ($queryDigits !== '' && voice_normalize_phone($value) === $queryDigits) return true;
        if ($field === 'id' && $value === (string)$query) return true;
    }
    return false;
}

function voice_row_partial_match($row, $query, $fields) {
    $queryNorm = voice_normalize_text($query);
    $queryDigits = voice_normalize_phone($query);

    foreach ($fields as $field) {
        $value = trim((string)($row[$field] ?? ''));
        if ($value === '') continue;
        $norm = voice_normalize_text($value);
        if ($queryNorm !== '' && $norm !== '' && voice_contains($norm, $queryNorm)) return true;
        if ($queryDigits !== '' && voice_contains(voice_normalize_phone($value), $queryDigits)) return true;
    }
    return false;
}

function voice_entity_supported_scopes($kind, $scope = '') {
    $scope = voice_normalize_scope($scope, $kind);
    switch ($kind) {
        case 'clienta':
        case 'interesada':
            return $scope !== '' && in_array($scope, array('lamami', 'jostal'), true) ? array($scope) : array('lamami', 'jostal');
        case 'casawasap_contacto':
            return array('casawasap');
        case 'agenda_contact':
            return array('josue');
        case 'bot':
            return array('bots');
        case 'lamamibot':
            return array('lamamibot');
    }
    return $scope !== '' ? array($scope) : array('');
}

function voice_entity_phone_field($kind, $scope = '') {
    if ($kind === 'bot') return 'telefono_bot';
    if (in_array($kind, array('clienta', 'interesada', 'casawasap_contacto', 'agenda_contact'), true)) return 'telefono';
    return '';
}

function voice_entity_name_field($kind, $scope = '') {
    if ($kind === 'bot') return 'nombre_bot';
    if (in_array($kind, array('clienta', 'casawasap_contacto', 'agenda_contact'), true)) return 'nombre';
    if ($kind === 'lamamibot') return 'nombre_bot';
    return 'nombre';
}

function voice_entity_row_matches_estado($kind, $scope, $row, $estado) {
    $estado = voice_normalize_text($estado);
    if ($estado === '') return true;
    if ($kind === 'clienta' && $scope === 'jostal' && $estado === 'en_casa') {
        return function_exists('jostal_clienta_en_casa') ? !!jostal_clienta_en_casa($row) : true;
    }
    $rowEstado = voice_normalize_text($row['estado'] ?? '');
    if ($rowEstado === '' && isset($row['modo'])) {
        $rowEstado = voice_normalize_text($row['modo']);
    }
    if ($estado === 'alta') return $rowEstado === 'alta' || $rowEstado === 'activo';
    if ($estado === 'baja') return $rowEstado === 'baja' || $rowEstado === 'inactivo';
    return $rowEstado === $estado;
}

function voice_entity_candidate_entries($kind, $scope = '') {
    $entries = array();
    foreach (voice_entity_supported_scopes($kind, $scope) as $resolvedScope) {
        foreach ((array)voice_find_dataset_rows($kind, $resolvedScope) as $row) {
            if (!is_array($row)) continue;
            $entries[] = array(
                'kind' => $kind,
                'scope' => $resolvedScope,
                'row' => $row,
            );
        }
    }
    return $entries;
}

function voice_resolve_entity_matches($kind, $query, $scope = '', $lookupField = '', $lookupValue = '', $estado = '') {
    $entries = voice_entity_candidate_entries($kind, $scope);
    $lookupField = trim((string)$lookupField);
    $lookupValue = trim((string)$lookupValue);
    $query = trim((string)$query);
    $exact = array();
    $partial = array();

    foreach ($entries as $entry) {
        $row = $entry['row'];
        $entryScope = $entry['scope'];
        if (!voice_entity_row_matches_estado($kind, $entryScope, $row, $estado)) continue;

        $matchedExact = false;
        $matchedPartial = false;

        if ($lookupField === 'telefono') {
            $field = voice_entity_phone_field($kind, $entryScope);
            $rowValue = voice_normalize_phone($row[$field] ?? '');
            $lookupDigits = voice_normalize_phone($lookupValue !== '' ? $lookupValue : $query);
            if ($lookupDigits !== '' && $rowValue !== '') {
                if ($rowValue === $lookupDigits) $matchedExact = true;
                elseif (voice_contains($rowValue, $lookupDigits) || voice_contains($lookupDigits, $rowValue)) $matchedPartial = true;
            }
        } elseif ($lookupField === 'id') {
            $rowId = trim((string)($row['id'] ?? ''));
            $lookupId = trim((string)($lookupValue !== '' ? $lookupValue : $query));
            if ($lookupId !== '' && $rowId !== '') {
                if ($rowId === $lookupId) $matchedExact = true;
                elseif (voice_contains(voice_normalize_text($rowId), voice_normalize_text($lookupId))) $matchedPartial = true;
            }
        } elseif ($lookupField === 'nombre') {
            $field = voice_entity_name_field($kind, $entryScope);
            $rowValue = trim((string)($row[$field] ?? ''));
            $lookupName = trim((string)($lookupValue !== '' ? $lookupValue : $query));
            if ($lookupName !== '' && $rowValue !== '') {
                if (voice_normalize_text($rowValue) === voice_normalize_text($lookupName)) $matchedExact = true;
                elseif (voice_contains(voice_normalize_text($rowValue), voice_normalize_text($lookupName))) $matchedPartial = true;
            }
        } else {
            $fields = voice_entity_search_fields($kind, $entryScope);
            if (voice_row_exact_match($row, $query, array_merge($fields, array('id')))) $matchedExact = true;
            elseif (voice_row_partial_match($row, $query, $fields)) $matchedPartial = true;
        }

        if ($matchedExact) $exact[] = $entry;
        elseif ($matchedPartial) $partial[] = $entry;
    }

    return !empty($exact) ? $exact : $partial;
}

function voice_context_current_entity($targetType, $context, $scope = '') {
    $page = trim((string)($context['page'] ?? ''));
    $tab = trim((string)($context['tab'] ?? ''));
    $edit = trim((string)($context['edit'] ?? ''));
    $clienteId = trim((string)($context['cliente_id'] ?? ''));
    $scope = voice_normalize_scope($scope, $targetType);

    if ($targetType === 'clienta') {
        if ($scope === 'jostal' || ($page === 'jostal' && $tab === 'clientas')) {
            if ($edit !== '') {
                $row = storage_find_by_id('jostal_clientas.json', $edit);
                if ($row) return array('scope' => 'jostal', 'row' => $row);
            }
        }
        if ($clienteId !== '') {
            $row = storage_find_by_id('clientes.json', $clienteId);
            if ($row) return array('scope' => 'lamami', 'row' => $row);
        }
        if (($page === 'clientas' || ($page === 'lamami' && $tab === 'clientas')) && $edit !== '') {
            $row = storage_find_by_id('clientes.json', $edit);
            if ($row) return array('scope' => 'lamami', 'row' => $row);
        }
    }

    if ($targetType === 'interesada') {
        if ($scope === 'jostal' || ($page === 'jostal' && $tab === 'interesadas')) {
            if ($edit !== '') {
                $row = storage_find_by_id('jostal_interesadas.json', $edit);
                if ($row) return array('scope' => 'jostal', 'row' => $row);
            }
        }
        if (($page === 'interesadas' || ($page === 'lamami' && $tab === 'interesadas')) && $edit !== '') {
            $row = storage_find_by_id('interesadas.json', $edit);
            if ($row) return array('scope' => 'lamami', 'row' => $row);
        }
    }

    if ($targetType === 'casawasap_contacto' && $page === 'casawasap' && $edit !== '') {
        $row = storage_find_by_id('casawasap_contactos.json', $edit);
        if ($row) return array('scope' => 'casawasap', 'row' => $row);
    }

    if ($targetType === 'agenda_contact' && $page === 'josue' && $tab === 'agenda' && $edit !== '') {
        $row = storage_find_by_id('agenda.json', $edit);
        if ($row) return array('scope' => 'josue', 'row' => $row);
    }

    if ($targetType === 'bot' && $page === 'bots' && $edit !== '') {
        $row = storage_find_by_id('bots.json', $edit);
        if ($row) return array('scope' => 'bots', 'row' => $row);
    }

    if ($targetType === 'lamamibot' && ($page === 'lamamibot' || ($page === 'lamami' && $tab === 'lamamibot'))) {
        return array('scope' => 'lamamibot', 'row' => lamamibot_get());
    }

    return null;
}

function voice_resolve_entity($targetType, $query, $context, $scope = '', $lookupField = '', $lookupValue = '', $estado = '') {
    $targetType = trim((string)$targetType);
    $query = trim((string)$query);
    $scope = voice_normalize_scope($scope, $targetType);
    $context = voice_clean_context($context);

    $result = array(
        'status' => 'not_attempted',
        'kind' => $targetType,
        'scope' => $scope,
        'row' => null,
        'options' => array(),
        'message' => '',
    );

    if ($targetType === '' || $targetType === 'none') return $result;

    if ($query === '__CURRENT__') {
        $current = voice_context_current_entity($targetType, $context, $scope);
        if ($current && !empty($current['row'])) {
            $result['status'] = 'resolved';
            $result['row'] = $current['row'];
            $result['scope'] = $current['scope'] ?? $scope;
            $result['message'] = 'Entidad resuelta desde el contexto actual.';
        } else {
            $result['status'] = 'not_found';
            $result['message'] = 'No hay una entidad actual compatible en la pantalla.';
        }
        return $result;
    }

    if ($query === '' && $lookupValue === '') {
        $result['status'] = 'missing_query';
        $result['message'] = 'Falta el texto de búsqueda para resolver la entidad.';
        return $result;
    }

    $matches = voice_resolve_entity_matches($targetType, $query !== '' ? $query : $lookupValue, $scope, $lookupField, $lookupValue, $estado);
    if (empty($matches)) {
        $result['status'] = 'not_found';
        $result['message'] = 'No he encontrado coincidencias para "' . ($query !== '' ? $query : $lookupValue) . '".';
        return $result;
    }

    if (count($matches) === 1) {
        $result['status'] = 'resolved';
        $result['row'] = $matches[0]['row'];
        $result['scope'] = $matches[0]['scope'];
        $result['message'] = 'Entidad resuelta correctamente.';
        return $result;
    }

    $result['status'] = 'ambiguous';
    foreach (array_slice($matches, 0, 8) as $entry) {
        $result['options'][] = voice_entity_option($targetType, $entry['row'], $entry['scope']);
    }
    $result['message'] = 'He encontrado varias coincidencias para "' . ($query !== '' ? $query : $lookupValue) . '".';
    return $result;
}

function voice_resolve_subject_from_params($params, $context) {
    $entityReference = trim((string)($params['entity_reference'] ?? 'none'));
    $targetType = trim((string)($params['target_type'] ?? 'none'));
    $scope = trim((string)($params['target_scope'] ?? ''));
    $lookupField = trim((string)($params['lookup_field'] ?? ''));
    $lookupValue = trim((string)($params['lookup_value'] ?? ''));
    $estado = trim((string)($params['estado'] ?? ''));

    if ($entityReference === 'current' && $targetType !== 'none') {
        return voice_resolve_entity($targetType, '__CURRENT__', $context, $scope, $lookupField, $lookupValue, $estado);
    }
    if (!empty($params['clienta_query'])) return voice_resolve_entity('clienta', $params['clienta_query'], $context, $scope, $lookupField, $lookupValue, $estado);
    if (!empty($params['contacto_query'])) return voice_resolve_entity('casawasap_contacto', $params['contacto_query'], $context, $scope, $lookupField, $lookupValue, $estado);
    if (!empty($params['agenda_query'])) return voice_resolve_entity('agenda_contact', $params['agenda_query'], $context, $scope, $lookupField, $lookupValue, $estado);
    if (!empty($params['bot_query'])) return voice_resolve_entity('bot', $params['bot_query'], $context, $scope, $lookupField, $lookupValue, $estado);
    if (!empty($params['interesada_query'])) return voice_resolve_entity('interesada', $params['interesada_query'], $context, $scope, $lookupField, $lookupValue, $estado);
    if ($lookupValue !== '' && $targetType !== 'none') return voice_resolve_entity($targetType, $lookupValue, $context, $scope, $lookupField, $lookupValue, $estado);
    if ($targetType !== 'none') return voice_resolve_entity($targetType, '__CURRENT__', $context, $scope, $lookupField, $lookupValue, $estado);

    return array('status' => 'not_attempted', 'kind' => '', 'scope' => '', 'row' => null, 'options' => array(), 'message' => '');
}

function voice_pipeline_resolve($interpretation, $context = array()) {
    $intent = isset($interpretation['intent']) ? $interpretation['intent'] : 'unsupported_command';
    $params = isset($interpretation['params']) && is_array($interpretation['params']) ? $interpretation['params'] : voice_default_ai_params();
    $options = array();
    $resolvedEntities = array();
    $clarification = array();

    if ($intent === 'needs_clarification' || $intent === 'ask_clarification' || ($interpretation['stage'] ?? '') === 'needs_clarification') {
        $clarification = array(
            'reason' => 'interpretation',
            'missing_fields' => $interpretation['missing_fields'] ?? array(),
            'question' => $interpretation['message'] ?? 'Necesito una aclaración antes de continuar.',
        );
        return array(
            'stage' => 'needs_clarification',
            'intent' => 'ask_clarification',
            'params' => $params,
            'message' => $clarification['question'],
            'errors' => $interpretation['errors'] ?? array(),
            'options' => array(),
            'resolved_entities' => array(),
            'clarification' => $clarification,
            'source_intent' => $intent,
        );
    }

    $entityIntents = array('show_stats_clienta', 'open_edit_view', 'set_bot_runtime_mode', 'open_entity', 'search_entity', 'open_entity_by_phone', 'open_entity_by_name');
    if (in_array($intent, $entityIntents, true)) {
        $subject = voice_resolve_subject_from_params($params, $context);
        if ($subject['status'] === 'resolved' && !empty($subject['row'])) {
            $resolvedEntities[] = voice_entity_option($subject['kind'], $subject['row'], $subject['scope'] ?? '');
            if ($subject['kind'] === 'clienta') {
                $params['resolved_cliente_id'] = trim((string)($subject['row']['id'] ?? ''));
                $params['resolved_cliente_nombre'] = trim((string)($subject['row']['nombre'] ?? ''));
                $params['resolved_cliente_scope'] = trim((string)($subject['scope'] ?? ''));
            } elseif ($subject['kind'] === 'bot') {
                $params['resolved_bot_id'] = trim((string)($subject['row']['id'] ?? ''));
                $params['resolved_bot_nombre'] = trim((string)($subject['row']['nombre_bot'] ?? ''));
                $params['resolved_bot_scope'] = trim((string)($subject['scope'] ?? ''));
            } else {
                $params['resolved_subject_id'] = trim((string)($subject['row']['id'] ?? ''));
                $params['resolved_subject_scope'] = trim((string)($subject['scope'] ?? ''));
                $params['resolved_subject_label'] = voice_entity_label($subject['kind'], $subject['row'], $subject['scope'] ?? '');
            }
        } elseif ($subject['status'] === 'ambiguous') {
            $options = $subject['options'];
            $clarification = array(
                'reason' => 'ambiguous_entity',
                'entity_kind' => $subject['kind'],
                'question' => $subject['message'] !== '' ? $subject['message'] : 'He encontrado varias coincidencias. Necesito que concretes cuál es.',
                'missing_fields' => array('entity'),
            );
            return array(
                'stage' => 'needs_clarification',
                'intent' => 'ask_clarification',
                'params' => $params,
                'message' => $clarification['question'],
                'errors' => array(),
                'options' => $options,
                'resolved_entities' => array(),
                'clarification' => $clarification,
                'source_intent' => $intent,
            );
        } elseif ($subject['status'] === 'not_found') {
            $clarification = array(
                'reason' => 'entity_not_found',
                'entity_kind' => $subject['kind'],
                'question' => $subject['message'] !== '' ? $subject['message'] : 'No he encontrado ninguna coincidencia.',
                'missing_fields' => array('entity'),
            );
            return array(
                'stage' => 'needs_clarification',
                'intent' => 'ask_clarification',
                'params' => $params,
                'message' => $clarification['question'],
                'errors' => array(),
                'options' => array(),
                'resolved_entities' => array(),
                'clarification' => $clarification,
                'source_intent' => $intent,
            );
        }
    }

    if ($intent === 'list_entities' && ($params['target_scope'] ?? '') === '') {
        $params['target_scope'] = voice_default_scope_for_target($params['target_type'] ?? 'none', $context);
    }

    if ($intent === 'open_tab') {
        if ($params['page'] === '' && $params['tab'] !== '') {
            if (in_array($params['tab'], array('interesadas', 'clientas', 'lamamibot'), true)) {
                $params['page'] = (($params['target_scope'] ?? '') === 'jostal') ? 'jostal' : 'lamami';
            } elseif (in_array($params['tab'], array('agenda', 'telefonos', 'config', 'configm', 'waha', 'publias', 'captacion', 'sendtaxs', 'notas'), true)) {
                $params['page'] = 'josue';
            } elseif (in_array($params['tab'], array('ventas', 'informes'), true)) {
                $params['page'] = 'jostal';
            }
        }
    }

    if ($intent === 'open_page' && $params['page'] === 'agenda') $intent = 'open_agenda';
    if ($intent === 'open_page' && $params['page'] === 'lamamibot') $intent = 'open_lamamibot';
    if ($intent === 'open_page' && $params['page'] === 'bots') $intent = 'open_bots';
    if ($intent === 'open_page' && $params['page'] === 'informes') $intent = 'open_informes';
    if ($intent === 'open_page' && $params['page'] === 'dashboard') $intent = 'go_dashboard';

    return array(
        'stage' => 'resolved',
        'intent' => $intent,
        'params' => $params,
        'message' => isset($interpretation['message']) ? $interpretation['message'] : 'Orden resuelta.',
        'errors' => isset($interpretation['errors']) ? $interpretation['errors'] : array(),
        'options' => $options,
        'resolved_entities' => $resolvedEntities,
        'clarification' => $clarification,
        'source_intent' => $intent,
    );
}

function voice_pipeline_validate($resolved, $context = array()) {
    $intent = isset($resolved['intent']) ? $resolved['intent'] : 'unsupported_command';
    $params = isset($resolved['params']) && is_array($resolved['params']) ? $resolved['params'] : voice_default_ai_params();
    $missing = array();
    $clarification = isset($resolved['clarification']) && is_array($resolved['clarification']) ? $resolved['clarification'] : array();

    if ($intent === 'create_agenda_contact') {
        if ($params['nombre'] === '') $missing[] = 'nombre';
        if ($params['telefono'] === '') $missing[] = 'telefono';
    }
    if ($intent === 'create_eureka') {
        if ($params['descripcion'] === '') $missing[] = 'descripcion';
    }
    if ($intent === 'create_casawasap_contacto') {
        if ($params['telefono'] === '') $missing[] = 'telefono';
    }
    if ($intent === 'add_gasto') {
        if ($params['cantidad'] === '') $missing[] = 'cantidad';
        if ($params['descripcion'] === '') $missing[] = 'descripcion';
    }
    if ($intent === 'set_lamamibot_runtime_mode' && !in_array($params['mode'], array('start', 'stop'), true)) {
        $missing[] = 'mode';
    }
    if ($intent === 'set_bot_runtime_mode') {
        if (!in_array($params['mode'], array('start', 'stop'), true)) $missing[] = 'mode';
        if (empty($params['resolved_bot_id'])) $missing[] = 'bot';
    }
    if ($intent === 'show_stats_clienta' && empty($params['resolved_cliente_id'])) $missing[] = 'clienta';
    if ($intent === 'open_tab') {
        if ($params['page'] === '') $missing[] = 'page';
        if ($params['tab'] === '') $missing[] = 'tab';
    }
    if ($intent === 'open_page' && $params['page'] === '') $missing[] = 'page';

    if (in_array($intent, array('open_edit_view', 'open_entity', 'search_entity', 'open_entity_by_phone', 'open_entity_by_name'), true)) {
        if ($params['target_type'] === 'none') $missing[] = 'target_type';
        if (empty($params['resolved_cliente_id']) && empty($params['resolved_subject_id']) && empty($params['resolved_bot_id'])) {
            $missing[] = 'entity';
        }
    }

    if ($intent === 'list_entities') {
        if ($params['target_type'] === 'none') $missing[] = 'target_type';
        if (($params['target_scope'] ?? '') === '') {
            $params['target_scope'] = voice_default_scope_for_target($params['target_type'], $context);
        }
    }

    if (($intent === 'query_analytics' || $intent === 'query_comparison') && empty($params['analytics_kind'])) {
        $missing[] = 'analytics_kind';
    }

    if (!empty($missing)) {
        if (empty($clarification)) {
            $clarification = array(
                'reason' => 'missing_fields',
                'question' => 'Faltan datos antes de poder continuar con la orden.',
                'missing_fields' => $missing,
            );
        } else {
            $clarification['missing_fields'] = $missing;
        }

        return array(
            'stage' => 'needs_clarification',
            'intent' => 'ask_clarification',
            'params' => $params,
            'missing_fields' => $missing,
            'message' => $clarification['question'],
            'errors' => array(),
            'options' => isset($resolved['options']) ? $resolved['options'] : array(),
            'resolved_entities' => isset($resolved['resolved_entities']) ? $resolved['resolved_entities'] : array(),
            'clarification' => $clarification,
            'source_intent' => $resolved['source_intent'] ?? $intent,
        );
    }

    return array(
        'stage' => 'resolved',
        'intent' => $intent,
        'params' => $params,
        'missing_fields' => array(),
        'message' => $resolved['message'] ?? 'Orden validada.',
        'errors' => $resolved['errors'] ?? array(),
        'options' => $resolved['options'] ?? array(),
        'resolved_entities' => $resolved['resolved_entities'] ?? array(),
        'clarification' => $clarification,
        'source_intent' => $resolved['source_intent'] ?? $intent,
    );
}

function voice_current_user_key() {
    if (!empty($_SESSION['user']['username'])) return trim((string)$_SESSION['user']['username']);
    return 'session';
}

function voice_pending_file() {
    return 'voice_pending_actions.json';
}

function voice_log_file() {
    return 'voice_commands_log.json';
}

function voice_pending_gc() {
    $rows = storage_read(voice_pending_file());
    $now = time();
    $out = array();
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $expires = strtotime((string)($row['expires_at'] ?? ''));
        $status = trim((string)($row['status'] ?? 'pending'));
        if ($expires && $expires < $now) continue;
        if (in_array($status, array('cancelled', 'completed'), true)) continue;
        $out[] = $row;
    }
    storage_write(voice_pending_file(), array_values($out));
}

function voice_pending_create($kind, $payload) {
    voice_pending_gc();
    $rows = storage_read(voice_pending_file());
    $token = generate_id('vp');
    $record = array(
        'token' => $token,
        'kind' => $kind,
        'status' => 'pending',
        'owner' => voice_current_user_key(),
        'created_at' => now_datetime(),
        'updated_at' => now_datetime(),
        'expires_at' => date('Y-m-d H:i:s', time() + 1800),
    );
    foreach ((array)$payload as $key => $value) {
        $record[$key] = $value;
    }
    $rows[] = $record;
    storage_write(voice_pending_file(), array_values($rows));
    return $record;
}

function voice_pending_find($token) {
    voice_pending_gc();
    $rows = storage_read(voice_pending_file());
    $owner = voice_current_user_key();
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        if (($row['token'] ?? '') === $token && ($row['owner'] ?? '') === $owner) {
            return $row;
        }
    }
    return null;
}

function voice_pending_update($token, $changes) {
    $rows = storage_read(voice_pending_file());
    foreach ($rows as $i => $row) {
        if (!is_array($row)) continue;
        if (($row['token'] ?? '') === $token && ($row['owner'] ?? '') === voice_current_user_key()) {
            $rows[$i] = array_merge($row, (array)$changes, array('updated_at' => now_datetime()));
            storage_write(voice_pending_file(), array_values($rows));
            return $rows[$i];
        }
    }
    return null;
}

function voice_pending_close($token, $status) {
    return voice_pending_update($token, array('status' => $status));
}

function voice_find_latest_pending() {
    voice_pending_gc();
    $rows = storage_read(voice_pending_file());
    $owner = voice_current_user_key();
    $best = null;
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        if (($row['owner'] ?? '') !== $owner) continue;
        if (($row['status'] ?? 'pending') !== 'pending') continue;
        if ($best === null || strcmp((string)($row['updated_at'] ?? ''), (string)($best['updated_at'] ?? '')) > 0) {
            $best = $row;
        }
    }
    return $best;
}

function voice_log_command($entry) {
    $rows = storage_read(voice_log_file());
    $logId = generate_id('vlog');
    $entry = array_merge(array(
        'id' => $logId,
        'timestamp' => now_datetime(),
        'owner' => voice_current_user_key(),
        'transcript' => '',
        'normalized_text' => '',
        'intent' => '',
        'stage' => '',
        'params' => array(),
        'context' => array(),
        'result_message' => '',
        'execution_mode' => '',
        'errors' => array(),
        'clarification' => array(),
        'confirmation' => array(),
        'pending_token' => '',
        'ai' => array(),
    ), (array)$entry);
    $rows[] = $entry;
    if (count($rows) > 500) {
        $rows = array_slice($rows, -500);
    }
    storage_write(voice_log_file(), array_values($rows));
    return $logId;
}

function voice_intent_requires_confirmation($intent, $params = array()) {
    $sensitive = array(
        'delete_gasto_request',
        'delete_agenda_request',
        'baja_clienta',
        'alta_clienta',
        'baja_casawasap_cliente',
        'alta_casawasap_cliente',
        'set_interesada_estado',
        'set_casawasap_estado',
        'set_bot_runtime_mode',
        'set_lamamibot_runtime_mode',
        'delete_bot',
        'delete_interesada',
    );
    return in_array($intent, $sensitive, true);
}

function voice_response_pending_meta($record) {
    if (!$record) return array();
    return array(
        'token' => $record['token'] ?? '',
        'kind' => $record['kind'] ?? '',
        'status' => $record['status'] ?? '',
        'expires_at' => $record['expires_at'] ?? '',
    );
}

function voice_make_ux($headline, $detail, $suggestedReply = '', $review = false) {
    return array(
        'headline' => $headline,
        'detail' => $detail,
        'suggested_reply' => $suggestedReply,
        'review_required' => $review,
    );
}

function voice_merge_params($base, $patch) {
    $base = is_array($base) ? $base : voice_default_ai_params();
    foreach ((array)$patch as $key => $value) {
        if ($value === null) continue;
        if (is_string($value) && trim($value) === '') continue;
        $base[$key] = is_string($value) ? trim($value) : $value;
    }
    return $base;
}

function voice_option_selection_key($option) {
    if (!empty($option['selection_key'])) return trim((string)$option['selection_key']);
    return trim((string)($option['kind'] ?? '')) . ':' . trim((string)($option['scope'] ?? '')) . ':' . trim((string)($option['id'] ?? ''));
}

function voice_option_from_selection($options, $selection) {
    $selection = trim((string)$selection);
    foreach ((array)$options as $option) {
        if (!is_array($option)) continue;
        if (voice_option_selection_key($option) === $selection) return $option;
        if (($option['id'] ?? '') === $selection) return $option;
        if (voice_normalize_text($option['label'] ?? '') === voice_normalize_text($selection)) return $option;
    }
    return null;
}

function voice_extract_followup_patch($text, $missingFields, $params = array()) {
    $patch = array();
    $normalized = voice_normalize_text($text);
    $raw = trim((string)$text);
    foreach ((array)$missingFields as $field) {
        if ($field === 'telefono') {
            $phone = voice_extract_phone($raw);
            if ($phone !== '') {
                $patch['telefono'] = voice_normalize_phone($phone);
                $patch['lookup_field'] = 'telefono';
                $patch['lookup_value'] = $patch['telefono'];
            }
        } elseif ($field === 'nombre') {
            $name = voice_extract_name_hint($raw);
            if ($name === '' && $raw !== '' && voice_extract_phone($raw) === '') {
                $name = preg_replace('/\s+/', ' ', $raw);
            }
            if ($name !== '') $patch['nombre'] = trim((string)$name);
        } elseif ($field === 'cantidad') {
            $amount = voice_extract_amount($normalized);
            if ($amount !== '') $patch['cantidad'] = $amount;
        } elseif ($field === 'descripcion') {
            if (preg_match('/(?:en|de|concepto)\s+(.+)$/u', $normalized, $m)) {
                $patch['descripcion'] = trim((string)$m[1]);
            } elseif ($raw !== '') {
                $patch['descripcion'] = $raw;
            }
        } elseif ($field === 'mode') {
            if (voice_contains_any($normalized, array('apaga', 'stop', 'desactiva'))) $patch['mode'] = 'stop';
            if (voice_contains_any($normalized, array('enciende', 'start', 'activa'))) $patch['mode'] = 'start';
        } elseif ($field === 'clienta' || $field === 'entity') {
            $lookup = voice_extract_entity_lookup($raw, $normalized, $params['target_type'] ?? 'none');
            if (($params['target_type'] ?? 'none') === 'none') $patch['target_type'] = 'clienta';
            $patch['entity_reference'] = 'explicit';
            if (($lookup['field'] ?? '') !== '') {
                $patch['lookup_field'] = $lookup['field'];
                $patch['lookup_value'] = $lookup['value'];
            }
            $resolvedType = $patch['target_type'] ?? ($params['target_type'] ?? 'clienta');
            if ($resolvedType === 'clienta') $patch['clienta_query'] = $lookup['value'] ?: $raw;
            if ($resolvedType === 'interesada') $patch['interesada_query'] = $lookup['value'] ?: $raw;
            if ($resolvedType === 'agenda_contact') $patch['agenda_query'] = $lookup['value'] ?: $raw;
            if ($resolvedType === 'casawasap_contacto') $patch['contacto_query'] = $lookup['value'] ?: $raw;
        } elseif ($field === 'bot') {
            $lookup = voice_extract_entity_lookup($raw, $normalized, 'bot');
            $patch['bot_query'] = $lookup['value'] ?: $raw;
            $patch['target_type'] = 'bot';
            $patch['target_scope'] = 'bots';
            $patch['entity_reference'] = 'explicit';
            if (($lookup['field'] ?? '') !== '') {
                $patch['lookup_field'] = $lookup['field'];
                $patch['lookup_value'] = $lookup['value'];
            }
        } elseif ($field === 'periodo') {
            $period = voice_parse_period_from_text($normalized);
            if (!empty($period['from'])) $patch['from'] = $period['from'];
            if (!empty($period['to'])) $patch['to'] = $period['to'];
            if (!empty($period['period_hint'])) $patch['period_hint'] = $period['period_hint'];
        }
    }
    return $patch;
}

function voice_build_confirmation_message($validated) {
    $intent = $validated['intent'] ?? 'unsupported_command';
    $params = $validated['params'] ?? array();
    if ($intent === 'set_lamamibot_runtime_mode') {
        return 'Voy a ' . (($params['mode'] ?? '') === 'stop' ? 'apagar' : 'encender') . ' LamamiBot. ¿Confirmas?';
    }
    if ($intent === 'set_bot_runtime_mode') {
        $name = trim((string)($params['resolved_bot_nombre'] ?? 'este bot'));
        return 'Voy a ' . (($params['mode'] ?? '') === 'stop' ? 'apagar' : 'encender') . ' ' . $name . '. ¿Confirmas?';
    }
    return 'La acción es sensible. ¿Confirmas que quieres ejecutarla?';
}

function voice_make_pending_confirmation($validated, $transcript, $context) {
    $message = voice_build_confirmation_message($validated);
    $record = voice_pending_create('confirmation', array(
        'transcript' => $transcript,
        'normalized_text' => voice_normalize_text($transcript),
        'intent' => $validated['intent'],
        'params' => $validated['params'],
        'context' => $context,
        'resolved_entities' => $validated['resolved_entities'] ?? array(),
        'options' => array(),
        'missing_fields' => array(),
        'message' => $message,
        'validated_payload' => $validated,
    ));

    return voice_build_response(array(
        'ok' => true,
        'stage' => 'needs_confirmation',
        'transcript' => $transcript,
        'normalized_text' => voice_normalize_text($transcript),
        'intent' => $validated['intent'],
        'params' => $validated['params'],
        'context' => $context,
        'message' => $message,
        'resolved_entities' => $validated['resolved_entities'] ?? array(),
        'confirmation_required' => true,
        'pending' => voice_response_pending_meta($record),
        'ux' => voice_make_ux('Confirmación necesaria', $message, 'Puedes pulsar Confirmar o decir “sí, confirma”.', true),
        'execution_mode' => 'preview',
    ));
}

function voice_make_pending_clarification($stagePayload, $transcript, $context) {
    $message = $stagePayload['message'] ?? 'Necesito una aclaración para continuar.';
    $record = voice_pending_create('clarification', array(
        'transcript' => $transcript,
        'normalized_text' => voice_normalize_text($transcript),
        'intent' => $stagePayload['intent'] ?? 'ask_clarification',
        'params' => $stagePayload['params'] ?? array(),
        'context' => $context,
        'resolved_entities' => $stagePayload['resolved_entities'] ?? array(),
        'options' => $stagePayload['options'] ?? array(),
        'missing_fields' => $stagePayload['missing_fields'] ?? array(),
        'message' => $message,
        'clarification' => $stagePayload['clarification'] ?? array(),
        'resume_payload' => $stagePayload,
        'source_intent' => $stagePayload['source_intent'] ?? ($stagePayload['intent'] ?? 'unsupported_command'),
    ));

    return voice_build_response(array(
        'ok' => true,
        'stage' => 'needs_clarification',
        'transcript' => $transcript,
        'normalized_text' => voice_normalize_text($transcript),
        'intent' => 'ask_clarification',
        'params' => $stagePayload['params'] ?? array(),
        'missing_fields' => $stagePayload['missing_fields'] ?? array(),
        'context' => $context,
        'message' => $message,
        'options' => $stagePayload['options'] ?? array(),
        'resolved_entities' => $stagePayload['resolved_entities'] ?? array(),
        'pending' => voice_response_pending_meta($record),
        'ux' => voice_make_ux('Necesito aclaración', $message, 'Responde con el dato que falta o pulsa una de las opciones.', true),
        'execution_mode' => 'preview',
    ));
}

function voice_run_validated_payload($validated, $context) {
    return voice_pipeline_execute($validated, $context);
}

function voice_resolve_followup_option($pending, $selection) {
    $option = voice_option_from_selection($pending['options'] ?? array(), $selection);
    if (!$option) return null;
    $params = $pending['params'] ?? voice_default_ai_params();
    $scope = trim((string)($option['scope'] ?? ''));
    if (($option['kind'] ?? '') === 'clienta') {
        $params['target_type'] = 'clienta';
        $params['target_scope'] = $scope;
        $params['entity_reference'] = 'explicit';
        $params['clienta_query'] = $option['label'] ?? '';
        $params['resolved_cliente_id'] = $option['id'] ?? '';
        $params['resolved_cliente_scope'] = $scope;
    } elseif (($option['kind'] ?? '') === 'bot') {
        $params['target_type'] = 'bot';
        $params['target_scope'] = $scope !== '' ? $scope : 'bots';
        $params['entity_reference'] = 'explicit';
        $params['bot_query'] = $option['label'] ?? '';
        $params['resolved_bot_id'] = $option['id'] ?? '';
        $params['resolved_bot_scope'] = $scope;
    } else {
        $params['target_type'] = $option['kind'] ?? ($params['target_type'] ?? 'none');
        if ($scope !== '') $params['target_scope'] = $scope;
        $params['entity_reference'] = 'explicit';
        $params['resolved_subject_id'] = $option['id'] ?? '';
        $params['resolved_subject_scope'] = $scope;
    }
    return $params;
}

function voice_handle_pending_interaction($pending, $commandText, $context, $interaction) {
    $normalized = voice_normalize_text($commandText);
    $followAction = trim((string)($interaction['followup_action'] ?? ''));
    $followValue = trim((string)($interaction['followup_value'] ?? ''));

    if (!$pending) {
        return voice_build_response(array(
            'ok' => false,
            'stage' => 'error',
            'intent' => 'unsupported_command',
            'context' => $context,
            'message' => 'La acción pendiente ya no está disponible.',
            'errors' => array('pending_not_found'),
            'ux' => voice_make_ux('Pendiente no disponible', 'La acción pendiente ya caducó o no existe.', '', true),
        ));
    }

    if ($pending['kind'] === 'confirmation') {
        if ($followAction === 'cancel' || voice_is_cancellation_text($normalized)) {
            voice_pending_close($pending['token'], 'cancelled');
            return voice_build_response(array(
                'ok' => true,
                'stage' => 'executed',
                'intent' => 'cancel_pending_action',
                'context' => $context,
                'message' => 'Acción cancelada. No se ha realizado ningún cambio.',
                'execution_mode' => 'preview',
                'ux' => voice_make_ux('Acción cancelada', 'No se ha realizado ningún cambio.', '', false),
            ));
        }

        if ($followAction === 'confirm' || voice_is_confirmation_text($normalized)) {
            $validated = $pending['validated_payload'] ?? array();
            $executed = voice_run_validated_payload($validated, $pending['context'] ?? $context);
            voice_pending_close($pending['token'], 'completed');
            return voice_build_response(array(
                'ok' => empty($executed['errors']) && (($executed['stage'] ?? '') !== 'error'),
                'stage' => $executed['stage'] ?? 'executed',
                'transcript' => $commandText,
                'normalized_text' => $normalized,
                'intent' => $validated['intent'] ?? 'unsupported_command',
                'params' => $validated['params'] ?? array(),
                'context' => $context,
                'message' => $executed['message'] ?? 'Acción confirmada y ejecutada.',
                'redirect_url' => $executed['redirect_url'] ?? '',
                'resolved_entities' => $validated['resolved_entities'] ?? array(),
                'errors' => $executed['errors'] ?? array(),
                'execution_mode' => $executed['execution_mode'] ?? 'write',
                'pending' => voice_response_pending_meta(array_merge($pending, array('status' => 'completed'))),
                'ux' => voice_make_ux('Acción ejecutada', $executed['message'] ?? 'Acción ejecutada.', '', false),
            ));
        }

        return voice_build_response(array(
            'ok' => true,
            'stage' => 'needs_confirmation',
            'intent' => $pending['intent'] ?? 'unsupported_command',
            'params' => $pending['params'] ?? array(),
            'context' => $context,
            'message' => $pending['message'] ?? 'Necesito confirmación para continuar.',
            'resolved_entities' => $pending['resolved_entities'] ?? array(),
            'confirmation_required' => true,
            'pending' => voice_response_pending_meta($pending),
            'ux' => voice_make_ux('Confirmación pendiente', $pending['message'] ?? 'Necesito confirmación.', 'Pulsa Confirmar o di “sí, confirma”.', true),
            'execution_mode' => 'preview',
        ));
    }

    if ($pending['kind'] === 'clarification') {
        if ($followAction === 'cancel' || voice_is_cancellation_text($normalized)) {
            voice_pending_close($pending['token'], 'cancelled');
            return voice_build_response(array(
                'ok' => true,
                'stage' => 'executed',
                'intent' => 'cancel_pending_action',
                'context' => $context,
                'message' => 'Aclaración cancelada. No se ha ejecutado nada.',
                'execution_mode' => 'preview',
                'ux' => voice_make_ux('Aclaración cancelada', 'No se ha ejecutado nada.', '', false),
            ));
        }

        $params = $pending['params'] ?? voice_default_ai_params();
        if ($followAction === 'select_option' && $followValue !== '') {
            $selectedParams = voice_resolve_followup_option($pending, $followValue);
            if ($selectedParams !== null) {
                $params = array_merge($params, $selectedParams);
            }
        } elseif ($commandText !== '') {
            $patch = voice_extract_followup_patch($commandText, $pending['missing_fields'] ?? array(), $params);
            if (!empty($pending['options'])) {
                $selectedParams = voice_resolve_followup_option($pending, $commandText);
                if ($selectedParams !== null) {
                    $patch = array_merge($patch, $selectedParams);
                }
            }
            $params = voice_merge_params($params, $patch);
        }

        $resume = $pending['resume_payload'] ?? array();
        $resume['params'] = $params;
        $resolved = voice_pipeline_resolve(array(
            'intent' => $pending['source_intent'] ?? ($pending['intent'] ?? ($resume['source_intent'] ?? ($resume['intent'] ?? 'unsupported_command'))),
            'params' => $params,
            'message' => 'Aclaración recibida. Reanudando la orden.',
            'errors' => array(),
            'stage' => 'interpreted',
        ), $pending['context'] ?? $context);
        $validated = voice_pipeline_validate($resolved, $pending['context'] ?? $context);

        if (($validated['stage'] ?? '') === 'needs_clarification') {
            voice_pending_update($pending['token'], array(
                'params' => $validated['params'] ?? $params,
                'options' => $validated['options'] ?? array(),
                'missing_fields' => $validated['missing_fields'] ?? array(),
                'message' => $validated['message'] ?? ($pending['message'] ?? ''),
                'resume_payload' => $validated,
            ));
            return voice_build_response(array(
                'ok' => true,
                'stage' => 'needs_clarification',
                'transcript' => $commandText,
                'normalized_text' => $normalized,
                'intent' => 'ask_clarification',
                'params' => $validated['params'] ?? $params,
                'missing_fields' => $validated['missing_fields'] ?? array(),
                'context' => $context,
                'message' => $validated['message'] ?? 'Todavía necesito un dato más.',
                'options' => $validated['options'] ?? array(),
                'resolved_entities' => $validated['resolved_entities'] ?? array(),
                'pending' => voice_response_pending_meta($pending),
                'ux' => voice_make_ux('Aclaración pendiente', $validated['message'] ?? 'Todavía necesito un dato más.', 'Puedes responder con el dato que falta.', true),
                'execution_mode' => 'preview',
            ));
        }

        if (voice_intent_requires_confirmation($validated['intent'] ?? '', $validated['params'] ?? array())) {
            voice_pending_close($pending['token'], 'completed');
            return voice_make_pending_confirmation($validated, $commandText !== '' ? $commandText : ($pending['transcript'] ?? ''), $context);
        }

        $executed = voice_run_validated_payload($validated, $pending['context'] ?? $context);
        voice_pending_close($pending['token'], 'completed');
        return voice_build_response(array(
            'ok' => empty($executed['errors']) && (($executed['stage'] ?? '') !== 'error'),
            'stage' => $executed['stage'] ?? 'executed',
            'transcript' => $commandText,
            'normalized_text' => $normalized,
            'intent' => $validated['intent'] ?? 'unsupported_command',
            'params' => $executed['params'] ?? ($validated['params'] ?? array()),
            'context' => $context,
            'message' => $executed['message'] ?? 'Orden completada tras la aclaración.',
            'redirect_url' => $executed['redirect_url'] ?? '',
            'resolved_entities' => $validated['resolved_entities'] ?? array(),
            'errors' => $executed['errors'] ?? array(),
            'execution_mode' => $executed['execution_mode'] ?? 'preview',
            'analytics' => $executed['analytics'] ?? array(),
            'pending' => voice_response_pending_meta(array_merge($pending, array('status' => 'completed'))),
            'ux' => voice_make_ux('Orden completada', $executed['message'] ?? 'Orden completada.', '', false),
        ));
    }

    return voice_build_response(array(
        'ok' => false,
        'stage' => 'error',
        'intent' => 'unsupported_command',
        'context' => $context,
        'message' => 'No se ha podido reanudar la acción pendiente.',
        'errors' => array('pending_kind_not_supported'),
        'ux' => voice_make_ux('Pendiente no soportado', 'No se ha podido reanudar la acción pendiente.', '', true),
    ));
}

function voice_amount_in_range($dateValue, $from, $to) {
    $ts = business_parse_ts($dateValue);
    if (!$ts) return false;
    list($fromTs, $toTs) = business_range_bounds($from, $to);
    if ($fromTs !== null && $ts < $fromTs) return false;
    if ($toTs !== null && $ts > $toTs) return false;
    return true;
}

function voice_branch_label($branch) {
    $map = array(
        'lamami' => 'LaMami',
        'casawasap' => 'Casawasap',
        'jostal' => 'Jostal',
    );
    return $map[$branch] ?? ucfirst((string)$branch);
}

function voice_build_analytics($params, $context) {
    $filters = voice_report_filters_from_params($params, $context);
    $summary = array(
        'filters' => $filters,
        'ingresos_total' => 0.0,
        'gastos_total' => 0.0,
        'balance_total' => 0.0,
        'branches' => array(
            'lamami' => 0.0,
            'casawasap' => 0.0,
            'jostal' => 0.0,
        ),
        'best_clienta' => array(),
        'insights' => array(),
        'cards' => array(),
    );

    $clientTotals = array();

    foreach (storage_read('clientes.json') as $row) {
        if (!voice_amount_in_range($row['fecha_alta'] ?? '', $filters['from'], $filters['to'])) continue;
        $amount = (float)($row['precio_alta'] ?? 0);
        $summary['ingresos_total'] += $amount;
        $summary['branches']['lamami'] += $amount;
        $id = trim((string)($row['id'] ?? ''));
        $name = trim((string)($row['nombre'] ?? 'Clienta'));
        if ($id !== '') {
            if (!isset($clientTotals[$id])) $clientTotals[$id] = array('id' => $id, 'nombre' => $name, 'rama' => 'lamami', 'total' => 0.0);
            $clientTotals[$id]['total'] += $amount;
        }
    }

    foreach (storage_read('leads.json') as $row) {
        if (!voice_amount_in_range($row['fecha_hora'] ?? '', $filters['from'], $filters['to'])) continue;
        $amount = (float)($row['precio_lead'] ?? 0);
        $summary['ingresos_total'] += $amount;
        $summary['branches']['lamami'] += $amount;
        $id = trim((string)($row['cliente_id'] ?? ''));
        $name = trim((string)($row['cliente_nombre'] ?? 'Clienta'));
        if ($id !== '') {
            if (!isset($clientTotals[$id])) $clientTotals[$id] = array('id' => $id, 'nombre' => $name, 'rama' => 'lamami', 'total' => 0.0);
            $clientTotals[$id]['total'] += $amount;
        }
    }

    foreach (storage_read('casawasap_pagos.json') as $row) {
        if (!voice_amount_in_range($row['fecha_hora'] ?? '', $filters['from'], $filters['to'])) continue;
        $amount = (float)($row['importe'] ?? 0);
        $summary['ingresos_total'] += $amount;
        $summary['branches']['casawasap'] += $amount;
        $id = trim((string)($row['cliente_id'] ?? ''));
        $name = trim((string)($row['cliente_nombre'] ?? 'Cliente Casawasap'));
        if ($id !== '') {
            if (!isset($clientTotals[$id])) $clientTotals[$id] = array('id' => $id, 'nombre' => $name, 'rama' => 'casawasap', 'total' => 0.0);
            $clientTotals[$id]['total'] += $amount;
        }
    }

    foreach (storage_read('jostal_leads.json') as $row) {
        if (!voice_amount_in_range($row['created_at'] ?? '', $filters['from'], $filters['to'])) continue;
        $amount = (float)($row['precio'] ?? 0);
        $summary['ingresos_total'] += $amount;
        $summary['branches']['jostal'] += $amount;
        $id = trim((string)($row['clienta_id'] ?? ''));
        $name = trim((string)($row['clienta_nombre'] ?? 'Clienta Jostal'));
        if ($id !== '') {
            if (!isset($clientTotals[$id])) $clientTotals[$id] = array('id' => $id, 'nombre' => $name, 'rama' => 'jostal', 'total' => 0.0);
            $clientTotals[$id]['total'] += $amount;
        }
    }

    foreach (storage_read('jostal_ventas.json') as $row) {
        if (!voice_amount_in_range($row['created_at'] ?? '', $filters['from'], $filters['to'])) continue;
        $amount = (float)($row['precio'] ?? 0);
        $summary['ingresos_total'] += $amount;
        $summary['branches']['jostal'] += $amount;
    }

    foreach (storage_read('gastos.json') as $row) {
        if (!voice_amount_in_range($row['created_at'] ?? '', $filters['from'], $filters['to'])) continue;
        $summary['gastos_total'] += (float)($row['cantidad'] ?? 0);
    }

    $summary['balance_total'] = $summary['ingresos_total'] - $summary['gastos_total'];

    uasort($clientTotals, function ($a, $b) {
        return ($b['total'] <=> $a['total']);
    });
    $best = reset($clientTotals);
    if ($best) {
        $summary['best_clienta'] = $best;
    }

    $topBranch = '';
    $topBranchAmount = null;
    foreach ($summary['branches'] as $branch => $amount) {
        if ($topBranchAmount === null || $amount > $topBranchAmount) {
            $topBranchAmount = $amount;
            $topBranch = $branch;
        }
    }
    if ($topBranch !== '') {
        $summary['insights'][] = voice_branch_label($topBranch) . ' lidera el periodo con ' . euro($topBranchAmount) . '.';
    }
    if (!empty($summary['best_clienta'])) {
        $summary['insights'][] = $summary['best_clienta']['nombre'] . ' es la mejor ficha del periodo con ' . euro($summary['best_clienta']['total']) . '.';
    }
    $summary['insights'][] = 'Balance del periodo: ' . euro($summary['balance_total']) . '.';

    $summary['cards'] = array(
        array('label' => 'Ingresos', 'value' => euro($summary['ingresos_total'])),
        array('label' => 'Gastos', 'value' => euro($summary['gastos_total'])),
        array('label' => 'Balance', 'value' => euro($summary['balance_total'])),
    );

    return $summary;
}

function voice_execute_analytics($intent, $params, $context) {
    $analytics = voice_build_analytics($params, $context);
    $message = 'Consulta analítica preparada.';
    if (($params['analytics_kind'] ?? '') === 'branches') {
        $message = 'Comparativa de ramas lista.';
    } elseif (($params['analytics_kind'] ?? '') === 'best_clienta') {
        $message = !empty($analytics['best_clienta'])
            ? 'La mejor ficha del periodo es ' . $analytics['best_clienta']['nombre'] . ' con ' . euro($analytics['best_clienta']['total']) . '.'
            : 'No hay una mejor ficha clara para el periodo filtrado.';
    } elseif (($params['analytics_kind'] ?? '') === 'summary') {
        $message = 'Resumen de ingresos y gastos preparado.';
    } elseif (($params['analytics_kind'] ?? '') === 'insights') {
        $message = 'Lectura analítica simple preparada.';
    }

    return array(
        'stage' => 'executed',
        'intent' => $intent,
        'params' => array_merge($params, $analytics['filters']),
        'message' => $message,
        'redirect_url' => '',
        'execution_mode' => 'readonly',
        'errors' => array(),
        'analytics' => $analytics,
    );
}

function voice_create_agenda_contact($params) {
    $row = array(
        'id' => generate_id('ag'),
        'nombre' => trim((string)($params['nombre'] ?? '')),
        'telefono' => trim((string)($params['telefono'] ?? '')),
        'observaciones' => trim((string)($params['observaciones'] ?? '')),
        'updated_at' => now_datetime(),
        'created_at' => now_datetime(),
    );

    storage_upsert('agenda.json', $row);

    return array(
        'ok' => true,
        'row' => $row,
        'message' => 'Contacto de agenda creado correctamente.',
        'redirect_url' => 'index.php?page=josue&tab=agenda&edit=' . urlencode($row['id']),
    );
}

function voice_create_eureka($params) {
    $descripcion = trim((string)($params['descripcion'] ?? ''));
    $row = eureka_create_row($descripcion, 'voice');
    storage_upsert('eurekas.json', $row);

    return array(
        'ok' => true,
        'row' => $row,
        'message' => 'Eureka guardada correctamente.',
        'redirect_url' => 'index.php?page=josue&tab=eurekas&edit=' . urlencode($row['id']),
    );
}

function voice_create_casawasap_contacto($params) {
    $row = array(
        'id' => generate_id('casa'),
        'telefono' => trim((string)($params['telefono'] ?? '')),
        'notas' => trim((string)($params['notas'] ?? '')),
        'quien_lo_trae' => trim((string)($params['quien_lo_trae'] ?? 'voz')),
        'estado' => 'interesado',
        'updated_at' => now_datetime(),
        'created_at' => now_datetime(),
    );
    if (trim((string)($params['nombre'] ?? '')) !== '') {
        $row['nombre'] = trim((string)$params['nombre']);
    }

    storage_upsert('casawasap_contactos.json', $row);

    return array(
        'ok' => true,
        'row' => $row,
        'message' => 'Contacto de Casawasap creado correctamente.',
        'redirect_url' => 'index.php?page=casawasap&edit=' . urlencode($row['id']),
    );
}

function voice_add_gasto($params) {
    $row = array(
        'id' => generate_id('gasto'),
        'cantidad' => to_float($params['cantidad'] ?? '', 0),
        'descripcion' => trim((string)($params['descripcion'] ?? '')),
        'created_at' => now_datetime(),
        'updated_at' => now_datetime(),
    );

    storage_upsert('gastos.json', $row);

    return array(
        'ok' => true,
        'row' => $row,
        'message' => 'Gasto registrado correctamente.',
        'redirect_url' => 'index.php?page=gastos',
    );
}

function voice_set_lamamibot_runtime_mode($mode) {
    $cfg = lamamibot_get();
    $runtimeBot = array(
        'nombre_bot' => function_exists('lamamibot_bot_slug') ? lamamibot_bot_slug($cfg) : (string)($cfg['nombre_bot'] ?? 'lamamibot'),
        'generated_assets' => is_array($cfg['generated_assets'] ?? null) ? $cfg['generated_assets'] : array(),
    );

    list($ok, $written, $errors) = bot_runtime_set_mode($runtimeBot, $mode);
    if (!$ok) {
        return array(
            'ok' => false,
            'errors' => $errors,
            'message' => 'No se pudo cambiar el runtime de LamamiBot.',
            'redirect_url' => lamami_tab_url('lamamibot'),
        );
    }

    return array(
        'ok' => true,
        'message' => 'LamamiBot ' . ($mode === 'start' ? 'encendido' : 'apagado') . ' correctamente.',
        'redirect_url' => lamami_tab_url('lamamibot'),
        'written' => $written,
    );
}

function voice_set_bot_runtime_mode($botId, $mode) {
    $bot = storage_find_by_id('bots.json', $botId);
    if (!$bot) {
        return array(
            'ok' => false,
            'errors' => array('bot_not_found'),
            'message' => 'No se encontró el bot indicado.',
            'redirect_url' => 'index.php?page=bots',
        );
    }

    list($ok, $written, $errors) = bot_runtime_set_mode($bot, $mode);
    if (!$ok) {
        return array(
            'ok' => false,
            'errors' => $errors,
            'message' => 'No se pudo cambiar el runtime del bot.',
            'redirect_url' => 'index.php?page=bots&edit=' . urlencode($botId),
        );
    }

    return array(
        'ok' => true,
        'message' => 'Bot ' . ($bot['nombre_bot'] ?? 'sin nombre') . ' ' . ($mode === 'start' ? 'encendido' : 'apagado') . ' correctamente.',
        'redirect_url' => 'index.php?page=bots&edit=' . urlencode($botId),
        'written' => $written,
    );
}

function voice_entity_list_url($targetType, $scope, $params = array()) {
    $scope = voice_normalize_scope($scope, $targetType);
    if ($targetType === 'clienta') {
        return $scope === 'jostal' ? 'index.php?page=jostal&tab=clientas' : lamami_tab_url('clientas');
    }
    if ($targetType === 'interesada') {
        return $scope === 'jostal' ? 'index.php?page=jostal&tab=interesadas' : lamami_tab_url('interesadas');
    }
    if ($targetType === 'casawasap_contacto') return 'index.php?page=casawasap';
    if ($targetType === 'agenda_contact') return 'index.php?page=josue&tab=agenda';
    if ($targetType === 'bot') return 'index.php?page=bots';
    if ($targetType === 'lamamibot') return lamami_tab_url('lamamibot');
    return voice_page_url($scope !== '' ? $scope : 'dashboard');
}

function voice_entity_edit_url($targetType, $scope, $id) {
    $scope = voice_normalize_scope($scope, $targetType);
    $id = trim((string)$id);
    if ($id === '' && $targetType !== 'lamamibot') return '';
    if ($targetType === 'clienta') {
        return $scope === 'jostal' ? 'index.php?page=jostal&tab=clientas&edit=' . urlencode($id) : lamami_tab_url('clientas', array('edit' => $id));
    }
    if ($targetType === 'interesada') {
        return $scope === 'jostal' ? 'index.php?page=jostal&tab=interesadas&edit=' . urlencode($id) : lamami_tab_url('interesadas', array('edit' => $id));
    }
    if ($targetType === 'casawasap_contacto') return 'index.php?page=casawasap&edit=' . urlencode($id);
    if ($targetType === 'agenda_contact') return 'index.php?page=josue&tab=agenda&edit=' . urlencode($id);
    if ($targetType === 'bot') return 'index.php?page=bots&edit=' . urlencode($id);
    if ($targetType === 'lamamibot') return lamami_tab_url('lamamibot');
    return '';
}

function voice_pipeline_execute($validated, $context = array()) {
    $intent = isset($validated['intent']) ? $validated['intent'] : 'unsupported_command';
    $params = isset($validated['params']) && is_array($validated['params']) ? $validated['params'] : voice_default_ai_params();

    if ($intent === 'go_dashboard') {
        return array(
            'stage' => 'executed',
            'intent' => $intent,
            'params' => $params,
            'message' => 'Abriendo dashboard.',
            'redirect_url' => voice_page_url('dashboard'),
            'execution_mode' => 'navigation',
            'errors' => array(),
        );
    }

    if ($intent === 'open_page' || $intent === 'open_agenda' || $intent === 'open_lamamibot' || $intent === 'open_bots' || $intent === 'open_avisos' || $intent === 'open_informes') {
        $redirect = '';
        if ($intent === 'open_agenda') $redirect = 'index.php?page=josue&tab=agenda';
        elseif ($intent === 'open_lamamibot') $redirect = lamami_tab_url('lamamibot');
        elseif ($intent === 'open_bots') $redirect = 'index.php?page=bots';
        elseif ($intent === 'open_avisos') $redirect = 'index.php?page=avisos';
        elseif ($intent === 'open_informes') $redirect = 'index.php?page=informes';
        else $redirect = voice_page_url($params['page'] ?? '');

        return array(
            'stage' => 'executed',
            'intent' => $intent,
            'params' => $params,
            'message' => 'Orden de navegación resuelta.',
            'redirect_url' => $redirect,
            'execution_mode' => 'navigation',
            'errors' => array(),
        );
    }

    if ($intent === 'open_tab') {
        return array(
            'stage' => 'executed',
            'intent' => $intent,
            'params' => $params,
            'message' => 'Abriendo subsección solicitada.',
            'redirect_url' => voice_tab_url($params['page'] ?? '', $params['tab'] ?? '', $params['view'] ?? '', $params['avtab'] ?? ''),
            'execution_mode' => 'navigation',
            'errors' => array(),
        );
    }

    if ($intent === 'filter_informes' || $intent === 'show_stats_clienta' || $intent === 'show_gastos_periodo' || $intent === 'show_ingresos_periodo' || $intent === 'show_gridmensual') {
        $filters = voice_report_filters_from_params($params, $context);
        if ($intent === 'show_stats_clienta' && !empty($params['resolved_cliente_id'])) {
            $filters['cliente_id'] = $params['resolved_cliente_id'];
        }
        if ($intent === 'show_gastos_periodo') $filters['tipo'] = 'gastos';
        if ($intent === 'show_ingresos_periodo') $filters['tipo'] = 'ingresos';
        if ($intent === 'show_gridmensual') $filters['view'] = 'grid';

        return array(
            'stage' => 'executed',
            'intent' => $intent,
            'params' => array_merge($params, $filters),
            'message' => $intent === 'show_stats_clienta' && !empty($params['resolved_cliente_nombre'])
                ? 'Abriendo informes filtrados para ' . $params['resolved_cliente_nombre'] . '.'
                : 'Abriendo informes con los filtros indicados.',
            'redirect_url' => voice_report_url($filters),
            'execution_mode' => 'navigation',
            'errors' => array(),
        );
    }

    if ($intent === 'query_comparison' || $intent === 'query_analytics') {
        return voice_execute_analytics($intent, $params, $context);
    }

    if (in_array($intent, array('open_edit_view', 'open_entity', 'search_entity', 'open_entity_by_phone', 'open_entity_by_name'), true)) {
        $targetType = $params['target_type'] ?? 'none';
        $scope = $params['resolved_subject_scope'] ?? ($params['resolved_cliente_scope'] ?? ($params['resolved_bot_scope'] ?? ($params['target_scope'] ?? '')));
        $id = '';
        if (!empty($params['resolved_cliente_id'])) $id = $params['resolved_cliente_id'];
        elseif (!empty($params['resolved_subject_id'])) $id = $params['resolved_subject_id'];
        elseif (!empty($params['resolved_bot_id'])) $id = $params['resolved_bot_id'];
        if ($targetType === 'clienta' && $scope === '') $scope = $params['resolved_cliente_scope'] ?? voice_default_scope_for_target('clienta', $context);
        if ($targetType === 'bot' && $scope === '') $scope = 'bots';
        $redirect = voice_entity_edit_url($targetType, $scope, $id);
        return array(
            'stage' => 'executed',
            'intent' => $intent,
            'params' => $params,
            'message' => 'Abriendo ficha solicitada.',
            'redirect_url' => $redirect,
            'execution_mode' => 'navigation',
            'errors' => array(),
        );
    }

    if ($intent === 'list_entities') {
        $scope = $params['target_scope'] ?? voice_default_scope_for_target($params['target_type'] ?? 'none', $context);
        $redirect = voice_entity_list_url($params['target_type'] ?? 'none', $scope, $params);
        return array(
            'stage' => 'executed',
            'intent' => $intent,
            'params' => $params,
            'message' => 'Abriendo el listado solicitado.',
            'redirect_url' => $redirect,
            'execution_mode' => 'navigation',
            'errors' => array(),
        );
    }

    if ($intent === 'create_agenda_contact') {
        $exec = voice_create_agenda_contact($params);
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent,
            'params' => $params,
            'message' => $exec['message'],
            'redirect_url' => $exec['redirect_url'],
            'execution_mode' => 'write',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'create_eureka') {
        $exec = voice_create_eureka($params);
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent,
            'params' => $params,
            'message' => $exec['message'],
            'redirect_url' => $exec['redirect_url'],
            'execution_mode' => 'write',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'create_casawasap_contacto') {
        $exec = voice_create_casawasap_contacto($params);
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent,
            'params' => $params,
            'message' => $exec['message'],
            'redirect_url' => $exec['redirect_url'],
            'execution_mode' => 'write',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'add_gasto') {
        $exec = voice_add_gasto($params);
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent,
            'params' => $params,
            'message' => $exec['message'],
            'redirect_url' => $exec['redirect_url'],
            'execution_mode' => 'write',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'set_lamamibot_runtime_mode') {
        $exec = voice_set_lamamibot_runtime_mode($params['mode']);
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent,
            'params' => $params,
            'message' => $exec['message'],
            'redirect_url' => $exec['redirect_url'],
            'execution_mode' => 'write',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'set_bot_runtime_mode') {
        $exec = voice_set_bot_runtime_mode($params['resolved_bot_id'], $params['mode']);
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent,
            'params' => $params,
            'message' => $exec['message'],
            'redirect_url' => $exec['redirect_url'],
            'execution_mode' => 'write',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    return array(
        'stage' => 'interpreted',
        'intent' => $intent,
        'params' => $params,
        'message' => 'La orden se ha interpretado, pero aún no tiene ejecución directa en esta fase.',
        'redirect_url' => '',
        'execution_mode' => 'preview',
        'errors' => array(),
    );
}

function voice_handle_command($commandText, $context = array(), $interaction = array(), $speechMeta = array()) {
    $speechMeta = voice_sanitize_speech_meta($speechMeta);
    $transcript = trim((string)$commandText);
    if (($speechMeta['source'] ?? '') === 'speech') {
        $transcript = voice_sanitize_speech_noise($transcript);
        if ($transcript === '' && !empty($speechMeta['alternatives'][0])) {
            $transcript = trim((string)$speechMeta['alternatives'][0]);
        }
    }
    $context = voice_clean_context($context);
    $interaction = is_array($interaction) ? $interaction : array();
    $pendingToken = trim((string)($interaction['pending_token'] ?? ''));
    $pending = null;

    if ($pendingToken !== '') {
        $pending = voice_pending_find($pendingToken);
    } elseif ($transcript !== '') {
        $interpretedProbe = voice_interpret_with_rules($transcript, $context);
        if (in_array($interpretedProbe['intent'] ?? '', array('confirm_pending_action', 'cancel_pending_action'), true)) {
            $pending = voice_find_latest_pending();
        }
    }

    if ($pending) {
        $response = voice_handle_pending_interaction($pending, $transcript, $context, $interaction);
        $response['log_id'] = voice_log_command(array(
            'transcript' => $transcript,
            'normalized_text' => voice_normalize_text($transcript),
            'intent' => $response['intent'] ?? '',
            'stage' => $response['stage'] ?? '',
            'params' => $response['params'] ?? array(),
            'context' => $context,
            'result_message' => $response['message'] ?? '',
            'execution_mode' => $response['execution_mode'] ?? '',
            'errors' => $response['errors'] ?? array(),
            'clarification' => $response['pending'] ?? array(),
            'confirmation' => $response['pending'] ?? array(),
            'pending_token' => $pending['token'] ?? '',
            'ai' => $response['ai'] ?? array(),
        ));
        return $response;
    }

    if ($transcript === '') {
        $response = voice_build_response(array(
            'ok' => false,
            'stage' => 'error',
            'transcript' => '',
            'normalized_text' => '',
            'intent' => 'unsupported_command',
            'context' => $context,
            'message' => 'No has dictado ni escrito ninguna orden.',
            'errors' => array('empty_command'),
            'ux' => voice_make_ux('Orden vacía', 'No has dictado ni escrito ninguna orden.', '', true),
        ));
        $response['log_id'] = voice_log_command(array(
            'transcript' => '',
            'normalized_text' => '',
            'intent' => 'unsupported_command',
            'stage' => 'error',
            'params' => array(),
            'context' => $context,
            'result_message' => $response['message'],
            'execution_mode' => 'preview',
            'errors' => $response['errors'],
        ));
        return $response;
    }

    $interpreted = voice_pipeline_interpret($transcript, $context, $speechMeta);
    $resolved = voice_pipeline_resolve($interpreted, $context);
    $validated = voice_pipeline_validate($resolved, $context);

    if (($validated['stage'] ?? '') === 'needs_clarification') {
        $response = voice_make_pending_clarification($validated, $transcript, $context);
        $response['ai'] = isset($interpreted['ai']) ? $interpreted['ai'] : array();
        $response['pipeline'] = array(
            'interpret' => $interpreted,
            'resolve' => $resolved,
            'validate' => $validated,
            'execute' => null,
        );
        $response['log_id'] = voice_log_command(array(
            'transcript' => $transcript,
            'normalized_text' => voice_normalize_text($transcript),
            'intent' => $response['intent'] ?? '',
            'stage' => $response['stage'] ?? '',
            'params' => $response['params'] ?? array(),
            'context' => $context,
            'result_message' => $response['message'] ?? '',
            'execution_mode' => $response['execution_mode'] ?? 'preview',
            'errors' => $response['errors'] ?? array(),
            'clarification' => $validated['clarification'] ?? array(),
            'pending_token' => $response['pending']['token'] ?? '',
            'ai' => $response['ai'] ?? array(),
        ));
        return $response;
    }

    if (voice_intent_requires_confirmation($validated['intent'] ?? '', $validated['params'] ?? array())) {
        $response = voice_make_pending_confirmation($validated, $transcript, $context);
        $response['ai'] = isset($interpreted['ai']) ? $interpreted['ai'] : array();
        $response['pipeline'] = array(
            'interpret' => $interpreted,
            'resolve' => $resolved,
            'validate' => $validated,
            'execute' => null,
        );
        $response['log_id'] = voice_log_command(array(
            'transcript' => $transcript,
            'normalized_text' => voice_normalize_text($transcript),
            'intent' => $response['intent'] ?? '',
            'stage' => $response['stage'] ?? '',
            'params' => $response['params'] ?? array(),
            'context' => $context,
            'result_message' => $response['message'] ?? '',
            'execution_mode' => $response['execution_mode'] ?? 'preview',
            'errors' => $response['errors'] ?? array(),
            'confirmation' => array('required' => true),
            'pending_token' => $response['pending']['token'] ?? '',
            'ai' => $response['ai'] ?? array(),
        ));
        return $response;
    }

    $executed = voice_pipeline_execute($validated, $context);
    $response = voice_build_response(array(
        'ok' => empty($executed['errors']) && (($executed['stage'] ?? '') !== 'error'),
        'stage' => isset($executed['stage']) ? $executed['stage'] : 'interpreted',
        'transcript' => $transcript,
        'normalized_text' => voice_normalize_text($transcript),
        'intent' => isset($executed['intent']) ? $executed['intent'] : 'unsupported_command',
        'params' => isset($executed['params']) ? $executed['params'] : array(),
        'missing_fields' => isset($validated['missing_fields']) ? $validated['missing_fields'] : array(),
        'context' => $context,
        'message' => isset($executed['message']) ? $executed['message'] : '',
        'redirect_url' => isset($executed['redirect_url']) ? $executed['redirect_url'] : '',
        'options' => isset($validated['options']) ? $validated['options'] : array(),
        'resolved_entities' => isset($validated['resolved_entities']) ? $validated['resolved_entities'] : array(),
        'errors' => isset($executed['errors']) ? $executed['errors'] : array(),
        'ai' => isset($interpreted['ai']) ? $interpreted['ai'] : array(),
        'analytics' => isset($executed['analytics']) ? $executed['analytics'] : array(),
        'execution_mode' => isset($executed['execution_mode']) ? $executed['execution_mode'] : 'preview',
        'ux' => voice_make_ux(
            (($executed['stage'] ?? '') === 'error') ? 'Error en la orden' : 'Orden procesada',
            isset($executed['message']) ? $executed['message'] : '',
            (($executed['stage'] ?? '') === 'executed' && !empty($executed['redirect_url'])) ? 'Se abrirá la pantalla correspondiente.' : '',
            false
        ),
        'pipeline' => array(
            'interpret' => $interpreted,
            'resolve' => $resolved,
            'validate' => $validated,
            'execute' => $executed,
        ),
    ));
    $response['log_id'] = voice_log_command(array(
        'transcript' => $transcript,
        'normalized_text' => voice_normalize_text($transcript),
        'intent' => $response['intent'] ?? '',
        'stage' => $response['stage'] ?? '',
        'params' => $response['params'] ?? array(),
        'context' => $context,
        'result_message' => $response['message'] ?? '',
        'execution_mode' => $response['execution_mode'] ?? '',
        'errors' => $response['errors'] ?? array(),
        'pending_token' => '',
        'ai' => $response['ai'] ?? array(),
    ));
    return $response;
}
