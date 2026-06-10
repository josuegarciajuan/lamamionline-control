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
    // Clear admin's personal data from dist template
    $config->set('telegram.chat_ids', []);
    $config->set('telegram.whatsapp_phones', '');
    $config->set('telegram.alert_enabled', false);
    $config->set('urls.google_maps_location', '');
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
        'prompt.sections.no_regateo',
        'prompt.sections.regateo_1',
        'prompt.sections.regateo_2',
        'prompt.sections.regateo_3',
        'prompt.sections.maps_solo_chica',
        // URL field
        'urls.google_maps_location',
        // Telegram
        'telegram.chat_ids',
        'telegram.whatsapp_phones',
        'telegram.alert_enabled',
        // Human delays (all subkeys)
        'human_delays.seen.fallback_sec',
        'human_delays.seen.random_min_sec',
        'human_delays.seen.random_max_sec',
        'human_delays.typing.fallback_sec',
        'human_delays.typing.chars_per_sec_min',
        'human_delays.typing.chars_per_sec_max',
        'human_delays.read.base_min_ms',
        'human_delays.read.base_max_ms',
        'human_delays.presend_sleep_sec',
        'human_delays.habituation.start_boost',
        'human_delays.habituation.decay',
        'human_delays.habituation.floor',
        // Cron
        'cron.followup.enabled',
        'cron.followup.max_leads_per_run',
        'cron.followup.send_window_start',
        'cron.followup.send_window_end',
        'cron.reminder.enabled',
        // Textarea list keys
        'message_variants.audio_auto_reply',
        'message_variants.eta_request_variants',
        'message_variants.dedup_start',
        'message_variants.dedup_end',
        'cron.followup.intro_variants',
        'cron.followup.closing_variants',
        'cron.reminder.message_variants',
        // Tab tracking
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
    $textareaKeys = ['telegram.chat_ids', 'telegram.whatsapp_phones', 'message_variants.audio_auto_reply', 'message_variants.eta_request_variants', 'message_variants.dedup_start', 'message_variants.dedup_end',
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

// ── Config check for A6 (moved here — needed by toggle_bot and progress) ──
// Load factory-default tariffs from dist config to compare full text (not just prefix)
$distTarifas = '';
$distPath = WASAPBOT_ROOT . '/config.dist.json';
if (file_exists($distPath)) {
    $distData = @json_decode((string)@file_get_contents($distPath), true);
    if (is_array($distData)) {
        $distTarifas = (string)($distData['prompt']['sections']['tarifas'] ?? '');
    }
}
$tarifasVal = (string)$config->get('prompt.sections.tarifas','');
$promptConfigured = strlen($tarifasVal) > 20 && trim($tarifasVal) !== trim($distTarifas);

// ── Toggle bot ──
if ($method === 'POST' && $action === 'toggle_bot') {
    requireValidCsrf();
    $newMode = ($botMode === 'start') ? 'stop' : 'start';

    // A4: Block turning ON if not fully configured
    if ($newMode === 'start') {
        $errors = [];
        if ($linesForUser <= 0) $errors[] = 'No tienes ninguna línea WhatsApp vinculada. Ve a 📱 Líneas.';
        if (!$promptConfigured) $errors[] = 'No has configurado tus tarifas. Ve a 🎭 Personalidad.';
        // Check active girls
        $activeGirls = 0;
        $gf = WASAPBOT_ROOT . '/data/users/' . $clientUserId . '/girls.json';
        if (file_exists($gf)) {
            $gd = @json_decode((string)@file_get_contents($gf), true);
            if (is_array($gd)) $activeGirls = count(array_filter($gd['girls']??[], fn($g)=>!empty($g['activa'])));
        }
        if ($activeGirls <= 0) $errors[] = 'No tienes chicas activas. Añade al menos una en 👩 Chicas.';
        // Check notification config
        $hasTelegram2 = false; $hasWhatsApp2 = false;
        $tgVal2 = $config->get('telegram.chat_ids', '');
        if (is_array($tgVal2)) $hasTelegram2 = count(array_filter($tgVal2, fn($v) => trim((string)$v) !== '')) > 0;
        elseif (is_string($tgVal2) && trim($tgVal2) !== '') $hasTelegram2 = true;
        $waVal2 = $config->get('telegram.whatsapp_phones', '');
        if (is_array($waVal2)) $hasWhatsApp2 = count(array_filter($waVal2, fn($v) => trim((string)$v) !== '')) > 0;
        elseif (is_string($waVal2) && trim($waVal2) !== '') $hasWhatsApp2 = true;
        if (!$hasTelegram2 && !$hasWhatsApp2) $errors[] = 'No has configurado dónde recibir avisos. Ve a 👥 Clientes y añade tu Chat ID de Telegram o tu teléfono WhatsApp.';
        if (!empty($errors)) {
            $notification = '<div class="alert alert-warning"><strong>⚠️ No se puede encender el bot todavía:</strong><br>• ' . implode('<br>• ', $errors) . '</div>';
        } else {
            $dir = dirname($modeFilePath);
            if (!is_dir($dir)) @mkdir($dir, 0750, true);
            if (@file_put_contents($modeFilePath, $newMode, LOCK_EX) !== false) @chmod($modeFilePath, 0664);
            $botMode = $newMode;
            $botStatusClass = 'status-on'; $botStatusLabel = 'ENCENDIDO';
            $notification = '<div class="alert alert-success">✅ Bot ENCENDIDO. ¡A recibir clientes!</div>';
        }
    } else {
        $dir = dirname($modeFilePath);
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        if (@file_put_contents($modeFilePath, $newMode, LOCK_EX) !== false) @chmod($modeFilePath, 0664);
        $botMode = $newMode;
        $botStatusClass = 'status-off'; $botStatusLabel = 'APAGADO';
        $notification = '<div class="alert alert-success">✅ Bot APAGADO.</div>';
    }
}

// ─────────────────────────────────────────────────────────────────────
//   Render
// ─────────────────────────────────────────────────────────────────────

$sectionKeys = ['rol', 'estilo', 'tarifas', 'servicios', 'ubicacion', 'instrucciones_fotos', 'identidad_chicas', 'seguridad', 'ejemplos', 'formato_respuesta'];

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>bot-casa — <?php echo $clientName; ?></title>
<link rel="stylesheet" href="assets/style.css?v=20260610_1">
<link rel="stylesheet" href="assets/chat.css?v=20260610_1">
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

/* ── Header value-prop ── */
.header-brand { display: flex; flex-direction: column; }
.header-tagline {
    font-size: .75rem; color: var(--text-muted); margin-top: 2px;
    font-weight: 400; opacity: .8; max-width: 360px; line-height: 1.4;
}
.header-pills { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.header-pill {
    display: inline-flex; align-items: center; gap: 4px;
    background: rgba(255,59,141,0.08); border: 1px solid rgba(255,59,141,0.15);
    border-radius: var(--radius-pill); padding: 4px 12px;
    font-size: .72rem; color: var(--accent-light);
    white-space: nowrap; font-weight: 500;
}

/* ── Wizard polish ── */
.wizard-hero {
    width: 72px; height: 72px; border-radius: 50%;
    background: linear-gradient(135deg, var(--accent), var(--accent2));
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px; font-size: 2rem;
    animation: wizardPulse 2.5s infinite;
    box-shadow: 0 0 30px rgba(255,59,141,0.3);
}
@keyframes wizardPulse {
    0%, 100% { box-shadow: 0 0 30px rgba(255,59,141,0.3); }
    50%      { box-shadow: 0 0 50px rgba(255,59,141,0.55); }
}
.wizard-step {
    animation: wizardStepIn 0.4s cubic-bezier(0.16,1,0.3,1) both;
}
.wizard-step:nth-child(1) { animation-delay: 0.1s; }
.wizard-step:nth-child(2) { animation-delay: 0.2s; }
.wizard-step:nth-child(3) { animation-delay: 0.3s; }
@keyframes wizardStepIn {
    from { opacity: 0; transform: translateX(-12px); }
    to   { opacity: 1; transform: translateX(0); }
}

/* ── Responsive header ── */
@media (max-width: 768px) {
    .header-pills { order: 3; width: 100%; }
    .header-tagline { max-width: 100%; font-size: .7rem; }
}
</style>
</head>
<body>

<div class="header">
    <div class="header-brand">
        <h1>casawasap<span style="font-size:.55em;opacity:.7;font-weight:400">.com</span></h1>
        <span class="subtitle"><?php echo $clientName; ?></span>
        <div class="header-tagline">Tu bot de WhatsApp que atiende, negocia y cierra clientes</div>
    </div>
    <div class="header-pills">
        <span class="header-pill">🤖 IA Conversacional</span>
        <span class="header-pill">💬 24 Horas</span>
        <span class="header-pill">📊 Leads Automáticos</span>
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
        <button onclick="window.location.reload()" class="btn btn-sm" title="Recargar panel" style="background:var(--input-bg);color:var(--text-muted);border:1px solid var(--border);cursor:pointer;font-size:.8rem;border-radius:6px;padding:6px 10px">↻ Recargar</button>
        <a href="logout" class="btn btn-sm" style="background:var(--input-bg);color:var(--text-muted);text-decoration:none">Salir</a>
    </div>
</div>

<?php echo $notification; ?>

<?php
// ── Progress indicator ──
$progressTotal = 4;
$progressDone = 0;
if ($linesForUser > 0) $progressDone++;
if ($promptConfigured) $progressDone++;
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
        <div class="wizard-hero">🚀</div>
        <h2 style="color:var(--accent);margin-bottom:8px">¡Bienvenido a bot-casa!</h2>
        <p style="color:var(--text-muted);margin-bottom:16px;font-size:.9rem">
            Configura tu bot en 3 pasos sencillos para empezar a recibir clientes por WhatsApp automáticamente.
        </p>
        <div style="text-align:left;margin-bottom:20px">
            <div class="wizard-step" style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--bg-surface);border-radius:var(--radius-sm);margin-bottom:8px;border-left:3px solid var(--accent)">
                <span style="font-size:1.5rem;flex-shrink:0">1️⃣</span>
                <div><strong>Personalidad</strong><br><span style="font-size:.78rem;color:var(--text-muted)">Define tarifas, ubicación y estilo → pestaña 🎭 Personalidad</span></div>
            </div>
            <div class="wizard-step" style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--bg-surface);border-radius:var(--radius-sm);margin-bottom:8px;border-left:3px solid var(--accent)">
                <span style="font-size:1.5rem;flex-shrink:0">2️⃣</span>
                <div><strong>Líneas WhatsApp</strong><br><span style="font-size:.78rem;color:var(--text-muted)">Vincula tus números → pestaña 📱 Líneas</span></div>
            </div>
            <div class="wizard-step" style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--bg-surface);border-radius:var(--radius-sm);border-left:3px solid var(--accent)">
                <span style="font-size:1.5rem;flex-shrink:0">3️⃣</span>
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
    <button type="button" data-tab="tab-mensajes">💬 Chat</button>
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
            Tu bot es un asistente virtual que atiende tus conversaciones de WhatsApp 
            <strong style="color:var(--accent)">las 24 horas del día</strong>. Responde de forma natural, 
            negocia precios, envía fotos y ubicación, y sabe reconocer cuándo un cliente 
            está realmente interesado.
        </p>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:8px">
            Mientras no puedes contestar —porque estás durmiendo, ocupado o descansando— el bot 
            mantiene viva cada conversación. En cualquier momento puedes entrar desde la pestaña 
            <strong style="color:var(--accent)">💬 Chat</strong> y tomar el control tú mismo.
        </p>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:16px">
            Además, el bot no solo reacciona: <strong style="color:var(--accent)">publica estados de WhatsApp</strong> 
            automáticamente para atraer nuevos contactos y hace <strong style="color:var(--accent)">seguimiento 
            con clientes antiguos</strong> para que vuelvan. Cuando está seguro de que un cliente 
            viene de camino, <strong style="color:var(--accent)">te avisa al instante</strong>.
        </p>

        <div style="margin-top:16px">
            <h3>✅ Configuración necesaria</h3>
            <?php
            $promptConfigured = $promptConfigured;
            $linesConfigured = $linesForUser > 0;
            $hasNotifications = false;
            $tgVal = $config->get('telegram.chat_ids', '');
            $waVal = $config->get('telegram.whatsapp_phones', '');
            if (is_array($tgVal)) $hasNotifications = count(array_filter($tgVal, fn($v) => trim((string)$v) !== '')) > 0;
            elseif (is_string($tgVal) && trim($tgVal) !== '') $hasNotifications = true;
            if (!$hasNotifications) {
                if (is_array($waVal)) $hasNotifications = count(array_filter($waVal, fn($v) => trim((string)$v) !== '')) > 0;
                elseif (is_string($waVal) && trim($waVal) !== '') $hasNotifications = true;
            }
            $checkItems = [
                ['✅ Vincular WhatsApp', $linesConfigured, 'Configura tus líneas en la pestaña 📱 Líneas.'],
                ['✅ Configurar tarifas', $promptConfigured, 'Define tus precios en la pestaña 🎭 Personalidad.'],
                ['⏳ Chicas configuradas', false, 'Añade tu catálogo en la pestaña 👩 Chicas.'],
                ['✅ Avisos configurados', $hasNotifications, 'Pon tu Chat ID de Telegram o teléfono en 👥 Clientes.'],
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
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-top:8px">
            <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px;text-align:center">
                <div style="font-size:2rem;margin-bottom:4px">🎭</div>
                <strong>Personalidad</strong>
                <p style="font-size:.75rem;color:var(--text-muted);margin-top:4px">Define tarifas, estilo y ubicación</p>
            </div>
            <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px;text-align:center">
                <div style="font-size:2rem;margin-bottom:4px">📱</div>
                <strong>Líneas WhatsApp</strong>
                <p style="font-size:.75rem;color:var(--text-muted);margin-top:4px">Vincula tus números</p>
            </div>
            <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px;text-align:center">
                <div style="font-size:2rem;margin-bottom:4px">👩</div>
                <strong>Catálogo de chicas</strong>
                <p style="font-size:.75rem;color:var(--text-muted);margin-top:4px">Añade tu equipo</p>
            </div>
            <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px;text-align:center">
                <div style="font-size:2rem;margin-bottom:4px">👥</div>
                <strong>Avisos</strong>
                <p style="font-size:.75rem;color:var(--text-muted);margin-top:4px">Configura Telegram o WhatsApp</p>
            </div>
            <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px;text-align:center">
                <div style="font-size:2rem;margin-bottom:4px">🚀</div>
                <strong>¡A funcionar!</strong>
                <p style="font-size:.75rem;color:var(--text-muted);margin-top:4px">Enciende y recibe clientes</p>
            </div>
        </div>
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
                                'latina_barrio'  => '💃 Cercana y directa (coloquial, natural)',
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
                        <select name="prompt[sections][speaker_mode]" onchange="buildPreview()">
                            <?php
                            $speakerModes = [
                                'mixto'         => '🔄 Mixto — recomendado (detecta automáticamente)',
                                'chica'         => '👩 Como la chica — 1ª persona (solo 1 chica)',
                                'recepcionista' => '👔 Como encargada — 3ª persona (varias chicas)',
                            ];
                            $currentSpeaker = (string) $config->get('prompt.sections.speaker_mode', 'mixto');
                            foreach ($speakerModes as $val => $label) {
                                echo '<option value="' . $val . '"' . selected($val === $currentSpeaker) . '>' . $label . '</option>';
                            }
                            ?>
                        </select>
                        <small style="color:var(--text-muted);font-size:.72rem;margin-top:4px;display:block">
                            <strong>Mixto:</strong> Si el cliente sabe qué chica es (ej: viene de un anuncio donde sí aparece el nombre, 
                            o ya la conoce de antes), el bot habla como ella en 1ª persona. Si no lo sabe (ej: anuncio genérico sin nombre), 
                            habla como encargada en 3ª persona.
                        </small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Uso de emojis</label>
                        <select name="prompt[sections][emoji_level]" onchange="buildPreview()">
                            <?php
                            $emojiLevels = ['moderado' => '😊 Moderado (1 por mensaje)', 'normal' => '🙂 Normal (1 cada 2-3 mensajes, adaptable)', 'poco' => '😐 Poco (ocasional)', 'nada' => '🚫 Sin emojis'];
                            $currentEmoji = (string) $config->get('prompt.sections.emoji_level', 'moderado');
                            foreach ($emojiLevels as $val => $label) {
                                echo '<option value="' . $val . '"' . selected($val === $currentEmoji) . '>' . $label . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Longitud de respuestas</label>
                        <select name="prompt[sections][reply_length]" onchange="buildPreview()">
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
                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px">💾 Guardar estilo</button>
            </div>

            <!-- Tarifas (A11: accordion + bigger) -->
            <details class="prompt-details" open>
                <summary class="prompt-summary">💰 Tarifas y precios</summary>
                <div style="padding:12px;border-top:1px solid var(--border)">
                <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:8px">
                    Escribe tus tarifas en lenguaje natural. Ejemplo: "30€ rapidito 10 min, 50€ media hora, 100€ 1 hora completo"
                </p>
                <textarea name="prompt[sections][tarifas]" class="code-area" style="width:100%;min-height:180px" spellcheck="false" oninput="buildPreview()"><?php echo cv('prompt.sections.tarifas'); ?></textarea>
                <div style="margin-top:8px;display:flex;gap:6px">
                    <button type="submit" class="btn btn-primary btn-sm">💾 Guardar tarifas</button>
                    <button type="button" class="btn btn-sm" style="background:var(--input-bg);color:var(--text-muted)" 
                        onclick="if(confirm('¿Restaurar tarifas por defecto?'))resetField('prompt\\[sections\\]\\[tarifas\\]','30€ = rapidito 10 min\\n50€ = media hora completo\\n100€ = 1 hora completo')">🔄 Restaurar</button>
                </div>
                </div>
            </details>

            <!-- Anti-regateo (A12) -->
            <details class="prompt-details">
                <summary class="prompt-summary">🛡️ Anti-regateo</summary>
                <div style="padding:12px;border-top:1px solid var(--border)">
                <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:8px">Cómo reacciona el bot cuando un cliente intenta negociar el precio.</p>
                <label class="checkbox-label" style="margin-bottom:8px">
                    <input type="hidden" name="prompt[sections][no_regateo]" value="0">
                    <input type="checkbox" name="prompt[sections][no_regateo]" value="1" <?php echo checked((bool)$config->get('prompt.sections.no_regateo',false)); ?> onchange="buildPreview()"> No aceptar regateo de ningún tipo
                </label>
                <div class="form-group"><label>1er regateo <span style="color:var(--text-muted);font-weight:400">— primera vez que negocian</span></label><input type="text" name="prompt[sections][regateo_1]" value="<?php echo cv('prompt.sections.regateo_1','precio fijo cari, por eso la calidad es buena 😏'); ?>" placeholder="Respuesta al primer regateo"></div>
                <div class="form-group"><label>2º regateo <span style="color:var(--text-muted);font-weight:400">— si insisten</span></label><input type="text" name="prompt[sections][regateo_2]" value="<?php echo cv('prompt.sections.regateo_2','no puedo bajar mas amor, son los precios que tengo'); ?>" placeholder="Respuesta si insiste"></div>
                <div class="form-group"><label>3er regateo <span style="color:var(--text-muted);font-weight:400">— corte final</span></label><input type="text" name="prompt[sections][regateo_3]" value="<?php echo cv('prompt.sections.regateo_3','si buscas mas barato no soy yo, suerte 😘'); ?>" placeholder="Respuesta final"></div>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:6px">💾 Guardar anti-regateo</button>
                </div>
            </details>

            <!-- Ubicación (A13: simplificado) -->
            <details class="prompt-details">
                <summary class="prompt-summary">📍 Ubicación</summary>
                <div style="padding:12px;border-top:1px solid var(--border)">
                <div class="form-row">
                    <div class="form-group" style="flex:2">
                        <label>Zona / ciudad</label>
                        <input type="text" name="prompt[sections][zona]" value="<?php echo cv('prompt.sections.zona'); ?>" placeholder="Ej: Madrid centro, zona tranquila" oninput="buildPreview()">
                    </div>
                    <div class="form-group" style="flex:1">
                        <label>Enlace Google Maps</label>
                        <input type="url" name="urls[google_maps_location]" value="<?php echo cv('urls.google_maps_location'); ?>" placeholder="https://maps.app.goo.gl/... (pega aquí tu enlace de Google Maps)">
                    </div>
                </div>
                <label class="checkbox-label" style="margin-top:8px">
                    <input type="hidden" name="prompt[sections][maps_solo_chica]" value="0">
                    <input type="checkbox" name="prompt[sections][maps_solo_chica]" value="1" <?php echo checked((bool)$config->get('prompt.sections.maps_solo_chica',true)); ?>> Enviar ubicación solo cuando el cliente haya elegido chica
                </label>
                <small style="color:var(--text-muted);font-size:.72rem;display:block;margin-top:4px">
                    Todas las chicas comparten la MISMA casa (no cada una tiene su piso). 
                    El bot no dará la dirección real hasta que el cliente elija a una chica concreta o insista mucho de verdad. 
                    Mientras tanto solo dirá la zona (ej: "Madrid centro"). Así cuando llegue a la casa, ya sabes a qué chica viene. 
                    Ideal si no trabajas con presentación de chicas en persona.
                </small>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:8px">💾 Guardar ubicación</button>
                </div>
            </details>

            <!-- Servicios (A11: accordion + bigger) -->
            <details class="prompt-details">
                <summary class="prompt-summary">🛏️ Servicios</summary>
                <div style="padding:12px;border-top:1px solid var(--border)">
                <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:6px">Describe los servicios disponibles. El bot usará esta información cuando le pregunten.</p>
                <textarea name="prompt[sections][servicios]" class="code-area" style="width:100%;min-height:150px" spellcheck="false" oninput="buildPreview()"><?php echo cv('prompt.sections.servicios'); ?></textarea>
                <div style="margin-top:6px;display:flex;gap:6px">
                    <button type="submit" class="btn btn-primary btn-sm">💾 Guardar servicios</button>
                    <button type="button" class="btn btn-sm" style="background:var(--input-bg);color:var(--text-muted)"
                        onclick="if(confirm('¿Restaurar servicios por defecto?'))resetField('prompt\\[sections\\]\\[servicios\\]','Servicio completo con preservativo.\\nFrancés natural solo en tarifa de 1h si el cliente lo pide.\\nGriego solo si el cliente pregunta expresamente.\\nNo salidas a domicilio.')">🔄 Restaurar</button>
                </div>
                </div>
            </details>

            <!-- Ofertas (A14: simplificado) -->
            <details class="prompt-details">
                <summary class="prompt-summary">🎁 Ofertas especiales (opcional)</summary>
                <div style="padding:12px;border-top:1px solid var(--border)">
                <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:6px">Describe ofertas o promociones temporales. El bot las mencionará cuando sea relevante para la conversación.</p>
                <textarea name="prompt[sections][ofertas]" class="code-area" style="width:100%;min-height:100px" spellcheck="false" oninput="buildPreview()"><?php echo cv('prompt.sections.ofertas'); ?></textarea>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:6px">💾 Guardar ofertas</button>
                </div>
            </details>
        </div>

        <!-- Preview column (friendly summary) -->
        <div class="prompt-preview-col">
            <div class="card prompt-preview-card">
                <h2>🧠 Configuración actual</h2>
                <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:8px">
                    Resumen de cómo está configurado tu bot ahora mismo.
                </p>
                <div id="prompt-summary" style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px 10px;font-size:.82rem;line-height:1.5;color:var(--text);max-height:60vh;overflow-y:auto">
                    <div style="color:var(--text-muted);text-align:center;padding:12px 0;font-size:.8rem">⏳ Cargando resumen...</div>
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
        <div class="section-guide">
            📱 <strong>Cada línea es un número de WhatsApp que conectas al bot.</strong> Cuando alguien 
            escriba a ese número, el bot contestará automáticamente. Puedes tener varios números 
            —por ejemplo, uno distinto para cada zona o anuncio— y el bot los atenderá todos.
        </div>

        <!-- Add form -->
        <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:16px;margin-bottom:20px">
            <h3 style="margin-bottom:10px">➕ Añadir línea</h3>
            <p style="color:var(--text-muted);font-size:.75rem;margin-bottom:10px">Al crear una línea, recibirás un código QR. Escanéalo con tu móvil desde WhatsApp → Ajustes → Vincular dispositivo para que el bot pueda usar ese número.</p>
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
                    <button type="button" id="btn-create-line" class="btn btn-primary" onclick="addLine()" style="white-space:nowrap">Crear línea</button>
                </div>
            </div>
            <div id="add-line-status" style="margin-top:6px;font-size:.78rem;color:var(--text-muted)"></div>
        </div>

        <!-- Lines table -->
        <div id="lines-container">
            <p style="color:var(--text-muted);text-align:center;padding:20px">No hay líneas configuradas.</p>
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
                <input type="text" id="test-phone" placeholder="Ej: 666555444 (formato español)" style="width:100%;margin:12px 0">
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

        <div class="section-guide">
            👩 <strong>Tu catálogo de chicas.</strong> Añade su nombre, una breve descripción y hasta 
            4 fotos. Puedes activarlas o desactivarlas cuando quieras —las chicas inactivas no 
            aparecerán. El bot conoce los datos de cada una: cuando un cliente pregunta por una 
            chica en concreto, sabe qué decir y qué fotos enviar.
        </div>

        <!-- Add/edit form -->
        <div class="girl-form-card">
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
            </div>
            <!-- Photo upload -->
            <div style="margin-top:10px">
                <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
                    <div style="flex:1;min-width:200px">
                        <label style="font-size:.78rem;color:var(--text-muted)">Añadir fotos (JPG, PNG, WebP — máx 5MB, 4 máximo)</label>
                        <input type="file" id="girl-photo-file" accept="image/jpeg,image/png,image/webp" onchange="handlePhotoSelect(this)" multiple style="width:100%">
                    </div>
                    <div>
                        <button type="button" class="btn btn-primary" onclick="saveGirl()" id="btn-guardar-chica">💾 Guardar</button>
                    </div>
                </div>
                <div id="photo-previews" class="photo-previews"></div>
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
            <!-- ON/OFF + Save -->
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap">
                <label class="checkbox-label" style="font-size:.9rem"><input type="checkbox" id="estados-enabled" onchange="saveEstadosConfig()"> Activar publicador de estados</label>
                <span style="flex:1"></span>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveEstadosConfig()">💾 Guardar configuración</button>
                <button type="button" class="btn btn-success btn-sm" onclick="publishEstado()">📢 Publicar ahora</button>
            </div>
            <!-- Settings row -->
            <div class="form-row">
                <div class="form-group">
                    <label>Frecuencia</label>
                    <select id="estados-freq-tipo" onchange="saveEstadosConfig()">
                        <option value="cada_x_horas">Cada X horas</option>
                        <option value="x_veces_al_dia">X veces al día</option>
                    </select>
                </div>
                <div class="form-group" style="max-width:70px">
                    <label>Cada</label>
                    <input type="number" id="estados-freq-valor" value="6" min="1" max="24" onchange="saveEstadosConfig()">
                </div>
                <div class="form-group">
                    <label>Formato</label>
                    <select id="estados-formato" onchange="saveEstadosConfig()">
                        <option value="mix_aleatorio">🎲 Aleatorio (recomendado)</option>
                        <option value="chicas_de_hoy">Todas las chicas, 1 foto</option>
                        <option value="chica_del_dia">1 chica aleatoria, 2 fotos</option>
                        <option value="duo_sexy">2 chicas, 1 foto c/u</option>
                        <option value="catalogo_rapido">Solo nombres</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Horario inicio</label><input type="time" id="estados-hora-inicio" value="08:00" onchange="saveEstadosConfig()"></div>
                <div class="form-group"><label>Horario fin</label><input type="time" id="estados-hora-fin" value="23:00" onchange="saveEstadosConfig()"></div>
            </div>
            <div id="estados-lines-checkboxes" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px"></div>
            <div id="estados-status" style="margin-top:8px;font-size:.82rem;color:var(--text-muted)"></div>
        </div>

        <!-- History -->
        <h3>📋 Historial de publicaciones</h3>
        <div id="estados-history" style="margin-top:8px">
            <p style="color:var(--text-muted);text-align:center;padding:10px">No hay publicaciones todavía.</p>
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
            👥 <strong>El bot te avisa cuando detecta un cliente en camino.</strong> 
            Cuando alguien pregunta tarifas, ubicación y da una hora aproximada, el bot 
            lo marca como lead y te notifica al instante para que estés preparado.
            Tú eliges dónde recibir esos avisos: por <strong>Telegram</strong> (recomendado) 
            o por <strong>WhatsApp</strong>. Configúralo aquí abajo y activa las alertas.
        </div>

        <!-- Telegram config -->
        <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:12px;margin-bottom:16px">
            <details open>
                <summary style="padding:0 0 8px 0;cursor:pointer;font-weight:600;font-size:.84rem;color:var(--text)">📱 Configurar avisos (Telegram o WhatsApp)</summary>
                <div style="padding-top:8px;font-size:.78rem;color:var(--text-muted)">
                    <strong>Telegram (recomendado):</strong><br>
                    1. Abre Telegram, busca @BotFather, crea un bot con /newbot y copia el token<br>
                    2. Busca @userinfobot para obtener tu Chat ID personal<br>
                    3. Pega tu Chat ID abajo (uno por línea si tienes varios)<br>
                    4. Activa las alertas con el checkbox<br><br>
                    <strong>WhatsApp:</strong><br>
                    Puedes poner tu número personal para recibir avisos por WhatsApp. 
                    <strong>IMPORTANTE:</strong> El número que pongas aquí NO puede ser uno de los 
                    que tengas configurados como línea del bot en 📱 Líneas. Usa tu número personal.
                </div>
            </details>
            <div style="border-top:1px solid var(--border);margin-top:8px;padding-top:10px">
            <div class="form-row">
                <div class="form-group" style="flex:2">
                    <label>Chat IDs de Telegram (uno por línea)</label>
                    <textarea name="telegram[chat_ids]" rows="2" class="code-area" spellcheck="false"><?php echo h(implode("\n", cva('telegram.chat_ids'))); ?></textarea>
                </div>
                <div class="form-group" style="flex:1">
                    <label>Teléfonos WhatsApp para avisos (uno por línea)</label>
                    <textarea name="telegram[whatsapp_phones]" rows="2" class="code-area" spellcheck="false" placeholder="34600111222"><?php echo h(implode("\n", cva('telegram.whatsapp_phones'))); ?></textarea>
                    <small style="color:var(--warn);font-size:.7rem">⚠️ No pueden ser números que tengas como líneas del bot.</small>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end">
                    <label class="checkbox-label"><input type="hidden" name="telegram[alert_enabled]" value="0"><input type="checkbox" name="telegram[alert_enabled]" value="1" <?php echo checked((bool)$config->get('telegram.alert_enabled',false)); ?>> Alertas activadas</label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">💾 Guardar avisos</button>
            </div>
        </div>

        <!-- Leads table -->
        <div id="clientes-table-container">
            <p style="color:var(--text-muted);text-align:center;padding:20px">No hay leads registrados todavía.</p>
        </div>
    </div>
</div>

<!-- ===== TAB: Mensajes (WhatsApp-style Chat) ===== -->
<div class="tab-content" id="tab-mensajes"></div>

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
                    Estos tiempos hacen que el bot parezca humano al responder. Simula que "lee", "piensa" y "escribe".
                    Valores bajos = parece artificial (responde al instante). Valores altos = cliente espera demasiado.
                </p>
                <div class="form-row">
                    <div class="form-group"><label>Espera antes de "ver" mensaje (seg) <span style="color:var(--ok)">Recomendado: 1</span></label><input type="number" step="0.1" name="human_delays[seen][fallback_sec]" value="<?php echo cv('human_delays.seen.fallback_sec','1'); ?>"></div>
                    <div class="form-group"><label>Espera aleatoria mínima "visto" (seg) <span style="color:var(--ok)">Recomendado: 1</span></label><input type="number" step="0.1" name="human_delays[seen][random_min_sec]" value="<?php echo cv('human_delays.seen.random_min_sec','1'); ?>"></div>
                    <div class="form-group"><label>Espera aleatoria máxima "visto" (seg) <span style="color:var(--ok)">Recomendado: 3</span></label><input type="number" step="0.1" name="human_delays[seen][random_max_sec]" value="<?php echo cv('human_delays.seen.random_max_sec','3'); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Tiempo base "escribiendo" (seg) <span style="color:var(--ok)">Recomendado: 4</span></label><input type="number" step="0.1" name="human_delays[typing][fallback_sec]" value="<?php echo cv('human_delays.typing.fallback_sec','4'); ?>"></div>
                    <div class="form-group"><label>Caracteres/segundo mín <span style="color:var(--ok)">Recomendado: 38</span></label><input type="number" name="human_delays[typing][chars_per_sec_min]" value="<?php echo cv('human_delays.typing.chars_per_sec_min','38'); ?>"></div>
                    <div class="form-group"><label>Caracteres/segundo máx <span style="color:var(--ok)">Recomendado: 85</span></label><input type="number" name="human_delays[typing][chars_per_sec_max]" value="<?php echo cv('human_delays.typing.chars_per_sec_max','85'); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Tiempo lectura mín (ms) <span style="color:var(--ok)">Recomendado: 900</span></label><input type="number" name="human_delays[read][base_min_ms]" value="<?php echo cv('human_delays.read.base_min_ms','900'); ?>"></div>
                    <div class="form-group"><label>Tiempo lectura máx (ms) <span style="color:var(--ok)">Recomendado: 2200</span></label><input type="number" name="human_delays[read][base_max_ms]" value="<?php echo cv('human_delays.read.base_max_ms','2200'); ?>"></div>
                    <div class="form-group"><label>Espera entre mensajes (seg) <span style="color:var(--ok)">Recomendado: 15</span></label><input type="number" step="0.1" name="human_delays[presend_sleep_sec]" value="<?php echo cv('human_delays.presend_sleep_sec','15'); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Habituación inicio <span style="color:var(--ok)">Recomendado: 6.2</span></label><input type="number" step="0.1" name="human_delays[habituation][start_boost]" value="<?php echo cv('human_delays.habituation.start_boost','6.2'); ?>"></div>
                    <div class="form-group"><label>Habituación decaimiento <span style="color:var(--ok)">Recomendado: 0.92</span></label><input type="number" step="0.01" name="human_delays[habituation][decay]" value="<?php echo cv('human_delays.habituation.decay','0.92'); ?>"></div>
                    <div class="form-group"><label>Habituación suelo <span style="color:var(--ok)">Recomendado: 1.25</span></label><input type="number" step="0.01" name="human_delays[habituation][floor]" value="<?php echo cv('human_delays.habituation.floor','1.25'); ?>"></div>
                </div>
            </div>
        </details>

        <details style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:0;margin-bottom:8px">
            <summary style="padding:12px 16px;cursor:pointer;font-weight:600;font-size:.9rem">🎲 Variantes de mensajes</summary>
            <div style="padding:12px 16px;border-top:1px solid var(--border)">
                <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:6px">
                    Frases que el bot elige al azar cuando recibe un audio (una por línea).
                    <button type="button" class="btn btn-sm" style="margin-left:8px;background:var(--input-bg);color:var(--text-muted);font-size:.65rem"
                        onclick="if(confirm('¿Restaurar frases por defecto?'))this.parentElement.nextElementSibling.value='no puedo escuchar audios amor, me lo escribes mejor?\namor por aqui no escucho audios, escribeme y te digo 😘\ncari no puedo oir audios ahora, me lo pones en texto?\nme va mejor si me lo escribes amor, los audios no puedo escucharlos\nguapo no puedo reproducir audios, escribeme un momentito y te contesto\nay amor, sin audio mejor, escribeme y asi te respondo rapido\ncielo no escucho audios por aqui, me lo mandas escrito?\nno puedo con audios ahora cari, escribeme mejor'">🔄 Restaurar</button>
                </p>
                <textarea name="message_variants[audio_auto_reply]" rows="4" class="code-area" spellcheck="false"><?php
                    $audioVal = trim(cv('message_variants.audio_auto_reply'));
                    echo $audioVal !== '' ? $audioVal : "no puedo escuchar audios amor, me lo escribes mejor?\namor por aqui no escucho audios, escribeme y te digo 😘\ncari no puedo oir audios ahora, me lo pones en texto?\nme va mejor si me lo escribes amor, los audios no puedo escucharlos\nguapo no puedo reproducir audios, escribeme un momentito y te contesto\nay amor, sin audio mejor, escribeme y asi te respondo rapido\ncielo no escucho audios por aqui, me lo mandas escrito?\nno puedo con audios ahora cari, escribeme mejor";
                ?></textarea>
                <p style="color:var(--text-muted);font-size:.78rem;margin:10px 0 6px">
                    Variantes para pedir la hora de llegada (ETA). El bot las rota.
                    <button type="button" class="btn btn-sm" style="margin-left:8px;background:var(--input-bg);color:var(--text-muted);font-size:.65rem"
                        onclick="if(confirm('¿Restaurar frases por defecto?'))this.parentElement.nextElementSibling.value='cuanto tardas amor?\navisame cuando salgas\nen cuantos min vienes?\ncuando llegas papi?\nme dices cuanto tardas?\nsal y avisame que te espero'">🔄 Restaurar</button>
                </p>
                <textarea name="message_variants[eta_request_variants]" rows="3" class="code-area" spellcheck="false"><?php
                    $etaVal = trim(cv('message_variants.eta_request_variants'));
                    echo $etaVal !== '' ? $etaVal : "cuanto tardas amor?\navisame cuando salgas\nen cuantos min vienes?\ncuando llegas papi?\nme dices cuanto tardas?\nsal y avisame que te espero";
                ?></textarea>
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
                    <strong>¿Qué hace?</strong> Si un cliente dice "llego en 20 minutos", el bot le enviará UN SOLO recordatorio pasado ese tiempo.
                </p>
                <div class="alert-warning" style="margin-bottom:10px;font-size:.85rem;padding:12px 14px;border-radius:8px;border:2px solid var(--warn);background:rgba(251,191,36,.12)">
                    ⚠️ <strong style="font-size:.95rem">IMPORTANTE:</strong> Este recordatorio se envía automáticamente aunque no hayas marcado el lead como "llegó" en la pestaña Clientes. El bot se basa solo en lo que el cliente dijo.
                </div>
                <div class="form-row">
                    <div class="form-group"><label class="checkbox-label"><input type="hidden" name="cron[reminder][enabled]" value="0"><input type="checkbox" name="cron[reminder][enabled]" value="1" <?php echo checked((bool)$config->get('cron.reminder.enabled',false)); ?>> Activado</label></div>
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
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:16px">
            Consulta el rendimiento de tu bot con datos reales: cuántas conversaciones ha mantenido, 
            cuántos leads ha generado, cuántos clientes han llegado y qué ratio de conversión tienes. 
            Entender estas cifras te ayuda a saber si tu inversión funciona y dónde puedes mejorar.
        </p>
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
// Global API token for all AJAX calls (works even without session cookie)
var _apiToken = <?php echo json_encode(generateCsrfToken()); ?>;
// Helper: append token to any API URL
function apiUrl(url) {
    return url + (url.indexOf('?') === -1 ? '?' : '&') + 'token=' + encodeURIComponent(_apiToken);
}
</script>
<script src="assets/chat.js?v=20260610_1"></script>
<script>
var _csrf = <?php echo json_encode(generateCsrfToken()); ?>;
var _defaultTarifas = <?php echo json_encode($distTarifas); ?>;

// ── Preview builder ──
function buildPreview() {
    var container = document.getElementById('prompt-summary');
    if (!container) return;

    var el = function(sel) { return document.querySelector(sel); };

    var estiloEl  = el('select[name="prompt[sections][estilo_tipo]"]');
    var speakerEl = el('select[name="prompt[sections][speaker_mode]"]');
    var emojiEl   = el('select[name="prompt[sections][emoji_level]"]');
    var lengthEl  = el('select[name="prompt[sections][reply_length]"]');
    var tarifasEl = el('textarea[name="prompt[sections][tarifas]"]');
    var zonaEl    = el('input[name="prompt[sections][zona]"]');
    var serviciosEl = el('textarea[name="prompt[sections][servicios]"]');
    var ofertasEl = el('textarea[name="prompt[sections][ofertas]"]');
    var regateoEl = el('input[name="prompt[sections][no_regateo]"]');

    var estilo  = estiloEl  ? estiloEl.options[estiloEl.selectedIndex].text   : '?';
    var speaker = speakerEl ? speakerEl.options[speakerEl.selectedIndex].text : '?';
    var emoji   = emojiEl   ? emojiEl.options[emojiEl.selectedIndex].text     : '?';
    var length  = lengthEl  ? lengthEl.options[lengthEl.selectedIndex].text   : '?';

    var tarifasVal   = tarifasEl   ? tarifasEl.value.trim()   : '';
    var zonaVal      = zonaEl      ? zonaEl.value.trim()      : '';
    var serviciosVal = serviciosEl ? serviciosEl.value.trim() : '';
    var ofertasVal   = ofertasEl   ? ofertasEl.value.trim()   : '';
    var noRegateo    = regateoEl   ? regateoEl.checked        : false;

    var tarifasOk   = tarifasVal.length > 20 && tarifasVal.trim() !== _defaultTarifas.trim();
    var zonaOk      = zonaVal.length > 2;
    var serviciosOk = serviciosVal.length > 10;
    var ofertasOk   = ofertasVal.length > 5;

    function row(icon, label, value, ok, extra) {
        var color = ok ? 'var(--ok)' : 'var(--warn)';
        var statusIcon = ok ? '✅' : '⚠️';
        var bg = ok ? 'rgba(45,212,191,.04)' : 'rgba(251,191,36,.04)';
        var extraHtml = extra ? '<span style="display:block;font-size:.7rem;color:var(--text-muted);margin-top:1px">' + escHtml(extra) + '</span>' : '';
        return '<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin-bottom:5px;background:'+bg+';border-radius:6px;border-left:3px solid '+color+'">' +
            '<span style="font-size:1.1rem;flex-shrink:0;width:28px;text-align:center">' + icon + '</span>' +
            '<div style="flex:1;min-width:0">' +
                '<span style="font-weight:600;font-size:.81rem">' + escHtml(label) + '</span>' +
                '<div style="font-size:.76rem;color:'+color+';margin-top:1px">' + value + '</div>' +
                extraHtml +
            '</div>' +
            '<span style="font-size:.85rem;flex-shrink:0;color:'+color+'">' + statusIcon + '</span>' +
        '</div>';
    }

    var rows = [];
    rows.push(row('🎨', 'Estilo', estilo, true));
    rows.push(row('🗣', 'Modo', speaker, true));
    rows.push(row('😊', 'Emojis', emoji, true));
    rows.push(row('📏', 'Longitud', length, true));
    rows.push(row('💰', 'Tarifas', tarifasOk ? 'Configuradas' : 'Sin configurar', tarifasOk));

    if (zonaOk) {
        rows.push(row('📍', 'Ubicación', zonaVal, true));
    } else {
        rows.push(row('📍', 'Ubicación', 'Sin configurar', false));
    }

    rows.push(row('🛏️', 'Servicios', serviciosOk ? 'Configurados' : 'Sin configurar', serviciosOk));
    rows.push(row('🎁', 'Ofertas', ofertasOk ? 'Configuradas' : 'Sin configurar', ofertasOk));

    var regateoText = noRegateo ? 'No se acepta regateo' : 'Se permite negociar';
    rows.push(row('🛡️', 'Regateo', regateoText, true));

    container.innerHTML = rows.join('');
}

// ── Líneas WhatsApp ──
function loadLines() {
    fetch(apiUrl('api/lines.php?action=list')).then(r=>r.json()).then(d=>{
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
                if (st === 'WORKING') {
                    html += '<span style="color:var(--ok);font-size:.8rem;margin-right:6px">✅ Conectada</span>';
                } else {
                    html += '<button type="button" onclick="showQR('+l.id+')" class="btn btn-sm btn-primary" style="margin-right:3px">QR</button>';
                }
                html += '<button type="button" onclick="showTest('+l.id+')" class="btn btn-sm" style="background:var(--info);color:#fff;margin-right:3px">Test</button>';
                if (st === 'WORKING') {
                    html += '<button type="button" onclick="checkLineStatus('+l.id+')" class="btn btn-sm" style="background:var(--ok);color:#fff;margin-right:3px">✓</button>';
                }
                html += '<button type="button" onclick="deleteLine('+l.id+')" class="btn btn-sm btn-danger">🗑</button>';
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
    var btn = document.getElementById('btn-create-line');
    btn.disabled = true; btn.textContent = '⏳ Creando...';
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
        btn.disabled = false; btn.textContent = 'Crear línea';
    }).catch(function(){
        statusEl.textContent = '❌ Error de conexión';
        btn.disabled = false; btn.textContent = 'Crear línea';
    });
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
    fetch(apiUrl('api/lines.php?action=qr&line_id='+lineId)).then(r=>r.json()).then(d=>{
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
    fetchQR(currentQrLineId);
}
function checkLineStatus(lineId) {
    fetch(apiUrl('api/lines.php?action=status')).then(r=>r.json()).then(d=>{
        if (d.ok && d.statuses) {
            var st = d.statuses[lineId] || 'unknown';
            alert('Estado de la línea: ' + st);
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
        if (d.ok) {
            document.getElementById('test-result').textContent = '✅ Mensaje enviado (HTTP '+d.http_code+')';
        } else {
            var err = d.error || ('HTTP '+d.http_code);
            document.getElementById('test-result').textContent = '❌ '+err;
        }
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
        fetch(apiUrl('api/lines.php?action=status')).then(r=>r.json()).then(d=>{
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
var _allGirls = [];
function loadGirls() {
    var container = document.getElementById('girls-container');
    if (!container) return;
    container.innerHTML = '<p style="color:var(--text-muted);text-align:center;padding:20px">Cargando chicas...</p>';
    fetch(apiUrl('api/girls.php?action=list')).then(r=>r.json()).then(d=>{
        if (!d.ok) { container.innerHTML = '<p style="color:var(--danger);text-align:center;padding:20px">Error al cargar chicas.</p>'; return; }
        _allGirls = d.girls;
        var html = '';
        if (d.girls.length === 0) {
            html = '<p style="color:var(--text-muted);text-align:center;padding:20px">No hay chicas configuradas. Añade la primera arriba.</p>';
        } else {
            html = '<div class="girls-grid">';
            d.girls.forEach(function(g) {
                var fotoCount = (g.fotos||[]).length;
                var heroImg = (g.fotos||[]).length > 0 ? g.fotos[0] : '';
                var heroStyle = heroImg ? 'background-image:url('+escHtml(heroImg)+');background-size:cover;background-position:center top' : '';
                var activeColor = g.activa ? 'var(--ok)' : 'var(--text-muted)';
                html += '<div class="girl-card'+(g.activa?' girl-card--active':'')+'">';
                // Hero image
                if (heroImg) {
                    html += '<div class="girl-card-hero" style="'+heroStyle+'">';
                    html += '<span class="girl-badge '+(g.activa?'girl-badge--on':'girl-badge--off')+'">'+(g.activa?'Activa':'Inactiva')+'</span>';
                    html += '</div>';
                }
                html += '<div class="girl-card-body">';
                html += '<div class="girl-card-header">';
                html += '<strong class="girl-card-name">'+escHtml(g.nombre)+'</strong>';
                if (!heroImg) {
                    html += '<span class="girl-badge '+(g.activa?'girl-badge--on':'girl-badge--off')+'">'+(g.activa?'Activa':'Inactiva')+'</span>';
                }
                html += '</div>';
                html += '<div class="girl-card-desc">'+escHtml(g.descripcion_corta||'Sin descripción')+'</div>';
                html += '<div class="girl-card-meta">';
                html += '<span class="girl-photo-count">📸 '+(fotoCount)+'/4 fotos</span>';
                html += '</div>';
                html += '<div class="girl-card-actions">';
                html += '<button type="button" onclick="editGirl(\''+escHtml(g.id)+'\',\''+escHtml(g.nombre)+'\',\''+escHtml(g.descripcion_corta||'')+'\')" class="btn btn-sm btn-warning" title="Editar">✏️</button>';
                html += '<button type="button" onclick="toggleGirl(\''+escHtml(g.id)+'\')" class="btn btn-sm '+(g.activa?'btn-toggle-on':'btn-toggle-off')+'" title="'+(g.activa?'Desactivar':'Activar')+'">'+(g.activa?'🟢':'🔴')+'</button>';
                html += '<button type="button" onclick="deleteGirl(\''+escHtml(g.id)+'\')" class="btn btn-sm btn-danger" title="Eliminar">🗑</button>';
                html += '</div></div></div>';
            });
            html += '</div>';
        }
        container.innerHTML = html;
    }).catch(function(e){
        container.innerHTML = '<p style="color:var(--danger);text-align:center;padding:20px">Error de conexión al cargar chicas.</p>';
    });
}
function saveGirl() {
    var id = document.getElementById('girl-edit-id').value;
    var nombre = document.getElementById('girl-nombre').value.trim();
    if (!nombre) return alert('El nombre es obligatorio.');
    var btn = document.getElementById('btn-guardar-chica');
    if (btn) { btn.disabled = true; btn.textContent = '⏳ Guardando...'; }
    var fd = new FormData();
    if (id) fd.append('id', id);
    fd.append('nombre', nombre);
    fd.append('descripcion', document.getElementById('girl-desc').value.trim());
    // Solo enviar activa al crear (nuevas chicas arrancan activas). Al editar se respeta el valor actual.
    if (!id) fd.append('activa', '1');
    fd.append('csrf_token', (typeof _csrf!=='undefined'?_csrf:''));
    // Append selected photos
    for (var i = 0; i < selectedPhotos.length; i++) {
        fd.append('photos[]', selectedPhotos[i]);
    }
    fetch('api/girls.php?action=save', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) {
            document.getElementById('girl-edit-id').value='';
            document.getElementById('girl-nombre').value='';
            document.getElementById('girl-desc').value='';
            document.getElementById('girl-form-title').textContent='➕ Nueva chica';
            clearPhotoPreviews();
            loadGirls();
        } else { alert('Error: '+(d.error||'Desconocido')); }
        if (btn) { btn.disabled = false; btn.textContent = '💾 Guardar'; }
    }).catch(function(){
        alert('Error de conexión al guardar.');
        if (btn) { btn.disabled = false; btn.textContent = '💾 Guardar'; }
    });
}
function editGirl(id, nombre, desc) {
    clearPhotoPreviews();
    document.getElementById('girl-edit-id').value = id;
    document.getElementById('girl-nombre').value = nombre;
    document.getElementById('girl-desc').value = desc;
    document.getElementById('girl-form-title').textContent = '✏️ Editar chica';
    // Scroll to the form so the user sees it
    document.getElementById('girl-nombre').focus();
    document.getElementById('girl-form-title').scrollIntoView({behavior:'smooth',block:'start'});
    // Show existing photos with delete buttons
    var girl = _allGirls.find(function(g) { return g.id === id; });
    if (girl && girl.fotos && girl.fotos.length > 0) {
        renderExistingPhotos(girl.fotos, id);
    } else {
        var previewContainer = document.getElementById('photo-previews');
        if (previewContainer) {
            previewContainer.innerHTML = '<span style="font-size:.75rem;color:var(--text-muted);padding:8px">📸 Sin fotos todavía. Añade hasta 4 en total.</span>';
        }
    }
}

// ── Render existing photos in the edit form with drag-and-drop ──
function renderExistingPhotos(photos, girlId) {
    var container = document.getElementById('photo-previews');
    if (!container) return;
    container.innerHTML = '';
    // Title with instructions
    var title = document.createElement('div');
    title.style.cssText = 'width:100%;font-size:.75rem;color:var(--text-muted);padding:0 0 6px 0';
    title.innerHTML = '📸 Fotos ('+photos.length+'/4) — arrastra para reordenar · <span style="color:var(--accent);font-weight:600">⭐</span> = principal';
    container.appendChild(title);
    // Thumbnails
    photos.forEach(function(url, idx) {
        var wrapper = document.createElement('div');
        wrapper.className = 'photo-preview-item' + (idx === 0 ? ' primary' : '');
        wrapper.setAttribute('data-photo-idx', idx);
        wrapper.setAttribute('data-girl-id', girlId);
        wrapper.setAttribute('draggable', 'true');
        // Drag events
        wrapper.addEventListener('dragstart', handlePhotoDragStart);
        wrapper.addEventListener('dragend', handlePhotoDragEnd);
        wrapper.addEventListener('dragover', handlePhotoDragOver);
        wrapper.addEventListener('dragleave', handlePhotoDragLeave);
        wrapper.addEventListener('drop', handlePhotoDrop);
        // Inner HTML: star badge, image, delete button
        wrapper.innerHTML =
            (idx === 0
                ? '<span class="primary-star" title="Foto principal">⭐</span>'
                : '<span class="set-primary-btn" onclick="event.stopPropagation();setAsPrimary(\''+escHtml(girlId)+'\','+idx+')" title="Marcar como principal">☆</span>') +
            '<img src="'+url+'" style="object-fit:cover;width:100%;height:100%" draggable="false">' +
            '<button type="button" onclick="event.stopPropagation();removeExistingPhoto(\''+escHtml(girlId)+'\','+idx+')" title="Eliminar esta foto">✕</button>';
        container.appendChild(wrapper);
    });
}

// ── Drag & drop: reorder photos ──
var _dragSrcIdx = null;

function handlePhotoDragStart(e) {
    _dragSrcIdx = parseInt(this.getAttribute('data-photo-idx'));
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', String(_dragSrcIdx));
    this.classList.add('dragging');
}
function handlePhotoDragEnd(e) {
    this.classList.remove('dragging');
    document.querySelectorAll('.photo-preview-item.drag-over').forEach(function(el) { el.classList.remove('drag-over'); });
    _dragSrcIdx = null;
}
function handlePhotoDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
    this.classList.add('drag-over');
}
function handlePhotoDragLeave(e) {
    this.classList.remove('drag-over');
}
function handlePhotoDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    this.classList.remove('drag-over');
    var targetIdx = parseInt(this.getAttribute('data-photo-idx'));
    if (_dragSrcIdx === null || _dragSrcIdx === targetIdx) return;
    var girlId = this.getAttribute('data-girl-id');
    var girl = _allGirls.find(function(g) { return g.id === girlId; });
    if (!girl || !girl.fotos) return;
    // Reorder array
    var fotos = girl.fotos.slice();
    var moved = fotos.splice(_dragSrcIdx, 1)[0];
    fotos.splice(targetIdx, 0, moved);
    girl.fotos = fotos;
    // Re-render
    renderExistingPhotos(fotos, girlId);
    // Save to server
    savePhotoOrder(girlId, fotos);
}

// ── Set a photo as primary (move to index 0) ──
function setAsPrimary(girlId, idx) {
    if (idx === 0) return;
    var girl = _allGirls.find(function(g) { return g.id === girlId; });
    if (!girl || !girl.fotos) return;
    var fotos = girl.fotos.slice();
    var moved = fotos.splice(idx, 1)[0];
    fotos.unshift(moved);
    girl.fotos = fotos;
    renderExistingPhotos(fotos, girlId);
    savePhotoOrder(girlId, fotos);
}

// ── Persist photo order to server ──
function savePhotoOrder(girlId, fotos) {
    var fd = new FormData();
    fd.append('id', girlId);
    fd.append('order', JSON.stringify(fotos));
    fd.append('csrf_token', (typeof _csrf !== 'undefined' ? _csrf : ''));
    fetch('api/girls.php?action=reorder_photos', { method: 'POST', body: fd }).then(function(r) { return r.json(); }).then(function(d) {
        if (!d.ok) {
            // Reload to recover correct state on error
            loadGirls();
            // Refresh edit form if still editing
            var currentEditId = document.getElementById('girl-edit-id').value;
            if (currentEditId) {
                return fetch(apiUrl('api/girls.php?action=list')).then(function(r) { return r.json(); }).then(function(data) {
                    if (data && data.ok) {
                        _allGirls = data.girls;
                        var girl = _allGirls.find(function(g) { return g.id === currentEditId; });
                        if (girl) renderExistingPhotos(girl.fotos || [], currentEditId);
                    }
                });
            }
        }
    }).catch(function() {});
}

// ── Remove an existing photo from a girl (server-side) ──
function removeExistingPhoto(girlId, photoIndex) {
    if (!confirm('¿Eliminar esta foto?')) return;
    var fd = new FormData();
    fd.append('id', girlId);
    fd.append('photo_index', photoIndex);
    fd.append('csrf_token', (typeof _csrf!=='undefined'?_csrf:''));
    fetch('api/girls.php?action=remove_photo', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (!d.ok) { alert('Error: '+(d.error||'No se pudo eliminar la foto')); return; }
        // Reload girls data and refresh both the card grid and the edit form
        return fetch(apiUrl('api/girls.php?action=list')).then(r=>r.json());
    }).then(function(d) {
        if (!d || !d.ok) return;
        _allGirls = d.girls;
        // Refresh the card grid
        loadGirls();
        // Refresh the edit form photos (if still editing)
        var currentEditId = document.getElementById('girl-edit-id').value;
        if (currentEditId) {
            var girl = _allGirls.find(function(g) { return g.id === currentEditId; });
            if (girl) {
                renderExistingPhotos(girl.fotos||[], currentEditId);
            }
        }
    }).catch(function(e){
        alert('Error de conexión al eliminar foto: '+(e.message||'sin conexión'));
    });
}

// ── Photo preview management ──
var selectedPhotos = [];
function handlePhotoSelect(input) {
    var files = input.files;
    var container = document.getElementById('photo-previews');
    if (!container) return;
    // In edit mode, keep existing photo thumbnails. Only clear new-photo previews.
    var isEditMode = document.getElementById('girl-edit-id').value !== '';
    if (!isEditMode) {
        container.innerHTML = '';
    } else {
        // Remove only the "new photo" preview items (marked with data-idx), keep existing ones
        container.querySelectorAll('.photo-preview-item:not([data-photo-idx])').forEach(function(el) { el.remove(); });
    }
    for (var n = 0; n < files.length; n++) {
        if (selectedPhotos.length >= 4) { alert('Máximo 4 fotos.'); break; }
        var file = files[n];
        if (file.size > 5*1024*1024) { alert(file.name + ' supera 5MB.'); continue; }
        if (!file.type.match(/^image\/(jpeg|png|webp)$/)) { alert(file.name + ' formato no válido.'); continue; }
        selectedPhotos.push(file);
        var url = URL.createObjectURL(file);
        var idx = selectedPhotos.length - 1;
        var wrapper = document.createElement('div');
        wrapper.className = 'photo-preview-item';
        wrapper.setAttribute('data-idx', idx);
        wrapper.innerHTML = '<img src="'+url+'" onload="URL.revokeObjectURL(this.src)"><button type="button" onclick="removePreviewPhoto('+idx+')">✕</button>';
        container.appendChild(wrapper);
    }
    input.value = '';
    if (selectedPhotos.length >= 4) { input.style.display = 'none'; }
}
function removePreviewPhoto(index) {
    selectedPhotos.splice(index, 1);
    var container = document.getElementById('photo-previews');
    if (!container) return;
    // Remove only the "new photo" preview items (not existing photos with data-photo-idx)
    container.querySelectorAll('.photo-preview-item:not([data-photo-idx])').forEach(function(el) { el.remove(); });
    // Rebuild new-photo previews with correct indices
    selectedPhotos.forEach(function(file, i) {
        var url = URL.createObjectURL(file);
        var wrapper = document.createElement('div');
        wrapper.className = 'photo-preview-item';
        wrapper.setAttribute('data-idx', i);
        wrapper.innerHTML = '<img src="'+url+'" onload="URL.revokeObjectURL(this.src)"><button type="button" onclick="removePreviewPhoto('+i+')">✕</button>';
        container.appendChild(wrapper);
    });
    var fileInput = document.getElementById('girl-photo-file');
    if (fileInput) fileInput.style.display = '';
}
function clearPhotoPreviews() {
    selectedPhotos = [];
    var container = document.getElementById('photo-previews');
    if (container) container.innerHTML = '';
    var fileInput = document.getElementById('girl-photo-file');
    if (fileInput) { fileInput.style.display = ''; fileInput.value = ''; }
}
function toggleGirl(id) {
    var fd = new FormData();
    fd.append('id', id);
    fd.append('csrf_token', (typeof _csrf!=='undefined'?_csrf:''));
    fetch('api/girls.php?action=toggle', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) { loadGirls(); } else { alert('Error: '+(d.error||'Desconocido')); }
    }).catch(function(e){
        alert('Error de conexión al cambiar estado: '+(e.message||'sin conexión'));
    });
}
function deleteGirl(id) {
    if (!confirm('¿Eliminar esta chica? Esta acción no se puede deshacer.')) return;
    var fd = new FormData();
    fd.append('id', id);
    fd.append('csrf_token', (typeof _csrf!=='undefined'?_csrf:''));
    fetch('api/girls.php?action=delete', {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) { loadGirls(); } else { alert('Error: '+(d.error||'No se pudo eliminar')); }
    }).catch(function(e){
        alert('Error de conexión al eliminar: '+(e.message||'sin conexión'));
    });
}

// ── Estados ──
function loadEstadosConfig() {
    fetch(apiUrl('api/estados.php?action=config')).then(r=>r.json()).then(d=>{
        if (!d.ok) return;
        var c = d.config;
        document.getElementById('estados-enabled').checked = !!c.enabled;
        document.getElementById('estados-freq-tipo').value = c.frecuencia_tipo || 'cada_x_horas';
        document.getElementById('estados-freq-valor').value = c.frecuencia_valor || 6;
        document.getElementById('estados-formato').value = c.formato || 'mix_aleatorio';
        document.getElementById('estados-hora-inicio').value = c.hora_inicio || '08:00';
        document.getElementById('estados-hora-fin').value = c.hora_fin || '23:00';
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
    fetch(apiUrl('api/estados.php?action=config'), {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        document.getElementById('estados-status').textContent = d.ok ? '✅ Configuración guardada' : '❌ Error al guardar: '+(d.error||'desconocido');
        setTimeout(function(){ document.getElementById('estados-status').textContent = ''; }, 3000);
    }).catch(function(e){
        document.getElementById('estados-status').textContent = '❌ Error de red: '+(e.message||'sin conexión');
        setTimeout(function(){ document.getElementById('estados-status').textContent = ''; }, 4000);
    });
}
function publishEstado() {
    document.getElementById('estados-status').textContent = '⏳ Publicando...';
    var fd = new FormData(); fd.append('csrf_token', _csrf);
    fetch(apiUrl('api/estados.php?action=publish'), {method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if (d.ok) {
            var oks = d.results.filter(function(r){return r.ok;}).length;
            document.getElementById('estados-status').textContent = '✅ Publicado en '+oks+'/'+d.results.length+' líneas';
            loadEstadosHistory();
        } else {
            document.getElementById('estados-status').textContent = '❌ '+(d.error||'Error');
        }
        setTimeout(function(){ document.getElementById('estados-status').textContent = ''; }, 5000);
    }).catch(function(e){
        document.getElementById('estados-status').textContent = '❌ Error de red: '+(e.message||'sin conexión');
        setTimeout(function(){ document.getElementById('estados-status').textContent = ''; }, 4000);
    });
}
function loadEstadosHistory() {
    fetch(apiUrl('api/estados.php?action=history')).then(r=>r.json()).then(d=>{
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
    fetch(apiUrl('api/clientes.php?action=list')).then(r=>r.json()).then(d=>{
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
                    ? '<button type="button" onclick="markLeadArrived(\''+escHtml(l.thread_id)+'\',false)" class="btn btn-sm" style="background:var(--ok-bg);color:var(--ok)">✅ Sí</button>'
                    : '<button type="button" onclick="markLeadArrived(\''+escHtml(l.thread_id)+'\',true)" class="btn btn-sm btn-warning">Marcar llegó</button>';
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

// ── Mensajes (via ChatApp modal) ──
// The WhatsApp-style chat is loaded from chat.js / chat.css. 
// Clicking the "Abrir Chat" button calls ChatApp.open().
// No additional init needed here — ChatApp handles everything.

// ── Estadísticas ──
function loadEstadisticas() {
    fetch(apiUrl('api/stats.php')).then(r=>r.json()).then(d=>{
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
        document.getElementById('estadisticas-container').innerHTML = '<div style="text-align:center;padding:40px"><p style="color:var(--text-muted);font-size:1.1rem">📊 Sin datos todavía</p><p style="color:var(--text-muted);margin-top:4px">Las estadísticas aparecerán cuando el bot empiece a recibir mensajes.</p></div>';
    });
}

// ── Registro (Logs) ──
function loadRegistro() {
    fetch(apiUrl('api/logs.php')).then(r=>r.json()).then(d=>{
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
    'tab-mensajes': function() { if (typeof ChatApp !== 'undefined') { setTimeout(function() { ChatApp.open(); }, 50); } }, // ChatApp modal — direct open
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
</script>

<script>
// ── Tab switching (isolated — runs even if main JS has errors) ──
(function() {
    var btns = document.querySelectorAll('#tabNav button[data-tab]');
    var tabs = document.querySelectorAll('.tab-content');
    btns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            btns.forEach(function(b) { b.classList.remove('active'); });
            tabs.forEach(function(t) { t.classList.remove('active'); });
            btn.classList.add('active');
            var target = document.getElementById(btn.getAttribute('data-tab'));
            if (target) target.classList.add('active');
            var activeInput = document.querySelector('.js-active-tab-input');
            if (activeInput) activeInput.value = btn.getAttribute('data-tab');
            try { localStorage.setItem('botcasa_client_tab', btn.getAttribute('data-tab')); } catch(e) {}
        });
    });
    var stored = null;
    try { stored = localStorage.getItem('botcasa_client_tab'); } catch(e) {}
    if (stored) {
        var btn = document.querySelector('#tabNav button[data-tab="' + stored + '"]');
        if (btn) btn.click();
    }
})();
</script>
<script>
// ── Preview card (isolated — runs regardless of main JS errors) ──
(function() {
    function escHtml(s) { var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

    function buildPreview() {
        var container = document.getElementById('prompt-summary');
        if (!container) return;

        var el = function(sel) { return document.querySelector(sel); };

        var estiloEl  = el('select[name="prompt[sections][estilo_tipo]"]');
        var speakerEl = el('select[name="prompt[sections][speaker_mode]"]');
        var emojiEl   = el('select[name="prompt[sections][emoji_level]"]');
        var lengthEl  = el('select[name="prompt[sections][reply_length]"]');
        var tarifasEl = el('textarea[name="prompt[sections][tarifas]"]');
        var zonaEl    = el('input[name="prompt[sections][zona]"]');
        var serviciosEl = el('textarea[name="prompt[sections][servicios]"]');
        var ofertasEl = el('textarea[name="prompt[sections][ofertas]"]');
        var regateoEl = el('input[name="prompt[sections][no_regateo]"]');

        var estilo  = estiloEl  ? estiloEl.options[estiloEl.selectedIndex].text   : '?';
        var speaker = speakerEl ? speakerEl.options[speakerEl.selectedIndex].text : '?';
        var emoji   = emojiEl   ? emojiEl.options[emojiEl.selectedIndex].text     : '?';
        var length  = lengthEl  ? lengthEl.options[lengthEl.selectedIndex].text   : '?';

        var tarifasVal   = tarifasEl   ? tarifasEl.value.trim()   : '';
        var zonaVal      = zonaEl      ? zonaEl.value.trim()      : '';
        var serviciosVal = serviciosEl ? serviciosEl.value.trim() : '';
        var ofertasVal   = ofertasEl   ? ofertasEl.value.trim()   : '';
        var noRegateo    = regateoEl   ? regateoEl.checked        : false;

    var tarifasOk   = tarifasVal.length > 20 && tarifasVal.trim() !== _defaultTarifas.trim();
        var zonaOk      = zonaVal.length > 2;
        var serviciosOk = serviciosVal.length > 10;
        var ofertasOk   = ofertasVal.length > 5;

        function row(icon, label, value, ok, extra) {
            var color = ok ? 'var(--ok)' : 'var(--warn)';
            var icon2 = ok ? '✅' : '⚠️';
            var bg = ok ? 'rgba(45,212,191,.04)' : 'rgba(251,191,36,.04)';
            var extraHtml = extra ? '<span style="display:block;font-size:.7rem;color:var(--text-muted);margin-top:1px">'+escHtml(extra)+'</span>' : '';
            return '<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;margin-bottom:5px;background:'+bg+';border-radius:6px;border-left:3px solid '+color+'">'+
                '<span style="font-size:1.1rem;flex-shrink:0;width:28px;text-align:center">'+icon+'</span>'+
                '<div style="flex:1;min-width:0"><span style="font-weight:600;font-size:.81rem">'+escHtml(label)+'</span>'+
                '<div style="font-size:.76rem;color:'+color+';margin-top:1px">'+value+'</div>'+extraHtml+'</div>'+
                '<span style="font-size:.85rem;flex-shrink:0;color:'+color+'">'+icon2+'</span></div>';
        }

        var rows = [];
        rows.push(row('🎨','Estilo', estilo, true));
        rows.push(row('🗣','Modo', speaker, true));
        rows.push(row('😊','Emojis', emoji.split(' ').slice(1).join(' '), true));
        rows.push(row('📏','Longitud', length.split(' ').slice(1).join(' ').replace('(','').replace(')',''), true));
        rows.push(row('💰','Tarifas', tarifasOk ? 'Configuradas' : 'Sin configurar', tarifasOk, tarifasOk ? '' : 'Define tus precios'));
        rows.push(row('📍','Ubicación', zonaOk ? zonaVal.substring(0,40) : 'Sin configurar', zonaOk));
        rows.push(row('🛏️','Servicios', serviciosOk ? 'Configurados' : 'Sin configurar', serviciosOk));
        rows.push(row('🎁','Ofertas', ofertasOk ? 'Configuradas' : 'Sin configurar', ofertasOk));
        rows.push(row('🛡️','Regateo', noRegateo ? 'No acepta' : 'Permite negociar', true));

        container.innerHTML = rows.join('');
    }

    // Run on load
    setTimeout(buildPreview, 400);
    // Also re-run when any form element changes
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(buildPreview, 500);
    });
})();
</script>

</body>
</html>
