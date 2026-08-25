'use strict';

const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');

function load(status) {
    const calls = [];
    const dom = new JSDOM(`<!doctype html><body>
        <button id="tutorial-anchor-dashboard">Inicio</button>
        <button id="tutorial-anchor-personality">Personalidad</button>
        <button id="tutorial-anchor-lines">Líneas</button>
        <button id="tutorial-anchor-chat">Chat</button>
    </body>`, { url: 'http://localhost/cliente', runScripts: 'dangerously', pretendToBeVisual: true });
    dom.window._csrf = 'csrf-test';
    dom.window.switchTab = function () {};
    dom.window.fetch = function (url, init) {
        calls.push({ url, init });
        if (url.includes('action=status')) return Promise.resolve({ json: () => Promise.resolve(status) });
        return Promise.resolve({ json: () => Promise.resolve({ ok: true }) });
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
});
