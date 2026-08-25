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
$isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
         || ((int) ($_SERVER['SERVER_PORT'] ?? 80) === 443);
session_set_cookie_params(["lifetime"=>0,"path"=>"/","secure"=>$isHttps,"httponly"=>true,"samesite"=>"Lax"]);
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }

$userId = (int) ($_SESSION['user_id'] ?? 0);
if (($_SESSION['role']??'') === 'admin' && !empty($_SESSION['suplantar_user_id'])) {
    $userId = (int) $_SESSION['suplantar_user_id'];
}

$girlsFile  = WASAPBOT_ROOT . '/data/users/' . $userId . '/girls.json';
$modeFile   = WASAPBOT_ROOT . '/data/users/' . $userId . '/.bot_mode';
$everOnFile = WASAPBOT_ROOT . '/data/users/' . $userId . '/.bot_has_been_on';

// ── Config for setup checklist ──
$configDir = \WasapBot\Bot::resolveUserConfigDir(WASAPBOT_ROOT, $userId);
$config = new \WasapBot\Core\Config($configDir, WASAPBOT_ROOT);

// Factory-default tariffs for comparison
$distTarifas = '';
$distPath = WASAPBOT_ROOT . '/config.dist.json';
if (file_exists($distPath)) {
    $distData = @json_decode((string)@file_get_contents($distPath), true);
    if (is_array($distData)) {
        $distTarifas = (string)($distData['prompt']['sections']['tarifas'] ?? '');
    }
}

// Tariffs configured
$tarifasVal = (string)$config->get('prompt.sections.tarifas', '');
$promptConfigured = strlen($tarifasVal) > 20 && trim($tarifasVal) !== trim($distTarifas);

// Notifications configured
$hasNotifications = false;
$tgVal = $config->get('telegram.chat_ids', '');
$waVal = $config->get('telegram.whatsapp_phones', '');
if (is_array($tgVal)) $hasNotifications = count(array_filter($tgVal, fn($v) => trim((string)$v) !== '')) > 0;
elseif (is_string($tgVal) && trim($tgVal) !== '') $hasNotifications = true;
if (!$hasNotifications) {
    if (is_array($waVal)) $hasNotifications = count(array_filter($waVal, fn($v) => trim((string)$v) !== '')) > 0;
    elseif (is_string($waVal) && trim($waVal) !== '') $hasNotifications = true;
}

// Bot mode
$botMode = 'stop';
if (file_exists($modeFile)) {
    $content = trim((string)@file_get_contents($modeFile));
    if ($content === 'start' || $content === 'stop') $botMode = $content;
}

// Bot ever on
$botEverOn = file_exists($everOnFile);

function readNdjson(string $path, int $maxLines = 50000): array {
    if (!file_exists($path)) return [];
    $lines = @file($path, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES);
    if (!$lines) return [];
    $recs = [];
    $total = count($lines);
    // If the file exceeds maxLines, only read the most recent records
    // (NDJSON is append-only, so the most recent are at the end)
    $start = max(0, $total - $maxLines);
    for ($i = $start; $i < $total; $i++) {
        $r = json_decode($lines[$i], true);
        if (is_array($r)) $recs[] = $r;
    }
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

    // Daily graph data (last 7 days) — single-pass optimization
    $dateCountsConv = $dateCountsLead = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = (new DateTimeImmutable("-{$i} days"))->format('Y-m-d');
        $dateCountsConv[$d] = 0;
        $dateCountsLead[$d] = 0;
    }
    foreach ($records as $r) {
        $date = substr((string) ($r['ts'] ?? ''), 0, 10);
        if (isset($dateCountsConv[$date])) $dateCountsConv[$date]++;
    }
    foreach ($leads as $ld) {
        $date = substr((string) ($ld['ts'] ?? ''), 0, 10);
        if (isset($dateCountsLead[$date])) $dateCountsLead[$date]++;
    }
    $dailyGraph = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = (new DateTimeImmutable("-{$i} days"))->format('Y-m-d');
        $label = (new DateTimeImmutable("-{$i} days"))->format('d/m');
        $dailyGraph[] = ['date'=>$label, 'conversations'=>$dateCountsConv[$d], 'leads'=>$dateCountsLead[$d]];
    }

    // ── Demo mode: override with marketing-friendly numbers ──
    $isDemo = (($_SESSION['username'] ?? '') === 'demo');
    if ($isDemo) {
        $demoStats = [
            'conversations_total' => 847,
            'conversations_today' => 42,
            'conversations_week'  => 187,
            'leads_total'         => 312,
            'leads_today'         => 17,
            'leads_week'          => 68,
            'leads_arrived'       => 234,
            'leads_pending'       => 78,
            'arrival_rate'        => 75,
            'lines_active'        => 3,
            'girls_active'        => 6,
        ];
        // Build realistic 7-day graph with growing trend
        $demoDailyGraph = [];
        $demoDays = [10, 13, 17, 15, 20, 18, 22]; // conv per day
        $demoLeads = [3, 5, 7, 6, 8, 7, 10]; // leads per day
        for ($i = 6; $i >= 0; $i--) {
            $d = (new DateTimeImmutable("-{$i} days"))->format('d/m');
            $demoDailyGraph[] = [
                'date' => $d,
                'conversations' => $demoDays[6 - $i],
                'leads' => $demoLeads[6 - $i],
            ];
        }
        $demoStats['daily_graph'] = $demoDailyGraph;
        $demoSetup = [
            'lines_linked' => true,
            'tarifas_configured' => true,
            'girls_active_bool' => true,
            'notifications_configured' => true,
            'progress_total' => 4,
            'progress_done' => 4,
            'progress_pct' => 100,
            'bot_status' => 'start',
            'bot_ever_on' => true,
        ];
        echo json_encode(['ok' => true, 'stats' => $demoStats, 'setup' => $demoSetup]);
    } else {
    // ── Setup progress ──
    $progressDone = 0;
    if ($linesCount > 0) $progressDone++;
    if ($promptConfigured) $progressDone++;
    if ($girlsActive > 0) $progressDone++;
    if ($hasNotifications) $progressDone++;
    $progressPct = $progressDone > 0 ? (int)round($progressDone / 4 * 100) : 0;

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
        'setup' => [
            'lines_linked'            => $linesCount > 0,
            'tarifas_configured'      => $promptConfigured,
            'girls_active_bool'       => $girlsActive > 0,
            'notifications_configured'=> $hasNotifications,
            'progress_total'          => 4,
            'progress_done'           => $progressDone,
            'progress_pct'            => $progressPct,
            'bot_status'              => $botMode,
            'bot_ever_on'             => $botEverOn,
        ],
    ]);
    } // end demo else
} catch (\Throwable $e) {
    error_log('[stats API] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
