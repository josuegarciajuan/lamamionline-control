<?php

function crm_db_default_config() {
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $config = array(
        'host' => 'localhost',
        'db' => 'telefonosbd',
        'user' => 'telefonosuser',
        'pass' => 'adfgAGD425#$df3',
        'charset' => 'utf8mb4',
    );

    $envMap = array(
        'CRM_DB_HOST' => 'host',
        'CRM_DB_NAME' => 'db',
        'CRM_DB_USER' => 'user',
        'CRM_DB_PASS' => 'pass',
        'CRM_DB_CHARSET' => 'charset',
    );

    foreach ($envMap as $envKey => $configKey) {
        $envValue = getenv($envKey);
        if (!is_string($envValue) || trim($envValue) === '') {
            continue;
        }
        $config[$configKey] = trim($envValue);
    }

    if (trim((string)$config['charset']) === '') {
        $config['charset'] = 'utf8mb4';
    }

    return $config;
}

function crm_db_quote_identifier($identifier) {
    $identifier = trim((string)$identifier);
    if ($identifier === '' || !preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        return '';
    }
    return '`' . $identifier . '`';
}

function crm_db_dsn($config) {
    $config = array_merge(crm_db_default_config(), is_array($config) ? $config : array());
    return 'mysql:host=' . $config['host'] . ';dbname=' . $config['db'] . ';charset=' . $config['charset'];
}

function crm_db_cache_key($config) {
    $config = array_merge(crm_db_default_config(), is_array($config) ? $config : array());
    return md5(implode('|', array(
        (string)$config['host'],
        (string)$config['db'],
        (string)$config['user'],
        (string)$config['pass'],
        (string)$config['charset'],
    )));
}

function crm_db_log_message($message) {
    $message = trim((string)$message);
    if ($message === '') {
        return;
    }
    if (function_exists('bootstrap_runtime_log')) {
        bootstrap_runtime_log('crm_db | ' . $message);
    }
}

function crm_db_connect($config = null) {
    $config = array_merge(crm_db_default_config(), is_array($config) ? $config : array());
    if (!class_exists('PDO')) {
        crm_db_log_message('PDO no disponible en este entorno.');
        return null;
    }

    $cacheKey = crm_db_cache_key($config);
    if (!isset($GLOBALS['crm_db_pdo_cache']) || !is_array($GLOBALS['crm_db_pdo_cache'])) {
        $GLOBALS['crm_db_pdo_cache'] = array();
    }
    if (array_key_exists($cacheKey, $GLOBALS['crm_db_pdo_cache'])) {
        $cached = $GLOBALS['crm_db_pdo_cache'][$cacheKey];
        return ($cached instanceof PDO) ? $cached : null;
    }

    try {
        $pdo = new PDO(
            crm_db_dsn($config),
            (string)$config['user'],
            (string)$config['pass'],
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            )
        );
        $GLOBALS['crm_db_pdo_cache'][$cacheKey] = $pdo;
        return $pdo;
    } catch (Exception $e) {
        $GLOBALS['crm_db_pdo_cache'][$cacheKey] = null;
        crm_db_log_message('Conexion fallida a ' . (string)$config['db'] . '@' . (string)$config['host'] . ' | ' . $e->getMessage());
        return null;
    }
}

function crm_db() {
    return crm_db_connect();
}

function crm_db_query_all($sql, $params = array(), $pdo = null) {
    $pdo = ($pdo instanceof PDO) ? $pdo : crm_db();
    if (!$pdo) {
        return array();
    }
    $stmt = $pdo->prepare((string)$sql);
    $stmt->execute(array_values((array)$params));
    $rows = $stmt->fetchAll();
    return is_array($rows) ? $rows : array();
}

function crm_db_query_one($sql, $params = array(), $pdo = null) {
    $rows = crm_db_query_all($sql, $params, $pdo);
    return isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
}

function crm_db_execute($sql, $params = array(), $pdo = null) {
    $pdo = ($pdo instanceof PDO) ? $pdo : crm_db();
    if (!$pdo) {
        return false;
    }
    $stmt = $pdo->prepare((string)$sql);
    return $stmt->execute(array_values((array)$params));
}

function crm_db_table_exists($table, $pdo = null) {
    $table = trim((string)$table);
    if ($table === '') {
        return false;
    }

    $pdo = ($pdo instanceof PDO) ? $pdo : crm_db();
    if (!$pdo) {
        return false;
    }

    $config = crm_db_default_config();
    $row = crm_db_query_one(
        'SELECT 1 AS ok FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1',
        array($config['db'], $table),
        $pdo
    );

    return is_array($row);
}

function crm_db_table_columns($table, $pdo = null) {
    $table = trim((string)$table);
    if ($table === '') {
        return array();
    }

    if (!isset($GLOBALS['crm_db_table_columns_cache']) || !is_array($GLOBALS['crm_db_table_columns_cache'])) {
        $GLOBALS['crm_db_table_columns_cache'] = array();
    }
    if (array_key_exists($table, $GLOBALS['crm_db_table_columns_cache'])) {
        $cached = $GLOBALS['crm_db_table_columns_cache'][$table];
        return is_array($cached) ? $cached : array();
    }

    $pdo = ($pdo instanceof PDO) ? $pdo : crm_db();
    if (!$pdo) {
        $GLOBALS['crm_db_table_columns_cache'][$table] = array();
        return array();
    }

    $config = crm_db_default_config();
    $rows = crm_db_query_all(
        'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY, IS_NULLABLE, EXTRA
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
         ORDER BY ORDINAL_POSITION ASC',
        array($config['db'], $table),
        $pdo
    );

    $out = array();
    foreach ($rows as $row) {
        $name = trim((string)($row['COLUMN_NAME'] ?? ''));
        if ($name === '') {
            continue;
        }
        $out[$name] = array(
            'data_type' => strtolower(trim((string)($row['DATA_TYPE'] ?? ''))),
            'column_key' => trim((string)($row['COLUMN_KEY'] ?? '')),
            'is_nullable' => strtoupper(trim((string)($row['IS_NULLABLE'] ?? 'YES'))) === 'YES',
            'extra' => strtolower(trim((string)($row['EXTRA'] ?? ''))),
        );
    }

    $GLOBALS['crm_db_table_columns_cache'][$table] = $out;
    return $out;
}
