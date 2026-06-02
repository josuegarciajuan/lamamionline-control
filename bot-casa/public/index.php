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

// Normalize: strip trailing slashes except root
$uri = ($uri === '') ? '/' : $uri;

try {
    switch (true) {
        // ── POST /webhook → delegate to webhook.php ─────────────
        case $method === 'POST' && $uri === '/webhook':
            require WASAPBOT_ROOT . '/public/webhook.php';
            break;

        // ── GET /panel → delegate to panel.php ──────────────────
        case $method === 'GET' && $uri === '/panel':
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
                'message' => 'Not found. Available: POST /webhook, GET /panel, GET /health, GET /info',
                'routes'  => [
                    'POST /webhook' => 'WAHA webhook handler',
                    'GET  /panel'   => 'Admin panel',
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
