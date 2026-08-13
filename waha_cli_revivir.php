#!/usr/bin/env php
<?php
/**
 * waha_cli_revivir.php — Levanta líneas WAHA caídas sin perder auth.
 *
 * Estrategia escalonada:
 *   1. Sin sesión → crear sesión (POST /api/sessions)
 *   2. Sesión FAILED/STOPPED → re-arrancar sesión (POST start)
 *      Si sigue FAILED → restart (DELETE + POST recreate)
 *   3. WORKING → nada que hacer
 *
 * Uso:
 *   php waha_cli_revivir.php              → revive todas las líneas
 *   php waha_cli_revivir.php --dry-run    → solo diagnóstico, no actúa
 */

declare(strict_types=1);

$dryRun = in_array('--dry-run', $argv ?? [], true);

// ── Config ──
define('WAHA_HOST', 'http://100.117.92.74');
define('WAHA_KEY', 'local321');
define('WAHA_SESSION', 'default');
define('TELEFONOS_PATH', __DIR__ . '/data/telefonos.json');

// ── Helpers ──

function waha_call(string $method, int $port, string $path, ?array $body = null): array
{
    $url = WAHA_HOST . ':' . $port . $path;
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'http' => 0, 'error' => 'curl_init failed', 'body' => null];
    }

    $headers = ['Accept: application/json', 'X-Api-Key: ' . WAHA_KEY];
    $opts = [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    } elseif ($method === 'DELETE') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
    } elseif ($method === 'PUT') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $bodyDecoded = null;
    if ($response !== false && $response !== '') {
        $bodyDecoded = json_decode($response, true);
    }

    return [
        'ok'       => $response !== false,
        'http'     => $httpCode,
        'error'    => $response === false ? ($error ?: 'Empty response') : null,
        'body'     => $bodyDecoded,
        'body_raw' => $response,
    ];
}

function status_label(string $status): string
{
    return match (strtoupper($status)) {
        'WORKING', 'CONNECTED' => 'WORKING',
        'SCAN_QR_CODE'         => 'SCAN_QR_CODE',
        'STARTING'             => 'STARTING',
        'STOPPED'              => 'STOPPED',
        'FAILED'               => 'FAILED',
        default                => $status,
    };
}

function status_icon(string $status): string
{
    return match ($status) {
        'WORKING'     => '🟢',
        'SCAN_QR_CODE' => '🟡',
        'STARTING'    => '🟠',
        default       => '🔴',
    };
}

function wasap_webhook_url_for_usage(string $uso): string {
    return match (strtolower(trim($uso))) {
        'personal' => 'http://100.76.30.118/control/personal_wasap_webhook.php',
        'bot casa' => 'https://lamami.online/control/bot-casa/public/webhook.php',
        default    => 'https://lamami.online/comercial_webhook.php',
    };
}

// ── Cargar líneas ──

$telefonosRaw = @file_get_contents(TELEFONOS_PATH);
if ($telefonosRaw === false) {
    die("ERROR: No se pudo leer " . TELEFONOS_PATH . "\n");
}
$telefonos = json_decode($telefonosRaw, true);
if (!is_array($telefonos)) {
    die("ERROR: telefonos.json no es un array válido\n");
}

// Indexar por puerto
$linesByPort = [];
foreach ($telefonos as $line) {
    $port = trim((string) ($line['waha_port'] ?? ''));
    if ($port === '') continue;
    $p = (int) $port;
    if (!isset($linesByPort[$p])) {
        $linesByPort[$p] = [];
    }
    $linesByPort[$p][] = $line;
}

if (empty($linesByPort)) {
    die("No hay líneas con waha_port configurado. Nada que hacer.\n");
}

echo str_repeat('─', 85) . "\n";
echo "  WAHA CLI REVIVIR — " . ($dryRun ? "MODO DRY-RUN (solo diagnóstico)" : "MODO ACTIVO") . "\n";
echo "  Servidor WAHA: " . WAHA_HOST . "\n";
echo "  Líneas a revisar: " . count($linesByPort) . "\n";
echo str_repeat('─', 85) . "\n\n";

// ── Buckets de resultados ──
$results = [
    'already_working' => [],   // ya estaba WORKING
    'recovered'       => [],   // se recuperó sin QR
    'needs_qr'        => [],   // sesión creada pero auth perdida (SCAN_QR_CODE)
    'needs_reset'     => [],   // FAILED que no se pudo recuperar ni con restart
    'unreachable'     => [],   // no responde HTTP
    'error'           => [],   // otro error
];

foreach ($linesByPort as $port => $lines) {
    $label = implode(', ', array_map(fn($l) => ($l['nombre'] ?? '?') . ' (' . ($l['tfono'] ?? '') . ')', $lines));
    $action = '';

    // ── 1. Check status ──
    $status = waha_call('GET', $port, '/api/sessions/' . WAHA_SESSION);
    $statusData = is_array($status['body']) ? $status['body'] : null;
    $currentStatus = $statusData ? strtoupper(trim((string) ($statusData['status'] ?? 'UNKNOWN'))) : null;
    $phone = '';
    if ($statusData && isset($statusData['me']['id'])) {
        $phone = preg_replace('/[^0-9]/', '', (string) $statusData['me']['id']);
    }

    // ── 2. Si ya está WORKING → skip ──
    if ($currentStatus === 'WORKING' || $currentStatus === 'CONNECTED') {
        $results['already_working'][] = ['port' => $port, 'label' => $label, 'status' => $currentStatus, 'phone' => $phone];
        $sIcon = status_icon('WORKING');
        echo "  {$sIcon} PUERTO {$port}: {$label}\n";
        echo "     Estado: WORKING ({$phone}) — ya estaba funcionando, nada que hacer\n\n";
        continue;
    }

    // ── 3. HTTP no 200 → unreachable ──
    if ($status['http'] < 200 || $status['http'] >= 500) {
        $errMsg = $status['error'] ?? ('HTTP ' . $status['http']);
        $results['unreachable'][] = ['port' => $port, 'label' => $label, 'error' => $errMsg];
        echo "  ❌ PUERTO {$port}: {$label}\n";
        echo "     No responde: {$errMsg}\n";
        echo "     → Necesita reset del contenedor vía Manager API\n\n";
        continue;
    }

    // ── 4. Sesión existe pero FAILED/STOPPED → intentar re-arrancar ──
    if ($currentStatus !== null) {
        echo "  🔴 PUERTO {$port}: {$label}\n";
        echo "     Estado: {$currentStatus}" . ($phone ? " ({$phone})" : "") . "\n";

        if ($dryRun) {
            echo "     [DRY-RUN] Intentaría start_session...\n\n";
            $results['needs_qr'][] = ['port' => $port, 'label' => $label, 'status' => $currentStatus];
            continue;
        }

        // Intentar re-arrancar sesión existente
        $action = 'start_session + restart';
        echo "     → start_session... ";
        $start = waha_call('POST', $port, '/api/sessions/' . WAHA_SESSION . '/start');
        sleep(3);

        $check = waha_call('GET', $port, '/api/sessions/' . WAHA_SESSION);
        $checkData = is_array($check['body']) ? $check['body'] : null;
        $newStatus = $checkData ? strtoupper(trim((string) ($checkData['status'] ?? 'UNKNOWN'))) : null;

        if ($newStatus === 'WORKING' || $newStatus === 'CONNECTED') {
            $newPhone = '';
            if ($checkData && isset($checkData['me']['id'])) {
                $newPhone = preg_replace('/[^0-9]/', '', (string) $checkData['me']['id']);
            }
            $results['recovered'][] = ['port' => $port, 'label' => $label, 'status' => $newStatus, 'phone' => $newPhone, 'action' => $action];
            echo "¡RECUPERADA! → WORKING ({$newPhone})\n\n";
            continue;
        }

        echo "{$newStatus}. ";
        echo "→ restart (DELETE + recreate)... ";
        waha_call('DELETE', $port, '/api/sessions/' . WAHA_SESSION);
        sleep(3);
        $uso = $lines[0]['uso'] ?? '';
        $webhookUrl = wasap_webhook_url_for_usage($uso);
        waha_call('POST', $port, '/api/sessions', [
            'name' => WAHA_SESSION,
            'config' => ['webhooks' => [['url' => $webhookUrl, 'events' => ['message', 'message.any']]]],
            'start' => true,
        ]);
        sleep(8);

        $check2 = waha_call('GET', $port, '/api/sessions/' . WAHA_SESSION);
        $check2Data = is_array($check2['body']) ? $check2['body'] : null;
        $finalStatus = $check2Data ? strtoupper(trim((string) ($check2Data['status'] ?? 'UNKNOWN'))) : null;
        $finalPhone = '';
        if ($check2Data && isset($check2Data['me']['id'])) {
            $finalPhone = preg_replace('/[^0-9]/', '', (string) $check2Data['me']['id']);
        }

        if ($finalStatus === 'WORKING' || $finalStatus === 'CONNECTED') {
            $results['recovered'][] = ['port' => $port, 'label' => $label, 'status' => $finalStatus, 'phone' => $finalPhone, 'action' => $action];
            echo "¡RECUPERADA! → WORKING ({$finalPhone})\n\n";
        } elseif ($finalStatus === 'SCAN_QR_CODE' || $finalStatus === 'STARTING') {
            $results['needs_qr'][] = ['port' => $port, 'label' => $label, 'status' => $finalStatus, 'action' => $action];
            echo "QR necesario ({$finalStatus}) → ve a Josue > Telefonos y vincula\n\n";
        } elseif ($finalStatus === 'FAILED') {
            $results['needs_reset'][] = ['port' => $port, 'label' => $label, 'status' => $finalStatus, 'action' => $action];
            echo "Sigue FAILED → necesita reset del contenedor\n\n";
        } else {
            $results['error'][] = ['port' => $port, 'label' => $label, 'status' => $finalStatus ?? '?', 'action' => $action];
            echo "Estado: " . ($finalStatus ?? 'sin respuesta') . "\n\n";
        }
        continue;
    }

    // ── 5. Sin sesión → crear ──
    echo "  ⚪ PUERTO {$port}: {$label}\n";
    echo "     Sin sesión WAHA\n";

    if ($dryRun) {
        echo "     [DRY-RUN] Crearía sesión...\n\n";
        $results['needs_qr'][] = ['port' => $port, 'label' => $label, 'status' => 'NO_SESSION'];
        continue;
    }

    $action = 'crear sesión';
    echo "     → Creando sesión... ";
    $uso = $lines[0]['uso'] ?? '';
    $webhookUrl = wasap_webhook_url_for_usage($uso);
    $create = waha_call('POST', $port, '/api/sessions', [
        'name' => WAHA_SESSION,
        'config' => ['webhooks' => [['url' => $webhookUrl, 'events' => ['message', 'message.any']]]],
        'start' => true,
    ]);

    if ($create['http'] >= 200 && $create['http'] < 300) {
        echo "OK (HTTP {$create['http']}). ";
        echo "Esperando arranque...\n";
        sleep(8);

        $check = waha_call('GET', $port, '/api/sessions/' . WAHA_SESSION);
        $checkData = is_array($check['body']) ? $check['body'] : null;
        $finalStatus = $checkData ? strtoupper(trim((string) ($checkData['status'] ?? 'UNKNOWN'))) : null;
        $finalPhone = '';
        if ($checkData && isset($checkData['me']['id'])) {
            $finalPhone = preg_replace('/[^0-9]/', '', (string) $checkData['me']['id']);
        }

        if ($finalStatus === 'WORKING' || $finalStatus === 'CONNECTED') {
            $results['recovered'][] = ['port' => $port, 'label' => $label, 'status' => $finalStatus, 'phone' => $finalPhone, 'action' => $action];
            echo "     ¡RECUPERADA! → WORKING ({$finalPhone})\n\n";
        } elseif ($finalStatus === 'SCAN_QR_CODE' || $finalStatus === 'STARTING') {
            $results['needs_qr'][] = ['port' => $port, 'label' => $label, 'status' => $finalStatus, 'action' => $action];
            echo "     QR necesario ({$finalStatus}) → ve a Josue > Telefonos y vincula\n\n";
        } elseif ($finalStatus === 'FAILED') {
            $results['needs_reset'][] = ['port' => $port, 'label' => $label, 'status' => $finalStatus, 'action' => $action];
            echo "     FAILED → necesita reset del contenedor\n\n";
        } else {
            $results['error'][] = ['port' => $port, 'label' => $label, 'status' => $finalStatus ?? '?', 'action' => $action, 'http' => $check['http']];
            echo "     Estado: " . ($finalStatus ?? 'sin respuesta') . " (HTTP {$check['http']})\n\n";
        }
    } else {
        $errMsg = $create['error'] ?? ('HTTP ' . $create['http']);
        $results['error'][] = ['port' => $port, 'label' => $label, 'error' => $errMsg, 'action' => $action];
        echo "     ERROR al crear sesión: {$errMsg}\n\n";
    }
}

// ── Resumen final ──

echo str_repeat('═', 85) . "\n";
echo "  RESUMEN FINAL\n";
echo str_repeat('═', 85) . "\n\n";

echo "  🟢 Ya funcionando (" . count($results['already_working']) . "):\n";
foreach ($results['already_working'] as $r) {
    echo "     puerto {$r['port']} — {$r['label']} — {$r['status']} ({$r['phone']})\n";
}
echo "\n";

echo "  ✅ Recuperadas sin QR (" . count($results['recovered']) . "):\n";
foreach ($results['recovered'] as $r) {
    echo "     puerto {$r['port']} — {$r['label']} — {$r['action']} → {$r['status']} ({$r['phone']})\n";
}
echo "\n";

echo "  🟡 Necesitan QR (" . count($results['needs_qr']) . "):\n";
foreach ($results['needs_qr'] as $r) {
    $s = $r['status'] ?? '?';
    echo "     puerto {$r['port']} — {$r['label']} — {$s} → ve a Josue > Telefonos, botón Vincular\n";
}
if (count($results['needs_qr']) > 0) {
    echo "     ┌─────────────────────────────────────────────────────┐\n";
    echo "     │ Abre: index.php?page=josue&tab=telefonos            │\n";
    echo "     │ Cada línea en 🟡 SCAN_QR_CODE tendrá botón Vincular │\n";
    echo "     └─────────────────────────────────────────────────────┘\n";
}
echo "\n";

echo "  🔴 Necesitan reset container (" . count($results['needs_reset']) . "):\n";
foreach ($results['needs_reset'] as $r) {
    echo "     puerto {$r['port']} — {$r['label']} — {$r['status']} — necesita reset\n";
}
echo "\n";

echo "  ❌ Unreachable (" . count($results['unreachable']) . "):\n";
foreach ($results['unreachable'] as $r) {
    echo "     puerto {$r['port']} — {$r['label']} — {$r['error']}\n";
}
echo "\n";

echo "  ⚠️  Errores (" . count($results['error']) . "):\n";
foreach ($results['error'] as $r) {
    $info = $r['status'] ?? ($r['error'] ?? '?');
    echo "     puerto {$r['port']} — {$r['label']} — {$info}\n";
}
echo "\n";

echo str_repeat('═', 85) . "\n";
$totalOk = count($results['already_working']) + count($results['recovered']);
$totalNeedAction = count($results['needs_qr']) + count($results['needs_reset']) + count($results['unreachable']) + count($results['error']);
echo "  Total: {$totalOk} OK (sin QR) | {$totalNeedAction} necesitan acción\n";
echo str_repeat('═', 85) . "\n";
