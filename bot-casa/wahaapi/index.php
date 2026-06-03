<?php
/**
 * wahaapi/index.php — API ligera de gestión WAHA en servidor local.
 *
 * Desplegar en: /var/www/html/wahaapi/
 * Apache lo sirve en http://<ip>/wahaapi/
 *
 * Acciones:
 *   GET  ?action=status              → lista instancias WAHA (docker ps + scan)
 *   POST ?action=create&port=3020    → crea nueva instancia docker WAHA
 *   POST ?action=delete&port=3020    → elimina instancia docker WAHA
 *   POST ?action=reset&port=3020     → resetea sesión (borra data/sessions, reconfigura)
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

function getNextPort(): int {
    $existing = [];
    $dirs = @scandir('/srv/');
    if ($dirs) {
        foreach ($dirs as $d) {
            if (preg_match('/^waha(\d+)$/', $d, $m)) {
                $existing[(int)$m[1]] = true;
            }
        }
    }
    // Convention: wahaN → port N-1. Existing: waha1..waha11 = ports 3000..3011
    // New lines start at port 3020 = waha3021
    $startWaha = 3021; // port 3020
    for ($n = $startWaha; $n < 3100; $n++) {
        if (!isset($existing[$n])) return $n - 1; // return port
    }
    return $startWaha - 1; // fallback
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
                        'status' => str_contains(strtolower($status), 'up') ? 'running' : 'stopped',
                        'ports_raw' => $ports,
                    ];
                }
            }

            // Also scan for WAHA API on common ports
            $apiPorts = [];
            for ($p = 3000; $p <= max(3050, count($containers) > 0 ? max(array_column($containers, 'port')) : 0); $p++) {
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
            // Convention: wahaN → docker port = N-1. So port 3020 → waha3021
            $wahaNum = $port + 1;
            $instanceDir = "/srv/waha{$wahaNum}";

            if (is_dir($instanceDir)) {
                echo json_encode(['ok' => false, 'error' => "Directory {$instanceDir} already exists"]);
                break;
            }

            // Create directory
            if (!@mkdir($instanceDir, 0755, true)) {
                echo json_encode(['ok' => false, 'error' => "Cannot create {$instanceDir}"]);
                break;
            }

            // Write docker-compose.yml
            $compose = <<<YAML
services:
  waha{$wahaNum}:
    image: devlikeapro/waha:latest
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
      - WHATSAPP_HOOK_URL=https://lamami.online/control/bot-casa/public/webhook.php
      - WHATSAPP_HOOK_EVENTS=message
    volumes:
      - ./data:/app/data
      - ./sessions:/app/.sessions
      - ./media:/app/.media
YAML;
            @file_put_contents("{$instanceDir}/docker-compose.yml", $compose);

            // Start container
            $result = execCmd("cd {$instanceDir} && docker compose up -d");
            if (!$result['ok']) {
                echo json_encode(['ok' => false, 'error' => 'docker compose up failed', 'output' => $result['output']]);
                break;
            }

            // Wait a moment and configure webhook
            sleep(2);
            $webhookUrl = 'https://lamami.online/control/bot-casa/public/webhook.php';
            $ch = curl_init("http://127.0.0.1:{$port}/api/sessions/default");
            $payload = json_encode([
                'name' => 'default',
                'config' => ['webhooks' => [['url' => $webhookUrl, 'events' => ['message']]]],
            ]);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'X-Api-Key: local321'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            curl_exec($ch);
            curl_close($ch);

            echo json_encode([
                'ok' => true,
                'port' => $containerPort,
                'dir' => $instanceDir,
                'message' => 'WAHA instance created. Use start_session to begin WhatsApp linking.',
            ]);
            break;

        case 'delete':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $port = (int) ($_POST['port'] ?? 0);
            if ($port <= 0) { echo json_encode(['ok'=>false,'error'=>'port required']); break; }

            $wahaNum = $port + 1;
            $instanceDir = "/srv/waha{$wahaNum}";
            if (!is_dir($instanceDir)) {
                echo json_encode(['ok' => false, 'error' => "Instance directory {$instanceDir} not found"]);
                break;
            }

            $result = execCmd("cd {$instanceDir} && docker compose down 2>/dev/null; rm -rf {$instanceDir}");
            echo json_encode(['ok' => $result['ok'], 'output' => $result['output']]);
            break;

        case 'reset':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $port = (int) ($_POST['port'] ?? 0);
            if ($port <= 0) { echo json_encode(['ok'=>false,'error'=>'port required']); break; }

            $wahaNum = $port + 1;
            $instanceDir = "/srv/waha{$wahaNum}";
            if (!is_dir($instanceDir)) {
                echo json_encode(['ok' => false, 'error' => "Directory {$instanceDir} not found"]);
                break;
            }

            // Stop, cleanup, restart
            $r1 = execCmd("cd {$instanceDir} && docker compose down");
            $r2 = execCmd("rm -rf {$instanceDir}/sessions/* {$instanceDir}/data/* 2>/dev/null");
            $r3 = execCmd("cd {$instanceDir} && docker compose up -d");

            echo json_encode([
                'ok' => $r3['ok'],
                'output' => array_merge($r1['output'] ?? [], $r2['output'] ?? [], $r3['output'] ?? []),
            ]);
            break;

        case 'start_session':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $port = (int) ($_POST['port'] ?? 0);
            if ($port <= 0) { echo json_encode(['ok'=>false,'error'=>'port required']); break; }

            $ch = curl_init("http://127.0.0.1:{$port}/api/sessions/default/start");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Api-Key: local321'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            echo json_encode(['ok' => true, 'response' => $resp ? json_decode($resp, true) : null]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action', 'available' => ['status','create','delete','reset','start_session']]);
    }
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
