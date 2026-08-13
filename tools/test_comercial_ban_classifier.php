<?php
// Test del clasificador de baneo comercial.
// Verifica que comercial_line_failure_counts_as_ban() distinga baneo real de
// fallos transitorios (reinicio WAHA, conexión, 5xx, rate limit).
//
// Uso: php tools/test_comercial_ban_classifier.php

require_once dirname(__DIR__) . '/app/bootstrap.php';

function ban_assert($expected, $httpCode, $errorText, $label) {
    $actual = comercial_line_failure_counts_as_ban($httpCode, $errorText);
    $ok = ($actual === $expected);
    $stream = $ok ? STDOUT : STDERR;
    fwrite($stream, ($ok ? '[OK] ' : '[FAIL] ') . $label
        . ' (esperado=' . var_export($expected, true) . ', obtenido=' . var_export($actual, true) . ')' . PHP_EOL);
    return $ok;
}

$pass = true;

// ── NO es ban: transitorios ────────────────────────────────────────────────
$pass = ban_assert(false, 201, '', 'HTTP 201 éxito → no ban') && $pass;
$pass = ban_assert(false, 0, 'Failed to connect: Connection refused', 'HTTP 0 conexión rehusada → no ban') && $pass;
$pass = ban_assert(false, 0, 'Operation timed out after 30001 ms', 'HTTP 0 timeout → no ban') && $pass;
$pass = ban_assert(false, 500, 'Internal Server Error', 'HTTP 500 → no ban') && $pass;
$pass = ban_assert(false, 503, 'Service Unavailable', 'HTTP 503 → no ban') && $pass;
$pass = ban_assert(false, 429, 'Too Many Requests', 'HTTP 429 rate limit → no ban') && $pass;
$pass = ban_assert(false, 422, 'HTTP 422 · {"error":"Session status is not as expected. Try again later or restart the session","session":"default","status":"STARTING","expected":["WORKING"]}', 'HTTP 422 status STARTING → no ban') && $pass;
$pass = ban_assert(false, 422, '{"error":"...","status":"SCAN_QR_CODE"}', 'HTTP 422 status SCAN_QR_CODE → no ban') && $pass;
$pass = ban_assert(false, 422, '{"error":"...","status":"STOPPED"}', 'HTTP 422 status STOPPED → no ban') && $pass;
$pass = ban_assert(false, 422, 'Session status is not as expected', '422 "Session status is not as expected" → no ban') && $pass;

// ── SÍ es ban: reales ──────────────────────────────────────────────────────
$pass = ban_assert(true, 401, 'Unauthorized', 'HTTP 401 → ban') && $pass;
$pass = ban_assert(true, 403, 'Forbidden', 'HTTP 403 → ban') && $pass;
$pass = ban_assert(true, 400, 'The account has been banned', 'error "banned" → ban') && $pass;
$pass = ban_assert(true, 400, 'You were logged out from WhatsApp', 'error "logged out" → ban') && $pass;
$pass = ban_assert(true, 400, 'Phone not authenticated', 'error "not authenticated" → ban') && $pass;
$pass = ban_assert(true, 400, 'unauthenticated session', 'error "unauthenticated" → ban') && $pass;

// ── Ambiguo 4xx sin señal explícita → no ban ──────────────────────────────
$pass = ban_assert(false, 400, 'Bad request', 'HTTP 400 sin señal → no ban') && $pass;
$pass = ban_assert(false, 404, 'Not Found', 'HTTP 404 → no ban') && $pass;

fwrite($pass ? STDOUT : STDERR, PHP_EOL . ($pass ? 'TODOS LOS TESTS OK' : 'HAY FALLOS') . PHP_EOL);
exit($pass ? 0 : 1);
