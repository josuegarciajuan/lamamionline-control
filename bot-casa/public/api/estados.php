<?php
/**
 * api/estados.php — Configuración y publicación de estados WhatsApp.
 *
 * Almacena en data/users/{userId}/estados.json
 * Publica vía WAHA API (la misma que usa el bot).
 */
declare(strict_types=1);

define('WASAPBOT_ROOT', dirname(__DIR__, 2));
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? 'config');
$userId = (int) ($_SESSION['user_id'] ?? 0);
if (($_SESSION['role']??'') === 'admin' && !empty($_SESSION['suplantar_user_id'])) {
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

$estadosFile = WASAPBOT_ROOT . '/data/users/' . $userId . '/estados.json';

function defaultConfig(): array {
    return [
        'enabled' => 0,
        'frecuencia_tipo' => 'cada_x_horas',
        'frecuencia_valor' => 6,
        'hora_inicio' => '08:00',
        'hora_fin' => '23:00',
        'formato' => 'chicas_de_hoy',
        'lineas' => [],
        'last_scheduled_run_at' => null,
        'log' => [],
    ];
}
function loadEstados(): array {
    global $estadosFile;
    if (!file_exists($estadosFile)) return defaultConfig();
    $data = @json_decode((string)@file_get_contents($estadosFile), true);
    if (!is_array($data)) return defaultConfig();
    return array_merge(defaultConfig(), $data);
}
function saveEstados(array $data): void {
    global $estadosFile;
    $dir = dirname($estadosFile);
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $data['log'] = array_slice($data['log'] ?? [], -50);
    @file_put_contents($estadosFile, json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);
}
function fetchActiveGirls(int $uid): array {
    $gf = WASAPBOT_ROOT . '/data/users/' . $uid . '/girls.json';
    if (!file_exists($gf)) return [];
    $d = @json_decode((string)@file_get_contents($gf), true);
    if (!is_array($d)) return [];
    return array_filter($d['girls'] ?? [], fn($g) => !empty($g['activa']));
}
function getUserLines(int $uid): array {
    $lf = WASAPBOT_ROOT . '/data/users/' . $uid . '/lines.json';
    if (!file_exists($lf)) return [];
    $d = @json_decode((string)@file_get_contents($lf), true);
    return is_array($d) ? $d : [];
}

$formatOptions = [
    'chicas_de_hoy' => 'Todas las chicas, 1 foto cada una',
    'chica_del_dia' => '1 chica aleatoria, 2 fotos',
    'duo_sexy' => '2 chicas aleatorias, 1 foto cada una',
    'catalogo_rapido' => 'Solo nombres, sin fotos',
    'mix_aleatorio' => 'Formato aleatorio cada ciclo',
];
$freqOptions = [
    'cada_x_horas' => 'Cada X horas',
    'x_veces_al_dia' => 'X veces al día',
];

header('Content-Type: application/json; charset=utf-8');

// Validate CSRF for all POST requests
if ($method === 'POST') requireValidCsrf();

try {
    switch ($action) {
        case 'config':
            if ($method === 'POST') {
                $cfg = loadEstados();
                $cfg['enabled'] = isset($_POST['enabled']) ? 1 : 0;
                if (isset($_POST['frecuencia_tipo']) && in_array($_POST['frecuencia_tipo'], ['cada_x_horas','x_veces_al_dia'])) {
                    $cfg['frecuencia_tipo'] = $_POST['frecuencia_tipo'];
                }
                if (isset($_POST['frecuencia_valor'])) $cfg['frecuencia_valor'] = max(1, min(24, (int)$_POST['frecuencia_valor']));
                if (isset($_POST['hora_inicio'])) $cfg['hora_inicio'] = $_POST['hora_inicio'];
                if (isset($_POST['hora_fin'])) $cfg['hora_fin'] = $_POST['hora_fin'];
                if (isset($_POST['formato']) && isset($formatOptions[$_POST['formato']])) $cfg['formato'] = $_POST['formato'];
                if (isset($_POST['lineas']) && is_array($_POST['lineas'])) $cfg['lineas'] = array_map('intval', $_POST['lineas']);
                saveEstados($cfg);
                echo json_encode(['ok' => true, 'config' => $cfg]);
            } else {
                $cfg = loadEstados();
                $cfg['available_lines'] = getUserLines($userId);
                echo json_encode(['ok' => true, 'config' => $cfg]);
            }
            break;

        case 'publish':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            $cfg = loadEstados();
            if (empty($cfg['enabled'])) { echo json_encode(['ok'=>false,'error'=>'Estados desactivados']); break; }

            $girls = fetchActiveGirls($userId);
            if (empty($girls)) { echo json_encode(['ok'=>false,'error'=>'No hay chicas activas']); break; }

            $lineIds = $cfg['lineas'];
            $allLines = getUserLines($userId);
            $selectedLines = array_filter($allLines, fn($l) => in_array((int)($l['id']??0), $lineIds));
            if (empty($selectedLines)) { echo json_encode(['ok'=>false,'error'=>'No hay líneas seleccionadas']); break; }

            // Build status text
            $formato = $cfg['formato'];
            $txt = '';
            $shuffled = $girls;
            shuffle($shuffled);

            switch ($formato) {
                case 'chicas_de_hoy':
                    $nombres = array_map(fn($g) => $g['nombre'], $shuffled);
                    $txt = '💋 ' . implode(' · ', $nombres) . ' 💋';
                    break;
                case 'chica_del_dia':
                    $g = $shuffled[0];
                    $foto = !empty($g['fotos'][0]) ? $g['fotos'][0] : '';
                    $txt = '🔥 ' . $g['nombre'] . ' 🔥' . ($foto ? "\n" . $foto : '');
                    break;
                case 'duo_sexy':
                    $duo = array_slice($shuffled, 0, 2);
                    $txt = '👯 ' . implode(' & ', array_map(fn($g) => $g['nombre'], $duo)) . ' 👯';
                    break;
                case 'catalogo_rapido':
                    $txt = '📋 ' . implode(', ', array_map(fn($g) => $g['nombre'], $shuffled));
                    break;
                case 'mix_aleatorio':
                    $k = array_rand($formatOptions, 1);
                    $cfg['formato'] = $k;
                    break;
            }

            $results = [];
            $wahaApiKey = $_ENV['WAHA_API_KEY'] ?? 'local321';
            $wahaServer = $_ENV['WAHA_SERVER'] ?? '100.117.92.74';

            foreach ($selectedLines as $line) {
                $port = (int) ($line['port'] ?? 0);
                if ($port <= 0) { $results[] = ['line_id'=>$line['id'], 'ok'=>false, 'error'=>'Sin puerto']; continue; }

                $payload = json_encode(['text' => $txt, 'backgroundColor' => '#25D366', 'font' => 0]);
                $ch = curl_init("http://{$wahaServer}:{$port}/api/default/status/text");
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', "X-Api-Key: {$wahaApiKey}"],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 15,
                ]);
                $resp = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $ok = $httpCode >= 200 && $httpCode < 300;
                $results[] = ['line_id' => $line['id'], 'ok' => $ok, 'http_code' => $httpCode];
            }

            // Log
            $cfg['log'][] = [
                'published_at' => date('c'),
                'formato' => $formato,
                'texto' => $txt,
                'resultados' => $results,
            ];
            $cfg['last_scheduled_run_at'] = date('c');
            saveEstados($cfg);

            echo json_encode(['ok' => true, 'results' => $results, 'text' => $txt]);
            break;

        case 'history':
            $cfg = loadEstados();
            echo json_encode(['ok' => true, 'log' => array_reverse($cfg['log'] ?? [])]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (\Throwable $e) {
    error_log('estados.php error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
