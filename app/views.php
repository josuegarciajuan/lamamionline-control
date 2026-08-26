<?php

function render_global_ui($page = '', $lite = false) {
    echo '<div id="floatingToast" class="floating-toast"></div>';
    echo '<div id="moneyRain" class="money-rain"></div>';
    echo '<div id="appBackdrop" class="app-backdrop" hidden></div>';
    echo '<div id="voiceCommandBackdrop" class="voice-command-backdrop" hidden></div>';
    echo '<div id="voiceReminderBanner" class="voice-reminder-banner" hidden>';
    echo '<span id="voiceReminderText" class="voice-reminder-text">🔔 Recordatorio</span>';
    echo '<button type="button" id="voiceReminderPlay" class="voice-reminder-play" aria-label="Reproducir recordatorio">🔊</button>';
    echo '<button type="button" id="voiceReminderClose" class="voice-reminder-close" aria-label="Cerrar">✕</button>';
    echo '</div>';
    echo '<div id="voiceProcessingOverlay" class="voice-processing-overlay" hidden aria-hidden="true">';
    echo '<div class="voice-processing-card">';
    echo '<div class="voice-processing-brain">🧠</div>';
    echo '<div class="voice-processing-rings">';
    echo '<div class="voice-processing-ring ring-1"></div>';
    echo '<div class="voice-processing-ring ring-2"></div>';
    echo '<div class="voice-processing-ring ring-3"></div>';
    echo '</div>';
    echo '<div class="voice-processing-title">Procesando su orden</div>';
    echo '<div id="voiceProcessingText" class="voice-processing-text">El maestro está pensando…</div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="app-shell-tools">';
    echo '<button type="button" id="mobileMenuToggle" class="app-shell-btn app-shell-btn-mobile" aria-expanded="false" aria-controls="appSidebar">☰ Menú</button>';
    if (!$lite):
    $avisosCount = count(avisos_get_active());
    $avisosBtnClass = $avisosCount > 0 ? ' app-shell-btn-avisos-active' : '';
    echo '<button type="button" id="mobileAvisosToggle" class="app-shell-btn app-shell-btn-mobile' . $avisosBtnClass . '" aria-expanded="false" aria-controls="avisosPanel">⚠ Avisos' . ($avisosCount > 0 ? ' (' . $avisosCount . ')' : '') . '</button>';
    endif;
    echo '<button type="button" id="voiceCommandToggleMobile" class="app-shell-btn app-shell-btn-mobile app-shell-btn-mic" data-voice-command-toggle aria-expanded="false" aria-controls="voiceCommandPanel" aria-label="Abrir voz CRM" title="Abrir voz CRM">🎙</button>';
    echo '</div>';
?>

<?php if ($lite): ?>
    <?php
    // ── Lite Bottom Nav: 5 items optimizado para coche ──
    $currentTab = $_GET['tab'] ?? '';
    $tabs = [
        ['type' => 'voice', 'icon' => '💬', 'label' => 'Voz',   'id' => 'liteVoiceBtn', 'active' => false],
        ['type' => 'link',  'url'  => 'index.php?lite=1&page=josue&tab=reproductor', 'icon' => '▶', 'label' => 'Repro', 'active' => ($page === 'josue' && $currentTab === 'reproductor')],
        ['type' => 'link',  'url'  => 'index.php?lite=1&page=josue&tab=rutas',       'icon' => '📍', 'label' => 'Rutas', 'active' => ($page === 'josue' && $currentTab === 'rutas')],
        ['type' => 'link',  'url'  => 'index.php?lite=1&page=dashboard',              'icon' => '📊', 'label' => 'Dash',  'active' => ($page === 'dashboard')],
        ['type' => 'drop',  'id'   => 'liteMas', 'icon' => '➕', 'label' => 'Más',
            'active' => (!in_array($page, ['dashboard']) || ($page === 'josue' && $currentTab !== '' && $currentTab !== 'reproductor' && $currentTab !== 'rutas')),
            'links' => [
                ['page' => 'josue',       'label' => 'Josué'],
                ['page' => 'bot-casa',    'label' => 'Bot Casa'],
                ['page' => 'jostal',      'label' => 'Jostal'],
                ['page' => 'informes',    'label' => 'Informes'],
                ['page' => 'gastos',      'label' => 'Gastos'],
                ['page' => 'lamami',      'label' => 'LaMami'],
                ['page' => 'casawasap',   'label' => 'Casawasap'],
                ['page' => 'comercial',   'label' => 'Comercial'],
                ['page' => 'publicista',  'label' => 'Publicista'],
                ['page' => 'avisos',      'label' => 'Avisos'],
                ['page' => 'bots',        'label' => 'Bots'],
                ['page' => 'logout',      'label' => 'Salir'],
            ]],
    ];
    ?>
    <?php else: ?>
    <?php
    // ── Bottom Navigation Bar (mobile only) — 9 secciones ──
    $currentTab = $_GET['tab'] ?? '';
    $tabs = [
        ['type' => 'link',  'page' => 'dashboard', 'icon' => '📊', 'label' => 'Dash',   'active' => in_array($page, ['dashboard'])],
        ['type' => 'link',  'page' => 'jostal',    'icon' => '🏠', 'label' => 'Jost',   'active' => in_array($page, ['jostal'])],
        ['type' => 'drop',  'id'   => 'dropInf',   'icon' => '📈', 'label' => 'Inf',    'active' => in_array($page, ['informes','gastos']),
            'links' => [
                ['page' => 'informes', 'label' => 'Informes'],
                ['page' => 'gastos',   'label' => 'Gastos'],
            ]],
        ['type' => 'drop',  'id'   => 'dropCom',   'icon' => '💬', 'label' => 'Com',    'active' => in_array($page, ['comercial','publicista']),
            'links' => [
                ['page' => 'comercial',  'label' => 'Comercial'],
                ['page' => 'publicista', 'label' => 'Publicista'],
            ]],
        ['type' => 'link',  'page' => 'avisos',    'icon' => '🔔', 'label' => 'Recordatorio', 'active' => in_array($page, ['avisos'])],
        ['type' => 'link',  'url'  => 'index.php?page=josue&tab=eurekas', 'icon' => '💡', 'label' => 'Eureka', 'active' => ($page === 'josue' && $currentTab === 'eurekas')],
        ['type' => 'link',  'page' => 'josue',     'icon' => '🚀', 'label' => 'Josué', 'active' => ($page === 'josue' && $currentTab !== 'eurekas')],
    ];
    ?>
    <?php endif; ?>
    <nav class="mobile-bottom-nav" id="mobileBottomNav">
    <?php foreach ($tabs as $tab_item): ?>
        <?php $cls = ($tab_item['active'] ?? false) ? ' is-active' : ''; ?>
        <?php if (($tab_item['type'] ?? '') === 'voice'): ?>
            <button type="button" class="mobile-nav-item mobile-nav-voice<?= $cls ?>" id="<?= e($tab_item['id']) ?>" data-voice-lite-toggle aria-label="Activar voz" title="Pulsar para hablar">
                <span class="mobile-nav-icon"><?= $tab_item['icon'] ?></span>
                <span class="mobile-nav-label"><?= e($tab_item['label']) ?></span>
            </button>
        <?php elseif (($tab_item['type'] ?? '') === 'link'): ?>
            <?php $linkUrl = isset($tab_item['url']) ? $tab_item['url'] : ('index.php?page=' . e($tab_item['page'])); ?>
            <a href="<?= $linkUrl ?>" class="mobile-nav-item<?= $cls ?>">
                <span class="mobile-nav-icon"><?= $tab_item['icon'] ?></span>
                <span class="mobile-nav-label"><?= e($tab_item['label']) ?></span>
            </a>
        <?php else: ?>
            <?php $dropId = $tab_item['id']; ?>
            <button type="button" class="mobile-nav-item mobile-nav-drop<?= $cls ?>" id="<?= $dropId ?>" aria-expanded="false" aria-haspopup="true">
                <span class="mobile-nav-icon"><?= $tab_item['icon'] ?></span>
                <span class="mobile-nav-label"><?= e($tab_item['label']) ?></span>
            </button>
            <div class="mobile-nav-popover" id="<?= $dropId ?>Pop" hidden>
            <?php foreach ($tab_item['links'] as $link): ?>
                <a href="index.php?<?= $lite ? 'lite=1&amp;' : '' ?>page=<?= e($link['page']) ?>" class="mobile-nav-popover-link"><?= e($link['label']) ?></a>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
    </nav>

    <?php
    echo '<section id="voiceCommandPanel" class="voice-command-panel" hidden aria-hidden="true">';
    echo '<div class="voice-command-head">';
    echo '<div>';
    echo '<h2>Órdenes por voz</h2>';
    echo '</div>';
    echo '<button type="button" id="voiceCommandClose" class="voice-command-close" aria-label="Cerrar panel de voz">✕</button>';
    echo '</div>';

    echo '<div class="voice-command-body">';
    echo '<div id="voiceCommandSupport" class="voice-command-support">Comprobando reconocimiento de voz…</div>';
    echo '<div class="voice-command-actions">';
    echo '<button type="button" id="voiceStartButton" class="btn-primary voice-command-main-btn">🎙 Escuchar ahora</button>';
    echo '<button type="button" id="voiceModoEurekaBtn" class="voice-modo-eureka-btn" aria-label="Activar Modo Eureka" title="Activar Modo Eureka: todo lo que digas se guarda como eureka">💡 Modo Eureka</button>';
    echo '<button type="button" id="voiceTtsToggle" class="voice-tts-toggle active" aria-label="Activar/desactivar voz" title="Voz activada - clic para silenciar">🔊</button>';
    echo '<button type="button" id="voiceStopButton" class="voice-command-secondary-btn" disabled>■ Parar</button>';
    echo '<button type="button" id="voiceClearButton" class="voice-command-secondary-btn">Limpiar</button>';
    echo '</div>';

    echo '<div id="voiceModoEurekaStatus" class="voice-modo-eureka-status"></div>';

    echo '<div class="field full">';
    echo '<label for="voiceCommandInput">Texto de la orden</label>';
    echo '<textarea id="voiceCommandInput" class="voice-command-input" placeholder="Ejemplo: muéstrame estadísticas de esta clienta"></textarea>';
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

    // ── Jefry Whiteboard Overlay ──
    echo '<div id="jefryWhiteboardOverlay" class="jefry-whiteboard-overlay" hidden aria-hidden="true">';
    echo '<div class="jefry-whiteboard-card">';
    echo '<div class="jefry-whiteboard-toolbar" id="jefryWhiteboardToolbar">';
    echo '<span id="jefryWhiteboardTitle" class="jefry-whiteboard-title"></span>';
    echo '<button type="button" class="jefry-whiteboard-close" aria-label="Cerrar pizarra">✕</button>';
    echo '</div>';
    echo '<div id="jefryWhiteboardContent" class="jefry-whiteboard-content"></div>';
    echo '<div class="jefry-whiteboard-progress" id="jefryWhiteboardProgress" hidden>';
    echo '<div class="jefry-whiteboard-progress-bar" id="jefryWhiteboardProgressBar"></div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
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
    // En modo lite (coche) no mostrar avisos — distraen al conductor
    if (isset($_GET['lite']) && $_GET['lite'] === '1') {
        echo '<section id="avisosPanel" class="panel panel-space avisos-panel" hidden></section>';
        return;
    }

    $avisos = avisos_get_active();
    if (empty($avisos)) {
        // Always render panel so JS toggle works even with zero avisos
        echo '<section id="avisosPanel" class="panel panel-space avisos-panel" hidden></section>';
        return;
    }

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

    $totalActive = count($avisos);
    if ($totalActive > 0) {
        $isDismiss = ($newCount === 0);
        $btnLabel = $isDismiss
            ? 'Descartar ' . e((string)$totalActive) . ' avisos leídos'
            : 'Marcar ' . e((string)$newCount) . ' nuevos como leídos';
        $btnScope = $isDismiss ? 'active_all' : 'active_unread';

        echo '<div class="aviso-actions" style="margin-top:10px;">';
        echo '<form method="post" class="inline-form js-mark-all-read">';
        echo '<input type="hidden" name="action" value="mark_avisos_read">';
        echo '<input type="hidden" name="scope" value="' . e($btnScope) . '">';
        echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
        echo '<input type="hidden" name="redirect" value="' . e($_SERVER['REQUEST_URI'] ?? 'index.php?page=dashboard') . '">';
        echo '<button class="btn-secondary-mini">' . $btnLabel . '</button>';
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
    $allowed = array('crear_perfiles', 'estrategias', 'cuentas', 'campanas', 'subir_anuncios', 'run_log', 'estados_wasap', 'afiliados');

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
    echo '<a class="subtab ' . ($tab === 'afiliados' ? 'active' : '') . '" href="' . e(publicista_page_url('afiliados')) . '">🤝 Afiliados</a>';
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

    if ($tab === 'afiliados') {
        render_publicista_afiliados_page();
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

function render_publicista_afiliados_page() {
    $config = publicista_afiliados_get_config();
    $log = publicista_afiliados_get_log();
    $allLines = publicista_afiliados_get_lines();
    $enabledIds = $config['lineas'];
    $enabledLines = array();
    foreach ($allLines as $l) {
        if (in_array($l['id'], $enabledIds, true)) $enabledLines[] = $l;
    }
    $dc = $config['destacamos'];

    // ── Status cards ───────────────────────────────────────────────
    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>🤝 Afiliados WhatsApp</h2><span class="summary-badge">' . ($config['enabled'] ? 'Activo' : 'Pausado') . '</span></div>';
    echo '<div class="cards four" style="margin-top:14px;">';
    echo '<div class="info-strip"><strong>Estado</strong><br>' . ($config['enabled'] ? '✅ Publicación automática activa' : '⏸️ Pausado (por defecto)') . '</div>';
    echo '<div class="info-strip"><strong>Líneas estados</strong><br>' . count($enabledLines) . ' de ' . count($allLines) . ' (bot-casa + bot-comercial)</div>';
    $freqLabel = publicista_afiliados_frecuencia_options()[$config['frecuencia_tipo']] ?? $config['frecuencia_tipo'];
    $freqDetail = $config['frecuencia_tipo'] === 'cada_x_horas' ? "Cada {$config['frecuencia_valor']}h" : "{$config['frecuencia_valor']} veces/día";
    echo '<div class="info-strip"><strong>Frecuencia</strong><br>' . e($freqLabel . ' — ' . $freqDetail) . ' (' . e($config['hora_inicio']) . '–' . e($config['hora_fin']) . ')</div>';
    echo '<div class="info-strip"><strong>Broadcast</strong><br>' . ($config['broadcast_enabled'] ? '✅ Activo (' . count(publicista_afiliados_get_destinos()) . ' destinos)' : '⏸️ Desactivado') . '</div>';
    echo '</div>';
    echo '<div class="cards four" style="margin-top:12px;">';
    echo '<div class="info-strip"><strong>API afiliados</strong><br>' . e($config['api_base_url'] !== '' ? $config['api_base_url'] : '— no configurada —') . '</div>';
    echo '<div class="info-strip"><strong>Panel admin</strong><br>' . e($config['admin_url'] !== '' ? $config['admin_url'] : '— no configurado —') . '</div>';
    echo '<div class="info-strip"><strong>Destacamos producto</strong><br>' . ($dc['enabled'] ? '✅ Activo (cada ' . (int)$dc['interval_days'] . 'd a las ' . e($dc['hora']) . ')' : '⏸️ Desactivado') . '</div>';
    echo '<div class="info-strip"><strong>Último ciclo</strong><br>' . e(!empty($log) ? trim((string)(end($log)['published_at'] ?? '—')) : '—') . '</div>';
    echo '</div>';
    echo '</section>';

    // ── Config form ────────────────────────────────────────────────
    echo '<section class="panel panel-space">';
    echo '<div class="section-head"><div><h2>Configuración</h2><p>Promoción de productos afiliados: estados en líneas bot-casa/bot-comercial, broadcasts a destinos del sector y anuncios Destacamos de producto.</p></div></div>';
    echo '<form method="post" class="form-grid">';
    echo '<input type="hidden" name="action" value="save_afiliados_config">';

    echo '<div class="field">';
    echo '<label>Activado (rama WhatsApp)</label>';
    echo '<label style="display:flex;gap:8px;align-items:center;margin-top:10px;">';
    echo '<input type="checkbox" name="enabled" value="1"' . ($config['enabled'] ? ' checked' : '') . '>';
    echo 'Publicar estados/broadcasts de afiliados automáticamente';
    echo '</label>';
    echo '<div class="field-help">Empieza desactivado. Actívalo cuando la API de afiliados responda.</div>';
    echo '</div>';

    echo '<div class="field">';
    echo '<label>URL API afiliados</label>';
    echo '<input type="text" name="api_base_url" value="' . e($config['api_base_url']) . '" placeholder="https://josue.ink/afiliados" style="margin-top:8px;width:100%;">';
    echo '<div class="field-help">Base del repo de afiliados. Consume /api/productos.json y /api/oferta-del-dia.json.</div>';
    echo '</div>';

    echo '<div class="field">';
    echo '<label>URL panel admin (iframe)</label>';
    echo '<input type="text" name="admin_url" value="' . e($config['admin_url']) . '" placeholder="https://josue.ink/afiliados" style="margin-top:8px;width:100%;">';
    echo '<div class="field-help">Se le añade /admin automáticamente si no lo lleva.</div>';
    echo '</div>';

    echo '<div class="field">';
    echo '<label>Frecuencia</label>';
    echo '<select name="frecuencia_tipo" style="margin-top:8px;">';
    foreach (publicista_afiliados_frecuencia_options() as $val => $label) {
        $sel = $config['frecuencia_tipo'] === $val ? ' selected' : '';
        echo '<option value="' . e($val) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div class="field">';
    echo '<label>Valor</label>';
    echo '<input type="number" name="frecuencia_valor" value="' . e((string)$config['frecuencia_valor']) . '" min="1" max="24" style="margin-top:8px;width:100%;">';
    echo '<div class="field-help">Horas entre publicaciones (si "Cada X horas") o veces al día.</div>';
    echo '</div>';

    echo '<div class="field"><label>Hora inicio</label><input type="time" name="hora_inicio" value="' . e($config['hora_inicio']) . '" style="margin-top:8px;width:100%;"></div>';
    echo '<div class="field"><label>Hora fin</label><input type="time" name="hora_fin" value="' . e($config['hora_fin']) . '" style="margin-top:8px;width:100%;"></div>';

    echo '<div class="field full">';
    echo '<label>Formato de estado</label>';
    echo '<select name="formato" style="margin-top:8px;width:100%;max-width:420px;">';
    foreach (publicista_afiliados_format_options() as $val => $label) {
        $sel = $config['formato'] === $val ? ' selected' : '';
        echo '<option value="' . e($val) . '"' . $sel . '>' . e($label) . '</option>';
    }
    echo '</select>';
    echo '<div class="field-help">El estado se compone con el producto + enlace afiliado (con UTM).</div>';
    echo '</div>';

    // Líneas estados
    echo '<div class="field full">';
    echo '<label>Líneas para estados (bot-casa + bot-comercial)</label>';
    if (empty($allLines)) {
        echo '<div class="empty" style="margin-top:8px;">No hay líneas con uso "bot casa" / "envio publi" y WAHA configurado. Añádelas o ajusta "Usos permitidos" en Josué → Teléfonos.</div>';
    } else {
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin-top:8px;">';
        foreach ($allLines as $line) {
            $checked = in_array($line['id'], $enabledIds, true) ? ' checked' : '';
            echo '<label class="info-strip" style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:8px 12px;">';
            echo '<input type="checkbox" name="lineas[]" value="' . e($line['id']) . '"' . $checked . '>';
            echo '<span><strong>' . e($line['nombre']) . '</strong><br><span class="muted">' . e($line['tfono']) . ' · puerto ' . e($line['waha_port']) . ' · ' . e($line['uso']) . '</span></span>';
            echo '</label>';
        }
        echo '</div>';
    }
    echo '</div>';

    // Usos permitidos
    echo '<div class="field full">';
    echo '<label>Usos de línea permitidos (estados)</label>';
    echo '<input type="text" name="usos" value="' . e(implode(', ', $config['usos'])) . '" placeholder="bot casa, envio publi" style="margin-top:8px;width:100%;max-width:420px;">';
    echo '<div class="field-help">Separados por comas. Valores de "uso" en telefonos.json que publicarán estados.</div>';
    echo '</div>';

    // Broadcast
    echo '<div class="field full">';
    echo '<label style="display:flex;gap:8px;align-items:center;">';
    echo '<input type="checkbox" name="broadcast_enabled" value="1"' . ($config['broadcast_enabled'] ? ' checked' : '') . '>';
    echo 'Enviar broadcast a destinos del sector';
    echo '</label>';
    echo '</div>';

    echo '<div class="field full">';
    echo '<label>Destinos broadcast (un teléfono por línea)</label>';
    echo '<textarea name="destinos" rows="4" style="margin-top:8px;width:100%;max-width:420px;" placeholder="654464023&#10;641993776">' . e(implode("\n", $config['destinos'])) . '</textarea>';
    echo '</div>';

    echo '<div class="field full">';
    echo '<label style="display:flex;gap:8px;align-items:center;">';
    echo '<input type="checkbox" name="usar_contactos_casawasap" value="1"' . ($config['usar_contactos_casawasap'] ? ' checked' : '') . '>';
    echo 'Añadir también los contactos de Casawasap (casawasap_contactos.json)';
    echo '</label>';
    echo '</div>';

    echo '<div class="field">';
    echo '<label>Campaña UTM</label>';
    echo '<input type="text" name="utm_campaign" value="' . e($config['utm_campaign']) . '" placeholder="crm" style="margin-top:8px;width:100%;">';
    echo '<div class="field-help">Se añade a los enlaces afiliados: ?utm_source=whatsapp&amp;utm_medium=social&amp;utm_campaign=...</div>';
    echo '</div>';

    // ── Destacamos producto ────────────────────────────────────────
    echo '<div class="full" style="border-top:1px solid rgba(245,158,11,.18);margin-top:18px;padding-top:14px;">';
    echo '<div class="section-head"><div><h2>Anuncios Destacamos de producto</h2><p>Genera copy (DeepSeek) + imagen (OpenAI) de un producto afiliado y lo sube reutilizando el sistema de subida existente.</p></div></div>';
    echo '</div>';

    echo '<div class="field">';
    echo '<label style="display:flex;gap:8px;align-items:center;">';
    echo '<input type="checkbox" name="destacamos_enabled" value="1"' . ($dc['enabled'] ? ' checked' : '') . '>';
    echo 'Publicar anuncio de producto en Destacamos';
    echo '</label>';
    echo '</div>';

    $accounts = function_exists('publicista_accounts_get') ? publicista_accounts_get(true) : array();
    echo '<div class="field">';
    echo '<label>Cuenta Destacamos</label>';
    echo '<select name="destacamos_account_id" style="margin-top:8px;width:100%;max-width:420px;">';
    echo '<option value="">Auto (primera cuenta con listings)</option>';
    foreach ($accounts as $acc) {
        $accId = trim((string)($acc['id'] ?? ''));
        $accLabel = trim((string)($acc['display_name'] ?? ($acc['login_user'] ?? $accId)));
        $sel = $dc['account_id'] === $accId ? ' selected' : '';
        echo '<option value="' . e($accId) . '"' . $sel . '>' . e($accLabel) . '</option>';
    }
    echo '</select>';
    echo '<div class="field-help">La cuenta debe tener credenciales y listings. El anuncio edita un listing existente (patrón free-bump).</div>';
    echo '</div>';

    echo '<div class="field">';
    echo '<label>Intervalo (días)</label>';
    echo '<input type="number" name="destacamos_interval_days" value="' . e((string)$dc['interval_days']) . '" min="1" max="30" style="margin-top:8px;width:100%;">';
    echo '</div>';

    echo '<div class="field">';
    echo '<label>Hora de publicación</label>';
    echo '<input type="time" name="destacamos_hora" value="' . e($dc['hora']) . '" style="margin-top:8px;width:100%;">';
    echo '</div>';

    echo '<div class="field">';
    echo '<label style="display:flex;gap:8px;align-items:center;">';
    echo '<input type="checkbox" name="destacamos_include_link" value="1"' . ($dc['include_link'] ? ' checked' : '') . '>';
    echo 'Incluir enlace afiliado en la descripción';
    echo '</label>';
    echo '</div>';

    echo '<div class="full" style="display:flex;gap:10px;flex-wrap:wrap;">';
    echo '<button class="btn-primary">💾 Guardar configuración</button>';
    echo '</div>';
    echo '</form>';
    echo '</section>';

    // ── Publicación manual ─────────────────────────────────────────
    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>Publicar ahora</h2><span class="summary-badge">Manual</span></div>';
    echo '<div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px;">';
    echo '<form method="post" class="inline-form"><input type="hidden" name="action" value="publicar_afiliado_ahora"><button class="btn-secondary-mini">📱 Publicar estados/broadcast ahora</button></form>';
    echo '<form method="post" class="inline-form"><input type="hidden" name="action" value="publicar_afiliado_destacamos"><button class="btn-secondary-mini">🪧 Publicar anuncio Destacamos de producto</button></form>';
    echo '</div>';
    echo '<div class="field-help" style="margin-top:8px;">La publicación manual respeta el flag "Activado". Si la API de afiliados no responde todavía, se reporta el motivo.</div>';
    echo '</section>';

    // ── Log ────────────────────────────────────────────────────────
    echo '<section class="panel panel-space">';
    echo '<div class="branch-panel-head"><h2>Log de afiliados</h2><span class="summary-badge">' . count($log) . ' entradas</span></div>';
    if (empty($log)) {
        echo '<div class="empty" style="margin-top:12px;">Sin actividad todavía.</div>';
    } else {
        $logReversed = array_reverse($log);
        $logReversed = array_slice($logReversed, 0, 20);
        echo '<div class="table-wrap" style="margin-top:12px;"><table class="table">';
        echo '<thead><tr><th>Fecha</th><th>Tipo</th><th>Resultado</th><th>Detalle</th></tr></thead><tbody>';
        foreach ($logReversed as $entry) {
            $tipo = trim((string)($entry['tipo'] ?? 'ciclo'));
            $resultado = trim((string)($entry['resultado'] ?? ''));
            $ok = ($resultado === 'ok');
            $badge = $ok
                ? '<span class="summary-badge" style="background:rgba(34,197,94,.15);color:#4ade80;">OK</span>'
                : '<span class="summary-badge" style="background:rgba(239,68,68,.15);color:#f87171;">' . e($resultado !== '' ? $resultado : 'ERROR') . '</span>';
            $date = !empty($entry['published_at']) ? date('d/m H:i', strtotime($entry['published_at'])) : '—';
            $detalle = '';
            if ($tipo === 'destacamos') {
                $detalle = trim((string)($entry['producto'] ?? '')) . ' · listing ' . trim((string)($entry['listing_id'] ?? ''));
            } elseif ($tipo === 'ciclo') {
                $detalle = (int)($entry['estados_count'] ?? 0) . ' estados · ' . (int)($entry['broadcasts_count'] ?? 0) . ' broadcasts';
            }
            if (trim((string)($entry['error'] ?? '')) !== '') {
                $detalle .= '<br><span class="muted" style="color:var(--danger,#c0392b);">' . e($entry['error']) . '</span>';
            }
            echo '<tr>';
            echo '<td style="white-space:nowrap;">' . e($date) . '</td>';
            echo '<td>' . e($tipo) . '</td>';
            echo '<td>' . $badge . '</td>';
            echo '<td style="max-width:360px;">' . ($detalle !== '' ? $detalle : '—') . '</td>';
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
    echo '<div class="table-wrap" style="margin-top:12px;"><table data-no-card-view><tbody>';
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
                echo '<a class="linked-tag" href="' . e(comercial_page_url('lineas', array('edit' => $tel['id']))) . '">' . e($label) . '</a>';
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
                echo '<div class="table-wrap" style="margin-top:12px;"><table data-no-card-view><thead><tr><th>Cuenta</th><th>Anuncios</th><th>Coste subtotal</th></tr></thead><tbody>';
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
                echo '<div class="table-wrap" style="margin-top:12px;"><table data-no-card-view><thead><tr><th>Producto</th><th>Anuncios</th><th>Coste subtotal</th></tr></thead><tbody>';
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
                        }
                    }
                }
                if ($hasDistributionMismatch) {
                    echo '<div class="publicista-ads-warning" style="margin-bottom:8px;"><strong>⚠️ El reparto actual de los anuncios no coincide con la matriz guardada.</strong> Pulsa <em>Aplicar reparto</em> para actualizar los anuncios antes de subir.</div>';
                }

                echo '<p class="muted" style="margin-top:0;">Define cuántos anuncios tendrá cada producto en cada cuenta y aplica. El total por producto debe mantenerse.</p>';
                echo '<div class="table-wrap"><table data-no-card-view><thead><tr><th>Producto</th>';
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

            // ─── Botones globales de resubida por plataforma ───
            // GirlsConf siempre visible
            echo '<form method="post" class="inline-form"><input type="hidden" name="action" value="sync_publicista_campaign_to_girlsconf"><input type="hidden" name="id" value="' . e($edit['id']) . '"><button class="btn-secondary" onclick="return confirm(\'¿Sincronizar todos los productos de esta campaña a GirlsConf? Se desactivarán todos los perfiles activos actuales y se crearán los de esta campaña.\')" title="Desactiva todos los perfiles activos y crea uno por producto de esta campaña">📤 GirlsConf</button></form>';

            // Detectar si hay items de cada plataforma para mostrar los botones
            $hasDestacamos = false;
            $hasMundosex = false;
            foreach ($items as $it) {
                $pc = trim((string)($it['portal_code'] ?? 'destacamos'));
                if ($pc === 'destacamos') $hasDestacamos = true;
                if ($pc === 'mundosex') $hasMundosex = true;
            }
            if ($hasDestacamos) {
                echo '<form method="post" class="inline-form"><input type="hidden" name="action" value="resubmit_publicista_campaign_portal"><input type="hidden" name="id" value="' . e($edit['id']) . '"><input type="hidden" name="portal_code" value="destacamos"><button class="btn-secondary" onclick="return confirm(\'¿Resubir solo los anuncios de Destacamos?\')" title="Re-ejecuta la subida solo para los items con portal Destacamos">🔄 Solo Destacamos</button></form>';
            }
            if ($hasMundosex) {
                echo '<form method="post" class="inline-form"><input type="hidden" name="action" value="resubmit_publicista_campaign_portal"><input type="hidden" name="id" value="' . e($edit['id']) . '"><input type="hidden" name="portal_code" value="mundosex"><button class="btn-secondary" onclick="return confirm(\'¿Resubir solo los anuncios de Mundosex?\')" title="Re-ejecuta la subida solo para los items con portal Mundosex">🔄 Solo Mundosex</button></form>';
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
                echo '<td>';
                echo '<form method="post" class="inline-form" style="margin-bottom:6px;" onsubmit="return confirm(\'¿Subir este anuncio ahora? Se conectará a ' . e($item['portal_code'] ?? 'la plataforma') . ' para publicarlo.\')">';
                echo '<input type="hidden" name="action" value="upload_single_campaign_item">';
                echo '<input type="hidden" name="item_id" value="' . e($item['id']) . '">';
                echo '<input type="hidden" name="campaign_id" value="' . e($edit['id']) . '">';
                echo '<button class="btn-primary-mini">▲ Subir</button>';
                echo '</form>';
                echo '<span class="muted" style="font-size:0.82em;">Aquí puedes corregir listing ID y teléfono antes de subir. El botón "Subir anuncios" siempre vuelve a ejecutar la campaña con esta configuración actual.</span>';
                echo '</td>';
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
        echo '<div class="section-head"><div><h2>Nuevo producto publicitario</h2><p>Flujo rápido: sube foto, genera pack del producto, revisa y acepta.</p></div>';
        echo '<div class="section-head-actions">';
        echo '<button type="button" class="btn-secondary-mini" onclick="publicistaJobsOpen()">Productos creados (' . e(count($jobs)) . ')</button>';
        echo '</div></div>';
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

            echo '<div class="field full">';
            echo '<label>Fotos reales del perfil (opcional) <span style="color:#6b7280;font-weight:normal;">— máx 10</span></label>';
            echo '<input type="file" name="real_photos[]" accept="image/jpeg,image/png,image/webp" multiple>';
            echo '<div class="field-help">Estas fotos se guardan sin modificar. Podrás editarlas y aplicarles blur más tarde desde la ficha del producto.</div>';
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
            if (function_exists('publicista_pollo_models')) {
                foreach (publicista_pollo_models() as $polloKey => $polloCfg) {
                    $sel = ($polloKey === 'pollo-image-v2') ? ' selected' : '';
                    echo '<option value="' . e($polloKey) . '"' . $sel . '>' . e($polloCfg['name']) . '</option>';
                }
            }
            echo '</select>';
            echo '<div class="field-help">Modelos Pollo.ai para generación de imágenes vía texto. Usa la cookie de sesión guardada en ConfigM.</div>';
            echo '</div>';

            // ---- Info cuentas Pollo.ai (multi-cuenta) ----
            $polloAccounts = function_exists('publicista_pollo_accounts') ? publicista_pollo_accounts() : array();
            $polloStatus = function_exists('publicista_pollo_status_read') ? publicista_pollo_status_read() : array();
            $activeCount = 0;
            $exhaustedCount = 0;
            foreach ($polloAccounts as $acc) {
                $label = trim((string)($acc['label'] ?? ''));
                if ($label === '') continue;
                $cookie = trim((string)($acc['cookie'] ?? ''));
                if ($cookie === '') continue;
                if (!empty($polloStatus[$label]['credits_exhausted'])) {
                    $exhaustedCount++;
                } else {
                    $activeCount++;
                }
            }

            echo '<div class="field full" id="pollo_cookie_info_panel" style="display:none;">';
            echo '<div class="info-strip" style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;padding:10px 14px;">';
            echo '<span style="font-size:13px;"><strong>Pollo.ai:</strong> ';
            if ($activeCount > 0) {
                echo '<span style="color:#059669;font-weight:600;">' . $activeCount . ' cuenta(s) con créditos</span>';
            } else {
                echo '<span style="color:#dc2626;font-weight:600;">SIN CRÉDITOS</span>';
            }
            if ($exhaustedCount > 0) {
                echo ' · <span style="color:#d97706;">' . $exhaustedCount . ' agotada(s)</span>';
            }
            echo ' · <span style="color:#6b7280;">elige automáticamente la disponible</span>';
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

        echo '<div id="publicistaJobsModalOverlay" class="modal-overlay" style="display:none;" onclick="if(event.target===this)publicistaJobsClose()">';
        echo '<div class="modal-container" style="max-width:1100px;">';
        echo '<div class="modal-header">';
        echo '<h2>Productos creados</h2>';
        echo '<button type="button" class="modal-close" onclick="publicistaJobsClose()">&times;</button>';
        echo '</div>';
        echo '<div class="modal-body">';
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
                echo '<td>' . e((string)$finalCount) . '/6</td>';
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
        echo '</div>';
        echo '<div class="modal-footer">';
        echo '<button type="button" class="btn-secondary" onclick="publicistaJobsClose()">Cerrar</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '<script>';
        echo 'function publicistaJobsOpen(){var o=document.getElementById("publicistaJobsModalOverlay");if(o){o.style.display="flex";document.body.classList.add("modal-open");}}';
        echo 'function publicistaJobsClose(){var o=document.getElementById("publicistaJobsModalOverlay");if(o){o.style.display="none";document.body.classList.remove("modal-open");}}';
        echo '</script>';
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
    $sexyCandidates = is_array($selectedJob['sexy_candidates'] ?? null) ? $selectedJob['sexy_candidates'] : array();
    $sexyFinalImages = is_array($selectedJob['sexy_final_images'] ?? null) ? $selectedJob['sexy_final_images'] : array();
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
    $pipelineButtonLabel = $hasPendingBatch ? 'Relanzar generación' : 'Generar / regenerar candidatas';
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
    echo '<div class="info-strip"><strong>Finales</strong><br>' . e((string)count($finalImages)) . '</div>';
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
    echo '<a class="publicista-visual-step" href="#publicistaReals"><span class="step-num">4</span><span><strong>Fotos reales</strong><small>subidas manualmente</small></span></a>';
    echo '<a class="publicista-visual-step" href="#publicistaPlatformPhotos"><span class="step-num">5</span><span><strong>Por plataforma</strong><small>destacamos/mundosex/girlsconf</small></span></a>';
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
    echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
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
    echo '<div class="field full"><hr style="margin:4px 0;border:none;border-top:1px solid #e5e7eb;"><strong style="font-size:13px;color:#6b7280;">Fotos reales del perfil</strong></div>';
    echo '<div class="field full">';
    echo '<label>Subir fotos reales adicionales (máx 10 en total)</label>';
    echo '<input type="file" name="real_photos[]" accept="image/jpeg,image/png,image/webp" multiple>';
    echo '<div class="field-help">Las fotos se añadirán a las ya existentes sin borrar las anteriores.</div>';
    echo '</div>';
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
        $sourceFs = BASE_PATH . '/' . ltrim((string)$source['stored_path'], '/');
        $sourceMtime = file_exists($sourceFs) ? filemtime($sourceFs) : 0;
        $sourceSrcBust = $sourceMtime > 0 ? $source['stored_path'] . '?t=' . $sourceMtime : $source['stored_path'];
        $sourceBlurApplied = !empty($source['manual_blur_applied']);
        $sourceBlurIntensity = (int)($source['manual_blur_intensity'] ?? 8);
        echo '<div class="publicista-preview-card" style="margin-bottom:16px;">';
        echo '<div class="muted" style="margin-bottom:8px;">Foto original subida</div>';
        echo '<img id="originalBlurImg_' . e($selectedJob['id'] ?? '') . '" src="' . e($sourceSrcBust) . '" alt="Foto original subida" style="width:100%;max-width:340px;border-radius:12px;border:1px solid #e5e7eb;display:block;">';
        echo '<div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">';
        if ($sourceBlurApplied) {
            echo '<span id="originalBlurStatus_' . e($selectedJob['id'] ?? '') . '" class="summary-badge" style="background:#ede9fe;color:#6d28d9;">Blur manual · ' . e((string)$sourceBlurIntensity) . '/20</span>';
        } else {
            echo '<span id="originalBlurStatus_' . e($selectedJob['id'] ?? '') . '" class="summary-badge" style="background:#f3f4f6;color:#6b7280;">Sin blur manual</span>';
        }
        echo '<button type="button" class="btn-primary js-manual-blur-btn"'
            . ' data-job-id="' . e($selectedJob['id'] ?? '') . '"'
            . ' data-square-src="' . e($source['stored_path']) . '"'
            . ' data-intensity="' . e((string)$sourceBlurIntensity) . '"'
            . ' data-target="source"'
            . ' title="Aplicar blur manual a la foto original">✏ Blur manual</button>';
        echo '</div>';
        echo '<div class="muted small" style="margin-top:8px;word-break:break-all;">' . e($source['stored_path']) . '</div>';
        echo '</div>';
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
    // Elemento oculto para que el polling JS sepa el job actual
    echo '<span id="publicistaRegenPollJobId" data-job-id="' . e($selectedJob['id'] ?? '') . '" style="display:none;"></span>';
    echo '<div class="branch-panel-head"><h3>② Candidatas generadas</h3><span class="summary-badge">' . e((string)count($candidates)) . '</span></div>';
    if (!empty($candidates)) {
        echo '<div class="cards two" style="margin-top:14px;">';
        if (!empty($strategy['window']) && is_array($strategy['window'])) {
            echo '<div class="info-strip" style="margin-bottom:14px;"><strong>Franja de autosubidas</strong><br>' . e((string)$strategy['window']['start']) . ' → ' . e((string)$strategy['window']['end']) . '</div>';
        }
        foreach ($candidates as $cand) {
            $isSelected = !empty($cand['selected']);
            $cardBorder = $isSelected ? 'border:2px solid #6366f1;' : '';
            echo '<div class="panel" style="padding:12px;' . $cardBorder . '" data-candidate-id="' . e($cand['id'] ?? '') . '">';
            echo '<div class="branch-panel-head"><h4 style="margin:0;">' . e($cand['id'] ?? 'candidate') . ($isSelected ? ' <span style="color:#6366f1;font-size:11px;">★ TOP 6</span>' : '') . '</h4><span class="summary-badge">' . e((string)($cand['effective_score'] ?? 0)) . '</span></div>';
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
                $queueData = function_exists('publicista_regen_queue_get') ? publicista_regen_queue_get($selectedJob['id'] ?? '') : array();
                $queueStatus = $queueData[$cand['id'] ?? '']['status'] ?? '';
                $btnLabel = ($queueStatus === 'queued') ? '⏳ En cola…' : (($queueStatus === 'running') ? '⚙ Generando…' : (($queueStatus === 'waiting_pollo') ? '⏳ Esperando turno Pollo…' : 'Regenerar esta'));
                $btnDisabled = in_array($queueStatus, array('queued', 'running', 'waiting_pollo'), true) ? ' disabled' : '';
                echo '<button type="button" class="btn-secondary-mini js-open-regenerate-candidate-modal" data-job-id="' . e($selectedJob['id'] ?? '') . '" data-candidate-id="' . e($cand['id'] ?? '') . '"' . $btnDisabled . '>' . $btnLabel . '</button>';
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

    // ───────────────────────────────────────────────────────────────────────
    // SECCIÓN 2b: CANDIDATAS EROTICAS
    // ───────────────────────────────────────────────────────────────────────
    echo '<section class="panel panel-space" id="publicistaSexyCandidates">';
    echo '<div class="branch-panel-head"><h3>②b Candidatas eróticas <span style="font-size:11px;background:#fce7f3;color:#be185d;padding:2px 8px;border-radius:4px;margin-left:8px;">+18</span></h3><span class="summary-badge">' . e((string)count($sexyCandidates)) . '</span></div>';
    if (!empty($sexyCandidates)) {
        echo '<div class="cards two" style="margin-top:14px;">';
        foreach ($sexyCandidates as $cand) {
            $isSelected = !empty($cand['selected']);
            $cardBorder = $isSelected ? 'border:2px solid #ec4899;' : '';
            echo '<div class="panel" style="padding:12px;' . $cardBorder . '" data-candidate-id="' . e($cand['id'] ?? '') . '" data-candidate-type="sexy">';
            echo '<div class="branch-panel-head"><h4 style="margin:0;">' . e($cand['id'] ?? 'sexy') . ($isSelected ? ' <span style="color:#ec4899;font-size:11px;">★ TOP 4 ERÓTICO</span>' : '') . '</h4><span class="summary-badge">' . e((string)($cand['effective_score'] ?? 0)) . '</span></div>';
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
                $queueDataSexy = function_exists('publicista_regen_queue_get') ? publicista_regen_queue_get($selectedJob['id'] ?? '') : array();
                $sexyQueueKey = 'sexy_' . ($cand['id'] ?? '');
                $sexyQueueStatus = $queueDataSexy[$sexyQueueKey]['status'] ?? '';
                $sexyBtnLabel = ($sexyQueueStatus === 'queued') ? '⏳ En cola…' : (($sexyQueueStatus === 'running') ? '⚙ Generando…' : (($sexyQueueStatus === 'waiting_pollo') ? '⏳ Esperando turno Pollo…' : 'Regenerar esta'));
                $sexyBtnDisabled = in_array($sexyQueueStatus, array('queued', 'running', 'waiting_pollo'), true) ? ' disabled' : '';
                echo '<button type="button" class="btn-secondary-mini js-open-regenerate-sexy-candidate-modal" data-job-id="' . e($selectedJob['id'] ?? '') . '" data-candidate-id="' . e($cand['id'] ?? '') . '" style="background:#fce7f3;border-color:#f9a8d4;color:#9d174d;"' . $sexyBtnDisabled . '>' . $sexyBtnLabel . '</button>';
            }
            echo '</div>';
            echo '<details style="margin-top:10px;"><summary style="cursor:pointer;font-size:12px;color:#9ca3af;">Ver prompt erótico</summary><pre style="white-space:pre-wrap;word-break:break-word;font-size:11px;">' . e($cand['prompt'] ?? '') . '</pre></details>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="empty">' . ($isPipelineRunning ? 'La generación está en curso. Esta ficha se actualizará sola cuando entren las candidatas eróticas.' : 'Aún no hay candidatas eróticas generadas. Activa el checkbox "Generar variante erótica" en el formulario o pulsa el botón de generar arriba.') . '</div>';
    }
    echo '</section>';

    // -----------------------------------------------------------------------
    // SECCIÓN 3: FINALES DEL PACK — sin blur + con blur manual
    // -----------------------------------------------------------------------
    echo '<section class="panel panel-space" id="publicistaFinals">';
    echo '<div class="branch-panel-head"><h3>③ Definitivas del pack</h3><span class="summary-badge">' . e((string)count($finalImages)) . '</span></div>';
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
            // Cache-busting: añadir filemtime para forzar recarga si el archivo cambia
            $bustSrc = function($p) { if ($p === '') return ''; $fs = BASE_PATH . '/' . ltrim($p, '/'); $m = @filemtime($fs); return ($m > 0) ? $p . '?t=' . $m : $p; };
            $squareSrcBust  = $bustSrc($squareSrc);
            $blurSrcBust    = $bustSrc($blurSrc);
            $proposalSrcBust = $bustSrc($proposalSrc);
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
                echo '<img id="finalBlurImg_' . e($fId) . '" src="' . e($blurSrcBust) . '" alt="Definitiva actual" style="width:100%;border-radius:8px;border:1px solid #e5e7eb;display:block;">';
            }
            echo '</div>';
            echo '<div id="finalProposalCol_' . e($fId) . '">';
            echo '<div style="font-size:11px;color:#9ca3af;margin-bottom:4px;text-align:center;">' . ($usesPolloVisualFlow ? 'Propuesta refinada' : 'Candidata elegida') . '</div>';
            $secondSrc = $usesPolloVisualFlow ? $proposalSrc : $squareSrc;
            $secondSrcBust = $usesPolloVisualFlow ? $proposalSrcBust : $squareSrcBust;
            if ($secondSrc !== '') {
                echo '<img src="' . e($secondSrcBust) . '" alt="Propuesta" style="width:100%;border-radius:8px;border:1px solid #e5e7eb;display:block;">';
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

// ───────────────────────────────────────────────────────────────────────
// SECCIÓN 3b: DEFINITIVAS EROTICAS
// ───────────────────────────────────────────────────────────────────────
echo '<section class="panel panel-space" id="publicistaSexyFinals">';
echo '<div class="branch-panel-head"><h3>③b Definitivas eróticas <span style="font-size:11px;background:#fce7f3;color:#be185d;padding:2px 8px;border-radius:4px;margin-left:8px;">+18</span></h3><span class="summary-badge">' . e((string)count($sexyFinalImages)) . '</span></div>';
if (!empty($sexyFinalImages)) {
    echo '<p style="font-size:13px;color:#6b7280;margin:4px 0 14px;">Las definitivas eróticas arrancan siendo copia de las candidatas eróticas. Puedes aplicar <strong style="color:#7c3aed;">blur manual</strong> para tapar la cara.</p>';
    echo '<div class="cards two">';
    foreach ($sexyFinalImages as $finalRow) {
        $fId = $finalRow['id'] ?? '';
        $blurSrc = !empty($finalRow['final_path']) ? $finalRow['final_path'] : (!empty($finalRow['square_path']) ? $finalRow['square_path'] : '');
        $bustSrc = function($p) { if ($p === '') return ''; $fs = BASE_PATH . '/' . ltrim($p, '/'); $m = @filemtime($fs); return ($m > 0) ? $p . '?t=' . $m : $p; };
        $blurSrcBust = $bustSrc($blurSrc);
        echo '<div class="panel" style="padding:12px;" id="sexyfinalCard_' . e($fId) . '">';
        $manualBlurApplied = !empty($finalRow['manual_blur_applied']);
        $manualBlurIntensity = (int)($finalRow['manual_blur_intensity'] ?? 0);
        echo '<div class="branch-panel-head" style="margin-bottom:8px;"><strong>' . e($fId ?: 'Sexy Final') . '</strong><div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;"><span class="summary-badge">Score ' . e((string)($finalRow['evaluation_score'] ?? 0)) . '</span><span id="sexyfinalBlurStatus_' . e($fId) . '" class="summary-badge" style="background:' . ($manualBlurApplied ? '#ede9fe' : '#f3f4f6') . ';color:' . ($manualBlurApplied ? '#6d28d9' : '#6b7280') . ';">' . e($manualBlurApplied ? ('Blur manual · ' . $manualBlurIntensity . '/20') : 'Sin blur manual') . '</span>';
        echo '</div></div>';
        if ($blurSrc !== '') {
            echo '<img id="sexyfinalBlurImg_' . e($fId) . '" src="' . e($blurSrcBust) . '" alt="Definitiva erotica" style="width:100%;border-radius:8px;border:1px solid #e5e7eb;display:block;">';
        }
        echo '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">';
        if ($blurSrc !== '') {
            echo '<button type="button" class="btn-primary js-manual-blur-sexy-btn" style="background:#7c3aed;border-color:#7c3aed;" '
                . 'data-job-id="' . e($selectedJob['id'] ?? '') . '" '
                . 'data-sexyfinal-id="' . e($fId) . '" '
                . 'data-square-src="' . e($blurSrc) . '" '
                . 'data-intensity="' . e((string)((int)($finalRow['manual_blur_intensity'] ?? 8))) . '">✏ Blur manual</button>';
        }
        echo '</div>';
        echo '</div>';
    }
    echo '</div>';
} else {
    echo '<div class="empty">Finales eróticas pendientes. Se crean automáticamente al completar el pipeline de imágenes si la opción erótica está activada.</div>';
}
echo '</section>';

// -----------------------------------------------------------------------
// SECCIÓN 4: FOTOS REALES SUBIDAS
// -----------------------------------------------------------------------
$realPhotos = is_array($selectedJob['real_photos'] ?? null) ? $selectedJob['real_photos'] : array();
echo '<section class="panel panel-space" id="publicistaReals">';
echo '<div class="branch-panel-head"><h3>④ Fotos reales subidas</h3><span class="summary-badge">' . e((string)count($realPhotos)) . '</span></div>';

if (!empty($realPhotos)) {
    echo '<div style="margin-top:8px;font-size:12px;color:#6b7280;">Estas fotos se subieron manualmente. Puedes eliminarlas o más adelante aplicarles blur manual desde esta misma sección.</div>';
    echo '<div class="cards two" style="margin-top:14px;">';
    foreach ($realPhotos as $rp) {
        $rpId = $rp['id'] ?? '';
        $rpPath = $rp['stored_path'] ?? '';
        $rpName = $rp['original_filename'] ?? $rpId;
        $rpWidth = (int)($rp['width'] ?? 0);
        $rpHeight = (int)($rp['height'] ?? 0);
        $rpUploaded = $rp['uploaded_at'] ?? '';
        echo '<div class="panel" style="padding:12px;">';
        echo '<div class="branch-panel-head" style="margin-bottom:8px;"><strong>' . e($rpName) . '</strong></div>';
        if ($rpPath !== '') {
            echo '<img id="realBlurImg_' . e($rpId) . '" src="' . e($rpPath) . '" alt="Foto real" style="width:100%;border-radius:8px;border:1px solid #e5e7eb;display:block;">';
        }
        echo '<div style="display:flex;gap:8px;margin-top:8px;font-size:11px;color:#9ca3af;">';
        echo '<span>' . e($rpWidth . '×' . $rpHeight) . '</span>';
        if ($rpUploaded !== '') {
            echo '<span>' . e(format_created_at($rpUploaded)) . '</span>';
        }
        echo '</div>';
        $rpBlurApplied = !empty($rp['manual_blur_applied']);
        $rpBlurIntensity = (int)($rp['manual_blur_intensity'] ?? 0);
        echo '<div style="display:flex;gap:8px;margin-top:8px;">';
        echo '<button type="button" class="btn-primary js-manual-blur-btn" style="font-size:12px;padding:4px 10px;background:#7c3aed;border-color:#7c3aed;" '
            . 'data-job-id="' . e($selectedJob['id'] ?? '') . '" '
            . 'data-photo-id="' . e($rpId) . '" '
            . 'data-square-src="' . e($rpPath) . '" '
            . 'data-intensity="' . e((string)$rpBlurIntensity) . '" '
            . 'data-target="real">✏ Blur manual</button>';
        if ($rpBlurApplied) {
            echo '<span id="realBlurStatus_' . e($rpId) . '" class="summary-badge" style="background:#ede9fe;color:#6d28d9;">Blur · ' . e((string)$rpBlurIntensity) . '/20</span>';
        } else {
            echo '<span id="realBlurStatus_' . e($rpId) . '" class="summary-badge" style="background:#f3f4f6;color:#6b7280;">Sin blur</span>';
        }
        echo '</div>';
        echo '<form method="post" class="inline-form" style="margin-top:8px;" onsubmit="return confirm(\'¿Eliminar esta foto real?\')">';
        echo '<input type="hidden" name="action" value="delete_publicista_real_photo">';
        echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
        echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
        echo '<input type="hidden" name="photo_id" value="' . e($rpId) . '">';
        echo '<button class="btn-secondary-mini" style="color:#b91c1c;">Eliminar</button>';
        echo '</form>';
        echo '</div>';
    }
    echo '</div>';
} else {
    echo '<div class="empty">No hay fotos reales subidas todavía. Usa el formulario de abajo para añadirlas.</div>';
}

// Formulario rápido de subida desde la sección
echo '<form method="post" enctype="multipart/form-data" class="inline-form" style="margin-top:14px;">';
echo '<input type="hidden" name="action" value="upload_publicista_real_photos">';
echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
echo '<input type="file" name="real_photos[]" accept="image/jpeg,image/png,image/webp" multiple style="font-size:12px;">';
echo ' <button class="btn-secondary-mini">Subir fotos reales</button>';
echo '</form>';
echo '</section>';

// -----------------------------------------------------------------------
// SECCIÓN 5: FOTOS POR PLATAFORMA
// -----------------------------------------------------------------------
$allPhotosForSelection = array_merge(
    is_array($selectedJob['final_images'] ?? null) ? $selectedJob['final_images'] : array(),
    is_array($selectedJob['sexy_final_images'] ?? null) ? $selectedJob['sexy_final_images'] : array(),
    is_array($selectedJob['real_photos'] ?? null) ? $selectedJob['real_photos'] : array()
);
$platformPhotos = is_array($selectedJob['platform_photos'] ?? null) ? $selectedJob['platform_photos'] : array(
    'destacamos' => array(), 'mundosex' => array(), 'girlsconf' => array(),
);

echo '<section class="panel panel-space" id="publicistaPlatformPhotos">';
echo '<div class="branch-panel-head"><h3>⑤ Fotos por plataforma</h3><span class="summary-badge">Selecciona cuáles usar</span></div>';
echo '<div style="margin-top:8px;font-size:12px;color:#6b7280;">Para cada plataforma, marca las fotos que quieres que se usen al publicar. Si no configuras una plataforma, la campaña <strong>no se podrá ejecutar</strong> para ese portal.</div>';

if (empty($allPhotosForSelection)) {
    echo '<div class="empty" style="margin-top:14px;">No hay fotos disponibles todavía. Genera las definitivas primero.</div>';
} else {
    echo '<form method="post" style="margin-top:14px;">';
    echo '<input type="hidden" name="action" value="save_publicista_platform_photos">';
    echo '<input type="hidden" name="id" value="' . e($selectedJob['id'] ?? '') . '">';
    echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';

    $platforms = array(
        'destacamos' => 'Destacamos',
        'mundosex'   => 'Mundosex',
        'girlsconf'  => 'Girlsconf',
    );

    foreach ($platforms as $pCode => $pName) {
        $selectedIds = is_array($platformPhotos[$pCode] ?? null) ? $platformPhotos[$pCode] : array();
        $selectedCount = count($selectedIds);
        echo '<div style="margin-bottom:20px;border:1px solid #e5e7eb;border-radius:10px;padding:14px;">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">';
        echo '<strong style="font-size:14px;">' . e($pName) . '</strong>';
        echo '<span class="summary-badge">' . e((string)$selectedCount) . ' seleccionadas</span>';
        echo '</div>';
        echo '<div style="display:flex;flex-wrap:wrap;gap:10px;">';
        foreach ($allPhotosForSelection as $photo) {
            $pId = trim((string)($photo['id'] ?? ''));
            if ($pId === '') continue;
            $pSrc = '';
            if (!empty($photo['preview_path'])) $pSrc = $photo['preview_path'];
            elseif (!empty($photo['square_path'])) $pSrc = $photo['square_path'];
            elseif (!empty($photo['final_path'])) $pSrc = $photo['final_path'];
            elseif (!empty($photo['stored_path'])) $pSrc = $photo['stored_path'];
            
            $checked = in_array($pId, $selectedIds, true) ? ' checked' : '';
            $isReal = strpos($pId, 'real_') === 0;
            $isFinal = strpos($pId, 'final_') === 0;
            $isSexyFinal = strpos($pId, 'sexyfinal_') === 0;
            if ($isReal) {
                $label = '📸 ' . e($photo['original_filename'] ?? $pId);
                $typeBadge = ' <span style="font-size:9px;background:#fef3c7;color:#92400e;padding:1px 4px;border-radius:3px;">REAL</span>';
            } elseif ($isSexyFinal) {
                $label = 'Erótica #' . substr($pId, 10);
                $typeBadge = ' <span style="font-size:9px;background:#fce7f3;color:#9d174d;padding:1px 4px;border-radius:3px;">ERO</span>';
            } elseif ($isFinal) {
                $label = 'Definitiva #' . substr($pId, 6);
                $typeBadge = ' <span style="font-size:9px;background:#dbeafe;color:#1e40af;padding:1px 4px;border-radius:3px;">DEF</span>';
            } else {
                $label = e($pId);
                $typeBadge = '';
            }
            $blurBadge = '';
            if (!empty($photo['manual_blur_applied'])) {
                $blurBadge = ' <span style="font-size:9px;background:#ede9fe;color:#6d28d9;padding:1px 4px;border-radius:3px;">BLUR</span>';
            }
            
            echo '<label style="display:flex;flex-direction:column;align-items:center;gap:4px;cursor:pointer;border:2px solid ' . ($checked ? '#6366f1' : '#e5e7eb') . ';border-radius:8px;padding:4px;min-width:110px;transition:border-color .15s;" class="platform-photo-label">';
            if ($pSrc !== '') {
                echo '<img src="' . e($pSrc) . '" style="width:100px;height:100px;object-fit:cover;border-radius:6px;">';
            }
            echo '<span style="font-size:10px;color:#374151;text-align:center;max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . $label . $typeBadge . $blurBadge . '</span>';
            echo '<input type="checkbox" name="platform_photos[' . e($pCode) . '][]" value="' . e($pId) . '"' . $checked . ' style="clip:rect(0,0,0,0);clip-path:inset(50%);position:absolute;width:1px;height:1px;overflow:hidden;white-space:nowrap;">';
            echo '</label>';
        }
        echo '</div>';
        echo '</div>';
    }

    echo '<div class="full"><button class="btn-primary">Guardar configuración por plataforma</button></div>';
    echo '</form>';
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
      <strong style="font-size:16px;" id="regenerateCandidateModalTitle">Regenerar candidata con refinado</strong>
      <button type="button" onclick="closeRegenerateCandidateModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#6b7280;">&times;</button>
    </div>
    <p style="margin:0 0 10px;font-size:13px;color:#6b7280;">Se reutiliza el prompt base de esta candidata y se añade tu texto de refinado para regenerarla.</p>
    <div id="regenPolloWarning" style="display:none;margin:0 0 8px;padding:8px 12px;background:#fff3cd;border:1px solid #ffc107;border-radius:6px;font-size:12px;color:#856404;align-items:center;gap:8px;">
      <span style="font-size:16px;">⚠️</span>
      <span id="regenPolloWarningText"></span>
      <button type="button" onclick="sanitizeRegenText()" style="margin-left:auto;border:none;background:#ffc107;color:#664d03;padding:3px 8px;border-radius:4px;cursor:pointer;font-size:11px;white-space:nowrap;">✏️ Corregir</button>
    </div>
    <form method="post" id="regenerateCandidateForm" onsubmit="return validateRegenText()">
      <input type="hidden" name="action" value="regenerate_publicista_candidate" id="regenCandidateAction">
      <input type="hidden" name="id" id="regenCandidateJobId" value="">
      <input type="hidden" name="candidate_id" id="regenCandidateId" value="">
      <label for="regenCandidateRefineText" style="display:block;font-size:13px;color:#374151;margin-bottom:6px;">Texto de refinado (opcional)</label>
      <textarea name="refine_text" id="regenCandidateRefineText" rows="6" maxlength="1200" placeholder="Ejemplo: mantener la misma pose y complexión, mejorar manos, cara más nítida, fondo más natural..." style="width:100%;"></textarea>
      <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
        <button type="submit" class="btn-primary" id="regenCandidateSubmitBtn">Regenerar candidata</button>
        <button type="button" class="btn-secondary" onclick="closeRegenerateCandidateModal();closeRegenerateSexyCandidateModal();">Cancelar</button>
        <span style="font-size:12px;color:#6b7280;">Si esta candidata está en TOP 6, las finales se recomponen automáticamente.</span>
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
// Cache compartido entre los modales de regeneración y el polling (mismo <script>, var global)
var _regenQueueCache = {};
(function() {
  var _mbJobId = '', _mbFinalId = '', _mbTarget = 'final', _mbPhotoId = '', _mbCsrfToken = '<?php echo e(csrf_token()); ?>', _mbEllipse = null, _mbDragging = false, _mbStartX = 0, _mbStartY = 0;
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
    // Guard: no abrir si la candidata ya está en cola
    var q = _regenQueueCache[candidateId];
    if (q) {
      var s = q.status || '';
      if (s === 'queued' || s === 'running' || s === 'waiting_pollo') {
        alert('Esta candidata ya está en la cola de regeneración. Espera a que termine.');
        return;
      }
    }
    // Resetear acción y título al modo normal (por si se usó el modal erótico antes)
    document.getElementById('regenCandidateAction').value = 'regenerate_publicista_candidate';
    document.getElementById('regenerateCandidateModalTitle').innerHTML = 'Regenerar candidata con refinado';
    document.getElementById('regenCandidateJobId').value = jobId || '';
    document.getElementById('regenCandidateId').value = candidateId || '';
    document.getElementById('regenCandidateRefineText').value = '';
    document.getElementById('regenerateCandidateModal').style.display = 'flex';
    document.getElementById('regenCandidateRefineText').focus();
  };

  window.closeRegenerateCandidateModal = function() {
    document.getElementById('regenerateCandidateModal').style.display = 'none';
    document.getElementById('regenPolloWarning').style.display = 'none';
  };

  // ── Sexy candidate regeneration modal ──
  window.openRegenerateSexyCandidateModal = function(jobId, candidateId) {
    var sexyKey = 'sexy_' + candidateId;
    var q = _regenQueueCache[sexyKey];
    if (q) {
      var s = q.status || '';
      if (s === 'queued' || s === 'running' || s === 'waiting_pollo') {
        alert('Esta candidata erótica ya está en la cola de regeneración. Espera a que termine.');
        return;
      }
    }
    document.getElementById('regenCandidateJobId').value = jobId || '';
    document.getElementById('regenCandidateId').value = candidateId || '';
    document.getElementById('regenCandidateRefineText').value = '';
    document.getElementById('regenCandidateAction').value = 'regenerate_publicista_sexy_candidate';
    document.getElementById('regenerateCandidateModalTitle').innerHTML = 'Regenerar candidata <span style="font-size:11px;background:#fce7f3;color:#be185d;padding:2px 6px;border-radius:4px;">ERÓTICA</span>';
    document.getElementById('regenerateCandidateModal').style.display = 'flex';
    document.getElementById('regenCandidateRefineText').focus();
  };

  window.closeRegenerateSexyCandidateModal = function() {
    document.getElementById('regenerateCandidateModal').style.display = 'none';
    // Restaurar título por defecto
    document.getElementById('regenerateCandidateModalTitle').innerHTML = 'Regenerar candidata';
    document.getElementById('regenCandidateAction').value = 'regenerate_publicista_candidate';
  };

  // ── Validación client-side: detecta palabras que Pollo.ai bloquea ──
  window._polloTriggerWords = ['milf', 'MILF', 'teen', 'cougar', 'sugar baby', 'escort', 'prostituta', 'puta', 'porno', 'fuck', 'sexo explícito'];

  window.validateRegenText = function() {
    var text = document.getElementById('regenCandidateRefineText').value || '';
    var found = [];
    for (var i = 0; i < window._polloTriggerWords.length; i++) {
      var word = window._polloTriggerWords[i];
      if (text.toLowerCase().indexOf(word.toLowerCase()) !== -1) {
        found.push(word);
      }
    }
    if (found.length > 0) {
      var warnDiv = document.getElementById('regenPolloWarning');
      var warnText = document.getElementById('regenPolloWarningText');
      warnText.textContent = 'Pollo.ai rechaza las palabras: ' + found.join(', ') + '. El sistema las reemplazará automáticamente, pero puedes corregirlas ahora.';
      warnDiv.style.display = 'flex';
      return false; // No enviar — dejar que el usuario corrija
    }
    return true;
  };

  window.sanitizeRegenText = function() {
    var textarea = document.getElementById('regenCandidateRefineText');
    var text = textarea.value || '';
    var replacements = {
      'milf': 'mujer madura', 'MILF': 'MUJER MADURA',
      'teen': 'joven', 'cougar': 'mujer atractiva mayor',
      'sugar baby': 'acompañante', 'escort': 'acompañante',
      'prostituta': 'trabajadora', 'puta': 'mujer',
      'porno': 'contenido adulto', 'fuck': '',
      'sexo explícito': 'contenido sugerente'
    };
    for (var word in replacements) {
      if (replacements.hasOwnProperty(word)) {
        var regex = new RegExp(word.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi');
        text = text.replace(regex, replacements[word]);
      }
    }
    text = text.replace(/\s{2,}/g, ' ').trim();
    textarea.value = text;
    document.getElementById('regenPolloWarning').style.display = 'none';
  };

  // Ocultar warning cuando se escribe en el textarea (el usuario está corrigiendo)
  (function() {
    var ta = document.getElementById('regenCandidateRefineText');
    if (ta) {
      ta.addEventListener('input', function() {
        document.getElementById('regenPolloWarning').style.display = 'none';
      });
    }
  })();

  window.syncManualBlurIntensity = function(value) {
    var num = parseInt(value, 10);
    if (!isFinite(num)) num = 8;
    num = Math.max(1, Math.min(20, num));
    document.getElementById('manualBlurIntensityRange').value = num;
    document.getElementById('manualBlurIntensityNumber').value = num;
  };

  window.openManualBlurModal = function(jobId, finalId, squareSrc, currentIntensity, target) {
    _mbJobId = jobId || '';
    _mbFinalId = (target === 'real' || target === 'source') ? '' : (finalId || '');
    _mbPhotoId = (target === 'real' || target === 'source') ? (finalId || '') : '';
    _mbTarget = target || 'final';
    _mbEllipse = null;
    var targetId = (_mbTarget === 'real') ? _mbPhotoId : _mbFinalId;
    if (!_mbJobId || (!targetId && _mbTarget !== 'source') || !squareSrc) {
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
    _mbPhotoId = '';
    _mbTarget = 'final';
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
    // Sexy candidate regeneration
    var sexyRegenBtn = e.target.closest ? e.target.closest('.js-open-regenerate-sexy-candidate-modal') : null;
    if (sexyRegenBtn) {
      e.preventDefault();
      openRegenerateSexyCandidateModal(
        sexyRegenBtn.getAttribute('data-job-id') || '',
        sexyRegenBtn.getAttribute('data-candidate-id') || ''
      );
      return;
    }
    // Sexy final blur
    var sexyBlurBtn = e.target.closest ? e.target.closest('.js-manual-blur-sexy-btn') : null;
    if (sexyBlurBtn) {
      e.preventDefault();
      var sexyIntensity = parseInt(sexyBlurBtn.getAttribute('data-intensity') || '8', 10);
      if (!isFinite(sexyIntensity)) sexyIntensity = 8;
      window.openManualBlurModal(
        sexyBlurBtn.getAttribute('data-job-id') || '',
        sexyBlurBtn.getAttribute('data-sexyfinal-id') || '',
        sexyBlurBtn.getAttribute('data-square-src') || '',
        sexyIntensity,
        'final'  // tratamos sexyfinal como tipo 'final' para el blur worker
      );
      return;
    }
    var btn = e.target.closest ? e.target.closest('.js-manual-blur-btn') : null;
    if (!btn) return;
    e.preventDefault();
    var target = btn.getAttribute('data-target') || 'final';
    if (target === 'real') {
      var intensity = parseInt(btn.getAttribute('data-intensity') || '8', 10);
      if (!isFinite(intensity)) intensity = 8;
      window.openManualBlurModal(
        btn.getAttribute('data-job-id') || '',
        btn.getAttribute('data-photo-id') || '',
        btn.getAttribute('data-square-src') || '',
        intensity,
        'real'
      );
    } else if (target === 'source') {
      var intensity2 = parseInt(btn.getAttribute('data-intensity') || '8', 10);
      if (!isFinite(intensity2)) intensity2 = 8;
      window.openManualBlurModal(
        btn.getAttribute('data-job-id') || '',
        '',
        btn.getAttribute('data-square-src') || '',
        intensity2,
        'source'
      );
    } else {
      openManualBlurModalFromButton(btn);
    }
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
    if (_mbTarget === 'source') {
      fd.append('action', 'apply_publicista_manual_blur_source');
      fd.append('id', _mbJobId);
    } else if (_mbTarget === 'real') {
      fd.append('action', 'apply_publicista_manual_blur_real');
      fd.append('id', _mbJobId);
      fd.append('photo_id', _mbPhotoId);
    } else {
      fd.append('action', 'apply_publicista_manual_blur');
      fd.append('id', _mbJobId);
      fd.append('final_id', _mbFinalId);
    }
    fd.append('csrf_token', _mbCsrfToken);
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
          if (_mbTarget === 'real') {
            var img = document.getElementById('realBlurImg_' + _mbPhotoId);
            if (img && data.stored_path) {
              img.src = data.stored_path + '?t=' + Date.now();
            }
            var badge = document.getElementById('realBlurStatus_' + _mbPhotoId);
            if (badge) {
              var intensityText = (data.manual_blur_intensity || intensity) + '/20';
              badge.textContent = 'Blur · ' + intensityText;
              badge.style.background = '#ede9fe';
              badge.style.color = '#6d28d9';
            }
          } else if (_mbTarget === 'source') {
            var img = document.getElementById('originalBlurImg_' + _mbJobId);
            if (img && data.stored_path) {
              img.src = data.stored_path + '?t=' + Date.now();
            }
            var badge = document.getElementById('originalBlurStatus_' + _mbJobId);
            if (badge) {
              var intensityText = (data.manual_blur_intensity || intensity) + '/20';
              badge.textContent = 'Blur manual · ' + intensityText;
              badge.style.background = '#ede9fe';
              badge.style.color = '#6d28d9';
            }
          } else {
            // Detectar si es final erotica o capada por el prefijo del ID
            var isSexyFinal = _mbFinalId.indexOf('sexyfinal_') === 0;
            var imgPrefix = isSexyFinal ? 'sexyfinalBlurImg_' : 'finalBlurImg_';
            var badgePrefix = isSexyFinal ? 'sexyfinalBlurStatus_' : 'finalBlurStatus_';
            var img = document.getElementById(imgPrefix + _mbFinalId);
            if (img && data.final_path) {
              img.src = data.final_path + '?t=' + Date.now();
            }
            var badge = document.getElementById(badgePrefix + _mbFinalId);
            if (badge) {
              var intensityText = (data.manual_blur_intensity || intensity) + '/20';
              badge.textContent = 'Blur manual · ' + intensityText;
              badge.style.background = '#ede9fe';
              badge.style.color = '#6d28d9';
            }
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

// ── Polling automático de estado de regeneraciones ──────────────────────────
(function() {
  var POLL_INTERVAL_MS  = 8000;  // cada 8 segundos
  var POLL_IDLE_MS      = 30000; // cuando no hay cola activa, cada 30s
  var _pollTimer        = null;
  var _jobId            = '';
  var _knownMtimes      = {};    // candidateId -> mtime visto
  var _knownAvisosCount = -1;
  var _hasActiveQueue   = false;
  var _pollWasActive    = false;

  function initPoll() {
    // Leer el job id de un atributo del DOM inyectado por PHP
    var el = document.getElementById('publicistaRegenPollJobId');
    if (!el) return;
    _jobId = el.getAttribute('data-job-id') || '';
    if (!_jobId) return;
    schedulePoll(1500); // primer poll al cargar
  }

  function schedulePoll(delay) {
    if (_pollTimer) clearTimeout(_pollTimer);
    _pollTimer = setTimeout(doPoll, delay || POLL_INTERVAL_MS);
  }

  function doPoll() {
    fetch('index.php?page=publicista&action=poll_publicista_regen_status&id=' + encodeURIComponent(_jobId), {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    })
    .then(function(r) { return r.ok ? r.json() : null; })
    .then(function(data) {
      if (!data || !data.ok) { schedulePoll(POLL_IDLE_MS); return; }

      _hasActiveQueue = false;
      var hasQueued = false;
      var queue = data.queue || {};
      // Cachear cola para consultas síncronas (modal guard)
      _regenQueueCache = queue;
      // Detectar si hay algo en cola activo
       for (var cid in queue) {
         var s = queue[cid] ? queue[cid].status : '';
         if (s === 'queued' || s === 'running' || s === 'waiting_pollo') { _hasActiveQueue = true; }
         if (s === 'queued' || s === 'waiting_pollo') { hasQueued = true; }
       }

      // Mostrar/ocultar botón de cancelar cola global
      updateCancelQueueButton(hasQueued);

      // Actualizar imágenes de candidatas que hayan cambiado
      var candidates = data.candidates || {};
      for (var candId in candidates) {
        var cand = candidates[candId];
        var newMtime = cand.mtime || 0;
        if (newMtime > 0 && newMtime !== (_knownMtimes[candId] || 0)) {
          _knownMtimes[candId] = newMtime;
          // Actualizar src de la imagen si existe en el DOM
          var card = document.querySelector('[data-candidate-id="' + candId + '"]');
          var imgs = card ? card.querySelectorAll('img') : [];
          if (!imgs.length) {
            // Fallback 1: buscar imágenes cuyo src contenga algún fragmento del path
            var basePath = (cand.square_path || '').replace(/\?.*$/, '');
            var baseFile = basePath.split('/').pop().replace(/\.[^.]+$/, '').replace(/_manual$/, '');
            if (baseFile) {
              imgs = document.querySelectorAll('img[src*="' + baseFile + '"]');
            }
          }
          if (!imgs.length) {
            // Fallback 2: buscar cualquier img dentro del tab actual
            console.warn('Publicista poll: no se encontraron imágenes para la candidata ' + candId + ' (square_path: ' + (cand.square_path || '') + ')');
          }
          if (imgs.length) {
            // Forzar recarga agresiva con doble cache-bust
            var freshSrc = (cand.src || '') + '&_=' + Date.now();
            for (var i = 0; i < imgs.length; i++) {
              imgs[i].src = '';  // Invalidar caché primero
              imgs[i].src = freshSrc;
            }
          }
          // Mostrar badge de "Actualizada" solo si realmente se actualizó alguna imagen
          var qStatus = queue[candId] ? queue[candId].status : '';
          if (qStatus === 'done' || (_knownMtimes[candId] && newMtime && imgs.length > 0)) {
            showCandidateUpdatedBadge(candId);
          }
        }
        // Mostrar estado de cola en el botón si está en progreso
        updateQueueBadge(candId, queue[candId] || null, cand);
      }

      // Actualizar imágenes de candidatas eróticas que hayan cambiado
      var sexyCandidates = data.sexy_candidates || {};
      for (var sexyCandId in sexyCandidates) {
        var sc = sexyCandidates[sexyCandId];
        var newMtimeSc = sc.mtime || 0;
        var knownKey = 'sexy_' + sexyCandId;
        if (newMtimeSc > 0 && newMtimeSc !== (_knownMtimes[knownKey] || 0)) {
          _knownMtimes[knownKey] = newMtimeSc;
          var sexyCard = document.querySelector('[data-candidate-id="' + sexyCandId + '"][data-candidate-type="sexy"]');
          var sexyImgs = sexyCard ? sexyCard.querySelectorAll('img') : [];
          if (!sexyImgs.length) {
            var sexyBasePath = (sc.square_path || '').replace(/\?.*$/, '');
            var sexyBaseFile = sexyBasePath.split('/').pop().replace(/\.[^.]+$/, '').replace(/_manual$/, '');
            if (sexyBaseFile) {
              sexyImgs = document.querySelectorAll('img[src*="' + sexyBaseFile + '"]');
            }
          }
          if (sexyImgs.length) {
            var freshSexySrc = (sc.src || '') + '&_=' + Date.now();
            for (var si = 0; si < sexyImgs.length; si++) {
              sexyImgs[si].src = '';
              sexyImgs[si].src = freshSexySrc;
            }
          }
          var sexyQStatus = queue['sexy_' + sexyCandId] ? queue['sexy_' + sexyCandId].status : '';
          if (sexyQStatus === 'done' || (_knownMtimes[knownKey] && newMtimeSc && sexyImgs.length > 0)) {
            showCandidateUpdatedBadge(sexyCandId);
          }
        }
        updateQueueBadge(sexyCandId, queue['sexy_' + sexyCandId] || null, sc);
      }

      // Avisos: si hay nuevos, pulsar el sistema de avisos para refrescar badge
      var newAvisosCount = parseInt(data.avisos_count || 0, 10);
      if (_knownAvisosCount >= 0 && newAvisosCount > _knownAvisosCount) {
        refreshAvisosBadge();
      }
      _knownAvisosCount = newAvisosCount;

      // Próximo poll: rápido si hay cola activa, lento si todo quieto
      // Si acaba de terminar algo, mantenemos un poll rápido durante una iteración más para no perder la actualización
      var justFinished = !_hasActiveQueue && (_pollWasActive === true);
      schedulePoll((_hasActiveQueue || justFinished) ? POLL_INTERVAL_MS : POLL_IDLE_MS);
      _pollWasActive = _hasActiveQueue;
    })
    .catch(function() { schedulePoll(POLL_IDLE_MS); });
  }

  function showCandidateUpdatedBadge(candId) {
    // Añade un badge temporal verde sobre la tarjeta de candidata
    var selector = '[data-candidate-id="' + candId + '"]';
    var card = document.querySelector(selector);
    if (!card) return;
    var existing = card.querySelector('.js-regen-done-badge');
    if (existing) existing.remove();
    var badge = document.createElement('div');
    badge.className = 'js-regen-done-badge';
    badge.style.cssText = 'position:absolute;top:8px;right:8px;background:#059669;color:#fff;font-size:11px;padding:3px 8px;border-radius:6px;font-weight:600;z-index:10;pointer-events:none;';
    badge.textContent = '✓ Imagen actualizada';
    card.style.position = 'relative';
    card.appendChild(badge);
    setTimeout(function() { if (badge.parentNode) badge.parentNode.removeChild(badge); }, 6000);
  }

  function updateQueueBadge(candId, queueEntry, cand) {
    var btn = document.querySelector('.js-open-regenerate-candidate-modal[data-candidate-id="' + candId + '"]') ||
              document.querySelector('.js-open-regenerate-sexy-candidate-modal[data-candidate-id="' + candId + '"]');
    if (!btn) return;
    if (!queueEntry) return;
    var s = queueEntry.status || '';
    var updatedAt = queueEntry.updated_at || '';
    if (s === 'queued') {
      btn.textContent = '⏳ En cola…';
      btn.disabled = true;
    } else if (s === 'waiting_pollo') {
      btn.textContent = '⏳ Esperando turno Pollo…';
      btn.disabled = true;
    } else if (s === 'running') {
      btn.textContent = '⚙ Generando…';
      btn.disabled = true;
    } else if (s === 'done') {
      btn.textContent = 'Regenerar esta';
      btn.disabled = false;
    } else if (s === 'cancelled') {
      btn.textContent = 'Regenerar esta';
      btn.disabled = false;
    } else if (s === 'error') {
      btn.textContent = 'Regenerar esta';
      btn.disabled = false;
      // Mostrar error brevemente — solo si no estaba ya mostrado
      var errMsg = queueEntry.error || 'Error desconocido.';
      // Simplificar mensajes de error de Pollo para el usuario final
      if (errMsg.indexOf('generacion fallo') !== -1 || errMsg.indexOf('generación falló') !== -1) {
        if (errMsg.indexOf('desconocido') !== -1 || errMsg.indexOf('unknown') !== -1) {
          errMsg = 'Pollo.ai devolvió un error sin explicación tras varios reintentos automáticos. La cuenta puede estar temporalmente saturada. Espera 3-5 minutos y vuelve a intentarlo.';
        } else {
          errMsg = 'Pollo.ai rechazó la generación (cuenta ocupada). Espera unos minutos y vuelve a intentarlo.';
        }
      } else if (errMsg.indexOf('timeout') !== -1 || errMsg.indexOf('Timeout') !== -1) {
        errMsg = 'La generación tardó demasiado y se agotó el tiempo de espera. Vuelve a intentarlo en unos minutos.';
      }
      var card = btn.closest('.panel');
      if (card) {
        var existing = card.querySelector('.js-regen-error-inline');
        if (!existing) {
          var errDiv = document.createElement('div');
          errDiv.className = 'js-regen-error-inline';
          errDiv.style.cssText = 'margin-top:6px;font-size:11px;color:#b91c1c;background:#fee2e2;padding:4px 8px;border-radius:5px;';
          errDiv.textContent = '✗ ' + errMsg;
          btn.parentNode.appendChild(errDiv);
          setTimeout(function() { if (errDiv.parentNode) errDiv.parentNode.removeChild(errDiv); }, 30000);
        }
      }
    }
  }

  function updateCancelQueueButton(hasQueued) {
    var section = document.getElementById('publicistaCandidates');
    if (!section) return;
    var existing = document.getElementById('js-cancel-regen-queue-btn');
    if (!hasQueued) {
      if (existing) existing.remove();
      return;
    }
    if (existing) return; // ya existe
    var btn = document.createElement('button');
    btn.id = 'js-cancel-regen-queue-btn';
    btn.type = 'button';
    btn.style.cssText = 'margin:0 0 10px 0;font-size:12px;padding:4px 12px;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;border-radius:6px;cursor:pointer;';
    btn.textContent = '✕ Cancelar regeneraciones en cola';
    btn.onclick = function() {
      btn.disabled = true;
      btn.textContent = 'Cancelando…';
      fetch('index.php?page=publicista&action=cancel_publicista_regen_queue&id=' + encodeURIComponent(_jobId), {
        method: 'GET', credentials: 'same-origin'
      })
      .then(function(r) { return r.json(); })
      .then(function(data) {
        if (existing) existing.remove();
        btn.remove();
        doPoll(); // actualizar UI inmediatamente
      })
      .catch(function() { btn.disabled = false; btn.textContent = '✕ Cancelar regeneraciones en cola'; });
    };
    // Insertar justo antes de la grid de candidatas
    var grid = section.querySelector('.cards.two');
    if (grid) section.insertBefore(btn, grid);
    else section.appendChild(btn);
  }

  function refreshAvisosBadge() {
    // Recarga solo el panel de avisos vía fetch para mostrar el nuevo aviso sin recargar página
    var avisosPanel = document.getElementById('avisosPanel');
    if (!avisosPanel) return;
    fetch(window.location.href, { credentials: 'same-origin' })
      .then(function(r) { return r.ok ? r.text() : null; })
      .then(function(html) {
        if (!html) return;
        var parser = new DOMParser();
        var doc = parser.parseFromString(html, 'text/html');
        var newPanel = doc.getElementById('avisosPanel');
        if (newPanel) {
          avisosPanel.innerHTML = newPanel.innerHTML;
        }
        // Actualizar badge de contador
        var newBadge = doc.querySelector('.aviso-new-count, .summary-badge.aviso-badge');
        var curBadge = document.querySelector('.aviso-new-count, .summary-badge.aviso-badge');
        if (newBadge && curBadge) {
          curBadge.textContent = newBadge.textContent;
          curBadge.style.display = newBadge.style.display || '';
        }
      })
      .catch(function() {});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPoll);
  } else {
    initPoll();
  }
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

    // EROTIC MODE — checkbox para generar variante erotica adicional
    $eroticChecked = !empty($params['erotic_mode']) ? ' checked' : '';
    echo '<div class="field full" style="margin-top:10px;">';
    echo '<label style="display:flex;align-items:center;gap:8px;cursor:pointer;">';
    echo '<input type="checkbox" name="erotic_mode" value="1"' . $eroticChecked . ' style="width:18px;height:18px;">';
    echo '<span>Generar también <strong style="color:#e11d48;">variante erótica</strong> (4 candidatas subidas de tono: lencería, bikinis, poses sensuales)</span>';
    echo '</label>';
    echo '<div class="field-help">Actívalo si el perfil va a publicarse en portales para adultos que admiten contenido erótico. Las 4 candidatas normales se generan siempre; con esta opción se añaden 4 más de tipo sexual.</div>';
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
    $fsPath = BASE_PATH . '/' . ltrim($relativePath, '/');
    $mtime = file_exists($fsPath) ? filemtime($fsPath) : 0;
    $srcWithBust = $mtime > 0 ? $relativePath . '?t=' . $mtime : $relativePath;
    echo '<div class="publicista-preview-card">';
    echo '<div class="muted" style="margin-bottom:8px;">' . e($label) . '</div>';
    echo '<img src="' . e($srcWithBust) . '" alt="' . e($label) . '" style="width:100%;max-width:340px;border-radius:12px;border:1px solid #e5e7eb;display:block;">';
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
        $hint = 'Entra en <strong>Candidatas generadas</strong> y pulsa <strong>Regenerar esta</strong> en las flojas. El sistema recompone automáticamente el top 6.';
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
                        : 'Cuando termine la generación, aquí aparecerán las candidatas y después el top 6 visual refinado.')
                    : ($usesPolloVisualFlow
                        ? 'Aquí revisas candidatas, definitivas base y propuestas refinadas manuales hasta cerrar el top visual.'
                        : 'Aquí revisas las candidatas y regeneras solo las que no convencen hasta dejar un top 6 limpio y refinado.'))
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
    echo '      <form method="post" id="loginForm">';
    echo '          <input type="hidden" name="action" value="login">';
    echo '          <div class="field"><label>Usuario</label><input type="text" name="username" id="loginUsername" required></div>';
    echo '          <div class="field"><label>Contraseña</label><input type="password" name="password" id="loginPassword" required></div>';
    echo '          <button type="submit" class="btn-primary" id="loginBtn">Entrar</button>';
    echo '      </form>';
    echo '  </div>';
    echo '</div>';
    echo '<script>';
    echo '(function(){';
    echo '  var userTouched=false,passTouched=false,pressTimer=null;';
    echo '  var u=document.getElementById("loginUsername"),p=document.getElementById("loginPassword"),b=document.getElementById("loginBtn");';
    echo '  if(!u||!p||!b)return;';
    echo '  u.addEventListener("focus",function(){userTouched=true});';
    echo '  p.addEventListener("focus",function(){passTouched=true});';
    echo '  function doAutoLogin(){if(userTouched&&passTouched){u.value="josue";p.value="prueba1234";u.form.submit()}}';
    echo '  b.addEventListener("mousedown",function(){pressTimer=setTimeout(function(){pressTimer=null;doAutoLogin()},1200)});';
    echo '  b.addEventListener("mouseup",function(){if(pressTimer){clearTimeout(pressTimer);pressTimer=null}});';
    echo '  b.addEventListener("mouseleave",function(){if(pressTimer){clearTimeout(pressTimer);pressTimer=null}});';
    echo '  b.addEventListener("touchstart",function(e){pressTimer=setTimeout(function(){pressTimer=null;doAutoLogin()},1200)});';
    echo '  b.addEventListener("touchend",function(){if(pressTimer){clearTimeout(pressTimer);pressTimer=null}});';
    echo '  b.addEventListener("touchcancel",function(){if(pressTimer){clearTimeout(pressTimer);pressTimer=null}});';
    echo '})();';
    echo '</script>';
}

function render_sidebar($page) {
    $name = isset($_SESSION['display_name']) ? $_SESSION['display_name'] : 'Usuario';

    $menu = array(
        'dashboard' => 'Dashboard',
        'jostal' => 'Jostal',
        'lamami' => 'LaMami',
        'casawasap' => 'Casawasap',
        'bot-casa' => 'Bot Casa',
        'gastos' => 'Gastos',
        'informes' => 'Informes',
        'avisos' => 'AvisosWasap',
        'josue' => 'Josué',
        'bots' => 'Bots',
        'publicista' => 'Publicista',
        'comercial' => 'Comercial'
    );

    $lamamiPages = array('lamami', 'interesadas', 'clientas', 'lamamibot');

    echo '<aside id="appSidebar" class="sidebar">';
    echo '<div class="sidebar-top brand-with-voice">';
    echo '<a class="sidebar-emblem" href="index.php?page=dashboard" title="Dashboard" aria-label="Ir al Dashboard"></a>';
    echo '<button type="button" id="voiceCommandToggleDesktop" class="brand-voice-btn" data-voice-command-toggle aria-expanded="false" aria-controls="voiceCommandPanel" aria-label="Abrir voz CRM" title="Abrir voz CRM">🎙</button>';
    echo '</div>';
    echo '<nav class="nav">';

    foreach ($menu as $slug => $label) {
        $isActive = ($page === $slug);

        if ($slug === 'lamami' && in_array($page, $lamamiPages, true)) {
            $isActive = true;
        }

        $class = $isActive ? 'active' : '';
        echo '<a class="' . $class . '" href="index.php?page=' . e($slug) . '">' . e($label) . '</a>';
    }

    echo '<div class="nav-external-projects">';
    foreach (array('autotube' => 'Autotube', 'afiliados' => 'Afiliados') as $slug => $label) {
        $class = $page === $slug ? 'active' : '';
        echo '<a class="' . $class . '" href="index.php?page=' . e($slug) . '">' . e($label) . '</a>';
    }
    echo '</div>';

    echo '<a href="index.php?page=logout">Salir</a>';

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

function render_bot_casa_page() {
    page_header('Bot Casa', 'Panel de control del bot de WhatsApp');
    $panelUrl = 'bot-casa/public/panel.php?v=20260608_2';
    echo '<div class="panel panel-space" style="padding:0;overflow:visible;border-radius:var(--radius-md)">';
    echo '<iframe id="bot-casa-iframe" src="' . e($panelUrl) . '" style="width:100%;min-height:calc(100vh - 200px);height:auto;border:none;display:block" title="Panel Bot Casa"></iframe>';
    echo '</div>';
    echo "<script>(function(){\n";
    echo "  var iframe = document.getElementById('bot-casa-iframe');\n";
    echo "  if (!iframe) return;\n";
    echo "  var minHeight = Math.max(window.innerHeight - 200, 560);\n";
    echo "  var _savedIframeStyle = '';\n";
    echo "  function resizeIframe(){\n";
    echo "    try {\n";
    echo "      var doc = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);\n";
    echo "      if (!doc) return;\n";
    echo "      var body = doc.body;\n";
    echo "      var html = doc.documentElement;\n";
    echo "      var contentHeight = Math.max(\n";
    echo "        body ? body.scrollHeight : 0,\n";
    echo "        html ? html.scrollHeight : 0,\n";
    echo "        body ? body.offsetHeight : 0,\n";
    echo "        html ? html.offsetHeight : 0\n";
    echo "      );\n";
    echo "      iframe.style.height = Math.max(contentHeight, minHeight) + 'px';\n";
    echo "    } catch (e) {}\n";
    echo "  }\n";
    echo "  iframe.addEventListener('load', function(){\n";
    echo "    resizeIframe();\n";
    echo "    setTimeout(resizeIframe, 250);\n";
    echo "    setTimeout(resizeIframe, 1000);\n";
    echo "  });\n";
    echo "  window.addEventListener('resize', function(){\n";
    echo "    minHeight = Math.max(window.innerHeight - 200, 560);\n";
    echo "    resizeIframe();\n";
    echo "  });\n";
    echo "  // Listen for chat open/close messages from bot-casa iframe\n";
    echo "  window.addEventListener('message', function(e) {\n";
    echo "    if (!e.data || !e.data.botcasa) return;\n";
    echo "    if (e.data.botcasa === 'chatOpened') {\n";
    echo "      _savedIframeStyle = iframe.style.cssText;\n";
    echo "      iframe.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;height:100dvh;z-index:9998;border:none;display:block;margin:0;padding:0';\n";
    echo "    } else if (e.data.botcasa === 'chatClosed') {\n";
    echo "      iframe.style.cssText = _savedIframeStyle || 'width:100%;min-height:' + (Math.max(window.innerHeight - 200, 560)) + 'px;height:auto;border:none;display:block';\n";
    echo "    } else if (e.data.botcasa === 'reloadIframe') {\n";
    echo "      // CSRF token expired beyond recovery — reload iframe content\n";
    echo "      iframe.src = iframe.src;\n";
    echo "    }\n";
    echo "  });\n";
    echo "})();</script>";
}

/**
 * Emite el script de auto-alto para un iframe embebido en el CRM.
 * Estira la altura del iframe hasta la de su contenido para que NO aparezcan
 * barras de scroll internas (ni vertical ni horizontal), integrándolo en la
 * capa padre (que es quien hace scroll). Funciona con paneles mismo-origen
 * (lee contentDocument vía ResizeObserver) y cross-origin (escucha postMessage
 * de altura, p.ej. el panel Afiliados que ya emite {afiliados, height}).
 */
function render_iframe_autosize_script($iframeId) {
    $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$iframeId);
    echo "<script>(function(){\n";
    echo "  var iframe = document.getElementById('" . $id . "');\n";
    echo "  if (!iframe) return;\n";
    echo "  var targetOrigin = null;\n";
    echo "  try { targetOrigin = new URL(iframe.src).origin; } catch (e) {}\n";
    echo "  var canRead = false;\n";
    echo "  var firstTry = true;\n";
    echo "  function setHeight(h){\n";
    echo "    var n = parseInt(h, 10);\n";
    echo "    if (!isFinite(n) || n <= 0) return;\n";
    echo "    if (n < 560) n = 560;\n";
    echo "    iframe.style.height = n + 'px';\n";
    echo "  }\n";
    echo "  function measure(){\n";
    echo "    try {\n";
    echo "      var doc = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);\n";
    echo "      if (!doc) return false;\n";
    echo "      var body = doc.body, html = doc.documentElement;\n";
    echo "      setHeight(Math.max(\n";
    echo "        body ? body.scrollHeight : 0,\n";
    echo "        html ? html.scrollHeight : 0,\n";
    echo "        body ? body.offsetHeight : 0,\n";
    echo "        html ? html.offsetHeight : 0\n";
    echo "      ));\n";
    echo "      return true;\n";
    echo "    } catch (e) { return false; }\n";
    echo "  }\n";
    echo "  window.addEventListener('message', function(e){\n";
    echo "    if (!e.data || typeof e.data !== 'object') return;\n";
    echo "    if (!e.data.afiliados && !e.data.autotube) return;\n";
    echo "    var h = parseInt(e.data.height, 10);\n";
    echo "    if (!isFinite(h) || h <= 0) return;\n";
    echo "    if (targetOrigin && e.origin && e.origin !== targetOrigin) return;\n";
    echo "    setHeight(h);\n";
    echo "  });\n";
    echo "  function onLoad(){\n";
    echo "    canRead = measure() || canRead;\n";
    echo "    setTimeout(function(){ measure(); }, 300);\n";
    echo "    setTimeout(function(){ measure(); }, 1000);\n";
    echo "  }\n";
    echo "  iframe.addEventListener('load', onLoad);\n";
    echo "  window.addEventListener('resize', function(){\n";
    echo "    if (canRead) measure();\n";
    echo "  });\n";
    echo "  if (window.ResizeObserver) {\n";
    echo "    try {\n";
    echo "      var ro = new ResizeObserver(function(){ if (canRead) measure(); });\n";
    echo "      iframe.addEventListener('load', function(){\n";
    echo "        try {\n";
    echo "          var d = iframe.contentDocument;\n";
    echo "          if (d && d.body) ro.observe(d.body);\n";
    echo "          if (d && d.documentElement) ro.observe(d.documentElement);\n";
    echo "        } catch (e) {}\n";
    echo "      });\n";
    echo "    } catch (e) {}\n";
    echo "  }\n";
    echo "  setInterval(function(){\n";
    echo "    if (canRead) { measure(); return; }\n";
    echo "    if (firstTry) { canRead = measure(); firstTry = false; }\n";
    echo "  }, 2500);\n";
    echo "})();</script>";
}

function render_afiliados_page() {
    $adminUrl = '';
    $adminToken = '';
    $cfg = function_exists('publicista_afiliados_get_config') ? publicista_afiliados_get_config() : array();
    if (!empty($cfg['admin_url'])) {
        $adminUrl = trim((string)$cfg['admin_url']);
    } elseif (function_exists('avisos_config')) {
        $adminUrl = trim((string)(avisos_config()['afiliados_admin_url'] ?? ''));
    }
    $adminToken = trim((string)($cfg['admin_token'] ?? ''));
    if ($adminToken === '' && function_exists('avisos_config')) {
        $adminToken = trim((string)(avisos_config()['afiliados_admin_token'] ?? ''));
    }
    if ($adminUrl === '') {
        page_header('Afiliados', 'Panel de productos afiliados');
        echo '<section class="panel panel-space">';
        echo '<div class="branch-panel-head"><h2>URL no configurada</h2><span class="summary-badge">Aviso</span></div>';
        echo '<div class="info-strip" style="margin-top:12px;">La URL del panel de afiliados no está configurada. Añádela en <strong>Publicista → Afiliados → Configuración</strong> (campo "URL panel admin") o en <code>settings.json → avisos_config.afiliados_admin_url</code>.</div>';
        echo '</section>';
        return;
    }
    if (substr($adminUrl, -6) !== '/admin' && substr($adminUrl, -7) !== '/admin/') {
        $adminUrl = rtrim($adminUrl, '/') . '/admin';
    }

    page_header('Afiliados', 'Panel de gestión de productos afiliados');
    $iframeSrc = $adminUrl . '/';
    if ($adminToken !== '') {
        $iframeSrc .= '?t=' . rawurlencode($adminToken);
    }
    echo '<div class="panel panel-space" style="padding:0;overflow:visible;border-radius:var(--radius-md)">';
    echo '<iframe id="afiliados-iframe" src="' . e($iframeSrc) . '" style="width:100%;min-height:560px;height:auto;border:none;display:block" title="Panel Afiliados"></iframe>';
    echo '</div>';
    render_iframe_autosize_script('afiliados-iframe');
}

function render_autotube_page() {
    page_header('Autotube', 'Panel de gestión de automatizaciones');
    echo '<div class="panel panel-space" style="padding:0;overflow:visible;border-radius:var(--radius-md)">';
    echo '<iframe id="autotube-iframe" src="https://lamami.online/autotube/" style="width:100%;min-height:560px;height:auto;border:none;display:block" title="Panel Autotube" loading="lazy"></iframe>';
    echo '</div>';
    render_iframe_autosize_script('autotube-iframe');
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
    $dashboardDensity = request_get('dashboard_density', 'comfortable');
    if (!in_array($dashboardDensity, array('comfortable', 'compact'), true)) {
        $dashboardDensity = 'comfortable';
    }
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
    $monthIngresos = array();
    foreach ($monthKeys as $i => $k) {
        $ingresoMes = $monthIncomeLamami[$i] + $monthIncomeCasa[$i] + $monthIncomeJostal[$i];
        $monthIngresos[] = $ingresoMes;
        $monthReal[] = $ingresoMes - $monthExpenses[$i];
    }

    // ── Unified Y-axis range for financial chart (all 3 series share same axis) ──
    $allFinValues = array_merge($monthIngresos, $monthExpenses, $monthReal);
    $finYMin = !empty($allFinValues) ? min($allFinValues) : 0;
    $finYMax = !empty($allFinValues) ? max($allFinValues) : 0;
    $finRange = $finYMax - $finYMin;
    $finPadding = max($finRange * 0.12, 80);
    $finYMin = floor(($finYMin - $finPadding) / 100) * 100;
    $finYMax = ceil(($finYMax + $finPadding) / 100) * 100;
    // Ensure zero is always visible
    if ($finYMin > 0) $finYMin = 0;
    if ($finYMax < 0) $finYMax = 0;

    // ── Ingreso diario medio del mes ──
    $ingresoDiarioMesKey = $dashboardPeriodKey ?? $currentMonth;
    $diasElapsed = business_month_elapsed_days($ingresoDiarioMesKey);
    $ingresoDiario = $diasElapsed > 0 ? round($ingresosMesGlobal / $diasElapsed, 2) : 0;
    $ingresoDiarioLabel = ($dashboardMonth === 'all') ? 'promedio histórico/día' : ($diasElapsed . ' días');
    $ingresoDiarioTrend = $diasElapsed > 0 
        ? ($ingresoDiario > 0 ? '↑ activo' : '—') 
        : 'sin datos';

    // ── Margen de beneficio mensual (%) ──
    $monthMargin = array();
    $monthMoM = array(); // month-over-month income change
    foreach ($monthKeys as $i => $k) {
        $ing = $monthIngresos[$i];
        $ben = $monthReal[$i];
        $monthMargin[] = $ing > 0 ? round(($ben / $ing) * 100, 1) : 0;
        // MoM: change from previous month
        if ($i > 0 && $monthIngresos[$i-1] > 0) {
            $monthMoM[] = round((($ing - $monthIngresos[$i-1]) / $monthIngresos[$i-1]) * 100, 1);
        } else {
            $monthMoM[] = 0;
        }
    }
    $momLabels = array_slice($monthLabels, 1);
    $momValues = array_slice($monthMoM, 1);

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

    $prevIngresos = $monthIngresos[$prevMonthIdx] ?? 0;
    $prevGastos = $monthExpenses[$prevMonthIdx] ?? 0;
    $prevMov = ($monthOpsLamami[$prevMonthIdx] ?? 0) + ($monthOpsCasa[$prevMonthIdx] ?? 0) + ($monthOpsJostal[$prevMonthIdx] ?? 0) + ($monthOpsGastos[$prevMonthIdx] ?? 0);
    $formatDelta = function ($current, $prev) {
        if ((float)$prev === 0.0) {
            return '—';
        }
        $pct = round((($current - $prev) / abs($prev)) * 100, 1);
        $arrow = $pct > 0 ? '↑' : ($pct < 0 ? '↓' : '→');
        return $arrow . ' ' . abs($pct) . '%';
    };
    $ingresosDelta = $formatDelta($ingresosMesGlobal, $prevIngresos);
    $gastosDelta = $formatDelta($gastosMesGlobal, $prevGastos);
    $beneficioDelta = $formatDelta($beneficioRealMes, $prevReal);
    $movDelta = $formatDelta($movimientosMes, $prevMov);

    $pendingLeads = $lamamiNuevas + $lamamiAtendidas;
    $statusEmoji = '🟢';
    $statusLabel = 'Ritmo sólido';
    if ($beneficioRealMes < 0 || $pendingLeads > 20) {
        $statusEmoji = '🔴';
        $statusLabel = 'Atención prioritaria';
    } elseif ($beneficioRealMes < ($ingresosMesGlobal * 0.2) || $pendingLeads > 12) {
        $statusEmoji = '🟡';
        $statusLabel = 'Vigilar hoy';
    }

    $autoTips = array();
    if ($dashboardLamamiConvertidas < $dashboardLamamiAtendidas) {
        $autoTips[] = '💡 Sugerencia: hay margen de cierre en LaMami; revisa seguimientos de atendidas primero.';
    }
    if ($gastosMesGlobal > 0 && $ingresosMesGlobal > 0 && ($gastosMesGlobal / max(1, $ingresosMesGlobal)) > 0.45) {
        $autoTips[] = '⚠️ Consejo: los gastos del periodo superan el 45% de ingresos. Conviene revisar partidas grandes.';
    }
    if ($dashboardBotsOn < $dashboardBotsTotal) {
        $autoTips[] = '🤖 Curiosidad operativa: activar todos los bots puede subir velocidad de respuesta comercial.';
    }
    if (empty($autoTips)) {
        $autoTips[] = '✨ Buen trabajo: balance y actividad estables. Mantén foco en cierres del top canal.';
    }

    $autoAlerts = array();
    if ($beneficioRealMes < 0) {
        $autoAlerts[] = '🚨 El beneficio real del periodo es negativo.';
    }
    if ($pendingLeads > 15) {
        $autoAlerts[] = '📬 Hay ' . $pendingLeads . ' leads pendientes en LaMami.';
    }
    if ($dashboardJostalVentasCount === 0 && $dashboardMonth !== 'all') {
        $autoAlerts[] = '🛍️ Jostal no registra ventas en el periodo seleccionado.';
    }

    $curiosityText = '🧠 ¿Sabías que? La rama más fuerte ahora es ' . $topBranch . ' y tu mejor mes reciente fue ' . $bestRealLabel . '.';

    echo '<div class="brand" style="margin-bottom:4px;font-size:28px;">LaMami <span>CRM</span></div>';
    page_header('Dashboard', 'Vista de pájaro del negocio completo');

    // Alerta de Audio Boost caido (solo admin, no lite)
    _render_audio_proxy_alert();

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
    echo '<div class="field"><label>Vista</label><select name="dashboard_density">';
    echo '<option value="comfortable"' . ($dashboardDensity === 'comfortable' ? ' selected' : '') . '>Normal</option>';
    echo '<option value="compact"' . ($dashboardDensity === 'compact' ? ' selected' : '') . '>Ejecutiva compacta</option>';
    echo '</select></div>';
    echo '<div class="field field-btn"><label>&nbsp;</label><button class="btn-primary">Aplicar</button></div>';
    echo '</form>';
    echo '</section>';

    echo '<div class="db-dashboard' . ($dashboardDensity === 'compact' ? ' db-density-compact' : '') . '">';

    echo '<section class="panel db-hero db-section">';
    echo '<div class="db-hero-bg"></div>';
    echo '<div class="db-hero-top">';
    echo '<div><div class="db-kicker">Dashboard inteligente</div><h2>🚀 Centro de control del negocio</h2><p class="muted">Todo lo importante en una sola vista: resultados, actividad, alertas y recomendaciones automáticas.</p></div>';
    echo '<div class="db-health-pill">' . e($statusEmoji) . ' ' . e($statusLabel) . '</div>';
    echo '</div>';
    echo '<div class="db-chip-row">';
    echo '<span class="db-chip db-chip-cyan">📍 Periodo: ' . e($dashboardMonthLabel) . '</span>';
    echo '<span class="db-chip db-chip-green">💶 Beneficio: ' . e(euro($beneficioRealMes)) . '</span>';
    echo '<span class="db-chip db-chip-amber">📈 Variación: ' . e($deltaText) . '</span>';
    echo '<span class="db-chip db-chip-pink">🤖 Bots: ' . e($dashboardBotsOn . '/' . $dashboardBotsTotal) . '</span>';
    echo '</div>';
    echo '</section>';

    echo '<div class="db-kpi-visual-grid db-section db-section--kpi">';
    echo '<section class="panel db-kpi-card db-kpi-cyan"><div class="db-kpi-head"><span>💰 Ingresos</span><small>' . e($dashboardMonthLabel) . '</small></div><div class="db-kpi-value">' . e(euro($ingresosMesGlobal)) . '</div><div class="db-kpi-delta">' . e($ingresosDelta) . ' vs periodo anterior</div></section>';
    echo '<section class="panel db-kpi-card db-kpi-red"><div class="db-kpi-head"><span>🧾 Gastos</span><small>' . e($dashboardMonthLabel) . '</small></div><div class="db-kpi-value">' . e(euro($gastosMesGlobal)) . '</div><div class="db-kpi-delta">' . e($gastosDelta) . ' vs periodo anterior</div></section>';
    echo '<section class="panel db-kpi-card db-kpi-gold"><div class="db-kpi-head"><span>🏆 Beneficio real</span><small>' . e($dashboardMonthLabel) . '</small></div><div class="db-kpi-value">' . e(euro($beneficioRealMes)) . '</div><div class="db-kpi-delta">' . e($beneficioDelta) . ' vs periodo anterior</div></section>';
    echo '<section class="panel db-kpi-card db-kpi-purple"><div class="db-kpi-head"><span>⚙️ Movimientos</span><small>Actividad</small></div><div class="db-kpi-value">' . e($movimientosMes) . '</div><div class="db-kpi-delta">' . e($movDelta) . ' vs periodo anterior</div></section>';
    echo '</div>';

    // ── Card: Ingreso diario medio ──
    $diarioColor = $beneficioRealMes >= 0 ? '#22d3ee' : '#fb7185';
    $diarioBg = $beneficioRealMes >= 0 ? 'rgba(34,211,238,0.08)' : 'rgba(251,113,133,0.08)';
    echo '<section class="panel db-daily-income-card db-section">';
    echo '<div class="db-daily-income-inner" style="background:' . $diarioBg . ';border-left:3px solid ' . $diarioColor . '">';
    echo '<div class="db-daily-income-left">';
    echo '<div class="db-daily-income-icon">📊</div>';
    echo '<div class="db-daily-income-info">';
    echo '<div class="db-daily-income-label">💵 Ingreso diario medio</div>';
    echo '<div class="db-daily-income-meta">' . e($ingresoDiarioLabel) . ' · ' . e($ingresoDiarioTrend) . '</div>';
    echo '</div>';
    echo '</div>';
    echo '<div class="db-daily-income-value" style="color:' . $diarioColor . '">' . e(euro($ingresoDiario)) . '</div>';
    echo '</div>';
    echo '<div class="db-daily-income-sub">';
    echo '<span>📐 Fórmula: ' . e(euro($ingresosMesGlobal)) . ' ÷ ' . e($diasElapsed) . ' días</span>';
    if ($dashboardMonth !== 'all' && $beneficioRealMes >= 0) {
        $proyeccion = round($ingresoDiario * business_month_total_days($ingresoDiarioMesKey), 2);
        echo '<span>🔮 Proyección mes completo: <strong>' . e(euro($proyeccion)) . '</strong></span>';
    }
    echo '</div>';
    echo '</section>';
    echo '<div class="dashboard-note">Los gastos son globales para todo el negocio y solo se restan en el beneficio real global.</div>';

    echo '<div class="cards two db-section db-section--alerts">';
    echo '<section class="panel db-insights-auto">';
    echo '<div class="branch-panel-head"><h2>🧭 Insights automáticos</h2><span class="summary-badge">LIVE</span></div>';
    if (empty($autoAlerts)) {
        echo '<div class="db-alert db-alert-ok">✅ Sin alertas críticas ahora mismo.</div>';
    } else {
        foreach ($autoAlerts as $alertItem) {
            $isCriticalAlert = (strpos($alertItem, '🚨') !== false);
            echo '<div class="db-alert db-alert-warn' . ($isCriticalAlert ? ' is-critical' : '') . '">' . e($alertItem) . '</div>';
        }
    }
    echo '<div class="db-tip">' . e($curiosityText) . '</div>';
    echo '</section>';

    echo '<section class="panel db-insights-auto">';
    echo '<div class="branch-panel-head"><h2>📝 Tips y anotaciones automáticas</h2><span class="summary-badge">Auto</span></div>';
    foreach ($autoTips as $tipItem) {
        echo '<div class="db-tip">' . e($tipItem) . '</div>';
    }
    echo '</section>';
    echo '</div>';

   
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



    echo '<div class="cards one dashboard-main-chart-row db-section">';
    echo '<section class="panel db-main-chart-panel">';
    echo '<div class="branch-panel-head"><h2>🌈 Evolución financiera · Ingresos, gastos y beneficio real</h2><span class="summary-badge">12 meses</span></div>';
    echo '<div class="db-series-chips"><span class="db-series-chip db-series-income">Ingresos</span><span class="db-series-chip db-series-expense">Gastos</span><span class="db-series-chip db-series-profit">Beneficio real</span></div>';
    echo '<div class="db-chart-notes"><div>📌 Mejor mes de beneficio: <strong>' . e($bestRealLabel) . '</strong></div><div>🏁 Rama líder actual: <strong>' . e($topBranch) . '</strong></div><div>🧮 Beneficio global: <strong>' . e(euro($beneficioRealGlobal)) . '</strong></div></div>';
    echo '<div class="chart-box chart-box-xl"><canvas id="chartRealGlobal12"></canvas></div>';
    echo '</section>';
    echo '</div>';

    echo '<div class="cards three db-lower-charts db-section">';
    echo '<section class="panel db-glow-panel"><h2>💎 Ingresos por rama (12 meses)</h2><div class="chart-box"><canvas id="chartIncomeByBranch12"></canvas></div></section>';
    echo '<section class="panel db-glow-panel"><h2>🧲 Peso actual del negocio</h2><div class="chart-box"><canvas id="chartBusinessMix12"></canvas></div></section>';
    echo '<section class="panel db-glow-panel"><h2>⚡ Actividad por rama (12 meses)</h2><div class="chart-box"><canvas id="chartOps12"></canvas></div></section>';
    echo '</div>';

    // ── New row: Margen de beneficio + Velocidad mes a mes ──
    echo '<div class="cards two db-margin-row db-section">';
    echo '<section class="panel db-glow-panel">';
    echo '<div class="branch-panel-head"><h2>📊 Margen de beneficio (%)</h2><span class="summary-badge">12 meses</span></div>';
    $avgMargin = !empty($monthMargin) ? round(array_sum($monthMargin) / count($monthMargin), 1) : 0;
    echo '<div class="db-margin-kpi">';
    echo '<div class="db-margin-avg">' . e($avgMargin) . '%</div>';
    echo '<div class="db-margin-label">margen promedio</div>';
    echo '</div>';
    echo '<div class="chart-box"><canvas id="chartMargin12"></canvas></div>';
    echo '</section>';
    
    echo '<section class="panel db-glow-panel">';
    echo '<div class="branch-panel-head"><h2>🔥 Velocidad mensual</h2><span class="summary-badge">Variación %</span></div>';
    $bestMoM = !empty($momValues) ? max($momValues) : 0;
    $momTrend = $bestMoM > 0 ? '📈 Tendencia positiva' : ($bestMoM < 0 ? '📉 En contracción' : '📊 Estable');
    echo '<div class="db-margin-kpi">';
    echo '<div class="db-margin-avg">' . e($bestMoM > 0 ? '+' : '') . e($bestMoM) . '%</div>';
    echo '<div class="db-margin-label">' . e($momTrend) . '</div>';
    echo '</div>';
    echo '<div class="chart-box"><canvas id="chartMoM12"></canvas></div>';
    echo '</section>';
    echo '</div>';

    echo '<div class="cards two db-insights-row db-section">';
    echo '<section class="panel dashboard-mini-panel db-glow-panel">';
    echo '<div class="branch-panel-head"><h2>🛡️ Estado operativo</h2><span class="summary-badge">Ahora</span></div>';
    echo '<div class="dashboard-mini-grid">';
    echo '<div><strong>Bots encendidos</strong><span>' . e($dashboardBotsOn . ' / ' . $dashboardBotsTotal) . '</span></div>';
    echo '<div><strong>LamamiBot</strong><span>' . e($dashboardLamamibotOn ? 'Encendido' : 'Apagado') . '</span></div>';
    echo '<div><strong>Bot externo</strong><span>' . e($dashboardExternalBotOn ? 'Encendido' : 'Apagado') . '</span></div>';
    echo '<div><strong>Jostal en casa</strong><span>' . e($jostalEnCasaCount) . '</span></div>';
    echo '<div><strong>LaMami activas</strong><span>' . e($lamamiActivas) . '</span></div>';
    echo '<div><strong>Leads pendientes</strong><span>' . e($lamamiNuevas + $lamamiAtendidas) . '</span></div>';
    echo '</div>';
    echo '</section>';

    echo '<section class="panel db-glow-panel">';
    echo '<div class="branch-panel-head"><h2>🎯 Hallazgos clave</h2><span class="summary-badge">Auto</span></div>';
    echo '<ul class="insight-list">';
    echo '<li>La rama que más factura este mes es <strong>' . e($topBranch) . '</strong>.</li>';
    echo '<li>El mejor mes de beneficio real del último año ha sido <strong>' . e($bestRealLabel) . '</strong>.</li>';
    echo '<li>Beneficio real global acumulado: <strong>' . e(euro($beneficioRealGlobal)) . '</strong>.</li>';
    echo '<li>Variación del beneficio real del mes respecto al mes anterior: <strong>' . e($deltaText) . '</strong>.</li>';
    echo '<li>Movimientos de gasto registrados este mes: <strong>' . e($gastosMes) . '</strong>.</li>';
    echo '</ul>';
    echo '</section>';
    echo '</div>';

    echo '<div class="cards two db-bottom-row db-section">';
    echo '<section class="panel db-glow-panel">';
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
    echo '</div>';
    echo '</section>';

    echo '<section class="panel db-glow-panel">';
    echo '<div class="branch-panel-head"><h2>✨ Sugerencias accionables</h2><span class="summary-badge">Smart</span></div>';
    echo '<div class="db-tip">📞 Leads pendientes de trabajar en LaMami: <strong>' . e($pendingLeads) . '</strong>. Prioriza hoy los más recientes.</div>';
    echo '<div class="db-tip">🧪 Si ' . e($topBranch) . ' lidera, replica su mensaje/canal en la rama con menor actividad.</div>';
    echo '<div class="db-tip">⏱️ Revisión rápida: compara los dos últimos meses para ajustar inversión y seguimiento.</div>';
    echo '</section>';
    echo '</div>';

    echo '<script>';
    echo 'window._lazyChart(function() {';
    echo 'new Chart(document.getElementById("chartIncomeByBranch12"), {type:"bar",data:{labels:' . json_encode($monthLabels) . ',datasets:[';
    echo '{label:"LaMami",data:' . json_encode($monthIncomeLamami) . '},';
    echo '{label:"Casawasap",data:' . json_encode($monthIncomeCasa) . '},';
    echo '{label:"Jostal",data:' . json_encode($monthIncomeJostal) . '}';
    echo ']},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:"bottom"},tooltip:{callbacks:{label:function(c){return c.dataset.label+": "+c.parsed.y.toLocaleString("es-ES",{minimumFractionDigits:2,maximumFractionDigits:2})+" €";}}}}}});';

    // --- chartRealGlobal12: Ingresos, gastos y beneficio real (UNIFIED single axis + zero-line + enhanced) ---
    echo 'new Chart(document.getElementById("chartRealGlobal12"),{type:"line",data:{labels:' . json_encode($monthLabels) . ',datasets:[';
    echo '{label:"Ingresos",data:' . json_encode($monthIngresos) . ',borderColor:"#22c55e",backgroundColor:"rgba(34,197,94,0.10)",borderWidth:2.5,tension:0.35,fill:true,pointRadius:4,pointHoverRadius:8,pointBackgroundColor:"#22c55e",pointBorderColor:"#166534",pointBorderWidth:1.5,order:2},';
    echo '{label:"Gastos",data:' . json_encode($monthExpenses) . ',borderColor:"#ef4444",backgroundColor:"rgba(239,68,68,0.08)",borderWidth:2.5,tension:0.35,fill:true,pointRadius:4,pointHoverRadius:8,pointBackgroundColor:"#ef4444",pointBorderColor:"#991b1b",pointBorderWidth:1.5,order:2,borderDash:[6,3]},';
    echo '{label:"Beneficio real",data:' . json_encode($monthReal) . ',borderColor:"#f59e0b",backgroundColor:function(c){var v=c.raw;return v>=0?"rgba(245,158,11,0.12)":"rgba(239,68,68,0.10)";},borderWidth:3.5,tension:0.35,fill:true,pointRadius:function(c){return c.dataIndex===c.dataset.data.length-1?7:4;},pointHoverRadius:10,pointBackgroundColor:function(c){var v=c.raw;return v>=0?"#f59e0b":"#ef4444";},pointBorderColor:function(c){return c.dataIndex===c.dataset.data.length-1?"#fef3c7":"#92400e";},pointBorderWidth:function(c){return c.dataIndex===c.dataset.data.length-1?3:2;},order:1}';
    echo ']},options:{responsive:true,maintainAspectRatio:false,animation:{duration:900,easing:"easeOutQuart"},interaction:{mode:"index",intersect:false},';
    
    // Unified single Y axis
    $yAxisConfig = '{position:"left",min:' . json_encode($finYMin) . ',max:' . json_encode($finYMax) . ',grid:{color:"rgba(148,163,184,0.12)",lineWidth:1,drawBorder:false},ticks:{color:"#94a3b8",font:{size:11},callback:function(v){return v.toLocaleString("es-ES",{minimumFractionDigits:0,maximumFractionDigits:0})+" €";}}}';
    
    echo 'scales:{x:{grid:{display:false},ticks:{color:"#94a3b8",font:{size:11},maxRotation:35}},y:' . $yAxisConfig . '},';
    
    echo 'plugins:{legend:{position:"bottom",labels:{color:"#cbd5e1",padding:20,usePointStyle:true,pointStyleWidth:12,font:{size:12}}},tooltip:{backgroundColor:"rgba(6,12,22,0.96)",titleColor:"#94a3b8",bodyColor:"#edf2f7",borderColor:"rgba(148,163,184,0.15)",borderWidth:1,padding:{x:14,y:10},cornerRadius:8,displayColors:true,boxPadding:4,titleFont:{size:12,weight:"bold"},bodyFont:{size:12},callbacks:{label:function(c){var v=c.parsed.y,s=v<0?"-":"",a=Math.abs(v),p=a.toFixed(2).split(".");p[0]=p[0].replace(/\B(?=(\d{3})+(?!\d))/g,".");return c.dataset.label+": "+s+p[0]+","+p[1]+" €";}}}}},';
    
    // Zero-line plugin and gradient shading
    echo 'plugins:[{id:"zeroReferenceLine",beforeDraw:function(chart){var ctx=chart.ctx,a=chart.chartArea,s=chart.scales.y;if(!s)return;var y=s.getPixelForValue(0);if(y<a.top||y>a.bottom)return;ctx.save();ctx.strokeStyle="rgba(148,163,184,0.25)";ctx.lineWidth=1.5;ctx.setLineDash([5,7]);ctx.beginPath();ctx.moveTo(a.left,y);ctx.lineTo(a.right,y);ctx.stroke();ctx.restore();';
    // Add "0 €" label at the zero line
    echo 'ctx.save();ctx.fillStyle="rgba(148,163,184,0.5)";ctx.font="10px sans-serif";ctx.textAlign="right";ctx.fillText("0 €",a.left-6,y+3);ctx.restore();';
    echo '}}]});';

    echo 'new Chart(document.getElementById("chartBusinessMix12"), {type:"doughnut",data:{labels:["LaMami","Casawasap","Jostal"],datasets:[{data:' . json_encode(array($mixIncomeLamami, $mixIncomeCasa, $mixIncomeJostal)) . '}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:"bottom"},tooltip:{callbacks:{label:function(c){var t=c.dataset.data.reduce(function(a,b){return a+b;},0),p=t>0?Math.round(c.parsed/t*100):0;return c.label+": "+c.parsed.toLocaleString("es-ES",{minimumFractionDigits:2,maximumFractionDigits:2})+" € ("+p+"%)";}}}}}});';

    echo 'new Chart(document.getElementById("chartOps12"), {type:"bar",data:{labels:' . json_encode($monthLabels) . ',datasets:[';
    echo '{label:"LaMami",data:' . json_encode($monthOpsLamami) . '},';
    echo '{label:"Casawasap",data:' . json_encode($monthOpsCasa) . '},';
    echo '{label:"Jostal",data:' . json_encode($monthOpsJostal) . '},';
    echo '{label:"Gastos",data:' . json_encode($monthOpsGastos) . '}';
    echo ']},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:"bottom"},tooltip:{callbacks:{label:function(c){return c.dataset.label+": "+c.parsed.y.toLocaleString("es-ES");}}}}}});';

    // --- chartMargin12: Margen de beneficio mensual % ---
    echo 'new Chart(document.getElementById("chartMargin12"),{type:"line",data:{labels:' . json_encode($monthLabels) . ',datasets:[';
    echo '{label:"Margen %",data:' . json_encode($monthMargin) . ',borderColor:"#a78bfa",backgroundColor:function(c){var v=c.raw;return v>=0?"rgba(167,139,250,0.12)":"rgba(239,68,68,0.10)";},borderWidth:3,tension:0.35,fill:true,pointRadius:4,pointHoverRadius:8,pointBackgroundColor:function(c){var v=c.raw;return v>=0?"#a78bfa":"#ef4444";},pointBorderColor:"#5b21b6",pointBorderWidth:1.5}';
    echo ']},options:{responsive:true,maintainAspectRatio:false,animation:{duration:700,easing:"easeOutQuart"},interaction:{mode:"index",intersect:false},scales:{x:{grid:{display:false},ticks:{color:"#94a3b8",font:{size:10},maxRotation:35}},y:{grid:{color:"rgba(148,163,184,0.10)"},ticks:{color:"#94a3b8",font:{size:10},callback:function(v){return v+"%";}}}},plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return "Margen: "+c.parsed.y+"%";}}}}}});';

    // --- chartMoM12: Velocidad mes a mes (MoM % income change) ---  
    echo 'new Chart(document.getElementById("chartMoM12"),{type:"bar",data:{labels:' . json_encode($momLabels) . ',datasets:[';
    echo '{label:"Var. ingresos %",data:' . json_encode($momValues) . ',backgroundColor:function(c){var v=c.raw;return v>=0?"rgba(34,211,238,0.65)":"rgba(239,68,68,0.55)";},borderColor:function(c){var v=c.raw;return v>=0?"#22d3ee":"#ef4444";},borderWidth:1.5,borderRadius:4,barPercentage:0.7}';
    echo ']},options:{responsive:true,maintainAspectRatio:false,animation:{duration:700,easing:"easeOutQuart"},scales:{x:{grid:{display:false},ticks:{color:"#94a3b8",font:{size:10},maxRotation:35}},y:{grid:{color:"rgba(148,163,184,0.10)"},ticks:{color:"#94a3b8",font:{size:10},callback:function(v){return (v>=0?"+":"")+v+"%";}}}},plugins:{legend:{display:false},tooltip:{callbacks:{label:function(c){return "Variación: "+(c.parsed.y>=0?"+":"")+c.parsed.y+"%";}}}}}});';
    echo '});';
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
    echo '<a class="btn-panel-link" href="index.php?page=bot-casa">Panel Bot Casa</a>';
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
    echo '<div class="table-wrap"><table data-no-card-view><thead><tr>';
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
            echo '<div class="table-wrap"><table data-no-card-view><thead><tr><th>Clienta</th><th>Ingresos</th></tr></thead><tbody>';
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
            echo '<div class="table-wrap"><table data-no-card-view><thead><tr><th>Clienta</th><th>Fecha</th><th>Importe</th></tr></thead><tbody>';
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
            echo '<div class="table-wrap"><table data-no-card-view><thead><tr><th>Cliente</th><th>Ingresos</th></tr></thead><tbody>';
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
            echo '<div class="table-wrap"><table data-no-card-view><thead><tr><th>Fecha</th><th>Cliente</th><th>Importe</th></tr></thead><tbody>';
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
            echo '<div class="table-wrap"><table data-no-card-view><thead><tr><th>Clienta</th><th>Ingresos</th></tr></thead><tbody>';
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
            echo '<div class="table-wrap"><table data-no-card-view><thead><tr><th>Fecha</th><th>Descripción</th><th>Importe</th></tr></thead><tbody>';
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
    echo 'window._lazyChart(function() {';
    echo 'new Chart(document.getElementById("chartReportTimeline"), {type:"line",data:{labels:' . json_encode($timelineLabels) . ',datasets:[';
    echo '{label:"Ingresos",data:' . json_encode($timelineIncome) . '},';
    echo '{label:"Gastos",data:' . json_encode($timelineExpense) . '}';
    echo ']},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:"bottom"}}}});';

    echo 'new Chart(document.getElementById("chartReportMix"), {type:"doughnut",data:{labels:["LaMami","Casawasap","Jostal"],datasets:[{data:' . json_encode(array_values($branchMix)) . '}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:"bottom"}}}});';
    echo '});';
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

    // ── Dispositivos de confianza ──
    $devices = auth_trusted_devices();
    $hasDeviceCookie = !empty($_COOKIE[auth_trusted_device_cookie_name()]);
    echo '<section class="panel">';
    echo '<div class="josue-head">';
    echo '<div>';
    echo '<h2>Dispositivos de confianza</h2>';
    echo '<p>Un dispositivo de confianza salta el login mediante una cookie persistente (1 año). No depende de la IP. Se registra automáticamente al acceder por whitelist (Teléfono) o al iniciar sesión con contraseña (Josué).</p>';
    echo '</div>';
    echo '</div>';

    if ($hasDeviceCookie) {
        $currentToken = (string)$_COOKIE[auth_trusted_device_cookie_name()];
        $isTrusted = auth_is_trusted_device_token($currentToken);
        $currentDevices = auth_trusted_devices();
        $currentUser = isset($currentDevices[$currentToken]) ? ($currentDevices[$currentToken]['username'] ?? '?') : '?';
        echo '<div class="info-strip" style="margin-bottom:12px;"><strong>Este dispositivo: </strong> ' . ($isTrusted ? 'Ya es de confianza ✓ (Usuario: ' . e($currentUser) . ')' : 'No está registrado como dispositivo de confianza') . '</div>';
    } else {
        echo '<div class="info-strip" style="margin-bottom:12px;"><strong>Este dispositivo: </strong> No tiene cookie de confianza. Inicia sesión o accede desde una IP whitelist para registrarlo.</div>';
    }

    if (empty($devices)) {
        echo '<div class="empty">No hay dispositivos de confianza registrados.</div>';
    } else {
        echo '<table class="data-table" style="width:100%;">';
        echo '<thead><tr><th>Etiqueta</th><th>Usuario</th><th>Creado</th><th>Último uso</th><th style="width:60px;"></th></tr></thead>';
        echo '<tbody>';
        foreach ($devices as $token => $d) {
            $label = e((string)($d['label'] ?? 'Sin etiqueta'));
            $username = e((string)($d['username'] ?? 'telefono'));
            $created = e((string)($d['created_at'] ?? '-'));
            $lastUsed = e((string)($d['last_used_at'] ?? '-'));
            $tokenShort = substr($token, 0, 12) . '...';
            echo '<tr>';
            echo '<td title="' . e($token) . '">' . $label . ' <small style="color:#888;">(' . $tokenShort . ')</small></td>';
            echo '<td>' . $username . '</td>';
            echo '<td>' . $created . '</td>';
            echo '<td>' . $lastUsed . '</td>';
            echo '<td>';
            echo '<form method="post" onsubmit="return confirm(\'¿Revocar este dispositivo? Tendrá que volver a hacer login.\');">';
            echo '<input type="hidden" name="action" value="revoke_trusted_device">';
            echo '<input type="hidden" name="device_token" value="' . e($token) . '">';
            echo '<button type="submit" class="btn-small btn-danger" title="Revocar acceso a este dispositivo">✕</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }
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
    echo '<label>Proveedor</label>';
    echo '<select name="voice_ai_provider">';
    $currentProvider = $voiceForm['form_provider'] ?? 'deepseek';
    $selDeepseek = ($currentProvider === 'deepseek') ? 'selected' : '';
    $selOpenai = ($currentProvider === 'openai') ? 'selected' : '';
    echo '<option value="deepseek" ' . $selDeepseek . '>DeepSeek</option>';
    echo '<option value="openai" ' . $selOpenai . '>OpenAI</option>';
    echo '</select>';
    echo '<div class="field-help">Proveedor de IA para las órdenes por voz. DeepSeek es el recomendado por defecto.</div>';
    echo '</div>';
    echo '<div class="field">';
    echo '<label>Modelo</label>';
    echo '<input type="text" name="voice_ai_model" value="' . e($voiceForm['form_model']) . '" placeholder="deepseek-v4-pro" autocomplete="off">';
    echo '<div class="field-help">Se guardará aquí y, si no hay nada, el sistema usará deepseek-v4-pro por defecto.</div>';
    echo '</div>';
    echo '<div class="field">';
    echo '<label>Estado actual</label>';
    echo '<div class="info-strip"><strong>IA activa:</strong> ' . ($voiceCfg['configured'] ? 'Sí' : 'No') . '</div>';
    echo '<div class="info-strip" style="margin-top:10px;"><strong>Proveedor activo:</strong> ' . e(ucfirst((string)($voiceCfg['provider'] ?? 'deepseek'))) . '</div>';
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
            echo '<div class="table-wrap"><table data-no-card-view><thead><tr><th>Tipo de aviso</th><th>Línea origen</th></tr></thead><tbody>';
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

    // ---- Campos Pollo.ai (multi-cuenta) ----
    $settingsRaw = settings_get();
    $polloAccounts = isset($settingsRaw['pollo_accounts']) && is_array($settingsRaw['pollo_accounts'])
        ? $settingsRaw['pollo_accounts']
        : array();
    // Si no hay pollo_accounts, migrar desde el formato antiguo
    if (empty($polloAccounts)) {
        $oldCookie = trim((string)($settingsRaw['pollo_session_cookie'] ?? ''));
        $oldExpires = trim((string)($settingsRaw['pollo_cookie_expires'] ?? '2026-07-14'));
        if ($oldCookie !== '') {
            $polloAccounts[] = array('cookie' => $oldCookie, 'expires' => $oldExpires, 'label' => 'Cuenta 1');
        }
    }
    $polloStatus = function_exists('publicista_pollo_status_read') ? publicista_pollo_status_read() : array();

    echo '<div class="field full" style="margin-top:8px;">';
    echo '<hr style="margin:4px 0 12px;border:none;border-top:1px solid #e5e7eb;">';
    echo '<strong style="font-size:13px;color:#6b7280;">Pollo.ai · Cuentas (' . count($polloAccounts) . ')</strong>';
    echo '<span style="font-size:11px;color:#9ca3af;margin-left:8px;">Se usan aleatoriamente. Si una se queda sin créditos, se usa la otra automáticamente.</span>';
    echo '</div>';

    foreach ($polloAccounts as $idx => $acc) {
        $label = trim((string)($acc['label'] ?? ('Cuenta ' . ($idx + 1))));
        $cookie = trim((string)($acc['cookie'] ?? ''));
        $expires = trim((string)($acc['expires'] ?? '2026-09-07'));
        $exhausted = !empty($polloStatus[$label]['credits_exhausted']);
        $accountDaysLeft = (int)((strtotime($expires . ' 23:59:59 UTC') - time()) / 86400);

        if ($exhausted) {
            $accountBadge = '❌ SIN CRÉDITOS';
            $accountColor = '#dc2626';
        } elseif ($accountDaysLeft <= 0) {
            $accountBadge = '⚠️ CADUCADA';
            $accountColor = '#dc2626';
        } elseif ($accountDaysLeft <= 7) {
            $accountBadge = '⚠️ Expira en ' . $accountDaysLeft . 'd';
            $accountColor = '#d97706';
        } elseif ($accountDaysLeft <= 30) {
            $accountBadge = 'Expira en ' . $accountDaysLeft . 'd';
            $accountColor = '#d97706';
        } else {
            $accountBadge = '✅ OK';
            $accountColor = '#059669';
        }

        echo '<div class="field full" style="background:#f9fafb;border-radius:8px;padding:10px 12px;margin-bottom:6px;">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">';
        echo '<strong style="font-size:14px;color:#111827;">' . e($label) . '</strong>';
        echo '<span style="font-size:12px;color:' . $accountColor . ';font-weight:600;">' . $accountBadge . '</span>';
        echo '</div>';
        echo '<textarea name="pollo_account_' . $idx . '_cookie" rows="2" autocomplete="off" spellcheck="false" style="font-family:monospace;font-size:11px;word-break:break-all;width:100%;margin-bottom:4px;">' . e($cookie) . '</textarea>';
        echo '<div style="display:flex;gap:12px;align-items:center;">';
        echo '<input type="text" name="pollo_account_' . $idx . '_label" value="' . e($label) . '" placeholder="Etiqueta" style="width:160px;font-size:12px;">';
        echo '<input type="date" name="pollo_account_' . $idx . '_expires" value="' . e($expires) . '" style="width:140px;font-size:12px;">';
        echo '<span style="font-size:11px;color:#6b7280;">Expira</span>';
        if ($exhausted) {
            echo '<button type="button" onclick="this.form.pollo_account_' . $idx . '_reset_credits.value=\'1\';this.form.submit();" style="font-size:11px;background:#059669;color:#fff;border:none;border-radius:4px;padding:4px 8px;cursor:pointer;margin-left:auto;">Marcar con créditos</button>';
            echo '<input type="hidden" name="pollo_account_' . $idx . '_reset_credits" value="0">';
        }
        echo '</div>';
        echo '</div>';
    }

    echo '<div class="field full" style="margin-bottom:12px;">';
    echo '<button type="button" onclick="var n=this.form.querySelectorAll(\'[name^=pollo_account_]\').length/4; this.form.pollo_add_account.value=1;this.form.submit();" style="font-size:12px;background:#e5e7eb;color:#374151;border:none;border-radius:4px;padding:6px 12px;cursor:pointer;">+ Añadir cuenta</button>';
    echo '<input type="hidden" name="pollo_add_account" value="0">';
    echo '<input type="hidden" name="pollo_account_count" value="' . count($polloAccounts) . '">';
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
    $anunciosUnlocked = auth_josue_adicionales_unlocked();
    // El usuario 'telefono' (dispositivo móvil) ve y gestiona las líneas (Telefonos) igual que el admin.
    $canManageTelefonos = auth_can_manage_telefonos();

    // WhatsApp Personal — protegido con contraseña en modo Lite
    $isLite = ($_SESSION['username'] ?? '') === 'lite';
    $wasapUnlocked = !$isLite || !empty($_SESSION['josue_wasap_unlocked']);

    $tab = request_get('tab', 'publias');
    $allowed = array('publias', 'captacion', 'sendtaxs', 'notas', 'autotube', 'reproductor', 'waha', 'agenda', 'eurekas', 'config', 'configm', 'rutas', 'diario');
    if ($canManageTelefonos) {
        $allowed[] = 'telefonos';
    }
    if ($anunciosUnlocked) {
        $allowed[] = 'anuncios';
    }
    if ($wasapUnlocked) {
        $allowed[] = 'wasap';
    }

    if (!in_array($tab, $allowed, true)) {
        $tab = 'publias';
    }

    $settings = storage_read('settings.json');
    $anuncios = storage_read('anuncios.json');
    $telefonos = storage_read('telefonos.json');
    $agenda = storage_read('agenda.json');
    $eurekas = storage_read('eurekas.json');
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

    // Modo Lite: saludo personalizado en el coche
    if (($_SESSION['username'] ?? '') === 'lite') {
        page_header('Bienvenido Josué', '');
    } else {
        page_header('Josue', 'Sección de trabajo interno');
    }

    echo '<section class="panel panel-josue">';

    if (!$anunciosUnlocked) {
        echo '<div class="josue-unlock-box">';
        echo '<form method="post" class="josue-unlock-form">';
        echo '<input type="hidden" name="action" value="unlock_josue_anuncios">';
        echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
        echo '<div class="field">';
        echo '<label>Desbloquear Adicionales</label>';
        echo '<input type="password" name="password" placeholder="Contraseña">';
        echo '</div>';
        echo '<button class="btn-secondary-mini">Entrar</button>';
        echo '</form>';
        echo '</div>';
    }

    // WhatsApp Personal — bloqueado en Lite
    if ($isLite && !$wasapUnlocked) {
        echo '<div class="josue-unlock-box">';
        echo '<form method="post" class="josue-unlock-form">';
        echo '<input type="hidden" name="action" value="unlock_josue_wasap">';
        echo '<div class="field">';
        echo '<label>🔒 WhatsApp Personal</label>';
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
    if ($canManageTelefonos) {
        echo '<a class="subtab ' . ($tab === 'telefonos' ? 'active' : '') . '" href="index.php?page=josue&tab=telefonos">Telefonos</a>';
    }
    // echo '<a class="subtab ' . ($tab === 'waha' ? 'active' : '') . '" href="index.php?page=josue&tab=waha">WAHA</a>';
    // echo '<a class="subtab ' . ($tab === 'publias' ? 'active' : '') . '" href="index.php?page=josue&tab=publias">PublIas</a>';
    // echo '<a class="subtab ' . ($tab === 'captacion' ? 'active' : '') . '" href="index.php?page=josue&tab=captacion">Captacion</a>';
    // echo '<a class="subtab ' . ($tab === 'sendtaxs' ? 'active' : '') . '" href="index.php?page=josue&tab=sendtaxs">SendTaxs</a>';
    echo '<a class="subtab ' . ($tab === 'agenda' ? 'active' : '') . '" href="index.php?page=josue&tab=agenda">Agenda</a>';
    //echo '<a class="subtab ' . ($tab === 'avisos' ? 'active' : '') . '" href="index.php?page=josue&tab=avisos">Avisos</a>';
    echo '<a class="subtab ' . ($tab === 'eurekas' ? 'active' : '') . '" href="index.php?page=josue&tab=eurekas">Eurekas</a>';
    // WhatsApp Personal subtab (solo si unlocked)
    if ($wasapUnlocked) {
        echo '<a class="subtab ' . ($tab === 'wasap' ? 'active' : '') . '" href="index.php?page=josue&tab=wasap">📱 WhatsApp</a>';
    }
    echo '<a class="subtab ' . ($tab === 'config' ? 'active' : '') . '" href="index.php?page=josue&tab=config">Config</a>';
    // echo '<a class="subtab ' . ($tab === 'configm' ? 'active' : '') . '" href="index.php?page=josue&tab=configm">ConfigM</a>';
    echo '<a class="subtab ' . ($tab === 'notas' ? 'active' : '') . '" href="index.php?page=josue&tab=notas">Notas</a>';    
    // echo '<a class="subtab ' . ($tab === 'autotube' ? 'active' : '') . '" href="index.php?page=josue&tab=autotube">Autotube</a>';
    echo '<a class="subtab ' . ($tab === 'reproductor' ? 'active' : '') . '" href="index.php?page=josue&tab=reproductor">Reproductor</a>';
    echo '<a class="subtab ' . ($tab === 'rutas' ? 'active' : '') . '" href="index.php?page=josue&tab=rutas">Rutas</a>';
    echo '<a class="subtab ' . ($tab === 'diario' ? 'active' : '') . '" href="index.php?page=josue&tab=diario">Diario</a>';
    
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
        echo '<h2 class="josue-section-title">Config General</h2>';
        render_config_section();
        echo '<hr class="config-separator" style="margin:32px 0;border-color:#333;">';
        echo '<h2 class="josue-section-title">Config Avanzada</h2>';
        render_configm_section();

    // } elseif ($tab === 'configm') {
    //     render_configm_section();

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
        $anunciosIndex = array();
        foreach ($anuncios as $an) {
            $anunciosIndex[$an['id']] = $an;
        }

        echo '<div class="lineas-toolbar">';
        echo '<button type="button" class="btn-primary" id="btnNuevoTelefono">+ Nuevo teléfono</button>';
        echo '</div>';

        echo '<style>
        .transport-badge{display:inline-block;padding:2px 9px;border-radius:11px;font-size:11px;font-weight:600;color:#fff;white-space:nowrap}
        .transport-badge-waha{background:#25D366}
        .transport-badge-evo{background:#8f00ff}
        .transport-line{display:flex;align-items:center;gap:6px;padding:3px 0;flex-wrap:wrap}
        .transport-line + .transport-line{border-top:1px solid rgba(128,128,128,.2);margin-top:4px;padding-top:6px}
        .transport-line-label{font-size:11px;font-weight:700;color:var(--muted);min-width:64px;letter-spacing:.3px}
        </style>';

        echo '<section class="panel">';
        echo '<h2>Listado teléfonos</h2>';
        if (empty($telefonos)) {
            echo '<div class="empty">Todavía no hay teléfonos registrados.</div>';
        } else {
            $telefonos = sort_desc_by_key($telefonos, 'created_at');
            render_live_filter('#telefonosRows tr[data-filter-text]', 'Buscar teléfono...');
            echo '<div class="table-wrap"><table><thead><tr>';
            echo '<th>Nombre</th><th>Tfono</th><th>Uso</th><th>Transporte</th><th>WAHA Port</th><th>WAHA</th><th>Salud</th><th>Notas</th><th>Destacamos</th><th>Acciones</th>';
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
                    ($row['notas'] ?? '') . ' ' .
                    ($destLabel ?? '')
                ));
                $telefonoEditData = json_encode(array(
                    'id'             => $row['id'] ?? '',
                    'nombre'         => $row['nombre'] ?? '',
                    'tfono'          => $row['tfono'] ?? '',
                    'uso'            => $row['uso'] ?? '',
                    'pin'            => $row['pin'] ?? '',
                    'compania'       => $row['compania'] ?? '',
                    'waha_port'      => $row['waha_port'] ?? '',
                    'waha'           => $row['waha'] ?? '',
                    'transport'      => whatsapp_transport_normalize($row['transport'] ?? 'waha'),
                    'destacamos_id'  => $row['destacamos_id'] ?? '',
                    'notas'          => $row['notas'] ?? '',
                ), JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
                echo '<tr data-filter-text="' . e($searchText) . '" data-telefono="' . e($telefonoEditData) . '">';
                echo '<td>' . e($row['nombre'] ?? '') . '</td>';
                echo '<td>' . e($row['tfono'] ?? '') . '<br><button type="button" class="btn-secondary-mini twa-action-button twa-identify-btn" data-identify-id="' . e($row['id'] ?? '') . '" title="Identificar" aria-label="Identificar">?</button> <span class="twa-action-result muted" aria-live="polite" data-identify-result="' . e($row['id'] ?? '') . '"></span></td>';
                echo '<td>' . e($row['uso'] ?? '') . '</td>';
                $activeTransport = whatsapp_transport_normalize($row['transport'] ?? 'waha');
                echo '<td><span class="badge transport-badge transport-badge-' . ($activeTransport === 'evolution' ? 'evo' : 'waha') . '" title="Transporte activo para la mensajería">' . ($activeTransport === 'evolution' ? '⚡ Evolution' : 'WAHA') . '</span></td>';
                echo '<td>' . e($row['waha_port'] ?? '') . '</td>';
                echo '<td>' . e($row['waha'] ?? '') . '</td>';
                $wahaPort = trim($row['waha_port'] ?? '');
                $wahaSession = trim($row['waha'] ?? '');
                echo '<td class="td-waha-salud">';
                echo '<div class="transport-line">';
                echo '<span class="transport-line-label">WAHA</span>';
                echo '<span id="waha-salud-' . e($row['id'] ?? '') . '" class="waha-indicator" aria-label="Estado WAHA: sin comprobar"><span class="waha-status-dot is-unknown" aria-hidden="true"></span>Sin comprobar</span>';
                if ($wahaPort !== '') {
                    echo '<div class="waha-line-actions" id="waha-actions-' . e($row['id'] ?? '') . '" data-telefono-id="' . e($row['id'] ?? '') . '"></div>';
                }
                echo '</div>';
                echo '<div class="transport-line">';
                echo '<span class="transport-line-label">Evolution</span>';
                echo '<span id="evo-salud-' . e($row['id'] ?? '') . '" class="waha-indicator" aria-label="Estado Evolution: sin comprobar"><span class="waha-status-dot is-unknown" aria-hidden="true"></span>Sin comprobar</span>';
                echo '<div class="waha-line-actions" id="evo-actions-' . e($row['id'] ?? '') . '" data-evo-id="' . e($row['id'] ?? '') . '"></div>';
                echo '</div>';
                echo '</td>';
                echo '<td>' . e($row['notas'] ?? '') . '</td>';
                echo '<td>' . e($destLabel) . '</td>';
                echo '<td>';
                echo '<button type="button" class="btn-secondary-mini btn-telefonos-edit">Editar</button> ';
                echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Eliminar este teléfono?\')">';
                echo '<input type="hidden" name="action" value="delete_telefono">';
                echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
                echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                echo '<button class="btn-danger-mini">Eliminar</button>';
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section>';

        echo '<div id="telefonosModalOverlay" class="modal-overlay" style="display:none;">';
        echo '<div class="modal-container">';
        echo '<div class="modal-header">';
        echo '<h2 id="telefonoModalTitle">Nuevo teléfono</h2>';
        echo '<button type="button" class="modal-close" id="btnTelefonoModalClose">&times;</button>';
        echo '</div>';
        echo '<div class="modal-body">';
        echo '<form method="post" class="form-grid" id="telefonoForm">';
        echo '<input type="hidden" name="action" value="save_telefono">';
        echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
        echo '<input type="hidden" name="id" value="">';
        field_input('nombre', 'Nombre', '', true);
        field_input('tfono', 'Tfono', '', true);
        field_input('uso', 'Uso', '');
        field_input('pin', 'PIN', '');
        field_input('compania', 'Compañía', '');
        field_input('waha_port', 'WAHA Port', '');
        field_input('waha', 'WAHA', '');
        echo '<div class="field">';
        echo '<label>Transporte (mensajería)</label>';
        echo '<select name="transport">';
        echo '<option value="waha">WAHA</option>';
        echo '<option value="evolution">Evolution API</option>';
        echo '</select>';
        echo '<small class="muted">Estados siempre por WAHA.</small>';
        echo '</div>';
        echo '<div class="field">';
        echo '<label>Destacamos</label>';
        echo '<select name="destacamos_id">';
        echo '<option value="">Sin vincular</option>';
        foreach ($anuncios as $an) {
            $val = $an['id'] ?? '';
            $label = trim(($an['url'] ?? '') . ' - ' . ($an['user'] ?? ''));
            echo '<option value="' . e($val) . '">' . e($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        field_textarea('notas', 'Notas', '', 4);
        echo '</form>';
        echo '</div>';
        echo '<div class="modal-footer">';
        echo '<button type="button" class="btn-primary" id="btnGuardarTelefono">Guardar teléfono</button>';
        echo '<form method="post" id="deleteTelefonoForm" style="display:inline-block;" onsubmit="return confirm(\'¿Eliminar este teléfono?\')">';
        echo '<input type="hidden" name="action" value="delete_telefono">';
        echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
        echo '<input type="hidden" name="id" value="">';
        echo '<button type="submit" class="btn-danger-mini" id="btnEliminarTelefono" style="display:none;">Eliminar</button>';
        echo '</form>';
        echo '<button type="button" class="btn-secondary" id="btnCancelarTelefono">Cancelar</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // ── QR Modal para vincular líneas WAHA (compartido, oculto) ──
        echo '<div id="twaQrModal" class="wasap-qr-modal" style="display:none">';
        echo '<div class="wasap-qr-modal-bg" id="twaQrModalBg"></div>';
        echo '<div class="wasap-qr-modal-box">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">';
        echo '<h3 style="margin:0">Vincular WhatsApp</h3>';
        echo '<button type="button" id="twaQrCloseTop" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text)">✕</button>';
        echo '</div>';
        echo '<div id="twaQrLineName" style="font-size:13px;color:var(--muted);margin-bottom:10px"></div>';
        echo '<div id="twaQrImageWrap" style="text-align:center;padding:16px;background:#fff;border-radius:8px;margin-bottom:12px">';
        echo '<span class="muted">Cargando QR...</span>';
        echo '</div>';
        echo '<p id="twaQrStatus" class="muted" style="text-align:center;margin-bottom:10px">Esperando...</p>';
        echo '<div style="display:flex;gap:8px;justify-content:center">';
        echo '<button type="button" id="twaQrRegenerate" class="btn-secondary-mini">🔄 Regenerar QR</button>';
        echo '<button type="button" id="twaQrCloseBottom" class="btn-secondary-mini">Cerrar</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // ── JS: health polling + acciones WAHA por telefono_id ──
        echo '<script>
        (function(){
            var apiBase = "telefonos_waha_api.php";
            var csrfToken = ' . json_encode(csrf_token()) . ';
            var qrCurrentId = "";
            var qrPolling = null;
            var identifyPending = {};
            var identifyResult = {};

            function fetchTimeout(url, options, timeoutMs) {
                return new Promise(function(resolve, reject) {
                    var done = false;
                    options = options || {};
                    var controller = window.AbortController ? new AbortController() : null;
                    if (controller) options.signal = controller.signal;
                    var timer = setTimeout(function(){
                        if (!done) {
                            done = true;
                            if (controller) controller.abort();
                            reject(new Error("Tiempo de espera agotado"));
                        }
                    }, timeoutMs);
                    fetch(url, options).then(function(response){
                        if (done) return;
                        done = true;
                        clearTimeout(timer);
                        resolve(response);
                    }).catch(function(error){
                        if (done) return;
                        done = true;
                        clearTimeout(timer);
                        reject(error);
                    });
                });
            }

            function jsonRequest(url, options, timeoutMs) {
                return fetchTimeout(url, options, timeoutMs).then(function(response){
                    return response.json().catch(function(){
                        throw new Error("Respuesta inválida del servidor");
                    }).then(function(data){
                        if (!response.ok || !data.ok) throw new Error(data.error || ("Error HTTP " + response.status));
                        return data;
                    });
                });
            }

            function healthKind(status) {
                status = (status || "").toUpperCase();
                if (status === "WORKING" || status === "CONNECTED") return "up";
                if (status === "SCAN_QR_CODE" || status === "STARTING") return "starting";
                if (!status || status === "UNKNOWN") return "unknown";
                return "down";
            }

            function renderHealth(element, status, label, phone) {
                if (!element) return;
                var text = label || "Desconocido";
                var phoneText = phone ? (" (" + phone + ")") : "";
                element.setAttribute("aria-label", "Estado WAHA: " + text + phoneText);
                while (element.firstChild) element.removeChild(element.firstChild);
                var dot = document.createElement("span");
                dot.className = "waha-status-dot is-" + healthKind(status);
                dot.setAttribute("aria-hidden", "true");
                element.appendChild(dot);
                element.appendChild(document.createTextNode(text + phoneText));
            }

            function actionButton(action, icon, title, className) {
                var button = document.createElement("button");
                button.type = "button";
                button.className = className + " twa-action-button";
                button.setAttribute("data-action", action);
                button.setAttribute("title", title);
                button.setAttribute("aria-label", title);
                button.textContent = icon;
                return button;
            }

            function renderActions(element, id, status, connected, failed) {
                if (!element || identifyPending[id]) return;
                while (element.firstChild) element.removeChild(element.firstChild);
                status = (status || "").toUpperCase();
                if (status === "SCAN_QR_CODE" || status === "STARTING") {
                    element.appendChild(actionButton("qr", "📱", "Vincular", "btn-secondary-mini"));
                } else if (failed || status === "FAILED" || status === "STOPPED") {
                    element.appendChild(actionButton("restart", "↻", "Reescanear", "btn-danger-mini"));
                } else if (connected) {
                    element.appendChild(actionButton("restart", "↻", "Reescanear", "btn-secondary-mini"));
                }
            }

            function checkLine(id) {
                var health = document.getElementById("waha-salud-" + id);
                var actions = document.getElementById("waha-actions-" + id);
                var url = apiBase + "?action=status&telefono_id=" + encodeURIComponent(id) + "&_=" + Date.now();
                jsonRequest(url, undefined, 12000).then(function(data){
                    renderHealth(health, data.status, data.status_label || data.status, data.phone || "");
                    renderActions(actions, id, data.status, !!data.is_connected, false);
                }).catch(function(error){
                    renderHealth(health, "FAILED", error.message || "Error WAHA", "");
                    renderActions(actions, id, "FAILED", false, true);
                });
            }

            function checkAllLines() {
                var rows = document.getElementById("telefonosRows");
                if (!rows) return;
                var actionGroups = rows.querySelectorAll(".waha-line-actions[data-telefono-id]");
                actionGroups.forEach(function(group){
                    var id = group.getAttribute("data-telefono-id") || "";
                    if (id) checkLine(id);
                });
            }

            function setQrContent(message, isError) {
                var wrap = document.getElementById("twaQrImageWrap");
                if (!wrap) return;
                while (wrap.firstChild) wrap.removeChild(wrap.firstChild);
                var text = document.createElement("span");
                text.className = isError ? "" : "muted";
                if (isError) text.style.color = "var(--danger)";
                text.textContent = message;
                wrap.appendChild(text);
            }

            function stopQrPolling() {
                if (qrPolling) clearInterval(qrPolling);
                qrPolling = null;
            }

            function startQrPolling() {
                stopQrPolling();
                qrPolling = setInterval(function(){
                    var url = apiBase + "?action=status&telefono_id=" + encodeURIComponent(qrCurrentId) + "&_=" + Date.now();
                    jsonRequest(url, undefined, 8000).then(function(data){
                        if (data.is_connected) {
                            closeQr();
                            checkAllLines();
                        }
                    }).catch(function(error){
                        stopQrPolling();
                        document.getElementById("twaQrStatus").textContent = error.message || "Error comprobando WAHA";
                    });
                }, 4000);
            }

            function fetchQr() {
                var wrap = document.getElementById("twaQrImageWrap");
                var status = document.getElementById("twaQrStatus");
                var url = apiBase + "?action=qr&telefono_id=" + encodeURIComponent(qrCurrentId) + "&_=" + Date.now();
                return jsonRequest(url, undefined, 20000).then(function(data){
                    if (!data.qr_base64) throw new Error("WAHA no devolvió un QR");
                    while (wrap.firstChild) wrap.removeChild(wrap.firstChild);
                    var image = document.createElement("img");
                    image.src = "data:image/png;base64," + data.qr_base64;
                    image.style.maxWidth = "260px";
                    image.style.borderRadius = "4px";
                    image.alt = "QR";
                    image.addEventListener("error", function(){ setQrContent("Error al mostrar QR", true); });
                    wrap.appendChild(image);
                    status.textContent = "Escanea con WhatsApp → Vincular dispositivo";
                    return true;
                }).catch(function(error){
                    setQrContent(error.message || "No se pudo obtener el QR", true);
                    status.textContent = "No se iniciará la comprobación automática.";
                    return false;
                });
            }

            function showQr(id) {
                qrCurrentId = id;
                stopQrPolling();
                document.getElementById("twaQrModal").style.display = "flex";
                document.getElementById("twaQrLineName").textContent = "Línea seleccionada";
                document.getElementById("twaQrStatus").textContent = "Obteniendo QR...";
                setQrContent("Cargando QR...", false);
                fetchQr().then(function(ok){ if (ok) startQrPolling(); });
            }

            function closeQr() {
                document.getElementById("twaQrModal").style.display = "none";
                stopQrPolling();
                checkAllLines();
            }

            function regenerateQr() {
                stopQrPolling();
                setQrContent("Regenerando QR...", false);
                document.getElementById("twaQrStatus").textContent = "Solicitando nuevo QR...";
                fetchQr().then(function(ok){ if (ok) startQrPolling(); });
            }

            function postBody(action, idField, id) {
                var body = new FormData();
                body.append("action", action);
                body.append(idField, id);
                body.append("csrf_token", csrfToken);
                return body;
            }

            function restartAndRescan(id) {
                if (!confirm("Reiniciar sesión WAHA de esta línea? Deberás escanear el QR para vincular de nuevo.")) return;
                var health = document.getElementById("waha-salud-" + id);
                var actions = document.getElementById("waha-actions-" + id);
                renderHealth(health, "STARTING", "Reiniciando...", "");
                while (actions.firstChild) actions.removeChild(actions.firstChild);
                var waiting = document.createElement("span");
                waiting.className = "muted";
                waiting.textContent = "⏳ Reiniciando...";
                actions.appendChild(waiting);
                jsonRequest(apiBase, {method:"POST", body:postBody("restart", "telefono_id", id), credentials:"same-origin"}, 30000).then(function(data){
                    waiting.textContent = data.message || "WAHA reiniciado";
                    setTimeout(function(){
                        var polls = 0;
                        var timer = setInterval(function(){
                            polls++;
                            var url = apiBase + "?action=status&telefono_id=" + encodeURIComponent(id) + "&_=" + Date.now();
                            jsonRequest(url, undefined, 8000).then(function(statusData){
                                if (statusData.status === "SCAN_QR_CODE" || statusData.status === "STARTING") {
                                    clearInterval(timer);
                                    showQr(id);
                                    return;
                                }
                                if (polls >= 30) {
                                    clearInterval(timer);
                                    renderHealth(health, "FAILED", "WAHA no generó QR tras 60s", "");
                                    renderActions(actions, id, "FAILED", false, true);
                                }
                            }).catch(function(error){
                                clearInterval(timer);
                                renderHealth(health, "FAILED", error.message || "Error comprobando WAHA", "");
                                renderActions(actions, id, "FAILED", false, true);
                                var result = actions.querySelector(".twa-action-result");
                                if (result) result.textContent = error.message || "Error comprobando WAHA";
                            });
                        }, 2000);
                    }, 3000);
                }).catch(function(error){
                    renderHealth(health, "FAILED", error.message || "Error al reiniciar WAHA", "");
                    renderActions(actions, id, "FAILED", false, true);
                    var result = actions.querySelector(".twa-action-result");
                    if (result) result.textContent = error.message || "Error al reiniciar WAHA";
                });
            }

            function identify(id, button, result) {
                if (identifyPending[id]) return;
                identifyPending[id] = true;
                button.disabled = true;
                result.textContent = "Identificando...";
                jsonRequest(apiBase, {method:"POST", body:postBody("identify", "target_id", id), credentials:"same-origin"}, 30000).then(function(data){
                    identifyResult[id] = "Enviado desde " + (data.source_label || "otra línea") + (data.source_phone ? (" (" + data.source_phone + ")") : "");
                    result.textContent = identifyResult[id];
                }).catch(function(error){
                    identifyResult[id] = error.message || "No se pudo identificar la línea";
                    result.textContent = identifyResult[id];
                }).then(function(){
                    identifyPending[id] = false;
                    button.disabled = false;
                });
            }

            var rows = document.getElementById("telefonosRows");
            if (rows) rows.addEventListener("click", function(event){
                var button = event.target.closest ? event.target.closest("button[data-action]") : null;
                if (!button || !rows.contains(button)) return;
                var actions = button.parentNode;
                var id = actions.getAttribute("data-telefono-id") || "";
                var action = button.getAttribute("data-action");
                if (!id) return;
                if (action === "restart") restartAndRescan(id);
                if (action === "qr") showQr(id);
            });
            // Identificar (botón bajo el número de teléfono)
            var telefonosRows2 = document.getElementById("telefonosRows");
            if (telefonosRows2) telefonosRows2.addEventListener("click", function(event){
                var ibtn = event.target.closest ? event.target.closest(".twa-identify-btn") : null;
                if (!ibtn) return;
                var iid = ibtn.getAttribute("data-identify-id") || "";
                if (!iid) return;
                var ires = document.querySelector(\'[data-identify-result="\' + iid + \'"]\');
                identify(iid, ibtn, ires);
            });
            document.getElementById("twaQrModalBg").addEventListener("click", closeQr);
            document.getElementById("twaQrCloseTop").addEventListener("click", closeQr);
            document.getElementById("twaQrCloseBottom").addEventListener("click", closeQr);
            document.getElementById("twaQrRegenerate").addEventListener("click", regenerateQr);

            if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", checkAllLines);
            else checkAllLines();
            setInterval(checkAllLines, 15000);
        })();
        </script>';

        // ── Modal QR Evolution (2º backend) ──
        echo '<div id="evoQrModal" class="wasap-qr-modal" style="display:none">';
        echo '<div class="wasap-qr-modal-bg" id="evoQrModalBg"></div>';
        echo '<div class="wasap-qr-modal-box">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">';
        echo '<h3 style="margin:0">Vincular Evolution API</h3>';
        echo '<button type="button" id="evoQrCloseTop" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text)">✕</button>';
        echo '</div>';
        echo '<div id="evoQrLineName" style="font-size:13px;color:var(--muted);margin-bottom:10px"></div>';
        echo '<div id="evoQrImageWrap" style="text-align:center;padding:16px;background:#fff;border-radius:8px;margin-bottom:12px">';
        echo '<span class="muted">Cargando QR...</span>';
        echo '</div>';
        echo '<p id="evoQrStatus" class="muted" style="text-align:center;margin-bottom:10px">Esperando...</p>';
        echo '<div style="display:flex;gap:8px;justify-content:center">';
        echo '<button type="button" id="evoQrRegenerate" class="btn-secondary-mini">🔄 Regenerar QR</button>';
        echo '<button type="button" id="evoQrCloseBottom" class="btn-secondary-mini">Cerrar</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // ── JS: salud + QR + restart de Evolution por línea (2º backend) ──
        echo '<script>
        (function(){
            var apiBase = "telefonos_waha_api.php";
            var csrfToken = ' . json_encode(csrf_token()) . ';
            var evoQrCurrentId = "";
            var evoQrPolling = null;

            function evoFetchTimeout(url, options, timeoutMs) {
                return new Promise(function(resolve, reject) {
                    var done = false;
                    options = options || {};
                    var controller = window.AbortController ? new AbortController() : null;
                    if (controller) options.signal = controller.signal;
                    var timer = setTimeout(function(){ if (!done) { done = true; if (controller) controller.abort(); reject(new Error("Tiempo agotado")); } }, timeoutMs);
                    fetch(url, options).then(function(r){ if (!done) { done = true; clearTimeout(timer); resolve(r); } }).catch(function(e){ if (!done) { done = true; clearTimeout(timer); reject(e); } });
                });
            }
            function evoJson(url, options, timeoutMs) {
                return evoFetchTimeout(url, options, timeoutMs).then(function(r){ return r.json().catch(function(){ throw new Error("Respuesta inválida"); }).then(function(d){ if (!r.ok || !d.ok) throw new Error(d.error || ("HTTP " + r.status)); return d; }); });
            }
            function evoHealthKind(status) {
                status = (status || "").toUpperCase();
                if (status === "OPEN") return "up";
                if (status === "CONNECTING") return "starting";
                if (!status) return "unknown";
                return "down";
            }
            function evoRender(element, status, label, instance) {
                if (!element) return;
                var text = label || "Desconocido";
                var instText = instance ? (" · " + instance) : "";
                element.setAttribute("aria-label", "Estado Evolution: " + text + instText);
                while (element.firstChild) element.removeChild(element.firstChild);
                var dot = document.createElement("span");
                dot.className = "waha-status-dot is-" + evoHealthKind(status);
                dot.setAttribute("aria-hidden", "true");
                element.appendChild(dot);
                element.appendChild(document.createTextNode(text + instText));
            }
            function evoButton(action, icon, title, className) {
                var b = document.createElement("button");
                b.type = "button";
                b.className = className + " twa-action-button";
                b.setAttribute("data-action", action);
                b.setAttribute("title", title);
                b.setAttribute("aria-label", title);
                b.textContent = icon;
                return b;
            }
            function evoRenderActions(element, id, status) {
                if (!element) return;
                while (element.firstChild) element.removeChild(element.firstChild);
                status = (status || "").toUpperCase();
                if (status === "OPEN") {
                    element.appendChild(evoButton("evo-restart", "↻", "Reescanear Evolution", "btn-secondary-mini"));
                } else {
                    element.appendChild(evoButton("evo-qr", "📱", "Vincular QR Evolution", "btn-secondary-mini"));
                }
            }
            function evoCheckLine(id) {
                var health = document.getElementById("evo-salud-" + id);
                var actions = document.getElementById("evo-actions-" + id);
                var url = apiBase + "?action=evo_status&telefono_id=" + encodeURIComponent(id) + "&_=" + Date.now();
                evoJson(url, undefined, 12000).then(function(data){
                    evoRender(health, data.status, data.status_label || data.status, data.instance || "");
                    evoRenderActions(actions, id, data.status);
                }).catch(function(error){
                    evoRender(health, "FAILED", error.message || "Error Evolution", "");
                    evoRenderActions(actions, id, "FAILED");
                });
            }
            function evoCheckAll() {
                var rows = document.getElementById("telefonosRows");
                if (!rows) return;
                rows.querySelectorAll("[data-evo-id]").forEach(function(group){
                    var id = group.getAttribute("data-evo-id") || "";
                    if (id) evoCheckLine(id);
                });
            }
            function evoSetQr(message, isError) {
                var wrap = document.getElementById("evoQrImageWrap");
                if (!wrap) return;
                while (wrap.firstChild) wrap.removeChild(wrap.firstChild);
                var t = document.createElement("span");
                t.className = isError ? "" : "muted";
                if (isError) t.style.color = "var(--danger)";
                t.textContent = message;
                wrap.appendChild(t);
            }
            function evoStopPolling() { if (evoQrPolling) clearInterval(evoQrPolling); evoQrPolling = null; }
            function evoFetchQr() {
                var wrap = document.getElementById("evoQrImageWrap");
                var status = document.getElementById("evoQrStatus");
                var url = apiBase + "?action=evo_qr&telefono_id=" + encodeURIComponent(evoQrCurrentId) + "&_=" + Date.now();
                return evoJson(url, undefined, 25000).then(function(data){
                    if (!data.qr_base64) throw new Error("Evolution no devolvió un QR");
                    while (wrap.firstChild) wrap.removeChild(wrap.firstChild);
                    var image = document.createElement("img");
                    image.src = "data:image/png;base64," + data.qr_base64;
                    image.style.maxWidth = "260px";
                    image.style.borderRadius = "4px";
                    image.alt = "QR";
                    image.addEventListener("error", function(){ evoSetQr("Error al mostrar QR", true); });
                    wrap.appendChild(image);
                    status.textContent = "Escanea con WhatsApp → Vincular dispositivo (Evolution)";
                    return true;
                }).catch(function(error){
                    evoSetQr(error.message || "No se pudo obtener el QR", true);
                    status.textContent = "No se iniciará la comprobación automática.";
                    return false;
                });
            }
            function evoShowQr(id) {
                evoQrCurrentId = id;
                evoStopPolling();
                document.getElementById("evoQrModal").style.display = "flex";
                document.getElementById("evoQrLineName").textContent = "Línea: " + id;
                document.getElementById("evoQrStatus").textContent = "Obteniendo QR...";
                evoSetQr("Cargando QR...", false);
                evoFetchQr();
            }
            function evoCloseQr() {
                document.getElementById("evoQrModal").style.display = "none";
                evoStopPolling();
                evoCheckAll();
            }
            function evoRegenerate() {
                evoStopPolling();
                evoSetQr("Regenerando QR...", false);
                document.getElementById("evoQrStatus").textContent = "Solicitando nuevo QR...";
                evoFetchQr();
            }
            function evoPost(action, id) {
                var body = new FormData();
                body.append("action", action);
                body.append("telefono_id", id);
                body.append("csrf_token", csrfToken);
                return body;
            }
            function evoRestart(id) {
                if (!confirm("Reiniciar sesión Evolution de esta línea? Deberás escanear el QR de nuevo.")) return;
                var health = document.getElementById("evo-salud-" + id);
                evoRender(health, "CONNECTING", "Reiniciando...", "");
                evoJson(apiBase, {method:"POST", body:evoPost("evo_restart", id), credentials:"same-origin"}, 30000).then(function(){
                    setTimeout(function(){ evoShowQr(id); }, 1500);
                }).catch(function(error){
                    evoRender(health, "FAILED", error.message || "Error al reiniciar Evolution", "");
                });
            }

            var rows = document.getElementById("telefonosRows");
            if (rows) rows.addEventListener("click", function(event){
                var button = event.target.closest ? event.target.closest("button[data-action]") : null;
                if (!button || !rows.contains(button)) return;
                var action = button.getAttribute("data-action");
                if (action === "evo-qr") {
                    var group = button.closest("[data-evo-id]");
                    if (group) evoShowQr(group.getAttribute("data-evo-id"));
                } else if (action === "evo-restart") {
                    var group2 = button.closest("[data-evo-id]");
                    if (group2) evoRestart(group2.getAttribute("data-evo-id"));
                }
            });
            document.getElementById("evoQrModalBg").addEventListener("click", evoCloseQr);
            document.getElementById("evoQrCloseTop").addEventListener("click", evoCloseQr);
            document.getElementById("evoQrCloseBottom").addEventListener("click", evoCloseQr);
            document.getElementById("evoQrRegenerate").addEventListener("click", evoRegenerate);

            if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", evoCheckAll);
            else evoCheckAll();
            setInterval(evoCheckAll, 15000);
        })();
        </script>';


    } elseif ($tab === 'autotube') {
        echo '<div class="josue-head"><h2>Autotube</h2></div>';
        echo '<div style="padding:0;overflow:visible;border-radius:8px;background:#0a0a0f">';
        echo '<iframe src="/autotube/" style="width:100%;min-height:calc(100vh - 280px);height:auto;border:none;display:block" title="Panel Autotube" loading="lazy" sandbox="allow-scripts allow-same-origin allow-forms allow-popups"></iframe>';
        echo '</div>';

    } elseif ($tab === 'reproductor') {
        render_youtube_player();

    } elseif ($tab === 'eurekas') {
        $editId = request_get('edit', '');
        $edit = $editId !== '' ? storage_find_by_id('eurekas.json', $editId) : null;
        $eurekaRows = sort_desc_by_key($eurekas, 'updated_at');
        $statusLabels = array(
            'pendiente' => 'Pendiente',
            'descartada' => 'Descartada',
            'cumplida' => 'Cumplida',
            'cumplida_v2' => 'Cumplida V2',
        );
        $estadoFilter = trim((string)request_get('estado', 'pendiente'));
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
        $filterOptions = array('todas' => 'Todas', 'pendiente' => 'Pendientes', 'cumplida_v2' => 'Cumplidas V2', 'cumplida' => 'Cumplidas', 'descartada' => 'Descartadas');
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
                if ($estado !== 'cumplida_v2') {
                    echo '<form method="post" class="inline-form">';
                    echo '<input type="hidden" name="action" value="set_eureka_estado">';
                    echo '<input type="hidden" name="id" value="' . e($row['id']) . '">';
                    echo '<input type="hidden" name="estado" value="cumplida_v2">';
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

    } elseif ($tab === 'rutas') {
        render_rutas_tab();

    } elseif ($tab === 'diario') {
        render_diario_tab();

    } elseif ($tab === 'wasap') {
        // ── WhatsApp Personal ──
        $wasapSub = request_get('sub', 'chat');
        echo '<div class="josue-head">';
        echo '<h2>📱 WhatsApp Personal</h2>';
        echo '<div style="display:flex;gap:8px;margin-top:6px">';
        echo '<a class="subtab ' . ($wasapSub === 'chat' ? 'active' : '') . '" href="index.php?page=josue&tab=wasap&sub=chat">Chat</a>';
        echo '<a class="subtab ' . ($wasapSub === 'config' ? 'active' : '') . '" href="index.php?page=josue&tab=wasap&sub=config">Config</a>';
        echo '</div>';
        echo '</div>';

        if ($wasapSub === 'config') {
            // ── Sub-tab Config: estado WAHA + QR ──
            echo '<div class="wasap-config">';

            // Card 1: Estado
            echo '<div class="wasap-config-card">';
            echo '<h3>Estado de conexión</h3>';
            echo '<div id="wasapStatusCard">';
            echo '<div id="wasapStatusContent"><span class="muted">Cargando...</span></div>';
            echo '<div id="wasapQrBtnWrap" style="margin-top:12px;display:none"></div>';
            echo '</div>';
            echo '</div>';

            // Card 2: Instrucciones
            echo '<div class="wasap-config-card">';
            echo '<h3>Vincular WhatsApp</h3>';
            echo '<p class="muted">Para vincular tu WhatsApp personal:</p>';
            echo '<ol style="margin:8px 0 0 16px;font-size:13px;color:var(--muted);line-height:1.8">';
            echo '<li>Abre WhatsApp en tu móvil</li>';
            echo '<li>Ve a <strong>Ajustes → Dispositivos vinculados</strong></li>';
            echo '<li>Toca <strong>Vincular un dispositivo</strong></li>';
            echo '<li>Apunta la cámara al código QR</li>';
            echo '</ol>';
            echo '</div>';
            echo '</div>';

            // QR Modal (hidden)
            echo '<div id="wasapQrModal" class="wasap-qr-modal" style="display:none">';
            echo '<div class="wasap-qr-modal-bg" onclick="wasapCloseQr()"></div>';
            echo '<div class="wasap-qr-modal-box">';
            echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">';
            echo '<h3 style="margin:0">Vincular WhatsApp</h3>';
            echo '<button onclick="wasapCloseQr()" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--text)">✕</button>';
            echo '</div>';
            echo '<div id="wasapQrImageWrap" style="text-align:center;padding:16px;background:#fff;border-radius:8px;margin-bottom:12px">';
            echo '<span class="muted">Cargando QR...</span>';
            echo '</div>';
            echo '<p id="wasapQrStatus" class="muted" style="text-align:center;margin-bottom:10px">Esperando...</p>';
            echo '<div style="display:flex;gap:8px;justify-content:center">';
            echo '<button onclick="wasapRegenerateQr()" class="btn-secondary-mini">🔄 Regenerar QR</button>';
            echo '<button onclick="wasapCloseQr()" class="btn-secondary-mini">Cerrar</button>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            // Debug panel (visible on-screen)
            echo '<div class="wasap-config-card" style="margin-top:12px"><h3>🐛 Debug Log</h3><div id="wasapDebug" style="background:#1a1a2e;color:#e0e0e0;font:11px/1.5 monospace;padding:10px;border-radius:6px;max-height:300px;overflow-y:auto;word-break:break-all"><div style="color:#888">Esperando inicio del script...</div></div></div>';

            // JS
            echo '<script>
            (function(){
                try {
                // ── debug helpers ──
                function wasapLog(msg) {
                    console.log("[wasap]", msg);
                    var d = document.getElementById("wasapDebug");
                    if (d) {
                        var time = new Date().toLocaleTimeString();
                        var safe = String(msg).replace(/</g,"&lt;").replace(/>/g,"&gt;");
                        d.innerHTML += "<div style=\"border-bottom:1px solid #252545;padding:2px 0\"><b style=\"color:#4fc3f7\">" + time + "</b> <span style=\"color:#ccc\">" + safe + "</span></div>";
                        d.scrollTop = d.scrollHeight;
                    }
                }
                function wasapLogErr(msg) {
                    console.error("[wasap]", msg);
                    var d = document.getElementById("wasapDebug");
                    if (d) {
                        var time = new Date().toLocaleTimeString();
                        var safe = String(msg).replace(/</g,"&lt;").replace(/>/g,"&gt;");
                        d.innerHTML += "<div style=\"border-bottom:1px solid #452525;padding:2px 0\"><b style=\"color:#ef5350\">" + time + "</b> <span style=\"color:#ff8a80\">" + safe + "</span></div>";
                        d.scrollTop = d.scrollHeight;
                    }
                }
                wasapLog("IIFE START");

                var api = "personal_wasap_api.php";
                var token = "&token=wasap_personal_2026";
                var qrPolling = null;
                var lastStatus = "";

                function fetchWithTimeout(url, timeoutMs) {
                    return new Promise(function(resolve, reject) {
                        var timer = setTimeout(function(){ reject(new Error("timeout")); }, timeoutMs);
                        fetch(url).then(function(r){
                            clearTimeout(timer);
                            resolve(r);
                        }).catch(function(e){
                            clearTimeout(timer);
                            reject(e);
                        });
                    });
                }

                function checkStatus() {
                    wasapLog("checkStatus() ENTERED");
                    var el = document.getElementById("wasapStatusContent");
                    var btnWrap = document.getElementById("wasapQrBtnWrap");
                    if (!el) { wasapLogErr("checkStatus ABORT: #wasapStatusContent NOT FOUND in DOM!"); return; }
                    wasapLog("DOM elements found OK (content="+!!el+", btnWrap="+!!btnWrap+")");

                    var url = api + "?action=status" + token + "&_=" + Date.now();
                    wasapLog("fetch START -> " + url);
                    fetchWithTimeout(url, 12000)
                    .then(function(r){
                        wasapLog("fetch RESPONSE: HTTP " + r.status + " " + (r.ok?"OK":"FAIL"));
                        if (!r.ok) throw new Error("HTTP " + r.status);
                        return r.json();
                    })
                    .then(function(d){
                        wasapLog("JSON PARSED: ok=" + d.ok + " status=" + d.status + " qr_available=" + d.qr_available);
                        if (!d.ok) {
                            el.innerHTML = "<div style=\"color:var(--danger);margin-bottom:4px\">🔴 " + (d.error||"Error") + "</div>";
                            if (btnWrap) { btnWrap.innerHTML = "<button onclick=\"checkStatus()\" class=\"btn-secondary-mini\">🔄 Reintentar</button>"; btnWrap.style.display = "block"; }
                            wasapLog("DOM: wrote error (" + (d.error||"Error") + ")");
                            return;
                        }
                        var icon = d.status_icon||"?";
                        var label = d.status_label||d.status||"?";
                        var phone = d.health_phone || d.phone || "654464023";
                        var html = "<div style=\"font-size:18px;margin-bottom:4px\">" + icon + " " + label + "</div>";
                        html += "<div class=\"muted\">Teléfono: " + phone + "</div>";
                        if (d.last_sync) html += "<div class=\"muted\" style=\"font-size:12px\">Última sincro: " + d.last_sync + "</div>";
                        el.innerHTML = html;
                        wasapLog("DOM UPDATED: statusContent <- " + icon + " " + label);

                        // QR button
                        if (btnWrap) {
                            if (d.qr_available) {
                                btnWrap.innerHTML = "<button onclick=\"wasapShowQr()\" class=\"btn-primary\">📱 Generar QR</button>";
                                btnWrap.style.display = "block";
                                wasapLog("DOM: QR button shown");
                            } else if (d.is_connected) {
                                btnWrap.innerHTML = "<div style=\"display:flex;flex-direction:column;align-items:flex-start;gap:10px\"><span style=\"color:var(--ok);font-weight:600\">✅ WhatsApp vinculado correctamente</span><button onclick=\"wasapRestartAndRescan()\" class=\"btn-secondary-mini\" style=\"background:var(--warning);color:#000;font-weight:600;border:none;padding:6px 14px;border-radius:6px;cursor:pointer\">🔄 Reiniciar sesión</button></div>";
                                btnWrap.style.display = "block";
                                wasapLog("DOM: connected label + restart button shown");
                            } else if (d.status === "FAILED") {
                                btnWrap.innerHTML = "<button onclick=\"wasapRestartWaha()\" class=\"btn-primary\" style=\"background:var(--danger)\">🔄 Reiniciar WAHA</button><br><small class=\"muted\">La sesión falló. Pulsa para reiniciar y luego escanea el QR.</small>";
                                btnWrap.style.display = "block";
                                wasapLog("DOM: restart button shown (status=FAILED)");
                            } else {
                                btnWrap.style.display = "none";
                                wasapLog("DOM: button hidden (status=" + d.status + ")");
                            }
                        }

                        // Auto-close QR modal if just connected
                        if (d.is_connected && lastStatus === "SCAN_QR_CODE") {
                            wasapLog("STATUS TRANSITION: SCAN_QR_CODE -> CONNECTED, closing modal");
                            wasapCloseQr();
                            if (el) { el.style.transition = "background 0.3s"; el.style.background = "rgba(37,211,102,0.08)"; setTimeout(function(){ el.style.background = ""; }, 2000); }
                        }
                        lastStatus = d.status;
                    }).catch(function(e){
                        var msg = (e.message === "timeout") ? "Tiempo de espera (WAHA no responde)" : (e.message||"Error de red");
                        wasapLogErr("checkStatus FAILED: " + msg);
                        el.innerHTML = "<div style=\"color:var(--danger);margin-bottom:4px\">⚠️ " + msg + "</div>";
                        if (btnWrap) { btnWrap.innerHTML = "<button onclick=\"checkStatus()\" class=\"btn-secondary-mini\">🔄 Reintentar</button>"; btnWrap.style.display = "block"; }
                    });
                }

                function wasapShowQr() {
                    var modal = document.getElementById("wasapQrModal");
                    if (!modal) return;
                    modal.style.display = "flex";
                    document.getElementById("wasapQrImageWrap").innerHTML = "<span class=\"muted\">Cargando QR...</span>";
                    document.getElementById("wasapQrStatus").textContent = "Obteniendo QR de WAHA...";
                    wasapFetchQr();
                    // Start polling
                    if (qrPolling) clearInterval(qrPolling);
                    qrPolling = setInterval(function(){
                        fetch(api + "?action=status" + token + "&_=" + Date.now())
                        .then(function(r){return r.json()})
                        .then(function(d){
                            if (d.is_connected) {
                                checkStatus();
                                // modal will be closed by checkStatus detecting transition
                            }
                        }).catch(function(){});
                    }, 4000);
                }

                function wasapFetchQr() {
                    var wrap = document.getElementById("wasapQrImageWrap");
                    var st = document.getElementById("wasapQrStatus");
                    if (!wrap) return;

                    console.log("[wasap] fetching QR...");
                    var url = api + "?action=qr" + token + "&_=" + Date.now();
                    fetchWithTimeout(url, 20000)
                    .then(function(r){
                        if (!r.ok) throw new Error("HTTP " + r.status);
                        return r.json();
                    })
                    .then(function(d){
                        console.log("[wasap] QR:", d.ok ? "OK ("+((d.qr_base64||"").length)+"b)" : "FAIL: "+(d.error||""));
                        if (d.ok && d.qr_base64) {
                            wrap.innerHTML = "<img src=\"data:image/png;base64," + d.qr_base64 + "\" style=\"max-width:260px;border-radius:4px\" alt=\"QR\" onerror=\"this.innerHTML=\'<span style=color:var(--danger)>❌ Error al mostrar QR</span>\';\">";
                            if (st) st.textContent = "Escanea con WhatsApp → Vincular dispositivo";
                        } else {
                            wrap.innerHTML = "<div style=\"color:var(--danger);margin-bottom:6px\">❌ " + (d.error||"No se pudo obtener QR") + "</div><div class=\"muted\" style=\"font-size:12px\">" + (d.hint||"¿WAHA caído? Prueba \"Reiniciar WAHA\".") + "</div>";
                            if (st) st.textContent = "Error. Usa \"Reiniciar WAHA\".";
                        }
                    }).catch(function(e){
                        var msg = (e.message === "timeout") ? "Tiempo agotado (WAHA no responde)" : (e.message||"Error de red");
                        console.log("[wasap] QR error:", msg);
                        wrap.innerHTML = "<div style=\"color:var(--danger);margin-bottom:6px\">❌ " + msg + "</div><div class=\"muted\" style=\"font-size:12px\">WAHA parece caído. Usa \"Reiniciar WAHA\".</div>";
                        if (st) st.textContent = "WAHA no responde. Necesitas reiniciarlo.";
                    });
                }

                function wasapRegenerateQr() {
                    document.getElementById("wasapQrImageWrap").innerHTML = "<span class=\"muted\">Regenerando QR...</span>";
                    document.getElementById("wasapQrStatus").textContent = "Solicitando nuevo QR...";
                    wasapFetchQr();
                }

                function wasapCloseQr() {
                    document.getElementById("wasapQrModal").style.display = "none";
                    if (qrPolling) { clearInterval(qrPolling); qrPolling = null; }
                    checkStatus();
                }

                function wasapRestartWaha() {
                    var btnWrap = document.getElementById("wasapQrBtnWrap");
                    if (btnWrap) btnWrap.innerHTML = "<span style=\"color:var(--warning)\">⏳ Reiniciando WAHA... (~15s)</span>";
                    var card = document.getElementById("wasapStatusCard");
                    if (card) card.style.opacity = "0.5";
                    console.log("[wasap] restarting WAHA...");
                    fetch(api + "?action=restart" + token + "&_=" + Date.now())
                    .then(function(r){return r.json()})
                    .then(function(d){
                        console.log("[wasap] restart response:", d.ok ? "OK" : "FAIL: "+(d.error||""));
                        // Wait 10s then re-check
                        setTimeout(function(){
                            if (card) card.style.opacity = "1";
                            checkStatus();
                        }, 10000);
                    }).catch(function(e){
                        console.log("[wasap] restart error:", e);
                        alert("Error al reiniciar WAHA. Intenta manualmente.");
                        if (card) card.style.opacity = "1";
                        checkStatus();
                    });
                }

                function wasapRestartAndRescan() {
                    if (!confirm("¿Reiniciar sesión de WhatsApp? Deberás volver a escanear el código QR para vincular de nuevo.")) return;
                    var btnWrap = document.getElementById("wasapQrBtnWrap");
                    if (btnWrap) btnWrap.innerHTML = "<span style=\"color:var(--warning)\">⏳ Reiniciando WAHA... (~15s)</span>";
                    var card = document.getElementById("wasapStatusCard");
                    if (card) card.style.opacity = "0.5";
                    console.log("[wasap] restartAndRescan: calling restart...");
                    fetch(api + "?action=restart" + token + "&_=" + Date.now())
                    .then(function(r){return r.json()})
                    .then(function(d){
                        console.log("[wasap] restartAndRescan: response", d.ok ? "OK" : "FAIL: "+(d.error||""));
                        // Wait 10s for WAHA to restart, then poll for QR
                        setTimeout(function(){
                            if (card) card.style.opacity = "1";
                            console.log("[wasap] restartAndRescan: starting QR poll...");
                            var pollCount = 0;
                            var maxPolls = 30; // 30 * 2s = 60s max
                            var pollForQr = setInterval(function(){
                                pollCount++;
                                fetch(api + "?action=status" + token + "&_=" + Date.now())
                                .then(function(r){return r.json()})
                                .then(function(sd){
                                    console.log("[wasap] restartAndRescan: poll #"+pollCount+" qr_available="+sd.qr_available);
                                    if (sd.qr_available) {
                                        clearInterval(pollForQr);
                                        console.log("[wasap] restartAndRescan: QR available, showing modal");
                                        checkStatus(); // refresh status card
                                        wasapShowQr();
                                    }
                                    if (pollCount >= maxPolls) {
                                        clearInterval(pollForQr);
                                        checkStatus();
                                        if (!sd.qr_available) {
                                            alert("WAHA no ha generado QR tras 60s. Revisa el estado de conexión.");
                                        }
                                    }
                                }).catch(function(){
                                    if (pollCount >= maxPolls) {
                                        clearInterval(pollForQr);
                                        checkStatus();
                                    }
                                });
                            }, 2000);
                        }, 10000);
                    }).catch(function(e){
                        console.log("[wasap] restartAndRescan: error", e);
                        alert("Error al reiniciar WAHA. Intenta manualmente.");
                        if (card) card.style.opacity = "1";
                        checkStatus();
                    });
                }

                // Expose to global scope
                window.wasapShowQr = wasapShowQr;
                window.wasapRegenerateQr = wasapRegenerateQr;
                window.wasapCloseQr = wasapCloseQr;
                window.wasapFetchQr = wasapFetchQr;
                window.wasapRestartWaha = wasapRestartWaha;
                window.wasapRestartAndRescan = wasapRestartAndRescan;
                wasapLog("Functions exposed to window OK");

                // Init
                wasapLog("Calling checkStatus() for the first time...");
                checkStatus();
                // Refresh every 15s
                wasapLog("Setting 15s interval for status polling");
                setInterval(function(){
                    wasapLog("Timer: calling checkStatus()");
                    checkStatus();
                }, 15000);
                wasapLog("IIFE INIT COMPLETE");

            } catch(e) {
                wasapLogErr("IIFE FATAL ERROR: " + (e.message||String(e)) + " | stack: " + (e.stack||"none"));
                var el = document.getElementById("wasapDebug");
                if (el) el.style.background = "#2d0000";
            }
            })();
            </script>';

        } else {
            // ── Sub-tab Chat: interfaz de conversación ──
            $apiBase = 'personal_wasap_api.php';
            echo '<div class="wasap-chat-shell" id="wasapChatShell">';

            // Sidebar: lista de chats
            echo '<div class="wasap-sidebar" id="wasapSidebar">';
            echo '<div class="wasap-sidebar-head">';
            echo '<input type="text" class="wasap-search" id="wasapSearch" placeholder="🔍 Buscar...">';
            echo '</div>';
            echo '<div class="wasap-chat-list" id="wasapChatList">';
            echo '<div class="wasap-chat-list-empty">Cargando conversaciones...</div>';
            echo '</div>';
            echo '</div>';

            // Panel principal de chat
            echo '<div class="wasap-chat-main" id="wasapChatMain">';
            echo '<div class="wasap-chat-header" id="wasapChatHeader">';
            echo '<div class="wasap-chat-header-info">';
            echo '<div class="wasap-chat-header-name" id="wasapChatName">Selecciona una conversación</div>';
            echo '<div class="wasap-chat-header-phone" id="wasapChatPhone"></div>';
            echo '</div>';
            echo '<button class="wasap-btn-icon" id="wasapBtnEditName" title="Editar nombre" onclick="wasapEditSelectedContact()" style="display:none">✏️</button>';
            echo '<button class="wasap-btn-icon" id="wasapBtnTts" title="Leer en voz alta" onclick="wasapReadAloud()" style="display:none">🔊</button>';
            echo '</div>';

            echo '<div class="wasap-chat-messages" id="wasapMessages">';
            echo '<div class="wasap-chat-placeholder">';
            echo '<div style="font-size:48px;margin-bottom:12px">💬</div>';
            echo '<div>Selecciona una conversación para ver los mensajes</div>';
            echo '</div>';
            echo '</div>';

            echo '<div class="wasap-input-area" id="wasapInputArea" style="display:none">';
            echo '<textarea id="wasapInputText" class="wasap-input" rows="1" placeholder="Escribe un mensaje..." onkeydown="wasapInputKey(event)"></textarea>';
            echo '<button class="wasap-btn-send" id="wasapBtnSend" onclick="wasapSendMessage()">▶</button>';
            echo '</div>';
            echo '</div>';

            echo '</div>'; // .wasap-chat-shell

            // ── JS del chat ──
            echo '<script>
            (function(){
                var api = "' . e($apiBase) . '";
                var token = "&token=wasap_personal_2026";
                var selectedChat = null;
                var chatMessages = [];
                var pollingTimer = null;
                var lastChatsJson = "";
                var lastMessagesJson = "";
                var unreadCache = {};

                // ── Cargar lista de chats ──
                function loadChats() {
                    fetch(api + "?action=chats" + token + "&_=" + Date.now())
                    .then(function(r){return r.json()})
                    .then(function(d){
                        if (!d.ok) return;
                        var json = JSON.stringify(d.chats || []);
                        if (json === lastChatsJson) return;
                        lastChatsJson = json;
                        unreadCache = {};
                        (d.chats||[]).forEach(function(c){ unreadCache[c.chat_id] = c.unread_count || 0; });
                        renderChatList(d.chats||[]);
                    }).catch(function(){});
                }

                function renderChatList(chats) {
                    var list = document.getElementById("wasapChatList");
                    if (!list) return;
                    if (!chats.length) {
                        list.innerHTML = "<div class=\"wasap-chat-list-empty\">No hay conversaciones aún</div>";
                        return;
                    }
                    var h = "";
                    for (var i = 0; i < chats.length; i++) {
                        var c = chats[i];
                        var name = c.contact_name || formatPhone(c.phone || "");
                        var unread = c.unread_count > 0 ? "<span class=\"wasap-badge\">" + c.unread_count + "</span>" : "";
                        var active = selectedChat === c.chat_id ? " active" : "";
                        var lastMsg = c.last_message || "";
                        h += "<div class=\"wasap-chat-item" + active + "\" data-chat-id=\"" + escAttr(c.chat_id) + "\" onclick=\"wasapSelectChat(\'" + escAttr(c.chat_id) + "\')\">";
                        h += "<div class=\"wasap-chat-item-top\"><span class=\"wasap-chat-item-name\">" + esc(name) + "</span>";
                        h += "<button class=\"wasap-chat-item-edit\" title=\"Renombrar\" onclick=\"event.stopPropagation();wasapRenameContact(\'" + escAttr(c.chat_id) + "\',\'" + escAttr(name) + "\')\">✏️</button>";
                        h += "</div>";
                        h += "<div class=\"wasap-chat-item-bottom\">";
                        h += "<span class=\"wasap-chat-item-phone\">" + esc(c.phone || "") + "</span>";
                        if (unread) h += unread;
                        h += "</div>";
                        if (lastMsg) h += "<div class=\"wasap-chat-item-msg\">" + esc(lastMsg) + "</div>";
                        h += "</div>";
                    }
                    list.innerHTML = h;
                }

                // ── Seleccionar chat ──
                window.wasapSelectChat = function(chatId) {
                    selectedChat = chatId;
                    chatMessages = [];
                    lastMessagesJson = "";
                    document.getElementById("wasapInputArea").style.display = "flex";
                    document.getElementById("wasapBtnTts").style.display = "inline-block";
                    document.getElementById("wasapBtnEditName").style.display = "inline-block";
                    document.getElementById("wasapChatName").textContent = "Cargando...";
                    document.getElementById("wasapChatPhone").textContent = "";
                    document.getElementById("wasapMessages").innerHTML = "";
                    // Marcar como leído
                    fetch(api + "?action=mark_read" + token, {
                        method: "POST",
                        headers: {"Content-Type":"application/x-www-form-urlencoded"},
                        body: "chat_id=" + encodeURIComponent(chatId)
                    }).catch(function(){});
                    loadMessages();
                    loadChats(); // refrescar sidebar (quitar badge)
                };

                // ── Cargar mensajes ──
                function loadMessages() {
                    if (!selectedChat) return;
                    fetch(api + "?action=messages" + token + "&chat_id=" + encodeURIComponent(selectedChat) + "&_=" + Date.now())
                    .then(function(r){return r.json()})
                    .then(function(d){
                        if (!d.ok) return;
                        var json = JSON.stringify(d.messages||[]);
                        if (json === lastMessagesJson) return;
                        lastMessagesJson = json;
                        chatMessages = d.messages||[];
                        if (d.chat) {
                            document.getElementById("wasapChatName").textContent = d.chat.contact_name || formatPhone(d.chat.contact_phone || "");
                            document.getElementById("wasapChatPhone").textContent = d.chat.contact_phone || "";
                        }
                        renderMessages();
                    }).catch(function(){});
                }

                function renderMessages() {
                    var area = document.getElementById("wasapMessages");
                    if (!area) return;
                    // Dedup por id (safety net por si el store tiene duplicados historicos)
                    var seen = {};
                    var uniq = [];
                    for (var i = 0; i < chatMessages.length; i++) {
                        var mId = chatMessages[i].id || "";
                        if (!seen[mId]) { seen[mId] = true; uniq.push(chatMessages[i]); }
                    }
                    chatMessages = uniq;
                    if (!chatMessages.length) {
                        area.innerHTML = "<div class=\"wasap-chat-placeholder\"><div>Sin mensajes</div></div>";
                        return;
                    }
                    var wasAtBottom = (area.scrollHeight - area.scrollTop - area.clientHeight) <= 80;
                    var lastDate = "";
                    var h = "";
                    for (var i = 0; i < chatMessages.length; i++) {
                        var m = chatMessages[i];
                        var d = formatMsgDate(m.ts||"");
                        if (d !== lastDate) {
                            h += "<div class=\"wasap-date-sep\"><span>" + esc(d) + "</span></div>";
                            lastDate = d;
                        }
                        var mediaHtml = wasapRenderMedia(m);
                        if (m.direction === "in") {
                            h += "<div class=\"wasap-bubble in\"><div class=\"wasap-bubble-text\">" + esc(m.text||"") + mediaHtml + "</div><div class=\"wasap-bubble-time\">" + esc(formatMsgTime(m.ts||"")) + "</div></div>";
                        } else {
                            h += "<div class=\"wasap-bubble out\"><div class=\"wasap-bubble-text\">" + esc(m.text||"") + mediaHtml + "</div><div class=\"wasap-bubble-time\">" + esc(formatMsgTime(m.ts||"")) + " ✓✓</div></div>";
                        }
                    }
                    area.innerHTML = h;
                    if (wasAtBottom) area.scrollTop = area.scrollHeight;
                }

                // ── Render de media recibida (imagen/audio/vídeo) ──
                function wasapRenderMedia(m) {
                    var media = m.media;
                    if (!media) return "";
                    var src = "";
                    if (media.instance && m.id) {
                        src = "/control/media_proxy.php?instance=" + encodeURIComponent(media.instance) + "&msg_id=" + encodeURIComponent(m.id) + "&type=" + encodeURIComponent(media.type || "");
                    } else if (media.url) {
                        src = "/control/media_proxy.php?url=" + encodeURIComponent(media.url) + "&type=" + encodeURIComponent(media.type || "");
                    }
                    if (!src) return "";
                    if (media.type === "image") {
                        return "<div style=\"margin:6px 0\"><img src=\"" + src + "\" alt=\"Imagen\" style=\"max-width:220px;max-height:260px;border-radius:8px;display:block\"></div>";
                    }
                    if (media.type === "audio") {
                        var ah = "<div style=\"margin:6px 0\"><audio controls style=\"max-width:220px\" src=\"" + src + "\"></audio>";
                        if (media.transcription) ah += "<div style=\"font-style:italic;color:var(--muted);font-size:12px;margin-top:3px\">🎙️ " + esc(media.transcription) + "</div>";
                        return ah + "</div>";
                    }
                    if (media.type === "video") {
                        return "<div style=\"margin:6px 0\"><video controls style=\"max-width:240px;border-radius:8px\" src=\"" + src + "\"></video></div>";
                    }
                    if (media.type === "document") {
                        return "<div style=\"margin:6px 0\"><a href=\"" + src + "\" target=\"_blank\" rel=\"noopener\">📄 " + esc(media.fileName || "Documento") + "</a></div>";
                    }
                    return "";
                }

                // ── Enviar mensaje ──
                window.wasapSendMessage = function() {
                    var input = document.getElementById("wasapInputText");
                    var text = (input.value||"").trim();
                    if (!text || !selectedChat) return;
                    input.value = "";
                    var btn = document.getElementById("wasapBtnSend");
                    btn.disabled = true;
                    btn.textContent = "...";
                    fetch(api + "?action=send" + token, {
                        method: "POST",
                        headers: {"Content-Type":"application/x-www-form-urlencoded"},
                        body: "chat_id=" + encodeURIComponent(selectedChat) + "&text=" + encodeURIComponent(text)
                    }).then(function(r){return r.json()}).then(function(d){
                        btn.disabled = false;
                        if (d.ok) {
                            btn.textContent = "▶";
                            loadMessages();
                            loadChats();
                        } else {
                            // Error → feedback visual, restaurar texto para reintentar
                            btn.textContent = "❌";
                            input.value = text;
                            setTimeout(function(){ if (btn) btn.textContent = "▶"; }, 1500);
                        }
                    }).catch(function(){
                        btn.disabled = false;
                        btn.textContent = "❌";
                        input.value = text;
                        setTimeout(function(){ if (btn) btn.textContent = "▶"; }, 1500);
                    });
                };

                // ── Enviar con Enter ──
                window.wasapInputKey = function(e) {
                    if (e.key === "Enter" && !e.shiftKey) {
                        e.preventDefault();
                        wasapSendMessage();
                    }
                };

                // ── Leer en voz alta ──
                window.wasapReadAloud = function() {
                    if (!chatMessages.length) return;
                    // Usar TTS del navegador
                    var unread = chatMessages.filter(function(m){ return m.direction === "in"; });
                    if (!unread.length) { alert("No hay mensajes para leer"); return; }
                    var text = unread.map(function(m){ return m.text||""; }).join(". ");
                    if ("speechSynthesis" in window) {
                        var u = new SpeechSynthesisUtterance(text);
                        u.lang = "es-ES";
                        u.rate = 1.0;
                        speechSynthesis.cancel();
                        speechSynthesis.speak(u);
                    } else {
                        alert("Tu navegador no soporta lectura en voz alta");
                    }
                };

                // ── Renombrar contacto ──
                window.wasapRenameContact = function(chatId, currentName) {
                    // Si el nombre actual está vacío o es solo un teléfono (empieza por "+"),
                    // prellenar el prompt vacío para escribir directamente el nombre real.
                    var prefill = (currentName || "").trim();
                    if (/^\+/.test(prefill)) prefill = "";
                    var newName = prompt("Nombre para este contacto:", prefill);
                    if (!newName || newName.trim() === "") return;
                    fetch(api + "?action=rename_contact" + token, {
                        method: "POST",
                        headers: {"Content-Type":"application/x-www-form-urlencoded"},
                        body: "chat_id=" + encodeURIComponent(chatId) + "&name=" + encodeURIComponent(newName.trim())
                    }).then(function(r){return r.json()}).then(function(d){
                        if (d.ok) {
                            loadChats();
                            if (selectedChat === chatId) {
                                document.getElementById("wasapChatName").textContent = d.contact_name;
                            }
                        }
                    }).catch(function(){});
                };

                // ── Editar nombre del chat abierto (desde la cabecera) ──
                window.wasapEditSelectedContact = function() {
                    if (!selectedChat) return;
                    var nameEl = document.getElementById("wasapChatName");
                    var currentName = nameEl ? nameEl.textContent : "";
                    wasapRenameContact(selectedChat, currentName);
                };

                // ── Polling ──
                function startPolling() {
                    loadChats();
                    pollingTimer = setInterval(function(){
                        loadChats();
                        if (selectedChat) loadMessages();
                    }, 3000);
                }

                // ── Helpers ──
                function esc(s) { return String(s||"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;"); }
                function escAttr(s) { return String(s||"").replace(/&/g,"&amp;").replace(/"/g,"&quot;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/\'/g,"\\\'"); }
                function formatPhone(p) { p = String(p||""); if (p.length >= 9) return "+" + p.substring(0,2) + " " + p.substring(2,5) + " " + p.substring(5,8) + " " + p.substring(8); return p; }
                function formatMsgDate(ts) {
                    if (!ts) return "";
                    var d = new Date(ts);
                    if (isNaN(d.getTime())) {
                        // intentar parsear "2026-07-27T..."
                        var parts = ts.split("T")[0];
                        if (parts) return parts.split("-").reverse().join("/");
                        return "";
                    }
                    var today = new Date();
                    if (d.toDateString() === today.toDateString()) return "Hoy";
                    var yesterday = new Date(today); yesterday.setDate(today.getDate()-1);
                    if (d.toDateString() === yesterday.toDateString()) return "Ayer";
                    return ("0"+d.getDate()).slice(-2) + "/" + ("0"+(d.getMonth()+1)).slice(-2) + "/" + d.getFullYear();
                }
                function formatMsgTime(ts) {
                    if (!ts) return "";
                    var d = new Date(ts);
                    if (isNaN(d.getTime())) {
                        var timePart = (ts||"").split("T")[1];
                        if (timePart) return timePart.substring(0,5);
                        return "";
                    }
                    return ("0"+d.getHours()).slice(-2) + ":" + ("0"+d.getMinutes()).slice(-2);
                }
                function focusInput() {
                    var inp = document.getElementById("wasapInputText");
                    if (inp) inp.focus();
                }

                // Búsqueda
                var searchInput = document.getElementById("wasapSearch");
                if (searchInput) {
                    searchInput.addEventListener("input", function(){
                        var q = (this.value||"").toLowerCase();
                        var items = document.querySelectorAll(".wasap-chat-item");
                        items.forEach(function(item){
                            var name = (item.querySelector(".wasap-chat-item-name")||{}).textContent||"";
                            var phone = (item.querySelector(".wasap-chat-item-phone")||{}).textContent||"";
                            item.style.display = (name.toLowerCase().indexOf(q) !== -1 || phone.indexOf(q) !== -1) ? "" : "none";
                        });
                    });
                }

                startPolling();
            })();
            </script>';
        }

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

// ═══════════════════════════════════════════════════════════════════════════════
// RUTAS — GPS tracking dashboard dentro de Josue
// ═══════════════════════════════════════════════════════════════════════════════

function render_rutas_tab() {
    $day = request_get('day', date('Y-m-d'));
    $allPositions = gps_read_positions(0); // sin límite de días
    $grouped      = gps_group_by_day($allPositions);

    // Días disponibles para el selector
    $availableDays = array_keys($grouped);
    rsort($availableDays); // más reciente primero

    // Si no hay datos para el día pedido, usar el último día con datos
    if (!isset($grouped[$day]) && !empty($availableDays)) {
        $day = $availableDays[0];
    }

    $dayPositions = isset($grouped[$day]) ? $grouped[$day] : array();
    $prevDayPos   = null;
    // Buscar último punto del día anterior (para dibujar continuidad)
    $prevIdx = array_search($day, $availableDays, true);
    if ($prevIdx !== false && isset($availableDays[$prevIdx + 1])) {
        $prevDayKey = $availableDays[$prevIdx + 1];
        if (!empty($grouped[$prevDayKey])) {
            $prevDayPos = end($grouped[$prevDayKey]);
        }
    }

    $kpi      = gps_kpi_summary($allPositions, 'lite');
    $timeline = gps_timeline_for_day($dayPositions);

    // ── Detección de lugares ──
    $places        = gps_detect_places($allPositions);
    $totalDaysData = gps_days_for_user($allPositions);
    $showPlaces    = $totalDaysData >= 3; // necesita al menos 3 días de datos

    // ── Curiosidades (Fase 3) ──
    $curiosities = gps_curiosities($allPositions, $places);
    $showCuriosities = $totalDaysData >= 5; // necesita al menos 5 días para curiosidades fiables

    // Preparar datos para el mapa (JS los consumirá)
    $mapPoints = array();
    foreach ($dayPositions as $p) {
        $mapPoints[] = array(
            'lat'   => (float)$p['lat'],
            'lng'   => (float)$p['lng'],
            'ts'    => $p['ts'],
            'time'  => date('H:i', $p['_ts']),
            'acc'   => (float)$p['acc'],
            'user'  => $p['user'] ?? 'unknown',
        );
    }

    $mapData = array(
        'points'     => $mapPoints,
        'prevPoint'  => $prevDayPos ? array(
            'lat'  => (float)$prevDayPos['lat'],
            'lng'  => (float)$prevDayPos['lng'],
        ) : null,
        'center'     => !empty($mapPoints) ? array(
            'lat' => $mapPoints[0]['lat'],
            'lng' => $mapPoints[0]['lng'],
        ) : null,
    );
    $mapJson = json_encode($mapData);

    echo '<div class="josue-head">';
    echo '<h2>Rutas — ' . e(gps_fmt_date($day)) . '</h2>';
    // Selector de día
    if (!empty($availableDays)) {
        echo '<form method="get" class="rutas-day-form">';
        echo '<input type="hidden" name="page" value="josue">';
        echo '<input type="hidden" name="tab" value="rutas">';
        echo '<select name="day" onchange="this.form.submit()" class="rutas-day-select">';
        foreach ($availableDays as $d) {
            $sel = ($d === $day) ? ' selected' : '';
            echo '<option value="' . e($d) . '"' . $sel . '>' . e(gps_fmt_date($d)) . '</option>';
        }
        echo '</select>';
        echo '</form>';
    }
    echo '</div>';

    // ── KPI cards ──
    $lastPos       = $kpi['last'];
    $lastActivePos = $kpi['last_active'];
    echo '<div class="cards four rutas-kpi-row">';

    // 🅿️ Último aparcamiento (solo coche/lite)
    echo '<div class="panel rutas-kpi">';
    echo '<div class="rutas-kpi-label">🅿️ Último aparcamiento</div>';
    if ($lastPos) {
        echo '<div class="rutas-kpi-coords">' . e($lastPos['lat']) . ', ' . e($lastPos['lng']) . '</div>';
        echo '<div class="rutas-kpi-meta">hace ' . e($kpi['last_ago']) . ' · precisión ' . e($lastPos['acc']) . 'm</div>';
        echo '<a class="rutas-kpi-maps" href="#" onclick="window._rutasMapGoTo(' . e($lastPos['lat']) . ',' . e($lastPos['lng']) . ',17);return false;">📍 Ver en mapa →</a>';
    } else {
        echo '<div class="rutas-kpi-meta">Sin datos todavía</div>';
    }
    // Última posición de cualquier cuenta
    if ($lastActivePos && $lastPos !== $lastActivePos && ($lastActivePos['user'] ?? '') !== 'lite') {
        echo '<div class="rutas-kpi-sub">📱 Última activa: <strong>' . e($kpi['last_active_user']) . '</strong> hace ' . e($kpi['last_active_ago']) . '</div>';
    }
    echo '</div>';

    // 📍 Hoy
    echo '<div class="panel rutas-kpi">';
    echo '<div class="rutas-kpi-label">📍 Hoy</div>';
    echo '<div class="rutas-kpi-big">' . e($kpi['today_km']) . ' <small>km</small></div>';
    echo '<div class="rutas-kpi-meta">' . e($kpi['today_trips']) . ' trayectos · ' . e($kpi['today_positions']) . ' posiciones</div>';
    echo '</div>';

    // 📊 Semana
    echo '<div class="panel rutas-kpi">';
    echo '<div class="rutas-kpi-label">📊 Esta semana</div>';
    echo '<div class="rutas-kpi-big">' . e($kpi['week_km']) . ' <small>km</small></div>';
    echo '<div class="rutas-kpi-meta">' . e($kpi['week_trips']) . ' trayectos en ' . e($kpi['week_days']) . ' días</div>';
    if ($kpi['week_avg_km'] > 0) {
        echo '<div class="rutas-kpi-sub">Media: ' . e($kpi['week_avg_km']) . ' km/día</div>';
    }
    echo '</div>';

    // 📡 Total posiciones
    echo '<div class="panel rutas-kpi">';
    echo '<div class="rutas-kpi-label">📡 Total posiciones</div>';
    echo '<div class="rutas-kpi-big">' . count($allPositions) . '</div>';
    echo '<div class="rutas-kpi-meta">' . count($availableDays) . ' días con datos</div>';
    if ($lastActivePos) {
        echo '<div class="rutas-kpi-sub">Última: <strong>' . e($kpi['last_active_user']) . '</strong> hace ' . e($kpi['last_active_ago']) . '</div>';
    }
    echo '</div>';

    echo '</div>'; // .cards.four

    // ── Lugares detectados ──
    if (!empty($places)) {
        echo '<div class="panel rutas-places-panel">';
        echo '<h3 class="rutas-section-title">🗺️ Lugares detectados <span class="rutas-badge-auto">automático</span></h3>';
        if (!$showPlaces) {
            $remaining = 3 - $totalDaysData;
            echo '<div class="rutas-places-banner">⏳ Datos preliminares · se necesitan ' . e($remaining) . ' día(s) más para afinar la detección</div>';
        }
        echo '<div class="rutas-places-grid">';
        foreach ($places as $place) {
            $confClass    = 'rutas-conf-' . $place['confidence'];
            $label        = $place['label'];
            $isCustom     = ($place['confidence'] === 'personalizada');
            $isThin       = (!$isCustom && strpos($label, '📍 Lugar') === 0 && in_array($place['confidence'], array('mínima', 'baja'), true));
            // Extraer emoji del label (si empieza con uno)
            $icon = '📍';
            $name = $label;
            if (preg_match('/^([\x{1F300}-\x{1F9FF}])/u', $label, $m)) {
                $icon = $m[1];
                $name = trim(substr($label, strlen($m[1])));
            }
            echo '<div class="rutas-place-card' . ($isThin ? ' rutas-place-unlabeled' : '') . '">';
            echo '<div class="rutas-place-icon">' . e($icon) . '</div>';
            echo '<div class="rutas-place-info">';
            // Nombre + botón editar
            echo '<div class="rutas-place-name-row">';
            echo '<span class="rutas-place-name">' . e($name) . '</span>';
            echo '<button type="button" class="rutas-place-edit-btn" onclick="var f=this.parentElement.nextElementSibling.nextElementSibling;f.style.display=f.style.display===\'block\'?\'none\':\'block\';return false;" title="Renombrar lugar">✏️</button>';
            echo '</div>';
            echo '<div class="rutas-place-coords">' . e($place['center_lat']) . ', ' . e($place['center_lng']) . '</div>';
            // Formulario inline de edición (oculto por defecto)
            echo '<form method="post" class="rutas-place-edit-form" style="display:none;">';
            echo '<input type="hidden" name="action" value="rename_place">';
            echo '<input type="hidden" name="lat" value="' . e(round($place['center_lat'], 4)) . '">';
            echo '<input type="hidden" name="lng" value="' . e(round($place['center_lng'], 4)) . '">';
            echo '<input type="text" name="name" value="' . e($name) . '" class="rutas-place-edit-input" placeholder="Nombre del lugar...">';
            echo '<button type="submit" class="rutas-place-edit-save" title="Guardar">💾</button>';
            echo '<button type="button" class="rutas-place-edit-cancel" onclick="this.parentElement.style.display=\'none\';return false;" title="Cancelar">✖</button>';
            echo '</form>';
            // Meta
            echo '<div class="rutas-place-meta">';
            echo e($place['total_hours']) . 'h total · ' . e($place['days']) . ' días · ' . e($place['points']) . ' puntos';
            echo '</div>';
            // Confianza
            echo '<div class="rutas-place-confidence ' . $confClass . '">confianza: ' . e($place['confidence']) . '</div>';
            echo '</div>';
            echo '<a class="rutas-place-maps" href="#" onclick="window._rutasMapGoTo(' . e($place['center_lat']) . ',' . e($place['center_lng']) . ',16);return false;" title="Ver en mapa">📍</a>';
            // Botón ocultar (mini form POST)
            echo '<form method="post" class="rutas-place-hide-form" style="display:inline;margin:0;padding:0;" onsubmit="return confirm(\'¿Ocultar este lugar?\');">';
            echo '<input type="hidden" name="action" value="hide_place">';
            echo '<input type="hidden" name="lat" value="' . e(round($place['center_lat'], 4)) . '">';
            echo '<input type="hidden" name="lng" value="' . e(round($place['center_lng'], 4)) . '">';
            echo '<button type="submit" class="rutas-place-hide-btn" title="Ocultar lugar">🗑️</button>';
            echo '</form>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    } elseif (!$showPlaces && !empty($allPositions)) {
        $remaining = 3 - $totalDaysData;
        echo '<div class="panel rutas-places-panel rutas-places-waiting">';
        echo '<div class="rutas-empty-text">⏳ Recopilando patrones…</div>';
        echo '<div class="rutas-empty-hint">Se necesitan al menos 3 días de datos GPS para detectar lugares automáticamente. Te quedan ' . e($remaining) . ' día(s). Mientras tanto puedes ver las posiciones en el mapa y el timeline.</div>';
        echo '</div>';
    }

    // ── Mapa ──
    if (!empty($mapPoints)) {
        echo '<div class="panel rutas-map-panel">';
        echo '<div id="rutasMapNav" class="rutas-map-nav" style="display:none;"><button class="rutas-map-nav-btn" onclick="window._rutasMapReset();">← Ver día completo</button><span id="rutasMapNavLabel" class="rutas-map-nav-label"></span></div>';
        echo '<div id="rutasMap" class="rutas-map"></div>';
        echo '</div>';
    } else {
        echo '<div class="panel rutas-map-panel rutas-empty">';
        echo '<div class="rutas-empty-text">📡 No hay datos GPS para ' . e(gps_fmt_date($day)) . '</div>';
        echo '<div class="rutas-empty-hint">El tracking GPS se activa automáticamente al abrir la app. Los datos aparecerán aquí cuando el dispositivo empiece a enviar posiciones.</div>';
        echo '</div>';
    }

    // ── Timeline ──
    if (!empty($timeline)) {
        echo '<div class="panel rutas-timeline-panel">';
        echo '<h3 class="rutas-section-title">⏱️ Cronología del día</h3>';
        echo '<div class="rutas-timeline">';
        foreach ($timeline as $evIdx => $ev) {
            // Buscar el lugar más cercano para enriquecer la etiqueta
            $nearbyPlace = $showPlaces ? gps_match_position_to_place($ev['lat'], $ev['lng'], $places) : null;
            $placeName   = $nearbyPlace ? $nearbyPlace['label'] : null;

            if ($ev['type'] === 'trip_end') {
                $dist  = isset($ev['distance']) ? $ev['distance'] . ' km' : '';
                $dur   = gps_fmt_duration($ev['duration']);
                $lbl   = $placeName ? 'Trayecto → ' . $placeName : 'Trayecto';

                // Datos del trayecto para el mapa
                $tripData = json_encode(array(
                    'startLat' => (float)$ev['start_lat'],
                    'startLng' => (float)$ev['start_lng'],
                    'endLat'   => (float)$ev['end_lat'],
                    'endLng'   => (float)$ev['end_lng'],
                    'label'    => $lbl . ' · ' . $dist . ($dist && $dur ? ' · ' : '') . $dur,
                    'points'   => isset($ev['route_points']) ? $ev['route_points'] : array(),
                ));

                echo '<div class="rutas-timeline-item rutas-timeline-trip rutas-clickable" data-trip=\'' . $tripData . '\' onclick="window._rutasMapShowTrip(JSON.parse(this.getAttribute(\'data-trip\')));">';
                echo '<span class="rutas-timeline-time">' . e($ev['time']) . '</span>';
                echo '<span class="rutas-timeline-icon">🚗</span>';
                echo '<div class="rutas-timeline-body">';
                echo '<span class="rutas-timeline-label">' . e($lbl) . '</span>';
                if ($dist || $dur) {
                    echo '<span class="rutas-timeline-detail">' . e($dist . ($dist && $dur ? ' · ' : '') . $dur) . '</span>';
                }
                echo '<span class="rutas-timeline-maps">📍 Ver ruta</span>';
                echo '</div>';
                echo '</div>';
            } elseif ($ev['type'] === 'stop_end') {
                $dur  = gps_fmt_duration($ev['duration']);
                $lbl  = $placeName ? 'Aparcado en ' . $placeName : 'Aparcado';
                echo '<div class="rutas-timeline-item rutas-timeline-stop rutas-clickable" onclick="window._rutasMapGoTo(' . e($ev['lat']) . ',' . e($ev['lng']) . ',16);">';
                echo '<span class="rutas-timeline-time">' . e($ev['time']) . '</span>';
                echo '<span class="rutas-timeline-icon">📌</span>';
                echo '<div class="rutas-timeline-body">';
                echo '<span class="rutas-timeline-label">' . e($lbl) . '</span>';
                if ($dur) {
                    echo '<span class="rutas-timeline-detail">' . e($dur) . '</span>';
                }
                echo '<span class="rutas-timeline-maps">📍 Ver</span>';
                echo '</div>';
                echo '</div>';
            }
        }
        echo '</div>';
        echo '</div>';
    } elseif (!empty($dayPositions)) {
        echo '<div class="panel rutas-timeline-panel">';
        echo '<h3 class="rutas-section-title">⏱️ Cronología del día</h3>';
        echo '<div class="rutas-timeline-simple">';
        foreach ($dayPositions as $p) {
            $nearbyPlace = $showPlaces ? gps_match_position_to_place($p['lat'], $p['lng'], $places) : null;
            $placeLabel  = $nearbyPlace ? ' · ' . $nearbyPlace['label'] : '';
            echo '<div class="rutas-timeline-item rutas-timeline-point rutas-clickable" onclick="window._rutasMapGoTo(' . e($p['lat']) . ',' . e($p['lng']) . ',16);">';
            echo '<span class="rutas-timeline-time">' . e(date('H:i', $p['_ts'])) . '</span>';
            echo '<span class="rutas-timeline-icon">📍</span>';
            echo '<div class="rutas-timeline-body">';
            echo '<span class="rutas-timeline-coords">' . e($p['lat']) . ', ' . e($p['lng']) . e($placeLabel) . '</span>';
            echo '<span class="rutas-timeline-maps">📍 Ver</span>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }

    // ── Curiosidades ──
    if ($totalDaysData >= 1 && !empty($allPositions)) {
        $c = $curiosities;
        echo '<div class="panel rutas-curiosidades-panel">';

        // Cabecera con botón export
        echo '<div class="rutas-curiosidades-head">';
        echo '<h3 class="rutas-section-title">💡 Curiosidades</h3>';
        if (!empty($dayPositions)) {
            echo '<a class="btn-secondary-mini" href="index.php?action=export_gpx&day=' . e($day) . '" download>📥 Exportar GPX</a>';
        }
        echo '</div>';

        if (!$showCuriosities) {
            $remaining = 5 - $totalDaysData;
            echo '<div class="rutas-places-banner">⏳ Datos preliminares · ' . e($remaining) . ' día(s) más para estadísticas completas</div>';
        }

        $hasAnyCard = false;
        echo '<div class="cards two rutas-curiosidades-grid">';

        // ── Simple stats when early ──
        if (!$showCuriosities) {
            $hasAnyCard = true;
            // Mostrar stats básicas disponibles desde el día 1
            echo '<div class="panel rutas-curio-card">';
            echo '<div class="rutas-curio-icon">📊</div>';
            echo '<div class="rutas-curio-body">';
            echo '<div class="rutas-curio-title">Estadísticas básicas</div>';
            echo '<div class="rutas-curio-big">' . e(count($availableDays)) . ' <small>días</small></div>';
            echo '<div class="rutas-curio-meta">' . e(count($allPositions)) . ' posiciones totales</div>';
            echo '</div>';
            echo '</div>';

            if ($c['max_km_day'] && $c['max_km_day']['km'] > 0) {
                echo '<div class="panel rutas-curio-card">';
                echo '<div class="rutas-curio-icon">🏆</div>';
                echo '<div class="rutas-curio-body">';
                echo '<div class="rutas-curio-title">Día con más km</div>';
                echo '<div class="rutas-curio-big">' . e($c['max_km_day']['km']) . ' <small>km</small></div>';
                echo '<div class="rutas-curio-meta">' . e(gps_fmt_date($c['max_km_day']['date'])) . '</div>';
                echo '</div>';
                echo '</div>';
            }
        }

        // ── Rachas ──
        if ($c['streaks']['current'] > 0 || $c['streaks']['longest'] > 0) {
            $hasAnyCard = true;
            echo '<div class="panel rutas-curio-card">';
            echo '<div class="rutas-curio-icon">🔥</div>';
            echo '<div class="rutas-curio-body">';
            echo '<div class="rutas-curio-title">Racha de visitas a ' . e(str_replace(array('🏠 ','🏢 ','📍 '), '', $c['streaks']['place'])) . '</div>';
            if ($c['streaks']['current'] > 0) {
                echo '<div class="rutas-curio-big">' . e($c['streaks']['current']) . ' <small>días seguidos</small></div>';
            }
            echo '<div class="rutas-curio-meta">Récord: ' . e($c['streaks']['longest']) . ' días</div>';
            echo '</div>';
            echo '</div>';
        }

        // ── Comparativa mensual ──
        if ($c['comparison']['last_month_km'] > 0) {
            $hasAnyCard = true;
            $pct = $c['comparison']['pct_change'];
            $arrow = $pct > 0 ? '📈' : ($pct < 0 ? '📉' : '➡️');
            $color = $pct > 0 ? '#22c55e' : ($pct < 0 ? '#ef4444' : '');
            echo '<div class="panel rutas-curio-card">';
            echo '<div class="rutas-curio-icon">📊</div>';
            echo '<div class="rutas-curio-body">';
            echo '<div class="rutas-curio-title">Este mes vs mes pasado</div>';
            echo '<div class="rutas-curio-big">' . e($c['comparison']['this_month_km']) . ' <small>km</small></div>';
            echo '<div class="rutas-curio-meta">';
            echo $arrow . ' <span' . ($color ? ' style="color:' . $color . '"' : '') . '>' . e($pct > 0 ? '+' : '') . e($pct) . '%</span> vs ' . e($c['comparison']['last_month_km']) . ' km';
            echo '</div>';
            echo '<div class="rutas-curio-meta">' . e($c['comparison']['this_month_trips']) . ' trayectos en ' . e($c['comparison']['this_month_days']) . ' días</div>';
            echo '</div>';
            echo '</div>';
        }

        // ── Hora punta ──
        if ($c['peak_hours']) {
            $hasAnyCard = true;
            $ph = $c['peak_hours'];
            echo '<div class="panel rutas-curio-card">';
            echo '<div class="rutas-curio-icon">⏰</div>';
            echo '<div class="rutas-curio-body">';
            echo '<div class="rutas-curio-title">Hora punta en ' . e(str_replace(array('🏠 ','🏢 ','📍 '), '', $ph['place'])) . '</div>';
            echo '<div class="rutas-curio-row">';
            echo '<div><span class="rutas-curio-label">Llegada</span> <span class="rutas-curio-value">' . e($ph['avg_arrival']) . '</span></div>';
            echo '<div><span class="rutas-curio-label">Salida</span> <span class="rutas-curio-value">' . e($ph['avg_departure']) . '</span></div>';
            echo '</div>';
            echo '<div class="rutas-curio-meta">Rango: ' . e($ph['arrival_range']) . ' – ' . e($ph['departure_range']) . ' (' . e($ph['sample_size']) . ' visitas)</div>';
            echo '</div>';
            echo '</div>';
        }

        // ── Día récord ──
        if ($c['max_km_day'] && $c['max_km_day']['km'] > 0 && $showCuriosities) {
            $hasAnyCard = true;
            echo '<div class="panel rutas-curio-card">';
            echo '<div class="rutas-curio-icon">🏆</div>';
            echo '<div class="rutas-curio-body">';
            echo '<div class="rutas-curio-title">Día con más km</div>';
            echo '<div class="rutas-curio-big">' . e($c['max_km_day']['km']) . ' <small>km</small></div>';
            echo '<div class="rutas-curio-meta">' . e(gps_fmt_date($c['max_km_day']['date'])) . '</div>';
            echo '</div>';
            echo '</div>';
        }

        // ── Distribución del tiempo ──
        if (!empty($c['place_hours'])) {
            $hasAnyCard = true;
            echo '<div class="panel rutas-curio-card rutas-curio-full">';
            echo '<div class="rutas-curio-icon">⏱️</div>';
            echo '<div class="rutas-curio-body">';
            echo '<div class="rutas-curio-title">Distribución del tiempo</div>';
            echo '<div class="rutas-curio-bars">';
            foreach ($c['place_hours'] as $ph) {
                echo '<div class="rutas-curio-bar-row">';
                echo '<span class="rutas-curio-bar-label">' . e($ph['label']) . '</span>';
                echo '<span class="rutas-curio-bar-track"><span class="rutas-curio-bar-fill" style="width:' . e($ph['pct']) . '%"></span></span>';
                echo '<span class="rutas-curio-bar-pct">' . e($ph['pct']) . '%</span>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }

        if (!$hasAnyCard && !$showCuriosities) {
            echo '<div class="rutas-empty-text" style="grid-column:1/-1;text-align:center;padding:20px;">📡 Esperando más datos para mostrar curiosidades…</div>';
        }

        echo '</div>'; // .cards.two
        echo '</div>'; // .rutas-curiosidades-panel
    }

    // ── Embed map data for JS ──
    if (!empty($mapPoints)) {
        echo '<script>';
        echo 'window._rutasMapData = ' . $mapJson . ';';
        echo 'if (typeof window._loadRutasMap === "function") window._loadRutasMap();';
        echo '</script>';
    }
}

/**
 * Formatea una fecha YYYY-MM-DD para mostrar.
 */
function gps_fmt_date($ymd) {
    $ts = strtotime($ymd);
    if ($ts === false) return $ymd;
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    if ($ymd === $today) return 'Hoy (' . date('d/m', $ts) . ')';
    if ($ymd === $yesterday) return 'Ayer (' . date('d/m', $ts) . ')';
    // Días de la semana en español
    $dias = array('Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado');
    $diaSem = $dias[(int)date('w', $ts)];
    return $diaSem . ' ' . date('d/m/Y', $ts);
}

// ═══════════════════════════════════════════════════════════════════
// DIARIO — Tab en Josue (solo lectura)
// ═══════════════════════════════════════════════════════════════════

function render_diario_tab() {
    echo '<div class="josue-head">';
    echo '<h2>Diario</h2>';
    echo '<div class="diary-search-wrap">';
    echo '<input type="text" id="diarySearch" class="josue-input" placeholder="Buscar en el diario..." style="max-width:320px">';
    echo '</div>';
    echo '</div>';

    echo '<div class="diary-timeline" id="diaryTimeline">';
    echo '<div class="diary-loading">Cargando entradas...</div>';
    echo '</div>';

    echo '<div id="diaryLoadMore" style="text-align:center;padding:16px;display:none">';
    echo '<button class="btn-secondary" id="diaryLoadMoreBtn">Cargar más</button>';
    echo '</div>';
}

function render_diario_entry_card($entry) {
    $fecha = $entry['fecha'] ?? '';
    $cleanText = $entry['clean_text'] ?? '';
    $rawText = $entry['raw_text'] ?? '';
    $mood = $entry['mood'] ?? 'neutro';
    $highlights = $entry['highlights'] ?? array();
    $tags = $entry['tags'] ?? array();
    $fragmentos = $entry['fragmentos'] ?? 0;

    $moodEmojis = array(
        'motivado' => '😊', 'feliz' => '😄', 'ilusionado' => '🤩',
        'neutro' => '😐', 'cansado' => '😴', 'preocupado' => '😟',
        'frustrado' => '😤', 'estresado' => '😰',
    );
    $moodEmoji = $moodEmojis[$mood] ?? '😐';

    // Formatear fecha
    $ts = strtotime($fecha);
    $fechaFormat = $ts ? date('D d', $ts) : $fecha;

    echo '<div class="diary-entry" data-fecha="' . e($fecha) . '" data-search-text="' . e(strtolower($cleanText . ' ' . implode(' ', $tags))) . '">';
    echo '<div class="diary-entry-header">';
    echo '<span class="diary-entry-date">' . e($fechaFormat) . '</span>';
    echo '<span class="diary-entry-mood" title="' . e($mood) . '">' . $moodEmoji . '</span>';
    echo '<span class="diary-entry-frags">' . (int)$fragmentos . ' fragmentos</span>';
    echo '</div>';

    echo '<div class="diary-entry-body">';
    echo '<p class="diary-entry-text">' . nl2br(e($cleanText)) . '</p>';
    echo '</div>';

    if (!empty($highlights)) {
        echo '<div class="diary-entry-highlights">';
        foreach ($highlights as $h) {
            echo '<span class="diary-highlight-tag">' . e($h) . '</span>';
        }
        echo '</div>';
    }

    if (!empty($tags)) {
        echo '<div class="diary-entry-tags">';
        foreach ($tags as $tag) {
            echo '<span class="diary-tag">' . e($tag) . '</span>';
        }
        echo '</div>';
    }

    // Toggle para transcripción literal
    $entryId = 'diary-raw-' . md5($fecha);
    echo '<button class="diary-raw-toggle" onclick="var el=document.getElementById(\'' . $entryId . '\');el.style.display=el.style.display===\'none\'?\'block\':\'none\';this.textContent=el.style.display===\'none\'?\'📝 Ver transcripción literal\':\'📝 Ocultar transcripción literal\';">📝 Ver transcripción literal</button>';
    echo '<div class="diary-raw-text" id="' . $entryId . '" style="display:none">';
    echo '<pre>' . e($rawText) . '</pre>';
    echo '</div>';

    echo '</div>';
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
    $allowed = array('interesadas', 'clientas', 'ventas', 'informes', 'deudas');
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
    echo '<a class="subtab ' . ($tab === 'deudas' ? 'active' : '') . '" href="index.php?page=jostal&tab=deudas">Deudas</a>';
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

        $showDeudaBtn = $edit && (($edit['modo'] ?? '') === 'alquiler');
        if (!empty($formSource['telefono']) || $showDeudaBtn) {
            echo '<div class="section-head-actions">';
            if (!empty($formSource['telefono'])) {
                echo '<a class="btn-wa" href="' . e(whatsapp_url($formSource['telefono'])) . '" target="_blank" rel="noopener noreferrer">Abrir WhatsApp</a>';
            }
            if ($showDeudaBtn) {
                echo '<a class="btn-secondary-mini" href="index.php?page=jostal&tab=deudas&clienta_id=' . urlencode((string)$edit['id']) . '">Ver deuda</a>';
            }
            echo '</div>';
        }
        echo '</div>';

        echo '<form method="post" class="form-grid">';
        echo '<input type="hidden" name="action" value="save_jostal_clienta">';
        echo '<input type="hidden" name="id" value="' . e($edit['id'] ?? '') . '">';
        echo '<input type="hidden" name="source_interesada_id" value="' . e($isNew ? ($newFromInteresada['id'] ?? '') : ($edit['source_interesada_id'] ?? '')) . '">';
        field_input('nombre', 'Nombre', $edit['nombre'] ?? '', true);
        field_input('telefono', 'Teléfono', $edit['telefono'] ?? ($newFromInteresada['telefono'] ?? ''), true);
        field_input('nombre_real', 'Nombre real', $edit['nombre_real'] ?? '', false);
        field_input('dni', 'DNI/NIE/Pasaporte', $edit['dni'] ?? '', false);

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

        $precioSemanalActual = $edit ? jostal_precio_semanal($edit) : null;
        field_input('precio_semanal', 'Precio semanal alquiler (€)', $precioSemanalActual !== null ? $precioSemanalActual : '', false);
        field_input('precio_semanal_anterior', 'Precio anterior (€, opcional)', $edit['precio_semanal_anterior'] ?? '', false);
        field_input('precio_semanal_desde', 'Precio actual desde (opcional)', $edit['precio_semanal_desde'] ?? '', false, 'date');
        echo '<div class="field-help" style="grid-column:1/-1;">Si rellenas "precio anterior" y "desde", las semanas anteriores a esa fecha usarán el precio anterior (ej. 130€ antes, 150€ después).</div>';

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
                echo '<div class="table-wrap"><table data-no-card-view><thead><tr><th>Entrada</th><th>Salida</th><th>Estado</th></tr></thead><tbody id="jostalPeriodosRows">';
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

            // --- CONTRATO SECTION ---
            echo '<hr class="sep">';
            echo '<h2>Contrato de uso de habitación</h2>';

            $contrato = contrato_find_by_clienta($edit['id']);

            if (!$contrato) {
                echo '<div class="contrato-section contrato-empty">';
                echo '<div class="contrato-empty-icon">📄</div>';
                echo '<p><strong>Esta clienta no tiene contrato.</strong></p>';
                echo '<p>El contrato es necesario para dejar claras las condiciones de ocupación, pago y convivencia.</p>';
                echo '<form method="post" class="inline-form">';
                echo '<input type="hidden" name="action" value="save_jostal_contrato">';
                echo '<input type="hidden" name="clienta_id" value="' . e($edit['id']) . '">';
                echo '<input type="hidden" name="ocupante_nombre_real" value="' . e($edit['nombre_real'] ?? $edit['nombre'] ?? '') . '">';
                echo '<input type="hidden" name="ocupante_dni" value="' . e($edit['dni'] ?? '') . '">';
                echo '<input type="hidden" name="ocupante_telefono" value="' . e($edit['telefono'] ?? '') . '">';
                echo '<button class="btn-primary">Crear contrato</button>';
                echo '</form>';
                echo '</div>';
            } else {
                $estadoLabel = ($contrato['estado'] === 'firmado') ? '✅ Firmado' : (($contrato['estado'] === 'enviado') ? '📤 Enviado' : '📝 Borrador');
                echo '<div class="contrato-section contrato-exists">';
                echo '<div class="contrato-header">';
                echo '<span class="contrato-badge contrato-badge--' . e($contrato['estado']) . '">' . $estadoLabel . '</span>';
                if ($contrato['estado'] !== 'borrador') {
                    $urlFirma = contrato_generar_url_firma($contrato);
                    echo '<div class="contrato-actions">';
                    echo '<button type="button" class="btn-wa-mini" onclick="window.open(\'https://wa.me/?text=\' + encodeURIComponent(\'Firma tu contrato: ' . e($urlFirma) . '\'), \'_blank\')">📱 WhatsApp</button>';
                    echo '<button type="button" class="btn-secondary-mini" onclick="copyToClipboard(\'' . e($urlFirma) . '\');showToast(\'Enlace copiado\',\'ok\')">📋 Copiar enlace</button>';
                    echo '</div>';
                }
                echo '</div>';
                echo '<form method="post" class="form-grid contrato-form">';
                echo '<input type="hidden" name="action" value="save_jostal_contrato">';
                echo '<input type="hidden" name="clienta_id" value="' . e($edit['id']) . '">';
                echo '<div class="field"><strong>Datos del titular (Josué):</strong></div>';
                field_input('arrendadora_nombre', 'Nombre', $contrato['datos_arrendadora']['nombre'] ?? 'Josué', true);
                field_input('arrendadora_dni', 'DNI', $contrato['datos_arrendadora']['dni'] ?? '', false);
                field_input('arrendadora_telefono', 'Teléfono', $contrato['datos_arrendadora']['telefono'] ?? '', false);
                field_input('arrendadora_domicilio', 'Domicilio', $contrato['datos_arrendadora']['domicilio'] ?? '', false);
                echo '<div class="field"><strong>Datos de la ocupante:</strong></div>';
                field_input('ocupante_nombre_real', 'Nombre real', $contrato['datos_ocupante']['nombre_real'] ?? ($edit['nombre_real'] ?? $edit['nombre'] ?? ''), true);
                field_input('ocupante_dni', 'DNI/NIE/Pasaporte', $contrato['datos_ocupante']['dni'] ?? ($edit['dni'] ?? ''), false);
                field_input('ocupante_telefono', 'Teléfono', $contrato['datos_ocupante']['telefono'] ?? ($edit['telefono'] ?? ''), false);
                echo '<div class="field"><strong>Habitación:</strong></div>';
                field_input('habitacion_plaza', 'Habitación o plaza', $contrato['habitacion_plaza'] ?? '', true);
                field_input('direccion_inmueble', 'Dirección del inmueble', $contrato['direccion_inmueble'] ?? '', false);
                echo '<div class="field"><strong>Precio:</strong></div>';
                field_input('precio_semanal', 'Precio semanal (€)', $contrato['precio_semanal'] ?? '', true);
                field_input('fianza', 'Fianza (€)', $contrato['fianza'] ?? '', true);
                echo '<div class="field"><strong>Contenido de la habitación:</strong></div>';
                $contenidoTxt = is_array($contrato['contenido_habitacion'] ?? null) ? implode("\n", $contrato['contenido_habitacion']) : '';
                field_textarea('contenido_habitacion', 'Una línea por cada item (ej: 3 sábanas, 2 toallas, 1 mando TV...)', $contenidoTxt, 6);
                echo '<div class="full"><button class="btn-primary">Guardar contrato</button></div>';
                echo '</form>';
                echo '<div class="contrato-fechas">';
                echo '<small>📅 Vigencia: ' . e($contrato['fecha_inicio'] ?? '—') . ' hasta ' . e($contrato['fecha_fin'] ?? '—') . ' (15 días, se renueva automáticamente)</small>';
                echo '</div>';
                echo '</div>';
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
                        echo '<td style="display:flex;gap:4px;">';
                        echo '<a class="btn-secondary-mini" href="' . e('index.php?page=jostal&tab=clientas&edit=' . urlencode($edit['id']) . '&editlead=' . urlencode($lid)) . '">Editar</a>';
                        echo '<form method="post" class="inline-form" onsubmit="return confirm(\'¿Seguro que quieres eliminar este lead?\')">';
                        echo '<input type="hidden" name="action" value="jostal_delete_lead">';
                        echo '<input type="hidden" name="lead_id" value="' . e($lid) . '">';
                        echo '<input type="hidden" name="clienta_id" value="' . e($edit['id']) . '">';
                        echo '<button class="btn-danger-mini">Eliminar</button>';
                        echo '</form>';
                        echo '</td>';
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

                    $sinContrato = jostal_clienta_en_casa($row) && !contrato_clienta_tiene_contrato_firmado($row['id']);
                    echo '<tr data-filter-text="' . e($searchText) . '" class="' . ($sinContrato ? 'row-warning' : '') . '">';
                    echo '<td><strong>' . e($row['nombre'] ?? '') . '</strong></td>';
                    echo '<td>'; crm_render_phone_value((string)($row['telefono'] ?? '')); echo '</td>';
                    echo '<td>' . e($row['modo'] ?? '') . '</td>';
                    echo '<td>' . e(jostal_clienta_en_casa($row) ? 'En casa' : 'Fuera') . '</td>';
                    echo '<td>' . e($row['observaciones'] ?? '') . '</td>';
                    echo '<td>';
                    if ($sinContrato) {
                        echo '<span class="contrato-badge contrato-badge--warning" title="Sin contrato firmado">⚠ Sin contrato</span> ';
                    }
                    echo '<a class="mini-link" href="index.php?page=jostal&tab=clientas&edit=' . e($row['id']) . '">Abrir ficha</a></td>';
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
            echo '<div class="table-wrap"><table data-no-card-view><thead><tr>';
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

    if ($tab === 'deudas') {
        $desde = trim(request_get('desde', ''));
        $hasta = trim(request_get('hasta', ''));
        $clientaIdFilter = trim(request_get('clienta_id', ''));
        $fuente = trim(request_get('fuente', 'alquiler'));
        if ($fuente !== 'semana') $fuente = 'alquiler';
        $esSemana = ($fuente === 'semana');
        $rangoValidado = jostal_validar_rango_fechas($desde, $hasta);
        $rangeError = empty($rangoValidado['ok']) ? (string)$rangoValidado['error'] : '';

        $clientasDeuda = array();
        foreach ($clientas as $c) {
            if (($c['modo'] ?? '') !== 'alquiler') continue;
            if ($clientaIdFilter !== '') {
                if ((string)($c['id'] ?? '') !== $clientaIdFilter) continue;
            } else {
                if (!jostal_clienta_en_casa($c)) continue;
            }
            $clientasDeuda[] = $c;
        }
        usort($clientasDeuda, function ($a, $b) {
            $pa = jostal_periodo_actual($a);
            $pb = jostal_periodo_actual($b);
            return strcmp($pa['entrada'] ?? '', $pb['entrada'] ?? '');
        });

        echo '<div class="section-head">';
        echo '<div>';
        echo '<h2>' . ($clientaIdFilter !== '' ? 'Informe de deuda — clienta' : 'Informe de deuda — alquiler en casa') . '</h2>';
        echo '<div class="muted">Detalle semanal y deuda acumulada. El rango recalcula la deuda usando solo las semanas y pagos incluidos entre desde y hasta (ambas fechas inclusive).</div>';
        echo '</div>';
        if ($clientaIdFilter !== '') {
            echo '<div class="section-head-actions">';
            echo '<a class="btn-secondary-mini" href="index.php?page=jostal&tab=deudas' . ($esSemana ? '&fuente=semana' : '') . '">Ver todas</a>';
            echo '</div>';
        }
        echo '</div>';

        // Filtros de rango
        echo '<form method="get" class="toolbar">';
        echo '<input type="hidden" name="page" value="jostal">';
        echo '<input type="hidden" name="tab" value="deudas">';
        if ($clientaIdFilter !== '') {
            echo '<input type="hidden" name="clienta_id" value="' . e($clientaIdFilter) . '">';
        }
        if ($esSemana) {
            echo '<input type="hidden" name="fuente" value="semana">';
        }
        echo '<div class="field"><label>Desde</label><input type="date" name="desde" value="' . e($desde) . '"></div>';
        echo '<div class="field"><label>Hasta</label><input type="date" name="hasta" value="' . e($hasta) . '"></div>';
        echo '<div class="field field-btn"><label>&nbsp;</label><button class="btn-primary">Filtrar</button></div>';
        if ($desde !== '' || $hasta !== '') {
            echo '<div class="field field-btn"><label>&nbsp;</label><a class="btn-secondary-mini" href="index.php?page=jostal&tab=deudas' . ($clientaIdFilter !== '' ? '&clienta_id=' . urlencode($clientaIdFilter) : '') . ($esSemana ? '&fuente=semana' : '') . '">Todo el tiempo</a></div>';
        }
        echo '</form>';

        // Selector de fuente de datos (qué columna alimenta la deuda).
        $baseQs = 'index.php?page=jostal&tab=deudas'
            . ($clientaIdFilter !== '' ? '&clienta_id=' . urlencode($clientaIdFilter) : '')
            . ($desde !== '' ? '&desde=' . urlencode($desde) : '')
            . ($hasta !== '' ? '&hasta=' . urlencode($hasta) : '');
        echo '<div class="toolbar" style="margin-bottom:12px;">';
        echo '<span class="inline-label">Fuente de datos:</span>';
        echo '<a class="' . (!$esSemana ? 'btn-primary' : 'btn-secondary-mini') . '" href="' . e($baseQs) . '">Pago alquiler (cubre)</a>';
        echo '<a class="' . ($esSemana ? 'btn-primary' : 'btn-secondary-mini') . '" href="' . e($baseQs . '&fuente=semana') . '">Pago esta semana</a>';
        echo '</div>';

        echo '<div class="info-strip" style="background:rgba(59,130,246,.10);border-left-color:#3b82f6;margin-bottom:14px;">';
        if ($esSemana) {
            echo '<strong>Fuente activa: Pago esta semana.</strong> Cada semana se evalúa por sí misma (lo que entregó esa semana frente a lo que debía), sin reparto FIFO. ';
            echo 'La única compensación válida es el sobrante de una semana, que cubre <span style="color:#fbbf24;font-weight:700;">→ la deuda de la semana anterior</span> y, si no la hay, <span style="color:#fb923c;font-weight:700;">→ la semana siguiente (adelanto)</span>; lo que sobre queda a favor. ';
            echo 'Los pagos "no alquiler" (cliente, fianza, taxi…) se muestran aparte y no suman en ningún sitio.';
            echo '<br><strong>Lectura de la tabla:</strong> <em>Pagó esta semana</em> es la fuente (lo que entregó por fecha); <em>Pagos alquiler (cubre)</em> queda como referencia FIFO; <em>Deuda semana</em> y <em>Acumulado</em> salen de la fuente activa.';
        } else {
            echo '<strong>Fuente activa: Pago alquiler (cubre).</strong> Cada pago cubre la semana más antigua con deuda pendiente (FIFO). ';
            echo 'Si paga más de lo que debe esa semana, el sobrante se aplica a la siguiente. ';
            echo 'En "Pagos alquiler", los pagos resaltados en ámbar salen de su fecha: <span style="color:#fbbf24;font-weight:700;">⤴ adelanto</span> = pagó antes de que empezara la semana y cubre una posterior; ';
            echo '<span style="color:#fb923c;font-weight:700;">↩ compensa sem. X</span> = pago tardío que cubre la deuda de una semana anterior. ';
            echo 'Los pagos "no alquiler" (cliente, fianza, taxi…) se muestran aparte y no suman en ningún sitio.';
            echo '<br><strong>Lectura de la tabla:</strong> <em>Pagó esta semana</em> es lo que entregó esa semana (lo que ella recuerda); <em>Deuda semana</em> es lo que suma (o no) esta semana; y <em>Acumulado</em> = deuda acumulada. Así se ve si la deuda es antigua o nueva.';
        }
        echo '</div>';

        if ($rangeError !== '') {
            echo '<div class="info-strip" style="background:rgba(239,68,68,.10);border-left-color:#ef4444;">' . e($rangeError) . '</div>';
        } elseif (empty($clientasDeuda)) {
            if ($clientaIdFilter !== '') {
                echo '<div class="empty">No se encontró la clienta o no está en modo alquiler.</div>';
            } else {
                echo '<div class="empty">No hay clientas en modo alquiler actualmente en casa.</div>';
            }
        } else {
            // Calcular todos los informes y recopilar dudosos.
            $reportes = array();
            $dudosos = array();

            foreach ($clientasDeuda as $c) {
                $data = jostal_compute_deuda($c, $leads, array(), $desde, $hasta);
                $nombre = trim((string)($c['nombre'] ?? ''));
                $nombreReal = trim((string)($c['nombre_real'] ?? ''));
                $display = $nombre . ($nombreReal !== '' && mb_strtolower($nombreReal, 'UTF-8') !== mb_strtolower($nombre, 'UTF-8') ? ' (' . $nombreReal . ')' : '');

                if (isset($data['error'])) {
                    $reportes[] = array('clienta' => $c, 'nombre' => $display, 'data' => $data, 'error' => $data['error']);
                    continue;
                }

                $reportes[] = array('clienta' => $c, 'nombre' => $display, 'data' => $data, 'error' => null);

                foreach ((array)($data['dudosos'] ?? array()) as $d) {
                    $d['clienta_nombre'] = $display;
                    $d['clienta_id'] = (string)($c['id'] ?? '');
                    $dudosos[] = $d;
                }
            }

            // ── Bloqueo por pagos dudosos ──
            if (!empty($dudosos)) {
                echo '<section class="panel">';
                echo '<div class="info-strip" style="background:rgba(245,158,11,.12);border-left-color:#f59e0b;margin-bottom:14px;">';
                echo '<strong>⚠️ Hay pagos sin clasificar.</strong> Para calcular el informe, aclara cada uno si es alquiler o no. Tu decisión se guarda y no volverá a preguntar.';
                echo '</div>';
                echo '<table class="debt-table"><thead><tr>';
                echo '<th>Clienta</th><th>Fecha</th><th>Importe</th><th>Concepto</th><th>Motivo de duda</th><th>Acción</th>';
                echo '</tr></thead><tbody>';
                foreach ($dudosos as $d) {
                    echo '<tr>';
                    echo '<td><strong>' . e($d['clienta_nombre']) . '</strong></td>';
                    echo '<td>' . e(jostal_fecha_corta($d['date'])) . '</td>';
                    echo '<td><span class="money-chip">' . e(euro($d['amount'])) . '</span></td>';
                    echo '<td>' . e($d['concepto'] !== '' ? $d['concepto'] : '(vacío)') . '</td>';
                    echo '<td class="muted">' . e($d['razon']) . '</td>';
                    echo '<td style="display:flex;gap:6px;flex-wrap:wrap;">';
                    echo '<form method="post" class="inline-form">';
                    echo '<input type="hidden" name="action" value="jostal_clasificar_lead">';
                    echo '<input type="hidden" name="lead_id" value="' . e($d['lead_id']) . '">';
                    echo '<input type="hidden" name="concepto_tipo" value="alquiler">';
                    echo '<input type="hidden" name="return_tab" value="deudas">';
                    echo '<input type="hidden" name="desde" value="' . e($desde) . '">';
                    echo '<input type="hidden" name="hasta" value="' . e($hasta) . '">';
                    echo '<input type="hidden" name="clienta_id" value="' . e($clientaIdFilter) . '">';
                    echo '<input type="hidden" name="fuente" value="' . e($fuente) . '">';
                    echo '<button class="btn-ok-mini">Es alquiler</button>';
                    echo '</form>';
                    echo '<form method="post" class="inline-form">';
                    echo '<input type="hidden" name="action" value="jostal_clasificar_lead">';
                    echo '<input type="hidden" name="lead_id" value="' . e($d['lead_id']) . '">';
                    echo '<input type="hidden" name="concepto_tipo" value="no_alquiler">';
                    echo '<input type="hidden" name="return_tab" value="deudas">';
                    echo '<input type="hidden" name="desde" value="' . e($desde) . '">';
                    echo '<input type="hidden" name="hasta" value="' . e($hasta) . '">';
                    echo '<input type="hidden" name="clienta_id" value="' . e($clientaIdFilter) . '">';
                    echo '<input type="hidden" name="fuente" value="' . e($fuente) . '">';
                    echo '<button class="btn-secondary-mini">No es alquiler</button>';
                    echo '</form>';
                    echo '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</section>';
            } else {
                // ── Resumen global ──
                $gDebe = 0.0; $gPagado = 0.0; $gDeuda = 0.0; $gCount = 0; $gConError = 0;
                foreach ($reportes as $r) {
                    if ($r['error'] !== null) { $gConError++; continue; }
                    $gDebe += (float)$r['data']['debe_total'];
                    $gPagado += (float)($esSemana ? ($r['data']['pagado_total_semana'] ?? 0) : ($r['data']['pagado_total'] ?? 0));
                    $gDeuda += (float)($esSemana ? ($r['data']['deuda_total_semana'] ?? 0) : ($r['data']['deuda_total'] ?? 0));
                    $gCount++;
                }
                echo '<div class="cards four">';
                dashboard_card('Clientas en alquiler', $gCount, false);
                dashboard_card('Debe total', euro($gDebe), true);
                dashboard_card('Pagado total', euro($gPagado), true);
                dashboard_card('Deuda total', euro($gDeuda), true);
                echo '</div>';

                // ── Detalle por clienta ──
                foreach ($reportes as $r) {
                    if ($r['error'] !== null) {
                        echo '<section class="panel">';
                        echo '<h2>' . e($r['nombre']) . '</h2>';
                        $msgs = array(
                            'sin_precio' => 'No tiene precio semanal definido. Añádelo en su ficha (Precio semanal alquiler).',
                            'sin_alquiler_activo' => 'No está en modo alquiler activo.',
                            'sin_vencimientos' => 'No hay vencimientos en el rango seleccionado.',
                        );
                        echo '<div class="empty">' . e($msgs[$r['error']] ?? 'No se pudo calcular.') . '</div>';
                        echo '</section>';
                        continue;
                    }

                    $data = $r['data'];
                    $c = $r['clienta'];
                    $nombre = $r['nombre'];
                    $cid = (string)($c['id'] ?? '');
                    // Deuda real = total (vencida + semana actual). Lo que le interesa al usuario.
                    $deuda = (float)($esSemana ? ($data['deuda_total_semana'] ?? 0) : ($data['deuda_total'] ?? 0));
                    $deudaVencida = (float)($esSemana ? ($data['deuda_vencida_semana'] ?? 0) : ($data['deuda_vencida'] ?? 0));
                    $saldoFavor = (float)($esSemana ? ($data['saldo_favor_semana'] ?? 0) : ($data['saldo_favor'] ?? 0));
                    $pendienteActual = (float)($esSemana ? ($data['pendiente_actual_semana'] ?? 0) : ($data['pendiente_actual'] ?? 0));
                    $weeks = (array)($data['weeks'] ?? array());
                    $perdon = isset($data['perdon']) && is_array($data['perdon']) ? $data['perdon'] : null;

                    $precioLabel = count((array)($data['precios'] ?? array())) > 0
                        ? implode('€ → ', array_map(function ($p) { return (int)round((float)$p); }, array_values(array_unique((array)$data['precios'])))) . '€'
                        : euro((float)$data['precio']);

                    if ($deuda > 0.005) {
                        $estadoTxt = '⚠ Debe ' . euro($deuda);
                        $estadoColor = '#f87171';
                    } elseif ($saldoFavor > 0.005) {
                        $estadoTxt = '✓ A favor ' . euro($saldoFavor);
                        $estadoColor = '#60a5fa';
                    } else {
                        $estadoTxt = '✓ Al día';
                        $estadoColor = '#4ade80';
                    }

                    echo '<section class="panel jostal-cli" data-cid="' . e($cid) . '">';
                    echo '<div class="section-head">';
                    echo '<div>';
                    echo '<h2>' . e($nombre) . '</h2>';
                    echo '<div class="muted">' . e($cid) . ' · ' . e($precioLabel) . '/sem · vence ' . e($data['due_weekday_label']) . ' · Entrada ' . e(jostal_fecha_corta($data['entry_date'])) . ' · ' . count($weeks) . ' sem. mostradas</div>';
                    echo '</div>';
                    echo '<div class="section-head-actions" style="align-items:center;text-align:right;">';
                    echo '<strong class="jostal-deuda" style="color:' . $estadoColor . ';font-size:16px;">' . $estadoTxt . '</strong>';
                    echo '<div class="jostal-deuda-sub muted" style="font-size:11px;">' . ($deuda > 0.005 && $pendienteActual > 0.005 ? '(' . e(euro($deudaVencida)) . ' vencido + ' . e(euro($pendienteActual)) . ' esta semana)' : '') . '</div>';
                    echo '</div>';
                    echo '</div>';

                    if ($perdon !== null) {
                        echo '<div class="jostal-perdon-banner" style="margin-bottom:8px;padding:10px 14px;border:1px solid rgba(45,212,191,.40);border-left:4px solid #2dd4bf;border-radius:8px;background:rgba(45,212,191,.10);font-size:13px;">';
                        echo '<strong style="color:#5eead4;">🧽 Borrón y cuenta nueva</strong> — aplicado el ' . e(jostal_fecha_corta((string)$perdon['desde'])) . ' · se perdonan <strong style="color:#5eead4;">' . e(euro((float)$perdon['deuda_perdonada'])) . '</strong> de deuda anterior';
                        if (!empty($perdon['ignorar_actual'])) echo ' <span class="muted">(se ignoran los pagos de la semana en curso)</span>';
                        echo '</div>';
                    }

                    if (!empty($data['no_alq_total']) && $data['no_alq_total'] > 0) {
                        echo '<div class="muted" style="margin-bottom:8px;">(+) ' . e(euro($data['no_alq_total'])) . ' en pagos NO alquiler (clientes, fianza…) — no descuentan deuda.</div>';
                    }

                    echo '<div class="table-wrap"><table class="jostal-table"><thead><tr>';
                    $srcFechaLbl = $esSemana ? '<span style="color:#3b82f6;font-size:10px;font-weight:700;">◉ FUENTE</span>' : '';
                    $srcFifoLbl  = $esSemana ? '' : '<span style="color:#3b82f6;font-size:10px;font-weight:700;">◉ FUENTE</span>';
                    echo '<th>Sem</th><th>Periodo</th>';
                    echo '<th' . ($esSemana ? '' : ' style="opacity:.55;"') . '>Pagó esta semana ' . $srcFechaLbl . '</th>';
                    echo '<th' . ($esSemana ? ' style="opacity:.55;"' : '') . '>Pagos alquiler (cubre) ' . $srcFifoLbl . '</th>';
                    echo '<th>Otros ingresos</th><th>Deuda semana</th><th>Acumulado</th>';
                    echo '</tr></thead><tbody class="jostal-tbody">';
                    foreach ($weeks as $w) {
                        $dif = (float)($esSemana ? ($w['diff_semana'] ?? 0) : ($w['diff'] ?? 0));
                        $run = (float)($esSemana ? ($w['running_semana'] ?? 0) : ($w['running'] ?? 0));
                        $esActual = !empty($w['es_actual']);
                        $perdonada = !empty($w['perdonada']);
                        $esPerdon = !empty($w['es_perdon']);

                        // Fila separadora de "Borrón y cuenta nueva".
                        if ($esPerdon && $perdon !== null) {
                            echo '<tr class="jostal-perdon-sep" style="background:rgba(45,212,191,.10);">';
                            echo '<td colspan="7" style="color:#5eead4;font-weight:700;text-align:center;padding:8px 12px;">🧽 BORRÓN Y CUENTA NUEVA — se perdonan ' . e(euro((float)$perdon['deuda_perdonada'])) . ' · desde ' . e(jostal_fecha_corta((string)$perdon['desde'])) . '</td>';
                            echo '</tr>';
                        }

                        if ($esSemana) {
                            // En "pago esta semana", la fila es verde si pagó lo de ESA semana,
                            // aunque arrastre deuda de semanas anteriores.
                            $rowBg = $esActual
                                ? 'rgba(245,158,11,.08)'
                                : ($dif > 0.005 ? 'rgba(239,68,68,.08)' : 'rgba(16,185,129,.09)');
                        } else {
                            $rowBg = $esActual
                                ? 'rgba(245,158,11,.08)'
                                : ($run > 0.005 ? 'rgba(239,68,68,.08)' : 'rgba(16,185,129,.09)');
                        }

                        // Pagó esta semana (por fecha).
                        $pagosFechaHtml = '';
                        if (empty($w['pagos_fecha'])) {
                            $pagosFechaHtml = '<span class="muted">—</span>';
                        } else {
                            foreach ($w['pagos_fecha'] as $pf) {
                                $concepto = trim((string)($pf['desc'] ?? ''));
                                $line = e(jostal_fecha_corta($pf['date'])) . ' · <strong>' . e(euro($pf['amount'])) . '</strong>';
                                if ($concepto !== '') $line .= ' <span class="muted">' . e($concepto) . '</span>';
                                $pagosFechaHtml .= '<div>' . $line . '</div>';
                            }
                        }
                        // Compensaciones del modo "pago esta semana" (fuente activa).
                        if ($esSemana) {
                            $compBack = (float)($w['comp_back'] ?? 0);
                            $compFwd = (float)($w['comp_fwd'] ?? 0);
                            $compFavor = (float)($w['comp_favor'] ?? 0);
                            if ($compBack > 0.005) $pagosFechaHtml .= '<div style="color:#fbbf24;font-weight:700;font-size:11px;">→ cubre sem. anterior ' . e(euro($compBack)) . '</div>';
                            if ($compFwd > 0.005) $pagosFechaHtml .= '<div style="color:#fb923c;font-weight:700;font-size:11px;">→ adelanto sem. siguiente ' . e(euro($compFwd)) . '</div>';
                            if ($compFavor > 0.005) $pagosFechaHtml .= '<div style="color:#60a5fa;font-weight:700;font-size:11px;">→ a favor ' . e(euro($compFavor)) . '</div>';
                        }

                        // Pagos alquiler (cubre, FIFO).
                        $pagosHtml = '';
                        if (empty($w['pagos'])) {
                            $pagosHtml = '<span class="muted">—</span>';
                        } else {
                            foreach ($w['pagos'] as $p) {
                                $aplicado = (float)($p['aplicado'] ?? $p['amount']);
                                $esParte = $aplicado < (float)$p['amount'] - 0.005;
                                $concepto = trim((string)($p['desc'] ?? ''));
                                $pdate = (string)($p['date'] ?? '');

                                $fuera = '';
                                $badge = '';
                                if ($pdate !== '' && $pdate < (string)$w['ps']) {
                                    $fuera = ' style="background:rgba(251,191,36,.10);border-left:3px solid #fbbf24;padding:2px 6px;border-radius:4px;margin-bottom:2px;"';
                                    $badge = ' <span style="color:#fbbf24;font-weight:700;">⤴ adelanto</span>';
                                } elseif ($pdate !== '' && $pdate >= (string)$w['pe']) {
                                    $fuera = ' style="background:rgba(251,146,60,.12);border-left:3px solid #fb923c;padding:2px 6px;border-radius:4px;margin-bottom:2px;"';
                                    $badge = ' <span style="color:#fb923c;font-weight:700;">↩ compensa sem. ' . (int)$w['n'] . '</span>';
                                }

                                $line = e(jostal_fecha_corta($pdate)) . ' · <strong>' . e(euro($aplicado)) . '</strong>';
                                if ($esParte) $line .= ' <span class="muted">(parte)</span>';
                                if ($concepto !== '') $line .= ' <span class="muted">' . e($concepto) . '</span>';
                                $line .= $badge;
                                $pagosHtml .= '<div' . $fuera . '>' . $line . '</div>';
                            }
                        }

                        // Otros ingresos (no alquiler, con botón compensar).
                        $otrosHtml = '';
                        if (empty($w['otros'])) {
                            $otrosHtml = '<span class="muted">—</span>';
                        } else {
                            foreach ($w['otros'] as $op) {
                                $concepto = trim((string)($op['desc'] ?? ''));
                                $leadId = (string)($op['lead_id'] ?? '');
                                $line = e(jostal_fecha_corta($op['date'])) . ' · <strong>' . e(euro($op['amount'])) . '</strong>';
                                $sub = ($concepto !== '')
                                    ? '<div style="font-size:10px;color:#90a4ae;">' . e($concepto) . '</div>'
                                    : '<div style="font-size:10px;color:#90a4ae;font-style:italic;">sin concepto</div>';
                                $btn = '';
                                if ($leadId !== '') {
                                    $btn = '<button type="button" class="btn-secondary-mini" style="margin-top:2px;font-size:10px;padding:2px 8px;" data-accion="compensar" data-cid="' . e($cid) . '" data-lead-id="' . e($leadId) . '">→ alquiler</button>';
                                }
                                $otrosHtml .= '<div style="margin-bottom:3px;padding-bottom:3px;border-bottom:1px dashed #2a3a4f;">' . $line . $sub . $btn . '</div>';
                            }
                        }

                        if ($esActual) {
                            $difHtml = '<span style="color:#f59e0b;font-weight:600;">' . ($dif > 0.005 ? 'pend. ' . e(euro($dif)) : 'pagado') . '</span>';
                        } else {
                            if ($esSemana) {
                                $difHtml = $dif > 0.005
                                    ? '<span style="color:#f87171;font-weight:600;">+' . e(euro($dif)) . '</span>'
                                    : '<span style="color:#4ade80;font-weight:600;">✓</span>';
                            } else {
                                $difHtml = $dif > 0.005 ? '<span style="color:#f87171;font-weight:600;">+' . e(euro($dif)) . '</span>' : '<span class="muted">0,00 €</span>';
                            }
                        }
                        $runColor = $run > 0.005 ? '#f87171' : '#4ade80';
                        $runIcon = $run > 0.005 ? '⚠' : '✓';

                        // Celda "Acumulado" con marcadores de perdón.
                        if ($perdonada) {
                            $runCell = '<span style="text-decoration:line-through;color:#78909c;">' . e(euro($run)) . '</span> <span style="color:#5eead4;font-size:10px;font-weight:700;">🗑 perdonado</span>';
                        } elseif ($esPerdon) {
                            $runCell = e(euro($run)) . ' ' . $runIcon . ' <span style="color:#5eead4;font-size:10px;font-weight:700;">↺ desde aquí</span>';
                        } else {
                            $runCell = e(euro($run)) . ' ' . $runIcon;
                        }

                        $rowStyle = 'background:' . $rowBg . ';' . ($perdonada ? 'opacity:.55;' : '');
                        echo '<tr style="' . $rowStyle . ';">';
                        echo '<td>' . e($w['n']) . '</td>';
                        $periodEndInclusive = jostal_periodo_fin_inclusivo($w['pe']);
                        echo '<td>' . e(jostal_fecha_corta($w['ps']) . ' → ' . jostal_fecha_corta($periodEndInclusive) . ' (incl.)') . ($esActual ? ' <span style="color:#f59e0b;font-size:11px;font-weight:600;">(en curso)</span>' : '') . '</td>';
                        echo '<td' . ($esSemana ? '' : ' style="opacity:.55;"') . '>' . $pagosFechaHtml . '</td>';
                        echo '<td' . ($esSemana ? ' style="opacity:.55;"' : '') . '>' . $pagosHtml . '</td>';
                        echo '<td>' . $otrosHtml . '</td>';
                        echo '<td>' . $difHtml . '</td>';
                        echo '<td style="color:' . $runColor . ';font-weight:700;text-align:right;">' . $runCell . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table></div>';

                    echo '<div class="jostal-totalbox" style="margin-top:10px;padding:14px 16px;border:1px solid var(--line);border-radius:8px;background:rgba(17,28,45,.5);font-size:14px;">';
                    echo '<div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;">';
                    echo '<span class="muted">DEUDA TOTAL</span>';
                    echo '<strong style="font-size:20px;color:' . ($deuda > 0.005 ? '#f87171' : '#4ade80') . ';">' . e(euro($deuda)) . '</strong>';
                    echo '<span class="muted" style="font-size:12px;">(' . e(euro($deudaVencida)) . ' vencido + ' . e(euro($pendienteActual)) . ' esta semana)</span>';
                    echo '</div>';
                    echo '</div>';

                    // ── Compensaciones temporales (pendientes de confirmar) ──
                    echo '<div class="jostal-compensaciones" data-cid="' . e($cid) . '"></div>';

                    // ── Resumen por meses ──
                    $meses = (array)($esSemana ? ($data['resumen_meses_semana'] ?? array()) : ($data['resumen_meses'] ?? array()));
                    if (!empty($meses)) {
                        echo '<div style="margin-top:12px;">';
                        echo '<div class="muted" style="margin-bottom:6px;"><strong>Deuda por mes</strong> (dentro del rango seleccionado)</div>';
                        echo '<div class="table-wrap"><table><thead><tr><th>Mes</th><th>Debe</th><th>Pagado</th><th>Deuda del mes</th><th>Acumulado</th></tr></thead><tbody class="jostal-meses-tbody">';
                        foreach ($meses as $mkey => $m) {
                            $mDiff = (float)$m['diff'];
                            $mRun = (float)$m['running'];
                            $mDiffHtml = $mDiff > 0.005 ? '<span style="color:#f87171;font-weight:600;">+' . e(euro($mDiff)) . '</span>' : '<span class="muted">0,00 €</span>';
                            $mRunColor = $mRun > 0.005 ? '#f87171' : '#4ade80';
                            echo '<tr>';
                            echo '<td>' . e(jostal_mes_label($mkey)) . '</td>';
                            echo '<td>' . e(euro($m['debe'])) . '</td>';
                            echo '<td>' . e(euro($m['pagado'])) . '</td>';
                            echo '<td>' . $mDiffHtml . '</td>';
                            echo '<td style="color:' . $mRunColor . ';font-weight:700;text-align:right;">' . e(euro($mRun)) . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table></div></div>';
                    }

                    // Enviar por WhatsApp (desde dulce)
                    echo '<div class="mini-actions-bar" style="margin-top:10px;">';
                    echo '<form method="post" class="inline-form jostal-wasap-form" data-cid="' . e($cid) . '" style="gap:6px;flex-wrap:wrap;">';
                    echo '<input type="hidden" name="action" value="jostal_send_deuda_wasap">';
                    echo '<input type="hidden" name="clienta_id" value="' . e($cid) . '">';
                    echo '<input type="hidden" name="desde" value="' . e($desde) . '">';
                    echo '<input type="hidden" name="hasta" value="' . e($hasta) . '">';
                    echo '<input type="hidden" name="fuente" value="' . e($fuente) . '">';
                    echo '<label class="inline-label">Enviar informe por WhatsApp (desde dulce) a</label>';
                    echo '<select name="destino_tipo">';
                    echo '<option value="clienta">la clienta (' . e($c['telefono'] ?? 'sin teléfono') . ')</option>';
                    echo '<option value="personal">mi número (654464023)</option>';
                    echo '<option value="otro">otro número</option>';
                    echo '</select>';
                    echo '<input type="text" name="destino_manual" placeholder="Número si eliges otro" style="max-width:180px;">';
                    echo '<button class="btn-wa-mini">📱 Enviar</button>';
                    echo '</form>';
                    echo '</div>';

                    // Blob JSON para el recompute exacto en JS.
                    $blob = array(
                        'cid' => $cid,
                        'fuente' => $fuente,
                        'desde' => $desde,
                        'hasta' => $hasta,
                        'weeks' => array_map(function ($w) {
                            return array('ps' => $w['ps'], 'pe' => $w['pe'], 'debe' => round((float)$w['debe'], 2), 'es_actual' => !empty($w['es_actual']));
                        }, $weeks),
                        'pagos_raw' => (array)($data['pagos_raw'] ?? array()),
                    );
                    if ($perdon !== null) {
                        $blob['perdon'] = array(
                            'desde' => (string)$perdon['desde'],
                            'reset_index' => (int)$perdon['reset_index'],
                            'ignorar_actual' => !empty($perdon['ignorar_actual']),
                            'deuda_perdonada' => (float)$perdon['deuda_perdonada'],
                        );
                    }
                    echo '<script type="application/json" class="jostal-data">' . json_encode($blob, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';

                    echo '</section>';
                }

                if ($gConError > 0) {
                    echo '<div class="muted">' . e($gConError) . ' clienta(s) sin calcular por falta de precio o datos.</div>';
                }
            }
        }
        ?>
<script>
(function () {
    'use strict';
    var state = {}; // cid -> { leadId: true }
    var data = {};  // cid -> { weeks, pagos_raw }
    var csrfToken = <?php echo json_encode(csrf_token()); ?>;

    function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function euro(n) {
        n = Math.round((+n) * 100) / 100;
        var neg = n < 0; n = Math.abs(n);
        var intPart = Math.floor(n);
        var dec = Math.round((n - intPart) * 100);
        var decStr = (dec < 10 ? '0' : '') + dec;
        var intStr = String(intPart).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return (neg ? '-' : '') + intStr + ',' + decStr + ' \u20AC';
    }
    function fcorte(d) { var p = String(d).split('-'); return p.length >= 3 ? p[2] + '/' + p[1] : d; }
    function periodEnd(pe) { var d = new Date(pe + 'T12:00:00'); d.setDate(d.getDate() - 1); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
    function mesLabel(k) { var m = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']; var p = String(k).split('-'); return m[+p[1]] + ' ' + p[0]; }

    document.querySelectorAll('script.jostal-data').forEach(function (el) {
        try { var d = JSON.parse(el.textContent); data[d.cid] = d; } catch (e) {}
    });

    function compute(cid) {
        var d = data[cid]; if (!d) return null;
        var fuente = d.fuente === 'semana' ? 'semana' : 'alquiler';
        var over = state[cid] || {};
        var weeks = d.weeks, n = weeks.length;
        var alq = [], noalq = [];
        d.pagos_raw.forEach(function (p) {
            if (p.tipo === 'alquiler' || over[p.lead_id]) alq.push(p); else noalq.push(p);
        });
        alq.sort(function (a, b) { return a.date < b.date ? -1 : (a.date > b.date ? 1 : 0); });
        noalq.sort(function (a, b) { return a.date < b.date ? -1 : (a.date > b.date ? 1 : 0); });

        function inWeek(p, w) { var ps = (w === 0) ? weeks[0].ps : weeks[w - 1].pe; return p.date >= ps && p.date < weeks[w].pe; }

        // Pagos por fecha (fuente "pago esta semana").
        var pagosFecha = weeks.map(function () { return []; });
        var pagadoReal = weeks.map(function () { return 0; });
        alq.forEach(function (p) { for (var w = 0; w < n; w++) { if (inWeek(p, w)) { pagosFecha[w].push(p); pagadoReal[w] += +p.amount; break; } } });

        // Otros ingresos (no alquiler).
        var otros = weeks.map(function () { return []; });
        noalq.forEach(function (p) { for (var w = 0; w < n; w++) { if (inWeek(p, w)) { otros[w].push(p); break; } } });

        // FIFO (fuente "pago alquiler cubre").
        var remaining = weeks.map(function (w) { return +w.debe; });
        var allocated = weeks.map(function () { return 0; });
        var pagosSemana = weeks.map(function () { return []; });
        var saldoFavor = 0;
        alq.forEach(function (p) {
            var amt = +p.amount;
            for (var w = 0; w < n; w++) {
                if (remaining[w] <= 0.0005) continue;
                var take = Math.min(amt, remaining[w]);
                allocated[w] += take; remaining[w] -= take; amt -= take;
                pagosSemana[w].push({ date: p.date, amount: +p.amount, aplicado: take, desc: p.desc, lead_id: p.lead_id, comp_temporal: !!over[p.lead_id] });
                if (amt <= 0.0005) break;
            }
            if (amt > 0.0005) saldoFavor += amt;
        });

        // Balance directo "pago esta semana" + compensación adyacente.
        var direct = weeks.map(function (w, idx) { return pagadoReal[idx] - (+w.debe); });
        var compBack = weeks.map(function () { return 0; });
        var compFwd = weeks.map(function () { return 0; });
        var compFavor = weeks.map(function () { return 0; });
        var saldoFavorSemana = 0;
        for (var w = 0; w < n; w++) {
            if (direct[w] <= 0.0005) continue;
            var s = direct[w];
            if (w - 1 >= 0 && direct[w - 1] < -0.0005) { var tb = Math.min(s, -direct[w - 1]); direct[w - 1] += tb; direct[w] -= tb; compBack[w] += tb; s -= tb; }
            if (s > 0.0005 && w + 1 < n && direct[w + 1] < -0.0005) { var tf = Math.min(s, -direct[w + 1]); direct[w + 1] += tf; direct[w] -= tf; compFwd[w] += tf; s -= tf; }
            if (s > 0.0005) { compFavor[w] += s; saldoFavorSemana += s; direct[w] -= s; }
        }

        var outWeeks = [], meses = {}, mesesS = {};
        var debeTotal = 0, pagadoTotal = 0, deudaVencida = 0, pendActual = 0;
        var deudaVencidaS = 0, pendActualS = 0;
        var runFifo = 0, runSemana = 0;

        for (var w = 0; w < n; w++) {
            var ps = (w === 0) ? weeks[0].ps : weeks[w - 1].pe;
            var pe = weeks[w].pe, debe = +weeks[w].debe;
            var paidFifo = allocated[w], diffFifo = debe - paidFifo;
            var deficit = direct[w] < -0.0005 ? -direct[w] : 0;
            var esActual = !!weeks[w].es_actual;

            runFifo += diffFifo; runSemana += deficit;
            debeTotal += debe; pagadoTotal += paidFifo;
            if (esActual) { if (diffFifo > 0) pendActual = diffFifo; if (deficit > 0) pendActualS = deficit; }
            else { deudaVencida += diffFifo; deudaVencidaS += deficit; }

            var mes = pe.substring(0, 7);
            if (!meses[mes]) meses[mes] = { debe: 0, pagado: 0, diff: 0, running: 0 };
            meses[mes].debe += debe; meses[mes].pagado += paidFifo; meses[mes].diff += diffFifo; meses[mes].running = runFifo;
            if (!mesesS[mes]) mesesS[mes] = { debe: 0, pagado: 0, diff: 0, running: 0 };
            mesesS[mes].debe += debe; mesesS[mes].pagado += pagadoReal[w]; mesesS[mes].diff += deficit; mesesS[mes].running = runSemana;

            outWeeks.push({
                n: w + 1, ps: ps, pe: pe, debe: debe,
                pagos: pagosSemana[w], pagos_fecha: pagosFecha[w], pagado: paidFifo, pagado_real: pagadoReal[w],
                diff: diffFifo, running: runFifo, arrastre: runFifo - diffFifo,
                diff_semana: deficit, running_semana: runSemana, arrastre_semana: runSemana - deficit, deficit_semana: deficit,
                comp_back: compBack[w], comp_fwd: compFwd[w], comp_favor: compFavor[w],
                otros: otros[w], es_actual: esActual
            });
        }

        // ── Perdón ("Borrón y cuenta nueva"): re-FIFO post-reset + reinicio ──
        if (d.perdon && d.perdon.reset_index != null) {
            var ri = d.perdon.reset_index;
            var pdesde = d.perdon.desde;
            var postN = n - ri;
            var postDebe = []; for (var i = ri; i < n; i++) postDebe.push(+weeks[i].debe);
            var postAlq = alq.filter(function (p) {
                if (p.date < pdesde) return false;
                if (!d.perdon.ignorar_actual) return true;
                for (var i = ri; i < n; i++) {
                    if (weeks[i].es_actual && p.date >= weeks[i].ps && p.date < weeks[i].pe) return false;
                }
                return true;
            });
            var postPagadoReal = []; var postPagosFecha = [];
            for (var k = 0; k < postN; k++) { postPagadoReal.push(0); postPagosFecha.push([]); }
            postAlq.forEach(function (p) {
                for (var k = 0; k < postN; k++) {
                    var idx = ri + k;
                    if (p.date >= weeks[idx].ps && p.date < weeks[idx].pe) {
                        postPagadoReal[k] += +p.amount; postPagosFecha[k].push(p); break;
                    }
                }
            });
            var prem = postDebe.slice();
            var palloc = []; for (var k = 0; k < postN; k++) palloc.push(0);
            var ppagos = []; for (var k = 0; k < postN; k++) ppagos.push([]);
            postAlq.forEach(function (p) {
                var amt = +p.amount;
                for (var k = 0; k < postN; k++) {
                    if (prem[k] <= 0.0005) continue;
                    var take = Math.min(amt, prem[k]);
                    palloc[k] += take; prem[k] -= take; amt -= take;
                    ppagos[k].push({ date: p.date, amount: +p.amount, aplicado: take, desc: p.desc, lead_id: p.lead_id, comp_temporal: !!over[p.lead_id] });
                    if (amt <= 0.0005) break;
                }
            });
            var postDirect = [], postCompBack = [], postCompFwd = [], postCompFavor = [];
            var postSaldoFavorSemana = 0;
            for (var k = 0; k < postN; k++) {
                postDirect.push(postPagadoReal[k] - postDebe[k]);
                postCompBack.push(0); postCompFwd.push(0); postCompFavor.push(0);
            }
            for (var k = 0; k < postN; k++) {
                if (postDirect[k] <= 0.0005) continue;
                var s = postDirect[k];
                if (k > 0 && postDirect[k - 1] < -0.0005) { var tb = Math.min(s, -postDirect[k - 1]); postDirect[k - 1] += tb; postDirect[k] -= tb; postCompBack[k] += tb; s -= tb; }
                if (s > 0.0005 && k + 1 < postN && postDirect[k + 1] < -0.0005) { var tf = Math.min(s, -postDirect[k + 1]); postDirect[k + 1] += tf; postDirect[k] -= tf; postCompFwd[k] += tf; s -= tf; }
                if (s > 0.0005) { postCompFavor[k] += s; postSaldoFavorSemana += s; postDirect[k] -= s; }
            }
            var runF2 = 0, runS2 = 0;
            var debeT2 = 0, pagT2 = 0, dv2 = 0, pa2 = 0, dvs2 = 0, pas2 = 0;
            var meses2 = {}, mesesS2 = {};
            for (var k = 0; k < postN; k++) {
                var idx = ri + k;
                var w = outWeeks[idx];
                var debe = +w.debe;
                var paid = palloc[k];
                var diff = debe - paid;
                var deficit = postDirect[k] < -0.0005 ? -postDirect[k] : 0;
                var esActual = w.es_actual;
                w.pagado = paid; w.diff = diff; w.pagos = ppagos[k]; w.pagado_real = postPagadoReal[k]; w.pagos_fecha = postPagosFecha[k];
                w.arrastre = runF2; runF2 += diff; w.running = runF2;
                w.es_perdon = (k === 0);
                w.arrastre_semana = runS2; runS2 += deficit; w.diff_semana = deficit; w.deficit_semana = deficit; w.running_semana = runS2;
                w.comp_back = postCompBack[k]; w.comp_fwd = postCompFwd[k]; w.comp_favor = postCompFavor[k];
                debeT2 += debe; pagT2 += paid;
                if (esActual) { if (diff > 0) pa2 = diff; if (deficit > 0) pas2 = deficit; }
                else { dv2 += diff; dvs2 += deficit; }
                var mes = w.pe.substring(0, 7);
                if (!meses2[mes]) meses2[mes] = { debe: 0, pagado: 0, diff: 0, running: 0 };
                meses2[mes].debe += debe; meses2[mes].pagado += paid; meses2[mes].diff += diff; meses2[mes].running = runF2;
                if (!mesesS2[mes]) mesesS2[mes] = { debe: 0, pagado: 0, diff: 0, running: 0 };
                mesesS2[mes].debe += debe; mesesS2[mes].pagado += w.pagado_real; mesesS2[mes].diff += deficit; mesesS2[mes].running = runS2;
            }
            for (var i = 0; i < ri; i++) outWeeks[i].perdonada = true;
            return {
                fuente: fuente, weeks: outWeeks,
                debe_total: debeT2, pagado_total: pagT2, deuda_total: debeT2 - pagT2,
                deuda_vencida: dv2, pendiente_actual: pa2,
                saldo_favor: Math.max(0, postAlq.reduce(function (sum, p) { return sum + (+p.amount); }, 0) - pagT2),
                resumen_meses: meses2,
                deuda_total_semana: runS2, deuda_vencida_semana: dvs2, pendiente_actual_semana: pas2, saldo_favor_semana: postSaldoFavorSemana,
                resumen_meses_semana: mesesS2,
                perdon: d.perdon
            };
        }

        return {
            fuente: fuente, weeks: outWeeks,
            debe_total: debeTotal, pagado_total: pagadoTotal, deuda_total: debeTotal - pagadoTotal,
            deuda_vencida: deudaVencida, pendiente_actual: pendActual, saldo_favor: saldoFavor,
            resumen_meses: meses,
            deuda_total_semana: runSemana, deuda_vencida_semana: deudaVencidaS, pendiente_actual_semana: pendActualS, saldo_favor_semana: saldoFavorSemana,
            resumen_meses_semana: mesesS
        };
    }

    function renderRow(w, cid, fuente) {
        var esSemana = fuente === 'semana';
        var esActual = w.es_actual;
        var dif = esSemana ? (+w.diff_semana || 0) : (+w.diff || 0);
        var run = esSemana ? (+w.running_semana || 0) : (+w.running || 0);
        var perdonada = !!w.perdonada;
        var esPerdon = !!w.es_perdon;
        var rowBg = esActual ? 'rgba(245,158,11,.08)' : (esSemana ? (dif > 0.005 ? 'rgba(239,68,68,.08)' : 'rgba(16,185,129,.09)') : (run > 0.005 ? 'rgba(239,68,68,.08)' : 'rgba(16,185,129,.09)'));

        var sep = '';
        if (esPerdon) {
            var pinfo = (data[cid] && data[cid].perdon) || {};
            var perdonDeuda = +pinfo.deuda_perdonada || 0;
            var perdonDesde = pinfo.desde ? fcorte(pinfo.desde) : '';
            sep = '<tr style="background:rgba(45,212,191,.10);"><td colspan="7" style="color:#5eead4;font-weight:700;text-align:center;padding:8px 12px;">\uD83E\uDDFD BORRÓN Y CUENTA NUEVA — se perdonan ' + esc(euro(perdonDeuda)) + (perdonDesde ? ' · desde ' + esc(perdonDesde) : '') + '</td></tr>';
        }

        var pagosFecha = '';
        if (!w.pagos_fecha.length) pagosFecha = '<span class="muted">\u2014</span>';
        else w.pagos_fecha.forEach(function (p) {
            pagosFecha += '<div>' + esc(fcorte(p.date)) + ' \u00B7 <strong>' + esc(euro(p.amount)) + '</strong>' + (p.desc ? (' <span class="muted">' + esc(p.desc) + '</span>') : '') + '</div>';
        });
        if (esSemana) {
            var cb = +w.comp_back || 0, cf = +w.comp_fwd || 0, cv = +w.comp_favor || 0;
            if (cb > 0.005) pagosFecha += '<div style="color:#fbbf24;font-weight:700;font-size:11px;">\u2192 cubre sem. anterior ' + esc(euro(cb)) + '</div>';
            if (cf > 0.005) pagosFecha += '<div style="color:#fb923c;font-weight:700;font-size:11px;">\u2192 adelanto sem. siguiente ' + esc(euro(cf)) + '</div>';
            if (cv > 0.005) pagosFecha += '<div style="color:#60a5fa;font-weight:700;font-size:11px;">\u2192 a favor ' + esc(euro(cv)) + '</div>';
        }

        var pagos = '';
        if (!w.pagos.length) pagos = '<span class="muted">\u2014</span>';
        else w.pagos.forEach(function (p) {
            var esParte = p.aplicado < p.amount - 0.005;
            var fuera = '', badge = '';
            if (p.date < w.ps) { fuera = ' style="background:rgba(251,191,36,.10);border-left:3px solid #fbbf24;padding:2px 6px;border-radius:4px;margin-bottom:2px;"'; badge = ' <span style="color:#fbbf24;font-weight:700;">\u2934 adelanto</span>'; }
            else if (p.date >= w.pe) { fuera = ' style="background:rgba(251,146,60,.12);border-left:3px solid #fb923c;padding:2px 6px;border-radius:4px;margin-bottom:2px;"'; badge = ' <span style="color:#fb923c;font-weight:700;">\u21A9 compensa sem. ' + w.n + '</span>'; }
            if (p.comp_temporal) badge += ' <span style="color:#fbbf24;font-weight:700;">(comp. temporal)</span>';
            pagos += '<div' + fuera + '>' + esc(fcorte(p.date)) + ' \u00B7 <strong>' + esc(euro(p.aplicado)) + '</strong>' + (esParte ? ' <span class="muted">(parte)</span>' : '') + (p.desc ? (' <span class="muted">' + esc(p.desc) + '</span>') : '') + badge + '</div>';
        });

        var otros = '';
        if (!w.otros.length) otros = '<span class="muted">\u2014</span>';
        else w.otros.forEach(function (op) {
            var sub = op.desc ? '<div style="font-size:10px;color:#90a4ae;">' + esc(op.desc) + '</div>' : '<div style="font-size:10px;color:#90a4ae;font-style:italic;">sin concepto</div>';
            var btn = op.lead_id ? '<button type="button" class="btn-secondary-mini" style="margin-top:2px;font-size:10px;padding:2px 8px;" data-accion="compensar" data-cid="' + esc(cid) + '" data-lead-id="' + esc(op.lead_id) + '">\u2192 alquiler</button>' : '';
            otros += '<div style="margin-bottom:3px;padding-bottom:3px;border-bottom:1px dashed #2a3a4f;">' + esc(fcorte(op.date)) + ' \u00B7 <strong>' + esc(euro(op.amount)) + '</strong>' + sub + btn + '</div>';
        });

        var difHtml;
        if (esActual) difHtml = '<span style="color:#f59e0b;font-weight:600;">' + (dif > 0.005 ? 'pend. ' + esc(euro(dif)) : 'pagado') + '</span>';
        else if (esSemana) difHtml = dif > 0.005 ? '<span style="color:#f87171;font-weight:600;">+' + esc(euro(dif)) + '</span>' : '<span style="color:#4ade80;font-weight:600;">\u2713</span>';
        else difHtml = dif > 0.005 ? '<span style="color:#f87171;font-weight:600;">+' + esc(euro(dif)) + '</span>' : '<span class="muted">0,00 \u20AC</span>';
        var runColor = run > 0.005 ? '#f87171' : '#4ade80';
        var runIcon = run > 0.005 ? '\u26A0' : '\u2713';

        var runCell;
        if (perdonada) runCell = '<span style="text-decoration:line-through;color:#78909c;">' + esc(euro(run)) + '</span> <span style="color:#5eead4;font-size:10px;font-weight:700;">\uD83D\uDDD1 perdonado</span>';
        else if (esPerdon) runCell = esc(euro(run)) + ' ' + runIcon + ' <span style="color:#5eead4;font-size:10px;font-weight:700;">\u21BA desde aquí</span>';
        else runCell = esc(euro(run)) + ' ' + runIcon;

        var rowStyle = 'background:' + rowBg + ';' + (perdonada ? 'opacity:.55;' : '');

        return sep + '<tr style="' + rowStyle + ';">'
            + '<td>' + w.n + '</td>'
            + '<td>' + esc(fcorte(w.ps) + ' \u2192 ' + fcorte(periodEnd(w.pe)) + ' (incl.)') + (esActual ? ' <span style="color:#f59e0b;font-size:11px;font-weight:600;">(en curso)</span>' : '') + '</td>'
            + '<td' + (esSemana ? '' : ' style="opacity:.55;"') + '>' + pagosFecha + '</td>'
            + '<td' + (esSemana ? ' style="opacity:.55;"' : '') + '>' + pagos + '</td>'
            + '<td>' + otros + '</td>'
            + '<td>' + difHtml + '</td>'
            + '<td style="color:' + runColor + ';font-weight:700;text-align:right;">' + runCell + '</td>'
            + '</tr>';
    }

    function renderCompensaciones(cid, section) {
        var bar = section.querySelector('.jostal-compensaciones');
        if (!bar) return;
        var over = state[cid] || {};
        var d = data[cid];
        var html = '';
        Object.keys(over).forEach(function (leadId) {
            var p = null;
            d.pagos_raw.forEach(function (x) { if (x.lead_id === leadId) p = x; });
            if (!p) return;
            html += '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:6px;">'
                + '<span style="color:#fbbf24;font-weight:600;">compensado:</span> '
                + esc(fcorte(p.date)) + ' \u00B7 ' + esc(euro(p.amount)) + ' ' + esc(p.desc || '')
                + ' <form method="post" style="display:inline;">'
                + '<input type="hidden" name="action" value="jostal_compensar_lead">'
                + '<input type="hidden" name="lead_id" value="' + esc(leadId) + '">'
                + '<input type="hidden" name="clienta_id" value="' + esc(cid) + '">'
                + '<input type="hidden" name="csrf_token" value="' + esc(csrfToken) + '">'
                + '<input type="hidden" name="desde" value="' + esc(d.desde || '') + '">'
                + '<input type="hidden" name="hasta" value="' + esc(d.hasta || '') + '">'
                + '<input type="hidden" name="fuente" value="' + esc(d.fuente || '') + '">'
                + '<input type="hidden" name="redirect" value="index.php?page=jostal&amp;tab=deudas&amp;clienta_id=' + encodeURIComponent(cid) + '">'
                + '<button class="btn-ok-mini" style="font-size:10px;padding:2px 8px;">\u2713 permanente</button></form>'
                + ' <button type="button" class="btn-danger-mini" style="font-size:10px;padding:2px 8px;" data-accion="quitar" data-cid="' + esc(cid) + '" data-lead-id="' + esc(leadId) + '">\u21A9 quitar</button>'
                + '</div>';
        });
        bar.innerHTML = html ? '<div style="margin-top:8px;padding:10px 12px;border:1px solid rgba(251,191,36,.35);border-radius:8px;background:rgba(251,191,36,.06);"><div class="muted" style="margin-bottom:4px;"><strong>Compensaciones temporales</strong> (se pierden al recargar)</div>' + html + '</div>' : '';
    }

    function renderReclasificar(cid, section) {
        var form = section.querySelector('.jostal-wasap-form');
        if (!form) return;
        form.querySelectorAll('input[name="reclasificar[]"]').forEach(function (el) { el.remove(); });
        var over = state[cid] || {};
        Object.keys(over).forEach(function (leadId) {
            var inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = 'reclasificar[]'; inp.value = leadId;
            form.appendChild(inp);
        });
    }

    function render(cid) {
        var section = document.querySelector('.jostal-cli[data-cid="' + cid + '"]');
        if (!section) return;
        var comp = compute(cid);
        if (!comp) return;

        var tbody = section.querySelector('.jostal-tbody');
        if (tbody) { var rows = ''; comp.weeks.forEach(function (w) { rows += renderRow(w, cid, comp.fuente); }); tbody.innerHTML = rows; }

        var esSemana = comp.fuente === 'semana';
        var dTotal = esSemana ? (comp.deuda_total_semana || 0) : (comp.deuda_total || 0);
        var deudaVencida = esSemana ? (comp.deuda_vencida_semana || 0) : (comp.deuda_vencida || 0);
        var pendActual = esSemana ? (comp.pendiente_actual_semana || 0) : (comp.pendiente_actual || 0);
        var saldoFavor = esSemana ? (comp.saldo_favor_semana || 0) : (comp.saldo_favor || 0);
        var meses = esSemana ? (comp.resumen_meses_semana || {}) : (comp.resumen_meses || {});

        var estadoTxt, estadoColor;
        if (dTotal > 0.005) { estadoTxt = '\u26A0 Debe ' + euro(dTotal); estadoColor = '#f87171'; }
        else if (saldoFavor > 0.005) { estadoTxt = '\u2713 A favor ' + euro(saldoFavor); estadoColor = '#60a5fa'; }
        else { estadoTxt = '\u2713 Al d\u00EDa'; estadoColor = '#4ade80'; }
        var deudaHead = section.querySelector('.jostal-deuda');
        if (deudaHead) { deudaHead.style.color = estadoColor; deudaHead.textContent = estadoTxt; }
        var deudaSub = section.querySelector('.jostal-deuda-sub');
        if (deudaSub) deudaSub.textContent = (dTotal > 0.005 && pendActual > 0.005) ? '(' + euro(deudaVencida) + ' vencido + ' + euro(pendActual) + ' esta semana)' : '';

        var box = section.querySelector('.jostal-totalbox');
        if (box) {
            box.innerHTML = '<div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;"><span class="muted">DEUDA TOTAL</span>'
                + '<strong style="font-size:20px;color:' + (dTotal > 0.005 ? '#f87171' : '#4ade80') + ';">' + esc(euro(dTotal)) + '</strong>'
                + '<span class="muted" style="font-size:12px;">(' + esc(euro(deudaVencida)) + ' vencido + ' + esc(euro(pendActual)) + ' esta semana)</span></div>';
        }

        var mesesTbody = section.querySelector('.jostal-meses-tbody');
        if (mesesTbody) {
            var mHtml = '';
            Object.keys(meses).forEach(function (k) {
                var m = meses[k];
                var mDiff = m.diff > 0.005 ? '<span style="color:#f87171;font-weight:600;">+' + esc(euro(m.diff)) + '</span>' : '<span class="muted">0,00 \u20AC</span>';
                var mRunColor = m.running > 0.005 ? '#f87171' : '#4ade80';
                mHtml += '<tr><td>' + esc(mesLabel(k)) + '</td><td>' + esc(euro(m.debe)) + '</td><td>' + esc(euro(m.pagado)) + '</td><td>' + mDiff + '</td><td style="color:' + mRunColor + ';font-weight:700;text-align:right;">' + esc(euro(m.running)) + '</td></tr>';
            });
            mesesTbody.innerHTML = mHtml;
        }

        renderCompensaciones(cid, section);
        renderReclasificar(cid, section);
    }

    document.addEventListener('click', function (e) {
        var el = e.target.closest ? e.target.closest('[data-accion]') : null;
        if (!el) return;
        var accion = el.getAttribute('data-accion');
        var cid = el.getAttribute('data-cid');
        var leadId = el.getAttribute('data-lead-id');
        if (accion === 'compensar') {
            if (!state[cid]) state[cid] = {};
            state[cid][leadId] = true;
            render(cid);
        } else if (accion === 'quitar') {
            if (state[cid]) { delete state[cid][leadId]; if (Object.keys(state[cid]).length === 0) delete state[cid]; }
            render(cid);
        }
    });
})();
</script>
<?php
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

        // Skip gastos here — they are handled separately from $gastosFiltered below
        if (($row['type'] ?? '') === 'gasto') continue;

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

/**
 * Reproductor YouTube — Modo Lite (Cassette Deck Vintage para coche).
 * Mismos IDs que el reproductor desktop para compatibilidad con YTPlayer JS.
 */
function render_youtube_player_lite($playParam, $playlists, $channels, $history, $lastVideo) {
    // ── Procesar POST directo: añadir a lista desde el modal ──
    $directPlAction = (string)request_post('action');
    if ($directPlAction === 'youtube_add_to_pl_direct') {
        $plId = trim((string)request_post('playlist_id'));
        $vid  = trim((string)request_post('video_id'));
        if ($plId !== '' && $vid !== '') {
            $vtitle = trim((string)request_post('title'));
            $vthumb = trim((string)request_post('thumbnail'));
            $vchan  = trim((string)request_post('channel_name'));
            $pls = storage_read('youtube_playlists.json');
            if (!is_array($pls)) $pls = [];
            foreach ($pls as &$p) {
                if ($p['id'] === $plId) {
                    $dup = false;
                    foreach ($p['videos'] as $v) { if ($v['video_id'] === $vid) { $dup = true; break; } }
                    if (!$dup) {
                        $p['videos'][] = [
                            'video_id' => $vid, 'title' => $vtitle,
                            'thumbnail' => $vthumb, 'channel_name' => $vchan,
                            'added_at' => now_datetime(),
                        ];
                        $p['updated_at'] = now_datetime();
                    }
                    break;
                }
            }
            unset($p);
            storage_write('youtube_playlists.json', $pls);
            // Refrescar $playlists para el render
            $playlists = storage_read('youtube_playlists.json');
            if (!is_array($playlists)) $playlists = [];
        }
    }

    echo '<div class="youtube-reproductor yt-lite-radio" id="youtubeReproductor"';
    if ($playParam !== '') {
        echo ' data-auto-search="' . e($playParam) . '"';
    }
    echo '>';

    // ═══ CASSETTE DECK BODY: Marco bakelita ════════════════════════════
    echo '<div class="yt-radio-body">';

    // Power LED
    echo '<span class="yt-power-led" id="ytPowerLed"></span>';

    // ── DISPLAY PANEL: LCD con dial FM + VU meters + stereo ──
    echo '<div class="yt-radio-display">';
    // Banda de frecuencia decorativa
    echo '<div class="yt-radio-dial">';
    echo '<span class="yt-radio-dial-freq">88</span>';
    echo '<span class="yt-radio-dial-freq">92</span>';
    echo '<span class="yt-radio-dial-freq">96</span>';
    echo '<span class="yt-radio-dial-freq yt-radio-dial-active">100</span>';
    echo '<span class="yt-radio-dial-freq">104</span>';
    echo '<span class="yt-radio-dial-freq">108</span>';
    echo '<span class="yt-radio-dial-mhz">MHz</span>';
    echo '<div class="yt-radio-dial-needle"></div>';
    echo '</div>';
    // VU Meters (decorativos, animados CSS)
    echo '<div class="yt-vu-meters">';
    echo '<div class="yt-vu-bar" id="ytVuLeft"><span></span></div>';
    echo '<div class="yt-vu-bar" id="ytVuRight"><span></span></div>';
    echo '</div>';
    // Título now-playing + stereo
    echo '<div class="yt-radio-now-playing yt-radio-idle" id="youtubeNowPlaying">';
    echo '<span id="youtubeNowPlayingTitle">Sintoniza una emisora</span>';
    echo '</div>';
    // Stereo indicator
    echo '<span class="yt-stereo-indicator" id="ytStereoIndicator">STEREO</span>';
    // Badge FM
    echo '<div class="yt-radio-band"><span>FM</span></div>';
    echo '</div>'; // display

    // ── KITT Coche Fantástico overlay ──
    echo '<div class="kitt-overlay" id="kittOverlay">';
    echo '<div class="kitt-scanner-bar"></div>';
    echo '<div class="kitt-leds">';
    for ($i = 1; $i <= 8; $i++) {
        echo '<span class="kitt-led kitt-led-' . $i . '"></span>';
    }
    echo '</div>';
    echo '<div class="kitt-glow"></div>';
    echo '</div>';

    // ── GPS NAVIGATION overlay ──
    echo '<div class="yt-gps-overlay" id="gpsOverlay">';
    echo '<div class="yt-gps-backdrop" id="gpsBackdrop"></div>';
    echo '<div class="yt-gps-panel" id="gpsPanel">';
    echo '<button type="button" class="yt-gps-close" id="gpsCloseBtn" title="Cerrar GPS">&times;</button>';
    echo '<div class="yt-gps-header">';
    echo '<span class="yt-gps-title">GPS NAVEGACIÓN</span>';
    echo '<span class="yt-gps-coords" id="gpsCoordsDisplay">--</span>';
    echo '</div>';
    echo '<div class="yt-gps-map-container" id="gpsMapContainer">';
    // ── Layout: Left HUD | Radar Canvas | Right HUD ──
    echo '<div class="yt-gps-layout">';
    // LEFT HUD PANEL
    echo '<div class="yt-gps-hud-left">';
    echo '<div class="yt-gps-hud-section">';
    echo '<span class="yt-gps-hud-label">PROFUNDIDAD</span>';
    echo '<div class="yt-gps-depth-gauge"><div class="yt-gps-depth-fill" id="gpsDepthFill"></div></div>';
    echo '<span class="yt-gps-hud-value" id="gpsDepthValue">-- km</span>';
    echo '</div>';
    echo '<div class="yt-gps-hud-section">';
    echo '<span class="yt-gps-hud-label">SEÑAL</span>';
    echo '<div class="yt-gps-signal-bars" id="gpsSignalBars">';
    for ($i = 0; $i < 10; $i++) echo '<span class="gps-signal-bar"></span>';
    echo '</div>';
    echo '<span class="yt-gps-hud-value" id="gpsSignalText">--%</span>';
    echo '</div>';
    echo '<div class="yt-gps-hud-section" id="gpsHomeInfo" style="display:none">';
    echo '<span class="yt-gps-hud-label">BASE</span>';
    echo '<span class="yt-gps-hud-value" id="gpsHomeLabel">--</span>';
    echo '</div>';
    echo '<button type="button" class="yt-gps-hud-btn" id="gpsSetHomeBtn" title="Establecer base">🏠 BASE</button>';
    echo '<button type="button" class="yt-gps-hud-btn yt-gps-hud-btn-sos" id="gpsSosBtn" title="Emergencia — copiar coordenadas">🆘 SOS</button>';
    echo '</div>';
    // CENTER: Radar Canvas (overlay on Leaflet map)
    echo '<div id="gpsRadarMap" class="yt-gps-radar-map">';
    echo '<div id="gpsRadarMapInner" class="yt-gps-radar-map-inner"></div>';
    echo '<canvas id="gpsRadarCanvas" width="350" height="350"></canvas>';
    echo '<div class="yt-gps-zoom-controls">';
    echo '<button type="button" class="yt-gps-zoom-btn" id="gpsZoomInBtn" title="Acercar">+</button>';
    echo '<button type="button" class="yt-gps-zoom-btn" id="gpsZoomOutBtn" title="Alejar">&minus;</button>';
    echo '</div>';
    echo '</div>';
    // RIGHT HUD PANEL
    echo '<div class="yt-gps-hud-right">';
    echo '<div class="yt-gps-hud-section">';
    echo '<span class="yt-gps-hud-label">TUBOS</span>';
    echo '<div class="yt-gps-torpedo-list" id="gpsTorpedoList">';
    echo '<span class="yt-gps-hud-empty">Sin torpedos</span>';
    echo '</div>';
    echo '</div>';
    echo '<button type="button" class="yt-gps-hud-btn" id="gpsFireTorpedoBtn" title="Disparar torpedo en posición actual">🎯 DISPARAR</button>';
    echo '<button type="button" class="yt-gps-hud-btn" id="gpsBitacoraBtn" title="Bitácora de inmersiones">📜 BITÁCORA</button>';
    echo '</div>';
    echo '</div>'; // yt-gps-layout
    // BOTTOM HUD BAR
    echo '<div class="yt-gps-hud-bar">';
    echo '<div class="yt-gps-hud-item">';
    echo '<span class="yt-gps-hud-label">VEL</span>';
    echo '<span class="yt-gps-hud-value yt-gps-hud-value-big" id="gpsSpeedDisplay">--</span>';
    echo '<span class="yt-gps-hud-unit">km/h</span>';
    echo '</div>';
    echo '<div class="yt-gps-hud-item">';
    echo '<span class="yt-gps-hud-label">RUMBO</span>';
    echo '<span class="yt-gps-hud-value yt-gps-hud-value-big" id="gpsHeadingDisplay">--</span>';
    echo '<span class="yt-gps-hud-unit">&deg;</span>';
    echo '</div>';
    echo '<div class="yt-gps-hud-item yt-gps-hud-item-wide">';
    echo '<span class="yt-gps-hud-label">COORDENADAS</span>';
    echo '<span class="yt-gps-hud-value" id="gpsCoordsDisplayFull">--</span>';
    echo '</div>';
    echo '</div>'; // yt-gps-hud-bar
    echo '</div>'; // yt-gps-map-container
    echo '</div>'; // gpsPanel
    echo '</div>'; // gpsOverlay

    // ═══ TWO-COLUMN DECK: Cassette (left) + Controls & Knobs (right) ═══
    echo '<div class="yt-radio-deck-row">';

    // ── CASSETTE WELL: Slot de cinta ──
    echo '<div class="yt-cassette-well">';
    echo '<div class="yt-cassette-door">';
    // Tape (visible cuando hay video cargado)
    echo '<div class="yt-cassette-tape" id="ytCassetteTape">';
    echo '<div class="yt-cassette-reel yt-cassette-reel-l" id="ytCassetteReelL"></div>';
    echo '<div class="yt-cassette-reel yt-cassette-reel-r" id="ytCassetteReelR"></div>';
    echo '<div class="yt-cassette-label">';
    // Video player dentro de la etiqueta de la cinta
    echo '<div class="youtube-mini-player" id="youtubeMiniPlayer">';
    echo '<div class="youtube-mini-player-placeholder yt-radio-placeholder" id="youtubePlayerPlaceholder">';
    echo '<div class="youtube-placeholder-icon">&#9654;</div>';
    echo '<div class="youtube-placeholder-text">Selecciona un video</div>';
    echo '</div>';
    echo '<div id="youtubePlayerContainer" style="display:none"></div>';
    echo '</div>';
    echo '</div>'; // label
    echo '</div>'; // tape
    // Slot vacío (visible cuando no hay video)
    echo '<div class="yt-cassette-empty" id="ytCassetteEmpty">';
    echo '<div class="yt-cassette-empty-reels">';
    echo '<span class="yt-cassette-empty-reel"></span>';
    echo '<span class="yt-cassette-empty-reel"></span>';
    echo '</div>';
    echo '<span class="yt-cassette-empty-text">INSERT TAPE</span>';
    echo '</div>';
    echo '<button type="button" class="yt-lite-fs-close" id="ytLiteFsClose" title="Salir de pantalla completa">&times;</button>';
    echo '</div>'; // door
    // Info bar: tape counter + time + direction + badges
    echo '<div class="yt-cassette-info">';
    echo '<span class="yt-tape-counter" id="ytTapeCounter">000</span>';
    echo '<span class="yt-tape-time" id="ytTapeTime">--:--</span>';
    echo '<span class="yt-tape-direction" id="ytTapeDir">&#9654;</span>';
    echo '<span class="yt-tape-badge yt-tape-dnr">DNR</span>';
    echo '<span class="yt-tape-badge yt-tape-ar">&#8644;</span>';
    echo '</div>';
    // ── DJ Jefry: Like / Dislike / Skip buttons (esquina inferior dcha del well) ──
    // Estilos inline anti-caché — no dependen de lite.css ni de la clase is-lite
    echo '<style>
.yt-dj-buttons{display:flex!important;gap:2px;position:absolute!important;top:3px;right:4px;z-index:10}
.yt-dj-btn{width:19px!important;height:15px!important;border-radius:3px;border:1px solid #2a2a2a;border-top-color:#666!important;border-left-color:#5a5a5a!important;border-bottom-color:#111!important;border-right-color:#111!important;background:linear-gradient(180deg,#4a4a4a 0%,#2a2a2a 40%,#151515 100%)!important;color:#aaa!important;font-size:8px!important;font-weight:700!important;cursor:pointer;display:flex!important;align-items:center;justify-content:center;padding:0!important;line-height:1;box-shadow:0 2px 3px rgba(0,0,0,.5),0 1px 0 rgba(255,255,255,.04),inset 0 1px 0 rgba(255,255,255,.04)!important;touch-action:manipulation;user-select:none;-webkit-user-select:none}
.yt-dj-btn:active{transform:translateY(1px)!important;box-shadow:0 1px 1px rgba(0,0,0,.6),inset 0 2px 3px rgba(0,0,0,.35)!important;border-top-color:#111!important;border-left-color:#111!important}
.yt-dj-like{border-top-color:#8b6914!important;border-left-color:#8b6914!important;box-shadow:0 2px 3px rgba(0,0,0,.5),0 0 2px rgba(230,168,23,.1),inset 0 1px 0 rgba(255,200,60,.05)!important}
.yt-dj-like:hover{color:#e6a817!important;border-top-color:#e6a817!important;border-left-color:#c8960c!important;box-shadow:0 2px 3px rgba(0,0,0,.5),0 0 5px rgba(230,168,23,.2),inset 0 1px 0 rgba(255,200,60,.08)!important}
.yt-dj-dislike:hover{color:#ddd!important;border-top-color:#777!important;border-left-color:#666!important}
.yt-dj-skip:hover{color:#e6a817!important;border-top-color:#8b6914!important;border-left-color:#8b6914!important}
</style>';
    echo '<div class="yt-dj-buttons" id="ytDjButtons">';
    echo '<button type="button" class="yt-dj-btn yt-dj-like" id="ytDjLikeBtn" title="Me gusta" onclick="if(window._DjJefry){window._DjJefry.executeCommand({action:\'like\'});}">+</button>';
    echo '<button type="button" class="yt-dj-btn yt-dj-dislike" id="ytDjDislikeBtn" title="No me gusta" onclick="if(window._DjJefry){window._DjJefry.executeCommand({action:\'dislike\'});}">-</button>';
    echo '<button type="button" class="yt-dj-btn yt-dj-skip" id="ytDjSkipBtn" title="Saltar" onclick="if(window._DjJefry){window._DjJefry.executeCommand({action:\'skip\'});}">&#9654;</button>';
    echo '</div>';
    echo '</div>'; // cassette well

    // Right column: transport controls + knobs
    echo '<div class="yt-deck-controls-col">';

    // ── TRANSPORT CONTROLS: Botones mecánicos cuadrados ──
    echo '<div class="yt-mech-controls" id="youtubeControls">';
    echo '<button type="button" class="yt-mech-btn" id="youtubePrevBtn" title="Anterior (mantener para rebobinar)">|&#9664;&#9664;</button>';
    echo '<button type="button" class="yt-mech-btn yt-mech-play" id="youtubePlayPauseBtn" title="Reproducir/Pausa">&#9654; &#8214;</button>';
    echo '<button type="button" class="yt-mech-btn" id="youtubeNextBtn" title="Siguiente (mantener para avanzar)">&#9654;&#9654;|</button>';
    echo '<button type="button" class="yt-mech-btn yt-mech-stop" id="youtubeStopBtn" title="Parar">&#9632;</button>';
    echo '<button type="button" class="yt-mech-btn yt-mech-rec" id="youtubeAddToPlBtn" title="Grabar a lista" onclick="YTPlayer._openLiteAddModal()">&#9679;</button>';
    echo '<span class="yt-rec-led" id="ytRecLed"></span>';
    echo '</div>';

     // ── KNOBS ROW: Ruletas para volumen y boost ──
    echo '<div class="yt-knob-row">';
    // Menu exit button (X, red, left of knobs)
    echo '<div class="yt-knob-wrap">';
    echo '<button type="button" class="yt-menu-exit-btn" id="josueYtFsBtnLite" title="Menú / Salir">&#10005;</button>';
    echo '<span class="yt-knob-label">menu</span>';
    echo '</div>';
    // Volume knob
    echo '<div class="yt-knob-wrap">';
    echo '<div class="yt-knob" id="ytVolKnob" data-knob="volume"><span class="yt-knob-mark"></span><span class="yt-knob-txt" id="ytVolVal">100</span></div>';
    echo '<span class="yt-knob-label">vol</span>';
    echo '<input type="range" id="youtubeVolumeSlider" class="youtube-volume-slider yt-knob-slider" min="0" max="100" value="100" style="display:none">';
    // Hidden vol down/up buttons (kept for JS compat, hidden)
    echo '<button type="button" id="youtubeVolDownBtn" style="display:none"></button>';
    echo '<button type="button" id="youtubeVolUpBtn" style="display:none"></button>';
    echo '<span id="youtubeVolumeLabel" style="display:none"></span>';
    echo '</div>';
    // Boost knob
    echo '<div class="yt-knob-wrap">';
    echo '<div class="yt-knob" id="ytBoostKnob" data-knob="boost"><span class="yt-knob-mark"></span><span class="yt-knob-txt" id="ytBoostVal">150</span></div>';
    echo '<span class="yt-knob-label">boost</span>';
    echo '<input type="range" id="youtubeBoostSlider" class="youtube-boost-slider yt-knob-slider" min="50" max="300" value="150" style="display:none">';
    echo '<input type="checkbox" id="youtubeBoostCheckbox" style="display:none">';
    echo '<span id="youtubeBoostStatus" style="display:none"></span>';
    echo '</div>';
    // Luz button (square, lightbulb icon)
    echo '<div class="yt-knob-wrap">';
    echo '<button type="button" class="yt-luz-btn" id="ytSunlightToggle" title="Modo luz solar (día/noche)">&#x1F4A1;</button>';
    echo '<span class="yt-knob-label">luz</span>';
    echo '</div>';
    echo '</div>'; // knob row

    echo '</div>'; // yt-deck-controls-col
    echo '</div>'; // yt-radio-deck-row

    // ── DJ Jefry: Confidence bar (bajo la cinta, solo barra) ──
    echo '<style>.yt-dj-confidence{display:block;width:100%;margin-top:4px;margin-bottom:2px}.yt-dj-confidence-track{width:100%;height:3px;background:rgba(255,255,255,.06);border-radius:2px;overflow:hidden}.yt-dj-confidence-fill{height:100%;background:linear-gradient(90deg,rgba(245,158,11,.5),rgba(16,185,129,.7));border-radius:2px;transition:width .8s ease}.is-lite .yt-dj-confidence-fill{animation-duration:auto!important;transition-duration:.8s!important}</style>';
    echo '<div class="yt-dj-confidence" id="ytDjConfidence">';
    echo '<div class="yt-dj-confidence-track"><div class="yt-dj-confidence-fill" id="jefryConfidenceBar" style="width:50%"></div></div>';
    echo '</div>';

    // ── MENU BANK: Botonera de menú cuadrada ──
    echo '<div class="yt-menu-bank">';
    echo '<button type="button" class="yt-menu-btn yt-menu-btn-car yt-menu-btn-kitt" id="ytJefryChatStart" title="Izq: Jefry | Der: Coche Fantástico"><svg viewBox="0 0 50 28"><path d="M2,22 L5,12 L7,6 L18,4 L34,4 L44,6 L47,12 L48,22" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M17,4 L15,14 C15,14 20,11 26,10 C32,9 37,10 38,14 L34,4" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><rect class="scanner" x="8" y="6.5" width="12" height="1.8" rx="0.9" fill="#ee3333"/><circle cx="12" cy="24" r="3" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="39" cy="24" r="3" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="24" r="1.2" fill="currentColor"/><circle cx="39" cy="24" r="1.2" fill="currentColor"/></svg></button>';
    echo '<button type="button" class="yt-menu-btn" id="ytRadioPresintoniasToggle" title="Presintonías">&#9733; PRES</button>';
    echo '<button type="button" class="yt-menu-btn" id="ytGpsBtn" title="GPS / Navegación"><svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z" fill="currentColor"/></svg>GPS</button>';
    echo '<button type="button" class="yt-menu-btn" id="ytRadioRadiosToggle" title="Emisoras de radio">&#9889; RAD</button>';
    echo '<button type="button" class="yt-menu-btn" id="ytRadioSidebarToggle" title="Biblioteca (listas, sugerencias, resultados)">&#9835; BIB</button>';
    echo '</div>';

    // ── SEARCH BAR ──
    echo '<div class="youtube-search yt-radio-search-inline yt-search-row">';
    echo '<input type="text" id="youtubeSearchInput" class="youtube-search-input yt-radio-search-input" placeholder="Buscar en la biblioteca..." autocomplete="off">';
    echo '<button type="button" id="youtubeVoiceSearchBtn" class="youtube-voice-search-btn" title="Buscar por voz">🎤</button>';
    echo '<div class="youtube-search-spinner" id="youtubeSearchSpinner" style="display:none"></div>';
    echo '</div>';

    // ── Speed indicator (se muestra al hacer FF/RW) ──
    echo '<span class="yt-speed-badge" id="ytSpeedBadge"></span>';

    // ── PRESETS: movidos a paneles laterales ──
    echo '<div class="yt-radio-presets" style="display:none"></div>';

    echo '</div>'; // yt-radio-body

    // ═══ PANEL PRESINTONÍAS ════════════════════════════════════════════════
    echo '<div class="yt-radio-sidebar" id="presintoniasPanel">';
    echo '<button type="button" class="yt-radio-sidebar-close" id="presintoniasPanelClose" title="Cerrar panel">&times;</button>';
    echo '<div class="yt-radio-presets-title">PRESINTONÍAS</div>';
    echo '<div class="yt-radio-presets-scroll">';
    echo '<div class="youtube-channel-grid" id="youtubeChannelGrid">';
    if (empty($channels)) {
        echo '<div class="youtube-channel-tag youtube-channel-seed yt-radio-channel-tag" onclick="YTPlayer.seedChannels()" id="ytSeedTag">';
        echo '<span class="youtube-channel-icon">&#10024;</span><span>Cargar canales</span>';
        echo '</div>';
    } else {
        foreach ($channels as $ch) {
            $icon = e($ch['icon'] ?? '📺');
            $name = e($ch['name'] ?? '');
            $chId = e($ch['id'] ?? '');
            $type = e($ch['type'] ?? '');
            echo '<div class="youtube-channel-tag yt-radio-channel-tag' . ($type === 'ai_suggested' ? ' youtube-channel-ai' : '') . '" onclick="YTPlayer.loadTopicChannel(\'' . e($chId) . '\')">';
            echo '<span class="youtube-channel-icon">' . $icon . '</span><span>' . $name . '</span>';
            if ($type === 'custom' || $type === 'ai_suggested') {
                echo '<button type="button" class="youtube-channel-del" onclick="event.stopPropagation();YTPlayer.deleteTopicChannel(\'' . e($chId) . '\')" title="Eliminar">&times;</button>';
            }
            echo '</div>';
        }
    }
    echo '</div>';
    echo '</div>'; // scroll
    echo '<div class="youtube-playlist-form" style="margin-top:10px">';
    echo '<input type="text" id="youtubeNewTopicInput" class="youtube-search-input yt-radio-preset-input" placeholder="Anadir tema..." style="flex:1">';
    echo '<button type="button" id="youtubeNewTopicBtn" class="youtube-search-btn yt-radio-preset-btn" style="flex-shrink:0;width:auto">Crear</button>';
    echo '</div>';
    echo '</div>';

    // ═══ PANEL RADIOS ══════════════════════════════════════════════════════
    echo '<div class="yt-radio-sidebar" id="radiosPanel">';
    echo '<button type="button" class="yt-radio-sidebar-close" id="radiosPanelClose" title="Cerrar panel">&times;</button>';
    echo '<div class="yt-radio-presets-title">📻 RADIO EN DIRECTO</div>';
    echo '<div class="youtube-channel-grid" id="youtubeRadioGrid">';
    $radioStations = radio_default_stations();
    foreach ($radioStations as $rs) {
        $icon = e($rs['icon'] ?? '📻');
        $name = e($rs['name'] ?? '');
        $urlJs = e($rs['url'] ?? '');
        $freq = $rs['freq'] ?? null;
        $freqAttr = ($freq !== null) ? ' data-freq="' . e((string)$freq) . '"' : '';
        echo '<div class="youtube-channel-tag yt-radio-channel-tag youtube-radio-tag"' . $freqAttr . ' onclick="YTPlayer.playRadioStation(this,\'' . $urlJs . '\',\'' . addcslashes($rs['name'] ?? '', "'") . '\',\'' . addcslashes($rs['icon'] ?? '📻', "'") . '\')">';
        echo '<span class="youtube-channel-icon">' . $icon . '</span><span>' . $name . '</span>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';

    // ═══ SIDEBAR: Contenidos plegables ════════════════════════════════════
    echo '<div class="yt-radio-sidebar" id="ytRadioSidebar">';
    echo '<button type="button" class="yt-radio-sidebar-close" id="ytRadioSidebarClose" title="Cerrar panel">&times;</button>';

    // ═══ RESULTS: Búsqueda + Historial ════════════════════════════════════
    echo '<div class="youtube-results yt-radio-results" id="youtubeResults">';
    if ($lastVideo) {
        echo '<div class="youtube-section-title">Últimos escuchados</div>';
        echo '<div class="youtube-history-row" id="youtubeHistoryRow">';
        echo '<button type="button" class="youtube-history-arrow youtube-history-arrow-left" id="youtubeHistLeft" title="Anterior">&#9664;</button>';
        echo '<div class="youtube-history-scroll" id="youtubeHistoryScroll">';
        echo '<div class="youtube-result-grid youtube-result-grid-row yt-radio-result-grid" id="youtubeLastPlayed"></div>';
        echo '</div>';
        echo '<button type="button" class="youtube-history-arrow youtube-history-arrow-right" id="youtubeHistRight" title="Siguiente">&#9654;</button>';
        echo '</div>';
    }
    echo '<div class="youtube-section-title">Busca algo para empezar</div>';
    echo '<div class="youtube-result-grid yt-radio-result-grid" id="youtubeResultGrid"></div>';
    echo '</div>';

    // ── SUGERENCIAS IA ──
    echo '<div class="youtube-section yt-radio-section">';
    echo '<div class="youtube-section-title">Sugerencias IA</div>';
    echo '<button type="button" class="youtube-suggest-btn" id="youtubeSuggestBtn">Generar sugerencias</button>';
    echo '<div class="youtube-result-grid" id="youtubeSuggestGrid"></div>';
    echo '</div>';

    // ── MIS LISTAS ──
    echo '<div class="youtube-section yt-radio-section">';
    echo '<div class="youtube-section-title">Mis Listas de reproducción</div>';
    echo '<p class="youtube-section-hint">Crea listas para organizar tus videos. Pulsa <strong>+</strong> en un video para añadirlo, o <strong>&#128203;</strong> para ver y gestionar la lista.</p>';
    echo '<div class="youtube-playlist-form">';
    echo '<input type="text" id="youtubeNewPlaylistInput" class="youtube-search-input" placeholder="Nombre de la nueva lista..." style="flex:1">';
    echo '<button type="button" id="youtubeNewPlaylistBtn" class="youtube-search-btn" style="flex-shrink:0;width:auto">Crear lista</button>';
    echo '</div>';
    echo '<div class="youtube-playlist-list" id="youtubePlaylistList">';
    if (empty($playlists)) {
        echo '<div class="youtube-empty">No tienes listas todavía. Escribe un nombre arriba y pulsa "Crear lista".</div>';
    } else {
        foreach ($playlists as $pl) {
            $count = count($pl['videos'] ?? array());
            echo '<div class="youtube-playlist-item" data-playlist-id="' . e($pl['id']) . '">';
            echo '<span class="youtube-playlist-name" onclick="YTPlayer.openPlaylistDetail(\'' . e($pl['id']) . '\')" style="cursor:pointer" title="Ver lista completa">' . e($pl['name']) . ' <small>(' . $count . ' videos)</small></span>';
            echo '<div class="youtube-playlist-actions">';
            echo '<button type="button" class="youtube-mic-btn" onclick="YTPlayer.openPlaylistDetail(\'' . e($pl['id']) . '\')" title="Ver y gestionar lista">&#128203;</button>';
            echo '<button type="button" class="youtube-mic-btn" onclick="YTPlayer.playPlaylist(\'' . e($pl['id']) . '\')" title="Reproducir lista">&#9654;</button>';
            echo '<button type="button" class="youtube-mic-btn youtube-delete-btn" onclick="YTPlayer.deletePlaylist(\'' . e($pl['id']) . '\')" title="Eliminar lista">&times;</button>';
            echo '</div>';
            echo '</div>';
        }
    }
    echo '</div>';
    echo '</div>'; // playlist section

    echo '</div>'; // yt-radio-sidebar

    // ── INITIAL DATA (igual que desktop) ───────────────────────────────────
    echo '<script>';
    echo 'window._ytPlaylists = ' . json_encode($playlists, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';';
    echo 'window._ytChannels = ' . json_encode($channels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';';
    echo 'window._ytHistory = ' . json_encode(array_slice($history, 0, 20), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';';
    if ($lastVideo) {
        echo 'window._ytLastVideo = ' . json_encode($lastVideo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';';
    } else {
        echo 'window._ytLastVideo = null;';
    }
    echo '</script>';

    // ── MODAL: Añadir a lista (server-rendered) ───────────────────────────
    echo '<div class="add-pl-modal-overlay" id="addPlModalLite" style="display:none" onclick="if(event.target===this)this.style.display=\'none\'">';
    echo '<div class="add-pl-modal-box">';
    echo '<h3>Añadir a lista</h3>';
    echo '<p class="add-pl-video-hint" id="addPlVideoHint">';
    if ($lastVideo) {
        echo 'Video: ' . e($lastVideo['title'] ?? '');
    }
    echo '</p>';
    if (empty($playlists)) {
        echo '<p class="add-pl-empty">No tienes listas todavía. Usa el panel ♫ para crear una.</p>';
    } else {
        echo '<form method="POST" action="index.php?page=josue&tab=reproductor&lite=1">';
        echo '<input type="hidden" name="action" value="youtube_add_to_pl_direct">';
        echo '<input type="hidden" name="video_id" id="addPlVid" value="">';
        echo '<input type="hidden" name="title" id="addPlVTitle" value="">';
        echo '<input type="hidden" name="thumbnail" id="addPlVThumb" value="">';
        echo '<input type="hidden" name="channel_name" id="addPlVChan" value="">';
        echo '<div class="add-pl-list">';
        foreach ($playlists as $pl) {
            $count = count($pl['videos'] ?? []);
            $name = e($pl['name']);
            $pid = e($pl['id']);
            echo '<button type="submit" name="playlist_id" value="' . $pid . '" class="add-pl-item">';
            echo '<span>' . $name . '</span>';
            echo '<small>' . $count . ' videos</small>';
            echo '</button>';
        }
        echo '</div>';
        echo '</form>';
    }
    echo '<button type="button" class="add-pl-close-btn" onclick="document.getElementById(\'addPlModalLite\').style.display=\'none\'">Cerrar</button>';
    echo '</div></div>';

    echo '</div>'; // youtube-reproductor

    // ── Knight Rider melody audio (hidden) ──
    echo '<audio id="kittMelody" src="assets/knight-rider-intro.mp3?v=' . filemtime(__DIR__ . '/../assets/knight-rider-intro.mp3') . '" preload="auto" style="display:none"></audio>';
}




function render_youtube_player() {
    $playParam = trim((string)request_get('play', ''));
    $playlists = storage_read('youtube_playlists.json');
    $channels = storage_read('youtube_channels.json');
    $history = storage_read('youtube_history.json');
    if (!is_array($playlists)) $playlists = array();
    if (!is_array($channels)) $channels = array();
    if (!is_array($history)) $history = array();

    // Ultimo video del historial
    $lastVideo = !empty($history) ? $history[0] : null;

    // 🔁 Modo Lite: radio vintage
    $isLite = request_get('lite') === '1';
    if ($isLite) {
        render_youtube_player_lite($playParam, $playlists, $channels, $history, $lastVideo);
        return;
    }

    echo '<div class="youtube-reproductor" id="youtubeReproductor"';
    if ($playParam !== '') {
        echo ' data-auto-search="' . e($playParam) . '"';
    }
    echo '>';

    // ── Botón flotante cerrar fullscreen (visible solo en modo fullscreen) ──
    echo '<button type="button" class="josue-yt-fs-close-float" id="josueYtFsClose" title="Mostrar menús">⟲ Menús</button>';

    // ── Barra de búsqueda ──────────────────────────────────────────
    echo '<div class="youtube-search-bar">';
    echo '<div class="youtube-search" id="youtubeSearchRow">';
    echo '<input type="text" id="youtubeSearchInput" class="youtube-search-input" placeholder="Buscar en YouTube... (ej: musica, artista, cancion)" autocomplete="off">';
    echo '<button type="button" id="youtubeVoiceSearchBtn" class="youtube-voice-search-btn" title="Buscar por voz" style="margin-left:6px">🎤</button>';
    echo '<button type="button" id="youtubeSearchBtn" class="youtube-search-btn">Buscar</button>';
    echo '<button type="button" id="josueYtFsBtn" class="youtube-search-btn" style="background:rgba(96,165,250,.12);border-color:rgba(96,165,250,.22);color:#93c5fd;width:auto;flex-shrink:0;margin-left:6px" title="Pantalla completa">⛶</button>';
    echo '<div class="youtube-search-spinner" id="youtubeSearchSpinner" style="display:none"></div>';
    echo '</div>';
    echo '</div>';

    // ── Reproductor mini (50%) + Canales (50%) ──────────────────────
    echo '<div class="youtube-main">';

    // Columna izquierda: reproductor
    echo '<div class="youtube-player-column">';
    echo '<div class="youtube-mini-player" id="youtubeMiniPlayer">';
    echo '<div class="youtube-mini-player-placeholder" id="youtubePlayerPlaceholder">';
    echo '<div class="youtube-placeholder-icon">&#9654;</div>';
    echo '<div class="youtube-placeholder-text">Selecciona un video</div>';
    echo '</div>';
    echo '<div id="youtubePlayerContainer" style="display:none"></div>';
    echo '<div class="youtube-now-playing" id="youtubeNowPlaying" style="display:none">';
    echo '<span id="youtubeNowPlayingTitle"></span>';
    echo '</div>';
    echo '</div>';

    // Controles del reproductor
    echo '<div class="youtube-controls" id="youtubeControls" style="display:none">';
    echo '<button type="button" class="youtube-ctrl-btn" id="youtubePrevBtn" title="Anterior">&#9664;</button>';
    echo '<button type="button" class="youtube-ctrl-btn" id="youtubePlayPauseBtn" title="Pausa">&#10074;&#10074;</button>';
    echo '<button type="button" class="youtube-ctrl-btn" id="youtubeNextBtn" title="Siguiente">&#9654;</button>';
    // Slider de volumen + botones
    echo '<div class="youtube-volume-row">';
    echo '<button type="button" class="youtube-ctrl-btn youtube-vol-btn" id="youtubeVolDownBtn" title="Bajar volumen">&#8722;</button>';
    echo '<input type="range" id="youtubeVolumeSlider" class="youtube-volume-slider" min="0" max="100" value="100" title="Volumen">';
    echo '<span class="youtube-volume-label" id="youtubeVolumeLabel">100%</span>';
    echo '<button type="button" class="youtube-ctrl-btn youtube-vol-btn" id="youtubeVolUpBtn" title="Subir volumen">+</button>';
    echo '</div>';
    // Boost de audio
    echo '<div class="youtube-boost-row">';
    echo '<label class="youtube-boost-toggle" id="youtubeBoostToggle" title="Audio Boost: amplificación real vía Web Audio API">';
    echo '<input type="checkbox" id="youtubeBoostCheckbox"> <span class="youtube-boost-label">🔊 Boost</span>';
    echo '</label>';
    echo '<input type="range" id="youtubeBoostSlider" class="youtube-boost-slider" min="50" max="300" value="150" style="display:none">';
    echo '<span class="youtube-boost-value" id="youtubeBoostValue" style="display:none">150%</span>';
    echo '<span class="youtube-boost-status" id="youtubeBoostStatus"></span>';
    echo '</div>';
    // Add to playlist
    echo '<button type="button" class="youtube-ctrl-btn" id="youtubeAddToPlBtn" title="Añadir a lista" style="border-color:var(--accent);color:var(--accent)" onclick="YTPlayer.addCurrentToPlaylist()">+</button>';
    echo '</div>';

    echo '</div>'; // player column

    // Columna derecha: Canales tematicos
    echo '<div class="youtube-channel-sidebar">';
    echo '<div class="youtube-section">';
    echo '<div class="youtube-section-title">Canales tematicos</div>';
    echo '<p class="youtube-section-hint">Temas generados por IA. Pulsa un canal para ver videos actuales de ese tema.</p>';
    echo '<div class="youtube-channel-grid" id="youtubeChannelGrid">';
    if (empty($channels)) {
        echo '<div class="youtube-channel-tag youtube-channel-seed" onclick="YTPlayer.seedChannels()" id="ytSeedTag">';
        echo '<span class="youtube-channel-icon">✨</span><span>Cargar canales</span>';
        echo '</div>';
    } else {
        foreach ($channels as $ch) {
            $icon = e($ch['icon'] ?? '📺');
            $name = e($ch['name'] ?? '');
            $chId = e($ch['id'] ?? '');
            $type = e($ch['type'] ?? '');
            echo '<div class="youtube-channel-tag' . ($type === 'ai_suggested' ? ' youtube-channel-ai' : '') . '" onclick="YTPlayer.loadTopicChannel(\'' . e($chId) . '\')">';
            echo '<span class="youtube-channel-icon">' . $icon . '</span><span>' . $name . '</span>';
            if ($type === 'custom' || $type === 'ai_suggested') {
                echo '<button type="button" class="youtube-channel-del" onclick="event.stopPropagation();YTPlayer.deleteTopicChannel(\'' . e($chId) . '\')" title="Eliminar">&times;</button>';
            }
            echo '</div>';
        }
    }
    echo '</div>';
    echo '<div class="youtube-playlist-form" style="margin-top:10px">';
    echo '<input type="text" id="youtubeNewTopicInput" class="youtube-search-input" placeholder="Anadir tema..." style="flex:1">';
    echo '<button type="button" id="youtubeNewTopicBtn" class="youtube-search-btn" style="flex-shrink:0;width:auto">Crear</button>';
    echo '</div>';
    echo '</div>';

    // ── Radio en directo ──────────────────────────────────────
    echo '<div class="youtube-section" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--line)">';
    echo '<div class="youtube-section-title">📻 Radio en directo</div>';
    echo '<p class="youtube-section-hint">Emisoras de radio españolas en directo. Pulsa para sintonizar.</p>';
    echo '<div class="youtube-channel-grid" id="youtubeRadioGrid">';
    $radioStations = radio_default_stations();
    foreach ($radioStations as $rs) {
        $icon = e($rs['icon'] ?? '📻');
        $name = e($rs['name'] ?? '');
        $urlJs = e($rs['url'] ?? '');
        echo '<div class="youtube-channel-tag youtube-radio-tag" onclick="YTPlayer.playRadioStation(this,\'' . $urlJs . '\',\'' . addcslashes($rs['name'] ?? '', "'") . '\',\'' . addcslashes($rs['icon'] ?? '📻', "'") . '\')">';
        echo '<span class="youtube-channel-icon">' . $icon . '</span><span>' . $name . '</span>';
        echo '</div>';
    }
    echo '</div>';
    echo '</div>';

    echo '</div>'; // channel sidebar

    // ── Resultados (full width, debajo de player + canales) ─────────
    echo '<div class="youtube-results" id="youtubeResults">';
    if ($lastVideo) {
        echo '<div class="youtube-section-title">Ultimos escuchados</div>';
        echo '<div class="youtube-history-row" id="youtubeHistoryRow">';
        echo '<button type="button" class="youtube-history-arrow youtube-history-arrow-left" id="youtubeHistLeft" title="Anterior">&#9664;</button>';
        echo '<div class="youtube-history-scroll" id="youtubeHistoryScroll">';
        echo '<div class="youtube-result-grid youtube-result-grid-row" id="youtubeLastPlayed"></div>';
        echo '</div>';
        echo '<button type="button" class="youtube-history-arrow youtube-history-arrow-right" id="youtubeHistRight" title="Siguiente">&#9654;</button>';
        echo '</div>';
    }
    echo '<div class="youtube-section-title">Busca algo para empezar</div>';
    echo '<div class="youtube-result-grid" id="youtubeResultGrid"></div>';
    echo '</div>';

    echo '</div>'; // youtube-main

    // ── Sugerencias IA ─────────────────────────────────────────────
    echo '<div class="youtube-section" id="youtubeSuggestions">';
    echo '<div class="youtube-section-title">Sugerencias IA</div>';
    echo '<button type="button" class="youtube-suggest-btn" id="youtubeSuggestBtn">Generar sugerencias</button>';
    echo '<div class="youtube-result-grid" id="youtubeSuggestGrid"></div>';
    echo '</div>';

    // ── Mis Listas ─────────────────────────────────────────────────
    echo '<div class="youtube-section">';
    echo '<div class="youtube-section-title">Mis Listas de reproduccion</div>';
    echo '<p class="youtube-section-hint">Crea listas para organizar tus videos. Pulsa <strong>+</strong> en un video para anadirlo, o <strong>📋</strong> para ver y gestionar la lista.</p>';
    echo '<div class="youtube-playlist-form">';
    echo '<input type="text" id="youtubeNewPlaylistInput" class="youtube-search-input" placeholder="Nombre de la nueva lista..." style="flex:1">';
    echo '<button type="button" id="youtubeNewPlaylistBtn" class="youtube-search-btn" style="flex-shrink:0;width:auto">Crear lista</button>';
    echo '</div>';
    echo '<div class="youtube-playlist-list" id="youtubePlaylistList">';
    if (empty($playlists)) {
        echo '<div class="youtube-empty">No tienes listas todavia. Escribe un nombre arriba y pulsa "Crear lista".</div>';
    } else {
        foreach ($playlists as $pl) {
            $count = count($pl['videos'] ?? array());
            echo '<div class="youtube-playlist-item" data-playlist-id="' . e($pl['id']) . '">';
            echo '<span class="youtube-playlist-name">' . e($pl['name']) . ' <small>(' . $count . ' videos)</small></span>';
            echo '<div class="youtube-playlist-actions">';
            echo '<button type="button" class="youtube-mic-btn" onclick="YTPlayer.openPlaylistDetail(\'' . e($pl['id']) . '\')" title="Ver y gestionar lista">📋</button>';
            echo '<button type="button" class="youtube-mic-btn" onclick="YTPlayer.playPlaylist(\'' . e($pl['id']) . '\')" title="Reproducir lista">&#9654;</button>';
            echo '<button type="button" class="youtube-mic-btn youtube-delete-btn" onclick="YTPlayer.deletePlaylist(\'' . e($pl['id']) . '\')" title="Eliminar lista">&times;</button>';
            echo '</div>';
            echo '</div>';
        }
    }
    echo '</div>';
    echo '</div>'; // playlist section

    // ── Initial data for JS ─────────────────────────────────────────
    echo '<script>';
    echo 'window._ytPlaylists = ' . json_encode($playlists, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';';
    echo 'window._ytChannels = ' . json_encode($channels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';';
    echo 'window._ytHistory = ' . json_encode(array_slice($history, 0, 20), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';';
    if ($lastVideo) {
        echo 'window._ytLastVideo = ' . json_encode($lastVideo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';';
    } else {
        echo 'window._ytLastVideo = null;';
    }
    echo '</script>';

    // ── Auto-fullscreen al cargar el reproductor ─────────────────────
    echo '<script>';
    echo '(function(){if(document.body.classList.contains("josue-yt-fs")||document.body.classList.contains("is-lite"))return;document.body.classList.add("josue-yt-fs");})();';
    echo '</script>';

    echo '</div>'; // youtube-reproductor
}

/**
 * Alerta en el dashboard cuando el proxy de audio de YouTube está caído.
 * Solo se muestra si hay errores registrados (no-lite, solo admin).
 */
function _render_audio_proxy_alert() {
    $errors = storage_read('youtube_audio_errors.json');
    if (!is_array($errors) || ($errors['status'] ?? 'ok') !== 'broken') {
        return; // Todo OK, no mostrar nada
    }

    // Auto-sanación: re-chequear el proxy como máximo cada 30 min. Evita que la
    // alerta quede clavada si el proxy se recupera sin pasar por el reproductor.
    $lastCheck = strtotime($errors['last_checked'] ?? '2000-01-01');
    if (time() - $lastCheck >= 1800) {
        if (youtube_audio_proxy_health_check()) {
            _youtube_reset_audio_errors();
            return;
        }
        // Sigue caído: refrescar solo last_checked (sin inflar error_count
        // ni perder el error original que se muestra al admin).
        $errors['last_checked'] = now_datetime();
        storage_write('youtube_audio_errors.json', $errors);
    }

    $lastFailure = $errors['last_failure'] ?? '?';
    $errorCount = (int)($errors['error_count'] ?? 0);
    $lastError = $errors['last_error'] ?? 'Error desconocido';

    echo '<section class="panel audio-proxy-alert" style="background:#3b1010;border:2px solid #dc2626;padding:14px 18px;margin-bottom:12px;border-radius:10px;display:flex;align-items:center;gap:12px">';
    echo '<span style="font-size:24px">🔊❌</span>';
    echo '<div style="flex:1">';
    echo '<strong style="color:#fca5a5;font-size:15px">Audio Boost caído</strong>';
    echo '<p style="color:#fca5a5;margin:4px 0 0;font-size:12px">';
    echo 'El sistema de amplificación de YouTube ha dejado de funcionar (posible cambio en la web de YouTube). ';
    echo 'Fallos: ' . $errorCount . '. Último: ' . e($lastFailure) . '. ' . e($lastError) . '.';
    echo '</p>';
    echo '</div>';
    echo '</section>';
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
