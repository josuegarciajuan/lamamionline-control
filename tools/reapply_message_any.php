<?php

declare(strict_types=1);

/**
 * reapply_message_any.php — regenera las sesiones WAHA de los bots comercial y
 * bot-casa para suscribirlas al evento `message.any`.
 *
 * ⚠️ IMPORTANTE: NO basta con PUT/restart. WAHA solo registra la suscripción
 *    `message.any` correctamente cuando la sesión se CREA de nuevo (POST). Y
 *    como DELETE hace logout, cada línea requerirá RE-ESCANEAR el QR.
 *
 *    Esto es exactamente lo que ya hace el botón "Reiniciar" de
 *    Josue → Telefonos (`telefonos_waha_api.php?action=restart`).
 *
 * Modo:
 *   php reapply_message_any.php            -> regenera TODAS las líneas objetivo
 *   php reapply_message_any.php --dry-run  -> solo muestra qué haría
 *   php reapply_message_any.php --port=3006 -> regenera SOLO una línea
 *
 * Tras ejecutar, escanea el QR de cada línea (Josue → Telefonos) para vincular.
 */

const WAHA_HOST = 'http://100.117.92.74';
const WAHA_API_KEY = 'local321';
const WAHA_SESSION = 'default';

$dryRun = in_array('--dry-run', $argv, true);
$onlyPort = 0;
foreach ($argv as $a) {
    if (str_starts_with($a, '--port=')) {
        $onlyPort = (int) substr($a, strlen('--port='));
    }
}

$usageToWebhook = [
    'bot casa'    => 'https://lamami.online/control/bot-casa/public/webhook.php',
    'envio publi' => 'https://lamami.online/comercial_webhook.php',
];

$telefonos = json_decode((string) @file_get_contents(__DIR__ . '/../data/telefonos.json'), true);
if (!is_array($telefonos)) {
    fwrite(STDERR, "No se pudo leer telefonos.json\n");
    exit(1);
}

function waha_request(string $method, int $port, string $path, ?array $body = null): array
{
    $url = WAHA_HOST . ':' . $port . $path;
    $ch = curl_init($url);
    $headers = ['Accept: application/json', 'X-Api-Key: ' . WAHA_API_KEY];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_POSTFIELDS     => $body !== null ? json_encode($body) : null,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http' => $http, 'body' => json_decode((string) $resp, true) ?: []];
}

$targets = [];
foreach ($telefonos as $t) {
    $uso  = strtolower(trim((string) ($t['uso'] ?? '')));
    $port = (int) ($t['waha_port'] ?? 0);
    if ($port <= 0 || !isset($usageToWebhook[$uso])) continue;
    if ($onlyPort > 0 && $port !== $onlyPort) continue;
    $targets[] = [
        'port' => $port,
        'nombre' => trim((string) ($t['nombre'] ?? '')),
        'uso' => $uso,
        'webhook' => $usageToWebhook[$uso],
    ];
}

if (empty($targets)) {
    echo "No hay líneas objetivo.\n";
    exit(0);
}

echo ($dryRun ? "== DRY-RUN ==\n" : "== REGENERANDO (requiere QR después) ==\n");

foreach ($targets as $i => $t) {
    $get = waha_request('GET', $t['port'], '/api/sessions/' . WAHA_SESSION);
    $status = strtoupper(trim((string) ($get['body']['status'] ?? 'UNKNOWN')));

    printf(
        "port %-5s %-16s uso=%-12s status=%-9s -> %s\n",
        $t['port'],
        $t['nombre'],
        $t['uso'],
        $status,
        $dryRun ? "DRY-RUN: DELETE + POST (message.any) → {$t['webhook']}" : 'DELETE + recreate + start',
    );

    if ($dryRun) continue;

    // 1. Delete (hace logout → luego pedirá QR)
    waha_request('DELETE', $t['port'], '/api/sessions/' . WAHA_SESSION);
    sleep(3);

    // 2. Recrear con message.any + start
    $post = waha_request('POST', $t['port'], '/api/sessions', [
        'name'   => WAHA_SESSION,
        'config' => [
            'webhooks' => [
                ['url' => $t['webhook'], 'events' => ['message', 'message.any']],
            ],
        ],
        'start'  => true,
    ]);

    printf(
        "         -> POST http=%d  (estado esperado: SCAN_QR_CODE → escanea QR en Josue→Telefonos)\n",
        $post['http'],
    );
}

if (!$dryRun) {
    echo "\n⚠️  Escanea el QR de cada línea regenerada en Josue → Telefonos para revincularla.\n";
}
