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
 */
function botcasa_require_admin(): void
{
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

        // ── GET /cliente → client panel (requires auth) ──────────
        case $method === 'GET' && $uri === '/cliente':
            botcasa_require_auth();
            http_response_code(200);
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>bot-casa — Panel Cliente</title><style>body{background:#080d17;color:#f0f3fa;font-family:Inter,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center}.card{background:#111b2e;border:1px solid #1c2d4a;border-radius:14px;padding:32px;max-width:500px;box-shadow:0 12px 40px rgba(0,0,0,.35)}h1{color:#f59e0b;margin-bottom:8px}p{color:#8b9ec0;margin-bottom:20px}a{color:#f59e0b}</style></head><body><div class="card"><h1>Panel Cliente</h1><p>El panel de cliente estará disponible en la Fase 3. Mientras tanto, puedes usar el panel de administración.</p><a href="logout">Cerrar sesión</a></div></body></html>';
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
