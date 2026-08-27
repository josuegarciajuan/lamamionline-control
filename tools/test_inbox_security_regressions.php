<?php
/**
 * Contratos de seguridad pendientes para el inbox comercial.
 *
 * Uso: php tools/test_inbox_security_regressions.php
 *
 * No escribe datos: los dos contratos de endpoint/cola se comprueban contra
 * sus puntos de extensión porque el código actual no permite inyectar un
 * almacén de cola aislado en comercial_process_manual_send_queue().
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

function inbox_security_assert(bool $condition, string $label): bool
{
    fwrite($condition ? STDOUT : STDERR, ($condition ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL);
    return $condition;
}

$pass = true;

// Una URL con comillas no es una URL HTTP segura para interpolar posteriormente
// en HTML. Debe descartarse en la normalización del webhook de WAHA.
$maliciousUrl = 'https://waha.example.test/media.jpg" onerror="globalThis.pwned=1';
$media = comercial_inbound_media_info([
    'raw' => [
        'media' => [
            'url' => $maliciousUrl,
            'mimetype' => 'image/jpeg',
        ],
    ],
]);
$pass = inbox_security_assert(
    ($media['url'] ?? null) === null,
    'WAHA rechaza una URL HTTPS con comillas/onerror antes de que pueda llegar a un atributo HTML'
) && $pass;

// La misma clave de idempotencia con otra intención no es un reintento válido.
// El helper acepta un archivo aislado, por lo que este caso no toca data/.
$queueFile = 'inbox-security-regression-' . getmypid() . '-' . uniqid('', true) . '.json';
try {
    $first = comercial_manual_send_job_enqueue('thread-A', 'mensaje A', 'duplicate-client-id', $queueFile);
    $collision = comercial_manual_send_job_enqueue('thread-B', 'mensaje B', 'duplicate-client-id', $queueFile);
    $pass = inbox_security_assert(
        empty($collision['id']) && empty($collision['ok']) && trim((string)($collision['error'] ?? '')) !== '',
        'client_message_id reutilizado por otro hilo/texto se rechaza en vez de devolver o encolar el trabajo previo'
    ) && $pass;

    // El endpoint debe validar/encolar antes de cancelar la automatización: una
    // colisión rechazada no puede marcar el hilo como intervención humana.
    $api = (string)file_get_contents(dirname(__DIR__) . '/inbox_api.php');
    $enqueueAt = strpos($api, 'comercial_manual_send_job_enqueue($threadId, $text, $clientMessageId)');
    $cancelAt = strpos($api, 'comercial_thread_request_cancel($threadId)');
    $pass = inbox_security_assert(
        $enqueueAt !== false && $cancelAt !== false && $enqueueAt < $cancelAt,
        'el endpoint encola/valida antes de cancelar la automatización del hilo'
    ) && $pass;
} finally {
    @unlink(DATA_PATH . '/' . $queueFile);
    @unlink(DATA_PATH . '/' . $queueFile . '.lock');
}

// Contrato de lease: un segundo tick debe reconocer explícitamente el estado
// processing y el vencimiento de su lease antes de seleccionar un candidato.
// Se verifica estáticamente porque el procesador actual fija su nombre de cola
// y no admite una fixture aislada para ejecutar dos ticks sin tocar data/.
$comercial = (string)file_get_contents(dirname(__DIR__) . '/app/comercial.php');
$pass = inbox_security_assert(
    preg_match("/status'\]\s*\?\?\s*''\)\s*===\s*'processing'.*?lease_expires_at/s", $comercial) === 1,
    'un trabajo processing con lease_expires_at vigente se excluye explícitamente de un segundo claim/tick'
) && $pass;

// Regresión de doble envío: el lock se libera antes de invocar al proveedor,
// por lo que otro tick evalúa la fila mientras el primer send sigue bloqueado.
// Como no hay inyección de reloj/cola en el worker, modelamos esa decisión a
// partir del presupuesto compartido de claim/recovery y de la comparación que
// habilita el reclaim. El escenario es deliberadamente el primer segundo
// después del presupuesto histórico de 120 s, con el primer envío aún activo;
// en ningún caso debe volver a ser seleccionable.
$processorStart = strpos($comercial, 'function comercial_process_manual_send_queue()');
$processorEnd = strpos($comercial, 'function comercial_register_line_attempt', $processorStart ?: 0);
$processor = $processorStart === false || $processorEnd === false
    ? ''
    : substr($comercial, $processorStart, $processorEnd - $processorStart);
$settings = comercial_get_settings();
$expectedLeaseSeconds = max(0, (int)($settings['typing_pre_max_sec'] ?? 0))
    + max(0, (int)($settings['typing_max_sec'] ?? 0))
    + max(0, (int)($settings['typing_jitter_sec'] ?? 0))
    + (4 * max(1, min(60, (int)($settings['curl_timeout_sec'] ?? 30))));
$claimLeaseSeconds = comercial_manual_send_queue_lease_seconds($settings);
$usesSharedLeaseForClaimAndRecovery = str_contains($processor, '$now + $leaseDurationSec')
    && str_contains($processor, '$legacyLeaseStartedAt + $leaseDurationSec');
$pass = inbox_security_assert(
    $claimLeaseSeconds >= $expectedLeaseSeconds && $usesSharedLeaseForClaimAndRecovery,
    'claim y recuperación legacy comparten un lease que cubre typing, tres requests WAHA y margen'
) && $pass;
$reclaimsAfterExpiry = str_contains(
    $processor,
    'if ($leaseExpiresAt !== false && $leaseExpiresAt > $now) continue;'
);
$firstClaimAt = 1_000_000;
$secondTickAt = $firstClaimAt + 121; // el envío original sigue activo aquí
$secondTickWouldReclaimActiveSend = $claimLeaseSeconds !== null
    && $reclaimsAfterExpiry
    && ($firstClaimAt + $claimLeaseSeconds) <= $secondTickAt;
$pass = inbox_security_assert(
    !$secondTickWouldReclaimActiveSend,
    'un segundo tick a claim+121 s no puede reclamar un job processing mientras su primer envío sigue activo'
) && $pass;

// ── Regresión del long-poll: agotamiento de recursos (slots) ──────────────
// El handler GET ?action=poll sostiene la conexión hasta 25 s: si cada cliente
// abre varios polls sin límite, los workers PHP-FPM se agotan. Contrato de
// seguridad:
//   1) el timeout del cliente se acota a un MÁXIMO de 25 s,
//   2) antes de esperar se adquiere un slot de poll por cliente y, si no se
//      puede adquirir (tope alcanzado), la respuesta es HTTP 429,
//   3) session_write_close() se llama ANTES de entrar en el bucle de espera
//      (nunca se retiene el lock de sesión durante un long-poll).
// Comprobación estática sobre la fuente: buscamos los literales exactos.
$apiSrc = (string)file_get_contents(dirname(__DIR__) . '/inbox_api.php');
$pollAt = strpos($apiSrc, "\$action === 'poll'");
$pollEnd = $pollAt === false ? false : strpos($apiSrc, "\$action === 'agent'", $pollAt);
$pollRegion = ($pollAt === false || $pollEnd === false)
    ? ''
    : substr($apiSrc, $pollAt, $pollEnd - $pollAt);

$pass = inbox_security_assert(
    str_contains($pollRegion, 'min(25'),
    'el handler ?action=poll acota el timeout del cliente a un máximo de 25 s (min(25))'
) && $pass;

$pass = inbox_security_assert(
    str_contains($pollRegion, 'comercial_poll_acquire_slot('),
    'el handler ?action=poll adquiere un slot de poll (comercial_poll_acquire_slot()) antes de esperar'
) && $pass;

$pass = inbox_security_assert(
    str_contains($pollRegion, 'http_response_code(429)'),
    'el handler ?action=poll responde 429 (http_response_code(429)) cuando no puede adquirir el slot'
) && $pass;

$pollCloseAt = strpos($pollRegion, 'session_write_close()');
$pollWaitAt = strpos($pollRegion, 'comercial_poll_wait_for_change(');
$pass = inbox_security_assert(
    $pollCloseAt !== false && $pollWaitAt !== false && $pollCloseAt < $pollWaitAt,
    'el handler ?action=poll cierra la sesión (session_write_close()) antes de entrar en el bucle de espera'
) && $pass;

fwrite($pass ? STDOUT : STDERR, PHP_EOL . ($pass ? 'TODOS LOS TESTS OK' : 'HAY FALLOS ESPERADOS') . PHP_EOL);
exit($pass ? 0 : 1);
