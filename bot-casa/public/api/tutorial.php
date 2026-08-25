<?php

declare(strict_types=1);

use WasapBot\Core\OnboardingState;

define('WASAPBOT_ROOT', dirname(__DIR__, 2));

require_once WASAPBOT_ROOT . '/src/Core/OnboardingState.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE);
    exit;
}

function tutorialCsrfValid(string $token): bool
{
    if ($token === '') return false;
    if (!empty($_SESSION['csrf_token']) && hash_equals((string) $_SESSION['csrf_token'], $token)) return true;
    $csrfKeyPath = WASAPBOT_ROOT . '/data/.csrf_secret';
    $csrfKey = is_readable($csrfKeyPath) ? trim((string) @file_get_contents($csrfKeyPath)) : '';
    if (strlen($csrfKey) < 32) return false;

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $now = time();
    for ($offset = 0; $offset <= 5; $offset++) {
        $slot = $now - ($offset * 600);
        $expected = hash_hmac(
            'sha256',
            $userId . '|' . date('Y-m-d-H', $slot) . (int) floor((int) date('i', $slot) / 10),
            $csrfKey
        );
        if (hash_equals($expected, $token)) return true;
    }
    return false;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);
if (($_SESSION['role'] ?? '') === 'admin' && !empty($_SESSION['suplantar_user_id'])) {
    $userId = (int) $_SESSION['suplantar_user_id'];
}

$state = new OnboardingState(WASAPBOT_ROOT, $userId);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string) ($_GET['action'] ?? 'status');

if ($method === 'GET' && $action === 'status') {
    echo json_encode(['ok' => true, 'state' => $state->read()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($method !== 'POST' || !in_array($action, ['start', 'step', 'pause', 'complete', 'restart', 'skip'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid action'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!tutorialCsrfValid((string) ($_POST['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token'], JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($action) {
    case 'start':
        $state->start();
        break;
    case 'step':
        $step = filter_var($_POST['step'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => OnboardingState::STEP_COUNT - 1]]);
        if ($step === false) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Invalid step'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $state->step((int) $step);
        break;
    case 'pause':
        $state->pause();
        break;
    case 'complete':
        $state->markCompleted();
        break;
    case 'restart':
        $state->restart();
        break;
    case 'skip':
        $state->markSkipped();
        break;
}

echo json_encode(['ok' => true, 'state' => $state->read()], JSON_UNESCAPED_UNICODE);
