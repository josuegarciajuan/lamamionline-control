<?php

declare(strict_types=1);

$css = (string)file_get_contents(__DIR__ . '/../assets/inbox-chat.css');
$js = (string)file_get_contents(__DIR__ . '/../assets/inbox-chat.js');
$api = (string)file_get_contents(__DIR__ . '/../inbox_api.php');
$inbox = (string)file_get_contents(__DIR__ . '/../inbox.php');
$js = (string)file_get_contents(__DIR__ . '/../assets/inbox-chat.js');

$assertions = array(
    'La cabecera principal mantiene sus controles en una sola fila' => strpos($inbox, '.inbox-topbar{display:flex;align-items:center;gap:10px;padding:8px 16px;background:#075e54;border-bottom:1px solid rgba(0,0,0,.15);flex-shrink:0;min-height:50px;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none}') !== false,
    'El botón Panel/Chat aparece antes de los toggles' => strpos($inbox, 'id="inboxPanelBtn"') < strpos($inbox, 'id="inboxToggles"'),
    'El toggle de respuestas usa icono de bot y megáfono al activarse' => strpos($inbox, 'aria-hidden="true">🤖</span>') !== false && strpos($inbox, "<?= \$repOn ? '📣' : 'OFF' ?>") !== false,
    'El estado del toggle de respuestas conserva el icono tras actualizarse' => substr_count($js, "enabled ? '📣' : 'OFF'") >= 1 && strpos($js, "settings.replies_enabled ? '📣' : 'OFF'") !== false,
    'La agenda usa un icono de calendario' => strpos($inbox, 'id="inboxAgendaBtn"') !== false && strpos($inbox, '📅') !== false,
    'La cabecera de conversación distribuye sus acciones en móvil' => strpos($css, '.inbox-chat-header-actions{flex:1 1 100%;flex-wrap:wrap;justify-content:flex-end}') !== false,
    'Las acciones de tarjetas pueden envolver en móvil' => strpos($css, '.agent-card-actions{flex-wrap:wrap}') !== false,
    'Los filtros del panel se desplazan en tablet' => strpos($css, '.inbox-agent-view .agent-quick-filters{max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}') !== false,
    'La API expone el estado de salud de cada línea' => strpos($api, "'health_status'     => trim((string)((\$line['comercial_state'] ?? array())['health_status'] ?? 'unknown'))") !== false,
    'La UI renderiza el punto según health_status y no según unread' => strpos($js, "line.health_status || 'unknown'") !== false && strpos($js, "line-dot line-dot--' + healthStatus") !== false,
    'La UI conserva la etiqueta accesible del estado de línea' => strpos($js, 'statusLabel') !== false && strpos($js, 'aria-label="Estado de WhatsApp:') !== false,
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
