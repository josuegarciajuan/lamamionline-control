// ═══════════════════════════════════════════════════════════════════════════════
// GPS RADAR — Sonar Submarino (Lite Mode Only)
// Leaflet dark map underneath + Canvas overlay with radar effects.
// 350×350px, 30fps setInterval, ~2ms Canvas draw per frame.
// ═══════════════════════════════════════════════════════════════════════════════

window.GpsRadar = (function () {
    'use strict';

    // ── Configuration ──────────────────────────────────────────────────────────
    var CFG = {
        canvasSize: 350,             // px, square
        fps: 30,                     // target frames per second
        sweepActiveSpeed: 2.094,     // rad/s (~3s per rotation in active mode)
        sweepPassiveSpeed: 0.628,    // rad/s (~10s per rotation in passive mode)
        passiveTimeoutMs: 120000,    // 2 min parked → passive mode
        sweepWidth: 0.392,           // rad (~22.5° arc for the sweep beam)
        trailMax: 100,               // max trail points
        trailFadeHours: 1.5,         // trail fully fades after this many hours
        pulseSpeed: 3.0,             // position dot pulse speed
        rangeKmDefault: 3,           // default radar range in km
        maxTorpedos: 6,              // torpedo tubes
        termoclinaDuration: 3000,    // ms for termoclina visual effect
        maxDepthRecordKey: 'gps_radar_max_depth',
        homeKey: 'gps_radar_home',
        torpedosKey: 'gps_radar_torpedos',
        trailKey: 'gps_radar_trail',
        // Colors — green phosphor palette
        colBg: '#000a00',
        colBgGlow: '#001a00',
        colRing: '#0a3a0a',
        colCompass: '#0a4a0a',
        colCompassN: '#00cc33',
        colTrail: '#00cc33',
        colTrailBright: '#00ff44',
        colSweepBright: '#00ff44',
        colSweepFade: '#002200',
        colPosition: '#00ff44',
        colTorpedo: '#ffaa00',
        colNoise: '#00ff44',
        colDepthGauge: '#00cc33',
        colDepthDanger: '#ff3300',
        colSignalGood: '#00ff44',
        colSignalBad: '#ff4400',
        colTermoclina: '#ff3300',
    };

    // ── State ───────────────────────────────────────────────────────────────────
    var canvas = null;
    var ctx = null;
    var centerX = 0;
    var centerY = 0;
    var radius = 0;
    var running = false;
    var loopTimer = null;
    var lastFrameTs = 0;
    var sweepAngle = 0;              // radians, current beam angle

    // ── Leaflet map state ──
    var _leafletMap = null;          // L.map instance
    var _leafletReady = false;       // true when Leaflet JS loaded
    var _leafletInitStarted = false; // true after first _lazyInitMap() call
    var _mapZoom = 17;              // zoom level (closer default for gps radar)
    var sweepSpeed = CFG.sweepActiveSpeed;
    var passiveMode = false;

    var currentPos = null;           // { lat, lng, accuracy, ts }
    var prevPos = null;              // previous position for heading/speed
    var speedKmh = 0;
    var headingDeg = 0;
    var depthKm = 0;
    var signalPct = 0;
    var rangeKm = CFG.rangeKmDefault;

    var trail = [];                  // [{ lat, lng, ts }] — ring buffer
    var torpedos = [];               // [{ lat, lng, label, ts }]
    var homeLat = null;
    var homeLng = null;
    var homeLabel = '';
    var maxDepthKm = 0;              // personal record

    // Termoclina state
    var termoclinaActive = false;
    var termoclinaStartTs = 0;

    // Long-press for torpedo firing
    var longPressTimer = null;
    var longPressActive = false;
    var longPressX = 0;
    var longPressY = 0;

    // ── Math helpers ────────────────────────────────────────────────────────────
    function degToRad(d) { return d * 0.0174533; }
    function radToDeg(r) { return r * 57.29578; }
    function clamp(v, lo, hi) { return v < lo ? lo : (v > hi ? hi : v); }

    function dist2D(lat1, lng1, lat2, lng2) {
        var dy = (lat2 - lat1) * 111320;
        var dx = (lng2 - lng1) * (111320 * Math.cos(lat1 * 0.0174533));
        return Math.sqrt(dx * dx + dy * dy);
    }

    function bearingDeg(lat1, lng1, lat2, lng2) {
        var dLng = (lng2 - lng1) * 0.0174533;
        var y = Math.sin(dLng) * Math.cos(lat2 * 0.0174533);
        var x = Math.cos(lat1 * 0.0174533) * Math.sin(lat2 * 0.0174533) -
                Math.sin(lat1 * 0.0174533) * Math.cos(lat2 * 0.0174533) * Math.cos(dLng);
        var brng = Math.atan2(y, x) * 57.29578;
        return (brng + 360) % 360;
    }

    // ── Projection: lat/lng → canvas x,y (relative to current position) ──────
    function project(lat, lng) {
        if (!currentPos) return { x: centerX, y: centerY, visible: false };
        var metersPerDegLat = 111320;
        var metersPerDegLng = 111320 * Math.cos(currentPos.lat * 0.0174533);
        var metersPerPixel = (rangeKm * 1000) / radius;
        var dx = (lng - currentPos.lng) * metersPerDegLng / metersPerPixel;
        var dy = (currentPos.lat - lat) * metersPerDegLat / metersPerPixel;
        var x = centerX + dx;
        var y = centerY + dy;
        var distFromCenter = Math.sqrt(dx * dx + dy * dy);
        return { x: x, y: y, visible: distFromCenter <= radius };
    }

    // ── Persistence ─────────────────────────────────────────────────────────────
    function saveState() {
        try {
            if (homeLat !== null && homeLng !== null) {
                localStorage.setItem(CFG.homeKey, JSON.stringify({
                    lat: homeLat, lng: homeLng, label: homeLabel
                }));
            }
            localStorage.setItem(CFG.torpedosKey, JSON.stringify(torpedos));
            localStorage.setItem(CFG.trailKey, JSON.stringify(trail.slice(-50)));
            localStorage.setItem(CFG.maxDepthRecordKey, String(maxDepthKm));
        } catch (e) {}
    }

    function loadState() {
        try {
            var h = localStorage.getItem(CFG.homeKey);
            if (h) {
                var hd = JSON.parse(h);
                homeLat = hd.lat; homeLng = hd.lng; homeLabel = hd.label || '';
            }
            var t = localStorage.getItem(CFG.torpedosKey);
            if (t) { torpedos = JSON.parse(t); }
            var tr = localStorage.getItem(CFG.trailKey);
            if (tr) { trail = JSON.parse(tr); }
            var md = localStorage.getItem(CFG.maxDepthRecordKey);
            if (md) { maxDepthKm = parseFloat(md) || 0; }
        } catch (e) {}
    }

    // ── HUD element references (set after DOM is ready) ─────────────────────────
    var hudEls = {};

    function bindHud() {
        hudEls = {
            depthGauge: document.getElementById('gpsDepthFill'),
            depthValue: document.getElementById('gpsDepthValue'),
            signalBars: document.getElementById('gpsSignalBars'),
            signalText: document.getElementById('gpsSignalText'),
            speed: document.getElementById('gpsSpeedDisplay'),
            heading: document.getElementById('gpsHeadingDisplay'),
            coords: document.getElementById('gpsCoordsDisplayFull'),
            torpedoList: document.getElementById('gpsTorpedoList'),
            homeInfo: document.getElementById('gpsHomeInfo'),
            homeLabel: document.getElementById('gpsHomeLabel'),
        };
    }

    // ── HUD update ──────────────────────────────────────────────────────────────
    function updateHud() {
        if (!hudEls.depthGauge) bindHud();
        if (!hudEls.depthGauge) return;
        var h = hudEls;

        // Depth gauge
        var depthPct = homeLat ? Math.min(100, (depthKm / rangeKm) * 100) : 0;
        var depthColor = (depthKm > maxDepthKm && maxDepthKm > 0) ?
            CFG.colDepthDanger : CFG.colDepthGauge;
        if (h.depthGauge) { h.depthGauge.style.height = depthPct + '%'; h.depthGauge.style.backgroundColor = depthColor; }
        if (h.depthValue) h.depthValue.textContent = depthKm.toFixed(1) + ' km';

        // Signal bars (0-10 bars)
        if (h.signalBars) {
            var bars = h.signalBars.children;
            var barsOn = Math.round(signalPct / 10);
            for (var i = 0; i < bars.length; i++) {
                var on = i < barsOn;
                bars[i].className = 'gps-signal-bar' + (on ? ' gps-signal-bar-on' : '');
                if (on) {
                    var t = i / 9; // 0 to 1
                    bars[i].style.backgroundColor = 'rgb(' +
                        Math.round(255 * (1 - t) * 0.8) + ',' +
                        Math.round(68 * (1 - t) + 255 * t) + ',0)';
                } else {
                    bars[i].style.backgroundColor = '';
                }
            }
        }
        if (h.signalText) h.signalText.textContent = Math.round(signalPct) + '%';

        // Speed
        if (h.speed) h.speed.textContent = speedKmh > 0 ? Math.round(speedKmh) : '--';

        // Heading
        if (h.heading) {
            if (headingDeg >= 0 && speedKmh > 0.5) {
                var dirs = ['N', 'NE', 'E', 'SE', 'S', 'SO', 'O', 'NO'];
                h.heading.textContent = dirs[Math.round(headingDeg / 45) % 8] + ' ' + Math.round(headingDeg);
            } else {
                h.heading.textContent = '--';
            }
        }

        // Coordinates
        if (h.coords) {
            h.coords.textContent = currentPos ?
                currentPos.lat.toFixed(5) + '°N  ' + currentPos.lng.toFixed(5) + '°W' : '--';
        }

        // Torpedo list
        if (h.torpedoList) {
            if (torpedos.length === 0) {
                h.torpedoList.innerHTML = '<span class="yt-gps-hud-empty">Sin torpedos</span>';
            } else {
                var html = '';
                for (var i = 0; i < torpedos.length; i++) {
                    var t = torpedos[i];
                    var dist = currentPos ? dist2D(currentPos.lat, currentPos.lng, t.lat, t.lng) : 0;
                    html += '<div class="gps-torpedo-row" data-index="' + i + '">' +
                        '<span class="gps-torpedo-icon">▼</span>' +
                        '<span class="gps-torpedo-label">' + escHtml(t.label) + '</span>' +
                        '<span class="gps-torpedo-dist">' + (dist / 1000).toFixed(1) + 'km</span>' +
                        '<button class="gps-torpedo-del" data-index="' + i + '" title="Eliminar torpedo">&times;</button>' +
                        '</div>';
                }
                h.torpedoList.innerHTML = html;
            }
        }

        // Home info
        if (h.homeInfo && homeLat !== null) {
            h.homeInfo.style.display = 'block';
            if (h.homeLabel) h.homeLabel.textContent = homeLabel || (homeLat.toFixed(4) + ', ' + homeLng.toFixed(4));
        }
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

    // ── Leaflet map (lazy load + auto-center) ────────────────────────────────
    function _lazyInitMap() {
        if (_leafletInitStarted) return;
        _leafletInitStarted = true;

        // Leaflet CSS (idempotent)
        if (!document.getElementById('leaflet-css')) {
            var lc = document.createElement('link');
            lc.id = 'leaflet-css';
            lc.rel = 'stylesheet';
            lc.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            lc.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
            lc.crossOrigin = '';
            document.head.appendChild(lc);
        }

        function _onLeafletReady() {
            _leafletReady = true;
            _ensureMap();  // try to create map now that Leaflet is loaded
        }

        if (window.L) {
            _onLeafletReady();
        } else {
            var ls = document.createElement('script');
            ls.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            ls.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
            ls.crossOrigin = '';
            ls.onload = _onLeafletReady;
            document.head.appendChild(ls);
        }
    }

    // Create map when both Leaflet + position are available (idempotent)
    function _ensureMap() {
        if (!_leafletReady) return;
        if (_leafletMap) return;
        if (!currentPos) return;

        var mapDiv = document.getElementById('gpsRadarMapInner');
        if (!mapDiv) return;

        _leafletMap = L.map(mapDiv, {
            attributionControl: false,
            zoomControl: false,
            dragging: false,
            touchZoom: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            boxZoom: false,
            keyboard: false,
            zoom: _mapZoom
        }).setView([currentPos.lat, currentPos.lng], _mapZoom);

        // Force dark background (JS overrides any Leaflet CSS)
        mapDiv.style.backgroundColor = '#000a00';

        // OSM tiles (reliable, always available) — tinted green via CSS filter
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19
        }).addTo(_leafletMap);

        // Force redraw after becoming visible
        setTimeout(function () { if (_leafletMap) _leafletMap.invalidateSize(); }, 200);
    }

    function _updateMapCenter(lat, lng) {
        _ensureMap();             // create map if not yet done
        if (!_leafletMap) return;
        _leafletMap.setView([lat, lng], _leafletMap.getZoom(), { animate: false });
    }

    // ── Drawing functions ───────────────────────────────────────────────────────
    function drawDistanceRings() {
        var ringKm = [0.5, 1, 2, 3, 5];
        ctx.lineWidth = 0.5;
        for (var i = 0; i < ringKm.length; i++) {
            var r = (ringKm[i] / rangeKm) * radius;
            if (r > radius) break;
            ctx.beginPath();
            ctx.arc(centerX, centerY, r, 0, Math.PI * 2);
            ctx.strokeStyle = CFG.colRing;
            ctx.stroke();
            // Label for the outermost visible ring
            if (i === ringKm.length - 1 || (ringKm[i + 1] / rangeKm) * radius > radius) {
                ctx.fillStyle = CFG.colRing;
                ctx.font = '9px "Courier New", monospace';
                ctx.fillText(ringKm[i] + ' km', centerX + r - 18, centerY - 4);
            }
        }
    }

    function drawCompass() {
        var marks = ['N', '', 'E', '', 'S', '', 'O', ''];
        ctx.font = '10px "Courier New", monospace';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        for (var i = 0; i < 8; i++) {
            var a = degToRad(i * 45 - 90); // 0° = North at top
            var innerR = radius - 8;
            var outerR = radius;
            var labelR = radius + 12;

            // Tick mark
            ctx.beginPath();
            ctx.moveTo(centerX + Math.cos(a) * innerR, centerY + Math.sin(a) * innerR);
            ctx.lineTo(centerX + Math.cos(a) * outerR, centerY + Math.sin(a) * outerR);
            ctx.strokeStyle = marks[i] === 'N' ? CFG.colCompassN : CFG.colCompass;
            ctx.lineWidth = marks[i] ? 1.5 : 0.8;
            ctx.stroke();

            // Label
            if (marks[i]) {
                ctx.fillStyle = marks[i] === 'N' ? CFG.colCompassN : CFG.colCompass;
                ctx.fillText(marks[i], centerX + Math.cos(a) * labelR, centerY + Math.sin(a) * labelR);
            }
        }
        // Heading indicator triangle (on the circle edge, pointing to current heading)
        if (headingDeg >= 0 && speedKmh > 0.5) {
            var ha = degToRad(headingDeg - 90);
            var hx = centerX + Math.cos(ha) * radius;
            var hy = centerY + Math.sin(ha) * radius;
            ctx.beginPath();
            ctx.moveTo(hx - 5, hy - 3);
            ctx.lineTo(hx + 5, hy);
            ctx.lineTo(hx - 5, hy + 3);
            ctx.closePath();
            ctx.fillStyle = CFG.colPosition;
            ctx.fill();
        }
    }

    function drawTrail() {
        if (trail.length < 2) return;
        var now = Date.now();
        var maxAgeMs = CFG.trailFadeHours * 3600000;

        for (var i = 0; i < trail.length; i++) {
            var t = trail[i];
            var p = project(t.lat, t.lng);
            if (!p.visible) continue;

            var age = now - t.ts;
            var alpha = clamp(1 - (age / maxAgeMs), 0.05, 0.9);

            // Newest points are brighter and larger
            var isRecent = i >= trail.length - 5;
            var dotSize = isRecent ? 3.0 : 2.0;
            var color = isRecent ? CFG.colTrailBright : CFG.colTrail;

            ctx.beginPath();
            ctx.arc(p.x, p.y, dotSize, 0, Math.PI * 2);
            ctx.fillStyle = color;
            ctx.globalAlpha = alpha;
            ctx.fill();
        }
        ctx.globalAlpha = 1;
    }

    function drawTorpedos() {
        var now = Date.now();
        for (var i = 0; i < torpedos.length; i++) {
            var t = torpedos[i];
            var p = project(t.lat, t.lng);
            if (!p.visible) {
                // Draw at edge of radar in the direction of the torpedo
                var dx = p.x - centerX;
                var dy = p.y - centerY;
                var d = Math.sqrt(dx * dx + dy * dy);
                if (d > 0) {
                    p.x = centerX + (dx / d) * (radius - 10);
                    p.y = centerY + (dy / d) * (radius - 10);
                }
            }

            // Pulsing diamond
            var pulse = 1 + 0.15 * Math.sin(now * 0.004 + i);
            var s = 7 * pulse;
            ctx.beginPath();
            ctx.moveTo(p.x, p.y - s);
            ctx.lineTo(p.x + s, p.y);
            ctx.lineTo(p.x, p.y + s);
            ctx.lineTo(p.x - s, p.y);
            ctx.closePath();
            ctx.fillStyle = CFG.colTorpedo;
            ctx.globalAlpha = 0.8;
            ctx.fill();
            ctx.strokeStyle = CFG.colTorpedo;
            ctx.lineWidth = 1;
            ctx.globalAlpha = 1;
            ctx.stroke();

            // Label
            ctx.font = '8px "Courier New", monospace';
            ctx.textAlign = 'center';
            ctx.fillStyle = CFG.colTorpedo;
            ctx.fillText(t.label, p.x, p.y - 12);
        }
    }

    function drawSweep(now) {
        // Draw the green sweep beam
        var halfW = CFG.sweepWidth / 2;
        var startA = sweepAngle - halfW;
        var endA = sweepAngle + halfW;

        // We draw a pie wedge and use a gradient for the sweep effect
        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.arc(centerX, centerY, radius, startA, endA);
        ctx.closePath();

        // Radial gradient from center to edge
        var grad = ctx.createRadialGradient(centerX, centerY, 0, centerX, centerY, radius);
        grad.addColorStop(0, 'rgba(0,255,68,0)');
        grad.addColorStop(0.7, 'rgba(0,255,68,0.03)');
        grad.addColorStop(0.95, 'rgba(0,255,68,0.12)');
        grad.addColorStop(1, 'rgba(0,255,68,0)');
        ctx.fillStyle = grad;
        ctx.fill();

        // Bright leading edge line
        var lx = centerX + Math.cos(sweepAngle + halfW) * radius;
        var ly = centerY + Math.sin(sweepAngle + halfW) * radius;
        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.lineTo(lx, ly);
        ctx.strokeStyle = CFG.colSweepBright;
        ctx.lineWidth = 1.2;
        ctx.globalAlpha = 0.7;
        ctx.stroke();
        ctx.globalAlpha = 1;
    }

    function drawPosition(now) {
        // Pulsing dot at center
        var pulse = 1 + 0.3 * Math.sin(now * 0.001 * CFG.pulseSpeed);
        var rOuter = 6 * pulse;
        var rInner = 2;

        // Outer glow
        var glow = ctx.createRadialGradient(centerX, centerY, rInner, centerX, centerY, rOuter);
        glow.addColorStop(0, 'rgba(0,255,68,1)');
        glow.addColorStop(0.4, 'rgba(0,255,68,0.5)');
        glow.addColorStop(1, 'rgba(0,255,68,0)');
        ctx.beginPath();
        ctx.arc(centerX, centerY, rOuter, 0, Math.PI * 2);
        ctx.fillStyle = glow;
        ctx.fill();

        // Core dot
        ctx.beginPath();
        ctx.arc(centerX, centerY, rInner, 0, Math.PI * 2);
        ctx.fillStyle = '#ffffff';
        ctx.fill();
    }

    function drawNoise(now) {
        // Static noise: proportional to (100 - signalPct)
        var noiseLevel = (100 - signalPct) / 100;
        if (noiseLevel < 0.05) return;

        var numParticles = Math.floor(noiseLevel * 60);
        var seed = Math.floor(now / 200); // changes every 200ms
        var pseudoRand = function (n) {
            var x = Math.sin(seed * 12.9898 + n * 78.233) * 43758.5453;
            return x - Math.floor(x);
        };

        ctx.fillStyle = CFG.colNoise;
        for (var i = 0; i < numParticles; i++) {
            var a = pseudoRand(i) * Math.PI * 2;
            var r = pseudoRand(i + 100) * radius;
            var x = centerX + Math.cos(a) * r;
            var y = centerY + Math.sin(a) * r;
            ctx.globalAlpha = 0.15 + pseudoRand(i + 200) * 0.4 * noiseLevel;
            ctx.fillRect(x - 0.5, y - 0.5, 1, 1);
        }
        ctx.globalAlpha = 1;
    }

    function drawTermoclina(now) {
        if (!termoclinaActive) return;
        var elapsed = now - termoclinaStartTs;
        if (elapsed > CFG.termoclinaDuration) {
            termoclinaActive = false;
            return;
        }
        var t = elapsed / CFG.termoclinaDuration;
        var intensity = t < 0.3 ? t / 0.3 : (1 - (t - 0.3) / 0.7);

        // Distortion: offset the top portion of the radar
        var offsetY = Math.sin(t * 15) * 4 * intensity;
        ctx.save();
        ctx.translate(0, offsetY);

        // Red vignette overlay
        var vignette = ctx.createRadialGradient(centerX, centerY, radius * 0.6, centerX, centerY, radius);
        vignette.addColorStop(0, 'rgba(255,50,0,0)');
        vignette.addColorStop(1, 'rgba(255,50,0,' + (0.35 * intensity) + ')');
        ctx.beginPath();
        ctx.rect(0, 0, CFG.canvasSize, CFG.canvasSize);
        ctx.fillStyle = vignette;
        ctx.fill();

        // TERMOCLINA text
        ctx.font = 'bold 11px "Courier New", monospace';
        ctx.textAlign = 'center';
        ctx.fillStyle = 'rgba(255,50,0,' + intensity + ')';
        ctx.fillText('TERMOCLINA', centerX, centerY - 30);
        ctx.fillText('AGUAS DESCONOCIDAS', centerX, centerY - 14);
        ctx.restore();
    }

    // ── Main draw ───────────────────────────────────────────────────────────────
    function draw(now) {
        if (!ctx) return;
        ctx.clearRect(0, 0, CFG.canvasSize, CFG.canvasSize);

        drawDistanceRings();
        drawCompass();
        drawTrail();
        drawTorpedos();
        drawSweep(now);
        drawNoise(now);
        drawPosition(now);
        drawTermoclina(now);
    }

    // ── Main loop ───────────────────────────────────────────────────────────────
    function loop() {
        if (!running) return;
        var now = Date.now();
        var dt = lastFrameTs ? (now - lastFrameTs) / 1000 : 1 / CFG.fps;
        lastFrameTs = now;

        // Update sweep angle
        sweepAngle += sweepSpeed * dt;
        if (sweepAngle > Math.PI * 2) sweepAngle -= Math.PI * 2;

        // Transition sweep speed (passive ↔ active)
        var targetSpeed = passiveMode ? CFG.sweepPassiveSpeed : CFG.sweepActiveSpeed;
        sweepSpeed += (targetSpeed - sweepSpeed) * 0.05;

        draw(now);
        updateHud();
    }

    // ── Public API ──────────────────────────────────────────────────────────────
    var self = {
        init: function (canvasId) {
            canvas = document.getElementById(canvasId);
            if (!canvas) return false;

            // Set canvas size
            canvas.width = CFG.canvasSize;
            canvas.height = CFG.canvasSize;
            canvas.style.width = CFG.canvasSize + 'px';
            canvas.style.height = CFG.canvasSize + 'px';

            ctx = canvas.getContext('2d');
            centerX = CFG.canvasSize / 2;
            centerY = CFG.canvasSize / 2;
            radius = CFG.canvasSize / 2 - 20; // leave room for compass labels

            loadState();
            bindHud();

            // Lazy-load Leaflet for the map underneath the radar
            _lazyInitMap();

            // Long-press handler for torpedo firing
            self._bindTouch();

            // Click on torpedo list items
            document.addEventListener('click', function (e) {
                var delBtn = e.target.closest('.gps-torpedo-del');
                if (delBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    var idx = parseInt(delBtn.getAttribute('data-index'), 10);
                    if (!isNaN(idx)) self.removeTorpedo(idx);
                }
            });

            // Zoom button bindings
            var zoomInBtn = document.getElementById('gpsZoomInBtn');
            var zoomOutBtn = document.getElementById('gpsZoomOutBtn');
            if (zoomInBtn) zoomInBtn.addEventListener('click', function (e) { e.stopPropagation(); self.zoomIn(); });
            if (zoomOutBtn) zoomOutBtn.addEventListener('click', function (e) { e.stopPropagation(); self.zoomOut(); });

            return true;
        },

        start: function () {
            if (running) return;
            if (!ctx) return;
            running = true;
            lastFrameTs = 0;

            // Determine initial passive mode
            if (currentPos) {
                passiveMode = (Date.now() - currentPos.ts) > CFG.passiveTimeoutMs;
            } else {
                passiveMode = true;
            }

            loopTimer = setInterval(loop, 1000 / CFG.fps);
        },

        stop: function () {
            running = false;
            if (loopTimer) {
                clearInterval(loopTimer);
                loopTimer = null;
            }
        },

        updatePosition: function (lat, lng, accuracy, ts) {
            if (!lat || !lng) return;

            prevPos = currentPos ? {
                lat: currentPos.lat,
                lng: currentPos.lng,
                ts: currentPos.ts
            } : null;

            currentPos = { lat: lat, lng: lng, accuracy: accuracy || 0, ts: ts || Date.now() };

            // Calculate speed (km/h)
            if (prevPos && prevPos.ts) {
                var dSec = (currentPos.ts - prevPos.ts) / 1000;
                if (dSec > 0 && dSec < 600) { // ignore if more than 10 min gap
                    var dMeters = dist2D(prevPos.lat, prevPos.lng, lat, lng);
                    speedKmh = (dMeters / dSec) * 3.6;
                }
            }

            // Calculate heading
            if (prevPos && speedKmh > 0.5) {
                headingDeg = bearingDeg(prevPos.lat, prevPos.lng, lat, lng);
            }

            // Calculate depth (distance from home)
            if (homeLat !== null && homeLng !== null) {
                var newDepth = dist2D(homeLat, homeLng, lat, lng) / 1000;
                depthKm = newDepth;

                // Check for new max depth → TERMOCLINA!
                if (maxDepthKm > 0 && newDepth > maxDepthKm * 1.02) {
                    maxDepthKm = newDepth;
                    termoclinaActive = true;
                    termoclinaStartTs = Date.now();
                } else if (newDepth > maxDepthKm) {
                    maxDepthKm = newDepth;
                }
            }

            // Signal quality from accuracy
            signalPct = clamp((50 - (accuracy || 50)) / 50 * 100, 0, 100);

            // Exit passive mode if we're moving
            if (speedKmh > 0.5) {
                passiveMode = false;
            }

            // Add to trail
            trail.push({ lat: lat, lng: lng, ts: currentPos.ts });
            if (trail.length > CFG.trailMax) trail.shift();

            // Persist
            saveState();

            // Update Leaflet map center
            _updateMapCenter(lat, lng);

            // Update header coords display
            var coordsEl = document.getElementById('gpsCoordsDisplay');
            if (coordsEl) {
                coordsEl.textContent = lat.toFixed(5) + ', ' + lng.toFixed(5);
            }
        },

        setHome: function (lat, lng, label) {
            homeLat = lat;
            homeLng = lng;
            homeLabel = label || '';
            // Recalculate depth
            if (currentPos) {
                depthKm = dist2D(homeLat, homeLng, currentPos.lat, currentPos.lng) / 1000;
                if (depthKm > maxDepthKm) maxDepthKm = depthKm;
            }
            saveState();
            updateHud();
        },

        fireTorpedo: function (lat, lng, label) {
            if (torpedos.length >= CFG.maxTorpedos) {
                // Replace oldest
                torpedos.shift();
            }
            torpedos.push({ lat: lat, lng: lng, label: label || ('T' + (torpedos.length + 1)), ts: Date.now() });
            saveState();
            updateHud();
        },

        removeTorpedo: function (index) {
            if (index >= 0 && index < torpedos.length) {
                torpedos.splice(index, 1);
                saveState();
                updateHud();
            }
        },

        getCurrentPosition: function () {
            return currentPos ? { lat: currentPos.lat, lng: currentPos.lng } : null;
        },

        get running() { return running; },

        // ── Touch handler for long-press torpedo firing ──
        _bindTouch: function () {
            if (!canvas) return;
            var self = this;

            canvas.addEventListener('touchstart', function (e) {
                if (!running) return;
                var touch = e.touches[0];
                var rect = canvas.getBoundingClientRect();
                longPressX = touch.clientX - rect.left;
                longPressY = touch.clientY - rect.top;

                // Scale to canvas coordinates
                var scaleX = CFG.canvasSize / rect.width;
                var scaleY = CFG.canvasSize / rect.height;
                longPressX *= scaleX;
                longPressY *= scaleY;

                longPressActive = false;
                clearTimeout(longPressTimer);
                longPressTimer = setTimeout(function () {
                    longPressActive = true;
                    self._handleLongPress(longPressX, longPressY);
                }, 600);
            }, { passive: false });

            canvas.addEventListener('touchend', function () {
                clearTimeout(longPressTimer);
                longPressActive = false;
            });

            canvas.addEventListener('touchmove', function () {
                clearTimeout(longPressTimer);
            });

            // Mouse support (desktop testing)
            canvas.addEventListener('mousedown', function (e) {
                if (!running) return;
                var rect = canvas.getBoundingClientRect();
                longPressX = (e.clientX - rect.left) * (CFG.canvasSize / rect.width);
                longPressY = (e.clientY - rect.top) * (CFG.canvasSize / rect.height);
                longPressActive = false;
                clearTimeout(longPressTimer);
                longPressTimer = setTimeout(function () {
                    longPressActive = true;
                    self._handleLongPress(longPressX, longPressY);
                }, 600);
            });

            canvas.addEventListener('mouseup', function () {
                clearTimeout(longPressTimer);
                longPressActive = false;
            });
        },

        _handleLongPress: function (x, y) {
            if (!currentPos) return;

            var dx = x - centerX;
            var dy = y - centerY;
            var distFromCenter = Math.sqrt(dx * dx + dy * dy);

            if (distFromCenter < 10) {
                // Too close to center → fire at current position
                var label = 'T' + (torpedos.length + 1);
                this.fireTorpedo(currentPos.lat, currentPos.lng, label);
                return;
            }

            // Direction and distance
            var angle = Math.atan2(-dy, dx); // atan2: x,y, keeping our coordinate system
            // Convert to geographic bearing (0°=North, clockwise)
            var bearing = radToDeg(angle); // canvas angle 0°=East → need to adjust
            // Actually in our projection: dx positive = East, dy negative = North (since y is inverted)
            // atan2(dx, -dy) → east=0, north=PI/2 (90°)
            var geoBearDeg = (90 - radToDeg(angle) + 360) % 360;
            // Actually let me recalculate properly
            var geoAngle = Math.atan2(dx, -dy); // 0=north, CW
            geoBearDeg = (radToDeg(geoAngle) + 360) % 360;

            var fraction = clamp(distFromCenter / radius, 0.1, 1);
            var distanceKm = fraction * rangeKm;
            var distanceMeters = distanceKm * 1000;

            // Project back to lat/lng
            var metersPerDegLat = 111320;
            var metersPerDegLng = 111320 * Math.cos(currentPos.lat * 0.0174533);
            var dLat = (distanceMeters * Math.cos(degToRad(geoBearDeg))) / metersPerDegLat;
            var dLng = (distanceMeters * Math.sin(degToRad(geoBearDeg))) / metersPerDegLng;

            var tLat = currentPos.lat + dLat;
            var tLng = currentPos.lng + dLng;
            var label = 'T' + (torpedos.length + 1);

            this.fireTorpedo(tLat, tLng, label);

            // Visual flash on canvas
            var flashCtx = ctx;
            if (flashCtx) {
                var px = centerX + dx * (fraction);
                var py = centerY + dy * (fraction);
                var flashGrad = flashCtx.createRadialGradient(px, py, 0, px, py, 20);
                flashGrad.addColorStop(0, 'rgba(255,170,0,0.6)');
                flashGrad.addColorStop(1, 'rgba(255,170,0,0)');
                flashCtx.beginPath();
                flashCtx.arc(px, py, 20, 0, Math.PI * 2);
                flashCtx.fillStyle = flashGrad;
                flashCtx.fill();
            }
        },

        zoomIn: function () {
            if (!_leafletMap) return;
            var z = _leafletMap.getZoom();
            if (z < 19) { _mapZoom = z + 1; _leafletMap.setZoom(_mapZoom); }
        },

        zoomOut: function () {
            if (!_leafletMap) return;
            var z = _leafletMap.getZoom();
            if (z > 3) { _mapZoom = z - 1; _leafletMap.setZoom(_mapZoom); }
        },
    };

    return self;
})();
