<?php

declare(strict_types=1);

/**
 * chat.php — Chat Operador (WhatsApp-style PWA)
 *
 * Standalone human-operator chat interface.
 * Access: GET /chat  (open access, auto-authenticated as admin)
 */

define('WASAPBOT_ROOT', dirname(__DIR__));

// ── Autoload ──
$vendorAutoload = WASAPBOT_ROOT . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    spl_autoload_register(function (string $class): void {
        $prefix = 'WasapBot\\';
        $prefixLen = strlen($prefix);
        if (strncmp($prefix, $class, $prefixLen) !== 0) return;
        $file = WASAPBOT_ROOT . '/src/' . str_replace('\\', '/', substr($class, $prefixLen)) . '.php';
        if (file_exists($file)) require_once $file;
    });
}

// ── Session — auto-login as admin ──
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => $isHttps, 'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

// Auto-authenticate as admin for open access — the operator chat always runs as
// the owner (user 1), regardless of any leftover client session (e.g. a previous
// login as a client user would otherwise empty the lines/conversations list).
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['username'] = 'admin';

$userId = (int) ($_SESSION['user_id'] ?? 0);

// ── CSRF token ──
function getCsrfSecret(): string
{
    $secretFile = (defined('WASAPBOT_ROOT') ? WASAPBOT_ROOT : __DIR__) . '/data/.csrf_secret';
    if (file_exists($secretFile)) {
        $secret = trim((string) @file_get_contents($secretFile));
        if (strlen($secret) >= 32) return $secret;
    }
    $secret = bin2hex(random_bytes(32));
    $dir = dirname($secretFile);
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    @file_put_contents($secretFile, $secret, LOCK_EX);
    @chmod($secretFile, 0600);
    return $secret;
}

function generateCsrfToken(): string
{
    $secret = getCsrfSecret();
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    return hash_hmac('sha256', $userId . '|' . date('Y-m-d-H') . floor((int) date('i') / 10), $secret);
}

$csrfToken = generateCsrfToken();
$apiToken = $csrfToken; // _apiToken uses same value

// ── Read bot mode for initial state ──
$configDir = \WasapBot\Bot::resolveUserConfigDir(WASAPBOT_ROOT, $userId);
$config = new \WasapBot\Core\Config($configDir, WASAPBOT_ROOT);
$modeFilePath = (string) $config->get('bot.mode_file', 'data/.bot_mode');
if ($userId > 1) {
    $modeFilePath = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, $modeFilePath);
}
if (!str_starts_with($modeFilePath, '/')) {
    $modeFilePath = WASAPBOT_ROOT . '/' . ltrim($modeFilePath, '/');
}

$botModeRaw = 'unknown';
if (file_exists($modeFilePath)) {
    $content = trim((string) @file_get_contents($modeFilePath));
    if ($content === 'start') $botModeRaw = 'start';
    elseif ($content === 'stop') $botModeRaw = 'stop';
}

$botIsOn = ($botModeRaw === 'start');
$version = (string) max(
    filemtime(__DIR__ . '/assets/chat-operator.js'),
    filemtime(__DIR__ . '/assets/chat-operator.css')
);

// ── HTML ──
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#075e54">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="SuperWasap">
<meta name="mobile-web-app-capable" content="yes">
<meta name="description" content="SuperWasap — Chat de WhatsApp con operador humano + bot asistente">
<link rel="icon" type="image/svg+xml" href="assets/wa-icon.svg?v=<?php echo $version; ?>">
<link rel="apple-touch-icon" href="assets/wa-icon.svg?v=<?php echo $version; ?>">
<link rel="manifest" href="chat-manifest.json?v=<?php echo $version; ?>">
<link rel="stylesheet" href="assets/chat-operator.css?v=<?php echo $version; ?>">
<title>SuperWasap</title>
<style>
/* ── Bot Toggle Bar (top of sidebar header area, always visible) ── */
.bot-control-bar {
  background: <?php echo $botIsOn ? '#1b3a2d' : '#3a1b1b'; ?>;
  padding: 8px 16px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  flex-shrink: 0;
  border-bottom: 1px solid rgba(255,255,255,.06);
  min-height: 44px;
}

.bot-status-label {
  font-size: .82rem;
  font-weight: 700;
  letter-spacing: .04em;
  display: flex;
  align-items: center;
  gap: 8px;
}

.bot-status-label.on { color: #25d366; }
.bot-status-label.off { color: #ef4444; }

.bot-status-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
  flex-shrink: 0;
}

.bot-status-label.on .bot-status-dot {
  background: #25d366;
  box-shadow: 0 0 8px rgba(37,211,102,.6);
  animation: botPulse 2s ease-in-out infinite;
}

.bot-status-label.off .bot-status-dot {
  background: #ef4444;
  box-shadow: 0 0 6px rgba(239,68,68,.4);
}

@keyframes botPulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: .6; transform: scale(1.3); }
}

.bot-toggle {
  padding: 6px 18px;
  border-radius: 20px;
  border: none;
  font-size: .8rem;
  font-weight: 700;
  cursor: pointer;
  letter-spacing: .03em;
  transition: all .15s;
  white-space: nowrap;
}

.bot-toggle.on {
  background: rgba(239,68,68,.2);
  color: #ef4444;
  border: 1px solid rgba(239,68,68,.3);
}

.bot-toggle.on:hover {
  background: rgba(239,68,68,.35);
}

.bot-toggle.off {
  background: rgba(37,211,102,.2);
  color: #25d366;
  border: 1px solid rgba(37,211,102,.3);
}

.bot-toggle.off:hover {
  background: rgba(37,211,102,.35);
}

/* ── Sound toggle ── */
.sound-toggle-btn {
  background: rgba(255,255,255,.06);
  border: 1px solid rgba(255,255,255,.08);
  color: #fff;
  font-size: 1.1rem;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background .15s;
  padding: 0;
  flex-shrink: 0;
}

.sound-toggle-btn:hover { background: rgba(255,255,255,.15); }
.sound-toggle-btn.muted { opacity: .5; }

.mark-all-read-btn {
  background: rgba(37,211,102,.18);
  border: 1px solid rgba(37,211,102,.35);
  color: #25d366;
  font-size: .75rem;
  font-weight: 700;
  padding: 6px 12px;
  border-radius: 16px;
  cursor: pointer;
  white-space: nowrap;
  transition: background .15s;
  flex-shrink: 0;
}

.mark-all-read-btn:hover { background: rgba(37,211,102,.3); }
</style>
</head>
<body>

<div class="app-layout">
    <!-- ── Sidebar ── -->
    <div class="chat-sidebar">
        <!-- Bot control bar -->
        <div class="bot-control-bar" id="bot-control-bar">
            <div class="bot-status-label <?php echo $botIsOn ? 'on' : 'off'; ?>" id="bot-status-label">
                <span class="bot-status-dot"></span>
                <span><?php echo $botIsOn ? 'BOT ENCENDIDO' : 'BOT APAGADO'; ?></span>
            </div>
            <button class="bot-toggle <?php echo $botIsOn ? 'on' : 'off'; ?>" id="bot-toggle-btn"
                    onclick="ChatOperator.toggleBotMode()">
                <?php echo $botIsOn ? 'APAGAR BOT' : 'ENCENDER BOT'; ?>
            </button>
            <button class="sound-toggle-btn" id="sound-toggle-btn" title="Activar/Desactivar sonido"
                    onclick="ChatOperator.toggleSound()">🔔</button>
            <button class="mark-all-read-btn" id="mark-all-read-btn" title="Marcar todas las conversaciones como leídas"
                    onclick="ChatOperator.markAllRead(null)">✓ Todo</button>
        </div>

        <!-- Search -->
        <div class="search-wrap">
            <span class="search-icon">🔍</span>
            <input type="text" class="search-input" id="search-input" placeholder="Buscar conversación..."
                   oninput="ChatOperator.filterConversations()" autocomplete="off">
        </div>

        <!-- Lines and conversations -->
        <div class="lines-list" id="lines-list">
            <div class="chat-loading">
                <div class="spinner"></div>
                <div>Cargando líneas...</div>
            </div>
        </div>
    </div>

    <!-- ── Chat Main ── -->
    <div class="chat-main" id="chat-main">
        <!-- Header -->
        <div class="chat-header">
            <button class="chat-mobile-back" onclick="ChatOperator.backToSidebar()" title="Volver">←</button>
            <div class="chat-header-avatar" id="chat-header-avatar">?</div>
            <div class="chat-header-info">
                <div class="chat-header-name" id="chat-header-name">Selecciona una conversación</div>
                <div class="chat-header-subtitle" id="chat-header-subtitle"></div>
            </div>
            <div class="chat-header-actions">
                <button class="conv-bot-toggle" id="chat-conv-pause-toggle"
                        onclick="ChatOperator.toggleConvPause()" title="Pausar/Reanudar bot en esta conversación"
                        style="display:none">
                    <span>🤖 Auto</span>
                    <span class="pause-pill"></span>
                </button>
            </div>
        </div>

        <!-- Messages -->
        <div class="chat-messages" id="chat-messages">
            <div class="chat-placeholder">
                <div class="chat-placeholder-icon">💬</div>
                <h3>Chat Operador</h3>
                <p>Selecciona una línea y una conversación para empezar a chatear.</p>
            </div>
        </div>

        <!-- Scroll to bottom -->
        <button class="chat-scroll-bottom" id="chat-scroll-bottom" onclick="ChatOperator.scrollToBottom(true)">↓</button>

        <!-- Input area -->
        <div class="chat-input-area" id="chat-input-area">
            <div class="chat-input-row">
                <button class="chat-input-btn" id="chat-emoji-btn" title="Emojis" onclick="ChatOperator.toggleEmojiPicker()">😊</button>
                <textarea id="chat-input-text" class="chat-input-text" placeholder="Escribe un mensaje..."
                          rows="1" oninput="ChatOperator.autoResizeTextarea(this)"
                          onkeydown="ChatOperator.handleInputKey(event)"></textarea>
                <div class="chat-emoji-picker" id="chat-emoji-picker"></div>
                <button class="chat-input-btn chat-attach-btn" id="chat-attach-btn" title="Adjuntar fotos" onclick="ChatOperator.openPhotoPicker()">📎</button>
                <button class="chat-input-send" id="chat-send-btn" title="Enviar" onclick="ChatOperator.sendMessage()">▶</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
// Globals for API authentication
var _apiToken = <?php echo json_encode($apiToken, JSON_UNESCAPED_UNICODE); ?>;
var _csrf = <?php echo json_encode($csrfToken, JSON_UNESCAPED_UNICODE); ?>;
var _userId = <?php echo json_encode($userId, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="assets/chat-operator.js?v=<?php echo $version; ?>"></script>

<!-- PWA install logic -->
<script>
(function() {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js?v=<?php echo $version; ?>')
            .then(function() { console.log('[ChatOperador] SW registered'); })
            .catch(function() {});
    }

    var deferredPrompt = null;
    var installBanner = null;

    window.addEventListener('beforeinstallprompt', function(e) {
        e.preventDefault();
        deferredPrompt = e;
        if (installBanner) installBanner.remove();

        installBanner = document.createElement('div');
        installBanner.style.cssText = 'position:fixed;bottom:20px;left:0;right:0;z-index:9999;display:flex;align-items:center;justify-content:center;padding:0 16px;pointer-events:none;';
        installBanner.innerHTML = '<div style="pointer-events:all;background:#1f2c33;color:#e9edef;padding:12px 20px;border-radius:16px;display:flex;align-items:center;gap:12px;box-shadow:0 8px 30px rgba(0,0,0,.5);border:1px solid rgba(37,211,102,.3);max-width:380px;width:100%">' +
            '<span style="font-size:2rem">💬</span>' +
            '<div style="flex:1;min-width:0"><div style="font-weight:600;font-size:.9rem">Instalar Chat Operador</div><div style="font-size:.75rem;color:var(--wa-muted,#8696a0)">Tenlo en tu escritorio como WhatsApp</div></div>' +
            '<button id="install-btn" style="background:#25d366;color:#000;border:none;padding:8px 16px;border-radius:20px;font-weight:700;font-size:.8rem;cursor:pointer;white-space:nowrap">Instalar</button>' +
        '</div>';
        document.body.appendChild(installBanner);

        document.getElementById('install-btn').addEventListener('click', function() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function() {
                    deferredPrompt = null;
                    if (installBanner) { installBanner.remove(); installBanner = null; }
                });
            } else {
                alert('La instalación rápida no está disponible. Usa "Añadir a pantalla de inicio" desde tu navegador.');
            }
        });
    });

    // Hide banner after install
    window.addEventListener('appinstalled', function() {
        deferredPrompt = null;
        if (installBanner) { installBanner.remove(); installBanner = null; }
    });
})();
</script>
</body>
</html>
