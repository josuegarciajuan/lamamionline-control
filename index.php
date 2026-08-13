<?php
require_once __DIR__ . '/app/bootstrap.php';

// ── Forzar recompilación de este archivo en cada despliegue ──
// Evita que PHP opcache sirva una versión compilada antigua
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}

// ── Detección automática del dispositivo coche (RK3566, Android 8.1, Chrome 95) ──
$isCarDevice = false;
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $ua = $_SERVER['HTTP_USER_AGENT'];
    // Modelo del SoC según specs/09-lite-device-specs.md: evb3561sv_w_65_m0
    if (stripos($ua, 'evb3561sv_w_65_m0') !== false) {
        $isCarDevice = true;
    }
}

// ?lite=0 permite forzar modo no-Lite en el coche (para testing/admin)
if (isset($_GET['lite']) && $_GET['lite'] === '0') {
    $isCarDevice = false;
}

// Lite se activa por parámetro explícito (?lite=1) o por detección automática del coche
$lite = (isset($_GET['lite']) && $_GET['lite'] === '1') || $isCarDevice;

$page = isset($_GET['page']) ? $_GET['page'] : ($lite ? 'josue' : 'dashboard');
// Modo Lite sin page explícito → Reproductor por defecto (car-friendly)
if ($lite && $page === 'josue' && !isset($_GET['tab'])) {
    $_GET['tab'] = 'reproductor';
}

// Modo Lite: auto-login sin contraseña (pantalla de coche)
if ($lite && !is_logged_in()) {
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = 'lite';
    $_SESSION['display_name'] = 'Coche';
}

// Modo Lite: recordar última sección visitada para restaurar al reabrir la app
// Usa cookie de sesión (se borra al cerrar el navegador) + settings.json (persiste en DB)
if ($lite && is_logged_in() && $page !== 'login' && $page !== 'logout') {
    $settings = settings_get();
    $liteLastPage = $settings['lite_last_page'] ?? null;
    $liteLastTab  = $settings['lite_last_tab'] ?? null;

    // ¿Sesión nueva del navegador? (cookie de sesión se borra al cerrar la app)
    if (!isset($_COOKIE['lamami_lite_sess'])) {
        // Restaurar última sección si es distinta a la actual
        if ($liteLastPage && $liteLastPage !== $page) {
            $qs = 'lite=1&page=' . urlencode($liteLastPage);
            if ($liteLastTab) {
                $qs .= '&tab=' . urlencode($liteLastTab);
            }
            setcookie('lamami_lite_sess', '1', 0, '/control/', '', false, true);
            header('Location: index.php?' . $qs);
            exit;
        }
        setcookie('lamami_lite_sess', '1', 0, '/control/', '', false, true);
    }

    // Guardar sección actual para la próxima vez (solo si cambió)
    $currentTab = $_GET['tab'] ?? null;
    if ($page !== $liteLastPage || $currentTab !== $liteLastTab) {
        $settings['lite_last_page'] = $page;
        $settings['lite_last_tab'] = $currentTab;
        storage_write('settings.json', $settings);
    }
}

// ── Wake Word copilot config (read from settings, used in body data-attrs) ──
$voiceSettings = storage_read('settings.json');
$voiceWakeEnabled = !empty($voiceSettings['voice_wake_enabled']);
$voiceWakeWord = trim((string)($voiceSettings['voice_wake_word'] ?? 'Jefry'));
if ($voiceWakeWord === '') $voiceWakeWord = 'Jefry';

if ($page === 'logout') {
    if ($lite) {
        // Modo Lite: ignorar logout, volver al dashboard
        header('Location: index.php?lite=1&page=dashboard');
        exit;
    }
    logout_user();
    header('Location: index.php?page=login&logged_out=1');
    exit;
}

auth_auto_login_from_whitelist();
auth_auto_login_from_trusted_device();

if (!is_logged_in() && $page !== 'login') {
    header('Location: index.php?page=login');
    exit;
}

if ($page === 'josue' && request_get('tab') === 'avisos') {
    header('Location: ' . avisos_page_url());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post_actions();
}

// Acciones GET ligeras (polling, etc.) — sin CSRF, solo autenticación
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    handle_get_actions();
}
// Prevent clickjacking on mobile (MOBILE-REDESIGN F0)
header('X-Frame-Options: DENY');

// Force HTTPS: upgrade all insecure requests (images, scripts, etc.) to HTTPS
header("Content-Security-Policy: upgrade-insecure-requests");

// ── Lite mode: prevent ALL browser+WebView caching on the car device ──
// Chrome 95 WebView + SW cache-first = stale UI persisted for days.
// no-store + no-cache + must-revalidate cubre las 3 capas (HTTP, SW, WebView).
if ($lite) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

// ── Anti-cache versiones automáticas (filemtime = cambiar al tocar el archivo) ──
$_liteCssV  = filemtime(__DIR__ . '/assets/lite.css');
$_appJsV    = filemtime(__DIR__ . '/assets/app.js');
$_gpsRadarJsV = filemtime(__DIR__ . '/assets/gps-radar.js');
$_styleCssV = filemtime(__DIR__ . '/assets/style.css');
$_tokensCssV= filemtime(__DIR__ . '/assets/tokens.css');
$_themeCssV = filemtime(__DIR__ . '/assets/theme.css');
$_swV       = filemtime(__DIR__ . '/sw.js');

?><!doctype html>
<html lang="es">
<?php if ($lite): ?>
<!-- HTTPS redirect: activar solo si el servidor tiene SSL configurado correctamente -->
<script>if(location.protocol==='http:'&&location.hostname!=='localhost'&&location.hostname!=='127.0.0.1')location.replace(location.href.replace(/^http:/,'https:'));</script>
<?php endif; ?>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?= $lite ? 'Bienvenido Josué' : 'LaMami CRM' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#060c16">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
<?php if ($lite): ?>
    <link rel="manifest" href="manifest-lite.json?v=<?= filemtime(__DIR__ . '/manifest-lite.json') ?>">
<?php else: ?>
    <link rel="manifest" href="manifest.json?v=<?= filemtime(__DIR__ . '/manifest.json') ?>">
<?php endif; ?>
    <script>
    // Lazy Chart.js — solo cargar en páginas con gráficos
    window._chartsQueue = [];
    window._lazyChart = function(fn) {
        if (window.Chart) { fn(); return; }
        window._chartsQueue.push(fn);
    };
    window._loadChartJS = function() {
        if (window.Chart || window._chartLoading) return;
        window._chartLoading = true;
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
        s.onload = function() {
            window._chartsQueue.forEach(function(fn) { fn(); });
            window._chartsQueue = [];
        };
        document.head.appendChild(s);
    };
    <?php if (!$lite): ?>window._loadChartJS();<?php endif; ?>
    </script>
<?php if ($lite): ?>
    <link rel="stylesheet" href="assets/lite.css?v=<?= $_liteCssV ?>">
<?php else: ?>
    <link rel="stylesheet" href="assets/tokens.css?v=<?= $_tokensCssV ?>">
    <link rel="stylesheet" href="assets/style.css?v=<?= $_styleCssV ?>">
    <link rel="stylesheet" href="assets/theme.css?v=<?= $_themeCssV ?>">
<?php endif; ?>
    <style>
    /* ═══ INLINE OVERRIDE: Pink→Gold + Mobile compaction ═══
       Este bloque garantiza que los cambios se apliquen siempre,
       incluso si el navegador usa versiones cacheadas de los .css */
    :root{--accent:#f59e0b!important;--accent-light:#fbbf24!important;--accent-dark:#d97706!important;--brand-rose:#f59e0b!important}
    .nav a:hover,.nav a.active{background:linear-gradient(135deg,rgba(245,158,11,.14),rgba(249,115,22,.10))!important;border-color:rgba(245,158,11,.30)!important}
    .app-shell-btn-mic,.brand-voice-btn{border-color:rgba(245,158,11,.22)!important;background:linear-gradient(135deg,rgba(245,158,11,.22),rgba(249,115,22,.18))!important}
    .subtabs .subtab.active,a.subtab.active{background:linear-gradient(135deg,rgba(245,158,11,.20),rgba(249,115,22,.14))!important;border-color:rgba(245,158,11,.22)!important;box-shadow:0 8px 20px rgba(245,158,11,.22)!important}
    .voice-command-status.stage-listening{background:rgba(245,158,11,.16)!important;border-color:rgba(245,158,11,.36)!important;color:#fde68a!important}
    .long-press-btn .progress-bar{background:rgba(245,158,11,.35)!important}
    @media(max-width:767px){
      .main{padding:38px 10px 80px!important}
      .app-shell-tools{gap:3px!important;top:4px!important;justify-content:flex-end!important}
      .app-shell-btn{padding:5px 8px!important;font-size:11px!important}
      .app-shell-btn-mic{width:30px!important;min-width:30px!important;flex:0 0 30px!important;border-color:rgba(245,158,11,.20)!important;background:linear-gradient(135deg,rgba(245,158,11,.18),rgba(249,115,22,.12))!important;font-size:15px!important}
      .page-head{margin-bottom:10px!important}
      .page-head h1{font-size:20px!important}
      .page-head p{font-size:12px!important}
      .mobile-fab{background:linear-gradient(135deg,#f59e0b,#d97706)!important;box-shadow:0 6px 20px rgba(245,158,11,.35)!important}
      .mobile-fab:active{box-shadow:0 4px 14px rgba(245,158,11,.25)!important}
    }
    </style>
</head>
<body class="page-<?= e($page) ?><?= $lite ? ' is-lite' : '' ?><?= (($page === 'josue' && ($_GET['tab'] ?? '') === 'reproductor') ? ' josue-yt-fs' : '') ?>" data-page="<?= e($page) ?>" data-gps-interval="<?= $lite ? '90' : '60' ?>" data-is-car-device="<?= $isCarDevice ? '1' : '0' ?>" data-voice-wake-enabled="<?= $voiceWakeEnabled ? '1' : '0' ?>" data-voice-wake-word="<?= e($voiceWakeWord) ?>">
<?php if ($page === 'login'): ?>
    <?php render_login_page(); ?>
<?php else: ?>
    <?php render_global_ui($page, $lite); ?>
    <div class="layout" id="appLayout">
        <?php render_sidebar($page); ?>
        <main class="main" id="appMain">
            <?php render_flash(); ?>
            <?php render_avisos_panel(); ?>
            <?php
                switch ($page) {
                    case 'lamami':
                        render_lamami_page();
                        break;

                    case 'interesadas':
                        $_GET['tab'] = 'interesadas';
                        render_lamami_page();
                        break;

                    case 'clientas':
                        $_GET['tab'] = 'clientas';
                        render_lamami_page();
                        break;

                    case 'lamamibot':
                        $_GET['tab'] = 'lamamibot';
                        render_lamami_page();
                        break;

                    case 'publicista':
                        if (!isset($_GET['tab']) || trim((string) $_GET['tab']) === '') {
                            $_GET['tab'] = 'crear_perfiles';
                        }
                        render_publicista_page();
                        break;

                    case 'comercial':
                        if (!isset($_GET['tab']) || trim((string) $_GET['tab']) === '') {
                            $_GET['tab'] = 'resumen';
                        }
                        render_comercial_page();
                        break;

                    case 'bots':
                        render_bots_page();
                        break;

                    case 'informes':
                        render_informes_page();
                        break;

                    case 'gridmensual':
                        $_GET['view'] = 'grid';
                        render_informes_page();
                        break;

                    case 'josue':
                        render_josue_page();
                        break;

                    case 'casawasap':
                        render_casawasap_page();
                        break;

                    case 'jostal':
                        render_jostal_page();
                        break;

                    case 'gastos':
                        render_gastos_page();
                        break;

                    case 'avisos':
                        render_avisos_page();
                        break;

                    case 'bot-casa':
                        render_bot_casa_page();
                        break;

                    default:
                        render_dashboard_page();
                        break;
                }
            ?>
        </main>
    </div>
<?php endif; ?>
<script src="assets/app.js?v=<?= $_appJsV ?>"></script>
<script src="assets/gps-radar.js?v=<?= $_gpsRadarJsV ?>"></script>
<script>
// Fuerza el handler del boton +Lista (belt-and-suspenders: el onclick HTML puede fallar por CSP/caché)
// En modo lite: el modal PHP #addPlModalLite ya tiene su propio handler inline (addEventListener).
// No tocamos onclick para no interferir con el handler inline de views.php.
(function attachListaBtn(){
    if (document.getElementById('addPlModalLite')) return;
    var b = document.getElementById('youtubeAddToPlBtn');
    if (b && typeof YTPlayer !== 'undefined' && YTPlayer.addCurrentToPlaylist) {
        b.onclick = function(){ YTPlayer.addCurrentToPlaylist(); };
    } else {
        // Si YTPlayer aun no esta listo, reintentar en el siguiente frame
        setTimeout(attachListaBtn, 100);
    }
})();
</script>
<?php if ($lite): ?>
<script>
// ── Service Worker + actualización automática ──
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/control/sw.js?v=<?= $_swV ?>', {scope: '/control/'})
        .then(function (reg) {
            // Si hay un SW esperando (installed pero no activated), forzar skipWaiting
            if (reg.waiting) {
                reg.waiting.postMessage({type: 'SKIP_WAITING'});
            }
            // Detectar cuando un nuevo SW llega y está esperando
            reg.addEventListener('updatefound', function () {
                var newWorker = reg.installing;
                if (!newWorker) return;
                newWorker.addEventListener('statechange', function () {
                    if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                        // Nuevo SW listo → forzar activación y recargar
                        newWorker.postMessage({type: 'SKIP_WAITING'});
                    }
                });
            });
        })
        .catch(function () { /* SW no soportado en este contexto */ });

    // Cuando el SW nuevo toma el control, diferir recarga para no interrumpir GPS
    var _swRefreshing = false;
    navigator.serviceWorker.addEventListener('controllerchange', function () {
        if (_swRefreshing) return;
        _swRefreshing = true;
        // Recarga diferida: el GPS sigue trackeando; la UI se actualiza
        // en la siguiente navegación natural o tras 5 min
        setTimeout(function () {
            if (!document.hidden) window.location.reload();
        }, 300000);
    });
}
<?php if (in_array($page, ['informes', 'gridmensual'], true)): ?>
// Lite: Chart.js solo en informes/gridmensual (no en dashboard, demasiado pesado para 2GB RAM)
window._loadChartJS();
<?php endif; ?>
</script>
<?php endif; ?>
</body>
</html>
