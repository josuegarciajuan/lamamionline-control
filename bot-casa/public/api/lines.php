<?php
/**
 * api/lines.php — CRUD de líneas WhatsApp para bot-casa multi-usuario.
 *
 * Usa WahaManager (HTTP) para operaciones reales con WAHA.
 * Puerto inicial para nuevas líneas: 3020 (las existentes 3000-3011 se respetan).
 */
declare(strict_types=1);

define('WASAPBOT_ROOT', dirname(__DIR__, 2));
spl_autoload_register(function (string $class): void {
    $prefix = 'WasapBot\\'; $prefixLen = strlen($prefix);
    if (strncmp($prefix, $class, $prefixLen) !== 0) return;
    $file = WASAPBOT_ROOT . '/src/' . str_replace('\\', '/', substr($class, $prefixLen)) . '.php';
    if (file_exists($file)) require_once $file;
});

$isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
session_set_cookie_params(["lifetime"=>0,"path"=>"/","secure"=>$isHttps,"httponly"=>true,"samesite"=>"Lax"]);
$isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
session_set_cookie_params(["lifetime"=>0,"path"=>"/","secure"=>$isHttps,"httponly"=>true,"samesite"=>"Lax"]);
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? 'list');
$userId = (int) ($_SESSION['user_id'] ?? 0);
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
if ($isAdmin && !empty($_SESSION['suplantar_user_id'])) $userId = (int) $_SESSION['suplantar_user_id'];

$linesFile = WASAPBOT_ROOT . '/data/users/' . $userId . '/lines.json';
$linesMapFile = WASAPBOT_ROOT . '/data/lines_map.json';

function loadLines(): array {
    global $linesFile;
    if (!file_exists($linesFile)) return [];
    $data = @json_decode((string)@file_get_contents($linesFile), true);
    return is_array($data) ? $data : [];
}
function saveLines(array $lines): void {
    global $linesFile;
    $dir = dirname($linesFile);
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    @file_put_contents($linesFile, json_encode($lines, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);
}
function updateLineMap(string $last9, int $uid): void {
    global $linesMapFile;
    $map = [];
    if (file_exists($linesMapFile)) {
        $map = @json_decode((string)@file_get_contents($linesMapFile), true);
        if (!is_array($map)) $map = [];
    }
    $map[$last9] = $uid;
    @file_put_contents($linesMapFile, json_encode($map, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n", LOCK_EX);
}
function removeLineMap(string $last9): void {
    global $linesMapFile;
    if (!file_exists($linesMapFile)) return;
    $map = @json_decode((string)@file_get_contents($linesMapFile), true);
    if (!is_array($map)) return;
    unset($map[$last9]);
    @file_put_contents($linesMapFile, json_encode($map, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n", LOCK_EX);
}

/**
 * Load telefonos.json and build a lookup map: last9 → descripcion (notas or nombre).
 * @return array<string, string>
 */
function loadTelefonosNotas(): array {
    $candidates = [
        WASAPBOT_ROOT . '/../../data/telefonos.json',
        WASAPBOT_ROOT . '/../data/telefonos.json',
        WASAPBOT_ROOT . '/data/telefonos.json',
        dirname(WASAPBOT_ROOT, 3) . '/data/telefonos.json',
    ];
    $raw = null;
    foreach ($candidates as $path) {
        $real = realpath($path);
        if ($real !== false && file_exists($real)) {
            $contents = @file_get_contents($real);
            if ($contents !== false) { $raw = $contents; break; }
        }
    }
    if ($raw === null) return [];

    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (\JsonException) {
        return [];
    }
    if (!is_array($decoded)) return [];

    $map = [];
    foreach ($decoded as $t) {
        if (!is_array($t)) continue;
        $tfono = trim((string)($t['tfono'] ?? ''));
        if ($tfono === '') continue;
        $digits = preg_replace('/[^0-9]/', '', $tfono);
        if ($digits === '' || strlen($digits) < 9) continue;
        $last9 = substr($digits, -9);
        $notas = trim((string)($t['notas'] ?? ''));
        $nombre = trim((string)($t['nombre'] ?? ''));
        $descripcion = ($notas !== '') ? $notas : $nombre;
        if ($descripcion !== '') {
            $map[$last9] = $descripcion;
        }
    }
    return $map;
}

/**
 * Enrich a lines array with descripcion from telefonos.json notas field.
 * @param list<array<string,mixed>> $lines
 * @return list<array<string,mixed>>
 */
function enrichLinesWithDescripcion(array $lines): array {
    static $notasMap = null;
    if ($notasMap === null) {
        $notasMap = loadTelefonosNotas();
    }
    foreach ($lines as &$line) {
        $last9 = (string)($line['last9'] ?? '');
        if ($last9 !== '' && isset($notasMap[$last9])) {
            $line['descripcion'] = $notasMap[$last9];
        } elseif (($line['label'] ?? '') !== '') {
            $line['descripcion'] = $line['label'];
        } else {
            $line['descripcion'] = 'Línea ' . ($line['id'] ?? '');
        }
    }
    unset($line);
    return $lines;
}

/**
 * Fallback: load routing lines from root config when the admin user
 * has no per-user lines.json. Returns lines in chat-compatible format.
 */
function loadRoutingLinesFallback(): array {
    $rootConfig = new \WasapBot\Core\Config(WASAPBOT_ROOT);
    $routingLines = $rootConfig->get('routing.lines', []);
    if (!is_array($routingLines)) return [];
    $lines = [];
    $idx = 1;
    foreach ($routingLines as $rl) {
        if (!is_array($rl)) continue;
        if (!((bool)($rl['enabled'] ?? true))) continue;
        $last9 = (string)($rl['last9'] ?? '');
        $lines[] = [
            'id'              => $idx++,
            'last9'           => $last9,
            'phone'           => $last9,
            'label'           => (string)($rl['label'] ?? ('Línea ' . ($rl['port'] ?? ''))),
            'port'            => (int)($rl['port'] ?? 0),
            'container_port'  => (int)($rl['port'] ?? 0),
            'created_at'      => date('c'),
            'health_status'   => 'unknown',
        ];
    }
    return $lines;
}

$wahaCfg = [
    'waha_server' => '100.117.92.74',
    'waha_api_key' => 'local321',
    'webhook_url' => 'https://lamami.online/control/bot-casa/public/webhook.php',
];
$wm = new \WasapBot\Core\WahaManager($wahaCfg);

header('Content-Type: application/json; charset=utf-8');

try {
    switch ($action) {

        case 'list':
            $lines = loadLines();
            if (empty($lines) && $isAdmin && empty($_SESSION['suplantar_user_id'])) {
                $lines = loadRoutingLinesFallback();
            }
            // Enrich with descripcion from telefonos.json
            $lines = enrichLinesWithDescripcion($lines);
            // Try to cross-reference with WAHA status (fails gracefully)
            try {
                $realStatus = $wm->scanInstances();
                foreach ($lines as &$line) {
                    $port = (int) ($line['port'] ?? 0);
                    if ($port > 0 && isset($realStatus[$port])) {
                        $rs = $realStatus[$port];
                        $sess = $rs['sessions'][0] ?? [];
                        $line['health_status'] = $sess['status'] ?? 'unknown';
                        $line['health_phone'] = $rs['phone'] ?? '';
                    } elseif ($port > 0) {
                        $line['health_status'] = 'unknown';
                    } else {
                        $line['health_status'] = 'pending';
                    }
                }
                unset($line);
            } catch (\Throwable $e) {
                // WAHA not available — just show local data
                foreach ($lines as &$line) { $line['health_status'] = $line['health_status'] ?? 'unknown'; }
                unset($line);
            }
            echo json_encode(['ok' => true, 'lines' => $lines]);
            break;

        case 'available':
            // Show available ports for new lines (3020+ and any free)
            $status = $wm->getStatus();
            $aval = ['next_port' => $status['next_port'] ?? 3020, 'api_ports' => $status['api_ports'] ?? []];
            echo json_encode(['ok' => true, 'available' => $aval]);
            break;

        case 'add':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $phone = trim((string)($_POST['phone'] ?? ''));
            $label = trim((string)($_POST['label'] ?? ''));
            $port  = (int) ($_POST['port'] ?? 0);
            if ($phone === '') { echo json_encode(['ok'=>false,'error'=>'Número requerido']); break; }

            $last9 = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($last9) < 9) $last9 = str_pad($last9, 9, '0', STR_PAD_LEFT);
            $last9 = mb_substr($last9, -9);

            $lines = loadLines();
            // Check if this phone is already assigned
            foreach ($lines as $l) { if (((string)($l['last9']??'') === $last9)) { echo json_encode(['ok'=>false,'error'=>'Este número ya está configurado.']); exit; } }
            // Check lines_map for global duplicates
            $globalMap = [];
            if (file_exists($linesMapFile)) {
                $globalMap = @json_decode((string)@file_get_contents($linesMapFile), true);
                if (!is_array($globalMap)) $globalMap = [];
            }
            if (isset($globalMap[$last9])) { echo json_encode(['ok'=>false,'error'=>'Este número ya está en uso por otro usuario.']); exit; }

            // If no port specified, get next available
            if ($port <= 0) {
                $status = $wm->getStatus();
                $port = (int) ($status['next_port'] ?? 3020);
            }

            // Try to create WAHA instance (fails gracefully if WAHA not available)
            $result = ['ok' => false, 'error' => 'WAHA no disponible — línea creada localmente.'];
            try {
                $result = $wm->createInstance($port);
            } catch (\Throwable $e) {
                $result = ['ok' => false, 'error' => 'WAHA no disponible'];
            }

            if ($result['ok']) {
                $nextId = count($lines) > 0 ? max(array_column($lines, 'id')) + 1 : 1;
                $line = [
                    'id' => $nextId,
                    'last9' => $last9,
                    'phone' => $phone,
                    'label' => $label !== '' ? $label : ('Línea ' . $nextId),
                    'port' => $result['port'] ?? $port,
                    'container_port' => $port,
                    'created_at' => date('c'),
                    'health_status' => 'starting',
                    'error' => '',
                ];
                $lines[] = $line;
                saveLines($lines);
                updateLineMap($last9, $userId);
                echo json_encode(['ok' => true, 'line' => $line, 'result' => $result]);
            } else {
                // Don't persist the line when WAHA creation fails — avoid phantom lines
                echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'WAHA no disponible — inténtalo de nuevo.']);
            }
            break;

        case 'qr':
            $lineId = (int) ($_GET['line_id'] ?? 0);
            $lines = loadLines();
            $found = null;
            foreach ($lines as $l) { if ((int)($l['id']??0) === $lineId) { $found = $l; break; } }
            if (!$found || empty($found['port'])) { echo json_encode(['ok'=>false,'error'=>'Línea no encontrada o sin puerto']); break; }

            $port = (int) $found['port'];

            // Start session before getting QR (idempotent if already running)
            $wm->startSession($port);

            // getQrCode retries internally up to 5 times if WAHA is still booting
            $qr = $wm->getQrCode($port);

            // Add warning about expiration
            if ($qr['ok']) {
                $qr['warning'] = '⚠️ El QR caduca en 30-60 segundos. Ten el móvil listo para escanear.';
            }
            echo json_encode($qr);
            break;

        case 'start_session':
            $lineId = (int) ($_GET['line_id'] ?? 0);
            $lines = loadLines();
            $found = null;
            foreach ($lines as $l) { if ((int)($l['id']??0) === $lineId) { $found = $l; break; } }
            if (!$found || empty($found['port'])) { echo json_encode(['ok'=>false,'error'=>'Línea no encontrada o sin puerto']); break; }

            $port = (int) $found['port'];
            $result = $wm->startSession($port);
            echo json_encode(['ok' => $result['ok'] ?? true, 'result' => $result]);
            break;

        case 'test':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $lineId = (int) ($_POST['line_id'] ?? 0);
            $testPhone = trim((string)($_POST['test_phone'] ?? ''));
            if ($testPhone === '') { echo json_encode(['ok'=>false,'error'=>'Teléfono de prueba requerido']); break; }

            $lines = loadLines();
            $found = null;
            foreach ($lines as $l) { if ((int)($l['id']??0) === $lineId) { $found = $l; break; } }
            if (!$found || empty($found['port'])) { echo json_encode(['ok'=>false,'error'=>'Línea no encontrada']); break; }

            $digits = preg_replace('/[^0-9]/', '', $testPhone);
            // Auto-detect Spanish local numbers (9 digits, starting with 6/7) → prepend 34
            if (strlen($digits) === 9 && preg_match('/^[67]/', $digits)) {
                $digits = '34' . $digits;
            }
            $chatId = $digits . '@c.us';
            $result = $wm->sendTestMessage((int) $found['port'], $chatId, '✅ Mensaje de prueba desde bot-casa');
            echo json_encode($result);
            break;

        case 'status':
            $lines = loadLines();
            if (empty($lines) && $isAdmin && empty($_SESSION['suplantar_user_id'])) {
                $lines = loadRoutingLinesFallback();
            }
            $lines = enrichLinesWithDescripcion($lines);
            $realStatus = $wm->scanInstances();
            $statuses = [];
            foreach ($lines as $line) {
                $port = (int) ($line['port'] ?? 0);
                if ($port > 0 && isset($realStatus[$port])) {
                    $s = $realStatus[$port]['sessions'][0] ?? [];
                    $statuses[(int)($line['id']??0)] = $s['status'] ?? 'unknown';
                } elseif ($port > 0) {
                    $statuses[(int)($line['id']??0)] = 'down';
                } else {
                    $statuses[(int)($line['id']??0)] = 'pending';
                }
            }
            echo json_encode(['ok' => true, 'statuses' => $statuses]);
            break;

        case 'delete':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $lineId = (int) ($_POST['line_id'] ?? 0);
            $lines = loadLines();
            $found = null; $idx = -1;
            foreach ($lines as $i => $l) { if ((int)($l['id']??0) === $lineId) { $found = $l; $idx = $i; break; } }
            if ($found === null) { echo json_encode(['ok'=>false,'error'=>'Línea no encontrada']); break; }

            $port = (int) ($found['port'] ?? 0);
            if ($port > 0) {
                // Reset the instance (don't delete, just clean session)
                $wm->resetInstance($port);
                removeLineMap((string)($found['last9'] ?? ''));
            }
            array_splice($lines, $idx, 1);
            saveLines($lines);
            echo json_encode(['ok' => true]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (\Throwable $e) {
    error_log('[lines API] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
