<?php

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('DATA_PATH', sys_get_temp_dir() . '/comercial-followup-' . uniqid('', true));

mkdir(DATA_PATH, 0775, true);
require_once APP_PATH . '/helpers.php';
require_once APP_PATH . '/db.php';
require_once APP_PATH . '/storage.php';
require_once APP_PATH . '/comercial.php';
require_once APP_PATH . '/comercial_agent.php';

function followup_assert($condition, $label) {
    fwrite($condition ? STDOUT : STDERR, ($condition ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL);
    return $condition;
}

$pass = true;

$hot = comercial_normalize_agent_decision(array(
    'state' => 'lead_hot',
    'confidence' => 0.91,
    'lead_score' => 88,
    'intent' => 'interested',
    'reason' => 'Quiere conocer el siguiente paso.',
));
$pass = followup_assert($hot['ok'] && $hot['state'] === 'lead_hot' && $hot['reply_allowed'] && !$hot['pause_bot'], 'lead_hot permite seguir respondiendo') && $pass;

$immediate = comercial_normalize_agent_decision(array(
    'state' => 'human_intervention',
    'confidence' => 0.96,
    'lead_score' => 97,
    'intent' => 'ready_to_buy',
    'reason' => 'Solo falta cerrar con la persona responsable.',
));
$pass = followup_assert($immediate['ok'] && !$immediate['reply_allowed'] && $immediate['pause_bot'] && $immediate['notification_priority'] === 'critical', 'human_intervention pausa y prioriza el aviso') && $pass;

$manual = comercial_resolve_automation_decision(array(
    'human_taken' => 1,
    'inbox_paused' => 0,
), array('state' => 'lead_hot'));
$pass = followup_assert($manual['state'] === 'human_intervention' && $manual['pause_bot'], 'la intervención manual siempre gana') && $pass;

$optOut = comercial_resolve_automation_decision(array(
    'human_taken' => 0,
    'inbox_paused' => 0,
    'automation_state' => 'opted_out',
), array('state' => 'continue'));
$pass = followup_assert($optOut['state'] === 'opted_out' && !$optOut['reply_allowed'], 'opted_out bloquea respuestas futuras') && $pass;

$highTurn = array('auto_turn_count' => 500, 'status' => 'open', 'human_taken' => 0, 'inbox_paused' => 0);
$pass = followup_assert(comercial_can_send_auto_followup($highTurn, 1), 'un contador alto no bloquea el seguimiento') && $pass;

$pass = followup_assert(comercial_automation_notification_key('thread-1', 'lead_hot', 'msg-1') !== comercial_automation_notification_key('thread-1', 'human_intervention', 'msg-1'), 'los avisos caliente e intervención tienen claves distintas') && $pass;

@rmdir(DATA_PATH);
fwrite($pass ? STDOUT : STDERR, $pass ? 'TODOS LOS TESTS OK' . PHP_EOL : 'HAY FALLOS' . PHP_EOL);
exit($pass ? 0 : 1);
