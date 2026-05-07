<?php
require_once __DIR__ . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "FORBIDDEN\n";
    exit;
}

$result = storage_run_maintenance_compaction(array(
    'accounts' => true,
    'campaigns' => true,
    'force' => false,
));

echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
