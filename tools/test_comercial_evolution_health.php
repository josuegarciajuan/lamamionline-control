<?php

require_once __DIR__ . '/../app/evolution/transport.php';
require_once __DIR__ . '/../app/comercial.php';

function assert_same_value($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nEsperado: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$open = comercial_evolution_health_from_response(array(
    'ok' => true,
    'http_code' => 200,
    'data' => array('state' => 'open'),
));
assert_same_value('up', $open['health_status'], 'Evolution open debe marcar la línea como up.');
assert_same_value('OPEN', $open['session_status'], 'Debe conservar el estado de Evolution.');

$connecting = comercial_evolution_health_from_response(array(
    'ok' => true,
    'http_code' => 200,
    'data' => array('state' => 'connecting'),
));
assert_same_value('starting', $connecting['health_status'], 'Evolution connecting debe marcar la línea como starting.');

$closed = comercial_evolution_health_from_response(array(
    'ok' => true,
    'http_code' => 200,
    'data' => array('state' => 'close'),
));
assert_same_value('down', $closed['health_status'], 'Evolution close debe marcar la línea como down.');

$failed = comercial_evolution_health_from_response(array(
    'ok' => false,
    'http_code' => 503,
    'data' => array(),
    'error' => 'Evolution no responde',
));
assert_same_value('down', $failed['health_status'], 'Un error de Evolution debe marcar la línea como down.');
assert_same_value('Evolution no responde', $failed['error'], 'Debe conservar el diagnóstico de Evolution.');
assert_same_value(3600, comercial_line_health_interval_seconds(), 'La comprobación periódica de salud debe conservar el intervalo de una hora.');

fwrite(STDOUT, "OK\n");
