<?php

declare(strict_types=1);

require_once __DIR__ . '/../ingest_client.php';

function client_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$message}\n");
}

$configFile = sys_get_temp_dir() . '/scraper_client_config_' . bin2hex(random_bytes(4)) . '.php';
file_put_contents($configFile, "<?php return array('endpoint' => 'http://127.0.0.1:9/local', 'secret' => 'file-secret');");
putenv('SCRAPER_INGEST_CONFIG=' . $configFile);
putenv('SCRAPER_INGEST_ENDPOINT=');
putenv('SCRAPER_INGEST_HMAC_SECRET=');
$fileConfig = scraper_ingest_client_config();
client_test_assert($fileConfig['endpoint'] === 'http://127.0.0.1:9/local' && $fileConfig['secret'] === 'file-secret', 'cliente carga configuración local');
@unlink($configFile);

client_test_assert(scraper_ingest_normalize_phone('+34 600 123 456') === '34600123456', 'cliente normaliza teléfono español');
client_test_assert(scraper_ingest_normalize_phone('911234567') === '', 'cliente rechaza teléfono no móvil');

putenv('SCRAPER_INGEST_ENDPOINT=http://127.0.0.1:9/never-production');
putenv('SCRAPER_INGEST_HMAC_SECRET=unit-test-secret');
$nonces = array();
$attempts = 0;
$transport = function ($endpoint, $body, $headers) use (&$attempts, &$nonces) {
    $attempts++;
    $nonce = preg_replace('/^X-Scraper-Nonce:\s*/', '', $headers[3]);
    $signature = preg_replace('/^X-Scraper-Signature:\s*/', '', $headers[4]);
    $timestamp = preg_replace('/^X-Scraper-Timestamp:\s*/', '', $headers[2]);
    $nonces[] = $nonce;
    client_test_assert(hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $body, 'unit-test-secret') === $signature, 'firma del cliente coincide');
    return $attempts === 1
        ? array('status' => 503, 'body' => '{}', 'error' => 'local retry')
        : array('status' => 202, 'body' => '{"ok":true,"accepted":1}', 'error' => '');
};

$result = scraper_ingest_client_send(array(
    'event_id' => 'client-unit-001',
    'type' => 'house',
    'items' => array(array('phone' => '600123456')),
), null, 2, $transport);
client_test_assert($result['ok'] === true && $result['attempts'] === 2, 'el cliente reintenta y acepta respuesta idempotente');
client_test_assert(count($nonces) === 2 && $nonces[0] !== $nonces[1], 'cada reintento usa nonce distinto');

$spool = sys_get_temp_dir() . '/scraper_client_spool_' . bin2hex(random_bytes(4));
$failed = scraper_ingest_client_send(array('event_id' => 'client-unit-002', 'type' => 'house', 'items' => array(array('phone' => '600123457'))), $spool, 1,
    function () { return array('status' => 503, 'body' => '{}', 'error' => 'offline'); });
client_test_assert($failed['ok'] === false && is_file($failed['spooled']), 'fallo local queda en spool');

$drain = scraper_ingest_client_drain_spool($spool, 5, function () {
    return array('status' => 202, 'body' => '{"ok":true,"accepted":1}', 'error' => '');
});
client_test_assert($drain['sent'] === 1 && $drain['pending'] === 0, 'el spool se drena al recuperar endpoint');

echo "ingest client tests passed\n";
