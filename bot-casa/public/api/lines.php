<?php
/**
 * api/lines.php — CRUD de líneas WhatsApp para bot-casa multi-usuario.
 *
 * Llamado vía AJAX desde client.php.
 * Requiere sesión autenticada.
 */
declare(strict_types=1);

define('WASAPBOT_ROOT', dirname(__DIR__, 2));
spl_autoload_register(function (string $class): void {
    $prefix = 'WasapBot\\'; $prefixLen = strlen($prefix);
    if (strncmp($prefix, $class, $prefixLen) !== 0) return;
    $file = WASAPBOT_ROOT . '/src/' . str_replace('\\', '/', substr($class, $prefixLen)) . '.php';
    if (file_exists($file)) require_once $file;
});

session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? 'list');
$userId = (int) ($_SESSION['user_id'] ?? 0);
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// Admin suplantar support
if ($isAdmin && !empty($_SESSION['suplantar_user_id'])) {
    $userId = (int) $_SESSION['suplantar_user_id'];
}

// ── CSRF protection for POST requests ──
function requireValidCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'CSRF token required']); exit; }
    $secretFile = WASAPBOT_ROOT . '/data/.csrf_secret';
    $secret = '';
    if (file_exists($secretFile)) {
        $secret = trim((string) @file_get_contents($secretFile));
    }
    if (strlen($secret) < 32) {
        $secret = bin2hex(random_bytes(32));
        $dir = dirname($secretFile);
        if (!is_dir($dir)) @mkdir($dir, 0700, true);
        @file_put_contents($secretFile, $secret, LOCK_EX);
        @chmod($secretFile, 0600);
    }
    $realUserId = (int) ($_SESSION['user_id'] ?? 0);
    $current = hash_hmac('sha256', $realUserId . '|' . date('Y-m-d-H') . floor((int) date('i') / 10), $secret);
    if (hash_equals($current, $token)) return;
    $prevSlot = max(0, floor((int) date('i') / 10) - 1);
    $previous = hash_hmac('sha256', $realUserId . '|' . date('Y-m-d-H') . $prevSlot, $secret);
    if (hash_equals($previous, $token)) return;
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'CSRF token invalid']);
    exit;
}

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

header('Content-Type: application/json; charset=utf-8');

// Validate CSRF for all POST requests
if ($method === 'POST') requireValidCsrf();

try {
    switch ($action) {
        case 'list':
            $lines = loadLines();
            // Check status for each line
            $wahaCfg = [
                'waha_server' => $_ENV['WAHA_SERVER'] ?? '100.117.92.74',
                'waha_api_key' => $_ENV['WAHA_API_KEY'] ?? 'local321',
            ];
            $wm = new \WasapBot\Core\WahaManager($wahaCfg);
            foreach ($lines as &$line) {
                $port = (int) ($line['port'] ?? 0);
                if ($port > 0) {
                    $status = $wm->checkStatus($port);
                    $line['health_status'] = $status['status'] ?? 'unknown';
                } else {
                    $line['health_status'] = 'pending';
                }
            }
            unset($line);
            echo json_encode(['ok' => true, 'lines' => $lines]);
            break;

        case 'add':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $phone = trim((string)($_POST['phone'] ?? ''));
            $label = trim((string)($_POST['label'] ?? ''));
            if ($phone === '') { echo json_encode(['ok'=>false,'error'=>'Número requerido']); break; }

            $last9 = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($last9) < 9) $last9 = str_pad($last9, 9, '0', STR_PAD_LEFT);
            $last9 = mb_substr($last9, -9);

            $lines = loadLines();
            $nextId = count($lines) > 0 ? max(array_column($lines, 'id')) + 1 : 1;

            $wahaCfg = [
                'waha_server' => $_ENV['WAHA_SERVER'] ?? '100.117.92.74',
                'waha_api_key' => $_ENV['WAHA_API_KEY'] ?? 'local321',
                'webhook_url' => 'https://lamami.online/control/bot-casa/public/webhook.php',
            ];
            $wm = new \WasapBot\Core\WahaManager($wahaCfg);
            $result = $wm->createInstance($last9, $userId);

            $port = $result['port'] ?? 0;
            $line = [
                'id' => $nextId,
                'last9' => $last9,
                'phone' => $phone,
                'label' => $label !== '' ? $label : ('Línea ' . $nextId),
                'port' => $port,
                'created_at' => date('c'),
                'health_status' => 'starting',
            ];

            if (!$result['ok']) {
                $line['port'] = 0;
                $line['health_status'] = 'error';
                $line['error'] = $result['error'] ?? 'Failed to create instance';
            }

            $lines[] = $line;
            saveLines($lines);
            if ($port > 0) updateLineMap($last9, $userId);
            echo json_encode(['ok' => true, 'line' => $line]);
            break;

        case 'qr':
            $lineId = (int) ($_GET['line_id'] ?? 0);
            $lines = loadLines();
            $found = null;
            foreach ($lines as $l) { if ((int)($l['id']??0) === $lineId) { $found = $l; break; } }
            if (!$found || empty($found['port'])) { echo json_encode(['ok'=>false,'error'=>'Línea no encontrada']); break; }

            $wahaCfg = ['waha_server'=>$_ENV['WAHA_SERVER']??'100.117.92.74','waha_api_key'=>$_ENV['WAHA_API_KEY']??'local321'];
            $wm = new \WasapBot\Core\WahaManager($wahaCfg);
            $qr = $wm->getQrCode((int) $found['port']);
            echo json_encode($qr);
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
            $chatId = $digits . '@c.us';

            $wahaCfg = ['waha_server'=>$_ENV['WAHA_SERVER']??'100.117.92.74','waha_api_key'=>$_ENV['WAHA_API_KEY']??'local321'];
            $wm = new \WasapBot\Core\WahaManager($wahaCfg);
            $result = $wm->sendTestMessage((int) $found['port'], $chatId, '✅ Mensaje de prueba desde bot-casa');
            echo json_encode($result);
            break;

        case 'status':
            $lines = loadLines();
            $wahaCfg = ['waha_server'=>$_ENV['WAHA_SERVER']??'100.117.92.74','waha_api_key'=>$_ENV['WAHA_API_KEY']??'local321'];
            $wm = new \WasapBot\Core\WahaManager($wahaCfg);
            $statuses = [];
            foreach ($lines as $line) {
                $port = (int) ($line['port'] ?? 0);
                $s = $port > 0 ? $wm->checkStatus($port) : ['ok'=>false,'status'=>'pending'];
                $statuses[(int)($line['id']??0)] = $s['status'] ?? 'unknown';
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
                $wahaCfg = ['waha_server'=>$_ENV['WAHA_SERVER']??'100.117.92.74'];
                $wm = new \WasapBot\Core\WahaManager($wahaCfg);
                $wm->deleteInstance($port);
                removeLineMap((string)($found['last9'] ?? ''));
            }

            array_splice($lines, $idx, 1);
            saveLines($lines);
            echo json_encode(['ok' => true]);
            break;

        case 'start_session':
            $lineId = (int) ($_GET['line_id'] ?? 0);
            $lines = loadLines();
            $found = null;
            foreach ($lines as $l) { if ((int)($l['id']??0) === $lineId) { $found = $l; break; } }
            if (!$found || empty($found['port'])) { echo json_encode(['ok'=>false,'error'=>'Línea no encontrada']); break; }

            $port = (int) $found['port'];
            $baseUrl = "http://" . ($_ENV['WAHA_SERVER']??'100.117.92.74') . ":{$port}";
            $apiKey = $_ENV['WAHA_API_KEY'] ?? 'local321';

            $ch = curl_init("{$baseUrl}/api/sessions/default/start");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json', "X-Api-Key: {$apiKey}"],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
            curl_exec($ch);
            curl_close($ch);
            echo json_encode(['ok' => true]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (\Throwable $e) {
    error_log('lines.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
