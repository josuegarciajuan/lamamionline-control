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
        'youtube' => array(
            'play_music' => array('label' => 'Reproducir música'),
            'play_video' => array('label' => 'Reproducir vídeo concreto'),
            'search_youtube' => array('label' => 'Buscar en YouTube'),
            'open_reproductor' => array('label' => 'Abrir reproductor YouTube'),
            'youtube_news' => array('label' => 'Noticias por YouTube'),
            'youtube_suggest' => array('label' => 'Sugerir videos'),
            'create_youtube_channel' => array('label' => 'Crear canal temático'),
            'youtube_control' => array('label' => 'Controlar reproductor'),
        ),
        'autotube' => array(
            'autotube_stats' => array('label' => 'Estadísticas de AutoTube'),
            'autotube_revenue' => array('label' => 'Ingresos de AutoTube'),
            'autotube_ypp' => array('label' => 'Progreso monetización'),
            'autotube_upcoming' => array('label' => 'Próximos videos'),
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
            'create_reminder' => array('label' => 'Crear recordatorio por voz'),
            'think_out_loud' => array('label' => 'Pensar en voz alta'),
            'take_note' => array('label' => 'Tomar nota por voz'),
            'daily_briefing' => array('label' => 'Resumen diario'),
            'search_all' => array('label' => 'Buscar en todas partes'),
            'undo' => array('label' => 'Deshacer último cambio'),
            'repeat_last' => array('label' => 'Repetir último comando'),
            'investor_report' => array('label' => 'Informe para inversores'),
            'sales_coach' => array('label' => 'Entrenador de ventas'),
        ),
        'conversation' => array(
            'conversation' => array('label' => 'Conversación'),
        ),
        'config' => array(
            'configure_assistant' => array('label' => 'Configurar asistente'),
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

function voice_clean_transcription($rawText, $alternatives = array()) {
    $rawText = trim((string)$rawText);
    if ($rawText === '') {
        // Try the best alternative
        foreach ($alternatives as $alt) {
            $alt = trim((string)$alt);
            if ($alt !== '') {
                $rawText = $alt;
                break;
            }
        }
    }
    if ($rawText === '') return array('cleaned' => '', 'raw' => '');

    $cfg = voice_ai_config();
    if (!$cfg['configured'] || !function_exists('curl_init')) {
        // Sin IA, devolver el texto tal cual
        return array('cleaned' => $rawText, 'raw' => $rawText);
    }

    $prompt = "Eres un transcriptor corrector. Recibes texto dictado por voz en español que puede tener errores debido a: pronunciación, volumen bajo, distancia al micrófono, ruido ambiente. Tu tarea es interpretar qué quiso decir la persona y reescribirlo correctamente en español natural.\n\nReglas:\n- Corrige palabras mal transcritas deduciendo por contexto y fonética similar.\n- NO cambies el significado. NO añadas información que no esté en el original.\n- Si el texto ya es correcto, devuélvelo igual.\n- Devuelve SOLO el texto corregido, sin explicaciones ni prefijos.\n- Si el texto es ininteligible, devuelve el texto original sin cambios.\n\nEjemplos:\n- \"Mi voy a fasa\" → \"Me voy a casa\"\n- \"Añedir un buton asul en el dashbord\" → \"Añadir un botón azul en el dashboard\"\n- \"quiero ver las clients de la mami\" → \"quiero ver las clientas de lamami\"\n- \"eureca nesesito un boton pa los informes\" → \"Eureka: necesito un botón para los informes\"\n- \"abre la ajenda de josue\" → \"abre la agenda de josue\"";

    $payload = array(
        'model' => $cfg['model'],
        'temperature' => 0.0,
        'messages' => array(
            array('role' => 'system', 'content' => $prompt),
            array('role' => 'user', 'content' => $rawText),
        ),
    );

    $ch = curl_init($cfg['chat_url']);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $cfg['api_key'],
            'Content-Type: application/json',
        ),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !is_string($response) || $response === '') {
        return array('cleaned' => $rawText, 'raw' => $rawText);
    }

    $decoded = json_decode($response, true);
    $cleaned = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
    if ($cleaned === '') {
        return array('cleaned' => $rawText, 'raw' => $rawText);
    }

    return array('cleaned' => $cleaned, 'raw' => $rawText);
}

function voice_eureka_fuzzy_match($text) {
    // Fuzzy match for "eureka" and variants in spoken Spanish
    $fuzzyPatterns = array(
        '/^\s*[¡!]?\s*eurek(a|as)[!¡:\.\-,\s]*(.*)$/iu',
        '/^\s*[¡!]?\s*urek(a|as)[!¡:\.\-,\s]*(.*)$/iu',
        '/^\s*[¡!]?\s*he?urek(a|as)[!¡:\.\-,\s]*(.*)$/iu',
        '/^\s*[¡!]?\s*eurec(a|as)[!¡:\.\-,\s]*(.*)$/iu',
        '/^\s*[¡!]?\s*e?urek(a|as)[!¡:\.\-,\s]*(.*)$/iu',
    );

    foreach ($fuzzyPatterns as $pattern) {
        if (preg_match($pattern, $text, $m)) {
            return trim((string)($m[2] ?? ''));
        }
    }
    return null; // No match
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
        'raw_transcript' => '',
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
        'tts_text' => '',
        'tts_importance' => 'normal',
        'suggestions' => array(),
        'whiteboard' => null,
        'system_actions' => array(),
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
        'youtube' => array('youtube', 'yutube', 'yutub', 'you tube', 'reproductor', 'musica', 'música', 'video', 'vídeo'),
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
        'model' => 'gpt-4.1-mini',
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
        if (in_array('gpt-4.1-mini', $flat, true)) {
            $cache['model'] = 'gpt-4.1-mini';
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
    return $model !== '' ? $model : 'gpt-4.1-mini';
}

function voice_ai_config_form_state() {
    $settings = storage_read('settings.json');
    $defaults = voice_ai_detect_defaults_from_bot_template();
    $envKey = trim((string)getenv('OPENAI_API_KEY'));
    $envModel = trim((string)getenv('OPENAI_VOICE_MODEL'));

    $storedKey = trim((string)($settings['voice_ai_api_key'] ?? ''));
    $storedModel = trim((string)($settings['voice_ai_model'] ?? ''));
    $storedProvider = trim((string)($settings['voice_ai_provider'] ?? ''));

    $formKey = $storedKey !== '' ? $storedKey : (string)($defaults['api_key'] ?? '');
    $formModel = $storedModel !== '' ? $storedModel : 'deepseek-v4-pro';
    if ($formModel === '') {
        $formModel = voice_ai_default_model();
    }
    $formProvider = $storedProvider !== '' ? $storedProvider : 'deepseek';

    return array(
        'form_api_key' => $formKey,
        'form_model' => $formModel,
        'form_provider' => $formProvider,
        'stored_api_key' => $storedKey,
        'stored_model' => $storedModel,
        'stored_provider' => $storedProvider,
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

    // ── Resolver proveedor ──────────────────────────────────────────
    $provider = trim((string)($settings['voice_ai_provider'] ?? ''));
    if ($provider === '') {
        $provider = 'deepseek'; // default
    }

    // ── Resolver API key ────────────────────────────────────────────
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

    // ── Resolver modelo ─────────────────────────────────────────────
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
        $model = 'deepseek-v4-pro';
        $modelSource = 'default';
    }

    // ── Resolver URL del endpoint según proveedor ───────────────────
    if ($provider === 'deepseek') {
        $chatUrl = 'https://api.deepseek.com/chat/completions';
    } else {
        $chatUrl = 'https://api.openai.com/v1/chat/completions';
    }

    $org = trim((string)getenv('OPENAI_ORGANIZATION'));
    $project = trim((string)getenv('OPENAI_PROJECT'));

    return array(
        'api_key' => $apiKey,
        'model' => $model,
        'provider' => $provider,
        'chat_url' => $chatUrl,
        'organization' => $org,
        'project' => $project,
        'configured' => ($apiKey !== ''),
        'api_key_source' => $apiKeySource,
        'model_source' => $modelSource,
    );
}

function voice_tts_config() {
    $settings = storage_read('settings.json');
    return array(
        'voice' => trim((string)($settings['voice_assistant_tts_voice'] ?? 'nova')),
        'enabled' => !empty($settings['voice_assistant_tts_enabled']),
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
            'interesadas', 'clientas', 'lamamibot', 'agenda', 'config', 'notas', 'eurekas',
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
        . "CORRECCIÓN DE ERRORES DE VOZ (homófonos, ruido, mal reconocimiento):\n"
        . "- Si la transcripción dice 'hostal', 'josta', 'yostal', 'hostales' → es JOSTAL\n"
        . "- Si dice 'casa guasap', 'casa wasap', 'casa whatsapp', 'casa wassap' → es CASAWASAP\n"
        . "- Si dice 'la mami', 'mami', 'lami', 'la mamy', 'lamary' → es LAMAMI\n"
        . "- Si dice 'mami bot', 'la mamibot', 'lamibot', 'mamibox' → es LAMAMIBOT\n"
        . "- Si dice 'interesada', 'interesadas', 'interesado', 'interesados', 'enteradas' → target_type=interesada\n"
        . "- Si dice 'clienta', 'clientas', 'cliente', 'clientes', 'clienta' → target_type=clienta\n"
        . "- Si dice 'abre', 'abrir', 'habré', 'entre', 'revisa' → acción de abrir/mostrar\n"
        . "- Si dice 'muéstrame', 'enséñame', 'quiero ver', 'vamos a', 'dame', 'pon' → acción de abrir/mostrar/crear\n"
        . "- Si dice 'agenda', 'ajenda' → página de agenda de Josué\n"
        . "- Si dice 'crea', 'crear', 'anade', 'añade', 'agrega', 'registra', 'mete', 'guarda' → acción de crear\n"
        . "- Si dice 'cuanto', 'cuanto hemos ganado', 'cuánto', 'cuanto va' → query_analytics con analytics_kind=summary\n"
        . "- Si dice 'dime', 'dame', 'saca' → acción de mostrar/abrir\n"
        . "- Si dice 'busca', 'buscar', 'localiza', 'encuentra', 'quien es' → acción de buscar\n"
        . "- Si dice 'la que tiene', 'el que tiene', 'quien tiene' → búsqueda por lookup_field\n"
        . "\n"
        . "ÓRDENES CORTAS O INCOMPLETAS (el usuario solo dijo una o dos palabras):\n"
        . "- Si solo se dice el nombre de una página (ej: 'jostal', 'lamami', 'publicista', 'comercial', 'informes', 'bots', 'avisos', 'josue', 'gastos'), interpreta como abrir esa página.\n"
        . "- Si solo se dice 'clientas' o 'interesadas', interpreta como abrir esa sección en el ámbito actual o en lamami si no hay contexto.\n"
        . "- Si solo se dice 'agenda', interpreta como open_agenda.\n"
        . "- Si se dice 'en casa', interpreta como filtrar clientas que están en casa (list_entities con estado=en_casa).\n"
        . "- Si se dice 'eureka' o 'eureka!', interpreta como create_eureka.\n"
        . "- Si solo hay un nombre propio (ej: 'Andrea', 'María'), interpreta como open_entity_by_name.\n"
        . "- Si solo hay un número de teléfono (9 dígitos), interpreta como open_entity_by_phone.\n"
        . "\n"
        . "INFERENCIA DESDE CONTEXTO (usando el campo context en el payload):\n"
        . "- Si el usuario está en la página de jostal (context.page=jostal) y dice 'clientas', abre clientas de jostal.\n"
        . "- Si el usuario está en jostal y dice 'la que tiene telefono X', busca por teléfono en jostal (target_scope=jostal).\n"
        . "- Si está en jostal y dice 'interesadas', abre interesadas de jostal.\n"
        . "- Si está en lamami y dice 'clientas en casa', busca clientas de lamami con estado en_casa.\n"
        . "- Si está en casawasap y dice 'crea contacto', usa target_scope=casawasap.\n"
        . "- Si está en josue y dice 'agenda', abre la pestaña agenda de josue.\n"
        . "- El contexto siempre debe influir en target_scope cuando no se menciona explícitamente un ámbito.\n"
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
        . "- tab: interesadas, clientas, lamamibot, agenda, ventas, config, informes, eurekas, crear_perfiles, estrategias, cuentas, campanas, subir_anuncios, resumen, procesos, lineas, conversaciones, leads, ajustes, logs\n"
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
        . "- 'apaga lamamibot' -> set_lamamibot_runtime_mode con mode=stop\n"
        . "- 'ensename las clientas de jostal' -> list_entities, target_type=clienta, target_scope=jostal\n"
        . "- 'quiero ver las que estan en casa' -> list_entities con estado=en_casa\n"
        . "- 'crea una interesada nueva en jostal' -> create_jostal_interesada (o open_tab con view=new y tab=interesadas)\n"
        . "- 'dame el resumen de esta semana' -> query_analytics, analytics_kind=summary, period_hint=this_week\n"
        . "- 'cuanto hemos ganado este mes' -> query_analytics, analytics_kind=summary, period_hint=this_month\n"
        . "- 'crea un contacto en la agenda' -> create_agenda_contact (detecta si hay nombre y teléfono)\n"
        . "- 'registra un gasto de 50 euros en oficina' -> add_gasto, cantidad=50, descripcion='oficina'\n"
        . "- 'apaga el bot de Paola' -> set_bot_runtime_mode con mode=stop, lookup_field=nombre, lookup_value=Paola\n"
        . "\n"
        . "COMANDOS DE REPRODUCTOR / YOUTUBE:\n"
        . "- 'ponme musica', 'pon musica', 'reproduce musica', 'musica por favor' -> play_music (usa ultimo genero o artista del historial)\n"
        . "- 'ponme la cancion X', 'pon X', 'reproduce X', 'busca X en youtube' -> play_video con params.query=X (busca el video y lo reproduce)\n"
        . "- 'busca en youtube X', 'busca videos de X' -> search_youtube con params.query=X\n"
        . "- 'abre el reproductor', 'abre youtube', 'muestrame el reproductor', 'quiero ver videos' -> open_reproductor\n"
        . "- 'ponme algo de X', 'pon canciones de X', 'quiero escuchar X' -> play_video con params.query=X\n"
        . "- 'siguiente cancion', 'otra cancion', 'siguiente' -> play_music (siguiente en historial)\n"
        . "- 'pon las noticias', 'videos de noticias', 'ultimos videos' -> open_reproductor con params.tab=channels\n"
        . "- Si el usuario pide reproducir algo que suena a cancion/artista/genero musical, interpreta como play_video con el query.\n"
        . "- Para musicos y artistas: extrae el nombre completo como query (ej: 'ponme freddy mercury' -> query='freddy mercury')\n";
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

function voice_rule_interpretation_score($payload, $context = array(), $transcript = '') {
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

    // Domain-specific bonuses for better candidate selection
    $normalized = voice_normalize_text($transcript);

    // Bonus for Jostal-specific keywords (often misrecognized by voice)
    if (voice_contains_any($normalized, array('jostal', 'hostal', 'yostal', 'josta'))) {
        $score += 2;
    }

    // Bonus for detecting numeric phone patterns in the transcript
    if (preg_match('/\d{6,}/', $normalized)) {
        $score += 3;
    }

    // Bonus for proper name patterns (capitalized words detectable in raw transcript)
    if (preg_match('/\b[A-ZÁÉÍÓÚÜÑ][a-záéíóúüñ]+\b/u', (string)$transcript)) {
        $score += 1;
    }

    // Penalty for very short interpretations with no meaningful params
    $paramCount = 0;
    foreach (array('page', 'tab', 'target_type', 'target_scope', 'lookup_value', 'estado', 'analytics_kind', 'mode') as $k) {
        if (!empty($params[$k]) && $params[$k] !== 'none' && $params[$k] !== '') $paramCount++;
    }
    if ($paramCount === 0 && $intent !== 'confirm_pending_action' && $intent !== 'cancel_pending_action') {
        $score -= 5;
    }

    // Bonus when the resolved intent/page matches current page context
    $currentPage = trim((string)($context['page'] ?? ''));
    if ($currentPage !== '' && !empty($params['page']) && $params['page'] === $currentPage) {
        $score += 3;
    } elseif ($currentPage !== '' && !empty($params['target_scope'])) {
        if ($params['target_scope'] === $currentPage || voice_normalize_scope($params['target_scope']) === $currentPage) {
            $score += 3;
        }
    }

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
                'role' => 'system',
                'content' => voice_interpreter_system_prompt(),
            ),
            array(
                'role' => 'user',
                'content' => json_encode(voice_ai_user_payload($text, $context, $speechMeta), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ),
        ),
        'response_format' => array(
            'type' => 'json_object',
        ),
        'thinking' => array('type' => 'disabled'),
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
    $ch = curl_init($cfg['chat_url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
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

    // Configuration commands
    if (preg_match('/\b(config[úu]rate|configurar|modo configuraci[óo]n|cambiar? tu nombre|cambiar tu voz|configura el asistente|ajustes del asistente)\b/iu', $raw)) {
        return array(
            'intent' => 'configure_assistant',
            'params' => $params,
            'needs_clarification' => false,
            'clarification_question' => '',
            'missing_fields' => array(),
        );
    }

    // Reminder commands
    if (preg_match('/\b(recu[ée]rdame|av[ií]same|no te olvides|recuerda|acuérdate)\b/iu', $raw)) {
        $descripcion = preg_replace('/\b(recu[ée]rdame|av[ií]same|no te olvides|recuerda|acuérdate)\s*(de\s*)?/iu', '', $raw);
        $descripcion = trim($descripcion);
        $params['descripcion'] = $descripcion;
        return array(
            'intent' => 'create_reminder',
            'params' => $params,
            'needs_clarification' => ($descripcion === ''),
            'clarification_question' => $descripcion === '' ? '¿Qué quieres que te recuerde?' : '',
            'missing_fields' => $descripcion === '' ? array('descripcion') : array(),
        );
    }

    $eurekaDesc = voice_eureka_fuzzy_match($raw);
    if ($eurekaDesc !== null) {
        $descripcion = $eurekaDesc;
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

    // "toma nota:" / "apunta:" → voice diary
    if (preg_match('/^\s*(toma nota[:\s]*|apunta[:\s]*|anota[:\s]*|guarda esto[:\s]*)/iu', $raw, $m)) {
        $nota = trim(preg_replace('/^\s*(toma nota[:\s]*|apunta[:\s]*|anota[:\s]*|guarda esto[:\s]*)/iu', '', $raw));
        $params['nota'] = $nota;
        return array('intent' => 'take_note', 'params' => $params,
            'needs_clarification' => ($nota === ''),
            'clarification_question' => $nota === '' ? '¿Qué quieres que anote?' : '',
            'missing_fields' => $nota === '' ? array('nota') : array());
    }

    // "no sé si..." / "qué opinas de..." / "debería..." → think out loud
    if (preg_match('/\b(no s[ée] si|qu[ée] opinas|qu[ée] te parece|deber[íi]a|recomiendas|aconsejas|qu[ée] har[íi]as|estoy dudando)\b/iu', $raw)) {
        $params['question'] = trim($raw);
        return array('intent' => 'think_out_loud', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }

    // "cómo va el día" / "resumen del día" / "cómo estamos hoy"
    if (preg_match('/\b(c[óo]mo va(mos)?|resumen del d[ií]a|c[óo]mo estamos|qu[ée] tal (el )?d[ií]a|balance|briefing)\b/iu', $raw)) {
        return array('intent' => 'daily_briefing', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }

    // "busca X en todas partes" / "busca X en todo"
    if (preg_match('/\b(busca|buscar|encuentra)\s+(.+)\s+(en todas partes|en todo|global|universal)\b/iu', $raw, $m)) {
        $params['query'] = trim($m[2] ?? '');
        return array('intent' => 'search_all', 'params' => $params,
            'needs_clarification' => ($params['query'] === ''),
            'missing_fields' => $params['query'] === '' ? array('query') : array());
    }

    // "deshacer" / "deshacer último" / "undo"
    if (preg_match('/\b(deshacer|deshaz|undo|tira atr[áa]s|vuelve atr[áa]s)\b/iu', $raw)) {
        return array('intent' => 'undo', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }

    // "repite" / "repítelo" / "otra vez" / "repite el último"
    if (preg_match('/\b(repite|rep[ií]telo|otra vez|el [úu]ltimo|lo de antes)\b/iu', $raw)) {
        return array('intent' => 'repeat_last', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }

    // "prepara informe para inversores" / "informe financiero" / "datos para invertir"
    if (preg_match('/\b(informe (para |de )?inversor|informe financiero|resumen financiero|datos para invertir|dame n[úu]meros|reporte de negocio)\b/iu', $raw)) {
        $params['period'] = voice_parse_period_from_text($raw);
        return array('intent' => 'investor_report', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }

    // "cómo puedo vender más" / "entrenador de ventas" / "mejora mi pitch"
    if (preg_match('/\b(c[óo]mo (puedo|podr[ií]a) (vender|mejorar|ganar) m[áa]s|entrenador de ventas|mejora mi pitch|consejos de venta|c[óo]mo convierto m[áa]s)\b/iu', $raw)) {
        return array('intent' => 'sales_coach', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }

    // ── AutoTube commands ────────────────────────────────────────────
    if (preg_match('/\b(c[óo]mo va(mos)?\s*(con\s+)?autotube|estado de autotube|stats? de autotube|autotube stats?)\b/iu', $raw)) {
        return array('intent' => 'autotube_stats', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }
    if (preg_match('/\b(cu[áa]nto ha(n)?\s*(ganado|generado|facturado)\s*(autotube|auto tube)?|ingresos\s*(de\s+)?autotube|revenue autotube|ganancias autotube)\b/iu', $raw)) {
        return array('intent' => 'autotube_revenue', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }
    if (preg_match('/\b(cu[áa]nto falta para monetizar|progreso\s*(de\s+)?monetizaci[óo]n|ypp|cu[áa]ndo\s*(se\s+)?monetiza|cu[áa]nto\s*(le\s+)?falta a autotube)\b/iu', $raw)) {
        return array('intent' => 'autotube_ypp', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }
    if (preg_match('/\b(pr[óo]ximo\s*video|cu[áa]ndo\s*sale|siguiente\s*video|qu[ée]\s*video\s*(viene|toca)|pr[óo]xima\s*publicaci[óo]n|upcoming)\b/iu', $raw)) {
        return array('intent' => 'autotube_upcoming', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }

    // ── YouTube reproductor commands ─────────────────────────────────
    if (preg_match('/\b(qu[ée] hay (de )?(las )?noticias|cu[ée]ntame las noticias|noticias de hoy|resumen de noticias|noticias ahora)\b/iu', $raw)) {
        return array('intent' => 'youtube_news', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }
    if (preg_match('/\b(sugi[ée]reme\s*(v[íi]deos|algo|contenido|m[úu]sica)|qu[ée] me recomiendas|dame sugerencias|recomi[ée]ndame)\b/iu', $raw)) {
        return array('intent' => 'youtube_suggest', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }
    if (preg_match('/\b(crea\s*(un\s+)?canal\s+(de\s+)?|a[ñn]ade\s*(un\s+)?canal\s+(de\s+)?|nuevo\s+canal\s+(de\s+)?)(.+)/iu', $raw, $m)) {
        $params['concept'] = trim($m[5] ?? '');
        return array('intent' => 'create_youtube_channel', 'params' => $params,
            'needs_clarification' => ($params['concept'] === ''),
            'missing_fields' => $params['concept'] === '' ? array('concept') : array());
    }
    if (preg_match('/\b(siguiente\s*(canci[óo]n|video)?|pausa|reanuda|reproduce|sube\s*(el\s+)?volumen|baja\s*(el\s+)?volumen)\b/iu', $raw)) {
        $params['action'] = trim($raw);
        return array('intent' => 'youtube_control', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }

    // ── CRM state-change commands ────────────────────────────────────

    // "dar de alta/baja a X", "marcar como X", "cambiar estado de X"
    if (preg_match('/\b(dar de|darse de|marcar como|poner como|cambia\w*\s+(el\s+)?estado\s+(de\s+)?)\s*(alta|baja|atendida|convertida|descartada|pendiente)\b/iu', $raw, $m)) {
        $newEstado = mb_strtolower(trim($m[4] ?? $m[3] ?? $m[2] ?? ''));
        $params['estado'] = $newEstado;
        // Extract entity hint
        $entityText = preg_replace('/\b(dar de|darse de|marcar como|poner como|cambia\w*\s+(el\s+)?estado\s+(de\s+)?)\s*(alta|baja|atendida|convertida|descartada|pendiente)\b/iu', '', $raw);
        $params['entity_hint'] = trim($entityText);
        return array('intent' => 'set_entity_estado', 'params' => $params,
            'needs_clarification' => empty($params['entity_hint']),
            'clarification_question' => empty($params['entity_hint']) ? '¿A qué persona le cambio el estado?' : '',
            'missing_fields' => empty($params['entity_hint']) ? array('entity_hint') : array());
    }

    // "X ha pagado Y euros"
    if (preg_match('/\b(ha\s+pagado|pag[óo]|ha\s+hecho\s+un\s+pago|pago\s+de)\b/iu', $raw)) {
        $amount = voice_extract_amount($normalized);
        $entityText = preg_replace('/\b(ha\s+pagado|pag[óo]|ha\s+hecho\s+un\s+pago|pago\s+de)\b/iu', '', $raw);
        $entityText = preg_replace('/\b\d+[\.,]?\d*\s*(euros?|€)\b/iu', '', $entityText);
        $params['entity_hint'] = trim($entityText);
        $params['cantidad'] = $amount > 0 ? (string)$amount : '';
        return array('intent' => 'add_entity_pago', 'params' => $params,
            'needs_clarification' => empty($params['entity_hint']),
            'missing_fields' => empty($params['entity_hint']) ? array('entity_hint') : array());
    }

    // "X ha salido de casa" / "X ya no está en casa" / "X se ha ido"
    if (preg_match('/\b(ha salido|se ha ido|ya no est[áa]|fuera de casa|sali[óo] de casa)\b/iu', $raw)) {
        $entityText = preg_replace('/\b(ha salido|se ha ido|ya no est[áa]|fuera de casa|sali[óo] de casa)\b/iu', '', $raw);
        $params['entity_hint'] = trim($entityText);
        return array('intent' => 'jostal_salida_casa', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }

    // "X ha vuelto a casa" / "X está en casa otra vez"
    if (preg_match('/\b(ha vuelto|est[áa] de vuelta|reactivar|de nuevo en casa|otra vez en casa)\b/iu', $raw)) {
        $entityText = preg_replace('/\b(ha vuelto|est[áa] de vuelta|reactivar|de nuevo en casa|otra vez en casa)\b/iu', '', $raw);
        $params['entity_hint'] = trim($entityText);
        return array('intent' => 'jostal_reactivar_casa', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }

    // "borra el gasto X" / "elimina gasto"
    if (preg_match('/\b(borr[ae]r?\s*(el\s+)?gasto|elimin[ae]r?\s*(el\s+)?gasto|quita\s*(el\s+)?gasto)\b/iu', $raw)) {
        $entityText = preg_replace('/\b(borr[ae]r?\s*(el\s+)?gasto|elimin[ae]r?\s*(el\s+)?gasto|quita\s*(el\s+)?gasto)\b/iu', '', $raw);
        $params['entity_hint'] = trim($entityText);
        return array('intent' => 'delete_gasto_request', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }

    // "borra de la agenda a X" / "elimina contacto de agenda X"
    if (preg_match('/\b(borr[ae]r?\s*(de\s+(la\s+)?agenda|el\s+contacto|a\s+))|(elimin[ae]r?\s*(de\s+(la\s+)?agenda|el\s+contacto))\b/iu', $raw)) {
        return array('intent' => 'delete_agenda_request', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }

    // "nueva interesada X" / "crear interesada X" / "añadir interesada"
    if (preg_match('/\b(nuev[ao]|crear|a[ñn]adir|registrar|alta de)\s+(interesada|clienta|inquilina)\b/iu', $raw, $m)) {
        $tipoEntity = mb_strtolower($m[2]);
        $scope = voice_extract_scope_from_text($normalized, $context);
        $entityText = preg_replace('/\b(nuev[ao]|crear|a[ñn]adir|registrar|alta de)\s+(interesada|clienta|inquilina)\b/iu', '', $raw);

        // Extract name and phone from remaining text
        $nameHint = voice_extract_name_hint($entityText);
        $phone = voice_extract_phone($entityText);
        $params['nombre'] = $nameHint !== '' ? $nameHint : trim($entityText);
        $params['telefono'] = $phone !== '' ? $phone : '';

        // Map to specific intent
        if ($scope === 'jostal' || $tipoEntity === 'inquilina') {
            $intent = ($tipoEntity === 'clienta') ? 'create_jostal_clienta' : 'create_jostal_interesada';
        } else {
            $intent = ($tipoEntity === 'clienta') ? 'create_clienta' : 'create_interesada';
        }
        $params['target_scope'] = $scope;

        return array('intent' => $intent, 'params' => $params,
            'needs_clarification' => ($params['nombre'] === '' && $params['telefono'] === ''),
            'missing_fields' => ($params['nombre'] === '' && $params['telefono'] === '') ? array('nombre o teléfono') : array());
    }

    // "convertir X en clienta" / "X ya es clienta"
    if (preg_match('/\b(convertir\s+(a\s+)?|ya es|pasa a ser)\s*(clienta|cliente)/iu', $raw)) {
        $entityText = preg_replace('/\b(convertir\s+(a\s+)?|ya es|pasa a ser)\s*(clienta|cliente)/iu', '', $raw);
        $params['entity_hint'] = trim($entityText);
        return array('intent' => 'convert_entity', 'params' => $params,
            'needs_clarification' => empty($params['entity_hint']),
            'missing_fields' => empty($params['entity_hint']) ? array('entity_hint') : array());
    }

    // "generar assets de lamamibot" / "regenerar bot"
    if (preg_match('/\b(generar assets?|regenerar (el\s+)?bot|crear assets?)\b/iu', $raw)) {
        return array('intent' => 'generate_lamamibot_assets', 'params' => $params,
            'needs_clarification' => false, 'missing_fields' => array());
    }

    // "en casa" or "solo en casa" patterns for filtering (without navigation verbs)
    if (voice_contains($normalized, 'en casa') && !voice_contains_any($normalized, array('entra', 'entrar', 'mete', 'meter'))) {
        $scope = voice_extract_scope_from_text($normalized, $context);
        $params['estado'] = 'en_casa';
        $params['target_type'] = 'clienta';
        if ($scope !== '') {
            $params['target_scope'] = $scope;
        } elseif (!empty($context['page'])) {
            $params['target_scope'] = trim((string)$context['page']);
        }
        return array(
            'intent' => 'list_entities',
            'params' => $params,
            'needs_clarification' => false,
            'clarification_question' => '',
            'missing_fields' => array(),
        );
    }

    // Simple page name only: when the user just says the page name
    $simplePages = array(
        'jostal', 'lamami', 'casawasap', 'publicista', 'comercial',
        'gastos', 'informes', 'bots', 'avisos', 'josue',
    );
    if (in_array($normalized, $simplePages, true)) {
        $params['page'] = $normalized;
        if ($normalized === 'josue') {
            return array('intent' => 'open_page', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
        }
        return array('intent' => 'open_page', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
    }

    // Variant: "hostal", "yostal", "josta" → jostal
    $normalizedClean = preg_replace('/\s+/', '', $normalized);
    $simplePageVariants = array(
        'hostal' => 'jostal', 'yostal' => 'jostal', 'josta' => 'jostal',
        'lamami' => 'lamami', 'lamary' => 'lamami', 'lami' => 'lamami',
        'casawasap' => 'casawasap', 'casaguasap' => 'casawasap',
        'publicista' => 'publicista', 'comercial' => 'comercial',
        'gastos' => 'gastos', 'informes' => 'informes',
        'bots' => 'bots', 'avisos' => 'avisos', 'josue' => 'josue',
    );
    if (isset($simplePageVariants[$normalized]) || isset($simplePageVariants[$normalizedClean])) {
        $resolved = $simplePageVariants[$normalized] ?? $simplePageVariants[$normalizedClean];
        $params['page'] = $resolved;
        return array('intent' => 'open_page', 'params' => $params, 'needs_clarification' => false, 'clarification_question' => '', 'missing_fields' => array());
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
    $candidateResults = array(); // track all results for tie-breaking and logging
    foreach (voice_candidate_texts($text, $speechMeta) as $candidateText) {
        $candidateRule = voice_sanitize_ai_interpretation(voice_interpret_with_rules($candidateText, $context));
        $candidateScore = voice_rule_interpretation_score($candidateRule, $context, $candidateText);
        $candidateResults[] = array(
            'transcript' => $candidateText,
            'score' => $candidateScore,
            'intent' => $candidateRule['intent'],
        );
        if ($rule === null || $candidateScore > $bestScore) {
            $rule = $candidateRule;
            $bestScore = $candidateScore;
            $selectedTranscript = $candidateText;
        } elseif ($candidateScore === $bestScore) {
            // Tie-breaking: prefer the SHORTEST clean transcript (less noise)
            if (mb_strlen($candidateText, 'UTF-8') < mb_strlen($selectedTranscript, 'UTF-8')) {
                $rule = $candidateRule;
                $selectedTranscript = $candidateText;
            }
        }
    }
    if ($rule === null) {
        $rule = voice_sanitize_ai_interpretation(voice_interpret_with_rules($text, $context));
    }

    // If the selected transcript and original text are very similar (diff only in punctuation/case),
    // prefer the original to avoid confusing the user
    $originalNormalized = voice_normalize_text($text);
    $selectedNormalized = voice_normalize_text($selectedTranscript);
    $similarityDistance = levenshtein($originalNormalized, $selectedNormalized);
    $maxLen = max(strlen($originalNormalized), strlen($selectedNormalized), 1);
    $similarityRatio = $similarityDistance / $maxLen;
    if ($similarityRatio < 0.15 && $selectedTranscript !== $text) {
        // Very similar — re-interpret using original text for cleaner message
        $originalRule = voice_sanitize_ai_interpretation(voice_interpret_with_rules($text, $context));
        $originalScore = voice_rule_interpretation_score($originalRule, $context, $text);
        if ($originalScore >= $bestScore - 1) {
            $rule = $originalRule;
            $selectedTranscript = $text;
        }
    }

    // Collect error details for the ai.errors field
    $aiErrors = isset($ai['errors']) && is_array($ai['errors']) ? $ai['errors'] : array();
    if (!empty($aiErrors)) {
        $aiErrors[] = 'fallback_transcripts_considered:' . json_encode($candidateResults, JSON_UNESCAPED_SLASHES);
        $aiErrors[] = 'original_transcript:' . $text;
        $aiErrors[] = 'speech_alternatives:' . json_encode($speechMeta['alternatives'] ?? array(), JSON_UNESCAPED_SLASHES);
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
        'errors' => $aiErrors,
        'ai' => array(
            'enabled' => !empty(voice_ai_config()['configured']),
            'provider' => 'openai',
            'model' => $ai['model'] ?? '',
            'request_id' => $ai['request_id'] ?? '',
            'client_request_id' => $ai['client_request_id'] ?? '',
            'used_fallback' => true,
            'selected_transcript' => $selectedTranscript,
            'errors' => $aiErrors,
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

function voice_fuzzy_match_name($input, $candidates) {
    $input = voice_normalize_text(trim((string)$input));
    if ($input === '' || empty($candidates)) return null;

    $best = null;
    $bestDist = PHP_INT_MAX;
    $threshold = mb_strlen($input, 'UTF-8') <= 5 ? 1 : 2; // Tighter for short names

    foreach ($candidates as $candidate) {
        $nameField = voice_entity_name_field($candidate['kind'] ?? '', $candidate['scope'] ?? '');
        $name = voice_normalize_text(trim((string)($candidate['row'][$nameField] ?? '')));
        if ($name === '') continue;

        $dist = levenshtein($input, $name);
        if ($dist < $bestDist) {
            $bestDist = $dist;
            $best = $candidate;
        }

        // Also check partial name match (e.g. "Andrea" in "Andrea María")
        if (mb_stripos($name, $input) !== false) {
            return $candidate; // Perfect partial match, return immediately
        }
    }

    // Return if within threshold
    return ($best !== null && $bestDist <= $threshold) ? $best : null;
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

    if (!empty($exact)) return $exact;
    if (!empty($partial)) return $partial;

    // Fuzzy fallback: try Levenshtein distance on names
    if ($lookupField === '' && $query !== '') {
        $fuzzy = voice_fuzzy_match_name($query, $entries);
        if ($fuzzy !== null) return array($fuzzy);
    }

    return array();
}

function voice_context_current_entity($targetType, $context, $scope = '') {
    $page = trim((string)($context['page'] ?? ''));
    $tab = trim((string)($context['tab'] ?? ''));
    $edit = trim((string)($context['edit'] ?? ''));
    $clienteId = trim((string)($context['cliente_id'] ?? ''));
    $queryString = trim((string)($context['query_string'] ?? ''));
    $requestUri = trim((string)($context['request_uri'] ?? ''));
    $scope = voice_normalize_scope($scope, $targetType);

    // Try to extract edit param from request_uri if not in context directly
    if ($edit === '' && ($requestUri !== '' || $queryString !== '')) {
        $qs = $requestUri !== '' ? parse_url($requestUri, PHP_URL_QUERY) : $queryString;
        if ($qs !== null && $qs !== '') {
            parse_str($qs, $uriParams);
            $edit = trim((string)($uriParams['edit'] ?? ''));
        }
    }

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
            } elseif (in_array($params['tab'], array('agenda', 'config', 'notas'), true)) {
            $params['page'] = 'josue';
        } elseif (in_array($params['tab'], array('lineas', 'procesos', 'conversaciones', 'leads', 'ajustes', 'logs'), true)) {
            $params['page'] = 'comercial';
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

    // ── Nuevos intents YouTube reproductor ──────────────────────────

    if ($intent === 'youtube_news') {
        $exec = voice_execute_youtube_news();
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent, 'params' => $params,
            'message' => $exec['message'],
            'execution_mode' => 'readonly',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'youtube_suggest') {
        $exec = voice_execute_youtube_suggest();
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent, 'params' => $params,
            'message' => $exec['message'],
            'redirect_url' => 'index.php?page=josue&tab=reproductor',
            'execution_mode' => 'navigation',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'create_youtube_channel') {
        $exec = voice_execute_create_youtube_channel($params);
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent, 'params' => $params,
            'message' => $exec['message'],
            'redirect_url' => $exec['redirect_url'] ?? 'index.php?page=josue&tab=reproductor',
            'execution_mode' => 'write',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'youtube_control') {
        $exec = voice_execute_youtube_control($params);
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent, 'params' => $params,
            'message' => $exec['message'],
            'redirect_url' => $exec['redirect_url'] ?? 'index.php?page=josue&tab=reproductor',
            'execution_mode' => 'navigation',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    // ── AutoTube intents ────────────────────────────────────────────

    if ($intent === 'autotube_stats') {
        $exec = voice_execute_autotube_stats();
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent, 'params' => $params,
            'message' => $exec['message'],
            'execution_mode' => 'readonly',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'autotube_revenue') {
        $exec = voice_execute_autotube_revenue();
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent, 'params' => $params,
            'message' => $exec['message'],
            'execution_mode' => 'readonly',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'autotube_ypp') {
        $exec = voice_execute_autotube_ypp();
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent, 'params' => $params,
            'message' => $exec['message'],
            'execution_mode' => 'readonly',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'autotube_upcoming') {
        $exec = voice_execute_autotube_upcoming();
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent, 'params' => $params,
            'message' => $exec['message'],
            'execution_mode' => 'readonly',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
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

    if ($pending['kind'] === 'config_step') {
        return voice_config_handle_step($pending, $commandText, $context, $interaction);
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

    if ($intent === 'daily_briefing') {
        $exec = voice_execute_daily_briefing($context);
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent, 'params' => $params,
            'message' => $exec['message'],
            'execution_mode' => 'readonly',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'search_all') {
        $exec = voice_execute_search_all($params);
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent, 'params' => $params,
            'message' => $exec['message'],
            'execution_mode' => 'readonly',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'undo') {
        $exec = voice_execute_undo();
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent, 'params' => $params,
            'message' => $exec['message'],
            'redirect_url' => $exec['redirect_url'] ?? '',
            'execution_mode' => 'navigation',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'repeat_last') {
        $exec = voice_execute_repeat_last();
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent, 'params' => $params,
            'message' => $exec['message'],
            'redirect_url' => $exec['redirect_url'] ?? '',
            'execution_mode' => $exec['execution_mode'] ?? 'preview',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'investor_report') {
        $exec = voice_execute_investor_report($params);
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent, 'params' => $params,
            'message' => $exec['message'],
            'execution_mode' => 'readonly',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'sales_coach') {
        $exec = voice_execute_sales_coach($params);
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent, 'params' => $params,
            'message' => $exec['message'],
            'execution_mode' => 'readonly',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'configure_assistant') {
        return voice_config_start($params);
    }

    if ($intent === 'create_reminder') {
        $exec = voice_create_reminder($params);
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent,
            'params' => $params,
            'message' => $exec['message'],
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

    if ($intent === 'take_note') {
        $exec = voice_execute_take_note($params);
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent,
            'params' => $params,
            'message' => $exec['message'],
            'execution_mode' => 'write',
            'errors' => !empty($exec['errors']) ? $exec['errors'] : array(),
        );
    }

    if ($intent === 'think_out_loud') {
        $exec = voice_execute_think_out_loud($params, $context);
        return array(
            'stage' => $exec['ok'] ? 'executed' : 'error',
            'intent' => $intent,
            'params' => $params,
            'message' => $exec['message'],
            'execution_mode' => 'readonly',
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

    // ── Nuevos intents CRM (Fase 8) ──────────────────────────────────

    $entityRedirectIntents = array(
        'set_entity_estado', 'add_entity_pago', 'jostal_salida_casa',
        'jostal_reactivar_casa', 'convert_entity', 'delete_gasto_request',
        'delete_agenda_request', 'create_interesada', 'create_clienta',
        'create_jostal_interesada', 'create_jostal_clienta',
        'edit_interesada', 'edit_clienta', 'edit_casawasap_contacto',
        'edit_agenda_contact', 'generate_lamamibot_assets',
        'create_quick_lead', 'add_jostal_lead', 'add_jostal_venta',
        'set_interesada_estado', 'set_casawasap_estado',
        'add_casawasap_pago', 'alta_clienta', 'baja_clienta',
        'alta_casawasap_cliente', 'baja_casawasap_cliente',
        'convert_interesada_to_clienta', 'convert_casawasap_cliente',
        'convert_jostal_clienta',
    );

    if (in_array($intent, $entityRedirectIntents, true)) {
        return voice_execute_entity_redirect($intent, $params, $context);
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

    // ── YouTube / Reproductor ────────────────────────────────────────

    if ($intent === 'open_reproductor') {
        $tab = $params['tab'] ?? '';
        $redirect = 'index.php?page=josue&tab=reproductor';
        if ($tab !== '') {
            $redirect .= '&subtab=' . urlencode($tab);
        }
        return array(
            'stage' => 'executed',
            'intent' => $intent,
            'params' => $params,
            'message' => 'Abriendo el reproductor de YouTube.',
            'redirect_url' => $redirect,
            'execution_mode' => 'navigation',
            'errors' => array(),
        );
    }

    if ($intent === 'play_music') {
        // Buscar ultimo del historial o algo de una playlist favorita
        $history = storage_read('youtube_history.json');
        $query = '';
        if (is_array($history) && !empty($history[0])) {
            $query = (string)($history[0]['title'] ?? '');
        }
        if ($query === '') {
            $query = 'musica espanola 2025'; // fallback
        }
        $redirect = 'index.php?page=josue&tab=reproductor&play=' . urlencode($query);
        return array(
            'stage' => 'executed',
            'intent' => $intent,
            'params' => $params,
            'message' => 'Reproduciendo música para ti.',
            'redirect_url' => $redirect,
            'execution_mode' => 'navigation',
            'errors' => array(),
        );
    }

    if ($intent === 'play_video' || $intent === 'search_youtube') {
        // Extraer el query de params o del mensaje
        $query = trim((string)($params['query'] ?? ''));
        if ($query === '') {
            // Intentar extraer del parametro lookup_value
            $query = trim((string)($params['lookup_value'] ?? ''));
        }
        if ($query === '') {
            $query = trim((string)($params['descripcion'] ?? ''));
        }
        $redirect = 'index.php?page=josue&tab=reproductor&play=' . urlencode($query);
        $msg = ($intent === 'play_video') ? 'Buscando y reproduciendo "' . $query . '".' : 'Buscando "' . $query . '" en YouTube.';
        return array(
            'stage' => 'executed',
            'intent' => $intent,
            'params' => array_merge($params, array('query' => $query)),
            'message' => $msg,
            'redirect_url' => $redirect,
            'execution_mode' => 'navigation',
            'errors' => array(),
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
    $rawTranscript = trim((string)($speechMeta['raw_transcript'] ?? ''));
    if ($rawTranscript === '') $rawTranscript = $transcript;
    $modoEureka = !empty($speechMeta['modo_eureka']);

    if (($speechMeta['source'] ?? '') === 'speech') {
        $transcript = voice_sanitize_speech_noise($transcript);
        if ($transcript === '' && !empty($speechMeta['alternatives'][0])) {
            $transcript = trim((string)$speechMeta['alternatives'][0]);
        }
    }

    // ── Clean transcription (AI correction) ──────────────────────────
    $cleanResult = array('cleaned' => $transcript, 'raw' => $rawTranscript);
    $settings = storage_read('settings.json');
    $useUnified = !empty($settings['voice_use_unified_prompt']);
    if ($transcript !== '' && ($modoEureka || ($speechMeta['source'] ?? '') === 'speech')) {
        $alternatives = $speechMeta['alternatives'] ?? array();
        if ($useUnified) {
            // Unified mode: skip separate cleaning, let AI interpreter handle it
            $cleanResult = array('cleaned' => $transcript, 'raw' => $rawTranscript);
        } else {
            $cleanResult = voice_clean_transcription($rawTranscript !== '' ? $rawTranscript : $transcript, $alternatives);
        }
    }
    $cleanedTranscript = $cleanResult['cleaned'];
    if ($cleanedTranscript === '') $cleanedTranscript = $transcript;
    $rawTranscript = $cleanResult['raw'];
    if ($rawTranscript === '') $rawTranscript = $transcript;

    $context = voice_clean_context($context);
    $interaction = is_array($interaction) ? $interaction : array();
    $pendingToken = trim((string)($interaction['pending_token'] ?? ''));
    $pending = null;

    // ── Modo Eureka: force create_eureka with cleaned text ───────────
    if ($modoEureka && $cleanedTranscript !== '' && $pendingToken === '') {
        $descripcion = $cleanedTranscript;
        $row = eureka_create_row($descripcion, 'voice');
        storage_upsert('eurekas.json', $row);
        $response = voice_build_response(array(
            'ok' => true,
            'stage' => 'executed',
            'transcript' => $cleanedTranscript,
            'raw_transcript' => $rawTranscript,
            'intent' => 'create_eureka',
            'context' => $context,
            'message' => 'Eureka guardada correctamente.',
            'redirect_url' => 'index.php?page=josue&tab=eurekas&edit=' . urlencode($row['id']),
            'execution_mode' => 'write',
            'ux' => voice_make_ux('¡Eureka guardada!', 'Eureka guardada correctamente desde modo voz.', '', false),
        ));
        $response['log_id'] = voice_log_command(array(
            'transcript' => $cleanedTranscript,
            'normalized_text' => voice_normalize_text($cleanedTranscript),
            'intent' => 'create_eureka',
            'stage' => 'executed',
            'params' => array('descripcion' => $descripcion),
            'context' => $context,
            'result_message' => 'Eureka guardada correctamente.',
            'execution_mode' => 'write',
        ));
        return $response;
    }

    // ── Conversation vs Command Router ────────────────────────────────
    if ($pendingToken === '' && $cleanedTranscript !== '') {
        $classification = voice_classify_message($cleanedTranscript);
        if ($classification === 'conversation') {
            return voice_handle_conversation($cleanedTranscript, $rawTranscript, $context, $speechMeta);
        }
    }

    if ($pendingToken !== '') {
        $pending = voice_pending_find($pendingToken);
    } elseif ($transcript !== '' || $cleanedTranscript !== '') {
        $probeText = $cleanedTranscript !== '' ? $cleanedTranscript : $transcript;
        $interpretedProbe = voice_interpret_with_rules($probeText, $context);
        if (in_array($interpretedProbe['intent'] ?? '', array('confirm_pending_action', 'cancel_pending_action'), true)) {
            $pending = voice_find_latest_pending();
        }
    }

    if ($pending) {
        $response = voice_handle_pending_interaction($pending, $cleanedTranscript, $context, $interaction);
        $response['raw_transcript'] = $rawTranscript;
        $response['log_id'] = voice_log_command(array(
            'transcript' => $cleanedTranscript,
            'normalized_text' => voice_normalize_text($cleanedTranscript),
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

    if ($cleanedTranscript === '' && $transcript === '') {
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
        $response['raw_transcript'] = $rawTranscript;
        return $response;
    }

    $interpreted = voice_pipeline_interpret($cleanedTranscript, $context, $speechMeta);
    $resolved = voice_pipeline_resolve($interpreted, $context);
    $validated = voice_pipeline_validate($resolved, $context);

    if (($validated['stage'] ?? '') === 'needs_clarification') {
        $response = voice_make_pending_clarification($validated, $cleanedTranscript, $context);
        $response['ai'] = isset($interpreted['ai']) ? $interpreted['ai'] : array();
        $response['pipeline'] = array(
            'interpret' => $interpreted,
            'resolve' => $resolved,
            'validate' => $validated,
            'execute' => null,
        );
        $response['log_id'] = voice_log_command(array(
            'transcript' => $cleanedTranscript,
            'normalized_text' => voice_normalize_text($cleanedTranscript),
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
        $response['raw_transcript'] = $rawTranscript;
        return $response;
    }

    if (voice_intent_requires_confirmation($validated['intent'] ?? '', $validated['params'] ?? array())) {
        $response = voice_make_pending_confirmation($validated, $cleanedTranscript, $context);
        $response['ai'] = isset($interpreted['ai']) ? $interpreted['ai'] : array();
        $response['pipeline'] = array(
            'interpret' => $interpreted,
            'resolve' => $resolved,
            'validate' => $validated,
            'execute' => null,
        );
        $response['log_id'] = voice_log_command(array(
            'transcript' => $cleanedTranscript,
            'normalized_text' => voice_normalize_text($cleanedTranscript),
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
        $response['raw_transcript'] = $rawTranscript;
        return $response;
    }

    $executed = voice_pipeline_execute($validated, $context);
    $response = voice_build_response(array(
        'ok' => empty($executed['errors']) && (($executed['stage'] ?? '') !== 'error'),
        'stage' => isset($executed['stage']) ? $executed['stage'] : 'interpreted',
        'transcript' => $cleanedTranscript,
        'raw_transcript' => $rawTranscript,
        'normalized_text' => voice_normalize_text($cleanedTranscript),
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
            'transcript' => $cleanedTranscript,
            'normalized_text' => voice_normalize_text($cleanedTranscript),
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
    $response['suggestions'] = voice_generate_suggestions($response, $context);
    voice_track_command_history($response);
    return $response;
}

// ════════════════════════════════════════════════════════════════════
// FASE 1 — Asistente conversacional: nombre, router, memoria, conversación
// ════════════════════════════════════════════════════════════════════

function voice_assistant_name() {
    $settings = storage_read('settings.json');
    $name = trim((string)($settings['voice_assistant_name'] ?? ''));
    return $name !== '' ? $name : 'Jefry';
}

function voice_memory_file() {
    return 'voice_memory.json';
}

function voice_memory_read() {
    $data = storage_read(voice_memory_file());
    if (!is_array($data)) $data = array();
    if (!isset($data['conversation_history']) || !is_array($data['conversation_history'])) {
        $data['conversation_history'] = array();
    }
    if (!isset($data['long_term']) || !is_array($data['long_term'])) {
        $data['long_term'] = array();
    }
    if (!isset($data['pending_questions']) || !is_array($data['pending_questions'])) {
        $data['pending_questions'] = array();
    }
    if (!isset($data['stats']) || !is_array($data['stats'])) {
        $data['stats'] = array(
            'total_conversations' => 0,
            'total_messages' => 0,
            'first_interaction' => null,
            'last_interaction' => null,
        );
    }
    if (!isset($data['user_model']) || !is_array($data['user_model'])) {
        $data['user_model'] = array(
            'personalidad' => array(
                'rasgos' => array(),
                'estilo_comunicacion' => '',
                'triggers_emocionales' => array(),
                'fortalezas' => array(),
                'updated_at' => null,
            ),
            'proyectos' => array(),
            'preocupaciones' => array(),
            'decisiones' => array(),
            'ideas_pendientes' => array(),
            'objetivos' => array(),
            'estado_emocional' => array(
                'actual' => array('mood' => 'neutro', 'intensidad' => 'baja', 'causa_probable' => '', 'desde' => null),
                'historico' => array(),
                'patron_semanal' => '',
                'tendencia' => 'estable',
            ),
            'updated_at' => null,
        );
    }
    return $data;
}

function voice_memory_write($data) {
    if (!is_array($data)) return;
    $existing = voice_memory_read();
    $data = array_merge($existing, $data);
    storage_write(voice_memory_file(), $data);
}

function voice_memory_append_conversation($userText, $assistantText) {
    $data = voice_memory_read();
    $now = now_datetime();
    $data['conversation_history'][] = array(
        'role' => 'user',
        'content' => trim((string)$userText),
        'ts' => $now,
    );
    $data['conversation_history'][] = array(
        'role' => 'assistant',
        'content' => trim((string)$assistantText),
        'ts' => $now,
    );
    // Keep only the last 100 messages (50 exchanges)
    if (count($data['conversation_history']) > 100) {
        $data['conversation_history'] = array_slice($data['conversation_history'], -100);
    }
    // Stats
    $data['stats']['total_messages'] = ($data['stats']['total_messages'] ?? 0) + 2;
    if ($data['stats']['first_interaction'] === null) {
        $data['stats']['first_interaction'] = $now;
    }
    $data['stats']['last_interaction'] = $now;
    voice_memory_write($data);
}

function voice_memory_get_recent($n = 20) {
    $data = voice_memory_read();
    $history = $data['conversation_history'] ?? array();
    return array_slice($history, -$n);
}

// ═══ Memoria largo plazo: aprendizaje autónomo y preguntas proactivas ═══

function voice_memory_add_fact($category, $fact, $source = 'inferred', $confidence = 0.5) {
    $data = voice_memory_read();
    if (!isset($data['long_term'][$category]) || !is_array($data['long_term'][$category])) {
        $data['long_term'][$category] = array();
    }
    // Don't store duplicates
    foreach ($data['long_term'][$category] as $existing) {
        if (($existing['fact'] ?? '') === $fact) return;
        // Similar enough? Skip
        similar_text($existing['fact'] ?? '', $fact, $percent);
        if ($percent > 80) return;
    }
    $data['long_term'][$category][] = array(
        'fact' => $fact,
        'source' => $source,
        'confidence' => min(1.0, max(0.0, (float)$confidence)),
        'stored_at' => now_datetime(),
        'times_recalled' => 1,
    );
    voice_memory_write($data);
}

function voice_memory_autonomous_learn($userText, $assistantText) {
    $data = voice_memory_read();
    $userText = trim((string)$userText);
    $assistantText = trim((string)$assistantText);
    if ($userText === '' || $assistantText === '') return;

    // Pattern 1: User explicitly teaches something
    if (preg_match('/\b(te voy a contar|te explico|para que sepas|recuerda que|ten en cuenta que|apunta esto)\b/iu', $userText)) {
        voice_memory_add_fact('about_user', $userText, 'user_mentioned', 0.9);
    }

    // Pattern 2: Business explanations
    if (preg_match('/\b(en (la mami|casawasap|jostal|publicista)|mi negocio|el negocio de)\b/iu', $userText)
        && mb_strlen($userText, 'UTF-8') > 40) {
        voice_memory_add_fact('business_knowledge', $userText, 'user_mentioned', 0.75);
    }

    // Pattern 3: Preferences expressed
    if (preg_match('/\b(prefiero|me gusta más|no me gusta|mejor|peor)\b/iu', $userText)) {
        voice_memory_add_fact('preferences', $userText, 'inferred', 0.5);
    }

    // Pattern 4: Dates and deadlines mentioned
    if (preg_match('/\b(el \d{1,2} de \w+|en \w+|\d{1,2}/\d{1,2}|principios de|finales de|la semana que viene)\b/iu', $userText)) {
        voice_memory_add_fact('important_dates', $userText, 'user_mentioned', 0.6);
    }

    // Pattern 5: Names + relationships
    if (preg_match('/\b(\w+) (es|era|fue|será) (mi|el|la|un|una) (\w+)\b/iu', $userText, $m)) {
        voice_memory_add_fact('people', $m[0], 'user_mentioned', 0.55);
    }

    // Cleanup old facts periodically (every ~5 conversations)
    $totalConvs = $data['stats']['total_conversations'] ?? 0;
    if ($totalConvs > 0 && $totalConvs % 5 === 0) {
        voice_memory_cleanup();
        // Learn from YouTube history every ~5 conversations
        voice_memory_learn_from_youtube();
    }
}

function voice_memory_cleanup() {
    $data = voice_memory_read();
    $changed = false;
    $now = time();
    foreach ($data['long_term'] as $category => &$facts) {
        if (!is_array($facts)) continue;
        $facts = array_values(array_filter($facts, function ($f) use ($now) {
            $storedAt = strtotime((string)($f['stored_at'] ?? ''));
            $ageDays = $storedAt > 0 ? ($now - $storedAt) / 86400 : 0;
            $confidence = (float)($f['confidence'] ?? 0.5);
            // Remove if: confidence < 0.3 AND > 30 days old
            if ($confidence < 0.3 && $ageDays > 30) return false;
            // Remove if: never recalled AND > 90 days old
            $recalled = (int)($f['times_recalled'] ?? 0);
            if ($recalled === 0 && $ageDays > 90) return false;
            return true;
        }));
    }
    voice_memory_write($data);
}

function voice_memory_get_relevant_facts($query = '') {
    $data = voice_memory_read();
    $allFacts = array();
    foreach ($data['long_term'] as $category => $facts) {
        if (!is_array($facts)) continue;
        foreach ($facts as $f) {
            $confidence = (float)($f['confidence'] ?? 0.5);
            if ($confidence >= 0.4) {
                $allFacts[] = "[$category] " . ($f['fact'] ?? '');
            }
        }
    }
    return array_slice($allFacts, -10); // Return last 10 relevant facts
}

function voice_memory_generate_questions() {
    $data = voice_memory_read();
    $templates = array(
        'business_knowledge' => array(
            '¿En qué se diferencia LaMami de Casawasap exactamente?',
            '¿Cómo funciona Jostal? ¿Es solo alquiler de habitaciones o hay más?',
            '¿Cómo decidiste montar estos negocios?',
        ),
        'about_user' => array(
            '¿Prefieres que use un tono más formal o más cercano?',
            '¿Hay algo que no te guste de cómo funciono?',
        ),
        'preferences' => array(),
    );

    // Check which categories are sparse
    foreach ($data['long_term'] as $category => $facts) {
        $count = is_array($facts) ? count($facts) : 0;
        if ($count <= 1 && isset($templates[$category])) {
            foreach ($templates[$category] as $q) {
                // Don't ask the same question twice
                $alreadyAsked = false;
                foreach ($data['pending_questions'] as $pq) {
                    if (($pq['question'] ?? '') === $q) { $alreadyAsked = true; break; }
                }
                if (!$alreadyAsked && count($data['pending_questions']) < 5) {
                    $data['pending_questions'][] = array(
                        'question' => $q,
                        'category' => $category,
                        'generated_at' => now_datetime(),
                        'asked' => false,
                    );
                }
            }
        }
    }
    voice_memory_write($data);
}

function voice_memory_ask_if_appropriate($context = array()) {
    $data = voice_memory_read();
    $questions = $data['pending_questions'] ?? array();

    // Find the first unasked question
    foreach ($questions as $idx => $q) {
        if (!empty($q['asked'])) continue;

        // Check: enough interactions since last question?
        $lastAskedAt = $data['stats']['last_question_at'] ?? null;
        if ($lastAskedAt && strtotime($lastAskedAt) > strtotime('-5 interactions')) continue;

        $data['pending_questions'][$idx]['asked'] = true;
        $data['pending_questions'][$idx]['asked_at'] = now_datetime();
        $data['stats']['last_question_at'] = now_datetime();
        voice_memory_write($data);
        return $q['question'];
    }

    // If queue is empty, generate more
    if (empty($questions) || count($questions) < 3) {
        voice_memory_generate_questions();
    }

    return null;
}

// ═══════════════════════════════════════════════════════════════════
// DIARIO — Buffer y entradas diarias
// ═══════════════════════════════════════════════════════════════════

function voice_diary_file() {
    return 'diario.json';
}

function voice_diary_read() {
    $data = storage_read(voice_diary_file());
    if (!is_array($data)) $data = array();
    if (!isset($data['buffer']) || !is_array($data['buffer'])) {
        $data['buffer'] = array();
    }
    if (!isset($data['entries']) || !is_array($data['entries'])) {
        $data['entries'] = array();
    }
    if (!isset($data['weekly_summaries']) || !is_array($data['weekly_summaries'])) {
        $data['weekly_summaries'] = array();
    }
    if (!isset($data['meta']) || !is_array($data['meta'])) {
        $data['meta'] = array(
            'last_compiled' => null,
            'last_weekly_summary' => null,
            'total_entries' => 0,
        );
    }
    return $data;
}

function voice_diary_write($data) {
    if (!is_array($data)) return;
    $existing = voice_diary_read();
    $data = array_merge($existing, $data);
    storage_write(voice_diary_file(), $data);
}

/**
 * Añade un fragmento al buffer del día.
 * Máximo 30 fragmentos; si se supera, elimina los de menor confidence.
 */
function voice_diary_buffer_add($item) {
    $data = voice_diary_read();
    $data['buffer'][] = array(
        'ts'         => now_datetime(),
        'tipo'       => trim((string)($item['tipo'] ?? '')),
        'raw_text'   => trim((string)($item['raw_text'] ?? '')),
        'clean_text' => trim((string)($item['clean_text'] ?? '')),
        'mood'       => trim((string)($item['mood'] ?? 'neutro')),
        'tags'       => is_array($item['tags'] ?? null) ? $item['tags'] : array(),
        'confidence' => min(1.0, max(0.0, (float)($item['confidence'] ?? 0.5))),
    );
    // Podar si >30 fragmentos: eliminar los de menor confidence
    if (count($data['buffer']) > 30) {
        usort($data['buffer'], function ($a, $b) {
            return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
        });
        $data['buffer'] = array_slice($data['buffer'], 0, 30);
    }
    voice_diary_write(array('buffer' => $data['buffer']));
}

/**
 * Devuelve los fragmentos del buffer del día actual.
 */
function voice_diary_buffer_get_today() {
    $data = voice_diary_read();
    $today = date('Y-m-d');
    return array_values(array_filter($data['buffer'], function ($item) use ($today) {
        $itemDate = substr((string)($item['ts'] ?? ''), 0, 10);
        return $itemDate === $today;
    }));
}

/**
 * Vacía el buffer (se llama tras compilar la entrada del día).
 */
function voice_diary_buffer_clear() {
    voice_diary_write(array('buffer' => array()));
}

/**
 * Compila la entrada diaria desde los fragmentos del buffer.
 * Si hay < 2 fragmentos no compila (día sin suficiente sustancia).
 * Devuelve la entrada creada o null.
 */
function voice_diary_compile_daily($fecha = null) {
    $fecha = $fecha ?? date('Y-m-d');
    $buffer = voice_diary_buffer_get_today();

    // Si es otra fecha, filtrar el buffer por esa fecha
    if ($fecha !== date('Y-m-d')) {
        $data = voice_diary_read();
        $buffer = array_values(array_filter($data['buffer'], function ($item) use ($fecha) {
            return substr((string)($item['ts'] ?? ''), 0, 10) === $fecha;
        }));
    }

    if (count($buffer) < 2) return null;

    // Concatenar raw_text
    $rawText = '';
    foreach ($buffer as $b) {
        $rawText .= trim((string)($b['raw_text'] ?? '')) . "\n\n";
    }
    $rawText = trim($rawText);

    // Generar clean_text con LLM
    $cleanText = voice_diary_generate_clean_text($rawText, $buffer);

    // Calcular mood predominante
    $moodCounts = array();
    foreach ($buffer as $b) {
        $m = $b['mood'] ?? 'neutro';
        $moodCounts[$m] = ($moodCounts[$m] ?? 0) + ($b['confidence'] ?? 0.5);
    }
    arsort($moodCounts);
    $dominantMood = key($moodCounts) ?: 'neutro';

    // Extraer highlights
    $highlights = voice_diary_extract_highlights($cleanText, $buffer);

    // Consolidar tags únicos
    $tags = array();
    foreach ($buffer as $b) {
        foreach (($b['tags'] ?? array()) as $t) {
            $tags[] = trim((string)$t);
        }
    }
    $tags = array_values(array_unique(array_filter($tags)));

    $entry = array(
        'fecha'       => $fecha,
        'raw_text'    => $rawText,
        'clean_text'  => $cleanText,
        'mood'        => $dominantMood,
        'highlights'  => $highlights,
        'tags'        => $tags,
        'fragmentos'  => count($buffer),
        'compiled_at' => now_datetime(),
    );

    // Guardar entrada
    $data = voice_diary_read();
    // Reemplazar si ya existe entrada de esta fecha
    $found = false;
    foreach ($data['entries'] as $idx => $existing) {
        if (($existing['fecha'] ?? '') === $fecha) {
            $data['entries'][$idx] = $entry;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $data['entries'][] = $entry;
    }
    $data['meta']['last_compiled'] = $fecha;
    $data['meta']['total_entries'] = count($data['entries']);
    voice_diary_write($data);

    // Limpiar buffer
    voice_diary_buffer_clear();

    // Generar embedding de la nueva entrada (async-friendly: si falla, no bloquea)
    voice_diary_embed_entry($entry);

    return $entry;
}

/**
 * Genera una narración coherente del día usando LLM.
 */
function voice_diary_generate_clean_text($rawText, $buffer) {
    $cfg = voice_ai_config();
    if (!$cfg['configured']) return $rawText;

    $prompt = <<<PROMPT
Eres el asistente personal del usuario. A continuación tienes fragmentos sueltos que el usuario ha mencionado durante el día.

Tu tarea es transformarlos en una narración coherente en primera persona del singular, como si fuera una entrada de diario personal. Reglas:
- Respeta el tono del usuario (directo, práctico, sin florituras).
- No inventes hechos ni detalles que no estén en los fragmentos.
- Agrupa por mañana / tarde / noche si hay material suficiente.
- Máximo 400 palabras.
- Responde SOLO con el texto de la entrada de diario, sin preámbulos ni etiquetas.

Fragmentos del día:
{$rawText}
PROMPT;

    $payload = array(
        'model'       => $cfg['model'],
        'temperature' => 0.5,
        'messages'    => array(
            array('role' => 'system', 'content' => 'Eres un escritor de diarios personales. Narración en primera persona, tono auténtico.'),
            array('role' => 'user', 'content' => $prompt),
        ),
        'thinking'        => array('type' => 'enabled'),
        'reasoning_effort' => 'high',
    );

    $ch = curl_init($cfg['chat_url']);
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => array(
            'Authorization: Bearer ' . $cfg['api_key'],
            'Content-Type: application/json',
        ),
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return $rawText;

    $decoded = json_decode($response, true);
    $clean = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
    return $clean !== '' ? $clean : $rawText;
}

/**
 * Extrae 3-5 highlights del día desde el texto limpio y los fragmentos.
 */
function voice_diary_extract_highlights($cleanText, $buffer) {
    // Método simple: extraer los fragmentos con mayor confidence
    $sorted = $buffer;
    usort($sorted, function ($a, $b) {
        return ($b['confidence'] ?? 0) <=> ($a['confidence'] ?? 0);
    });

    $highlights = array();
    foreach (array_slice($sorted, 0, 5) as $b) {
        $title = trim((string)($b['clean_text'] ?? ''));
        if ($title !== '' && strlen($title) > 10) {
            // Tomar primera frase (hasta el primer punto o 100 chars)
            $firstSentence = preg_split('/[.!?]\s+/', $title, 2)[0];
            if (strlen($firstSentence) > 100) {
                $firstSentence = mb_substr($firstSentence, 0, 100) . '...';
            }
            $highlights[] = $firstSentence;
        }
    }
    return array_slice(array_unique($highlights), 0, 5);
}

// ═══════════════════════════════════════════════════════════════════
// DIARIO — Embeddings & Semantic Search
// ═══════════════════════════════════════════════════════════════════

/**
 * Genera un embedding para un texto usando OpenAI text-embedding-3-small.
 * El resultado es un vector de 1536 dimensiones.
 * Cachea por texto para no repetir llamadas.
 */
function voice_diary_embed($text) {
    $text = trim((string)$text);
    if ($text === '') return null;

    // Cache simple: mismo texto → mismo embedding (guardado en diario.json)
    static $cache = array();
    $cacheKey = md5($text);
    if (isset($cache[$cacheKey])) return $cache[$cacheKey];

    $cfg = voice_ai_config();
    if (!$cfg['configured']) return null;

    // Embeddings solo disponibles en OpenAI (no DeepSeek)
    $apiKey = $cfg['api_key'];
    $embeddingUrl = 'https://api.openai.com/v1/embeddings';

    $payload = array(
        'model' => 'text-embedding-3-small',
        'input' => $text,
    );

    $ch = curl_init($embeddingUrl);
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => array(
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ),
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return null;

    $decoded = json_decode($response, true);
    $embedding = $decoded['data'][0]['embedding'] ?? null;
    if (is_array($embedding)) {
        $cache[$cacheKey] = $embedding;
    }
    return $embedding;
}

/**
 * Calcula cosine similarity entre dos vectores.
 * Vectores deben ser arrays del mismo tamaño (normalizados o no, el coseno los maneja).
 */
function voice_diary_cosine_similarity($vecA, $vecB) {
    if (!is_array($vecA) || !is_array($vecB) || count($vecA) !== count($vecB)) return 0.0;
    $dot = 0.0;
    $normA = 0.0;
    $normB = 0.0;
    $len = count($vecA);
    for ($i = 0; $i < $len; $i++) {
        $a = (float)$vecA[$i];
        $b = (float)$vecB[$i];
        $dot += $a * $b;
        $normA += $a * $a;
        $normB += $b * $b;
    }
    if ($normA == 0.0 || $normB == 0.0) return 0.0;
    return $dot / (sqrt($normA) * sqrt($normB));
}

/**
 * Genera y guarda el embedding de una entrada individual.
 * Se llama desde voice_diary_compile_daily() tras compilar.
 * Si falla, no bloquea — la entrada se guarda sin embedding.
 */
function voice_diary_embed_entry(&$entry) {
    $text = $entry['clean_text'] ?? $entry['raw_text'] ?? '';
    if ($text === '') return;

    $embedding = voice_diary_embed($text);
    if ($embedding !== null) {
        $entry['embedding'] = $embedding;
        $entry['embedding_model'] = 'text-embedding-3-small';

        // Actualizar entrada en diario.json
        $data = voice_diary_read();
        foreach ($data['entries'] as $idx => $existing) {
            if (($existing['fecha'] ?? '') === ($entry['fecha'] ?? '')) {
                $data['entries'][$idx]['embedding'] = $embedding;
                $data['entries'][$idx]['embedding_model'] = 'text-embedding-3-small';
                voice_diary_write($data);
                break;
            }
        }
    }
}

/**
 * Busca las top-K entradas del diario más similares a una query.
 * Genera embedding de la query y compara contra todas las entradas con embedding.
 * Si no hay entradas con embedding, intenta embeddear las pendientes primero.
 */
function voice_diary_search_similar($query, $topK = 3) {
    $query = trim((string)$query);
    if ($query === '') return array();

    $queryEmbedding = voice_diary_embed($query);
    if ($queryEmbedding === null) return array();

    $data = voice_diary_read();
    $entries = $data['entries'] ?? array();

    $scored = array();
    foreach ($entries as $idx => $entry) {
        $entryEmbedding = $entry['embedding'] ?? null;
        if (!is_array($entryEmbedding)) continue;

        $score = voice_diary_cosine_similarity($queryEmbedding, $entryEmbedding);
        if ($score > 0.3) { // Umbral mínimo de relevancia
            $scored[] = array(
                'entry' => $entry,
                'score' => round($score, 4),
                'idx' => $idx,
            );
        }
    }

    // Ordenar por score descendente
    usort($scored, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    return array_slice($scored, 0, $topK);
}

/**
 * Genera embeddings para todas las entradas del diario que no tengan uno.
 * Se llama desde voice_diary_compile_daily tras compilar una entrada,
 * y también bajo demanda si voice_diary_search_similar no encuentra nada.
 */
function voice_diary_embed_pending() {
    $data = voice_diary_read();
    $changed = false;

    foreach ($data['entries'] as $idx => $entry) {
        // Saltar si ya tiene embedding
        if (isset($entry['embedding']) && is_array($entry['embedding'])) continue;

        $text = $entry['clean_text'] ?? $entry['raw_text'] ?? '';
        if ($text === '') continue;

        $embedding = voice_diary_embed($text);
        if ($embedding !== null) {
            $data['entries'][$idx]['embedding'] = $embedding;
            $data['entries'][$idx]['embedding_model'] = 'text-embedding-3-small';
            $changed = true;
        }
    }

    // También embeddear weekly_summaries
    foreach ($data['weekly_summaries'] as $idx => $ws) {
        if (isset($ws['embedding']) && is_array($ws['embedding'])) continue;
        $text = $ws['summary'] ?? '';
        if ($text === '') continue;
        $embedding = voice_diary_embed($text);
        if ($embedding !== null) {
            $data['weekly_summaries'][$idx]['embedding'] = $embedding;
            $data['weekly_summaries'][$idx]['embedding_model'] = 'text-embedding-3-small';
            $changed = true;
        }
    }

    if ($changed) {
        voice_diary_write($data);
    }
}

/**
 * Busca entradas del diario relacionadas con un tema y las formatea para system prompt.
 * Se llama cuando el usuario menciona explícitamente algo que puede tener historial (ej: "flujo de caja").
 */
function voice_diary_inject_relevant($query) {
    $results = voice_diary_search_similar($query, 3);
    if (empty($results)) return '';

    $lines = array();
    $lines[] = '[ENTRADAS DEL DIARIO RELACIONADAS CON "' . $query . '"]';
    foreach ($results as $r) {
        $entry = $r['entry'];
        $fecha = $entry['fecha'] ?? '';
        $clean = $entry['clean_text'] ?? '';
        // Tomar primeras 150 letras como snippet
        $snippet = mb_strlen($clean) > 150 ? mb_substr($clean, 0, 147) . '...' : $clean;
        $lines[] = "- $fecha (relevancia: " . $r['score'] . "): $snippet";
    }
    $lines[] = "\nEl usuario está hablando de un tema sobre el que tiene historial en su diario. Úsalo si es relevante para dar contexto o hacer conexiones con el pasado.";

    return implode("\n", $lines);
}

// ═══════════════════════════════════════════════════════════════════
// DIARIO — Classification & Extraction pipeline
// ═══════════════════════════════════════════════════════════════════

/**
 * Clasifica una utterance del usuario en una de 7 categorías de diario.
 * Usa LLM rápido (gpt-4o-mini o el modelo configurado) con timeout 5s.
 * Devuelve {tipo, confidence} o null si es 'casual'.
 */
function voice_diary_classify($utterance) {
    $utterance = trim((string)$utterance);
    if ($utterance === '') return null;

    $cfg = voice_ai_config();
    if (!$cfg['configured']) return null;

    // Usar modelo más rápido/barato si es OpenAI
    $model = ($cfg['provider'] === 'openai') ? 'gpt-4o-mini' : $cfg['model'];
    $chatUrl = $cfg['chat_url'];

    $prompt = <<<PROMPT
Eres un clasificador de intención personal. Analiza el siguiente mensaje del usuario y clasifícalo en UNA de estas categorías:

- personal_reflection: reflexión sobre su estado de ánimo, cómo se siente, cansancio, motivación, ánimo
- project_idea: nueva idea de proyecto, plan de negocio, mejora de proceso, iniciativa
- decision: tomó una decisión explícita sobre algo (ej: "voy a...", "he decidido que...", "a partir de ahora...")
- worry: preocupación, problema, fuente de estrés o ansiedad
- achievement: logro, avance importante, hito, algo bueno que pasó hoy
- factual: dato objetivo, información neutra (no personal)
- casual: saludo, small talk, pregunta genérica, agradecimiento, no merece guardarse en un diario

Responde SOLO en JSON: {"tipo": "categoria", "confidence": 0.XX}

Mensaje del usuario: {$utterance}
PROMPT;

    $payload = array(
        'model'       => $model,
        'temperature' => 0.0,
        'messages'    => array(
            array('role' => 'system', 'content' => 'Eres un clasificador preciso. Responde solo JSON.'),
            array('role' => 'user', 'content' => $prompt),
        ),
    );

    $ch = curl_init($chatUrl);
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => array(
            'Authorization: Bearer ' . $cfg['api_key'],
            'Content-Type: application/json',
        ),
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return null;

    $decoded = json_decode($response, true);
    $content = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
    if ($content === '') return null;

    // Extraer JSON de la respuesta (puede venir con backticks)
    $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
    $content = preg_replace('/\s*```$/i', '', $content);
    $result = json_decode(trim($content), true);
    if (!is_array($result)) return null;

    $tipo = trim((string)($result['tipo'] ?? ''));
    $confidence = (float)($result['confidence'] ?? 0.5);

    if ($tipo === 'casual' || $tipo === '') return null;

    return array('tipo' => $tipo, 'confidence' => min(1.0, max(0.0, $confidence)));
}

/**
 * Extrae un hecho estructurado de una utterance del usuario.
 * Usa LLM rápido con timeout 5s.
 * Devuelve {tipo, titulo, resumen, mood, tags, confidence}.
 */
function voice_diary_extract($utterance, $tipo) {
    $utterance = trim((string)$utterance);
    $tipo = trim((string)$tipo);
    if ($utterance === '' || $tipo === '') return null;

    $cfg = voice_ai_config();
    if (!$cfg['configured']) return null;

    $model = ($cfg['provider'] === 'openai') ? 'gpt-4o-mini' : $cfg['model'];
    $chatUrl = $cfg['chat_url'];

    $prompt = <<<PROMPT
Extrae la información relevante del siguiente mensaje del usuario como un hecho de diario personal.

Tipo detectado: {$tipo}

Devuelve SOLO JSON:
{
  "titulo": "resumen en 8 palabras máximo",
  "resumen": "1-2 frases capturando la esencia del hecho, en primera persona",
  "mood": "motivado|preocupado|cansado|feliz|frustrado|neutro|ilusionado|estresado",
  "tags": ["tag1", "tag2"]
}

Mensaje del usuario: {$utterance}
PROMPT;

    $payload = array(
        'model'       => $model,
        'temperature' => 0.0,
        'messages'    => array(
            array('role' => 'system', 'content' => 'Eres un extractor de hechos precisos. Responde solo JSON sin preámbulos.'),
            array('role' => 'user', 'content' => $prompt),
        ),
    );

    $ch = curl_init($chatUrl);
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => array(
            'Authorization: Bearer ' . $cfg['api_key'],
            'Content-Type: application/json',
        ),
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
    ));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return null;

    $decoded = json_decode($response, true);
    $content = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
    if ($content === '') return null;

    $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
    $content = preg_replace('/\s*```$/i', '', $content);
    $result = json_decode(trim($content), true);
    if (!is_array($result)) return null;

    return array(
        'tipo'       => $tipo,
        'raw_text'   => $utterance,
        'clean_text' => trim((string)($result['resumen'] ?? $result['clean_text'] ?? '')),
        'mood'       => trim((string)($result['mood'] ?? 'neutro')),
        'tags'       => is_array($result['tags'] ?? null) ? $result['tags'] : array(),
        'confidence' => 0.85, // el extractor tiene alta confianza por defecto
    );
}

/**
 * Procesa un texto del usuario: lo parte en oraciones, clasifica cada una
 * y extrae hechos de las que sean diarizables.
 * Devuelve array de items listos para voice_diary_buffer_add().
 */
function voice_diary_process_utterances($text) {
    $text = trim((string)$text);
    if ($text === '') return array();

    // Partir en oraciones por puntuación o longitud máxima
    $sentences = preg_split('/(?<=[.!?])\s+/', $text);
    if (count($sentences) <= 1) {
        // Si es un texto largo sin puntuación, partir por comas o conjunciones
        if (strlen($text) > 150) {
            $sentences = preg_split('/\s*[,;]\s*/', $text);
        } else {
            $sentences = array($text);
        }
    }

    $items = array();
    foreach ($sentences as $sentence) {
        $sentence = trim($sentence);
        if ($sentence === '' || strlen($sentence) < 10) continue;

        $classification = voice_diary_classify($sentence);
        if ($classification === null) continue;

        $tipo = $classification['tipo'];
        $confidence = $classification['confidence'];

        // Solo procesar si confidence >= 0.7
        if ($confidence < 0.7) continue;

        $extracted = voice_diary_extract($sentence, $tipo);
        if ($extracted === null) {
            // Fallback sin LLM: usar la oración tal cual
            $extracted = array(
                'tipo'       => $tipo,
                'raw_text'   => $sentence,
                'clean_text' => $sentence,
                'mood'       => 'neutro',
                'tags'       => array(),
                'confidence' => $confidence,
            );
        } else {
            $extracted['confidence'] = $confidence;
        }

        $items[] = $extracted;
    }

    return $items;
}

/**
 * Pipeline completo: clasifica utterances del usuario → extrae hechos → buffer → user model.
 * Se llama desde voice_handle_conversation() después de cada intercambio.
 */
function voice_diary_pipeline_process($text) {
    $items = voice_diary_process_utterances($text);
    foreach ($items as $item) {
        voice_diary_buffer_add($item);
        if (($item['confidence'] ?? 0) > 0.85) {
            voice_user_model_update_hot($item);
        }
    }
}

/**
 * Genera un resumen compacto de los highlights del diario de los últimos 7 días
 * para inyectar en el system prompt de conversación.
 */
function voice_diary_inject_recent() {
    $data = voice_diary_read();
    $entries = $data['entries'] ?? array();
    if (empty($entries)) return '';

    // Ordenar por fecha descendente, coger últimos 7
    usort($entries, function ($a, $b) {
        return ($b['fecha'] ?? '') <=> ($a['fecha'] ?? '');
    });
    $recent = array_slice($entries, 0, 7);

    $lines = array();
    $lines[] = '[DIARIO — ÚLTIMOS DÍAS]';
    foreach ($recent as $entry) {
        $fecha = $entry['fecha'] ?? '';
        $mood = $entry['mood'] ?? 'neutro';
        $moodEmoji = voice_diary_mood_emoji($mood);

        // Tomar el primer highlight o primera frase del clean_text
        $highlight = '';
        if (!empty($entry['highlights'])) {
            $highlight = $entry['highlights'][0];
        } else {
            $clean = $entry['clean_text'] ?? '';
            $highlight = preg_split('/[.!?]\s+/', $clean, 2)[0] ?? '';
        }
        if (mb_strlen($highlight) > 120) {
            $highlight = mb_substr($highlight, 0, 117) . '...';
        }

        $lines[] = "- $fecha $moodEmoji: $highlight";
    }

    $lines[] = "\nUsa esta información para hacer referencias naturales al pasado del usuario cuando sea relevante. Si hoy menciona algo que conecta con un día anterior, menciónalo. No fuerces referencias si no vienen al caso.";

    return implode("\n", $lines);
}

/**
 * Devuelve emoji para un mood.
 */
function voice_diary_mood_emoji($mood) {
    $map = array(
        'motivado'    => '😊',
        'feliz'       => '😄',
        'ilusionado'  => '🤩',
        'neutro'      => '😐',
        'cansado'     => '😴',
        'preocupado'  => '😟',
        'frustrado'   => '😤',
        'estresado'   => '😰',
    );
    return $map[$mood] ?? '😐';
}

// ═══════════════════════════════════════════════════════════════════
// USER MODEL — Perfil vivo del usuario en voice_memory.json
// ═══════════════════════════════════════════════════════════════════

/**
 * Lee el user_model desde voice_memory.json.
 */
function voice_user_model_read() {
    $data = voice_memory_read();
    return $data['user_model'] ?? array();
}

/**
 * Escribe el user_model parcial (mergea con existente).
 */
function voice_user_model_write($model) {
    if (!is_array($model)) return;
    $data = voice_memory_read();
    $existing = $data['user_model'] ?? array();
    $data['user_model'] = array_merge($existing, $model);
    $data['user_model']['updated_at'] = now_datetime();
    voice_memory_write($data);
}

/**
 * Actualiza el user_model en caliente basado en un fragmento del diario.
 * Solo se ejecuta si confidence > 0.85.
 */
function voice_user_model_update_hot($diaryItem) {
    $tipo = trim((string)($diaryItem['tipo'] ?? ''));
    $cleanText = trim((string)($diaryItem['clean_text'] ?? ''));
    $mood = trim((string)($diaryItem['mood'] ?? 'neutro'));
    $tags = is_array($diaryItem['tags'] ?? null) ? $diaryItem['tags'] : array();
    if ($tipo === '' || $cleanText === '') return;

    $model = voice_user_model_read();
    $now = now_datetime();

    switch ($tipo) {
        case 'personal_reflection':
            // Actualizar estado emocional
            $model['estado_emocional']['actual'] = array(
                'mood' => $mood,
                'intensidad' => 'media',
                'causa_probable' => $cleanText,
                'desde' => $now,
            );
            $model['estado_emocional']['historico'][] = array(
                'fecha' => date('Y-m-d'),
                'mood' => $mood,
            );
            // Mantener solo últimos 30 días
            if (count($model['estado_emocional']['historico']) > 30) {
                $model['estado_emocional']['historico'] = array_slice($model['estado_emocional']['historico'], -30);
            }
            break;

        case 'project_idea':
            // ¿Es un proyecto nuevo o una mención a uno existente?
            $found = false;
            $title = voice_user_model_extract_title($cleanText);
            foreach ($model['proyectos'] as $idx => $proj) {
                similar_text($proj['nombre'] ?? '', $title, $pct);
                if ($pct > 60) {
                    $model['proyectos'][$idx]['ultima_mencion'] = date('Y-m-d');
                    $model['proyectos'][$idx]['menciones'] = ($model['proyectos'][$idx]['menciones'] ?? 0) + 1;
                    if (($model['proyectos'][$idx]['estado'] ?? '') === 'pausado' || ($model['proyectos'][$idx]['estado'] ?? '') === 'abandonado') {
                        $model['proyectos'][$idx]['estado'] = 'activo';
                    }
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $model['proyectos'][] = array(
                    'id' => 'proj_' . substr(md5(uniqid()), 0, 8),
                    'nombre' => $title,
                    'estado' => 'activo',
                    'prioridad' => 'media',
                    'descripcion' => $cleanText,
                    'origen' => 'diario',
                    'primera_mencion' => date('Y-m-d'),
                    'ultima_mencion' => date('Y-m-d'),
                    'menciones' => 1,
                    'hitos' => array(),
                );
            }
            break;

        case 'decision':
            $title = voice_user_model_extract_title($cleanText);
            $newDecision = array(
                'id' => 'dec_' . date('Ymd') . '_' . substr(md5(uniqid()), 0, 6),
                'que' => $title,
                'cuando' => date('Y-m-d'),
                'contexto' => $cleanText,
                'consecuencias' => array(),
                'vigente' => true,
            );
            // Marcar decisiones contradictorias como no vigentes
            foreach ($model['decisiones'] as $idx => $dec) {
                if (!($dec['vigente'] ?? true)) continue;
                similar_text($dec['que'] ?? '', $title, $pct);
                if ($pct > 40) {
                    $model['decisiones'][$idx]['vigente'] = false;
                }
            }
            $model['decisiones'][] = $newDecision;
            break;

        case 'worry':
            $title = voice_user_model_extract_title($cleanText);
            $found = false;
            foreach ($model['preocupaciones'] as $idx => $preoc) {
                similar_text($preoc['tema'] ?? '', $title, $pct);
                if ($pct > 50) {
                    $model['preocupaciones'][$idx]['frecuencia'] = ($model['preocupaciones'][$idx]['frecuencia'] ?? 0) + 1;
                    $model['preocupaciones'][$idx]['ultima_mencion'] = date('Y-m-d');
                    $model['preocupaciones'][$idx]['intensidad'] = 'alta';
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $model['preocupaciones'][] = array(
                    'id' => 'wor_' . substr(md5(uniqid()), 0, 8),
                    'tema' => $title,
                    'categoria' => $tags[0] ?? 'general',
                    'intensidad' => 'alta',
                    'frecuencia' => 1,
                    'primera_mencion' => date('Y-m-d'),
                    'ultima_mencion' => date('Y-m-d'),
                    'resuelta' => false,
                );
            }
            break;

        case 'achievement':
            // Buscar proyecto relacionado y añadir hito
            foreach ($model['proyectos'] as $idx => $proj) {
                $projName = $proj['nombre'] ?? '';
                if ($projName !== '' && stripos($cleanText . ' ' . implode(' ', $tags), $projName) !== false) {
                    $model['proyectos'][$idx]['hitos'][] = array(
                        'fecha' => date('Y-m-d'),
                        'que' => $cleanText,
                    );
                    $model['proyectos'][$idx]['ultima_mencion'] = date('Y-m-d');
                    break;
                }
            }
            // Si menciona objetivo, actualizar progreso
            foreach ($model['objetivos'] as $idx => $obj) {
                $objQue = $obj['que'] ?? '';
                if ($objQue !== '' && stripos($cleanText, $objQue) !== false) {
                    $model['objetivos'][$idx]['progreso_percibido'] = min(100, ($model['objetivos'][$idx]['progreso_percibido'] ?? 0) + 5);
                }
            }
            break;
    }

    voice_user_model_write($model);
}

/**
 * Extrae un título corto (máx 60 chars) de un texto para usar como nombre de proyecto/preocupación/decision.
 */
function voice_user_model_extract_title($text) {
    $text = trim((string)$text);
    // Tomar primera frase hasta punto, o primeros 60 chars
    $first = preg_split('/[.!?]\s*/', $text, 2)[0];
    if (mb_strlen($first) > 60) {
        $first = mb_substr($first, 0, 57) . '...';
    }
    return $first;
}

/**
 * Poda nocturna del user_model:
 * - Preocupaciones no mencionadas en 14+ días → bajan intensidad
 * - Preocupaciones no mencionadas en 30+ días → resueltas
 * - Proyectos no mencionados en 30+ días → pausados
 * - Proyectos no mencionados en 90+ días → abandonados
 * - Ideas pendientes no mencionadas en 60+ días → archivadas
 * - Consolida tendencia emocional
 */
function voice_user_model_prune() {
    $model = voice_user_model_read();
    $today = date('Y-m-d');
    $changed = false;

    // Preocupaciones
    foreach ($model['preocupaciones'] as $idx => $preoc) {
        $lastMention = $preoc['ultima_mencion'] ?? $preoc['primera_mencion'] ?? '';
        if ($lastMention === '') continue;
        $daysSince = (strtotime($today) - strtotime($lastMention)) / 86400;
        if ($daysSince > 30 && !($preoc['resuelta'] ?? false)) {
            $model['preocupaciones'][$idx]['resuelta'] = true;
            $model['preocupaciones'][$idx]['intensidad'] = 'baja';
            $changed = true;
        } elseif ($daysSince > 14 && !($preoc['resuelta'] ?? false)) {
            $model['preocupaciones'][$idx]['intensidad'] = 'media';
            $changed = true;
        }
    }

    // Proyectos
    foreach ($model['proyectos'] as $idx => $proj) {
        $lastMention = $proj['ultima_mencion'] ?? $proj['primera_mencion'] ?? '';
        if ($lastMention === '') continue;
        $daysSince = (strtotime($today) - strtotime($lastMention)) / 86400;
        $currentState = $proj['estado'] ?? 'activo';
        if ($daysSince > 90 && $currentState === 'activo') {
            $model['proyectos'][$idx]['estado'] = 'abandonado';
            $changed = true;
        } elseif ($daysSince > 30 && $currentState === 'activo') {
            $model['proyectos'][$idx]['estado'] = 'pausado';
            $changed = true;
        }
    }

    // Ideas pendientes
    foreach ($model['ideas_pendientes'] as $idx => $idea) {
        $fecha = $idea['fecha'] ?? '';
        if ($fecha === '') continue;
        $daysSince = (strtotime($today) - strtotime($fecha)) / 86400;
        if ($daysSince > 60 && ($idea['estado'] ?? '') === 'pendiente') {
            $model['ideas_pendientes'][$idx]['estado'] = 'archivada';
            $changed = true;
        }
    }

    // Tendencia emocional (últimos 7 moods)
    $historico = $model['estado_emocional']['historico'] ?? array();
    $recentMoods = array_slice(array_column($historico, 'mood'), -7);
    if (count($recentMoods) >= 3) {
        $model['estado_emocional']['tendencia'] = voice_user_model_calc_tendencia($recentMoods);
        $changed = true;
    }

    if ($changed) {
        voice_user_model_write($model);
    }
}

/**
 * Calcula tendencia emocional: 'mejorando', 'empeorando', 'estable', 'oscilante'.
 */
function voice_user_model_calc_tendencia($moods) {
    $positive = array('motivado', 'feliz', 'ilusionado');
    $negative = array('preocupado', 'frustrado', 'estresado', 'cansado');
    $neutral = array('neutro');

    $firstHalf = array_slice($moods, 0, (int)ceil(count($moods) / 2));
    $secondHalf = array_slice($moods, (int)ceil(count($moods) / 2));

    $scoreFirst = voice_user_model_mood_score($firstHalf, $positive, $negative);
    $scoreSecond = voice_user_model_mood_score($secondHalf, $positive, $negative);

    $diff = $scoreSecond - $scoreFirst;
    if (abs($diff) < 0.3) return 'estable';
    if ($diff > 0.5) return 'mejorando';
    if ($diff < -0.5) return 'empeorando';
    return 'oscilante';
}

function voice_user_model_mood_score($moods, $positive, $negative) {
    $score = 0;
    foreach ($moods as $m) {
        if (in_array($m, $positive, true)) $score += 1;
        elseif (in_array($m, $negative, true)) $score -= 1;
    }
    return count($moods) > 0 ? $score / count($moods) : 0;
}

/**
 * Genera un resumen compacto del user model para inyectar en el system prompt (~200 tokens).
 */
function voice_user_model_inject_context() {
    $model = voice_user_model_read();
    $lines = array();

    // Estado emocional actual
    $mood = $model['estado_emocional']['actual']['mood'] ?? 'neutro';
    $moodIntensity = $model['estado_emocional']['actual']['intensidad'] ?? 'baja';
    $lines[] = "Estado anímico: $mood (intensidad: $moodIntensity).";

    // Proyectos activos (top 3 por menciones)
    $activos = array_filter($model['proyectos'] ?? array(), function ($p) {
        return ($p['estado'] ?? '') === 'activo';
    });
    usort($activos, function ($a, $b) {
        return ($b['menciones'] ?? 0) <=> ($a['menciones'] ?? 0);
    });
    $activos = array_slice($activos, 0, 3);
    if (!empty($activos)) {
        $projNames = array_map(function ($p) { return $p['nombre'] ?? ''; }, $activos);
        $lines[] = 'Proyectos activos: ' . implode(' | ', array_filter($projNames));
    }

    // Preocupaciones activas (top 3 por frecuencia)
    $activas = array_filter($model['preocupaciones'] ?? array(), function ($p) {
        return !($p['resuelta'] ?? false);
    });
    usort($activas, function ($a, $b) {
        return ($b['frecuencia'] ?? 0) <=> ($a['frecuencia'] ?? 0);
    });
    $activas = array_slice($activas, 0, 3);
    if (!empty($activas)) {
        $worNames = array_map(function ($p) { return $p['tema'] ?? ''; }, $activas);
        $lines[] = 'Preocupaciones activas: ' . implode(' | ', array_filter($worNames));
    }

    // Últimas decisiones vigentes (top 2)
    $vigentes = array_filter($model['decisiones'] ?? array(), function ($d) {
        return $d['vigente'] ?? false;
    });
    $vigentes = array_reverse(array_slice($vigentes, -2));
    if (!empty($vigentes)) {
        $decNames = array_map(function ($d) { return $d['que'] ?? ''; }, $vigentes);
        $lines[] = 'Decisiones vigentes: ' . implode(' | ', array_filter($decNames));
    }

    if (empty($lines)) return '';

    return "[PERFIL DEL USUARIO — ACTUALIZADO]\n" . implode("\n", $lines);
}

function voice_classify_message($text) {
    $text = trim((string)$text);
    if ($text === '') return 'conversation';

    // 1. Si el pipeline de reglas lo reconoce como comando → command
    $interpreted = voice_interpret_with_rules($text, array());
    if (($interpreted['intent'] ?? '') !== 'unsupported_command'
        && ($interpreted['intent'] ?? '') !== 'ask_clarification') {
        return 'command';
    }

    // 2. Patrones claros de conversación
    $conversationPatterns = array(
        '/\b(qué opinas|cómo ves|qué te parece|crees que|tú qué harías)\b/i',
        '/\b(hola|buenos días|buenas tardes|buenas noches|hey|ey|buenas)\b/i',
        '/\b(gracias|adiós|hasta luego|nos vemos|bye|chao)\b/i',
        '/\b(cuéntame|dime algo|explícame|háblame de|hablamos)\b/i',
        '/\?$/',  // Termina en pregunta
    );
    foreach ($conversationPatterns as $pattern) {
        if (preg_match($pattern, $text)) return 'conversation';
    }

    // 3. Frases muy cortas sin verbo de acción → conversación
    $wordCount = str_word_count(voice_normalize_text($text));
    $hasActionVerb = preg_match('/\b(abre|crea|busca|muestra|dime|ve|añade|registra|apaga|enciende|cambia|marca)\b/i', $text);
    if ($wordCount <= 4 && !$hasActionVerb) return 'conversation';

    // 4. Por defecto → command (intenta interpretar)
    return 'command';
}

function voice_conversation_system_prompt() {
    $name = voice_assistant_name();
    return "Eres $name, el asistente virtual de Josué. Eres una persona cercana, práctica, con carácter y sentido del humor. Hablas en español natural, con frases cortas pero cálidas. Tu tono es informal y de confianza, como un amigo que además te ayuda con el trabajo.

Josué gestiona 4 negocios desde este CRM:

1. LaMami (lamami.online): Plataforma de intermediación. Trabajadoras se dan de alta (29€ pago único) y reciben clientes extra. Solo pagan comisión cuando llega un cliente (10€/30min, 20€/1h). Modelo: 'si no llega cliente, no pagas'.

2. Casawasap: Gestión de clientes con sistema de pagos y bots de WhatsApp. Cada cliente puede tener su propio bot automatizado vinculado a su ficha. Se gestionan contactos, pagos, altas/bajas y estados.

3. Jostal: Alquiler de habitaciones por horas/días. Además, Josué ofrece a las inquilinas un servicio de publicidad: las anuncia en portales y les deriva clientes, cobrando comisión por cada uno que llega. Doble modelo: alquiler + comisión por clientes derivados.

4. Publicista: Herramienta interna que convierte una foto real de una trabajadora en un pack publicitario completo mediante IA: genera imágenes finales, títulos, textos de anuncios y exporta listo para publicar en portales.

5. AutoTube: Tu fábrica automatizada de contenido en YouTube. Pipeline completo: scraping de contenido viral → guión con IA → narración TTS → montaje con MoviePy → SEO → upload programado a YouTube → analytics → monetización vía YouTube Partner Program. Varios canales activos con temáticas de misterio, historia y ciencia. Modelo: publicar videos automáticamente y monetizar con anuncios (CPM $5-$12). Coste operativo: ~$11/mes.

Reglas de conversación:
- Responde SIEMPRE en español natural, frases cortas. Sin parrafadas.
- Puedes hablar de CUALQUIER tema: clima, noticias, consejos, anécdotas, ideas de negocio.
- Si Josué te cuenta algo personal, escucha con empatía y responde con naturalidad.
- Si menciona algo relacionado con sus negocios, propón acciones concretas en el CRM cuando tenga sentido, pero sin ser pesado.
- Tienes sentido del humor, pero no eres un payaso. Ironía fina cuando toque, cero chistes forzados.
- Conoces los datos del CRM y puedes acceder a ellos si es relevante.
- Si no sabes algo, dilo. No te inventes datos.
- Tienes memoria a largo plazo. Recuerdas datos sobre Josué, sus negocios, preferencias
  y conversaciones anteriores. El contexto de memoria se te proporciona al inicio.
  Si un dato que recuerdas es relevante, úsalo con naturalidad.
- Si Josué te dice \"te voy a contar algo\", \"apunta esto\", o te explica en detalle
  algo sobre sus negocios, presta especial atención porque es información importante.
- Detecta el idioma en que te habla Josué y responde en el mismo idioma.
  Si te habla en inglés, responde en inglés. Si en español, en español.
  Por defecto, habla en español.
- Detecta el estado de ánimo de Josué por cómo habla. Si notas frustración,
  cansancio o enfado, sé más empática y ofrece ayuda concreta. Si está contento,
  celebra con él. Adapta tu tono a su estado emocional.
- Puedes usar estas HERRAMIENTAS para buscar información externa. Para usarlas,
  escribe EXACTAMENTE en una línea aparte: TOOL:nombre|argumento
  Herramientas disponibles:
  • TOOL:weather|ciudad — tiempo actual y previsión
  • TOOL:search|consulta — buscar en internet
  • TOOL:fetch|url — leer el contenido de una página web
  • TOOL:date — fecha, hora y día de la semana actual
  • TOOL:crm — resumen rápido del CRM (clientas activas, ingresos hoy)
  • TOOL:calc|expresión — calcular una operación matemática
  • TOOL:autotube — estadísticas de AutoTube (canales, views, revenue, YPP)
  • TOOL:send_whatsapp|nombre_contacto|mensaje — buscar y enviar WhatsApp a un contacto (se envía DE VERDAD)
  • TOOL:read_whatsapp — leer en voz alta los mensajes de WhatsApp no leídos
  • TOOL:reply_whatsapp|nombre_contacto|mensaje — responder a un contacto de WhatsApp
  • TOOL:set_mode|modo|true/false — cambiar modo (silent, accompanied, proactive)
  • TOOL:search_contact|nombre — buscar persona en clientas, interesadas y agenda
  • TOOL:play_music|estado_de_ánimo — poner música (ej: alegre, chill, reggaeton, electrónica, pop, rock)
  • TOOL:parking|save/recall — guardar o recuperar posición del coche aparcado
  • TOOL:voice_control|accion|valor — controlar música (pause_music, resume_music, skip_song, volume|50)
  • TOOL:whiteboard|modo|tipo|contenido|duracion — mostrar algo visualmente en la pizarra
    modo: flash (se cierra sola a los N segundos) o modal (persistente, la cierra el usuario)
    tipo: chart (gráfico de ventas), image (generar imagen con IA), html (mostrar HTML), text (texto grande)
    duración: solo para flash, segundos (por defecto 5). En modal se ignora.
    Cuándo usarla: SOLO si lo visual aporta algo que la voz no puede transmitir.
    NUNCA para: música, navegación, parking, preguntas simples, confirmaciones.
    Ejemplos:
      TOOL:whiteboard|flash|html|<h1>☀️ Buenos días</h1>|5
      TOOL:whiteboard|modal|chart|ventas del mes
      TOOL:whiteboard|modal|image|paisaje de montaña al atardecer
  Cuando uses una herramienta, pon la línea TOOL primero y luego tu respuesta
  para el usuario en las líneas siguientes. Si no necesitas herramientas,
  simplemente responde normal, sin TOOL.";
}

function voice_conversation_chat($userText, $history = array()) {
    $cfg = voice_ai_config();
    if (!$cfg['configured']) {
        return 'Lo siento, no tengo mi conexión de IA configurada ahora mismo. ¿Puedes revisar la configuración de voz en Ajustes?';
    }

    $name = voice_assistant_name();
    $messages = array();

    // Build system prompt with memory context
    $systemPrompt = voice_conversation_system_prompt();
    $relevantFacts = voice_memory_get_relevant_facts();
    if (!empty($relevantFacts)) {
        $systemPrompt .= "\n\nDATOS QUE CONOCES SOBRE JOSUÉ Y SUS NEGOCIOS (memoria a largo plazo):\n";
        foreach ($relevantFacts as $fact) {
            $systemPrompt .= "- $fact\n";
        }
        $systemPrompt .= "\nUsa estos datos cuando sea relevante en la conversación, pero no los menciones explícitamente a menos que vengan al caso.";
    }

    // Inject user model context (perfil vivo)
    $userModelCtx = voice_user_model_inject_context();
    if ($userModelCtx !== '') {
        $systemPrompt .= "\n\n" . $userModelCtx . "\n\nUsa esta información para personalizar tus respuestas: si está preocupado, sé más comprensivo. Si tiene proyectos activos, relaciónalos con lo que te pregunta. Si hay decisiones vigentes, tenlas en cuenta.";
    }

    // Inject recent diary highlights (últimos 7 días)
    $diaryCtx = voice_diary_inject_recent();
    if ($diaryCtx !== '') {
        $systemPrompt .= "\n\n" . $diaryCtx;
    }

    // Inject WhatsApp recent activity
    $wasapCtx = wasap_learning_inject_context();
    if ($wasapCtx !== '') {
        $systemPrompt .= "\n\n" . $wasapCtx;
    }
    $messages[] = array('role' => 'system', 'content' => $systemPrompt);

    // Add recent history (last 16 messages = 8 exchanges)
    if (!empty($history)) {
        $recent = array_slice($history, -16);
        foreach ($recent as $entry) {
            $role = ($entry['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $content = trim((string)($entry['content'] ?? ''));
            if ($content !== '') {
                $messages[] = array('role' => $role, 'content' => $content);
            }
        }
    }

    // Current user message
    $messages[] = array('role' => 'user', 'content' => trim((string)$userText));

    $payload = array(
        'model' => $cfg['model'],
        'temperature' => 0.7,
        'messages' => $messages,
        'thinking' => array('type' => 'enabled'),
        'reasoning_effort' => 'high',
    );

    $ch = curl_init($cfg['chat_url']);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $cfg['api_key'],
            'Content-Type: application/json',
        ),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !is_string($response) || $response === '') {
        return 'Vaya, me he quedado en blanco. ¿Me repites eso?';
    }

    $decoded = json_decode($response, true);
    $content = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
    if ($content === '') {
        return 'No sé qué decirte ahora mismo. ¿Probamos con otra cosa?';
    }

    return $content;
}

function voice_handle_conversation($cleanedText, $rawText, $context, $speechMeta) {
    $history = voice_memory_get_recent(20);

    $aiResponse = voice_conversation_chat($cleanedText, $history);
    $allSystemActions = array();
    $allWhiteboard = null;

    $maxToolRounds = 2;
    for ($round = 0; $round < $maxToolRounds; $round++) {
        $toolResults = voice_execute_tools_with_actions($aiResponse);
        if ($toolResults === null) break;

        $toolText = $toolResults['text_results'];
        if (!empty($toolResults['system_actions'])) {
            $allSystemActions = array_merge($allSystemActions, $toolResults['system_actions']);
        }
        // Collect whiteboard from this round
        if (!empty($toolResults['whiteboard'])) {
            $allWhiteboard = $toolResults['whiteboard'];
        }

        $followupPrompt = "Resultados de las herramientas que has solicitado:\n\n" . $toolText . "\n\nAhora responde al usuario con esta información, en español natural y breve.";
        $aiResponse = voice_conversation_chat($followupPrompt, $history);
    }

    voice_memory_append_conversation($cleanedText, $aiResponse);

    // Autonomous learning from this exchange
    voice_memory_autonomous_learn($cleanedText, $aiResponse);

    // ── Diary pipeline: classify utterances → buffer → user model ──
    voice_diary_pipeline_process($cleanedText);

    // ── WhatsApp learning: process pending messages from WhatsApp ──
    if (function_exists('wasap_learning_process_pending')) {
        wasap_learning_process_pending();
    }

    $data = voice_memory_read();
    $data['stats']['total_conversations'] = ($data['stats']['total_conversations'] ?? 0) + 1;
    voice_memory_write($data);

    // Check for proactive question to offer
    $proactiveQuestion = voice_memory_ask_if_appropriate($context);

    // Check for milestones and records (gamification)
    $milestoneMsg = voice_check_milestones();
    if (!$milestoneMsg) $milestoneMsg = voice_check_record_day();

    $fullMessage = $aiResponse;
    if ($milestoneMsg) {
        $fullMessage = $milestoneMsg . "\n\n" . $fullMessage;
    }
    if ($proactiveQuestion && ($data['stats']['total_conversations'] ?? 0) % 3 === 0) {
        $fullMessage .= "\n\n💭 " . $proactiveQuestion;
    }

    return voice_build_response(array(
        'ok' => true,
        'stage' => 'executed',
        'transcript' => $cleanedText,
        'raw_transcript' => $rawText,
        'intent' => 'conversation',
        'context' => $context,
        'message' => $fullMessage,
        'execution_mode' => 'readonly',
        'ux' => voice_make_ux($fullMessage, '', '', false),
        'system_actions' => $allSystemActions,
        'whiteboard' => $allWhiteboard,
    ));
}

// ════════════════════════════════════════════════════════════════════
// FASE 2 — Herramientas de internet y function calling
// ════════════════════════════════════════════════════════════════════

function voice_execute_tools_from_response($aiResponse) {
    $aiResponse = trim((string)$aiResponse);
    if ($aiResponse === '') return null;

    $results = array();
    $lines = explode("\n", $aiResponse);
    $foundAny = false;

    foreach ($lines as $line) {
        $line = trim($line);
        if (!preg_match('/^TOOL:\s*(\w+)\s*\|?\s*(.*)$/i', $line, $m)) continue;

        $foundAny = true;
        $toolName = strtolower(trim($m[1]));
        $arg = trim($m[2] ?? '');

        $result = '';
        switch ($toolName) {
            case 'weather':
                $result = voice_tool_get_weather($arg);
                break;
            case 'search':
                $result = voice_tool_web_search($arg);
                break;
            case 'fetch':
                $result = voice_tool_web_fetch($arg);
                break;
            case 'date':
                $result = voice_tool_get_date_info();
                break;
            case 'crm':
                $result = voice_tool_get_crm_summary();
                break;
            case 'prioritize_clients':
                $result = voice_tool_prioritize_clients();
                break;
            case 'campaign_health':
                $result = voice_tool_campaign_health();
                break;
            case 'forecast':
                $result = voice_tool_forecast();
                break;
            case 'calc':
                $result = voice_tool_calculate($arg);
                break;
            case 'autotube':
                $result = autotube_format_summary();
                $result = $result !== null ? $result : 'AutoTube no está accesible ahora.';
                break;
            case 'send_whatsapp':
                $result = voice_tool_send_whatsapp($arg);
                break;
            case 'read_whatsapp':
                $result = voice_tool_read_whatsapp($arg);
                break;
            case 'reply_whatsapp':
                $result = voice_tool_reply_whatsapp($arg);
                break;
            case 'set_mode':
                $result = voice_tool_set_mode($arg);
                break;
            case 'search_contact':
                $result = voice_tool_search_contact($arg);
                break;
            case 'play_music':
                $result = voice_tool_play_music($arg);
                break;
            case 'parking':
                $result = voice_tool_parking($arg);
                break;
            case 'voice_control':
                $result = voice_tool_voice_control($arg);
                break;
            default:
                $result = "Herramienta '$toolName' no disponible.";
        }
        $results[] = "[$toolName] " . ($result !== '' ? $result : 'Sin resultado.');
    }

    return $foundAny ? implode("\n", $results) : null;
}

// ── Execute tools and extract system_actions from JSON responses ──
function voice_execute_tools_with_actions($aiResponse) {
    $aiResponse = trim((string)$aiResponse);
    if ($aiResponse === '') return null;

    $textResults = array();
    $systemActions = array();
    $whiteboardData = array();
    $foundAny = false;
    $lines = explode("\n", $aiResponse);

    foreach ($lines as $line) {
        $line = trim($line);
        if (!preg_match('/^TOOL:\s*(\w+)\s*\|?\s*(.*)$/i', $line, $m)) continue;

        $foundAny = true;
        $toolName = strtolower(trim($m[1]));
        $arg = trim($m[2] ?? '');

        // Use the existing switch logic by calling the inner function
        $result = voice_execute_single_tool($toolName, $arg);

        // Check if result is JSON (contains system_actions or whiteboard)
        if (is_string($result) && strlen($result) > 0 && $result[0] === '{') {
            $decoded = json_decode($result, true);

            // Collect whiteboard data
            if ($decoded && isset($decoded['whiteboard'])) {
                $whiteboardData[] = $decoded['whiteboard'];
            }

            // Existing system_actions handling
            if ($decoded && isset($decoded['system_actions'])) {
                $systemActions = array_merge($systemActions, $decoded['system_actions']);
                $textResults[] = "[$toolName] " . ($decoded['message'] ?? 'Acción ejecutada.');
                continue;
            }
        }

        $textResults[] = "[$toolName] " . ($result !== '' ? $result : 'Sin resultado.');
    }

    if (!$foundAny) return null;

    return array(
        'text_results' => implode("\n", $textResults),
        'system_actions' => $systemActions,
        'whiteboard' => !empty($whiteboardData) ? end($whiteboardData) : null,
    );
}

function voice_execute_single_tool($toolName, $arg) {
    switch ($toolName) {
        case 'weather':         return voice_tool_get_weather($arg);
        case 'search':          return voice_tool_web_search($arg);
        case 'fetch':           return voice_tool_web_fetch($arg);
        case 'date':            return voice_tool_get_date_info();
        case 'crm':             return voice_tool_get_crm_summary();
        case 'prioritize_clients': return voice_tool_prioritize_clients();
        case 'campaign_health': return voice_tool_campaign_health();
        case 'forecast':        return voice_tool_forecast();
        case 'calc':            return voice_tool_calculate($arg);
        case 'autotube':        $r = autotube_format_summary(); return $r !== null ? $r : 'AutoTube no accesible.';
        case 'send_whatsapp':   return voice_tool_send_whatsapp($arg);
        case 'read_whatsapp':   return voice_tool_read_whatsapp($arg);
        case 'reply_whatsapp':  return voice_tool_reply_whatsapp($arg);
        case 'set_mode':        return voice_tool_set_mode($arg);
        case 'search_contact':  return voice_tool_search_contact($arg);
        case 'play_music':      return voice_tool_play_music($arg);
        case 'parking':         return voice_tool_parking($arg);
        case 'voice_control':   return voice_tool_voice_control($arg);
        case 'whiteboard':      return voice_tool_whiteboard($arg);
        default:                return "Herramienta '$toolName' no disponible.";
    }
}

function voice_tool_get_weather($city) {
    $city = trim((string)$city);
    if ($city === '') return 'Indica una ciudad.';

    $url = 'https://wttr.in/' . urlencode($city) . '?format=4&lang=es';
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
    ));
    $result = curl_exec($ch);
    curl_close($ch);

    $result = trim((string)$result);
    if ($result === '') return "No pude consultar el tiempo para $city.";
    return $result;
}

function voice_tool_web_fetch($url) {
    $url = trim((string)$url);
    if ($url === '') return 'Indica una URL.';
    if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;

    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; JefryAssistant/1.0)',
    ));
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !is_string($html) || $html === '') {
        return "No pude acceder a $url (código $httpCode).";
    }

    // Convert HTML to readable text
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);

    if (mb_strlen($text, 'UTF-8') > 6000) {
        $text = mb_substr($text, 0, 6000, 'UTF-8') . '… [truncado]';
    }

    return $text !== '' ? $text : "La página $url no tiene contenido de texto legible.";
}

function voice_tool_web_search($query) {
    $query = trim((string)$query);
    if ($query === '') return 'Indica qué quieres buscar.';

    $url = 'https://lite.duckduckgo.com/lite/?q=' . urlencode($query);
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; JefryAssistant/1.0)',
    ));
    $html = curl_exec($ch);
    curl_close($ch);

    if (!is_string($html) || $html === '') {
        return "No pude buscar '$query'. Intenta de nuevo.";
    }

    // Parse DuckDuckGo Lite results
    $results = array();
    // Each result is typically a link inside a <td> with class or structure
    preg_match_all('#<a[^>]*href="([^"]*)"[^>]*>(.*?)</a>#is', $html, $linkMatches, PREG_SET_ORDER);
    preg_match_all('#<td[^>]*class="[^"]*result-snippet[^"]*"[^>]*>(.*?)</td>#is', $html, $snippetMatches);

    $count = 0;
    foreach ($linkMatches as $i => $link) {
        $href = trim($link[1] ?? '');
        $title = trim(strip_tags($link[2] ?? ''));
        if ($href === '' || $title === '' || strpos($href, 'duckduckgo.com') !== false) continue;
        if (strpos($href, '//') === 0) $href = 'https:' . $href;

        $snippet = '';
        if (isset($snippetMatches[1][$i])) {
            $snippet = trim(strip_tags($snippetMatches[1][$i]));
        }

        $results[] = ($count + 1) . ". $title\n   $href" . ($snippet !== '' ? "\n   $snippet" : '');
        $count++;
        if ($count >= 5) break;
    }

    if (empty($results)) {
        return "No encontré resultados claros para '$query'. Prueba con otras palabras.";
    }

    return "Resultados para '$query':\n\n" . implode("\n\n", $results);
}

function voice_tool_get_date_info() {
    setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'Spanish_Spain');
    $now = new DateTime('now', new DateTimeZone('Europe/Madrid'));

    $diaSemana = $now->format('l');
    $diasES = array(
        'Monday' => 'lunes', 'Tuesday' => 'martes', 'Wednesday' => 'miércoles',
        'Thursday' => 'jueves', 'Friday' => 'viernes', 'Saturday' => 'sábado', 'Sunday' => 'domingo',
    );
    $diaSemanaES = $diasES[$diaSemana] ?? $diaSemana;

    $mesesES = array(
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    );
    $mes = $mesesES[(int)$now->format('n')] ?? $now->format('F');

    return sprintf(
        "Hoy es %s, %d de %s de %d. Son las %s. Estamos en la semana %d del año.",
        $diaSemanaES,
        (int)$now->format('d'),
        $mes,
        (int)$now->format('Y'),
        $now->format('H:i'),
        (int)$now->format('W')
    );
}

function voice_tool_get_crm_summary() {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $monthStart = date('Y-m-01');

    // ── Clientas activas ──
    $lamamiClientas = storage_read('clientas.json') ?: array();
    $lamamiActivas = 0;
    foreach ((array)$lamamiClientas as $c) {
        if (($c['estado'] ?? '') === 'alta' || ($c['estado'] ?? '') === 'en_casa') $lamamiActivas++;
    }

    $jostalClientas = storage_read('jostal_clientas.json') ?: array();
    $jostalActivas = 0;
    foreach ((array)$jostalClientas as $c) {
        if (($c['estado'] ?? '') === 'alta') $jostalActivas++;
    }

    $casawasapContactos = storage_read('casawasap_contactos.json') ?: array();

    // ── Leads hoy ──
    $todayLeads = array();
    $todayLeadsLamami = 0;
    $todayLeadsJostal = 0;
    $leads = storage_read('leads.json') ?: array();
    $interesadas = storage_read('interesadas.json') ?: array();
    foreach ((array)$leads as $l) {
        if (substr((string)($l['created_at'] ?? ''), 0, 10) === $today) {
            $todayLeads[] = $l;
            $branch = $l['branch'] ?? $l['rama'] ?? '';
            if ($branch === 'lamami') $todayLeadsLamami++;
            elseif ($branch === 'jostal') $todayLeadsJostal++;
        }
    }
    foreach ((array)$interesadas as $l) {
        if (substr((string)($l['created_at'] ?? $l['creado'] ?? ''), 0, 10) === $today) {
            $todayLeads[] = $l;
            $todayLeadsLamami++;
        }
    }
    $todayLeadsCount = count($todayLeads);

    // ── Ingresos hoy, ayer, mes ──
    $todayIncome = ingresos_total_periodo($today, $today);
    $yesterdayIncome = ingresos_total_periodo($yesterday, $yesterday);
    $monthIncome = ingresos_total_periodo($monthStart, $today);
    $daysElapsed = (int)date('j');
    $avgDaily = $daysElapsed > 0 ? round($monthIncome / $daysElapsed) : 0;

    // ── Gastos hoy ──
    $todayExpenses = 0;
    $gastos = storage_read('gastos.json') ?: array();
    foreach ((array)$gastos as $g) {
        if (($g['fecha'] ?? '') === $today) $todayExpenses += (float)($g['importe'] ?? $g['cantidad'] ?? 0);
    }

    // ── Clientas contactadas hoy ──
    $todayContacted = 0;
    foreach ((array)$lamamiClientas as $c) {
        $last = $c['ultimo_contacto'] ?? $c['last_contact'] ?? '';
        if ($last && substr($last, 0, 10) === $today) $todayContacted++;
    }

    // ── vs yesterday ──
    $vsYesterday = '';
    if ($yesterdayIncome > 0) {
        $pct = round(($todayIncome - $yesterdayIncome) / $yesterdayIncome * 100);
        $vsYesterday = $pct >= 0 ? "+{$pct}% vs ayer" : "{$pct}% vs ayer";
    }

    // ── vs monthly average ──
    $vsAvg = '';
    if ($avgDaily > 0) {
        $pct = round(($todayIncome - $avgDaily) / $avgDaily * 100);
        $vsAvg = $pct >= 0 ? "+{$pct}% vs media diaria" : "{$pct}% vs media diaria";
    }

    $beneficio = $todayIncome - $todayExpenses;

    $lines = array();
    $lines[] = "📊 Resumen hoy " . date('d/m');
    $lines[] = "💰 Ingresos: {$todayIncome}€";
    if ($todayExpenses > 0) $lines[] = "💸 Gastos: {$todayExpenses}€";
    if ($beneficio != $todayIncome) $lines[] = "📈 Beneficio: {$beneficio}€";
    if ($todayLeadsCount > 0) {
        $leadDetail = "{$todayLeadsCount} leads";
        if ($todayLeadsLamami > 0) $leadDetail .= " ({$todayLeadsLamami} LaMami)";
        if ($todayLeadsJostal > 0) $leadDetail .= " ({$todayLeadsJostal} Jostal)";
        $lines[] = "👥 {$leadDetail}";
    }
    if ($todayContacted > 0) $lines[] = "📞 {$todayContacted} clientas contactadas";
    if ($vsYesterday) $lines[] = "📊 {$vsYesterday}";
    if ($vsAvg) $lines[] = "📊 {$vsAvg}";
    $lines[] = "- LaMami: {$lamamiActivas} clientas activas";
    $lines[] = "- Jostal: {$jostalActivas} clientas activas";
    $lines[] = "- Casawasap: " . count($casawasapContactos) . " contactos";

    $avisos = storage_read('avisos.json') ?: array();
    $activeAvisos = count(array_filter((array)$avisos, function ($a) { return empty($a['read_at']); }));
    if ($activeAvisos > 0) $lines[] = "- ⚠️ {$activeAvisos} avisos sin leer";

    $lines[] = '- ' . (autotube_format_summary() ?: 'AutoTube: sin datos');

    return implode("\n", $lines);
}

// ── Tool: Prioritize clients (top 3 to contact today) ─────────────────

function voice_tool_prioritize_clients() {
    $clientas = storage_read('clientas.json') ?: array();
    $today = date('Y-m-d');
    $scored = array();

    foreach ($clientas as $c) {
        $nombre = $c['nombre'] ?? $c['name'] ?? '?';
        $estado = $c['estado'] ?? '';
        $ingresos = (float)($c['ingresos_totales'] ?? $c['total_ingresos'] ?? 0);
        $ultimoContacto = $c['ultimo_contacto'] ?? $c['last_contact'] ?? '';

        // Days without contact
        $diasSinContacto = 365;
        if ($ultimoContacto) {
            $ultimo = new DateTime(substr($ultimoContacto, 0, 10));
            $hoy = new DateTime($today);
            $diasSinContacto = (int)$ultimo->diff($hoy)->days;
        }

        // Contacted today?
        $contactadaHoy = $ultimoContacto && substr($ultimoContacto, 0, 10) === $today;

        // Lead nuevo sin atender (created today or yesterday, no contact)
        $esLeadNuevo = false;
        $created = $c['created'] ?? $c['creado'] ?? $c['fecha_alta'] ?? '';
        if ($created) {
            $createdDate = substr($created, 0, 10);
            if ($createdDate >= date('Y-m-d', strtotime('-2 days')) && $diasSinContacto >= 2) {
                $esLeadNuevo = true;
            }
        }

        // Score
        $score = ($diasSinContacto / 30) * 40
               + ($estado === 'en_casa' ? 30 : ($estado === 'alta' ? 15 : 0))
               + min($ingresos / 500, 20)
               + ($esLeadNuevo ? 25 : 0)
               - ($contactadaHoy ? 50 : 0);

        if ($score > 0) {
            $scored[] = array(
                'nombre' => $nombre,
                'score' => round($score, 1),
                'dias' => $diasSinContacto,
                'estado' => $estado,
                'ingresos' => $ingresos,
                'nuevo' => $esLeadNuevo,
            );
        }
    }

    // Sort by score descending, take top 3
    usort($scored, function ($a, $b) { return $b['score'] <=> $a['score']; });
    $top = array_slice($scored, 0, 3);

    if (empty($top)) return "No hay clientas pendientes de contacto. ¡Buen trabajo!";

    $lines = array("🎯 Top clientas a contactar hoy:");
    foreach ($top as $i => $c) {
        $num = $i + 1;
        $reason = array();
        if ($c['nuevo']) $reason[] = 'lead nuevo';
        if ($c['estado'] === 'en_casa') $reason[] = 'en casa';
        if ($c['dias'] > 0) $reason[] = "{$c['dias']}d sin contacto";
        if ($c['ingresos'] > 0) $reason[] = "{$c['ingresos']}€ generados";
        $reasonStr = !empty($reason) ? ' (' . implode(', ', $reason) . ')' : '';
        $lines[] = "{$num}. {$c['nombre']}{$reasonStr}";
    }

    return implode("\n", $lines);
}

// ── Tool: Campaign health (detect stale campaigns) ────────────────────

function voice_tool_campaign_health() {
    $campaigns = storage_read('publicista_campanas.json') ?: array();
    if (empty($campaigns)) {
        // Try alternate file name
        $campaigns = storage_read('campanas.json') ?: array();
    }

    $stale = array();
    $cutoff = date('Y-m-d', strtotime('-3 days'));

    foreach ($campaigns as $c) {
        $estado = $c['estado'] ?? $c['status'] ?? '';
        if ($estado !== 'activa' && $estado !== 'active') continue;

        $ultimaPublicacion = $c['ultima_publicacion'] ?? $c['last_publish'] ?? $c['updated_at'] ?? '';
        if (!$ultimaPublicacion) continue;

        $pubDate = substr($ultimaPublicacion, 0, 10);
        if ($pubDate < $cutoff) {
            $nombre = $c['nombre'] ?? $c['name'] ?? $c['titulo'] ?? '?';
            $dias = (int)(new DateTime($pubDate))->diff(new DateTime())->days;
            $stale[] = array('nombre' => $nombre, 'dias' => $dias);
        }
    }

    if (empty($stale)) return "Todas las campañas están al día. Ninguna parada.";

    $lines = array("⚠️ Campañas paradas:");
    foreach ($stale as $s) {
        $lines[] = "- {$s['nombre']}: {$s['dias']} días sin publicar";
    }
    return implode("\n", $lines);
}

// ── Tool: Forecast (month-end projection) ────────────────────────────

function voice_tool_forecast() {
    $monthStart = date('Y-m-01');
    $today = date('Y-m-d');
    $daysInMonth = (int)date('t');
    $daysElapsed = (int)date('j');
    $daysRemaining = $daysInMonth - $daysElapsed;

    $monthIncome = ingresos_total_periodo($monthStart, $today);
    $avgDaily = $daysElapsed > 0 ? $monthIncome / $daysElapsed : 0;
    $projection = $monthIncome + ($avgDaily * $daysRemaining);

    // Weekend adjustment: if remaining days include weekends, reduce by ~15%
    $weekendDays = 0;
    for ($i = 1; $i <= $daysRemaining; $i++) {
        $dayOfWeek = (int)date('N', strtotime("+{$i} days"));
        if ($dayOfWeek >= 6) $weekendDays++;
    }
    $weekendRatio = $daysRemaining > 0 ? $weekendDays / $daysRemaining : 0;
    $adjustedProjection = round($projection * (1 - $weekendRatio * 0.15));

    // Best month this year
    $yearStart = date('Y-01-01');
    $bestMonth = 0;
    $bestMonthName = '';
    for ($m = 1; $m <= (int)date('n'); $m++) {
        $ms = date('Y-m-01', strtotime("{$yearStart} +" . ($m - 1) . " months"));
        $me = date('Y-m-t', strtotime($ms));
        $mIncome = ingresos_total_periodo($ms, $me);
        if ($mIncome > $bestMonth) {
            $bestMonth = $mIncome;
            $bestMonthName = date('F', strtotime($ms));
        }
    }

    // Best day this month
    $bestDay = 0;
    $bestDayDate = '';
    for ($d = 1; $d <= $daysElapsed; $d++) {
        $ds = date('Y-m-') . str_pad($d, 2, '0', STR_PAD_LEFT);
        $dIncome = ingresos_total_periodo($ds, $ds);
        if ($dIncome > $bestDay) {
            $bestDay = $dIncome;
            $bestDayDate = (string)$d;
        }
    }

    $ranking = $adjustedProjection > $bestMonth ? 'sería el mejor del año' : "sería el 2º mejor, por detrás de {$bestMonthName} ({$bestMonth}€)";

    $lines = array();
    $lines[] = "📈 Proyección de " . date('F') . ":";
    $lines[] = "- Ingresos hasta hoy: {$monthIncome}€";
    $lines[] = "- Media diaria: " . round($avgDaily) . "€/día";
    $lines[] = "- Días restantes: {$daysRemaining}";
    $lines[] = "- Proyección fin de mes: {$adjustedProjection}€";
    if ($bestDay > 0) $lines[] = "- Mejor día del mes: {$bestDay}€ (día {$bestDayDate})";
    $lines[] = "- {$ranking}";

    return implode("\n", $lines);
}

function voice_tool_calculate($expression) {
    $expression = trim((string)$expression);
    if ($expression === '') return 'Indica una expresión matemática.';

    // Only allow numbers, basic operators, parentheses, dots, spaces
    if (!preg_match('/^[\d\s\+\-\*\/\.\(\)\%\,\^]+$/', $expression)) {
        return 'Solo puedo calcular operaciones matemáticas básicas.';
    }

    // Replace ^ with ** for power
    $expression = str_replace('^', '**', $expression);

    try {
        $result = @eval("return ($expression);");
        if ($result === false || !is_numeric($result)) {
            return "No pude calcular '$expression'.";
        }
        return "$expression = $result";
    } catch (\Throwable $e) {
        return "No pude calcular '$expression'.";
    }
}

// ════════════════════════════════════════════════════════════════════
// FASE 4 — Configuración por voz bidireccional
// ════════════════════════════════════════════════════════════════════

function voice_config_start($params) {
    return voice_config_build_step('name', array(
        'message' => 'Vamos a configurarme. Te iré preguntando paso a paso. Empecemos: ¿cómo quieres que me llame?',
        'tts_text' => 'Vamos a configurarme. Te iré preguntando paso a paso. Empecemos: ¿cómo quieres que me llame?',
    ));
}

function voice_config_build_step($step, $data = array()) {
    $name = voice_assistant_name();
    $token = voice_pending_create('config_step', array(
        'transcript' => $data['transcript'] ?? '',
        'normalized_text' => '',
        'intent' => 'configure_assistant',
        'params' => array(),
        'context' => array(),
        'config_step' => $step,
        'config_state' => $data['config_state'] ?? array(),
        'message' => $data['message'] ?? '',
    ));

    return array(
        'stage' => 'needs_clarification',
        'intent' => 'configure_assistant',
        'params' => array(),
        'context' => array(),
        'message' => $data['message'] ?? '',
        'tts_text' => $data['tts_text'] ?? ($data['message'] ?? ''),
        'tts_importance' => 'high',
        'pending' => voice_response_pending_meta(array('token' => $token, 'kind' => 'config_step', 'status' => 'pending')),
        'execution_mode' => 'preview',
        'ok' => true,
    );
}

function voice_config_handle_step($pending, $commandText, $context, $interaction) {
    $step = $pending['config_step'] ?? 'name';
    $state = $pending['config_state'] ?? array();
    $normalized = voice_normalize_text($commandText);
    $followAction = trim((string)($interaction['followup_action'] ?? ''));

    // Allow cancellation at any step
    if ($followAction === 'cancel' || voice_is_cancellation_text($normalized)) {
        voice_pending_close($pending['token'], 'cancelled');
        return voice_build_response(array(
            'ok' => true, 'stage' => 'executed', 'intent' => 'cancel_pending_action',
            'message' => 'Configuración cancelada. Sigo con mi configuración actual.',
            'execution_mode' => 'preview',
            'ux' => voice_make_ux('Configuración cancelada', 'Sigo con mi configuración actual.', '', false),
        ));
    }

    switch ($step) {
        case 'name':
            return voice_config_step_name($pending, $normalized, $state);

        case 'name_confirm':
            if ($normalized === 'sí' || $normalized === 'si' || $normalized === 'eso es' || $normalized === 'correcto' || $normalized === 'vale') {
                $state['assistant_name'] = $state['proposed_name'] ?? 'Jefry';
                return voice_config_step_wake_word($pending, $state);
            }
            // Retry
            voice_pending_close($pending['token'], 'completed');
            return voice_config_build_step('name', array(
                'message' => 'Vale, repítemelo: ¿cómo quieres que me llame?',
                'tts_text' => 'Vale, repítemelo. ¿Cómo quieres que me llame?',
                'config_state' => $state,
            ));

        case 'wake_word':
            return voice_config_step_wake_word_handle($pending, $normalized, $state);

        case 'wake_word_confirm':
            if ($normalized === 'sí' || $normalized === 'si' || $normalized === 'eso es' || $normalized === 'correcto' || $normalized === 'vale' || $normalized === 'así está bien' || $normalized === 'asi esta bien') {
                voice_pending_close($pending['token'], 'completed');
                return voice_config_step_voice($pending, $state);
            }
            // Retry: ask again what wake word they want
            voice_pending_close($pending['token'], 'completed');
            return voice_config_step_wake_word($pending, $state);

        case 'voice':
            return voice_config_step_voice_handle($pending, $normalized, $state, $followAction, $interaction);

        case 'voice_confirm':
            if ($normalized === 'sí' || $normalized === 'si' || $normalized === 'me gusta' || $normalized === 'vale') {
                voice_pending_close($pending['token'], 'completed');
                return voice_config_step_language($state);
            }
            // Retry voice selection
            voice_pending_close($pending['token'], 'completed');
            return voice_config_step_voice($pending, $state);

        case 'language':
            return voice_config_step_language_handle($pending, $normalized, $state);

        case 'proactive':
            return voice_config_step_proactive_handle($pending, $normalized, $state);

        case 'confirm':
            if ($normalized === 'sí' || $normalized === 'si' || $normalized === 'guarda' || $normalized === 'guardar' || $normalized === 'vale') {
                voice_pending_close($pending['token'], 'completed');
                return voice_config_save($state);
            }
            // Restart
            voice_pending_close($pending['token'], 'completed');
            return voice_config_build_step('name', array(
                'message' => 'Vale, empecemos de nuevo: ¿cómo quieres que me llame?',
                'tts_text' => 'Vale, empecemos de nuevo. ¿Cómo quieres que me llame?',
            ));

        default:
            voice_pending_close($pending['token'], 'completed');
            return voice_config_start(array());
    }
}

// ── Step 1: Name ─────────────────────────────────────────────────────

function voice_config_step_name($pending, $normalized, $state) {
    if ($normalized === '' || mb_strlen($normalized, 'UTF-8') > 30) {
        voice_pending_close($pending['token'], 'completed');
        return voice_config_build_step('name', array(
            'message' => 'No he entendido bien el nombre. Dímelo otra vez, porfa.',
            'tts_text' => 'No he entendido bien el nombre. Dímelo otra vez.',
            'config_state' => $state,
        ));
    }

    $state['proposed_name'] = $normalized;
    voice_pending_close($pending['token'], 'completed');
    return voice_config_build_step('name_confirm', array(
        'message' => 'He entendido "' . $normalized . '". ¿Es correcto?',
        'tts_text' => 'He entendido ' . $normalized . '. ¿Es correcto?',
        'config_state' => $state,
    ));
}

// ── Step 1b: Wake Word (activación manos libres) ──────────────────────

function voice_config_step_wake_word($pending, $state) {
    $name = $state['assistant_name'] ?? 'Jefry';
    $defaultWake = 'Oye ' . $name;

    voice_pending_close($pending['token'], 'completed');
    return voice_config_build_step('wake_word', array(
        'message' => "Ahora dime: ¿quieres poder llamarme sin tocar el micrófono?\n\n" .
                     "Si dices 'sí', la palabra de activación será '$defaultWake'. Cuando la digas, empezaré a escucharte sin que pulses nada.\n\n" .
                     "Si dices 'no', solo funcionaré cuando pulses el botón del micro.\n\n" .
                     "También puedes decir tu propia palabra de activación ahora mismo (por ejemplo: 'coche' o 'asistente').",
        'tts_text' => '¿Quieres poder llamarme sin tocar el micrófono? Di sí para activar ' . $defaultWake . ', o di no para usar solo el botón. También puedes inventar tu propia palabra.',
        'config_state' => $state,
    ));
}

function voice_config_step_wake_word_handle($pending, $normalized, $state) {
    $name = $state['assistant_name'] ?? 'Jefry';

    // No wake word
    if (preg_match('/\b(no|nada|desactivado|apagado|solo bot[óo]n|sin voz)\b/iu', $normalized)) {
        $state['wake_enabled'] = false;
        $state['wake_word'] = '';
        voice_pending_close($pending['token'], 'completed');
        return voice_config_step_voice($pending, $state);
    }

    // Custom wake word provided directly
    $custom = trim(mb_strtolower($normalized, 'UTF-8'));
    $filtered = preg_replace('/\b(oye|hey|hola|ok|s[ií]|vale|activar|manos libres|quiero|palabra|activaci[óo]n)\b/iu', '', $custom);
    $filtered = trim(preg_replace('/\s+/', ' ', $filtered));

    // If what remains is just "sí" or empty, use default
    if ($filtered === '' || $filtered === 'sí' || $filtered === 'si' || mb_strlen($filtered, 'UTF-8') < 2) {
        $state['wake_word'] = $name; // Base word, "Oye X" is built by client
        $state['wake_enabled'] = true;
    } else {
        // User provided a custom word
        $state['wake_word'] = mb_convert_case($filtered, MB_CASE_TITLE, 'UTF-8');
        $state['wake_enabled'] = true;
    }

    $wakeDisplay = $state['wake_enabled'] ? ('Oye ' . $state['wake_word']) : 'desactivado';

    voice_pending_close($pending['token'], 'completed');
    return voice_config_build_step('wake_word_confirm', array(
        'message' => "Palabra de activación: $wakeDisplay. ¿Está bien? Di 'sí' para seguir o dime otra palabra.",
        'tts_text' => 'Palabra de activación: ' . $wakeDisplay . '. ¿Está bien?',
        'config_state' => $state,
    ));
}

// ── Step 2: Voice ─────────────────────────────────────────────────────

function voice_config_step_voice($pending, $state) {
    voice_pending_close($pending['token'], 'completed');
    return voice_config_build_step('voice', array(
        'message' => "Perfecto. Ahora vamos a elegir mi voz. Voy a decir una frase con cada voz disponible:\n\n" .
                     "Voz 1 - Nova (femenina, cálida): 'Hola, soy " . ($state['assistant_name'] ?? 'Jefry') . ", tu asistente virtual. ¿Qué tal te sueno?'\n\n" .
                     "Voz 2 - Shimmer (femenina, dulce)\n" .
                     "Voz 3 - Alloy (neutra, versátil)\n" .
                     "Voz 4 - Echo (masculina, grave)\n\n" .
                     "Cuando oigas la que te guste, di 'para' o 'esa'. Si quieres repetir, di 'siguiente'.",
        'tts_text' => 'Perfecto. Ahora vamos a elegir mi voz. Escucha las opciones y di "para" cuando oigas la que te guste.',
        'config_state' => $state,
    ));
}

function voice_config_step_voice_handle($pending, $normalized, $state, $followAction, $interaction) {
    $voicesAvailable = array('nova', 'shimmer', 'alloy', 'echo');
    $voiceLabels = array(
        'nova' => 'Nova (femenina, cálida)',
        'shimmer' => 'Shimmer (femenina, dulce)',
        'alloy' => 'Alloy (neutra, versátil)',
        'echo' => 'Echo (masculina, grave)',
    );
    $voiceIndex = (int)($state['voice_index'] ?? 0);

    if ($normalized === 'siguiente' || $normalized === 'otra' || $normalized === 'no me gusta') {
        $voiceIndex++;
        if ($voiceIndex >= count($voicesAvailable)) $voiceIndex = 0;
        $state['voice_index'] = $voiceIndex;
        voice_pending_close($pending['token'], 'completed');
        $voiceName = $voicesAvailable[$voiceIndex];
        return voice_config_build_step('voice', array(
            'message' => 'Voz ' . ($voiceIndex + 1) . ' - ' . $voiceLabels[$voiceName] . '. Di "para" o "esa" si te gusta.',
            'tts_text' => 'Esta es la voz número ' . ($voiceIndex + 1) . '. Di "para" si te gusta.',
            'config_state' => $state,
        ));
    }

    if ($normalized === 'para' || $normalized === 'esa' || $normalized === 'esa me gusta') {
        $state['tts_voice'] = $voicesAvailable[$voiceIndex];
        voice_pending_close($pending['token'], 'completed');
        return voice_config_build_step('voice_confirm', array(
            'message' => 'Voz ' . $voiceLabels[$state['tts_voice']] . ' seleccionada. ¿Te gusta como sueno?',
            'tts_text' => 'Voz seleccionada. ¿Te gusta como sueno?',
            'config_state' => $state,
        ));
    }

    // If user said a number directly
    if (is_numeric($normalized) && $normalized >= 1 && $normalized <= count($voicesAvailable)) {
        $state['tts_voice'] = $voicesAvailable[(int)$normalized - 1];
        voice_pending_close($pending['token'], 'completed');
        return voice_config_build_step('voice_confirm', array(
            'message' => 'Voz ' . $voiceLabels[$state['tts_voice']] . ' seleccionada. ¿Te gusta?',
            'tts_text' => 'Voz seleccionada. ¿Te gusta como sueno?',
            'config_state' => $state,
        ));
    }

    // Default: proceed to next voice
    $voiceIndex++;
    if ($voiceIndex >= count($voicesAvailable)) $voiceIndex = 0;
    $state['voice_index'] = $voiceIndex;
    voice_pending_close($pending['token'], 'completed');
    $voiceName = $voicesAvailable[$voiceIndex];
    return voice_config_build_step('voice', array(
        'message' => 'Voz ' . ($voiceIndex + 1) . ' - ' . $voiceLabels[$voiceName] . '. Di "para" o "esa" si te gusta.',
        'tts_text' => 'Esta es la voz ' . ($voiceIndex + 1) . '.',
        'config_state' => $state,
    ));
}

// ── Step 3: Language ──────────────────────────────────────────────────

function voice_config_step_language($state) {
    return voice_config_build_step('language', array(
        'message' => "¿En qué idioma prefieres que te hable?\n1. Español\n2. Inglés (English)",
        'tts_text' => '¿En qué idioma prefieres que te hable? Español o inglés.',
        'config_state' => $state,
    ));
}

function voice_config_step_language_handle($pending, $normalized, $state) {
    if (preg_match('/\b(español|espanol|españa|spanish|castellano|1)\b/iu', $normalized)) {
        $state['language'] = 'es';
    } elseif (preg_match('/\b(ingles|inglés|english|2)\b/iu', $normalized)) {
        $state['language'] = 'en';
    } else {
        $state['language'] = 'es'; // default
    }

    voice_pending_close($pending['token'], 'completed');
    return voice_config_step_proactive($state);
}

// ── Step 4: Proactive mode ───────────────────────────────────────────

function voice_config_step_proactive($state) {
    $langLabel = ($state['language'] ?? 'es') === 'en' ? 'English' : 'Español';
    return voice_config_build_step('proactive', array(
        'message' => "Idioma: $langLabel. Ahora dime: ¿quieres que sea proactiva y te sugiera cosas sin que me las pidas?\n\n" .
                     "Por ejemplo: avisarte de campañas paradas, recordarte tareas, sugerirte acciones según lo que hagas.\n\n" .
                     "Di 'sí' o 'no'.",
        'tts_text' => 'Una última cosa. ¿Quieres que te sugiera cosas sin que me las pidas? Por ejemplo, avisarte de campañas paradas. Di sí o no.',
        'config_state' => $state,
    ));
}

function voice_config_step_proactive_handle($pending, $normalized, $state) {
    if (preg_match('/\b(s[ií]|vale|dale|claro|ok|por supuesto)\b/iu', $normalized)) {
        $state['proactive'] = true;
    } else {
        $state['proactive'] = false;
    }

    voice_pending_close($pending['token'], 'completed');
    return voice_config_step_confirm($state);
}

// ── Step 5: Confirmation ──────────────────────────────────────────────

function voice_config_step_confirm($state) {
    $name = $state['assistant_name'] ?? 'Jefry';
    $voice = $state['tts_voice'] ?? 'nova';
    $language = ($state['language'] ?? 'es') === 'en' ? 'Inglés' : 'Español';
    $proactive = ($state['proactive'] ?? true) ? 'Sí' : 'No';
    $wakeEnabled = $state['wake_enabled'] ?? true;
    $wakeWord = $state['wake_word'] ?? $name;
    $wakeDisplay = $wakeEnabled ? ('Oye ' . $wakeWord) : 'Desactivado';

    $summary = "Resumen de la configuración:\n" .
               "- Nombre: $name\n" .
               "- Activación: $wakeDisplay\n" .
               "- Voz: $voice\n" .
               "- Idioma: $language\n" .
               "- Proactiva: $proactive\n\n" .
               "¿Guardo esta configuración? Di 'sí' para guardar o 'no' para empezar de nuevo.";

    return voice_config_build_step('confirm', array(
        'message' => $summary,
        'tts_text' => 'Resumen. Nombre: ' . $name . '. Activación: ' . $wakeDisplay . '. Voz: ' . $voice . '. Idioma: ' . $language . '. Proactiva: ' . $proactive . '. ¿Guardo?',
        'config_state' => $state,
    ));
}

// ── Save ──────────────────────────────────────────────────────────────

function voice_config_save($state) {
    $settings = storage_read('settings.json');
    $name = $state['assistant_name'] ?? 'Jefry';
    $voice = $state['tts_voice'] ?? 'nova';
    $language = $state['language'] ?? 'es';
    $proactive = $state['proactive'] ?? true;
    $wakeEnabled = $state['wake_enabled'] ?? true;
    $wakeWord = $state['wake_word'] ?? 'Jefry';

    $settings['voice_assistant_name'] = $name;
    $settings['voice_assistant_tts_voice'] = $voice;
    $settings['voice_assistant_language'] = $language;
    $settings['voice_assistant_proactive'] = $proactive;
    $settings['voice_wake_enabled'] = $wakeEnabled;
    $settings['voice_wake_word'] = $wakeWord;
    storage_write('settings.json', $settings);

    $wakeMsg = $wakeEnabled ? (" Di 'Oye $wakeWord' para activarme sin tocar el micro.") : ' Usa el botón del micro para hablarme.';

    return voice_build_response(array(
        'ok' => true,
        'stage' => 'executed',
        'intent' => 'configure_assistant',
        'message' => "¡Configuración guardada! A partir de ahora soy $name, con voz $voice, en " . ($language === 'en' ? 'inglés' : 'español') . ".$wakeMsg",
        'tts_text' => 'Configuración guardada. A partir de ahora soy ' . $name . '. ' . ($wakeEnabled ? 'Di Oye ' . $wakeWord . ' para llamarme.' : 'Usa el botón para hablar conmigo.') . ' ¡Encantada!',
        'tts_importance' => 'high',
        'execution_mode' => 'write',
        'ux' => voice_make_ux('¡Configuración guardada!', "Nombre: $name · Voz: $voice · Idioma: " . ($language === 'en' ? 'inglés' : 'español'), '', false),
    ));
}

// ════════════════════════════════════════════════════════════════════
// FASE 6 — Sugerencias proactivas post-comando
// ════════════════════════════════════════════════════════════════════

function voice_suggestion_templates() {
    $templates = array(
        // Sequential: B follows A logically
        array(
            'type' => 'sequential',
            'trigger_intent' => 'open_tab',
            'trigger_tab' => 'clientas',
            'label' => '¿Filtrar por las que están en casa?',
            'action' => 'clientas en casa',
        ),
        array(
            'type' => 'sequential',
            'trigger_intent' => 'open_tab',
            'trigger_tab' => 'interesadas',
            'label' => '¿Ver solo las nuevas sin atender?',
            'action' => 'interesadas nuevas',
        ),
        array(
            'type' => 'sequential',
            'trigger_intent' => 'create_eureka',
            'label' => '¿Generar el prompt Codex para esta eureka?',
            'action' => 'generar prompt de la eureka',
        ),
        array(
            'type' => 'sequential',
            'trigger_intent' => 'open_page',
            'trigger_page' => 'publicista',
            'label' => '¿Revisar campañas pendientes?',
            'action' => 'abre publicista campañas',
        ),
        array(
            'type' => 'sequential',
            'trigger_intent' => 'open_page',
            'trigger_page' => 'lamami',
            'label' => '¿Ver las clientas activas?',
            'action' => 'abre clientas',
        ),
        // Anomaly: something unusual detected
        array(
            'type' => 'anomaly',
            'trigger_intent' => 'query_analytics',
            'label' => '¿Quieres ver el desglose por rama?',
            'action' => 'compara ramas',
        ),
        // Temporal: time-based
        array(
            'type' => 'temporal',
            'trigger_hour_start' => 7,
            'trigger_hour_end' => 11,
            'label' => '¿Quieres el resumen de la mañana?',
            'action' => 'dame el resumen',
        ),
        array(
            'type' => 'temporal',
            'trigger_hour_start' => 19,
            'trigger_hour_end' => 23,
            'label' => '¿Resumen del día de hoy?',
            'action' => 'cómo ha ido el día',
        ),
        // Generic: always available
        array(
            'type' => 'generic',
            'trigger_intent' => null,
            'label' => '¿Cómo van los números hoy?',
            'action' => 'cómo van los números hoy',
        ),
        array(
            'type' => 'generic',
            'trigger_intent' => null,
            'label' => '¿Alguna eureka nueva?',
            'action' => 'abre eurekas',
        ),
        // AutoTube + YouTube
        array(
            'type' => 'generic',
            'trigger_intent' => null,
            'label' => '¿Cómo va AutoTube?',
            'action' => 'cómo va autotube',
        ),
        array(
            'type' => 'generic',
            'trigger_intent' => null,
            'label' => '¿Qué hay en las noticias?',
            'action' => 'noticias de hoy',
        ),
        array(
            'type' => 'sequential',
            'trigger_intent' => 'open_tab',
            'trigger_tab' => 'reproductor',
            'label' => '¿Sugerirte videos basados en lo que escuchas?',
            'action' => 'sugiéreme videos',
        ),
    );
    return $templates;
}

function voice_suggestion_score($template, $response, $context) {
    $score = 0;
    $intent = $response['intent'] ?? '';
    $params = $response['params'] ?? array();
    $tab = $params['tab'] ?? ($context['tab'] ?? '');
    $page = $params['page'] ?? ($context['page'] ?? '');

    // Relevance: does this template match the current intent? (×3)
    $triggerIntent = $template['trigger_intent'] ?? null;
    if ($triggerIntent !== null) {
        if ($intent === $triggerIntent) $score += 3 * 3;

        // Check trigger_tab match
        $triggerTab = $template['trigger_tab'] ?? null;
        if ($triggerTab !== null && $tab === $triggerTab) $score += 2 * 3;

        // Check trigger_page match
        $triggerPage = $template['trigger_page'] ?? null;
        if ($triggerPage !== null && $page === $triggerPage) $score += 2 * 3;
    }

    // Urgency: is it temporal and relevant now? (×4)
    $type = $template['type'] ?? '';
    if ($type === 'temporal') {
        $hour = (int)date('G');
        $start = (int)($template['trigger_hour_start'] ?? 0);
        $end = (int)($template['trigger_hour_end'] ?? 24);
        if ($hour >= $start && $hour <= $end) $score += 2 * 4;
    }

    // Generic templates always score a baseline
    if ($type === 'generic' && $triggerIntent === null) {
        $score += 1 * 1; // Low baseline, only shown if nothing better
    }

    return $score;
}

function voice_generate_suggestions($response, $context) {
    $templates = voice_suggestion_templates();
    $scored = array();

    foreach ($templates as $template) {
        $s = voice_suggestion_score($template, $response, $context);
        if ($s > 0) {
            $scored[] = array(
                'label' => $template['label'],
                'action' => $template['action'],
                'type' => $template['type'],
                'score' => $s,
            );
        }
    }

    // Sort by score descending
    usort($scored, function ($a, $b) {
        return $b['score'] - $a['score'];
    });

    // Return top 2
    $top = array_slice($scored, 0, 2);
    // Remove score from output
    return array_map(function ($s) {
        return array('label' => $s['label'], 'action' => $s['action'], 'type' => $s['type']);
    }, $top);
}

// ════════════════════════════════════════════════════════════════════
// FASE 7 — Recordatorios multicanal (WhatsApp + In-App + Push)
// ════════════════════════════════════════════════════════════════════

function voice_parse_reminder_time($text) {
    $text = trim((string)$text);
    $dueAt = null;
    $now = new DateTime('now', new DateTimeZone('Europe/Madrid'));

    // "en X minutos"
    if (preg_match('/\ben\s+(\d+)\s*minutos?\b/iu', $text, $m)) {
        $minutes = (int)$m[1];
        $dueAt = (clone $now)->modify("+$minutes minutes");
    }
    // "en X horas"
    elseif (preg_match('/\ben\s+(\d+)\s*horas?\b/iu', $text, $m)) {
        $hours = (int)$m[1];
        $dueAt = (clone $now)->modify("+$hours hours");
    }
    // "mañana a las HH:MM" or "mañana a las HH"
    elseif (preg_match('/ma[ñn]ana\s+a\s+las\s+(\d{1,2})(?::(\d{2}))?/iu', $text, $m)) {
        $hour = (int)$m[1];
        $min = isset($m[2]) ? (int)$m[2] : 0;
        $dueAt = (clone $now)->modify('+1 day')->setTime($hour, $min);
    }
    // "el lunes/martes/... a las HH:MM"
    elseif (preg_match('/el\s+(lunes|martes|mi[ée]rcoles|jueves|viernes|s[áa]bado|domingo)\s*(a las\s+(\d{1,2})(?::(\d{2}))?)?/iu', $text, $m)) {
        $daysES = array('lunes' => 'monday', 'martes' => 'tuesday', 'miércoles' => 'wednesday', 'miercoles' => 'wednesday',
            'jueves' => 'thursday', 'viernes' => 'friday', 'sábado' => 'saturday', 'sabado' => 'saturday', 'domingo' => 'sunday');
        $dayEN = $daysES[mb_strtolower($m[1] ?? '')] ?? null;
        if ($dayEN) {
            $dueAt = (clone $now)->modify("next $dayEN");
            if (isset($m[3])) {
                $hour = (int)$m[3];
                $min = isset($m[4]) ? (int)$m[4] : 0;
                $dueAt->setTime($hour, $min);
            } else {
                $dueAt->setTime(9, 0); // Default 9:00
            }
        }
    }
    // Default: 30 minutes from now
    if ($dueAt === null) {
        $dueAt = (clone $now)->modify('+30 minutes');
    }

    return $dueAt->format('Y-m-d H:i:s');
}

function voice_create_reminder($params) {
    $descripcion = trim((string)($params['descripcion'] ?? ''));
    if ($descripcion === '') {
        return array('ok' => false, 'message' => 'No has dicho qué quieres que te recuerde.', 'errors' => array('empty_description'));
    }

    $dueAt = voice_parse_reminder_time($descripcion);
    $currentUrl = $_SERVER['REQUEST_URI'] ?? 'index.php?page=dashboard';

    $reminder = array(
        'id' => generate_id('rem'),
        'descripcion' => $descripcion,
        'due_at' => $dueAt,
        'context_url' => $currentUrl,
        'delivery' => array(
            'in_app' => true,
            'whatsapp' => false,
        ),
        'status' => 'pending',
        'delivered' => false,
        'created_at' => now_datetime(),
        'created_by' => 'voice',
    );

    $reminders = storage_read('voice_reminders.json');
    $reminders[] = $reminder;
    storage_write('voice_reminders.json', $reminders);

    $dueFormatted = date('H:i', strtotime($dueAt));
    $when = (strtotime($dueAt) - time()) < 3600
        ? "en " . ceil((strtotime($dueAt) - time()) / 60) . " minutos"
        : "a las $dueFormatted";

    return array(
        'ok' => true,
        'message' => "Recordatorio guardado: \"$descripcion\". Te avisaré $when.",
        'row' => $reminder,
    );
}

function voice_check_reminders() {
    $reminders = storage_read('voice_reminders.json');
    $now = now_datetime();
    $due = array();

    foreach ($reminders as $idx => $r) {
        if (($r['status'] ?? '') !== 'pending') continue;
        if (!empty($r['delivered'])) continue;
        if (($r['due_at'] ?? '') > $now) continue;

        $due[] = $r;
        // Mark as delivered in storage
        $reminders[$idx]['delivered'] = true;
        $reminders[$idx]['delivered_at'] = $now;
    }

    if (!empty($due)) {
        storage_write('voice_reminders.json', $reminders);
        // Try WhatsApp delivery for reminders that have it enabled
        foreach ($due as $r) {
            if (!empty($r['delivery']['whatsapp'])) {
                voice_reminder_deliver_whatsapp($r);
            }
        }
    }

    return $due;
}

function voice_reminder_deliver_whatsapp($reminder) {
    $settings = storage_read('settings.json');
    $userPhone = trim((string)($settings['admin_phone'] ?? ''));
    if ($userPhone === '') return;

    $desc = $reminder['descripcion'] ?? '';
    $contextUrl = $reminder['context_url'] ?? '';
    $link = $contextUrl !== '' ? 'https://admin.casawasap.com/control/' . ltrim($contextUrl, '/') : '';

    $message = "🔔 Recordatorio: $desc";
    if ($link !== '') {
        $message .= "\n\n📋 Abrir: $link";
    }

    // Use WAHA API to send WhatsApp message
    voice_reminder_send_waha_message($userPhone, $message);
}

function voice_reminder_send_waha_message($phone, $message) {
    $wahaPort = trim((string)(storage_read('settings.json')['waha_default_port'] ?? '3000'));
    $wahaUrl = "http://127.0.0.1:$wahaPort/api/sendText";

    $payload = json_encode(array(
        'chatId' => preg_replace('/[^\d]/', '', $phone) . '@c.us',
        'text' => $message,
        'session' => 'default',
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init($wahaUrl);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 3,
    ));
    curl_exec($ch);
    curl_close($ch);
}

// ════════════════════════════════════════════════════════════════════
// FASE 8 — Ejecución unificada de intents CRM
// ════════════════════════════════════════════════════════════════════

function voice_execute_entity_redirect($intent, $params, $context) {
    $entityHint = trim((string)($params['entity_hint'] ?? ''));
    $nombre = trim((string)($params['nombre'] ?? ''));
    $telefono = trim((string)($params['telefono'] ?? ''));
    $targetScope = $params['target_scope'] ?? '';
    $newEstado = $params['estado'] ?? '';

    // ── Resolve entity if hint provided ──────────────────────────────
    $resolved = null;
    $resolvedType = '';
    if ($entityHint !== '') {
        // Try to find the entity
        $targetType = voice_detect_target_type($entityHint, $context);
        if ($targetType === '') $targetType = voice_infer_target_type_from_intent($intent);
        $scope = $targetScope !== '' ? $targetScope : voice_default_scope_for_target($targetType, $context);
        $resolved = voice_resolve_entity($entityHint, $targetType, $scope, $context);
        $resolvedType = $targetType;
    }

    // ── Map intent to redirect URL ───────────────────────────────────
    $url = '';
    $message = '';

    switch ($intent) {
        // Create intents → redirect to new form
        case 'create_interesada':
            $url = lamami_tab_url('interesadas', array('view' => 'new'));
            $message = 'Abriendo formulario de nueva interesada en LaMami.';
            break;
        case 'create_clienta':
            $url = lamami_tab_url('clientas', array('view' => 'new'));
            $message = 'Abriendo formulario de nueva clienta en LaMami.';
            break;
        case 'create_jostal_interesada':
            $url = jostal_tab_url('interesadas', array('view' => 'new'));
            $message = 'Abriendo formulario de nueva interesada en Jostal.';
            break;
        case 'create_jostal_clienta':
            $url = jostal_tab_url('clientas', array('view' => 'new'));
            $message = 'Abriendo formulario de nueva clienta en Jostal.';
            break;

        // State changes → resolve entity then redirect to edit
        case 'set_entity_estado':
        case 'set_interesada_estado':
        case 'set_casawasap_estado':
            if ($resolved) {
                $url = voice_entity_edit_url_from_result($resolved, $resolvedType);
                $message = "Abriendo ficha de " . voice_entity_label($resolved, $resolvedType) . " para cambiar estado a '$newEstado'.";
            } else {
                $url = 'index.php?page=lamami';
                $message = 'No encontré la persona. Abriendo LaMami para que la busques.';
            }
            break;

        case 'jostal_salida_casa':
        case 'jostal_reactivar_casa':
            if ($resolved && $resolvedType === 'clienta') {
                $url = voice_entity_edit_url($resolved['id'] ?? '', $resolvedType, 'jostal');
                $action = $intent === 'jostal_salida_casa' ? 'salida' : 'entrada';
                $message = "Abriendo ficha de " . voice_entity_label($resolved, $resolvedType) . " para marcar $action.";
            } else {
                $url = jostal_tab_url('clientas');
                $message = 'No encontré la inquilina. Abriendo Jostal para que la busques.';
            }
            break;

        // Payment
        case 'add_entity_pago':
        case 'add_casawasap_pago':
            if ($resolved) {
                $url = voice_entity_edit_url($resolved['id'] ?? '', $resolvedType, 'casawasap');
                $cantidad = $params['cantidad'] ?? '';
                $message = "Abriendo ficha para registrar pago" . ($cantidad !== '' ? " de {$cantidad}€" : '') . ".";
            } else {
                $url = 'index.php?page=casawasap';
                $message = 'No encontré el contacto. Abriendo Casawasap.';
            }
            break;

        // Convert
        case 'convert_entity':
        case 'convert_interesada_to_clienta':
        case 'convert_casawasap_cliente':
        case 'convert_jostal_clienta':
            if ($resolved) {
                $url = voice_entity_edit_url($resolved['id'] ?? '', $resolvedType, $targetScope);
                $message = "Abriendo ficha de " . voice_entity_label($resolved, $resolvedType) . " para convertir.";
            } else {
                $url = 'index.php?page=lamami';
                $message = 'No encontré a quien convertir. Abriendo LaMami.';
            }
            break;

        // Delete
        case 'delete_gasto_request':
            $url = 'index.php?page=gastos';
            $message = 'Abriendo gastos para que selecciones cuál borrar.';
            break;
        case 'delete_agenda_request':
            $url = 'index.php?page=josue&tab=agenda';
            $message = 'Abriendo agenda para que selecciones cuál borrar.';
            break;

        // Edit
        case 'edit_interesada':
        case 'edit_clienta':
        case 'edit_casawasap_contacto':
        case 'edit_agenda_contact':
            if ($resolved) {
                $url = voice_entity_edit_url($resolved['id'] ?? '', $resolvedType, $targetScope);
                $message = "Abriendo ficha de " . voice_entity_label($resolved, $resolvedType) . ".";
            } else {
                $url = 'index.php?page=lamami';
                $message = 'No encontré la persona. Abriendo LaMami.';
            }
            break;

        // Alta/Baja
        case 'alta_clienta':
        case 'baja_clienta':
        case 'alta_casawasap_cliente':
        case 'baja_casawasap_cliente':
            if ($resolved) {
                $url = voice_entity_edit_url($resolved['id'] ?? '', $resolvedType, $targetScope);
                $action = (strpos($intent, 'alta') !== false) ? 'alta' : 'baja';
                $message = "Abriendo ficha de " . voice_entity_label($resolved, $resolvedType) . " para dar de $action.";
            } else {
                $url = 'index.php?page=lamami';
                $message = 'No encontré la persona. Abriendo LaMami.';
            }
            break;

        // LamamiBot assets
        case 'generate_lamamibot_assets':
            $url = 'index.php?page=lamami&tab=lamamibot';
            $message = 'Abriendo LamamiBot para generar assets.';
            break;

        // Quick lead / Jostal lead/venta
        case 'create_quick_lead':
        case 'add_jostal_lead':
        case 'add_jostal_venta':
            if ($resolved) {
                $url = voice_entity_edit_url($resolved['id'] ?? '', $resolvedType, $targetScope);
                $message = "Abriendo ficha para añadir " . ($intent === 'add_jostal_venta' ? 'venta' : 'lead') . ".";
            } else {
                $pages = array('create_quick_lead' => 'lamami', 'add_jostal_lead' => 'jostal', 'add_jostal_venta' => 'jostal');
                $url = 'index.php?page=' . ($pages[$intent] ?? 'lamami');
                $message = 'Abriendo página para continuar.';
            }
            break;

        default:
            $url = 'index.php?page=dashboard';
            $message = 'Comando reconocido. Abriendo dashboard.';
    }

    return array(
        'stage' => 'executed',
        'intent' => $intent,
        'params' => $params,
        'message' => $message,
        'redirect_url' => $url,
        'execution_mode' => 'navigation',
    );
}

function voice_infer_target_type_from_intent($intent) {
    $map = array(
        'create_interesada' => 'interesada',
        'create_clienta' => 'clienta',
        'alta_clienta' => 'clienta',
        'baja_clienta' => 'clienta',
        'set_entity_estado' => 'clienta',
        'set_interesada_estado' => 'interesada',
        'convert_entity' => 'interesada',
        'convert_interesada_to_clienta' => 'interesada',
        'add_entity_pago' => 'casawasap_contacto',
        'add_casawasap_pago' => 'casawasap_contacto',
        'set_casawasap_estado' => 'casawasap_contacto',
        'jostal_salida_casa' => 'clienta',
        'jostal_reactivar_casa' => 'clienta',
        'create_jostal_interesada' => 'interesada',
        'create_jostal_clienta' => 'clienta',
        'add_jostal_lead' => 'clienta',
        'add_jostal_venta' => 'clienta',
        'convert_jostal_clienta' => 'interesada',
        'edit_agenda_contact' => 'agenda_contact',
        'delete_agenda_request' => 'agenda_contact',
    );
    return $map[$intent] ?? '';
}

function jostal_tab_url($tab, $params = array()) {
    $query = http_build_query(array_merge(array('page' => 'jostal', 'tab' => $tab), $params));
    return 'index.php?' . $query;
}


function voice_entity_edit_url_from_result($resolved, $resolvedType) {
    $id = $resolved['id'] ?? '';
    $scope = $resolvedType === 'casawasap_contacto' ? 'casawasap' : ($resolvedType === 'agenda_contact' ? 'josue' : 'lamami');
    return voice_entity_edit_url($id, $resolvedType, $scope);
}

// ════════════════════════════════════════════════════════════════════
// FASE 10 — Pensar en voz alta, diario de voz, análisis de sentimiento
// ════════════════════════════════════════════════════════════════════

function voice_execute_take_note($params) {
    $nota = trim((string)($params['nota'] ?? ''));
    if ($nota === '') {
        return array('ok' => false, 'message' => 'No has dicho qué quieres que anote.', 'errors' => array('empty_note'));
    }

    $note = array(
        'id' => generate_id('nte'),
        'nota' => $nota,
        'created_at' => now_datetime(),
        'source' => 'voice',
    );

    $notes = storage_read('voice_notes.json');
    $notes[] = $note;
    storage_write('voice_notes.json', $notes);

    // Auto-detect future dates for reminders
    $futureDate = voice_detect_future_date($nota);
    $reminderMsg = '';
    if ($futureDate) {
        $reminder = array(
            'id' => generate_id('rem'),
            'descripcion' => 'Revisar nota: ' . mb_substr($nota, 0, 60, 'UTF-8'),
            'due_at' => $futureDate,
            'context_url' => 'index.php?page=josue&tab=notas',
            'delivery' => array('in_app' => true, 'whatsapp' => false),
            'status' => 'pending', 'delivered' => false,
            'created_at' => now_datetime(), 'created_by' => 'voice_auto',
        );
        $reminders = storage_read('voice_reminders.json');
        $reminders[] = $reminder;
        storage_write('voice_reminders.json', $reminders);
        $reminderMsg = ' Además, te recordaré revisar esta nota cuando toque.';
    }

    return array(
        'ok' => true,
        'message' => 'Nota guardada: "' . mb_substr($nota, 0, 80, 'UTF-8') . '"…' . $reminderMsg,
    );
}

function voice_detect_future_date($text) {
    $text = trim((string)$text);
    // "el día 15" / "el 15 de julio"
    if (preg_match('/\bel\s+(d[ií]a\s+)?(\d{1,2})(\s+de\s+(\w+))?\b/iu', $text, $m)) {
        $day = (int)$m[2];
        $monthName = mb_strtolower($m[4] ?? '');
        $now = new DateTime('now', new DateTimeZone('Europe/Madrid'));
        $month = (int)$now->format('n');
        $year = (int)$now->format('Y');

        $monthsES = array('enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6,
            'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12);
        if ($monthName !== '' && isset($monthsES[$monthName])) {
            $month = $monthsES[$monthName];
        }

        $dueAt = DateTime::createFromFormat('Y-m-d H:i:s', "$year-$month-$day 09:00:00", new DateTimeZone('Europe/Madrid'));
        if ($dueAt && $dueAt > $now) {
            return $dueAt->format('Y-m-d H:i:s');
        }
    }
    // "la semana que viene" / "el lunes que viene"
    if (preg_match('/\b(la semana que viene|el lunes que viene|el próximo (lunes|martes|mi[ée]rcoles|jueves|viernes))\b/iu', $text)) {
        $now = new DateTime('now', new DateTimeZone('Europe/Madrid'));
        $now->modify('+7 days');
        return $now->format('Y-m-d 09:00:00');
    }
    return null;
}

function voice_execute_think_out_loud($params, $context) {
    $question = trim((string)($params['question'] ?? ''));
    if ($question === '') {
        return array('ok' => false, 'message' => 'No he entendido la pregunta.', 'errors' => array('empty_question'));
    }

    // Get CRM data for context
    $crmSummary = voice_tool_get_crm_summary();
    $cfg = voice_ai_config();
    if (!$cfg['configured']) {
        return array('ok' => true, 'message' => "Sin mi conexión de IA no puedo analizar en profundidad, pero aquí tienes los datos:\n\n$crmSummary");
    }

    $name = voice_assistant_name();
    $prompt = "Eres $name, asistente de Josué. Te ha pedido opinión o consejo sobre esto:\n\n\"$question\"\n\nDatos actuales del CRM:\n$crmSummary\n\nResponde en español natural, breve y práctico. Si puedes dar una recomendación basada en los datos del CRM, hazlo. Si no tienes suficientes datos, sé honesto pero intenta ayudar. Máximo 3 frases.";

    $payload = array(
        'model' => $cfg['model'],
        'temperature' => 0.7,
        'messages' => array(
            array('role' => 'system', 'content' => $prompt),
            array('role' => 'user', 'content' => $question),
        ),
    );

    $ch = curl_init($cfg['chat_url']);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $cfg['api_key'],
            'Content-Type: application/json',
        ),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
    ));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !is_string($response) || $response === '') {
        return array('ok' => true, 'message' => "No puedo analizar ahora mismo, pero aquí van los datos:\n\n$crmSummary");
    }

    $decoded = json_decode($response, true);
    $content = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
    if ($content === '') {
        return array('ok' => true, 'message' => "Datos del CRM:\n\n$crmSummary");
    }

    return array('ok' => true, 'message' => $content);
}

function voice_detect_sentiment($text) {
    $text = mb_strtolower(trim((string)$text), 'UTF-8');
    $positive = array('genial', 'perfecto', 'bien', 'feliz', 'contento', 'contenta', 'bueno', 'buena',
        'fantástico', 'increíble', 'gracias', 'me gusta', 'me encanta', 'vamos', 'adelante', 'sí');
    $negative = array('mal', 'fatal', 'horrible', 'triste', 'cabreado', 'hasta los cojones', 'harto',
        'no puedo más', 'estresado', 'preocupado', 'jodido', 'puto', 'mierda', 'asco',
        'qué asco', 'no me gusta', 'odio', 'cansado', 'agotado');

    $posCount = 0;
    $negCount = 0;
    foreach ($positive as $w) { if (mb_stripos($text, $w) !== false) $posCount++; }
    foreach ($negative as $w) { if (mb_stripos($text, $w) !== false) $negCount++; }

    if ($negCount > $posCount) return 'negative';
    if ($posCount > $negCount) return 'positive';
    return 'neutral';
}

function voice_detect_sentiment_note($text) {
    $sentiment = voice_detect_sentiment($text);
    if ($sentiment === 'positive') return ' (ánimo positivo 👍)';
    if ($sentiment === 'negative') return ' (ánimo bajo 😕)';
    return '';
}

// ════════════════════════════════════════════════════════════════════
// FASE 11 — Resumen diario, historial, búsqueda universal, undo, multi-idioma
// ════════════════════════════════════════════════════════════════════

function voice_execute_daily_briefing($context) {
    $crmSummary = voice_tool_get_crm_summary();
    $name = voice_assistant_name();
    $hora = (int)date('G');
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 20 ? 'Buenas tardes' : 'Buenas noches');

    $cfg = voice_ai_config();
    if (!$cfg['configured']) {
        return array('ok' => true, 'message' => "$saludo Josué. Aquí va el resumen:\n\n$crmSummary");
    }

    $prompt = "$saludo Josué. Eres $name. Genera un resumen diario breve y natural con estos datos del CRM. Sé práctica, directa, y menciona solo lo importante. Máximo 5 frases.\n\n$crmSummary";
    $payload = array(
        'model' => $cfg['model'], 'temperature' => 0.5,
        'messages' => array(
            array('role' => 'system', 'content' => $prompt),
            array('role' => 'user', 'content' => 'Dame el resumen del día.'),
        ),
    );

    $ch = curl_init($cfg['chat_url']);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $cfg['api_key'], 'Content-Type: application/json'),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5,
    ));
    $response = curl_exec($ch);
    curl_close($ch);

    $content = '';
    if ($response) {
        $decoded = json_decode($response, true);
        $content = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
    }
    if ($content === '') $content = "$saludo Josué.\n\n$crmSummary";

    return array('ok' => true, 'message' => $content);
}

function voice_execute_search_all($params) {
    $query = trim((string)($params['query'] ?? ''));
    if ($query === '') return array('ok' => false, 'message' => 'No has dicho qué buscar.', 'errors' => array('empty_query'));

    $datasets = array(
        'LaMami clientas' => 'clientes.json',
        'LaMami interesadas' => 'interesadas.json',
        'Casawasap contactos' => 'casawasap_contactos.json',
        'Jostal clientas' => 'jostal_clientas.json',
        'Jostal interesadas' => 'jostal_interesadas.json',
        'Agenda' => 'agenda.json',
        'Bots' => 'bots.json',
    );

    $results = array();
    $queryNorm = voice_normalize_text($query);

    foreach ($datasets as $label => $file) {
        $rows = storage_read($file);
        foreach ((array)$rows as $row) {
            $nameField = $row['nombre'] ?? $row['name'] ?? $row['user'] ?? '';
            $phone = $row['telefono'] ?? $row['tfono'] ?? $row['phone'] ?? '';
            $searchText = voice_normalize_text($nameField . ' ' . $phone);
            if ($searchText !== '' && mb_stripos($searchText, $queryNorm) !== false) {
                $results[] = array(
                    'label' => $label,
                    'name' => trim((string)$nameField),
                    'phone' => trim((string)$phone),
                    'id' => $row['id'] ?? '',
                );
            }
        }
    }

    if (empty($results)) {
        return array('ok' => true, 'message' => "No encontré '$query' en ningún sitio. Prueba con otro nombre o teléfono.");
    }

    $total = count($results);
    $msg = "Encontré $total resultado" . ($total > 1 ? 's' : '') . " para '$query':\n\n";
    foreach (array_slice($results, 0, 8) as $r) {
        $msg .= "• {$r['label']}: {$r['name']}" . ($r['phone'] !== '' ? " ({$r['phone']})" : '') . "\n";
    }
    if ($total > 8) $msg .= "\n... y " . ($total - 8) . " más.";

    return array('ok' => true, 'message' => $msg);
}

function voice_command_history_file() { return 'voice_command_history.json'; }

function voice_push_command_history($intent, $params, $redirectUrl) {
    $entry = array(
        'intent' => $intent,
        'params' => $params,
        'redirect_url' => $redirectUrl,
        'executed_at' => now_datetime(),
    );
    $history = storage_read(voice_command_history_file());
    $history[] = $entry;
    if (count($history) > 10) $history = array_slice($history, -10);
    storage_write(voice_command_history_file(), $history);
}

function voice_execute_repeat_last() {
    $history = storage_read(voice_command_history_file());
    if (empty($history)) {
        return array('ok' => false, 'message' => 'No hay comandos anteriores para repetir.', 'errors' => array('empty_history'));
    }
    $last = end($history);
    $intent = $last['intent'] ?? '';
    $url = $last['redirect_url'] ?? '';

    return array(
        'ok' => true,
        'message' => "Repitiendo último comando: $intent.",
        'redirect_url' => $url !== '' ? $url : 'index.php?page=dashboard',
        'execution_mode' => 'navigation',
    );
}

function voice_execute_undo() {
    $history = storage_read(voice_command_history_file());
    if (empty($history)) {
        return array('ok' => false, 'message' => 'No hay nada que deshacer.', 'errors' => array('empty_history'));
    }
    // Pop the last command
    array_pop($history);
    storage_write(voice_command_history_file(), $history);

    $prev = !empty($history) ? end($history) : null;
    $prevUrl = $prev['redirect_url'] ?? 'index.php?page=dashboard';

    return array(
        'ok' => true,
        'message' => 'Último comando deshecho. Volviendo al anterior.',
        'redirect_url' => $prevUrl,
        'execution_mode' => 'navigation',
    );
}

// Integrate command history tracking into voice_handle_command's log area
// This is called after each command execution
function voice_track_command_history($response) {
    $intent = $response['intent'] ?? '';
    $params = $response['params'] ?? array();
    $url = $response['redirect_url'] ?? '';
    if ($intent !== '' && $intent !== 'unsupported_command' && $intent !== 'conversation') {
        voice_push_command_history($intent, $params, $url);
    }
}

function voice_detect_language($text) {
    $text = trim((string)$text);
    // Simple heuristic: count English vs Spanish words
    $englishWords = array('the', 'is', 'are', 'was', 'were', 'will', 'can', 'could', 'would', 'should',
        'have', 'has', 'had', 'do', 'does', 'did', 'what', 'when', 'where', 'who', 'why', 'how',
        'please', 'thanks', 'hello', 'good', 'morning', 'night', 'open', 'show', 'create', 'search');
    $spanishWords = array('el', 'la', 'los', 'las', 'es', 'son', 'fue', 'será', 'puede', 'podría',
        'tiene', 'ha', 'había', 'hace', 'qué', 'cuándo', 'dónde', 'quién', 'por qué', 'cómo',
        'por favor', 'gracias', 'hola', 'buenos', 'días', 'noches', 'abre', 'muestra', 'crea', 'busca');

    $enScore = 0; $esScore = 0;
    $lower = mb_strtolower($text, 'UTF-8');
    foreach ($englishWords as $w) { if (mb_stripos($lower, $w) !== false) $enScore++; }
    foreach ($spanishWords as $w) { if (mb_stripos($lower, $w) !== false) $esScore++; }

    if ($enScore > $esScore && $enScore >= 2) return 'en';
    return 'es';
}

// ════════════════════════════════════════════════════════════════════
// FASE 12 — Modo inversor, entrenador de ventas, gamificación
// ════════════════════════════════════════════════════════════════════

function voice_execute_investor_report($params) {
    $period = $params['period'] ?? array('from' => date('Y-m-01'), 'to' => date('Y-m-d'));

    $summary = voice_tool_get_crm_summary();

    // Get monthly history for the last 3 months
    $monthlyData = array();
    for ($i = 2; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i month"));
        $monthlyData[$month] = voice_get_month_summary($month);
    }

    $monthlyStr = '';
    foreach ($monthlyData as $m => $data) {
        $mesesES = array('01' => 'Enero', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun',
            '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic');
        $mesLabel = $mesesES[substr($m, 5, 2)] ?? $m;
        $ingresos = number_format($data['ingresos'] ?? 0, 2);
        $gastos = number_format($data['gastos'] ?? 0, 2);
        $monthlyStr .= "$mesLabel: +{$ingresos}€ ingresos, -{$gastos}€ gastos\n";
    }

    $cfg = voice_ai_config();
    if (!$cfg['configured']) {
        return array('ok' => true, 'message' => "📊 INFORME FINANCIERO\n\nActual:\n$summary\n\nÚltimos 3 meses:\n$monthlyStr");
    }

    $name = voice_assistant_name();
    $prompt = "Eres $name. Genera un INFORME PARA INVERSORES profesional pero cercano, basado en estos datos reales del CRM de Josué. Incluye: resumen ejecutivo, KPIs clave, tendencia mensual (últimos 3 meses), y una recomendación de inversión. Sé conciso pero convincente. Usa formato de informe.\n\nDatos actuales:\n$summary\n\nHistórico 3 meses:\n$monthlyStr";

    $payload = array(
        'model' => $cfg['model'], 'temperature' => 0.4,
        'messages' => array(
            array('role' => 'system', 'content' => $prompt),
            array('role' => 'user', 'content' => 'Genera el informe para inversores.'),
        ),
    );

    $ch = curl_init($cfg['chat_url']);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $cfg['api_key'], 'Content-Type: application/json'),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 5,
    ));
    $response = curl_exec($ch);
    curl_close($ch);

    $content = '';
    if ($response) {
        $decoded = json_decode($response, true);
        $content = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
    }
    if ($content === '') $content = "📊 INFORME FINANCIERO\n\n$summary\n\n$monthlyStr";

    return array('ok' => true, 'message' => $content);
}

function voice_get_month_summary($month) {
    $leads = storage_read('leads.json');
    $casawasapPagos = storage_read('casawasap_pagos.json');
    $jostalVentas = storage_read('jostal_ventas.json');
    $gastos = storage_read('gastos.json');

    $ingresos = 0;
    $gastosTotal = 0;

    foreach ((array)$leads as $l) {
        if (substr((string)($l['created_at'] ?? ''), 0, 7) === $month) {
            $ingresos += (float)($l['precio'] ?? 0);
        }
    }
    foreach ((array)$casawasapPagos as $p) {
        if (substr((string)($p['created_at'] ?? ''), 0, 7) === $month) {
            $ingresos += (float)($p['cantidad'] ?? 0);
        }
    }
    foreach ((array)$jostalVentas as $v) {
        if (substr((string)($v['created_at'] ?? ''), 0, 7) === $month) {
            $ingresos += (float)($v['cantidad'] ?? $v['precio'] ?? 0);
        }
    }
    foreach ((array)$gastos as $g) {
        if (substr((string)($g['created_at'] ?? ''), 0, 7) === $month) {
            $gastosTotal += (float)($g['cantidad'] ?? 0);
        }
    }

    return array('ingresos' => $ingresos, 'gastos' => $gastosTotal);
}

function voice_execute_sales_coach($params) {
    // Analyze recent leads and conversion rates
    $interesadas = storage_read('interesadas.json');
    $clientas = storage_read('clientes.json');
    $jostalInteresadas = storage_read('jostal_interesadas.json');
    $jostalClientas = storage_read('jostal_clientas.json');

    $lamamiTotal = count((array)$interesadas);
    $lamamiConverted = 0;
    foreach ((array)$interesadas as $i) {
        if (($i['estado'] ?? '') === 'convertida') $lamamiConverted++;
    }
    $lamamiRate = $lamamiTotal > 0 ? round(($lamamiConverted / $lamamiTotal) * 100, 1) : 0;

    $jostalTotal = count((array)$jostalInteresadas);
    $jostalConverted = 0;
    foreach ((array)$jostalInteresadas as $i) {
        if (($i['estado'] ?? '') === 'convertida') $jostalConverted++;
    }
    $jostalRate = $jostalTotal > 0 ? round(($jostalConverted / $jostalTotal) * 100, 1) : 0;

    // Recent leads (last 30 days)
    $recentLeads = 0;
    $recentConverted = 0;
    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    foreach ((array)$interesadas as $i) {
        $date = substr((string)($i['created_at'] ?? ''), 0, 10);
        if ($date >= $thirtyDaysAgo) {
            $recentLeads++;
            if (($i['estado'] ?? '') === 'convertida') $recentConverted++;
        }
    }
    $recentRate = $recentLeads > 0 ? round(($recentConverted / $recentLeads) * 100, 1) : 0;

    $data = "📊 ANÁLISIS DE VENTAS\n\n" .
            "LaMami: $lamamiTotal interesadas → $lamamiConverted convertidas ({$lamamiRate}%)\n" .
            "Jostal: $jostalTotal interesadas → $jostalConverted convertidas ({$jostalRate}%)\n" .
            "Últimos 30 días: $recentLeads leads → $recentConverted convertidos ({$recentRate}%)\n";

    $cfg = voice_ai_config();
    if (!$cfg['configured']) {
        $data .= "\n💡 Consejo: si el ratio de conversión es <30%, revisa el primer mensaje de WhatsApp. Si es >50%, estás haciendo un buen trabajo.";
        return array('ok' => true, 'message' => $data);
    }

    $prompt = "Eres un entrenador de ventas experto. Analiza estos datos de conversión de leads del CRM de Josué (negocio de servicios para adultos) y dale 2-3 consejos prácticos y específicos para mejorar. Sé directo, sin tonterías. Esto es un negocio real.\n\n" . $data;

    $payload = array(
        'model' => $cfg['model'], 'temperature' => 0.6,
        'messages' => array(
            array('role' => 'system', 'content' => $prompt),
            array('role' => 'user', 'content' => 'Dame consejos para mejorar las ventas.'),
        ),
    );

    $ch = curl_init($cfg['chat_url']);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $cfg['api_key'], 'Content-Type: application/json'),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_CONNECTTIMEOUT => 5,
    ));
    $response = curl_exec($ch);
    curl_close($ch);

    $content = '';
    if ($response) {
        $decoded = json_decode($response, true);
        $content = trim((string)($decoded['choices'][0]['message']['content'] ?? ''));
    }
    if ($content === '') $content = $data;

    return array('ok' => true, 'message' => $content);
}

function voice_check_milestones() {
    $data = voice_memory_read();
    $total = $data['stats']['total_conversations'] ?? 0;

    $milestones = array(
        1 => '🎉 ¡Primera conversación! Bienvenido al futuro.',
        10 => '🔥 10 conversaciones. Esto ya va en serio.',
        50 => '💯 50 conversaciones. Medio centenar. ¡Máquina!',
        100 => '🏆 100 conversaciones. Eres un power user.',
        500 => '👑 500 conversaciones. Ya eres leyenda.',
    );

    foreach ($milestones as $threshold => $msg) {
        if ($total === $threshold) return $msg;
    }

    return null;
}

function voice_check_record_day() {
    // Check if today is the best day ever
    $today = date('Y-m-d');
    $leads = storage_read('leads.json');
    $pagos = storage_read('casawasap_pagos.json');

    $todayTotal = 0;
    foreach ((array)$leads as $l) {
        if (substr((string)($l['created_at'] ?? ''), 0, 10) === $today) {
            $todayTotal += (float)($l['precio'] ?? 0);
        }
    }
    foreach ((array)$pagos as $p) {
        if (substr((string)($p['created_at'] ?? ''), 0, 10) === $today) {
            $todayTotal += (float)($p['cantidad'] ?? 0);
        }
    }

    // Get historical daily max
    $dailyTotals = array();
    $allData = array_merge((array)$leads, (array)$pagos);
    foreach ($allData as $item) {
        $day = substr((string)($item['created_at'] ?? ''), 0, 10);
        if ($day !== '' && $day !== $today) {
            if (!isset($dailyTotals[$day])) $dailyTotals[$day] = 0;
            $dailyTotals[$day] += (float)($item['precio'] ?? $item['cantidad'] ?? 0);
        }
    }
    $historicalMax = !empty($dailyTotals) ? max($dailyTotals) : 0;

    if ($todayTotal > $historicalMax && $historicalMax > 0 && $todayTotal > 50) {
        $prevRecord = number_format($historicalMax, 2);
        $todayFormatted = number_format($todayTotal, 2);
        return "🏆 ¡RÉCORD HISTÓRICO! Hoy has facturado {$todayFormatted}€, superando tu anterior récord de {$prevRecord}€. ¡A celebrar!";
    }

    return null;
}

// ════════════════════════════════════════════════════════════════════
// AUTOTUBE + YOUTUBE — Execute functions
// ════════════════════════════════════════════════════════════════════

// ── AutoTube ──────────────────────────────────────────────────────────

function voice_execute_autotube_stats() {
    $summary = autotube_format_summary();
    if ($summary === null) {
        return array('ok' => false, 'message' => 'No puedo acceder a AutoTube ahora mismo. ¿Está el servicio corriendo?', 'errors' => array('autotube_unavailable'));
    }
    return array('ok' => true, 'message' => "📊 AUTO TUBE\n\n" . $summary);
}

function voice_execute_autotube_revenue() {
    $data = autotube_get_summary();
    if (!$data['ok']) return array('ok' => false, 'message' => 'AutoTube no está accesible.', 'errors' => array('unavailable'));
    $totales = $data['totales'] ?? array();
    $revenue = number_format((float)($totales['revenue'] ?? 0), 2);
    $msg = "💰 Ingresos estimados (último mes): \${$revenue}\n";
    foreach ($data['canales'] as $c) {
        $name = $c['name'] ?? $c['slug'];
        $rev = number_format((float)($c['revenue'] ?? 0), 2);
        $msg .= "- {$name}: \${$rev}\n";
    }
    $msg .= "\nCoste operativo: ~\$11/mes";
    return array('ok' => true, 'message' => $msg);
}

function voice_execute_autotube_ypp() {
    $data = autotube_get_summary();
    if (!$data['ok']) return array('ok' => false, 'message' => 'AutoTube no está accesible.', 'errors' => array('unavailable'));
    $msg = "📈 PROGRESO MONETIZACIÓN\n\nObjetivo: 1,000 subs + 4,000h\n\n";
    foreach ($data['canales'] as $c) {
        $name = $c['name'] ?? $c['slug'];
        $ov = $c['ypp_overall_pct'];
        $bar = str_repeat('█', (int)($ov / 10)) . str_repeat('░', 10 - (int)($ov / 10));
        $msg .= "{$name}: [{$bar}] {$ov}%\n";
    }
    return array('ok' => true, 'message' => $msg);
}

function voice_execute_autotube_upcoming() {
    $data = autotube_get_summary();
    if (!$data['ok']) return array('ok' => false, 'message' => 'AutoTube no está accesible.', 'errors' => array('unavailable'));
    $proximos = $data['proximos'] ?? array();
    if (empty($proximos)) return array('ok' => true, 'message' => 'No hay videos programados próximamente.');
    $msg = "🎬 PRÓXIMOS VIDEOS\n\n";
    foreach ($proximos as $p) {
        $title = mb_substr((string)($p['title'] ?? 'Sin título'), 0, 60, 'UTF-8');
        $canal = $p['canal'] ?? '?';
        $due = $p['due_at'] ?? '';
        $fecha = $due !== '' ? date('d/m H:i', strtotime($due)) : 'Pendiente';
        $msg .= "• {$title}\n  Canal: {$canal} · {$fecha}\n\n";
    }
    return array('ok' => true, 'message' => $msg);
}

// ── YouTube Reproductor ───────────────────────────────────────────────

function voice_execute_youtube_news() {
    $videos = youtube_topic_channel_videos('noticias espana hoy 2026', 5);
    if (empty($videos)) return array('ok' => true, 'message' => 'No encontré noticias recientes. Prueba más tarde.');
    $cfg = voice_ai_config();
    $titles = '';
    foreach (array_slice($videos, 0, 5) as $v) $titles .= '- ' . ($v['title'] ?? '') . "\n";
    if (!$cfg['configured']) return array('ok' => true, 'message' => "📰\n\n{$titles}");
    $payload = array('model' => $cfg['model'], 'temperature' => 0.3,
        'messages' => array(
            array('role' => 'system', 'content' => "Resume estas noticias en 2-3 frases en español:\n\n{$titles}"),
            array('role' => 'user', 'content' => 'Resume.'),
        ),
    );
    $ch = curl_init($cfg['chat_url']);
    curl_setopt_array($ch, array(CURLOPT_POST => true, CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $cfg['api_key'], 'Content-Type: application/json'), CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5));
    $resp = curl_exec($ch); curl_close($ch);
    $content = '';
    if ($resp) { $dec = json_decode($resp, true); $content = trim((string)($dec['choices'][0]['message']['content'] ?? '')); }
    if ($content === '') $content = $titles;
    return array('ok' => true, 'message' => "📰 " . $content);
}

function voice_execute_youtube_suggest() {
    $history = storage_read('youtube_history.json');
    if (empty($history)) return array('ok' => true, 'message' => 'Todavía no has escuchado nada. ¡Empieza a usar el reproductor!');
    $suggestions = youtube_ai_suggest($history, 3);
    if (empty($suggestions)) return array('ok' => true, 'message' => 'No pude generar sugerencias. Abriendo reproductor...');
    $msg = "Basado en lo que has escuchado:\n\n";
    foreach ($suggestions as $i => $s) $msg .= ($i + 1) . ". {$s}\n";
    return array('ok' => true, 'message' => $msg);
}

function voice_execute_create_youtube_channel($params) {
    $concept = trim((string)($params['concept'] ?? ''));
    if ($concept === '') return array('ok' => false, 'message' => 'No has dicho de qué quieres el canal.', 'errors' => array('empty_concept'));
    $query = youtube_ai_generate_channel_query($concept);
    if ($query === '') $query = $concept;
    $channels = storage_read('youtube_channels.json');
    $channels[] = array('id' => 'ct_' . uniqid(), 'name' => $concept, 'query' => $query, 'icon' => '📺', 'type' => 'custom', 'added_at' => now_datetime());
    storage_write('youtube_channels.json', $channels);
    return array('ok' => true, 'message' => "✅ Canal '{$concept}' creado. IA optimizó búsqueda: '{$query}'.", 'redirect_url' => 'index.php?page=josue&tab=reproductor');
}

function voice_execute_youtube_control($params) {
    $action = mb_strtolower(trim((string)($params['action'] ?? '')));
    $history = storage_read('youtube_history.json');
    $lastVideo = !empty($history[0]['title']) ? $history[0]['title'] : '';
    $redirect = 'index.php?page=josue&tab=reproductor';
    if ($lastVideo !== '') $redirect .= '&play=' . urlencode($lastVideo);
    return array('ok' => true, 'message' => 'Abriendo reproductor.', 'redirect_url' => $redirect, 'execution_mode' => 'navigation');
}

// ── YouTube → Voice Memory Learning ───────────────────────────────────

function voice_memory_learn_from_youtube() {
    $history = storage_read('youtube_history.json');
    if (!is_array($history) || count($history) < 3) return;
    $recent = array_slice($history, 0, 30);
    $channels = array(); $genres = array(); $hours = array();
    $genreKeywords = array(
        'fútbol' => array('futbol', 'gol', 'liga', 'barça', 'madrid', 'champions'),
        'música' => array('musica', 'canción', 'disco', 'album', 'concierto', 'mix'),
        'política' => array('politica', 'gobierno', 'elecciones', 'congreso', 'presidente'),
        'tecnología' => array('tecnologia', 'iphone', 'android', 'programación', 'ia '),
        'humor' => array('humor', 'comedia', 'chiste', 'monólogo', 'risa'),
    );
    foreach ($recent as $v) {
        $title = mb_strtolower((string)($v['title'] ?? ''), 'UTF-8');
        $channel = mb_strtolower((string)($v['channel_name'] ?? ''), 'UTF-8');
        $ts = $v['listened_at'] ?? '';
        if ($channel !== '') $channels[$channel] = ($channels[$channel] ?? 0) + 1;
        if ($ts !== '') { $hour = (int)date('G', strtotime($ts)); $hours[$hour] = ($hours[$hour] ?? 0) + 1; }
        foreach ($genreKeywords as $genre => $words) {
            foreach ($words as $w) {
                if (mb_stripos($title, $w) !== false) { $genres[$genre] = ($genres[$genre] ?? 0) + 1; break; }
            }
        }
    }
    arsort($channels); $topChannel = !empty($channels) ? array_key_first($channels) : null;
    if ($topChannel && ($channels[$topChannel] ?? 0) >= 3) voice_memory_add_fact('preferences', "Canal YT favorito: '{$topChannel}'", 'youtube_history', 0.65);
    arsort($genres); $topGenre = !empty($genres) ? array_key_first($genres) : null;
    if ($topGenre && ($genres[$topGenre] ?? 0) >= 3) voice_memory_add_fact('preferences', "Interesado en contenido de {$topGenre}", 'youtube_history', 0.6);
}

// ════════════════════════════════════════════════════════════════════
// COPILOT PROACTIVO — Motor de frases y check-ins (Fase 1)
// ════════════════════════════════════════════════════════════════════

function voice_handle_proactive($trigger, $context = array()) {
    switch ($trigger) {
        case 'morning_greeting':
            return voice_build_morning_greeting();
        case 'evening_wrapup':
            return voice_build_evening_wrapup();
        case 'celebration_check':
            return voice_build_celebration();
        case 'proactive_phrase':
            $phraseId = trim((string)($context['phrase_id'] ?? ''));
            return voice_build_proactive_phrase($phraseId, $context);
        // ── Diary proactivity ──
        case 'diary_morning':
            return voice_diary_build_morning_greeting();
        case 'diary_worry_nag':
            return voice_diary_build_worry_nag();
        case 'diary_idea_remind':
            return voice_diary_build_idea_remind();
        case 'diary_decision_nudge':
            return voice_diary_build_decision_nudge();
        case 'diary_mood_check':
            return voice_diary_build_mood_check();
        case 'diary_compile_today':
            return voice_diary_build_compile_trigger();
        default:
            return json_encode(array('ok' => false, 'message' => '', 'tts_text' => ''));
    }
}

// ── Morning Greeting ──────────────────────────────────────────────────

function voice_build_morning_greeting() {
    $name = voice_assistant_name();
    $weatherText = '';
    $yesterdayIncome = '';
    $pendingClients = '';
    $dayPhrase = '';
    $recordMessage = '';

    // Weather
    $weather = voice_execute_tool_weather('Madrid');
    if (!empty($weather)) {
        $weatherText = $weather;
    }

    // Yesterday income
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $ingresos = ingresos_total_periodo($yesterday, $yesterday);
    if ($ingresos > 0) {
        $yesterdayIncome = "Ayer facturaste {$ingresos}€.";
    }

    // Pending clients
    $clientas = storage_read('clientas.json');
    $pending = 0;
    if (is_array($clientas)) {
        foreach ($clientas as $c) {
            $estado = $c['estado'] ?? '';
            if ($estado === 'en_casa') $pending++;
        }
    }
    if ($pending > 0) {
        $pendingClients = "Tienes {$pending} clientas pendientes de contacto.";
    }

    // Day of week phrase
    $diaSemana = (int)date('N'); // 1=lunes..7=domingo
    $dayPhrases = array(
        1 => 'Es lunes, arrancamos la semana con fuerza.',
        2 => 'Martes. Un día más cerca del finde.',
        3 => 'Miércoles, ecuador de la semana.',
        4 => 'Jueves, ya queda poquito.',
        5 => '¡Viernes! Último empujón.',
        6 => 'Sábado. Día de relax... ¿o de trabajar?',
        7 => 'Domingo. ¿Seguro que toca currar hoy?',
    );
    $dayPhrase = $dayPhrases[$diaSemana] ?? '';

    // Celebration check (record of the month)
    $recordCheck = voice_compute_record_of_month();
    if ($recordCheck['is_record']) {
        $recordMessage = " ¡Récord del mes! {$recordCheck['today_income']}€, tu mejor día de " . date('F') . '.';
    }

    // Build message
    $parts = array_filter(array($weatherText, $yesterdayIncome, $pendingClients, $dayPhrase));
    $message = 'Buenos días' . ($name !== 'Jefry' ? '' : '') . '. ' . implode(' ', $parts);
    if ($recordMessage) $message .= $recordMessage;

    $ttsParts = array_filter(array(
        $weatherText ? preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚñÑ0-9º°\s\.\,\-]/u', '', $weatherText) : '',
        $yesterdayIncome,
        $pendingClients,
        $recordMessage ? preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚñÑ0-9º°\s\.\,\-¡!]/u', '', $recordMessage) : '',
    ));
    $tts = 'Buenos días. ' . implode(' ', $ttsParts) . ' ¿Arrancamos?';

    return json_encode(array(
        'ok' => true,
        'message' => $message,
        'tts_text' => $tts,
        'tts_importance' => 'high',
        'celebration' => $recordCheck['is_record'] ? array(
            'type' => 'record',
            'headline' => '¡Récord del mes!',
            'detail' => "{$recordCheck['today_income']}€ hoy. Tu mejor día.",
        ) : null,
        'whiteboard' => array(
            'mode' => 'flash',
            'type' => 'html',
            'duration' => 5,
            'html' => '<div style="text-align:center;padding:20px"><span style="font-size:72px;display:block;margin-bottom:12px">☀️</span><span style="font-size:28px;color:#e2c044;display:block;margin-bottom:16px">Buenos días, Josué</span><span style="font-size:56px;display:block">❤️</span></div>',
        ),
    ), JSON_UNESCAPED_UNICODE);
}

// ── Evening Wrap-up ───────────────────────────────────────────────────

function voice_build_evening_wrapup() {
    $today = date('Y-m-d');
    $ingresosHoy = ingresos_total_periodo($today, $today);

    // GPS summary
    $positions = gps_read_positions(1);
    $kpi = gps_kpi_summary($positions, true);

    $km = round($kpi['total_km'] ?? 0, 1);
    $trips = $kpi['trips'] ?? 0;
    $duration = $kpi['total_duration_minutes'] ?? 0;
    $durationStr = $duration > 0 ? gps_fmt_duration($duration) : '';

    $parts = array();
    if ($km > 0) $parts[] = "{$km} kilómetros";
    if ($trips > 0) $parts[] = "{$trips} trayectos";
    if ($durationStr) $parts[] = $durationStr . ' al volante';
    if ($ingresosHoy > 0) $parts[] = "{$ingresosHoy}€ facturados";

    $message = "Fin del día. " . (empty($parts) ? 'Hoy ha sido un día tranquilo.' : implode(', ', $parts) . '.') . ' ¿Algo más que apuntar?';
    $tts = "Fin del día. " . (empty($parts) ? 'Hoy ha sido un día tranquilo.' : implode('. ', $parts) . '.') . ' ¿Algo más que apuntar?';

    return json_encode(array(
        'ok' => true,
        'message' => $message,
        'tts_text' => $tts,
        'tts_importance' => 'high',
        'whiteboard' => array(
            'mode' => 'flash',
            'type' => 'html',
            'duration' => 6,
            'html' => '<div style="text-align:center;padding:20px"><span style="font-size:64px;display:block;margin-bottom:12px">🌙</span><span style="font-size:26px;color:#8099b3;display:block;margin-bottom:12px">Fin del día</span><span style="font-size:18px;color:#d9e2ef;display:block">' . (!empty($parts) ? implode(' · ', $parts) : 'Día tranquilo') . '</span></div>',
        ),
    ), JSON_UNESCAPED_UNICODE);
}

// ── Celebration (record of the month) ─────────────────────────────────

function voice_build_celebration() {
    $record = voice_compute_record_of_month();
    if (!$record['is_record']) {
        return json_encode(array('ok' => true, 'message' => '', 'tts_text' => '', 'celebration' => null));
    }

    $message = "¡Récord del mes! {$record['today_income']}€ hoy. Tu mejor día de " . date('F') . ". El anterior récord era {$record['previous_best']}€.";
    $tts = "¡Récord del mes! {$record['today_income']} euros hoy. Eres un máquina.";

    return json_encode(array(
        'ok' => true,
        'message' => $message,
        'tts_text' => $tts,
        'tts_importance' => 'high',
        'celebration' => array(
            'type' => 'record',
            'headline' => '¡Récord del mes!',
            'detail' => "{$record['today_income']}€ hoy. Tu mejor día.",
            'previous_best' => "{$record['previous_best']}€ (día {$record['previous_day']})",
        ),
        'whiteboard' => array(
            'mode' => 'flash',
            'type' => 'html',
            'duration' => 5,
            'html' => '<div style="text-align:center;padding:20px"><span style="font-size:64px;display:block;margin-bottom:12px">🏆</span><span style="font-size:30px;color:#e2c044;display:block;margin-bottom:8px">¡Récord del mes!</span><span style="font-size:48px;color:#fff;display:block;font-weight:bold">' . number_format($record['today_income'], 0) . '€</span><span style="font-size:16px;color:#8099b3;display:block;margin-top:8px">Tu mejor día de ' . date('F') . '</span></div>',
        ),
    ), JSON_UNESCAPED_UNICODE);
}

function voice_compute_record_of_month() {
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $todayIncome = ingresos_total_periodo($today, $today);

    // Find best day this month (excluding today)
    $best = 0;
    $bestDay = '';
    $d = new DateTime($monthStart);
    $yesterday = new DateTime($today);
    $yesterday->modify('-1 day');
    while ($d <= $yesterday) {
        $dayStr = $d->format('Y-m-d');
        $dayIncome = ingresos_total_periodo($dayStr, $dayStr);
        if ($dayIncome > $best) {
            $best = $dayIncome;
            $bestDay = $d->format('j');
        }
        $d->modify('+1 day');
    }

    return array(
        'today_income' => $todayIncome,
        'previous_best' => $best,
        'previous_day' => $bestDay,
        'is_record' => ($todayIncome > $best && $todayIncome > 0),
    );
}

// ── Proactive Phrases Pool ────────────────────────────────────────────

function voice_get_proactive_phrase($phraseId, $data = array()) {
    $all = voice_proactive_phrase_pool();
    $pool = isset($all[$phraseId]) ? $all[$phraseId] : null;
    if (!$pool) return array('message' => '', 'tts_text' => '');

    $message = is_callable($pool['message']) ? call_user_func($pool['message'], $data) : $pool['message'];
    $tts = is_callable($pool['tts']) ? call_user_func($pool['tts'], $data) : ($pool['tts'] ?? $message);

    return array(
        'message' => $message,
        'tts_text' => $tts,
        'phrase_id' => $phraseId,
        'priority' => $pool['priority'] ?? 'medium',
    );
}

function voice_build_proactive_phrase($phraseId, $context = array()) {
    $phrase = voice_get_proactive_phrase($phraseId, $context);
    if (empty($phrase['message'])) {
        return json_encode(array('ok' => true, 'message' => '', 'tts_text' => ''));
    }
    return json_encode(array(
        'ok' => true,
        'message' => $phrase['message'],
        'tts_text' => $phrase['tts_text'],
        'tts_importance' => ($phrase['priority'] === 'high' ? 'high' : 'normal'),
        'phrase_id' => $phraseId,
    ), JSON_UNESCAPED_UNICODE);
}

// ── Pool definition (~60 frases, incluidas las ingeniosas de 1.4) ────

function voice_proactive_phrase_pool() {
    $name = voice_assistant_name();
    $hoy = date('Y-m-d');
    $diaSem = (int)date('N');
    $diaMes = (int)date('j');
    $hora = (int)date('G');

    // ── Helpers inline ──
    $diasSemana = array(1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo');
    $hoyNombre = $diasSemana[$diaSem] ?? '';

    return array(

        // ═══ 1.4 Frases ingeniosas (aleatorias, 1 por trayecto) ═══

        'fun_oldest_client' => array(
            'message' => function() {
                $c = voice_find_oldest_clienta();
                return $c ? "¿Sabías que tu clienta más antigua es {$c['nombre']}, desde " . date('F Y', strtotime($c['created'] ?? 'now')) . '?' : '';
            },
            'tts' => function() {
                $c = voice_find_oldest_clienta();
                return $c ? "¿Sabías que tu clienta más antigua es {$c['nombre']}?" : '';
            },
            'priority' => 'medium',
        ),
        'fun_total_leads' => array(
            'message' => function() {
                $l = count(storage_read('interesadas.json') ?: array());
                $c = count(storage_read('clientas.json') ?: array());
                return "Llevas {$l} leads y {$c} clientas creadas desde que empezaste. Ahí es nada.";
            },
            'tts' => "Llevas unos cuantos leads creados. ¡Cuánto has crecido!",
            'priority' => 'medium',
        ),
        'fun_trend_up' => array(
            'message' => function() {
                $thisMonth = ingresos_total_periodo(date('Y-m-01'), date('Y-m-t'));
                $lastMonth = ingresos_total_periodo(date('Y-m-01', strtotime('-1 month')), date('Y-m-t', strtotime('-1 month')));
                if ($lastMonth <= 0) return '';
                $pct = round(($thisMonth - $lastMonth) / $lastMonth * 100);
                return $pct > 0 ? "Este mes llevas un {$pct}% más de ingresos que el mes pasado a estas alturas. ¡Sigue así!" : '';
            },
            'tts' => function() {
                $thisMonth = ingresos_total_periodo(date('Y-m-01'), date('Y-m-t'));
                $lastMonth = ingresos_total_periodo(date('Y-m-01', strtotime('-1 month')), date('Y-m-t', strtotime('-1 month')));
                if ($lastMonth <= 0) return '';
                $pct = round(($thisMonth - $lastMonth) / $lastMonth * 100);
                return $pct > 0 ? "Este mes llevas un {$pct}% más de ingresos. Sigue así." : '';
            },
            'priority' => 'medium',
        ),
        'fun_goal_close' => array(
            'message' => function() {
                $obj = voice_monthly_goal();
                $ing = ingresos_total_periodo(date('Y-m-01'), date('Y-m-t'));
                $remain = $obj - $ing;
                return $remain > 0 && $remain < 500 ? "Te quedan {$remain}€ para tu objetivo del mes. ¡A por ello!" : '';
            },
            'tts' => function() {
                $obj = voice_monthly_goal();
                $ing = ingresos_total_periodo(date('Y-m-01'), date('Y-m-t'));
                $remain = $obj - $ing;
                return $remain > 0 && $remain < 500 ? "Te quedan {$remain} euros para el objetivo. Casi." : '';
            },
            'priority' => 'medium',
        ),
        'fun_motivation' => array(
            'message' => function() use ($name) {
                $frases = array(
                    "Cada lead es una oportunidad. Hoy puede ser el día.",
                    "Recuerda: el 'no' ya lo tienes. Busca el 'sí'.",
                    "{$name} confía en ti. Los números no mienten.",
                );
                return $frases[array_rand($frases)];
            },
            'priority' => 'medium',
        ),

        // ═══ GPS / Ubicación ═══

        'gps_near_home' => array(
            'message' => 'Ya casi llegas a casa. ¿Te esperan?',
            'tts' => 'Ya casi llegas a casa. ¿Te esperan?',
            'priority' => 'high',
        ),
        'gps_near_work' => array(
            'message' => 'Ya casi llegas. ¿Preparado para el día?',
            'tts' => 'Ya casi llegas. ¿Preparado para el día?',
            'priority' => 'high',
        ),
        'gps_different_route' => array(
            'message' => 'Veo que hoy vamos por otro camino. ¿Aventura o recado?',
            'tts' => 'Veo que hoy vamos por otro camino. ¿Aventura o recado?',
            'priority' => 'high',
        ),
        'gps_eta' => array(
            'message' => function($d) {
                $min = (int)($d['eta_minutes'] ?? 0);
                return $min > 0 ? "A este ritmo te quedan unos {$min} minutos." : '';
            },
            'tts' => function($d) {
                $min = (int)($d['eta_minutes'] ?? 0);
                return $min > 0 ? "Te quedan unos {$min} minutos." : '';
            },
            'priority' => 'high',
        ),
        'gps_traffic' => array(
            'message' => 'Parece que hay atasco. ¿Pongo las noticias?',
            'tts' => 'Parece que hay atasco. ¿Pongo las noticias?',
            'priority' => 'high',
        ),
        'gps_one_hour' => array(
            'message' => 'Llevas una hora al volante. ¿Paramos un momento?',
            'tts' => 'Llevas una hora al volante. ¿Paramos un momento a estirar las piernas?',
            'priority' => 'high',
        ),
        'gps_cross_city' => array(
            'message' => function($d) {
                $city = $d['city'] ?? '';
                return $city ? "Acabas de entrar en {$city}. ¿Vienes a ver a alguien?" : '';
            },
            'tts' => function($d) {
                $city = $d['city'] ?? '';
                return $city ? "Acabas de entrar en {$city}." : '';
            },
            'priority' => 'low',
        ),
        'gps_speeding' => array(
            'message' => 'Vas a buena marcha hoy. ¿Autovía?',
            'tts' => 'Vas rápido hoy.',
            'priority' => 'high',
        ),

        // ═══ Check-ins / Estado de ánimo ═══

        'mood_how_feeling' => array(
            'message' => '¿Cómo te sientes hoy? ¿Energía a tope o café urgente?',
            'tts' => '¿Cómo te sientes hoy?',
            'priority' => 'high',
        ),
        'mood_silent' => array(
            'message' => 'Te noto callado. ¿Todo bien o prefieres que no te moleste?',
            'tts' => 'Te noto callado. ¿Todo bien?',
            'priority' => 'high',
        ),
        'mood_good_day' => array(
            'message' => function() {
                $ing = ingresos_total_periodo(date('Y-m-d'), date('Y-m-d'));
                return $ing > 150 ? 'Parece que hoy ha sido un buen día. ¿Lo celebramos con música?' : '';
            },
            'tts' => 'Parece que hoy ha sido un buen día. ¿Lo celebramos con música?',
            'priority' => 'medium',
        ),
        'mood_slow_day' => array(
            'message' => function() {
                $ing = ingresos_total_periodo(date('Y-m-d'), date('Y-m-d'));
                return $ing < 50 && $ing >= 0 ? 'Hoy ha sido un día tranquilo de ventas. Mañana más y mejor.' : '';
            },
            'tts' => 'Hoy ha sido un día tranquilo. Mañana más y mejor.',
            'priority' => 'medium',
        ),
        'mood_lunch' => array(
            'message' => 'Son las ' . date('G') . '. ¿Has comido ya? No trabajes en ayunas.',
            'tts' => '¿Has comido ya? No trabajes en ayunas.',
            'priority' => 'medium',
        ),
        'mood_nightfall' => array(
            'message' => 'Ya es de noche. Conduce con cuidado.',
            'tts' => 'Ya es de noche. Conduce con cuidado.',
            'priority' => 'medium',
        ),
        'mood_streak' => array(
            'message' => function() {
                $streak = voice_count_streak_days();
                return $streak >= 3 ? "Llevas {$streak} días seguidos por encima de tu media. ¡Estás en racha!" : '';
            },
            'tts' => function() {
                $streak = voice_count_streak_days();
                return $streak >= 3 ? "Llevas {$streak} días seguidos en racha." : '';
            },
            'priority' => 'medium',
        ),
        'mood_no_expenses' => array(
            'message' => function() {
                $gastos = storage_read('gastos.json') ?: array();
                $hoy = date('Y-m-d');
                $hasGasto = false;
                foreach ($gastos as $g) {
                    if (($g['fecha'] ?? '') === $hoy) { $hasGasto = true; break; }
                }
                return !$hasGasto ? 'Hoy no has apuntado ningún gasto todavía. ¿Seguro que no te dejas nada?' : '';
            },
            'tts' => 'Hoy no has apuntado gastos. ¿Seguro que no te dejas nada?',
            'priority' => 'medium',
        ),

        // ═══ Música / Entretenimiento ═══

        'music_no_music_yet' => array(
            'message' => '¿Qué música quieres? ¿Algo tranquilo o marcha?',
            'tts' => '¿Qué música quieres?',
            'priority' => 'high',
        ),
        'music_same_style' => array(
            'message' => function($d) {
                $mins = (int)($d['minutes'] ?? 0);
                return $mins >= 30 ? "Llevamos {$mins} minutos con este estilo. ¿Seguimos o cambiamos?" : '';
            },
            'tts' => 'Llevamos un rato con este estilo. ¿Cambiamos?',
            'priority' => 'high',
        ),
        'music_repeat_song' => array(
            'message' => function($d) {
                $count = (int)($d['play_count'] ?? 0);
                return $count >= 3 ? "Esta canción la has escuchado {$count} veces hoy. ¿Tu favorita del momento?" : '';
            },
            'tts' => function($d) {
                $count = (int)($d['play_count'] ?? 0);
                return $count >= 3 ? "Esta canción la has escuchado {$count} veces hoy." : '';
            },
            'priority' => 'high',
        ),
        'music_nightfall_energetic' => array(
            'message' => 'Está anocheciendo. ¿Pongo algo más tranquilo?',
            'tts' => 'Está anocheciendo. ¿Pongo algo más tranquilo?',
            'priority' => 'medium',
        ),
        'music_weekend' => array(
            'message' => function() use ($hoyNombre, $diaSem) {
                return ($diaSem === 6 || $diaSem === 7) ? "Es {$hoyNombre}. ¿Música de finde o seguimos con lo de siempre?" : '';
            },
            'tts' => function() use ($diaSem) {
                return ($diaSem === 6 || $diaSem === 7) ? 'Es finde. ¿Música de finde?' : '';
            },
            'priority' => 'medium',
        ),
        'music_sad_song' => array(
            'message' => 'Esa era profunda. ¿Subimos el ánimo con algo más alegre?',
            'tts' => 'Esa era profunda. ¿Algo más alegre?',
            'priority' => 'low',
        ),

        // ═══ Efemérides / Agenda ═══

        'date_monday' => array(
            'message' => function() use ($diaSem) { return $diaSem === 1 ? 'Lunes. Ánimo, que la semana acaba de empezar.' : ''; },
            'tts' => 'Lunes. Ánimo.',
            'priority' => 'high',
        ),
        'date_friday' => array(
            'message' => function() use ($diaSem) { return $diaSem === 5 ? '¡Viernes! Esta noche hay que celebrarlo.' : ''; },
            'tts' => '¡Viernes! Esta noche hay que celebrarlo.',
            'priority' => 'high',
        ),
        'date_month_start' => array(
            'message' => function() use ($diaMes) { return $diaMes <= 3 ? 'Mes nuevo. ¿Algún objetivo para ' . date('F') . '?' : ''; },
            'tts' => function() use ($diaMes) { return $diaMes <= 3 ? 'Mes nuevo. ¿Algún objetivo?' : ''; },
            'priority' => 'medium',
        ),
        'date_month_end' => array(
            'message' => function() use ($diaMes) {
                $diasMes = (int)date('t');
                return $diaMes >= ($diasMes - 3) ? 'Últimos días del mes. ¿Llegamos al objetivo?' : '';
            },
            'tts' => 'Últimos días del mes. ¿Llegamos al objetivo?',
            'priority' => 'medium',
        ),
        'date_anniversary' => array(
            'message' => function() {
                $mem = voice_memory_read();
                $first = $mem['stats']['first_interaction'] ?? null;
                if (!$first) return '';
                $firstDate = substr($first, 5, 5); // MM-DD
                $todayMD = date('m-d');
                return $firstDate === $todayMD ? 'Tal día como hoy empezaste con LaMami. Cuánto has crecido.' : '';
            },
            'tts' => 'Hoy hace un año que empezaste. Cuánto has crecido.',
            'priority' => 'low',
        ),
        'date_extreme_temp' => array(
            'message' => function($d) {
                $temp = (int)($d['temp'] ?? 20);
                if ($temp >= 35) return "Hace {$temp} grados fuera. Hidrátate bien.";
                if ($temp <= 5) return "Hace {$temp} grados. Abrígate.";
                return '';
            },
            'tts' => function($d) {
                $temp = (int)($d['temp'] ?? 20);
                if ($temp >= 35) return "Hace {$temp} grados. Hidrátate.";
                if ($temp <= 5) return "Hace {$temp} grados. Abrígate.";
                return '';
            },
            'priority' => 'medium',
        ),

        // ═══ Motivación / Negocio ═══

        'biz_goal_close' => array(
            'message' => function() {
                $obj = voice_monthly_goal();
                $ing = ingresos_total_periodo(date('Y-m-01'), date('Y-m-t'));
                $pct = $obj > 0 ? round($ing / $obj * 100) : 0;
                return $pct >= 85 && $pct < 100 ? "Estás al {$pct}% de tu objetivo mensual. ¡A por ello!" : '';
            },
            'tts' => function() {
                $obj = voice_monthly_goal();
                $ing = ingresos_total_periodo(date('Y-m-01'), date('Y-m-t'));
                $pct = $obj > 0 ? round($ing / $obj * 100) : 0;
                return $pct >= 85 && $pct < 100 ? "Estás al {$pct} por ciento del objetivo." : '';
            },
            'priority' => 'medium',
        ),
        'biz_goal_surpassed' => array(
            'message' => function() {
                $obj = voice_monthly_goal();
                $ing = ingresos_total_periodo(date('Y-m-01'), date('Y-m-t'));
                return $ing >= $obj && $obj > 0 ? '¡Objetivo del mes superado! Todo lo que venga ahora es bonus.' : '';
            },
            'tts' => '¡Objetivo del mes superado! Todo bonus.',
            'priority' => 'medium',
        ),
        'biz_branch_neglected' => array(
            'message' => function() {
                $branch = voice_detect_neglected_branch();
                return $branch ? "Hace 5 días que no tocas {$branch}. ¿Todo bien por allí?" : '';
            },
            'tts' => function() {
                $branch = voice_detect_neglected_branch();
                return $branch ? "Hace días que no tocas {$branch}." : '';
            },
            'priority' => 'medium',
        ),
        'biz_best_week' => array(
            'message' => function() {
                return ''; // Too complex for MVP, placeholder
            },
            'tts' => '',
            'priority' => 'low',
        ),

        // ═══ Conversación casual / Humor ═══

        'chat_long_silence' => array(
            'message' => 'Oye, ¿y si me cuentas algo? Un cotilleo, un sueño, lo que sea.',
            'tts' => 'Oye, cuéntame algo.',
            'priority' => 'medium',
        ),
        'chat_third_interaction' => array(
            'message' => function($d) {
                $count = (int)($d['interaction_count'] ?? 0);
                return $count >= 3 ? "Tercera vez que hablamos hoy. Ya somos amigos." : '';
            },
            'tts' => 'Tercera vez que hablamos hoy. Ya somos amigos.',
            'priority' => 'medium',
        ),
        'chat_thanks' => array(
            'message' => 'De nada. Para eso estamos los copilotos.',
            'tts' => 'De nada.',
            'priority' => 'high',
        ),
        'chat_frustration' => array(
            'message' => 'Respira hondo. El tráfico no merece tu energía.',
            'tts' => 'Respira hondo. No merece la pena enfadarse.',
            'priority' => 'low',
        ),
        'chat_rain' => array(
            'message' => 'Está lloviendo. Cuidado con el suelo mojado. Y qué bonito suena en el techo.',
            'tts' => 'Está lloviendo. Conduce con cuidado.',
            'priority' => 'medium',
        ),
        'chat_new_place' => array(
            'message' => 'No te conozco este sitio. ¿Sitio nuevo para comer?',
            'tts' => 'No conozco este sitio. ¿Nuevo?',
            'priority' => 'medium',
        ),
        'chat_calm_down' => array(
            'message' => 'Con calma, que no hay prisa.',
            'tts' => 'Con calma.',
            'priority' => 'low',
        ),
        'chat_total_silence' => array(
            'message' => 'Llevamos un rato en silencio total. ¿Te pongo algo de ambiente?',
            'tts' => 'Llevamos un rato en silencio. ¿Te pongo música?',
            'priority' => 'medium',
        ),
    );
}

// ── Helper: find oldest clienta ───────────────────────────────────────

function voice_find_oldest_clienta() {
    $clientas = storage_read('clientas.json') ?: array();
    $oldest = null;
    $oldestDate = null;
    foreach ($clientas as $c) {
        $created = $c['created'] ?? $c['creado'] ?? $c['fecha_alta'] ?? '';
        if (!$created) continue;
        if ($oldestDate === null || $created < $oldestDate) {
            $oldestDate = $created;
            $oldest = $c;
        }
    }
    return $oldest ? array('nombre' => ($oldest['nombre'] ?? $oldest['name'] ?? '?'), 'created' => $oldestDate) : null;
}

// ── Helper: count streak days above average ─────────────────────────

function voice_count_streak_days() {
    $today = new DateTime();
    $streak = 0;
    $monthTotal = ingresos_total_periodo(date('Y-m-01'), date('Y-m-t'));
    $daysElapsed = (int)date('j');
    $avg = $daysElapsed > 0 ? $monthTotal / $daysElapsed : 0;
    for ($i = 0; $i < 30; $i++) {
        $d = clone $today;
        $d->modify("-{$i} days");
        $dayStr = $d->format('Y-m-d');
        $dayIncome = ingresos_total_periodo($dayStr, $dayStr);
        if ($dayIncome > $avg) $streak++;
        else break;
    }
    return $streak;
}

// ── Helper: monthly goal ─────────────────────────────────────────────

function voice_monthly_goal() {
    $settings = storage_read('settings.json');
    return (int)($settings['voice_monthly_goal'] ?? 4000);
}

// ── Helper: detect neglected branch (>5 days no activity) ───────────

function voice_detect_neglected_branch() {
    $branches = array('lamami' => 'LaMami', 'casawasap' => 'Casawasap', 'jostal' => 'Jostal');
    $hoy = date('Y-m-d');
    $cutoff = date('Y-m-d', strtotime('-5 days'));
    $ingresosRaw = storage_read('ingresos.json') ?: array();

    foreach ($branches as $key => $label) {
        $hasRecent = false;
        foreach ($ingresosRaw as $ing) {
            if (($ing['rama'] ?? $ing['branch'] ?? '') === $key && ($ing['fecha'] ?? '') >= $cutoff) {
                $hasRecent = true;
                break;
            }
        }
        if (!$hasRecent) return $label;
    }
    return null;
}

// ── Helper: execute weather tool (reuse from conversation tools) ─────

function voice_execute_tool_weather($city = 'Madrid') {
    // Self-hosted scrapers (from voice_execute_tools_from_response)
    $urls = array(
        "https://wttr.in/{$city}?format=%C+%t+%h",
        "https://wttr.in/{$city}?format=3",
    );
    foreach ($urls as $url) {
        $ctx = stream_context_create(array('http' => array('timeout' => 5)));
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw && strlen($raw) > 5 && strlen($raw) < 300) {
            $clean = trim(strip_tags($raw));
            $clean = preg_replace('/\s+/', ' ', $clean);
            if (strlen($clean) > 3) return $clean;
        }
    }
    return '';
}

// ════════════════════════════════════════════════════════════════════
// UNIFIED TOOLS — LLM-controlled copilot actions (send_whatsapp, etc.)
// ════════════════════════════════════════════════════════════════════

function voice_tool_send_whatsapp($arg) {
    $parts = explode('|', $arg, 2);
    $contactName = trim($parts[0] ?? '');
    $message = trim($parts[1] ?? '');

    if ($contactName === '' || $message === '') {
        return voice_tool_action_json('error', 'send_whatsapp', array('message' => 'Necesito un nombre y un mensaje.'));
    }

    $phone = null;
    $foundIn = '';
    $clientas = storage_read('clientas.json') ?: array();
    foreach ($clientas as $c) {
        $n = $c['nombre'] ?? $c['name'] ?? '';
        if (mb_stripos($n, $contactName) !== false) {
            $phone = $c['telefono'] ?? $c['phone'] ?? $c['tel'] ?? null;
            $foundIn = 'clientas';
            break;
        }
    }
    if (!$phone) {
        $interesadas = storage_read('interesadas.json') ?: array();
        foreach ($interesadas as $c) {
            $n = $c['nombre'] ?? $c['name'] ?? '';
            if (mb_stripos($n, $contactName) !== false) {
                $phone = $c['telefono'] ?? $c['phone'] ?? $c['tel'] ?? null;
                $foundIn = 'interesadas';
                break;
            }
        }
    }
    if (!$phone) {
        $agenda = storage_read('agenda.json') ?: array();
        foreach ($agenda as $c) {
            $n = $c['nombre'] ?? $c['name'] ?? '';
            if (mb_stripos($n, $contactName) !== false) {
                $phone = $c['telefono'] ?? $c['phone'] ?? $c['tel'] ?? null;
                $foundIn = 'agenda';
                break;
            }
        }
    }

    if (!$phone) {
        return "No encontré el teléfono de {$contactName}. ¿Está en la agenda o en las clientas?";
    }

    $phoneDigits = preg_replace('/[^0-9]/', '', $phone);

    // Intentar enviar via WAHA personal (waha3032, puerto 3031)
    $sent = false;
    $wahaError = '';
    $chatId = $phoneDigits . '@c.us';
    $wahaUrl = 'http://100.117.92.74:3031/api/sendText';

    $ch = curl_init($wahaUrl);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array('X-Api-Key: local321', 'Content-Type: application/json'),
        CURLOPT_POSTFIELDS => json_encode(array('chatId' => $chatId, 'text' => $message, 'session' => 'default'), JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ));
    $wahaResp = curl_exec($ch);
    $wahaHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($wahaHttpCode >= 200 && $wahaHttpCode < 300 && $wahaResp) {
        $sent = true;
    } else {
        $wahaError = "WAHA responded HTTP {$wahaHttpCode}";
    }

    if ($sent) {
        // Guardar en el store de WhatsApp Personal también (read-merge-write atómico)
        $storePath = __DIR__ . '/../data/personal_wasap_data.json';
        $dir = dirname($storePath);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $fh = @fopen($storePath, 'c+');
        if ($fh && flock($fh, LOCK_EX)) {
            $raw = stream_get_contents($fh);
            $store = (is_string($raw) && $raw !== '') ? @json_decode($raw, true) : null;
            if (!is_array($store)) $store = ['chats' => [], 'contacts_index' => [], 'learning' => [], 'meta' => []];
            // Asegurar estructura
            if (!isset($store['chats'])) $store['chats'] = [];
            if (!isset($store['contacts_index'])) $store['contacts_index'] = [];
            if (!isset($store['learning'])) $store['learning'] = [];
            if (!isset($store['meta'])) $store['meta'] = [];

            if (!isset($store['chats'][$chatId])) {
                $store['chats'][$chatId] = ['contact_name' => $contactName, 'contact_phone' => $phoneDigits, 'messages' => [], 'unread_count' => 0];
            }
            $store['chats'][$chatId]['contact_name'] = $contactName;
            $msgId = 'msg_voice_' . bin2hex(random_bytes(4));
            // Dedup: evitar duplicados si se llama múltiples veces
            $exists = false;
            foreach ($store['chats'][$chatId]['messages'] as $em) {
                if (($em['id'] ?? '') === $msgId) { $exists = true; break; }
            }
            if (!$exists) {
                $store['chats'][$chatId]['messages'][] = [
                    'id' => $msgId,
                    'direction' => 'out', 'from_me' => true,
                    'text' => $message, 'ts' => date('c'), 'read' => true,
                ];
            }
            $store['chats'][$chatId]['last_message_at'] = date('c');
            if (count($store['chats'][$chatId]['messages']) > 500) {
                $store['chats'][$chatId]['messages'] = array_slice($store['chats'][$chatId]['messages'], -500);
            }
            $json = json_encode($store, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                ftruncate($fh, 0);
                rewind($fh);
                fwrite($fh, $json);
                fflush($fh);
            }
            flock($fh, LOCK_UN);
            fclose($fh);
        } else {
            if ($fh) fclose($fh);
        }
        return voice_tool_action_json('executed', 'send_whatsapp', array(
            'message' => "✅ WhatsApp enviado a {$contactName} ({$phone}): \"{$message}\"",
            'phone' => $phoneDigits,
        ));
    }

    // Fallback: generar link wa.me
    $waUrl = 'https://wa.me/' . $phoneDigits . '?text=' . urlencode($message);
    return voice_tool_action_json('executed', 'send_whatsapp', array(
        'message' => "No pude enviar por WAHA ({$wahaError}), pero aquí tienes el link: {$waUrl}",
        'phone' => $phoneDigits,
        'whatsapp_url' => $waUrl,
    ));
}

/**
 * Lee los mensajes de WhatsApp no leídos y los devuelve como texto para TTS.
 * Uso: TOOL:read_whatsapp
 */
function voice_tool_read_whatsapp($arg) {
    $storePath = __DIR__ . '/../data/personal_wasap_data.json';
    $dir = dirname($storePath);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $unreadMessages = array();

    $fh = @fopen($storePath, 'c+');
    if ($fh && flock($fh, LOCK_EX)) {
        $raw = stream_get_contents($fh);
        $store = (is_string($raw) && $raw !== '') ? @json_decode($raw, true) : null;
        if (!is_array($store)) $store = ['chats' => [], 'contacts_index' => [], 'learning' => [], 'meta' => []];

        if (empty($store['chats'])) {
            flock($fh, LOCK_UN);
            fclose($fh);
            return "No tienes conversaciones de WhatsApp todavía.";
        }

        foreach ($store['chats'] as $chatId => $chat) {
            $name = (!empty($chat['contact_name']) ? $chat['contact_name'] : ($chat['contact_phone'] ?? 'Desconocido'));
            foreach ($chat['messages'] as $msg) {
                if (!($msg['read'] ?? false) && ($msg['direction'] ?? '') === 'in') {
                    $unreadMessages[] = array('from' => $name, 'text' => $msg['text'] ?? '', 'ts' => $msg['ts'] ?? '');
                }
            }
        }

        if (empty($unreadMessages)) {
            flock($fh, LOCK_UN);
            fclose($fh);
            return "No tienes mensajes nuevos de WhatsApp.";
        }

        // Marcar como leídos
        foreach ($store['chats'] as $chatId => &$chat) {
            foreach ($chat['messages'] as &$msg) {
                if (!($msg['read'] ?? false) && ($msg['direction'] ?? '') === 'in') {
                    $msg['read'] = true;
                }
            }
            $chat['unread_count'] = 0;
        }
        unset($chat, $msg);

        $json = json_encode($store, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, $json);
            fflush($fh);
        }
        flock($fh, LOCK_UN);
        fclose($fh);
    } else {
        if ($fh) fclose($fh);
        return "Aún no hay mensajes de WhatsApp. La sección WhatsApp Personal no se ha configurado.";
    }

    $lines = array();
    $lines[] = "Tienes " . count($unreadMessages) . " mensaje(s) nuevo(s) de WhatsApp:";
    foreach ($unreadMessages as $idx => $m) {
        $short = mb_strlen($m['text']) > 180 ? mb_substr($m['text'], 0, 177) . '...' : $m['text'];
        $lines[] = ($idx + 1) . ". De {$m['from']}: \"{$short}\"";
    }

    return implode("\n", $lines) . "\n\n(He marcado todos como leídos. Si quieres responder a alguno, dime: 'contesta a [nombre] diciendo [mensaje]')";
}

/**
 * Responde a un contacto de WhatsApp.
 * Uso: TOOL:reply_whatsapp|nombre_contacto|mensaje
 */
function voice_tool_reply_whatsapp($arg) {
    $parts = explode('|', $arg, 2);
    $contactName = trim($parts[0] ?? '');
    $message = trim($parts[1] ?? '');

    if ($contactName === '' || $message === '') {
        return voice_tool_action_json('error', 'reply_whatsapp', array('message' => 'Necesito un nombre de contacto y el mensaje.'));
    }

    // Reutilizar send_whatsapp con el mismo formato
    return voice_tool_send_whatsapp("{$contactName}|{$message}");
}

/**
 * Pipeline de aprendizaje desde WhatsApp.
 * Procesa mensajes pendientes de clasificación y los integra en el diario y user model.
 * Se llama periódicamente (vía cron o al interactuar con Jefry).
 */
function wasap_learning_process_pending() {
    $storePath = __DIR__ . '/../data/personal_wasap_data.json';
    $dir = dirname($storePath);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    // Read-merge-write atómico con LOCK_EX
    $fh = @fopen($storePath, 'c+');
    if (!$fh) return;
    if (!flock($fh, LOCK_EX)) {
        fclose($fh);
        return;
    }

    $raw = stream_get_contents($fh);
    $store = (is_string($raw) && $raw !== '') ? @json_decode($raw, true) : null;
    if (!is_array($store)) {
        flock($fh, LOCK_UN);
        fclose($fh);
        return;
    }

    $pending = $store['learning']['pending_classification'] ?? array();
    $remaining = array();
    $processed = 0;

    foreach ($pending as $item) {
        $text = trim((string)($item['text'] ?? ''));
        if ($text === '' || mb_strlen($text) < 15) continue;

        // Clasificar con el pipeline del diario (misma función)
        $classification = voice_diary_classify($text);
        if ($classification === null) {
            $remaining[] = $item;
            continue;
        }

        $tipo = $classification['tipo'];
        $confidence = $classification['confidence'];

        if ($confidence < 0.7) {
            $remaining[] = $item;
            continue;
        }

        // Extraer datos estructurados
        $extracted = voice_diary_extract($text, $tipo);
        if ($extracted === null) {
            $extracted = array(
                'tipo' => $tipo,
                'raw_text' => $text,
                'clean_text' => $text,
                'mood' => 'neutro',
                'tags' => array('whatsapp'),
                'confidence' => $confidence,
            );
        } else {
            $extracted['confidence'] = $confidence;
            if (!isset($extracted['tags'])) $extracted['tags'] = array();
            $extracted['tags'][] = 'whatsapp';
        }

        // Añadir al buffer del diario
        voice_diary_buffer_add($extracted);

        // Si alta confianza, actualizar user model inmediatamente
        if ($confidence > 0.85) {
            voice_user_model_update_hot($extracted);
        }

        // Si es un commitment (compromiso), guardar en important_dates
        if ($tipo === 'decision' && $confidence > 0.75) {
            voice_memory_add_fact('important_dates', $text, 'whatsapp', 0.7);
        }

        $processed++;
    }

    $store['learning']['pending_classification'] = $remaining;
    $json = json_encode($store, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json !== false) {
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, $json);
        fflush($fh);
    }

    flock($fh, LOCK_UN);
    fclose($fh);

    if ($processed > 0) {
        // También chequeamos si hay suficiente material para compilar el diario
        $buffer = voice_diary_buffer_get_today();
        if (count($buffer) >= 2) {
            voice_diary_compile_daily();
        }
    }
}

function voice_tool_set_mode($arg) {
    $parts = explode('|', $arg, 2);
    $mode = trim($parts[0] ?? '');
    $value = trim($parts[1] ?? '');

    $validModes = array('silent', 'accompanied', 'proactive');
    if ($mode === '' || !in_array($mode, $validModes)) {
        return voice_tool_action_json('error', 'set_mode', array('message' => 'Modo no válido.'));
    }

    $boolValue = ($value === 'true' || $value === '1' || $value === 'on' || $value === 'activado' || $value === 'sí');
    $modeLabels = array('silent' => 'Modo silencio', 'accompanied' => 'Modo acompañado', 'proactive' => 'Modo proactivo');

    return voice_tool_action_json('executed', 'set_mode', array(
        'mode' => $mode,
        'value' => $boolValue,
        'message' => $modeLabels[$mode] . ': ' . ($boolValue ? 'activado' : 'desactivado') . '.',
    ));
}

function voice_tool_search_contact($arg) {
    $name = trim($arg);
    if ($name === '') return 'Dime el nombre de la persona que buscas.';

    $results = array();
    $sources = array(
        'clientas' => storage_read('clientas.json') ?: array(),
        'interesadas' => storage_read('interesadas.json') ?: array(),
        'agenda' => storage_read('agenda.json') ?: array(),
    );

    foreach ($sources as $sourceName => $list) {
        foreach ((array)$list as $c) {
            $n = $c['nombre'] ?? $c['name'] ?? '';
            if (mb_stripos($n, $name) !== false) {
                $phone = $c['telefono'] ?? $c['phone'] ?? $c['tel'] ?? 'Sin teléfono';
                $estado = $c['estado'] ?? $c['status'] ?? '';
                $lastContact = $c['ultimo_contacto'] ?? $c['last_contact'] ?? '';
                $results[] = "{$n} ({$sourceName}) · 📞 {$phone}" . ($estado ? " · {$estado}" : '') . ($lastContact ? ' · último: ' . substr($lastContact, 0, 10) : '');
            }
        }
    }

    if (empty($results)) return "No encontré a nadie llamado '{$name}'.";
    return "🔍 Resultados para '{$name}':\n" . implode("\n", $results);
}

function voice_tool_play_music($arg) {
    $mood = trim($arg);
    if ($mood === '') $mood = 'default';
    return voice_tool_action_json('executed', 'play_music', array(
        'message' => 'Poniendo música' . ($mood !== 'default' ? " ({$mood})" : '') . '.',
        'mood' => $mood,
    ));
}

function voice_tool_parking($arg) {
    $action = strtolower(trim($arg));
    if (!in_array($action, array('save', 'recall'))) {
        return voice_tool_action_json('error', 'parking', array('message' => 'Usa "save" o "recall".'));
    }
    return voice_tool_action_json('executed', 'parking', array(
        'action' => $action,
        'message' => ($action === 'save' ? 'Guardando posición de parking.' : 'Buscando dónde aparcaste.'),
    ));
}

function voice_tool_voice_control($arg) {
    $parts = explode('|', $arg, 2);
    $action = strtolower(trim($parts[0] ?? ''));
    $value = trim($parts[1] ?? '');

    $validActions = array('pause_music', 'resume_music', 'skip_song', 'volume', 'mute', 'unmute');
    if (!in_array($action, $validActions)) {
        return voice_tool_action_json('error', 'voice_control', array('message' => 'Acción no válida.'));
    }
    return voice_tool_action_json('executed', 'voice_control', array(
        'action' => $action,
        'value' => $value,
        'message' => ucfirst(str_replace('_', ' ', $action)) . ($value !== '' ? " {$value}" : '') . '.',
    ));
}

// ═══════════════════════════════════════════════════════════════════
// WHITEBOARD TOOL & CHART GENERATION
// ═══════════════════════════════════════════════════════════════════

function voice_tool_whiteboard($arg) {
    $parts = explode('|', $arg, 4);
    $mode = trim($parts[0] ?? 'modal');
    $type = trim($parts[1] ?? 'text');
    $content = trim($parts[2] ?? '');
    $duration = isset($parts[3]) ? intval(trim($parts[3])) : ($mode === 'flash' ? 5 : 0);

    if (!in_array($mode, array('flash', 'modal'), true)) {
        $mode = 'modal';
    }
    if (!in_array($type, array('chart', 'image', 'html', 'text'), true)) {
        $type = 'text';
    }
    if ($mode === 'flash' && $duration <= 0) {
        $duration = 5;
    }

    $wb = array('mode' => $mode, 'type' => $type, 'duration' => $duration);

    switch ($type) {
        case 'chart':
            $chart = voice_whiteboard_generate_chart($content);
            $wb['chart'] = $chart;
            $chartTitle = is_array($chart) && isset($chart['title']) ? $chart['title'] : 'gráfico';
            return json_encode(array(
                'message' => "Gráfico «{$chartTitle}» generado.",
                'whiteboard' => $wb,
            ), JSON_UNESCAPED_UNICODE);

        case 'image':
            $imageUrl = 'https://image.pollinations.ai/prompt/' . urlencode($content);
            $wb['src'] = $imageUrl;
            $wb['alt'] = $content;
            return json_encode(array(
                'message' => 'Imagen generada para la pizarra.',
                'whiteboard' => $wb,
            ), JSON_UNESCAPED_UNICODE);

        case 'html':
            // Sanitize: allow safe tags, strip scripts/iframes
            $safeTags = '<div><span><p><h1><h2><h3><h4><h5><h6><strong><em><b><i><u><br><hr>'
                . '<img><ul><ol><li><a><table><thead><tbody><tr><td><th><style>'
                . '<svg><path><circle><rect><line><polyline><polygon><text>';
            $safe = strip_tags($content, $safeTags);
            // Remove on* attributes and javascript: URLs
            $safe = preg_replace('/\s+on\w+\s*=\s*"[^"]*"/i', '', $safe);
            $safe = preg_replace('/\s+on\w+\s*=\s*\'[^\']*\'/i', '', $safe);
            $safe = preg_replace('/href\s*=\s*"javascript:[^"]*"/i', 'href="#"', $safe);
            $wb['html'] = $safe;
            return json_encode(array(
                'message' => 'Contenido visual preparado.',
                'whiteboard' => $wb,
            ), JSON_UNESCAPED_UNICODE);

        case 'text':
        default:
            $wb['text'] = $content;
            return json_encode(array(
                'message' => 'Texto preparado para la pizarra.',
                'whiteboard' => $wb,
            ), JSON_UNESCAPED_UNICODE);
    }
}

function voice_whiteboard_generate_chart($description) {
    $description = mb_strtolower(trim((string)$description), 'UTF-8');

    // Ventas por rama / por negocio
    if (preg_match('/rama|negocio|compar/i', $description)) {
        return voice_whiteboard_chart_branches();
    }

    // Ventas de la semana
    if (preg_match('/semana/i', $description)) {
        return voice_whiteboard_chart_sales_week();
    }

    // Ventas del mes (default)
    return voice_whiteboard_chart_sales_month();
}

function voice_whiteboard_chart_sales_month() {
    $monthStart = date('Y-m-01');
    $today = date('Y-m-d');
    $monthEnd = date('Y-m-t');
    $endDate = $monthEnd > $today ? $today : $monthEnd;

    $labels = array();
    $values = array();
    $current = strtotime($monthStart);
    $end = strtotime($endDate);

    while ($current <= $end) {
        $dateStr = date('Y-m-d', $current);
        $labels[] = date('j', $current);
        $values[] = (float)ingresos_total_periodo($dateStr, $dateStr);
        $current = strtotime('+1 day', $current);
    }

    // Use Spanish month name
    $months = array('', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
    $monthNum = (int)date('n');
    $monthName = $months[$monthNum] ?? date('F');
    $year = date('Y');

    return array(
        'type' => 'bar',
        'title' => "Ingresos de {$monthName} {$year}",
        'labels' => $labels,
        'datasets' => array(
            array('label' => 'Ingresos (€)', 'data' => $values),
        ),
    );
}

function voice_whiteboard_chart_sales_week() {
    $labels = array();
    $values = array();
    $weekDays = array('Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb');

    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $labels[] = $weekDays[(int)date('w', strtotime($date))];
        $values[] = (float)ingresos_total_periodo($date, $date);
    }

    return array(
        'type' => 'bar',
        'title' => 'Ingresos — últimos 7 días',
        'labels' => $labels,
        'datasets' => array(
            array('label' => 'Ingresos (€)', 'data' => $values),
        ),
    );
}

function voice_whiteboard_chart_branches() {
    $thisMonth = date('Y-m');
    $branches = array('lamami', 'jostal', 'casawasap');
    $branchLabels = array('lamami' => 'LaMami', 'jostal' => 'Jostal', 'casawasap' => 'Casawasap');
    $colors = array('#e2c044', '#4fc3f7', '#81c784');

    $labels = array();
    $data = array();
    $bgColors = array();

    $ingresos = storage_read('ingresos.json');
    if (!is_array($ingresos)) $ingresos = array();

    foreach ($branches as $branch) {
        $total = 0;
        foreach ($ingresos as $entry) {
            $fecha = $entry['fecha'] ?? '';
            if (strpos($fecha, $thisMonth) === 0 && ($entry['rama'] ?? '') === $branch) {
                $total += (float)($entry['cantidad'] ?? 0);
            }
        }
        $labels[] = $branchLabels[$branch];
        $data[] = round($total, 2);
        $bgColors[] = $colors[array_search($branch, $branches)];
    }

    // Use Spanish month name
    $months = array('', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre');
    $monthNum = (int)date('n');
    $monthName = $months[$monthNum] ?? date('F');

    return array(
        'type' => 'doughnut',
        'title' => "Ingresos por rama — {$monthName}",
        'labels' => $labels,
        'datasets' => array(
            array('label' => 'Ingresos (€)', 'data' => $data, 'backgroundColor' => $bgColors),
        ),
    );
}

// ═══════════════════════════════════════════════════════════════════
// DIARY PROACTIVE HANDLERS
// ═══════════════════════════════════════════════════════════════════

/**
 * Saludo matutino con contexto del diario: "Ayer fue un día {mood}."
 */
function voice_diary_build_morning_greeting() {
    $name = voice_assistant_name();
    $data = voice_diary_read();
    $entries = $data['entries'] ?? array();

    // Buscar entrada de ayer
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $yesterdayEntry = null;
    foreach ($entries as $e) {
        if (($e['fecha'] ?? '') === $yesterday) {
            $yesterdayEntry = $e;
            break;
        }
    }

    $message = '';
    $tts = '';

    if ($yesterdayEntry) {
        $mood = $yesterdayEntry['mood'] ?? 'neutro';
        $moodEmoji = voice_diary_mood_emoji($mood);
        $highlights = $yesterdayEntry['highlights'] ?? array();
        $highlightText = !empty($highlights) ? $highlights[0] : '';

        $message = "Buenos días. Ayer fue un día $mood $moodEmoji.";
        if ($highlightText) {
            $message .= " $highlightText";
        }
        $message .= " ¿Cómo te sientes hoy?";
        $tts = $message;
    } else {
        $message = "Buenos días. ¿Cómo te sientes hoy?";
        $tts = $message;
    }

    return json_encode(array(
        'ok' => true,
        'message' => $message,
        'tts_text' => $tts,
    ), JSON_UNESCAPED_UNICODE);
}

/**
 * Preocupación persistente: "Llevas 3 días mencionando X."
 */
function voice_diary_build_worry_nag() {
    $model = voice_user_model_read();
    $preocupaciones = $model['preocupaciones'] ?? array();

    // Buscar preocupación activa con más frecuencia
    $topWorry = null;
    foreach ($preocupaciones as $p) {
        if (!($p['resuelta'] ?? false) && ($p['frecuencia'] ?? 0) >= 3) {
            if (!$topWorry || ($p['frecuencia'] ?? 0) > ($topWorry['frecuencia'] ?? 0)) {
                $topWorry = $p;
            }
        }
    }

    if (!$topWorry) {
        return json_encode(array('ok' => false, 'message' => '', 'tts_text' => ''));
    }

    $tema = $topWorry['tema'] ?? 'esto';
    $frec = $topWorry['frecuencia'] ?? 3;
    $message = "Llevas {$frec} días mencionando {$tema}. ¿Quieres que lo miremos juntos?";
    return json_encode(array(
        'ok' => true,
        'message' => $message,
        'tts_text' => $message,
    ), JSON_UNESCAPED_UNICODE);
}

/**
 * Recordatorio de idea pendiente: "Tenías una idea sobre X."
 */
function voice_diary_build_idea_remind() {
    $model = voice_user_model_read();
    $ideas = $model['ideas_pendientes'] ?? array();

    // Filtrar ideas pendientes no archivadas
    $pendientes = array_filter($ideas, function ($i) {
        return ($i['estado'] ?? '') === 'pendiente';
    });

    if (empty($pendientes)) {
        return json_encode(array('ok' => false, 'message' => '', 'tts_text' => ''));
    }

    // Coger la más antigua
    usort($pendientes, function ($a, $b) {
        return ($a['fecha'] ?? '') <=> ($b['fecha'] ?? '');
    });
    $idea = $pendientes[0];
    $que = $idea['que'] ?? 'una idea que tuviste';
    $fecha = $idea['fecha'] ?? '';

    $message = "El {$fecha} tuviste una idea: {$que}. ¿Has avanzado algo?";
    return json_encode(array(
        'ok' => true,
        'message' => $message,
        'tts_text' => $message,
    ), JSON_UNESCAPED_UNICODE);
}

/**
 * Recordatorio de decisión que podría necesitar revisión.
 */
function voice_diary_build_decision_nudge() {
    $model = voice_user_model_read();
    $decisiones = $model['decisiones'] ?? array();
    $today = date('Y-m-d');

    // Buscar decisión vigente con más de 60 días
    $oldDecision = null;
    foreach ($decisiones as $d) {
        if (!($d['vigente'] ?? false)) continue;
        $cuando = $d['cuando'] ?? '';
        if ($cuando === '') continue;
        $daysSince = (strtotime($today) - strtotime($cuando)) / 86400;
        if ($daysSince > 60) {
            $oldDecision = $d;
            break;
        }
    }

    if (!$oldDecision) {
        return json_encode(array('ok' => false, 'message' => '', 'tts_text' => ''));
    }

    $que = $oldDecision['que'] ?? 'una decisión';
    $cuando = $oldDecision['cuando'] ?? '';
    $message = "El {$cuando} decidiste {$que}. ¿Sigue vigente o ha cambiado algo?";
    return json_encode(array(
        'ok' => true,
        'message' => $message,
        'tts_text' => $message,
    ), JSON_UNESCAPED_UNICODE);
}

/**
 * Check de estado de ánimo bajo: "Te noto bajo de ánimo varios días."
 */
function voice_diary_build_mood_check() {
    $model = voice_user_model_read();
    $historico = $model['estado_emocional']['historico'] ?? array();

    if (count($historico) < 3) {
        return json_encode(array('ok' => false, 'message' => '', 'tts_text' => ''));
    }

    $negative = array('preocupado', 'frustrado', 'estresado', 'cansado');
    $recent = array_slice($historico, -3);
    $negativeCount = 0;
    foreach ($recent as $h) {
        if (in_array($h['mood'] ?? '', $negative, true)) $negativeCount++;
    }

    if ($negativeCount < 3) {
        return json_encode(array('ok' => false, 'message' => '', 'tts_text' => ''));
    }

    $message = "Te noto bajo de ánimo estos últimos días. ¿Quieres hablar de ello? No tienes que cargar con todo solo.";
    return json_encode(array(
        'ok' => true,
        'message' => $message,
        'tts_text' => $message,
    ), JSON_UNESCAPED_UNICODE);
}

/**
 * Trigger para compilar la entrada del día (se llama al final del día).
 */
function voice_diary_build_compile_trigger() {
    $buffer = voice_diary_buffer_get_today();
    if (count($buffer) < 2) {
        return json_encode(array('ok' => false, 'message' => '', 'tts_text' => ''));
    }

    $entry = voice_diary_compile_daily();
    if (!$entry) {
        return json_encode(array('ok' => false, 'message' => '', 'tts_text' => ''));
    }

    $mood = $entry['mood'] ?? 'neutro';
    $message = "He compilado tu diario de hoy. Ha sido un día {$mood}. ¿Algo más que quieras añadir?";
    return json_encode(array(
        'ok' => true,
        'message' => $message,
        'tts_text' => $message,
    ), JSON_UNESCAPED_UNICODE);
}

function voice_tool_action_json($stage, $intent, $data) {
    return json_encode(array(
        'ok' => true,
        'stage' => $stage,
        'intent' => $intent,
        'message' => $data['message'] ?? '',
        'system_actions' => array($data),
    ), JSON_UNESCAPED_UNICODE);
}

/**
 * Inyecta contexto de WhatsApp reciente en el system prompt de Jefry.
 * Devuelve texto con actividad reciente de WhatsApp o cadena vacía.
 */
function wasap_learning_inject_context() {
    $storePath = __DIR__ . '/../data/personal_wasap_data.json';
    if (!file_exists($storePath)) return '';

    $store = @json_decode((string)@file_get_contents($storePath), true);
    if (!is_array($store)) return '';

    $today = date('Y-m-d');
    $stats = $store['learning']['daily_stats'][$today] ?? null;
    $chats = $store['chats'] ?? array();

    $lines = array();
    $lines[] = '[WHATSAPP — ACTIVIDAD RECIENTE]';

    if ($stats) {
        $sent = (int)($stats['messages_sent'] ?? 0);
        $received = (int)($stats['messages_received'] ?? 0);
        $contacts = (array)($stats['contacts_talked_to'] ?? array());
        $lines[] = "Hoy: {$sent} enviados, {$received} recibidos, con " . count($contacts) . " contacto(s).";
    }

    // Top 3 contactos más activos (por número de mensajes en el store)
    $contactActivity = array();
    foreach ($chats as $chatId => $chat) {
        $name = (!empty($chat['contact_name']) ? $chat['contact_name'] : ($chat['contact_phone'] ?? ''));
        if ($name === '') continue;
        $total = count($chat['messages'] ?? array());
        $contactActivity[$name] = $total;
    }
    arsort($contactActivity);
    $topContacts = array_slice(array_keys($contactActivity), 0, 3);
    if (!empty($topContacts)) {
        $lines[] = 'Contactos más frecuentes: ' . implode(', ', $topContacts) . '.';
    }

    // Contactos con mensajes no leídos
    $unreadContacts = array();
    foreach ($chats as $chatId => $chat) {
        $unread = (int)($chat['unread_count'] ?? 0);
        if ($unread > 0) {
            $name = (!empty($chat['contact_name']) ? $chat['contact_name'] : ($chat['contact_phone'] ?? ''));
            $unreadContacts[] = "{$name} ({$unread})";
        }
    }
    if (!empty($unreadContacts)) {
        $lines[] = 'Mensajes sin leer de: ' . implode(', ', $unreadContacts) . '.';
    }

    if (count($lines) <= 1) return '';

    return implode("\n", $lines);
}
