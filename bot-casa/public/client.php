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
    if (!file_exists($modeFilePath)) return 'unknown';
    $content = trim((string) @file_get_contents($modeFilePath));
    return in_array($content, ['start', 'stop'], true) ? $content : 'unknown';
}
$botMode = getBotMode();
$botStatusClass = $botMode === 'start' ? 'status-on' : ($botMode === 'stop' ? 'status-off' : 'status-unknown');
$botStatusLabel = $botMode === 'start' ? 'ENCENDIDO' : ($botMode === 'stop' ? 'APAGADO' : 'DESCONOCIDO');

// ── Stats ──
$leadsPath  = resolvePath('files.leads', 'data/leads.ndjson');
$memoryPath = resolvePath('files.session_memory', 'data/session_memory.ndjson');
$todayStr = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))->format('Y-m-d');
$leadsTotal = 0; $leadsToday = 0;
foreach (readNdjson($leadsPath) as $lead) {
    $leadsTotal++;
    if (str_starts_with((string) ($lead['ts'] ?? ''), $todayStr)) $leadsToday++;
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
<link rel="stylesheet" href="assets/style.css?v=20260603_3">
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
.stat-card { background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 16px 18px; text-align: center; }
.stat-card .stat-num { font-size: 2rem; font-weight: 800; }
.stat-card .stat-label { font-size: .78rem; color: var(--text-muted); margin-top: 4px; }
.stat-card .stat-sub { font-size: .7rem; color: rgba(255,255,255,.3); margin-top: 2px; }

.config-checklist { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 8px; }
.checklist-item { display: flex; align-items: center; gap: 6px; padding: 6px 12px; background: var(--bg-surface); border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: .8rem; }
.checklist-item .check-icon { font-size: .9rem; }
.check-ok { color: var(--ok); border-color: rgba(52,211,153,.3); }
.check-warn { color: var(--warn); border-color: rgba(251,191,36,.3); }

.section-guide { background: var(--info-bg); border: 1px solid var(--info); border-radius: var(--radius-sm); padding: 12px 16px; margin-bottom: 12px; font-size: .82rem; color: var(--info); }

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

<div class="tab-nav" id="tabNav">
    <button type="button" class="active" data-tab="tab-dashboard">📊 Inicio</button>
    <button type="button" data-tab="tab-mibot">🤖 Mi Bot</button>
    <button type="button" data-tab="tab-personalidad">🎭 Personalidad</button>
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
                <div class="stat-label">Líneas activas</div>
                <div class="stat-sub">WhatsApp vinculados</div>
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
                ['✅ Vincular WhatsApp', $linesConfigured, 'Configura tus líneas en la sección Líneas (próximamente).'],
                ['✅ Configurar tarifas', $promptConfigured, 'Define tus precios en la pestaña Personalidad.'],
                ['⏳ Personalidad completa', false, 'Ajusta el tono y estilo del bot.'],
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
            2. Vincula tus <strong>líneas de WhatsApp</strong> (próximamente)<br>
            3. Añade tu <strong>catálogo de chicas</strong> (próximamente)<br>
            4. ¡Enciende el bot y empieza a recibir clientes!
        </p>
    </div>
</div>

<!-- ===== TAB: Personalidad ===== -->
<div class="tab-content" id="tab-personalidad">
    <div class="section-guide">
        💡 <strong>Consejo:</strong> Cuanto más detallada sea la configuración, mejor responderá tu bot. Tómate tu tiempo para rellenar cada sección.
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
                        <select name="prompt[sections][estilo_tipo]" onchange="updateTonoPreview()">
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
                    <span class="tooltip-wrap">
                        <span class="tooltip-icon">?</span>
                        <span class="tooltip-box">Define tus precios. El bot usará EXACTAMENTE estos valores. Si cambias los precios, cambia esto.</span>
                    </span>
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
                    <span class="tooltip-wrap">
                        <span class="tooltip-icon">?</span>
                        <span class="tooltip-box">Zona general donde estás. El bot dirá esto cuando le pregunten. No pongas la dirección exacta, solo zona/barrio.</span>
                    </span>
                </h2>
                <div class="form-row">
                    <div class="form-group" style="flex:2">
                        <label>Zona / ciudad</label>
                        <input type="text" name="prompt[sections][zona]" value="<?php echo cv('prompt.sections.zona'); ?>" placeholder="Ej: Burriana centro, piso discreto">
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
                <h2>🛏️ Servicios</h2>
                <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:8px">
                    Describe los servicios disponibles. El bot usará esta información cuando le pregunten.
                </p>
                <textarea name="prompt[sections][servicios]" class="code-area" style="width:100%;min-height:80px" spellcheck="false"><?php echo cv('prompt.sections.servicios'); ?></textarea>
            </div>

            <!-- Ofertas -->
            <div class="card">
                <h2>🎁 Ofertas especiales (opcional)</h2>
                <textarea name="prompt[sections][ofertas]" class="code-area" style="width:100%;min-height:60px" spellcheck="false"><?php echo cv('prompt.sections.ofertas'); ?></textarea>
            </div>

            <div style="margin-top:16px">
                <button type="submit" class="btn btn-primary btn-lg">💾 Guardar Personalidad</button>
            </div>
        </div>

        <!-- Preview column -->
        <div class="prompt-preview-col">
            <div class="card prompt-preview-card">
                <h2>Vista previa del prompt</h2>
                <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:8px">
                    Así ve el bot la configuración. Solo lectura.
                </p>
                <pre id="prompt-preview" class="prompt-preview-box" style="font-size:.72rem">Cargando...</pre>
                <div id="prompt-stats" class="prompt-stats"></div>
            </div>
        </div>
    </div>
</div>

</form>

<script>
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
    var tono = document.querySelector('select[name="prompt[sections][estilo_tipo]"]');
    var tonoLabel = tono ? tono.options[tono.selectedIndex].text : '';
    var tarifas = document.querySelector('textarea[name="prompt[sections][tarifas]"]');
    var ubicacion = document.querySelector('textarea[name="prompt[sections][ubicacion]"]');
    var zona = document.querySelector('input[name="prompt[sections][zona]"]');
    var servicios = document.querySelector('textarea[name="prompt[sections][servicios]"]');
    var ofertas = document.querySelector('textarea[name="prompt[sections][ofertas]"]');
    var mapsUrl = document.querySelector('input[name="urls[google_maps_location]"]');

    var parts = [];
    parts.push('=== BOT-CASA CONFIG ===');
    parts.push('');
    parts.push('TONO: ' + (tonoLabel || 'Latina de barrio'));
    parts.push('');
    if ((tarifas && tarifas.value.trim()) || (ubicacion && ubicacion.value.trim())) {
        parts.push('--- TARIFAS ---');
        parts.push((tarifas && tarifas.value.trim()) ? tarifas.value.trim() : '(sin configurar)');
        parts.push('');
        parts.push('--- UBICACIÓN ---');
        if (zona && zona.value.trim()) parts.push('Zona: ' + zona.value.trim());
        if (ubicacion && ubicacion.value.trim()) parts.push(ubicacion.value.trim());
        if (!zona && !(ubicacion && ubicacion.value.trim())) parts.push('(sin configurar)');
        parts.push('');
    }
    if (servicios && servicios.value.trim()) {
        parts.push('--- SERVICIOS ---');
        parts.push(servicios.value.trim());
        parts.push('');
    }
    if (ofertas && ofertas.value.trim()) {
        parts.push('--- OFERTAS ---');
        parts.push(ofertas.value.trim());
        parts.push('');
    }
    if (mapsUrl && mapsUrl.value.trim()) {
        parts.push('Google Maps: ' + mapsUrl.value.trim());
        parts.push('');
    }
    parts.push('---');
    parts.push('(El prompt completo incluye reglas avanzadas gestionadas automáticamente por el sistema)');

    var preview = document.getElementById('prompt-preview');
    if (preview) preview.textContent = parts.join('\n');

    var stats = document.getElementById('prompt-stats');
    if (stats) {
        var totalChars = parts.join('\n').length;
        var configured = (tarifas && tarifas.value.trim().length > 10) ? 1 : 0;
        configured += (zona && zona.value.trim().length > 0) ? 1 : 0;
        configured += (servicios && servicios.value.trim().length > 10) ? 1 : 0;
        stats.innerHTML = '<span class="' + (totalChars > 100 ? 'stat-ok' : 'stat-warn') + '">' + totalChars + ' caracteres</span>'
            + '<span>' + configured + '/3 secciones configuradas</span>';
    }
}

function updateTonoPreview() { buildPreview(); }

// Bind all inputs
document.addEventListener('DOMContentLoaded', function() {
    buildPreview();
    var inputs = document.querySelectorAll('textarea[name^="prompt"], input[name^="prompt"], input[name^="urls"], select[name^="prompt"]');
    inputs.forEach(function(el) { el.addEventListener('input', buildPreview); });
});
</script>
</body>
</html>
