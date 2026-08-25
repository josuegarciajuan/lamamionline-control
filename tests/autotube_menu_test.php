<?php

require_once __DIR__ . '/../app/helpers.php';
require_once __DIR__ . '/../app/views.php';

function assert_contains(string $needle, string $haystack, string $message): void
{
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$_SESSION['display_name'] = 'Test';

ob_start();
render_sidebar('autotube');
$sidebar = ob_get_clean();

assert_contains('nav-external-projects', $sidebar, 'El grupo visual de proyectos externos debe existir.');
assert_contains('href="index.php?page=autotube"', $sidebar, 'Autotube debe tener un enlace directo.');
assert_contains('href="index.php?page=afiliados"', $sidebar, 'Afiliados debe permanecer en el grupo externo.');
if (strpos($sidebar, 'page=autotube') > strpos($sidebar, 'page=afiliados')) {
    fwrite(STDERR, "FAIL: Autotube debe aparecer antes de Afiliados.\n");
    exit(1);
}

if (!function_exists('render_autotube_page')) {
    fwrite(STDERR, "FAIL: Debe existir el renderizador de Autotube.\n");
    exit(1);
}

ob_start();
render_autotube_page();
$page = ob_get_clean();

assert_contains('src="https://lamami.online/autotube/"', $page, 'Autotube debe cargarse desde su URL pública.');
assert_contains('title="Panel Autotube"', $page, 'El iframe de Autotube debe tener un título accesible.');

echo "PASS: integración de Autotube en el menú\n";
