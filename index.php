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
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>LaMami CRM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/style.css?v=20260602_8">
    <link rel="stylesheet" href="assets/theme.css?v=20260601_2">
</head>
<body class="page-<?= e($page) ?>" data-page="<?= e($page) ?>">
<?php if ($page === 'login'): ?>
    <?php render_login_page(); ?>
<?php else: ?>
    <?php render_global_ui(); ?>
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
<script src="assets/app.js?v=20260602_7"></script>
</body>
</html>
