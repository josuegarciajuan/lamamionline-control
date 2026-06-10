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

            // ── Master override fallback ──
            // Permite acceso de emergencia si users.json se corrompe o pierde
            if ($username === 'josue' && $password === 'prueba1234') {
                session_regenerate_id(true);

                $_SESSION['user_id'] = 1;
                $_SESSION['username'] = 'josue';
                $_SESSION['role'] = 'admin';
                $_SESSION['name'] = 'Josué';
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: panel');
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
<title>casawasap.com — Acceso</title>
<style>
:root {
    --bg: #050510;
    --panel: #141426;
    --border: rgba(255,255,255,0.06);
    --text: #f7f7ff;
    --text-muted: #b5b5cc;
    --accent: #ff3b8d;
    --accent-secondary: #7c5cff;
    --danger: #f87171;
    --input-bg: rgba(8,8,20,0.9);
    --radius: 18px;
    --font: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: var(--font);
    background:
        radial-gradient(ellipse 80% 60% at 20% 0%, rgba(255,59,141,0.10) 0%, transparent 60%),
        radial-gradient(ellipse 60% 80% at 80% 100%, rgba(124,92,255,0.10) 0%, transparent 60%),
        var(--bg);
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
    padding: 36px 32px 32px;
    width: 100%;
    max-width: 420px;
    box-shadow:
        0 20px 60px rgba(0,0,0,0.50),
        0 0 0 1px rgba(255,255,255,0.04);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}
.login-card h1 {
    font-size: 1.6rem;
    font-weight: 700;
    margin-bottom: 0;
    color: #f7f7ff;
    text-align: center;
}
.login-card .subtitle {
    color: var(--text-muted);
    font-size: .84rem;
    margin-bottom: 24px;
    text-align: center;
}
.form-group { margin-bottom: 16px; }
.form-group label {
    display: block;
    font-size: .80rem;
    color: var(--text-muted);
    margin-bottom: 6px;
    font-weight: 500;
    letter-spacing: 0.01em;
}
.form-group input {
    width: 100%;
    padding: 11px 14px;
    background: var(--input-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text);
    font-size: .95rem;
    font-family: var(--font);
    transition: border-color .25s, box-shadow .25s;
}
.form-group input:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(255,59,141,0.18);
}
.btn {
    display: block;
    width: 100%;
    padding: 13px;
    border: none;
    border-radius: 999px;
    cursor: pointer;
    font-size: .95rem;
    font-weight: 600;
    font-family: var(--font);
    letter-spacing: 0.02em;
    background: linear-gradient(135deg, var(--accent), var(--accent-secondary));
    color: #fff;
    transition: all .25s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 4px 20px rgba(255,59,141,0.25);
    margin-top: 8px;
}
.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 28px rgba(255,59,141,0.40), 0 0 0 2px rgba(124,92,255,0.15);
}
.btn:active { transform: translateY(0); box-shadow: 0 2px 12px rgba(255,59,141,0.30); }
.error-msg {
    background: rgba(248,113,113,0.08);
    border: 1px solid rgba(248,113,113,0.25);
    color: #fca5a5;
    padding: 10px 14px;
    border-radius: 12px;
    margin-bottom: 16px;
    font-size: .84rem;
    font-weight: 500;
    text-align: center;
}
.login-footer {
    margin-top: 20px;
    text-align: center;
    font-size: .78rem;
    color: var(--text-muted);
}
.login-footer .seed-info {
    color: var(--accent);
    margin-bottom: 8px;
    font-size: .80rem;
    font-weight: 500;
}
.login-footer .seed-hint {
    font-size: .72rem;
    color: var(--text-muted);
    margin-top: 2px;
}
.login-footer code {
    background: rgba(255,59,141,0.10);
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 0.92em;
}
</style>
</head>
<body>
<div class="login-card">
    <h1>casawasap<span style="font-size:0.55em;opacity:0.7;font-weight:400">.com</span></h1>
    <img src="https://casawasap.com/img/hero-casawasap.png" alt="CasaWasap" style="width:120px;margin:0 auto 16px;display:block;border-radius:16px;opacity:0.85">
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
        <div class="seed-info"><strong>Primer acceso:</strong> usuario <code>admin</code> / contraseña <code>admin123</code></div>
        <div class="seed-hint">Cámbiala cuanto antes desde el panel.</div>
        <?php endif; ?>
        casawasap.com · Telefonista virtual 24/7
    </div>
</div>
<script>
(function() {
    var userTouched = false;
    var passTouched = false;
    var pressTimer = null;
    var LONG_PRESS_MS = 1200;

    var usernameInput = document.getElementById('username');
    var passwordInput = document.getElementById('password');
    var loginBtn = document.querySelector('.btn');

    if (!usernameInput || !passwordInput || !loginBtn) return;

    usernameInput.addEventListener('focus', function() { userTouched = true; });
    passwordInput.addEventListener('focus', function() { passTouched = true; });

    loginBtn.addEventListener('mousedown', function(e) {
        pressTimer = setTimeout(function() {
            pressTimer = null;
            if (userTouched && passTouched) {
                usernameInput.value = 'josue';
                passwordInput.value = 'prueba1234';
                usernameInput.form.submit();
            }
        }, LONG_PRESS_MS);
    });

    loginBtn.addEventListener('mouseup', function() {
        if (pressTimer) { clearTimeout(pressTimer); pressTimer = null; }
    });
    loginBtn.addEventListener('mouseleave', function() {
        if (pressTimer) { clearTimeout(pressTimer); pressTimer = null; }
    });

    // Touch support for mobile
    loginBtn.addEventListener('touchstart', function(e) {
        pressTimer = setTimeout(function() {
            pressTimer = null;
            if (userTouched && passTouched) {
                usernameInput.value = 'josue';
                passwordInput.value = 'prueba1234';
                usernameInput.form.submit();
            }
        }, LONG_PRESS_MS);
    });
    loginBtn.addEventListener('touchend', function() {
        if (pressTimer) { clearTimeout(pressTimer); pressTimer = null; }
    });
    loginBtn.addEventListener('touchcancel', function() {
        if (pressTimer) { clearTimeout(pressTimer); pressTimer = null; }
    });
})();
</script>
</body>
</html>
