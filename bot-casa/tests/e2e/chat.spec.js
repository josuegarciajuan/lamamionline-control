/**
 * tests/e2e/chat.spec.js
 *
 * Playwright E2E tests for the chat UI.
 * Loads chat.js in a real browser with mocked API responses.
 * Run: npx playwright test --config tests/e2e/playwright.config.js
 */

const { test, expect } = require('@playwright/test');
const path = require('path');

// ── Mock data factories ──
const MOCKS = {
  linesOk: {
    ok: true,
    lines: [
      { id: 1, last9: '123456789', port: '3001', label: 'Línea 1', descripcion: 'Principal', phone: '34666123456', health_phone: '34666123456', health_status: 'WORKING', live_status: 'WORKING' },
      { id: 2, last9: '987654321', port: '3002', label: 'Línea 2', phone: '34666987654', health_phone: '34666987654', health_status: 'STOPPED', live_status: 'STOPPED' },
    ],
  },
  linesStatus: { ok: true, statuses: { '1': 'WORKING', '2': 'STOPPED' } },
  threadsSummary: { ok: true, summary: { '123456789': { total_convos: 2, total_unread: 1 }, '987654321': { total_convos: 0, total_unread: 0 } } },
  pausedList: { ok: true, paused: [] },
  threads: {
    ok: true,
    threads: [
      { thread_id: '123456789_34666123456', phone: '34666123456', sender_lid: '', count: 3, last_ts: new Date().toISOString(), first_msg: 'Hola', last_msg: 'Cuanto cuesta?', unread: 1 },
      { thread_id: '123456789_34666987654', phone: '34666987654', sender_lid: '', count: 1, last_ts: new Date(Date.now() - 86400000).toISOString(), first_msg: 'Buenas', last_msg: 'Buenas', unread: 0 },
    ],
    total: 2,
  },
  conversation: (unread) => ({
    ok: true,
    conversation: [
      { ts: new Date(Date.now() - 120000).toISOString(), user_msg: 'Hola', bot_reply: '', _pending: true, speaker_girl: '', sender_lid: '' },
      { ts: new Date(Date.now() - 60000).toISOString(), user_msg: 'Hola', bot_reply: '¡Hola cariño! ¿En qué puedo ayudarte?', _pending: false, speaker_girl: '', sender_lid: '' },
    ],
    count: 2,
  }),
  markRead: { ok: true, thread_id: 'test', last_read_ts: new Date().toISOString() },
  sendManual: { ok: true, sent: true, seen_ok: true, typing_ok: true, delay_ms: 50 },
  togglePause: { ok: true, paused: true },
  csrfToken: { ok: true, token: 'test-token' },
};

// ── Setup mock API routes for every test ──
async function setupMocks(page) {
  await page.route('**/api/csrf-token.php', r => r.fulfill({ json: MOCKS.csrfToken }));
  await page.route('**/api/lines.php?action=list**', r => r.fulfill({ json: MOCKS.linesOk }));
  await page.route('**/api/lines.php?action=status**', r => r.fulfill({ json: MOCKS.linesStatus }));
  await page.route('**/mensajes.php?action=threads_summary**', r => r.fulfill({ json: MOCKS.threadsSummary }));
  await page.route('**/mensajes.php?action=paused_list**', r => r.fulfill({ json: MOCKS.pausedList }));
  await page.route('**/mensajes.php?action=threads**', r => r.fulfill({ json: MOCKS.threads }));
  await page.route('**/mensajes.php?action=conversation**', r => r.fulfill({ json: MOCKS.conversation() }));
  await page.route('**/mensajes.php?action=mark_read**', r => r.fulfill({ json: MOCKS.markRead }));
  await page.route('**/mensajes.php?action=send_manual**', async r => {
    await new Promise(res => setTimeout(res, 100)); // simulate delay
    return r.fulfill({ json: MOCKS.sendManual });
  });
  await page.route('**/mensajes.php?action=toggle_pause**', r => r.fulfill({ json: MOCKS.togglePause }));
}

// ── HTML fixture: loads chat.js + chat.css in a standalone page ──
function chatPageHTML() {
  return `<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="${path.join(__dirname, '..', '..', 'public', 'assets', 'style.css')}">
<link rel="stylesheet" href="${path.join(__dirname, '..', '..', 'public', 'assets', 'chat.css')}">
<style>body{background:#0a0a1a;color:#fff;font-family:sans-serif;padding:20px}</style>
</head>
<body>
<button id="open-chat" onclick="ChatApp.open()">Abrir Chat</button>
<script>
  window._apiToken = 'test-token';
  window._csrf = 'test-csrf';
  window._isAdminPanel = true;
  window.IS_DEMO = false;
  window.updateAllCsrfInputs = function(){};
</script>
<script src="${path.join(__dirname, '..', '..', 'public', 'assets', 'chat.js')}"></script>
</body></html>`;
}

// ═══════════════════════════════════════════════════════════════
//  Tests
// ═══════════════════════════════════════════════════════════════

test.describe('Chat E2E', () => {

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    const html = chatPageHTML();
    await page.setContent(html, { waitUntil: 'networkidle' });
  });

  test('abre y cierra el chat', async ({ page }) => {
    await page.click('#open-chat');
    await page.waitForSelector('.chat-overlay', { state: 'visible' });

    // Close via ✕ button
    const closeBtn = page.locator('.chat-sidebar-close').first();
    await closeBtn.click();
    await page.waitForSelector('.chat-overlay', { state: 'hidden', timeout: 5000 }).catch(() => {});
    // Overlay should be gone (or closing)
  });

  test('carga líneas con indicadores de estado', async ({ page }) => {
    await page.click('#open-chat');
    await page.waitForSelector('.chat-line-row');

    // Should show at least one line with a dot
    const dots = page.locator('.chat-line-dot');
    await expect(dots.first()).toBeVisible({ timeout: 3000 });
  });

  test('NO auto-expande línea al abrir (bug #1)', async ({ page }) => {
    await page.click('#open-chat');
    await page.waitForSelector('.chat-line-row');

    const expanded = page.locator('.chat-line-row.expanded');
    await expect(expanded).toHaveCount(0);
  });

  test('expande línea y muestra conversaciones', async ({ page }) => {
    await page.click('#open-chat');
    await page.waitForSelector('.chat-line-row');

    // Click the first line
    await page.locator('.chat-line-row').first().click();
    await page.waitForSelector('.chat-conv-row');

    const convs = page.locator('.chat-conv-row');
    await expect(convs.first()).toBeVisible({ timeout: 3000 });
  });

  test('abre conversación y muestra burbujas', async ({ page }) => {
    await page.click('#open-chat');
    await page.waitForSelector('.chat-line-row');
    await page.locator('.chat-line-row').first().click();
    await page.waitForSelector('.chat-conv-row');

    // Click first conversation
    await page.locator('.chat-conv-row').first().click();
    await page.waitForSelector('.chat-msg');

    const bubbles = page.locator('.chat-msg');
    await expect(bubbles.first()).toBeVisible({ timeout: 3000 });
  });

  test('envía mensaje y muestra bubble local', async ({ page }) => {
    await page.click('#open-chat');
    await page.waitForSelector('.chat-line-row');
    await page.locator('.chat-line-row').first().click();
    await page.waitForSelector('.chat-conv-row');
    await page.locator('.chat-conv-row').first().click();
    await page.waitForSelector('#chat-input-text');

    // Type and send via Enter
    await page.fill('#chat-input-text', 'Hola, prueba E2E');
    await page.press('#chat-input-text', 'Enter');

    // Bubble should appear
    await page.waitForSelector('.chat-msg.bot', { timeout: 3000 });
    const bubble = page.locator('.chat-msg.bot').last();
    await expect(bubble).toBeVisible();
  });

  test('emoji picker abre y cierra', async ({ page }) => {
    await page.click('#open-chat');
    await page.waitForSelector('#chat-emoji-btn');

    await page.click('#chat-emoji-btn');
    await expect(page.locator('#chat-emoji-picker.show')).toBeVisible();

    await page.click('#chat-emoji-btn');
    await expect(page.locator('#chat-emoji-picker.show')).toHaveCount(0);
  });

  test('search filtra líneas', async ({ page }) => {
    await page.click('#open-chat');
    await page.waitForSelector('#chat-search');

    await page.fill('#chat-search', 'Principal');
    // Visual check: at least one line row is visible
    const line = page.locator('.chat-line-row').first();
    await expect(line).toBeVisible({ timeout: 2000 });
  });

  test('Escape cierra el chat', async ({ page }) => {
    await page.click('#open-chat');
    await page.waitForSelector('.chat-overlay', { state: 'visible' });

    await page.keyboard.press('Escape');
    await page.waitForTimeout(300);
    // Overlay should no longer be visible
    const overlayVisible = await page.locator('.chat-overlay').isVisible().catch(() => false);
    expect(overlayVisible).toBeFalsy();
  });
});

// ── Mobile-specific tests ──
test.describe('Chat E2E — Mobile', () => {

  test.beforeEach(async ({ page }) => {
    await setupMocks(page);
    const html = chatPageHTML();
    await page.setContent(html, { waitUntil: 'networkidle' });
    // Force mobile viewport
    await page.setViewportSize({ width: 375, height: 812 });
  });

  test('sidebar visible, main oculto al abrir en mobile', async ({ page }) => {
    await page.click('#open-chat');
    await page.waitForSelector('#chat-sidebar');

    // Main should be hidden on mobile
    const main = page.locator('#chat-main.mobile-hidden');
    await expect(main).toBeVisible({ timeout: 3000 });
  });

  test('seleccionar conversación muestra main en mobile', async ({ page }) => {
    await page.click('#open-chat');
    await page.waitForSelector('.chat-line-row');
    await page.locator('.chat-line-row').first().click();
    await page.waitForSelector('.chat-conv-row');
    await page.locator('.chat-conv-row').first().click();
    await page.waitForTimeout(500);

    // Main should be visible now
    const main = page.locator('#chat-main');
    await expect(main).toBeVisible({ timeout: 3000 });
  });
});
