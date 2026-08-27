<?php
/**
 * Regresión TDD: la pestaña chat del CRM solo debe construir el iframe.
 *
 * Uso: php tools/test_comercial_chat_tab_lazy_loading.php
 *
 * Es una prueba estática porque render_comercial_page() no expone sus
 * dependencias de almacenamiento para sustituirlas por fixtures. Protege el
 * orden de evaluación: el guard de chat debe ocurrir antes de cargar datos que
 * solo necesitan las demás pestañas.
 */

declare(strict_types=1);

function comercial_chat_tab_assert(bool $condition, string $label): bool
{
    fwrite($condition ? STDOUT : STDERR, ($condition ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL);
    return $condition;
}

$source = (string) file_get_contents(dirname(__DIR__) . '/app/comercial.php');
$functionStart = strpos($source, 'function render_comercial_page()');
$functionEnd = $functionStart === false ? false : strpos($source, "\nfunction ", $functionStart + 1);
$page = $functionStart === false
    ? ''
    : substr($source, $functionStart, $functionEnd === false ? null : $functionEnd - $functionStart);
$chatGuardAt = strpos($page, "if (\$tab === 'chat')");

$pass = comercial_chat_tab_assert($functionStart !== false, 'existe render_comercial_page()')
    && comercial_chat_tab_assert($chatGuardAt !== false, 'render_comercial_page() contiene un guard específico para tab=chat');

// Estos accesos cargan conversaciones, KPIs, anuncios o configuración de
// procesos. El iframe de chat los carga por su propio endpoint, así que no
// pueden evaluarse antes de devolver la pestaña chat.
$expensiveLoads = [
    '$processes = comercial_get_processes()',
    '$lines = comercial_list_lines()',
    '$linesIndexed = comercial_list_lines_indexed()',
    '$threads = comercial_get_threads()',
    '$leads = comercial_get_leads()',
    '$summary = comercial_collect_summary()',
    '$anuncios = storage_read(\'anuncios.json\')',
];

foreach ($expensiveLoads as $load) {
    $loadAt = strpos($page, $load);
    $pass = comercial_chat_tab_assert(
        $loadAt === false || ($chatGuardAt !== false && $chatGuardAt < $loadAt),
        "tab=chat devuelve antes de {$load}"
    ) && $pass;
}

fwrite($pass ? STDOUT : STDERR, PHP_EOL . ($pass ? 'TODOS LOS TESTS OK' : 'HAY FALLOS ESPERADOS') . PHP_EOL);
exit($pass ? 0 : 1);
