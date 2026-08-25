<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/scraper_ingest.php';

function scraper_ingest_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$message}\n");
}

$configFile = sys_get_temp_dir() . '/scraper_ingest_config_' . bin2hex(random_bytes(4)) . '.php';
file_put_contents($configFile, "<?php return array('secret' => 'unit-secret', 'max_skew' => 123);");
$localConfig = scraper_ingest_load_local_config($configFile, 'SCRAPER_INGEST_TEST_CONFIG');
scraper_ingest_test_assert($localConfig['secret'] === 'unit-secret' && $localConfig['max_skew'] === 123, 'carga configuración local del endpoint');
@unlink($configFile);

$baseDir = sys_get_temp_dir() . '/scraper_ingest_test_' . bin2hex(random_bytes(5));
mkdir($baseDir, 0770, true);

$payload = array(
    'event_id' => 'evt-test-001',
    'type' => 'house',
    'items' => array(
        array('phone' => '+34 600 123 456', 'name' => 'Casa test'),
    ),
);

$signature = scraper_ingest_signature('1700000000', 'nonce-test', json_encode($payload), 'test-secret');
scraper_ingest_test_assert($signature === hash_hmac('sha256', "1700000000\nnonce-test\n" . json_encode($payload), 'test-secret'), 'firma HMAC canónica');
scraper_ingest_test_assert(scraper_ingest_route_for_type('individual') === 'f_clientes', 'individual enruta a f_clientes');
scraper_ingest_test_assert(scraper_ingest_route_for_type('house') === 'casawasap', 'house enruta a casawasap');
scraper_ingest_test_assert(scraper_ingest_route_for_type('collaborator') === 'publicista', 'collaborator enruta a publicista');

scraper_ingest_test_assert(scraper_ingest_normalize_phone('+34 600 123 456') === '34600123456', 'normaliza movil español con +34');
scraper_ingest_test_assert(scraper_ingest_normalize_phone('600123456') === '34600123456', 'normaliza movil español nacional');
scraper_ingest_test_assert(scraper_ingest_normalize_phone('911234567') === '', 'rechaza fijo español');
scraper_ingest_test_assert(scraper_ingest_normalize_phone('123') === '', 'rechaza teléfono corto');
$mapped = scraper_ingest_individual_values(
    array('phone' => '34600123457', 'source' => 'destacamos', 'province' => 'Castellón', 'category' => 'casas'),
    array('telefono' => array(), 'fuente' => array(), 'provincia' => array(), 'sector' => array())
);
scraper_ingest_test_assert($mapped['fuente'] === 'destacamos' && $mapped['provincia'] === 'Castellón' && $mapped['sector'] === 'casas', 'mapea aliases fuente/provincia/sector');
scraper_ingest_test_assert(!isset($mapped['id_usuario']) && !isset($mapped['email']), 'no inventa id_usuario ni email');

$result = scraper_ingest_store_payload($payload, $baseDir, 'evt-test-001');
scraper_ingest_test_assert($result['accepted'] === 1, 'primer evento aceptado');
scraper_ingest_test_assert($result['duplicate'] === false, 'primer evento no duplicado');
scraper_ingest_test_assert(is_file($baseDir . '/queues/casawasap_1.jsonl') || is_file($baseDir . '/queues/casawasap_2.jsonl') || is_file($baseDir . '/queues/casawasap_3.jsonl'), 'se crea una cola casawasap');

$duplicate = scraper_ingest_store_payload($payload, $baseDir, 'evt-test-001');
scraper_ingest_test_assert($duplicate['duplicate'] === true, 'evento repetido es idempotente');
scraper_ingest_test_assert($duplicate['accepted'] === 0, 'evento repetido no vuelve a encolar');

$individualRows = array();
$individualWriter = function (array $item) use (&$individualRows) {
    $individualRows[] = $item;
};

$individualResult = scraper_ingest_store_payload(array(
    'event_id' => 'evt-test-002',
    'type' => 'individual',
    'items' => array(array('telefono' => '600123457', 'source' => 'destacamos', 'province' => 'Valencia', 'category' => 'particulares')),
), $baseDir, 'evt-test-002', null, $individualWriter);
scraper_ingest_test_assert($individualResult['accepted'] === 1, 'individual aceptado');
scraper_ingest_test_assert(count($individualRows) === 1, 'individual se inserta mediante escritor directo');
scraper_ingest_test_assert($individualRows[0]['phone'] === '34600123457', 'individual entrega teléfono normalizado al escritor');
scraper_ingest_test_assert($individualRows[0]['source'] === 'destacamos' && $individualRows[0]['province'] === 'Valencia' && $individualRows[0]['category'] === 'particulares', 'individual preserva metadatos');

$individualDuplicate = scraper_ingest_store_payload(array(
    'event_id' => 'evt-test-002b',
    'type' => 'individual',
    'items' => array(array('telefono' => '+34 600 123 457')),
), $baseDir, 'evt-test-002b', null, $individualWriter);
scraper_ingest_test_assert($individualDuplicate['accepted'] === 1 && count($individualRows) === 2, 'eventos distintos llegan al escritor');
scraper_ingest_test_assert(!is_file($baseDir . '/queues/f_clientes_1.jsonl') && !is_file($baseDir . '/queues/f_clientes_2.jsonl') && !is_file($baseDir . '/queues/f_clientes_3.jsonl'), 'individual no usa cola JSONL');

scraper_ingest_store_payload(array(
    'event_id' => 'evt-test-003',
    'type' => 'collaborator',
    'items' => array(array('whatsapp' => '600123458')),
), $baseDir, 'evt-test-003');
scraper_ingest_test_assert(is_file($baseDir . '/queues/publicista_1.jsonl') || is_file($baseDir . '/queues/publicista_2.jsonl') || is_file($baseDir . '/queues/publicista_3.jsonl'), 'se crea una cola publicista');

scraper_ingest_test_assert(scraper_ingest_register_nonce($baseDir, 'nonce-replay-test', time(), 300) === true, 'nonce nuevo aceptado');
scraper_ingest_test_assert(scraper_ingest_register_nonce($baseDir, 'nonce-replay-test', time(), 300) === false, 'nonce repetido rechazado');

echo "scraper ingest tests passed\n";
