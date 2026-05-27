<?php

function bootstrap_storage() {
    if (!is_dir(DATA_PATH)) {
        mkdir(DATA_PATH, 0775, true);
    }

    $defaultUsers = array(
        array(
            'id' => 'usr_admin',
            'username' => 'nuria',
            'password' => 'josue',
            'name' => 'Nuria'
        )
    );

    $defaults = array(
        'users.json' => $defaultUsers,
        'clientes.json' => array(),
        'bots.json' => array(),
        'lamamibot.json' => array(
            'id' => 'lamamibot',
            'nombre_bot' => 'LamamiBot',
            'estado' => '',
            'telefonos_ids' => array(),
            'clientas_ids' => array(),
            'girlsconf_json_path' => '',
            'girlsconf_base_url' => '',
            'last_sync_at' => '',
            'last_sync_summary' => '',
            'generated_assets' => array(),
            'created_at' => '',
            'updated_at' => ''
        ),
        'publicista_jobs.json' => array(),
        'publicista_templates.json' => array(),
        'publicista_plannings.json' => array(),
        'publicista_campaigns.json' => array(),
        'publicista_campaign_items.json' => array(),
        'publicista_tasks.json' => array(),
        'publicista_runs.json' => array(),
        'leads.json' => array(),
        'interesadas.json' => array(),
        'casawasap_contactos.json' => array(),
        'casawasap_pagos.json' => array(),
        'jostal_interesadas.json' => array(),
        'jostal_clientas.json' => array(),
        'jostal_leads.json' => array(),
        'jostal_ventas.json' => array(),
        'gastos.json' => array(),
        'anuncios.json' => array(),
        'telefonos.json' => array(),
        'agenda.json' => array(),
        'eurekas.json' => array(),
        'avisos.json' => array(),
        'avisos_runs.json' => array(),
        'voice_commands_log.json' => array(),
        'voice_pending_actions.json' => array(),
        'settings.json' => array(
            'lead_default_price' => 10,
            'brand' => 'LaMami CRM',
            'voice_ai_model' => 'gpt-5.1',
            'whitelist_ips' => array(
                '84.125.78.95',
                '79.116.229.72'
            )
        )
    );

    foreach ($defaults as $file => $content) {
        $path = DATA_PATH . '/' . $file;
        if (!file_exists($path)) {
            file_put_contents($path, json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    publicista_ensure_base_dirs();
    if (function_exists('comercial_bootstrap_storage')) {
        comercial_bootstrap_storage();
    }
}

function storage_run_maintenance_compaction($options = array()) {
    $options = is_array($options) ? $options : array();
    $runAccounts = !array_key_exists('accounts', $options) || !empty($options['accounts']);
    $runCampaigns = !array_key_exists('campaigns', $options) || !empty($options['campaigns']);
    $force = !empty($options['force']);

    $result = array(
        'accounts' => null,
        'campaigns' => null,
    );

    if ($runAccounts && function_exists('publicista_accounts_compact_storage_data')) {
        $result['accounts'] = publicista_accounts_compact_storage_data($force);
    }
    if ($runCampaigns && function_exists('publicista_campaign_compact_storage_data')) {
        $result['campaigns'] = publicista_campaign_compact_storage_data($force);
    }

    return $result;
}

function storage_backend_allowed_modes() {
    return array('json', 'dual', 'mysql');
}

function storage_backend_mode($refresh = false) {
    if ($refresh || !isset($GLOBALS['storage_backend_mode_cache'])) {
        $mode = '';
        $envValue = getenv('CRM_STORAGE_BACKEND');
        if (is_string($envValue)) {
            $mode = trim($envValue);
        }
        if ($mode === '' && isset($_ENV['CRM_STORAGE_BACKEND'])) {
            $mode = trim((string)$_ENV['CRM_STORAGE_BACKEND']);
        }
        if ($mode === '') {
            $settings = storage_bootstrap_settings_raw();
            $mode = trim((string)($settings['storage_backend'] ?? 'json'));
        }
        if (!in_array($mode, storage_backend_allowed_modes(), true)) {
            $mode = 'json';
        }
        $GLOBALS['storage_backend_mode_cache'] = $mode;
    }
    return (string)$GLOBALS['storage_backend_mode_cache'];
}

function storage_backend_mode_reset() {
    unset($GLOBALS['storage_backend_mode_cache']);
}

function storage_cache_key($file) {
    return storage_backend_mode() . '|' . trim((string)$file);
}

function storage_invalidate_cache($file = '') {
    $file = trim((string)$file);
    if (!isset($GLOBALS['storage_read_cache']) || !is_array($GLOBALS['storage_read_cache'])) {
        $GLOBALS['storage_read_cache'] = array();
        return;
    }
    if ($file === '') {
        $GLOBALS['storage_read_cache'] = array();
        return;
    }

    foreach (array_keys($GLOBALS['storage_read_cache']) as $cacheKey) {
        if ($cacheKey === $file || substr((string)$cacheKey, -1 * strlen('|' . $file)) === '|' . $file) {
            unset($GLOBALS['storage_read_cache'][$cacheKey]);
        }
    }
}

function storage_json_path($file) {
    return DATA_PATH . '/' . ltrim(trim((string)$file), '/');
}

function storage_bootstrap_settings_raw() {
    $path = storage_json_path('settings.json');
    if (!is_file($path)) {
        return array();
    }
    $raw = @file_get_contents($path);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : array();
}

function storage_json_read_direct($file) {
    $path = storage_json_path($file);
    if (!file_exists($path)) {
        return array();
    }
    if ($file === 'anuncios.json') {
        return storage_read_accounts_fast($path);
    }
    if (storage_should_stream_read($file, $path)) {
        return storage_read_large_array_stream($path);
    }
    $raw = @file_get_contents($path);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : array();
}

function storage_runtime_log($event, $context = array()) {
    if (!function_exists('bootstrap_runtime_log')) return;
    $parts = array();
    foreach ((array)$context as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $parts[] = trim((string)$key) . '=' . trim((string)$value);
        }
    }
    bootstrap_runtime_log('storage | ' . trim((string)$event) . (empty($parts) ? '' : (' | ' . implode(' | ', $parts))));
}

function storage_json_write_direct($file, $data) {
    $path = storage_json_path($file);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $pretty = storage_should_pretty_write($file, $data);
    if (function_exists('bootstrap_runtime_log') && is_array($data)) {
        $rows = count($data);
        $mem = function_exists('memory_get_usage') ? memory_get_usage(true) : 0;
        if ($rows > 100 || $mem > 256 * 1024 * 1024) {
            bootstrap_runtime_log('storage_write_json | file=' . $file . ' | rows=' . $rows . ' | mem=' . $mem);
        }
    }
    $json = storage_json_encode($data, $pretty);
    $tmpPath = $path . '.tmp';
    $written = @file_put_contents($tmpPath, $json, LOCK_EX);
    if ($written === false) {
        storage_runtime_log('tmp_write_failed', array('file' => $file, 'tmp_path' => $tmpPath));
        $fallbackWritten = @file_put_contents($path, $json, LOCK_EX);
        if ($fallbackWritten === false) {
            storage_runtime_log('final_write_failed', array('file' => $file, 'path' => $path));
        }
    } else {
        $renamed = @rename($tmpPath, $path);
        if (!$renamed) {
            storage_runtime_log('rename_failed', array('file' => $file, 'tmp_path' => $tmpPath, 'path' => $path));
            $fallbackWritten = @file_put_contents($path, $json, LOCK_EX);
            if ($fallbackWritten === false) {
                storage_runtime_log('final_write_failed', array('file' => $file, 'path' => $path));
            }
            @unlink($tmpPath);
        }
    }
}

function storage_json_upsert_direct($file, $row) {
    $rows = storage_json_read_direct($file);
    $updated = false;
    foreach ($rows as $i => $item) {
        if (isset($item['id']) && $item['id'] === $row['id']) {
            $rows[$i] = array_merge($item, $row);
            $updated = true;
            break;
        }
    }
    if (!$updated) {
        $rows[] = $row;
    }
    storage_json_write_direct($file, array_values($rows));
}

function storage_json_delete_direct($file, $id) {
    $rows = storage_json_read_direct($file);
    $out = array();
    foreach ($rows as $row) {
        if (!isset($row['id']) || $row['id'] !== $id) {
            $out[] = $row;
        }
    }
    storage_json_write_direct($file, array_values($out));
}

function storage_mysql_file_spec($file) {
    static $specs = null;
    if ($specs === null) {
        $specs = array(
            'settings.json' => array('kind' => 'singleton', 'table' => 'crm_settings', 'key_column' => 'id', 'key_value' => 'settings', 'json_column' => 'payload_json'),
            'users.json' => array('kind' => 'rows_by_id', 'table' => 'crm_users', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'agenda.json' => array('kind' => 'rows_by_id', 'table' => 'crm_agenda', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'gastos.json' => array('kind' => 'rows_by_id', 'table' => 'crm_gastos', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'interesadas.json' => array('kind' => 'rows_by_id', 'table' => 'crm_interesadas', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'clientes.json' => array('kind' => 'rows_by_id', 'table' => 'crm_clientes', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'leads.json' => array('kind' => 'rows_by_id', 'table' => 'crm_leads', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'telefonos.json' => array('kind' => 'rows_by_id', 'table' => 'crm_telefonos', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'bots.json' => array('kind' => 'rows_by_id', 'table' => 'crm_bots', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'lamamibot.json' => array('kind' => 'singleton', 'table' => 'crm_lamamibot', 'key_column' => 'id', 'key_value' => 'lamamibot', 'json_column' => 'raw_json'),
            'eurekas.json' => array('kind' => 'rows_by_id', 'table' => 'crm_eurekas', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'casawasap_contactos.json' => array('kind' => 'rows_by_id', 'table' => 'crm_casawasap_contactos', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'casawasap_pagos.json' => array('kind' => 'rows_by_id', 'table' => 'crm_casawasap_pagos', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'jostal_interesadas.json' => array('kind' => 'rows_by_id', 'table' => 'crm_jostal_interesadas', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'jostal_clientas.json' => array('kind' => 'rows_by_id', 'table' => 'crm_jostal_clientas', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'jostal_leads.json' => array('kind' => 'rows_by_id', 'table' => 'crm_jostal_leads', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'jostal_ventas.json' => array('kind' => 'rows_by_id', 'table' => 'crm_jostal_ventas', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'contratos.json' => array('kind' => 'rows_by_id', 'table' => 'crm_contratos', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'avisos.json' => array('kind' => 'rows_by_id', 'table' => 'crm_avisos', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'avisos_runs.json' => array('kind' => 'rows_by_id', 'table' => 'crm_avisos_runs', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'comercial_settings.json' => array('kind' => 'singleton', 'table' => 'crm_comercial_settings', 'key_column' => 'id', 'key_value' => 'comercial_settings', 'json_column' => 'payload_json'),
            'comercial_processes.json' => array('kind' => 'rows_by_id', 'table' => 'crm_comercial_processes', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'comercial_runtime.json' => array('kind' => 'singleton', 'table' => 'crm_comercial_runtime', 'key_column' => 'scope_key', 'key_value' => 'runtime', 'json_column' => 'payload_json'),
            'comercial_line_state.json' => array('kind' => 'rows_by_id', 'table' => 'crm_comercial_line_state', 'key_column' => 'line_id', 'json_column' => 'raw_json'),
            'comercial_daily_stats.json' => array('kind' => 'singleton', 'table' => 'crm_comercial_daily_stats', 'key_column' => 'scope_key', 'key_value' => '__full__', 'json_column' => 'payload_json'),
            'comercial_threads.json' => array('kind' => 'rows_by_id', 'table' => 'crm_comercial_threads', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'comercial_ai_memory.json' => array('kind' => 'rows_by_id', 'table' => 'crm_comercial_ai_memory', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'comercial_leads.json' => array('kind' => 'rows_by_id', 'table' => 'crm_comercial_leads', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'comercial_webhook_seen.json' => array('kind' => 'scalar_list', 'table' => 'crm_comercial_webhook_seen', 'key_column' => 'message_id'),
            'anuncios.json' => array('kind' => 'rows_by_id', 'table' => 'crm_publicista_accounts', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'publicista_templates.json' => array('kind' => 'rows_by_id', 'table' => 'crm_publicista_templates', 'key_column' => 'id', 'json_column' => 'payload_json'),
            'publicista_jobs.json' => array('kind' => 'rows_by_id', 'table' => 'crm_publicista_jobs', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'publicista_plannings.json' => array('kind' => 'rows_by_id', 'table' => 'crm_publicista_plannings', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'publicista_campaigns.json' => array('kind' => 'rows_by_id', 'table' => 'crm_publicista_campaigns', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'publicista_campaign_items.json' => array('kind' => 'rows_by_id', 'table' => 'crm_publicista_campaign_items', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'publicista_tasks.json' => array('kind' => 'rows_by_id', 'table' => 'crm_publicista_tasks', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'publicista_runs.json' => array('kind' => 'rows_by_id', 'table' => 'crm_publicista_runs', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'voice_commands_log.json' => array('kind' => 'rows_by_id', 'table' => 'crm_voice_commands_log', 'key_column' => 'id', 'json_column' => 'raw_json'),
            'voice_pending_actions.json' => array('kind' => 'rows_by_id', 'table' => 'crm_voice_pending_actions', 'key_column' => 'token', 'json_column' => 'raw_json'),
        );
    }
    return isset($specs[$file]) ? $specs[$file] : null;
}

function storage_mysql_log_message($message) {
    $message = trim((string)$message);
    if ($message === '') {
        return;
    }
    if (function_exists('bootstrap_runtime_log')) {
        bootstrap_runtime_log('storage_mysql | ' . $message);
    }
}

function storage_mysql_table_ready($table) {
    static $cache = array();
    $table = trim((string)$table);
    if ($table === '') {
        return false;
    }
    if (array_key_exists($table, $cache)) {
        return !empty($cache[$table]);
    }
    $ready = crm_db_table_exists($table) && !empty(crm_db_table_columns($table));
    $cache[$table] = $ready ? 1 : 0;
    return $ready;
}

function storage_mysql_json_encode_compact($value) {
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) $flags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
    $json = json_encode($value, $flags);
    return ($json === false) ? 'null' : $json;
}

function storage_mysql_is_json_type($dataType) {
    return strtolower(trim((string)$dataType)) === 'json';
}

function storage_mysql_is_integer_type($dataType) {
    return in_array(strtolower(trim((string)$dataType)), array('tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint'), true);
}

function storage_mysql_is_decimal_type($dataType) {
    return in_array(strtolower(trim((string)$dataType)), array('decimal', 'numeric', 'float', 'double', 'real'), true);
}

function storage_mysql_is_datetime_type($dataType) {
    return in_array(strtolower(trim((string)$dataType)), array('datetime', 'timestamp'), true);
}

function storage_mysql_is_date_type($dataType) {
    return strtolower(trim((string)$dataType)) === 'date';
}

function storage_mysql_phone_digits($value) {
    return preg_replace('/\D+/', '', (string)$value);
}

function storage_mysql_normalize_datetime_value($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $value = str_replace('T', ' ', $value);
    if (strlen($value) === 16) {
        $value .= ':00';
    }
    return $value;
}

function storage_mysql_normalize_date_value($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    if (strlen($value) >= 10) {
        return substr($value, 0, 10);
    }
    return $value;
}

function storage_mysql_value_for_column($meta, $value) {
    $meta = is_array($meta) ? $meta : array();
    $dataType = strtolower(trim((string)($meta['data_type'] ?? '')));

    if ($value === null) {
        return null;
    }
    if (storage_mysql_is_json_type($dataType)) {
        return storage_mysql_json_encode_compact($value);
    }
    if (storage_mysql_is_datetime_type($dataType)) {
        return storage_mysql_normalize_datetime_value($value);
    }
    if (storage_mysql_is_date_type($dataType)) {
        return storage_mysql_normalize_date_value($value);
    }
    if (storage_mysql_is_integer_type($dataType)) {
        if ($value === '') {
            return null;
        }
        return (int)$value;
    }
    if (storage_mysql_is_decimal_type($dataType)) {
        if ($value === '') {
            return null;
        }
        return (float)$value;
    }
    if (is_array($value)) {
        return storage_mysql_json_encode_compact($value);
    }
    return (string)$value;
}

function storage_mysql_derive_column_value($spec, $column, $row, $meta) {
    $column = trim((string)$column);
    $row = is_array($row) ? $row : array();

    if (substr($column, -5) === '_json') {
        $baseKey = substr($column, 0, -5);
        if ($baseKey !== '' && array_key_exists($baseKey, $row)) {
            return array('has' => true, 'value' => storage_mysql_value_for_column($meta, $row[$baseKey]));
        }
    }

    switch ($column) {
        case 'telefono_norm':
            foreach (array('telefono', 'tfono', 'telefono_bot', 'target_phone', 'line_phone') as $sourceKey) {
                if (!array_key_exists($sourceKey, $row)) continue;
                return array('has' => true, 'value' => storage_mysql_phone_digits($row[$sourceKey]));
            }
            break;
        case 'movil_origen_norm':
            if (array_key_exists('movil_origen', $row)) {
                return array('has' => true, 'value' => storage_mysql_phone_digits($row['movil_origen']));
            }
            break;
        case 'line_phone_norm':
            if (array_key_exists('line_phone', $row)) {
                return array('has' => true, 'value' => storage_mysql_phone_digits($row['line_phone']));
            }
            break;
        case 'target_phone_norm':
            if (array_key_exists('target_phone', $row)) {
                return array('has' => true, 'value' => storage_mysql_phone_digits($row['target_phone']));
            }
            break;
        case 'password_hash':
            if (array_key_exists('password_hash', $row)) {
                return array('has' => true, 'value' => storage_mysql_value_for_column($meta, $row['password_hash']));
            }
            if (array_key_exists('password', $row)) {
                return array('has' => true, 'value' => storage_mysql_value_for_column($meta, $row['password']));
            }
            break;
        case 'key_column':
            break;
    }

    return array('has' => false, 'value' => null);
}

function storage_mysql_prepare_record_from_row($spec, $row) {
    $spec = is_array($spec) ? $spec : array();
    $row = is_array($row) ? $row : array();
    $table = trim((string)($spec['table'] ?? ''));
    $keyColumn = trim((string)($spec['key_column'] ?? 'id'));
    $jsonColumn = trim((string)($spec['json_column'] ?? ''));
    $columns = crm_db_table_columns($table);
    if (empty($columns)) {
        return array();
    }

    $record = array();
    foreach ($columns as $column => $meta) {
        if (strpos((string)($meta['extra'] ?? ''), 'auto_increment') !== false) {
            continue;
        }

        if ($column === $keyColumn) {
            if (isset($spec['key_value'])) {
                $record[$column] = (string)$spec['key_value'];
            } elseif ($keyColumn === 'id' && isset($row['id'])) {
                $record[$column] = (string)$row['id'];
            } elseif (isset($row[$keyColumn])) {
                $record[$column] = (string)$row[$keyColumn];
            }
            continue;
        }

        if ($jsonColumn !== '' && $column === $jsonColumn) {
            $record[$column] = storage_mysql_json_encode_compact($row);
            continue;
        }

        if (array_key_exists($column, $row)) {
            $record[$column] = storage_mysql_value_for_column($meta, $row[$column]);
            continue;
        }

        $derived = storage_mysql_derive_column_value($spec, $column, $row, $meta);
        if (!empty($derived['has'])) {
            $record[$column] = $derived['value'];
        }
    }

    return $record;
}

function storage_mysql_upsert_record($table, $record, $keyColumn, $pdo) {
    $tableSql = crm_db_quote_identifier($table);
    $keySql = crm_db_quote_identifier($keyColumn);
    if ($tableSql === '' || $keySql === '' || empty($record)) {
        return false;
    }

    $columns = array_keys($record);
    $quotedColumns = array();
    $placeholders = array();
    $params = array();
    $updates = array();

    foreach ($columns as $column) {
        $quoted = crm_db_quote_identifier($column);
        if ($quoted === '') {
            continue;
        }
        $quotedColumns[] = $quoted;
        $placeholders[] = '?';
        $params[] = $record[$column];
        if ($column !== $keyColumn) {
            $updates[] = $quoted . ' = VALUES(' . $quoted . ')';
        }
    }

    if (empty($quotedColumns)) {
        return false;
    }

    $sql = 'INSERT INTO ' . $tableSql . ' (' . implode(', ', $quotedColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    if (!empty($updates)) {
        $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
    }

    return crm_db_execute($sql, $params, $pdo);
}

function storage_mysql_order_sql($table, $keyColumn) {
    $columns = crm_db_table_columns($table);
    $parts = array();
    foreach (array('updated_at', 'created_at') as $candidate) {
        if (isset($columns[$candidate])) {
            $quoted = crm_db_quote_identifier($candidate);
            if ($quoted !== '') {
                $parts[] = $quoted . ' DESC';
            }
        }
    }
    $quotedKey = crm_db_quote_identifier($keyColumn);
    if ($quotedKey !== '') {
        $parts[] = $quotedKey . ' ASC';
    }
    return empty($parts) ? '' : (' ORDER BY ' . implode(', ', $parts));
}

function storage_mysql_decode_row($spec, $dbRow) {
    $spec = is_array($spec) ? $spec : array();
    $dbRow = is_array($dbRow) ? $dbRow : array();
    $jsonColumn = trim((string)($spec['json_column'] ?? ''));
    if ($jsonColumn !== '' && isset($dbRow[$jsonColumn])) {
        $decoded = json_decode((string)$dbRow[$jsonColumn], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $out = array();
    foreach ($dbRow as $key => $value) {
        if ($key === $jsonColumn) {
            continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

function storage_mysql_read($file) {
    $spec = storage_mysql_file_spec($file);
    if (!$spec) {
        return array('ok' => false, 'has_rows' => false, 'data' => array());
    }

    $pdo = crm_db();
    if (!$pdo) {
        return array('ok' => false, 'has_rows' => false, 'data' => array());
    }

    $table = trim((string)($spec['table'] ?? ''));
    if (!storage_mysql_table_ready($table)) {
        return array('ok' => false, 'has_rows' => false, 'data' => array());
    }

    $kind = trim((string)($spec['kind'] ?? 'rows_by_id'));
    try {
        if ($kind === 'singleton') {
            $keyColumn = crm_db_quote_identifier($spec['key_column']);
            $tableSql = crm_db_quote_identifier($table);
            if ($keyColumn === '' || $tableSql === '') {
                return array('ok' => false, 'has_rows' => false, 'data' => array());
            }
            $sql = 'SELECT * FROM ' . $tableSql . ' WHERE ' . $keyColumn . ' = ? LIMIT 1';
            $row = crm_db_query_one($sql, array($spec['key_value']), $pdo);
            if (!is_array($row)) {
                return array('ok' => true, 'has_rows' => false, 'data' => array());
            }
            return array('ok' => true, 'has_rows' => true, 'data' => storage_mysql_decode_row($spec, $row));
        }

        if ($kind === 'scalar_list') {
            $tableSql = crm_db_quote_identifier($table);
            $keyColumn = crm_db_quote_identifier($spec['key_column']);
            if ($tableSql === '' || $keyColumn === '') {
                return array('ok' => false, 'has_rows' => false, 'data' => array());
            }
            $orderSql = '';
            $columns = crm_db_table_columns($table);
            if (isset($columns['first_seen_at'])) {
                $orderSql = ' ORDER BY `first_seen_at` ASC, ' . $keyColumn . ' ASC';
            } else {
                $orderSql = ' ORDER BY ' . $keyColumn . ' ASC';
            }
            $rows = crm_db_query_all('SELECT ' . $keyColumn . ' AS value FROM ' . $tableSql . $orderSql, array(), $pdo);
            $out = array();
            foreach ($rows as $row) {
                $value = trim((string)($row['value'] ?? ''));
                if ($value !== '') {
                    $out[] = $value;
                }
            }
            return array('ok' => true, 'has_rows' => !empty($out), 'data' => array_values($out));
        }

        $tableSql = crm_db_quote_identifier($table);
        $rows = crm_db_query_all('SELECT * FROM ' . $tableSql . storage_mysql_order_sql($table, $spec['key_column']), array(), $pdo);
        $out = array();
        foreach ($rows as $row) {
            $decoded = storage_mysql_decode_row($spec, $row);
            if (is_array($decoded)) {
                $out[] = $decoded;
            }
        }
        return array('ok' => true, 'has_rows' => !empty($out), 'data' => array_values($out));
    } catch (Exception $e) {
        storage_mysql_log_message('read fail | file=' . $file . ' | ' . $e->getMessage());
        return array('ok' => false, 'has_rows' => false, 'data' => array());
    }
}

function storage_mysql_sync_comercial_process_lines($rows, $pdo) {
    if (!storage_mysql_table_ready('crm_comercial_process_lines')) {
        return true;
    }
    crm_db_execute('DELETE FROM `crm_comercial_process_lines`', array(), $pdo);
    foreach ((array)$rows as $row) {
        if (!is_array($row)) continue;
        $processId = trim((string)($row['id'] ?? ''));
        if ($processId === '') continue;
        foreach ((array)($row['assigned_line_ids'] ?? array()) as $lineId) {
            $lineId = trim((string)$lineId);
            if ($lineId === '') continue;
            crm_db_execute(
                'INSERT INTO `crm_comercial_process_lines` (`process_id`, `line_id`, `created_at`) VALUES (?, ?, ?)',
                array($processId, $lineId, now_datetime()),
                $pdo
            );
        }
    }
    return true;
}

function storage_mysql_sync_comercial_process_line_row($row, $pdo) {
    if (!storage_mysql_table_ready('crm_comercial_process_lines')) {
        return true;
    }
    $processId = trim((string)($row['id'] ?? ''));
    if ($processId === '') {
        return true;
    }
    crm_db_execute('DELETE FROM `crm_comercial_process_lines` WHERE `process_id` = ?', array($processId), $pdo);
    foreach ((array)($row['assigned_line_ids'] ?? array()) as $lineId) {
        $lineId = trim((string)$lineId);
        if ($lineId === '') continue;
        crm_db_execute(
            'INSERT INTO `crm_comercial_process_lines` (`process_id`, `line_id`, `created_at`) VALUES (?, ?, ?)',
            array($processId, $lineId, now_datetime()),
            $pdo
        );
    }
    return true;
}

function storage_mysql_delete_comercial_process_lines($processId, $pdo) {
    if (!storage_mysql_table_ready('crm_comercial_process_lines')) {
        return true;
    }
    return crm_db_execute('DELETE FROM `crm_comercial_process_lines` WHERE `process_id` = ?', array($processId), $pdo);
}

function storage_mysql_write($file, $data) {
    $spec = storage_mysql_file_spec($file);
    if (!$spec) {
        return false;
    }

    $pdo = crm_db();
    if (!$pdo) {
        return false;
    }

    $table = trim((string)($spec['table'] ?? ''));
    if (!storage_mysql_table_ready($table)) {
        return false;
    }

    $tableSql = crm_db_quote_identifier($table);
    if ($tableSql === '') {
        return false;
    }

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }

        $kind = trim((string)($spec['kind'] ?? 'rows_by_id'));
        if ($kind === 'singleton') {
            $record = storage_mysql_prepare_record_from_row($spec, is_array($data) ? $data : array());
            storage_mysql_upsert_record($table, $record, $spec['key_column'], $pdo);
        } elseif ($kind === 'scalar_list') {
            crm_db_execute('DELETE FROM ' . $tableSql, array(), $pdo);
            foreach ((array)$data as $value) {
                $value = trim((string)$value);
                if ($value === '') continue;
                crm_db_execute(
                    'INSERT INTO ' . $tableSql . ' (`' . $spec['key_column'] . '`, `first_seen_at`) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE `first_seen_at` = VALUES(`first_seen_at`)',
                    array($value, now_datetime()),
                    $pdo
                );
            }
        } else {
            crm_db_execute('DELETE FROM ' . $tableSql, array(), $pdo);
            foreach ((array)$data as $row) {
                if (!is_array($row)) continue;
                $record = storage_mysql_prepare_record_from_row($spec, $row);
                if (empty($record)) continue;
                storage_mysql_upsert_record($table, $record, $spec['key_column'], $pdo);
            }
            if ($file === 'comercial_processes.json') {
                storage_mysql_sync_comercial_process_lines($data, $pdo);
            }
        }

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        storage_mysql_log_message('write fail | file=' . $file . ' | ' . $e->getMessage());
        return false;
    }
}

function storage_mysql_upsert($file, $row) {
    $spec = storage_mysql_file_spec($file);
    if (!$spec || trim((string)($spec['kind'] ?? '')) !== 'rows_by_id') {
        return false;
    }

    $pdo = crm_db();
    if (!$pdo) {
        return false;
    }

    $table = trim((string)($spec['table'] ?? ''));
    if (!storage_mysql_table_ready($table)) {
        return false;
    }

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
        $record = storage_mysql_prepare_record_from_row($spec, is_array($row) ? $row : array());
        if (empty($record)) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
        storage_mysql_upsert_record($table, $record, $spec['key_column'], $pdo);
        if ($file === 'comercial_processes.json') {
            storage_mysql_sync_comercial_process_line_row($row, $pdo);
        }
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        storage_mysql_log_message('upsert fail | file=' . $file . ' | ' . $e->getMessage());
        return false;
    }
}

function storage_mysql_delete($file, $id) {
    $spec = storage_mysql_file_spec($file);
    if (!$spec || trim((string)($spec['kind'] ?? '')) !== 'rows_by_id') {
        return false;
    }

    $pdo = crm_db();
    if (!$pdo) {
        return false;
    }

    $table = trim((string)($spec['table'] ?? ''));
    $tableSql = crm_db_quote_identifier($table);
    $keySql = crm_db_quote_identifier($spec['key_column']);
    if (!storage_mysql_table_ready($table) || $tableSql === '' || $keySql === '') {
        return false;
    }

    try {
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
        crm_db_execute('DELETE FROM ' . $tableSql . ' WHERE ' . $keySql . ' = ?', array($id), $pdo);
        if ($file === 'comercial_processes.json') {
            storage_mysql_delete_comercial_process_lines($id, $pdo);
        }
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        storage_mysql_log_message('delete fail | file=' . $file . ' | ' . $e->getMessage());
        return false;
    }
}

function storage_read($file) {
    $mode = storage_backend_mode();
    $cacheKey = storage_cache_key($file);
    if ($cacheKey !== '' && isset($GLOBALS['storage_read_cache']) && array_key_exists($cacheKey, (array)$GLOBALS['storage_read_cache'])) {
        $cached = $GLOBALS['storage_read_cache'][$cacheKey];
        return is_array($cached) ? $cached : array();
    }

    $data = array();
    $cacheable = false;

    if ($mode === 'mysql') {
        $mysql = storage_mysql_read($file);
        if (!empty($mysql['ok'])) {
            $data = is_array($mysql['data']) ? $mysql['data'] : array();
        } else {
            $data = storage_json_read_direct($file);
        }
    } elseif ($mode === 'dual') {
        $mysql = storage_mysql_read($file);
        if (!empty($mysql['ok']) && !empty($mysql['has_rows'])) {
            $data = is_array($mysql['data']) ? $mysql['data'] : array();
        } else {
            $data = storage_json_read_direct($file);
            $cacheable = true;
        }
    } else {
        $data = storage_json_read_direct($file);
        $cacheable = true;
    }

    if ($cacheable && $cacheKey !== '') {
        if (!isset($GLOBALS['storage_read_cache']) || !is_array($GLOBALS['storage_read_cache'])) {
            $GLOBALS['storage_read_cache'] = array();
        }
        $sizeBytes = @filesize(storage_json_path($file));
        if ($sizeBytes !== false && (int)$sizeBytes <= 1024 * 1024) {
            $GLOBALS['storage_read_cache'][$cacheKey] = $data;
        } else {
            unset($GLOBALS['storage_read_cache'][$cacheKey]);
        }
    }
    return $data;
}

function storage_should_stream_read($file, $path) {
    $file = trim((string)$file);
    $path = trim((string)$path);
    if ($file === '' || $path === '') {
        return false;
    }
    $size = @filesize($path);
    if ($size === false || (int)$size < 1024 * 1024) {
        return false;
    }
    $prefix = @file_get_contents($path, false, null, 0, 4096);
    if (!is_string($prefix) || $prefix === '') {
        return false;
    }
    $prefix = ltrim($prefix, "\xEF\xBB\xBF \t\r\n");
    return $prefix !== '' && $prefix[0] === '[';
}

function storage_walk_large_array_stream($path, $callback) {
    if (!is_callable($callback)) {
        return;
    }
    $handle = @fopen($path, 'rb');
    if (!$handle) {
        return;
    }

    $started = false;
    $inString = false;
    $escape = false;
    $arrayDepth = 0;
    $objectDepth = 0;
    $capturing = false;
    $current = '';

    while (!feof($handle)) {
        $chunk = @fread($handle, 65536);
        if (!is_string($chunk) || $chunk === '') {
            continue;
        }
        $len = strlen($chunk);
        for ($i = 0; $i < $len; $i++) {
            $ch = $chunk[$i];

            if (!$started) {
                if ($ch === '[') {
                    $started = true;
                    $arrayDepth = 1;
                }
                continue;
            }

            if (!$inString && $ch === '{' && $arrayDepth === 1 && $objectDepth === 0) {
                $capturing = true;
                $current = '';
            }

            if ($capturing) {
                $current .= $ch;
            }

            if ($inString) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === '"') {
                    $inString = false;
                }
                continue;
            }

            if ($ch === '"') {
                $inString = true;
                continue;
            }
            if ($ch === '[') {
                $arrayDepth++;
                continue;
            }
            if ($ch === ']') {
                if ($arrayDepth > 0) {
                    $arrayDepth--;
                }
                if ($arrayDepth === 0) {
                    break 2;
                }
                continue;
            }
            if ($ch === '{') {
                $objectDepth++;
                continue;
            }
            if ($ch === '}') {
                if ($objectDepth > 0) {
                    $objectDepth--;
                    if ($capturing && $arrayDepth === 1 && $objectDepth === 0) {
                        $row = json_decode($current, true);
                        if (is_array($row)) {
                            call_user_func($callback, $row);
                        }
                        $capturing = false;
                        $current = '';
                    }
                }
                continue;
            }
        }
    }

    @fclose($handle);
}

function storage_read_large_array_stream($path, $rowFilter = null) {
    $rows = array();
    storage_walk_large_array_stream($path, function($row) use (&$rows, $rowFilter) {
        if (is_callable($rowFilter) && !call_user_func($rowFilter, $row)) {
            return;
        }
        $rows[] = $row;
    });
    return $rows;
}

function storage_walk_rows($file, $callback) {
    if (!is_callable($callback)) {
        return;
    }
    $file = trim((string)$file);
    if ($file === '') {
        return;
    }
    $path = storage_json_path($file);
    if (storage_backend_mode() === 'json' && file_exists($path) && $file !== 'anuncios.json' && storage_should_stream_read($file, $path)) {
        storage_walk_large_array_stream($path, $callback);
    } else {
        foreach ((array)storage_read($file) as $row) {
            call_user_func($callback, $row);
        }
    }
}

function storage_read_filtered($file, $rowFilter = null) {
    $rows = array();
    storage_walk_rows($file, function($row) use (&$rows, $rowFilter) {
        if (is_callable($rowFilter) && !call_user_func($rowFilter, $row)) {
            return;
        }
        $rows[] = $row;
    });
    return $rows;
}

function storage_extract_top_level_json_object_chunks($raw) {
    $raw = trim((string)$raw);
    if ($raw === '' || $raw[0] !== '[') {
        return array();
    }

    $chunks = array();
    $len = strlen($raw);
    $inString = false;
    $escape = false;
    $squareDepth = 0;
    $curlyDepth = 0;
    $start = null;

    for ($i = 0; $i < $len; $i++) {
        $ch = $raw[$i];
        if ($inString) {
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($ch === '\\') {
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                $inString = false;
            }
            continue;
        }

        if ($ch === '"') {
            $inString = true;
            continue;
        }
        if ($ch === '[') {
            $squareDepth++;
            continue;
        }
        if ($ch === ']') {
            $squareDepth = max(0, $squareDepth - 1);
            continue;
        }
        if ($ch === '{') {
            if ($squareDepth === 1 && $curlyDepth === 0) {
                $start = $i;
            }
            $curlyDepth++;
            continue;
        }
        if ($ch === '}') {
            if ($curlyDepth > 0) {
                $curlyDepth--;
                if ($squareDepth === 1 && $curlyDepth === 0 && $start !== null) {
                    $chunks[] = substr($raw, $start, $i - $start + 1);
                    $start = null;
                }
            }
            continue;
        }
    }

    return $chunks;
}

function storage_read_accounts_fast($path) {
    $raw = @file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return array();
    }

    $chunks = storage_extract_top_level_json_object_chunks($raw);
    if (empty($chunks)) {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return array();
        }
        $rows = array();
        foreach ($decoded as $row) {
            $rows[] = publicista_account_normalize(publicista_account_strip_runtime_fields($row));
        }
        return $rows;
    }

    $rows = array();
    foreach ($chunks as $chunk) {
        $row = json_decode($chunk, true);
        if (!is_array($row)) {
            continue;
        }
        $rows[] = publicista_account_normalize(publicista_account_strip_runtime_fields($row));
    }
    return $rows;
}

function storage_json_encode($data, $pretty = true) {
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) $flags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
    if ($pretty) $flags |= JSON_PRETTY_PRINT;
    $json = json_encode($data, $flags);
    if ($json === false) {
        $json = $pretty ? "[]\n" : "[]";
    }
    return $json;
}

function storage_should_pretty_write($file, $data) {
    $file = trim((string)$file);
    if (in_array($file, array(
        'publicista_campaigns.json',
        'publicista_campaign_items.json',
        'publicista_runs.json',
        'publicista_plannings.json',
        'anuncios.json',
        'bots.json',
        'avisos.json',
        'avisos_runs.json',
    ), true)) {
        return false;
    }
    if (is_array($data) && count($data) > 200) {
        return false;
    }
    return true;
}

function storage_write($file, $data) {
    $mode = storage_backend_mode();
    $mysqlOk = false;
    if (in_array($mode, array('dual', 'mysql'), true)) {
        $mysqlOk = storage_mysql_write($file, $data);
    }
    if ($mode !== 'mysql' || !$mysqlOk || $file === 'settings.json') {
        storage_json_write_direct($file, $data);
    }
    if ($file === 'settings.json') {
        storage_backend_mode_reset();
    }
    storage_invalidate_cache($file);
}

function storage_find_by_id($file, $id) {
    $rows = storage_read($file);
    foreach ($rows as $row) {
        if (isset($row['id']) && $row['id'] === $id) {
            return $row;
        }
    }
    return null;
}

function storage_upsert($file, $row) {
    $mode = storage_backend_mode();
    $mysqlOk = false;
    if (in_array($mode, array('dual', 'mysql'), true)) {
        $mysqlOk = storage_mysql_upsert($file, $row);
    }
    if ($mode !== 'mysql' || !$mysqlOk || $file === 'settings.json') {
        storage_json_upsert_direct($file, $row);
    }
    if ($file === 'settings.json') {
        storage_backend_mode_reset();
    }
    storage_invalidate_cache($file);
}

function storage_delete($file, $id) {
    $mode = storage_backend_mode();
    $mysqlOk = false;
    if (in_array($mode, array('dual', 'mysql'), true)) {
        $mysqlOk = storage_mysql_delete($file, $id);
    }
    if ($mode !== 'mysql' || !$mysqlOk) {
        storage_json_delete_direct($file, $id);
    }
    storage_invalidate_cache($file);
}


function publicista_account_defaults($id = '') {
    $id = trim((string)$id);
    if ($id === '') {
        $id = generate_id('pubacc');
    }

    return array(
        'id' => $id,
        'portal_code' => 'destacamos',
        'portal_label' => 'Destacamos',
        'portal_url' => '',
        'login_user' => '',
        'login_pass' => '',
        'display_name' => '',
        'descripcion' => '',
        'estado' => 'active',
        'automation_mode' => 'full_publish',
        'health_status' => 'ok',
        'priority_weight' => 100,
        'max_active_ads' => 0,
        'listing_slot_count' => 0,
        'portal_listing_ids' => array(),
        'daily_publish_limit' => 0,
        'created_ads_count' => 0,
        'active_ads_count' => 0,
        'published_ads_count' => 0,
        'free_bump_tasks_count' => 0,
        'free_bump_start_time' => '08:00',
        'free_bump_end_time' => '23:00',
        'free_bump_anticipation_minutes' => 8,
        'free_bump_interval_min_minutes' => 12,
        'free_bump_interval_max_minutes' => 120,
        'free_bump_retry_empty_min_minutes' => 10,
        'free_bump_retry_empty_max_minutes' => 22,
        'free_bump_jitter_min_seconds' => 30,
        'free_bump_jitter_max_seconds' => 180,
        'last_used_at' => '',
        'last_success_at' => '',
        'last_error_at' => '',
        'last_error' => '',
        'notes_internal' => '',
        'created_at' => '',
        'updated_at' => '',
    );
}

function publicista_account_portal_options() {
    return array(
        'destacamos' => 'Destacamos',
        'mundosex' => 'MundosexAnuncio',
        'otro' => 'Otro / manual',
    );
}

function publicista_account_status_options() {
    return array(
        'active' => 'Activa',
        'paused' => 'Pausada',
        'blocked' => 'Bloqueada',
        'review' => 'Revisar',
    );
}

function publicista_account_automation_options() {
    return array(
        'full_publish' => 'Publicación completa',
        'edit_only' => 'Solo edición',
        'free_bump_only' => 'Solo subir gratis',
        'manual' => 'Manual',
    );
}

function publicista_account_strip_runtime_fields($row) {
    $row = is_array($row) ? $row : array();
    foreach (array_keys($row) as $key) {
        $key = (string)$key;
        if ($key !== '' && $key[0] === '_') {
            unset($row[$key]);
        }
    }
    return $row;
}

function publicista_accounts_compact_storage_data($force = false) {
    $path = DATA_PATH . '/anuncios.json';
    if (!is_file($path)) {
        return array(
            'compacted' => false,
            'rows' => 0,
            'changed_rows' => 0,
            'bytes_before' => 0,
            'bytes_after' => 0,
        );
    }

    $bytesBefore = @filesize($path);
    $rows = storage_read('anuncios.json');
    $compacted = array();
    $changedRows = 0;

    foreach ((array)$rows as $row) {
        $original = is_array($row) ? $row : array();
        $sanitized = publicista_account_normalize(publicista_account_strip_runtime_fields($original));
        if (storage_json_encode($original, false) !== storage_json_encode($sanitized, false)) {
            $changedRows++;
        }
        $compacted[] = $sanitized;
    }

    if ($force || $changedRows > 0) {
        storage_write('anuncios.json', array_values($compacted));
    }

    clearstatcache(true, $path);
    $bytesAfter = @filesize($path);
    return array(
        'compacted' => ($force || $changedRows > 0),
        'rows' => count($compacted),
        'changed_rows' => $changedRows,
        'bytes_before' => (int)($bytesBefore !== false ? $bytesBefore : 0),
        'bytes_after' => (int)($bytesAfter !== false ? $bytesAfter : 0),
    );
}

function publicista_accounts_compact_storage_if_needed() {
    static $done = false;
    if ($done) {
        return null;
    }
    $done = true;

    $path = DATA_PATH . '/anuncios.json';
    if (!is_file($path)) {
        return null;
    }

    $size = @filesize($path);
    $needsCompaction = ($size !== false && (int)$size > 512 * 1024);
    if (!$needsCompaction) {
        $sample = @file_get_contents($path, false, null, 0, 65536);
        $needsCompaction = is_string($sample) && strpos($sample, '"_runtime_metrics"') !== false;
    }
    if (!$needsCompaction) {
        return null;
    }

    return publicista_accounts_compact_storage_data(true);
}

function publicista_campaign_compact_storage_if_needed() {
    static $done = false;
    if ($done) {
        return null;
    }
    $done = true;

    $targets = array(
        DATA_PATH . '/publicista_campaigns.json',
        DATA_PATH . '/publicista_campaign_items.json',
    );
    $needsCompaction = false;

    foreach ($targets as $path) {
        if (!is_file($path)) {
            continue;
        }
        $size = @filesize($path);
        if ($size !== false && (int)$size > 2 * 1024 * 1024) {
            $needsCompaction = true;
            break;
        }
        $sample = @file_get_contents($path, false, null, 0, 262144);
        if (!is_string($sample) || $sample === '') {
            continue;
        }
        if (
            strpos($sample, '"_runtime_metrics"') !== false ||
            strpos($sample, '"campaign_items"') !== false ||
            strpos($sample, '"tasks"') !== false ||
            strpos($sample, '"listing_assignments"') !== false
        ) {
            $needsCompaction = true;
            break;
        }
    }

    if (!$needsCompaction) {
        return null;
    }

    return publicista_campaign_compact_storage_data(false);
}


function publicista_account_health_options() {
    return array(
        'ok' => 'Correcta',
        'warning' => 'Con advertencias',
        'error' => 'Con incidencias',
    );
}

function publicista_account_health_label($value) {
    $options = publicista_account_health_options();
    $value = trim((string)$value);
    return isset($options[$value]) ? $options[$value] : 'Correcta';
}

function publicista_account_status_label($value) {
    $options = publicista_account_status_options();
    $value = trim((string)$value);
    return isset($options[$value]) ? $options[$value] : ($value !== '' ? $value : 'Activa');
}

function publicista_account_automation_label($value) {
    $options = publicista_account_automation_options();
    $value = trim((string)$value);
    return isset($options[$value]) ? $options[$value] : ($value !== '' ? $value : 'Manual');
}

function publicista_account_normalize($row) {
    $row = publicista_account_strip_runtime_fields($row);
    $id = trim((string)($row['id'] ?? ''));
    $base = publicista_account_defaults($id);

    if (($row['portal_url'] ?? '') === '' && !empty($row['url'])) {
        $row['portal_url'] = (string)$row['url'];
    }
    if (($row['login_user'] ?? '') === '' && !empty($row['user'])) {
        $row['login_user'] = (string)$row['user'];
    }
    if (($row['login_pass'] ?? '') === '' && !empty($row['pass'])) {
        $row['login_pass'] = (string)$row['pass'];
    }
    if (($row['display_name'] ?? '') === '') {
        $row['display_name'] = trim((string)($row['descripcion'] ?? ''));
    }

    $merged = array_merge($base, $row);
    $merged['estado'] = publicista_normalize_enum($merged['estado'] ?? 'active', publicista_account_status_options(), 'active');
    $merged['automation_mode'] = publicista_normalize_enum($merged['automation_mode'] ?? 'full_publish', publicista_account_automation_options(), 'manual');
    $merged['health_status'] = publicista_normalize_enum($merged['health_status'] ?? 'ok', publicista_account_health_options(), 'ok');
    $merged['priority_weight'] = max(0, (int)($merged['priority_weight'] ?? 100));
    $merged['max_active_ads'] = max(0, (int)($merged['max_active_ads'] ?? 0));
    if (!isset($merged['portal_listing_ids']) || !is_array($merged['portal_listing_ids'])) {
        $rawIds = isset($merged['portal_listing_ids']) ? (string)$merged['portal_listing_ids'] : (isset($merged['portal_listing_ids_raw']) ? (string)$merged['portal_listing_ids_raw'] : '');
        $parsed = preg_split('/\r\n|\r|\n|,|;/', $rawIds);
        $merged['portal_listing_ids'] = is_array($parsed) ? $parsed : array();
    }
    $listingIds = array();
    foreach ((array)$merged['portal_listing_ids'] as $listingId) {
        $listingId = trim((string)$listingId);
        if ($listingId !== '') $listingIds[$listingId] = $listingId;
    }
    $merged['portal_listing_ids'] = array_values($listingIds);
    $merged['listing_slot_count'] = count($merged['portal_listing_ids']);
    $merged['daily_publish_limit'] = max(0, (int)($merged['daily_publish_limit'] ?? 0));
    $merged['created_ads_count'] = max(0, (int)($merged['created_ads_count'] ?? 0));
    $merged['active_ads_count'] = max(0, (int)($merged['active_ads_count'] ?? 0));
    $merged['published_ads_count'] = max(0, (int)($merged['published_ads_count'] ?? 0));
    $merged['free_bump_tasks_count'] = max(0, (int)($merged['free_bump_tasks_count'] ?? 0));
    $merged['free_bump_start_time'] = publicista_free_bump_normalize_hhmm($merged['free_bump_start_time'] ?? '08:00', '08:00');
    $merged['free_bump_end_time'] = publicista_free_bump_normalize_hhmm($merged['free_bump_end_time'] ?? '23:00', '23:00');
    $merged['free_bump_anticipation_minutes'] = max(0, min(120, (int)($merged['free_bump_anticipation_minutes'] ?? 8)));
    $merged['free_bump_interval_min_minutes'] = max(1, min(240, (int)($merged['free_bump_interval_min_minutes'] ?? 12)));
    $merged['free_bump_interval_max_minutes'] = max($merged['free_bump_interval_min_minutes'], min(720, (int)($merged['free_bump_interval_max_minutes'] ?? 120)));
    $merged['free_bump_retry_empty_min_minutes'] = max(1, min(240, (int)($merged['free_bump_retry_empty_min_minutes'] ?? 10)));
    $merged['free_bump_retry_empty_max_minutes'] = max($merged['free_bump_retry_empty_min_minutes'], min(720, (int)($merged['free_bump_retry_empty_max_minutes'] ?? 22)));
    $merged['free_bump_jitter_min_seconds'] = max(0, min(1800, (int)($merged['free_bump_jitter_min_seconds'] ?? 30)));
    $merged['free_bump_jitter_max_seconds'] = max($merged['free_bump_jitter_min_seconds'], min(3600, (int)($merged['free_bump_jitter_max_seconds'] ?? 180)));
    if (trim((string)($merged['created_at'] ?? '')) === '') $merged['created_at'] = now_datetime();
    $merged['updated_at'] = trim((string)($merged['updated_at'] ?? '')) !== '' ? $merged['updated_at'] : $merged['created_at'];

    return $merged;
}

function publicista_accounts_get($withMetrics = false) {
    $rows = storage_read('anuncios.json');
    $out = array();
    $accountsById = array();
    foreach ($rows as $row) {
        $normalized = publicista_account_normalize($row);
        $out[] = $normalized;
        $id = trim((string)($normalized['id'] ?? ''));
        if ($id !== '') {
            $accountsById[$id] = $normalized;
        }
    }
    if ($withMetrics && !empty($accountsById)) {
        $metricsById = publicista_account_runtime_metrics_batch(array_keys($accountsById), $accountsById);
        foreach ($out as $index => $normalized) {
            $id = trim((string)($normalized['id'] ?? ''));
            if ($id !== '' && isset($metricsById[$id])) {
                $out[$index] = publicista_account_runtime_metrics_apply($normalized, $metricsById[$id]);
            }
        }
    }
    return $out;
}

function publicista_account_get($id, $withMetrics = false) {
    foreach (publicista_accounts_get($withMetrics) as $row) {
        if (($row['id'] ?? '') === $id) return $row;
    }
    return null;
}

function publicista_account_upsert($row) {
    storage_upsert('anuncios.json', publicista_account_normalize($row));
}

function publicista_account_delete($id) {
    storage_delete('anuncios.json', $id);
}

function publicista_entity_snapshot_defaults() {
    return array(
        'version' => 1,
        'source_type' => '',
        'source_id' => '',
        'created_at' => '',
        'data' => array(),
    );
}

function publicista_planning_defaults($id = '') {
    $id = trim((string)$id);
    if ($id === '') $id = generate_id('pubplan');
    return array(
        'id' => $id,
        'nombre' => '',
        'estado' => 'saved',
        'version' => 1,
        'parent_planning_id' => '',
        'portal_code' => '',
        'portal_label' => '',
        'portal_url' => '',
        'city' => '',
        'province' => '',
        'category' => '',
        'category_label' => '',
        'num_products_target' => 0,
        'competition_snapshot' => array(),
        'pricing_snapshot' => array(),
        'strategy_snapshot' => array(),
        'recommendation_options' => array(),
        'analysis_sources' => array(),
        'market_signals' => array(),
        'default_option_code' => 'recommended',
        'cost_snapshot' => array(),
        'selection_rules' => array(),
        'summary' => array(),
        'calculated_at' => '',
        'notes' => '',
        'created_at' => '',
        'updated_at' => '',
    );
}

function publicista_campaign_defaults($id = '') {
    $id = trim((string)$id);
    if ($id === '') $id = generate_id('pubcamp');
    return array(
        'id' => $id,
        'nombre' => '',
        'estado' => 'draft',
        'planning_id' => '',
        'planning_snapshot' => publicista_entity_snapshot_defaults(),
        'strategy_option_code' => 'recommended',
        'strategy_option_label' => '',
        'strategy_option_snapshot' => array(),
        'product_ids' => array(),
        'products_snapshot' => array(),
        'account_ids' => array(),
        'accounts_snapshot' => array(),
        'selected_listing_refs' => array(),
        'min_products' => 0,
        'max_products' => 0,
        'composition_plan' => array(),
        'automation_plan' => array(),
        'approval_snapshot' => array(),
        'recalculation_snapshot' => array(),
        'auto_rotation_schedule' => array(),
        'execution_summary' => array(),
        'notes' => '',
        'created_at' => '',
        'updated_at' => '',
    );
}

function publicista_campaign_auto_rotation_schedule_defaults() {
    return array(
        'enabled' => false,
        'daily_start_time' => '08:00',
        'daily_end_time' => '23:00',
        'every_hours' => 6,
        'run_immediately_once' => false,
        'last_run_at' => '',
        'next_run_at' => '',
        'status' => 'disabled',
        'last_error' => '',
        'updated_at' => '',
    );
}

function publicista_campaign_auto_rotation_schedule_normalize($row) {
    $row = is_array($row) ? $row : array();
    $base = publicista_campaign_auto_rotation_schedule_defaults();
    $merged = array_merge($base, $row);

    $enabledRaw = $merged['enabled'] ?? false;
    $merged['enabled'] = !empty($enabledRaw) && !in_array(strtolower(trim((string)$enabledRaw)), array('0', 'false', 'no', 'off'), true);

    $normalizeHhmm = function($value, $fallback) {
        $value = trim((string)$value);
        if (preg_match('/^(2[0-3]|[01]?\d):([0-5]\d)$/', $value)) {
            $parts = explode(':', $value, 2);
            return str_pad((string)((int)$parts[0]), 2, '0', STR_PAD_LEFT) . ':' . $parts[1];
        }
        return $fallback;
    };

    $merged['daily_start_time'] = $normalizeHhmm($merged['daily_start_time'] ?? '', $base['daily_start_time']);
    $merged['daily_end_time'] = $normalizeHhmm($merged['daily_end_time'] ?? '', $base['daily_end_time']);
    $legacyFrequency = (int)($merged['frequency_hours'] ?? 0);
    $merged['every_hours'] = max(1, (int)($merged['every_hours'] ?? ($legacyFrequency > 0 ? $legacyFrequency : $base['every_hours'])));
    $runNowRaw = $merged['run_immediately_once'] ?? false;
    $merged['run_immediately_once'] = !empty($runNowRaw) && !in_array(strtolower(trim((string)$runNowRaw)), array('0', 'false', 'no', 'off'), true);
    $merged['frequency_hours'] = $merged['every_hours'];
    $merged['last_run_at'] = trim((string)($merged['last_run_at'] ?? ''));
    $merged['next_run_at'] = trim((string)($merged['next_run_at'] ?? ''));
    $merged['status'] = trim((string)($merged['status'] ?? ($merged['enabled'] ? 'active' : 'disabled')));
    if ($merged['status'] === '') $merged['status'] = $merged['enabled'] ? 'active' : 'disabled';
    $merged['last_error'] = trim((string)($merged['last_error'] ?? ''));
    $merged['updated_at'] = trim((string)($merged['updated_at'] ?? ''));

    return $merged;
}

function publicista_campaign_item_defaults($id = '') {
    $id = trim((string)$id);
    if ($id === '') $id = generate_id('pubitem');
    return array(
        'id' => $id,
        'campaign_id' => '',
        'estado' => 'draft',
        'portal_code' => '',
        'product_job_id' => '',
        'product_snapshot' => publicista_entity_snapshot_defaults(),
        'account_id' => '',
        'account_snapshot' => publicista_entity_snapshot_defaults(),
        'copy_variant_id' => '',
        'copy_snapshot' => array(),
        'image_ids' => array(),
        'image_snapshot' => array(),
        'publish_mode' => '',
        'planning_profile_snapshot' => array(),
        'phone_id' => '',
        'external_ad_id' => '',
        'publish_result' => array(),
        'created_at' => '',
        'updated_at' => '',
    );
}

function publicista_task_defaults($id = '') {
    $id = trim((string)$id);
    if ($id === '') $id = generate_id('pubtask');
    return array(
        'id' => $id,
        'campaign_id' => '',
        'campaign_item_id' => '',
        'account_id' => '',
        'portal_code' => '',
        'task_type' => 'free_bump',
        'estado' => 'pending',
        'frequency_rule' => '',
        'next_run_at' => '',
        'last_run_at' => '',
        'last_result' => array(),
        'fail_count' => 0,
        'created_at' => '',
        'updated_at' => '',
    );
}

function publicista_run_defaults($id = '') {
    $id = trim((string)$id);
    if ($id === '') $id = generate_id('pubrun');
    return array(
        'id' => $id,
        'campaign_id' => '',
        'run_type' => '',
        'estado' => 'pending',
        'summary' => '',
        'progress' => array(
            'total_items' => 0,
            'processed_items' => 0,
            'published' => 0,
            'failed' => 0,
            'current_item_id' => '',
            'current_listing_id' => '',
            'current_account_id' => '',
        ),
        'pipeline' => array(
            'status' => 'pending',
            'stage' => 'queued',
            'summary' => '',
        ),
        'started_at' => '',
        'finished_at' => '',
        'stop_requested_at' => '',
        'stop_requested_by' => '',
        'stop_acknowledged_at' => '',
        'items' => array(),
        'created_at' => '',
        'updated_at' => '',
    );
}


function publicista_product_defaults($id = '') {
    return publicista_job_defaults($id);
}

function publicista_products_get() {
    return publicista_jobs_get();
}

function publicista_product_get($id) {
    return publicista_job_get($id);
}

function publicista_product_save($row) {
    return publicista_job_save($row);
}

function publicista_product_delete($id) {
    return publicista_job_delete($id);
}

function publicista_snapshot_from_entity($sourceType, $row) {
    $row = is_array($row) ? $row : array();
    $snapshot = publicista_entity_snapshot_defaults();
    $snapshot['source_type'] = trim((string)$sourceType);
    $snapshot['source_id'] = trim((string)($row['id'] ?? ''));
    $snapshot['created_at'] = now_datetime();
    $snapshot['data'] = publicista_snapshot_entity_data($sourceType, $row);
    return $snapshot;
}

function publicista_snapshot_entity_data($sourceType, $row) {
    $sourceType = trim((string)$sourceType);
    $row = is_array($row) ? $row : array();

    if ($sourceType === 'planning') {
        $planning = publicista_planning_normalize($row);
        return array(
            'id' => trim((string)($planning['id'] ?? '')),
            'nombre' => trim((string)($planning['nombre'] ?? '')),
            'version' => max(1, (int)($planning['version'] ?? 1)),
            'portal_code' => trim((string)($planning['portal_code'] ?? '')),
            'portal_label' => trim((string)($planning['portal_label'] ?? '')),
            'portal_url' => trim((string)($planning['portal_url'] ?? '')),
            'city' => trim((string)($planning['city'] ?? '')),
            'province' => trim((string)($planning['province'] ?? '')),
            'category' => trim((string)($planning['category'] ?? '')),
            'category_label' => trim((string)($planning['category_label'] ?? '')),
            'num_products_target' => max(0, (int)($planning['num_products_target'] ?? 0)),
            'summary' => is_array($planning['summary'] ?? null) ? $planning['summary'] : array(),
            'cost_snapshot' => is_array($planning['cost_snapshot'] ?? null) ? array(
                'grand_total' => (float)($planning['cost_snapshot']['grand_total'] ?? 0),
                'days' => array_values((array)($planning['cost_snapshot']['days'] ?? array())),
            ) : array(),
        );
    }

    if ($sourceType === 'product') {
        $product = publicista_product_defaults();
        if (!empty($row)) {
            $product = array_merge($product, $row);
        }
        $copyPack = function_exists('publicista_job_copy_pack') ? publicista_job_copy_pack($product) : (is_array($product['copy_pack'] ?? null) ? $product['copy_pack'] : array());
        $finalImages = is_array($product['final_images'] ?? null) ? $product['final_images'] : array();
        return array(
            'id' => trim((string)($product['id'] ?? '')),
            'clienta_id' => trim((string)($product['clienta_id'] ?? '')),
            'clienta_scope' => trim((string)($product['clienta_scope'] ?? '')),
            'clienta_nombre_snapshot' => trim((string)($product['clienta_nombre_snapshot'] ?? '')),
            'nombre_trabajo' => trim((string)($product['nombre_trabajo'] ?? '')),
            'estado' => trim((string)($product['estado'] ?? '')),
            'localidad_snapshot' => trim((string)($product['localidad_snapshot'] ?? '')),
            'provincia_snapshot' => trim((string)($product['provincia_snapshot'] ?? '')),
            'final_images_count' => count($finalImages),
            'copy_versions_count' => is_array($copyPack['versions'] ?? null) ? count($copyPack['versions']) : 0,
        );
    }

    if ($sourceType === 'account') {
        $account = publicista_account_normalize($row);
        return array(
            'id' => trim((string)($account['id'] ?? '')),
            'portal_code' => trim((string)($account['portal_code'] ?? '')),
            'portal_label' => trim((string)($account['portal_label'] ?? '')),
            'login_user' => trim((string)($account['login_user'] ?? '')),
            'display_name' => trim((string)($account['display_name'] ?? '')),
            'estado' => trim((string)($account['estado'] ?? '')),
            'automation_mode' => trim((string)($account['automation_mode'] ?? '')),
            'health_status' => trim((string)($account['health_status'] ?? '')),
            'portal_listing_ids' => array_values((array)($account['portal_listing_ids'] ?? array())),
            'listing_slot_count' => count((array)($account['portal_listing_ids'] ?? array())),
        );
    }

    $fallback = array();
    foreach (array('id', 'nombre', 'estado', 'updated_at', 'created_at') as $key) {
        if (array_key_exists($key, $row)) {
            $fallback[$key] = $row[$key];
        }
    }
    return $fallback;
}

function publicista_planning_status_options() {
    return array(
        'saved' => 'Guardada',
    );
}

function publicista_campaign_status_options() {
    return array(
        'draft' => 'Borrador',
        'generating' => 'Generando',
        'generated' => 'Generada',
        'approved' => 'Aprobada',
        'uploading' => 'Subiendo',
        'completed' => 'Completada',
        'paused' => 'Pausada',
        'cancelled' => 'Cancelada',
        'error' => 'Error',
    );
}

function publicista_campaign_item_status_options() {
    return array(
        'draft' => 'Borrador',
        'ready' => 'Listo',
        'queued' => 'En cola',
        'published' => 'Publicado',
        'failed' => 'Fallido',
        'cancelled' => 'Cancelado',
    );
}

function publicista_task_status_options() {
    return array(
        'pending' => 'Pendiente',
        'active' => 'Activa',
        'paused' => 'Pausada',
        'done' => 'Completada',
        'error' => 'Error',
    );
}

function publicista_run_status_options() {
    return array(
        'pending' => 'Pendiente',
        'running' => 'Ejecutando',
        'completed' => 'Completado',
        'failed' => 'Fallido',
        'cancelled' => 'Cancelado',
    );
}

function publicista_normalize_enum($value, $options, $fallback) {
    $value = trim((string)$value);
    return isset($options[$value]) ? $value : $fallback;
}

function publicista_planning_normalize($row) {
    $row = is_array($row) ? $row : array();
    $id = trim((string)($row['id'] ?? ''));
    $base = publicista_planning_defaults($id);
    $merged = array_merge($base, $row);
    $merged['estado'] = 'saved';
    $merged['version'] = max(1, (int)($merged['version'] ?? 1));
    $merged['num_products_target'] = max(0, (int)($merged['num_products_target'] ?? 0));
    $merged['default_option_code'] = trim((string)($merged['default_option_code'] ?? 'recommended')) !== '' ? trim((string)$merged['default_option_code']) : 'recommended';
    foreach (array('competition_snapshot','pricing_snapshot','strategy_snapshot','recommendation_options','analysis_sources','market_signals','cost_snapshot','selection_rules','summary') as $k) {
        if (!isset($merged[$k]) || !is_array($merged[$k])) $merged[$k] = array();
    }
    if (trim((string)($merged['calculated_at'] ?? '')) === '') $merged['calculated_at'] = trim((string)($merged['updated_at'] ?? ''));
    if (trim((string)($merged['created_at'] ?? '')) === '') $merged['created_at'] = now_datetime();
    $merged['updated_at'] = trim((string)($merged['updated_at'] ?? '')) !== '' ? $merged['updated_at'] : $merged['created_at'];
    return $merged;
}

function publicista_campaign_normalize($row) {
    $row = is_array($row) ? $row : array();
    $id = trim((string)($row['id'] ?? ''));
    $base = publicista_campaign_defaults($id);
    $merged = array_merge($base, $row);
    $merged['estado'] = publicista_normalize_enum($merged['estado'] ?? 'draft', publicista_campaign_status_options(), 'draft');
    foreach (array('product_ids','account_ids','selected_listing_refs','products_snapshot','accounts_snapshot') as $k) {
        if (!isset($merged[$k]) || !is_array($merged[$k])) $merged[$k] = array();
    }
    foreach (array('planning_snapshot','composition_plan','automation_plan','approval_snapshot','recalculation_snapshot','execution_summary','strategy_option_snapshot','auto_rotation_schedule') as $k) {
        if (!isset($merged[$k]) || !is_array($merged[$k])) $merged[$k] = array();
    }
    $merged['strategy_option_code'] = trim((string)($merged['strategy_option_code'] ?? 'recommended')) !== '' ? trim((string)$merged['strategy_option_code']) : 'recommended';
    $merged['strategy_option_label'] = trim((string)($merged['strategy_option_label'] ?? ''));
    $merged['automation_plan'] = publicista_campaign_compact_automation_plan($merged['automation_plan'] ?? array());
    $merged['auto_rotation_schedule'] = publicista_campaign_auto_rotation_schedule_normalize($merged['auto_rotation_schedule'] ?? array());
    $merged['min_products'] = max(0, (int)($merged['min_products'] ?? 0));
    $merged['max_products'] = max(0, (int)($merged['max_products'] ?? 0));
    if ($merged['max_products'] > 0 && $merged['min_products'] > $merged['max_products']) {
        $merged['min_products'] = $merged['max_products'];
    }
    if (trim((string)($merged['created_at'] ?? '')) === '') $merged['created_at'] = now_datetime();
    $merged['updated_at'] = trim((string)($merged['updated_at'] ?? '')) !== '' ? $merged['updated_at'] : $merged['created_at'];
    return $merged;
}

function publicista_campaign_listing_ref($accountId, $listingId) {
    $accountId = trim((string)$accountId);
    $listingId = trim((string)$listingId);
    return $accountId !== '' && $listingId !== '' ? ($accountId . '::' . $listingId) : '';
}

function publicista_campaign_parse_listing_ref($ref) {
    $ref = trim((string)$ref);
    $parts = explode('::', $ref, 2);
    return array(
        'account_id' => trim((string)($parts[0] ?? '')),
        'listing_id' => trim((string)($parts[1] ?? '')),
        'ref' => $ref,
    );
}

function publicista_campaign_item_normalize($row) {
    $row = is_array($row) ? $row : array();
    $id = trim((string)($row['id'] ?? ''));
    $base = publicista_campaign_item_defaults($id);
    $merged = array_merge($base, $row);
    $merged['estado'] = publicista_normalize_enum($merged['estado'] ?? 'draft', publicista_campaign_item_status_options(), 'draft');
    foreach (array('product_snapshot','account_snapshot') as $k) {
        if (!isset($merged[$k]) || !is_array($merged[$k])) $merged[$k] = publicista_entity_snapshot_defaults();
        else $merged[$k] = array_merge(publicista_entity_snapshot_defaults(), $merged[$k]);
    }
    foreach (array('copy_snapshot','image_snapshot','publish_result','planning_profile_snapshot') as $k) {
        if (!isset($merged[$k]) || !is_array($merged[$k])) $merged[$k] = array();
    }
    $merged['copy_snapshot'] = publicista_campaign_compact_copy_snapshot($merged['copy_snapshot']);
    $merged['image_snapshot'] = publicista_campaign_compact_image_snapshot($merged['image_snapshot']);
    $merged['planning_profile_snapshot'] = publicista_campaign_compact_planning_profile_snapshot($merged['planning_profile_snapshot']);
    if (!isset($merged['image_ids']) || !is_array($merged['image_ids'])) $merged['image_ids'] = array();
    if (trim((string)($merged['created_at'] ?? '')) === '') $merged['created_at'] = now_datetime();
    $merged['updated_at'] = trim((string)($merged['updated_at'] ?? '')) !== '' ? $merged['updated_at'] : $merged['created_at'];
    return $merged;
}

function publicista_campaign_compact_copy_snapshot($copy) {
    $copy = is_array($copy) ? $copy : array();
    return array(
        'variant_id' => trim((string)($copy['variant_id'] ?? '')),
        'version_id' => trim((string)($copy['version_id'] ?? '')),
        'slot' => trim((string)($copy['slot'] ?? '')),
        'focus' => publicista_campaign_trim_text_limit(trim((string)($copy['focus'] ?? '')), 300),
        'short_hook' => publicista_campaign_trim_text_limit(trim((string)($copy['short_hook'] ?? '')), 300),
        'title_neutral' => publicista_campaign_trim_text_limit(trim((string)($copy['title_neutral'] ?? '')), 120),
        'title_suggestive' => publicista_campaign_trim_text_limit(trim((string)($copy['title_suggestive'] ?? '')), 120),
        'body_neutral' => publicista_campaign_trim_text_limit(trim((string)($copy['body_neutral'] ?? '')), 2000),
        'body_suggestive' => publicista_campaign_trim_text_limit(trim((string)($copy['body_suggestive'] ?? '')), 2000),
    );
}

function publicista_campaign_compact_image_snapshot($images) {
    $images = array_values((array)$images);
    $out = array();
    foreach ($images as $img) {
        if (!is_array($img)) continue;
        $out[] = array(
            'id' => trim((string)($img['id'] ?? '')),
            'filename' => trim((string)($img['filename'] ?? '')),
            'path_rel' => trim((string)($img['path_rel'] ?? '')),
            'final_path' => trim((string)($img['final_path'] ?? '')),
            'square_path' => trim((string)($img['square_path'] ?? '')),
            'preview_path' => trim((string)($img['preview_path'] ?? '')),
        );
    }
    return $out;
}

function publicista_campaign_compact_planning_profile_snapshot($profile) {
    $profile = is_array($profile) ? $profile : array();
    $opts = is_array($profile['opts'] ?? null) ? $profile['opts'] : array();
    return array(
        'girl' => (int)($profile['girl'] ?? 0),
        'num' => (int)($profile['num'] ?? 0),
        'name' => publicista_campaign_trim_text_limit(trim((string)($profile['name'] ?? '')), 120),
        'opts' => $opts,
        'why' => publicista_campaign_trim_text_limit(trim((string)($profile['why'] ?? '')), 300),
        'cost' => (float)($profile['cost'] ?? 0),
        'free_slots' => array_values((array)($profile['free_slots'] ?? array())),
    );
}

function publicista_task_normalize($row) {
    $row = is_array($row) ? $row : array();
    $id = trim((string)($row['id'] ?? ''));
    $base = publicista_task_defaults($id);
    $merged = array_merge($base, $row);
    $merged['estado'] = publicista_normalize_enum($merged['estado'] ?? 'pending', publicista_task_status_options(), 'pending');
    $merged['fail_count'] = max(0, (int)($merged['fail_count'] ?? 0));
    if (!isset($merged['last_result']) || !is_array($merged['last_result'])) $merged['last_result'] = array();
    if (trim((string)($merged['created_at'] ?? '')) === '') $merged['created_at'] = now_datetime();
    $merged['updated_at'] = trim((string)($merged['updated_at'] ?? '')) !== '' ? $merged['updated_at'] : $merged['created_at'];
    return $merged;
}

function publicista_run_normalize($row) {
    $row = is_array($row) ? $row : array();
    $id = trim((string)($row['id'] ?? ''));
    $base = publicista_run_defaults($id);
    $merged = array_merge($base, $row);
    $merged['estado'] = publicista_normalize_enum($merged['estado'] ?? 'pending', publicista_run_status_options(), 'pending');
    if (!isset($merged['items']) || !is_array($merged['items'])) $merged['items'] = array();
    if (!isset($merged['progress']) || !is_array($merged['progress'])) $merged['progress'] = $base['progress'];
    else $merged['progress'] = array_merge($base['progress'], $merged['progress']);
    if (!isset($merged['pipeline']) || !is_array($merged['pipeline'])) $merged['pipeline'] = $base['pipeline'];
    else $merged['pipeline'] = array_merge($base['pipeline'], $merged['pipeline']);
    $merged['stop_requested_at'] = trim((string)($merged['stop_requested_at'] ?? ''));
    $merged['stop_requested_by'] = trim((string)($merged['stop_requested_by'] ?? ''));
    $merged['stop_acknowledged_at'] = trim((string)($merged['stop_acknowledged_at'] ?? ''));
    $merged['human_report'] = trim((string)($merged['human_report'] ?? ''));
    if (trim((string)($merged['created_at'] ?? '')) === '') $merged['created_at'] = now_datetime();
    $merged['updated_at'] = trim((string)($merged['updated_at'] ?? '')) !== '' ? $merged['updated_at'] : $merged['created_at'];
    return $merged;
}

function publicista_plannings_get() {
    $rows = storage_read('publicista_plannings.json');
    $out = array();
    foreach ($rows as $row) {
        $out[] = publicista_planning_normalize($row);
    }
    return sort_desc_by_key($out, 'updated_at');
}

function publicista_planning_get($id) {
    foreach (publicista_plannings_get() as $row) {
        if (($row['id'] ?? '') === $id) return $row;
    }
    return null;
}

function publicista_planning_save($row) {
    $normalized = publicista_planning_normalize($row);
    $normalized['updated_at'] = now_datetime();
    storage_upsert('publicista_plannings.json', $normalized);
    return array(true, $normalized);
}

function publicista_planning_delete($id) {
    storage_delete('publicista_plannings.json', $id);
}

function publicista_planning_status_label($value) {
    $options = publicista_planning_status_options();
    $value = trim((string)$value);
    return 'Guardada';
}

function publicista_planning_compose_name($city, $province, $categoryLabel, $numProducts) {
    $parts = array();
    if (trim((string)$city) !== '') $parts[] = trim((string)$city);
    if (trim((string)$province) !== '') $parts[] = trim((string)$province);
    $left = implode(' · ', $parts);
    $right = trim((string)$categoryLabel);
    if ($right === '') $right = 'Todas las categorías';
    $suffix = max(0, (int)$numProducts) . ' productos';
    return trim(($left !== '' ? $left . ' · ' : '') . $right . ' · ' . $suffix);
}

function publicista_planning_build_summary($planning) {
    $planning = publicista_planning_normalize($planning);
    $comp = is_array($planning['competition_snapshot']) ? $planning['competition_snapshot'] : array();
    $strategies = is_array($planning['strategy_snapshot']) ? $planning['strategy_snapshot'] : array();
    $summary = array(
        'profiles_total' => 0,
        'premium_profiles' => 0,
        'top_profiles' => 0,
        'auto_profiles' => 0,
        'combo_top_auto_profiles' => 0,
        'free_profiles' => 0,
        'free_slots' => 0,
        'warnings_count' => 0,
        'girls_count' => max(0, (int)($planning['num_products_target'] ?? 0)),
        'competition_level' => trim((string)($comp['level'] ?? '')),
    );
    foreach ($strategies as $strategy) {
        $profiles = is_array($strategy['profiles'] ?? null) ? $strategy['profiles'] : array();
        foreach ($profiles as $profile) {
            $summary['profiles_total']++;
            $opts = is_array($profile['opts'] ?? null) ? $profile['opts'] : array();
            $hasPremium = !empty($opts['PREMIUM']) || !empty($opts['premium']);
            $hasTop = !empty($opts['TOP']) || !empty($opts['top']);
            $hasAuto = !empty($opts['auto7']) || !empty($opts['auto4']);
            if ($hasPremium) $summary['premium_profiles']++;
            if ($hasTop) $summary['top_profiles']++;
            if ($hasAuto) $summary['auto_profiles']++;
            if ($hasTop && $hasAuto) $summary['combo_top_auto_profiles']++;
            if (!empty($opts['free']) && is_array($opts['free'])) {
                $summary['free_profiles']++;
                $summary['free_slots'] += count($opts['free']);
            }
        }
        $warnings = is_array($strategy['overlapWarnings'] ?? null) ? $strategy['overlapWarnings'] : array();
        $summary['warnings_count'] += count($warnings);
    }
    return $summary;
}

function publicista_planning_duplicate_from_existing($sourceId) {
    $source = publicista_planning_get($sourceId);
    if (!$source) return array(false, 'No se encontró el planning a duplicar.');
    $copy = $source;
    $copy['id'] = generate_id('pubplan');
    $copy['parent_planning_id'] = trim((string)($source['parent_planning_id'] ?? '')) !== '' ? trim((string)$source['parent_planning_id']) : trim((string)$source['id']);
    $copy['version'] = max(1, (int)($source['version'] ?? 1)) + 1;
$copy['estado'] = 'saved';
    $copy['nombre'] = trim((string)($source['nombre'] ?? '')) . ' · v' . $copy['version'];
    $copy['created_at'] = now_datetime();
    $copy['updated_at'] = $copy['created_at'];
    $copy['calculated_at'] = $copy['created_at'];
    return publicista_planning_save($copy);
}

function publicista_campaigns_get() {
    $rows = storage_read('publicista_campaigns.json');
    $out = array();
    foreach ($rows as $row) {
        $out[] = publicista_campaign_normalize($row);
    }
    return sort_desc_by_key($out, 'updated_at');
}

function publicista_campaign_get($id) {
    foreach (publicista_campaigns_get() as $row) {
        if (($row['id'] ?? '') === $id) return $row;
    }
    return null;
}

function publicista_campaign_save($row) {
    $normalized = publicista_campaign_normalize($row);
    $normalized['updated_at'] = now_datetime();
    storage_upsert('publicista_campaigns.json', $normalized);
    return array(true, $normalized);
}

function publicista_campaign_auto_rotation_is_in_window($schedule, $refTs = null) {
    $schedule = publicista_campaign_auto_rotation_schedule_normalize($schedule);
    $refTs = $refTs ?: time();
    $day = date('Y-m-d', $refTs);
    $startTs = strtotime($day . ' ' . $schedule['daily_start_time'] . ':00');
    $endTs = strtotime($day . ' ' . $schedule['daily_end_time'] . ':00');
    if ($startTs === false || $endTs === false) return false;
    if ($startTs <= $endTs) {
        return $refTs >= $startTs && $refTs <= $endTs;
    }
    return $refTs >= $startTs || $refTs <= $endTs;
}

function publicista_campaign_auto_rotation_next_window_start_ts($schedule, $refTs = null) {
    $schedule = publicista_campaign_auto_rotation_schedule_normalize($schedule);
    $refTs = $refTs ?: time();
    $today = date('Y-m-d', $refTs);
    $startToday = strtotime($today . ' ' . $schedule['daily_start_time'] . ':00');
    if ($startToday === false) return $refTs;
    if ($refTs <= $startToday) return $startToday;
    return strtotime('+1 day', $startToday);
}

function publicista_campaign_auto_rotation_next_due_ts($schedule, $fromTs = null) {
    $schedule = publicista_campaign_auto_rotation_schedule_normalize($schedule);
    $fromTs = $fromTs ?: time();
    $candidateTs = $fromTs + (max(1, (int)$schedule['every_hours']) * 3600);
    if (publicista_campaign_auto_rotation_is_in_window($schedule, $candidateTs)) {
        return $candidateTs;
    }
    return publicista_campaign_auto_rotation_next_window_start_ts($schedule, $candidateTs);
}

function publicista_campaign_auto_rotation_run_due($options = array()) {
    $options = is_array($options) ? $options : array();
    $nowTs = time();
    $nowDt = now_datetime();

    $activeCampaign = null;
    foreach (publicista_campaigns_get() as $campaign) {
        $schedule = publicista_campaign_auto_rotation_schedule_normalize((array)($campaign['auto_rotation_schedule'] ?? array()));
        if (!empty($schedule['enabled'])) {
            $activeCampaign = $campaign;
            break;
        }
    }
    if (!$activeCampaign) {
        return array('status' => 'no_active_schedule', 'next_run_at' => '');
    }

    $campaignId = trim((string)($activeCampaign['id'] ?? ''));
    publicista_campaign_recover_stuck_run($campaignId, array('stale_seconds' => 900));
    $activeCampaign = publicista_campaign_get($campaignId) ?: $activeCampaign;
    $schedule = publicista_campaign_auto_rotation_schedule_normalize((array)($activeCampaign['auto_rotation_schedule'] ?? array()));
    if (trim((string)$schedule['next_run_at']) === '') {
        $schedule['next_run_at'] = $nowDt;
    }

    $campaignStatus = trim((string)($activeCampaign['estado'] ?? ''));
    if ($campaignStatus === 'uploading' || publicista_campaign_running_run($campaignId)) {
        $schedule['status'] = 'running';
        $activeCampaign['auto_rotation_schedule'] = $schedule;
        $activeCampaign['execution_summary'] = array_merge((array)($activeCampaign['execution_summary'] ?? array()), array(
            'auto_rotation_status' => 'running',
            'auto_rotation_next_run_at' => trim((string)$schedule['next_run_at']),
            'auto_rotation_last_status' => 'Campaña en ejecución, se omite auto-disparo.',
        ));
        publicista_campaign_save($activeCampaign);
        return array('status' => 'already_running', 'campaign_id' => $campaignId, 'next_run_at' => trim((string)$schedule['next_run_at']));
    }

    $forceImmediate = !empty($schedule['run_immediately_once']);

    $nextTs = strtotime((string)$schedule['next_run_at']);
    if ($nextTs === false) $nextTs = $nowTs;
    if (!$forceImmediate && $nextTs > $nowTs) {
        return array('status' => 'not_due', 'campaign_id' => $campaignId, 'next_run_at' => trim((string)$schedule['next_run_at']));
    }

    if (!$forceImmediate && !publicista_campaign_auto_rotation_is_in_window($schedule, $nowTs)) {
        $nextWindowTs = publicista_campaign_auto_rotation_next_window_start_ts($schedule, $nowTs);
        $schedule['status'] = 'outside_window';
        $schedule['next_run_at'] = date('Y-m-d H:i:s', $nextWindowTs);
        $activeCampaign['auto_rotation_schedule'] = $schedule;
        $activeCampaign['execution_summary'] = array_merge((array)($activeCampaign['execution_summary'] ?? array()), array(
            'auto_rotation_status' => 'outside_window',
            'auto_rotation_next_run_at' => $schedule['next_run_at'],
            'auto_rotation_last_status' => 'Fuera de ventana diaria.',
        ));
        publicista_campaign_save($activeCampaign);
        return array('status' => 'outside_window', 'campaign_id' => $campaignId, 'next_run_at' => $schedule['next_run_at']);
    }

    list($okDispatch, $savedCampaign, $meta) = publicista_campaign_dispatch_async($campaignId);
    $targetCampaign = $savedCampaign ?: $activeCampaign;
    $targetCampaign = publicista_campaign_normalize($targetCampaign);
    $targetSchedule = publicista_campaign_auto_rotation_schedule_normalize((array)($targetCampaign['auto_rotation_schedule'] ?? array()));
    $targetSchedule['run_immediately_once'] = false;
    $targetSchedule['last_run_at'] = $nowDt;
    $nextDueTs = publicista_campaign_auto_rotation_next_due_ts($targetSchedule, $nowTs);
    $targetSchedule['next_run_at'] = date('Y-m-d H:i:s', $nextDueTs);
    $targetSchedule['status'] = $okDispatch ? 'dispatched' : 'error';
    $targetSchedule['last_error'] = $okDispatch ? '' : trim((string)($meta['error'] ?? 'No se pudo lanzar auto-rotación.'));
    $targetSchedule['updated_at'] = $nowDt;
    $targetCampaign['auto_rotation_schedule'] = $targetSchedule;
    $targetCampaign['execution_summary'] = array_merge((array)($targetCampaign['execution_summary'] ?? array()), array(
        'auto_rotation_status' => $targetSchedule['status'],
        'auto_rotation_next_run_at' => $targetSchedule['next_run_at'],
        'auto_rotation_last_run_at' => $targetSchedule['last_run_at'],
        'auto_rotation_last_status' => $okDispatch ? 'Auto-disparo lanzado.' : 'Error al lanzar auto-disparo.',
        'auto_rotation_last_error' => $targetSchedule['last_error'],
    ));
    publicista_campaign_save($targetCampaign);

    // En cron no hay redirección asíncrona como en la acción web:
    // lanzamos la ejecución real del run inmediatamente.
    if ($okDispatch) {
        $runId = trim((string)($meta['run_id'] ?? ''));
        try {
            list($okRun, $finalCampaign, $run, $runMeta) = publicista_campaign_execute($campaignId, array(
                'run_id' => $runId,
                'auto_rotation' => true,
            ));
            $notifyCampaign = $finalCampaign ?: (publicista_campaign_get($campaignId) ?: $targetCampaign);
            $notifyRun = $run ?: ($runId !== '' ? publicista_run_get($runId) : array());
            publicista_campaign_notify_execution_finished($notifyCampaign, $notifyRun, $runMeta, $okRun);
        } catch (Throwable $e) {
            $failedCampaign = publicista_campaign_get($campaignId) ?: $targetCampaign;
            if ($failedCampaign) {
                $failedCampaign['estado'] = 'error';
                $failedCampaign['updated_at'] = now_datetime();
                $failedCampaign['execution_summary'] = array_merge((array)($failedCampaign['execution_summary'] ?? array()), array(
                    'last_phase' => 'error',
                    'last_run_id' => $runId,
                    'last_run_status' => 'failed',
                    'last_run_error' => $e->getMessage(),
                    'last_upload_finished_at' => now_datetime(),
                    'auto_rotation_last_error' => $e->getMessage(),
                ));
                publicista_campaign_save($failedCampaign);
            }
        }
    }

    return array(
        'status' => $targetSchedule['status'],
        'campaign_id' => $campaignId,
        'run_id' => trim((string)($meta['run_id'] ?? '')),
        'next_run_at' => $targetSchedule['next_run_at'],
        'error' => $targetSchedule['last_error'],
    );
}

function publicista_campaign_force_auto_rotation_now($campaignId, $options = array()) {
    $campaignId = trim((string)$campaignId);
    $campaign = $campaignId !== '' ? publicista_campaign_get($campaignId) : null;
    publicista_campaign_recover_stuck_run($campaignId, array('stale_seconds' => 900));
    $campaign = $campaignId !== '' ? publicista_campaign_get($campaignId) : $campaign;
    if (!$campaign) {
        return array(false, array('error' => 'No se encontró la campaña.'));
    }

    $items = publicista_campaign_items_for_campaign($campaignId);
    if (empty($items)) {
        return array(false, array('error' => 'La campaña no tiene items generados.'));
    }

    if (publicista_campaign_running_run($campaignId) || trim((string)($campaign['estado'] ?? '')) === 'uploading') {
        return array(false, array('error' => 'Ya hay una subida en curso para esta campaña.'));
    }

    list($okDispatch, $savedCampaign, $meta) = publicista_campaign_dispatch_async($campaignId);
    if (!$okDispatch) {
        return array(false, array('error' => trim((string)($meta['error'] ?? 'No se pudo lanzar la subida.'))));
    }

    $runId = trim((string)($meta['run_id'] ?? ''));
    try {
        list($okRun, $finalCampaign, $run, $runMeta) = publicista_campaign_execute($campaignId, array(
            'run_id' => $runId,
            'auto_rotation' => true,
        ));
        $notifyCampaign = $finalCampaign ?: (publicista_campaign_get($campaignId) ?: $campaign);
        $notifyRun = $run ?: ($runId !== '' ? publicista_run_get($runId) : array());
        publicista_campaign_notify_execution_finished($notifyCampaign, $notifyRun, $runMeta, $okRun);

        return array($okRun, array(
            'run_id' => $runId,
            'published' => (int)($runMeta['published'] ?? 0),
            'failed' => (int)($runMeta['failed'] ?? 0),
            'stopped' => !empty($runMeta['stopped']),
            'error' => $okRun ? '' : trim((string)($runMeta['error'] ?? 'La ejecución terminó con incidencias.')),
        ));
    } catch (Throwable $e) {
        return array(false, array('run_id' => $runId, 'error' => 'Error forzando la subida: ' . $e->getMessage()));
    }
}

function publicista_campaign_delete($id) {
    storage_delete('publicista_campaigns.json', $id);
}


function publicista_campaign_status_label($value) {
    $options = publicista_campaign_status_options();
    $value = trim((string)$value);
    return isset($options[$value]) ? $options[$value] : 'Borrador';
}

function publicista_campaign_item_status_label($value) {
    $options = publicista_campaign_item_status_options();
    $value = trim((string)$value);
    return isset($options[$value]) ? $options[$value] : 'Borrador';
}

function publicista_campaign_can_delete($id) {
    $items = publicista_campaign_items_for_campaign($id);
    $tasks = publicista_tasks_for_campaign($id);
    $runs = array();
    foreach (publicista_runs_get() as $run) {
        if (($run['campaign_id'] ?? '') === $id) $runs[] = $run;
    }
    $errors = array();
    if (!empty($tasks)) $errors[] = 'Tiene tareas automáticas vinculadas.';
    if (!empty($runs)) $errors[] = 'Tiene ejecuciones históricas vinculadas.';
    return array(empty($errors), $errors, array('items_count' => count($items), 'tasks_count' => count($tasks), 'runs_count' => count($runs)));
}

function publicista_campaign_delete_with_items($id) {
    foreach (publicista_campaign_items_for_campaign($id) as $item) {
        publicista_campaign_item_delete($item['id'] ?? '');
    }
    publicista_campaign_delete($id);
}

function publicista_campaign_compose_name($planning, $selectedProductsCount = 0) {
    $planning = publicista_planning_normalize($planning);
    $name = trim((string)($planning['nombre'] ?? ''));
    if ($name === '') {
        $name = publicista_planning_compose_name($planning['city'] ?? '', $planning['province'] ?? '', $planning['category_label'] ?? '', (int)($planning['num_products_target'] ?? 0));
    }
    $suffix = max(0, (int)$selectedProductsCount) > 0 ? ' · campaña ' . max(0, (int)$selectedProductsCount) . ' productos' : ' · campaña';
    return trim($name . $suffix);
}

function publicista_product_is_ready_for_campaign($product) {
    $product = is_array($product) ? $product : array();
    $productId = trim((string)($product['id'] ?? ''));
    if ($productId === '') {
        $product = publicista_product_defaults();
    } else {
        $product = array_merge(publicista_product_defaults($productId), $product);
    }
    $copy = function_exists('publicista_job_copy_pack') ? publicista_job_copy_pack($product) : (is_array($product['copy_pack'] ?? null) ? $product['copy_pack'] : array());
    $hasCopy = trim((string)($copy['current_version_id'] ?? '')) !== '' || !empty($copy['versions']);
    $finals = is_array($product['final_images'] ?? null) ? $product['final_images'] : array();
    $hasImages = count($finals) > 0;
    return array(
        'ready' => $hasCopy && $hasImages,
        'has_copy' => $hasCopy,
        'has_images' => $hasImages,
        'final_images_count' => count($finals),
        'copy_versions_count' => is_array($copy['versions'] ?? null) ? count($copy['versions']) : 0,
        'label' => ($hasCopy && $hasImages) ? 'Listo' : 'Incompleto',
    );
}

function publicista_campaign_strategy_option_codes() {
    return array('accepted', 'recommended', 'optimal');
}

function publicista_campaign_planning_option_meta_map($planning) {
    $planning = publicista_planning_normalize($planning);
    $options = is_array($planning['recommendation_options'] ?? null) ? $planning['recommendation_options'] : array();
    $meta = array();
    foreach (publicista_campaign_strategy_option_codes() as $optionCode) {
        $option = is_array($options[$optionCode] ?? null) ? $options[$optionCode] : array();
        if (empty($option)) {
            continue;
        }
        $profilesTotal = 0;
        foreach ((array)($option['strategies'] ?? array()) as $optionStrategy) {
            $profilesTotal += count((array)($optionStrategy['profiles'] ?? array()));
        }
        $meta[$optionCode] = array(
            'code' => $optionCode,
            'label' => trim((string)($option['label'] ?? ucfirst($optionCode))),
            'profiles_total' => $profilesTotal,
            'grand_total' => (float)($option['grand_total'] ?? 0),
            'warnings_count' => count((array)($option['warnings'] ?? array())),
            'decision_help' => trim((string)($option['decision_help'] ?? '')),
            'comparison_note' => trim((string)($option['comparison_note'] ?? '')),
            'market_posture' => trim((string)($option['market_posture'] ?? '')),
        );
    }
    return $meta;
}

function publicista_campaign_resolve_planning_option($planning, $optionCode = '') {
    $planning = publicista_planning_normalize($planning);
    $options = is_array($planning['recommendation_options'] ?? null) ? $planning['recommendation_options'] : array();
    $optionCode = trim((string)$optionCode);
    if ($optionCode === '') {
        $optionCode = trim((string)($planning['default_option_code'] ?? 'recommended'));
    }
    if ($optionCode !== '' && !empty($options[$optionCode]) && is_array($options[$optionCode])) {
        $resolved = $options[$optionCode];
        $resolved['code'] = $optionCode;
        return array($optionCode, $resolved);
    }
    foreach (publicista_campaign_strategy_option_codes() as $fallbackCode) {
        if (!empty($options[$fallbackCode]) && is_array($options[$fallbackCode])) {
            $resolved = $options[$fallbackCode];
            $resolved['code'] = $fallbackCode;
            return array($fallbackCode, $resolved);
        }
    }
    return array('', array());
}

function publicista_campaign_flatten_planning_profiles($planning, $optionCode = '') {
    $planning = publicista_planning_normalize($planning);
    list($resolvedOptionCode, $resolvedOption) = publicista_campaign_resolve_planning_option($planning, $optionCode);
    $strategiesSource = !empty($resolvedOption['strategies']) && is_array($resolvedOption['strategies'])
        ? (array)$resolvedOption['strategies']
        : (array)($planning['strategy_snapshot'] ?? array());
    $rows = array();
    foreach ($strategiesSource as $strategy) {
        $girl = (int)($strategy['girl'] ?? 0);
        foreach ((array)($strategy['profiles'] ?? array()) as $profile) {
            $rows[] = array(
                'girl' => $girl,
                'num' => (int)($profile['num'] ?? 0),
                'name' => trim((string)($profile['name'] ?? 'Perfil')),
                'opts' => is_array($profile['opts'] ?? null) ? $profile['opts'] : array(),
                'why' => trim((string)($profile['why'] ?? '')),
                'cost' => (float)($profile['cost'] ?? 0),
                'free_slots' => !empty($profile['opts']['free']) && is_array($profile['opts']['free']) ? array_values($profile['opts']['free']) : array(),
                'strategy_option_code' => $resolvedOptionCode,
                'strategy_option_label' => trim((string)($resolvedOption['label'] ?? '')),
            );
        }
    }
    return $rows;
}

function publicista_campaign_profile_payment_flags($profileRow) {
    $opts = is_array($profileRow['opts'] ?? null) ? $profileRow['opts'] : array();
    return array(
        'has_premium' => !empty($opts['PREMIUM']) || !empty($opts['premium']),
        'has_top' => !empty($opts['TOP']) || !empty($opts['top']),
        'has_auto7' => !empty($opts['auto7']),
        'has_auto4' => !empty($opts['auto4']),
        'has_free' => !empty($opts['free']),
    );
}

function publicista_campaign_profile_publish_mode($profileRow) {
    $flags = publicista_campaign_profile_payment_flags($profileRow);
    if ($flags['has_premium'] && $flags['has_top'] && $flags['has_auto7']) return 'premium_top_auto7';
    if ($flags['has_premium'] && $flags['has_top'] && $flags['has_auto4']) return 'premium_top_auto4';
    if ($flags['has_top'] && $flags['has_auto7']) return 'top_auto7';
    if ($flags['has_top'] && $flags['has_auto4']) return 'top_auto4';
    if ($flags['has_premium'] && $flags['has_top']) return 'premium_top';
    if ($flags['has_premium'] && $flags['has_auto7']) return 'premium_auto7';
    if ($flags['has_premium'] && $flags['has_auto4']) return 'premium_auto4';
    if ($flags['has_premium']) return 'premium';
    if ($flags['has_top']) return 'top';
    if ($flags['has_auto7']) return 'auto7';
    if ($flags['has_auto4']) return 'auto4';
    if ($flags['has_free']) return 'free';
    return 'standard';
}

function publicista_campaign_publish_mode_label($mode) {
    $map = array(
        'premium_top_auto7' => 'PREMIUM + TOP + Auto 7€',
        'premium_top_auto4' => 'PREMIUM + TOP + Auto 4€',
        'top_auto7' => 'TOP + Auto 7€',
        'top_auto4' => 'TOP + Auto 4€',
        'premium_top' => 'PREMIUM + TOP',
        'premium_auto7' => 'PREMIUM + Auto 7€',
        'premium_auto4' => 'PREMIUM + Auto 4€',
        'premium' => 'PREMIUM',
        'top' => 'TOP',
        'auto7' => 'Auto 7€',
        'auto4' => 'Auto 4€',
        'free' => 'Gratis',
        'standard' => 'Estándar',
    );
    $mode = trim((string)$mode);
    return isset($map[$mode]) ? $map[$mode] : strtoupper($mode);
}

function publicista_campaign_profile_cost($profileRow) {
    if (isset($profileRow['cost'])) return (float)$profileRow['cost'];
    if (function_exists('publicista_ads_profile_cost_from_opts')) {
        return (float)publicista_ads_profile_cost_from_opts(is_array($profileRow['opts'] ?? null) ? $profileRow['opts'] : array());
    }
    return 0.0;
}

function publicista_campaign_humanization_defaults() {
    return array(
        'pre_publish_delay_min_sec' => 4,
        'pre_publish_delay_max_sec' => 10,
        'between_items_delay_min_sec' => 18,
        'between_items_delay_max_sec' => 45,
        'field_delay_min_ms' => 120,
        'field_delay_max_ms' => 320,
        'photo_delay_min_ms' => 1800,
        'photo_delay_max_ms' => 4200,
        'post_save_delay_min_sec' => 3,
        'post_save_delay_max_sec' => 8,
    );
}

function publicista_campaign_humanize_delay_usecs($minSec, $maxSec) {
    $minSec = max(0, (float)$minSec);
    $maxSec = max($minSec, (float)$maxSec);
    $value = $minSec;
    if ($maxSec > $minSec) {
        $value = $minSec + (mt_rand(0, 1000) / 1000) * ($maxSec - $minSec);
    }
    return (int)round($value * 1000000);
}

function publicista_campaign_build_automation_plan($campaign, $planning, $items) {
    $campaign = publicista_campaign_normalize($campaign);
    $planning = publicista_planning_normalize($planning);
    $human = publicista_campaign_humanization_defaults();
    $groups = array();
    $totalCost = 0.0;

    foreach ((array)$items as $idx => $item) {
        $accountId = trim((string)($item['account_id'] ?? ''));
        if ($accountId === '') $accountId = 'sin_cuenta';
        if (!isset($groups[$accountId])) {
            $groups[$accountId] = array(
                'account_id' => $accountId,
                'account_name' => trim((string)publicista_array_get(publicista_array_get(publicista_array_get($item, 'account_snapshot', array()), 'data', array()), 'display_name', publicista_array_get(publicista_array_get(publicista_array_get($item, 'account_snapshot', array()), 'data', array()), 'login_user', ''))),
                'portal_code' => trim((string)($item['portal_code'] ?? 'destacamos')),
                'items' => array(),
            );
        }
        $cost = publicista_campaign_profile_cost(publicista_array_get($item, 'planning_profile_snapshot', array()));
        $totalCost += $cost;
        $groups[$accountId]['items'][] = array(
            'campaign_item_id' => trim((string)($item['id'] ?? '')),
            'external_ad_id' => trim((string)($item['external_ad_id'] ?? '')),
            'publish_mode' => trim((string)($item['publish_mode'] ?? 'standard')),
            'publish_mode_label' => publicista_campaign_publish_mode_label($item['publish_mode'] ?? 'standard'),
            'estimated_cost' => $cost,
            'title' => publicista_campaign_item_copy_title($item),
            'images_count' => count((array)($item['image_snapshot'] ?? array())),
            'action_type' => 'edit_existing_listing',
            'delay_before_this_sec' => $idx === 0 ? 0 : round($human['between_items_delay_min_sec'] + ($human['between_items_delay_max_sec'] - $human['between_items_delay_min_sec']) / 2),
            'reason' => trim((string)publicista_array_get(publicista_array_get($item, 'planning_profile_snapshot', array()), 'why', '')),
        );
    }

    return array(
        'generated_at' => now_datetime(),
        'campaign_id' => trim((string)($campaign['id'] ?? '')),
        'planning_id' => trim((string)($planning['id'] ?? '')),
        'planning_name' => trim((string)($planning['nombre'] ?? '')),
        'humanization' => $human,
        'accounts' => array_values($groups),
        'items_total' => count((array)$items),
        'estimated_cost_total' => $totalCost,
        'steps' => array(
            '1. Generar composición y revisar asignación por cuenta/anuncio.',
            '2. Aprobar campaña para congelar el planning operativo.',
            '3. Subir perfiles automáticamente con pausas humanas entre anuncios e imágenes.',
        ),
    );
}

function publicista_campaign_compact_automation_plan($plan) {
    $plan = is_array($plan) ? $plan : array();
    $accounts = array();
    foreach ((array)($plan['accounts'] ?? array()) as $account) {
        $accounts[] = array(
            'account_id' => trim((string)($account['account_id'] ?? '')),
            'account_name' => trim((string)($account['account_name'] ?? '')),
            'portal_code' => trim((string)($account['portal_code'] ?? '')),
            'items_count' => count((array)($account['items'] ?? array())),
        );
    }
    return array(
        'generated_at' => trim((string)($plan['generated_at'] ?? '')),
        'campaign_id' => trim((string)($plan['campaign_id'] ?? '')),
        'planning_id' => trim((string)($plan['planning_id'] ?? '')),
        'planning_name' => trim((string)($plan['planning_name'] ?? '')),
        'humanization' => is_array($plan['humanization'] ?? null) ? $plan['humanization'] : array(),
        'accounts' => $accounts,
        'items_total' => max(0, (int)($plan['items_total'] ?? 0)),
        'estimated_cost_total' => (float)($plan['estimated_cost_total'] ?? 0),
        'steps' => array_values((array)($plan['steps'] ?? array())),
    );
}

function publicista_campaign_extract_copy_variants($product) {
    $variants = array();
    $copy = function_exists('publicista_job_copy_pack') ? publicista_job_copy_pack($product) : (is_array($product['copy_pack'] ?? null) ? $product['copy_pack'] : array());
    $versions = is_array($copy['versions'] ?? null) ? $copy['versions'] : array();
    $currentVersionId = trim((string)($copy['current_version_id'] ?? ''));
    $current = null;
    foreach ($versions as $version) {
        if (($version['id'] ?? '') === $currentVersionId) {
            $current = $version;
            break;
        }
    }
    if (!$current && !empty($versions)) $current = $versions[0];
    if (!$current) return $variants;
    foreach ((array)($current['ads'] ?? array()) as $idx => $ad) {
        $variants[] = array(
            'variant_id' => trim((string)($current['id'] ?? '')) . ':ad:' . $idx,
            'version_id' => trim((string)($current['id'] ?? '')),
            'slot' => trim((string)($ad['slot'] ?? ('slot_' . ($idx + 1)))),
            'focus' => trim((string)($ad['focus'] ?? '')),
            'title_neutral' => trim((string)($ad['title_neutral'] ?? '')),
            'title_suggestive' => trim((string)($ad['title_suggestive'] ?? '')),
            'body_neutral' => trim((string)($ad['body_neutral'] ?? '')),
            'body_suggestive' => trim((string)($ad['body_suggestive'] ?? '')),
        );
    }
    if (empty($variants)) {
        foreach ((array)($current['title_options'] ?? array()) as $idx => $title) {
            $variants[] = array(
                'variant_id' => trim((string)($current['id'] ?? '')) . ':title:' . $idx,
                'version_id' => trim((string)($current['id'] ?? '')),
                'slot' => 'title_' . ($idx + 1),
                'focus' => trim((string)($current['pack_angle'] ?? '')),
                'title_neutral' => trim((string)$title),
                'title_suggestive' => '',
                'body_neutral' => trim((string)($copy['current_summary'] ?? '')),
                'body_suggestive' => '',
            );
        }
    }
    return $variants;
}

function publicista_campaign_pick_copy_variant($product, $index = 0) {
    $variants = publicista_campaign_extract_copy_variants($product);
    if (empty($variants)) return array();
    $index = max(0, (int)$index);
    return $variants[$index % count($variants)];
}

function publicista_campaign_mb_substr_safe($text, $start, $length = null) {
    $text = (string)$text;
    if (function_exists('mb_substr')) {
        return $length === null ? mb_substr($text, $start, null, 'UTF-8') : mb_substr($text, $start, $length, 'UTF-8');
    }
    return $length === null ? substr($text, $start) : substr($text, $start, $length);
}

function publicista_campaign_mb_strlen_safe($text) {
    $text = (string)$text;
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

function publicista_campaign_trim_text_limit($text, $limit) {
    $text = trim((string)$text);
    $limit = max(1, (int)$limit);
    if (publicista_campaign_mb_strlen_safe($text) <= $limit) {
        return $text;
    }
    return rtrim(publicista_campaign_mb_substr_safe($text, 0, $limit));
}

function publicista_campaign_copy_fingerprint($title, $body) {
    $title = strtolower(trim(preg_replace('/\s+/u', ' ', (string)$title)));
    $body = strtolower(trim(preg_replace('/\s+/u', ' ', (string)$body)));
    return sha1($title . '|' . $body);
}

function publicista_campaign_copy_scope_key($item) {
    return trim((string)($item['portal_code'] ?? 'destacamos')) . '|' . trim((string)($item['account_id'] ?? ''));
}

function publicista_campaign_copy_portal_code($item) {
    return trim((string)($item['portal_code'] ?? 'destacamos'));
}

function publicista_campaign_copy_compact_text($text) {
    $text = preg_replace('/\s+/u', ' ', trim((string)$text));
    return trim((string)$text);
}

function publicista_campaign_extract_location_hint($texts) {
    foreach ((array)$texts as $text) {
        $text = publicista_campaign_copy_compact_text($text);
        if ($text === '') continue;
        if (preg_match('/\b(en\s+[[:alpha:]ÁÉÍÓÚÑáéíóúñ][[:alpha:]ÁÉÍÓÚÑáéíóúñ\s\-]{1,32})/u', $text, $match)) {
            return trim((string)$match[1]);
        }
    }
    return '';
}

function publicista_campaign_variant_title_from_row($variant) {
    $title = trim((string)($variant['title_neutral'] ?? ''));
    if ($title === '') $title = trim((string)($variant['title_suggestive'] ?? ''));
    if ($title === '') $title = 'Anuncio automatizado';
    return $title;
}

function publicista_campaign_variant_body_from_row($variant) {
    $body = trim((string)($variant['body_neutral'] ?? ''));
    if ($body === '') $body = trim((string)($variant['body_suggestive'] ?? ''));
    if ($body === '') $body = trim((string)($variant['focus'] ?? ''));
    return $body;
}

function publicista_campaign_destacamos_safe_title($title) {
    $title = publicista_campaign_copy_compact_text($title);
    if ($title === '') return 'Perfil activo con trato discreto';
    $replacements = array(
        '/\btop\b/iu' => '',
        '/\bvip\b/iu' => '',
        '/\bmorb[oa]\b/iu' => '',
        '/\bfiest[ae]ra?\b/iu' => '',
        '/\bviciosa?\b/iu' => '',
        '/\bexplosiva\b/iu' => '',
    );
    foreach ($replacements as $pattern => $replacement) {
        $title = preg_replace($pattern, $replacement, $title);
    }
    $title = preg_replace('/\s*[·|]+\s*/u', ' · ', $title);
    $title = preg_replace('/\s{2,}/u', ' ', (string)$title);
    $title = trim((string)$title, " \t\n\r\0\x0B·|-");
    if (preg_match('/^en\s+/iu', $title)) {
        $title = 'Disponible ' . $title;
    }
    if ($title === '') {
        $title = 'Perfil activo con trato discreto';
    }
    return publicista_campaign_trim_text_limit($title, 120);
}

function publicista_campaign_split_sentences($text, $limit = 8) {
    $text = publicista_campaign_copy_compact_text($text);
    if ($text === '') {
        return array();
    }

    $parts = preg_split('/(?<=[\.\!\?])\s+/u', $text);
    if (!is_array($parts) || empty($parts)) {
        $parts = array($text);
    }

    $sentences = array();
    foreach ($parts as $part) {
        $part = publicista_campaign_copy_compact_text($part);
        if ($part === '') {
            continue;
        }
        $len = publicista_campaign_mb_strlen_safe($part);
        if ($len < 28 || $len > 240) {
            continue;
        }
        $sentences[$part] = $part;
        if (count($sentences) >= max(1, (int)$limit)) {
            break;
        }
    }

    return array_values($sentences);
}

function publicista_campaign_destacamos_body_snippets($seedBodies, $limit = 10) {
    $snippets = array();
    foreach ((array)$seedBodies as $body) {
        foreach (publicista_campaign_split_sentences($body, 5) as $sentence) {
            $normalized = publicista_campaign_trim_text_limit($sentence, 240);
            if ($normalized === '') {
                continue;
            }
            $snippets[$normalized] = $normalized;
            if (count($snippets) >= max(1, (int)$limit)) {
                break 2;
            }
        }
    }
    return array_values($snippets);
}

function publicista_campaign_destacamos_safe_candidates($item, $seedTitles, $seedBodies) {
    $item = is_array($item) ? $item : array();
    $seedTitles = array_values(array_filter(array_map('trim', (array)$seedTitles)));
    $seedBodies = array_values(array_filter(array_map('trim', (array)$seedBodies)));
    $location = publicista_campaign_extract_location_hint(array_merge($seedTitles, $seedBodies));
    $bodySnippets = publicista_campaign_destacamos_body_snippets($seedBodies, 12);

    $titlePool = array();
    $pushTitle = function($title) use (&$titlePool) {
        $title = publicista_campaign_destacamos_safe_title($title);
        if ($title === '') return;
        $titlePool[$title] = $title;
    };
    foreach ($seedTitles as $title) {
        $pushTitle($title);
    }

    if ($location !== '') {
        $pushTitle('Disponible ' . $location . ' con trato discreto');
        $pushTitle('Perfil activo ' . $location . ' y ambiente cuidado');
        $pushTitle('Trato cercano ' . $location . ' y presencia natural');
        $pushTitle('Atención cuidada ' . $location . ' y conversación agradable');
        $pushTitle('Ambiente reservado ' . $location . ' y trato atento');
        $pushTitle('Presencia natural ' . $location . ' y buena conexión');
        $pushTitle('Perfil actual ' . $location . ' con presencia cuidada');
        $pushTitle('Cita tranquila ' . $location . ' y trato cercano');
    }

    foreach (array(
        'Trato cercano y ambiente discreto',
        'Perfil activo con atención cuidada',
        'Ambiente reservado y presencia natural',
        'Perfil discreto con trato agradable',
        'Atención cuidada y presencia natural',
        'Ambiente agradable y trato respetuoso',
        'Presencia cuidada y trato cercano',
        'Perfil actual con ambiente reservado',
        'Conversación agradable y trato discreto',
        'Ambiente cuidado y presencia natural',
    ) as $title) {
        $pushTitle($title);
    }

    $bodyPool = array();
    $pushBody = function($body) use (&$bodyPool) {
        $body = publicista_campaign_copy_compact_text($body);
        if ($body === '') return;
        $bodyPool[$body] = publicista_campaign_trim_text_limit($body, 2000);
    };

    foreach ($seedBodies as $body) {
        $pushBody($body);
    }

    foreach (array(
        'Perfil pensado para quienes valoran la discrecion, el buen trato y un ambiente cuidado. Atencion educada, comunicacion clara y una experiencia comoda desde el primer momento. Si buscas una cita bien organizada, puedes escribir con tranquilidad.',
        'Anuncio orientado a transmitir cercania, presencia natural y un trato respetuoso. La idea es ofrecer una cita agradable, discreta y facil de coordinar, cuidando el ambiente y los detalles.',
        'Propuesta centrada en la discrecion, la buena presencia y un entorno agradable. Comunicacion sencilla, atencion cuidada y una experiencia comoda para quienes valoran el trato natural.',
        'Perfil activo con ambiente reservado, trato cercano y una forma de atender cuidada. Ideal para planes tranquilos, bien organizados y con una presencia agradable.',
        'Espacio pensado para quienes buscan educacion, discrecion y comodidad. Buena presencia, comunicacion facil y una experiencia natural en un entorno cuidado.',
        'Atencion discreta, presencia agradable y una propuesta orientada a la comodidad. Todo esta planteado para que la cita sea sencilla, natural y bien organizada.',
        'Anuncio de tono prudente, con foco en el buen trato, la cercania y el ambiente cuidado. Pensado para quienes valoran una experiencia discreta y agradable.',
        'Perfil con trato natural, comunicacion clara y ambiente reservado. La prioridad es ofrecer una cita comoda, respetuosa y facil de coordinar.',
    ) as $body) {
        $pushBody($body);
    }

    $leadPool = array(
        'Perfil pensado para quienes valoran la discrecion, la calma y el buen trato.',
        'Propuesta preparada para una cita comoda, reservada y facil de coordinar.',
        'Anuncio de tono prudente, orientado a transmitir cercania y presencia natural.',
        'Perfil activo con una forma de atender cuidada y ambiente agradable.',
        'Espacio planteado para quienes buscan trato respetuoso y una experiencia natural.',
        'Atencion discreta y comunicacion clara, con una presencia agradable desde el primer momento.',
    );
    if ($location !== '') {
        array_unshift($leadPool,
            'Disponible ' . $location . ' con una propuesta reservada y bien cuidada.',
            'Perfil activo ' . $location . ' con trato cercano y ambiente preparado.'
        );
    }

    $middlePool = array_merge($bodySnippets, array(
        'La idea es ofrecer una cita tranquila, bien organizada y con una presencia especialmente cuidada.',
        'Se priorizan la conversacion facil, la cercania y un ambiente comodo para que todo fluya con naturalidad.',
        'Hay foco en la discrecion, la educacion y una experiencia agradable para quienes valoran los detalles.',
        'Todo esta enfocado en un trato natural, una coordinacion sencilla y un ambiente reservado.',
        'Es una propuesta pensada para transmitir calma, presencia y comodidad desde el primer contacto.',
        'La atencion se orienta a una experiencia serena, con buena energia y una comunicacion clara.',
    ));

    $closePool = array(
        'Si buscas una cita natural y comoda, puedes escribir con tranquilidad.',
        'Ideal para quienes prefieren una experiencia discreta, cuidada y facil de coordinar.',
        'Pensado para un encuentro agradable, reservado y con buen trato.',
        'Todo esta planteado para coordinar de forma sencilla, prudente y agradable.',
        'Una opcion cuidada para quienes valoran una comunicacion clara y una presencia natural.',
        'Si te encaja este estilo, el contacto puede ser facil, comodo y discreto.',
    );

    $comboLimit = 32;
    foreach ($leadPool as $lead) {
        foreach ($middlePool as $middle) {
            foreach ($closePool as $close) {
                $body = trim($lead . ' ' . $middle . ' ' . $close);
                $pushBody($body);
                if (count($bodyPool) >= $comboLimit + count($seedBodies)) {
                    break 3;
                }
            }
        }
    }

    $candidates = array();
    $titles = array_values($titlePool);
    $bodies = array_values($bodyPool);
    if (empty($titles)) $titles[] = 'Perfil activo con trato discreto';
        if (empty($bodies)) {
        $bodies[] = 'Perfil pensado para quienes valoran la discrecion, el buen trato y un ambiente cuidado. Atencion educada, comunicacion clara y una experiencia comoda desde el primer momento.';
    }

    $limit = min(40, max(count($titles), count($bodies)) + 14);
    for ($i = 0; $i < $limit; $i++) {
        $title = $titles[$i % count($titles)];
        $body = $bodies[$i % count($bodies)];
        $key = publicista_campaign_copy_fingerprint($title, $body);
        if (isset($candidates[$key])) continue;
        $candidates[$key] = array(
            'title' => $title,
            'body' => $body,
            'reason' => 'portal_safe:destacamos:' . ($i + 1),
        );
    }

    return array_values($candidates);
}

function publicista_campaign_recombined_copy_candidates($item, $baseTitle, $baseBody, $variants) {
    $portalCode = publicista_campaign_copy_portal_code($item);
    $titles = array();
    $bodies = array();

    foreach (array_merge(array(array(
        'title' => $baseTitle,
        'body' => $baseBody,
    )), array_map(function($variant) {
        return array(
            'title' => publicista_campaign_variant_title_from_row($variant),
            'body' => publicista_campaign_variant_body_from_row($variant),
        );
    }, (array)$variants)) as $row) {
        $title = publicista_campaign_copy_compact_text($row['title'] ?? '');
        $body = publicista_campaign_copy_compact_text($row['body'] ?? '');
        if ($title !== '') $titles[$title] = $title;
        if ($body !== '') $bodies[$body] = $body;
    }

    if ($portalCode === 'destacamos') {
        return publicista_campaign_destacamos_safe_candidates($item, array_values($titles), array_values($bodies));
    }

    $candidates = array();
    $titles = array_values($titles);
    $bodies = array_values($bodies);
    $limit = 12;
    foreach ($titles as $title) {
        foreach ($bodies as $body) {
            $key = publicista_campaign_copy_fingerprint($title, $body);
            if (isset($candidates[$key])) continue;
            $candidates[$key] = array(
                'title' => publicista_campaign_trim_text_limit($title, 120),
                'body' => publicista_campaign_trim_text_limit($body, 2000),
                'reason' => 'variant_mix',
            );
            if (count($candidates) >= $limit) {
                break 2;
            }
        }
    }
    return array_values($candidates);
}

function publicista_campaign_dedupe_title_suffixes() {
    return array(
        ' · trato cuidado',
        ' · discreta y elegante',
        ' · ambiente selecto',
        ' · presencia natural',
        ' · estilo actual',
        ' · atención cercana',
        ' · contacto discreto',
        ' · conversación agradable',
        ' · ambiente reservado',
        ' · trato atento',
    );
}

function publicista_campaign_dedupe_body_suffixes() {
    return array(
        'Ambiente discreto y trato cuidado.',
        'Ideal para planes tranquilos con buena conexión.',
        'Imagen pulida, conversación fácil y presencia agradable.',
        'Una propuesta pensada para quienes valoran la discreción.',
        'Cercanía, calma y una presencia especialmente cuidada.',
        'Perfecto para un encuentro cómodo, reservado y agradable.',
        'Comunicación sencilla y un ritmo cómodo desde el primer momento.',
        'Todo está pensado para coordinar de forma natural y prudente.',
        'Presencia cuidada y ambiente preparado para una cita agradable.',
        'Trato respetuoso, conversación fácil y una experiencia cómoda.',
    );
}

function publicista_campaign_prepare_unique_copy(&$seenByScope, $item, $baseTitle, $baseBody, $retryContext = array()) {
    $scope = publicista_campaign_copy_scope_key($item);
    $portalCode = publicista_campaign_copy_portal_code($item);
    if (!isset($seenByScope[$scope]) || !is_array($seenByScope[$scope])) {
        $seenByScope[$scope] = array();
    }

    $candidates = array();
    $addCandidate = function($title, $body, $reason) use (&$candidates) {
        $title = trim((string)$title);
        $body = trim((string)$body);
        if ($title === '' && $body === '') {
            return;
        }
        $fingerprint = publicista_campaign_copy_fingerprint($title, $body);
        if (isset($candidates[$fingerprint])) {
            return;
        }
        $candidates[$fingerprint] = array(
            'title' => publicista_campaign_trim_text_limit($title, 120),
            'body' => publicista_campaign_trim_text_limit($body, 2000),
            'reason' => (string)$reason,
        );
    };

    $baseTitle = trim((string)$baseTitle);
    $baseBody = trim((string)$baseBody);
    $product = is_array($item['product_snapshot']['data'] ?? null) ? $item['product_snapshot']['data'] : array();
    $variants = publicista_campaign_extract_copy_variants($product);
    
    // Si hay contexto de retry (error previo), generamos variantes específicas
    $retryAttempt = max(0, (int)($retryContext['attempt'] ?? 0));
    $retryErrorCode = trim((string)($retryContext['error_code'] ?? ''));
    
    if ($retryAttempt > 0 && function_exists('destacamos_generate_text_variant')) {
        // Generar variantes automáticas basadas en el tipo de error
        $errorType = $retryErrorCode;
        if ($errorType === '') {
            $errorType = 'duplicate_copy';
        }
        
        // Variantes automáticas para el cuerpo
        $autoVariants = array();
        for ($v = 1; $v <= 6; $v++) {
            $variantText = destacamos_generate_text_variant($baseBody, $v, $errorType);
            if ($variantText !== $baseBody && $variantText !== '') {
                $autoVariants[] = $variantText;
            }
        }
        
        // Si hay variantes automáticas, añadirlas como candidatos prioritarios
        foreach ($autoVariants as $vIdx => $variantBody) {
            $addCandidate(
                $baseTitle !== '' ? $baseTitle : 'Perfil activo',
                $variantBody,
                'auto_variant:' . ($vIdx + 1) . ':' . $errorType
            );
        }
    }
    
    foreach (publicista_campaign_recombined_copy_candidates($item, $baseTitle, $baseBody, $variants) as $candidate) {
        $addCandidate($candidate['title'] ?? '', $candidate['body'] ?? '', $candidate['reason'] ?? 'variant_mix');
    }
    $addCandidate($baseTitle, $baseBody, 'base_copy');
    if (!empty($variants)) {
        $currentVariantId = trim((string)($item['copy_variant_id'] ?? ''));
        usort($variants, function($a, $b) use ($currentVariantId) {
            $aId = trim((string)($a['variant_id'] ?? ''));
            $bId = trim((string)($b['variant_id'] ?? ''));
            if ($aId === $currentVariantId) return -1;
            if ($bId === $currentVariantId) return 1;
            return strcmp($aId, $bId);
        });
        foreach ($variants as $variant) {
            $addCandidate(
                publicista_campaign_variant_title_from_row($variant),
                publicista_campaign_variant_body_from_row($variant),
                'copy_variant:' . trim((string)($variant['variant_id'] ?? ''))
            );
        }
    }

    foreach ($candidates as $fingerprint => $candidate) {
        if (isset($seenByScope[$scope][$fingerprint])) {
            continue;
        }
        $seenByScope[$scope][$fingerprint] = true;
        return array(
            'title' => $candidate['title'],
            'body' => $candidate['body'],
            'adjusted' => ($candidate['reason'] !== 'base_copy'),
            'reason' => $candidate['reason'],
        );
    }

    $titleSuffixes = publicista_campaign_dedupe_title_suffixes();
    $bodySuffixes = publicista_campaign_dedupe_body_suffixes();
    $seed = abs(crc32(trim((string)($item['id'] ?? ($item['external_ad_id'] ?? '0')))));
    $titleBase = $baseTitle !== '' ? $baseTitle : 'Anuncio automatizado';
    $bodyBase = $baseBody;

    for ($i = 0; $i < max(count($titleSuffixes), count($bodySuffixes)); $i++) {
        $titleSuffix = $titleSuffixes[($seed + $i) % count($titleSuffixes)];
        $bodySuffix = $bodySuffixes[($seed + $i) % count($bodySuffixes)];
        $titleLimit = 120 - publicista_campaign_mb_strlen_safe($titleSuffix);
        $titleVariant = rtrim(publicista_campaign_trim_text_limit($titleBase, max(1, $titleLimit))) . $titleSuffix;
        $bodyVariant = trim($bodyBase);
        if ($bodySuffix !== '' && stripos($bodyVariant, $bodySuffix) === false) {
            $bodyVariant = trim($bodyVariant . ' ' . $bodySuffix);
        }
        if ($portalCode === 'destacamos') {
            $titleVariant = publicista_campaign_destacamos_safe_title($titleVariant);
        }
        $bodyVariant = publicista_campaign_trim_text_limit($bodyVariant, 2000);
        $fingerprint = publicista_campaign_copy_fingerprint($titleVariant, $bodyVariant);
        if (isset($seenByScope[$scope][$fingerprint])) {
            continue;
        }
        $seenByScope[$scope][$fingerprint] = true;
        return array(
            'title' => $titleVariant,
            'body' => $bodyVariant,
            'adjusted' => true,
            'reason' => 'dedupe_suffix',
        );
    }

    $fallbackFingerprint = publicista_campaign_copy_fingerprint($titleBase, $bodyBase);
    $seenByScope[$scope][$fallbackFingerprint] = true;
    return array(
        'title' => publicista_campaign_trim_text_limit($titleBase, 120),
        'body' => publicista_campaign_trim_text_limit($bodyBase, 2000),
        'adjusted' => false,
        'reason' => 'base_copy_forced',
    );
}

function publicista_campaign_result_requests_copy_retry($result) {
    if (!is_array($result) || !empty($result['ok'])) {
        return false;
    }

    $errorCode = trim((string)($result['error_code'] ?? ''));
    if (in_array($errorCode, array('duplicate_copy', 'content_moderation'), true)) {
        return true;
    }

    $parts = array(
        trim((string)($result['error'] ?? '')),
        implode(' ', array_values((array)($result['validation_errors'] ?? array()))),
    );
    $haystack = strtolower(trim(implode(' ', array_filter($parts, function($value) {
        return trim((string)$value) !== '';
    }))));

    if ($haystack === '') {
        return false;
    }

    foreach (array(
        'mismo texto',
        'mismo título',
        'mismo titulo',
        'texto un poco diferente',
        'título un poco diferente',
        'titulo un poco diferente',
        'camuflar algunas expresiones prohibidas',
        'expresiones prohibidas',
        'revisa el contenido de tu perfil',
    ) as $needle) {
        if (strpos($haystack, $needle) !== false) {
            return true;
        }
    }

    return false;
}

function publicista_campaign_pick_images($product, $limit = 4) {
    $limit = max(1, (int)$limit);
    $images = array_values((array)($product['final_images'] ?? array()));
    if (empty($images)) return array();
    return array_slice($images, 0, $limit);
}

function publicista_campaign_validate_for_generation($campaign) {
    $campaign = publicista_campaign_normalize($campaign);
    $errors = array();
    $warnings = array();
    $planning = publicista_planning_get($campaign['planning_id'] ?? '');
    if (!$planning) {
        $errors[] = 'Debes seleccionar un planning válido.';
        return array(false, $errors, $warnings, null, array(), array());
    }

    $products = array();
    foreach ((array)($campaign['product_ids'] ?? array()) as $id) {
        $row = publicista_product_get($id);
        if ($row) $products[] = $row;
    }
    $accounts = array();
    foreach ((array)($campaign['account_ids'] ?? array()) as $id) {
        $row = publicista_account_get($id);
        if ($row) $accounts[] = publicista_account_hydrate_metrics($row);
    }

    if (empty($products)) $errors[] = 'Selecciona al menos un producto publicitario.';
    if (empty($accounts)) $errors[] = 'Selecciona al menos una cuenta de portal.';

    $readyProducts = array();
    foreach ($products as $product) {
        $readyInfo = publicista_product_is_ready_for_campaign($product);
        $product['_campaign_ready'] = $readyInfo;
        if ($readyInfo['ready']) $readyProducts[] = $product;
        else $warnings[] = 'El producto "' . trim((string)($product['nombre_trabajo'] ?? $product['id'])) . '" no está listo para campaña (faltan copies o imágenes finales).';
    }

    $validAccounts = array();
    $planningPortal = trim((string)($planning['portal_code'] ?? 'destacamos'));
    foreach ($accounts as $account) {
        $portalCode = trim((string)($account['portal_code'] ?? ''));
        if ($portalCode !== '' && $planningPortal !== '' && $portalCode !== $planningPortal && $portalCode !== 'otro') {
            $warnings[] = 'La cuenta "' . trim((string)($account['display_name'] ?? $account['login_user'] ?? $account['id'])) . '" no coincide con el portal del planning.';
            continue;
        }
        if (in_array(($account['estado'] ?? ''), array('blocked'), true)) {
            $warnings[] = 'La cuenta "' . trim((string)($account['display_name'] ?? $account['login_user'] ?? $account['id'])) . '" está bloqueada.';
            continue;
        }
        $validAccounts[] = $account;
    }

    $requiredProducts = max(1, (int)($planning['num_products_target'] ?? 1));
    if (count((array)$campaign['product_ids']) !== $requiredProducts) {
        $errors[] = 'Debes seleccionar exactamente ' . $requiredProducts . ' perfiles/productos para esta estrategia.';
    }
    if (count($readyProducts) !== $requiredProducts) {
        $errors[] = 'La estrategia requiere exactamente ' . $requiredProducts . ' perfiles listos. Revisa la selección o completa copies/imágenes en los perfiles elegidos.';
    }
    if (empty($validAccounts)) $errors[] = 'No hay cuentas válidas disponibles para este planning.';

    $planProfiles = publicista_campaign_flatten_planning_profiles($planning, trim((string)($campaign['strategy_option_code'] ?? '')));
    $selectedSlots = publicista_campaign_selected_listing_slots($campaign, $validAccounts, $campaign['id'] ?? '');
    if (empty($campaign['selected_listing_refs'])) {
        $errors[] = 'Debes seleccionar los IDs internos de anuncio que quieres usar en la campaña.';
    }
    if (!empty($selectedSlots['errors'])) {
        foreach ($selectedSlots['errors'] as $slotError) {
            $errors[] = $slotError;
        }
    }

    return array(empty($errors), $errors, $warnings, $planning, $readyProducts, $validAccounts, $selectedSlots);
}

function publicista_campaign_available_listing_slots($accounts, $excludeCampaignId = '') {
    $slots = array();
    foreach ((array)$accounts as $account) {
        $account = publicista_account_hydrate_metrics($account);
        $usage = publicista_account_listing_usage($account['id'] ?? '', $account, $excludeCampaignId);
        foreach ((array)($usage['listing_ids'] ?? array()) as $listingId) {
            $slots[] = array('account' => $account, 'listing_id' => $listingId);
        }
    }
    return $slots;
}

function publicista_campaign_selected_listing_slots($campaign, $accounts, $excludeCampaignId = '', $applyEquitableBalance = true) {
    $campaign = publicista_campaign_normalize($campaign);
    $selectedRefs = array_values((array)($campaign['selected_listing_refs'] ?? array()));
    $accountsById = array();
    $availableByRef = array();
    $errors = array();

    foreach ((array)$accounts as $account) {
        $account = publicista_account_hydrate_metrics($account);
        $accountId = trim((string)($account['id'] ?? ''));
        if ($accountId === '') continue;
        $accountsById[$accountId] = $account;
        $usage = publicista_account_listing_usage($accountId, $account, $excludeCampaignId);
        foreach ((array)($usage['listing_ids'] ?? array()) as $listingId) {
            $ref = publicista_campaign_listing_ref($accountId, $listingId);
            if ($ref === '') continue;
            $availableByRef[$ref] = array(
                'account' => $account,
                'listing_id' => trim((string)$listingId),
                'ref' => $ref,
            );
        }
    }

    $selectedSlots = array();
    foreach ($selectedRefs as $ref) {
        $parsed = publicista_campaign_parse_listing_ref($ref);
        if ($parsed['account_id'] === '' || $parsed['listing_id'] === '') continue;
        if (!isset($accountsById[$parsed['account_id']])) {
            $errors[] = 'Se ha seleccionado un anuncio para una cuenta que no forma parte de la campaña.';
            continue;
        }
        if (!isset($availableByRef[$ref])) {
            $errors[] = 'El anuncio interno "' . $parsed['listing_id'] . '" ya no existe en la cuenta seleccionada.';
            continue;
        }
        $selectedSlots[] = $availableByRef[$ref];
    }

    if ($applyEquitableBalance) {
        $selectedSlots = publicista_campaign_balance_selected_slots_equitable($selectedSlots);
    }

    return array(
        'slots' => $selectedSlots,
        'errors' => array_values(array_unique($errors)),
        'selected_count' => count($selectedSlots),
        'available_count' => count($availableByRef),
    );
}

function publicista_campaign_balance_selected_slots_equitable($selectedSlots) {
    $selectedSlots = array_values((array)$selectedSlots);
    $total = count($selectedSlots);
    if ($total <= 1) return $selectedSlots;

    $groups = array();
    foreach ($selectedSlots as $slot) {
        $accountId = trim((string)($slot['account']['id'] ?? ''));
        if ($accountId === '') continue;
        if (!isset($groups[$accountId])) $groups[$accountId] = array();
        $groups[$accountId][] = $slot;
    }
    if (count($groups) <= 1) return $selectedSlots;

    foreach ($groups as $accountId => $items) {
        usort($items, function($a, $b) {
            $la = trim((string)($a['listing_id'] ?? ''));
            $lb = trim((string)($b['listing_id'] ?? ''));
            return strcmp($la, $lb);
        });
        $groups[$accountId] = array_values($items);
    }

    $accountIds = array_keys($groups);
    sort($accountIds, SORT_STRING);
    $accountsCount = count($accountIds);
    $base = (int)floor($total / $accountsCount);
    $remainder = $total % $accountsCount;

    $target = array();
    foreach ($accountIds as $idx => $accountId) {
        $target[$accountId] = $base + ($idx < $remainder ? 1 : 0);
    }

    $balanced = array();
    foreach ($accountIds as $accountId) {
        $take = min((int)$target[$accountId], count($groups[$accountId]));
        for ($i = 0; $i < $take; $i++) {
            $balanced[] = array_shift($groups[$accountId]);
        }
    }

    while (count($balanced) < $total) {
        $progress = false;
        foreach ($accountIds as $accountId) {
            if (empty($groups[$accountId])) continue;
            $balanced[] = array_shift($groups[$accountId]);
            $progress = true;
            if (count($balanced) >= $total) break;
        }
        if (!$progress) break;
    }

    return array_values($balanced);
}

function publicista_campaign_profile_is_paid($profileRow) {
    $flags = publicista_campaign_profile_payment_flags($profileRow);
    return !empty($flags['has_premium']) || !empty($flags['has_top']) || !empty($flags['has_auto7']) || !empty($flags['has_auto4']);
}

function publicista_campaign_make_extra_free_profile($sourceProfile, $sequence) {
    $sourceProfile = is_array($sourceProfile) ? $sourceProfile : array();
    $freeSlots = array_values((array)($sourceProfile['free_slots'] ?? array()));
    if (empty($freeSlots)) $freeSlots = array('10:00', '22:00');
    return array(
        'girl' => (int)($sourceProfile['girl'] ?? 0),
        'num' => 1000 + (int)$sequence,
        'name' => trim((string)($sourceProfile['name'] ?? 'Perfil')) . ' · ajuste gratis',
        'opts' => array('free' => $freeSlots),
        'why' => 'Ajuste automático para ocupar anuncios internos extra con perfil gratuito.',
        'cost' => 0.0,
        'free_slots' => $freeSlots,
    );
}

function publicista_campaign_profile_priority($profileRow) {
    $flags = publicista_campaign_profile_payment_flags($profileRow);
    if (!empty($flags['has_premium']) && !empty($flags['has_top']) && !empty($flags['has_auto7'])) return 480;
    if (!empty($flags['has_premium']) && !empty($flags['has_top']) && !empty($flags['has_auto4'])) return 460;
    if (!empty($flags['has_top']) && !empty($flags['has_auto7'])) return 420;
    if (!empty($flags['has_top']) && !empty($flags['has_auto4'])) return 400;
    if (!empty($flags['has_premium']) && !empty($flags['has_top'])) return 380;
    if (!empty($flags['has_top'])) return 320;
    if (!empty($flags['has_premium'])) return 300;
    if (!empty($flags['has_auto7'])) return 230;
    if (!empty($flags['has_auto4'])) return 180;
    if (!empty($flags['has_free'])) return 40;
    return 100;
}

function publicista_campaign_group_profiles_by_girl($planProfiles) {
    $groups = array();
    foreach (array_values((array)$planProfiles) as $profileRow) {
        $girl = max(1, (int)($profileRow['girl'] ?? 1));
        if (!isset($groups[$girl])) {
            $groups[$girl] = array(
                'girl' => $girl,
                'profiles' => array(),
            );
        }
        $groups[$girl]['profiles'][] = $profileRow;
    }

    foreach ($groups as $girl => $group) {
        usort($group['profiles'], function($a, $b) {
            return publicista_campaign_profile_priority($b) <=> publicista_campaign_profile_priority($a);
        });
        $groups[$girl] = $group;
    }

    ksort($groups);
    return array_values($groups);
}

function publicista_campaign_flatten_profile_groups($groups) {
    $rows = array();
    foreach ((array)$groups as $group) {
        foreach ((array)($group['profiles'] ?? array()) as $profileRow) {
            $rows[] = $profileRow;
        }
    }
    return array_values($rows);
}

function publicista_campaign_adjust_profiles_to_selected_slots($planProfiles, $selectedSlotCount) {
    $groups = publicista_campaign_group_profiles_by_girl($planProfiles);
    $selectedSlotCount = max(0, (int)$selectedSlotCount);
    $allProfiles = publicista_campaign_flatten_profile_groups($groups);
    $paid = array_values(array_filter($allProfiles, 'publicista_campaign_profile_is_paid'));
    $baseCount = count($allProfiles);
    $warnings = array();
    $errors = array();

    if ($selectedSlotCount <= 0) {
        $errors[] = 'Debes seleccionar al menos un anuncio interno.';
        return array($planProfiles, $warnings, $errors);
    }

    if ($selectedSlotCount === $baseCount) {
        return array($allProfiles, $warnings, $errors);
    }

    if ($selectedSlotCount < count($paid)) {
        $errors[] = 'Has seleccionado ' . $selectedSlotCount . ' anuncios, pero la estrategia necesita al menos ' . count($paid) . ' anuncios de pago. No se puede reajustar solo quitando gratuitos.';
        return array($allProfiles, $warnings, $errors);
    }

    if ($selectedSlotCount < $baseCount) {
        $removeCount = $baseCount - $selectedSlotCount;
        while ($removeCount > 0) {
            $candidateIndex = -1;
            $candidateFreeCount = -1;
            foreach ($groups as $index => $group) {
                $freeCount = 0;
                foreach ((array)$group['profiles'] as $profileRow) {
                    if (!publicista_campaign_profile_is_paid($profileRow)) $freeCount++;
                }
                if ($freeCount <= 0) continue;
                if ($freeCount > $candidateFreeCount) {
                    $candidateIndex = $index;
                    $candidateFreeCount = $freeCount;
                }
            }
            if ($candidateIndex < 0) break;

            for ($i = count($groups[$candidateIndex]['profiles']) - 1; $i >= 0; $i--) {
                if (!publicista_campaign_profile_is_paid($groups[$candidateIndex]['profiles'][$i])) {
                    array_splice($groups[$candidateIndex]['profiles'], $i, 1);
                    $removeCount--;
                    break;
                }
            }
        }

        $adjusted = publicista_campaign_flatten_profile_groups($groups);
        $warnings[] = 'La campaña usa menos anuncios que la estrategia base. Se han quitado ' . max(0, $baseCount - count($adjusted)) . ' perfiles gratuitos, preservando todos los anuncios de pago.';
        return array($adjusted, $warnings, $errors);
    }

    $adjusted = $allProfiles;
    $extraNeeded = $selectedSlotCount - $baseCount;
    $freeSeeds = array_values(array_filter($allProfiles, function($row) {
        return !publicista_campaign_profile_is_paid($row);
    }));
    $freeSeeds = !empty($freeSeeds) ? $freeSeeds : (!empty($paid) ? $paid : array(array(
        'girl' => 1,
        'num' => 1,
        'name' => 'Perfil extra',
        'free_slots' => array('10:00', '22:00'),
    )));

    for ($i = 0; $i < $extraNeeded; $i++) {
        $candidateIndex = 0;
        $candidateSize = null;
        foreach ($groups as $index => $group) {
            $size = count((array)($group['profiles'] ?? array()));
            if ($candidateSize === null || $size < $candidateSize) {
                $candidateIndex = $index;
                $candidateSize = $size;
            }
        }
        $groupGirl = max(1, (int)($groups[$candidateIndex]['girl'] ?? 1));
        $seed = null;
        foreach ($freeSeeds as $seedRow) {
            if (max(1, (int)($seedRow['girl'] ?? 1)) === $groupGirl) {
                $seed = $seedRow;
                break;
            }
        }
        if (!$seed) $seed = $freeSeeds[$i % count($freeSeeds)];
        $groups[$candidateIndex]['profiles'][] = publicista_campaign_make_extra_free_profile($seed, $i + 1);
    }

    $adjusted = publicista_campaign_flatten_profile_groups($groups);
    $warnings[] = 'La campaña usa más anuncios que la estrategia base. Se han añadido ' . $extraNeeded . ' perfiles gratuitos para ocupar todos los anuncios seleccionados.';
    return array(array_values($adjusted), $warnings, $errors);
}

function publicista_campaign_order_selected_slots($selectedSlots, $accounts, $rotationSeed = '') {
    $groups = array();

    foreach ((array)$selectedSlots as $slot) {
        $accountId = trim((string)($slot['account']['id'] ?? ''));
        if ($accountId === '') continue;
        if (!isset($groups[$accountId])) $groups[$accountId] = array();
        $groups[$accountId][] = $slot;
    }

    if (empty($groups)) {
        return array();
    }

    foreach ($groups as $accountId => $items) {
        usort($groups[$accountId], function($a, $b) {
            return strcmp((string)($a['listing_id'] ?? ''), (string)($b['listing_id'] ?? ''));
        });
    }

    $accountIds = array();
    foreach ((array)$accounts as $account) {
        $accountId = trim((string)($account['id'] ?? ''));
        if ($accountId === '' || !isset($groups[$accountId])) continue;
        $accountIds[] = $accountId;
    }
    foreach (array_keys($groups) as $accountId) {
        if (!in_array($accountId, $accountIds, true)) {
            $accountIds[] = $accountId;
        }
    }
    if (empty($accountIds)) {
        $accountIds = array_keys($groups);
        sort($accountIds, SORT_STRING);
    }
    $accountsCount = count($accountIds);
    $totalSlots = count((array)$selectedSlots);

    $baseTarget = (int)floor($totalSlots / max(1, $accountsCount));
    $remainder = $totalSlots % max(1, $accountsCount);
    $targets = array_fill_keys($accountIds, $baseTarget);
    $rotationSeed = trim((string)$rotationSeed);
    $startIndex = 0;
    if ($remainder > 0 && $accountsCount > 0) {
        if ($rotationSeed !== '') {
            $startIndex = abs((int)crc32($rotationSeed)) % $accountsCount;
        }
        for ($i = 0; $i < $remainder; $i++) {
            $idx = ($startIndex + $i) % $accountsCount;
            $targets[$accountIds[$idx]]++;
        }
    }

    $assigned = array();
    foreach ($accountIds as $accountId) {
        $assigned[$accountId] = 0;
    }

    $ordered = array();
    $progress = true;
    while ($progress) {
        $progress = false;
        foreach ($accountIds as $accountId) {
            if ($assigned[$accountId] >= (int)($targets[$accountId] ?? 0)) continue;
            if (empty($groups[$accountId])) continue;
            $ordered[] = array_shift($groups[$accountId]);
            $assigned[$accountId]++;
            $progress = true;
        }
    }

    foreach ($accountIds as $accountId) {
        while (!empty($groups[$accountId])) {
            $ordered[] = array_shift($groups[$accountId]);
        }
    }

    return array_values($ordered);
}

function publicista_campaign_build_generation_summary($campaign, $planning, $items, $warnings = array(), $errors = array()) {
    $productsUsed = array();
    $accountsUsed = array();
    $modes = array();
    $estimatedCost = 0.0;
    foreach ($items as $item) {
        $productsUsed[$item['product_job_id'] ?? ''] = true;
        $accountsUsed[$item['account_id'] ?? ''] = true;
        $mode = trim((string)($item['publish_mode'] ?? 'standard'));
        if (!isset($modes[$mode])) $modes[$mode] = 0;
        $modes[$mode]++;
        $estimatedCost += publicista_campaign_profile_cost(publicista_array_get($item, 'planning_profile_snapshot', array()));
    }
    return array(
        'planning_id' => trim((string)($planning['id'] ?? '')),
        'planning_name' => trim((string)($planning['nombre'] ?? '')),
        'strategy_option_code' => trim((string)($campaign['strategy_option_code'] ?? '')),
        'strategy_option_label' => trim((string)($campaign['strategy_option_label'] ?? '')),
        'items_total' => count($items),
        'products_used_count' => count($productsUsed),
        'accounts_used_count' => count($accountsUsed),
        'listing_ids_assigned_count' => count($items),
        'modes' => $modes,
        'estimated_cost' => $estimatedCost,
        'warnings' => array_values($warnings),
        'errors' => array_values($errors),
        'generated_at' => now_datetime(),
    );
}

function publicista_campaign_preview_summary($campaign, $items = array()) {
    $campaign = is_array($campaign) ? $campaign : array();
    $items = array_values(is_array($items) ? $items : array());

    $totals = array(
        'items_count' => 0,
        'estimated_cost_total' => 0.0,
        'accounts_count' => 0,
        'products_count' => 0,
    );

    $byAccount = array();
    $byProduct = array();
    $warnings = array();

    foreach ($items as $item) {
        if (!is_array($item)) continue;

        $totals['items_count']++;
        $itemCost = (float)publicista_campaign_profile_cost(publicista_array_get($item, 'planning_profile_snapshot', array()));
        $totals['estimated_cost_total'] += $itemCost;

        $accountId = trim((string)($item['account_id'] ?? ''));
        $accountSnapshot = is_array($item['account_snapshot']['data'] ?? null) ? $item['account_snapshot']['data'] : array();
        $accountName = trim((string)($accountSnapshot['display_name'] ?? ($accountSnapshot['login_user'] ?? $accountId)));
        if ($accountName === '') $accountName = 'Cuenta sin nombre';

        if (!isset($byAccount[$accountId])) {
            $byAccount[$accountId] = array(
                'account_id' => $accountId,
                'account_name' => $accountName,
                'items_count' => 0,
                'estimated_cost_total' => 0.0,
            );
        }
        $byAccount[$accountId]['items_count']++;
        $byAccount[$accountId]['estimated_cost_total'] += $itemCost;

        $productId = trim((string)($item['product_job_id'] ?? ''));
        $productSnapshot = is_array($item['product_snapshot']['data'] ?? null) ? $item['product_snapshot']['data'] : array();
        $productName = trim((string)($productSnapshot['nombre_trabajo'] ?? $productId));
        if ($productName === '') $productName = 'Producto sin nombre';

        if (!isset($byProduct[$productId])) {
            $byProduct[$productId] = array(
                'product_id' => $productId,
                'product_name' => $productName,
                'items_count' => 0,
                'estimated_cost_total' => 0.0,
            );
        }
        $byProduct[$productId]['items_count']++;
        $byProduct[$productId]['estimated_cost_total'] += $itemCost;

        $listingId = trim((string)($item['external_ad_id'] ?? ''));
        if ($listingId === '') {
            $warnings[] = 'Hay items sin ID interno de anuncio (listing). Revísalos antes de subir.';
        }
    }

    $totals['accounts_count'] = count($byAccount);
    $totals['products_count'] = count($byProduct);

    $accountRows = array_values($byAccount);
    usort($accountRows, function($a, $b) {
        $aCount = (int)($a['items_count'] ?? 0);
        $bCount = (int)($b['items_count'] ?? 0);
        if ($aCount === $bCount) {
            return strcmp((string)($a['account_name'] ?? ''), (string)($b['account_name'] ?? ''));
        }
        return $bCount <=> $aCount;
    });

    $productRows = array_values($byProduct);
    usort($productRows, function($a, $b) {
        $aCount = (int)($a['items_count'] ?? 0);
        $bCount = (int)($b['items_count'] ?? 0);
        if ($aCount === $bCount) {
            return strcmp((string)($a['product_name'] ?? ''), (string)($b['product_name'] ?? ''));
        }
        return $bCount <=> $aCount;
    });

    return array(
        'totals' => $totals,
        'by_account' => $accountRows,
        'by_product' => $productRows,
        'warnings' => array_values(array_unique($warnings)),
    );
}

function publicista_campaign_rebalance_distribution($campaign, $distributionMatrix) {
    $campaign = publicista_campaign_normalize($campaign);
    $distributionMatrix = is_array($distributionMatrix) ? $distributionMatrix : array();

    $campaignId = trim((string)($campaign['id'] ?? ''));
    if ($campaignId === '') {
        return array(false, $campaign, array('errors' => array('La campaña no es válida.'), 'warnings' => array()));
    }

    $items = publicista_campaign_items_for_campaign($campaignId);
    if (empty($items)) {
        return array(false, $campaign, array('errors' => array('No hay items generados para reequilibrar.'), 'warnings' => array()));
    }

    $productIds = array_values(array_filter(array_map('trim', (array)($campaign['product_ids'] ?? array()))));
    if (empty($productIds)) {
        $productCounts = array();
        foreach ($items as $item) {
            $productId = trim((string)($item['product_job_id'] ?? ''));
            if ($productId === '') continue;
            if (!isset($productCounts[$productId])) $productCounts[$productId] = 0;
            $productCounts[$productId]++;
        }
        $productIds = array_keys($productCounts);
    }
    $productIds = array_values(array_unique(array_filter($productIds, function($id) { return trim((string)$id) !== ''; })));
    sort($productIds, SORT_STRING);
    if (empty($productIds)) {
        return array(false, $campaign, array('errors' => array('No se pudieron detectar productos para el reparto.'), 'warnings' => array()));
    }

    $accountIds = array_values(array_filter(array_map('trim', (array)($campaign['account_ids'] ?? array()))));
    if (empty($accountIds)) {
        return array(false, $campaign, array('errors' => array('La campaña no tiene cuentas configuradas.'), 'warnings' => array()));
    }

    $allAccounts = publicista_accounts_get(false);
    $accountsById = array();
    foreach ((array)$allAccounts as $account) {
        $aid = trim((string)($account['id'] ?? ''));
        if ($aid === '' || !in_array($aid, $accountIds, true)) continue;
        $accountsById[$aid] = $account;
    }

    foreach ($accountIds as $aid) {
        if (!isset($accountsById[$aid])) {
            return array(false, $campaign, array('errors' => array('No se encontró la cuenta seleccionada: ' . $aid), 'warnings' => array()));
        }
    }

    $selectedSlotsMeta = publicista_campaign_selected_listing_slots($campaign, array_values($accountsById), $campaignId, false);
    $slotErrors = array_values((array)($selectedSlotsMeta['errors'] ?? array()));
    if (!empty($slotErrors)) {
        return array(false, $campaign, array('errors' => $slotErrors, 'warnings' => array()));
    }

    $slotPoolByAccount = array();
    foreach ((array)($selectedSlotsMeta['slots'] ?? array()) as $slot) {
        $aid = trim((string)($slot['account']['id'] ?? ''));
        $listingId = trim((string)($slot['listing_id'] ?? ''));
        if ($aid === '' || $listingId === '') continue;
        if (!isset($slotPoolByAccount[$aid])) $slotPoolByAccount[$aid] = array();
        $slotPoolByAccount[$aid][] = $listingId;
    }
    foreach ($accountIds as $aid) {
        if (!isset($slotPoolByAccount[$aid])) $slotPoolByAccount[$aid] = array();
        sort($slotPoolByAccount[$aid], SORT_STRING);
    }

    $errors = array();
    $warnings = array();
    $capacityByAccount = array();

    foreach ($accountIds as $aid) {
        $capacityByAccount[$aid] = count((array)($slotPoolByAccount[$aid] ?? array()));
    }

    $requestedMatrix = array();
    foreach ($productIds as $productId) {
        $row = is_array($distributionMatrix[$productId] ?? null) ? $distributionMatrix[$productId] : array();
        $requestedMatrix[$productId] = array();
        foreach ($accountIds as $aid) {
            $raw = $row[$aid] ?? 0;
            $requestedMatrix[$productId][$aid] = max(0, (int)$raw);
        }
    }

    $totalItems = count($items);
    $requestedTotal = 0;
    foreach ($requestedMatrix as $row) {
        foreach ((array)$row as $count) {
            $requestedTotal += max(0, (int)$count);
        }
    }

    $targetMatrix = $requestedMatrix;

    // Count items per product (informational only — no restrictive validation;
    // cross-product redistribution in the assignment phase handles any mismatch)
    $allItems = array_values($items);
    $itemsPerProduct = array();
    foreach ($allItems as $item) {
        $pid = trim((string)($item['product_job_id'] ?? ''));
        if ($pid === '') continue;
        if (!isset($itemsPerProduct[$pid])) $itemsPerProduct[$pid] = 0;
        $itemsPerProduct[$pid]++;
    }
    // Add any product from items that is missing in the campaign product list
    foreach ($itemsPerProduct as $pid => $count) {
        if (!in_array($pid, $productIds, true)) {
            $productIds[] = $pid;
            $warnings[] = 'Producto ' . $pid . ' detectado en items pero ausente de la lista de productos de la campaña. Se ha añadido automáticamente.';
        }
    }
    sort($productIds, SORT_STRING);
    // Ensure every product has a row in targetMatrix
    foreach ($productIds as $pid) {
        if (!isset($targetMatrix[$pid])) {
            $targetMatrix[$pid] = array();
            foreach ($accountIds as $aid) {
                $targetMatrix[$pid][$aid] = 0;
            }
        }
    }

    // Capacity validation per account (existing)
    $accountTargetCounts = array_fill_keys($accountIds, 0);
    foreach ($productIds as $productId) {
        foreach ($accountIds as $aid) {
            $accountTargetCounts[$aid] += (int)($targetMatrix[$productId][$aid] ?? 0);
        }
    }

    foreach ($accountTargetCounts as $aid => $count) {
        $available = max(0, (int)($capacityByAccount[$aid] ?? 0));
        if ($count > $available) {
            $errors[] = 'La cuenta ' . $aid . ' supera su máximo: solicitado ' . $count . ', capacidad ' . $available . '.';
        }
    }
    if (!empty($errors)) {
        return array(false, $campaign, array('errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))));
    }

    foreach ($accountTargetCounts as $aid => $count) {
        $available = count((array)($slotPoolByAccount[$aid] ?? array()));
        if ($count > $available) {
            $errors[] = 'La cuenta ' . $aid . ' no tiene suficientes IDs internos disponibles (' . $available . ') para el reparto solicitado (' . $count . ').';
        }
    }

    if (!empty($errors)) {
        return array(false, $campaign, array('errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))));
    }

    // Sort all items by ID
    usort($allItems, function($a, $b) {
        return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
    });

    // Build listing queues per account
    $listingQueues = array();
    foreach ($slotPoolByAccount as $aid => $listingIds) {
        $listingQueues[$aid] = array_values($listingIds);
    }

    // Build product entity lookup
    $productEntities = array();
    foreach ($productIds as $pid) {
        $entity = publicista_product_get($pid);
        $productEntities[$pid] = $entity ?: array('id' => $pid, 'nombre' => 'Producto ' . $pid);
    }

    // ─── Group items by product_job_id ────────────────────────────────
    $itemsByProduct = array();
    foreach ($allItems as $item) {
        $pid = trim((string)($item['product_job_id'] ?? ''));
        if ($pid === '') {
            $pid = '__unassigned__';
        }
        if (!isset($itemsByProduct[$pid])) $itemsByProduct[$pid] = array();
        $itemsByProduct[$pid][] = $item;
    }
    foreach ($itemsByProduct as $pid => &$bucket) {
        usort($bucket, function($a, $b) {
            return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
        });
    }
    unset($bucket);

    $changedItems = 0;
    $updatedItems = array();
    $productVariantUsage = array();

    // Build target lists per product from the matrix
    $productTargets = array();
    foreach ($productIds as $pid) {
        $productTargets[$pid] = array();
        foreach ($accountIds as $aid) {
            $need = (int)($targetMatrix[$pid][$aid] ?? 0);
            for ($i = 0; $i < $need; $i++) {
                $productTargets[$pid][] = array('product_id' => $pid, 'account_id' => $aid);
            }
        }
    }

    // ─── Cross-product redistribution: surplus items → deficit products ──
    $surplusPool = array();
    $deficitMap = array();

    foreach ($productIds as $pid) {
        $targetCount = count($productTargets[$pid] ?? array());
        $bucketCount = count($itemsByProduct[$pid] ?? array());
        $diff = $bucketCount - $targetCount;
        if ($diff > 0) {
            $extra = array_splice($itemsByProduct[$pid], $targetCount, $diff);
            foreach ($extra as $item) {
                $surplusPool[] = array('item' => $item, 'from_pid' => $pid);
            }
        } elseif ($diff < 0) {
            $deficitMap[$pid] = abs($diff);
        }
    }

    $redistributed = 0;
    foreach ($surplusPool as $entry) {
        $item = $entry['item'];
        $fromPid = $entry['from_pid'];
        $placed = false;
        foreach ($deficitMap as $dpid => &$need) {
            if ($need <= 0) continue;
            if (!isset($productEntities[$dpid])) continue;
            // Reassign item to the deficit product
            $item['product_job_id'] = $dpid;
            $item['product_snapshot'] = publicista_snapshot_from_entity('product', $productEntities[$dpid]);
            if (!isset($productVariantUsage[$dpid])) $productVariantUsage[$dpid] = 0;
            $vi = $productVariantUsage[$dpid];
            $productVariantUsage[$dpid] = $vi + 1;
            $newProd = $productEntities[$dpid];
            $cv = publicista_campaign_pick_copy_variant($newProd, $vi);
            $imgs = publicista_campaign_pick_images($newProd, 4);
            $item['copy_variant_id'] = trim((string)($cv['variant_id'] ?? ''));
            $item['copy_snapshot'] = $cv;
            $item['image_ids'] = array_map(function($img) { return trim((string)($img['id'] ?? ($img['filename'] ?? $img['path_rel'] ?? ''))); }, $imgs);
            $item['image_snapshot'] = $imgs;
            if (!isset($itemsByProduct[$dpid])) $itemsByProduct[$dpid] = array();
            $itemsByProduct[$dpid][] = $item;
            $need--;
            $changedItems++;
            $redistributed++;
            $placed = true;
            break;
        }
        if (!$placed) {
            $itemsByProduct[$fromPid][] = $item;
            $warnings[] = 'No se pudo reasignar el item ' . ($item['id'] ?? '?') . ' desde ' . $fromPid . '; se mantiene en su producto original.';
        }
    }
    if ($redistributed > 0) {
        $warnings[] = 'Redistribución cruzada: ' . $redistributed . ' items movidos entre productos para cuadrar con la matriz de reparto.';
    }

    // Re-sort buckets that received redistributed items
    foreach ($deficitMap as $dpid => $_) {
        if (!empty($itemsByProduct[$dpid])) {
            usort($itemsByProduct[$dpid], function($a, $b) {
                return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
            });
        }
    }

    // Rebuild productTargets
    foreach ($productIds as $pid) {
        $productTargets[$pid] = array();
        foreach ($accountIds as $aid) {
            $need = (int)($targetMatrix[$pid][$aid] ?? 0);
            for ($i = 0; $i < $need; $i++) {
                $productTargets[$pid][] = array('product_id' => $pid, 'account_id' => $aid);
            }
        }
    }

    // ─── Assign targets to items, product by product ───────────────────
    foreach ($productIds as $pid) {
        $bucket = $itemsByProduct[$pid] ?? array();
        $productEntity = $productEntities[$pid] ?? array('id' => $pid, 'nombre' => 'Producto ' . $pid);
        $targets = $productTargets[$pid] ?? array();

        $targetCount = count($targets);
        $itemCount = count($bucket);
        if ($targetCount !== $itemCount) {
            while (count($bucket) > $targetCount) {
                array_pop($bucket);
                $warnings[] = 'Producto ' . $pid . ': sobraba un item tras redistribución (ignorado).';
            }
            while (count($bucket) < $targetCount && $targetCount > $itemCount) {
                if (count($bucket) === 0) break;
                $errors[] = 'Producto ' . $pid . ': faltan ' . ($targetCount - $itemCount) . ' items para cubrir los targets de la matriz.';
                break;
            }
        }
        if (!empty($errors)) break;

        if (!isset($productVariantUsage[$pid])) {
            $productVariantUsage[$pid] = 0;
        }

        foreach ($bucket as $idx => $item) {
            $target = $targets[$idx] ?? array();
            $targetAid = trim((string)($target['account_id'] ?? ''));
            $targetPid = trim((string)($target['product_id'] ?? ''));
            if ($targetAid === '' || $targetPid === '' || empty($listingQueues[$targetAid])) {
                return array(false, $campaign, array('errors' => array('No hay IDs internos suficientes para aplicar el reparto solicitado.'), 'warnings' => array_values(array_unique($warnings))));
            }

            $newListingId = array_shift($listingQueues[$targetAid]);
            $currentAid = trim((string)($item['account_id'] ?? ''));
            $currentListing = trim((string)($item['external_ad_id'] ?? ''));
            $currentPid = trim((string)($item['product_job_id'] ?? ''));
            $productChanged = ($currentPid !== $targetPid);

            if ($currentAid !== $targetAid || $currentListing !== $newListingId || $productChanged) {
                $changedItems++;
            }

            $accountEntity = $accountsById[$targetAid] ?? array();
            $item['product_job_id'] = $targetPid;
            $item['product_snapshot'] = publicista_snapshot_from_entity('product', $productEntity);
            $item['account_id'] = $targetAid;
            $item['account_snapshot'] = publicista_snapshot_from_entity('account', $accountEntity);
            $item['external_ad_id'] = $newListingId;
            $item['phone_id'] = '';

            if ($productChanged) {
                $variantIndex = (int)($productVariantUsage[$pid] ?? 0);
                $productVariantUsage[$pid] = $variantIndex + 1;
                $copyVariant = publicista_campaign_pick_copy_variant($productEntity, $variantIndex);
                $images = publicista_campaign_pick_images($productEntity, 4);
                $item['copy_variant_id'] = trim((string)($copyVariant['variant_id'] ?? ''));
                $item['copy_snapshot'] = $copyVariant;
                $item['image_ids'] = array_map(function($img) { return trim((string)($img['id'] ?? ($img['filename'] ?? $img['path_rel'] ?? ''))); }, $images);
                $item['image_snapshot'] = $images;
            }

            $item['updated_at'] = now_datetime();
            list($_okItem, $savedItem) = publicista_campaign_item_save($item);
            $updatedItems[] = $savedItem;
        }
    }

    if (!empty($errors)) {
        return array(false, $campaign, array('errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))));
    }

    // Warn about unassigned items (items whose product_job_id was empty or unknown)
    if (!empty($itemsByProduct['__unassigned__'])) {
        $warnings[] = 'Hay ' . count($itemsByProduct['__unassigned__']) . ' items sin producto asignado que no se reequilibraron.';
    }

    // Detect orphan product buckets (items whose product is not in the campaign list)
    $processedPids = array_combine($productIds, array_fill(0, count($productIds), true));
    foreach ($itemsByProduct as $opid => $bucket) {
        if ($opid === '__unassigned__') continue;
        if (isset($processedPids[$opid])) continue;
        if (empty($bucket)) continue;
        $warnings[] = 'Producto ' . $opid . ' tiene ' . count($bucket) . ' items pero no aparece en la matriz de reparto. Esos items no fueron reasignados.';
    }

    $planning = publicista_planning_get($campaign['planning_id'] ?? '');
    if (!$planning) {
        $planning = array('id' => '', 'nombre' => 'Sin planning');
    }

    $warnings = array_values(array_unique($warnings));
    $campaign['distribution_matrix'] = $targetMatrix;
    $campaign['distribution_matrix_updated_at'] = now_datetime();
    $campaign['automation_plan'] = publicista_campaign_compact_automation_plan(publicista_campaign_build_automation_plan($campaign, $planning, $updatedItems));
    $campaign['execution_summary'] = array_merge(
        (array)($campaign['execution_summary'] ?? array()),
        publicista_campaign_build_generation_summary($campaign, $planning, $updatedItems, $warnings, array()),
        array(
            'last_phase' => 'distribution_rebalanced',
            'last_distribution_rebalanced_at' => now_datetime(),
            'last_distribution_rebalanced_items' => $changedItems,
        )
    );
    $campaign['updated_at'] = now_datetime();
    list($_okCampaign, $savedCampaign) = publicista_campaign_save($campaign);

    return array(true, $savedCampaign, array(
        'errors' => array(),
        'warnings' => $warnings,
        'changed_items' => $changedItems,
        'autofix' => array(
            'requested_total' => $requestedTotal,
            'campaign_total' => $totalItems,
        ),
    ));
}

function publicista_campaign_autofix_distribution_matrix($productCounts, $accountIds, $capacityByAccount, $requestedMatrix) {
    $productCounts = is_array($productCounts) ? $productCounts : array();
    $accountIds = array_values(array_filter(array_map('trim', (array)$accountIds)));
    $capacityByAccount = is_array($capacityByAccount) ? $capacityByAccount : array();
    $requestedMatrix = is_array($requestedMatrix) ? $requestedMatrix : array();

    $warnings = array();
    $errors = array();
    $targetMatrix = array();
    $requested = array();
    $accountAssigned = array_fill_keys($accountIds, 0);
    $maxRowAutofixDelta = 1;

    $totalRequired = 0;
    foreach ($productCounts as $productId => $required) {
        $totalRequired += max(0, (int)$required);
        $requested[$productId] = array();
        $targetMatrix[$productId] = array();
        foreach ($accountIds as $aid) {
            $count = max(0, (int)($requestedMatrix[$productId][$aid] ?? 0));
            $requested[$productId][$aid] = $count;
            $targetMatrix[$productId][$aid] = $count;
        }
    }

    $totalCapacity = 0;
    foreach ($accountIds as $aid) {
        $totalCapacity += max(0, (int)($capacityByAccount[$aid] ?? 0));
    }

    $requestedByAccount = array_fill_keys($accountIds, 0);
    foreach ($productCounts as $productId => $_required) {
        foreach ($accountIds as $aid) {
            $requestedByAccount[$aid] += (int)($requested[$productId][$aid] ?? 0);
        }
    }

    $overCapacity = array();
    foreach ($accountIds as $aid) {
        $requestedTotalForAccount = (int)($requestedByAccount[$aid] ?? 0);
        $capacity = max(0, (int)($capacityByAccount[$aid] ?? 0));
        if ($requestedTotalForAccount > $capacity) {
            $overCapacity[$aid] = array(
                'requested' => $requestedTotalForAccount,
                'capacity' => $capacity,
                'deficit' => $requestedTotalForAccount - $capacity,
            );
        }
    }

    if ($totalRequired > $totalCapacity) {
        $deficit = $totalRequired - $totalCapacity;
        $parts = array();
        foreach ($accountIds as $aid) {
            $parts[] = $aid . ': solicitado=' . (int)($requestedByAccount[$aid] ?? 0) . ', max=' . max(0, (int)($capacityByAccount[$aid] ?? 0));
        }
        $errors[] = 'No hay capacidad suficiente en las cuentas seleccionadas. Requerido total=' . $totalRequired . ', capacidad total=' . $totalCapacity . ', déficit=' . $deficit . '. Detalle por cuenta -> ' . implode(' | ', $parts);
        return array(false, array(), array(
            'errors' => $errors,
            'warnings' => $warnings,
            'requested_by_account' => $requestedByAccount,
            'capacity_by_account' => $capacityByAccount,
            'deficit_total' => $deficit,
            'over_capacity_accounts' => $overCapacity,
        ));
    }

    if (!empty($overCapacity)) {
        $parts = array();
        foreach ($accountIds as $aid) {
            if (!isset($overCapacity[$aid])) continue;
            $parts[] = $aid . ': solicitado=' . (int)$overCapacity[$aid]['requested'] . ', max=' . (int)$overCapacity[$aid]['capacity'] . ', déficit=' . (int)$overCapacity[$aid]['deficit'];
        }
        $errors[] = 'El reparto solicitado supera la capacidad máxima de una o más cuentas y no se corrige automáticamente. ' . implode(' | ', $parts);
        return array(false, array(), array(
            'errors' => $errors,
            'warnings' => $warnings,
            'requested_by_account' => $requestedByAccount,
            'capacity_by_account' => $capacityByAccount,
            'deficit_total' => 0,
            'over_capacity_accounts' => $overCapacity,
        ));
    }

    foreach ($productCounts as $productId => $required) {
        $required = max(0, (int)$required);
        $rowTotal = 0;
        foreach ($accountIds as $aid) $rowTotal += (int)$targetMatrix[$productId][$aid];
        $diff = $required - $rowTotal;
        if ($diff === 0) continue;

        if (abs($diff) > $maxRowAutofixDelta) {
            $errors[] = 'El producto ' . $productId . ' tiene un descuadre de ' . abs($diff) . ' (solicitado=' . $rowTotal . ', requerido=' . $required . ') y solo se autoajustan descuadres pequeños (máximo ' . $maxRowAutofixDelta . ').';
            continue;
        }

        if ($diff > 0) {
            for ($step = 0; $step < $diff; $step++) {
                $bestAid = '';
                $bestScore = -PHP_INT_MAX;
                foreach ($accountIds as $aid) {
                    $desire = (int)$requested[$productId][$aid] - (int)$targetMatrix[$productId][$aid];
                    $balance = -1 * (int)$accountAssigned[$aid];
                    $score = ($desire * 1000000) + ($balance * 1000);
                    if ($score > $bestScore || $bestAid === '') {
                        $bestScore = $score;
                        $bestAid = $aid;
                    }
                }
                if ($bestAid === '') break;
                $targetMatrix[$productId][$bestAid] = (int)$targetMatrix[$productId][$bestAid] + 1;
            }
        } else {
            $toRemove = abs($diff);
            for ($step = 0; $step < $toRemove; $step++) {
                $bestAid = '';
                $bestScore = -PHP_INT_MAX;
                foreach ($accountIds as $aid) {
                    $current = (int)$targetMatrix[$productId][$aid];
                    if ($current <= 0) continue;
                    $excess = $current - (int)$requested[$productId][$aid];
                    $score = ($excess * 1000000) + ($current * 1000);
                    if ($score > $bestScore || $bestAid === '') {
                        $bestScore = $score;
                        $bestAid = $aid;
                    }
                }
                if ($bestAid === '') break;
                $targetMatrix[$productId][$bestAid] = max(0, (int)$targetMatrix[$productId][$bestAid] - 1);
            }
        }

        $warnings[] = 'Autoajuste menor aplicado en producto ' . $productId . ': diferencia corregida de ' . abs($diff) . '.';
    }

    if (!empty($errors)) {
        return array(false, array(), array(
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
            'requested_by_account' => $requestedByAccount,
            'capacity_by_account' => $capacityByAccount,
            'deficit_total' => 0,
            'over_capacity_accounts' => $overCapacity,
        ));
    }

    $accountAssigned = array_fill_keys($accountIds, 0);
    foreach ($productCounts as $productId => $_required) {
        foreach ($accountIds as $aid) {
            $accountAssigned[$aid] += (int)($targetMatrix[$productId][$aid] ?? 0);
        }
    }

    $moveCount = 0;
    foreach ($accountIds as $fromAid) {
        $capacity = max(0, (int)($capacityByAccount[$fromAid] ?? 0));
        while ((int)$accountAssigned[$fromAid] > $capacity) {
            $bestMove = null;
            foreach (array_keys($productCounts) as $productId) {
                $fromCount = (int)($targetMatrix[$productId][$fromAid] ?? 0);
                if ($fromCount <= 0) continue;
                foreach ($accountIds as $toAid) {
                    if ($toAid === $fromAid) continue;
                    $free = max(0, (int)($capacityByAccount[$toAid] ?? 0) - (int)$accountAssigned[$toAid]);
                    if ($free <= 0) continue;
                    $removePenalty = max(0, (int)$requested[$productId][$fromAid] - ($fromCount - 1));
                    $toCount = (int)($targetMatrix[$productId][$toAid] ?? 0);
                    $addPenalty = max(0, ($toCount + 1) - (int)$requested[$productId][$toAid]);
                    $penalty = $removePenalty + $addPenalty;
                    $candidate = array(
                        'product_id' => $productId,
                        'from' => $fromAid,
                        'to' => $toAid,
                        'penalty' => $penalty,
                        'free' => $free,
                    );
                    if ($bestMove === null
                        || $candidate['penalty'] < $bestMove['penalty']
                        || ($candidate['penalty'] === $bestMove['penalty'] && $candidate['free'] > $bestMove['free'])
                        || ($candidate['penalty'] === $bestMove['penalty'] && $candidate['free'] === $bestMove['free'] && strcmp((string)$candidate['to'], (string)$bestMove['to']) < 0)
                    ) {
                        $bestMove = $candidate;
                    }
                }
            }

            if ($bestMove === null) {
                $errors[] = 'No se pudo equilibrar automáticamente el reparto por capacidad de cuentas.';
                return array(false, array(), array(
                    'errors' => $errors,
                    'warnings' => $warnings,
                    'requested_by_account' => $requestedByAccount,
                    'capacity_by_account' => $capacityByAccount,
                    'deficit_total' => 0,
                    'over_capacity_accounts' => $overCapacity,
                ));
            }

            $p = $bestMove['product_id'];
            $from = $bestMove['from'];
            $to = $bestMove['to'];
            $targetMatrix[$p][$from] = max(0, (int)$targetMatrix[$p][$from] - 1);
            $targetMatrix[$p][$to] = (int)$targetMatrix[$p][$to] + 1;
            $accountAssigned[$from]--;
            $accountAssigned[$to]++;
            $moveCount++;
        }
    }

    foreach ($productCounts as $productId => $required) {
        $rowTotal = 0;
        foreach ($accountIds as $aid) $rowTotal += (int)($targetMatrix[$productId][$aid] ?? 0);
        if ($rowTotal !== (int)$required) {
            $errors[] = 'No se pudo cuadrar automáticamente el producto ' . $productId . ': requiere ' . (int)$required . ' y quedó en ' . $rowTotal . '.';
        }
    }
    if (!empty($errors)) {
        return array(false, array(), array(
            'errors' => array_values(array_unique($errors)),
            'warnings' => $warnings,
            'requested_by_account' => $requestedByAccount,
            'capacity_by_account' => $capacityByAccount,
            'deficit_total' => 0,
            'over_capacity_accounts' => $overCapacity,
        ));
    }

    $requestedTotal = 0;
    foreach ($requested as $row) {
        foreach ($row as $count) $requestedTotal += (int)$count;
    }
    if ($requestedTotal !== $totalRequired) {
        $warnings[] = 'El reparto enviado no cuadraba (' . $requestedTotal . ' frente a ' . $totalRequired . ') y se ha autoajustado equilibrando cuentas/perfiles.';
    }
    if ($moveCount > 0) {
        $warnings[] = 'Autoajuste por capacidad aplicado: ' . $moveCount . ' movimientos entre cuentas.';
    }

    return array(true, $targetMatrix, array(
        'warnings' => array_values(array_unique($warnings)),
        'requested_total' => $requestedTotal,
        'required_total' => $totalRequired,
        'capacity_total' => $totalCapacity,
        'move_count' => $moveCount,
        'requested_by_account' => $requestedByAccount,
        'capacity_by_account' => $capacityByAccount,
        'deficit_total' => 0,
        'over_capacity_accounts' => array(),
        'max_row_autofix_delta' => $maxRowAutofixDelta,
    ));
}

function publicista_campaign_generate_items($campaign) {
    $campaign = publicista_campaign_normalize($campaign);
    list($ok, $errors, $warnings, $planning, $readyProducts, $validAccounts, $selectedSlotsMeta) = publicista_campaign_validate_for_generation($campaign);
    if (!$ok) {
        return array(false, $campaign, array(), array('errors' => $errors, 'warnings' => $warnings));
    }

    usort($validAccounts, function($a, $b) {
        $pa = (int)($a['priority_weight'] ?? 100);
        $pb = (int)($b['priority_weight'] ?? 100);
        if ($pa === $pb) return strcmp((string)($a['login_user'] ?? ''), (string)($b['login_user'] ?? ''));
        return $pb <=> $pa;
    });

    $requiredProducts = max(1, (int)($planning['num_products_target'] ?? 1));
    $selectedProducts = array_values($readyProducts);
    if (count($selectedProducts) !== $requiredProducts) {
        return array(false, $campaign, array(), array('errors' => array('No hay productos listos seleccionables para generar la campaña.'), 'warnings' => $warnings));
    }

    $planProfiles = publicista_campaign_flatten_planning_profiles($planning, trim((string)($campaign['strategy_option_code'] ?? '')));
    if (empty($planProfiles)) {
        for ($i = 0; $i < max(1, count($selectedProducts)); $i++) {
            $planProfiles[] = array('girl' => $i + 1, 'num' => $i + 1, 'name' => 'Perfil ' . ($i + 1), 'opts' => array('free' => array('10:00','22:00')), 'why' => 'Fallback sin strategy snapshot', 'cost' => 0.0, 'free_slots' => array('10:00','22:00'));
        }
        $warnings[] = 'El planning no tenía perfiles detallados. Se ha generado una composición mínima de fallback.';
    }
    list($planProfiles, $adjustWarnings, $adjustErrors) = publicista_campaign_adjust_profiles_to_selected_slots($planProfiles, (int)($selectedSlotsMeta['selected_count'] ?? 0));
    $warnings = array_merge($warnings, $adjustWarnings);
    if (!empty($adjustErrors)) {
        return array(false, $campaign, array(), array('errors' => $adjustErrors, 'warnings' => $warnings));
    }

    $savedItems = array();
    $productSnapshots = array();
    $accountSnapshots = array();
    foreach (publicista_campaign_items_for_campaign($campaign['id']) as $oldItem) {
        publicista_campaign_item_delete($oldItem['id'] ?? '');
    }

    $slotPool = publicista_campaign_order_selected_slots((array)($selectedSlotsMeta['slots'] ?? array()), $validAccounts, (string)($campaign['id'] ?? ''));
    if (count($slotPool) < count($planProfiles)) {
        return array(false, $campaign, array(), array('errors' => array('No hay suficientes IDs internos de anuncio seleccionados para generar todos los items de la campaña.'), 'warnings' => $warnings));
    }

    $productVariantUsage = array();
    foreach (array_values($planProfiles) as $idx => $profileRow) {
        $girlIndex = max(1, (int)($profileRow['girl'] ?? 1)) - 1;
        $product = $selectedProducts[$girlIndex % count($selectedProducts)];
        $slot = $slotPool[$idx] ?? null;
        if (!$slot || empty($slot['account'])) {
            return array(false, $campaign, $savedItems, array('errors' => array('No se pudo asignar un anuncio interno disponible a todos los perfiles del planning.'), 'warnings' => $warnings));
        }
        $account = $slot['account'];
        $productIdForVariant = trim((string)($product['id'] ?? ('product_' . $girlIndex)));
        $variantIndex = (int)($productVariantUsage[$productIdForVariant] ?? 0);
        $productVariantUsage[$productIdForVariant] = $variantIndex + 1;
        $copyVariant = publicista_campaign_pick_copy_variant($product, $variantIndex);
        $images = publicista_campaign_pick_images($product, 4);
        $item = publicista_campaign_item_defaults();
        $item['campaign_id'] = $campaign['id'];
        $item['estado'] = 'ready';
        $item['portal_code'] = trim((string)($planning['portal_code'] ?? 'destacamos'));
        $item['product_job_id'] = trim((string)($product['id'] ?? ''));
        $item['product_snapshot'] = publicista_snapshot_from_entity('product', $product);
        $item['account_id'] = trim((string)($account['id'] ?? ''));
        $item['account_snapshot'] = publicista_snapshot_from_entity('account', $account);
        $item['copy_variant_id'] = trim((string)($copyVariant['variant_id'] ?? ''));
        $item['copy_snapshot'] = $copyVariant;
        $item['image_ids'] = array_map(function($img) { return trim((string)($img['id'] ?? ($img['filename'] ?? $img['path_rel'] ?? ''))); }, $images);
        $item['image_snapshot'] = $images;
        $item['publish_mode'] = publicista_campaign_profile_publish_mode($profileRow);
        $item['planning_profile_snapshot'] = $profileRow;
        $item['external_ad_id'] = trim((string)($slot['listing_id'] ?? ''));
        $item['created_at'] = now_datetime();
        $item['updated_at'] = $item['created_at'];
        list($_ok, $savedItem) = publicista_campaign_item_save($item);
        $savedItems[] = $savedItem;
        $productId = trim((string)($product['id'] ?? ''));
        $accountId = trim((string)($account['id'] ?? ''));
        if ($productId !== '' && !isset($productSnapshots[$productId])) {
            $productSnapshots[$productId] = publicista_snapshot_from_entity('product', $product);
        }
        if ($accountId !== '' && !isset($accountSnapshots[$accountId])) {
            $accountSnapshots[$accountId] = publicista_snapshot_from_entity('account', $account);
        }
    }

    $campaign['planning_snapshot'] = publicista_snapshot_from_entity('planning', $planning);
    $campaign['products_snapshot'] = array_values($productSnapshots);
    $campaign['accounts_snapshot'] = array_values($accountSnapshots);
    $campaign['composition_plan'] = array(
        'generated_at' => now_datetime(),
        'desired_products' => $requiredProducts,
        'selected_products_count' => count($selectedProducts),
        'plan_profiles_count' => count($planProfiles),
        'selected_listing_refs_count' => count((array)($campaign['selected_listing_refs'] ?? array())),
        'selected_product_ids' => array_map(function($row){ return trim((string)($row['id'] ?? '')); }, $selectedProducts),
        'warnings' => $warnings,
        'validation_errors' => array(),
    );
    $campaign['automation_plan'] = publicista_campaign_compact_automation_plan(publicista_campaign_build_automation_plan($campaign, $planning, $savedItems));
    $campaign['approval_snapshot'] = array(
        'approved_at' => now_datetime(),
        'approved_items_count' => count($savedItems),
        'approved_by' => 'system',
        'approval_mode' => 'auto_after_generation',
    );
    $campaign['recalculation_snapshot'] = array(
        'generated_at' => now_datetime(),
        'desired_products' => $requiredProducts,
        'selected_products_count' => count($selectedProducts),
        'plan_profiles_count' => count($planProfiles),
        'selected_listing_refs_count' => count((array)($campaign['selected_listing_refs'] ?? array())),
        'warnings' => $warnings,
        'validation_errors' => array(),
    );
    $campaign['execution_summary'] = publicista_campaign_build_generation_summary($campaign, $planning, $savedItems, $warnings, array());
    $campaign['estado'] = 'approved';
    $campaign['updated_at'] = now_datetime();
    list($_ok2, $savedCampaign) = publicista_campaign_save($campaign);

    return array(true, $savedCampaign, $savedItems, array('errors' => array(), 'warnings' => $warnings));
}

function publicista_campaign_items_get() {
    $rows = storage_read('publicista_campaign_items.json');
    $out = array();
    foreach ($rows as $row) {
        $out[] = publicista_campaign_item_normalize($row);
    }
    return sort_desc_by_key($out, 'updated_at');
}

function publicista_campaign_item_get($id) {
    foreach (publicista_campaign_items_get() as $row) {
        if (($row['id'] ?? '') === $id) return $row;
    }
    return null;
}

function publicista_campaign_item_save($row) {
    $normalized = publicista_campaign_item_normalize($row);
    $normalized['updated_at'] = now_datetime();
    storage_upsert('publicista_campaign_items.json', $normalized);
    return array(true, $normalized);
}

function publicista_campaign_item_delete($id) {
    storage_delete('publicista_campaign_items.json', $id);
}

function publicista_tasks_get() {
    $rows = storage_read('publicista_tasks.json');
    $out = array();
    foreach ($rows as $row) {
        $out[] = publicista_task_normalize($row);
    }
    return sort_desc_by_key($out, 'updated_at');
}

function publicista_task_get($id) {
    foreach (publicista_tasks_get() as $row) {
        if (($row['id'] ?? '') === $id) return $row;
    }
    return null;
}

function publicista_task_save($row) {
    $normalized = publicista_task_normalize($row);
    $normalized['updated_at'] = now_datetime();
    storage_upsert('publicista_tasks.json', $normalized);
    return array(true, $normalized);
}

function publicista_task_delete($id) {
    storage_delete('publicista_tasks.json', $id);
}

function publicista_runs_get() {
    $rows = storage_read('publicista_runs.json');
    $out = array();
    foreach ($rows as $row) {
        $out[] = publicista_run_normalize($row);
    }
    return sort_desc_by_key($out, 'updated_at');
}

function publicista_run_get($id) {
    foreach (publicista_runs_get() as $row) {
        if (($row['id'] ?? '') === $id) return $row;
    }
    return null;
}

function publicista_run_save($row) {
    $normalized = publicista_run_normalize($row);
    $normalized['updated_at'] = now_datetime();
    storage_upsert('publicista_runs.json', $normalized);
    return array(true, $normalized);
}

function publicista_run_delete($id) {
    storage_delete('publicista_runs.json', $id);
}

function publicista_campaign_running_run($campaignId, $staleSeconds = 21600) {
    $campaignId = trim((string)$campaignId);
    $staleSeconds = max(60, (int)$staleSeconds);
    $threshold = time() - $staleSeconds;
    foreach (publicista_runs_for_campaign($campaignId) as $run) {
        if (!in_array(($run['estado'] ?? ''), array('pending', 'running'), true)) continue;
        $updatedTs = strtotime((string)($run['updated_at'] ?? ($run['started_at'] ?? '')));
        if ($updatedTs !== false && $updatedTs >= $threshold) {
            return $run;
        }
    }
    return null;
}

function publicista_campaign_recover_stuck_run($campaignId, $options = array()) {
    $campaignId = trim((string)$campaignId);
    if ($campaignId === '') {
        return array(false, null, null, array('error' => 'Campaign ID vacío.'));
    }

    $options = is_array($options) ? $options : array();
    $staleSeconds = max(600, (int)($options['stale_seconds'] ?? 900));
    $threshold = time() - $staleSeconds;

    $staleRun = null;
    foreach (publicista_runs_for_campaign($campaignId) as $run) {
        $estado = trim((string)($run['estado'] ?? ''));
        if (!in_array($estado, array('pending', 'running'), true)) continue;

        $updatedTs = strtotime((string)($run['updated_at'] ?? ($run['started_at'] ?? ($run['created_at'] ?? ''))));
        if ($updatedTs === false || $updatedTs >= $threshold) continue;

        $staleRun = $run;
        break;
    }

    if (!$staleRun) {
        return array(false, null, null, array('reason' => 'no_stale_run', 'stale_seconds' => $staleSeconds));
    }

    $now = now_datetime();
    $runId = trim((string)($staleRun['id'] ?? ''));
    $lastHeartbeat = trim((string)($staleRun['updated_at'] ?? ($staleRun['started_at'] ?? ($staleRun['created_at'] ?? ''))));
    $recoverMsg = 'Run recuperado por atasco: sin progreso/heartbeat durante más de ' . $staleSeconds . 's.';

    $staleRun['estado'] = 'failed';
    $staleRun['summary'] = $recoverMsg;
    $staleRun['finished_at'] = $now;
    $staleRun['pipeline'] = array_merge((array)($staleRun['pipeline'] ?? array()), array(
        'status' => 'error',
        'stage' => 'stuck_recovered',
        'summary' => $recoverMsg,
        'last_heartbeat_at' => $lastHeartbeat,
    ));
    $staleRun['updated_at'] = $now;
    list($_okRun, $savedRun) = publicista_run_save($staleRun);

    $campaign = publicista_campaign_get($campaignId);
    if ($campaign) {
        $campaign['estado'] = 'error';
        $campaign['updated_at'] = $now;
        $campaign['execution_summary'] = array_merge((array)($campaign['execution_summary'] ?? array()), array(
            'last_phase' => 'stuck_recovered',
            'last_run_id' => $runId,
            'last_run_status' => 'failed',
            'last_run_error' => $recoverMsg,
            'last_upload_finished_at' => $now,
        ));
        list($_okCampaign, $campaign) = publicista_campaign_save($campaign);
    }

    return array(true, $campaign, $savedRun, array(
        'stale_seconds' => $staleSeconds,
        'last_heartbeat_at' => $lastHeartbeat,
        'reason' => 'stuck_run_recovered',
    ));
}

function publicista_run_stop_requested($run) {
    $run = is_array($run) ? $run : array();
    return trim((string)($run['stop_requested_at'] ?? '')) !== '';
}

function publicista_campaign_request_stop($campaignId, $requestedBy = 'user') {
    $campaignId = trim((string)$campaignId);
    $requestedBy = trim((string)$requestedBy);
    if ($requestedBy === '') $requestedBy = 'user';

    $campaign = $campaignId !== '' ? publicista_campaign_get($campaignId) : null;
    if (!$campaign) {
        return array(false, null, array('error' => 'No se encontró la campaña.'));
    }

    $runningRun = publicista_campaign_running_run($campaignId);
    if (!$runningRun) {
        return array(false, null, array('error' => 'No hay una subida en curso para esta campaña.'));
    }

    $alreadyRequested = publicista_run_stop_requested($runningRun);
    if (!$alreadyRequested) {
        $runningRun['stop_requested_at'] = now_datetime();
        $runningRun['stop_requested_by'] = $requestedBy;
        $runningRun['summary'] = 'Parada solicitada. Finalizando el paso en curso...';
        $runningRun['pipeline'] = array_merge((array)($runningRun['pipeline'] ?? array()), array(
            'status' => 'running',
            'stage' => 'stop_requested',
            'summary' => 'Parada solicitada. El motor cerrará la subida al terminar el paso actual.',
        ));
        $runningRun['updated_at'] = now_datetime();
        list($_okRun, $runningRun) = publicista_run_save($runningRun);
    }

    $campaign['execution_summary'] = array_merge((array)($campaign['execution_summary'] ?? array()), array(
        'last_phase' => 'stop_requested',
        'last_stop_requested_at' => trim((string)($runningRun['stop_requested_at'] ?? now_datetime())),
        'last_stop_requested_by' => trim((string)($runningRun['stop_requested_by'] ?? $requestedBy)),
        'last_run_id' => trim((string)($runningRun['id'] ?? '')),
        'last_run_status' => trim((string)($runningRun['estado'] ?? 'running')),
    ));
    $campaign['updated_at'] = now_datetime();
    publicista_campaign_save($campaign);

    return array(true, $runningRun, array('already_requested' => $alreadyRequested));
}

function publicista_campaign_items_for_campaign($campaignId) {
    $campaignId = trim((string)$campaignId);
    $out = array();
    foreach (storage_read_filtered('publicista_campaign_items.json', function($row) use ($campaignId) {
        return trim((string)($row['campaign_id'] ?? '')) === $campaignId;
    }) as $row) {
        $out[] = publicista_campaign_item_normalize($row);
    }
    return sort_desc_by_key($out, 'updated_at');
}

function publicista_tasks_for_campaign($campaignId) {
    $campaignId = trim((string)$campaignId);
    $out = array();
    foreach (storage_read_filtered('publicista_tasks.json', function($row) use ($campaignId) {
        return trim((string)($row['campaign_id'] ?? '')) === $campaignId;
    }) as $row) {
        $out[] = publicista_task_normalize($row);
    }
    return sort_desc_by_key($out, 'updated_at');
}

function publicista_tasks_for_campaign_item($campaignItemId) {
    $campaignItemId = trim((string)$campaignItemId);
    $out = array();
    foreach (publicista_tasks_get() as $row) {
        if (($row['campaign_item_id'] ?? '') === $campaignItemId) $out[] = $row;
    }
    return $out;
}

function publicista_account_listing_ids($account) {
    $account = publicista_account_normalize($account);
    return array_values((array)($account['portal_listing_ids'] ?? array()));
}

function publicista_campaign_items_by_account($accountId, $excludeCampaignId = '') {
    $accountId = trim((string)$accountId);
    $excludeCampaignId = trim((string)$excludeCampaignId);
    $out = array();
    foreach (publicista_campaign_items_get() as $item) {
        if (($item['account_id'] ?? '') !== $accountId) continue;
        if ($excludeCampaignId !== '' && ($item['campaign_id'] ?? '') === $excludeCampaignId) continue;
        if (in_array(($item['estado'] ?? ''), array('cancelled'), true)) continue;
        $out[] = $item;
    }
    return $out;
}

function publicista_account_listing_usage($accountId, $account = null, $excludeCampaignId = '') {
    $accountId = trim((string)$accountId);
    if (!$account) $account = publicista_account_get($accountId);
    $account = publicista_account_normalize($account ?: array('id' => $accountId));
    $listingIds = publicista_account_listing_ids($account);
    $assigned = array();
    foreach (publicista_campaign_items_by_account($accountId, $excludeCampaignId) as $item) {
        $listingId = trim((string)($item['external_ad_id'] ?? ''));
        if ($listingId === '') continue;
        if (!isset($assigned[$listingId])) $assigned[$listingId] = array();
        $assigned[$listingId][] = array(
            'campaign_item_id' => $item['id'] ?? '',
            'campaign_id' => $item['campaign_id'] ?? '',
            'estado' => $item['estado'] ?? '',
            'product_job_id' => $item['product_job_id'] ?? '',
        );
    }
    return array(
        'listing_ids' => $listingIds,
        'total' => count($listingIds),
        'assigned_map' => $assigned,
        'assigned_count' => count($assigned),
        'available_ids' => array_values($listingIds),
        'available_count' => count($listingIds),
    );
}

function publicista_account_pick_available_listing_id($account, $excludeCampaignId = '', $reservedIds = array()) {
    $account = publicista_account_normalize($account);
    $usage = publicista_account_listing_usage($account['id'] ?? '', $account, $excludeCampaignId);
    $reserved = array();
    foreach ((array)$reservedIds as $rid) {
        $rid = trim((string)$rid);
        if ($rid !== '') $reserved[$rid] = true;
    }
    foreach ((array)($usage['listing_ids'] ?? array()) as $listingId) {
        if (!isset($reserved[$listingId])) return $listingId;
    }
    return '';
}

function publicista_account_runtime_metrics_defaults($account = array()) {
    $account = publicista_account_normalize(is_array($account) ? $account : array());
    $listingIds = publicista_account_listing_ids($account);
    return array(
        'linked_phones' => array(),
        'linked_phones_count' => 0,
        'campaign_items_count' => 0,
        'published_ads_count' => 0,
        'active_ads_count' => 0,
        'tasks_count' => 0,
        'listing_ids' => $listingIds,
        'listing_ids_total' => count($listingIds),
        'listing_ids_assigned_count' => 0,
        'listing_ids_available' => array_values($listingIds),
        'listing_ids_available_count' => count($listingIds),
        'listing_assignments' => array(),
    );
}

function publicista_account_runtime_metrics_apply($account, $metrics) {
    $account = publicista_account_normalize($account);
    $metrics = is_array($metrics) ? $metrics : array();
    $account['active_ads_count'] = max((int)($account['active_ads_count'] ?? 0), (int)($metrics['active_ads_count'] ?? 0));
    $account['published_ads_count'] = max((int)($account['published_ads_count'] ?? 0), (int)($metrics['published_ads_count'] ?? 0));
    $account['free_bump_tasks_count'] = max((int)($account['free_bump_tasks_count'] ?? 0), (int)($metrics['tasks_count'] ?? 0));
    $account['listing_slot_count'] = max((int)($account['listing_slot_count'] ?? 0), (int)($metrics['listing_ids_total'] ?? 0));
    $account['_runtime_metrics'] = $metrics;
    return $account;
}

function publicista_account_runtime_metrics_batch($accountIds, $accountsById = array()) {
    $metricsById = array();
    $normalizedAccountsById = array();
    $normalizedIds = array();

    foreach ((array)$accountsById as $key => $account) {
        $normalized = publicista_account_normalize(is_array($account) ? $account : array('id' => $key));
        $normalizedId = trim((string)($normalized['id'] ?? $key));
        if ($normalizedId === '') {
            continue;
        }
        $normalizedAccountsById[$normalizedId] = $normalized;
    }

    foreach ((array)$accountIds as $accountId) {
        $accountId = trim((string)$accountId);
        if ($accountId === '' || isset($metricsById[$accountId])) {
            continue;
        }
        if (!isset($normalizedAccountsById[$accountId])) {
            $normalizedAccountsById[$accountId] = publicista_account_get($accountId, false) ?: array('id' => $accountId);
            $normalizedAccountsById[$accountId] = publicista_account_normalize($normalizedAccountsById[$accountId]);
        }
        $metricsById[$accountId] = publicista_account_runtime_metrics_defaults($normalizedAccountsById[$accountId]);
        $normalizedIds[$accountId] = true;
    }

    if (empty($metricsById)) {
        return array();
    }

    foreach (storage_read('telefonos.json') as $tel) {
        $accountId = trim((string)($tel['destacamos_id'] ?? ''));
        if ($accountId === '' || !isset($metricsById[$accountId])) {
            continue;
        }
        $metricsById[$accountId]['linked_phones'][] = $tel;
        $metricsById[$accountId]['linked_phones_count']++;
    }

    foreach (publicista_campaign_items_get() as $item) {
        $accountId = trim((string)($item['account_id'] ?? ''));
        if ($accountId === '' || !isset($metricsById[$accountId])) {
            continue;
        }
        $metricsById[$accountId]['campaign_items_count']++;
        if (($item['estado'] ?? '') === 'published') {
            $metricsById[$accountId]['published_ads_count']++;
        }
        if (in_array(($item['estado'] ?? ''), array('ready','queued','published'), true)) {
            $metricsById[$accountId]['active_ads_count']++;
        }
        $listingId = trim((string)($item['external_ad_id'] ?? ''));
        if ($listingId !== '') {
            if (!isset($metricsById[$accountId]['listing_assignments'][$listingId])) {
                $metricsById[$accountId]['listing_assignments'][$listingId] = array();
            }
            $metricsById[$accountId]['listing_assignments'][$listingId][] = array(
                'campaign_item_id' => $item['id'] ?? '',
                'campaign_id' => $item['campaign_id'] ?? '',
                'estado' => $item['estado'] ?? '',
                'product_job_id' => $item['product_job_id'] ?? '',
            );
        }
    }

    foreach (publicista_tasks_get() as $task) {
        $accountId = trim((string)($task['account_id'] ?? ''));
        if ($accountId === '' || !isset($metricsById[$accountId])) {
            continue;
        }
        $metricsById[$accountId]['tasks_count']++;
    }

    foreach ($metricsById as $accountId => $metrics) {
        $account = $normalizedAccountsById[$accountId] ?? array('id' => $accountId);
        $listingIds = publicista_account_listing_ids($account);
        $metricsById[$accountId]['listing_ids'] = $listingIds;
        $metricsById[$accountId]['listing_ids_total'] = count($listingIds);
        $metricsById[$accountId]['listing_ids_assigned_count'] = count((array)($metrics['listing_assignments'] ?? array()));
        $metricsById[$accountId]['listing_ids_available'] = array_values($listingIds);
        $metricsById[$accountId]['listing_ids_available_count'] = count($listingIds);
    }

    return $metricsById;
}

function publicista_account_runtime_metrics($accountId, $accountsById = array()) {
    $accountId = trim((string)$accountId);
    if ($accountId === '') {
        return publicista_account_runtime_metrics_defaults();
    }
    $metricsById = publicista_account_runtime_metrics_batch(array($accountId), $accountsById);
    return isset($metricsById[$accountId]) ? $metricsById[$accountId] : publicista_account_runtime_metrics_defaults(array('id' => $accountId));
}

function publicista_account_effective_capacity($account) {
    $account = publicista_account_normalize($account);
    $runtime = publicista_account_runtime_metrics($account['id']);
    $listingSlots = max(0, (int)($account['listing_slot_count'] ?? 0));
    $manualLimit = max(0, (int)($account['max_active_ads'] ?? 0));
    $limit = $listingSlots > 0 ? $listingSlots : $manualLimit;
    $active = max((int)($account['active_ads_count'] ?? 0), (int)($runtime['active_ads_count'] ?? 0));
    $availableFromIds = (int)($runtime['listing_ids_total'] ?? 0);
    $available = $listingSlots > 0 ? $availableFromIds : $manualLimit;
    return array(
        'limit' => $limit,
        'active' => $active,
        'available' => $available,
        'is_unlimited' => $limit <= 0,
        'is_saturated' => false,
        'source' => $listingSlots > 0 ? 'listing_ids' : 'manual_limit',
        'listing_slots_total' => $listingSlots,
        'listing_slots_available' => $availableFromIds,
    );
}

function publicista_account_hydrate_metrics($account) {
    $account = publicista_account_normalize($account);
    $accountId = trim((string)($account['id'] ?? ''));
    $metrics = publicista_account_runtime_metrics($accountId, $accountId !== '' ? array($accountId => $account) : array());
    return publicista_account_runtime_metrics_apply($account, $metrics);
}

function publicista_account_can_delete($id, $metrics = null) {
    if (!is_array($metrics)) {
        $metrics = publicista_account_runtime_metrics($id);
    }
    $errors = array();
    if (!empty($metrics['linked_phones_count'])) $errors[] = 'Tiene teléfonos vinculados.';
    if (!empty($metrics['campaign_items_count'])) $errors[] = 'Tiene anuncios/campaign items vinculados.';
    if (!empty($metrics['tasks_count'])) $errors[] = 'Tiene tareas automáticas vinculadas.';
    return array(empty($errors), $errors, $metrics);
}

function publicista_free_bump_clear_cache() {
    unset($GLOBALS['publicista_free_bump_config_cache'], $GLOBALS['publicista_free_bump_state_cache']);
}

function publicista_free_bump_normalize_hhmm($value, $default = '08:00') {
    $value = trim((string)$value);
    if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value)) {
        return $value;
    }
    return $default;
}

function publicista_free_bump_config_defaults() {
    return array(
        'enabled' => 0,
        'groups' => array(),  // group_name => {enabled, start_time, end_time}
        'allow_any_listing' => 1,
        'humanize' => 1,
        'anticipation_minutes' => 8,
        'interval_min_minutes' => 12,
        'interval_max_minutes' => 120,
        'retry_empty_min_minutes' => 10,
        'retry_empty_max_minutes' => 22,
        'jitter_min_seconds' => 30,
        'jitter_max_seconds' => 180,
        'updated_at' => '',
    );
}

function publicista_free_bump_config_normalize($row) {
    $row = is_array($row) ? $row : array();
    $cfg = array_merge(publicista_free_bump_config_defaults(), $row);
    $cfg['enabled'] = !empty($cfg['enabled']) ? 1 : 0;
    $cfg['allow_any_listing'] = !array_key_exists('allow_any_listing', $cfg) || !empty($cfg['allow_any_listing']) ? 1 : 0;
    $cfg['humanize'] = !empty($cfg['humanize']) ? 1 : 0;
    $cfg['anticipation_minutes'] = max(0, min(120, (int)($cfg['anticipation_minutes'] ?? 8)));
    $cfg['interval_min_minutes'] = max(1, min(240, (int)($cfg['interval_min_minutes'] ?? 12)));
    $cfg['interval_max_minutes'] = max($cfg['interval_min_minutes'], min(720, (int)($cfg['interval_max_minutes'] ?? 120)));
    $cfg['retry_empty_min_minutes'] = max(1, min(240, (int)($cfg['retry_empty_min_minutes'] ?? 10)));
    $cfg['retry_empty_max_minutes'] = max($cfg['retry_empty_min_minutes'], min(720, (int)($cfg['retry_empty_max_minutes'] ?? 22)));
    $cfg['jitter_min_seconds'] = max(0, min(1800, (int)($cfg['jitter_min_seconds'] ?? 30)));
    $cfg['jitter_max_seconds'] = max($cfg['jitter_min_seconds'], min(3600, (int)($cfg['jitter_max_seconds'] ?? 180)));
    $groups = array();
    foreach ((array)($cfg['groups'] ?? array()) as $groupName => $groupData) {
        $groupName = trim((string)$groupName);
        if ($groupName === '') continue;
        $groupData = is_array($groupData) ? $groupData : array();
        $groups[$groupName] = array(
            'enabled' => !empty($groupData['enabled']) ? 1 : 0,
            'start_time' => publicista_free_bump_normalize_hhmm((string)($groupData['start_time'] ?? '08:00'), '08:00'),
            'end_time' => publicista_free_bump_normalize_hhmm((string)($groupData['end_time'] ?? '23:00'), '23:00'),
        );
    }
    $cfg['groups'] = $groups;
    $cfg['updated_at'] = trim((string)($cfg['updated_at'] ?? ''));
    // Remove legacy keys from old config format
    unset($cfg['start_time'], $cfg['end_time'], $cfg['account_ids']);
    return $cfg;
}

function publicista_free_bump_config() {
    if (isset($GLOBALS['publicista_free_bump_config_cache']) && is_array($GLOBALS['publicista_free_bump_config_cache'])) {
        return $GLOBALS['publicista_free_bump_config_cache'];
    }
    $settings = storage_read('settings.json');
    $cfg = publicista_free_bump_config_normalize($settings['publicista_free_bump_config'] ?? array());
    $GLOBALS['publicista_free_bump_config_cache'] = $cfg;
    return $cfg;
}

function publicista_free_bump_save_config($row) {
    $settings = storage_read('settings.json');
    $cfg = publicista_free_bump_config_normalize($row);
    $cfg['updated_at'] = now_datetime();
    $settings['publicista_free_bump_config'] = $cfg;
    storage_write('settings.json', $settings);
    publicista_free_bump_clear_cache();
    return $cfg;
}

function publicista_free_bump_state_defaults() {
    return array(
        'next_run_at' => '',
        'last_run_at' => '',
        'last_success_at' => '',
        'last_account_id' => '',
        'last_account_label' => '',
        'last_listing_id' => '',
        'last_status' => '',
        'last_error' => '',
        'today_key' => '',
        'today_ok' => 0,
        'today_failed' => 0,
        'today_empty' => 0,
        'updated_at' => '',
    );
}

function publicista_free_bump_state_normalize($row) {
    $row = is_array($row) ? $row : array();
    $state = array_merge(publicista_free_bump_state_defaults(), $row);
    foreach (array('next_run_at','last_run_at','last_success_at','last_account_id','last_account_label','last_listing_id','last_status','last_error','today_key','updated_at') as $key) {
        $state[$key] = trim((string)($state[$key] ?? ''));
    }
    $state['today_ok'] = max(0, (int)($state['today_ok'] ?? 0));
    $state['today_failed'] = max(0, (int)($state['today_failed'] ?? 0));
    $state['today_empty'] = max(0, (int)($state['today_empty'] ?? 0));
    return $state;
}

function publicista_free_bump_state() {
    if (isset($GLOBALS['publicista_free_bump_state_cache']) && is_array($GLOBALS['publicista_free_bump_state_cache'])) {
        return $GLOBALS['publicista_free_bump_state_cache'];
    }
    $settings = storage_read('settings.json');
    $state = publicista_free_bump_state_normalize($settings['publicista_free_bump_state'] ?? array());
    $GLOBALS['publicista_free_bump_state_cache'] = $state;
    return $state;
}

function publicista_free_bump_save_state($row) {
    $settings = storage_read('settings.json');
    $state = publicista_free_bump_state_normalize($row);
    $state['updated_at'] = now_datetime();
    $settings['publicista_free_bump_state'] = $state;
    storage_write('settings.json', $settings);
    publicista_free_bump_clear_cache();
    return $state;
}

function publicista_free_bump_state_prepare_today($state, $dayKey = '') {
    $state = publicista_free_bump_state_normalize($state);
    $dayKey = trim((string)$dayKey);
    if ($dayKey === '') $dayKey = date('Y-m-d');
    if ($state['today_key'] !== $dayKey) {
        $state['today_key'] = $dayKey;
        $state['today_ok'] = 0;
        $state['today_failed'] = 0;
        $state['today_empty'] = 0;
    }
    return $state;
}

function publicista_free_bump_logs_path() {
    $dir = publicista_root_dir();
    publicista_ensure_dir($dir);
    return $dir . '/free_bump_logs.ndjson';
}

function publicista_free_bump_log_append($row) {
    $row = is_array($row) ? $row : array();
    $row['created_at'] = trim((string)($row['created_at'] ?? now_datetime()));
    $json = storage_json_encode($row, false);
    return @file_put_contents(publicista_free_bump_logs_path(), $json . "\n", FILE_APPEND | LOCK_EX) !== false;
}

function publicista_campaign_repair_duplicate_drafts() {
    $campaigns = publicista_campaigns_get();
    if (empty($campaigns)) {
        return array('deleted_campaign_ids' => array(), 'deleted_item_ids' => array());
    }

    $keepersBySignature = array();
    foreach ($campaigns as $campaign) {
        $productIds = array_values(array_filter(array_map('trim', (array)($campaign['product_ids'] ?? array()))));
        sort($productIds);
        $accountIds = array_values(array_filter(array_map('trim', (array)($campaign['account_ids'] ?? array()))));
        sort($accountIds);
        $listingRefs = array_values(array_filter(array_map('trim', (array)($campaign['selected_listing_refs'] ?? array()))));
        sort($listingRefs);
        $signature = sha1(storage_json_encode(array(
            'nombre' => trim((string)($campaign['nombre'] ?? '')),
            'planning_id' => trim((string)($campaign['planning_id'] ?? '')),
            'strategy_option_code' => trim((string)($campaign['strategy_option_code'] ?? '')),
            'product_ids' => $productIds,
            'account_ids' => $accountIds,
            'selected_listing_refs' => $listingRefs,
        ), false));
        if (!isset($keepersBySignature[$signature])) {
            $keepersBySignature[$signature] = array();
        }
        $keepersBySignature[$signature][] = $campaign;
    }

    $deleteCampaignIds = array();
    foreach ($keepersBySignature as $group) {
        if (count($group) < 2) continue;
        $complete = array_values(array_filter($group, function($campaign) {
            $status = trim((string)($campaign['estado'] ?? 'draft'));
            $snapshot = is_array($campaign['planning_snapshot']['data'] ?? null) ? $campaign['planning_snapshot']['data'] : array();
            return $status !== 'draft' && !empty($snapshot);
        }));
        if (empty($complete)) continue;
        foreach ($group as $campaign) {
            $status = trim((string)($campaign['estado'] ?? 'draft'));
            $snapshot = is_array($campaign['planning_snapshot']['data'] ?? null) ? $campaign['planning_snapshot']['data'] : array();
            if ($status === 'draft' && empty($snapshot)) {
                $deleteCampaignIds[trim((string)($campaign['id'] ?? ''))] = trim((string)($campaign['id'] ?? ''));
            }
        }
    }

    if (empty($deleteCampaignIds)) {
        return array('deleted_campaign_ids' => array(), 'deleted_item_ids' => array());
    }

    $keptCampaigns = array();
    foreach ($campaigns as $campaign) {
        $campaignId = trim((string)($campaign['id'] ?? ''));
        if ($campaignId === '' || isset($deleteCampaignIds[$campaignId])) continue;
        $keptCampaigns[] = $campaign;
    }

    $items = publicista_campaign_items_get();
    $deletedItemIds = array();
    $keptItems = array();
    foreach ($items as $item) {
        $campaignId = trim((string)($item['campaign_id'] ?? ''));
        if ($campaignId !== '' && isset($deleteCampaignIds[$campaignId])) {
            $deletedItemIds[] = trim((string)($item['id'] ?? ''));
            continue;
        }
        $keptItems[] = $item;
    }

    storage_write('publicista_campaigns.json', array_values($keptCampaigns));
    storage_write('publicista_campaign_items.json', array_values($keptItems));

    return array(
        'deleted_campaign_ids' => array_values($deleteCampaignIds),
        'deleted_item_ids' => array_values($deletedItemIds),
    );
}

function publicista_campaign_compact_storage_data($repairDuplicates = false) {
    $repair = array('deleted_campaign_ids' => array(), 'deleted_item_ids' => array());
    if ($repairDuplicates) {
        $repair = publicista_campaign_repair_duplicate_drafts();
    }

    $campaigns = publicista_campaigns_get();
    $compactedCampaigns = array();
    foreach ($campaigns as $campaign) {
        $campaign = publicista_campaign_normalize($campaign);
        $planningData = is_array($campaign['planning_snapshot']['data'] ?? null) ? $campaign['planning_snapshot']['data'] : array();
        if (trim((string)($campaign['planning_id'] ?? '')) !== '') {
            $planningData = publicista_planning_get($campaign['planning_id']) ?: $planningData;
        }
        $campaign['planning_snapshot'] = publicista_snapshot_from_entity('planning', $planningData);

        $productSnapshots = array();
        foreach ((array)($campaign['products_snapshot'] ?? array()) as $snapshot) {
            $productData = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : array();
            $productId = trim((string)($productData['id'] ?? ''));
            if ($productId !== '') {
                $productData = publicista_product_get($productId) ?: $productData;
            }
            $key = trim((string)($productData['id'] ?? ''));
            if ($key === '') {
                $key = sha1(storage_json_encode($productData, false));
            }
            $productSnapshots[$key] = publicista_snapshot_from_entity('product', $productData);
        }
        $campaign['products_snapshot'] = array_values($productSnapshots);

        $accountSnapshots = array();
        foreach ((array)($campaign['accounts_snapshot'] ?? array()) as $snapshot) {
            $accountData = is_array($snapshot['data'] ?? null) ? $snapshot['data'] : array();
            $accountId = trim((string)($accountData['id'] ?? ''));
            if ($accountId !== '') {
                $accountData = publicista_account_get($accountId, false) ?: $accountData;
            }
            $key = trim((string)($accountData['id'] ?? ''));
            if ($key === '') {
                $key = sha1(storage_json_encode($accountData, false));
            }
            $accountSnapshots[$key] = publicista_snapshot_from_entity('account', $accountData);
        }
        $campaign['accounts_snapshot'] = array_values($accountSnapshots);
        $campaign['automation_plan'] = publicista_campaign_compact_automation_plan($campaign['automation_plan'] ?? array());
        $compactedCampaigns[] = $campaign;
    }

    $items = publicista_campaign_items_get();
    $existingCampaignIds = array();
    foreach ($compactedCampaigns as $campaign) {
        $campaignId = trim((string)($campaign['id'] ?? ''));
        if ($campaignId !== '') $existingCampaignIds[$campaignId] = true;
    }
    $compactedItems = array();
    foreach ($items as $item) {
        $item = publicista_campaign_item_normalize($item);
        $campaignId = trim((string)($item['campaign_id'] ?? ''));
        if ($campaignId === '' || !isset($existingCampaignIds[$campaignId])) {
            continue;
        }

        $productData = is_array($item['product_snapshot']['data'] ?? null) ? $item['product_snapshot']['data'] : array();
        $productId = trim((string)($item['product_job_id'] ?? ($productData['id'] ?? '')));
        if ($productId !== '') {
            $productData = publicista_product_get($productId) ?: $productData;
        }
        $item['product_snapshot'] = publicista_snapshot_from_entity('product', $productData);

        $accountData = is_array($item['account_snapshot']['data'] ?? null) ? $item['account_snapshot']['data'] : array();
        $accountId = trim((string)($item['account_id'] ?? ($accountData['id'] ?? '')));
        if ($accountId !== '') {
            $accountData = publicista_account_get($accountId, false) ?: $accountData;
        }
        $item['account_snapshot'] = publicista_snapshot_from_entity('account', $accountData);
        $item['copy_snapshot'] = publicista_campaign_compact_copy_snapshot($item['copy_snapshot'] ?? array());
        $item['image_snapshot'] = publicista_campaign_compact_image_snapshot($item['image_snapshot'] ?? array());
        $item['planning_profile_snapshot'] = publicista_campaign_compact_planning_profile_snapshot($item['planning_profile_snapshot'] ?? array());
        $compactedItems[] = $item;
    }

    storage_write('publicista_campaigns.json', array_values($compactedCampaigns));
    storage_write('publicista_campaign_items.json', array_values($compactedItems));

    return array(
        'campaigns_compacted' => count($compactedCampaigns),
        'items_compacted' => count($compactedItems),
        'deleted_campaign_ids' => array_values((array)($repair['deleted_campaign_ids'] ?? array())),
        'deleted_item_ids' => array_values((array)($repair['deleted_item_ids'] ?? array())),
    );
}

function publicista_campaign_build_human_report($campaign, $run, $meta) {
    $campaign = is_array($campaign) ? $campaign : array();
    $run = is_array($run) ? $run : array();
    $meta = is_array($meta) ? $meta : array();

    $campaignName = trim((string)($campaign['nombre'] ?? ''));
    if ($campaignName === '') $campaignName = 'Campaña';

    $published = max(0, (int)($meta['published'] ?? 0));
    $failed = max(0, (int)($meta['failed'] ?? 0));
    $lines = array();
    $lines[] = 'Campaña: ' . $campaignName . '.';
    $lines[] = 'Resultado global: ' . $published . ' anuncios subidos y ' . $failed . ' fallidos.';

    $successLines = array();
    $failureLines = array();
    $softMismatchLines = array();
    foreach ((array)($meta['results'] ?? array()) as $row) {
        $result = is_array($row['result'] ?? null) ? $row['result'] : array();
        $listingId = trim((string)($result['payload_summary']['listingId'] ?? ($result['listingId'] ?? '')));
        $title = trim((string)($result['payload_summary']['title'] ?? ''));
        $currentUrl = trim((string)($result['currentUrl'] ?? ''));
        $label = $title !== '' ? $title : ($listingId !== '' ? ('listing ' . $listingId) : trim((string)($row['campaign_item_id'] ?? 'item')));

        $softMismatch = is_array($result['save_soft_mismatch'] ?? null) ? $result['save_soft_mismatch'] : array();
        if (!empty($softMismatch)) {
            $softLine = 'Aviso guardado: ' . $label . ' · Diferencia normalizada en description';
            $softCount = max(1, (int)($softMismatch['mismatch_count'] ?? count((array)($softMismatch['mismatches'] ?? array()))));
            $softLine .= ' · diferencias: ' . $softCount;
            if ($listingId !== '') $softLine .= ' · ID ' . $listingId;
            $softMismatchLines[] = $softLine . '.';
        }

        if (!empty($row['ok'])) {
            $line = 'OK: ' . $label;
            if ($listingId !== '') $line .= ' · ID ' . $listingId;
            if ($currentUrl !== '') $line .= ' · ' . $currentUrl;
            $successLines[] = $line . '.';
            continue;
        }

        $error = trim((string)($result['error'] ?? 'Sin detalle'));
        $errorCode = trim((string)($result['error_code'] ?? ''));
        $errorCategory = trim((string)($result['error_category'] ?? ''));
        $errorPhase = trim((string)($result['error_phase'] ?? ''));
        $attempts = max(1, (int)($result['copy_attempts'] ?? 1));
        $line = 'Fallo: ' . $label . ' · ' . $error;
        if ($errorCode !== '') $line .= ' · code: ' . $errorCode;
        if ($errorCategory !== '') $line .= ' · cat: ' . $errorCategory;
        if ($errorPhase !== '') $line .= ' · fase: ' . $errorPhase;
        if ($attempts > 1) $line .= ' · intentos de copy: ' . $attempts;
        $failureLines[] = $line . '.';
    }

    if (!empty($successLines)) {
        $lines[] = 'Publicados correctamente:';
        foreach ($successLines as $line) $lines[] = '- ' . $line;
    }
    if (!empty($softMismatchLines)) {
        $lines[] = 'Advertencias de guardado (no bloqueantes):';
        foreach ($softMismatchLines as $line) $lines[] = '- ' . $line;
    }
    if (!empty($failureLines)) {
        $lines[] = 'Fallos detectados:';
        foreach ($failureLines as $line) $lines[] = '- ' . $line;
    }

    $runId = trim((string)($run['id'] ?? ''));
    if ($runId !== '') {
        $lines[] = 'Run: ' . $runId . '.';
    }
    return implode("\n", $lines);
}

function publicista_free_bump_logs_get($limit = 80) {
    $limit = max(1, (int)$limit);
    $path = publicista_free_bump_logs_path();
    if (!is_file($path)) return array();
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines) || empty($lines)) return array();
    $lines = array_slice($lines, -1 * $limit);
    $out = array();
    foreach ($lines as $line) {
        $decoded = json_decode((string)$line, true);
        if (is_array($decoded)) $out[] = $decoded;
    }
    return array_reverse($out);
}

function publicista_free_bump_logs_all() {
    $path = publicista_free_bump_logs_path();
    if (!is_file($path)) return array();
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines) || empty($lines)) return array();
    $out = array();
    foreach ($lines as $line) {
        $decoded = json_decode((string)$line, true);
        if (is_array($decoded)) $out[] = $decoded;
    }
    return $out;
}

function publicista_free_bump_find_log_by_request_id($requestId) {
    $requestId = trim((string)$requestId);
    if ($requestId === '') return null;
    $logs = publicista_free_bump_logs_all();
    for ($i = count($logs) - 1; $i >= 0; $i--) {
        $row = is_array($logs[$i] ?? null) ? $logs[$i] : array();
        if (trim((string)($row['request_id'] ?? '')) === $requestId) {
            return $row;
        }
    }
    return null;
}

function publicista_free_bump_selected_accounts($cfg, $includeSkipped = false) {
    $cfg = publicista_free_bump_config_normalize($cfg);
    $groups = $cfg['groups'];
    $allAccounts = publicista_accounts_get(false);
    $out = array();
    foreach ($allAccounts as $account) {
        if (trim((string)($account['portal_code'] ?? '')) !== 'destacamos') continue;
        $displayName = trim((string)($account['display_name'] ?? ''));
        if ($displayName === '' || !isset($groups[$displayName])) continue;
        $groupCfg = $groups[$displayName];
        if (empty($groupCfg['enabled'])) continue;
        $skipReason = '';
        if (trim((string)($account['estado'] ?? 'active')) !== 'active') {
            $skipReason = 'La cuenta no está activa.';
        } elseif (trim((string)($account['login_user'] ?? '')) === '' || trim((string)($account['login_pass'] ?? '')) === '') {
            $skipReason = 'Faltan credenciales.';
        } elseif (empty($cfg['allow_any_listing']) && empty(publicista_account_listing_ids($account))) {
            $skipReason = 'No tiene IDs internos asignados.';
        }
        $account['_free_bump_ready'] = ($skipReason === '');
        $account['_free_bump_skip_reason'] = $skipReason;
        $account['_group_name'] = $displayName;
        $account['_group_cfg'] = $groupCfg;
        if ($includeSkipped || $skipReason === '') {
            $out[] = $account;
        }
    }
    return array_values($out);
}

function publicista_free_bump_random_between($min, $max) {
    $min = (int)$min;
    $max = (int)$max;
    if ($max <= $min) return $min;
    try {
        return random_int($min, $max);
    } catch (Throwable $e) {
        return mt_rand($min, $max);
    }
}

function publicista_free_bump_listing_key($accountId, $listingId) {
    return trim((string)$accountId) . '|' . trim((string)$listingId);
}

function publicista_free_bump_last_success_map($logs) {
    $map = array();
    foreach ((array)$logs as $row) {
        if (empty($row['ok'])) continue;
        $accountId = trim((string)($row['account_id'] ?? ''));
        $listingId = trim((string)($row['listing_id'] ?? ''));
        if ($accountId === '' || $listingId === '') continue;
        $ts = strtotime((string)($row['created_at'] ?? ''));
        if ($ts === false) continue;
        $key = publicista_free_bump_listing_key($accountId, $listingId);
        if (!isset($map[$key]) || $ts > $map[$key]) {
            $map[$key] = $ts;
        }
    }
    return $map;
}

function publicista_free_bump_account_ads_per_day($account) {
    $account = publicista_account_normalize($account);
    $listingIdsCount = count(publicista_account_listing_ids($account));
    $manualTasks = max(0, (int)($account['free_bump_tasks_count'] ?? 0));
    $slotCount = max(0, (int)($account['listing_slot_count'] ?? 0));
    // Avoid historical publish/active totals biasing daily free-bump priority.
    $basis = max($listingIdsCount, $manualTasks, $slotCount);
    if ($basis <= 0 && !empty($account['_free_bump_ready'])) {
        $basis = 1;
    }
    return $basis;
}

function publicista_free_bump_success_count_in_window($logs, $accountId, $startTs, $endTs) {
    $accountId = trim((string)$accountId);
    $startTs = (int)$startTs;
    $endTs = (int)$endTs;
    if ($accountId === '' || $startTs <= 0 || $endTs <= 0 || $endTs < $startTs) {
        return 0;
    }
    $count = 0;
    foreach ((array)$logs as $row) {
        if (empty($row['ok'])) continue;
        if (trim((string)($row['account_id'] ?? '')) !== $accountId) continue;
        $ts = strtotime((string)($row['created_at'] ?? ''));
        if ($ts === false) continue;
        if ($ts < $startTs || $ts > $endTs) continue;
        $count++;
    }
    return $count;
}

function publicista_free_bump_window_bounds($cfg, $nowTs = null) {
    // Accept either a full config or a group config {start_time, end_time}
    if (!isset($cfg['start_time']) && !isset($cfg['end_time'])) {
        $cfg = publicista_free_bump_config_normalize($cfg);
    }
    $startTime = publicista_free_bump_normalize_hhmm((string)($cfg['start_time'] ?? '08:00'), '08:00');
    $endTime = publicista_free_bump_normalize_hhmm((string)($cfg['end_time'] ?? '23:00'), '23:00');
    $nowTs = $nowTs ?: time();
    $day = date('Y-m-d', $nowTs);
    $yesterday = date('Y-m-d', $nowTs - 86400);
    $tomorrow = date('Y-m-d', $nowTs + 86400);

    $todayStartTs = strtotime($day . ' ' . $startTime . ':00');
    $todayEndTs = strtotime($day . ' ' . $endTime . ':00');
    if ($todayStartTs === false || $todayEndTs === false) {
        $todayStartTs = strtotime($day . ' 08:00:00');
        $todayEndTs = strtotime($day . ' 23:00:00');
    }

    $crossesMidnight = $todayEndTs <= $todayStartTs;
    if (!$crossesMidnight) {
        if ($nowTs < $todayStartTs) {
            return array(
                'start_ts' => $todayStartTs,
                'end_ts' => $todayEndTs,
                'phase' => 'before_window',
                'in_window' => false,
                'day_key' => date('Y-m-d', $todayStartTs),
                'crosses_midnight' => false,
            );
        }
        if ($nowTs <= $todayEndTs) {
            return array(
                'start_ts' => $todayStartTs,
                'end_ts' => $todayEndTs,
                'phase' => 'inside_window',
                'in_window' => true,
                'day_key' => date('Y-m-d', $todayStartTs),
                'crosses_midnight' => false,
            );
        }
        return array(
            'start_ts' => strtotime($tomorrow . ' ' . $startTime . ':00'),
            'end_ts' => strtotime($tomorrow . ' ' . $endTime . ':00'),
            'phase' => 'after_window',
            'in_window' => false,
            'day_key' => $tomorrow,
            'crosses_midnight' => false,
        );
    }

    $todayEndTs += 86400;
    $yesterdayStartTs = strtotime($yesterday . ' ' . $startTime . ':00');
    $yesterdayEndTs = strtotime($yesterday . ' ' . $endTime . ':00');
    if ($yesterdayStartTs === false || $yesterdayEndTs === false) {
        $yesterdayStartTs = strtotime($yesterday . ' 08:00:00');
        $yesterdayEndTs = strtotime($yesterday . ' 23:00:00');
    }
    if ($yesterdayEndTs <= $yesterdayStartTs) $yesterdayEndTs += 86400;

    if ($nowTs >= $yesterdayStartTs && $nowTs <= $yesterdayEndTs) {
        return array(
            'start_ts' => $yesterdayStartTs,
            'end_ts' => $yesterdayEndTs,
            'phase' => 'inside_window',
            'in_window' => true,
            'day_key' => date('Y-m-d', $yesterdayStartTs),
            'crosses_midnight' => true,
        );
    }

    if ($nowTs < $todayStartTs) {
        return array(
            'start_ts' => $todayStartTs,
            'end_ts' => $todayEndTs,
            'phase' => 'before_window',
            'in_window' => false,
            'day_key' => date('Y-m-d', $todayStartTs),
            'crosses_midnight' => true,
        );
    }

    if ($nowTs <= $todayEndTs) {
        return array(
            'start_ts' => $todayStartTs,
            'end_ts' => $todayEndTs,
            'phase' => 'inside_window',
            'in_window' => true,
            'day_key' => date('Y-m-d', $todayStartTs),
            'crosses_midnight' => true,
        );
    }

    $tomorrowStartTs = strtotime($tomorrow . ' ' . $startTime . ':00');
    $tomorrowEndTs = strtotime($tomorrow . ' ' . $endTime . ':00');
    if ($tomorrowStartTs === false || $tomorrowEndTs === false) {
        $tomorrowStartTs = strtotime($tomorrow . ' 08:00:00');
        $tomorrowEndTs = strtotime($tomorrow . ' 23:00:00');
    }
    if ($tomorrowEndTs <= $tomorrowStartTs) $tomorrowEndTs += 86400;

    return array(
        'start_ts' => $tomorrowStartTs,
        'end_ts' => $tomorrowEndTs,
        'phase' => 'after_window',
        'in_window' => false,
        'day_key' => date('Y-m-d', $tomorrowStartTs),
        'crosses_midnight' => true,
    );
}

function publicista_free_bump_account_opportunities($account, $window, $lastSuccessMap, $nowTs = null, $allowAnyListing = false) {
    $account = publicista_account_normalize($account);
    $nowTs = $nowTs ?: time();
    $accountId = trim((string)($account['id'] ?? ''));
    $listingIds = publicista_account_listing_ids($account);
    if (empty($listingIds) && $allowAnyListing) {
        $listingIds = array('__account_scan__');
    }
    $remainingCount = 0;
    $dueNowCount = 0;
    $soonestTs = 0;
    $opportunities = array();

    foreach ($listingIds as $listingId) {
        $key = publicista_free_bump_listing_key($accountId, $listingId);
        if ($listingId === '__account_scan__') {
            $lastAccountSuccessTs = 0;
            foreach ((array)$lastSuccessMap as $successKey => $successTs) {
                if ($accountId !== '' && strpos((string)$successKey, $accountId . '|') === 0) {
                    $lastAccountSuccessTs = max($lastAccountSuccessTs, (int)$successTs);
                }
            }
            $nextTs = $lastAccountSuccessTs > 0 ? ($lastAccountSuccessTs + 12 * 3600) : (int)$window['start_ts'];
        } else {
            $nextTs = isset($lastSuccessMap[$key]) ? ((int)$lastSuccessMap[$key] + 12 * 3600) : (int)$window['start_ts'];
        }
        while ($nextTs < (int)$window['start_ts']) {
            $nextTs += 12 * 3600;
        }
        while ($nextTs <= (int)$window['end_ts']) {
            $opportunities[] = array(
                'listing_id' => $listingId,
                'eligible_at' => $nextTs,
            );
            $remainingCount++;
            if ($nextTs <= $nowTs) $dueNowCount++;
            if ($soonestTs === 0 || $nextTs < $soonestTs) $soonestTs = $nextTs;
            $nextTs += 12 * 3600;
        }
    }

    return array(
        'account_id' => $account['id'] ?? '',
        'listing_ids_total' => count($listingIds),
        'remaining_count' => $remainingCount,
        'due_now_count' => $dueNowCount,
        'next_eligible_ts' => $soonestTs,
        'opportunities' => $opportunities,
    );
}

function publicista_free_bump_plan_snapshot($cfg = null, $state = null, $nowTs = null) {
    $cfg = $cfg === null ? publicista_free_bump_config() : publicista_free_bump_config_normalize($cfg);
    $state = $state === null ? publicista_free_bump_state() : publicista_free_bump_state_normalize($state);
    $nowTs = $nowTs ?: time();

    $groups = $cfg['groups'];
    $selectedAccounts = publicista_free_bump_selected_accounts($cfg, true);
    $logs = publicista_free_bump_logs_all();
    $lastSuccessMap = publicista_free_bump_last_success_map($logs);

    // Group selected accounts by group name
    $accountsByGroup = array();
    foreach ($selectedAccounts as $account) {
        $gn = trim((string)($account['_group_name'] ?? ''));
        if ($gn !== '') $accountsByGroup[$gn][] = $account;
    }

    $accountPlans = array();
    $groupPlans = array();
    $totalListingIds = 0;
    $remainingTotal = 0;
    $dueNowTotal = 0;
    $soonestFutureTs = 0;
    $anyWindowInProgress = false;
    $recommendedNextTs = 0;
    $dailyTargetTotal = 0;
    $completedWindowTotal = 0;
    $pendingTargetTotal = 0;
    $dynamicIntervalSeconds = 0;

    foreach ($groups as $groupName => $groupCfg) {
        if (empty($groupCfg['enabled'])) continue;
        $window = publicista_free_bump_window_bounds($groupCfg, $nowTs);
        if (!empty($window['in_window'])) $anyWindowInProgress = true;

        $groupAccounts = $accountsByGroup[$groupName] ?? array();
        $readyInGroup = array_filter($groupAccounts, function($a) { return !empty($a['_free_bump_ready']); });
        $groupRemainingTotal = 0;
        $groupDueNowTotal = 0;
        $groupSoonestFutureTs = 0;
        $groupDailyTargetTotal = 0;
        $groupCompletedWindowTotal = 0;
        $groupPendingTargetTotal = 0;

        foreach ($groupAccounts as $account) {
            $listingIds = publicista_account_listing_ids($account);
            $totalListingIds += count($listingIds);
            $accountPlan = array(
                'account_id' => $account['id'] ?? '',
                'account_label' => trim((string)($account['display_name'] ?? ($account['login_user'] ?? ($account['id'] ?? '')))),
                'group' => $groupName,
                'ready' => !empty($account['_free_bump_ready']),
                'skip_reason' => trim((string)($account['_free_bump_skip_reason'] ?? '')),
                'listing_ids_total' => count($listingIds),
                'remaining_count' => 0,
                'due_now_count' => 0,
                'next_eligible_at' => '',
                'daily_target_count' => 0,
                'completed_in_window_count' => 0,
                'pending_target_count' => 0,
            );
            if (!empty($account['_free_bump_ready'])) {
                $accountId = trim((string)($account['id'] ?? ''));
                $opSummary = publicista_free_bump_account_opportunities($account, $window, $lastSuccessMap, $nowTs, !empty($cfg['allow_any_listing']));
                $accountPlan['remaining_count'] = (int)($opSummary['remaining_count'] ?? 0);
                $accountPlan['due_now_count'] = (int)($opSummary['due_now_count'] ?? 0);
                $accountPlan['next_eligible_at'] = !empty($opSummary['next_eligible_ts']) ? date('Y-m-d H:i:s', (int)$opSummary['next_eligible_ts']) : '';

                $adsPerDay = publicista_free_bump_account_ads_per_day($account);
                $dailyTarget = max(0, $adsPerDay * 2);
                $completedInWindow = publicista_free_bump_success_count_in_window($logs, $accountId, (int)$window['start_ts'], (int)$window['end_ts']);
                $pendingTarget = max(0, $dailyTarget - $completedInWindow);
                $accountPlan['daily_target_count'] = $dailyTarget;
                $accountPlan['completed_in_window_count'] = $completedInWindow;
                $accountPlan['pending_target_count'] = $pendingTarget;

                $groupRemainingTotal += $accountPlan['remaining_count'];
                $groupDueNowTotal += $accountPlan['due_now_count'];
                $remainingTotal += $accountPlan['remaining_count'];
                $dueNowTotal += $accountPlan['due_now_count'];
                $groupDailyTargetTotal += $dailyTarget;
                $groupCompletedWindowTotal += $completedInWindow;
                $groupPendingTargetTotal += $pendingTarget;
                $dailyTargetTotal += $dailyTarget;
                $completedWindowTotal += $completedInWindow;
                $pendingTargetTotal += $pendingTarget;
                if (!empty($opSummary['next_eligible_ts']) && (int)$opSummary['next_eligible_ts'] > $nowTs) {
                    $eligibleTs = (int)$opSummary['next_eligible_ts'];
                    if ($soonestFutureTs === 0 || $eligibleTs < $soonestFutureTs) $soonestFutureTs = $eligibleTs;
                    if ($groupSoonestFutureTs === 0 || $eligibleTs < $groupSoonestFutureTs) $groupSoonestFutureTs = $eligibleTs;
                }
            }
            $accountPlans[] = $accountPlan;
        }

        // Compute per-group recommended next using all remaining 12h opportunities,
        // so the bumps are spread across the whole active window instead of agotarse al principio.
        $groupRecommendedNextTs = 0;
        $groupRequiredIntervalSec = 0;
        if (!empty($readyInGroup)) {
            if (!empty($window['in_window'])) {
                $groupSpacingCount = max(0, $groupRemainingTotal);
                if ($groupSpacingCount > 0) {
                    $windowRemainingSec = max(60, (int)$window['end_ts'] - $nowTs);
                    $rawInterval = (int)ceil($windowRemainingSec / max(1, $groupSpacingCount));
                    $groupRequiredIntervalSec = max(
                        max(60, (int)$cfg['interval_min_minutes'] * 60),
                        min(max(60, (int)$cfg['interval_max_minutes'] * 60), $rawInterval)
                    );
                    if ($dynamicIntervalSeconds === 0 || $groupRequiredIntervalSec < $dynamicIntervalSeconds) {
                        $dynamicIntervalSeconds = $groupRequiredIntervalSec;
                    }
                }

                if ($groupDueNowTotal > 0) {
                    $groupRecommendedNextTs = $nowTs + max(60, $groupRequiredIntervalSec > 0 ? $groupRequiredIntervalSec : ((int)$cfg['interval_min_minutes'] * 60));
                } elseif ($groupRemainingTotal > 0 && $groupSoonestFutureTs > 0) {
                    $baseIntervalSec = max(60, $groupRequiredIntervalSec > 0 ? $groupRequiredIntervalSec : ((int)$cfg['interval_min_minutes'] * 60));
                    $precheckTs = max($nowTs + 60, $groupSoonestFutureTs - ((int)$cfg['anticipation_minutes'] * 60));
                    $groupRecommendedNextTs = min($nowTs + $baseIntervalSec, $precheckTs);
                } elseif ($groupRemainingTotal > 0) {
                    $groupRecommendedNextTs = min((int)$window['end_ts'], $nowTs + max(60, $groupRequiredIntervalSec));
                } else {
                    $nextWindow = publicista_free_bump_window_bounds($groupCfg, (int)$window['end_ts'] + 60);
                    $groupRecommendedNextTs = (int)$nextWindow['start_ts'];
                }
            } else {
                $groupRecommendedNextTs = (int)$window['start_ts'];
            }
        }
        if ($groupRecommendedNextTs > 0 && ($recommendedNextTs === 0 || $groupRecommendedNextTs < $recommendedNextTs)) {
            $recommendedNextTs = $groupRecommendedNextTs;
        }

        $groupPlans[] = array(
            'group' => $groupName,
            'window_start_at' => date('Y-m-d H:i:s', (int)$window['start_ts']),
            'window_end_at' => date('Y-m-d H:i:s', (int)$window['end_ts']),
            'window_phase' => $window['phase'],
            'window_in_progress' => !empty($window['in_window']),
            'ready_count' => count($readyInGroup),
            'total_count' => count($groupAccounts),
            'remaining_count' => $groupRemainingTotal,
            'due_now_count' => $groupDueNowTotal,
            'daily_target_count' => $groupDailyTargetTotal,
            'completed_in_window_count' => $groupCompletedWindowTotal,
            'pending_target_count' => $groupPendingTargetTotal,
            'required_interval_seconds' => $groupRequiredIntervalSec,
            'recommended_next_at' => $groupRecommendedNextTs > 0 ? date('Y-m-d H:i:s', $groupRecommendedNextTs) : '',
        );
    }

    if (!$cfg['enabled']) $recommendedNextTs = 0;

    $readyAccountsTotal = count(array_filter($selectedAccounts, function($a) { return !empty($a['_free_bump_ready']); }));

    return array(
        'config' => $cfg,
        'state' => $state,
        'window_in_progress' => $anyWindowInProgress,
        'selected_accounts_count' => count($selectedAccounts),
        'ready_accounts_count' => $readyAccountsTotal,
        'total_listing_ids' => $totalListingIds,
        'remaining_opportunities' => $remainingTotal,
        'due_now_total' => $dueNowTotal,
        'daily_target_count_total' => $dailyTargetTotal,
        'completed_in_window_total' => $completedWindowTotal,
        'pending_target_total' => $pendingTargetTotal,
        'dynamic_interval_seconds' => $dynamicIntervalSeconds,
        'soonest_future_at' => $soonestFutureTs > 0 ? date('Y-m-d H:i:s', $soonestFutureTs) : '',
        'recommended_next_at' => $recommendedNextTs > 0 ? date('Y-m-d H:i:s', $recommendedNextTs) : '',
        'recommended_next_ts' => $recommendedNextTs,
        'accounts' => $accountPlans,
        'groups' => $groupPlans,
    );
}

function publicista_free_bump_apply_jitter($cfg, $baseTs, $nowTs = null) {
    $cfg = publicista_free_bump_config_normalize($cfg);
    $baseTs = (int)$baseTs;
    $nowTs = $nowTs ?: time();
    if ($baseTs <= 0) return 0;
    $jitter = publicista_free_bump_random_between($cfg['jitter_min_seconds'], $cfg['jitter_max_seconds']);
    return max($nowTs, $baseTs + $jitter);
}

function publicista_free_bump_schedule_next_ts($cfg, $plan, $nowTs = null, $reason = 'normal') {
    $cfg = publicista_free_bump_config_normalize($cfg);
    $nowTs = $nowTs ?: time();
    $baseTs = (int)($plan['recommended_next_ts'] ?? 0);
    if ($baseTs <= 0) return 0;

    $dynamicIntervalSec = max(0, (int)($plan['dynamic_interval_seconds'] ?? 0));
    if (in_array($reason, array('empty', 'error'), true)) {
        $retryTs = $nowTs + publicista_free_bump_random_between($cfg['retry_empty_min_minutes'] * 60, $cfg['retry_empty_max_minutes'] * 60);
        $baseTs = max($baseTs, $retryTs);
    } else {
        if ($baseTs <= $nowTs) {
            $baseTs = $nowTs + ($dynamicIntervalSec > 0 ? $dynamicIntervalSec : max(60, $cfg['interval_min_minutes'] * 60));
        }
        if ($dynamicIntervalSec > 0) {
            $baseTs = min($baseTs, $nowTs + $dynamicIntervalSec);
        }
    }

    if (!in_array($reason, array('empty', 'error'), true) && $dynamicIntervalSec > 0) {
        $tightJitterMax = min((int)$cfg['jitter_max_seconds'], max(15, (int)floor($dynamicIntervalSec * 0.25)));
        $tightJitterMin = min((int)$cfg['jitter_min_seconds'], $tightJitterMax);
        $cfg['jitter_min_seconds'] = $tightJitterMin;
        $cfg['jitter_max_seconds'] = $tightJitterMax;
    }

    return publicista_free_bump_apply_jitter($cfg, $baseTs, $nowTs);
}

function publicista_free_bump_account_order($accounts, $plan, $state, $nowTs = null) {
    $nowTs = $nowTs ?: time();
    $state = publicista_free_bump_state_normalize($state);
    $accountPlans = array();
    foreach ((array)($plan['accounts'] ?? array()) as $row) {
        $accountPlans[trim((string)($row['account_id'] ?? ''))] = $row;
    }

    $scored = array();
    foreach ((array)$accounts as $account) {
        $id = trim((string)($account['id'] ?? ''));
        if ($id === '') continue;
        $meta = $accountPlans[$id] ?? array();
        $score = publicista_free_bump_random_between(1, 100000);
        $score += max(0, (int)($account['priority_weight'] ?? 100));
        $score += max(0, ((int)($meta['due_now_count'] ?? 0)) * 10000);
        $score += max(0, ((int)($meta['pending_target_count'] ?? 0)) * 3000);
        $score += max(0, ((int)($meta['remaining_count'] ?? 0)) * 1000);
        if ($state['last_account_id'] !== '' && $state['last_account_id'] === $id) {
            $score -= 50000;
        }
        $scored[] = array('score' => $score, 'account' => $account);
    }

    usort($scored, function($a, $b) {
        if ($a['score'] === $b['score']) return 0;
        return ($a['score'] > $b['score']) ? -1 : 1;
    });

    return array_values(array_map(function($row) {
        return $row['account'];
    }, $scored));
}

function publicista_free_bump_summary_line($state, $plan) {
    return 'Cuentas listas: ' . (int)($plan['ready_accounts_count'] ?? 0)
        . ' · IDs: ' . (int)($plan['total_listing_ids'] ?? 0)
        . ' · Oportunidades restantes: ' . (int)($plan['remaining_opportunities'] ?? 0)
        . ' · Próximo intento: ' . trim((string)($state['next_run_at'] ?? 'sin planificar'));
}

function publicista_free_bump_primary_attempt($attempts) {
    $attempts = array_values(array_filter((array)$attempts, function($attempt) {
        return is_array($attempt);
    }));
    if (empty($attempts)) return array();

    foreach ($attempts as $attempt) {
        if (!empty($attempt['ok'])) return $attempt;
    }
    foreach ($attempts as $attempt) {
        $errorCode = trim((string)($attempt['error_code'] ?? ''));
        $error = trim((string)($attempt['error'] ?? ''));
        if (($errorCode !== '' && $errorCode !== 'no_free_listing_available') || $error !== '') {
            return $attempt;
        }
    }
    foreach ($attempts as $attempt) {
        if (trim((string)($attempt['listing_id'] ?? '')) !== '') return $attempt;
    }
    return $attempts[0];
}

function publicista_free_bump_log_summary_from_result($result, $state) {
    $result = is_array($result) ? $result : array();
    $state = is_array($state) ? $state : array();
    $attempts = array_values(array_filter((array)($result['attempts'] ?? array()), function($attempt) {
        return is_array($attempt);
    }));
    $attemptsCount = count($attempts);
    $primary = publicista_free_bump_primary_attempt($attempts);
    $accountLabel = trim((string)($result['account_label'] ?? ($primary['account_label'] ?? ($primary['account_id'] ?? ''))));
    $listingId = trim((string)($result['listing_id'] ?? ($primary['listing_id'] ?? '')));
    $status = trim((string)($result['status'] ?? ($state['last_status'] ?? '')));

    if (!empty($result['ok'])) {
        $summary = 'Subido gratis';
        if ($listingId !== '') $summary .= ' el listing ' . $listingId;
        if ($accountLabel !== '') $summary .= ' en ' . $accountLabel;
        return $summary . '.';
    }

    if ($status === 'error') {
        $error = trim((string)($primary['error'] ?? ($result['error'] ?? '')));
        $errorCode = trim((string)($primary['error_code'] ?? ''));
        $summary = $accountLabel !== '' ? ('Error en ' . $accountLabel) : 'Alguna cuenta devolvió error al intentar subir gratis';
        if ($error !== '') {
            $summary .= ': ' . $error;
        } elseif ($errorCode !== '') {
            $summary .= ' (' . $errorCode . ')';
        }
        if ($attemptsCount > 1) {
            $summary .= ' · revisadas ' . $attemptsCount . ' cuentas';
        }
        return $summary . '.';
    }

    if ($attemptsCount > 0) {
        return 'No había anuncios libres tras revisar ' . $attemptsCount . ' cuenta' . ($attemptsCount === 1 ? '' : 's') . '.';
    }

    return trim((string)($state['last_error'] ?? 'No había anuncios libres.'));
}

function publicista_free_bump_append_cycle_log($result, $state, $extra = array()) {
    $result = is_array($result) ? $result : array();
    $state = is_array($state) ? $state : array();
    $extra = is_array($extra) ? $extra : array();
    $attempts = array_values((array)($result['attempts'] ?? array()));
    $primaryAttempt = publicista_free_bump_primary_attempt($attempts);

    $accountId = trim((string)($result['account_id'] ?? ''));
    if ($accountId === '' && !empty($primaryAttempt)) {
        $accountId = trim((string)($primaryAttempt['account_id'] ?? ''));
    }
    $accountLabel = trim((string)($result['account_label'] ?? ''));
    if ($accountLabel === '' && !empty($primaryAttempt)) {
        $accountLabel = trim((string)($primaryAttempt['account_label'] ?? ($primaryAttempt['account_id'] ?? '')));
    }
    $listingId = trim((string)($result['listing_id'] ?? ''));
    if ($listingId === '' && !empty($primaryAttempt)) {
        $listingId = trim((string)($primaryAttempt['listing_id'] ?? ''));
    }

    $summary = trim((string)($extra['summary'] ?? ''));
    if ($summary === '') {
        $summary = publicista_free_bump_log_summary_from_result($result, $state);
    }
    $requestId = trim((string)($extra['request_id'] ?? ($result['request_id'] ?? '')));
    if ($requestId !== '') {
        $existing = publicista_free_bump_find_log_by_request_id($requestId);
        if ($existing) {
            return $existing;
        }
    }

    $logRow = array(
        'id' => generate_id('pfb'),
        'created_at' => now_datetime(),
        'request_id' => $requestId,
        'ok' => !empty($result['ok']),
        'status' => trim((string)($result['status'] ?? ($state['last_status'] ?? 'unknown'))),
        'trigger' => trim((string)($extra['trigger'] ?? 'scheduler')),
        'account_id' => $accountId,
        'account_label' => $accountLabel,
        'listing_id' => $listingId,
        'error' => trim((string)($result['error'] ?? ($state['last_error'] ?? ''))),
        'error_code' => trim((string)(!empty($primaryAttempt) ? ($primaryAttempt['error_code'] ?? '') : ($result['error_code'] ?? ''))),
        'accounts_checked' => count($attempts),
        'primary_attempt' => $primaryAttempt,
        'attempts' => $attempts,
        'next_run_at' => trim((string)($state['next_run_at'] ?? ($result['next_run_at'] ?? ''))),
        'summary' => $summary,
    );
    publicista_free_bump_log_append($logRow);
    return $logRow;
}

function publicista_free_bump_execute_cycle($options = array()) {
    $force = !empty($options['force']);
    $trigger = trim((string)($options['trigger'] ?? ($force ? 'manual' : 'scheduler')));
    $requestId = trim((string)($options['request_id'] ?? generate_id('pfbreq')));
    $forcedAccountId = trim((string)($options['forced_account_id'] ?? ''));
    $nowTs = time();
    $cfg = publicista_free_bump_config();
    $state = publicista_free_bump_state_prepare_today(publicista_free_bump_state());
    $plan = publicista_free_bump_plan_snapshot($cfg, $state, $nowTs);

    $result = array(
        'ok' => false,
        'executed' => false,
        'status' => 'skipped',
        'request_id' => $requestId,
        'error' => '',
        'next_run_at' => '',
        'attempts' => array(),
        'plan' => $plan,
    );

    if (!$cfg['enabled']) {
        $state['next_run_at'] = '';
        $state['last_status'] = 'disabled';
        $state['updated_at'] = now_datetime();
        publicista_free_bump_save_state($state);
        $result['status'] = 'disabled';
        $result['error'] = 'Automatización desactivada.';
        if ($force) {
            publicista_free_bump_append_cycle_log($result, $state, array(
                'trigger' => $trigger,
                'request_id' => $requestId,
                'summary' => 'Intento manual descartado: automatización desactivada.',
            ));
        }
        return $result;
    }

    // Build selected accounts (including skipped when forcing a specific account)
    $allSelectedAccounts = publicista_free_bump_selected_accounts($cfg, $forcedAccountId !== '');
    $readyGroupAccounts = array();
    $selectedAccountsById = array();
    foreach ($allSelectedAccounts as $account) {
        $currentAccountId = trim((string)($account['id'] ?? ''));
        if ($currentAccountId !== '') {
            $selectedAccountsById[$currentAccountId] = $account;
        }
        if (empty($account['_free_bump_ready'])) continue;
        $groupName = trim((string)($account['_group_name'] ?? ''));
        if ($groupName === '') continue;
        $readyGroupAccounts[$groupName][] = $account;
    }

    if ($forcedAccountId !== '') {
        if (!isset($selectedAccountsById[$forcedAccountId])) {
            $state['last_status'] = 'forced_account_not_found';
            $state['last_error'] = 'La cuenta forzada no forma parte de los grupos activos de automatización.';
            $state['last_run_at'] = now_datetime();
            publicista_free_bump_save_state($state);
            $result['status'] = 'forced_account_not_found';
            $result['error'] = $state['last_error'];
            if ($force) {
                publicista_free_bump_append_cycle_log($result, $state, array(
                    'trigger' => $trigger,
                    'request_id' => $requestId,
                ));
            }
            return $result;
        }

        $forcedAccount = $selectedAccountsById[$forcedAccountId];
        if (empty($forcedAccount['_free_bump_ready'])) {
            $state['last_status'] = 'forced_account_not_ready';
            $state['last_error'] = trim((string)($forcedAccount['_free_bump_skip_reason'] ?? 'La cuenta forzada no está lista para ejecutar.'));
            $state['last_run_at'] = now_datetime();
            publicista_free_bump_save_state($state);
            $result['status'] = 'forced_account_not_ready';
            $result['error'] = $state['last_error'];
            $result['account_id'] = $forcedAccountId;
            $result['account_label'] = trim((string)($forcedAccount['display_name'] ?? ($forcedAccount['login_user'] ?? $forcedAccountId)));
            if ($force) {
                publicista_free_bump_append_cycle_log($result, $state, array(
                    'trigger' => $trigger,
                    'request_id' => $requestId,
                ));
            }
            return $result;
        }
    }
    if (empty($readyGroupAccounts)) {
        $state['next_run_at'] = '';
        $state['last_status'] = 'no_accounts';
        $state['last_error'] = 'No hay cuentas de Destacamos listas para automatizar.';
        publicista_free_bump_save_state($state);
        $result['status'] = 'no_accounts';
        $result['error'] = $state['last_error'];
        if ($force) {
            publicista_free_bump_append_cycle_log($result, $state, array(
                'trigger' => $trigger,
                'request_id' => $requestId,
                'summary' => 'Intento manual descartado: no hay cuentas listas.',
            ));
        }
        return $result;
    }

    if (!publicista_require_automation_adapter('destacamos', 'free_bump') || !function_exists('destacamos_subir_gratis_disponible')) {
        $state['last_status'] = 'error';
        $state['last_error'] = 'El adaptador automático de Destacamos no está disponible.';
        $state['today_failed']++;
        publicista_free_bump_save_state($state);
        $result['status'] = 'error';
        $result['error'] = $state['last_error'];
        if ($force) {
            publicista_free_bump_append_cycle_log($result, $state, array(
                'trigger' => $trigger,
                'request_id' => $requestId,
            ));
        }
        return $result;
    }

    $nextRunTs = strtotime((string)($state['next_run_at'] ?? ''));
    $due = false;
    if ($force) {
        $due = true;
    } elseif ($nextRunTs) {
        $due = ($nextRunTs <= $nowTs);
    } else {
        $recommendedTs = (int)($plan['recommended_next_ts'] ?? 0);
        if ($recommendedTs > $nowTs) {
            $scheduledTs = publicista_free_bump_schedule_next_ts($cfg, $plan, $nowTs, 'normal');
            $state['next_run_at'] = $scheduledTs > 0 ? date('Y-m-d H:i:s', $scheduledTs) : '';
            $state['last_status'] = 'scheduled';
            $state['last_error'] = '';
            publicista_free_bump_save_state($state);
            $result['next_run_at'] = $state['next_run_at'];
            return $result;
        }
        $due = true;
    }
    if (!$due) {
        $result['next_run_at'] = $state['next_run_at'];
        return $result;
    }

    // Check which groups are currently in their window
    $inWindowGroups = array();
    foreach ($readyGroupAccounts as $groupName => $groupAccounts) {
        $groupCfg = $cfg['groups'][$groupName] ?? array();
        $groupWindow = publicista_free_bump_window_bounds($groupCfg, $nowTs);
        if (!empty($groupWindow['in_window'])) {
            $inWindowGroups[$groupName] = array(
                'accounts' => $groupAccounts,
                'window' => $groupWindow,
            );
        }
    }
    if (empty($inWindowGroups)) {
        if ($forcedAccountId !== '') {
            $forced = $selectedAccountsById[$forcedAccountId] ?? null;
            $state['last_status'] = 'forced_account_outside_window';
            $state['last_run_at'] = now_datetime();
            $state['last_error'] = 'La cuenta forzada está fuera de su ventana horaria.';
            $state['next_run_at'] = '';
            publicista_free_bump_save_state($state);
            $result['status'] = 'forced_account_outside_window';
            $result['error'] = $state['last_error'];
            $result['account_id'] = $forcedAccountId;
            $result['account_label'] = trim((string)($forced['display_name'] ?? ($forced['login_user'] ?? $forcedAccountId)));
            if ($force) {
                publicista_free_bump_append_cycle_log($result, $state, array(
                    'trigger' => $trigger,
                    'request_id' => $requestId,
                ));
            }
            return $result;
        }
        $scheduleTs = publicista_free_bump_schedule_next_ts($cfg, $plan, $nowTs, 'normal');
        $state['next_run_at'] = $scheduleTs > 0 ? date('Y-m-d H:i:s', $scheduleTs) : '';
        $state['last_status'] = 'waiting_window';
        $state['last_run_at'] = now_datetime();
        $state['last_error'] = '';
        publicista_free_bump_save_state($state);
        $result['next_run_at'] = $state['next_run_at'];
        $result['status'] = 'waiting_window';
        if ($force) {
            publicista_free_bump_append_cycle_log($result, $state, array(
                'trigger' => $trigger,
                'request_id' => $requestId,
                'summary' => 'Intento manual fuera de ventana. Próximo intento programado: ' . trim((string)$state['next_run_at']) . '.',
            ));
        }
        return $result;
    }

    $orderedAccounts = array();
    if ($forcedAccountId !== '') {
        foreach ($inWindowGroups as $groupMeta) {
            foreach ((array)($groupMeta['accounts'] ?? array()) as $accountCandidate) {
                if (trim((string)($accountCandidate['id'] ?? '')) === $forcedAccountId) {
                    $orderedAccounts = array($accountCandidate);
                    break 2;
                }
            }
        }
    }

    // Pick a random group from those in window when there is no account force
    if (empty($orderedAccounts)) {
        $groupKeys = array_keys($inWindowGroups);
        $selectedGroupName = $groupKeys[array_rand($groupKeys)];
        $selectedGroup = $inWindowGroups[$selectedGroupName];
        $orderedAccounts = publicista_free_bump_account_order($selectedGroup['accounts'], $plan, $state, $nowTs);
    }
    $result['executed'] = true;
    $result['status'] = 'no_available';

    foreach ($orderedAccounts as $account) {
        $accountId = trim((string)($account['id'] ?? ''));
        $accountLabel = trim((string)($account['display_name'] ?? ($account['login_user'] ?? $accountId)));
        $payload = array(
            'username' => trim((string)($account['login_user'] ?? '')),
            'password' => trim((string)($account['login_pass'] ?? '')),
            'listingId' => '__auto__',
            'allowed_listing_ids' => !empty($cfg['allow_any_listing']) ? array() : publicista_account_listing_ids($account),
            'timeoutMs' => max(30000, (int)($options['timeoutMs'] ?? 70000)),
            'debug_log' => true,
            'humanize' => !empty($cfg['humanize']),
        );
        $attemptResult = destacamos_subir_gratis_disponible($payload);
        $attempt = array(
            'account_id' => $accountId,
            'account_label' => $accountLabel,
            'ok' => !empty($attemptResult['ok']),
            'error_code' => trim((string)($attemptResult['error_code'] ?? '')),
            'error' => trim((string)($attemptResult['error'] ?? '')),
            'listing_id' => trim((string)($attemptResult['listingId'] ?? '')),
            'available_count_before' => (int)($attemptResult['available_count_before'] ?? 0),
            'available_listing_ids_before' => array_values((array)($attemptResult['available_listing_ids_before'] ?? array())),
            'current_url' => trim((string)($attemptResult['currentUrl'] ?? '')),
            'executed_at' => now_datetime(),
            'result' => $attemptResult,
        );
        $result['attempts'][] = $attempt;

        if (!empty($attemptResult['ok'])) {
            $result['ok'] = true;
            $result['status'] = 'success';
            $result['account_id'] = $accountId;
            $result['account_label'] = $accountLabel;
            $result['listing_id'] = trim((string)($attemptResult['listingId'] ?? ''));
            $result['details'] = $attemptResult;
            break;
        }
    }

    $primaryAttempt = publicista_free_bump_primary_attempt($result['attempts']);
    if (!empty($primaryAttempt)) {
        if (trim((string)($result['account_id'] ?? '')) === '') {
            $result['account_id'] = trim((string)($primaryAttempt['account_id'] ?? ''));
            $result['account_label'] = trim((string)($primaryAttempt['account_label'] ?? ($primaryAttempt['account_id'] ?? '')));
        }
        if (trim((string)($result['listing_id'] ?? '')) === '') {
            $result['listing_id'] = trim((string)($primaryAttempt['listing_id'] ?? ''));
        }
        $result['primary_attempt'] = $primaryAttempt;
    }

    $state = publicista_free_bump_state_prepare_today($state);
    $state['last_run_at'] = now_datetime();
    $state['last_error'] = '';
    if (!empty($result['ok'])) {
        $state['today_ok']++;
        $state['last_success_at'] = $state['last_run_at'];
        $state['last_account_id'] = trim((string)($result['account_id'] ?? ''));
        $state['last_account_label'] = trim((string)($result['account_label'] ?? ''));
        $state['last_listing_id'] = trim((string)($result['listing_id'] ?? ''));
        $state['last_status'] = 'success';
    } else {
        $hadRealFailures = false;
        foreach ((array)$result['attempts'] as $attempt) {
            if (($attempt['error_code'] ?? '') !== 'no_free_listing_available' && trim((string)($attempt['error'] ?? '')) !== '') {
                $hadRealFailures = true;
                break;
            }
        }
        if ($hadRealFailures) {
            $state['today_failed']++;
            $state['last_status'] = 'error';
            $state['last_error'] = 'Alguna cuenta devolvió error al intentar subir gratis.';
            $result['status'] = 'error';
            $result['error'] = trim((string)($primaryAttempt['error'] ?? ''));
            if ($result['error'] === '') {
                $result['error'] = $state['last_error'];
            }
        } else {
            $state['today_empty']++;
            $state['last_status'] = 'no_available';
            $state['last_error'] = 'No había ningún anuncio libre para subir en las cuentas revisadas.';
            $result['status'] = 'no_available';
            $result['error'] = $state['last_error'];
        }
    }

    $postPlan = publicista_free_bump_plan_snapshot($cfg, $state, $nowTs);
    $reason = !empty($result['ok']) ? 'success' : (($state['last_status'] === 'error') ? 'error' : 'empty');
    $nextTs = publicista_free_bump_schedule_next_ts($cfg, $postPlan, $nowTs, $reason);
    $state['next_run_at'] = $nextTs > 0 ? date('Y-m-d H:i:s', $nextTs) : '';
    publicista_free_bump_save_state($state);

    $logRow = publicista_free_bump_append_cycle_log($result, $state, array(
        'trigger' => $trigger,
        'request_id' => $requestId,
    ));
    $result['log_id'] = trim((string)($logRow['id'] ?? ''));

    $result['next_run_at'] = $state['next_run_at'];
    $result['state'] = $state;
    $result['plan'] = $postPlan;
    return $result;
}

function publicista_free_bump_run_due($force = false, $options = array()) {
    if ($force) {
        $options['force'] = true;
    }
    return publicista_free_bump_execute_cycle($options);
}


function publicista_task_status_label($value) {
    $options = publicista_task_status_options();
    $value = trim((string)$value);
    return isset($options[$value]) ? $options[$value] : 'Pendiente';
}

function publicista_run_status_label($value) {
    $options = publicista_run_status_options();
    $value = trim((string)$value);
    return isset($options[$value]) ? $options[$value] : 'Pendiente';
}

function publicista_task_frequency_label($rule) {
    $rule = trim((string)$rule);
    if ($rule === '') return 'Sin regla';
    if (strpos($rule, 'times:') === 0) {
        $times = array_filter(array_map('trim', explode(',', substr($rule, 6))));
        return !empty($times) ? 'Horas: ' . implode(', ', $times) : 'Horas sin definir';
    }
    return $rule;
}

function publicista_campaign_item_resolve_phone($item) {
    $item = publicista_campaign_item_normalize($item);
    $phoneId = trim((string)($item['phone_id'] ?? ''));
    $phones = storage_read('telefonos.json');
    if ($phoneId !== '') {
        foreach ($phones as $phone) {
            if (trim((string)($phone['id'] ?? '')) === $phoneId) return $phone;
        }
    }
    $accountId = trim((string)($item['account_id'] ?? ''));
    if ($accountId !== '') {
        foreach ($phones as $phone) {
            if (trim((string)($phone['destacamos_id'] ?? '')) === $accountId) return $phone;
        }
    }
    return null;
}

function publicista_text_fold($text) {
    $text = html_entity_decode(trim((string)$text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($text === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false && $converted !== '') {
            $text = $converted;
        }
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', ' ', $text);
    return trim((string)preg_replace('/\s+/', ' ', (string)$text));
}

function publicista_digits_only($text) {
    return preg_replace('/\D+/', '', (string)$text);
}

function publicista_destacamos_normalize_province_label($province) {
    $province = trim((string)$province);
    if ($province === '') {
        return '';
    }
    $token = publicista_text_fold($province);
    $map = array(
        'castellon' => 'Castellón',
    );
    return $map[$token] ?? $province;
}

function publicista_destacamos_extract_zip_candidate($value) {
    $digits = publicista_digits_only($value);
    if (strlen($digits) < 5) {
        return '';
    }
    $zip = substr($digits, 0, 5);
    return preg_match('/^[0-5][0-9]{4}$/', $zip) ? $zip : '';
}

function publicista_destacamos_guess_zip($city, $province = '') {
    $cityToken = publicista_text_fold($city);
    $provinceToken = publicista_text_fold($province);
    if ($cityToken === '' && $provinceToken === '') {
        return '';
    }

    $rules = array(
        array(
            'province' => 'castellon',
            'cities' => array('burriana', 'borriana'),
            'zip' => '12530',
        ),
    );

    foreach ($rules as $rule) {
        $requiredProvince = trim((string)($rule['province'] ?? ''));
        if ($requiredProvince !== '' && $provinceToken !== '' && $provinceToken !== $requiredProvince) {
            continue;
        }
        foreach ((array)($rule['cities'] ?? array()) as $cityNeedle) {
            $cityNeedle = trim((string)$cityNeedle);
            if ($cityNeedle === '') {
                continue;
            }
            if ($cityToken === $cityNeedle || strpos(' ' . $cityToken . ' ', ' ' . $cityNeedle . ' ') !== false) {
                return trim((string)($rule['zip'] ?? ''));
            }
        }
    }

    return '';
}

function publicista_campaign_resolve_location($campaign, $item) {
    $campaign = publicista_campaign_normalize($campaign);
    $item = publicista_campaign_item_normalize($item);
    $planningData = is_array($campaign['planning_snapshot']['data'] ?? null) ? $campaign['planning_snapshot']['data'] : array();
    $productData = is_array($item['product_snapshot']['data'] ?? null) ? $item['product_snapshot']['data'] : array();

    $city = trim((string)($planningData['city'] ?? ''));
    if ($city === '') {
        $city = trim((string)($productData['localidad_snapshot'] ?? ''));
    }

    $province = trim((string)($planningData['province'] ?? ''));
    if ($province === '') {
        $province = trim((string)($productData['provincia_snapshot'] ?? ''));
    }
    $province = publicista_destacamos_normalize_province_label($province);

    $zip = '';
    $zipCandidates = array(
        $planningData['zip'] ?? '',
        $planningData['postal_code'] ?? '',
        $planningData['codigo_postal'] ?? '',
        $productData['zip'] ?? '',
        $productData['postal_code'] ?? '',
        $productData['codigo_postal'] ?? '',
    );
    foreach ($zipCandidates as $candidate) {
        $zip = publicista_destacamos_extract_zip_candidate($candidate);
        if ($zip !== '') {
            break;
        }
    }
    if ($zip === '') {
        $zip = publicista_destacamos_guess_zip($city, $province);
    }

    return array(
        'city' => $city,
        'province' => $province,
        'zip' => $zip,
    );
}
// Alias retrocompatible
function publicista_destacamos_resolve_location($campaign, $item) {
    return publicista_campaign_resolve_location($campaign, $item);
}

function publicista_campaign_item_copy_title($item) {
    $copy = is_array($item['copy_snapshot'] ?? null) ? $item['copy_snapshot'] : array();
    $title = trim((string)($copy['title_neutral'] ?? ''));
    if ($title === '') $title = trim((string)($copy['title_suggestive'] ?? ''));
    if ($title === '') $title = 'Anuncio automatizado';
    return $title;
}

function publicista_campaign_item_copy_body($item) {
    $copy = is_array($item['copy_snapshot'] ?? null) ? $item['copy_snapshot'] : array();
    $body = trim((string)($copy['body_neutral'] ?? ''));
    if ($body === '') $body = trim((string)($copy['body_suggestive'] ?? ''));
    if ($body === '') $body = trim((string)($copy['short_hook'] ?? ''));
    return $body;
}

function publicista_campaign_item_image_paths($item) {
    $images = is_array($item['image_snapshot'] ?? null) ? $item['image_snapshot'] : array();
    $paths = array();
    foreach ($images as $img) {
        $candidates = array(
            trim((string)($img['final_path'] ?? '')),
            trim((string)($img['square_path'] ?? '')),
            trim((string)($img['preview_path'] ?? '')),
            trim((string)($img['path_rel'] ?? '')),
            trim((string)($img['filename'] ?? '')),
        );
        foreach ($candidates as $rel) {
            if ($rel === '') continue;
            $fs = $rel;
            if (substr($rel, 0, 1) === '/') $fs = BASE_PATH . '/' . ltrim($rel, '/');
            if (file_exists($fs)) {
                $paths[] = realpath($fs) ?: $fs;
                break;
            }
        }
    }
    return array_values(array_unique(array_filter($paths)));
}

function publicista_campaign_item_ready_for_execution($item) {
    $item = publicista_campaign_item_normalize($item);
    $errors = array();
    $account = publicista_account_get($item['account_id'] ?? '', true);
    if (!$account) {
        $errors[] = 'Cuenta no encontrada.';
        return array(false, $errors, null, null);
    }
    if (trim((string)($account['login_user'] ?? '')) === '' || trim((string)($account['login_pass'] ?? '')) === '') {
        $errors[] = 'La cuenta no tiene credenciales completas.';
    }
    $portal = trim((string)($item['portal_code'] ?? 'destacamos'));
    $phone = publicista_campaign_item_resolve_phone($item);
    if ($portal === 'destacamos') {
        if (trim((string)($item['external_ad_id'] ?? '')) === '') {
            $errors[] = 'Falta el listing ID / external_ad_id para ejecutar el adaptador actual de Destacamos.';
        }
        if (!$phone) {
            $errors[] = 'No hay teléfono vinculado ni teléfono por defecto en la cuenta.';
        }
    } elseif ($portal === 'mundosex') {
        if (trim((string)($item['external_ad_id'] ?? '')) === '') {
            $errors[] = 'Falta el listing ID / external_ad_id para ejecutar el adaptador de Mundosex.';
        }
        if (!$phone) {
            $errors[] = 'No hay teléfono vinculado ni teléfono por defecto en la cuenta.';
        }
    } else {
        $errors[] = 'No existe adaptador automático implementado para este portal todavía.';
    }
    return array(empty($errors), $errors, $account, $phone);
}

function publicista_require_automation_adapter($portalCode, $taskType = 'publish') {
    $portalCode = trim((string)$portalCode);
    if ($portalCode === 'destacamos') {
        if ($taskType === 'free_bump') require_once BASE_PATH . '/subirPublicidad/subir-gratis.php';
        else require_once BASE_PATH . '/subirPublicidad/destacamos.php';
        return true;
    }
    if ($portalCode === 'mundosex') {
        require_once BASE_PATH . '/subirPublicidad/mundosex.php';
        return true;
    }
    return false;
}

function publicista_campaign_execute_item($campaign, $item, $options = array()) {
    $campaign = publicista_campaign_normalize($campaign);
    $item = publicista_campaign_item_normalize($item);
    list($canRun, $errors, $account, $phone) = publicista_campaign_item_ready_for_execution($item);
    if (!$canRun) {
        $item['estado'] = 'failed';
        $item['publish_result'] = array('ok' => false, 'stage' => 'preflight', 'errors' => $errors, 'checked_at' => now_datetime());
        $item['updated_at'] = now_datetime();
        publicista_campaign_item_save($item);
        return array(false, $item, $item['publish_result']);
    }

    $portalCode = trim((string)($item['portal_code'] ?? 'destacamos'));
    if (!publicista_require_automation_adapter($portalCode, 'publish')) {
        $result = array('ok' => false, 'error' => 'No hay adaptador de publicación para el portal indicado.');
        $item['estado'] = 'failed';
        $item['publish_result'] = $result;
        $item['updated_at'] = now_datetime();
        publicista_campaign_item_save($item);
        return array(false, $item, $result);
    }

    $imagePaths = publicista_campaign_item_image_paths($item);
    // Variedad visual: cada anuncio sube las fotos en orden aleatorio para que la foto principal del listado varíe entre anuncios del mismo producto
    if (count($imagePaths) > 1) {
        shuffle($imagePaths);
    }
    $adapterCode = $portalCode === 'destacamos' ? 'destacamos_php_http' : ($portalCode === 'mundosex' ? 'mundosex_browser' : ($portalCode !== '' ? $portalCode . '_unknown_adapter' : 'unknown_adapter'));
    $location = publicista_campaign_resolve_location($campaign, $item);
    $resolvedPhone = publicista_digits_only(trim((string)($phone['tfono'] ?? '')));
    if (strlen($resolvedPhone) > 9) {
        $resolvedPhone = substr($resolvedPhone, -9);
    }
    $fieldOverrides = is_array($options['field_overrides'] ?? null) ? $options['field_overrides'] : array();
    $copyAdjustment = is_array($options['copy_adjustment'] ?? null) ? $options['copy_adjustment'] : array();
    $finalTitle = array_key_exists('title', $fieldOverrides) ? trim((string)$fieldOverrides['title']) : publicista_campaign_item_copy_title($item);
    $finalDescription = array_key_exists('description', $fieldOverrides) ? trim((string)$fieldOverrides['description']) : publicista_campaign_item_copy_body($item);
    $payloadFields = array(
        'title' => $finalTitle,
        'description' => $finalDescription,
    );

    $protectedSnapshot = array(
        'telefono' => $resolvedPhone,
        'city' => trim((string)($location['city'] ?? '')),
        'localidad' => trim((string)($location['province'] ?? '')),
        'zip' => trim((string)($location['zip'] ?? '')),
    );

    $allowProtectedFieldOverrides = !empty($options['allow_contact_location_updates']);
    // Mundosex requiere provincia y ciudad en el formulario siempre
    if ($portalCode === 'mundosex') {
        $allowProtectedFieldOverrides = true;
    }
    if ($allowProtectedFieldOverrides) {
        if ($protectedSnapshot['telefono'] !== '') {
            $payloadFields['telefono'] = $protectedSnapshot['telefono'];
        }
        if ($protectedSnapshot['city'] !== '') {
            $payloadFields['city'] = $protectedSnapshot['city'];
        }
        if ($protectedSnapshot['localidad'] !== '') {
            $payloadFields['localidad'] = $protectedSnapshot['localidad'];
        }
        if ($protectedSnapshot['zip'] !== '') {
            $payloadFields['zip'] = $protectedSnapshot['zip'];
        }
    }
    $payload = array(
        'username' => trim((string)($account['login_user'] ?? '')),
        'password' => trim((string)($account['login_pass'] ?? '')),
        'listingId' => trim((string)($item['external_ad_id'] ?? '')),
        'timeoutMs' => max(30000, (int)($options['timeoutMs'] ?? 90000)),
        'save' => true,
        'debug_log' => array_key_exists('debug_log', $options) ? !empty($options['debug_log']) : true,
        'fields' => $payloadFields,
        'editPhotos' => !empty($imagePaths),
        'photos' => $imagePaths,
        'humanize' => isset($options['humanize']) && is_array($options['humanize']) ? $options['humanize'] : publicista_campaign_humanization_defaults(),
    );
    if (is_array($options['session'] ?? null)) {
        $payload['session'] = $options['session'];
    }

    if ($portalCode === 'mundosex' && function_exists('mundosex_ejecutar_automatizacion')) {
        $result = mundosex_ejecutar_automatizacion($payload);
    } else {
        $result = ejecutarAutomatizacion($payload);
    }
    $result['executed_at'] = now_datetime();
    $result['adapter'] = $adapterCode;
    $result['payload_summary'] = array(
        'adapter' => $adapterCode,
        'listingId' => $payload['listingId'],
        'photos_count' => count($imagePaths),
        'title' => $payload['fields']['title'],
        'phone' => trim((string)($payload['fields']['telefono'] ?? '')),
        'city' => trim((string)($payload['fields']['city'] ?? '')),
        'localidad' => trim((string)($payload['fields']['localidad'] ?? '')),
        'zip' => trim((string)($payload['fields']['zip'] ?? '')),
        'protected_contact_location' => $protectedSnapshot,
        'contact_location_updates_sent' => $allowProtectedFieldOverrides,
        'description_chars' => function_exists('mb_strlen') ? mb_strlen((string)$payload['fields']['description'], 'UTF-8') : strlen((string)$payload['fields']['description']),
        'debug_log' => !empty($payload['debug_log']),
        'copy_adjustment' => $copyAdjustment,
    );

    if (!empty($result['ok'])) {
        $item['estado'] = 'published';
        if (trim((string)($item['external_ad_id'] ?? '')) === '' && trim((string)($result['listingId'] ?? '')) !== '') {
            $item['external_ad_id'] = trim((string)$result['listingId']);
        }
        $account['published_ads_count'] = (int)($account['published_ads_count'] ?? 0) + 1;
        $account['active_ads_count'] = max((int)($account['active_ads_count'] ?? 0), 0);
        $account['last_success_at'] = now_datetime();
        $account['last_used_at'] = $account['last_success_at'];
        $account['last_error_at'] = '';
        $account['last_error'] = '';
        publicista_account_upsert($account);
    } else {
        $item['estado'] = 'failed';
        $account['last_error_at'] = now_datetime();
        $account['last_error'] = trim((string)($result['error'] ?? 'Error no especificado en automatización.'));
        $account['last_used_at'] = $account['last_error_at'];
        publicista_account_upsert($account);
    }

    $item['publish_result'] = $result;
    $item['updated_at'] = now_datetime();
    list($_ok, $savedItem) = publicista_campaign_item_save($item);

    // Free-bump tasks solo para Destacamos (Mundosex no tiene soporte de subir-gratis)
    if (!empty($result['ok']) && ($item['portal_code'] ?? 'destacamos') !== 'mundosex') {
        publicista_task_ensure_free_bump_for_item($campaign, $savedItem);
    }

    return array(!empty($result['ok']), $savedItem, $result);
}

function publicista_task_times_from_item($item) {
    $profile = is_array($item['planning_profile_snapshot'] ?? null) ? $item['planning_profile_snapshot'] : array();
    $times = array();
    foreach ((array)($profile['free_slots'] ?? array()) as $time) {
        $time = trim((string)$time);
        if ($time !== '') $times[$time] = $time;
    }
    if (empty($times) && in_array(trim((string)($item['publish_mode'] ?? '')), array('free', 'mixed', 'standard','top','top_auto7','top_auto4','auto7','auto4'), true)) {
        $times['10:00'] = '10:00';
        $times['22:00'] = '22:00';
    }
    return array_values($times);
}

function publicista_task_next_run_at_from_times($times, $nowTs = null) {
    $times = array_values(array_filter(array_map('trim', (array)$times)));
    if (empty($times)) return date('Y-m-d H:i:s', ($nowTs ?: time()) + 12 * 3600);
    $nowTs = $nowTs ?: time();
    $today = date('Y-m-d', $nowTs);
    $candidates = array();
    foreach ($times as $time) {
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) continue;
        $candidate = strtotime($today . ' ' . $time . ':00');
        if ($candidate !== false && $candidate > $nowTs) $candidates[] = $candidate;
        $tomorrow = strtotime('+1 day', strtotime($today . ' 00:00:00'));
        $tomCandidate = strtotime(date('Y-m-d', $tomorrow) . ' ' . $time . ':00');
        if ($tomCandidate !== false) $candidates[] = $tomCandidate;
    }
    sort($candidates);
    foreach ($candidates as $candidate) if ($candidate > $nowTs) return date('Y-m-d H:i:s', $candidate);
    return date('Y-m-d H:i:s', $nowTs + 12 * 3600);
}

function publicista_task_ensure_free_bump_for_item($campaign, $item) {
    $campaign = publicista_campaign_normalize($campaign);
    $item = publicista_campaign_item_normalize($item);
    $times = publicista_task_times_from_item($item);
    if (empty($times)) return array(false, null);

    $existing = null;
    foreach (publicista_tasks_for_campaign_item($item['id']) as $task) {
        if (($task['task_type'] ?? '') === 'free_bump') { $existing = $task; break; }
    }

    $row = publicista_task_defaults($existing['id'] ?? '');
    $row = array_merge($row, is_array($existing) ? $existing : array());
    $row['campaign_id'] = $campaign['id'];
    $row['campaign_item_id'] = $item['id'];
    $row['account_id'] = trim((string)($item['account_id'] ?? ''));
    $row['portal_code'] = trim((string)($item['portal_code'] ?? 'destacamos'));
    $row['task_type'] = 'free_bump';
    $row['estado'] = 'active';
    $row['frequency_rule'] = 'times:' . implode(',', $times);
    $row['next_run_at'] = publicista_task_next_run_at_from_times($times);
    $row['updated_at'] = now_datetime();
    if (trim((string)($row['created_at'] ?? '')) === '') $row['created_at'] = now_datetime();
    return publicista_task_save($row);
}

function publicista_task_due($task, $refTs = null) {
    $task = publicista_task_normalize($task);
    if (!in_array($task['estado'], array('pending', 'active', 'error'), true)) return false;
    $next = trim((string)($task['next_run_at'] ?? ''));
    if ($next === '') return false;
    $nextTs = strtotime($next);
    if ($nextTs === false) return false;
    return $nextTs <= ($refTs ?: time());
}

function publicista_campaign_notify_execution_finished($campaign, $run, $meta, $ok) {
    $campaign = is_array($campaign) ? $campaign : array();
    $run = is_array($run) ? $run : array();
    $meta = is_array($meta) ? $meta : array();

    $campaignId = trim((string)($campaign['id'] ?? ''));
    $campaignName = trim((string)($campaign['nombre'] ?? ''));
    if ($campaignName === '') $campaignName = $campaignId !== '' ? ('Campaña ' . $campaignId) : 'Campaña';
    $runId = trim((string)($run['id'] ?? ''));
    $published = max(0, (int)($meta['published'] ?? 0));
    $failed = max(0, (int)($meta['failed'] ?? 0));
    $isStopped = trim((string)($run['estado'] ?? '')) === 'cancelled' || !empty($meta['stopped']);
    $severity = $isStopped ? 'media' : ((!$ok || $failed > 0) ? 'alta' : 'media');

    if ($isStopped) {
        $title = 'Campaña detenida: ' . $campaignName;
    } else {
        $title = $failed > 0
            ? 'Campaña finalizada con errores: ' . $campaignName
            : 'Campaña subida: ' . $campaignName;
    }

    $message = 'Subidos: ' . $published . '. Fallidos: ' . $failed . '.';
    $humanReport = trim((string)($run['human_report'] ?? ''));
    $error = trim((string)($meta['error'] ?? ''));
    if ($error === '' && !empty($meta['results']) && is_array($meta['results'])) {
        foreach ($meta['results'] as $resultRow) {
            $resultData = is_array($resultRow['result'] ?? null) ? $resultRow['result'] : array();
            $error = trim((string)($resultData['error'] ?? ''));
            if ($error !== '') break;
        }
    }
    if ($isStopped) {
        $message = 'Ejecución detenida por solicitud manual. Subidos: ' . $published . '. Fallidos: ' . $failed . '.';
    }
    if ($error !== '') {
        $message .= ' Error principal: ' . $error;
    }
    if ($runId !== '') {
        $message .= ' Run: ' . $runId . '.';
    }
    if ($humanReport !== '') {
        $message .= ' Informe: ' . str_replace("\n", ' ', $humanReport);
    }

    // NOTIFICACIONES DESACTIVADAS: no generar avisos al finalizar campañas
    //avisos_create_active(
    //    $title,
    //    $message,
    //    $severity,
    //    'publicista_campaign_upload',
    //    array(
    //        'campaign_id' => $campaignId,
    //        'campaign_name' => $campaignName,
    //        'run_id' => $runId,
    //        'published' => $published,
    //        'failed' => $failed,
    //        'ok' => $ok ? true : false,
    //        'human_report' => $humanReport,
    //    ),
    //    false,
    //    'publicista_campaign_upload_' . ($runId !== '' ? $runId : generate_id('pubnotice'))
    //);
}

function publicista_campaign_dispatch_async($campaignId) {
    $campaignId = trim((string)$campaignId);
    $campaign = $campaignId !== '' ? publicista_campaign_get($campaignId) : null;
    if (!$campaign) {
        return array(false, null, array('error' => 'No se encontró la campaña a subir.'));
    }

    publicista_campaign_recover_stuck_run($campaignId, array('stale_seconds' => 900));
    $campaign = publicista_campaign_get($campaignId) ?: $campaign;

    $runningRun = publicista_campaign_running_run($campaignId);
    if ($runningRun) {
        return array(false, $campaign, array('error' => 'Ya hay una subida de esta campaña en curso.'));
    }

    $executionSummary = is_array($campaign['execution_summary'] ?? null) ? $campaign['execution_summary'] : array();
    $lastDispatchTs = strtotime((string)($executionSummary['last_dispatch_at'] ?? ''));
    $recentDispatch = $lastDispatchTs !== false && $lastDispatchTs >= (time() - 120);
    if (($campaign['estado'] ?? '') === 'uploading' && $recentDispatch) {
        return array(false, $campaign, array('error' => 'La subida de esta campaña acaba de lanzarse. Espera unos segundos antes de reintentar.'));
    }

    $items = publicista_campaign_items_for_campaign($campaignId);
    if (empty($items)) {
        return array(false, $campaign, array('error' => 'La campaña no tiene items generados.'));
    }

    $status = trim((string)($campaign['estado'] ?? 'draft'));
    if (!in_array($status, array('generated', 'approved', 'completed', 'error', 'uploading'), true)) {
        return array(false, $campaign, array('error' => 'Antes de subir anuncios debes generar la composición de la campaña.'));
    }

    $approvalSnapshot = is_array($campaign['approval_snapshot'] ?? null) ? $campaign['approval_snapshot'] : array();
    if ($status === 'generated' || empty($approvalSnapshot['approved_at'])) {
        $campaign['approval_snapshot'] = array(
            'approved_at' => now_datetime(),
            'approved_items_count' => count($items),
            'approved_by' => 'system',
            'approval_mode' => 'auto_before_execution',
        );
    }

    $run = publicista_run_defaults();
    $run['campaign_id'] = $campaignId;
    $run['run_type'] = 'campaign_upload';
    $run['estado'] = 'pending';
    $run['summary'] = 'Subida de anuncios en cola.';
    $run['progress'] = array(
        'total_items' => count($items),
        'processed_items' => 0,
        'published' => 0,
        'failed' => 0,
        'current_item_id' => '',
        'current_listing_id' => '',
        'current_account_id' => '',
    );
    $run['pipeline'] = array(
        'status' => 'pending',
        'stage' => 'queued',
        'summary' => 'La campaña está en cola para subir anuncios en segundo plano.',
    );
    $run['items'] = array();
    $run['created_at'] = now_datetime();
    $run['updated_at'] = $run['created_at'];
    list($_runOk, $savedRun) = publicista_run_save($run);

    $campaign['estado'] = 'uploading';
    $campaign['updated_at'] = now_datetime();
    $campaign['execution_summary'] = array_merge((array)($campaign['execution_summary'] ?? array()), array(
        'last_phase' => 'queued',
        'last_dispatch_at' => now_datetime(),
        'last_run_id' => $savedRun['id'],
        'last_run_status' => 'pending',
        'last_run_total' => count($items),
        'last_run_processed' => 0,
        'last_run_published' => 0,
        'last_run_failed' => 0,
    ));
    list($_ok, $savedCampaign) = publicista_campaign_save($campaign);

    return array(true, $savedCampaign, array(
        'run_id' => $savedRun['id'],
    ));
}

function publicista_task_execute($taskId, $options = array()) {
    $task = publicista_task_get($taskId);
    if (!$task) return array(false, null, array('ok' => false, 'error' => 'No se encontró la tarea.'));

    $campaign = publicista_campaign_get($task['campaign_id'] ?? '');
    $item = publicista_campaign_item_get($task['campaign_item_id'] ?? '');
    $account = publicista_account_get($task['account_id'] ?? '', true);
    if (!$campaign || !$item || !$account) {
        $task['estado'] = 'error';
        $task['fail_count'] = (int)($task['fail_count'] ?? 0) + 1;
        $task['last_result'] = array('ok' => false, 'error' => 'Campaña, item o cuenta no disponibles.');
        $task['last_run_at'] = now_datetime();
        $task['updated_at'] = $task['last_run_at'];
        publicista_task_save($task);
        return array(false, $task, $task['last_result']);
    }

    $phone = publicista_campaign_item_resolve_phone($item);
    if (!$phone) {
        $task['estado'] = 'error';
        $task['fail_count'] = (int)($task['fail_count'] ?? 0) + 1;
        $task['last_result'] = array('ok' => false, 'error' => 'No hay teléfono asignado para el free bump.');
        $task['last_run_at'] = now_datetime();
        $task['updated_at'] = $task['last_run_at'];
        publicista_task_save($task);
        return array(false, $task, $task['last_result']);
    }

    if (!publicista_require_automation_adapter(trim((string)($task['portal_code'] ?? 'destacamos')), 'free_bump')) {
        $task['estado'] = 'error';
        $task['fail_count'] = (int)($task['fail_count'] ?? 0) + 1;
        $task['last_result'] = array('ok' => false, 'error' => 'No hay adaptador de free bump para este portal.');
        $task['last_run_at'] = now_datetime();
        $task['updated_at'] = $task['last_run_at'];
        publicista_task_save($task);
        return array(false, $task, $task['last_result']);
    }

    $payload = array(
        'username' => trim((string)($account['login_user'] ?? '')),
        'password' => trim((string)($account['login_pass'] ?? '')),
        'telefono' => trim((string)($phone['tfono'] ?? '')),
        'headless' => !empty($options['headless']) || !isset($options['headless']),
        'timeoutMs' => max(30000, (int)($options['timeoutMs'] ?? 45000)),
    );
    $result = subirGratis($payload);
    $result['executed_at'] = now_datetime();
    $task['last_result'] = $result;
    $task['last_run_at'] = $result['executed_at'];
    $task['updated_at'] = $task['last_run_at'];

    $times = array();
    $rule = trim((string)($task['frequency_rule'] ?? ''));
    if (strpos($rule, 'times:') === 0) $times = array_filter(array_map('trim', explode(',', substr($rule, 6))));

    if (!empty($result['ok'])) {
        $task['estado'] = 'active';
        $task['next_run_at'] = publicista_task_next_run_at_from_times($times);
        $account['last_success_at'] = now_datetime();
        $account['last_used_at'] = $account['last_success_at'];
        $account['last_error_at'] = '';
        $account['last_error'] = '';
        publicista_account_upsert($account);
    } else {
        $task['estado'] = 'error';
        $task['fail_count'] = (int)($task['fail_count'] ?? 0) + 1;
        $task['next_run_at'] = date('Y-m-d H:i:s', time() + 3600);
        $account['last_error_at'] = now_datetime();
        $account['last_error'] = trim((string)($result['error'] ?? 'Error no especificado en free bump.'));
        $account['last_used_at'] = $account['last_error_at'];
        publicista_account_upsert($account);
    }

    list($ok, $savedTask) = publicista_task_save($task);
    return array(!empty($result['ok']) && $ok, $savedTask, $result);
}

function publicista_campaign_execute($campaignId, $options = array()) {
    $campaign = publicista_campaign_get($campaignId);
    if (!$campaign) return array(false, null, null, array('error' => 'No se encontró la campaña.'));
    $campaignStatus = trim((string)($campaign['estado'] ?? 'draft'));
    if (!in_array($campaignStatus, array('generated','approved','completed','error','uploading'), true)) {
        return array(false, $campaign, null, array('error' => 'Antes de subir anuncios debes generar la composición de la campaña.'));
    }
    $items = publicista_campaign_items_for_campaign($campaignId);
    if (empty($items)) return array(false, $campaign, null, array('error' => 'La campaña no tiene items generados.'));

    $approvalSnapshot = is_array($campaign['approval_snapshot'] ?? null) ? $campaign['approval_snapshot'] : array();
    if ($campaignStatus === 'generated' || empty($approvalSnapshot['approved_at'])) {
        $campaign['estado'] = 'approved';
        $campaign['approval_snapshot'] = array(
            'approved_at' => now_datetime(),
            'approved_items_count' => count($items),
            'approved_by' => 'system',
            'approval_mode' => 'auto_before_execution',
        );
        $campaign['updated_at'] = now_datetime();
        publicista_campaign_save($campaign);
    }

    $humanize = isset($options['humanize']) && is_array($options['humanize']) ? array_merge(publicista_campaign_humanization_defaults(), $options['humanize']) : publicista_campaign_humanization_defaults();

    $runId = trim((string)($options['run_id'] ?? ''));
    $savedRun = $runId !== '' ? publicista_run_get($runId) : null;
    if (!$savedRun) {
        $run = publicista_run_defaults();
        $run['campaign_id'] = $campaignId;
        $run['run_type'] = 'campaign_upload';
        $run['created_at'] = now_datetime();
        $run['updated_at'] = $run['created_at'];
        list($_ok, $savedRun) = publicista_run_save($run);
    }

    $savedRun['estado'] = 'running';
    $savedRun['summary'] = 'Subida de anuncios iniciada.';
    $savedRun['started_at'] = trim((string)($savedRun['started_at'] ?? '')) !== '' ? $savedRun['started_at'] : now_datetime();
    $savedRun['finished_at'] = '';
    $savedRun['items'] = array();
    $savedRun['progress'] = array(
        'total_items' => count($items),
        'processed_items' => 0,
        'published' => 0,
        'failed' => 0,
        'current_item_id' => '',
        'current_listing_id' => '',
        'current_account_id' => '',
    );
    $savedRun['pipeline'] = array(
        'status' => 'running',
        'stage' => 'uploading',
        'summary' => 'La campaña está subiendo anuncios.',
    );
    $savedRun['updated_at'] = now_datetime();
    list($_ok, $savedRun) = publicista_run_save($savedRun);

    $campaign['estado'] = 'uploading';
    $campaign['updated_at'] = now_datetime();
    $campaign['execution_summary'] = array_merge((array)($campaign['execution_summary'] ?? array()), array(
        'last_phase' => 'uploading',
        'humanize' => $humanize,
        'last_run_id' => $savedRun['id'],
        'last_run_status' => 'running',
        'last_run_total' => count($items),
        'last_run_processed' => 0,
        'last_run_published' => 0,
        'last_run_failed' => 0,
        'last_upload_started_at' => now_datetime(),
    ));
    publicista_campaign_save($campaign);

    $autoRotationMode = !empty($options['auto_rotation']);
    if ($autoRotationMode) {
        $rotationSeed = trim((string)($savedRun['id'] ?? ''));
        if ($rotationSeed === '') {
            $rotationSeed = (string)microtime(true);
        }
        $items = publicista_campaign_items_round_robin_by_account_randomized($items, $rotationSeed);
    } else {
        $items = publicista_campaign_items_round_robin_by_account($items);
    }

    // --- Auto-aplicar reparto si hay distribution_matrix guardada ---
    $storedMatrix = is_array($campaign['distribution_matrix'] ?? null) ? $campaign['distribution_matrix'] : array();
    if (!empty($storedMatrix)) {
        list($rebalanceOk, $rebalancedCampaign, $rebalanceMeta) = publicista_campaign_rebalance_distribution($campaign, $storedMatrix);
        if ($rebalanceOk) {
            $campaign = $rebalancedCampaign;
            $items = publicista_campaign_items_for_campaign($campaignId);
            if ($autoRotationMode) {
                $rotationSeed = trim((string)($savedRun['id'] ?? ''));
                if ($rotationSeed === '') {
                    $rotationSeed = (string)microtime(true);
                }
                $items = publicista_campaign_items_round_robin_by_account_randomized($items, $rotationSeed);
            } else {
                $items = publicista_campaign_items_round_robin_by_account($items);
            }
            // Actualizar el progress total con el nuevo count
            $savedRun['progress']['total_items'] = count($items);
            $savedRun['updated_at'] = now_datetime();
            publicista_run_save($savedRun);
        } else {
            // Loguear warning pero continuar con items existentes
            $rebalanceErrors = is_array($rebalanceMeta['errors'] ?? null) ? $rebalanceMeta['errors'] : array();
            if (!empty($rebalanceErrors)) {
                $savedRun['summary'] = 'Aviso: no se pudo aplicar el reparto guardado. ' . implode(' ', $rebalanceErrors) . ' Se sube con reparto actual.';
                $savedRun['updated_at'] = now_datetime();
                publicista_run_save($savedRun);
            }
        }
    }

    // Auto-rotación: solo rotan anuncios de Destacamos. Los de Mundosex se suben una sola vez.
    if ($autoRotationMode) {
        $itemsBefore = count($items);
        $items = array_values(array_filter($items, function($item) {
            return trim((string)($item['portal_code'] ?? 'destacamos')) !== 'mundosex';
        }));
        if (count($items) < $itemsBefore) {
            $savedRun['summary'] = ($savedRun['summary'] ?? '') . ' [Auto-rotación: ' . ($itemsBefore - count($items)) . ' items Mundosex omitidos]';
        }
    }

    $results = array();
    $published = 0;
    $failed = 0;
    $softMismatchCount = 0;
    $hardFailedCount = 0;
    $businessRejectedCount = 0;
    $stopped = false;
    $stoppedAt = '';
    $lastAccountId = '';
    $isFirst = true;
    $seenCopyFingerprints = array();
    $activeSession = null;
    $activeSessionAccountId = '';
    $activeSessionPortalCode = '';

    try {
    foreach ($items as $idx => $item) {
        $liveRun = publicista_run_get($savedRun['id'] ?? '');
        if ($liveRun && publicista_run_stop_requested($liveRun)) {
            $stopped = true;
            $stoppedAt = now_datetime();
            $savedRun = $liveRun;
            break;
        }

        $currentAccountId = trim((string)($item['account_id'] ?? ''));
        $currentPortalCode = trim((string)($item['portal_code'] ?? 'destacamos'));
        if (!$isFirst) {
            $sleepUsec = publicista_campaign_humanize_delay_usecs($humanize['between_items_delay_min_sec'], $humanize['between_items_delay_max_sec']);
            usleep($sleepUsec);
        } else {
            $sleepUsec = publicista_campaign_humanize_delay_usecs($humanize['pre_publish_delay_min_sec'], $humanize['pre_publish_delay_max_sec']);
            usleep($sleepUsec);
        }

        if ($lastAccountId !== '' && $currentAccountId !== '' && $currentAccountId !== $lastAccountId) {
            usleep(publicista_campaign_humanize_delay_usecs(8, 20));
        }

        if ($activeSession && ($currentAccountId !== $activeSessionAccountId || $currentPortalCode !== $activeSessionPortalCode)) {
            if ($activeSessionPortalCode === 'destacamos' && publicista_require_automation_adapter($activeSessionPortalCode, 'publish') && function_exists('destacamos_http_cleanup_session')) {
                destacamos_http_cleanup_session($activeSession);
            }
            $activeSession = null;
            $activeSessionAccountId = '';
            $activeSessionPortalCode = '';
        }

        if (!$activeSession && $currentAccountId !== '' && $currentPortalCode === 'destacamos') {
            if (publicista_require_automation_adapter($currentPortalCode, 'publish') && function_exists('destacamos_http_session')) {
                $activeSession = destacamos_http_session((int)ceil(max(30000, (int)($options['timeoutMs'] ?? 90000)) / 1000));
                $activeSession['human'] = array_merge(destacamos_human_defaults(), $humanize, array('enabled' => true));
                $activeSessionAccountId = $currentAccountId;
                $activeSessionPortalCode = $currentPortalCode;
            }
        }

        $item['estado'] = 'queued';
        publicista_campaign_item_save($item);

        $itemOptions = array_merge($options, array('humanize' => $humanize));
        if ($activeSession && $currentAccountId === $activeSessionAccountId && $currentPortalCode === $activeSessionPortalCode) {
            $itemOptions['session'] = $activeSession;
        }
        $baseTitle = publicista_campaign_item_copy_title($item);
        $baseBody = publicista_campaign_item_copy_body($item);
        $retryHistory = array();
        $maxCopyAttempts = max(1, min(8, (int)($options['max_copy_retry_attempts'] ?? 6)));
        $attemptNumber = 0;
        $okItem = false;
        $savedItem = $item;
        $result = array('ok' => false, 'error' => 'No se pudo ejecutar el item.');

        try {
            $lastErrorCode = '';
            while (true) {
                $attemptNumber++;
                $retryContext = array(
                    'attempt' => $attemptNumber - 1,
                    'error_code' => $lastErrorCode,
                );
                $copyPlan = publicista_campaign_prepare_unique_copy(
                    $seenCopyFingerprints,
                    $item,
                    $baseTitle,
                    $baseBody,
                    $retryContext
                );
                
                // Pre-check: si es content_moderation y tenemos la función de filtrado
                if ($lastErrorCode === 'content_moderation' && function_exists('destacamos_filter_moderation_words')) {
                    $filterMode = $attemptNumber >= 4 ? 'strict' : 'moderate';
                    $copyPlan['body'] = destacamos_filter_moderation_words($copyPlan['body'], $filterMode);
                    $copyPlan['title'] = destacamos_filter_moderation_words($copyPlan['title'], $filterMode);
                    $copyPlan['reason'] = $copyPlan['reason'] . ':moderation_filtered:' . $filterMode;
                }
                
                $itemOptions['field_overrides'] = array(
                    'title' => $copyPlan['title'],
                    'description' => $copyPlan['body'],
                );
                $itemOptions['copy_adjustment'] = $copyPlan;

                list($okItem, $savedItem, $result) = publicista_campaign_execute_item($campaign, $item, $itemOptions);
                if (!is_array($result)) {
                    $result = array(
                        'ok' => false,
                        'error_code' => 'invalid_result_shape',
                        'error' => 'Resultado no estructurado devuelto por el adaptador.',
                    );
                    $okItem = false;
                }

                if ($okItem || !publicista_campaign_result_requests_copy_retry($result) || $attemptNumber >= $maxCopyAttempts) {
                    break;
                }

                $lastErrorCode = trim((string)($result['error_code'] ?? 'duplicate_copy'));
                $retryHistory[] = array(
                    'attempt' => $attemptNumber,
                    'reason' => $lastErrorCode,
                    'error' => trim((string)($result['error'] ?? '')),
                    'validation_errors' => array_values((array)($result['validation_errors'] ?? array())),
                    'copy_adjustment' => $copyPlan,
                    'failed_at' => now_datetime(),
                );
                usleep(publicista_campaign_humanize_delay_usecs(3, 7));
            }
        } catch (Throwable $itemError) {
            $okItem = false;
            $result = array(
                'ok' => false,
                'error_code' => 'runtime_exception',
                'error' => 'Error inesperado durante la subida del item: ' . trim((string)$itemError->getMessage()),
                'exception_class' => get_class($itemError),
                'executed_at' => now_datetime(),
            );
            if (!empty($retryHistory)) {
                $result['retry_history'] = $retryHistory;
                $result['copy_attempts'] = $attemptNumber;
            }
            $savedItem = $item;
            $savedItem['estado'] = 'failed';
            $savedItem['publish_result'] = $result;
            $savedItem['updated_at'] = now_datetime();
            list($_saveOk, $savedItem) = publicista_campaign_item_save($savedItem);
        }

        if (!empty($retryHistory)) {
            if (!is_array($result)) {
                $result = array();
            }
            $result['copy_attempts'] = max($attemptNumber, 1);
            $result['retry_history'] = $retryHistory;
            if (!isset($result['payload_summary']) || !is_array($result['payload_summary'])) {
                $result['payload_summary'] = array();
            }
            $result['payload_summary']['copy_attempts'] = max($attemptNumber, 1);
            $result['payload_summary']['copy_adjustment'] = $itemOptions['copy_adjustment'] ?? array();
            $savedItem['publish_result'] = $result;
            $savedItem['updated_at'] = now_datetime();
            publicista_campaign_item_save($savedItem);
        }

        $results[] = array(
            'campaign_item_id' => $savedItem['id'] ?? ($item['id'] ?? ''),
            'ok' => !empty($okItem),
            'estado' => $savedItem['estado'] ?? ($item['estado'] ?? ''),
            'result' => $result,
        );
        if ($okItem) $published++;
        else $failed++;
        if (is_array($result['save_soft_mismatch'] ?? null) && !empty($result['save_soft_mismatch'])) {
            $softMismatchCount++;
        }
        $failureKind = publicista_result_failure_kind($result);
        if ($failureKind === 'business_rejected') {
            $businessRejectedCount++;
        } elseif ($failureKind === 'hard_failed') {
            $hardFailedCount++;
        }

        $savedRun['items'] = $results;
        $savedRun['summary'] = 'Procesados ' . count($results) . '/' . count($items) . ' anuncios. OK: ' . $published . ' · Fallidos: ' . $failed . ' · Hard: ' . $hardFailedCount . ' · Rechazos negocio: ' . $businessRejectedCount . ($softMismatchCount > 0 ? (' · Avisos de guardado: ' . $softMismatchCount) : '') . '.';
        $savedRun['progress'] = array(
            'total_items' => count($items),
            'processed_items' => count($results),
            'published' => $published,
            'failed' => $failed,
            'current_item_id' => trim((string)($savedItem['id'] ?? ($item['id'] ?? ''))),
            'current_listing_id' => trim((string)($savedItem['external_ad_id'] ?? ($item['external_ad_id'] ?? ''))),
            'current_account_id' => $currentAccountId,
        );
        $savedRun['pipeline'] = array(
            'status' => 'running',
            'stage' => 'uploading',
            'summary' => 'Subiendo anuncio ' . ($idx + 1) . ' de ' . count($items) . '.',
        );
        $savedRun['updated_at'] = now_datetime();
        publicista_run_save($savedRun);

        $campaign = publicista_campaign_get($campaignId) ?: $campaign;
        $campaign['execution_summary'] = array_merge((array)($campaign['execution_summary'] ?? array()), array(
            'last_run_id' => $savedRun['id'],
            'last_run_status' => 'running',
            'last_run_total' => count($items),
            'last_run_processed' => count($results),
            'last_run_published' => $published,
            'last_run_failed' => $failed,
            'last_run_hard_failed_count' => $hardFailedCount,
            'last_run_business_rejected_count' => $businessRejectedCount,
            'last_run_soft_mismatch_count' => $softMismatchCount,
            'last_phase' => 'uploading',
            'last_upload_heartbeat_at' => now_datetime(),
        ));
        $campaign['updated_at'] = now_datetime();
        publicista_campaign_save($campaign);

        $lastAccountId = $currentAccountId;
        $isFirst = false;
    }
    } finally {
        if ($activeSession && $activeSessionPortalCode === 'destacamos' && publicista_require_automation_adapter($activeSessionPortalCode, 'publish') && function_exists('destacamos_http_cleanup_session')) {
            destacamos_http_cleanup_session($activeSession);
        }
    }

    $campaign = publicista_campaign_get($campaignId) ?: $campaign;
    $runStatus = $failed > 0 ? ($published > 0 ? 'completed' : 'failed') : 'completed';
    $campaignStatus = $failed > 0 ? ($published > 0 ? 'completed' : 'error') : 'completed';
    $lastPhase = 'uploaded';
    $runSummary = 'Subidos: ' . $published . ' · Fallidos: ' . $failed . ' · Hard: ' . $hardFailedCount . ' · Rechazos negocio: ' . $businessRejectedCount . ($softMismatchCount > 0 ? (' · Avisos de guardado: ' . $softMismatchCount) : '');

    if ($stopped) {
        $runStatus = 'cancelled';
        $campaignStatus = 'paused';
        $lastPhase = 'stopped';
        $runSummary = 'Detenida manualmente. Subidos: ' . $published . ' · Fallidos: ' . $failed . ' · Hard: ' . $hardFailedCount . ' · Rechazos negocio: ' . $businessRejectedCount . ($softMismatchCount > 0 ? (' · Avisos de guardado: ' . $softMismatchCount) : '');
    }

    $campaign['execution_summary'] = array_merge((array)($campaign['execution_summary'] ?? array()), array(
        'last_run_id' => $savedRun['id'],
        'last_run_at' => now_datetime(),
        'last_run_status' => $runStatus,
        'last_run_total' => count($items),
        'last_run_processed' => count($results),
        'last_run_published' => $published,
        'last_run_failed' => $failed,
        'last_run_hard_failed_count' => $hardFailedCount,
        'last_run_business_rejected_count' => $businessRejectedCount,
        'last_run_soft_mismatch_count' => $softMismatchCount,
        'last_phase' => $lastPhase,
        'last_upload_finished_at' => now_datetime(),
        'last_stop_acknowledged_at' => $stopped ? ($stoppedAt !== '' ? $stoppedAt : now_datetime()) : trim((string)($campaign['execution_summary']['last_stop_acknowledged_at'] ?? '')),
    ));
    $campaign['estado'] = $campaignStatus;
    $campaign['updated_at'] = now_datetime();
    publicista_campaign_save($campaign);

    // Sync to girlsconf after successful publish
    $portalCode = trim((string)($campaign['planning_snapshot']['data']['portal_code'] ?? 'destacamos'));
    if ($published > 0 && in_array($portalCode, array('destacamos', 'mundosex'), true)) {
        if (function_exists('publicista_sync_girlsconf_to_girlsconf')) {
            try {
                publicista_sync_girlsconf_to_girlsconf($campaignId);
            } catch (Throwable $e) {
                if (function_exists('bootstrap_runtime_log_exception')) {
                    bootstrap_runtime_log_exception('publicista_sync_girlsconf', $e);
                }
            }
        }
    }

    $savedRun['estado'] = $runStatus;
    $savedRun['finished_at'] = now_datetime();
    $savedRun['summary'] = $runSummary;
    $savedRun['hard_failed_count'] = $hardFailedCount;
    $savedRun['business_rejected_count'] = $businessRejectedCount;
    $savedRun['save_soft_mismatch_count'] = $softMismatchCount;
    if ($stopped) {
        $savedRun['stop_acknowledged_at'] = $stoppedAt !== '' ? $stoppedAt : $savedRun['finished_at'];
    }
    $savedRun['human_report'] = publicista_campaign_build_human_report($campaign, $savedRun, array(
        'published' => $published,
        'failed' => $failed,
        'results' => $results,
    ));
    $savedRun['items'] = $results;
    $savedRun['progress'] = array(
        'total_items' => count($items),
        'processed_items' => count($results),
        'published' => $published,
        'failed' => $failed,
        'current_item_id' => '',
        'current_listing_id' => '',
        'current_account_id' => '',
    );
    $savedRun['pipeline'] = array(
        'status' => $savedRun['estado'] === 'failed' ? 'error' : 'done',
        'stage' => $stopped ? 'stopped' : 'completed',
        'summary' => $savedRun['summary'],
    );
    $savedRun['updated_at'] = $savedRun['finished_at'];
    publicista_run_save($savedRun);

    return array($failed === 0, $campaign, $savedRun, array(
        'published' => $published,
        'failed' => $failed,
        'hard_failed_count' => $hardFailedCount,
        'business_rejected_count' => $businessRejectedCount,
        'save_soft_mismatch_count' => $softMismatchCount,
        'results' => $results,
        'stopped' => $stopped,
        'human_report' => $savedRun['human_report'],
    ));
}

function publicista_result_failure_kind($result) {
    $result = is_array($result) ? $result : array();
    if (!empty($result['ok'])) return 'ok';
    if (is_array($result['save_soft_mismatch'] ?? null) && !empty($result['save_soft_mismatch'])) return 'soft_warning';
    $code = trim((string)($result['error_code'] ?? ''));
    if (in_array($code, array('duplicate_copy', 'content_moderation', 'validation_error', 'missing_required'), true)) {
        return 'business_rejected';
    }
    return 'hard_failed';
}

function publicista_campaign_items_round_robin_by_account($items) {
    if (!is_array($items) || count($items) <= 1) {
        return is_array($items) ? $items : array();
    }

    // 1) Orden determinista base por cuenta y anuncio (estable con índice original)
    $indexed = array();
    foreach ($items as $index => $item) {
        $item['__rr_original_index'] = (int)$index;
        $indexed[] = $item;
    }

    usort($indexed, function($a, $b) {
        $aAccount = trim((string)($a['account_id'] ?? ''));
        $bAccount = trim((string)($b['account_id'] ?? ''));
        if ($aAccount !== $bAccount) {
            return strcmp($aAccount, $bAccount);
        }

        $aExternal = trim((string)($a['external_ad_id'] ?? ''));
        $bExternal = trim((string)($b['external_ad_id'] ?? ''));
        if ($aExternal !== $bExternal) {
            return strcmp($aExternal, $bExternal);
        }

        $aIndex = (int)($a['__rr_original_index'] ?? 0);
        $bIndex = (int)($b['__rr_original_index'] ?? 0);
        if ($aIndex === $bIndex) {
            return 0;
        }
        return ($aIndex < $bIndex) ? -1 : 1;
    });

    // 2) Buckets por cuenta, preservando orden interno
    $buckets = array();
    $accountOrder = array();
    foreach ($indexed as $item) {
        $accountId = trim((string)($item['account_id'] ?? ''));
        if (!isset($buckets[$accountId])) {
            $buckets[$accountId] = array();
            $accountOrder[] = $accountId;
        }
        $buckets[$accountId][] = $item;
    }

    // 3) Round-robin equitativo entre cuentas hasta vaciar buckets
    $out = array();
    while (true) {
        $advanced = false;
        foreach ($accountOrder as $accountId) {
            if (empty($buckets[$accountId])) {
                continue;
            }
            $advanced = true;
            $next = array_shift($buckets[$accountId]);
            unset($next['__rr_original_index']);
            $out[] = $next;
        }
        if (!$advanced) {
            break;
        }
    }

    return $out;
}

function publicista_campaign_items_round_robin_by_account_randomized($items, $seed = null) {
    $ordered = publicista_campaign_items_round_robin_by_account($items);
    if (!is_array($ordered) || count($ordered) <= 1) {
        return is_array($ordered) ? $ordered : array();
    }

    $accountBuckets = array();
    $accountOrder = array();
    foreach ($ordered as $item) {
        $accountId = trim((string)($item['account_id'] ?? ''));
        if (!isset($accountBuckets[$accountId])) {
            $accountBuckets[$accountId] = array();
            $accountOrder[] = $accountId;
        }
        $accountBuckets[$accountId][] = $item;
    }

    $seedInt = null;
    if ($seed !== null) {
        $seedInt = abs((int)crc32((string)$seed));
    }
    if ($seedInt !== null) {
        mt_srand($seedInt);
    }

    shuffle($accountOrder);
    foreach ($accountBuckets as $aid => $bucket) {
        shuffle($bucket);
        $accountBuckets[$aid] = $bucket;
    }

    if ($seedInt !== null) {
        mt_srand();
    }

    $out = array();
    while (true) {
        $advanced = false;
        foreach ($accountOrder as $aid) {
            if (empty($accountBuckets[$aid])) {
                continue;
            }
            $advanced = true;
            $out[] = array_shift($accountBuckets[$aid]);
        }
        if (!$advanced) break;
    }

    return $out;
}

function publicista_runs_for_campaign($campaignId) {
    $campaignId = trim((string)$campaignId);
    $out = array();
    foreach (storage_read_filtered('publicista_runs.json', function($row) use ($campaignId) {
        return trim((string)($row['campaign_id'] ?? '')) === $campaignId;
    }) as $run) {
        $out[] = publicista_run_normalize($run);
    }
    return sort_desc_by_key($out, 'updated_at');
}

function publicista_campaign_item_counts_by_campaign() {
    $counts = array();
    storage_walk_rows('publicista_campaign_items.json', function($row) use (&$counts) {
        $campaignId = trim((string)($row['campaign_id'] ?? ''));
        if ($campaignId === '') {
            return;
        }
        if (!isset($counts[$campaignId])) {
            $counts[$campaignId] = 0;
        }
        $counts[$campaignId]++;
    });
    return $counts;
}

function publicista_tasks_run_due($campaignId = '') {
    $campaignId = trim((string)$campaignId);
    $tasks = publicista_tasks_get();
    $executed = array();
    foreach ($tasks as $task) {
        if ($campaignId !== '' && ($task['campaign_id'] ?? '') !== $campaignId) continue;
        if (!publicista_task_due($task)) continue;
        list($ok, $savedTask, $result) = publicista_task_execute($task['id']);
        $executed[] = array('ok' => $ok, 'task' => $savedTask, 'result' => $result);
    }
    return $executed;
}

function publicista_root_dir() {
    return DATA_PATH . '/publicista';
}

function publicista_jobs_root_dir() {
    return publicista_root_dir() . '/jobs';
}

function publicista_templates_root_dir() {
    return publicista_root_dir() . '/templates';
}

function publicista_ensure_dir($path) {
    if (is_dir($path)) return true;
    return @mkdir($path, 0775, true) || is_dir($path);
}

function publicista_ensure_base_dirs() {
    $dirs = array(
        publicista_root_dir(),
        publicista_jobs_root_dir(),
        publicista_templates_root_dir(),
    );

    foreach ($dirs as $dir) {
        publicista_ensure_dir($dir);
    }
}

function publicista_build_job_asset_dirs($id) {
    $id = trim((string)$id);
    $baseRel = 'data/publicista/jobs/' . $id;

    return array(
        'job_root' => $baseRel,
        'originals_dir' => $baseRel . '/original',
        'candidates_dir' => $baseRel . '/candidates',
        'finals_dir' => $baseRel . '/finals',
        'meta_dir' => $baseRel . '/meta',
        'logs_dir' => $baseRel . '/logs',
        'reals_dir' => $baseRel . '/reals',
    );
}

function publicista_job_fs_paths($id) {
    $id = trim((string)$id);
    $base = publicista_jobs_root_dir() . '/' . $id;

    return array(
        'job_root' => $base,
        'originals_dir' => $base . '/original',
        'candidates_dir' => $base . '/candidates',
        'finals_dir' => $base . '/finals',
        'meta_dir' => $base . '/meta',
        'logs_dir' => $base . '/logs',
        'reals_dir' => $base . '/reals',
    );
}

function publicista_ensure_job_dirs($id) {
    publicista_ensure_base_dirs();
    $paths = publicista_job_fs_paths($id);
    foreach ($paths as $path) {
        if (!publicista_ensure_dir($path)) {
            return false;
        }
    }
    return true;
}

function publicista_remove_dir_recursive($dir) {
    $dir = trim((string)$dir);
    if ($dir === '' || !file_exists($dir)) return true;
    if (!is_dir($dir)) return @unlink($dir);

    $items = @scandir($dir);
    if ($items === false) return false;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            if (!publicista_remove_dir_recursive($path)) return false;
        } else {
            if (!@unlink($path)) return false;
        }
    }

    return @rmdir($dir);
}

function publicista_job_defaults($id = '') {
    $id = trim((string)$id);
    if ($id === '') {
        $id = generate_id('pubjob');
    }

    return array(
        'id' => $id,
        'clienta_id' => '',
        'clienta_scope' => 'lamami',
        'clienta_nombre_snapshot' => '',
        'publish_name' => '',
        'nombre_trabajo' => '',
        'estado' => 'draft',
        'notas' => '',
        'physical_notes' => '',
        'services_snapshot' => '',
        'tarifas_snapshot' => '',
        'localidad_snapshot' => '',
        'provincia_snapshot' => '',
        'source_image' => array(
            'original_filename' => '',
            'stored_path' => '',
            'mime_type' => '',
            'size_bytes' => 0,
            'width' => 0,
            'height' => 0,
            'uploaded_at' => '',
        ),
        'asset_dirs' => publicista_build_job_asset_dirs($id),
        'models' => array(
            'descriptor' => '',
            'image' => '',
        ),
        'processing' => array(
            'last_action' => '',
            'last_started_at' => '',
            'last_finished_at' => '',
            'last_error' => '',
            'last_error_at' => '',
            'last_openai_request_id' => '',
            'last_openai_http_code' => 0,
        ),
        'local_assets' => array(
            'analysis_json_path' => '',
            'prepared_square_path' => '',
            'face_blur_path' => '',
            'preview_path' => '',
            'prepared_at' => '',
            'worker_command' => '',
            'worker_result' => array(),
        ),
        'descriptor' => array(
            'model' => '',
            'request_id' => '',
            'http_code' => 0,
            'raw_response_path' => '',
            'parsed_json_path' => '',
            'summary' => '',
            'data' => array(),
        ),
        'prompt_master' => array(
            'built_at' => '',
            'text' => '',
            'variants' => array(),
            'path' => '',
        ),
        'pipeline' => array(
            'run_id' => '',
            'started_at' => '',
            'finished_at' => '',
            'status' => '',
            'summary' => '',
            'mode' => 'max_saving',
            'stage' => '',
            'selected_candidate_ids' => array(),
            'final_candidate_ids' => array(),
            'total_generated' => 0,
            'total_selected' => 0,
            'batch' => array(
                'image_batch_id' => '',
                'input_file_id' => '',
                'output_file_id' => '',
                'error_file_id' => '',
                'status' => '',
                'submitted_at' => '',
                'last_checked_at' => '',
                'completed_at' => '',
                'custom_ids' => array(),
                'result_jsonl_path' => '',
                'errors_jsonl_path' => '',
            ),
        ),
        'workflow' => array(
            'restrictions_text' => '',
            'restriction_flags' => array(),
            'auto_regenerate' => 0,
            'pack_final' => 0,
            'pack_finalized_at' => '',
            'pack_final_note' => '',
        ),
        'product_profile' => array(
            'nombre_publico' => '',
            'producto_estado' => 'draft',
            'ready_for_campaign' => 0,
            'ready_for_campaign_at' => '',
            'portal_codes' => array(),
            'internal_tags' => array(),
            'notes' => '',
        ),
        'copy_pack' => array(
            'desired_tone' => 'equilibrado',
            'examples_base' => '',
            'current_version_id' => '',
            'current_summary' => '',
            'current_export_text' => '',
            'current_export_txt_path' => '',
            'current_export_json_path' => '',
            'generated_at' => '',
            'last_error' => '',
            'last_error_at' => '',
            'retry_count' => 0,
            'versions' => array(),
        ),
        'costs' => array(
            'response_calls_count' => 0,
            'image_generations_count' => 0,
            'batch_jobs_count' => 0,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cached_input_tokens' => 0,
            'estimated_usd_responses' => 0,
            'estimated_usd_images' => 0,
            'estimated_usd_total' => 0,
            'last_breakdown' => array(),
            'last_cost_update_at' => '',
        ),
        'real_photos' => array(),
        'candidates' => array(),
        'final_images' => array(),
        'created_at' => '',
        'updated_at' => '',
    );
}

function publicista_normalize_status($status) {
    $status = trim((string)$status);
    if ($status === 'configured') {
        $status = 'needs_review';
    }
    $allowed = array_keys(publicista_job_status_options());
    return in_array($status, $allowed, true) ? $status : 'draft';
}

function publicista_jobs_get() {
    $rows = storage_read('publicista_jobs.json');
    $out = array();

    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $defaults = publicista_job_defaults(isset($row['id']) ? $row['id'] : '');
        $merged = array_merge($defaults, $row);
        if (!isset($merged['product_profile']) || !is_array($merged['product_profile'])) {
            $merged['product_profile'] = $defaults['product_profile'];
        } else {
            $merged['product_profile'] = array_merge($defaults['product_profile'], $merged['product_profile']);
        }
        if (!isset($merged['real_photos']) || !is_array($merged['real_photos'])) {
            $merged['real_photos'] = $defaults['real_photos'];
        }
        $merged['estado'] = publicista_normalize_status($merged['estado']);
        $merged['asset_dirs'] = publicista_build_job_asset_dirs($merged['id']);
        $out[] = $merged;
    }

    return sort_desc_by_key($out, 'updated_at');
}

function publicista_job_get($id) {
    $id = trim((string)$id);
    if ($id === '') return null;

    foreach (publicista_jobs_get() as $row) {
        if (($row['id'] ?? '') === $id) {
            return $row;
        }
    }

    return null;
}

function publicista_job_save($row) {
    $row = is_array($row) ? $row : array();
    $id = trim((string)($row['id'] ?? ''));
    $existing = $id !== '' ? publicista_job_get($id) : null;

    if ($id === '') {
        $id = generate_id('pubjob');
    }

    $base = publicista_job_defaults($id);
    $merged = array_merge($base, $existing ?: array(), $row);
    $merged['id'] = $id;
    $merged['estado'] = publicista_normalize_status($merged['estado'] ?? 'draft');
    $merged['asset_dirs'] = publicista_build_job_asset_dirs($id);

    if (!isset($merged['source_image']) || !is_array($merged['source_image'])) {
        $merged['source_image'] = $base['source_image'];
    } else {
        $merged['source_image'] = array_merge($base['source_image'], $merged['source_image']);
    }

    if (!isset($merged['models']) || !is_array($merged['models'])) {
        $merged['models'] = $base['models'];
    } else {
        $merged['models'] = array_merge($base['models'], $merged['models']);
    }

    if (!isset($merged['processing']) || !is_array($merged['processing'])) {
        $merged['processing'] = $base['processing'];
    } else {
        $merged['processing'] = array_merge($base['processing'], $merged['processing']);
    }

    if (!isset($merged['local_assets']) || !is_array($merged['local_assets'])) {
        $merged['local_assets'] = $base['local_assets'];
    } else {
        $merged['local_assets'] = array_merge($base['local_assets'], $merged['local_assets']);
    }

    if (!isset($merged['descriptor']) || !is_array($merged['descriptor'])) {
        $merged['descriptor'] = $base['descriptor'];
    } else {
        $merged['descriptor'] = array_merge($base['descriptor'], $merged['descriptor']);
    }

    if (!isset($merged['prompt_master']) || !is_array($merged['prompt_master'])) {
        $merged['prompt_master'] = $base['prompt_master'];
    } else {
        $merged['prompt_master'] = array_merge($base['prompt_master'], $merged['prompt_master']);
    }

    if (!isset($merged['pipeline']) || !is_array($merged['pipeline'])) {
        $merged['pipeline'] = $base['pipeline'];
    } else {
        $merged['pipeline'] = array_merge($base['pipeline'], $merged['pipeline']);
    }
    if (!isset($merged['pipeline']['batch']) || !is_array($merged['pipeline']['batch'])) {
        $merged['pipeline']['batch'] = $base['pipeline']['batch'];
    } else {
        $merged['pipeline']['batch'] = array_merge($base['pipeline']['batch'], $merged['pipeline']['batch']);
    }

    if (!isset($merged['workflow']) || !is_array($merged['workflow'])) {
        $merged['workflow'] = $base['workflow'];
    } else {
        $merged['workflow'] = array_merge($base['workflow'], $merged['workflow']);
    }
    $merged['workflow']['restriction_flags'] = publicista_normalize_restriction_flags(isset($merged['workflow']['restriction_flags']) ? $merged['workflow']['restriction_flags'] : array());
    $merged['workflow']['auto_regenerate'] = !empty($merged['workflow']['auto_regenerate']) ? 1 : 0;
    $merged['workflow']['pack_final'] = !empty($merged['workflow']['pack_final']) ? 1 : 0;


    if (!isset($merged['product_profile']) || !is_array($merged['product_profile'])) {
        $merged['product_profile'] = $base['product_profile'];
    } else {
        $merged['product_profile'] = array_merge($base['product_profile'], $merged['product_profile']);
    }
    $merged['product_profile']['ready_for_campaign'] = !empty($merged['product_profile']['ready_for_campaign']) ? 1 : 0;
    if (!isset($merged['product_profile']['portal_codes']) || !is_array($merged['product_profile']['portal_codes'])) {
        $merged['product_profile']['portal_codes'] = array();
    }
    if (!isset($merged['product_profile']['internal_tags']) || !is_array($merged['product_profile']['internal_tags'])) {
        $merged['product_profile']['internal_tags'] = array();
    }

    if (!isset($merged['copy_pack']) || !is_array($merged['copy_pack'])) {
        $merged['copy_pack'] = $base['copy_pack'];
    } else {
        $merged['copy_pack'] = array_merge($base['copy_pack'], $merged['copy_pack']);
    }
    $merged['copy_pack']['desired_tone'] = trim((string)($merged['copy_pack']['desired_tone'] ?? 'equilibrado'));
    if ($merged['copy_pack']['desired_tone'] === '') {
        $merged['copy_pack']['desired_tone'] = 'equilibrado';
    }
    if (!isset($merged['copy_pack']['versions']) || !is_array($merged['copy_pack']['versions'])) {
        $merged['copy_pack']['versions'] = array();
    }
    $merged['copy_pack']['retry_count'] = (int)($merged['copy_pack']['retry_count'] ?? 0);

    if (!isset($merged['costs']) || !is_array($merged['costs'])) {
        $merged['costs'] = $base['costs'];
    } else {
        $merged['costs'] = array_merge($base['costs'], $merged['costs']);
    }
    if (!isset($merged['costs']['last_breakdown']) || !is_array($merged['costs']['last_breakdown'])) {
        $merged['costs']['last_breakdown'] = array();
    }
    $merged['costs']['batch_jobs_count'] = (int)($merged['costs']['batch_jobs_count'] ?? 0);

    if (!isset($merged['candidates']) || !is_array($merged['candidates'])) {
        $merged['candidates'] = $base['candidates'];
    }

    if (!isset($merged['final_images']) || !is_array($merged['final_images'])) {
        $merged['final_images'] = $base['final_images'];
    }

    if (trim((string)($merged['created_at'] ?? '')) === '') {
        $merged['created_at'] = now_datetime();
    }
    $merged['updated_at'] = now_datetime();

    if (!publicista_ensure_job_dirs($id)) {
        return array(false, 'No se pudieron crear las carpetas base del trabajo Publicista.');
    }

    storage_upsert('publicista_jobs.json', $merged);
    return array(true, $merged);
}

function publicista_job_delete($id) {
    $id = trim((string)$id);
    if ($id === '') return array(false, 'ID de trabajo vacío.');

    storage_delete('publicista_jobs.json', $id);
    $paths = publicista_job_fs_paths($id);
    if (!publicista_remove_dir_recursive($paths['job_root'])) {
        return array(false, 'Trabajo eliminado del JSON, pero no se pudo borrar su carpeta.');
    }

    return array(true, 'Trabajo eliminado.');
}

function publicista_jobs_for_clienta($clientaId) {
    $clientaId = trim((string)$clientaId);
    $out = array();
    foreach (publicista_jobs_get() as $row) {
        if (($row['clienta_id'] ?? '') === $clientaId) {
            $out[] = $row;
        }
    }
    return $out;
}

function clientes_index() {
    $items = storage_read('clientes.json');
    $idx = array();
    foreach ($items as $item) {
        $idx[$item['id']] = $item;
    }
    return $idx;
}

function bots_index() {
    $items = storage_read('bots.json');
    $idx = array();
    foreach ($items as $item) {
        $idx[$item['id']] = $item;
    }
    return $idx;
}

function settings_get() {
    return storage_read('settings.json');
}

function get_active_clientas() {
    $items = storage_read('clientes.json');
    $out = array();
    foreach ($items as $item) {
        if (isset($item['estado']) && $item['estado'] === 'alta') {
            $out[] = $item;
        }
    }
    return $out;
}

function get_clienta_current_bot($clientaId) {
    $clientaId = trim((string)$clientaId);
    if ($clientaId === '') {
        return null;
    }

    $bots = storage_read('bots.json');
    foreach ($bots as $bot) {
        if (bot_linked_type($bot) !== 'lamami_clienta') {
            continue;
        }
        if (bot_linked_id($bot) === $clientaId) {
            return $bot;
        }
    }
    return null;
}

function get_leads_for_clienta($clientaId) {
    $items = storage_read('leads.json');
    $out = array();
    foreach ($items as $item) {
        if (isset($item['cliente_id']) && $item['cliente_id'] === $clientaId) {
            $out[] = $item;
        }
    }
    return $out;
}

function get_leads_for_bot($botId) {
    $items = storage_read('leads.json');
    $out = array();
    foreach ($items as $item) {
        if (isset($item['bot_id']) && $item['bot_id'] === $botId) {
            $out[] = $item;
        }
    }
    return $out;
}

function lamamibot_defaults() {
    return array(
        'id' => 'lamamibot',
        'nombre_bot' => 'LamamiBot',
        'estado' => '',
        'telefonos_ids' => array(),
        'clientas_ids' => array(),
        'girlsconf_json_path' => '',
        'girlsconf_base_url' => '',
        'last_sync_at' => '',
        'last_sync_summary' => '',
        'generated_assets' => array(),
        'created_at' => '',
        'updated_at' => ''
    );
}

function lamamibot_get() {
    $raw = storage_read('lamamibot.json');
    if (!is_array($raw)) {
        $raw = array();
    }

    $row = array_merge(lamamibot_defaults(), $raw);

    if (!isset($row['telefonos_ids']) || !is_array($row['telefonos_ids'])) {
        $row['telefonos_ids'] = array();
    }
    if (!isset($row['clientas_ids']) || !is_array($row['clientas_ids'])) {
        $row['clientas_ids'] = array();
    }

    if (!isset($row['generated_assets']) || !is_array($row['generated_assets'])) {
        $row['generated_assets'] = array();
    }

    $row['generated_assets'] = lamamibot_clean_generated_assets($row['generated_assets']);

    return $row;
}

function lamamibot_save($row) {
    $existing = lamamibot_get();
    $merged = array_merge($existing, is_array($row) ? $row : array());

    $merged['id'] = 'lamamibot';

    $telefonosIds = array();
    foreach ((array)($merged['telefonos_ids'] ?? array()) as $id) {
        $id = trim((string)$id);
        if ($id === '') continue;
        if (!in_array($id, $telefonosIds, true)) {
            $telefonosIds[] = $id;
        }
    }

    $clientasIds = array();
    foreach ((array)($merged['clientas_ids'] ?? array()) as $id) {
        $id = trim((string)$id);
        if ($id === '') continue;
        if (!in_array($id, $clientasIds, true)) {
            $clientasIds[] = $id;
        }
    }

    $merged['telefonos_ids'] = $telefonosIds;
    $merged['clientas_ids'] = $clientasIds;

    if (!isset($merged['generated_assets']) || !is_array($merged['generated_assets'])) {
        $merged['generated_assets'] = array();
    }

    $merged['generated_assets'] = lamamibot_clean_generated_assets($merged['generated_assets']);

    if (trim((string)($merged['created_at'] ?? '')) === '') {
        $merged['created_at'] = now_datetime();
    }
    $merged['updated_at'] = now_datetime();

    storage_write('lamamibot.json', $merged);
}
