/**
 * chat-operator.js — WhatsApp-style Chat Operador
 *
 * Standalone chat interface for human operators.
 * Uses existing API endpoints (api/lines.php, api/mensajes.php, api/bot-mode.php).
 *
 * Usage: loaded by chat.php which sets globals _apiToken, _csrf, _userId.
 */
var ChatOperator = (function() {

    // ── Globals expected from chat.php ──
    var _apiToken = (typeof _apiToken !== 'undefined') ? _apiToken : '';
    var _csrf = (typeof _csrf !== 'undefined') ? _csrf : '';
    var _userId = (typeof _userId !== 'undefined') ? _userId : 1;

    // ── State ──
    var state = {
        lines: [],
        conversations: {},    // { lineLast9: [{thread_id, phone, last_ts, last_msg, first_msg, unread, sender_lid}] }
        activeLine: null,
        activeThread: null,
        botMode: 'unknown',   // 'start' | 'stop' | 'unknown'
        convPause: {},        // { thread_id: bool }
        pollInterval: null,
        lastMsgCount: 0,
        linesSummary: {},
        _readTimestamps: {},
        _renderedCount: 0,
        _pollTick: 0,
        photoPicker: {
            overlay: null,
            girls: [],
            selected: [],
        },
        soundEnabled: true,
        _readUnreadSnap: {},
    };

    // ── Emojis ──
    var EMOJIS = [
        '😊','😂','❤️','😍','😘','🥰','😜','😎','🤩','😇','🙈','💋',
        '💕','✨','🔥','💯','👍','👋','🌹','🌸','💐','🎉','🎀','💎',
        '😏','😉','🤗','😌','😛','🤔','😅','😢','😤','🥺','😴','🤤',
        '👀','💪','🤝','🙏','💃','🕺','👑','💄','👠','👜','🌟','⭐',
        '✅','❌','⏰','📱','💬','📢','📍','🏠','🚗','💰','💶','🎁',
    ];

    // ── Helpers ──

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = (s === undefined || s === null) ? '' : s;
        return d.innerHTML;
    }

    function apiUrl(url) {
        var sep = url.indexOf('?') === -1 ? '?' : '&';
        return url + sep + 'token=' + encodeURIComponent(_apiToken);
    }

    function _fetch(url, opts) {
        opts = opts || {};
        return fetch(url, opts).then(function(r) {
            if (r.status === 403) {
                // Token expired — try refreshing
                return fetch('api/csrf-token.php').then(function(tr) { return tr.json(); }).then(function(td) {
                    if (td.ok && td.token) {
                        _apiToken = td.token;
                        _csrf = td.token;
                        if (opts.body instanceof FormData) {
                            opts.body.set('csrf_token', td.token);
                        }
                        var newUrl = url.replace(/token=[^&]*/, 'token=' + encodeURIComponent(td.token));
                        return fetch(newUrl, opts);
                    }
                    throw new Error('Token refresh failed');
                });
            }
            return r;
        });
    }

    function formatPhone(phone) {
        if (!phone) return 'Desconocido';
        var d = phone.replace(/[^0-9]/g, '');
        if (d.length >= 9) {
            return '+' + d.slice(0, 2) + ' ' + d.slice(2, 5) + ' ' + d.slice(5, 8) + ' ' + d.slice(8);
        }
        return '+' + d;
    }

    function formatTime(ts) {
        if (!ts) return '';
        try {
            var d = new Date(ts);
            if (isNaN(d.getTime())) return '';
            return d.toLocaleTimeString('es-ES', {hour:'2-digit', minute:'2-digit'});
        } catch(e) { return ''; }
    }

    function formatDate(ts) {
        if (!ts) return '';
        try {
            var d = new Date(ts);
            if (isNaN(d.getTime())) return '';
            var today = new Date();
            var yesterday = new Date(today);
            yesterday.setDate(yesterday.getDate() - 1);
            if (d.toDateString() === today.toDateString()) return 'Hoy';
            if (d.toDateString() === yesterday.toDateString()) return 'Ayer';
            return d.toLocaleDateString('es-ES', {weekday:'long', day:'numeric', month:'long'});
        } catch(e) { return ''; }
    }

    function dateKey(ts) {
        if (!ts) return '';
        try {
            var d = new Date(ts);
            if (isNaN(d.getTime())) return '';
            return d.toISOString().slice(0,10);
        } catch(e) { return ''; }
    }

    function getInitial(phone) {
        if (!phone) return '?';
        var d = phone.replace(/[^0-9a-zA-Z]/g, '');
        return d.charAt(d.length - 1).toUpperCase();
    }

    function isImageUrl(url) {
        if (!url) return false;
        if (/\.(?:jpg|jpeg|png|webp|gif|bmp|svg)(?:\?|#|$)/i.test(url)) return true;
        if (/\/(imgur\.com|ibb\.co|i\.ibb\.co|cloudinary\.com|cloudfront\.net|amazonaws\.com)/i.test(url)) return true;
        if (/\/\/[.a-z0-9-]*compartir\.site\//i.test(url)) return true;
        if (/\/api\/image-proxy\.php/i.test(url)) return true;
        if (/\/(?:photo|image|img|pic|fotos?|girls?|photos?|images?)\//i.test(url)) return true;
        return false;
    }

    function formatMessageBody(text) {
        if (!text) return '';
        var urlRegex = /(https?:\/\/[^\s<>"')\]}\u0000-\u001F\u007F-\u009F]+)/gi;
        var parts = text.split(urlRegex);
        var result = '';
        for (var i = 0; i < parts.length; i++) {
            var part = parts[i];
            if (!part) continue;
            if (/^https?:\/\//i.test(part)) {
                var cleanUrl = part.replace(/[.,;:!?]+$/g, '');
                var trailing = part.slice(cleanUrl.length);
                if (isImageUrl(cleanUrl)) {
                    var imgSrc = getDirectImageUrl(cleanUrl);
                    result += '<img class="chat-img" src="' + esc(imgSrc) + '" ' +
                        'alt="Imagen" loading="lazy" ' +
                        'onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'inline\';" ' +
                        'onclick="window.open(\'' + esc(cleanUrl) + '\',\'_blank\')" ' +
                        'title="Click para abrir">' +
                        '<a class="chat-img-fallback" href="' + esc(cleanUrl) + '" ' +
                        'target="_blank" rel="noopener" style="display:none">' + esc(cleanUrl) + '</a>';
                } else {
                    result += '<a class="chat-link" href="' + esc(cleanUrl) + '" target="_blank" rel="noopener">' + esc(cleanUrl) + '</a>';
                }
                result += esc(trailing);
            } else {
                result += esc(part);
            }
        }
        return result;
    }

    /**
     * Render de media recibida (imagen/audio/vídeo) + transcripción en cursiva.
     */
    function renderMediaBlock(msg) {
        var media = msg && msg.media;
        var transcription = (msg && msg.transcription || '').trim();
        var proxy = '';
        if (media) {
            if (media.instance && msg && msg.id) {
                proxy = '/control/media_proxy.php?instance=' + encodeURIComponent(media.instance) + '&msg_id=' + encodeURIComponent(msg.id) + '&type=' + encodeURIComponent(media.type || '');
            } else if (media.url) {
                proxy = '/control/media_proxy.php?url=' + encodeURIComponent(media.url) + '&type=' + encodeURIComponent(media.type || '');
            }
        }
        if (!proxy) {
            return transcription ? ('<div class="msg-transcription"><em>🎙️ ' + esc(transcription) + '</em></div>') : '';
        }
        var html = '';
        if (media.type === 'audio') {
            html += '<audio controls style="max-width:220px;display:block;margin:4px 0" src="' + proxy + '"></audio>';
        } else if (media.type === 'image') {
            html += '<img src="' + proxy + '" style="max-width:200px;border-radius:8px;display:block;margin:4px 0" alt="Imagen">';
        } else if (media.type === 'video') {
            html += '<video controls style="max-width:220px;border-radius:8px;display:block;margin:4px 0" src="' + proxy + '"></video>';
        }
        if (transcription) {
            html += '<div class="msg-transcription"><em>🎙️ ' + esc(transcription) + '</em></div>';
        }
        return html;
    }

    // ── CSRF refresh ──
    function refreshCsrf() {
        fetch('api/csrf-token.php').then(function(r) { return r.json(); }).then(function(d) {
            if (d.ok && d.token) {
                _apiToken = d.token;
                _csrf = d.token;
            }
        }).catch(function(){});
    }
    setInterval(refreshCsrf, 240000);

    // ── API: Bot Mode ──
    function loadBotMode() {
        _fetch(apiUrl('api/bot-mode.php')).then(function(r) { return r.json(); }).then(function(d) {
            if (d.ok) {
                state.botMode = d.mode;
                renderBotToggle();
            }
        }).catch(function(){});
    }

    function toggleBotMode() {
        var fd = new FormData();
        fd.append('action', 'toggle');
        fd.append('csrf_token', _csrf);

        _fetch(apiUrl('api/bot-mode.php'), { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok) {
                    state.botMode = d.mode;
                    renderBotToggle();
                    if (state.activeThread) {
                        addSystemMessage(state.botMode === 'start' ? '🟢 Bot encendido — responderá automáticamente' : '🔴 Bot apagado — no responderá automáticamente');
                    }
                } else {
                    alert('Error: ' + (d.error || 'No se pudo cambiar el modo'));
                }
            }).catch(function() {
                alert('Error de conexión al cambiar el modo del bot');
            });
    }

    // ── System message in current conversation ──
    function addSystemMessage(text) {
        var msgArea = document.getElementById('chat-messages');
        if (!msgArea) return;
        var placeholder = msgArea.querySelector('.chat-placeholder, .chat-empty');
        if (placeholder) msgArea.innerHTML = '';
        msgArea.insertAdjacentHTML('beforeend',
            '<div class="chat-msg-system"><div class="system-text">' + esc(text) + '</div></div>');
        scrollToBottom(true);
    }

    // ── Notification sound (WhatsApp-like multi-tone) ──
    var _audioCtx = null;
    function playNotification() {
        if (!state.soundEnabled) return;
        try {
            if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            var ctx = _audioCtx;
            if (ctx.state === 'suspended') ctx.resume();
            var now = ctx.currentTime;

            // 5-note ascending pattern mimicking WhatsApp notification
            var notes = [
                { freq: 1175, at: 0,    dur: 0.08, gain: 0.22 },
                { freq: 1568, at: 0.10, dur: 0.08, gain: 0.22 },
                { freq: 1319, at: 0.20, dur: 0.08, gain: 0.20 },
                { freq: 1568, at: 0.30, dur: 0.08, gain: 0.22 },
                { freq: 1175, at: 0.40, dur: 0.12, gain: 0.18 },
            ];
            for (var i = 0; i < notes.length; i++) {
                var n = notes[i];
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.type = 'triangle';
                osc.frequency.value = n.freq;
                gain.gain.setValueAtTime(0, now + n.at);
                gain.gain.linearRampToValueAtTime(n.gain, now + n.at + 0.005);
                gain.gain.exponentialRampToValueAtTime(0.001, now + n.at + n.dur);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now + n.at);
                osc.stop(now + n.at + n.dur);
            }
        } catch(e) { /* audio not available */ }

        // Native notification (works even with tab in background / phone locked)
        if (typeof Notification !== 'undefined' && Notification.permission === 'granted') {
            try { new Notification('SuperWasap', { body: 'Nuevo mensaje recibido', icon: 'assets/wa-icon.svg' }); } catch(e) {}
        } else if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    }

    // ── Sound toggle ──
    function initSound() {
        var stored = localStorage.getItem('chat-operator-sound');
        if (stored === 'off') { state.soundEnabled = false; updateSoundBtn(); }
    }

    function updateSoundBtn() {
        var btn = document.getElementById('sound-toggle-btn');
        if (btn) {
            btn.textContent = state.soundEnabled ? '🔔' : '🔕';
            if (state.soundEnabled) btn.classList.remove('muted');
            else btn.classList.add('muted');
        }
    }

    function toggleSound() {
        state.soundEnabled = !state.soundEnabled;
        localStorage.setItem('chat-operator-sound', state.soundEnabled ? 'on' : 'off');
        updateSoundBtn();
    }

    function renderBotToggle() {
        var btn = document.getElementById('bot-toggle-btn');
        var status = document.getElementById('bot-status-label');
        if (!btn || !status) return;

        var isOn = state.botMode === 'start';
        btn.className = 'bot-toggle' + (isOn ? ' on' : ' off');
        btn.innerHTML = isOn ? 'APAGAR BOT' : 'ENCENDER BOT';
        status.textContent = isOn ? 'BOT ENCENDIDO' : 'BOT APAGADO';
        status.className = 'bot-status-label' + (isOn ? ' on' : ' off');
    }

    // ── API: Lines ──
    function loadLines() {
        _fetch(apiUrl('api/lines.php?action=list'))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok && d.lines) {
                    state.lines = d.lines;
                    // Also load WAHA statuses
                    _fetch(apiUrl('api/lines.php?action=status'))
                        .then(function(r2) { return r2.json(); })
                        .then(function(d2) {
                            if (d2.ok && d2.statuses) {
                                for (var i = 0; i < state.lines.length; i++) {
                                    var st = d2.statuses[state.lines[i].id];
                                    if (st) state.lines[i].live_status = st;
                                }
                            }
                            renderLines();
                        }).catch(function() { renderLines(); });
                } else {
                    renderLines();
                }
            }).catch(function() {
                renderLines();
            });
    }

    function loadLinesSummary() {
        _fetch(apiUrl('api/mensajes.php?action=threads_summary'))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok && d.summary) {
                    var now = Date.now();
                    var readByLine = {};
                    for (var tid in state._readTimestamps) {
                        if (state._readTimestamps.hasOwnProperty(tid) && (now - state._readTimestamps[tid] < 15000)) {
                            var pos = tid.indexOf('_');
                            var ln = (pos !== -1) ? tid.substring(0, pos) : '';
                            if (ln) {
                                var snap = state._readUnreadSnap[tid] || 0;
                                readByLine[ln] = (readByLine[ln] || 0) + Math.max(1, snap);
                            }
                        }
                    }
                    for (var k in d.summary) {
                        if (d.summary.hasOwnProperty(k)) {
                            var svr = d.summary[k].total_unread || 0;
                            var local = readByLine[k] || 0;
                            if (local > 0 && svr > 0) d.summary[k].total_unread = Math.max(0, svr - local);
                            state.linesSummary[k] = d.summary[k];
                        }
                    }
                }
                renderLines();
            }).catch(function(){});
    }

    function loadPausedList() {
        _fetch(apiUrl('api/mensajes.php?action=paused_list'))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok && d.paused) {
                    for (var i = 0; i < d.paused.length; i++) {
                        state.convPause[d.paused[i]] = true;
                    }
                }
                updatePauseUI();
            }).catch(function(){});
    }

    // ── Render Sidebar ──
    function renderLines() {
        var listEl = document.getElementById('lines-list');
        if (!listEl) return;

        if (state.lines.length === 0) {
            listEl.innerHTML = '<div class="no-convs"><div class="no-convs-icon">📱</div>No hay líneas WhatsApp configuradas.</div>';
            return;
        }

        var html = '';
        for (var i = 0; i < state.lines.length; i++) {
            var line = state.lines[i];
            var last9 = line.last9 || '';
            var label = line.descripcion || line.label || ('Línea ' + line.id);
            var phone = line.health_phone || line.phone || last9;
            var liveSt = line.live_status || line.health_status || 'unknown';

            var dotClass = 'unknown';
            if (liveSt === 'WORKING') dotClass = 'online';
            else if (liveSt === 'STARTING' || liveSt === 'starting' || liveSt === 'SCAN_QR') dotClass = 'starting';
            else if (liveSt === 'STOPPED' || liveSt === 'down' || liveSt === 'FAILED') dotClass = 'offline';
            else if (liveSt === 'error') dotClass = 'offline';

            var isExpanded = state.activeLine === last9;
            var summary = state.linesSummary[last9] || {};
            var totalUnread = summary.total_unread || 0;
            var totalConvos = summary.total_convos || 0;

            var badgeHtml = '';
            if (totalUnread > 0) badgeHtml += '<span class="line-badge-unread">' + totalUnread + '</span>';
            if (totalConvos > 0) badgeHtml += '<span class="line-badge-total">' + totalConvos + '</span>';
            if (badgeHtml !== '') badgeHtml = '<span class="line-badges">' + badgeHtml + '</span>';

            var markHtml = '';
            if (totalUnread > 0) {
                markHtml = '<button type="button" class="line-mark-read" title="Marcar todas como leídas" ' +
                    'onclick="event.stopPropagation();ChatOperator.markAllRead(\'' + esc(last9) + '\')">✓</button>';
            }

            var convsForLine = state.conversations[last9] || [];
            var lineLastTs = '';
            for (var ci = 0; ci < convsForLine.length; ci++) {
                var cts = convsForLine[ci].last_ts || '';
                if (cts > lineLastTs) lineLastTs = cts;
            }
            var lineTime = formatTime(lineLastTs);

            html += '<div class="line-row' + (isExpanded ? ' expanded' : '') + '" onclick="ChatOperator.toggleLine(\'' + esc(last9) + '\')">' +
                '<span class="line-dot ' + dotClass + '"></span>' +
                '<div class="line-info">' +
                    '<div class="line-name">' +
                        '<span class="line-label">' + esc(label) + '<span class="line-engine">(' + (line.transport === 'evolution' ? 'evo' : 'waha') + ')</span></span>' +
                        (lineTime ? '<span class="line-time">' + esc(lineTime) + '</span>' : '') +
                    '</div>' +
                    '<div class="line-phone">' + esc(formatPhone(phone)) + '</div>' +
                '</div>' +
                badgeHtml +
                markHtml +
                '<span class="line-chevron">▶</span>' +
            '</div>';

            // Conversations under this line
            html += '<div class="line-conversations" id="convs-' + esc(last9) + '">';
            var convs = state.conversations[last9] || [];
            if (convs.length === 0) {
                html += '<div class="no-convs" style="font-size:.78rem;padding:12px 16px 12px 44px">Sin conversaciones</div>';
            } else {
                for (var j = 0; j < convs.length; j++) {
                    var c = convs[j];
                    var isActiveConv = state.activeThread === c.thread_id;
                    var convName = formatPhone(c.phone || '');
                    var preview = c.last_msg || c.first_msg || '';
                    if (preview.length > 40) preview = preview.slice(0, 40) + '...';
                    var time = formatTime(c.last_ts);
                    var isPaused = state.convPause[c.thread_id];
                    var displayTime = time || '--:--';

                    html += '<div class="conv-row' + (isActiveConv ? ' active' : '') + '" onclick="event.stopPropagation();ChatOperator.openConversation(\'' + esc(c.thread_id) + '\',\'' + esc(c.phone || '') + '\')">' +
                        '<div class="conv-avatar">' + esc(getInitial(c.phone || '')) + '</div>' +
                        '<div class="conv-info">' +
                            '<div class="conv-name">' + esc(convName) +
                                '<span class="conv-time-inline">' + esc(displayTime) + '</span>' +
                            '</div>' +
                            '<div class="conv-preview">' + esc(preview) + '</div>' +
                        '</div>' +
                        '<div class="conv-meta">' +
                            '<span class="conv-time">' + esc(displayTime) + '</span>' +
                            (c.unread > 0 ? '<span class="conv-badge-unread">' + c.unread + '</span>' : '') +
                            (isPaused ? '<span class="conv-paused">⏸</span>' : '') +
                        '</div>' +
                    '</div>';
                }
            }
            html += '</div>';
        }

        listEl.innerHTML = html;
    }

    // ── Search ──
    function filterConversations() {
        var q = (document.getElementById('search-input').value || '').toLowerCase().trim();
        var rows = document.querySelectorAll('.conv-row');
        var lines = document.querySelectorAll('.line-row');
        var lineConvs = document.querySelectorAll('.line-conversations');

        if (!q) {
            // Show all
            rows.forEach(function(r) { r.style.display = ''; });
            lines.forEach(function(l) { l.style.display = ''; });
            lineConvs.forEach(function(lc) { lc.style.display = ''; });
            // Re-collapse non-active lines
            for (var i = 0; i < state.lines.length; i++) {
                var last9 = state.lines[i].last9 || '';
                if (state.activeLine !== last9) {
                    var lc = document.getElementById('convs-' + last9);
                    if (lc) lc.style.display = 'none';
                }
            }
            return;
        }

        // Filter
        var visibleLines = {};
        rows.forEach(function(r) {
            var text = (r.textContent || '').toLowerCase();
            if (text.indexOf(q) !== -1) {
                r.style.display = '';
                var threadId = r.getAttribute('data-thread') || '';
                if (threadId) {
                    var pos = threadId.indexOf('_');
                    if (pos !== -1) visibleLines[threadId.substring(0, pos)] = true;
                }
            } else {
                r.style.display = 'none';
            }
        });

        // Show/hide lines and conversations
        for (var i = 0; i < state.lines.length; i++) {
            var last9 = state.lines[i].last9 || '';
            var lc = document.getElementById('convs-' + last9);
            if (visibleLines[last9]) {
                if (lc) lc.style.display = 'block';
            } else if (q) {
                if (lc) lc.style.display = 'none';
            }
        }
    }

    // ── Line / Conversation Selection ──
    function toggleLine(last9) {
        if (state.activeLine === last9) {
            state.activeLine = null;
        } else {
            state.activeLine = last9;
            if (!state.conversations[last9]) {
                loadConversationsForLine(last9);
            }
        }
        renderLines();
    }

    function loadConversationsForLine(last9) {
        var convsEl = document.getElementById('convs-' + last9);
        if (convsEl) {
            convsEl.innerHTML = '<div class="no-convs" style="font-size:.78rem;padding:12px 16px 12px 44px">Cargando...</div>';
        }

        _fetch(apiUrl('api/mensajes.php?action=threads&last9=' + encodeURIComponent(last9)))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) { state.conversations[last9] = []; renderLines(); return; }
                state.conversations[last9] = d.threads;
                renderLines();
            }).catch(function() {
                state.conversations[last9] = [];
                renderLines();
            });
    }

    function openConversation(threadId, phone) {
        // When switching threads, stop suppressing unread for the old one
        if (state.activeThread && state.activeThread !== threadId) {
            delete state._readTimestamps[state.activeThread];
            delete state._readUnreadSnap[state.activeThread];
        }
        state.activeThread = threadId;
        state.lastMsgCount = 0;
        state._renderedCount = 0;

        // Clear unread locally
        var lineLast9 = state.activeLine;
        if (lineLast9 && state.conversations[lineLast9]) {
            var convs = state.conversations[lineLast9];
            for (var i = 0; i < convs.length; i++) {
                if (convs[i].thread_id === threadId && convs[i].unread > 0) {
                    convs[i].unread = 0;
                    if (state.linesSummary[lineLast9] && state.linesSummary[lineLast9].total_unread > 0) {
                        state.linesSummary[lineLast9].total_unread = Math.max(0, state.linesSummary[lineLast9].total_unread - 1);
                    }
                    break;
                }
            }
        }
        state._readTimestamps[threadId] = Date.now();

        // Snapshot: how many unread the server reported before markRead
        var convsSnap = state.conversations[state.activeLine] || [];
        for (var ks = 0; ks < convsSnap.length; ks++) {
            if (convsSnap[ks].thread_id === threadId) {
                state._readUnreadSnap[threadId] = convsSnap[ks].unread || 0;
                break;
            }
        }

        renderLines();
        markRead(threadId);

        // Update header
        var avatarEl = document.getElementById('chat-header-avatar');
        var nameEl = document.getElementById('chat-header-name');
        var subtitleEl = document.getElementById('chat-header-subtitle');
        if (avatarEl) avatarEl.textContent = getInitial(phone);
        if (nameEl) nameEl.textContent = formatPhone(phone);
        if (subtitleEl) {
            var lineLabel = '';
            for (var j = 0; j < state.lines.length; j++) {
                if (state.lines[j].last9 === state.activeLine) {
                    lineLabel = state.lines[j].descripcion || state.lines[j].label || '';
                    break;
                }
            }
            subtitleEl.textContent = lineLabel ? 'Línea: ' + lineLabel : '';
        }

        // Update UI
        updatePauseUI();
        loadMessages(threadId);

        // Mobile: switch to main panel
        modalOpenConversation();
    }

    function modalOpenConversation() {
        if (window.innerWidth <= 768) {
            var layout = document.querySelector('.app-layout');
            if (layout) layout.classList.add('in-conversation');
        }
    }

    function backToSidebar() {
        if (state.activeThread) {
            delete state._readTimestamps[state.activeThread];
            delete state._readUnreadSnap[state.activeThread];
        }
        state.activeThread = null;
        state.lastMsgCount = 0;
        state._renderedCount = 0;
        if (window.innerWidth <= 768) {
            var layout = document.querySelector('.app-layout');
            if (layout) layout.classList.remove('in-conversation');
        }
        renderLines();
    }

    function markRead(threadId, retries) {
        retries = retries || 0;
        var fd = new FormData();
        fd.append('thread_id', threadId);
        fd.append('csrf_token', _csrf);

        _fetch(apiUrl('api/mensajes.php?action=mark_read'), { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok) {
                    if (!state._readTimestamps[threadId]) state._readTimestamps[threadId] = Date.now();
                } else if (retries < 2) {
                    setTimeout(function() { markRead(threadId, retries + 1); }, 2000);
                }
            }).catch(function() {
                if (retries < 2) setTimeout(function() { markRead(threadId, retries + 1); }, 2000);
            });
    }

    // ── Mark all as read (per line, or all lines when last9 is empty) ──
    function markAllRead(last9) {
        last9 = last9 || '';

        // Optimistic local clear of unread badges for affected lines.
        var targets;
        if (last9 !== '') {
            targets = [last9];
        } else {
            targets = [];
            for (var k in state.linesSummary) {
                if (state.linesSummary.hasOwnProperty(k)) targets.push(k);
            }
            for (var ln in state.conversations) {
                if (state.conversations.hasOwnProperty(ln) && targets.indexOf(ln) === -1) targets.push(ln);
            }
        }

        for (var t = 0; t < targets.length; t++) {
            var ln = targets[t];
            var convs = state.conversations[ln] || [];
            for (var i = 0; i < convs.length; i++) {
                convs[i].unread = 0;
                delete state._readTimestamps[convs[i].thread_id];
                delete state._readUnreadSnap[convs[i].thread_id];
            }
            if (state.linesSummary[ln]) state.linesSummary[ln].total_unread = 0;
        }

        renderLines();

        var fd = new FormData();
        if (last9 !== '') fd.append('last9', last9);
        fd.append('csrf_token', _csrf);

        _fetch(apiUrl('api/mensajes.php?action=mark_all_read'), { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok) loadLinesSummary();
            }).catch(function(){});
    }

    // ── Messages ──
    function loadMessages(threadId) {
        var msgArea = document.getElementById('chat-messages');
        if (!msgArea) return;
        msgArea.innerHTML = '<div class="chat-loading"><div class="spinner"></div><div>Cargando mensajes...</div></div>';

        _fetch(apiUrl('api/mensajes.php?action=conversation&thread_id=' + encodeURIComponent(threadId)))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (state.activeThread !== threadId) return;
                if (!d.ok) {
                    msgArea.innerHTML = '<div class="chat-error">Error al cargar conversación</div>';
                    return;
                }
                var conv = d.conversation || [];
                state.lastMsgCount = conv.length;
                renderMessages(conv);
            }).catch(function() {
                if (state.activeThread !== threadId) return;
                msgArea.innerHTML = '<div class="chat-error">Error de conexión</div>';
            });
    }

    function renderMessages(conversation) {
        var msgArea = document.getElementById('chat-messages');
        if (!msgArea) return;

        if (!conversation || conversation.length === 0) {
            msgArea.innerHTML = '<div class="chat-placeholder">' +
                '<div class="chat-placeholder-icon">💭</div>' +
                '<h3>Sin mensajes aún</h3>' +
                '<p>No hay mensajes en esta conversación.</p></div>';
            state._renderedCount = 0;
            return;
        }

        // Sort by timestamp
        conversation.sort(function(a, b) {
            return (a.ts || '').localeCompare(b.ts || '');
        });

        // Dedup logic (same as chat.js)
        var deduped = [];
        var seenFull = {};
        for (var i = 0; i < conversation.length; i++) {
            var m = conversation[i];
            var umsg = (m.user_msg || '').trim();
            var breply = (m.bot_reply || '').trim();
            if (umsg && !breply && m._pending) {
                var skip = false;
                for (var j = i + 1; j < conversation.length; j++) {
                    if ((conversation[j].user_msg || '').indexOf(umsg) !== -1 && (conversation[j].bot_reply || '').trim()) {
                        skip = true; break;
                    }
                }
                if (skip) continue;
            }
            if (umsg || breply) {
                var key = umsg + '|' + breply + '|' + (m.ts || '');
                if (seenFull[key]) continue;
                seenFull[key] = true;
            }
            deduped.push(m);
        }
        conversation = deduped;

        // Track _pending count to detect resolved pendings
        var pendingCount = 0;
        for (var p = 0; p < conversation.length; p++) {
            if (conversation[p]._pending) pendingCount++;
        }
        var prevPending = (typeof state._pendingCount === 'number') ? state._pendingCount : pendingCount;

        var prevCount = state._renderedCount || 0;
        var totalCount = conversation.length;

        // Force full rebuild if pendings changed (e.g. pending resolved to full)
        if (prevPending > pendingCount) {
            state._renderedCount = 0;
            prevCount = 0;
        }
        state._pendingCount = pendingCount;

        if (prevCount === 0 || totalCount < prevCount) {
            msgArea.innerHTML = '';
            state._renderedCount = 0;
            prevCount = 0;
        }

        var wasAtBottom = (msgArea.scrollHeight - msgArea.scrollTop - msgArea.clientHeight) <= 120;

        var lastDate = '';
        var html = '';
        for (var i = prevCount; i < totalCount; i++) {
            var msg = conversation[i];
            var ts = msg.ts || '';
            var dk = dateKey(ts);

            if (dk !== lastDate && ts) {
                html += '<div class="chat-date-sep"><span>' + esc(formatDate(ts)) + '</span></div>';
                lastDate = dk;
            }

            var userMsg = (msg.user_msg || '').trim();
            var botMsg = (msg.bot_reply || '').trim();

            if (userMsg) {
                var userBody = (msg.media && msg.media.type === 'audio')
                    ? renderMediaBlock(msg)
                    : formatMessageBody(userMsg) + renderMediaBlock(msg);
                html += '<div class="chat-msg user"><div class="bubble">' +
                    '<div class="msg-body">' + userBody + '</div>' +
                    '<div class="msg-time">' + esc(formatTime(ts)) + '</div>' +
                '</div></div>';
            }

            if (botMsg) {
                // Skip if identical bot message already visible in DOM (prevent duplicate on send)
                var existingBots = msgArea.querySelectorAll('.chat-msg.bot .msg-body');
                var lastBot = existingBots[existingBots.length - 1];
                if (lastBot && lastBot.textContent.trim() === botMsg.trim()) continue;
                html += '<div class="chat-msg bot"><div class="bubble">' +
                    '<div class="msg-body">' + formatMessageBody(botMsg) + '</div>' +
                    '<div class="msg-time">' + esc(formatTime(ts)) +
                        '<span class="msg-checks">✓✓</span></div>' +
                '</div></div>';
            }
        }

        if (prevCount === 0) {
            msgArea.innerHTML = html;
        } else if (html) {
            msgArea.insertAdjacentHTML('beforeend', html);
        }
        state._renderedCount = totalCount;

        // Typing indicator
        var oldTyping = msgArea.querySelector('.chat-typing');
        if (oldTyping) oldTyping.remove();

        var hasRecentPending = false;
        for (var p = 0; p < conversation.length; p++) {
            if (conversation[p]._pending) {
                var msgTs = new Date(conversation[p].ts).getTime();
                if ((Date.now() - msgTs) < 5 * 60 * 1000) { hasRecentPending = true; break; }
            }
        }
        var paused = state.convPause[state.activeThread] || false;
        if (hasRecentPending && !paused) {
            var typingEl = document.createElement('div');
            typingEl.className = 'chat-typing';
            typingEl.innerHTML = '<span class="bot-typing-icon">🤖</span><span></span><span></span><span></span>';
            msgArea.appendChild(typingEl);
        }

        if (wasAtBottom) scrollToBottom(false);
    }

    function scrollToBottom(smooth) {
        var msgArea = document.getElementById('chat-messages');
        if (!msgArea) return;
        if (smooth) {
            msgArea.scrollTo({ top: msgArea.scrollHeight, behavior: 'smooth' });
        } else {
            msgArea.scrollTop = msgArea.scrollHeight;
        }
    }

    function handleScroll() {
        var el = document.getElementById('chat-messages');
        var btn = document.getElementById('chat-scroll-bottom');
        if (!el || !btn) return;
        var dist = el.scrollHeight - el.scrollTop - el.clientHeight;
        if (dist > 120) { btn.classList.add('visible'); }
        else { btn.classList.remove('visible'); }
    }

    // ── Send Message ──
    function sendMessage() {
        var input = document.getElementById('chat-input-text');
        if (!input) return;
        var text = input.value.trim();
        if (!text) return;
        if (!state.activeThread) return;

        var port = null;
        var phone = '';
        var senderLid = '';
        for (var i = 0; i < state.lines.length; i++) {
            if (state.lines[i].last9 === state.activeLine) {
                port = state.lines[i].port;
                break;
            }
        }
        var convs = state.conversations[state.activeLine] || [];
        for (var j = 0; j < convs.length; j++) {
            if (convs[j].thread_id === state.activeThread) {
                phone = convs[j].phone || '';
                senderLid = convs[j].sender_lid || '';
                break;
            }
        }

        var chatId = phone.replace(/[^0-9]/g, '') + '@c.us';
        var lidChatId = senderLid ? senderLid.replace(/[^0-9]/g, '') + '@lid' : '';

        // Optimistic UI
        var msgArea = document.getElementById('chat-messages');
        if (msgArea) {
            var placeholder = msgArea.querySelector('.chat-placeholder, .chat-empty');
            if (placeholder) msgArea.innerHTML = '';

            var now = new Date().toISOString();
            var time = formatTime(now);
            var bubbleHtml = '<div class="chat-msg bot" style="animation:none"><div class="bubble">' +
                '<div class="msg-body">' + esc(text) + '</div>' +
                '<div class="msg-time">' + esc(time) + '<span class="msg-checks">✓</span></div>' +
            '</div></div>';
            msgArea.insertAdjacentHTML('beforeend', bubbleHtml);
            scrollToBottom(true);
        }

        input.value = '';
        autoResizeTextarea(input);

        if (port && chatId) {
            var fd = new FormData();
            fd.append('port', port);
            fd.append('chat_id', chatId);
            if (lidChatId) fd.append('lid_chat_id', lidChatId);
            fd.append('text', text);
            fd.append('csrf_token', _csrf);

            var sendBtn = document.getElementById('chat-send-btn');
            if (sendBtn) sendBtn.disabled = true;
            setTimeout(function() { if (sendBtn) sendBtn.disabled = false; }, 400);

            _fetch(apiUrl('api/mensajes.php?action=send_manual'), { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.ok) {
                        var checks = msgArea.querySelectorAll('.chat-msg.bot .msg-checks');
                        if (checks.length > 0) {
                            var seenOk = (d.seen_ok !== false);
                            checks[checks.length - 1].textContent = seenOk ? '✓✓' : '✓⚠';
                        }
                        markRead(state.activeThread);
                    } else {
                        msgArea.insertAdjacentHTML('beforeend',
                            '<div class="chat-msg-system"><div class="system-text">❌ No se pudo enviar: ' + esc(d.error || 'Error') + '</div></div>');
                        scrollToBottom(true);
                    }
                }).catch(function() {
                    msgArea.insertAdjacentHTML('beforeend',
                        '<div class="chat-msg-system"><div class="system-text">❌ Error de conexión</div></div>');
                    scrollToBottom(true);
                });
        }
    }

    function handleInputKey(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    }

    function autoResizeTextarea(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 120) + 'px';
    }

    // ── Emoji Picker ──
    function toggleEmojiPicker() {
        var picker = document.getElementById('chat-emoji-picker');
        if (!picker) return;
        if (picker.classList.contains('show')) {
            picker.classList.remove('show');
        } else {
            picker.classList.add('show');
            setTimeout(function() {
                document.addEventListener('click', closeEmojiOnOutside);
            }, 10);
        }
    }

    function closeEmojiOnOutside(e) {
        var picker = document.getElementById('chat-emoji-picker');
        var btn = document.getElementById('chat-emoji-btn');
        if (!picker) { document.removeEventListener('click', closeEmojiOnOutside); return; }
        if (!picker.contains(e.target) && e.target !== btn) {
            picker.classList.remove('show');
            document.removeEventListener('click', closeEmojiOnOutside);
        }
    }

    function insertEmoji(emoji) {
        var input = document.getElementById('chat-input-text');
        if (!input) return;
        var start = input.selectionStart;
        var end = input.selectionEnd;
        input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
        input.selectionStart = input.selectionEnd = start + emoji.length;
        input.focus();
        autoResizeTextarea(input);
        var picker = document.getElementById('chat-emoji-picker');
        if (picker) picker.classList.remove('show');
    }

    function populateEmojiPicker() {
        var picker = document.getElementById('chat-emoji-picker');
        if (!picker) return;
        var html = '';
        for (var i = 0; i < EMOJIS.length; i++) {
            html += '<button type="button" onclick="ChatOperator.insertEmoji(\'' + EMOJIS[i] + '\')">' + EMOJIS[i] + '</button>';
        }
        picker.innerHTML = html;
    }

    // ── Bot Pause per conversation ──
    function toggleConvPause() {
        if (!state.activeThread) return;
        var current = state.convPause[state.activeThread] || false;
        var newPause = !current;
        state.convPause[state.activeThread] = newPause;
        updatePauseUI();

        // Remove typing indicator on pause
        if (newPause) {
            var typingEl = document.querySelector('#chat-messages .chat-typing');
            if (typingEl) typingEl.remove();
        }

        var fd = new FormData();
        fd.append('thread_id', state.activeThread);
        fd.append('pause_action', newPause ? 'pause' : 'resume');
        fd.append('csrf_token', _csrf);

        _fetch(apiUrl('api/mensajes.php?action=toggle_pause'), { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) {
                    state.convPause[state.activeThread] = current;
                    updatePauseUI();
                } else {
                    addSystemMessage(newPause ? '⏸ Bot pausado en esta conversación' : '▶ Bot reanudado en esta conversación');
                    state.lastMsgCount = 0;
                    refreshMessages();
                }
            }).catch(function() {
                state.convPause[state.activeThread] = current;
                updatePauseUI();
            });
    }

    function updatePauseUI() {
        var toggleEl = document.getElementById('chat-conv-pause-toggle');
        if (!toggleEl) return;

        var isPaused = state.convPause[state.activeThread] || false;
        if (!state.activeThread) {
            toggleEl.style.display = 'none';
            return;
        }
        toggleEl.style.display = 'flex';
        toggleEl.className = 'conv-bot-toggle' + (isPaused ? ' paused' : '');
        var label = toggleEl.querySelector('span:first-child');
        if (label) label.textContent = isPaused ? '⏸ Pausado' : '🤖 Auto';
        var pill = toggleEl.querySelector('.pause-pill');
        if (!pill) {
            pill = document.createElement('span');
            pill.className = 'pause-pill';
            toggleEl.appendChild(pill);
        }
    }

    // ── Photo Picker (image attachment from girls catalog) ──

    function openPhotoPicker() {
        if (!state.activeThread) return;
        if (state.photoPicker.overlay) closePhotoPicker();
        state.photoPicker.girls = [];
        state.photoPicker.selected = [];
        createPhotoPickerOverlay();

        _fetch(apiUrl('api/girls.php?action=get_catalog'))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok || !d.girls) { state.photoPicker.girls = []; renderPhotoGrid([]); return; }
            state.photoPicker.girls = d.girls;
            renderPhotoGrid(d.girls);
        }).catch(function() {
            state.photoPicker.girls = [];
            renderPhotoGrid([]);
        });
    }

    function closePhotoPicker() {
        var pp = state.photoPicker;
        if (pp.overlay) { pp.overlay.remove(); pp.overlay = null; }
        pp.girls = [];
        pp.selected = [];
    }

    function createPhotoPickerOverlay() {
        var pp = state.photoPicker;
        if (pp.overlay) return;

        var overlay = document.createElement('div');
        overlay.className = 'photo-picker-overlay';
        overlay.onclick = function(e) { if (e.target === overlay) closePhotoPicker(); };

        var container = document.createElement('div');
        container.className = 'photo-picker-container';

        var header = document.createElement('div');
        header.className = 'photo-picker-header';
        header.innerHTML = '<h3>Selecciona fotos</h3>' +
            '<button class="photo-picker-close" title="Cerrar" onclick="ChatOperator.closePhotoPicker()">✕</button>';
        container.appendChild(header);

        var grid = document.createElement('div');
        grid.className = 'photo-picker-grid';
        grid.id = 'photo-picker-grid';
        grid.innerHTML = '<div class="photo-picker-loading"><div class="spinner"></div><span>Cargando fotos...</span></div>';
        container.appendChild(grid);

        var footer = document.createElement('div');
        footer.className = 'photo-picker-footer';
        footer.id = 'photo-picker-footer';
        footer.innerHTML =
            '<span class="photo-picker-count" id="photo-picker-count">0 seleccionadas</span>' +
            '<div class="photo-picker-actions">' +
                '<button class="photo-picker-btn photo-picker-cancel" onclick="ChatOperator.closePhotoPicker()">Cancelar</button>' +
                '<button class="photo-picker-btn photo-picker-accept" id="photo-picker-accept" disabled onclick="ChatOperator.sendImages()">Enviar</button>' +
            '</div>';
        container.appendChild(footer);

        overlay.appendChild(container);
        document.body.appendChild(overlay);
        pp.overlay = overlay;

        overlay.addEventListener('keydown', function(e) { if (e.key === 'Escape') closePhotoPicker(); });
    }

    function getDirectImageUrl(url) {
        if (!url) return '';
        var m = url.match(/^https?:\/\/(?:[^\/]*\.)?compartir\.site\/([a-z0-9]+)\/?$/i);
        if (m) return 'https://compartir.site/' + m[1] + '/' + m[1] + '.jpg';
        if (/\/api\/image-proxy\.php/i.test(url) && _apiToken) {
            var sep = url.indexOf('?') === -1 ? '?' : '&';
            return url + sep + 'token=' + encodeURIComponent(_apiToken);
        }
        return url;
    }

    function renderPhotoGrid(girls) {
        var grid = document.getElementById('photo-picker-grid');
        if (!grid) return;

        var photos = [];
        for (var gi = 0; gi < girls.length; gi++) {
            var g = girls[gi];
            var fotos = g.fotos || [];
            for (var fi = 0; fi < fotos.length; fi++) {
                photos.push({ url: fotos[fi], girlName: g.nombre || '', girlId: g.id || '' });
            }
        }

        if (photos.length === 0) {
            grid.innerHTML = '<div class="photo-picker-empty">No hay fotos disponibles.<br><span style="font-size:.75rem;color:var(--wa-muted,#8696a0)">Añade chicas activas con fotos primero.</span></div>';
            return;
        }

        var html = '';
        for (var i = 0; i < photos.length; i++) {
            var p = photos[i];
            var isSel = state.photoPicker.selected.indexOf(p.url) !== -1;
            html += '<div class="photo-picker-thumb' + (isSel ? ' selected' : '') + '" ' +
                'data-url="' + esc(p.url) + '" title="' + esc(p.girlName) + '" ' +
                'onclick="ChatOperator.togglePhotoSelection(this)" ' +
                'style="background-image:url(\'' + esc(getDirectImageUrl(p.url)) + '\')"></div>';
        }
        grid.innerHTML = html;
        updatePhotoPickerFooter();
    }

    function togglePhotoSelection(el) {
        var url = el.getAttribute('data-url');
        if (!url) return;
        var idx = state.photoPicker.selected.indexOf(url);
        if (idx !== -1) { state.photoPicker.selected.splice(idx, 1); el.classList.remove('selected'); }
        else { state.photoPicker.selected.push(url); el.classList.add('selected'); }
        updatePhotoPickerFooter();
    }

    function updatePhotoPickerFooter() {
        var countEl = document.getElementById('photo-picker-count');
        var acceptBtn = document.getElementById('photo-picker-accept');
        var count = state.photoPicker.selected.length;
        if (countEl) countEl.textContent = count + ' seleccionada' + (count !== 1 ? 's' : '');
        if (acceptBtn) acceptBtn.disabled = count === 0;
    }

    function sendImages() {
        var urls = state.photoPicker.selected.slice();
        if (urls.length === 0) return;
        if (!state.activeThread) return;

        var port = null, phone = '', senderLid = '';
        for (var i = 0; i < state.lines.length; i++) {
            if (state.lines[i].last9 === state.activeLine) { port = state.lines[i].port; break; }
        }
        var convs = state.conversations[state.activeLine] || [];
        for (var j = 0; j < convs.length; j++) {
            if (convs[j].thread_id === state.activeThread) {
                phone = convs[j].phone || '';
                senderLid = convs[j].sender_lid || '';
                break;
            }
        }
        var chatId = phone.replace(/[^0-9]/g, '') + '@c.us';
        var lidChatId = senderLid ? senderLid.replace(/[^0-9]/g, '') + '@lid' : '';

        if (!port || !chatId) return;

        var acceptBtn = document.getElementById('photo-picker-accept');
        if (acceptBtn) acceptBtn.disabled = true;
        closePhotoPicker();

        var msgArea = document.getElementById('chat-messages');
        var sendIndex = 0;

        function sendNext() {
            if (sendIndex >= urls.length) return;
            var text = urls[sendIndex];

            if (msgArea) {
                var placeholder = msgArea.querySelector('.chat-placeholder, .chat-empty');
                if (placeholder) msgArea.innerHTML = '';
                var now = new Date().toISOString();
                var bubbleHtml = '<div class="chat-msg bot" style="animation:none"><div class="bubble">' +
                    '<div class="msg-body">' + formatMessageBody(text) + '</div>' +
                    '<div class="msg-time">' + esc(formatTime(now)) + '<span class="msg-checks">✓</span></div>' +
                '</div></div>';
                msgArea.insertAdjacentHTML('beforeend', bubbleHtml);
                scrollToBottom(true);
            }

            var fd = new FormData();
            fd.append('port', port);
            fd.append('chat_id', chatId);
            if (lidChatId) fd.append('lid_chat_id', lidChatId);
            fd.append('text', text);
            fd.append('csrf_token', _csrf);

            _fetch(apiUrl('api/mensajes.php?action=send_manual'), { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.ok && msgArea) {
                        var checks = msgArea.querySelectorAll('.chat-msg.bot .msg-checks');
                        if (checks.length > 0) checks[checks.length - 1].textContent = d.seen_ok !== false ? '✓✓' : '✓⚠';
                    }
                }).catch(function(){});
            sendIndex++;
            if (sendIndex < urls.length) setTimeout(sendNext, 2000 + Math.floor(Math.random() * 1000));
        }
        sendNext();
    }

    // ── Polling ──
    function startPolling() {
        stopPolling();
        state._pollTick = 0;
        state.pollInterval = setInterval(function() {
            state._pollTick++;

            if (state.activeThread) {
                refreshMessages();
            }

            if (state._pollTick % 4 === 0) {
                if (state.activeLine) refreshThreads();
                loadLinesSummary();
            }

            if (state._pollTick % 20 === 0) {
                loadLines();
            }
        }, 1500);
    }

    function stopPolling() {
        if (state.pollInterval) {
            clearInterval(state.pollInterval);
            state.pollInterval = null;
        }
    }

    function refreshMessages() {
        if (!state.activeThread) return;
        var threadId = state.activeThread;
        _fetch(apiUrl('api/mensajes.php?action=conversation&thread_id=' + encodeURIComponent(state.activeThread)))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) return;
                if (state.activeThread !== threadId) return;
                var conv = d.conversation || [];
                // Check if new user messages arrived (other party)
                var hadNewUserMsg = false;
                if (conv.length > state.lastMsgCount) {
                    for (var i = state.lastMsgCount; i < conv.length; i++) {
                        if ((conv[i].user_msg || '').trim()) { hadNewUserMsg = true; break; }
                    }
                }
                state.lastMsgCount = conv.length;
                renderMessages(conv);
                // Play notification if new user messages arrived
                if (hadNewUserMsg) playNotification();
            }).catch(function(){});
    }

    // ── Preload conversations for all lines ──
    function preloadAllConversations() {
        var pending = 0;
        for (var i = 0; i < state.lines.length; i++) {
            var last9 = state.lines[i].last9 || '';
            if (!last9) continue;
            if (state.conversations[last9]) continue; // already loaded
            pending++;
            _fetch(apiUrl('api/mensajes.php?action=threads&last9=' + encodeURIComponent(last9)))
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.ok && d.threads) {
                        // Restore correct last9 from threads data
                        for (var t = 0; t < d.threads.length; t++) {
                            var tid = d.threads[t].thread_id || '';
                            var sep = tid.indexOf('_');
                            var ln = sep !== -1 ? tid.substring(0, sep) : '';
                            if (ln && !state.conversations[ln]) state.conversations[ln] = [];
                            if (ln) state.conversations[ln].push(d.threads[t]);
                        }
                    }
                    pending--;
                    if (pending <= 0) renderLines();
                }).catch(function() {
                    pending--;
                    if (pending <= 0) renderLines();
                });
        }
        if (pending === 0) renderLines(); // no new lines to load
    }

    function refreshThreads() {
        if (!state.activeLine) return;
        _fetch(apiUrl('api/mensajes.php?action=threads&last9=' + encodeURIComponent(state.activeLine)))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.ok) return;
                // Preserve locally-cleared unread for recently-read threads
                var now = Date.now();
                for (var i = 0; i < d.threads.length; i++) {
                    var tid = d.threads[i].thread_id || '';
                    if (tid && state._readTimestamps[tid] && (now - state._readTimestamps[tid] < 20000)
                        && (d.threads[i].unread || 0) <= (state._readUnreadSnap[tid] || 0)) {
                        d.threads[i].unread = 0;
                    }
                }
                state.conversations[state.activeLine] = d.threads;
                renderLines();
            }).catch(function(){});
    }

    // ── Init ──
    function init() {
        populateEmojiPicker();

        // Sound toggle button in header
        initSound();

        // Unlock AudioContext on first user interaction (browser policy)
        document.addEventListener('click', function unlockAudio() {
            try {
                var ac = new (window.AudioContext || window.webkitAudioContext)();
                if (ac.state === 'suspended') ac.resume();
                ac.close();
            } catch(e) {}
            document.removeEventListener('click', unlockAudio);
        }, { once: true });

        // Scroll listener
        var msgArea = document.getElementById('chat-messages');
        if (msgArea) msgArea.addEventListener('scroll', handleScroll);

        // Search
        var searchInput = document.getElementById('search-input');
        if (searchInput) searchInput.addEventListener('input', filterConversations);

        // Load everything
        loadBotMode();
        loadLines();
        loadLinesSummary();
        loadPausedList();
        startPolling();

        // Sync state when tab becomes visible again
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                if (state.activeThread) refreshMessages();
                if (state.activeLine) { refreshThreads(); loadLinesSummary(); }
            }
        });

        // Preload conversations after a short delay (after lines are loaded)
        setTimeout(function() { preloadAllConversations(); }, 800);

        // Keyboard shortcut for Escape (back to sidebar on mobile)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && state.activeThread) {
                backToSidebar();
            }
        });
    }

    // ── Public API ──
    return {
        init: init,
        toggleLine: toggleLine,
        openConversation: openConversation,
        backToSidebar: backToSidebar,
        sendMessage: sendMessage,
        handleInputKey: handleInputKey,
        autoResizeTextarea: autoResizeTextarea,
        toggleEmojiPicker: toggleEmojiPicker,
        insertEmoji: insertEmoji,
        toggleBotMode: toggleBotMode,
        toggleConvPause: toggleConvPause,
        filterConversations: filterConversations,
        scrollToBottom: scrollToBottom,
        openPhotoPicker: openPhotoPicker,
        closePhotoPicker: closePhotoPicker,
        togglePhotoSelection: togglePhotoSelection,
        sendImages: sendImages,
        toggleSound: toggleSound,
        markAllRead: markAllRead,
    };

})();

// Auto-init on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    ChatOperator.init();
});
