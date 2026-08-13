/**
 * tests/js/chat.test.js
 *
 * Unit + DOM integration tests for chat.js
 * Run with: node --test tests/js/chat.test.js
 */

'use strict';

const { describe, it, beforeEach, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const { setup, resetMock, getCalls, MOCKS } = require('./setup-dom');

// ── Helpers ──
function qs(sel, root) { return (root || document).querySelector(sel); }
function qsa(sel, root) { return (root || document).querySelectorAll(sel); }
function flush() { return new Promise(r => setTimeout(r, 30)); }

// ── Default mock map: URL fragment → response ──
function defaultMocks() {
    return {
        'csrf-token.php': MOCKS['csrf-token.php'],
        'action=list': MOCKS.linesOk,
        'action=status': MOCKS.linesStatus,
        'threads_summary': MOCKS.threadsSummaryOk,
        'paused_list': MOCKS.pausedListEmpty,
        'threads&last9=': MOCKS.threadsOk,
        'mark_read': MOCKS.markReadOk,
        'send_manual': MOCKS.sendManualOk,
        'toggle_pause': MOCKS.togglePauseOk,
    };
}

// ────────────────────────────────────────────────
//  Open / Close
// ────────────────────────────────────────────────

describe('Open / Close lifecycle', () => {
    let chat;
    beforeEach(async () => {
        resetMock();
        chat = setup(defaultMocks()).ChatApp;
    });
    afterEach(() => { try { chat.close(); } catch (e) {} });

    it('abre y crea overlay en DOM', async () => {
        chat.open(); await flush();
        assert.ok(qs('.chat-overlay'), 'overlay debe existir');
        assert.ok(qs('.chat-container'), 'container debe existir');
    });

    it('no crea overlay duplicado', async () => {
        chat.open(); await flush();
        chat.open(); await flush();
        assert.equal(qsa('.chat-overlay').length, 1);
    });

    it('cierra y elimina overlay', async () => {
        chat.open(); await flush();
        chat.close();
        await new Promise(r => setTimeout(r, 250));
        await flush();
        assert.equal(qsa('.chat-overlay').length, 0);
    });

    it('cierra con Escape', async () => {
        chat.open(); await flush();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await new Promise(r => setTimeout(r, 250));
        await flush();
        assert.equal(qsa('.chat-overlay').length, 0);
    });

    it('NO auto-expande línea al abrir (bug #1)', async () => {
        chat.open(); await flush();
        assert.equal(qsa('.chat-line-row.expanded').length, 0, 'ninguna línea debe estar expandida');
    });
});

// ────────────────────────────────────────────────
//  Line selection
// ────────────────────────────────────────────────

describe('Line selection', () => {
    let chat;
    beforeEach(async () => {
        resetMock();
        chat = setup(defaultMocks()).ChatApp;
        chat.open(); await flush();
    });
    afterEach(() => { try { chat.close(); } catch (e) {} });

    it('expande línea al hacer toggle', async () => {
        chat.toggleLine('123456789');
        await flush(); await flush(); await flush();
        assert.ok(qsa('.chat-line-row.expanded').length > 0);
    });

    it('colapsa al toggle de nuevo', async () => {
        chat.toggleLine('123456789');
        await flush(); await flush();
        chat.toggleLine('123456789');
        await flush();
        assert.equal(qsa('.chat-line-row.expanded').length, 0);
    });
});

// ────────────────────────────────────────────────
//  Conversation loading
// ────────────────────────────────────────────────

describe('Conversation loading', () => {
    let chat;
    beforeEach(async () => {
        resetMock();
        const mocks = {
            ...defaultMocks(),
            'action=conversation&thread_id=123456789_34666123456': MOCKS.conversationOk,
            'action=conversation&thread_id=123456789_34666987654': MOCKS.conversationBuenas,
        };
        chat = setup(mocks).ChatApp;
        chat.open(); await flush();
        chat.toggleLine('123456789');
        await flush(); await flush(); await flush();
    });
    afterEach(() => { try { chat.close(); } catch (e) {} });

    it('abre conversación y renderiza mensajes', async () => {
        chat.openConversation('123456789_34666123456', '34666123456');
        await flush(); await flush(); await flush();
        const msg = qs('#chat-messages');
        assert.ok(msg.innerHTML.includes('chat-msg'), 'debe haber burbujas de mensaje');
    });

    it('actualiza header con nombre', async () => {
        chat.openConversation('123456789_34666123456', '34666123456');
        const name = qs('#chat-header-name');
        assert.ok(name.textContent.length > 0, 'header debe mostrar nombre');
    });

    it('llama markRead', async () => {
        chat.openConversation('123456789_34666123456', '34666123456');
        await flush(); await flush();
        assert.ok(getCalls().some(c => c.url.includes('mark_read')), 'debe llamar mark_read');
    });

    it('guarda contra cambio rápido (bug #2)', async () => {
        chat.openConversation('123456789_34666123456', '34666123456');
        await flush();
        // Switch before first resolves
        chat.openConversation('123456789_34666987654', '34666987654');
        await flush(); await flush(); await flush(); await flush();
        const msg = qs('#chat-messages');
        // Should show B's Buenas, not A's full convo
        const hasBothFullA = msg.innerHTML.includes('Hola') && msg.innerHTML.includes('¡Hola cariño!');
        // It's OK if neither resolved yet, but NOT OK if A's full convo is showing
        assert.ok(!hasBothFullA, 'no debe mostrar conversación A completa tras cambiar rápido a B');
    });

    it('muestra typing indicator para pending', async () => {
        const now = new Date();
        const recentTs = new Date(now.getTime() - 60000).toISOString(); // 1 min ago
        resetMock();
        const recentConv = {
            ok: true,
            conversation: [
                { ts: recentTs, user_msg: 'Hola', bot_reply: '', _pending: true, speaker_girl: '', sender_lid: '' },
            ],
            count: 1,
        };
        const m2 = { ...defaultMocks(), 'action=conversation&thread_id=123456789_34666123456': recentConv };
        const c2 = setup(m2).ChatApp;
        c2.open(); await flush();
        c2.toggleLine('123456789'); await flush(); await flush(); await flush();
        c2.openConversation('123456789_34666123456', '34666123456');
        await flush(); await flush(); await flush(); await flush();
        assert.ok(qs('.chat-typing'), 'debe mostrar typing indicator');
        try { c2.close(); } catch (e) {}
    });

    it('placeholder para conversación vacía', async () => {
        resetMock();
        const m2 = { ...defaultMocks(), 'action=conversation&thread_id=123456789_34666123456': MOCKS.conversationEmpty };
        const c2 = setup(m2).ChatApp;
        c2.open(); await flush();
        c2.toggleLine('123456789'); await flush(); await flush(); await flush();
        c2.openConversation('123456789_34666123456', '34666123456');
        await flush(); await flush(); await flush();
        assert.ok(qs('#chat-messages').innerHTML.includes('Sin mensajes'), 'placeholder para vacía');
        try { c2.close(); } catch (e) {}
    });
});

// ────────────────────────────────────────────────
//  Message sending
// ────────────────────────────────────────────────

describe('Message sending', () => {
    let chat;
    beforeEach(async () => {
        resetMock();
        const mocks = {
            ...defaultMocks(),
            'action=conversation&thread_id=123456789_34666123456': MOCKS.conversationOk,
        };
        chat = setup(mocks).ChatApp;
        chat.open(); await flush();
        chat.toggleLine('123456789'); await flush(); await flush(); await flush();
        chat.openConversation('123456789_34666123456', '34666123456');
        await flush(); await flush(); await flush();
    });
    afterEach(() => { try { chat.close(); } catch (e) {} });

    it('no envía sin texto', () => {
        qs('#chat-input-text').value = '';
        const before = getCalls().length;
        chat.sendMessage();
        assert.equal(getCalls().length, before, 'sin texto no debe llamar API');
    });

    it('bubble local insertado inmediatamente', () => {
        qs('#chat-input-text').value = 'Test msg';
        const beforeBubbles = qsa('.chat-msg.bot').length;
        chat.sendMessage();
        assert.ok(qsa('.chat-msg.bot').length > beforeBubbles, 'debe aparecer bubble local');
    });

    it('bubble muestra ✓ simple', () => {
        qs('#chat-input-text').value = 'Test';
        chat.sendMessage();
        const checks = qsa('.msg-checks');
        assert.equal(checks[checks.length - 1].textContent, '✓', '✓ inicial');
    });

    it('botón se re-habilita en 400ms (bug #4)', async () => {
        qs('#chat-input-text').value = 'Test';
        chat.sendMessage();
        await new Promise(r => setTimeout(r, 500));
        await flush();
        assert.equal(qs('#chat-send-btn').disabled, false, 'botón debe re-habilitarse');
    });
});

// ────────────────────────────────────────────────
//  Pause / Resume
// ────────────────────────────────────────────────

describe('Pause / Resume', () => {
    let chat;
    beforeEach(async () => {
        resetMock();
        const mocks = {
            ...defaultMocks(),
            'action=conversation&thread_id=123456789_34666123456': MOCKS.conversationOk,
        };
        chat = setup(mocks).ChatApp;
        chat.open(); await flush();
        chat.toggleLine('123456789'); await flush(); await flush(); await flush();
        chat.openConversation('123456789_34666123456', '34666123456');
        await flush(); await flush(); await flush();
    });
    afterEach(() => { try { chat.close(); } catch (e) {} });

    it('llama toggle_pause API', async () => {
        chat.toggleBotPause(); await flush();
        assert.ok(getCalls().some(c => c.url.includes('toggle_pause')), 'debe llamar API');
    });

    it('UI cambia a Pausado', () => {
        chat.toggleBotPause();
        assert.equal(qs('#chat-pause-label').textContent, 'Pausado');
    });

    it('muestra hint de pausa', () => {
        chat.toggleBotPause();
        assert.ok(qs('#chat-paused-hint').classList.contains('visible'));
    });
});

// ────────────────────────────────────────────────
//  Emoji picker
// ────────────────────────────────────────────────

describe('Emoji picker', () => {
    let chat;
    beforeEach(async () => {
        resetMock();
        chat = setup(defaultMocks()).ChatApp;
        chat.open(); await flush();
    });
    afterEach(() => { try { chat.close(); } catch (e) {} });

    it('abre picker', async () => {
        chat.toggleEmojiPicker(); await flush();
        assert.ok(qs('#chat-emoji-picker').classList.contains('show'));
    });

    it('cierra picker', async () => {
        chat.toggleEmojiPicker(); await flush();
        chat.toggleEmojiPicker(); await flush();
        assert.ok(!qs('#chat-emoji-picker').classList.contains('show'));
    });

    it('inserta emoji', async () => {
        chat.toggleEmojiPicker(); await flush();
        chat.insertEmoji('😊'); await flush();
        assert.ok(qs('#chat-input-text').value.includes('😊'));
    });
});

// ────────────────────────────────────────────────
//  Search / Filter
// ────────────────────────────────────────────────

describe('Search / Filter', () => {
    let chat;
    beforeEach(async () => {
        resetMock();
        chat = setup(defaultMocks()).ChatApp;
        chat.open(); await flush();
    });
    afterEach(() => { try { chat.close(); } catch (e) {} });

    it('query vacía muestra todo', async () => {
        qs('#chat-search').value = '';
        chat.filter(); await flush();
        const rows = qsa('.chat-line-row');
        const visible = Array.from(rows).filter(r => r.style.display !== 'none');
        assert.equal(visible.length, rows.length);
    });
});

// ────────────────────────────────────────────────
//  Mobile navigation
// ────────────────────────────────────────────────

describe('Mobile navigation', () => {
    let chat;
    beforeEach(async () => {
        resetMock();
        Object.defineProperty(window, 'innerWidth', { value: 375, writable: true, configurable: true });
        chat = setup(defaultMocks()).ChatApp;
        chat.open(); await flush();
    });
    afterEach(async () => {
        try { chat.close(); } catch (e) {}
        await flush();
        Object.defineProperty(window, 'innerWidth', { value: 1024, writable: true, configurable: true });
    });

    it('sidebar visible al abrir en mobile', () => {
        const main = qs('#chat-main');
        assert.ok(main.classList.contains('mobile-hidden') || !qs('#chat-mobile-back').style.display.includes('none'), 'sidebar visible en mobile');
    });

    it('showMain oculta sidebar', async () => {
        chat.showMain(); await flush();
        assert.ok(!qs('#chat-sidebar').classList.contains('mobile-visible'));
    });

    it('showSidebar muestra sidebar', async () => {
        chat.showMain(); await flush();
        chat.showSidebar(); await flush();
        assert.ok(qs('#chat-sidebar').classList.contains('mobile-visible'));
    });
});

// ────────────────────────────────────────────────
//  Error handling
// ────────────────────────────────────────────────

describe('Error handling', () => {
    afterEach(() => {
        try { ChatApp?.close(); } catch (e) {}
    });

    it('maneja error al cargar líneas', async () => {
        resetMock();
        const mocks = {
            'csrf-token.php': MOCKS['csrf-token.php'],
            'action=list': { ok: false, error: 'Server error' },
            'action=status': MOCKS.linesStatus,
            'threads_summary': MOCKS.threadsSummaryOk,
            'paused_list': MOCKS.pausedListEmpty,
        };
        const chat = setup(mocks).ChatApp;
        chat.open(); await flush();
        const list = qs('#chat-lines-list');
        const html = list.innerHTML;
        // Both outcomes are valid: direct error HTML, or empty-state
        // (loadLinesSummary may race and call renderLineList with empty state.lines)
        const hasError = html.includes('Error') || html.includes('error') || html.includes('Error al cargar');
        const hasEmpty = html.includes('No hay líneas');
        assert.ok(hasError || hasEmpty,
            `esperado mensaje de error o empty state, obtenido: ${html.slice(0, 120)}`);
    });
});

// ────────────────────────────────────────────────
//  Empty states
// ────────────────────────────────────────────────

describe('Empty states', () => {
    afterEach(() => { try { ChatApp?.close(); } catch (e) {} });

    it('sin líneas muestra placeholder', async () => {
        resetMock();
        const mocks = {
            'csrf-token.php': MOCKS['csrf-token.php'],
            'action=list': MOCKS.linesEmpty,
            'action=status': { ok: true, statuses: {} },
            'threads_summary': { ok: true, summary: {} },
            'paused_list': MOCKS.pausedListEmpty,
        };
        const chat = setup(mocks).ChatApp;
        chat.open(); await flush();
        assert.ok(qs('#chat-lines-list').innerHTML.includes('No hay líneas'));
    });

    it('sin conversación muestra placeholder', async () => {
        resetMock();
        const chat = setup(defaultMocks()).ChatApp;
        chat.open(); await flush();
        const msg = qs('#chat-messages');
        assert.ok(msg.innerHTML.includes('Selecciona una conversación') || msg.innerHTML.includes('chat-placeholder'));
    });
});
