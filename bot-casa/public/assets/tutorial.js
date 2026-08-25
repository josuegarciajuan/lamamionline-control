(function () {
    'use strict';

    var state = { step: 0, steps: [], overlay: null, previousFocus: null };
    var active = false;

    function token() { return typeof _csrf !== 'undefined' ? _csrf : ''; }
    function post(action) {
        var data = new FormData();
        data.append('csrf_token', token());
        return fetch('api/tutorial.php?action=' + action, {
            method: 'POST', body: data, credentials: 'same-origin'
        }).then(function (response) { return response.json(); });
    }
    function targetFor(step) { return document.querySelector(step.target); }
    function position() {
        var target = targetFor(state.steps[state.step]);
        var card = state.overlay.querySelector('.cw-tutorial__card');
        var spotlight = state.overlay.querySelector('.cw-tutorial__spotlight');
        if (!target) {
            spotlight.hidden = true;
            card.style.left = '50%'; card.style.top = '50%';
            card.style.transform = 'translate(-50%, -50%)';
            return;
        }
        var rect = target.getBoundingClientRect();
        spotlight.hidden = false;
        spotlight.style.left = Math.max(8, rect.left - 6) + 'px';
        spotlight.style.top = Math.max(8, rect.top - 6) + 'px';
        spotlight.style.width = Math.max(20, rect.width + 12) + 'px';
        spotlight.style.height = Math.max(20, rect.height + 12) + 'px';
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
        state.overlay.querySelector('[data-tutorial-next]').textContent = state.step === state.steps.length - 1 ? 'Terminar' : 'Siguiente';
        position();
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
        var step = state.steps[state.step];
        if (step.action) step.action();
        if (state.step >= state.steps.length - 1) { close('complete'); return; }
        state.step += 1;
        render();
        state.overlay.querySelector('[data-tutorial-next]').focus();
    }
    function show() {
        state.previousFocus = document.activeElement;
        state.steps = [
            { title: 'Bienvenida a CasaWasap', text: 'Te guiamos por lo esencial para dejar tu bot listo. Puedes saltar el tutorial y retomarlo desde aquí más adelante.', target: '#tutorial-anchor-dashboard' },
            { title: 'Define tu personalidad', text: 'En Personalidad ajustas tarifas, zona y estilo de respuesta del bot.', target: '#tutorial-anchor-personality', action: function () { switchTab('tab-personalidad'); } },
            { title: 'Vincula una línea', text: 'En Líneas conectas el WhatsApp que atenderá automáticamente a tus clientes.', target: '#tutorial-anchor-lines', action: function () { switchTab('tab-lineas'); } },
            { title: 'Revisa tus conversaciones', text: 'Abre Chat para consultar conversaciones y responder manualmente cuando lo necesites.', target: '#tutorial-anchor-chat', action: function () { switchTab('tab-mensajes'); if (window.ChatApp) window.ChatApp.open(); } }
        ];
        state.overlay = document.createElement('div');
        state.overlay.className = 'cw-tutorial';
        state.overlay.setAttribute('role', 'dialog');
        state.overlay.setAttribute('aria-modal', 'true');
        state.overlay.setAttribute('aria-labelledby', 'cw-tutorial-title');
        state.overlay.innerHTML = '<div class="cw-tutorial__veil"></div><div class="cw-tutorial__spotlight"></div><section class="cw-tutorial__card"><h2 id="cw-tutorial-title" data-tutorial-title></h2><p data-tutorial-text></p><div class="cw-tutorial__meta"><span data-tutorial-count aria-live="polite"></span><div class="cw-tutorial__actions"><button type="button" class="cw-tutorial__skip" data-tutorial-skip>Omitir</button><button type="button" class="cw-tutorial__next" data-tutorial-next></button></div></div></section>';
        document.body.appendChild(state.overlay);
        state.overlay.querySelector('[data-tutorial-next]').addEventListener('click', next);
        state.overlay.querySelector('[data-tutorial-skip]').addEventListener('click', function () { close('skip'); });
        state.overlay.addEventListener('keydown', function (event) { if (event.key === 'Escape') close('skip'); });
        active = true;
        window.CasaWasapTutorialActive = true;
        window.dispatchEvent(new CustomEvent('casawasap:tutorial-started'));
        render();
        state.overlay.querySelector('[data-tutorial-next]').focus();
    }
    function init() {
        fetch('api/tutorial.php?action=status', { credentials: 'same-origin' })
            .then(function (response) { return response.json(); })
            .then(function (data) { if (data.ok && !data.state.completed && !data.state.skipped) show(); })
            .catch(function () {});
        window.addEventListener('resize', function () { if (active) position(); });
    }
    window.CasaWasapTutorial = { start: show };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
