<?php
/**
 * api/mensajes.php — Historial de conversaciones para bot-casa multi-usuario.
 */
declare(strict_types=1);

define('WASAPBOT_ROOT', dirname(__DIR__, 2));
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

$memoryFile = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'session_memory.ndjson');
$leadsFile  = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'leads.ndjson');

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
        case 'threads':
            $search = trim((string) ($_GET['search'] ?? ''));
            $records = readNdjson($memoryFile);
            $threads = [];
            foreach ($records as $r) {
                $tid = (string) ($r['thread_id'] ?? '');
                if ($tid === '') continue;
                if (!isset($threads[$tid])) {
                    $phone = (string) ($r['phone'] ?? '');
                    $threads[$tid] = [
                        'thread_id' => $tid,
                        'phone' => $phone,
                        'count' => 0,
                        'last_ts' => '',
                        'first_msg' => '',
                        'last_msg' => '',
                    ];
                }
                $threads[$tid]['count']++;
                $ts = (string) ($r['ts'] ?? '');
                if ($ts > $threads[$tid]['last_ts']) {
                    $threads[$tid]['last_ts'] = $ts;
                    $threads[$tid]['last_msg'] = mb_substr((string)($r['bot_reply']??$r['user_msg']??''), 0, 60);
                }
                if (!$threads[$tid]['first_msg']) {
                    $threads[$tid]['first_msg'] = mb_substr((string)($r['user_msg']??$r['bot_reply']??''), 0, 40);
                }
            }

            // Filter by search
            if ($search !== '') {
                $threads = array_filter($threads, function($t) use ($search) {
                    return strpos($t['phone'], $search) !== false || strpos($t['thread_id'], $search) !== false;
                });
            }

            // Sort by last_ts desc
            uasort($threads, fn($a,$b) => $b['last_ts'] <=> $a['last_ts']);
            $threads = array_values($threads);
            echo json_encode(['ok' => true, 'threads' => $threads, 'total' => count($threads)]);
            break;

        case 'conversation':
            $threadId = trim((string) ($_GET['thread_id'] ?? ''));
            if ($threadId === '') { echo json_encode(['ok'=>false,'error'=>'thread_id required']); break; }
            $records = readNdjson($memoryFile);
            $conv = [];
            foreach ($records as $r) {
                if (((string)($r['thread_id']??'')) === $threadId) {
                    $conv[] = [
                        'ts' => (string) ($r['ts'] ?? ''),
                        'user_msg' => (string) ($r['user_msg'] ?? ''),
                        'bot_reply' => (string) ($r['bot_reply'] ?? ''),
                        'speaker_girl' => (string) ($r['speaker_girl_name'] ?? $r['selected_girl_name'] ?? ''),
                    ];
                }
            }
            echo json_encode(['ok' => true, 'conversation' => $conv, 'count' => count($conv)]);
            break;

        case 'mark_lead':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            requireValidCsrf();
            $threadId = trim((string)($_POST['thread_id'] ?? ''));
            if ($threadId === '') { echo json_encode(['ok'=>false,'error'=>'thread_id required']); break; }

            // Append to leads.ndjson
            $record = [
                'ts' => date('c'),
                'phone' => explode('_', $threadId, 2)[1] ?? '',
                'thread_id' => $threadId,
                'line_label' => 'manual',
                'eta_minutes' => 0,
                'lead_confidence' => 0.5,
                'arrived' => false,
                'source' => 'manual_panel',
            ];
            $dir = dirname($leadsFile);
            if (!is_dir($dir)) @mkdir($dir, 0700, true);
            @file_put_contents($leadsFile, json_encode($record, JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND|LOCK_EX);
            echo json_encode(['ok' => true]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (\Throwable $e) {
    error_log('[mensajes API] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
