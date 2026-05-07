<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

putenv('CRM_STORAGE_BACKEND=dual');
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

$onlyArg = '';
foreach ($argv as $arg) {
    if (strpos($arg, '--only=') === 0) {
        $onlyArg = substr($arg, 7);
    }
}
if ($onlyArg !== '') {
    $requested = array();
    foreach (explode(',', $onlyArg) as $file) {
        $file = trim($file);
        if ($file !== '') $requested[$file] = $file;
    }
    $files = array_values(array_filter($files, function ($f) use ($requested) {
        return isset($requested[$f]);
    }));
}

if (empty($files)) {
    fwrite(STDOUT, "No hay archivos seleccionados\n");
    exit(0);
}

$pdo = crm_db();
$runStartedAt = now_datetime();
$runId = 0;
if ($pdo && crm_db_table_exists('crm_migration_runs', $pdo)) {
    crm_db_execute(
        'INSERT INTO crm_migration_runs (migration_key, started_at, status, details_json) VALUES (?, ?, ?, ?)',
        array('phase2_backfill', $runStartedAt, 'running', json_encode(array('files' => $files), JSON_UNESCAPED_UNICODE)),
        $pdo
    );
    $row = crm_db_query_one('SELECT LAST_INSERT_ID() AS id', array(), $pdo);
    $runId = (int)($row['id'] ?? 0);
}

$ok = true;
$summary = array();
foreach ($files as $file) {
    $spec = storage_mysql_file_spec($file);
    if (!$spec) {
        $summary[$file] = array('ok' => false, 'error' => 'sin_spec');
        $ok = false;
        continue;
    }
    $table = trim((string)($spec['table'] ?? ''));
    if ($table === '' || !crm_db_table_exists($table, $pdo)) {
        $summary[$file] = array('ok' => false, 'error' => 'tabla_no_disponible', 'table' => $table);
        $ok = false;
        continue;
    }

    $data = storage_json_read_direct($file);
    if ($file === 'telefonos.json') {
        foreach ($data as $idx => $row) {
            if (!is_array($row)) continue;
            if (!isset($row['waha']) || $row['waha'] === null || $row['waha'] === '') {
                $row['waha'] = 0;
            }
            $data[$idx] = $row;
        }
    }
    if ($file === 'eurekas.json') {
        foreach ($data as $idx => $row) {
            if (!is_array($row)) continue;
            if (isset($row['descripcion']) && is_string($row['descripcion']) && strlen($row['descripcion']) > 60000) {
                $row['descripcion'] = substr($row['descripcion'], 0, 60000);
            }
            $data[$idx] = $row;
        }
    }

    $writeOk = storage_mysql_write($file, $data);
    if (!$writeOk) {
        $summary[$file] = array('ok' => false, 'error' => 'mysql_write_failed', 'table' => $table);
        $ok = false;
        fwrite(STDOUT, "FAIL {$file} -> {$table}\n");
        continue;
    }

    if ($file === 'settings.json') {
        storage_json_write_direct($file, $data);
        storage_backend_mode_reset();
    }
    $count = is_array($data) ? count($data) : (!empty($data) ? 1 : 0);
    $summary[$file] = array('ok' => true, 'rows' => $count, 'table' => $table);
    fwrite(STDOUT, "OK {$file} -> {$table} ({$count})\n");
}

if ($pdo && $runId > 0) {
    crm_db_execute(
        'UPDATE crm_migration_runs SET finished_at = ?, status = ?, details_json = ? WHERE id = ?',
        array(now_datetime(), $ok ? 'ok' : 'failed', json_encode($summary, JSON_UNESCAPED_UNICODE), $runId),
        $pdo
    );
}

fwrite(STDOUT, ($ok ? "Backfill phase2 OK\n" : "Backfill phase2 con errores\n"));
exit($ok ? 0 : 1);
