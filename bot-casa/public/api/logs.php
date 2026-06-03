<?php
/**
 * api/logs.php — Logs sanitizados para bot-casa multi-usuario.
 */
declare(strict_types=1);

define('WASAPBOT_ROOT', dirname(__DIR__, 2));
session_start();
if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }

$userId = (int) ($_SESSION['user_id'] ?? 0);
if (($_SESSION['role']??'') === 'admin' && !empty($_SESSION['suplantar_user_id'])) {
    $userId = (int) $_SESSION['suplantar_user_id'];
}

$logFile = \WasapBot\Bot::resolveUserDataPath(WASAPBOT_ROOT, $userId, 'bot.log');

header('Content-Type: application/json; charset=utf-8');

try {
    if (!file_exists($logFile) || !is_readable($logFile)) {
        echo json_encode(['ok' => true, 'log' => '(sin registros)']);
        exit;
    }

    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        echo json_encode(['ok' => true, 'log' => '(sin registros)']);
        exit;
    }

    // Take last 200 lines, sanitize sensitive data
    $lines = array_slice($lines, -200);

    // Sanitize: remove API keys, IPs, secrets
    $sanitized = [];
    foreach ($lines as $line) {
        // Redact API keys / tokens / secrets / passwords
        $line = preg_replace('/(api_key|api-key|apikey|token|secret|password|passwd)[=:]\s*["\']?([^"\'\s,}]{4,})/i', '$1=***REDACTED***', $line);
        // Redact bearer tokens
        $line = preg_replace('/Bearer\s+\S+/i', 'Bearer ***', $line);
        // Redact private/RFC1918 IPv4 addresses (10.x, 172.16-31.x, 192.168.x) + CGNAT (100.64-127.x)
        $line = preg_replace('/\b(10\.\d{1,3}\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3}|100\.(6[4-9]|[7-9]\d|1[01]\d|12[0-7])\.\d{1,3}\.\d{1,3})\b/', 'x.x.x.x', $line);
        // Redact phone numbers only when preceded by label context (avoid matching timestamps/IDs)
        $line = preg_replace('/(phone|tel|mobile|wa_id|jid|from|to|sender)[=:]\s*["\']?\+?(\d{7,15})/i', '$1=***PHONE***', $line);
        $sanitized[] = $line;
    }

    echo json_encode(['ok' => true, 'log' => implode("\n", $sanitized)]);
} catch (\Throwable $e) {
    error_log('[logs API] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Internal server error']);
}
