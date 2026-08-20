/**
 * inbox-chat.js — Operador de chat SuperWasap + Bandeja Comercial.
 * Gestiona sidebar, chat, envío, polling, toggles y vista de agente.
 */
(function(){
    console.log('[inbox-chat] v20260729_3 cargado ✓');
    var api = 'inbox_api.php';
    var selectedLineId = null;
    var selectedThreadId = null;
    var selectedThreadPhone = '';
    var lastMessagesJson = '';
    var lastLinesJson = '';
    var pollingTimer = null;
    var linesData = [];
    var currentView = 'chat'; // 'chat' | 'agent'
    var fullChatThreadId = null;   // ID del hilo abierto en fullscreen
    var fullChatThreadData = null; // Datos del hilo fullscreen
    var _readTimestamps = {};      // { threadId: Date.now() } — marca cuándo se leyó localmente

    // ── Init ──
    function init() {
        if (currentView === 'chat') {
            loadLines();
            pollingTimer = setInterval(function(){
                if (document.hidden) return;
                if (currentView === 'chat') loadLines();
            }, 5000);
        } else if (currentView === 'agent') {
            // Inicializar tabla de agente tras carga del DOM
            setTimeout(function() { initAgentTable(); }, 200);
        }
        // Badge polling siempre activo
        startBadgePolling();
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
            initAgentTable();
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
            loadLines();
        }

        // Update URL without reload
        try { history.replaceState(null, '', '?view=' + view); } catch(e) {}
    }

    // ── Agent Cards — init event listeners ──
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

        panel.querySelectorAll('.agent-filter-btn').forEach(function(btn){
            if (btn._inboxBound) return;
            btn._inboxBound = true;
            btn.addEventListener('click', function(e){
                e.preventDefault();
                var filter = btn.getAttribute('data-filter');
                if (!filter) return;

                // Update active state
                panel.querySelectorAll('.agent-filter-btn').forEach(function(b){ b.classList.remove('is-active'); });
                btn.classList.add('is-active');

                // Filter cards
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
            });
        });

        // Initial badge update
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
                            updateCardBadges(btn.closest('.inbox-agent-grid'));
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
                            updateCardBadges(grid.closest('.agent-table-panel'));
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

    // Poll unread count every 5s for panel badge (lightweight endpoint)
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

    // ── Load lines + threads ──
    function loadLines() {
        fetch(api + '?action=lines&_=' + Date.now(), {credentials:'same-origin'})
        .then(function(r){return r.json()})
        .then(function(d){
            if (!d.ok) return;
            var json = JSON.stringify(d.lines || []);
            if (json === lastLinesJson) return;
            lastLinesJson = json;
            linesData = d.lines || [];
            renderSidebar(linesData);
            if (d.settings) updateGlobalToggles(d.settings);
        }).catch(function(){});
    }

    // ── Sidebar rendering ──
    function renderSidebar(lines) {
        var list = document.getElementById('inboxLinesList');
        if (!list) return;
        if (!lines.length) {
            list.innerHTML = '<div class="inbox-empty">No hay líneas con conversaciones</div>';
            return;
        }
        var now = Date.now();
        var h = '';
        for (var i = 0; i < lines.length; i++) {
            var line = lines[i];
            var lid = line.line_id || '';
            var lname = esc(line.line_name || 'Línea');
            var lphone = line.line_phone || '';
            var threads = line.threads || [];
            var tcount = threads.length;
            var lineLastTs = line.line_last_ts || '';
            var lineUnread = line.line_total_unread || 0;
            var lineTime = formatLineTime(lineLastTs);

            // Race-condition: subtract locally-read threads from server unread count
            for (var j = 0; j < threads.length; j++) {
                var tid = threads[j].id;
                if (threads[j].unread && _readTimestamps[tid] && (now - _readTimestamps[tid]) < 120000) {
                    threads[j].unread = false;
                    lineUnread = Math.max(0, (lineUnread || 0) - 1);
                }
            }

            var dotClass = lineUnread > 0 ? ' line-dot line-dot--unread' : ' line-dot';

            h += '<div class="inbox-line-group collapsed" data-line-id="' + escAttr(lid) + '">';
            h += '<div class="inbox-line-header" onclick="InboxChat.toggleLine(\'' + escAttr(lid) + '\')">';
            h += '<span class="' + dotClass + '"></span>';
            h += '<span>' + esc(lname) + '</span>';
            if (lineTime) {
                h += '<span class="inbox-line-time">' + esc(lineTime) + '</span>';
            }
            h += '<span class="inbox-line-meta">' + esc(lphone) + ' · ' + tcount + '</span>';
            if (lineUnread > 0) {
                h += '<span class="inbox-line-badge-unread">' + lineUnread + '</span>';
            }
            h += '<span class="inbox-line-arrow">▼</span>';
            h += '</div>';
            h += '<div class="inbox-thread-list">';
            if (!threads.length) {
                h += '<div class="inbox-loading">Sin conversaciones</div>';
            } else {
                for (var j = 0; j < threads.length; j++) {
                    var t = threads[j];
                    var tid = t.id || '';
                    var tname = t.display_name || t.phone || '?';
                    var tmsg = t.last_message || '';
                    var tstage = t.stage_label || t.stage || '';
                    var tpaused = t.paused || t.human_taken;
                    var tprocess = t.process_slug || '';
                    var tunread = t.unread;
                    var ttime = formatRelativeTime(t.last_ts || '');
                    var active = (selectedThreadId === tid) ? ' active' : '';
                    var pausedClass = tpaused ? ' paused' : '';
                    var unreadClass = tunread ? ' is-unread' : '';

                    h += '<div class="inbox-thread-item' + active + pausedClass + unreadClass + '" data-thread-id="' + escAttr(tid) + '" onclick="InboxChat.selectThread(\'' + escAttr(tid) + '\',\'' + escAttr(tname) + '\')">';
                    h += '<div class="inbox-thread-item-top">';
                    if (tunread) {
                        h += '<span class="inbox-thread-dot"></span>';
                    }
                    h += '<span class="inbox-thread-name">' + esc(tname) + '</span>';
                    if (ttime) {
                        h += '<span class="inbox-thread-time">' + esc(ttime) + '</span>';
                    }
                    h += '</div>';
                    h += '<div class="inbox-thread-item-sub">';
                    h += '<span class="inbox-thread-stage ' + escAttr(tstage.toLowerCase()) + '">' + esc(tstage) + '</span>';
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
                }
            }
            h += '</div></div>';
        }
        list.innerHTML = h;
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

    // ── Render messages ──
    function renderMessages(messages) {
        var area = document.getElementById('inboxMessages');
        if (!area) return;
        if (!messages.length) {
            area.innerHTML = '<div class="inbox-chat-placeholder"><div class="inbox-chat-placeholder-icon">💬</div><div>Sin mensajes aún</div></div>';
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
            h += '<div class="inbox-msg-bubble ' + dir + cont + '">';
            if (botTag) h += botTag;
            h += '<div class="msg-text">' + formatMessageBody(m.text || '') + '</div>';
            h += '<div class="msg-time">' + esc(time);
            if (dir === 'out') h += '<span class="msg-checks">✓✓</span>';
            h += '</div>';
            h += '</div>';
        }
        area.innerHTML = h;
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
        fetch(api + '?action=send&_=' + Date.now(), {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=send&thread_id=' + encodeURIComponent(selectedThreadId) + '&text=' + encodeURIComponent(text),
            credentials: 'same-origin'
        })
        .then(function(r){return r.json()})
        .then(function(d){
            if (!d.ok) { alert('Error: ' + (d.error || 'No se pudo enviar')); input.value = text; }
            else { loadMessages(true); loadLines(); }
        })
        .catch(function(){ alert('Error de red al enviar'); input.value = text; })
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

    // ── Toggle line collapse ──
    function toggleLine(lineId) {
        var group = document.querySelector('.inbox-line-group[data-line-id="' + lineId + '"]');
        if (group) group.classList.toggle('collapsed');
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
                if (st) st.textContent = settings.replies_enabled ? 'ON' : 'OFF';
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

    // ── Search filter ──
    function filterSidebar() {
        var q = (document.getElementById('inboxSearch')?.value || '').toLowerCase();
        var items = document.querySelectorAll('.inbox-thread-item');
        var groups = document.querySelectorAll('.inbox-line-group');
        items.forEach(function(item){
            var text = (item.textContent || '').toLowerCase();
            item.style.display = (q === '' || text.indexOf(q) !== -1) ? '' : 'none';
        });
        groups.forEach(function(group){
            var vis = group.querySelectorAll('.inbox-thread-item:not([style*="display: none"])');
            group.style.display = vis.length > 0 ? '' : 'none';
        });
    }

    // ── Fullscreen chat (desde la tabla de agente) ──
    function openFullChat(threadId) {
        console.log('[inbox-chat] openFullChat(', threadId, ')');
        fullChatThreadId = threadId;
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
        })
        .catch(function(){
            if (msgEl) msgEl.innerHTML = '<div class="inbox-chat-placeholder"><div>Error al cargar</div></div>';
        });
    }

    function closeFullChat() {
        var overlay = document.getElementById('inboxFullChat');
        if (overlay) overlay.style.display = 'none';
        // Refresh agent badges after closing chat
        var panel = document.querySelector('.inbox-agent-view .agent-table-panel');
        if (panel) updateCardBadges(panel);
        fullChatThreadId = null;
        fullChatThreadData = null;
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

        fetch(api + '?action=send&_=' + Date.now(), {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=send&thread_id=' + encodeURIComponent(fullChatThreadId) + '&text=' + encodeURIComponent(text),
            credentials: 'same-origin'
        })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (!d.ok) { alert('Error: ' + (d.error || 'No se pudo enviar')); input.value = text; }
            else { openFullChat(fullChatThreadId); } // recargar mensajes
        })
        .catch(function(){ alert('Error de red al enviar'); input.value = text; })
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
            h += '<div class="inbox-msg-bubble ' + dir + cont + '">';
            if (botTag) h += botTag;
            h += '<div class="msg-text">' + formatMessageBody(m.text || '') + '</div>';
            h += '<div class="msg-time">' + esc(time);
            if (dir === 'out') h += '<span class="msg-checks">✓✓</span>';
            h += '</div>';
            h += '</div>';
        }
        area.innerHTML = h;
        if (wasAtBottom) area.scrollTop = area.scrollHeight;
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
    function formatMessageBody(text) {
        var s = String(text || '');
        if (!s) return '';
        var urls = extractUrls(s);
        var out = esc(s);
        if (!urls.length) return out;
        for (var i = 0; i < urls.length; i++) {
            var u = urls[i];
            var e = esc(u);
            var linkHtml = isImageUrl(u)
                ? '<a href="' + e + '" target="_blank" rel="noopener" onclick="event.stopPropagation()"><img class="chat-img" src="' + e + '" alt="foto" loading="lazy"></a>'
                : '<a class="chat-link" href="' + e + '" target="_blank" rel="noopener" onclick="event.stopPropagation()">' + e + '</a>';
            out = out.split(e).join(linkHtml);
        }
        return out;
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
        // Forzar recarga de la tabla de agente (reload de la página en modo agent)
        if (currentView === 'agent') {
            // Re-fetch via el endpoint y re-render
            location.reload();
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
            var fd = 'action=send&thread_id=' + encodeURIComponent(threadId) + '&text=' + encodeURIComponent(url);
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

    // ── Expose ──
    window.InboxChat = {
        currentView: currentView,
        switchView: switchView,
        initAgentTable: initAgentTable,
        selectThread: selectThread,
        sendMessage: sendMessage,
        togglePause: togglePause,
        toggleLine: toggleLine,
        filterSidebar: filterSidebar,
        loadLines: loadLines,
        openFullChat: openFullChat,
        closeFullChat: closeFullChat,
        sendFullChatMessage: sendFullChatMessage,
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
    });
})();
