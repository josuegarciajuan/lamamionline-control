<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

function norm_visible(string $value): string {
    $out = '';
    $len = strlen($value);
    for ($i = 0; $i < $len; $i++) {
        $ch = $value[$i];
        $ord = ord($ch);
        if ($ord < 32 || $ord === 127) {
            $out .= sprintf('\\x%02X', $ord);
        } else {
            $out .= $ch;
        }
    }
    return $out;
}

function line(string $k, $v): void {
    if (is_array($v)) {
        echo $k . ': ' . json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return;
    }
    echo $k . ': ' . (string)$v . PHP_EOL;
}

$now = date('Y-m-d H:i:s');
echo '=== free_bump_diag ===' . PHP_EOL;
line('now', $now);
line('base_path', BASE_PATH);
line('data_path', DATA_PATH);
line('storage_backend_mode', storage_backend_mode());

$settings = storage_read('settings.json');
$cfg = publicista_free_bump_config();
$state = publicista_free_bump_state();
$rawAnunciosPath = DATA_PATH . '/anuncios.json';
$rawAnuncios = @file_get_contents($rawAnunciosPath);
$rawDecoded = is_string($rawAnuncios) ? json_decode($rawAnuncios, true) : null;
$rawRows = is_array($rawDecoded) ? $rawDecoded : array();

echo PHP_EOL . '--- Config groups ---' . PHP_EOL;
foreach ((array)($cfg['groups'] ?? array()) as $groupName => $groupData) {
    line('group', $groupName);
    line('  group_visible', norm_visible((string)$groupName));
    line('  group_hex', strtoupper(bin2hex((string)$groupName)));
    line('  enabled', (int)($groupData['enabled'] ?? 0));
    line('  start_time', (string)($groupData['start_time'] ?? ''));
    line('  end_time', (string)($groupData['end_time'] ?? ''));
}

echo PHP_EOL . '--- State ---' . PHP_EOL;
line('last_account_id', (string)($state['last_account_id'] ?? ''));
line('last_account_label', (string)($state['last_account_label'] ?? ''));
line('last_status', (string)($state['last_status'] ?? ''));
line('next_run_at', (string)($state['next_run_at'] ?? ''));

$allAccounts = publicista_accounts_get(false);
$destacamosAccounts = array_values(array_filter($allAccounts, static function(array $a): bool {
    return trim((string)($a['portal_code'] ?? '')) === 'destacamos';
}));
$selectedWithSkipped = publicista_free_bump_selected_accounts($cfg, true);
$selectedReadyOnly = publicista_free_bump_selected_accounts($cfg, false);

echo PHP_EOL . '--- Pipeline counters ---' . PHP_EOL;
line('raw_anuncios_rows', count($rawRows));
line('publicista_accounts_get_total', count($allAccounts));
line('publicista_accounts_get_destacamos', count($destacamosAccounts));
line('selected_include_skipped', count($selectedWithSkipped));
line('selected_ready_only', count($selectedReadyOnly));

echo PHP_EOL . '--- Destacamos active accounts (normalized) ---' . PHP_EOL;
foreach ($destacamosAccounts as $account) {
    if (trim((string)($account['estado'] ?? '')) !== 'active') {
        continue;
    }
    $displayName = trim((string)($account['display_name'] ?? ''));
    line('id', (string)($account['id'] ?? ''));
    line('  login', (string)($account['login_user'] ?? ''));
    line('  display_name', $displayName);
    line('  display_visible', norm_visible($displayName));
    line('  display_hex', strtoupper(bin2hex($displayName)));
    line('  estado', (string)($account['estado'] ?? ''));
    line('  portal_code', (string)($account['portal_code'] ?? ''));
    line('  has_group_exact_key', array_key_exists($displayName, (array)($cfg['groups'] ?? array())) ? 1 : 0);
}

echo PHP_EOL . '--- Selected includeSkipped details ---' . PHP_EOL;
foreach ($selectedWithSkipped as $account) {
    line('id', (string)($account['id'] ?? ''));
    line('  login', (string)($account['login_user'] ?? ''));
    line('  display_name', (string)($account['display_name'] ?? ''));
    line('  estado', (string)($account['estado'] ?? ''));
    line('  portal_code', (string)($account['portal_code'] ?? ''));
    line('  group_name', (string)($account['_group_name'] ?? ''));
    line('  ready', !empty($account['_free_bump_ready']) ? 1 : 0);
    line('  skip_reason', (string)($account['_free_bump_skip_reason'] ?? ''));
}

$targetId = 'anun_d727cb0d';
echo PHP_EOL . '--- Target account checks (' . $targetId . ') ---' . PHP_EOL;
$rawTarget = null;
foreach ($rawRows as $row) {
    if (trim((string)($row['id'] ?? '')) === $targetId) {
        $rawTarget = $row;
        break;
    }
}
line('in_raw_anuncios_json', $rawTarget ? 1 : 0);
if (is_array($rawTarget)) {
    line('  raw_login_user', (string)($rawTarget['login_user'] ?? ''));
    line('  raw_display_name', (string)($rawTarget['display_name'] ?? ''));
    line('  raw_estado', (string)($rawTarget['estado'] ?? ''));
    line('  raw_portal_code', (string)($rawTarget['portal_code'] ?? ''));
}

$normalizedTarget = publicista_account_get($targetId, false);
line('in_publicista_account_get', is_array($normalizedTarget) ? 1 : 0);
if (is_array($normalizedTarget)) {
    line('  norm_login_user', (string)($normalizedTarget['login_user'] ?? ''));
    line('  norm_display_name', (string)($normalizedTarget['display_name'] ?? ''));
    line('  norm_estado', (string)($normalizedTarget['estado'] ?? ''));
    line('  norm_portal_code', (string)($normalizedTarget['portal_code'] ?? ''));
}

$mysqlCheck = storage_mysql_read('anuncios.json');
echo PHP_EOL . '--- MySQL backend check for anuncios.json ---' . PHP_EOL;
line('mysql_ok', !empty($mysqlCheck['ok']) ? 1 : 0);
line('mysql_has_rows', !empty($mysqlCheck['has_rows']) ? 1 : 0);
line('mysql_rows_count', is_array($mysqlCheck['data'] ?? null) ? count($mysqlCheck['data']) : 0);
$mysqlTargetFound = 0;
if (is_array($mysqlCheck['data'] ?? null)) {
    foreach ($mysqlCheck['data'] as $row) {
        if (trim((string)($row['id'] ?? '')) === $targetId) {
            $mysqlTargetFound = 1;
            line('mysql_target_login_user', (string)($row['login_user'] ?? ''));
            line('mysql_target_display_name', (string)($row['display_name'] ?? ''));
            line('mysql_target_estado', (string)($row['estado'] ?? ''));
            line('mysql_target_portal_code', (string)($row['portal_code'] ?? ''));
            break;
        }
    }
}
line('mysql_has_target_id', $mysqlTargetFound);

$plan = publicista_free_bump_plan_snapshot($cfg, $state, time());
echo PHP_EOL . '--- Plan snapshot counters ---' . PHP_EOL;
line('plan_selected_accounts_count', (int)($plan['selected_accounts_count'] ?? 0));
line('plan_ready_accounts_count', (int)($plan['ready_accounts_count'] ?? 0));
line('plan_window_in_progress', !empty($plan['window_in_progress']) ? 1 : 0);
