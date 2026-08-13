<?php
/**
 * inbox_shared.php — Renderizado compartido para tabs de conversaciones.
 *
 * Usado por: ?page=comercial (superwasap) y ?page=inbox (nuevo inbox).
 *
 * Cada página define un $ctx con:
 *   'id'                => 'comercial' | 'inbox'
 *   'url_fn'            => nombre de función callable (string) para construir URLs
 *   'tab_name'          => nombre del tab (ej. 'conversaciones')
 *   'page_name'         => nombre de la página (ej. 'comercial' | 'inbox')
 *   'feed_url'          => URL del endpoint de polling (relativa a raíz)
 *   'can_toggle_thread' => bool — mostrar toggle 🔇 por conversación
 *   'show_export'       => bool — mostrar botón exportar
 */

if (!defined('INBOX_SHARED_LOADED')) {
    define('INBOX_SHARED_LOADED', true);

    // ── Helpers internos ──

    function _inbox_snippet($text, $max = 120) {
        $clean = preg_replace('/\s+/u', ' ', trim((string)$text));
        if ($clean === '') return '—';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($clean, 'UTF-8') > $max) return mb_substr($clean, 0, $max - 1, 'UTF-8') . '…';
            return $clean;
        }
        if (strlen($clean) > $max) return substr($clean, 0, $max - 1) . '…';
        return $clean;
    }

    function _inbox_triage_level($thread) {
        $stage = trim((string)($thread['stage'] ?? ''));
        $humanTaken = !empty($thread['human_taken']);
        $hasReply = (int)($thread['replies_count'] ?? 0) > 0;
        $hasLead = trim((string)($thread['lead_id'] ?? '')) !== '';
        if ($stage === 'very_hot' || (!$humanTaken && $hasReply && $stage !== 'discarded')) return 'P1';
        if ($stage === 'discarded' || $hasLead) return 'P3';
        return 'P2';
    }

    function _inbox_is_recent_reply($thread) {
        if ((int)($thread['replies_count'] ?? 0) <= 0) return false;
        $ts = trim((string)($thread['updated_at'] ?? ''));
        if ($ts === '') return false;
        $when = strtotime($ts);
        if ($when === false) return false;
        return $when >= (time() - 36 * 3600);
    }

    // ── Función principal de filtrado ──

    function inbox_filter_threads($threads, $stageFilter, $quickFilter, $lineFilter, $processFilter) {
        $out = array();
        foreach ($threads as $thread) {
            if (!comercial_thread_matches_filter($thread, $stageFilter)) continue;
            $threadLineId = trim((string)($thread['line_id'] ?? ''));
            $threadProcess = trim((string)($thread['process_slug'] ?? ''));
            if ($lineFilter !== '' && $lineFilter !== 'all' && $threadLineId !== $lineFilter) continue;
            if ($processFilter !== '' && $processFilter !== 'all' && $threadProcess !== $processFilter) continue;
            if ($quickFilter === 'unhandled' && !empty($thread['human_taken'])) continue;
            if ($quickFilter === 'without_lead' && trim((string)($thread['lead_id'] ?? '')) !== '') continue;
            if ($quickFilter === 'recent_replies' && !_inbox_is_recent_reply($thread)) continue;
            $out[] = $thread;
        }
        return $out;
    }

    // ── Render: barra de filtros (chips de etapa + export) ──

    function inbox_render_stage_filters($ctx, $threads, $stageFilter) {
        $urlFn = $ctx['url_fn'];
        $tabName = $ctx['tab_name'];
        $availableFilters = array(
            'all' => 'Todas',
            'opened' => 'Abiertas directas',
            'responded' => 'Respondidas',
            'qualified' => 'Qualifieds',
            'very_hot' => 'Muy calientes',
            'discarded' => 'Descartadas',
        );
        echo '<div class="commercial-filter-bar">';
        foreach ($availableFilters as $filterKey => $filterLabel) {
            $count = 0;
            foreach ($threads as $t) {
                if (comercial_thread_matches_filter($t, $filterKey)) $count++;
            }
            $activeClass = $stageFilter === $filterKey ? ' active' : '';
            echo '<span class="commercial-filter-chip' . $activeClass . '">';
            echo '<a href="' . e(call_user_func($urlFn, $tabName, array('stage_filter' => $filterKey))) . '">' . e($filterLabel) . ' · ' . e((string)$count) . '</a>';
            if (!empty($ctx['show_export'])) {
                echo ' <form method="post" style="display:inline-block; margin-left:6px;">';
                echo '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
                echo '<input type="hidden" name="action" value="comercial_export_threads_csv">';
                echo '<input type="hidden" name="stage_filter" value="' . e($filterKey) . '">';
                echo '<input type="hidden" name="redirect" value="' . e(call_user_func($urlFn, $tabName, array('stage_filter' => $filterKey))) . '">';
                echo '<button type="submit" class="btn-secondary-mini">Exportar a Excel</button>';
                echo '</form>';
            }
            echo '</span>';
        }
        echo '</div>';
    }

    // ── Render: quick filters + dropdowns de línea/proceso ──

    function inbox_render_quick_filters($ctx, $lines, $processes, $stageFilter, $quickFilter, $lineFilter, $processFilter) {
        $urlFn = $ctx['url_fn'];
        $tabName = $ctx['tab_name'];
        $pageName = $ctx['page_name'];

        // Quick filters
        $quickFilters = array(
            'all' => 'Todo',
            'unhandled' => 'Sin gestionar',
            'without_lead' => 'Sin lead',
            'recent_replies' => 'Respuestas recientes',
        );
        echo '<div class="commercial-filter-bar">';
        foreach ($quickFilters as $qKey => $qLabel) {
            $qClass = $quickFilter === $qKey ? ' active' : '';
            echo '<button type="submit" name="quick_filter" value="' . e($qKey) . '" class="commercial-filter-chip commercial-filter-btn' . $qClass . '">' . e($qLabel) . '</button>';
        }
        echo '</div>';

        // Line / Process dropdowns
        echo '<div class="form-grid-2">';

        echo '<div class="field"><label>Línea</label><select name="line_filter">';
        $lineOpts = array('all' => 'Todas las líneas');
        foreach ($lines as $line) {
            $lid = (string)($line['id'] ?? '');
            if ($lid === '') continue;
            $lineOpts[$lid] = trim((string)($line['nombre'] ?? '')) !== '' ? (string)$line['nombre'] : ('Línea ' . $lid);
        }
        foreach ($lineOpts as $val => $text) {
            echo '<option value="' . e((string)$val) . '"' . ((string)$lineFilter === (string)$val ? ' selected' : '') . '>' . e((string)$text) . '</option>';
        }
        echo '</select></div>';

        echo '<div class="field"><label>Proceso</label><select name="process_filter">';
        $procOpts = array('all' => 'Todos los procesos');
        foreach ($processes as $proc) {
            $slug = trim((string)($proc['slug'] ?? ''));
            if ($slug !== '') $procOpts[$slug] = trim((string)($proc['nombre'] ?? $slug));
        }
        foreach ($procOpts as $val => $text) {
            echo '<option value="' . e((string)$val) . '"' . ((string)$processFilter === (string)$val ? ' selected' : '') . '>' . e((string)$text) . '</option>';
        }
        echo '</select></div>';
        echo '</div>';
        echo '<div class="commercial-inline-checks"><button type="submit" class="btn-secondary-mini">Aplicar filtros</button></div>';
    }

    // ── Render: vista detalle de un hilo ──

    function inbox_render_thread_detail($ctx, $viewThread, $snapshot, $linesIndexed, $stageFilter, $quickFilter, $lineFilter, $processFilter) {
        $urlFn = $ctx['url_fn'];
        $tabName = $ctx['tab_name'];
        $feedUrl = $ctx['feed_url'];
        $canToggle = !empty($ctx['can_toggle_thread']);
        $viewStage = trim((string)($viewThread['stage'] ?? ''));
        $viewLineName = isset($linesIndexed[(string)($viewThread['line_id'] ?? '')]) ? trim((string)($linesIndexed[(string)$viewThread['line_id']]['nombre'] ?? '')) : '';
        $threadId = (string)($viewThread['id'] ?? '');
        $isPaused = !empty($viewThread['inbox_paused']) || !empty($viewThread['human_taken']);

        echo '<div class="commercial-thread-view">';
        echo '<div class="commercial-thread-view-head">';
        echo '<div><strong>Conversación completa · </strong>';
        crm_render_phone_value((string)($viewThread['target_phone'] ?? ''), array('strong' => true));
        echo '</div>';
        echo '<a class="mini-link" href="' . e(call_user_func($urlFn, $tabName, array('stage_filter' => $stageFilter !== '' ? $stageFilter : 'all'))) . '">Cerrar</a>';
        echo '</div>';

        // Subhead con datos + toggle por conversación
        echo '<div class="commercial-thread-subhead" id="commercialThreadHeader"'
            . ' data-updated-at="' . e((string)($snapshot['updated_at'] ?? '')) . '"'
            . ' data-thread-id="' . e($threadId) . '"'
            . ' data-feed-url="' . e($feedUrl) . '">';
        echo '<div style="display:flex; justify-content:space-between; align-items:center;">';
        echo '<div>';
        echo '<span class="muted-small">Proceso: <strong id="commercialThreadProcess">' . e((string)($viewThread['process_slug'] ?? '')) . '</strong> · Estado: <span id="commercialThreadStageLabel" class="status-pill ' . e(comercial_thread_stage_css_class($viewStage)) . '">' . e(comercial_thread_stage_label($viewStage)) . '</span></span><br>';
        echo '<span class="muted-small">WhatsApp origen desde el que se habla:</span> <span id="commercialThreadLine">';
        crm_render_phone_value((string)($viewThread['line_phone'] ?? ''));
        echo ($viewLineName !== '' ? ' · ' . e($viewLineName) : '');
        echo '</span>';
        echo '</div>';
        if ($canToggle) {
            echo '<div>';
            echo '<form method="post" style="display:inline-block;">';
            echo '<input type="hidden" name="action" value="inbox_toggle_thread_pause">';
            echo '<input type="hidden" name="thread_id" value="' . e($threadId) . '">';
            echo '<input type="hidden" name="return_stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
            echo '<input type="hidden" name="return_view_thread" value="' . e($threadId) . '">';
            echo '<button type="submit" class="btn-secondary-mini" style="font-size:16px;">' . ($isPaused ? '🔇 Pausada' : '🔊 Activa') . '</button>';
            echo '</form>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';

        // Acciones de etapa
        echo '<div class="commercial-thread-top-actions">';
        if ($viewStage !== 'qualified' && $viewStage !== 'very_hot') {
            echo '<form method="post">';
            echo '<input type="hidden" name="action" value="comercial_set_thread_stage">';
            echo '<input type="hidden" name="thread_id" value="' . e($threadId) . '">';
            echo '<input type="hidden" name="stage" value="qualified">';
            echo '<input type="hidden" name="return_stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
            echo '<input type="hidden" name="return_view_thread" value="' . e($threadId) . '">';
            echo '<button type="submit" class="btn-secondary-mini">Marcar cualificado</button>';
            echo '</form>';
        }
        if ($viewStage !== 'very_hot' && $viewStage !== 'discarded') {
            echo '<form method="post">';
            echo '<input type="hidden" name="action" value="comercial_set_thread_stage">';
            echo '<input type="hidden" name="thread_id" value="' . e($threadId) . '">';
            echo '<input type="hidden" name="stage" value="very_hot">';
            echo '<input type="hidden" name="return_stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
            echo '<input type="hidden" name="return_view_thread" value="' . e($threadId) . '">';
            echo '<button type="submit" class="btn-secondary-mini">Marcar muy caliente</button>';
            echo '</form>';
        }
        if ($viewStage !== 'discarded') {
            echo '<form method="post">';
            echo '<input type="hidden" name="action" value="comercial_set_thread_stage">';
            echo '<input type="hidden" name="thread_id" value="' . e($threadId) . '">';
            echo '<input type="hidden" name="stage" value="discarded">';
            echo '<input type="hidden" name="return_stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
            echo '<input type="hidden" name="return_view_thread" value="' . e($threadId) . '">';
            echo '<button type="submit" class="btn-secondary-mini btn-danger-soft">Descartar conversación</button>';
            echo '</form>';
        }
        echo '</div>';

        // Timeline chat
        echo '<div class="commercial-thread-chat-shell">';
        echo '<div class="commercial-thread-chat-bar">WhatsApp en vivo · se actualiza solo</div>';
        echo '<div id="commercialThreadTimelineWrap">';
        echo (string)($snapshot['timeline_html'] ?? '');
        echo '</div>';
        echo '<form method="post" class="commercial-thread-view-reply-form">';
        echo '<input type="hidden" name="action" value="comercial_send_thread_message">';
        echo '<input type="hidden" name="thread_id" value="' . e($threadId) . '">';
        echo '<input type="hidden" name="return_stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
        echo '<input type="hidden" name="return_view_thread" value="' . e($threadId) . '">';
        echo '<textarea name="manual_text" rows="3" placeholder="Escribe aquí para responder desde este mismo WhatsApp origen..."></textarea>';
        echo '<button type="submit" class="btn-primary">Responder desde este móvil origen</button>';
        echo '</form>';
        echo '</div>';

        // Webhook log
        echo '<div class="commercial-thread-webhook-block">';
        echo '<strong>Log webhook / inbound</strong>';
        echo '<div id="commercialThreadWebhookWrap">';
        echo (string)($snapshot['webhook_log_html'] ?? '');
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    // ── Render: tabla de hilos con triage ──

    function inbox_render_thread_table($ctx, $filteredThreads, $linesIndexed, $stageFilter, $quickFilter, $lineFilter, $processFilter) {
        $urlFn = $ctx['url_fn'];
        $tabName = $ctx['tab_name'];
        $canToggle = !empty($ctx['can_toggle_thread']);

        // Agrupar por triage
        $triageGroups = array('P1' => array(), 'P2' => array(), 'P3' => array());
        foreach ($filteredThreads as $thread) {
            $triage = _inbox_triage_level($thread);
            if (!isset($triageGroups[$triage])) $triageGroups[$triage] = array();
            $triageGroups[$triage][] = $thread;
        }

        echo '<div class="table-wrap"><table><thead><tr><th>Proceso</th><th>Cliente</th><th>Estado</th><th>Vista rápida</th><th></th></tr></thead><tbody>';
        foreach (array('P1', 'P2', 'P3') as $triage) {
            $groupRows = isset($triageGroups[$triage]) ? (array)$triageGroups[$triage] : array();
            if (empty($groupRows)) continue;

            echo '<tr class="commercial-triage-group-row"><td colspan="5"><span class="commercial-triage-badge ' . e(strtolower($triage)) . '">' . e($triage) . '</span> <strong>Prioridad ' . e($triage) . '</strong> <span class="muted-small">· ' . e((string)count($groupRows)) . ' conversaciones</span></td></tr>';

            foreach ($groupRows as $thread) {
                $stage = trim((string)($thread['stage'] ?? ''));
                $lineName = isset($linesIndexed[(string)($thread['line_id'] ?? '')]) ? trim((string)($linesIndexed[(string)$thread['line_id']]['nombre'] ?? '')) : '';
                $tid = (string)($thread['id'] ?? '');
                $isPaused = !empty($thread['inbox_paused']) || !empty($thread['human_taken']);

                echo '<tr class="commercial-thread-row stage-' . e($stage) . '">';
                echo '<td>' . e((string)$thread['process_slug']) . '<br><span class="muted-small">línea </span>';
                crm_render_phone_value((string)($thread['line_phone'] ?? ''));
                if ($lineName !== '') echo '<span class="muted-small"> · ' . e($lineName) . '</span>';
                echo '</td>';

                echo '<td>';
                crm_render_phone_value((string)($thread['target_phone'] ?? ''), array('strong' => true));
                echo '<br><span class="muted-small">replies: ' . e((string)$thread['replies_count']) . ' · envíos: ' . e((string)$thread['messages_sent_count']) . ' · enviado: ' . e((string)($thread['created_at'] ?? '')) . '</span>';
                if ($canToggle && $isPaused) echo ' <span class="muted-small">🔇</span>';
                echo '</td>';

                echo '<td><span class="status-pill ' . e(comercial_thread_stage_css_class($stage)) . '">' . e(comercial_thread_stage_label($stage)) . '</span></td>';

                echo '<td><div class="commercial-row-preview">';
                echo '<div class="commercial-row-snippet in"><strong>IN</strong> ' . e(_inbox_snippet((string)($thread['last_inbound_text'] ?? ''))) . '</div>';
                echo '<div class="commercial-row-snippet out"><strong>OUT</strong> ' . e(_inbox_snippet((string)($thread['last_outbound_text'] ?? ''))) . '</div>';
                echo '</div></td>';

                echo '<td class="commercial-thread-actions-cell">';
                if ($stage !== 'qualified' && $stage !== 'very_hot') {
                    echo '<form method="post" style="display:inline-block; margin-bottom:6px;">';
                    echo '<input type="hidden" name="action" value="comercial_set_thread_stage">';
                    echo '<input type="hidden" name="thread_id" value="' . e($tid) . '">';
                    echo '<input type="hidden" name="stage" value="qualified">';
                    echo '<input type="hidden" name="return_stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
                    echo '<button type="submit" class="btn-secondary-mini">Marcar cualificado</button>';
                    echo '</form><br>';
                }
                if ($stage !== 'very_hot' && $stage !== 'discarded') {
                    echo '<form method="post" style="display:inline-block; margin-bottom:6px;">';
                    echo '<input type="hidden" name="action" value="comercial_set_thread_stage">';
                    echo '<input type="hidden" name="thread_id" value="' . e($tid) . '">';
                    echo '<input type="hidden" name="stage" value="very_hot">';
                    echo '<input type="hidden" name="return_stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
                    echo '<button type="submit" class="btn-secondary-mini">Marcar muy caliente</button>';
                    echo '</form><br>';
                }
                if ($stage !== 'discarded') {
                    echo '<form method="post" style="display:inline-block; margin-bottom:6px;">';
                    echo '<input type="hidden" name="action" value="comercial_set_thread_stage">';
                    echo '<input type="hidden" name="thread_id" value="' . e($tid) . '">';
                    echo '<input type="hidden" name="stage" value="discarded">';
                    echo '<input type="hidden" name="return_stage_filter" value="' . e($stageFilter !== '' ? $stageFilter : 'all') . '">';
                    echo '<button type="submit" class="btn-secondary-mini btn-danger-soft">Descartar</button>';
                    echo '</form><br>';
                }
                echo '<form method="post" style="display:inline-block;">';
                echo '<input type="hidden" name="action" value="comercial_promote_thread">';
                echo '<input type="hidden" name="thread_id" value="' . e($tid) . '">';
                echo '<button type="submit" class="btn-primary btn-small">Crear lead</button>';
                echo '</form>';
                echo '<br><a class="mini-link commercial-open-thread-cta" href="' . e(call_user_func($urlFn, $tabName, array('stage_filter' => $stageFilter !== '' ? $stageFilter : 'all', 'quick_filter' => $quickFilter !== '' ? $quickFilter : 'all', 'line_filter' => $lineFilter !== '' ? $lineFilter : 'all', 'process_filter' => $processFilter !== '' ? $processFilter : 'all', 'view_thread' => $tid))) . '">Abrir hilo completo</a>';
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table></div>';
    }

    // ── Render: JS de auto-refresh del timeline ──

    function inbox_render_polling_js() {
        echo '<script>(function(){'
            . 'const root=document.getElementById("commercialThreadHeader");'
            . 'if(!root){return;}'
            . 'const feedUrl=root.getAttribute("data-feed-url");'
            . 'const threadId=root.getAttribute("data-thread-id");'
            . 'const timeline=document.getElementById("commercialThreadTimelineWrap");'
            . 'const webhook=document.getElementById("commercialThreadWebhookWrap");'
            . 'const stageLabel=document.getElementById("commercialThreadStageLabel");'
            . 'let currentUpdated=root.getAttribute("data-updated-at")||"";'
            . 'let busy=false;'
            . 'function tick(){'
            . 'if(busy||document.hidden){return;}'
            . 'busy=true;'
            . 'fetch(feedUrl+"?thread_id="+encodeURIComponent(threadId)+"&_="+Date.now(),{credentials:"same-origin"})'
            . '.then(function(r){return r.json();})'
            . '.then(function(data){'
            . 'if(!data||!data.ok||!data.thread){return;}'
            . 'const next=data.thread.updated_at||"";'
            . 'if(next!==""&&next!==currentUpdated){'
            . 'currentUpdated=next;'
            . 'root.setAttribute("data-updated-at",next);'
            . 'if(stageLabel){stageLabel.textContent=data.thread.stage_label||"";stageLabel.className="status-pill "+(data.thread.stage_css||"muted");}'
            . 'if(timeline){timeline.innerHTML=data.thread.timeline_html||""; const el=timeline.querySelector(".commercial-thread-timeline"); if(el){el.scrollTop=el.scrollHeight;}}'
            . 'if(webhook){webhook.innerHTML=data.thread.webhook_log_html||"";}'
            . '}'
            . '})'
            . '.catch(function(){})'
            . '.finally(function(){busy=false;});'
            . '}'
            . 'if(timeline){const first=timeline.querySelector(".commercial-thread-timeline"); if(first){first.scrollTop=first.scrollHeight;}}'
            . 'tick();'
            . 'window.setInterval(tick,4000);'
            . '})();</script>';
    }
}
