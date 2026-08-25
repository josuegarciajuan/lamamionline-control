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
$isDemo = (($_SESSION['username'] ?? '') === 'demo');
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
    $now = time();
    $valid = false;
    for ($offset = 0; $offset <= 5; $offset++) {
        $t = $now - ($offset * 600);
        $expected = hash_hmac('sha256', $userId . '|' . date('Y-m-d-H', $t) . (int) floor((int) date('i', $t) / 10), $secret);
        if (hash_equals($expected, $token)) { $valid = true; break; }
    }
    if ($token === '' || !$valid) {
        http_response_code(403); echo json_encode(['ok'=>false,'error'=>'CSRF invalid']); exit;
    }
}

/**
 * Auto-pause: añade un thread a la lista de pausados si no está ya.
 * Usado cuando un humano responde desde el panel o desde WA nativo.
 */
function autoPauseThread(int $userId, string $threadId): void {
    if ($threadId === '') return;
    $pausedFile = ($userId > 1)
        ? \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'paused_threads.ndjson')
        : WASAPBOT_ROOT . '/data/paused_threads.ndjson';
    $dirP = dirname($pausedFile);
    if (!is_dir($dirP)) @mkdir($dirP, 0700, true);

    // Check if already paused
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
        $cancelDir = WASAPBOT_ROOT . '/data/cancel';
        if (!is_dir($cancelDir)) @mkdir($cancelDir, 0700, true);
        $cancelHash = hash('sha256', $threadId);
        @file_put_contents($cancelDir . '/' . $cancelHash . '.cancel', gmdate('c'), LOCK_EX);
        $rec = json_encode(['thread_id' => $threadId, 'paused_at' => gmdate('c')], JSON_UNESCAPED_UNICODE);
        @file_put_contents($pausedFile, $rec . "\n", FILE_APPEND | LOCK_EX);
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

/**
 * Returns the canonical read_status path for the current user.
 * Per-user (userId>1): data/users/{userId}/read_status.json
 * Admin/legacy (userId<=1): data/read_status.json (global)
 */
function readStatusPath(): string {
    global $userId;
    if ($userId > 1) {
        return \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'read_status.json');
    }
    return WASAPBOT_ROOT . '/data/read_status.json';
}

/**
 * Reads read_status from the canonical path.
 * If the per-user file doesn't exist yet, seeds it from the global file
 * so that future reads/writes stay on the same path (avoids race conditions).
 */
function readReadStatus(): array {
    $path = readStatusPath();

    // Direct hit: file already exists
    if (file_exists($path)) {
        $raw = @file_get_contents($path);
        if ($raw !== false) {
            $data = json_decode($raw, true);
            if (is_array($data)) return $data;
        }
        // Corrupt file → fall through to seed
    }

    // Per-user file missing → seed from global (first-time migration)
    global $userId;
    if ($userId > 1) {
        $globalPath = WASAPBOT_ROOT . '/data/read_status.json';
        if (file_exists($globalPath)) {
            $raw = @file_get_contents($globalPath);
            if ($raw !== false) {
                $data = json_decode($raw, true);
                if (is_array($data)) {
                    $dir = dirname($path);
                    if (!is_dir($dir)) @mkdir($dir, 0700, true);
                    @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
                    return $data;
                }
            }
        }
    }

    return [];
}

function saveReadStatus(array $data): void {
    $path = readStatusPath();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0700, true) && !is_dir($dir)) {
            error_log('saveReadStatus: cannot create directory ' . $dir);
            return;
        }
    }

    // Self-heal: if file exists but is owned by another user (e.g. root),
    // try to fix permissions in-place (chmod) instead of deleting.
    // NEVER destroy data — the file content is valuable.
    if (file_exists($path) && !is_writable($path)) {
        if (!@chmod($path, 0664)) {
            error_log('saveReadStatus: read_status not writable and chmod failed for ' . $path);
            return;
        }
        clearstatcache(true, $path);
    }

    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log('saveReadStatus: json_encode failed: ' . json_last_error_msg());
        return;
    }

    $written = @file_put_contents($path, $json, LOCK_EX);
    if ($written === false) {
        error_log('saveReadStatus: file_put_contents failed for ' . $path . ' (dir writable: ' . (is_writable($dir) ? 'yes' : 'no') . ')');
    }
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

/**
 * Resolve the last9 for a given WAHA port by reading the user's lines.json.
 * Falls back to root config routing.lines for admin (userId<=1) or if
 * lines.json doesn't exist. This avoids the Config(userId) bug where
 * Config's constructor ignores the second argument and always loads
 * root config, whose routing.lines doesn't contain per-user lines.
 */
function resolveLast9FromPort(int $userId, string $port): string
{
    // Per-user: read lines.json (where api/lines.php stores line data)
    if ($userId > 1) {
        $linesPath = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'lines.json');
        if (file_exists($linesPath)) {
            $lines = @json_decode((string) @file_get_contents($linesPath), true);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    if (is_array($line) && (string) ($line['port'] ?? '') === $port) {
                        return (string) ($line['last9'] ?? '');
                    }
                }
            }
        }
    }
    // Only the admin/legacy context may consult root routing.
    if ($userId > 1) return '';
    $cfg = new \WasapBot\Core\Config(WASAPBOT_ROOT);
    $routingLines = (array) $cfg->get('routing.lines', []);
    foreach ($routingLines as $rl) {
        if (is_array($rl) && (string) ($rl['port'] ?? '') === $port) {
            return (string) ($rl['last9'] ?? '');
        }
    }
    return '';
}

header('Content-Type: application/json; charset=utf-8');

// ── Demo mode: block all mutations ──
if ($isDemo && $method === 'POST') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Modo demo: solo lectura']);
    exit;
}

try {
    // Resolve session_memory and leads paths the same way webhook.php does:
    // load the user's config (which has paths resolved by Bot::bootstrap)
    // and pass them through resolveUserDataPath for environment-independent resolution.
    // This ensures chat reads from the EXACT same file the webhook writes to.
    if ($userId > 1) {
        $userConfigDir = \WasapBot\Bot::resolveUserConfigDir(WASAPBOT_ROOT, $userId);
        $userCfg = new \WasapBot\Core\Config($userConfigDir, WASAPBOT_ROOT);
        $memoryFile = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, (string) $userCfg->get('files.session_memory', 'data/session_memory.ndjson'));
        $leadsFile  = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, (string) $userCfg->get('files.leads', 'data/leads.ndjson'));
    } else {
        $rootCfg = new \WasapBot\Core\Config(WASAPBOT_ROOT);
        $memoryFile = (string) $rootCfg->get('files.session_memory', WASAPBOT_ROOT . '/public/data/session_memory.ndjson');
        $leadsFile  = (string) $rootCfg->get('files.leads', WASAPBOT_ROOT . '/public/data/leads.ndjson');
    }
    switch ($action) {
        case 'threads':
            $search = trim((string) ($_GET['search'] ?? ''));
            $last9  = trim((string) ($_GET['last9'] ?? ''));
            $last9Prefix = ($last9 !== '') ? $last9 . '_' : '';
            $records = readNdjson($memoryFile);

            // ── Pass 1 (O(n)): build per-thread aggregates in a single scan.
            // Also capture the earliest ts per thread (first_ts) so the
            // read_status backfill below does NOT need to re-scan records.
            $threads = [];
            $firstTs = []; // thread_id => earliest ts
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
                        'last_ts'    => '',
                        'first_msg'  => '',
                        'last_msg'   => '',
                        'unread'     => 0,
                    ];
                    $firstTs[$tid] = '';
                }
                $threads[$tid]['count']++;
                // Propagate sender_lid from any record (not just the first)
                if (empty($threads[$tid]['sender_lid'])) {
                    $sl = (string) ($r['sender_lid'] ?? '');
                    if ($sl !== '') $threads[$tid]['sender_lid'] = $sl;
                }
                $ts = (string) ($r['ts'] ?? '');
                if ($ts !== '') {
                    if ($firstTs[$tid] === '' || $ts < $firstTs[$tid]) {
                        $firstTs[$tid] = $ts;
                    }
                }
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

            // Filter by search (same order as before: search filter before backfill,
            // so filtered-out threads are not backfilled into read_status).
            if ($search !== '') {
                $threads = array_filter($threads, function($t) use ($search) {
                    return strpos($t['phone'], $search) !== false || strpos($t['thread_id'], $search) !== false;
                });
            }

            // ── Backfill read_status for unseen threads (O(threads), no record re-scan) ──
            $readStatus = readReadStatus();
            $dirtyReadStatus = false;
            $now = time();
            foreach ($threads as $tid => $t) {
                if (($readStatus[$tid] ?? '') !== '') continue;
                // Thread never seen before.
                // If last activity is within 30 min → genuinely new thread → mark as unread.
                // Otherwise → pre-existing legacy thread → backfill as fully read.
                $lastTsUnix = strtotime($t['last_ts']);
                $isRecent = ($lastTsUnix !== false && ($now - $lastTsUnix) < 1800);

                if ($isRecent) {
                    // Genuinely new: set read marker to just before the first message
                    // so all messages in this thread appear as unread.
                    $first = $firstTs[$tid] ?? '';
                    $firstUnix = $first !== '' ? strtotime($first) : $now;
                    $readStatus[$tid] = gmdate('Y-m-d\TH:i:s\Z', max(0, (int)$firstUnix - 1));
                } else {
                    // Legacy thread: backfill as read up to the last message
                    $readStatus[$tid] = $t['last_ts'];
                }
                $dirtyReadStatus = true;
            }
            if ($dirtyReadStatus) saveReadStatus($readStatus);

            // ── Pass 2 (O(n)): count unread in a single scan over records ──
            // ts > lastRead (after backfill) → unread. Threads filtered out by
            // search are skipped via isset() because they are absent from $threads.
            foreach ($records as $r) {
                $tid = (string) ($r['thread_id'] ?? '');
                if ($tid === '' || !isset($threads[$tid])) continue;
                $lastRead = $readStatus[$tid] ?? '';
                $ts = (string) ($r['ts'] ?? '');
                if ($ts > $lastRead) $threads[$tid]['unread']++;
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

            // Native outbound sync is deliberately restricted to marked tenant
            // lines created through the public client API. Root/admin and legacy
            // lines never reach WAHA /api/messages here.
            if ($userId > 1) {
                $lineKey = explode('_', $threadId, 2)[0] ?? '';
                $markedLine = null;
                $tenantLinesFile = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'lines.json');
                $tenantLines = file_exists($tenantLinesFile)
                    ? json_decode((string) @file_get_contents($tenantLinesFile), true)
                    : [];
                if (is_array($tenantLines)) {
                    foreach ($tenantLines as $tenantLine) {
                        if (is_array($tenantLine)
                            && (string) ($tenantLine['last9'] ?? '') === $lineKey
                            && !empty($tenantLine['capture_native_outbound'])) {
                            $markedLine = $tenantLine;
                            break;
                        }
                    }
                }
                if (is_array($markedLine)) {
                    $threadPhone = explode('_', $threadId, 2)[1] ?? '';
                    $senderLid = '';
                    foreach ($records as $record) {
                        if ((string) ($record['thread_id'] ?? '') === $threadId && (string) ($record['sender_lid'] ?? '') !== '') {
                            $senderLid = (string) $record['sender_lid'];
                            break;
                        }
                    }
                    $nativeLogger = new \WasapBot\Core\FileLogger($userCfg);
                    $nativeSync = new \WasapBot\Services\NativeOutboundSync(
                        $userCfg,
                        new \WasapBot\Core\HttpClient($nativeLogger),
                        new \WasapBot\Memory\SessionMemory($userCfg, $nativeLogger),
                        $nativeLogger,
                        $memoryFile,
                        \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'paused_threads.ndjson'),
                    );
                    $nativeSync->sync($userId, $markedLine, $threadId, $threadPhone, $senderLid);
                    $records = readNdjson($memoryFile);
                }
            }
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
                        'from_me'    => (bool) ($r['from_me'] ?? false),
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
                // Defense-in-depth: also skip incomplete records (no bot_reply, _pending=false)
                // when a complete version (with bot_reply) exists later.
                // Handles edge cases where the immediate webhook write has _pending=false
                // but a full pipeline record follows.
                if (!$isPending && $umsg !== '' && $breply === '') {
                    $skip = false;
                    for ($j = $i + 1; $j < $total; $j++) {
                        $nxt = $conv[$j];
                        if (mb_strpos(trim((string)($nxt['user_msg']??'')), $umsg) !== false
                            && trim((string)($nxt['bot_reply']??'')) !== '') {
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) continue;
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
            $cfgDir = \WasapBot\Bot::resolveUserConfigDir(WASAPBOT_ROOT, $userId);
            $cfg = new \WasapBot\Core\Config($cfgDir, WASAPBOT_ROOT);
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
            // Resolve line last9 from user's lines.json (not root config routing.lines)
            $last9 = resolveLast9FromPort($userId, $port);
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

            // Auto-pause: human replied from panel → stop bot for this conversation
            autoPauseThread($userId, $threadId);

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
            $cfgDirS = \WasapBot\Bot::resolveUserConfigDir(WASAPBOT_ROOT, $userId);
            $cfgS = new \WasapBot\Core\Config($cfgDirS, WASAPBOT_ROOT);
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
            $last9 = resolveLast9FromPort($userId, $port);
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

            // Auto-pause: human replied from panel → stop bot for this conversation
            autoPauseThread($userId, $threadId);

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

            // Per-user paused threads for isolated users, global for admin/legacy
            $pausedFile = ($userId > 1)
                ? \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'paused_threads.ndjson')
                : WASAPBOT_ROOT . '/data/paused_threads.ndjson';
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
            // List all paused threads (for initializing UI state) — per-user aware
            $pausedFile = ($userId > 1)
                ? \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'paused_threads.ndjson')
                : WASAPBOT_ROOT . '/data/paused_threads.ndjson';
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

            // Use current server time (+10s buffer) as the read marker.
            // This avoids reading the potentially huge session_memory.ndjson file,
            // eliminates the risk of empty $lastTs triggering backfill, and
            // dramatically reduces the race-condition window with concurrent
            // threads/threads_summary writes.
            // All messages with ts <= now will be considered read.
            $lastTs = gmdate('Y-m-d\TH:i:s\Z', time() + 10);

            $readStatus = readReadStatus();
            $readStatus[$threadId] = $lastTs;
            saveReadStatus($readStatus);

            echo json_encode(['ok' => true, 'thread_id' => $threadId, 'last_read_ts' => $lastTs]);
            break;

        case 'mark_all_read':
            // Mark every conversation (optionally filtered by line last9) as read.
            // last9 empty → mark ALL lines (global "marcar todo" button).
            if ($method !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST required']); break; }
            requireValidCsrf();
            $last9 = trim((string) ($_POST['last9'] ?? ''));

            // Same buffer as mark_read so messages up to "now" count as read.
            $lastTs = gmdate('Y-m-d\TH:i:s\Z', time() + 10);

            $records = readNdjson($memoryFile);
            $readStatus = readReadStatus();
            $prefix = ($last9 !== '') ? $last9 . '_' : '';
            $marked = 0;
            $seen = [];
            foreach ($records as $r) {
                $tid = (string) ($r['thread_id'] ?? '');
                if ($tid === '') continue;
                if ($prefix !== '' && strpos($tid, $prefix) !== 0) continue;
                if (isset($seen[$tid])) continue;
                $seen[$tid] = true;
                $readStatus[$tid] = $lastTs;
                $marked++;
            }
            saveReadStatus($readStatus);

            echo json_encode(['ok' => true, 'last9' => $last9, 'marked' => $marked]);
            break;

        default:
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (\Throwable $e) {
    error_log('[mensajes API] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
