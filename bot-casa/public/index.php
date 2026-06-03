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

        // ── GET|POST /panel → admin panel (requires admin auth) ──
        case ($method === 'GET' || $method === 'POST') && $uri === '/panel':
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

        // ── GET|POST /cliente → client panel ──
        case ($method === 'GET' || $method === 'POST') && $uri === '/cliente':
            botcasa_require_auth();
            $clientUserId = (int) ($_SESSION['user_id'] ?? 0);
            $isAdmin = ($_SESSION['role'] ?? '') === 'admin';

            // Handle suplantar POST from admin panel
            if ($method === 'POST' && $isAdmin) {
                $postToken = (string) ($_POST['csrf_token'] ?? '');
                $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
                if ($postToken !== '' && hash_equals($sessionToken, $postToken)) {
                    $suplantarId = (int) ($_POST['suplantar_user_id'] ?? 0);
                    if ($suplantarId > 0) {
                        // Validate user exists and is active
                        $tempUm = new \WasapBot\Core\UserManager(WASAPBOT_ROOT);
                        $target = $tempUm->getUser($suplantarId);
                        if ($target !== null && !empty($target['active'])) {
                            $_SESSION['suplantar_user_id'] = $suplantarId;
                            $clientUserId = $suplantarId;
                        }
                    } else {
                        // suplantar_user_id=0 means "back to myself"
                        unset($_SESSION['suplantar_user_id']);
                        $clientUserId = (int) ($_SESSION['user_id'] ?? 0);
                    }
                }
            }

            // Check existing suplantar session for admin
            if ($isAdmin) {
                $storedSuplantar = (int) ($_SESSION['suplantar_user_id'] ?? 0);
                if ($storedSuplantar > 0) {
                    $tempUm = new \WasapBot\Core\UserManager(WASAPBOT_ROOT);
                    $suplantarUser = $tempUm->getUser($storedSuplantar);
                    if ($suplantarUser !== null && !empty($suplantarUser['active'])) {
                        $clientUserId = $storedSuplantar;
                    } else {
                        unset($_SESSION['suplantar_user_id']);
                    }
                }
            }

            // Render client panel
            $clientPanelPath = WASAPBOT_ROOT . '/public/client.php';
            if (file_exists($clientPanelPath)) {
                require $clientPanelPath;
            } else {
                http_response_code(503);
                header('Content-Type: text/html; charset=utf-8');
                echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>503</title></head><body style="background:#080d17;color:#f0f3fa;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0"><div style="text-align:center"><h1 style="color:#f59e0b">503</h1><p>Panel de cliente no disponible.</p><p><a href="login" style="color:#f59e0b">Volver</a></p></div></body></html>';
            }
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
            // If accessed standalone (not from CRM API call), redirect to login
            if ($uri === '/') {
                $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
                // Only redirect for browser-like access (not API calls)
                $acceptHeader = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
                if (strpos($acceptHeader, 'text/html') !== false) {
                    botcasa_require_auth();
                    // If authenticated, redirect to appropriate panel
                    if (($_SESSION['role'] ?? '') === 'admin') {
                        header('Location: panel');
                    } else {
                        header('Location: cliente');
                    }
                    exit;
                }
            }
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
