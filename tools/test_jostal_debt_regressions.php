<?php

$projectPath = dirname(__DIR__);
$storageChild = (($argv[1] ?? '') === '--storage-child');
$testBase = $storageChild
    ? (string)($argv[2] ?? '')
    : sys_get_temp_dir() . '/lamami-jostal-test-' . getmypid() . '-' . uniqid('', true);
if ($testBase === '') exit(64);
define('BASE_PATH', $testBase);
define('APP_PATH', $projectPath . '/app');
define('DATA_PATH', $testBase . '/data');
date_default_timezone_set('Europe/Madrid');
if (!is_dir(DATA_PATH) && !@mkdir(DATA_PATH, 0775, true)) exit(66);
require_once APP_PATH . '/helpers.php';
require_once APP_PATH . '/storage.php';

class JostalTestAssertion extends RuntimeException {}

if ($storageChild) {
    $operation = (string)($argv[3] ?? '');
    $file = (string)($argv[4] ?? 'storage_race.json');
    if ($operation === 'mutate-hold') {
        $marker = (string)($argv[5] ?? '');
        $gate = (string)($argv[6] ?? '');
        $result = storage_json_mutate_row_atomic($file, 'target', function ($row) use ($marker, $gate) {
            file_put_contents($marker, 'locked');
            $deadline = microtime(true) + 5;
            while (!is_file($gate) && microtime(true) < $deadline) usleep(10000);
            if (!is_file($gate)) return array('ok' => false, 'error' => 'timeout');
            $row['mutated'] = true;
            return array('ok' => true, 'row' => $row);
        });
        exit(!empty($result['ok']) ? 0 : 2);
    }
    if ($operation === 'mutate-after-gate') {
        $gate = (string)($argv[5] ?? '');
        $deadline = microtime(true) + 5;
        while (!is_file($gate) && microtime(true) < $deadline) usleep(10000);
        if (!is_file($gate)) exit(3);
        $result = storage_json_mutate_row_atomic($file, 'target', function ($row) {
            $row['mutated'] = true;
            return array('ok' => true, 'row' => $row);
        });
        exit(!empty($result['ok']) ? 0 : 2);
    }
    if ($operation === 'mutate-direct') {
        $result = storage_json_mutate_row_atomic($file, 'target', function ($row) {
            $row['mutated'] = true;
            return array('ok' => true, 'row' => $row);
        });
        exit(!empty($result['ok']) ? 0 : 2);
    }
    if ($operation === 'delete') {
        storage_delete($file, 'target');
        exit(0);
    }
    if ($operation === 'delete-direct') {
        exit(storage_json_delete_direct($file, 'target') ? 0 : 2);
    }
    if ($operation === 'upsert') {
        $row = json_decode(base64_decode((string)($argv[5] ?? ''), true), true);
        if (!is_array($row)) exit(4);
        exit(storage_upsert($file, $row) ? 0 : 2);
    }
    if ($operation === 'write') {
        $rows = json_decode(base64_decode((string)($argv[5] ?? ''), true), true);
        if (!is_array($rows)) exit(4);
        storage_write($file, $rows);
        exit(0);
    }
    if ($operation === 'write-direct') {
        $rows = json_decode(base64_decode((string)($argv[5] ?? ''), true), true);
        if (!is_array($rows)) exit(4);
        exit(storage_json_write_direct($file, $rows) ? 0 : 2);
    }
    exit(65);
}

function jostal_test_remove_dir($path) {
    if (!is_dir($path)) return;
    foreach (scandir($path) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $child = $path . '/' . $entry;
        if (is_dir($child)) jostal_test_remove_dir($child);
        else unlink($child);
    }
    rmdir($path);
}

function jostal_test_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] " . $message . PHP_EOL);
        throw new JostalTestAssertion($message);
    }
    fwrite(STDOUT, "[OK] " . $message . PHP_EOL);
}

function jostal_test_process_start($testBase, $operation, $args = array(), $user = '') {
    $script = __FILE__;
    $command = array_merge(array(PHP_BINARY, $script, '--storage-child', $testBase, $operation), $args);
    if ($user !== '') {
        $boundScript = '/var/www/html/atupuerta/control/tools/test_jostal_debt_regressions.php';
        if (is_readable($boundScript)) $script = $boundScript;
        $command = array_merge(array(
            '/usr/sbin/runuser', '-u', $user, '--', PHP_BINARY,
            '-d', 'opcache.file_cache_only=0', '-d', 'opcache.enable_cli=0',
            $script, '--storage-child', $testBase, $operation,
        ), $args);
    }
    $pipes = array();
    $process = proc_open($command, array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    ), $pipes);
    if (!is_resource($process)) throw new RuntimeException('No se pudo iniciar proceso hijo.');
    fclose($pipes[0]);
    return array($process, $pipes);
}

function jostal_test_process_wait($child, $expectedExit, $message) {
    list($process, $pipes) = $child;
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    jostal_test_assert($exit === $expectedExit, $message . ' (exit=' . $exit . ', out=' . trim($stdout) . ', err=' . trim($stderr) . ')');
}

function jostal_test_wait_file($path, $message) {
    $deadline = microtime(true) + 5;
    while (!is_file($path) && microtime(true) < $deadline) usleep(10000);
    jostal_test_assert(is_file($path), $message);
}

function jostal_test_read_rows($file) {
    $rows = json_decode(file_get_contents(DATA_PATH . '/' . $file), true);
    return is_array($rows) ? $rows : array();
}

function jostal_test_find_row($rows, $id) {
    foreach ($rows as $row) {
        if (is_array($row) && (string)($row['id'] ?? '') === (string)$id) return $row;
    }
    return null;
}

function jostal_test_close($actual, $expected, $message) {
    jostal_test_assert(abs((float)$actual - (float)$expected) < 0.005, $message . ' (actual: ' . $actual . ', esperado: ' . $expected . ')');
}

function jostal_test_clienta($id, $price = 100) {
    return array(
        'id' => $id,
        'nombre' => 'Prueba',
        'modo' => 'alquiler',
        'precio_semanal' => $price,
        'rent_due_weekday' => 1,
        'periodos_estancia' => array(array('entrada' => '2026-07-27', 'salida' => '')),
    );
}

function jostal_test_lead($id, $clientaId, $date, $amount, $tipo = 'alquiler') {
    return array(
        'id' => $id,
        'clienta_id' => $clientaId,
        'created_at' => $date . ' 12:00:00',
        'precio' => $amount,
        'observacion' => $tipo === 'alquiler' ? 'alquiler' : 'cliente',
        'concepto_tipo' => $tipo,
        'concepto_fuente' => 'manual',
    );
}

try {
// Fecha controlada: los resultados no dependen del día en que se ejecute el test.
$asOf = '2026-08-15';

// El rango es inclusivo por fechas, pero conserva periodos semanales [inicio, fin).
$rangeClient = jostal_test_clienta('jcli_test_range');
$rangeData = jostal_compute_deuda(
    $rangeClient,
    array(jostal_test_lead('lead_boundary', $rangeClient['id'], '2026-08-10', 100)),
    array(),
    '2026-08-10',
    '2026-08-10',
    $asOf
);
jostal_test_assert(empty($rangeData['error']), 'el rango de un día en un límite semanal produce informe');
jostal_test_assert(count($rangeData['weeks']) === 1, 'desde/hasta incluye la semana que comienza en la fecha límite');
jostal_test_assert($rangeData['weeks'][0]['ps'] === '2026-08-10' && $rangeData['weeks'][0]['pe'] === '2026-08-17', 'el periodo semanal usa límites [inicio, fin) inequívocos');
jostal_test_close($rangeData['pagado_total_semana'], 100, 'hasta incluye el pago realizado ese mismo día');
$rangeText = jostal_texto_deuda('Prueba', $rangeData, '2026-08-10', '2026-08-10', 'semana');
jostal_test_assert(strpos($rangeText, '1 semana(s)') !== false && strpos($rangeText, 'pagó 100,00 €') !== false, 'WhatsApp usa exactamente el rango ya recalculado');
$beforeEntry = jostal_compute_deuda($rangeClient, array(), array(), '2026-07-01', '2026-07-10', $asOf);
jostal_test_assert(($beforeEntry['error'] ?? '') === 'sin_vencimientos', 'un rango anterior a la entrada no genera deuda');

// El total pagado semanal es dinero real, no min(debe, pagado).
$surplusClient = jostal_test_clienta('jcli_test_surplus');
$surplusData = jostal_compute_deuda(
    $surplusClient,
    array(jostal_test_lead('lead_surplus', $surplusClient['id'], '2026-08-05', 150)),
    array(),
    '2026-08-03',
    '2026-08-09',
    $asOf
);
jostal_test_close($surplusData['pagado_total_semana'], 150, 'pagado_total_semana incluye el sobrante realmente pagado');
$surplusMonth = reset($surplusData['resumen_meses_semana']);
jostal_test_close($surplusMonth['diff'], 0, 'la deuda mensual semanal nunca usa signo negativo');
$unpaidData = jostal_compute_deuda($surplusClient, array(), array(), '2026-08-03', '2026-08-09', $asOf);
$unpaidMonth = reset($unpaidData['resumen_meses_semana']);
jostal_test_close($unpaidMonth['diff'], 100, 'la deuda mensual semanal suma el déficit con signo positivo');

// Una compensación semanal anterior al perdón no puede cruzar el corte.
$forgivenClient = jostal_test_clienta('jcli_0428b6e4', 150);
$forgivenData = jostal_compute_deuda(
    $forgivenClient,
    array(jostal_test_lead('lead_before_cutoff', $forgivenClient['id'], '2026-08-09', 260)),
    array(),
    '2026-08-03',
    '2026-08-15',
    $asOf
);
jostal_test_close($forgivenData['deuda_total_semana'], 150, 'el sobrante anterior al perdón no compensa deuda posterior');
jostal_test_close($forgivenData['deuda_total'], 150, 'el total FIFO post-perdón no reincorpora semanas perdonadas');

// ignorar_actual elimina el pago antes de cualquier asignación o compensación.
$ignoredClient = jostal_test_clienta('jcli_2bd0670c', 130);
$ignoredData = jostal_compute_deuda(
    $ignoredClient,
    array(jostal_test_lead('lead_ignored_current', $ignoredClient['id'], '2026-08-11', 260)),
    array(),
    '2026-08-10',
    '2026-08-15',
    $asOf
);
jostal_test_close($ignoredData['deuda_total'], 130, 'ignorar_actual excluye el pago actual antes de FIFO');
jostal_test_close($ignoredData['deuda_total_semana'], 130, 'ignorar_actual excluye el pago actual antes de compensación semanal');
jostal_test_close($ignoredData['pagado_total_semana'], 0, 'el pago absorbido por deuda perdonada no cuenta como pago del rango');

// El saldo a favor tras el perdón usa solo pagos post-corte y debe coincidir en PHP/JS.
$favorData = jostal_compute_deuda(
    $forgivenClient,
    array(jostal_test_lead('lead_after_cutoff', $forgivenClient['id'], '2026-08-10', 400)),
    array(),
    '2026-08-10',
    '2026-08-15',
    $asOf
);
jostal_test_close($favorData['saldo_favor'], 250, 'saldo_favor post-perdón conserva el exceso sobre la deuda del tramo');

// Validación estricta compartida por GET y POST WhatsApp.
jostal_test_assert(jostal_validar_rango_fechas('2026-08-01', '2026-08-15')['ok'] === true, 'acepta rango Y-m-d ordenado');
jostal_test_assert(jostal_validar_rango_fechas('2026-8-1', '2026-08-15')['ok'] === false, 'rechaza fecha no estricta Y-m-d');
jostal_test_assert(jostal_validar_rango_fechas('2026-02-30', '')['ok'] === false, 'rechaza fecha inexistente');
jostal_test_assert(jostal_validar_rango_fechas('2026-08-15', '2026-08-01')['ok'] === false, 'rechaza desde posterior a hasta');

// Los redirects suministrados por formularios nunca pueden salir del origen.
jostal_test_assert(safe_internal_redirect_path('index.php?page=jostal&tab=deudas', 'index.php?page=dashboard') === 'index.php?page=jostal&tab=deudas', 'acepta redirect relativo interno');
jostal_test_assert(safe_internal_redirect_path('/control/index.php?page=jostal', 'index.php?page=dashboard') === '/control/index.php?page=jostal', 'acepta ruta absoluta del mismo origen');
jostal_test_assert(safe_internal_redirect_path('https://evil.example/phish', 'index.php?page=dashboard') === 'index.php?page=dashboard', 'rechaza redirect externo con esquema');
jostal_test_assert(safe_internal_redirect_path('//evil.example/phish', 'index.php?page=dashboard') === 'index.php?page=dashboard', 'rechaza redirect externo protocol-relative');
jostal_test_assert(safe_internal_redirect_path("index.php\r\nLocation: https://evil.example", 'index.php?page=dashboard') === 'index.php?page=dashboard', 'rechaza inyección de cabeceras');

// Reglas de autorización de la compensación permanente, sin tocar storage.
$validLead = jostal_test_lead('lead_noalq', 'jcli_owner', '2026-08-10', 25, 'no_alquiler');
jostal_test_assert(jostal_validar_compensacion_permanente($validLead, 'jcli_owner') === '', 'acepta pago no_alquiler positivo de la clienta mostrada');
jostal_test_assert(jostal_validar_compensacion_permanente($validLead, 'jcli_other') !== '', 'rechaza pago perteneciente a otra clienta');
$zeroLead = $validLead; $zeroLead['precio'] = 0;
jostal_test_assert(jostal_validar_compensacion_permanente($zeroLead, 'jcli_owner') !== '', 'rechaza pago sin importe positivo');
$rentLead = $validLead; $rentLead['concepto_tipo'] = 'alquiler';
jostal_test_assert(jostal_validar_compensacion_permanente($rentLead, 'jcli_owner') !== '', 'rechaza pago que ya no es no_alquiler');

// La compensación relee dentro del lock y conserva cambios concurrentes no relacionados.
storage_write('jostal_leads.json', array($validLead));
storage_find_by_id('jostal_leads.json', 'lead_noalq'); // llena caché con la versión anterior
$concurrentLead = $validLead;
$concurrentLead['nota_concurrente'] = 'conservar';
file_put_contents(DATA_PATH . '/jostal_leads.json', json_encode(array($concurrentLead), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
$mutation = jostal_compensar_lead_permanente('lead_noalq', 'jcli_owner');
jostal_test_assert(!empty($mutation['ok']), 'la compensación permanente confirma una escritura atómica completa');
$storedRows = json_decode(file_get_contents(DATA_PATH . '/jostal_leads.json'), true);
$storedLead = $storedRows[0] ?? array();
jostal_test_assert(($storedLead['nota_concurrente'] ?? '') === 'conservar', 'la mutación atómica no sobrescribe campos concurrentes');
jostal_test_assert(($storedLead['concepto_tipo'] ?? '') === 'alquiler', 'la mutación atómica convierte el pago a alquiler');
jostal_test_assert(substr_count((string)($storedLead['observacion'] ?? ''), 'compensación posterior alquiler') === 1, 'la compensación añade el sufijo exacto una sola vez');
$secondMutation = jostal_compensar_lead_permanente('lead_noalq', 'jcli_owner');
jostal_test_assert(empty($secondMutation['ok']), 'una segunda compensación se rechaza al revalidar el estado actual bajo lock');

$otherBackendLead = jostal_test_lead('lead_other_backend', 'jcli_owner', '2026-08-10', 25, 'no_alquiler');
$mysqlDispatches = 0;
$mysqlAdapterBehavior = 'success';
$GLOBALS['storage_mysql_mutation_adapter'] = function ($file, $id, $mutator, $allowInsert) use (&$mysqlDispatches, &$mysqlAdapterBehavior, $otherBackendLead) {
    $mysqlDispatches++;
    if ($mysqlAdapterBehavior === 'unavailable') {
        return array('ok' => false, 'error' => 'offline', 'code' => 'backend_unavailable');
    }
    if ($mysqlAdapterBehavior === 'validation') {
        return array('ok' => false, 'error' => 'rechazada', 'code' => 'validation_failed');
    }
    $mutation = call_user_func($mutator, $otherBackendLead);
    return !empty($mutation['ok'])
        ? array('ok' => true, 'row' => $mutation['row'], 'backend' => 'mysql')
        : array('ok' => false, 'error' => (string)($mutation['error'] ?? 'validation'), 'code' => 'validation_failed');
};

$GLOBALS['storage_backend_mode_cache'] = 'mysql';
$mysqlMutation = jostal_compensar_lead_permanente('lead_other_backend', 'jcli_owner');
jostal_test_assert(!empty($mysqlMutation['ok']) && ($mysqlMutation['row']['concepto_tipo'] ?? '') === 'alquiler', 'la compensación permanente usa mutación transaccional en backend mysql');

storage_backend_mode_reset();
$GLOBALS['storage_backend_mode_cache'] = 'dual';
storage_json_write_direct('jostal_leads.json', array($otherBackendLead));
$dualMutation = jostal_compensar_lead_permanente('lead_other_backend', 'jcli_owner');
$dualRows = json_decode(file_get_contents(DATA_PATH . '/jostal_leads.json'), true);
jostal_test_assert(!empty($dualMutation['ok']) && ($dualRows[0]['concepto_tipo'] ?? '') === 'alquiler', 'backend dual muta mysql y refleja la misma fila validada en JSON');
jostal_test_assert($mysqlDispatches === 2, 'el dispatcher selecciona mysql tanto en mysql como en dual');

$mysqlAdapterBehavior = 'unavailable';
$GLOBALS['storage_backend_mode_cache'] = 'mysql';
storage_json_write_direct('jostal_leads.json', array($otherBackendLead));
$fallbackMutation = jostal_compensar_lead_permanente('lead_other_backend', 'jcli_owner');
jostal_test_assert(!empty($fallbackMutation['ok']) && ($fallbackMutation['backend'] ?? '') === 'json_fallback', 'mysql no disponible conserva el fallback JSON histórico');

$mysqlAdapterBehavior = 'validation';
storage_json_write_direct('jostal_leads.json', array($otherBackendLead));
$rejectedMutation = jostal_compensar_lead_permanente('lead_other_backend', 'jcli_owner');
$rejectedRows = json_decode(file_get_contents(DATA_PATH . '/jostal_leads.json'), true);
jostal_test_assert(empty($rejectedMutation['ok']) && ($rejectedRows[0]['concepto_tipo'] ?? '') === 'no_alquiler', 'un rechazo transaccional MySQL no cae a JSON ni elude la revalidación');
unset($GLOBALS['storage_mysql_mutation_adapter']);
storage_backend_mode_reset();

// JSON existente ilegible o malformado nunca se sustituye por [] ni por un reemplazo nuevo.
$corruptFile = 'storage_corrupt.json';
$corruptPath = DATA_PATH . '/' . $corruptFile;
$corruptRaw = '{"target":{"broken":';
file_put_contents($corruptPath, $corruptRaw);
$corruptMutation = storage_json_mutate_row_atomic($corruptFile, 'target', function ($row) {
    return array('ok' => true, 'row' => array('id' => 'target', 'value' => 'mutated'));
}, true);
jostal_test_assert(empty($corruptMutation['ok']) && file_get_contents($corruptPath) === $corruptRaw, 'mutación falla cerrada ante JSON malformado sin modificarlo');
$corruptDelete = storage_json_delete_direct($corruptFile, 'target');
jostal_test_assert($corruptDelete === false && file_get_contents($corruptPath) === $corruptRaw, 'delete falla cerrado ante JSON malformado sin vaciarlo');
$corruptWrite = storage_json_write_direct($corruptFile, array(array('id' => 'replacement')));
jostal_test_assert($corruptWrite === false && file_get_contents($corruptPath) === $corruptRaw, 'reemplazo completo falla cerrado ante JSON malformado');

$missingFile = 'storage_missing.json';
@unlink(DATA_PATH . '/' . $missingFile);
jostal_test_assert(storage_json_write_direct($missingFile, array(array('id' => 'new'))) === true, 'un JSON ausente se considera estado inicial vacío válido');

// Un archivo válido pero ilegible para el escritor tampoco puede ser reemplazado.
if (function_exists('posix_geteuid') && posix_geteuid() === 0 && is_executable('/usr/sbin/runuser')) {
    $unreadableFile = 'storage_unreadable.json';
    $unreadablePath = DATA_PATH . '/' . $unreadableFile;
    $unreadableRaw = json_encode(array(array('id' => 'target', 'value' => 'protected')));
    file_put_contents($unreadablePath, $unreadableRaw);
    chmod(DATA_PATH, 0777);
    chmod($unreadablePath, 0600);
    chown($unreadablePath, 0);
    chgrp($unreadablePath, 0);
    $lockHandle = storage_json_lock_open($unreadableFile);
    if (is_resource($lockHandle)) fclose($lockHandle);

    $unreadableMutation = jostal_test_process_start($testBase, 'mutate-direct', array($unreadableFile), 'www-data');
    jostal_test_process_wait($unreadableMutation, 2, 'mutación rechaza un JSON existente ilegible');
    $unreadableDelete = jostal_test_process_start($testBase, 'delete-direct', array($unreadableFile), 'www-data');
    jostal_test_process_wait($unreadableDelete, 2, 'delete rechaza un JSON existente ilegible');
    $unreadableWrite = jostal_test_process_start($testBase, 'write-direct', array($unreadableFile, base64_encode(json_encode(array(array('id' => 'replacement'))))), 'www-data');
    jostal_test_process_wait($unreadableWrite, 2, 'reemplazo completo rechaza un JSON existente ilegible');
    jostal_test_assert(file_get_contents($unreadablePath) === $unreadableRaw, 'las operaciones ilegibles conservan exactamente el contenido original');
}

// Carreras reales entre procesos: todas las operaciones del mismo JSON respetan el mismo orden de lock.
$raceFile = 'storage_race.json';
$baseRows = array(array('id' => 'target', 'value' => 'initial'), array('id' => 'keep', 'value' => 'keep'));

// mutación → delete: delete espera y gana al ejecutarse después; no puede resucitar target.
storage_write($raceFile, $baseRows);
$marker = $testBase . '/mutation-delete.locked';
$gate = $testBase . '/mutation-delete.release';
$mutationChild = jostal_test_process_start($testBase, 'mutate-hold', array($raceFile, $marker, $gate));
jostal_test_wait_file($marker, 'la mutación hija adquirió el lock antes del delete');
$deleteChild = jostal_test_process_start($testBase, 'delete', array($raceFile));
usleep(150000);
$deleteWasBlocked = !empty(proc_get_status($deleteChild[0])['running']);
file_put_contents($gate, 'go');
jostal_test_process_wait($mutationChild, 0, 'la mutación previa termina correctamente');
jostal_test_process_wait($deleteChild, 0, 'el delete posterior termina correctamente');
$rows = jostal_test_read_rows($raceFile);
jostal_test_assert($deleteWasBlocked, 'storage_delete espera el lock común durante una mutación');
jostal_test_assert(jostal_test_find_row($rows, 'target') === null && jostal_test_find_row($rows, 'keep') !== null, 'mutación→delete no resucita la fila y conserva las demás');

// mutación → upsert: el upsert relee después del lock y combina ambos cambios.
storage_write($raceFile, $baseRows);
$marker = $testBase . '/mutation-upsert.locked';
$gate = $testBase . '/mutation-upsert.release';
$mutationChild = jostal_test_process_start($testBase, 'mutate-hold', array($raceFile, $marker, $gate));
jostal_test_wait_file($marker, 'la mutación hija adquirió el lock antes del upsert');
$upsertRow = array('id' => 'target', 'upserted' => true);
$upsertChild = jostal_test_process_start($testBase, 'upsert', array($raceFile, base64_encode(json_encode($upsertRow))));
usleep(150000);
$upsertWasBlocked = !empty(proc_get_status($upsertChild[0])['running']);
file_put_contents($gate, 'go');
jostal_test_process_wait($mutationChild, 0, 'la mutación previa al upsert termina correctamente');
jostal_test_process_wait($upsertChild, 0, 'el upsert posterior termina correctamente');
$target = jostal_test_find_row(jostal_test_read_rows($raceFile), 'target');
jostal_test_assert($upsertWasBlocked, 'storage_upsert espera el lock común durante una mutación');
jostal_test_assert(!empty($target['mutated']) && !empty($target['upserted']), 'mutación→upsert conserva ambos cambios sin pérdida');

// delete → mutación: la mutación posterior relee ausencia y falla cerrada.
storage_write($raceFile, $baseRows);
$gate = $testBase . '/delete-mutation.release';
$mutationChild = jostal_test_process_start($testBase, 'mutate-after-gate', array($raceFile, $gate));
$deleteChild = jostal_test_process_start($testBase, 'delete', array($raceFile));
jostal_test_process_wait($deleteChild, 0, 'el delete previo termina correctamente');
file_put_contents($gate, 'go');
jostal_test_process_wait($mutationChild, 2, 'la mutación posterior detecta la fila eliminada');
$rows = jostal_test_read_rows($raceFile);
jostal_test_assert(jostal_test_find_row($rows, 'target') === null && jostal_test_find_row($rows, 'keep') !== null, 'delete→mutación no resucita la fila eliminada');

// mutación → reemplazo completo: storage_write espera y su snapshot posterior gana completo.
storage_write($raceFile, $baseRows);
$marker = $testBase . '/mutation-write.locked';
$gate = $testBase . '/mutation-write.release';
$replacement = array(array('id' => 'replacement', 'value' => 'full-write'));
$mutationChild = jostal_test_process_start($testBase, 'mutate-hold', array($raceFile, $marker, $gate));
jostal_test_wait_file($marker, 'la mutación hija adquirió el lock antes del reemplazo completo');
$writeChild = jostal_test_process_start($testBase, 'write', array($raceFile, base64_encode(json_encode($replacement))));
usleep(150000);
$writeWasBlocked = !empty(proc_get_status($writeChild[0])['running']);
file_put_contents($gate, 'go');
jostal_test_process_wait($mutationChild, 0, 'la mutación previa al reemplazo termina correctamente');
jostal_test_process_wait($writeChild, 0, 'el reemplazo completo posterior termina correctamente');
$rows = jostal_test_read_rows($raceFile);
jostal_test_assert($writeWasBlocked, 'storage_write espera el lock común durante una mutación');
jostal_test_assert($rows === $replacement, 'mutación→reemplazo completo respeta que la última operación gane sin mezcla parcial');

// reemplazo completo → mutación: la mutación relee el snapshot nuevo y conserva sus campos.
storage_write($raceFile, $baseRows);
$gate = $testBase . '/write-mutation.release';
$replacement = array(array('id' => 'target', 'value' => 'replacement', 'concurrent' => 'keep'), array('id' => 'other', 'value' => 'other'));
$mutationChild = jostal_test_process_start($testBase, 'mutate-after-gate', array($raceFile, $gate));
$writeChild = jostal_test_process_start($testBase, 'write', array($raceFile, base64_encode(json_encode($replacement))));
jostal_test_process_wait($writeChild, 0, 'el reemplazo completo previo termina correctamente');
file_put_contents($gate, 'go');
jostal_test_process_wait($mutationChild, 0, 'la mutación posterior al reemplazo termina correctamente');
$rows = jostal_test_read_rows($raceFile);
$target = jostal_test_find_row($rows, 'target');
jostal_test_assert(($target['value'] ?? '') === 'replacement' && ($target['concurrent'] ?? '') === 'keep' && !empty($target['mutated']), 'reemplazo completo→mutación no pierde campos del snapshot nuevo');
jostal_test_assert(jostal_test_find_row($rows, 'other') !== null, 'reemplazo completo→mutación conserva las demás filas');

// El lock es reutilizable entre usuarios y los reemplazos conservan el modo del JSON existente.
$lockMode = fileperms(DATA_PATH . '/' . $raceFile . '.lock') & 0777;
jostal_test_assert($lockMode === 0666, 'el lock común queda abrible por root y www-data');
chmod(DATA_PATH . '/' . $raceFile . '.lock', 0644); // simula lock legado creado por root
storage_write($raceFile, $replacement);
$repairedLockMode = fileperms(DATA_PATH . '/' . $raceFile . '.lock') & 0777;
jostal_test_assert($repairedLockMode === 0666, 'un escritor propietario repara el modo de un lock legado');
chmod(DATA_PATH . '/' . $raceFile, 0660);
$metadataBefore = stat(DATA_PATH . '/' . $raceFile);
storage_write($raceFile, $replacement);
$metadataAfter = stat(DATA_PATH . '/' . $raceFile);
$targetMode = fileperms(DATA_PATH . '/' . $raceFile) & 0777;
jostal_test_assert($targetMode === 0660, 'el reemplazo atómico conserva el modo del JSON existente');
jostal_test_assert(($metadataAfter['uid'] ?? null) === ($metadataBefore['uid'] ?? null), 'el reemplazo atómico conserva el propietario cuando los permisos lo permiten');
jostal_test_assert(($metadataAfter['gid'] ?? null) === ($metadataBefore['gid'] ?? null), 'el reemplazo atómico conserva el grupo cuando los permisos lo permiten');

// En este despliegue, root crea y www-data puede abrir el mismo lock y reemplazar el JSON.
if (function_exists('posix_geteuid') && posix_geteuid() === 0 && function_exists('posix_getpwnam') && is_executable('/usr/sbin/runuser')) {
    $wwwData = posix_getpwnam('www-data');
    if (is_array($wwwData)) {
        @unlink(DATA_PATH . '/' . $raceFile);
        @unlink(DATA_PATH . '/' . $raceFile . '.lock');
        chmod(DATA_PATH, 0777);
        chown(DATA_PATH, (int)$wwwData['uid']);
        chgrp(DATA_PATH, (int)$wwwData['gid']);
        storage_write($raceFile, $baseRows); // ejecutado como root, dueño derivado del directorio www-data
        jostal_test_assert(fileowner(DATA_PATH . '/' . $raceFile) === (int)$wwwData['uid'], 'un JSON nuevo hereda el propietario del directorio de datos');
        $crossUserReplacement = array(array('id' => 'cross-user', 'value' => 'www-data'));
        $wwwChild = jostal_test_process_start($testBase, 'write', array($raceFile, base64_encode(json_encode($crossUserReplacement))), 'www-data');
        jostal_test_process_wait($wwwChild, 0, 'www-data reutiliza el lock creado por root');
        jostal_test_assert(jostal_test_read_rows($raceFile) === $crossUserReplacement, 'el reemplazo cross-user queda íntegro');
        jostal_test_assert(fileowner(DATA_PATH . '/' . $raceFile) === (int)$wwwData['uid'] && filegroup(DATA_PATH . '/' . $raceFile) === (int)$wwwData['gid'], 'el reemplazo cross-user conserva propietario y grupo');
    }
}

$actions = file_get_contents(dirname(__DIR__) . '/app/actions.php');
$views = file_get_contents(dirname(__DIR__) . '/app/views.php');
jostal_test_assert(strpos($actions, "'comercial_export_threads_csv', 'jostal_compensar_lead'") !== false, 'la compensación permanente está cubierta por CSRF');
jostal_test_assert(strpos($views, 'name="csrf_token"') !== false, 'el formulario permanente envía token CSRF');
jostal_test_assert(strpos($views, 'name="clienta_id" value="\' + esc(cid)') !== false, 'el formulario permanente envía clienta_id');
jostal_test_assert(strpos($views, 'var pdesde = d.perdon.desde;') !== false, 'JS usa la fecha de perdón configurada exactamente igual que PHP');
jostal_test_assert(strpos($views, 'saldo_favor: Math.max(0, postAlq.reduce') !== false, 'JS calcula saldo_favor post-perdón con la misma base que PHP');
jostal_test_assert(strpos($views, 'El rango recalcula la deuda') !== false, 'la UI explica que el rango recalcula los totales');
jostal_test_assert(strpos($views, '$deudaTotal = $deudaVencida + $pendienteActual;') === false, 'la vista no vuelve a sumar semanas perdonadas para mostrar el total');

fwrite(STDOUT, "Jostal debt regression checks OK" . PHP_EOL);
} catch (Throwable $e) {
    if (!$e instanceof JostalTestAssertion) fwrite(STDERR, '[ERROR] ' . $e->getMessage() . PHP_EOL);
    $testExit = 1;
} finally {
    jostal_test_remove_dir($testBase);
}
exit(isset($testExit) ? $testExit : 0);
