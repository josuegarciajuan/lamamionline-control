<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

function phase1_assert($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] " . $message . PHP_EOL);
        exit(1);
    }
    fwrite(STDOUT, "[OK] " . $message . PHP_EOL);
}

$files = array(
    DATA_PATH . '/avisos.json',
    DATA_PATH . '/anuncios.json',
    DATA_PATH . '/publicista_campaigns.json',
    DATA_PATH . '/publicista_campaign_items.json',
);

$before = array();
foreach ($files as $file) {
    $before[$file] = @hash_file('sha256', $file);
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['page'] = 'dashboard';
$_SESSION['logged_in'] = true;
ob_start();
require dirname(__DIR__) . '/index.php';
ob_end_clean();

foreach ($files as $file) {
    $after = @hash_file('sha256', $file);
    phase1_assert($before[$file] === $after, 'GET dashboard no modifica ' . basename($file));
}

$id = avisos_create_active('Test Fase1', 'Comprobación mark as read', 'media', 'manual', array(), false, 'phase1_regression_' . time());
$created = storage_find_by_id('avisos.json', $id);
phase1_assert($created && empty($created['read_at']), 'Aviso de prueba creado como no leído');

avisos_mark_as_read(array($id));
$marked = storage_find_by_id('avisos.json', $id);
phase1_assert($marked && !empty($marked['read_at']), 'POST lógico mark_as_read marca read_at');

$snapshot = json_decode((string)@file_get_contents(DATA_PATH . '/avisos_active_snapshot.json'), true);
$activeSource = 0;
foreach (storage_read('avisos.json') as $row) {
    if (($row['status'] ?? '') === 'active') {
        $activeSource++;
    }
}
$activeSnapshot = (is_array($snapshot) && isset($snapshot['active_rows']) && is_array($snapshot['active_rows']))
    ? count($snapshot['active_rows'])
    : -1;
phase1_assert($activeSource === $activeSnapshot, 'Snapshot de activos consistente con avisos.json');

aviso_dismiss($id);
fwrite(STDOUT, "Fase 1 regression checks OK" . PHP_EOL);
