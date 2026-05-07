<?php
require_once __DIR__ . '/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
$results = comercial_run_tick();
echo json_encode(array('ok' => true, 'ran_at' => now_datetime(), 'results' => $results), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
