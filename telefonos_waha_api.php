<?php
/**
 * telefonos_waha_api.php — API para gestión WAHA de las líneas en telefonos.
 *
 * Endpoints:
 *   GET ?action=status&waha_port=X&waha=Y   → estado de conexión WAHA por línea
 *   GET ?action=qr&waha_port=X&waha=Y       → QR base64 para vincular línea
 *   GET ?action=restart&waha_port=X&waha=Y  → reiniciar sesión WAHA de la línea
 */

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

// Auth: solo usuarios logueados
if (!is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// ── Config WAHA para la línea personal de Josue ──
// Mismos valores que personal_wasap_api.php.
// Cuando se consulta el puerto 3031, se usan estas credenciales en lugar de comercial_get_settings().
define('TWA_WASAP_PORT', '3031');
define('TWA_WASAP_HOST', 'http://100.117.92.74');
define('TWA_WASAP_KEY', 'local321');
define('TWA_WASAP_SESSION', 'default');

// ── Helpers ──

function telefonos_waha_err(string $msg): never {
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

/**
 * Extrae puerto y sesión WAHA de la petición, y devuelve los settings adecuados.
 *
 * Si el puerto es 3031 (línea personal de Josue), usa las credenciales hardcodeadas
 * de personal_wasap_api.php (TWA_WASAP_*). Para el resto, comercial_get_settings().
 *
 * @return array{string, string, array} [port, session, settings]
 */
function telefonos_waha_ensure_params(): array {
    $port = trim((string)($_GET['waha_port'] ?? ''));
    if ($port === '') {
        telefonos_waha_err('Falta parámetro waha_port');
    }

    if ($port === TWA_WASAP_PORT) {
        // Línea personal de Josue — misma config que personal_wasap_api.php
        $session = TWA_WASAP_SESSION;
        $settings = [
            'waha_host'      => TWA_WASAP_HOST,
            'waha_api_key'   => TWA_WASAP_KEY,
            'waha_session'   => TWA_WASAP_SESSION,
            'curl_timeout_sec' => '8',
        ];
    } else {
        // Líneas comerciales — config global
        $settings = comercial_get_settings();
        $session = trim((string)($settings['waha_session'] ?? 'default'));
        if ($session === '') {
            $session = 'default';
        }
    }
    return [$port, $session, $settings];
}

// ── Enrutamiento ──

$action = trim((string)($_GET['action'] ?? ''));
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($action === '') {
    telefonos_waha_err('Falta parámetro action');
}

switch ($action) {

    // ── Estado de conexión ──
    case 'status':
        [$port, $session, $settings] = telefonos_waha_ensure_params();

        $response = comercial_waha_get_json($settings, $port, 'api/sessions/' . rawurlencode($session));
        $httpCode = (int)($response['http_code'] ?? 0);
        $body = (string)($response['body'] ?? '');

        if (!$response['ok'] || !in_array($httpCode, [200, 201], true) || $body === '') {
            $errMsg = trim((string)($response['error'] ?? ''));
            if ($errMsg === '') {
                $errMsg = $httpCode > 0 ? ('HTTP ' . $httpCode) : 'Sin respuesta de WAHA';
            }
            echo json_encode([
                'ok'            => false,
                'error'         => $errMsg,
                'status'        => 'UNREACHABLE',
                'status_label'  => 'No responde',
                'status_icon'   => '🔴',
                'is_connected'  => false,
                'phone'         => '',
                'debug_url'     => (string)($response['url'] ?? ''),
                'debug_http'    => $httpCode,
                'debug_curl'    => trim((string)($response['error'] ?? '')),
            ]);
            break;
        }

        $sessionData = json_decode($body, true);
        if (!is_array($sessionData)) {
            echo json_encode([
                'ok'            => false,
                'error'         => 'Respuesta inválida de WAHA',
                'status'        => 'UNKNOWN',
                'status_label'  => 'Error',
                'status_icon'   => '🔴',
                'is_connected'  => false,
                'phone'         => '',
                'debug_url'     => (string)($response['url'] ?? ''),
                'debug_http'    => $httpCode,
                'debug_body'    => mb_substr($body, 0, 300),
            ]);
            break;
        }

        $status = strtoupper(trim((string)($sessionData['status'] ?? 'UNKNOWN')));
        $statusLabel = match ($status) {
            'WORKING', 'CONNECTED' => 'Conectado',
            'SCAN_QR_CODE'         => 'Esperando QR',
            'STARTING'             => 'Iniciando...',
            'STOPPED'              => 'Detenido',
            'FAILED'               => 'Falló — reiniciar',
            default                => $status,
        };
        $statusIcon = match ($status) {
            'WORKING', 'CONNECTED' => '🟢',
            'SCAN_QR_CODE'         => '🟡',
            'STARTING'             => '🟠',
            default                => '🔴',
        };

        $phone = '';
        $me = $sessionData['me'] ?? null;
        if (is_array($me) && !empty($me['id'])) {
            $phone = preg_replace('/[^0-9]/', '', (string)$me['id']);
        }

        echo json_encode([
            'ok'           => true,
            'status'       => $status,
            'status_label' => $statusLabel,
            'status_icon'  => $statusIcon,
            'is_connected' => ($status === 'WORKING' || $status === 'CONNECTED'),
            'phone'        => $phone,
        ]);
        break;

    // ── QR (base64 PNG) ──
    case 'qr':
        [$port, $session, $settings] = telefonos_waha_ensure_params();

        $qrPath = 'api/' . rawurlencode($session) . '/auth/qr?format=image';
        $lastError = '';

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $ch = curl_init(comercial_waha_url($settings, $port, $qrPath));
            if ($ch === false) {
                $lastError = 'curl_init failed';
                break;
            }
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'X-Api-Key: ' . $settings['waha_api_key'],
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response !== false && $response !== '') {
                $data = json_decode($response, true);
                if ($httpCode === 200) {
                    if (is_array($data) && !empty($data['data'])) {
                        echo json_encode([
                            'ok'        => true,
                            'qr_base64' => (string)$data['data'],
                        ]);
                        break 2; // Exit switch
                    }
                    $lastError = 'WAHA no devolvió QR (response sin data)';
                } else {
                    $wahaErr = is_array($data) ? trim((string)($data['error'] ?? '')) : '';
                    $wahaStatus = is_array($data) ? trim((string)($data['status'] ?? '')) : '';
                    $lastError = $wahaErr ?: ('HTTP ' . $httpCode . ' — WAHA devolvió error');
                    if ($wahaStatus) $lastError .= ' (sesión: ' . $wahaStatus . ')';
                }
            } else {
                $lastError = $curlError !== ''
                    ? 'Error cURL: ' . $curlError
                    : ('HTTP ' . $httpCode . ' — sin respuesta');
            }

            if ($attempt < 3) {
                usleep(800000); // 0.8s entre reintentos
            }
        }

        echo json_encode([
            'ok'    => false,
            'error' => $lastError ?: 'No se pudo obtener el QR tras 3 intentos',
            'hint'  => '¿WAHA caído en el puerto ' . $port . '?',
        ]);
        break;

    // ── Reiniciar sesión WAHA ──
    case 'restart':
        [$port, $session, $settings] = telefonos_waha_ensure_params();

        // Step 1: Delete session
        $delResult = comercial_waha_request_json($settings, $port, 'DELETE', 'api/sessions/' . rawurlencode($session));
        $delHttp = (int)($delResult['http_code'] ?? 0);
        if (!$delResult['ok'] || !in_array($delHttp, [200, 204], true)) {
            $delErr = trim((string)($delResult['error'] ?? ''));
            echo json_encode([
                'ok'    => false,
                'error' => 'Error al borrar sesión WAHA (HTTP ' . $delHttp . ')' . ($delErr ? ': ' . $delErr : ''),
            ]);
            break;
        }

        sleep(3);

        // Step 2: Recreate session with webhook config
        // Determinar webhook URL según el uso de la línea
        $telData = @json_decode((string)@file_get_contents(__DIR__ . '/data/telefonos.json'), true);
        $uso = '';
        if (is_array($telData)) {
            foreach ($telData as $t) {
                if (((string)($t['waha_port'] ?? '')) === $port) {
                    $uso = (string)($t['uso'] ?? '');
                    break;
                }
            }
        }
        $webhookUrl = match (strtolower(trim($uso))) {
            'personal' => 'http://100.76.30.118/control/personal_wasap_webhook.php',
            'bot casa' => 'https://lamami.online/control/bot-casa/public/webhook.php',
            default    => 'https://lamami.online/comercial_webhook.php',
        };

        $result = comercial_waha_request_json($settings, $port, 'POST', 'api/sessions', [
            'name'   => $session,
            'config' => ['webhooks' => [['url' => $webhookUrl, 'events' => ['message', 'message.any']]]],
            'start'  => true,
        ]);

        if ($result['ok'] && in_array((int)($result['http_code'] ?? 0), [200, 201], true)) {
            // Step 3: Wait and verify session is progressing (not immediately FAILED)
            sleep(3);
            $check = comercial_waha_get_json($settings, $port, 'api/sessions/' . rawurlencode($session));
            $checkBody = json_decode((string)($check['body'] ?? ''), true);
            $checkStatus = is_array($checkBody) ? strtoupper(trim((string)($checkBody['status'] ?? ''))) : '';

            if ($checkStatus === 'FAILED') {
                echo json_encode([
                    'ok'    => false,
                    'error' => 'Sesión pasó a FAILED tras reiniciar. Limpia sesiones del contenedor WAHA: rm -rf sessions/* data/* && docker compose up -d.',
                ]);
            } else {
                echo json_encode(['ok' => true, 'message' => 'WAHA reiniciado. Estado: ' . ($checkStatus ?: 'iniciando') . '. Escanea el QR cuando aparezca.']);
            }
        } else {
            $err = trim((string)($result['error'] ?? ''));
            echo json_encode([
                'ok'    => false,
                'error' => 'Error al reiniciar WAHA (HTTP ' . ($result['http_code'] ?? '?') . ')' . ($err ? ': ' . $err : ''),
            ]);
        }
        break;

    default:
        telefonos_waha_err('Acción desconocida: ' . $action);
}
