<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/auth.php';
require_once dirname(__DIR__) . '/app/telefonos_waha_service.php';

$failures = 0;

function twa_test_assert(bool $condition, string $label): void
{
    global $failures;
    if ($condition) {
        fwrite(STDOUT, "[OK] {$label}\n");
        return;
    }
    $failures++;
    fwrite(STDERR, "[FAIL] {$label}\n");
}

function twa_test_same($expected, $actual, string $label): void
{
    twa_test_assert($expected === $actual, $label . ' (esperado=' . var_export($expected, true) . ', obtenido=' . var_export($actual, true) . ')');
}

function twa_test_response(string $status, string $me = '', int $httpCode = 200): array
{
    $body = ['status' => $status];
    if ($me !== '') $body['me'] = ['id' => $me];
    return ['ok' => true, 'http_code' => $httpCode, 'body' => json_encode($body)];
}

$commercialSettings = [
    'waha_host' => 'http://100.113.76.93',
    'waha_api_key' => 'test-key',
    'waha_session' => 'ignored',
    'curl_timeout_sec' => '8',
];
$personalSettings = [
    'waha_host' => 'http://100.117.92.74',
    'waha_api_key' => 'personal-test-key',
    'curl_timeout_sec' => '8',
];

// Authentication is explicit: only josue is administrator, regardless of login mechanism.
foreach (['lite', 'telefono', 'coche', 'nuria', ''] as $username) {
    $_SESSION = ['logged_in' => true, 'username' => $username];
    twa_test_assert(!auth_is_admin(), "deniega identidad no admin {$username}");
}
$_SESSION = ['logged_in' => true, 'username' => 'lite'];
twa_test_assert(!auth_can_manage_telefonos(), 'lite permanece bloqueado antes de desbloquear adicionales');
$_SESSION['josue_adicionales_unlocked'] = true;
twa_test_assert(auth_can_manage_telefonos(), 'lite gestiona teléfonos tras desbloquear adicionales');
$_SESSION = ['logged_in' => true, 'username' => 'telefono'];
twa_test_assert(auth_can_manage_telefonos(), 'telefono conserva gestión de teléfonos');
$_SESSION = ['logged_in' => true, 'username' => 'josue', 'auth_via_device' => true];
twa_test_assert(auth_is_admin(), 'trusted-device josue conserva acceso admin');
$_SESSION = [];
twa_test_same(401, telefonos_waha_authorize(false, false)['status'], 'API distingue no autenticado');
twa_test_same(403, telefonos_waha_authorize(true, false)['status'], 'API deniega autenticado no admin');
twa_test_same(200, telefonos_waha_authorize(true, true)['status'], 'API permite admin');

// Immutable SSRF allowlists.
foreach (telefonos_waha_allowed_hosts() as $host) {
    twa_test_assert(telefonos_waha_host_is_allowed($host), "host desplegado permitido {$host}");
}
foreach (['http://127.0.0.1', 'http://169.254.169.254', 'http://100.117.92.74/', 'https://100.117.92.74', 'http://evil.test'] as $host) {
    twa_test_assert(!telefonos_waha_host_is_allowed($host), "host no inventariado rechazado {$host}");
}
foreach (range(3000, 3011) as $port) {
    twa_test_assert(telefonos_waha_port_is_allowed((string)$port, false), "puerto WAHA permitido {$port}");
}
twa_test_assert(telefonos_waha_port_is_allowed('3031', false), 'puerto personal permitido');
twa_test_assert(telefonos_waha_port_is_allowed('', true), 'puerto vacío permitido al guardar');
foreach (['', '2999', '3012', '65535', '3000x'] as $port) {
    twa_test_assert(!telefonos_waha_port_is_allowed($port, false), "puerto endpoint rechazado {$port}");
}

// Phone normalization is strict and deterministic.
twa_test_same('34600111222', telefonos_waha_normalize_phone('+34 600 111 222'), 'normaliza E.164 formateado');
twa_test_same('34600111222', telefonos_waha_normalize_phone('34600111222'), 'acepta internacional canónico sin +');
twa_test_same('34600111222', telefonos_waha_normalize_phone('600 111 222'), 'añade prefijo 34 a español de nueve dígitos');
foreach (['0034600111222', '60011122', '+600111222', '600111222 ext 4', '600111222x4', '123-45', ''] as $phone) {
    twa_test_same(null, telefonos_waha_normalize_phone($phone), "rechaza teléfono ambiguo {$phone}");
}

$rows = [
    ['id' => 'target', 'nombre' => 'Destino', 'tfono' => '+34 600 111 222', 'waha_port' => '3002', 'waha' => 'target-session', 'uso' => 'bot casa'],
    ['id' => 'source-b', 'nombre' => 'Origen B', 'tfono' => '600333444', 'waha_port' => '3004', 'waha' => 'session-b', 'uso' => 'comercial'],
    ['id' => 'source-a', 'nombre' => 'Origen A', 'tfono' => '600222333', 'waha_port' => '3031', 'waha' => 'must-be-ignored', 'uso' => 'personal'],
    ['id' => 'same-phone', 'nombre' => 'Duplicada', 'tfono' => '600111222', 'waha_port' => '3010', 'waha' => 'duplicate', 'uso' => 'comercial'],
    ['id' => 'same-identity', 'nombre' => 'Misma sesión', 'tfono' => '699999999', 'waha_port' => '3002', 'waha' => 'target-session', 'uso' => 'comercial'],
    ['id' => 'bad-port', 'nombre' => 'Puerto inválido', 'tfono' => '611111111', 'waha_port' => '3020', 'waha' => 'ignored', 'uso' => 'comercial'],
    ['id' => 'inactive', 'nombre' => 'Inactiva', 'tfono' => '611222333', 'waha_port' => '3005', 'waha' => 'inactive', 'uso' => 'INACTIVO'],
];

$personalConfig = telefonos_waha_line_config($rows[2], $commercialSettings, $personalSettings);
twa_test_same('default', $personalConfig['session'], 'puerto 3031 fuerza sesión default');
twa_test_same('http://100.117.92.74', $personalConfig['settings']['waha_host'], 'puerto 3031 usa host personal permitido');

// Las instancias desplegadas son WAHA Core: solo admiten la sesión 'default'.
// El campo waha de la fila es una etiqueta, nunca el nombre real de la sesión.
$commercialConfig = telefonos_waha_line_config($rows[1], $commercialSettings, $personalSettings);
twa_test_same('default', $commercialConfig['session'], 'línea comercial usa sesión default (WAHA Core)');
twa_test_same('http://100.113.76.93', $commercialConfig['settings']['waha_host'], 'línea comercial usa host comercial');

// Runtime me.id exclusion and immediate deterministic fallback.
$events = [];
$dedup = [];
$result = telefonos_waha_identify(
    'target',
    $rows,
    $commercialSettings,
    $personalSettings,
    function (array $config, array $row) use (&$events): array {
        $events[] = 'status:' . $row['id'];
        if ($row['id'] === 'source-a') return twa_test_response('WORKING', '34600111222@c.us');
        return twa_test_response('CONNECTED', '34600333444@c.us');
    },
    function (array $config, string $phone, string $message, array $row) use (&$events): array {
        $events[] = 'send:' . $row['id'] . ':' . $phone . ':' . $message;
        return ['ok' => true, 'http_code' => 201, 'body' => '{}'];
    },
    function (string $key, int $now, int $window) use (&$dedup): ?array {
        return isset($dedup[$key]) && $dedup[$key]['at'] >= $now - $window ? $dedup[$key]['result'] : null;
    },
    function (string $key, array $value, int $now) use (&$dedup): void {
        $dedup[$key] = ['at' => $now, 'result' => $value];
    },
    ['now' => static fn(): int => 1000, 'clock' => static fn(): float => 10.0, 'max_candidates' => 4, 'budget_seconds' => 20]
);
twa_test_same(200, $result['status'], 'identificar envía desde fuente válida');
twa_test_same('Origen B', $result['source_label'], 'excluye fuente cuyo me.id real es destino');
twa_test_same([
    'status:source-a',
    'status:source-b',
    'send:source-b:34600111222:Hola Destino (+34 600 111 222), ya sé quién eres',
], $events, 'health y envío son inmediatos, deterministas y con texto exacto');
twa_test_assert(!in_array('status:inactive', $events, true), 'uso=inactivo no participa como emisor');

$eventCount = count($events);
$deduplicated = telefonos_waha_identify(
    'target', $rows, $commercialSettings, $personalSettings,
    function () use (&$events): array { $events[] = 'unexpected-status'; return []; },
    function () use (&$events): array { $events[] = 'unexpected-send'; return []; },
    function (string $key, int $now, int $window) use (&$dedup): ?array {
        return isset($dedup[$key]) && $dedup[$key]['at'] >= $now - $window ? $dedup[$key]['result'] : null;
    },
    static function (): void {},
    ['now' => static fn(): int => 1001, 'clock' => static fn(): float => 11.0]
);
twa_test_assert(!empty($deduplicated['deduplicated']), 'reintento devuelve éxito deduplicado');
twa_test_same($eventCount, count($events), 'deduplicación evita status y envío reales');

// Failed send falls through immediately to the next candidate.
$fallbackEvents = [];
$fallback = telefonos_waha_identify(
    'target', $rows, $commercialSettings, $personalSettings,
    function (array $config, array $row) use (&$fallbackEvents): array {
        $fallbackEvents[] = 'status:' . $row['id'];
        return twa_test_response('CONNECTED', $row['id'] === 'source-a' ? '34600222333@c.us' : '34600333444@c.us');
    },
    function (array $config, string $phone, string $message, array $row) use (&$fallbackEvents): array {
        $fallbackEvents[] = 'send:' . $row['id'];
        return ['ok' => true, 'http_code' => $row['id'] === 'source-a' ? 500 : 200, 'body' => '{}'];
    },
    static fn(): ?array => null,
    static function (): void {},
    ['now' => static fn(): int => 2000, 'clock' => static fn(): float => 20.0]
);
twa_test_same(['status:source-a', 'send:source-a', 'status:source-b', 'send:source-b'], $fallbackEvents, 'fallback no comprueba todas antes de enviar');
twa_test_same('Origen B', $fallback['source_label'], 'fallback informa fuente final');

// HTTP 200 con error en el body se trata como envío fallido y cae al siguiente candidato.
$errorBodyEvents = [];
$errorBody = telefonos_waha_identify(
    'target', $rows, $commercialSettings, $personalSettings,
    function (array $config, array $row) use (&$errorBodyEvents): array {
        $errorBodyEvents[] = 'status:' . $row['id'];
        return twa_test_response('CONNECTED', $row['id'] === 'source-a' ? '34600222333@c.us' : '34600333444@c.us');
    },
    function (array $config, string $phone, string $message, array $row) use (&$errorBodyEvents): array {
        $errorBodyEvents[] = 'send:' . $row['id'];
        if ($row['id'] === 'source-a') {
            return ['ok' => true, 'http_code' => 200, 'body' => json_encode(['error' => 'sesión inválida'])];
        }
        return ['ok' => true, 'http_code' => 200, 'body' => '{}'];
    },
    static fn(): ?array => null,
    static function (): void {},
    ['now' => static fn(): int => 2200, 'clock' => static fn(): float => 22.0]
);
twa_test_same(['status:source-a', 'send:source-a', 'status:source-b', 'send:source-b'], $errorBodyEvents, 'envío 200 con error en body cae al siguiente candidato');
twa_test_same('Origen B', $errorBody['source_label'], 'envío 200 con error en body informa fuente final');

$exceptionEvents = [];
$exceptionFallback = telefonos_waha_identify(
    'target', $rows, $commercialSettings, $personalSettings,
    function (array $config, array $row) use (&$exceptionEvents): array {
        $exceptionEvents[] = 'status:' . $row['id'];
        if ($row['id'] === 'source-a') throw new RuntimeException('detalle interno');
        return twa_test_response('CONNECTED', '34600333444@c.us');
    },
    function (array $config, string $phone, string $message, array $row) use (&$exceptionEvents): array {
        $exceptionEvents[] = 'send:' . $row['id'];
        return ['ok' => true, 'http_code' => 200, 'body' => '{}'];
    },
    static fn(): ?array => null,
    static function (): void {},
    ['now' => static fn(): int => 2100, 'clock' => static fn(): float => 21.0]
);
twa_test_same(200, $exceptionFallback['status'], 'excepción de una fuente no aborta fallback');
twa_test_same(['status:source-a', 'status:source-b', 'send:source-b'], $exceptionEvents, 'fallback aísla fallo inesperado por fuente');

// Candidate cap is enforced without any real HTTP.
$manyRows = [$rows[0]];
for ($i = 0; $i < 8; $i++) {
    $manyRows[] = ['id' => 'src-' . $i, 'nombre' => 'S' . $i, 'tfono' => '61' . str_pad((string)$i, 7, '0', STR_PAD_LEFT), 'waha_port' => (string)(3003 + ($i % 8)), 'waha' => 's' . $i];
}
$capCalls = 0;
$capped = telefonos_waha_identify(
    'target', $manyRows, $commercialSettings, $personalSettings,
    function () use (&$capCalls): array { $capCalls++; return twa_test_response('STOPPED'); },
    static fn(): array => ['ok' => false, 'http_code' => 500],
    static fn(): ?array => null,
    static function (): void {},
    ['now' => static fn(): int => 3000, 'clock' => static fn(): float => 30.0, 'max_candidates' => 4, 'budget_seconds' => 20]
);
twa_test_same(4, $capCalls, 'límite de candidatos aplicado');
twa_test_same(409, $capped['status'], 'sin fuente conectada devuelve conflicto');
twa_test_assert(
    strpos($capped['error'] ?? '', 'Líneas comprobadas') !== false && strpos($capped['error'] ?? '', 'STOPPED') !== false,
    'conflicto detalla líneas comprobadas y su estado'
);

$budgetCalls = 0;
$clockValues = [0.0, 0.0, 21.0, 21.0];
$budgeted = telefonos_waha_identify(
    'target', $manyRows, $commercialSettings, $personalSettings,
    function () use (&$budgetCalls): array { $budgetCalls++; return twa_test_response('CONNECTED', '34610000000@c.us'); },
    function () use (&$budgetCalls): array { $budgetCalls++; return ['ok' => true, 'http_code' => 200]; },
    static fn(): ?array => null,
    static function (): void {},
    [
        'now' => static fn(): int => 3000,
        'clock' => function () use (&$clockValues): float { return array_shift($clockValues) ?? 21.0; },
        'max_candidates' => 4,
        'budget_seconds' => 20,
    ]
);
twa_test_same(1, $budgetCalls, 'presupuesto temporal impide envío tardío y más candidatos');
twa_test_same(409, $budgeted['status'], 'presupuesto agotado termina sin envío');

// Restart is idempotent on missing DELETE and strict on recreated state.
$targetConfig = telefonos_waha_line_config($rows[0], $commercialSettings, $personalSettings);
$restartCalls = [];
$restart = telefonos_waha_restart(
    $targetConfig,
    function (array $config, string $method, string $path, ?array $payload) use (&$restartCalls): array {
        $restartCalls[] = compact('method', 'path', 'payload');
        if ($method === 'DELETE') return ['ok' => true, 'http_code' => 404, 'body' => '{}'];
        if ($method === 'GET') return twa_test_response('SCAN_QR_CODE');
        return ['ok' => true, 'http_code' => 201, 'body' => '{}'];
    },
    static function (): void {}
);
twa_test_same(200, $restart['status'], 'DELETE 404 permite recreación');
twa_test_same('api/sessions/default', $restartCalls[0]['path'], 'reinicio usa sesión default (WAHA Core)');
twa_test_same('default', $restartCalls[1]['payload']['name'], 'recreación usa sesión default (WAHA Core)');

$stopped = telefonos_waha_restart(
    $targetConfig,
    static function (array $config, string $method): array {
        if ($method === 'GET') return twa_test_response('STOPPED');
        return ['ok' => true, 'http_code' => $method === 'DELETE' ? 204 : 201, 'body' => '{}'];
    },
    static function (): void {}
);
twa_test_same(502, $stopped['status'], 'reinicio rechaza STOPPED');

// Static integration contracts: no bootstrap execution, no production storage writes.
$root = dirname(__DIR__);
$apiSource = (string)file_get_contents($root . '/telefonos_waha_api.php');
$viewsSource = (string)file_get_contents($root . '/app/views.php');
$commercialSource = (string)file_get_contents($root . '/app/comercial.php');
$actionsSource = (string)file_get_contents($root . '/app/actions.php');
$publicistaSource = (string)file_get_contents($root . '/app/publicista.php');
twa_test_assert(strpos($apiSource, 'auth_can_manage_telefonos()') !== false, 'dispatch comprueba permiso de teléfonos');
twa_test_assert(strpos($apiSource, 'auth_can_manage_telefonos()') < strpos($apiSource, "storage_read('telefonos.json')"), '403 ocurre antes de lookup');
twa_test_assert(strpos($apiSource, 'catch (Throwable $e)') !== false, 'dispatch captura Throwable');
twa_test_assert(strpos($viewsSource, 'Legacy WAHA script') === false, 'UI no conserva JS legado comentado');
twa_test_assert(strpos($viewsSource, 'identifyResult[id] = "Enviado desde "') !== false && strpos($viewsSource, 'result.textContent = identifyResult[id]') !== false, 'UI muestra éxito seguro incluso deduplicado');
twa_test_assert(strpos($viewsSource, 'body:postBody("restart", "telefono_id", id)') !== false, 'UI reinicia por POST y telefono_id');
$evoStatusBlockStart = strpos($apiSource, "if (\$action === 'evo_status')");
$evoStatusBlockEnd = strpos($apiSource, "if (\$action === 'evo_qr')", $evoStatusBlockStart === false ? 0 : $evoStatusBlockStart);
$evoStatusBlock = ($evoStatusBlockStart !== false && $evoStatusBlockEnd !== false)
    ? substr($apiSource, $evoStatusBlockStart, $evoStatusBlockEnd - $evoStatusBlockStart)
    : '';
twa_test_assert(strpos($evoStatusBlock, 'evolution_ensure_webhook') === false, 'consultar estado Evolution no crea instancia ni genera QR');
twa_test_assert(substr_count($publicistaSource, "strtolower(\$uso) === 'inactivo'") >= 2, 'automatizaciones de estados excluyen uso=inactivo');

foreach ([
    [$viewsSource, 'save_telefono'], [$viewsSource, 'delete_telefono'],
    [$commercialSource, 'save_telefono'], [$commercialSource, 'delete_telefono'],
    [$commercialSource, 'save_comercial_settings'],
] as [$source, $action]) {
    $pattern = '#<form[^>]*>.*?name="action" value="' . preg_quote($action, '#') . '".*?name="csrf_token"#s';
    twa_test_assert((bool)preg_match($pattern, $source), "formulario activo {$action} incluye CSRF");
}
foreach (['action_save_telefono', 'action_delete_telefono', 'action_save_comercial_settings'] as $function) {
    $start = strpos($actionsSource, 'function ' . $function . '(');
    $slice = $start === false ? '' : substr($actionsSource, $start, 700);
    $permissionCheck = in_array($function, ['action_save_telefono', 'action_delete_telefono'], true)
        ? 'auth_can_manage_telefonos()'
        : 'auth_is_admin()';
    twa_test_assert(strpos($slice, $permissionCheck) !== false && strpos($slice, 'csrf_validate') !== false, "{$function} aplica permiso y CSRF en servidor");
}

exit($failures === 0 ? 0 : 1);
