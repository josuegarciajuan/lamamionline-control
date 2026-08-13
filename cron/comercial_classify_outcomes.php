<?php
/**
 * cron/comercial_classify_outcomes.php — Clasifica conversaciones comerciales inactivas.
 *
 * Uso:
 *   php cron/comercial_classify_outcomes.php
 *   php cron/comercial_classify_outcomes.php --days=14
 *
 * Ejecutar cada 30 min vía cron.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once APP_PATH . '/comercial_learning.php';

$days = 7;
foreach ($argv ?? array() as $arg) {
    if (str_starts_with($arg, '--days=')) {
        $days = max(1, (int)substr($arg, 7));
    }
}

echo date('Y-m-d H:i:s') . " — Comercial outcome classification (last {$days} days)\n";

try {
    $newCount = comercial_classify_conversation_outcomes($days);
    echo "  New classifications: {$newCount}\n";
} catch (\Throwable $e) {
    echo "  ERROR: " . $e->getMessage() . " en " . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}

echo "Done.\n";
