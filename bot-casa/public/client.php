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

// ── Security headers ──
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

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

// ── Subscription status ──
$subManager = new \WasapBot\Core\SubscriptionManager($um);
$subStatus = $subManager->getStatus($clientUserId);
$subExpired = $subStatus['isExpired'];
$subShowBanner = !in_array($subStatus['status'], ['unlimited', 'demo'], true);

$clientName = h((string) ($clientUser['name'] ?? $clientUser['username'] ?? 'Usuario'));

// ── Demo mode detection ──
$isDemo = (($_SESSION['username'] ?? '') === 'demo');

// ── Load user config ──
$configDir = \WasapBot\Bot::resolveUserConfigDir(WASAPBOT_ROOT, $clientUserId);
$config = new \WasapBot\Core\Config($configDir, WASAPBOT_ROOT);

// ── Override data paths for non-admin users (data isolation) ──
if ($clientUserId > 1) {
    $fileKeys = ['files.session_memory', 'files.leads', 'files.reminders', 'files.playbook', 'files.wa_raw_payload', 'files.bot_log', 'bot.mode_file'];
    foreach ($fileKeys as $key) {
        $val = $config->get($key, '');
        if (is_string($val) && $val !== '') {
            $config->set($key, \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $clientUserId, $val));
        }
    }
    // Note: Don't clear telegram/maps settings here — each user has their own
    // config.local.json in data/users/{id}/. Clearing them destroys saved user data
    // on every page load, which breaks the toggle_bot check and empties form fields.
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
    // Accept current slot + up to 5 previous 10-min windows (60 min total).
    // Uses time() + offset so hour-day boundaries are handled correctly.
    $now = time();
    for ($offset = 0; $offset <= 5; $offset++) {
        $t = $now - ($offset * 600);
        $expected = hash_hmac('sha256', $userId . '|' . date('Y-m-d-H', $t) . (int) floor((int) date('i', $t) / 10), $secret);
        if (hash_equals($expected, $token)) return true;
    }
    return false;
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
$linesForUser = \WasapBot\Core\Pricing::userLineCount($clientUserId, WASAPBOT_ROOT);

// ── Renewal price (based on line count) ──
$extraLineCost = \WasapBot\Core\Pricing::extraLine(); // €/week per extra line
$basePrice = \WasapBot\Core\Pricing::weeklyBase($clientUserId);    // €/week base (1 line included)
$extraLineCount = max($linesForUser - 1, 0);
$renewalPrice = $basePrice + ($extraLineCount * $extraLineCost);

// ─────────────────────────────────────────────────────────────────────
//   Actions
// ─────────────────────────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? '');
$baseUrl = 'cliente';
$notification = '';
if (isset($_GET['toggled']) && $_GET['toggled'] === '1') {
    $notification = '<div class="alert alert-success">✅ Bot ENCENDIDO. ¡A recibir clientes!</div>';
}
if (isset($_GET['toggled']) && $_GET['toggled'] === '0') {
    $notification = '<div class="alert alert-success">✅ Bot APAGADO.</div>';
}
if (isset($_GET['saved'])) {
    $notification = '<div class="alert alert-success">✅ Configuración guardada correctamente.</div>';
}

// ── Save config ──
if ($method === 'POST' && $action === 'save_config') {
    if ($isDemo) { $notification = '<div class="alert alert-warning">🔒 Modo demostración: los cambios no se guardan.</div>'; }
    else {
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
        'human_delays.seen.random_min_sec',
        'human_delays.seen.random_max_sec',
        'human_delays.typing.chars_per_sec_min',
        'human_delays.typing.chars_per_sec_max',
        'human_delays.typing.chunk_size',
        'human_delays.typing.chunk_pause_factor',
        'human_delays.typing.start_min_ms',
        'human_delays.typing.start_max_ms',
        'human_delays.typing.max_incoming_chars',
        'human_delays.typing.clamp_max_ms',
        'human_delays.read.base_min_ms',
        'human_delays.read.base_max_ms',
        'human_delays.read.per_char_ms',
        'human_delays.read.clamp_min_ms',
        'human_delays.read.clamp_max_ms',
        'human_delays.presend_sleep_sec',
        'human_delays.habituation.start_boost',
        'human_delays.habituation.decay',
        'human_delays.habituation.floor',
        'human_delays.after_send_fallback_sec',
        'human_delays.read.short_threshold_chars',
        'human_delays.read.short_base_min_ms',
        'human_delays.read.short_base_max_ms',
        'human_delays.pace.enabled',
        'human_delays.pace.min_factor',
        'human_delays.pace.max_factor',
        'human_delays.pace.reference_sec',
        'human_delays.pace.steepness',
        'human_delays.correction.enabled',
        'human_delays.correction.probability',
        'human_delays.correction.pause_min_ms',
        'human_delays.correction.pause_max_ms',
        'human_delays.pattern_variation.enabled',
        'human_delays.pattern_variation.weight_standard',
        'human_delays.pattern_variation.weight_skip_read',
        'human_delays.pattern_variation.weight_read_first',
        'human_delays.burst.enabled',
        'human_delays.burst.window_sec',
        'human_delays.burst.threshold_msgs',
        'human_delays.burst.rapid_factor',
        'human_delays.urgent.enabled',
        'human_delays.urgent.factor',
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
    header('Location: cliente?saved=1');
    exit;
    } // end else (not demo)
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

// ── Has notifications check ──
$hasNotifications = false;
$tgVal = $config->get('telegram.chat_ids', '');
$waVal = $config->get('telegram.whatsapp_phones', '');
if (is_array($tgVal)) $hasNotifications = count(array_filter($tgVal, fn($v) => trim((string)$v) !== '')) > 0;
elseif (is_string($tgVal) && trim($tgVal) !== '') $hasNotifications = true;
if (!$hasNotifications) {
    if (is_array($waVal)) $hasNotifications = count(array_filter($waVal, fn($v) => trim((string)$v) !== '')) > 0;
    elseif (is_string($waVal) && trim($waVal) !== '') $hasNotifications = true;
}

// ── Active girls count ──
$girlsActiveCount = 0;
$gf = WASAPBOT_ROOT . '/data/users/' . $clientUserId . '/girls.json';
if (file_exists($gf)) {
    $gd = @json_decode((string)@file_get_contents($gf), true);
    if (is_array($gd)) $girlsActiveCount = count(array_filter($gd['girls']??[], fn($g)=>!empty($g['activa'])));
}

// ── Demo mode: override stats with marketing-friendly numbers ──
if ($isDemo) {
    $leadsTotal = 312;
    $leadsToday = 17;
    $leadsArrived = 234;
    $allThreads = array_fill(0, 847, true);
    $todayThreads = array_fill(0, 42, true);
    $linesForUser = 3;
    $girlsActiveCount = 6;
}

// ── Toggle bot ──
if ($method === 'POST' && $action === 'toggle_bot') {
    if ($isDemo) { $notification = '<div class="alert alert-warning">🔒 Modo demostración: el bot no se puede encender ni apagar.</div>'; }
    else {
    requireValidCsrf();
    $newMode = ($botMode === 'start') ? 'stop' : 'start';

    // A4: Block turning ON if not fully configured
    if ($newMode === 'start') {
        $errors = [];

        // ── Subscription check ──
        if ($subExpired) $errors[] = 'Tu acceso ha expirado. <a href="pago" style="color:var(--accent);font-weight:600">Activa tu plan →</a>';

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
        if (!$hasTelegram2 && !$hasWhatsApp2) $errors[] = 'No has configurado dónde recibir avisos. Ve a 🔔 Notificaciones y añade tu Chat ID de Telegram o tu teléfono WhatsApp.';
        if (!empty($errors)) {
            $notification = '<div class="alert alert-warning"><strong>⚠️ No se puede encender el bot todavía:</strong><br>• ' . implode('<br>• ', $errors) . '</div>';
        } else {
            $dir = dirname($modeFilePath);
            if (!is_dir($dir)) @mkdir($dir, 0750, true);
            if (@file_put_contents($modeFilePath, $newMode, LOCK_EX) !== false) @chmod($modeFilePath, 0664);
            // Mark that the bot has been turned on at least once
            $everOnMarker = WASAPBOT_ROOT . '/data/users/' . $clientUserId . '/.bot_has_been_on';
            if (!file_exists($everOnMarker)) {
                @file_put_contents($everOnMarker, date('c'), LOCK_EX);
            }
            header('Location: cliente?toggled=1');
            exit;
        }
    } else {
        $dir = dirname($modeFilePath);
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        if (@file_put_contents($modeFilePath, $newMode, LOCK_EX) !== false) @chmod($modeFilePath, 0664);
        header('Location: cliente?toggled=0');
        exit;
    }
    } // end else (not demo)
}

// ─────────────────────────────────────────────────────────────────────
//   Render
// ─────────────────────────────────────────────────────────────────────

$sectionKeys = ['rol', 'estilo', 'tarifas', 'servicios', 'ubicacion', 'instrucciones_fotos', 'identidad_chicas', 'seguridad', 'ejemplos', 'formato_respuesta'];

// ── Detect if direct access (admin.casawasap.com) vs CRM embed (lamami.online) ──
$isDirectAccess = (strpos($_SERVER['HTTP_HOST'] ?? '', 'casawasap.com') !== false);

?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<?php if ($isDirectAccess): ?>
<meta name="theme-color" content="#f5f5f7">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="CasaWasap">
<link rel="manifest" href="manifest.json?v=20260611_1">
<link rel="apple-touch-icon" href="https://casawasap.com/img/hero-casawasap.png">
<?php endif; ?>
<title>bot-casa — <?php echo $clientName; ?></title>
<link rel="stylesheet" href="assets/style.css?v=20260825_2">
<link rel="stylesheet" href="assets/chat.css?v=20260629_1">
<link rel="stylesheet" href="assets/tutorial.css?v=20260825_1">
<?php if ($isDirectAccess): ?>
<script src="assets/pwa.js?v=20260825_1"></script>
<?php endif; ?>
<script>
// ── Theme toggle ──
(function() {
    var saved = null;
    try { saved = localStorage.getItem('botcasa_theme'); } catch(e) {}
    if (saved === 'dark') document.body.classList.add('theme-dark');
    document.addEventListener('DOMContentLoaded', function() {
        var btn = document.getElementById('themeToggle');
        if (!btn) return;
        var isDark = document.body.classList.contains('theme-dark');
        var iconLight = btn.querySelector('.theme-icon-light');
        var iconDark = btn.querySelector('.theme-icon-dark');
        if (iconLight) iconLight.style.display = isDark ? 'none' : 'inline';
        if (iconDark) iconDark.style.display = isDark ? 'inline' : 'none';
        btn.addEventListener('click', function() {
            document.body.classList.toggle('theme-dark');
            var nowDark = document.body.classList.contains('theme-dark');
            try { localStorage.setItem('botcasa_theme', nowDark ? 'dark' : 'light'); } catch(e) {}
            if (iconLight) iconLight.style.display = nowDark ? 'none' : 'inline';
            if (iconDark) iconDark.style.display = nowDark ? 'inline' : 'none';
        });
    });
})();
</script>
</head>
<body<?php echo $isDirectAccess ? ' class="app-client"' : ''; ?>>

<div class="header-client">
    <div class="header-brand">
        <div class="brand-icon">CW</div>
        <div class="brand-text">
            <h1>CasaWasap<span>.com</span></h1>
            <span class="header-slogan">Deja de vivir pegado al WhatsApp</span>
        </div>
    </div>
    <div class="header-user">
        <?php $userInitial = mb_substr($clientName, 0, 1); ?>
        <div class="user-avatar"><?php echo h($userInitial); ?></div>
        <span class="user-name"><?php echo $clientName; ?></span>
        <?php if (($_SESSION['role'] ?? '') === 'admin' && ($_SESSION['user_id'] ?? 0) !== $clientUserId): ?>
        <span class="suplantando-badge">👁 Suplantando</span>
        <?php endif; ?>
        <button id="themeToggle" class="btn btn-sm" title="Cambiar tema" type="button">
            <span class="theme-icon-light">☀️</span>
            <span class="theme-icon-dark" style="display:none">🌙</span>
        </button>
        <form method="post" action="cliente?action=toggle_bot" style="display:inline"<?php echo $isDemo ? ' onsubmit="showDemoToast(event)"' : ''; ?>>
            <input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
            <button type="submit" class="btn <?php echo $botMode === 'start' ? 'btn-danger' : 'btn-success'; ?> btn-sm"<?php echo ($isDemo || ($subExpired && $botMode !== 'start')) ? ' disabled' : ''; ?> title="<?php echo $subExpired ? 'Acceso expirado — activa tu plan para usar el bot' : ''; ?>">
                <?php echo $botMode === 'start' ? '⏹ APAGAR' : '▶ ENCENDER'; ?>
            </button>
        </form>
        <a href="logout" class="btn btn-sm">Salir</a>
    </div>
</div>

<?php echo $notification; ?>

<?php if ($isDemo): ?>
<div class="demo-banner">
    <span class="demo-banner-icon">🔒</span>
    <span class="demo-banner-text"><strong>Modo demostración</strong> — Solo lectura. Navega libremente por todas las pestañas para ver cómo funciona CasaWasap en producción real.</span>
</div>
<?php endif; ?>

<?php if ($subShowBanner): ?>
<?php
    $bannerClass = '';
    $bannerIcon = '';
    $bannerTitle = '';
    $bannerBody = '';
    $bannerCta = '';
    $bannerCtaUrl = '';
    $titleWarningClass = '';

    $cur = $subStatus['currentDay'];
    $tot = $subStatus['totalDays'];
    $left = $subStatus['daysLeft'];
    $barPct = ($tot > 0) ? round($cur / $tot * 100) : 0;

    if ($subStatus['status'] === 'trial') {
        $bannerClass = 'sub-trial';
        $bannerIcon = '🎁';
        $bannerTitle = 'Prueba gratuita — Día ' . $cur . ' de ' . $tot;
        $bannerBody = ($left <= 2 && $left > 0)
            ? '⚠️ Tu prueba termina en ' . $left . ' día' . ($left > 1 ? 's' : '') . '. Activa tu plan para seguir usando el bot.'
            : '';
        $bannerCta = '<a href="pago" class="btn btn-sm" style="background:var(--accent);color:#fff;text-decoration:none;padding:6px 14px;border-radius:6px;font-weight:600">Pagar con PayPal — ' . $renewalPrice . '€/sem</a>';
    } elseif ($subStatus['status'] === 'active') {
        $bannerClass = 'sub-active';
        $bannerIcon = '✅';
        $bannerTitle = 'Plan semanal — Día ' . $cur . ' de ' . $tot;
        // El botón de pago y el aviso solo aparecen cuando queda 1 día o menos
        // para que la renovación no confunda al cliente recién pagado.
        if ($left <= 1) {
            $bannerBody = ($left > 0)
                ? '⚠️ Tu plan vence en ' . $left . ' día' . ($left > 1 ? 's' : '') . '. Renueva para no perder el acceso.'
                : '⚠️ Tu plan vence hoy. Renueva para no perder el acceso.';
            $bannerCta = '<a href="pago" class="btn btn-sm" style="background:var(--accent);color:#fff;text-decoration:none;padding:6px 14px;border-radius:6px;font-weight:600">Pagar con PayPal — ' . $renewalPrice . '€/sem</a>';
            $titleWarningClass = ' sub-title--warning';
        } else {
            $bannerBody = '';
        }
    } elseif ($subStatus['status'] === 'expired') {
        $bannerClass = 'sub-expired';
        $bannerIcon = '🔴';
        $bannerTitle = 'Acceso expirado';
        $bannerBody = 'Tu periodo de acceso ha finalizado. Activa tu plan para seguir usando el bot.';
        $bannerCta = '<a href="pago" class="btn btn-sm" style="background:var(--danger);color:#fff;text-decoration:none;padding:6px 14px;border-radius:6px;font-weight:600">Pagar con PayPal — ' . $renewalPrice . '€/sem</a>';
        $barPct = 100; // full bar but red
    }
?>
<div class="subscription-banner <?php echo $bannerClass; ?>">
    <div class="sub-banner-left">
        <span class="sub-icon"><?php echo $bannerIcon; ?></span>
        <div class="sub-info">
            <span class="sub-title<?php echo $titleWarningClass; ?>"><?php echo $bannerTitle; ?></span>
            <?php if ($bannerBody !== ''): ?>
            <span class="sub-body"><?php echo $bannerBody; ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="sub-banner-right">
        <?php if ($tot > 0): ?>
        <div class="sub-progress-mini">
            <div class="sub-progress-track">
                <div class="sub-progress-fill <?php echo $subStatus['status'] === 'expired' ? 'sub-progress-fill--danger' : ''; ?>" style="width:<?php echo $barPct; ?>%"></div>
            </div>
            <span class="sub-progress-label"><?php echo $cur; ?>/<?php echo $tot; ?> días</span>
        </div>
        <?php endif; ?>
        <?php echo $bannerCta; ?>
    </div>
</div>
<?php endif; ?>

<?php
// ── Progress indicator ──
$progressTotal = 4;
$progressDone = 0;
if ($linesForUser > 0) $progressDone++;
if ($promptConfigured) $progressDone++;
$botEverOnMarker = WASAPBOT_ROOT . '/data/users/' . $clientUserId . '/.bot_has_been_on';
$botEverOn = file_exists($botEverOnMarker);
if (file_exists(WASAPBOT_ROOT . '/data/users/' . $clientUserId . '/girls.json')) {
    $gd = @json_decode((string)@file_get_contents(WASAPBOT_ROOT . '/data/users/' . $clientUserId . '/girls.json'), true);
    if (is_array($gd) && count(array_filter($gd['girls']??[], fn($g)=>!empty($g['activa']))) > 0) $progressDone++;
}
if ($hasNotifications) $progressDone++;
$progressPct = $progressDone > 0 ? round($progressDone / $progressTotal * 100) : 0;
?>
<div id="dashboard-progress">
<?php if ($progressPct < 100): ?>
<div class="progress-bar-wrap">
    <div class="progress-bar">
        <span>⚙️ Configuración: <?php echo $progressDone; ?>/<?php echo $progressTotal; ?></span>
        <div class="progress-bar-track">
            <div class="progress-bar-fill" style="width:<?php echo $progressPct; ?>%"></div>
        </div>
        <span><?php echo $progressPct; ?>%</span>
    </div>
</div>
<?php endif; ?>
</div>

<?php
// ── Onboarding wizard (shown if progress < 25%) ──
$showWizard = false; // Replaced by the server-persisted guided tutorial below.
if ($showWizard):
?>
<div id="wizard-overlay" class="wizard-overlay">
    <div class="wizard-card">
        <div class="wizard-hero">🚀</div>
        <h2 style="color:var(--accent);margin-bottom:6px">¡Bienvenido a CasaWasap!</h2>
        <p style="color:var(--text-muted);margin-bottom:20px;font-size:.88rem">
            Configura tu bot en 3 pasos para empezar a recibir clientes automáticamente.
        </p>
        <div style="text-align:left;margin-bottom:20px">
            <?php
            $wizSteps = [
                ['Personalidad', 'Define tarifas, ubicación y estilo', 'tab-personalidad', $promptConfigured],
                ['Líneas WhatsApp', 'Vincula tus números de teléfono', 'tab-lineas', $linesForUser > 0],
                ['Chicas activas', 'Añade tu catálogo de chicas', 'tab-chicas', $girlsActiveCount > 0],
            ];
            foreach ($wizSteps as $step):
                $ok = $step[3];
            ?>
            <div class="wizard-step wizard-step--<?php echo $ok ? 'ok' : 'fail'; ?>">
                <span class="ws-icon"><?php echo $ok ? '✅' : '❌'; ?></span>
                <div class="ws-body">
                    <strong><?php echo $step[0]; ?></strong>
                    <span><?php echo $step[1]; ?> → pestaña <?php echo $step[2]; ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-primary btn-lg" onclick="dismissWizard()" style="width:100%">¡Empezar!</button>
        <p style="font-size:.7rem;color:var(--text-muted);margin-top:10px">Este asistente solo aparece una vez. Los cambios se guardan automáticamente.</p>
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
    <button type="button" class="active" data-tab="tab-dashboard" id="tutorial-anchor-dashboard">📊 Inicio</button>
    <button type="button" data-tab="tab-personalidad" id="tutorial-anchor-personality">🎭 Personalidad</button>
    <button type="button" data-tab="tab-lineas" id="tutorial-anchor-lines">📱 Líneas</button>
    <button type="button" data-tab="tab-chicas">👩 Chicas</button>
    <button type="button" data-tab="tab-clientes">🔔 Notificaciones</button>
    <button type="button" data-tab="tab-mensajes" id="tutorial-anchor-chat">💬 Chat</button>
    <button type="button" data-tab="tab-estados">📢 Estados</button>
    <button type="button" data-tab="tab-seguimiento">📨 Seguimiento</button>
    <button type="button" data-tab="tab-learning">🧠 Aprendizaje</button>
    <button type="button" data-tab="tab-ajustes">⚙️ Ajustes</button>
    <button type="button" data-tab="tab-estadisticas">📈 Estadísticas</button>
</div>

<form method="post" action="cliente?action=save_config" class="main-form<?php echo $isDemo ? ' main-form--readonly' : ''; ?>"<?php echo $isDemo ? ' onsubmit="showDemoToast(event);return false"' : ''; ?>>
<input type="hidden" name="csrf_token" value="<?php echo h(generateCsrfToken()); ?>">
<input type="hidden" name="active_tab" class="js-active-tab-input" value="tab-dashboard">

<!-- ===== TAB: Dashboard ===== -->
<div class="tab-content active" id="tab-dashboard">
    <div id="dashboard-dynamic">
    <div class="card bot-status-bar">
    <span class="bot-emoji">🤖</span>
    <span class="bot-label">Estado del Bot</span>
    <span class="bot-indicator <?php echo $botStatusClass; ?>"></span>
    <span class="bot-status-text"><?php echo h($botStatusLabel); ?></span>
</div>

    <?php if ($progressPct < 100): ?>
    <div class="dashboard-hero">
        <div class="dashboard-hero-inner">
            <span class="dh-emoji">🚀</span>
            <div class="dh-body">
                <strong class="dh-title">Completa estos simples pasos para poner en marcha tu bot ya mismo</strong>
                <p class="dh-desc">Muy fácil de usar. <strong>CasaWasap</strong>, tu asistente que te ayuda a comunicarte y fidelizar clientes.</p>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="dashboard-hero dashboard-hero--done">
        <div class="dashboard-hero-inner">
            <span class="dh-emoji">✅</span>
            <div class="dh-body">
                <strong class="dh-title">¡Todo listo! Has completado todos los pasos de configuración</strong>
                <p class="dh-desc">Tu bot ya está en marcha. Si quieres modificar algo, vuelve a las pestañas de configuración cuando quieras.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // ── Setup cards data ──
    $setupCards = [
        [
            'id' => 'lineas',
            'icon' => '📱',
            'title' => 'Vincular WhatsApp',
            'ok' => $linesForUser > 0,
            'okText' => 'Conectado',
            'failText' => 'Pendiente',
            'hint' => 'Vincula tus números en Líneas',
            'tab' => 'tab-lineas',
        ],
        [
            'id' => 'tarifas',
            'icon' => '💰',
            'title' => 'Configurar tarifas',
            'ok' => $promptConfigured,
            'okText' => 'Configuradas',
            'failText' => 'Sin definir',
            'hint' => 'Define tus precios en Personalidad',
            'tab' => 'tab-personalidad',
        ],
        [
            'id' => 'chicas',
            'icon' => '👩',
            'title' => 'Chicas activas',
            'ok' => $girlsActiveCount > 0,
            'okText' => $girlsActiveCount . ' activa' . ($girlsActiveCount !== 1 ? 's' : ''),
            'failText' => 'Ninguna',
            'hint' => 'Añade tu catálogo en Chicas',
            'tab' => 'tab-chicas',
        ],
        [
            'id' => 'avisos',
            'icon' => '📬',
            'title' => 'Configurar avisos',
            'ok' => $hasNotifications,
            'okText' => 'Activados',
            'failText' => 'Sin avisos',
            'hint' => 'Configura Telegram o WhatsApp en Notificaciones',
            'tab' => 'tab-clientes',
        ],
    ];
    ?>
    <div class="setup-grid">
        <?php foreach ($setupCards as $card): ?>
        <div class="setup-card <?php echo $card['ok'] ? 'setup-card--ok' : 'setup-card--fail'; ?>" onclick="switchTab('<?php echo $card['tab']; ?>')">
            <span class="setup-icon"><?php echo $card['icon']; ?></span>
            <span class="setup-title"><?php echo $card['title']; ?></span>
            <span class="setup-badge <?php echo $card['ok'] ? 'setup-badge--ok' : 'setup-badge--fail'; ?>">
                <?php echo $card['ok'] ? '✅ ' . $card['okText'] : '❌ ' . $card['failText']; ?>
            </span>
            <?php if (!$card['ok']): ?>
            <span class="setup-hint"><?php echo $card['hint']; ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($progressPct >= 100 && !$botEverOn): ?>
    <div class="setup-cta">
        <div class="cta-icon">🚀</div>
        <div class="cta-title">¡Todo listo!</div>
        <div class="cta-sub">Enciende tu bot con el botón ▶ ENCENDER de arriba y empieza a recibir clientes automáticamente.</div>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>Estadísticas</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-num stat-num--info"><?php echo count($allThreads); ?></div>
                <div class="stat-label">Conversaciones totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-num stat-num--accent"><?php echo count($todayThreads); ?></div>
                <div class="stat-label">Conversaciones hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-num stat-num--ok"><?php echo $leadsTotal; ?></div>
                <div class="stat-label">Leads totales</div>
            </div>
            <div class="stat-card">
                <div class="stat-num stat-num--ok"><?php echo $leadsToday; ?></div>
                <div class="stat-label">Leads hoy</div>
            </div>
            <div class="stat-card">
                <div class="stat-num stat-num--accent2"><?php echo $linesForUser; ?></div>
                <div class="stat-label">Líneas WhatsApp</div>
                <div class="stat-sub">Vinculadas al bot</div>
            </div>
            <?php ?>
            <div class="stat-card">
                <div class="stat-num stat-num--accent2"><?php echo $girlsActiveCount; ?></div>
                <div class="stat-label">Chicas activas</div>
                <div class="stat-sub">En catálogo</div>
            </div>
            <div class="stat-card">
                <div class="stat-num stat-num--ok"><?php echo $leadsArrived??0; ?></div>
                <div class="stat-label">Clientes recibidos</div>
                <div class="stat-sub">Marcados como llegados</div>
            </div>
            <div class="stat-card">
                <div class="stat-num stat-num--warn"><?php echo count($allThreads)>0 ? round(count($allThreads)/max($leadsTotal,1),1) : 0; ?></div>
                <div class="stat-label">Ratio conv/lead</div>
                <div class="stat-sub">Conversaciones por lead</div>
            </div>
        </div>
    </div>
    </div>

    <div class="stats-more-link">
        <span>📈</span>
        <span>¿Quieres ver estadísticas más detalladas? Visita la pestaña <a href="#" onclick="event.preventDefault();switchTab('tab-estadisticas')">Estadísticas</a> con gráficos completos de rendimiento.</span>
    </div>
</div>

<!-- ===== TAB: Personalidad ===== -->
<div class="tab-content" id="tab-personalidad">
    <div class="section-guide">
        <span class="section-guide-icon">💡</span>
        <div class="section-guide-body">
            <strong>Define la personalidad de tu bot</strong>
            <span>Cuanto más detallada sea la configuración, mejor responderá. Tómate tu tiempo para rellenar cada sección. Usa 🔄 para restaurar valores de fábrica si te lías.</span>
        </div>
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
                <div class="detail-body">
                <p class="form-hint">
                    Escribe tus tarifas en lenguaje natural. Ejemplo: "30€ rapidito 10 min, 50€ media hora, 100€ 1 hora completo"
                </p>
                <textarea name="prompt[sections][tarifas]" class="code-area" style="width:100%;min-height:180px" spellcheck="false" oninput="buildPreview()"><?php echo cv('prompt.sections.tarifas'); ?></textarea>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-sm">💾 Guardar tarifas</button>
                    <button type="button" class="btn btn-sm" class="btn-ghost" 
                        onclick="if(confirm('¿Restaurar tarifas por defecto?'))resetField('prompt\\[sections\\]\\[tarifas\\]','30€ = rapidito 10 min\\n50€ = media hora completo\\n100€ = 1 hora completo')">🔄 Restaurar</button>
                </div>
                </div>
            </details>

            <!-- Anti-regateo (A12) -->
            <details class="prompt-details">
                <summary class="prompt-summary">🛡️ Anti-regateo</summary>
                <div class="detail-body">
                <p class="form-hint">Cómo reacciona el bot cuando un cliente intenta negociar el precio.</p>
                <label class="checkbox-label" style="margin-bottom:8px">
                    <input type="hidden" name="prompt[sections][no_regateo]" value="0">
                    <input type="checkbox" name="prompt[sections][no_regateo]" value="1" <?php echo checked((bool)$config->get('prompt.sections.no_regateo',false)); ?> onchange="buildPreview()"> No aceptar regateo de ningún tipo
                </label>
                <div class="form-group"><label>1er regateo <span class="label-muted">— primera vez que negocian</span></label><input type="text" name="prompt[sections][regateo_1]" value="<?php echo cv('prompt.sections.regateo_1','precio fijo cari, por eso la calidad es buena 😏'); ?>" placeholder="Respuesta al primer regateo"></div>
                <div class="form-group"><label>2º regateo <span class="label-muted">— si insisten</span></label><input type="text" name="prompt[sections][regateo_2]" value="<?php echo cv('prompt.sections.regateo_2','no puedo bajar mas amor, son los precios que tengo'); ?>" placeholder="Respuesta si insiste"></div>
                <div class="form-group"><label>3er regateo <span class="label-muted">— corte final</span></label><input type="text" name="prompt[sections][regateo_3]" value="<?php echo cv('prompt.sections.regateo_3','si buscas mas barato no soy yo, suerte 😘'); ?>" placeholder="Respuesta final"></div>
                <button type="submit" class="btn btn-primary btn-sm" style="margin-top:6px">💾 Guardar anti-regateo</button>
                </div>
            </details>

            <!-- Ubicación (A13: simplificado) -->
            <details class="prompt-details">
                <summary class="prompt-summary">📍 Ubicación</summary>
                <div class="detail-body">
                <div class="form-row">
                    <div class="form-group form-group--wide" style">
                        <label>Zona / ciudad</label>
                        <input type="text" name="prompt[sections][zona]" value="<?php echo cv('prompt.sections.zona'); ?>" placeholder="Ej: Madrid centro, zona tranquila" oninput="buildPreview()">
                    </div>
                    <div class="form-group form-group--narrow"
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
                <div class="detail-body">
                <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:6px">Describe los servicios disponibles. El bot usará esta información cuando le pregunten.</p>
                <textarea name="prompt[sections][servicios]" class="code-area" style="width:100%;min-height:150px" spellcheck="false" oninput="buildPreview()"><?php echo cv('prompt.sections.servicios'); ?></textarea>
                <div style="margin-top:6px;display:flex;gap:6px">
                    <button type="submit" class="btn btn-primary btn-sm">💾 Guardar servicios</button>
                    <button type="button" class="btn btn-sm" class="btn-ghost"
                        onclick="if(confirm('¿Restaurar servicios por defecto?'))resetField('prompt\\[sections\\]\\[servicios\\]','Servicio completo con preservativo.\\nFrancés natural solo en tarifa de 1h si el cliente lo pide.\\nGriego solo si el cliente pregunta expresamente.\\nNo salidas a domicilio.')">🔄 Restaurar</button>
                </div>
                </div>
            </details>

            <!-- Ofertas (A14: simplificado) -->
            <details class="prompt-details">
                <summary class="prompt-summary">🎁 Ofertas especiales (opcional)</summary>
                <div class="detail-body">
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
                <p class="form-hint">
                    Resumen de cómo está configurado tu bot ahora mismo.
                </p>
                <div id="prompt-summary">
                    <div class="empty-state" style="font-size:.8rem">⏳ Cargando resumen...</div>
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
            <span class="section-guide-icon">📱</span>
            <div class="section-guide-body">
                <strong>Cada línea es un número de WhatsApp que conectas al bot</strong>
                <span>Cuando alguien escriba a ese número, el bot contestará automáticamente. Puedes tener varios números y el bot los atenderá todos.</span>
            </div>
        </div>

        <?php if ($subStatus['status'] === 'trial'): ?>
        <div class="trial-limit-notice">
            <span>🔒</span>
            <span><strong>Modo prueba gratuita:</strong> tienes 1 línea incluida y lista para configurar. Para añadir más líneas, <a href="pago" style="color:var(--accent);font-weight:600">activa el plan de pago →</a></span>
        </div>
        <?php elseif ($subStatus['status'] === 'active'): ?>
        <div class="trial-limit-notice" style="background:linear-gradient(135deg, rgba(5,150,105,0.08), rgba(5,150,105,0.04));border-color:rgba(5,150,105,0.2)">
            <span>💡</span>
            <span>
                <strong>Tu plan incluye 1 línea de WhatsApp, lista para configurar.</strong>
                <span style="display:block;margin-top:4px">La primera línea no tiene coste adicional. Cada línea adicional cuesta <strong>+<?php echo $extraLineCost; ?>€/semana</strong>.</span>
                <?php if ($extraLineCount > 0): ?>
                <span style="display:block;margin-top:4px">Tienes <?php echo $linesForUser; ?> línea<?php echo $linesForUser > 1 ? 's' : ''; ?> → tu renovación: <strong><?php echo $renewalPrice; ?>€/sem</strong>.</span>
                <?php else: ?>
                <span style="display:block;margin-top:4px">Tu renovación: <strong><?php echo $renewalPrice; ?>€/sem</strong>.</span>
                <?php endif; ?>
                <span style="display:block;margin-top:4px">¿Muchas líneas? <a href="pago" style="color:var(--accent);font-weight:600">Borra las que no uses antes de pagar →</a></span>
            </span>
        </div>
        <?php endif; ?>

        <?php $trialAndHasLine = ($subStatus['status'] === 'trial' && $linesForUser >= 1); ?>
        <?php if (!$trialAndHasLine): ?>
        <!-- Add form -->
        <div class="form-section">
            <h3 style="margin-bottom:10px">➕ Añadir línea</h3>
            <p style="color:var(--text-muted);font-size:.75rem;margin-bottom:10px">Al crear una línea, aparecerá un botón <strong>QR</strong> en la tabla. Púlsalo para ver el código QR. Debes escanearlo <strong>rápido (antes de 1-2 minutos)</strong> desde tu WhatsApp → Ajustes → Vincular dispositivo.</p>
            <div class="form-row-end">
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
        <?php endif; ?>

        <!-- Lines table -->
        <div id="lines-container">
            <p class="empty-state">No hay líneas configuradas.</p>
        </div>

        <!-- QR modal (hidden) -->
        <div id="qr-modal" class="modal-backdrop">
            <div class="modal-panel">
                <h3>📱 Escanea el QR</h3>
                <p class="modal-hint">Abre WhatsApp → Ajustes → Vincular dispositivo</p>
                <p class="modal-warn">⚠️ El QR caduca rápido. Si al escanear desde WhatsApp → Dispositivos vinculados te da error, probablemente ha caducado. Regenera el QR y escanea de nuevo.</p>
                <img id="qr-image" src="" class="modal-img" alt="QR Code">
                <div id="qr-status" class="modal-status"></div>
                <button type="button" class="btn btn-sm btn-primary" onclick="regenerateQR()">🔄 Regenerar QR</button>
                <button type="button" class="btn btn-sm btn-ghost" onclick="document.getElementById('qr-modal').classList.remove('open')">Cerrar</button>
            </div>
        </div>

        <!-- Test modal -->
        <div id="test-modal" class="modal-backdrop">
            <div class="modal-panel">
                <h3>📤 Enviar mensaje de prueba</h3>
                <input type="text" id="test-phone" placeholder="Ej: 666555444 (formato español)" class="modal-input">
                <input type="hidden" id="test-line-id">
                <button type="button" class="btn btn-primary" onclick="sendTestMessage()">Enviar prueba</button>
                <button type="button" class="btn btn-sm btn-ghost" style="margin-top:8px" onclick="document.getElementById('test-modal').classList.remove('open')">Cancelar</button>
                <div id="test-result" class="modal-result"></div>
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
            <span class="section-guide-icon">👩</span>
            <div class="section-guide-body">
                <strong>Tu catálogo de chicas</strong>
                <span>Añade su nombre, una breve descripción y hasta 4 fotos. Puedes activarlas o desactivarlas cuando quieras —las chicas inactivas no aparecerán.</span>
            </div>
        </div>

        <!-- Add/edit form -->
        <div class="girl-form-card">
            <h3 id="girl-form-title" style="margin-bottom:10px">➕ Nueva chica</h3>
            <input type="hidden" id="girl-edit-id">
            <div class="form-row">
                <div class="form-group form-group--narrow" style="min-width:140px">
                    <label>Nombre *</label>
                    <input type="text" id="girl-nombre" placeholder="Ej: Sandra" style="width:100%">
                </div>
                <div class="form-group form-group--wide" style;min-width:250px">
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
            <p class="empty-state">Cargando chicas...</p>
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
            <span class="section-guide-icon">📢</span>
            <div class="section-guide-body">
                <strong>El bot publica estados automáticamente</strong>
                <span>Los estados se generan con las chicas activas de 👩 Chicas. Si no hay chicas activas, no se publicará nada. El bot alterna entre varios formatos.</span>
            </div>
        </div>

        <!-- Config form -->
        <div class="form-section">
            <!-- ON/OFF + Save -->
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap">
                <label class="checkbox-label" style="font-size:.9rem"><input type="checkbox" id="estados-enabled" onchange="saveEstadosConfig()"> Activar publicador de estados</label>
                <span class="form-spacer"></span>
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
                <div class="form-group form-group--pin">
                    <label>Cada</label>
                    <input type="number" id="estados-freq-valor" value="6" min="1" max="24" onchange="saveEstadosConfig()">
                </div>
                <div class="form-group">
                    <label>Formato</label>
                    <select id="estados-formato" onchange="saveEstadosConfig()">
                        <option value="mix_aleatorio">🎲 Mix aleatorio (recomendado)</option>
                        <optgroup label="── Individual (1 chica) ──">
                            <option value="chica_del_dia">🔥 Chica del día (1 + 2 fotos)</option>
                            <option value="tentacion_del_dia">🍎 Tentación del día (1 + 2 fotos)</option>
                            <option value="dulce_prohibido">🍭 Dulce prohibido (1 + 2 fotos)</option>
                            <option value="ven_ya">⏳ Ven ya (1 urgente + 2 fotos)</option>
                            <option value="susurro">🤫 Al oído (1 íntima + 2 fotos)</option>
                            <option value="confesion">🌙 Confesión nocturna (1 + 2 fotos)</option>
                            <option value="frase_del_dia">💬 Frase del día (1 + frase pícara)</option>
                            <option value="solo_valientes">💪 Solo para valientes (1 + desafío)</option>
                            <option value="cita_a_ciegas">🕶️ Cita a ciegas (1 + misterio, sin foto)</option>
                            <option value="regalo_sorpresa">🎁 Regalo sorpresa (1 + tono regalo)</option>
                            <option value="amiga_recomienda">🗣️ Amiga recomienda (1 + curiosidad)</option>
                            <option value="la_nueva">🆕 Te está esperando (1 + directo)</option>
                        </optgroup>
                        <optgroup label="── Varias chicas ──">
                            <option value="chicas_de_hoy">👯‍♀️ Chicas de hoy (todas + 1 foto c/u)</option>
                            <option value="duo_sexy">💋 Dúo sexy (2 + 1 foto c/u)</option>
                            <option value="estrella_grupo">⭐ Estrella + grupo (1 destacada + resto)</option>
                            <option value="trio_tentador">👯 Triple tentación (3 + 1 foto c/u)</option>
                            <option value="puertas_abiertas">🚪 Puertas abiertas (todas, bienvenida)</option>
                            <option value="antojos">🍒 Antojos (todas, estilo menú)</option>
                            <option value="el_equipo">💪 El equipazo (todas, alineación)</option>
                            <option value="frescas">🌸 Recién llegaditas (todas, frescas)</option>
                            <option value="catalogo_rapido">📋 Catálogo rápido (solo nombres)</option>
                            <option value="juego_parejas">🎭 Juego de parejas (2 + química)</option>
                            <option value="el_casting">🎬 El casting (todas + tú eres el juez)</option>
                            <option value="modo_finde">🍾 Modo finde (todas + festivo)</option>
                        </optgroup>
                        <optgroup label="── Especial ──">
                            <option value="oferta_flash">⚡ Ahora o nunca (1+ chicas + urgencia)</option>
                        </optgroup>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Horario inicio</label><input type="time" id="estados-hora-inicio" value="08:00" onchange="saveEstadosConfig()"></div>
                <div class="form-group"><label>Horario fin</label><input type="time" id="estados-hora-fin" value="23:00" onchange="saveEstadosConfig()"></div>
            </div>
            <div id="estados-lines-checkboxes" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px"></div>
            <div id="estados-status" class="status-msg"></div>
        </div>

        <!-- History -->
        <h3>📋 Historial de publicaciones</h3>
        <div id="estados-history" class="status-msg">
            <p class="empty-state" style="padding:10px">No hay publicaciones todavía.</p>
        </div>
    </div>
</div>

<!-- ===== TAB: Notificaciones ===== -->
<div class="tab-content" id="tab-clientes">
    <div class="card">
        <h2>🔔 Notificaciones y Avisos
            <span class="tooltip-wrap"><span class="tooltip-icon">?</span>
                <span class="tooltip-box">Lista de clientes que han mostrado interés en venir. Marca los que llegaron de verdad para medir la efectividad del bot.</span>
            </span>
        </h2>

        <div class="section-guide">
            <span class="section-guide-icon">🔔</span>
            <div class="section-guide-body">
                <strong>El bot te avisa cuando detecta un cliente en camino</strong>
                <span>Configura aquí dónde recibir los avisos: por <strong>Telegram</strong> (recomendado) o por <strong>WhatsApp</strong>. Los leads aparecerán en la pestaña 🧠 Aprendizaje.</span>
            </div>
        </div>

        <!-- Telegram config -->
        <divclass="config-section">
            <details open>
                <summary style="padding:0 0 8px 0;cursor:pointer;font-weight:600;font-size:.84rem;color:var(--text)">📱 Configurar avisos (Telegram o WhatsApp)</summary>
                <div style="padding-top:8px;font-size:.78rem;color:var(--text-muted)">
                    <strong>📱 Telegram (recomendado) — paso a paso:</strong><br>
                    1. Abre la app de Telegram en tu móvil u ordenador<br>
                    2. En el buscador, escribe <strong>@BotFather</strong> (es el bot oficial para crear bots)<br>
                    3. Escríbele <strong>/newbot</strong> y sigue sus instrucciones: te pedirá un nombre (ej: "Avisos Casa") y un usuario (ej: <code>avisos_casa_bot</code>)<br>
                    4. @BotFather te dará un <strong>token</strong> (un texto largo). CÓPIALO, lo necesitarás<br>
                    5. Ahora busca <strong>@userinfobot</strong> en Telegram, inícialo con /start y te dará tu <strong>Chat ID</strong> (un número, ej: 123456789)<br>
                    6. Pega tu Chat ID en la caja de abajo (uno por línea si tienes varios)<br>
                    7. Marca el checkbox <strong>Alertas activadas</strong><br>
                    8. Pulsa el botón 💾 Guardar avisos<br><br>
                    <strong>WhatsApp (alternativa):</strong><br>
                    Puedes poner tu número personal para recibir los avisos por WhatsApp (menos recomendado).
                    <strong>IMPORTANTE:</strong> El número que pongas NO puede ser uno de los que tengas como línea del bot en 📱 Líneas. Usa tu número personal.
                </div>
            </details>
            <div style="border-top:1px solid var(--border);margin-top:8px;padding-top:10px">
            <div class="form-row">
                <div class="form-group form-group--wide" style">
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
    </div>
</div>

<!-- ===== TAB: Mensajes (WhatsApp-style Chat) ===== -->
<div class="tab-content" id="tab-mensajes"></div>

<!-- ===== TAB: Seguimiento ===== -->
<div class="tab-content" id="tab-seguimiento">
    <div class="card">
        <h2>📨 Seguimiento automático</h2>
        <div class="section-guide">
            <span class="section-guide-icon">📨</span>
            <div class="section-guide-body">
                <strong>No pierdas clientes automáticamente</strong>
                <span>Estas funciones te ayudan a mantener a los clientes informados sin que tengas que hacer nada.</span>
            </div>
        </div>

        <!-- Follow-up -->
        <div class="feature-card">
            <div class="feature-header">
                <h3>🔄 Recontactar leads antiguos</h3>
                <label class="checkbox-label" style="display:inline-flex;align-items:center;gap:6px;font-size:.82rem;cursor:pointer">
                    <input type="hidden" name="cron[followup][enabled]" value="0">
                    <input type="checkbox" name="cron[followup][enabled]" value="1" <?php echo checked((bool)$config->get('cron.followup.enabled',false)); ?> onchange="this.closest('.feature-card').classList.toggle('feature-card--on',this.checked)"> Activado
                </label>
            </div>
            <p>
                El bot revisa periódicamente los leads antiguos y les envía un mensaje con fotos de las chicas disponibles para intentar que vuelvan.
                Es como un <strong>"te echamos de menos"</strong> automático.
            </p>
            <p style="color:var(--text-muted);font-size:.78rem">
                <strong>¿Cuándo se envía?</strong> Solo a clientes con los que se habló hace 48-72h y que NO hayan sido marcados como "llegó".
            </p>
            <div class="alert-warning" style="margin-bottom:12px;font-size:.8rem;padding:10px 14px;border-radius:8px">
                ⚠️ <strong>Importante:</strong> Marca los leads como "llegó" en la pestaña <strong>🧠 Aprendizaje</strong>. Si no los marcas, cuando pase el tiempo que el cliente dijo que iba a tardar, el bot le recordará igual que tiene una cita. Si no tienes tiempo de marcar llegadas, mejor <strong>desactiva esta función</strong>.
            </div>
            <div class="form-row">
                <div class="form-group"><label>Máx leads por ejecución</label><input type="number" name="cron[followup][max_leads_per_run]" value="<?php echo cv('cron.followup.max_leads_per_run','10'); ?>"></div>
                <div class="form-group"><label>Horario inicio</label><input type="text" name="cron[followup][send_window_start]" value="<?php echo cv('cron.followup.send_window_start','10:00'); ?>"></div>
                <div class="form-group"><label>Horario fin</label><input type="text" name="cron[followup][send_window_end]" value="<?php echo cv('cron.followup.send_window_end','22:00'); ?>"></div>
            </div>
        </div>

        <!-- Recordatorios ETA -->
        <div class="feature-card">
            <div class="feature-header">
                <h3>⏰ Recordatorios de llegada</h3>
                <label class="checkbox-label" style="display:inline-flex;align-items:center;gap:6px;font-size:.82rem;cursor:pointer">
                    <input type="hidden" name="cron[reminder][enabled]" value="0">
                    <input type="checkbox" name="cron[reminder][enabled]" value="1" <?php echo checked((bool)$config->get('cron.reminder.enabled',false)); ?>> Activado
                </label>
            </div>
            <p>
                Si un cliente dice <strong>"llego en 20 minutos"</strong>, el bot le enviará <strong>un solo recordatorio</strong> pasado ese tiempo para confirmar que sigue en camino.
            </p>
            <div class="alert-warning" style="margin-bottom:10px;font-size:.85rem;padding:12px 14px;border-radius:8px;border:2px solid var(--warn)">
                ⚠️ <strong>IMPORTANTE:</strong> Este recordatorio se envía automáticamente aunque no hayas marcado el lead como "llegó" en 🧠 Aprendizaje. El bot se basa solo en lo que el cliente dijo. Si el cliente llega pero no lo marcaste, el bot le enviará el recordatorio igual. Si no tienes tiempo de marcar llegadas, <strong>mejor no uses esta función</strong>.
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="margin-top:8px">💾 Guardar configuración de seguimiento</button>
    </div>
</div>

<!-- ===== TAB: Aprendizaje ===== -->
<div class="tab-content" id="tab-learning">
    <div class="card">
        <h2>🧠 Aprendizaje del Bot
            <span class="tooltip-wrap"><span class="tooltip-icon">?</span>
                <span class="tooltip-box">El bot analiza las conversaciones para aprender tu estilo y detectar patrones. Cuantos más leads confirmes, más inteligente se hará.</span>
            </span>
        </h2>
        <div class="section-guide">
            <span class="section-guide-icon">🧠</span>
            <div class="section-guide-body">
                <strong>El bot aprende de tus conversaciones</strong>
                <span>Coge tu estilo si contestas desde la pestaña Chat. Aquí verás las estadísticas de aprendizaje, el playbook generado por IA, y las conversaciones clasificadas por el bot.</span>
            </div>
        </div>

        <!-- Stats cards (populated by JS) -->
        <div id="learning-stats" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:16px;margin-bottom:16px">
            <div style="flex:1;min-width:105px;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px;text-align:center">
                <div style="font-size:1.5rem;font-weight:700;color:var(--text-muted)">—</div>
                <div style="font-size:.7rem;color:var(--text-muted);margin-top:2px">Cargando…</div>
            </div>
        </div>

        <!-- Playbook section -->
        <div id="learning-playbook" style="background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px;margin-bottom:16px;display:none">
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:8px">
                <strong>Playbook:</strong> <span id="playbook-status"></span>
                <button type="button" class="btn btn-sm btn-info" style="margin-left:12px" onclick="viewPlaybook()" id="btn-view-playbook">📖 Ver playbook</button>
            </div>
        </div>
        <div id="playbook-preview" style="display:none;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px;max-height:500px;overflow:auto;font-size:.82rem;line-height:1.6;white-space:pre-wrap;margin-bottom:16px"></div>

        <!-- Classified outcomes table -->
        <h3 style="margin-top:20px;margin-bottom:12px">📋 Conversaciones clasificadas</h3>
        <div style="display:flex;gap:8px;margin-bottom:12px;align-items:center">
            <span id="outcomes-count" style="font-size:.8rem;color:var(--text-muted)">Cargando…</span>
        </div>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:.82rem">
                <thead><tr style="background:var(--bg)">
                    <th style="padding:8px;text-align:left;border-bottom:1px solid var(--border)">Teléfono</th>
                    <th style="padding:8px;text-align:left;border-bottom:1px solid var(--border)">Outcome</th>
                    <th style="padding:8px;text-align:left;border-bottom:1px solid var(--border)">Msgs</th>
                    <th style="padding:8px;text-align:left;border-bottom:1px solid var(--border)">Fecha</th>
                    <th style="padding:8px;text-align:left;border-bottom:1px solid var(--border)">Acción</th>
                </tr></thead>
                <tbody id="outcomes-tbody">
                    <tr><td colspan="5" style="padding:20px;text-align:center;color:var(--text-muted)">Cargando…</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ===== TAB: Ajustes ===== -->
<div class="tab-content" id="tab-ajustes">
    <div class="card">
        <h2>⚙️ Ajustes del Bot</h2>
        <div class="section-guide">
            <span class="section-guide-icon">⚙️</span>
            <div class="section-guide-body">
                <strong>Ajustes avanzados del bot</strong>
                <span>Configura los tiempos de respuesta para que parezca humano. Si no estás seguro, deja los valores recomendados.</span>
            </div>
        </div>

        <details class="prompt-details" open>
            <summary style="padding:12px 16px;cursor:pointer;font-weight:600;font-size:.9rem">⏱ Humanización (Delays)</summary>
            <div class="detail-body">
                <p class="form-hint">
                    Estos tiempos hacen que el bot parezca humano al responder. Simula que "lee", "piensa" y "escribe".
                    Valores bajos = parece artificial (responde al instante). Valores altos = cliente espera demasiado.
                </p>
                <div class="form-row">
                    <div class="form-group"><label>Espera aleatoria mínima "visto" (seg) <span class="rec-label">Recomendado: 1</span></label><input type="number" step="0.1" name="human_delays[seen][random_min_sec]" value="<?php echo cv('human_delays.seen.random_min_sec','1'); ?>"></div>
                    <div class="form-group"><label>Espera aleatoria máxima "visto" (seg) <span class="rec-label">Recomendado: 3</span></label><input type="number" step="0.1" name="human_delays[seen][random_max_sec]" value="<?php echo cv('human_delays.seen.random_max_sec','3'); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Caracteres/segundo mín <span class="rec-label">Recomendado: 38</span></label><input type="number" name="human_delays[typing][chars_per_sec_min]" value="<?php echo cv('human_delays.typing.chars_per_sec_min','38'); ?>"></div>
                    <div class="form-group"><label>Caracteres/segundo máx <span class="rec-label">Recomendado: 85</span></label><input type="number" name="human_delays[typing][chars_per_sec_max]" value="<?php echo cv('human_delays.typing.chars_per_sec_max','85'); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Tiempo lectura mín (ms) <span class="rec-label">Recomendado: 900</span></label><input type="number" name="human_delays[read][base_min_ms]" value="<?php echo cv('human_delays.read.base_min_ms','900'); ?>"></div>
                    <div class="form-group"><label>Tiempo lectura máx (ms) <span class="rec-label">Recomendado: 2200</span></label><input type="number" name="human_delays[read][base_max_ms]" value="<?php echo cv('human_delays.read.base_max_ms','2200'); ?>"></div>
                    <div class="form-group"><label>Espera entre mensajes (seg) <span class="rec-label">Recomendado: 15</span></label><input type="number" step="0.1" name="human_delays[presend_sleep_sec]" value="<?php echo cv('human_delays.presend_sleep_sec','15'); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Habituación inicio <span class="rec-label">Recomendado: 6.2</span></label><input type="number" step="0.1" name="human_delays[habituation][start_boost]" value="<?php echo cv('human_delays.habituation.start_boost','6.2'); ?>"></div>
                    <div class="form-group"><label>Habituación decaimiento <span class="rec-label">Recomendado: 0.92</span></label><input type="number" step="0.01" name="human_delays[habituation][decay]" value="<?php echo cv('human_delays.habituation.decay','0.92'); ?>"></div>
                    <div class="form-group"><label>Habituación suelo <span class="rec-label">Recomendado: 1.25</span></label><input type="number" step="0.01" name="human_delays[habituation][floor]" value="<?php echo cv('human_delays.habituation.floor','1.25'); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Lectura por carácter (ms) <span class="rec-label">Recomendado: 22</span></label><input type="number" name="human_delays[read][per_char_ms]" value="<?php echo cv('human_delays.read.per_char_ms','22'); ?>"></div>
                    <div class="form-group"><label>Lectura clamp mín (ms) <span class="rec-label">Recomendado: 1200</span></label><input type="number" name="human_delays[read][clamp_min_ms]" value="<?php echo cv('human_delays.read.clamp_min_ms','1200'); ?>"></div>
                    <div class="form-group"><label>Lectura clamp máx (ms) <span class="rec-label">Recomendado: 22000</span></label><input type="number" name="human_delays[read][clamp_max_ms]" value="<?php echo cv('human_delays.read.clamp_max_ms','22000'); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Umbral msg corto (chars) <span class="rec-label">Recomendado: 15</span></label><input type="number" name="human_delays[read][short_threshold_chars]" value="<?php echo cv('human_delays.read.short_threshold_chars','15'); ?>"></div>
                    <div class="form-group"><label>Lectura base corta mín (ms) <span class="rec-label">Recomendado: 300</span></label><input type="number" name="human_delays[read][short_base_min_ms]" value="<?php echo cv('human_delays.read.short_base_min_ms','300'); ?>"></div>
                    <div class="form-group"><label>Lectura base corta máx (ms) <span class="rec-label">Recomendado: 800</span></label><input type="number" name="human_delays[read][short_base_max_ms]" value="<?php echo cv('human_delays.read.short_base_max_ms','800'); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Tipeo chunk (chars) <span class="rec-label">Recomendado: 24</span></label><input type="number" name="human_delays[typing][chunk_size]" value="<?php echo cv('human_delays.typing.chunk_size','24'); ?>"></div>
                    <div class="form-group"><label>Pausa chunk (factor) <span class="rec-label">Recomendado: 0.65</span></label><input type="number" step="0.01" name="human_delays[typing][chunk_pause_factor]" value="<?php echo cv('human_delays.typing.chunk_pause_factor','0.65'); ?>"></div>
                    <div class="form-group"><label>Start typing mín (ms) <span class="rec-label">Recomendado: 350</span></label><input type="number" name="human_delays[typing][start_min_ms]" value="<?php echo cv('human_delays.typing.start_min_ms','350'); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Start typing máx (ms) <span class="rec-label">Recomendado: 1200</span></label><input type="number" name="human_delays[typing][start_max_ms]" value="<?php echo cv('human_delays.typing.start_max_ms','1200'); ?>"></div>
                    <div class="form-group"><label>Chars entrantes máx <span class="rec-label">Recomendado: 180</span></label><input type="number" name="human_delays[typing][max_incoming_chars]" value="<?php echo cv('human_delays.typing.max_incoming_chars','180'); ?>"></div>
                    <div class="form-group"><label>Clamp máx typing (ms) <span class="rec-label">Recomendado: 90000</span></label><input type="number" name="human_delays[typing][clamp_max_ms]" value="<?php echo cv('human_delays.typing.clamp_max_ms','90000'); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Pausa tras enviar (seg) <span class="rec-label">Recomendado: 0.4</span></label><input type="number" step="0.1" name="human_delays[after_send_fallback_sec]" value="<?php echo cv('human_delays.after_send_fallback_sec','0.4'); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>🎯 Pace: factor mín <span class="rec-label">0.5</span></label><input type="number" step="0.01" name="human_delays[pace][min_factor]" value="<?php echo cv('human_delays.pace.min_factor','0.5'); ?>"></div>
                    <div class="form-group"><label>Pace: factor máx <span class="rec-label">2.0</span></label><input type="number" step="0.01" name="human_delays[pace][max_factor]" value="<?php echo cv('human_delays.pace.max_factor','2'); ?>"></div>
                    <div class="form-group"><label>Pace: ref (seg) <span class="rec-label">60</span></label><input type="number" step="0.1" name="human_delays[pace][reference_sec]" value="<?php echo cv('human_delays.pace.reference_sec','60'); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Pace: steepness <span class="rec-label">0.2</span></label><input type="number" step="0.01" name="human_delays[pace][steepness]" value="<?php echo cv('human_delays.pace.steepness','0.2'); ?>"></div>
                    <div class="form-group"><label>Corrección: prob <span class="rec-label">0.12</span></label><input type="number" step="0.01" name="human_delays[correction][probability]" value="<?php echo cv('human_delays.correction.probability','0.12'); ?>"></div>
                    <div class="form-group"><label>Corrección: pausa mín (ms) <span class="rec-label">400</span></label><input type="number" name="human_delays[correction][pause_min_ms]" value="<?php echo cv('human_delays.correction.pause_min_ms','400'); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Corrección: pausa máx (ms) <span class="rec-label">1800</span></label><input type="number" name="human_delays[correction][pause_max_ms]" value="<?php echo cv('human_delays.correction.pause_max_ms','1800'); ?>"></div>
                    <div class="form-group"><label>Ráfaga: ventana (seg) <span class="rec-label">30</span></label><input type="number" name="human_delays[burst][window_sec]" value="<?php echo cv('human_delays.burst.window_sec','30'); ?>"></div>
                    <div class="form-group"><label>Ráfaga: umbral (msgs) <span class="rec-label">3</span></label><input type="number" name="human_delays[burst][threshold_msgs]" value="<?php echo cv('human_delays.burst.threshold_msgs','3'); ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Ráfaga: factor <span class="rec-label">0.33</span></label><input type="number" step="0.01" name="human_delays[burst][rapid_factor]" value="<?php echo cv('human_delays.burst.rapid_factor','0.33'); ?>"></div>
                    <div class="form-group"><label>Urgencia: factor <span class="rec-label">0.25</span></label><input type="number" step="0.01" name="human_delays[urgent][factor]" value="<?php echo cv('human_delays.urgent.factor','0.25'); ?>"></div>
                    <div class="form-group"><label>Patrón: Std / Skip / Read1 (%) <span class="rec-label">70/20/10</span></label>
                        <input type="number" name="human_delays[pattern_variation][weight_standard]" value="<?php echo cv('human_delays.pattern_variation.weight_standard','70'); ?>" placeholder="70" style="width:32%;display:inline">
                        <input type="number" name="human_delays[pattern_variation][weight_skip_read]" value="<?php echo cv('human_delays.pattern_variation.weight_skip_read','20'); ?>" placeholder="20" style="width:32%;display:inline">
                        <input type="number" name="human_delays[pattern_variation][weight_read_first]" value="<?php echo cv('human_delays.pattern_variation.weight_read_first','10'); ?>" placeholder="10" style="width:32%;display:inline">
                    </div>
                </div>
                <div class="alert-warning" style="margin-top:12px;font-size:.8rem;padding:10px 14px;border-radius:8px">
                    ⚠️ <strong>Cuidado:</strong> Modifica estos valores con precaución. Si los cambias sin control, el bot puede desajustarse: contestar demasiado rápido (parecerá artificial y espantará clientes) o demasiado lento (el cliente se impacientará y se irá). Los valores recomendados están probados y funcionan bien.
                </div>
            </div>
        </details>

        <details class="prompt-details">
            <summary style="padding:12px 16px;cursor:pointer;font-weight:600;font-size:.9rem">🎲 Variantes de mensajes</summary>
            <div class="detail-body">
                <p class="form-hint">
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
                        onclick="if(confirm('¿Restaurar frases por defecto?'))this.parentElement.nextElementSibling.value='cuanto tardas amor?\navisame cuando salgas\nen cuantos min vienes?\ncuando llegas amor?\nme dices cuanto tardas?\nsal y avisame que te espero'">🔄 Restaurar</button>
                </p>
                <textarea name="message_variants[eta_request_variants]" rows="3" class="code-area" spellcheck="false"><?php
                    $etaVal = trim(cv('message_variants.eta_request_variants'));
                    echo $etaVal !== '' ? $etaVal : "cuanto tardas amor?\navisame cuando salgas\nen cuantos min vienes?\ncuando llegas amor?\nme dices cuanto tardas?\nsal y avisame que te espero";
                ?></textarea>
            </div>
        </details>

        <button type="submit" class="btn btn-primary" style="margin-top:12px">💾 Guardar Ajustes</button>
    </div>
</div>

<!-- ===== TAB: Estadísticas ===== -->
<div class="tab-content" id="tab-estadisticas">
    <div class="card">
        <h2>📈 Estadísticas</h2>
        <div class="section-guide">
            <span class="section-guide-icon">📈</span>
            <div class="section-guide-body">
                <strong>Rendimiento real de tu bot</strong>
                <span>Conversaciones, leads generados, clientes que llegaron y ratio de conversión. Entiende si tu inversión funciona y dónde mejorar.</span>
            </div>
        </div>
        <div id="estadisticas-container">
            <p class="empty-state empty-state--large">Cargando estadísticas...</p>
        </div>
    </div>
</div>

</form>
<script>
// ── Demo mode guard ──
var IS_DEMO = <?php echo $isDemo ? 'true' : 'false'; ?>;
function showDemoToast(e) {
    if (e && e.preventDefault) e.preventDefault();
    var existing = document.querySelector('.demo-toast');
    if (existing) { existing.remove(); }
    var t = document.createElement('div');
    t.className = 'demo-toast';
    t.textContent = '🔒 Modo demo: solo lectura. No se permiten cambios.';
    t.style.cssText = 'position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:var(--warn);color:#000;padding:10px 24px;border-radius:8px;font-size:.85rem;font-weight:600;z-index:99999;box-shadow:0 4px 20px rgba(0,0,0,.3);animation:demoToastIn .3s ease';
    document.body.appendChild(t);
    setTimeout(function() { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(function() { if (t.parentNode) t.remove(); }, 300); }, 2000);
}
// ── Tab switching helper ──
function switchTab(tabId) {
    var btn = document.querySelector('#tabNav button[data-tab="' + tabId + '"]');
    if (btn) btn.click();
}

// Global API token for all AJAX calls (works even without session cookie)
var _apiToken = <?php echo json_encode(generateCsrfToken()); ?>;
// Helper: append token to any API URL
function apiUrl(url) {
    return url + (url.indexOf('?') === -1 ? '?' : '&') + 'token=' + encodeURIComponent(_apiToken);
}
// Keep all CSRF hidden inputs up-to-date (prevent 403 on form POSTs after long sessions)
function updateAllCsrfInputs(token) {
    var inputs = document.querySelectorAll('input[name="csrf_token"]');
    for (var i = 0; i < inputs.length; i++) { inputs[i].value = token; }
}
</script>
<script>
// ── Detección global de sesión expirada (401) ──
// Si cualquier API devuelve 401, redirige al login automáticamente.
(function() {
    var _origFetch = window.fetch;
    window.fetch = function(url, init) {
        return _origFetch.call(window, url, init).then(function(r) {
            if (r.status === 401) { window.location.href = 'login'; }
            return r;
        });
    };
})();
</script>
<script src="assets/chat.js?v=20260629_1"></script>
<script src="assets/tutorial.js?v=20260825_1"></script>
<script>
var _csrf = <?php echo json_encode(generateCsrfToken()); ?>;
// ── Keepalive de sesión + CSRF refresh: ping cada 5 min ──
setInterval(function() {
    fetch(apiUrl('api/csrf-token.php'), {credentials: 'same-origin'}).then(function(r) {
        if (r.status === 401) { window.location.href = 'login'; return; }
        return r.json();
    }).then(function(d) {
        if (d && d.ok && d.token) {
            if (typeof _apiToken !== 'undefined') _apiToken = d.token;
            if (typeof _csrf !== 'undefined') _csrf = d.token;
            updateAllCsrfInputs(d.token);
        }
    }).catch(function(){});
}, 300000);

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
    fetch(apiUrl('api/lines.php?action=list'), {credentials: 'same-origin'}).then(r=>r.json()).then(d=>{
        if (!d.ok) return;
        var html = '';
        if (d.lines.length === 0) {
            html = '<p class="empty-state">No hay líneas configuradas. Añade tu primer número arriba.</p>';
        } else {
            html = '<div class="table-responsive"><table class="memory-table" style="font-size:.83rem"><thead><tr><th>Línea</th><th>Teléfono</th><th>Puerto</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
            d.lines.forEach(function(l) {
                var st = l.health_status || '?';
                var statusIcon = {'WORKING':'🟢 ONLINE','STARTING':'🟡 CONECTANDO','SCAN_QR':'📱 QR PENDIENTE','starting':'🟡 ARRANCANDO','FAILED':'🔴 FALLIDA','STOPPED':'⏸️ PARADA','down':'🔴 CAÍDA','pending':'⚪ PENDIENTE','unknown':'⚪ DESCONOCIDO'}[st] || ('⚪ '+(st||'?'));
                var phoneDisp = l.health_phone ? l.health_phone : (l.last9||l.phone);
                html += '<tr><td><strong>'+escHtml(l.label)+'</strong></td><td class="mono">'+escHtml(phoneDisp)+'</td><td>'+l.port+'</td><td>'+statusIcon+'</td>';
                html += '<td style="white-space:nowrap">';
                if (st === 'WORKING') {
                    html += '<span style="color:var(--ok);font-size:.8rem;margin-right:6px">✅ Conectada</span>';
                    html += '<button type="button" onclick="showTest('+l.id+')" class="btn btn-sm" style="background:var(--info);color:var(--text-bright);margin-right:3px">Test</button>';
                    html += '<button type="button" onclick="checkLineStatus('+l.id+')" class="btn btn-sm" style="background:var(--ok);color:var(--text-bright);margin-right:3px">✓</button>';
                } else {
                    html += '<button type="button" onclick="showQR('+l.id+')" class="btn btn-sm btn-primary" style="margin-right:3px">QR</button>';
                }
                html += '<button type="button" onclick="deleteLine('+l.id+')" class="btn btn-sm btn-danger">🗑</button>';
                html += '</td></tr>';
            });
            html += '</tbody></table></div>';
        }
        document.getElementById('lines-container').innerHTML = html;
    }).catch(function(){ document.getElementById('lines-container').innerHTML = '<p class="empty-state" style="color:var(--danger)">Error al cargar líneas</p>'; });
}
function addLine() {
    if (IS_DEMO) { showDemoToast(); return; }
    var phone = document.getElementById('new-line-phone').value.trim();
    var label = document.getElementById('new-line-label').value.trim();
    if (!phone) return alert('Introduce un número de teléfono');
    var statusEl = document.getElementById('add-line-status');
    var btn = document.getElementById('btn-create-line');
    btn.disabled = true; btn.textContent = '⏳ Creando...';
    statusEl.textContent = '⏳ Creando instancia WAHA... (puede tardar 10-15 segundos)';
    var fd = new FormData(); fd.append('phone', phone); fd.append('label', label); fd.append('csrf_token', _csrf);
    fetch('api/lines.php?action=add', {method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(d=>{
        if (d.extra_line_payment) {
            // Extra line requires payment — redirect to payment page
            showToast('💡 Línea extra: pago inicial prorrateado ' + Number(d.price).toFixed(2) + '€. Después +' + Number(d.weekly_price || 10).toFixed(2) + '€/sem. Redirigiendo al pago...', 'info');
            setTimeout(function(){ window.location.href = 'pago'; }, 1000);
        } else if (d.ok) {
            statusEl.textContent = '✅ Instancia creada en puerto '+(d.line?d.line.port:'?')+'. Usa el botón QR para vincular WhatsApp.';
            document.getElementById('new-line-phone').value='';
            document.getElementById('new-line-label').value='';
            loadLines();
            showToast('✅ Línea creada correctamente', 'success');
        } else {
            statusEl.innerHTML = d.trial_limit
                ? '❌ ' + escHtml(d.error) + ' <a href="pago" style="color:var(--accent);font-weight:600;text-decoration:underline">Activar plan →</a>'
                : '❌ ' + escHtml(d.error||'Error al crear');
            showToast('❌ '+(d.error||'Error al crear la línea'), 'error');
        }
        btn.disabled = false; btn.textContent = 'Crear línea';
    }).catch(function(){
        statusEl.textContent = '❌ Error de conexión';
        showToast('❌ Error de conexión al crear la línea', 'error');
        btn.disabled = false; btn.textContent = 'Crear línea';
    });
}
var currentQrLineId = 0;
function showQR(lineId) {
    currentQrLineId = lineId;
    document.getElementById('qr-modal').classList.add('open');
    document.getElementById('qr-image').src = '';
    document.getElementById('qr-status').textContent = 'Cargando QR...';
    fetchQR(lineId);
}
function fetchQR(lineId) {
    fetch(apiUrl('api/lines.php?action=qr&line_id='+lineId), {credentials: 'same-origin'}).then(r=>r.json()).then(d=>{
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
    if (IS_DEMO) { showDemoToast(); return; }
    if (!currentQrLineId) return;
    document.getElementById('qr-status').textContent = 'Generando nuevo QR...';
    fetchQR(currentQrLineId);
}
function checkLineStatus(lineId) {
    fetch(apiUrl('api/lines.php?action=status'), {credentials: 'same-origin'}).then(r=>r.json()).then(d=>{
        if (d.ok && d.statuses) {
            var st = d.statuses[lineId] || 'unknown';
            alert('Estado de la línea: ' + st);
        }
    });
}

function showTest(lineId) {
    document.getElementById('test-modal').classList.add('open');
    document.getElementById('test-line-id').value = lineId;
    document.getElementById('test-phone').value = '';
    document.getElementById('test-result').textContent = '';
}
function sendTestMessage() {
    if (IS_DEMO) { showDemoToast(); return; }
    var lineId = document.getElementById('test-line-id').value;
    var phone = document.getElementById('test-phone').value.trim();
    if (!phone) return alert('Introduce un número de teléfono');
    var fd = new FormData(); fd.append('line_id', lineId); fd.append('test_phone', phone); fd.append('csrf_token', _csrf);
    document.getElementById('test-result').textContent = 'Enviando...';
    fetch('api/lines.php?action=test', {method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(d=>{
        if (d.ok) {
            document.getElementById('test-result').textContent = '✅ Mensaje enviado (HTTP '+d.http_code+')';
        } else {
            var err = d.error || ('HTTP '+d.http_code);
            document.getElementById('test-result').textContent = '❌ '+err;
        }
    });
}
function deleteLine(lineId) {
    if (IS_DEMO) { showDemoToast(); return; }
    if (!confirm('¿Eliminar esta línea? Se desvinculará del bot.')) return;
    var fd = new FormData(); fd.append('line_id', lineId); fd.append('csrf_token', _csrf);
    fetch('api/lines.php?action=delete', {method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(d=>{
        if (d.ok) { loadLines(); showToast('🗑️ Línea eliminada', 'success'); }
        else { showToast('❌ Error: '+(d.error||'Desconocido'), 'error'); }
    }).catch(function(e) {
        showToast('❌ Error de conexión al eliminar la línea', 'error');
    });
}

// ── Polling estado líneas (cada 60s) ──
var linePollInterval;
function startLinePolling() { linePollInterval = setInterval(function() {
    if (document.getElementById('tab-lineas') && document.getElementById('tab-lineas').classList.contains('active')) {
        loadLines();
    }
}, 60000); }
startLinePolling();

// ── Chicas ──
var _allGirls = [];
// Helper: convierte URLs compartir.site (shortlink) a URL directa de imagen
function getDirectImageUrl(url) {
    if (!url) return '';
    var m = url.match(/^https?:\/\/(?:[^\/]*\.)?compartir\.site\/([a-z0-9]+)\/?$/i);
    if (m) return 'https://compartir.site/' + m[1] + '/' + m[1] + '.jpg';
    return url;
}
function loadGirls() {
    var container = document.getElementById('girls-container');
    if (!container) return;
    container.innerHTML = '<p class="empty-state">Cargando chicas...</p>';
    fetch(apiUrl('api/girls.php?action=list'), {credentials: 'same-origin'}).then(r=>r.json()).then(d=>{
        if (!d.ok) { container.innerHTML = '<p style="color:var(--danger);text-align:center;padding:20px">Error al cargar chicas.</p>'; return; }
        _allGirls = d.girls;
        var html = '';
        if (d.girls.length === 0) {
            html = '<p class="empty-state">No hay chicas configuradas. Añade la primera arriba.</p>';
        } else {
            html = '<div class="girls-grid">';
            d.girls.forEach(function(g) {
                var fotoCount = (g.fotos||[]).length;
                var heroImg = (g.fotos||[]).length > 0 ? g.fotos[0] : '';
                var displayImg = getDirectImageUrl(heroImg);
                var heroStyle = displayImg ? 'background-image:url('+escHtml(displayImg)+');background-size:cover;background-position:center top' : '';
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
    if (IS_DEMO) { showDemoToast(); return; }
    var id = document.getElementById('girl-edit-id').value;
    var nombre = document.getElementById('girl-nombre').value.trim();
    if (!nombre) { showToast('El nombre es obligatorio.', 'warning'); return; }
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
    fetch('api/girls.php?action=save', {method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(d=>{
        if (d.ok) {
            document.getElementById('girl-edit-id').value='';
            document.getElementById('girl-nombre').value='';
            document.getElementById('girl-desc').value='';
            document.getElementById('girl-form-title').textContent='➕ Nueva chica';
            clearPhotoPreviews();
            loadGirls();
            showToast('✅ Chica guardada correctamente', 'success');
        } else { showToast('❌ Error: '+(d.error||'Desconocido'), 'error'); }
        if (btn) { btn.disabled = false; btn.textContent = '💾 Guardar'; }
    }).catch(function(){
        showToast('❌ Error de conexión al guardar la chica', 'error');
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
            '<img src="'+getDirectImageUrl(url)+'" style="object-fit:cover;width:100%;height:100%" draggable="false">' +
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
    if (IS_DEMO) { showDemoToast(); return; }
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
    if (IS_DEMO) { showDemoToast(); return; }
    var fd = new FormData();
    fd.append('id', girlId);
    fd.append('order', JSON.stringify(fotos));
    fd.append('csrf_token', (typeof _csrf !== 'undefined' ? _csrf : ''));
    fetch('api/girls.php?action=reorder_photos', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function(r) { return r.json(); }).then(function(d) {
        if (!d.ok) {
            // Reload to recover correct state on error
            loadGirls();
            // Refresh edit form if still editing
            var currentEditId = document.getElementById('girl-edit-id').value;
            if (currentEditId) {
                return fetch(apiUrl('api/girls.php?action=list'), {credentials: 'same-origin'}).then(function(r) { return r.json(); }).then(function(data) {
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
    if (IS_DEMO) { showDemoToast(); return; }
    if (!confirm('¿Eliminar esta foto?')) return;
    var fd = new FormData();
    fd.append('id', girlId);
    fd.append('photo_index', photoIndex);
    fd.append('csrf_token', (typeof _csrf!=='undefined'?_csrf:''));
    fetch('api/girls.php?action=remove_photo', {method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(d=>{
        if (!d.ok) { alert('Error: '+(d.error||'No se pudo eliminar la foto')); return; }
        // Reload girls data and refresh both the card grid and the edit form
        return fetch(apiUrl('api/girls.php?action=list'), {credentials: 'same-origin'}).then(r=>r.json());
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
    if (IS_DEMO) { showDemoToast(); return; }
    var fd = new FormData();
    fd.append('id', id);
    fd.append('csrf_token', (typeof _csrf!=='undefined'?_csrf:''));
    fetch('api/girls.php?action=toggle', {method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(d=>{
        if (d.ok) { loadGirls(); } else { showToast('❌ Error: '+(d.error||'Desconocido'), 'error'); }
    }).catch(function(e){
        showToast('❌ Error de conexión al cambiar estado', 'error');
    });
}
function deleteGirl(id) {
    if (IS_DEMO) { showDemoToast(); return; }
    if (!confirm('¿Eliminar esta chica? Esta acción no se puede deshacer.')) return;
    var fd = new FormData();
    fd.append('id', id);
    fd.append('csrf_token', (typeof _csrf!=='undefined'?_csrf:''));
    fetch('api/girls.php?action=delete', {method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(d=>{
        if (d.ok) { loadGirls(); showToast('🗑️ Chica eliminada', 'success'); }
        else { showToast('❌ Error: '+(d.error||'No se pudo eliminar'), 'error'); }
    }).catch(function(e){
        showToast('❌ Error de conexión al eliminar', 'error');
    });
}

// ── Estados ──
function loadEstadosConfig() {
    fetch(apiUrl('api/estados.php?action=config'), {credentials: 'same-origin'}).then(r=>r.json()).then(d=>{
        if (!d.ok) return;
        var c = d.config;
        document.getElementById('estados-enabled').checked = !!c.enabled;
        document.getElementById('estados-freq-tipo').value = c.frecuencia_tipo || 'cada_x_horas';
        document.getElementById('estados-freq-valor').value = c.frecuencia_valor || 6;
        // Populate format dropdown from API
        if (c.format_options) {
            var sel = document.getElementById('estados-formato');
            var current = c.formato || 'mix_aleatorio';
            sel.innerHTML = '<option value="mix_aleatorio">🎲 Mix aleatorio (recomendado)</option>';
            // Group formats
            var individuales = ['chica_del_dia','tentacion_del_dia','dulce_prohibido','ven_ya','susurro','confesion',
                'frase_del_dia','solo_valientes','cita_a_ciegas','regalo_sorpresa','amiga_recomienda','la_nueva'];
            var varias = ['chicas_de_hoy','duo_sexy','estrella_grupo','trio_tentador','puertas_abiertas',
                'antojos','el_equipo','frescas','catalogo_rapido','juego_parejas','el_casting','modo_finde'];
            var especial = ['oferta_flash'];
            sel.innerHTML += '<optgroup label="── Individual (1 chica) ──"></optgroup>';
            var og1 = sel.querySelector('optgroup:last-of-type');
            individuales.forEach(function(f){ if(c.format_options[f]){ og1.innerHTML += '<option value="'+f+'">'+escHtml(c.format_options[f])+'</option>'; } });
            sel.innerHTML += '<optgroup label="── Varias chicas ──"></optgroup>';
            var og2 = sel.querySelector('optgroup:last-of-type');
            varias.forEach(function(f){ if(c.format_options[f]){ og2.innerHTML += '<option value="'+f+'">'+escHtml(c.format_options[f])+'</option>'; } });
            sel.innerHTML += '<optgroup label="── Especial ──"></optgroup>';
            var og3 = sel.querySelector('optgroup:last-of-type');
            especial.forEach(function(f){ if(c.format_options[f]){ og3.innerHTML += '<option value="'+f+'">'+escHtml(c.format_options[f])+'</option>'; } });
            sel.value = current;
        } else {
            document.getElementById('estados-formato').value = c.formato || 'mix_aleatorio';
        }
        document.getElementById('estados-hora-inicio').value = c.hora_inicio || '08:00';
        document.getElementById('estados-hora-fin').value = c.hora_fin || '23:00';
        var lcb = document.getElementById('estados-lines-checkboxes');
        lcb.innerHTML = (c.available_lines||[]).map(function(l){
            var checked = (c.lineas||[]).indexOf(l.id) !== -1 ? 'checked' : '';
            return '<label class="checkbox-label" style="font-size:.78rem"><input type="checkbox" value="'+l.id+'" '+checked+' onchange="saveEstadosConfig()"> '+escHtml(l.label||l.last9)+'</label>';
        }).join('');
    });
}
function saveEstadosConfig() {
    if (IS_DEMO) { showDemoToast(); return; }
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
    showToast('⏳ Guardando configuración...', 'info');
    fetch(apiUrl('api/estados.php?action=config'), {method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(d=>{
        if (d.ok) {
            showToast('✅ Configuración guardada correctamente', 'success');
        } else {
            showToast('❌ Error al guardar: '+(d.error||'desconocido'), 'error');
        }
    }).catch(function(e){
        showToast('❌ Error de red: '+(e.message||'sin conexión'), 'error');
    });
}
function publishEstado() {
    if (IS_DEMO) { showDemoToast(); return; }
    showToast('⏳ Publicando estado...', 'info');
    var fd = new FormData(); fd.append('csrf_token', _csrf);
    fetch(apiUrl('api/estados.php?action=publish'), {method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(d=>{
        if (d.ok) {
            var oks = d.results.filter(function(r){return r.ok;}).length;
            showToast('✅ Publicado en '+oks+'/'+d.results.length+' líneas', 'success');
            loadEstadosHistory();
        } else {
            showToast('❌ '+(d.error||'Error al publicar'), 'error');
        }
    }).catch(function(e){
        showToast('❌ Error de red: '+(e.message||'sin conexión'), 'error');
    });
}
function loadEstadosHistory() {
    fetch(apiUrl('api/estados.php?action=history'), {credentials: 'same-origin'}).then(r=>r.json()).then(d=>{
        if (!d.ok) return;
        var html = d.log.slice(0,10).map(function(e){
            var date = e.published_at ? new Date(e.published_at).toLocaleString('es-ES') : '?';
            return '<div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-sm);padding:8px 12px;margin-bottom:4px;font-size:.8rem"><strong>'+date+'</strong> — '+escHtml(e.formato)+'<br><span style="color:var(--text-muted)">'+escHtml((e.texto||'').substring(0,80))+'</span></div>';
        }).join('');
        document.getElementById('estados-history').innerHTML = html || '<p style="color:var(--text-muted);text-align:center;padding:10px">Sin publicaciones</p>';
    });
}

// ── Helpers ──
function escHtml(s) { var d=document.createElement('div'); d.textContent=s; return d.innerHTML; }

// ── Toast Notification System ──
function showToast(message, type) {
    type = type || 'info';
    var container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }
    var icons = {success:'✅', error:'❌', warning:'⚠️', info:'ℹ️', loading:'⏳'};
    var toast = document.createElement('div');
    toast.className = 'toast toast--' + type;
    toast.innerHTML = '<span class="toast-icon">'+(icons[type]||icons.info)+'</span><span class="toast-message">'+escHtml(message)+'</span>';
    container.appendChild(toast);
    // Auto-dismiss: success/warning/info 3.5s, error 6s
    var duration = type === 'error' ? 6000 : 3500;
    setTimeout(function(){
        toast.classList.add('toast--removing');
        setTimeout(function(){ if (toast.parentNode) toast.remove(); }, 300);
    }, duration);
}

// ── Clientes ──
function loadClientes() {
    fetch(apiUrl('api/clientes.php?action=list'), {credentials: 'same-origin'}).then(r=>r.json()).then(d=>{
        if (!d.ok) return;
        var html = '';
        if (d.leads.length === 0) {
            html = '<p class="empty-state">No hay leads registrados todavía.</p>';
        } else {
            html = '<div style="display:flex;gap:8px;margin-bottom:12px"><span style="color:var(--text-muted);font-size:.8rem">'+d.total+' leads</span></div>';
            html += '<div class="table-responsive"><table class="memory-table" style="font-size:.82rem"><thead><tr><th>Fecha</th><th>Teléfono</th><th>Línea</th><th>Confianza</th><th>¿Llegó?</th></tr></thead><tbody>';
            d.leads.forEach(function(l){
                var arrivedIcon = l.arrived ? '✅ Sí' : '❌ No';
                var arrivedBtn = l.arrived
                    ? '<button type="button" onclick="markLeadArrived(\''+escHtml(l.thread_id)+'\',false)" class="btn btn-sm" style="background:var(--ok-bg);color:var(--ok)">✅ Sí</button>'
                    : '<button type="button" onclick="markLeadArrived(\''+escHtml(l.thread_id)+'\',true)" class="btn btn-sm btn-warning">Marcar llegó</button>';
                var confColor = parseInt(l.confidence) > 80 ? 'color:var(--ok)' : (parseInt(l.confidence) > 50 ? 'color:var(--warn)' : 'color:var(--text-muted)');
                html += '<tr><td class="mono">'+escHtml(l.ts)+'</td><td class="mono">'+escHtml(l.phone)+'</td><td>'+escHtml(l.line_label)+'</td><td style="'+confColor+'">'+l.confidence+'</td><td>'+arrivedBtn+'</td></tr>';
            });
            html += '</tbody></table></div>';
        }
        document.getElementById('clientes-table-container').innerHTML = html;
    });
}
function markLeadArrived(threadId, arrived) {
    if (IS_DEMO) { showDemoToast(); return; }
    var fd = new FormData(); fd.append('csrf_token', _csrf); fd.append('thread_id', threadId);
    if (arrived) fd.append('arrived', '1'); else fd.append('arrived', '0');
    fetch('api/clientes.php?action=mark_arrived', {method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(d=>{
        if (d.ok) { loadClientes(); showToast('✅ Lead actualizado', 'success'); }
        else { showToast('❌ Error: '+(d.error||'Desconocido'), 'error'); }
    });
}

// ── Aprendizaje ──
function loadAprendizaje() {
    // Stats + playbook
    fetch(apiUrl('api/aprendizaje.php?action=stats'), {credentials: 'same-origin'}).then(function(r){return r.json();}).then(function(d){
        if (!d.ok) return;
        var s = d.stats;

        // Stats cards
        var html = '';
        function card(num, label, color) {
            html += '<div style="flex:1;min-width:105px;background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:14px;text-align:center">';
            html += '<div style="font-size:1.5rem;font-weight:700;color:'+color+'">'+num+'</div>';
            html += '<div style="font-size:.7rem;color:var(--text-muted);margin-top:2px">'+label+'</div></div>';
        }
        card(s.total_classified, 'Analizadas', 'var(--primary)');
        card(s.leads, 'Leads', 'var(--ok)');
        card(s.mareador, 'Mareadores', 'var(--warn)');
        card(s.lead_ghosted, 'Ghosteos', 'var(--danger)');
        card(s.pending_review, 'Pendientes', 'var(--info)');
        document.getElementById('learning-stats').innerHTML = html;

        // Playbook
        var pb = document.getElementById('learning-playbook');
        var ps = document.getElementById('playbook-status');
        if (s.playbook_exists) {
            ps.innerHTML = '<span style="color:var(--ok)">✅ Activo</span> <span style="color:var(--text-muted);font-size:.78rem;margin-left:8px">Actualizado: '+escHtml(s.playbook_updated)+'</span>';
        } else {
            ps.innerHTML = '<span style="color:var(--danger)">❌ No generado</span>';
        }
        pb.style.display = 'block';
    });

    // Outcomes
    fetch(apiUrl('api/aprendizaje.php?action=outcomes'), {credentials: 'same-origin'}).then(function(r){return r.json();}).then(function(d){
        if (!d.ok) return;
        document.getElementById('outcomes-count').textContent = d.total + ' conversaciones clasificadas';
        var tbody = document.getElementById('outcomes-tbody');
        if (d.outcomes.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="padding:20px;text-align:center;color:var(--text-muted)">No hay conversaciones clasificadas todavía.</td></tr>';
            return;
        }
        var oc = {
            'lead_probable':    'var(--info)',
            'lead_confirmado':  'var(--ok)',
            'lead_ghosted':     'var(--warn)',
            'mareador':         '#f59e0b',
            'hostil':           'var(--danger)',
            'muerta':           'var(--text-muted)',
            'indeterminado':    'var(--text-muted)'
        };
        var html = '';
        d.outcomes.forEach(function(o){
            var color = oc[o.outcome] || 'var(--text-muted)';
            var label = o.human_confirmed ? (o.outcome + ' ✓') : o.outcome;
            var btns = '';
            if (o.outcome === 'lead_probable' || o.outcome === 'lead_detectado') {
                btns = '<button class="btn btn-sm" onclick="confirmOutcome(\''+escHtml(o.thread_id)+'\',\'lead_confirmado\')" style="background:var(--ok-bg);color:var(--ok);margin:2px">✅ Vino</button>' +
                       '<button class="btn btn-sm" onclick="confirmOutcome(\''+escHtml(o.thread_id)+'\',\'lead_ghosted\')" style="background:var(--warn-bg);color:var(--warn);margin:2px">❌ No vino</button>';
            }
            html += '<tr style="border-bottom:1px solid var(--border)">';
            html += '<td style="padding:8px;font-family:monospace;font-size:.75rem">'+escHtml(o.phone)+'</td>';
            html += '<td style="padding:8px"><span style="color:'+color+';font-weight:600">'+escHtml(label)+'</span></td>';
            html += '<td style="padding:8px">'+(o.message_count||0)+'</td>';
            html += '<td style="padding:8px;font-size:.75rem;color:var(--text-muted)">'+escHtml((o.classified_at||'').substring(0,10))+'</td>';
            html += '<td style="padding:8px">'+btns+'</td>';
            html += '</tr>';
        });
        tbody.innerHTML = html;
    });
}

function confirmOutcome(threadId, newOutcome) {
    if (IS_DEMO) { showDemoToast(); return; }
    fetch(apiUrl('api/aprendizaje.php?action=confirm_outcome'), {
        method: 'POST',
        body: JSON.stringify({thread_id: threadId, outcome: newOutcome}),
        credentials: 'same-origin'
    }).then(function(r){return r.json();    }).then(function(d){ if (d.ok) { loadAprendizaje(); showToast('✅ Clasificación actualizada', 'success'); } });
}

function viewPlaybook() {
    var preview = document.getElementById('playbook-preview');
    if (preview.style.display === 'block') { preview.style.display = 'none'; return; }
    preview.style.display = 'block';
    preview.textContent = 'Cargando…';
    fetch(apiUrl('api/aprendizaje.php?action=playbook'), {credentials: 'same-origin'}).then(function(r){return r.json();}).then(function(d){
        if (d.ok) preview.textContent = d.content || '(playbook vacío)';
        else preview.textContent = 'Error: '+(d.error||'desconocido');
    }).catch(function(){ preview.textContent = 'Error al cargar el playbook.'; });
}

// ── Mensajes (via ChatApp modal) ──
// The WhatsApp-style chat is loaded from chat.js / chat.css. 
// Clicking the "Abrir Chat" button calls ChatApp.open().
// No additional init needed here — ChatApp handles everything.

// ── Estadísticas ──
function loadEstadisticas() {
    var container = document.getElementById('estadisticas-container');
    if (!container) return;
    container.innerHTML = '<div style="text-align:center;padding:40px"><p style="color:var(--text-muted);font-size:1.1rem">⏳ Cargando estadísticas…</p></div>';

    var controller = new AbortController();
    var timeoutId = setTimeout(function() { controller.abort(); }, 15000);

    fetch(apiUrl('api/stats.php'), {credentials: 'same-origin', signal: controller.signal})
    .then(function(r) {
        clearTimeout(timeoutId);
        if (!r.ok) { throw new Error('HTTP ' + r.status + ' ' + r.statusText); }
        return r.json();
    })
    .then(function(d) {
        if (!d.ok) {
            var errMsg = d.error || 'Error desconocido';
            container.innerHTML = '<p class="empty-state empty-state--large">⚠️ Error al cargar estadísticas: ' + escHtml(errMsg) + '</p>';
            if (window.console) console.warn('[stats] API returned ok:false:', errMsg);
            return;
        }
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
        html += '<div class="stat-card"><div class="stat-num stat-num--info">'+s.conversations_total+'</div><div class="stat-label">💬 Conversaciones</div><div class="stat-sub">'+s.conversations_today+' hoy</div></div>';
        html += '<div class="stat-card"><div class="stat-num stat-num--ok">'+s.leads_total+'</div><div class="stat-label">🎯 Leads</div><div class="stat-sub">'+s.leads_today+' hoy</div></div>';
        html += '<div class="stat-card"><div class="stat-num stat-num--accent2">'+s.leads_arrived+'</div><div class="stat-label">✅ Llegaron</div><div class="stat-sub">'+s.leads_pending+' pendientes</div></div>';
        html += '<div class="stat-card"><div class="stat-num stat-num--accent">'+s.girls_active+'</div><div class="stat-label">👩 Chicas</div><div class="stat-sub">Activas</div></div>';
        html += '<div class="stat-card"><div class="stat-num stat-num--accent2">'+s.lines_active+'</div><div class="stat-label">📱 Líneas</div><div class="stat-sub">Vinculadas</div></div>';
        html += '<div class="stat-card"><div class="stat-num stat-num--ok">'+s.conversations_week+'</div><div class="stat-label">📅 Esta semana</div><div class="stat-sub">Conversaciones</div></div>';
        html += '</div>';
        html += '</div></div>';

        // 7-day chart
        var maxVal = Math.max.apply(null, d.stats.daily_graph.map(function(g){ return Math.max(g.conversations, g.leads); })) || 1;
        var colors = ['var(--info)','var(--accent)','var(--ok)','var(--money)','var(--accent2)','var(--accent2)','var(--warn)'];
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
        html += '<div><span style="color:var(--text-muted)">Efectividad:</span> <strong class="rec-label">'+(s.leads_arrived>0&&s.leads_total>0?(s.leads_arrived/s.leads_total*100).toFixed(0):0)+'%</strong> de leads llegaron</div>';
        html += '<div><span style="color:var(--text-muted)">Promedio diario:</span> <strong style="color:var(--info)">'+(s.leads_week>0?(s.leads_week/7).toFixed(1):0)+'</strong> leads/día</div>';
        html += '</div></div>';

        container.innerHTML = html;
    })
    .catch(function(err) {
        clearTimeout(timeoutId);
        if (window.console) console.error('[stats] Fetch error:', err);
        if (err && err.name === 'AbortError') {
            container.innerHTML = '<div style="text-align:center;padding:40px"><p style="color:var(--warn);font-size:1.1rem">⏱️ La carga de estadísticas tardó demasiado</p><p style="color:var(--text-muted);margin-top:4px">Inténtalo de nuevo en unos segundos.</p><button class="btn btn-primary" style="margin-top:12px" onclick="loadedTabs[\'tab-estadisticas\']=false;loadEstadisticas();">Reintentar</button></div>';
        } else {
            container.innerHTML = '<div style="text-align:center;padding:40px"><p style="color:var(--warn);font-size:1.1rem">⚠️ No se pudieron cargar las estadísticas</p><p style="color:var(--text-muted);margin-top:4px;font-size:.85rem">Error: ' + escHtml((err && err.message) || 'Conexión fallida') + '</p><button class="btn btn-primary" style="margin-top:12px" onclick="loadedTabs[\'tab-estadisticas\']=false;loadEstadisticas();">Reintentar</button></div>';
        }
    });
}

// ── Registro (Logs) ──
function loadRegistro() {
    fetch(apiUrl('api/logs.php'), {credentials: 'same-origin'}).then(r=>r.json()).then(d=>{
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

// ── Dashboard refresh ──
function loadDashboard() {
    fetch(apiUrl('api/stats.php'), {credentials: 'same-origin'})
    .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(function(d) {
        if (!d.ok) return;
        var s = d.stats;
        var p = d.setup;

        // ── Progress bar ──
        var progEl = document.getElementById('dashboard-progress');
        if (progEl) {
            if (p.progress_pct < 100) {
                progEl.innerHTML =
                    '<div class="progress-bar-wrap">' +
                    '<div class="progress-bar">' +
                    '<span>⚙️ Configuración: ' + p.progress_done + '/' + p.progress_total + '</span>' +
                    '<div class="progress-bar-track">' +
                    '<div class="progress-bar-fill" style="width:' + p.progress_pct + '%"></div>' +
                    '</div>' +
                    '<span>' + p.progress_pct + '%</span>' +
                    '</div></div>';
            } else {
                progEl.innerHTML = '';
            }
        }

        // ── Build dynamic dashboard HTML ──
        var botStatusClass = p.bot_status === 'start' ? 'status-on' : (p.bot_status === 'stop' ? 'status-off' : 'status-unknown');
        var botStatusLabel = p.bot_status === 'start' ? 'ENCENDIDO' : (p.bot_status === 'stop' ? 'APAGADO' : 'DESCONOCIDO');

        var heroHtml = '';
        if (p.progress_pct < 100) {
            heroHtml =
                '<div class="dashboard-hero">' +
                '<div class="dashboard-hero-inner">' +
                '<span class="dh-emoji">🚀</span>' +
                '<div class="dh-body">' +
                '<strong class="dh-title">Completa estos simples pasos para poner en marcha tu bot ya mismo</strong>' +
                '<p class="dh-desc">Muy fácil de usar. <strong>CasaWasap</strong>, tu asistente que te ayuda a comunicarte y fidelizar clientes.</p>' +
                '</div></div></div>';
        } else {
            heroHtml =
                '<div class="dashboard-hero dashboard-hero--done">' +
                '<div class="dashboard-hero-inner">' +
                '<span class="dh-emoji">✅</span>' +
                '<div class="dh-body">' +
                '<strong class="dh-title">¡Todo listo! Has completado todos los pasos de configuración</strong>' +
                '<p class="dh-desc">Tu bot ya está en marcha. Si quieres modificar algo, vuelve a las pestañas de configuración cuando quieras.</p>' +
                '</div></div></div>';
        }

        var cards = [
            {id:'lineas', icon:'📱', title:'Vincular WhatsApp', ok: p.lines_linked, okText:'Conectado', failText:'Pendiente', hint:'Vincula tus números en Líneas', tab:'tab-lineas'},
            {id:'tarifas', icon:'💰', title:'Configurar tarifas', ok: p.tarifas_configured, okText:'Configuradas', failText:'Sin definir', hint:'Define tus precios en Personalidad', tab:'tab-personalidad'},
            {id:'chicas', icon:'👩', title:'Chicas activas', ok: p.girls_active_bool, okText: s.girls_active + ' activa' + (s.girls_active !== 1 ? 's' : ''), failText:'Ninguna', hint:'Añade tu catálogo en Chicas', tab:'tab-chicas'},
            {id:'avisos', icon:'📬', title:'Configurar avisos', ok: p.notifications_configured, okText:'Activados', failText:'Sin avisos', hint:'Configura Telegram o WhatsApp en Notificaciones', tab:'tab-clientes'}
        ];
        var cardsHtml = '<div class="setup-grid">';
        cards.forEach(function(c) {
            var cls = c.ok ? 'setup-card--ok' : 'setup-card--fail';
            var badgeCls = c.ok ? 'setup-badge--ok' : 'setup-badge--fail';
            cardsHtml +=
                '<div class="setup-card ' + cls + '" onclick="switchTab(\'' + c.tab + '\')">' +
                '<span class="setup-icon">' + c.icon + '</span>' +
                '<span class="setup-title">' + c.title + '</span>' +
                '<span class="setup-badge ' + badgeCls + '">' + (c.ok ? '✅ ' + c.okText : '❌ ' + c.failText) + '</span>';
            if (!c.ok) cardsHtml += '<span class="setup-hint">' + c.hint + '</span>';
            cardsHtml += '</div>';
        });
        cardsHtml += '</div>';

        var ctaHtml = '';
        if (p.progress_pct >= 100 && !p.bot_ever_on) {
            ctaHtml =
                '<div class="setup-cta">' +
                '<div class="cta-icon">🚀</div>' +
                '<div class="cta-title">¡Todo listo!</div>' +
                '<div class="cta-sub">Enciende tu bot con el botón ▶ ENCENDER de arriba y empieza a recibir clientes automáticamente.</div>' +
                '</div>';
        }

        var ratioVal = s.conversations_total > 0 ? Math.round(s.conversations_total / Math.max(s.leads_total, 1) * 10) / 10 : 0;

        var statsHtml =
            '<div class="card">' +
            '<h2>Estadísticas</h2>' +
            '<div class="stats-grid">' +
            '<div class="stat-card"><div class="stat-num stat-num--info">' + s.conversations_total + '</div><div class="stat-label">Conversaciones totales</div></div>' +
            '<div class="stat-card"><div class="stat-num stat-num--accent">' + s.conversations_today + '</div><div class="stat-label">Conversaciones hoy</div></div>' +
            '<div class="stat-card"><div class="stat-num stat-num--ok">' + s.leads_total + '</div><div class="stat-label">Leads totales</div></div>' +
            '<div class="stat-card"><div class="stat-num stat-num--ok">' + s.leads_today + '</div><div class="stat-label">Leads hoy</div></div>' +
            '<div class="stat-card"><div class="stat-num stat-num--accent2">' + s.lines_active + '</div><div class="stat-label">Líneas WhatsApp</div><div class="stat-sub">Vinculadas al bot</div></div>' +
            '<div class="stat-card"><div class="stat-num stat-num--accent2">' + s.girls_active + '</div><div class="stat-label">Chicas activas</div><div class="stat-sub">En catálogo</div></div>' +
            '<div class="stat-card"><div class="stat-num stat-num--ok">' + (s.leads_arrived || 0) + '</div><div class="stat-label">Clientes recibidos</div><div class="stat-sub">Marcados como llegados</div></div>' +
            '<div class="stat-card"><div class="stat-num stat-num--warn">' + ratioVal + '</div><div class="stat-label">Ratio conv/lead</div><div class="stat-sub">Conversaciones por lead</div></div>' +
            '</div></div>';

        var dynEl = document.getElementById('dashboard-dynamic');
        if (dynEl) {
            dynEl.innerHTML =
                '<div class="card bot-status-bar">' +
                '<span class="bot-emoji">🤖</span>' +
                '<span class="bot-label">Estado del Bot</span>' +
                '<span class="bot-indicator ' + botStatusClass + '"></span>' +
                '<span class="bot-status-text">' + botStatusLabel + '</span>' +
                '</div>' +
                heroHtml + cardsHtml + ctaHtml + statsHtml;
        }
    })
    .catch(function() { /* silent fail — server-rendered HTML is the fallback */ });
}

// ── Load data when tabs become active ──
var tabLoaders = {
    'tab-dashboard': loadDashboard,
    'tab-lineas': loadLines,
    'tab-chicas': loadGirls,
    'tab-estados': function() { loadEstadosConfig(); loadEstadosHistory(); },
    'tab-clientes': loadClientes,
    'tab-mensajes': function() { if (typeof ChatApp !== 'undefined') { ChatApp.open(); } }, // Always re-open (user may have closed)
    'tab-estadisticas': loadEstadisticas,
    'tab-learning': loadAprendizaje,
};
var loadedTabs = {};
document.querySelectorAll('#tabNav button[data-tab]').forEach(function(btn){
    btn.addEventListener('click', function(){
        var tabId = btn.getAttribute('data-tab');
        // Dashboard and Chat always refresh (data may have changed)
        if (tabId === 'tab-dashboard' || tabId === 'tab-mensajes') {
            if (tabLoaders[tabId]) tabLoaders[tabId]();
            return;
        }
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
            var bg = ok ? 'var(--ok-bg)' : 'var(--warn-bg)';
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

<?php if ($isDirectAccess): ?>
<!-- ── App Backdrop (for mobile popovers) ── -->
<div id="appBackdrop" hidden></div>

<!-- ── Mobile Bottom Area (CTA bar + nav) ── -->
<div class="mobile-bottom-area">
<?php if ($isDemo): ?>
<div class="demo-cta-bar">
    <span class="demo-cta-bar-icon">👁️</span>
    <div class="demo-cta-bar-body">
        <strong>¿Te gusta CasaWasap?</strong>
        <span>Pruébalo en tu casa 10 días gratis, sin tarjeta.</span>
    </div>
    <a href="https://casawasap.com/#registro" class="btn-demo-cta" target="_blank" rel="noopener">Empezar gratis →</a>
</div>
<script>document.body.classList.add('has-demo-cta');</script>
<?php endif; ?>

<!-- ── Mobile Bottom Navigation ── -->
<nav class="mobile-bottom-nav" id="mobileBottomNav">
    <!-- 1. Inicio -->
    <button type="button" class="mobile-nav-item is-active"
            data-activate-tab="tab-dashboard" aria-label="Inicio">
        <span class="mobile-nav-icon">📊</span>
        <span class="mobile-nav-label">Inicio</span>
    </button>

    <!-- 2. Chat -->
    <button type="button" class="mobile-nav-item"
            data-activate-tab="tab-mensajes" aria-label="Chat">
        <span class="mobile-nav-icon">💬</span>
        <span class="mobile-nav-label">Chat</span>
    </button>

    <!-- 3. Config básica → dropdown -->
    <button type="button" class="mobile-nav-item mobile-nav-drop"
            id="dropConfigBtn" aria-expanded="false" aria-controls="dropConfigPop" aria-label="Configuración">
        <span class="mobile-nav-icon">⚙️</span>
        <span class="mobile-nav-label">Config</span>
    </button>
    <div class="mobile-nav-popover" id="dropConfigPop" hidden>
        <button type="button" class="mobile-nav-popover-link" data-activate-tab="tab-personalidad">
            🎭 Personalidad
        </button>
        <button type="button" class="mobile-nav-popover-link" data-activate-tab="tab-lineas">
            📱 Líneas
        </button>
        <button type="button" class="mobile-nav-popover-link" data-activate-tab="tab-chicas">
            👩 Chicas
        </button>
        <button type="button" class="mobile-nav-popover-link" data-activate-tab="tab-clientes">
            🔔 Notificaciones
        </button>
    </div>

    <!-- 4. Fidelizar → dropdown -->
    <button type="button" class="mobile-nav-item mobile-nav-drop"
            id="dropFidelBtn" aria-expanded="false" aria-controls="dropFidelPop" aria-label="Fidelizar">
        <span class="mobile-nav-icon">💝</span>
        <span class="mobile-nav-label">Fideliz.</span>
    </button>
    <div class="mobile-nav-popover" id="dropFidelPop" hidden>
        <button type="button" class="mobile-nav-popover-link" data-activate-tab="tab-estados">
            📢 Estados
        </button>
        <button type="button" class="mobile-nav-popover-link" data-activate-tab="tab-seguimiento">
            📨 Seguimiento
        </button>
    </div>

    <!-- 5. Stats -->
    <button type="button" class="mobile-nav-item"
            data-activate-tab="tab-estadisticas" aria-label="Estadísticas">
        <span class="mobile-nav-icon">📈</span>
        <span class="mobile-nav-label">Stats</span>
    </button>

    <!-- 6. Ajustes y Salir → dropdown -->
    <button type="button" class="mobile-nav-item mobile-nav-drop"
            id="dropAjustesBtn" aria-expanded="false" aria-controls="dropAjustesPop" aria-label="Ajustes">
        <span class="mobile-nav-icon">🧠</span>
        <span class="mobile-nav-label">Ajustes</span>
    </button>
    <div class="mobile-nav-popover" id="dropAjustesPop" hidden>
        <button type="button" class="mobile-nav-popover-link" data-activate-tab="tab-learning">
            🧠 Aprendizaje
        </button>
        <button type="button" class="mobile-nav-popover-link" data-activate-tab="tab-ajustes">
            ⚙️ Ajustes
        </button>
        <a href="logout" class="mobile-nav-popover-link">🚪 Salir</a>
    </div>
</nav>
</div><!-- .mobile-bottom-area -->
<?php endif; ?>

</body>
</html>
