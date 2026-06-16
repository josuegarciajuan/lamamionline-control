<?php
require_once __DIR__ . '/app/bootstrap.php';

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

if ($page === 'logout') {
    logout_user();
    header('Location: index.php?page=login&logged_out=1');
    exit;
}

auth_auto_login_from_whitelist();

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
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>LaMami CRM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#060c16">
    <link rel="manifest" href="manifest.json?v=20260611_1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/tokens.css?v=20260616_1">
    <link rel="stylesheet" href="assets/style.css?v=20260616_1">
    <link rel="stylesheet" href="assets/theme.css?v=20260616_1">
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
<body class="page-<?= e($page) ?>" data-page="<?= e($page) ?>">
<?php if ($page === 'login'): ?>
    <?php render_login_page(); ?>
<?php else: ?>
    <?php render_global_ui($page); ?>
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
<script src="assets/app.js?v=20260616_1"></script>
</body>
</html>
