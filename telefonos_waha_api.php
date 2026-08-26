<?php
/**
 * API administrativa para consultar y gestionar WAHA desde Josué > Teléfonos.
 *
 * GET:  status, qr (telefono_id)
 * POST: restart (telefono_id), identify (target_id), ambos con csrf_token
 */

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/telefonos_waha_service.php';
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');

if (!defined('TWA_WASAP_HOST')) define('TWA_WASAP_HOST', 'http://100.117.92.74');
if (!defined('TWA_WASAP_KEY')) define('TWA_WASAP_KEY', 'local321');

/** @param array<string,mixed> $payload */
function telefonos_waha_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo $json === false ? '{"ok":false,"error":"Error interno del servidor"}' : $json;
}

/** @return array<string,mixed> */
function telefonos_waha_personal_settings(): array
{
    return [
        'waha_host' => TWA_WASAP_HOST,
        'waha_api_key' => TWA_WASAP_KEY,
        'waha_session' => 'default',
        'curl_timeout_sec' => '8',
    ];
}

/** @param array<string,mixed> $config */
function telefonos_waha_get_status(array $config): array
{
    $response = comercial_waha_get_json(
        $config['settings'],
        $config['port'],
        'api/sessions/' . rawurlencode($config['session'])
    );
    if (!telefonos_waha_response_is_success($response)) {
        return ['ok' => false, 'http_status' => 502, 'error' => 'WAHA no responde', 'status' => 'UNREACHABLE', 'status_label' => 'No responde', 'status_icon' => '🔴', 'is_connected' => false, 'phone' => ''];
    }
    $data = json_decode((string)($response['body'] ?? ''), true);
    if (!is_array($data)) {
        return ['ok' => false, 'http_status' => 502, 'error' => 'Respuesta inválida de WAHA', 'status' => 'UNKNOWN', 'status_label' => 'Error', 'status_icon' => '🔴', 'is_connected' => false, 'phone' => ''];
    }
    $status = strtoupper(trim((string)($data['status'] ?? 'UNKNOWN')));
    $label = match ($status) {
        'WORKING', 'CONNECTED' => 'Conectado',
        'SCAN_QR_CODE' => 'Esperando QR',
        'STARTING' => 'Iniciando...',
        'STOPPED' => 'Detenido',
        'FAILED' => 'Falló — reiniciar',
        default => $status,
    };
    $icon = match ($status) {
        'WORKING', 'CONNECTED' => '🟢',
        'SCAN_QR_CODE' => '🟡',
        'STARTING' => '🟠',
        default => '🔴',
    };
    $me = $data['me'] ?? null;
    $phone = is_array($me) ? (telefonos_waha_phone_from_waha_id((string)($me['id'] ?? '')) ?? '') : '';
    return ['ok' => true, 'http_status' => 200, 'status' => $status, 'status_label' => $label, 'status_icon' => $icon, 'is_connected' => in_array($status, ['WORKING', 'CONNECTED'], true), 'phone' => $phone];
}

/** @param array<string,mixed> $config */
function telefonos_waha_get_qr(array $config): array
{
    $path = 'api/' . rawurlencode($config['session']) . '/auth/qr?format=image';
    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $response = comercial_waha_get_json($config['settings'], $config['port'], $path);
        if (telefonos_waha_response_is_success($response, [200])) {
            $data = json_decode((string)($response['body'] ?? ''), true);
            $qr = is_array($data) && is_string($data['data'] ?? null) ? $data['data'] : '';
            if ($qr !== '' && strlen($qr) <= 2 * 1024 * 1024) {
                return ['ok' => true, 'http_status' => 200, 'qr_base64' => $qr];
            }
        }
        if ($attempt < 3) usleep(400000);
    }
    return ['ok' => false, 'http_status' => 502, 'error' => 'No se pudo obtener el QR de WAHA'];
}

function telefonos_waha_dispatch_inner(): void
{
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $canManageTelefonos = auth_can_manage_telefonos();
    $access = telefonos_waha_authorize(is_logged_in(), $canManageTelefonos);
    if ($access['status'] !== 200) {
        telefonos_waha_json(['ok' => false, 'error' => $access['error']], $access['status']);
        return;
    }

    $action = trim((string)($method === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '')));
    if ($action === '') {
        telefonos_waha_json(['ok' => false, 'error' => 'Falta acción'], 400);
        return;
    }

    $mutating = in_array($action, ['restart', 'identify'], true);
    if ($mutating) {
        $validation = telefonos_waha_validate_mutation($method, csrf_validate((string)($_POST['csrf_token'] ?? '')));
        if ($validation['status'] !== 200) {
            if ($validation['status'] === 405) header('Allow: POST');
            telefonos_waha_json(['ok' => false, 'error' => $validation['error']], $validation['status']);
            return;
        }
    } elseif ($method !== 'GET') {
        header('Allow: GET');
        telefonos_waha_json(['ok' => false, 'error' => 'Método no permitido'], 405);
        return;
    }

    $rows = storage_read('telefonos.json');
    $rows = is_array($rows) ? $rows : [];
    $commercialSettings = comercial_get_settings();
    $personalSettings = telefonos_waha_personal_settings();

    if ($action === 'identify') {
        if (!isset($_SESSION['telefonos_waha_identify_success']) || !is_array($_SESSION['telefonos_waha_identify_success'])) {
            $_SESSION['telefonos_waha_identify_success'] = [];
        }
        $result = telefonos_waha_identify(
            trim((string)($_POST['target_id'] ?? '')),
            $rows,
            $commercialSettings,
            $personalSettings,
            static function (array $config, array $row): array {
                $config['settings']['curl_timeout_sec'] = '2';
                return comercial_waha_get_json($config['settings'], $config['port'], 'api/sessions/' . rawurlencode($config['session']));
            },
            static function (array $config, string $phone, string $message, array $row): array {
                $config['settings']['curl_timeout_sec'] = '3';
                return comercial_waha_post_json($config['settings'], $config['port'], 'api/sendText', [
                    'chatId' => $phone . '@c.us',
                    'text' => $message,
                    'session' => $config['session'],
                ]);
            },
            static function (string $key, int $now, int $window): ?array {
                $entry = $_SESSION['telefonos_waha_identify_success'][$key] ?? null;
                if (!is_array($entry) || (int)($entry['at'] ?? 0) < $now - $window || !is_array($entry['result'] ?? null)) return null;
                return $entry['result'];
            },
            static function (string $key, array $value, int $now): void {
                foreach ($_SESSION['telefonos_waha_identify_success'] as $storedKey => $entry) {
                    if (!is_array($entry) || (int)($entry['at'] ?? 0) < $now - TELEFONOS_WAHA_IDENTIFY_DEDUP_SECONDS) {
                        unset($_SESSION['telefonos_waha_identify_success'][$storedKey]);
                    }
                }
                $_SESSION['telefonos_waha_identify_success'][$key] = ['at' => $now, 'result' => $value];
            },
            ['on_error' => static function (string $stage, Throwable $error): void {
                bootstrap_runtime_log_exception('telefonos_waha_identify_' . $stage, $error);
            }]
        );
        $status = (int)($result['status'] ?? 500);
        unset($result['status']);
        telefonos_waha_json($result, $status);
        return;
    }

    $telefonoId = trim((string)($method === 'POST' ? ($_POST['telefono_id'] ?? '') : ($_GET['telefono_id'] ?? '')));
    $row = telefonos_waha_find_row($rows, $telefonoId);
    if ($row === null) {
        telefonos_waha_json(['ok' => false, 'error' => 'Teléfono no encontrado'], 404);
        return;
    }
    try {
        $config = telefonos_waha_line_config($row, $commercialSettings, $personalSettings);
    } catch (InvalidArgumentException $e) {
        telefonos_waha_json(['ok' => false, 'error' => $e->getMessage()], 400);
        return;
    }

    if ($action === 'status') {
        $result = telefonos_waha_get_status($config);
        $status = (int)$result['http_status'];
        unset($result['http_status']);
        telefonos_waha_json($result, $status);
        return;
    }
    if ($action === 'qr') {
        $result = telefonos_waha_get_qr($config);
        $status = (int)$result['http_status'];
        unset($result['http_status']);
        telefonos_waha_json($result, $status);
        return;
    }
    if ($action === 'restart') {
        $result = telefonos_waha_restart(
            $config,
            static function (array $cfg, string $requestMethod, string $path, ?array $payload): array {
                return comercial_waha_request_json($cfg['settings'], $cfg['port'], $requestMethod, $path, $payload);
            },
            static function (int $seconds): void { sleep($seconds); }
        );
        $status = (int)$result['status'];
        unset($result['status']);
        telefonos_waha_json($result, $status);
        return;
    }

    telefonos_waha_json(['ok' => false, 'error' => 'Acción desconocida'], 400);
}

function telefonos_waha_dispatch(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    try {
        telefonos_waha_dispatch_inner();
    } catch (Throwable $e) {
        bootstrap_runtime_log_exception('telefonos_waha_api', $e);
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        telefonos_waha_json(['ok' => false, 'error' => 'Error interno del servidor'], 500);
    }
}

if (!defined('TELEFONOS_WAHA_NO_DISPATCH')) telefonos_waha_dispatch();
