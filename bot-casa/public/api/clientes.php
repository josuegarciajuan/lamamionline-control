<?php
/**
 * api/clientes.php — Leads y clientes para bot-casa multi-usuario.
 */
declare(strict_types=1);

define('WASAPBOT_ROOT', dirname(__DIR__, 2));
$isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
session_set_cookie_params(["lifetime"=>0,"path"=>"/","secure"=>$isHttps,"httponly"=>true,"samesite"=>"Lax"]);
$isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
session_set_cookie_params(["lifetime"=>0,"path"=>"/","secure"=>$isHttps,"httponly"=>true,"samesite"=>"Lax"]);
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? 'list');
$userId = (int) ($_SESSION['user_id'] ?? 0);
if (($_SESSION['role']??'') === 'admin' && !empty($_SESSION['suplantar_user_id'])) {
    $userId = (int) $_SESSION['suplantar_user_id'];
}

$leadsFile = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'leads.ndjson');
$memoryFile = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'session_memory.ndjson');

function requireValidCsrf(): void {
    $secretFile = WASAPBOT_ROOT . '/data/.csrf_secret';
    $secret = '';
    if (file_exists($secretFile)) $secret = (string) @file_get_contents($secretFile);
    if (strlen($secret) < 32) $secret = bin2hex(random_bytes(32));
    $token = (string) ($_POST['csrf_token'] ?? '');
    $current = hash_hmac('sha256', date('Y-m-d-H') . floor((int) date('i') / 10), $secret);
    $prevSlot = max(0, floor((int) date('i') / 10) - 1);
    $prev = hash_hmac('sha256', date('Y-m-d-H') . $prevSlot, $secret);
    if ($token === '' || (!hash_equals($current, $token) && !hash_equals($prev, $token))) {
        http_response_code(403); echo json_encode(['ok'=>false,'error'=>'CSRF invalid']); exit;
    }
}

function readNdjson(string $path): array {
    if (!file_exists($path)) return [];
    $lines = @file($path, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    if (!$lines) return [];
    $recs = [];
    foreach ($lines as $l) { $r = json_decode($l, true); if (is_array($r)) $recs[] = $r; }
    return $recs;
}

header('Content-Type: application/json; charset=utf-8');

try {
    switch ($action) {
        case 'list':
            $leads = readNdjson($leadsFile);
            $leads = array_reverse($leads);
            // Enrich with "arrived" status
            $display = [];
            foreach ($leads as $lead) {
                $ts  = (string) ($lead['ts'] ?? '');
                $dt  = '';
                if ($ts) { try { $d = new DateTimeImmutable($ts); $dt = $d->format('d/m/Y H:i'); } catch(\Exception){ $dt=$ts; } }
                $display[] = [
                    'ts' => $dt,
                    'phone' => (string) ($lead['phone'] ?? ''),
                    'line_label' => (string) ($lead['line_label'] ?? ''),
                    'eta_minutes' => (int) ($lead['eta_minutes'] ?? 0),
                    'confidence' => isset($lead['lead_confidence']) ? round((float)$lead['lead_confidence']*100).'%' : '—',
                    'thread_id' => (string) ($lead['thread_id'] ?? ''),
                    'arrived' => !empty($lead['arrived']),
                ];
            }
            echo json_encode(['ok' => true, 'leads' => $display, 'total' => count($display)]);
            break;

        case 'mark_arrived':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            requireValidCsrf();
            $threadId = trim((string) ($_POST['thread_id'] ?? ''));
            $arrived  = isset($_POST['arrived']) && $_POST['arrived'] !== 'false' && $_POST['arrived'] !== '0';
            if ($threadId === '') { echo json_encode(['ok'=>false,'error'=>'thread_id required']); break; }

            $leads = readNdjson($leadsFile);
            $found = false;
            foreach ($leads as &$lead) {
                if (((string) ($lead['thread_id'] ?? '')) === $threadId) {
                    $lead['arrived'] = $arrived;
                    $found = true;
                    break;
                }
            }
            unset($lead);

            if ($found) {
                $tmpFile = $leadsFile . '.tmp';
                $fp = @fopen($tmpFile, 'wb');
                if ($fp) {
                    flock($fp, LOCK_EX);
                    foreach ($leads as $lead) {
                        fwrite($fp, json_encode($lead, JSON_UNESCAPED_UNICODE) . "\n");
                    }
                    fflush($fp);
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    @rename($tmpFile, $leadsFile);
                }
                echo json_encode(['ok' => true, 'arrived' => $arrived]);
            } else {
                echo json_encode(['ok' => false, 'error' => 'Lead not found']);
            }
            break;

        case 'telegram_guide':
            echo json_encode(['ok' => true, 'guide' => [
                'title' => 'Configurar avisos por Telegram',
                'steps' => [
                    '1. Abre Telegram y busca @BotFather',
                    '2. Crea un bot con /newbot y copia el token',
                    '3. Busca @userinfobot para obtener tu Chat ID',
                    '4. Pega el Chat ID en el campo de abajo',
                ],
            ]]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (\Throwable $e) {
    error_log('[clientes API] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
