<?php
/**
 * api/stats.php — Estadísticas para bot-casa multi-usuario.
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

$userId = (int) ($_SESSION['user_id'] ?? 0);
if (($_SESSION['role']??'') === 'admin' && !empty($_SESSION['suplantar_user_id'])) {
    $userId = (int) $_SESSION['suplantar_user_id'];
}

$girlsFile  = WASAPBOT_ROOT . '/data/users/' . $userId . '/girls.json';

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
    $leadsFile  = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'leads.ndjson');
    $memoryFile = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'session_memory.ndjson');
    $todayStr = (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->format('Y-m-d');
    $weekAgo  = (new DateTimeImmutable('-7 days'))->format('Y-m-d');

    // Leads (return empty if file doesn't exist)
    $leads = file_exists($leadsFile) ? readNdjson($leadsFile) : [];
    $leadsTotal = count($leads);
    $leadsToday = 0;
    $leadsWeek  = 0;
    $leadsArrived = 0;
    foreach ($leads as $l) {
        $ts = (string) ($l['ts'] ?? '');
        if (str_starts_with($ts, $todayStr)) $leadsToday++;
        if ($ts >= $weekAgo) $leadsWeek++;
        if (!empty($l['arrived'])) $leadsArrived++;
    }

    // Conversations (return empty if file doesn't exist)
    $records = file_exists($memoryFile) ? readNdjson($memoryFile) : [];
    $allThreads = []; $todayThreads = []; $weekThreads = [];
    foreach ($records as $r) {
        $tid = (string) ($r['thread_id'] ?? '');
        if ($tid === '') continue;
        $allThreads[$tid] = true;
        $ts = (string) ($r['ts'] ?? '');
        if (str_starts_with($ts, $todayStr)) $todayThreads[$tid] = true;
        if ($ts >= $weekAgo) $weekThreads[$tid] = true;
    }

    // Lines count
    $linesMapPath = WASAPBOT_ROOT . '/data/lines_map.json';
    $linesCount = 0;
    if (file_exists($linesMapPath)) {
        $map = @json_decode((string)@file_get_contents($linesMapPath), true);
        if (is_array($map)) {
            foreach ($map as $uid) { if ((int)$uid === $userId) $linesCount++; }
        }
    }

    // Girls count
    $girlsActive = 0;
    if (file_exists($girlsFile)) {
        $girlsData = @json_decode((string)@file_get_contents($girlsFile), true);
        if (is_array($girlsData)) {
            $girlsActive = count(array_filter($girlsData['girls']??[], fn($g)=>!empty($g['activa'])));
        }
    }

    // Daily graph data (last 7 days)
    $dailyGraph = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = (new DateTimeImmutable("-{$i} days"))->format('Y-m-d');
        $label = (new DateTimeImmutable("-{$i} days"))->format('d/m');
        $c = 0; $l = 0;
        foreach ($records as $r) {
            if (str_starts_with((string)($r['ts']??''), $d)) $c++;
        }
        foreach ($leads as $ld) {
            if (str_starts_with((string)($ld['ts']??''), $d)) $l++;
        }
        $dailyGraph[] = ['date'=>$label, 'conversations'=>$c, 'leads'=>$l];
    }

    echo json_encode([
        'ok' => true,
        'stats' => [
            'conversations_total' => count($allThreads),
            'conversations_today' => count($todayThreads),
            'conversations_week'  => count($weekThreads),
            'leads_total'         => $leadsTotal,
            'leads_today'         => $leadsToday,
            'leads_week'          => $leadsWeek,
            'leads_arrived'       => $leadsArrived,
            'leads_pending'       => $leadsTotal - $leadsArrived,
            'arrival_rate'        => $leadsTotal > 0 ? round($leadsArrived / $leadsTotal * 100) : 0,
            'lines_active'        => $linesCount,
            'girls_active'        => $girlsActive,
            'daily_graph'         => $dailyGraph,
        ],
    ]);
} catch (\Throwable $e) {
    error_log('[stats API] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
