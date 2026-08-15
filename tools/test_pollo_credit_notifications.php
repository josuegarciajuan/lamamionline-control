<?php
// Regression test for Pollo credit-state notifications.
// Uses a temporary DATA_PATH so production data is never read or written.

$projectPath = dirname(__DIR__);
$tmpBase = sys_get_temp_dir() . '/lamami-pollo-credit-test-' . getmypid() . '-' . uniqid();

define('BASE_PATH', $tmpBase);
define('APP_PATH', $projectPath . '/app');
define('DATA_PATH', $tmpBase . '/data');

require_once APP_PATH . '/helpers.php';
require_once APP_PATH . '/storage.php';
require_once APP_PATH . '/avisos.php';
require_once APP_PATH . '/publicista.php';

function pollo_test_assert($expected, $actual, $label) {
    $ok = ($expected === $actual);
    fwrite($ok ? STDOUT : STDERR, ($ok ? '[OK] ' : '[FAIL] ') . $label
        . ' (esperado=' . var_export($expected, true) . ', obtenido=' . var_export($actual, true) . ')' . PHP_EOL);
    return $ok;
}

function pollo_test_alerts() {
    return array_values(array_filter(storage_read('avisos.json'), function ($row) {
        return ($row['engine'] ?? '') === 'pollo';
    }));
}

function pollo_test_remove_dir($path) {
    if (!is_dir($path)) return;
    foreach (scandir($path) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $child = $path . '/' . $entry;
        if (is_dir($child)) {
            pollo_test_remove_dir($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
}

$pass = true;

try {
    bootstrap_storage();
    storage_write('settings.json', array(
        'pollo_accounts' => array(
            array('label' => 'Cuenta 1', 'cookie' => 'cookie-1'),
            array('label' => 'Cuenta 2', 'cookie' => 'cookie-2'),
        ),
    ));

    // Exhausted is a transition: the first periodic check notifies once.
    publicista_pollo_mark_exhausted('Cuenta 1');
    publicista_pollo_check_and_alert();
    $pass = pollo_test_assert(1, count(pollo_test_alerts()), 'agotamiento inicial emite un aviso') && $pass;

    // A later periodic run with the same exhausted state must not notify again.
    $alerts = pollo_test_alerts();
    $alerts[0]['created_at'] = gmdate('Y-m-d H:i:s', time() - (48 * 3600));
    storage_write('avisos.json', $alerts);
    publicista_pollo_check_and_alert();
    $pass = pollo_test_assert(1, count(pollo_test_alerts()), 'agotamiento sin cambios no repite aviso diario') && $pass;

    // ConfigM's "Marcar con créditos" transition emits one recovery notice.
    publicista_pollo_mark_recovered('Cuenta 1');
    publicista_pollo_check_and_alert();
    $alerts = pollo_test_alerts();
    $pass = pollo_test_assert(2, count($alerts), 'recuperación emite un aviso') && $pass;
    $pass = pollo_test_assert('Pollo.ai: cuenta Cuenta 1 con créditos de nuevo', $alerts[1]['title'] ?? '', 'aviso de recuperación identifica la cuenta') && $pass;

    publicista_pollo_check_and_alert();
    $pass = pollo_test_assert(2, count(pollo_test_alerts()), 'recuperación sin cambios no repite aviso') && $pass;
} finally {
    pollo_test_remove_dir($tmpBase);
}

fwrite($pass ? STDOUT : STDERR, PHP_EOL . ($pass ? 'TODOS LOS TESTS OK' : 'HAY FALLOS') . PHP_EOL);
exit($pass ? 0 : 1);
