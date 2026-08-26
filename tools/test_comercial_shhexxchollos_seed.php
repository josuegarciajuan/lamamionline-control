<?php

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('DATA_PATH', sys_get_temp_dir() . '/comercial-shhexxchollos-' . uniqid('', true));

mkdir(DATA_PATH, 0775, true);
require_once APP_PATH . '/helpers.php';
require_once APP_PATH . '/db.php';
require_once APP_PATH . '/storage.php';
require_once APP_PATH . '/comercial.php';

function shhexxchollos_assert($condition, $label) {
    $stream = $condition ? STDOUT : STDERR;
    fwrite($stream, ($condition ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL);
    return $condition;
}

$pass = true;
$seed = comercial_default_process_seed('shhexxchollos');
$pass = shhexxchollos_assert($seed['slug'] === 'shhexxchollos', 'la semilla usa el slug esperado') && $pass;
$pass = shhexxchollos_assert($seed['nombre'] === 'Shhexxchollos', 'la semilla usa el nombre esperado') && $pass;
$pass = shhexxchollos_assert((int)$seed['enabled'] === 0, 'la semilla nace deshabilitada') && $pass;
$pass = shhexxchollos_assert((float)$seed['daily_target_percent'] === 0.0, 'la semilla no tiene objetivo diario') && $pass;
$pass = shhexxchollos_assert($seed['source_mysql_query'] === '', 'la semilla no tiene consulta MySQL') && $pass;
$pass = shhexxchollos_assert($seed['source_queue_files'] === array(), 'la semilla no tiene colas') && $pass;
$pass = shhexxchollos_assert($seed['assigned_line_ids'] === array(), 'la semilla no tiene líneas asignadas') && $pass;

$defaults = comercial_build_default_processes();
$defaultSlugs = array_column($defaults, 'slug');
$pass = shhexxchollos_assert(in_array('shhexxchollos', $defaultSlugs, true), 'la semilla forma parte de los procesos por defecto') && $pass;

storage_write('comercial_processes.json', array(comercial_default_process_seed('lamami')));
$migrated = comercial_get_processes();
$matches = array_values(array_filter($migrated, function ($process) {
    return ($process['slug'] ?? '') === 'shhexxchollos';
}));
$pass = shhexxchollos_assert(count($matches) === 1, 'la migración añade el proceso una sola vez') && $pass;
if (count($matches) === 1) {
    $process = $matches[0];
    $pass = shhexxchollos_assert((int)$process['enabled'] === 0, 'la migración mantiene enabled=0') && $pass;
    $pass = shhexxchollos_assert((float)$process['daily_target_percent'] === 0.0, 'la migración mantiene daily_target_percent=0') && $pass;
    $pass = shhexxchollos_assert($process['source_mysql_query'] === '', 'la migración no añade consulta MySQL') && $pass;
    $pass = shhexxchollos_assert($process['source_queue_files'] === array(), 'la migración no añade colas') && $pass;
    $pass = shhexxchollos_assert($process['assigned_line_ids'] === array(), 'la migración no añade líneas') && $pass;
}

$migratedAgain = comercial_get_processes();
$matchesAgain = array_values(array_filter($migratedAgain, function ($process) {
    return ($process['slug'] ?? '') === 'shhexxchollos';
}));
$pass = shhexxchollos_assert(count($matchesAgain) === 1, 'la migración es idempotente') && $pass;

@unlink(DATA_PATH . '/comercial_processes.json');
@unlink(DATA_PATH . '/comercial_events.jsonl');
@rmdir(DATA_PATH);

fwrite($pass ? STDOUT : STDERR, $pass ? 'TODOS LOS TESTS OK' . PHP_EOL : 'HAY FALLOS' . PHP_EOL);
exit($pass ? 0 : 1);
