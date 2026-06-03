<?php

declare(strict_types=1);

/**
 * index.php — Front controller / router.
 *
 * Routes:
 *   POST /webhook              → webhook.php (WAHA webhook handler)
 *   GET  /panel                → panel.php  (admin panel)
 *   GET  /info                 → JSON info  {bot, version, running}
 *   GET  /health               → JSON health check
 *   GET  /                     → JSON info  (same as /info)
 *   *                          → 404 JSON
 */

// ─────────────────────────────────────────────────────────────────────
//  Autoload
// ─────────────────────────────────────────────────────────────────────

define('WASAPBOT_ROOT', dirname(__DIR__));

$vendorAutoload = WASAPBOT_ROOT . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
} else {
    spl_autoload_register(function (string $class): void {
        $prefix = 'WasapBot\\';
        $prefixLen = strlen($prefix);

        if (strncmp($prefix, $class, $prefixLen) !== 0) {
            return;
        }

        $relativeClass = substr($class, $prefixLen);
        $file = WASAPBOT_ROOT . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    });
}

// ─────────────────────────────────────────────────────────────────────
//  Routing
// ─────────────────────────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$uri    = rtrim((string) $uri, '/') ?: '/';
$uri    = ($uri === '') ? '/' : $uri;

// ── Auth helpers (shared across routes) ─────────────────────────────

/**
 * Check if the current request is authenticated. Starts session if needed.
 * @return bool
 */
function botcasa_is_authenticated(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        // Secure session cookie configuration
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    return !empty($_SESSION['user_id']);
}

/**
 * Require authentication. If not authenticated, redirect to login.
 */
function botcasa_require_auth(): void
{
    if (!botcasa_is_authenticated()) {
        header('Location: login');
        exit;
    }
}

/**
 * Require admin role. If not admin, show 403.
 * If users.json doesn't exist yet (legacy mode), skip auth entirely.
 */
function botcasa_require_admin(): void
{
    // Legacy mode: if users.json doesn't exist, panel is open (no auth)
    static $checked = false;
    static $legacyMode = false;
    if (!$checked) {
        $checked = true;
        $usersFile = WASAPBOT_ROOT . '/data/users.json';
        $legacyMode = !file_exists($usersFile);
    }
    if ($legacyMode) {
        return;
    }

    botcasa_require_auth();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>403 Prohibido</title></head><body style="background:#080d17;color:#f0f3fa;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0"><div style="text-align:center"><h1 style="color:#f87171;font-size:3rem">403</h1><p>Acceso restringido. Solo el administrador puede acceder a esta sección.</p><p style="margin-top:16px"><a href="panel" style="color:#f59e0b">Volver al panel</a></p></div></body></html>';
        exit;
    }
}

try {
    switch (true) {
        // ── GET|POST /login → login page ─────────────────────────
        case $uri === '/login':
            require WASAPBOT_ROOT . '/public/login.php';
            break;

        // ── GET /logout → logout and redirect ────────────────────
        case $uri === '/logout':
            require WASAPBOT_ROOT . '/public/logout.php';
            break;

        // ── POST /webhook → delegate to webhook.php ─────────────
        case $method === 'POST' && $uri === '/webhook':
            require WASAPBOT_ROOT . '/public/webhook.php';
            break;

        // ── GET /panel → admin panel (requires admin auth) ───────
        case $method === 'GET' && $uri === '/panel':
            botcasa_require_admin();
            $panelPath = WASAPBOT_ROOT . '/public/panel.php';
            if (file_exists($panelPath)) {
                require $panelPath;
            } else {
                http_response_code(503);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'Admin panel not available (panel.php missing)',
                ], JSON_UNESCAPED_UNICODE);
            }
            break;

        // ── GET|POST /cliente → client panel (auth + suplantar) ──
        case ($method === 'GET' || $method === 'POST') && $uri === '/cliente':
            botcasa_require_admin(); // Solo admin puede acceder/suplantar
            $suplantarUserId = 0;
            $suplantarUser = null;

            // Handle suplantar POST from admin panel
            if ($method === 'POST') {
                $postToken = (string) ($_POST['csrf_token'] ?? '');
                $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
                if ($postToken !== '' && hash_equals($sessionToken, $postToken)) {
                    $suplantarUserId = (int) ($_POST['suplantar_user_id'] ?? 0);
                }
            }

            // Also check existing suplantar session
            $storedSuplantar = (int) ($_SESSION['suplantar_user_id'] ?? 0);
            if ($suplantarUserId > 0) {
                $_SESSION['suplantar_user_id'] = $suplantarUserId;
                $storedSuplantar = $suplantarUserId;
            }

            // Cargar datos del usuario suplantado
            if ($storedSuplantar > 0) {
                $tempUm = new \WasapBot\Core\UserManager(WASAPBOT_ROOT);
                $suplantarUser = $tempUm->getUser($storedSuplantar);
                // Validate user is active
                if ($suplantarUser !== null && empty($suplantarUser['active'])) {
                    $suplantarUser = null;
                    unset($_SESSION['suplantar_user_id']);
                }
            }

            http_response_code(200);
            header('Content-Type: text/html; charset=utf-8');
            header('X-Frame-Options: DENY');
            header('X-Content-Type-Options: nosniff');
            ?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>bot-casa — Panel Cliente</title>
<style>
:root{--bg:#080d17;--panel:#111b2e;--border:#1c2d4a;--text:#f0f3fa;--text-muted:#8b9ec0;--accent:#f59e0b;--ok:#34d399;--danger:#f87171;--radius:14px;--font:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:32px;max-width:550px;width:100%;box-shadow:0 12px 40px rgba(0,0,0,.35)}
h1{color:var(--accent);margin-bottom:8px;font-size:1.4rem}
h2{color:var(--text);margin-bottom:16px;font-size:1rem}
p{color:var(--text-muted);margin-bottom:12px;font-size:.9rem}
.badge{display:inline-block;padding:4px 12px;border-radius:6px;font-size:.8rem;font-weight:600;margin-bottom:16px}
.badge-admin{background:var(--accent);color:#1a1206}
.badge-user{background:var(--input-bg, #0c1522);color:var(--text-muted)}
.info-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);font-size:.85rem}
.info-row:last-child{border-bottom:none}
.info-label{color:var(--text-muted)}
.info-value{color:var(--text);font-weight:500}
.btn-row{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}
.btn{display:inline-block;padding:8px 18px;border:none;border-radius:10px;cursor:pointer;font-size:.88rem;font-weight:600;font-family:var(--font);text-decoration:none;transition:all .2s}
.btn-primary{background:linear-gradient(135deg,var(--accent),#d97706);color:#1a1206}
.btn-primary:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(245,158,11,.35)}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text-muted)}
.btn-outline:hover{background:rgba(255,255,255,.05)}
</style></head><body>
<div class="card">
    <h1>Panel Cliente</h1>
    <?php if ($suplantarUser): ?>
        <span class="badge <?php echo ($suplantarUser['role'] ?? '') === 'admin' ? 'badge-admin' : 'badge-user'; ?>">
            <?php echo ($suplantarUser['role'] ?? '') === 'admin' ? 'Admin' : 'Usuario'; ?>
        </span>
        <h2>Viendo panel de: <?php echo htmlspecialchars((string)($suplantarUser['name'] ?? $suplantarUser['username'] ?? '?'), ENT_QUOTES, 'UTF-8'); ?></h2>
        <div class="info-row"><span class="info-label">Usuario</span><span class="info-value"><?php echo htmlspecialchars((string)($suplantarUser['username'] ?? '?'), ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="info-row"><span class="info-label">ID</span><span class="info-value"><?php echo (int)($suplantarUser['id'] ?? 0); ?></span></div>
        <div class="info-row"><span class="info-label">Creado</span><span class="info-value"><?php echo htmlspecialchars((string)($suplantarUser['created_at'] ?? '?'), ENT_QUOTES, 'UTF-8'); ?></span></div>
        <div class="info-row"><span class="info-label">Activo</span><span class="info-value"><?php echo !empty($suplantarUser['active']) ? '✅ Sí' : '❌ No'; ?></span></div>
        <p style="margin-top:16px;font-size:.82rem">El panel completo de cliente estará disponible en la Fase 3. Desde aquí podrás configurar su bot, chicas, líneas y estados.</p>
        <div class="btn-row">
            <form method="post" action="cliente" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="suplantar_user_id" value="0">
                <button type="submit" class="btn btn-outline">👤 Volver a mi usuario</button>
            </form>
            <a href="panel" class="btn btn-outline">Volver al panel admin</a>
        </div>
    <?php else: ?>
        <p>Selecciona un usuario para suplantar desde el panel de administración (pestaña <strong>Usuarios</strong> → botón <strong>🔍 Ver</strong>).</p>
        <p style="font-size:.82rem">El panel de cliente estará disponible en la Fase 3.</p>
        <div class="btn-row">
            <a href="panel" class="btn btn-primary">Ir al panel admin</a>
            <a href="logout" class="btn btn-outline">Cerrar sesión</a>
        </div>
    <?php endif; ?>
</div>
</body></html>
            <?php
            break;

        // ── GET /health → health check (no heavy bootstrap) ────
        case $method === 'GET' && $uri === '/health':
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status'  => 'ok',
                'service' => 'wasapBot PHP',
                'version' => '1.0',
                'time'    => date('c'),
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ── GET /  or  GET /info → bot info ────────────────────
        case $method === 'GET' && ($uri === '/' || $uri === '/info'):
            try {
                $instances = \WasapBot\Bot::bootstrap(WASAPBOT_ROOT);
                $bot       = $instances['bot'];
                $running   = $bot->isRunning();
            } catch (\Throwable) {
                $running = false;
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'bot'     => 'wasapBot PHP',
                'version' => '1.0',
                'running' => $running,
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ── 404 fallback ───────────────────────────────────────
        default:
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status'  => 'error',
                'message' => 'Not found. Available: POST /webhook, GET /panel, GET /cliente, GET /login, GET /health, GET /info',
                'routes'  => [
                    'POST /webhook' => 'WAHA webhook handler',
                    'GET  /panel'   => 'Admin panel',
                    'GET  /cliente' => 'Client panel',
                    'GET  /login'   => 'Login page',
                    'GET  /health'  => 'Health check',
                    'GET  /info'    => 'Bot info',
                ],
            ], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (\Throwable $e) {
    // Catch-all: never expose stack traces
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');

    error_log('[wasapBot] index.php unhandled exception: ' . $e->getMessage());

    echo json_encode([
        'status'  => 'error',
        'message' => 'Internal server error',
    ], JSON_UNESCAPED_UNICODE);
}
