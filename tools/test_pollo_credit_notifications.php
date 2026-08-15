<?php
// Regression test for Pollo credit-state notifications.
// Uses a temporary DATA_PATH so production data is never read or written.

$projectPath = dirname(__DIR__);
$mode = $argv[1] ?? '';

if (in_array($mode, array('--mock-dual-write-mysql-failure', '--mock-dual-write-mirror-failure'), true)) {
    $childBase = sys_get_temp_dir() . '/lamami-dual-write-mock-' . getmypid() . '-' . uniqid();
    define('BASE_PATH', $childBase);
    define('APP_PATH', $projectPath . '/app');
    define('DATA_PATH', $childBase . '/data');
    @mkdir(DATA_PATH, 0777, true);
    $jsonPath = DATA_PATH . '/avisos.json';
    $initialJson = json_encode(array(array('id' => 'json-original')));
    if ($mode === '--mock-dual-write-mirror-failure') @mkdir($jsonPath, 0777, true);
    else file_put_contents($jsonPath, $initialJson);
    $GLOBALS['mock_mysql_write_ok'] = $mode === '--mock-dual-write-mirror-failure';
    $GLOBALS['mock_mysql_data'] = array(array('id' => 'mysql-original'));
    function storage_backend_mode() { return 'dual'; }
    function storage_mysql_read($file) {
        return array('ok' => true, 'has_rows' => !empty($GLOBALS['mock_mysql_data']), 'data' => $GLOBALS['mock_mysql_data']);
    }
    function storage_mysql_write($file, $rows) {
        if (!$GLOBALS['mock_mysql_write_ok']) return false;
        $GLOBALS['mock_mysql_data'] = array_values($rows);
        return true;
    }
    function storage_invalidate_cache($file = '') {}
    function now_datetime() { return gmdate('Y-m-d H:i:s'); }
    require_once APP_PATH . '/avisos.php';
    $writeResult = avisos_rows_update_atomic(function ($rows) {
        $rows[] = array('id' => 'new-row');
        return array('rows' => $rows, 'changed' => true, 'result' => true);
    });
    if ($mode === '--mock-dual-write-mysql-failure') {
        $ok = empty($writeResult['ok']) && file_get_contents($jsonPath) === $initialJson;
    } else {
        $readResult = avisos_rows_update_atomic(function ($rows) {
            return array('rows' => $rows, 'changed' => false, 'result' => array_column($rows, 'id'));
        });
        $ok = !empty($writeResult['ok'])
            && !empty($readResult['ok'])
            && $readResult['result'] === array('mysql-original', 'new-row');
    }
    foreach ((array)glob(DATA_PATH . '/*') as $path) {
        if (is_dir($path)) @rmdir($path); else @unlink($path);
    }
    @rmdir(DATA_PATH);
    @rmdir(BASE_PATH);
    exit($ok ? 0 : 1);
}

if (in_array($mode, array('--mock-mysql-empty', '--mock-mysql-failure', '--mock-dual-failure'), true)) {
    $childBase = sys_get_temp_dir() . '/lamami-mysql-mock-' . getmypid() . '-' . uniqid();
    define('BASE_PATH', $childBase);
    define('APP_PATH', $projectPath . '/app');
    define('DATA_PATH', $childBase . '/data');
    @mkdir(DATA_PATH, 0777, true);
    file_put_contents(DATA_PATH . '/avisos.json', json_encode(array(array('id' => 'json-fallback-must-not-be-used'))));
    $GLOBALS['mock_storage_mode'] = $mode === '--mock-dual-failure' ? 'dual' : 'mysql';
    $GLOBALS['mock_mysql_ok'] = $mode === '--mock-mysql-empty';
    $GLOBALS['mock_callback_called'] = false;
    function storage_backend_mode() { return $GLOBALS['mock_storage_mode']; }
    function storage_mysql_read($file) {
        return array('ok' => $GLOBALS['mock_mysql_ok'], 'has_rows' => false, 'data' => array());
    }
    function storage_read($file) {
        return array(array('id' => 'json-fallback-must-not-be-used'));
    }
    function storage_invalidate_cache($file = '') {}
    function now_datetime() { return gmdate('Y-m-d H:i:s'); }
    require_once APP_PATH . '/avisos.php';
    $result = avisos_rows_update_atomic(function ($rows) {
        $GLOBALS['mock_callback_called'] = true;
        return array('rows' => $rows, 'changed' => false, 'result' => $rows);
    });
    if ($mode === '--mock-mysql-empty') {
        $ok = !empty($result['ok']) && $GLOBALS['mock_callback_called'] && $result['result'] === array();
    } else {
        $ok = empty($result['ok']) && !$GLOBALS['mock_callback_called'];
    }
    foreach ((array)glob(DATA_PATH . '/*') as $path) if (is_file($path)) @unlink($path);
    @rmdir(DATA_PATH);
    @rmdir(BASE_PATH);
    exit($ok ? 0 : 1);
}

if ($mode === '--concurrent-check') {
    $childBase = (string)($argv[2] ?? '');
    define('BASE_PATH', $childBase);
    define('APP_PATH', $projectPath . '/app');
    define('DATA_PATH', $childBase . '/data');
    require_once APP_PATH . '/helpers.php';
    require_once APP_PATH . '/storage.php';
    require_once APP_PATH . '/avisos.php';
    require_once APP_PATH . '/publicista.php';
    publicista_pollo_check_and_alert();
    exit(0);
}

if ($mode === '--serialized-check' || $mode === '--mark-recovered') {
    $childBase = (string)($argv[2] ?? '');
    $spyPath = (string)($argv[3] ?? '');
    define('BASE_PATH', $childBase);
    define('APP_PATH', $projectPath . '/app');
    define('DATA_PATH', $childBase . '/data');
    require_once APP_PATH . '/helpers.php';
    require_once APP_PATH . '/storage.php';
    if ($mode === '--serialized-check') {
        $GLOBALS['pollo_send_spy_path'] = $spyPath;
        function avisos_all() { return array(); }
        function avisos_create_active() {
            file_put_contents($GLOBALS['pollo_send_spy_path'], "send-start\n", FILE_APPEND);
            usleep(700000);
            file_put_contents($GLOBALS['pollo_send_spy_path'], "send-end\n", FILE_APPEND);
            return 'serialized-aviso';
        }
        function aviso_dismiss() { return true; }
    }
    require_once APP_PATH . '/publicista.php';
    if ($mode === '--mark-recovered') publicista_pollo_mark_recovered('Serializada');
    else publicista_pollo_check_and_alert();
    exit(0);
}

if ($mode === '--malformed-avisos' || $mode === '--malformed-status') {
    $childBase = sys_get_temp_dir() . '/lamami-malformed-' . getmypid() . '-' . uniqid();
    define('BASE_PATH', $childBase);
    define('APP_PATH', $projectPath . '/app');
    define('DATA_PATH', $childBase . '/data');
    @mkdir(DATA_PATH, 0777, true);
    require_once APP_PATH . '/helpers.php';
    require_once APP_PATH . '/storage.php';
    if ($mode === '--malformed-avisos') {
        file_put_contents(DATA_PATH . '/avisos.json', '{malformed');
        require_once APP_PATH . '/avisos.php';
        $result = avisos_create_active('No borrar', 'Malformed', 'alta', 'pollo', array(), false, 'malformed');
        $ok = $result === false && file_get_contents(DATA_PATH . '/avisos.json') === '{malformed';
    } else {
        file_put_contents(BASE_PATH . '/data/pollo_accounts_status.json', '{malformed');
        require_once APP_PATH . '/publicista.php';
        publicista_pollo_mark_exhausted('Malformed');
        $ok = file_get_contents(BASE_PATH . '/data/pollo_accounts_status.json') === '{malformed';
    }
    foreach ((array)glob(DATA_PATH . '/*') as $path) {
        if (is_file($path)) @unlink($path);
    }
    @rmdir(DATA_PATH);
    @rmdir(BASE_PATH);
    exit($ok ? 0 : 1);
}

if ($mode === '--avisos-persistence-failure') {
    $childBase = sys_get_temp_dir() . '/lamami-avisos-failure-' . getmypid() . '-' . uniqid();
    define('BASE_PATH', $childBase);
    define('APP_PATH', $projectPath . '/app');
    define('DATA_PATH', $childBase . '/data');
    require_once APP_PATH . '/helpers.php';
    require_once APP_PATH . '/storage.php';
    require_once APP_PATH . '/avisos.php';
    @mkdir(DATA_PATH, 0775, true);
    @mkdir(DATA_PATH . '/avisos.json', 0775, true);
    $id = avisos_create_active('No persistir', 'Prueba de fallo real', 'alta', 'pollo', array(), false, 'failure_test');
    @unlink(DATA_PATH . '/avisos.json.lock');
    @rmdir(DATA_PATH . '/avisos.json');
    @rmdir(DATA_PATH);
    @rmdir(BASE_PATH);
    exit($id === false ? 0 : 1);
}

if ($mode === '--delivery-finalize-failure') {
    $childBase = sys_get_temp_dir() . '/lamami-delivery-failure-' . getmypid() . '-' . uniqid();
    define('BASE_PATH', $childBase);
    define('APP_PATH', $projectPath . '/app');
    define('DATA_PATH', $childBase . '/data');
    require_once APP_PATH . '/helpers.php';
    require_once APP_PATH . '/storage.php';
    require_once APP_PATH . '/avisos.php';
    @mkdir(DATA_PATH, 0777, true);
    file_put_contents(DATA_PATH . '/avisos.json', '[]');
    $id = avisos_create_active('Entrega ambigua', 'No duplicar', 'alta', 'manual', array(), false, 'ambiguous_delivery');
    $claimed = avisos_claim_whatsapp_delivery($id, now_datetime());
    $sentRow = aviso_apply_whatsapp_send_result($claimed, array(
        'ok' => true, 'status' => 'sent', 'attempted_at' => now_datetime(), 'phones' => array(), 'error' => '',
    ), now_datetime());
    $canonical = DATA_PATH . '/avisos.json';
    $backup = DATA_PATH . '/avisos.backup.json';
    rename($canonical, $backup);
    mkdir($canonical, 0777);
    $finalized = avisos_store_whatsapp_result_atomic($id, $sentRow);
    rmdir($canonical);
    rename($backup, $canonical);
    avisos_retry_pending_whatsapp();
    $row = storage_find_by_id('avisos.json', $id);
    $ok = empty($finalized['ok'])
        && !empty($row['whatsapp_delivery_claim'])
        && empty($row['whatsapp_sent_at']);
    foreach ((array)glob(DATA_PATH . '/*') as $path) if (is_file($path)) @unlink($path);
    @rmdir(DATA_PATH);
    @rmdir(BASE_PATH);
    exit($ok ? 0 : 1);
}

if ($mode === '--without-avisos' || $mode === '--failing-avisos' || $mode === '--failing-dismiss') {
    $childBase = sys_get_temp_dir() . '/lamami-pollo-no-avisos-' . getmypid() . '-' . uniqid();
    define('BASE_PATH', $childBase);
    define('APP_PATH', $projectPath . '/app');
    define('DATA_PATH', $childBase . '/data');
    require_once APP_PATH . '/helpers.php';
    require_once APP_PATH . '/storage.php';
    if ($mode === '--failing-avisos') {
        function avisos_all() { return array(); }
        function avisos_create_active() { return false; }
    } elseif ($mode === '--failing-dismiss') {
        $GLOBALS['pollo_test_create_calls'] = 0;
        function avisos_all() {
            return array(array(
                'id' => 'persisted-exhaustion',
                'engine' => 'pollo',
                'source_key' => 'legacy',
                'title' => 'Pollo.ai: cuenta Sin avisos sin créditos',
                'status' => 'active',
                'meta' => array('account' => 'Sin avisos'),
            ));
        }
        function avisos_create_active() {
            $GLOBALS['pollo_test_create_calls']++;
            return 'unexpected-recovery';
        }
        function aviso_dismiss() { return false; }
    }
    require_once APP_PATH . '/publicista.php';

    @mkdir(DATA_PATH, 0775, true);
    storage_write('settings.json', array(
        'pollo_accounts' => array(array('label' => 'Sin avisos', 'cookie' => 'cookie')),
    ));
    publicista_pollo_mark_exhausted('Sin avisos');
    if ($mode === '--failing-dismiss') publicista_pollo_mark_recovered('Sin avisos');
    publicista_pollo_check_and_alert();
    $status = publicista_pollo_status_read();
    $account = $status['Sin avisos'] ?? array();
    if ($mode === '--failing-dismiss') {
        $ok = empty($account['recovery_notified_at'])
            && empty($account['recovery_notification_claim'])
            && (int)$GLOBALS['pollo_test_create_calls'] === 0;
    } else {
        $ok = empty($account['exhaustion_notified_at']) && empty($account['exhaustion_notification_claim']);
    }
    exit($ok ? 0 : 1);
}

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

function pollo_test_account_status($label) {
    $status = publicista_pollo_status_read();
    return is_array($status[$label] ?? null) ? $status[$label] : array();
}

function pollo_test_find_alert($title) {
    foreach (pollo_test_alerts() as $alert) {
        if (($alert['title'] ?? '') === $title) return $alert;
    }
    return null;
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
    avisos_rows_update_atomic(function ($rows) use ($alerts) {
        foreach ($rows as $index => $row) {
            if (($row['id'] ?? '') === ($alerts[0]['id'] ?? '')) {
                $rows[$index]['created_at'] = $alerts[0]['created_at'];
            }
        }
        return array('rows' => $rows, 'changed' => true, 'result' => true);
    });
    publicista_pollo_check_and_alert();
    $pass = pollo_test_assert(1, count(pollo_test_alerts()), 'agotamiento sin cambios no repite aviso diario') && $pass;

    // ConfigM's "Marcar con créditos" transition emits one recovery notice.
    publicista_pollo_mark_recovered('Cuenta 1');
    publicista_pollo_check_and_alert();
    $alerts = pollo_test_alerts();
    $pass = pollo_test_assert(2, count($alerts), 'recuperación emite un aviso') && $pass;
    $pass = pollo_test_assert('Pollo.ai: cuenta Cuenta 1 con créditos de nuevo', $alerts[1]['title'] ?? '', 'aviso de recuperación identifica la cuenta') && $pass;
    $pass = pollo_test_assert('dismissed', $alerts[0]['status'] ?? '', 'recuperación descarta el aviso de agotamiento activo') && $pass;

    publicista_pollo_check_and_alert();
    $pass = pollo_test_assert(2, count(pollo_test_alerts()), 'recuperación sin cambios no repite aviso') && $pass;

    // A complete second cycle must create fresh notices, not adopt old history.
    publicista_pollo_mark_exhausted('Cuenta 1');
    publicista_pollo_check_and_alert();
    $pass = pollo_test_assert(3, count(pollo_test_alerts()), 'segundo agotamiento emite un aviso nuevo') && $pass;
    publicista_pollo_mark_recovered('Cuenta 1');
    publicista_pollo_check_and_alert();
    $alerts = pollo_test_alerts();
    $pass = pollo_test_assert(4, count($alerts), 'segunda recuperación emite un aviso nuevo') && $pass;
    $pass = pollo_test_assert('dismissed', $alerts[2]['status'] ?? '', 'segunda recuperación descarta solo su agotamiento activo') && $pass;

    // Migration: an active legacy matching notice is adopted instead of duplicated.
    publicista_pollo_mark_exhausted('Cuenta 2');
    $legacyId = avisos_create_active(
        'Pollo.ai: cuenta Cuenta 2 sin créditos',
        'Aviso creado por la versión anterior.',
        'alta',
        'pollo',
        array('account' => 'Cuenta 2'),
        false,
        'legacy_pollo_exhausted_cuenta_2'
    );
    $beforeMigration = count(pollo_test_alerts());
    publicista_pollo_check_and_alert();
    $pass = pollo_test_assert($beforeMigration, count(pollo_test_alerts()), 'migración adopta aviso de agotamiento coincidente') && $pass;
    $migratedStatus = pollo_test_account_status('Cuenta 2');
    $pass = pollo_test_assert($legacyId, $migratedStatus['exhaustion_aviso_id'] ?? '', 'migración recuerda el aviso activo adoptado') && $pass;

    publicista_pollo_mark_recovered('Cuenta 2');
    publicista_pollo_check_and_alert();
    $legacyAlert = pollo_test_find_alert('Pollo.ai: cuenta Cuenta 2 sin créditos');
    $pass = pollo_test_assert('dismissed', $legacyAlert['status'] ?? '', 'recuperación descarta también el aviso legado adoptado') && $pass;

    $dismissContractId = avisos_create_active('Contrato dismiss', 'Aviso auxiliar', 'baja', 'manual', array(), false, 'dismiss_contract');
    $pass = pollo_test_assert(true, aviso_dismiss($dismissContractId), 'aviso_dismiss confirma persistencia real') && $pass;
    $pass = pollo_test_assert(false, aviso_dismiss('aviso_inexistente'), 'aviso_dismiss falla si no existe el aviso') && $pass;

    $plannedId = avisos_create_manual_planned('Planificado', 'Aviso futuro', gmdate('Y-m-d H:i:s', time() - 60), 'media');
    $pass = pollo_test_assert(true, is_string($plannedId) && $plannedId !== '', 'creación planificada persiste con primitive atómica') && $pass;
    $pass = pollo_test_assert(1, avisos_activate_planned_manuals(false), 'activación planificada usa actualización atómica') && $pass;
    avisos_mark_as_read(array($plannedId));
    $plannedActive = storage_find_by_id('avisos.json', $plannedId);
    $pass = pollo_test_assert(true, !empty($plannedActive['read_at']), 'marcar leído persiste sin reemplazar snapshot obsoleto') && $pass;
    avisos_mark_as_read_and_dismiss(array($plannedId));
    $plannedDismissed = storage_find_by_id('avisos.json', $plannedId);
    $pass = pollo_test_assert('dismissed', $plannedDismissed['status'] ?? '', 'descarte masivo persiste atómicamente') && $pass;
    $futureId = avisos_create_manual_planned('Eliminar', 'Aviso a borrar', gmdate('Y-m-d H:i:s', time() + 3600), 'baja');
    avisos_delete_planned($futureId);
    $pass = pollo_test_assert(null, storage_find_by_id('avisos.json', $futureId), 'eliminación planificada no borra avisos concurrentes') && $pass;

    $destacamosId = avisos_create_active(
        'Recordatorio Destacamos', 'Auxiliar', 'baja', 'recurring',
        array('kind' => 'destacamos_publish'), false, 'destacamos_publish_test'
    );
    $pass = pollo_test_assert(1, avisos_dismiss_destacamos_publish_reminders(), 'descarte especializado usa primitive atómica') && $pass;
    $destacamosRow = storage_find_by_id('avisos.json', $destacamosId);
    $pass = pollo_test_assert('dismissed', $destacamosRow['status'] ?? '', 'descarte especializado conserva resultado') && $pass;

    $syncStats = avisos_sync_generated(array(
        aviso_make('atomic_sync_test', 'source_1', 'Sincronizado', 'Prueba', 'baja', array(), true),
    ), 'atomic_sync_test', false, 'run_1');
    $pass = pollo_test_assert(1, (int)($syncStats['created'] ?? 0), 'sincronización generada crea dentro de primitive atómica') && $pass;
    $unrelatedId = avisos_create_active('No relacionado', 'Debe sobrevivir', 'baja', 'manual', array(), false, 'unrelated_sync_test');
    $resolveStats = avisos_sync_generated(array(), 'atomic_sync_test', false, 'run_2');
    $pass = pollo_test_assert(1, (int)($resolveStats['resolved'] ?? 0), 'sincronización generada resuelve atómicamente') && $pass;
    $pass = pollo_test_assert(true, is_array(storage_find_by_id('avisos.json', $unrelatedId)), 'sincronización no elimina avisos ajenos') && $pass;

    $avisosLockMode = (int)fileperms(DATA_PATH . '/avisos.json.lock') & 0777;
    $polloLockMode = (int)fileperms(publicista_pollo_status_lock_file()) & 0777;
    $pass = pollo_test_assert(0666, $avisosLockMode, 'lock de avisos queda utilizable entre root y www-data') && $pass;
    $pass = pollo_test_assert(0666, $polloLockMode, 'lock de estado Pollo queda utilizable entre root y www-data') && $pass;
    chmod(DATA_PATH . '/avisos.json', 0640);
    $avisosOwnerBefore = fileowner(DATA_PATH . '/avisos.json');
    $avisosGroupBefore = filegroup(DATA_PATH . '/avisos.json');
    avisos_create_active('Modo', 'Conservar permisos', 'baja', 'manual', array(), false, 'mode_test');
    $pass = pollo_test_assert(0640, ((int)fileperms(DATA_PATH . '/avisos.json') & 0777), 'reemplazo atómico conserva modo de avisos.json') && $pass;
    $pass = pollo_test_assert($avisosOwnerBefore, fileowner(DATA_PATH . '/avisos.json'), 'reemplazo atómico conserva uid de avisos.json') && $pass;
    $pass = pollo_test_assert($avisosGroupBefore, filegroup(DATA_PATH . '/avisos.json'), 'reemplazo atómico conserva gid de avisos.json') && $pass;
    chmod(publicista_pollo_status_file(), 0640);
    $statusOwnerBefore = fileowner(publicista_pollo_status_file());
    $statusGroupBefore = filegroup(publicista_pollo_status_file());
    publicista_pollo_status_update(function ($status) {
        $status['_mode_test'] = array('ok' => true);
        return array('data' => $status, 'changed' => true, 'result' => true);
    });
    $pass = pollo_test_assert(0640, ((int)fileperms(publicista_pollo_status_file()) & 0777), 'reemplazo atómico conserva modo de estado Pollo') && $pass;
    $pass = pollo_test_assert($statusOwnerBefore, fileowner(publicista_pollo_status_file()), 'reemplazo atómico conserva uid de estado Pollo') && $pass;
    $pass = pollo_test_assert($statusGroupBefore, filegroup(publicista_pollo_status_file()), 'reemplazo atómico conserva gid de estado Pollo') && $pass;
    chmod(DATA_PATH . '/avisos.json', 0644);
    avisos_create_active('Lectura cruzada', 'root/www-data', 'baja', 'manual', array(), false, 'cross_user_read');
    $pass = pollo_test_assert(true, (((int)fileperms(DATA_PATH . '/avisos.json') & 0004) !== 0), 'avisos.json conserva lectura para el otro usuario') && $pass;
    chmod(publicista_pollo_status_file(), 0644);
    publicista_pollo_status_update(function ($status) {
        $status['_cross_user_read'] = true;
        return array('data' => $status, 'changed' => true, 'result' => true);
    });
    $pass = pollo_test_assert(true, (((int)fileperms(publicista_pollo_status_file()) & 0004) !== 0), 'estado Pollo conserva lectura para el otro usuario') && $pass;

    $claimId = avisos_create_active('Claim WA', 'No reenviar', 'alta', 'manual', array(), false, 'claim_retry_test');
    avisos_rows_update_atomic(function ($rows) use ($claimId) {
        foreach ($rows as $index => $row) {
            if (($row['id'] ?? '') !== $claimId) continue;
            $rows[$index]['whatsapp_delivery_claim'] = array('token' => 'ambiguous', 'claimed_at' => now_datetime());
        }
        return array('rows' => $rows, 'changed' => true, 'result' => true);
    });
    $retryStats = avisos_retry_pending_whatsapp();
    $claimedRow = storage_find_by_id('avisos.json', $claimId);
    $pass = pollo_test_assert('', $claimedRow['whatsapp_last_attempt_at'] ?? '', 'retry no duplica entrega con claim ambiguo') && $pass;

    $dismissRaceId = avisos_create_active('Race dismiss', 'Snapshot pendiente', 'alta', 'manual', array(), false, 'dismiss_race');
    $pendingSnapshot = storage_find_by_id('avisos.json', $dismissRaceId);
    $pass = pollo_test_assert(true, aviso_whatsapp_should_retry($pendingSnapshot), 'snapshot previo considera entrega pendiente') && $pass;
    aviso_dismiss($dismissRaceId);
    $raceSendResult = avisos_send_and_store_result($pendingSnapshot, now_datetime());
    $dismissedAfterRace = storage_find_by_id('avisos.json', $dismissRaceId);
    $pass = pollo_test_assert('skipped_claimed', $raceSendResult['status'] ?? '', 'claim relee y rechaza snapshot ya descartado') && $pass;
    $pass = pollo_test_assert('', $dismissedAfterRace['whatsapp_last_attempt_at'] ?? '', 'retry nunca envía fila descartada tras snapshot') && $pass;

    $canonicalRows = json_decode((string)file_get_contents(DATA_PATH . '/avisos.json'), true);
    $canonicalRows[] = array(
        'id' => 'snapshot_freshness_test', 'engine' => 'manual', 'source_key' => 'snapshot_freshness_test',
        'title' => 'Canonical nuevo', 'message' => '', 'severity' => 'baja', 'meta' => array(),
        'status' => 'active', 'created_at' => now_datetime(), 'updated_at' => now_datetime(),
    );
    file_put_contents(DATA_PATH . '/avisos.json', json_encode($canonicalRows));
    clearstatcache(true, DATA_PATH . '/avisos.json');
    $freshActiveIds = array_map(function ($row) { return $row['id'] ?? ''; }, avisos_get_active());
    $pass = pollo_test_assert(true, in_array('snapshot_freshness_test', $freshActiveIds, true), 'snapshot obsoleto cae al canonical fresco') && $pass;

    // Without notice creation support, no notified marker or claim may be persisted.
    $childCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --without-avisos';
    exec($childCommand, $childOutput, $childExit);
    $pass = pollo_test_assert(0, $childExit, 'sin soporte de avisos no persiste marcador ni claim') && $pass;

    $failedChildCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --failing-avisos';
    exec($failedChildCommand, $failedChildOutput, $failedChildExit);
    $pass = pollo_test_assert(0, $failedChildExit, 'si crear aviso falla no persiste marcador ni claim') && $pass;

    $failedDismissCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --failing-dismiss';
    exec($failedDismissCommand, $failedDismissOutput, $failedDismissExit);
    $pass = pollo_test_assert(0, $failedDismissExit, 'si descartar agotamiento falla no crea ni finaliza recuperación') && $pass;

    $malformedAvisosCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --malformed-avisos';
    exec($malformedAvisosCommand, $malformedAvisosOutput, $malformedAvisosExit);
    $pass = pollo_test_assert(0, $malformedAvisosExit, 'avisos.json malformado falla cerrado sin borrado') && $pass;
    $malformedStatusCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --malformed-status';
    exec($malformedStatusCommand, $malformedStatusOutput, $malformedStatusExit);
    $pass = pollo_test_assert(0, $malformedStatusExit, 'estado Pollo malformado falla cerrado sin borrado') && $pass;

    $persistenceCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --avisos-persistence-failure';
    exec($persistenceCommand, $persistenceOutput, $persistenceExit);
    $pass = pollo_test_assert(0, $persistenceExit, 'avisos_create_active devuelve false si no puede persistir') && $pass;
    $deliveryFailureCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --delivery-finalize-failure';
    exec($deliveryFailureCommand, $deliveryFailureOutput, $deliveryFailureExit);
    $pass = pollo_test_assert(0, $deliveryFailureExit, 'send exitoso con finalize fallido queda ambiguo y no se reintenta') && $pass;
    foreach (array('--mock-mysql-empty', '--mock-mysql-failure', '--mock-dual-failure') as $mockMode) {
        $mockCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . $mockMode;
        exec($mockCommand, $mockOutput, $mockExit);
        $pass = pollo_test_assert(0, $mockExit, 'backend mock seguro: ' . $mockMode) && $pass;
    }
    foreach (array('--mock-dual-write-mysql-failure', '--mock-dual-write-mirror-failure') as $mockMode) {
        $mockCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . $mockMode;
        exec($mockCommand, $mockOutput, $mockExit);
        $pass = pollo_test_assert(0, $mockExit, 'persistencia dual canónica: ' . $mockMode) && $pass;
    }

    // Two checkers split two account transitions concurrently; neither aviso may be lost.
    $concurrentBase = $tmpBase . '/concurrent';
    @mkdir($concurrentBase . '/data', 0775, true);
    chmod($concurrentBase . '/data', 0777);
    file_put_contents($concurrentBase . '/data/settings.json', json_encode(array(
        'pollo_accounts' => array(
            array('label' => 'Concurrente A', 'cookie' => 'cookie-a'),
            array('label' => 'Concurrente B', 'cookie' => 'cookie-b'),
        ),
        'avisos_config' => array('whatsapp_target_phones' => ''),
    )));
    file_put_contents($concurrentBase . '/data/avisos.json', '[]');
    file_put_contents($concurrentBase . '/data/pollo_accounts_status.json', json_encode(array(
        'Concurrente A' => array('credits_exhausted' => true, 'exhaustion_cycle' => 1, 'exhausted_at' => '2026-08-15 12:00:00'),
        'Concurrente B' => array('credits_exhausted' => true, 'exhaustion_cycle' => 1, 'exhausted_at' => '2026-08-15 12:00:00'),
    )));
    chmod($concurrentBase . '/data/avisos.json', 0644);
    chmod($concurrentBase . '/data/pollo_accounts_status.json', 0644);
    $concurrentCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__)
        . ' --concurrent-check ' . escapeshellarg($concurrentBase);
    $procA = proc_open($concurrentCommand, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipesA);
    $procB = proc_open($concurrentCommand, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipesB);
    foreach (array($pipesA, $pipesB) as $pipes) {
        if (isset($pipes[0]) && is_resource($pipes[0])) fclose($pipes[0]);
        if (isset($pipes[1]) && is_resource($pipes[1])) {
            stream_get_contents($pipes[1]);
            fclose($pipes[1]);
        }
        if (isset($pipes[2]) && is_resource($pipes[2])) {
            stream_get_contents($pipes[2]);
            fclose($pipes[2]);
        }
    }
    $exitA = is_resource($procA) ? proc_close($procA) : -1;
    $exitB = is_resource($procB) ? proc_close($procB) : -1;
    $concurrentAvisos = json_decode((string)file_get_contents($concurrentBase . '/data/avisos.json'), true);
    $concurrentAvisos = array_values(array_filter((array)$concurrentAvisos, function ($row) {
        return ($row['engine'] ?? '') === 'pollo';
    }));
    $concurrentLabels = array_map(function ($row) { return $row['meta']['account'] ?? ''; }, $concurrentAvisos);
    sort($concurrentLabels);
    $concurrentStatus = json_decode((string)file_get_contents($concurrentBase . '/data/pollo_accounts_status.json'), true);
    $pass = pollo_test_assert(0, $exitA, 'primer checker concurrente termina correctamente') && $pass;
    $pass = pollo_test_assert(0, $exitB, 'segundo checker concurrente termina correctamente') && $pass;
    $pass = pollo_test_assert(array('Concurrente A', 'Concurrente B'), $concurrentLabels, 'dos cuentas concurrentes conservan ambos avisos') && $pass;
    $bothMarked = !empty($concurrentStatus['Concurrente A']['exhaustion_notified_at'])
        && !empty($concurrentStatus['Concurrente B']['exhaustion_notified_at']);
    $pass = pollo_test_assert(true, $bothMarked, 'dos cuentas concurrentes finalizan ambos marcadores') && $pass;

    $serializedBase = $tmpBase . '/serialized';
    @mkdir($serializedBase . '/data', 0777, true);
    file_put_contents($serializedBase . '/data/settings.json', json_encode(array(
        'pollo_accounts' => array(array('label' => 'Serializada', 'cookie' => 'cookie')),
    )));
    file_put_contents($serializedBase . '/data/pollo_accounts_status.json', json_encode(array(
        'Serializada' => array('credits_exhausted' => true, 'exhaustion_cycle' => 1, 'exhausted_at' => now_datetime()),
    )));
    $spyPath = $serializedBase . '/send-spy.log';
    $serializedCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --serialized-check '
        . escapeshellarg($serializedBase) . ' ' . escapeshellarg($spyPath);
    $serializedProc = proc_open($serializedCommand, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $serializedPipes);
    for ($wait = 0; $wait < 100 && !is_file($spyPath); $wait++) usleep(10000);
    $recoverCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --mark-recovered '
        . escapeshellarg($serializedBase) . ' ' . escapeshellarg($spyPath);
    $recoverProc = proc_open($recoverCommand, array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $recoverPipes);
    usleep(150000);
    $recoverStatusDuringSend = is_resource($recoverProc) ? proc_get_status($recoverProc) : array('running' => false);
    foreach (array($serializedPipes, $recoverPipes) as $pipes) {
        foreach ((array)$pipes as $index => $pipe) {
            if (is_resource($pipe)) {
                if ((int)$index !== 0) stream_get_contents($pipe);
                fclose($pipe);
            }
        }
    }
    $serializedExit = is_resource($serializedProc) ? proc_close($serializedProc) : -1;
    $recoverExit = is_resource($recoverProc) ? proc_close($recoverProc) : -1;
    $spyLines = is_file($spyPath) ? file($spyPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : array();
    $pass = pollo_test_assert(true, !empty($recoverStatusDuringSend['running']), 'transición espera mientras se envía WhatsApp') && $pass;
    $pass = pollo_test_assert(array('send-start', 'send-end'), $spyLines, 'send spy registra una sola entrega serializada') && $pass;
    $pass = pollo_test_assert(0, $serializedExit, 'checker serializado termina correctamente') && $pass;
    $pass = pollo_test_assert(0, $recoverExit, 'recuperación bloqueada termina después del envío') && $pass;
} finally {
    pollo_test_remove_dir($tmpBase);
}

fwrite($pass ? STDOUT : STDERR, PHP_EOL . ($pass ? 'TODOS LOS TESTS OK' : 'HAY FALLOS') . PHP_EOL);
exit($pass ? 0 : 1);
