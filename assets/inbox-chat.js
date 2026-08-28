/**
 * inbox-chat.js — Operador de chat SuperWasap + Bandeja Comercial.
 * Gestiona sidebar, chat, envío, polling, toggles y vista de agente.
 */
(function(){
    console.log('[inbox-chat] v20260827_1 cargado ✓');
    var api = 'inbox_api.php';
    var selectedLineId = null;
    var selectedThreadId = null;
    var selectedThreadPhone = '';
    var lastMessagesJson = '';
    var lastLinesJson = '';
    var pollingTimer = null;
    var linesData = [];
    var currentView = window.InboxChatInitialView === 'agent' ? 'agent' : 'chat'; // 'chat' | 'agent'
    var fullChatThreadId = null;   // ID del hilo abierto en fullscreen
    var fullChatThreadData = null; // Datos del hilo fullscreen
    var _fullChatLastJson = '';    // dedup del refresco en tiempo real del fullchat
    var _readTimestamps = {};      // { threadId: Date.now() } — marca cuándo se leyó localmente
    var _fullChatRevision = '';
    var _fullChatBefore = null;
    var _fullChatHasMore = false;
    var _optimisticMessages = {}; // client_message_id -> local bubble

    var _inboxRevision = ''; // última revisión conocida del estado local (long-poll)

    // Un único coordinador encadena el tiempo real: un long-poll de estado
    // (?action=poll&since=<rev>) que, cuando detecta cambios (d.changed),
    // refresca líneas y el hilo abierto. No hay intervalos paralelos: la
    // cadencia la marca el propio poll (~300 ms tras un cambio, ~400 ms sin
    // cambios). Ante errores se aplica un backoff acotado (1s, 2s, 4s… máx
    // 30s) que se resetea con cada éxito. En segundo plano (document.hidden)
    // el bucle se pausa y se reanuda vía visibilitychange.
    var pollCoordinator = (function(){
        var timer = null, inFlight = {}, failures = 0, baseDelay = 1000, maxDelay = 30000;
        var _lastNativeSyncAt = 0; // throttle del sync de respuestas nativas (~20s)
        function delay() { return Math.min(maxDelay, baseDelay * Math.pow(2, failures)); }
        function schedule(wait) { if (timer) clearTimeout(timer); timer = setTimeout(realtimeLoop, wait); }
        function request(name, fn) {
            if (inFlight[name]) return Promise.resolve(null);
            inFlight[name] = true;
            return fn().then(function(value){ failures = 0; return value; }, function(error){ failures = Math.min(5, failures + 1); throw error; })
                .finally(function(){ inFlight[name] = false; });
        }
        function realtimeLoop() {
            if (document.hidden) return; // al volver a la pestaña, visibilitychange reanuda
            request('poll', function(){
                var url = api + '?action=poll&since=' + encodeURIComponent(_inboxRevision) + '&timeout=25&_=' + Date.now();
                return fetch(url, {credentials:'same-origin'})
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        if (!d || !d.ok) throw new Error('poll');
                        _inboxRevision = d.revision || _inboxRevision;
                        return d;
                    });
            }).then(function(d){
                if (!d) { schedule(400); return; } // single-flight en curso: no encolar
                if (!document.hidden) {
                    if (d.changed) {
                        if (currentView === 'chat') request('lines', function(){ return loadLines(_inboxRevision); }).catch(function(){});
                        if (fullChatThreadId) request('thread', refreshFullChat).catch(function(){});
                    }
                    schedule(d.changed ? 300 : 400);
                }
                maybeSyncNative();
            }, function(){
                // Error de red/parseo: reintentar siempre con backoff acotado.
                if (!document.hidden) schedule(delay());
            });
        }
        // Sync opcional de respuestas nativas del hilo abierto (~cada 20 s).
        // Es un disparador de estado: la respuesta se ignora. Si detecta
        // respuestas nativas, la revisión cambia y el siguiente poll refresca
        // el hilo abierto vía refreshFullChat.
        function maybeSyncNative() {
            if (!fullChatThreadId || document.hidden) return;
            var now = Date.now();
            if (now - _lastNativeSyncAt < 20000) return;
            if (inFlight['nativeSync']) return;
            _lastNativeSyncAt = now;
            inFlight['nativeSync'] = true;
            var url = api + '?action=thread&id=' + encodeURIComponent(fullChatThreadId) + '&sync_native=1&_=' + Date.now();
            if (_fullChatRevision) url += '&revision=' + encodeURIComponent(_fullChatRevision);
            fetch(url, {credentials:'same-origin'})
                .then(function(r){ return r.json(); })
                .catch(function(){ /* silencioso */ })
                .finally(function(){ inFlight['nativeSync'] = false; });
        }
        function runNow() {
            if (document.hidden) return;
            if (inFlight['poll']) return; // ya hay un poll esperando en el servidor
            realtimeLoop();
        }
        document.addEventListener('visibilitychange', function(){ if (!document.hidden) { failures = 0; runNow(); } });
        return { start: function(){ runNow(); }, poke: function(){ runNow(); }, request: request, resetNativeSync: function(){ _lastNativeSyncAt = 0; } };
    })();

    // ── Sidebar: líneas agrupadas + lazy-load por línea ──
    var _lineThreads = {};         // { lineId: { threads:[], hasMore, nextTs, nextId, loading, loaded, collapsed } }
    var _searchMode = false;
    var _searchDebounce = null;

    // ── Init ──
    function init() {
        if (currentView === 'chat') {
            loadLines();
            pollCoordinator.start();
        } else if (currentView === 'agent') {
            loadAgentPanel();
            pollCoordinator.start();
        }
    }

    // ── View switching ──
    function switchView(view) {
        currentView = view;
        var chatShell = document.getElementById('inboxChatShell');
        var agentShell = document.getElementById('inboxAgentShell');
        var panelBtn = document.getElementById('inboxPanelBtn');
        var toggles = document.getElementById('inboxToggles');

        if (view === 'agent') {
            if (chatShell) chatShell.style.display = 'none';
            if (agentShell) agentShell.style.display = 'flex';
            if (toggles) toggles.style.display = 'flex';
            if (panelBtn) {
                panelBtn.innerHTML = '💬 Chat';
                panelBtn.title = 'Ir al Chat de WhatsApp';
                panelBtn.onclick = function(){ InboxChat.switchView('chat'); };
            }
            loadAgentPanel();
            updatePanelBadge(_lastBadgeCount);
        } else {
            if (chatShell) chatShell.style.display = 'flex';
            if (agentShell) agentShell.style.display = 'none';
            if (toggles) toggles.style.display = 'flex';
            if (panelBtn) {
                panelBtn.innerHTML = '📊 Panel';
                panelBtn.title = 'Ir al Panel de Agente';
                panelBtn.onclick = function(){ InboxChat.switchView('agent'); };
            }
            pollCoordinator.poke();
        }

        // Update URL without reload
        try { history.replaceState(null, '', '?view=' + view); } catch(e) {}
    }

    function loadAgentPanel() {
        var view = document.getElementById('inboxAgentView');
        if (!view || view.getAttribute('data-loaded') === '1') { initAgentTable(); return Promise.resolve(); }
        return fetch(api + '?action=agent&_=' + Date.now(), {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d.ok) throw new Error('agent');
                view.innerHTML = d.html || '<div class="agent-empty">Sin datos</div>';
                view.setAttribute('data-loaded', '1');
                initAgentTable();
            })
            .catch(function(){ view.innerHTML = '<div class="inbox-empty">No se pudo cargar el panel</div>'; });
    }

    // ── Agent Cards — init event listeners ──
    // Filtro activo elegido por el usuario (persiste entre re-renders del panel)
    var _agentActiveFilter = null;

    function initAgentTable() {
        var grid = document.querySelector('.agent-cards-grid');
        if (!grid) return;

        // Attach card listeners
        attachCardListeners(grid);

        // Fullscreen button
        var fsBtn = document.getElementById('agentFullscreenBtn');
        if (fsBtn && !fsBtn._inboxBound) {
            fsBtn._inboxBound = true;
            fsBtn.addEventListener('click', function(){
                document.body.classList.add('inbox-agent-fs');
                if (!document.querySelector('.inbox-agent-fs-close')) {
                    var closeBtn = document.createElement('button');
                    closeBtn.className = 'inbox-agent-fs-close';
                    closeBtn.textContent = '✕ Cerrar';
                    closeBtn.onclick = function(){
                        document.body.classList.remove('inbox-agent-fs');
                        closeBtn.remove();
                    };
                    document.body.appendChild(closeBtn);
                }
            });
        }

        // Filter buttons
        var panel = document.querySelector('.inbox-agent-view .agent-table-panel');
        if (!panel) return;

        // Restore last filter chosen in this sesión (si no, se usa el del servidor: pendientes)
        if (_agentActiveFilter) {
            panel.querySelectorAll('.agent-filter-btn').forEach(function(b){
                b.classList.toggle('is-active', b.getAttribute('data-filter') === _agentActiveFilter);
            });
        }

        panel.querySelectorAll('.agent-filter-btn').forEach(function(btn){
            if (btn._inboxBound) return;
            btn._inboxBound = true;
            btn.addEventListener('click', function(e){
                e.preventDefault();
                var filter = btn.getAttribute('data-filter');
                if (!filter) return;
                _agentActiveFilter = filter;

                // Update active state
                panel.querySelectorAll('.agent-filter-btn').forEach(function(b){ b.classList.remove('is-active'); });
                btn.classList.add('is-active');

                applyAgentFilter(panel);
            });
        });

        // Aplicar el filtro activo al entrar (por defecto: solo pendientes)
        applyAgentFilter(panel);
    }

    // Filtra las tarjetas del panel según el botón .is-active y actualiza contadores
    function applyAgentFilter(panel) {
        if (!panel) return;
        var active = panel.querySelector('.agent-filter-btn.is-active');
        var filter = active ? active.getAttribute('data-filter') : (_agentActiveFilter || 'pending');
        if (!filter) return;

        var cards = panel.querySelectorAll('.agent-card, .agent-empty');
        cards.forEach(function(card){
            if (card.classList.contains('agent-empty')) {
                card.style.display = (filter === 'all' || filter === 'pending') ? '' : 'none';
                return;
            }
            var status = card.getAttribute('data-agent-status');
            if (filter === 'all') { card.style.display = ''; }
            else if (filter === 'pending') { card.style.display = status === 'pending' ? '' : 'none'; }
            else if (filter === 'done') { card.style.display = status === 'done' ? '' : 'none'; }
            else if (filter === 'discarded') { card.style.display = status === 'discarded' ? '' : 'none'; }
        });

        updateCardBadges(panel);
    }

    function attachCardListeners(grid) {
        // Reply button (Contestar)
        grid.querySelectorAll('.agent-card-btn-reply').forEach(function(btn){
            if (btn._inboxBound) return;
            btn._inboxBound = true;
            btn.addEventListener('click', function(e){
                e.preventDefault();
                var threadId = btn.getAttribute('data-thread-id');
                if (!threadId || btn.classList.contains('is-sent')) return;

                var phone = btn.closest('.agent-card')?.getAttribute('data-phone') || '';
                // Open full chat
                openFullChat(threadId, phone);
            });
        });

        // View button (Ver conversación)
        grid.querySelectorAll('.agent-card-btn-view').forEach(function(btn){
            if (btn._inboxBound) return;
            btn._inboxBound = true;
            btn.addEventListener('click', function(e){
                e.preventDefault();
                var threadId = btn.getAttribute('data-thread-id');
                if (!threadId) return;
                openFullChat(threadId);
            });
        });

        // Convertir en lead button
        grid.querySelectorAll('.agent-card-btn-convert-lead').forEach(function(btn){
            if (btn._inboxBound) return;
            btn._inboxBound = true;
            btn.addEventListener('click', function(e){
                e.preventDefault();
                showToast('Próximamente disponible', 'info');
            });
        });

        // Attend button (Atender)
        grid.querySelectorAll('.agent-card-btn-attend').forEach(function(btn){
            if (btn._inboxBound) return;
            btn._inboxBound = true;
            btn.addEventListener('click', function(e){
                e.preventDefault();
                var threadId = btn.getAttribute('data-thread-id');
                if (!threadId) return;

                btn.textContent = '⏳';
                btn.disabled = true;

                fetch(api + '?action=attend&_=' + Date.now(), {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: 'action=attend&thread_id=' + encodeURIComponent(threadId),
                    credentials: 'same-origin'
                })
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if (d && d.ok) {
                        var card = btn.closest('.agent-card');
                        if (card) {
                            card.setAttribute('data-agent-status', 'done');
                            card.classList.remove('agent-card-hot','agent-card-warm','agent-card-pending');
                            card.classList.add('agent-card-done');
                            var statusEl = card.querySelector('.agent-card-status');
                            if (statusEl) {
                                statusEl.className = 'agent-card-status done';
                                statusEl.innerHTML = '🟢 Atendido';
                            }
                            // Cambiar botón Atender + Contestar → Ver conversación
                            btn.className = 'agent-card-btn agent-card-btn-view';
                            btn.innerHTML = '👁 Ver conversación';
                            btn.disabled = false;
                            btn._inboxBound = true; // para que attachCardListeners no re-bindee
                            btn.addEventListener('click', function(e2){
                                e2.preventDefault();
                                openFullChat(threadId);
                            });
                            // También cambiar el botón Contestar → Convertir en lead
                            var replyBtn = card.querySelector('.agent-card-btn-reply');
                            if (replyBtn) {
                                replyBtn.className = 'agent-card-btn agent-card-btn-convert-lead';
                                replyBtn.innerHTML = '🎯 Convertir en lead';
                                replyBtn._inboxBound = false;
                                replyBtn.addEventListener('click', function(e3){
                                    e3.preventDefault();
                                    showToast('Próximamente disponible', 'info');
                                });
                            }
                            applyAgentFilter(btn.closest('.agent-table-panel'));
                        }
                    } else {
                        btn.textContent = '✅ Atender';
                        btn.disabled = false;
                    }
                })
                .catch(function(){
                    btn.textContent = '✅ Atender';
                    btn.disabled = false;
                });
            });
        });

        // Discard button
        grid.querySelectorAll('.agent-card-btn-discard').forEach(function(btn){
            if (btn._inboxBound) return;
            btn._inboxBound = true;
            btn.addEventListener('click', function(e){
                e.preventDefault();
                var threadId = btn.getAttribute('data-thread-id');
                if (!threadId) return;

                if (!btn.classList.contains('is-confirming')) {
                    btn.classList.add('is-confirming');
                    btn.textContent = '¿Seguro?';
                    setTimeout(function(){
                        btn.classList.remove('is-confirming');
                        btn.textContent = '✕ Descartar';
                    }, 3000);
                    return;
                }

                btn.textContent = '⏳';
                btn.disabled = true;

                fetch(api + '?action=discard&_=' + Date.now(), {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: 'action=discard&thread_id=' + encodeURIComponent(threadId),
                    credentials: 'same-origin'
                })
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if (d && d.ok) {
                        var card = btn.closest('.agent-card');
                        if (card) {
                            card.setAttribute('data-agent-status', 'discarded');
                            card.classList.remove('agent-card-hot','agent-card-warm','agent-card-pending','agent-card-done');
                            card.classList.add('agent-card-discarded');
                            var statusEl = card.querySelector('.agent-card-status');
                            if (statusEl) {
                                statusEl.className = 'agent-card-status discarded';
                                statusEl.innerHTML = '⚫ Descartado';
                            }
                            // Ocultar botón descartar y cambiar contestar + atender por ver
                            var replyBtn = card.querySelector('.agent-card-btn-reply');
                            if (replyBtn) {
                                replyBtn.className = 'agent-card-btn agent-card-btn-view';
                                replyBtn.innerHTML = '👁 Ver conversación';
                                replyBtn.setAttribute('data-thread-id', threadId);
                                replyBtn._inboxBound = false;
                                replyBtn.classList.remove('is-sent');
                                replyBtn.disabled = false;
                            }
                            var attendBtn = card.querySelector('.agent-card-btn-attend');
                            if (attendBtn) {
                                attendBtn.className = 'agent-card-btn agent-card-btn-view';
                                attendBtn.innerHTML = '👁 Ver conversación';
                                attendBtn.setAttribute('data-thread-id', threadId);
                                attendBtn._inboxBound = false;
                                attendBtn.disabled = false;
                            }
                            btn.remove();
                            attachCardListeners(grid);
                            applyAgentFilter(grid.closest('.agent-table-panel'));
                        }
                        showToast('Descartado ✓', 'ok');
                    } else {
                        btn.textContent = '✕ Descartar';
                        btn.disabled = false;
                        btn.classList.remove('is-confirming');
                        showToast('Error al descartar', 'error');
                    }
                })
                .catch(function(){
                    btn.textContent = '✕ Descartar';
                    btn.disabled = false;
                    btn.classList.remove('is-confirming');
                    showToast('Error de red', 'error');
                });
            });
        });

        // Copy phone button
        grid.querySelectorAll('.agent-card-copy').forEach(function(btn){
            if (btn._inboxBound) return;
            btn._inboxBound = true;
            btn.addEventListener('click', function(e){
                e.preventDefault();
                var phone = btn.getAttribute('data-phone') || '';
                if (!phone) return;
                copyToClipboard(phone, btn);
            });
        });
    }

    function copyToClipboard(text, el) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function(){
                el.classList.add('is-copied');
                el.textContent = '✓';
                setTimeout(function(){ el.classList.remove('is-copied'); el.textContent = '📋'; }, 1500);
            });
        } else {
            var ta = document.createElement('textarea');
            ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            document.execCommand('copy'); document.body.removeChild(ta);
            el.classList.add('is-copied'); el.textContent = '✓';
            setTimeout(function(){ el.classList.remove('is-copied'); el.textContent = '📋'; }, 1500);
        }
    }

    function updateCardBadges(panel) {
        var all = 0, pending = 0, done = 0, discarded = 0;
        panel.querySelectorAll('.agent-card').forEach(function(card){
            all++;
            var s = card.getAttribute('data-agent-status');
            if (s === 'pending') pending++;
            else if (s === 'done') done++;
            else if (s === 'discarded') discarded++;
        });
        var badgeAll = panel.querySelector('.agent-filter-btn[data-filter="all"] .badge');
        var badgePending = panel.querySelector('.agent-filter-btn[data-filter="pending"] .badge');
        var badgeDone = panel.querySelector('.agent-filter-btn[data-filter="done"] .badge');
        var badgeDiscarded = panel.querySelector('.agent-filter-btn[data-filter="discarded"] .badge');
        if (badgeAll) badgeAll.textContent = all;
        if (badgePending) badgePending.textContent = pending;
        if (badgeDone) badgeDone.textContent = done;
        if (badgeDiscarded) badgeDiscarded.textContent = discarded;

        // Update big counter too
        var bigCount = document.getElementById('agentPendingCount');
        if (bigCount) bigCount.textContent = pending;

        // Update panel button unread badge
        updatePanelBadge(pending);
    }

    // ── Panel button unread badge ──
    var _lastBadgeCount = 0;

    function updatePanelBadge(count) {
        _lastBadgeCount = count;
        var btn = document.getElementById('inboxPanelBtn');
        if (!btn) return;

        var dot = btn.querySelector('.panel-unread-dot');
        if (count > 0) {
            if (!dot) {
                dot = document.createElement('span');
                dot.className = 'panel-unread-dot has-unread';
                btn.appendChild(dot);
            }
            dot.classList.add('has-unread');
        } else if (dot) {
            dot.classList.remove('has-unread');
        }
    }

    // Legacy helpers retained for callers; the coordinator uses `lines` only.
    var _badgePollTimer = null;

    function startBadgePolling() {
        stopBadgePolling();
        _badgePollTimer = setInterval(function(){
            if (document.hidden) return;
            fetch(api + '?action=pending_count&_=' + Date.now(), {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.ok && typeof d.pending === 'number') {
                    _lastBadgeCount = d.pending;
                    updatePanelBadge(d.pending);
                }
            }).catch(function(){});
        }, 5000);
    }

    function stopBadgePolling() {
        if (_badgePollTimer) { clearInterval(_badgePollTimer); _badgePollTimer = null; }
    }

    function showToast(msg, type) {
        var el = document.createElement('div');
        el.className = 'agent-toast ' + (type || 'ok') + ' is-visible';
        el.textContent = msg;
        document.body.appendChild(el);
        setTimeout(function(){ el.classList.remove('is-visible'); }, 2000);
        setTimeout(function(){ el.remove(); }, 2400);
    }

    // ── Load lines (resumen de líneas agrupadas) ──
    // `since` = revisión conocida: si el servidor responde unchanged, el estado
    // local ya lo tenemos y se omite el re-render completo (ahorro de parseo).
    function loadLines(since) {
        if (_searchMode) return Promise.resolve(); // en modo búsqueda no pisamos las líneas
        var url = api + '?action=lines&_=' + Date.now();
        if (since) url += '&since=' + encodeURIComponent(since);
        return fetch(url, {credentials:'same-origin'})
        .then(function(r){return r.json()})
        .then(function(d){
            if (!d.ok) throw new Error('lines');
            if (d.unchanged) return d; // misma revisión: nada que renderizar
            var json = JSON.stringify(d.lines || []);
            if (json === lastLinesJson) return d;
            lastLinesJson = json;
            linesData = d.lines || [];
            _searchMode = false;
            renderLines();
            if (d.settings) updateGlobalToggles(d.settings);
            if (typeof d.pending === 'number') updatePanelBadge(d.pending);
            return d;
        });
    }

    // ── Estado por línea ──
    function ensureLineState(lineId) {
        if (!_lineThreads[lineId]) {
            _lineThreads[lineId] = {
                threads: [], hasMore: true, nextTs: null, nextId: null,
                loading: false, loaded: false, collapsed: true
            };
        }
        return _lineThreads[lineId];
    }

    // ── Sidebar rendering (grupos de línea) ──
    function renderLines() {
        var list = document.getElementById('inboxLinesList');
        if (!list) return;
        if (!linesData.length) {
            list.innerHTML = '<div class="inbox-empty">No hay líneas con conversaciones</div>';
            return;
        }
        var now = Date.now();
        var h = '';
        for (var i = 0; i < linesData.length; i++) {
            var line = linesData[i];
            var lid = line.line_id || '';
            var lname = line.line_name || 'Línea';
            var lphone = line.line_phone || '';
            var tcount = line.thread_count || 0;
            var lineLastTs = line.line_last_ts || '';
            var lineUnread = line.line_total_unread || 0;
            var lineTime = formatLineTime(lineLastTs);

            var st = _lineThreads[lid] || { collapsed: true };

            // Race-condition: resta hilos leídos localmente (dentro de 2 min)
            if (st.threads) {
                for (var j = 0; j < st.threads.length; j++) {
                    var tt = st.threads[j];
                    if (tt.unread && _readTimestamps[tt.id] && (now - _readTimestamps[tt.id]) < 120000) {
                        tt.unread = false;
                        lineUnread = Math.max(0, lineUnread - 1);
                    }
                }
            }

            // Punto verde solo si hay no leídas; gris si no.
            var dotClass = lineUnread > 0 ? ' line-dot line-dot--unread' : ' line-dot';

            var markBtn = '';
            if (lineUnread > 0) {
                markBtn = '<button type="button" class="inbox-line-mark-read" title="Marcar todas como leídas" '
                    + 'onclick="event.stopPropagation();InboxChat.markAllRead(\'' + escAttr(lid) + '\')">✓</button>';
            }

            h += '<div class="inbox-line-group' + (st.collapsed ? ' collapsed' : '') + '" data-line-id="' + escAttr(lid) + '">';
            h += '<div class="inbox-line-header" onclick="InboxChat.toggleLine(\'' + escAttr(lid) + '\')">';
            h += '<span class="' + dotClass + '"></span>';
            h += '<span class="inbox-line-name">' + esc(lname) + '</span>';
            if (lineTime) h += '<span class="inbox-line-time">' + esc(lineTime) + '</span>';
            h += '<span class="inbox-line-meta">' + esc(lphone) + ' · ' + tcount + '</span>';
            if (lineUnread > 0) h += '<span class="inbox-line-badge-unread">' + lineUnread + '</span>';
            h += markBtn;
            h += '<span class="inbox-line-arrow">▼</span>';
            h += '</div>';
            h += '<div class="inbox-thread-list">' + renderThreadListHtml(lid) + '</div>';
            h += '</div>';
        }
        list.innerHTML = h;
    }

    // ── HTML de la lista de hilos de una línea (desde el estado cacheado) ──
    function renderThreadListHtml(lineId) {
        var st = _lineThreads[lineId];
        if (!st || !st.loaded) return '';
        if (!st.threads.length) {
            return '<div class="inbox-loading">Sin conversaciones</div>';
        }
        var h = '';
        for (var j = 0; j < st.threads.length; j++) {
            h += renderThreadItemHtml(st.threads[j]);
        }
        if (st.hasMore && !_searchMode) {
            h += '<div class="inbox-load-more" data-line-id="' + escAttr(lineId) + '">Cargando más…</div>';
        }
        return h;
    }

    // ── HTML de un único hilo (reutilizado en lista de línea y en búsqueda) ──
    function renderThreadItemHtml(t) {
        var now = Date.now();
        var tid = t.id || '';
        var tname = t.display_name || t.phone || '?';
        var tmsg = t.last_message || '';
        var tstage = t.stage_label || t.stage || '';
        var tpaused = t.paused || t.human_taken;
        var tprocess = t.process_slug || '';
        var tline = t.line_name || '';
        var tunread = t.unread;
        var ttime = formatRelativeTime(t.last_ts || '');

        // Race-condition: resta hilos leídos localmente
        if (tunread && _readTimestamps[tid] && (now - _readTimestamps[tid]) < 120000) {
            tunread = false;
        }

        var active = (selectedThreadId === tid) ? ' active' : '';
        var pausedClass = tpaused ? ' paused' : '';
        var unreadClass = tunread ? ' is-unread' : '';

        var h = '<div class="inbox-thread-item' + active + pausedClass + unreadClass + '" data-thread-id="' + escAttr(tid) + '" onclick="InboxChat.selectThread(\'' + escAttr(tid) + '\',\'' + escAttr(tname) + '\')">';
        h += '<div class="inbox-thread-item-top">';
        if (tunread) h += '<span class="inbox-thread-dot"></span>';
        h += '<span class="inbox-thread-name">' + esc(tname) + '</span>';
        if (ttime) h += '<span class="inbox-thread-time">' + esc(ttime) + '</span>';
        h += '</div>';
        h += '<div class="inbox-thread-item-sub">';
        h += '<span class="inbox-thread-stage ' + escAttr(tstage.toLowerCase()) + '">' + esc(tstage) + '</span>';
        if (tline) h += '<span class="inbox-thread-line">' + esc(tline) + '</span>';
        h += '</div>';
        if (tmsg) {
            h += '<div class="inbox-thread-msg">';
            if (tpaused) h += '<span class="paused-icon" title="Bot parado en esta conversación">⏸</span>';
            h += esc(tmsg);
            h += '</div>';
        }
        h += '<div class="inbox-thread-meta">';
        if (tprocess) h += '<span>' + esc(tprocess) + '</span>';
        h += '<span>R:' + (t.replies_count||0) + ' E:' + (t.sent_count||0) + '</span>';
        h += '</div>';
        h += '</div>';
        return h;
    }

    // ── Re-render SOLO la lista de hilos de una línea (sin tocar el resto) ──
    function renderThreadList(lineId) {
        var group = document.querySelector('.inbox-line-group[data-line-id="' + lineId + '"]');
        if (!group) return;
        var listEl = group.querySelector('.inbox-thread-list');
        if (listEl) listEl.innerHTML = renderThreadListHtml(lineId);
    }

    // ── Render lista plana (resultados de búsqueda) ──
    function renderSearchResults(threads) {
        var list = document.getElementById('inboxLinesList');
        if (!list) return;
        if (!threads.length) {
            list.innerHTML = '<div class="inbox-empty">Sin resultados</div>';
            return;
        }
        var h = '';
        for (var i = 0; i < threads.length; i++) {
            h += renderThreadItemHtml(threads[i]);
        }
        list.innerHTML = h;
        if (list.scrollTop) list.scrollTop = 0;
    }

    // ── Carga (perezosa) de hilos de una línea ──
    function loadLineThreads(lineId, reset) {
        var st = ensureLineState(lineId);
        if (st.loading) return;
        if (!reset && (!st.hasMore || !st.nextTs)) return;
        st.loading = true;

        var url = api + '?action=line_threads&line_id=' + encodeURIComponent(lineId) + '&limit=50&_=' + Date.now();
        if (!reset && st.nextTs) {
            url += '&before_ts=' + encodeURIComponent(st.nextTs) + '&before_id=' + encodeURIComponent(st.nextId || '');
        }

        fetch(url, {credentials:'same-origin'})
        .then(function(r){return r.json()})
        .then(function(d){
            if (!d.ok) return;
            var newItems = d.threads || [];
            if (reset) {
                st.threads = newItems;
            } else {
                var seen = {};
                for (var i = 0; i < st.threads.length; i++) seen[st.threads[i].id] = true;
                newItems = newItems.filter(function(t){ return !seen[t.id]; });
                st.threads = st.threads.concat(newItems);
            }
            st.hasMore = !!d.has_more;
            st.nextTs = d.next_ts || null;
            st.nextId = d.next_id || null;
            st.loaded = true;
            renderThreadList(lineId);
        })
        .catch(function(){})
        .finally(function(){ st.loading = false; });
    }

    // ── Scroll infinito: carga más de la última línea expandida con más por cargar ──
    function loadMoreExpandedLines() {
        for (var i = linesData.length - 1; i >= 0; i--) {
            var lid = linesData[i].line_id;
            var st = _lineThreads[lid];
            if (!st) continue;
            if (st.collapsed || !st.loaded) continue;
            if (st.hasMore && st.nextTs) {
                loadLineThreads(lid, false);
                return;
            }
        }
    }

    // ── Select thread ──
    function selectThread(threadId, phone) {
        selectedThreadId = threadId;
        selectedThreadPhone = phone;
        var items = document.querySelectorAll('.inbox-thread-item');
        for (var i = 0; i < items.length; i++) {
            items[i].classList.toggle('active', items[i].getAttribute('data-thread-id') === threadId);
        }
        // Marcar como leído al abrir
        markRead(threadId);
        // Abrir siempre en overlay fullscreen — no usar panel inline
        openFullChat(threadId);
    }

    // ── Load messages ──
    function loadMessages(isInitial) {
        if (!selectedThreadId) return;
        fetch(api + '?action=thread&id=' + encodeURIComponent(selectedThreadId) + '&_=' + Date.now(), {credentials:'same-origin'})
        .then(function(r){return r.json()})
        .then(function(d){
            if (!d.ok) return;
            var json = JSON.stringify(d.messages || []);
            if (!isInitial && json === lastMessagesJson) return;
            lastMessagesJson = json;
            var subEl = document.getElementById('inboxChatSub');
            var pauseBtn = document.getElementById('inboxPauseBtn');
            var stageBadge = document.getElementById('inboxStageBadge');
            if (d.thread) {
                var t = d.thread;
                var parts = [];
                if (t.process_slug) parts.push(t.process_slug);
                if (t.line_name) parts.push('via ' + t.line_name);
                if (t.line_phone) parts.push(t.line_phone);
                if (subEl) subEl.textContent = parts.join(' · ') || 'Conversación';
                if (stageBadge && t.stage_label) {
                    stageBadge.textContent = t.stage_label;
                    stageBadge.className = 'inbox-stage-badge ' + (t.stage||'').toLowerCase();
                }
                if (pauseBtn) {
                    if (t.paused || t.human_taken) {
                        pauseBtn.className = 'inbox-btn-toggle is-paused';
                        pauseBtn.innerHTML = '🔇 Pausada';
                    } else {
                        pauseBtn.className = 'inbox-btn-toggle is-active';
                        pauseBtn.innerHTML = '🤖 Auto';
                    }
                }
                var panelBtn = document.getElementById('inboxPanelAddBtn');
                if (panelBtn && threadId) {
                    panelBtn.style.display = '';
                    panelBtn.onclick = function(){ InboxChat.panelAddShow(threadId); };
                }
            }
            renderMessages(d.messages || []);
            updateTypingIndicator(document.getElementById('inboxMessages'), d.thread);
        }).catch(function(){});
    }

    // Re-añade las burbujas optimistas vivas (no reconciliadas) de un hilo
    // tras un re-render completo del contenedor.
    function appendPendingOptimistic(area, threadId, containerId) {
        if (!area) return;
        Object.keys(_optimisticMessages).forEach(function(clientId){
            var local = _optimisticMessages[clientId];
            if (!local || local.threadId !== threadId) return;
            if (local.containerId && local.containerId !== containerId) return;
            area.insertAdjacentHTML('beforeend', optimisticBubbleHtml(clientId, local));
        });
    }

    // ── Render messages (contenedor inline) ──
    function renderMessages(messages) {
        var area = document.getElementById('inboxMessages');
        if (!area) return;
        if (!messages.length) {
            area.innerHTML = '<div class="inbox-chat-placeholder"><div class="inbox-chat-placeholder-icon">💬</div><div>Sin mensajes aún</div></div>';
            // Conservar burbujas optimistas del hilo (p.ej. primer mensaje en vuelo)
            appendPendingOptimistic(area, selectedThreadId, 'inboxMessages');
            return;
        }
        var seen = {};
        var uniq = [];
        for (var i = 0; i < messages.length; i++) {
            var key = (messages[i].ts || '') + '|' + (messages[i].direction || '') + '|' + (messages[i].text || '').substring(0, 30);
            if (!seen[key]) { seen[key] = true; uniq.push(messages[i]); }
        }
        messages = uniq;
        var wasAtBottom = (area.scrollHeight - area.scrollTop - area.clientHeight) <= 80;
        var lastDate = '';
        var lastAuthor = '';
        var h = '';
        for (var i = 0; i < messages.length; i++) {
            var m = messages[i];
            var d = formatDate(m.ts || '');
            if (d !== lastDate) {
                h += '<div class="inbox-msg-date-sep"><span>' + esc(d) + '</span></div>';
                lastDate = d;
            }
            var dir = m.direction === 'out' ? 'out' : 'in';
            var time = formatTime(m.ts || '');
            var botTag = m.is_bot ? '<span class="msg-bot-tag">🤖 bot</span>' : '';
            var cont = (lastAuthor === dir) ? ' cont' : '';
            lastAuthor = dir;
            h += '<div class="inbox-msg-bubble ' + dir + cont + '" data-message-key="' + escAttr(messageKey(m)) + '">';
            if (botTag) h += botTag;
            h += '<div class="msg-text">' + renderInboxMedia(m, dir) + '</div>';
            h += '<div class="msg-time">' + esc(time);
            if (dir === 'out') h += '<span class="msg-checks">✓✓</span>';
            h += '</div>';
            h += '</div>';
        }
        // Reconciliación: retirar burbujas optimistas ya persistidas; las que
        // siguen en vuelo se re-añaden tras el render.
        reconcilePersistedOutbound(messages, selectedThreadId, area);
        area.innerHTML = h;
        appendPendingOptimistic(area, selectedThreadId, 'inboxMessages');
        if (wasAtBottom) area.scrollTop = area.scrollHeight;
    }

    // ── Typing indicator (burbuja 3 puntos) ──
    // Se muestra cuando el bot tiene una acción programada en el futuro próximo
    // (next_bot_action_at) → está "escribiendo"/pensando una respuesta.
    function updateTypingIndicator(area, thread) {
        if (!area) return;
        var existing = area.querySelector('.inbox-typing');
        var show = false;
        if (thread && thread.next_bot_action_at && !thread.paused && !thread.human_taken) {
            var ts = String(thread.next_bot_action_at || '');
            var d = new Date(ts.replace(' ','T') + (ts.indexOf('+') === -1 && ts.indexOf('Z') === -1 ? 'Z' : ''));
            if (!isNaN(d.getTime()) && (d.getTime() - Date.now()) < 300000 && (d.getTime() - Date.now()) > -60000) {
                show = true;
            }
        }
        if (show && !existing) {
            var el = document.createElement('div');
            el.className = 'inbox-typing';
            el.innerHTML = '<span></span><span></span><span></span>';
            area.appendChild(el);
            area.scrollTop = area.scrollHeight;
        } else if (!show && existing) {
            existing.remove();
        }
    }
    function sendMessage() {
        if (!selectedThreadId) return;
        var input = document.getElementById('inboxChatInput');
        var btn = document.getElementById('inboxChatSendBtn');
        if (!input) return;
        var text = (input.value || '').trim();
        if (!text) return;
        if (btn) btn.disabled = true;
        input.value = '';
        input.style.height = 'auto';
        var clientMessageId = newClientMessageId();
        var containerId = 'inboxMessages';
        addOptimisticMessage(selectedThreadId, text, clientMessageId, containerId);

        fetch(api + '?action=send&_=' + Date.now(), {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=send&thread_id=' + encodeURIComponent(selectedThreadId) + '&text=' + encodeURIComponent(text) + '&client_message_id=' + encodeURIComponent(clientMessageId),
            credentials: 'same-origin'
        })
        .then(function(r){return r.json()})
        .then(function(d){
            if (!d.ok || d.status === 'failed') {
                // Fallo de API (incl. 409 conflicto) o envío terminal fallido:
                // la burbuja queda con Reintentar y el texto NO se restaura.
                markOptimisticFailed(clientMessageId, containerId);
            } else {
                if (d.status === 'sent') markOptimisticSent(clientMessageId, containerId);
                else markOptimisticQueued(clientMessageId, containerId);
                pollCoordinator.poke();
            }
        })
        .catch(function(){
            // Error duro de cliente (red/parseo): marcamos fallo y devolvemos
            // el texto al input para que no se pierda.
            markOptimisticFailed(clientMessageId, containerId);
            if (input) { input.value = text; input.style.height = 'auto'; }
        })
        .finally(function(){ if (btn) btn.disabled = false; if (input) input.focus(); });
    }

    // ── Toggle per-conversation pause ──
    function togglePause() {
        if (!selectedThreadId) return;
        var pauseBtn = document.getElementById('inboxPauseBtn');
        if (pauseBtn) { pauseBtn.textContent = '⏳'; pauseBtn.disabled = true; }
        fetch(api + '?action=toggle_thread&_=' + Date.now(), {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=toggle_thread&thread_id=' + encodeURIComponent(selectedThreadId),
            credentials: 'same-origin'
        })
        .then(function(r){return r.json()})
        .then(function(){ loadLines(); loadMessages(true); })
        .finally(function(){ if (pauseBtn) pauseBtn.disabled = false; });
    }

    // ── Toggle line collapse (y carga perezosa de sus hilos la primera vez) ──
    function toggleLine(lineId) {
        var st = ensureLineState(lineId);
        st.collapsed = !st.collapsed;
        var group = document.querySelector('.inbox-line-group[data-line-id="' + lineId + '"]');
        if (group) group.classList.toggle('collapsed', st.collapsed);
        if (!st.collapsed && !st.loaded) {
            loadLineThreads(lineId, true);
        }
    }

    // ── Marcar todas las conversaciones de una línea como leídas ──
    function markAllRead(lineId) {
        if (!lineId) return;
        // Optimista: limpiar no-leídas en caché y en el resumen de línea
        var st = _lineThreads[lineId];
        if (st && st.threads) {
            for (var i = 0; i < st.threads.length; i++) {
                st.threads[i].unread = false;
                delete _readTimestamps[st.threads[i].id];
            }
        }
        for (var j = 0; j < linesData.length; j++) {
            if (linesData[j].line_id === lineId) linesData[j].line_total_unread = 0;
        }
        renderLines();

        fetch(api + '?action=mark_all_read&_=' + Date.now(), {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=mark_all_read&line_id=' + encodeURIComponent(lineId),
            credentials: 'same-origin'
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.ok) loadLines();
        })
        .catch(function(){});
    }

    // ── Input handler ──
    function handleInputKey(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
    }
    function autoResize() {
        this.style.height = 'auto'; this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    }

    // ── Update global toggle switches ──
    function updateGlobalToggles(settings) {
        if (settings.replies_enabled !== undefined) {
            var label = document.getElementById('inboxToggleReplies');
            if (label) {
                var cb = label.querySelector('input[type="checkbox"]');
                if (cb) cb.checked = settings.replies_enabled;
                var st = label.querySelector('.inbox-switch__state');
                if (st) st.textContent = settings.replies_enabled ? '📣' : 'OFF';
            }
        }
        if (settings.opener_enabled !== undefined) {
            var label = document.getElementById('inboxToggleOpener');
            if (label) {
                var cb = label.querySelector('input[type="checkbox"]');
                if (cb) cb.checked = settings.opener_enabled;
            }
        }
    }

    // ── Toggle global (Respuestas / Inicio) ──
    // Vive aquí (y no en un <script> inline) para poder usar `api`,
    // `loadLines` y el poke del coordinador de tiempo real.
    function updateSwitchState(type, enabled) {
        if (type !== 'replies') return;
        var el = document.getElementById('inboxToggleReplies');
        var st = el ? el.querySelector('.inbox-switch__state') : null;
        if (st) st.textContent = enabled ? '📣' : 'OFF';
    }
    function toggleGlobal(type, checkbox) {
        if (!checkbox) return;
        var wasChecked = checkbox.checked;
        checkbox.disabled = true;

        fetch(api + '?action=toggle_' + type + '&_=' + Date.now(), {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=toggle_' + type,
            credentials: 'same-origin'
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.ok) {
                checkbox.checked = d.enabled;
                updateSwitchState(type, d.enabled);
                loadLines();
                pollCoordinator.poke(); // refresh inmediato de líneas y badge
            } else {
                checkbox.checked = wasChecked;
            }
        })
        .catch(function(){
            checkbox.checked = wasChecked;
        })
        .finally(function(){ checkbox.disabled = false; });
    }

    // ── Search filter ──
    function filterSidebar() {
        var q = (document.getElementById('inboxSearch')?.value || '').trim();
        if (_searchDebounce) clearTimeout(_searchDebounce);
        _searchDebounce = setTimeout(function(){
            if (q === '') {
                _searchMode = false;
                lastLinesJson = '';
                loadLines();
                return;
            }
            _searchMode = true;
            fetch(api + '?action=lines&limit=200&search=' + encodeURIComponent(q) + '&_=' + Date.now(), {credentials:'same-origin'})
            .then(function(r){return r.json()})
            .then(function(d){
                if (!d.ok) return;
                renderSearchResults(d.threads || []);
            })
            .catch(function(){});
        }, 300);
    }

    // ── Fullscreen chat (desde la tabla de agente) ──
    function openFullChat(threadId) {
        console.log('[inbox-chat] openFullChat(', threadId, ')');
        fullChatThreadId = threadId;
        _fullChatRevision = '';
        _fullChatBefore = null;
        _fullChatHasMore = false;
        // Al abrir un hilo, el próximo tick del coordinador lanza el sync de
        // respuestas nativas de inmediato (throttle ~20s desde esta apertura).
        pollCoordinator.resetNativeSync();
        var overlay = document.getElementById('inboxFullChat');
        var nameEl = document.getElementById('inboxFullChatName');
        var subEl = document.getElementById('inboxFullChatSub');
        var msgEl = document.getElementById('inboxFullChatMessages');
        var input = document.getElementById('inboxFullChatInput');

        if (!overlay) return;

        // Marcar como leído al abrir (incluso desde tabla agente)
        markRead(threadId);

        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        if (nameEl) nameEl.textContent = 'Cargando...';
        if (subEl) subEl.textContent = '';
        if (msgEl) msgEl.innerHTML = '<div class="inbox-chat-placeholder"><div class="inbox-chat-placeholder-icon">💬</div><div>Cargando conversación...</div></div>';
        if (input) { input.value = ''; input.style.height = 'auto'; }

        fetch(api + '?action=thread&id=' + encodeURIComponent(threadId) + '&_=' + Date.now(), {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (!d.ok) {
                if (msgEl) msgEl.innerHTML = '<div class="inbox-chat-placeholder"><div>No se pudo cargar la conversación</div></div>';
                return;
            }
            fullChatThreadData = d;
            if (d.thread) {
                var t = d.thread;
                // Mostrar nombre de agenda si existe, o teléfono
                var agendaEntry = t.agenda_entry;
                if (agendaEntry && agendaEntry.nombre) {
                    if (nameEl) nameEl.innerHTML = '👤 <a href="#" class="inbox-fullchat-agenda-link" onclick="event.preventDefault(); InboxChat.agendaOpenFromFullChat(); return false;" title="Ver en agenda">' + esc(agendaEntry.nombre) + '</a>'
                        + ' <button class="inbox-fullchat-panel-add" onclick="event.stopPropagation(); InboxChat.panelAddShow(&quot;' + escAttr(threadId) + '&quot;);" title="Añadir al panel de agente">📌 Panel</button>';
                } else {
                    if (nameEl) {
                        nameEl.innerHTML = esc(t.phone || 'Conversación')
                            + ' <button class="inbox-fullchat-agenda-add" onclick="event.stopPropagation(); InboxChat.agendaAddFromFullChat();" title="Añadir a agenda">👤</button>'
                            + ' <button class="inbox-fullchat-panel-add" onclick="event.stopPropagation(); InboxChat.panelAddShow(&quot;' + escAttr(threadId) + '&quot;);" title="Añadir al panel de agente">📌 Panel</button>';
                    }
                }
                var parts = [];
                if (t.process_slug) parts.push(t.process_slug);
                if (t.line_name) parts.push('via ' + t.line_name);
                if (subEl) subEl.textContent = parts.join(' · ') || 'Conversación';
                renderFullChatPause(t);
            }
            renderFullChatMessages(d.messages || []);
            updateTypingIndicator(document.getElementById('inboxFullChatMessages'), d.thread);
            _fullChatLastJson = JSON.stringify(d.messages || []);
            _fullChatRevision = d.revision || '';
            _fullChatBefore = d.before || null;
            _fullChatHasMore = !!d.has_more;
        })
        .catch(function(){
            if (msgEl) msgEl.innerHTML = '<div class="inbox-chat-placeholder"><div>Error al cargar</div></div>';
        });
    }

    // Refresca el fullchat desde cualquier vista. Solo anexa novedades, nunca
    // reemplaza el timeline que el operador está leyendo.
    function refreshFullChat() {
        if (!fullChatThreadId) return Promise.resolve();
        var url = api + '?action=thread&id=' + encodeURIComponent(fullChatThreadId) + '&_=' + Date.now();
        if (_fullChatRevision) url += '&revision=' + encodeURIComponent(_fullChatRevision);
        return fetch(url, {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (!d.ok) throw new Error('thread');
            if (d.unchanged) return d;
            var json = JSON.stringify(d.messages || []);
            if (json === _fullChatLastJson) return d;
            _fullChatLastJson = json;
            _fullChatRevision = d.revision || _fullChatRevision;
            fullChatThreadData = d;
            var msgEl = document.getElementById('inboxFullChatMessages');
            var subEl = document.getElementById('inboxFullChatSub');
            if (d.thread) {
                var t = d.thread;
                if (subEl) {
                    var parts = [];
                    if (t.process_slug) parts.push(t.process_slug);
                    if (t.line_name) parts.push('via ' + t.line_name);
                    subEl.textContent = parts.join(' · ') || 'Conversación';
                }
                renderFullChatPause(t);
            }
            appendFullChatChanges(d.messages || []);
            updateTypingIndicator(msgEl, d.thread);
            return d;
        })
        ;
    }

    function closeFullChat() {
        var overlay = document.getElementById('inboxFullChat');
        if (overlay) overlay.style.display = 'none';
        // Refresh agent badges after closing chat
        var panel = document.querySelector('.inbox-agent-view .agent-table-panel');
        if (panel) updateCardBadges(panel);
        fullChatThreadId = null;
        fullChatThreadData = null;
        _fullChatLastJson = '';
        _fullChatRevision = '';
        document.body.style.overflow = '';
        // Resetear el highlight de la sidebar
        selectedThreadId = null;
        var items = document.querySelectorAll('.inbox-thread-item');
        for (var i = 0; i < items.length; i++) {
            items[i].classList.remove('active');
        }
        // Forzar refresco del sidebar para quitar indicadores de no leído
        if (currentView === 'chat') loadLines();
    }

    // ── Pill de pausa del fullscreen chat ──
    // Muestra si el bot está activo ("🤖 Auto") o parado ("⏸ Parado") en esta
    // conversación. Parado = inbox_paused o human_taken (intervención humana
    // desde la app o desde WhatsApp nativo).
    function renderFullChatPause(thread) {
        var btn = document.getElementById('inboxFullChatPauseBtn');
        if (!btn) return;
        var paused = !!(thread && (thread.paused || thread.human_taken));
        var label = document.getElementById('inboxFullChatPauseLabel');
        btn.style.display = '';
        btn.className = 'inbox-conv-pause' + (paused ? ' paused' : '');
        if (label) label.textContent = paused ? '⏸ Parado' : '🤖 Auto';
        btn.title = paused
            ? 'El bot está parado en esta conversación (intervención humana). Pulsa para reactivarlo.'
            : 'Parar las respuestas automáticas del bot en esta conversación';
    }

    function toggleFullChatPause() {
        if (!fullChatThreadId) return;
        var btn = document.getElementById('inboxFullChatPauseBtn');
        if (btn) btn.disabled = true;
        fetch(api + '?action=toggle_thread&_=' + Date.now(), {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=toggle_thread&thread_id=' + encodeURIComponent(fullChatThreadId),
            credentials: 'same-origin'
        })
        .then(function(r){ return r.json(); })
        .then(function(){
            if (currentView === 'chat') loadLines();
            openFullChat(fullChatThreadId); // recarga mensajes + estado de pausa
        })
        .catch(function(){
            showToast('Error al cambiar el estado del bot');
        })
        .finally(function(){ if (btn) btn.disabled = false; });
    }

    // ── markRead — marca un hilo como leído (patrón SuperWasap) ──
    function markRead(threadId) {
        if (!threadId) return;
        var now = Date.now();
        _readTimestamps[threadId] = now;
        // POST al backend
        fetch(api + '?action=mark_read&_=' + now, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=mark_read&thread_id=' + encodeURIComponent(threadId),
            credentials: 'same-origin'
        }).catch(function(e){
            console.warn('[inbox-chat] markRead falló:', e);
        });
    }

    function sendFullChatMessage() {
        if (!fullChatThreadId) return;
        var input = document.getElementById('inboxFullChatInput');
        var btn = document.getElementById('inboxFullChatSendBtn');
        if (!input) return;
        var text = (input.value || '').trim();
        if (!text) return;
        if (btn) btn.disabled = true;
        input.value = '';
        input.style.height = 'auto';
        var clientMessageId = newClientMessageId();
        addOptimisticMessage(fullChatThreadId, text, clientMessageId, 'inboxFullChatMessages');

        fetch(api + '?action=send&_=' + Date.now(), {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=send&thread_id=' + encodeURIComponent(fullChatThreadId) + '&text=' + encodeURIComponent(text) + '&client_message_id=' + encodeURIComponent(clientMessageId),
            credentials: 'same-origin'
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (!d.ok || d.status === 'failed') { markOptimisticFailed(clientMessageId, 'inboxFullChatMessages'); }
            else {
                if (d.status === 'sent') markOptimisticSent(clientMessageId, 'inboxFullChatMessages');
                else markOptimisticQueued(clientMessageId, 'inboxFullChatMessages');
                pollCoordinator.poke();
            }
        })
        .catch(function(){ markOptimisticFailed(clientMessageId, 'inboxFullChatMessages'); })
        .finally(function(){ if (btn) btn.disabled = false; if (input) input.focus(); });
    }

    function handleFullChatKey(e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendFullChatMessage(); }
    }

    function renderFullChatMessages(messages) {
        var area = document.getElementById('inboxFullChatMessages');
        if (!area) return;
        if (!messages.length) {
            area.innerHTML = '<div class="inbox-chat-placeholder"><div class="inbox-chat-placeholder-icon">💬</div><div>Sin mensajes aún</div></div>';
            appendPendingOptimistic(area, fullChatThreadId, 'inboxFullChatMessages');
            return;
        }
        var seen = {};
        var uniq = [];
        for (var i = 0; i < messages.length; i++) {
            var key = (messages[i].ts || '') + '|' + (messages[i].direction || '') + '|' + (messages[i].text || '').substring(0, 30);
            if (!seen[key]) { seen[key] = true; uniq.push(messages[i]); }
        }
        messages = uniq;
        var wasAtBottom = (area.scrollHeight - area.scrollTop - area.clientHeight) <= 80;
        var lastDate = '';
        var lastAuthor = '';
        var h = '';
        for (var i = 0; i < messages.length; i++) {
            var m = messages[i];
            var d = formatDate(m.ts || '');
            if (d !== lastDate) {
                h += '<div class="inbox-msg-date-sep"><span>' + esc(d) + '</span></div>';
                lastDate = d;
            }
            var dir = m.direction === 'out' ? 'out' : 'in';
            var time = formatTime(m.ts || '');
            var botTag = m.is_bot ? '<span class="msg-bot-tag">🤖 bot</span>' : '';
            var cont = (lastAuthor === dir) ? ' cont' : '';
            lastAuthor = dir;
            h += '<div class="inbox-msg-bubble ' + dir + cont + '" data-message-key="' + escAttr(messageKey(m)) + '">';
            if (botTag) h += botTag;
            h += '<div class="msg-text">' + renderInboxMedia(m, dir) + '</div>';
            h += '<div class="msg-time">' + esc(time);
            if (dir === 'out') h += '<span class="msg-checks">✓✓</span>';
            h += '</div>';
            h += '</div>';
        }
        reconcilePersistedOutbound(messages, fullChatThreadId, area);
        area.innerHTML = h;
        appendPendingOptimistic(area, fullChatThreadId, 'inboxFullChatMessages');
        if (wasAtBottom) area.scrollTop = area.scrollHeight;
    }

    function messageKey(m) {
        return [m.ts || '', m.direction || '', m.text || '', m.event || ''].join('|');
    }
    function appendFullChatChanges(messages) {
        var area = document.getElementById('inboxFullChatMessages');
        if (!area) return;
        var wasAtBottom = (area.scrollHeight - area.scrollTop - area.clientHeight) <= 80;
        // Reconciliación: retirar la burbuja optimista cuando el saliente ya
        // está persistido en el refresco incremental.
        reconcilePersistedOutbound(messages, fullChatThreadId, area);
        var known = {};
        area.querySelectorAll('[data-message-key]').forEach(function(node){ known[node.getAttribute('data-message-key')] = true; });
        messages.forEach(function(m){
            var key = messageKey(m);
            if (known[key]) return;
            var dir = m.direction === 'out' ? 'out' : 'in';
            area.insertAdjacentHTML('beforeend', '<div class="inbox-msg-bubble ' + dir + '" data-message-key="' + escAttr(key) + '"><div class="msg-text">' + renderInboxMedia(m, dir) + '</div><div class="msg-time">' + esc(formatTime(m.ts || '')) + (dir === 'out' ? '<span class="msg-checks">✓✓</span>' : '') + '</div></div>');
            known[key] = true;
        });
        if (wasAtBottom) area.scrollTop = area.scrollHeight;
    }
    function loadFullChatOlder() {
        if (!fullChatThreadId || !_fullChatHasMore || !_fullChatBefore) return;
        var area = document.getElementById('inboxFullChatMessages');
        if (!area || area.getAttribute('data-loading-history') === '1') return;
        area.setAttribute('data-loading-history', '1');
        var height = area.scrollHeight, top = area.scrollTop;
        fetch(api + '?action=thread&id=' + encodeURIComponent(fullChatThreadId) + '&before=' + encodeURIComponent(_fullChatBefore) + '&_=' + Date.now(), {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d.ok) throw new Error('history');
                var fragment = document.createDocumentFragment();
                (d.messages || []).forEach(function(m){
                    var box = document.createElement('div');
                    var dir = m.direction === 'out' ? 'out' : 'in';
                    box.className = 'inbox-msg-bubble ' + dir;
                    box.setAttribute('data-message-key', messageKey(m));
                    box.innerHTML = '<div class="msg-text">' + renderInboxMedia(m, dir) + '</div><div class="msg-time">' + esc(formatTime(m.ts || '')) + '</div>';
                    fragment.appendChild(box);
                });
                area.insertBefore(fragment, area.firstChild);
                area.scrollTop = top + (area.scrollHeight - height);
                _fullChatBefore = d.before || null;
                _fullChatHasMore = !!d.has_more;
            })
            .catch(function(){ showToast('No se pudieron cargar mensajes anteriores', 'error'); })
            .finally(function(){ area.removeAttribute('data-loading-history'); });
    }
    function newClientMessageId() {
        return 'cm_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
    }
    // HTML de una burbuja optimista (se usa para insertarla y para re-añadirla
    // tras un re-render del contenedor). El estado vive en _optimisticMessages.
    function optimisticBubbleHtml(clientId, local) {
        var h = '<div class="inbox-msg-bubble out inbox-msg-pending' + (local.status === 'failed' ? ' is-failed' : '') + '" data-client-message-id="' + escAttr(clientId) + '" data-message-key="opt-' + escAttr(clientId) + '">';
        h += '<div class="msg-text">' + formatMessageBody(local.text) + '</div>';
        h += '<div class="msg-time"><span class="msg-send-state">' + esc(local.label || (local.status === 'queued' ? 'En cola' : 'Enviando')) + '</span></div>';
        if (local.status === 'failed') {
            h += '<button type="button" class="msg-retry" onclick="InboxChat.retryMessage(\'' + escAttr(clientId) + '\')">Reintentar</button>';
        }
        h += '</div>';
        return h;
    }
    function addOptimisticMessage(threadId, text, clientId, containerId) {
        containerId = containerId || 'inboxFullChatMessages';
        _optimisticMessages[clientId] = {threadId:threadId, text:text, status:'sending', label:'Enviando', containerId:containerId};
        var area = document.getElementById(containerId);
        if (!area) return;
        area.insertAdjacentHTML('beforeend', optimisticBubbleHtml(clientId, _optimisticMessages[clientId]));
        area.scrollTop = area.scrollHeight;
    }
    function markOptimisticQueued(clientId, containerId) { setOptimisticStatus(clientId, 'queued', 'En cola', containerId); }
    function markOptimisticSent(clientId, containerId) { setOptimisticStatus(clientId, 'queued', 'Enviado', containerId); }
    function markOptimisticFailed(clientId, containerId) { setOptimisticStatus(clientId, 'failed', 'Falló, reintentar', containerId); }
    function setOptimisticStatus(clientId, status, label, containerId) {
        var local = _optimisticMessages[clientId];
        if (!local) return;
        local.status = status;
        local.label = label;
        containerId = containerId || local.containerId || 'inboxFullChatMessages';
        var area = document.getElementById(containerId);
        var bubble = area && area.querySelector('[data-client-message-id="' + escAttr(clientId) + '"]');
        if (!bubble) return;
        bubble.classList.toggle('is-failed', status === 'failed');
        var state = bubble.querySelector('.msg-send-state');
        if (state) state.textContent = label;
        if (status === 'failed' && !bubble.querySelector('.msg-retry')) {
            bubble.insertAdjacentHTML('beforeend', '<button type="button" class="msg-retry" onclick="InboxChat.retryMessage(\'' + escAttr(clientId) + '\')">Reintentar</button>');
        }
    }
    // Reconciliación: cuando un refresco trae un saliente ya persistido (mismo
    // texto + mismo hilo), se retira la burbuja optimista correspondiente. Se
    // aplica tanto al inline (renderMessages) como al fullscreen
    // (appendFullChatChanges / renderFullChatMessages).
    function reconcilePersistedOutbound(messages, threadId, area) {
        if (!area) return;
        messages.forEach(function(m){
            if (!m || m.direction !== 'out') return;
            Object.keys(_optimisticMessages).forEach(function(clientId){
                var local = _optimisticMessages[clientId];
                if (!local || local.threadId !== threadId || local.text !== m.text) return;
                var el = area.querySelector('[data-client-message-id="' + escAttr(clientId) + '"]');
                if (el) el.remove();
                delete _optimisticMessages[clientId];
            });
        });
    }
    // Reintenta el MISMO client_message_id con retry=1 (idempotente: el backend
    // reabre el trabajo fallido sin duplicar el envío). Apunta al contenedor
    // donde vive la burbuja fallida.
    function retryMessage(clientId) {
        var local = _optimisticMessages[clientId];
        if (!local || !local.threadId) return;
        var containerId = local.containerId || 'inboxFullChatMessages';
        setOptimisticStatus(clientId, 'sending', 'Enviando', containerId);
        fetch(api + '?action=send&_=' + Date.now(), {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=send&thread_id=' + encodeURIComponent(local.threadId) + '&text=' + encodeURIComponent(local.text) + '&client_message_id=' + encodeURIComponent(clientId) + '&retry=1', credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.ok && d.status !== 'failed') {
                    if (d.status === 'sent') markOptimisticSent(clientId, containerId);
                    else markOptimisticQueued(clientId, containerId);
                    pollCoordinator.poke();
                } else markOptimisticFailed(clientId, containerId);
            })
            .catch(function(){ markOptimisticFailed(clientId, containerId); });
    }

    // ── Helpers ──
    function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
    function escAttr(s) { return String(s).replace(/'/g,'%27').replace(/"/g,'%22').replace(/\\/g,'\\\\'); }
    function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    // ── Formatea el cuerpo del mensaje estilo WhatsApp nativo (patrón bot-casa) ──
    // Detecta URLs de imagen (compartir.site, .jpg/.png/.webp) y las renderiza
    // inline como <img> clicable; el resto de URLs como <a> clicable.
    function isImageUrl(url) {
        return /^https?:\/\//i.test(url)
            && (/(?:^|\/)compartir\.site\//i.test(url)
                || /\.(?:jpe?g|png|webp|gif)(?:\?|#|$)/i.test(url));
    }
    function extractUrls(text) {
        var m = String(text).match(/(https?:\/\/[^\s<>"']+)/gi);
        return m || [];
    }
    function safeHttpUrl(value) {
        var raw = String(value || '');
        if (!raw || /[\x00-\x20"'\\]/.test(raw)) return '';
        try {
            var parsed = new URL(raw, window.location.href);
            return (parsed.protocol === 'http:' || parsed.protocol === 'https:') && !parsed.username && !parsed.password ? parsed.href : '';
        } catch (e) {
            return '';
        }
    }
    // Los binarios solo se cargan directamente desde nuestro origen o el CDN
    // explícito de imágenes; el resto pasa por el proxy autenticado del servidor.
    function safeDirectMediaUrl(value) {
        var safe = safeHttpUrl(value);
        if (!safe) return '';
        var parsed = new URL(safe);
        return parsed.origin === window.location.origin || parsed.hostname.toLowerCase() === 'compartir.site' ? safe : '';
    }
    function externalLinkHtml(url, imageUrl) {
        var safeUrl = safeHttpUrl(url);
        if (!safeUrl) return esc(url);
        var link = document.createElement('a');
        link.href = safeUrl;
        link.target = '_blank';
        link.rel = 'noopener';
        link.className = imageUrl ? '' : 'chat-link';
        link.onclick = function(event) { event.stopPropagation(); };
        if (imageUrl) {
            var image = document.createElement('img');
            image.className = 'chat-img';
            image.src = imageUrl;
            image.alt = 'foto';
            image.loading = 'lazy';
            link.appendChild(image);
        } else {
            link.textContent = safeUrl;
        }
        return link.outerHTML;
    }
    function mediaElementHtml(tag, src) {
        var safe = safeDirectMediaUrl(src);
        if (!safe) return '';
        var element = document.createElement(tag);
        element.src = safe;
        if (tag === 'audio' || tag === 'video') element.controls = true;
        element.style.cssText = tag === 'img'
            ? 'max-width:200px;border-radius:8px;display:block;margin:4px 0'
            : 'max-width:220px;border-radius:8px;display:block;margin:4px 0';
        if (tag === 'img') element.alt = 'Imagen';
        return element.outerHTML;
    }
    function formatMessageBody(text) {
        var s = String(text || '');
        if (!s) return '';
        var urls = extractUrls(s);
        var out = esc(s);
        if (!urls.length) return out;
        for (var i = 0; i < urls.length; i++) {
            var u = urls[i];
            var e = esc(u);
            var src = safeDirectMediaUrl(directImageSrc(u));
            var linkHtml = isImageUrl(u)
                ? externalLinkHtml(u, src)
                : externalLinkHtml(u, '');
            out = out.split(e).join(linkHtml);
        }
        return out;
    }
    function directImageSrc(url) {
        var match = String(url || '').match(/^(https?:\/\/compartir\.site\/([^\/?#]+))\/?(?:[?#].*)?$/i);
        return match ? match[1] + '/' + match[2] + '.jpg' : url;
    }

    /**
     * Render de media recibida + transcripción en cursiva (inbox comercial).
     */
    function renderInboxMedia(m, dir) {
        var media = m && m.media;
        var transcription = (m && m.transcription || '').trim();
        var body = m ? formatMessageBody(m.text || '') : '';
        if (media) {
            var proxy = '';
            if (media.url_source === 'public' && /^https?:\/\//i.test(media.url || '')) {
                proxy = safeDirectMediaUrl(media.url);
            } else if (media.instance && (media.message_id || (m && m.id))) {
                proxy = '/control/media_proxy.php?instance=' + encodeURIComponent(media.instance) + '&msg_id=' + encodeURIComponent(media.message_id || m.id) + '&type=' + encodeURIComponent(media.type || '');
            } else if (media.url_source === 'evolution_authenticated' && media.url) {
                proxy = '/control/media_proxy.php?url=' + encodeURIComponent(media.url) + '&type=' + encodeURIComponent(media.type || '');
            }
            if (proxy) {
                if (media.type === 'audio') {
                    body = mediaElementHtml('audio', proxy) + body;
                } else if (media.type === 'image') {
                    body = mediaElementHtml('img', proxy) + body;
                } else if (media.type === 'video') {
                    body = mediaElementHtml('video', proxy) + body;
                }
            }
        }
        if (transcription) {
            body += '<div class="msg-transcription"><em>🎙️ ' + esc(transcription) + '</em></div>';
        }
        return body;
    }

    function formatDate(ts) {
        if (!ts) return '';
        var d = new Date(ts.replace(' ','T') + (ts.indexOf('+') === -1 && ts.indexOf('Z') === -1 ? 'Z' : ''));
        if (isNaN(d.getTime())) return ts.substring(0,10);
        var now = new Date();
        var diff = Math.floor((now - d) / 86400000);
        if (diff === 0) return 'Hoy';
        if (diff === 1) return 'Ayer';
        return d.toLocaleDateString('es-ES', {day:'numeric',month:'short'});
    }
    function formatTime(ts) {
        if (!ts) return '';
        var d = new Date(ts.replace(' ','T') + (ts.indexOf('+') === -1 && ts.indexOf('Z') === -1 ? 'Z' : ''));
        if (isNaN(d.getTime())) return '';
        return d.toLocaleTimeString('es-ES', {hour:'2-digit',minute:'2-digit'});
    }

    // ── Formatea timestamp para sidebar (estilo WhatsApp real) ──
    //  < 1 min → "Ahora"
    //  < 60 min → "Xm"
    //  < 24h → "HH:MM"
    //  ayer → "Ayer"
    //  < 7 días → nombre del día
    //  < 1 año → "dd/mm"
    //  ≥ 1 año → "dd/mm/yy"
    function formatRelativeTime(ts) {
        if (!ts) return '';
        var d = new Date(ts.replace(' ','T') + (ts.indexOf('+') === -1 && ts.indexOf('Z') === -1 ? 'Z' : ''));
        if (isNaN(d.getTime())) return '';
        var now = new Date();
        var diffMs = now - d;
        var diffSec = Math.floor(diffMs / 1000);
        var diffMin = Math.floor(diffSec / 60);
        var diffHours = Math.floor(diffMin / 60);
        var diffDays = Math.floor(diffHours / 24);

        if (diffSec < 60) return 'Ahora';
        if (diffMin < 60) return diffMin + 'm';
        if (diffHours < 24) return d.toLocaleTimeString('es-ES', {hour:'2-digit', minute:'2-digit'});

        var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        var msgDay = new Date(d.getFullYear(), d.getMonth(), d.getDate());
        var dayDiff = Math.round((today - msgDay) / 86400000);

        if (dayDiff === 1) return 'Ayer';
        if (dayDiff < 7) {
            var days = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
            return days[d.getDay()];
        }
        if (d.getFullYear() === now.getFullYear()) {
            return d.toLocaleDateString('es-ES', {day:'2-digit', month:'2-digit'});
        }
        return d.toLocaleDateString('es-ES', {day:'2-digit', month:'2-digit', year:'2-digit'});
    }

    // ── Formatea timestamp para line header (estilo: "hace Xm", "hace Xh") ──
    function formatLineTime(ts) {
        if (!ts) return '';
        var d = new Date(ts.replace(' ','T') + (ts.indexOf('+') === -1 && ts.indexOf('Z') === -1 ? 'Z' : ''));
        if (isNaN(d.getTime())) return '';
        var now = new Date();
        var diffMs = now - d;
        var diffMin = Math.floor(diffMs / 60000);
        var diffHours = Math.floor(diffMin / 60);
        var diffDays = Math.floor(diffHours / 24);

        if (diffMin < 1) return 'ahora';
        if (diffMin < 60) return 'hace ' + diffMin + 'm';
        if (diffHours < 24) return 'hace ' + diffHours + 'h';
        if (diffDays === 1) return 'ayer';
        if (diffDays < 30) return 'hace ' + diffDays + 'd';
        return 'hace >1 mes';
    }

    // ── Init ──
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // ═══════════════════════════════════════════════════════════════════
    // AGENDA COMERCIAL
    // ═══════════════════════════════════════════════════════════════════

    var agendaEntryList = [];
    var agendaNegociosList = [];
    var agendaCurrentEntry = null;
    var agendaPanelPhones = {};

    function openAgenda() {
        var overlay = document.getElementById('inboxAgendaOverlay');
        if (!overlay) return;
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        agendaShowListView();
        agendaLoad();
    }

    function closeAgenda() {
        var overlay = document.getElementById('inboxAgendaOverlay');
        if (overlay) overlay.style.display = 'none';
        document.body.style.overflow = '';
        agendaHideForm();
        agendaHideDetail();
    }

    function agendaLoad() {
        var negocio = document.getElementById('inboxAgendaFilterNegocio')?.value || '';
        var listEl = document.getElementById('inboxAgendaList');
        if (listEl) listEl.innerHTML = '<div class="inbox-loading">Cargando agenda...</div>';

        var url = api + '?action=comercial_agenda_list&_=' + Date.now();
        if (negocio) url += '&negocio=' + encodeURIComponent(negocio);

        fetch(url, {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (!d.ok) return;
            agendaEntryList = d.entries || [];
            agendaNegociosList = d.negocios || [];
            agendaRenderList(agendaEntryList);
            agendaUpdateFilterOptions();
        }).catch(function(){
            if (listEl) listEl.innerHTML = '<div class="inbox-loading">Error al cargar la agenda</div>';
        });

        // Cargar también los teléfonos que están en el panel de agente
        fetch(api + '?action=comercial_agenda_panel_phones&_=' + Date.now(), {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (!d.ok) return;
            agendaPanelPhones = {};
            var phones = d.panel_phones || [];
            for (var i = 0; i < phones.length; i++) {
                agendaPanelPhones[phones[i]] = true;
            }
            agendaRenderList(agendaEntryList);
        }).catch(function(){ /* silencioso */ });
    }

    function agendaUpdateFilterOptions() {
        var sel = document.getElementById('inboxAgendaFilterNegocio');
        if (!sel) return;
        var currentVal = sel.value;
        sel.innerHTML = '<option value="">Todos los negocios</option>';
        for (var i = 0; i < agendaNegociosList.length; i++) {
            var n = agendaNegociosList[i];
            sel.innerHTML += '<option value="' + escAttr(n.slug) + '">' + esc(n.nombre) + '</option>';
        }
        sel.value = currentVal;
    }

    function agendaRenderList(entries) {
        var listEl = document.getElementById('inboxAgendaList');
        if (!listEl) return;
        // NOTA: No llamamos agendaShowListView() aquí para evitar
        // race condition con formularios o detalles abiertos desde otro flujo.

        if (!entries.length) {
            listEl.innerHTML = '<div class="inbox-empty">No hay contactos en la agenda. Usa "＋ Añadir" para crear uno.</div>';
            return;
        }

        var h = '<table class="inbox-agenda-table"><thead><tr>';
        h += '<th>Nombre</th><th>Teléfono</th><th>Negocio</th><th>Notas</th><th></th>';
        h += '</tr></thead><tbody>';

        for (var i = 0; i < entries.length; i++) {
            var e = entries[i];
            var negocioLabel = e.negocio || '';
            if (e.negocio === 'jostal' && e.submode) {
                negocioLabel += ' (' + (e.submode === 'alquiler' ? 'Alquiler' : 'Plaza') + ')';
            }
            var notas = (e.notas || '').substring(0, 60);
            if ((e.notas || '').length > 60) notas += '...';

            h += '<tr onclick="InboxChat.agendaOpenDetail(\'' + escAttr(e.id) + '\')">';
            h += '<td class="agenda-col-nombre">' + esc(e.nombre || 'Sin nombre') + '</td>';
            h += '<td class="agenda-col-tlf">' + esc(e.telefono || '') + '</td>';
            h += '<td><span class="agenda-col-negocio">' + esc(negocioLabel) + '</span></td>';
            h += '<td class="agenda-col-notas">' + esc(notas) + '</td>';
            h += '<td class="agenda-col-actions" onclick="event.stopPropagation()">';
            h += '<button class="agenda-action-btn agenda-openchat-btn" data-id="' + escAttr(e.id) + '" title="Abrir conversación" onclick="InboxChat.agendaOpenChat(\'' + escAttr(e.id) + '\',\'' + escAttr(e.telefono||'') + '\',\'' + escAttr(e.nombre||'') + '\')">💬</button>';
            h += '<button class="agenda-action-btn" title="Editar" onclick="InboxChat.agendaShowEditForm(\'' + escAttr(e.id) + '\')">✏️</button>';
            h += '<button class="agenda-action-btn agenda-action-delete" title="Eliminar" onclick="InboxChat.agendaDelete(\'' + escAttr(e.id) + '\')">🗑️</button>';
            if (agendaPanelPhones[(e.telefono||'').replace(/\D/g,'')]) {
                h += '<button class="agenda-action-btn agenda-action-panel" title="Ver en el panel de agente" onclick="InboxChat.agendaGoToPanel(\'' + escAttr(e.telefono) + '\')">📊</button>';
            }
            h += '</td></tr>';
        }
        h += '</tbody></table>';
        listEl.innerHTML = h;
    }

    function agendaShowListView() {
        setDisplay('inboxAgendaList', true);
        setDisplay('inboxAgendaFormContainer', false);
        setDisplay('inboxAgendaDetail', false);
        setDisplay('inboxAgendaFilters', true);
    }

    function agendaShowNewForm(phone, threadId) {
        // Mostrar overlay directamente sin pasar por openAgenda() que carga la lista
        var overlay = document.getElementById('inboxAgendaOverlay');
        if (!overlay) return;
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        // Poblar formulario vacío
        document.getElementById('agendaFormId').value = '';
        document.getElementById('agendaFormThreadId').value = threadId || '';
        document.getElementById('agendaFormNombre').value = '';
        document.getElementById('agendaFormTelefono').value = phone || '';
        document.getElementById('agendaFormNotas').value = '';
        document.getElementById('agendaFormNegocio').value = '';
        document.getElementById('agendaFormSubmode').value = 'plaza';
        document.getElementById('inboxAgendaFormTitle').textContent = 'Nuevo contacto';
        agendaPopulateNegocioSelect();
        agendaToggleSubmode();
        setDisplay('inboxAgendaList', false);
        setDisplay('inboxAgendaFormContainer', true);
        setDisplay('inboxAgendaDetail', false);
        setDisplay('inboxAgendaFilters', false);
    }

    function agendaShowEditForm(entryId) {
        // Mostrar overlay
        var overlay = document.getElementById('inboxAgendaOverlay');
        if (overlay) { overlay.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
        setDisplay('inboxAgendaList', false);
        setDisplay('inboxAgendaFormContainer', true);
        setDisplay('inboxAgendaDetail', false);
        setDisplay('inboxAgendaFilters', false);

        var entry = null;
        for (var i = 0; i < agendaEntryList.length; i++) {
            if (agendaEntryList[i].id === entryId) { entry = agendaEntryList[i]; break; }
        }
        if (!entry) {
            // fetch from API
            fetch(api + '?action=comercial_agenda_get&id=' + encodeURIComponent(entryId) + '&_=' + Date.now(), {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.ok && d.entry) agendaFillForm(d.entry);
            });
            return;
        }
        agendaFillForm(entry);
    }

    function agendaFillForm(entry) {
        agendaPopulateNegocioSelect();
        document.getElementById('agendaFormId').value = entry.id || '';
        document.getElementById('agendaFormThreadId').value = entry.thread_id || '';
        document.getElementById('agendaFormNombre').value = entry.nombre || '';
        document.getElementById('agendaFormTelefono').value = entry.telefono || '';
        document.getElementById('agendaFormNegocio').value = entry.negocio || '';
        document.getElementById('agendaFormSubmode').value = entry.submode || 'plaza';
        document.getElementById('agendaFormNotas').value = entry.notas || '';
        document.getElementById('inboxAgendaFormTitle').textContent = 'Editar: ' + (entry.nombre || entry.telefono || 'Contacto');
        agendaToggleSubmode();
        setDisplay('inboxAgendaList', false);
        setDisplay('inboxAgendaFormContainer', true);
        setDisplay('inboxAgendaDetail', false);
        setDisplay('inboxAgendaFilters', false);
    }

    function agendaPopulateNegocioSelect() {
        var sel = document.getElementById('agendaFormNegocio');
        if (!sel) return;
        if (sel.children.length > 1) return; // ya poblado
        sel.innerHTML = '<option value="">Seleccionar...</option>';
        if (agendaNegociosList.length === 0) {
            // Si aún no se cargaron, usar defaults
            var defaults = [
                {slug:'lamami', nombre:'LaMami'},
                {slug:'jostal', nombre:'Jostal'},
                {slug:'casawasap', nombre:'CasaWasap'},
                {slug:'publicista', nombre:'Publicista'},
                {slug:'general', nombre:'General'}
            ];
            agendaNegociosList = defaults;
        }
        for (var i = 0; i < agendaNegociosList.length; i++) {
            var n = agendaNegociosList[i];
            sel.innerHTML += '<option value="' + escAttr(n.slug) + '">' + esc(n.nombre) + '</option>';
        }
    }

    function agendaToggleSubmode() {
        var negocio = document.getElementById('agendaFormNegocio')?.value || '';
        setDisplay('agendaFieldSubmode', negocio === 'jostal');
    }

    function agendaSave(event) {
        if (event) event.preventDefault();
        var btn = document.getElementById('agendaBtnSave');
        if (btn) { btn.textContent = '⏳ Guardando...'; btn.disabled = true; }

        var body = 'action=comercial_agenda_save';
        body += '&id=' + encodeURIComponent(document.getElementById('agendaFormId').value);
        body += '&nombre=' + encodeURIComponent(document.getElementById('agendaFormNombre').value);
        body += '&telefono=' + encodeURIComponent(document.getElementById('agendaFormTelefono').value);
        body += '&negocio=' + encodeURIComponent(document.getElementById('agendaFormNegocio').value);
        body += '&submode=' + encodeURIComponent(document.getElementById('agendaFormSubmode').value);
        body += '&notas=' + encodeURIComponent(document.getElementById('agendaFormNotas').value);
        body += '&thread_id=' + encodeURIComponent(document.getElementById('agendaFormThreadId').value);

        fetch(api + '?action=comercial_agenda_save&_=' + Date.now(), {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body,
            credentials: 'same-origin'
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.ok) {
                agendaShowListView();
                agendaLoad();
                showToast('Contacto guardado ✓', 'ok');
                // Refrescar sidebar y agent table para nombres de agenda
                if (currentView === 'chat') loadLines();
                if (currentView === 'agent') refreshAgentTable();
            } else {
                showToast('Error: ' + (d.error || 'No se pudo guardar'), 'error');
            }
        })
        .catch(function(){ showToast('Error de red', 'error'); })
        .finally(function(){
            if (btn) { btn.textContent = '💾 Guardar'; btn.disabled = false; }
        });
    }

    function agendaDelete(entryId) {
        if (!confirm('¿Eliminar este contacto de la agenda?')) return;

        fetch(api + '?action=comercial_agenda_delete&_=' + Date.now(), {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=comercial_agenda_delete&id=' + encodeURIComponent(entryId),
            credentials: 'same-origin'
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.ok) {
                agendaShowListView();
                agendaLoad();
                showToast('Contacto eliminado ✓', 'ok');
                if (currentView === 'chat') loadLines();
                if (currentView === 'agent') refreshAgentTable();
            } else {
                showToast('Error al eliminar', 'error');
            }
        })
        .catch(function(){ showToast('Error de red', 'error'); });
    }

    function agendaHideForm() {
        setDisplay('inboxAgendaFormContainer', false);
        setDisplay('inboxAgendaDetail', false);
        setDisplay('inboxAgendaList', true);
        setDisplay('inboxAgendaFilters', true);
    }

    function agendaOpenDetail(entryId) {
        // Asegurar overlay visible
        var overlay = document.getElementById('inboxAgendaOverlay');
        if (overlay && overlay.style.display === 'none') {
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        var entry = null;
        for (var i = 0; i < agendaEntryList.length; i++) {
            if (agendaEntryList[i].id === entryId) { entry = agendaEntryList[i]; break; }
        }
        if (!entry) {
            fetch(api + '?action=comercial_agenda_get&id=' + encodeURIComponent(entryId) + '&_=' + Date.now(), {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d.ok && d.entry) agendaRenderDetail(d.entry);
            });
            return;
        }
        agendaRenderDetail(entry);
    }

    function agendaRenderDetail(entry) {
        agendaCurrentEntry = entry;
        var negocioLabel = entry.negocio || '';
        if (entry.negocio === 'jostal' && entry.submode) {
            negocioLabel += ' (' + (entry.submode === 'alquiler' ? 'Alquiler' : 'Plaza') + ')';
        }
        var detailEl = document.getElementById('inboxAgendaDetailContent');
        if (!detailEl) return;
        var h = '';
        h += '<div class="agenda-detail-row"><span class="agenda-detail-label">Nombre</span><span class="agenda-detail-value agenda-detail-name">' + esc(entry.nombre || '(sin nombre)') + '</span></div>';
        h += '<div class="agenda-detail-row"><span class="agenda-detail-label">Teléfono</span><span class="agenda-detail-value">' + esc(entry.telefono || '') + '</span></div>';
        h += '<div class="agenda-detail-row"><span class="agenda-detail-label">Negocio</span><span class="agenda-detail-value"><span class="agenda-col-negocio">' + esc(negocioLabel) + '</span></span></div>';
        h += '<div class="agenda-detail-row"><span class="agenda-detail-label">Notas</span><span class="agenda-detail-value">' + esc(entry.notas || '(sin notas)') + '</span></div>';
        if (entry.created_at) {
            h += '<div class="agenda-detail-row"><span class="agenda-detail-label">Creado</span><span class="agenda-detail-value">' + esc(entry.created_at) + '</span></div>';
        }
        h += '<div class="agenda-detail-actions">';
        h += '<button class="agenda-btn-save agenda-openchat-btn" data-id="' + escAttr(entry.id) + '" style="text-align:center;text-decoration:none;display:inline-flex;align-items:center;justify-content:center" onclick="InboxChat.agendaOpenChat(\'' + escAttr(entry.id) + '\',\'' + escAttr(entry.telefono||'') + '\',\'' + escAttr(entry.nombre||'') + '\')">💬 Chatear</button>';
        h += '<button class="agenda-btn-save" onclick="InboxChat.agendaShowEditForm(\'' + escAttr(entry.id) + '\')">✏️ Editar</button>';
        h += '<button class="agenda-btn-cancel" onclick="InboxChat.agendaDelete(\'' + escAttr(entry.id) + '\')">🗑️ Eliminar</button>';
        if (agendaPanelPhones[(entry.telefono||'').replace(/\D/g,'')]) {
            h += '<button class="agenda-btn-cancel" style="border-color:rgba(37,211,102,.3);color:#25d366" onclick="InboxChat.agendaGoToPanel(\'' + escAttr(entry.telefono) + '\')">📊 En Panel</button>';
        }
        h += '</div>';
        detailEl.innerHTML = h;
        setDisplay('inboxAgendaList', false);
        setDisplay('inboxAgendaFormContainer', false);
        setDisplay('inboxAgendaDetail', true);
        setDisplay('inboxAgendaFilters', false);
    }

    function agendaHideDetail() {
        setDisplay('inboxAgendaDetail', false);
        setDisplay('inboxAgendaList', true);
        setDisplay('inboxAgendaFilters', true);
        agendaCurrentEntry = null;
    }

    function agendaOpenChat(entryId, telefono, nombre) {
        // Buscar la entrada en agendaEntryList para obtener el thread_id
        var entry = agendaCurrentEntry;
        if (entry && entry.id === entryId) {
            // Ya está cargada
        } else {
            for (var i = 0; i < agendaEntryList.length; i++) {
                if (agendaEntryList[i].id === entryId) { entry = agendaEntryList[i]; break; }
            }
        }

        var phoneNorm = (telefono || '').replace(/\D/g, '');

        // Si la entrada ya tiene thread_id, abrir directamente
        if (entry && entry.thread_id) {
            closeAgenda();
            openFullChat(entry.thread_id);
            return;
        }

        // Buscar thread por teléfono vía API
        var btn = document.querySelector('.agenda-openchat-btn[data-id="' + escAttr(entryId) + '"]');
        if (btn) { btn.disabled = true; btn.textContent = '...'; }

        fetch(api + '?action=find_thread_by_phone&phone=' + encodeURIComponent(phoneNorm) + '&_=' + Date.now(), {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (btn) { btn.disabled = false; btn.textContent = '💬'; }
            if (d.ok && d.thread_id) {
                closeAgenda();
                openFullChat(d.thread_id);
            } else {
                showToast('No se encontró conversación para ' + (nombre || telefono));
                // Fallback: abrir WhatsApp nativo
                window.open('https://wa.me/' + phoneNorm, '_blank');
            }
        })
        .catch(function(){
            if (btn) { btn.disabled = false; btn.textContent = '💬'; }
            showToast('Error al buscar conversación');
            window.open('https://wa.me/' + phoneNorm, '_blank');
        });
    }

    function agendaGoToPanel(phone) {
        closeAgenda();
        switchView('agent');
    }

    function agendaOpenFromFullChat() {
        // Abrir desde el header del fullchat: busca el teléfono actual en agenda
        if (!fullChatThreadData || !fullChatThreadData.thread || !fullChatThreadData.thread.agenda_entry) {
            // No hay entrada de agenda → mostrar formulario nuevo
            var phone = fullChatThreadData?.thread?.phone || '';
            var threadId = fullChatThreadId || '';
            agendaShowNewForm(phone, threadId);
            return;
        }
        var ae = fullChatThreadData.thread.agenda_entry;
        if (ae && ae.id) {
            // Mostrar overlay directamente y luego cargar detalle
            var overlay = document.getElementById('inboxAgendaOverlay');
            if (!overlay) return;
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            agendaOpenDetail(ae.id);
        }
    }

    function agendaAddFromFullChat() {
        var phone = fullChatThreadData?.thread?.phone || '';
        var threadId = fullChatThreadId || '';
        agendaShowNewForm(phone, threadId);
    }

    // Helper para mostrar/ocultar
    function setDisplay(id, show) {
        var el = document.getElementById(id);
        if (el) el.style.display = show ? '' : 'none';
    }

    function refreshAgentTable() {
        if (currentView === 'agent') {
            var view = document.getElementById('inboxAgentView');
            if (view) view.removeAttribute('data-loaded');
            loadAgentPanel();
        }
    }

    // ── Panel Add ──
    var panelAddThreadId = null;

    function panelAddShow(threadId, phone) {
        threadId = threadId || selectedThreadId;
        if (!threadId) return;
        panelAddThreadId = threadId;
        var negocioSel = document.getElementById('panelAddNegocio');
        var reasonEl = document.getElementById('panelAddReason');
        document.getElementById('panelAddThreadId').value = threadId;
        if (negocioSel) negocioSel.value = '';
        if (reasonEl) reasonEl.value = '';
        var overlay = document.getElementById('inboxPanelAddOverlay');
        if (overlay) { overlay.style.display = 'flex'; document.body.style.overflow = 'hidden'; }
    }

    function panelAddHide() {
        var overlay = document.getElementById('inboxPanelAddOverlay');
        if (overlay) { overlay.style.display = 'none'; document.body.style.overflow = ''; }
        panelAddThreadId = null;
    }

    function panelAddSubmit(event) {
        if (event) event.preventDefault();
        var btn = document.getElementById('panelAddBtnSave');
        var threadId = document.getElementById('panelAddThreadId').value;
        var negocio = document.getElementById('panelAddNegocio')?.value || '';
        var reason = document.getElementById('panelAddReason')?.value || '';

        if (!threadId || !negocio || !reason) {
            showToast('Completa todos los campos obligatorios');
            return false;
        }
        if (btn) { btn.textContent = '⏳ Añadiendo...'; btn.disabled = true; }

        fetch('inbox_api.php?action=manual_panel_add&_=' + Date.now(), {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=manual_panel_add&thread_id=' + encodeURIComponent(threadId) +
                  '&reason=' + encodeURIComponent(reason) +
                  '&negocio=' + encodeURIComponent(negocio),
            credentials: 'same-origin'
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.ok) {
                showToast('Añadido al panel');
                panelAddHide();
            } else {
                showToast('Error: ' + (d.error || 'No se pudo añadir'));
            }
        })
        .catch(function(){
            showToast('Error de red al añadir al panel');
        })
        .finally(function(){
            if (btn) { btn.textContent = '✓ Añadir al Panel'; btn.disabled = false; }
        });
        return false;
    }

    // ── Photo picker: adjuntar fotos de habitaciones ──
    var photoPickerThreadId = null;
    var photoPickerIsFullChat = false;
    var photoPickerSelected = {};

    function openPhotoPicker(isFullChat) {
        var threadId = isFullChat ? fullChatThreadId : selectedThreadId;
        if (!threadId) {
            showToast('Abre una conversación primero');
            return;
        }
        photoPickerThreadId = threadId;
        photoPickerIsFullChat = !!isFullChat;
        photoPickerSelected = {};
        var overlay = document.getElementById('inboxPhotoPickerOverlay');
        var grid = document.getElementById('inboxPhotoPickerGrid');
        var sendBtn = document.getElementById('inboxPhotoPickerSendBtn');
        if (!overlay || !grid) return;
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        grid.innerHTML = '<div class="inbox-loading">Cargando fotos...</div>';
        if (sendBtn) { sendBtn.disabled = true; }

        fetch(api + '?action=room_photos&_=' + Date.now(), {credentials:'same-origin'})
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (!d.ok || !d.photos || !d.photos.length) {
                grid.innerHTML = '<div class="inbox-empty">No hay fotos de habitaciones disponibles</div>';
                return;
            }
            var h = '';
            for (var i = 0; i < d.photos.length; i++) {
                var p = d.photos[i];
                var url = p.url || '';
                var img = p.img || url;
                if (!url) continue;
                h += '<div class="inbox-photo-picker-thumb" data-url="' + escAttr(url) + '" onclick="InboxChat.photoPickerToggle(this)">'
                    + '<img src="' + escAttr(img) + '" alt="habitación" loading="lazy">'
                    + '<span class="inbox-photo-picker-check">✓</span>'
                    + '</div>';
            }
            grid.innerHTML = h || '<div class="inbox-empty">No hay fotos de habitaciones disponibles</div>';
        })
        .catch(function(){
            grid.innerHTML = '<div class="inbox-empty">Error al cargar las fotos</div>';
        });
    }

    function photoPickerHide() {
        var overlay = document.getElementById('inboxPhotoPickerOverlay');
        if (overlay) overlay.style.display = 'none';
        document.body.style.overflow = '';
        photoPickerThreadId = null;
        photoPickerSelected = {};
    }

    function photoPickerToggle(el) {
        var url = el ? el.getAttribute('data-url') : '';
        if (!url) return;
        var sendBtn = document.getElementById('inboxPhotoPickerSendBtn');
        if (photoPickerSelected[url]) {
            delete photoPickerSelected[url];
            if (el) el.classList.remove('selected');
        } else {
            photoPickerSelected[url] = true;
            if (el) el.classList.add('selected');
        }
        if (sendBtn) sendBtn.disabled = !Object.keys(photoPickerSelected).length;
    }

    function photoPickerSend() {
        var urls = Object.keys(photoPickerSelected);
        var threadId = photoPickerThreadId;
        if (!urls.length || !threadId) return;
        photoPickerHide();
        // Enviar cada foto como mensaje (URL), con pequeño delay entre envíos
        // (mismo patrón que bot-casa sendImages).
        var sendOne = function(idx) {
            if (idx >= urls.length) return;
            var url = urls[idx];
                var fd = 'action=send&thread_id=' + encodeURIComponent(threadId) + '&text=' + encodeURIComponent(url) + '&client_message_id=' + encodeURIComponent(newClientMessageId());
            fetch(api + '?action=send&_=' + Date.now(), {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: fd,
                credentials: 'same-origin'
            })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d.ok) showToast('Error al enviar: ' + (d.error || ''));
                else {
                    if (photoPickerIsFullChat && fullChatThreadId) openFullChat(fullChatThreadId);
                    else loadMessages(true);
                    loadLines();
                }
                setTimeout(function(){ sendOne(idx + 1); }, 2200);
            })
            .catch(function(){
                showToast('Error de red al enviar la foto');
                setTimeout(function(){ sendOne(idx + 1); }, 2200);
            });
        };
        sendOne(0);
    }

    // ── PWA: instalación (solo se muestra el botón si el navegador dispara
    // beforeinstallprompt; Chrome Android cumple criterios PWA) ──
    var _deferredInstallPrompt = null;
    window.addEventListener('beforeinstallprompt', function(e){
        e.preventDefault();
        _deferredInstallPrompt = e;
        var btn = document.getElementById('inboxInstallBtn');
        if (btn) btn.classList.add('is-visible');
    });
    window.addEventListener('appinstalled', function(){
        _deferredInstallPrompt = null;
        var btn = document.getElementById('inboxInstallBtn');
        if (btn) btn.classList.remove('is-visible');
    });
    function installApp() {
        var e = _deferredInstallPrompt;
        if (!e) return;
        _deferredInstallPrompt = null;
        var btn = document.getElementById('inboxInstallBtn');
        if (btn) btn.classList.remove('is-visible');
        try { e.prompt(); } catch (err) { /* prompt() puede no estar listo aún */ }
    }

    // ── Expose ──
    window.InboxChat = {
        currentView: currentView,
        switchView: switchView,
        initAgentTable: initAgentTable,
        selectThread: selectThread,
        sendMessage: sendMessage,
        togglePause: togglePause,
        toggleLine: toggleLine,
        markAllRead: markAllRead,
        filterSidebar: filterSidebar,
        loadLines: loadLines,
        toggleGlobal: toggleGlobal,
        openFullChat: openFullChat,
        closeFullChat: closeFullChat,
        sendFullChatMessage: sendFullChatMessage,
        retryMessage: retryMessage,
        installApp: installApp,
        handleFullChatKey: handleFullChatKey,
        toggleFullChatPause: toggleFullChatPause,
        markRead: markRead,
        updatePanelBadge: updatePanelBadge,
        copyToClipboard: copyToClipboard,
        showToast: showToast,
        // Agenda
        openAgenda: openAgenda,
        closeAgenda: closeAgenda,
        agendaLoad: agendaLoad,
        agendaShowNewForm: agendaShowNewForm,
        agendaShowEditForm: agendaShowEditForm,
        agendaSave: agendaSave,
        agendaDelete: agendaDelete,
        agendaHideForm: agendaHideForm,
        agendaToggleSubmode: agendaToggleSubmode,
        agendaOpenDetail: agendaOpenDetail,
        agendaHideDetail: agendaHideDetail,
        agendaOpenChat: agendaOpenChat,
        agendaOpenFromFullChat: agendaOpenFromFullChat,
        agendaAddFromFullChat: agendaAddFromFullChat,
        agendaGoToPanel: agendaGoToPanel,
        // Panel Add
        panelAddShow: panelAddShow,
        panelAddHide: panelAddHide,
        panelAddSubmit: panelAddSubmit,
        // Photo picker
        openPhotoPicker: openPhotoPicker,
        photoPickerHide: photoPickerHide,
        photoPickerToggle: photoPickerToggle,
        photoPickerSend: photoPickerSend,
    };

    // ── Attach input handlers ──
    document.addEventListener('DOMContentLoaded', function(){
        var input = document.getElementById('inboxChatInput');
        if (input) { input.addEventListener('keydown', handleInputKey); input.addEventListener('input', autoResize); }
        var search = document.getElementById('inboxSearch');
        if (search) search.addEventListener('input', filterSidebar);
        var linesList = document.getElementById('inboxLinesList');
        if (linesList) {
            linesList.addEventListener('scroll', function(){
                if (_searchMode) return;
                if (linesList.scrollTop + linesList.clientHeight >= linesList.scrollHeight - 250) {
                    loadMoreExpandedLines();
                }
            });
        }
        var fullMessages = document.getElementById('inboxFullChatMessages');
        if (fullMessages) fullMessages.addEventListener('scroll', function(){
            if (fullMessages.scrollTop < 80) loadFullChatOlder();
        });
    });
})();
