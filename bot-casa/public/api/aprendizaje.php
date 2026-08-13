<?php
/**
 * api/aprendizaje.php — Estadísticas de aprendizaje, playbook y outcomes
 * clasificados para el panel cliente de bot-casa.
 *
 * Acciones:
 *   GET  ?action=stats            → Conteos por outcome + estado del playbook (per-user)
 *   GET  ?action=playbook         → Contenido del playbook del usuario
 *   GET  ?action=outcomes         → Últimas 100 conversaciones clasificadas (per-user)
 *   POST ?action=confirm_outcome  → Confirmar outcome (body: {thread_id, outcome})
 *
 * Scoping per-user:
 *   conversation_outcomes.ndjson es global (sin user_id). Se filtra cruzando
 *   thread_id con los del session_memory.ndjson del usuario.
 */
declare(strict_types=1);

define('WASAPBOT_ROOT', dirname(__DIR__, 2));

spl_autoload_register(function (string $class): void {
    $prefix = 'WasapBot\\';
    $prefixLen = strlen($prefix);
    if (strncmp($prefix, $class, $prefixLen) !== 0) return;
    $relativeClass = substr($class, $prefixLen);
    $file = WASAPBOT_ROOT . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require_once $file;
});

$isHttps = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off");
session_set_cookie_params(["lifetime"=>0,"path"=>"/","secure"=>$isHttps,"httponly"=>true,"samesite"=>"Lax"]);
session_start();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'Unauthorized']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
$isDemo = (($_SESSION['username'] ?? '') === 'demo');
if (($_SESSION['role']??'') === 'admin' && !empty($_SESSION['suplantar_user_id'])) {
    $userId = (int) $_SESSION['suplantar_user_id'];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

header('Content-Type: application/json; charset=utf-8');

/**
 * Lee el session_memory del usuario y devuelve un set de thread_id únicos.
 */
function getUserThreadIds(string $memoryFile): array
{
    if (!file_exists($memoryFile)) return [];
    $threadIds = [];
    $handle = @fopen($memoryFile, 'r');
    if ($handle === false) return [];
    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if ($line === '') continue;
        $rec = json_decode($line, true);
        if (!is_array($rec)) continue;
        $tid = (string) ($rec['thread_id'] ?? '');
        if ($tid !== '') $threadIds[$tid] = true;
    }
    fclose($handle);
    return $threadIds;
}

/**
 * Actualiza una línea en el ndjson de outcomes y llama a ClientProfileService.
 */
function updateOutcomeInFile(string $outcomesFile, string $threadId, string $newOutcome, int $userId): bool
{
    if (!file_exists($outcomesFile)) return false;
    $lines = @file($outcomesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return false;

    $found = false;
    $updatedRec = null;
    $updatedLines = [];
    foreach ($lines as $line) {
        $rec = json_decode(trim($line), true);
        if (is_array($rec) && ((string) ($rec['thread_id'] ?? '')) === $threadId) {
            $rec['outcome'] = $newOutcome;
            $rec['human_confirmed'] = true;
            $rec['classified_at'] = date('c');
            $updatedRec = $rec;
            $found = true;
        }
        $updatedLines[] = json_encode($rec ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    if (!$found) return false;

    @file_put_contents($outcomesFile, implode("\n", $updatedLines) . "\n", LOCK_EX);

    // Update client profile for this user
    try {
        $userConfigDir = \WasapBot\Bot::resolveUserConfigDir(WASAPBOT_ROOT, $userId);
        $config = new \WasapBot\Core\Config($userConfigDir);
        require_once WASAPBOT_ROOT . '/src/Services/ClientProfileService.php';
        $profileSvc = new \WasapBot\Services\ClientProfileService($config);
        $profileSvc->updateProfile(
            (string) ($updatedRec['phone'] ?? ''),
            $newOutcome,
            (array) ($updatedRec['tags'] ?? []),
            (string) ($updatedRec['selected_girl'] ?? '')
        );
    } catch (\Throwable $e) {
        error_log('[api/aprendizaje] ClientProfileService error: ' . $e->getMessage());
    }

    return true;
}

try {
    $memoryFile   = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'session_memory.ndjson');
    // Support per-user outcomes: check user dir first, fall back to global
    $userOutcomesFile = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'conversation_outcomes.ndjson');
    $globalOutcomesFile = WASAPBOT_ROOT . '/public/data/conversation_outcomes.ndjson';
    $outcomesFile = (file_exists($userOutcomesFile) && filesize($userOutcomesFile) > 0)
        ? $userOutcomesFile
        : $globalOutcomesFile;
    $playbookFile = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'playbook.md');

    // ── POST: confirm_outcome ─────────────────────────────────────────
    if ($method === 'POST' && $action === 'confirm_outcome') {
        if ($isDemo) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Modo demo: solo lectura']);
            exit;
        }
        $raw = (string) @file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
            exit;
        }
        $threadId   = (string) ($body['thread_id'] ?? '');
        $newOutcome = (string) ($body['outcome'] ?? '');
        if ($threadId === '' || !in_array($newOutcome, ['lead_confirmado', 'lead_ghosted', 'mareador'], true)) {
            echo json_encode(['ok' => false, 'error' => 'Missing or invalid thread_id/outcome']);
            exit;
        }
        // Verify thread belongs to this user (prevent cross-user writes)
        $userThreadIds = getUserThreadIds($memoryFile);
        if (!isset($userThreadIds[$threadId])) {
            echo json_encode(['ok' => false, 'error' => 'Thread not found for this user']);
            exit;
        }
        $updated = updateOutcomeInFile($outcomesFile, $threadId, $newOutcome, $userId);
        echo json_encode(['ok' => $updated, 'thread_id' => $threadId, 'outcome' => $newOutcome]);
        exit;
    }

    // ── GET actions ───────────────────────────────────────────────────
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    if ($action === 'stats') {
        $stats = [
            'total_classified'  => 0,
            'lead_probable'     => 0,
            'lead_confirmado'   => 0,
            'leads'             => 0,
            'lead_ghosted'      => 0,
            'mareador'          => 0,
            'hostil'            => 0,
            'muerta'            => 0,
            'pending_review'    => 0,
            'playbook_exists'   => false,
            'playbook_updated'  => null,
        ];

        $userThreadIds = getUserThreadIds($memoryFile);

        if (!empty($userThreadIds) && file_exists($outcomesFile)) {
            $handle = @fopen($outcomesFile, 'r');
            if ($handle !== false) {
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if ($line === '') continue;
                    $rec = json_decode($line, true);
                    if (!is_array($rec)) continue;
                    $tid = (string) ($rec['thread_id'] ?? '');
                    if (!isset($userThreadIds[$tid])) continue;

                    $stats['total_classified']++;
                    $outcome = (string) ($rec['outcome'] ?? '');
                    if (isset($stats[$outcome])) $stats[$outcome]++;
                    if ($outcome === 'lead_probable' && empty($rec['human_confirmed'])) {
                        $stats['pending_review']++;
                    }
                }
                fclose($handle);
            }
        }

        $stats['leads'] = $stats['lead_probable'] + $stats['lead_confirmado'];

        if (file_exists($playbookFile)) {
            $stats['playbook_exists'] = true;
            $stats['playbook_updated'] = date('d/m/Y H:i', filemtime($playbookFile));
        }

        // ── Demo mode: override with marketing-friendly learning stats ──
        if ($isDemo) {
            $demoLearningStats = [
                'total_classified' => 280,
                'lead_probable'    => 72,
                'lead_confirmado'  => 168,
                'leads'            => 240,
                'lead_ghosted'     => 28,
                'mareador'         => 8,
                'hostil'           => 4,
                'muerta'           => 0,
                'pending_review'   => 12,
                'playbook_exists'  => true,
                'playbook_updated' => '10/06/2026 18:30',
            ];
            echo json_encode(['ok' => true, 'stats' => $demoLearningStats]);
        } else {
        echo json_encode(['ok' => true, 'stats' => $stats]);
        }

    } elseif ($action === 'playbook') {
        if (file_exists($playbookFile)) {
            $content = @file_get_contents($playbookFile);
            echo json_encode([
                'ok'      => true,
                'content' => $content !== false ? $content : '',
                'updated' => date('d/m/Y H:i', filemtime($playbookFile)),
            ]);
        } else {
            echo json_encode(['ok' => true, 'content' => '', 'updated' => null]);
        }

    } elseif ($action === 'outcomes') {
        $outcomes = [];
        $userThreadIds = getUserThreadIds($memoryFile);

        if (!empty($userThreadIds) && file_exists($outcomesFile)) {
            $lines = @file($outcomesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                $lines = array_reverse($lines);
                foreach ($lines as $line) {
                    $rec = json_decode(trim($line), true);
                    if (!is_array($rec)) continue;
                    $tid = (string) ($rec['thread_id'] ?? '');
                    if (!isset($userThreadIds[$tid])) continue;

                    $outcomes[] = [
                        'thread_id'      => $tid,
                        'phone'          => (string) ($rec['phone'] ?? ''),
                        'outcome'        => (string) ($rec['outcome'] ?? 'indeterminado'),
                        'message_count'  => (int) ($rec['message_count'] ?? 0),
                        'classified_at'  => (string) ($rec['classified_at'] ?? ''),
                        'confidence'     => (int) round(((float) ($rec['confidence'] ?? 0)) * 100),
                        'human_confirmed' => !empty($rec['human_confirmed']),
                    ];

                    if (count($outcomes) >= 100) break;
                }
            }
        }

        echo json_encode(['ok' => true, 'outcomes' => $outcomes, 'total' => count($outcomes)]);

    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Unknown action. Use: stats, playbook, outcomes']);
    }
} catch (\Throwable $e) {
    error_log('[api/aprendizaje] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
