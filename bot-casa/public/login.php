<?php

declare(strict_types=1);

/**
 * login.php — Página de autenticación para bot-casa multi-usuario.
 *
 * GET  /login → muestra formulario
 * POST /login → procesa credenciales, inicia sesión, redirige según role
 */

// ── Bootstrap mínimo ──
define('WASAPBOT_ROOT', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $prefix = 'WasapBot\\';
    $prefixLen = strlen($prefix);
    if (strncmp($prefix, $class, $prefixLen) !== 0) return;
    $relativeClass = substr($class, $prefixLen);
    $file = WASAPBOT_ROOT . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require_once $file;
});

// ── Iniciar sesión ──
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

// ── Si ya está autenticado, redirigir ──
$isLoggedIn = !empty($_SESSION['user_id']);
if ($isLoggedIn) {
    $role = (string) ($_SESSION['role'] ?? 'user');
    if ($role === 'admin') {
        header('Location: panel');
    } else {
        header('Location: cliente');
    }
    exit;
}

// ── CSRF token ──
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// ── Procesar POST ──
$error = '';
$hasAttempt = false;
$um = new \WasapBot\Core\UserManager(WASAPBOT_ROOT);
$needsSeeding = !$um->hasUsersFile();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hasAttempt = true;

    // Rate limit básico: dormir 1s para frenar fuerza bruta
    sleep(1);

    // Validar CSRF
    $postToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postToken)) {
        $error = 'Token de seguridad inválido. Recarga la página.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $error = 'Usuario y contraseña son obligatorios.';
        } else {
            // Si es el primer acceso, seedear el admin por defecto
            if ($needsSeeding) {
                $um->seedDefaultAdmin();
            }

            $user = $um->authenticate($username, $password);

            if ($user !== null) {
                // Regenerar session ID para prevenir session fixation
                session_regenerate_id(true);

                $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
                $_SESSION['username'] = (string) ($user['username'] ?? '');
                $_SESSION['role'] = (string) ($user['role'] ?? 'user');
                $_SESSION['name'] = (string) ($user['name'] ?? '');
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                if ($_SESSION['role'] === 'admin') {
                    header('Location: panel');
                } else {
                    header('Location: cliente');
                }
                exit;
            }

            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}

// ── Renderizar ──
// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Permitted-Cross-Domain-Policies: none');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>bot-casa — Acceso</title>
<style>
:root {
    --bg: #080d17;
    --panel: #111b2e;
    --border: #1c2d4a;
    --text: #f0f3fa;
    --text-muted: #8b9ec0;
    --accent: #f59e0b;
    --accent-dark: #d97706;
    --danger: #f87171;
    --radius: 14px;
    --font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: var(--font);
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.login-card {
    background: var(--panel);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 32px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 12px 40px rgba(0,0,0,.35);
}
.login-card h1 {
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 6px;
    color: var(--accent);
}
.login-card .subtitle {
    color: var(--text-muted);
    font-size: .85rem;
    margin-bottom: 24px;
}
.form-group { margin-bottom: 16px; }
.form-group label {
    display: block;
    font-size: .82rem;
    color: var(--text-muted);
    margin-bottom: 4px;
    font-weight: 500;
}
.form-group input {
    width: 100%;
    padding: 10px 12px;
    background: #0c1522;
    border: 1px solid var(--border);
    border-radius: 10px;
    color: var(--text);
    font-size: .95rem;
    font-family: var(--font);
    transition: border-color .2s;
}
.form-group input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 2px rgba(245,158,11,.22);
}
.btn {
    display: block;
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: .95rem;
    font-weight: 600;
    font-family: var(--font);
    background: linear-gradient(135deg, var(--accent), var(--accent-dark));
    color: #1a1206;
    transition: all .2s;
    box-shadow: 0 4px 12px rgba(0,0,0,.25);
    margin-top: 8px;
}
.btn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(245,158,11,.35); }
.error-msg {
    background: rgba(248,113,113,.10);
    border: 1px solid rgba(248,113,113,.30);
    color: var(--danger);
    padding: 10px 14px;
    border-radius: 10px;
    margin-bottom: 16px;
    font-size: .85rem;
    font-weight: 500;
}
.login-footer {
    margin-top: 16px;
    text-align: center;
    font-size: .78rem;
    color: var(--text-muted);
}
</style>
</head>
<body>
<div class="login-card">
    <h1>bot-casa</h1>
    <p class="subtitle">Panel de administración para tu bot de WhatsApp</p>

    <?php if ($error !== ''): ?>
    <div class="error-msg"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <form method="post" action="login" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="form-group">
            <label for="username">Usuario</label>
            <input type="text" id="username" name="username" required autofocus autocomplete="username"
                   value="<?php echo htmlspecialchars((string) ($_POST['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
        </div>

        <button type="submit" class="btn">Entrar</button>
    </form>

    <div class="login-footer">
        <?php if ($needsSeeding): ?>
        <p style="color:#f59e0b;margin-bottom:4px"><strong>Primer acceso:</strong> usuario <code>admin</code> / contraseña <code>admin123</code></p>
        <p style="font-size:.72rem">Cámbiala cuanto antes desde el panel.</p>
        <?php else: ?>
        Acceso restringido · bot-casa v2.0
        <?php endif; ?>
    </div>
</div>
</body>
</html>
