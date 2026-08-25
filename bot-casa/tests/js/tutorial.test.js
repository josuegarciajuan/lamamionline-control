'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');

function load(status) {
    const calls = [];
    const dom = new JSDOM(`<!doctype html><body>
        <nav id="tabNav">
            <button id="tutorial-anchor-dashboard" data-tab="tab-dashboard">Inicio</button>
            <button id="tutorial-anchor-personality" data-tab="tab-personalidad">Personalidad</button>
            <button id="tutorial-anchor-bot-style" data-tab="tab-personalidad">Estilo</button>
            <button id="tutorial-anchor-rates" data-tab="tab-personalidad">Tarifas</button>
            <button id="tutorial-anchor-services-location" data-tab="tab-personalidad">Servicios</button>
            <button id="tutorial-anchor-girls" data-tab="tab-chicas">Chicas</button>
            <button id="tutorial-anchor-notifications" data-tab="tab-clientes">Avisos</button>
            <button id="tutorial-anchor-lines" data-tab="tab-lineas">Líneas</button>
            <button id="tutorial-anchor-bot-toggle" data-tab="tab-dashboard">Bot</button>
            <button id="tutorial-anchor-chat" data-tab="tab-mensajes">Chat</button>
            <button id="tutorial-anchor-summary" data-tab="tab-dashboard">Resumen</button>
            <div id="tutorial-target-subscription-banner" data-tutorial-next-tab="tab-lineas"
                data-tutorial-subscription-status="trial" data-tutorial-current-day="3"
                data-tutorial-days-left="7" data-tutorial-total-days="10">Banner</div>
        </nav>
    </body>`, { url: 'http://localhost/cliente', runScripts: 'dangerously', pretendToBeVisual: true });
    dom.window._csrf = 'csrf-test';
        dom.window.switchTab = function (tab) {
            dom.window.__switchedTab = tab;
            dom.window.__events.push('tab:' + tab);
        };
        dom.window.__events = [];
        dom.window.ChatApp = {
            open: function () {
                dom.window.__chatOpened = true;
                const overlay = dom.window.document.createElement('div');
                overlay.className = 'chat-overlay';
                dom.window.document.body.appendChild(overlay);
            },
            close: function () {
                dom.window.__chatClosed = true;
                dom.window.__events.push('chat:close');
                const overlay = dom.window.document.querySelector('.chat-overlay');
                if (overlay) overlay.remove();
            }
        };
    dom.window.fetch = function (url, init) {
        calls.push({ url, init });
        if (url.includes('action=status')) return Promise.resolve({ json: () => Promise.resolve(status) });
        return Promise.resolve({ json: () => Promise.resolve({ ok: true, state: status.state }) });
    };
    dom.window.eval(fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'assets', 'tutorial.js'), 'utf8'));
    return { dom, calls };
}

describe('guided tutorial', () => {
    it('shows an accessible dialog for a new user and persists skip', async () => {
        const { dom, calls } = load({ ok: true, state: { completed: false, skipped: false } });
        await new Promise(resolve => setTimeout(resolve, 10));

        const dialog = dom.window.document.querySelector('[role="dialog"]');
        assert.ok(dialog);
        assert.equal(dialog.getAttribute('aria-modal'), 'true');
        dialog.querySelector('[data-tutorial-skip]').click();
        await new Promise(resolve => setTimeout(resolve, 10));
        assert.ok(calls.some(call => call.url.includes('action=skip')));
        dom.window.close();
    });

    it('does not show again after server-side completion', async () => {
        const { dom } = load({ ok: true, state: { completed: true, skipped: false } });
        await new Promise(resolve => setTimeout(resolve, 10));
        assert.equal(dom.window.document.querySelector('[role="dialog"]'), null);
        dom.window.close();
    });

    it('renders all twelve approved stages and resumes the persisted step', async () => {
        const { dom, calls } = load({ ok: true, state: {
            status: 'pending', current_step: 4, version: 1,
            timestamps: { started_at: null, updated_at: null, paused_at: null, completed_at: null, restarted_at: null }
        } });
        await new Promise(resolve => setTimeout(resolve, 10));

        assert.equal(dom.window.document.querySelector('[data-tutorial-count]').textContent, '5 / 12');
        assert.equal(dom.window.document.querySelector('[data-tutorial-title]').textContent, 'Servicios y ubicación');
        assert.equal(dom.window.document.querySelectorAll('.cw-tutorial__blocker').length, 4);
        assert.ok(dom.window.document.querySelector('.cw-tutorial__spotlight'));
        assert.ok(dom.window.document.querySelector('[data-tutorial-exit]'));
        assert.ok(calls.some(call => call.url.includes('action=start')));
        dom.window.document.querySelector('[data-tutorial-next]').click();
        await new Promise(resolve => setTimeout(resolve, 10));
        assert.ok(calls.some(call => call.url.includes('action=step')));
        dom.window.close();
    });

    it('shows trial motivation with dynamic days and routes without activating the bot', async () => {
        const { dom, calls } = load({ ok: true, state: { status: 'pending', current_step: 11 } });
        await new Promise(resolve => setTimeout(resolve, 10));

        assert.equal(dom.window.document.querySelector('[data-tutorial-title]').textContent, 'Tu prueba gratuita');
        assert.match(dom.window.document.querySelector('[data-tutorial-text]').textContent, /Día 3 de 10/);
        assert.match(dom.window.document.querySelector('[data-tutorial-text]').textContent, /quedan 7 días/);
        assert.equal(dom.window.document.querySelector('[data-tutorial-next]').textContent, 'Empezar configuración');
        assert.equal(dom.window.document.querySelector('[data-tutorial-exit]').textContent, 'Terminar por ahora');
        assert.equal(dom.window.document.querySelector('.cw-tutorial__spotlight').hidden, false);

        dom.window.document.querySelector('[data-tutorial-next]').click();
        await new Promise(resolve => setTimeout(resolve, 10));
        assert.equal(dom.window.__switchedTab, 'tab-lineas');
        assert.ok(calls.some(call => call.url.includes('action=complete')));
        assert.notEqual(dom.window.__switchedTab, 'tab-dashboard');
        dom.window.close();
    });

    it('uses neutral fallback copy when the trial banner is unavailable', async () => {
        const { dom } = load({ ok: true, state: { status: 'pending', current_step: 11 } });
        const banner = dom.window.document.querySelector('#tutorial-target-subscription-banner');
        banner.remove();
        await new Promise(resolve => setTimeout(resolve, 10));

        assert.equal(dom.window.document.querySelector('[data-tutorial-text]').textContent,
            'Cuando quieras, empieza la configuración básica para comprobar cuánto trabajo puedes ahorrar y cómo responde tu bot.');
        dom.window.close();
    });

    it('opens ChatApp on Chat and closes it before switching to dashboard Summary', async () => {
        const { dom } = load({ ok: true, state: { status: 'pending', current_step: 9 } });
        await new Promise(resolve => setTimeout(resolve, 30));

        assert.equal(dom.window.document.querySelector('[data-tutorial-title]').textContent, 'Chat');
        assert.equal(dom.window.__chatOpened, true);
        assert.ok(dom.window.document.querySelector('.chat-overlay'));

        dom.window.document.querySelector('[data-tutorial-next]').click();
        await new Promise(resolve => setTimeout(resolve, 50));

        assert.equal(dom.window.__chatClosed, true);
        assert.equal(dom.window.__switchedTab, 'tab-dashboard');
        assert.ok(dom.window.__events.indexOf('chat:close') < dom.window.__events.indexOf('tab:tab-dashboard'));
        assert.equal(dom.window.document.querySelector('.chat-overlay'), null);
        assert.equal(dom.window.document.querySelector('[data-tutorial-title]').textContent, 'Resumen');
        dom.window.close();
    });
});
