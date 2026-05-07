<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

putenv('CRM_STORAGE_BACKEND=json');
storage_backend_mode_reset();

$files = array(
    'users.json','agenda.json','gastos.json','interesadas.json','clientes.json','leads.json','telefonos.json','bots.json',
    'lamamibot.json','eurekas.json','casawasap_contactos.json','casawasap_pagos.json','jostal_interesadas.json',
    'jostal_clientas.json','jostal_leads.json','jostal_ventas.json','avisos.json','avisos_runs.json','comercial_settings.json',
    'comercial_processes.json','comercial_runtime.json','comercial_line_state.json','comercial_daily_stats.json',
    'comercial_threads.json','comercial_ai_memory.json','comercial_leads.json','comercial_webhook_seen.json','anuncios.json',
    'publicista_templates.json','publicista_jobs.json','publicista_plannings.json','publicista_campaigns.json',
    'publicista_campaign_items.json','publicista_tasks.json','publicista_runs.json','voice_commands_log.json',
    'voice_pending_actions.json','settings.json'
);

function parity_extract_ids($items, $spec) {
    $ids = array();
    $keyColumn = trim((string)($spec['key_column'] ?? 'id'));
    $kind = trim((string)($spec['kind'] ?? 'rows_by_id'));
    if ($kind === 'singleton') {
        $ids[] = (string)($spec['key_value'] ?? 'singleton');
        return $ids;
    }
    if ($kind === 'scalar_list') {
        foreach ((array)$items as $value) {
            $value = trim((string)$value);
            if ($value !== '') $ids[] = $value;
        }
        sort($ids);
        return array_values(array_unique($ids));
    }
    foreach ((array)$items as $row) {
        if (!is_array($row)) continue;
        $id = trim((string)($row[$keyColumn] ?? ($row['id'] ?? '')));
        if ($id !== '') $ids[] = $id;
    }
    sort($ids);
    return array_values(array_unique($ids));
}

$overallOk = true;
$report = array();

foreach ($files as $file) {
    $spec = storage_mysql_file_spec($file);
    if (!$spec) continue;

    $jsonData = storage_json_read_direct($file);
    $mysql = storage_mysql_read($file);
    $mysqlData = (!empty($mysql['ok']) && isset($mysql['data']) && is_array($mysql['data'])) ? $mysql['data'] : array();

    $jsonIds = parity_extract_ids($jsonData, $spec);
    $mysqlIds = parity_extract_ids($mysqlData, $spec);

    $missingInMysql = array_values(array_diff($jsonIds, $mysqlIds));
    $extraInMysql = array_values(array_diff($mysqlIds, $jsonIds));

    $ok = empty($missingInMysql) && empty($extraInMysql) && count($jsonData) === count($mysqlData);
    if (!$ok) $overallOk = false;

    $report[$file] = array(
        'json_count' => is_array($jsonData) ? count($jsonData) : 0,
        'mysql_count' => is_array($mysqlData) ? count($mysqlData) : 0,
        'missing_in_mysql' => $missingInMysql,
        'extra_in_mysql' => $extraInMysql,
        'ok' => $ok,
    );

    fwrite(STDOUT, ($ok ? 'OK ' : 'FAIL ') . $file . ' | json=' . count((array)$jsonData) . ' mysql=' . count((array)$mysqlData) . PHP_EOL);
}

$reportPath = DATA_PATH . '/phase2_parity_report_' . date('Ymd_His') . '.json';
@file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
fwrite(STDOUT, 'Reporte: ' . $reportPath . PHP_EOL);

exit($overallOk ? 0 : 1);
