(function () {
    'use strict';

    var state = { step: 0, steps: [], overlay: null, previousFocus: null };
    var active = false;
    function tutorialEnabled() { return typeof TUTORIAL_ENABLED === 'undefined' || TUTORIAL_ENABLED === true; }

    function token() { return typeof _csrf !== 'undefined' ? _csrf : ''; }
    function nextFrame(callback) {
        (window.requestAnimationFrame || function (fn) { return window.setTimeout(fn, 16); })(callback);
    }
    function post(action, step) {
        var data = new FormData();
        data.append('csrf_token', token());
        if (typeof step === 'number') data.append('step', String(step));
        return fetch('api/tutorial.php?action=' + action, {
            method: 'POST', body: data, credentials: 'same-origin', headers: { 'Accept': 'application/json' }
        }).then(function (response) {
            if (!response.ok) throw new Error('Tutorial request failed');
            return response.json();
        });
    }
    function targetFor(step) { return document.querySelector(step.target); }
    function subscriptionBanner() { return document.querySelector('#tutorial-target-subscription-banner'); }
    function trialMotivationText() {
        var banner = subscriptionBanner();
        if (!banner || banner.dataset.tutorialSubscriptionStatus !== 'trial') {
            return 'Cuando quieras, empieza la configuración básica para comprobar cuánto trabajo puedes ahorrar y cómo responde tu bot.';
        }
        var day = banner.dataset.tutorialCurrentDay;
        var left = banner.dataset.tutorialDaysLeft;
        var total = banner.dataset.tutorialTotalDays;
        if (!day || !left || !total) {
            return 'Tienes una prueba gratuita de 10 días. Empieza la configuración básica para comprobar cuánto trabajo puedes ahorrar y cómo responde tu bot.';
        }
        return 'Tienes una prueba gratuita de 10 días: vas por el Día ' + day + ' de ' + total + ' y quedan ' + left + ' días. Empieza la configuración básica para comprobar cuánto trabajo puedes ahorrar y cómo responde tu bot.';
    }
    function waitForTarget(step, attempts, callback) {
        var target = targetFor(step);
        if (target) {
            callback(target);
            return;
        }
        if (attempts <= 0) { callback(target); return; }
        nextFrame(function () { waitForTarget(step, attempts - 1, callback); });
    }
    function switchTo(step, callback) {
        if (step.tab && typeof switchTab === 'function') switchTab(step.tab);
        if (step.open) {
            var disclosure = document.querySelector(step.open);
            if (disclosure) disclosure.open = true;
        }
        if (step.chat && window.ChatApp && typeof window.ChatApp.open === 'function') window.ChatApp.open();
        // Tabs and ChatApp can render asynchronously; measure after two frames.
        nextFrame(function () { nextFrame(function () { waitForTarget(step, 12, callback); }); });
    }
    function placeBlockers(rect) {
        var blockers = state.overlay.querySelectorAll('.cw-tutorial__blocker');
        var x = Math.max(0, rect.left - 8), y = Math.max(0, rect.top - 8);
        var right = Math.min(window.innerWidth, rect.right + 8), bottom = Math.min(window.innerHeight, rect.bottom + 8);
        var boxes = [
            [0, 0, window.innerWidth, y], [0, bottom, window.innerWidth, window.innerHeight - bottom],
            [0, y, x, bottom - y], [right, y, window.innerWidth - right, bottom - y]
        ];
        for (var i = 0; i < blockers.length; i++) {
            blockers[i].style.left = boxes[i][0] + 'px'; blockers[i].style.top = boxes[i][1] + 'px';
            blockers[i].style.width = Math.max(0, boxes[i][2]) + 'px'; blockers[i].style.height = Math.max(0, boxes[i][3]) + 'px';
        }
    }
    function position(target) {
        var card = state.overlay.querySelector('.cw-tutorial__card');
        var spotlight = state.overlay.querySelector('.cw-tutorial__spotlight');
        if (!target) {
            spotlight.hidden = true;
            card.style.left = '50%'; card.style.top = '50%'; card.style.transform = 'translate(-50%, -50%)';
            return;
        }
        var rect = target.getBoundingClientRect();
        spotlight.hidden = false;
        spotlight.style.left = Math.max(0, rect.left - 6) + 'px'; spotlight.style.top = Math.max(0, rect.top - 6) + 'px';
        spotlight.style.width = Math.max(20, rect.width + 12) + 'px'; spotlight.style.height = Math.max(20, rect.height + 12) + 'px';
        placeBlockers(rect);
        card.style.transform = '';
        card.style.left = Math.min(Math.max(16, rect.left), window.innerWidth - 376) + 'px';
        card.style.top = rect.bottom + 18 < window.innerHeight - 180 ? (rect.bottom + 18) + 'px' : 'auto';
        card.style.bottom = rect.bottom + 18 < window.innerHeight - 180 ? 'auto' : '20px';
    }
    function render() {
        var step = state.steps[state.step];
        state.overlay.querySelector('[data-tutorial-title]').textContent = step.title;
        state.overlay.querySelector('[data-tutorial-text]').textContent = step.text;
        state.overlay.querySelector('[data-tutorial-count]').textContent = (state.step + 1) + ' / ' + state.steps.length;
        var finalStep = state.step === state.steps.length - 1;
        state.overlay.querySelector('[data-tutorial-next]').textContent = finalStep ? 'Empezar configuración' : 'Siguiente';
        state.overlay.querySelector('[data-tutorial-exit]').textContent = finalStep ? 'Terminar por ahora' : 'Salir';
        state.overlay.querySelector('.cw-tutorial__card').classList.toggle('cw-tutorial__card--motivation', finalStep);
        switchTo(step, function (target) {
            if (target && target.scrollIntoView) {
                target.scrollIntoView({ behavior: 'auto', block: 'center', inline: 'nearest' });
            }
            nextFrame(function () { position(target); });
        });
    }
    function close(action) {
        if (!active) return;
        active = false;
        window.CasaWasapTutorialActive = false;
        if (state.overlay) state.overlay.remove();
        if (state.previousFocus && state.previousFocus.focus) state.previousFocus.focus();
        post(action).catch(function () {});
        window.dispatchEvent(new CustomEvent('casawasap:tutorial-finished'));
    }
    function next() {
        if (state.step >= state.steps.length - 1) {
            var banner = subscriptionBanner();
            var nextTab = banner && banner.dataset.tutorialNextTab ? banner.dataset.tutorialNextTab : 'tab-personalidad';
            close('complete');
            if (typeof switchTab === 'function') switchTab(nextTab);
            return;
        }
        state.step += 1;
        post('step', state.step).catch(function () {});
        render();
        state.overlay.querySelector('[data-tutorial-next]').focus();
    }
    function show(serverState) {
        state.previousFocus = document.activeElement;
        state.steps = [
            { title: 'Bienvenida a CasaWasap', text: 'Te enseñamos lo esencial para dejar tu bot listo.', target: '#tutorial-anchor-dashboard', tab: 'tab-dashboard' },
            { title: 'Personalidad', text: 'Aquí defines cómo trabaja y responde tu bot.', target: '#tutorial-anchor-personality', tab: 'tab-personalidad' },
            { title: 'Estilo del bot', text: 'Elige el tono, la voz y el uso de emojis.', target: '#tutorial-target-style', tab: 'tab-personalidad', open: '#tutorial-target-style' },
            { title: 'Tarifas', text: 'Indica tus precios para que las respuestas sean coherentes.', target: '#tutorial-target-rates', tab: 'tab-personalidad', open: '#tutorial-target-rates' },
            { title: 'Servicios y ubicación', text: 'Explica los servicios y la zona donde atiendes.', target: '#tutorial-target-services-location', tab: 'tab-personalidad', open: '#tutorial-target-services-location' },
            { title: 'Chicas', text: 'Añade y activa las chicas que aparecerán en el catálogo.', target: '#tutorial-anchor-girls', tab: 'tab-chicas' },
            { title: 'Notificaciones', text: 'Configura dónde recibir los avisos de nuevos clientes.', target: '#tutorial-anchor-notifications', tab: 'tab-clientes' },
            { title: 'Líneas', text: 'Vincula las líneas de WhatsApp que atenderá el bot.', target: '#tutorial-anchor-lines', tab: 'tab-lineas' },
            { title: 'Encender el bot', text: 'Cuando todo esté configurado, enciende el bot desde aquí.', target: '#tutorial-anchor-bot-toggle', tab: 'tab-dashboard' },
            { title: 'Chat', text: 'Consulta conversaciones y responde manualmente si lo necesitas.', target: '#tutorial-anchor-chat', tab: 'tab-mensajes', chat: true },
            { title: 'Resumen', text: 'Revisa el progreso y vuelve a cualquier sección cuando quieras.', target: '#dashboard-progress', tab: 'tab-dashboard' },
            { title: 'Tu prueba gratuita', text: trialMotivationText(), target: '#tutorial-target-subscription-banner', tab: 'tab-dashboard' }
        ];
        state.step = Math.max(0, Math.min(state.steps.length - 1, Number(serverState && serverState.current_step) || 0));
        state.overlay = document.createElement('div');
        state.overlay.className = 'cw-tutorial'; state.overlay.setAttribute('role', 'dialog'); state.overlay.setAttribute('aria-modal', 'true');
        state.overlay.setAttribute('aria-labelledby', 'cw-tutorial-title'); state.overlay.setAttribute('aria-describedby', 'cw-tutorial-text');
        state.overlay.innerHTML = '<div class="cw-tutorial__veil" aria-hidden="true"></div><div class="cw-tutorial__blocker" aria-hidden="true"></div><div class="cw-tutorial__blocker" aria-hidden="true"></div><div class="cw-tutorial__blocker" aria-hidden="true"></div><div class="cw-tutorial__blocker" aria-hidden="true"></div><div class="cw-tutorial__spotlight" aria-hidden="true"></div><section class="cw-tutorial__card"><h2 id="cw-tutorial-title" data-tutorial-title></h2><p id="cw-tutorial-text" data-tutorial-text></p><div class="cw-tutorial__meta"><span data-tutorial-count aria-live="polite"></span><div class="cw-tutorial__actions"><button type="button" class="cw-tutorial__exit" data-tutorial-exit>Salir</button><button type="button" class="cw-tutorial__skip" data-tutorial-skip>Omitir</button><button type="button" class="cw-tutorial__next" data-tutorial-next></button></div></div></section>';
        document.body.appendChild(state.overlay);
        state.overlay.querySelector('[data-tutorial-next]').addEventListener('click', next);
        state.overlay.querySelector('[data-tutorial-exit]').addEventListener('click', function () { close('pause'); });
        state.overlay.querySelector('[data-tutorial-skip]').addEventListener('click', function () { close('skip'); });
        state.overlay.addEventListener('keydown', function (event) { if (event.key === 'Escape') close('skip'); });
        active = true; window.CasaWasapTutorialActive = true;
        window.dispatchEvent(new CustomEvent('casawasap:tutorial-started'));
        post('start').catch(function () {}); render(); state.overlay.querySelector('[data-tutorial-next]').focus();
    }
    function init() {
        fetch('api/tutorial.php?action=status', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.ok && tutorialEnabled()) {
                    // Only a brand-new client is auto-started. Paused or skipped
                    // tutorials require an explicit manual start.
                    if ((data.state.status === 'pending' || (!data.state.status && !data.state.completed && !data.state.skipped)) && !data.state.completed && !data.state.skipped) show(data.state);
                }
            }).catch(function () {});
        window.addEventListener('resize', function () { if (active) position(targetFor(state.steps[state.step])); });
    }
    window.CasaWasapTutorial = { start: function () { if (tutorialEnabled()) show({ current_step: 0 }); }, pause: function () { return post('pause'); }, restart: function () { return post('restart'); } };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
