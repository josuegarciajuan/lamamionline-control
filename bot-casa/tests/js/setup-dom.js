/**
 * tests/js/setup-dom.js
 *
 * Sets up a jsdom environment that mimics a browser page
 * with all the DOM elements chat.js needs.
 */

'use strict';

const { JSDOM } = require('jsdom');
const fs = require('fs');
const path = require('path');

// ── Global mocks ──
let _mockResponses = {};
let _mockSequential = [];
let _calls = [];

function createMockFetch(responses) {
    _mockResponses = responses || {};
    _mockSequential = [];
    _calls = [];
    return mockFetch;
}

async function mockFetch(url, init) {
    _calls.push({ url: typeof url === 'string' ? url : url.url, init, method: init?.method || 'GET' });
    const u = typeof url === 'string' ? url : url.url;

    // Check sequential mocks first
    if (_mockSequential.length > 0) {
        const next = _mockSequential.shift();
        const response = typeof next === 'function' ? next(u, init) : next;
        if (response) {
            return makeResponse(response);
        }
    }

    // Check URL-based mocks
    for (const [pattern, resp] of Object.entries(_mockResponses)) {
        if (u.includes(pattern)) {
            const r = typeof resp === 'function' ? resp(u, init) : resp;
            return makeResponse(r);
        }
    }

    // No mock found — return error to trigger .catch() paths gracefully
    return makeResponse({ ok: false, error: 'Unmocked: ' + u, status: 500 });
}

function makeResponse(data) {
    return {
        status: data.status !== undefined ? data.status : 200,
        ok: data.ok !== undefined ? data.ok : (data.status === undefined || data.status < 400),
        json: () => Promise.resolve(data.body !== undefined ? data.body : data),
        text: () => Promise.resolve(typeof data === 'string' ? data : JSON.stringify(data)),
        headers: new Map(),
    };
}

// ── DOM Setup ──
function createDOM() {
    const dom = new JSDOM(`<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Test</title></head>
<body>
    <div id="test-root"></div>
</body>
</html>`, {
        url: 'http://localhost/panel',
        pretendToBeVisual: true,
        runScripts: 'dangerously',
    });

    const win = dom.window;

    // ── Timer isolation: do NOT override global setTimeout/setInterval ──
    // jsdom has its own timer implementation that works within the window.
    // Overriding globals causes cross-window timer leaks and stack overflows.
    // Instead, we provide mock versions that work seamlessly.

    // Install globals on Node's global scope (needed for DOM APIs)
    global.window = win;
    global.document = win.document;
    global.Node = win.Node;
    global.Element = win.Element;
    global.HTMLElement = win.HTMLElement;
    global.HTMLDivElement = win.HTMLDivElement;
    global.HTMLTextAreaElement = win.HTMLTextAreaElement;
    global.HTMLButtonElement = win.HTMLButtonElement;
    global.HTMLInputElement = win.HTMLInputElement;
    global.DOMParser = win.DOMParser;
    global.Event = win.Event;
    global.MouseEvent = win.MouseEvent;
    global.KeyboardEvent = win.KeyboardEvent;
    global.FormData = win.FormData;
    global.fetch = mockFetch;
    global.XMLHttpRequest = win.XMLHttpRequest;
    
    // IMPORTANT: Use jsdom's native timers via the window, NOT globals.
    // chat.js runs via win.eval() so all timer calls go to win.setTimeout etc.
    // For test code, provide safe wrappers via the window only.
    win.fetch = mockFetch;

    // Install global vars expected by chat.js on the window
    win._apiToken = 'test-token-12345';
    win._csrf = 'test-csrf-token';
    win._isAdminPanel = true;
    win.IS_DEMO = false;
    win.updateAllCsrfInputs = function() {};

    // Mock scrollTo (not implemented in jsdom)
    if (win.Element && win.Element.prototype) {
        win.Element.prototype.scrollTo = function() {};
    }
    if (win.HTMLElement && win.HTMLElement.prototype) {
        win.HTMLElement.prototype.scrollIntoView = function() {};
    }

    // Mock postMessage
    win.parent = win;
    win.postMessage = function() {};

    return dom;
}

// ── Load chat.js ──
function loadChatJS(dom) {
    const win = dom.window;
    const scriptPath = path.join(__dirname, '..', '..', 'public', 'assets', 'chat.js');

    let scriptContent;
    try {
        scriptContent = fs.readFileSync(scriptPath, 'utf-8');
    } catch (e) {
        throw new Error('Cannot read chat.js at ' + scriptPath + ': ' + e.message);
    }

    try {
        // Use eval in the window's context so var declarations attach to window
        win.eval(scriptContent);
    } catch (e) {
        throw new Error('chat.js eval failed: ' + e.message + '\n' + (e.stack || ''));
    }

    if (!win.ChatApp) {
        throw new Error('ChatApp not loaded — chat.js did not export correctly');
    }
}

// ── Test fixture setup ──
function setup(mockResponses) {
    if (mockResponses) {
        createMockFetch(mockResponses);
    }
    const dom = createDOM();
    loadChatJS(dom);
    return { ChatApp: dom.window.ChatApp, dom };
}

// ── Helpers ──
function resetMock() {
    _mockResponses = {};
    _mockSequential = [];
    _calls = [];
}

function addSequentialMock(response) {
    _mockSequential.push(response);
}

function getCalls() {
    return _calls.slice();
}

// ── Common mock data factories ──
const MOCKS = {
    // csrf token
    'csrf-token.php': { ok: true, token: 'fresh-token' },

    // Lines
    linesOk: {
        ok: true,
        lines: [
            { id: 1, last9: '123456789', port: '3001', label: 'Línea Principal',
              descripcion: 'Principal', phone: '34666123456', health_phone: '34666123456',
              health_status: 'WORKING', live_status: 'WORKING' },
            { id: 2, last9: '987654321', port: '3002', label: 'Línea 2',
              phone: '34666987654', health_phone: '34666987654',
              health_status: 'STOPPED', live_status: 'STOPPED' },
        ],
        statuses: {}, // used by status endpoint
    },
    linesEmpty: { ok: true, lines: [], statuses: {} },
    linesStatus: {
        ok: true,
        statuses: { '1': 'WORKING', '2': 'STOPPED' },
    },

    // Threads
    threadsOk: {
        ok: true,
        threads: [
            {
                thread_id: '123456789_34666123456', phone: '34666123456',
                sender_lid: '', count: 3, last_ts: '2026-06-29T10:00:00Z',
                first_msg: 'Hola', last_msg: 'Cuánto cuesta?', unread: 1,
            },
            {
                thread_id: '123456789_34666987654', phone: '34666987654',
                sender_lid: '', count: 1, last_ts: '2026-06-28T08:00:00Z',
                first_msg: 'Buenas', last_msg: 'Buenas', unread: 0,
            },
        ],
        total: 2,
    },
    threadsEmpty: { ok: true, threads: [], total: 0 },

    // Conversation
    conversationOk: {
        ok: true,
        conversation: [
            { ts: '2026-06-29T09:58:00Z', user_msg: 'Hola', bot_reply: '', _pending: true, speaker_girl: '', sender_lid: '' },
            { ts: '2026-06-29T09:59:00Z', user_msg: 'Hola', bot_reply: '¡Hola cariño! ¿En qué puedo ayudarte?', _pending: false, speaker_girl: '', sender_lid: '' },
            { ts: '2026-06-29T10:00:00Z', user_msg: 'Cuánto cuesta?', bot_reply: '', _pending: true, speaker_girl: '', sender_lid: '' },
            { ts: '2026-06-29T10:00:10Z', user_msg: 'Cuánto cuesta?', bot_reply: '50€ la media hora, 80€ la hora 😘', _pending: false, speaker_girl: '', sender_lid: '' },
        ],
        count: 4,
    },
    conversationEmpty: { ok: true, conversation: [], count: 0 },
    conversationBuenas: {
        ok: true,
        conversation: [
            { ts: '2026-06-28T08:00:00Z', user_msg: 'Buenas', bot_reply: 'Hola!', _pending: false, speaker_girl: '', sender_lid: '' },
        ],
        count: 1,
    },

    // Threads summary
    threadsSummaryOk: {
        ok: true,
        summary: {
            '123456789': { total_convos: 2, total_unread: 1 },
            '987654321': { total_convos: 0, total_unread: 0 },
        },
    },

    // Paused
    pausedListEmpty: { ok: true, paused: [] },

    // Send
    sendManualOk: { ok: true, sent: true, seen_ok: true, typing_ok: true, delay_ms: 1200 },
    sendManualFail: { ok: false, error: 'WAHA send failed', seen_ok: false },

    // Mark read
    markReadOk: { ok: true, thread_id: 'test', last_read_ts: '2026-06-29T10:01:00Z' },

    // Toggle pause
    togglePauseOk: { ok: true, paused: true },
    togglePauseResumeOk: { ok: true, paused: false },

    // Generic error
    error: { ok: false, error: 'Server error', status: 500 },
};

module.exports = {
    setup,
    resetMock,
    addSequentialMock,
    getCalls,
    createMockFetch,
    MOCKS,
};
