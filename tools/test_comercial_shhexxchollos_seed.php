<?php

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('DATA_PATH', sys_get_temp_dir() . '/comercial-shhexxchollos-' . uniqid('', true));

mkdir(DATA_PATH, 0775, true);
require_once APP_PATH . '/helpers.php';
require_once APP_PATH . '/db.php';
require_once APP_PATH . '/storage.php';
require_once APP_PATH . '/comercial.php';
require_once APP_PATH . '/comercial_knowledge.php';
require_once APP_PATH . '/comercial_knowledge_v2.php';

function shhexxchollos_assert($condition, $label) {
    $stream = $condition ? STDOUT : STDERR;
    fwrite($stream, ($condition ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL);
    return $condition;
}

$pass = true;
$lamamiQuery = "SELECT id, telefono, updatedsamp, nombre_comercial FROM f_clientes WHERE baja = 0 ORDER BY updatedsamp DESC LIMIT 300";
$seed = comercial_default_process_seed('shhexxchollos');
$pass = shhexxchollos_assert($seed['slug'] === 'shhexxchollos', 'la semilla usa el slug esperado') && $pass;
$pass = shhexxchollos_assert($seed['nombre'] === 'Shhexxchollos', 'la semilla usa el nombre esperado') && $pass;
$pass = shhexxchollos_assert((int)$seed['enabled'] === 0, 'la semilla nace deshabilitada') && $pass;
$pass = shhexxchollos_assert((float)$seed['daily_target_percent'] === 12.0, 'la semilla usa el 12% del objetivo diario') && $pass;
$pass = shhexxchollos_assert($seed['source_type'] === 'mysql_recent', 'la semilla usa números de la misma consulta que lamami/plaza') && $pass;
$pass = shhexxchollos_assert($seed['source_mysql_query'] === $lamamiQuery, 'la semilla usa la misma consulta MySQL que lamami') && $pass;
$pass = shhexxchollos_assert($seed['source_queue_files'] === array(), 'la semilla no tiene colas') && $pass;
$pass = shhexxchollos_assert($seed['assigned_line_ids'] === comercial_guess_line_ids(array('jostal dulce', 'nuria-jostal')), 'la semilla asigna las mismas líneas que lamami/plaza') && $pass;
$pass = shhexxchollos_assert($seed['conversation_max_auto_turns'] === 5 && $seed['escalation_score_threshold'] === 78, 'la semilla usa los límites de conversación estándar') && $pass;
$pass = shhexxchollos_assert(count($seed['positive_keywords']) > 10, 'la semilla incluye palabras qualified para chollos') && $pass;

$defaults = comercial_build_default_processes();
$defaultSlugs = array_column($defaults, 'slug');
$pass = shhexxchollos_assert(in_array('shhexxchollos', $defaultSlugs, true), 'la semilla forma parte de los procesos por defecto') && $pass;
$shhexxKnowledge = comercial_knowledge_get('shhexxchollos');
$pass = shhexxchollos_assert(strpos($shhexxKnowledge['product'], 'https://shhexxchollos.com') !== false, 'la knowledge v1 incluye la web') && $pass;
$shhexxV2 = comercial_knowledge_v2_get('shhexxchollos', 'SALUDO_INICIAL');
$pass = shhexxchollos_assert(strpos(implode(' ', $shhexxV2['opening_guidance']), 'https://shhexxchollos.com') !== false, 'la knowledge v2 incluye la web en apertura') && $pass;
$pass = shhexxchollos_assert(comercial_default_process_seed('publicista')['daily_target_percent'] === 0, 'publicista no tiene prospección proactiva') && $pass;
$publiscortSeed = comercial_default_process_seed('publiscort');
$pass = shhexxchollos_assert((int)$publiscortSeed['enabled'] === 1, 'publiscort está activo') && $pass;
$pass = shhexxchollos_assert((float)$publiscortSeed['daily_target_percent'] === 25.0, 'publiscort usa el 25% del objetivo') && $pass;
$pass = shhexxchollos_assert(strpos(comercial_knowledge_get('publiscort')['pricing'], '40€') !== false, 'publiscort mantiene 40€ en knowledge v1') && $pass;
$pass = shhexxchollos_assert(strpos((string)comercial_knowledge_v2_get('publiscort', 'PRESENTACION')['pricing'], '40€') !== false, 'publiscort mantiene 40€ en knowledge v2') && $pass;
$pass = shhexxchollos_assert(comercial_default_process_seed('publicista')['daily_target_percent'] === 0, 'publicista no tiene prospección proactiva') && $pass;
$lamamiV2 = comercial_knowledge_v2_get('lamami', 'SALUDO_INICIAL');
$pass = shhexxchollos_assert(strpos(implode(' ', $lamamiV2['opening_guidance']), 'No mencionar precios') !== false, 'LaMami abre de forma progresiva y sin precios') && $pass;
$plazaV1 = comercial_knowledge_get('plaza');
$pass = shhexxchollos_assert(strpos($plazaV1['product'], '50/50') !== false && strpos($plazaV1['pricing'], '150€ y 170€') !== false, 'Plaza mantiene 50/50 y alquiler 150-170') && $pass;
$casawasapV2 = comercial_knowledge_v2_get('casawasap', 'SALUDO_INICIAL');
$pass = shhexxchollos_assert(strpos(implode(' ', $casawasapV2['opening_guidance']), 'No mencionar precio') !== false, 'CasaWasap abre sin precio ni preguntas retóricas') && $pass;
$pass = shhexxchollos_assert(comercial_default_process_seed('lamami')['ia_opener_enabled'] === 1, 'LaMami usa apertura LLM') && $pass;
$pass = shhexxchollos_assert(comercial_default_process_templates('plaza') === array(), 'no hay plantillas fijas de apertura') && $pass;

storage_write('comercial_processes.json', array(comercial_default_process_seed('lamami')));
$migrated = comercial_get_processes();
$migratedPubliscort = array_values(array_filter($migrated, function ($process) {
    return ($process['slug'] ?? '') === 'publiscort';
}));
$pass = shhexxchollos_assert(count($migratedPubliscort) === 1 && (int)$migratedPubliscort[0]['enabled'] === 1 && (float)$migratedPubliscort[0]['daily_target_percent'] === 25.0, 'la migración reactiva Publiscort al 25%') && $pass;
$matches = array_values(array_filter($migrated, function ($process) {
    return ($process['slug'] ?? '') === 'shhexxchollos';
}));
$pass = shhexxchollos_assert(count($matches) === 1, 'la migración añade el proceso una sola vez') && $pass;
if (count($matches) === 1) {
    $process = $matches[0];
    $pass = shhexxchollos_assert((int)$process['enabled'] === 0, 'la migración mantiene enabled=0') && $pass;
    $pass = shhexxchollos_assert((float)$process['daily_target_percent'] === 12.0, 'la migración mantiene el 12% del objetivo') && $pass;
    $pass = shhexxchollos_assert($process['source_mysql_query'] === $lamamiQuery, 'la migración mantiene la consulta MySQL de lamami') && $pass;
    $pass = shhexxchollos_assert($process['source_queue_files'] === array(), 'la migración no añade colas') && $pass;
    $pass = shhexxchollos_assert($process['assigned_line_ids'] === comercial_guess_line_ids(array('jostal dulce', 'nuria-jostal')), 'la migración asigna las mismas líneas que lamami/plaza') && $pass;
}

$migratedAgain = comercial_get_processes();
$matchesAgain = array_values(array_filter($migratedAgain, function ($process) {
    return ($process['slug'] ?? '') === 'shhexxchollos';
}));
$pass = shhexxchollos_assert(count($matchesAgain) === 1, 'la migración es idempotente') && $pass;

@unlink(DATA_PATH . '/comercial_processes.json');
@unlink(DATA_PATH . '/comercial_events.jsonl');
@rmdir(DATA_PATH);

fwrite($pass ? STDOUT : STDERR, $pass ? 'TODOS LOS TESTS OK' . PHP_EOL : 'HAY FALLOS' . PHP_EOL);
exit($pass ? 0 : 1);
