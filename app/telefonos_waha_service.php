<?php

declare(strict_types=1);

const TELEFONOS_WAHA_PERSONAL_PORT = '3031';
const TELEFONOS_WAHA_IDENTIFY_MAX_CANDIDATES = 4;
const TELEFONOS_WAHA_IDENTIFY_BUDGET_SECONDS = 20;
const TELEFONOS_WAHA_IDENTIFY_DEDUP_SECONDS = 120;

/** @return array<int,string> */
function telefonos_waha_allowed_hosts(): array
{
    return [
        'http://100.117.92.74',
        'http://100.113.76.93',
        'http://100.76.30.118',
    ];
}

function telefonos_waha_host_is_allowed(string $host): bool
{
    return in_array($host, telefonos_waha_allowed_hosts(), true);
}

function telefonos_waha_port_is_allowed(string $port, bool $allowBlank): bool
{
    $port = trim($port);
    if ($port === '') return $allowBlank;
    if (!preg_match('/^\d{4}$/', $port)) return false;
    $number = (int)$port;
    return ($number >= 3000 && $number <= 3011) || $number === 3031;
}

function telefonos_waha_session_is_valid(string $session): bool
{
    return (bool)preg_match('/^[A-Za-z0-9._-]{1,128}$/', $session);
}

/**
 * Canonicalizes supported phone inputs to digits-only international form.
 * Nine-digit Spanish numbers receive country code 34.
 */
function telefonos_waha_normalize_phone(string $phone): ?string
{
    $phone = trim($phone);
    if ($phone === '' || strncmp($phone, '00', 2) === 0) return null;
    if (!preg_match('/^\+?[0-9 ().-]+$/', $phone)) return null;
    if (substr_count($phone, '+') > 1 || (strpos($phone, '+') !== false && $phone[0] !== '+')) return null;

    $hasPlus = $phone[0] === '+';
    $digits = (string)preg_replace('/\D+/', '', $phone);
    if ($digits === '' || $digits[0] === '0') return null;

    if (!$hasPlus && strlen($digits) === 9) {
        if (!preg_match('/^[6789]\d{8}$/', $digits)) return null;
        return '34' . $digits;
    }
    if ($hasPlus && strlen($digits) < 10) return null;
    if (strlen($digits) < 10 || strlen($digits) > 15) return null;
    return $digits;
}

function telefonos_waha_phone_from_waha_id(string $id): ?string
{
    if (!preg_match('/^([1-9]\d{9,14})(?::\d+)?@/', trim($id), $matches)) return null;
    return telefonos_waha_normalize_phone($matches[1]);
}

/** @return array{status:int,error:string} */
function telefonos_waha_authorize(bool $authenticated, bool $admin): array
{
    if (!$authenticated) return ['status' => 401, 'error' => 'No autorizado'];
    if (!$admin) return ['status' => 403, 'error' => 'Acceso denegado'];
    return ['status' => 200, 'error' => ''];
}

/** @return array{status:int,error:string} */
function telefonos_waha_validate_mutation(string $method, bool $csrfValid): array
{
    if (strtoupper($method) !== 'POST') return ['status' => 405, 'error' => 'Método no permitido'];
    if (!$csrfValid) return ['status' => 403, 'error' => 'Token CSRF inválido'];
    return ['status' => 200, 'error' => ''];
}

/** @param array<int,array<string,mixed>> $rows */
function telefonos_waha_find_row(array $rows, string $id): ?array
{
    $id = trim($id);
    if ($id === '') return null;
    foreach ($rows as $row) {
        if (is_array($row) && hash_equals((string)($row['id'] ?? ''), $id)) return $row;
    }
    return null;
}

/**
 * @param array<string,mixed>|null $row
 * @param array<string,mixed> $commercialSettings
 * @param array<string,mixed> $personalSettings
 * @return array{row:array<string,mixed>,port:string,session:string,settings:array<string,mixed>}
 */
function telefonos_waha_line_config(?array $row, array $commercialSettings, array $personalSettings): array
{
    if ($row === null) throw new InvalidArgumentException('Línea no encontrada');
    $port = trim((string)($row['waha_port'] ?? ''));
    if (!telefonos_waha_port_is_allowed($port, false)) {
        throw new InvalidArgumentException('La línea no tiene un puerto WAHA permitido');
    }

    // Todas las instancias WAHA desplegadas son WAHA Core, que solo admite la
    // sesión 'default'. El campo waha de la fila es solo una etiqueta, no el
    // nombre real de la sesión (usarlo rompía status/identify/restart con 422).
    if ($port === TELEFONOS_WAHA_PERSONAL_PORT) {
        $settings = $personalSettings;
    } else {
        $settings = $commercialSettings;
    }
    $session = 'default';
    if (!telefonos_waha_session_is_valid($session)) {
        throw new InvalidArgumentException('La línea no tiene una sesión WAHA válida');
    }

    $host = (string)($settings['waha_host'] ?? '');
    if (!telefonos_waha_host_is_allowed($host)) {
        throw new InvalidArgumentException('El host WAHA no está permitido');
    }
    $settings['waha_host'] = $host;
    $settings['waha_session'] = $session;
    $settings['curl_timeout_sec'] = (string)max(1, min(8, (int)($settings['curl_timeout_sec'] ?? 8)));

    return ['row' => $row, 'port' => $port, 'session' => $session, 'settings' => $settings];
}

/** @param array<string,mixed> $response */
function telefonos_waha_response_is_success(array $response, array $codes = [200, 201]): bool
{
    return !empty($response['ok']) && in_array((int)($response['http_code'] ?? 0), $codes, true);
}

/** @param array<string,mixed> $response
 *  @return array{connected:bool,status:string,phone:?string}
 */
function telefonos_waha_status_info(array $response): array
{
    if (!telefonos_waha_response_is_success($response)) {
        return ['connected' => false, 'status' => '', 'phone' => null];
    }
    $body = json_decode((string)($response['body'] ?? ''), true);
    if (!is_array($body)) return ['connected' => false, 'status' => '', 'phone' => null];
    $status = strtoupper(trim((string)($body['status'] ?? '')));
    $me = $body['me'] ?? null;
    $phone = is_array($me) ? telefonos_waha_phone_from_waha_id((string)($me['id'] ?? '')) : null;
    return [
        'connected' => in_array($status, ['WORKING', 'CONNECTED'], true),
        'status' => $status,
        'phone' => $phone,
    ];
}

function telefonos_waha_webhook_for_row(array $row): string
{
    return match (strtolower(trim((string)($row['uso'] ?? '')))) {
        'personal' => 'http://100.76.30.118/control/personal_wasap_webhook.php',
        'bot casa' => 'https://lamami.online/control/bot-casa/public/webhook.php',
        default => 'https://lamami.online/comercial_webhook.php',
    };
}

/**
 * @param array<int,array<string,mixed>> $rows
 * @param array<string,mixed> $commercialSettings
 * @param array<string,mixed> $personalSettings
 * @param callable(array,array):array $statusCallback
 * @param callable(array,string,string,array):array $sendCallback
 * @param callable(string,int,int):?array $dedupGet
 * @param callable(string,array,int):void $dedupPut
 * @param array<string,mixed> $options
 * @return array<string,mixed>
 */
function telefonos_waha_identify(
    string $targetId,
    array $rows,
    array $commercialSettings,
    array $personalSettings,
    callable $statusCallback,
    callable $sendCallback,
    callable $dedupGet,
    callable $dedupPut,
    array $options = []
): array {
    $target = telefonos_waha_find_row($rows, $targetId);
    if ($target === null) return ['ok' => false, 'status' => 404, 'error' => 'Teléfono no encontrado'];
    $targetPhone = telefonos_waha_normalize_phone((string)($target['tfono'] ?? ''));
    if ($targetPhone === null) return ['ok' => false, 'status' => 400, 'error' => 'El teléfono de destino no es válido'];

    $nowCallback = $options['now'] ?? static fn(): int => time();
    $clock = $options['clock'] ?? static fn(): float => microtime(true);
    $now = (int)$nowCallback();
    $dedupKey = hash('sha256', (string)($target['id'] ?? '') . "\0" . $targetPhone);
    $cached = $dedupGet($dedupKey, $now, TELEFONOS_WAHA_IDENTIFY_DEDUP_SECONDS);
    if (is_array($cached) && !empty($cached['ok'])) {
        $cached['deduplicated'] = true;
        return $cached;
    }

    try {
        $targetConfig = telefonos_waha_line_config($target, $commercialSettings, $personalSettings);
        $targetIdentity = $targetConfig['port'] . "\0" . strtolower($targetConfig['session']);
    } catch (InvalidArgumentException $e) {
        $targetIdentity = '';
    }

    $candidates = [];
    foreach ($rows as $row) {
        if (!is_array($row) || (string)($row['id'] ?? '') === (string)($target['id'] ?? '')) continue;
        $storedPhone = telefonos_waha_normalize_phone((string)($row['tfono'] ?? ''));
        if ($storedPhone !== null && hash_equals($targetPhone, $storedPhone)) continue;
        try {
            $config = telefonos_waha_line_config($row, $commercialSettings, $personalSettings);
        } catch (InvalidArgumentException $e) {
            continue;
        }
        $identity = $config['port'] . "\0" . strtolower($config['session']);
        if ($targetIdentity !== '' && hash_equals($targetIdentity, $identity)) continue;
        $candidates[] = ['row' => $row, 'config' => $config];
    }
    usort($candidates, static fn(array $a, array $b): int => strcmp((string)($a['row']['id'] ?? ''), (string)($b['row']['id'] ?? '')));

    $maxCandidates = max(1, min(TELEFONOS_WAHA_IDENTIFY_MAX_CANDIDATES, (int)($options['max_candidates'] ?? TELEFONOS_WAHA_IDENTIFY_MAX_CANDIDATES)));
    $budgetSeconds = max(1, min(TELEFONOS_WAHA_IDENTIFY_BUDGET_SECONDS, (int)($options['budget_seconds'] ?? TELEFONOS_WAHA_IDENTIFY_BUDGET_SECONDS)));
    $onError = $options['on_error'] ?? static function (string $stage, Throwable $error): void {};
    $candidateLabel = static function (array $candidate): string {
        return trim((string)($candidate['row']['nombre'] ?? ''))
            . ' (puerto ' . (string)($candidate['config']['port'] ?? '') . ')';
    };
    $startedAt = (float)$clock();
    $hadConnected = false;
    $diagnostics = [];

    foreach (array_slice($candidates, 0, $maxCandidates) as $candidate) {
        if ((float)$clock() - $startedAt >= $budgetSeconds) break;
        try {
            $response = $statusCallback($candidate['config'], $candidate['row']);
        } catch (Throwable $error) {
            $onError('status', $error);
            $diagnostics[] = $candidateLabel($candidate) . ': error al consultar estado';
            continue;
        }
        $info = telefonos_waha_status_info(is_array($response) ? $response : []);
        if (!$info['connected']) {
            $diagnostics[] = $candidateLabel($candidate) . ': no conectada (' . ($info['status'] !== '' ? $info['status'] : 'sin respuesta') . ')';
            continue;
        }
        if ($info['phone'] !== null && hash_equals($targetPhone, $info['phone'])) {
            $diagnostics[] = $candidateLabel($candidate) . ': es la propia línea';
            continue;
        }
        if ((float)$clock() - $startedAt >= $budgetSeconds) break;

        $hadConnected = true;
        $message = 'IDENTIFICAR: esta línea es ' . trim((string)($target['nombre'] ?? ''))
            . ' (' . trim((string)($target['tfono'] ?? '')) . ')';
        try {
            $sent = $sendCallback($candidate['config'], $targetPhone, $message, $candidate['row']);
        } catch (Throwable $error) {
            $onError('send', $error);
            $diagnostics[] = $candidateLabel($candidate) . ': error al enviar';
            continue;
        }
        if (!telefonos_waha_response_is_success(is_array($sent) ? $sent : [])) {
            $diagnostics[] = $candidateLabel($candidate) . ': no pudo enviar el mensaje';
            continue;
        }
        $result = [
            'ok' => true,
            'status' => 200,
            'message' => 'Mensaje de identificación enviado',
            'source_label' => trim((string)($candidate['row']['nombre'] ?? '')),
            'source_phone' => trim((string)($candidate['row']['tfono'] ?? '')),
            'deduplicated' => false,
        ];
        $dedupPut($dedupKey, $result, $now);
        return $result;
    }

    $detail = $diagnostics !== [] ? ' Líneas comprobadas: ' . implode('; ', $diagnostics) . '.' : '';
    if (!$hadConnected) return ['ok' => false, 'status' => 409, 'error' => 'No hay otra línea WAHA conectada disponible.' . $detail];
    return ['ok' => false, 'status' => 502, 'error' => 'Las líneas conectadas no pudieron enviar el mensaje.' . $detail];
}

/**
 * @param array<string,mixed> $config
 * @param callable(array,string,string,?array):array $requestCallback
 * @param callable(int):void $sleepCallback
 * @return array<string,mixed>
 */
function telefonos_waha_restart(array $config, callable $requestCallback, callable $sleepCallback): array
{
    $sessionPath = 'api/sessions/' . rawurlencode((string)$config['session']);
    $deleted = $requestCallback($config, 'DELETE', $sessionPath, null);
    if (!telefonos_waha_response_is_success($deleted, [200, 204, 404])) {
        return ['ok' => false, 'status' => 502, 'error' => 'WAHA no pudo borrar la sesión'];
    }
    $sleepCallback(1);

    $payload = [
        'name' => $config['session'],
        'config' => ['webhooks' => [[
            'url' => telefonos_waha_webhook_for_row($config['row']),
            'events' => ['message', 'message.any'],
        ]]],
        'start' => true,
    ];
    $created = $requestCallback($config, 'POST', 'api/sessions', $payload);
    if (!telefonos_waha_response_is_success($created)) {
        return ['ok' => false, 'status' => 502, 'error' => 'WAHA no pudo recrear la sesión'];
    }
    $sleepCallback(2);

    $checked = $requestCallback($config, 'GET', $sessionPath, null);
    if (!telefonos_waha_response_is_success($checked)) {
        return ['ok' => false, 'status' => 502, 'error' => 'WAHA no confirmó la sesión reiniciada'];
    }
    $info = telefonos_waha_status_info($checked);
    if (!in_array($info['status'], ['STARTING', 'SCAN_QR_CODE', 'WORKING', 'CONNECTED'], true)) {
        return ['ok' => false, 'status' => 502, 'error' => 'La sesión WAHA no pudo iniciarse'];
    }
    return ['ok' => true, 'status' => 200, 'message' => 'WAHA reiniciado. Escanea el QR cuando aparezca.'];
}
