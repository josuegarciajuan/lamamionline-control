/**
 * bot-casa WhatsApp Chat Interface — JavaScript
 *
 * Usage:
 *   ChatApp.open()   → opens the chat modal
 *   ChatApp.close()  → closes the chat modal
 *
 * Dependencies: _apiToken (global, set in panel.php / client.php)
 * API endpoints: api/lines.php, api/mensajes.php
 */

var ChatApp = (function() {

    // ── State ──
    var state = {
        lines: [],
        conversations: {},   // { lineLast9: [ {thread_id, phone, count, last_ts, last_msg, first_msg, unread} ] }
        activeLine: null,    // last9 of selected line
        activeThread: null,  // thread_id of open conversation
        convPause: {},       // { thread_id: bool } — bot paused per conversation (local + server state)
        isOpen: false,
        overlay: null,
        container: null,
        pollInterval: null,  // timer ID for auto-refresh
        lastMsgCount: 0,     // for detecting new messages
        linesSummary: {},    // { lineLast9: { total_convos, total_unread } } — badge data
        _readTimestamps: {}, // { thread_id: Date.now() } — local mark_read timestamps for race-condition protection
        _renderedCount: 0,   // how many messages are currently in the DOM (for incremental render)
        // Photo picker state
        photoPicker: {
            overlay: null,
            girls: [],       // loaded catalog from API
            selected: [],    // array of selected photo URLs
        },
    };

    // ── Emoji set ──
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
        d.textContent = s;
        return d.innerHTML;
    }

    /**
     * Check if a URL likely points to an image (for inline rendering).
     */
    function isImageUrl(url) {
        if (!url) return false;
        // Image extensions
        if (/\.(?:jpg|jpeg|png|webp|gif|bmp|svg)(?:\?|#|$)/i.test(url)) return true;
        // Image CDNs and hosts
        if (/\/\/(?:[^\/]*\.)?(?:imgur\.com|ibb\.co|i\.ibb\.co|cloudinary\.com|cloudfront\.net|amazonaws\.com)/i.test(url)) return true;
        // compartir.site shortlinks (resolve to .jpg)
        if (/\/\/(?:[^\/]*\.)?compartir\.site\//i.test(url)) return true;
        // Local image proxy
        if (/\/api\/image-proxy\.php/i.test(url)) return true;
        // Path segments suggesting images
        if (/\/(?:photo|image|img|pic|fotos?|girls?|photos?|images?)\//i.test(url)) return true;
        // Direct image query params
        if (/[?&](?:format|type|fm|output)=(?:jpg|jpeg|png|webp|gif)/i.test(url)) return true;
        return false;
    }

    /**
     * Format message body text with clickable links and inline images.
     * Detects URLs, renders image URLs as <img> with fallback, and other URLs as <a>.
     * All non-URL text is escaped safely.
     *
     * @param {string} text - The raw message text
     * @returns {string} HTML string safe for innerHTML
     */
    function formatMessageBody(text) {
        if (!text) return '';
        // Match https?:// URLs (stop at whitespace or common delimiters)
        var urlRegex = /(https?:\/\/[^\s<>"')\]}\u0000-\u001F\u007F-\u009F]+)/gi;
        var parts = text.split(urlRegex);
        var result = '';
        for (var i = 0; i < parts.length; i++) {
            var part = parts[i];
            if (!part) continue;
            // Check if this part is a URL
            if (/^https?:\/\//i.test(part)) {
                // Clean trailing punctuation from URL (.,;:!? that aren't part of the URL)
                var cleanUrl = part.replace(/[.,;:!?]+$/g, '');
                var trailing = part.slice(cleanUrl.length);
                if (isImageUrl(cleanUrl)) {
                    // Resolve direct image URL for compartir.site links
                    var imgSrc = getDirectImageUrl(cleanUrl);
                    result += '<img class="chat-img" src="' + esc(imgSrc) + '" ' +
                        'alt="Imagen" loading="lazy" ' +
                        'onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'inline\';" ' +
                        'onclick="window.open(\'' + esc(cleanUrl) + '\',\'_blank\')" ' +
                        'title="Click para abrir enlace original">' +
                        '<a class="chat-img-fallback" href="' + esc(cleanUrl) + '" ' +
                        'target="_blank" rel="noopener" ' +
                        'style="display:none">' + esc(cleanUrl) + '</a>';
                } else {
                    // Regular link
                    result += '<a class="chat-link" href="' + esc(cleanUrl) + '" target="_blank" rel="noopener">' + esc(cleanUrl) + '</a>';
                }
                result += esc(trailing);
            } else {
                // Plain text — escape it
                result += esc(part);
            }
        }
        return result;
    }

    /**
     * Render de media recibida (imagen/audio/vídeo) + transcripción en cursiva.
     * Usa media_proxy.php para servir la media de Evolution (MinIO).
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

    /**
     * Send a message to the parent window if we're inside an iframe.
     * The CRM's views.php listens for 'chatOpened'/'chatClosed' to make
     * the iframe fullscreen on mobile so the chat overlay fills 100% of the screen.
     */
    function notifyParent(action) {
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ botcasa: action }, '*');
            }
        } catch (e) {}
    }

    function formatPhone(phone) {
        if (!phone) return 'Desconocido';
        var d = phone.replace(/[^0-9]/g, '');
        if (d.length === 9) return d.slice(0,3) + ' ' + d.slice(3,5) + ' ' + d.slice(5,7) + ' ' + d.slice(7,9);
        if (d.length === 11) return d.slice(0,2) + ' ' + d.slice(2,5) + ' ' + d.slice(5,8) + ' ' + d.slice(8,11);
        if (d.length === 12) return d.slice(0,2) + ' ' + d.slice(2,5) + ' ' + d.slice(5,9);
        return phone;
    }

    function formatTime(ts) {
        if (!ts) return '';
        try {
            var d = new Date(ts);
            if (isNaN(d.getTime())) return '';
            var now = new Date();
            var diffMs = now - d;
            var diffHours = diffMs / 3600000;

            // Recent (<2h): show time only
            if (diffHours < 2) {
                return d.toLocaleTimeString('es-ES', {hour:'2-digit', minute:'2-digit'});
            }

            // Older: show date + time to avoid confusion
            var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            var msgDay = new Date(d.getFullYear(), d.getMonth(), d.getDate());
            var dayDiff = Math.round((today - msgDay) / 86400000);

            var timePart = d.toLocaleTimeString('es-ES', {hour:'2-digit', minute:'2-digit'});
            if (dayDiff === 0) return timePart;                          // hoy → "11:42"
            if (dayDiff === 1) return 'Ayer ' + timePart;                // ayer → "Ayer 11:42"
            return d.toLocaleDateString('es-ES', {day:'2-digit', month:'2-digit'}) + ' ' + timePart; // "25/06 11:42"
        } catch(e) {
            return '';
        }
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
        } catch(e) {
            return '';
        }
    }

    function dateKey(ts) {
        if (!ts) return '';
        try {
            var d = new Date(ts);
            if (isNaN(d.getTime())) return '';
            return d.toISOString().slice(0,10);
        } catch(e) {
            return '';
        }
    }

    function getInitial(phone) {
        if (!phone) return '?';
        var d = phone.replace(/[^0-9a-zA-Z]/g, '');
        return d.charAt(d.length - 1).toUpperCase();
    }

    function apiUrl(url) {
        var sep = url.indexOf('?') === -1 ? '?' : '&';
        return url + sep + 'token=' + encodeURIComponent((typeof _apiToken !== 'undefined' ? _apiToken : ''));
    }

    // ── CSRF Token Refresh (prevents 403 after ~20 min idle) ──
    var _tokenRefreshPromise = null;

    function refreshToken() {
        if (_tokenRefreshPromise) return _tokenRefreshPromise;
        _tokenRefreshPromise = fetch('api/csrf-token.php')
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok && d.token) {
                    if (typeof _apiToken !== 'undefined') _apiToken = d.token;
                    if (typeof _csrf !== 'undefined') _csrf = d.token;
                    // Also update hidden CSRF inputs for form-based (non-AJAX) submissions
                    if (typeof updateAllCsrfInputs === 'function') updateAllCsrfInputs(d.token);
                }
                _tokenRefreshPromise = null;
            })
            .catch(function() {
                _tokenRefreshPromise = null;
            });
        return _tokenRefreshPromise;
    }

    /**
     * Safe fetch wrapper: auto-refreshes CSRF token on 403 responses
     * and retries the request once with the fresh token.
     *
     * Usage: _fetch(url, init) — drop-in replacement for fetch(url, init).
     */
    function _fetch(url, init) {
        init = init || {};
        return fetch(url, init).then(function(r) {
            if (r.status !== 403) return r;
            // Token expired — refresh and retry once
            return refreshToken().then(function() {
                var newUrl = url;
                var newInit = init;
                if (!init.method || init.method === 'GET') {
                    // Rebuild URL: strip old token, add fresh one
                    newUrl = url.replace(/[?&]token=[^&#]*/g, '');
                    newUrl = apiUrl(newUrl);
                } else if (init.body instanceof FormData) {
                    // Rebuild FormData with fresh csrf_token
                    newInit = Object.assign({}, init);
                    var newFd = new FormData();
                    try {
                        init.body.forEach(function(v, k) {
                            if (k !== 'csrf_token') newFd.append(k, v);
                        });
                    } catch(e) {}
                    newFd.append('csrf_token', (typeof _csrf !== 'undefined' ? _csrf : ''));
                    newInit.body = newFd;
                }
                return fetch(newUrl, newInit);
            }).catch(function() {
                // CSRF expired beyond recovery — notify parent to reload iframe
                notifyParent('reloadIframe');
                return new Response('{"ok":false,"error":"CSRF expired"}', {status:403, headers:{'Content-Type':'application/json'}});
            });
        });
    }

    // Proactive refresh every 4 minutes (token window is 10+10=20 min)
    var _tokenRefreshTimer = setInterval(refreshToken, 240000);
    // Also refresh on first open (covers iframe that was loaded >4 min ago)
    refreshToken();

    // ── DOM Creation ──

    function createOverlay() {
        if (state.overlay) return;

        var overlay = document.createElement('div');
        overlay.className = 'chat-overlay';
        overlay.onclick = function(e) {
            if (e.target === overlay) close();
        };

        var container = document.createElement('div');
        container.className = 'chat-container';

        // Sidebar
        var sidebar = document.createElement('div');
        sidebar.className = 'chat-sidebar';
        sidebar.id = 'chat-sidebar';

        var sidebarHeader = document.createElement('div');
        sidebarHeader.className = 'chat-sidebar-header';
        sidebarHeader.innerHTML =
            '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">' +
                '<h3 style="margin-bottom:0;display:flex;align-items:center;gap:6px">💬 Conversaciones' +
                    '<span class="tooltip-wrap" style="display:inline-flex;position:relative">' +
                        '<span class="tooltip-icon" style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:var(--info);color:#fff;font-size:.7rem;font-weight:700;cursor:help;line-height:1">?</span>' +
                        '<span class="tooltip-box" style="display:none;position:absolute;z-index:100;top:100%;left:0;margin-top:6px;background:var(--panel);border:1px solid var(--accent);border-radius:10px;padding:10px 14px;font-size:.75rem;color:var(--text);max-width:280px;box-shadow:0 10px 30px rgba(0,0,0,.6);line-height:1.55;white-space:normal;font-weight:400">' +
                        'Aquí aparecen todas las conversaciones de WhatsApp. Puedes:<br>• <strong>Ver en directo</strong> cómo responde el bot<br>• <strong>Pausar el bot</strong> en una conversación concreta<br>• <strong>Contestar tú</strong> manualmente<br>• <strong>Reactivar el bot</strong> cuando quieras<br>• <strong>Control total</strong> de tu WhatsApp</span>' +
                    '</span>' +
                '</h3>' +
                '<button class="chat-sidebar-close" title="Cerrar chat" onclick="ChatApp.close()">✕</button>' +
            '</div>' +
            '<div class="chat-search-wrap"><input type="text" class="chat-search" id="chat-search" placeholder="Buscar por teléfono..." oninput="ChatApp.filter()"></div>';
        sidebar.appendChild(sidebarHeader);

        var linesList = document.createElement('div');
        linesList.className = 'chat-lines-list';
        linesList.id = 'chat-lines-list';
        sidebar.appendChild(linesList);

        container.appendChild(sidebar);

        // ── Tooltip toggle for the "?" icon next to "Conversaciones" ──
        (function() {
            var tooltipWrap = sidebar.querySelector('.tooltip-wrap');
            var tooltipIcon = sidebar.querySelector('.tooltip-icon');
            var tooltipBox = sidebar.querySelector('.tooltip-box');
            if (tooltipIcon && tooltipBox) {
                tooltipIcon.addEventListener('click', function(e) {
                    e.stopPropagation();
                    var isVisible = tooltipBox.style.display === 'block';
                    tooltipBox.style.display = isVisible ? 'none' : 'block';
                });
                // Close on click outside
                document.addEventListener('click', function(e) {
                    if (tooltipWrap && !tooltipWrap.contains(e.target)) {
                        tooltipBox.style.display = 'none';
                    }
                });
            }
        })();

        // Main chat area
        var main = document.createElement('div');
        main.className = 'chat-main';
        main.id = 'chat-main';

        // Header
        var header = document.createElement('div');
        header.className = 'chat-header';
        header.id = 'chat-header';
        header.innerHTML =
            '<button class="chat-mobile-back" id="chat-mobile-back" onclick="ChatApp.showSidebar()" title="Volver">←</button>' +
            '<div class="chat-header-avatar" id="chat-header-avatar">?</div>' +
            '<div class="chat-header-info">' +
                '<div class="chat-header-name" id="chat-header-name">Selecciona una conversación</div>' +
                '<div class="chat-header-subtitle" id="chat-header-subtitle"></div>' +
            '</div>' +
            '<div class="chat-header-actions">' +
                '<div class="chat-pause-toggle" id="chat-pause-toggle" onclick="ChatApp.toggleBotPause()" title="Pausar/Reanudar bot para esta conversación">' +
                    '<span style="font-size:.85rem">🤖</span>' +
                    '<span id="chat-pause-label">Auto</span>' +
                    '<span class="pause-pill"></span>' +
                '</div>' +
                '<button class="chat-header-btn close-btn" title="Cerrar chat" onclick="ChatApp.close()">✕</button>' +
            '</div>';
        main.appendChild(header);

        // Messages area
        var messages = document.createElement('div');
        messages.className = 'chat-messages';
        messages.id = 'chat-messages';
        messages.innerHTML =
            '<div class="chat-placeholder">' +
                '<div class="chat-placeholder-icon">💬</div>' +
                '<h3>Selecciona una conversación</h3>' +
                '<p>Elige una línea y una conversación en el panel izquierdo para ver los mensajes.</p>' +
            '</div>';
        main.appendChild(messages);

        // Scroll-to-bottom button
        var scrollBtn = document.createElement('button');
        scrollBtn.className = 'chat-scroll-bottom';
        scrollBtn.id = 'chat-scroll-bottom';
        scrollBtn.innerHTML = '↓';
        scrollBtn.onclick = function() { ChatApp.scrollToBottom(true); };
        main.appendChild(scrollBtn);

        // Input area
        var inputArea = document.createElement('div');
        inputArea.className = 'chat-input-area';
        inputArea.id = 'chat-input-area';
        inputArea.innerHTML =
            '<div class="chat-input-row" style="position:relative">' +
                '<button class="chat-input-btn" id="chat-emoji-btn" title="Emojis" onclick="ChatApp.toggleEmojiPicker()">😊</button>' +
                '<textarea id="chat-input-text" placeholder="Escribe un mensaje..." rows="1" oninput="ChatApp.autoResizeTextarea(this)" onkeydown="ChatApp.handleInputKey(event)"></textarea>' +
                '<button class="chat-input-btn chat-attach-btn" id="chat-attach-btn" title="Adjuntar imágenes" onclick="ChatApp.openPhotoPicker()">📎</button>' +
                '<button class="chat-input-send" id="chat-send-btn" title="Enviar" onclick="ChatApp.sendMessage()">▶</button>' +
                '<div class="chat-emoji-picker" id="chat-emoji-picker"></div>' +
            '</div>' +
            '<div class="chat-paused-hint" id="chat-paused-hint">⚠️ Bot pausado. Tu respuesta se enviará manualmente.</div>';
        main.appendChild(inputArea);

        container.appendChild(main);
        overlay.appendChild(container);
        document.body.appendChild(overlay);

        state.overlay = overlay;
        state.container = container;

        // Populate emoji picker
        populateEmojiPicker();

        // Scroll listener for the scroll-to-bottom button
        var msgArea = document.getElementById('chat-messages');
        msgArea.addEventListener('scroll', handleScroll);

        // Escape key to close
        document.addEventListener('keydown', handleGlobalKey);
    }

    function populateEmojiPicker() {
        var picker = document.getElementById('chat-emoji-picker');
        if (!picker) return;
        var html = '';
        for (var i = 0; i < EMOJIS.length; i++) {
            html += '<button type="button" onclick="ChatApp.insertEmoji(\'' + EMOJIS[i] + '\')">' + EMOJIS[i] + '</button>';
        }
        picker.innerHTML = html;
    }

    function destroyOverlay() {
        if (state.overlay) {
            document.removeEventListener('keydown', handleGlobalKey);
            state.overlay.remove();
            state.overlay = null;
            state.container = null;
        }
    }

    function handleGlobalKey(e) {
        if (e.key === 'Escape' && state.isOpen) {
            close();
        }
    }

    function handleScroll() {
        var el = document.getElementById('chat-messages');
        var btn = document.getElementById('chat-scroll-bottom');
        if (!el || !btn) return;
        var distFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
        if (distFromBottom > 120) {
            btn.classList.add('visible');
        } else {
            btn.classList.remove('visible');
        }
    }

    // ── Public API ──

    function open() {
        if (state.isOpen) return;
        state.isOpen = true;
        createOverlay();
        initMobile();
        document.body.style.overflow = 'hidden';
        loadLines();
        loadLinesSummary();
        loadPausedList();
        startPolling();
        // Notify parent frame to go fullscreen (CRM iframe integration)
        notifyParent('chatOpened');
    }

    function close() {
        if (!state.isOpen) return;
        state.isOpen = false;
        stopPolling();
        var overlay = state.overlay;
        if (overlay) {
            overlay.classList.add('closing');
            setTimeout(function() {
                destroyOverlay();
                document.body.style.overflow = '';
            }, 180);
        } else {
            destroyOverlay();
            document.body.style.overflow = '';
        }
        // Notify parent frame to restore size
        notifyParent('chatClosed');
    }

    // ── Data Loading ──

    function loadLines() {
        var listEl = document.getElementById('chat-lines-list');
        if (!listEl) return;
        listEl.innerHTML = '<div class="chat-loading"><div class="spinner"></div><div>Cargando líneas...</div></div>';

        _fetch(apiUrl('api/lines.php?action=list'))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok || !d.lines) {
                listEl.innerHTML = '<div class="chat-error">Error al cargar líneas</div>';
                return;
            }
            state.lines = d.lines;
            renderLineList();

            // Load conversations lazily: only when user expands a line.
            // No auto-expand — chat opens clean, no conversation preselected.

            // Also fetch WAHA statuses for live indicators
            _fetch(apiUrl('api/lines.php?action=status'))
            .then(function(r2) { return r2.json(); })
            .then(function(d2) {
                if (d2.ok && d2.statuses) {
                    for (var i = 0; i < state.lines.length; i++) {
                        var line = state.lines[i];
                        var st = d2.statuses[line.id];
                        if (st) {
                            line.live_status = st;
                        }
                    }
                    renderLineList();
                }
            }).catch(function(){});
        }).catch(function() {
            listEl.innerHTML = '<div class="chat-error">Error de conexión</div>';
        });
    }

    function loadLinesSummary() {
        _fetch(apiUrl('api/mensajes.php?action=threads_summary'))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok && d.summary) {
                state.linesSummary = d.summary;
                renderLineList();
            }
        }).catch(function(){});
    }

    function markRead(threadId, retries) {
        retries = retries || 0;
        var fd = new FormData();
        fd.append('thread_id', threadId);
        fd.append('csrf_token', (typeof _csrf !== 'undefined' ? _csrf : ''));
        _fetch(apiUrl('api/mensajes.php?action=mark_read'), {
            method: 'POST',
            body: fd
        }).then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) {
                // Ensure readTimestamp is set (defensive; already set in openConversation)
                if (!state._readTimestamps[threadId]) {
                    state._readTimestamps[threadId] = Date.now();
                }
                // Confirm line summary badges with server
                refreshLinesSummary();
            } else if (retries < 2) {
                // Server error — retry after short delay
                setTimeout(function() { markRead(threadId, retries + 1); }, 2000);
            } else {
                console.warn('ChatApp: markRead failed after retries', threadId, d.error);
            }
        }).catch(function() {
            if (retries < 2) {
                // Network error — retry after short delay
                setTimeout(function() { markRead(threadId, retries + 1); }, 2000);
            } else {
                console.warn('ChatApp: markRead network error after retries', threadId);
            }
        });
    }

    function refreshLinesSummary(last9) {
        var url = 'api/mensajes.php?action=threads_summary';
        if (last9) url += '&last9=' + encodeURIComponent(last9);
        _fetch(apiUrl(url))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok && d.summary) {
                // ── Race-condition guard for line-level badges ──
                // If threads were recently marked read locally (via markRead),
                // the server may still report stale total_unread before
                // read_status.json is persisted. Count how many threads were
                // recently read per line and subtract from server values.
                var now = Date.now();
                var readByLine = {};
                for (var tid in state._readTimestamps) {
                    if (state._readTimestamps.hasOwnProperty(tid)) {
                        if (now - state._readTimestamps[tid] < 15000) {
                            var pos = tid.indexOf('_');
                            var lineId = (pos !== -1) ? tid.substring(0, pos) : '';
                            if (lineId) {
                                readByLine[lineId] = (readByLine[lineId] || 0) + 1;
                            }
                        }
                    }
                }

                // Merge with existing summary, protecting against stale data
                for (var k in d.summary) {
                    if (d.summary.hasOwnProperty(k)) {
                        var svrUnread = d.summary[k].total_unread || 0;
                        var locallyRead = readByLine[k] || 0;
                        // Subtract recently-read threads from server count
                        // instead of all-or-nothing clamp
                        if (locallyRead > 0 && svrUnread > 0) {
                            d.summary[k].total_unread = Math.max(0, svrUnread - locallyRead);
                        }
                        state.linesSummary[k] = d.summary[k];
                    }
                }
                renderLineList();
            }
        }).catch(function(){});
    }

    function renderLineList() {
        var listEl = document.getElementById('chat-lines-list');
        if (!listEl) return;

        if (state.lines.length === 0) {
            var hint = (typeof _isAdminPanel !== 'undefined' && _isAdminPanel)
                ? 'Configura líneas en la pestaña Config → Routing.'
                : 'Ve a la pestaña Líneas para añadir una.';
            listEl.innerHTML = '<div class="chat-no-convs"><div class="no-convs-icon">📱</div>No hay líneas WhatsApp configuradas.<br><span style="font-size:.7rem">' + hint + '</span></div>';
            return;
        }

        var html = '';
        for (var i = 0; i < state.lines.length; i++) {
            var line = state.lines[i];
            var last9 = line.last9 || '';
            var label = (typeof _isAdminPanel !== 'undefined' && _isAdminPanel)
                ? (line.descripcion || line.label || ('Línea ' + line.id))
                : (line.label || ('Línea ' + line.id));
            var phone = line.health_phone || line.phone || last9;
            var liveSt = line.live_status || line.health_status || 'unknown';

            // Map status to CSS class
            var dotClass = 'unknown';
            var statusLabel = '';
            if (liveSt === 'WORKING') { dotClass = 'online'; statusLabel = 'Online'; }
            else if (liveSt === 'STARTING' || liveSt === 'starting' || liveSt === 'SCAN_QR') { dotClass = 'starting'; statusLabel = 'Conectando'; }
            else if (liveSt === 'STOPPED' || liveSt === 'down' || liveSt === 'FAILED') { dotClass = 'offline'; statusLabel = 'Offline'; }
            else if (liveSt === 'error') { dotClass = 'offline'; statusLabel = 'Error'; }

            var isExpanded = state.activeLine === last9;
            var convCount = (state.conversations[last9] || []).length;
            var summary = state.linesSummary[last9] || {};
            var totalConvos = summary.total_convos || convCount;
            var totalUnread = summary.total_unread || 0;
            var badgeHtml = '';
            if (totalUnread > 0) {
                badgeHtml += '<span class="chat-line-badge-unread">' + totalUnread + '</span>';
            }
            if (totalConvos > 0) {
                badgeHtml += '<span class="chat-line-badge-total">' + totalConvos + '</span>';
            }
            if (badgeHtml !== '') {
                badgeHtml = '<span class="chat-line-badges">' + badgeHtml + '</span>';
            }

            // ── Timestamp for this line: max last_ts across its conversations ──
            var convsForLineTs = state.conversations[last9] || [];
            var lineLastTs = '';
            for (var ci = 0; ci < convsForLineTs.length; ci++) {
                var cts = convsForLineTs[ci].last_ts || '';
                if (cts > lineLastTs) lineLastTs = cts;
            }
            var lineTime = formatTime(lineLastTs);

            html += '<div class="chat-line-row' + (isExpanded ? ' expanded' : '') + '" data-line="' + esc(last9) + '" onclick="ChatApp.toggleLine(\'' + esc(last9) + '\')">' +
                '<span class="chat-line-dot ' + dotClass + '" title="' + esc(statusLabel || liveSt) + '"></span>' +
                '<div class="chat-line-info">' +
                    '<div class="chat-line-name">' + esc(label) +
                        (lineTime ? '<span class="chat-line-time">' + esc(lineTime) + '</span>' : '') +
                    '</div>' +
                    '<div class="chat-line-phone">' + esc(formatPhone(phone)) + '</div>' +
                '</div>' +
                badgeHtml +
                '<span class="chat-line-chevron">▶</span>' +
            '</div>';

            // Conversations under this line
            html += '<div class="chat-line-conversations" id="convs-' + esc(last9) + '">';
            var convs = state.conversations[last9] || [];
            if (convs.length === 0) {
                html += '<div class="chat-no-convs" style="font-size:.73rem;padding:10px 16px 12px 32px">Sin conversaciones</div>';
            } else {
                for (var j = 0; j < convs.length; j++) {
                    var c = convs[j];
                    var isActiveConv = state.activeThread === c.thread_id;
                    var convPhone = c.phone || '';
                    var convName = formatPhone(convPhone);
                    var preview = c.last_msg || c.first_msg || '';
                    if (preview.length > 40) preview = preview.slice(0, 40) + '...';
                    var time = formatTime(c.last_ts);
                    var isPaused = state.convPause[c.thread_id];
                    // Forced fallback: show time or "--:--" so it's never invisible
                    var displayTime = time || '--:--';

                    html += '<div class="chat-conv-row' + (isActiveConv ? ' active' : '') + '" data-thread="' + esc(c.thread_id) + '" onclick="event.stopPropagation(); ChatApp.openConversation(\'' + esc(c.thread_id) + '\', \'' + esc(convPhone) + '\')">' +
                        '<div class="chat-conv-avatar">' + esc(getInitial(convPhone)) + '</div>' +
                        '<div class="chat-conv-info">' +
                            '<div class="chat-conv-name">' + esc(convName) +
                                '<span class="chat-conv-time-inline">' + esc(displayTime) + '</span>' +
                            '</div>' +
                            '<div class="chat-conv-preview">' + esc(preview) + '</div>' +
                        '</div>' +
                    '<div class="chat-conv-meta">' +
                        '<span class="chat-conv-time">' + esc(displayTime) + '</span>' +
                        (c.unread > 0 ? '<span class="chat-conv-badge-unread">' + c.unread + '</span>' : '') +
                        (isPaused ? '<span class="chat-conv-paused" title="Bot pausado">⏸</span>' : '') +
                    '</div>' +
                    '</div>';
                }
            }
            html += '</div>';
        }

        listEl.innerHTML = html;
    }

    // ── Line / Conversation Selection ──

    function toggleLine(last9) {
        if (state.activeLine === last9) {
            state.activeLine = null;
        } else {
            state.activeLine = last9;
            // Load conversations for this line if not already loaded
            if (!state.conversations[last9]) {
                loadConversationsForLine(last9);
            }
        }
        renderLineList();
    }

    function loadConversationsForLine(last9) {
        var listEl = document.getElementById('chat-lines-list');
        var convsEl = document.getElementById('convs-' + last9);
        if (convsEl) {
            convsEl.innerHTML = '<div style="padding:10px 16px 12px 32px"><div class="spinner" style="width:16px;height:16px;border-width:2px"></div> <span style="font-size:.73rem;color:var(--text-muted)">Cargando...</span></div>';
        }
        // Load threads for this line, filtered server-side by last9
        _fetch(apiUrl('api/mensajes.php?action=threads&last9=' + encodeURIComponent(last9)))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok) {
                state.conversations[last9] = [];
                renderLineList();
                return;
            }
            state.conversations[last9] = d.threads;
            renderLineList();
        }).catch(function() {
            state.conversations[last9] = [];
            renderLineList();
        });
    }

    function openConversation(threadId, phone) {
        state.activeThread = threadId;
        state.lastMsgCount = 0; // Reset for polling
        state._renderedCount = 0; // Force full re-render for new thread

        // ── Clear unread badges LOCALLY before rendering ──
        // This makes the badge disappear instantly (not waiting for server POST).
        var lineLast9 = state.activeLine;
        var hadUnread = false;
        if (lineLast9 && state.conversations[lineLast9]) {
            var convs = state.conversations[lineLast9];
            for (var i = 0; i < convs.length; i++) {
                if (convs[i].thread_id === threadId && convs[i].unread > 0) {
                    convs[i].unread = 0;
                    hadUnread = true;
                    break;
                }
            }
        }
        // Decrement line-level badge optimistically — only if this conversation actually had unreads
        if (hadUnread && lineLast9 && state.linesSummary[lineLast9] && state.linesSummary[lineLast9].total_unread > 0) {
            state.linesSummary[lineLast9].total_unread = Math.max(0, (state.linesSummary[lineLast9].total_unread || 0) - 1);
        }
        // Set readTimestamp early to protect against polling race conditions
        state._readTimestamps[threadId] = Date.now();

        renderLineList();

        // Persist to server asynchronously
        markRead(threadId);

        // Update header
        var avatarEl = document.getElementById('chat-header-avatar');
        var nameEl = document.getElementById('chat-header-name');
        var subtitleEl = document.getElementById('chat-header-subtitle');
        if (avatarEl) avatarEl.textContent = getInitial(phone);
        if (nameEl) nameEl.textContent = formatPhone(phone);
        if (subtitleEl) {
            var lineLabel = '';
            for (var i = 0; i < state.lines.length; i++) {
                if (state.lines[i].last9 === state.activeLine) {
                    var lo = state.lines[i];
                    lineLabel = (typeof _isAdminPanel !== 'undefined' && _isAdminPanel)
                        ? (lo.descripcion || lo.label || '')
                        : (lo.label || '');
                    break;
                }
            }
            subtitleEl.textContent = lineLabel ? 'Línea: ' + lineLabel : '';
        }

        // Show/hide pause toggle
        var pauseToggle = document.getElementById('chat-pause-toggle');
        if (pauseToggle) {
            pauseToggle.style.display = 'flex';
            updatePauseUI();
        }

        // Load messages
        loadMessages(threadId);

        // Mobile: switch to main panel
        if (window.innerWidth <= 768) {
            showMain();
        }
    }

    function loadMessages(threadId) {
        var msgArea = document.getElementById('chat-messages');
        if (!msgArea) return;
        msgArea.innerHTML = '<div class="chat-loading"><div class="spinner"></div><div>Cargando mensajes...</div></div>';

        _fetch(apiUrl('api/mensajes.php?action=conversation&thread_id=' + encodeURIComponent(threadId)))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            // Guard: if user switched thread while fetching, discard this response
            if (state.activeThread !== threadId) return;
            if (!d.ok) {
                msgArea.innerHTML = '<div class="chat-error">Error al cargar conversación</div>';
                return;
            }
            var conv = d.conversation || [];
            state.lastMsgCount = conv.length;
            renderMessages(conv);
        }).catch(function() {
            // Guard: if user switched thread, don't show error for old thread
            if (state.activeThread !== threadId) return;
            msgArea.innerHTML = '<div class="chat-error">Error de conexión</div>';
        });
    }

    function renderMessages(conversation) {
        var msgArea = document.getElementById('chat-messages');
        if (!msgArea) return;

        if (!conversation || conversation.length === 0) {
            msgArea.innerHTML =
                '<div class="chat-empty">' +
                    '<div class="chat-empty-icon">💭</div>' +
                    '<div class="chat-empty-title">Sin mensajes</div>' +
                    '<div class="chat-empty-subtitle">Esta conversación aún no tiene mensajes registrados.</div>' +
                '</div>';
            state._renderedCount = 0;
            return;
        }

        // Sort by timestamp
        conversation.sort(function(a, b) {
            return (a.ts || '').localeCompare(b.ts || '');
        });

        // Dedup: skip duplicate records (same logic as before)
        var deduped = [];
        var seenPending = {};
        var seenFull = {};
        for (var i = 0; i < conversation.length; i++) {
            var m = conversation[i];
            var umsg = (m.user_msg || '').trim();
            var breply = (m.bot_reply || '').trim();
            if (umsg && !breply && m._pending) {
                var skip = false;
                for (var j = i + 1; j < conversation.length; j++) {
                    var nxt = conversation[j];
                    if ((nxt.user_msg || '').indexOf(umsg) !== -1 && (nxt.bot_reply || '').trim()) {
                        skip = true;
                        break;
                    }
                }
                if (skip) continue;
                if (seenPending[umsg]) continue;
                seenPending[umsg] = true;
            }
            if (umsg && !breply && !m._pending) {
                var skipIncomplete = false;
                for (var j = i + 1; j < conversation.length; j++) {
                    var nxt2 = conversation[j];
                    if ((nxt2.user_msg || '').indexOf(umsg) !== -1 && (nxt2.bot_reply || '').trim()) {
                        skipIncomplete = true;
                        break;
                    }
                }
                if (skipIncomplete) continue;
            }
            if (umsg || breply) {
                var key = umsg + '|' + breply + '|' + (m.ts || '');
                if (seenFull[key]) continue;
                seenFull[key] = true;
            }
            deduped.push(m);
        }
        conversation = deduped;

        // ── Incremental rendering instead of full innerHTML replace ──
        var prevCount = state._renderedCount || 0;
        var totalCount = conversation.length;

        // If thread changed or first render → full rebuild needed
        if (prevCount === 0 || totalCount < prevCount) {
            msgArea.innerHTML = '';
            state._renderedCount = 0;
            prevCount = 0;
        }

        // Determine if user is scrolled near bottom (≤ 120px from bottom)
        var wasAtBottom = (msgArea.scrollHeight - msgArea.scrollTop - msgArea.clientHeight) <= 120;

        // Render only new messages (or all if full rebuild)
        var lastDate = '';
        var html = '';
        for (var i = prevCount; i < totalCount; i++) {
            var msg = conversation[i];
            var ts = msg.ts || '';
            var dk = dateKey(ts);

            // Date separator — only add if different from last one already shown
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
                html += '<div class="chat-msg user">' +
                    '<div class="bubble">' +
                        '<div class="msg-body">' + userBody + '</div>' +
                        '<div class="msg-time">' + esc(formatTime(ts)) + '</div>' +
                    '</div>' +
                '</div>';
            }

            if (botMsg) {
                html += '<div class="chat-msg bot">' +
                    '<div class="bubble">' +
                        '<div class="msg-body">' + formatMessageBody(botMsg) + '</div>' +
                        '<div class="msg-time">' +
                            esc(formatTime(ts)) +
                            '<span class="msg-checks">✓✓</span>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            }

            if (!userMsg && !botMsg && msg.raw) {
                html += '<div class="chat-msg-system"><div class="system-text">' + esc((msg.raw || '').slice(0, 100)) + '</div></div>';
            }
        }

        // Append new messages (or set if first render)
        if (prevCount === 0) {
            msgArea.innerHTML = html;
        } else if (html) {
            msgArea.insertAdjacentHTML('beforeend', html);
        }

        state._renderedCount = totalCount;

        // ── Typing indicator: show only if a recent pending message remains unresolved ──
        // Remove old typing indicator first
        var oldTyping = msgArea.querySelector('.chat-typing');
        if (oldTyping) oldTyping.remove();

        var hasRecentPending = false;
        var now = Date.now();
        for (var p = 0; p < conversation.length; p++) {
            if (conversation[p]._pending) {
                var msgTs = new Date(conversation[p].ts).getTime();
                if (now - msgTs < 5 * 60 * 1000 || (typeof IS_DEMO !== 'undefined' && IS_DEMO)) {
                    hasRecentPending = true;
                    break;
                }
            }
        }
        var paused = state.convPause[state.activeThread] || false;
        if (hasRecentPending && !paused) {
            var typingEl = document.createElement('div');
            typingEl.className = 'chat-typing';
            typingEl.innerHTML = '<span></span><span></span><span></span>';
            msgArea.appendChild(typingEl);
        }

        // Auto-scroll only if user was already at bottom
        if (wasAtBottom) {
            scrollToBottom(false);
        }
    }

    function scrollToBottom(smooth) {
        var msgArea = document.getElementById('chat-messages');
        if (!msgArea) return;
        setTimeout(function() {
            msgArea.scrollTo({
                top: msgArea.scrollHeight,
                behavior: smooth ? 'smooth' : 'auto'
            });
        }, 50);
    }

    // ── Message Sending ──

    function sendMessage() {
        var input = document.getElementById('chat-input-text');
        if (!input) return;
        var text = input.value.trim();
        if (!text) return;
        if (!state.activeThread) {
            alert('Selecciona una conversación primero.');
            return;
        }

        // Find the line port
        var port = null;
        var phone = '';
        for (var i = 0; i < state.lines.length; i++) {
            if (state.lines[i].last9 === state.activeLine) {
                port = state.lines[i].port;
                break;
            }
        }

        // Get the contact phone and sender_lid from the active thread
        var convs = state.conversations[state.activeLine] || [];
        var senderLid = '';
        for (var j = 0; j < convs.length; j++) {
            if (convs[j].thread_id === state.activeThread) {
                phone = convs[j].phone || '';
                senderLid = convs[j].sender_lid || '';
                break;
            }
        }

        var chatId = phone.replace(/[^0-9]/g, '') + '@c.us';
        var lidChatId = senderLid ? senderLid.replace(/[^0-9]/g, '') + '@lid' : '';

        // Append local bubble
        var msgArea = document.getElementById('chat-messages');
        if (msgArea) {
            // Remove placeholder if present
            var placeholder = msgArea.querySelector('.chat-placeholder, .chat-empty');
            if (placeholder) msgArea.innerHTML = '';

            var now = new Date().toISOString();
            var time = formatTime(now);

            var bubbleHtml = '<div class="chat-msg bot" style="animation:none">' +
                '<div class="bubble">' +
                    '<div class="msg-body">' + esc(text) + '</div>' +
                    '<div class="msg-time">' + esc(time) + '<span class="msg-checks">✓</span></div>' +
                '</div>' +
            '</div>';
            msgArea.insertAdjacentHTML('beforeend', bubbleHtml);
            scrollToBottom(true);
        }

        // Clear input
        input.value = '';
        autoResizeTextarea(input);

        // Send via API if port is available
        if (port && chatId) {
            var fd = new FormData();
            fd.append('port', port);
            fd.append('chat_id', chatId);
            if (lidChatId) fd.append('lid_chat_id', lidChatId);
            fd.append('text', text);
            fd.append('csrf_token', (typeof _csrf !== 'undefined' ? _csrf : ''));

            // Brief disable to prevent accidental double-sends (re-enable after 400ms)
            var sendBtn = document.getElementById('chat-send-btn');
            if (sendBtn) sendBtn.disabled = true;
            setTimeout(function() {
                if (sendBtn) sendBtn.disabled = false;
            }, 400);

            _fetch('api/mensajes.php?action=send_manual', {
                method: 'POST',
                body: fd
            }).then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok) {
                    // Update check marks: ✓✓ if seen worked, ✓⚠ if not
                    var seenOk = (d.seen_ok !== false); // true or undefined = ok
                    var checks = msgArea.querySelectorAll('.chat-msg.bot .msg-checks');
                    if (checks.length > 0) {
                        checks[checks.length - 1].textContent = seenOk ? '✓✓' : '✓⚠';
                        if (!seenOk) {
                            checks[checks.length - 1].title = 'Mensaje enviado pero no se marcaron como leídos';
                        }
                    }
                    if (!seenOk) {
                        msgArea.insertAdjacentHTML('beforeend',
                            '<div class="chat-msg-system"><div class="system-text" style="color:var(--warn)">⚠️ No se pudieron marcar como leídos</div></div>');
                        scrollToBottom(true);
                    }
                    // Update read pointer so the message WE just sent is not counted as unread
                    markRead(state.activeThread);
                } else {
                    // Show error in the chat area
                    msgArea.insertAdjacentHTML('beforeend',
                        '<div class="chat-msg-system"><div class="system-text" style="color:var(--danger)">❌ No se pudo enviar: ' + esc(d.error || 'Error desconocido') + '</div></div>');
                    scrollToBottom(true);
                }
            }).catch(function(e) {
                // Show connection error in the chat area
                msgArea.insertAdjacentHTML('beforeend',
                    '<div class="chat-msg-system"><div class="system-text" style="color:var(--danger)">❌ Error de conexión al enviar mensaje</div></div>');
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
            // Close picker when clicking outside
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
        var text = input.value;
        input.value = text.slice(0, start) + emoji + text.slice(end);
        input.selectionStart = input.selectionEnd = start + emoji.length;
        input.focus();
        autoResizeTextarea(input);

        // Close picker
        var picker = document.getElementById('chat-emoji-picker');
        if (picker) picker.classList.remove('show');
    }

    // ── Bot Pause Toggle ──

    function toggleBotPause() {
        if (!state.activeThread) return;
        var current = state.convPause[state.activeThread] || false;
        var newPause = !current;
        state.convPause[state.activeThread] = newPause;
        updatePauseUI();
        renderLineList();

        // ── Typing indicator: remove immediately on pause ──
        if (newPause) {
            var typingEl = document.querySelector('#chat-messages .chat-typing');
            if (typingEl) typingEl.remove();
        }
        // Force next poll to re-render (so indicator comes back if resumed with pending msgs)
        state._lastBotReply = null;
        state.lastMsgCount = -1;

        // Persist via API
        var fd = new FormData();
        fd.append('thread_id', state.activeThread);
        fd.append('pause_action', newPause ? 'pause' : 'resume');
        fd.append('csrf_token', (typeof _csrf !== 'undefined' ? _csrf : ''));

        _fetch(apiUrl('api/mensajes.php?action=toggle_pause'), {
            method: 'POST',
            body: fd
        }).then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok) {
                // Revert on failure
                state.convPause[state.activeThread] = current;
                updatePauseUI();
                renderLineList();
            } else if (newPause && state.activeThread === state.activeThread) {
                // Add a system message in the chat
                addSystemMessage('Bot pausado — ya no responderá automáticamente a esta conversación.');
            } else if (!newPause) {
                addSystemMessage('Bot reanudado — volverá a responder automáticamente.');
            }
        }).catch(function() {
            // Revert on error
            state.convPause[state.activeThread] = current;
            updatePauseUI();
            renderLineList();
        });
    }

    function loadPausedList() {
        _fetch(apiUrl('api/mensajes.php?action=paused_list'))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok && d.paused) {
                for (var i = 0; i < d.paused.length; i++) {
                    state.convPause[d.paused[i]] = true;
                }
                renderLineList();
                updatePauseUI();
            }
        }).catch(function(){});
    }

    function addSystemMessage(text) {
        var msgArea = document.getElementById('chat-messages');
        if (!msgArea) return;
        var placeholder = msgArea.querySelector('.chat-placeholder, .chat-empty');
        if (placeholder) msgArea.innerHTML = '';
        var now = new Date().toISOString();
        var time = formatTime(now);
        var div = document.createElement('div');
        div.className = 'chat-msg-system';
        div.innerHTML = '<div class="system-text">' + esc(text) + '</div>';
        msgArea.appendChild(div);
        scrollToBottom(true);
    }

    function startPolling() {
        stopPolling();
        state._pollTick = 0;  // reset counter
        state.pollInterval = setInterval(function() {
            if (!state.isOpen) return;
            // Use Page Visibility API: pause polling when browser tab is hidden
            if (document.hidden) return;
            state._pollTick++;

            // Every tick (1.5s): refresh messages if a conversation is open
            if (state.activeThread) {
                refreshMessages();
            }

            // Every 4th tick (6s): refresh thread list for active line + summary badges
            if (state._pollTick % 4 === 0) {
                if (state.activeLine) refreshThreads();
                refreshLinesSummary(state.activeLine || undefined);
            }

            // Every 20th tick (30s): refresh lines status
            if (state._pollTick % 20 === 0) {
                refreshLinesStatus();
            }
        }, 1500); // Poll every 1.5 seconds for near-real-time updates
    }

    function stopPolling() {
        if (state.pollInterval) {
            clearInterval(state.pollInterval);
            state.pollInterval = null;
        }
    }

    function refreshLinesStatus() {
        _fetch(apiUrl('api/lines.php?action=status'))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok && d.statuses) {
                var changed = false;
                for (var i = 0; i < state.lines.length; i++) {
                    var line = state.lines[i];
                    var st = d.statuses[line.id];
                    if (st && line.live_status !== st) {
                        line.live_status = st;
                        line.health_status = st;
                        changed = true;
                    }
                }
                if (changed) renderLineList();
            }
        }).catch(function(){});
    }

    function refreshMessages() {
        if (!state.activeThread) return;
        var threadId = state.activeThread;  // snapshot for guard
        _fetch(apiUrl('api/mensajes.php?action=conversation&thread_id=' + encodeURIComponent(threadId)))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            // Guard: if user switched thread while fetching, discard this response
            if (state.activeThread !== threadId) return;
            if (!d.ok) return;
            var conv = d.conversation || [];
            var newCount = conv.length;
            var lastBotReply = conv.length > 0 ? (conv[conv.length-1].bot_reply || '') : '';
            // Re-render if count changed OR bot reply was added to the last message
            if (newCount !== state.lastMsgCount || lastBotReply !== state._lastBotReply) {
                state.lastMsgCount = newCount;
                state._lastBotReply = lastBotReply;
                renderMessages(conv);
                // Also refresh thread list to update preview/last_msg
                refreshThreads();
            }
        }).catch(function(){});
    }

    function refreshThreads() {
        if (!state.activeLine) return;
        _fetch(apiUrl('api/mensajes.php?action=threads&last9=' + encodeURIComponent(state.activeLine)))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok) return;
            state.conversations[state.activeLine] = d.threads;

            // Preserve local read state for threads recently marked as read.
            // Prevents the poll from restoring unread badges before the server
            // has persisted the mark_read (race condition fix).
            var now = Date.now();
            for (var i = 0; i < d.threads.length; i++) {
                var tid = d.threads[i].thread_id;
                var readTs = state._readTimestamps[tid];
                if (readTs && (now - readTs < 15000)) {   // 15-second protection window
                    d.threads[i].unread = 0;
                }
            }
            // Periodic cleanup of stale entries (outside the protection window)
            for (var k in state._readTimestamps) {
                if (state._readTimestamps.hasOwnProperty(k) && (now - state._readTimestamps[k] > 15000)) {
                    delete state._readTimestamps[k];
                }
            }

            // If active thread no longer exists in the refreshed list, deselect it
            if (state.activeThread && d.threads.length > 0) {
                var found = false;
                for (var i2 = 0; i2 < d.threads.length; i2++) {
                    if (d.threads[i2].thread_id === state.activeThread) { found = true; break; }
                }
                if (!found) state.activeThread = null;
            }
            renderLineList();
        }).catch(function(){});
    }

    function updatePauseUI() {
        var toggle = document.getElementById('chat-pause-toggle');
        var label = document.getElementById('chat-pause-label');
        var hint = document.getElementById('chat-paused-hint');
        if (!toggle || !state.activeThread) return;

        var isPaused = state.convPause[state.activeThread] || false;
        if (isPaused) {
            toggle.classList.add('paused');
            if (label) label.textContent = 'Pausado';
            if (hint) hint.classList.add('visible');
        } else {
            toggle.classList.remove('paused');
            if (label) label.textContent = 'Auto';
            if (hint) hint.classList.remove('visible');
        }
    }

    // ── Search / Filter ──

    function filter() {
        var query = document.getElementById('chat-search');
        if (!query) return;
        var q = query.value.trim().toLowerCase();

        var rows = document.querySelectorAll('.chat-line-row');
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var nameEl = row.querySelector('.chat-line-name');
            var phoneEl = row.querySelector('.chat-line-phone');
            var name = nameEl ? nameEl.textContent.toLowerCase() : '';
            var phone = phoneEl ? phoneEl.textContent.toLowerCase() : '';

            if (q === '' || name.indexOf(q) !== -1 || phone.indexOf(q) !== -1) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    }

    // ── Mobile Navigation ──

    function showMain() {
        var sidebar = document.getElementById('chat-sidebar');
        var main = document.getElementById('chat-main');
        if (sidebar) sidebar.classList.remove('mobile-visible');
        if (main) main.classList.remove('mobile-hidden');
    }

    function showSidebar() {
        var sidebar = document.getElementById('chat-sidebar');
        var main = document.getElementById('chat-main');
        if (sidebar) sidebar.classList.add('mobile-visible');
        if (main) main.classList.add('mobile-hidden');
    }

    // ── Photo Picker (Image Attachment) ──

    function openPhotoPicker() {
        if (!state.activeThread) {
            alert('Selecciona una conversación primero.');
            return;
        }
        if (state.photoPicker.overlay) {
            closePhotoPicker();
        }

        // Fetch catalog
        state.photoPicker.girls = [];
        state.photoPicker.selected = [];
        createPhotoPickerOverlay();

        _fetch(apiUrl('api/girls.php?action=get_catalog'))
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (!d.ok || !d.girls) {
                state.photoPicker.girls = [];
                renderPhotoGrid([]);
                return;
            }
            state.photoPicker.girls = d.girls;
            renderPhotoGrid(d.girls);
        }).catch(function() {
            state.photoPicker.girls = [];
            renderPhotoGrid([]);
        });
    }

    function closePhotoPicker() {
        var pp = state.photoPicker;
        if (pp.overlay) {
            pp.overlay.remove();
            pp.overlay = null;
        }
        pp.girls = [];
        pp.selected = [];
    }

    function createPhotoPickerOverlay() {
        var pp = state.photoPicker;
        if (pp.overlay) return;

        var overlay = document.createElement('div');
        overlay.className = 'photo-picker-overlay';
        overlay.onclick = function(e) {
            if (e.target === overlay) closePhotoPicker();
        };

        var container = document.createElement('div');
        container.className = 'photo-picker-container';

        // Header
        var header = document.createElement('div');
        header.className = 'photo-picker-header';
        header.innerHTML = '<h3>Selecciona imágenes</h3>' +
            '<button class="photo-picker-close" title="Cerrar" onclick="ChatApp.closePhotoPicker()">✕</button>';
        container.appendChild(header);

        // Grid area
        var grid = document.createElement('div');
        grid.className = 'photo-picker-grid';
        grid.id = 'photo-picker-grid';
        grid.innerHTML = '<div class="photo-picker-loading"><div class="spinner"></div><span>Cargando fotos...</span></div>';
        container.appendChild(grid);

        // Footer
        var footer = document.createElement('div');
        footer.className = 'photo-picker-footer';
        footer.id = 'photo-picker-footer';
        footer.innerHTML =
            '<span class="photo-picker-count" id="photo-picker-count">0 seleccionadas</span>' +
            '<div class="photo-picker-actions">' +
                '<button class="photo-picker-btn photo-picker-cancel" onclick="ChatApp.closePhotoPicker()">Cancelar</button>' +
                '<button class="photo-picker-btn photo-picker-accept" id="photo-picker-accept" disabled onclick="ChatApp.sendImages()">Aceptar</button>' +
            '</div>';
        container.appendChild(footer);

        overlay.appendChild(container);
        document.body.appendChild(overlay);
        pp.overlay = overlay;

        // ESC key to close
        overlay.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closePhotoPicker();
        });
    }

    /**
     * Convert a photo URL to a direct image URL for thumbnail display.
     * compartir.site URLs are HTML wrapper pages (for WhatsApp link preview).
     * The actual image lives at: compartir.site/{code}/{code}.jpg
     */
    function getDirectImageUrl(url) {
        if (!url) return '';
        // compartir.site shortlink: https://compartir.site/abc12/ → https://compartir.site/abc12/abc12.jpg
        var m = url.match(/^https?:\/\/(?:[^\/]*\.)?compartir\.site\/([a-z0-9]+)\/?$/i);
        if (m) {
            return 'https://compartir.site/' + m[1] + '/' + m[1] + '.jpg';
        }
        // Append auth token for image-proxy.php URLs so images load even
        // without a valid session cookie (token-based auth in image-proxy.php)
        if (/\/api\/image-proxy\.php/i.test(url) && typeof _apiToken !== 'undefined' && _apiToken) {
            var sep = url.indexOf('?') === -1 ? '?' : '&';
            return url + sep + 'token=' + encodeURIComponent(_apiToken);
        }
        // Already a direct image URL (e.g., /api/image-proxy.php, i.ibb.co, etc.)
        return url;
    }

    function renderPhotoGrid(girls) {
        var grid = document.getElementById('photo-picker-grid');
        if (!grid) return;

        // Build flat list of all photo URLs with girl info
        var photos = [];
        for (var gi = 0; gi < girls.length; gi++) {
            var g = girls[gi];
            var fotos = g.fotos || [];
            for (var fi = 0; fi < fotos.length; fi++) {
                photos.push({
                    url: fotos[fi],
                    girlName: g.nombre || '',
                    girlId: g.id || '',
                });
            }
        }

        if (photos.length === 0) {
            grid.innerHTML = '<div class="photo-picker-empty">No hay fotos disponibles.<br><span style="font-size:.75rem;color:var(--text-muted)">Añade chicas activas con fotos primero.</span></div>';
            return;
        }

        var html = '';
        for (var i = 0; i < photos.length; i++) {
            var p = photos[i];
            var isSelected = state.photoPicker.selected.indexOf(p.url) !== -1;
            var escUrl = esc(p.url);
            var imgUrl = esc(getDirectImageUrl(p.url));
            html += '<div class="photo-picker-thumb' + (isSelected ? ' selected' : '') + '" ' +
                'data-url="' + escUrl + '" ' +
                'title="' + esc(p.girlName) + '" ' +
                'onclick="ChatApp.togglePhotoSelection(this)" ' +
                'style="background-image:url(\'' + imgUrl + '\')">' +
            '</div>';
        }

        grid.innerHTML = html;
        updatePhotoPickerFooter();
    }

    function togglePhotoSelection(el) {
        var url = el.getAttribute('data-url');
        if (!url) return;

        var idx = state.photoPicker.selected.indexOf(url);
        if (idx !== -1) {
            state.photoPicker.selected.splice(idx, 1);
            el.classList.remove('selected');
        } else {
            state.photoPicker.selected.push(url);
            el.classList.add('selected');
        }
        updatePhotoPickerFooter();
    }

    function updatePhotoPickerFooter() {
        var countEl = document.getElementById('photo-picker-count');
        var acceptBtn = document.getElementById('photo-picker-accept');
        var count = state.photoPicker.selected.length;

        if (countEl) {
            countEl.textContent = count + ' seleccionada' + (count !== 1 ? 's' : '');
        }
        if (acceptBtn) {
            acceptBtn.disabled = count === 0;
        }
    }

    function sendImages() {
        var urls = state.photoPicker.selected.slice(); // copy
        if (urls.length === 0) return;
        if (!state.activeThread) {
            alert('Selecciona una conversación primero.');
            return;
        }

        // Find line port
        var port = null;
        var phone = '';
        for (var i = 0; i < state.lines.length; i++) {
            if (state.lines[i].last9 === state.activeLine) {
                port = state.lines[i].port;
                break;
            }
        }

        var convs = state.conversations[state.activeLine] || [];
        var senderLid = '';
        for (var j = 0; j < convs.length; j++) {
            if (convs[j].thread_id === state.activeThread) {
                phone = convs[j].phone || '';
                senderLid = convs[j].sender_lid || '';
                break;
            }
        }

        var chatId = phone.replace(/[^0-9]/g, '') + '@c.us';
        var lidChatId = senderLid ? senderLid.replace(/[^0-9]/g, '') + '@lid' : '';

        if (!port || !chatId) {
            alert('No se pudo determinar la línea o el chat.');
            return;
        }

        // Disable accept button during send
        var acceptBtn = document.getElementById('photo-picker-accept');
        if (acceptBtn) acceptBtn.disabled = true;

        closePhotoPicker();

        var msgArea = document.getElementById('chat-messages');
        var sendIndex = 0;

        function sendNext() {
            if (sendIndex >= urls.length) return;

            var text = urls[sendIndex];

            // Append local bubble
            if (msgArea) {
                var placeholder = msgArea.querySelector('.chat-placeholder, .chat-empty');
                if (placeholder) msgArea.innerHTML = '';

                var now = new Date().toISOString();
                var time = formatTime(now);
                var bubbleHtml = '<div class="chat-msg bot" style="animation:none">' +
                    '<div class="bubble">' +
                        '<div class="msg-body">' + formatMessageBody(text) + '</div>' +
                        '<div class="msg-time">' + esc(time) + '<span class="msg-checks">✓</span></div>' +
                    '</div>' +
                '</div>';
                msgArea.insertAdjacentHTML('beforeend', bubbleHtml);
                scrollToBottom(true);
            }

            var fd = new FormData();
            fd.append('port', port);
            fd.append('chat_id', chatId);
            if (lidChatId) fd.append('lid_chat_id', lidChatId);
            fd.append('text', text);
            fd.append('csrf_token', (typeof _csrf !== 'undefined' ? _csrf : ''));

            _fetch('api/mensajes.php?action=send_manual', {
                method: 'POST',
                body: fd
            }).then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok && msgArea) {
                    var checks = msgArea.querySelectorAll('.chat-msg.bot .msg-checks');
                    if (checks.length > 0) {
                        checks[checks.length - 1].textContent = d.seen_ok !== false ? '✓✓' : '✓⚠';
                    }
                } else if (msgArea) {
                    msgArea.insertAdjacentHTML('beforeend',
                        '<div class="chat-msg-system"><div class="system-text" style="color:var(--danger)">❌ No se pudo enviar: ' + esc(d.error || 'Error') + '</div></div>');
                    scrollToBottom(true);
                }
            }).catch(function() {
                if (msgArea) {
                    msgArea.insertAdjacentHTML('beforeend',
                        '<div class="chat-msg-system"><div class="system-text" style="color:var(--danger)">❌ Error de conexión al enviar imagen</div></div>');
                    scrollToBottom(true);
                }
            });

            sendIndex++;
            if (sendIndex < urls.length) {
                // Random delay 2-3 seconds between images
                var delay = 2000 + Math.floor(Math.random() * 1000);
                setTimeout(sendNext, delay);
            }
        }

        sendNext();
    }

    // ── Init on load ──
    // Auto-init for mobile: show sidebar first on small screens
    function initMobile() {
        if (window.innerWidth <= 768 && state.activeThread) {
            // Already have a conversation open, show main
            showMain();
        } else if (window.innerWidth <= 768) {
            showSidebar();
        }
    }

    // ── Public API ──

    return {
        open: open,
        close: close,
        toggleLine: toggleLine,
        openConversation: openConversation,
        sendMessage: sendMessage,
        handleInputKey: handleInputKey,
        autoResizeTextarea: autoResizeTextarea,
        toggleEmojiPicker: toggleEmojiPicker,
        insertEmoji: insertEmoji,
        toggleBotPause: toggleBotPause,
        filter: filter,
        scrollToBottom: scrollToBottom,
        showSidebar: showSidebar,
        showMain: showMain,
        refreshMessages: refreshMessages,
        openPhotoPicker: openPhotoPicker,
        closePhotoPicker: closePhotoPicker,
        togglePhotoSelection: togglePhotoSelection,
        sendImages: sendImages,
    };

})();
