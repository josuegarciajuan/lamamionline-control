<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$pdo = crm_db();
if (!$pdo) {
    fwrite(STDERR, "No se pudo conectar a MySQL\n");
    exit(1);
}

function phase2_index_exists($table, $indexName, $pdo) {
    $cfg = crm_db_default_config();
    $row = crm_db_query_one(
        'SELECT 1 AS ok FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
        array($cfg['db'], $table, $indexName),
        $pdo
    );
    return is_array($row);
}

function phase2_apply_sql($sql, $pdo) {
    try {
        crm_db_execute($sql, array(), $pdo);
        return true;
    } catch (Exception $e) {
        fwrite(STDERR, "SQL error: " . $e->getMessage() . "\n");
        return false;
    }
}

$ok = true;

$ok = phase2_apply_sql(
    'CREATE TABLE IF NOT EXISTS crm_comercial_ai_memory (
      id VARCHAR(64) NOT NULL PRIMARY KEY,
      owner_phone_norm VARCHAR(16) NULL,
      memory_kind VARCHAR(64) NULL,
      summary_text LONGTEXT NULL,
      payload_json JSON NULL,
      updated_at DATETIME NULL,
      created_at DATETIME NULL,
      raw_json JSON NULL,
      KEY idx_crm_comercial_ai_memory_owner_phone_norm (owner_phone_norm),
      KEY idx_crm_comercial_ai_memory_memory_kind (memory_kind),
      KEY idx_crm_comercial_ai_memory_updated_at (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    $pdo
) && $ok;

if (crm_db_table_exists('crm_eurekas', $pdo)) {
    $ok = phase2_apply_sql('ALTER TABLE crm_eurekas MODIFY descripcion LONGTEXT NULL', $pdo) && $ok;
}
if (crm_db_table_exists('crm_comercial_threads', $pdo)) {
    $ok = phase2_apply_sql('ALTER TABLE crm_comercial_threads MODIFY line_phone_norm VARCHAR(64) NULL, MODIFY target_phone_norm VARCHAR(64) NULL', $pdo) && $ok;
}

$indexes = array(
    array('crm_publicista_campaign_items', 'idx_crm_pub_items_campaign_estado', 'ALTER TABLE crm_publicista_campaign_items ADD INDEX idx_crm_pub_items_campaign_estado (campaign_id, estado)'),
    array('crm_publicista_campaign_items', 'idx_crm_pub_items_campaign_created', 'ALTER TABLE crm_publicista_campaign_items ADD INDEX idx_crm_pub_items_campaign_created (campaign_id, created_at)'),
    array('crm_publicista_tasks', 'idx_crm_pub_tasks_estado_next', 'ALTER TABLE crm_publicista_tasks ADD INDEX idx_crm_pub_tasks_estado_next (estado, next_run_at)'),
    array('crm_comercial_threads', 'idx_crm_com_threads_status_updated', 'ALTER TABLE crm_comercial_threads ADD INDEX idx_crm_com_threads_status_updated (status, updated_at)'),
    array('crm_comercial_threads', 'idx_crm_com_threads_process_stage', 'ALTER TABLE crm_comercial_threads ADD INDEX idx_crm_com_threads_process_stage (process_id, stage)'),
    array('crm_comercial_events', 'idx_crm_com_events_process_ts', 'ALTER TABLE crm_comercial_events ADD INDEX idx_crm_com_events_process_ts (process_id, ts)'),
    array('crm_comercial_events', 'idx_crm_com_events_line_ts', 'ALTER TABLE crm_comercial_events ADD INDEX idx_crm_com_events_line_ts (line_id, ts)'),
    array('crm_comercial_webhook_logs', 'idx_crm_com_webhook_from_ts', 'ALTER TABLE crm_comercial_webhook_logs ADD INDEX idx_crm_com_webhook_from_ts (from_phone_norm, ts)'),
    array('crm_comercial_webhook_logs', 'idx_crm_com_webhook_to_ts', 'ALTER TABLE crm_comercial_webhook_logs ADD INDEX idx_crm_com_webhook_to_ts (to_phone_norm, ts)'),
    array('crm_avisos', 'idx_crm_avisos_status_severity_updated', 'ALTER TABLE crm_avisos ADD INDEX idx_crm_avisos_status_severity_updated (status, severity, updated_at)'),
);

foreach ($indexes as $idx) {
    $table = $idx[0];
    $indexName = $idx[1];
    $sql = $idx[2];
    if (!crm_db_table_exists($table, $pdo)) {
        fwrite(STDOUT, "SKIP index {$indexName} (tabla {$table} no existe)\n");
        continue;
    }
    if (phase2_index_exists($table, $indexName, $pdo)) {
        fwrite(STDOUT, "OK index {$indexName} ya existe\n");
        continue;
    }
    $applied = phase2_apply_sql($sql, $pdo);
    $ok = $applied && $ok;
    fwrite(STDOUT, ($applied ? "OK" : "FAIL") . " index {$indexName}\n");
}

$ok = phase2_apply_sql(
    'CREATE TABLE IF NOT EXISTS crm_migration_runs (
      id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      migration_key VARCHAR(128) NOT NULL,
      started_at DATETIME NOT NULL,
      finished_at DATETIME NULL,
      status VARCHAR(32) NOT NULL,
      details_json JSON NULL,
      UNIQUE KEY uniq_crm_migration_runs_key_started (migration_key, started_at),
      KEY idx_crm_migration_runs_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    $pdo
) && $ok;

fwrite(STDOUT, $ok ? "Schema phase2 aplicado\n" : "Schema phase2 con errores\n");
exit($ok ? 0 : 1);
