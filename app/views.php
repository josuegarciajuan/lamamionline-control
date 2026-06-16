<?php

function render_global_ui() {
    echo '<div id="floatingToast" class="floating-toast"></div>';
    echo '<div id="moneyRain" class="money-rain"></div>';
    echo '<div id="appBackdrop" class="app-backdrop" hidden></div>';
    echo '<div id="voiceCommandBackdrop" class="voice-command-backdrop" hidden></div>';
    echo '<div class="app-shell-tools">';
    echo '<button type="button" id="mobileMenuToggle" class="app-shell-btn app-shell-btn-mobile" aria-expanded="false" aria-controls="appSidebar">☰ Menú</button>';
    echo '<button type="button" id="mobileAvisosToggle" class="app-shell-btn app-shell-btn-mobile" aria-expanded="false" aria-controls="avisosPanel">⚠ Avisos</button>';
    echo '<button type="button" id="voiceCommandToggle" class="app-shell-btn app-shell-btn-voice" aria-expanded="false" aria-controls="voiceCommandPanel">🎙 Voz CRM</button>';
    echo '<button type="button" id="appFullscreenToggle" class="app-shell-btn">⛶ Pantalla completa</button>';
    echo '</div>';

    echo '<section id="voiceCommandPanel" class="voice-command-panel" hidden aria-hidden="true">';
    echo '<div class="voice-command-head">';
    echo '<div>';
    echo '<h2>Órdenes por voz</h2>';
    echo '<p>Habla o escribe una orden para el CRM.</p>';
    echo '</div>';
    echo '<button type="button" id="voiceCommandClose" class="voice-command-close" aria-label="Cerrar panel de voz">✕</button>';
    echo '</div>';

    echo '<div class="voice-command-body">';
    echo '<div id="voiceCommandSupport" class="voice-command-support">Comprobando reconocimiento de voz…</div>';
    echo '<div class="voice-command-actions">';
    echo '<button type="button" id="voiceStartButton" class="btn-primary voice-command-main-btn">🎙 Empezar a hablar</button>';
    echo '<button type="button" id="voiceStopButton" class="voice-command-secondary-btn" disabled>■ Detener</button>';
    echo '<button type="button" id="voiceClearButton" class="voice-command-secondary-btn">Limpiar</button>';
    echo '</div>';

    echo '<div class="field full">';
    echo '<label for="voiceCommandInput">Texto de la orden</label>';
    echo '<textarea id="voiceCommandInput" class="voice-command-input" placeholder="Ejemplo: muéstrame estadísticas de esta clienta"></textarea>';
    echo '<div class="field-help">Puedes corregir el texto antes de enviarlo.</div>';
    echo '</div>';

    echo '<div class="voice-command-meta">';
    echo '<span id="voiceCommandStatus" class="voice-command-status stage-idle">Listo para escuchar.</span>';
    echo '<span id="voiceCommandStage" class="voice-command-stage">Sin enviar</span>';
    echo '</div>';

    echo '<div class="voice-command-submit-row">';
    echo '<button type="button" id="voiceSendButton" class="btn-primary">Enviar orden</button>';
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

function render_avisos_panel() {
    $avisos = avisos_get_active();
    if (empty($avisos)) return;

    $newIds = array();

    echo '<section id="avisosPanel" class="panel panel-space avisos-panel">';
    echo '<div class="branch-panel-head">';
    echo '<h2><a class="mini-link" href="' . e(avisos_page_url(array('avtab' => 'active'))) . '">Avisos activos</a></h2>';
    echo '<span class="summary-badge">' . e(count($avisos)) . '</span>';
    echo '</div>';

    foreach ($avisos as $aviso) {
        $isNew = empty($aviso['read_at']);
        if ($isNew) $newIds[] = $aviso['id'];

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
        echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Descartar este aviso?\')">';
        echo '<input type="hidden" name="action" value="dismiss_aviso">';
        echo '<input type="hidden" name="id" value="' . e($aviso['id'] ?? '') . '">';
        echo '<input type="hidden" name="redirect" value="' . e($_SERVER['REQUEST_URI'] ?? 'index.php?page=dashboard') . '">';
        echo '<button class="btn-danger-mini">Descartar</button>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }

    echo '</section>';

    if (!empty($newIds)) {
        avisos_mark_as_read($newIds);
    }
}

function render_avisos_section($baseUrl = 'index.php?page=avisos') {
    $tab = request_get('avtab', 'planned');
    if (!in_array($tab, array('planned', 'active', 'history'), true)) {
        $tab = 'planned';
    }

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

    if ($tab === 'planned') {
        echo '<div class="cards two">';

        echo '<section class="panel">';
        echo '<h2>Nuevo aviso manual</h2>';
        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="action" value="create_manual_aviso">';

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
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Descartar este aviso?\')">';
                echo '<input type="hidden" name="action" value="dismiss_aviso">';
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
    $allowed = array('crear_perfiles', 'calculo_publicidad', 'subir_anuncios');

    if (!in_array($tab, $allowed, true)) {
        $tab = 'crear_perfiles';
    }

    page_header('Publicista', 'Crea perfiles, calcula la estrategia publicitaria y prepara la futura automatización de subida');

    echo '<section class="panel panel-space">';
    echo '<div class="subtabs">';
    echo '<a class="subtab ' . ($tab === 'crear_perfiles' ? 'active' : '') . '" href="' . e(publicista_page_url('crear_perfiles')) . '">Crear perfiles</a>';
    echo '<a class="subtab ' . ($tab === 'calculo_publicidad' ? 'active' : '') . '" href="' . e(publicista_page_url('calculo_publicidad')) . '">Cálculo publicidad</a>';
    echo '<a class="subtab ' . ($tab === 'subir_anuncios' ? 'active' : '') . '" href="' . e(publicista_page_url('subir_anuncios')) . '">Subir anuncios</a>';
    echo '</div>';
    echo '</section>';

    if ($tab === 'calculo_publicidad') {
        render_publicista_calculo_publicidad_page();
        return;
    }

    if ($tab === 'subir_anuncios') {
        render_publicista_subir_anuncios_page();
        return;
    }

    render_publicista_crear_perfiles_page(true);
}

function render_publicista_calculo_publicidad_page() {
    $defaults = array(
        'city' => 'Burriana',
        'province' => 'Castellón',
        'category' => '',
        'num_girls' => '1',
    );

    $form = array_merge($defaults, array_intersect_key($_GET, $defaults));
    $shouldAnalyze = trim((string) request_get('analyze')) === '1';
    $result = null;

    if ($shouldAnalyze) {
        $city = trim((string) ($form['city'] ?? ''));
        $province = trim((string) ($form['province'] ?? ''));
        $category = trim((string) ($form['category'] ?? ''));
        $numGirls = max(1, min(8, (int) ($form['num_girls'] ?? 1)));

        $comp = publicista_ads_scrape($city, $category);
        $strategies = publicista_ads_build_strategy($comp, $numGirls);
        $grandTotal = 0.0;
        foreach ($strategies as $strategyRow) {
            $grandTotal += (float) ($strategyRow['cost'] ?? 0);
        }

        $categories = publicista_ads_categories();
        $result = array(
            'city' => $city,
            'province' => $province,
            'catLabel' => isset($categories[$category]) ? $categories[$category] : 'Todas las categorías',
            'numGirls' => $numGirls,
            'comp' => $comp,
            'strategies' => $strategies,
            'grandTotal' => $grandTotal,
        );
    }

    $prices = publicista_ads_prices();

    echo '<section class="panel panel-space publicista-ads-intro">';
    echo '<div class="branch-panel-head"><h2>Calculadora de estrategia publicitaria</h2><span class="summary-badge">Destacamos</span></div>';
    echo '<div class="cards three" style="margin-top:12px;">';
    echo '<div class="info-strip"><strong>Paso 1</strong><br>Indica ciudad, categoría y cuántas chicas quieres empujar.</div>';
    echo '<div class="info-strip"><strong>Paso 2</strong><br>Se hace scraping del listado y se mide competencia real: PREMIUM, TOP, autorenuevas y total.</div>';
    echo '<div class="info-strip"><strong>Paso 3</strong><br>El CRM te devuelve perfiles recomendados, coste, horarios y una línea de tiempo.</div>';
    echo '</div>';
    echo '</section>';

    echo '<div class="cards two">';

    echo '<section class="panel">';
    echo '<div class="section-head"><div><h2>Parámetros de análisis</h2><p>Esta pantalla sustituye a estrategiaTops.php, pero integrada ya en el diseño del CRM.</p></div></div>';
    echo '<form method="get" class="form-grid">';
    echo '<input type="hidden" name="page" value="publicista">';
    echo '<input type="hidden" name="tab" value="calculo_publicidad">';
    echo '<input type="hidden" name="analyze" value="1">';

    echo '<div class="field"><label>Ciudad</label><input type="text" name="city" value="' . e($form['city']) . '" required></div>';
    echo '<div class="field"><label>Provincia</label><input type="text" name="province" value="' . e($form['province']) . '"></div>';
    echo '<div class="field"><label>Categoría</label><select name="category">';
    foreach (publicista_ads_categories() as $value => $label) {
        echo '<option value="' . e($value) . '"' . (((string) $form['category'] === (string) $value) ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    echo '</select></div>';
    echo '<div class="field"><label>Número de chicas</label><input type="number" name="num_girls" min="1" max="8" value="' . e((string) ((int) $form['num_girls'])) . '"></div>';
    echo '<div class="full" style="display:flex;gap:10px;flex-wrap:wrap;">';
    echo '<button class="btn-primary" type="submit">Analizar y generar estrategia</button>';
    echo '<a class="btn-secondary-mini" href="' . e(publicista_page_url('calculo_publicidad')) . '">Limpiar</a>';
    echo '</div>';
    echo '</form>';
    echo '</section>';

    echo '<section class="panel">';
    echo '<div class="branch-panel-head"><h2>Precios configurados</h2><span class="summary-badge">Base</span></div>';
    echo '<div class="table-wrap" style="margin-top:12px;"><table><tbody>';
    echo '<tr><td>TOP (10 días)</td><td style="text-align:right;"><strong>' . e(publicista_ads_euros((float) $prices['top'])) . '</strong></td></tr>';
    echo '<tr><td>Autorenueva 10 sub/día</td><td style="text-align:right;"><strong>' . e(publicista_ads_euros((float) $prices['auto7'])) . '</strong></td></tr>';
    echo '<tr><td>Autorenueva 4 sub/día</td><td style="text-align:right;"><strong>' . e(publicista_ads_euros((float) $prices['auto4'])) . '</strong></td></tr>';
    echo '<tr><td>PREMIUM 30 días (estimado)</td><td style="text-align:right;"><strong>' . e(publicista_ads_euros((float) $prices['premium'])) . '</strong></td></tr>';
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
    echo '<div class="cards four" style="margin-top:14px;">';
    echo '<div class="info-strip"><strong>PREMIUM</strong><br>' . e((string) ($comp['premium'] ?? 0)) . '</div>';
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
    echo '</section>';

    echo '<section class="panel panel-space publicista-ads-cost-summary">';
    echo '<div class="branch-panel-head"><h2>Resumen de inversión</h2><span class="summary-badge">' . e((string) $result['numGirls']) . ' chica' . ($result['numGirls'] > 1 ? 's' : '') . '</span></div>';
    echo '<div class="cards three" style="margin-top:14px;">';
    echo '<div class="info-strip"><strong>Total acumulado</strong><br>' . e(publicista_ads_euros((float) $result['grandTotal'])) . '</div>';
    echo '<div class="info-strip"><strong>Coste medio por chica</strong><br>' . e(publicista_ads_euros($result['numGirls'] > 0 ? ((float) $result['grandTotal'] / (int) $result['numGirls']) : 0)) . '</div>';
    echo '<div class="info-strip"><strong>Nivel</strong><br>' . e(publicista_ads_level_label($level)) . '</div>';
    echo '</div>';
    echo '</section>';

    foreach ($result['strategies'] as $strategy) {
        $girl = (int) ($strategy['girl'] ?? 0);

        echo '<section class="panel panel-space publicista-ads-girl">';
        echo '<div class="section-head"><div><h2>Chica ' . e((string) $girl) . '</h2><p>' . e((string) count($strategy['profiles'] ?? array())) . ' perfiles · ' . e(publicista_ads_euros((float) ($strategy['cost'] ?? 0))) . ' por período</p></div><div class="section-head-actions">';
        echo '<span class="publicista-ads-level-pill" style="background:' . e(publicista_ads_level_bg($strategy['level'] ?? 'media')) . ';color:' . e(publicista_ads_level_fg($strategy['level'] ?? 'media')) . ';">' . e(publicista_ads_level_icon($strategy['level'] ?? 'media')) . ' ' . e(publicista_ads_level_label($strategy['level'] ?? 'media')) . '</span>';
        echo '</div></div>';

        echo '<div class="cards two" style="margin-top:14px;">';
        echo '<section class="panel">';
        echo '<h3>Por qué esta estrategia</h3>';
        echo '<ul class="publicista-ads-reasons">';
        foreach (($strategy['reasons'] ?? array()) as $reason) {
            echo '<li>' . e($reason) . '</li>';
        }
        echo '</ul>';
        echo '</section>';

        echo '<section class="panel">';
        echo '<h3>Perfiles a crear / comprar</h3>';
        echo '<div class="table-wrap"><table class="publicista-ads-profile-table"><thead><tr><th>#</th><th>Perfil</th><th>Productos</th><th>Nota</th><th>Coste</th></tr></thead><tbody>';
        foreach (($strategy['profiles'] ?? array()) as $profile) {
            echo '<tr>';
            echo '<td><span class="summary-badge">' . e((string) ($profile['num'] ?? '')) . '</span></td>';
            echo '<td><strong>' . e($profile['name'] ?? '') . '</strong></td>';
            echo '<td>' . publicista_ads_badge_line_html($profile['opts'] ?? array()) . '</td>';
            echo '<td>' . e($profile['why'] ?? '') . '</td>';
            echo '<td><strong>' . e(((float) ($profile['cost'] ?? 0) === 0.0) ? 'Gratis' : publicista_ads_euros((float) ($profile['cost'] ?? 0))) . '</strong></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
        echo '</section>';
        echo '</div>';

        if (!empty($strategy['allFirings'])) {
            echo '<section class="panel panel-space">';
            echo '<h3>Horarios exactos de subida</h3>';
            foreach ($strategy['allFirings'] as $firing) {
                $typeLabel = (($firing['type'] ?? '') === 'auto7') ? 'Autorenueva 7€ · 10 sub/día' : 'Refuerzo 4€ · 4 sub/día';
                echo '<div class="publicista-ads-firing-row">';
                echo '<div class="publicista-ads-firing-label"><strong>P' . e((string) ($firing['profile'] ?? '')) . '</strong><br>' . e($typeLabel) . '<br><span class="muted">' . e(($firing['start'] ?? '') . ' → ' . ($firing['end'] ?? '')) . '</span></div>';
                echo '<div class="publicista-ads-firing-times">';
                foreach (($firing['times'] ?? array()) as $time) {
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
            echo '<div class="publicista-ads-warning"><strong>Subidas muy próximas detectadas</strong><ul class="publicista-ads-warn-list">';
            foreach ($strategy['overlapWarnings'] as $warning) {
                echo '<li>' . e($warning) . '</li>';
            }
            echo '</ul></div>';
            echo '</section>';
        }

        foreach (($strategy['profiles'] ?? array()) as $profile) {
            if (empty($profile['opts']['free']) || !is_array($profile['opts']['free'])) {
                continue;
            }
            echo '<section class="panel panel-space publicista-ads-free-box">';
            echo '<h3>Perfil gratuito P' . e((string) ($profile['num'] ?? '')) . '</h3>';
            echo '<div style="display:flex;gap:8px;flex-wrap:wrap;">';
            foreach ($profile['opts']['free'] as $freeTime) {
                echo '<span class="publicista-ads-time-pill">⏰ ' . e($freeTime) . '</span>';
            }
            echo '</div>';
            echo '<div class="muted" style="margin-top:10px;">Subida manual, una vez cada 12h, sin coste.</div>';
            echo '</section>';
        }
    }
}

function render_publicista_subir_anuncios_page() {
    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>Subir anuncios</h2><span class="summary-badge">Próximamente</span></div>';
    echo '<div class="cards three" style="margin-top:14px;">';
    echo '<div class="info-strip"><strong>Objetivo</strong><br>Automatizar la subida del anuncio a varias webs desde el CRM.</div>';
    echo '<div class="info-strip"><strong>Estado actual</strong><br>Subsección reservada hasta que queden bien testeadas Crear perfiles y Cálculo publicidad.</div>';
    echo '<div class="info-strip"><strong>Siguiente fase</strong><br>Conectar credenciales, plantillas, imágenes finales y trazabilidad por portal.</div>';
    echo '</div>';
    echo '<div class="publicista-ads-warning" style="margin-top:16px;">Aquí todavía no hacemos ninguna subida automática. Solo queda preparada la pestaña para la siguiente fase.</div>';
    echo '</section>';
}

function render_publicista_crear_perfiles_page($embedded = false) {
    $jobs = publicista_jobs_get();
    $clientas = storage_read('clientes.json');
    $clientas = sort_desc_by_key($clientas, 'created_at');
    $aiCfg = publicista_ai_config();

    $clientaFilter = trim((string)request_get('clienta_id'));
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

    $activeCount = 0;
    $doneCount = 0;
    $definitiveCount = 0;
    foreach ($jobs as $job) {
        if (($job['estado'] ?? '') !== 'archived') $activeCount++;
        if (($job['estado'] ?? '') === 'done') $doneCount++;
        $wfTmp = function_exists('publicista_job_workflow') ? publicista_job_workflow($job) : array();
        if (!empty($wfTmp['pack_final'])) $definitiveCount++;
    }

    echo '<div class="cards two">';

    echo '<section class="panel">';
    echo '<div class="section-head"><div><h2>Nuevo trabajo Publicista</h2><p>Flujo rápido: sube foto, genera pack, revisa y acepta.</p></div></div>';
    if (empty($clientas)) {
        echo '<div class="empty">No hay clientas todavía. Primero necesitas al menos una clienta en LaMami.</div>';
    } else {
        echo '<form method="post" enctype="multipart/form-data" class="form-grid">';
        echo '<input type="hidden" name="action" value="create_publicista_job">';
        field_select_clienta('clienta_id', 'Clienta', $clientas, $clientaFilter);
        field_input('nombre_trabajo', 'Nombre interno del pack', '');
        echo '<div class="field full">';
        echo '<label>Foto original</label>';
        echo '<input type="file" name="source_image" accept="image/jpeg,image/png,image/webp">';
        echo '<div class="field-help">Si pulsas <strong>Crear y generar</strong>, esta foto se usa directamente para lanzar todo el pipeline.</div>';
        echo '</div>';
        field_textarea('physical_notes', 'Observaciones físicas / matices', '', 4);
        field_textarea('restrictions_text', 'Restricciones libres', '', 3);
        echo '<div class="field full">';
        echo '<label>Restricciones rápidas</label>';
        publicista_render_restriction_checkboxes(array());
        echo '</div>';
        field_textarea('services_snapshot', 'Servicios base (snapshot inicial)', '', 3);
        echo '<div class="field">';
        echo '<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="auto_regenerate" value="1"> Auto-regeneración automática (desactivada por defecto)</label>';
        echo '<div class="field-help">Déjala desactivada para el modo ahorro máximo. Con el nuevo flujo se generan 6 candidatas fijas y luego eliges si regeneras una concreta.</div>';
        echo '</div>';
        echo '<div class="field">';
        echo '<label>Tono de textos</label>';
        echo '<select name="copy_tone">';
        foreach (publicista_copy_tone_options() as $value => $label) {
            echo '<option value="' . e($value) . '">' . e($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        field_textarea('copy_examples_base', 'Ejemplos base de redacción (opcional)', '', 4);
        field_textarea('notas', 'Notas internas', '', 3);
        echo '<div class="full" style="display:flex;gap:10px;flex-wrap:wrap;">';
        echo '<button class="btn-secondary-mini" name="create_mode" value="draft">Crear borrador</button>';
        echo '<button class="btn-primary" name="create_mode" value="generate">Crear y generar</button>';
        echo '</div>';
        echo '</form>';
    }
    echo '</section>';

    echo '<section class="panel">';
    echo '<div class="branch-panel-head"><h2>Resumen y cola</h2><span class="summary-badge">' . e(count($jobs)) . '</span></div>';
    echo '<div class="cards four" style="margin-top:12px;">';
    echo '<div class="info-strip"><strong>Activos</strong><br>' . e((string)$activeCount) . '</div>';
    echo '<div class="info-strip"><strong>Finalizados</strong><br>' . e((string)$doneCount) . '</div>';
    echo '<div class="info-strip"><strong>Definitivos</strong><br>' . e((string)$definitiveCount) . '</div>';
    echo '</div>';
    echo '<div class="info-strip"><strong>OpenAI API key:</strong> ' . ($aiCfg['configured'] ? 'Detectada (' . e($aiCfg['api_key_source']) . ')' : 'No detectada') . '</div>';
    echo '<div class="info-strip" style="margin-top:10px;"><strong>Modelos:</strong> ' . e($aiCfg['descriptor_model']) . ' · ' . e($aiCfg['image_model']) . '</div>';
    echo '<div class="info-strip" style="margin-top:10px;"><strong>UX objetivo:</strong> subir foto, generar, revisar en la misma pantalla y aceptar o regenerar solo lo necesario.</div>';
    echo '</section>';
    echo '</div>';

    publicista_render_intro_guide_panel();

    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>Trabajos creados</h2><span class="summary-badge">' . e(count($jobs)) . '</span></div>';
    if (empty($jobs)) {
        echo '<div class="empty">Todavía no hay trabajos creados en Publicista.</div>';
    } else {
        render_live_filter('#publicistaJobsRows tr[data-filter-text]', 'Buscar trabajo o clienta...');
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Trabajo</th><th>Estado</th><th>Clienta</th><th>Finales</th><th>Actualizado</th><th>Acciones</th>';
        echo '</tr></thead><tbody id="publicistaJobsRows">';
        foreach ($jobs as $row) {
            $wf = function_exists('publicista_job_workflow') ? publicista_job_workflow($row) : array();
            $searchText = strtolower(trim(
                ($row['nombre_trabajo'] ?? '') . ' ' .
                ($row['clienta_nombre_snapshot'] ?? '') . ' ' .
                ($row['localidad_snapshot'] ?? '') . ' ' .
                ($row['provincia_snapshot'] ?? '') . ' ' .
                ($row['estado'] ?? '')
            ));
            $finalCount = is_array($row['final_images'] ?? null) ? count($row['final_images']) : 0;
            echo '<tr data-filter-text="' . e($searchText) . '">';
            echo '<td><strong>' . e($row['nombre_trabajo'] ?: ('Trabajo ' . ($row['id'] ?? ''))) . '</strong><br><span class="muted">' . e($row['id'] ?? '') . '</span></td>';
            echo '<td><span class="summary-badge">' . e(publicista_job_status_label($row['estado'] ?? 'draft')) . '</span>';
            if (!empty($wf['pack_final'])) echo '<div class="muted small" style="margin-top:6px;">Definitivo</div>';
            echo '</td>';
            echo '<td>' . e($row['clienta_nombre_snapshot'] ?? '-') . '</td>';
            echo '<td>' . e((string)$finalCount) . '/4</td>';
            echo '<td>' . e(format_created_at($row['updated_at'] ?? '')) . '</td>';
            echo '<td><a class="mini-link" href="' . e(publicista_tab_url(array('job' => $row['id']))) . '">Abrir ficha</a>';
            if (!empty($row['clienta_id'])) echo ' · <a class="mini-link" href="' . e(lamami_tab_url('clientas', array('edit' => $row['clienta_id']))) . '">Clienta</a>';
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
    $restrictionLabels = publicista_restriction_labels($workflow['restriction_flags'] ?? array());
    $descriptorData = is_array($descriptor['data'] ?? null) ? $descriptor['data'] : array();
    $batchState = function_exists('publicista_pipeline_batch_state') ? publicista_pipeline_batch_state($selectedJob) : array();
    $hasPendingBatch = function_exists('publicista_pipeline_has_pending_batch') ? publicista_pipeline_has_pending_batch($selectedJob) : false;
    $batchStatusLabel = function_exists('publicista_batch_status_label') ? publicista_batch_status_label($batchState['status'] ?? '') : '-';
    $pipelineButtonLabel = $hasPendingBatch ? 'Actualizar batch / continuar' : 'Enviar batch de 6 imágenes';

    echo '<section class="panel panel-space">';
    echo '<div class="section-head">';
    echo '<div>';
    echo '<h2>' . e($selectedJob['nombre_trabajo'] ?: 'Trabajo Publicista') . '</h2>';
    echo '<div class="muted">Clienta: ' . e($selectedJob['clienta_nombre_snapshot'] ?? '-') . ' · ID: ' . e($selectedJob['id'] ?? '') . ' · Estado: ' . e(publicista_job_status_label($selectedJob['estado'] ?? 'draft')) . '</div>';
    if (!empty($workflow['pack_final'])) {
        echo '<div class="info-strip" style="margin-top:10px;"><strong>Pack definitivo</strong> · ' . e(format_created_at($workflow['pack_finalized_at'] ?? '')) . '</div>';
    }
    echo '</div>';
    echo '<div class="section-head-actions" style="display:flex;gap:10px;flex-wrap:wrap;">';
    if (!empty($selectedJob['clienta_id'])) {
        echo '<a class="btn-secondary-mini" href="' . e(lamami_tab_url('clientas', array('edit' => $selectedJob['clienta_id']))) . '">Abrir clienta</a>';
    }
    echo '<form method="post" class="inline-form">';
    echo '<input type="hidden" name="action" value="duplicate_publicista_job">';
    echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
    echo '<button class="btn-secondary-mini">Duplicar como base</button>';
    echo '</form>';
    if (!empty($finalImages) && empty($workflow['pack_final'])) {
        echo '<form method="post" class="inline-form">';
        echo '<input type="hidden" name="action" value="mark_publicista_pack_definitive">';
        echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
        echo '<button class="btn-primary">Marcar pack como definitivo</button>';
        echo '</form>';
    }
    echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar este trabajo y su estructura de carpetas?\')">';
    echo '<input type="hidden" name="action" value="delete_publicista_job">';
    echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
    echo '<button class="btn-danger-mini">Eliminar trabajo</button>';
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

    echo '<form method="post" class="form-grid" style="margin-top:16px;">';
    echo '<input type="hidden" name="action" value="save_publicista_job">';
    echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
    field_select_clienta('clienta_id', 'Clienta', $clientas, $selectedJob['clienta_id'] ?? '');
    field_input('nombre_trabajo', 'Nombre interno del pack', $selectedJob['nombre_trabajo'] ?? '', true);
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
    field_textarea('physical_notes', 'Observaciones físicas / matices', $selectedJob['physical_notes'] ?? '', 4);
    field_textarea('restrictions_text', 'Restricciones libres', $workflow['restrictions_text'] ?? '', 3);
    echo '<div class="field full">';
    echo '<label>Restricciones rápidas</label>';
    publicista_render_restriction_checkboxes($workflow['restriction_flags'] ?? array());
    echo '</div>';
    echo '<div class="field">';
    $checkedAuto = !empty($workflow['auto_regenerate']) ? ' checked' : '';
    echo '<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="auto_regenerate" value="1"' . $checkedAuto . '> Auto-regeneración automática</label>';
    echo '<div class="field-help">Déjalo apagado para el modo ahorro máximo. Solo actívalo si quieres que el sistema intente 3 candidatas extra automáticamente.</div>';
    echo '</div>';
    echo '<div class="field">';
    echo '<label>Tono de textos</label>';
    echo '<select name="copy_tone">';
    foreach (publicista_copy_tone_options() as $value => $label) {
        $selTone = (($copyPack['desired_tone'] ?? 'equilibrado') === $value) ? ' selected' : '';
        echo '<option value="' . e($value) . '"' . $selTone . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    field_textarea('services_snapshot', 'Servicios snapshot', $selectedJob['services_snapshot'] ?? '', 4);
    field_textarea('tarifas_snapshot', 'Tarifas snapshot', $selectedJob['tarifas_snapshot'] ?? '', 4);
    field_textarea('copy_examples_base', 'Ejemplos base de redacción (opcional)', $copyPack['examples_base'] ?? '', 5);
    field_textarea('notas', 'Notas internas', $selectedJob['notas'] ?? '', 4);
    echo '<div class="full"><button class="btn-primary">Guardar configuración del trabajo</button></div>';
    echo '</form>';
    echo '</section>';

    echo '<div class="cards two">';
    echo '<section class="panel" id="publicistaQuickActions">';
    echo '<h3>Acciones del paso actual</h3>';
    echo '<form method="post" enctype="multipart/form-data" class="form-grid">';
    echo '<input type="hidden" name="action" value="run_publicista_image_pipeline">';
    echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
    echo '<div class="field full">';
    echo '<label>Imagen original de la clienta</label>';
    echo '<input type="file" name="source_image" accept="image/jpeg,image/png,image/webp">';
    echo '<div class="field-help">Sube una nueva imagen si quieres cambiar la base. Si no subes nada, este botón reutiliza la foto actual del trabajo. Si ya hay un batch enviado, el mismo botón sirve para comprobarlo y continuar.</div>';
    echo '</div>';
    echo '<div class="full" style="display:flex;gap:10px;flex-wrap:wrap;">';
    echo '<button class="btn-primary">' . e($pipelineButtonLabel) . '</button>';
    echo '</div>';
    echo '</form>';

    echo '<form method="post" enctype="multipart/form-data" class="form-grid" style="margin-top:14px;">';
    echo '<input type="hidden" name="action" value="prepare_publicista_job_engine">';
    echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
    echo '<div class="field full">';
    echo '<label>Solo preparar origen y descriptor</label>';
    echo '<input type="file" name="source_image" accept="image/jpeg,image/png,image/webp">';
    echo '<div class="field-help">Úsalo si antes de generar imágenes quieres comprobar que el recorte 1:1 y la descripción automática encajan bien con la clienta.</div>';
    echo '</div>';
    echo '<div class="full"><button class="btn-secondary-mini">Preparar origen</button></div>';
    echo '</form>';

    echo '<div class="info-strip"><strong>Última acción:</strong> ' . e($processing['last_action'] ?? '-') . '</div>';
    echo '<div class="info-strip" style="margin-top:10px;"><strong>Resumen pipeline:</strong> ' . e($pipeline['summary'] ?? '-') . '</div>';
    echo '<div class="info-strip" style="margin-top:10px;"><strong>Request ID:</strong> ' . e($processing['last_openai_request_id'] ?? '-') . '</div>';
    if (!empty($processing['last_error'])) {
        echo '<div class="info-strip" style="margin-top:10px;"><strong>Último error:</strong> ' . e($processing['last_error']) . '</div>';
    }
    echo '</section>';

    echo '<section class="panel">';
    echo '<h3>Original y descriptor</h3>';
    if (!empty($source['stored_path'])) {
        publicista_render_job_image_card($source['stored_path'], 'Original');
    }
    if (!empty($localAssets['prepared_square_path'])) {
        publicista_render_job_image_card($localAssets['prepared_square_path'], 'Origen 1:1');
    }
    if (!empty($localAssets['face_blur_path'])) {
        publicista_render_job_image_card($localAssets['face_blur_path'], 'Blur facial origen');
    }
    if (!empty($localAssets['preview_path'])) {
        publicista_render_job_image_card($localAssets['preview_path'], 'Preview');
    }
    if (!empty($descriptorData)) {
        echo '<div class="info-strip"><strong>Descriptor OpenAI</strong></div>';
        publicista_render_publicista_descriptor_summary($descriptorData);
    } else {
        echo '<div class="empty">Aún no se ha generado el descriptor estructurado.</div>';
    }
    echo '</section>';
    echo '</div>';

    echo '<div class="cards two">';
    echo '<section class="panel">';
    echo '<h3>Prompt maestro y restricciones</h3>';
    if (!empty($restrictionLabels)) {
        echo '<div class="info-strip"><strong>Restricciones rápidas:</strong> ' . e(implode(' · ', $restrictionLabels)) . '</div>';
    }
    if (!empty($workflow['restrictions_text'])) {
        echo '<div class="info-strip" style="margin-top:10px;"><strong>Restricciones libres:</strong> ' . e($workflow['restrictions_text']) . '</div>';
    }
    if (!empty($promptMaster['text'])) {
        echo '<div class="info-strip" style="margin-top:10px;"><strong>Construido:</strong> ' . e(format_created_at($promptMaster['built_at'] ?? '')) . '</div>';
        echo '<details style="margin-top:12px;"><summary>Ver prompt maestro</summary><pre style="white-space:pre-wrap;word-break:break-word;">' . e($promptMaster['text']) . '</pre></details>';
        if (!empty($promptMaster['variants']) && is_array($promptMaster['variants'])) {
            echo '<details style="margin-top:12px;"><summary>Ver variantes de prompt</summary>';
            echo '<ol style="margin:10px 0 0 18px;">';
            foreach ($promptMaster['variants'] as $variant) {
                echo '<li style="margin-bottom:8px;"><span class="muted">' . e($variant) . '</span></li>';
            }
            echo '</ol></details>';
        }
    } else {
        echo '<div class="empty">Aún no se ha construido el prompt maestro.</div>';
    }
    echo '</section>';

    echo '<section class="panel" id="publicistaFinals">';
    echo '<h3>Finales del pack</h3>';
    if (!empty($finalImages)) {
        echo '<div class="cards two">';
        foreach ($finalImages as $finalRow) {
            echo '<div class="panel" style="padding:12px;">';
            if (!empty($finalRow['final_path'])) {
                publicista_render_job_image_card($finalRow['final_path'], ($finalRow['id'] ?? 'Final'));
            }
            echo '<div class="info-strip" style="margin-top:10px;"><strong>Origen:</strong> ' . e($finalRow['source_candidate_id'] ?? '-') . '</div>';
            echo '<div class="info-strip" style="margin-top:10px;"><strong>Score:</strong> ' . e((string)($finalRow['evaluation_score'] ?? 0)) . '</div>';
            echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">';
            echo '<form method="post" class="inline-form">';
            echo '<input type="hidden" name="action" value="refresh_publicista_final_local">';
            echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
            echo '<input type="hidden" name="final_id" value="' . e($finalRow['id'] ?? '') . '">';
            echo '<input type="hidden" name="mode" value="blur">';
            echo '<button class="btn-secondary-mini">Reaplicar blur</button>';
            echo '</form>';
            echo '<form method="post" class="inline-form">';
            echo '<input type="hidden" name="action" value="refresh_publicista_final_local">';
            echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
            echo '<input type="hidden" name="final_id" value="' . e($finalRow['id'] ?? '') . '">';
            echo '<input type="hidden" name="mode" value="reframe">';
            echo '<button class="btn-secondary-mini">Rehacer 1:1</button>';
            echo '</form>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="empty">Todavía no hay 4 finales generadas.</div>';
    }
    echo '</section>';
    echo '</div>';

    echo '<section class="panel panel-space" id="publicistaCandidates">';
    echo '<h3>Candidatas generadas</h3>';
    if (!empty($candidates)) {
        echo '<div class="cards two">';
        foreach ($candidates as $cand) {
            echo '<div class="panel" style="padding:12px;">';
            echo '<div class="branch-panel-head"><h4 style="margin:0;">' . e($cand['id'] ?? 'candidate') . '</h4><span class="summary-badge">' . e((string)($cand['effective_score'] ?? 0)) . '</span></div>';
            if (!empty($cand['preview_path'])) {
                publicista_render_job_image_card($cand['preview_path'], 'Preview');
            } elseif (!empty($cand['raw_path'])) {
                publicista_render_job_image_card($cand['raw_path'], 'Raw');
            }
            echo '<div class="info-strip" style="margin-top:10px;"><strong>Estado:</strong> ' . e($cand['status'] ?? '-') . '</div>';
            echo '<div class="info-strip" style="margin-top:10px;"><strong>Ronda:</strong> ' . e($cand['round'] ?? '-') . '</div>';
            if (!empty($cand['evaluation']) && is_array($cand['evaluation'])) {
                echo '<div class="info-strip" style="margin-top:10px;"><strong>Likeness / quality / overall:</strong> ' . e((string)($cand['evaluation']['likeness_score'] ?? 0)) . ' / ' . e((string)($cand['evaluation']['quality_score'] ?? 0)) . ' / ' . e((string)($cand['evaluation']['overall_score'] ?? 0)) . '</div>';
                if (!empty($cand['evaluation']['issues']) && is_array($cand['evaluation']['issues'])) {
                    echo '<div class="info-strip" style="margin-top:10px;"><strong>Issues:</strong> ' . e(implode(' | ', $cand['evaluation']['issues'])) . '</div>';
                }
            }
            if (!empty($cand['selected'])) {
                echo '<div class="info-strip" style="margin-top:10px;"><strong>Top 4:</strong> Seleccionada</div>';
            }
            if (!empty($cand['error'])) {
                echo '<div class="info-strip" style="margin-top:10px;"><strong>Error:</strong> ' . e($cand['error']) . '</div>';
            }
            echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">';
            echo '<form method="post" class="inline-form">';
            echo '<input type="hidden" name="action" value="regenerate_publicista_candidate">';
            echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
            echo '<input type="hidden" name="candidate_id" value="' . e($cand['id'] ?? '') . '">';
            echo '<button class="btn-secondary-mini">Regenerar esta</button>';
            echo '</form>';
            echo '</div>';
            echo '<details style="margin-top:12px;"><summary>Ver prompt</summary><pre style="white-space:pre-wrap;word-break:break-word;">' . e($cand['prompt'] ?? '') . '</pre></details>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="empty">Aún no hay candidatas generadas.</div>';
    }
    echo '</section>';

    echo '<section class="panel panel-space" id="publicistaCopyPack">';
    echo '<div class="section-head"><div><h3>Títulos, textos y export</h3><p>Genera el pack de copy, revisa variantes y exporta todo para publicar.</p></div><div class="section-head-actions">';
    echo '<form method="post" class="inline-form">';
    echo '<input type="hidden" name="action" value="generate_publicista_copy_pack">';
    echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
    echo '<button class="btn-primary">Generar / regenerar textos</button>';
    echo '</form>';
    echo '</div></div>';
    echo '<div class="cards three">';
    echo '<div class="info-strip"><strong>Versión actual</strong><br>' . e($copyPack['current_version_id'] ?? 'Pendiente') . '</div>';
    echo '<div class="info-strip"><strong>Generado</strong><br>' . e(format_created_at($copyPack['generated_at'] ?? '')) . '</div>';
    echo '<div class="info-strip"><strong>Reintentos</strong><br>' . e((string)($copyPack['retry_count'] ?? 0)) . '</div>';
    echo '</div>';
    if (!empty($copyPack['last_error'])) {
        echo '<div class="info-strip" style="margin-top:10px;"><strong>Último error textos:</strong> ' . e($copyPack['last_error']) . '</div>';
    }
    echo '<div class="cards two" style="margin-top:14px;">';
    echo '<section class="panel">';
    echo '<h4>Resumen actual</h4>';
    if ($currentCopyVersion) {
        echo '<div class="info-strip"><strong>Tono:</strong> ' . e(publicista_copy_tone_label($currentCopyVersion['tone'] ?? 'equilibrado')) . '</div>';
        echo '<div class="info-strip" style="margin-top:10px;"><strong>Enfoque:</strong> ' . e($currentCopyVersion['pack_angle'] ?? '-') . '</div>';
        if (!empty($copyPack['current_export_txt_path'])) {
            echo '<div class="info-strip" style="margin-top:10px;"><strong>TXT export:</strong> <a class="mini-link" href="' . e($copyPack['current_export_txt_path']) . '" target="_blank" rel="noopener">Abrir</a></div>';
        }
        if (!empty($copyPack['current_export_json_path'])) {
            echo '<div class="info-strip" style="margin-top:10px;"><strong>JSON export:</strong> <a class="mini-link" href="' . e($copyPack['current_export_json_path']) . '" target="_blank" rel="noopener">Abrir</a></div>';
        }
        if (!empty($currentCopyVersion['title_options']) && is_array($currentCopyVersion['title_options'])) {
            echo '<details style="margin-top:12px;"><summary>Ver títulos</summary><ul style="margin:10px 0 0 18px;">';
            foreach ($currentCopyVersion['title_options'] as $title) {
                echo '<li>' . e($title) . '</li>';
            }
            echo '</ul></details>';
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



function publicista_render_intro_guide_panel() {
    $steps = array(
        array(
            'num' => 1,
            'title' => 'Elige clienta y nombre del pack',
            'body' => 'Empieza en <strong>Nuevo trabajo Publicista</strong>. Selecciona la clienta y pon un nombre interno fácil de reconocer.'
        ),
        array(
            'num' => 2,
            'title' => 'Sube la foto base y define matices',
            'body' => 'Añade la imagen original y, si hace falta, escribe matices físicos, restricciones y tono deseado de los textos.'
        ),
        array(
            'num' => 3,
            'title' => 'Decide cómo arrancar',
            'body' => '<strong>Crear borrador</strong> solo guarda la base. <strong>Crear y generar</strong> lanza todo el proceso de imágenes desde esa misma foto.'
        ),
        array(
            'num' => 4,
            'title' => 'Espera el batch y vuelve a continuar',
            'body' => 'En modo ahorro máximo se envían 6 candidatas por batch. Si queda pendiente, reabre la ficha y pulsa <strong>Actualizar batch / continuar</strong>.'
        ),
        array(
            'num' => 5,
            'title' => 'Revisa las candidatas y el top 4 final',
            'body' => 'Cuando el batch termine, verás candidatas y 4 finales. Si una flojea, usa <strong>Regenerar esta</strong> en la candidata concreta.'
        ),
        array(
            'num' => 6,
            'title' => 'Genera textos y cierra el trabajo',
            'body' => 'Con el pack visual ya bueno, pulsa <strong>Generar / regenerar textos</strong>. Si todo está correcto, termina con <strong>Marcar pack como definitivo</strong>.'
        ),
    );

    echo '<section class="panel panel-space publicista-guide-panel">';
    echo '<div class="section-head"><div><h2>Qué hace Publicista y en qué orden usarlo</h2><p>Esta sección convierte una foto real de una clienta en un pack publicitario completo: imágenes finales, títulos, anuncios y export listo para publicar.</p></div></div>';
    echo '<div class="publicista-steps-grid">';
    foreach ($steps as $step) {
        echo '<article class="publicista-step-card is-pending">';
        echo '<div class="publicista-step-top"><span class="publicista-step-num">' . e((string)$step['num']) . '</span><span class="publicista-step-status">Paso</span></div>';
        echo '<h4>' . e($step['title']) . '</h4>';
        echo '<p>' . $step['body'] . '</p>';
        echo '</article>';
    }
    echo '</div>';
    echo '<div class="publicista-guide-note"><strong>Idea clave:</strong> Publicista no es solo “generar imágenes”. Primero prepara una base fiel a la clienta, luego monta el pack visual, después crea el pack de textos y por último te deja cerrar una versión definitiva.</div>';
    echo '</section>';
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

    if ($hasSource && !$hasPrepared) {
        $currentStep = 2;
        $headline = 'Ya hay foto subida, pero aún no está preparada la base técnica del trabajo.';
        $hint = 'Puedes lanzar directamente el pipeline completo o preparar antes el origen si quieres revisar recorte y descriptor.';
        $cta = array('type' => 'anchor', 'href' => '#publicistaQuickActions', 'label' => 'Ir a acciones del paso actual');
    } elseif ($hasPendingBatch) {
        $currentStep = 3;
        $headline = 'El batch de imágenes ya está enviado y ahora toca continuarlo.';
        $hint = 'No hace falta subir otra foto. Pulsa <strong>Actualizar batch / continuar</strong> para consultar si terminó y, si ya está listo, montar candidatas + finales.';
        $cta = array('type' => 'continue_batch', 'label' => 'Actualizar batch / continuar');
    } elseif (!$hasCandidates && !$hasFullFinals) {
        $currentStep = 3;
        $headline = 'La base ya está lista; ahora toca generar las 6 candidatas.';
        $hint = 'Usa la foto actual del trabajo y lanza el botón principal de imágenes.';
        $cta = array('type' => 'run_pipeline', 'label' => 'Enviar batch de 6 imágenes');
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
        $hint = 'Si estás conforme con este pack, márcalo como definitivo. Eso deja claro cuál es la versión final aprobada.';
        $cta = array('type' => 'mark_definitive', 'label' => 'Marcar pack como definitivo');
    } else {
        $currentStep = 6;
        $headline = 'Este trabajo ya está cerrado como pack definitivo.';
        $hint = 'Puedes revisar imágenes, textos o duplicarlo como base para otro pack.';
        $cta = array('type' => 'anchor', 'href' => '#publicistaCopyPack', 'label' => 'Ver textos y export');
    }

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
                ? 'La foto base ya está preparada y el descriptor visual ya existe.'
                : 'Aquí se sube la foto original y se prepara la base técnica: recorte 1:1, blur facial y descriptor estructurado.'
        ),
        array(
            'num' => 3,
            'title' => 'Generación de candidatas',
            'status' => $hasPendingBatch ? 'waiting' : (($hasCandidates || $hasFullFinals) ? 'done' : (($currentStep === 3) ? 'current' : 'pending')),
            'body' => $hasPendingBatch
                ? 'El batch de 6 imágenes está en marcha. Cuando quieras revisar si terminó, vuelve a pulsar el botón principal.'
                : (($hasCandidates || $hasFullFinals)
                    ? 'Las candidatas ya fueron generadas.'
                    : 'En este paso se lanzan las 6 candidatas base en modo ahorro máximo.')
        ),
        array(
            'num' => 4,
            'title' => 'Revisión del pack visual',
            'status' => $hasFullFinals ? 'done' : (($hasCandidates || $currentStep === 4) ? 'current' : 'pending'),
            'body' => $hasFullFinals
                ? 'Ya tienes 4 finales listas y el top visual está montado.'
                : 'Aquí revisas las candidatas y regeneras solo las que no convencen hasta dejar un top 4 limpio.'
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
        echo '<button class="btn-primary">' . e($cta['label'] ?? 'Marcar definitivo') . '</button>';
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
    echo '      <div class="login-help">Login: nuria / josue</div>';
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
        'logout' => 'Salir'
    );

    $lamamiPages = array('lamami', 'interesadas', 'clientas', 'lamamibot');

    echo '<aside id="appSidebar" class="sidebar">';
    echo '<div class="brand">LaMami <span>CRM</span></div>';
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
            echo '<strong>' . e($row['telefono']) . '</strong>';
            echo '<br><i class="muted">(' . e(format_created_at(isset($row['created_at']) ? $row['created_at'] : '')) . ')</i>';
            echo '<br><span class="muted">' . e($row['observaciones']) . '</span>';
            echo '</td>';
            echo '<td>' . e($row['movil_origen']) . '</td>';
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
            echo e($row['telefono']);
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
    $clientes = get_active_clientas();
    $edit = null;
    $editId = request_get('edit');
    if ($editId !== '') {
        $edit = storage_find_by_id('bots.json', $editId);
    }

    page_header('Bots', 'Asignación actual y lectura de leads históricos');

    echo '<div class="cards two">';
    echo '<section class="panel">';
    echo '<div class="section-head">';
    echo '<div>';
    echo '<h2>' . ($edit ? 'Ficha de bot' : 'Nuevo bot') . '</h2>';
    if ($edit) {
        echo '<div class="muted">Created at: <i>' . e(format_created_at(isset($edit['created_at']) ? $edit['created_at'] : '')) . '</i></div>';
        echo '<div class="muted">Estado runtime: ' . bot_runtime_dot_html($edit) . '</div>';
        echo '<div class="bot-runtime-actions">';
        render_bot_runtime_toggle_form($edit, 'index.php?page=bots&edit=' . urlencode($edit['id'] ?? ''), false);
        $girlsUrl = bot_girls_panel_url($edit);
        if ($girlsUrl !== '') {
            echo '<a class="btn-panel-link" target="_blank" rel="noopener noreferrer" href="' . e($girlsUrl) . '">Girls config</a>';
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

    field_input('nombre_bot', 'Nombre bot', $edit ? $edit['nombre_bot'] : '', true);
    field_input('telefono_bot', 'Teléfono bot', $edit ? $edit['telefono_bot'] : '');
    field_input('waha_port', 'WAHA port', $edit ? $edit['waha_port'] : '');
    echo '<div class="field">';
    echo '<label>IP servidor</label>';
    echo '<select name="server_ip">';
    foreach (array(
        '100.117.92.74' => '100.117.92.74 · oficina',
        '100.113.76.93' => '100.113.76.93 · josue',
        '100.76.30.118' => '100.76.30.118 · liveyourdre2',
    ) as $ip => $label) {
        $sel = (($edit['server_ip'] ?? '100.113.76.93') === $ip) ? ' selected' : '';
        echo '<option value="' . e($ip) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div class="field">';
    echo '<label>Modo bot</label>';
    echo '<select name="bot_mode">';
    foreach (array('multiple' => 'Multiple', 'personal' => 'Personal') as $k => $label) {
        $sel = (($edit['bot_mode'] ?? 'multiple') === $k) ? ' selected' : '';
        echo '<option value="' . e($k) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';
    field_select_clienta('cliente_id', 'Clienta actual vinculada', $clientes, $edit ? $edit['cliente_id'] : '');
    echo '<input type="hidden" name="estado" value="' . e($edit ? ($edit['estado'] ?? 'activo') : 'activo') . '">';

    echo '<div class="full"><button class="btn-primary">Guardar bot</button></div>';
    echo '</form>';

    if ($edit) {
        render_bot_generated_assets_panel($edit);
        render_bot_leads_panel($edit);
    }

    echo '</section>';

    echo '<section class="panel">';
    echo '<h2>Listado</h2>';
    if (empty($items)) {
        echo '<div class="empty">No hay bots todavía.</div>';
    } else {
        $cidx = clientes_index();
        $items = sort_desc_by_key($items, 'created_at');
        render_live_filter('#botsRows tr[data-filter-text]', 'Buscar bot...');
        echo '<div class="table-wrap"><table><thead><tr>';
        echo '<th>Bot</th><th>Clienta actual</th><th>Estado</th><th>Teléfono</th><th>WAHA</th><th>IP</th><th>Modo</th><th>Acciones</th>';
        echo '</tr></thead><tbody id="botsRows">';
        foreach ($items as $row) {
            $clientName = isset($cidx[$row['cliente_id']]['nombre']) ? $cidx[$row['cliente_id']]['nombre'] : 'Sin vincular';

            $runtimeLabel = bot_runtime_label($row);

            $searchText = strtolower(trim(
                ($row['nombre_bot'] ?? '') . ' ' .
                ($row['telefono_bot'] ?? '') . ' ' .
                ($row['waha_port'] ?? '') . ' ' .
                ($row['server_ip'] ?? '') . ' ' .
                ($row['bot_mode'] ?? '') . ' ' .
                ($clientName ?? '') . ' ' .
                $runtimeLabel
            ));
            echo '<tr data-filter-text="' . e($searchText) . '">';
            echo '<td><strong>' . e($row['nombre_bot']) . '</strong></td>';
            echo '<td>' . ($clientName ? e($clientName) : '<span class="muted">Sin clienta</span>') . '</td>';
            echo '<td>';
            echo '<div class="runtime-cell">' . bot_runtime_dot_html($row);
            render_bot_runtime_toggle_form($row, 'index.php?page=bots', true);
            echo '</div>';
            echo '</td>';
            echo '<td>' . e($row['telefono_bot']) . '<br><i class="muted">(' . e(format_created_at(isset($row['created_at']) ? $row['created_at'] : '')) . ')</i></td>';
            echo '<td>' . e($row['waha_port']) . '</td>';
            echo '<td>' . e($row['server_ip'] ?: '-') . '</td>';
            echo '<td>' . e($row['bot_mode']) . '</td>';

            echo '<td>';
            echo '<a class="mini-link" href="index.php?page=bots&edit=' . e($row['id']) . '">Abrir ficha</a> ';
            $girlsUrl = bot_girls_panel_url($row);
            if ($girlsUrl !== '') {
                echo '<a class="btn-panel-link compact" target="_blank" rel="noopener noreferrer" href="' . e($girlsUrl) . '">Girls config</a> ';
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

    $clienta = null;
    if (!empty($bot['cliente_id'])) {
        $clienta = storage_find_by_id('clientes.json', $bot['cliente_id']);
    }

    echo '<div class="info-strip">';
    echo '<strong>Bot:</strong> ' . e($bot['nombre_bot']);
    echo '<br>';
    echo '<strong>Clienta vinculada:</strong> ' . e($clienta ? ($clienta['nombre'] ?? '') : 'Sin vincular');
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

    render_generated_text_box('Texto1 · JSON del bot', 'bot_texto1', $g['texto1'] ?? '');
    render_generated_text_box('Texto2 · JSON del mode switch', 'bot_texto2', $g['texto2'] ?? '');
    render_generated_text_box('Texto3 · docker-compose.yml', 'bot_texto3', $g['texto3'] ?? '');
    render_generated_text_box('Texto4 · Enlace panel de chicas', 'bot_texto4', $g['texto4'] ?? '');
    render_generated_text_box(
        'Texto5 · Enlaces encender / apagar',
        'bot_texto5',
        "Encender: " . ($g['texto5_start'] ?? '') . "\n" .
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
        'destacamos_reminder_enabled' => array(
            'label' => 'Activar recordatorio de Destacamos',
            'help' => '1 = activado, 0 = desactivado. Si está activado, el sistema generará avisos automáticos para subir publicidad a Destacamos.'
        ),
        'destacamos_reminder_times' => array(
            'label' => 'Horas de Destacamos',
            'help' => 'Una hora por línea, en formato HH:MM. Ejemplo: 00:01 y 12:01. Cada hora genera un aviso independiente.'
        ),
        'destacamos_reminder_window_minutes' => array(
            'label' => 'Tolerancia de Destacamos (minutos)',
            'help' => 'Durante cuántos minutos después de la hora programada se sigue aceptando generar el aviso, por si el cron no corre justo a tiempo.'
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
            'help' => 'Selecciona qué teléfono e IP usarán los avisos para salir por WhatsApp.'
        ),
        'whatsapp_target_phones' => array(
            'label' => 'Teléfonos destino de avisos',
            'help' => 'Números que recibirán los avisos. Puedes poner uno por línea.'
        ),
    );
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
            echo '<div class="field">';
            echo '<label>' . e($label) . '</label>';
            echo '<select name="whatsapp_sender_key">';
            foreach ($senderPresets as $presetKey => $preset) {
                $selected = ($senderKey === $presetKey) ? ' selected' : '';
                echo '<option value="' . e($presetKey) . '"' . $selected . '>' . e($preset['label']) . '</option>';
            }
            echo '</select>';
            echo '<div class="field-help">' . e($help) . '</div>';
            echo '</div>';
            continue;
        }

        if ($key === 'whatsapp_target_phones' || $key === 'destacamos_reminder_times') {
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
    echo '<strong>Destacamos:</strong><br>';
    echo 'Activo: ' . e((string)aviso_cfg('destacamos_reminder_enabled', 1)) . '<br>';
    echo 'Horas: ' . e(is_array(aviso_cfg('destacamos_reminder_times', array())) ? implode(', ', aviso_cfg('destacamos_reminder_times', array())) : str_replace("\n", ', ', (string)aviso_cfg('destacamos_reminder_times', "00:01\n12:01"))) . '<br>';
    echo 'Tolerancia: ' . e((string)aviso_cfg('destacamos_reminder_window_minutes', 90)) . ' min<br>';
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
    echo '<strong>Nota:</strong> `avisos_config.php` es la base por defecto, y lo que guardas aquí la sobrescribe dinámicamente.';
    echo '</div>';

    echo '</section>';

    echo '</div>';
}

function render_josue_page() {
    $anunciosUnlocked = !empty($_SESSION['josue_anuncios_unlocked']);

    $tab = request_get('tab', 'publias');
    $allowed = array('publias', 'captacion', 'sendtaxs', 'notas', 'autotube', 'waha', 'telefonos', 'agenda', 'config', 'configm');
    if ($anunciosUnlocked) {
        $allowed[] = 'anuncios';
    }

    if (!in_array($tab, $allowed, true)) {
        $tab = 'publias';
    }

    $settings = storage_read('settings.json');
    $anuncios = storage_read('anuncios.json');
    $telefonos = storage_read('telefonos.json');
    $agenda = storage_read('agenda.json');
    $sendtaxsState = isset($settings['sendtaxs_state']) && is_array($settings['sendtaxs_state'])
        ? $settings['sendtaxs_state']
        : array();

    $sendtaxsDefaults = array_merge(array(
        'porc_plaza' => '31.5',
        'porc_publi_sched' => '28.4',
        'porc_publipub' => '21.0',
        'porc_pubcasas' => '19.1',
        'total_deseado' => '51',
    ), $sendtaxsState);

    $text = in_array($tab, array('publias', 'captacion', 'notas', 'waha'), true)
        ? (isset($settings[$tab . '_text']) ? (string)$settings[$tab . '_text'] : '')
        : '';

    $isEdit = request_get('edit_text', '') === '1';

    if (in_array($tab, array('publias', 'captacion', 'notas', 'waha'), true) && trim($text) === '') {
        $isEdit = true;
    }

    page_header('Josue', 'Sección de trabajo interno');

    echo '<section class="panel panel-josue">';

    if (!$anunciosUnlocked) {
        echo '<div class="josue-unlock-box">';
        echo '<form method="post" class="josue-unlock-form">';
        echo '<input type="hidden" name="action" value="unlock_josue_anuncios">';
        echo '<div class="field">';
        echo '<label>Desbloquear Anuncios</label>';
        echo '<input type="password" name="password" placeholder="Contraseña">';
        echo '</div>';
        echo '<button class="btn-secondary-mini">Entrar</button>';
        echo '</form>';
        echo '</div>';
    }

    echo '<div class="subtabs">';
    if ($anunciosUnlocked) {
        echo '<a class="subtab ' . ($tab === 'anuncios' ? 'active' : '') . '" href="index.php?page=josue&tab=anuncios">Anuncios</a>';
    }
    echo '<a class="subtab ' . ($tab === 'telefonos' ? 'active' : '') . '" href="index.php?page=josue&tab=telefonos">Telefonos</a>';
    echo '<a class="subtab ' . ($tab === 'waha' ? 'active' : '') . '" href="index.php?page=josue&tab=waha">WAHA</a>';
    echo '<a class="subtab ' . ($tab === 'publias' ? 'active' : '') . '" href="index.php?page=josue&tab=publias">PublIas</a>';
    echo '<a class="subtab ' . ($tab === 'captacion' ? 'active' : '') . '" href="index.php?page=josue&tab=captacion">Captacion</a>';
    echo '<a class="subtab ' . ($tab === 'sendtaxs' ? 'active' : '') . '" href="index.php?page=josue&tab=sendtaxs">SendTaxs</a>';
    echo '<a class="subtab ' . ($tab === 'agenda' ? 'active' : '') . '" href="index.php?page=josue&tab=agenda">Agenda</a>';
    //echo '<a class="subtab ' . ($tab === 'avisos' ? 'active' : '') . '" href="index.php?page=josue&tab=avisos">Avisos</a>';
    echo '<a class="subtab ' . ($tab === 'config' ? 'active' : '') . '" href="index.php?page=josue&tab=config">Config</a>';
    echo '<a class="subtab ' . ($tab === 'configm' ? 'active' : '') . '" href="index.php?page=josue&tab=configm">ConfigM</a>';
    echo '<a class="subtab ' . ($tab === 'notas' ? 'active' : '') . '" href="index.php?page=josue&tab=notas">Notas</a>';    
    echo '<a class="subtab ' . ($tab === 'autotube' ? 'active' : '') . '" href="index.php?page=josue&tab=autotube">Autotube</a>';
    
    echo '</div>';

    echo '<div class="subtab-content">';

    if ($tab === 'sendtaxs') {
        echo '<div class="sendtaxs-tool">';
        echo '<div class="sendtaxs-head">';
        echo '<h2>Ajustador de Porcentajes y Tasas de Envío</h2>';
        echo '<p>Ingresa los porcentajes deseados. Puedes editarlos libremente aunque no sumen 100%. Usa "Normalizar a 100%" para escalar proporcionalmente, o pulsa "Calcular" y se normalizará automáticamente antes de generar los ajustes.</p>';
        echo '</div>';

        echo '<div class="sendtaxs-grid">';
        echo '  <div class="field"><label>Porcentaje Plaza</label><input type="number" id="porc_plaza" value="' . e($sendtaxsDefaults['porc_plaza']) . '" min="0" max="100" step="0.1"></div>';
        echo '  <div class="field"><label>Porcentaje Lamami</label><input type="number" id="porc_publi_sched" value="' . e($sendtaxsDefaults['porc_publi_sched']) . '" min="0" max="100" step="0.1"></div>';
        echo '  <div class="field"><label>Porcentaje Publicista</label><input type="number" id="porc_publipub" value="' . e($sendtaxsDefaults['porc_publipub']) . '" min="0" max="100" step="0.1"></div>';
        echo '  <div class="field"><label>Porcentaje Casawasap</label><input type="number" id="porc_pubcasas" value="' . e($sendtaxsDefaults['porc_pubcasas']) . '" min="0" max="100" step="0.1"></div>';
        echo '</div>';

        echo '<div class="sendtaxs-toolbar">';
        echo '  <button type="button" class="btn-secondary-mini" onclick="sendtaxsNormalizar()">Normalizar a 100%</button>';
        echo '</div>';

        echo '<div class="sendtaxs-grid sendtaxs-grid-single">';
        echo '  <div class="field"><label>Total mensajes diarios deseado</label><input type="number" id="total_deseado" value="' . e($sendtaxsDefaults['total_deseado']) . '" min="1" step="1"></div>';
        echo '</div>';

        echo '<div class="sendtaxs-toolbar">';
        echo '  <button type="button" class="btn-primary" onclick="sendtaxsCalcular()">Calcular envíos y ajustes</button>';
        echo '</div>';

        echo '<div id="sendtaxs_resultados" class="sendtaxs-resultados"></div>';
        echo '</div>';

        echo <<<'HTML'
<script>
(function () {
    const sendtaxsEnvActual = { plaza: 14, publi_sched: 12.6, publipub: 9.3, pubcasas: 8.5 };
    const sendtaxsRangosActual = {
        plaza_pico_min: 2300, plaza_pico_max: 5000, plaza_resto_min: 3000, plaza_resto_max: 6000,
        publi_pico_min: 2300, publi_pico_max: 5200, publi_resto_min: 5300, publi_resto_max: 6300,
        publipub_min: 45, publipub_max: 90,
        pubcasas_min: 90, pubcasas_max: 120
    };

    function sendtaxsGetIds() {
        return ["porc_plaza", "porc_publi_sched", "porc_publipub", "porc_pubcasas"];
    }

    window.sendtaxsNormalizar = function () {
        const ids = sendtaxsGetIds();
        let suma = 0;
        ids.forEach(function (id) {
            const el = document.getElementById(id);
            suma += parseFloat(el ? el.value : 0) || 0;
        });
        if (suma === 0) return;

        ids.forEach(function (id) {
            const el = document.getElementById(id);
            const val = parseFloat(el ? el.value : 0) || 0;
            const nuevoVal = (val / suma) * 100;
            if (el) el.value = nuevoVal.toFixed(1);
        });
    };

    window.sendtaxsCalcular = function () {
        const ids = sendtaxsGetIds();
        let suma = 0;
        ids.forEach(function (id) {
            const el = document.getElementById(id);
            suma += parseFloat(el ? el.value : 0) || 0;
        });

        if (Math.abs(suma - 100) > 0.1) {
            window.sendtaxsNormalizar();
        }

        const porcPlazaEl = document.getElementById("porc_plaza");
        const porcPubliSchedEl = document.getElementById("porc_publi_sched");
        const porcPublipubEl = document.getElementById("porc_publipub");
        const porcPubcasasEl = document.getElementById("porc_pubcasas");
        const totalEl = document.getElementById("total_deseado");
        const resultadosEl = document.getElementById("sendtaxs_resultados");

        if (!porcPlazaEl || !porcPubliSchedEl || !porcPublipubEl || !porcPubcasasEl || !totalEl || !resultadosEl) {
            return;
        }

        const porcs = {
            plaza: (parseFloat(porcPlazaEl.value) || 0) / 100,
            publi_sched: (parseFloat(porcPubliSchedEl.value) || 0) / 100,
            publipub: (parseFloat(porcPublipubEl.value) || 0) / 100,
            pubcasas: (parseFloat(porcPubcasasEl.value) || 0) / 100
        };

        const total = parseFloat(totalEl.value) || 0;
        if (total <= 0) {
            resultadosEl.innerHTML = '<div class="sendtaxs-card"><strong>Indica un total válido mayor que 0.</strong></div>';
            return;
        }

        const envNuevos = {
            plaza: (total * porcs.plaza).toFixed(1),
            publi_sched: (total * porcs.publi_sched).toFixed(1),
            publipub: (total * porcs.publipub).toFixed(1),
            pubcasas: (total * porcs.pubcasas).toFixed(1)
        };

        const plazaVal = parseFloat(envNuevos.plaza);
        const publiSchedVal = parseFloat(envNuevos.publi_sched);
        const publipubVal = parseFloat(envNuevos.publipub);
        const pubcasasVal = parseFloat(envNuevos.pubcasas);

        const scales = {
            plaza: plazaVal > 0 ? (sendtaxsEnvActual.plaza / plazaVal) : 0,
            publi_sched: publiSchedVal > 0 ? (sendtaxsEnvActual.publi_sched / publiSchedVal) : 0,
            publipub: publipubVal > 0 ? (sendtaxsEnvActual.publipub / publipubVal) : 0,
            pubcasas: pubcasasVal > 0 ? (sendtaxsEnvActual.pubcasas / pubcasasVal) : 0
        };

        let html = "";
        html += `<div class="sendtaxs-card">`;
        html += `<h3>Envíos diarios calculados</h3>`;
        html += `<ul class="sendtaxs-list">`;
        html += `<li><strong>Plaza:</strong> ${envNuevos.plaza} mensajes/día</li>`;
        html += `<li><strong>Lamami:</strong> ${envNuevos.publi_sched} mensajes/día</li>`;
        html += `<li><strong>Publicista:</strong> ${envNuevos.publipub} mensajes/día</li>`;
        html += `<li><strong>Casawasap:</strong> ${envNuevos.pubcasas} mensajes/día</li>`;
        html += `</ul>`;
        html += `</div>`;

        html += `<div class="sendtaxs-card">`;
        html += `<h3>Ajustes en scripts</h3>`;

        html += `<div class="sendtaxs-block">`;
        html += `<strong>Plaza_scheduler.sh</strong><br>`;
        html += `<span class="muted">/var/www/html/jostal/plaza_scheduler.sh</span><br><br>`;
        html += `Pico (15-19): min_s=${Math.round(sendtaxsRangosActual.plaza_pico_min * scales.plaza)}, max_s=${Math.round(sendtaxsRangosActual.plaza_pico_max * scales.plaza)}<br>`;
        html += `Resto: min_s=${Math.round(sendtaxsRangosActual.plaza_resto_min * scales.plaza)}, max_s=${Math.round(sendtaxsRangosActual.plaza_resto_max * scales.plaza)}`;
        html += `</div>`;

        html += `<div class="sendtaxs-block">`;
        html += `<strong>Publicidad_scheduler.sh (Lamami)</strong><br>`;
        html += `<span class="muted">/var/www/html/atupuerta/publicidad_scheduler.sh</span><br><br>`;
        html += `Pico (13-19): min_s=${Math.round(sendtaxsRangosActual.publi_pico_min * scales.publi_sched)}, max_s=${Math.round(sendtaxsRangosActual.publi_pico_max * scales.publi_sched)}<br>`;
        html += `Resto: min_s=${Math.round(sendtaxsRangosActual.publi_resto_min * scales.publi_sched)}, max_s=${Math.round(sendtaxsRangosActual.publi_resto_max * scales.publi_sched)}`;
        html += `</div>`;

        html += `<div class="sendtaxs-block">`;
        html += `<strong>enviar-publicistas.php (Publicista)</strong><br>`;
        html += `<span class="muted">/var/www/html/wasapbot/botPubli/enviar-publicistas.php</span><br>`;
        html += `<span class="muted">/var/www/html/wasapbot/botPubli/enviar-publicistas2.php</span><br><br>`;
        html += `MIN_WAIT_MINUTES=${Math.round(sendtaxsRangosActual.publipub_min * scales.publipub)}<br>`;
        html += `MAX_WAIT_MINUTES=${Math.round(sendtaxsRangosActual.publipub_max * scales.publipub)}`;
        html += `</div>`;

        html += `<div class="sendtaxs-block">`;
        html += `<strong>enviar.php (Casawasap)</strong><br>`;
        html += `<span class="muted">/var/www/html/wasapbot/botPubli/enviar.php</span><br>`;
        html += `<span class="muted">/var/www/html/wasapbot/botPubli/enviar2.php</span><br><br>`;
        html += `MIN_WAIT_MINUTES=${Math.round(sendtaxsRangosActual.pubcasas_min * scales.pubcasas)}<br>`;
        html += `MAX_WAIT_MINUTES=${Math.round(sendtaxsRangosActual.pubcasas_max * scales.pubcasas)}`;
        html += `</div>`;

        html += `</div>`;

        resultadosEl.innerHTML = html;

        fetch('index.php?page=josue&tab=sendtaxs', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: new URLSearchParams({
                action: 'save_sendtaxs_state',
                porc_plaza: porcPlazaEl.value,
                porc_publi_sched: porcPubliSchedEl.value,
                porc_publipub: porcPublipubEl.value,
                porc_pubcasas: porcPubcasasEl.value,
                total_deseado: totalEl.value
            })
        }).catch(function () {});

    };
})();
</script>
HTML;

if (!empty($sendtaxsState)) {
    echo '<script>window.sendtaxsCalcular && window.sendtaxsCalcular();</script>';
}

/*
    } elseif ($tab === 'avisos') {
        render_avisos_section('index.php?page=josue&tab=avisos');
*/
    } elseif ($tab === 'config') {
        render_config_section();

    } elseif ($tab === 'configm') {
        render_configm_section();

    } elseif ($tab === 'anuncios') {
        $editId = request_get('edit', '');
        $edit = $editId !== '' ? storage_find_by_id('anuncios.json', $editId) : null;

        $telefonosByAnuncio = array();
        foreach ($telefonos as $tel) {
            $aid = $tel['destacamos_id'] ?? '';
            if ($aid === '') continue;
            if (!isset($telefonosByAnuncio[$aid])) $telefonosByAnuncio[$aid] = array();
            $telefonosByAnuncio[$aid][] = $tel;
        }

        echo '<div class="cards two">';

        echo '<section class="panel">';
        echo '<div class="josue-head">';
        echo '<h2>' . ($edit ? 'Editar anuncio' : 'Nuevo anuncio') . '</h2>';
        echo '</div>';

        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="action" value="save_anuncio">';
        echo '<input type="hidden" name="id" value="' . e($edit['id'] ?? '') . '">';
        field_input('url', 'URL', $edit['url'] ?? '', true);
        field_input('user', 'User', $edit['user'] ?? '', true);
        field_input('pass', 'Pass', $edit['pass'] ?? '', true);
        field_textarea('descripcion', 'Descripción', $edit['descripcion'] ?? '', 4);
        echo '<div class="full"><button class="btn-primary">Guardar anuncio</button></div>';
        echo '</form>';

        if ($edit) {
            $linked = $telefonosByAnuncio[$edit['id']] ?? array();
            echo '<hr class="sep">';
            echo '<h2>Teléfonos vinculados</h2>';
            if (empty($linked)) {
                echo '<div class="empty">No hay teléfonos vinculados a este anuncio.</div>';
            } else {
                echo '<div class="table-wrap"><table><thead><tr><th>Nombre</th><th>Teléfono</th><th>WAHA Port</th><th>WAHA</th></tr></thead><tbody>';
                foreach ($linked as $tel) {
                    echo '<tr>';
                    echo '<td><a class="mini-link" href="index.php?page=josue&tab=telefonos&edit=' . e($tel['id']) . '">' . e($tel['nombre'] ?? '') . '</a></td>';
                    echo '<td>' . e($tel['tfono'] ?? '') . '</td>';
                    echo '<td>' . e($tel['waha_port'] ?? '') . '</td>';
                    echo '<td>' . e($tel['waha'] ?? '') . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
            }
        }
        echo '</section>';

        echo '<section class="panel">';
        echo '<h2>Listado anuncios</h2>';
        if (empty($anuncios)) {
            echo '<div class="empty">Todavía no hay anuncios registrados.</div>';
        } else {
            $anuncios = sort_desc_by_key($anuncios, 'created_at');
            render_live_filter('#anunciosRows tr[data-filter-text]', 'Buscar anuncio...');
            echo '<div class="table-wrap"><table><thead><tr>';
            echo '<th>URL</th><th>User</th><th>Pass</th><th>Descripción</th><th>Acciones</th>';
            echo '</tr></thead><tbody id="anunciosRows">';
            foreach ($anuncios as $row) {
                $searchText = strtolower(trim(
                    ($row['url'] ?? '') . ' ' .
                    ($row['user'] ?? '') . ' ' .
                    ($row['pass'] ?? '') . ' ' .
                    ($row['descripcion'] ?? '')
                ));

                echo '<tr data-filter-text="' . e($searchText) . '">';
                echo '<td><div class="copy-row copy-row-vertical"><span>' . e($row['url'] ?? '') . '</span><button type="button" class="btn-copy-mini" data-copy="' . e($row['url'] ?? '') . '">Copiar</button></div></td>';
                echo '<td><div class="copy-row copy-row-vertical"><span>' . e($row['user'] ?? '') . '</span><button type="button" class="btn-copy-mini" data-copy="' . e($row['user'] ?? '') . '">Copiar</button></div></td>';
                echo '<td><div class="copy-row copy-row-vertical"><span>' . e($row['pass'] ?? '') . '</span><button type="button" class="btn-copy-mini" data-copy="' . e($row['pass'] ?? '') . '">Copiar</button></div></td>';
                echo '<td>' . nl2br(e($row['descripcion'] ?? '')) . '</td>';
                echo '<td>';
                echo '<a class="mini-link" href="index.php?page=josue&tab=anuncios&edit=' . e($row['id']) . '">Editar</a> ';
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar este anuncio?\')">';
                echo '<input type="hidden" name="action" value="delete_anuncio">';
                echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                echo '<button class="btn-danger-mini">Eliminar</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';

                $linked = $telefonosByAnuncio[$row['id']] ?? array();
                echo '<tr class="subrow-phones" data-filter-text="' . e($searchText) . '">';
                echo '<td colspan="5">';
                echo '<div class="subrow-label">Teléfonos vinculados:</div>';
                if (empty($linked)) {
                    echo '<div class="muted">Ninguno</div>';
                } else {
                    echo '<div class="linked-tags">';
                    foreach ($linked as $tel) {
                        $label = trim(($tel['nombre'] ?? '') . ' · ' . ($tel['tfono'] ?? ''));
                        echo '<a class="linked-tag" href="index.php?page=josue&tab=telefonos&edit=' . e($tel['id']) . '">' . e($label) . '</a>';
                    }
                    echo '</div>';
                }
                echo '<hr class="sep">';
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

        echo '<div class="cards two">';

        echo '<section class="panel">';
        echo '<div class="josue-head">';
        echo '<h2>' . ($edit ? 'Ficha agenda' : 'Nuevo contacto agenda') . '</h2>';
        echo '</div>';

        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="action" value="save_agenda">';
        echo '<input type="hidden" name="id" value="' . e($edit['id'] ?? '') . '">';
        field_input('nombre', 'Nombre', $edit['nombre'] ?? '', true);
        field_input('telefono', 'Teléfono', $edit['telefono'] ?? '', true);
        field_textarea('observaciones', 'Observaciones', $edit['observaciones'] ?? '', 5);
        echo '<div class="full"><button class="btn-primary">Guardar contacto</button></div>';
        echo '</form>';
        echo '</section>';

        echo '<section class="panel">';
        echo '<h2>Listado agenda</h2>';

        if (empty($agenda)) {
            echo '<div class="empty">Todavía no hay contactos en agenda.</div>';
        } else {
            $agenda = sort_desc_by_key($agenda, 'created_at');

            render_live_filter('#agendaRows tr[data-filter-text]', 'Buscar contacto agenda...');

            echo '<div class="table-wrap"><table><thead><tr>';
            echo '<th>Nombre</th><th>Teléfono</th><th>Observaciones</th><th>Acciones</th>';
            echo '</tr></thead><tbody id="agendaRows">';

            foreach ($agenda as $row) {
                $searchText = strtolower(trim(
                    ($row['nombre'] ?? '') . ' ' .
                    ($row['telefono'] ?? '') . ' ' .
                    ($row['observaciones'] ?? '')
                ));

                echo '<tr data-filter-text="' . e($searchText) . '">';
                echo '<td>' . e($row['nombre'] ?? '') . '</td>';
                echo '<td>' . e($row['telefono'] ?? '') . '</td>';
                echo '<td>' . e($row['observaciones'] ?? '') . '</td>';
                echo '<td>';
                echo '<a class="mini-link" href="index.php?page=josue&tab=agenda&edit=' . e($row['id']) . '">Editar</a> ';
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar este contacto?\')">';
                echo '<input type="hidden" name="action" value="delete_agenda">';
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
                echo '<td>' . e($row['tfono'] ?? '') . '</td>';
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
    } elseif ($tab === 'autotube') {
        echo '<div class="josue-head"><h2>Autotube</h2></div>';
        echo '<div style="padding:0;overflow:visible;border-radius:8px;background:#0a0a0f">';
        echo '<iframe src="/autotube/" style="width:100%;min-height:calc(100vh - 280px);height:auto;border:none;display:block" title="Panel Autotube" loading="lazy" sandbox="allow-scripts allow-same-origin allow-forms allow-popups"></iframe>';
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
    echo '<div class="full"><button class="btn-primary">' . (($edit && in_array(($edit['estado'] ?? ''), array('cliente', 'baja'), true)) ? 'Guardar ficha base' : 'Guardar interesado') . '</button></div>';
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

            echo '<td><strong>' . e($row['telefono']) . '</strong><br><i class="muted">(' . e(format_created_at(isset($row['created_at']) ? $row['created_at'] : '')) . ')</i></td>';
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
                echo '<td><strong>' . e($row['telefono']) . '</strong><br><i class="muted">(' . e(format_created_at($row['created_at'] ?? '')) . ')</i></td>';
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

        if ($isNew) {
            field_input('first_arrival_date', 'Primera llegada a la casa', business_today_date(), true, 'date');
        }

        field_textarea('observaciones', 'Observaciones', $edit['observaciones'] ?? ($newFromInteresada['observaciones'] ?? ''), 4);
        echo '<div class="full"><button class="btn-primary">' . ($isNew ? 'Crear clienta' : 'Guardar clienta') . '</button></div>';
        echo '</form>';

        if ($edit) {
            echo '<hr class="sep">';
            echo '<div class="money-callout">';
            echo '<div class="money-title">Registrar lead Jostal</div>';
            echo '<form method="post" class="lead-quick-inline lead-quick-inline-jostal" onsubmit="return confirmLeadSubmit(this);">';
            echo '<input type="hidden" name="action" value="jostal_add_lead">';
            echo '<input type="hidden" name="clienta_id" value="' . e($edit['id']) . '">';
            echo '<input type="datetime-local" name="created_at" value="' . e(today_datetime_local()) . '" class="money-date">';
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

            if (empty($clientaLeads)) {
                echo '<div class="empty">Todavía no hay leads para esta clienta.</div>';
            } else {
                echo '<div class="table-wrap"><table><thead><tr>';
                echo '<th>Fecha</th><th>Precio</th><th>Observación</th>';
                echo '</tr></thead><tbody>';
                foreach ($clientaLeads as $lead) {
                    echo '<tr>';
                    echo '<td>' . e(format_created_at($lead['created_at'] ?? '')) . '</td>';
                    echo '<td><span class="money-chip">' . e(euro($lead['precio'] ?? 0)) . '</span></td>';
                    echo '<td>' . e($lead['observacion'] ?? '') . '</td>';
                    echo '</tr>';
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
                    echo '<td>' . e($row['telefono'] ?? '') . '</td>';
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
    echo '<form method="post" class="form-grid">';
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
    echo '<div class="field">';
    echo '<label>' . e($label) . '</label>';
    echo '<input type="' . e($type) . '" name="' . e($name) . '" value="' . e($value) . '"' . ($required ? ' required' : '') . '>';
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