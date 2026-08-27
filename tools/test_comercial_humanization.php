<?php
// Tests de las reglas deterministas de humanización del bot comercial.
// Uso: php tools/test_comercial_humanization.php

require_once dirname(__DIR__) . '/app/comercial_humanize.php';

function humanization_assert(string $expected, string $input, string $label): bool {
    $actual = comercial_humanize_outbound_message($input);
    $ok = $actual === $expected;
    $stream = $ok ? STDOUT : STDERR;
    fwrite($stream, ($ok ? '[OK] ' : '[FAIL] ') . $label
        . ' (esperado=' . var_export($expected, true) . ', obtenido=' . var_export($actual, true) . ')' . PHP_EOL);
    return $ok;
}

$pass = true;

$pass = humanization_assert(
    'Te cuento lo que necesitas y lo dejamos claro.',
    '¡Te cuento lo que necesitas y lo dejamos claro.',
    'elimina signo de apertura de exclamación'
) && $pass;
$pass = humanization_assert(
    'Te paso el detalle que encaja con lo que buscas?',
    '¿Te paso el detalle que encaja con lo que buscas?',
    'elimina signo de apertura de interrogación'
) && $pass;
$pass = humanization_assert(
    'Con dos fotos se puede ver bastante bien el margen de mejora.',
    'Con dos fotos se puede ver bastante bien el margen de mejora. ¿Quieres que te explique algo más?',
    'elimina cierre genérico condescendiente'
) && $pass;
$pass = humanization_assert(
    'El precio es 50 euros por semana. La prueba sirve para verlo en tu número.',
    'El precio es 50 euros por semana. La prueba sirve para verlo en tu número.',
    'conserva información concreta y útil'
) && $pass;

fwrite($pass ? STDOUT : STDERR, PHP_EOL . ($pass ? 'TODOS LOS TESTS OK' : 'HAY FALLOS') . PHP_EOL);
exit($pass ? 0 : 1);
