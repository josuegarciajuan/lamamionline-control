<?php
/**
 * wahaapi/index.php — API ligera de gestión WAHA en servidor local.
 *
 * Desplegar en: /var/www/html/wahaapi/
 * Apache lo sirve en http://<ip>/wahaapi/
 *
 * Convenciones de puertos:
 *   - Manuales (legacy): wahaN → port = 3000 + N - 1  (ej: waha7 → 3006)
 *   - Automáticas (nuevas): wahaN → port = N - 1  a partir de port 3020 (ej: waha3021 → 3020)
 *
 * Acciones:
 *   GET  ?action=status              → lista instancias WAHA (docker ps + scan)
 *   POST ?action=create&port=3020    → crea nueva instancia docker WAHA (inicia sesión + webhook)
 *   POST ?action=delete&port=3020    → elimina instancia docker WAHA
 *   POST ?action=reset&port=3020     → resetea sesión (borra data/sessions, reconfigura, rearranca)
 *   POST ?action=start_session&port=3020 → inicia sesión WAHA
 *
 * Seguridad: requiere header X-Api-Key: local321
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// ── Auth ──
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== 'local321') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? 'status');

// ── Helpers ──
function execCmd(string $cmd): array {
    $output = [];
    $exitCode = 0;
    exec($cmd . ' 2>&1', $output, $exitCode);
    return ['ok' => $exitCode === 0, 'output' => $output, 'code' => $exitCode];
}

/** @return array{ok: bool, body: string|null, http: int} */
function wahaApiCall(string $method, int $port, string $path, ?string $body = null): array {
    $ch = curl_init("http://127.0.0.1:{$port}{$path}");
    $headers = ['Accept: application/json', 'X-Api-Key: local321'];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    $opts = [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        if ($body !== null) $opts[CURLOPT_POSTFIELDS] = $body;
    } elseif ($method === 'PUT' || $method === 'DELETE') {
        $opts[CURLOPT_CUSTOMREQUEST] = $method;
        if ($body !== null) $opts[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['ok' => $httpCode >= 200 && $httpCode < 300, 'body' => ($response === false ? null : $response), 'http' => $httpCode];
}

function getNextPort(): int {
    // New automatic instances start at port 3020 (waha3021+).
    // Manual instances (ports 3000-3019, dirs waha7-waha... ) are ignored.
    $dirs = @scandir('/srv/');
    $used = [];
    if ($dirs) {
        foreach ($dirs as $d) {
            if (preg_match('/^waha(\d+)$/', $d, $m)) {
                $n = (int)$m[1];
                if ($n >= 3021) $used[$n - 1] = true;   // automatic: waha3021 → port 3020
            }
        }
    }
    for ($p = 3020; $p < 3100; $p++) {
        if (!isset($used[$p])) return $p;
    }
    return 3020;
}

/** Resolve port → instance directory, handling both conventions. */
function findWahaDir(int $port): ?string {
    $dirs = @scandir('/srv/');
    if (!$dirs) return null;
    foreach ($dirs as $d) {
        if (preg_match('/^waha(\d+)$/', $d, $m)) {
            $n = (int)$m[1];
            // Automatic: waha3021 → port 3020 (n >= 3021, port = n - 1)
            if ($n >= 3021 && $n - 1 === $port) return "/srv/waha{$n}";
            // Manual: waha7 → port 3006 (n < 3021, port = 3000 + n - 1)
            if ($n < 3021 && 3000 + $n - 1 === $port) return "/srv/waha{$n}";
        }
    }
    return null;
}

// ─────────────────────────────────────────────────────────

try {
    switch ($action) {

        case 'status':
            // List docker containers
            $result = execCmd("docker ps --format '{{.Names}} {{.Status}} {{.Ports}}' --filter 'name=waha'");
            $containers = [];
            if ($result['ok'] && !empty($result['output'])) {
                foreach ($result['output'] as $line) {
                    $parts = preg_split('/\s+/', $line, 3);
                    $name = $parts[0] ?? '';
                    $status = $parts[1] ?? '';
                    $ports = $parts[2] ?? '';
                    // Extract port number from "0.0.0.0:3008->3000/tcp"
                    $port = 0;
                    if (preg_match('/:(\d+)->/', $ports, $m)) $port = (int)$m[1];
                    $containers[] = [
                        'name' => $name,
                        'port' => $port,
                        'status' => (strpos(strtolower($status), 'up') !== false) ? 'running' : 'stopped',
                        'ports_raw' => $ports,
                    ];
                }
            }

            // Also scan for WAHA API on active ports (dynamic range from docker ps)
            $maxPort = max(array_column($containers, 'port') ?: [0]);
            $scanMax = max(3050, $maxPort + 5);
            $apiPorts = [];
            for ($p = 3000; $p <= $scanMax; $p++) {
                $ch = curl_init("http://127.0.0.1:{$p}/api/sessions");
                curl_setopt_array($ch, [
                    CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Api-Key: local321'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 3,
                ]);
                $resp = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200) {
                    $sessions = @json_decode($resp, true);
                    $apiPorts[] = [
                        'port' => $p,
                        'sessions' => is_array($sessions) ? array_map(function($s) {
                            return [
                                'name' => $s['name'] ?? '?',
                                'status' => $s['status'] ?? 'unknown',
                                'phone' => isset($s['me']['id']) ? str_replace('@c.us', '', (string)$s['me']['id']) : '',
                            ];
                        }, $sessions) : [],
                    ];
                }
            }

            echo json_encode([
                'ok' => true,
                'containers' => $containers,
                'api_ports' => $apiPorts,
                'next_port' => getNextPort(),
            ]);
            break;

        case 'create':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $port = (int) ($_POST['port'] ?? 0);
            if ($port <= 0) { $port = getNextPort(); }
            // Convention for ports >= 3020: wahaNum = port + 1 (i.e. port 3020 → waha3021)
            $wahaNum = $port + 1;
            $instanceDir = "/srv/waha{$wahaNum}";

            // ── Cleanup if directory exists ──
            if (is_dir($instanceDir)) {
                // Delete any existing WAHA session first
                @wahaApiCall('DELETE', $port, '/api/sessions/default');
                execCmd("cd {$instanceDir} && docker compose down 2>/dev/null");
                execCmd("rm -rf {$instanceDir}/sessions/* {$instanceDir}/data/* 2>/dev/null");
            }

            // ── Create directory ──
            if (!@mkdir($instanceDir, 0755, true) && !is_dir($instanceDir)) {
                echo json_encode(['ok' => false, 'error' => "Cannot create {$instanceDir}"]);
                break;
            }

            // ── Write docker-compose.yml ──
            $webhookUrl = 'https://lamami.online/control/bot-casa/public/webhook.php';
            $compose = <<<YAML
services:
  waha{$wahaNum}:
    image: devlikeapro/waha:gows-2026.5.1
    container_name: waha{$wahaNum}
    restart: unless-stopped
    ports:
      - "{$port}:3000"
    pids_limit: -1
    ulimits:
      nproc: 65535
      nofile:
        soft: 65535
        hard: 65535
    security_opt:
      - seccomp=unconfined
    environment:
      - WAHA_API_KEY=local321
      - WHATSAPP_DEFAULT_ENGINE=GOWS
      - TZ=Europe/Madrid
      - WAHA_DASHBOARD_USERNAME=admin
      - WAHA_DASHBOARD_PASSWORD=admin123
      - WHATSAPP_SWAGGER_USERNAME=admin
      - WHATSAPP_SWAGGER_PASSWORD=admin123
      - WHATSAPP_HOOK_URL={$webhookUrl}
      - WHATSAPP_HOOK_EVENTS=message,message.any
    volumes:
      - ./data:/app/data
      - ./sessions:/app/.sessions
      - ./media:/app/.media
YAML;
            @file_put_contents("{$instanceDir}/docker-compose.yml", $compose);

            // ── Start container ──
            $result = execCmd("cd {$instanceDir} && docker compose up -d");
            if (!$result['ok']) {
                echo json_encode(['ok' => false, 'error' => 'docker compose up failed', 'output' => $result['output']]);
                break;
            }

            // ── Wait for WAHA to become reachable (retry up to 15s) ──
            $ready = false;
            for ($i = 0; $i < 15; $i++) {
                sleep(1);
                $check = @wahaApiCall('GET', $port, '/api/sessions');
                if ($check['http'] >= 200 && $check['http'] < 500) {
                    $ready = true;
                    break;
                }
            }
            if (!$ready) {
                echo json_encode(['ok' => false, 'error' => "WAHA did not become ready on port {$port} after 15s"]);
                break;
            }

            // ── Create session with webhook config ──
            $sessionPayload = json_encode([
                'name' => 'default',
                'config' => ['webhooks' => [['url' => $webhookUrl, 'events' => ['message', 'message.any']]]],
            ]);
            wahaApiCall('POST', $port, '/api/sessions', $sessionPayload);

            // ── PUT to ensure webhook config ──
            wahaApiCall('PUT', $port, '/api/sessions/default', $sessionPayload);

            // ── Start session ──
            wahaApiCall('POST', $port, '/api/sessions/default/start');

            echo json_encode([
                'ok' => true,
                'port' => $port,
                'dir' => $instanceDir,
                'message' => 'WAHA instance created and session started. QR is now available.',
            ]);
            break;

        case 'delete':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $port = (int) ($_POST['port'] ?? 0);
            if ($port <= 0) { echo json_encode(['ok'=>false,'error'=>'port required']); break; }

            $instanceDir = findWahaDir($port);
            if ($instanceDir === null) {
                echo json_encode(['ok' => false, 'error' => "No WAHA directory found for port {$port}"]);
                break;
            }

            // Delete session, then stop container
            @wahaApiCall('DELETE', $port, '/api/sessions/default');
            $r1 = execCmd("cd {$instanceDir} && docker compose down 2>/dev/null");
            echo json_encode(['ok' => $r1['ok'], 'output' => $r1['output']]);
            break;

        case 'reset':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $port = (int) ($_POST['port'] ?? 0);
            if ($port <= 0) { echo json_encode(['ok'=>false,'error'=>'port required']); break; }

            $instanceDir = findWahaDir($port);
            if ($instanceDir === null) {
                echo json_encode(['ok' => false, 'error' => "No WAHA directory found for port {$port}"]);
                break;
            }

            // Delete session → stop → clean → restart → recreate session → start
            @wahaApiCall('DELETE', $port, '/api/sessions/default');
            $r1 = execCmd("cd {$instanceDir} && docker compose down 2>/dev/null");
            $r2 = execCmd("rm -rf {$instanceDir}/sessions/* {$instanceDir}/data/* 2>/dev/null");
            $r3 = execCmd("cd {$instanceDir} && docker compose up -d");

            if (!$r3['ok']) {
                echo json_encode(['ok' => false, 'error' => 'docker compose up failed', 'output' => array_merge($r1['output'] ?? [], $r2['output'] ?? [], $r3['output'] ?? [])]);
                break;
            }

            // Wait for WAHA
            $ready = false;
            for ($i = 0; $i < 15; $i++) {
                sleep(1);
                $check = @wahaApiCall('GET', $port, '/api/sessions');
                if ($check['http'] >= 200 && $check['http'] < 500) { $ready = true; break; }
            }

            $webhookUrl = 'https://lamami.online/control/bot-casa/public/webhook.php';
            $sessionPayload = json_encode([
                'name' => 'default',
                'config' => ['webhooks' => [['url' => $webhookUrl, 'events' => ['message', 'message.any']]]],
            ]);
            wahaApiCall('POST', $port, '/api/sessions', $sessionPayload);
            wahaApiCall('PUT', $port, '/api/sessions/default', $sessionPayload);
            wahaApiCall('POST', $port, '/api/sessions/default/start');

            echo json_encode(['ok' => true, 'message' => 'Session reset complete']);
            break;

        case 'start_session':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $port = (int) ($_POST['port'] ?? 0);
            if ($port <= 0) { echo json_encode(['ok'=>false,'error'=>'port required']); break; }

            $result = wahaApiCall('POST', $port, '/api/sessions/default/start');
            echo json_encode(['ok' => $result['ok'], 'http' => $result['http'], 'response' => $result['body'] ? json_decode($result['body'], true) : null]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action', 'available' => ['status','create','delete','reset','start_session']]);
    }
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
