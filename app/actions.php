<?php

function handle_get_actions() {
    @ini_set('display_errors', '0');
    if (!is_logged_in()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'error' => 'No autenticado.'));
        exit;
    }
    $action = trim((string)($_GET['action'] ?? ''));
    switch ($action) {
        case 'poll_publicista_regen_status':
            action_poll_publicista_regen_status();
            break;
        case 'cancel_publicista_regen_queue':
            action_cancel_publicista_regen_queue();
            break;
        case 'touch_gps':
            action_touch_gps();
            break;
        case 'export_gpx':
            action_export_gpx();
            break;
        case 'youtube_audio_proxy':
            action_youtube_audio_proxy();
            break;
        case 'youtube_audio_health':
            action_youtube_audio_health();
            break;
        default:
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('ok' => false, 'error' => 'Acción GET desconocida.'));
            exit;
    }
}

// ── GPS position tracking (JSONL) ───────────────────────────────────────────
function action_touch_gps() {
    $lat = round((float)($_GET['lat'] ?? 0), 6);
    $lng = round((float)($_GET['lng'] ?? 0), 6);
    $acc = round((float)($_GET['acc'] ?? 0), 1);

    if ($lat === 0.0 && $lng === 0.0) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'error' => 'Coordenadas inválidas'));
        exit;
    }

    // ⛔ Solo aceptar GPS del dispositivo coche real (defensa en profundidad)
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (stripos($ua, 'evb3561sv_w_65_m0') === false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'error' => 'Dispositivo no autorizado para GPS'));
        exit;
    }

    // Rechazar posiciones con precisión pobre (>50m = sin chip GPS, solo IP/WiFi/torre)
    if ($acc > 50) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'error' => 'Precisión insuficiente'));
        exit;
    }

    $entry = array(
        'ts'   => date('c'),
        'lat'  => $lat,
        'lng'  => $lng,
        'acc'  => $acc,
        'user' => $_SESSION['username'] ?? 'unknown',
    );

    $file = __DIR__ . '/../data/gps_positions.jsonl';
    $dir  = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $written = file_put_contents($file, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);

    if ($written === false) {
        bootstrap_runtime_log('action_touch_gps | ERROR: no se pudo escribir en ' . $file . ' - lat=' . $lat . ' lng=' . $lng);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'error' => 'Error de escritura en el servidor'));
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => true));
    exit;
}

// ── GPS: exportar ruta del día como GPX ──────────────────────────────────────
function action_export_gpx() {
    if (!is_logged_in()) {
        header('HTTP/1.1 403 Forbidden');
        exit;
    }

    $day = trim((string)($_GET['day'] ?? ''));
    if ($day === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
        header('HTTP/1.1 400 Bad Request');
        echo 'Fecha inválida. Usa ?day=YYYY-MM-DD';
        exit;
    }

    $positions = gps_read_positions(0);
    $grouped   = gps_group_by_day($positions);

    if (!isset($grouped[$day]) || empty($grouped[$day])) {
        header('HTTP/1.1 404 Not Found');
        echo 'No hay datos GPS para el día ' . htmlspecialchars($day);
        exit;
    }

    $dayPositions = $grouped[$day];

    // Generar GPX 1.1
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<gpx version="1.1" creator="LaMami CRM" xmlns="http://www.topografix.com/GPX/1/1">' . "\n";
    $xml .= '  <trk>' . "\n";
    $xml .= '    <name>Ruta ' . htmlspecialchars($day) . '</name>' . "\n";
    $xml .= '    <trkseg>' . "\n";

    foreach ($dayPositions as $p) {
        $ts = date('c', $p['_ts']);
        $xml .= '      <trkpt lat="' . $p['lat'] . '" lon="' . $p['lng'] . '">' . "\n";
        $xml .= '        <time>' . $ts . '</time>' . "\n";
        if (!empty($p['acc'])) {
            $xml .= '        <hdop>' . round($p['acc'] / 5, 1) . '</hdop>' . "\n";
        }
        $xml .= '      </trkpt>' . "\n";
    }

    $xml .= '    </trkseg>' . "\n";
    $xml .= '  </trk>' . "\n";
    $xml .= '</gpx>' . "\n";

    $filename = 'ruta-' . $day . '.gpx';
    header('Content-Type: application/gpx+xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($xml));
    echo $xml;
    exit;
}

// ── GPS: renombrar un lugar detectado ─────────────────────────────────────────
function action_rename_place() {
    $lat  = round((float)request_post('lat', 0), 4);
    $lng  = round((float)request_post('lng', 0), 4);
    $name = trim((string)request_post('name', ''));

    if ($lat === 0.0 && $lng === 0.0) {
        set_flash('error', 'Coordenadas inválidas.');
        redirect_to('index.php?page=josue&tab=rutas');
    }
    if ($name === '') {
        set_flash('error', 'El nombre no puede estar vacío.');
        redirect_to('index.php?page=josue&tab=rutas');
    }

    $key = $lat . ',' . $lng;
    $settings = storage_read('settings.json');
    if (!isset($settings['rutas_place_names']) || !is_array($settings['rutas_place_names'])) {
        $settings['rutas_place_names'] = array();
    }
    $settings['rutas_place_names'][$key] = $name;
    storage_write('settings.json', $settings);

    set_flash('ok', 'Lugar renombrado a "' . $name . '".');
    redirect_to('index.php?page=josue&tab=rutas');
}

// ── GPS: ocultar un lugar detectado ────────────────────────────────────────────
function action_hide_place() {
    $lat = round((float)request_post('lat', 0), 4);
    $lng = round((float)request_post('lng', 0), 4);

    if ($lat === 0.0 && $lng === 0.0) {
        set_flash('error', 'Coordenadas inválidas.');
        redirect_to('index.php?page=josue&tab=rutas');
    }

    $key = $lat . ',' . $lng;
    $settings = storage_read('settings.json');
    if (!isset($settings['rutas_hidden_places']) || !is_array($settings['rutas_hidden_places'])) {
        $settings['rutas_hidden_places'] = array();
    }
    $settings['rutas_hidden_places'][$key] = true;
    storage_write('settings.json', $settings);

    set_flash('ok', 'Lugar ocultado.');
    redirect_to('index.php?page=josue&tab=rutas');
}

function handle_post_actions() {
    $action = request_post('action');

    if ($action === 'login') {
        $user = trim(request_post('username'));
        $pass = (string)request_post('password');
        if (login_user($user, $pass)) {
            set_flash('ok', 'Bienvenida al sistema.');
            redirect_to('index.php?page=dashboard');
        }
        set_flash('error', 'Usuario o contraseña incorrectos.');
        redirect_to('index.php?page=login');
    }

    if (!is_logged_in()) {
        if ($action === 'voice_command' || $action === 'voice_proactive' || $action === 'debug_voice') {
            voice_json_response(voice_build_response(array(
                'ok' => false,
                'stage' => 'error',
                'message' => 'La sesión no está activa.',
                'errors' => array('auth_required'),
            )));
        }
        redirect_to('index.php?page=login');
    }

    if (in_array($action, array('create_manual_aviso', 'delete_planned_aviso', 'mark_avisos_read', 'comercial_export_threads_csv', 'jostal_compensar_lead'), true)) {
        if (!csrf_validate((string)request_post('csrf_token'))) {
            set_flash('error', 'La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.');
            redirect_to(safe_internal_redirect_path(request_post('redirect', ''), 'index.php?page=dashboard'));
        }
    }

    switch ($action) {
        case 'save_interesada':
            action_save_interesada();
            break;
        case 'delete_interesada':
            action_delete_generic('interesadas.json', 'Interesada eliminada.', lamami_tab_url('interesadas'));
            break;
        case 'set_interesada_estado':
            action_set_interesada_estado();
            break;
        case 'convert_interesada':
            action_convert_interesada();
            break;
        case 'save_clienta':
            action_save_clienta();
            break;
        case 'baja_clienta':
            action_baja_clienta();
            break;
        case 'alta_clienta':
            action_alta_clienta();
            break;
        case 'save_bot':
            action_save_bot();
            break;
        case 'delete_bot':
            action_delete_generic('bots.json', 'Bot eliminado.', 'index.php?page=bots');
            break;
        case 'set_bot_runtime_mode':
            action_set_bot_runtime_mode();
            break;
        case 'set_dashboard_external_bot_runtime_mode':
            action_set_dashboard_external_bot_runtime_mode();
            break;
        case 'quick_lead':
            action_quick_lead();
            break;
        case 'delete_lead':
            action_delete_lead();
            break;
        case 'generate_bot_assets':
            action_generate_bot_assets();
            break;
        case 'save_josue_text':
            action_save_josue_text();
            break;
        case 'save_casawasap_contacto':
            action_save_casawasap_contacto();
            break;
        case 'convert_casawasap_cliente':
            action_convert_casawasap_cliente();
            break;
        case 'casawasap_add_pago':
            action_casawasap_add_pago();
            break;
        case 'delete_casawasap_pago':
            action_delete_casawasap_pago();
            break;
        case 'save_jostal_interesada':
            action_save_jostal_interesada();
            break;
        case 'convert_jostal_clienta':
            action_convert_jostal_clienta();
            break;
        case 'save_jostal_clienta':
            action_save_jostal_clienta();
            break;
        case 'jostal_add_lead':
            action_jostal_add_lead();
            break;
        case 'jostal_edit_lead':
            action_jostal_edit_lead();
            break;
        case 'jostal_delete_lead':
            action_jostal_delete_lead();
            break;
        case 'jostal_add_venta':
            action_jostal_add_venta();
            break;
        case 'add_gasto':
            action_add_gasto();
            break;
        case 'delete_gasto':
            action_delete_gasto();
            break;

        case 'set_casawasap_estado':
            action_set_casawasap_estado();
            break;
        case 'baja_casawasap_cliente':
            action_baja_casawasap_cliente();
            break;
        case 'alta_casawasap_cliente':
            action_alta_casawasap_cliente();
            break;

        case 'discard_jostal_interesada':
            action_discard_jostal_interesada();
            break;
        case 'reactivate_jostal_interesada':
            action_reactivate_jostal_interesada();
            break;
        case 'jostal_salida_casa':
            action_jostal_salida_casa();
            break;
        case 'jostal_reactivar_casa':
            action_jostal_reactivar_casa();
            break;
        case 'jostal_update_rent_due_weekday':
            action_jostal_update_rent_due_weekday();
            break;
        case 'jostal_clasificar_lead':
            action_jostal_clasificar_lead();
            break;
        case 'jostal_compensar_lead':
            action_jostal_compensar_lead();
            break;
        case 'jostal_send_deuda_wasap':
            action_jostal_send_deuda_wasap();
            break;

        case 'unlock_josue_anuncios':
            action_unlock_josue_anuncios();
            break;
        case 'unlock_josue_wasap':
            action_unlock_josue_wasap();
            break;
        case 'rename_place':
            action_rename_place();
            break;
        case 'hide_place':
            action_hide_place();
            break;
        case 'save_anuncio':
            action_save_anuncio();
            break;
        case 'delete_anuncio':
            action_delete_anuncio();
            break;
        case 'unlock_publicista_accounts':
            action_unlock_publicista_accounts();
            break;
        case 'save_publicista_account':
            action_save_publicista_account();
            break;
        case 'delete_publicista_account':
            action_delete_publicista_account();
            break;
        case 'save_publicista_planning':
            action_save_publicista_planning();
            break;
        case 'duplicate_publicista_planning':
            action_duplicate_publicista_planning();
            break;
        case 'delete_publicista_planning':
            action_delete_publicista_planning();
            break;
        case 'set_publicista_planning_status':
            action_set_publicista_planning_status();
            break;
        case 'save_publicista_campaign':
            action_save_publicista_campaign();
            break;
        case 'generate_publicista_campaign':
            action_generate_publicista_campaign();
            break;
        case 'delete_publicista_campaign':
            action_delete_publicista_campaign();
            break;
        case 'set_publicista_campaign_status':
            action_set_publicista_campaign_status();
            break;
        case 'execute_publicista_campaign':
            action_execute_publicista_campaign();
            break;
        case 'sync_publicista_campaign_to_girlsconf':
            action_sync_publicista_campaign_to_girlsconf();
            break;
        case 'resubmit_publicista_campaign_portal':
            action_resubmit_publicista_campaign_portal();
            break;
        case 'stop_publicista_campaign_run':
            action_stop_publicista_campaign_run();
            break;
        case 'save_publicista_campaign_item_meta':
            action_save_publicista_campaign_item_meta();
            break;
        case 'upload_single_campaign_item':
            action_upload_single_campaign_item();
            break;
        case 'save_publicista_campaign_auto_rotation':
            action_save_publicista_campaign_auto_rotation();
            break;
        case 'force_publicista_campaign_auto_rotation_now':
            action_force_publicista_campaign_auto_rotation_now();
            break;
        case 'rebalance_publicista_campaign_distribution':
            action_rebalance_publicista_campaign_distribution();
            break;
        case 'run_publicista_task':
            action_run_publicista_task();
            break;
        case 'set_publicista_task_status':
            action_set_publicista_task_status();
            break;

        case 'unlock_josue_anuncios':
            action_unlock_josue_anuncios();
            break;
        case 'save_anuncio':
            action_save_anuncio();
            break;
        case 'delete_anuncio':
            action_delete_anuncio();
            break;
        case 'save_publicista_account':
            action_save_publicista_account();
            break;
        case 'delete_publicista_account':
            action_delete_publicista_account();
            break;
        case 'save_publicista_free_bump_config':
            action_save_publicista_free_bump_config();
            break;
        case 'run_publicista_free_bump_cycle':
            action_run_publicista_free_bump_cycle();
            break;
        case 'save_telefono':
            action_save_telefono();
            break;
        case 'delete_telefono':
            action_delete_telefono();
            break;
        case 'save_comercial_distribution':
            action_save_comercial_distribution();
            break;
        case 'toggle_comercial_process_enabled':
            action_toggle_comercial_process_enabled();
            break;
        case 'save_comercial_settings':
            action_save_comercial_settings();
            break;
        case 'save_comercial_process':
            action_save_comercial_process();
            break;
        case 'upload_plaza_room_photo':
            action_upload_plaza_room_photo();
            break;
        case 'delete_plaza_room_photo':
            action_delete_plaza_room_photo();
            break;
        case 'save_comercial_blacklist':
            action_save_comercial_blacklist();
            break;
        case 'delete_comercial_blacklist':
            action_delete_comercial_blacklist();
            break;
        case 'comercial_run_tick':
            action_comercial_run_tick();
            break;
        case 'comercial_run_test_probe':
            action_comercial_run_test_probe();
            break;
        case 'comercial_reset_test_probe':
            action_comercial_reset_test_probe();
            break;
        case 'save_comercial_line_state':
            action_save_comercial_line_state();
            break;
        case 'comercial_check_lines_health':
            action_comercial_check_lines_health();
            break;
        case 'comercial_set_thread_stage':
            action_comercial_set_thread_stage();
            break;
        case 'comercial_send_thread_message':
            action_comercial_send_thread_message();
            break;
        case 'comercial_promote_thread':
            action_comercial_promote_thread();
            break;
        case 'toggle_inbox_replies':
            action_toggle_inbox_replies();
            break;
        case 'toggle_inbox_opener':
            action_toggle_inbox_opener();
            break;
        case 'inbox_toggle_thread_pause':
            action_inbox_toggle_thread_pause();
            break;
        case 'comercial_export_threads_csv':
            action_comercial_export_threads_csv();
            break;
        case 'comercial_run_ai_qualification':
            action_comercial_run_ai_qualification();
            break;
        case 'save_estados_wasap_config':
            action_save_estados_wasap_config();
            break;
        case 'publicar_estado_manual':
            action_publicar_estado_manual();
            break;
        case 'dismiss_aviso':
            action_dismiss_aviso();
            break;
        case 'mark_avisos_read':
            action_mark_avisos_read();
            break;
        case 'save_agenda':
            action_save_agenda();
            break;
        case 'delete_agenda':
            action_delete_agenda();
            break;
        case 'save_eureka':
            action_save_eureka();
            break;
        case 'generate_eureka_prompt':
            action_generate_eureka_prompt();
            break;
        case 'delete_eureka':
            action_delete_eureka();
            break;
        case 'set_eureka_estado':
            action_set_eureka_estado();
            break;
        case 'create_manual_aviso':
            action_create_manual_aviso();
            break;
        case 'delete_planned_aviso':
            action_delete_planned_aviso();
            break;

        case 'save_configm':
            action_save_configm();
            break;
        case 'save_access_config':
            action_save_access_config();
            break;
        case 'revoke_trusted_device':
            action_revoke_trusted_device();
            break;
        case 'save_voice_ai_config':
            action_save_voice_ai_config();
            break;

        case 'save_lamamibot':
            action_save_lamamibot();
            break;

        case 'generate_lamamibot_assets':
            action_generate_lamamibot_assets();
            break;
        case 'set_lamamibot_runtime_mode':
            action_set_lamamibot_runtime_mode();
            break;

        case 'create_publicista_job':
            action_create_publicista_job();
            break;
        case 'save_publicista_job':
            action_save_publicista_job();
            break;
        case 'delete_publicista_job':
            action_delete_publicista_job();
            break;
        case 'prepare_publicista_job_engine':
            action_prepare_publicista_job_engine();
            break;
        case 'run_publicista_image_pipeline':
            action_run_publicista_image_pipeline();
            break;
        case 'regenerate_publicista_candidate':
            action_regenerate_publicista_candidate();
            break;
        case 'regenerate_publicista_sexy_candidate':
            action_regenerate_publicista_sexy_candidate();
            break;
        case 'poll_publicista_regen_status':
            action_poll_publicista_regen_status();
            break;
        case 'refresh_publicista_final_local':
            action_refresh_publicista_final_local();
            break;
        case 'choose_publicista_final_variant':
            action_choose_publicista_final_variant();
            break;
        case 'mark_publicista_pack_definitive':
            action_mark_publicista_pack_definitive();
            break;
        case 'generate_publicista_copy_pack':
            action_generate_publicista_copy_pack();
            break;
        case 'regenerate_publicista_copy_title':
            action_regenerate_publicista_copy_title();
            break;
        case 'regenerate_publicista_copy_ad':
            action_regenerate_publicista_copy_ad();
            break;
        case 'duplicate_publicista_job':
            action_duplicate_publicista_job();
            break;
        case 'apply_publicista_manual_blur':
            action_apply_publicista_manual_blur();
            break;
        case 'apply_publicista_manual_blur_real':
            action_apply_publicista_manual_blur_real();
            break;
        case 'apply_publicista_manual_blur_source':
            action_apply_publicista_manual_blur_source();
            break;
        case 'upload_publicista_real_photos':
            action_upload_publicista_real_photos();
            break;
        case 'delete_publicista_real_photo':
            action_delete_publicista_real_photo();
            break;
        case 'save_publicista_platform_photos':
            action_save_publicista_platform_photos();
            break;
        case 'voice_command':
            action_voice_command();
            break;
        case 'debug_voice':
            action_debug_voice();
            break;
        case 'tts':
            action_tts();
            break;
        case 'voice_check_reminders':
            action_voice_check_reminders();
            break;
        case 'voice_proactive':
            $trigger = trim((string)($_POST['proactive_trigger'] ?? ''));
            $context = json_decode((string)($_POST['proactive_context_json'] ?? '{}'), true) ?: array();
            echo voice_handle_proactive($trigger, $context);
            break;
        case 'get_diario_entries':
            action_get_diario_entries();
            break;
        case 'get_diario_entry':
            action_get_diario_entry();
            break;
        case 'search_diario':
            action_search_diario();
            break;
        case 'save_jostal_contrato':
            action_save_jostal_contrato();
            break;
        case 'submit_contrato_firma':
            action_submit_contrato_firma();
            break;
        case 'youtube_search':
            action_youtube_search();
            break;
        case 'youtube_suggest':
            action_youtube_suggest();
            break;
        case 'youtube_log_history':
            action_youtube_log_history();
            break;
        case 'youtube_save_playlist':
            action_youtube_save_playlist();
            break;
        case 'youtube_delete_playlist':
            action_youtube_delete_playlist();
            break;
        case 'youtube_add_to_playlist':
            action_youtube_add_to_playlist();
            break;
        case 'youtube_remove_from_playlist':
            action_youtube_remove_from_playlist();
            break;
        case 'youtube_create_topic_channel':
            action_youtube_create_topic_channel();
            break;
        case 'youtube_delete_topic_channel':
            action_youtube_delete_topic_channel();
            break;
        case 'youtube_topic_channel_videos':
            action_youtube_topic_channel_videos();
            break;
        case 'youtube_seed_channels':
            action_youtube_seed_channels();
            break;
        case 'youtube_reorder_playlist':
            action_youtube_reorder_playlist();
            break;
        case 'youtube_audio_stream':
            action_youtube_audio_stream();
            break;
        case 'youtube_audio_proxy':
            action_youtube_audio_proxy();
            break;
        case 'youtube_audio_health':
            action_youtube_audio_health();
            break;
        case 'youtube_voice_search':
            action_youtube_voice_search();
            break;
    }
}

function action_save_publicista_job() {
    $id = trim((string)request_post('id'));
    $existing = publicista_job_get($id);

    if (!$existing) {
        set_flash('error', 'No se encontró el trabajo de Publicista.');
        redirect_to(publicista_tab_url());
    }

    $clientaParsed = publicista_parse_clienta_picker_value(request_post('clienta_id'));
    $clientaRef = publicista_find_clienta_any($clientaParsed['id'], $clientaParsed['scope']);
    $clienta = $clientaRef ? ($clientaRef['row'] ?? array()) : null;
    if (!$clientaRef || !$clienta) {
        set_flash('error', 'Selecciona una clienta válida.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    $row = $existing;
    $row['clienta_id'] = trim((string)($clientaRef['id'] ?? ($clienta['id'] ?? '')));
    $row['clienta_scope'] = trim((string)($clientaRef['scope'] ?? 'lamami'));
    $row['clienta_nombre_snapshot'] = trim((string)($clientaRef['nombre'] ?? ($clienta['nombre'] ?? '')));
    $row['publish_name'] = trim((string)request_post('publish_name'));
    $row['nombre_trabajo'] = trim((string)request_post('nombre_trabajo'));
    $row['estado'] = trim((string)request_post('estado', $existing['estado'] ?? 'draft'));
    $row['notas'] = trim((string)request_post('notas'));
    $row['physical_notes'] = '';
    $row['services_snapshot'] = trim((string)request_post('services_snapshot'));
    $row['tarifas_snapshot'] = trim((string)request_post('tarifas_snapshot'));
    $row['localidad_snapshot'] = trim((string)request_post('localidad_snapshot'));
    $row['provincia_snapshot'] = trim((string)request_post('provincia_snapshot'));
    $rawRestr = trim((string)request_post('restrictions_text'));
    if (function_exists('mb_substr')) {
        $rawRestr = mb_substr($rawRestr, 0, 1000, 'UTF-8');
    } else {
        $rawRestr = substr($rawRestr, 0, 1000);
    }
    $rawRestr = preg_replace('/\[CAPA\b/i', '[C4P4-', $rawRestr);
    $row['workflow'] = array_merge(isset($existing['workflow']) && is_array($existing['workflow']) ? $existing['workflow'] : array(), array(
        'restrictions_text' => $rawRestr,
        'restriction_flags' => publicista_normalize_restriction_flags(request_post('restriction_flags', array())),
        'auto_regenerate' => !empty($_POST['auto_regenerate']) ? 1 : 0,
    ));
    $row['copy_pack'] = array_merge(isset($existing['copy_pack']) && is_array($existing['copy_pack']) ? $existing['copy_pack'] : array(), array(
        'desired_tone' => trim((string)request_post('copy_tone', 'equilibrado')),
    ));
    $row['production_params'] = function_exists('publicista_normalize_outfit_params')
        ? publicista_normalize_outfit_params($_POST)
        : array();

    list($ok, $result) = publicista_job_save($row);

    // Guardar fotos reales subidas si las hay
    $realPhotos = isset($_FILES['real_photos']) && is_array($_FILES['real_photos']) ? $_FILES['real_photos'] : null;
    if ($realPhotos && !empty($realPhotos['tmp_name'])) {
        $tmpNames = is_array($realPhotos['tmp_name']) ? $realPhotos['tmp_name'] : array();
        $hasFile = false;
        foreach ($tmpNames as $t) {
            if ((string)$t !== '') { $hasFile = true; break; }
        }
        if ($hasFile) {
            list($okReal, $resultReal) = publicista_attach_real_photos($id, $realPhotos);
            if ($okReal && is_array($resultReal)) {
                $result = $resultReal;
                $ok = true;
            }
        }
    }

    if (!$ok) {
        set_flash('error', is_string($result) ? $result : 'No se pudo guardar el trabajo de Publicista.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    set_flash('ok', 'Trabajo de Publicista guardado.');
    redirect_to(publicista_tab_url(array('job' => $id)));
}

function action_upload_publicista_real_photos() {
    if (!csrf_validate((string)request_post('csrf_token'))) {
        set_flash('error', 'Sesión caducada. Recarga la página.');
        redirect_to(publicista_tab_url());
    }

    $id = trim((string)request_post('id'));
    $job = publicista_job_get($id);
    if (!$job) {
        set_flash('error', 'No se encontró el trabajo de Publicista.');
        redirect_to(publicista_tab_url());
    }

    $realPhotos = isset($_FILES['real_photos']) && is_array($_FILES['real_photos']) ? $_FILES['real_photos'] : null;
    if (!$realPhotos || empty($realPhotos['tmp_name'])) {
        set_flash('error', 'No se seleccionaron fotos para subir.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    $tmpNames = is_array($realPhotos['tmp_name']) ? $realPhotos['tmp_name'] : array();
    $hasFile = false;
    foreach ($tmpNames as $t) {
        if ((string)$t !== '') { $hasFile = true; break; }
    }
    if (!$hasFile) {
        set_flash('error', 'No se seleccionaron fotos para subir.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    list($ok, $result) = publicista_attach_real_photos($id, $realPhotos);
    if (!$ok) {
        set_flash('error', is_string($result) ? $result : 'No se pudieron subir las fotos reales.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    set_flash('ok', 'Fotos reales subidas correctamente.');
    redirect_to(publicista_tab_url(array('job' => $id)));
}

function action_delete_publicista_real_photo() {
    if (!csrf_validate((string)request_post('csrf_token'))) {
        set_flash('error', 'Sesión caducada. Recarga la página.');
        redirect_to(publicista_tab_url());
    }

    $id = trim((string)request_post('id'));
    $photoId = trim((string)request_post('photo_id'));
    $job = publicista_job_get($id);
    if (!$job) {
        set_flash('error', 'No se encontró el trabajo de Publicista.');
        redirect_to(publicista_tab_url());
    }

    $photos = is_array($job['real_photos'] ?? null) ? $job['real_photos'] : array();
    $found = null;
    foreach ($photos as $i => $rp) {
        if (($rp['id'] ?? '') === $photoId) {
            $found = $i;
            break;
        }
    }
    if ($found === null) {
        set_flash('error', 'Foto no encontrada.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    // Eliminar archivo físico del disco (derivar ruta desde photoId, no desde stored_path)
    $paths = publicista_job_fs_paths($id);
    $mimeType = trim((string)($photos[$found]['mime_type'] ?? 'image/jpeg'));
    $ext = function_exists('publicista_guess_extension_from_mime') ? publicista_guess_extension_from_mime($mimeType) : 'jpg';
    $fs = $paths['reals_dir'] . '/' . $photoId . '.' . $ext;
    if (file_exists($fs)) {
        @unlink($fs);
    }

    // Eliminar del array y reindexar IDs
    array_splice($photos, $found, 1);
    $photos = array_values($photos);
    foreach ($photos as $j => &$rpRef) {
        $rpRef['id'] = 'real_' . str_pad((string)($j + 1), 2, '0', STR_PAD_LEFT);
    }
    unset($rpRef);

    $job['real_photos'] = $photos;
    list($ok, $result) = publicista_job_save($job);
    if (!$ok) {
        set_flash('error', is_string($result) ? $result : 'No se pudo guardar tras eliminar la foto.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    set_flash('ok', 'Foto real eliminada.');
    redirect_to(publicista_tab_url(array('job' => $id)));
}

function action_save_publicista_platform_photos() {
    $id = trim((string)request_post('id'));
    if (!csrf_validate((string)request_post('csrf_token'))) {
        set_flash('error', 'Sesión caducada. Recarga la página.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    $job = publicista_job_get($id);
    if (!$job) {
        set_flash('error', 'No se encontró el trabajo de Publicista.');
        redirect_to(publicista_tab_url());
    }

    $platformPhotos = is_array($job['platform_photos'] ?? null) ? $job['platform_photos'] : array(
        'destacamos' => array(), 'mundosex' => array(), 'girlsconf' => array(),
    );

    $posted = isset($_POST['platform_photos']) && is_array($_POST['platform_photos']) ? $_POST['platform_photos'] : array();
    foreach (array('destacamos', 'mundosex', 'girlsconf') as $pCode) {
        $ids = isset($posted[$pCode]) && is_array($posted[$pCode]) ? $posted[$pCode] : array();
        $platformPhotos[$pCode] = array_values(array_map('trim', $ids));
    }

    $job['platform_photos'] = $platformPhotos;
    list($ok, $result) = publicista_job_save($job);
    if (!$ok) {
        set_flash('error', is_string($result) ? $result : 'No se pudo guardar la configuración de fotos por plataforma.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    set_flash('ok', 'Fotos por plataforma guardadas.');
    redirect_to(publicista_tab_url(array('job' => $id)));
}

function action_delete_publicista_job() {
    $id = trim((string)request_post('id'));
    list($ok, $message) = publicista_job_delete($id);

    set_flash($ok ? 'ok' : 'error', $message);
    redirect_to(publicista_tab_url());
}


function action_prepare_publicista_job_engine() {
    $id = trim((string)request_post('id'));
    $job = publicista_job_get($id);
    if (!$job) {
        set_flash('error', 'No se encontró el trabajo de Publicista.');
        redirect_to(publicista_tab_url());
    }

    $uploadedFile = isset($_FILES['source_image']) && is_array($_FILES['source_image']) ? $_FILES['source_image'] : null;
    list($ok, $result) = publicista_prepare_job_engine($id, $uploadedFile);

    if (!$ok) {
        set_flash('error', is_string($result) ? $result : 'No se pudo preparar el motor técnico del trabajo.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    set_flash('ok', 'Origen preparado: imagen guardada, base 1:1 sin deformar creada y descriptor OpenAI generado.');
    redirect_to(publicista_tab_url(array('job' => $id)));
}


function action_run_publicista_image_pipeline() {
    $id = trim((string)request_post('id'));
    $job = $id !== '' ? publicista_job_get($id) : null;
    if (!$job) {
        set_flash('error', 'No se encontró el trabajo de Publicista.');
        redirect_to(publicista_tab_url());
    }
    if (function_exists('publicista_pipeline_is_running') && publicista_pipeline_is_running($job)) {
        set_flash('ok', 'La generación ya está en marcha. No hace falta volver a pulsar el botón.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    $uploadedFile = isset($_FILES['source_image']) && is_array($_FILES['source_image']) ? $_FILES['source_image'] : null;
    set_flash('ok', 'Generación lanzada en segundo plano. Recibirás un aviso cuando termine.');
    $targetUrl = publicista_tab_url(array('job' => $id));
    publicista_finish_redirect_response($targetUrl);

    try {
        list($ok, $result) = publicista_run_image_pipeline($id, $uploadedFile);
        if (!$ok) {
            publicista_mark_job_pipeline_failed($id, is_string($result) ? $result : 'No se pudo completar el pipeline de imágenes de Publicista.');
        }
    } catch (Throwable $e) {
        if (function_exists('bootstrap_runtime_log_exception')) {
            bootstrap_runtime_log_exception('action_run_publicista_image_pipeline_background', $e);
        }
        publicista_mark_job_pipeline_failed($id, 'Error interno al generar el perfil: ' . trim((string)$e->getMessage()));
    }

    exit;
}


function action_regenerate_publicista_candidate() {
    $id = trim((string)request_post('id'));
    $candidateId = trim((string)request_post('candidate_id'));
    $refineText = trim((string)request_post('refine_text'));
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($refineText) > 1200) {
            $refineText = mb_substr($refineText, 0, 1200);
        }
    } else {
        if (strlen($refineText) > 1200) {
            $refineText = substr($refineText, 0, 1200);
        }
    }
    $job = $id !== '' ? publicista_job_get($id) : null;
    if ($job && function_exists('publicista_pipeline_is_running') && publicista_pipeline_is_running($job)) {
        set_flash('ok', 'La generación sigue en curso. Espera a que termine antes de regenerar una candidata concreta.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    // ═══ Guard: evitar duplicados ═══
    if (function_exists('publicista_regen_is_candidate_busy') && publicista_regen_is_candidate_busy($id, $candidateId)) {
        set_flash('ok', 'Esta candidata ya está en la cola de regeneración. Espera a que termine o cancélala primero.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    // ═══ Guard: Pollo.ai ocupado → encolar sin lanzar proceso ═══
    if (function_exists('publicista_pollo_is_busy') && publicista_pollo_is_busy()) {
        // No lanzamos proceso background. Solo encolamos para que el proceso
        // que actualmente ocupa Pollo la procese al terminar (trigger_next).
        if (function_exists('publicista_regen_queue_set_status')) {
            publicista_regen_queue_set_status($id, $candidateId, 'waiting_pollo', '', 5, array('refine_text' => $refineText));
        }
        set_flash('ok', 'Pollo.ai está ocupado con otra generación. Tu candidata se ha encolado y se regenerará automáticamente en cuanto haya turno.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    // ═══ Pollo libre: marcarlo como busy y lanzar ═══
    if (function_exists('publicista_pollo_set_busy') && !publicista_pollo_set_busy($id, $candidateId)) {
        // Otro proceso ganó la carrera justo antes — encolar
        if (function_exists('publicista_regen_queue_set_status')) {
            publicista_regen_queue_set_status($id, $candidateId, 'waiting_pollo', '', 5, array('refine_text' => $refineText));
        }
        set_flash('ok', 'Pollo.ai está ocupado con otra generación. Tu candidata se ha encolado y se regenerará automáticamente en cuanto haya turno.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    set_flash('ok', 'Regeneración lanzada en segundo plano. Recibirás un aviso cuando termine.');
    $targetUrl = publicista_tab_url(array('job' => $id));
    publicista_finish_redirect_response($targetUrl);

    try {
        list($ok, $result) = publicista_regenerate_candidate($id, $candidateId, $refineText);
        $latestJob = publicista_job_get($id) ?: $job;
        if (function_exists('publicista_notify_candidate_regeneration_finished')) {
            publicista_notify_candidate_regeneration_finished($latestJob, $candidateId, $ok, $result);
        }
    } catch (Throwable $e) {
        if (function_exists('bootstrap_runtime_log_exception')) {
            bootstrap_runtime_log_exception('action_regenerate_publicista_candidate_background', $e);
        }
        if (function_exists('publicista_notify_candidate_regeneration_finished')) {
            publicista_notify_candidate_regeneration_finished($job ?: array('id' => $id), $candidateId, false, 'Error interno al regenerar la candidata: ' . trim((string)$e->getMessage()));
        }
    }

    exit;
}


function action_regenerate_publicista_sexy_candidate() {
    $id = trim((string)request_post('id'));
    $candidateId = trim((string)request_post('candidate_id'));
    $refineText = trim((string)request_post('refine_text'));
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($refineText) > 1200) {
            $refineText = mb_substr($refineText, 0, 1200);
        }
    } else {
        if (strlen($refineText) > 1200) {
            $refineText = substr($refineText, 0, 1200);
        }
    }
    $job = $id !== '' ? publicista_job_get($id) : null;
    if ($job && function_exists('publicista_pipeline_is_running') && publicista_pipeline_is_running($job)) {
        set_flash('ok', 'La generación sigue en curso. Espera a que termine antes de regenerar una candidata erótica.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    $queueId = 'sexy_' . $candidateId;

    // ═══ Guard: evitar duplicados ═══
    if (function_exists('publicista_regen_is_candidate_busy') && publicista_regen_is_candidate_busy($id, $queueId)) {
        set_flash('ok', 'Esta candidata ya está en la cola de regeneración. Espera a que termine o cancélala primero.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    // ═══ Guard: Pollo.ai ocupado → encolar sin lanzar proceso ═══
    if (function_exists('publicista_pollo_is_busy') && publicista_pollo_is_busy()) {
        if (function_exists('publicista_regen_queue_set_status')) {
            publicista_regen_queue_set_status($id, $queueId, 'waiting_pollo', '', 5, array('refine_text' => $refineText));
        }
        set_flash('ok', 'Pollo.ai está ocupado con otra generación. Tu candidata erótica se ha encolado y se regenerará automáticamente en cuanto haya turno.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    // ═══ Pollo libre: marcarlo como busy y lanzar ═══
    if (function_exists('publicista_pollo_set_busy') && !publicista_pollo_set_busy($id, $candidateId)) {
        if (function_exists('publicista_regen_queue_set_status')) {
            publicista_regen_queue_set_status($id, $queueId, 'waiting_pollo', '', 5, array('refine_text' => $refineText));
        }
        set_flash('ok', 'Pollo.ai está ocupado con otra generación. Tu candidata erótica se ha encolado y se regenerará automáticamente en cuanto haya turno.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    set_flash('ok', 'Regeneración erótica lanzada en segundo plano.');
    $targetUrl = publicista_tab_url(array('job' => $id));
    publicista_finish_redirect_response($targetUrl);

    try {
        list($ok, $result) = publicista_regenerate_sexy_candidate($id, $candidateId, $refineText);
        $latestJob = publicista_job_get($id) ?: $job;
        if (function_exists('publicista_notify_candidate_regeneration_finished')) {
            publicista_notify_candidate_regeneration_finished($latestJob, $candidateId, $ok, $result);
        }
    } catch (Throwable $e) {
        if (function_exists('bootstrap_runtime_log_exception')) {
            bootstrap_runtime_log_exception('action_regenerate_publicista_sexy_candidate_background', $e);
        }
        if (function_exists('publicista_notify_candidate_regeneration_finished')) {
            publicista_notify_candidate_regeneration_finished($job ?: array('id' => $id), $candidateId, false, 'Error interno al regenerar la candidata erótica: ' . trim((string)$e->getMessage()));
        }
    }

    exit;
}


/**
 * Endpoint de polling ligero. Devuelve JSON con:
 * - queue: estado de la cola de regeneraciones (queued/running/done/error por candidateId)
 * - candidates: snapshot de square_path + filemtime + status de cada candidata
 * - avisos_count: número de avisos activos nuevos (para badge)
 * No requiere CSRF — solo GET con id del job.
 */
function action_poll_publicista_regen_status() {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $id = trim((string)($_GET['id'] ?? $_POST['id'] ?? ''));
    if ($id === '') {
        echo json_encode(array('ok' => false, 'error' => 'Falta id del trabajo.'));
        exit;
    }
    $job = function_exists('publicista_job_get') ? publicista_job_get($id) : null;
    if (!$job) {
        echo json_encode(array('ok' => false, 'error' => 'Trabajo no encontrado.'));
        exit;
    }
    $queue = function_exists('publicista_regen_queue_get') ? publicista_regen_queue_get($id) : array();
    $candidates = is_array($job['candidates'] ?? null) ? $job['candidates'] : array();
    $candidatesOut = array();
    foreach ($candidates as $cand) {
        $candId = trim((string)($cand['id'] ?? ''));
        if ($candId === '') continue;
        $squarePath = trim((string)($cand['square_path'] ?? ''));
        $fsPath = $squarePath !== '' ? BASE_PATH . '/' . ltrim($squarePath, '/') : '';
        $mtime = ($fsPath !== '' && file_exists($fsPath)) ? (int)filemtime($fsPath) : 0;
        $candidatesOut[$candId] = array(
            'square_path' => $squarePath,
            'mtime'       => $mtime,
            'src'         => $mtime > 0 ? $squarePath . '?t=' . $mtime : $squarePath,
            'status'      => trim((string)($cand['status'] ?? '')),
            'round'       => trim((string)($cand['round'] ?? '')),
            'error'       => trim((string)($cand['error'] ?? '')),
        );
    }
    // Add sexy candidates
    $sexyCandidates = is_array($job['sexy_candidates'] ?? null) ? $job['sexy_candidates'] : array();
    $sexyCandidatesOut = array();
    foreach ($sexyCandidates as $cand) {
        $candId = trim((string)($cand['id'] ?? ''));
        if ($candId === '') continue;
        $squarePath = trim((string)($cand['square_path'] ?? ''));
        $fsPath = $squarePath !== '' ? BASE_PATH . '/' . ltrim($squarePath, '/') : '';
        $mtime = ($fsPath !== '' && file_exists($fsPath)) ? (int)filemtime($fsPath) : 0;
        $sexyCandidatesOut[$candId] = array(
            'square_path' => $squarePath,
            'mtime'       => $mtime,
            'src'         => $mtime > 0 ? $squarePath . '?t=' . $mtime : $squarePath,
            'status'      => trim((string)($cand['status'] ?? '')),
            'round'       => trim((string)($cand['round'] ?? '')),
            'error'       => trim((string)($cand['error'] ?? '')),
        );
    }
    // Avisos activos recientes (últimos 30 min)
    $avisosCount = 0;
    if (function_exists('avisos_get_active')) {
        $avisos = avisos_get_active();
        if (is_array($avisos)) {
            $cutoff = time() - 1800;
            foreach ($avisos as $av) {
                $createdAt = trim((string)($av['created_at'] ?? ''));
                $ts = $createdAt !== '' ? strtotime($createdAt) : 0;
                if ($ts >= $cutoff) $avisosCount++;
            }
        }
    }
    echo json_encode(array(
        'ok'              => true,
        'queue'           => $queue,
        'candidates'      => $candidatesOut,
        'sexy_candidates' => $sexyCandidatesOut,
        'avisos_count'    => $avisosCount,
        'ts'              => time(),
    ), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Cancela las entradas en cola (queued) para un job dado.
 * Las entradas en running no se pueden cancelar desde aquí (el proceso PHP ya arrancó).
 */
function action_cancel_publicista_regen_queue() {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    $id = trim((string)($_GET['id'] ?? ''));
    if ($id === '') {
        echo json_encode(array('ok' => false, 'error' => 'Falta id del trabajo.'));
        exit;
    }
    if (!function_exists('publicista_regen_queue_get') || !function_exists('publicista_regen_queue_set_status')) {
        echo json_encode(array('ok' => false, 'error' => 'Funciones de cola no disponibles.'));
        exit;
    }
    $queue = publicista_regen_queue_get($id);
    $cancelled = array();
    foreach ($queue as $candId => $entry) {
        $status = $entry['status'] ?? '';
        if ($status === 'queued' || $status === 'waiting_pollo') {
            publicista_regen_queue_set_status($id, $candId, 'cancelled', 'Cancelado por el usuario');
            $cancelled[] = $candId;
        }
    }
    echo json_encode(array('ok' => true, 'cancelled' => $cancelled), JSON_UNESCAPED_UNICODE);
    exit;
}

function action_refresh_publicista_final_local() {
    $id = trim((string)request_post('id'));
    $finalId = trim((string)request_post('final_id'));
    $mode = trim((string)request_post('mode', 'refresh'));
    $job = $id !== '' ? publicista_job_get($id) : null;
    if (!$job) {
        set_flash('error', 'No se encontró el trabajo de Publicista.');
        redirect_to(publicista_tab_url());
    }

    $usesPollo = function_exists('publicista_job_uses_pollo_model') && publicista_job_uses_pollo_model($job);
    $msg = $usesPollo
        ? 'Refinado lanzado en segundo plano. Recibirás un aviso cuando termine.'
        : ($mode === 'reframe'
            ? 'Rehacer final premium lanzado en segundo plano. Recibirás un aviso cuando termine.'
            : 'Rehacer final lanzado en segundo plano. Recibirás un aviso cuando termine.');
    set_flash('ok', $msg);
    $targetUrl = publicista_tab_url(array('job' => $id));
    publicista_finish_redirect_response($targetUrl);

    try {
        list($ok, $result) = publicista_refresh_final_local_assets($id, $finalId, $mode);
        $latestJob = publicista_job_get($id) ?: $job;
        if (function_exists('publicista_notify_final_refresh_finished')) {
            publicista_notify_final_refresh_finished($latestJob, $finalId, $mode, $ok, $result);
        }
    } catch (Throwable $e) {
        if (function_exists('bootstrap_runtime_log_exception')) {
            bootstrap_runtime_log_exception('action_refresh_publicista_final_local_background', $e);
        }
        if (function_exists('publicista_notify_final_refresh_finished')) {
            publicista_notify_final_refresh_finished($job, $finalId, $mode, false, 'Error interno al refrescar la imagen final: ' . trim((string)$e->getMessage()));
        }
    }

    exit;
}

function action_choose_publicista_final_variant() {
    $id = trim((string)request_post('id'));
    $finalId = trim((string)request_post('final_id'));
    $choice = trim((string)request_post('choice'));
    list($ok, $result) = publicista_set_final_variant_choice($id, $finalId, $choice);
    if (!$ok) {
        set_flash('error', is_string($result) ? $result : 'No se pudo guardar la elección de la final.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }
    set_flash('ok', $choice === 'refined' ? 'La versión refinada pasa a ser la definitiva actual.' : 'Se mantiene la candidata actual como definitiva.');
    redirect_to(publicista_tab_url(array('job' => $id)));
}

function action_mark_publicista_pack_definitive() {
    $id = trim((string)request_post('id'));
    list($ok, $result) = publicista_mark_pack_definitive($id);
    if (!$ok) {
        set_flash('error', is_string($result) ? $result : 'No se pudo marcar el pack como definitivo.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }
    set_flash('ok', 'Perfil marcado como terminado y definitivo.');
    redirect_to(publicista_tab_url(array('job' => $id)));
}

function action_create_publicista_job() {
    $clientaParsed = publicista_parse_clienta_picker_value(request_post('clienta_id'));
    $clientaRef = publicista_find_clienta_any($clientaParsed['id'], $clientaParsed['scope']);
    $clienta = $clientaRef ? ($clientaRef['row'] ?? array()) : null;

    if (!$clientaRef || !$clienta) {
        set_flash('error', 'Selecciona una clienta válida para crear el trabajo de Publicista.');
        redirect_to(publicista_tab_url());
    }

    $clientaNombre = trim((string)($clientaRef['nombre'] ?? ($clienta['nombre'] ?? 'Clienta')));
    $nombreTrabajo = trim((string)request_post('nombre_trabajo'));
    if ($nombreTrabajo === '') {
        $nombreTrabajo = 'Pack ' . $clientaNombre . ' ' . date('Y-m-d');
    }

    $restrictionFlags = publicista_normalize_restriction_flags(request_post('restriction_flags', array()));
    $autoRegenerate = !empty($_POST['auto_regenerate']) ? 1 : 0;

    $job = publicista_job_defaults();
    $job['clienta_id'] = trim((string)($clientaRef['id'] ?? ($clienta['id'] ?? '')));
    $job['clienta_scope'] = trim((string)($clientaRef['scope'] ?? 'lamami'));
    $job['clienta_nombre_snapshot'] = $clientaNombre;
    $job['publish_name'] = trim((string)request_post('publish_name'));
    $job['nombre_trabajo'] = $nombreTrabajo;
    $job['estado'] = 'draft';
    $job['notas'] = trim((string)request_post('notas'));
    $job['physical_notes'] = '';
    $job['services_snapshot'] = trim((string)request_post('services_snapshot', $clientaRef['services'] ?? ''));
    $job['tarifas_snapshot'] = trim((string)request_post('tarifas_snapshot', $clientaRef['tarifas'] ?? ''));
    $job['localidad_snapshot'] = trim((string)request_post('localidad_snapshot', $clientaRef['localidad'] ?? ''));
    $job['provincia_snapshot'] = trim((string)request_post('provincia_snapshot', $clientaRef['provincia'] ?? ''));
    $job['workflow'] = array_merge(isset($job['workflow']) && is_array($job['workflow']) ? $job['workflow'] : array(), array(
        'restrictions_text' => trim((string)request_post('restrictions_text')),
        'restriction_flags' => $restrictionFlags,
        'auto_regenerate' => $autoRegenerate,
        'pack_final' => 0,
        'pack_finalized_at' => '',
        'pack_final_note' => '',
    ));
    $job['copy_pack'] = array_merge(isset($job['copy_pack']) && is_array($job['copy_pack']) ? $job['copy_pack'] : array(), array(
        'desired_tone' => trim((string)request_post('copy_tone', 'equilibrado')),
    ));
    $job['production_params'] = function_exists('publicista_normalize_outfit_params')
        ? publicista_normalize_outfit_params($_POST)
        : array();

    // Guardar modelo de imagen seleccionado (Pollo.ai por defecto)
    $imageModelSelector = trim((string)request_post('image_model_selector', 'pollo-image-v2'));
    if (function_exists('publicista_is_pollo_model') && publicista_is_pollo_model($imageModelSelector)) {
        $job['models']['image'] = $imageModelSelector;
    }

    $uploadedFile = isset($_FILES['source_image']) && is_array($_FILES['source_image']) ? $_FILES['source_image'] : null;
    if (!$uploadedFile || (int)($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        set_flash('error', 'Para crear el producto necesitas subir una imagen original.');
        redirect_to(publicista_tab_url());
    }

    list($ok, $result) = publicista_job_save($job);
    if (!$ok) {
        set_flash('error', is_string($result) ? $result : 'No se pudo crear el trabajo de Publicista.');
        redirect_to(publicista_tab_url());
    }

    $savedJob = is_array($result) ? $result : publicista_job_get($job['id']);

    // Guardar fotos reales subidas junto con el source_image
    $realPhotos = isset($_FILES['real_photos']) && is_array($_FILES['real_photos']) ? $_FILES['real_photos'] : null;
    if ($realPhotos && !empty($realPhotos['tmp_name'])) {
        $tmpNames = is_array($realPhotos['tmp_name']) ? $realPhotos['tmp_name'] : array();
        $hasFile = false;
        foreach ($tmpNames as $t) {
            if ((string)$t !== '') { $hasFile = true; break; }
        }
        if ($hasFile) {
            list($okReal, $resultReal) = publicista_attach_real_photos($savedJob['id'], $realPhotos);
            if ($okReal && is_array($resultReal)) {
                $savedJob = $resultReal;
            }
        }
    }

    if (!$savedJob || trim((string)($savedJob['id'] ?? '')) === '') {
        set_flash('error', 'Se creó el trabajo, pero no se pudo preparar la generación.');
        redirect_to(publicista_tab_url());
    }

    set_flash('ok', 'Trabajo creado. La generación de imágenes se ha lanzado en segundo plano y recibirás un aviso cuando termine.');
    $targetUrl = publicista_tab_url(array('job' => $savedJob['id']));
    publicista_finish_redirect_response($targetUrl);

    try {
        list($okPipeline, $pipelineResult) = publicista_run_image_pipeline($savedJob['id'], $uploadedFile);
        if (!$okPipeline) {
            publicista_mark_job_pipeline_failed($savedJob['id'], is_string($pipelineResult) ? $pipelineResult : 'Se creó el trabajo, pero falló la generación inicial.');
        }
    } catch (Throwable $e) {
        if (function_exists('bootstrap_runtime_log_exception')) {
            bootstrap_runtime_log_exception('action_create_publicista_job_background', $e);
        }
        publicista_mark_job_pipeline_failed($savedJob['id'], 'Error interno al generar el perfil: ' . trim((string)$e->getMessage()));
    }

    exit;
}

function action_duplicate_publicista_job() {
    $id = trim((string)request_post('id'));
    list($ok, $result) = publicista_duplicate_job($id);
    if (!$ok) {
        set_flash('error', is_string($result) ? $result : 'No se pudo duplicar el trabajo de Publicista.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }
    $newId = is_array($result) ? trim((string)($result['id'] ?? '')) : '';
    set_flash('ok', 'Trabajo duplicado como base.');
    redirect_to(publicista_tab_url(array('job' => $newId !== '' ? $newId : $id)));
}

function action_apply_publicista_manual_blur() {
    header('Content-Type: application/json; charset=utf-8');
    $id = trim((string)request_post('id'));
    $finalId = trim((string)request_post('final_id'));
    $bx = (float)request_post('bx', '0.2');
    $by = (float)request_post('by', '0.05');
    $bw = (float)request_post('bw', '0.6');
    $bh = (float)request_post('bh', '0.35');
    $intensity = (int)request_post('intensity', '8');

    if ($id === '' || $finalId === '') {
        echo json_encode(array('ok' => false, 'error' => 'Parámetros incompletos.'));
        exit;
    }

    list($ok, $result) = publicista_apply_manual_blur_to_final($id, $finalId, $bx, $by, $bw, $bh, $intensity);
    if (!$ok) {
        echo json_encode(array('ok' => false, 'error' => is_string($result) ? $result : 'Error al aplicar el blur manual.'));
        exit;
    }

    echo json_encode(array(
        'ok' => true,
        'final_path' => $result['final_path'] ?? '',
        'preview_path' => $result['preview_path'] ?? '',
        'manual_blur_applied' => !empty($result['manual_blur_applied']),
        'manual_blur_intensity' => (int)($result['manual_blur_intensity'] ?? 0),
    ));
    exit;
}

function action_apply_publicista_manual_blur_real() {
    header('Content-Type: application/json; charset=utf-8');
    $id = trim((string)request_post('id'));
    $photoId = trim((string)request_post('photo_id'));
    $bx = (float)request_post('bx', '0.2');
    $by = (float)request_post('by', '0.05');
    $bw = (float)request_post('bw', '0.6');
    $bh = (float)request_post('bh', '0.35');
    $intensity = (int)request_post('intensity', '8');

    if ($id === '' || $photoId === '') {
        echo json_encode(array('ok' => false, 'error' => 'Parámetros incompletos.'));
        exit;
    }

    list($ok, $result) = publicista_apply_manual_blur_to_real_photo($id, $photoId, $bx, $by, $bw, $bh, $intensity);
    if (!$ok) {
        echo json_encode(array('ok' => false, 'error' => is_string($result) ? $result : 'Error al aplicar el blur manual.'));
        exit;
    }

    echo json_encode(array(
        'ok' => true,
        'stored_path' => $result['stored_path'] ?? '',
        'preview_path' => $result['preview_path'] ?? '',
        'manual_blur_applied' => !empty($result['manual_blur_applied']),
        'manual_blur_intensity' => (int)($result['manual_blur_intensity'] ?? 0),
    ));
    exit;
}

function action_apply_publicista_manual_blur_source() {
    header('Content-Type: application/json; charset=utf-8');
    $id = trim((string)request_post('id'));
    $bx = (float)request_post('bx', '0.2');
    $by = (float)request_post('by', '0.05');
    $bw = (float)request_post('bw', '0.6');
    $bh = (float)request_post('bh', '0.35');
    $intensity = (int)request_post('intensity', '8');

    if ($id === '') {
        echo json_encode(array('ok' => false, 'error' => 'Parámetros incompletos.'));
        exit;
    }

    list($ok, $result) = publicista_apply_manual_blur_to_source($id, $bx, $by, $bw, $bh, $intensity);
    if (!$ok) {
        echo json_encode(array('ok' => false, 'error' => is_string($result) ? $result : 'Error al aplicar el blur manual.'));
        exit;
    }

    echo json_encode(array(
        'ok' => true,
        'stored_path' => $result['stored_path'] ?? '',
        'preview_path' => $result['preview_path'] ?? '',
        'manual_blur_applied' => !empty($result['manual_blur_applied']),
        'manual_blur_intensity' => (int)($result['manual_blur_intensity'] ?? 0),
    ));
    exit;
}

function action_voice_command() {
    $commandText = trim((string)request_post('voice_command_text'));
    $contextJson = trim((string)request_post('voice_context_json'));
    $alternativesJson = trim((string)request_post('voice_alternatives_json'));
    $rawTranscript = trim((string)request_post('voice_raw_transcript'));
    $modoEureka = (trim((string)request_post('voice_modo_eureka')) === '1');
    $context = array();
    $speechMeta = array(
        'source' => trim((string)request_post('voice_input_source')),
        'alternatives' => array(),
        'raw_transcript' => $rawTranscript,
        'modo_eureka' => $modoEureka,
    );
    $interaction = array(
        'pending_token' => trim((string)request_post('voice_pending_token')),
        'followup_action' => trim((string)request_post('voice_followup_action')),
        'followup_value' => trim((string)request_post('voice_followup_value')),
    );

    if ($contextJson !== '') {
        $decoded = json_decode($contextJson, true);
        if (is_array($decoded)) {
            $context = $decoded;
        }
    }

    if ($alternativesJson !== '') {
        $decodedAlternatives = json_decode($alternativesJson, true);
        if (is_array($decodedAlternatives)) {
            $speechMeta['alternatives'] = array_values(array_filter(array_map(function ($item) {
                return is_string($item) ? trim($item) : '';
            }, $decodedAlternatives), function ($item) {
                return $item !== '';
            }));
        }
    }

    $response = voice_handle_command($commandText, $context, $interaction, $speechMeta);
    voice_json_response($response);
}

function action_debug_voice() {
    $step = trim((string)request_post('step'));
    $detail = trim((string)request_post('detail'));
    $line = date('Y-m-d H:i:s') . "\t" . ($step ?? '') . "\t" . substr(($detail ?? ''), 0, 500) . "\n";
    file_put_contents(__DIR__ . '/../data/voice_debug.log', $line, FILE_APPEND | LOCK_EX);
    voice_json_response(voice_build_response(array(
        'ok' => true, 'stage' => 'debug', 'message' => 'Telemetría registrada', 'step' => $step
    )));
}

function action_delete_generic($file, $message, $redirect) {
    $id = request_post('id');
    if ($id !== '') {
        storage_delete($file, $id);
    }
    set_flash('ok', $message);
    redirect_to($redirect);
}

function action_save_interesada() {
    $id = request_post('id');
    if ($id === '') $id = generate_id('int');

    $existing = storage_find_by_id('interesadas.json', $id);

    $row = array(
        'id' => $id,
        'telefono' => trim(request_post('telefono')),
        'observaciones' => trim(request_post('observaciones')),
        'movil_origen' => trim(request_post('movil_origen')),
        'estado' => trim(request_post('estado', 'nueva')),
        'cliente_id' => trim(request_post('cliente_id')),
        'updated_at' => now_datetime()
    );

    if ($existing && isset($existing['created_at'])) {
        $row['created_at'] = $existing['created_at'];
    } else {
        $row['created_at'] = now_datetime();
    }

    if ($existing && isset($existing['convertida_at'])) {
        $row['convertida_at'] = $existing['convertida_at'];
    }

    storage_upsert('interesadas.json', $row);
    set_flash('ok', 'Interesada guardada correctamente.');
    //redirect_to('index.php?page=interesadas');
    redirect_to(lamami_tab_url('interesadas'));
}

function action_set_interesada_estado() {
    $id = request_post('id');
    $estado = trim(request_post('estado'));
    $row = storage_find_by_id('interesadas.json', $id);

    if (!$row) {
        set_flash('error', 'Interesada no encontrada.');
        redirect_to(lamami_tab_url('interesadas'));
    }

    $row['estado'] = $estado;
    $row['updated_at'] = now_datetime();
    storage_upsert('interesadas.json', $row);

    $fb = interesada_state_feedback($estado);
    set_flash($fb[0], $fb[1], $fb[2]);
    redirect_to(lamami_tab_url('clientas'));
}

function action_convert_interesada() {
    $interesadaId = request_post('interesada_id');
    $interesada = storage_find_by_id('interesadas.json', $interesadaId);

    if (!$interesada) {
        set_flash('error', 'No se encontró la interesada.');
        redirect_to('index.php?page=interesadas');
    }

    $clienteId = generate_id('cli');
    $cliente = array(
        'id' => $clienteId,
        'nombre' => trim(request_post('nombre')),
        'telefono' => trim(request_post('telefono')),
        'localidad' => trim(request_post('localidad')),
        'provincia' => trim(request_post('provincia')),
        'fecha_alta' => request_post('fecha_alta', business_today_date()),
        'precio_alta' => to_float(request_post('precio_alta'), 0),
        'modo_pago' => trim(request_post('modo_pago')),
        'notas' => trim(request_post('notas')),
        'estado' => 'alta',
        'created_at' => now_datetime(),
        'updated_at' => now_datetime(),
        'source_interesada_id' => $interesadaId
    );

    storage_upsert('clientes.json', $cliente);

    $interesadas = storage_read('interesadas.json');
    foreach ($interesadas as $i => $item) {
        if (isset($item['id']) && $item['id'] === $interesadaId) {
            $interesadas[$i]['estado'] = 'convertida';
            $interesadas[$i]['cliente_id'] = $clienteId;
            $interesadas[$i]['convertida_at'] = now_datetime();
            $interesadas[$i]['updated_at'] = now_datetime();
            break;
        }
    }
    storage_write('interesadas.json', $interesadas);

    set_flash('ok', '¡Excelente! Interesada convertida en clienta.', 'celebrate');
    redirect_to('index.php?page=clientas');
}

function action_save_clienta() {
    $id = request_post('id');
    $existing = storage_find_by_id('clientes.json', $id);

    if (!$existing) {
        set_flash('error', 'No se puede crear una clienta directamente. Debe venir de una interesada.');
        redirect_to(lamami_tab_url('interesadas'));
    }

    $row = array(
        'id' => $id,
        'nombre' => trim(request_post('nombre')),
        'telefono' => trim(request_post('telefono')),
        'localidad' => trim(request_post('localidad')),
        'provincia' => trim(request_post('provincia')),
        'fecha_alta' => request_post('fecha_alta', $existing['fecha_alta']),
        'precio_alta' => to_float(request_post('precio_alta'), $existing['precio_alta']),
        'modo_pago' => trim(request_post('modo_pago')),
        'notas' => trim(request_post('notas')),
        'estado' => trim(request_post('estado', $existing['estado'])),
        'ubicacion_maps' => trim(request_post('ubicacion_maps')),
        'zona' => trim(request_post('zona')),
        'servicios' => trim(request_post('servicios')),
        'tarifas' => trim(request_post('tarifas')),
        'updated_at' => now_datetime()
    );

    $row['created_at'] = isset($existing['created_at']) ? $existing['created_at'] : now_datetime();
    $row['source_interesada_id'] = isset($existing['source_interesada_id']) ? $existing['source_interesada_id'] : '';
    if (isset($existing['fecha_baja'])) $row['fecha_baja'] = $existing['fecha_baja'];

    storage_upsert('clientes.json', $row);
    set_flash('ok', 'Clienta actualizada.');
    redirect_to(lamami_tab_url('clientas', array('edit' => $id)));
}

function action_baja_clienta() {
    $id = request_post('id');
    $existing = storage_find_by_id('clientes.json', $id);
    if (!$existing) {
        set_flash('error', 'Clienta no encontrada.');
        redirect_to(lamami_tab_url('clientas'));
    }

    if (clienta_has_linked_bot($id)) {
        set_flash('error', 'No se puede dar de baja mientras tenga un bot vinculado.');
        redirect_to(lamami_tab_url('clientas', array('edit' => $id)));
    }

    $existing['estado'] = 'baja';
    $existing['fecha_baja'] = request_post('fecha_baja', business_today_date());
    $existing['updated_at'] = now_datetime();
    storage_upsert('clientes.json', $existing);
    set_flash('ok', 'Clienta dada de baja.');
    redirect_to(lamami_tab_url('clientas', array('edit' => $id)));
}

function action_alta_clienta() {
    $id = request_post('id');
    $existing = storage_find_by_id('clientes.json', $id);
    if (!$existing) {
        set_flash('error', 'Clienta no encontrada.');
        redirect_to(lamami_tab_url('clientas'));
    }
    $existing['estado'] = 'alta';
    $existing['updated_at'] = now_datetime();
    storage_upsert('clientes.json', $existing);
    set_flash('ok', 'Clienta reactivada.');
    redirect_to(lamami_tab_url('clientas', array('edit' => $id)));
}

function action_save_bot() {
    $id = request_post('id');
    if ($id === '') $id = generate_id('bot');

    list($linkedType, $linkedId) = bot_parse_linked_ref(request_post('linked_ref'));

    $row = array(
        'id' => $id,
        'nombre_bot' => trim(request_post('nombre_bot')),
        'telefono_bot' => trim(request_post('telefono_bot')),
        'waha_port' => trim(request_post('waha_port')),
        'linked_type' => $linkedType,
        'linked_id' => $linkedId,
        'cliente_id' => $linkedType === 'lamami_clienta' ? $linkedId : '',
        'server_ip' => trim(request_post('server_ip', '100.113.76.93')),
        'bot_mode' => trim(request_post('bot_mode', 'multiple')),
        'ubicacion_maps' => trim(request_post('ubicacion_maps')),
        'zona' => trim(request_post('zona')),
        'servicios' => trim(request_post('servicios')),
        'tarifas' => trim(request_post('tarifas')),
        'estado' => trim(request_post('estado')),
        'updated_at' => now_datetime()
    );

    $existing = storage_find_by_id('bots.json', $id);
    if ($existing) {
        if (!isset($row['server_ip']) || $row['server_ip'] === '') {
            $row['server_ip'] = $existing['server_ip'] ?? '100.113.76.93';
        }
        if (!isset($row['bot_mode']) || $row['bot_mode'] === '') {
            $row['bot_mode'] = $existing['bot_mode'] ?? 'multiple';
        }
    }
    if ($existing && isset($existing['created_at'])) {
        $row['created_at'] = $existing['created_at'];
    } else {
        $row['created_at'] = now_datetime();
    }

    if ($existing && isset($existing['generated_assets']) && is_array($existing['generated_assets'])) {
        $linkChanged = (bot_linked_type($existing) !== $linkedType) || (bot_linked_id($existing) !== $linkedId);
        if (!$linkChanged) {
            $row['generated_assets'] = $existing['generated_assets'];
        }
    }

    storage_upsert('bots.json', $row);
    set_flash('ok', 'Bot guardado correctamente.');
    redirect_to('index.php?page=bots&edit=' . urlencode($id));
}

function action_save_lamamibot() {
    $telefonosRows = storage_read('telefonos.json');
    $telefonosIdx = array();
    foreach ($telefonosRows as $row) {
        $id = trim((string)($row['id'] ?? ''));
        if ($id !== '') {
            $telefonosIdx[$id] = $row;
        }
    }

    $clientasRows = get_active_clientas();
    $clientasIdx = array();
    foreach ($clientasRows as $row) {
        $id = trim((string)($row['id'] ?? ''));
        if ($id !== '') {
            $clientasIdx[$id] = $row;
        }
    }

    $telefonosIdsRaw = isset($_POST['telefonos_ids']) && is_array($_POST['telefonos_ids'])
        ? $_POST['telefonos_ids']
        : array();

    $clientasIdsRaw = isset($_POST['clientas_ids']) && is_array($_POST['clientas_ids'])
        ? $_POST['clientas_ids']
        : array();

    $telefonosIds = array();
    foreach ($telefonosIdsRaw as $id) {
        $id = trim((string)$id);
        if ($id === '') continue;
        if (!isset($telefonosIdx[$id])) continue;
        if (!in_array($id, $telefonosIds, true)) {
            $telefonosIds[] = $id;
        }
    }

    $clientasIds = array();
    foreach ($clientasIdsRaw as $id) {
        $id = trim((string)$id);
        if ($id === '') continue;
        if (!isset($clientasIdx[$id])) continue;
        if (!in_array($id, $clientasIds, true)) {
            $clientasIds[] = $id;
        }
    }

    $nombreBot = trim(request_post('nombre_bot', 'LamamiBot'));
    if ($nombreBot === '') {
        $nombreBot = 'LamamiBot';
    }

    $estado = trim(request_post('estado'));

    $cfg = array_merge(lamamibot_get(), array(
        'nombre_bot' => $nombreBot,
        'estado' => $estado,
        'telefonos_ids' => $telefonosIds,
        'clientas_ids' => $clientasIds,
        'girlsconf_json_path' => lamamibot_girlsconf_json_path(),
        'girlsconf_base_url' => lamamibot_girlsconf_base_url(),
    ));

    $sync = lamamibot_sync_girlsconf($clientasIds);
    $syncSummary = lamamibot_build_sync_summary($telefonosIds, $clientasIds, $sync);

    $cfg['last_sync_at'] = now_datetime();
    $cfg['last_sync_summary'] = $syncSummary;

    $genOk = false;
    $genResult = null;

    if (!empty($sync['ok'])) {
        list($genOk, $genResult) = lamamibot_generate_bot_bundle($cfg);
        if ($genOk) {
            $cfg['generated_assets'] = $genResult;
        }
    }

    lamamibot_save($cfg);

    if (empty($sync['ok'])) {
        set_flash(
            'error',
            'LamamiBot guardado, pero la sincronización con girlsconf_lamamidef falló: ' . ($sync['message'] ?? 'Error desconocido.')
        );
        redirect_to(lamami_tab_url('lamamibot'));
    }

    if (!$genOk) {
        set_flash(
            'error',
            $syncSummary . ' · La configuración se guardó, pero no se pudo regenerar el JSON del bot: ' . (is_string($genResult) ? $genResult : 'Error desconocido.')
        );
        redirect_to(lamami_tab_url('lamamibot'));
    }

    $msg = $syncSummary . ' · ' . ($genResult['summary'] ?? 'JSON del bot regenerado.');
    if (!empty($genResult['warnings']) && is_array($genResult['warnings'])) {
        $msg .= ' · Avisos: ' . implode(' | ', $genResult['warnings']);
    }

    set_flash('ok', $msg);
    redirect_to(lamami_tab_url('lamamibot'));
}

function action_generate_lamamibot_assets() {
    $cfg = lamamibot_get();

    list($ok, $result) = lamamibot_generate_bot_bundle($cfg);

    if (!$ok) {
        set_flash('error', 'No se pudo regenerar el JSON de LamamiBot: ' . $result);
        redirect_to(lamami_tab_url('lamamibot'));
    }

    $cfg['generated_assets'] = $result;
    lamamibot_save($cfg);

    $msg = $result['summary'] ?? 'JSON del bot regenerado.';
    if (!empty($result['warnings']) && is_array($result['warnings'])) {
        $msg .= ' · Avisos: ' . implode(' | ', $result['warnings']);
    }

    set_flash('ok', $msg);
    redirect_to(lamami_tab_url('lamamibot'));
}

function action_set_lamamibot_runtime_mode() {
    $mode = strtolower(trim(request_post('mode')));
    if ($mode !== 'start' && $mode !== 'stop') {
        set_flash('error', 'Modo no válido. Usa start o stop.');
        redirect_to(lamami_tab_url('lamamibot'));
    }

    $cfg = lamamibot_get();
    $runtimeBot = array(
        'nombre_bot' => function_exists('lamamibot_bot_slug') ? lamamibot_bot_slug($cfg) : (string)($cfg['nombre_bot'] ?? 'lamamibot'),
        'generated_assets' => is_array($cfg['generated_assets'] ?? null) ? $cfg['generated_assets'] : array(),
    );

    list($ok, $written, $errors) = bot_runtime_set_mode($runtimeBot, $mode);

    if (!$ok) {
        set_flash('error', 'No se pudo cambiar el estado runtime de LamamiBot. ' . implode(' | ', $errors));
        redirect_to(lamami_tab_url('lamamibot'));
    }

    $label = ($mode === 'start') ? 'encendido' : 'apagado';
    $msg = 'LamamiBot ' . $label . ' correctamente.';
    if (!empty($written)) {
        $msg .= ' Mode file actualizado en: ' . implode(' | ', $written);
    }

    set_flash('ok', $msg);
    redirect_to(lamami_tab_url('lamamibot'));
}

function build_lead_row($id, $clientaId, $fechaHora, $precioLead, $observaciones) {
    $clientas = clientes_index();
    if (!isset($clientas[$clientaId])) {
        return array(false, 'Clienta no encontrada.');
    }

    $clienta = $clientas[$clientaId];
    $bot = get_clienta_current_bot($clientaId);

    if (!$bot) {
        return array(false, 'Esta clienta no tiene bot vinculado. No se puede registrar el lead.');
    }

    return array(true, array(
        'id' => $id,
        'cliente_id' => $clientaId,
        'cliente_nombre' => isset($clienta['nombre']) ? $clienta['nombre'] : '',
        'bot_id' => isset($bot['id']) ? $bot['id'] : '',
        'bot_nombre' => isset($bot['nombre_bot']) ? $bot['nombre_bot'] : '',
        'fecha_hora' => $fechaHora,
        'precio_lead' => $precioLead,
        'observaciones' => $observaciones,
        'updated_at' => now_datetime()
    ));
}

function action_quick_lead() {
    $settings = settings_get();
    $defaultPrice = isset($settings['lead_default_price']) ? $settings['lead_default_price'] : 10;
    $id = generate_id('lead');
    $clientaId = trim(request_post('cliente_id'));

    list($ok, $result) = build_lead_row(
        $id,
        $clientaId,
        request_post('fecha_hora', today_datetime_local()),
        request_post('precio_lead') === '' ? (float)$defaultPrice : to_float(request_post('precio_lead')),
        trim(request_post('observaciones'))
    );

    if (!$ok) {
        set_flash('error', $result);
        redirect_to(lamami_tab_url('clientas', array('edit' => $clientaId)));
    }

    $row = $result;
    $row['created_at'] = now_datetime();

    storage_upsert('leads.json', $row);
    set_flash('ok', lead_success_message($row['precio_lead']), 'money');
    redirect_to(lamami_tab_url('clientas', array('edit' => $clientaId)));
}

function action_delete_lead() {
    $id = request_post('id');
    $clientaId = request_post('clienta_id');
    if ($id !== '') {
        storage_delete('leads.json', $id);
    }
    set_flash('ok', 'Lead eliminado.');
    redirect_to(lamami_tab_url('clientas', array('edit' => $clientaId)));
}
function action_generate_bot_assets() {
    $id = trim(request_post('id'));
    if ($id === '') {
        set_flash('error', 'Falta el ID del bot.');
        redirect_to('index.php?page=bots');
    }

    $bot = storage_find_by_id('bots.json', $id);
    if (!$bot) {
        set_flash('error', 'Bot no encontrado.');
        redirect_to('index.php?page=bots');
    }

    $linkedType = bot_linked_type($bot);
    $linkedId = bot_linked_id($bot);
    if ($linkedType === '' || $linkedId === '') {
        set_flash('error', 'Este bot no tiene ninguna ficha vinculada.');
        redirect_to('index.php?page=bots&edit=' . urlencode($id));
    }

    if ($linkedType === 'lamami_clienta') {
        $linkedRow = storage_find_by_id('clientes.json', $linkedId);
        if (!$linkedRow) {
            set_flash('error', 'La clienta LaMami vinculada no existe.');
            redirect_to('index.php?page=bots&edit=' . urlencode($id));
        }
        list($ok, $result) = lamami_generate_bot_bundle($bot, $linkedRow);
    } elseif ($linkedType === 'casawasap_cliente') {
        $linkedRow = storage_find_by_id('casawasap_contactos.json', $linkedId);
        if (!$linkedRow) {
            set_flash('error', 'El cliente CasaWasap vinculado no existe.');
            redirect_to('index.php?page=bots&edit=' . urlencode($id));
        }
        list($ok, $result) = casawasap_generate_bot_bundle($bot, $linkedRow);
    } else {
        set_flash('error', 'Tipo de vínculo del bot no soportado.');
        redirect_to('index.php?page=bots&edit=' . urlencode($id));
    }

    if (!$ok) {
        set_flash('error', $result);
        redirect_to('index.php?page=bots&edit=' . urlencode($id));
    }

    $bot['generated_assets'] = $result;
    $bot['updated_at'] = now_datetime();

    storage_upsert('bots.json', $bot);

    $summary = trim((string)($result['summary'] ?? ''));
    set_flash('ok', $summary !== '' ? $summary : 'Pack del bot generado correctamente.');
    redirect_to('index.php?page=bots&edit=' . urlencode($id));
}


function action_set_bot_runtime_mode() {
    $id = trim(request_post('id'));
    $mode = trim(request_post('mode', 'start'));
    $redirect = trim(request_post('redirect', 'index.php?page=bots'));

    if ($id === '') {
        set_flash('error', 'Falta el ID del bot.');
        redirect_to($redirect);
    }

    $bot = storage_find_by_id('bots.json', $id);
    if (!$bot) {
        set_flash('error', 'No se encontró el bot.');
        redirect_to($redirect);
    }

    list($ok, $written, $errors) = bot_runtime_set_mode($bot, $mode);
    if (!$ok) {
        $hint = ' Revisa permisos/propietario del fichero .bot_mode y de la carpeta /srv/n8n_data. Si el archivo lo creó n8n con otro dueño, este código ya intenta recrearlo, pero si la carpeta no es escribible por PHP habrá que corregir permisos una sola vez en el servidor.';
        set_flash('error', 'No se pudo cambiar el estado runtime del bot. ' . implode(' | ', $errors) . $hint);
        redirect_to($redirect);
    }

    $label = strtolower($mode) === 'stop' ? 'apagado' : 'encendido';
    set_flash('ok', 'Bot ' . ($bot['nombre_bot'] ?? 'sin nombre') . ' ' . $label . ' correctamente.');
    redirect_to($redirect);
}

function action_set_dashboard_external_bot_runtime_mode() {
    $mode = trim(request_post('mode', 'start'));
    $redirect = trim(request_post('redirect', 'index.php?page=dashboard'));
    $bot = dashboard_external_bot_virtual();

    list($ok, $written, $errors) = bot_runtime_set_mode($bot, $mode);
    if (!$ok) {
        $hint = ' Revisa permisos/propietario del fichero .bot_mode y de la carpeta /srv/n8n_data. Si el archivo lo creó n8n con otro dueño, este código ya intenta recrearlo, pero si la carpeta no es escribible por PHP habrá que corregir permisos una sola vez en el servidor.';
        set_flash('error', 'No se pudo cambiar el estado runtime del bot externo. ' . implode(' | ', $errors) . $hint);
        redirect_to($redirect);
    }

    $label = strtolower($mode) === 'stop' ? 'apagado' : 'encendido';
    set_flash('ok', 'Bot externo ' . $label . ' correctamente.');
    redirect_to($redirect);
}

function action_save_josue_text() {
    $tab = trim(request_post('tab'));
    $allowed = array('publias', 'captacion', 'notas', 'waha');

    if (!in_array($tab, $allowed, true)) {
        set_flash('error', 'Subsección no válida.');
        redirect_to('index.php?page=josue');
    }

    $text = trim(request_post('text'));
    $settings = storage_read('settings.json');
    $settings[$tab . '_text'] = $text;
    $settings['updated_at'] = now_datetime();
    storage_write('settings.json', $settings);

    set_flash('ok', 'Texto guardado correctamente.');
    redirect_to('index.php?page=josue&tab=' . urlencode($tab));
}

function configm_override_types_for_autoroute() {
    return array(
        'attention',
        'attention:unattended',
        'attention:attended_24h',
        'attention:attended_48h',
        'overdue',
        'integrity',
        'inactivity',
        'performance',
        'strategic',
        'events',
        'milestones',
        'recurring',
        'recurring:destacamos_publish',
        'recurring:mundosex_publish',
    );
}

function configm_sender_candidates_for_autoroute() {
    if (function_exists('configm_telefonos_sender_candidates')) {
        $rows = (array)configm_telefonos_sender_candidates();
        $out = array();
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $id = trim((string)($row['id'] ?? ''));
            $phone = trim((string)($row['phone'] ?? ''));
            $value = $id !== '' ? $id : $phone;
            if ($value === '') continue;
            $out[] = $value;
        }
        if (!empty($out)) return array_values($out);
    }

    $rows = storage_read('telefonos.json');
    $out = array();
    foreach ((array)$rows as $row) {
        if (!is_array($row)) continue;
        $id = trim((string)($row['id'] ?? ''));
        $phone = trim((string)($row['tfono'] ?? ($row['telefono'] ?? '')));
        $value = $id !== '' ? $id : $phone;
        if ($value === '') continue;
        $out[] = $value;
    }
    return array_values($out);
}

function configm_default_override_assignments($lineValues) {
    $lineValues = array_values(array_filter((array)$lineValues, function($v) {
        return trim((string)$v) !== '';
    }));
    if (empty($lineValues)) return array();

    $lineA = $lineValues[0];
    $lineB = $lineValues[count($lineValues) > 1 ? 1 : 0];
    $lineC = $lineValues[count($lineValues) > 2 ? 2 : (count($lineValues) > 1 ? 1 : 0)];

    return array(
        // Críticos / importantes
        'attention' => $lineA,
        'attention:unattended' => $lineA,
        'attention:attended_24h' => $lineA,
        'attention:attended_48h' => $lineA,
        'overdue' => $lineA,
        'integrity' => $lineA,
        'inactivity' => $lineA,

        // Seguimiento negocio
        'performance' => $lineB,
        'strategic' => $lineB,

        // Operativo / bajo valor inmediato
        'events' => $lineC,
        'milestones' => $lineC,
        'recurring' => $lineC,
        'recurring:destacamos_publish' => $lineC,
        'recurring:mundosex_publish' => $lineC,
    );
}

function action_save_configm() {
    $settings = storage_read('settings.json');
    $defaults = is_file(BASE_PATH . '/avisos_config.php') ? (require BASE_PATH . '/avisos_config.php') : array();

    $config = array();

    foreach ($defaults as $key => $defaultValue) {
        if ($key === 'whatsapp_sender_key') {
            continue;
        }
        if ($key === 'whatsapp_target_phones') {
            continue;
        }

        $raw = request_post($key, $defaultValue);

        if (is_int($defaultValue)) {
            $config[$key] = (int)$raw;
        } elseif (is_float($defaultValue)) {
            $config[$key] = (float)str_replace(',', '.', (string)$raw);
        } else {
            $config[$key] = is_string($raw) ? trim($raw) : $raw;
        }
    }

    $targetPhones = trim((string)request_post('whatsapp_target_phones', "654464023\n641993776"));

    $config['whatsapp_target_phones'] = $targetPhones;

    $noiseProfile = trim((string)request_post('alerts_noise_profile', 'balanceado'));
    if (!in_array($noiseProfile, array('conservador', 'balanceado', 'agresivo'), true)) {
        $noiseProfile = 'balanceado';
    }
    $config['alerts_noise_profile'] = $noiseProfile;

    $postedOverrideMap = request_post('whatsapp_sender_override_map', array());
    $postedOverrideMap = is_array($postedOverrideMap) ? $postedOverrideMap : array();
    $cleanOverrideMap = array();
    foreach ($postedOverrideMap as $rawKey => $rawValue) {
        $mapKey = strtolower(trim((string)$rawKey));
        $mapValue = trim((string)$rawValue);
        if ($mapKey === '' || $mapValue === '') continue;
        $cleanOverrideMap[$mapKey] = $mapValue;
    }

    $legacyExtraRaw = trim((string)request_post('whatsapp_sender_overrides_legacy_extra', ''));
    $legacyMap = array();
    if ($legacyExtraRaw !== '') {
        $lines = preg_split('/\r\n|\r|\n/', $legacyExtraRaw);
        foreach ((array)$lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '=') === false) continue;
            list($k, $v) = array_map('trim', explode('=', $line, 2));
            $k = strtolower($k);
            if ($k === '' || $v === '') continue;
            $legacyMap[$k] = $v;
        }
    }

    $finalOverrideMap = array_merge($legacyMap, $cleanOverrideMap);

    // Autocompletado inteligente: si algún tipo clave no se asignó manualmente,
    // lo repartimos por líneas existentes para mantener un routing estable.
    $defaultAssignments = configm_default_override_assignments(configm_sender_candidates_for_autoroute());
    foreach (configm_override_types_for_autoroute() as $typeKey) {
        $normalizedType = strtolower(trim((string)$typeKey));
        if ($normalizedType === '') continue;
        if (!isset($finalOverrideMap[$normalizedType]) || trim((string)$finalOverrideMap[$normalizedType]) === '') {
            if (!empty($defaultAssignments[$normalizedType])) {
                $finalOverrideMap[$normalizedType] = trim((string)$defaultAssignments[$normalizedType]);
            }
        }
    }

    ksort($finalOverrideMap);
    $overrideLines = array();
    foreach ($finalOverrideMap as $k => $v) {
        $overrideLines[] = $k . '=' . $v;
    }
    $config['whatsapp_sender_overrides'] = implode("\n", $overrideLines);

    $settings['avisos_config'] = $config;

    // Guardar cuentas de Pollo.ai (formato multi-cuenta)
    $accountCount = (int)request_post('pollo_account_count', 0);
    $polloAccounts = array();
    $addAccount = (int)request_post('pollo_add_account', 0) > 0;

    for ($i = 0; $i < max($accountCount, 2); $i++) {
        $cookie = trim((string)request_post('pollo_account_' . $i . '_cookie', ''));
        $label = trim((string)request_post('pollo_account_' . $i . '_label', ''));
        $expires = trim((string)request_post('pollo_account_' . $i . '_expires', ''));
        $resetCredits = (int)request_post('pollo_account_' . $i . '_reset_credits', 0) > 0;

        // Si hay cookie, guardar la cuenta
        if ($cookie !== '') {
            $polloAccounts[] = array(
                'cookie' => $cookie,
                'label' => $label !== '' ? $label : ('Cuenta ' . ($i + 1)),
                'expires' => $expires !== '' ? $expires : '2026-09-07',
            );
        }
        // Resetear estado de créditos si se pulsó el botón
        if ($resetCredits && $label !== '' && function_exists('publicista_pollo_status_read')) {
            publicista_pollo_mark_recovered($label);
        }
    }

    // Si se pidió añadir cuenta, añadir una vacía
    if ($addAccount) {
        $polloAccounts[] = array(
            'cookie' => '',
            'label' => 'Cuenta ' . (count($polloAccounts) + 1),
            'expires' => '2026-09-07',
        );
    }

    $settings['pollo_accounts'] = $polloAccounts;
    // Mantener backward compat: primera cuenta en los campos legacy
    if (!empty($polloAccounts)) {
        $settings['pollo_session_cookie'] = $polloAccounts[0]['cookie'];
        $settings['pollo_cookie_expires'] = $polloAccounts[0]['expires'] ?? '2026-09-07';
    }

    $settings['updated_at'] = now_datetime();

    storage_write('settings.json', $settings);

    set_flash('ok', 'Configuración guardada correctamente.');
    redirect_to('index.php?page=josue&tab=configm');
}


function action_save_access_config() {
    $settings = storage_read('settings.json');
    $raw = trim((string)request_post('whitelist_ips', "84.125.78.95
79.116.229.72"));
    $ips = preg_split('/[
,;]+/', $raw);
    $clean = array();

    foreach ((array)$ips as $ip) {
        $ip = trim((string)$ip);
        if ($ip === '') continue;
        if (!filter_var($ip, FILTER_VALIDATE_IP)) continue;
        $clean[$ip] = $ip;
    }

    if (empty($clean)) {
        $clean['84.125.78.95'] = '84.125.78.95';
        $clean['79.116.229.72'] = '79.116.229.72';
    }

    $settings['whitelist_ips'] = array_values($clean);
    $settings['updated_at'] = now_datetime();
    storage_write('settings.json', $settings);

    set_flash('ok', 'Whitelist de IPs guardada correctamente.');
    redirect_to('index.php?page=josue&tab=config');
}

function action_revoke_trusted_device() {
    $token = trim((string)request_post('device_token'));
    if ($token !== '' && auth_remove_trusted_device($token)) {
        set_flash('ok', 'Dispositivo revocado correctamente.');
    } else {
        set_flash('error', 'No se pudo revocar el dispositivo.');
    }
    redirect_to('index.php?page=josue&tab=config');
}

function action_save_voice_ai_config() {
    $settings = storage_read('settings.json');

    $apiKey = trim((string)request_post('voice_ai_api_key'));
    $model = trim((string)request_post('voice_ai_model'));
    $provider = trim((string)request_post('voice_ai_provider'));

    if ($apiKey === '') {
        $apiKey = voice_ai_default_api_key();
    }
    if ($model === '') {
        $model = 'deepseek-v4-pro';
    }
    if ($provider === '') {
        $provider = 'deepseek';
    }

    $settings['voice_ai_api_key'] = $apiKey;
    $settings['voice_ai_model'] = $model;
    $settings['voice_ai_provider'] = $provider;
    $settings['updated_at'] = now_datetime();
    storage_write('settings.json', $settings);

    if ($apiKey === '') {
        set_flash('error', 'Configuración guardada, pero no se detectó ninguna API key por defecto en la plantilla del bot.');
    } else {
        set_flash('ok', 'Configuración de IA de voz guardada correctamente.');
    }

    redirect_to('index.php?page=josue&tab=config');
}

function action_save_casawasap_contacto() {
    $id = trim(request_post('id'));
    if ($id === '') $id = generate_id('casa');

    $existing = storage_find_by_id('casawasap_contactos.json', $id);

    $pick = function ($key, $default = '') use ($existing) {
        if (array_key_exists($key, $_POST)) {
            return trim(request_post($key));
        }
        return trim((string)($existing[$key] ?? $default));
    };

    $row = array(
        'id' => $id,
        'telefono' => $pick('telefono'),
        'notas' => $pick('notas'),
        'quien_lo_trae' => $pick('quien_lo_trae'),
        'estado' => $existing && isset($existing['estado']) ? $existing['estado'] : 'interesado',
        'updated_at' => now_datetime()
    );

    if ($existing && isset($existing['nombre'])) $row['nombre'] = $existing['nombre'];
    if ($existing && isset($existing['precio'])) $row['precio'] = $existing['precio'];
    if ($existing && isset($existing['cliente_at'])) $row['cliente_at'] = $existing['cliente_at'];
    if ($existing && isset($existing['periodicidad_cobro'])) $row['periodicidad_cobro'] = $existing['periodicidad_cobro'];
    if ($existing && isset($existing['modo'])) $row['modo'] = $existing['modo'];

    foreach (array(
        'bot_business_name',
        'bot_contexto',
        'bot_servicios',
        'bot_tarifas',
        'bot_zona',
        'bot_ubicacion_maps',
        'bot_horario',
        'bot_objetivo',
        'bot_modo_preferido'
    ) as $field) {
        $value = $pick($field);
        if ($value !== '' || ($existing && array_key_exists($field, (array)$existing))) {
            $row[$field] = $value;
        }
    }

    if (($row['bot_modo_preferido'] ?? '') === '') {
        $row['bot_modo_preferido'] = trim((string)($row['modo'] ?? 'multiple'));
    }
    if (($row['bot_modo_preferido'] ?? '') !== 'personal') {
        $row['bot_modo_preferido'] = 'multiple';
    }

    $row['created_at'] = ($existing && isset($existing['created_at'])) ? $existing['created_at'] : now_datetime();

    storage_upsert('casawasap_contactos.json', $row);
    set_flash('ok', 'Contacto de Casawasap guardado.');
    redirect_to('index.php?page=casawasap&edit=' . urlencode($id));
}

function action_convert_casawasap_cliente() {
    $id = trim(request_post('id'));
    $existing = storage_find_by_id('casawasap_contactos.json', $id);

    if (!$existing) {
        set_flash('error', 'Interesado no encontrado.');
        redirect_to('index.php?page=casawasap');
    }

    $existing['estado'] = 'cliente';
    $existing['nombre'] = trim(request_post('nombre'));
    $existing['precio'] = to_float(request_post('precio'), 0);
    $existing['periodicidad_cobro'] = trim(request_post('periodicidad_cobro', 'semanal'));
    $existing['cliente_at'] = isset($existing['cliente_at']) ? $existing['cliente_at'] : now_datetime();
    $existing['updated_at'] = now_datetime();

    foreach (array(
        'bot_business_name',
        'bot_contexto',
        'bot_servicios',
        'bot_tarifas',
        'bot_zona',
        'bot_ubicacion_maps',
        'bot_horario',
        'bot_objetivo',
        'bot_modo_preferido'
    ) as $field) {
        if (array_key_exists($field, $_POST)) {
            $existing[$field] = trim(request_post($field));
        }
    }

    if (($existing['bot_modo_preferido'] ?? '') === '') {
        $existing['bot_modo_preferido'] = trim((string)($existing['modo'] ?? 'multiple'));
    }
    if (($existing['bot_modo_preferido'] ?? '') !== 'personal') {
        $existing['bot_modo_preferido'] = 'multiple';
    }

    storage_upsert('casawasap_contactos.json', $existing);
    set_flash('ok', 'Interesado convertido en cliente de Casawasap.', 'celebrate');
    redirect_to('index.php?page=casawasap&edit=' . urlencode($id));
}

/**
 * Notifica un nuevo pago de Casawasap al admin vía Telegram.
 */
function crm_notificar_telegram_pago(array $pago, array $cliente): void {
    $token = '7455622229:AAG7qFKsNS52Xn7WkWdxgshqriTZCVQedNE';
    $chatId = '6755848011';
    $msg  = "NUEVO PAGO Casawasap\n";
    $msg .= "Cliente: " . ($cliente['nombre'] ?? '?') . "\n";
    $msg .= "Importe: " . number_format((float) ($pago['importe'] ?? 0), 2) . " EUR\n";
    $msg .= "Fecha: " . ($pago['fecha_hora'] ?? '?') . "\n";
    if (!empty($pago['observaciones'])) {
        $msg .= "Notas: " . $pago['observaciones'] . "\n";
    }
    $url = 'https://api.telegram.org/bot' . $token . '/sendMessage?chat_id=' . $chatId . '&text=' . urlencode($msg);
    @file_get_contents($url);
}

function action_casawasap_add_pago() {
    $clienteId = trim(request_post('cliente_id'));
    $cliente = storage_find_by_id('casawasap_contactos.json', $clienteId);

    if (!$cliente || !isset($cliente['estado']) || $cliente['estado'] !== 'cliente') {
        set_flash('error', 'Cliente de Casawasap no encontrado.');
        redirect_to('index.php?page=casawasap');
    }

    $row = array(
        'id' => generate_id('cpago'),
        'cliente_id' => $clienteId,
        'cliente_nombre' => isset($cliente['nombre']) ? $cliente['nombre'] : '',
        'fecha_hora' => request_post('fecha_hora', today_datetime_local()),
        'importe' => to_float(request_post('importe'), 0),
        'observaciones' => trim(request_post('observaciones')),
        'created_at' => now_datetime(),
        'updated_at' => now_datetime()
    );

    storage_upsert('casawasap_pagos.json', $row);

    // Notificar a Telegram
    crm_notificar_telegram_pago($row, $cliente);

    set_flash('ok', 'Pago registrado: +' . euro($row['importe']), 'money');
    redirect_to('index.php?page=casawasap&edit=' . urlencode($clienteId));
}

function action_delete_casawasap_pago() {
    $id = trim(request_post('id'));
    $clienteId = trim(request_post('cliente_id'));

    if ($id !== '') {
        storage_delete('casawasap_pagos.json', $id);
    }

    set_flash('ok', 'Pago eliminado.');
    redirect_to('index.php?page=casawasap&edit=' . urlencode($clienteId));
}


function action_save_jostal_interesada() {
    $id = trim(request_post('id'));
    if ($id === '') $id = generate_id('jint');

    $existing = storage_find_by_id('jostal_interesadas.json', $id);

    $row = array(
        'id' => $id,
        'telefono' => trim(request_post('telefono')),
        'observaciones' => trim(request_post('observaciones')),
        'interesada_en' => trim(request_post('interesada_en')),
        'fecha' => request_post('fecha', business_today_date()),
        'updated_at' => now_datetime()
    );

    $row['estado'] = ($existing && isset($existing['estado'])) ? $existing['estado'] : 'nueva';
    $row['created_at'] = ($existing && isset($existing['created_at'])) ? $existing['created_at'] : now_datetime();
    if ($existing && isset($existing['clienta_id'])) $row['clienta_id'] = $existing['clienta_id'];

    storage_upsert('jostal_interesadas.json', $row);
    set_flash('ok', 'Interesada de Jostal guardada.');
    redirect_to('index.php?page=jostal&tab=interesadas&edit=' . urlencode($id));
}

function action_convert_jostal_clienta() {
    $interesadaId = trim(request_post('interesada_id'));
    $interesada = storage_find_by_id('jostal_interesadas.json', $interesadaId);

    if (!$interesada) {
        set_flash('error', 'Interesada de Jostal no encontrada.');
        redirect_to('index.php?page=jostal&tab=interesadas');
    }

    $clientaId = isset($interesada['clienta_id']) && $interesada['clienta_id'] !== '' ? $interesada['clienta_id'] : generate_id('jcli');

    $firstArrival = trim(request_post('first_arrival_date', business_today_date()));

    $clienta = array(
        'id' => $clientaId,
        'telefono' => isset($interesada['telefono']) ? $interesada['telefono'] : '',
        'observaciones' => isset($interesada['observaciones']) ? $interesada['observaciones'] : '',
        'modo' => trim(request_post('modo')),
        'precio_semanal' => to_float(request_post('precio_semanal'), 0),
        'precio_semanal_anterior' => to_float(request_post('precio_semanal_anterior'), 0),
        'precio_semanal_desde' => trim(request_post('precio_semanal_desde')),
        'rent_due_weekday' => max(1, min(7, (int)request_post('rent_due_weekday', jostal_weekday_from_date($firstArrival)))),
        'nombre' => trim(request_post('nombre')),
        'created_at' => now_datetime(),
        'updated_at' => now_datetime(),
        'source_interesada_id' => $interesadaId,
        'periodos_estancia' => array(
            array(
                'entrada' => $firstArrival,
                'salida' => ''
            )
        )
    );

    $existingClienta = storage_find_by_id('jostal_clientas.json', $clientaId);
    if ($existingClienta && isset($existingClienta['created_at'])) {
        $clienta['created_at'] = $existingClienta['created_at'];
    }

    storage_upsert('jostal_clientas.json', $clienta);

    $interesada['estado'] = 'convertida';
    $interesada['convertida_at'] = now_datetime();
    $interesada['clienta_id'] = $clientaId;
    $interesada['updated_at'] = now_datetime();
    storage_upsert('jostal_interesadas.json', $interesada);

    set_flash('ok', 'Interesada convertida en clienta de Jostal.', 'celebrate');
    redirect_to('index.php?page=jostal&tab=clientas&edit=' . urlencode($clientaId));
}

function action_save_jostal_clienta() {
    $id = trim(request_post('id'));
    $existing = $id !== '' ? storage_find_by_id('jostal_clientas.json', $id) : null;
    $sourceInteresadaId = trim(request_post('source_interesada_id'));
    error_log("DEBUG_JOSTAL_CONVERT: action=save_jostal_clienta id=[{$id}] source_interesada_id=[{$sourceInteresadaId}] POST_keys=" . implode(',', array_keys($_POST)));

    if (!$existing && $sourceInteresadaId === '') {
        set_flash('error', 'No se puede crear una clienta de Jostal directamente.');
        redirect_to('index.php?page=jostal&tab=interesadas');
    }

    if (!$existing) {
        $interesada = storage_find_by_id('jostal_interesadas.json', $sourceInteresadaId);
        error_log("DEBUG_JOSTAL_CONVERT: storage_find_by_id jostal_interesadas source=[{$sourceInteresadaId}] found=" . ($interesada ? 'SI' : 'NO') . " estado=" . ($interesada['estado'] ?? 'n/a'));
        if (!$interesada) {
            set_flash('error', 'Interesada de Jostal no encontrada.');
            redirect_to('index.php?page=jostal&tab=interesadas');
        }

        if (($interesada['estado'] ?? '') === 'convertida' && !empty($interesada['clienta_id'])) {
            redirect_to('index.php?page=jostal&tab=clientas&edit=' . urlencode($interesada['clienta_id']));
        }

        $id = !empty($interesada['clienta_id']) ? $interesada['clienta_id'] : generate_id('jcli');
        $firstArrival = trim(request_post('first_arrival_date', business_today_date()));

        $row = array(
            'id' => $id,
            'telefono' => trim(request_post('telefono')),
            'observaciones' => trim(request_post('observaciones')),
            'modo' => trim(request_post('modo')),
            'precio_semanal' => to_float(request_post('precio_semanal'), 0),
            'precio_semanal_anterior' => to_float(request_post('precio_semanal_anterior'), 0),
            'precio_semanal_desde' => trim(request_post('precio_semanal_desde')),
            'rent_due_weekday' => max(1, min(7, (int)request_post('rent_due_weekday', jostal_weekday_from_date($firstArrival)))),
            'nombre' => trim(request_post('nombre')),
            'nombre_real' => trim(request_post('nombre_real')),
            'dni' => trim(request_post('dni')),
            'updated_at' => now_datetime(),
            'created_at' => now_datetime(),
            'source_interesada_id' => $sourceInteresadaId,
            'periodos_estancia' => array(
                array(
                    'entrada' => $firstArrival,
                    'salida' => ''
                )
            )
        );

        storage_upsert('jostal_clientas.json', $row);

        $interesada['estado'] = 'convertida';
        $interesada['convertida_at'] = now_datetime();
        $interesada['clienta_id'] = $id;
        $interesada['updated_at'] = now_datetime();
        storage_upsert('jostal_interesadas.json', $interesada);

        set_flash('ok', 'Interesada convertida en clienta de Jostal.', 'celebrate');
        redirect_to('index.php?page=jostal&tab=clientas&edit=' . urlencode($id));
    }

    $row = array(
        'id' => $id,
        'telefono' => trim(request_post('telefono')),
        'observaciones' => trim(request_post('observaciones')),
        'modo' => trim(request_post('modo')),
        'precio_semanal' => to_float(request_post('precio_semanal'), 0),
        'precio_semanal_anterior' => to_float(request_post('precio_semanal_anterior'), 0),
        'precio_semanal_desde' => trim(request_post('precio_semanal_desde')),
        'rent_due_weekday' => max(1, min(7, (int)request_post('rent_due_weekday', jostal_alquiler_due_weekday($existing)))),
        'nombre' => trim(request_post('nombre')),
        'nombre_real' => trim(request_post('nombre_real')),
        'dni' => trim(request_post('dni')),
        'updated_at' => now_datetime(),
        'created_at' => isset($existing['created_at']) ? $existing['created_at'] : now_datetime(),
        'source_interesada_id' => isset($existing['source_interesada_id']) ? $existing['source_interesada_id'] : '',
        'periodos_estancia' => isset($existing['periodos_estancia']) && is_array($existing['periodos_estancia'])
            ? $existing['periodos_estancia']
            : array()
    );

    storage_upsert('jostal_clientas.json', $row);
    set_flash('ok', 'Clienta de Jostal actualizada.');
    redirect_to('index.php?page=jostal&tab=clientas&edit=' . urlencode($id));
}

function action_jostal_add_lead() {
    $clientaId = trim(request_post('clienta_id'));
    $clienta = storage_find_by_id('jostal_clientas.json', $clientaId);

    if (!$clienta) {
        set_flash('error', 'Clienta de Jostal no encontrada.');
        redirect_to('index.php?page=jostal&tab=clientas');
    }

    $leadDate = trim(request_post('created_at_date'));
    $leadTime = trim(request_post('created_at_time'));
    if ($leadDate === '' && $leadTime === '') {
        $leadCreatedAt = now_datetime();
    } elseif ($leadDate === '') {
        $leadCreatedAt = now_datetime();
    } else {
        $leadCreatedAt = $leadDate . ' ' . ($leadTime !== '' ? $leadTime : '00:00') . ':00';
    }

    $row = array(
        'id' => generate_id('jlead'),
        'clienta_id' => $clientaId,
        'clienta_nombre' => isset($clienta['nombre']) ? $clienta['nombre'] : '',
        'precio' => to_float(request_post('precio'), 0),
        'observacion' => trim(request_post('observacion')),
        'created_at' => $leadCreatedAt,
        'updated_at' => now_datetime()
    );

    storage_upsert('jostal_leads.json', $row);
    set_flash('ok', 'Lead Jostal registrado: +' . euro($row['precio']), 'money');
    redirect_to('index.php?page=jostal&tab=clientas&edit=' . urlencode($clientaId));
}

function action_jostal_edit_lead() {
    $leadId = trim(request_post('lead_id'));
    $clientaId = trim(request_post('clienta_id'));
    $clienta = storage_find_by_id('jostal_clientas.json', $clientaId);

    if (!$clienta) {
        set_flash('error', 'Clienta de Jostal no encontrada.');
        redirect_to('index.php?page=jostal&tab=clientas');
    }

    $existing = storage_find_by_id('jostal_leads.json', $leadId);
    if (!$existing) {
        set_flash('error', 'Lead no encontrado.');
        redirect_to('index.php?page=jostal&tab=clientas&edit=' . urlencode($clientaId));
    }

    $leadDate = trim(request_post('created_at_date'));
    $leadTime = trim(request_post('created_at_time'));
    if ($leadDate === '' && $leadTime === '') {
        $leadCreatedAt = $existing['created_at'] ?? now_datetime();
    } elseif ($leadDate === '') {
        $leadCreatedAt = $existing['created_at'] ?? now_datetime();
    } else {
        $leadCreatedAt = $leadDate . ' ' . ($leadTime !== '' ? $leadTime : '00:00') . ':00';
    }

    $row = array(
        'id' => $leadId,
        'clienta_id' => $clientaId,
        'clienta_nombre' => isset($clienta['nombre']) ? $clienta['nombre'] : '',
        'precio' => to_float(request_post('precio'), 0),
        'observacion' => trim(request_post('observacion')),
        'created_at' => $leadCreatedAt,
        'updated_at' => now_datetime()
    );

    // Si la observación no cambió, preservar la clasificación persistida.
    // Si cambió, se deja sin clasificar para que se re-detecte automáticamente.
    $obsCambiada = (string)($row['observacion'] ?? '') !== (string)($existing['observacion'] ?? '');
    if (!$obsCambiada) {
        if (isset($existing['concepto_tipo'])) $row['concepto_tipo'] = $existing['concepto_tipo'];
        if (isset($existing['concepto_fuente'])) $row['concepto_fuente'] = $existing['concepto_fuente'];
        if (isset($existing['concepto_confirmado_at'])) $row['concepto_confirmado_at'] = $existing['concepto_confirmado_at'];
    }

    storage_upsert('jostal_leads.json', $row);
    set_flash('ok', 'Lead actualizado.');
    redirect_to('index.php?page=jostal&tab=clientas&edit=' . urlencode($clientaId));
}

function action_jostal_delete_lead() {
    $leadId = trim(request_post('lead_id'));
    $clientaId = trim(request_post('clienta_id'));
    if ($leadId !== '') {
        storage_delete('jostal_leads.json', $leadId);
    }
    set_flash('ok', 'Lead eliminado.');
    redirect_to('index.php?page=jostal&tab=clientas&edit=' . urlencode($clientaId));
}

function action_jostal_add_venta() {
    $row = array(
        'id' => generate_id('jventa'),
        'descripcion' => trim(request_post('descripcion')),
        'precio' => to_float(request_post('precio'), 0),
        'created_at' => now_datetime(),
        'updated_at' => now_datetime()
    );

    storage_upsert('jostal_ventas.json', $row);
    set_flash('ok', 'Venta Jostal registrada: +' . euro($row['precio']), 'money');
    redirect_to('index.php?page=jostal&tab=ventas');
}

function action_add_gasto() {
    $createdAt = trim(request_post('created_at'));
    if ($createdAt === '') {
        $createdAt = now_datetime();
    } else {
        $createdAt = str_replace('T', ' ', $createdAt);
        if (strlen($createdAt) === 16) {
            $createdAt .= ':00';
        }
    }

    $row = array(
        'id' => generate_id('gasto'),
        'cantidad' => to_float(request_post('cantidad'), 0),
        'descripcion' => trim(request_post('descripcion')),
        'created_at' => $createdAt,
        'updated_at' => now_datetime()
    );

    storage_upsert('gastos.json', $row);
    set_flash('ok', 'Gasto registrado: -' . euro($row['cantidad']), 'sadmoney');
    redirect_to('index.php?page=gastos');
}


function action_delete_gasto() {
    $id = trim(request_post('id'));
    if ($id !== '') {
        storage_delete('gastos.json', $id);
    }
    set_flash('ok', 'Gasto eliminado definitivamente.');
    redirect_to('index.php?page=gastos');
}

function action_set_casawasap_estado() {
    $id = trim(request_post('id'));
    $estado = trim(request_post('estado'));
    $row = storage_find_by_id('casawasap_contactos.json', $id);

    if (!$row) {
        set_flash('error', 'Contacto de Casawasap no encontrado.');
        redirect_to('index.php?page=casawasap');
    }

    $row['estado'] = $estado;
    $row['updated_at'] = now_datetime();
    storage_upsert('casawasap_contactos.json', $row);

    set_flash('ok', 'Estado actualizado.');
    redirect_to('index.php?page=casawasap&edit=' . urlencode($id));
}

function action_baja_casawasap_cliente() {
    $id = trim(request_post('id'));
    $row = storage_find_by_id('casawasap_contactos.json', $id);

    if (!$row) {
        set_flash('error', 'Cliente Casawasap no encontrado.');
        redirect_to('index.php?page=casawasap');
    }

    $row['estado'] = 'baja';
    $row['baja_at'] = now_datetime();
    $row['updated_at'] = now_datetime();
    storage_upsert('casawasap_contactos.json', $row);

    set_flash('ok', 'Cliente Casawasap dado de baja.');
    redirect_to('index.php?page=casawasap&edit=' . urlencode($id));
}

function action_alta_casawasap_cliente() {
    $id = trim(request_post('id'));
    $row = storage_find_by_id('casawasap_contactos.json', $id);

    if (!$row) {
        set_flash('error', 'Cliente Casawasap no encontrado.');
        redirect_to('index.php?page=casawasap');
    }

    $row['estado'] = 'cliente';
    $row['updated_at'] = now_datetime();
    storage_upsert('casawasap_contactos.json', $row);

    set_flash('ok', 'Cliente Casawasap reactivado.');
    redirect_to('index.php?page=casawasap&edit=' . urlencode($id));
}

function action_discard_jostal_interesada() {
    $id = trim(request_post('id'));
    $row = storage_find_by_id('jostal_interesadas.json', $id);

    if (!$row) {
        set_flash('error', 'Interesada Jostal no encontrada.');
        redirect_to('index.php?page=jostal&tab=interesadas');
    }

    $row['estado'] = 'descartada';
    $row['updated_at'] = now_datetime();
    storage_upsert('jostal_interesadas.json', $row);

    set_flash('ok', 'Interesada Jostal descartada.');
    redirect_to('index.php?page=jostal&tab=interesadas&edit=' . urlencode($id));
}

function action_reactivate_jostal_interesada() {
    $id = trim(request_post('id'));
    $row = storage_find_by_id('jostal_interesadas.json', $id);

    if (!$row) {
        set_flash('error', 'Interesada Jostal no encontrada.');
        redirect_to('index.php?page=jostal&tab=interesadas');
    }

    $row['estado'] = 'nueva';
    $row['updated_at'] = now_datetime();
    storage_upsert('jostal_interesadas.json', $row);

    set_flash('ok', 'Interesada Jostal reactivada.');
    redirect_to('index.php?page=jostal&tab=interesadas&edit=' . urlencode($id));
}

function action_jostal_salida_casa() {
    $id = trim(request_post('id'));
    $salida = trim(request_post('salida', business_today_date()));
    $row = storage_find_by_id('jostal_clientas.json', $id);

    if (!$row) {
        set_flash('error', 'Clienta Jostal no encontrada.');
        redirect_to('index.php?page=jostal&tab=clientas');
    }

    $periodos = jostal_periodos_estancia($row);
    if (empty($periodos)) {
        set_flash('error', 'Esta clienta no tiene ninguna estancia abierta.');
        redirect_to('index.php?page=jostal&tab=clientas&edit=' . urlencode($id));
    }

    $lastIdx = count($periodos) - 1;
    $periodos[$lastIdx]['salida'] = $salida;

    $row['periodos_estancia'] = $periodos;
    $row['updated_at'] = now_datetime();
    storage_upsert('jostal_clientas.json', $row);

    set_flash('ok', 'Salida de la casa registrada.');
    redirect_to('index.php?page=jostal&tab=clientas&edit=' . urlencode($id));
}

function action_jostal_reactivar_casa() {
    $id = trim(request_post('id'));
    $entrada = trim(request_post('entrada', business_today_date()));
    $row = storage_find_by_id('jostal_clientas.json', $id);

    if (!$row) {
        set_flash('error', 'Clienta Jostal no encontrada.');
        redirect_to('index.php?page=jostal&tab=clientas');
    }

    $periodos = jostal_periodos_estancia($row);
    $periodos[] = array(
        'entrada' => $entrada,
        'salida' => ''
    );

    $row['periodos_estancia'] = $periodos;
    if ((string)($row['modo'] ?? '') === 'alquiler' && (int)($row['rent_due_weekday'] ?? 0) <= 0) {
        $row['rent_due_weekday'] = jostal_weekday_from_date($entrada);
    }
    $row['updated_at'] = now_datetime();
    storage_upsert('jostal_clientas.json', $row);

    set_flash('ok', 'Reentrada en la casa registrada.');
    redirect_to('index.php?page=jostal&tab=clientas&edit=' . urlencode($id));
}

function action_jostal_update_rent_due_weekday() {
    $id = trim(request_post('id'));
    $row = storage_find_by_id('jostal_clientas.json', $id);

    if (!$row) {
        set_flash('error', 'Clienta Jostal no encontrada.');
        redirect_to('index.php?page=jostal&tab=clientas');
    }

    $weekday = max(1, min(7, (int)request_post('rent_due_weekday', jostal_alquiler_due_weekday($row))));
    $row['rent_due_weekday'] = $weekday;
    $row['updated_at'] = now_datetime();
    storage_upsert('jostal_clientas.json', $row);

    set_flash('ok', 'Día semanal de cobro actualizado.');
    redirect_to('index.php?page=jostal&tab=clientas&edit=' . urlencode($id));
}

/**
 * Clasifica manualmente un lead dudoso como alquiler o no-alquiler y lo persiste.
 * La clasificación manual gana siempre sobre la detección automática.
 */
function action_jostal_clasificar_lead() {
    $leadId = trim(request_post('lead_id'));
    $tipo = trim(request_post('concepto_tipo'));
    $returnTab = trim(request_post('return_tab', 'deudas'));
    $desde = trim(request_post('desde', ''));
    $hasta = trim(request_post('hasta', ''));
    $clientaId = trim(request_post('clienta_id', ''));
    $fuente = trim(request_post('fuente', ''));

    if (!in_array($tipo, array('alquiler', 'no_alquiler'), true)) {
        set_flash('error', 'Clasificación inválida.');
        redirect_to('index.php?page=jostal&tab=deudas');
    }

    $lead = storage_find_by_id('jostal_leads.json', $leadId);
    if (!$lead) {
        set_flash('error', 'Lead no encontrado.');
        redirect_to('index.php?page=jostal&tab=deudas');
    }

    $lead['concepto_tipo'] = $tipo;
    $lead['concepto_fuente'] = 'manual';
    $lead['concepto_confirmado_at'] = now_datetime();
    $lead['updated_at'] = now_datetime();
    storage_upsert('jostal_leads.json', $lead);

    set_flash('ok', 'Pago clasificado como ' . ($tipo === 'alquiler' ? 'alquiler' : 'no alquiler') . '.');
    $qs = 'index.php?page=jostal&tab=' . urlencode($returnTab);
    if ($desde !== '') $qs .= '&desde=' . urlencode($desde);
    if ($hasta !== '') $qs .= '&hasta=' . urlencode($hasta);
    if ($clientaId !== '') $qs .= '&clienta_id=' . urlencode($clientaId);
    if ($fuente !== '') $qs .= '&fuente=' . urlencode($fuente);
    redirect_to($qs);
}

/**
 * Compensa un pago de "otros ingresos" convirtiéndolo permanentemente en alquiler.
 * El concepto pasa a ser "{original} + compensación posterior alquiler".
 */
function action_jostal_compensar_lead() {
    $leadId = trim(request_post('lead_id'));
    $clientaId = trim(request_post('clienta_id'));
    $desde = trim(request_post('desde', ''));
    $hasta = trim(request_post('hasta', ''));
    $fuente = trim(request_post('fuente', ''));

    $clienta = storage_find_by_id('jostal_clientas.json', $clientaId);
    if (!$clienta) {
        set_flash('error', 'Clienta no encontrada.');
        redirect_to('index.php?page=jostal&tab=deudas');
    }

    $mutation = jostal_compensar_lead_permanente($leadId, $clientaId);
    if (empty($mutation['ok'])) {
        set_flash('error', (string)($mutation['error'] ?? 'No se pudo compensar el pago.'));
        redirect_to('index.php?page=jostal&tab=deudas');
    }

    set_flash('ok', 'Pago compensado como alquiler.');
    $qs = 'index.php?page=jostal&tab=deudas';
    if ($desde !== '') $qs .= '&desde=' . urlencode($desde);
    if ($hasta !== '') $qs .= '&hasta=' . urlencode($hasta);
    $qs .= '&clienta_id=' . urlencode($clientaId);
    if ($fuente === 'semana') $qs .= '&fuente=semana';
    redirect_to($qs);
}

/**
 * Envía por WhatsApp (desde la línea "jostal dulce") el informe de deuda de una clienta.
 * Admite compensaciones temporales vía `reclasificar[]` (lead_id tratados como alquiler).
 */
function action_jostal_send_deuda_wasap() {
    $clientaId = trim(request_post('clienta_id'));
    $destinoTipo = trim(request_post('destino_tipo', 'clienta'));
    $destinoManual = trim(request_post('destino_manual', ''));
    $desde = trim(request_post('desde', ''));
    $hasta = trim(request_post('hasta', ''));
    $fuente = trim(request_post('fuente', 'alquiler'));
    if ($fuente !== 'semana') $fuente = 'alquiler';

    $rango = jostal_validar_rango_fechas($desde, $hasta);
    if (empty($rango['ok'])) {
        set_flash('error', (string)$rango['error']);
        redirect_to('index.php?page=jostal&tab=deudas' . ($clientaId !== '' ? '&clienta_id=' . urlencode($clientaId) : ''));
    }

    $reclasificar = request_post('reclasificar', array());
    if (!is_array($reclasificar)) $reclasificar = array();
    $reclasificar = array_values(array_unique(array_filter(array_map('strval', $reclasificar), function ($x) { return $x !== ''; })));

    $clienta = storage_find_by_id('jostal_clientas.json', $clientaId);
    if (!$clienta) {
        set_flash('error', 'Clienta no encontrada.');
        redirect_to('index.php?page=jostal&tab=deudas');
    }

    $data = jostal_compute_deuda($clienta, null, $reclasificar, $desde, $hasta);
    if (isset($data['error'])) {
        set_flash('error', 'No se pudo calcular la deuda de esta clienta.');
        redirect_to('index.php?page=jostal&tab=deudas');
    }

    // Determinar teléfono destino.
    if ($destinoTipo === 'clienta') {
        $target = trim((string)($clienta['telefono'] ?? ''));
    } elseif ($destinoTipo === 'personal') {
        $target = '654464023';
    } else {
        $target = $destinoManual;
    }

    $targetDigits = comercial_only_digits($target);
    if ($targetDigits === '') {
        set_flash('error', 'Teléfono de destino vacío.');
        redirect_to('index.php?page=jostal&tab=deudas');
    }

    $line = jostal_dulce_line();
    if (!$line) {
        set_flash('error', 'Línea "jostal dulce" no encontrada o sin configurar.');
        redirect_to('index.php?page=jostal&tab=deudas');
    }

    $nombre = trim((string)($clienta['nombre'] ?? ''));
    $texto = jostal_texto_deuda($nombre, $data, $desde, $hasta, $fuente);

    $result = comercial_send_text_via_line($line, $targetDigits, $texto, array('slug' => 'jostal_deuda'));

    if (!empty($result['ok'])) {
        set_flash('ok', 'Informe enviado por WhatsApp (dulce) a ' . $targetDigits . '.', 'celebrate');
    } else {
        set_flash('error', 'Error al enviar: ' . trim((string)($result['error'] ?? 'desconocido')));
    }

    $qs = 'index.php?page=jostal&tab=deudas';
    if ($desde !== '') $qs .= '&desde=' . urlencode($desde);
    if ($hasta !== '') $qs .= '&hasta=' . urlencode($hasta);
    if ($fuente === 'semana') $qs .= '&fuente=semana';
    redirect_to($qs);
}

function action_unlock_josue_anuncios() {
    $password = trim(request_post('password'));

    if ($password === 'hola1234') {
        $_SESSION['josue_anuncios_unlocked'] = true;
        set_flash('ok', 'Sección Anuncios desbloqueada.');
    } else {
        set_flash('error', 'Contraseña incorrecta.');
    }

    redirect_to('index.php?page=josue');
}

function action_unlock_josue_wasap() {
    $password = trim(request_post('password'));

    if ($password === '2681') {
        $_SESSION['josue_wasap_unlocked'] = true;
        set_flash('ok', 'WhatsApp Personal desbloqueado.');
    } else {
        set_flash('error', 'Contraseña incorrecta.');
    }

    redirect_to('index.php?page=josue&tab=wasap');
}

function action_unlock_publicista_accounts() {
    $password = trim(request_post('password'));

    if ($password === 'hola1234') {
        $_SESSION['publicista_accounts_unlocked'] = true;
        set_flash('ok', 'Sección Cuentas desbloqueada.');
    } else {
        set_flash('error', 'Contraseña incorrecta.');
    }

    redirect_to(publicista_page_url('cuentas'));
}

function action_save_publicista_account() {
    $id = trim((string)request_post('id'));
    if ($id === '') $id = generate_id('pubacc');

    $existing = publicista_account_get($id);
    $portalCode = trim((string)request_post('portal_code', 'destacamos'));
    $portalOptions = publicista_account_portal_options();
    $portalLabel = $portalOptions[$portalCode] ?? 'Otro / manual';

    $row = publicista_account_defaults($id);
    $row = array_merge($row, is_array($existing) ? $existing : array());
    $row['portal_code'] = $portalCode;
    $row['portal_label'] = trim((string)request_post('portal_label')) !== '' ? trim((string)request_post('portal_label')) : $portalLabel;
    $row['portal_url'] = trim((string)request_post('portal_url'));
    $row['login_user'] = trim((string)request_post('login_user'));
    $row['login_pass'] = trim((string)request_post('login_pass'));
    $row['display_name'] = trim((string)request_post('display_name'));
    $row['descripcion'] = trim((string)request_post('descripcion'));
    $row['estado'] = trim((string)request_post('estado', 'active'));
    $row['automation_mode'] = trim((string)request_post('automation_mode', 'full_publish'));
    $row['health_status'] = trim((string)request_post('health_status', 'ok'));
    $row['priority_weight'] = max(0, (int)request_post('priority_weight', 100));
    $row['max_active_ads'] = 0;
    $row['portal_listing_ids'] = preg_split('/\r\n|\r|\n|,|;/', trim((string)request_post('portal_listing_ids_raw')));
    $row['daily_publish_limit'] = max(0, (int)request_post('daily_publish_limit', 0));
    $row['created_ads_count'] = max(0, (int)request_post('created_ads_count', (int)($existing['created_ads_count'] ?? 0)));
    $row['active_ads_count'] = max(0, (int)request_post('active_ads_count', (int)($existing['active_ads_count'] ?? 0)));
    $row['published_ads_count'] = max(0, (int)request_post('published_ads_count', (int)($existing['published_ads_count'] ?? 0)));
    $row['free_bump_tasks_count'] = max(0, (int)request_post('free_bump_tasks_count', (int)($existing['free_bump_tasks_count'] ?? 0)));
    $row['last_success_at'] = trim((string)request_post('last_success_at', (string)($existing['last_success_at'] ?? '')));
    $row['last_error_at'] = trim((string)request_post('last_error_at', (string)($existing['last_error_at'] ?? '')));
    $row['last_error'] = trim((string)request_post('last_error', (string)($existing['last_error'] ?? '')));
    $row['notes_internal'] = trim((string)request_post('notes_internal'));
    $row['created_at'] = ($existing && isset($existing['created_at'])) ? $existing['created_at'] : now_datetime();
    $row['updated_at'] = now_datetime();

    if ($row['portal_url'] === '' || $row['login_user'] === '' || $row['login_pass'] === '') {
        set_flash('error', 'Portal/URL, usuario y contraseña son obligatorios.');
        redirect_to(publicista_page_url('cuentas', array('edit' => $id)));
    }

    publicista_account_upsert($row);
    set_flash('ok', 'Cuenta guardada en Publicista.');
    redirect_to(publicista_page_url('cuentas', array('edit' => $id)));
}

function action_save_publicista_free_bump_config() {
    $current = publicista_free_bump_config();
    $groupsRaw = isset($_POST['groups']) && is_array($_POST['groups']) ? $_POST['groups'] : array();
    $groups = array();
    foreach ($groupsRaw as $groupName => $groupData) {
        $groupName = trim((string)$groupName);
        if ($groupName === '') continue;
        $groupData = is_array($groupData) ? $groupData : array();
        $groups[$groupName] = array(
            'enabled' => !empty($groupData['enabled']) ? 1 : 0,
            'start_time' => trim((string)($groupData['start_time'] ?? '08:00')),
            'end_time' => trim((string)($groupData['end_time'] ?? '23:00')),
        );
    }
    $cfg = array_merge($current, array(
        'enabled' => request_post('enabled') ? 1 : 0,
        'groups' => $groups,
        'humanize' => request_post('humanize') ? 1 : 0,
        'anticipation_minutes' => max(0, (int)request_post('anticipation_minutes', $current['anticipation_minutes'] ?? 8)),
        'interval_min_minutes' => max(1, (int)request_post('interval_min_minutes', $current['interval_min_minutes'] ?? 12)),
        'interval_max_minutes' => max(1, (int)request_post('interval_max_minutes', $current['interval_max_minutes'] ?? 120)),
        'retry_empty_min_minutes' => max(1, (int)request_post('retry_empty_min_minutes', $current['retry_empty_min_minutes'] ?? 10)),
        'retry_empty_max_minutes' => max(1, (int)request_post('retry_empty_max_minutes', $current['retry_empty_max_minutes'] ?? 22)),
        'jitter_min_seconds' => max(0, (int)request_post('jitter_min_seconds', $current['jitter_min_seconds'] ?? 30)),
        'jitter_max_seconds' => max(0, (int)request_post('jitter_max_seconds', $current['jitter_max_seconds'] ?? 180)),
    ));
    $savedCfg = publicista_free_bump_save_config($cfg);
    $state = publicista_free_bump_state_prepare_today(publicista_free_bump_state());
    $plan = publicista_free_bump_plan_snapshot($savedCfg, $state, time());
    $nextTs = publicista_free_bump_schedule_next_ts($savedCfg, $plan, time(), 'normal');
    $state['next_run_at'] = ($savedCfg['enabled'] && $nextTs > 0) ? date('Y-m-d H:i:s', $nextTs) : '';
    $state['last_status'] = $savedCfg['enabled'] ? 'configured' : 'disabled';
    $state['last_error'] = '';
    publicista_free_bump_save_state($state);

    if (function_exists('avisos_dismiss_destacamos_publish_reminders')) {
        avisos_dismiss_destacamos_publish_reminders();
    }

    set_flash('ok', 'Configuración de subidas automáticas guardada.');
    redirect_to(publicista_page_url('subir_anuncios'));
}

function action_run_publicista_free_bump_cycle() {
    $forcedAccountId = trim((string)request_post('account_id', ''));
    $forcedAccount = null;
    if ($forcedAccountId !== '') {
        $forcedAccount = publicista_account_get($forcedAccountId);
    }
    $requestId = generate_id('pfbreq');
    $result = publicista_free_bump_run_due(true, array(
        'trigger' => 'manual',
        'request_id' => $requestId,
        'forced_account_id' => $forcedAccountId,
    ));
    $existingLog = function_exists('publicista_free_bump_find_log_by_request_id')
        ? publicista_free_bump_find_log_by_request_id($requestId)
        : null;
    if (!$existingLog && function_exists('publicista_free_bump_append_cycle_log')) {
        $state = is_array($result['state'] ?? null) ? $result['state'] : publicista_free_bump_state_prepare_today(publicista_free_bump_state());
        $logRow = publicista_free_bump_append_cycle_log($result, $state, array(
            'trigger' => 'manual',
            'request_id' => $requestId,
        ));
        $result['log_id'] = trim((string)($logRow['id'] ?? ''));
        $existingLog = $logRow;
    }
    if (!$existingLog && function_exists('publicista_free_bump_log_append')) {
        $state = is_array($result['state'] ?? null) ? $result['state'] : publicista_free_bump_state_prepare_today(publicista_free_bump_state());
        $attempts = array_values((array)($result['attempts'] ?? array()));
        $primaryAttempt = function_exists('publicista_free_bump_primary_attempt') ? publicista_free_bump_primary_attempt($attempts) : (!empty($attempts) ? $attempts[0] : array());
        $fallbackRow = array(
            'id' => generate_id('pfb'),
            'created_at' => now_datetime(),
            'request_id' => $requestId,
            'ok' => !empty($result['ok']),
            'status' => trim((string)($result['status'] ?? ($state['last_status'] ?? 'unknown'))),
            'trigger' => 'manual',
            'account_id' => trim((string)($result['account_id'] ?? ($primaryAttempt['account_id'] ?? ''))),
            'account_label' => trim((string)($result['account_label'] ?? ($primaryAttempt['account_label'] ?? ($primaryAttempt['account_id'] ?? '')))),
            'listing_id' => trim((string)($result['listing_id'] ?? ($primaryAttempt['listing_id'] ?? ''))),
            'error' => trim((string)($result['error'] ?? ($state['last_error'] ?? ''))),
            'error_code' => trim((string)($result['error_code'] ?? ($primaryAttempt['error_code'] ?? ''))),
            'accounts_checked' => count($attempts),
            'primary_attempt' => $primaryAttempt,
            'attempts' => $attempts,
            'next_run_at' => trim((string)($state['next_run_at'] ?? ($result['next_run_at'] ?? ''))),
            'summary' => function_exists('publicista_free_bump_log_summary_from_result')
                ? publicista_free_bump_log_summary_from_result($result, $state)
                : 'Intento manual de subida.',
        );
        publicista_free_bump_log_append($fallbackRow);
        $result['log_id'] = trim((string)($fallbackRow['id'] ?? ''));
    }
    if (!empty($result['ok'])) {
        $msg = $forcedAccountId !== ''
            ? 'Subida gratis forzada ejecutada correctamente.'
            : 'Subida gratis ejecutada correctamente.';
        if ($forcedAccountId !== '') {
            $forcedLabel = trim((string)($forcedAccount['display_name'] ?? ($forcedAccount['login_user'] ?? $forcedAccountId)));
            $msg .= ' Cuenta: ' . $forcedLabel . '.';
        }
        if (trim((string)($result['listing_id'] ?? '')) !== '') {
            $msg .= ' Listing: ' . trim((string)$result['listing_id']) . '.';
        }
        set_flash('ok', $msg);
    } else {
        $status = trim((string)($result['status'] ?? ''));
        $error = trim((string)($result['error'] ?? ''));
        if ($status === 'waiting_window') {
            set_flash('ok', 'No tocaba ejecutar ahora. Próximo intento: ' . trim((string)($result['next_run_at'] ?? 'sin planificar')) . '.');
        } elseif ($status === 'no_available') {
            $msg = $forcedAccountId !== ''
                ? 'No había anuncios libres para subir en la cuenta forzada.'
                : 'No había anuncios libres para subir en las cuentas revisadas.';
            set_flash('error', $msg);
        } elseif ($status === 'forced_account_not_ready' || $status === 'forced_account_not_found' || $status === 'forced_account_outside_window') {
            set_flash('error', $error !== '' ? $error : 'La cuenta forzada no se pudo ejecutar en este momento.');
        } elseif ($status === 'error') {
            $accountLabel = trim((string)($result['account_label'] ?? ''));
            $msg = $error !== '' ? $error : 'Alguna cuenta devolvió error al intentar subir gratis.';
            if ($accountLabel !== '') {
                $msg = $accountLabel . ': ' . $msg;
            }
            set_flash('error', $msg);
        } else {
            set_flash('error', $error !== '' ? $error : 'La ejecución automática no pudo completar ninguna subida.');
        }
    }
    redirect_to(publicista_page_url('subir_anuncios'));
}

function action_delete_publicista_account() {
    $id = trim((string)request_post('id'));
    if ($id !== '') {
        list($canDelete, $errors) = publicista_account_can_delete($id);
        if (!$canDelete) {
            set_flash('error', 'No se puede eliminar la cuenta: ' . implode(' ', $errors));
            redirect_to(publicista_page_url('cuentas', array('edit' => $id)));
        }
        publicista_account_delete($id);
    }

    set_flash('ok', 'Cuenta eliminada.');
    redirect_to(publicista_page_url('cuentas'));
}


function action_save_publicista_planning() {
    $id = trim((string)request_post('id'));
    if ($id === '') $id = generate_id('pubplan');

    $existing = publicista_planning_get($id);
    $competition = json_decode((string)request_post('competition_snapshot_json', '{}'), true);
    $pricing = json_decode((string)request_post('pricing_snapshot_json', '{}'), true);
    $strategy = json_decode((string)request_post('strategy_snapshot_json', '[]'), true);
    $recommendationOptions = json_decode((string)request_post('recommendation_options_json', '{}'), true);
    $analysisSources = json_decode((string)request_post('analysis_sources_json', '[]'), true);
    $marketSignals = json_decode((string)request_post('market_signals_json', '{}'), true);
    $cost = json_decode((string)request_post('cost_snapshot_json', '{}'), true);
    $selectionRules = json_decode((string)request_post('selection_rules_json', '{}'), true);
    $summary = json_decode((string)request_post('summary_json', '{}'), true);

    if (!is_array($competition)) $competition = array();
    if (!is_array($pricing)) $pricing = array();
    if (!is_array($strategy)) $strategy = array();
    if (!is_array($recommendationOptions)) $recommendationOptions = array();
    if (!is_array($analysisSources)) $analysisSources = array();
    if (!is_array($marketSignals)) $marketSignals = array();
    if (!is_array($cost)) $cost = array();
    if (!is_array($selectionRules)) $selectionRules = array();
    if (!is_array($summary)) $summary = array();

    $city = trim((string)request_post('city'));
    $province = trim((string)request_post('province'));
    $category = trim((string)request_post('category'));
    $categoryLabel = trim((string)request_post('category_label'));
    $numProducts = max(1, (int)request_post('num_products_target', 1));
    $defaultName = publicista_planning_compose_name($city, $province, $categoryLabel, $numProducts);

    $row = publicista_planning_defaults($id);
    $row = array_merge($row, is_array($existing) ? $existing : array());
    $row['nombre'] = trim((string)request_post('nombre')) !== '' ? trim((string)request_post('nombre')) : $defaultName;
    $row['estado'] = 'saved';
    $row['version'] = max(1, (int)request_post('version', (int)($existing['version'] ?? 1)));
    $row['parent_planning_id'] = trim((string)request_post('parent_planning_id', (string)($existing['parent_planning_id'] ?? '')));
    $portalCode = trim((string)request_post('portal_code', 'destacamos'));
    $validPortals = array_keys(publicista_account_portal_options());
    if (!in_array($portalCode, $validPortals, true)) {
        $portalCode = 'destacamos';
    }
    $row['portal_code'] = $portalCode;
    $row['portal_label'] = trim((string)request_post('portal_label', $validPortals[$portalCode] ?? 'Destacamos'));
    $row['portal_url'] = trim((string)request_post('portal_url', ''));
    $row['city'] = $city;
    $row['province'] = $province;
    $row['category'] = $category;
    $row['category_label'] = $categoryLabel;
    $row['num_products_target'] = $numProducts;
    $row['competition_snapshot'] = $competition;
    $row['pricing_snapshot'] = $pricing;
    $row['strategy_snapshot'] = $strategy;
    $row['recommendation_options'] = $recommendationOptions;
    $row['analysis_sources'] = $analysisSources;
    $row['market_signals'] = $marketSignals;
    $row['default_option_code'] = trim((string)request_post('default_option_code', 'recommended')) !== '' ? trim((string)request_post('default_option_code', 'recommended')) : 'recommended';
    $row['cost_snapshot'] = $cost;
    $row['selection_rules'] = $selectionRules;
    $row['summary'] = !empty($summary) ? $summary : publicista_planning_build_summary($row);
    $row['calculated_at'] = trim((string)request_post('calculated_at')) !== '' ? trim((string)request_post('calculated_at')) : now_datetime();
    $row['notes'] = trim((string)request_post('notes'));
    $row['created_at'] = ($existing && isset($existing['created_at'])) ? $existing['created_at'] : now_datetime();
    $row['updated_at'] = now_datetime();

    list($ok, $saved) = publicista_planning_save($row);
    if (!$ok) {
        set_flash('error', 'No se pudo guardar la estrategia.');
        redirect_to(publicista_page_url('estrategias'));
    }
    set_flash('ok', 'Estrategia guardada en Publicista.');
    redirect_to(publicista_page_url('estrategias', array('planning' => $saved['id'])));
}

function action_duplicate_publicista_planning() {
    $id = trim((string)request_post('id'));
    if ($id === '') {
        set_flash('error', 'No se indicó la estrategia a duplicar.');
        redirect_to(publicista_page_url('estrategias'));
    }
    list($ok, $saved) = publicista_planning_duplicate_from_existing($id);
    if (!$ok) {
        set_flash('error', is_string($saved) ? $saved : 'No se pudo duplicar la estrategia.');
        redirect_to(publicista_page_url('estrategias'));
    }
    set_flash('ok', 'Estrategia duplicada como nueva versión guardada.');
    redirect_to(publicista_page_url('estrategias', array('planning' => $saved['id'])));
}

function action_delete_publicista_planning() {
    $id = trim((string)request_post('id'));
    if ($id !== '') {
        publicista_planning_delete($id);
        set_flash('ok', 'Estrategia eliminada.');
    }
    redirect_to(publicista_page_url('estrategias'));
}

function action_set_publicista_planning_status() {
    $id = trim((string)request_post('id'));
    if ($id === '') {
        set_flash('error', 'No se encontró la estrategia.');
        redirect_to(publicista_page_url('estrategias'));
    }
    $planning = publicista_planning_get($id);
    if (!$planning) {
        set_flash('error', 'No se encontró la estrategia.');
        redirect_to(publicista_page_url('estrategias'));
    }
    $planning['estado'] = 'saved';
    $planning['updated_at'] = now_datetime();
    publicista_planning_save($planning);
    set_flash('ok', 'La estrategia queda guardada.');
    redirect_to(publicista_page_url('estrategias', array('planning' => $id)));
}

function publicista_collect_post_ids($key) {
    $raw = request_post($key, array());
    if (!is_array($raw)) $raw = array($raw);
    $out = array();
    foreach ($raw as $value) {
        $value = trim((string)$value);
        if ($value === '') continue;
        $out[$value] = $value;
    }
    return array_values($out);
}

function publicista_collect_selected_listing_refs_post($key = 'selected_listing_refs') {
    $raw = request_post($key, array());
    if (!is_array($raw)) $raw = array($raw);
    $out = array();
    foreach ($raw as $value) {
        $parsed = function_exists('publicista_campaign_parse_listing_ref')
            ? publicista_campaign_parse_listing_ref($value)
            : array('account_id' => '', 'listing_id' => '');
        $ref = function_exists('publicista_campaign_listing_ref')
            ? publicista_campaign_listing_ref($parsed['account_id'], $parsed['listing_id'])
            : '';
        if ($ref === '') continue;
        $out[$ref] = $ref;
    }
    return array_values($out);
}

function action_save_publicista_campaign() {
    $id = trim((string)request_post('id'));
    if ($id === '') $id = generate_id('pubcamp');

    try {
        $existing = publicista_campaign_get($id);
        $planningId = trim((string)request_post('planning_id'));
        $planning = $planningId !== '' ? publicista_planning_get($planningId) : null;
        $requestedOptionCode = trim((string)request_post('strategy_option_code'));
        list($resolvedOptionCode, $resolvedOption) = $planning ? publicista_campaign_resolve_planning_option($planning, $requestedOptionCode) : array('', array());

        $productIds = publicista_collect_post_ids('product_ids');
        $accountIds = publicista_collect_post_ids('account_ids');
        $selectedListingRefs = publicista_collect_selected_listing_refs_post();
        $requiredProducts = max(1, (int)($planning['num_products_target'] ?? 1));

        $row = publicista_campaign_defaults($id);
        $row = array_merge($row, is_array($existing) ? $existing : array());
        $row['planning_id'] = $planningId;
        $row['strategy_option_code'] = $resolvedOptionCode !== '' ? $resolvedOptionCode : 'recommended';
        $row['strategy_option_label'] = trim((string)($resolvedOption['label'] ?? ''));
        $row['strategy_option_snapshot'] = is_array($resolvedOption) ? $resolvedOption : array();
        $row['nombre'] = trim((string)request_post('nombre'));
        if ($row['nombre'] === '' && $planning) {
            $row['nombre'] = publicista_campaign_compose_name($planning, count($productIds));
        }
        if ($row['nombre'] === '') $row['nombre'] = 'Campaña ' . $id;
        $row['product_ids'] = $productIds;
        $row['account_ids'] = $accountIds;
        $row['selected_listing_refs'] = $selectedListingRefs;
        $row['min_products'] = $requiredProducts;
        $row['max_products'] = $requiredProducts;
        $row['notes'] = trim((string)request_post('notes'));
        if (!$planning) {
            set_flash('error', 'Debes seleccionar un planning válido para la campaña.');
            redirect_to(publicista_page_url('campanas', array('edit' => $id)));
        }
        if ($resolvedOptionCode === '' || empty($resolvedOption)) {
            set_flash('error', 'Debes elegir una versión válida de la estrategia antes de crear la campaña.');
            redirect_to(publicista_page_url('campanas', array('edit' => $id, 'planning_id' => $planningId)));
        }

        list($canGenerateNow, $validationErrors, $validationWarnings) = publicista_campaign_validate_for_generation($row, $planning, $resolvedOption, true);
        if (!$canGenerateNow) {
            $messageParts = array();
            if (!empty($validationErrors)) {
                $messageParts[] = implode(' ', (array)$validationErrors);
            }
            if (!empty($validationWarnings)) {
                $messageParts[] = 'Avisos: ' . implode(' ', (array)$validationWarnings);
            }
            $message = trim(implode(' ', $messageParts));
            if ($message === '') {
                $message = 'No se puede crear la campaña todavía. Revisa estrategia, productos, cuentas y anuncios internos seleccionados.';
            }
            set_flash('error', $message);
            redirect_to(publicista_page_url('campanas', array('edit' => $id, 'planning_id' => $planningId)));
        }

        $row['estado'] = 'generating';
        $row['execution_summary'] = array_merge((array)($row['execution_summary'] ?? array()), array(
            'last_phase' => 'generation_queued',
            'last_generation_status' => 'queued',
            'last_generation_requested_at' => now_datetime(),
            'last_generation_error' => '',
            'last_generation_finished_at' => '',
        ));
        $row['created_at'] = ($existing && isset($existing['created_at'])) ? $existing['created_at'] : now_datetime();
        $row['updated_at'] = now_datetime();

        list($ok, $saved) = publicista_campaign_save($row);
        if (!$ok) {
            set_flash('error', 'No se pudo guardar la campaña.');
            redirect_to(publicista_page_url('campanas'));
        }

        set_flash('ok', 'Campaña guardada. La composición se está generando en segundo plano.');
        $targetUrl = publicista_page_url('campanas', array('edit' => $saved['id']));
        publicista_finish_redirect_response($targetUrl);

        // --- Conditional generation: skip if items exist, distribution_matrix set, and listing refs unchanged ---
        $existingItems = publicista_campaign_items_for_campaign($id);
        $hasItems = !empty($existingItems);
        $hasDistributionMatrix = !empty($existing['distribution_matrix'] ?? array());
        $normalizeIdsForCompare = function($values) {
            $normalized = array_values(array_filter(array_map('trim', (array)$values), function($value) {
                return $value !== '';
            }));
            sort($normalized, SORT_STRING);
            return $normalized;
        };

        $oldListingRefs = $normalizeIdsForCompare(is_array($existing['selected_listing_refs'] ?? null) ? $existing['selected_listing_refs'] : array());
        $newListingRefs = $normalizeIdsForCompare($selectedListingRefs);
        $listingRefsChanged = $newListingRefs !== $oldListingRefs;

        $oldAccountIds = $normalizeIdsForCompare(is_array($existing['account_ids'] ?? null) ? $existing['account_ids'] : array());
        $newAccountIds = $normalizeIdsForCompare($accountIds);
        $accountIdsChanged = $newAccountIds !== $oldAccountIds;

        $oldProductIds = $normalizeIdsForCompare(is_array($existing['product_ids'] ?? null) ? $existing['product_ids'] : array());
        $newProductIds = $normalizeIdsForCompare($productIds);
        $productIdsChanged = $newProductIds !== $oldProductIds;

        $oldPlanningId = trim((string)($existing['planning_id'] ?? ''));
        $planningChanged = $oldPlanningId !== $planningId;

        $oldStrategyOptionCode = trim((string)($existing['strategy_option_code'] ?? ''));
        $strategyChanged = $oldStrategyOptionCode !== $row['strategy_option_code'];

        $shouldRegenerate = !$hasItems
            || !$hasDistributionMatrix
            || $listingRefsChanged
            || $accountIdsChanged
            || $productIdsChanged
            || $planningChanged
            || $strategyChanged;

        if (!$shouldRegenerate) {
            // Items exist, distribution_matrix is set, and listing refs haven't changed — skip regeneration.
            $saved['estado'] = 'generated';
            $saved['execution_summary'] = array_merge((array)($saved['execution_summary'] ?? array()), array(
                'last_phase' => 'generation_skipped',
                'last_generation_status' => 'skipped',
                'last_generation_error' => '',
                'last_generation_finished_at' => now_datetime(),
            ));
            publicista_campaign_save($saved);
        } else {
            try {
                list($okGen, $generatedCampaign, $items, $meta) = publicista_campaign_generate_items($saved);
                if (!$okGen) {
                    $failedCampaign = publicista_campaign_get($saved['id']) ?: $saved;
                    $errors = is_array($meta['errors'] ?? null) ? $meta['errors'] : array();
                    $warnings = is_array($meta['warnings'] ?? null) ? $meta['warnings'] : array();
                    $message = 'No se pudo generar la composición de la campaña.';
                    if (!empty($errors)) $message .= ' ' . implode(' ', $errors);
                    if (!empty($warnings)) $message .= ' Avisos: ' . implode(' ', $warnings);
                    $failedCampaign['estado'] = 'error';
                    $failedCampaign['updated_at'] = now_datetime();
                    $failedCampaign['execution_summary'] = array_merge((array)($failedCampaign['execution_summary'] ?? array()), array(
                        'last_phase' => 'generation_error',
                        'last_generation_status' => 'failed',
                        'last_generation_error' => trim($message),
                        'last_generation_finished_at' => now_datetime(),
                    ));
                    publicista_campaign_save($failedCampaign);
                    exit;
                }

                $generatedCampaign['execution_summary'] = array_merge((array)($generatedCampaign['execution_summary'] ?? array()), array(
                    'last_phase' => 'generation_done',
                    'last_generation_status' => 'completed',
                    'last_generation_error' => '',
                    'last_generation_finished_at' => now_datetime(),
                ));
                publicista_campaign_save($generatedCampaign);
            } catch (Throwable $e) {
                if (function_exists('bootstrap_runtime_log_exception')) {
                    bootstrap_runtime_log_exception('action_save_publicista_campaign_background', $e);
                }
                $failedCampaign = publicista_campaign_get($saved['id']) ?: $saved;
                $failedCampaign['estado'] = 'error';
                $failedCampaign['updated_at'] = now_datetime();
                $failedCampaign['execution_summary'] = array_merge((array)($failedCampaign['execution_summary'] ?? array()), array(
                    'last_phase' => 'generation_fatal_error',
                    'last_generation_status' => 'failed',
                    'last_generation_error' => trim((string)$e->getMessage()),
                    'last_generation_finished_at' => now_datetime(),
                ));
                publicista_campaign_save($failedCampaign);
            }
        }

        exit;
    } catch (Throwable $e) {
        if (function_exists('bootstrap_runtime_log_exception')) {
            bootstrap_runtime_log_exception('action_save_publicista_campaign', $e);
            bootstrap_runtime_log('POST save_publicista_campaign | id=' . $id . ' | planning_id=' . trim((string)request_post('planning_id')) . ' | products=' . count((array)request_post('product_ids', array())) . ' | accounts=' . count((array)request_post('account_ids', array())) . ' | listings=' . count((array)request_post('selected_listing_refs', array())));
        }
        set_flash('error', 'Error interno al guardar la campaña: ' . trim((string)$e->getMessage()));
        redirect_to(publicista_page_url('campanas', array('edit' => $id)));
    }
}

function action_generate_publicista_campaign() {
    action_save_publicista_campaign();
}

function action_delete_publicista_campaign() {
    $id = trim((string)request_post('id'));
    if ($id !== '') {
        list($canDelete, $errors) = publicista_campaign_can_delete($id);
        if (!$canDelete) {
            set_flash('error', 'No se puede eliminar la campaña: ' . implode(' ', $errors));
            redirect_to(publicista_page_url('campanas', array('edit' => $id)));
        }
        publicista_campaign_delete_with_items($id);
        set_flash('ok', 'Campaña eliminada.');
    }
    redirect_to(publicista_page_url('campanas'));
}

function action_set_publicista_campaign_status() {
    $id = trim((string)request_post('id'));
    $status = trim((string)request_post('estado'));
    $campaign = $id !== '' ? publicista_campaign_get($id) : null;
    if (!$campaign) {
        set_flash('error', 'No se encontró la campaña.');
        redirect_to(publicista_page_url('campanas'));
    }
    $campaign['estado'] = $status;
    $campaign['updated_at'] = now_datetime();
    publicista_campaign_save($campaign);
    set_flash('ok', 'Estado de la campaña actualizado.');
    redirect_to(publicista_page_url('campanas', array('edit' => $id)));
}


function action_save_anuncio() {
    action_save_publicista_account();
}

function action_delete_anuncio() {
    action_delete_publicista_account();
}


function action_save_publicista_campaign_item_meta() {
    $id = trim((string)request_post('id'));
    $item = $id !== '' ? publicista_campaign_item_get($id) : null;
    if (!$item) {
        set_flash('error', 'No se encontró el item de campaña.');
        redirect_to(publicista_page_url('campanas'));
    }
    $campaignId = trim((string)($item['campaign_id'] ?? ''));
    $item['external_ad_id'] = trim((string)request_post('external_ad_id'));
    $item['phone_id'] = trim((string)request_post('phone_id'));
    $item['updated_at'] = now_datetime();
    publicista_campaign_item_save($item);
    set_flash('ok', 'Metadatos del item actualizados.');
    redirect_to(publicista_page_url('campanas', array('edit' => $campaignId)));
}

function action_upload_single_campaign_item() {
    $itemId = trim((string)request_post('item_id'));
    $campaignId = trim((string)request_post('campaign_id'));

    if ($itemId === '' || $campaignId === '') {
        set_flash('error', 'Faltan datos para subir el anuncio.');
        redirect_to(publicista_page_url('campanas'));
    }

    $item = publicista_campaign_item_get($itemId);
    if (!$item) {
        set_flash('error', 'No se encontró el anuncio a subir.');
        redirect_to(publicista_page_url('campanas', array('edit' => $campaignId)));
    }

    $campaign = publicista_campaign_get($campaignId);
    if (!$campaign) {
        set_flash('error', 'No se encontró la campaña.');
        redirect_to(publicista_page_url('campanas', array('edit' => $campaignId)));
    }

    $portalCode = trim((string)($item['portal_code'] ?? 'destacamos'));
    $portalLabel = $portalCode === 'destacamos' ? 'Destacamos' : ($portalCode === 'mundosex' ? 'MundosexAnuncio' : $portalCode);
    $accountSnapshot = is_array($item['account_snapshot'] ?? null) ? $item['account_snapshot'] : array();
    $accountName = trim((string)($accountSnapshot['data']['display_name'] ?? ($accountSnapshot['data']['login_user'] ?? ($item['account_id'] ?? 'desconocida'))));
    $productSnapshot = is_array($item['product_snapshot'] ?? null) ? $item['product_snapshot'] : array();
    $productName = trim((string)($productSnapshot['data']['nombre_trabajo'] ?? ($item['product_job_id'] ?? 'producto')));

    set_flash('ok', 'Subida individual lanzada en segundo plano para "' . $productName . '" → ' . $portalLabel . ' (' . $accountName . '). Recarga la página para ver el resultado.');
    $targetUrl = publicista_page_url('campanas', array('edit' => $campaignId));
    publicista_finish_redirect_response($targetUrl);

    try {
        list($ok, $savedItem, $result) = publicista_campaign_execute_item($campaign, $item, array());
        if ($ok && function_exists('publicista_task_ensure_free_bump_for_item')) {
            // El free bump ya se crea dentro de execute_item para destacamos, pero por si acaso
        }
    } catch (Throwable $e) {
        // El error ya queda registrado en el item vía publicista_campaign_execute_item
        // (que hace catch interno y guarda publish_result con estado 'failed')
    }

    exit;
}

function action_save_publicista_campaign_auto_rotation() {
    $campaignId = trim((string)request_post('campaign_id'));
    $campaign = $campaignId !== '' ? publicista_campaign_get($campaignId) : null;
    if (!$campaign) {
        set_flash('error', 'No se encontró la campaña.');
        redirect_to(publicista_page_url('campanas'));
    }

    $items = publicista_campaign_items_for_campaign($campaignId);
    if (empty($items)) {
        set_flash('error', 'Solo puedes configurar la auto-rotación cuando la campaña ya tiene composición generada.');
        redirect_to(publicista_page_url('campanas', array('edit' => $campaignId)));
    }

    $schedule = publicista_campaign_auto_rotation_schedule_defaults();
    $enabledRaw = trim((string)request_post('auto_rotation_enabled', ''));
    $schedule['enabled'] = in_array($enabledRaw, array('1', 'on', 'true', 'yes'), true);
    $schedule['daily_start_time'] = trim((string)request_post('auto_rotation_daily_start_time', $schedule['daily_start_time']));
    $schedule['daily_end_time'] = trim((string)request_post('auto_rotation_daily_end_time', $schedule['daily_end_time']));
    $schedule['every_hours'] = max(1, (int)request_post('auto_rotation_every_hours', request_post('auto_rotation_frequency_hours', $schedule['every_hours'])));
    $schedule['run_immediately_once'] = $schedule['enabled'] ? true : false;
    $schedule['last_run_at'] = trim((string)(($campaign['auto_rotation_schedule']['last_run_at'] ?? '')));
    $schedule['next_run_at'] = trim((string)(($campaign['auto_rotation_schedule']['next_run_at'] ?? '')));
    if ($schedule['enabled'] && $schedule['next_run_at'] === '') {
        $schedule['next_run_at'] = now_datetime();
    }
    $schedule['status'] = $schedule['enabled'] ? 'active' : 'disabled';
    $schedule['last_error'] = $schedule['enabled'] ? trim((string)(($campaign['auto_rotation_schedule']['last_error'] ?? ''))) : '';
    $schedule['updated_at'] = now_datetime();
    $schedule = publicista_campaign_auto_rotation_schedule_normalize($schedule);

    $campaign['auto_rotation_schedule'] = $schedule;
    $campaign['execution_summary'] = array_merge((array)($campaign['execution_summary'] ?? array()), array(
        'auto_rotation_status' => $schedule['enabled'] ? 'active' : 'inactive',
        'auto_rotation_last_status' => $schedule['enabled'] ? 'Configuración guardada' : 'Auto-rotación desactivada',
        'auto_rotation_last_error' => $schedule['enabled'] ? trim((string)(($campaign['execution_summary']['auto_rotation_last_error'] ?? ''))) : '',
        'auto_rotation_updated_at' => now_datetime(),
    ));
    $campaign['updated_at'] = now_datetime();

    publicista_campaign_save($campaign);

    if (!empty($schedule['enabled'])) {
        foreach (publicista_campaigns_get() as $other) {
            $otherId = trim((string)($other['id'] ?? ''));
            if ($otherId === '' || $otherId === $campaignId) continue;
            $otherSchedule = publicista_campaign_auto_rotation_schedule_normalize((array)($other['auto_rotation_schedule'] ?? array()));
            if (empty($otherSchedule['enabled'])) continue;
            $otherSchedule['enabled'] = false;
            $otherSchedule['status'] = 'disabled';
            $otherSchedule['updated_at'] = now_datetime();
            $other['auto_rotation_schedule'] = $otherSchedule;
            $other['execution_summary'] = array_merge((array)($other['execution_summary'] ?? array()), array(
                'auto_rotation_status' => 'inactive',
                'auto_rotation_last_status' => 'Desactivada automáticamente al activar otra campaña.',
            ));
            $other['updated_at'] = now_datetime();
            publicista_campaign_save($other);
        }
    }

    set_flash('ok', 'Horario de auto-rotación guardado.');
    redirect_to(publicista_page_url('campanas', array('edit' => $campaignId)));
}

function action_force_publicista_campaign_auto_rotation_now() {
    $campaignId = trim((string)request_post('campaign_id'));
    $campaign = $campaignId !== '' ? publicista_campaign_get($campaignId) : null;
    if (!$campaign) {
        set_flash('error', 'No se encontró la campaña.');
        redirect_to(publicista_page_url('campanas'));
    }

    $items = publicista_campaign_items_for_campaign($campaignId);
    if (empty($items)) {
        set_flash('error', 'La campaña no tiene items generados.');
        redirect_to(publicista_page_url('campanas', array('edit' => $campaignId)));
    }

    if (publicista_campaign_running_run($campaignId) || trim((string)($campaign['estado'] ?? '')) === 'uploading') {
        set_flash('error', 'Ya hay una subida en curso para esta campaña.');
        redirect_to(publicista_page_url('campanas', array('edit' => $campaignId)));
    }

    list($okDispatch, $savedCampaign, $meta) = publicista_campaign_dispatch_async($campaignId);
    if (!$okDispatch) {
        $msg = trim((string)($meta['error'] ?? 'No se pudo lanzar la resubida.'));
        set_flash('error', $msg);
        redirect_to(publicista_page_url('campanas', array('edit' => $campaignId)));
    }

    $runId = trim((string)($meta['run_id'] ?? ''));
    $msg = 'Resubida forzada lanzada en segundo plano.';
    if ($runId !== '') $msg .= ' Run: ' . $runId . '.';
    $msg .= ' Recibirás un aviso cuando termine.';
    set_flash('ok', $msg);

    $targetUrl = publicista_page_url('campanas', array('edit' => $campaignId));
    publicista_finish_redirect_response($targetUrl);

    try {
        list($okRun, $finalCampaign, $run, $runMeta) = publicista_campaign_execute($campaignId, array(
            'run_id' => $runId,
            'auto_rotation' => true,
        ));
        $notifyCampaign = $finalCampaign ?: (publicista_campaign_get($campaignId) ?: $campaign);
        $notifyRun = $run ?: ($runId !== '' ? publicista_run_get($runId) : array());
        publicista_campaign_notify_execution_finished($notifyCampaign, $notifyRun, $runMeta, $okRun);
    } catch (Throwable $e) {
        $failedCampaign = publicista_campaign_get($campaignId) ?: $campaign;
        if ($failedCampaign) {
            $failedCampaign['estado'] = 'error';
            $failedCampaign['updated_at'] = now_datetime();
            $failedCampaign['execution_summary'] = array_merge((array)($failedCampaign['execution_summary'] ?? array()), array(
                'last_phase' => 'error',
                'last_run_id' => $runId,
                'last_run_status' => 'failed',
                'last_run_error' => $e->getMessage(),
                'last_upload_finished_at' => now_datetime(),
                'auto_rotation_last_error' => $e->getMessage(),
            ));
            publicista_campaign_save($failedCampaign);
        }
        $failedRun = $runId !== '' ? publicista_run_get($runId) : null;
        if ($failedRun) {
            $failedRun['estado'] = 'failed';
            $failedRun['finished_at'] = now_datetime();
            $failedRun['summary'] = 'Error fatal durante la resubida forzada: ' . $e->getMessage();
            $failedRun['pipeline'] = array_merge((array)($failedRun['pipeline'] ?? array()), array(
                'status' => 'error',
                'stage' => 'fatal_error',
                'summary' => $failedRun['summary'],
            ));
            $failedRun['updated_at'] = $failedRun['finished_at'];
            publicista_run_save($failedRun);
        }
        publicista_campaign_notify_execution_finished(
            $failedCampaign ?: array('id' => $campaignId, 'nombre' => 'Campaña ' . $campaignId),
            $failedRun ?: array('id' => $runId),
            array('error' => $e->getMessage(), 'published' => 0, 'failed' => 0, 'results' => array()),
            false
        );
    }
    exit;
}

function action_rebalance_publicista_campaign_distribution() {
    $campaignId = trim((string)request_post('campaign_id'));
    if ($campaignId === '') {
        set_flash('error', 'Falta la campaña a reequilibrar.');
        redirect_to(publicista_page_url('campanas'));
    }

    $campaign = publicista_campaign_get($campaignId);
    if (!$campaign) {
        set_flash('error', 'No se encontró la campaña.');
        redirect_to(publicista_page_url('campanas'));
    }

    $matrixRaw = request_post('distribution_matrix', array());
    if (!is_array($matrixRaw)) {
        $matrixRaw = array();
    }

    list($ok, $updatedCampaign, $meta) = publicista_campaign_rebalance_distribution($campaign, $matrixRaw);
    if (!$ok) {
        $errors = is_array($meta['errors'] ?? null) ? $meta['errors'] : array();
        $warnings = is_array($meta['warnings'] ?? null) ? $meta['warnings'] : array();
        $message = 'No se pudo aplicar el reparto.';
        if (!empty($errors)) $message .= ' ' . implode(' ', $errors);
        if (!empty($warnings)) $message .= ' Avisos: ' . implode(' ', $warnings);
        set_flash('error', trim($message));
        redirect_to(publicista_page_url('campanas', array('edit' => $campaignId)));
    }

    $changed = (int)($meta['changed_items'] ?? 0);
    $warnings = array_values((array)($meta['warnings'] ?? array()));
    $message = 'Reparto actualizado correctamente. Items ajustados: ' . $changed . '.';

    $accountsById = array();
    foreach ((array)publicista_accounts_get(false) as $accountRow) {
        $aid = trim((string)($accountRow['id'] ?? ''));
        if ($aid === '') continue;
        $accountsById[$aid] = $accountRow;
    }

    $campaignAccountIds = array_values(array_filter(array_map('trim', (array)($campaign['account_ids'] ?? array()))));
    $requestedTotalsByAccount = array_fill_keys($campaignAccountIds, 0);
    foreach ((array)$matrixRaw as $productRow) {
        if (!is_array($productRow)) continue;
        foreach ($campaignAccountIds as $aid) {
            $requestedTotalsByAccount[$aid] += max(0, (int)($productRow[$aid] ?? 0));
        }
    }

    $appliedTotalsByAccount = array_fill_keys($campaignAccountIds, 0);
    $finalItems = publicista_campaign_items_for_campaign($campaignId);
    foreach ((array)$finalItems as $itemRow) {
        $aid = trim((string)($itemRow['account_id'] ?? ''));
        if ($aid === '' || !array_key_exists($aid, $appliedTotalsByAccount)) continue;
        $appliedTotalsByAccount[$aid]++;
    }

    $formatTotals = function($totals) use ($accountsById) {
        $parts = array();
        foreach ((array)$totals as $aid => $count) {
            $entity = is_array($accountsById[$aid] ?? null) ? $accountsById[$aid] : array();
            $name = trim((string)($entity['display_name'] ?? ($entity['login_user'] ?? $aid)));
            if ($name === '') $name = $aid;
            $parts[] = $name . ': ' . (int)$count;
        }
        return implode(' · ', $parts);
    };

    $message .= ' Solicitado: ' . $formatTotals($requestedTotalsByAccount) . '. Aplicado: ' . $formatTotals($appliedTotalsByAccount) . '.';
    if (!empty($warnings)) {
        $message .= ' Autoajustes: ' . implode(' ', $warnings);
    }
    set_flash('ok', $message);
    redirect_to(publicista_page_url('campanas', array('edit' => $campaignId)));
}


function action_approve_publicista_campaign() {
    $id = trim((string)request_post('id'));
    $campaign = $id !== '' ? publicista_campaign_get($id) : null;
    if (!$campaign) {
        set_flash('error', 'No se encontró la campaña a aprobar.');
        redirect_to(publicista_page_url('campanas'));
    }
    $items = publicista_campaign_items_for_campaign($id);
    if (empty($items)) {
        set_flash('error', 'Primero debes generar la composición antes de aprobar la campaña.');
        redirect_to(publicista_page_url('campanas', array('edit' => $id)));
    }
    $campaign['estado'] = 'approved';
    $campaign['approval_snapshot'] = array(
        'approved_at' => now_datetime(),
        'approved_items_count' => count($items),
        'approved_by' => 'operator',
    );
    $campaign['updated_at'] = now_datetime();
    publicista_campaign_save($campaign);
    set_flash('ok', 'Campaña aprobada. Ya puedes pulsar “Subir anuncios”.');
    redirect_to(publicista_page_url('campanas', array('edit' => $id)));
}


function publicista_mark_job_pipeline_failed($jobId, $message) {
    $jobId = trim((string)$jobId);
    if ($jobId === '') {
        return false;
    }

    $job = publicista_job_get($jobId);
    if (!$job) {
        return false;
    }

    $message = trim((string)$message);
    if ($message === '') {
        $message = 'El pipeline de imágenes terminó con error.';
    }

    $finishedAt = now_datetime();
    $job['estado'] = 'error';
    $job['pipeline'] = array_merge((array)($job['pipeline'] ?? array()), array(
        'finished_at' => $finishedAt,
        'status' => 'failed',
        'stage' => 'error',
        'summary' => $message,
    ));
    $job['processing'] = array_merge((array)($job['processing'] ?? array()), array(
        'last_action' => 'run_pipeline_reference_error',
        'last_finished_at' => $finishedAt,
        'last_error' => $message,
        'last_error_at' => $finishedAt,
    ));

    list($ok, $saved) = publicista_job_save($job);
    return (bool)$ok;
}

function publicista_finish_redirect_response($url) {
    ignore_user_abort(true);
    @set_time_limit(0);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Location: ' . $url, true, 302);
    header('Connection: close');
    header('Content-Length: 0');
    while (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }
}

function action_execute_publicista_campaign() {
    $id = trim((string)request_post('id'));
    $campaign = $id !== '' ? publicista_campaign_get($id) : null;
    if (!$campaign) {
        set_flash('error', 'No se encontró la campaña a ejecutar.');
        redirect_to(publicista_page_url('campanas'));
    }
    // Si esta campaña tiene auto-rotación habilitada, es la candidata a ser la activa
    $campaignForRotation = publicista_campaign_get($id);
    if ($campaignForRotation) {
        $rotSchedule = publicista_campaign_auto_rotation_schedule_normalize((array)($campaignForRotation['auto_rotation_schedule'] ?? array()));
        if (!empty($rotSchedule['enabled'])) {
            // Desactivar auto-rotación en todas las demás campañas
            foreach (publicista_campaigns_get() as $other) {
                $otherId = trim((string)($other['id'] ?? ''));
                if ($otherId === '' || $otherId === $id) continue;
                $otherSchedule = publicista_campaign_auto_rotation_schedule_normalize((array)($other['auto_rotation_schedule'] ?? array()));
                if (empty($otherSchedule['enabled'])) continue;
                $otherSchedule['enabled'] = false;
                $otherSchedule['status'] = 'disabled';
                $otherSchedule['updated_at'] = now_datetime();
                $other['auto_rotation_schedule'] = $otherSchedule;
                $other['execution_summary'] = array_merge((array)($other['execution_summary'] ?? array()), array(
                    'auto_rotation_status' => 'inactive',
                    'auto_rotation_last_status' => 'Desactivada: otra campaña recibió Subir anuncios.',
                ));
                $other['updated_at'] = now_datetime();
                publicista_campaign_save($other);
            }
            // Asegurar que esta campaña siga activa para auto-rotación
            $rotSchedule['enabled'] = true;
            $rotSchedule['status'] = 'active';
            $rotSchedule['updated_at'] = now_datetime();
            if (trim((string)$rotSchedule['next_run_at']) === '') {
                $rotSchedule['next_run_at'] = now_datetime();
            }
            $campaignForRotation['auto_rotation_schedule'] = $rotSchedule;
            $campaignForRotation['execution_summary'] = array_merge((array)($campaignForRotation['execution_summary'] ?? array()), array(
                'auto_rotation_status' => 'active',
                'auto_rotation_last_status' => 'Activada al recibir Subir anuncios.',
                'auto_rotation_updated_at' => now_datetime(),
            ));
            $campaignForRotation['updated_at'] = now_datetime();
            publicista_campaign_save($campaignForRotation);
        }
    }
    list($ok, $savedCampaign, $meta) = publicista_campaign_dispatch_async($id);
    if (!$ok) {
        $msg = trim((string)($meta['error'] ?? 'No se pudo subir la campaña.'));
        set_flash('error', $msg);
        redirect_to(publicista_page_url('campanas', array('edit' => $id)));
    }
    $runId = trim((string)($meta['run_id'] ?? ''));
    $msg = 'Subida lanzada en segundo plano.';
    if ($runId !== '') {
        $msg .= ' Run: ' . $runId . '.';
    }
    $msg .= ' Recibirás un aviso en el sistema cuando termine.';
    set_flash('ok', $msg);
    $targetUrl = publicista_page_url('campanas', array('edit' => $id));
    publicista_finish_redirect_response($targetUrl);

    try {
        list($okRun, $finalCampaign, $run, $runMeta) = publicista_campaign_execute($id, array('run_id' => $runId));
        $notifyCampaign = $finalCampaign ?: (publicista_campaign_get($id) ?: $campaign);
        $notifyRun = $run ?: ($runId !== '' ? publicista_run_get($runId) : array());
        publicista_campaign_notify_execution_finished($notifyCampaign, $notifyRun, $runMeta, $okRun);
    } catch (Throwable $e) {
        $failedCampaign = publicista_campaign_get($id) ?: $campaign;
        if ($failedCampaign) {
            $failedCampaign['estado'] = 'error';
            $failedCampaign['updated_at'] = now_datetime();
            $failedCampaign['execution_summary'] = array_merge((array)($failedCampaign['execution_summary'] ?? array()), array(
                'last_phase' => 'error',
                'last_run_id' => $runId,
                'last_run_status' => 'failed',
                'last_run_error' => $e->getMessage(),
                'last_upload_finished_at' => now_datetime(),
            ));
            publicista_campaign_save($failedCampaign);
        }

        $failedRun = $runId !== '' ? publicista_run_get($runId) : null;
        if ($failedRun) {
            $failedRun['estado'] = 'failed';
            $failedRun['finished_at'] = now_datetime();
            $failedRun['summary'] = 'Error fatal durante la subida: ' . $e->getMessage();
            $failedRun['pipeline'] = array_merge((array)($failedRun['pipeline'] ?? array()), array(
                'status' => 'error',
                'stage' => 'fatal_error',
                'summary' => $failedRun['summary'],
            ));
            $failedRun['updated_at'] = $failedRun['finished_at'];
            publicista_run_save($failedRun);
        }

        publicista_campaign_notify_execution_finished(
            $failedCampaign ?: array('id' => $id, 'nombre' => 'Campaña ' . $id),
            $failedRun ?: array('id' => $runId),
            array(
                'error' => $e->getMessage(),
                'published' => 0,
                'failed' => 0,
                'results' => array(),
            ),
            false
        );
    }

    exit;
}

function action_sync_publicista_campaign_to_girlsconf() {
    $id = trim((string)request_post('id'));
    $campaign = $id !== '' ? publicista_campaign_get($id) : null;
    if (!$campaign) {
        set_flash('error', 'No se encontró la campaña.');
        redirect_to(publicista_page_url('campanas'));
    }

    if (!function_exists('publicista_sync_girlsconf_to_girlsconf')) {
        set_flash('error', 'El módulo de GirlsConf no está disponible.');
        redirect_to(publicista_page_url('campanas', array('edit' => $id)));
    }

    try {
        $ok = publicista_sync_girlsconf_to_girlsconf($id);
        if ($ok) {
            set_flash('ok', 'GirlsConf sincronizado correctamente. Se han desactivado todos los perfiles activos y se han creado los de esta campaña.');
        } else {
            set_flash('error', 'No se pudo sincronizar GirlsConf. Verifica que la campaña tenga productos con imágenes.');
        }
    } catch (Throwable $e) {
        set_flash('error', 'Error al sincronizar GirlsConf: ' . $e->getMessage());
    }

    redirect_to(publicista_page_url('campanas', array('edit' => $id)));
}

function action_resubmit_publicista_campaign_portal() {
    $id = trim((string)request_post('id'));
    $portalCode = trim((string)request_post('portal_code'));
    $campaign = $id !== '' ? publicista_campaign_get($id) : null;
    if (!$campaign) {
        set_flash('error', 'No se encontró la campaña.');
        redirect_to(publicista_page_url('campanas'));
    }
    if ($portalCode === '' || !in_array($portalCode, array('destacamos', 'mundosex'), true)) {
        set_flash('error', 'Portal no válido para resubida.');
        redirect_to(publicista_page_url('campanas', array('edit' => $id)));
    }

    list($okDispatch, $savedCampaign, $meta) = publicista_campaign_dispatch_async($id);
    if (!$okDispatch) {
        $msg = trim((string)($meta['error'] ?? 'No se pudo lanzar la resubida.'));
        set_flash('error', $msg);
        redirect_to(publicista_page_url('campanas', array('edit' => $id)));
    }

    $runId = trim((string)($meta['run_id'] ?? ''));
    $msg = 'Resubida solo ' . $portalCode . ' lanzada en segundo plano.';
    if ($runId !== '') $msg .= ' Run: ' . $runId . '.';
    set_flash('ok', $msg);

    $targetUrl = publicista_page_url('campanas', array('edit' => $id));
    publicista_finish_redirect_response($targetUrl);

    try {
        list($okRun, $finalCampaign, $run, $runMeta) = publicista_campaign_execute($id, array(
            'run_id' => $runId,
            'portal_filter' => $portalCode,
        ));
        $notifyCampaign = $finalCampaign ?: (publicista_campaign_get($id) ?: $campaign);
        $notifyRun = $run ?: ($runId !== '' ? publicista_run_get($runId) : array());
        publicista_campaign_notify_execution_finished($notifyCampaign, $notifyRun, $runMeta, $okRun);
    } catch (Throwable $e) {
        $failedCampaign = publicista_campaign_get($id) ?: $campaign;
        if ($failedCampaign) {
            $failedCampaign['estado'] = 'error';
            $failedCampaign['updated_at'] = now_datetime();
            $failedCampaign['execution_summary'] = array_merge((array)($failedCampaign['execution_summary'] ?? array()), array(
                'last_phase' => 'error',
                'last_run_id' => $runId,
                'last_run_status' => 'failed',
                'last_run_error' => $e->getMessage(),
                'last_upload_finished_at' => now_datetime(),
            ));
            publicista_campaign_save($failedCampaign);
        }
        $failedRun = $runId !== '' ? publicista_run_get($runId) : null;
        if ($failedRun) {
            $failedRun['estado'] = 'failed';
            $failedRun['finished_at'] = now_datetime();
            $failedRun['summary'] = 'Error fatal durante la resubida ' . $portalCode . ': ' . $e->getMessage();
            $failedRun['pipeline'] = array_merge((array)($failedRun['pipeline'] ?? array()), array(
                'status' => 'error', 'stage' => 'fatal_error', 'summary' => $failedRun['summary'],
            ));
            $failedRun['updated_at'] = $failedRun['finished_at'];
            publicista_run_save($failedRun);
        }
        publicista_campaign_notify_execution_finished(
            $failedCampaign ?: array('id' => $id, 'nombre' => 'Campaña ' . $id),
            $failedRun ?: array('id' => $runId),
            array('error' => $e->getMessage(), 'published' => 0, 'failed' => 0, 'results' => array()),
            false
        );
    }

    exit;
}

function action_stop_publicista_campaign_run() {
    $id = trim((string)request_post('id'));
    $campaign = $id !== '' ? publicista_campaign_get($id) : null;
    if (!$campaign) {
        set_flash('error', 'No se encontró la campaña.');
        redirect_to(publicista_page_url('campanas'));
    }

    $actor = function_exists('current_user') ? current_user() : array();
    $requestedBy = is_logged_in() ? trim((string)($actor['username'] ?? 'user')) : 'system';
    list($ok, $run, $meta) = publicista_campaign_request_stop($id, $requestedBy);
    if (!$ok) {
        $msg = trim((string)($meta['error'] ?? 'No hay una subida en curso para detener.'));
        set_flash('error', $msg);
        redirect_to(publicista_page_url('campanas', array('edit' => $id)));
    }

    $runId = trim((string)($run['id'] ?? ''));
    $msg = 'Solicitud de parada enviada correctamente.';
    if ($runId !== '') {
        $msg .= ' Run: ' . $runId . '.';
    }
    $msg .= ' La ejecución finalizará de forma limpia en cuanto complete el paso actual.';
    set_flash('ok', $msg);
    redirect_to(publicista_page_url('campanas', array('edit' => $id)));
}

function action_run_publicista_task() {
    $id = trim((string)request_post('id'));
    $task = $id !== '' ? publicista_task_get($id) : null;
    if (!$task) {
        set_flash('error', 'No se encontró la tarea automática.');
        redirect_to(publicista_page_url('campanas'));
    }
    list($ok, $savedTask, $result) = publicista_task_execute($id);
    $campaignId = trim((string)($task['campaign_id'] ?? ''));
    if ($ok) set_flash('ok', 'Tarea ejecutada correctamente.');
    else set_flash('error', 'La tarea devolvió error: ' . trim((string)($result['error'] ?? 'sin detalle')));
    redirect_to(publicista_page_url('campanas', array('edit' => $campaignId)));
}

function action_set_publicista_task_status() {
    $id = trim((string)request_post('id'));
    $estado = trim((string)request_post('estado'));
    $task = $id !== '' ? publicista_task_get($id) : null;
    if (!$task) {
        set_flash('error', 'No se encontró la tarea automática.');
        redirect_to(publicista_page_url('campanas'));
    }
    $task['estado'] = $estado;
    $task['updated_at'] = now_datetime();
    publicista_task_save($task);
    set_flash('ok', 'Estado de la tarea actualizado.');
    redirect_to(publicista_page_url('campanas', array('edit' => trim((string)($task['campaign_id'] ?? '')))));
}

function action_save_telefono() {
    if (!auth_is_admin()) {
        set_flash('error', 'Acceso denegado.');
        redirect_to(comercial_page_url('lineas'));
    }
    if (!csrf_validate((string)request_post('csrf_token'))) {
        set_flash('error', 'La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.');
        redirect_to(comercial_page_url('lineas'));
    }

    $id = trim(request_post('id'));
    if ($id === '') $id = generate_id('tf');

    $wahaPort = trim((string)request_post('waha_port'));
    $wahaSession = trim((string)request_post('waha'));
    if (!telefonos_waha_port_is_allowed($wahaPort, true)) {
        set_flash('error', 'Puerto WAHA no permitido. Usa 3000-3011, 3031 o déjalo vacío.');
        redirect_to(comercial_page_url('lineas', array('edit' => $id)));
    }
    if ($wahaPort !== '' && $wahaPort !== TELEFONOS_WAHA_PERSONAL_PORT) {
        $effectiveSession = $wahaSession !== '' ? $wahaSession : 'default';
        if (!telefonos_waha_session_is_valid($effectiveSession)) {
            set_flash('error', 'Sesión WAHA no válida.');
            redirect_to(comercial_page_url('lineas', array('edit' => $id)));
        }
    }

    $existing = storage_find_by_id('telefonos.json', $id);

    $row = array(
        'id' => $id,
        'nombre' => trim(request_post('nombre')),
        'tfono' => trim(request_post('tfono')),
        'uso' => trim(request_post('uso')),
        'pin' => trim(request_post('pin')),
        'compania' => trim(request_post('compania')),
        'waha_port' => $wahaPort,
        'waha' => $wahaPort === TELEFONOS_WAHA_PERSONAL_PORT ? 'default' : $wahaSession,
        'notas' => trim(request_post('notas')),
        'destacamos_id' => trim(request_post('destacamos_id')),
        'updated_at' => now_datetime(),
        'created_at' => ($existing && isset($existing['created_at'])) ? $existing['created_at'] : now_datetime()
    );

    storage_upsert('telefonos.json', $row);
    set_flash('ok', 'Teléfono guardado.');
    redirect_to(comercial_page_url('lineas', array('edit' => $id)));
}

function action_delete_telefono() {
    if (!auth_is_admin()) {
        set_flash('error', 'Acceso denegado.');
        redirect_to(comercial_page_url('lineas'));
    }
    if (!csrf_validate((string)request_post('csrf_token'))) {
        set_flash('error', 'La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.');
        redirect_to(comercial_page_url('lineas'));
    }

    $id = trim(request_post('id'));
    if ($id !== '') {
        storage_delete('telefonos.json', $id);
    }

    set_flash('ok', 'Teléfono eliminado.');
    redirect_to(comercial_page_url('lineas'));
}

function action_save_comercial_distribution() {
    $settings = comercial_get_settings();
    $settings['global_daily_target'] = max(1, (int)request_post('global_daily_target', $settings['global_daily_target']));
    comercial_save_settings($settings);

    $raw = isset($_POST['distribution_percent']) && is_array($_POST['distribution_percent'])
        ? $_POST['distribution_percent']
        : array();

    $normalized = comercial_normalize_percentage_map($raw);
    $rows = comercial_get_processes();
    foreach ($rows as &$row) {
        $id = (string)($row['id'] ?? '');
        if ($id !== '' && isset($normalized[$id])) {
            $row['daily_target_percent'] = (float)$normalized[$id];
            $row['daily_target_absolute'] = 0;
            $row['next_run_at'] = '';
        }
    }
    unset($row);

    comercial_save_processes($rows);
    set_flash('ok', 'Reparto comercial guardado y normalizado a 100%.');
    redirect_to(comercial_page_url('procesos'));
}

function action_toggle_comercial_process_enabled() {
    $id = trim((string)request_post('id'));
    $row = $id !== '' ? comercial_get_process($id) : null;
    if (!$row) {
        set_flash('error', 'Proceso no encontrado.');
        redirect_to(comercial_page_url('procesos'));
    }

    $newEnabled = (int)request_post('enabled') === 1 ? 1 : 0;
    $nombre = trim((string)($row['nombre'] ?? $row['slug'] ?? $id));

    // Detectar si el guardrail de apagado masivo va a bloquear el cambio
    // antes de ejecutarlo, para informar al usuario con claridad.
    if ($newEnabled === 0) {
        $allProcesses = comercial_get_processes();
        $currentEnabledCount = 0;
        foreach ($allProcesses as $p) {
            if (!empty($p['enabled'])) {
                $currentEnabledCount++;
            }
        }
        if ($currentEnabledCount <= 1 && !empty($row['enabled'])) {
            // El guardrail impediría apagar el último proceso activo.
            // Informamos al usuario y no intentamos el cambio.
            set_flash('error', 'No se puede apagar "' . $nombre . '" porque es el único proceso activo. Enciende otro proceso antes de apagar este.');
            redirect_to(comercial_page_url('procesos', array('edit' => $row['id'])));
        }
    }

    $row['enabled'] = $newEnabled;
    $row['next_run_at'] = '';
    comercial_upsert_process($row);

    // Verificar que el cambio se persistió realmente en disco.
    // Si el archivo JSON no es escribible por el servidor (permisos/propietario),
    // storage_write falla silenciosamente y la página recargada muestra el estado antiguo.
    $reloaded = comercial_get_process($id);
    if ($reloaded && (int)($reloaded['enabled'] ?? 0) !== $newEnabled) {
        set_flash('error', 'No se pudo guardar el cambio. El archivo de procesos no es escribible por el servidor. Contacta con el administrador del sistema.');
        redirect_to(comercial_page_url('procesos', array('edit' => $row['id'])));
    }

    $accion = $newEnabled ? 'encendido' : 'apagado';
    set_flash('ok', 'Proceso "' . $nombre . '" ' . $accion . ' correctamente.');
    redirect_to(comercial_page_url('procesos', array('edit' => $row['id'])));
}

function action_dismiss_aviso() {
    $id = trim(request_post('id'));
    $redirect = trim(request_post('redirect', 'index.php?page=dashboard'));
    $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

    if (!csrf_validate((string)request_post('csrf_token'))) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(419);
            echo json_encode(array(
                'ok' => false,
                'message' => 'La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.',
            ));
            exit;
        }

        set_flash('error', 'La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.');
        redirect_to($redirect);
    }

    if ($id !== '') {
        aviso_dismiss($id);
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => true, 'id' => $id));
        exit;
    }

    set_flash('ok', 'Aviso descartado.');

    $sep = (strpos($redirect, '?') === false) ? '?' : '&';
    $redirect .= $sep . '_d=' . time();

    redirect_to($redirect);
}

function action_mark_avisos_read() {
    $scope = trim((string)request_post('scope', 'active_unread'));
    $redirect = trim((string)request_post('redirect', 'index.php?page=dashboard'));
    $isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    $ids = array();

    if ($scope === 'active_unread') {
        $ids = avisos_active_unread_ids();
    } elseif ($scope === 'active_all') {
        $ids = avisos_active_all_ids();
    } elseif (isset($_POST['ids']) && is_array($_POST['ids'])) {
        foreach ((array)$_POST['ids'] as $id) {
            $id = trim((string)$id);
            if ($id !== '') $ids[] = $id;
        }
    }

    if (!empty($ids)) {
        avisos_mark_as_read_and_dismiss(array_values(array_unique($ids)));
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => true, 'count' => count($ids)));
        exit;
    }

    if (!empty($ids)) {
        set_flash('ok', 'Avisos descartados correctamente.');
    } else {
        set_flash('ok', 'No había avisos para descartar.');
    }

    redirect_to($redirect);
}

function action_create_manual_aviso() {
    $title = trim(request_post('title'));
    $message = trim(request_post('message'));
    $scheduledFor = trim(request_post('scheduled_for'));
    $severity = trim(request_post('severity', 'media'));

    if ($title === '' || $scheduledFor === '') {
        set_flash('error', 'Debes indicar al menos título y fecha/hora.');
        redirect_to(avisos_page_url(array('avtab' => 'planned')));
    }

    if (!strtotime(str_replace('T', ' ', $scheduledFor))) {
        set_flash('error', 'La fecha/hora del aviso no es válida.');
        redirect_to(avisos_page_url(array('avtab' => 'planned')));
    }

    avisos_create_manual_planned($title, $message, $scheduledFor, $severity);

    set_flash('ok', 'Aviso manual planificado correctamente.');
    redirect_to(avisos_page_url(array('avtab' => 'planned')));
}

function action_delete_planned_aviso() {
    $id = trim(request_post('id'));

    if ($id !== '') {
        avisos_delete_planned($id);
    }

    set_flash('ok', 'Aviso planificado eliminado.');
    redirect_to(avisos_page_url(array('avtab' => 'planned')));
}

function action_save_agenda() {
    $id = trim(request_post('id'));
    if ($id === '') $id = generate_id('ag');

    $existing = storage_find_by_id('agenda.json', $id);

    $row = array(
        'id' => $id,
        'nombre' => trim(request_post('nombre')),
        'telefono' => trim(request_post('telefono')),
        'observaciones' => trim(request_post('observaciones')),
        'updated_at' => now_datetime(),
        'created_at' => ($existing && isset($existing['created_at'])) ? $existing['created_at'] : now_datetime()
    );

    storage_upsert('agenda.json', $row);
    set_flash('ok', 'Contacto de agenda guardado.');
    redirect_to('index.php?page=josue&tab=agenda&edit=' . urlencode($id));
}

function action_delete_agenda() {
    $id = trim(request_post('id'));
    if ($id !== '') {
        storage_delete('agenda.json', $id);
    }

    set_flash('ok', 'Contacto de agenda eliminado.');
    redirect_to('index.php?page=josue&tab=agenda');
}

function action_save_eureka() {
    $id = trim(request_post('id'));
    if ($id === '') $id = generate_id('eur');

    $existing = storage_find_by_id('eurekas.json', $id);
    $descripcion = trim(request_post('descripcion'));

    if ($descripcion === '') {
        set_flash('error', 'La descripción no puede estar vacía.');
        redirect_to('index.php?page=josue&tab=eurekas' . ($id !== '' ? '&edit=' . urlencode($id) : ''));
    }

    $promptCodex = trim((string)($existing['prompt_codex'] ?? ''));
    $promptGeneratedAt = trim((string)($existing['prompt_generated_at'] ?? ''));
    if ($existing && trim((string)($existing['descripcion'] ?? '')) !== $descripcion) {
        $promptCodex = '';
        $promptGeneratedAt = '';
    }

    $row = array(
        'id' => $id,
        'descripcion' => $descripcion,
        'estado' => trim((string)($existing['estado'] ?? 'pendiente')) ?: 'pendiente',
        'prompt_codex' => $promptCodex,
        'prompt_generated_at' => $promptGeneratedAt,
        'source' => trim((string)($existing['source'] ?? 'manual')) ?: 'manual',
        'updated_at' => now_datetime(),
        'created_at' => ($existing && isset($existing['created_at'])) ? $existing['created_at'] : now_datetime(),
    );

    storage_upsert('eurekas.json', $row);
    set_flash('ok', 'Eureka guardada.');
    redirect_to('index.php?page=josue&tab=eurekas&edit=' . urlencode($id));
}

function action_generate_eureka_prompt() {
    $id = trim(request_post('id'));
    $row = $id !== '' ? storage_find_by_id('eurekas.json', $id) : null;
    if (!$row) {
        set_flash('error', 'Eureka no encontrada.');
        redirect_to('index.php?page=josue&tab=eurekas');
    }

    $descripcion = trim((string)($row['descripcion'] ?? ''));
    if ($descripcion === '') {
        set_flash('error', 'La eureka no tiene descripción para convertirla en prompt.');
        redirect_to('index.php?page=josue&tab=eurekas&edit=' . urlencode($id));
    }

    $row['prompt_codex'] = eureka_build_codex_prompt($descripcion);
    $row['prompt_generated_at'] = now_datetime();
    $row['updated_at'] = now_datetime();
    storage_upsert('eurekas.json', $row);

    set_flash('ok', 'Prompt Codex generado para la eureka.');
    redirect_to('index.php?page=josue&tab=eurekas&edit=' . urlencode($id));
}

function action_delete_eureka() {
    $id = trim(request_post('id'));
    if ($id !== '') {
        storage_delete('eurekas.json', $id);
    }

    set_flash('ok', 'Eureka eliminada.');
    redirect_to('index.php?page=josue&tab=eurekas');
}

function action_set_eureka_estado() {
    $id = trim(request_post('id'));
    $estado = trim(request_post('estado'));
    $allowed = array('pendiente', 'descartada', 'cumplida', 'cumplida_v2');

    $row = $id !== '' ? storage_find_by_id('eurekas.json', $id) : null;
    if (!$row) {
        set_flash('error', 'Eureka no encontrada.');
        redirect_to('index.php?page=josue&tab=eurekas');
    }

    if (!in_array($estado, $allowed, true)) {
        set_flash('error', 'Estado no válido.');
        redirect_to('index.php?page=josue&tab=eurekas');
    }

    $row['estado'] = $estado;
    $row['updated_at'] = now_datetime();
    storage_upsert('eurekas.json', $row);

    set_flash('ok', 'Estado de la eureka actualizado.');
    redirect_to('index.php?page=josue&tab=eurekas&edit=' . urlencode($id));
}

function action_regenerate_publicista_copy_title() {
    $id = trim((string)request_post('id'));
    $titleIndex = (int)request_post('title_index', -1);
    $extraConcepts = publicista_normalize_copy_extra_concepts_input(request_post('copy_extra_concepts'));

    list($ok, $result) = publicista_regenerate_copy_title_option($id, $titleIndex, $extraConcepts);
    if (!$ok) {
        set_flash('error', is_string($result) ? $result : 'No se pudo regenerar el título.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    set_flash('ok', 'Título regenerado.');
    redirect_to(publicista_tab_url(array('job' => $id)));
}

function action_regenerate_publicista_copy_ad() {
    $id = trim((string)request_post('id'));
    $slot = trim((string)request_post('slot'));
    $extraConcepts = publicista_normalize_copy_extra_concepts_input(request_post('copy_extra_concepts'));

    list($ok, $result) = publicista_regenerate_copy_ad_slot($id, $slot, $extraConcepts);
    if (!$ok) {
        set_flash('error', is_string($result) ? $result : 'No se pudo regenerar el anuncio.');
        redirect_to(publicista_tab_url(array('job' => $id)));
    }

    set_flash('ok', 'Anuncio regenerado.');
    redirect_to(publicista_tab_url(array('job' => $id)));
}

function action_generate_publicista_copy_pack() {
    $id = trim((string)request_post('id'));
    $extraConcepts = publicista_normalize_copy_extra_concepts_input(request_post('copy_extra_concepts'));
    $job = $id !== '' ? publicista_job_get($id) : null;
    if (!$job) {
        set_flash('error', 'No se encontró el trabajo de Publicista.');
        redirect_to(publicista_tab_url());
    }

    set_flash('ok', 'Generación de textos lanzada en segundo plano. Recibirás un aviso cuando termine.');
    $targetUrl = publicista_tab_url(array('job' => $id));
    publicista_finish_redirect_response($targetUrl);

    try {
        list($ok, $result) = publicista_generate_copy_pack($id, true, $extraConcepts);
        $latestJob = publicista_job_get($id) ?: $job;
        if (function_exists('publicista_notify_copy_pack_finished')) {
            publicista_notify_copy_pack_finished($latestJob, $ok, $result);
        }
    } catch (Throwable $e) {
        if (function_exists('bootstrap_runtime_log_exception')) {
            bootstrap_runtime_log_exception('action_generate_publicista_copy_pack_background', $e);
        }
        if (function_exists('publicista_notify_copy_pack_finished')) {
            publicista_notify_copy_pack_finished($job, false, 'Error interno al generar textos: ' . trim((string)$e->getMessage()));
        }
    }

    exit;
}

function publicista_normalize_copy_extra_concepts_input($raw) {
    $text = trim((string)$raw);
    if ($text === '') return '';

    $text = str_replace(array("\r\n", "\r"), "\n", $text);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) > 1200) {
            $text = mb_substr($text, 0, 1200);
        }
    } else {
        if (strlen($text) > 1200) {
            $text = substr($text, 0, 1200);
        }
    }

    return trim($text);
}

function action_save_comercial_settings() {
    if (!auth_is_admin()) {
        set_flash('error', 'Acceso denegado.');
        redirect_to(comercial_page_url('ajustes'));
    }
    if (!csrf_validate((string)request_post('csrf_token'))) {
        set_flash('error', 'La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.');
        redirect_to(comercial_page_url('ajustes'));
    }

    $current = comercial_get_settings();
    $numericFields = array(
        'curl_timeout_sec', 'global_daily_target', 'ban_window_size', 'ban_fail_streak_warning', 'ban_fail_streak_pause',
        'ban_fail_ratio_warning', 'ban_fail_ratio_pause', 'cooldown_minutes_warning', 'cooldown_minutes_pause',
        'conversation_max_auto_turns', 'conversation_max_defers'
    );

    foreach ($numericFields as $field) {
        $current[$field] = request_post($field, $current[$field]);
    }

    $requestedHost = trim((string)request_post('waha_host', $current['waha_host']));
    if (!telefonos_waha_host_is_allowed($requestedHost)) {
        set_flash('error', 'Host WAHA no permitido.');
        redirect_to(comercial_page_url('ajustes'));
    }
    $current['waha_host'] = $requestedHost;
    $current['waha_api_key'] = trim((string)request_post('waha_api_key', $current['waha_api_key']));
    $requestedSession = trim((string)request_post('waha_session', $current['waha_session']));
    if (!telefonos_waha_session_is_valid($requestedSession)) {
        set_flash('error', 'Sesión WAHA no válida.');
        redirect_to(comercial_page_url('ajustes'));
    }
    $current['waha_session'] = $requestedSession;
    $current['auto_followup_enabled'] = request_post('auto_followup_enabled') ? 1 : 0;
    $current['auto_pause_enabled'] = request_post('auto_pause_enabled') ? 1 : 0;
    $current['ia_second_turn_enabled'] = request_post('ia_second_turn_enabled') ? 1 : 0;
    $current['ia_learning_enabled'] = request_post('ia_learning_enabled') ? 1 : 0;
    comercial_save_settings($current);
    set_flash('ok', 'Ajustes de Comercial guardados.');
    redirect_to(comercial_page_url('ajustes'));
}

function action_save_comercial_process() {
    $id = trim((string)request_post('id'));
    $existing = $id !== '' ? comercial_get_process($id) : null;
    $row = is_array($existing) ? $existing : comercial_default_process_seed(trim((string)request_post('slug', 'nuevo')));

    $fields = array(
        'id', 'nombre', 'slug', 'source_type', 'priority',
        'window_start_hour', 'window_end_hour',
        'source_mysql_host', 'source_mysql_db', 'source_mysql_user', 'source_mysql_pass', 'source_mysql_query',
        'source_phone_field', 'source_queue_files', 'message_templates', 'followup_templates', 'positive_keywords', 'negative_keywords',
        'ia_context_prompt', 'signal_detection_rules', 'conversation_max_auto_turns', 'escalation_score_threshold'
    );

    foreach ($fields as $field) {
        $row[$field] = request_post($field, isset($row[$field]) ? $row[$field] : '');
    }

    $row['assigned_line_ids'] = isset($_POST['assigned_line_ids']) && is_array($_POST['assigned_line_ids']) ? $_POST['assigned_line_ids'] : array();
    $row['enabled'] = request_post('enabled') ? 1 : 0;
    $row['auto_followup'] = request_post('auto_followup') ? 1 : 0;
    $row['auto_create_lead'] = request_post('auto_create_lead') ? 1 : 0;
    $row['ia_learning_enabled'] = request_post('ia_learning_enabled') ? 1 : 0;
    $row['ia_opener_enabled'] = request_post('ia_opener_enabled') ? 1 : 0;
    $row['auto_notify_operator'] = request_post('auto_notify_operator') ? 1 : 0;
    comercial_upsert_process($row);
    set_flash('ok', 'Proceso comercial guardado.');
    redirect_to(comercial_page_url('procesos', array('edit' => $row['id'])));
}

function action_upload_plaza_room_photo() {
    $redirect = comercial_page_url('procesos', array('edit' => 'comproc_plaza'));

    if (!csrf_validate((string)request_post('csrf_token'))) {
        set_flash('error', 'La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.');
        redirect_to($redirect);
    }

    $names = (array)($_FILES['photos']['name'] ?? array());
    if (empty($names) || trim((string)($names[0] ?? '')) === '') {
        set_flash('error', 'No se recibió ninguna foto.');
        redirect_to($redirect);
    }

    $tmpNames = (array)($_FILES['photos']['tmp_name'] ?? array());
    $sizes = (array)($_FILES['photos']['size'] ?? array());
    $errs = (array)($_FILES['photos']['error'] ?? array());

    $photos = plaza_room_photos_get();
    $uploaded = 0;
    $errors = array();

    for ($i = 0; $i < count($names); $i++) {
        if (count($photos) >= 12) {
            $errors[] = 'Máximo 12 fotos alcanzado.';
            break;
        }
        if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $tmp = trim((string)($tmpNames[$i] ?? ''));
        if ($tmp === '' || !is_file($tmp)) continue;
        if ((int)($sizes[$i] ?? 0) > COMPARTIR_PHOTO_MAX_BYTES) {
            $errors[] = 'Foto demasiado grande (máx 5MB).';
            continue;
        }

        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string)@finfo_file($finfo, $tmp) : '';
        if ($finfo) @finfo_close($finfo);
        if (!in_array($mime, COMPARTIR_ALLOWED_MIMES, true)) {
            $errors[] = 'Formato no permitido. Usa JPG, PNG o WebP.';
            continue;
        }

        $result = compartir_store_image($tmp, $mime, 'Habitación Casa Burriana', 'Habitación disponible');
        if (empty($result['ok'])) {
            $errors[] = (string)($result['error'] ?? 'Error al subir la foto.');
            continue;
        }

        $photos[] = array(
            'url' => (string)$result['url'],
            'img' => (string)$result['img'],
            'added_at' => now_datetime(),
        );
        $uploaded++;
    }

    plaza_room_photos_save($photos);

    if ($uploaded > 0) {
        set_flash('ok', $uploaded . ' foto(s) subida(s) correctamente.');
    }
    if (!empty($errors)) {
        set_flash('error', implode(' ', array_slice($errors, 0, 3)));
    }
    redirect_to($redirect);
}

function action_delete_plaza_room_photo() {
    $redirect = comercial_page_url('procesos', array('edit' => 'comproc_plaza'));

    if (!csrf_validate((string)request_post('csrf_token'))) {
        set_flash('error', 'La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.');
        redirect_to($redirect);
    }

    $index = (int)request_post('index', -1);
    $photos = plaza_room_photos_get();
    if ($index >= 0 && isset($photos[$index])) {
        $url = trim((string)($photos[$index]['url'] ?? ''));
        array_splice($photos, $index, 1);
        plaza_room_photos_save($photos);
        if ($url !== '') {
            compartir_delete_folder_by_url($url);
        }
        set_flash('ok', 'Foto eliminada.');
    } else {
        set_flash('error', 'Foto no encontrada.');
    }
    redirect_to($redirect);
}

function action_save_comercial_blacklist() {
    $id = trim((string)request_post('id'));
    $existing = $id !== '' ? comercial_get_blacklist_entry($id) : null;
    $row = is_array($existing) ? $existing : comercial_blacklist_entry_defaults($id);
    $row['phone'] = trim((string)request_post('phone'));
    $row['notes'] = trim((string)request_post('notes'));

    if (comercial_only_digits($row['phone']) === '') {
        set_flash('error', 'El teléfono de la blacklist no puede estar vacío.');
        redirect_to(comercial_page_url('blacklist' . ($id !== '' ? '' : ''), array_filter(array('edit_blacklist' => $id !== '' ? $id : ''))));
    }

    comercial_upsert_blacklist_entry($row);
    set_flash('ok', 'Teléfono guardado en la blacklist global.');
    redirect_to(comercial_page_url('blacklist'));
}

function action_delete_comercial_blacklist() {
    $id = trim((string)request_post('id'));
    if ($id !== '') {
        comercial_delete_blacklist_entry($id);
    }
    set_flash('ok', 'Teléfono eliminado de la blacklist global.');
    redirect_to(comercial_page_url('blacklist'));
}

function action_comercial_run_tick() {
    $processId = trim((string)request_post('process_id'));
    $results = comercial_run_tick($processId);
    if (empty($results)) {
        set_flash('ok', 'Tick ejecutado. No había procesos listos para enviar.');
    } else {
        set_flash('ok', 'Tick ejecutado. Procesos revisados: ' . count($results));
    }
    $tab = $processId !== '' ? 'procesos' : 'resumen';
    $params = $processId !== '' ? array('edit' => $processId) : array();
    redirect_to(comercial_page_url($tab, $params));
}

function action_comercial_run_test_probe() {
    $result = comercial_send_test_probe();
    if (!empty($result['ok'])) {
        set_flash('ok', 'Prueba enviada al ' . comercial_test_probe_phone() . ' usando el proceso Plaza. Ahora responde desde ese móvil para verificar clasificación y respuesta automática.');
    } else {
        set_flash('error', trim((string)($result['error'] ?? 'No se pudo lanzar la prueba comercial.')));
    }
    redirect_to(comercial_page_url('resumen'));
}

function action_comercial_reset_test_probe() {
    $result = comercial_reset_test_probe();
    set_flash('ok', 'Prueba reiniciada. Hilos borrados: ' . (int)($result['threads_deleted'] ?? 0) . ' · leads borrados: ' . (int)($result['leads_deleted'] ?? 0));
    redirect_to(comercial_page_url('resumen'));
}

function action_save_comercial_line_state() {
    $lineId = trim((string)request_post('line_id'));
    $status = trim((string)request_post('status', 'active'));
    if ($lineId !== '') {
        $patch = array('status' => $status);
        if ($status === 'active') {
            $patch['consecutive_failures'] = 0;
            $patch['cooldown_until'] = '';
            $patch['effective_power_factor'] = 1;
            $patch['last_error'] = '';
        }
        comercial_update_line_state($lineId, $patch);
        comercial_event_append('line_status_manual', array('line_id' => $lineId, 'status' => $status));
    }
    set_flash('ok', 'Estado de línea actualizado.');
    redirect_to(comercial_page_url('lineas'));
}

function action_comercial_check_lines_health() {
    $lineId = trim((string)request_post('line_id'));

    if ($lineId !== '') {
        $lines = comercial_list_lines_indexed();
        if (!isset($lines[$lineId])) {
            set_flash('error', 'No se encontró la línea a comprobar.');
            redirect_to(comercial_page_url('lineas'));
        }

        $result = comercial_check_line_health($lines[$lineId], true);
        $label = trim((string)($result['line_name'] ?? 'la línea'));
        $healthLabel = comercial_line_health_label((string)($result['health_status'] ?? 'unknown'));
        $detail = trim((string)($result['error'] ?? ''));
        set_flash(
            ($result['health_status'] ?? '') === 'up' ? 'ok' : 'error',
            $detail !== ''
                ? ('Comprobación de ' . $label . ': ' . $healthLabel . '. ' . $detail)
                : ('Comprobación de ' . $label . ': ' . $healthLabel . '.')
        );
        redirect_to(comercial_page_url('lineas'));
    }

    $results = comercial_refresh_lines_health(true);
    $checked = 0;
    $up = 0;
    $down = 0;
    $starting = 0;

    foreach ((array)$results as $row) {
        if (empty($row['checked'])) continue;
        $checked++;
        $healthStatus = trim((string)($row['health_status'] ?? 'unknown'));
        if ($healthStatus === 'up') $up++;
        elseif ($healthStatus === 'starting') $starting++;
        elseif ($healthStatus === 'down') $down++;
    }

    set_flash('ok', 'Comprobación WAHA completada. Revisadas: ' . $checked . ' · activas: ' . $up . ' · arrancando: ' . $starting . ' · caídas: ' . $down);
    redirect_to(comercial_page_url('lineas'));
}

function action_comercial_set_thread_stage() {
    $threadId = trim((string)request_post('thread_id'));
    $stage = trim((string)request_post('stage', 'qualified'));
    $returnStageFilter = trim((string)request_post('return_stage_filter', ''));
    $returnViewThread = trim((string)request_post('return_view_thread', ''));
    $threads = comercial_get_threads();
    foreach ($threads as $thread) {
        if ((string)$thread['id'] !== $threadId) continue;
        $thread = comercial_thread_apply_stage($thread, $stage);
        comercial_upsert_thread($thread);
        comercial_event_append('thread_stage_manual', array('thread_id' => $threadId, 'stage' => $stage));
        break;
    }
    set_flash('ok', 'Conversación actualizada.');
    redirect_to(comercial_page_url('conversaciones', array_filter(array(
        'stage_filter' => $returnStageFilter,
        'view_thread' => $returnViewThread,
    ))));
}

function action_comercial_send_thread_message() {
    $threadId = trim((string)request_post('thread_id'));
    $manualText = trim((string)request_post('manual_text'));
    $returnStageFilter = trim((string)request_post('return_stage_filter', ''));
    $returnViewThread = trim((string)request_post('return_view_thread', ''));
    if ($threadId === '' || $manualText === '') {
        set_flash('error', 'Falta la conversación o el texto a enviar.');
        redirect_to(comercial_page_url('conversaciones', array_filter(array(
            'stage_filter' => $returnStageFilter,
            'view_thread' => $returnViewThread,
        ))));
    }

    $thread = null;
    foreach (comercial_get_threads() as $row) {
        if ((string)$row['id'] === $threadId) {
            $thread = $row;
            break;
        }
    }

    if (!$thread) {
        set_flash('error', 'No se encontró la conversación.');
        redirect_to(comercial_page_url('conversaciones', array_filter(array(
            'stage_filter' => $returnStageFilter,
            'view_thread' => $returnViewThread,
        ))));
    }

    $send = comercial_send_thread_message($thread, $manualText, array(
        'human_taken' => true,
        'event_type' => 'manual_outbound_sent',
    ));
    if (!empty($send['ok'])) {
        $threadAfter = comercial_normalize_thread((array)($send['thread'] ?? $thread));
        $threadAfter = comercial_record_thread_reply_feedback($threadAfter, $manualText);
        comercial_upsert_thread($threadAfter);
        if (!empty(comercial_get_settings()['ia_learning_enabled'])) {
            comercial_ai_memory_store_feedback((string)($threadAfter['process_slug'] ?? ''), 'human_reply', $manualText, array(
                'accepted' => !empty($threadAfter['last_ai_feedback_meta']['accepted']),
                'edited' => !empty($threadAfter['last_ai_feedback_meta']['edited']),
                'led_to_lead' => false,
                'trigger_text' => trim((string)($threadAfter['last_inbound_text'] ?? '')),
            ));
        }
        set_flash('ok', 'Mensaje enviado por la misma línea de origen.');
    } else {
        set_flash('error', trim((string)($send['error'] ?? 'No se pudo enviar el mensaje.')));
    }
    redirect_to(comercial_page_url('conversaciones', array_filter(array(
        'stage_filter' => $returnStageFilter,
        'view_thread' => $returnViewThread !== '' ? $returnViewThread : $threadId,
    ))));
}

function action_comercial_promote_thread() {
    $threadId = trim((string)request_post('thread_id'));
    list($ok, $result) = comercial_promote_thread_to_lead($threadId);
    if ($ok) {
        set_flash('ok', 'Lead comercial creado.');
        redirect_to(comercial_page_url('leads'));
    }
    set_flash('error', is_string($result) ? $result : 'No se pudo crear el lead.');
    redirect_to(comercial_page_url('conversaciones'));
}

function action_comercial_run_ai_qualification() {
    $threads = comercial_get_threads();
    $processes = comercial_get_processes();
    $processesBySlug = array();
    foreach ($processes as $p) {
        $processesBySlug[trim((string)($p['slug'] ?? ''))] = $p;
    }

    $analyzed = 0;
    $passed = 0;
    $cutoffTs = time() - (4 * 86400);

    foreach ($threads as $thread) {
        $thread = comercial_normalize_thread($thread);
        $stage = trim((string)($thread['stage'] ?? ''));

        // Saltar descartados
        if ($stage === 'discarded') continue;

        // Solo últimos 4 días
        $updatedTs = strtotime((string)($thread['updated_at'] ?? ''));
        if ($updatedTs < $cutoffTs) continue;

        // Saltar si ya fue analizado recientemente (< 24h)
        $lastAnalysis = trim((string)($thread['ai_qualified_at'] ?? ''));
        if ($lastAnalysis !== '' && strtotime($lastAnalysis) > time() - 86400) continue;

        // Saltar si no tiene respuestas y no es very_hot
        $replies = (int)($thread['replies_count'] ?? 0);
        if ($replies === 0 && $stage !== 'very_hot') continue;

        $processSlug = trim((string)($thread['process_slug'] ?? ''));
        $process = isset($processesBySlug[$processSlug]) ? $processesBySlug[$processSlug] : array();

        $result = comercial_ai_qualify_lead($thread, $process);
        $analyzed++;

        if (!empty($result['ok'])) {
            $thread['ai_qualified_at'] = now_datetime();
            $thread['ai_interest_score'] = (int)($result['interest_score'] ?? 0);
            $thread['ai_summary'] = trim((string)($result['summary'] ?? ''));
            $thread['ai_action_advice'] = trim((string)($result['action_advice'] ?? ''));
            $thread['ai_is_genuine'] = !empty($result['is_genuine_lead']);
            $thread['ai_buying_signals'] = (array)($result['buying_signals'] ?? array());
            $thread['ai_risk_signals'] = (array)($result['risk_signals'] ?? array());
            $thread['ai_suggested_priority'] = trim((string)($result['suggested_priority'] ?? ''));
            $thread['ai_reason'] = trim((string)($result['reason'] ?? ''));

            comercial_upsert_thread($thread);

            if (!empty($result['is_genuine_lead'])) {
                $passed++;
            }
        }
    }

    set_flash('ok', "IA analizó {$analyzed} conversaciones. {$passed} pasaron el filtro.", 'ok');
    redirect_to(comercial_page_url('agente'));
}

function action_comercial_export_threads_csv() {
    $stageFilter = trim((string)request_post('stage_filter', 'all'));
    $allowedFilters = array('all', 'opened', 'responded', 'qualified', 'very_hot', 'discarded');

    if (!in_array($stageFilter, $allowedFilters, true)) {
        set_flash('error', 'Filtro de exportación no válido.');
        redirect_to(comercial_page_url('conversaciones'));
    }

    $threads = comercial_get_threads();
    $linesIndexed = comercial_list_lines_indexed();

    $filteredThreads = array();
    foreach ($threads as $thread) {
        if (comercial_thread_matches_filter($thread, $stageFilter)) {
            $filteredThreads[] = $thread;
        }
    }

    $safeFilter = preg_replace('/[^a-z0-9_\-]/i', '_', $stageFilter);
    $filename = 'comercial_conversaciones_' . $safeFilter . '_' . date('Ymd_His') . '.xlsx';

    $xlsxPath = comercial_build_threads_xlsx_export($filteredThreads, $linesIndexed, $stageFilter);
    if ($xlsxPath === '') {
        set_flash('error', 'No se pudo generar el fichero XLSX.');
        redirect_to(comercial_page_url('conversaciones', array('stage_filter' => $stageFilter)));
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string)filesize($xlsxPath));
    header('Pragma: no-cache');
    header('Expires: 0');

    readfile($xlsxPath);
    @unlink($xlsxPath);
    exit;
}

function comercial_build_threads_xlsx_export($threads, $linesIndexed, $stageFilter) {
    $tmpFile = tempnam(sys_get_temp_dir(), 'comercial_threads_xlsx_');
    if ($tmpFile === false) {
        return '';
    }
    $xlsxPath = $tmpFile . '.xlsx';
    @unlink($xlsxPath);

    $zip = new ZipArchive();
    if ($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($tmpFile);
        return '';
    }

    $row = 1;
    $sheetRowsXml = '';

    $sheetRowsXml .= comercial_xlsx_row($row++, array(
        array('v' => 'Exportación conversaciones comercial', 's' => 1),
        array('v' => '', 's' => 0),
    ));
    $sheetRowsXml .= comercial_xlsx_row($row++, array(
        array('v' => 'Filtro', 's' => 2),
        array('v' => (string)$stageFilter, 's' => 0),
    ));
    $sheetRowsXml .= comercial_xlsx_row($row++, array(
        array('v' => 'Generado', 's' => 2),
        array('v' => (string)now_datetime(), 's' => 0),
    ));
    $sheetRowsXml .= comercial_xlsx_row($row++, array(
        array('v' => 'Total conversaciones', 's' => 2),
        array('v' => (string)count((array)$threads), 's' => 0),
    ));
    $sheetRowsXml .= comercial_xlsx_row($row++, array(
        array('v' => '', 's' => 0),
        array('v' => '', 's' => 0),
    ));

    foreach ((array)$threads as $thread) {
        $stage = trim((string)($thread['stage'] ?? ''));
        $lineId = trim((string)($thread['line_id'] ?? ''));
        $lineName = isset($linesIndexed[$lineId]) ? trim((string)($linesIndexed[$lineId]['nombre'] ?? '')) : '';

        $sheetRowsXml .= comercial_xlsx_row($row++, array(
            array('v' => 'line_name', 's' => 3),
            array('v' => $lineName, 's' => 4),
        ));
        $sheetRowsXml .= comercial_xlsx_row($row++, array(
            array('v' => 'target_phone', 's' => 3),
            array('v' => (string)($thread['target_phone'] ?? ''), 's' => 4),
        ));
        $sheetRowsXml .= comercial_xlsx_row($row++, array(
            array('v' => 'stage_label', 's' => 3),
            array('v' => comercial_thread_stage_label($stage), 's' => 4),
        ));
        $sheetRowsXml .= comercial_xlsx_row($row++, array(
            array('v' => 'created_at', 's' => 3),
            array('v' => (string)($thread['created_at'] ?? ''), 's' => 4),
        ));
        $sheetRowsXml .= comercial_xlsx_row($row++, array(
            array('v' => 'updated_at', 's' => 3),
            array('v' => (string)($thread['updated_at'] ?? ''), 's' => 4),
        ));

        $sheetRowsXml .= comercial_xlsx_row($row++, array(
            array('v' => 'fecha del mensaje', 's' => 5),
            array('v' => 'mensaje', 's' => 5),
        ));

        $history = comercial_thread_history($thread, 5000);
        foreach ((array)$history as $entry) {
            $direction = strtolower(trim((string)($entry['direction'] ?? '')));
            $isInbound = in_array($direction, array('inbound', 'in', 'incoming', 'entrada', 'received', 'reply'), true);
            $messageStyle = $isInbound ? 6 : 7;
            $sheetRowsXml .= comercial_xlsx_row($row++, array(
                array('v' => (string)($entry['ts'] ?? ''), 's' => $messageStyle),
                array('v' => (string)($entry['text'] ?? ''), 's' => $messageStyle),
            ));
        }

        $sheetRowsXml .= comercial_xlsx_row($row++, array(
            array('v' => '', 's' => 0),
            array('v' => '', 's' => 0),
        ));
    }

    $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
        . '</Types>';

    $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
        . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
        . '</Relationships>';

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Conversaciones" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $stylesXml = comercial_threads_xlsx_styles_xml();

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="18"/>'
        . '<cols><col min="1" max="1" width="28" customWidth="1"/><col min="2" max="2" width="110" customWidth="1"/></cols>'
        . '<sheetData>' . $sheetRowsXml . '</sheetData>'
        . '</worksheet>';

    $createdIso = gmdate('Y-m-d\TH:i:s\Z');
    $coreXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
        . '<dc:title>Exportación Conversaciones Comercial</dc:title>'
        . '<dc:creator>Lamamionline Control</dc:creator>'
        . '<cp:lastModifiedBy>Lamamionline Control</cp:lastModifiedBy>'
        . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $createdIso . '</dcterms:created>'
        . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $createdIso . '</dcterms:modified>'
        . '</cp:coreProperties>';

    $appXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
        . '<Application>Lamamionline Control</Application>'
        . '</Properties>';

    $zip->addFromString('[Content_Types].xml', $contentTypesXml);
    $zip->addFromString('_rels/.rels', $relsXml);
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
    $zip->addFromString('xl/styles.xml', $stylesXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->addFromString('docProps/core.xml', $coreXml);
    $zip->addFromString('docProps/app.xml', $appXml);
    $zip->close();
    @unlink($tmpFile);

    return $xlsxPath;
}

function comercial_threads_xlsx_styles_xml() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2">'
        . '<font><sz val="11"/><color rgb="FF1F2937"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FF111827"/><name val="Calibri"/><family val="2"/></font>'
        . '</fonts>'
        . '<fills count="6">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFDCEBFF"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFECFDF5"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"><color rgb="FFE5E7EB"/></left><right style="thin"><color rgb="FFE5E7EB"/></right><top style="thin"><color rgb="FFE5E7EB"/></top><bottom style="thin"><color rgb="FFE5E7EB"/></bottom><diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="8">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

function comercial_xlsx_cell_ref($column, $row) {
    $letters = '';
    $n = (int)$column;
    while ($n > 0) {
        $mod = ($n - 1) % 26;
        $letters = chr(65 + $mod) . $letters;
        $n = (int)(($n - $mod) / 26);
    }
    return $letters . (string)$row;
}

function comercial_xlsx_escape($value) {
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function comercial_xlsx_row($rowIndex, $cells) {
    $rowXml = '<row r="' . (int)$rowIndex . '">';
    $column = 1;
    foreach ((array)$cells as $cell) {
        $ref = comercial_xlsx_cell_ref($column, (int)$rowIndex);
        $style = isset($cell['s']) ? (int)$cell['s'] : 0;
        $value = isset($cell['v']) ? (string)$cell['v'] : '';
        $rowXml .= '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . comercial_xlsx_escape($value) . '</t></is></c>';
        $column++;
    }
    $rowXml .= '</row>';
    return $rowXml;
}

// ─── Estados Wasap ────────────────────────────────────────────────────────

function action_save_estados_wasap_config() {
    $config = publicista_estados_wasap_save_config($_POST);
    set_flash('ok', 'Configuración de estados Wasap guardada.');
    redirect_to('index.php?page=publicista&tab=estados_wasap');
}

function action_publicar_estado_manual() {
    $result = publicista_estados_wasap_publicar_ahora();
    if (!empty($result['ok'])) {
        set_flash('ok', $result['message']);
    } else {
        set_flash('error', $result['error']);
    }
    redirect_to('index.php?page=publicista&tab=estados_wasap');
}

// ─── Jostal Contratos ─────────────────────────────────────────────────────

function action_save_jostal_contrato() {
    $clientaId = trim(request_post('clienta_id'));
    if ($clientaId === '') {
        set_flash('error', 'Falta el ID de clienta.');
        redirect_to('index.php?page=jostal&tab=clientas');
    }

    $existing = contrato_find_by_clienta($clientaId);
    $id = $existing ? $existing['id'] : generate_id('ctr');
    $esNuevo = !$existing;

    $ventana = contrato_calcular_ventana_15dias();

    $contenidoRaw = trim(request_post('contenido_habitacion', ''));
    $contenidoLineas = array();
    if ($contenidoRaw !== '') {
        $lineas = preg_split('/\r\n|\r|\n/', $contenidoRaw);
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea !== '') $contenidoLineas[] = $linea;
        }
    }

    $row = array(
        'id' => $id,
        'clienta_id' => $clientaId,
        'estado' => $existing ? ($existing['estado'] ?? 'borrador') : 'borrador',
        'datos_arrendadora' => array(
            'nombre' => trim(request_post('arrendadora_nombre', 'Josué')),
            'dni' => trim(request_post('arrendadora_dni', '')),
            'telefono' => trim(request_post('arrendadora_telefono', '')),
            'domicilio' => trim(request_post('arrendadora_domicilio', '')),
        ),
        'datos_ocupante' => array(
            'nombre_real' => trim(request_post('ocupante_nombre_real', '')),
            'dni' => trim(request_post('ocupante_dni', '')),
            'telefono' => trim(request_post('ocupante_telefono', '')),
        ),
        'habitacion_plaza' => trim(request_post('habitacion_plaza', '')),
        'direccion_inmueble' => trim(request_post('direccion_inmueble', '')),
        'precio_semanal' => trim(request_post('precio_semanal', '')),
        'fianza' => trim(request_post('fianza', '')),
        'contenido_habitacion' => $contenidoLineas,
        'fecha_inicio' => $ventana['fecha_inicio'],
        'fecha_fin' => $ventana['fecha_fin'],
        'firma_ocupante' => $existing ? ($existing['firma_ocupante'] ?? contrato_default_row()['firma_ocupante']) : contrato_default_row()['firma_ocupante'],
        'firma_arrendadora' => $existing ? ($existing['firma_arrendadora'] ?? contrato_default_row()['firma_arrendadora']) : contrato_default_row()['firma_arrendadora'],
        'url_firma_token' => $existing ? ($existing['url_firma_token'] ?? generate_id('ctrtkn')) : generate_id('ctrtkn'),
        'updated_at' => now_datetime(),
        'created_at' => $existing ? ($existing['created_at'] ?? now_datetime()) : now_datetime(),
    );

    storage_upsert('contratos.json', $row);

    set_flash('ok', $esNuevo ? 'Contrato creado.' : 'Contrato actualizado.', 'celebrate');
    redirect_to('index.php?page=jostal&tab=clientas&edit=' . urlencode($clientaId));
}

function action_submit_contrato_firma() {
    $token = trim(request_post('token', ''));
    $contrato = contrato_find_by_token($token);

    if (!$contrato) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'error' => 'Contrato no encontrado.'));
        exit;
    }

    $firmaDataUrl = trim(request_post('firma_data_url', ''));
    if ($firmaDataUrl === '') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'error' => 'No se ha recibido la firma.'));
        exit;
    }

    $contrato['estado'] = 'firmado';
    $contrato['firma_ocupante'] = array(
        'data_url' => $firmaDataUrl,
        'fecha_hora' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'dispositivo' => trim(request_post('dispositivo', $_SERVER['HTTP_USER_AGENT'] ?? '')),
        'navegador' => trim(request_post('navegador', '')),
    );
    $contrato['updated_at'] = now_datetime();

    storage_upsert('contratos.json', $contrato);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array('ok' => true, 'message' => 'Contrato firmado correctamente.'));
    exit;
}

function action_tts() {
    $text = trim((string)request_post('text'));
    if ($text === '') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'error' => 'No text provided'));
        exit;
    }

    // Preprocess: replace symbols for natural speech
    $text = str_replace(array('€', '°C', '%'), array(' euros', ' grados', ' por ciento'), $text);

    $cfg = voice_ai_config();
    if (!$cfg['configured']) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'error' => 'AI not configured'));
        exit;
    }

    $ttsCfg = voice_tts_config();
    $voice = $ttsCfg['voice'] !== '' ? $ttsCfg['voice'] : 'nova';

    // Build TTS URL based on provider (DeepSeek has no TTS → always use OpenAI)
    $ttsUrl = ($cfg['provider'] === 'openai')
        ? 'https://api.openai.com/v1/audio/speech'
        : 'https://api.openai.com/v1/audio/speech'; // DeepSeek has no TTS API

    $payload = array(
        'model' => 'tts-1',
        'input' => $text,
        'voice' => $voice,
        'response_format' => 'mp3',
    );

    $ch = curl_init($ttsUrl);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $cfg['api_key'],
            'Content-Type: application/json',
        ),
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
    ));

    $audio = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($httpCode !== 200 || !is_string($audio) || $audio === '') {
        http_response_code(502);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'error' => "TTS API returned $httpCode"));
        exit;
    }

    header('Content-Type: ' . ($contentType ?: 'audio/mpeg'));
    header('Content-Length: ' . strlen($audio));
    header('Cache-Control: public, max-age=3600');
    echo $audio;
    exit;
}

function action_voice_check_reminders() {
    $reminders = voice_check_reminders();
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    echo json_encode($reminders, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── YouTube actions ─────────────────────────────────────────────────

function action_youtube_search() {
    $query = trim((string)request_post('query'));
    if ($query === '') {
        _youtube_json_response(array('ok' => false, 'error' => 'Query vacio', 'results' => array()));
        return;
    }

    $results = youtube_search($query, 48);

    // Guardar ultima busqueda en sesion
    $_SESSION['youtube_last_search'] = array(
        'query' => $query,
        'results' => $results,
        'searched_at' => now_datetime(),
    );

    _youtube_json_response(array('ok' => true, 'query' => $query, 'results' => $results));
}

function action_youtube_suggest() {
    $history = storage_read('youtube_history.json');
    if (!is_array($history)) $history = array();

    $suggestions = youtube_ai_suggest($history, 5);

    // Hacer busqueda real de cada sugerencia
    $allSuggestions = array();
    foreach ($suggestions as $term) {
        $results = youtube_search($term);
        if (!empty($results)) {
            $allSuggestions[] = array(
                'term' => $term,
                'results' => array_slice($results, 0, 3),
            );
        }
    }

    // ── Fallback A: si no hay resultados, buscar por canales del historial ──
    if (empty($allSuggestions)) {
        $channelQueries = array();
        foreach ($history as $item) {
            $ch = trim((string)($item['channel_name'] ?? ''));
            if ($ch !== '' && !isset($channelQueries[$ch])) {
                $channelQueries[$ch] = true;
            }
        }
        foreach (array_keys($channelQueries) as $chQuery) {
            if (count($allSuggestions) >= 5) break;
            $results = youtube_search($chQuery);
            if (!empty($results)) {
                $allSuggestions[] = array(
                    'term' => 'Canal: ' . $chQuery,
                    'results' => array_slice($results, 0, 3),
                );
            }
        }
    }

    // ── Fallback B: queries populares evergreen ──
    if (empty($allSuggestions)) {
        $trendingQueries = array(
            'música 2026 España',
            'noticias hoy España',
            'deportes highlights',
            'música en español 2026',
            'tendencias YouTube España',
        );
        foreach ($trendingQueries as $tq) {
            if (count($allSuggestions) >= 5) break;
            $results = youtube_search($tq);
            if (!empty($results)) {
                $allSuggestions[] = array(
                    'term' => 'Tendencia: ' . $tq,
                    'results' => array_slice($results, 0, 3),
                );
            }
        }
    }

    _youtube_json_response(array('ok' => true, 'suggestions' => $allSuggestions));
}

function action_youtube_log_history() {
    $videoId = trim((string)request_post('video_id'));
    $title = trim((string)request_post('title'));
    $thumbnail = trim((string)request_post('thumbnail'));
    $channelName = trim((string)request_post('channel_name'));
    $publishedTime = trim((string)request_post('published_time'));

    if ($videoId === '') {
        _youtube_json_response(array('ok' => false, 'error' => 'video_id requerido'));
        return;
    }

    $history = storage_read('youtube_history.json');
    if (!is_array($history)) $history = array();

    // Evitar duplicados consecutivos
    if (!empty($history) && $history[0]['video_id'] === $videoId) {
        _youtube_json_response(array('ok' => true, 'skipped' => true));
        return;
    }

    array_unshift($history, array(
        'video_id' => $videoId,
        'title' => $title,
        'thumbnail' => $thumbnail,
        'channel_name' => $channelName,
        'published_time' => $publishedTime,
        'listened_at' => now_datetime(),
    ));

    // Mantener solo los ultimos 100 items
    if (count($history) > 100) {
        $history = array_slice($history, 0, 100);
    }

    storage_write('youtube_history.json', $history);

    _youtube_json_response(array('ok' => true));
}

function action_youtube_save_playlist() {
    $id = trim((string)request_post('id'));
    $name = trim((string)request_post('name'));

    if ($name === '') {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            _youtube_json_response(array('ok' => false, 'error' => 'Nombre requerido'));
        } else {
            set_flash('error', 'El nombre de la lista es obligatorio.');
            redirect_to('index.php?page=josue&tab=reproductor');
        }
        return;
    }

    $playlists = storage_read('youtube_playlists.json');
    if (!is_array($playlists)) $playlists = array();

    $now = now_datetime();

    if ($id !== '') {
        // Editar existente
        foreach ($playlists as &$pl) {
            if ($pl['id'] === $id) {
                $pl['name'] = $name;
                $pl['updated_at'] = $now;
                break;
            }
        }
        unset($pl);
    } else {
        // Crear nueva
        $playlists[] = array(
            'id' => uniqid('pl_'),
            'name' => $name,
            'videos' => array(),
            'created_at' => $now,
            'updated_at' => $now,
        );
    }

    storage_write('youtube_playlists.json', $playlists);

    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        _youtube_json_response(array('ok' => true, 'playlists' => $playlists));
    } else {
        set_flash('ok', 'Lista guardada.');
        redirect_to('index.php?page=josue&tab=reproductor');
    }
}

function action_youtube_delete_playlist() {
    $id = trim((string)request_post('id'));
    if ($id === '') {
        _youtube_json_response(array('ok' => false, 'error' => 'ID requerido'));
        return;
    }

    $playlists = storage_read('youtube_playlists.json');
    if (!is_array($playlists)) $playlists = array();

    $playlists = array_values(array_filter($playlists, function ($pl) use ($id) {
        return $pl['id'] !== $id;
    }));

    storage_write('youtube_playlists.json', $playlists);
    _youtube_json_response(array('ok' => true, 'playlists' => $playlists));
}

function action_youtube_add_to_playlist() {
    $playlistId = trim((string)request_post('playlist_id'));
    $videoId = trim((string)request_post('video_id'));
    $title = trim((string)request_post('title'));
    $thumbnail = trim((string)request_post('thumbnail'));
    $channelName = trim((string)request_post('channel_name'));

    if ($playlistId === '' || $videoId === '') {
        _youtube_json_response(array('ok' => false, 'error' => 'playlist_id y video_id requeridos'));
        return;
    }

    $playlists = storage_read('youtube_playlists.json');
    if (!is_array($playlists)) $playlists = array();

    $found = false;
    foreach ($playlists as &$pl) {
        if ($pl['id'] === $playlistId) {
            // Evitar duplicados
            $alreadyExists = false;
            foreach ($pl['videos'] as $v) {
                if ($v['video_id'] === $videoId) {
                    $alreadyExists = true;
                    break;
                }
            }
            if (!$alreadyExists) {
                $pl['videos'][] = array(
                    'video_id' => $videoId,
                    'title' => $title,
                    'thumbnail' => $thumbnail,
                    'channel_name' => $channelName,
                    'added_at' => now_datetime(),
                );
                $pl['updated_at'] = now_datetime();
            }
            $found = true;
            break;
        }
    }
    unset($pl);

    if ($found) {
        storage_write('youtube_playlists.json', $playlists);
    }

    _youtube_json_response(array('ok' => $found, 'playlists' => $playlists));
}

function action_youtube_remove_from_playlist() {
    $playlistId = trim((string)request_post('playlist_id'));
    $videoId = trim((string)request_post('video_id'));

    if ($playlistId === '' || $videoId === '') {
        _youtube_json_response(array('ok' => false, 'error' => 'playlist_id y video_id requeridos'));
        return;
    }

    $playlists = storage_read('youtube_playlists.json');
    if (!is_array($playlists)) $playlists = array();

    foreach ($playlists as &$pl) {
        if ($pl['id'] === $playlistId) {
            $pl['videos'] = array_values(array_filter($pl['videos'], function ($v) use ($videoId) {
                return $v['video_id'] !== $videoId;
            }));
            $pl['updated_at'] = now_datetime();
            break;
        }
    }
    unset($pl);

    storage_write('youtube_playlists.json', $playlists);
    _youtube_json_response(array('ok' => true, 'playlists' => $playlists));
}

function action_youtube_seed_channels() {
    $channels = storage_read('youtube_channels.json');
    if (!is_array($channels)) $channels = array();

    // Solo seedear si esta vacio
    if (empty($channels)) {
        $channels = youtube_default_channels();
        storage_write('youtube_channels.json', $channels);
    }

    // Tambien sugerir canales AI
    $history = storage_read('youtube_history.json');
    if (!is_array($history)) $history = array();
    $aiChannels = youtube_ai_suggest_channels($history, 3);
    $aiChannelList = array();

    foreach ($aiChannels as $ac) {
        // Evitar duplicados por nombre
        $dup = false;
        foreach ($channels as $ch) {
            if (mb_strtolower($ch['name']) === mb_strtolower($ac['name'])) {
                $dup = true;
                break;
            }
        }
        if (!$dup) {
            $aiChannelList[] = array(
                'id' => 'ai_' . uniqid(),
                'name' => $ac['name'],
                'query' => $ac['query'],
                'icon' => '🤖',
                'type' => 'ai_suggested',
                'added_at' => now_datetime(),
            );
        }
    }

    _youtube_json_response(array(
        'ok' => true,
        'channels' => $channels,
        'ai_suggested' => $aiChannelList,
    ));
}

function action_youtube_create_topic_channel() {
    $concept = trim((string)request_post('concept'));
    if ($concept === '') {
        _youtube_json_response(array('ok' => false, 'error' => 'Concepto requerido'));
        return;
    }

    // La IA genera la query optimizada
    $query = youtube_ai_generate_channel_query($concept);

    $channels = storage_read('youtube_channels.json');
    if (!is_array($channels)) $channels = array();

    $id = 'ct_' . uniqid();
    $newChannel = array(
        'id' => $id,
        'name' => $concept,
        'query' => $query,
        'icon' => '📺',
        'type' => 'custom',
        'added_at' => now_datetime(),
    );

    $channels[] = $newChannel;
    storage_write('youtube_channels.json', $channels);

    // Buscar videos con la query generada
    $videos = youtube_search($query);

    _youtube_json_response(array(
        'ok' => true,
        'channel' => $newChannel,
        'channels' => $channels,
        'query_used' => $query,
        'videos' => $videos,
    ));
}

function action_youtube_delete_topic_channel() {
    $id = trim((string)request_post('id'));
    if ($id === '') {
        _youtube_json_response(array('ok' => false, 'error' => 'ID requerido'));
        return;
    }

    $channels = storage_read('youtube_channels.json');
    if (!is_array($channels)) $channels = array();

    $channels = array_values(array_filter($channels, function ($ch) use ($id) {
        return ($ch['id'] ?? '') !== $id;
    }));

    storage_write('youtube_channels.json', $channels);
    _youtube_json_response(array('ok' => true, 'channels' => $channels));
}

function action_youtube_topic_channel_videos() {
    $id = trim((string)request_post('id'));
    if ($id === '') {
        _youtube_json_response(array('ok' => false, 'error' => 'ID requerido', 'videos' => array()));
        return;
    }

    $channels = storage_read('youtube_channels.json');
    if (!is_array($channels)) $channels = array();

    $query = '';
    $name = '';
    foreach ($channels as $ch) {
        if (($ch['id'] ?? '') === $id) {
            $query = trim((string)($ch['query'] ?? ''));
            $name = trim((string)($ch['name'] ?? ''));
            break;
        }
    }

    if ($query === '') {
        _youtube_json_response(array('ok' => false, 'error' => 'Canal no encontrado', 'videos' => array()));
        return;
    }

    $videos = youtube_search($query, 48);

    _youtube_json_response(array(
        'ok' => true,
        'channel_name' => $name,
        'query' => $query,
        'videos' => $videos,
    ));
}

function action_youtube_reorder_playlist() {
    $playlistId = trim((string)request_post('playlist_id'));
    $videoIds = request_post('video_ids');
    if (!is_array($videoIds)) $videoIds = array();

    if ($playlistId === '') {
        _youtube_json_response(array('ok' => false, 'error' => 'playlist_id requerido'));
        return;
    }

    $playlists = storage_read('youtube_playlists.json');
    if (!is_array($playlists)) $playlists = array();

    foreach ($playlists as &$pl) {
        if ($pl['id'] === $playlistId) {
            $currentVideos = $pl['videos'] ?? array();
            // Build a lookup map for fast reordering
            $lookup = array();
            foreach ($currentVideos as $v) {
                $lookup[$v['video_id']] = $v;
            }

            // Rebuild videos in the order specified by video_ids
            $reordered = array();
            foreach ($videoIds as $vid) {
                if (isset($lookup[$vid])) {
                    $reordered[] = $lookup[$vid];
                }
            }
            // Append any remaining videos not in the ordered list
            foreach ($currentVideos as $v) {
                if (!in_array($v['video_id'], $videoIds, true)) {
                    $reordered[] = $v;
                }
            }

            $pl['videos'] = $reordered;
            $pl['updated_at'] = now_datetime();
            break;
        }
    }
    unset($pl);

    storage_write('youtube_playlists.json', $playlists);
    _youtube_json_response(array('ok' => true, 'playlists' => $playlists));
}

/**
 * Proxy de audio de YouTube: extrae la URL del stream del video
 * y la devuelve al frontend para usar con Web Audio API + GainNode.
 */
function action_youtube_audio_stream() {
    $videoId = trim((string)request_post('video_id'));
    if ($videoId === '') {
        _youtube_json_response(array('ok' => false, 'error' => 'video_id requerido'));
        return;
    }

    $stream = youtube_get_audio_stream($videoId);

    if (!$stream || empty($stream['url'])) {
        // Registrar el fallo para notificar al admin
        _youtube_record_audio_error('No se pudo extraer el stream de audio', $videoId);
        _youtube_json_response(array('ok' => false, 'error' => 'no_audio_stream', 'msg' => 'No se pudo obtener el stream de audio para este video.'));
        return;
    }

    // Si funciono, resetear el contador de errores (el proxy esta sano)
    _youtube_reset_audio_errors();

    // Guardar la URL real en sesion para el proxy
    $_SESSION['yt_audio_cache'][$videoId] = array(
        'url' => $stream['url'],
        'mime_type' => $stream['mime_type'],
        'content_length' => $stream['content_length'] ?? null,
        'expires' => time() + 3600, // 1 hora
    );

    // Devolver URL de nuestro proxy (mismo origen → sin CORS → AudioContext funciona)
    $proxyUrl = 'index.php?action=youtube_audio_proxy&video_id=' . urlencode($videoId);

    _youtube_json_response(array(
        'ok' => true,
        'url' => $proxyUrl,
        'mime_type' => $stream['mime_type'],
        'bitrate' => $stream['bitrate'],
    ));
}

/**
 * Proxy de streaming de audio: lee el audio de YouTube y lo sirve
 * desde nuestro servidor para evitar problemas de CORS con AudioContext.
 */
function action_youtube_audio_proxy() {
    $videoId = trim((string)(request_get('video_id', '')));
    if ($videoId === '') {
        header('HTTP/1.1 400 Bad Request');
        echo 'video_id requerido';
        exit;
    }

    // Extracción fresca en cada request (sin cache de sesión): las URLs de
    // googlevideo llevan firma (sig/spc) que caduca y puede provocar 403.
    $stream = youtube_get_audio_stream($videoId);
    if (!$stream || empty($stream['url'])) {
        _youtube_record_audio_error('No se pudo extraer el stream de audio', $videoId);
        header('HTTP/1.1 502 Bad Gateway');
        echo 'No se pudo obtener el stream de audio';
        exit;
    }

    $audioUrl  = $stream['url'];
    $mimeType  = $stream['mime_type'] ?: 'audio/mp4';
    $totalSize = $stream['content_length'] ?? null;
    $formatId  = $stream['format_id'] ?? 'bestaudio/best';
    $duration  = (float)($stream['duration'] ?? 0);

    // Range request support (seek byte-exacto)
    $rangeStart = 0;
    $rangeEnd = null;
    $isRangeRequest = false;
    $rangeHeader = $_SERVER['HTTP_RANGE'] ?? null;
    if ($rangeHeader && $totalSize) {
        if (preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $m)) {
            $rangeStart = (int)$m[1];
            $rangeEnd = $m[2] !== '' ? (int)$m[2] : ($totalSize - 1);
            $isRangeRequest = true;
        }
    }

    // Cabeceras idénticas a las que envía yt-dlp al descargar de googlevideo
    // (evita el 403 de bot-detection por UA truncado / falta de Sec-Fetch-Mode).
    $ytHeaders = array(
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-us,en;q=0.5',
        'Sec-Fetch-Mode: navigate',
        'Accept-Encoding: identity',
        'Connection: close',
    );

    // Probe ligero: valida que googlevideo no rechace la petición (403/429)
    // ANTES de comprometer las cabeceras HTTP hacia el cliente.
    $probeRange = $isRangeRequest ? "{$rangeStart}-{$rangeEnd}" : '0-0';
    $probeCode = _youtube_probe_stream($audioUrl, $ytHeaders, $probeRange);

    if ($probeCode >= 400) {
        // FALLBACK: streaming completo vía yt-dlp (robusto ante bot-detection).
        _youtube_stream_fallback_ytdlp($videoId, $mimeType, $formatId);
        exit;
    }

    // ── Streaming principal vía curl (mismas cabeceras que yt-dlp) ──
    $ch = curl_init($audioUrl);
    $curlOpts = array(
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => $ytHeaders,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // coherencia con --force-ipv4 de yt-dlp
        // Stream directo: curl escribe la salida que PHP reenvia al cliente
        CURLOPT_WRITEFUNCTION => function ($ch, $data) {
            echo $data;
            if (ob_get_level() > 0) ob_flush();
            flush();
            return strlen($data);
        },
    );
    if ($isRangeRequest) {
        $curlOpts[CURLOPT_RANGE] = "{$rangeStart}-{$rangeEnd}";
    }
    curl_setopt_array($ch, $curlOpts);

    // Desactivar buffering de PHP para streaming fluido
    if (ob_get_level() > 0) ob_end_clean();
    // Headers para audio
    if ($isRangeRequest && $totalSize) {
        header('HTTP/1.1 206 Partial Content');
        header("Content-Range: bytes {$rangeStart}-{$rangeEnd}/{$totalSize}");
        header('Content-Length: ' . ($rangeEnd - $rangeStart + 1));
    } else {
        header('HTTP/1.1 200 OK');
        if ($totalSize) header('Content-Length: ' . $totalSize);
    }
    header('Content-Type: ' . $mimeType);
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Accept-Ranges: bytes');
    // Desconectar la sesion para no bloquear otras requests
    session_write_close();

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        // Si falla el streaming, no podemos cambiar headers (ya enviados)
        // pero al menos registramos el error
        _youtube_record_audio_error("Streaming fallo con HTTP {$httpCode}", $videoId);
    }
    exit;
}

/**
 * Sondeo ligero de la URL de googlevideo: hace un GET de un solo byte
 * (Range 0-0 o el rango solicitado) y devuelve el código HTTP.
 * Permite detectar 403/429 antes de enviar cabeceras al cliente.
 */
function _youtube_probe_stream($url, $headers, $range) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RANGE => $range,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ));
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

/**
 * Fallback de streaming: descarga el audio vía yt-dlp (-o -) y lo sirve al
 * cliente directamente. Se usa cuando el re-fetch por curl es rechazado
 * (403/429). No soporta byte-range: sirve el stream completo desde 0.
 */
function _youtube_stream_fallback_ytdlp($videoId, $mimeType, $formatId) {
    $cmd = sprintf(
        'yt-dlp --force-ipv4 -f %s -o - --no-part --no-playlist --no-warnings --no-progress --socket-timeout 15 %s',
        escapeshellarg($formatId),
        escapeshellarg('https://www.youtube.com/watch?v=' . $videoId)
    );

    $proc = proc_open($cmd, array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('file', '/dev/null', 'w'),
    ), $pipes);

    if (!is_resource($proc)) {
        _youtube_record_audio_error('Fallback: no se pudo lanzar yt-dlp', $videoId);
        header('HTTP/1.1 502 Bad Gateway');
        echo 'No se pudo obtener el stream de audio';
        exit;
    }

    fclose($pipes[0]);

    if (ob_get_level() > 0) ob_end_clean();
    header('HTTP/1.1 200 OK');
    header('Content-Type: ' . $mimeType);
    header('Cache-Control: no-store, no-cache, must-revalidate');
    // Sin Content-Length (chunked): el navegador reproduce el stream igual.
    session_write_close();
    set_time_limit(0);

    while (!feof($pipes[1])) {
        $chunk = fread($pipes[1], 65536);
        if ($chunk === false) break;
        echo $chunk;
        flush();
    }
    fclose($pipes[1]);
    $status = proc_close($proc);

    if ($status !== 0) {
        _youtube_record_audio_error('Fallback yt-dlp fallo con exit ' . $status, $videoId);
    }
    exit;
}

/**
 * Health check del proxy de audio. El frontend consulta esto al cargar
 * para saber si puede activar el boost o no.
 */
function action_youtube_audio_health() {
    // Primero mirar si ya tenemos un estado cached reciente (ultimos 30 min)
    $errors = storage_read('youtube_audio_errors.json');
    if (is_array($errors) && isset($errors['status'])) {
        $lastCheck = strtotime($errors['last_checked'] ?? '2000-01-01');
        if (time() - $lastCheck < 1800) {
            // Cache fresco, devolver estado guardado
            _youtube_json_response(array(
                'ok' => true,
                'proxy_working' => ($errors['status'] === 'ok'),
                'cached' => true,
            ));
            return;
        }
    }

    // Hacer health check real
    $working = youtube_audio_proxy_health_check();

    if ($working) {
        _youtube_reset_audio_errors();
    } else {
        _youtube_record_audio_error('Health check fallido: no se pudo obtener stream de ningun video de prueba', 'health_check');
    }

    _youtube_json_response(array(
        'ok' => true,
        'proxy_working' => $working,
        'cached' => false,
    ));
}

/**
 * Registra un error del proxy de audio para notificar al admin.
 */
function _youtube_record_audio_error($errorMsg, $videoId = '') {
    $errors = storage_read('youtube_audio_errors.json');
    if (!is_array($errors)) $errors = array();

    $now = now_datetime();
    $count = (int)($errors['error_count'] ?? 0) + 1;

    $errors = array(
        'status' => 'broken',
        'first_failure' => $errors['first_failure'] ?? $now,
        'last_failure' => $now,
        'last_checked' => $now,
        'error_count' => $count,
        'last_error' => $errorMsg,
        'last_video_id' => $videoId,
    );

    storage_write('youtube_audio_errors.json', $errors);

    // Tambien loguear al error_log del servidor
    error_log("[YoutubeAudioProxy] ERROR #{$count}: {$errorMsg}" . ($videoId ? " (video: {$videoId})" : ''));
}

/**
 * Voice search fallback: transcribe audio via OpenAI Whisper API.
 * Recibe audio/webm via POST y devuelve la transcripcion como JSON.
 */
function action_youtube_voice_search() {
    if (empty($_FILES['audio']) || $_FILES['audio']['error'] !== UPLOAD_ERR_OK) {
        _youtube_json_response(array('ok' => false, 'error' => 'No se recibio el audio', 'transcript' => ''));
        return;
    }

    $tmpPath = $_FILES['audio']['tmp_name'];
    $mimeType = $_FILES['audio']['type'] ?: 'audio/webm';

    $apiKey = trim((string)getenv('OPENAI_API_KEY'));
    if ($apiKey === '') {
        // Fallback: try Publicista config
        if (function_exists('publicista_ai_config')) {
            $pubCfg = publicista_ai_config();
            $apiKey = $pubCfg['api_key'] ?? '';
        }
    }
    if ($apiKey === '') {
        _youtube_json_response(array('ok' => false, 'error' => 'OPENAI_API_KEY no configurada', 'transcript' => ''));
        return;
    }

    if (!function_exists('curl_file_create')) {
        _youtube_json_response(array('ok' => false, 'error' => 'curl_file_create no disponible (PHP < 5.5)', 'transcript' => ''));
        return;
    }

    $postFields = array(
        'file' => curl_file_create($tmpPath, $mimeType, 'audio.webm'),
        'model' => 'whisper-1',
        'language' => 'es',
        'response_format' => 'json',
    );

    $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $apiKey,
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($curlError !== '') {
        _youtube_json_response(array('ok' => false, 'error' => 'Error de conexion: ' . $curlError, 'transcript' => ''));
        return;
    }

    if ($httpCode !== 200) {
        _youtube_json_response(array('ok' => false, 'error' => 'Whisper API error HTTP ' . $httpCode, 'transcript' => ''));
        return;
    }

    $decoded = json_decode((string)$body, true);
    $transcript = trim((string)($decoded['text'] ?? ''));

    _youtube_json_response(array('ok' => true, 'transcript' => $transcript));
}

/**
 * Resetea el estado de errores del proxy cuando vuelve a funcionar.
 */
function _youtube_reset_audio_errors() {
    storage_write('youtube_audio_errors.json', array(
        'status' => 'ok',
        'last_checked' => now_datetime(),
        'last_success' => now_datetime(),
    ));
}

function _youtube_json_response($data) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// DIARY ACTIONS
// ═══════════════════════════════════════════════════════════════════

function action_get_diario_entries() {
    $offset = (int)request_post('offset', 0);
    $limit = min(20, max(1, (int)request_post('limit', 10)));
    $search = trim((string)request_post('search', ''));

    $data = voice_diary_read();
    $entries = $data['entries'] ?? array();

    // Ordenar por fecha descendente
    usort($entries, function ($a, $b) {
        return ($b['fecha'] ?? '') <=> ($a['fecha'] ?? '');
    });

    // Filtrar por búsqueda si hay
    if ($search !== '') {
        $searchLower = mb_strtolower($search);
        $entries = array_filter($entries, function ($e) use ($searchLower) {
            $haystack = mb_strtolower(($e['clean_text'] ?? '') . ' ' . implode(' ', $e['tags'] ?? array()));
            return strpos($haystack, $searchLower) !== false;
        });
        $entries = array_values($entries);
    }

    $total = count($entries);
    $page = array_slice($entries, $offset, $limit);

    // Quitar embeddings de la respuesta (son vectores enormes, no se envían al front)
    foreach ($page as &$entry) {
        unset($entry['embedding']);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'ok' => true,
        'entries' => $page,
        'total' => $total,
        'has_more' => ($offset + $limit) < $total,
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function action_get_diario_entry() {
    $fecha = trim((string)request_post('fecha', ''));
    if ($fecha === '') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'error' => 'fecha requerida'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = voice_diary_read();
    $entry = null;
    foreach ($data['entries'] ?? array() as $e) {
        if (($e['fecha'] ?? '') === $fecha) {
            $entry = $e;
            break;
        }
    }

    if ($entry) {
        unset($entry['embedding']);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'ok' => true,
        'entry' => $entry,
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function action_search_diario() {
    $query = trim((string)request_post('query', ''));
    if ($query === '') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('ok' => false, 'error' => 'query requerida'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Búsqueda semántica si hay embeddings, fallback a full-text
    $results = voice_diary_search_similar($query, 5);

    $entries = array();
    foreach ($results as $r) {
        $e = $r['entry'];
        unset($e['embedding']);
        $e['_score'] = $r['score'];
        $entries[] = $e;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array(
        'ok' => true,
        'entries' => $entries,
        'query' => $query,
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ─────────────────────────────────────────────────────────
//  Acciones del nuevo inbox comercial
// ─────────────────────────────────────────────────────────

function action_toggle_inbox_replies() {
    $settings = inbox_get_settings();
    $settings['replies_enabled'] = !empty($settings['replies_enabled']) ? false : true;
    inbox_save_settings($settings);
    $label = $settings['replies_enabled'] ? 'activadas' : 'desactivadas';
    set_flash('ok', 'Respuestas automáticas ' . $label . '.');
    $returnTab = trim((string)request_post('return_tab', 'conversaciones'));
    redirect_to(inbox_page_url($returnTab));
}

function action_toggle_inbox_opener() {
    $settings = inbox_get_settings();
    $settings['opener_enabled'] = !empty($settings['opener_enabled']) ? false : true;
    inbox_save_settings($settings);
    $label = $settings['opener_enabled'] ? 'activado' : 'desactivado';
    set_flash('ok', 'Inicio de conversaciones ' . $label . '.');
    $returnTab = trim((string)request_post('return_tab', 'conversaciones'));
    redirect_to(inbox_page_url($returnTab));
}

function action_inbox_toggle_thread_pause() {
    $threadId = trim((string)request_post('thread_id'));
    $returnStageFilter = trim((string)request_post('return_stage_filter', ''));
    $returnViewThread = trim((string)request_post('return_view_thread', ''));

    $threads = comercial_get_threads();
    foreach ($threads as $thread) {
        if ((string)($thread['id'] ?? '') !== $threadId) continue;
        $thread = comercial_normalize_thread($thread);
        // Unificamos inbox_paused + human_taken: si cualquiera está activo → reanudar ambos
        $isEffectivelyPaused = !empty($thread['inbox_paused']) || !empty($thread['human_taken']);
        if ($isEffectivelyPaused) {
            $thread['inbox_paused'] = 0;
            $thread['human_taken']  = 0;
            $label = 'reactivada';
        } else {
            $thread['inbox_paused'] = 1;
            $label = 'pausada';
        }
        comercial_upsert_thread($thread);
        set_flash('ok', 'Conversación ' . $label . '.');
        break;
    }

    redirect_to(inbox_page_url('conversaciones', array_filter(array(
        'stage_filter' => $returnStageFilter,
        'view_thread' => $returnViewThread !== '' ? $returnViewThread : $threadId,
    ))));
}
