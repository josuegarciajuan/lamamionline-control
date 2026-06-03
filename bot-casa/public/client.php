<?php

declare(strict_types=1);

/**
 * client.php — Panel de cliente para bot-casa multi-usuario.
 *
 * Accesible por:
 *   - Usuarios normales (role=user): ven su propio panel
 *   - Admin con suplantar: ven el panel del usuario suplantado
 *
 * Recibe $clientUserId desde index.php (el ID del usuario cuyo panel se muestra).
 */

// ─────────────────────────────────────────────────────────────────────
//  Bootstrap
// ─────────────────────────────────────────────────────────────────────

define('WASAPBOT_ROOT', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $prefix = 'WasapBot\\';
    $prefixLen = strlen($prefix);
    if (strncmp($prefix, $class, $prefixLen) !== 0) return;
    $relativeClass = substr($class, $prefixLen);
    $file = WASAPBOT_ROOT . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require_once $file;
});

// ── Session check ──
if (session_status() === PHP_SESSION_NONE) session_start();
$isLoggedIn = !empty($_SESSION['user_id']);
if (!$isLoggedIn) { header('Location: login'); exit; }

// ── Determine effective user ID ──
// $clientUserId is set by index.php (for admin suplantar)
// For normal users, it's their own user_id
$clientUserId = isset($clientUserId) ? (int) $clientUserId : (int) ($_SESSION['user_id'] ?? 0);
if ($clientUserId <= 0) { $clientUserId = (int) ($_SESSION['user_id'] ?? 1); }

$um = new \WasapBot\Core\UserManager(WASAPBOT_ROOT);
$clientUser = $um->getUser($clientUserId);
if ($clientUser === null || empty($clientUser['active'])) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Usuario no encontrado</title></head><body style="background:#080d17;color:#f0f3fa;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0"><div style="text-align:center"><h1 style="color:#f87171">Usuario no encontrado</h1><p><a href="panel" style="color:#f59e0b">Volver</a></p></div></body></html>';
    exit;
}

$clientName = h((string) ($clientUser['name'] ?? $clientUser['username'] ?? 'Usuario'));

// ── Load user config ──
$configDir = \WasapBot\Bot::resolveUserConfigDir(WASAPBOT_ROOT, $clientUserId);
$config = new \WasapBot\Core\Config($configDir);

// ── Override data paths for non-admin users (data isolation) ──
if ($clientUserId > 1) {
    $fileKeys = ['files.session_memory', 'files.leads', 'files.reminders', 'files.playbook', 'files.wa_raw_payload', 'files.bot_log', 'bot.mode_file'];
    foreach ($fileKeys as $key) {
        $val = $config->get($key, '');
        if (is_string($val) && $val !== '') {
            $config->set($key, \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $clientUserId, $val));
        }
    }
}

// ── Helpers ──
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function cv(string $key, mixed $default = ''): string {
    global $config;
    $value = $config->get($key, $default);
    if (is_array($value)) return '';
    return h((string) $value);
}
function cva(string $key, mixed $default = []): array {
    global $config; $value = $config->get($key, $default);
    return is_array($value) ? $value : [];
}
function checked(bool $cond): string { return $cond ? 'checked' : ''; }
function selected(bool $cond): string { return $cond ? 'selected' : ''; }

// ── CSRF ──
function getCsrfSecret(): string {
    $secretFile = WASAPBOT_ROOT . '/data/.csrf_secret';
    if (file_exists($secretFile)) {
        $secret = (string) @file_get_contents($secretFile);
        if (strlen($secret) >= 32) return $secret;
    }
    $secret = bin2hex(random_bytes(32));
    @file_put_contents($secretFile, $secret, LOCK_EX);
    @chmod($secretFile, 0600);
    return $secret;
}
/**
 * Generate a CSRF token bound to the current authenticated user and time window.
 * Binding to user_id ensures tokens are not reusable across different user sessions.
 */
function generateCsrfToken(): string {
    $secret = getCsrfSecret();
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    return hash_hmac('sha256', $userId . '|' . date('Y-m-d-H') . floor((int) date('i') / 10), $secret);
}
function validateCsrfToken(string $token): bool {
    $secret = getCsrfSecret();
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $current = hash_hmac('sha256', $userId . '|' . date('Y-m-d-H') . floor((int) date('i') / 10), $secret);
    if (hash_equals($current, $token)) return true;
    $prevSlot = max(0, floor((int) date('i') / 10) - 1);
    $previous = hash_hmac('sha256', $userId . '|' . date('Y-m-d-H') . $prevSlot, $secret);
    return hash_equals($previous, $token);
}
function requireValidCsrf(): void {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || !validateCsrfToken($token)) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>403</title></head><body style="background:#080d17;color:#f0f3fa;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0"><div style="text-align:center"><h1 style="color:#f87171;font-size:3rem">403</h1><p>Token de seguridad inválido.</p></div></body></html>';
        exit;
    }
}

function resolvePath(string $configKey, string $defaultValue): string {
    global $config;
    $raw = (string) $config->get($configKey, $defaultValue);
    if (str_starts_with($raw, '/')) {
        // Absolute paths: allow only if within the project root (defense-in-depth)
        $realRoot = realpath(WASAPBOT_ROOT) ?: WASAPBOT_ROOT;
        if (str_starts_with($raw, $realRoot . '/')) return $raw;
        // Fall through to default
    } else {
        $resolved = WASAPBOT_ROOT . '/' . ltrim($raw, '/');
        // Resolve real path to prevent traversal attacks (e.g., ../../../etc/passwd)
        $real = realpath($resolved);
        if ($real !== false && str_starts_with($real, (realpath(WASAPBOT_ROOT) ?: WASAPBOT_ROOT) . '/')) {
            return $real;
        }
    }
    // If path traversal or invalid, fall back to default
    return WASAPBOT_ROOT . '/' . ltrim($defaultValue, '/');
}

// ── NDJSON reader ──
function readNdjson(string $filePath): array {
    if (!file_exists($filePath) || !is_readable($filePath)) return [];
    $lines = @file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return [];
    $records = [];
    foreach ($lines as $line) {
        $rec = json_decode($line, true);
        if (is_array($rec)) $records[] = $rec;
    }
    return $records;
}

// ── Bot mode ──
$modeFilePath = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $clientUserId, '.bot_mode');
function getBotMode(): string {
    global $modeFilePath;
    if (!file_exists($modeFilePath)) {
        // New user: create .bot_mode with 'stop'
        $dir = dirname($modeFilePath);
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        @file_put_contents($modeFilePath, 'stop', LOCK_EX);
        return 'stop';
    }
    $content = trim((string) @file_get_contents($modeFilePath));
    return in_array($content, ['start', 'stop'], true) ? $content : 'stop';
}
$botMode = getBotMode();
$botStatusClass = $botMode === 'start' ? 'status-on' : ($botMode === 'stop' ? 'status-off' : 'status-unknown');
$botStatusLabel = $botMode === 'start' ? 'ENCENDIDO' : ($botMode === 'stop' ? 'APAGADO' : 'DESCONOCIDO');

// ── Stats ──
$leadsPath  = resolvePath('files.leads', 'data/leads.ndjson');
$memoryPath = resolvePath('files.session_memory', 'data/session_memory.ndjson');
$todayStr = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))->format('Y-m-d');
$leadsTotal = 0; $leadsToday = 0; $leadsArrived = 0;
foreach (readNdjson($leadsPath) as $lead) {
    $leadsTotal++;
    if (str_starts_with((string) ($lead['ts'] ?? ''), $todayStr)) $leadsToday++;
    if (!empty($lead['arrived'])) $leadsArrived++;
}
$allThreads = []; $todayThreads = [];
foreach (readNdjson($memoryPath) as $rec) {
    $tid = (string) ($rec['thread_id'] ?? '');
    if ($tid === '') continue;
    $allThreads[$tid] = true;
    if (str_starts_with((string) ($rec['ts'] ?? ''), $todayStr)) $todayThreads[$tid] = true;
}

// ── Active lines count ──
$linesMapPath = WASAPBOT_ROOT . '/data/lines_map.json';
$linesForUser = 0;
if (file_exists($linesMapPath)) {
    $linesMap = @json_decode((string) @file_get_contents($linesMapPath), true);
    if (is_array($linesMap)) {
        foreach ($linesMap as $uid) {
            if ((int) $uid === $clientUserId) $linesForUser++;
        }
    }
}

// ─────────────────────────────────────────────────────────────────────
//   Actions
// ─────────────────────────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');
$baseUrl = 'cliente';
$notification = '';

// ── Save config ──
if ($method === 'POST' && $action === 'save_config') {
    requireValidCsrf();

    // ── Allowed config keys (explicit allowlist) ──
    // Only keys in this list can be set by the client panel form.
    $allowedKeys = [
        // Prompt sections (personality tab)
        'prompt.sections.estilo_tipo',
        'prompt.sections.speaker_mode',
        'prompt.sections.emoji_level',
        'prompt.sections.reply_length',
        'prompt.sections.tarifas',
        'prompt.sections.zona',
        'prompt.sections.ubicacion',
        'prompt.sections.servicios',
        'prompt.sections.ofertas',
        // URL field
        'urls.google_maps_location',
        // Textarea list keys (need special handling: string → array)
        'message_variants.audio_auto_reply',
        'message_variants.dedup_start',
        'message_variants.dedup_end',
        'cron.followup.intro_variants',
        'cron.followup.closing_variants',
        'cron.reminder.message_variants',
        // Tab tracking (not persisted to config, just used client-side)
    ];
    $allowedKeysMap = array_flip($allowedKeys);

    // Flatten POST arrays into dotted keys, filtering to allowed keys only
    $flat = [];
    $walk = function (array $data, string $prefix = '') use (&$walk, &$flat, $allowedKeysMap): void {
        foreach ($data as $key => $value) {
            $fullKey = $prefix === '' ? $key : $prefix . '.' . $key;
            if (is_array($value) && !isset($value[0])) {
                $walk($value, $fullKey);
            } elseif (isset($allowedKeysMap[$fullKey])) {
                $flat[$fullKey] = $value;
            }
            // Keys not in the allowlist are silently dropped
        }
    };
    $walk($_POST);

    // Convert textarea array keys (newline-separated → array)
    $textareaKeys = ['message_variants.audio_auto_reply', 'message_variants.dedup_start', 'message_variants.dedup_end',
        'cron.followup.intro_variants', 'cron.followup.closing_variants', 'cron.reminder.message_variants'];
    foreach ($textareaKeys as $tk) {
        if (isset($flat[$tk]) && is_string($flat[$tk])) {
            $lines = array_filter(array_map('trim', explode("\n", $flat[$tk])), fn($l) => $l !== '');
            $flat[$tk] = $lines;
        }
    }

    foreach ($flat as $key => $value) {
        $config->set($key, $value);
    }
    $config->save();
    $notification = '<div class="alert alert-success">✅ Configuración guardada correctamente.</div>';
}

// ── Toggle bot ──
if ($method === 'POST' && $action === 'toggle_bot') {
    requireValidCsrf();
    $newMode = ($botMode === 'start') ? 'stop' : 'start';
    $dir = dirname($modeFilePath);
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    if (@file_put_contents($modeFilePath, $newMode, LOCK_EX) !== false) {
        @chmod($modeFilePath, 0664);
    }
    $botMode = $newMode;
    $botStatusClass = $botMode === 'start' ? 'status-on' : 'status-off';
    $botStatusLabel = $botMode === 'start' ? 'ENCENDIDO' : 'APAGADO';
    $notification = '<div class="alert alert-success">✅ Bot ' . ($botMode === 'start' ? 'ENCENDIDO' : 'APAGADO') . '.</div>';
}

// ─────────────────────────────────────────────────────────────────────
//   Render
// ─────────────────────────────────────────────────────────────────────

$sectionKeys = ['rol', 'estilo', 'tarifas', 'servicios', 'ubicacion', 'instrucciones_fotos', 'identidad_chicas', 'seguridad', 'ejemplos', 'formato_respuesta'];

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>bot-casa — <?php echo $clientName; ?></title>
<link rel="stylesheet" href="assets/style.css?v=20260603_4">
<style>
/* ── Client panel overrides / additions ── */
.tooltip-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 18px; height: 18px; border-radius: 50%;
    background: var(--info); color: #fff; font-size: .7rem; font-weight: 700;
    cursor: help; margin-left: 4px; line-height: 1;
}
.tooltip-box {
    display: none; position: absolute; z-index: 100;
    background: var(--panel); border: 1px solid var(--accent);
    border-radius: var(--radius-sm); padding: 10px 12px;
    font-size: .78rem; color: var(--text); max-width: 280px;
    box-shadow: var(--shadow-md); line-height: 1.5;
}
.tooltip-wrap { position: relative; display: inline; }
.tooltip-wrap:hover .tooltip-box, .tooltip-wrap:focus-within .tooltip-box { display: block; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-top: 4px; }
.stat-card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    padding: 18px 20px;
    text-align: center;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-sm); }
.stat-card .stat-num { font-size: 2rem; font-weight: 800; }
.stat-card .stat-label { font-size: .78rem; color: var(--text-muted); margin-top: 4px; }
.stat-card .stat-sub { font-size: .7rem; color: rgba(255,255,255,.25); margin-top: 2px; }

.config-checklist { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px; }
.checklist-item { display: flex; align-items: center; gap: 6px; padding: 8px 14px; background: var(--panel); border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: .8rem; }
.checklist-item .check-icon { font-size: .9rem; }
.check-ok { color: var(--ok); border-color: rgba(45,212,191,.25); }
.check-warn { color: var(--warn); border-color: rgba(251,191,36,.25); }

.section-guide {
    background: var(--info-bg);
    border: 1px solid rgba(124,92,255,0.2);
    border-radius: var(--radius-sm);
    padding: 12px 16px;
    margin-bottom: 12px;
    font-size: .82rem;
    color: var(--info);
}

@media (max-width: 768px) {
    .prompt-layout { flex-direction: column !important; }
    .prompt-edit-col, .prompt-preview-col { flex: 1 1 100% !important; max-width: 100% !important; }
    .prompt-preview-card { position: static !important; max-height: 50vh !important; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>
</head>
<body>

<div class="header">
    <div>
        <h1>🤖 bot-casa</h1>
        <span class="subtitle"><?php echo $clientName; ?></span>
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        <?php if (($_SESSION['role'] ?? '') === 'admin' && ($_SESSION['user_id'] ?? 0) !== $clientUserId): ?>
        <span style="color:var(--accent);font-size:.8rem">👁 Suplantando</span>
        <?php endif; ?>
        <form method="post" action="cliente?action=toggle_bot" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
            <button type="submit" class="btn <?php echo $botMode === 'start' ? 'btn-danger' : 'btn-success'; ?> btn-sm">
                <?php echo $botMode === 'start' ? '⏹ APAGAR' : '▶ ENCENDER'; ?>
            </button>
        </form>
        <a href="logout" class="btn btn-sm" style="background:var(--input-bg);color:var(--text-muted);text-decoration:none">Salir</a>
    </div>
</div>

<?php echo $notification; ?>

<?php
// ── Progress indicator ──
$progressTotal = 4;
$progressDone = 0;
if ($linesForUser > 0) $progressDone++;
if (strlen((string)$config->get('prompt.sections.tarifas','')) > 20) $progressDone++;
if (file_exists(WASAPBOT_ROOT . '/data/users/' . $clientUserId . '/girls.json')) {
    $gd = @json_decode((string)@file_get_contents(WASAPBOT_ROOT . '/data/users/' . $clientUserId . '/girls.json'), true);
    if (is_array($gd) && count(array_filter($gd['girls']??[], fn($g)=>!empty($g['activa']))) > 0) $progressDone++;
}
$configPath = WASAPBOT_ROOT . '/data/users/' . $clientUserId . '/config.local.json';
if (file_exists($configPath)) $progressDone++;
$progressPct = $progressDone > 0 ? round($progressDone / $progressTotal * 100) : 0;
if ($progressPct < 100):
?>
<div style="padding:0 20px;margin-bottom:8px">
    <div style="display:flex;align-items:center;gap:8px;font-size:.78rem;color:var(--text-muted)">
        <span>⚙️ Configuración: <?php echo $progressDone; ?>/<?php echo $progressTotal; ?></span>
        <div style="flex:1;background:var(--input-bg);border-radius:4px;height:6px;overflow:hidden">
            <div style="background:linear-gradient(90deg,var(--accent),var(--ok));height:100%;width:<?php echo $progressPct; ?>%;border-radius:4px;transition:width .3s"></div>
        </div>
        <span><?php echo $progressPct; ?>%</span>
    </div>
</div>
<?php endif; ?>

<?php
// ── Onboarding wizard (shown if progress < 25%) ──
$showWizard = $progressPct < 25 && !isset($_COOKIE['botcasa_wizard_done']);
if ($showWizard):
?>
<div id="wizard-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px">
    <div style="background:var(--panel);border:1px solid var(--accent);border-radius:var(--radius-lg);padding:32px;max-width:500px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.5);text-align:center">
        <div style="font-size:3rem;margin-bottom:8px">🚀</div>
        <h2 style="color:var(--accent);margin-bottom:8px">¡Bienvenido a bot-casa!</h2>
        <p style="color:var(--text-muted);margin-bottom:16px;font-size:.9rem">
            Configura tu bot en 3 pasos sencillos para empezar a recibir clientes por WhatsApp automáticamente.
        </p>
        <div style="text-align:left;margin-bottom:20px">
            <div style="display:flex;align-items:center;gap:12px;padding:10px;background:var(--bg-surface);border-radius:var(--radius-sm);margin-bottom:8px">
                <span style="font-size:1.5rem">1️⃣</span>
                <div><strong>Personalidad</strong><br><span style="font-size:.78rem;color:var(--text-muted)">Define tarifas, ubicación y estilo → pestaña 🎭 Personalidad</span></div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;padding:10px;background:var(--bg-surface);border-radius:var(--radius-sm);margin-bottom:8px">
                <span style="font-size:1.5rem">2️⃣</span>
                <div><strong>Líneas WhatsApp</strong><br><span style="font-size:.78rem;color:var(--text-muted)">Vincula tus números → pestaña 📱 Líneas</span></div>
            </div>
            <div style="display:flex;align-items:center;gap:12px;padding:10px;background:var(--bg-surface);border-radius:var(--radius-sm)">
                <span style="font-size:1.5rem">3️⃣</span>
                <div><strong>Chicas</strong><br><span style="font-size:.78rem;color:var(--text-muted)">Añade tu catálogo → pestaña 👩 Chicas</span></div>
            </div>
        </div>
        <button type="button" class="btn btn-primary btn-lg" onclick="dismissWizard()" style="width:100%">¡Empezar!</button>
        <p style="font-size:.7rem;color:var(--text-muted);margin-top:10px">Este asistente solo aparece una vez. Puedes volver a verlo desde Ajustes.</p>
    </div>
</div>
<script>
function dismissWizard() {
    document.getElementById('wizard-overlay').style.display = 'none';
    document.cookie = 'botcasa_wizard_done=1;max-age=86400;path=/;samesite=lax';
}
</script>
<?php endif; ?>

<div class="tab-nav" id="tabNav">
    <button type="button" class="active" data-tab="tab-dashboard">📊 Inicio</button>
    <button type="button" data-tab="tab-mibot">🤖 Mi Bot</button>
    <button type="button" data-tab="tab-personalidad">🎭 Personalidad</button>
    <button type="button" data-tab="tab-lineas">📱 Líneas</button>
    <button type="button" data-tab="tab-chicas">👩 Chicas</button>
    <button type="button" data-tab="tab-estados">📢 Estados</button>
    <button type="button" data-tab="tab-clientes">👥 Clientes</button>
    <button type="button" data-tab="tab-mensajes">💬 Mensajes</button>
    <button type="button" data-tab="tab-ajustes">⚙️ Ajustes</button>
    <button type="button" data-tab="tab-estadisticas">📈 Estadísticas</button>
    <button type="button" data-tab="tab-registro">📋 Registro</button>
</div>

<form method="post" action="cliente?action=save_config" class="main-form">
<input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
<input type="hidden" name="active_tab" class="js-active-tab-input" value="tab-dashboard">

<!-- ===== TAB: Dashboard ===== -->
<div class="tab-content active" id="tab-dashboard">
    <div class="card">
        <h2>Estado de tu Bot</h2>
        <div class="bot-status">
            <span class="bot-indicator <?php echo $botStatusClass; ?>"></span>
            <span class="bot-status-text"><?php echo h($botStatusLabel); ?></span>
        </div>
    </div>

    <div class="card">
        <h2>Estadísticas</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-num" style="color:var(--info)"><?php echo count($allThreads); ?></div>
                <div class="stat-label">Conversaciones totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" style="color:var(--accent)"><?php echo count($todayThreads); ?></div>
                <div class="stat-label">Conversaciones hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" style="color:var(--ok)"><?php echo $leadsTotal; ?></div>
                <div class="stat-label">Leads totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" style="color:var(--money)"><?php echo $leadsToday; ?></div>
                <div class="stat-label">Leads hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" style="color:var(--accent2)"><?php echo $linesForUser; ?></div>
                <div class="stat-label">Líneas WhatsApp</div>
                <div class="stat-sub">Vinculadas al bot</div>
            </div>
            <?php
            $girlsActiveCount = 0;
            $gf = WASAPBOT_ROOT . '/data/users/' . $clientUserId . '/girls.json';
            if (file_exists($gf)) {
                $gd = @json_decode((string)@file_get_contents($gf), true);
                if (is_array($gd)) $girlsActiveCount = count(array_filter($gd['girls']??[], fn($g)=>!empty($g['activa'])));
            }
            ?>
            <div class="stat-card">
                <div class="stat-num" style="color:#a78bfa"><?php echo $girlsActiveCount; ?></div>
                <div class="stat-label">Chicas activas</div>
                <div class="stat-sub">En catálogo</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" style="color:<?php echo $leadsTotal>0&&$leadsArrived>0?'var(--ok)':'var(--text-muted)'; ?>"><?php echo $leadsArrived??0; ?></div>
                <div class="stat-label">Clientes recibidos</div>
                <div class="stat-sub">Marcados como llegados</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" style="color:#f97316"><?php echo count($allThreads)>0 ? round(count($allThreads)/max($leadsTotal,1),1) : 0; ?></div>
                <div class="stat-label">Ratio conv/lead</div>
                <div class="stat-sub">Conversaciones por lead</div>
            </div>
        </div>
    </div>
</div>

<!-- ===== TAB: Mi Bot ===== -->
<div class="tab-content" id="tab-mibot">
    <div class="card">
        <h2>🤖 ¿Cómo funciona tu bot?</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:16px">
            Tu bot atiende automáticamente a los clientes que te escriben por WhatsApp. Les responde de forma natural,
            negocia tarifas, envía fotos y ubicación, y te avisa cuando alguien va a venir.
        </p>

        <div style="margin-top:16px">
            <h3>✅ Configuración necesaria</h3>
            <?php
            $promptConfigured = strlen((string) $config->get('prompt.sections.tarifas', '')) > 20;
            $linesConfigured = $linesForUser > 0;
            $checkItems = [
                ['✅ Vincular WhatsApp', $linesConfigured, 'Configura tus líneas en la pestaña 📱 Líneas.'],
                ['✅ Configurar tarifas', $promptConfigured, 'Define tus precios en la pestaña 🎭 Personalidad.'],
                ['⏳ Chicas configuradas', false, 'Añade tu catálogo en la pestaña 👩 Chicas.'],
            ];
            ?>
            <div class="config-checklist">
                <?php foreach ($checkItems as [$label, $done, $tip]): ?>
                <div class="checklist-item <?php echo $done ? 'check-ok' : 'check-warn'; ?>">
                    <span class="check-icon"><?php echo $done ? '✅' : '⚠️'; ?></span>
                    <span><?php echo $label; ?>
                        <span class="tooltip-wrap">
                            <span class="tooltip-icon">?</span>
                            <span class="tooltip-box"><?php echo h($tip); ?></span>
                        </span>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>🚀 Empezar</h2>
        <p style="color:var(--text-muted);font-size:.85rem">
            1. Configura tu <strong>Personalidad</strong> (tarifas, estilo, ubicación)<br>
            2. Vincula tus <strong>líneas de WhatsApp</strong> en la pestaña 📱 Líneas<br>
            3. Añade tu <strong>catálogo de chicas</strong> en la pestaña 👩 Chicas<br>
            4. ¡Enciende el bot y empieza a recibir clientes!
        </p>
    </div>
</div>

<!-- ===== TAB: Personalidad ===== -->
<div class="tab-content" id="tab-personalidad">
    <div class="section-guide">
        💡 <strong>Consejo:</strong> Cuanto más detallada sea la configuración, mejor responderá tu bot. Tómate tu tiempo para rellenar cada sección. Usa el botón 🔄 para restaurar valores de fábrica si te lías.
    </div>

    <div class="prompt-layout">
        <div class="prompt-edit-col">
            <!-- Estilo / tono parametrizado -->
            <div class="card">
                <h2>🎨 Estilo del bot
                    <span class="tooltip-wrap">
                        <span class="tooltip-icon">?</span>
                        <span class="tooltip-box">Define cómo habla tu bot. Esto afecta a TODAS las conversaciones.</span>
                    </span>
                </h2>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tono de voz principal</label>
                        <select name="prompt[sections][estilo_tipo]" onchange="buildPreview()">
                            <?php
                            $tonos = [
                                'latina_barrio'  => '💃 Latina de barrio (directa, pícara, coloquial)',
                                'carinosa_dulce' => '🌸 Cariñosa y dulce',
                                'formal_educada' => '👔 Formal y educada',
                                'directa_pro'    => '⚡ Directa y profesional',
                            ];
                            $currentTono = (string) $config->get('prompt.sections.estilo_tipo', 'latina_barrio');
                            foreach ($tonos as $val => $label) {
                                echo '<option value="' . $val . '"' . selected($val === $currentTono) . '>' . $label . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>¿El bot habla en primera persona?
                            <span class="tooltip-wrap">
                                <span class="tooltip-icon">?</span>
                                <span class="tooltip-box">Si es la chica quien habla, usa "soy Ana, tengo X...". Si es una recepcionista, habla de las chicas en tercera persona.</span>
                            </span>
                        </label>
                        <select name="prompt[sections][speaker_mode]">
                            <?php
                            $speakerModes = [
                                'chica'        => 'Como la chica (1ª persona)',
                                'recepcionista'=> 'Como recepcionista (3ª persona)',
                            ];
                            $currentSpeaker = (string) $config->get('prompt.sections.speaker_mode', 'chica');
                            foreach ($speakerModes as $val => $label) {
                                echo '<option value="' . $val . '"' . selected($val === $currentSpeaker) . '>' . $label . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Uso de emojis</label>
                        <select name="prompt[sections][emoji_level]">
                            <?php
                            $emojiLevels = ['moderado' => '😊 Moderado (1 por mensaje)', 'poco' => '🙂 Poco (ocasional)', 'nada' => '🚫 Sin emojis'];
                            $currentEmoji = (string) $config->get('prompt.sections.emoji_level', 'moderado');
                            foreach ($emojiLevels as $val => $label) {
                                echo '<option value="' . $val . '"' . selected($val === $currentEmoji) . '>' . $label . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Longitud de respuestas</label>
                        <select name="prompt[sections][reply_length]">
                            <?php
                            $lengths = ['ultra_corta' => 'Ultra corta (1 frase)', 'corta' => 'Corta (1-2 frases)', 'normal' => 'Normal (2-3 frases)'];
                            $currentLen = (string) $config->get('prompt.sections.reply_length', 'corta');
                            foreach ($lengths as $val => $label) {
                                echo '<option value="' . $val . '"' . selected($val === $currentLen) . '>' . $label . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Tarifas -->
            <div class="card">
                <h2>💰 Tarifas y precios
                    <span class="tooltip-wrap"><span class="tooltip-icon">?</span>
                        <span class="tooltip-box">Define tus precios. El bot usará EXACTAMENTE estos valores.</span>
                    </span>
                    <button type="button" class="btn btn-sm" style="float:right;background:var(--input-bg);color:var(--text-muted);font-size:.7rem"
                        onclick="resetField('prompt\\[sections\\]\\[tarifas\\]','30€ = rapidito 10 min\n50€ = media hora completo\n100€ = 1 hora completo')">🔄 Restaurar</button>
                </h2>
                <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:12px">
                    Escribe tus tarifas en formato libre. Ejemplo:<br>
                    <code style="background:var(--input-bg);padding:2px 6px;border-radius:3px">30€ = rapidito 10 min / 50€ = media hora / 100€ = 1 hora completo</code>
                </p>
                <textarea name="prompt[sections][tarifas]" class="code-area" style="width:100%;min-height:100px" spellcheck="false"><?php echo cv('prompt.sections.tarifas'); ?></textarea>
            </div>

            <!-- Ubicación -->
            <div class="card">
                <h2>📍 Ubicación
                    <span class="tooltip-wrap"><span class="tooltip-icon">?</span>
                        <span class="tooltip-box">Zona general. El bot dirá esto cuando le pregunten. No pongas dirección exacta.</span>
                    </span>
                    <button type="button" class="btn btn-sm" style="float:right;background:var(--input-bg);color:var(--text-muted);font-size:.7rem"
                        onclick="resetField('prompt\\[sections\\]\\[zona\\]','');resetField('prompt\\[sections\\]\\[ubicacion\\]','');resetField('urls\\[google_maps_location\\]','')">🔄 Restaurar</button>
                </h2>
                <div class="form-row">
                    <div class="form-group" style="flex:2">
                        <label>Zona / ciudad</label>
                        <input type="text" name="prompt[sections][zona]" value="<?php echo cv('prompt.sections.zona'); ?>" placeholder="Ej: Zona centro, piso discreto">
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>Enlace Google Maps</label>
                        <input type="url" name="urls[google_maps_location]" value="<?php echo cv('urls.google_maps_location'); ?>" placeholder="https://maps.app.goo.gl/...">
                    </div>
                </div>
                <textarea name="prompt[sections][ubicacion]" class="code-area" style="width:100%;min-height:60px;margin-top:8px" spellcheck="false"><?php echo cv('prompt.sections.ubicacion'); ?></textarea>
            </div>

            <!-- Servicios -->
            <div class="card">
                <h2>🛏️ Servicios
                    <button type="button" class="btn btn-sm" style="float:right;background:var(--input-bg);color:var(--text-muted);font-size:.7rem"
                        onclick="resetField('prompt\\[sections\\]\\[servicios\\]','Servicio completo con preservativo.\nFrancés natural solo en tarifa de 1h si el cliente lo pide.\nGriego solo si el cliente pregunta expresamente.\nNo salidas a domicilio.')">🔄 Restaurar</button>
                </h2>
                <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:8px">
                    Describe los servicios disponibles. El bot usará esta información cuando le pregunten.
                </p>
                <textarea name="prompt[sections][servicios]" class="code-area" style="width:100%;min-height:80px" spellcheck="false"><?php echo cv('prompt.sections.servicios'); ?></textarea>
            </div>

            <!-- Ofertas -->
            <div class="card">
                <h2>🎁 Ofertas especiales (opcional)
                    <button type="button" class="btn btn-sm" style="float:right;background:var(--input-bg);color:var(--text-muted);font-size:.7rem"
                        onclick="resetField('prompt\\[sections\\]\\[ofertas\\]','')">🔄 Restaurar</button>
                </h2>
                <textarea name="prompt[sections][ofertas]" class="code-area" style="width:100%;min-height:60px" spellcheck="false"><?php echo cv('prompt.sections.ofertas'); ?></textarea>
            </div>

            <div style="margin-top:16px">
                <button type="submit" class="btn btn-primary btn-lg">💾 Guardar Personalidad</button>
            </div>
        </div>

        <!-- Preview column (friendly summary) -->
        <div class="prompt-preview-col">
            <div class="card prompt-preview-card">
                <h2>🧠 Configuración actual</h2>
                <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:8px">
                    Resumen de cómo está configurado tu bot ahora mismo.
                </p>
                <div id="prompt-summary" style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;font-size:.82rem;line-height:1.6;color:var(--text);max-height:60vh;overflow-y:auto">
                    <p style="color:var(--text-muted)">Completa los campos de la izquierda para ver el resumen.</p>
                </div>
                <div id="prompt-stats" class="prompt-stats"></div>
            </div>
        </div>
    </div>
</div>

<!-- ===== TAB: Líneas WhatsApp ===== -->
<div class="tab-content" id="tab-lineas">
    <div class="card">
        <h2>📱 Líneas de WhatsApp
            <span class="tooltip-wrap"><span class="tooltip-icon">?</span>
                <span class="tooltip-box">Vincula tus números de WhatsApp para que el bot atienda por ellos. Cada línea es un número distinto.</span>
            </span>
        </h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:16px">
            Añade los números de WhatsApp que quieres vincular al bot. Tras añadir una línea, escanea el código QR con WhatsApp para vincularla.
        </p>

        <!-- Add form -->
        <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:16px;margin-bottom:20px">
            <h3 style="margin-bottom:10px">➕ Añadir línea</h3>
            <p style="color:var(--text-muted);font-size:.75rem;margin-bottom:10px">Esto creará una nueva instancia WAHA en el servidor (puerto 3020+). Las líneas existentes (3000-3011) no se tocan.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                <div style="flex:2;min-width:160px">
                    <label style="font-size:.78rem;color:var(--text-muted)">Número de teléfono</label>
                    <input type="text" id="new-line-phone" placeholder="Ej: 612345678" style="width:100%">
                </div>
                <div style="flex:1;min-width:100px">
                    <label style="font-size:.78rem;color:var(--text-muted)">Etiqueta</label>
                    <input type="text" id="new-line-label" placeholder="Línea principal" style="width:100%">
                </div>
                <div>
                    <button type="button" class="btn btn-primary" onclick="addLine()" style="white-space:nowrap">Crear línea</button>
                </div>
            </div>
            <div id="add-line-status" style="margin-top:6px;font-size:.78rem;color:var(--text-muted)"></div>
        </div>

        <!-- Lines table -->
        <div id="lines-container">
            <p style="color:var(--text-muted);text-align:center;padding:20px">Cargando líneas...</p>
        </div>

        <!-- QR modal (hidden) -->
        <div id="qr-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:1000;align-items:center;justify-content:center">
            <div style="background:var(--panel);border:1px solid var(--border);border-radius:var(--radius-md);padding:24px;max-width:400px;text-align:center">
                <h3>📱 Escanea el QR</h3>
                <p style="color:var(--text-muted);font-size:.82rem;margin:8px 0">Abre WhatsApp → Ajustes → Vincular dispositivo</p>
                <p style="color:var(--danger);font-size:.75rem;margin:4px 0">⚠️ El QR caduca en 30-60 segundos. Ten el móvil listo.</p>
                <img id="qr-image" src="" style="max-width:280px;border-radius:8px;margin:12px auto" alt="QR Code">
                <div id="qr-status" style="margin-top:8px;font-size:.85rem"></div>
                <button type="button" class="btn btn-sm btn-primary" style="margin-top:4px" onclick="regenerateQR()">🔄 Regenerar QR</button>
                <button type="button" class="btn btn-sm" style="margin-top:4px;background:var(--input-bg);color:var(--text-muted)" onclick="document.getElementById('qr-modal').style.display='none'">Cerrar</button>
            </div>
        </div>

        <!-- Test modal -->
        <div id="test-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.8);z-index:1000;align-items:center;justify-content:center">
            <div style="background:var(--panel);border:1px solid var(--border);border-radius:var(--radius-md);padding:24px;max-width:400px;text-align:center">
                <h3>📤 Enviar mensaje de prueba</h3>
                <input type="text" id="test-phone" placeholder="Número de teléfono (con prefijo)" style="width:100%;margin:12px 0">
                <input type="hidden" id="test-line-id">
                <button type="button" class="btn btn-primary" onclick="sendTestMessage()">Enviar prueba</button>
                <button type="button" class="btn btn-sm" style="margin-top:8px;background:var(--input-bg);color:var(--text-muted)" onclick="document.getElementById('test-modal').style.display='none'">Cancelar</button>
                <div id="test-result" style="margin-top:8px;font-size:.82rem"></div>
            </div>
        </div>
    </div>
</div>

<!-- ===== TAB: Chicas ===== -->
<div class="tab-content" id="tab-chicas">
    <div class="card">
        <h2>👩 Catálogo de Chicas
            <span class="tooltip-wrap"><span class="tooltip-icon">?</span>
                <span class="tooltip-box">Gestiona las chicas que ofrece tu bot. Las fotos se mostrarán en las conversaciones cuando los clientes pregunten.</span>
            </span>
        </h2>

        <!-- Add/edit form -->
        <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:16px;margin-bottom:20px">
            <h3 id="girl-form-title" style="margin-bottom:10px">➕ Nueva chica</h3>
            <input type="hidden" id="girl-edit-id">
            <div class="form-row">
                <div class="form-group" style="flex:1;min-width:140px">
                    <label>Nombre *</label>
                    <input type="text" id="girl-nombre" placeholder="Ej: Sandra" style="width:100%">
                </div>
                <div class="form-group" style="flex:2;min-width:250px">
                    <label>Descripción</label>
                    <textarea id="girl-desc" rows="3" placeholder="Ej: Morena, 25 años, cariñosa, simpática..." style="width:100%;min-height:60px"></textarea>
                </div>
                <div style="display:flex;align-items:flex-end;gap:6px">
                    <button type="button" class="btn btn-primary" onclick="saveGirl()">💾 Guardar</button>
                </div>
            </div>
            <!-- Photo upload -->
            <div style="margin-top:10px;display:flex;gap:8px;align-items:flex-end">
                <div style="flex:1">
                    <label style="font-size:.78rem;color:var(--text-muted)">Añadir foto (JPG, PNG, WebP — máx 5MB)</label>
                    <input type="file" id="girl-photo-file" accept="image/jpeg,image/png,image/webp" style="width:100%">
                </div>
                <div>
                    <button type="button" class="btn btn-sm btn-success" onclick="uploadGirlPhoto()" id="btn-upload-photo">📤 Subir</button>
                </div>
                <span id="upload-status" style="font-size:.75rem;color:var(--text-muted)"></span>
            </div>
        </div>

        <!-- Girls list -->
        <div id="girls-container">
            <p style="color:var(--text-muted);text-align:center;padding:20px">Cargando chicas...</p>
        </div>
    </div>
</div>

<!-- ===== TAB: Estados WhatsApp ===== -->
<div class="tab-content" id="tab-estados">
    <div class="card">
        <h2>📢 Publicador de Estados
            <span class="tooltip-wrap"><span class="tooltip-icon">?</span>
                <span class="tooltip-box">El bot publica estados de WhatsApp automáticamente. Los genera basándose en las chicas activas de tu catálogo, combinando nombres, fotos y textos atractivos.</span>
            </span>
        </h2>
        <div class="section-guide">
            📢 <strong>¿Cómo funciona?</strong> El bot crea y publica estados de WhatsApp automáticamente según la frecuencia que configures. 
            Los estados se generan con las chicas que tengas activas en la pestaña 👩 Chicas. Si no hay chicas activas, no se publicará nada.
            Puedes elegir entre varios formatos (catálogo completo, chica del día, dúo, etc.) y el bot los irá alternando.
        </div>

        <!-- Config form -->
        <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:16px;margin-bottom:20px">
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                <div>
                    <label style="font-size:.78rem;color:var(--text-muted)">Activado</label>
                    <label class="checkbox-label"><input type="checkbox" id="estados-enabled" onchange="saveEstadosConfig()"> Publicar estados</label>
                </div>
                <div style="flex:1;min-width:120px">
                    <label style="font-size:.78rem;color:var(--text-muted)">Frecuencia</label>
                    <select id="estados-freq-tipo" onchange="saveEstadosConfig()">
                        <option value="cada_x_horas">Cada X horas</option>
                        <option value="x_veces_al_dia">X veces al día</option>
                    </select>
                </div>
                <div style="width:80px">
                    <label style="font-size:.78rem;color:var(--text-muted)">Valor</label>
                    <input type="number" id="estados-freq-valor" value="6" min="1" max="24" onchange="saveEstadosConfig()" style="width:100%">
                </div>
                <div style="flex:1;min-width:160px">
                    <label style="font-size:.78rem;color:var(--text-muted)">Formato</label>
                    <select id="estados-formato" onchange="saveEstadosConfig()">
                        <option value="chicas_de_hoy">Todas las chicas, 1 foto</option>
                        <option value="chica_del_dia">1 chica aleatoria, 2 fotos</option>
                        <option value="duo_sexy">2 chicas, 1 foto c/u</option>
                        <option value="catalogo_rapido">Solo nombres</option>
                        <option value="mix_aleatorio">Aleatorio cada ciclo</option>
                    </select>
                </div>
                <div>
                    <button type="button" class="btn btn-success" onclick="publishEstado()">📢 Publicar ahora</button>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:10px;align-items:flex-end">
                <div style="width:100px">
                    <label style="font-size:.78rem;color:var(--text-muted)">Desde</label>
                    <input type="time" id="estados-hora-inicio" value="08:00" onchange="saveEstadosConfig()">
                </div>
                <div style="width:100px">
                    <label style="font-size:.78rem;color:var(--text-muted)">Hasta</label>
                    <input type="time" id="estados-hora-fin" value="23:00" onchange="saveEstadosConfig()">
                </div>
                <div id="estados-lines-checkboxes" style="flex:1;display:flex;flex-wrap:wrap;gap:10px"></div>
            </div>
            <div id="estados-status" style="margin-top:8px;font-size:.82rem;color:var(--text-muted)"></div>
        </div>

        <!-- History -->
        <h3>📋 Historial de publicaciones</h3>
        <div id="estados-history" style="margin-top:8px">
            <p style="color:var(--text-muted);text-align:center;padding:10px">Cargando...</p>
        </div>
    </div>
</div>

<!-- ===== TAB: Clientes ===== -->
<div class="tab-content" id="tab-clientes">
    <div class="card">
        <h2>👥 Clientes (Leads)
            <span class="tooltip-wrap"><span class="tooltip-icon">?</span>
                <span class="tooltip-box">Lista de clientes que han mostrado interés en venir. Marca los que llegaron de verdad para medir la efectividad del bot.</span>
            </span>
        </h2>

        <div class="section-guide">
            📱 <strong>¿Quieres recibir avisos cuando llegue un cliente?</strong> Puedes recibir notificaciones por <strong>Telegram</strong> (recomendado) o por <strong>WhatsApp</strong>.<br><br>
            <strong>Telegram (recomendado):</strong><br>
            <span style="font-size:.78rem">1. Abre Telegram, busca @BotFather, crea un bot con /newbot y copia el token<br>
            2. Busca @userinfobot para obtener tu Chat ID personal<br>
            3. Pega tu Chat ID abajo (uno por línea si tienes varios)<br>
            4. Activa las alertas con el checkbox</span><br><br>
            <strong>WhatsApp:</strong><br>
            <span style="font-size:.78rem">Puedes poner tu número personal para recibir avisos por WhatsApp. <strong>IMPORTANTE:</strong> El número que pongas aquí NO puede ser uno de los que tengas configurados como línea del bot en 📱 Líneas. Usa tu número personal.</span>
        </div>

        <!-- Telegram config -->
        <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;margin-bottom:16px">
            <div class="form-row">
                <div class="form-group" style="flex:2">
                    <label>Chat IDs de Telegram (uno por línea)</label>
                    <textarea name="telegram[chat_ids]" rows="3" class="code-area" spellcheck="false"><?php echo cv('telegram.chat_ids'); ?></textarea>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end">
                    <label class="checkbox-label"><input type="hidden" name="telegram[alert_enabled]" value="0"><input type="checkbox" name="telegram[alert_enabled]" value="1" <?php echo checked((bool)$config->get('telegram.alert_enabled',false)); ?>> Alertas activadas</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">💾 Guardar Telegram</button>
        </div>

        <!-- Leads table -->
        <div id="clientes-table-container">
            <p style="color:var(--text-muted);text-align:center;padding:20px">Cargando leads...</p>
        </div>
    </div>
</div>

<!-- ===== TAB: Mensajes ===== -->
<div class="tab-content" id="tab-mensajes">
    <div class="card">
        <h2>💬 Historial de Conversaciones
            <span class="tooltip-wrap"><span class="tooltip-icon">?</span>
                <span class="tooltip-box">Busca conversaciones por número de teléfono. Puedes ver el historial completo y marcar conversaciones como lead.</span>
            </span>
        </h2>

        <div style="display:flex;gap:10px;margin-bottom:16px">
            <input type="text" id="msg-search" placeholder="Buscar por teléfono..." style="flex:1" oninput="searchThreads()">
        </div>

        <div id="mensajes-threads-container">
            <p style="color:var(--text-muted);text-align:center;padding:20px">Cargando conversaciones...</p>
        </div>

        <!-- Chat modal -->
        <div id="chat-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:1000" onclick="if(event.target===this)closeChat()">
            <div style="background:var(--panel);border:1px solid var(--border);border-radius:var(--radius-md);padding:20px;max-width:600px;width:95%;margin:40px auto;max-height:85vh;overflow-y:auto">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                    <h3>💬 Conversación</h3>
                    <button type="button" class="btn btn-sm btn-primary" id="chat-mark-lead-btn" onclick="markAsLeadFromChat()">⭐ Marcar como lead</button>
                </div>
                <div id="chat-messages" style="max-height:60vh;overflow-y:auto"></div>
                <button type="button" class="btn btn-sm" style="margin-top:12px;background:var(--input-bg);color:var(--text-muted)" onclick="closeChat()">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== TAB: Ajustes ===== -->
<div class="tab-content" id="tab-ajustes">
    <div class="card">
        <h2>⚙️ Ajustes del Bot</h2>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:16px">
            Configuración avanzada. Si no estás seguro, deja los valores por defecto.
        </p>

        <details style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:0;margin-bottom:8px" open>
            <summary style="padding:12px 16px;cursor:pointer;font-weight:600;font-size:.9rem">⏱ Humanización (Delays)</summary>
            <div style="padding:12px 16px;border-top:1px solid var(--border)">
                <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:10px">
                    Estos tiempos hacen que el bot parezca humano al responder. Simula que "lee" el mensaje, "piensa" y "escribe". 
                    Valores muy bajos hacen que el bot parezca artificial (responde al instante). Valores muy altos hacen esperar demasiado al cliente.
                </p>
                <div class="form-row">
                    <div class="form-group">
                        <label>Seen delay (seg) <span style="color:var(--text-muted)">— Tiempo que tarda en "ver" el mensaje. Recomendado: 1-3</span></label>
                        <input type="number" step="0.1" name="human_delays[seen][fallback_sec]" value="<?php echo cv('human_delays.seen.fallback_sec','1'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Typing fallback (seg) <span style="color:var(--text-muted)">— Tiempo base de "escribir". Recomendado: 2-4</span></label>
                        <input type="number" step="0.1" name="human_delays[typing][fallback_sec]" value="<?php echo cv('human_delays.typing.fallback_sec','4'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Read delay min (ms) <span style="color:var(--text-muted)">— Mínimo para "leer". Recomendado: 900</span></label>
                        <input type="number" name="human_delays[read][base_min_ms]" value="<?php echo cv('human_delays.read.base_min_ms','900'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Read delay max (ms) <span style="color:var(--text-muted)">— Máximo para "leer". Recomendado: 2200</span></label>
                        <input type="number" name="human_delays[read][base_max_ms]" value="<?php echo cv('human_delays.read.base_max_ms','2200'); ?>">
                    </div>
                </div>
            </div>
        </details>

        <details style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:0;margin-bottom:8px">
            <summary style="padding:12px 16px;cursor:pointer;font-weight:600;font-size:.9rem">🎲 Variantes de mensajes</summary>
            <div style="padding:12px 16px;border-top:1px solid var(--border)">
                <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:6px">
                    Frases que el bot elige al azar cuando recibe un audio (una por línea). Cuantas más pongas, más variado parecerá.
                    <button type="button" class="btn btn-sm" style="margin-left:8px;background:var(--input-bg);color:var(--text-muted);font-size:.65rem"
                        onclick="this.parentElement.nextElementSibling.value='no puedo escuchar audios amor, me lo escribes mejor?\namor por aqui no escucho audios, escribeme y te digo 😘\ncari no puedo oir audios ahora, me lo pones en texto?\nme va mejor si me lo escribes amor, los audios no puedo escucharlos'">🔄 Restaurar</button>
                </p>
                <textarea name="message_variants[audio_auto_reply]" rows="4" class="code-area" spellcheck="false"><?php echo cv('message_variants.audio_auto_reply'); ?></textarea>
                <p style="color:var(--text-muted);font-size:.78rem;margin:10px 0 6px">
                    Variantes para pedir la hora de llegada (ETA) al cliente. El bot las rota.
                    <button type="button" class="btn btn-sm" style="margin-left:8px;background:var(--input-bg);color:var(--text-muted);font-size:.65rem"
                        onclick="this.parentElement.nextElementSibling.value='cuanto tardas amor?\navisame cuando salgas\nen cuantos min vienes?\ncuando llegas papi?\nme dices cuanto tardas?\nsal y avisame que te espero'">🔄 Restaurar</button>
                </p>
                <textarea name="message_variants[eta_request_variants]" rows="3" class="code-area" spellcheck="false"><?php echo cv('message_variants.eta_request_variants'); ?></textarea>
            </div>
        </details>

        <details style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:0;margin-bottom:8px">
            <summary style="padding:12px 16px;cursor:pointer;font-weight:600;font-size:.9rem">📨 Follow-up (recontactar leads)</summary>
            <div style="padding:12px 16px;border-top:1px solid var(--border)">
                <p style="color:var(--text-muted);font-size:.8rem;margin-bottom:8px">
                    <strong>¿Qué hace?</strong> El bot revisa periódicamente los leads antiguos y les envía un mensaje con fotos de las chicas disponibles para intentar que vuelvan. 
                    Es como un "te echamos de menos" automático.<br><br>
                    <strong>¿Cuándo se envía?</strong> Solo a clientes con los que se habló hace 48-72h y que NO hayan sido marcados como "llegó" en la pestaña 👥 Clientes.
                </p>
                <div class="alert-warning" style="margin-bottom:10px;font-size:.8rem;padding:8px 12px;border-radius:8px">
                    ⚠️ <strong>Importante:</strong> Marca los leads como "llegó" en la pestaña Clientes. Si no los marcas, el bot les reenviará mensajes y puede quedar raro.
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="checkbox-label"><input type="hidden" name="cron[followup][enabled]" value="0"><input type="checkbox" name="cron[followup][enabled]" value="1" <?php echo checked((bool)$config->get('cron.followup.enabled',false)); ?>> Activado</label></div>
                    <div class="form-group"><label>Máx leads por ejecución</label><input type="number" name="cron[followup][max_leads_per_run]" value="<?php echo cv('cron.followup.max_leads_per_run','10'); ?>"></div>
                    <div class="form-group"><label>Horario inicio</label><input type="text" name="cron[followup][send_window_start]" value="<?php echo cv('cron.followup.send_window_start','10:00'); ?>"></div>
                    <div class="form-group"><label>Horario fin</label><input type="text" name="cron[followup][send_window_end]" value="<?php echo cv('cron.followup.send_window_end','22:00'); ?>"></div>
                </div>
            </div>
        </details>

        <details style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:0;margin-bottom:8px">
            <summary style="padding:12px 16px;cursor:pointer;font-weight:600;font-size:.9rem">⏰ Recordatorios ETA</summary>
            <div style="padding:12px 16px;border-top:1px solid var(--border)">
                <p style="color:var(--text-muted);font-size:.8rem;margin-bottom:8px">
                    <strong>¿Qué hace?</strong> Si un cliente dice "llego en 20 minutos", el bot programa un recordatorio y se lo envía pasado ese tiempo. 
                    Así el cliente no se olvida de venir.<br><br>
                    <strong>Importante:</strong> Este recordatorio se envía aunque no hayas marcado el lead como "llegó". Es automático y se basa en lo que el cliente dijo.
                </p>
                <div class="form-row">
                    <div class="form-group"><label class="checkbox-label"><input type="hidden" name="cron[reminder][enabled]" value="0"><input type="checkbox" name="cron[reminder][enabled]" value="1" <?php echo checked((bool)$config->get('cron.reminder.enabled',false)); ?>> Activado</label></div>
                    <div class="form-group"><label>Máx por ejecución</label><input type="number" name="cron[reminder][max_per_run]" value="<?php echo cv('cron.reminder.max_per_run','5'); ?>"></div>
                </div>
            </div>
        </details>

        <button type="submit" class="btn btn-primary" style="margin-top:12px">💾 Guardar Ajustes</button>
    </div>
</div>

<!-- ===== TAB: Estadísticas ===== -->
<div class="tab-content" id="tab-estadisticas">
    <div class="card">
        <h2>📈 Estadísticas</h2>
        <div id="estadisticas-container">
            <p style="color:var(--text-muted);text-align:center;padding:30px">Cargando estadísticas...</p>
        </div>
    </div>
</div>

<!-- ===== TAB: Registro (Logs) ===== -->
<div class="tab-content" id="tab-registro">
    <div class="card">
        <h2>📋 Registro de actividad</h2>
        <p style="color:var(--text-muted);font-size:.82rem;margin-bottom:12px">
            Últimas 200 líneas del registro del bot. Por curiosidad — no contiene datos sensibles.
        </p>
        <div id="registro-container">
            <pre id="registro-pre" style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;max-height:500px;overflow:auto;font-family:monospace;font-size:.72rem;white-space:pre-wrap;color:var(--text-muted)">Haz clic en la pestaña para cargar el registro de actividad.</pre>
        </div>
    </div>
</div>

</form>
<script>
var _csrf = <?php echo json_encode(generateCsrfToken()); ?>;
// ── Líneas WhatsApp ──
function loadLines() {
    fetch('api/lines.php?action=list').then(r=>r.json()).then(d=>{
        if (!d.ok) return;
        var html = '';
        if (d.lines.length === 0) {
            html = '<p style="color:var(--text-muted);text-align:center;padding:20px">No hay líneas configuradas. Añade tu primer número arriba.</p>';
        } else {
            html = '<table class="memory-table" style="font-size:.83rem"><thead><tr><th>Línea</th><th>Teléfono</th><th>Puerto</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
            d.lines.forEach(function(l) {
                var st = l.health_status || '?';
                var statusIcon = {'WORKING':'🟢 ONLINE','STARTING':'🟡 CONECTANDO','SCAN_QR':'📱 QR PENDIENTE','starting':'🟡 ARRANCANDO','down':'🔴 CAÍDA','pending':'⚪ PENDIENTE'}[st] || ('⚪ '+(st||'?'));
                var phoneDisp = l.health_phone ? l.health_phone : (l.last9||l.phone);
                html += '<tr><td><strong>'+escHtml(l.label)+'</strong></td><td class="mono">'+escHtml(phoneDisp)+'</td><td>'+l.port+'</td><td>'+statusIcon+'</td>';
                html += '<td style="white-space:nowrap">';
                html += '<button onclick="showQR('+l.id+')" class="btn btn-sm btn-primary" style="margin-right:3px">QR</button>';
                html += '<button onclick="showTest('+l.id+')" class="btn btn-sm" style="background:var(--info);color:#fff;margin-right:3px">Test</button>';
                if (st === 'WORKING') {
                    html += '<button onclick="checkLineStatus('+l.id+')" class="btn btn-sm" style="background:var(--ok);color:#fff;margin-right:3px">✓</button>';
                }
                html += '<button onclick="deleteLine('+l.id+')" class="btn btn-sm btn-danger">🗑</button>';
                html += '</td></tr>';
            });
            html += '</tbody></table>';
        }
        document.getElementById('lines-container').innerHTML = html;
    }).catch(function(){ document.getElementById('lines-container').innerHTML = '<p style="color:var(--danger)">Error al cargar líneas</p>'; });
}
function addLine() {
    var phone = document.getElementById('new-line-phone').value.trim();
    var label = document.getElementById('new-line-label').value.trim();
    if (!phone) return alert('Introduce un número de teléfono');
    var statusEl = document.getElementById('add-line-status');
    statusEl.textContent = '⏳ Creando instancia WAHA... (puede tardar 10-15 segundos)';
    var fd = new FormData(); fd.append('phone', phone); fd.append('label', label); fd.append('csrf_token', _csrf);
    fetch('api/lines.php?action=add', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) {
            statusEl.textContent = '✅ Instancia creada en puerto '+(d.line?d.line.port:'?')+'. Usa el botón QR para vincular WhatsApp.';
            document.getElementById('new-line-phone').value='';
            document.getElementById('new-line-label').value='';
            loadLines();
        } else {
            statusEl.textContent = '❌ '+(d.error||'Error al crear');
        }
    }).catch(function(){ statusEl.textContent = '❌ Error de conexión'; });
}
var currentQrLineId = 0;
function showQR(lineId) {
    currentQrLineId = lineId;
    document.getElementById('qr-modal').style.display = 'flex';
    document.getElementById('qr-image').src = '';
    document.getElementById('qr-status').textContent = 'Cargando QR...';
    fetchQR(lineId);
}
function fetchQR(lineId) {
    fetch('api/lines.php?action=qr&line_id='+lineId).then(r=>r.json()).then(d=>{
        if (d.ok && d.qr_base64) {
            document.getElementById('qr-image').src = 'data:image/png;base64,'+d.qr_base64;
            document.getElementById('qr-status').textContent = d.warning || 'Escanea con WhatsApp → Vincular dispositivo';
        } else {
            document.getElementById('qr-status').innerHTML = '<span style="color:var(--danger)">❌ '+(d.error||'No se pudo obtener QR')+'</span><br><small>Prueba a regenerar el QR o espera unos segundos.</small>';
        }
    }).catch(function(){
        document.getElementById('qr-status').textContent = '❌ Error de conexión';
    });
}
function regenerateQR() {
    if (!currentQrLineId) return;
    document.getElementById('qr-status').textContent = 'Generando nuevo QR...';
    // First restart the session to get a fresh QR
    fetch('api/lines.php?action=start_session&line_id='+currentQrLineId).then(r=>r.json()).then(function(){
        // Small delay then fetch QR
        setTimeout(function(){ fetchQR(currentQrLineId); }, 2000);
    });
}
function checkLineStatus(lineId) {
    fetch('api/lines.php?action=status').then(r=>r.json()).then(d=>{
        if (d.ok && d.statuses) {
            var st = d.statuses[lineId] || 'unknown';
            alert('Estado de la línea: ' + st);
        }
    });
}
        document.getElementById('lines-container').innerHTML = html;
    }).catch(function(){ document.getElementById('lines-container').innerHTML = '<p style="color:var(--danger)">Error al cargar líneas</p>'; });
}
function addLine() {
    var phone = document.getElementById('new-line-phone').value.trim();
    var label = document.getElementById('new-line-label').value.trim();
    if (!phone) return alert('Introduce un número de teléfono');
    var fd = new FormData(); fd.append('phone', phone); fd.append('label', label); fd.append('csrf_token', _csrf);
    fetch('api/lines.php?action=add', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) { document.getElementById('new-line-phone').value=''; document.getElementById('new-line-label').value=''; loadLines(); }
        else alert('Error: '+(d.error||'Desconocido'));
    });
}
function showQR(lineId) {
    document.getElementById('qr-modal').style.display = 'flex';
    document.getElementById('qr-image').src = '';
    document.getElementById('qr-status').textContent = 'Cargando QR...';
    fetch('api/lines.php?action=qr&line_id='+lineId).then(r=>r.json()).then(d=>{
        if (d.ok && d.qr_base64) {
            document.getElementById('qr-image').src = 'data:image/png;base64,'+d.qr_base64;
            document.getElementById('qr-status').textContent = 'Escanea con WhatsApp → Vincular dispositivo';
        } else {
            document.getElementById('qr-status').textContent = '❌ '+(d.error||'No se pudo obtener QR');
        }
    });
}
function showTest(lineId) {
    document.getElementById('test-modal').style.display = 'flex';
    document.getElementById('test-line-id').value = lineId;
    document.getElementById('test-phone').value = '';
    document.getElementById('test-result').textContent = '';
}
function sendTestMessage() {
    var lineId = document.getElementById('test-line-id').value;
    var phone = document.getElementById('test-phone').value.trim();
    if (!phone) return alert('Introduce un número de teléfono');
    var fd = new FormData(); fd.append('line_id', lineId); fd.append('test_phone', phone); fd.append('csrf_token', _csrf);
    document.getElementById('test-result').textContent = 'Enviando...';
    fetch('api/lines.php?action=test', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        document.getElementById('test-result').textContent = d.ok ? '✅ Mensaje enviado' : '❌ '+(d.error||'Error');
    });
}
function deleteLine(lineId) {
    if (!confirm('¿Eliminar esta línea? Se desvinculará del bot.')) return;
    var fd = new FormData(); fd.append('line_id', lineId); fd.append('csrf_token', _csrf);
    fetch('api/lines.php?action=delete', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) loadLines(); else alert('Error: '+(d.error||'Desconocido'));
    });
}

// ── Polling estado líneas (cada 60s) ──
var linePollInterval;
function startLinePolling() { linePollInterval = setInterval(function() {
    if (document.getElementById('tab-lineas') && document.getElementById('tab-lineas').classList.contains('active')) {
        fetch('api/lines.php?action=status').then(r=>r.json()).then(d=>{
            if (!d.ok || !d.statuses) return;
            var rows = document.querySelectorAll('#lines-container tbody tr');
            rows.forEach(function(tr, i) {
                // las filas se cargan con loadLines, el id está en el onclick
            });
        });
    }
}, 60000); }
startLinePolling();

// ── Chicas ──
function loadGirls() {
    fetch('api/girls.php?action=list').then(r=>r.json()).then(d=>{
        if (!d.ok) return;
        var html = '';
        if (d.girls.length === 0) {
            html = '<p style="color:var(--text-muted);text-align:center;padding:20px">No hay chicas configuradas. Añade la primera arriba.</p>';
        } else {
            html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px;margin-top:8px">';
            d.girls.forEach(function(g) {
                var active = g.activa ? '✅ Activa' : '❌ Inactiva';
                var activeColor = g.activa ? 'var(--ok)' : 'var(--text-muted)';
                var photos = (g.fotos||[]).slice(0,3).map(function(u,i){ return '<img src="'+escHtml(u)+'" style="width:60px;height:60px;object-fit:cover;border-radius:6px;margin:2px" onerror="this.style.display=\'none\'">'; }).join('');
                html += '<div style="background:var(--bg-surface);border:1px solid '+(g.activa?'rgba(45,212,191,.25)':'var(--border)')+';border-radius:var(--radius-sm);padding:12px">';
                html += '<strong>'+escHtml(g.nombre)+'</strong>';
                html += '<span style="font-size:.75rem;color:'+activeColor+';margin-left:8px">'+active+'</span>';
                html += '<div style="font-size:.78rem;color:var(--text-muted);margin:4px 0">'+escHtml(g.descripcion_corta||'')+'</div>';
                if (photos) html += '<div style="margin-top:6px">'+photos+'</div>';
                html += '<div style="margin-top:8px;display:flex;gap:4px">';
                html += '<button onclick="editGirl(\''+escHtml(g.id)+'\',\''+escHtml(g.nombre)+'\',\''+escHtml(g.descripcion_corta||'')+'\')" class="btn btn-sm btn-warning">✏️</button>';
                html += '<button onclick="toggleGirl(\''+escHtml(g.id)+'\')" class="btn btn-sm" style="background:var(--input-bg);color:var(--text-muted)">'+(g.activa?'Pausar':'Activar')+'</button>';
                html += '<button onclick="deleteGirl(\''+escHtml(g.id)+'\')" class="btn btn-sm btn-danger">🗑</button>';
                html += '</div></div>';
            });
            html += '</div>';
        }
        document.getElementById('girls-container').innerHTML = html;
    });
}
function saveGirl() {
    var id = document.getElementById('girl-edit-id').value;
    var fd = new FormData();
    if (id) fd.append('id', id);
    fd.append('nombre', document.getElementById('girl-nombre').value.trim());
    fd.append('descripcion', document.getElementById('girl-desc').value.trim());
    fd.append('activa', '1');
    fd.append('csrf_token', _csrf);
    fetch('api/girls.php?action=save', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) { document.getElementById('girl-edit-id').value=''; document.getElementById('girl-nombre').value=''; document.getElementById('girl-desc').value=''; document.getElementById('girl-form-title').textContent='➕ Nueva chica'; loadGirls(); }
        else alert('Error: '+(d.error||'Desconocido'));
    });
}
function editGirl(id, nombre, desc) {
    document.getElementById('girl-edit-id').value = id;
    document.getElementById('girl-nombre').value = nombre;
    document.getElementById('girl-desc').value = desc;
    document.getElementById('girl-form-title').textContent = '✏️ Editar chica';
    document.getElementById('girl-nombre').focus();
}
function editGirl(id, nombre, desc) {
    document.getElementById('girl-edit-id').value = id;
    document.getElementById('girl-nombre').value = nombre;
    document.getElementById('girl-desc').value = desc;
    document.getElementById('girl-form-title').textContent = '✏️ Editar chica';
    document.getElementById('girl-nombre').focus();
}
function addGirlPhoto() {
    // Legacy: URL-based photo add (kept for backward compat)
    var url = document.getElementById('girl-photo-url');
    if (!url) return;
    var id = document.getElementById('girl-edit-id').value;
    if (!url.value.trim() || !id) return alert('Primero guarda la chica y luego añade fotos');
    var fd = new FormData(); fd.append('id', id); fd.append('photo_url', url.value.trim());
    fd.append('csrf_token', _csrf);
    fetch('api/girls.php?action=add_photo', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) { url.value=''; loadGirls(); }
        else alert('Error: '+(d.error||'Desconocido'));
    });
}
// New: file upload
function uploadGirlPhoto() {
    var id = document.getElementById('girl-edit-id').value;
    var fileInput = document.getElementById('girl-photo-file');
    if (!id) return alert('Primero guarda la chica (nombre + descripción) y luego sube fotos.');
    if (!fileInput.files || !fileInput.files[0]) return alert('Selecciona una imagen.');
    var file = fileInput.files[0];
    if (file.size > 5*1024*1024) return alert('La imagen no puede superar 5 MB.');
    var status = document.getElementById('upload-status');
    status.textContent = '⏳ Subiendo...';
    var fd = new FormData(); fd.append('id', id); fd.append('photo', file); fd.append('csrf_token', _csrf);
    fetch('api/girls.php?action=upload_photo', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) { fileInput.value=''; status.textContent='✅ Subida'; loadGirls(); }
        else { status.textContent='❌ '+(d.error||'Error'); }
    }).catch(function(){ status.textContent='❌ Error de conexión'; });
}
function toggleGirl(id) { var fd = new FormData(); fd.append('id', id); fd.append('csrf_token', _csrf); fetch('api/girls.php?action=toggle',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.ok) loadGirls(); }); }
function deleteGirl(id) { if(!confirm('¿Eliminar esta chica?')) return; var fd = new FormData(); fd.append('id', id); fd.append('csrf_token', _csrf); fetch('api/girls.php?action=delete',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.ok) loadGirls(); }); }

// ── Estados ──
function loadEstadosConfig() {
    fetch('api/estados.php?action=config').then(r=>r.json()).then(d=>{
        if (!d.ok) return;
        var c = d.config;
        document.getElementById('estados-enabled').checked = !!c.enabled;
        document.getElementById('estados-freq-tipo').value = c.frecuencia_tipo;
        document.getElementById('estados-freq-valor').value = c.frecuencia_valor;
        document.getElementById('estados-formato').value = c.formato;
        document.getElementById('estados-hora-inicio').value = c.hora_inicio;
        document.getElementById('estados-hora-fin').value = c.hora_fin;
        // Lines checkboxes
        var lcb = document.getElementById('estados-lines-checkboxes');
        lcb.innerHTML = (c.available_lines||[]).map(function(l){
            var checked = (c.lineas||[]).indexOf(l.id) !== -1 ? 'checked' : '';
            return '<label class="checkbox-label" style="font-size:.78rem"><input type="checkbox" value="'+l.id+'" '+checked+' onchange="saveEstadosConfig()"> '+escHtml(l.label||l.last9)+'</label>';
        }).join('');
    });
}
function saveEstadosConfig() {
    var fd = new FormData();
    fd.append('csrf_token', _csrf);
    if (document.getElementById('estados-enabled').checked) fd.append('enabled','1');
    fd.append('frecuencia_tipo', document.getElementById('estados-freq-tipo').value);
    fd.append('frecuencia_valor', document.getElementById('estados-freq-valor').value);
    fd.append('formato', document.getElementById('estados-formato').value);
    fd.append('hora_inicio', document.getElementById('estados-hora-inicio').value);
    fd.append('hora_fin', document.getElementById('estados-hora-fin').value);
    var checks = document.querySelectorAll('#estados-lines-checkboxes input[type=checkbox]:checked');
    checks.forEach(function(c){ fd.append('lineas[]', c.value); });
    fetch('api/estados.php?action=config', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        document.getElementById('estados-status').textContent = d.ok ? '✅ Configuración guardada' : '❌ Error al guardar';
        setTimeout(function(){ document.getElementById('estados-status').textContent = ''; }, 3000);
    });
}
function publishEstado() {
    document.getElementById('estados-status').textContent = '⏳ Publicando...';
    var fd = new FormData(); fd.append('csrf_token', _csrf);
    fetch('api/estados.php?action=publish', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) {
            var oks = d.results.filter(function(r){return r.ok;}).length;
            document.getElementById('estados-status').textContent = '✅ Publicado en '+oks+'/'+d.results.length+' líneas';
            loadEstadosHistory();
        } else {
            document.getElementById('estados-status').textContent = '❌ '+(d.error||'Error');
        }
        setTimeout(function(){ document.getElementById('estados-status').textContent = ''; }, 5000);
    });
}
function loadEstadosHistory() {
    fetch('api/estados.php?action=history').then(r=>r.json()).then(d=>{
        if (!d.ok) return;
        var html = d.log.slice(0,10).map(function(e){
            var date = e.published_at ? new Date(e.published_at).toLocaleString('es-ES') : '?';
            return '<div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:8px 12px;margin-bottom:4px;font-size:.8rem"><strong>'+date+'</strong> — '+escHtml(e.formato)+'<br><span style="color:var(--text-muted)">'+escHtml((e.texto||'').substring(0,80))+'</span></div>';
        }).join('');
        document.getElementById('estados-history').innerHTML = html || '<p style="color:var(--text-muted);text-align:center;padding:10px">Sin publicaciones</p>';
    });
}

// ── Helper ──
function escHtml(s) { var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

// ── Clientes ──
function loadClientes() {
    fetch('api/clientes.php?action=list').then(r=>r.json()).then(d=>{
        if (!d.ok) return;
        var html = '';
        if (d.leads.length === 0) {
            html = '<p style="color:var(--text-muted);text-align:center;padding:20px">No hay leads registrados todavía.</p>';
        } else {
            html = '<div style="display:flex;gap:8px;margin-bottom:12px"><span style="color:var(--text-muted);font-size:.8rem">'+d.total+' leads</span></div>';
            html += '<table class="memory-table" style="font-size:.82rem"><thead><tr><th>Fecha</th><th>Teléfono</th><th>Línea</th><th>Confianza</th><th>¿Llegó?</th></tr></thead><tbody>';
            d.leads.forEach(function(l){
                var arrivedIcon = l.arrived ? '✅ Sí' : '❌ No';
                var arrivedBtn = l.arrived
                    ? '<button onclick="markLeadArrived(\''+escHtml(l.thread_id)+'\',false)" class="btn btn-sm" style="background:var(--ok-bg);color:var(--ok)">✅ Sí</button>'
                    : '<button onclick="markLeadArrived(\''+escHtml(l.thread_id)+'\',true)" class="btn btn-sm btn-warning">Marcar llegó</button>';
                var confColor = parseInt(l.confidence) > 80 ? 'color:var(--ok)' : (parseInt(l.confidence) > 50 ? 'color:var(--warn)' : 'color:var(--text-muted)');
                html += '<tr><td class="mono">'+escHtml(l.ts)+'</td><td class="mono">'+escHtml(l.phone)+'</td><td>'+escHtml(l.line_label)+'</td><td style="'+confColor+'">'+l.confidence+'</td><td>'+arrivedBtn+'</td></tr>';
            });
            html += '</tbody></table>';
        }
        document.getElementById('clientes-table-container').innerHTML = html;
    });
}
function markLeadArrived(threadId, arrived) {
    var fd = new FormData(); fd.append('csrf_token', _csrf); fd.append('thread_id', threadId);
    if (arrived) fd.append('arrived', '1'); else fd.append('arrived', '0');
    fetch('api/clientes.php?action=mark_arrived', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) loadClientes(); else alert('Error: '+(d.error||'Desconocido'));
    });
}

// ── Mensajes ──
var currentChatThreadId = '';
function loadMensajes() { searchThreads(); }
function searchThreads() {
    var q = document.getElementById('msg-search').value.trim();
    fetch('api/mensajes.php?action=threads&search='+encodeURIComponent(q)).then(r=>r.json()).then(d=>{
        if (!d.ok) return;
        var html = '';
        if (d.threads.length === 0) {
            html = '<p style="color:var(--text-muted);text-align:center;padding:20px">No se encontraron conversaciones.</p>';
        } else {
            html = '<table class="memory-table" style="font-size:.82rem"><thead><tr><th>Teléfono</th><th>Mensajes</th><th>Último</th><th>Acción</th></tr></thead><tbody>';
            d.threads.forEach(function(t){
                var phoneDisp = t.phone.length > 6 ? '...' + t.phone.substring(t.phone.length-6) : t.phone;
                html += '<tr><td class="mono"><strong>'+escHtml(phoneDisp)+'</strong></td><td>'+t.count+'</td><td style="font-size:.75rem;color:var(--text-muted)">'+escHtml(t.last_msg)+'</td>';
                html += '<td><button onclick="openChat(\''+escHtml(t.thread_id)+'\')" class="btn btn-sm btn-primary">Ver</button></td></tr>';
            });
            html += '</tbody></table>';
        }
        document.getElementById('mensajes-threads-container').innerHTML = html;
    });
}
function openChat(threadId) {
    currentChatThreadId = threadId;
    document.getElementById('chat-modal').style.display = 'block';
    document.getElementById('chat-messages').innerHTML = '<p style="color:var(--text-muted)">Cargando...</p>';
    fetch('api/mensajes.php?action=conversation&thread_id='+encodeURIComponent(threadId)).then(r=>r.json()).then(d=>{
        if (!d.ok) return;
        var html = '';
        d.conversation.forEach(function(m){
            var dt = m.ts ? new Date(m.ts).toLocaleString('es-ES') : '';
            html += '<div style="margin-bottom:10px;padding:8px 12px;background:var(--bg-surface);border-radius:var(--radius-sm);font-size:.82rem">';
            if (m.user_msg) html += '<div style="color:var(--info);margin-bottom:2px"><strong>👤 Cliente:</strong> '+escHtml(m.user_msg)+'</div>';
            if (m.bot_reply) html += '<div style="color:var(--ok)"><strong>🤖 Bot:</strong> '+escHtml(m.bot_reply)+'</div>';
            html += '<div style="font-size:.7rem;color:var(--text-muted);margin-top:2px">'+dt+'</div></div>';
        });
        document.getElementById('chat-messages').innerHTML = html || '<p style="color:var(--text-muted)">Conversación vacía</p>';
    });
}
function closeChat() { document.getElementById('chat-modal').style.display = 'none'; currentChatThreadId = ''; }
function markAsLeadFromChat() {
    if (!currentChatThreadId) return;
    var fd = new FormData(); fd.append('thread_id', currentChatThreadId);
    fetch('api/mensajes.php?action=mark_lead', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        alert(d.ok ? 'Conversación marcada como lead ✅' : 'Error: '+(d.error||'Desconocido'));
        closeChat();
    });
}

// ── Estadísticas ──
function loadEstadisticas() {
    fetch('api/stats.php').then(r=>r.json()).then(d=>{
        if (!d.ok) { document.getElementById('estadisticas-container').innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:30px">Error al cargar estadísticas.</p>'; return; }
        var s = d.stats;

        // Hero stat: arrival rate with progress ring
        var rate = s.arrival_rate;
        var rateColor = rate>=50 ? 'var(--ok)' : (rate>=25 ? 'var(--warn)' : 'var(--danger)');
        var rateLabel = rate>=50 ? 'Excelente' : (rate>=25 ? 'Normal' : 'Bajo');

        var html = '';

        // Hero section
        html += '<div style="display:flex;gap:20px;flex-wrap:wrap;margin-bottom:20px">';
        html += '<div style="flex:1;min-width:200px;background:linear-gradient(135deg,var(--bg-surface),var(--panel));border:1px solid var(--border);border-radius:var(--radius-md);padding:24px;text-align:center">';
        html += '<div style="position:relative;display:inline-block">';
        html += '<svg width="100" height="100" viewBox="0 0 100 100"><circle cx="50" cy="50" r="42" fill="none" stroke="var(--border)" stroke-width="8"/><circle cx="50" cy="50" r="42" fill="none" stroke="'+rateColor+'" stroke-width="8" stroke-dasharray="'+(rate*2.64)+' 264" stroke-linecap="round" transform="rotate(-90 50 50)" style="transition:stroke-dasharray .8s"/></svg>';
        html += '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;color:'+rateColor+'">'+rate+'%</div>';
        html += '</div>';
        html += '<div style="font-size:.85rem;color:var(--text-muted);margin-top:4px">Tasa de llegada</div>';
        html += '<div style="font-size:.72rem;color:'+rateColor+'">'+rateLabel+'</div>';
        html += '<div style="font-size:.7rem;color:var(--text-muted);margin-top:4px">'+s.leads_arrived+' llegaron de '+s.leads_total+' notificados</div>';
        html += '</div>';

        // Quick stats
        html += '<div style="flex:2;min-width:300px">';
        html += '<div class="stats-grid">';
        html += '<div class="stat-card"><div class="stat-num" style="color:var(--info)">'+s.conversations_total+'</div><div class="stat-label">💬 Conversaciones</div><div class="stat-sub">'+s.conversations_today+' hoy</div></div>';
        html += '<div class="stat-card"><div class="stat-num" style="color:var(--ok)">'+s.leads_total+'</div><div class="stat-label">🎯 Leads</div><div class="stat-sub">'+s.leads_today+' hoy</div></div>';
        html += '<div class="stat-card"><div class="stat-num" style="color:#a78bfa">'+s.leads_arrived+'</div><div class="stat-label">✅ Llegaron</div><div class="stat-sub">'+s.leads_pending+' pendientes</div></div>';
        html += '<div class="stat-card"><div class="stat-num" style="color:var(--accent)">'+s.girls_active+'</div><div class="stat-label">👩 Chicas</div><div class="stat-sub">Activas</div></div>';
        html += '<div class="stat-card"><div class="stat-num" style="color:var(--accent2)">'+s.lines_active+'</div><div class="stat-label">📱 Líneas</div><div class="stat-sub">Vinculadas</div></div>';
        html += '<div class="stat-card"><div class="stat-num" style="color:var(--money)">'+s.conversations_week+'</div><div class="stat-label">📅 Esta semana</div><div class="stat-sub">Conversaciones</div></div>';
        html += '</div>';
        html += '</div></div>';

        // 7-day chart
        var maxVal = Math.max.apply(null, d.stats.daily_graph.map(function(g){ return Math.max(g.conversations, g.leads); })) || 1;
        var colors = ['var(--info)','var(--accent)','var(--ok)','var(--money)','var(--accent2)','#a78bfa','#f97316'];
        html += '<div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:20px;margin-bottom:16px">';
        html += '<h3 style="margin-bottom:14px">📊 Actividad últimos 7 días</h3>';
        html += '<div style="display:flex;align-items:flex-end;height:130px;gap:4px;padding:0 2px">';
        d.stats.daily_graph.forEach(function(g, i){
            var convH = Math.max((g.conversations/maxVal*100).toFixed(0), 2);
            var leadH = Math.max((g.leads/maxVal*100).toFixed(0), 2);
            html += '<div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%">';
            html += '<div style="font-size:.6rem;color:var(--text-muted);margin-bottom:2px">'+(g.conversations||'')+'</div>';
            html += '<div style="background:'+colors[i%7]+';width:70%;height:'+convH+'%;border-radius:3px 3px 0 0;min-height:4px;position:relative" title="'+g.conversations+' conversaciones"></div>';
            html += '<div style="font-size:.6rem;color:var(--accent);margin-top:1px">'+(g.leads||'')+'</div>';
            html += '<div style="background:var(--accent);opacity:.7;width:40%;height:'+leadH+'%;border-radius:3px 3px 0 0;min-height:4px" title="'+g.leads+' leads"></div>';
            html += '<div style="font-size:.55rem;color:var(--text-muted);margin-top:3px">'+g.date+'</div>';
            html += '</div>';
        });
        html += '</div>';
        html += '<div style="display:flex;gap:16px;margin-top:8px;font-size:.7rem;color:var(--text-muted);justify-content:center"><span>█ Conversaciones</span><span style="color:var(--accent);opacity:.7">█ Leads</span></div>';
        html += '</div>';

        // Fun facts
        html += '<div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:16px;text-align:center">';
        html += '<h3 style="margin-bottom:10px">✨ Datos curiosos</h3>';
        var ratio = s.leads_total > 0 && s.conversations_total > 0 ? (s.leads_total/s.conversations_total*100).toFixed(1) : 0;
        html += '<div style="display:flex;flex-wrap:wrap;gap:20px;justify-content:center;font-size:.82rem">';
        html += '<div><span style="color:var(--text-muted)">Conversión:</span> <strong style="color:var(--accent)">'+ratio+'%</strong> de conversaciones generan lead</div>';
        html += '<div><span style="color:var(--text-muted)">Efectividad:</span> <strong style="color:var(--ok)">'+(s.leads_arrived>0&&s.leads_total>0?(s.leads_arrived/s.leads_total*100).toFixed(0):0)+'%</strong> de leads llegaron</div>';
        html += '<div><span style="color:var(--text-muted)">Promedio diario:</span> <strong style="color:var(--info)">'+(s.leads_week>0?(s.leads_week/7).toFixed(1):0)+'</strong> leads/día</div>';
        html += '</div></div>';

        document.getElementById('estadisticas-container').innerHTML = html;
    }).catch(function(){
        document.getElementById('estadisticas-container').innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:30px">Sin datos todavía. ¡Empieza a usar el bot!</p>';
    });
}

// ── Registro (Logs) ──
function loadRegistro() {
    fetch('api/logs.php').then(r=>r.json()).then(d=>{
        if (d.ok) {
            document.getElementById('registro-pre').textContent = d.log || '(sin actividad todavía)';
        } else {
            document.getElementById('registro-pre').textContent = 'Error al cargar registros';
        }
        var pre = document.getElementById('registro-pre');
        if (pre) pre.scrollTop = pre.scrollHeight;
    }).catch(function(){
        document.getElementById('registro-pre').textContent = '(sin actividad todavía)';
    });
}

// ── Load data when tabs become active ──
var tabLoaders = {
    'tab-lineas': loadLines,
    'tab-chicas': loadGirls,
    'tab-estados': function() { loadEstadosConfig(); loadEstadosHistory(); },
    'tab-clientes': loadClientes,
    'tab-mensajes': loadMensajes,
    'tab-estadisticas': loadEstadisticas,
    'tab-registro': loadRegistro,
};
var loadedTabs = {};
document.querySelectorAll('#tabNav button[data-tab]').forEach(function(btn){
    btn.addEventListener('click', function(){
        var tabId = btn.getAttribute('data-tab');
        if (tabLoaders[tabId] && !loadedTabs[tabId]) { loadedTabs[tabId]=true; tabLoaders[tabId](); }
    });
});
// ── Tab switching ──
(function() {
    var btns = document.querySelectorAll('#tabNav button[data-tab]');
    var tabs = document.querySelectorAll('.tab-content');
    var activeInput = document.querySelector('.js-active-tab-input');
    var stored = localStorage.getItem('botcasa_client_tab');
    btns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            btns.forEach(function(b) { b.classList.remove('active'); });
            tabs.forEach(function(t) { t.classList.remove('active'); });
            btn.classList.add('active');
            var target = document.getElementById(btn.getAttribute('data-tab'));
            if (target) { target.classList.add('active'); }
            if (activeInput) { activeInput.value = btn.getAttribute('data-tab'); }
            localStorage.setItem('botcasa_client_tab', btn.getAttribute('data-tab'));
        });
    });
    if (stored) {
        var btn = document.querySelector('#tabNav button[data-tab="' + stored + '"]');
        if (btn) btn.click();
    }
})();

// ── Prompt preview ──
function buildPreview() {
    try {
    var tono = document.querySelector('select[name="prompt[sections][estilo_tipo]"]');
    if (!tono) return; // Only build if Personalidad tab elements exist
    var tonoLabel = tono ? tono.options[tono.selectedIndex].text.split(' ').slice(1).join(' ') : 'Latina de barrio';
    var speaker = document.querySelector('select[name="prompt[sections][speaker_mode]"]');
    var speakerLabel = speaker ? speaker.options[speaker.selectedIndex].text : 'Como la chica';
    var emoji = document.querySelector('select[name="prompt[sections][emoji_level]"]');
    var emojiLabel = emoji ? emoji.options[emoji.selectedIndex].text : 'Moderado';
    var len = document.querySelector('select[name="prompt[sections][reply_length]"]');
    var lenLabel = len ? len.options[len.selectedIndex].text : 'Corta';
    var tarifas = document.querySelector('textarea[name="prompt[sections][tarifas]"]');
    var zona = document.querySelector('input[name="prompt[sections][zona]"]');
    var servicios = document.querySelector('textarea[name="prompt[sections][servicios]"]');

    // Count price lines (e.g. "30€...") in tarifas
    var tarifasVal = tarifas ? tarifas.value.trim() : '';
    var priceCount = (tarifasVal.match(/[\d]+[€$]/g) || []).length;
    var hasTarifas = tarifasVal.length > 10;
    var hasZona = zona && zona.value.trim().length > 0;
    var hasServicios = servicios && servicios.value.trim().length > 10;

    var html = '';
    html += '<div style="margin-bottom:12px"><strong style="color:var(--accent)">🎨 Estilo:</strong> ' + escHtml(tonoLabel) + '</div>';
    html += '<div style="margin-bottom:12px"><strong>🗣 Habla:</strong> ' + escHtml(speakerLabel) + ' · ' + escHtml(emojiLabel) + ' · ' + escHtml(lenLabel) + '</div>';

    html += '<div style="margin-bottom:12px"><strong>💰 Tarifas:</strong> ';
    if (hasTarifas) {
        html += '<span style="color:var(--ok)">✅ ' + priceCount + ' precios configurados</span>';
    } else {
        html += '<span style="color:var(--warn)">⚠️ Sin configurar</span>';
    }
    html += '</div>';

    html += '<div style="margin-bottom:12px"><strong>📍 Ubicación:</strong> ';
    if (hasZona) {
        html += '<span style="color:var(--ok)">✅ ' + escHtml(zona.value.trim().substring(0,40)) + '</span>';
    } else {
        html += '<span style="color:var(--warn)">⚠️ Sin configurar</span>';
    }
    html += '</div>';

    html += '<div style="margin-bottom:12px"><strong>🛏️ Servicios:</strong> ';
    if (hasServicios) {
        html += '<span style="color:var(--ok)">✅ Configurados</span>';
    } else {
        html += '<span style="color:var(--warn)">⚠️ Sin configurar</span>';
    }
    html += '</div>';

    var configured = (hasTarifas ? 1 : 0) + (hasZona ? 1 : 0) + (hasServicios ? 1 : 0);
    html += '<div style="margin-top:12px;padding-top:10px;border-top:1px solid var(--border);text-align:center;color:var(--text-muted);font-size:.78rem">';
    html += configured + '/3 secciones configuradas';
    html += '</div>';

    var summary = document.getElementById('prompt-summary');
    if (summary) summary.innerHTML = html;

    var stats = document.getElementById('prompt-stats');
    if (stats) stats.innerHTML = '';
    } catch(e) { /* silently ignore if elements not found */ }
}

// Reset field to default value
function resetField(name, defaultValue) {
    var el = document.querySelector('[name="' + name + '"]');
    if (el) {
        el.value = defaultValue;
        el.dispatchEvent(new Event('input', {bubbles:true}));
        buildPreview();
    }
}
</script>
</body>
</html>
