/* ═══════════════════════════════════════════════════════════════════════════
   LITE DIAG — Diagnóstico visual en pantalla (sin consola / sin F12)
   ---------------------------------------------------------------------------
   Se activa con el botón "DIAG" de la barra superior del reproductor Lite.
   Comprueba:
     1) versión de assets cargada + estilos calculados (detecta caché vieja)
     2) hit-test de cada botón clave (detecta capas que los tapan)
     3) acciones reales (abrir/cerrar GPS, PRES, RAD, BIB)
     4) errores JS en pantalla
     5) modo "toque real" con recuadros sobre los botones reales
     6) envío del informe a index.php?action=lite_diag
   ES5, sin dependencias.
   ═══════════════════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    if (!document.body || !document.body.classList.contains('is-lite')) return;

    var PANEL_ID = 'liteDiagPanel';

    var TARGETS = [
        { id: 'vzDiagBtn',                  label: 'DIAG' },
        { id: 'vzLiteExitBtn',              label: 'MENÚ' },
        { id: 'vzLiteTestBtn',              label: 'TEST VOZ' },
        { id: 'ytGpsBtn',                   label: 'GPS' },
        { id: 'ytRadioPresintoniasToggle',  label: 'PRES' },
        { id: 'ytRadioRadiosToggle',        label: 'RAD' },
        { id: 'ytRadioSidebarToggle',       label: 'BIB' },
        { id: 'ytJefryChatStart',           label: 'CAR' },
        { id: 'youtubePlayPauseBtn',        label: 'PLAY' }
    ];

    var ACTIONS = [
        { label: 'GPS',  click: 'ytGpsBtn',                     panel: 'gpsOverlay',         cls: 'open' },
        { label: 'PRES', click: 'ytRadioPresintoniasToggle',    panel: 'presintoniasPanel',  cls: 'open' },
        { label: 'RAD',  click: 'ytRadioRadiosToggle',          panel: 'radiosPanel',        cls: 'open' },
        { label: 'BIB',  click: 'ytRadioSidebarToggle',         panel: 'ytRadioSidebar',     cls: 'open' }
    ];

    var _errors = [];
    var _hits = [];
    var _actions = [];
    var _touches = [];
    var _touchMode = false;
    var _outlineTimer = null;

    // ── Captura de errores (siempre activa, coste mínimo) ───────────────────
    window.addEventListener('error', function (e) {
        _pushError('error', (e && e.message ? e.message : 'error') +
            (e && e.lineno ? ' @line ' + e.lineno : ''));
    });
    window.addEventListener('unhandledrejection', function (e) {
        var r = e && e.reason ? (e.reason.message || String(e.reason)) : 'rejection';
        _pushError('promise', r);
    });
    function _pushError(kind, msg) {
        _errors.push({ t: _hhmmss(), kind: kind, msg: String(msg).substr(0, 220) });
        if (_errors.length > 20) _errors.shift();
    }

    // ── Utilidades ───────────────────────────────────────────────────────────
    function el(id) { return document.getElementById(id); }
    function _hhmmss() { return new Date().toISOString().slice(11, 19); }
    function _css(node, prop) {
        if (!node || !window.getComputedStyle) return '?';
        return window.getComputedStyle(node)[prop];
    }
    function _versionOf(sel) {
        try {
            var n = document.querySelector(sel);
            if (!n) return 'no encontrado';
            return n.getAttribute('src') || n.getAttribute('href') || '?';
        } catch (e) { return '?'; }
    }
    function _describe(node) {
        if (!node) return 'nada';
        var s = node.tagName ? node.tagName.toLowerCase() : '?';
        if (node.id) s += '#' + node.id;
        if (node.className && typeof node.className === 'string') {
            var c = node.className.split(' ').filter(function (x) { return x; }).slice(0, 2).join('.');
            if (c) s += '.' + c;
        }
        return s;
    }
    function _hasOpen(id, cls) {
        var n = el(id);
        return !!(n && n.classList.contains(cls));
    }
    function _esc(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ── Estilos (inyectados, no dependen de lite.css) ────────────────────────
    function _injectStyles() {
        if (el('ldStyles')) return;
        var st = document.createElement('style');
        st.id = 'ldStyles';
        st.textContent = [
            '.ld-panel{position:fixed;top:0;left:0;right:0;bottom:0;z-index:2147483600;background:#050a12;color:#e8f0fb;',
            'font-family:"Courier New",monospace;display:flex;flex-direction:column;box-sizing:border-box;padding:8px;overflow:hidden}',
            '.ld-head{display:flex;flex-wrap:wrap;gap:6px;align-items:center;border-bottom:2px solid #1e3a5f;padding-bottom:8px;flex:0 0 auto}',
            '.ld-title{font-size:18px;font-weight:700;color:#fbbf24;letter-spacing:1px}',
            '.ld-ver{font-size:11px;color:#93c5fd;margin-left:auto}',
            '.ld-btn{min-height:44px;padding:8px 12px;border-radius:8px;border:2px solid #3b82f6;background:#0f2140;color:#dbeafe;',
            'font-size:15px;font-weight:700;cursor:pointer;touch-action:manipulation;-webkit-user-select:none;user-select:none}',
            '.ld-btn:active{transform:translateY(1px);background:#17325c}',
            '.ld-btn.green{border-color:#10b981;color:#6ee7b7}',
            '.ld-btn.amber{border-color:#f59e0b;color:#fcd34d}',
            '.ld-btn.red{border-color:#ef4444;color:#fca5a5}',
            '.ld-body{flex:1 1 auto;overflow-y:auto;-webkit-overflow-scrolling:touch;padding:8px 2px 90px 2px}',
            '.ld-sec{margin:10px 0 4px;font-size:16px;font-weight:700;color:#fbbf24;border-bottom:1px solid #1e3a5f;padding-bottom:4px}',
            '.ld-row{display:flex;align-items:flex-start;gap:8px;font-size:15px;padding:5px 0;border-bottom:1px dashed rgba(255,255,255,.08);line-height:1.25}',
            '.ld-ok{color:#4ade80;font-weight:700}.ld-bad{color:#f87171;font-weight:700}.ld-warn{color:#fbbf24;font-weight:700}',
            '.ld-k{color:#93c5fd;white-space:nowrap}',
            '.ld-small{font-size:12px;color:#cbd5e1;word-break:break-all}',
            '.ld-toast{position:absolute;left:8px;right:8px;bottom:8px;background:#111827;border:2px solid #f59e0b;border-radius:10px;',
            'padding:12px;font-size:16px;font-weight:700;color:#fde68a;text-align:center;display:none}',
            '.ld-toast.show{display:block}',
            'body.ld-touch .ld-body,body.ld-touch .ld-head .ld-touch-hide{display:none}',
            'body.ld-touch .ld-head{border-bottom:none;padding-bottom:0}',
            'body.ld-touch .ld-panel{top:auto;height:auto;bottom:0;background:rgba(5,10,18,.94)}',
            '#ldOutlines{position:fixed;top:0;left:0;right:0;bottom:0;z-index:2147483500;pointer-events:none}',
            '#ldOutlines .ld-box{position:absolute;border:3px solid #f59e0b;border-radius:6px;box-shadow:0 0 10px rgba(245,158,11,.6)}',
            '#ldOutlines .ld-box span{position:absolute;top:-18px;left:0;background:#f59e0b;color:#000;font:700 11px "Courier New",monospace;padding:1px 4px;border-radius:3px}',
            '#ldReadout{font-size:20px;font-weight:700;color:#4ade80;padding:8px;text-align:center}',
            '.ld-readout{display:none;width:100%;font-size:18px;font-weight:700;color:#4ade80;padding:6px 0}',
            'body.ld-touch .ld-readout{display:block}'
        ].join('');
        document.head.appendChild(st);
    }

    // ── Medición de hit-test (oculta el panel un instante) ───────────────────
    function _measureHits() {
        var panel = el(PANEL_ID);
        var outlines = el('ldOutlines');
        var prevPanel = panel ? panel.style.display : null;
        var prevOut = outlines ? outlines.style.display : null;
        if (panel) panel.style.display = 'none';
        if (outlines) outlines.style.display = 'none';
        void document.body.offsetHeight; // force reflow

        var results = [];
        for (var i = 0; i < TARGETS.length; i++) {
            var t = TARGETS[i];
            var node = el(t.id);
            if (!node) { results.push({ id: t.id, label: t.label, status: 'missing' }); continue; }
            var r = node.getBoundingClientRect();
            if (r.width < 2 || r.height < 2) {
                results.push({ id: t.id, label: t.label, status: 'hidden' });
                continue;
            }
            var x = r.left + r.width / 2;
            var y = r.top + r.height / 2;
            if (x < 0 || y < 0 || x > window.innerWidth || y > window.innerHeight) {
                results.push({ id: t.id, label: t.label, status: 'offscreen' });
                continue;
            }
            var hit = document.elementFromPoint(x, y);
            var ok = hit === node || (node.contains && node.contains(hit));
            results.push({
                id: t.id,
                label: t.label,
                status: ok ? 'ok' : 'blocked',
                hit: _describe(hit),
                x: Math.round(x), y: Math.round(y)
            });
        }

        if (panel) panel.style.display = prevPanel || '';
        if (outlines) outlines.style.display = prevOut || '';
        return results;
    }

    // ── Acciones reales (click programático + verificación) ──────────────────
    function _runActions() {
        var results = [];
        for (var i = 0; i < ACTIONS.length; i++) {
            var a = ACTIONS[i];
            var btn = el(a.click);
            if (!btn) { results.push({ label: a.label, status: 'missing' }); continue; }
            var started = false, ok = false, err = '';
            try {
                btn.click();
                started = true;
                ok = _hasOpen(a.panel, a.cls);
            } catch (e) {
                err = (e && e.message) ? e.message : 'error';
            }
            // limpiar: volver a pulsar para cerrar
            try { if (started) btn.click(); } catch (e2) {}
            results.push({ label: a.label, status: ok ? 'ok' : (err ? 'error' : 'fail'), detail: err || (ok ? '' : 'no abre ' + a.panel) });
        }
        return results;
    }

    // ── Informe ──────────────────────────────────────────────────────────────
    function _stylesReport() {
        var go = el('gpsOverlay');
        var cv = el('gpsRadarCanvas');
        var bar = el('vzLiteFsBar');
        return {
            gpsOverlay: { visibility: _css(go, 'visibility'), pointerEvents: _css(go, 'pointerEvents'), zIndex: _css(go, 'zIndex') },
            gpsCanvas: { pointerEvents: _css(cv, 'pointerEvents') },
            barParent: bar && bar.parentNode ? (bar.parentNode.tagName.toLowerCase() + (bar.parentNode.id ? '#' + bar.parentNode.id : '')) : 'null',
            barTop: bar ? Math.round(bar.getBoundingClientRect().top) : -1
        };
    }
    function _summary() {
        var ok = 0, bad = 0;
        for (var i = 0; i < _hits.length; i++) { if (_hits[i].status === 'ok') ok++; else bad++; }
        return 'TAPADOS ' + bad + '/' + _hits.length + ' · ACCIONES OK ' + _actions.filter(function (a) { return a.status === 'ok'; }).length + '/' + _actions.length;
    }
    function _buildReport() {
        return {
            ts: new Date().toISOString(),
            url: location.href,
            ua: navigator.userAgent,
            screen: (window.screen ? (screen.width + 'x' + screen.height) : '?'),
            versions: {
                appJs: _versionOf('script[src*="app.js"]'),
                liteCss: _versionOf('link[href*="lite.css"]'),
                diagJs: _versionOf('script[src*="lite-diag.js"]')
            },
            styles: _stylesReport(),
            hits: _hits,
            actions: _actions,
            errors: _errors,
            lastTouches: _touches.slice(0, 10)
        };
    }
    function _b64(str) { return btoa(unescape(encodeURIComponent(str))); }

    function _send() {
        var report = _buildReport();
        var json = JSON.stringify(report);
        if (json.length > 3000) {
            report.errors = report.errors.slice(-3);
            report.lastTouches = (report.lastTouches || []).slice(0, 3);
            json = JSON.stringify(report);
        }
        if (json.length > 6000) {
            report.hits = [];
            report.errors = [];
            report.lastTouches = [];
            report.note = 'informe reducido por tamaño';
            json = JSON.stringify(report);
        }
        var url = 'index.php?action=lite_diag&data=' + encodeURIComponent(_b64(json));
        _toast('Enviando…');
        try {
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    if (j && j.ok) { _toast('INFORME ENVIADO ✅'); }
                    else { _toast('Error servidor: ' + ((j && j.error) ? j.error : '?') + ' · ' + _summary()); }
                })
                .catch(function () { _toast('Sin conexión. Código: ' + _summary()); });
        } catch (e) {
            _toast('No se pudo enviar. Código: ' + _summary());
        }
    }

    // ── Modo toque real ──────────────────────────────────────────────────────
    function _drawOutlines() {
        var box = el('ldOutlines');
        if (!box) return;
        box.innerHTML = '';
        for (var i = 0; i < TARGETS.length; i++) {
            var node = el(TARGETS[i].id);
            if (!node) continue;
            var r = node.getBoundingClientRect();
            if (r.width < 2 || r.height < 2) continue;
            var d = document.createElement('div');
            d.className = 'ld-box';
            d.style.left = Math.round(r.left) + 'px';
            d.style.top = Math.round(r.top) + 'px';
            d.style.width = Math.round(r.width) + 'px';
            d.style.height = Math.round(r.height) + 'px';
            var s = document.createElement('span');
            s.textContent = TARGETS[i].label;
            d.appendChild(s);
            box.appendChild(d);
        }
    }
    function _setTouchMode(on) {
        _touchMode = !!on;
        if (_touchMode) document.body.classList.add('ld-touch');
        else document.body.classList.remove('ld-touch');
        var box = el('ldOutlines');
        if (_touchMode) {
            if (!box) { box = document.createElement('div'); box.id = 'ldOutlines'; document.body.appendChild(box); }
            box.style.display = '';
            _drawOutlines();
            if (_outlineTimer) clearInterval(_outlineTimer);
            _outlineTimer = setInterval(_drawOutlines, 1000);
        } else {
            if (_outlineTimer) { clearInterval(_outlineTimer); _outlineTimer = null; }
            if (box) box.style.display = 'none';
        }
        _render();
    }

    // Registrar toques reales (capture, solo en modo toque)
    document.addEventListener('click', function (e) {
        if (!_touchMode) return;
        _touches.unshift({ t: _hhmmss(), el: _describe(e.target) });
        if (_touches.length > 15) _touches.pop();
        var ro = el('ldReadout');
        if (ro) ro.textContent = 'ÚLTIMO TOQUE: ' + _describe(e.target);
    }, true);

    // ── Render ───────────────────────────────────────────────────────────────
    function _row(label, status, text) {
        var mark = status === 'ok' ? '✅' : (status === 'blocked' || status === 'fail' || status === 'error' ? '❌' : '⚠️');
        var cls = status === 'ok' ? 'ld-ok' : (status === 'hidden' || status === 'offscreen' || status === 'missing' ? 'ld-warn' : 'ld-bad');
        return '<div class="ld-row"><span class="' + cls + '">' + mark + '</span><span class="ld-k">' + _esc(label) + '</span><span>' + _esc(text) + '</span></div>';
    }

    function _render() {
        var body = el('ldBody');
        if (!body) return;
        var html = '';

        // 1. Versiones
        html += '<div class="ld-sec">1 · VERSIÓN CARGADA</div>';
        html += '<div class="ld-small">app.js: ' + _esc(_versionOf('script[src*="app.js"]')) + '<br>lite.css: ' + _esc(_versionOf('link[href*="lite.css"]')) + '<br>lite-diag.js: ' + _esc(_versionOf('script[src*="lite-diag.js"]')) + '</div>';

        // 2. Estilos calculados
        var s = _stylesReport();
        html += '<div class="ld-sec">2 · ESTADO DEL OVERLAY GPS</div>';
        html += _row('gpsOverlay visibility', s.gpsOverlay.visibility === 'hidden' ? 'ok' : 'fail', s.gpsOverlay.visibility + ' (esperado hidden)');
        html += _row('gpsOverlay pointer-events', s.gpsOverlay.pointerEvents === 'none' ? 'ok' : 'fail', s.gpsOverlay.pointerEvents + ' (esperado none)');
        html += _row('gpsOverlay z-index', s.gpsOverlay.zIndex === '100010' ? 'ok' : 'warn', s.gpsOverlay.zIndex + ' (esperado 100010)');
        html += _row('radar canvas pointer-events', s.gpsCanvas.pointerEvents === 'none' ? 'ok' : 'fail', s.gpsCanvas.pointerEvents + ' (esperado none)');
        html += _row('barra superior padre', s.barParent === 'body' ? 'ok' : 'warn', _esc(s.barParent) + ' (esperado body)');

        // 3. Hit-test botones
        html += '<div class="ld-sec">3 · BOTONES (¿TAPADOS?)</div>';
        if (!_hits.length) html += '<div class="ld-row">Pulsa RE-EJECUTAR</div>';
        for (var i = 0; i < _hits.length; i++) {
            var h = _hits[i];
            var txt;
            if (h.status === 'ok') txt = 'pulsable';
            else if (h.status === 'blocked') txt = 'TAPADO POR: ' + h.hit + ' @' + h.x + ',' + h.y;
            else if (h.status === 'hidden') txt = 'no visible ahora';
            else if (h.status === 'offscreen') txt = 'fuera de pantalla';
            else txt = 'no existe';
            html += _row(h.label, h.status === 'ok' ? 'ok' : (h.status === 'blocked' ? 'blocked' : 'warn'), txt);
        }

        // 4. Acciones
        html += '<div class="ld-sec">4 · ACCIONES</div>';
        if (!_actions.length) html += '<div class="ld-row">Pulsa RE-EJECUTAR</div>';
        for (var j = 0; j < _actions.length; j++) {
            var a = _actions[j];
            html += _row(a.label, a.status, a.status === 'ok' ? 'abre y cierra correctamente' : (a.detail || a.status));
        }

        // 5. Toques reales
        html += '<div class="ld-sec">5 · TOQUES REALES</div>';
        for (var k = 0; k < _touches.length; k++) {
            html += '<div class="ld-small">' + _esc(_touches[k].t) + ' → ' + _esc(_touches[k].el) + '</div>';
        }

        // 6. Errores
        html += '<div class="ld-sec">6 · ERRORES JS (' + _errors.length + ')</div>';
        if (!_errors.length) html += '<div class="ld-row"><span class="ld-ok">✅</span> ninguno</div>';
        for (var m = 0; m < _errors.length; m++) {
            html += '<div class="ld-small">' + _esc(_errors[m].t) + ' [' + _esc(_errors[m].kind) + '] ' + _esc(_errors[m].msg) + '</div>';
        }

        html += '<div class="ld-sec">RESUMEN</div><div class="ld-row"><span class="ld-k">CODIGO</span><span>' + _esc(_summary()) + '</span></div>';

        body.innerHTML = html;
    }

    function _toast(msg) {
        var t = el('ldToast');
        if (!t) return;
        t.textContent = msg;
        t.className = 'ld-toast show';
        clearTimeout(t._timer);
        t._timer = setTimeout(function () { t.className = 'ld-toast'; }, 6000);
    }

    function _rerun() {
        _hits = _measureHits();
        _actions = _runActions();
        _render();
        _toast(_summary());
    }

    // ── Construcción / apertura ──────────────────────────────────────────────
    function _ensurePanel() {
        var p = el(PANEL_ID);
        if (p) return p;
        _injectStyles();
        p = document.createElement('div');
        p.id = PANEL_ID;
        p.className = 'ld-panel';
        p.innerHTML =
            '<div class="ld-head">' +
                '<span class="ld-title">DIAGNÓSTICO LITE</span>' +
                '<span class="ld-ver" id="ldVer"></span>' +
                '<button type="button" class="ld-btn amber ld-touch-hide" id="ldRerun">⟲ RE-EJECUTAR</button>' +
                '<button type="button" class="ld-btn green ld-touch-hide" id="ldSend">📤 ENVIAR INFORME</button>' +
                '<button type="button" class="ld-btn ld-touch-hide" id="ldTouch">👆 PROBAR TOQUES</button>' +
                '<button type="button" class="ld-btn red" id="ldClose">✕ CERRAR</button>' +
                '<div class="ld-readout" id="ldReadout">ÚLTIMO TOQUE: --</div>' +
            '</div>' +
            '<div class="ld-body" id="ldBody"></div>' +
            '<div class="ld-toast" id="ldToast"></div>';
        document.body.appendChild(p);

        el('ldClose').addEventListener('click', function () { if (_touchMode) _setTouchMode(false); close(); });
        el('ldRerun').addEventListener('click', function () { _rerun(); });
        el('ldSend').addEventListener('click', function () { _send(); });
        el('ldTouch').addEventListener('click', function () { _setTouchMode(!_touchMode); });
        return p;
    }

    function open() {
        var p = _ensurePanel();
        if (p) p.style.display = '';
        var v = el('ldVer');
        if (v) v.textContent = 'app.js ' + _versionOf('script[src*="app.js"]');
        _rerun();
        setTimeout(function () { _rerun(); }, 300); // segunda pasada tras pintar
    }

    function close() {
        var p = el(PANEL_ID);
        if (p) p.style.display = 'none';
        if (_touchMode) _setTouchMode(false);
    }

    window.LiteDiag = { open: open, close: close, run: _rerun, report: _buildReport };
})();
