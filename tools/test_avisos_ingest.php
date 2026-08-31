<?php

declare(strict_types=1);

/**
 * Tests + smoke del endpoint api/avisos_ingest.php.
 * El smoke se ejecuta en AVISOS_INGEST_TEST_MODE: valida el pipeline HMAC/nonce
 * pero NO crea aviso ni envía WhatsApp. Ejecutar: php tools/test_avisos_ingest.php
 */

require_once __DIR__ . '/../app/avisos_ingest.php';

function avisos_ingest_test_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    fwrite(STDOUT, "OK: {$message}\n");
}

function avisos_ingest_smoke_request(string $endpointPath, string $body, array $env): array
{
    $childEnv = array();
    foreach (getenv() as $key => $value) {
        $childEnv[$key] = $value;
    }
    foreach ($env as $key => $value) {
        $childEnv[$key] = $value;
    }

    $cmd = 'php ' . escapeshellarg($endpointPath);
    $descriptorSpec = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    $proc = proc_open($cmd, $descriptorSpec, $pipes, null, $childEnv);
    if (!is_resource($proc)) {
        throw new RuntimeException('No se pudo lanzar el endpoint para el smoke');
    }
    fwrite($pipes[0], $body);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($proc);
    $payload = json_decode((string)$stdout, true);
    if ($stderr !== '') {
        fwrite(STDOUT, "STDERR smoke: " . trim($stderr) . "\n");
    }
    return array('exit' => $exitCode, 'json' => is_array($payload) ? $payload : array(), 'raw' => (string)$stdout);
}

$configFile = sys_get_temp_dir() . '/avisos_ingest_config_' . bin2hex(random_bytes(4)) . '.php';
file_put_contents($configFile, "<?php return array('secret' => 'unit-secret', 'max_skew' => 123);");
$localConfig = avisos_ingest_load_local_config($configFile, 'AVISOS_INGEST_TEST_CONFIG');
avisos_ingest_test_assert($localConfig['secret'] === 'unit-secret' && $localConfig['max_skew'] === 123, 'carga configuración local del endpoint');
@unlink($configFile);

$payload = array(
    'title' => 'Aviso de prueba',
    'message' => 'Mensaje de prueba desde el smoke',
    'severity' => 'baja',
    'meta' => array('origin' => 'test'),
);
$body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$signature = avisos_ingest_signature('1700000000', 'nonce-smoke', $body, 'test-secret');
avisos_ingest_test_assert($signature === hash_hmac('sha256', "1700000000\nnonce-smoke\n" . $body, 'test-secret'), 'firma HMAC canónica');

$validated = avisos_ingest_validate_payload($payload);
avisos_ingest_test_assert($validated['title'] === 'Aviso de prueba' && $validated['message'] !== '', 'valida title y message');
avisos_ingest_test_assert($validated['severity'] === 'baja', 'severity baja aceptada');
avisos_ingest_test_assert($validated['engine'] === 'manual', 'engine por defecto manual');
avisos_ingest_test_assert($validated['source_key'] === '', 'source_key vacío permitido');

$media = avisos_ingest_validate_payload(array('title' => 'T', 'message' => 'M', 'severity' => 'critica'));
avisos_ingest_test_assert($media['severity'] === 'media', 'severity inválida cae a media');

$caught = null;
try {
    avisos_ingest_validate_payload(array('message' => 'sin title'));
} catch (InvalidArgumentException $e) {
    $caught = $e->getMessage();
}
avisos_ingest_test_assert($caught !== null && strpos($caught, 'title') !== false, 'rechaza payload sin title');

$caught = null;
try {
    avisos_ingest_validate_payload(array('title' => 'T', 'message' => 'M', 'meta' => 'no-array'));
} catch (InvalidArgumentException $e) {
    $caught = $e->getMessage();
}
avisos_ingest_test_assert($caught !== null && strpos($caught, 'meta') !== false, 'rechaza meta no objeto');

$baseDir = sys_get_temp_dir() . '/avisos_ingest_test_' . bin2hex(random_bytes(5));
avisos_ingest_test_assert(avisos_ingest_register_nonce($baseDir, 'nonce-replay-test', time(), 300) === true, 'nonce nuevo aceptado');
avisos_ingest_test_assert(avisos_ingest_register_nonce($baseDir, 'nonce-replay-test', time(), 300) === false, 'nonce repetido rechazado');

$endpoint = __DIR__ . '/../api/avisos_ingest.php';
$now = (string)time();
$nonce = 'smoke-' . bin2hex(random_bytes(8));
$sig = avisos_ingest_signature($now, $nonce, $body, 'test-secret');
$baseEnv = array(
    'AVISOS_INGEST_HMAC_SECRET' => 'test-secret',
    'AVISOS_INGEST_TEST_MODE' => '1',
    'AVISOS_INGEST_RUNTIME_DIR' => $baseDir . '/runtime',
    'HTTP_X_AVISOS_TIMESTAMP' => $now,
    'HTTP_X_AVISOS_NONCE' => $nonce,
    'HTTP_X_AVISOS_SIGNATURE' => 'sha256=' . $sig,
);

$smoke = avisos_ingest_smoke_request($endpoint, $body, $baseEnv);
avisos_ingest_test_assert($smoke['exit'] === 0 && ($smoke['json']['ok'] ?? false) === true, 'smoke firma correcta responde 202 ok');
avisos_ingest_test_assert(($smoke['json']['test_mode'] ?? false) === true, 'smoke se ejecuta en modo test sin enviar WhatsApp');
avisos_ingest_test_assert(($smoke['json']['severity'] ?? '') === 'baja', 'smoke refleja severity validada');

$nonce2 = 'smoke-' . bin2hex(random_bytes(8));
$badSig = avisos_ingest_signature($now, $nonce2, $body, 'wrong-secret');
$smokeBad = avisos_ingest_smoke_request($endpoint, $body, array(
    'AVISOS_INGEST_HMAC_SECRET' => 'test-secret',
    'AVISOS_INGEST_TEST_MODE' => '1',
    'AVISOS_INGEST_RUNTIME_DIR' => $baseDir . '/runtime',
    'HTTP_X_AVISOS_TIMESTAMP' => $now,
    'HTTP_X_AVISOS_NONCE' => $nonce2,
    'HTTP_X_AVISOS_SIGNATURE' => 'sha256=' . $badSig,
));
avisos_ingest_test_assert(($smokeBad['json']['ok'] ?? true) === false && ($smokeBad['json']['error'] ?? '') !== '', 'smoke firma incorrecta rechazada');

$smokeNoKey = avisos_ingest_smoke_request($endpoint, $body, array(
    'AVISOS_INGEST_CONFIG' => sys_get_temp_dir() . '/avisos_ingest_nonexistent_' . bin2hex(random_bytes(4)) . '.php',
));
avisos_ingest_test_assert(($smokeNoKey['json']['ok'] ?? true) === false && ($smokeNoKey['json']['error'] ?? '') !== '', 'sin secreto configurado responde 503');

echo "avisos ingest tests passed\n";
