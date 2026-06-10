<?php
/**
 * api/mensajes.php — Historial de conversaciones + envío manual + pausa por conversación.
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
if (($_SESSION['role']??'') === 'admin' && !empty($_SESSION['suplantar_user_id'])) {
    $userId = (int) $_SESSION['suplantar_user_id'];
}



function requireValidCsrf(): void {
    $secretFile = WASAPBOT_ROOT . '/data/.csrf_secret';
    $secret = '';
    if (file_exists($secretFile)) $secret = (string) @file_get_contents($secretFile);
    if (strlen($secret) < 32) $secret = bin2hex(random_bytes(32));
    $token = (string) ($_POST['csrf_token'] ?? '');
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $current = hash_hmac('sha256', $userId . '|' . date('Y-m-d-H') . floor((int) date('i') / 10), $secret);
    $prevSlot = max(0, floor((int) date('i') / 10) - 1);
    $prev = hash_hmac('sha256', $userId . '|' . date('Y-m-d-H') . $prevSlot, $secret);
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

function readReadStatus(): array {
    $path = WASAPBOT_ROOT . '/data/read_status.json';
    if (!file_exists($path)) return [];
    $raw = @file_get_contents($path);
    if ($raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function saveReadStatus(array $data): void {
    $path = WASAPBOT_ROOT . '/data/read_status.json';
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Execute a WAHA POST call and return structured result.
 * Never throws — returns ['ok'=>bool, 'http_code'=>int, 'body'=>string].
 */
function wahaPost(string $url, string $headers, string $payload): array
{
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => $headers,
            'content'       => $payload,
            'timeout'       => 8,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $ctx);
    $httpCode = 0;
    if (isset($http_response_header)) {
        foreach ($http_response_header as $h) {
            if (preg_match('#^HTTP/\d+\.\d+\s+(\d+)#', $h, $m)) {
                $httpCode = (int) $m[1];
                break;
            }
        }
    }
    return [
        'ok'        => ($response !== false && $httpCode >= 200 && $httpCode < 300),
        'http_code' => $httpCode,
        'body'      => $response !== false ? $response : '',
    ];
}

header('Content-Type: application/json; charset=utf-8');

try {
    $rootCfg = new \WasapBot\Core\Config(WASAPBOT_ROOT);
    $baseMemory = (string) $rootCfg->get('files.session_memory', WASAPBOT_ROOT . '/public/data/session_memory.ndjson');
    $baseLeads  = (string) $rootCfg->get('files.leads', WASAPBOT_ROOT . '/public/data/leads.ndjson');
    if ($userId > 1) {
        $memoryFile = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, basename($baseMemory));
        $leadsFile  = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, basename($baseLeads));
    } else {
        $memoryFile = $baseMemory;
        $leadsFile  = $baseLeads;
    }
    switch ($action) {
        case 'threads':
            $search = trim((string) ($_GET['search'] ?? ''));
            $last9  = trim((string) ($_GET['last9'] ?? ''));
            $last9Prefix = ($last9 !== '') ? $last9 . '_' : '';
            $records = readNdjson($memoryFile);
            $threads = [];
            foreach ($records as $r) {
                $tid = (string) ($r['thread_id'] ?? '');
                if ($tid === '') continue;
                // Server-side filter by line last9 prefix
                if ($last9Prefix !== '' && strpos($tid, $last9Prefix) !== 0) {
                    continue;
                }
                if (!isset($threads[$tid])) {
                    $phone = (string) ($r['phone'] ?? '');
                    $senderLid = (string) ($r['sender_lid'] ?? '');
                    $threads[$tid] = [
                        'thread_id'  => $tid,
                        'phone'      => $phone,
                        'sender_lid' => $senderLid,
                        'count'      => 0,
                        'last_ts' => '',
                        'first_msg' => '',
                        'last_msg' => '',
                    ];
                }
                $threads[$tid]['count']++;
                // Propagate sender_lid from any record (not just the first)
                if (empty($threads[$tid]['sender_lid'])) {
                    $sl = (string) ($r['sender_lid'] ?? '');
                    if ($sl !== '') $threads[$tid]['sender_lid'] = $sl;
                }
                $ts = (string) ($r['ts'] ?? '');
                if ($ts > $threads[$tid]['last_ts']) {
                    $threads[$tid]['last_ts'] = $ts;
                    // Prefer user_msg (what the customer said) over bot_reply.
                    // For support staff, the customer's words are the signal;
                    // the bot's reply is predictable/automated.
                    $threads[$tid]['last_msg'] = mb_substr((string)($r['user_msg'] ?: $r['bot_reply'] ?? ''), 0, 60);
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

            // Compute unread counts — backfill unseen threads on first access
            $readStatus = readReadStatus();
            $dirtyReadStatus = false;
            $now = time();
            foreach ($threads as $tid => &$t) {
                $lastRead = $readStatus[$tid] ?? '';
                if ($lastRead === '') {
                    // Thread never seen before.
                    // If last activity is within 30 min → genuinely new thread → mark as unread.
                    // Otherwise → pre-existing legacy thread → backfill as fully read.
                    $lastTsUnix = strtotime($t['last_ts']);
                    $isRecent = ($lastTsUnix !== false && ($now - $lastTsUnix) < 1800);

                    if ($isRecent) {
                        // Genuinely new: set read marker to just before the first message
                        // so all messages in this thread appear as unread.
                        $firstTs = null;
                        foreach ($records as $r) {
                            if (((string)($r['thread_id']??'')) === $tid) {
                                $rts = (string)($r['ts']??'');
                                if ($rts !== '' && ($firstTs === null || $rts < $firstTs)) {
                                    $firstTs = $rts;
                                }
                            }
                        }
                        $firstUnix = $firstTs ? strtotime($firstTs) : $now;
                        $readStatus[$tid] = gmdate('Y-m-d\TH:i:s\Z', max(0, (int)$firstUnix - 1));
                    } else {
                        // Legacy thread: backfill as read up to the last message
                        $readStatus[$tid] = $t['last_ts'];
                    }
                    $dirtyReadStatus = true;
                    $lastRead = $readStatus[$tid];
                }

                // Count messages with ts > lastRead
                $unread = 0;
                foreach ($records as $r) {
                    if (((string)($r['thread_id']??'')) === $tid) {
                        $ts = (string)($r['ts']??'');
                        if ($ts > $lastRead) $unread++;
                    }
                }
                $t['unread'] = $unread;
            }
            unset($t);
            if ($dirtyReadStatus) saveReadStatus($readStatus);

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
                        '_pending' => (bool) ($r['_pending'] ?? false),
                        'sender_lid' => (string) ($r['sender_lid'] ?? ''),
                    ];
                }
            }

            // Deduplicate: skip _pending records that:
            // (a) have a full version (same user_msg with bot_reply) later, OR
            // (b) are duplicate _pending records (same user_msg already seen)
            $deduped = [];
            $seenPendingMsgs = [];  // track _pending user_msgs already added
            $total = count($conv);
            for ($i = 0; $i < $total; $i++) {
                $m = $conv[$i];
                $umsg = trim((string) ($m['user_msg'] ?? ''));
                $breply = trim((string) ($m['bot_reply'] ?? ''));
                $isPending = (bool) ($m['_pending'] ?? false);

                if ($isPending && $umsg !== '') {
                    // (b) skip duplicate _pending with same user_msg already emitted
                    if (isset($seenPendingMsgs[$umsg])) continue;

                    // (a) check if a full record containing this user_msg exists later
                    //     (uses mb_strpos to handle coalesced messages like "msg1 | msg2")
                    $skip = false;
                    for ($j = $i + 1; $j < $total; $j++) {
                        $nxt = $conv[$j];
                        if (mb_strpos(trim((string)($nxt['user_msg']??'')), $umsg) !== false && trim((string)($nxt['bot_reply']??'')) !== '') {
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) continue;

                    $seenPendingMsgs[$umsg] = true;
                }
                // Also skip duplicate FULL records (same user_msg + same bot_reply, same ts)
                // This handles the edge case where the pipeline writes the full record twice
                if ($umsg !== '' || $breply !== '') {
                    $key = $umsg . '|' . $breply . '|' . ((string)($m['ts'] ?? ''));
                    static $seenFull = [];
                    if (isset($seenFull[$key])) continue;
                    $seenFull[$key] = true;
                }

                $deduped[] = $m;
            }
            $conv = $deduped;

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

        case 'send':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            requireValidCsrf();

            $port   = trim((string) ($_POST['port']   ?? ''));
            $chatId = trim((string) ($_POST['chat_id'] ?? ''));
            $text   = trim((string) ($_POST['text']    ?? ''));
            if ($port === '' || $chatId === '' || $text === '') {
                echo json_encode(['ok'=>false,'error'=>'port, chat_id and text are required']);
                break;
            }

            // Build WAHA URL from config
            $cfg = new \WasapBot\Core\Config(WASAPBOT_ROOT, $userId);
            $wahaServer = (string) $cfg->get('waha.base_ip', '100.117.92.74');
            $apiKey     = (string) $cfg->get('waha.api_key', '');
            $session    = (string) $cfg->get('waha.session', 'default');
            $url = "http://{$wahaServer}:{$port}/api/sendText";

            $payload = json_encode([
                'chatId'  => $chatId,
                'text'    => $text,
                'session' => $session,
            ], JSON_UNESCAPED_UNICODE);

            $headers = "Content-Type: application/json\r\n";
            if ($apiKey !== '') {
                $headers .= "x-api-key: {$apiKey}\r\n";
            }

            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => $headers,
                    'content' => $payload,
                    'timeout' => 15,
                ],
            ]);

            $response = @file_get_contents($url, false, $ctx);
            if ($response === false) {
                echo json_encode(['ok'=>false,'error'=>'WAHA send failed', 'url' => $url]);
                break;
            }

            // Save the sent message to session_memory
            $phone = '';
            if (preg_match('/^(\d+)@/', $chatId, $m)) {
                $phone = $m[1];
            }
            // Determine thread_id from chatId phone + discover line last9 from routing
            $last9 = '';
            $cfg2 = new \WasapBot\Core\Config(WASAPBOT_ROOT, $userId);
            $routingLines = (array) $cfg2->get('routing.lines', []);
            foreach ($routingLines as $rl) {
                $rlPort = (string) ($rl['port'] ?? '');
                if ($rlPort === $port) {
                    $last9 = (string) ($rl['last9'] ?? '');
                    break;
                }
            }
            $threadId = ($last9 !== '' ? $last9 . '_' : '') . $phone;

            $record = [
                '_seq'     => 0,
                'ts'       => gmdate('Y-m-d\TH:i:s\Z'),
                'thread_id' => $threadId,
                'phone'    => $phone,
                'user_msg' => '',
                'bot_reply' => $text,
                'manual'   => true,
                'source'   => 'manual_panel',
            ];

            // Append to NDJSON
            $dir = dirname($memoryFile);
            if (!is_dir($dir)) @mkdir($dir, 0700, true);
            if (file_exists($memoryFile) && is_readable($memoryFile)) {
                $existing = @file($memoryFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $maxSeq = 0;
                if ($existing) {
                    foreach ($existing as $l) {
                        $er = json_decode($l, true);
                        if (is_array($er) && isset($er['_seq'])) {
                            $maxSeq = max($maxSeq, (int) $er['_seq']);
                        }
                    }
                }
                $record['_seq'] = $maxSeq + 1;
            } else {
                $record['_seq'] = 1;
            }
            @file_put_contents($memoryFile, json_encode($record, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

            echo json_encode(['ok' => true, 'sent' => true]);
            break;

        case 'send_manual':
            // Humanized send: sendSeen (with lid_chat_id for GOWS) → startTyping → delay → sendText → save
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            requireValidCsrf();

            $port      = trim((string) ($_POST['port']        ?? ''));
            $chatId    = trim((string) ($_POST['chat_id']     ?? ''));
            $lidChatId = trim((string) ($_POST['lid_chat_id'] ?? ''));
            $text      = trim((string) ($_POST['text']        ?? ''));
            if ($port === '' || $chatId === '' || $text === '') {
                echo json_encode(['ok'=>false,'error'=>'port, chat_id and text are required']);
                break;
            }

            // Build WAHA config
            $cfgS = new \WasapBot\Core\Config(WASAPBOT_ROOT, $userId);
            $wahaServer = (string) $cfgS->get('waha.base_ip', '100.117.92.74');
            $apiKey     = (string) $cfgS->get('waha.api_key', '');
            $session    = (string) $cfgS->get('waha.session', 'default');

            $headers = "Content-Type: application/json\r\n";
            if ($apiKey !== '') { $headers .= "x-api-key: {$apiKey}\r\n"; }

            // ── 1. Start typing indicator ─────────────────────────────
            $typingUrl    = "http://{$wahaServer}:{$port}/api/startTyping";
            $typingPayload = json_encode(['chatId' => $chatId, 'session' => $session], JSON_UNESCAPED_UNICODE);
            $typingResult = wahaPost($typingUrl, $headers, $typingPayload);

            // ── 2. Humanized typing delay ─────────────────────────────
            $charsPerSecMin = (int) $cfgS->get('human_delays.typing.chars_per_sec_min', 38);
            $charsPerSecMax = (int) $cfgS->get('human_delays.typing.chars_per_sec_max', 85);
            $startMinMs = (int) $cfgS->get('human_delays.typing.start_min_ms', 350);
            $startMaxMs = (int) $cfgS->get('human_delays.typing.start_max_ms', 1200);

            $charsPerSec = rand($charsPerSecMin, $charsPerSecMax);
            $textLen = mb_strlen($text);
            $typingSec = max($textLen / max($charsPerSec, 1), 0.8);
            $typingSec = min($typingSec, 8.0);
            $startDelayMs = rand($startMinMs, $startMaxMs);
            $totalDelayMs = $startDelayMs + (int)($typingSec * 1000);

            usleep($totalDelayMs * 1000);

            // ── 3. Send text ──────────────────────────────────────────
            $textUrl = "http://{$wahaServer}:{$port}/api/sendText";
            $textPayload = json_encode([
                'chatId'  => $chatId,
                'text'    => $text,
                'session' => $session,
            ], JSON_UNESCAPED_UNICODE);
            $sendResult = wahaPost($textUrl, $headers, $textPayload);

            if (!$sendResult['ok']) {
                echo json_encode([
                    'ok'        => false,
                    'error'     => 'WAHA send failed (HTTP ' . $sendResult['http_code'] . ')',
                    'seen_ok'   => false,
                    'typing_ok' => $typingResult['ok'],
                    'delay_ms'  => $totalDelayMs,
                ]);
                break;
            }

            // ── 4. Mark as seen (after sendText — reply itself marks as read) ──
            $seenUrl = "http://{$wahaServer}:{$port}/api/sendSeen";
            // Try LID format first if available, then regular chatId
            $seenChatIds = [];
            if ($lidChatId !== '') $seenChatIds[] = $lidChatId;
            $seenChatIds[] = $chatId;
            $seenResult = ['ok' => false, 'http_code' => 0];
            foreach ($seenChatIds as $scid) {
                $sp = json_encode(['chatId' => $scid, 'session' => $session], JSON_UNESCAPED_UNICODE);
                $seenResult = wahaPost($seenUrl, $headers, $sp);
                if ($seenResult['ok']) break;
            }

            // ── 5. Save to session_memory ─────────────────────────────
            $phone = '';
            if (preg_match('/^(\d+)@/', $chatId, $m)) { $phone = $m[1]; }
            $last9 = '';
            $cfgL = new \WasapBot\Core\Config(WASAPBOT_ROOT, $userId);
            $routingLines = (array) $cfgL->get('routing.lines', []);
            foreach ($routingLines as $rl) {
                if ((string)($rl['port'] ?? '') === $port) {
                    $last9 = (string)($rl['last9'] ?? '');
                    break;
                }
            }
            $threadId = ($last9 !== '' ? $last9 . '_' : '') . $phone;

            $record = [
                '_seq'       => 0,
                'ts'         => gmdate('Y-m-d\TH:i:s\Z'),
                'thread_id'  => $threadId,
                'phone'      => $phone,
                'user_msg'   => '',
                'bot_reply'  => $text,
                'manual'     => true,
                'source'     => 'manual_panel',
            ];

            $dir = dirname($memoryFile);
            if (!is_dir($dir)) @mkdir($dir, 0700, true);
            if (file_exists($memoryFile) && is_readable($memoryFile)) {
                $existing = @file($memoryFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $maxSeq = 0;
                if ($existing) {
                    foreach ($existing as $l) {
                        $er = json_decode($l, true);
                        if (is_array($er) && isset($er['_seq'])) { $maxSeq = max($maxSeq, (int)$er['_seq']); }
                    }
                }
                $record['_seq'] = $maxSeq + 1;
            } else {
                $record['_seq'] = 1;
            }
            @file_put_contents($memoryFile, json_encode($record, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);

            echo json_encode([
                'ok'        => true,
                'sent'      => true,
                'seen_ok'   => $seenResult['ok'],
                'typing_ok' => $typingResult['ok'],
                'delay_ms'  => $totalDelayMs,
            ]);
            break;

        case 'toggle_pause':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            requireValidCsrf();

            $threadId = trim((string) ($_POST['thread_id'] ?? ''));
            $action2  = trim((string) ($_POST['pause_action'] ?? '')); // 'pause' | 'resume'
            if ($threadId === '' || !in_array($action2, ['pause', 'resume'], true)) {
                echo json_encode(['ok'=>false,'error'=>'thread_id and pause_action (pause|resume) required']);
                break;
            }

            $pausedFile = WASAPBOT_ROOT . '/data/paused_threads.ndjson';
            $dirP = dirname($pausedFile);
            if (!is_dir($dirP)) @mkdir($dirP, 0700, true);

            if ($action2 === 'pause') {
                // Create cancel file to abort any in-flight response
                $cancelDir = WASAPBOT_ROOT . '/data/cancel';
                if (!is_dir($cancelDir)) @mkdir($cancelDir, 0700, true);
                $cancelHash = hash('sha256', $threadId);
                @file_put_contents($cancelDir . '/' . $cancelHash . '.cancel', gmdate('c'), LOCK_EX);

                // Append to paused list if not already there
                $existing = [];
                if (file_exists($pausedFile)) {
                    $lines = @file($pausedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ((array) $lines as $l) {
                        $r = json_decode($l, true);
                        if (is_array($r) && isset($r['thread_id'])) {
                            $existing[] = (string) $r['thread_id'];
                        }
                    }
                }
                if (!in_array($threadId, $existing, true)) {
                    $rec = json_encode(['thread_id' => $threadId, 'paused_at' => gmdate('c')], JSON_UNESCAPED_UNICODE);
                    @file_put_contents($pausedFile, $rec . "\n", FILE_APPEND | LOCK_EX);
                }
            } else {
                // Resume: remove from paused list
                $kept = [];
                if (file_exists($pausedFile)) {
                    $lines = @file($pausedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ((array) $lines as $l) {
                        $r = json_decode($l, true);
                        if (is_array($r) && isset($r['thread_id']) && (string) $r['thread_id'] !== $threadId) {
                            $kept[] = $l;
                        }
                    }
                }
                @file_put_contents($pausedFile, implode("\n", $kept) . (count($kept) > 0 ? "\n" : ''), LOCK_EX);

                // Clean up orphaned _pending records for this thread.
                // When the bot was paused mid-response, the _pending record
                // written by webhook.php is never followed by a full reply,
                // causing the chat-typing indicator to reappear on resume.
                $memLines = @file($memoryFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($memLines) {
                    $memKept = [];
                    $memRemoved = 0;
                    foreach ($memLines as $memLine) {
                        $memRec = json_decode($memLine, true);
                        if (is_array($memRec)
                            && ((string) ($memRec['thread_id'] ?? '')) === $threadId
                            && !empty($memRec['_pending'])
                        ) {
                            $memRemoved++;
                            continue; // drop orphaned _pending record
                        }
                        $memKept[] = $memLine;
                    }
                    if ($memRemoved > 0) {
                        @file_put_contents($memoryFile, implode("\n", $memKept) . (count($memKept) > 0 ? "\n" : ''), LOCK_EX);
                    }
                }
            }

            echo json_encode(['ok' => true, 'paused' => ($action2 === 'pause')]);
            break;

        case 'paused_list':
            // List all paused threads (for initializing UI state)
            $pausedFile = WASAPBOT_ROOT . '/data/paused_threads.ndjson';
            $paused = [];
            if (file_exists($pausedFile)) {
                $lines = @file($pausedFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ((array) $lines as $l) {
                    $r = json_decode($l, true);
                    if (is_array($r) && isset($r['thread_id'])) {
                        $paused[] = $r['thread_id'];
                    }
                }
            }
            echo json_encode(['ok' => true, 'paused' => $paused]);
            break;

        case 'read_status':
            echo json_encode(['ok' => true, 'read_status' => readReadStatus()]);
            break;

        case 'threads_summary':
            // Returns aggregated per-line: {total_convos, total_unread}
            // Optional: last9 to filter to a single line
            $filterLast9 = trim((string) ($_GET['last9'] ?? ''));
            $records = readNdjson($memoryFile);
            $readStatus = readReadStatus();

            // Build per-line aggregation
            $linesSummary = [];    // last9 => ['threads' => [thread_id], 'unread' => 0]
            $threadLastTs = [];    // thread_id => last_ts

            foreach ($records as $r) {
                $tid = (string) ($r['thread_id'] ?? '');
                if ($tid === '') continue;
                // Extract last9 from thread_id (format: last9_phone)
                $pos = strpos($tid, '_');
                $lineLast9 = ($pos !== false) ? substr($tid, 0, $pos) : '';
                if ($lineLast9 === '') continue;
                if ($filterLast9 !== '' && $lineLast9 !== $filterLast9) continue;

                if (!isset($linesSummary[$lineLast9])) {
                    $linesSummary[$lineLast9] = ['threads' => [], 'unread' => 0];
                }
                $linesSummary[$lineLast9]['threads'][$tid] = true;

                $ts = (string) ($r['ts'] ?? '');
                if ($ts > ($threadLastTs[$tid] ?? '')) {
                    $threadLastTs[$tid] = $ts;
                }
            }

            // Compute unread per thread, sum per line — backfill on first encounter
            $dirtyReadStatus = false;
            $now = time();
            foreach ($linesSummary as $lineLast9 => &$ls) {
                $lineUnread = 0;
                foreach ($ls['threads'] as $tid => $_) {
                    $lastRead = $readStatus[$tid] ?? '';
                    if ($lastRead === '') {
                        // Thread never seen before.
                        $lastTsUnix = strtotime($threadLastTs[$tid] ?? '');
                        $isRecent = ($lastTsUnix !== false && ($now - $lastTsUnix) < 1800);
                        if ($isRecent) {
                            // Genuinely new thread → count as having unread activity
                            $readStatus[$tid] = '2000-01-01T00:00:00Z';
                            $lineUnread++;
                        } else {
                            // Legacy thread → backfill as read
                            $readStatus[$tid] = $threadLastTs[$tid] ?? '';
                        }
                        $dirtyReadStatus = true;
                    } elseif (($threadLastTs[$tid] ?? '') > $lastRead) {
                        $lineUnread++;
                    }
                }
                $ls = [
                    'total_convos' => count($ls['threads']),
                    'total_unread' => $lineUnread,
                ];
            }
            unset($ls);
            if ($dirtyReadStatus) saveReadStatus($readStatus);

            echo json_encode(['ok' => true, 'summary' => $linesSummary]);
            break;

        case 'mark_read':
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            requireValidCsrf();
            $threadId = trim((string) ($_POST['thread_id'] ?? ''));
            if ($threadId === '') { echo json_encode(['ok'=>false,'error'=>'thread_id required']); break; }

            // Get last message timestamp for this thread
            $records = readNdjson($memoryFile);
            $lastTs = '';
            foreach ($records as $r) {
                if (((string)($r['thread_id']??'')) === $threadId) {
                    $ts = (string)($r['ts']??'');
                    if ($ts > $lastTs) $lastTs = $ts;
                }
            }

            $readStatus = readReadStatus();
            $readStatus[$threadId] = $lastTs;
            saveReadStatus($readStatus);

            echo json_encode(['ok' => true, 'thread_id' => $threadId, 'last_read_ts' => $lastTs]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (\Throwable $e) {
    error_log('[mensajes API] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
