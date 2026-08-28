<?php

declare(strict_types=1);

$css = (string)file_get_contents(__DIR__ . '/../assets/inbox-chat.css');
$inbox = (string)file_get_contents(__DIR__ . '/../inbox.php');

$assertions = array(
    'La cabecera principal permite distribuir controles en móvil' => strpos($inbox, '.inbox-topbar{flex-wrap:wrap;row-gap:6px}') !== false,
    'La cabecera de conversación distribuye sus acciones en móvil' => strpos($css, '.inbox-chat-header-actions{flex:1 1 100%;flex-wrap:wrap;justify-content:flex-end}') !== false,
    'Las acciones de tarjetas pueden envolver en móvil' => strpos($css, '.agent-card-actions{flex-wrap:wrap}') !== false,
    'Los filtros del panel se desplazan en tablet' => strpos($css, '.inbox-agent-view .agent-quick-filters{max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}') !== false,
);

$failures = array();
foreach ($assertions as $description => $passed) {
    if (!$passed) {
        $failures[] = $description;
    }
}

if ($failures !== array()) {
    fwrite(STDERR, "FALLÓ:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "OK: contrato responsive del inbox\n");
