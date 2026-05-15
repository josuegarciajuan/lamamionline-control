<?php

function render_global_ui() {
    echo '<div id="floatingToast" class="floating-toast"></div>';
    echo '<div id="moneyRain" class="money-rain"></div>';
    echo '<div id="appBackdrop" class="app-backdrop" hidden></div>';
    echo '<div id="voiceCommandBackdrop" class="voice-command-backdrop" hidden></div>';
    echo '<div id="voiceProcessingOverlay" class="voice-processing-overlay" hidden aria-hidden="true">';
    echo '<div class="voice-processing-card">';
    echo '<div class="voice-processing-orb"></div>';
    echo '<div class="voice-processing-title">Maestro procesando solicitud</div>';
    echo '<div id="voiceProcessingText" class="voice-processing-text">Interpretando tu orden dentro del CRM…</div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="app-shell-tools">';
    echo '<button type="button" id="mobileMenuToggle" class="app-shell-btn app-shell-btn-mobile" aria-expanded="false" aria-controls="appSidebar">☰ Menú</button>';
    echo '<button type="button" id="mobileAvisosToggle" class="app-shell-btn app-shell-btn-mobile" aria-expanded="false" aria-controls="avisosPanel">⚠ Avisos</button>';
    echo '<button type="button" id="voiceCommandToggleMobile" class="app-shell-btn app-shell-btn-mobile app-shell-btn-mic" data-voice-command-toggle aria-expanded="false" aria-controls="voiceCommandPanel" aria-label="Abrir voz CRM" title="Abrir voz CRM">🎙</button>';
    echo '</div>';

    echo '<section id="voiceCommandPanel" class="voice-command-panel" hidden aria-hidden="true">';
    echo '<div class="voice-command-head">';
    echo '<div>';
    echo '<h2>Órdenes por voz</h2>';
    echo '<p>Pulsa el micro y habla. Cuando termines, la orden se envía sola.</p>';
    echo '</div>';
    echo '<button type="button" id="voiceCommandClose" class="voice-command-close" aria-label="Cerrar panel de voz">✕</button>';
    echo '</div>';

    echo '<div class="voice-command-body">';
    echo '<div id="voiceCommandSupport" class="voice-command-support">Comprobando reconocimiento de voz…</div>';
    echo '<div class="voice-command-actions">';
    echo '<button type="button" id="voiceStartButton" class="btn-primary voice-command-main-btn">🎙 Escuchar ahora</button>';
    echo '<button type="button" id="voiceStopButton" class="voice-command-secondary-btn" disabled>■ Parar</button>';
    echo '<button type="button" id="voiceClearButton" class="voice-command-secondary-btn">Limpiar</button>';
    echo '</div>';

    echo '<div class="field full">';
    echo '<label for="voiceCommandInput">Texto de la orden</label>';
    echo '<textarea id="voiceCommandInput" class="voice-command-input" placeholder="Ejemplo: muéstrame estadísticas de esta clienta"></textarea>';
    echo '<div class="field-help">Se enviará automáticamente al terminar de hablar. Si quieres, puedes corregir el texto y reenviarlo manualmente.</div>';
    echo '</div>';

    echo '<div class="voice-command-meta">';
    echo '<span id="voiceCommandStatus" class="voice-command-status stage-idle">Listo para escuchar.</span>';
    echo '<span id="voiceCommandStage" class="voice-command-stage">Sin enviar</span>';
    echo '</div>';

    echo '<div class="voice-command-submit-row">';
    echo '<button type="button" id="voiceSendButton" class="btn-primary">Enviar manualmente</button>';
    echo '</div>';

    echo '<div id="voiceCommandResponse" class="voice-command-response" aria-live="polite"></div>';
    echo '</div>';
    echo '</section>';
}

function render_flash() {
    $flash = get_flash();
    if (!$flash) return;
    $fx = isset($flash['fx']) ? $flash['fx'] : '';
    echo '<div class="flash flash-' . e($flash['type']) . '" data-fx="' . e($fx) . '">' . e($flash['message']) . '</div>';
}

function render_aviso_whatsapp_log_panel($aviso, $baseUrl = 'index.php?page=avisos&avtab=active') {
    $aviso = is_array($aviso) ? $aviso : array();
    $log = is_array($aviso['whatsapp_last_log'] ?? null) ? $aviso['whatsapp_last_log'] : array();
    $json = json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) $json = 'No se pudo codificar el log.';

    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>Log envío WhatsApp</h2><span class="summary-badge">' . e($aviso['id'] ?? 'sin_id') . '</span></div>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">';
    echo '<a class="btn-secondary-mini" href="' . e($baseUrl) . '">Volver a avisos</a>';
    echo '</div>';

    echo '<div class="cards three" style="margin-top:12px;">';
    echo '<div class="info-strip"><strong>Aviso</strong><br>' . e($aviso['title'] ?? 'Aviso') . '</div>';
    echo '<div class="info-strip"><strong>Resultado</strong><br>' . e($aviso['whatsapp_last_result'] ?? 'sin dato') . '</div>';
    echo '<div class="info-strip"><strong>Intento</strong><br>' . e(format_created_at($aviso['whatsapp_last_attempt_at'] ?? '')) . '</div>';
    echo '</div>';

    if (trim((string)($aviso['whatsapp_last_error'] ?? '')) !== '') {
        echo '<div class="publicista-ads-warning" style="margin-top:12px;">' . e((string)$aviso['whatsapp_last_error']) . '</div>';
    }

    echo '<pre style="white-space:pre-wrap;word-break:break-word;max-width:100%;overflow:auto;margin-top:12px;">' . e($json) . '</pre>';
    echo '</section>';
}


function render_publicista_strategy_option_detail($option, $isDefault = false) {
    $option = is_array($option) ? $option : array();
    if (empty($option)) return;

    $label = (string)($option['label'] ?? 'Opción');
    $modeLabel = trim((string)($option['mode_label'] ?? $label));
    $strategies = is_array($option['strategies'] ?? null) ? $option['strategies'] : array();
    $warnings = is_array($option['warnings'] ?? null) ? $option['warnings'] : array();
    $headStyle = $isDefault ? ' style="border:1px solid rgba(99,102,241,.35);box-shadow:0 0 0 1px rgba(99,102,241,.14) inset;"' : '';

    echo '<section class="panel panel-space"' . $headStyle . '>';
    echo '<div class="branch-panel-head"><h2>' . e($label) . '</h2><span class="summary-badge">' . ($isDefault ? 'Opción por defecto' : 'Alternativa') . '</span></div>';
    echo '<div class="cards four" style="margin-top:12px;">';
    echo '<div class="info-strip"><strong>Total</strong><br>' . e(publicista_ads_euros((float)($option['grand_total'] ?? 0))) . '</div>';
    echo '<div class="info-strip"><strong>Media por chica</strong><br>' . e(publicista_ads_euros((float)($option['avg_per_product'] ?? 0))) . '</div>';
    echo '<div class="info-strip"><strong>Postura</strong><br>' . e(ucfirst((string)($option['market_posture'] ?? 'equilibrada'))) . '</div>';
    echo '<div class="info-strip"><strong>Avisos</strong><br>' . e((string)count($warnings)) . '</div>';
    echo '</div>';
    if (!empty($option['explanation'])) {
        echo '<div class="info-strip" style="margin-top:12px;"><strong>Lectura de esta versión:</strong> ' . e((string)$option['explanation']) . '</div>';
    }
    if (!empty($option['decision_help'])) {
        echo '<div class="info-strip" style="margin-top:12px;"><strong>Cuándo elegirla:</strong> ' . e((string)$option['decision_help']) . '</div>';
    }
    if (!empty($option['comparison_note'])) {
        echo '<div class="info-strip" style="margin-top:12px;"><strong>Comparativa:</strong> ' . e((string)$option['comparison_note']) . '</div>';
    }
    if (!empty($option['free_bot_note'])) {
        echo '<div class="info-strip" style="margin-top:12px;"><strong>Bot gratis:</strong> ' . e((string)$option['free_bot_note']) . '</div>';
    }
    if (!empty($option['synergy_note'])) {
        echo '<div class="info-strip" style="margin-top:12px;"><strong>Sinergia multichica:</strong> ' . e((string)$option['synergy_note']) . '</div>';
    }
    if (!empty($option['window']) && is_array($option['window'])) {
        echo '<div class="info-strip" style="margin-top:12px;"><strong>Ventana aplicada:</strong> ' . e((string)$option['window']['start']) . ' → ' . e((string)$option['window']['end']) . '</div>';
    }
    echo '</section>';

    foreach ($strategies as $strategy) {
        $girl = (int)($strategy['girl'] ?? 0);

        echo '<section class="panel panel-space publicista-ads-girl">';
        echo '<div class="section-head"><div><h2>' . e($label) . ' · Chica ' . e((string)$girl) . '</h2><p>' . e((string)count($strategy['profiles'] ?? array())) . ' perfiles · ' . e(publicista_ads_euros((float)($strategy['cost'] ?? 0))) . ' por período · ' . e((string)($strategy['mode_label'] ?? $modeLabel)) . '</p></div><div class="section-head-actions">';
        echo '<span class="publicista-ads-level-pill" style="background:' . e(publicista_ads_level_bg($strategy['level'] ?? 'media')) . ';color:' . e(publicista_ads_level_fg($strategy['level'] ?? 'media')) . ';">' . e(publicista_ads_level_icon($strategy['level'] ?? 'media')) . ' ' . e(publicista_ads_level_label($strategy['level'] ?? 'media')) . '</span>';
        echo '</div></div>';

        if (!empty($strategy['window']) && is_array($strategy['window'])) {
            echo '<div class="info-strip" style="margin-top:14px;"><strong>Franja de autosubidas</strong><br>' . e((string)$strategy['window']['start']) . ' → ' . e((string)$strategy['window']['end']) . '</div>';
        }
        echo '<div class="cards two" style="margin-top:14px;">';
        echo '<section class="panel">';
        echo '<h3>Por qué esta estrategia</h3>';
        echo '<ul class="publicista-ads-reasons">';
        foreach ((array)($strategy['reasons'] ?? array()) as $reason) {
            echo '<li>' . e($reason) . '</li>';
        }
        echo '</ul>';
        echo '</section>';

        echo '<section class="panel">';
        echo '<h3>Perfiles a crear / comprar</h3>';
        echo '<div class="table-wrap"><table class="publicista-ads-profile-table"><thead><tr><th>#</th><th>Perfil</th><th>Productos</th><th>Nota</th><th>Coste</th></tr></thead><tbody>';
        foreach ((array)($strategy['profiles'] ?? array()) as $profile) {
            echo '<tr>';
            echo '<td><span class="summary-badge">' . e((string)($profile['num'] ?? '')) . '</span></td>';
            echo '<td><strong>' . e($profile['name'] ?? '') . '</strong></td>';
            echo '<td>' . publicista_ads_badge_line_html($profile['opts'] ?? array()) . '</td>';
            echo '<td>' . e($profile['why'] ?? '') . '</td>';
            echo '<td><strong>' . e(((float)($profile['cost'] ?? 0) === 0.0) ? 'Gratis' : publicista_ads_euros((float)($profile['cost'] ?? 0))) . '</strong></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '</section>';
        echo '</div>';

        if (!empty($strategy['allFirings'])) {
            echo '<section class="panel panel-space">';
            echo '<h3>Horarios exactos de subida</h3>';
            foreach ((array)$strategy['allFirings'] as $firing) {
                $typeLabel = (($firing['type'] ?? '') === 'auto7') ? 'Autorenueva 7€ · 10 sub/día' : 'Refuerzo 4€ · 4 sub/día';
                echo '<div class="publicista-ads-firing-row">';
                echo '<div class="publicista-ads-firing-label"><strong>P' . e((string)($firing['profile'] ?? '')) . '</strong><br>' . e($typeLabel) . '<br><span class="muted">' . e(($firing['start'] ?? '') . ' → ' . ($firing['end'] ?? '')) . '</span></div>';
                echo '<div class="publicista-ads-firing-times">';
                foreach ((array)($firing['times'] ?? array()) as $time) {
                    echo '<span class="publicista-ads-time-pill">' . e($time) . '</span>';
                }
                echo '</div>';
                echo '</div>';
            }
            echo '</section>';

            echo '<section class="panel panel-space">';
            echo '<h3>Línea de tiempo 24h</h3>';
            echo '<div class="publicista-ads-timeline">' . publicista_ads_render_timeline_svg($strategy['allFirings']) . '</div>';
            echo '<div class="muted" style="margin-top:10px;">Cada fila es una autorenueva. La barra marca el rango activo y los puntos el momento exacto de subida.</div>';
            echo '</section>';
        }

        if (!empty($strategy['overlapWarnings'])) {
            echo '<section class="panel panel-space">';
            echo '<div class="publicista-ads-warning"><strong>Coincidencias a revisar</strong><br><span class="muted">El sistema ya ha separado automáticamente las autosubidas, pero aún quedan algunos puntos cercanos dentro de la franja elegida.</span><ul class="publicista-ads-warn-list">';
            foreach ((array)$strategy['overlapWarnings'] as $warning) {
                echo '<li>' . e($warning) . '</li>';
            }
            echo '</ul></div>';
            echo '</section>';
        }

        foreach ((array)($strategy['profiles'] ?? array()) as $profile) {
            if (empty($profile['opts']['free']) || !is_array($profile['opts']['free'])) {
                continue;
            }
            echo '<section class="panel panel-space publicista-ads-free-box">';
            echo '<h3>Perfil gratuito P' . e((string)($profile['num'] ?? '')) . '</h3>';
            echo '<div style="display:flex;gap:8px;flex-wrap:wrap;">';
            foreach ((array)$profile['opts']['free'] as $freeTime) {
                echo '<span class="publicista-ads-time-pill">⏰ ' . e($freeTime) . '</span>';
            }
            echo '</div>';
            echo '<div class="muted" style="margin-top:10px;">Subida manual, una vez cada 12h, sin coste.</div>';
            echo '</section>';
        }
    }
}

function render_avisos_panel() {
    $avisos = avisos_get_active();
    if (empty($avisos)) return;

    $newCount = 0;

    echo '<section id="avisosPanel" class="panel panel-space avisos-panel">';
    echo '<div class="branch-panel-head">';
    echo '<h2><a class="mini-link" href="' . e(avisos_page_url(array('avtab' => 'active'))) . '">Avisos activos</a></h2>';
    echo '<span class="summary-badge">' . e(count($avisos)) . '</span>';
    echo '</div>';

    foreach ($avisos as $aviso) {
        $isNew = empty($aviso['read_at']);
        if ($isNew) $newCount++;

        echo '<div class="aviso-item aviso-sev-' . e($aviso['severity'] ?? 'media') . ' ' . ($isNew ? 'aviso-item-new' : '') . '">';
        echo '<div class="aviso-main">';
        echo '<div class="aviso-title-row">';
        echo '<strong>' . e($aviso['title'] ?? 'Aviso') . '</strong>';
        if ($isNew) {
            echo '<span class="summary-badge badge-new">NUEVO</span>';
        }
        echo '</div>';
        if (($aviso['message'] ?? '') !== '') {
            echo '<div class="muted">' . e($aviso['message']) . '</div>';
        }
        echo '<div class="muted small">Motor: ' . e($aviso['engine'] ?? 'general') . ' · Creado: ' . e(format_created_at($aviso['created_at'] ?? '')) . '</div>';
        echo '</div>';

        echo '<div class="aviso-actions">';
        $avisoMeta = is_array($aviso['meta'] ?? null) ? $aviso['meta'] : array();
        $runId = trim((string)($avisoMeta['run_id'] ?? ''));
        if ($runId !== '') {
            echo '<a class="btn-secondary-mini" href="' . e(publicista_page_url('run_log', array('run_id' => $runId, 'campaign_id' => trim((string)($avisoMeta['campaign_id'] ?? ''))))) . '">Ver log</a>';
        }
        if (aviso_whatsapp_has_failure($aviso)) {
            echo '<a class="btn-secondary-mini" href="' . e(aviso_whatsapp_log_url($aviso['id'] ?? '')) . '">Ver log WA</a>';
        }
        echo '<form method="post" class="inline-form">';
        echo '<input type="hidden" name="action" value="dismiss_aviso">';
        echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
        echo '<input type="hidden" name="id" value="' . e($aviso['id'] ?? '') . '">';
        echo '<input type="hidden" name="redirect" value="' . e($_SERVER['REQUEST_URI'] ?? 'index.php?page=dashboard') . '">';
        echo '<button class="btn-danger-mini">Descartar</button>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }

    if ($newCount > 0) {
        echo '<div class="aviso-actions" style="margin-top:10px;">';
        echo '<form method="post" class="inline-form js-mark-all-read">';
        echo '<input type="hidden" name="action" value="mark_avisos_read">';
        echo '<input type="hidden" name="scope" value="active_unread">';
        echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
        echo '<input type="hidden" name="redirect" value="' . e($_SERVER['REQUEST_URI'] ?? 'index.php?page=dashboard') . '">';
        echo '<button class="btn-secondary-mini">Marcar ' . e((string)$newCount) . ' nuevos como leídos</button>';
        echo '</form>';
        echo '</div>';
    }

    echo '</section>';
}

function render_avisos_section($baseUrl = 'index.php?page=avisos') {
    $tab = request_get('avtab', 'planned');
    if (!in_array($tab, array('planned', 'active', 'history'), true)) {
        $tab = 'planned';
    }
    $waLogId = trim((string)request_get('wa_log', ''));

    $planned = avisos_get_planned();
    $active = avisos_get_active();
    $history = avisos_get_history();

    echo '<section class="panel panel-space">';
    echo '<div class="subtabs">';
    echo '<a class="subtab ' . ($tab === 'planned' ? 'active' : '') . '" href="' . e($baseUrl . '&avtab=planned') . '">Planificados</a>';
    echo '<a class="subtab ' . ($tab === 'active' ? 'active' : '') . '" href="' . e($baseUrl . '&avtab=active') . '">Activos</a>';
    echo '<a class="subtab ' . ($tab === 'history' ? 'active' : '') . '" href="' . e($baseUrl . '&avtab=history') . '">Descartados / histórico</a>';
        echo '</div>';
    echo '</section>';

    if ($waLogId !== '') {
        $selectedAviso = storage_find_by_id('avisos.json', $waLogId);
        if ($selectedAviso && !empty($selectedAviso['whatsapp_last_log']) && is_array($selectedAviso['whatsapp_last_log'])) {
            render_aviso_whatsapp_log_panel($selectedAviso, $baseUrl . '&avtab=' . urlencode($tab));
        } else {
            echo '<section class="panel panel-space"><div class="publicista-ads-warning">No se encontró log de WhatsApp para el aviso solicitado.</div></section>';
        }
    }

    if ($tab === 'planned') {
        echo '<div class="cards two">';

        echo '<section class="panel">';
        echo '<h2>Nuevo aviso manual</h2>';
        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="action" value="create_manual_aviso">';
        echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';

        field_input('title', 'Título', '', true);
        field_input('scheduled_for', 'Fecha y hora activación', today_datetime_local(), true, 'datetime-local');

        echo '<div class="field">';
        echo '<label>Severidad</label>';
        echo '<select name="severity">';
        echo '<option value="baja">Baja</option>';
        echo '<option value="media" selected>Media</option>';
        echo '<option value="alta">Alta</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="full">';
        field_textarea('message', 'Texto del aviso', '', 5);
        echo '</div>';

        echo '<div class="full"><button class="btn-primary">Planificar aviso</button></div>';
        echo '</form>';
        echo '</section>';

        echo '<section class="panel">';
        echo '<div class="branch-panel-head"><h2>Avisos planificados</h2><span class="summary-badge">' . e(count($planned)) . '</span></div>';

        if (empty($planned)) {
            echo '<div class="empty">No hay avisos planificados.</div>';
        } else {
            echo '<div class="table-wrap"><table><thead><tr>';
            echo '<th>Fecha activación</th><th>Título</th><th>Texto</th><th>Nivel</th><th>Acciones</th>';
            echo '</tr></thead><tbody>';

            foreach ($planned as $aviso) {
                echo '<tr>';
                echo '<td>' . e(format_created_at($aviso['scheduled_for'] ?? '')) . '</td>';
                echo '<td>' . e($aviso['title'] ?? 'Aviso') . '</td>';
                echo '<td>' . e($aviso['message'] ?? '') . '</td>';
                echo '<td>' . e(ucfirst($aviso['severity'] ?? 'media')) . '</td>';
                echo '<td>';
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar este aviso planificado?\')">';
                echo '<input type="hidden" name="action" value="delete_planned_aviso">';
                echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
                echo '<input type="hidden" name="id" value="' . e($aviso['id'] ?? '') . '">';
                echo '<button class="btn-danger-mini">Borrar</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody></table></div>';
        }

        echo '</section>';
        echo '</div>';
        return;
    }

    if ($tab === 'active') {
        echo '<section class="panel panel-space">';
        echo '<div class="branch-panel-head"><h2>Avisos activos</h2><span class="summary-badge">' . e(count($active)) . '</span></div>';

        if (empty($active)) {
            echo '<div class="empty">No hay avisos activos.</div>';
        } else {
            foreach ($active as $aviso) {
                $origin = (($aviso['engine'] ?? '') === 'manual') ? 'Manual' : ('Motor: ' . ($aviso['engine'] ?? 'general'));

                echo '<div class="aviso-item aviso-sev-' . e($aviso['severity'] ?? 'media') . '">';
                echo '<div class="aviso-main">';
                echo '<div class="aviso-title-row">';
                echo '<strong>' . e($aviso['title'] ?? 'Aviso') . '</strong>';
                echo '<span class="summary-badge">' . e($origin) . '</span>';
                echo '</div>';

                if (($aviso['message'] ?? '') !== '') {
                    echo '<div class="muted">' . e($aviso['message']) . '</div>';
                }

                echo '<div class="muted small">Creado: ' . e(format_created_at($aviso['created_at'] ?? '')) . '</div>';
                echo '</div>';

                echo '<div class="aviso-actions">';
                if (aviso_whatsapp_has_failure($aviso)) {
                    echo '<a class="btn-secondary-mini" href="' . e(aviso_whatsapp_log_url($aviso['id'] ?? '')) . '">Ver log WA</a>';
                }
                echo '<form method="post" class="inline-form">';
                echo '<input type="hidden" name="action" value="dismiss_aviso">';
                echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
                echo '<input type="hidden" name="id" value="' . e($aviso['id'] ?? '') . '">';
                echo '<input type="hidden" name="redirect" value="' . e($baseUrl . '&avtab=active') . '">';
                echo '<button class="btn-danger-mini">Descartar</button>';
                echo '</form>';
                echo '</div>';
                echo '</div>';
            }
        }

        echo '</section>';
        return;
    }

    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>Histórico de avisos</h2><span class="summary-badge">' . e(count($history)) . '</span></div>';

    if (empty($history)) {
        echo '<div class="empty">No hay avisos en el histórico.</div>';
    } else {
        render_live_filter('#avisosHistoryRows tr[data-filter-text]', 'Buscar en histórico de avisos...');

        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Estado</th><th>Origen</th><th>Título</th><th>Texto</th><th>Fecha</th>';
        echo '</tr></thead><tbody id="avisosHistoryRows">';

        foreach ($history as $aviso) {
            $estado = ($aviso['status'] ?? '') === 'dismissed' ? 'Descartado' : 'Resuelto';
            $origen = (($aviso['engine'] ?? '') === 'manual') ? 'Manual' : ($aviso['engine'] ?? 'general');
            $fechaHist = $aviso['dismissed_at'] ?? '';
            if ($fechaHist === '') $fechaHist = $aviso['resolved_at'] ?? '';
            if ($fechaHist === '') $fechaHist = $aviso['updated_at'] ?? '';

            $searchText = strtolower(trim(
                $estado . ' ' .
                $origen . ' ' .
                ($aviso['title'] ?? '') . ' ' .
                ($aviso['message'] ?? '') . ' ' .
                $fechaHist
            ));

            echo '<tr data-filter-text="' . e($searchText) . '">';
            echo '<td>' . e($estado) . '</td>';
            echo '<td>' . e($origen) . '</td>';
            echo '<td>' . e($aviso['title'] ?? 'Aviso') . '</td>';
            echo '<td>' . e($aviso['message'] ?? '') . '</td>';
            echo '<td>' . e(format_created_at($fechaHist)) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    echo '</section>';
}

function render_avisos_page() {
    page_header('Avisos', 'Avisos manuales planificados, avisos activos y histórico');
    render_avisos_section('index.php?page=avisos');
}

function render_lamami_page() {
    $tab = request_get('tab', 'interesadas');
    $allowed = array('interesadas', 'clientas', 'lamamibot');

    if (!in_array($tab, $allowed, true)) {
        $tab = 'interesadas';
    }

    page_header('LaMami', 'Interesadas, clientas y LamamiBot en una única sección');

    echo '<section class="panel panel-space">';
    echo '<div class="subtabs">';
    echo '<a class="subtab ' . ($tab === 'interesadas' ? 'active' : '') . '" href="' . e(lamami_tab_url('interesadas')) . '">Interesadas</a>';
    echo '<a class="subtab ' . ($tab === 'clientas' ? 'active' : '') . '" href="' . e(lamami_tab_url('clientas')) . '">Clientas</a>';
    echo '<a class="subtab ' . ($tab === 'lamamibot' ? 'active' : '') . '" href="' . e(lamami_tab_url('lamamibot')) . '">LamamiBot</a>';
    echo '</div>';
    echo '</section>';

    if ($tab === 'clientas') {
        render_clientas_page(true);
        return;
    }

    if ($tab === 'lamamibot') {
        render_lamamibot_page(true);
        return;
    }

    render_interesadas_page(true);
}

function render_publicista_page() {
    $tab = request_get('tab', 'crear_perfiles');
    if ($tab === 'calculo_publicidad') $tab = 'estrategias';
    $allowed = array('crear_perfiles', 'estrategias', 'cuentas', 'campanas', 'subir_anuncios', 'run_log', 'estados_wasap');

    if (!in_array($tab, $allowed, true)) {
        $tab = 'crear_perfiles';
    }
    $accountsUnlocked = !empty($_SESSION['publicista_accounts_unlocked']);

    page_header('Publicista', 'Crea productos publicitarios, calcula estrategia, gestiona cuentas, genera campañas y ejecuta la automatización disponible.');

    echo '<section class="panel panel-space">';
    echo '<div class="subtabs">';
    echo '<a class="subtab ' . ($tab === 'cuentas' ? 'active' : '') . '" href="' . e(publicista_page_url('cuentas')) . '">Cuentas</a>';
    echo '<a class="subtab ' . ($tab === 'crear_perfiles' ? 'active' : '') . '" href="' . e(publicista_page_url('crear_perfiles')) . '">Perfiles</a>';
    echo '<a class="subtab ' . ($tab === 'estrategias' ? 'active' : '') . '" href="' . e(publicista_page_url('estrategias')) . '">Estrategias</a>';
    echo '<a class="subtab ' . ($tab === 'campanas' ? 'active' : '') . '" href="' . e(publicista_page_url('campanas')) . '">Campañas</a>';
    echo '<a class="subtab ' . ($tab === 'subir_anuncios' ? 'active' : '') . '" href="' . e(publicista_page_url('subir_anuncios')) . '">Subir anuncios</a>';
    echo '<a class="subtab ' . ($tab === 'estados_wasap' ? 'active' : '') . '" href="' . e(publicista_page_url('estados_wasap')) . '">📱 Estados</a>';
    echo '</div>';
    echo '</section>';

    if ($tab === 'estrategias') {
        render_publicista_calculo_publicidad_page();
        return;
    }

    if ($tab === 'cuentas') {
        if (!$accountsUnlocked) {
            echo '<section class="panel panel-space">';
            echo '<div class="branch-panel-head"><h2>Cuentas protegidas</h2><span class="summary-badge">Privado</span></div>';
            echo '<div class="info-strip" style="margin-top:12px;">Esta sección está protegida por contraseña.</div>';
            echo '<form method="post" class="form-grid" style="margin-top:14px;max-width:420px;">';
            echo '<input type="hidden" name="action" value="unlock_publicista_accounts">';
            echo '<div class="field">';
            echo '<label>Contraseña</label>';
            echo '<input type="password" name="password" placeholder="Contraseña" required>';
            echo '</div>';
            echo '<div><button class="btn-primary">Entrar</button></div>';
            echo '</form>';
            echo '</section>';
            return;
        }

        render_publicista_cuentas_page();
        return;
    }

    if ($tab === 'campanas') {
        render_publicista_campanas_page();
        return;
    }

    if ($tab === 'subir_anuncios') {
        render_publicista_subir_anuncios_page();
        return;
    }

    if ($tab === 'run_log') {
        render_publicista_run_log_page();
        return;
    }

    if ($tab === 'estados_wasap') {
        render_publicista_estados_wasap_page();
        return;
    }

    render_publicista_crear_perfiles_page(true);
}

function render_publicista_run_log_page() {
    $runId = trim((string)request_get('run_id', ''));
    $campaignId = trim((string)request_get('campaign_id', ''));
    $run = $runId !== '' ? publicista_run_get($runId) : null;

    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>Log de subida</h2><span class="summary-badge">' . e($runId !== '' ? $runId : 'sin run') . '</span></div>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">';
    if ($campaignId !== '') {
        echo '<a class="btn-secondary-mini" href="' . e(publicista_page_url('campanas', array('edit' => $campaignId))) . '">Volver a campaña</a>';
    }
    if ($runId !== '') {
        echo '<a class="btn-secondary-mini" href="' . e(publicista_page_url('run_log', array('run_id' => $runId, 'campaign_id' => $campaignId))) . '">Recargar</a>';
    }
    echo '</div>';

    if (!$run) {
        echo '<div class="publicista-ads-warning" style="margin-top:12px;">No se encontró el run solicitado.</div>';
        echo '</section>';
        return;
    }

    $json = json_encode($run, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) $json = 'No se pudo codificar el run a JSON.';

    $humanReport = trim((string)($run['human_report'] ?? ''));
    if ($humanReport !== '') {
        echo '<div class="info-strip" style="margin-top:12px;"><strong>Informe humano</strong><br>' . nl2br(e($humanReport)) . '</div>';
    }
    echo '<p class="muted" style="margin-top:12px;">Aquí está el run completo con items, errores, payloads resumidos, humanTrace y debug_log.</p>';
    echo '<pre style="white-space:pre-wrap;word-break:break-word;max-width:100%;overflow:auto;margin-top:12px;">' . e($json) . '</pre>';
    echo '</section>';
}

function render_publicista_estados_wasap_page() {
    $config = publicista_estados_wasap_get_config();
    $log = publicista_estados_wasap_get_log();
    $allLines = publicista_estados_wasap_get_bot_casa_lines();
    $enabledIds = $config['lineas'];
    $enabledLines = array();
    foreach ($allLines as $l) {
        if (in_array($l['id'], $enabledIds, true)) $enabledLines[] = $l;
    }

    // ── Status cards ───────────────────────────────────────────────
    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>📱 Estados WhatsApp</h2><span class="summary-badge">' . ($config['enabled'] ? 'Activo' : 'Pausado') . '</span></div>';
    echo '<div class="cards four" style="margin-top:14px;">';
    echo '<div class="info-strip"><strong>Estado</strong><br>' . ($config['enabled'] ? '✅ Publicación automática activa' : '⏸️ Pausado') . '</div>';
    echo '<div class="info-strip"><strong>Líneas habilitadas</strong><br>' . count($enabledLines) . ' de ' . count($allLines) . '</div>';
    echo '<div class="info-strip"><strong>Formato</strong><br>' . e(publicista_estados_wasap_format_options()[$config['formato']] ?? $config['formato']) . '</div>';
    $freqLabel = publicista_estados_wasap_frecuencia_options()[$config['frecuencia_tipo']] ?? $config['frecuencia_tipo'];
    $freqDetail = $config['frecuencia_tipo'] === 'cada_x_horas' ? "Cada {$config['frecuencia_valor']}h" : "{$config['frecuencia_valor']} veces/día";
    echo '<div class="info-strip"><strong>Frecuencia</strong><br>' . e($freqLabel . ' — ' . $freqDetail) . ' (' . e($config['hora_inicio']) . '–' . e($config['hora_fin']) . ')</div>';
    echo '</div>';

    $lastLog = !empty($log) ? end($log) : null;
    $lastPubAt = $lastLog ? $lastLog['published_at'] : '—';
    $lastResult = $lastLog ? ($lastLog['resultado'] === 'ok' ? '✅ OK' : '❌ Error') : '—';
    echo '<div class="cards four" style="margin-top:12px;">';
    echo '<div class="info-strip"><strong>Última publicación</strong><br>' . e($lastPubAt) . '</div>';
    echo '<div class="info-strip"><strong>Último resultado</strong><br>' . $lastResult . '</div>';
    if ($lastLog) {
        echo '<div class="info-strip"><strong>Última línea usada</strong><br>' . e($lastLog['linea_nombre'] ?? '—') . '</div>';
        echo '<div class="info-strip"><strong>Último HTTP</strong><br>' . e((string)($lastLog['http_code'] ?? '—')) . '</div>';
    } else {
        echo '<div class="info-strip"><strong>Última línea</strong><br>—</div>';
        echo '<div class="info-strip"><strong>Último HTTP</strong><br>—</div>';
    }
    echo '</div>';
    echo '</section>';

    // ── Config form ────────────────────────────────────────────────
    echo '<section class="panel panel-space">';
    echo '<div class="section-head"><div><h2>Configuración</h2><p>Define cómo y cuándo se publican los estados de WhatsApp con fotos de las chicas activas.</p></div></div>';
    echo '<form method="post" class="form-grid">';
    echo '<input type="hidden" name="action" value="save_estados_wasap_config">';

    // Enabled
    echo '<div class="field">';
    echo '<label>Activado</label>';
    echo '<label style="display:flex;gap:8px;align-items:center;margin-top:10px;">';
    echo '<input type="checkbox" name="enabled" value="1"' . ($config['enabled'] ? ' checked' : '') . '>';
    echo 'Publicar estados automáticamente';
    echo '</label>';
    echo '</div>';

    // Frequency type
    echo '<div class="field">';
    echo '<label>Frecuencia</label>';
    echo '<select name="frecuencia_tipo" style="margin-top:8px;">';
    foreach (publicista_estados_wasap_frecuencia_options() as $val => $label) {
        $sel = $config['frecuencia_tipo'] === $val ? ' selected' : '';
        echo '<option value="' . e($val) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Frequency value
    echo '<div class="field">';
    echo '<label>Valor</label>';
    echo '<input type="number" name="frecuencia_valor" value="' . e((string)$config['frecuencia_valor']) . '" min="1" max="24" style="margin-top:8px;width:100%;">';
    echo '<div class="field-help">Horas entre publicaciones (si "Cada X horas") o veces al día (si "X veces al día").</div>';
    echo '</div>';

    // Time range
    echo '<div class="field"><label>Hora inicio</label><input type="time" name="hora_inicio" value="' . e($config['hora_inicio']) . '" style="margin-top:8px;width:100%;"></div>';
    echo '<div class="field"><label>Hora fin</label><input type="time" name="hora_fin" value="' . e($config['hora_fin']) . '" style="margin-top:8px;width:100%;"></div>';

    // Format
    echo '<div class="field full">';
    echo '<label>Formato de publicación</label>';
    echo '<select name="formato" style="margin-top:8px;width:100%;max-width:420px;">';
    foreach (publicista_estados_wasap_format_options() as $val => $label) {
        $sel = $config['formato'] === $val ? ' selected' : '';
        echo '<option value="' . e($val) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '<div class="field-help">Elige el estilo del estado. "Mix aleatorio" alterna entre todos los formatos.</div>';
    echo '</div>';

    // Lines selector
    echo '<div class="field full">';
    echo '<label>Líneas bot casa</label>';
    if (empty($allLines)) {
        echo '<div class="empty" style="margin-top:8px;">No hay líneas con uso "bot casa" y WAHA configurado. Añádelas en Josué → Teléfonos.</div>';
    } else {
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin-top:8px;">';
        foreach ($allLines as $line) {
            $checked = in_array($line['id'], $enabledIds, true) ? ' checked' : '';
            echo '<label class="info-strip" style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 12px;">';
            echo '<input type="checkbox" name="lineas[]" value="' . e($line['id']) . '"' . $checked . '>';
            echo '<span><strong>' . e($line['nombre']) . '</strong><br><span class="muted">' . e($line['tfono']) . ' · puerto ' . e($line['waha_port']) . '</span></span>';
            echo '</label>';
        }
        echo '</div>';
        echo '<div class="field-help">Selecciona las líneas en las que se publicará el estado. Se enviará el mismo texto a todas las seleccionadas.</div>';
    }
    echo '</div>';

    echo '<div class="full" style="display:flex;gap:10px;flex-wrap:wrap;">';
    echo '<button class="btn-primary">💾 Guardar configuración</button>';
    echo '</div>';
    echo '</form>';
    echo '</section>';

    // ── Manual publish ─────────────────────────────────────────────
    echo '<section class="panel panel-space">';
    echo '<div class="section-head"><div><h2>Publicación manual</h2><p>Publica un estado ahora mismo en las líneas habilitadas, sin esperar a la automatización. Ideal para pruebas o emergencias.</p></div>';
    echo '<div class="section-head-actions">';
    echo '<form method="post" class="inline-form">';
    echo '<input type="hidden" name="action" value="publicar_estado_manual">';
    echo '<button class="btn-primary">📤 Publicar ahora</button>';
    echo '</form>';
    echo '</div></div>';
    echo '</section>';

    // ── Log table ──────────────────────────────────────────────────
    echo '<section class="panel panel-space">';
    echo '<div class="section-head"><div><h2>Historial de publicaciones</h2><p>Últimas ' . min(count($log), 200) . ' publicaciones registradas.</p></div></div>';

    if (empty($log)) {
        echo '<div class="empty" style="margin-top:12px;">No hay publicaciones registradas todavía. Usa "Publicar ahora" para hacer una prueba.</div>';
    } else {
        echo '<div class="table-wrap" style="margin-top:12px;"><table><thead><tr>';
        echo '<th>Fecha</th><th>Línea</th><th>Formato</th><th>Resultado</th><th>HTTP</th><th>Vista previa</th>';
        echo '</tr></thead><tbody>';
        $reversedLog = array_reverse($log);
        foreach ($reversedLog as $entry) {
            $ok = ($entry['resultado'] ?? '') === 'ok';
            $badge = $ok ? '<span class="summary-badge">✅ OK</span>' : '<span class="summary-badge" style="background:var(--danger,#c0392b);">❌ Error</span>';
            $date = !empty($entry['published_at']) ? date('d/m H:i', strtotime($entry['published_at'])) : '—';
            $preview = mb_substr((string)($entry['texto'] ?? ''), 0, 80) . (mb_strlen((string)($entry['texto'] ?? '')) > 80 ? '…' : '');
            $errorMsg = !$ok && !empty($entry['error']) ? '<br><span class="muted" style="color:var(--danger,#c0392b);">' . e($entry['error']) . '</span>' : '';
            echo '<tr>';
            echo '<td style="white-space:nowrap;">' . e($date) . '</td>';
            echo '<td>' . e($entry['linea_nombre'] ?? $entry['linea_id'] ?? '—') . '</td>';
            echo '<td>' . e($entry['formato_usado'] ?? '—') . '</td>';
            echo '<td>' . $badge . $errorMsg . '</td>';
            echo '<td>' . e((string)($entry['http_code'] ?? '—')) . '</td>';
            echo '<td style="max-width:300px;white-space:pre-wrap;word-break:break-word;">' . e($preview) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
}

function render_publicista_calculo_publicidad_page() {
    $defaults = array(
        'city' => 'Burriana',
        'province' => 'Castellón',
        'category' => '',
        'num_girls' => '1',
        'autos_start_min' => '10:30',
        'autos_end_max' => '04:00',
    );

    $form = array_merge($defaults, array_intersect_key($_GET, $defaults));
    $strategyWindow = publicista_ads_strategy_window_normalize(array(
        'start' => (string)($form['autos_start_min'] ?? ''),
        'end' => (string)($form['autos_end_max'] ?? ''),
    ));
    $form['autos_start_min'] = $strategyWindow['start'];
    $form['autos_end_max'] = $strategyWindow['end'];
    $shouldAnalyze = trim((string) request_get('analyze')) === '1';
    $result = null;
    $plannings = publicista_plannings_get();
    $selectedPlanningId = trim((string)request_get('planning'));
    $selectedPlanning = $selectedPlanningId !== '' ? publicista_planning_get($selectedPlanningId) : null;

    if ($shouldAnalyze) {
        $city = trim((string) ($form['city'] ?? ''));
        $province = trim((string) ($form['province'] ?? ''));
        $category = trim((string) ($form['category'] ?? ''));
        $numGirls = max(1, min(8, (int) ($form['num_girls'] ?? 1)));
        $strategyWindow = publicista_ads_strategy_window_normalize(array(
            'start' => (string)($form['autos_start_min'] ?? ''),
            'end' => (string)($form['autos_end_max'] ?? ''),
        ));

        $comp = publicista_ads_scrape($city, $category, $province);
        $strategyPack = publicista_ads_build_strategy_pack($comp, $numGirls, $strategyWindow);
        $strategies = is_array($strategyPack['strategies'] ?? null) ? $strategyPack['strategies'] : array();
        $grandTotal = (float)($strategyPack['grand_total'] ?? 0);

        $categories = publicista_ads_categories();
        $result = array(
            'city' => $city,
            'province' => $province,
            'catLabel' => isset($categories[$category]) ? $categories[$category] : 'Todas las categorías',
            'numGirls' => $numGirls,
            'comp' => $comp,
            'strategyPack' => $strategyPack,
            'strategyWindow' => $strategyWindow,
            'strategies' => $strategies,
            'grandTotal' => $grandTotal,
        );
    }

    if (!$result && $selectedPlanning) {
        $savedCompetition = is_array($selectedPlanning['competition_snapshot'] ?? null) ? $selectedPlanning['competition_snapshot'] : array();
        $savedOptions = is_array($selectedPlanning['recommendation_options'] ?? null) ? $selectedPlanning['recommendation_options'] : array();
        $savedDefaultOptionCode = trim((string)($selectedPlanning['default_option_code'] ?? 'recommended'));
        $savedDefaultOption = is_array($savedOptions[$savedDefaultOptionCode] ?? null) ? $savedOptions[$savedDefaultOptionCode] : array();
        if (empty($savedDefaultOption)) {
            foreach (array('accepted', 'recommended', 'optimal') as $fallbackOptionCode) {
                if (!empty($savedOptions[$fallbackOptionCode]) && is_array($savedOptions[$fallbackOptionCode])) {
                    $savedDefaultOptionCode = $fallbackOptionCode;
                    $savedDefaultOption = $savedOptions[$fallbackOptionCode];
                    break;
                }
            }
        }
        if (empty($savedOptions) && !empty($selectedPlanning['strategy_snapshot'])) {
            $savedOptions[$savedDefaultOptionCode] = array(
                'label' => 'Versión guardada',
                'strategies' => is_array($selectedPlanning['strategy_snapshot'] ?? null) ? $selectedPlanning['strategy_snapshot'] : array(),
                'grand_total' => (float)($selectedPlanning['cost_snapshot']['grand_total'] ?? 0),
                'avg_per_product' => max(1, (int)($selectedPlanning['num_products_target'] ?? 1)) > 0
                    ? ((float)($selectedPlanning['cost_snapshot']['grand_total'] ?? 0) / max(1, (int)($selectedPlanning['num_products_target'] ?? 1)))
                    : 0,
                'warnings' => array(),
                'comparison_note' => 'Snapshot recuperado desde una estrategia ya guardada.',
            );
            $savedDefaultOption = $savedOptions[$savedDefaultOptionCode];
        }
        $result = array(
            'city' => (string)($selectedPlanning['city'] ?? ''),
            'province' => (string)($selectedPlanning['province'] ?? ''),
            'catLabel' => (string)($selectedPlanning['category_label'] ?? 'Todas las categorías'),
            'numGirls' => max(1, (int)($selectedPlanning['num_products_target'] ?? 1)),
            'comp' => $savedCompetition,
            'strategyPack' => array(
                'options' => $savedOptions,
                'default_option_code' => $savedDefaultOptionCode,
                'default' => $savedDefaultOption,
                'strategies' => is_array($savedDefaultOption['strategies'] ?? null) ? $savedDefaultOption['strategies'] : (is_array($selectedPlanning['strategy_snapshot'] ?? null) ? $selectedPlanning['strategy_snapshot'] : array()),
                'grand_total' => (float)($savedDefaultOption['grand_total'] ?? ($selectedPlanning['cost_snapshot']['grand_total'] ?? 0)),
                'chooser_note' => trim((string)($selectedPlanning['summary']['narrative'] ?? '')),
            ),
            'strategyWindow' => is_array($selectedPlanning['selection_rules']['schedule_window'] ?? null)
                ? publicista_ads_strategy_window_normalize((array)$selectedPlanning['selection_rules']['schedule_window'])
                : $strategyWindow,
            'strategies' => is_array($savedDefaultOption['strategies'] ?? null) ? $savedDefaultOption['strategies'] : (is_array($selectedPlanning['strategy_snapshot'] ?? null) ? $selectedPlanning['strategy_snapshot'] : array()),
            'grandTotal' => (float)($savedDefaultOption['grand_total'] ?? ($selectedPlanning['cost_snapshot']['grand_total'] ?? 0)),
        );
        if (trim((string)$form['category']) === '' && trim((string)($selectedPlanning['category'] ?? '')) !== '') {
            $form['category'] = (string)$selectedPlanning['category'];
        }
    }

    $prices = publicista_ads_prices();

    echo '<section class="panel panel-space publicista-ads-intro">';
    echo '<div class="branch-panel-head"><h2>Estrategias publicitarias</h2><span class="summary-badge">Destacamos</span></div>';
    echo '<div class="cards three" style="margin-top:12px;">';
    echo '<div class="info-strip"><strong>Paso 1</strong><br>Indica ciudad, categoría y cuántas chicas quieres empujar.</div>';
    echo '<div class="info-strip"><strong>Paso 2</strong><br>Se hace scraping del listado y se mide competencia real: TOP, autorenuevas, combinaciones TOP+auto y total.</div>';
    echo '<div class="info-strip"><strong>Paso 3</strong><br>El CRM te devuelve perfiles recomendados, coste, horarios y una línea de tiempo.</div>';
    echo '</div>';
    echo '</section>';

    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>Estrategias guardadas</h2><span class="summary-badge">' . e((string)count($plannings)) . '</span></div>';
    if ($selectedPlanning) {
        $planSummary = is_array($selectedPlanning['summary'] ?? null) ? $selectedPlanning['summary'] : array();
        echo '<div class="info-strip" style="margin-top:12px;"><strong>Estrategia abierta:</strong> ' . e($selectedPlanning['nombre'] ?? '') . ' · v' . e((string)($selectedPlanning['version'] ?? 1)) . '</div>';
        echo '<div class="cards four" style="margin-top:12px;">';
        echo '<div class="info-strip"><strong>Productos objetivo</strong><br>' . e((string)($selectedPlanning['num_products_target'] ?? 0)) . '</div>';
        echo '<div class="info-strip"><strong>Perfiles calculados</strong><br>' . e((string)($planSummary['profiles_total'] ?? 0)) . '</div>';
        echo '<div class="info-strip"><strong>Coste snapshot</strong><br>' . e(publicista_ads_euros((float)($selectedPlanning['cost_snapshot']['grand_total'] ?? 0))) . '</div>';
        echo '<div class="info-strip"><strong>Calculado</strong><br>' . e(format_created_at($selectedPlanning['calculated_at'] ?? '')) . '</div>';
        echo '</div>';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">';
        echo '<form method="post" class="inline-form"><input type="hidden" name="action" value="duplicate_publicista_planning"><input type="hidden" name="id" value="' . e($selectedPlanning['id']) . '"><button class="btn-secondary-mini">Duplicar versión</button></form>';
        echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar este planning?\')"><input type="hidden" name="action" value="delete_publicista_planning"><input type="hidden" name="id" value="' . e($selectedPlanning['id']) . '"><button class="btn-danger-mini">Eliminar</button></form>';
        echo '</div>';
    }
    if (empty($plannings)) {
        echo '<div class="empty" style="margin-top:12px;">Todavía no hay estrategias guardadas. Cuando lances un análisis podrás convertirlo en una estrategia reutilizable.</div>';
    } else {
        render_live_filter('#publicistaPlanningsRows tr[data-filter-text]', 'Buscar estrategia, ciudad o categoría...');
        echo '<div class="table-wrap" style="margin-top:12px;"><table><thead><tr><th>Estrategia</th><th>Objetivo</th><th>Coste</th><th>Calculado</th><th>Acciones</th></tr></thead><tbody id="publicistaPlanningsRows">';
        foreach ($plannings as $planRow) {
            $sum = is_array($planRow['summary'] ?? null) ? $planRow['summary'] : array();
            $planName = trim((string)($planRow['nombre'] ?? ''));
            if ($planName === '') {
                $planName = publicista_planning_compose_name(
                    (string)($planRow['city'] ?? ''),
                    (string)($planRow['province'] ?? ''),
                    (string)($planRow['category_label'] ?? ''),
                    (int)($planRow['num_products_target'] ?? 0)
                );
            }
            $search = strtolower(trim(($planRow['nombre'] ?? '') . ' ' . ($planRow['city'] ?? '') . ' ' . ($planRow['province'] ?? '') . ' ' . ($planRow['category_label'] ?? '')));
            echo '<tr data-filter-text="' . e($search) . '">';
            echo '<td><strong>' . e($planName) . '</strong><br><span class="muted">Nombre estrategia · ' . e($planRow['city'] ?? '') . (($planRow['province'] ?? '') !== '' ? ' · ' . e($planRow['province'] ?? '') : '') . ' · ' . e($planRow['category_label'] ?? '') . ' · v' . e((string)($planRow['version'] ?? 1)) . '</span></td>';
            echo '<td><strong>' . e((string)($planRow['num_products_target'] ?? 0)) . '</strong> productos<br><span class="muted">' . e((string)($sum['profiles_total'] ?? 0)) . ' perfiles · ' . e((string)($sum['warnings_count'] ?? 0)) . ' avisos</span></td>';
            echo '<td>' . e(publicista_ads_euros((float)($planRow['cost_snapshot']['grand_total'] ?? 0))) . '</td>';
            echo '<td>' . e(format_created_at($planRow['calculated_at'] ?? ($planRow['updated_at'] ?? ''))) . '</td>';
            echo '<td><a class="mini-link" href="' . e(publicista_page_url('estrategias', array('planning' => $planRow['id']))) . '">Abrir</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';

    echo '<div class="cards two">';

    echo '<section class="panel">';
    echo '<div class="section-head"><div><h2>Parámetros de análisis</h2><p>Esta pantalla sustituye a estrategiaTops.php, pero integrada ya en el diseño del CRM.</p></div></div>';
    echo '<form method="get" class="form-grid">';
    echo '<input type="hidden" name="page" value="publicista">';
    echo '<input type="hidden" name="tab" value="estrategias">';
    echo '<input type="hidden" name="analyze" value="1">';

    echo '<div class="field"><label>Ciudad</label><input type="text" name="city" value="' . e($form['city']) . '" required></div>';
    echo '<div class="field"><label>Provincia</label><input type="text" name="province" value="' . e($form['province']) . '"></div>';
    echo '<div class="field"><label>Categoría</label><select name="category">';
    foreach (publicista_ads_categories() as $value => $label) {
        echo '<option value="' . e($value) . '"' . (((string) $form['category'] === (string) $value) ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="field"><label>Número de chicas</label><input type="number" name="num_girls" min="1" max="8" value="' . e((string) ((int) $form['num_girls'])) . '"></div>';
    echo '<div class="field"><label>Hora mínima autosubidas</label><input type="time" name="autos_start_min" value="' . e((string)$form['autos_start_min']) . '"></div>';
    echo '<div class="field"><label>Hora máxima autosubidas</label><input type="time" name="autos_end_max" value="' . e((string)$form['autos_end_max']) . '"></div>';
    echo '<div class="full"><div class="info-strip"><strong>Ventana de autosubidas</strong><br>Todos los horarios pagados se recalculan dentro de esta franja y el sistema intenta separarlos para evitar subidas demasiado cercanas.</div></div>';
    echo '<div class="full" style="display:flex;gap:10px;flex-wrap:wrap;">';
    echo '<button class="btn-primary" type="submit">Analizar y generar estrategia</button>';
    echo '<a class="btn-secondary-mini" href="' . e(publicista_page_url('estrategias')) . '">Limpiar</a>';
    echo '</div>';
    echo '</form>';
    echo '</section>';

    echo '<section class="panel">';
    echo '<div class="branch-panel-head"><h2>Precios configurados</h2><span class="summary-badge">Base</span></div>';
    echo '<div class="table-wrap" style="margin-top:12px;"><table><tbody>';
    echo '<tr><td>TOP (10 días)</td><td style="text-align:right;"><strong>' . e(publicista_ads_euros((float) $prices['top'])) . '</strong></td></tr>';
    echo '<tr><td>Autorenueva 10 sub/día</td><td style="text-align:right;"><strong>' . e(publicista_ads_euros((float) $prices['auto7'])) . '</strong></td></tr>';
    echo '<tr><td>Autorenueva 4 sub/día</td><td style="text-align:right;"><strong>' . e(publicista_ads_euros((float) $prices['auto4'])) . '</strong></td></tr>';
    echo '</tbody></table></div>';
    echo '<div class="info-strip" style="margin-top:12px;"><strong>Nota:</strong> el cálculo sigue tus precios internos actuales. Si cambian en la web, actualiza solo los importes en la función publicista_ads_prices().</div>';
    echo '</section>';

    echo '</div>';

    if (!$result) {
        echo '<section class="panel panel-space">';
        echo '<div class="empty">Todavía no has lanzado ningún análisis. Completa el formulario y pulsa <strong>Analizar y generar estrategia</strong>.</div>';
        echo '</section>';
        return;
    }

    $comp = $result['comp'];
    $level = $comp['level'] ?? 'media';

    if (!empty($comp['notice'])) {
        echo '<section class="panel panel-space"><div class="publicista-ads-warning">⚠️ ' . e($comp['notice']) . '</div></section>';
    }

    echo '<section class="panel panel-space">';
    echo '<div class="section-head"><div><h2>Análisis de competencia</h2><p>' . e($result['city']) . (($result['province'] !== '') ? ' · ' . e($result['province']) : '') . ' · ' . e($result['catLabel']) . '</p></div><div class="section-head-actions">';
    echo '<span class="publicista-ads-level-pill" style="background:' . e(publicista_ads_level_bg($level)) . ';color:' . e(publicista_ads_level_fg($level)) . ';">' . e(publicista_ads_level_icon($level)) . ' Competencia ' . e(publicista_ads_level_label($level)) . '</span>';
    echo '</div></div>';
    echo '<div class="cards three" style="margin-top:14px;">';
    echo '<div class="info-strip"><strong>TOPs</strong><br>' . e((string) ($comp['top'] ?? 0)) . '</div>';
    echo '<div class="info-strip"><strong>Autorenuevas</strong><br>' . e((string) ($comp['auto'] ?? 0)) . '</div>';
    echo '<div class="info-strip"><strong>Total perfiles</strong><br>' . e((string) ($comp['total'] ?? 0)) . '</div>';
    echo '</div>';
    echo '<div class="info-strip" style="margin-top:12px;"><strong>URL analizada:</strong> <a class="mini-link" href="' . e($comp['url'] ?? '#') . '" target="_blank" rel="noopener">Abrir listado</a> · ' . (!empty($comp['scraped']) ? 'Datos en tiempo real' : 'Datos estimados') . '</div>';

    $topCount = (int) ($comp['top'] ?? 0);
    if ($topCount <= 15) {
        echo '<div class="info-strip" style="margin-top:12px;"><strong>Lectura rápida:</strong> con ' . e((string) $topCount) . ' TOPs activos, todos pueden ser visibles al mismo tiempo. Un TOP ya pesa mucho.</div>';
    } else {
        echo '<div class="info-strip" style="margin-top:12px;"><strong>Lectura rápida:</strong> con ' . e((string) $topCount) . ' TOPs activos ya hay rotación. Necesitas más de un TOP para ganar probabilidad de aparición.</div>';
    }
    $marketSignals = is_array($comp['market_signals'] ?? null) ? $comp['market_signals'] : array();
    echo '<div class="cards three" style="margin-top:12px;">';
    echo '<div class="info-strip"><strong>TOP + Auto visibles</strong><br>' . e((string)($comp['combo_top_auto'] ?? 0)) . ' anuncios</div>';
    echo '<div class="info-strip"><strong>Riesgo ostentación</strong><br>' . e(ucfirst((string)($marketSignals['ostentation_risk'] ?? 'medio'))) . '</div>';
    echo '<div class="info-strip"><strong>Política combo</strong><br>' . e((string)($marketSignals['combo_policy'] ?? 'selective')) . '</div>';
    echo '</div>';
    if (!empty($marketSignals['combo_note'])) {
        echo '<div class="info-strip" style="margin-top:12px;"><strong>Lectura sobre TOP + auto:</strong> ' . e((string)$marketSignals['combo_note']) . '</div>';
    }
    if (!empty($comp['sources']) && is_array($comp['sources'])) {
        echo '<section class="panel panel-space" style="margin-top:14px;">';
        echo '<h3>Fuentes usadas para la media inteligente</h3>';
        echo '<div class="table-wrap"><table><thead><tr><th>Variante</th><th>Estado</th><th>TOP</th><th>Auto</th><th>Total</th><th>TOP+Auto</th></tr></thead><tbody>';
        foreach ((array)$comp['sources'] as $src) {
            $srcStats = is_array($src['stats'] ?? null) ? $src['stats'] : array();
            echo '<tr>';
            echo '<td><a class="mini-link" href="' . e((string)($src['url'] ?? '#')) . '" target="_blank" rel="noopener">' . e((string)($src['label'] ?? $src['code'] ?? 'Fuente')) . '</a></td>';
            echo '<td>' . (!empty($src['scraped']) ? 'OK' : 'Sin datos') . '</td>';
            echo '<td>' . e((string)($srcStats['top'] ?? 0)) . '</td>';
            echo '<td>' . e((string)($srcStats['auto'] ?? 0)) . '</td>';
            echo '<td>' . e((string)($srcStats['total'] ?? 0)) . '</td>';
            echo '<td>' . e((string)($srcStats['combo_top_auto'] ?? 0)) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '</section>';
    }
    echo '</section>';

    echo '<section class="panel panel-space publicista-ads-cost-summary">';
    echo '<div class="branch-panel-head"><h2>Resumen de inversión</h2><span class="summary-badge">' . e((string) $result['numGirls']) . ' chica' . ($result['numGirls'] > 1 ? 's' : '') . '</span></div>';
    echo '<div class="cards three" style="margin-top:14px;">';
    echo '<div class="info-strip"><strong>Total acumulado</strong><br>' . e(publicista_ads_euros((float) $result['grandTotal'])) . '</div>';
    echo '<div class="info-strip"><strong>Coste medio por chica</strong><br>' . e(publicista_ads_euros($result['numGirls'] > 0 ? ((float) $result['grandTotal'] / (int) $result['numGirls']) : 0)) . '</div>';
    echo '<div class="info-strip"><strong>Nivel</strong><br>' . e(publicista_ads_level_label($level)) . '</div>';
    echo '</div>';
    echo '</section>';

    $strategyPack = is_array($result['strategyPack'] ?? null) ? $result['strategyPack'] : array();
    $strategyWindow = is_array($result['strategyWindow'] ?? null) ? $result['strategyWindow'] : publicista_ads_strategy_window_defaults();
    $recommendationOptions = is_array($strategyPack['options'] ?? null) ? $strategyPack['options'] : array();
    $defaultOptionCode = trim((string)($strategyPack['default_option_code'] ?? 'recommended'));

    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>Elección rápida de inversión</h2><span class="summary-badge">Comparativa</span></div>';
    echo '<div class="info-strip" style="margin-top:12px;"><strong>Ventana aplicada:</strong> ' . e((string)$strategyWindow['start']) . ' → ' . e((string)$strategyWindow['end']) . '</div>';
    if (!empty($strategyPack['chooser_note'])) {
        echo '<div class="info-strip" style="margin-top:12px;"><strong>Lectura rápida:</strong> ' . e((string)$strategyPack['chooser_note']) . '</div>';
    }
    echo '<div class="cards three" style="margin-top:12px;">';
    foreach (array('accepted', 'recommended', 'optimal') as $optionCode) {
        $option = is_array($strategyPack['options'][$optionCode] ?? null) ? $strategyPack['options'][$optionCode] : array();
        if (empty($option)) continue;
        $optionProfiles = 0;
        foreach ((array)($option['strategies'] ?? array()) as $optionStrategy) {
            $optionProfiles += count((array)($optionStrategy['profiles'] ?? array()));
        }
        $tag = ($optionCode === 'accepted') ? 'Menos inversión' : (($optionCode === 'optimal') ? 'Más inversión' : 'Punto medio');
        $cardStyle = ($optionCode === $defaultOptionCode) ? 'border:1px solid rgba(99,102,241,.55);box-shadow:0 0 0 1px rgba(99,102,241,.18) inset;' : '';
        echo '<div class="info-strip" style="' . e($cardStyle) . '">';
        echo '<strong>' . e((string)($option['label'] ?? 'Opción')) . '</strong><br><span class="muted">' . e($tag) . '</span>';
        echo '<div style="margin-top:10px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;">';
        echo '<div><span class="muted">Total</span><br><strong>' . e(publicista_ads_euros((float)($option['grand_total'] ?? 0))) . '</strong></div>';
        echo '<div><span class="muted">Por chica</span><br><strong>' . e(publicista_ads_euros((float)($option['avg_per_product'] ?? 0))) . '</strong></div>';
        echo '<div><span class="muted">Perfiles</span><br><strong>' . e((string)$optionProfiles) . '</strong></div>';
        echo '<div><span class="muted">Avisos</span><br><strong>' . e((string)count((array)($option['warnings'] ?? array()))) . '</strong></div>';
        echo '</div>';
        if (!empty($option['explanation'])) echo '<div style="margin-top:10px;">' . e((string)$option['explanation']) . '</div>';
        if (!empty($option['decision_help'])) echo '<div class="muted" style="margin-top:8px;">' . e((string)$option['decision_help']) . '</div>';
        if (!empty($option['comparison_note'])) echo '<div class="muted" style="margin-top:8px;">' . e((string)$option['comparison_note']) . '</div>';
        if ($optionCode === 'accepted' && !empty($option['savings_vs_optimal'])) {
            echo '<div style="margin-top:8px;"><strong>Ahorras ' . e(publicista_ads_euros((float)$option['savings_vs_optimal'])) . '</strong> frente a la versión fuerte.</div>';
        } elseif ($optionCode === 'recommended') {
            echo '<div style="margin-top:8px;">+' . e(publicista_ads_euros((float)($option['extra_vs_accepted'] ?? 0))) . ' frente a ahorro · ahorras ' . e(publicista_ads_euros((float)($option['savings_vs_optimal'] ?? 0))) . ' frente a empuje fuerte.</div>';
        } elseif ($optionCode === 'optimal') {
            echo '<div style="margin-top:8px;">Inviertes <strong>' . e(publicista_ads_euros((float)($option['extra_vs_accepted'] ?? 0))) . '</strong> más que en la versión ahorro.</div>';
        }
        echo '</div>';
    }
    echo '</div>';
    echo '</section>';

    $marketSignals = is_array($comp['market_signals'] ?? null) ? $comp['market_signals'] : array();
    $costSnapshot = array(
        'grand_total' => (float)$result['grandTotal'],
        'avg_per_product' => $result['numGirls'] > 0 ? ((float)$result['grandTotal'] / (int)$result['numGirls']) : 0,
        'competition_level' => $level,
        'accepted_total' => (float)publicista_array_get(publicista_array_get($recommendationOptions, 'accepted', array()), 'grand_total', 0),
        'recommended_total' => (float)publicista_array_get(publicista_array_get($recommendationOptions, 'recommended', array()), 'grand_total', 0),
        'optimal_total' => (float)publicista_array_get(publicista_array_get($recommendationOptions, 'optimal', array()), 'grand_total', 0),
    );
    $selectionRules = array(
        'min_products' => max(1, (int)$result['numGirls']),
        'max_products' => max(1, (int)$result['numGirls']),
        'source' => 'estrategias',
        'default_option_code' => $defaultOptionCode,
        'feedback_modes' => array('accepted', 'recommended', 'optimal'),
        'schedule_window' => $strategyWindow,
    );
    $planningDraft = array(
        'portal_code' => 'destacamos',
        'portal_label' => 'Destacamos',
        'portal_url' => (string)($comp['url'] ?? ''),
        'city' => (string)$result['city'],
        'province' => (string)$result['province'],
        'category' => (string)($form['category'] ?? ''),
        'category_label' => (string)$result['catLabel'],
        'num_products_target' => (int)$result['numGirls'],
        'competition_snapshot' => $comp,
        'pricing_snapshot' => $prices,
        'strategy_snapshot' => $result['strategies'],
        'recommendation_options' => $recommendationOptions,
        'analysis_sources' => is_array($comp['sources'] ?? null) ? $comp['sources'] : array(),
        'market_signals' => $marketSignals,
        'default_option_code' => $defaultOptionCode,
        'cost_snapshot' => $costSnapshot,
        'selection_rules' => $selectionRules,
        'summary' => publicista_planning_build_summary(array(
            'competition_snapshot' => $comp,
            'strategy_snapshot' => $result['strategies'],
            'market_signals' => $marketSignals,
            'default_option_code' => $defaultOptionCode,
            'num_products_target' => (int)$result['numGirls'],
        )),
    );
    $planningDefaultName = publicista_planning_compose_name($planningDraft['city'], $planningDraft['province'], $planningDraft['category_label'], $planningDraft['num_products_target']);

    echo '<section class="panel panel-space">';
    echo '<div class="section-head"><div><h2>Guardar estrategia</h2><p>Convierte este análisis en una estrategia reutilizable para futuras campañas.</p></div></div>';
    echo '<form method="post" class="form-grid">';
    echo '<input type="hidden" name="action" value="save_publicista_planning">';
    if ($selectedPlanning) {
        echo '<input type="hidden" name="id" value="' . e((string)($selectedPlanning['id'] ?? '')) . '">';
        echo '<input type="hidden" name="version" value="' . e((string)($selectedPlanning['version'] ?? 1)) . '">';
        echo '<input type="hidden" name="parent_planning_id" value="' . e((string)($selectedPlanning['parent_planning_id'] ?? '')) . '">';
    }
    echo '<input type="hidden" name="portal_code" value="destacamos">';
    echo '<input type="hidden" name="portal_label" value="Destacamos">';
    // Portal selector visible para poder elegir entre portales al guardar la estrategia
    echo '<div class="field"><label>Portal</label><select name="portal_code_visible" onchange="this.form.portal_code.value=this.value;this.form.portal_label.value=this.options[this.selectedIndex].text;">';
    foreach (publicista_account_portal_options() as $value => $label) {
        $sel = ($value === 'destacamos') ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select></div>';
    echo '<input type="hidden" name="portal_url" value="' . e((string)($comp['url'] ?? '')) . '">';
    echo '<input type="hidden" name="city" value="' . e((string)$result['city']) . '">';
    echo '<input type="hidden" name="province" value="' . e((string)$result['province']) . '">';
    echo '<input type="hidden" name="category" value="' . e((string)($form['category'] ?? '')) . '">';
    echo '<input type="hidden" name="category_label" value="' . e((string)$result['catLabel']) . '">';
    echo '<input type="hidden" name="num_products_target" value="' . e((string)((int)$result['numGirls'])) . '">';
    echo '<input type="hidden" name="competition_snapshot_json" value="' . e(json_encode($planningDraft['competition_snapshot'], JSON_UNESCAPED_UNICODE)) . '">';
    echo '<input type="hidden" name="pricing_snapshot_json" value="' . e(json_encode($planningDraft['pricing_snapshot'], JSON_UNESCAPED_UNICODE)) . '">';
    echo '<input type="hidden" name="strategy_snapshot_json" value="' . e(json_encode($planningDraft['strategy_snapshot'], JSON_UNESCAPED_UNICODE)) . '">';
    echo '<input type="hidden" name="recommendation_options_json" value="' . e(json_encode($planningDraft['recommendation_options'], JSON_UNESCAPED_UNICODE)) . '">';
    echo '<input type="hidden" name="analysis_sources_json" value="' . e(json_encode($planningDraft['analysis_sources'], JSON_UNESCAPED_UNICODE)) . '">';
    echo '<input type="hidden" name="market_signals_json" value="' . e(json_encode($planningDraft['market_signals'], JSON_UNESCAPED_UNICODE)) . '">';
    echo '<input type="hidden" name="default_option_code" value="' . e((string)$planningDraft['default_option_code']) . '">';
    echo '<input type="hidden" name="cost_snapshot_json" value="' . e(json_encode($planningDraft['cost_snapshot'], JSON_UNESCAPED_UNICODE)) . '">';
    echo '<input type="hidden" name="selection_rules_json" value="' . e(json_encode($planningDraft['selection_rules'], JSON_UNESCAPED_UNICODE)) . '">';
    echo '<input type="hidden" name="summary_json" value="' . e(json_encode($planningDraft['summary'], JSON_UNESCAPED_UNICODE)) . '">';
    echo '<input type="hidden" name="calculated_at" value="' . e($selectedPlanning ? (string)($selectedPlanning['calculated_at'] ?? now_datetime()) : now_datetime()) . '">';
    field_input('nombre', 'Nombre de la estrategia', $selectedPlanning ? (string)($selectedPlanning['nombre'] ?? $planningDefaultName) : $planningDefaultName, true);
    $planningNotes = $selectedPlanning
        ? (string)($selectedPlanning['notes'] ?? '')
        : ('Estrategia generada desde el estudio de mercado en tiempo real. Ventana autosubidas: ' . $strategyWindow['start'] . ' → ' . $strategyWindow['end'] . '.');
    field_textarea('notes', 'Notas internas', $planningNotes, 3);
    echo '<div class="full" style="display:flex;gap:10px;flex-wrap:wrap;">';
    echo '<button class="btn-primary">Guardar estrategia</button>';
    echo '</div>';
    echo '</form>';
    echo '</section>';

    $displayedOption = is_array($recommendationOptions[$defaultOptionCode] ?? null) ? $recommendationOptions[$defaultOptionCode] : array();
    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>Detalle operativo completo</h2><span class="summary-badge">3 versiones</span></div>';
    echo '<div class="info-strip" style="margin-top:12px;"><strong>Qué estás viendo abajo:</strong> el desglose completo de las tres versiones calculadas. La opción ' . e((string)($displayedOption['label'] ?? 'equilibrada')) . ' sigue marcada como referencia por defecto, pero ahora también puedes revisar completa la más barata y la más cara.</div>';
    echo '</section>';

    foreach (array('accepted', 'recommended', 'optimal') as $optionCode) {
        $option = is_array($recommendationOptions[$optionCode] ?? null) ? $recommendationOptions[$optionCode] : array();
        if (empty($option)) continue;
        render_publicista_strategy_option_detail($option, $optionCode === $defaultOptionCode);
    }
}

function render_publicista_cuentas_page() {
    $accounts = publicista_accounts_get(false);
    $telefonos = storage_read('telefonos.json');
    $editId = trim((string)request_get('edit'));
    $accountsById = array();
    foreach ($accounts as $accountRow) {
        $accountId = trim((string)($accountRow['id'] ?? ''));
        if ($accountId !== '') {
            $accountsById[$accountId] = $accountRow;
        }
    }
    $metricsById = !empty($accountsById) ? publicista_account_runtime_metrics_batch(array_keys($accountsById), $accountsById) : array();
    foreach ($accounts as $index => $accountRow) {
        $accountId = trim((string)($accountRow['id'] ?? ''));
        if ($accountId !== '' && isset($metricsById[$accountId])) {
            $accounts[$index] = publicista_account_runtime_metrics_apply($accountRow, $metricsById[$accountId]);
            $accountsById[$accountId] = $accounts[$index];
        }
    }
    $edit = ($editId !== '' && isset($accountsById[$editId])) ? $accountsById[$editId] : null;

    $telefonosByAccount = array();
    foreach ($telefonos as $tel) {
        $aid = trim((string)($tel['destacamos_id'] ?? ''));
        if ($aid === '') continue;
        if (!isset($telefonosByAccount[$aid])) $telefonosByAccount[$aid] = array();
        $telefonosByAccount[$aid][] = $tel;
    }

    $activeCount = 0;
    $blockedCount = 0;
    $createdAdsTotal = 0;
    $freeTasksTotal = 0;
    foreach ($accounts as $row) {
        if (($row['estado'] ?? '') === 'active') $activeCount++;
        if (($row['estado'] ?? '') === 'blocked') $blockedCount++;
        $createdAdsTotal += (int)($row['created_ads_count'] ?? 0);
        $freeTasksTotal += (int)($row['free_bump_tasks_count'] ?? 0);
    }

    echo '<div class="cards two">';

    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>Cuentas registradas</h2><span class="summary-badge">' . e((string)count($accounts)) . '</span></div>';
    if (empty($accounts)) {
        echo '<div class="empty">Todavía no hay cuentas registradas en Publicista.</div>';
    } else {
        render_live_filter('#publicistaAccountsRows tr[data-filter-text]', 'Buscar cuenta, portal o usuario...');
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Portal</th><th>Usuario</th><th>Estado</th><th>Salud</th><th>Automatización</th><th>IDs internos</th><th>Anuncios</th><th>Último uso</th><th>Acciones</th>';
        echo '</tr></thead><tbody id="publicistaAccountsRows">';
        foreach ($accounts as $row) {
            $searchText = strtolower(trim(($row['portal_label'] ?? '') . ' ' . ($row['portal_url'] ?? '') . ' ' . ($row['login_user'] ?? '') . ' ' . ($row['display_name'] ?? '') . ' ' . ($row['descripcion'] ?? '')));
            $runtime = $row['_runtime_metrics'] ?? ($metricsById[$row['id']] ?? publicista_account_runtime_metrics($row['id'], $accountsById));
            $linkedPhones = $runtime['linked_phones'] ?? ($telefonosByAccount[$row['id']] ?? array());
            echo '<tr data-filter-text="' . e($searchText) . '">';
            echo '<td><strong>' . e($row['portal_label'] ?? 'Portal') . '</strong><br><span class="muted">' . e($row['portal_url'] ?? '') . '</span></td>';
            echo '<td><div class="copy-row copy-row-vertical"><span>' . e($row['login_user'] ?? '') . '</span><button type="button" class="btn-copy-mini" data-copy="' . e($row['login_user'] ?? '') . '">Copiar</button></div><span class="muted">Prio ' . e((string)($row['priority_weight'] ?? 100)) . '</span></td>';
            echo '<td><span class="summary-badge">' . e(publicista_account_status_label($row['estado'] ?? 'active')) . '</span></td>';
            echo '<td>' . e(publicista_account_health_label($row['health_status'] ?? 'ok')) . '</td>';
            echo '<td>' . e(publicista_account_automation_label($row['automation_mode'] ?? 'manual')) . '<br><span class="muted">Límite diario: ' . e((string)($row['daily_publish_limit'] ?? 0)) . '</span></td>';
            echo '<td><strong>' . e((string)($runtime['listing_ids_total'] ?? 0)) . '</strong><br><span class="muted">Inventario editable</span></td>';
            echo '<td><strong>' . e((string)($row['created_ads_count'] ?? 0)) . '</strong> creados<br><span class="muted">' . e((string)($runtime['campaign_items_count'] ?? 0)) . ' items campaña · ' . e((string)count($linkedPhones)) . ' teléfonos · ' . e((string)($runtime['tasks_count'] ?? 0)) . ' tareas</span></td>';
            echo '<td>' . e(format_created_at($row['last_used_at'] ?? ($row['updated_at'] ?? ''))) . '</td>';
            echo '<td><a class="mini-link" href="' . e(publicista_page_url('cuentas', array('edit' => $row['id']))) . '">Editar</a> ';
            echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar esta cuenta de portal?\')">';
            echo '<input type="hidden" name="action" value="delete_publicista_account">';
            echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
            echo '<button class="btn-danger-mini">Eliminar</button>';
            echo '</form></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';

    echo '<section class="panel">';
    echo '<div class="section-head"><div><h2>' . ($edit ? 'Editar cuenta de portal' : 'Nueva cuenta de portal') . '</h2><p>Base operativa para futuras campañas. Aquí se guardan las cuentas reales que luego podrán recibir anuncios.</p></div></div>';
    echo '<form method="post" class="form-grid">';
    echo '<input type="hidden" name="action" value="save_publicista_account">';
    echo '<input type="hidden" name="id" value="' . e($edit['id'] ?? '') . '">';
    echo '<div class="field"><label>Portal</label><select name="portal_code">';
    foreach (publicista_account_portal_options() as $value => $label) {
        echo '<option value="' . e($value) . '"' . (($edit['portal_code'] ?? 'destacamos') === $value ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select></div>';
    field_input('portal_label', 'Etiqueta portal', $edit['portal_label'] ?? 'Destacamos');
    field_input('portal_url', 'Portal / URL', $edit['portal_url'] ?? ($edit['url'] ?? ''), true);
    field_input('login_user', 'Usuario', $edit['login_user'] ?? ($edit['user'] ?? ''), true);
    field_input('login_pass', 'Contraseña', $edit['login_pass'] ?? ($edit['pass'] ?? ''), true);
    field_input('display_name', 'Nombre interno', $edit['display_name'] ?? '');
    field_textarea('descripcion', 'Descripción visible', $edit['descripcion'] ?? '', 3);
    echo '<div class="field"><label>Estado</label><select name="estado">';
    foreach (publicista_account_status_options() as $value => $label) {
        echo '<option value="' . e($value) . '"' . (($edit['estado'] ?? 'active') === $value ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="field"><label>Automatización soportada</label><select name="automation_mode">';
    foreach (publicista_account_automation_options() as $value => $label) {
        echo '<option value="' . e($value) . '"' . (($edit['automation_mode'] ?? 'full_publish') === $value ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="field"><label>Salud operativa</label><select name="health_status">';
    foreach (publicista_account_health_options() as $value => $label) {
        echo '<option value="' . e($value) . '"' . (($edit['health_status'] ?? 'ok') === $value ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select></div>';
    field_input('priority_weight', 'Prioridad de reparto (100 = normal)', (string)($edit['priority_weight'] ?? 100));
    field_textarea('portal_listing_ids_raw', 'IDs internos de anuncios existentes (uno por línea)', implode("
", (array)($edit['portal_listing_ids'] ?? array())), 6);
    field_input('daily_publish_limit', 'Límite diario de publicaciones (0 = sin límite)', (string)($edit['daily_publish_limit'] ?? 0));
    field_input('created_ads_count', 'Anuncios creados (contador interno)', (string)($edit['created_ads_count'] ?? 0));
    field_input('free_bump_tasks_count', 'Tareas free bump (base manual)', (string)($edit['free_bump_tasks_count'] ?? 0));
    field_input('last_success_at', 'Último éxito (texto/fecha libre)', $edit['last_success_at'] ?? '');
    field_input('last_error_at', 'Último error (texto/fecha libre)', $edit['last_error_at'] ?? '');
    field_textarea('last_error', 'Detalle último error', $edit['last_error'] ?? '', 2);
    field_textarea('notes_internal', 'Notas internas', $edit['notes_internal'] ?? '', 3);
    echo '<div class="full" style="display:flex;gap:10px;flex-wrap:wrap;">';
    echo '<button class="btn-primary">Guardar cuenta</button>';
    if ($edit) {
        echo '<a class="btn-secondary-mini" href="' . e(publicista_page_url('cuentas')) . '">Nueva</a>';
    }
    echo '</div>';
    echo '</form>';

    if ($edit) {
        $linked = $telefonosByAccount[$edit['id']] ?? array();
        $runtime = $edit['_runtime_metrics'] ?? ($metricsById[$edit['id']] ?? publicista_account_runtime_metrics($edit['id'], $accountsById));
        list($canDelete, $deleteErrors) = publicista_account_can_delete($edit['id'], $runtime);
        echo '<hr class="sep">';
        echo '<h2>Integridad de la cuenta</h2>';
        echo '<div class="cards four" style="margin-bottom:12px;">';
        echo '<div class="info-strip"><strong>Teléfonos vinculados</strong><br>' . e((string)count($linked)) . '</div>';
        echo '<div class="info-strip"><strong>Items campaña</strong><br>' . e((string)($runtime['campaign_items_count'] ?? 0)) . '</div>';
        echo '<div class="info-strip"><strong>Tareas automáticas</strong><br>' . e((string)($runtime['tasks_count'] ?? 0)) . '</div>';
        echo '<div class="info-strip"><strong>IDs internos</strong><br>' . e((string)($runtime['listing_ids_total'] ?? 0)) . '</div>';
        echo '</div>';
        if (!$canDelete) {
            echo '<div class="publicista-ads-warning">Esta cuenta no se puede eliminar ahora mismo: ' . e(implode(' ', $deleteErrors)) . '</div>';
        } else {
            echo '<div class="info-strip">Esta cuenta está libre de relaciones críticas y se puede eliminar si hace falta.</div>';
        }
        echo '<h2 style="margin-top:16px;">Teléfonos vinculados</h2>';
        if (empty($linked)) {
            echo '<div class="empty">No hay teléfonos vinculados a esta cuenta.</div>';
        } else {
            echo '<div class="linked-tags">';
            foreach ($linked as $tel) {
                $label = trim(($tel['nombre'] ?? '') . ' · ' . ($tel['tfono'] ?? ''));
                echo '<a class="linked-tag" href="index.php?page=josue&tab=telefonos&edit=' . e($tel['id']) . '">' . e($label) . '</a>';
            }
            echo '</div>';
        }
    }
    echo '</section>';

    echo '<section class="panel">';
    echo '<div class="branch-panel-head"><h2>Resumen de cuentas</h2><span class="summary-badge">' . e((string)count($accounts)) . '</span></div>';
    $warningCount = 0;
    foreach ($accounts as $accTmp) {
        if (($accTmp['health_status'] ?? 'ok') !== 'ok') $warningCount++;
    }
    echo '<div class="cards five" style="margin-top:12px;">';
    echo '<div class="info-strip"><strong>Activas</strong><br>' . e((string)$activeCount) . '</div>';
    echo '<div class="info-strip"><strong>Bloqueadas</strong><br>' . e((string)$blockedCount) . '</div>';
    echo '<div class="info-strip"><strong>Con incidencias</strong><br>' . e((string)$warningCount) . '</div>';
    echo '<div class="info-strip"><strong>Anuncios creados</strong><br>' . e((string)$createdAdsTotal) . '</div>';
    echo '<div class="info-strip"><strong>Tareas free bump</strong><br>' . e((string)$freeTasksTotal) . '</div>';
    echo '</div>';
    echo '<div class="info-strip" style="margin-top:12px;"><strong>Bloque 1:</strong> la cuenta ya queda conectada con el modelo nuevo de campañas, items y tareas. Aunque todavía no los generemos desde UI, la integridad de la entidad ya está preparada.</div>';
    echo '</section>';

    echo '</div>';

}


function render_publicista_campanas_page() {
    $campaigns = publicista_campaigns_get();
    $plannings = publicista_plannings_get();
    $products = publicista_products_get();
    $accounts = publicista_accounts_get(false);
    $campaignItemCounts = publicista_campaign_item_counts_by_campaign();
    $planningsById = array();
    foreach ($plannings as $planRow) {
        $planId = trim((string)($planRow['id'] ?? ''));
        if ($planId !== '') $planningsById[$planId] = $planRow;
    }
    $editId = trim((string)request_get('edit'));
    $edit = $editId !== '' ? publicista_campaign_get($editId) : null;
    $editItems = array();
    $editItemsById = array();
    $editTasks = array();
    $editTasksByCampaignItemId = array();
    $editRuns = array();
    if ($edit) {
        $editItems = publicista_campaign_items_for_campaign($edit['id']);
        foreach ($editItems as $itemRow) {
            $itemId = trim((string)($itemRow['id'] ?? ''));
            if ($itemId !== '') {
                $editItemsById[$itemId] = $itemRow;
            }
        }
        $editTasks = publicista_tasks_for_campaign($edit['id']);
        foreach ($editTasks as $taskRow) {
            $campaignItemId = trim((string)($taskRow['campaign_item_id'] ?? ''));
            if ($campaignItemId === '') continue;
            if (!isset($editTasksByCampaignItemId[$campaignItemId])) $editTasksByCampaignItemId[$campaignItemId] = array();
            $editTasksByCampaignItemId[$campaignItemId][] = $taskRow;
        }
        $editRuns = publicista_runs_for_campaign($edit['id']);
    }
    $selectedPlanningId = trim((string)request_get('planning_id', ($edit['planning_id'] ?? '')));
    $selectedPlanning = $selectedPlanningId !== '' ? ($planningsById[$selectedPlanningId] ?? publicista_planning_get($selectedPlanningId)) : null;
    $selectedStrategyOptionCode = trim((string)($edit['strategy_option_code'] ?? ''));
    $selectedPlanningOptionMeta = $selectedPlanning ? publicista_campaign_planning_option_meta_map($selectedPlanning) : array();
    if ($selectedStrategyOptionCode === '' && $selectedPlanning) {
        $selectedStrategyOptionCode = trim((string)($selectedPlanning['default_option_code'] ?? 'recommended'));
    }
    $accountsWithListingIds = array();
    $accountsById = array();
    foreach ($accounts as $account) {
        $accountId = trim((string)($account['id'] ?? ''));
        if ($accountId !== '') {
            $accountsById[$accountId] = $account;
        }
        $listingIds = array_values((array)($account['portal_listing_ids'] ?? array()));
        if (!empty($listingIds)) {
            $account['_campaign_usage'] = array(
                'listing_ids' => $listingIds,
                'total' => count($listingIds),
                'assigned_map' => array(),
                'assigned_count' => 0,
                'available_ids' => $listingIds,
                'available_count' => count($listingIds),
            );
            $account['_runtime_metrics'] = array(
                'listing_ids_total' => count($listingIds),
            );
            $accountsWithListingIds[] = $account;
        }
    }

    $savedPlanningCount = count($plannings);

    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>Motor de campañas</h2><span class="summary-badge">Bloque 4</span></div>';
    echo '<div class="cards four" style="margin-top:12px;">';
    echo '<div class="info-strip"><strong>Campañas</strong><br>' . e((string)count($campaigns)) . '</div>';
    echo '<div class="info-strip"><strong>Estrategias guardadas</strong><br>' . e((string)$savedPlanningCount) . '</div>';
    echo '<div class="info-strip"><strong>Productos</strong><br>' . e((string)count($products)) . '</div>';
    echo '<div class="info-strip"><strong>Cuentas</strong><br>' . e((string)count($accountsWithListingIds)) . '</div>';
    echo '</div>';

    echo '<section class="panel">';
    echo '<div class="section-head"><div><h2>' . ($edit ? 'Editar campaña' : 'Nueva campaña') . '</h2></div></div>';
    if ($edit) {
        echo '<div class="info-strip" style="margin-bottom:12px;"><strong>Modo edición</strong><br>Estás editando una campaña existente. Este formulario modifica esta campaña; no se está creando una nueva.</div>';
    }
    echo '<form method="post" class="form-grid publicista-campaign-form" id="publicistaCampaignForm">';
    echo '<input type="hidden" name="id" value="' . e($edit['id'] ?? '') . '">';
    field_input('nombre', 'Nombre de campaña', $edit['nombre'] ?? ($selectedPlanning ? publicista_campaign_compose_name($selectedPlanning, 0) : ''), true);
    $requiredProducts = $selectedPlanning ? max(1, (int)($selectedPlanning['num_products_target'] ?? 1)) : 0;
    echo '<div class="field"><label>Estrategia</label><select name="planning_id" required class="js-publicista-campaign-planning">';
    echo '<option value="">Selecciona una estrategia...</option>';
    foreach ($plannings as $plan) {
        $selected = (($edit['planning_id'] ?? $selectedPlanningId) === ($plan['id'] ?? '')) ? ' selected' : '';
        $planName = trim((string)($plan['nombre'] ?? ''));
        if ($planName === '') {
            $planName = publicista_planning_compose_name(
                (string)($plan['city'] ?? ''),
                (string)($plan['province'] ?? ''),
                (string)($plan['category_label'] ?? ''),
                (int)($plan['num_products_target'] ?? 0)
            );
        }
        $planLabel = $planName;
        $planMeta = trim((string)($plan['city'] ?? ''));
        if (trim((string)($plan['category_label'] ?? '')) !== '') {
            $planMeta .= ($planMeta !== '' ? ' · ' : '') . (string)$plan['category_label'];
        }
        if ($planMeta !== '') {
            $planLabel .= ' · ' . $planMeta;
        }
        $planOptionMeta = publicista_campaign_planning_option_meta_map($plan);
        echo '<option value="' . e($plan['id'] ?? '') . '" data-required-products="' . e((string)max(1, (int)($plan['num_products_target'] ?? 1))) . '" data-strategy-options="' . e(json_encode($planOptionMeta, JSON_UNESCAPED_UNICODE)) . '" data-default-option="' . e((string)($plan['default_option_code'] ?? 'recommended')) . '"' . $selected . '>' . e($planLabel) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="field"><label>Versión de la estrategia</label><select name="strategy_option_code" required id="publicistaCampaignStrategyOption" class="js-publicista-campaign-option" ' . ($selectedPlanning ? '' : 'disabled') . '>';
    echo '<option value="">Elige primero una estrategia...</option>';
    foreach (publicista_campaign_strategy_option_codes() as $optionCode) {
        $optionMeta = is_array($selectedPlanningOptionMeta[$optionCode] ?? null) ? $selectedPlanningOptionMeta[$optionCode] : array();
        if (empty($optionMeta)) continue;
        $selectedAttr = ($selectedStrategyOptionCode === $optionCode) ? ' selected' : '';
        echo '<option value="' . e($optionCode) . '" data-label="' . e((string)($optionMeta['label'] ?? '')) . '" data-total="' . e(publicista_ads_euros((float)($optionMeta['grand_total'] ?? 0))) . '" data-profiles="' . e((string)($optionMeta['profiles_total'] ?? 0)) . '" data-warnings="' . e((string)($optionMeta['warnings_count'] ?? 0)) . '" data-help="' . e((string)($optionMeta['decision_help'] ?? '')) . '" data-note="' . e((string)($optionMeta['comparison_note'] ?? '')) . '"' . $selectedAttr . '>' . e((string)($optionMeta['label'] ?? ucfirst($optionCode))) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="field"><label>Resumen de la versión elegida</label><div id="publicistaCampaignOptionInfo" class="info-strip"><strong>' . e($selectedPlanning ? 'Selecciona la versión concreta antes de guardar.' : 'Primero elige una estrategia.') . '</strong><br><span class="muted">Se usará esta versión para calcular anuncios, costes y composición.</span></div></div>';
    echo '<div class="field"><label>Perfiles requeridos por la estrategia</label><div id="publicistaCampaignRequiredProducts" class="info-strip" data-required-products="' . e((string)$requiredProducts) . '"><strong>' . e((string)$requiredProducts) . '</strong><br>Debes seleccionar exactamente este número de perfiles.</div></div>';
    field_textarea('notes', 'Notas internas', $edit['notes'] ?? '', 3);

    $selectedProductIds = array_flip((array)($edit['product_ids'] ?? array()));
    echo '<div class="full">';
    echo '<label>Productos publicitarios elegibles</label>';
    if (empty($products)) {
        echo '<div class="empty">Todavía no hay productos disponibles en Crear perfiles.</div>';
    } else {
        render_live_filter('#publicistaCampaignProducts [data-filter-text]', 'Buscar perfil por nombre, clienta o estado...');
        echo '<div class="info-strip" style="margin-top:12px;"><strong>Selección cerrada</strong><br>La estrategia manda. Debes elegir exactamente <span id="publicistaCampaignRequiredInline">' . e((string)$requiredProducts) . '</span> perfiles listos.</div>';
        echo '<div id="publicistaCampaignProducts" class="linked-tags" style="margin-top:12px;">';
        foreach ($products as $product) {
            $ready = publicista_product_is_ready_for_campaign($product);
            $checked = isset($selectedProductIds[$product['id']]) ? ' checked' : '';
            $title = trim((string)($product['nombre_trabajo'] ?? $product['id']));
            $clienta = trim((string)($product['clienta_nombre_snapshot'] ?? ''));
            $meta = (string)$ready['final_images_count'] . ' imgs · ' . (string)$ready['copy_versions_count'] . ' copies';
            $search = strtolower(trim($title . ' ' . $clienta . ' ' . ($product['estado'] ?? '') . ' ' . $meta));
            echo '<label class="linked-tag" data-filter-text="' . e($search) . '" style="display:flex;align-items:center;gap:8px;">';
            echo '<input type="checkbox" class="js-publicista-campaign-product" name="product_ids[]" value="' . e($product['id']) . '"' . $checked . '>';
            echo '<span><strong>' . e($title) . '</strong>' . ($clienta !== '' ? ' · ' . e($clienta) : '') . ' · ' . e($ready['label']) . ' · ' . e($meta) . '</span>';
            echo '</label>';
        }
        echo '</div>';
    }
    echo '</div>';

    $selectedAccountIds = array_flip((array)($edit['account_ids'] ?? array()));
    $selectedListingRefs = array_flip((array)($edit['selected_listing_refs'] ?? array()));
    echo '<div class="full">';
    echo '<label>Cuentas de portal disponibles</label>';
    if (empty($accountsWithListingIds)) {
        echo '<div class="empty">No hay cuentas con IDs internos cargados en Publicista &gt; Cuentas.</div>';
    } else {
        echo '<div style="display:grid;gap:12px;margin-top:12px;">';
        foreach ($accountsWithListingIds as $account) {
            $checked = isset($selectedAccountIds[$account['id']]) ? ' checked' : '';
            $accountId = trim((string)($account['id'] ?? ''));
            $accountLabel = trim((string)($account['display_name'] ?: $account['login_user']));
            $accountUser = trim((string)($account['login_user'] ?? ''));
            $usage = is_array($account['_campaign_usage'] ?? null) ? $account['_campaign_usage'] : publicista_account_listing_usage($accountId, $account, $edit['id'] ?? '');
            echo '<div class="panel panel-space" style="margin:0;" data-account-box="' . e($accountId) . '">';
            echo '<label class="linked-tag" style="display:flex;align-items:center;gap:8px;">';
            echo '<input type="checkbox" class="js-publicista-campaign-account-toggle" name="account_ids[]" value="' . e($accountId) . '"' . $checked . ' data-account-id="' . e($accountId) . '">';
            echo '<span><strong>' . e($accountLabel) . '</strong> · usuario ' . e($accountUser) . ' · ' . e($account['portal_label'] ?? '') . ' · ' . e(publicista_account_status_label($account['estado'] ?? 'active')) . ' · ' . e((string)($account['_runtime_metrics']['listing_ids_total'] ?? 0)) . ' IDs internos</span>';
            echo '</label>';
            echo '<div class="publicista-campaign-listings" data-account-picker="' . e($accountId) . '" style="display:grid;gap:8px;margin-top:10px;">';
            echo '<div class="muted">Selecciona exactamente qué anuncios internos de esta cuenta puede usar la campaña.</div>';
            echo '<div class="linked-tags">';
            foreach ((array)$usage['listing_ids'] as $listingId) {
                $listingRef = publicista_campaign_listing_ref($accountId, $listingId);
                $listingChecked = isset($selectedListingRefs[$listingRef]) ? ' checked' : '';
                echo '<label class="linked-tag" style="display:flex;align-items:center;gap:8px;">';
                echo '<input type="checkbox" class="js-publicista-campaign-listing" name="selected_listing_refs[]" value="' . e($listingRef) . '"' . $listingChecked . ' data-account-id="' . e($accountId) . '">';
                echo '<span><strong>ID ' . e($listingId) . '</strong> · ' . e($accountUser) . '</span>';
                echo '</label>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div>';

    echo '<div class="full" style="display:flex;gap:10px;flex-wrap:wrap;">';
    echo '<button class="btn-primary" name="action" value="save_publicista_campaign">Guardar y continuar</button>';
    if ($edit) {
        echo '<a class="btn-secondary-mini" href="' . e(publicista_page_url('campanas')) . '">Nueva</a>';
    }
    echo '</div>';
    echo '</form>';
    echo '</section>';

    if ($edit) {
        $items = array_values((array)$editItems);
        $planning = $planningsById[trim((string)($edit['planning_id'] ?? ''))] ?? publicista_planning_get($edit['planning_id'] ?? '');
        $summary = is_array($edit['execution_summary'] ?? null) ? $edit['execution_summary'] : array();
        list($canDelete, $deleteErrors, $deleteMeta) = publicista_campaign_can_delete($edit['id']);
        echo '<hr class="sep">';
        echo '<h2>Estado de la campaña</h2>';
        echo '<div class="cards four" style="margin-bottom:12px;">';
        echo '<div class="info-strip"><strong>Estado</strong><br>' . e(publicista_campaign_status_label($edit['estado'] ?? 'draft')) . '</div>';
        echo '<div class="info-strip"><strong>Estrategia</strong><br>' . e($planning['nombre'] ?? 'Sin planning') . '</div>';
        echo '<div class="info-strip"><strong>Versión elegida</strong><br>' . e((string)($edit['strategy_option_label'] ?: ($summary['strategy_option_label'] ?? 'Sin definir'))) . '</div>';
        echo '<div class="info-strip"><strong>Coste estimado</strong><br>' . e(publicista_ads_euros((float)($summary['estimated_cost'] ?? 0))) . '</div>';
        echo '</div>';
        if (!empty($edit['strategy_option_snapshot']) && is_array($edit['strategy_option_snapshot'])) {
            $strategyOptionSnapshot = (array)$edit['strategy_option_snapshot'];
            $snapshotProfiles = 0;
            foreach ((array)($strategyOptionSnapshot['strategies'] ?? array()) as $snapshotStrategy) {
                $snapshotProfiles += count((array)($snapshotStrategy['profiles'] ?? array()));
            }
            echo '<div class="info-strip" style="margin-bottom:12px;"><strong>Detalle de la versión seleccionada</strong><br>'
                . e((string)($strategyOptionSnapshot['label'] ?? ($edit['strategy_option_label'] ?? 'Versión'))) 
                . ' · ' . e(publicista_ads_euros((float)($strategyOptionSnapshot['grand_total'] ?? 0)))
                . ' · ' . e((string)$snapshotProfiles) . ' perfiles'
                . (!empty($strategyOptionSnapshot['decision_help']) ? ' · ' . e((string)$strategyOptionSnapshot['decision_help']) : '')
                . '</div>';
        }
        if (($edit['estado'] ?? '') === 'generating') {
            $queuedAt = trim((string)($summary['last_generation_requested_at'] ?? ''));
            echo '<div class="info-strip" style="margin-bottom:12px;"><strong>Generando composición</strong><br>La campaña ya está guardada y la composición se está recalculando en segundo plano.' . ($queuedAt !== '' ? ' Solicitud: ' . e(format_created_at($queuedAt)) . '.' : '') . ' Recarga esta página en unos segundos.</div>';
        }
        $lastGenerationError = trim((string)($summary['last_generation_error'] ?? ''));
        if ($lastGenerationError !== '') {
            echo '<div class="publicista-ads-warning"><strong>Error de generación:</strong><br>' . e($lastGenerationError) . '</div>';
        }
        if (!empty($summary['warnings'])) {
            echo '<div class="publicista-ads-warning"><strong>Avisos de generación:</strong><ul class="publicista-ads-warn-list">';
            foreach ((array)$summary['warnings'] as $warning) echo '<li>' . e($warning) . '</li>';
            echo '</ul></div>';
        }
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">';
        if (!$canDelete) {
            echo '<div class="publicista-ads-warning">No se puede eliminar: ' . e(implode(' ', $deleteErrors)) . '</div>';
        } else {
            echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar esta campaña y sus items generados?\')"><input type="hidden" name="action" value="delete_publicista_campaign"><input type="hidden" name="id" value="' . e($edit['id']) . '"><button class="btn-danger-mini">Eliminar campaña</button></form>';
        }
        echo '</div>';

        $automationPlan = is_array($edit['automation_plan'] ?? null) ? $edit['automation_plan'] : array();
        $approvalSnapshot = is_array($edit['approval_snapshot'] ?? null) ? $edit['approval_snapshot'] : array();
        echo '<h2 style="margin-top:18px;">Planning operativo</h2>';
        if (empty($automationPlan)) {
            echo '<div class="empty">Todavía no existe planning operativo. Guarda la campaña para generarlo automáticamente.</div>';
        } else {
            echo '<div class="cards four" style="margin-bottom:12px;">';
            echo '<div class="info-strip"><strong>Items planificados</strong><br>' . e((string)($automationPlan['items_total'] ?? 0)) . '</div>';
            echo '<div class="info-strip"><strong>Coste planificado</strong><br>' . e(publicista_ads_euros((float)($automationPlan['estimated_cost_total'] ?? 0))) . '</div>';
            $approvalLabel = !empty($approvalSnapshot['approved_at']) ? format_created_at($approvalSnapshot['approved_at']) : 'Pendiente';
            if (($approvalSnapshot['approval_mode'] ?? '') === 'auto_after_generation') {
                $approvalLabel .= ' · automática';
            } elseif (($approvalSnapshot['approval_mode'] ?? '') === 'auto_before_execution') {
                $approvalLabel .= ' · automática al ejecutar';
            }
            echo '<div class="info-strip"><strong>Aprobada</strong><br>' . e($approvalLabel) . '</div>';
            echo '<div class="info-strip"><strong>Subida humanizada</strong><br>sí · pausas entre anuncios e imágenes</div>';
            echo '</div>';
            /*
            if (!empty($automationPlan['steps']) && is_array($automationPlan['steps'])) {
                echo '<div class="publicista-ads-warning"><strong>Flujo:</strong><ul class="publicista-ads-warn-list">';
                foreach ($automationPlan['steps'] as $stepTxt) echo '<li>' . e($stepTxt) . '</li>';
                echo '</ul></div>';
            }
            */
            if (!empty($automationPlan['accounts']) && is_array($automationPlan['accounts'])) {
                echo '<div class="table-wrap" style="margin-top:12px;"><table><thead><tr><th>Cuenta</th><th>Portal</th><th>Items</th><th>Planning</th></tr></thead><tbody>';
                foreach ($automationPlan['accounts'] as $planAccount) {
                    echo '<tr>';
                    echo '<td><strong>' . e($planAccount['account_name'] ?? ($planAccount['account_id'] ?? '')) . '</strong></td>';
                    echo '<td>' . e($planAccount['portal_code'] ?? '') . '</td>';
                    echo '<td>' . e((string)($planAccount['items_count'] ?? count((array)($planAccount['items'] ?? array())))) . '</td>';
                    if (!empty($planAccount['items']) && is_array($planAccount['items'])) {
                        $rowsTxt = array();
                        foreach ((array)$planAccount['items'] as $planItem) {
                            $rowsTxt[] = ($planItem['external_ad_id'] !== '' ? ('ID ' . $planItem['external_ad_id']) : 'Sin ID') . ' · ' . ($planItem['publish_mode_label'] ?? '') . ' · espera ' . (string)($planItem['delay_before_this_sec'] ?? 0) . 's';
                        }
                        echo '<td>' . e(implode(' | ', $rowsTxt)) . '</td>';
                    } else {
                        echo '<td><span class="muted">Resumen compacto guardado. El detalle está en la composición generada.</span></td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }
        }

        echo '<h2 style="margin-top:18px;">Vista previa operativa</h2>';
        if (empty($items)) {
            echo '<div class="empty">No hay vista previa porque todavía no existen items generados.</div>';
        } else {
            $preview = publicista_campaign_preview_summary($edit, $items);
            $previewTotals = is_array($preview['totals'] ?? null) ? $preview['totals'] : array();
            $previewByAccount = is_array($preview['by_account'] ?? null) ? $preview['by_account'] : array();
            $previewByProduct = is_array($preview['by_product'] ?? null) ? $preview['by_product'] : array();
            $previewWarnings = is_array($preview['warnings'] ?? null) ? $preview['warnings'] : array();

            $matrixAccounts = array();
            $campaignAccountIds = array_values(array_filter(array_map('trim', (array)($edit['account_ids'] ?? array()))));
            foreach ($campaignAccountIds as $aid) {
                if ($aid === '') continue;
                $accountEntity = is_array($accountsById[$aid] ?? null) ? $accountsById[$aid] : array();
                $accountName = trim((string)($accountEntity['display_name'] ?? ($accountEntity['login_user'] ?? $aid)));
                if ($accountName === '') $accountName = $aid;
                $matrixAccounts[$aid] = array(
                    'account_id' => $aid,
                    'account_name' => $accountName,
                );
            }

            $matrixAccountCapacity = array();
            $selectedSlotsMeta = publicista_campaign_selected_listing_slots($edit, array_values($accountsById), trim((string)($edit['id'] ?? '')), false);
            foreach ((array)($selectedSlotsMeta['slots'] ?? array()) as $slotRow) {
                $slotAid = trim((string)($slotRow['account']['id'] ?? ''));
                if ($slotAid === '') continue;
                if (!isset($matrixAccountCapacity[$slotAid])) $matrixAccountCapacity[$slotAid] = 0;
                $matrixAccountCapacity[$slotAid]++;
            }
            if (empty($matrixAccounts)) {
                foreach ($previewByAccount as $accountRow) {
                    $aid = trim((string)($accountRow['account_id'] ?? ''));
                    if ($aid === '') continue;
                    $matrixAccounts[$aid] = array(
                        'account_id' => $aid,
                        'account_name' => trim((string)($accountRow['account_name'] ?? $aid)),
                    );
                }
            }

            $matrixProducts = array();
            foreach ($previewByProduct as $productRow) {
                $pid = trim((string)($productRow['product_id'] ?? ''));
                if ($pid === '') continue;
                $matrixProducts[$pid] = array(
                    'product_id' => $pid,
                    'product_name' => trim((string)($productRow['product_name'] ?? $pid)),
                );
            }

            $currentMatrix = array();
            foreach ($items as $itemRow) {
                $pid = trim((string)($itemRow['product_job_id'] ?? ''));
                $aid = trim((string)($itemRow['account_id'] ?? ''));
                if ($pid === '' || $aid === '') continue;
                if (!isset($currentMatrix[$pid])) $currentMatrix[$pid] = array();
                if (!isset($currentMatrix[$pid][$aid])) $currentMatrix[$pid][$aid] = 0;
                $currentMatrix[$pid][$aid]++;
            }

            echo '<div class="cards four" style="margin-bottom:12px;">';
            echo '<div class="info-strip"><strong>Items</strong><br>' . e((string)($previewTotals['items_count'] ?? 0)) . '</div>';
            echo '<div class="info-strip"><strong>Coste total estimado</strong><br>' . e(publicista_ads_euros((float)($previewTotals['estimated_cost_total'] ?? 0))) . '</div>';
            echo '<div class="info-strip"><strong>Cuentas implicadas</strong><br>' . e((string)($previewTotals['accounts_count'] ?? 0)) . '</div>';
            echo '<div class="info-strip"><strong>Productos implicados</strong><br>' . e((string)($previewTotals['products_count'] ?? 0)) . '</div>';
            echo '</div>';

            if (!empty($previewWarnings)) {
                echo '<div class="publicista-ads-warning"><strong>Avisos de preview:</strong><ul class="publicista-ads-warn-list">';
                foreach ($previewWarnings as $warningTxt) {
                    echo '<li>' . e($warningTxt) . '</li>';
                }
                echo '</ul></div>';
            }

            if (!empty($previewByAccount)) {
                echo '<div class="table-wrap" style="margin-top:12px;"><table><thead><tr><th>Cuenta</th><th>Anuncios</th><th>Coste subtotal</th></tr></thead><tbody>';
                foreach ($previewByAccount as $rowAccount) {
                    echo '<tr>';
                    echo '<td><strong>' . e((string)($rowAccount['account_name'] ?? ($rowAccount['account_id'] ?? ''))) . '</strong></td>';
                    echo '<td>' . e((string)($rowAccount['items_count'] ?? 0)) . '</td>';
                    echo '<td>' . e(publicista_ads_euros((float)($rowAccount['estimated_cost_total'] ?? 0))) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }

            if (!empty($previewByProduct)) {
                echo '<div class="table-wrap" style="margin-top:12px;"><table><thead><tr><th>Producto</th><th>Anuncios</th><th>Coste subtotal</th></tr></thead><tbody>';
                foreach ($previewByProduct as $rowProduct) {
                    echo '<tr>';
                    echo '<td><strong>' . e((string)($rowProduct['product_name'] ?? ($rowProduct['product_id'] ?? ''))) . '</strong></td>';
                    echo '<td>' . e((string)($rowProduct['items_count'] ?? 0)) . '</td>';
                    echo '<td>' . e(publicista_ads_euros((float)($rowProduct['estimated_cost_total'] ?? 0))) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }

            $hasDistributionMismatch = false;

            if (!empty($matrixProducts) && !empty($matrixAccounts)) {
                echo '<form method="post" class="panel panel-space" style="margin-top:12px;background:#0b1422;">';
                echo '<input type="hidden" name="action" value="rebalance_publicista_campaign_distribution">';
                echo '<input type="hidden" name="campaign_id" value="' . e($edit['id']) . '">';
                echo '<h3 style="margin:0 0 10px;">Ajustar reparto anuncios por producto y cuenta</h3>';

                // Detect mismatch between current distribution and stored matrix
                $storedDistribution = is_array($edit['distribution_matrix'] ?? null) ? $edit['distribution_matrix'] : array();
                if (!empty($storedDistribution)) {
                    foreach ($matrixProducts as $productMeta) {
                        $pid = $productMeta['product_id'];
                        foreach ($matrixAccounts as $accountMeta) {
                            $aid = $accountMeta['account_id'];
                            $currentCount = (int)($currentMatrix[$pid][$aid] ?? 0);
                            $storedCount = (int)($storedDistribution[$pid][$aid] ?? 0);
                            if ($currentCount !== $storedCount) {
                                $hasDistributionMismatch = true;
                                break 2;
            }

            $activeUnreadCount = 0;
            foreach ($active as $a) {
                if (empty($a['read_at'])) $activeUnreadCount++;
            }
            if ($activeUnreadCount > 0) {
                echo '<div class="aviso-actions" style="margin-top:10px;">';
                echo '<form method="post" class="inline-form js-mark-all-read">';
                echo '<input type="hidden" name="action" value="mark_avisos_read">';
                echo '<input type="hidden" name="scope" value="active_unread">';
                echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
                echo '<input type="hidden" name="redirect" value="' . e($baseUrl . '&avtab=active') . '">';
                echo '<button class="btn-secondary-mini">Marcar ' . e((string)$activeUnreadCount) . ' nuevos como leídos</button>';
                echo '</form>';
                echo '</div>';
            }
        }
                    }
                }
                if ($hasDistributionMismatch) {
                    echo '<div class="publicista-ads-warning" style="margin-bottom:8px;"><strong>⚠️ El reparto actual de los anuncios no coincide con la matriz guardada.</strong> Pulsa <em>Aplicar reparto</em> para actualizar los anuncios antes de subir.</div>';
                }

                echo '<p class="muted" style="margin-top:0;">Define cuántos anuncios tendrá cada producto en cada cuenta y aplica. El total por producto debe mantenerse.</p>';
                echo '<div class="table-wrap"><table><thead><tr><th>Producto</th>';
                foreach ($matrixAccounts as $accountMeta) {
                    $aid = trim((string)($accountMeta['account_id'] ?? ''));
                    $capacityTxt = (string)(int)($matrixAccountCapacity[$aid] ?? 0);
                    echo '<th>' . e((string)$accountMeta['account_name']) . '<br><span class="muted" style="font-size:11px;">Capacidad: ' . e($capacityTxt) . '</span></th>';
                }
                echo '<th>Total producto</th></tr></thead><tbody>';

                foreach ($matrixProducts as $productMeta) {
                    $pid = $productMeta['product_id'];
                    $rowTotal = 0;
                    echo '<tr>';
                    echo '<td><strong>' . e((string)$productMeta['product_name']) . '</strong></td>';
                    foreach ($matrixAccounts as $accountMeta) {
                        $aid = $accountMeta['account_id'];
                        $count = (int)($currentMatrix[$pid][$aid] ?? 0);
                        $rowTotal += $count;
                        echo '<td><input type="number" min="0" step="1" name="distribution_matrix[' . e($pid) . '][' . e($aid) . ']" value="' . e((string)$count) . '" style="max-width:90px;"></td>';
                    }
                    echo '<td><strong>' . e((string)$rowTotal) . '</strong></td>';
                    echo '</tr>';
                }

                echo '</tbody></table></div>';
                echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;">';
                $applyText = $hasDistributionMismatch ? '⚠️ Aplicar reparto (pendiente)' : 'Aplicar reparto';
                $applyStyle = $hasDistributionMismatch ? 'background:#e67e22;color:#fff;font-weight:bold;' : '';
                echo '<button class="btn-secondary-mini" style="' . e($applyStyle) . '" onclick="return confirm(\'¿Aplicar este nuevo reparto de anuncios por producto y cuenta?\')">' . e($applyText) . '</button>';
                echo '</div>';
                echo '</form>';
            }

            echo '<div class="info-strip" style="margin-top:12px;">';
            if ($hasDistributionMismatch) {
                echo '<strong>⚠️ El reparto no coincide con la matriz guardada. Aplica el reparto antes de subir.</strong> || ';
            }
            echo '<strong>Siguiente paso:</strong> revisa/ajusta listing y teléfono por item en la tabla inferior. Cuando esté todo correcto, pulsa <strong>Subir anuncios</strong>.</div>';
        }

        echo '<h2 style="margin-top:18px;">Composición generada</h2>';
        if (empty($items)) {
            if (($edit['estado'] ?? '') === 'generating') {
                echo '<div class="empty">La composición todavía se está generando en segundo plano. Recarga esta página en unos segundos.</div>';
            } else {
                echo '<div class="empty">Esta campaña todavía no tiene items generados. Guarda la campaña para generarlos automáticamente.</div>';
            }
        } else {
            $autoRotation = publicista_campaign_auto_rotation_schedule_normalize((array)($edit['auto_rotation_schedule'] ?? array()));
            $executionSummary = is_array($edit['execution_summary'] ?? null) ? $edit['execution_summary'] : array();
            $autoRotationStatus = !empty($autoRotation['enabled']) ? 'active' : 'inactive';
            $autoRotationNextRunAt = trim((string)($executionSummary['auto_rotation_next_run_at'] ?? ''));
            $autoRotationLastRunAt = trim((string)($executionSummary['auto_rotation_last_run_at'] ?? ''));
            $autoRotationLastStatus = trim((string)($executionSummary['auto_rotation_last_status'] ?? ''));
            $autoRotationLastError = trim((string)($executionSummary['auto_rotation_last_error'] ?? ''));

            echo '<section class="panel panel-space" style="margin-bottom:12px;">';
            echo '<div class="branch-panel-head"><h2>Auto-rotación de campaña</h2><span class="summary-badge">Programación</span></div>';
            echo '<p style="margin:6px 0 0 0;padding:6px 10px;background:#fff3cd;border-radius:4px;font-size:0.85em;">ℹ️ La auto-rotación solo aplica a anuncios de <strong>Destacamos</strong>. Los anuncios de <strong>MundosexAnuncio</strong> se suben una sola vez y no rotan.</p>';
            echo '<div class="cards four" style="margin-top:12px;">';
            echo '<div class="info-strip"><strong>Estado</strong><br>' . e($autoRotationStatus === 'active' ? 'Activa' : 'Inactiva') . '</div>';
            echo '<div class="info-strip"><strong>Próxima ejecución</strong><br>' . e($autoRotationNextRunAt !== '' ? format_created_at($autoRotationNextRunAt) : 'Sin programar') . '</div>';
            echo '<div class="info-strip"><strong>Última ejecución</strong><br>' . e($autoRotationLastRunAt !== '' ? format_created_at($autoRotationLastRunAt) : 'Aún sin ejecuciones') . '</div>';
            echo '<div class="info-strip"><strong>Último estado</strong><br>' . e($autoRotationLastError !== '' ? $autoRotationLastError : ($autoRotationLastStatus !== '' ? $autoRotationLastStatus : 'Sin eventos')) . '</div>';
            echo '</div>';
            echo '<form method="post" class="form-grid" style="margin-top:12px;">';
            echo '<input type="hidden" name="action" value="save_publicista_campaign_auto_rotation">';
            echo '<input type="hidden" name="campaign_id" value="' . e($edit['id']) . '">';
            echo '<div class="field">';
            echo '<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="auto_rotation_enabled" value="1"' . (!empty($autoRotation['enabled']) ? ' checked' : '') . '> Activar auto-rotación</label>';
            echo '<div class="field-help">Si está activa, la campaña podrá ejecutarse automáticamente dentro de la franja diaria.</div>';
            echo '</div>';
            echo '<div class="field"><label>Hora inicio diaria</label><input type="time" name="auto_rotation_daily_start_time" value="' . e((string)$autoRotation['daily_start_time']) . '"></div>';
            echo '<div class="field"><label>Hora fin diaria</label><input type="time" name="auto_rotation_daily_end_time" value="' . e((string)$autoRotation['daily_end_time']) . '"></div>';
            echo '<div class="field"><label>Frecuencia (cada X horas)</label><input type="number" min="1" step="1" name="auto_rotation_every_hours" value="' . e((string)$autoRotation['every_hours']) . '"></div>';
            echo '<div class="full" style="display:flex;gap:10px;flex-wrap:wrap;"><button class="btn-secondary-mini">Guardar horario</button></div>';
            echo '</form>';
            echo '<form method="post" class="inline-form" style="margin-top:10px;">';
            echo '<input type="hidden" name="action" value="force_publicista_campaign_auto_rotation_now">';
            echo '<input type="hidden" name="campaign_id" value="' . e($edit['id']) . '">';
            echo '<button class="btn-primary" onclick="return confirm(\'¿Forzar ahora una resubida inmediata de esta campaña?\')">Forzar resubida ahora</button>';
            echo '</form>';
            echo '</section>';

            // Campaign run history (collapsible)
            echo '<section class="panel panel-space" id="campaignRunHistory">';
            echo '<div class="section-head">';
            echo '<div><h2>Historial de subidas</h2><p>Últimas ejecuciones de esta campaña (auto-rotación y forzadas).</p></div>';
            echo '<div class="section-head-actions">';
            echo '<button class="btn-secondary-mini" type="button" id="btnToggleRunHistory" onclick="toggleCampaignRunHistory()">Mostrar historial de subidas</button>';
            echo '</div>';
            echo '</div>';
            echo '<div id="campaignRunHistoryTable" style="display:none;">';
            if (empty($editRuns)) {
                echo '<div class="empty" style="margin-top:8px;">Todavía no hay ejecuciones registradas para esta campaña.</div>';
            } else {
                echo '<div class="table-wrap"><table><thead><tr>';
                echo '<th>Fecha</th><th>Tipo</th><th>Estado</th><th>Publicados</th><th>Fallidos</th><th>Resumen</th><th>Acción</th>';
                echo '</tr></thead><tbody>';
                $runStatusColors = array('pending' => '#f59e0b', 'running' => '#3b82f6', 'completed' => '#22c55e', 'failed' => '#ef4444', 'cancelled' => '#6b7280');
                foreach ($editRuns as $run) {
                    $runId = trim((string)($run['id'] ?? ''));
                    $runType = trim((string)($run['run_type'] ?? ''));
                    $runEstado = trim((string)($run['estado'] ?? 'pending'));
                    $runCreatedAt = format_created_at($run['created_at'] ?? '');
                    $published = (int)($run['progress']['published'] ?? 0);
                    $failed = (int)($run['progress']['failed'] ?? 0);
                    $runSummary = trim((string)($run['summary'] ?? ''));
                    $pipelineSummary = trim((string)($run['pipeline']['summary'] ?? ''));
                    $humanReport = trim((string)($run['human_report'] ?? ''));
                    $runTypeLabel = $runType === 'auto_rotation' ? 'Auto-rotación' : ($runType === 'manual_force' ? 'Forzada' : ($runType !== '' ? e($runType) : 'Subida'));

                    echo '<tr>';
                    echo '<td>' . e($runCreatedAt) . '</td>';
                    echo '<td><span class="summary-badge">' . e($runTypeLabel) . '</span></td>';
                    echo '<td><span class="summary-badge" style="background:' . e($runStatusColors[$runEstado] ?? '#6b7280') . ';color:#fff;">' . e(publicista_run_status_label($runEstado)) . '</span></td>';
                    echo '<td>' . e((string)$published) . '</td>';
                    echo '<td>' . e((string)$failed) . '</td>';
                    echo '<td style="max-width:240px;white-space:normal;">';
                    if ($runSummary !== '') echo e($runSummary);
                    elseif ($pipelineSummary !== '') echo e($pipelineSummary);
                    else echo '<span class="muted">-</span>';
                    if ($humanReport !== '') echo '<br><span class="muted" style="font-size:11px;">' . e('Informe: ' . $humanReport) . '</span>';
                    echo '</td>';
                    echo '<td><button class="btn-secondary-mini" type="button" onclick="toggleRunLogDetail(\'' . e($runId) . '\')">Ver log</button></td>';
                    echo '</tr>';

                    // Hidden detail row
                    echo '<tr class="run-log-detail" id="runLogDetail_' . e($runId) . '" style="display:none;">';
                    echo '<td colspan="7" style="background:var(--panel);border-top:1px solid var(--line);padding:12px;">';
                    echo '<div style="font-size:12px;color:var(--muted);">';
                    echo '<strong>Run ID:</strong> ' . e($runId) . '<br>';
                    echo '<strong>Estado pipeline:</strong> ' . e(trim((string)($run['pipeline']['status'] ?? ''))) . ' → ' . e(trim((string)($run['pipeline']['stage'] ?? ''))) . '<br>';
                    if ($pipelineSummary !== '') echo '<strong>Resumen pipeline:</strong> ' . e($pipelineSummary) . '<br>';
                    if ($humanReport !== '') echo '<strong>Informe humano:</strong> ' . e($humanReport) . '<br>';
                    if ($runSummary !== '') echo '<strong>Resumen:</strong> ' . e($runSummary) . '<br>';
                    echo '<strong>Inicio:</strong> ' . e(format_created_at($run['started_at'] ?? '')) . '<br>';
                    echo '<strong>Fin:</strong> ' . e(format_created_at($run['finished_at'] ?? '')) . '<br>';
                    echo '<strong>Total items:</strong> ' . e((string)((int)($run['progress']['total_items'] ?? 0))) . ' | ';
                    echo '<strong>Procesados:</strong> ' . e((string)((int)($run['progress']['processed_items'] ?? 0))) . '<br>';
                    if (!empty($run['stop_requested_at'])) echo '<strong>Detención solicitada:</strong> ' . e(format_created_at($run['stop_requested_at'])) . '<br>';
                    echo '</div>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }
            echo '</div>';
            echo '</section>';

            $phones = storage_read('telefonos.json');
            $runningRun = publicista_campaign_running_run($edit['id']);
            $campaignStatus = trim((string)($edit['estado'] ?? ''));
            $lastRunStatus = trim((string)($executionSummary['last_run_status'] ?? ''));
            $lastRunId = trim((string)($executionSummary['last_run_id'] ?? ''));
            $campaignLooksRunning = in_array($campaignStatus, array('uploading', 'running'), true)
                || in_array($lastRunStatus, array('pending', 'running'), true);

            if (!$runningRun && $lastRunId !== '') {
                $fallbackRun = publicista_run_get($lastRunId);
                if ($fallbackRun && in_array(($fallbackRun['estado'] ?? ''), array('pending', 'running'), true)) {
                    $runningRun = $fallbackRun;
                }
            }

            if (!$runningRun && !empty($editRuns) && is_array($editRuns)) {
                foreach ($editRuns as $candidateRun) {
                    $candidateStatus = trim((string)($candidateRun['estado'] ?? ''));
                    if (in_array($candidateStatus, array('pending', 'running'), true)) {
                        $runningRun = $candidateRun;
                        break;
                    }
                }
            }

            if (!$runningRun && $campaignLooksRunning) {
                $runningRun = array(
                    'estado' => ($lastRunStatus !== '' ? $lastRunStatus : 'running'),
                    'summary' => trim((string)($executionSummary['last_phase'] ?? 'Subida en curso')),
                    'progress' => array(
                        'processed_items' => (int)($executionSummary['last_run_processed'] ?? 0),
                        'total_items' => (int)($executionSummary['last_run_total'] ?? 0),
                    ),
                    'stop_requested_at' => '',
                );
            }
            $phonesByAccount = array();
            foreach ($phones as $phoneRow) {
                $aid = trim((string)($phoneRow['destacamos_id'] ?? ''));
                if ($aid === '') continue;
                if (!isset($phonesByAccount[$aid])) $phonesByAccount[$aid] = array();
                $phonesByAccount[$aid][] = $phoneRow;
            }

            echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">';
            if ($runningRun) {
                $runProgress = is_array($runningRun['progress'] ?? null) ? $runningRun['progress'] : array();
                $processed = (int)($runProgress['processed_items'] ?? 0);
                $total = (int)($runProgress['total_items'] ?? 0);
                $runSummary = trim((string)($runningRun['summary'] ?? ''));
                echo '<div class="info-strip"><strong>Subida en curso</strong><br>' . e(publicista_run_status_label($runningRun['estado'] ?? 'running')) . ' · ' . e((string)$processed) . '/' . e((string)$total) . ' procesados' . ($runSummary !== '' ? ' · ' . e($runSummary) : '') . '</div>';
                $stopRequestedAt = trim((string)($runningRun['stop_requested_at'] ?? ''));
                if ($stopRequestedAt === '') {
                    echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Solicitar parada de la subida en curso? Se cerrará de forma limpia al terminar el paso actual.\')">';
                    echo '<input type="hidden" name="action" value="stop_publicista_campaign_run">';
                    echo '<input type="hidden" name="id" value="' . e($edit['id']) . '">';
                    echo '<button class="btn-danger-mini">Detener subida</button>';
                    echo '</form>';
                } else {
                    echo '<div class="info-strip" style="background:#fff7ed;color:#9a3412;"><strong>Parada solicitada</strong><br>Solicitud enviada ' . e(format_created_at($stopRequestedAt)) . '. El motor cerrará al completar el paso actual.</div>';
                }
            } else {
                echo '<form method="post" class="inline-form"><input type="hidden" name="action" value="execute_publicista_campaign"><input type="hidden" name="id" value="' . e($edit['id']) . '"><button class="btn-primary" onclick="return confirm(\'¿Lanzar la subida de anuncios en segundo plano con la configuración actual de la campaña?\')">Subir anuncios</button></form>';
            }
            echo '</div>';

            echo '<div class="table-wrap"><table><thead><tr><th>#</th><th>Producto</th><th>Cuenta</th><th>Modo</th><th>Copy</th><th>Listing / teléfono</th><th>Estado</th><th>Último resultado</th><th>Acciones</th></tr></thead><tbody>';
            foreach ($items as $idx => $item) {
                $productSnapshot = is_array($item['product_snapshot']['data'] ?? null) ? $item['product_snapshot']['data'] : array();
                $accountSnapshot = is_array($item['account_snapshot']['data'] ?? null) ? $item['account_snapshot']['data'] : array();
                $copySnap = is_array($item['copy_snapshot'] ?? null) ? $item['copy_snapshot'] : array();
                $copyTitle = trim((string)($copySnap['title_neutral'] ?? ''));
                if ($copyTitle === '') $copyTitle = trim((string)($copySnap['title_suggestive'] ?? ''));
                $result = is_array($item['publish_result'] ?? null) ? $item['publish_result'] : array();
                $linkedPhones = $phonesByAccount[$item['account_id']] ?? array();
                $currentPhoneId = trim((string)($item['phone_id'] ?? ''));
                if ($currentPhoneId === '' && !empty($linkedPhones)) $currentPhoneId = trim((string)($linkedPhones[0]['id'] ?? ''));

                echo '<tr>';
                echo '<td><span class="summary-badge">' . e((string)($idx + 1)) . '</span></td>';
                echo '<td><strong>' . e($productSnapshot['nombre_trabajo'] ?? ($item['product_job_id'] ?? '')) . '</strong><br><span class="muted">' . e((string)count((array)($item['image_snapshot'] ?? array()))) . ' imágenes</span></td>';
                echo '<td>' . e($accountSnapshot['display_name'] ?? ($accountSnapshot['login_user'] ?? ($item['account_id'] ?? ''))) . '<br><span class="muted">' . e($accountSnapshot['portal_label'] ?? ($item['portal_code'] ?? '')) . '</span></td>';
                $itemCost = (float)publicista_campaign_profile_cost($item['planning_profile_snapshot'] ?? array());
                echo '<td><strong>' . e(publicista_campaign_publish_mode_label((string)($item['publish_mode'] ?? 'standard'))) . '</strong><br><span class="muted">' . e(publicista_ads_euros($itemCost)) . '</span></td>';
                echo '<td>' . e($copyTitle !== '' ? $copyTitle : 'Sin título detectado') . '</td>';
                echo '<td>';
                echo '<form method="post" class="inline-form" style="display:grid;gap:6px;min-width:230px;">';
                echo '<input type="hidden" name="action" value="save_publicista_campaign_item_meta">';
                echo '<input type="hidden" name="id" value="' . e($item['id']) . '">';
                echo '<input type="text" name="external_ad_id" value="' . e($item['external_ad_id'] ?? '') . '" placeholder="Listing ID existente">';
                echo '<select name="phone_id">';
                echo '<option value="">Teléfono automático de la cuenta</option>';
                foreach ($linkedPhones as $phoneRow) {
                    $selPhone = ($currentPhoneId === ($phoneRow['id'] ?? '')) ? ' selected' : '';
                    echo '<option value="' . e($phoneRow['id'] ?? '') . '"' . $selPhone . '>' . e(($phoneRow['nombre'] ?? 'Teléfono') . ' · ' . ($phoneRow['tfono'] ?? '')) . '</option>';
                }
                echo '</select>';
                echo '<button class="btn-secondary-mini">Guardar meta</button>';
                echo '</form>';
                echo '</td>';
                echo '<td><span class="summary-badge">' . e(publicista_campaign_item_status_label($item['estado'] ?? 'draft')) . '</span></td>';
                echo '<td>';
                if (!empty($result)) {
                    echo '<strong>' . e(!empty($result['ok']) ? 'OK' : 'ERROR') . '</strong><br><span class="muted">' . e(trim((string)($result['error'] ?? ($result['renewNote'] ?? '')))) . '</span>';
                    $debugLog = is_array($result['debug_log'] ?? null) ? $result['debug_log'] : array();
                    $debugFiles = array();
                    foreach ((array)($debugLog['steps'] ?? array()) as $debugStep) {
                        $savedHtmlPath = trim((string)($debugStep['saved_html_path'] ?? ''));
                        if ($savedHtmlPath !== '') $debugFiles[] = $savedHtmlPath;
                    }
                    $debugDump = array(
                        'ok' => !empty($result['ok']),
                        'error' => trim((string)($result['error'] ?? '')),
                        'adapter' => trim((string)($result['adapter'] ?? '')),
                        'payload_summary' => is_array($result['payload_summary'] ?? null) ? $result['payload_summary'] : array(),
                        'debug_files' => array_values(array_unique($debugFiles)),
                        'debug_log' => $debugLog,
                        'humanTrace' => is_array($result['humanTrace'] ?? null) ? $result['humanTrace'] : array(),
                    );
                    $debugJson = json_encode($debugDump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($debugJson === false) $debugJson = 'No se pudo codificar el debug.';
                    echo '<details style="margin-top:6px;"><summary>Ver debug detallado</summary><pre style="white-space:pre-wrap;word-break:break-word;max-width:780px;margin-top:8px;">' . e($debugJson) . '</pre></details>';
                } else echo '<span class="muted">Sin ejecutar</span>';
                echo '</td>';
                echo '<td><span class="muted">Aquí puedes corregir listing ID y teléfono antes de subir. El botón "Subir anuncios" siempre vuelve a ejecutar la campaña con esta configuración actual.</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';

            $runs = array_values((array)$editRuns);
            echo '<h2 style="margin-top:18px;">Histórico de ejecuciones</h2>';
            if (empty($runs)) {
                echo '<div class="empty">Todavía no hay runs registrados para esta campaña.</div>';
            } else {
                echo '<div class="table-wrap"><table><thead><tr><th>Run</th><th>Tipo</th><th>Estado</th><th>Inicio</th><th>Fin</th><th>Resumen</th></tr></thead><tbody>';
                foreach ($runs as $run) {
                    echo '<tr>';
                    echo '<td><strong>' . e($run['id'] ?? '') . '</strong></td>';
                    echo '<td>' . e($run['run_type'] ?? '') . '</td>';
                    echo '<td><span class="summary-badge">' . e(publicista_run_status_label($run['estado'] ?? 'pending')) . '</span></td>';
                    echo '<td>' . e(format_created_at($run['started_at'] ?? '')) . '</td>';
                    echo '<td>' . e(format_created_at($run['finished_at'] ?? '')) . '</td>';
                    $softMismatchCount = max(0, (int)($run['save_soft_mismatch_count'] ?? 0));
                    echo '<td>' . e($run['summary'] ?? '');
                    if ($softMismatchCount > 0) {
                        echo '<br><span class="summary-badge" style="background:#f59e0b;color:#111827;">Aviso suave</span> <span class="muted">Diferencias solo en descripción: ' . e((string)$softMismatchCount) . '</span>';
                    }
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }
        }
    }

    echo '<section class="panel">';
    echo '<div class="branch-panel-head"><h2>Campañas registradas</h2><span class="summary-badge">' . e((string)count($campaigns)) . '</span></div>';
    if (empty($campaigns)) {
        echo '<div class="empty" style="margin-top:12px;">Todavía no hay campañas registradas.</div>';
    } else {
        render_live_filter('#publicistaCampaignRows tr[data-filter-text]', 'Buscar campaña, planning o estado...');
        echo '<div class="table-wrap" style="margin-top:12px;"><table><thead><tr><th>Campaña</th><th>Estado</th><th>Estrategia</th><th>Selección</th><th>Items</th><th>Acciones</th></tr></thead><tbody id="publicistaCampaignRows">';
        foreach ($campaigns as $row) {
            $campaignId = trim((string)($row['id'] ?? ''));
            $itemsCount = (int)($campaignItemCounts[$campaignId] ?? 0);
            $search = strtolower(trim(($row['nombre'] ?? '') . ' ' . ($row['estado'] ?? '') . ' ' . ($row['planning_id'] ?? '')));
            $planning = $planningsById[trim((string)($row['planning_id'] ?? ''))] ?? publicista_planning_get($row['planning_id'] ?? '');
            echo '<tr data-filter-text="' . e($search) . '">';
            echo '<td><strong>' . e($row['nombre'] ?? '') . '</strong><br><span class="muted">' . e(format_created_at($row['updated_at'] ?? ($row['created_at'] ?? ''))) . '</span></td>';
            echo '<td><span class="summary-badge">' . e(publicista_campaign_status_label($row['estado'] ?? 'draft')) . '</span></td>';
            echo '<td>' . e($planning['nombre'] ?? ($row['planning_id'] ?? 'Sin planning')) . '<br><span class="muted">' . e((string)($row['strategy_option_label'] ?? '')) . '</span></td>';
            echo '<td><strong>' . e((string)count((array)($row['product_ids'] ?? array()))) . '</strong> productos<br><span class="muted">' . e((string)count((array)($row['account_ids'] ?? array()))) . ' cuentas · min ' . e((string)($row['min_products'] ?? 0)) . ' / max ' . e((string)($row['max_products'] ?? 0)) . '</span></td>';
            echo '<td>' . e((string)$itemsCount) . '</td>';
            echo '<td><a class="mini-link" href="' . e(publicista_page_url('campanas', array('edit' => $row['id']))) . '">Abrir</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
}

function render_publicista_subir_anuncios_page() {
    $cfg = publicista_free_bump_config();
    $state = publicista_free_bump_state_prepare_today(publicista_free_bump_state());
    $plan = publicista_free_bump_plan_snapshot($cfg, $state, time());
    $accounts = array_values(array_filter(publicista_accounts_get(false), function($row) {
        return trim((string)($row['portal_code'] ?? '')) === 'destacamos';
    }));
    $accountsById = array();
    foreach ($accounts as $account) {
        $accountId = trim((string)($account['id'] ?? ''));
        if ($accountId === '') continue;
        $accountsById[$accountId] = $account;
    }
    $logs = publicista_free_bump_logs_get(40);

    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>Subir anuncios</h2><span class="summary-badge">' . ($cfg['enabled'] ? 'Activo' : 'Pausado') . '</span></div>';
    echo '<div class="cards four" style="margin-top:14px;">';
    echo '<div class="info-strip"><strong>Próximo intento</strong><br>' . e((string)($state['next_run_at'] ?: 'Sin planificar')) . '</div>';
    echo '<div class="info-strip"><strong>Cuentas listas</strong><br>' . e((string)($plan['ready_accounts_count'] ?? 0)) . ' de ' . e((string)($plan['selected_accounts_count'] ?? 0)) . '</div>';
    echo '<div class="info-strip"><strong>IDs totales</strong><br>' . e((string)($plan['total_listing_ids'] ?? 0)) . '</div>';
    echo '<div class="info-strip"><strong>Oportunidades restantes</strong><br>' . e((string)($plan['remaining_opportunities'] ?? 0)) . '</div>';
    echo '</div>';
    echo '<div class="cards four" style="margin-top:12px;">';
    echo '<div class="info-strip"><strong>Hoy OK</strong><br>' . e((string)($state['today_ok'] ?? 0)) . '</div>';
    echo '<div class="info-strip"><strong>Hoy sin libres</strong><br>' . e((string)($state['today_empty'] ?? 0)) . '</div>';
    echo '<div class="info-strip"><strong>Hoy errores</strong><br>' . e((string)($state['today_failed'] ?? 0)) . '</div>';
    echo '<div class="info-strip"><strong>Último éxito</strong><br>' . e((string)($state['last_success_at'] ?: 'Aún ninguno')) . '</div>';
    echo '</div>';
    echo '<div class="cards four" style="margin-top:12px;">';
    echo '<div class="info-strip"><strong>Objetivo diario</strong><br>' . e((string)($plan['daily_target_count_total'] ?? 0)) . '</div>';
    echo '<div class="info-strip"><strong>Ya subidos en ventana</strong><br>' . e((string)($plan['completed_in_window_total'] ?? 0)) . '</div>';
    echo '<div class="info-strip"><strong>Pendientes hoy</strong><br>' . e((string)($plan['pending_target_total'] ?? 0)) . '</div>';
    echo '<div class="info-strip"><strong>Cadencia calculada</strong><br>' . e((string)(!empty($plan['dynamic_interval_seconds']) ? ceil(((int)$plan['dynamic_interval_seconds']) / 60) . ' min aprox.' : 'Sin calcular')) . '</div>';
    echo '</div>';
    echo '<div class="info-strip" style="margin-top:14px;"><strong>Resumen operativo:</strong> ' . e(publicista_free_bump_summary_line($state, $plan)) . '</div>';
    echo '</section>';

    echo '<section class="panel panel-space">';
    echo '<div class="section-head"><div><h2>Configuración automática</h2><p>Este sistema entra en Destacamos, busca el primer anuncio libre y lo sube gratis respetando el cooldown de 12 horas por anuncio. Las cuentas se agrupan por nombre de grupo; cada grupo tiene su propia franja horaria.</p></div></div>';
    echo '<form method="post" class="form-grid">';
    echo '<input type="hidden" name="action" value="save_publicista_free_bump_config">';
    echo '<div class="field"><label>Activado</label><label style="display:flex;gap:8px;align-items:center;margin-top:10px;"><input type="checkbox" name="enabled" value="1"' . ($cfg['enabled'] ? ' checked' : '') . '> Ejecutar automáticamente</label></div>';
    echo '<div class="field"><label>Humanizar login y click</label><label style="display:flex;gap:8px;align-items:center;margin-top:10px;"><input type="checkbox" name="humanize" value="1"' . ($cfg['humanize'] ? ' checked' : '') . '> Añadir pausas humanas</label></div>';
    field_input('anticipation_minutes', 'Margen de anticipación (min)', (string)($cfg['anticipation_minutes'] ?? 8));
    field_input('interval_min_minutes', 'Intervalo mínimo (min)', (string)($cfg['interval_min_minutes'] ?? 12));
    field_input('interval_max_minutes', 'Intervalo máximo (min)', (string)($cfg['interval_max_minutes'] ?? 120));
    field_input('retry_empty_min_minutes', 'Reintento si no hay libres min (min)', (string)($cfg['retry_empty_min_minutes'] ?? 10));
    field_input('retry_empty_max_minutes', 'Reintento si no hay libres max (min)', (string)($cfg['retry_empty_max_minutes'] ?? 22));
    field_input('jitter_min_seconds', 'Jitter mínimo (seg)', (string)($cfg['jitter_min_seconds'] ?? 30));
    field_input('jitter_max_seconds', 'Jitter máximo (seg)', (string)($cfg['jitter_max_seconds'] ?? 180));

    // Group accounts by display_name
    $groupedAccounts = array();
    foreach ($accounts as $account) {
        $displayName = trim((string)($account['display_name'] ?? ''));
        if ($displayName === '') $displayName = '(sin nombre)';
        $groupedAccounts[$displayName][] = $account;
    }
    ksort($groupedAccounts);

    echo '<div class="field full">';
    echo '<label>Grupos de cuentas</label>';
    if (empty($groupedAccounts)) {
        echo '<div class="empty" style="margin-top:10px;">No hay cuentas de Destacamos registradas todavía.</div>';
    } else {
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px;margin-top:10px;">';
        foreach ($groupedAccounts as $groupName => $groupAccounts) {
            $groupCfg = $cfg['groups'][$groupName] ?? array();
            $groupEnabled = !empty($groupCfg['enabled']);
            $groupStartTime = $groupCfg['start_time'] ?? '08:00';
            $groupEndTime = $groupCfg['end_time'] ?? '23:00';
            $safeGroupName = e($groupName);
            echo '<div class="info-strip" style="display:block;">';
            echo '<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">';
            echo '<label style="display:flex;gap:6px;align-items:center;cursor:pointer;">';
            echo '<input type="checkbox" name="groups[' . $safeGroupName . '][enabled]" value="1"' . ($groupEnabled ? ' checked' : '') . '>';
            echo '<strong>' . $safeGroupName . '</strong>';
            echo '</label>';
            echo '</div>';
            echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:8px;">';
            echo '<div><label style="font-size:12px;color:var(--muted,#888);">Inicio</label><br><input type="time" name="groups[' . $safeGroupName . '][start_time]" value="' . e($groupStartTime) . '" style="width:100%;"></div>';
            echo '<div><label style="font-size:12px;color:var(--muted,#888);">Fin</label><br><input type="time" name="groups[' . $safeGroupName . '][end_time]" value="' . e($groupEndTime) . '" style="width:100%;"></div>';
            echo '</div>';
            foreach ($groupAccounts as $acct) {
                $listingIdsTotal = count((array)($acct['portal_listing_ids'] ?? array()));
                echo '<div style="font-size:12px;padding:4px 0;border-top:1px solid rgba(0,0,0,.06);">';
                echo '<span class="muted">' . e($acct['login_user'] ?? '') . ' · ' . e(publicista_account_status_label($acct['estado'] ?? 'active')) . ' · ' . e((string)$listingIdsTotal) . ' IDs</span>';
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    echo '<div class="field-help">Cada grupo se activa y se sube de forma aleatoria dentro de su propia franja horaria. Si la hora de fin es menor que la de inicio, la ventana cruza medianoche.</div>';
    echo '</div>';
    echo '<div class="full" style="display:flex;gap:10px;flex-wrap:wrap;">';
    echo '<button class="btn-primary">Guardar automatización</button>';
    echo '</div>';
    echo '</form>';
    echo '</section>';

    echo '<section class="panel panel-space">';
    echo '<div class="section-head"><div><h2>Planificación calculada</h2><p>La planificación usa los IDs asignados a las cuentas y el historial de subidas. El botón "Subir ahora" fuerza un ciclo inmediato.</p></div>';
    echo '<div class="section-head-actions" style="display:flex;gap:10px;flex-wrap:wrap;">';
    echo '<form method="post" class="inline-form"><input type="hidden" name="action" value="run_publicista_free_bump_cycle"><button class="btn-secondary-mini">Subir ahora</button></form>';
    echo '</div></div>';
    echo '<div class="cards four" style="margin-top:10px;">';
    echo '<div class="info-strip"><strong>Próximo recomendado</strong><br>' . e((string)($plan['recommended_next_at'] ?? 'sin calcular')) . '</div>';
    echo '<div class="info-strip"><strong>Próxima elegibilidad</strong><br>' . e((string)($plan['soonest_future_at'] ?: 'Ahora o ninguna')) . '</div>';
    echo '<div class="info-strip"><strong>Oportunidades restantes</strong><br>' . e((string)($plan['remaining_opportunities'] ?? 0)) . '</div>';
    echo '<div class="info-strip"><strong>Ventanas activas</strong><br>' . e((string)(count(array_filter((array)($plan['groups'] ?? array()), function($g) { return !empty($g['window_in_progress']); })))) . ' de ' . e((string)count((array)($plan['groups'] ?? array()))) . ' grupos</div>';
    echo '</div>';
    if (!empty($plan['groups'])) {
        echo '<div class="table-wrap" style="margin-top:14px;"><table><thead><tr>';
        echo '<th>Grupo</th><th>Ventana</th><th>Fase</th><th>Cuentas listas</th><th>Objetivo / pendientes</th><th>Oportunidades</th><th>Ya pueden subir</th><th>Próximo recomendado</th>';
        echo '</tr></thead><tbody>';
        foreach ((array)$plan['groups'] as $groupPlan) {
            echo '<tr>';
            echo '<td><strong>' . e($groupPlan['group'] ?? '') . '</strong></td>';
            echo '<td>' . e((string)($groupPlan['window_start_at'] ?? '')) . '<br><span class="muted">hasta ' . e((string)($groupPlan['window_end_at'] ?? '')) . '</span></td>';
            echo '<td>' . e((string)($groupPlan['window_phase'] ?? '-')) . (!empty($groupPlan['window_in_progress']) ? ' <span class="summary-badge">Activa</span>' : '') . '</td>';
            echo '<td>' . e((string)($groupPlan['ready_count'] ?? 0)) . ' de ' . e((string)($groupPlan['total_count'] ?? 0)) . '</td>';
            echo '<td>' . e((string)($groupPlan['daily_target_count'] ?? 0)) . ' / ' . e((string)($groupPlan['pending_target_count'] ?? 0)) . '<br><span class="muted">cadencia ' . e((string)(!empty($groupPlan['required_interval_seconds']) ? ceil(((int)$groupPlan['required_interval_seconds']) / 60) . ' min' : '-')) . '</span></td>';
            echo '<td>' . e((string)($groupPlan['remaining_count'] ?? 0)) . '</td>';
            echo '<td>' . e((string)($groupPlan['due_now_count'] ?? 0)) . '</td>';
            echo '<td>' . e((string)($groupPlan['recommended_next_at'] ?: '-')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    if (!empty($plan['accounts'])) {
        echo '<div class="table-wrap" style="margin-top:14px;"><table><thead><tr>';
        echo '<th>Grupo</th><th>Cuenta</th><th>Estado</th><th>IDs</th><th>Oportunidades</th><th>Ya puede subir</th><th>Siguiente elegible</th><th>Acción</th>';
        echo '</tr></thead><tbody>';
        $lastGroup = null;
        foreach ((array)$plan['accounts'] as $accountPlan) {
            $group = $accountPlan['group'] ?? '';
            echo '<tr>';
            echo '<td>' . ($group !== $lastGroup ? '<strong>' . e($group) . '</strong>' : '') . '</td>';
            $lastGroup = $group;
            echo '<td><strong>' . e($accountPlan['account_label'] ?? ($accountPlan['account_id'] ?? '')) . '</strong><br><span class="muted">' . e($accountPlan['account_id'] ?? '') . '</span></td>';
            if (!empty($accountPlan['ready'])) {
                echo '<td><span class="summary-badge">Lista</span></td>';
            } else {
                echo '<td><span class="summary-badge">Saltada</span><br><span class="muted">' . e($accountPlan['skip_reason'] ?? '') . '</span></td>';
            }
            echo '<td>' . e((string)($accountPlan['listing_ids_total'] ?? 0)) . '</td>';
            echo '<td>' . e((string)($accountPlan['remaining_count'] ?? 0)) . '</td>';
            echo '<td>' . e((string)($accountPlan['due_now_count'] ?? 0)) . '</td>';
            echo '<td>' . e((string)($accountPlan['next_eligible_at'] ?: '-')) . '</td>';
            echo '<td>';
            if (!empty($accountPlan['ready']) && trim((string)($accountPlan['account_id'] ?? '')) !== '') {
                echo '<form method="post" class="inline-form" style="display:inline-flex;">';
                echo '<input type="hidden" name="action" value="run_publicista_free_bump_cycle">';
                echo '<input type="hidden" name="account_id" value="' . e((string)$accountPlan['account_id']) . '">';
                echo '<button class="btn-secondary-mini" type="submit">Subir ahora esta cuenta</button>';
                echo '</form>';
            } else {
                echo '<span class="muted">No disponible</span>';
            }
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';

    echo '<section class="panel panel-space">';
    echo '<div class="section-head"><div><h2>Log de subidas</h2><p>Cada fila corresponde a un ciclo del scheduler o a un intento manual con "Subir ahora". Si una cuenta no tenía ningún anuncio libre, el sistema prueba otras antes de replanificar.</p></div></div>';
    if (empty($logs)) {
        echo '<div class="empty">Todavía no hay ejecuciones registradas.</div>';
    } else {
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Hora</th><th>Origen</th><th>Estado</th><th>Cuenta</th><th>Usuario</th><th>Listing</th><th>Intentos</th><th>Resumen / detalle</th><th>Próximo</th>';
        echo '</tr></thead><tbody>';
        foreach ($logs as $row) {
            $attempts = array_values(array_filter((array)($row['attempts'] ?? array()), function($attempt) {
                return is_array($attempt);
            }));
            $primaryAttempt = function_exists('publicista_free_bump_primary_attempt') ? publicista_free_bump_primary_attempt($attempts) : (!empty($attempts) ? $attempts[0] : array());
            $accountId = trim((string)($row['account_id'] ?? ''));
            $accountLabel = trim((string)($row['account_label'] ?? ($row['account_id'] ?? '')));
            if (($accountLabel === '' || $accountId === '') && !empty($primaryAttempt)) {
                if ($accountId === '') $accountId = trim((string)($primaryAttempt['account_id'] ?? ''));
                if ($accountLabel === '') $accountLabel = trim((string)($primaryAttempt['account_label'] ?? ($primaryAttempt['account_id'] ?? '')));
            }
            $accountUser = '';
            if ($accountId !== '' && isset($accountsById[$accountId])) {
                $accountUser = trim((string)($accountsById[$accountId]['login_user'] ?? ''));
            }
            $listingId = trim((string)($row['listing_id'] ?? ''));
            if ($listingId === '' && !empty($primaryAttempt)) {
                $listingId = trim((string)($primaryAttempt['listing_id'] ?? ''));
            }
            $detailDump = array(
                'request_id' => trim((string)($row['request_id'] ?? '')),
                'trigger' => trim((string)($row['trigger'] ?? 'scheduler')),
                'status' => trim((string)($row['status'] ?? '')),
                'ok' => !empty($row['ok']),
                'error' => trim((string)($row['error'] ?? '')),
                'error_code' => trim((string)($row['error_code'] ?? '')),
                'account_id' => trim((string)($row['account_id'] ?? '')),
                'account_label' => trim((string)($row['account_label'] ?? '')),
                'listing_id' => trim((string)($row['listing_id'] ?? '')),
                'attempts' => $attempts,
                'next_run_at' => trim((string)($row['next_run_at'] ?? '')),
            );
            $detailJson = json_encode($detailDump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($detailJson === false) $detailJson = 'No se pudo codificar el detalle.';
            echo '<tr>';
            echo '<td>' . e(format_created_at($row['created_at'] ?? '')) . '</td>';
            echo '<td>' . e(trim((string)($row['trigger'] ?? 'scheduler')) === 'manual' ? 'Manual' : 'Automático') . '</td>';
            echo '<td><span class="summary-badge">' . e($row['status'] ?? '') . '</span></td>';
            echo '<td>' . e($accountLabel !== '' ? $accountLabel : '-') . '</td>';
            echo '<td>' . e($accountUser !== '' ? $accountUser : '-') . '</td>';
            echo '<td>' . e($listingId !== '' ? $listingId : '-') . '</td>';
            echo '<td>' . e((string)count($attempts)) . '</td>';
            echo '<td>';
            echo e($row['summary'] ?? '');
            if (!empty($attempts)) {
                echo '<details style="margin-top:8px;"><summary>Ver detalle del ciclo</summary>';
                foreach ($attempts as $idx => $attempt) {
                    $attemptAccountId = trim((string)($attempt['account_id'] ?? ''));
                    $attemptAccount = trim((string)($attempt['account_label'] ?? ($attempt['account_id'] ?? '')));
                    $attemptUser = '';
                    if ($attemptAccountId !== '' && isset($accountsById[$attemptAccountId])) {
                        $attemptUser = trim((string)($accountsById[$attemptAccountId]['login_user'] ?? ''));
                    }
                    $attemptListing = trim((string)($attempt['listing_id'] ?? ''));
                    $attemptError = trim((string)($attempt['error'] ?? ''));
                    $attemptErrorCode = trim((string)($attempt['error_code'] ?? ''));
                    $attemptStatus = !empty($attempt['ok']) ? 'ok' : (($attemptErrorCode === 'no_free_listing_available') ? 'sin libres' : 'error');
                    $availableBefore = (int)($attempt['available_count_before'] ?? 0);
                    $availableIds = array_values((array)($attempt['available_listing_ids_before'] ?? array()));
                    echo '<div class="info-strip" style="margin-top:8px;">';
                    echo '<strong>Intento ' . e((string)($idx + 1)) . ' · ' . e($attemptStatus) . '</strong><br>';
                    echo 'Cuenta: ' . e($attemptAccount !== '' ? $attemptAccount : '-');
                    if ($attemptUser !== '') {
                        echo ' · Usuario: ' . e($attemptUser);
                    }
                    if ($attemptListing !== '') {
                        echo ' · Listing: ' . e($attemptListing);
                    }
                    echo ' · Libres antes: ' . e((string)$availableBefore);
                    if (!empty($availableIds)) {
                        echo '<br>IDs libres detectados: ' . e(implode(', ', $availableIds));
                    }
                    if ($attemptError !== '') {
                        echo '<br>Error: ' . e($attemptError);
                    } elseif ($attemptErrorCode !== '') {
                        echo '<br>Código: ' . e($attemptErrorCode);
                    }
                    if (trim((string)($attempt['current_url'] ?? '')) !== '') {
                        echo '<br>URL: ' . e($attempt['current_url']);
                    }
                    echo '</div>';
                }
                echo '<pre style="white-space:pre-wrap;word-break:break-word;max-width:900px;margin-top:8px;">' . e($detailJson) . '</pre>';
                echo '</details>';
            }
            echo '</td>';
            echo '<td>' . e(format_created_at($row['next_run_at'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
}

function render_publicista_crear_perfiles_page($embedded = false) {
    $jobs = publicista_jobs_get();
    $clientas = publicista_all_clientas();

    $clientaFilterRaw = trim((string)request_get('clienta_id'));
    $clientaFilterScope = trim((string)request_get('clienta_scope'));
    $clientaFilterParsed = publicista_parse_clienta_picker_value($clientaFilterRaw);
    if ($clientaFilterParsed['id'] !== '') {
        $clientaFilter = $clientaFilterParsed['id'];
        if ($clientaFilterScope === '') $clientaFilterScope = $clientaFilterParsed['scope'];
    } else {
        $clientaFilter = $clientaFilterRaw;
    }

    $selectedJobId = trim((string)request_get('job'));
    $selectedJob = $selectedJobId !== '' ? publicista_job_get($selectedJobId) : null;

    if (!$embedded) {
        page_header('Publicista', 'Sube la foto, genera el pack y revisa candidatas/finales sin salir del CRM');
    }

    if ($clientaFilter !== '') {
        $jobs = array_values(array_filter($jobs, function ($row) use ($clientaFilter) {
            return (($row['clienta_id'] ?? '') === $clientaFilter);
        }));
    }

    $showOnlyJobDetail = (bool)$selectedJob;

    if ($showOnlyJobDetail) {
        echo '<section class="panel panel-space">';
        echo '<div class="section-head"><div><h2>Ficha de producto</h2><p>Vista centrada en un único producto publicitario.</p></div>';
        echo '<div class="section-head-actions" style="display:flex;gap:10px;flex-wrap:wrap;">';
        echo '<a class="btn-secondary-mini" href="' . e(publicista_tab_url()) . '">← Volver al listado de productos</a>';
        echo '</div></div>';
        echo '</section>';
    }

    if (!$showOnlyJobDetail) {

        echo '<section class="panel panel-space">';
        echo '<div class="section-head"><div><h2>Nuevo producto publicitario</h2><p>Flujo rápido: sube foto, genera pack del producto, revisa y acepta.</p></div></div>';
        if (empty($clientas)) {
            echo '<div class="empty">No hay clientas todavía. Primero necesitas al menos una clienta en LaMami o Jostal.</div>';
        } else {
            echo '<form method="post" enctype="multipart/form-data" class="form-grid">';
            echo '<input type="hidden" name="action" value="create_publicista_job">';
            publicista_field_clienta_picker('clienta_id', 'Clienta', $clientas, publicista_clienta_picker_selected_value($clientaFilter, $clientaFilterScope));
            field_input('nombre_trabajo', 'Nombre interno del pack', '');
            field_input('publish_name', 'Nombre de publicación (el que aparece en los anuncios)', '');
            echo '<div class="field full">';
            echo '<label>Foto original <span style="color:#e11d48;">*</span></label>';
            echo '<input type="file" name="source_image" accept="image/jpeg,image/png,image/webp">';
            echo '<div class="field-help">Esta foto se usa directamente para crear el producto y lanzar todo el pipeline inicial.</div>';
            echo '</div>';

            echo '<div class="field full"><hr style="margin:4px 0;border:none;border-top:1px solid #e5e7eb;"><strong style="font-size:13px;color:#6b7280;">Ropa y estilo visual</strong></div>';
            publicista_render_production_params_fields(array());

            echo '<div class="field full"><hr style="margin:4px 0;border:none;border-top:1px solid #e5e7eb;"><strong style="font-size:13px;color:#6b7280;">Restricciones y opciones de imagen</strong></div>';
            field_textarea('restrictions_text', 'Restricciones libres (imagen)', '', 2);
            echo '<div class="field full">';
            echo '<label>Restricciones rápidas</label>';
            publicista_render_restriction_checkboxes(array('keep_hair_color', 'keep_body_build', 'avoid_visible_tattoos'));
            echo '</div>';

            echo '<div class="field full"><hr style="margin:4px 0;border:none;border-top:1px solid #e5e7eb;"><strong style="font-size:13px;color:#6b7280;">Brief libre (prioridad máxima)</strong></div>';
            echo '<div class="field full">';
            echo '<label>Brief visual libre del operador</label>';
            echo '<textarea name="operator_brief" rows="3" placeholder="Cualquier detalle específico de imagen que quieras que prime sobre todo: ropa concreta, ambiente, rasgos físicos a resaltar, etc. Este texto tiene la máxima prioridad en la generación de imágenes." style="width:100%;"></textarea>';
            echo '<div class="field-help">Prevalece sobre el descriptor automático y sobre las selecciones de arriba.</div>';
            echo '</div>';

            echo '<div class="field full"><hr style="margin:4px 0;border:none;border-top:1px solid #e5e7eb;"><strong style="font-size:13px;color:#6b7280;">Textos publicitarios</strong></div>';
            echo '<div class="field">';
            echo '<label>Tono de textos</label>';
            echo '<select name="copy_tone">';
            foreach (publicista_copy_tone_options() as $value => $label) {
                echo '<option value="' . e($value) . '">' . e($label) . '</option>';
            }
            echo '</select>';
            echo '</div>';
            echo '<div class="field full">';
            echo '<label>Plataformas destino (para adaptar el copy)</label>';
            publicista_render_checkboxes_group('copy_platforms', publicista_copy_platform_options(), array('destacamos', 'loquosex'));
            echo '</div>';
            echo '<div class="field full">';
            echo '<label>Ángulos de anuncio preferidos</label>';
            publicista_render_checkboxes_group('copy_angles', publicista_copy_angle_options(), array());
            echo '</div>';
            echo '<div class="field full">';
            echo '<label>Brief libre para los textos</label>';
            echo '<textarea name="copy_brief" rows="2" placeholder="Apodo, frase característica, servicios concretos, horarios, zona, o cualquier instrucción específica para el copy..." style="width:100%;"></textarea>';
            echo '</div>';
            field_textarea('services_snapshot', 'Servicios (opcional)', '', 2);
            field_textarea('notas', 'Notas internas', '', 2);

            echo '<div class="field">';
            echo '<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="auto_regenerate" value="1"> Auto-regeneración automática</label>';
            echo '<div class="field-help">Déjala desactivada. Con el flujo actual se generan 6 candidatas referenciadas y eliges si regeneras alguna concreta.</div>';
            echo '</div>';

            // ---- Selector de modelo de imagen ----
            echo '<div class="field full"><hr style="margin:4px 0;border:none;border-top:1px solid #e5e7eb;"><strong style="font-size:13px;color:#6b7280;">Modelo de generación de imágenes</strong></div>';
            echo '<div class="field">';
            echo '<label>Modelo de imagen <span style="color:#6b7280;font-weight:normal;">(afecta solo a la generación de candidatas)</span></label>';
            echo '<select name="image_model_selector" id="pollo_image_model_selector" onchange="publicistaModelChange(this.value)">';
            echo '<option value="gpt">GPT (OpenAI · configurado en sistema)</option>';
            if (function_exists('publicista_pollo_models')) {
                foreach (publicista_pollo_models() as $polloKey => $polloCfg) {
                    $sel = ($polloKey === 'pollo-image-v2') ? ' selected' : '';
                    echo '<option value="' . e($polloKey) . '"' . $sel . '>' . e($polloCfg['name']) . '</option>';
                }
            }
            echo '</select>';
            echo '<div class="field-help">Con GPT usa el pipeline habitual con OpenAI. Con modelos Pollo.ai usa la cookie de sesión guardada en ConfigM para generar imágenes vía texto (sin referencia directa de imagen, pero con el mismo prompt detallado).</div>';
            echo '</div>';

            // ---- Info cookie Pollo.ai (se muestra al elegir modelo Pollo) ----
            $polloDays = function_exists('publicista_pollo_cookie_days_remaining') ? publicista_pollo_cookie_days_remaining() : -1;
            $polloExpires = function_exists('publicista_pollo_cookie_expires') ? publicista_pollo_cookie_expires() : '';
            if ($polloDays > 30) { $polloBadge = 'OK'; $polloColor = '#059669'; }
            elseif ($polloDays > 7) { $polloBadge = 'Aviso: menos de 1 mes'; $polloColor = '#d97706'; }
            elseif ($polloDays > 0) { $polloBadge = 'URGENTE: menos de 1 semana'; $polloColor = '#dc2626'; }
            else { $polloBadge = 'CADUCADA'; $polloColor = '#dc2626'; }

            echo '<div class="field full" id="pollo_cookie_info_panel" style="display:none;">';
            echo '<div class="info-strip" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;padding:10px 14px;">';
            echo '<span style="font-size:13px;"><strong>Cookie Pollo.ai:</strong> <span style="color:' . e($polloColor) . ';font-weight:600;">' . e($polloBadge) . '</span>';
            if ($polloDays > 0) {
                echo ' · expira ' . e($polloExpires) . ' (en ' . e((string)$polloDays) . ' días)';
            } else {
                echo ' · expiró el ' . e($polloExpires);
            }
            echo '</span>';
            echo '<button type="button" class="btn-secondary-mini" onclick="document.getElementById(\'polloInstruccionesModal\').showModal()">¿Cómo renovar la cookie?</button>';
            echo '</div>';
            echo '</div>';

            // ---- Modal de instrucciones ----
            echo '<dialog id="polloInstruccionesModal" style="max-width:600px;width:90%;padding:28px;border-radius:12px;border:1px solid #e5e7eb;background:#fff;box-shadow:0 20px 60px rgba(0,0,0,.15);">';
            echo '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">';
            echo '<h3 style="margin:0;font-size:16px;color:#111827;">Cómo renovar la cookie de Pollo.ai</h3>';
            echo '<button type="button" onclick="document.getElementById(\'polloInstruccionesModal\').close()" style="background:none;border:none;font-size:24px;cursor:pointer;line-height:1;color:#6b7280;">×</button>';
            echo '</div>';
            echo '<div style="font-size:13px;color:#374151;line-height:1.75;">';
            echo '<p style="margin:0 0 12px;"><strong>La cookie caduca cada ~3 meses.</strong> Cuando caduque, la generación dará error 401 o 403.</p>';
            echo '<ol style="margin:0 0 14px;padding-left:20px;">';
            echo '<li>Abre <strong>Chrome o Firefox</strong> y ve a <a href="https://pollo.ai" target="_blank" style="color:#4f46e5;">pollo.ai</a></li>';
            echo '<li>Inicia sesión con tu cuenta (Google u otro método)</li>';
            echo '<li>Pulsa <strong>F12</strong> para abrir las DevTools</li>';
            echo '<li>Ve a la pestaña <strong>Network</strong> (Red)</li>';
            echo '<li>Activa el filtro <strong>Fetch/XHR</strong></li>';
            echo '<li>Recarga la página o realiza cualquier acción en la web</li>';
            echo '<li>Haz clic en cualquier petición que vaya a <code style="background:#f3f4f6;padding:1px 4px;border-radius:3px;">pollo.ai</code></li>';
            echo '<li>En el panel derecho: <strong>Headers → Request Headers</strong></li>';
            echo '<li>Busca la línea que empieza por <code style="background:#f3f4f6;padding:1px 4px;border-radius:3px;">Cookie:</code></li>';
            echo '<li>Copia <strong>TODO</strong> el valor (empieza por <code style="background:#f3f4f6;padding:1px 4px;border-radius:3px;">__Secure-next-auth.session-token=...</code>)</li>';
            echo '<li>Ve a <strong>Josue → ConfigM</strong> y pega el valor en el campo <strong>"Cookie sesión Pollo.ai"</strong></li>';
            echo '<li>Actualiza también el campo <strong>"Fecha expiración cookie Pollo.ai"</strong> (la ves en el header <code style="background:#f3f4f6;padding:1px 4px;border-radius:3px;">set-cookie → Expires=...</code>)</li>';
            echo '</ol>';
            echo '<p style="margin:0;padding:10px 12px;background:#f0fdf4;border-radius:6px;font-size:12px;color:#166534;"><strong>Alternativa rápida (Firefox):</strong> F12 → Almacenamiento → Cookies → https://pollo.ai → busca <code>__Secure-next-auth.session-token</code> → copia el valor → pega en ConfigM con el formato <code>__Secure-next-auth.session-token=VALOR_COPIADO</code></p>';
            echo '</div>';
            echo '</dialog>';

            echo '<script>function publicistaModelChange(v){document.getElementById("pollo_cookie_info_panel").style.display=v!=="gpt"?"":"none";}</script>';

            echo '<div class="full" style="display:flex;gap:10px;flex-wrap:wrap;">';
            echo '<button class="btn-primary">Crear y generar</button>';
            echo '</div>';

            echo '</form>';
        }
        
        echo '</section>';

        echo '<section class="panel panel-space">';

        echo '<div class="branch-panel-head"><h2>Productos creados</h2><span class="summary-badge">' . e(count($jobs)) . '</span></div>';
        if (empty($jobs)) {
            echo '<div class="empty">Todavía no hay trabajos creados en Publicista.</div>';
        } else {
            render_live_filter('#publicistaJobsRows tr[data-filter-text]', 'Buscar producto o clienta...');
            echo '<div class="table-wrap"><table><thead><tr>';
            echo '<th>Producto</th><th>Estado</th><th>Clienta</th><th>Finales</th><th>Actualizado</th><th>Acciones</th>';
            echo '</tr></thead><tbody id="publicistaJobsRows">';
            foreach ($jobs as $row) {
                $wf = function_exists('publicista_job_workflow') ? publicista_job_workflow($row) : array();
                $clientaEditUrl = publicista_clienta_edit_url($row['clienta_id'] ?? '', $row['clienta_scope'] ?? '');
                $clientaScopeLabel = publicista_clienta_source_label(($row['clienta_scope'] ?? '') === 'jostal' ? 'jostal' : 'lamami');
                $searchText = strtolower(trim(
                    ($row['nombre_trabajo'] ?? '') . ' ' .
                    ($row['clienta_nombre_snapshot'] ?? '') . ' ' .
                    ($row['localidad_snapshot'] ?? '') . ' ' .
                    ($row['provincia_snapshot'] ?? '') . ' ' .
                    ($row['estado'] ?? '') . ' ' .
                    $clientaScopeLabel
                ));
                $finalCount = is_array($row['final_images'] ?? null) ? count($row['final_images']) : 0;
                echo '<tr data-filter-text="' . e($searchText) . '">';
                echo '<td><strong>' . e($row['nombre_trabajo'] ?: ('Producto ' . ($row['id'] ?? ''))) . '</strong><br><span class="muted">' . e($row['id'] ?? '') . '</span></td>';
                echo '<td><span class="summary-badge">' . e(publicista_job_status_label($row['estado'] ?? 'draft')) . '</span>';
                if (!empty($wf['pack_final'])) echo '<div class="muted small" style="margin-top:6px;">Definitivo</div>';
                echo '</td>';
                echo '<td>' . e($row['clienta_nombre_snapshot'] ?? '-') . '<br><span class="muted small">' . e($clientaScopeLabel) . '</span></td>';
                echo '<td>' . e((string)$finalCount) . '/4</td>';
                echo '<td>' . e(format_created_at($row['updated_at'] ?? '')) . '</td>';
                echo '<td><a class="mini-link" href="' . e(publicista_tab_url(array('job' => $row['id']))) . '">Abrir ficha</a>';
                if ($clientaEditUrl !== '') echo ' · <a class="mini-link" href="' . e($clientaEditUrl) . '">Clienta</a>';
                echo ' · <form method="post" class="inline-form" style="display:inline;">';
                echo '<input type="hidden" name="action" value="duplicate_publicista_job">';
                echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                echo '<button class="mini-link" style="background:none;border:none;padding:0;cursor:pointer;">Duplicar</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';
    }
    if (!$selectedJob) {
        return;
    }

    $assetDirs = is_array($selectedJob['asset_dirs'] ?? null) ? $selectedJob['asset_dirs'] : array();
    $source = is_array($selectedJob['source_image'] ?? null) ? $selectedJob['source_image'] : array();
    $processing = is_array($selectedJob['processing'] ?? null) ? $selectedJob['processing'] : array();
    $localAssets = is_array($selectedJob['local_assets'] ?? null) ? $selectedJob['local_assets'] : array();
    $descriptor = is_array($selectedJob['descriptor'] ?? null) ? $selectedJob['descriptor'] : array();
    $promptMaster = is_array($selectedJob['prompt_master'] ?? null) ? $selectedJob['prompt_master'] : array();
    $pipeline = is_array($selectedJob['pipeline'] ?? null) ? $selectedJob['pipeline'] : array();
    $candidates = is_array($selectedJob['candidates'] ?? null) ? $selectedJob['candidates'] : array();
    $finalImages = is_array($selectedJob['final_images'] ?? null) ? $selectedJob['final_images'] : array();
    $workflow = function_exists('publicista_job_workflow') ? publicista_job_workflow($selectedJob) : array();
    $copyPack = function_exists('publicista_job_copy_pack') ? publicista_job_copy_pack($selectedJob) : array();
    $currentCopyVersion = function_exists('publicista_current_copy_version') ? publicista_current_copy_version($selectedJob) : null;
    $costs = is_array($selectedJob['costs'] ?? null) ? $selectedJob['costs'] : array();
    $clientaUsage = (!empty($selectedJob['clienta_id']) && function_exists('publicista_clienta_usage_summary')) ? publicista_clienta_usage_summary($selectedJob['clienta_id']) : array('jobs' => 0, 'definitive' => 0, 'copies' => 0);
    $selectedClientaEditUrl = publicista_clienta_edit_url($selectedJob['clienta_id'] ?? '', $selectedJob['clienta_scope'] ?? '');
    $selectedClientaPickerValue = publicista_clienta_picker_selected_value($selectedJob['clienta_id'] ?? '', $selectedJob['clienta_scope'] ?? '');
    $restrictionLabels = publicista_restriction_labels($workflow['restriction_flags'] ?? array());
    $descriptorData = is_array($descriptor['data'] ?? null) ? $descriptor['data'] : array();
    $batchState = function_exists('publicista_pipeline_batch_state') ? publicista_pipeline_batch_state($selectedJob) : array();
    $hasPendingBatch = function_exists('publicista_pipeline_has_pending_batch') ? publicista_pipeline_has_pending_batch($selectedJob) : false;
    $batchStatusLabel = function_exists('publicista_batch_status_label') ? publicista_batch_status_label($batchState['status'] ?? '') : '-';
    $isPipelineRunning = function_exists('publicista_pipeline_is_running') ? publicista_pipeline_is_running($selectedJob) : (($selectedJob['estado'] ?? '') === 'processing');
    $pipelineButtonLabel = $hasPendingBatch ? 'Relanzar generación' : 'Generar / regenerar 6 candidatas';
    $pipelineWaitingLabel = $hasPendingBatch ? 'Generación en curso / esperando resultado' : 'Generación en curso';
    $pipelineStartedLabel = !empty($processing['last_started_at']) ? format_created_at($processing['last_started_at']) : '';
    $canCloseProfileAsFinished = (count($finalImages) >= 4) && !empty($currentCopyVersion) && empty($workflow['pack_final']);

    echo '<section class="panel panel-space">';
    echo '<div class="section-head">';
    echo '<div>';
    echo '<h2>' . e($selectedJob['nombre_trabajo'] ?: 'Producto publicitario') . '</h2>';
    echo '<div class="muted">Clienta: ' . e($selectedJob['clienta_nombre_snapshot'] ?? '-') . ' · Producto ID: ' . e($selectedJob['id'] ?? '') . ' · Estado: ' . e(publicista_job_status_label($selectedJob['estado'] ?? 'draft')) . '</div>';
    if (!empty($workflow['pack_final'])) {
        echo '<div class="info-strip" style="margin-top:10px;"><strong>Pack definitivo</strong> · ' . e(format_created_at($workflow['pack_finalized_at'] ?? '')) . '</div>';
    }
    echo '</div>';
    echo '<div class="section-head-actions" style="display:flex;gap:10px;flex-wrap:wrap;">';
    if ($selectedClientaEditUrl !== '') {
        echo '<a class="btn-secondary-mini" href="' . e($selectedClientaEditUrl) . '">Abrir clienta</a>';
    }
    echo '<form method="post" class="inline-form">';
    echo '<input type="hidden" name="action" value="duplicate_publicista_job">';
    echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
    echo '<button class="btn-secondary-mini">Duplicar como base</button>';
    echo '</form>';
    if ($canCloseProfileAsFinished) {
        echo '<form method="post" class="inline-form">';
        echo '<input type="hidden" name="action" value="mark_publicista_pack_definitive">';
        echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
        echo '<button class="btn-primary">Cerrar perfil como terminado</button>';
        echo '</form>';
    }
    echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar este trabajo y su estructura de carpetas?\')">';
    echo '<input type="hidden" name="action" value="delete_publicista_job">';
    echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
    echo '<button class="btn-danger-mini">Eliminar producto</button>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    echo '<div class="cards four">';
    echo '<div class="info-strip"><strong>Original</strong><br>' . (!empty($source['stored_path']) ? 'Subida' : 'Pendiente') . '</div>';
    echo '<div class="info-strip"><strong>Candidatas</strong><br>' . e((string)count($candidates)) . '</div>';
    echo '<div class="info-strip"><strong>Finales</strong><br>' . e((string)count($finalImages)) . '/4</div>';
    echo '<div class="info-strip"><strong>Auto-regenerar</strong><br>' . (!empty($workflow['auto_regenerate']) ? 'Sí (encarece)' : 'No (recomendado)') . '</div>';
    echo '</div>';
    echo '<div class="cards four" style="margin-top:12px;">';
    echo '<div class="info-strip"><strong>Texto actual</strong><br>' . (!empty($copyPack['current_version_id']) ? 'Generado' : 'Pendiente') . '</div>';
    echo '<div class="info-strip"><strong>Coste estimado</strong><br>' . e('$' . number_format((float)($costs['estimated_usd_total'] ?? 0), 3, '.', '')) . '</div>';
    echo '<div class="info-strip"><strong>Uso clienta</strong><br>' . e((string)($clientaUsage['jobs'] ?? 0)) . ' jobs · ' . e((string)($clientaUsage['definitive'] ?? 0)) . ' definitivos</div>';
    echo '<div class="info-strip"><strong>Modo coste</strong><br>Batch imágenes + Flex textos</div>';
    echo '</div>';

    publicista_render_job_guide_panel($selectedJob);

    echo '<div class="publicista-visual-flow" style="margin-top:16px;">';
    echo '<a class="publicista-visual-step" href="#publicistaOrigen"><span class="step-num">1</span><span><strong>Original</strong><small>base y recorte</small></span></a>';
    echo '<a class="publicista-visual-step" href="#publicistaCandidates"><span class="step-num">2</span><span><strong>Candidatas</strong><small>revisión y selección</small></span></a>';
    echo '<a class="publicista-visual-step" href="#publicistaFinals"><span class="step-num">3</span><span><strong>Definitivas</strong><small>blur manual final</small></span></a>';
    echo '</div>';

    if ($canCloseProfileAsFinished) {
        echo '<section class="panel panel-space panel-highlight-success" style="margin-top:16px;">';
        echo '<div class="branch-panel-head">';
        echo '<h3>Perfil listo para terminar</h3>';
        echo '<span class="summary-badge">Último paso</span>';
        echo '</div>';
        echo '<div class="info-strip" style="margin-top:12px;">Las imágenes, los textos y el pack final ya están correctos. Solo falta cerrar este trabajo para dejarlo marcado como <strong>perfil terminado</strong>.</div>';
        echo '<form method="post" style="margin-top:16px;max-width:520px;" onsubmit="return confirm(\'¿Cerrar este perfil como terminado y dejarlo como definitivo?\')">';
        echo '<input type="hidden" name="action" value="mark_publicista_pack_definitive">';
        echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
        echo '<button class="btn-primary btn-big">Cerrar perfil como terminado</button>';
        echo '</form>';
        echo '</section>';
    }

    $openConfigPanel = empty($source['stored_path']) && empty($candidates) && empty($finalImages);
    echo '<details class="publicista-config-panel"' . ($openConfigPanel ? ' open' : '') . '>';
    echo '<summary>Configuración del trabajo</summary>';
    echo '<div class="publicista-config-help">Edita aquí la ficha y sus parámetros. La parte visual queda justo debajo, en orden: <strong>original → candidatas → definitivas</strong>.</div>';

    $prodParams = function_exists('publicista_job_production_params') ? publicista_job_production_params($selectedJob) : array();
    echo '<form method="post" class="form-grid" style="margin-top:16px;">';
    echo '<input type="hidden" name="action" value="save_publicista_job">';
    echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
    publicista_field_clienta_picker('clienta_id', 'Clienta', $clientas, $selectedClientaPickerValue);
    field_input('nombre_trabajo', 'Nombre interno del pack', $selectedJob['nombre_trabajo'] ?? '', true);
    field_input('publish_name', 'Nombre de publicación (el que aparece en los anuncios)', $selectedJob['publish_name'] ?? '');
    echo '<div class="field">';
    echo '<label>Estado</label>';
    echo '<select name="estado">';
    foreach (publicista_job_status_options() as $value => $label) {
        $sel = (($selectedJob['estado'] ?? 'draft') === $value) ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    field_input('localidad_snapshot', 'Localidad snapshot', $selectedJob['localidad_snapshot'] ?? '');
    field_input('provincia_snapshot', 'Provincia snapshot', $selectedJob['provincia_snapshot'] ?? '');

    echo '<div class="field full"><hr style="margin:4px 0;border:none;border-top:1px solid #e5e7eb;"><strong style="font-size:13px;color:#6b7280;">Ropa y estilo visual</strong><div class="field-help" style="margin-top:4px;">Estos parámetros se aplicarán al regenerar el pipeline de imágenes.</div></div>';
    publicista_render_production_params_fields($prodParams);

    echo '<div class="field full"><hr style="margin:4px 0;border:none;border-top:1px solid #e5e7eb;"><strong style="font-size:13px;color:#6b7280;">Restricciones de imagen</strong></div>';
    field_textarea('restrictions_text', 'Restricciones libres (imagen)', $workflow['restrictions_text'] ?? '', 2);
    echo '<div class="field full">';
    echo '<label>Restricciones rápidas</label>';
    publicista_render_restriction_checkboxes($workflow['restriction_flags'] ?? array());
    echo '</div>';

    echo '<div class="field full"><hr style="margin:4px 0;border:none;border-top:1px solid #e5e7eb;"><strong style="font-size:13px;color:#6b7280;">Brief libre (prioridad máxima)</strong></div>';
    echo '<div class="field full">';
    echo '<label>Brief visual libre del operador</label>';
    echo '<textarea name="operator_brief" rows="3" placeholder="Detalles específicos de imagen con máxima prioridad..." style="width:100%;">' . e($prodParams['operator_brief'] ?? '') . '</textarea>';
    echo '<div class="field-help">Prevalece sobre el descriptor automático y las selecciones de ropa.</div>';
    echo '</div>';

    echo '<div class="field">';
    $checkedAuto = !empty($workflow['auto_regenerate']) ? ' checked' : '';
    echo '<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="auto_regenerate" value="1"' . $checkedAuto . '> Auto-regeneración automática</label>';
    echo '<div class="field-help">Déjalo apagado. Solo actívalo si quieres 3 candidatas extra automáticas.</div>';
    echo '</div>';

    echo '<div class="field full"><hr style="margin:4px 0;border:none;border-top:1px solid #e5e7eb;"><strong style="font-size:13px;color:#6b7280;">Textos publicitarios</strong></div>';
    echo '<div class="field">';
    echo '<label>Tono de textos</label>';
    echo '<select name="copy_tone">';
    foreach (publicista_copy_tone_options() as $value => $label) {
        $selTone = (($copyPack['desired_tone'] ?? 'equilibrado') === $value) ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $selTone . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '<div class="field full">';
    echo '<label>Plataformas destino</label>';
    publicista_render_checkboxes_group('copy_platforms', publicista_copy_platform_options(), $prodParams['copy_platforms'] ?? array('destacamos', 'loquosex'));
    echo '</div>';
    echo '<div class="field full">';
    echo '<label>Ángulos de anuncio preferidos</label>';
    publicista_render_checkboxes_group('copy_angles', publicista_copy_angle_options(), $prodParams['copy_angles'] ?? array());
    echo '</div>';
    echo '<div class="field full">';
    echo '<label>Brief libre para los textos</label>';
    echo '<textarea name="copy_brief" rows="2" placeholder="Apodo, frase característica, servicios concretos, horarios, zona..." style="width:100%;">' . e($prodParams['copy_brief'] ?? '') . '</textarea>';
    echo '</div>';
    field_textarea('services_snapshot', 'Servicios snapshot (opcional)', $selectedJob['services_snapshot'] ?? '', 3);
    field_textarea('tarifas_snapshot', 'Tarifas snapshot', $selectedJob['tarifas_snapshot'] ?? '', 3);
    field_textarea('notas', 'Notas internas', $selectedJob['notas'] ?? '', 3);
    echo '<div class="full"><button class="btn-primary">Guardar configuración del trabajo</button></div>';
    echo '</form>';
    echo '</details>';
    echo '</section>';

    // -----------------------------------------------------------------------
    // SECCIÓN 1: ORIGEN Y GENERACIÓN
    // -----------------------------------------------------------------------
    echo '<section class="panel panel-space" id="publicistaOrigen" style="margin-top:16px;">';
    echo '<div class="branch-panel-head"><h3>① Original base y preparación</h3>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;">';
    if ($isPipelineRunning) {
        echo '<span class="btn-primary" style="opacity:.65;cursor:default;pointer-events:none;">' . e($pipelineWaitingLabel) . '</span>';
        echo '<span class="btn-secondary-mini" style="opacity:.65;cursor:default;pointer-events:none;">Espera a que termine</span>';
    } else {
        echo '<form method="post" enctype="multipart/form-data" class="inline-form">';
        echo '<input type="hidden" name="action" value="run_publicista_image_pipeline">';
        echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
        echo '<input type="file" name="source_image" accept="image/jpeg,image/png,image/webp" style="display:none;" id="pipelineFileInput" onchange="this.form.submit()">';
        echo '<button type="button" class="btn-primary" onclick="document.getElementById(\'pipelineFileInput\').click()">' . e($pipelineButtonLabel) . ' (nueva foto)</button>';
        echo '<button type="submit" class="btn-secondary-mini" title="Reutiliza la foto ya subida y vuelve a generar candidatas y definitivas">Regenerar con la foto actual</button>';
        echo '</form>';
        echo '<form method="post" enctype="multipart/form-data" class="inline-form">';
        echo '<input type="hidden" name="action" value="prepare_publicista_job_engine">';
        echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
        echo '<input type="file" name="source_image" accept="image/jpeg,image/png,image/webp" style="display:none;" id="prepareFileInput" onchange="this.form.submit()">';
        echo '<button type="button" class="btn-secondary-mini" onclick="document.getElementById(\'prepareFileInput\').click()">Solo preparar origen y descriptor</button>';
        echo '</form>';
    }
    echo '</div></div>';

    if (!empty($processing['last_error'])) {
        $lastErrorText = (string)$processing['last_error'];
        $canRetryDescriptor = stripos($lastErrorText, 'OpenAI descriptor falló:') !== false;
        $copyPackErrorHints = array(
            'fase a del pack de textos',
            'pack de textos',
            'copy pack',
            'títulos y textos',
            'titulos y textos',
        );
        $canContinueCopyPack = false;
        foreach ($copyPackErrorHints as $hint) {
            if (stripos($lastErrorText, $hint) !== false) {
                $canContinueCopyPack = true;
                break;
            }
        }
        if (!$canContinueCopyPack) {
            $pipelineSummaryText = trim((string)($pipeline['summary'] ?? ''));
            if ($pipelineSummaryText !== '' && stripos($pipelineSummaryText, 'textos pendientes') !== false) {
                $canContinueCopyPack = true;
            }
        }
        echo '<div class="info-strip" style="margin-top:10px;background:#fef2f2;color:#b91c1c;display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap;">';
        echo '<div><strong>Error pipeline:</strong> ' . e($lastErrorText) . '</div>';
        if (!$isPipelineRunning) {
            if ($canRetryDescriptor) {
                echo '<form method="post" class="inline-form" style="margin:0;">';
                echo '<input type="hidden" name="action" value="run_publicista_image_pipeline">';
                echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
                echo '<button type="submit" class="btn-secondary-mini" title="Reintenta desde descriptor OpenAI y continúa el pipeline completo con la foto actual">Reintentar</button>';
                echo '</form>';
            }
            if ($canContinueCopyPack) {
                echo '<form method="post" class="inline-form" style="margin:0;">';
                echo '<input type="hidden" name="action" value="generate_publicista_copy_pack">';
                echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
                echo '<button type="submit" class="btn-secondary-mini" title="Continúa desde la fase de textos y completa el perfil">Continuar con el proceso</button>';
                echo '</form>';
            }
        }
        echo '</div>';
    }
    if (!empty($pipeline['summary'])) {
        echo '<div class="info-strip" style="margin-top:8px;"><strong>Pipeline:</strong> ' . e($pipeline['summary']) . '</div>';
    }
    if ($isPipelineRunning) {
        $runningText = 'La generación ya está lanzada. No hace falta volver a pulsar ningún botón.';
        if ($pipelineStartedLabel !== '') {
            $runningText .= ' Inicio: ' . $pipelineStartedLabel . '.';
        }
        $runningText .= ' Esta ficha se recargará sola para mostrar avances.';
        echo '<div class="info-strip" style="margin-top:8px;background:#eff6ff;color:#1d4ed8;"><strong>' . e($pipelineWaitingLabel) . '</strong><br>' . e($runningText) . '</div>';
        echo '<script>setTimeout(function(){ window.location.reload(); }, 12000);</script>';
    }

    echo '<div class="cards two" style="margin-top:16px;">';
    // Imágenes de origen
    echo '<div>';
    if (!empty($source['stored_path'])) {
        publicista_render_job_image_card($source['stored_path'], 'Foto original subida');
    }
    if (!empty($localAssets['prepared_square_path'])) {
        publicista_render_job_image_card($localAssets['prepared_square_path'], 'Base 1:1 sin deformar');
    }
    echo '</div>';
    // Descriptor
    echo '<div>';
    if (!empty($descriptorData)) {
        echo '<div class="info-strip" style="margin-bottom:8px;"><strong>Descriptor IA generado</strong></div>';
        publicista_render_publicista_descriptor_summary($descriptorData);
    } else {
        echo '<div class="empty">Descriptor pendiente. Sube la foto y pulsa "Solo preparar origen y descriptor" primero.</div>';
    }
    echo '</div>';
    echo '</div>';

    // Prompt maestro (colapsado)
    if (!empty($promptMaster['text'])) {
        echo '<details style="margin-top:14px;">';
        echo '<summary style="cursor:pointer;font-size:13px;color:#6b7280;">Ver prompt maestro generado · ' . e(format_created_at($promptMaster['built_at'] ?? '')) . '</summary>';
        if (!empty($restrictionLabels)) {
            echo '<div class="info-strip" style="margin-top:8px;"><strong>Restricciones:</strong> ' . e(implode(' · ', $restrictionLabels)) . '</div>';
        }
        echo '<pre style="white-space:pre-wrap;word-break:break-word;margin-top:8px;">' . e($promptMaster['text']) . '</pre>';
        if (!empty($promptMaster['variants']) && is_array($promptMaster['variants'])) {
            echo '<ol style="margin:8px 0 0 18px;">';
            foreach ($promptMaster['variants'] as $variant) {
                echo '<li style="margin-bottom:6px;font-size:12px;color:#6b7280;">' . e($variant) . '</li>';
            }
            echo '</ol>';
        }
        echo '</details>';
    }
    echo '</section>';

    // -----------------------------------------------------------------------
    // SECCIÓN 2: CANDIDATAS GENERADAS
    // -----------------------------------------------------------------------
    echo '<section class="panel panel-space" id="publicistaCandidates">';
    echo '<div class="branch-panel-head"><h3>② Candidatas generadas</h3><span class="summary-badge">' . e((string)count($candidates)) . '</span></div>';
    if (!empty($candidates)) {
        echo '<div class="cards two" style="margin-top:14px;">';
        if (!empty($strategy['window']) && is_array($strategy['window'])) {
            echo '<div class="info-strip" style="margin-bottom:14px;"><strong>Franja de autosubidas</strong><br>' . e((string)$strategy['window']['start']) . ' → ' . e((string)$strategy['window']['end']) . '</div>';
        }
        foreach ($candidates as $cand) {
            $isSelected = !empty($cand['selected']);
            $cardBorder = $isSelected ? 'border:2px solid #6366f1;' : '';
            echo '<div class="panel" style="padding:12px;' . $cardBorder . '">';
            echo '<div class="branch-panel-head"><h4 style="margin:0;">' . e($cand['id'] ?? 'candidate') . ($isSelected ? ' <span style="color:#6366f1;font-size:11px;">★ TOP 4</span>' : '') . '</h4><span class="summary-badge">' . e((string)($cand['effective_score'] ?? 0)) . '</span></div>';
            // Mostrar imagen sin blur: square_path si existe, si no preview_path, si no raw
            $imgToShow = '';
            if (!empty($cand['square_path'])) $imgToShow = $cand['square_path'];
            elseif (!empty($cand['preview_path'])) $imgToShow = $cand['preview_path'];
            elseif (!empty($cand['raw_path'])) $imgToShow = $cand['raw_path'];
            if ($imgToShow !== '') {
                publicista_render_job_image_card($imgToShow, 'Sin blur');
            }
            if (!empty($cand['evaluation']) && is_array($cand['evaluation'])) {
                $ev = $cand['evaluation'];
                echo '<div class="info-strip" style="margin-top:8px;font-size:12px;"><strong>Scores:</strong> Semejanza ' . e((string)($ev['likeness_score'] ?? 0)) . ' · Calidad ' . e((string)($ev['quality_score'] ?? 0)) . ' · Global ' . e((string)($ev['overall_score'] ?? 0)) . '</div>';
                $boolFields = array(
                    'hands_ok' => 'Manos', 'anatomy_ok' => 'Anatomía', 'single_face_clear' => 'Cara única',
                    'body_proportions_match' => 'Complexión', 'skin_texture_ok' => 'Piel real',
                    'mirror_coherent' => 'Espejo OK', 'background_ok' => 'Fondo',
                );
                $pills = array();
                foreach ($boolFields as $field => $lbl) {
                    if (!array_key_exists($field, $ev)) continue;
                    $ok = !empty($ev[$field]);
                    $pills[] = '<span style="font-size:11px;padding:2px 5px;border-radius:4px;background:' . ($ok ? '#dcfce7' : '#fee2e2') . ';color:' . ($ok ? '#15803d' : '#b91c1c') . ';">' . ($ok ? '✓' : '✗') . ' ' . e($lbl) . '</span>';
                }
                if (!empty($pills)) echo '<div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:3px;">' . implode('', $pills) . '</div>';
                if (!empty($ev['issues']) && is_array($ev['issues'])) {
                    echo '<div style="margin-top:6px;font-size:11px;color:#b45309;"><strong>Issues:</strong> ' . e(implode(' · ', $ev['issues'])) . '</div>';
                }
            }
            if (!empty($cand['error'])) {
                echo '<div class="info-strip" style="margin-top:8px;color:#b91c1c;font-size:12px;"><strong>Error:</strong> ' . e($cand['error']) . '</div>';
            }
            echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px;">';
            if ($isPipelineRunning) {
                echo '<span class="info-strip" style="font-size:12px;background:#f8fafc;">Generación en curso: espera a que termine para regenerar candidatas manualmente.</span>';
            } else {
                echo '<button type="button" class="btn-secondary-mini js-open-regenerate-candidate-modal" data-job-id="' . e($selectedJob['id'] ?? '') . '" data-candidate-id="' . e($cand['id'] ?? '') . '">Regenerar esta</button>';
            }
            echo '</div>';
            echo '<details style="margin-top:10px;"><summary style="cursor:pointer;font-size:12px;color:#9ca3af;">Ver prompt</summary><pre style="white-space:pre-wrap;word-break:break-word;font-size:11px;">' . e($cand['prompt'] ?? '') . '</pre></details>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="empty">' . ($isPipelineRunning ? 'La generación está en curso. Esta ficha se actualizará sola cuando entren las candidatas.' : 'Aún no hay candidatas generadas. Pulsa el botón principal de arriba.') . '</div>';
    }
    echo '</section>';

    // -----------------------------------------------------------------------
    // SECCIÓN 3: FINALES DEL PACK — sin blur + con blur manual
    // -----------------------------------------------------------------------
    echo '<section class="panel panel-space" id="publicistaFinals">';
    echo '<div class="branch-panel-head"><h3>③ Definitivas del pack</h3><span class="summary-badge">' . e((string)count($finalImages)) . '/4</span></div>';
    $usesPolloVisualFlow = function_exists('publicista_job_uses_pollo_model') && publicista_job_uses_pollo_model($selectedJob ?? array());
    if (!empty($finalImages)) {
        if ($usesPolloVisualFlow) {
            echo '<p style="font-size:13px;color:#6b7280;margin:4px 0 14px;">Ahora las definitivas arrancan siendo la <strong>misma candidata elegida</strong>. El botón <strong style="color:#2563eb;">Generar propuesta refinada</strong> crea una alternativa aparte y tú decides si la adoptas o si prefieres seguir con la candidata actual. El <strong style="color:#7c3aed;">Blur manual</strong> siempre actúa sobre la definitiva actual.</p>';
        } else {
            echo '<p style="font-size:13px;color:#6b7280;margin:4px 0 14px;">Aquí revisas el flujo final en el orden correcto: primero la <strong>versión limpia</strong> y al lado la <strong>definitiva actual</strong>. El botón <strong style="color:#7c3aed;">Blur manual</strong> abre un editor con elipse e intensidad regulable. La final ya viene refinada en modo premium antes del blur.</p>';
        }
        echo '<div class="cards two">';
        foreach ($finalImages as $finalRow) {
            $fId = $finalRow['id'] ?? '';
            $squareSrc = !empty($finalRow['square_path']) ? $finalRow['square_path'] : (!empty($finalRow['final_path']) ? $finalRow['final_path'] : '');
            $blurSrc = !empty($finalRow['final_path']) ? $finalRow['final_path'] : $squareSrc;
            $proposalSrc = !empty($finalRow['refine_proposal_path']) ? $finalRow['refine_proposal_path'] : '';
            echo '<div class="panel" style="padding:12px;" id="finalCard_' . e($fId) . '">';
            $manualBlurApplied = !empty($finalRow['manual_blur_applied']);
            $manualBlurIntensity = (int)($finalRow['manual_blur_intensity'] ?? 0);
            echo '<div class="branch-panel-head" style="margin-bottom:8px;"><strong>' . e($fId ?: 'Final') . '</strong><div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;"><span class="summary-badge">Score ' . e((string)($finalRow['evaluation_score'] ?? 0)) . '</span><span id="finalBlurStatus_' . e($fId) . '" class="summary-badge" style="background:' . ($manualBlurApplied ? '#ede9fe' : '#f3f4f6') . ';color:' . ($manualBlurApplied ? '#6d28d9' : '#6b7280') . ';">' . e($manualBlurApplied ? ('Blur manual · ' . $manualBlurIntensity . '/20') : 'Sin blur manual') . '</span>';
            if ($usesPolloVisualFlow && $proposalSrc !== '') {
                echo '<span class="summary-badge" style="background:#dbeafe;color:#1d4ed8;">Propuesta refinada pendiente</span>';
            }
            echo '</div></div>';
            echo '<div class="cards two" style="gap:8px;">';
            echo '<div>';
            echo '<div style="font-size:11px;color:#9ca3af;margin-bottom:4px;text-align:center;">Definitiva actual</div>';
            if ($blurSrc !== '') {
                echo '<img id="finalBlurImg_' . e($fId) . '" src="' . e($blurSrc) . '" alt="Definitiva actual" style="width:100%;border-radius:8px;border:1px solid #e5e7eb;display:block;">';
            }
            echo '</div>';
            echo '<div id="finalProposalCol_' . e($fId) . '">';
            echo '<div style="font-size:11px;color:#9ca3af;margin-bottom:4px;text-align:center;">' . ($usesPolloVisualFlow ? 'Propuesta refinada' : 'Candidata elegida') . '</div>';
            $secondSrc = $usesPolloVisualFlow ? $proposalSrc : $squareSrc;
            if ($secondSrc !== '') {
                echo '<img src="' . e($secondSrc) . '" alt="Propuesta" style="width:100%;border-radius:8px;border:1px solid #e5e7eb;display:block;">';
            } else {
                echo '<div style="text-align:center;font-size:12px;color:#9ca3af;padding:20px 0;">' . ($usesPolloVisualFlow ? 'Todavía no hay propuesta refinada para esta final.' : 'Sin imagen adicional') . '</div>';
            }
            echo '</div>';
            echo '</div>';
            if ($usesPolloVisualFlow) {
                echo '<div style="font-size:12px;color:#6b7280;margin-top:10px;">' . ($proposalSrc !== '' ? 'Ya tienes una propuesta refinada lista para comparar. Puedes adoptarla o quedarte con la candidata actual.' : 'Esta definitiva usa la candidata original. Si quieres, genera una propuesta refinada y decide después con cuál quedarte.') . '</div>';
            } else {
                echo '<div style="font-size:12px;color:#6b7280;margin-top:10px;">' . ($manualBlurApplied ? ('Blur manual aplicado con intensidad <strong>' . e((string)$manualBlurIntensity) . '/20</strong>. Puedes reabrir el editor para subirlo o bajarlo.') : 'Todavía sin blur manual. La imagen definitiva está limpia y lista para editar.') . '</div>';
            }

            echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">';
            if ($blurSrc !== '') {
                echo '<button type="button" class="btn-primary js-manual-blur-btn" style="background:#7c3aed;border-color:#7c3aed;" '
                    . 'data-job-id="' . e($selectedJob['id'] ?? '') . '" '
                    . 'data-final-id="' . e($fId) . '" '
                    . 'data-square-src="' . e($blurSrc) . '" '
                    . 'data-intensity="' . e((string)((int)($finalRow['manual_blur_intensity'] ?? 8))) . '">✏ Blur manual</button>';
            }
            echo '<form method="post" class="inline-form">';
            echo '<input type="hidden" name="action" value="refresh_publicista_final_local">';
            echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
            echo '<input type="hidden" name="final_id" value="' . e($fId) . '">';
            echo '<input type="hidden" name="mode" value="reframe">';
            echo '<button class="btn-secondary-mini">' . ($usesPolloVisualFlow ? ($proposalSrc !== '' ? 'Regenerar propuesta refinada' : 'Generar propuesta refinada') : 'Rehacer final premium') . '</button>';
            echo '</form>';
            if ($usesPolloVisualFlow && $proposalSrc !== '') {
                echo '<form method="post" class="inline-form">';
                echo '<input type="hidden" name="action" value="choose_publicista_final_variant">';
                echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
                echo '<input type="hidden" name="final_id" value="' . e($fId) . '">';
                echo '<input type="hidden" name="choice" value="refined">';
                echo '<button class="btn-primary" style="background:#2563eb;border-color:#2563eb;">Usar refinada como definitiva</button>';
                echo '</form>';
                echo '<form method="post" class="inline-form">';
                echo '<input type="hidden" name="action" value="choose_publicista_final_variant">';
                echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
                echo '<input type="hidden" name="final_id" value="' . e($fId) . '">';
                echo '<input type="hidden" name="choice" value="candidate">';
                echo '<button class="btn-secondary-mini">Quedarme con la candidata actual</button>';
                echo '</form>';
            }
            echo '</div>';
            if ($usesPolloVisualFlow && !empty($finalRow['refine_proposal_prompt'])) {
                echo '<details style="margin-top:10px;"><summary style="cursor:pointer;font-size:12px;color:#9ca3af;">Ver prompt de la propuesta refinada</summary><pre style="white-space:pre-wrap;word-break:break-word;font-size:11px;">' . e($finalRow['refine_proposal_prompt']) . '</pre></details>';
            }
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="empty">Finales pendientes. Se crean automáticamente al completar el pipeline de imágenes.</div>';
    }
    echo '</section>';

    echo '<section class="panel panel-space" id="publicistaCopyPack">';
    echo '<div class="section-head"><div><h3>Títulos, textos y export</h3><p>Genera el pack de copy, revisa variantes y exporta todo para publicar.</p></div><div class="section-head-actions">';
    echo '<button type="button" class="btn-primary" onclick="openRegenerateCopyPackModal(\'' . e($selectedJob['id'] ?? '') . '\')">Generar / regenerar textos</button>';
    echo '</div></div>';
    echo '<div class="cards three">';
    echo '<div class="info-strip"><strong>Versión actual</strong><br>' . e($copyPack['current_version_id'] ?? 'Pendiente') . '</div>';
    echo '<div class="info-strip"><strong>Generado</strong><br>' . e(format_created_at($copyPack['generated_at'] ?? '')) . '</div>';
    echo '<div class="info-strip"><strong>Reintentos</strong><br>' . e((string)($copyPack['retry_count'] ?? 0)) . (!empty($currentCopyVersion['generation_phases']) ? ' · Fases: ' . e((string)$currentCopyVersion['generation_phases']) : '') . '</div>';
    echo '</div>';
    if (!empty($copyPack['last_error'])) {
        echo '<div class="info-strip" style="margin-top:10px;color:#b91c1c;"><strong>Último error textos:</strong> ' . e($copyPack['last_error']) . '</div>';
    }

    // Panel de validación automática
    if ($currentCopyVersion && !empty($currentCopyVersion['validation']) && is_array($currentCopyVersion['validation'])) {
        echo '<section class="panel" style="margin-top:14px;border-left:4px solid #6366f1;">';
        echo '<h4>Validación automática por plataforma</h4>';
        $val = $currentCopyVersion['validation'];
        if (!empty($val['titles_check']) && is_array($val['titles_check'])) {
            echo '<details style="margin-top:10px;"><summary>Títulos — estado por plataforma</summary><div style="margin-top:10px;display:grid;gap:6px;">';
            $titleOpts = is_array($currentCopyVersion['title_options'] ?? null) ? $currentCopyVersion['title_options'] : array();
            foreach ($val['titles_check'] as $tc) {
                $idx = (int)($tc['index'] ?? 0);
                $titleText = $titleOpts[$idx] ?? '?';
                $okDest = !empty($tc['ok_destacamos']);
                $okLo = !empty($tc['ok_loquosex']);
                $issues = is_array($tc['issues'] ?? null) ? $tc['issues'] : array();
                $destIcon = $okDest ? '✓' : '✗';
                $destColor = $okDest ? '#15803d' : '#b91c1c';
                $loIcon = $okLo ? '✓' : '✗';
                $loColor = $okLo ? '#15803d' : '#b91c1c';
                echo '<div class="info-strip" style="font-size:12px;">';
                echo '<strong>T' . ($idx + 1) . ':</strong> ' . e($titleText) . '<br>';
                echo '<span style="color:' . $destColor . ';">' . $destIcon . ' destacamos</span> &nbsp; <span style="color:' . $loColor . ';">' . $loIcon . ' loquosex</span>';
                if (!empty($issues)) echo '<br><span style="color:#b45309;">' . e(implode(' · ', $issues)) . '</span>';
                echo '</div>';
            }
            echo '</div></details>';
        }
        if (!empty($val['ads_check']) && is_array($val['ads_check'])) {
            echo '<details style="margin-top:10px;"><summary>Anuncios — estado variante neutra</summary><div style="margin-top:10px;display:grid;gap:6px;">';
            foreach ($val['ads_check'] as $ac) {
                $slot = trim((string)($ac['slot'] ?? 'Anuncio'));
                $neutralOk = !empty($ac['neutral_ok']);
                $suggestiveOk = !empty($ac['suggestive_ok']);
                $neutralIssues = is_array($ac['neutral_issues'] ?? null) ? $ac['neutral_issues'] : array();
                $suggestiveIssues = is_array($ac['suggestive_issues'] ?? null) ? $ac['suggestive_issues'] : array();
                $nIcon = $neutralOk ? '✓' : '✗';
                $nColor = $neutralOk ? '#15803d' : '#b91c1c';
                $sIcon = $suggestiveOk ? '✓' : '✗';
                $sColor = $suggestiveOk ? '#15803d' : '#b91c1c';
                echo '<div class="info-strip" style="font-size:12px;">';
                echo '<strong>' . e($slot) . ':</strong> ';
                echo '<span style="color:' . $nColor . ';">' . $nIcon . ' neutra</span> &nbsp; <span style="color:' . $sColor . ';">' . $sIcon . ' sugerente</span>';
                if (!empty($neutralIssues)) echo '<br><span style="color:#b45309;">Neutra: ' . e(implode(' · ', $neutralIssues)) . '</span>';
                if (!empty($suggestiveIssues)) echo '<br><span style="color:#b45309;">Sugerente: ' . e(implode(' · ', $suggestiveIssues)) . '</span>';
                echo '</div>';
            }
            echo '</div></details>';
        }
        echo '</section>';
    }

    echo '<div class="cards two" style="margin-top:14px;">';
    echo '<section class="panel">';
    echo '<h4>Resumen actual</h4>';
    if ($currentCopyVersion) {
        echo '<div class="info-strip"><strong>Tono:</strong> ' . e(publicista_copy_tone_label($currentCopyVersion['tone'] ?? 'equilibrado')) . '</div>';
        echo '<div class="info-strip" style="margin-top:10px;"><strong>Enfoque:</strong> ' . e($currentCopyVersion['pack_angle'] ?? '-') . '</div>';
        if (!empty($currentCopyVersion['reference_source_label'])) {
            echo '<div class="info-strip" style="margin-top:10px;"><strong>Base externa usada:</strong> ' . e($currentCopyVersion['reference_source_label']);
            if (!empty($currentCopyVersion['reference_examples_count'])) echo ' · ' . e((string)$currentCopyVersion['reference_examples_count']) . ' ejemplos';
            echo '</div>';
        }
        if (!empty($copyPack['current_export_txt_path'])) {
            echo '<div class="info-strip" style="margin-top:10px;"><strong>TXT export:</strong> <a class="mini-link" href="' . e($copyPack['current_export_txt_path']) . '" target="_blank" rel="noopener">Abrir</a></div>';
        }
        if (!empty($copyPack['current_export_json_path'])) {
            echo '<div class="info-strip" style="margin-top:10px;"><strong>JSON export:</strong> <a class="mini-link" href="' . e($copyPack['current_export_json_path']) . '" target="_blank" rel="noopener">Abrir</a></div>';
        }
        if (!empty($currentCopyVersion['title_options']) && is_array($currentCopyVersion['title_options'])) {
            $val = is_array($currentCopyVersion['validation'] ?? null) ? $currentCopyVersion['validation'] : array();
            $titleValMap = array();
            foreach ((array)($val['titles_check'] ?? array()) as $tc) {
                $titleValMap[(int)($tc['index'] ?? -1)] = $tc;
            }
            echo '<details style="margin-top:12px;" open><summary>Ver títulos finales (' . count($currentCopyVersion['title_options']) . ')</summary><div style="display:grid;gap:10px;margin-top:10px;">';
            foreach ($currentCopyVersion['title_options'] as $titleIdx => $title) {
                $tv = $titleValMap[$titleIdx] ?? null;
                echo '<div class="info-strip">';
                echo '<strong>T' . ($titleIdx + 1) . ':</strong> ' . e($title);
                if ($tv !== null) {
                    $ok = !empty($tv['ok_destacamos']);
                    echo ' <span style="font-size:11px;color:' . ($ok ? '#15803d' : '#b91c1c') . ';">' . ($ok ? '✓ destacamos' : '✗ destacamos') . '</span>';
                }
                echo '<form method="post" class="inline-form" style="margin-top:8px;">';
                echo '<input type="hidden" name="action" value="regenerate_publicista_copy_title">';
                echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
                echo '<input type="hidden" name="title_index" value="' . e((string)$titleIdx) . '">';
                echo '<button class="btn-secondary-mini">Regenerar</button>';
                echo '</form>';
                echo '</div>';
            }
            echo '</div></details>';
        }
        if (!empty($currentCopyVersion['title_candidates']) && is_array($currentCopyVersion['title_candidates'])) {
            echo '<details style="margin-top:10px;"><summary>Ver pool fase A (' . count($currentCopyVersion['title_candidates']) . ' candidatos)</summary>';
            echo '<ol style="margin:10px 0 0 18px;">';
            foreach ($currentCopyVersion['title_candidates'] as $tc) {
                echo '<li style="font-size:12px;color:#6b7280;margin-bottom:4px;">' . e((string)$tc) . '</li>';
            }
            echo '</ol></details>';
        }
        if (!empty($copyPack['current_export_text'])) {
            echo '<details style="margin-top:12px;"><summary>Ver export listo para copiar/pegar</summary><pre style="white-space:pre-wrap;word-break:break-word;max-height:420px;overflow:auto;">' . e($copyPack['current_export_text']) . '</pre></details>';
        }
    } else {
        echo '<div class="empty">Todavía no se ha generado el pack de títulos y textos.</div>';
    }
    echo '</section>';
    echo '<section class="panel">';
    echo '<h4>Versiones guardadas</h4>';
    if (!empty($copyPack['versions']) && is_array($copyPack['versions'])) {
        foreach ($copyPack['versions'] as $versionRow) {
            echo '<div class="info-strip" style="margin-top:10px;">';
            echo '<strong>' . e($versionRow['id'] ?? 'version') . '</strong><br>';
            echo e(format_created_at($versionRow['created_at'] ?? '')) . ' · ' . e(publicista_copy_tone_label($versionRow['tone'] ?? 'equilibrado'));
            if (!empty($versionRow['pack_angle'])) echo '<br>' . e($versionRow['pack_angle']);
            if (!empty($versionRow['export_txt_path'])) echo '<br><a class="mini-link" href="' . e($versionRow['export_txt_path']) . '" target="_blank" rel="noopener">TXT</a>';
            if (!empty($versionRow['export_json_path'])) echo ' · <a class="mini-link" href="' . e($versionRow['export_json_path']) . '" target="_blank" rel="noopener">JSON</a>';
            echo '</div>';
        }
    } else {
        echo '<div class="empty">Aún no hay histórico de versiones.</div>';
    }
    echo '</section>';
    echo '</div>';
    if ($currentCopyVersion && !empty($currentCopyVersion['ads']) && is_array($currentCopyVersion['ads'])) {
        echo '<div class="cards three" style="margin-top:14px;">';
        foreach ($currentCopyVersion['ads'] as $ad) {
            echo '<section class="panel">';
            echo '<h4>' . e($ad['slot'] ?? 'Anuncio') . '</h4>';
            echo '<div class="info-strip"><strong>Focus:</strong> ' . e($ad['focus'] ?? '-') . '</div>';
            echo '<div class="info-strip" style="margin-top:10px;"><strong>Hook:</strong> ' . e($ad['short_hook'] ?? '-') . '</div>';
            echo '<form method="post" class="inline-form" style="margin-top:12px;">';
            echo '<input type="hidden" name="action" value="regenerate_publicista_copy_ad">';
            echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
            echo '<input type="hidden" name="slot" value="' . e($ad['slot'] ?? '') . '">';
            echo '<button class="btn-secondary-mini">Regenerar este anuncio</button>';
            echo '</form>';
            echo '<details style="margin-top:12px;"><summary>Variante neutra</summary><div class="info-strip" style="margin-top:10px;"><strong>Título:</strong> ' . e($ad['title_neutral'] ?? '') . '</div><pre style="white-space:pre-wrap;word-break:break-word;">' . e($ad['body_neutral'] ?? '') . '</pre></details>';
            echo '<details style="margin-top:12px;"><summary>Variante sugerente</summary><div class="info-strip" style="margin-top:10px;"><strong>Título:</strong> ' . e($ad['title_suggestive'] ?? '') . '</div><pre style="white-space:pre-wrap;word-break:break-word;">' . e($ad['body_suggestive'] ?? '') . '</pre></details>';
            echo '</section>';
        }
        echo '</div>';
    }

    echo '<section class="panel panel-space">';
    echo '<h3>Estructura técnica</h3>';
    foreach ($assetDirs as $label => $path) {
        echo '<div class="info-strip" style="margin-top:10px;"><strong>' . e($label) . ':</strong> ' . e($path) . '</div>';
    }
    echo '</section>';

    // -----------------------------------------------------------------------
    // Modal de regenerado de textos + candidata + Modal de blur manual
    // -----------------------------------------------------------------------
    echo <<<'HTML'
<div id="regenerateCopyPackModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.72);z-index:10000;align-items:center;justify-content:center;flex-direction:column;">
  <div style="background:#fff;border-radius:14px;padding:20px;max-width:720px;width:96vw;max-height:90vh;overflow:auto;box-shadow:0 8px 40px rgba(0,0,0,0.35);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:10px;">
      <strong style="font-size:16px;">Regenerar textos con conceptos extra</strong>
      <button type="button" onclick="closeRegenerateCopyPackModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#6b7280;">&times;</button>
    </div>
    <p style="margin:0 0 10px;font-size:13px;color:#6b7280;">Se reutiliza el contexto actual de textos y, si quieres, se añaden nuevos conceptos para esta regeneración puntual.</p>
    <form method="post" id="regenerateCopyPackForm">
      <input type="hidden" name="action" value="generate_publicista_copy_pack">
      <input type="hidden" name="id" id="regenCopyPackJobId" value="">
      <label for="regenCopyPackExtraConcepts" style="display:block;font-size:13px;color:#374151;margin-bottom:6px;">Conceptos extra para el prompt (opcional)</label>
      <textarea name="copy_extra_concepts" id="regenCopyPackExtraConcepts" rows="6" maxlength="1200" placeholder="Ejemplo: más enfoque elegante y exclusivo, gancho de novedad, CTA más directo y tono cercano..." style="width:100%;"></textarea>
      <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <button type="submit" class="btn-primary">Generar / regenerar textos</button>
        <button type="button" class="btn-secondary" onclick="closeRegenerateCopyPackModal()">Cancelar</button>
        <span style="font-size:12px;color:#6b7280;">Este texto se aplica solo en esta ejecución; no sustituye tu brief libre guardado.</span>
      </div>
    </form>
  </div>
</div>

<div id="regenerateCandidateModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.72);z-index:10000;align-items:center;justify-content:center;flex-direction:column;">
  <div style="background:#fff;border-radius:14px;padding:20px;max-width:720px;width:96vw;max-height:90vh;overflow:auto;box-shadow:0 8px 40px rgba(0,0,0,0.35);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:10px;">
      <strong style="font-size:16px;">Regenerar candidata con refinado</strong>
      <button type="button" onclick="closeRegenerateCandidateModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#6b7280;">&times;</button>
    </div>
    <p style="margin:0 0 10px;font-size:13px;color:#6b7280;">Se reutiliza el prompt base de esta candidata y se añade tu texto de refinado para regenerarla.</p>
    <form method="post" id="regenerateCandidateForm">
      <input type="hidden" name="action" value="regenerate_publicista_candidate">
      <input type="hidden" name="id" id="regenCandidateJobId" value="">
      <input type="hidden" name="candidate_id" id="regenCandidateId" value="">
      <label for="regenCandidateRefineText" style="display:block;font-size:13px;color:#374151;margin-bottom:6px;">Texto de refinado (opcional)</label>
      <textarea name="refine_text" id="regenCandidateRefineText" rows="6" maxlength="1200" placeholder="Ejemplo: mantener la misma pose y complexión, mejorar manos, cara más nítida, fondo más natural..." style="width:100%;"></textarea>
      <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <button type="submit" class="btn-primary">Regenerar candidata</button>
        <button type="button" class="btn-secondary" onclick="closeRegenerateCandidateModal()">Cancelar</button>
        <span style="font-size:12px;color:#6b7280;">Si esta candidata está en TOP 4, las finales se recomponen automáticamente.</span>
      </div>
    </form>
  </div>
</div>

<div id="manualBlurModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.72);z-index:9999;align-items:center;justify-content:center;flex-direction:column;">
  <div style="background:#fff;border-radius:14px;padding:24px;max-width:760px;width:96vw;max-height:90vh;overflow:auto;box-shadow:0 8px 40px rgba(0,0,0,0.35);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:10px;">
      <strong style="font-size:16px;">Blur manual — marca la zona con una elipse</strong>
      <button type="button" onclick="closeManualBlurModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#6b7280;">&times;</button>
    </div>
    <p style="margin:0 0 12px;font-size:13px;color:#6b7280;">Haz clic y arrastra sobre la imagen para dibujar la <strong>elipse</strong> de la cara. Después ajusta la intensidad y pulsa <strong>Aplicar</strong>.</p>
    <div style="position:relative;display:inline-block;width:100%;">
      <canvas id="manualBlurCanvas" style="width:100%;border-radius:10px;cursor:crosshair;display:block;background:#f8fafc;" width="1024" height="1024"></canvas>
    </div>
    <div style="margin-top:14px;display:flex;gap:14px;flex-wrap:wrap;align-items:end;">
      <label style="display:flex;flex-direction:column;gap:6px;min-width:210px;font-size:13px;color:#374151;">
        Intensidad del blur
        <input type="range" id="manualBlurIntensityRange" min="1" max="20" step="1" value="8" oninput="syncManualBlurIntensity(this.value)">
      </label>
      <label style="display:flex;flex-direction:column;gap:6px;min-width:90px;font-size:13px;color:#374151;">
        Valor
        <input type="number" id="manualBlurIntensityNumber" min="1" max="20" step="1" value="8" oninput="syncManualBlurIntensity(this.value)">
      </label>
      <div style="font-size:12px;color:#6b7280;max-width:280px;">Valores bajos dejan un difuminado suave. Valores altos tapan más la cara.</div>
    </div>
    <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <button type="button" class="btn-primary" id="manualBlurSubmitBtn" onclick="submitManualBlur()">Aplicar blur</button>
      <button type="button" class="btn-secondary" onclick="closeManualBlurModal()">Cancelar</button>
      <span id="manualBlurStatus" style="font-size:13px;color:#6b7280;"></span>
    </div>
  </div>
</div>
<script>
(function() {
  var _mbJobId = '', _mbFinalId = '', _mbEllipse = null, _mbDragging = false, _mbStartX = 0, _mbStartY = 0;
  var _mbImg = new Image();

  window.openRegenerateCopyPackModal = function(jobId) {
    document.getElementById('regenCopyPackJobId').value = jobId || '';
    document.getElementById('regenCopyPackExtraConcepts').value = '';
    document.getElementById('regenerateCopyPackModal').style.display = 'flex';
    document.getElementById('regenCopyPackExtraConcepts').focus();
  };

  window.closeRegenerateCopyPackModal = function() {
    document.getElementById('regenerateCopyPackModal').style.display = 'none';
  };

  window.openRegenerateCandidateModal = function(jobId, candidateId) {
    document.getElementById('regenCandidateJobId').value = jobId || '';
    document.getElementById('regenCandidateId').value = candidateId || '';
    document.getElementById('regenCandidateRefineText').value = '';
    document.getElementById('regenerateCandidateModal').style.display = 'flex';
    document.getElementById('regenCandidateRefineText').focus();
  };

  window.closeRegenerateCandidateModal = function() {
    document.getElementById('regenerateCandidateModal').style.display = 'none';
  };

  window.syncManualBlurIntensity = function(value) {
    var num = parseInt(value, 10);
    if (!isFinite(num)) num = 8;
    num = Math.max(1, Math.min(20, num));
    document.getElementById('manualBlurIntensityRange').value = num;
    document.getElementById('manualBlurIntensityNumber').value = num;
  };

  window.openManualBlurModal = function(jobId, finalId, squareSrc, currentIntensity) {
    _mbJobId = jobId || '';
    _mbFinalId = finalId || '';
    _mbEllipse = null;
    if (!_mbJobId || !_mbFinalId || !squareSrc) {
      document.getElementById('manualBlurStatus').textContent = 'Faltan datos para abrir el editor.';
      return;
    }
    syncManualBlurIntensity(currentIntensity || 8);
    var modal = document.getElementById('manualBlurModal');
    modal.style.display = 'flex';
    document.getElementById('manualBlurStatus').textContent = 'Cargando imagen...';
    document.getElementById('manualBlurSubmitBtn').disabled = true;
    _mbImg = new Image();
    _mbImg.crossOrigin = 'anonymous';
    _mbImg.onload = function() {
      var canvas = document.getElementById('manualBlurCanvas');
      canvas.width = _mbImg.naturalWidth || 1024;
      canvas.height = _mbImg.naturalHeight || 1024;
      drawBlurCanvas();
      document.getElementById('manualBlurStatus').textContent = 'Arrastra para marcar la cara con una elipse.';
      document.getElementById('manualBlurSubmitBtn').disabled = false;
    };
    _mbImg.onerror = function() {
      document.getElementById('manualBlurStatus').textContent = 'Error al cargar la imagen.';
    };
    _mbImg.src = squareSrc + '?t=' + Date.now();
  };

  window.closeManualBlurModal = function() {
    document.getElementById('manualBlurModal').style.display = 'none';
    _mbEllipse = null;
    _mbJobId = '';
    _mbFinalId = '';
  };

  function openManualBlurModalFromButton(btn) {
    if (!btn) return;
    var intensity = parseInt(btn.getAttribute('data-intensity') || '8', 10);
    if (!isFinite(intensity)) intensity = 8;
    window.openManualBlurModal(
      btn.getAttribute('data-job-id') || '',
      btn.getAttribute('data-final-id') || '',
      btn.getAttribute('data-square-src') || '',
      intensity
    );
  }

  function canvasEventCoords(e) {
    var canvas = document.getElementById('manualBlurCanvas');
    var rect = canvas.getBoundingClientRect();
    var scaleX = canvas.width / rect.width;
    var scaleY = canvas.height / rect.height;
    var clientX = e.touches ? e.touches[0].clientX : e.clientX;
    var clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return {
      x: (clientX - rect.left) * scaleX,
      y: (clientY - rect.top) * scaleY
    };
  }

  function drawBlurCanvas() {
    var canvas = document.getElementById('manualBlurCanvas');
    var ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(_mbImg, 0, 0, canvas.width, canvas.height);
    if (_mbEllipse) {
      ctx.save();
      ctx.strokeStyle = '#a855f7';
      ctx.lineWidth = Math.max(3, canvas.width / 180);
      ctx.setLineDash([8, 5]);
      ctx.beginPath();
      ctx.ellipse(_mbEllipse.cx, _mbEllipse.cy, _mbEllipse.rx, _mbEllipse.ry, 0, 0, Math.PI * 2);
      ctx.stroke();
      ctx.globalAlpha = 0.16;
      ctx.fillStyle = '#a855f7';
      ctx.beginPath();
      ctx.ellipse(_mbEllipse.cx, _mbEllipse.cy, _mbEllipse.rx, _mbEllipse.ry, 0, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    }
  }

  var canvas = document.getElementById('manualBlurCanvas');

  function onDown(e) {
    e.preventDefault();
    var pos = canvasEventCoords(e);
    _mbDragging = true;
    _mbStartX = pos.x;
    _mbStartY = pos.y;
    _mbEllipse = null;
  }

  function onMove(e) {
    if (!_mbDragging) return;
    e.preventDefault();
    var pos = canvasEventCoords(e);
    var left = Math.min(_mbStartX, pos.x);
    var top = Math.min(_mbStartY, pos.y);
    var width = Math.abs(pos.x - _mbStartX);
    var height = Math.abs(pos.y - _mbStartY);
    _mbEllipse = {
      left: left,
      top: top,
      width: width,
      height: height,
      cx: left + width / 2,
      cy: top + height / 2,
      rx: width / 2,
      ry: height / 2
    };
    drawBlurCanvas();
  }

  function onUp() {
    _mbDragging = false;
  }

  canvas.addEventListener('mousedown', onDown);
  canvas.addEventListener('mousemove', onMove);
  canvas.addEventListener('mouseup', onUp);
  canvas.addEventListener('mouseleave', onUp);
  canvas.addEventListener('touchstart', onDown, {passive: false});
  canvas.addEventListener('touchmove', onMove, {passive: false});
  canvas.addEventListener('touchend', onUp);

  document.addEventListener('click', function(e) {
    var regenBtn = e.target.closest ? e.target.closest('.js-open-regenerate-candidate-modal') : null;
    if (regenBtn) {
      e.preventDefault();
      openRegenerateCandidateModal(
        regenBtn.getAttribute('data-job-id') || '',
        regenBtn.getAttribute('data-candidate-id') || ''
      );
      return;
    }
    var btn = e.target.closest ? e.target.closest('.js-manual-blur-btn') : null;
    if (!btn) return;
    e.preventDefault();
    openManualBlurModalFromButton(btn);
  });

  window.submitManualBlur = function() {
    if (!_mbEllipse || _mbEllipse.width < 8 || _mbEllipse.height < 8) {
      document.getElementById('manualBlurStatus').textContent = 'Primero dibuja una elipse sobre la zona.';
      return;
    }
    var canvas = document.getElementById('manualBlurCanvas');
    var bx = _mbEllipse.left / canvas.width;
    var by = _mbEllipse.top / canvas.height;
    var bw = _mbEllipse.width / canvas.width;
    var bh = _mbEllipse.height / canvas.height;
    var intensity = parseInt(document.getElementById('manualBlurIntensityNumber').value, 10);
    if (!isFinite(intensity)) intensity = 8;
    intensity = Math.max(1, Math.min(20, intensity));
    syncManualBlurIntensity(intensity);

    document.getElementById('manualBlurSubmitBtn').disabled = true;
    document.getElementById('manualBlurStatus').textContent = 'Aplicando blur...';

    var fd = new FormData();
    fd.append('action', 'apply_publicista_manual_blur');
    fd.append('id', _mbJobId);
    fd.append('final_id', _mbFinalId);
    fd.append('bx', bx.toFixed(6));
    fd.append('by', by.toFixed(6));
    fd.append('bw', bw.toFixed(6));
    fd.append('bh', bh.toFixed(6));
    fd.append('intensity', String(intensity));

    fetch(window.location.href, {method: 'POST', body: fd})
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (data.ok) {
          document.getElementById('manualBlurStatus').textContent = '¡Blur aplicado!';
          var img = document.getElementById('finalBlurImg_' + _mbFinalId);
          if (img && data.final_path) {
            img.src = data.final_path + '?t=' + Date.now();
          }
          var badge = document.getElementById('finalBlurStatus_' + _mbFinalId);
          if (badge) {
            var intensityText = (data.manual_blur_intensity || intensity) + '/20';
            badge.textContent = 'Blur manual · ' + intensityText;
            badge.style.background = '#ede9fe';
            badge.style.color = '#6d28d9';
          }
          setTimeout(function() { closeManualBlurModal(); }, 650);
        } else {
          document.getElementById('manualBlurStatus').textContent = 'Error: ' + (data.error || 'desconocido');
          document.getElementById('manualBlurSubmitBtn').disabled = false;
        }
      })
      .catch(function(err) {
        document.getElementById('manualBlurStatus').textContent = 'Error de red: ' + err;
        document.getElementById('manualBlurSubmitBtn').disabled = false;
      });
  };
})();
</script>
HTML;
}

function publicista_render_restriction_checkboxes($selectedFlags) {
    $selectedFlags = publicista_normalize_restriction_flags($selectedFlags);
    $options = publicista_restriction_flag_options();
    echo '<div class="publicista-restrictions-grid">';
    foreach ($options as $value => $label) {
        $checked = in_array($value, $selectedFlags, true) ? ' checked' : '';
        echo '<label class="publicista-check-item"><input type="checkbox" name="restriction_flags[]" value="' . e($value) . '"' . $checked . '> ' . e($label) . '</label>';
    }
    echo '</div>';
}

function publicista_render_checkboxes_group($fieldName, $options, $selectedValues) {
    $selectedValues = is_array($selectedValues) ? $selectedValues : array();
    echo '<div class="publicista-restrictions-grid">';
    foreach ($options as $value => $label) {
        $checked = in_array($value, $selectedValues, true) ? ' checked' : '';
        echo '<label class="publicista-check-item"><input type="checkbox" name="' . e($fieldName) . '[]" value="' . e($value) . '"' . $checked . '> ' . e($label) . '</label>';
    }
    echo '</div>';
}

function publicista_render_production_params_fields($params) {
    $params = is_array($params) ? $params : array();

    // COLOR
    echo '<div class="field">';
    echo '<label>Color de ropa</label>';
    echo '<select name="outfit_color">';
    $currentColor = $params['color'] ?? 'auto';
    foreach (publicista_outfit_color_options() as $value => $label) {
        $sel = ($currentColor === $value) ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // NIVEL DE PROVOCACIÓN
    echo '<div class="field">';
    echo '<label>Nivel de atrevimiento</label>';
    echo '<select name="outfit_level">';
    $currentLevel = $params['level'] ?? 'sexy';
    foreach (publicista_outfit_level_options() as $value => $label) {
        $sel = ($currentLevel === $value) ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '<div class="field-help">Discreto = apto para webs estrictas. Sexy = por defecto, equilibrado. Muy sugerente = máximo sin desnudo.</div>';
    echo '</div>';

    // AJUSTE
    echo '<div class="field">';
    echo '<label>Ajuste de la prenda</label>';
    echo '<select name="outfit_fit">';
    $currentFit = $params['fit'] ?? 'ajustado';
    foreach (publicista_outfit_fit_options() as $value => $label) {
        $sel = ($currentFit === $value) ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // ESTILO DE PRENDA
    echo '<div class="field full">';
    echo '<label>Estilo de prenda (deja sin marcar para que el modelo elija)</label>';
    $currentStyle = $params['style'] ?? 'auto_random';
    echo '<div class="publicista-restrictions-grid">';
    foreach (publicista_outfit_style_options() as $value => $label) {
        $checked = ($currentStyle === $value) ? ' checked' : '';
        echo '<label class="publicista-check-item"><input type="radio" name="outfit_style" value="' . e($value) . '"' . $checked . '> ' . e($label) . '</label>';
    }
    echo '<label class="publicista-check-item"><input type="radio" name="outfit_style" value=""' . ($currentStyle === '' ? ' checked' : '') . '> Auto (el modelo elige)</label>';
    echo '</div>';
    echo '</div>';

    // COMPLEMENTOS
    echo '<div class="field full">';
    echo '<label>Complementos</label>';
    $currentComplements = is_array($params['complements'] ?? null) ? $params['complements'] : array('zapatillas');
    publicista_render_checkboxes_group('outfit_complements', publicista_outfit_complement_options(), $currentComplements);
    echo '</div>';

    // VARIEDAD DE ROPA
    echo '<div class="field full">';
    echo '<label>Variedad de ropa entre fotos</label>';
    echo '<select name="outfit_variety">';
    $currentVariety = $params['outfit_variety'] ?? 'mixed';
    foreach (publicista_outfit_variety_options() as $value => $label) {
        $sel = ($currentVariety === $value) ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '<div class="field-help">Si activas variedad, las fotos mostrarán 2-3 looks distintos (coherentes entre sí) en lugar de la misma ropa repetida. Más realista.</div>';
    echo '</div>';

    // FONDO Y LUZ
    echo '<div class="field">';
    echo '<label>Tipo de espacio / fondo</label>';
    echo '<select name="setting_type">';
    $currentSetting = $params['setting'] ?? 'random';
    foreach (publicista_setting_type_options() as $value => $label) {
        $sel = ($currentSetting === $value) ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div class="field">';
    echo '<label>Iluminación</label>';
    echo '<select name="lighting_type">';
    $currentLighting = $params['lighting'] ?? 'natural';
    foreach (publicista_lighting_options() as $value => $label) {
        $sel = ($currentLighting === $value) ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // ENCUADRE
    echo '<div class="field">';
    echo '<label>Encuadre preferido</label>';
    echo '<select name="framing_pref">';
    $currentFraming = $params['framing'] ?? 'lejano';
    foreach (publicista_framing_options() as $value => $label) {
        $sel = ($currentFraming === $value) ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // SELFIES
    echo '<div class="field">';
    echo '<label>Formato selfie</label>';
    echo '<select name="selfie_mode">';
    $currentSelfieMode = $params['selfie_mode'] ?? 'mixed';
    foreach (publicista_selfie_mode_options() as $value => $label) {
        $sel = ($currentSelfieMode === $value) ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '<div class="field-help">Si lo activas, solo algunas candidatas saldrán en formato selfie o primer plano cercano; nunca todas.</div>';
    echo '</div>';

    // POSE
    echo '<div class="field">';
    echo '<label>Postura preferida</label>';
    echo '<select name="pose_pref">';
    $currentPose = $params['pose'] ?? 'sugerente';
    foreach (publicista_pose_options() as $value => $label) {
        $sel = ($currentPose === $value) ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // EXPRESIÓN
    echo '<div class="field">';
    echo '<label>Expresión / actitud</label>';
    echo '<select name="expression_pref">';
    $currentExpression = $params['expression'] ?? 'sonrisa';
    foreach (publicista_expression_options() as $value => $label) {
        $sel = ($currentExpression === $value) ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // MAQUILLAJE
    echo '<div class="field">';
    echo '<label>Maquillaje</label>';
    echo '<select name="makeup_pref">';
    $currentMakeup = $params['makeup'] ?? 'natural';
    foreach (publicista_makeup_options() as $value => $label) {
        $sel = ($currentMakeup === $value) ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
}

function publicista_render_job_image_card($relativePath, $label) {
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '') return;
    echo '<div class="publicista-preview-card">';
    echo '<div class="muted" style="margin-bottom:8px;">' . e($label) . '</div>';
    echo '<img src="' . e($relativePath) . '" alt="' . e($label) . '" style="width:100%;max-width:340px;border-radius:12px;border:1px solid #e5e7eb;display:block;">';
    echo '<div class="muted small" style="margin-top:8px;word-break:break-all;">' . e($relativePath) . '</div>';
    echo '</div>';
}

function publicista_render_publicista_descriptor_summary($descriptor) {
    $descriptor = is_array($descriptor) ? $descriptor : array();
    echo '<div class="info-strip"><strong>Guía de parecido:</strong> ' . e($descriptor['similarity_guidance'] ?? '-') . '</div>';
    if (!empty($descriptor['apparent_age'])) {
        echo '<div class="info-strip" style="margin-top:10px;"><strong>Edad aparente:</strong> ' . e($descriptor['apparent_age']) . '</div>';
    }
    echo '<div class="info-strip" style="margin-top:10px;"><strong>Tono de piel / cabello:</strong> ' . e(($descriptor['skin_tone'] ?? '-') . ' · ' . ($descriptor['hair_color'] ?? '-') . ' / ' . ($descriptor['hair_texture'] ?? '-')) . '</div>';
    echo '<div class="info-strip" style="margin-top:10px;"><strong>Complexión / rostro:</strong> ' . e(($descriptor['body_build'] ?? '-') . ' · ' . ($descriptor['face_shape'] ?? '-')) . '</div>';
    echo '<div class="info-strip" style="margin-top:10px;"><strong>Outfit:</strong> ' . e($descriptor['outfit_summary'] ?? '-') . '</div>';
    echo '<div class="info-strip" style="margin-top:10px;"><strong>Pose / expresión:</strong> ' . e(($descriptor['pose_summary'] ?? '-') . ' · ' . ($descriptor['expression'] ?? '-')) . '</div>';
    echo '<div class="info-strip" style="margin-top:10px;"><strong>Fondo / luz:</strong> ' . e(($descriptor['background_summary'] ?? '-') . ' · ' . ($descriptor['lighting_summary'] ?? '-')) . '</div>';
    if (!empty($descriptor['distinguishing_features']) && is_array($descriptor['distinguishing_features'])) {
        echo '<div class="info-strip" style="margin-top:10px;"><strong>Rasgos distintivos:</strong> ' . e(implode(', ', $descriptor['distinguishing_features'])) . '</div>';
    }
    if (!empty($descriptor['quality_notes']) && is_array($descriptor['quality_notes'])) {
        echo '<div class="info-strip" style="margin-top:10px;"><strong>Notas de calidad:</strong> ' . e(implode(' | ', $descriptor['quality_notes'])) . '</div>';
    }
}

function publicista_build_job_guide_data($job) {
    $job = is_array($job) ? $job : array();
    $source = is_array($job['source_image'] ?? null) ? $job['source_image'] : array();
    $localAssets = is_array($job['local_assets'] ?? null) ? $job['local_assets'] : array();
    $descriptor = is_array($job['descriptor'] ?? null) ? $job['descriptor'] : array();
    $descriptorData = is_array($descriptor['data'] ?? null) ? $descriptor['data'] : array();
    $candidates = is_array($job['candidates'] ?? null) ? $job['candidates'] : array();
    $finalImages = is_array($job['final_images'] ?? null) ? $job['final_images'] : array();
    $workflow = function_exists('publicista_job_workflow') ? publicista_job_workflow($job) : array();
    $currentCopyVersion = function_exists('publicista_current_copy_version') ? publicista_current_copy_version($job) : null;
    $hasPendingBatch = function_exists('publicista_pipeline_has_pending_batch') ? publicista_pipeline_has_pending_batch($job) : false;
    $isPipelineRunning = function_exists('publicista_pipeline_is_running') ? publicista_pipeline_is_running($job) : (($job['estado'] ?? '') === 'processing');

    $hasSource = trim((string)($source['stored_path'] ?? '')) !== '';
    $hasPrepared = trim((string)($localAssets['prepared_square_path'] ?? '')) !== '' || !empty($descriptorData);
    $hasCandidates = count($candidates) > 0;
    $finalCount = count($finalImages);
    $hasFullFinals = $finalCount >= 4;
    $hasCopy = !empty($currentCopyVersion);
    $isDefinitive = !empty($workflow['pack_final']);

    $currentStep = 2;
    $headline = 'Todavía falta subir o preparar la foto base.';
    $hint = 'Baja a <strong>Acciones del paso actual</strong>, sube la imagen y pulsa el botón principal.';
    $cta = array('type' => 'anchor', 'href' => '#publicistaQuickActions', 'label' => 'Ir a acciones del paso actual');

    if ($isPipelineRunning) {
        $currentStep = 3;
        $headline = 'La generación ya está en marcha.';
        $hint = 'No vuelvas a pulsar generar. Espera en esta ficha: se recarga sola y te mostrará las candidatas o el error real cuando termine.';
        $cta = array('type' => 'processing_wait', 'label' => 'Generación en curso');
    } elseif ($hasSource && !$hasPrepared) {
        $currentStep = 2;
        $headline = 'Ya hay foto subida, pero aún no está preparada la base técnica del trabajo.';
        $hint = 'Puedes lanzar directamente el pipeline completo o preparar antes el origen si quieres revisar la base 1:1 y el descriptor.';
        $cta = array('type' => 'anchor', 'href' => '#publicistaQuickActions', 'label' => 'Ir a acciones del paso actual');
    } elseif ($hasPendingBatch) {
        $currentStep = 3;
        $headline = 'Hay una generación antigua pendiente de cerrar.';
        $hint = 'No hace falta subir otra foto. Pulsa el botón de regenerar para relanzar el flujo con la foto actual.';
        $cta = array('type' => 'continue_batch', 'label' => 'Relanzar generación');
    } elseif (!$hasCandidates && !$hasFullFinals) {
        $currentStep = 3;
        $headline = 'La base ya está lista; ahora toca generar las 6 candidatas referenciadas.';
        $hint = 'Usa la foto actual del trabajo y lanza el botón principal de imágenes.';
        $cta = array('type' => 'run_pipeline', 'label' => 'Generar 6 candidatas');
    } elseif (!$hasFullFinals) {
        $currentStep = 4;
        $headline = 'Ya hay candidatas, pero el pack visual todavía necesita revisión.';
        $hint = 'Entra en <strong>Candidatas generadas</strong> y pulsa <strong>Regenerar esta</strong> en las flojas. El sistema recompone automáticamente el top 4.';
        $cta = array('type' => 'anchor', 'href' => '#publicistaCandidates', 'label' => 'Ir a candidatas');
    } elseif (!$hasCopy) {
        $currentStep = 5;
        $headline = 'El pack visual ya está listo; ahora toca sacar los textos.';
        $hint = 'Pulsa <strong>Generar / regenerar textos</strong> para crear títulos, anuncios y export TXT/JSON.';
        $cta = array('type' => 'generate_copy', 'label' => 'Generar / regenerar textos');
    } elseif (!$isDefinitive) {
        $currentStep = 6;
        $headline = 'Ya tienes imágenes y textos; solo falta cerrar la versión buena.';
        $hint = 'Si estás conforme con este pack, pulsa el cierre visible de esta ficha para dejarlo marcado como perfil terminado y definitivo.';
        $cta = array('type' => 'mark_definitive', 'label' => 'Cerrar perfil como terminado');
    } else {
        $currentStep = 6;
        $headline = 'Este trabajo ya está cerrado como pack definitivo.';
        $hint = 'Puedes revisar imágenes, textos o duplicarlo como base para otro pack.';
        $cta = array('type' => 'anchor', 'href' => '#publicistaCopyPack', 'label' => 'Ver textos y export');
    }

    $usesPolloVisualFlow = function_exists('publicista_job_uses_pollo_model') && publicista_job_uses_pollo_model($job ?? array());

    $steps = array(
        array(
            'num' => 1,
            'title' => 'Trabajo creado',
            'status' => 'done',
            'body' => 'Ya existe la ficha del trabajo. Desde aquí se controla todo el proceso del pack.'
        ),
        array(
            'num' => 2,
            'title' => 'Foto base y descriptor',
            'status' => $hasPrepared ? 'done' : (($currentStep === 2) ? 'current' : 'pending'),
            'body' => $hasPrepared
                ? 'La foto base ya está preparada en 1:1 sin deformar y el descriptor visual ya existe.'
                : 'Aquí se sube la foto original y se prepara la base técnica: lienzo 1:1 sin deformar y descriptor estructurado.'
        ),
        array(
            'num' => 3,
            'title' => 'Generación de candidatas',
            'status' => ($isPipelineRunning || $hasPendingBatch) ? 'waiting' : (($hasCandidates || $hasFullFinals) ? 'done' : (($currentStep === 3) ? 'current' : 'pending')),
            'body' => $isPipelineRunning
                ? 'La generación está corriendo en segundo plano. No hace falta volver a pulsar ningún botón.'
                : ($hasPendingBatch
                    ? 'Había una generación anterior abierta. Puedes relanzarla con la foto actual cuando quieras.'
                    : (($hasCandidates || $hasFullFinals)
                        ? 'Las candidatas ya fueron generadas usando referencia directa de la foto original.'
                        : 'En este paso se lanzan las 6 candidatas referenciadas desde la foto original.'))
        ),
        array(
            'num' => 4,
            'title' => 'Revisión del pack visual',
            'status' => $hasFullFinals ? 'done' : (($isPipelineRunning || $hasCandidates || $currentStep === 4) ? 'current' : 'pending'),
            'body' => $hasFullFinals
                ? ($usesPolloVisualFlow
                    ? 'Ya tienes 4 definitivas base listas. Desde cada una puedes lanzar un refinado manual opcional y decidir si adoptarlo o no.'
                    : 'Ya tienes 4 finales premium listas y el top visual está montado.')
                : ($isPipelineRunning
                    ? ($usesPolloVisualFlow
                        ? 'Cuando termine la generación, aquí aparecerán las candidatas y después las 4 definitivas base para revisión manual.'
                        : 'Cuando termine la generación, aquí aparecerán las candidatas y después el top 4 visual refinado.')
                    : ($usesPolloVisualFlow
                        ? 'Aquí revisas candidatas, definitivas base y propuestas refinadas manuales hasta cerrar el top visual.'
                        : 'Aquí revisas las candidatas y regeneras solo las que no convencen hasta dejar un top 4 limpio y refinado.'))
        ),
        array(
            'num' => 5,
            'title' => 'Títulos, anuncios y export',
            'status' => $hasCopy ? 'done' : (($hasFullFinals || $currentStep === 5) ? 'current' : 'pending'),
            'body' => $hasCopy
                ? 'El pack de textos ya está generado y listo para copiar o exportar.'
                : 'Con el visual ya bueno, aquí se generan títulos, textos y archivos de exportación.'
        ),
        array(
            'num' => 6,
            'title' => 'Cierre del trabajo',
            'status' => $isDefinitive ? 'done' : (($hasCopy || $currentStep === 6) ? 'current' : 'pending'),
            'body' => $isDefinitive
                ? 'El trabajo está marcado como definitivo.'
                : 'Último paso: cuando todo esté bien, marcas el pack como definitivo.'
        ),
    );

    return array(
        'headline' => $headline,
        'hint' => $hint,
        'cta' => $cta,
        'steps' => $steps,
    );
}

function publicista_render_job_guide_cta($job, $cta) {
    $jobId = trim((string)($job['id'] ?? ''));
    $type = trim((string)($cta['type'] ?? ''));
    if ($jobId === '' || $type === '') return;

    echo '<div class="publicista-guide-actions">';
    if ($type === 'anchor') {
        echo '<a class="btn-primary" href="' . e($cta['href'] ?? '#') . '">' . e($cta['label'] ?? 'Ir') . '</a>';
    } elseif ($type === 'processing_wait') {
        echo '<span class="btn-primary" style="opacity:.65;cursor:default;pointer-events:none;">' . e($cta['label'] ?? 'Generación en curso') . '</span>';
    } elseif ($type === 'continue_batch' || $type === 'run_pipeline') {
        echo '<form method="post" class="inline-form">';
        echo '<input type="hidden" name="action" value="run_publicista_image_pipeline">';
        echo '<input type="hidden" name="id" value="' . e($jobId) . '">';
        echo '<button class="btn-primary">' . e($cta['label'] ?? 'Continuar') . '</button>';
        echo '</form>';
    } elseif ($type === 'generate_copy') {
        echo '<form method="post" class="inline-form">';
        echo '<input type="hidden" name="action" value="generate_publicista_copy_pack">';
        echo '<input type="hidden" name="id" value="' . e($jobId) . '">';
        echo '<button class="btn-primary">' . e($cta['label'] ?? 'Generar textos') . '</button>';
        echo '</form>';
    } elseif ($type === 'mark_definitive') {
        echo '<form method="post" class="inline-form">';
        echo '<input type="hidden" name="action" value="mark_publicista_pack_definitive">';
        echo '<input type="hidden" name="id" value="' . e($jobId) . '">';
        echo '<button class="btn-primary btn-big">' . e($cta['label'] ?? 'Cerrar perfil como terminado') . '</button>';
        echo '</form>';
    }
    echo '</div>';
}

function publicista_render_job_guide_panel($job) {
    $guide = publicista_build_job_guide_data($job);
    echo '<section class="panel panel-space publicista-guide-panel" style="margin-top:16px;">';
    echo '<div class="section-head"><div><h3>Asistente del proceso</h3><p>Te dice en qué punto está este trabajo y cuál es el siguiente botón que tiene sentido pulsar.</p></div></div>';
    echo '<div class="publicista-guide-note"><strong>Ahora mismo:</strong> ' . $guide['headline'] . '<br>' . $guide['hint'] . '</div>';
    publicista_render_job_guide_cta($job, $guide['cta']);
    echo '<div class="publicista-steps-grid">';
    foreach ($guide['steps'] as $step) {
        $status = trim((string)($step['status'] ?? 'pending'));
        $label = 'Pendiente';
        if ($status === 'done') $label = 'Hecho';
        elseif ($status === 'current') $label = 'Ahora';
        elseif ($status === 'waiting') $label = 'Esperando';
        echo '<article class="publicista-step-card is-' . e($status) . '">';
        echo '<div class="publicista-step-top"><span class="publicista-step-num">' . e((string)$step['num']) . '</span><span class="publicista-step-status">' . e($label) . '</span></div>';
        echo '<h4>' . e($step['title']) . '</h4>';
        echo '<p>' . e($step['body']) . '</p>';
        echo '</article>';
    }
    echo '</div>';
    echo '</section>';
}

function render_login_page() {
    $ip = auth_client_ip();
    $isWhitelisted = auth_is_whitelisted_ip($ip);

    echo '<div class="login-wrap">';
    echo '  <div class="login-card">';
    echo '      <div class="brand">LaMami <span>CRM</span></div>';
    echo '      <div class="subtitle center">Acceso al sistema</div>';
    if ($ip !== '') {
        echo '      <div class="login-help">IP detectada: ' . e($ip) . ($isWhitelisted ? ' · en whitelist' : '') . '</div>';
    }
    render_flash();
    echo '      <form method="post">';
    echo '          <input type="hidden" name="action" value="login">';
    echo '          <div class="field"><label>Usuario</label><input type="text" name="username" required></div>';
    echo '          <div class="field"><label>Contraseña</label><input type="password" name="password" required></div>';
    echo '          <button type="submit" class="btn-primary">Entrar</button>';
    echo '      </form>';
    echo '  </div>';
    echo '</div>';
}

function render_sidebar($page) {
    $name = isset($_SESSION['display_name']) ? $_SESSION['display_name'] : 'Usuario';

    $menu = array(
        'dashboard' => 'Dashboard',
        'jostal' => 'Jostal',
        'lamami' => 'LaMami',
        'casawasap' => 'Casawasap',
        'gastos' => 'Gastos',
        'informes' => 'Informes',
        'avisos' => 'AvisosWasap',
        'josue' => 'Josué',
        'bots' => 'Bots',
        'publicista' => 'Publicista',
        'comercial' => 'Comercial',
        'logout' => 'Salir'
    );

    $lamamiPages = array('lamami', 'interesadas', 'clientas', 'lamamibot');

    echo '<aside id="appSidebar" class="sidebar">';
    echo '<div class="brand brand-with-voice">';
    echo '<a class="brand-home" href="index.php?page=dashboard">LaMami <span>CRM</span></a>';
    echo '<button type="button" id="voiceCommandToggleDesktop" class="brand-voice-btn" data-voice-command-toggle aria-expanded="false" aria-controls="voiceCommandPanel" aria-label="Abrir voz CRM" title="Abrir voz CRM">🎙</button>';
    echo '</div>';
    echo '<div class="userbox">Hola, ' . e($name) . '</div>';
    echo '<nav class="nav">';

    foreach ($menu as $slug => $label) {
        $isActive = ($page === $slug);

        if ($slug === 'lamami' && in_array($page, $lamamiPages, true)) {
            $isActive = true;
        }

        $class = $isActive ? 'active' : '';
        echo '<a class="' . $class . '" href="index.php?page=' . e($slug) . '">' . e($label) . '</a>';
    }

    echo '</nav>';
    echo '</aside>';
}

function page_header($title, $subtitle = '') {
    echo '<div class="page-head">';
    echo '<div>';
    echo '<h1>' . e($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p>' . e($subtitle) . '</p>';
    }
    echo '</div>';
    echo '</div>';
}

function dashboard_card($title, $value, $money = false) {
    echo '<section class="panel stat">';
    echo '<div class="stat-label">' . e($title) . '</div>';
    echo '<div class="stat-value ' . ($money ? 'money' : '') . '">' . e($value) . '</div>';
    echo '</section>';
}

function render_dashboard_page() {
    $clientes = storage_read('clientes.json');
    $bots = storage_read('bots.json');
    $dashboardExternalBot = dashboard_external_bot_virtual();
    $lamamibotCfg = lamamibot_get();
    $dashboardLamamibot = array(
        'id' => 'dashboard_lamamibot',
        'nombre_bot' => function_exists('lamamibot_bot_slug') ? lamamibot_bot_slug($lamamibotCfg) : (string)($lamamibotCfg['nombre_bot'] ?? 'lamamibot'),
        'generated_assets' => (array)($lamamibotCfg['generated_assets'] ?? array()),
    );
    $leads = storage_read('leads.json');
    $interesadas = storage_read('interesadas.json');
    $casawasapContactos = storage_read('casawasap_contactos.json');
    $casawasapPagos = storage_read('casawasap_pagos.json');
    $jostalInteresadas = storage_read('jostal_interesadas.json');
    $jostalClientas = storage_read('jostal_clientas.json');
    $jostalLeads = storage_read('jostal_leads.json');
    $jostalVentas = storage_read('jostal_ventas.json');
    $gastos = storage_read('gastos.json');

    $parseTs = function ($value) {
        $raw = trim((string)$value);
        if ($raw === '') return 0;
        return strtotime(str_replace('T', ' ', $raw));
    };

    $monthKeys = array();
    $monthLabels = array();
    $baseMonthTs = strtotime(business_current_month_key() . '-01');
    for ($i = 11; $i >= 0; $i--) {
        $key = date('Y-m', strtotime('-' . $i . ' month', $baseMonthTs));
        $monthKeys[] = $key;
        $monthLabels[] = date('m/y', strtotime($key . '-01'));
    }

    $monthIncomeLamami = array_fill(0, count($monthKeys), 0);
    $monthIncomeCasa = array_fill(0, count($monthKeys), 0);
    $monthIncomeJostal = array_fill(0, count($monthKeys), 0);
    $monthExpenses = array_fill(0, count($monthKeys), 0);
    $monthOpsLamami = array_fill(0, count($monthKeys), 0);
    $monthOpsCasa = array_fill(0, count($monthKeys), 0);
    $monthOpsJostal = array_fill(0, count($monthKeys), 0);
    $monthOpsGastos = array_fill(0, count($monthKeys), 0);

    $monthIndex = array();
    foreach ($monthKeys as $i => $key) {
        $monthIndex[$key] = $i;
    }

    $currentMonth = business_current_month_key();

    $dashboardMonth = request_get('dashboard_month', $currentMonth);
    $dashboardMonthOptions = get_dashboard_activity_months();
    if ($dashboardMonth !== 'all' && !in_array($dashboardMonth, $dashboardMonthOptions, true)) {
        $dashboardMonth = $currentMonth;
    }
    $dashboardMonthLabel = ($dashboardMonth === 'all') ? 'Todo el histórico' : date('m/Y', strtotime($dashboardMonth . '-01'));
    $dashboardPeriodKey = ($dashboardMonth === 'all') ? null : $dashboardMonth;
    $inDashboardPeriod = function ($value) use ($parseTs, $dashboardPeriodKey) {
        if ($dashboardPeriodKey === null) return true;
        $ts = $parseTs($value);
        if (!$ts) return false;
        return business_month_key_from_ts($ts) === $dashboardPeriodKey;
    };

    $dashboardLamamiLeadsCount = 0;
    $dashboardLamamiAltasCount = 0;
    $dashboardLamamiNuevas = 0;
    $dashboardLamamiAtendidas = 0;
    $dashboardLamamiConvertidas = 0;

    $dashboardCasaInteresados = 0;
    $dashboardCasaClientes = 0;
    $dashboardCasaPagosCount = 0;

    $dashboardJostalInteresadas = 0;
    $dashboardJostalClientas = 0;
    $dashboardJostalLeadsCount = 0;
    $dashboardJostalVentasCount = 0;

    $dashboardInternalBotsOn = 0;
    foreach ($bots as $botItem) {
        if (bot_runtime_is_on($botItem)) {
            $dashboardInternalBotsOn++;
        }
    }
    $dashboardLamamibotOn = bot_runtime_is_on($dashboardLamamibot);
    $dashboardExternalBotOn = bot_runtime_is_on($dashboardExternalBot);
    $dashboardBotsOn = $dashboardInternalBotsOn + ($dashboardLamamibotOn ? 1 : 0) + ($dashboardExternalBotOn ? 1 : 0);
    $dashboardBotsTotal = count($bots) + 2;

    $lamamiActivas = 0;
    $lamamiBajas = 0;
    $lamamiIngresosAltas = 0;
    $lamamiIngresosLeads = 0;
    $lamamiAltasMes = 0;
    $lamamiLeadsMes = 0;
    $lamamiNuevas = 0;
    $lamamiAtendidas = 0;
    $lamamiConvertidas = 0;

    foreach ($clientes as $cliente) {
        if (($cliente['estado'] ?? '') === 'alta') $lamamiActivas++;
        if (($cliente['estado'] ?? '') === 'baja') $lamamiBajas++;

        $precioAlta = isset($cliente['precio_alta']) ? (float)$cliente['precio_alta'] : 0;
        $lamamiIngresosAltas += $precioAlta;

        $ts = $parseTs($cliente['fecha_alta'] ?? '');
        if ($ts) {
            $key = business_month_key_from_ts($ts);
            if (isset($monthIndex[$key])) {
                $monthIncomeLamami[$monthIndex[$key]] += $precioAlta;
                $monthOpsLamami[$monthIndex[$key]]++;
            }
            if ($key === $currentMonth) $lamamiAltasMes++;
            if ($inDashboardPeriod($cliente['fecha_alta'] ?? '')) $dashboardLamamiAltasCount++;
        }
    }

    foreach ($leads as $lead) {
        $precioLead = isset($lead['precio_lead']) ? (float)$lead['precio_lead'] : 0;
        $lamamiIngresosLeads += $precioLead;

        $ts = $parseTs($lead['fecha_hora'] ?? '');
        if ($ts) {
            $key = business_month_key_from_ts($ts);
            if (isset($monthIndex[$key])) {
                $monthIncomeLamami[$monthIndex[$key]] += $precioLead;
                $monthOpsLamami[$monthIndex[$key]]++;
            }
            if ($key === $currentMonth) $lamamiLeadsMes++;
            if ($inDashboardPeriod($lead['fecha_hora'] ?? '')) $dashboardLamamiLeadsCount++;
        }
    }

    foreach ($interesadas as $item) {
        $estado = $item['estado'] ?? '';
        if ($estado === 'nueva') $lamamiNuevas++;
        if ($estado === 'atendida') $lamamiAtendidas++;
        if ($estado === 'convertida') $lamamiConvertidas++;

        if ($inDashboardPeriod($item['created_at'] ?? '')) {
            if ($estado === 'nueva') $dashboardLamamiNuevas++;
            if ($estado === 'atendida') $dashboardLamamiAtendidas++;
            if ($estado === 'convertida') $dashboardLamamiConvertidas++;
        }
    }

    $lamamiConversion = count($interesadas) > 0 ? round(($lamamiConvertidas / count($interesadas)) * 100, 1) : 0;
    $lamamiTotal = $lamamiIngresosAltas + $lamamiIngresosLeads;

    $casaInteresados = 0;
    $casaClientes = 0;
    $casaPagosMes = 0;
    $casaTotal = 0;

    foreach ($casawasapContactos as $contacto) {
        if (($contacto['estado'] ?? '') === 'cliente') $casaClientes++;
        else $casaInteresados++;

        if ($inDashboardPeriod($contacto['created_at'] ?? '')) {
            if (($contacto['estado'] ?? '') === 'cliente') $dashboardCasaClientes++;
            else $dashboardCasaInteresados++;
        }
    }

    foreach ($casawasapPagos as $pago) {
        $importe = isset($pago['importe']) ? (float)$pago['importe'] : 0;
        $casaTotal += $importe;

        $ts = $parseTs($pago['fecha_hora'] ?? '');
        if ($ts) {
            $key = business_month_key_from_ts($ts);
            if (isset($monthIndex[$key])) {
                $monthIncomeCasa[$monthIndex[$key]] += $importe;
                $monthOpsCasa[$monthIndex[$key]]++;
            }
            if ($key === $currentMonth) $casaPagosMes++;
            if ($inDashboardPeriod($pago['fecha_hora'] ?? '')) $dashboardCasaPagosCount++;
        }
    }

    $jostalClientasCount = count($jostalClientas);

    $jostalInteresadasCount = 0;
    foreach ($jostalInteresadas as $item) {
        if (($item['estado'] ?? '') !== 'convertida') {
            $jostalInteresadasCount++;
        }
        if (($item['estado'] ?? '') !== 'convertida' && $inDashboardPeriod($item['created_at'] ?? '')) {
            $dashboardJostalInteresadas++;
        }
    }
    foreach ($jostalClientas as $clientaItem) {
        if ($inDashboardPeriod($clientaItem['created_at'] ?? '')) $dashboardJostalClientas++;
    }

    $jostalEnCasaCount = 0;
    foreach ($jostalClientas as $clientaItem) {
        if (jostal_clienta_en_casa($clientaItem)) {
            $jostalEnCasaCount++;
        }
    }

    $jostalLeadsMes = 0;
    $jostalVentasMes = 0;
    $jostalIngresosLeads = 0;
    $jostalIngresosVentas = 0;

    foreach ($jostalLeads as $lead) {
        $precio = isset($lead['precio']) ? (float)$lead['precio'] : 0;
        $jostalIngresosLeads += $precio;

        $ts = $parseTs($lead['created_at'] ?? '');
        if ($ts) {
            $key = business_month_key_from_ts($ts);
            if (isset($monthIndex[$key])) {
                $monthIncomeJostal[$monthIndex[$key]] += $precio;
                $monthOpsJostal[$monthIndex[$key]]++;
            }
            if ($key === $currentMonth) $jostalLeadsMes++;
            if ($inDashboardPeriod($lead['created_at'] ?? '')) $dashboardJostalLeadsCount++;
        }
    }

    foreach ($jostalVentas as $venta) {
        $precio = isset($venta['precio']) ? (float)$venta['precio'] : 0;
        $jostalIngresosVentas += $precio;

        $ts = $parseTs($venta['created_at'] ?? '');
        if ($ts) {
            $key = business_month_key_from_ts($ts);
            if (isset($monthIndex[$key])) {
                $monthIncomeJostal[$monthIndex[$key]] += $precio;
                $monthOpsJostal[$monthIndex[$key]]++;
            }
            if ($key === $currentMonth) $jostalVentasMes++;
            if ($inDashboardPeriod($venta['created_at'] ?? '')) $dashboardJostalVentasCount++;
        }
    }

    $jostalTotal = $jostalIngresosLeads + $jostalIngresosVentas;

    $gastosTotal = 0;
    $gastosMes = 0;

    foreach ($gastos as $gasto) {
        $cantidad = isset($gasto['cantidad']) ? (float)$gasto['cantidad'] : 0;
        $gastosTotal += $cantidad;

        $ts = $parseTs($gasto['created_at'] ?? '');
        if ($ts) {
            $key = business_month_key_from_ts($ts);
            if (isset($monthIndex[$key])) {
                $monthExpenses[$monthIndex[$key]] += $cantidad;
                $monthOpsGastos[$monthIndex[$key]]++;
            }
            if ($key === $currentMonth) $gastosMes++;
        }
    }

    $currentMonthIdx = isset($monthIndex[$currentMonth]) ? $monthIndex[$currentMonth] : (count($monthKeys) - 1);

    if ($dashboardMonth === 'all') {
        $ingresosMesGlobal = $lamamiTotal + $casaTotal + $jostalTotal;
        $gastosMesGlobal = $gastosTotal;
        $beneficioRealMes = $ingresosMesGlobal - $gastosMesGlobal;
        $movimientosMes = array_sum($monthOpsLamami) + array_sum($monthOpsCasa) + array_sum($monthOpsJostal) + array_sum($monthOpsGastos);

        $dashboardLamamiIncome = $lamamiTotal;
        $dashboardCasaIncome = $casaTotal;
        $dashboardJostalIncome = $jostalTotal;
    } else {
        $selectedIdx = isset($monthIndex[$dashboardMonth]) ? $monthIndex[$dashboardMonth] : $currentMonthIdx;

        $ingresosMesGlobal = $monthIncomeLamami[$selectedIdx] + $monthIncomeCasa[$selectedIdx] + $monthIncomeJostal[$selectedIdx];
        $gastosMesGlobal = $monthExpenses[$selectedIdx];
        $beneficioRealMes = $ingresosMesGlobal - $gastosMesGlobal;
        $movimientosMes = $monthOpsLamami[$selectedIdx] + $monthOpsCasa[$selectedIdx] + $monthOpsJostal[$selectedIdx] + $monthOpsGastos[$selectedIdx];

        $dashboardLamamiIncome = $monthIncomeLamami[$selectedIdx];
        $dashboardCasaIncome = $monthIncomeCasa[$selectedIdx];
        $dashboardJostalIncome = $monthIncomeJostal[$selectedIdx];
    }

    $ingresosGlobales = $lamamiTotal + $casaTotal + $jostalTotal;
    $beneficioRealGlobal = $ingresosGlobales - $gastosTotal;

    $monthReal = array();
    foreach ($monthKeys as $i => $k) {
        $monthReal[] = ($monthIncomeLamami[$i] + $monthIncomeCasa[$i] + $monthIncomeJostal[$i]) - $monthExpenses[$i];
    }

    $mixIncomeLamami = array_sum($monthIncomeLamami);
    $mixIncomeCasa = array_sum($monthIncomeCasa);
    $mixIncomeJostal = array_sum($monthIncomeJostal);
    if (($mixIncomeLamami + $mixIncomeCasa + $mixIncomeJostal) <= 0) {
        $mixIncomeLamami = 1;
        $mixIncomeCasa = 0;
        $mixIncomeJostal = 0;
    }

    $recent = array();

    foreach ($leads as $lead) {
        $ts = $parseTs($lead['fecha_hora'] ?? '');
        if (!$ts) continue;
        $recent[] = array(
            'ts' => $ts,
            'branch' => 'LaMami',
            'type' => 'Lead',
            'label' => ($lead['cliente_nombre'] ?? '') !== '' ? $lead['cliente_nombre'] : 'Sin clienta',
            'amount' => isset($lead['precio_lead']) ? (float)$lead['precio_lead'] : 0,
            'link' => !empty($lead['cliente_id']) ? 'index.php?page=clientas&edit=' . urlencode($lead['cliente_id']) : ''
        );
    }

    foreach ($clientes as $cliente) {
        $ts = $parseTs($cliente['fecha_alta'] ?? '');
        if (!$ts) continue;
        $recent[] = array(
            'ts' => $ts,
            'branch' => 'LaMami',
            'type' => 'Alta',
            'label' => $cliente['nombre'] ?? 'Clienta',
            'amount' => isset($cliente['precio_alta']) ? (float)$cliente['precio_alta'] : 0,
            'link' => !empty($cliente['id']) ? 'index.php?page=clientas&edit=' . urlencode($cliente['id']) : ''
        );
    }

    foreach ($casawasapPagos as $pago) {
        $ts = $parseTs($pago['fecha_hora'] ?? '');
        if (!$ts) continue;
        $recent[] = array(
            'ts' => $ts,
            'branch' => 'Casawasap',
            'type' => 'Pago',
            'label' => ($pago['cliente_nombre'] ?? '') !== '' ? $pago['cliente_nombre'] : 'Cliente',
            'amount' => isset($pago['importe']) ? (float)$pago['importe'] : 0,
            'link' => !empty($pago['cliente_id']) ? 'index.php?page=casawasap&edit=' . urlencode($pago['cliente_id']) : ''
        );
    }

    foreach ($jostalLeads as $lead) {
        $ts = $parseTs($lead['created_at'] ?? '');
        if (!$ts) continue;
        $recent[] = array(
            'ts' => $ts,
            'branch' => 'Jostal',
            'type' => 'Lead',
            'label' => ($lead['clienta_nombre'] ?? '') !== '' ? $lead['clienta_nombre'] : 'Clienta',
            'amount' => isset($lead['precio']) ? (float)$lead['precio'] : 0,
            'link' => !empty($lead['clienta_id']) ? 'index.php?page=jostal&tab=clientas&edit=' . urlencode($lead['clienta_id']) : ''
        );
    }

    foreach ($jostalVentas as $venta) {
        $ts = $parseTs($venta['created_at'] ?? '');
        if (!$ts) continue;
        $recent[] = array(
            'ts' => $ts,
            'branch' => 'Jostal',
            'type' => 'Venta',
            'label' => ($venta['descripcion'] ?? '') !== '' ? $venta['descripcion'] : 'Venta',
            'amount' => isset($venta['precio']) ? (float)$venta['precio'] : 0,
            'link' => 'index.php?page=jostal&tab=ventas'
        );
    }

    foreach ($gastos as $gasto) {
        $ts = $parseTs($gasto['created_at'] ?? '');
        if (!$ts) continue;
        $recent[] = array(
            'ts' => $ts,
            'branch' => 'Global',
            'type' => 'Gasto',
            'label' => ($gasto['descripcion'] ?? '') !== '' ? $gasto['descripcion'] : 'Gasto',
            'amount' => -1 * (isset($gasto['cantidad']) ? (float)$gasto['cantidad'] : 0),
            'link' => 'index.php?page=gastos'
        );
    }

    usort($recent, function ($a, $b) {
        return $b['ts'] <=> $a['ts'];
    });
    $recent = array_slice($recent, 0, 12);

    $monthBranchIncome = array(
        'LaMami' => $monthIncomeLamami[$currentMonthIdx],
        'Casawasap' => $monthIncomeCasa[$currentMonthIdx],
        'Jostal' => $monthIncomeJostal[$currentMonthIdx]
    );
    arsort($monthBranchIncome);
    $topBranch = key($monthBranchIncome);

    $bestRealValue = null;
    $bestRealLabel = '-';
    foreach ($monthReal as $i => $value) {
        if ($bestRealValue === null || $value > $bestRealValue) {
            $bestRealValue = $value;
            $bestRealLabel = $monthLabels[$i];
        }
    }

    $prevMonthIdx = max(0, $currentMonthIdx - 1);
    $prevReal = isset($monthReal[$prevMonthIdx]) ? $monthReal[$prevMonthIdx] : 0;
    $deltaText = $prevReal != 0 ? round((($beneficioRealMes - $prevReal) / abs($prevReal)) * 100, 1) . '%' : 'N/A';

    page_header('Dashboard', 'Vista de pájaro del negocio completo');
    render_dashboard_external_bot_panel();

    echo '<section class="panel panel-space">';
    echo '<form method="get" class="toolbar">';
    echo '<input type="hidden" name="page" value="dashboard">';
    echo '<div class="field"><label>Mes resumen</label><select name="dashboard_month">';
    echo '<option value="all"' . ($dashboardMonth === 'all' ? ' selected' : '') . '>Todo el histórico</option>';
    foreach ($dashboardMonthOptions as $m) {
        $sel = ($dashboardMonth === $m) ? ' selected' : '';
        echo '<option value="' . e($m) . '"' . $sel . '>' . e(date('m/Y', strtotime($m . '-01'))) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="field field-btn"><label>&nbsp;</label><button class="btn-primary">Aplicar</button></div>';
    echo '</form>';
    echo '</section>';

    echo '<div class="cards four">';
    dashboard_card('Ingresos · ' . $dashboardMonthLabel, euro($ingresosMesGlobal), true);
    dashboard_card('Gastos · ' . $dashboardMonthLabel, euro($gastosMesGlobal), true);
    dashboard_card('Beneficio real · ' . $dashboardMonthLabel, euro($beneficioRealMes), true);
    dashboard_card('Movimientos · ' . $dashboardMonthLabel, $movimientosMes);
    echo '</div>';

    echo '<div class="dashboard-note">Los gastos son globales para todo el negocio y solo se restan en el beneficio real global.</div>';

   
    echo '<div class="cards three">';
    echo '<section class="panel branch-panel">';
    echo '<div class="branch-panel-head"><h2>LaMami</h2><span class="summary-badge">' . e(euro($dashboardLamamiIncome)) . '</span></div>';
    echo '<div class="branch-kpis">';
    echo '<div><strong>Activas:</strong> ' . e($lamamiActivas) . '</div>';
    echo '<div><strong>Bajas:</strong> ' . e($lamamiBajas) . '</div>';
    echo '<div><strong>Nuevas período:</strong> ' . e($dashboardLamamiNuevas) . '</div>';
    echo '<div><strong>Atendidas período:</strong> ' . e($dashboardLamamiAtendidas) . '</div>';
    echo '<div><strong>Convertidas período:</strong> ' . e($dashboardLamamiConvertidas) . '</div>';
    echo '<div><strong>Conversión global:</strong> ' . e($lamamiConversion) . '%</div>';
    echo '<div><strong>Leads período:</strong> ' . e($dashboardLamamiLeadsCount) . '</div>';
    echo '<div><strong>Altas período:</strong> ' . e($dashboardLamamiAltasCount) . '</div>';
    echo '<div><strong>Ingreso periodo:</strong> ' . e(euro($dashboardLamamiIncome)) . '</div>';
    echo '</div>';
    echo '</section>';

    echo '<section class="panel branch-panel">';
    echo '<div class="branch-panel-head"><h2>Casawasap</h2><span class="summary-badge">' . e(euro($dashboardCasaIncome)) . '</span></div>';
    echo '<div class="branch-kpis">';
    echo '<div><strong>Interesados período:</strong> ' . e($dashboardCasaInteresados) . '</div>';
    echo '<div><strong>Clientes período:</strong> ' . e($dashboardCasaClientes) . '</div>';
    echo '<div><strong>Pagos período:</strong> ' . e($dashboardCasaPagosCount) . '</div>';
    echo '<div><strong>Ingreso periodo:</strong> ' . e(euro($dashboardCasaIncome)) . '</div>';
    echo '<div><strong>Acumulado:</strong> ' . e(euro($casaTotal)) . '</div>';
    echo '</div>';
    echo '</section>';

    echo '<section class="panel branch-panel">';
    echo '<div class="branch-panel-head"><h2>Jostal</h2><span class="summary-badge">' . e(euro($dashboardJostalIncome)) . '</span></div>';
    echo '<div class="branch-kpis">';
    echo '<div><strong>Interesadas período:</strong> ' . e($dashboardJostalInteresadas) . '</div>';
    echo '<div><strong>Clientas período:</strong> ' . e($dashboardJostalClientas) . '</div>';
    echo '<div><strong>Leads período:</strong> ' . e($dashboardJostalLeadsCount) . '</div>';
    echo '<div><strong>Ventas período:</strong> ' . e($dashboardJostalVentasCount) . '</div>';
    echo '<div><strong>Ingreso periodo:</strong> ' . e(euro($dashboardJostalIncome)) . '</div>';
    echo '<div><strong>Acumulado:</strong> ' . e(euro($jostalTotal)) . '</div>';
    echo '</div>';
    echo '</section>';
    echo '</div>'; 



    echo '<div class="cards one dashboard-main-chart-row">';
    echo '<section class="panel">';
    echo '<div class="branch-panel-head"><h2>Ingresos, gastos y beneficio real</h2><span class="summary-badge">12 meses</span></div>';
    echo '<div class="chart-box chart-box-xl"><canvas id="chartRealGlobal12"></canvas></div>';
    echo '</section>';
    echo '</div>';

    echo '<div class="cards four">';
    echo '<section class="panel"><h2>Ingresos por rama (12 meses)</h2><div class="chart-box"><canvas id="chartIncomeByBranch12"></canvas></div></section>';
    echo '<section class="panel"><h2>Peso actual del negocio</h2><div class="chart-box"><canvas id="chartBusinessMix12"></canvas></div></section>';
    echo '<section class="panel"><h2>Actividad por rama (12 meses)</h2><div class="chart-box"><canvas id="chartOps12"></canvas></div></section>';
    echo '<section class="panel dashboard-mini-panel">';
    echo '<div class="branch-panel-head"><h2>Estado operativo</h2><span class="summary-badge">Ahora</span></div>';
    echo '<div class="dashboard-mini-grid">';
    echo '<div><strong>Bots encendidos</strong><span>' . e($dashboardBotsOn . ' / ' . $dashboardBotsTotal) . '</span></div>';
    echo '<div><strong>LamamiBot</strong><span>' . e($dashboardLamamibotOn ? 'Encendido' : 'Apagado') . '</span></div>';
    echo '<div><strong>Bot externo</strong><span>' . e($dashboardExternalBotOn ? 'Encendido' : 'Apagado') . '</span></div>';
    echo '<div><strong>Jostal en casa</strong><span>' . e($jostalEnCasaCount) . '</span></div>';
    echo '<div><strong>LaMami activas</strong><span>' . e($lamamiActivas) . '</span></div>';
    echo '<div><strong>Leads pendientes</strong><span>' . e($lamamiNuevas + $lamamiAtendidas) . '</span></div>';
    echo '</div>';
    echo '</section>';
    echo '</div>';

    echo '<div class="cards two">';
    echo '<section class="panel">';
    echo '<div class="branch-panel-head"><h2>Actividad reciente</h2><span class="summary-badge">' . e(count($recent)) . ' items</span></div>';
    echo '<div class="activity-list">';
    if (empty($recent)) {
        echo '<div class="empty">Todavía no hay movimientos recientes.</div>';
    } else {
        foreach ($recent as $item) {
            echo '<div class="activity-item">';
            echo '<div class="activity-top">';
            echo '<span><strong>' . e($item['branch']) . '</strong> · ' . e($item['type']) . '</span>';
            echo '<span class="' . ($item['amount'] < 0 ? 'amount-negative' : 'amount-positive') . '">' . e(euro(abs($item['amount']))) . ($item['amount'] < 0 ? ' -' : ' +') . '</span>';
            echo '</div>';
            echo '<div class="muted">';
            if ($item['link'] !== '') {
                echo '<a class="mini-link" href="' . e($item['link']) . '">' . e($item['label']) . '</a>';
            } else {
                echo e($item['label']);
            }
            echo ' · ' . e(date('d/m/Y H:i', $item['ts']));
            echo '</div>';
            echo '</div>';
        }
    }
    echo '</div>';
    echo '</section>';

    echo '<section class="panel">';
    echo '<div class="branch-panel-head"><h2>Insights rápidos</h2><span class="summary-badge">12 meses</span></div>';
    echo '<ul class="insight-list">';
    echo '<li>La rama que más factura este mes es <strong>' . e($topBranch) . '</strong>.</li>';
    echo '<li>El mejor mes de beneficio real del último año ha sido <strong>' . e($bestRealLabel) . '</strong>.</li>';
    echo '<li>Beneficio real global acumulado: <strong>' . e(euro($beneficioRealGlobal)) . '</strong>.</li>';
    echo '<li>Variación del beneficio real del mes respecto al mes anterior: <strong>' . e($deltaText) . '</strong>.</li>';
    echo '<li>Leads pendientes de trabajar en LaMami: <strong>' . e($lamamiNuevas + $lamamiAtendidas) . '</strong>.</li>';
    echo '<li>Movimientos de gasto registrados este mes: <strong>' . e($gastosMes) . '</strong>.</li>';
    echo '</ul>';
    echo '</section>';
    echo '</div>';

    echo '<script>';
    echo 'new Chart(document.getElementById("chartIncomeByBranch12"), {type:"bar",data:{labels:' . json_encode($monthLabels) . ',datasets:[';
    echo '{label:"LaMami",data:' . json_encode($monthIncomeLamami) . '},';
    echo '{label:"Casawasap",data:' . json_encode($monthIncomeCasa) . '},';
    echo '{label:"Jostal",data:' . json_encode($monthIncomeJostal) . '}';
    echo ']},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:"bottom"}}}});';

    echo 'new Chart(document.getElementById("chartRealGlobal12"), {type:"line",data:{labels:' . json_encode($monthLabels) . ',datasets:[';
    echo '{label:"Ingresos",data:' . json_encode(array_map(function ($i) use ($monthIncomeLamami, $monthIncomeCasa, $monthIncomeJostal) { return $monthIncomeLamami[$i] + $monthIncomeCasa[$i] + $monthIncomeJostal[$i]; }, array_keys($monthLabels))) . '},';
    echo '{label:"Gastos",data:' . json_encode($monthExpenses) . '},';
    echo '{label:"Beneficio real",data:' . json_encode($monthReal) . '}';
    echo ']},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:"bottom"}}}});';

    echo 'new Chart(document.getElementById("chartBusinessMix12"), {type:"doughnut",data:{labels:["LaMami","Casawasap","Jostal"],datasets:[{data:' . json_encode(array($mixIncomeLamami, $mixIncomeCasa, $mixIncomeJostal)) . '}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:"bottom"}}}});';

    echo 'new Chart(document.getElementById("chartOps12"), {type:"bar",data:{labels:' . json_encode($monthLabels) . ',datasets:[';
    echo '{label:"LaMami",data:' . json_encode($monthOpsLamami) . '},';
    echo '{label:"Casawasap",data:' . json_encode($monthOpsCasa) . '},';
    echo '{label:"Jostal",data:' . json_encode($monthOpsJostal) . '},';
    echo '{label:"Gastos",data:' . json_encode($monthOpsGastos) . '}';
    echo ']},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:"bottom"}}}});';
    echo '</script>';
}

function render_interesadas_page($embedded = false) {
    $items = storage_read('interesadas.json');
    $edit = null;
    $editId = request_get('edit');
    if ($editId !== '') {
        $edit = storage_find_by_id('interesadas.json', $editId);
    }

    $convert = null;
    $convertId = request_get('convert');
    if ($convertId !== '') {
        $convert = storage_find_by_id('interesadas.json', $convertId);
    }

    if (!$embedded) {
        page_header('Interesadas', 'Trabajo comercial visual y rápido');
    }

    if ($convert) {
        echo '<section class="panel panel-space panel-highlight-success">';
        echo '<h2>Convertir interesada a clienta</h2>';
        echo '<p class="muted big-msg">Vamos a rematar esta oportunidad. Completa el alta y conviértela en clienta.</p>';
        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="action" value="convert_interesada">';
        echo '<input type="hidden" name="interesada_id" value="' . e($convert['id']) . '">';
        field_input('nombre', 'Nombre', '', true);
        field_input('telefono', 'Teléfono', isset($convert['telefono']) ? $convert['telefono'] : '');
        field_input('localidad', 'Localidad', '');
        field_input('provincia', 'Provincia', '');
        field_input('fecha_alta', 'Fecha alta', business_today_date(), false, 'date');
        field_input('precio_alta', 'Precio alta', '');
        field_input('modo_pago', 'Modo pago', '');
        field_textarea('notas', 'Notas internas', '', 3);
        echo '<div class="full"><button class="btn-primary btn-big">Convertir en clienta</button></div>';
        echo '</form>';
        echo '</section>';
    }

    echo '<div class="cards two">';
    echo '<section class="panel">';
    echo '<div class="section-head">';
    echo '<div>';
    echo '<h2>' . ($edit ? 'Editar interesada' : 'Nueva interesada') . '</h2>';
    if ($edit) {
        echo '<div class="muted">Created at: <i>' . e(format_created_at(isset($edit['created_at']) ? $edit['created_at'] : '')) . '</i></div>';
    }
    echo '</div>';

    if ($edit && !empty($edit['telefono'])) {
        echo '<div class="section-head-actions">';
        echo '<a class="btn-wa" href="' . e(whatsapp_url($edit['telefono'])) . '" target="_blank" rel="noopener noreferrer">Abrir WhatsApp</a>';
        echo '</div>';
    }
    echo '</div>';

    echo '<form method="post" class="form-grid">';
    echo '<input type="hidden" name="action" value="save_interesada">';
    echo '<input type="hidden" name="id" value="' . e($edit ? $edit['id'] : '') . '">';

    field_input('telefono', 'Teléfono', $edit ? $edit['telefono'] : '', true);
    field_input('movil_origen', 'De qué móvil viene', $edit ? $edit['movil_origen'] : '');

    echo '<div class="field">';
    echo '<label>Estado</label>';
    echo '<select name="estado">';
    $estadoActual = $edit ? $edit['estado'] : 'nueva';
    $opts = array('nueva' => 'Nueva', 'atendida' => 'Atendida', 'convertida' => 'Convertida', 'descartada' => 'Descartada');
    foreach ($opts as $k => $label) {
        $sel = ($estadoActual === $k) ? ' selected' : '';
        echo '<option value="' . e($k) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    field_input('cliente_id', 'ID clienta (solo si ya fue convertida)', $edit ? $edit['cliente_id'] : '');
    field_textarea('observaciones', 'Observaciones', $edit ? $edit['observaciones'] : '', 4);
    echo '<div class="full"><button class="btn-primary">Guardar interesada</button></div>';
    echo '</form>';
    echo '</section>';

    echo '<section class="panel">';
    echo '<h2>Listado</h2>';
    if (empty($items)) {
        echo '<div class="empty">No hay interesadas todavía.</div>';
    } else {
        $cidx = clientes_index();
        $items = sort_desc_by_key($items, 'created_at');
        render_live_filter('#interesadasRows tr[data-filter-text]', 'Buscar interesada...');
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Teléfono</th><th>Origen</th><th>Estado</th><th>Vínculo</th><th>Tiempo hasta alta</th><th>Acciones</th>';
        echo '</tr></thead><tbody id="interesadasRows">';
        foreach ($items as $row) {
            $dias = '-';
            $vinculo = '-';
            $estado = isset($row['estado']) ? $row['estado'] : '';
            $rowClass = 'row-state-' . $estado;

            if (
                $estado === 'convertida' &&
                isset($row['cliente_id']) && isset($cidx[$row['cliente_id']])
            ) {
                $clienta = $cidx[$row['cliente_id']];
                $vinculo = 'Clienta: ' . (isset($clienta['nombre']) ? $clienta['nombre'] : '');
                $diasCalc = days_between_dates(
                    isset($row['created_at']) ? $row['created_at'] : '',
                    isset($clienta['fecha_alta']) ? $clienta['fecha_alta'] : ''
                );
                if ($diasCalc !== null) $dias = $diasCalc . ' días';
            }

            $searchText = strtolower(trim(
                ($row['telefono'] ?? '') . ' ' .
                ($row['movil_origen'] ?? '') . ' ' .
                ($row['observaciones'] ?? '') . ' ' .
                ($estado ?? '')
            ));
            echo '<tr class="' . e($rowClass) . '" data-filter-text="' . e($searchText) . '">';
            echo '<td>';
            crm_render_phone_value((string)($row['telefono'] ?? ''), array('strong' => true));
            echo '<br><i class="muted">(' . e(format_created_at(isset($row['created_at']) ? $row['created_at'] : '')) . ')</i>';
            echo '<br><span class="muted">' . e($row['observaciones']) . '</span>';
            echo '</td>';
            echo '<td>'; crm_render_phone_value((string)($row['movil_origen'] ?? '')); echo '</td>';
            echo '<td><span class="pill state-' . e($estado) . '">' . e(interesada_estado_label($estado)) . '</span></td>';
            echo '<td>' . e($vinculo) . '</td>';
            echo '<td>' . e($dias) . '</td>';
            echo '<td>';
            echo '<div class="action-stack">';
            echo '<a class="mini-link" href="' . e(lamami_tab_url('interesadas', array('edit' => $row['id']))) . '">Editar</a>';

            if ($estado === 'nueva') {
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Marcar como atendida?\')">';
                echo '<input type="hidden" name="action" value="set_interesada_estado">';
                echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                echo '<input type="hidden" name="estado" value="atendida">';
                echo '<button class="btn-warning-mini">Pasar a atendida</button>';
                echo '</form>';
            } elseif ($estado === 'atendida') {
                echo '<a class="mini-link success-link" href="' . e(lamami_tab_url('interesadas', array('convert' => $row['id']))) . '">Convertir</a>';
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Marcar como descartada?\')">';
                echo '<input type="hidden" name="action" value="set_interesada_estado">';
                echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                echo '<input type="hidden" name="estado" value="descartada">';
                echo '<button class="btn-danger-mini">Descartar</button>';
                echo '</form>';
            } elseif ($estado === 'descartada') {
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Reabrir como atendida?\')">';
                echo '<input type="hidden" name="action" value="set_interesada_estado">';
                echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                echo '<input type="hidden" name="estado" value="atendida">';
                echo '<button class="btn-ok-mini">Reactivar</button>';
                echo '</form>';
            }

            echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar interesada?\')">';
            echo '<input type="hidden" name="action" value="delete_interesada">';
            echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
            echo '<button class="btn-danger-mini">Borrar</button>';
            echo '</form>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
    echo '</div>';
}

function render_clientas_page($embedded = false) {
    $items = storage_read('clientes.json');
    $edit = null;
    $editId = request_get('edit');
    if ($editId !== '') {
        $edit = storage_find_by_id('clientes.json', $editId);
    }

    if (!$embedded) {
        page_header('Clientas', 'Solo se crean desde interesadas. Aquí se editan y trabajan los leads.');
    }

    echo '<div class="cards two">';
    echo '<section class="panel">';
    if ($edit) {
        echo '<div class="section-head">';
        echo '<div>';
        echo '<h2>Ficha de clienta</h2>';
        echo '<div class="muted">Created at: <i>' . e(format_created_at(isset($edit['created_at']) ? $edit['created_at'] : '')) . '</i></div>';
        echo '</div>';

        if (!empty($edit['telefono'])) {
            echo '<div class="section-head-actions">';
            echo '<a class="btn-secondary-mini" href="' . e(publicista_tab_url(array('clienta_id' => $edit['id']))) . '">Publicista</a>';
            echo '<a class="btn-wa" href="' . e(whatsapp_url($edit['telefono'])) . '" target="_blank" rel="noopener noreferrer">Abrir WhatsApp</a>';
            echo '</div>';
        } else {
            echo '<div class="section-head-actions">';
            echo '<a class="btn-secondary-mini" href="' . e(publicista_tab_url(array('clienta_id' => $edit['id']))) . '">Publicista</a>';
            echo '</div>';
        }
        echo '</div>';

        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="action" value="save_clienta">';
        echo '<input type="hidden" name="id" value="' . e($edit['id']) . '">';
        field_input('nombre', 'Nombre', $edit['nombre'], true);
        field_input('telefono', 'Teléfono', $edit['telefono']);
        field_input('localidad', 'Localidad', $edit['localidad']);
        field_input('provincia', 'Provincia', $edit['provincia']);
        field_input('fecha_alta', 'Fecha alta', $edit['fecha_alta'], false, 'date');
        field_input('precio_alta', 'Precio alta', $edit['precio_alta']);
        field_input('modo_pago', 'Modo pago', $edit['modo_pago']);
        field_input('estado', 'Estado', $edit['estado']);
        field_textarea('notas', 'Notas', isset($edit['notas']) ? $edit['notas'] : '', 4);
        field_input('ubicacion_maps', 'Ubicación Maps', $edit ? $edit['ubicacion_maps'] : '');
        field_textarea('zona', 'Zona', $edit ? $edit['zona'] : '');
        field_textarea('servicios', 'Servicios', $edit ? $edit['servicios'] : '', 3);
        field_textarea('tarifas', 'Tarifas', $edit ? $edit['tarifas'] : '', 3);

        echo '<div class="full"><button class="btn-primary">Guardar cambios</button></div>';
        echo '</form>';

        $linkedBot = get_clienta_current_bot($edit['id']);

        echo '<div class="mini-actions-bar">';
        if (isset($edit['estado']) && $edit['estado'] === 'alta') {
            if ($linkedBot) {
                echo '<div class="info-strip">No se puede dar de baja mientras tenga vinculado el bot <strong>' . e($linkedBot['nombre_bot'] ?? '') . '</strong>.</div>';
            } else {
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Dar de baja a la clienta?\')">';
                echo '<input type="hidden" name="action" value="baja_clienta">';
                echo '<input type="hidden" name="id" value="' . e($edit['id']) . '">';
                echo '<input type="hidden" name="fecha_baja" value="' . e(business_today_date()) . '">';
                echo '<button class="btn-warning-mini">Dar de baja</button>';
                echo '</form>';
            }
        } else {
            echo '<form method="post" class="inline-form">';
            echo '<input type="hidden" name="action" value="alta_clienta">';
            echo '<input type="hidden" name="id" value="' . e($edit['id']) . '">';
            echo '<button class="btn-ok-mini">Reactivar</button>';
            echo '</form>';
        }
        echo '</div>';

        $intTxt = '-';
        if (!empty($edit['source_interesada_id'])) {
            $int = storage_find_by_id('interesadas.json', $edit['source_interesada_id']);
            if ($int) {
                $intTxt = 'Interesada origen: ' . (isset($int['telefono']) ? $int['telefono'] : $edit['source_interesada_id']);
            }
        }
        echo '<div class="info-strip">' . e($intTxt) . '</div>';

        render_clienta_leads_panel($edit);
    } else {
        echo '<h2>Alta de clientas</h2>';
        echo '<div class="empty">Las clientas no se crean aquí. Deben venir desde interesadas mediante conversión.</div>';
    }
    echo '</section>';

    echo '<section class="panel">';
    echo '<h2>Listado</h2>';
    if (empty($items)) {
        echo '<div class="empty">No hay clientas todavía.</div>';
    } else {
        $items = sort_desc_by_key($items, 'fecha_alta');
        render_live_filter('#clientasRows tr[data-filter-text]', 'Buscar clienta...');
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Nombre</th><th>Estado</th><th>Teléfono</th><th>Alta</th><th>Bot actual</th><th>Acciones</th>';
        echo '</tr></thead><tbody id="clientasRows">';
        foreach ($items as $row) {
            $bot = get_clienta_current_bot($row['id']);
            $botTxt = $bot ? $bot['nombre_bot'] : 'Sin bot';
            $searchText = strtolower(trim(
                ($row['nombre'] ?? '') . ' ' .
                ($row['telefono'] ?? '') . ' ' .
                ($row['localidad'] ?? '') . ' ' .
                ($row['provincia'] ?? '') . ' ' .
                ($botTxt ?? '')
            ));
            echo '<tr data-filter-text="' . e($searchText) . '">';
            echo '<td><strong>' . e($row['nombre']) . '</strong></td>';
            echo '<td><span class="pill state-' . e($row['estado']) . '">' . e(clienta_estado_label($row['estado'])) . '</span></td>';
            echo '<td>';
            crm_render_phone_value((string)($row['telefono'] ?? ''));
            echo '<br><i class="muted">(' . e(format_created_at(isset($row['created_at']) ? $row['created_at'] : '')) . ')</i>';
            echo '</td>';
            echo '<td>' . e($row['fecha_alta']) . '<br><span class="muted">' . e(euro($row['precio_alta'])) . '</span></td>';
            echo '<td>' . e($botTxt) . '</td>';
            echo '<td><a class="mini-link" href="' . e(lamami_tab_url('clientas', array('edit' => $row['id']))) . '">Abrir ficha</a> · <a class="mini-link" href="' . e(publicista_tab_url(array('clienta_id' => $row['id']))) . '">Publicista</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
    echo '</div>';
}

function render_clienta_leads_panel($clienta) {
    $bot = get_clienta_current_bot($clienta['id']);
    echo '<hr class="sep">';
    echo '<div class="lead-zone">';
    echo '<h2>Leads de esta clienta</h2>';

    if (!$bot) {
        echo '<div class="empty">Esta clienta no tiene bot vinculado. Vincula un bot para poder registrar leads.</div>';
        echo '</div>';
        return;
    }

    echo '<div class="money-callout">';
    echo '<div class="money-title">Registrar lead rápido</div>';
    echo '<form method="post" class="lead-quick-inline" onsubmit="return confirmLeadSubmit(this);">';
    echo '<input type="hidden" name="action" value="quick_lead">';
    echo '<input type="hidden" name="cliente_id" value="' . e($clienta['id']) . '">';
    echo '<input type="hidden" name="fecha_hora" value="' . e(today_datetime_local()) . '">';
    echo '<input type="text" name="precio_lead" value="10" class="money-input">';
    echo '<input type="text" name="observaciones" placeholder="Observación opcional" class="money-note">';
    echo '<button class="btn-money">+ Registrar lead</button>';
    echo '</form>';
    echo '<div class="muted">Bot actual vinculado: ' . e($bot['nombre_bot']) . '</div>';
    echo '</div>';

    $from = request_get('client_leads_from', '');
    $to = request_get('client_leads_to', '');
    $leads = get_leads_for_clienta($clienta['id']);
    $leads = filter_rows_between_dates($leads, 'fecha_hora', $from, $to);
    $leads = sort_desc_by_key($leads, 'fecha_hora');
    $totals = lead_totals($leads);

    echo '<form method="get" class="toolbar toolbar-small lead-filter-bar">';
    echo '<input type="hidden" name="page" value="clientas">';
    echo '<input type="hidden" name="edit" value="' . e($clienta['id']) . '">';
    echo '<div class="field"><label>Desde</label><input type="date" name="client_leads_from" value="' . e($from) . '"></div>';
    echo '<div class="field"><label>Hasta</label><input type="date" name="client_leads_to" value="' . e($to) . '"></div>';
    echo '<div class="field field-btn"><label>&nbsp;</label><button class="btn-primary">Filtrar</button></div>';
    echo '</form>';

    if (empty($leads)) {
        echo '<div class="empty">No hay leads para esta clienta con esos filtros.</div>';
    } else {
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Fecha</th><th>Precio</th><th>Clienta</th><th>Bot</th><th>Observación</th><th>Acciones</th>';
        echo '</tr></thead><tbody>';
        foreach ($leads as $lead) {
            echo '<tr>';
            echo '<td>' . e(str_replace('T', ' ', $lead['fecha_hora'])) . '</td>';
            echo '<td><span class="money-chip">' . e(euro($lead['precio_lead'])) . '</span></td>';
            echo '<td>' . e($lead['cliente_nombre']) . '</td>';
            echo '<td>' . e($lead['bot_nombre']) . '</td>';
            echo '<td>' . e(isset($lead['observaciones']) ? $lead['observaciones'] : '') . '</td>';
            echo '<td>';
            echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Seguro que quieres eliminar este lead?\')">';
            echo '<input type="hidden" name="action" value="delete_lead">';
            echo '<input type="hidden" name="id" value="' . e($lead['id']) . '">';
            echo '<input type="hidden" name="clienta_id" value="' . e($clienta['id']) . '">';
            echo '<button class="btn-danger-mini">Eliminar</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    echo '<div class="totals-bar">';
    echo '<div><strong>Total leads:</strong> ' . e($totals['count']) . '</div>';
    echo '<div><strong>Total dinero:</strong> <span class="money-chip big">' . e(euro($totals['money'])) . '</span></div>';
    echo '</div>';
    echo '</div>';
}


function render_bot_runtime_toggle_form($bot, $redirect, $compact = false) {
    $isOn = bot_runtime_is_on($bot);
    $mode = $isOn ? 'stop' : 'start';
    $label = $isOn ? 'Apagar' : 'Encender';
    $class = $isOn ? 'runtime-toggle-btn runtime-on' : 'runtime-toggle-btn runtime-off';
    if ($compact) {
        $class .= ' compact';
    }

    echo '<form method="post" class="inline-form runtime-toggle-form">';
    echo '<input type="hidden" name="action" value="set_bot_runtime_mode">';
    echo '<input type="hidden" name="id" value="' . e($bot['id'] ?? '') . '">';
    echo '<input type="hidden" name="mode" value="' . e($mode) . '">';
    echo '<input type="hidden" name="redirect" value="' . e($redirect) . '">';
    echo '<button class="' . e($class) . '">' . e($label) . '</button>';
    echo '</form>';
}

function render_dashboard_external_bot_panel() {
    $bot = dashboard_external_bot_virtual();
    $isOn = bot_runtime_is_on($bot);
    $toggleMode = $isOn ? 'stop' : 'start';
    $toggleLabel = $isOn ? 'Apagar bot' : 'Encender bot';
    $toggleClass = $isOn ? 'lamamibot-toggle on' : 'lamamibot-toggle off';
    $girlsUrl = bot_girls_panel_url($bot);
    $modePath = bot_mode_file_path($bot);

    echo '<section class="panel dashboard-bot-hero panel-space">';
    echo '<div class="dashboard-bot-topbar">';
    echo '<div class="dashboard-bot-runtime">';
    echo '<div class="dashboard-bot-runtime-title">Bot casa</div>';
    echo '<div class="dashboard-bot-runtime-status">' . bot_runtime_dot_html($bot) . '</div>';
    echo '</div>';
    echo '<div class="dashboard-bot-top-actions">';
    echo '<form method="post" class="inline-form">';
    echo '<input type="hidden" name="action" value="set_dashboard_external_bot_runtime_mode">';
    echo '<input type="hidden" name="mode" value="' . e($toggleMode) . '">';
    echo '<input type="hidden" name="redirect" value="index.php?page=dashboard">';
    echo '<button class="' . e($toggleClass) . '">' . e($toggleLabel) . '</button>';
    echo '</form>';
    if ($girlsUrl !== '') {
        echo '<a class="btn-panel-link" href="' . e($girlsUrl) . '" target="_blank" rel="noopener noreferrer">Abrir panel chicas</a>';
    }
    echo '</div>';
    echo '</div>';
    echo '</section>';
}

function render_bots_page() {
    $items = storage_read('bots.json');
    $lamamiClientas = get_active_clientas();
    $casawasapClientes = get_casawasap_active_clientes();
    $edit = null;
    $editId = request_get('edit');
    if ($editId !== '') {
        $edit = storage_find_by_id('bots.json', $editId);
    }

    $prefillType = '';
    $prefillId = '';
    if (!$edit) {
        list($prefillType, $prefillId) = bot_parse_linked_ref(request_get('linked_ref', ''));
    }

    $selectedType = $edit ? bot_linked_type($edit) : $prefillType;
    $selectedId = $edit ? bot_linked_id($edit) : $prefillId;
    $selectedRef = bot_build_linked_ref($selectedType, $selectedId);

    $prefillProfile = array();
    if ($selectedType !== '' && $selectedId !== '') {
        $prefillProfile = bot_resolve_profile(array(
            'linked_type' => $selectedType,
            'linked_id' => $selectedId,
            'cliente_id' => $selectedType === 'lamami_clienta' ? $selectedId : '',
        ));
    }

    $defaultBotName = $edit ? (string)($edit['nombre_bot'] ?? '') : bot_suggest_name_from_profile($prefillProfile);
    $defaultBotMode = $edit ? (string)($edit['bot_mode'] ?? 'multiple') : (string)($prefillProfile['modo_preferido'] ?? 'multiple');
    if ($defaultBotMode !== 'personal') {
        $defaultBotMode = 'multiple';
    }

    page_header('Bots', 'Bots individuales LaMami y bots para clientes de CasaWasap');

    echo '<div class="cards two">';
    echo '<section class="panel">';
    echo '<div class="section-head">';
    echo '<div>';
    echo '<h2>' . ($edit ? 'Ficha de bot' : 'Nuevo bot') . '</h2>';
    if ($edit) {
        echo '<div class="muted">Created at: <i>' . e(format_created_at(isset($edit['created_at']) ? $edit['created_at'] : '')) . '</i></div>';
        echo '<div class="muted">Estado runtime: ' . bot_runtime_dot_html($edit) . '</div>';
        echo '<div class="muted">Origen: <strong>' . e(bot_linked_source_label($edit)) . '</strong> · Ficha vinculada: <strong>' . e(bot_linked_display_name($edit)) . '</strong></div>';
        echo '<div class="bot-runtime-actions">';
        render_bot_runtime_toggle_form($edit, 'index.php?page=bots&edit=' . urlencode($edit['id'] ?? ''), false);
        $girlsUrl = bot_girls_panel_url($edit);
        if ($girlsUrl !== '') {
            echo '<a class="btn-panel-link" target="_blank" rel="noopener noreferrer" href="' . e($girlsUrl) . '">Panel</a>';
        }
        echo '</div>';

        $modeCandidates = bot_mode_file_candidates($edit);
        if (!empty($modeCandidates)) {
            echo '<div class="muted small">Rutas modo probadas:</div>';
            foreach ($modeCandidates as $modePath) {
                echo '<div class="muted small">· ' . e($modePath) . '</div>';
            }
        }
    }
    echo '</div>';
    echo '</div>';
    echo '<form method="post" class="form-grid">';
    echo '<input type="hidden" name="action" value="save_bot">';
    echo '<input type="hidden" name="id" value="' . e($edit ? $edit['id'] : '') . '">';

    field_select_bot_linked_entity('linked_ref', 'Ficha vinculada', $lamamiClientas, $casawasapClientes, $selectedRef);
    if ($selectedType !== '' && $selectedId !== '' && !empty($prefillProfile)) {
        echo '<div class="full info-strip">';
        echo '<strong>Origen de datos:</strong> ' . e($prefillProfile['source_label'] ?? bot_linked_source_label(array('linked_type' => $selectedType))) . ' · ';
        echo '<strong>Nombre base:</strong> ' . e($prefillProfile['display_name'] ?? '');
        if (!empty($prefillProfile['zona'])) {
            echo '<br><strong>Zona:</strong> ' . e($prefillProfile['zona']);
        }
        if (!empty($prefillProfile['servicios'])) {
            $servicesPreview = (string)$prefillProfile['servicios'];
            if (strlen($servicesPreview) > 220) {
                $servicesPreview = substr($servicesPreview, 0, 220) . '...';
            }
            echo '<br><strong>Servicios:</strong> ' . e($servicesPreview);
        }
        echo '</div>';
    }

    field_input('nombre_bot', 'Nombre bot', $defaultBotName, true);
    field_input('telefono_bot', 'Teléfono bot', $edit ? ($edit['telefono_bot'] ?? '') : '');
    field_input('waha_port', 'WAHA port', $edit ? ($edit['waha_port'] ?? '') : '');
    echo '<div class="field">';
    echo '<label>IP servidor</label>';
    echo '<select name="server_ip">';
    foreach (array(
        '100.117.92.74' => '100.117.92.74 · oficina',
        '100.113.76.93' => '100.113.76.93 · josue',
        '100.76.30.118' => '100.76.30.118 · liveyourdre2',
    ) as $ip => $label) {
        $sel = ((($edit['server_ip'] ?? '100.113.76.93') === $ip) ? ' selected' : '');
        echo '<option value="' . e($ip) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div class="field">';
    echo '<label>Modo bot</label>';
    echo '<select name="bot_mode">';
    foreach (array('multiple' => 'Multiple', 'personal' => 'Personal') as $k => $label) {
        $currentMode = $edit ? ($edit['bot_mode'] ?? 'multiple') : $defaultBotMode;
        $sel = ($currentMode === $k) ? ' selected' : '';
        echo '<option value="' . e($k) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    echo '<input type="hidden" name="estado" value="' . e($edit ? ($edit['estado'] ?? 'activo') : 'activo') . '">';

    echo '<div class="full"><button class="btn-primary">Guardar bot</button></div>';
    echo '</form>';

    if ($edit) {
        render_bot_generated_assets_panel($edit);
        if (bot_linked_type($edit) === 'lamami_clienta') {
            render_bot_leads_panel($edit);
        }
    }

    echo '</section>';

    echo '<section class="panel">';
    echo '<h2>Listado</h2>';
    if (empty($items)) {
        echo '<div class="empty">No hay bots todavía.</div>';
    } else {
        $items = sort_desc_by_key($items, 'created_at');
        render_live_filter('#botsRows tr[data-filter-text]', 'Buscar bot...');
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Bot</th><th>Origen</th><th>Ficha vinculada</th><th>Estado</th><th>Teléfono</th><th>WAHA</th><th>IP</th><th>Modo</th><th>Acciones</th>';
        echo '</tr></thead><tbody id="botsRows">';
        foreach ($items as $row) {
            $linkedName = bot_linked_display_name($row);
            $sourceLabel = bot_linked_source_label($row);
            $runtimeLabel = bot_runtime_label($row);

            $searchText = strtolower(trim(
                ($row['nombre_bot'] ?? '') . ' ' .
                ($row['telefono_bot'] ?? '') . ' ' .
                ($row['waha_port'] ?? '') . ' ' .
                ($row['server_ip'] ?? '') . ' ' .
                ($row['bot_mode'] ?? '') . ' ' .
                ($linkedName ?? '') . ' ' .
                $sourceLabel . ' ' .
                $runtimeLabel
            ));
            echo '<tr data-filter-text="' . e($searchText) . '">';
            echo '<td><strong>' . e($row['nombre_bot']) . '</strong></td>';
            echo '<td>' . e($sourceLabel) . '</td>';
            echo '<td>' . e($linkedName) . '</td>';
            echo '<td>';
            echo '<div class="runtime-cell">' . bot_runtime_dot_html($row);
            render_bot_runtime_toggle_form($row, 'index.php?page=bots', true);
            echo '</div>';
            echo '</td>';
            echo '<td>';
            crm_render_phone_value((string)($row['telefono_bot'] ?? ''));
            echo '<br><i class="muted">(' . e(format_created_at(isset($row['created_at']) ? $row['created_at'] : '')) . ')</i>';
            echo '</td>';
            echo '<td>' . e($row['waha_port']) . '</td>';
            echo '<td>' . e(($row['server_ip'] ?? '') ?: '-') . '</td>';
            echo '<td>' . e($row['bot_mode']) . '</td>';

            echo '<td>';
            echo '<a class="mini-link" href="index.php?page=bots&edit=' . e($row['id']) . '">Abrir ficha</a> ';
            $girlsUrl = bot_girls_panel_url($row);
            if ($girlsUrl !== '') {
                echo '<a class="btn-panel-link compact" target="_blank" rel="noopener noreferrer" href="' . e($girlsUrl) . '">Panel</a> ';
            }
            echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar bot?\')">';
            echo '<input type="hidden" name="action" value="delete_bot">';
            echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
            echo '<button class="btn-danger-mini">Borrar</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
    echo '</div>';
}

function render_lamamibot_page($embedded = false) {
    $cfg = lamamibot_get();
    $telefonos = storage_read('telefonos.json');
    $clientas = get_active_clientas();
    $generated = isset($cfg['generated_assets']) && is_array($cfg['generated_assets'])
        ? $cfg['generated_assets']
        : array();

    $selectedTelefonos = array();
    foreach ((array)($cfg['telefonos_ids'] ?? array()) as $id) {
        $selectedTelefonos[(string)$id] = true;
    }

    $selectedClientas = array();
    foreach ((array)($cfg['clientas_ids'] ?? array()) as $id) {
        $selectedClientas[(string)$id] = true;
    }

    $runtimeBot = array(
        'nombre_bot' => function_exists('lamamibot_bot_slug') ? lamamibot_bot_slug($cfg) : (string)($cfg['nombre_bot'] ?? 'lamamibot'),
        'generated_assets' => $generated,
    );

    $isOn = bot_runtime_is_on($runtimeBot);
    $toggleMode = $isOn ? 'stop' : 'start';
    $toggleLabel = $isOn ? 'Apagar bot' : 'Encender bot';
    $toggleClass = $isOn ? 'lamamibot-toggle on' : 'lamamibot-toggle off';

    if (!$embedded) {
        page_header('LamamiBot', 'Bot núcleo multi-línea / multi-clienta de LaMami');
    }

    echo '<section class="panel panel-space lamamibot-hero">';
    echo '<div class="lamamibot-topbar">';
    echo '<div class="lamamibot-runtime">';
    echo '<div class="lamamibot-runtime-title">Estado runtime</div>';
    echo '<div class="lamamibot-runtime-status">' . bot_runtime_dot_html($runtimeBot) . '</div>';
    echo '<div class="muted lamamibot-route">Ruta principal mode file: <span>' . e(bot_mode_file_path($runtimeBot)) . '</span></div>';
    echo '</div>';

    echo '<div class="lamamibot-top-actions">';
    echo '<a class="btn-primary lamamibot-linkbtn" target="_blank" href="' . e(lamamibot_girlsconf_base_url()) . '">Abrir panel de chicas</a>';

    echo '<form method="post" class="inline-form">';
    echo '<input type="hidden" name="action" value="set_lamamibot_runtime_mode">';
    echo '<input type="hidden" name="mode" value="' . e($toggleMode) . '">';
    echo '<button class="' . e($toggleClass) . '">' . e($toggleLabel) . '</button>';
    echo '</form>';
    echo '</div>';
    echo '</div>';

    if (!empty($generated['warnings']) && is_array($generated['warnings'])) {
        echo '<div class="lamamibot-warning-strip">';
        foreach ($generated['warnings'] as $warning) {
            echo '<div class="lamamibot-warning-item">' . e((string)$warning) . '</div>';
        }
        echo '</div>';
    }

    echo '</section>';

    echo '<div class="cards two">';

    echo '<section class="panel">';
    echo '<div class="section-head">';
    echo '<div>';
    echo '<h2>Configuración del núcleo</h2>';
    echo '<div class="muted">Guardar esta ficha sincroniza girlsconf_lamamidef y regenera automáticamente todo el pack del bot.</div>';
    echo '</div>';
    echo '</div>';

    echo '<form method="post" class="form-grid">';
    echo '<input type="hidden" name="action" value="save_lamamibot">';

    echo '<div class="field full">';
    echo '<label>Líneas de teléfono vinculadas</label>';
    if (empty($telefonos)) {
        echo '<div class="empty">No hay teléfonos creados en la sección Josué → Teléfonos.</div>';
    } else {
        echo '<div class="selector-box">';
        foreach ($telefonos as $tel) {
            $id = trim((string)($tel['id'] ?? ''));
            $nombre = trim((string)($tel['nombre'] ?? ''));
            $tfono = trim((string)($tel['tfono'] ?? ''));
            $wahaPort = trim((string)($tel['waha_port'] ?? ''));
            $label = $nombre !== '' ? $nombre : ($tfono !== '' ? $tfono : 'Teléfono');
            $checked = isset($selectedTelefonos[$id]) ? ' checked' : '';

            echo '<label class="check-row">';
            echo '<input type="checkbox" name="telefonos_ids[]" value="' . e($id) . '"' . $checked . '>';
            echo '<span><strong>' . e($label) . '</strong>';
            if ($tfono !== '') {
                echo ' · ' . e($tfono);
            }
            if ($wahaPort !== '') {
                echo ' <span class="muted">(WAHA ' . e($wahaPort) . ')</span>';
            } else {
                echo ' <span class="muted">(sin WAHA port)</span>';
            }
            echo '</span>';
            echo '</label>';
        }
        echo '</div>';
    }
    echo '</div>';

    echo '<div class="field full">';
    echo '<label>Clientas LaMami vinculadas</label>';
    if (empty($clientas)) {
        echo '<div class="empty">No hay clientas activas disponibles.</div>';
    } else {
        echo '<div class="selector-box">';
        foreach ($clientas as $cli) {
            $id = trim((string)($cli['id'] ?? ''));
            $nombre = trim((string)($cli['nombre'] ?? 'Clienta'));
            $telefono = trim((string)($cli['telefono'] ?? ''));
            $checked = isset($selectedClientas[$id]) ? ' checked' : '';

            echo '<label class="check-row">';
            echo '<input type="checkbox" name="clientas_ids[]" value="' . e($id) . '"' . $checked . '>';
            echo '<span><strong>' . e($nombre) . '</strong>';
            if ($telefono !== '') {
                echo ' <span class="muted">· ' . e($telefono) . '</span>';
            }
            echo '</span>';
            echo '</label>';
        }
        echo '</div>';
    }
    echo '</div>';

    echo '<div class="full lamamibot-actions">';
    echo '<button type="submit" class="btn-primary btn-big">Guardar y regenerar pack completo</button>';
    echo '</div>';

    echo '<div class="field full">';
    echo '<label>Destino girlsconf</label>';
    echo '<div class="empty">' . e(lamamibot_girlsconf_json_path()) . '</div>';
    echo '</div>';

    echo '</form>';
    echo '</section>';

    echo '<section class="panel">';
    echo '<div class="section-head">';
    echo '<div>';
    echo '<h2>Resumen del núcleo</h2>';
    echo '<div class="muted">Información principal y salidas listas para copiar.</div>';
    echo '</div>';
    echo '</div>';

    echo '<div class="generated-box">';
    echo '<div class="generated-box-head"><h3>Resumen guardado</h3></div>';
    echo '<div class="muted">';
    echo 'Líneas vinculadas: ' . e(count((array)($cfg['telefonos_ids'] ?? array()))) . '<br>';
    echo 'Clientas vinculadas: ' . e(count((array)($cfg['clientas_ids'] ?? array()))) . '<br>';
    echo 'Última sync: ' . e((string)($cfg['last_sync_at'] ?? '')) . '<br>';
    echo 'Girlsconf URL: ' . e((string)($cfg['girlsconf_base_url'] ?? lamamibot_girlsconf_base_url()));
    echo '</div>';
    echo '</div>';

    echo '<div class="generated-box">';
    echo '<div class="generated-box-head"><h3>Resumen última sincronización</h3></div>';
    echo '<div class="muted">' . e((string)($cfg['last_sync_summary'] ?? 'Aún no se ha sincronizado nada.')) . '</div>';
    echo '</div>';

    if (!empty($generated)) {
        echo '<div class="generated-box">';
        echo '<div class="generated-box-head"><h3>Resumen última generación</h3></div>';
        echo '<div class="muted">';
        echo 'Generado: ' . e((string)($generated['generated_at'] ?? '')) . '<br>';
        echo 'Slug técnico: ' . e((string)($generated['bot_slug'] ?? '')) . '<br>';
        echo 'Girls JSON: ' . e((string)($generated['girls_json_url'] ?? '')) . '<br>';
        echo 'Memoria mixta: ' . e((string)($generated['session_memory_mix_path'] ?? '')) . '<br>';
        echo 'Runtime mode: ' . e((string)($generated['runtime_mode'] ?? '')) . '<br>';
        echo 'Resumen: ' . e((string)($generated['summary'] ?? ''));
        echo '</div>';
        echo '</div>';

        if (!empty($generated['bot_mode_paths']) && is_array($generated['bot_mode_paths'])) {
            echo '<div class="generated-box">';
            echo '<div class="generated-box-head"><h3>Mode files preparados</h3></div>';
            echo '<div class="muted"><ul style="margin:0; padding-left:18px;">';
            foreach ($generated['bot_mode_paths'] as $path) {
                echo '<li>' . e((string)$path) . '</li>';
            }
            echo '</ul></div>';
            echo '</div>';
        }
    }

    render_generated_text_box(
        'Texto1 · JSON real del bot',
        'lamamibot_texto1',
        (string)($generated['texto1'] ?? '')
    );
    render_generated_text_box(
        'Texto2 · JSON del mode switch',
        'lamamibot_texto2',
        (string)($generated['texto2'] ?? '')
    );
    render_generated_text_box(
        'Texto3 · docker-compose.yml',
        'lamamibot_texto3',
        (string)($generated['texto3'] ?? '')
    );
    render_generated_text_box(
        'Texto5 · Enlaces encender / apagar',
        'lamamibot_texto5',
        "Encender: " . (string)($generated['texto5_start'] ?? '') . "\n" .
        "Apagar: " . (string)($generated['texto5_stop'] ?? '')
    );

    echo '</section>';

    echo '</div>';
}

function render_bot_leads_panel($bot) {
    echo '<hr class="sep">';
    echo '<div class="lead-zone">';
    echo '<h2>Leads históricos de este bot</h2>';

    $from = request_get('bot_leads_from', '');
    $to = request_get('bot_leads_to', '');
    $leads = get_leads_for_bot($bot['id']);
    $leads = filter_rows_between_dates($leads, 'fecha_hora', $from, $to);
    $leads = sort_desc_by_key($leads, 'fecha_hora');
    $totals = lead_totals($leads);

    echo '<form method="get" class="toolbar toolbar-small lead-filter-bar">';
    echo '<input type="hidden" name="page" value="bots">';
    echo '<input type="hidden" name="edit" value="' . e($bot['id']) . '">';
    echo '<div class="field"><label>Desde</label><input type="date" name="bot_leads_from" value="' . e($from) . '"></div>';
    echo '<div class="field"><label>Hasta</label><input type="date" name="bot_leads_to" value="' . e($to) . '"></div>';
    echo '<div class="field field-btn"><label>&nbsp;</label><button class="btn-primary">Filtrar</button></div>';
    echo '</form>';

    if (empty($leads)) {
        echo '<div class="empty">No hay leads para este bot con esos filtros.</div>';
    } else {
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Fecha</th><th>Precio</th><th>Clienta</th><th>Bot</th><th>Observación</th>';
        echo '</tr></thead><tbody>';
        foreach ($leads as $lead) {
            echo '<tr>';
            echo '<td>' . e(str_replace('T', ' ', $lead['fecha_hora'])) . '</td>';
            echo '<td><span class="money-chip">' . e(euro($lead['precio_lead'])) . '</span></td>';
            echo '<td>' . e($lead['cliente_nombre']) . '</td>';
            echo '<td>' . e($lead['bot_nombre']) . '</td>';
            echo '<td>' . e(isset($lead['observaciones']) ? $lead['observaciones'] : '') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }

    echo '<div class="totals-bar">';
    echo '<div><strong>Total leads:</strong> ' . e($totals['count']) . '</div>';
    echo '<div><strong>Total dinero:</strong> <span class="money-chip big">' . e(euro($totals['money'])) . '</span></div>';
    echo '</div>';
    echo '</div>';
}

function render_bot_generated_assets_panel($bot) {
    echo '<hr class="sep">';
    echo '<div class="lead-zone">';
    echo '<h2>Generador de textos del bot</h2>';

    $profile = bot_resolve_profile($bot);
    $sourceLabel = bot_linked_source_label($bot);
    $displayName = trim((string)($profile['display_name'] ?? ''));

    echo '<div class="info-strip">';
    echo '<strong>Bot:</strong> ' . e($bot['nombre_bot']);
    echo '<br>';
    echo '<strong>Origen:</strong> ' . e($sourceLabel);
    echo '<br>';
    echo '<strong>Ficha vinculada:</strong> ' . e($displayName !== '' ? $displayName : 'Sin vincular');
    if (!empty($profile['zona'])) {
        echo '<br><strong>Zona:</strong> ' . e($profile['zona']);
    }
    echo '</div>';

    echo '<form method="post" class="inline-form" style="margin-top:14px;">';
    echo '<input type="hidden" name="action" value="generate_bot_assets">';
    echo '<input type="hidden" name="id" value="' . e($bot['id']) . '">';
    echo '<button class="btn-primary btn-big">Generar / regenerar pack del bot</button>';
    echo '</form>';

    if (empty($bot['generated_assets']) || !is_array($bot['generated_assets'])) {
        echo '<div class="empty">Todavía no se ha generado este pack.</div>';
        echo '</div>';
        return;
    }

    $g = $bot['generated_assets'];
    echo '<div class="muted" style="margin-top:12px;">Última generación: ' . e($g['generated_at'] ?? '') . '</div>';
    if (!empty($g['summary'])) {
        echo '<div class="info-strip" style="margin-top:10px;"><strong>Resumen:</strong> ' . e($g['summary']) . '</div>';
    }
    if (!empty($g['warnings']) && is_array($g['warnings'])) {
        echo '<div class="info-strip" style="margin-top:10px;"><strong>Avisos:</strong><br>';
        foreach ($g['warnings'] as $warning) {
            echo '· ' . e((string)$warning) . '<br>';
        }
        echo '</div>';
    }

    $texto4Title = (bot_linked_type($bot) === 'lamami_clienta')
        ? 'Texto4 · Enlace panel de chicas'
        : 'Texto4 · Enlace panel base del bot';

    render_generated_text_box('Texto1 · JSON del bot', 'bot_texto1', $g['texto1'] ?? '');
    render_generated_text_box('Texto2 · JSON del mode switch', 'bot_texto2', $g['texto2'] ?? '');
    render_generated_text_box('Texto3 · docker-compose.yml', 'bot_texto3', $g['texto3'] ?? '');
    render_generated_text_box($texto4Title, 'bot_texto4', $g['texto4'] ?? '');
    render_generated_text_box(
        'Texto5 · Enlaces encender / apagar',
        'bot_texto5',
        "Encender: " . ($g['texto5_start'] ?? '') . "
" .
        "Apagar: " . ($g['texto5_stop'] ?? '')
    );

    echo '</div>';
}

function render_generated_text_box($title, $key, $value) {
    echo '<div class="generated-box">';
    echo '<div class="generated-box-head">';
    echo '<h3>' . e($title) . '</h3>';
    echo '<button type="button" class="mini-link js-copy-snippet" data-copy-target="' . e($key) . '">Copiar</button>';
    echo '</div>';
    echo '<textarea id="' . e($key) . '" class="generated-textarea" readonly rows="10">' . e($value) . '</textarea>';
    echo '</div>';
}

function render_informes_page() {
    $clientes = storage_read('clientes.json');
    $leads = storage_read('leads.json');
    $interesadas = storage_read('interesadas.json');
    $casawasapContactos = storage_read('casawasap_contactos.json');
    $casawasapPagos = storage_read('casawasap_pagos.json');
    $jostalInteresadas = storage_read('jostal_interesadas.json');
    $jostalClientas = storage_read('jostal_clientas.json');
    $jostalLeads = storage_read('jostal_leads.json');
    $jostalVentas = storage_read('jostal_ventas.json');
    $gastos = storage_read('gastos.json');

    $from = request_get('from', business_current_month_key() . '-01');

    $to = request_get('to', business_today_date());
    $rama = request_get('rama', 'todas');
    $clienteId = request_get('cliente_id', '');
    $tipo = request_get('tipo', 'todos');
    $reportView = request_get('view', 'report');

    $parseTs = function ($value) {
        $raw = trim((string)$value);
        if ($raw === '') return 0;
        return strtotime(str_replace('T', ' ', $raw));
    };

    $inRange = function ($value) use ($parseTs, $from, $to) {
        $ts = $parseTs($value);
        if (!$ts) return false;

        list($start, $end) = business_range_bounds($from, $to);

        if ($start !== null && $ts < $start) return false;
        if ($end !== null && $ts > $end) return false;

        return true;
    };

    $movements = array();

    foreach ($leads as $lead) {
        $movements[] = array(
            'branch' => 'lamami',
            'type' => 'lead',
            'date' => $lead['fecha_hora'] ?? '',
            'amount' => isset($lead['precio_lead']) ? (float)$lead['precio_lead'] : 0,
            'label' => ($lead['cliente_nombre'] ?? '') !== '' ? $lead['cliente_nombre'] : 'Sin clienta',
            'description' => $lead['observacion'] ?? '',
            'client_id' => $lead['cliente_id'] ?? '',
            'link' => !empty($lead['cliente_id']) ? 'index.php?page=clientas&edit=' . urlencode($lead['cliente_id']) : '',
            'display_type' => 'Lead'
        );
    }

    foreach ($clientes as $cliente) {
        $movements[] = array(
            'branch' => 'lamami',
            'type' => 'alta',
            'date' => $cliente['fecha_alta'] ?? '',
            'amount' => isset($cliente['precio_alta']) ? (float)$cliente['precio_alta'] : 0,
            'label' => $cliente['nombre'] ?? 'Clienta',
            'description' => 'Alta de clienta',
            'client_id' => $cliente['id'] ?? '',
            'link' => !empty($cliente['id']) ? 'index.php?page=clientas&edit=' . urlencode($cliente['id']) : '',
            'display_type' => 'Alta'
        );
    }

    foreach ($casawasapPagos as $pago) {
        $movements[] = array(
            'branch' => 'casawasap',
            'type' => 'pago',
            'date' => $pago['fecha_hora'] ?? '',
            'amount' => isset($pago['importe']) ? (float)$pago['importe'] : 0,
            'label' => ($pago['cliente_nombre'] ?? '') !== '' ? $pago['cliente_nombre'] : 'Cliente',
            'description' => $pago['observaciones'] ?? '',
            'client_id' => $pago['cliente_id'] ?? '',
            'link' => !empty($pago['cliente_id']) ? 'index.php?page=casawasap&edit=' . urlencode($pago['cliente_id']) : '',
            'display_type' => 'Pago'
        );
    }

    foreach ($jostalLeads as $lead) {
        $movements[] = array(
            'branch' => 'jostal',
            'type' => 'lead',
            'date' => $lead['created_at'] ?? '',
            'amount' => isset($lead['precio']) ? (float)$lead['precio'] : 0,
            'label' => ($lead['clienta_nombre'] ?? '') !== '' ? $lead['clienta_nombre'] : 'Clienta',
            'description' => $lead['observacion'] ?? '',
            'client_id' => $lead['clienta_id'] ?? '',
            'link' => !empty($lead['clienta_id']) ? 'index.php?page=jostal&tab=clientas&edit=' . urlencode($lead['clienta_id']) : '',
            'display_type' => 'Lead'
        );
    }

    foreach ($jostalVentas as $venta) {
        $movements[] = array(
            'branch' => 'jostal',
            'type' => 'venta',
            'date' => $venta['created_at'] ?? '',
            'amount' => isset($venta['precio']) ? (float)$venta['precio'] : 0,
            'label' => 'Venta Jostal',
            'description' => $venta['descripcion'] ?? '',
            'client_id' => '',
            'link' => 'index.php?page=jostal&tab=ventas',
            'display_type' => 'Venta'
        );
    }

    foreach ($gastos as $gasto) {
        $movements[] = array(
            'branch' => 'global',
            'type' => 'gasto',
            'date' => $gasto['created_at'] ?? '',
            'amount' => -1 * (isset($gasto['cantidad']) ? (float)$gasto['cantidad'] : 0),
            'label' => 'Gasto',
            'description' => $gasto['descripcion'] ?? '',
            'client_id' => '',
            'link' => 'index.php?page=gastos',
            'display_type' => 'Gasto'
        );
    }

    $matchTipo = function ($rowType, $filterType) {
        if ($filterType === 'todos') return true;
        if ($filterType === 'ingresos') return in_array($rowType, array('lead', 'alta', 'pago', 'venta'), true);
        if ($filterType === 'gastos') return $rowType === 'gasto';
        return $rowType === $filterType;
    };

    $filteredMovements = array();
    foreach ($movements as $row) {
        if (!$inRange($row['date'])) continue;
        if ($rama !== 'todas' && $row['branch'] !== $rama) continue;
        if (!$matchTipo($row['type'], $tipo)) continue;
        if ($clienteId !== '' && $row['client_id'] !== $clienteId) continue;
        $row['ts'] = $parseTs($row['date']);
        $filteredMovements[] = $row;
    }

    usort($filteredMovements, function ($a, $b) {
        return $b['ts'] <=> $a['ts'];
    });

    $filteredIncome = 0;
    $filteredExpense = 0;
    $positiveCount = 0;
    $branchTotals = array('lamami' => 0, 'casawasap' => 0, 'jostal' => 0);

    foreach ($filteredMovements as $row) {
        if (($row['type'] ?? '') === 'gasto') {
            $filteredExpense += abs((float)$row['amount']);
            continue;
        }

        $filteredIncome += (float)$row['amount'];

        if ((float)$row['amount'] > 0) {
            $positiveCount++;
        }

        if (isset($branchTotals[$row['branch']])) {
            $branchTotals[$row['branch']] += (float)$row['amount'];
        }
    }    

    $filteredIncome = 0;
    $filteredExpense = 0;
    $positiveCount = 0;
    $branchTotals = array('lamami' => 0, 'casawasap' => 0, 'jostal' => 0);

    foreach ($filteredMovements as $row) {
        if (($row['type'] ?? '') === 'gasto') {
            $filteredExpense += abs((float)$row['amount']);
            continue;
        }

        $filteredIncome += (float)$row['amount'];

        if ((float)$row['amount'] > 0) {
            $positiveCount++;
        }

        if (isset($branchTotals[$row['branch']])) {
            $branchTotals[$row['branch']] += (float)$row['amount'];
        }
    }

    arsort($branchTotals);
    $bestBranchKey = key($branchTotals);
    $bestBranchLabel = $bestBranchKey === 'lamami' ? 'LaMami' : ($bestBranchKey === 'casawasap' ? 'Casawasap' : 'Jostal');

    $beneficioRealFiltrado = ($rama === 'todas') ? ($filteredIncome - $filteredExpense) : null;
    $ticketMedio = $positiveCount > 0 ? ($filteredIncome / $positiveCount) : 0;

    $dailyMap = array();
    $branchMix = array('LaMami' => 0, 'Casawasap' => 0, 'Jostal' => 0);
    foreach ($filteredMovements as $row) {
        $dayKey = business_day_key_from_ts($row['ts']);
        if (!isset($dailyMap[$dayKey])) {
            $dailyMap[$dayKey] = array('income' => 0, 'expense' => 0);
        }

        if (($row['type'] ?? '') === 'gasto') {
            $dailyMap[$dayKey]['expense'] += abs((float)$row['amount']);
            continue;
        }

        $dailyMap[$dayKey]['income'] += (float)$row['amount'];

        if ($row['branch'] === 'lamami') $branchMix['LaMami'] += (float)$row['amount'];
        if ($row['branch'] === 'casawasap') $branchMix['Casawasap'] += (float)$row['amount'];
        if ($row['branch'] === 'jostal') $branchMix['Jostal'] += (float)$row['amount'];
    }

    $timelineLabels = array();
    $timelineIncome = array();
    $timelineExpense = array();
    $cursor = strtotime($from . ' 00:00:00');
    $endCursor = strtotime($to . ' 00:00:00');
    while ($cursor && $endCursor && $cursor <= $endCursor) {
        $k = date('Y-m-d', $cursor);
        $timelineLabels[] = date('d/m', $cursor);
        $timelineIncome[] = isset($dailyMap[$k]) ? $dailyMap[$k]['income'] : 0;
        $timelineExpense[] = isset($dailyMap[$k]) ? $dailyMap[$k]['expense'] : 0;
        $cursor = strtotime('+1 day', $cursor);
    }

    if (($branchMix['LaMami'] + $branchMix['Casawasap'] + $branchMix['Jostal']) <= 0) {
        $branchMix['LaMami'] = 1;
        $branchMix['Casawasap'] = 0;
        $branchMix['Jostal'] = 0;
    }

    $lamamiLeadsFiltered = array();
    foreach ($leads as $lead) {
        if (!$inRange($lead['fecha_hora'] ?? '')) continue;
        if ($clienteId !== '' && ($lead['cliente_id'] ?? '') !== $clienteId) continue;
        if (!$matchTipo('lead', $tipo) && $tipo !== 'todos' && $tipo !== 'ingresos') continue;
        $lamamiLeadsFiltered[] = $lead;
    }

    $lamamiAltasFiltered = array();
    foreach ($clientes as $cliente) {
        if (!$inRange($cliente['fecha_alta'] ?? '')) continue;
        if ($clienteId !== '' && ($cliente['id'] ?? '') !== $clienteId) continue;
        if (!$matchTipo('alta', $tipo) && $tipo !== 'todos' && $tipo !== 'ingresos') continue;
        $lamamiAltasFiltered[] = $cliente;
    }

    $lamamiInteresadasFiltered = array();
    foreach ($interesadas as $item) {
        if (!$inRange($item['created_at'] ?? '')) continue;
        $lamamiInteresadasFiltered[] = $item;
    }

    $lamamiLeadIncome = 0;
    $lamamiRank = array();
    foreach ($lamamiLeadsFiltered as $lead) {
        $money = isset($lead['precio_lead']) ? (float)$lead['precio_lead'] : 0;
        $lamamiLeadIncome += $money;
        $label = ($lead['cliente_nombre'] ?? '') !== '' ? $lead['cliente_nombre'] : 'Sin clienta';
        if (!isset($lamamiRank[$label])) $lamamiRank[$label] = 0;
        $lamamiRank[$label] += $money;
    }
    arsort($lamamiRank);

    $lamamiAltaIncome = 0;
    foreach ($lamamiAltasFiltered as $cliente) {
        $lamamiAltaIncome += isset($cliente['precio_alta']) ? (float)$cliente['precio_alta'] : 0;
    }

    $lamamiNew = 0;
    $lamamiDone = 0;
    $lamamiAtt = 0;
    foreach ($lamamiInteresadasFiltered as $item) {
        $estado = $item['estado'] ?? '';
        if ($estado === 'nueva') $lamamiNew++;
        if ($estado === 'atendida') $lamamiAtt++;
        if ($estado === 'convertida') $lamamiDone++;
    }

    $casaContactosFiltered = array();
    foreach ($casawasapContactos as $contacto) {
        if (!$inRange($contacto['created_at'] ?? '')) continue;
        if ($clienteId !== '' && ($contacto['id'] ?? '') !== $clienteId) continue;
        $casaContactosFiltered[] = $contacto;
    }

    $casaPagosFiltered = array();
    $casaRank = array();
    foreach ($casawasapPagos as $pago) {
        if (!$inRange($pago['fecha_hora'] ?? '')) continue;
        if ($clienteId !== '' && ($pago['cliente_id'] ?? '') !== $clienteId) continue;
        if (!$matchTipo('pago', $tipo) && $tipo !== 'todos' && $tipo !== 'ingresos') continue;
        $casaPagosFiltered[] = $pago;
        $label = ($pago['cliente_nombre'] ?? '') !== '' ? $pago['cliente_nombre'] : 'Cliente';
        if (!isset($casaRank[$label])) $casaRank[$label] = 0;
        $casaRank[$label] += isset($pago['importe']) ? (float)$pago['importe'] : 0;
    }
    arsort($casaRank);

    $casaIncome = 0;
    foreach ($casaPagosFiltered as $pago) {
        $casaIncome += isset($pago['importe']) ? (float)$pago['importe'] : 0;
    }

    $casaClientesFiltered = 0;
    $casaInteresadosFiltered = 0;
    foreach ($casaContactosFiltered as $contacto) {
        if (($contacto['estado'] ?? '') === 'cliente') $casaClientesFiltered++;
        else $casaInteresadosFiltered++;
    }

    $jostalInteresadasFiltered = array();
    foreach ($jostalInteresadas as $item) {
        if (!$inRange($item['created_at'] ?? '')) continue;
        if (($item['estado'] ?? '') === 'convertida') continue;
        $jostalInteresadasFiltered[] = $item;
    }

    $jostalClientasFiltered = array();
    foreach ($jostalClientas as $item) {
        if (!$inRange($item['created_at'] ?? '')) continue;
        if ($clienteId !== '' && ($item['id'] ?? '') !== $clienteId) continue;
        $jostalClientasFiltered[] = $item;
    }

    $jostalLeadsFiltered = array();
    $jostalLeadRank = array();
    foreach ($jostalLeads as $lead) {
        if (!$inRange($lead['created_at'] ?? '')) continue;
        if ($clienteId !== '' && ($lead['clienta_id'] ?? '') !== $clienteId) continue;
        if (!$matchTipo('lead', $tipo) && $tipo !== 'todos' && $tipo !== 'ingresos') continue;
        $jostalLeadsFiltered[] = $lead;
        $label = ($lead['clienta_nombre'] ?? '') !== '' ? $lead['clienta_nombre'] : 'Clienta';
        if (!isset($jostalLeadRank[$label])) $jostalLeadRank[$label] = 0;
        $jostalLeadRank[$label] += isset($lead['precio']) ? (float)$lead['precio'] : 0;
    }
    arsort($jostalLeadRank);

    $jostalVentasFiltered = array();
    foreach ($jostalVentas as $venta) {
        if (!$inRange($venta['created_at'] ?? '')) continue;
        if (!$matchTipo('venta', $tipo) && $tipo !== 'todos' && $tipo !== 'ingresos') continue;
        $jostalVentasFiltered[] = $venta;
    }

    $jostalLeadIncome = 0;
    foreach ($jostalLeadsFiltered as $lead) {
        $jostalLeadIncome += isset($lead['precio']) ? (float)$lead['precio'] : 0;
    }

    $jostalVentasIncome = 0;
    foreach ($jostalVentasFiltered as $venta) {
        $jostalVentasIncome += isset($venta['precio']) ? (float)$venta['precio'] : 0;
    }

    $gastosFiltered = array();
    foreach ($gastos as $gasto) {
        if (!$inRange($gasto['created_at'] ?? '')) continue;
        if (!$matchTipo('gasto', $tipo) && $tipo !== 'todos' && $tipo !== 'gastos') continue;
        $gastosFiltered[] = $gasto;
    }

    page_header('Informes', 'Análisis filtrable y detallado por rama y a nivel global');

    echo '<section class="panel panel-space">';
    echo '<form method="get" class="toolbar">';
    echo '<input type="hidden" name="page" value="informes">';
    echo '<div class="field"><label>Desde</label><input type="date" name="from" value="' . e($from) . '"></div>';
    echo '<div class="field"><label>Hasta</label><input type="date" name="to" value="' . e($to) . '"></div>';

    echo '<div class="field"><label>Rama</label><select name="rama">';
    foreach (array('todas' => 'Todas', 'lamami' => 'LaMami', 'casawasap' => 'Casawasap', 'jostal' => 'Jostal') as $k => $label) {
        $sel = ($rama === $k) ? ' selected' : '';
        echo '<option value="' . e($k) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select></div>';

    echo '<div class="field"><label>Tipo</label><select name="tipo">';
    foreach (array(
        'todos' => 'Todos',
        'ingresos' => 'Ingresos',
        'gastos' => 'Gastos',
        'lead' => 'Leads',
        'alta' => 'Altas',
        'pago' => 'Pagos',
        'venta' => 'Ventas'
    ) as $k => $label) {
        $sel = ($tipo === $k) ? ' selected' : '';
        echo '<option value="' . e($k) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select></div>';

    echo '<div class="field"><label>Cliente / contacto</label><select name="cliente_id">';
    echo '<option value="">Todos</option>';
    foreach ($clientes as $cliente) {
        $sel = ($clienteId === ($cliente['id'] ?? '')) ? ' selected' : '';
        echo '<option value="' . e($cliente['id'] ?? '') . '"' . $sel . '>LaMami · ' . e($cliente['nombre'] ?? 'Clienta') . '</option>';
    }
    foreach ($casawasapContactos as $contacto) {
        $sel = ($clienteId === ($contacto['id'] ?? '')) ? ' selected' : '';
        $label = ($contacto['nombre'] ?? '') !== '' ? $contacto['nombre'] : ($contacto['telefono'] ?? 'Contacto');
        echo '<option value="' . e($contacto['id'] ?? '') . '"' . $sel . '>Casawasap · ' . e($label) . '</option>';
    }
    foreach ($jostalClientas as $clienta) {
        $sel = ($clienteId === ($clienta['id'] ?? '')) ? ' selected' : '';
        $label = ($clienta['nombre'] ?? '') !== '' ? $clienta['nombre'] : ($clienta['telefono'] ?? 'Clienta');
        echo '<option value="' . e($clienta['id'] ?? '') . '"' . $sel . '>Jostal · ' . e($label) . '</option>';
    }
    echo '</select></div>';

    echo '<div class="field field-btn"><label>&nbsp;</label><button class="btn-primary" name="view" value="report">Aplicar filtros</button></div>';
    echo '<div class="field field-btn"><label>&nbsp;</label><button class="btn-secondary-mini" name="view" value="grid">Ver grid</button></div>';
    echo '</form>';
    echo '</section>';

    if ($reportView === 'grid') {
        render_informes_grid_view($filteredMovements, $gastosFiltered, $from, $to, $rama, $tipo, $clienteId);
        return;
    }

    echo '<div class="cards four">';
    dashboard_card('Ingresos filtrados', euro($filteredIncome), true);
    dashboard_card('Gastos filtrados', euro($filteredExpense), true);
    dashboard_card('Movimientos filtrados', count($filteredMovements));
    dashboard_card('Ticket medio ingresos', euro($ticketMedio), true);
    echo '</div>';

    echo '<div class="cards two">';
    dashboard_card('Beneficio real filtrado', $beneficioRealFiltrado === null ? 'No aplica' : euro($beneficioRealFiltrado), true);
    dashboard_card('Rama líder del período', $bestBranchLabel);
    echo '</div>';

    if ($rama !== 'todas') {
        echo '<div class="dashboard-note">Al filtrar por una rama, los gastos siguen siendo globales y no se reparten entre negocios. Por eso el beneficio real solo aplica al modo "Todas".</div>';
    }

    echo '<div class="cards two">';
    echo '<section class="panel"><h2>Evolución diaria del período</h2><div class="chart-box"><canvas id="chartReportTimeline"></canvas></div></section>';
    echo '<section class="panel"><h2>Mix de ingresos filtrados</h2><div class="chart-box"><canvas id="chartReportMix"></canvas></div></section>';
    echo '</div>';

    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>Consolidado por rama</h2><span class="summary-badge">' . e($from) . ' → ' . e($to) . '</span></div>';
    echo '<div class="table-wrap"><table><thead><tr>';
    echo '<th>Rama</th><th>Ingresos</th><th>Movimientos</th><th>% ingresos</th>';
    echo '</tr></thead><tbody>';
    $incomeBase = $filteredIncome > 0 ? $filteredIncome : 1;
    foreach (array('lamami' => 'LaMami', 'casawasap' => 'Casawasap', 'jostal' => 'Jostal') as $key => $label) {
        $movCount = 0;
        foreach ($filteredMovements as $row) {
            if ($row['branch'] === $key && $row['amount'] >= 0) $movCount++;
        }
        $money = isset($branchTotals[$key]) ? $branchTotals[$key] : 0;
        echo '<tr>';
        echo '<td>' . e($label) . '</td>';
        echo '<td><span class="money-chip">' . e(euro($money)) . '</span></td>';
        echo '<td>' . e($movCount) . '</td>';
        echo '<td>' . e(round(($money / $incomeBase) * 100, 1)) . '%</td>';
        echo '</tr>';
    }
    echo '<tr>';
    echo '<td><strong>Gastos globales</strong></td>';
    echo '<td><span class="expense-chip">' . e(euro($filteredExpense)) . '</span></td>';
    echo '<td>' . e(count($gastosFiltered)) . '</td>';
    echo '<td>—</td>';
    echo '</tr>';
    echo '</tbody></table></div>';
    echo '</section>';

    if ($rama === 'todas' || $rama === 'lamami') {
        echo '<section class="panel panel-space report-section">';
        echo '<div class="branch-panel-head"><h2>LaMami</h2><span class="summary-badge">' . e(euro($lamamiLeadIncome + $lamamiAltaIncome)) . '</span></div>';
        echo '<div class="cards four">';
        dashboard_card('Leads', count($lamamiLeadsFiltered));
        dashboard_card('Ingresos leads', euro($lamamiLeadIncome), true);
        dashboard_card('Altas', count($lamamiAltasFiltered));
        dashboard_card('Ingresos altas', euro($lamamiAltaIncome), true);
        echo '</div>';
        echo '<div class="cards four">';
        dashboard_card('Interesadas nuevas', $lamamiNew);
        dashboard_card('Interesadas atendidas', $lamamiAtt);
        dashboard_card('Interesadas convertidas', $lamamiDone);
        dashboard_card('Bots', count($bots = storage_read('bots.json')));
        echo '</div>';

        echo '<div class="cards two">';
        echo '<section class="panel">';
        echo '<h2>Top clientas por ingresos lead</h2>';
        if (empty($lamamiRank)) {
            echo '<div class="empty">Sin datos para este filtro.</div>';
        } else {
            echo '<div class="table-wrap"><table><thead><tr><th>Clienta</th><th>Ingresos</th></tr></thead><tbody>';
            $shown = 0;
            foreach ($lamamiRank as $label => $money) {
                echo '<tr><td>' . e($label) . '</td><td><span class="money-chip">' . e(euro($money)) . '</span></td></tr>';
                $shown++;
                if ($shown >= 10) break;
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';

        echo '<section class="panel">';
        echo '<h2>Últimas altas filtradas</h2>';
        if (empty($lamamiAltasFiltered)) {
            echo '<div class="empty">Sin altas en este período.</div>';
        } else {
            echo '<div class="table-wrap"><table><thead><tr><th>Clienta</th><th>Fecha</th><th>Importe</th></tr></thead><tbody>';
            $tmp = $lamamiAltasFiltered;
            usort($tmp, function ($a, $b) use ($parseTs) { return $parseTs($b['fecha_alta'] ?? '') <=> $parseTs($a['fecha_alta'] ?? ''); });
            foreach (array_slice($tmp, 0, 10) as $cliente) {
                echo '<tr>';
                echo '<td><a class="mini-link" href="index.php?page=clientas&edit=' . e($cliente['id'] ?? '') . '">' . e($cliente['nombre'] ?? 'Clienta') . '</a></td>';
                echo '<td>' . e($cliente['fecha_alta'] ?? '') . '</td>';
                echo '<td><span class="money-chip">' . e(euro($cliente['precio_alta'] ?? 0)) . '</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';
        echo '</div>';
        echo '</section>';
    }

    if ($rama === 'todas' || $rama === 'casawasap') {
        echo '<section class="panel panel-space report-section">';
        echo '<div class="branch-panel-head"><h2>Casawasap</h2><span class="summary-badge">' . e(euro($casaIncome)) . '</span></div>';
        echo '<div class="cards four">';
        dashboard_card('Interesados', $casaInteresadosFiltered);
        dashboard_card('Clientes', $casaClientesFiltered);
        dashboard_card('Pagos', count($casaPagosFiltered));
        dashboard_card('Ingresos', euro($casaIncome), true);
        echo '</div>';

        echo '<div class="cards two">';
        echo '<section class="panel">';
        echo '<h2>Top clientes por pagos</h2>';
        if (empty($casaRank)) {
            echo '<div class="empty">Sin pagos para este filtro.</div>';
        } else {
            echo '<div class="table-wrap"><table><thead><tr><th>Cliente</th><th>Ingresos</th></tr></thead><tbody>';
            $shown = 0;
            foreach ($casaRank as $label => $money) {
                echo '<tr><td>' . e($label) . '</td><td><span class="money-chip">' . e(euro($money)) . '</span></td></tr>';
                $shown++;
                if ($shown >= 10) break;
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';

        echo '<section class="panel">';
        echo '<h2>Pagos filtrados</h2>';
        if (empty($casaPagosFiltered)) {
            echo '<div class="empty">Sin pagos para este período.</div>';
        } else {
            echo '<div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Cliente</th><th>Importe</th></tr></thead><tbody>';
            $tmp = $casaPagosFiltered;
            usort($tmp, function ($a, $b) use ($parseTs) { return $parseTs($b['fecha_hora'] ?? '') <=> $parseTs($a['fecha_hora'] ?? ''); });
            foreach (array_slice($tmp, 0, 10) as $pago) {
                echo '<tr>';
                echo '<td>' . e(format_created_at($pago['fecha_hora'] ?? '')) . '</td>';
                echo '<td><a class="mini-link" href="index.php?page=casawasap&edit=' . e($pago['cliente_id'] ?? '') . '">' . e($pago['cliente_nombre'] ?? 'Cliente') . '</a></td>';
                echo '<td><span class="money-chip">' . e(euro($pago['importe'] ?? 0)) . '</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';
        echo '</div>';
        echo '</section>';
    }

    if ($rama === 'todas' || $rama === 'jostal') {
        echo '<section class="panel panel-space report-section">';
        echo '<div class="branch-panel-head"><h2>Jostal</h2><span class="summary-badge">' . e(euro($jostalLeadIncome + $jostalVentasIncome)) . '</span></div>';
        echo '<div class="cards four">';
        dashboard_card('Interesadas', count($jostalInteresadasFiltered));
        dashboard_card('Clientas', count($jostalClientasFiltered));
        dashboard_card('Leads', count($jostalLeadsFiltered));
        dashboard_card('Ventas', count($jostalVentasFiltered));
        echo '</div>';
        echo '<div class="cards four">';
        dashboard_card('Ingresos leads', euro($jostalLeadIncome), true);
        dashboard_card('Ingresos ventas', euro($jostalVentasIncome), true);
        dashboard_card('Total Jostal', euro($jostalLeadIncome + $jostalVentasIncome), true);
        dashboard_card('Ticket medio', euro((count($jostalLeadsFiltered) + count($jostalVentasFiltered)) > 0 ? (($jostalLeadIncome + $jostalVentasIncome) / (count($jostalLeadsFiltered) + count($jostalVentasFiltered))) : 0), true);
        echo '</div>';

        echo '<div class="cards two">';
        echo '<section class="panel">';
        echo '<h2>Top clientas por leads</h2>';
        if (empty($jostalLeadRank)) {
            echo '<div class="empty">Sin leads para este filtro.</div>';
        } else {
            echo '<div class="table-wrap"><table><thead><tr><th>Clienta</th><th>Ingresos</th></tr></thead><tbody>';
            $shown = 0;
            foreach ($jostalLeadRank as $label => $money) {
                echo '<tr><td>' . e($label) . '</td><td><span class="money-chip">' . e(euro($money)) . '</span></td></tr>';
                $shown++;
                if ($shown >= 10) break;
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';

        echo '<section class="panel">';
        echo '<h2>Ventas filtradas</h2>';
        if (empty($jostalVentasFiltered)) {
            echo '<div class="empty">Sin ventas para este período.</div>';
        } else {
            echo '<div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Descripción</th><th>Importe</th></tr></thead><tbody>';
            $tmp = $jostalVentasFiltered;
            usort($tmp, function ($a, $b) use ($parseTs) { return $parseTs($b['created_at'] ?? '') <=> $parseTs($a['created_at'] ?? ''); });
            foreach (array_slice($tmp, 0, 10) as $venta) {
                echo '<tr>';
                echo '<td>' . e(format_created_at($venta['created_at'] ?? '')) . '</td>';
                echo '<td>' . e($venta['descripcion'] ?? 'Venta') . '</td>';
                echo '<td><span class="money-chip">' . e(euro($venta['precio'] ?? 0)) . '</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';
        echo '</div>';
        echo '</section>';
    }

    echo '<section class="panel panel-space report-section">';
    echo '<div class="branch-panel-head"><h2>Movimientos unificados</h2><span class="summary-badge">' . e(count($filteredMovements)) . ' registros</span></div>';
    if (empty($filteredMovements)) {
        echo '<div class="empty">No hay movimientos con los filtros seleccionados.</div>';
    } else {
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Fecha</th><th>Rama</th><th>Tipo</th><th>Cliente / concepto</th><th>Detalle</th><th>Importe</th>';
        echo '</tr></thead><tbody>';
        foreach ($filteredMovements as $row) {
            $branchLabel = $row['branch'] === 'lamami' ? 'LaMami' : ($row['branch'] === 'casawasap' ? 'Casawasap' : ($row['branch'] === 'jostal' ? 'Jostal' : 'Global'));
            echo '<tr>';
            echo '<td>' . e(format_created_at($row['date'])) . '</td>';
            echo '<td>' . e($branchLabel) . '</td>';
            echo '<td>' . e($row['display_type']) . '</td>';
            echo '<td>';
            if ($row['link'] !== '') {
                echo '<a class="mini-link" href="' . e($row['link']) . '">' . e($row['label']) . '</a>';
            } else {
                echo e($row['label']);
            }
            echo '</td>';
            echo '<td>' . e($row['description']) . '</td>';
            echo '<td><span class="' . ($row['amount'] < 0 ? 'expense-chip' : 'money-chip') . '">' . e(euro(abs($row['amount']))) . '</span></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';

    echo '<script>';
    echo 'new Chart(document.getElementById("chartReportTimeline"), {type:"line",data:{labels:' . json_encode($timelineLabels) . ',datasets:[';
    echo '{label:"Ingresos",data:' . json_encode($timelineIncome) . '},';
    echo '{label:"Gastos",data:' . json_encode($timelineExpense) . '}';
    echo ']},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:"bottom"}}}});';

    echo 'new Chart(document.getElementById("chartReportMix"), {type:"doughnut",data:{labels:["LaMami","Casawasap","Jostal"],datasets:[{data:' . json_encode(array_values($branchMix)) . '}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:"bottom"}}}});';
    echo '</script>';
}

function configm_field_meta() {
    return array(
        'income_milestone_step' => array(
            'label' => 'Hito de ingresos mensuales (€)',
            'help' => 'Cada vez que los ingresos del mes superen un múltiplo de esta cifra, se genera un aviso. Ejemplo: 1000 crea avisos en 1000, 2000, 3000...'
        ),
        'profit_milestone_step' => array(
            'label' => 'Hito de beneficio mensual (€)',
            'help' => 'Cada vez que el beneficio real del mes supere un múltiplo de esta cifra, se genera un aviso.'
        ),
        'branch_concentration_percent' => array(
            'label' => 'Concentración alta en una sola rama (%)',
            'help' => 'Si una rama absorbe este porcentaje o más de los ingresos del mes, se lanza un aviso de concentración.'
        ),
        'high_expense_amount' => array(
            'label' => 'Gasto alto (€)',
            'help' => 'Cualquier gasto igual o superior a esta cantidad se considera gasto alto y dispara aviso.'
        ),
        'events_recent_hours' => array(
            'label' => 'Ventana de eventos recientes (horas)',
            'help' => 'Sirve para que el cron detecte altas, leads o pagos recientes sin disparar avisos históricos antiguos.'
        ),
        'no_income_hours_1' => array(
            'label' => 'Inactividad corta sin ingresos (horas)',
            'help' => 'Tras este número de horas sin ingresos, se genera el primer aviso de inactividad.'
        ),
        'no_income_hours_2' => array(
            'label' => 'Inactividad grave sin ingresos (horas)',
            'help' => 'Tras este número de horas sin ingresos, se genera el segundo aviso más serio.'
        ),
        'unattended_interesada_hours' => array(
            'label' => 'Interesadas sin atender (horas)',
            'help' => 'Tiempo máximo que puede pasar una interesada nueva sin ser atendida antes de disparar aviso.'
        ),
        'lamami_attended_without_convert_hours' => array(
            'label' => 'LaMami atendida pero no convertida (horas)',
            'help' => 'Si una interesada de LaMami fue atendida pero no convierte en este plazo, se avisa.'
        ),
        'overdue_interesada_days' => array(
            'label' => 'Interesada antigua no convertida (días)',
            'help' => 'Días máximos antes de avisar de una interesada que sigue sin convertir.'
        ),
        'lamami_clienta_without_leads_days' => array(
            'label' => 'Clienta LaMami sin leads (días)',
            'help' => 'Si una clienta de LaMami lleva este número de días sin generar ningún lead, salta aviso.'
        ),
        'jostal_clienta_en_casa_without_income_days' => array(
            'label' => 'Clienta Jostal en casa sin ingresos (días)',
            'help' => 'Si una clienta que está en casa en Jostal no genera ingresos durante este tiempo, se avisa.'
        ),
        'casawasap_cliente_without_pagos_days' => array(
            'label' => 'Cliente Casawasap sin pagos (días)',
            'help' => 'Si un cliente de Casawasap pasa este tiempo sin registrar pagos, se avisa.'
        ),
        'weekly_cycle_days' => array(
            'label' => 'Ciclo semanal de cobros (días)',
            'help' => 'Número de días del ciclo estándar para revisar renovaciones o cobros recurrentes.'
        ),
        'overdue_additional_weeks' => array(
            'label' => 'Semanas extra para considerar vencido',
            'help' => 'Semanas adicionales de retraso para generar el aviso de cobro/publicidad vencida.'
        ),
        'many_renewals_due_today_min_total' => array(
            'label' => 'Mínimo de renovaciones/cobros que vencen hoy',
            'help' => 'Si hoy vencen al menos esta cantidad total, se genera un aviso agregado.'
        ),
        'projection_min_elapsed_days' => array(
            'label' => 'Días mínimos del mes para proyectar',
            'help' => 'Hasta que no hayan pasado al menos estos días del mes, no se hacen avisos de proyección.'
        ),
        'projection_vs_previous_factor' => array(
            'label' => 'Factor mínimo frente al mes anterior',
            'help' => 'Ejemplo: 0.80 significa que si la proyección actual cae por debajo del 80% del mes pasado, se avisa.'
        ),
        'negative_trend_days' => array(
            'label' => 'Días consecutivos empeorando',
            'help' => 'Número de días seguidos de empeoramiento necesarios para lanzar aviso de tendencia negativa.'
        ),
        'too_many_active_alerts_count' => array(
            'label' => 'Demasiados avisos activos',
            'help' => 'A partir de este número de avisos activos simultáneos, el sistema considera que ya hay demasiado ruido.'
        ),
        'mundosex_reminder_enabled' => array(
            'label' => 'Activar recordatorio de MundoSex',
            'help' => '1 = activado, 0 = desactivado. Si está activado, se generarán avisos automáticos recurrentes para subir publicidad a MundoSex.'
        ),
        'mundosex_reminder_interval_hours' => array(
            'label' => 'Frecuencia de MundoSex (horas)',
            'help' => 'Cada cuántas horas debe saltar el recordatorio dentro de la franja diaria configurada.'
        ),
        'mundosex_reminder_start_time' => array(
            'label' => 'Hora de inicio de MundoSex',
            'help' => 'Hora del día desde la que empieza a contar la franja de recordatorios. Formato HH:MM.'
        ),
        'mundosex_reminder_end_time' => array(
            'label' => 'Hora final de MundoSex',
            'help' => 'Hora límite del día hasta la que pueden generarse recordatorios. Formato HH:MM.'
        ),
        'mundosex_reminder_window_minutes' => array(
            'label' => 'Tolerancia de MundoSex (minutos)',
            'help' => 'Minutos extra durante los que sigue siendo válido disparar el aviso tras la hora objetivo.'
        ),
        'whatsapp_sender_key' => array(
            'label' => 'Origen de envío WhatsApp',
            'help' => 'Los avisos salen automáticamente por rotación usando una de las líneas activas que Comercial tenga disponibles en ese momento.'
        ),
        'whatsapp_target_phones' => array(
            'label' => 'Teléfonos destino de avisos',
            'help' => 'Números que recibirán los avisos. Puedes poner uno por línea.'
        ),
        'alerts_noise_profile' => array(
            'label' => 'Perfil de ruido de avisos',
            'help' => 'Conservador: envía casi todo. Balanceado: envía alta/media. Agresivo: envía solo alta y reduce el ruido al máximo.'
        ),
        'whatsapp_allowed_engine_kinds' => array(
            'label' => 'Tipos que sí pueden llegar por WhatsApp',
            'help' => 'Filtro principal anti-ruido. Una clave por línea (engine o engine:kind). Si un aviso no está aquí, no sale por WhatsApp aunque sea severidad alta.'
        ),
        'whatsapp_sender_overrides' => array(
            'label' => 'Asignación manual tipo de aviso → teléfono/línea',
            'help' => 'Una regla por línea: engine[:kind]=line_id_o_telefono. Ej: attention=631454098 o recurring:destacamos_publish=12'
        ),
    );
}

function configm_avisos_override_types_catalog() {
    return array(
        'attention' => 'Atención (todos)',
        'attention:unattended' => 'Atención · Interesadas sin atender',
        'attention:attended_24h' => 'Atención · Atendida sin convertir (24h+)',
        'attention:attended_48h' => 'Atención · Atendida sin convertir (48h+)',
        'recurring' => 'Recordatorios recurrentes (todos)',
        'recurring:destacamos_publish' => 'Recordatorios · Publicar en Destacamos',
        'recurring:mundosex_publish' => 'Recordatorios · Publicar en MundoSex',
        'inactivity' => 'Inactividad (todos)',
        'overdue' => 'Overdue / atrasos (todos)',
        'events' => 'Eventos (todos)',
        'milestones' => 'Hitos (todos)',
        'strategic' => 'Estratégicos (todos)',
        'integrity' => 'Integridad (todos)',
        'performance' => 'Rendimiento (todos)',
    );
}

function configm_telefonos_sender_candidates() {
    $rows = storage_read('telefonos.json');
    $out = array();
    foreach ((array)$rows as $row) {
        if (!is_array($row)) continue;
        $id = trim((string)($row['id'] ?? ''));
        $name = trim((string)($row['nombre'] ?? ($row['alias'] ?? '')));
        $phone = trim((string)($row['tfono'] ?? ($row['telefono'] ?? '')));
        if ($id === '' && $phone === '') continue;
        $out[] = array(
            'id' => $id,
            'name' => $name !== '' ? $name : 'Línea sin nombre',
            'phone' => $phone,
        );
    }

    usort($out, function($a, $b) {
        $aLabel = strtolower(trim((string)($a['name'] ?? '')) . ' ' . trim((string)($a['phone'] ?? '')));
        $bLabel = strtolower(trim((string)($b['name'] ?? '')) . ' ' . trim((string)($b['phone'] ?? '')));
        return strcmp($aLabel, $bLabel);
    });

    return array_values($out);
}


function render_config_section() {
    $settings = settings_get();
    $ips = auth_whitelist_ips();
    $clientIp = auth_client_ip();
    $voiceCfg = voice_ai_config();
    $voiceForm = voice_ai_config_form_state();

    echo '<div class="cards two">';

    echo '<section class="panel">';
    echo '<div class="josue-head">';
    echo '<div>';
    echo '<h2>Acceso rápido por IP</h2>';
    echo '<p>Si la IP que entra está en whitelist, el sistema deja pasar directamente sin pedir login.</p>';
    echo '</div>';
    echo '</div>';

    echo '<form method="post" class="form-grid">';
    echo '<input type="hidden" name="action" value="save_access_config">';
    echo '<div class="field full">';
    echo '<label>IPs en whitelist</label>';
    echo '<textarea name="whitelist_ips" rows="8">' . e(implode("
", $ips)) . '</textarea>';
    echo '<div class="field-help">Una IP por línea. También admite coma o punto y coma.</div>';
    echo '</div>';
    echo '<div class="full"><button class="btn-primary">Guardar whitelist</button></div>';
    echo '</form>';
    echo '</section>';

    echo '<section class="panel">';
    echo '<h2>Diagnóstico</h2>';
    echo '<div class="info-strip"><strong>IP detectada ahora:</strong> ' . e($clientIp !== '' ? $clientIp : 'No detectada') . '</div>';
    echo '<div class="info-strip" style="margin-top:12px;"><strong>Estado:</strong> ' . (auth_is_whitelisted_ip($clientIp) ? 'Esta IP ya está autorizada.' : 'Esta IP no está en whitelist.') . '</div>';
    echo '<div class="generated-box">';
    echo '<div class="generated-box-head"><h3>Whitelist actual</h3></div>';
    if (empty($ips)) {
        echo '<div class="empty">No hay IPs guardadas.</div>';
    } else {
        echo '<div class="linked-tags">';
        foreach ($ips as $ip) {
            echo '<span class="linked-tag">' . e($ip) . '</span>';
        }
        echo '</div>';
    }
    echo '</div>';
    echo '</section>';

    echo '<section class="panel">';
    echo '<div class="josue-head">';
    echo '<div>';
    echo '<h2>IA voz CRM</h2>';
    echo '<p>Configura la API key y el modelo que usará el sistema de órdenes por voz. Si no había nada guardado, se precarga automáticamente la key detectada en la plantilla del bot.</p>';
    echo '</div>';
    echo '</div>';

    echo '<form method="post" class="form-grid">';
    echo '<input type="hidden" name="action" value="save_voice_ai_config">';
    echo '<div class="field full">';
    echo '<label>API key OpenAI</label>';
    echo '<input type="password" name="voice_ai_api_key" value="' . e($voiceForm['form_api_key']) . '" autocomplete="off" spellcheck="false">';
    echo '<div class="field-help">Por defecto se usa la key detectada en el Bearer de la plantilla del bot. Si algún día la cambias aquí, esta configuración tendrá prioridad frente a ese valor por defecto. Si además defines OPENAI_API_KEY en el servidor, esa variable tendrá prioridad sobre todo lo demás.</div>';
    echo '</div>';
    echo '<div class="field">';
    echo '<label>Modelo</label>';
    echo '<input type="text" name="voice_ai_model" value="' . e($voiceForm['form_model']) . '" placeholder="gpt-5.1" autocomplete="off">';
    echo '<div class="field-help">Se guardará aquí y, si no hay nada, el sistema usará gpt-5.1 por defecto.</div>';
    echo '</div>';
    echo '<div class="field">';
    echo '<label>Estado actual</label>';
    echo '<div class="info-strip"><strong>IA activa:</strong> ' . ($voiceCfg['configured'] ? 'Sí' : 'No') . '</div>';
    echo '<div class="info-strip" style="margin-top:10px;"><strong>Modelo activo:</strong> ' . e((string)$voiceCfg['model']) . '</div>';
    echo '<div class="info-strip" style="margin-top:10px;"><strong>Fuente de la key:</strong> ' . e((string)$voiceCfg['api_key_source']) . '</div>';
    echo '<div class="info-strip" style="margin-top:10px;"><strong>Fuente del modelo:</strong> ' . e((string)$voiceCfg['model_source']) . '</div>';
    echo '</div>';
    echo '<div class="full">';
    echo '<div class="generated-box">';
    echo '<div class="generated-box-head"><h3>Diagnóstico de origen</h3></div>';
    echo '<div class="info-strip"><strong>Variable OPENAI_API_KEY:</strong> ' . ($voiceForm['has_env_api_key'] ? 'Detectada' : 'No detectada') . '</div>';
    echo '<div class="info-strip" style="margin-top:10px;"><strong>Key guardada en settings:</strong> ' . ($voiceForm['has_stored_api_key'] ? 'Sí' : 'No') . '</div>';
    echo '<div class="info-strip" style="margin-top:10px;"><strong>Key detectada en plantilla del bot:</strong> ' . ($voiceForm['has_default_api_key'] ? 'Sí' : 'No') . '</div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="full"><button class="btn-primary">Guardar IA de voz</button></div>';
    echo '</form>';
    echo '</section>';

    echo '</div>';
}

function render_configm_section() {
    $defaults = is_file(BASE_PATH . '/avisos_config.php') ? (require BASE_PATH . '/avisos_config.php') : array();
    $current = avisos_config();
    $senderPresets = avisos_sender_presets();
    $senderKey = avisos_sender_config_key();
    $metaMap = configm_field_meta();
    $overrideTypes = configm_avisos_override_types_catalog();
    $overrideMap = function_exists('aviso_sender_overrides_map') ? aviso_sender_overrides_map() : array();
    $senderCandidates = configm_telefonos_sender_candidates();

    echo '<div class="cards two">';

    echo '<section class="panel">';
    echo '<div class="josue-head">';
    echo '<h2>ConfigM · Motor de avisos</h2>';
    echo '<p>Aquí puedes cambiar tanto la lógica interna de avisos como la forma en que se envían por WhatsApp. Cada campo incluye explicación clara debajo.</p>';
    echo '</div>';

    echo '<form method="post" class="form-grid">';
    echo '<input type="hidden" name="action" value="save_configm">';

    foreach ($defaults as $key => $defaultValue) {
        $meta = $metaMap[$key] ?? array(
            'label' => $key,
            'help' => 'Sin descripción adicional.'
        );
        $label = $meta['label'];
        $help = $meta['help'];
        $value = array_key_exists($key, $current) ? $current[$key] : $defaultValue;

        if ($key === 'whatsapp_sender_key') {
            $availableCount = 0;
            if (function_exists('avisos_comercial_sender_lines')) {
                $availableCount = count((array)avisos_comercial_sender_lines());
            }
            echo '<div class="full">';
            echo '<label>' . e($label) . '</label>';
            echo '<div class="info-strip"><strong>Modo actual:</strong> routing determinista por tipo de aviso (estable por engine/kind).';
            echo '<br><strong>Disponibles ahora:</strong> ' . e((string)$availableCount) . '</div>';
            echo '<div class="field-help">' . e($help) . '</div>';
            echo '</div>';
            continue;
        }

        if ($key === 'alerts_noise_profile') {
            $profile = trim((string)$value);
            if (!in_array($profile, array('conservador', 'balanceado', 'agresivo'), true)) {
                $profile = 'balanceado';
            }
            echo '<div class="full">';
            echo '<label>' . e($label) . '</label>';
            echo '<select name="alerts_noise_profile">';
            echo '<option value="conservador"' . ($profile === 'conservador' ? ' selected' : '') . '>Conservador</option>';
            echo '<option value="balanceado"' . ($profile === 'balanceado' ? ' selected' : '') . '>Balanceado</option>';
            echo '<option value="agresivo"' . ($profile === 'agresivo' ? ' selected' : '') . '>Agresivo anti-ruido</option>';
            echo '</select>';
            echo '<div class="field-help">' . e($help) . '</div>';
            echo '</div>';
            continue;
        }

        if ($key === 'whatsapp_target_phones' || $key === 'destacamos_reminder_times' || $key === 'whatsapp_allowed_engine_kinds') {
            echo '<div class="full">';
            field_textarea(
                $key,
                $label,
                is_array($value) ? implode("\n", $value) : (string)$value,
                4
            );
            echo '<div class="field-help">' . e($help) . '</div>';
            echo '</div>';
            continue;
        }

        if ($key === 'whatsapp_sender_overrides') {
            echo '<div class="full">';
            echo '<label>' . e($label) . '</label>';
            echo '<div class="field-help" style="margin-bottom:10px;">Vincula cada tipo de aviso a una línea de Josue > Teléfonos. Si dejas "Automático", se usará el routing determinista por tipo.</div>';
            echo '<div class="table-wrap"><table><thead><tr><th>Tipo de aviso</th><th>Línea origen</th></tr></thead><tbody>';
            foreach ($overrideTypes as $typeKey => $typeLabel) {
                $selected = trim((string)($overrideMap[strtolower($typeKey)] ?? ''));
                echo '<tr>';
                echo '<td><strong>' . e($typeLabel) . '</strong><br><span class="muted">' . e($typeKey) . '</span></td>';
                echo '<td>';
                echo '<select name="whatsapp_sender_override_map[' . e($typeKey) . ']">';
                echo '<option value="">Automático (determinista)</option>';
                foreach ($senderCandidates as $line) {
                    $lineId = trim((string)($line['id'] ?? ''));
                    $linePhone = trim((string)($line['phone'] ?? ''));
                    $lineValue = $lineId !== '' ? $lineId : $linePhone;
                    if ($lineValue === '') continue;
                    $labelLine = trim((string)$line['name']) . ($linePhone !== '' ? (' · ' . $linePhone) : '');
                    echo '<option value="' . e($lineValue) . '"' . ($selected === $lineValue ? ' selected' : '') . '>' . e($labelLine) . ($lineId !== '' ? (' (id ' . e($lineId) . ')') : '') . '</option>';
                }
                echo '</select>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';

            $legacyRaw = is_array($value) ? implode("\n", $value) : (string)$value;
            echo '<div style="margin-top:10px;">';
            echo '<label>Reglas avanzadas (opcional)</label>';
            echo '<textarea name="whatsapp_sender_overrides_legacy_extra" rows="4" placeholder="engine[:kind]=line_id_o_telefono\nEj: recurring:destacamos_publish=631454098">' . e($legacyRaw) . '</textarea>';
            echo '<div class="field-help">Para tipos no listados en la tabla. Si repites una clave, prevalece la selección de la tabla.</div>';
            echo '</div>';

            echo '</div>';
            continue;
        }

        $type = 'text';
        if (is_int($defaultValue) || is_float($defaultValue)) {
            $type = 'number';
        }
        if (strpos($key, '_time') !== false && is_string($defaultValue) && preg_match('/^\d{2}:\d{2}$/', $defaultValue)) {
            $type = 'time';
        }

        echo '<div class="field">';
        echo '<label>' . e($label) . '</label>';
        echo '<input type="' . e($type) . '" name="' . e($key) . '" value="' . e((string)$value) . '" step="any">';
        echo '<div class="field-help">' . e($help) . '</div>';
        echo '</div>';
    }

    // ---- Campos Pollo.ai (fuera del bucle de avisos_config.php) ----
    $settingsRaw = settings_get();
    $polloCookieCurrent = trim((string)($settingsRaw['pollo_session_cookie'] ?? ''));
    $polloExpiresCurrent = trim((string)($settingsRaw['pollo_cookie_expires'] ?? '2026-07-14'));
    $polloDaysLeft = function_exists('publicista_pollo_cookie_days_remaining') ? publicista_pollo_cookie_days_remaining() : -1;
    if ($polloDaysLeft > 30) { $polloStatusBadge = 'OK'; $polloStatusColor = '#059669'; }
    elseif ($polloDaysLeft > 7) { $polloStatusBadge = 'Aviso - menos de 1 mes'; $polloStatusColor = '#d97706'; }
    elseif ($polloDaysLeft > 0) { $polloStatusBadge = 'URGENTE - menos de 1 semana'; $polloStatusColor = '#dc2626'; }
    else { $polloStatusBadge = 'CADUCADA - renueva ya'; $polloStatusColor = '#dc2626'; }

    echo '<div class="field full" style="margin-top:8px;">';
    echo '<hr style="margin:4px 0 12px;border:none;border-top:1px solid #e5e7eb;">';
    echo '<strong style="font-size:13px;color:#6b7280;">Pollo.ai · Cookie de sesión</strong>';
    echo '</div>';

    echo '<div class="field full">';
    echo '<label>Cookie sesión Pollo.ai</label>';
    echo '<textarea name="pollo_session_cookie" rows="3" autocomplete="off" spellcheck="false" style="font-family:monospace;font-size:11px;word-break:break-all;">' . e($polloCookieCurrent) . '</textarea>';
    echo '<div class="field-help">Pega aquí el valor completo del header <code>Cookie:</code> copiado de las DevTools mientras estás logueado en pollo.ai. Empieza por <code>__Secure-next-auth.session-token=...</code>. Instrucciones detalladas en el formulario de creación de perfiles.</div>';
    echo '</div>';

    echo '<div class="field">';
    echo '<label>Fecha expiración cookie Pollo.ai</label>';
    echo '<input type="date" name="pollo_cookie_expires" value="' . e($polloExpiresCurrent) . '">';
    echo '<div class="field-help">Fecha en que caduca la cookie. La ves en el header <code>set-cookie → Expires=</code> cuando capturas la cookie. Actualiza este campo cada vez que renuevas la cookie.</div>';
    echo '</div>';

    echo '<div class="field">';
    echo '<label>Estado actual de la cookie</label>';
    echo '<div class="info-strip" style="display:flex;align-items:center;gap:12px;">';
    echo '<span style="font-size:13px;"><strong style="color:' . e($polloStatusColor) . ';">' . e($polloStatusBadge) . '</strong>';
    if ($polloCookieCurrent !== '') {
        echo ' · expira ' . e($polloExpiresCurrent);
        if ($polloDaysLeft > 0) echo ' (en ' . e((string)$polloDaysLeft) . ' días)';
    } else {
        echo ' · Sin cookie configurada';
    }
    echo '</span>';
    echo '</div>';
    echo '</div>';

    echo '<div class="full"><button class="btn-primary">Guardar configuración</button></div>';
    echo '</form>';
    echo '</section>';

    echo '<section class="panel">';
    echo '<div class="josue-head"><h2>Resumen actual</h2></div>';

    $activePreset = $senderPresets[$senderKey];
    echo '<div class="info-strip">';
    echo '<strong>Origen activo:</strong> ' . e($activePreset['label']) . '<br>';
    echo '<strong>IP:</strong> ' . e($activePreset['host']) . '<br>';
    echo '<strong>Teléfono origen:</strong> ' . e($activePreset['phone']) . '<br>';
    echo '<strong>Puerto:</strong> ' . e($activePreset['port']) . '<br>';
    echo '<strong>WAHA:</strong> ' . e($activePreset['waha_name']) . '<br>';
    echo '<strong>Sesión:</strong> ' . e($activePreset['session']) . '<br>';
    echo '</div>';

    $targets = avisos_target_phones();
    echo '<div class="info-strip" style="margin-top:12px;">';
    echo '<strong>Destinos actuales:</strong><br>';
    if (empty($targets)) {
        echo '<span class="muted">No hay teléfonos destino configurados.</span>';
    } else {
        foreach ($targets as $phone) {
            echo '· ' . e($phone) . '<br>';
        }
    }
    echo '</div>';

    echo '<div class="info-strip" style="margin-top:12px;">';
    echo '<strong>MundoSex:</strong><br>';
    echo 'Activo: ' . e((string)aviso_cfg('mundosex_reminder_enabled', 1)) . '<br>';
    echo 'Cada: ' . e((string)aviso_cfg('mundosex_reminder_interval_hours', 4)) . ' horas<br>';
    echo 'Desde: ' . e((string)aviso_cfg('mundosex_reminder_start_time', '08:00')) . '<br>';
    echo 'Hasta: ' . e((string)aviso_cfg('mundosex_reminder_end_time', '23:00')) . '<br>';
    echo 'Tolerancia: ' . e((string)aviso_cfg('mundosex_reminder_window_minutes', 90)) . ' min<br>';
    echo '</div>';

    echo '<div class="info-strip" style="margin-top:12px;">';
    echo '<strong>Perfil ruido avisos:</strong> ' . e((string)aviso_cfg('alerts_noise_profile', 'balanceado')) . '<br>';
    echo '<strong>Routing de líneas:</strong> determinista por tipo de aviso<br>';
    $overrideRules = trim((string)aviso_cfg('whatsapp_sender_overrides', ''));
    if ($overrideRules !== '') {
        echo '<strong>Overrides activos:</strong><br><pre style="white-space:pre-wrap;word-break:break-word;margin:6px 0 0;">' . e($overrideRules) . '</pre>';
    } else {
        echo '<strong>Overrides activos:</strong> ninguno';
    }
    echo '</div>';

    echo '<div class="info-strip" style="margin-top:12px;">';
    echo '<strong>Nota:</strong> `avisos_config.php` es la base por defecto, y lo que guardas aquí la sobrescribe dinámicamente.';
    echo '</div>';

    echo '</section>';

    echo '</div>';
}

function render_josue_page() {
    $tab = request_get('tab', 'publias');
    $allowed = array('publias', 'captacion', 'notas', 'waha', 'telefonos', 'agenda', 'eurekas', 'config', 'configm');

    if (!in_array($tab, $allowed, true)) {
        $tab = 'publias';
    }

    $settings = storage_read('settings.json');
    $anuncios = storage_read('anuncios.json');
    $telefonos = storage_read('telefonos.json');
    $agenda = storage_read('agenda.json');
    $eurekas = storage_read('eurekas.json');


    $text = in_array($tab, array('publias', 'captacion', 'notas', 'waha'), true)
        ? (isset($settings[$tab . '_text']) ? (string)$settings[$tab . '_text'] : '')
        : '';

    $isEdit = request_get('edit_text', '') === '1';

    if (in_array($tab, array('publias', 'captacion', 'notas', 'waha'), true) && trim($text) === '') {
        $isEdit = true;
    }

    page_header('Josue', 'Sección de trabajo interno');

    echo '<section class="panel panel-josue">';

    echo '<div class="subtabs">';
    echo '<a class="subtab ' . ($tab === 'telefonos' ? 'active' : '') . '" href="index.php?page=josue&tab=telefonos">Telefonos</a>';
    echo '<a class="subtab ' . ($tab === 'waha' ? 'active' : '') . '" href="index.php?page=josue&tab=waha">WAHA</a>';
    echo '<a class="subtab ' . ($tab === 'publias' ? 'active' : '') . '" href="index.php?page=josue&tab=publias">PublIas</a>';
    echo '<a class="subtab ' . ($tab === 'captacion' ? 'active' : '') . '" href="index.php?page=josue&tab=captacion">Captacion</a>';
    echo '<a class="subtab ' . ($tab === 'agenda' ? 'active' : '') . '" href="index.php?page=josue&tab=agenda">Agenda</a>';
    echo '<a class="subtab ' . ($tab === 'eurekas' ? 'active' : '') . '" href="index.php?page=josue&tab=eurekas">Eurekas</a>';
    //echo '<a class="subtab ' . ($tab === 'avisos' ? 'active' : '') . '" href="index.php?page=josue&tab=avisos">Avisos</a>';
    echo '<a class="subtab ' . ($tab === 'config' ? 'active' : '') . '" href="index.php?page=josue&tab=config">Config</a>';
    echo '<a class="subtab ' . ($tab === 'configm' ? 'active' : '') . '" href="index.php?page=josue&tab=configm">ConfigM</a>';
    echo '<a class="subtab ' . ($tab === 'notas' ? 'active' : '') . '" href="index.php?page=josue&tab=notas">Notas</a>';    
    
    echo '</div>';

    echo '<div class="subtab-content">';


    if ($tab === 'config') {
        render_config_section();

    } elseif ($tab === 'configm') {
        render_configm_section();

    } elseif ($tab === 'anuncios') {
        echo '<section class="panel panel-space">';
        echo '<div class="branch-panel-head"><h2>Anuncios movido a Publicista</h2><span class="summary-badge">Reubicado</span></div>';
        echo '<div class="info-strip">La gestión de cuentas de portales ya no vive en Josué. Ahora está en <strong>Publicista &gt; Cuentas</strong> para que quede unida al futuro módulo de campañas.</div>';
        echo '<div style="margin-top:12px;"><a class="btn-primary" href="' . e(publicista_page_url('cuentas')) . '">Abrir cuentas en Publicista</a></div>';
        echo '</section>';
    } elseif ($tab === 'telefonos') {
        $editId = request_get('edit', '');
        $edit = $editId !== '' ? storage_find_by_id('telefonos.json', $editId) : null;

        $anunciosIndex = array();
        foreach ($anuncios as $an) {
            $anunciosIndex[$an['id']] = $an;
        }

        echo '<div class="cards two">';

        echo '<section class="panel">';
        echo '<div class="josue-head">';
        echo '<h2>' . ($edit ? 'Ficha teléfono' : 'Nuevo teléfono') . '</h2>';
        echo '</div>';

        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="action" value="save_telefono">';
        echo '<input type="hidden" name="id" value="' . e($edit['id'] ?? '') . '">';
        field_input('nombre', 'Nombre', $edit['nombre'] ?? '', true);
        field_input('tfono', 'Tfono', $edit['tfono'] ?? '', true);
        field_input('uso', 'Uso', $edit['uso'] ?? '');
        field_input('pin', 'PIN', $edit['pin'] ?? '');
        field_input('compania', 'Compañía', $edit['compania'] ?? '');
        field_input('waha_port', 'WAHA Port', $edit['waha_port'] ?? '');
        field_input('waha', 'WAHA', $edit['waha'] ?? '');
        echo '<div class="field">';
        echo '<label>Destacamos</label>';
        echo '<select name="destacamos_id">';
        echo '<option value="">Sin vincular</option>';
        foreach ($anuncios as $an) {
            $val = $an['id'] ?? '';
            $label = trim(($an['url'] ?? '') . ' - ' . ($an['user'] ?? ''));
            $sel = (($edit['destacamos_id'] ?? '') === $val) ? ' selected' : '';
            echo '<option value="' . e($val) . '"' . $sel . '>' . e($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        field_textarea('notas', 'Notas', $edit['notas'] ?? '', 4);
        echo '<div class="full"><button class="btn-primary">Guardar teléfono</button></div>';
        echo '</form>';
        echo '</section>';

        echo '<section class="panel">';
        echo '<h2>Listado teléfonos</h2>';
        if (empty($telefonos)) {
            echo '<div class="empty">Todavía no hay teléfonos registrados.</div>';
        } else {
            $telefonos = sort_desc_by_key($telefonos, 'created_at');
            render_live_filter('#telefonosRows tr[data-filter-text]', 'Buscar teléfono...');
            echo '<div class="table-wrap"><table><thead><tr>';
            echo '<th>Nombre</th><th>Tfono</th><th>Uso</th><th>WAHA Port</th><th>WAHA</th><th>Destacamos</th><th>Acciones</th>';
            echo '</tr></thead><tbody id="telefonosRows">';
            foreach ($telefonos as $row) {
                $dest = $anunciosIndex[$row['destacamos_id'] ?? ''] ?? null;
                $destLabel = $dest ? trim(($dest['url'] ?? '') . ' - ' . ($dest['user'] ?? '')) : '-';
                $searchText = strtolower(trim(
                    ($row['nombre'] ?? '') . ' ' .
                    ($row['tfono'] ?? '') . ' ' .
                    ($row['uso'] ?? '') . ' ' .
                    ($row['compania'] ?? '') . ' ' .
                    ($row['waha_port'] ?? '') . ' ' .
                    ($row['waha'] ?? '') . ' ' .
                    ($destLabel ?? '')
                ));
                echo '<tr data-filter-text="' . e($searchText) . '">';
                echo '<td>' . e($row['nombre'] ?? '') . '</td>';
                echo '<td>'; crm_render_phone_value((string)($row['tfono'] ?? '')); echo '</td>';
                echo '<td>' . e($row['uso'] ?? '') . '</td>';
                echo '<td>' . e($row['waha_port'] ?? '') . '</td>';
                echo '<td>' . e($row['waha'] ?? '') . '</td>';
                echo '<td>' . e($destLabel) . '</td>';
                echo '<td>';
                echo '<a class="mini-link" href="index.php?page=josue&tab=telefonos&edit=' . e($row['id']) . '">Editar</a> ';
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar este teléfono?\')">';
                echo '<input type="hidden" name="action" value="delete_telefono">';
                echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                echo '<button class="btn-danger-mini">Eliminar</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';

        echo '</div>';
    } elseif ($tab === 'agenda') {
        $editId = request_get('edit', '');
        $edit = $editId !== '' ? storage_find_by_id('agenda.json', $editId) : null;
        $agendaRows = sort_desc_by_key($agenda, 'updated_at');

        echo '<div class="cards two">';

        echo '<section class="panel">';
        echo '<div class="josue-head">';
        echo '<h2>' . ($edit ? 'Editar contacto' : 'Nuevo contacto') . '</h2>';
        if ($edit) {
            echo '<a class="btn-secondary-mini" href="index.php?page=josue&tab=agenda">Nuevo contacto</a>';
        }
        echo '</div>';
        echo '<div class="info-strip">Agenda simple para guardar teléfonos útiles de Josué. Mantiene el mismo JSON y flujo actual.</div>';

        echo '<form method="post" class="form-grid" style="margin-top:12px;">';
        echo '<input type="hidden" name="action" value="save_agenda">';
        echo '<input type="hidden" name="id" value="' . e($edit['id'] ?? '') . '">';
        field_input('nombre', 'Nombre', $edit['nombre'] ?? '', true);
        field_input('telefono', 'Telefono', $edit['telefono'] ?? '', true);
        field_textarea('observaciones', 'Observaciones', $edit['observaciones'] ?? '', 6);
        echo '<div class="full josue-actions">';
        echo '<button class="btn-primary" type="submit">' . ($edit ? 'Guardar cambios' : 'Crear contacto') . '</button>';
        if ($edit) {
            echo '<a class="btn-secondary-mini" href="index.php?page=josue&tab=agenda">Cancelar</a>';
        }
        echo '</div>';
        echo '</form>';
        echo '</section>';

        echo '<section class="panel">';
        echo '<div class="josue-head">';
        echo '<h2>Agenda</h2>';
        echo '<span class="summary-badge">' . count($agendaRows) . ' contactos</span>';
        echo '</div>';

        if (empty($agendaRows)) {
            echo '<div class="empty">Todavía no hay contactos en la agenda.</div>';
        } else {
            render_live_filter('#agendaCards .contact-card[data-filter-text]', 'Buscar por nombre, telefono u observaciones...');
            echo '<div id="agendaCards" class="stack-list">';
            foreach ($agendaRows as $row) {
                $nombre = trim((string)($row['nombre'] ?? ''));
                $telefono = trim((string)($row['telefono'] ?? ''));
                $observaciones = trim((string)($row['observaciones'] ?? ''));
                $searchText = strtolower(trim($nombre . ' ' . $telefono . ' ' . $observaciones));

                echo '<article class="contact-card info-strip" data-filter-text="' . e($searchText) . '">';
                echo '<div class="contact-card-main">';
                echo '<div class="contact-card-body">';
                echo '<div class="contact-card-name">' . e($nombre !== '' ? $nombre : 'Sin nombre') . '</div>';
                if ($telefono !== '') {
                    echo '<div class="contact-card-phone-wrap">';
                    crm_render_phone_value($telefono, array('tel_link' => true));
                    echo '</div>';
                } else {
                    echo '<div class="muted contact-card-subline">Sin telefono</div>';
                }
                if ($observaciones !== '') {
                    echo '<div class="muted contact-card-notes">' . e($observaciones) . '</div>';
                } else {
                    echo '<div class="muted contact-card-notes">Sin observaciones</div>';
                }
                echo '</div>';

                echo '<div class="contact-card-actions">';
                if ($telefono !== '') {
                    echo '<a class="mini-link" href="tel:' . e($telefono) . '">Llamar</a>';
                }
                echo '<a class="mini-link" href="index.php?page=josue&tab=agenda&edit=' . e($row['id']) . '">Editar</a>';
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar este contacto de agenda?\')">';
                echo '<input type="hidden" name="action" value="delete_agenda">';
                echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                echo '<button class="btn-danger-mini" type="submit">Eliminar</button>';
                echo '</form>';
                echo '</div>';
                echo '</div>';

                echo '<div class="muted contact-card-meta">Actualizado: ' . e($row['updated_at'] ?? '-') . '</div>';
                echo '</article>';
            }
            echo '</div>';
        }
        echo '</section>';

        echo '</div>';
    } elseif ($tab === 'eurekas') {
        $editId = request_get('edit', '');
        $edit = $editId !== '' ? storage_find_by_id('eurekas.json', $editId) : null;
        $eurekaRows = sort_desc_by_key($eurekas, 'updated_at');
        $statusLabels = array(
            'pendiente' => 'Pendiente',
            'descartada' => 'Descartada',
            'cumplida' => 'Cumplida',
        );
        $estadoFilter = trim((string)request_get('estado', 'todas'));
        if ($estadoFilter !== 'todas' && !isset($statusLabels[$estadoFilter])) {
            $estadoFilter = 'todas';
        }
        if ($estadoFilter !== 'todas') {
            $eurekaRows = array_values(array_filter($eurekaRows, function ($row) use ($estadoFilter) {
                return trim((string)($row['estado'] ?? 'pendiente')) === $estadoFilter;
            }));
        }

        echo '<div class="cards two">';

        echo '<section class="panel">';
        echo '<div class="josue-head">';
        echo '<h2>' . ($edit ? 'Editar eureka' : 'Nueva eureka') . '</h2>';
        if ($edit) {
            echo '<a class="btn-secondary-mini" href="index.php?page=josue&tab=eurekas">Nueva eureka</a>';
        }
        echo '</div>';


        echo '<form method="post" class="form-grid" style="margin-top:12px;">';
        echo '<input type="hidden" name="action" value="save_eureka">';
        echo '<input type="hidden" name="id" value="' . e($edit['id'] ?? '') . '">';
        field_textarea('descripcion', 'Descripción', $edit['descripcion'] ?? '', 8);
        echo '<div class="full josue-actions">';
        echo '<button class="btn-primary" type="submit">' . ($edit ? 'Guardar cambios' : 'Crear eureka') . '</button>';
        if ($edit) {
            echo '<a class="btn-secondary-mini" href="index.php?page=josue&tab=eurekas">Cancelar</a>';
        }
        echo '</div>';
        echo '</form>';
        echo '</section>';

        echo '<section class="panel">';
        echo '<div class="josue-head">';
        echo '<h2>Eurekas</h2>';
        echo '<span class="summary-badge">' . count($eurekaRows) . ' items</span>';
        echo '</div>';

        echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 6px;">';
        $filterOptions = array('todas' => 'Todas', 'pendiente' => 'Pendientes', 'cumplida' => 'Cumplidas', 'descartada' => 'Descartadas');
        foreach ($filterOptions as $filterValue => $filterLabel) {
            $href = 'index.php?page=josue&tab=eurekas';
            if ($filterValue !== 'todas') {
                $href .= '&estado=' . urlencode($filterValue);
            }
            $class = ($estadoFilter === $filterValue) ? 'btn-primary' : 'btn-secondary-mini';
            echo '<a class="' . $class . '" href="' . e($href) . '">' . e($filterLabel) . '</a>';
        }
        echo '</div>';

        if (empty($eurekaRows)) {
            echo '<div class="empty">Todavía no hay eurekas guardadas.</div>';
        } else {
            render_live_filter('#eurekaCards .contact-card[data-filter-text]', 'Buscar por descripción o estado...');
            echo '<div id="eurekaCards" class="stack-list">';
            foreach ($eurekaRows as $row) {
                $descripcion = trim((string)($row['descripcion'] ?? ''));
                $estado = trim((string)($row['estado'] ?? 'pendiente'));
                $promptCodex = trim((string)($row['prompt_codex'] ?? ''));
                $promptGeneratedAt = trim((string)($row['prompt_generated_at'] ?? ''));
                if (!isset($statusLabels[$estado])) {
                    $estado = 'pendiente';
                }
                $searchText = strtolower(trim($descripcion . ' ' . $estado));

                echo '<article class="contact-card info-strip" data-filter-text="' . e($searchText) . '">';
                echo '<div class="contact-card-main">';
                echo '<div class="contact-card-body">';
                echo '<div class="contact-card-name">' . e($statusLabels[$estado]) . '</div>';
                echo '<div class="contact-card-notes">' . nl2br(e($descripcion !== '' ? $descripcion : 'Sin descripción')) . '</div>';
                echo '</div>';

                echo '<div class="contact-card-actions">';
                if ($estado !== 'pendiente') {
                    echo '<form method="post" class="inline-form">';
                    echo '<input type="hidden" name="action" value="set_eureka_estado">';
                    echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                    echo '<input type="hidden" name="estado" value="pendiente">';
                    echo '<button class="mini-link" type="submit">Pendiente</button>';
                    echo '</form>';
                }
                if ($estado !== 'cumplida') {
                    echo '<form method="post" class="inline-form">';
                    echo '<input type="hidden" name="action" value="set_eureka_estado">';
                    echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                    echo '<input type="hidden" name="estado" value="cumplida">';
                    echo '<button class="mini-link" type="submit">Cumplida</button>';
                    echo '</form>';
                }
                if ($estado !== 'descartada') {
                    echo '<form method="post" class="inline-form">';
                    echo '<input type="hidden" name="action" value="set_eureka_estado">';
                    echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                    echo '<input type="hidden" name="estado" value="descartada">';
                    echo '<button class="mini-link" type="submit">Descartar</button>';
                    echo '</form>';
                }
                echo '<form method="post" class="inline-form">';
                echo '<input type="hidden" name="action" value="generate_eureka_prompt">';
                echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                echo '<button class="mini-link" type="submit">' . e($promptCodex !== '' ? 'Regenerar prompt' : 'Generar prompt') . '</button>';
                echo '</form>';
                echo '<a class="mini-link" href="index.php?page=josue&tab=eurekas&edit=' . e($row['id']) . '">Editar</a>';
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar esta eureka?\')">';
                echo '<input type="hidden" name="action" value="delete_eureka">';
                echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                echo '<button class="btn-danger-mini" type="submit">Eliminar</button>';
                echo '</form>';
                echo '</div>';
                echo '</div>';

                if ($promptCodex !== '') {
                    echo '<details style="margin-top:12px;">';
                    echo '<summary>Prompt Codex' . ($promptGeneratedAt !== '' ? ' · ' . e(format_created_at($promptGeneratedAt)) : '') . '</summary>';
                    echo '<div class="generated-box" style="margin-top:10px;">';
                    echo '<div class="generated-box-head">';
                    echo '<h3>Prompt listo para pasar a Codex</h3>';
                    echo '<button type="button" class="btn-copy-mini" data-copy="' . e($promptCodex) . '">Copiar prompt</button>';
                    echo '</div>';
                    echo '<textarea class="generated-textarea" rows="14" readonly>' . e($promptCodex) . '</textarea>';
                    echo '</div>';
                    echo '</details>';
                }

                echo '<div class="muted contact-card-meta">Actualizado: ' . e($row['updated_at'] ?? '-') . '</div>';
                echo '</article>';
            }
            echo '</div>';
        }
        echo '</section>';

        echo '</div>';
    } else {
        $title = ($tab === 'publias') ? 'PublIas' : (($tab === 'captacion') ? 'Captacion' : (($tab === 'notas') ? 'Notas' : 'WAHA'));

        echo '<div class="josue-head">';
        echo '<h2>' . e($title) . '</h2>';
        if ($isEdit) {
            echo '<a class="btn-secondary-mini" href="index.php?page=josue&tab=' . e($tab) . '">Cancelar</a>';
        } else {
            echo '<a class="btn-wa" href="index.php?page=josue&tab=' . e($tab) . '&edit_text=1">Editar</a>';
        }
        echo '</div>';

        if ($isEdit) {
            echo '<form method="post" class="josue-form">';
            echo '<input type="hidden" name="action" value="save_josue_text">';
            echo '<input type="hidden" name="tab" value="' . e($tab) . '">';
            echo '<textarea name="text" class="josue-textarea" rows="24">' . e($text) . '</textarea>';
            echo '<div class="josue-actions">';
            echo '<button class="btn-primary josue-save-btn" type="submit">Guardar</button>';
            echo '</div>';
            echo '</form>';
        } else {
            echo '<div class="josue-text-view">';
            echo nl2br(e($text));
            echo '</div>';
        }
    }

    echo '</div>';
    echo '</section>';
}

function render_casawasap_page() {
    $contactos = storage_read('casawasap_contactos.json');
    $pagos = storage_read('casawasap_pagos.json');

    $editId = request_get('edit', '');
    $edit = $editId !== '' ? storage_find_by_id('casawasap_contactos.json', $editId) : null;

    $clienteId = request_get('cliente_id', $edit ? $edit['id'] : '');
    $clientes = get_casawasap_active_clientes();

    page_header('Casawasap', 'Interesados, clientes, pagos y beneficios del subproyecto');

    echo '<div class="cards two">';
    echo '<section class="panel">';
    echo '<div class="section-head">';
    echo '<div>';
    echo '<h2>' . ($edit ? 'Ficha Casawasap' : 'Nuevo interesado') . '</h2>';
    if ($edit) {
        echo '<div class="muted">Created at: <i>' . e(format_created_at(isset($edit['created_at']) ? $edit['created_at'] : '')) . '</i></div>';
    }
    echo '</div>';

    if ($edit && !empty($edit['telefono'])) {
        echo '<div class="section-head-actions">';
        echo '<a class="btn-wa" href="' . e(whatsapp_url($edit['telefono'])) . '" target="_blank" rel="noopener noreferrer">Abrir WhatsApp</a>';
        echo '</div>';
    }
    echo '</div>';

    echo '<form method="post" class="form-grid">';
    echo '<input type="hidden" name="action" value="save_casawasap_contacto">';
    echo '<input type="hidden" name="id" value="' . e($edit ? $edit['id'] : '') . '">';
    field_input('telefono', 'Teléfono', $edit ? $edit['telefono'] : '', true);
    field_input('quien_lo_trae', 'Quién lo trae', $edit ? $edit['quien_lo_trae'] : '');
    field_textarea('notas', 'Notas', $edit ? $edit['notas'] : '', 4);
    if ($edit && in_array(($edit['estado'] ?? ''), array('cliente', 'baja'), true)) {
        render_casawasap_bot_profile_fields($edit);
    }
    echo '<div class="full"><button class="btn-primary">' . (($edit && in_array(($edit['estado'] ?? ''), array('cliente', 'baja'), true)) ? 'Guardar ficha completa' : 'Guardar interesado') . '</button></div>';
    echo '</form>';

    if ($edit && in_array(($edit['estado'] ?? ''), array('cliente', 'baja'), true)) {
        echo '<hr class="sep">';
        echo '<h2>Datos de cliente Casawasap</h2>';
        echo '<div class="cards two">';
        echo '<div class="panel">';
        echo '<div><strong>Nombre:</strong> ' . e($edit['nombre'] ?? '-') . '</div>';
        echo '<div><strong>Precio:</strong> ' . e(euro($edit['precio'] ?? 0)) . '</div>';
        echo '<div><strong>Periodicidad de cobro:</strong> ' . e($edit['periodicidad_cobro'] ?? '-') . '</div>';
        echo '<div><strong>Fecha alta cliente:</strong> ' . e(format_created_at($edit['cliente_at'] ?? '')) . '</div>';
        echo '</div>';
        echo '</div>';

        $linkedBot = get_casawasap_cliente_current_bot($edit['id'] ?? '');
        echo '<div class="info-strip" style="margin-top:12px;">';
        if ($linkedBot) {
            echo '<strong>Bot vinculado actual:</strong> ' . e($linkedBot['nombre_bot'] ?? 'Bot') . ' · ';
            echo '<a class="mini-link" href="index.php?page=bots&edit=' . e($linkedBot['id'] ?? '') . '">Abrir ficha del bot</a>';
        } else {
            $createUrl = 'index.php?page=bots&linked_ref=' . urlencode(bot_build_linked_ref('casawasap_cliente', $edit['id'] ?? ''));
            echo '<strong>Sin bot individual todavía.</strong> ';
            echo '<a class="mini-link" href="' . e($createUrl) . '">Crear bot para este cliente</a>';
        }
        echo '</div>';
    }


    if ($edit && ($edit['estado'] ?? '') === 'interesado') {
        echo '<div class="mini-actions-bar">';
        echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Descartar este interesado?\')">';
        echo '<input type="hidden" name="action" value="set_casawasap_estado">';
        echo '<input type="hidden" name="id" value="' . e($edit['id']) . '">';
        echo '<input type="hidden" name="estado" value="descartado">';
        echo '<button class="btn-danger-mini">Descartar</button>';
        echo '</form>';
        echo '</div>';
    }

    if ($edit && ($edit['estado'] ?? '') === 'descartado') {
        echo '<div class="mini-actions-bar">';
        echo '<form method="post" class="inline-form">';
        echo '<input type="hidden" name="action" value="set_casawasap_estado">';
        echo '<input type="hidden" name="id" value="' . e($edit['id']) . '">';
        echo '<input type="hidden" name="estado" value="interesado">';
        echo '<button class="btn-ok-mini">Reactivar interesado</button>';
        echo '</form>';
        echo '</div>';
    }

    if ($edit && ($edit['estado'] ?? '') === 'cliente') {
        echo '<div class="mini-actions-bar">';
        echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Dar de baja este cliente?\')">';
        echo '<input type="hidden" name="action" value="baja_casawasap_cliente">';
        echo '<input type="hidden" name="id" value="' . e($edit['id']) . '">';
        echo '<button class="btn-warning-mini">Dar de baja</button>';
        echo '</form>';
        echo '</div>';
    }

    if ($edit && ($edit['estado'] ?? '') === 'baja') {
        echo '<div class="mini-actions-bar">';
        echo '<form method="post" class="inline-form">';
        echo '<input type="hidden" name="action" value="alta_casawasap_cliente">';
        echo '<input type="hidden" name="id" value="' . e($edit['id']) . '">';
        echo '<button class="btn-ok-mini">Reactivar cliente</button>';
        echo '</form>';
        echo '</div>';
    }

    if ($edit && (($edit['estado'] ?? '') === 'interesado')) {
        echo '<hr class="sep">';
        echo '<h2>Pasar a cliente</h2>';
        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="action" value="convert_casawasap_cliente">';
        echo '<input type="hidden" name="id" value="' . e($edit['id']) . '">';
        field_input('nombre', 'Nombre', isset($edit['nombre']) ? $edit['nombre'] : '', true);
        field_input('precio', 'Precio', isset($edit['precio']) ? $edit['precio'] : '');
        echo '<div class="field">';
        echo '<label>Periodicidad de cobro</label>';
        echo '<select name="periodicidad_cobro">';
        foreach (array('semanal' => 'Semanal', 'mensual' => 'Mensual') as $k => $label) {
            $sel = (($edit['periodicidad_cobro'] ?? 'semanal') === $k) ? ' selected' : '';
            echo '<option value="' . e($k) . '"' . $sel . '>' . e($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        render_casawasap_bot_profile_fields($edit);
        echo '<div class="full"><button class="btn-ok-mini">Convertir en cliente</button></div>';
        echo '</form>';
    }

    if ($edit && in_array(($edit['estado'] ?? ''), array('cliente', 'baja'), true)) {
        echo '<hr class="sep">';
        echo '<div class="money-callout">';
        echo '<div class="money-title">Registrar pago / beneficio</div>';
        echo '<form method="post" class="lead-quick-inline" onsubmit="return confirmLeadSubmit(this);">';
        echo '<input type="hidden" name="action" value="casawasap_add_pago">';
        echo '<input type="hidden" name="cliente_id" value="' . e($edit['id']) . '">';
        echo '<input type="hidden" name="fecha_hora" value="' . e(today_datetime_local()) . '">';
        echo '<input type="text" name="importe" value="100" class="money-input">';
        echo '<input type="text" name="observaciones" placeholder="Observación opcional" class="money-note">';
        echo '<button class="btn-money">€ Añadir pago</button>';
        echo '</form>';
        echo '<div class="muted">Cliente Casawasap: ' . e(isset($edit['nombre']) ? $edit['nombre'] : '') . '</div>';
        echo '</div>';

        $clientePagos = get_casawasap_pagos_for_cliente($edit['id']);
        $clientePagos = sort_desc_by_key($clientePagos, 'fecha_hora');
        $clienteTotals = casawasap_pago_totals($clientePagos);

        if (empty($clientePagos)) {
            echo '<div class="empty">Todavía no hay pagos para este cliente.</div>';
        } else {
            render_live_filter('#casawasapClientePagosRows tr[data-filter-text]', 'Buscar pago...');
            echo '<div class="table-wrap"><table><thead><tr>';
            echo '<th>Fecha</th><th>Importe</th><th>Observación</th><th>Acciones</th>';
            echo '</tr></thead><tbody id="casawasapClientePagosRows">';
            foreach ($clientePagos as $pago) {
                $searchText = strtolower(trim(
                    ($pago['fecha_hora'] ?? '') . ' ' .
                    ($pago['observaciones'] ?? '') . ' ' .
                    ($pago['importe'] ?? '')
                ));
                echo '<tr data-filter-text="' . e($searchText) . '">';
                echo '<td>' . e(str_replace('T', ' ', $pago['fecha_hora'])) . '</td>';
                echo '<td><span class="money-chip">' . e(euro($pago['importe'])) . '</span></td>';
                echo '<td>' . e(isset($pago['observaciones']) ? $pago['observaciones'] : '') . '</td>';
                echo '<td>';
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar este pago?\')">';
                echo '<input type="hidden" name="action" value="delete_casawasap_pago">';
                echo '<input type="hidden" name="id" value="' . e($pago['id']) . '">';
                echo '<input type="hidden" name="cliente_id" value="' . e($edit['id']) . '">';
                echo '<button class="btn-danger-mini">Eliminar</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';

            echo '<div class="totals-bar">';
            echo '<div><strong>Total pagos:</strong> ' . e($clienteTotals['count']) . '</div>';
            echo '<div><strong>Total dinero:</strong> <span class="money-chip big">' . e(euro($clienteTotals['money'])) . '</span></div>';
            echo '</div>';
        }
    }

    echo '</section>';

    echo '<section class="panel">';
    echo '<h2>Listado Casawasap</h2>';
    if (empty($contactos)) {
        echo '<div class="empty">No hay interesados todavía.</div>';
    } else {
        $contactos = sort_desc_by_key($contactos, 'created_at');
        render_live_filter('#casawasapRows tr[data-filter-text]', 'Buscar contacto Casawasap...');
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Teléfono</th><th>Quién lo trae</th><th>Estado</th><th>Cliente</th><th>Periodicidad</th><th>Notas</th><th>Acciones</th>';
        echo '</tr></thead><tbody id="casawasapRows">';
        foreach ($contactos as $row) {
            $searchText = strtolower(trim(
                ($row['telefono'] ?? '') . ' ' .
                ($row['quien_lo_trae'] ?? '') . ' ' .
                ($row['nombre'] ?? '') . ' ' .
                ($row['periodicidad_cobro'] ?? '') . ' ' .
                ($row['notas'] ?? '') . ' ' .
                ($row['estado'] ?? '')
            ));
            echo '<tr class="' . (($row['estado'] ?? '') === 'descartado' ? 'row-soft-danger' : '') . '" data-filter-text="' . e($searchText) . '">';

            echo '<td>';
            crm_render_phone_value((string)($row['telefono'] ?? ''), array('strong' => true));
            echo '<br><i class="muted">(' . e(format_created_at(isset($row['created_at']) ? $row['created_at'] : '')) . ')</i>';
            echo '</td>';
            echo '<td>' . e(isset($row['quien_lo_trae']) ? $row['quien_lo_trae'] : '') . '</td>';

            $estadoCasa = $row['estado'] ?? 'interesado';
            $estadoLabel = 'Interesado';
            if ($estadoCasa === 'cliente') $estadoLabel = 'Cliente';
            if ($estadoCasa === 'descartado') $estadoLabel = 'Descartado';
            if ($estadoCasa === 'baja') $estadoLabel = 'Baja';

            echo '<td><span class="pill state-' . e($estadoCasa) . '">' . e($estadoLabel) . '</span></td>';

            echo '<td>';
            echo e(isset($row['nombre']) ? $row['nombre'] : '-');
            if (in_array(($row['estado'] ?? ''), array('cliente', 'baja'), true)) {
                echo '<br><span class="muted">' . e(euro(isset($row['precio']) ? $row['precio'] : 0)) . '</span>';
            }
            echo '</td>';

            echo '<td>' . e($row['periodicidad_cobro'] ?? '-') . '</td>';
            echo '<td>' . e(isset($row['notas']) ? $row['notas'] : '') . '</td>';
            echo '<td><a class="mini-link" href="index.php?page=casawasap&edit=' . e($row['id']) . '">Abrir ficha</a></td>';

            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
    echo '</div>';

    $from = request_get('from', business_current_month_key() . '-01');

    $to = request_get('to', business_today_date());
    $filterClienteId = request_get('cliente_id', '');

    $pagosFiltrados = array();
    foreach ($pagos as $pago) {
        if ($filterClienteId !== '' && (!isset($pago['cliente_id']) || $pago['cliente_id'] !== $filterClienteId)) continue;
        $pagosFiltrados[] = $pago;
    }
    $pagosFiltrados = filter_rows_between_dates($pagosFiltrados, 'fecha_hora', $from, $to);
    $pagosFiltrados = sort_desc_by_key($pagosFiltrados, 'fecha_hora');
    $totals = casawasap_pago_totals($pagosFiltrados);

    echo '<section class="panel panel-space">';
    echo '<h2>Consulta de beneficios Casawasap</h2>';
    echo '<form method="get" class="toolbar">';
    echo '<input type="hidden" name="page" value="casawasap">';
    echo '<div class="field"><label>Desde</label><input type="date" name="from" value="' . e($from) . '"></div>';
    echo '<div class="field"><label>Hasta</label><input type="date" name="to" value="' . e($to) . '"></div>';
    echo '<div class="field"><label>Cliente</label><select name="cliente_id">';
    echo '<option value="">Todos</option>';
    foreach ($clientes as $cliente) {
        $sel = ($filterClienteId === $cliente['id']) ? ' selected' : '';
        echo '<option value="' . e($cliente['id']) . '"' . $sel . '>' . e(isset($cliente['nombre']) ? $cliente['nombre'] : $cliente['telefono']) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="field field-btn"><label>&nbsp;</label><button class="btn-primary">Filtrar</button></div>';
    echo '</form>';

    echo '<div class="cards three">';
    dashboard_card('Clientes Casawasap', count($clientes));
    dashboard_card('Pagos filtrados', $totals['count']);
    dashboard_card('Beneficio Casawasap', euro($totals['money']), true);
    echo '</div>';

    if (empty($pagosFiltrados)) {
        echo '<div class="empty">No hay pagos con esos filtros.</div>';
    } else {
        render_live_filter('#casawasapBeneficiosRows tr[data-filter-text]', 'Buscar movimiento Casawasap...');
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Fecha</th><th>Cliente</th><th>Importe</th><th>Observación</th>';
        echo '</tr></thead><tbody id="casawasapBeneficiosRows">';
        foreach ($pagosFiltrados as $pago) {
            $searchText = strtolower(trim(
                ($pago['fecha_hora'] ?? '') . ' ' .
                ($pago['cliente_nombre'] ?? '') . ' ' .
                ($pago['observaciones'] ?? '') . ' ' .
                ($pago['importe'] ?? '')
            ));
            echo '<tr data-filter-text="' . e($searchText) . '">';
            echo '<td>' . e(str_replace('T', ' ', $pago['fecha_hora'])) . '</td>';
            echo '<td>' . e(isset($pago['cliente_nombre']) ? $pago['cliente_nombre'] : '') . '</td>';
            echo '<td><span class="money-chip">' . e(euro($pago['importe'])) . '</span></td>';
            echo '<td>' . e(isset($pago['observaciones']) ? $pago['observaciones'] : '') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
}

function render_jostal_page() {
    $tab = request_get('tab', 'interesadas');
    $allowed = array('interesadas', 'clientas', 'ventas', 'informes');
    if (!in_array($tab, $allowed, true)) $tab = 'interesadas';

    $legacyConvertId = request_get('convert', '');
    if ($tab === 'interesadas' && $legacyConvertId !== '') {
        redirect_to('index.php?page=jostal&tab=clientas&new_from_interesada=' . urlencode($legacyConvertId));
    }

    $soloEnCasa = request_get('solo_en_casa', '0') === '1';

    $interesadas = storage_read('jostal_interesadas.json');
    $clientas = storage_read('jostal_clientas.json');
    $leads = storage_read('jostal_leads.json');
    $ventas = storage_read('jostal_ventas.json');

    page_header('Jostal', 'Alquileres, servicios y ventas del sub-negocio');

    echo '<section class="panel panel-jostal">';
    echo '<div class="subtabs">';
    echo '<a class="subtab ' . ($tab === 'interesadas' ? 'active' : '') . '" href="index.php?page=jostal&tab=interesadas">Interesadas</a>';
    echo '<div class="subtab-split' . ($tab === 'clientas' ? ' is-active' : '') . '">';
    echo '<a class="subtab-split-main' . ($tab === 'clientas' && !$soloEnCasa ? ' is-current' : '') . '" href="index.php?page=jostal&tab=clientas">Clientas</a>';
    echo '<a class="subtab-split-side' . ($tab === 'clientas' && $soloEnCasa ? ' is-current' : '') . '" href="index.php?page=jostal&tab=clientas&solo_en_casa=1">En casa</a>';
    echo '</div>';
    echo '<a class="subtab ' . ($tab === 'ventas' ? 'active' : '') . '" href="index.php?page=jostal&tab=ventas">Ventas</a>';
    echo '<a class="subtab ' . ($tab === 'informes' ? 'active' : '') . '" href="index.php?page=jostal&tab=informes">GridMensual</a>';
    echo '</div>';

    echo '<div class="subtab-content">';

    if ($tab === 'interesadas') {
        $editId = request_get('edit', '');
        $edit = $editId !== '' ? storage_find_by_id('jostal_interesadas.json', $editId) : null;

        $convertId = request_get('convert', '');
        $convert = $convertId !== '' ? storage_find_by_id('jostal_interesadas.json', $convertId) : null;

        echo '<div class="cards two">';
        echo '<section class="panel">';
        echo '<div class="section-head">';
        echo '<div>';
        echo '<h2>' . ($edit ? 'Editar interesada Jostal' : 'Nueva interesada Jostal') . '</h2>';
        if ($edit) {
            echo '<div class="muted">Created at: <i>' . e(format_created_at($edit['created_at'] ?? '')) . '</i></div>';
        }
        echo '</div>';

        if ($edit && !empty($edit['telefono'])) {
            echo '<div class="section-head-actions">';
            echo '<a class="btn-wa" href="' . e(whatsapp_url($edit['telefono'])) . '" target="_blank" rel="noopener noreferrer">Abrir WhatsApp</a>';
            echo '</div>';
        }
        echo '</div>';
        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="action" value="save_jostal_interesada">';
        echo '<input type="hidden" name="id" value="' . e($edit ? $edit['id'] : '') . '">';
        field_input('telefono', 'Teléfono', $edit ? $edit['telefono'] : '', true);
        field_input('fecha', 'Fecha', $edit ? $edit['fecha'] : business_today_date(), true, 'date');

        echo '<div class="field">';
        echo '<label>Interesada en</label>';
        echo '<select name="interesada_en">';
        $current = $edit ? $edit['interesada_en'] : 'indiferente';
        foreach (array('alquiler' => 'Alquiler', 'plaza' => 'Plaza', 'indiferente' => 'Indiferente') as $k => $label) {
            $sel = ($current === $k) ? ' selected' : '';
            echo '<option value="' . e($k) . '"' . $sel . '>' . e($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        field_textarea('observaciones', 'Observaciones', $edit ? $edit['observaciones'] : '', 4);
        echo '<div class="full"><button class="btn-primary">Guardar interesada</button></div>';
        echo '</form>';

        if ($convert && (!isset($convert['estado']) || $convert['estado'] !== 'convertida')) {
            echo '<hr class="sep">';
            echo '<h2>Convertir a clienta</h2>';
            echo '<form method="post" class="form-grid">';
            echo '<input type="hidden" name="action" value="convert_jostal_clienta">';
            echo '<input type="hidden" name="interesada_id" value="' . e($convert['id']) . '">';
            field_input('nombre', 'Nombre', '', true);

            echo '<div class="field">';
            echo '<label>Modo</label>';
            echo '<select name="modo">';
            $defaultModo = ($convert['interesada_en'] === 'alquiler' || $convert['interesada_en'] === 'plaza') ? $convert['interesada_en'] : 'plaza';
            foreach (array('plaza' => 'Plaza', 'alquiler' => 'Alquiler') as $k => $label) {
                $sel = ($defaultModo === $k) ? ' selected' : '';
                echo '<option value="' . e($k) . '"' . $sel . '>' . e($label) . '</option>';
            }
            echo '</select>';
            echo '</div>';

            field_input('first_arrival_date', 'Primera llegada a la casa', business_today_date(), true, 'date');
            echo '<div class="field">';
            echo '<label>Día semanal de cobro</label>';
            echo '<select name="rent_due_weekday">';
            $defaultDueWeekday = jostal_weekday_from_date(business_today_date());
            foreach (jostal_weekday_options() as $weekday => $weekdayLabel) {
                $sel = ((int)$defaultDueWeekday === (int)$weekday) ? ' selected' : '';
                echo '<option value="' . e((string)$weekday) . '"' . $sel . '>' . e($weekdayLabel) . '</option>';
            }
            echo '</select>';
            echo '<div class="field-help">Por defecto se usa el mismo día de la semana en que entra en la casa.</div>';
            echo '</div>';
            echo '<div class="full"><button class="btn-ok-mini">Convertir en clienta</button></div>';
            echo '</form>';
        }

        echo '</section>';

        echo '<section class="panel">';
        echo '<h2>Listado interesadas Jostal</h2>';
        if (empty($interesadas)) {
            echo '<div class="empty">No hay interesadas todavía.</div>';
        } else {
            $interesadas = array_values(array_filter($interesadas, function ($row) {
                return ($row['estado'] ?? '') !== 'convertida';
            }));
            $interesadas = sort_desc_by_key($interesadas, 'created_at');
            render_live_filter('#jostalInteresadasRows tr[data-filter-text]', 'Buscar interesada Jostal...');
            echo '<div class="table-wrap"><table><thead><tr>';
            echo '<th>Teléfono</th><th>Interés</th><th>Fecha</th><th>Observaciones</th><th>Acciones</th>';
            echo '</tr></thead><tbody id="jostalInteresadasRows">';
            foreach ($interesadas as $row) {
                $searchText = strtolower(trim(
                    ($row['telefono'] ?? '') . ' ' .
                    ($row['interesada_en'] ?? '') . ' ' .
                    ($row['fecha'] ?? '') . ' ' .
                    ($row['observaciones'] ?? '') . ' ' .
                    ($row['estado'] ?? '')
                ));
                echo '<tr class="' . (($row['estado'] ?? '') === 'descartada' ? 'row-soft-danger' : '') . '" data-filter-text="' . e($searchText) . '">';
                echo '<td>';
                crm_render_phone_value((string)($row['telefono'] ?? ''), array('strong' => true));
                echo '<br><i class="muted">(' . e(format_created_at($row['created_at'] ?? '')) . ')</i>';
                echo '</td>';
                echo '<td>' . e($row['interesada_en']) . '</td>';
                echo '<td>' . e($row['fecha']) . '</td>';
                echo '<td>' . e($row['observaciones']) . '</td>';

                echo '<td>';
                echo '<a class="mini-link" href="index.php?page=jostal&tab=interesadas&edit=' . e($row['id']) . '">Editar</a>';

                if (($row['estado'] ?? '') === 'descartada') {
                    echo ' · <form method="post" class="inline-form">';
                    echo '<input type="hidden" name="action" value="reactivate_jostal_interesada">';
                    echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                    echo '<button class="btn-ok-mini">Reactivar</button>';
                    echo '</form>';
                } elseif (($row['estado'] ?? '') !== 'convertida') {
                    echo ' · <a class="mini-link success-link" href="index.php?page=jostal&tab=clientas&new_from_interesada=' . e($row['id']) . '">Convertir</a>';
                    echo ' · <form method="post" class="inline-form" onsubmit="return confirm(\'¿Descartar interesada?\')">';
                    echo '<input type="hidden" name="action" value="discard_jostal_interesada">';
                    echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                    echo '<button class="btn-danger-mini">Descartar</button>';
                    echo '</form>';
                } else {
                    echo '<br><span class="muted">Convertida</span>';
                }
                echo '</td>';


                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';
        echo '</div>';
    }

if ($tab === 'clientas') {
    $editId = request_get('edit', '');
    $edit = $editId !== '' ? storage_find_by_id('jostal_clientas.json', $editId) : null;
    $newFromInteresadaId = request_get('new_from_interesada', '');
    $newFromInteresada = null;

    if (!$edit && $newFromInteresadaId !== '') {
        $newFromInteresada = storage_find_by_id('jostal_interesadas.json', $newFromInteresadaId);
        if ($newFromInteresada && ($newFromInteresada['estado'] ?? '') === 'convertida' && !empty($newFromInteresada['clienta_id'])) {
            redirect_to('index.php?page=jostal&tab=clientas&edit=' . urlencode($newFromInteresada['clienta_id']));
        }
    }

    echo '<div class="cards two">';
    echo '<section class="panel">';
    if ($edit || $newFromInteresada) {
        $isNew = !$edit;
        $formSource = $edit ? $edit : $newFromInteresada;

        echo '<div class="section-head">';
        echo '<div>';
        echo '<h2>' . ($isNew ? 'Nueva clienta Jostal desde interesada' : 'Ficha clienta Jostal') . '</h2>';
        if ($edit) {
            echo '<div class="muted">Created at: <i>' . e(format_created_at($edit['created_at'] ?? '')) . '</i></div>';
        } elseif ($newFromInteresada) {
            echo '<div class="muted">Alta precargada desde la interesada seleccionada.</div>';
        }
        echo '</div>';

        if (!empty($formSource['telefono'])) {
            echo '<div class="section-head-actions">';
            echo '<a class="btn-wa" href="' . e(whatsapp_url($formSource['telefono'])) . '" target="_blank" rel="noopener noreferrer">Abrir WhatsApp</a>';
            echo '</div>';
        }
        echo '</div>';

        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="action" value="save_jostal_clienta">';
        echo '<input type="hidden" name="id" value="' . e($edit['id'] ?? '') . '">';
        echo '<input type="hidden" name="source_interesada_id" value="' . e($isNew ? ($newFromInteresada['id'] ?? '') : ($edit['source_interesada_id'] ?? '')) . '">';
        field_input('nombre', 'Nombre', $edit['nombre'] ?? '', true);
        field_input('telefono', 'Teléfono', $edit['telefono'] ?? ($newFromInteresada['telefono'] ?? ''), true);

        echo '<div class="field">';
        echo '<label>Modo</label>';
        echo '<select name="modo">';
        if ($edit) {
            $current = $edit['modo'] ?? 'plaza';
        } else {
            $current = (($newFromInteresada['interesada_en'] ?? '') === 'alquiler' || ($newFromInteresada['interesada_en'] ?? '') === 'plaza')
                ? $newFromInteresada['interesada_en']
                : 'plaza';
        }
        foreach (array('plaza' => 'Plaza', 'alquiler' => 'Alquiler') as $k => $label) {
            $sel = ($current === $k) ? ' selected' : '';
            echo '<option value="' . e($k) . '"' . $sel . '>' . e($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div class="field">';
        echo '<label>Día semanal de cobro</label>';
        echo '<select name="rent_due_weekday">';
        $dueWeekdayCurrent = $edit ? jostal_alquiler_due_weekday($edit) : jostal_weekday_from_date(business_today_date());
        if ($dueWeekdayCurrent < 1 || $dueWeekdayCurrent > 7) {
            $dueWeekdayCurrent = 1;
        }
        foreach (jostal_weekday_options() as $weekday => $weekdayLabel) {
            $sel = ((int)$dueWeekdayCurrent === (int)$weekday) ? ' selected' : '';
            echo '<option value="' . e((string)$weekday) . '"' . $sel . '>' . e($weekdayLabel) . '</option>';
        }
        echo '</select>';
        echo '<div class="field-help">Solo aplica al modo alquiler. Se puede ajustar cuando quieras.</div>';
        echo '</div>';

        if ($isNew) {
            field_input('first_arrival_date', 'Primera llegada a la casa', business_today_date(), true, 'date');
        }

        field_textarea('observaciones', 'Observaciones', $edit['observaciones'] ?? ($newFromInteresada['observaciones'] ?? ''), 4);
        echo '<div class="full"><button class="btn-primary">' . ($isNew ? 'Crear clienta' : 'Guardar clienta') . '</button></div>';
        echo '</form>';

        if ($edit) {
            $paymentInfo = jostal_alquiler_payment_info($edit);
            if (!empty($paymentInfo['enabled'])) {
                echo '<div class="info-strip" style="margin-bottom:12px;">';
                echo '<strong>Alquiler en casa</strong><br>';
                echo 'Entró el ' . e((string)$paymentInfo['entry_date']) . ' (' . e((string)$paymentInfo['entry_weekday_label']) . ')<br>';
                echo 'Cobra cada ' . e((string)$paymentInfo['due_weekday_label']) . '<br>';
                echo 'Próximo pago: ' . e((string)$paymentInfo['next_due_date']);
                if (!empty($paymentInfo['due_today'])) {
                    echo ' · <strong>toca pagar hoy</strong>';
                } else {
                    echo ' · faltan <strong>' . e((string)$paymentInfo['days_left']) . '</strong> día' . ((int)$paymentInfo['days_left'] === 1 ? '' : 's');
                }
                echo '<form method="post" class="inline-form" style="margin-top:10px;">';
                echo '<input type="hidden" name="action" value="jostal_update_rent_due_weekday">';
                echo '<input type="hidden" name="id" value="' . e($edit['id']) . '">';
                echo '<label class="inline-label">Cambiar día cobro</label>';
                echo '<select name="rent_due_weekday">';
                foreach (jostal_weekday_options() as $weekday => $weekdayLabel) {
                    $sel = ((int)($paymentInfo['due_weekday'] ?? 0) === (int)$weekday) ? ' selected' : '';
                    echo '<option value="' . e((string)$weekday) . '"' . $sel . '>' . e($weekdayLabel) . '</option>';
                }
                echo '</select>';
                echo '<button class="btn-secondary-mini" type="submit">Actualizar</button>';
                echo '</form>';
                echo '</div>';
            }

            echo '<hr class="sep">';
            echo '<div class="money-callout">';
            echo '<div class="money-title">Registrar lead Jostal</div>';
            echo '<form method="post" class="lead-quick-inline lead-quick-inline-jostal" onsubmit="return confirmLeadSubmit(this);">';
            echo '<input type="hidden" name="action" value="jostal_add_lead">';
            echo '<input type="hidden" name="clienta_id" value="' . e($edit['id']) . '">';
            echo '<label class="inline-label">Fecha (día/mes/año)</label>';
            echo '<input type="date" name="created_at_date" value="' . e(business_today_date()) . '" class="money-date">';
            echo '<label class="inline-label">Hora</label>';
            echo '<input type="time" name="created_at_time" value="' . e(date('H:i')) . '" class="money-date">';
            echo '<input type="text" name="precio" value="10" class="money-input">';
            echo '<input type="text" name="observacion" placeholder="Observación del lead" class="money-note">';
            echo '<button class="btn-money">€ Añadir lead</button>';
            echo '</form>';
            echo '</div>';

            $enCasa = jostal_clienta_en_casa($edit);

            echo '<div class="mini-actions-bar">';
            if ($enCasa) {
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Registrar salida de la casa?\')">';
                echo '<input type="hidden" name="action" value="jostal_salida_casa">';
                echo '<input type="hidden" name="id" value="' . e($edit['id']) . '">';
                echo '<input type="hidden" name="salida" value="' . e(business_today_date()) . '">';
                echo '<button class="btn-warning-mini">Marcar que se fue de la casa</button>';
                echo '</form>';
                echo '<div class="info-strip">Actualmente está en la casa.</div>';
            } else {
                echo '<form method="post" class="inline-form">';
                echo '<input type="hidden" name="action" value="jostal_reactivar_casa">';
                echo '<input type="hidden" name="id" value="' . e($edit['id']) . '">';
                echo '<label class="inline-label">Vuelve en</label>';
                echo '<input type="date" name="entrada" value="' . e(business_today_date()) . '">';
                echo '<button class="btn-ok-mini">Reactivar entrada</button>';
                echo '</form>';
                echo '<div class="info-strip">Actualmente no está en la casa.</div>';
            }

            echo '</div>';

            $periodos = jostal_periodos_estancia($edit);
            echo '<hr class="sep">';
            echo '<h2>Periodos en la casa</h2>';
            if (empty($periodos)) {
                echo '<div class="empty">Todavía no hay periodos registrados.</div>';
            } else {
                render_live_filter('#jostalPeriodosRows tr[data-filter-text]', 'Buscar periodo...');
                echo '<div class="table-wrap"><table><thead><tr><th>Entrada</th><th>Salida</th><th>Estado</th></tr></thead><tbody id="jostalPeriodosRows">';
                foreach (array_reverse($periodos) as $p) {
                    $periodoEstado = (($p['salida'] ?? '') === '' ? 'En casa' : 'Finalizado');
                    $searchText = strtolower(trim(($p['entrada'] ?? '') . ' ' . ($p['salida'] ?? '') . ' ' . $periodoEstado));
                    echo '<tr data-filter-text="' . e($searchText) . '">';
                    echo '<td>' . e($p['entrada'] ?? '') . '</td>';
                    echo '<td>' . e(($p['salida'] ?? '') !== '' ? $p['salida'] : '-') . '</td>';
                    echo '<td>' . e(($p['salida'] ?? '') === '' ? 'En casa' : 'Finalizado') . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }

            echo '<hr class="sep">';
            echo '<h2>LEADS</h2>';

            $clientaLeads = get_jostal_leads_for_clienta($edit['id']);
            $clientaLeads = sort_desc_by_key($clientaLeads, 'created_at');

            $editLeadId = trim((string)request_get('editlead', ''));

            if (empty($clientaLeads) && $editLeadId === '') {
                echo '<div class="empty">Todavía no hay leads para esta clienta.</div>';
            } else {
                echo '<div class="table-wrap"><table><thead><tr>';
                echo '<th>Fecha</th><th>Precio</th><th>Observación</th><th></th>';
                echo '</tr></thead><tbody>';
                foreach ($clientaLeads as $lead) {
                    $lid = $lead['id'] ?? '';
                    $isEditing = ($editLeadId !== '' && $editLeadId === $lid);

                    if ($isEditing) {
                        $leadDateVal = substr((string)($lead['created_at'] ?? ''), 0, 10);
                        $leadTimeVal = substr((string)($lead['created_at'] ?? ''), 11, 5);
                        echo '<tr style="background:#fef3c7;">';
                        echo '<td colspan="4">';
                        echo '<form method="post" class="lead-quick-inline lead-quick-inline-jostal" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">';
                        echo '<input type="hidden" name="action" value="jostal_edit_lead">';
                        echo '<input type="hidden" name="lead_id" value="' . e($lid) . '">';
                        echo '<input type="hidden" name="clienta_id" value="' . e($edit['id']) . '">';
                        echo '<label class="inline-label">Fecha</label>';
                        echo '<input type="date" name="created_at_date" value="' . e($leadDateVal) . '" class="money-date">';
                        echo '<label class="inline-label">Hora</label>';
                        echo '<input type="time" name="created_at_time" value="' . e($leadTimeVal) . '" class="money-date">';
                        echo '<label class="inline-label">€</label>';
                        echo '<input type="text" name="precio" value="' . e((string)($lead['precio'] ?? '0')) . '" class="money-input" style="width:60px;">';
                        echo '<label class="inline-label">Obs</label>';
                        echo '<input type="text" name="observacion" value="' . e($lead['observacion'] ?? '') . '" class="money-note">';
                        echo '<button class="btn-ok-mini">Guardar</button>';
                        echo ' <a href="' . e('index.php?page=jostal&tab=clientas&edit=' . urlencode($edit['id'])) . '" class="btn-secondary-mini">Cancelar</a>';
                        echo '</form>';
                        echo '</td>';
                        echo '</tr>';
                    } else {
                        echo '<tr>';
                        echo '<td>' . e(format_created_at($lead['created_at'] ?? '')) . '</td>';
                        echo '<td><span class="money-chip">' . e(euro($lead['precio'] ?? 0)) . '</span></td>';
                        echo '<td>' . e($lead['observacion'] ?? '') . '</td>';
                        echo '<td><a class="btn-secondary-mini" href="' . e('index.php?page=jostal&tab=clientas&edit=' . urlencode($edit['id']) . '&editlead=' . urlencode($lid)) . '">Editar</a></td>';
                        echo '</tr>';
                    }
                }
                echo '</tbody></table></div>';
            }
        }
    } else {
        echo '<h2>Clientas Jostal</h2>';
        echo '<div class="empty">Las clientas de Jostal solo pueden crearse desde interesadas. Usa el botón Convertir desde el listado de interesadas.</div>';
    }
    echo '</section>';

      
        echo '<section class="panel">';
        echo '<div class="section-head">';
        echo '<div>';
        echo '<h2>Listado clientas Jostal</h2>';
        echo '<div class="muted">Puedes alternar entre ver todas o solo las que actualmente están en casa.</div>';
        echo '</div>';
        echo '<div class="section-head-actions">';
        if ($soloEnCasa) {
            echo '<a class="btn-secondary-mini" href="index.php?page=jostal&tab=clientas">Ver todas</a>';
        } else {
            echo '<a class="btn-primary" href="index.php?page=jostal&tab=clientas&solo_en_casa=1">Solo en casa</a>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="field jostal-search-field">';
        echo '<label>Buscar por nombre</label>';
        echo '<input type="text" class="js-live-filter" data-target-selector="#jostalClientasRows tr" placeholder="Escribe el nombre...">';
        echo '</div>';

        if (empty($clientas)) {
            echo '<div class="empty">No hay clientas todavía.</div>';
        } else {
            $clientas = sort_desc_by_key($clientas, 'created_at');

            if ($soloEnCasa) {
                $clientas = array_values(array_filter($clientas, function ($row) {
                    return jostal_clienta_en_casa($row);
                }));
            }

            if (empty($clientas)) {
                echo '<div class="empty">No hay clientas en casa con el filtro actual.</div>';
            } else {
                echo '<div class="table-wrap"><table><thead><tr>';
                echo '<th>Nombre</th><th>Teléfono</th><th>Modo</th><th>Casa</th><th>Observaciones</th><th>Acciones</th>';
                echo '</tr></thead><tbody id="jostalClientasRows">';

                foreach ($clientas as $row) {
                    $rawSearchText = trim(
                        ($row['nombre'] ?? '') . ' ' .
                        ($row['telefono'] ?? '') . ' ' .
                        ($row['modo'] ?? '') . ' ' .
                        ($row['observaciones'] ?? '') . ' ' .
                        (jostal_clienta_en_casa($row) ? 'en casa' : 'fuera')
                    );
                    $searchText = function_exists('mb_strtolower')
                        ? mb_strtolower($rawSearchText, 'UTF-8')
                        : strtolower($rawSearchText);

                    echo '<tr data-filter-text="' . e($searchText) . '">';
                    echo '<td><strong>' . e($row['nombre'] ?? '') . '</strong></td>';
                    echo '<td>'; crm_render_phone_value((string)($row['telefono'] ?? '')); echo '</td>';
                    echo '<td>' . e($row['modo'] ?? '') . '</td>';
                    echo '<td>' . e(jostal_clienta_en_casa($row) ? 'En casa' : 'Fuera') . '</td>';
                    echo '<td>' . e($row['observaciones'] ?? '') . '</td>';
                    echo '<td><a class="mini-link" href="index.php?page=jostal&tab=clientas&edit=' . e($row['id']) . '">Abrir ficha</a></td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }
        }
        echo '</section>';
        echo '</div>';
    }

    if ($tab === 'ventas') {
        echo '<div class="cards two">';
        echo '<section class="panel">';
        echo '<h2>Nueva venta Jostal</h2>';
        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="action" value="jostal_add_venta">';
        field_input('descripcion', 'Descripción', '', true);
        field_input('precio', 'Precio', '', true);
        echo '<div class="full"><button class="btn-money">€ Guardar venta</button></div>';
        echo '</form>';
        echo '</section>';

        echo '<section class="panel">';
        echo '<h2>Listado ventas Jostal</h2>';
        if (empty($ventas)) {
            echo '<div class="empty">No hay ventas todavía.</div>';
        } else {
            $ventas = sort_desc_by_key($ventas, 'created_at');
            render_live_filter('#jostalVentasRows tr[data-filter-text]', 'Buscar venta Jostal...');
            echo '<div class="table-wrap"><table><thead><tr>';
            echo '<th>Descripción</th><th>Precio</th><th>Created at</th>';
            echo '</tr></thead><tbody id="jostalVentasRows">';
            foreach ($ventas as $venta) {
                $searchText = strtolower(trim(
                    ($venta['descripcion'] ?? '') . ' ' .
                    ($venta['precio'] ?? '') . ' ' .
                    ($venta['created_at'] ?? '')
                ));
                echo '<tr data-filter-text="' . e($searchText) . '">';
                echo '<td>' . e($venta['descripcion'] ?? '') . '</td>';
                echo '<td><span class="money-chip">' . e(euro($venta['precio'] ?? 0)) . '</span></td>';
                echo '<td>' . e(format_created_at($venta['created_at'] ?? '')) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';
        echo '</div>';
    }

if ($tab === 'informes') {
    $month = request_get('month', business_current_month_key());
    $monthTs = strtotime($month . '-01');
    if (!$monthTs) $monthTs = strtotime(business_current_month_key() . '-01');

    $daysInMonth = (int)date('t', $monthTs);
    list($monthStart, $monthEnd) = business_month_bounds(date('Y-m', $monthTs));

    $itemsByDay = array();
    for ($d = 1; $d <= $daysInMonth; $d++) $itemsByDay[$d] = array();

    foreach ($leads as $lead) {
        $ts = strtotime(str_replace('T', ' ', $lead['created_at'] ?? ''));
        if (!$ts) continue;
        if (business_month_key_from_ts($ts) !== $month) continue;

        $businessDayKey = business_day_key_from_ts($ts);
        $day = (int)date('j', strtotime($businessDayKey));

        $itemsByDay[$day][] = array(
            'type' => 'lead',
            'clienta_id' => $lead['clienta_id'] ?? '',
            'clienta_nombre' => $lead['clienta_nombre'] ?? 'Sin clienta',
            'precio' => (float)($lead['precio'] ?? 0),
            'observacion' => $lead['observacion'] ?? '',
            'created_at' => $lead['created_at'] ?? ''
        );
    }

    foreach ($ventas as $venta) {
        $ts = strtotime(str_replace('T', ' ', $venta['created_at'] ?? ''));
        if (!$ts) continue;
        if (business_month_key_from_ts($ts) !== $month) continue;

        $businessDayKey = business_day_key_from_ts($ts);
        $day = (int)date('j', strtotime($businessDayKey));

        $itemsByDay[$day][] = array(
            'type' => 'venta',
            'descripcion' => $venta['descripcion'] ?? 'Venta',
            'precio' => (float)($venta['precio'] ?? 0),
            'observacion' => '',
            'created_at' => $venta['created_at'] ?? ''
        );
    }

        echo '<section class="panel panel-space">';
        echo '<form method="get" class="toolbar">';
        echo '<input type="hidden" name="page" value="jostal">';
        echo '<input type="hidden" name="tab" value="informes">';
        echo '<div class="field"><label>Mes</label><input type="month" name="month" value="' . e($month) . '"></div>';
        echo '<div class="field field-btn"><label>&nbsp;</label><button class="btn-primary">Ver mes</button></div>';
        echo '</form>';
        echo '</section>';

        $monthTotal = 0;
        echo '<div class="jostal-days-grid">';
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $totalDia = 0;
            echo '<section class="jostal-day-col">';
            echo '<div class="jostal-day-head">Día ' . e($d) . '</div>';
            echo '<div class="jostal-day-body">';
            if (empty($itemsByDay[$d])) {
                echo '<div class="muted">Sin movimientos</div>';
            } else {
                foreach ($itemsByDay[$d] as $item) {
                    $totalDia += (float)$item['precio'];
                    echo '<div class="jostal-item">';
                    if ($item['type'] === 'lead') {
                        echo '<div><a class="mini-link" href="index.php?page=jostal&tab=clientas&edit=' . e($item['clienta_id']) . '">' . e($item['clienta_nombre']) . '</a></div>';
                    } else {
                        echo '<div><strong>Venta</strong></div>';
                    }
                    echo '<div class="money-chip">' . e(euro($item['precio'])) . '</div>';
                    echo '<div class="muted small">' . e($item['type'] === 'lead' ? $item['observacion'] : ($item['descripcion'] ?? '')) . '</div>';
                    echo '</div>';
                }
            }
            echo '</div>';
            echo '<div class="jostal-day-total">Total: ' . e(euro($totalDia)) . '</div>';
            $monthTotal += $totalDia;
            echo '</section>';
        }
        echo '</div>';
        echo '<div class="jostal-month-total">Total del mes: <span class="money-chip big">' . e(euro($monthTotal)) . '</span></div>';
    }

    echo '</div>';
    echo '</section>';
}

function render_informes_grid_view($filteredMovements, $gastosFiltered, $from, $to, $rama, $tipo, $clienteId) {
    $itemsByDay = array();

    $fromTs = strtotime($from . ' 00:00:00');
    $toTs = strtotime($to . ' 00:00:00');

    if (!$fromTs || !$toTs || $toTs < $fromTs) {
        echo '<section class="panel panel-space"><div class="empty">Rango de fechas inválido para mostrar el grid.</div></section>';
        return;
    }

    $cursor = $fromTs;
    while ($cursor <= $toTs) {
        $dayKey = date('Y-m-d', $cursor);
        $itemsByDay[$dayKey] = array();
        $cursor = strtotime('+1 day', $cursor);
    }

    foreach ($filteredMovements as $row) {
        $ts = (int)($row['ts'] ?? 0);
        if (!$ts) continue;

        $dayKey = business_day_key_from_ts($ts);
        if (!isset($itemsByDay[$dayKey])) continue;

        $itemsByDay[$dayKey][] = array(
            'branch' => $row['branch'] ?? '',
            'type' => $row['type'] ?? '',
            'amount' => (float)($row['amount'] ?? 0),
            'label' => $row['label'] ?? '',
            'description' => $row['description'] ?? '',
            'link' => $row['link'] ?? '',
            'ts' => $ts,
        );
    }

    foreach ($gastosFiltered as $gasto) {
        $ts = business_parse_ts($gasto['created_at'] ?? '');
        if (!$ts) continue;

        $dayKey = business_day_key_from_ts($ts);
        if (!isset($itemsByDay[$dayKey])) continue;

        $itemsByDay[$dayKey][] = array(
            'branch' => 'gastos',
            'type' => 'gasto',
            'amount' => -1 * (float)($gasto['cantidad'] ?? 0),
            'label' => 'Gasto',
            'description' => $gasto['descripcion'] ?? '',
            'link' => 'index.php?page=gastos',
            'ts' => $ts,
        );
    }

    $totalIngresos = 0;
    $totalGastos = 0;
    $branchIncomeTotals = array(
        'lamami' => 0,
        'casawasap' => 0,
        'jostal' => 0,
    );

    foreach ($itemsByDay as $dayKey => $items) {
        usort($items, function ($a, $b) {
            return $b['ts'] <=> $a['ts'];
        });
        $itemsByDay[$dayKey] = $items;

        foreach ($items as $item) {
            $isRealExpense = (($item['type'] ?? '') === 'gasto');

            if ($isRealExpense) {
                $totalGastos += abs((float)$item['amount']);
            } else {
                $totalIngresos += (float)$item['amount'];
                if (isset($branchIncomeTotals[$item['branch']])) {
                    $branchIncomeTotals[$item['branch']] += (float)$item['amount'];
                }
            }
        }
    }

    echo '<section class="panel panel-space">';
    echo '<div class="grid-legend">';
    echo '<span class="grid-legend-chip lamami">LaMami</span>';
    echo '<span class="grid-legend-chip casawasap">Casawasap</span>';
    echo '<span class="grid-legend-chip jostal">Jostal</span>';
    echo '<span class="grid-legend-chip gastos">Gastos</span>';
    echo '</div>';

    echo '<div class="cards three">';
    dashboard_card('Ingresos filtrados', euro($totalIngresos), true);
    dashboard_card('Gastos filtrados', euro($totalGastos), true);
    dashboard_card('Beneficio filtrado', euro($totalIngresos - $totalGastos), true);
    echo '</div>';

    echo '<div class="cards three">';
    dashboard_card('LaMami', euro($branchIncomeTotals['lamami']), true);
    dashboard_card('Casawasap', euro($branchIncomeTotals['casawasap']), true);
    dashboard_card('Jostal', euro($branchIncomeTotals['jostal']), true);
    echo '</div>';

    echo '<div class="muted">Vista grid aplicada con filtros: ' . e($from) . ' → ' . e($to) . ' · rama=' . e($rama) . ' · tipo=' . e($tipo) . ($clienteId !== '' ? (' · cliente=' . e($clienteId)) : '') . '</div>';
    echo '</section>';

    echo '<div class="jostal-days-grid">';
    foreach ($itemsByDay as $dayKey => $items) {
        $incomeDia = 0;
        $gastoDia = 0;

        echo '<section class="jostal-day-col">';
        echo '<div class="jostal-day-head">' . e(date('d/m', strtotime($dayKey))) . '</div>';
        echo '<div class="jostal-day-body">';

        if (empty($items)) {
            echo '<div class="muted">Sin movimientos</div>';
        } else {
            foreach ($items as $item) {
                $isRealExpense = (($item['type'] ?? '') === 'gasto');

                if ($isRealExpense) {
                    $gastoDia += abs((float)$item['amount']);
                } else {
                    $incomeDia += (float)$item['amount'];
                }

                echo '<div class="jostal-item monthly-grid-item monthly-grid-' . e($item['branch']) . '">';
                echo '<div class="monthly-grid-top">';
                echo '<span class="monthly-grid-branch">' .
                    e(
                        $item['branch'] === 'lamami' ? 'LaMami' :
                        ($item['branch'] === 'casawasap' ? 'Casawasap' :
                        ($item['branch'] === 'jostal' ? 'Jostal' : 'Gasto'))
                    )
                . '</span>';

                if ($isRealExpense) {
                    echo '<span class="expense-chip">' . e(euro(abs((float)$item['amount']))) . '</span>';
                } else {
                    $chipClass = ((float)$item['amount'] < 0) ? 'expense-chip' : 'money-chip';
                    echo '<span class="' . e($chipClass) . '">' . e(euro(abs((float)$item['amount']))) . '</span>';
                }

                echo '</div>';

                if (($item['label'] ?? '') !== '') {
                    if (($item['link'] ?? '') !== '') {
                        echo '<div><a class="mini-link" href="' . e($item['link']) . '">' . e($item['label']) . '</a></div>';
                    } else {
                        echo '<div><strong>' . e($item['label']) . '</strong></div>';
                    }
                }

                if (($item['description'] ?? '') !== '') {
                    echo '<div class="muted small">' . e($item['description']) . '</div>';
                }

                echo '<div class="muted small">' . e(date('H:i', $item['ts'])) . '</div>';
                echo '</div>';
            }
        }

        echo '</div>';
        echo '<div class="jostal-day-foot">';
        echo '<span class="money-chip">' . e(euro($incomeDia)) . '</span>';
        echo '<span class="expense-chip">' . e(euro($gastoDia)) . '</span>';
        echo '</div>';
        echo '</section>';
    }
    echo '</div>';
}

function render_gridmensual_page() {
    $month = request_get('month', business_current_month_key());
    $monthTs = strtotime($month . '-01');
    if (!$monthTs) {
        $month = business_current_month_key();
        $monthTs = strtotime($month . '-01');
    }

    $clientes = storage_read('clientes.json');
    $leads = storage_read('leads.json');
    $casawasapPagos = storage_read('casawasap_pagos.json');
    $jostalLeads = storage_read('jostal_leads.json');
    $jostalVentas = storage_read('jostal_ventas.json');
    $gastos = storage_read('gastos.json');

    $daysInMonth = (int)date('t', $monthTs);
    $itemsByDay = array();
    for ($d = 1; $d <= $daysInMonth; $d++) $itemsByDay[$d] = array();

    $addItem = function ($branch, $type, $value, $amount, $label, $description, $link = '') use (&$itemsByDay, $month) {
        $ts = business_parse_ts($value);
        if (!$ts) return;
        if (business_month_key_from_ts($ts) !== $month) return;

        $day = (int)date('j', strtotime(business_day_key_from_ts($ts)));
        if ($day <= 0 || !isset($itemsByDay[$day])) return;

        $itemsByDay[$day][] = array(
            'branch' => $branch,
            'type' => $type,
            'amount' => (float)$amount,
            'label' => $label,
            'description' => $description,
            'link' => $link,
            'ts' => $ts,
        );
    };

    foreach ($leads as $lead) {
        $addItem(
            'lamami',
            'lead',
            $lead['fecha_hora'] ?? '',
            (float)($lead['precio_lead'] ?? 0),
            ($lead['cliente_nombre'] ?? '') !== '' ? $lead['cliente_nombre'] : 'Sin clienta',
            $lead['observacion'] ?? '',
            !empty($lead['cliente_id']) ? 'index.php?page=clientas&edit=' . urlencode($lead['cliente_id']) : ''
        );
    }

    foreach ($clientes as $cliente) {
        $addItem(
            'lamami',
            'alta',
            $cliente['fecha_alta'] ?? '',
            (float)($cliente['precio_alta'] ?? 0),
            $cliente['nombre'] ?? 'Clienta',
            'Alta de clienta',
            !empty($cliente['id']) ? 'index.php?page=clientas&edit=' . urlencode($cliente['id']) : ''
        );
    }

    foreach ($casawasapPagos as $pago) {
        $addItem(
            'casawasap',
            'pago',
            $pago['fecha_hora'] ?? '',
            (float)($pago['importe'] ?? 0),
            ($pago['cliente_nombre'] ?? '') !== '' ? $pago['cliente_nombre'] : 'Cliente',
            $pago['observaciones'] ?? '',
            !empty($pago['cliente_id']) ? 'index.php?page=casawasap&edit=' . urlencode($pago['cliente_id']) : ''
        );
    }

    foreach ($jostalLeads as $lead) {
        $addItem(
            'jostal',
            'lead',
            $lead['created_at'] ?? '',
            (float)($lead['precio'] ?? 0),
            ($lead['clienta_nombre'] ?? '') !== '' ? $lead['clienta_nombre'] : 'Clienta',
            $lead['observacion'] ?? '',
            !empty($lead['clienta_id']) ? 'index.php?page=jostal&tab=clientas&edit=' . urlencode($lead['clienta_id']) : ''
        );
    }

    foreach ($jostalVentas as $venta) {
        $addItem(
            'jostal',
            'venta',
            $venta['created_at'] ?? '',
            (float)($venta['precio'] ?? 0),
            'Venta Jostal',
            $venta['descripcion'] ?? '',
            'index.php?page=jostal&tab=ventas'
        );
    }

    foreach ($gastos as $gasto) {
        $addItem(
            'gastos',
            'gasto',
            $gasto['created_at'] ?? '',
            -1 * (float)($gasto['cantidad'] ?? 0),
            'Gasto',
            $gasto['descripcion'] ?? '',
            'index.php?page=gastos'
        );
    }

    $totalIngresos = 0;
    $totalGastos = 0;
    $branchIncomeTotals = array(
        'lamami' => 0,
        'casawasap' => 0,
        'jostal' => 0
    );

    foreach ($itemsByDay as $day => $items) {
        usort($items, function ($a, $b) {
            return $b['ts'] <=> $a['ts'];
        });
        $itemsByDay[$day] = $items;

        foreach ($items as $item) {
            $isRealExpense = (($item['type'] ?? '') === 'gasto');

            if ($isRealExpense) {
                $totalGastos += abs((float)$item['amount']);
            } else {
                $totalIngresos += (float)$item['amount'];
                if (isset($branchIncomeTotals[$item['branch']])) {
                    $branchIncomeTotals[$item['branch']] += (float)$item['amount'];
                }
            }
        }
    }

    page_header('Grid mensual', 'Vista diaria consolidada de LaMami, Casawasap, Jostal y gastos');

    echo '<section class="panel panel-space">';
    echo '<form method="get" class="toolbar">';
    echo '<input type="hidden" name="page" value="gridmensual">';
    echo '<div class="field"><label>Mes</label><input type="month" name="month" value="' . e($month) . '"></div>';
    echo '<div class="field field-btn"><label>&nbsp;</label><button class="btn-primary">Ver mes</button></div>';
    echo '</form>';
    echo '</section>';

    echo '<section class="panel panel-space">';
    echo '<div class="grid-legend">';
    echo '<span class="grid-legend-chip lamami">LaMami</span>';
    echo '<span class="grid-legend-chip casawasap">Casawasap</span>';
    echo '<span class="grid-legend-chip jostal">Jostal</span>';
    echo '<span class="grid-legend-chip gastos">Gastos</span>';
    echo '</div>';

    echo '<div class="cards three">';
    dashboard_card('Ingresos del mes', euro($totalIngresos), true);
    dashboard_card('Gastos del mes', euro($totalGastos), true);
    dashboard_card('Beneficio del mes', euro($totalIngresos - $totalGastos), true);
    echo '</div>';

    echo '<div class="cards three">';
    dashboard_card('LaMami', euro($branchIncomeTotals['lamami']), true);
    dashboard_card('Casawasap', euro($branchIncomeTotals['casawasap']), true);
    dashboard_card('Jostal', euro($branchIncomeTotals['jostal']), true);
    echo '</div>';
    echo '</section>';

    echo '<div class="jostal-days-grid">';
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $incomeDia = 0;
        $gastoDia = 0;

        echo '<section class="jostal-day-col">';
        echo '<div class="jostal-day-head">Día ' . e($d) . '</div>';
        echo '<div class="jostal-day-body">';

        if (empty($itemsByDay[$d])) {
            echo '<div class="muted">Sin movimientos</div>';
        } else {
            foreach ($itemsByDay[$d] as $item) {
                $isRealExpense = (($item['type'] ?? '') === 'gasto');

                if ($isRealExpense) {
                    $gastoDia += abs((float)$item['amount']);
                } else {
                    $incomeDia += (float)$item['amount'];
                }

                echo '<div class="jostal-item monthly-grid-item monthly-grid-' . e($item['branch']) . '">';
                echo '<div class="monthly-grid-top">';
                echo '<span class="monthly-grid-branch">' .
                    e(
                        $item['branch'] === 'lamami' ? 'LaMami' :
                        ($item['branch'] === 'casawasap' ? 'Casawasap' :
                        ($item['branch'] === 'jostal' ? 'Jostal' : 'Gasto'))
                    )
                . '</span>';

                if ($isRealExpense) {
                    echo '<span class="expense-chip">' . e(euro(abs((float)$item['amount']))) . '</span>';
                } else {
                    $chipClass = ((float)$item['amount'] < 0) ? 'expense-chip' : 'money-chip';
                    echo '<span class="' . e($chipClass) . '">' . e(euro(abs((float)$item['amount']))) . '</span>';
                }

                echo '</div>';

                echo '<div>';
                if ($item['link'] !== '') {
                    echo '<a class="mini-link" href="' . e($item['link']) . '">' . e($item['label']) . '</a>';
                } else {
                    echo '<strong>' . e($item['label']) . '</strong>';
                }
                echo '</div>';

                echo '<div class="muted small">' . e($item['description']) . '</div>';
                echo '</div>';
            }
        }

        echo '</div>';
        echo '<div class="jostal-day-total">';
        echo 'Ingresos: ' . e(euro($incomeDia)) . '<br>';
        echo 'Gastos: ' . e(euro($gastoDia)) . '<br>';
        echo 'Beneficio: ' . e(euro($incomeDia - $gastoDia));
        echo '</div>';
        echo '</section>';
    }
    echo '</div>';

    echo '<div class="jostal-month-total">';
    echo 'Total del mes · Ingresos: <span class="money-chip">' . e(euro($totalIngresos)) . '</span> · ';
    echo 'Gastos: <span class="expense-chip">' . e(euro($totalGastos)) . '</span> · ';
    echo 'Beneficio: <span class="money-chip big">' . e(euro($totalIngresos - $totalGastos)) . '</span>';
    echo '</div>';
}

function render_gastos_page() {
    $gastos = storage_read('gastos.json');
    $gastos = sort_desc_by_key($gastos, 'created_at');

    page_header('Gastos', 'Registro simple de gastos del proyecto');

    echo '<div class="cards two">';

    echo '<section class="panel">';
    echo '<h2>Nuevo gasto</h2>';
    echo '<form method="post" class="form-grid" onsubmit="return confirmGastoSubmit(this);">';
    echo '<input type="hidden" name="action" value="add_gasto">';
    field_input('cantidad', 'Cantidad', '', true);
    field_input('created_at', 'Fecha y hora', today_datetime_local(), true, 'datetime-local');
    field_textarea('descripcion', 'Descripción', '', 4);
    echo '<div class="full"><button class="btn-expense">☹ Registrar gasto</button></div>';
    echo '</form>';
    echo '</section>';

    echo '<section class="panel">';
    echo '<h2>Listado de gastos</h2>';
    if (empty($gastos)) {
        echo '<div class="empty">Todavía no hay gastos registrados.</div>';
    } else {
        render_live_filter('#gastosRows tr[data-filter-text]', 'Buscar gasto...');
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Cantidad</th><th>Created at</th><th>Descripción</th><th>Acciones</th>';
        echo '</tr></thead><tbody id="gastosRows">';
        foreach ($gastos as $gasto) {
            $searchText = strtolower(trim(
                ($gasto['cantidad'] ?? '') . ' ' .
                ($gasto['created_at'] ?? '') . ' ' .
                ($gasto['descripcion'] ?? '')
            ));
            echo '<tr data-filter-text="' . e($searchText) . '">';
            echo '<td><span class="expense-chip">' . e(euro($gasto['cantidad'] ?? 0)) . '</span></td>';
            echo '<td>' . e(format_created_at($gasto['created_at'] ?? '')) . '</td>';
            echo '<td>' . e($gasto['descripcion'] ?? '') . '</td>';
            echo '<td>';
            echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar definitivamente este gasto?\')">';
            echo '<input type="hidden" name="action" value="delete_gasto">';
            echo '<input type="hidden" name="id" value="' . e($gasto['id'] ?? '') . '">';
            echo '<button class="btn-danger-mini">Eliminar</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';

    echo '</div>';
}

function render_live_filter($targetSelector, $placeholder = 'Buscar...') {
    echo '<div class="field list-search-field">';
    echo '<label>Buscar</label>';
    echo '<input type="text" class="js-live-filter" data-target-selector="' . e($targetSelector) . '" placeholder="' . e($placeholder) . '">';
    echo '</div>';
}

function field_input($name, $label, $value = '', $required = false, $type = 'text') {
    $name = (string)$name;
    $label = (string)$label;
    $value = (string)$value;
    $isPhone = ($type === 'tel') || crm_is_phone_field_name($name) || crm_is_phone_field_name($label);
    $copyPhone = $isPhone ? crm_phone_copy_value($value) : '';
    echo '<div class="field">';
    echo '<label>' . e($label) . '</label>';
    if ($isPhone && $copyPhone !== '') {
        echo '<div class="copy-row">';
        echo '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '" style="flex:1 1 auto;min-width:0;"' . ($required ? ' required' : '') . '>';
        echo '<button type="button" class="btn-copy-mini" data-copy="' . e($copyPhone) . '">Copiar</button>';
        echo '</div>';
    } else {
        echo '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . ($required ? ' required' : '') . '>';
    }
    echo '</div>';
}

function field_textarea($name, $label, $value = '', $rows = 4) {
    echo '<div class="field full">';
    echo '<label>' . e($label) . '</label>';
    echo '<textarea name="' . e($name) . '" rows="' . e($rows) . '">' . e($value) . '</textarea>';
    echo '</div>';
}

function field_select_clienta($name, $label, $clientes, $selected = '') {
    echo '<div class="field">';
    echo '<label>' . e($label) . '</label>';
    echo '<select name="' . e($name) . '">';
    echo '<option value="">-- Selecciona clienta --</option>';
    foreach ($clientes as $cliente) {
        $sel = ($selected === $cliente['id']) ? ' selected' : '';
        echo '<option value="' . e($cliente['id']) . '"' . $sel . '>' . e($cliente['nombre']) . '</option>';
    }
    echo '</select>';
    echo '</div>';
}

function field_select_bot_linked_entity($name, $label, $lamamiClientas, $casawasapClientes, $selected = '') {
    echo '<div class="field full">';
    echo '<label>' . e($label) . '</label>';
    echo '<select name="' . e($name) . '">';
    echo '<option value="">-- Selecciona ficha vinculada --</option>';

    if (!empty($lamamiClientas)) {
        echo '<optgroup label="LaMami · clientas activas">';
        foreach ($lamamiClientas as $clienta) {
            $value = bot_build_linked_ref('lamami_clienta', $clienta['id'] ?? '');
            if ($value === '') continue;
            $sel = ($selected === $value) ? ' selected' : '';
            echo '<option value="' . e($value) . '"' . $sel . '>' . e($clienta['nombre'] ?? 'Clienta') . '</option>';
        }
        echo '</optgroup>';
    }

    if (!empty($casawasapClientes)) {
        echo '<optgroup label="CasaWasap · clientes">';
        foreach ($casawasapClientes as $cliente) {
            $profile = casawasap_bot_profile_from_contact($cliente);
            $labelText = trim((string)($profile['business_name'] ?? ''));
            if ($labelText === '') {
                $labelText = trim((string)($cliente['telefono'] ?? 'Cliente CasaWasap'));
            }
            $value = bot_build_linked_ref('casawasap_cliente', $cliente['id'] ?? '');
            if ($value === '') continue;
            $sel = ($selected === $value) ? ' selected' : '';
            echo '<option value="' . e($value) . '"' . $sel . '>' . e($labelText) . '</option>';
        }
        echo '</optgroup>';
    }

    echo '</select>';
    echo '<div class="field-help">El bot guardará el vínculo por tipo de ficha: clienta LaMami o cliente CasaWasap.</div>';
    echo '</div>';
}

function render_casawasap_bot_profile_fields($row = array()) {
    $row = is_array($row) ? $row : array();

    echo '<div class="full info-strip"><strong>Datos para bot</strong><br>Estos campos alimentan la generación del bot cuando este cliente se usa desde la sección Bots.</div>';
    field_input('bot_business_name', 'Nombre comercial / nombre visible del bot', $row['bot_business_name'] ?? ($row['nombre'] ?? ''), false);
    field_textarea('bot_contexto', 'Contexto del negocio / briefing corto', $row['bot_contexto'] ?? ($row['notas'] ?? ''), 3);
    field_textarea('bot_servicios', 'Servicios para el bot', $row['bot_servicios'] ?? '', 4);
    field_textarea('bot_tarifas', 'Tarifas para el bot', $row['bot_tarifas'] ?? '', 4);
    field_input('bot_zona', 'Zona / ubicación textual', $row['bot_zona'] ?? '', false);
    field_input('bot_ubicacion_maps', 'Ubicación Maps', $row['bot_ubicacion_maps'] ?? '', false);
    field_input('bot_horario', 'Horario', $row['bot_horario'] ?? '', false);
    field_input('bot_objetivo', 'Objetivo / CTA principal', $row['bot_objetivo'] ?? '', false);

    echo '<div class="field">';
    echo '<label>Modo sugerido para el bot</label>';
    echo '<select name="bot_modo_preferido">';
    $currentMode = (string)($row['bot_modo_preferido'] ?? ($row['modo'] ?? 'multiple'));
    if ($currentMode !== 'personal') {
        $currentMode = 'multiple';
    }
    foreach (array('multiple' => 'Multiple', 'personal' => 'Personal') as $k => $labelText) {
        $sel = ($currentMode === $k) ? ' selected' : '';
        echo '<option value="' . e($k) . '"' . $sel . '>' . e($labelText) . '</option>';
    }
    echo '</select>';
    echo '</div>';
}

function publicista_field_clienta_picker($name, $label, $clientas, $selected = '') {
    $selected = trim((string)$selected);
    $selectId = 'publicista_clienta_picker_' . substr(md5($name . '|' . $selected . '|' . count($clientas)), 0, 10);

    echo '<div class="field full publicista-clienta-picker">';
    echo '<label>' . e($label) . '</label>';
    echo '<input type="text" class="js-publicista-clienta-filter" data-target-select="#' . e($selectId) . '" placeholder="Buscar por nombre, teléfono, ciudad, sección o estado...">';
    echo '<select id="' . e($selectId) . '" name="' . e($name) . '" size="8">';
    echo '<option value="">-- Selecciona clienta --</option>';
    foreach ($clientas as $clienta) {
        $value = trim((string)($clienta['picker_value'] ?? ''));
        if ($value === '') continue;
        $sel = ($selected === $value) ? ' selected' : '';
        echo '<option value="' . e($value) . '" data-search="' . e($clienta['search_text'] ?? '') . '"' . $sel . '>' . e($clienta['display_label'] ?? ($clienta['nombre'] ?? $value)) . '</option>';
    }
    echo '</select>';
    echo '<div class="field-help">Se muestran clientas de LaMami y de Jostal, estén o no en la casa. Escribe para filtrar el listado y luego selecciona una.</div>';
    echo '</div>';
}
