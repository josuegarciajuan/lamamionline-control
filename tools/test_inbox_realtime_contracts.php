<?php
/**
 * Contratos TDD del "realtime" del inbox comercial — FASE ROJA (regresión).
 *
 * Uso: php tools/test_inbox_realtime_contracts.php
 *
 * Define el contrato de seis helpers del inbox que hoy no existen o no
 * cumplen el contrato en app/comercial.php:
 *
 *   1. comercial_inbox_revision($eventsPath = '', $threadsPath = ''): string
 *      — revisión barata derivada de mtime+tamaño de events/threads/settings/
 *        line-state. Acepta rutas de fixture opcionales para aislar el test
 *        (mismo patrón de inyección que comercial_thread_history_page).
 *   2. comercial_poll_wait_for_change($sinceRev, $timeoutSec = 25,
 *      $eventsPath = '', $threadsPath = '', $flushOutput = false): array
 *      — long-poll: ['changed' => bool, 'revision' => string]. El 5º parámetro
 *        $flushOutput permite flushear la salida antes de la espera sin alterar
 *        el comportamiento de cambio/timeout.
 *   3. comercial_manual_send_job_process(array $job, $file = ''): array
 *      — procesa un trabajo de envío manual (respeta lease/owner), envía vía
 *        comercial_send_thread_message o marca el fallo, y finaliza a
 *        sent|failed liberando el claim.
 *   4. comercial_process_manual_send_queue(int $limit = 0)
 *      — debe procesar TODOS los trabajos pendientes elegibles (0 = todos),
 *        no solo uno por tick.
 *   5. comercial_poll_acquire_slot(string $key, int $ttlSec = 25): bool
 *      — tope de long-polls concurrentes (agotamiento de recursos): un slot
 *        por key; devuelve false si el slot sigue en uso, true si es nuevo o
 *        si el mtime del fichero del slot es más viejo que el ttl (expira).
 *      El directorio de slots se deriva de DATA_PATH.
 *   6. comercial_poll_release_slot(string $key): void
 *      — libera el slot de poll para que el mismo key se pueda re-adquirir de
 *        inmediato.
 *
 * FASE ROJA: cada contrato debe fallar hoy — o porque la función no existe
 * ("Call to undefined function") o porque el comportamiento actual no cumple
 * el contrato (la cola no declara $limit y procesa un único trabajo por tick).
 *
 * Sin acceso a datos de producción ni contenido de conversaciones: los
 * fixtures de events/threads viven en un directorio temporal y los ficheros
 * de cola usan nombres anónimos con prefijo único (se eliminan al terminar,
 * igual que en test_inbox_api_contracts.php). Los envíos apuntan siempre a
 * thread_ids inexistentes, así que el send cae en el camino "hilo no
 * encontrado": sin red, sin cancelar automatizaciones ni escribir datos
 * reales.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

function inbox_realtime_assert(bool $condition, string $label): bool
{
    fwrite($condition ? STDOUT : STDERR, ($condition ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL);
    return $condition;
}

/**
 * Ejecuta un bloque de comportamiento sin matar el harness: cualquier
 * Throwable (p.ej. "Call to undefined function") se reporta como fallo con
 * la razón real. Los fallos internos ya los registra inbox_realtime_assert.
 */
function inbox_realtime_run_behavior(string $label, callable $block, bool &$pass): void
{
    try {
        $block();
    } catch (Throwable $e) {
        $pass = inbox_realtime_assert(false, $label . ' — ' . get_class($e) . ': ' . $e->getMessage()) && $pass;
    }
}

/**
 * Comprueba la firma deseada de un helper. Cada entrada esperada es
 * ['name' => ..., 'default' => ...] para parámetros opcionales o
 * ['name' => ...] para requeridos. Devuelve false si la función no existe
 * o la firma difiere de la esperada.
 */
function inbox_realtime_signature_matches(string $fn, array $expected): bool
{
    try {
        $params = (new ReflectionFunction($fn))->getParameters();
    } catch (Throwable $e) {
        return false;
    }
    $expected = array_values($expected);
    if (count($params) !== count($expected)) return false;
    foreach ($expected as $i => $spec) {
        $param = $params[$i];
        if ($param->getName() !== $spec['name']) return false;
        $expectDefault = array_key_exists('default', $spec);
        if ($param->isDefaultValueAvailable() !== $expectDefault) return false;
        if ($expectDefault && $param->getDefaultValue() !== $spec['default']) return false;
    }
    return true;
}

function inbox_realtime_fixture_event(string $ts, string $text): array
{
    return array(
        'ts' => $ts,
        'type' => 'reply_received',
        'payload' => array(
            'thread_id' => 'thread-fixture',
            'target_phone' => '000000000',
            'text' => $text,
        ),
    );
}

function inbox_realtime_fixture_thread_id(): string
{
    return 'thread-fixture-' . substr(uniqid('', true), -10);
}

/** Escribe la cola completa bajo lock (fixture aislado en DATA_PATH, nombre único). */
function inbox_realtime_seed_queue(string $queueFile, array $jobs): void
{
    storage_json_with_lock($queueFile, function () use ($queueFile, $jobs) {
        return storage_json_write_locked($queueFile, array_values($jobs));
    });
    storage_invalidate_cache($queueFile);
}

/** Lee la cola completa (las escrituras del storage son atómicas vía rename). */
function inbox_realtime_read_queue(string $queueFile): array
{
    $data = storage_json_read_direct($queueFile);
    return is_array($data) ? array_values($data) : array();
}

function inbox_realtime_find_job(array $jobs, string $jobId): ?array
{
    foreach ($jobs as $row) {
        if (is_array($row) && (string)($row['id'] ?? '') === $jobId) return $row;
    }
    return null;
}

/** Aplica un patch a un trabajo de la cola bajo lock. */
function inbox_realtime_patch_job(string $queueFile, string $jobId, array $patch): void
{
    storage_json_with_lock($queueFile, function () use ($queueFile, $jobId, $patch) {
        $stored = storage_json_read_locked_strict($queueFile);
        if (empty($stored['ok'])) return false;
        $jobs = array_values((array)($stored['data'] ?? array()));
        foreach ($jobs as $i => $job) {
            if (!is_array($job)) continue;
            if ((string)($job['id'] ?? '') === $jobId) {
                $jobs[$i] = array_merge($job, $patch);
                break;
            }
        }
        return storage_json_write_locked($queueFile, $jobs);
    });
    storage_invalidate_cache($queueFile);
}

/** Fábrica de trabajos con el mismo esquema que crea comercial_manual_send_job_enqueue(). */
function inbox_realtime_make_job(string $id, string $threadId, string $cmid, array $extra = array()): array
{
    return array_merge(array(
        'id' => $id,
        'thread_id' => $threadId,
        'text' => 'mensaje de prueba',
        'client_message_id' => $cmid,
        'actor' => 'test',
        'request_fingerprint' => 'fpr-' . $id,
        'status' => 'pending',
        'attempts' => 0,
        'available_at' => date('Y-m-d H:i:s', time() - 60),
        'created_at' => now_datetime(),
        'updated_at' => now_datetime(),
        'sent_at' => '',
        'last_error' => '',
        'claim_owner' => '',
        'lease_expires_at' => '',
    ), $extra);
}

$pass = true;
$fixtureDir = sys_get_temp_dir() . '/lamami-inbox-realtime-' . getmypid() . '-' . uniqid('', true);
if (!mkdir($fixtureDir, 0700, true) && !is_dir($fixtureDir)) {
    fwrite(STDERR, 'No se pudo crear el directorio temporal de fixtures.' . PHP_EOL);
    exit(1);
}

$eventsPath = $fixtureDir . '/comercial_events.jsonl';
$threadsPath = $fixtureDir . '/comercial_threads.json';
$queuePrefix = 'inbox-realtime-' . basename($fixtureDir);
// comercial_process_manual_send_queue() no expone parámetro de fichero (el
// contrato solo declara $limit), así que la fixture de cola de ese contrato
// usa el nombre por defecto dentro del DATA_PATH aislado del worktree y se
// elimina al terminar. Los contratos 3 y 5 sí inyectan fichero propio.
$queueFileDefault = 'comercial_manual_send_jobs.json';
$queueFiles = array($queuePrefix . '-job.json', $queuePrefix . '-double.json', $queueFileDefault);
$queueFileJob = $queueFiles[0];
$queueFileDouble = $queueFiles[1];
$fixtureThreadIds = array();
$slotKeys = array();
$revBase = '';

try {
    // ═══════════════════════════════════════════════════════════════════
    // 1. comercial_inbox_revision(): revisión barata de mtime+tamaño
    // ═══════════════════════════════════════════════════════════════════
    $pass = inbox_realtime_assert(
        function_exists('comercial_inbox_revision'),
        'existe comercial_inbox_revision() para derivar una revisión barata del estado local'
    ) && $pass;
    $pass = inbox_realtime_assert(
        inbox_realtime_signature_matches('comercial_inbox_revision', array(
            array('name' => 'eventsPath', 'default' => ''),
            array('name' => 'threadsPath', 'default' => ''),
        )),
        'comercial_inbox_revision() acepta rutas de fixture opcionales (eventsPath, threadsPath)'
    ) && $pass;

    inbox_realtime_run_behavior('comercial_inbox_revision(): revisión no vacía y estable', function () use ($eventsPath, $threadsPath, &$pass, &$revBase) {
        file_put_contents($eventsPath, json_encode(inbox_realtime_fixture_event('2026-08-27T10:00:00Z', 'primero'), JSON_UNESCAPED_SLASHES) . PHP_EOL);
        file_put_contents($threadsPath, '[]');
        $revA = comercial_inbox_revision($eventsPath, $threadsPath);
        $pass = inbox_realtime_assert(is_string($revA) && $revA !== '', 'la revisión es una cadena no vacía') && $pass;
        $revB = comercial_inbox_revision($eventsPath, $threadsPath);
        $pass = inbox_realtime_assert($revA === $revB, 'la revisión es estable si los ficheros no cambian') && $pass;
        $revBase = (string)$revA;
    }, $pass);

    inbox_realtime_run_behavior('comercial_inbox_revision(): sensible a mtime y tamaño de los fixtures', function () use ($eventsPath, $threadsPath, &$pass, &$revBase) {
        $before = $revBase !== '' ? $revBase : comercial_inbox_revision($eventsPath, $threadsPath);
        touch($eventsPath, time() + 300); // solo mtime, mismo contenido
        $afterMtime = comercial_inbox_revision($eventsPath, $threadsPath);
        $pass = inbox_realtime_assert($afterMtime !== $before, 'cambiar el mtime del events fixture altera la revisión') && $pass;

        file_put_contents($eventsPath, json_encode(inbox_realtime_fixture_event('2026-08-27T10:01:00Z', 'segundo'), JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
        $afterSize = comercial_inbox_revision($eventsPath, $threadsPath);
        $pass = inbox_realtime_assert($afterSize !== $afterMtime, 'crecer el events fixture (tamaño) altera la revisión') && $pass;

        touch($threadsPath, time() + 300);
        $afterThreads = comercial_inbox_revision($eventsPath, $threadsPath);
        $pass = inbox_realtime_assert($afterThreads !== $afterSize, 'cambiar el mtime del threads fixture altera la revisión') && $pass;
        $revBase = (string)$afterThreads;
    }, $pass);

    // ═══════════════════════════════════════════════════════════════════
    // 2. comercial_poll_wait_for_change(): long-poll del estado local
    // ═══════════════════════════════════════════════════════════════════
    $pass = inbox_realtime_assert(
        function_exists('comercial_poll_wait_for_change'),
        'existe comercial_poll_wait_for_change() para long-poll del estado local'
    ) && $pass;
    $pass = inbox_realtime_assert(
        inbox_realtime_signature_matches('comercial_poll_wait_for_change', array(
            array('name' => 'sinceRev'),
            array('name' => 'timeoutSec', 'default' => 25),
            array('name' => 'eventsPath', 'default' => ''),
            array('name' => 'threadsPath', 'default' => ''),
            array('name' => 'flushOutput', 'default' => false),
        )),
        'comercial_poll_wait_for_change() declara sinceRev, timeoutSec=25, rutas de fixture y flushOutput=false'
    ) && $pass;

    inbox_realtime_run_behavior('comercial_poll_wait_for_change(): detecta el cambio a mitad de la espera', function () use ($eventsPath, $threadsPath, &$pass, &$revBase) {
        $sinceRev = $revBase !== '' ? $revBase : comercial_inbox_revision($eventsPath, $threadsPath);
        // El cambio ocurre DESPUÉS de arrancar el poll (proceso hermano), como
        // en producción: un evento llega mientras el cliente espera. El poll
        // debe re-stat los ficheros en cada iteración para verlo. El mtime
        // se fija a now+60 explícito: un touch a secas podría caer en el mismo
        // segundo que el mtime previo (granularidad 1 s) y no alterar nada.
        exec(sprintf(
            'sleep 0.3; php -r %s %s >/dev/null 2>&1 &',
            escapeshellarg('touch($argv[1], time() + 60);'),
            escapeshellarg($eventsPath)
        ));
        $start = microtime(true);
        $res = comercial_poll_wait_for_change($sinceRev, 3, $eventsPath, $threadsPath);
        $elapsed = microtime(true) - $start;
        $pass = inbox_realtime_assert(is_array($res) && array_key_exists('changed', $res) && array_key_exists('revision', $res), 'el poll devuelve el array {changed, revision}') && $pass;
        $pass = inbox_realtime_assert(($res['changed'] ?? null) === true, 'el poll informa changed=true cuando el mtime cambia durante la espera') && $pass;
        $pass = inbox_realtime_assert(is_string($res['revision'] ?? '') && ($res['revision'] ?? '') !== $sinceRev, 'el poll devuelve la revisión nueva distinta de la previa') && $pass;
        $pass = inbox_realtime_assert($elapsed < 2.0, 'el poll responde con prontitud (antes del timeout de 3 s)') && $pass;
        $revBase = (string)($res['revision'] ?? $revBase);
    }, $pass);

    inbox_realtime_run_behavior('comercial_poll_wait_for_change(): agota el timeout sin cambios', function () use ($eventsPath, $threadsPath, &$pass, &$revBase) {
        $revCurrent = $revBase !== '' ? $revBase : comercial_inbox_revision($eventsPath, $threadsPath);
        $start = microtime(true);
        $res = comercial_poll_wait_for_change($revCurrent, 1, $eventsPath, $threadsPath);
        $elapsed = microtime(true) - $start;
        $pass = inbox_realtime_assert(($res['changed'] ?? null) === false, 'sin cambios dentro del timeout el poll informa changed=false') && $pass;
        $pass = inbox_realtime_assert((string)($res['revision'] ?? '') === $revCurrent, 'el poll devuelve la revisión actual sin cambios') && $pass;
        $pass = inbox_realtime_assert($elapsed >= 0.9 && $elapsed < 10, 'el poll espera de verdad ~1 s (timeoutSec=1) antes de devolver false') && $pass;
    }, $pass);

    inbox_realtime_run_behavior('comercial_poll_wait_for_change(): con flushOutput=true detecta el cambio a mitad de la espera', function () use ($eventsPath, $threadsPath, &$pass, &$revBase) {
        $sinceRev = $revBase !== '' ? $revBase : comercial_inbox_revision($eventsPath, $threadsPath);
        // Mismo escenario que el contrato 2: el cambio llega mientras el poll
        // espera, con el 5º parámetro flushOutput=true en CLI. La llamada debe
        // seguir devolviendo el array {changed, revision} y ver el cambio.
        exec(sprintf(
            'sleep 0.3; php -r %s %s >/dev/null 2>&1 &',
            escapeshellarg('touch($argv[1], time() + 60);'),
            escapeshellarg($eventsPath)
        ));
        $start = microtime(true);
        $res = comercial_poll_wait_for_change($sinceRev, 3, $eventsPath, $threadsPath, true);
        $elapsed = microtime(true) - $start;
        $pass = inbox_realtime_assert(is_array($res) && array_key_exists('changed', $res) && array_key_exists('revision', $res), 'con flushOutput=true el poll devuelve el array {changed, revision}') && $pass;
        $pass = inbox_realtime_assert(($res['changed'] ?? null) === true, 'con flushOutput=true el poll informa changed=true cuando el mtime cambia durante la espera') && $pass;
        $pass = inbox_realtime_assert(is_string($res['revision'] ?? '') && ($res['revision'] ?? '') !== $sinceRev, 'con flushOutput=true el poll devuelve la revisión nueva distinta de la previa') && $pass;
        $pass = inbox_realtime_assert($elapsed < 2.0, 'con flushOutput=true el poll responde con prontitud (antes del timeout de 3 s)') && $pass;
        $revBase = (string)($res['revision'] ?? $revBase);
    }, $pass);

    inbox_realtime_run_behavior('comercial_poll_wait_for_change(): con flushOutput=true agota el timeout sin cambios', function () use ($eventsPath, $threadsPath, &$pass, &$revBase) {
        $revCurrent = $revBase !== '' ? $revBase : comercial_inbox_revision($eventsPath, $threadsPath);
        $start = microtime(true);
        $res = comercial_poll_wait_for_change($revCurrent, 1, $eventsPath, $threadsPath, true);
        $elapsed = microtime(true) - $start;
        $pass = inbox_realtime_assert(($res['changed'] ?? null) === false, 'con flushOutput=true y sin cambios el poll informa changed=false') && $pass;
        $pass = inbox_realtime_assert((string)($res['revision'] ?? '') === $revCurrent, 'con flushOutput=true el poll devuelve la revisión actual sin cambios') && $pass;
        $pass = inbox_realtime_assert($elapsed >= 0.9 && $elapsed < 10, 'con flushOutput=true el poll espera de verdad ~1 s (timeoutSec=1) antes de devolver false') && $pass;
    }, $pass);

    inbox_realtime_run_behavior('comercial_poll_wait_for_change(): rutas de fixture inexistentes sin fatal', function () use ($fixtureDir, &$pass) {
        $missingEvents = $fixtureDir . '/no-existe-events.jsonl';
        $missingThreads = $fixtureDir . '/no-existe-threads.json';
        $probe = comercial_poll_wait_for_change('', 1, $missingEvents, $missingThreads);
        $pass = inbox_realtime_assert(is_array($probe) && array_key_exists('changed', $probe) && array_key_exists('revision', $probe), 'con rutas inexistentes el poll devuelve {changed, revision} sin lanzar') && $pass;
        $probe2 = comercial_poll_wait_for_change((string)($probe['revision'] ?? ''), 1, $missingEvents, $missingThreads);
        $pass = inbox_realtime_assert(is_array($probe2) && ($probe2['changed'] ?? null) === false, 'la revisión de rutas inexistentes es estable (changed=false)') && $pass;
    }, $pass);

    // ═══════════════════════════════════════════════════════════════════
    // 3. comercial_manual_send_job_process(): procesa un trabajo reclamado
    // ═══════════════════════════════════════════════════════════════════
    $pass = inbox_realtime_assert(
        function_exists('comercial_manual_send_job_process'),
        'existe comercial_manual_send_job_process() para procesar un trabajo de envío manual ya reclamado'
    ) && $pass;
    $pass = inbox_realtime_assert(
        inbox_realtime_signature_matches('comercial_manual_send_job_process', array(
            array('name' => 'job'),
            array('name' => 'file', 'default' => ''),
        )),
        'comercial_manual_send_job_process() declara job + fichero de cola opcional'
    ) && $pass;

    // 3a. Transición de finalización: trabajo reclamado EN PROPIEDAD (lease
    // vigente + owner coincidente) → se procesa, se libera el claim y el
    // hilo inexistente fuerza el camino "fallo" → estado terminal 'failed'.
    inbox_realtime_run_behavior('comercial_manual_send_job_process(): finaliza un trabajo reclamado en propiedad', function () use ($queueFileJob, &$pass, &$fixtureThreadIds) {
        $threadId = inbox_realtime_fixture_thread_id();
        $fixtureThreadIds[] = $threadId;
        $job = inbox_realtime_make_job('job-finalize-1', $threadId, 'cmid-finalize-1', array(
            'status' => 'processing',
            'claim_owner' => 'owner-a',
            'lease_expires_at' => date('Y-m-d H:i:s', time() + 120),
        ));
        inbox_realtime_seed_queue($queueFileJob, array($job));

        $result = comercial_manual_send_job_process($job, $queueFileJob);

        $stored = inbox_realtime_read_queue($queueFileJob);
        $found = inbox_realtime_find_job($stored, 'job-finalize-1');
        $pass = inbox_realtime_assert(is_array($result), 'el procesador de un trabajo devuelve un array') && $pass;
        $pass = inbox_realtime_assert(($result['job_id'] ?? '') === $job['id'], 'el resultado identifica el trabajo procesado (job_id)') && $pass;
        $pass = inbox_realtime_assert(is_array($found), 'el trabajo sigue presente en la cola') && $pass;
        if (!is_array($found)) return;
        $pass = inbox_realtime_assert((int)($found['attempts'] ?? 0) === 1, 'el trabajo reclamado en propiedad se procesa una vez (attempts=1)') && $pass;
        $pass = inbox_realtime_assert((string)($found['claim_owner'] ?? 'x') === '' && (string)($found['lease_expires_at'] ?? 'x') === '', 'el claim y el lease se liberan al finalizar') && $pass;
        $pass = inbox_realtime_assert((string)($found['status'] ?? '') === 'failed', 'sin hilo real el envío falla y el trabajo finaliza como failed') && $pass;
        $pass = inbox_realtime_assert(trim((string)($found['last_error'] ?? '')) !== '', 'el fallo deja last_error explicativo') && $pass;
    }, $pass);

    // 3b. Enforcement de lease/owner: un trabajo processing con lease vigente
    // de OTRO owner no se puede procesar: se rechaza y queda intacto.
    inbox_realtime_run_behavior('comercial_manual_send_job_process(): respeta lease/owner ajenos', function () use ($queueFileJob, &$pass) {
        $threadId = inbox_realtime_fixture_thread_id();
        $job = inbox_realtime_make_job('job-lease-2', $threadId, 'cmid-lease-2', array(
            'status' => 'processing',
            'claim_owner' => 'owner-b',
            'lease_expires_at' => date('Y-m-d H:i:s', time() + 120),
        ));
        inbox_realtime_seed_queue($queueFileJob, array($job));
        $callerClaim = array_merge($job, array('claim_owner' => 'owner-a')); // claim ajeno

        $result = comercial_manual_send_job_process($callerClaim, $queueFileJob);

        $stored = inbox_realtime_read_queue($queueFileJob);
        $found = inbox_realtime_find_job($stored, 'job-lease-2');
        $pass = inbox_realtime_assert(empty($result['ok']) && trim((string)($result['error'] ?? '')) !== '', 'un trabajo con lease vigente de otro owner se rechaza (ok=false + error)') && $pass;
        $pass = inbox_realtime_assert(is_array($found) && (string)($found['status'] ?? '') === 'processing' && (string)($found['claim_owner'] ?? '') === 'owner-b', 'el trabajo ajeno sigue en processing con su owner intacto') && $pass;
        $pass = inbox_realtime_assert(is_array($found) && (int)($found['attempts'] ?? -1) === 0 && (string)($found['lease_expires_at'] ?? '') !== '', 'el trabajo ajeno no se reclama ni se intenta (attempts=0, lease intacto)') && $pass;
    }, $pass);

    // ═══════════════════════════════════════════════════════════════════
    // 4. comercial_process_manual_send_queue(0): procesa TODA la cola
    // ═══════════════════════════════════════════════════════════════════
    $pass = inbox_realtime_assert(
        inbox_realtime_signature_matches('comercial_process_manual_send_queue', array(
            array('name' => 'limit', 'default' => 0),
        )),
        'comercial_process_manual_send_queue() declara $limit = 0 (0 = procesar toda la cola)'
    ) && $pass;

    inbox_realtime_run_behavior('comercial_process_manual_send_queue(): procesa TODOS los pendientes en un tick', function () use ($queueFileDefault, &$pass) {
        $cmids = array('cmid-all-0001', 'cmid-all-0002', 'cmid-all-0003', 'cmid-all-0004');
        $enqueued = array();
        foreach (array_slice($cmids, 0, 3) as $cmid) {
            $enqueued[] = comercial_manual_send_job_enqueue(inbox_realtime_fixture_thread_id(), 'mensaje ' . $cmid, $cmid, $queueFileDefault);
        }
        // 4º trabajo: lease ajeno vigente → no elegible, no debe tocarse.
        $protected = comercial_manual_send_job_enqueue(inbox_realtime_fixture_thread_id(), 'mensaje protegido', $cmids[3], $queueFileDefault);
        inbox_realtime_patch_job($queueFileDefault, (string)$protected['id'], array(
            'status' => 'processing',
            'claim_owner' => 'owner-otro',
            'lease_expires_at' => date('Y-m-d H:i:s', time() + 120),
        ));

        $result = comercial_process_manual_send_queue(0);

        $stored = inbox_realtime_read_queue($queueFileDefault);
        $byId = array();
        foreach ($stored as $row) {
            if (is_array($row)) $byId[(string)($row['id'] ?? '')] = $row;
        }
        $processed = true;
        $statuses = array();
        foreach ($enqueued as $job) {
            $row = $byId[(string)($job['id'] ?? '')] ?? null;
            $attempts = is_array($row) ? (int)($row['attempts'] ?? 0) : 0;
            $status = is_array($row) ? (string)($row['status'] ?? '?') : '?';
            $statuses[] = $status . ':' . $attempts;
            if ($attempts < 1) $processed = false;
        }
        $pass = inbox_realtime_assert($processed, 'los tres trabajos pendientes se procesan en un solo tick (attempts>=1 en todos) — estado: ' . implode(', ', $statuses)) && $pass;

        $terminal = true;
        foreach ($enqueued as $job) {
            $row = $byId[(string)($job['id'] ?? '')] ?? null;
            if (!is_array($row) || !in_array((string)($row['status'] ?? ''), array('sent', 'failed'), true)) $terminal = false;
        }
        $pass = inbox_realtime_assert($terminal, 'los tres trabajos finalizan en estado terminal (sent|failed)') && $pass;

        $prot = $byId[(string)($protected['id'] ?? '')] ?? null;
        $pass = inbox_realtime_assert(
            is_array($prot) && (string)($prot['status'] ?? '') === 'processing'
                && (string)($prot['claim_owner'] ?? '') === 'owner-otro'
                && (int)($prot['attempts'] ?? -1) === 0,
            'el trabajo con lease vigente de otro owner no se toca'
        ) && $pass;
    }, $pass);

    // ═══════════════════════════════════════════════════════════════════
    // 5. Sin doble envío: re-enqueue de la misma client_message_id
    // ═══════════════════════════════════════════════════════════════════
    inbox_realtime_run_behavior('reenvío por client_message_id: sin doble envío tras procesar', function () use ($queueFileDouble, &$pass) {
        $threadId = inbox_realtime_fixture_thread_id();
        $cmid = 'cmid-double-0001';
        $first = comercial_manual_send_job_enqueue($threadId, 'texto único', $cmid, $queueFileDouble);
        $jobId = (string)($first['id'] ?? '');
        $pass = inbox_realtime_assert($jobId !== '', 'el primer enqueue devuelve un trabajo con id') && $pass;

        $p1 = comercial_manual_send_job_process($first, $queueFileDouble);

        $retry = comercial_manual_send_job_enqueue($threadId, 'texto único', $cmid, $queueFileDouble);
        $pass = inbox_realtime_assert((string)($retry['id'] ?? '') === $jobId, 're-enqueue de la misma client_message_id devuelve el MISMO trabajo (idempotente)') && $pass;

        $p2 = comercial_manual_send_job_process($retry, $queueFileDouble);

        $matching = array();
        foreach (inbox_realtime_read_queue($queueFileDouble) as $row) {
            if (is_array($row) && (string)($row['client_message_id'] ?? '') === $cmid) $matching[] = $row;
        }
        $pass = inbox_realtime_assert(count($matching) === 1, 'solo existe un trabajo para esa client_message_id') && $pass;
        $pass = inbox_realtime_assert(count($matching) === 1 && (string)($matching[0]['id'] ?? '') === $jobId, 'el único trabajo es el original (mismo id)') && $pass;
        $pass = inbox_realtime_assert(count($matching) === 1 && (int)($matching[0]['attempts'] ?? -1) === 1, 'el trabajo se procesa una sola vez (attempts=1): sin doble envío') && $pass;
    }, $pass);

    // ═══════════════════════════════════════════════════════════════════
    // 6. Slots de long-poll: tope de clientes concurrentes
    //    (comercial_poll_acquire_slot / comercial_poll_release_slot)
    // ═══════════════════════════════════════════════════════════════════
    // Protege al servidor del agotamiento de recursos (cada long-poll ocupa
    // un worker/PHP-FPM): un slot por key, expira por mtime (ttl) y se libera
    // explícitamente. Las claves de prueba son únicas; los ficheros de slot
    // residen en un directorio derivado de DATA_PATH y se limpian al terminar
    // (data/ del worktree es un scaffold aislado, nunca producción).
    $pass = inbox_realtime_assert(
        function_exists('comercial_poll_acquire_slot'),
        'existe comercial_poll_acquire_slot() para limitar los long-polls concurrentes'
    ) && $pass;
    $pass = inbox_realtime_assert(
        inbox_realtime_signature_matches('comercial_poll_acquire_slot', array(
            array('name' => 'key'),
            array('name' => 'ttlSec', 'default' => 25),
        )),
        'comercial_poll_acquire_slot() declara key + ttlSec=25'
    ) && $pass;
    $pass = inbox_realtime_assert(
        function_exists('comercial_poll_release_slot'),
        'existe comercial_poll_release_slot() para liberar el slot de poll'
    ) && $pass;
    $pass = inbox_realtime_assert(
        inbox_realtime_signature_matches('comercial_poll_release_slot', array(
            array('name' => 'key'),
        )),
        'comercial_poll_release_slot() declara key'
    ) && $pass;

    // El directorio de slots debe derivarse de DATA_PATH (no de rutas sueltas
    // del servidor): comprobación estática sobre la fuente de app/comercial.php.
    $comercialSrc = (string)file_get_contents(dirname(__DIR__) . '/app/comercial.php');
    $acquireAt = strpos($comercialSrc, 'function comercial_poll_acquire_slot');
    $pass = inbox_realtime_assert(
        $acquireAt !== false && strpos($comercialSrc, 'DATA_PATH', (int)$acquireAt) !== false,
        'comercial_poll_acquire_slot() deriva el directorio de slots de DATA_PATH'
    ) && $pass;

    inbox_realtime_run_behavior('comercial_poll_acquire_slot(): el segundo acquire con el mismo key (en uso) falla', function () use (&$pass, &$slotKeys) {
        $key = 'slot-cap-' . substr(uniqid('', true), -8);
        $slotKeys[] = $key;
        $first = comercial_poll_acquire_slot($key);
        $second = comercial_poll_acquire_slot($key);
        $pass = inbox_realtime_assert($first === true, 'el primer acquire del slot devuelve true') && $pass;
        $pass = inbox_realtime_assert($second === false, 'mientras el slot está en uso, un segundo acquire del mismo key devuelve false (cap)') && $pass;
    }, $pass);

    inbox_realtime_run_behavior('comercial_poll_acquire_slot(): un slot con mtime más viejo que el ttl se re-adquiere (expira)', function () use (&$pass, &$slotKeys) {
        $key = 'slot-expire-' . substr(uniqid('', true), -8);
        $slotKeys[] = $key;
        $acquired = comercial_poll_acquire_slot($key, 1);
        // ttl = 1 s: al dormir 2 s el fichero del slot queda con mtime más viejo
        // que el ttl, exactamente el caso de expiración/reclaim por mtime.
        sleep(2);
        $reclaimed = comercial_poll_acquire_slot($key, 1);
        $pass = inbox_realtime_assert($acquired === true, 'el primer acquire con ttl corto devuelve true') && $pass;
        $pass = inbox_realtime_assert($reclaimed === true, 'pasado el ttl (mtime del slot más viejo que ttl) el mismo key vuelve a adquirirse') && $pass;
    }, $pass);

    inbox_realtime_run_behavior('comercial_poll_release_slot(): liberar permite re-adquirir de inmediato', function () use (&$pass, &$slotKeys) {
        $key = 'slot-release-' . substr(uniqid('', true), -8);
        $slotKeys[] = $key;
        $first = comercial_poll_acquire_slot($key);
        comercial_poll_release_slot($key);
        $again = comercial_poll_acquire_slot($key);
        $pass = inbox_realtime_assert($first === true, 'el primer acquire devuelve true') && $pass;
        $pass = inbox_realtime_assert($again === true, 'tras release(), el mismo key se adquiere de inmediato otra vez') && $pass;
    }, $pass);

    $status = $pass ? 'TODOS LOS TESTS OK' : 'FASE ROJA: HAY FALLOS ESPERADOS (comportamiento no implementado)';
    fwrite($pass ? STDOUT : STDERR, PHP_EOL . $status . PHP_EOL);
} finally {
    if (isset($fixtureDir) && is_dir($fixtureDir)) {
        foreach (glob($fixtureDir . '/*') ?: array() as $file) {
            @unlink($file);
        }
        @rmdir($fixtureDir);
    }
    foreach ($queueFiles as $qf) {
        @unlink(DATA_PATH . '/' . $qf);
        @unlink(DATA_PATH . '/' . $qf . '.lock');
    }
    // Marcadores de cancelación de hilos fixture (no deberían existir: los
    // hilos no se encuentran, pero por si el helper los crea antes del fallo).
    foreach ($fixtureThreadIds as $tid) {
        @unlink(DATA_PATH . '/comercial_thread_cancel/' . md5((string)$tid) . '.cancel');
    }
    // Limpieza de slots de poll: los helpers derivan el directorio de DATA_PATH,
    // así que en este worktree los ficheros viven en el scaffold aislado, no en
    // producción. Se libera cada key (si el helper ya existe) y se borran los
    // ficheros residuales que contengan la clave de prueba.
    foreach ($slotKeys as $slotKey) {
        if (function_exists('comercial_poll_release_slot')) {
            try {
                comercial_poll_release_slot($slotKey);
            } catch (Throwable $e) {
                // el helper no está implementado: la limpieza por fichero cubre
            }
        }
        $slotDirs = array(
            DATA_PATH . '/comercial_poll_slots',
            DATA_PATH . '/poll_slots',
        );
        foreach ($slotDirs as $slotDir) {
            foreach (glob($slotDir . '/*') ?: array() as $slotFile) {
                if (strpos((string)$slotFile, $slotKey) !== false) @unlink($slotFile);
            }
            @rmdir($slotDir);
        }
        // Variante plana: ficheros de slot directamente en DATA_PATH con el key.
        foreach (glob(DATA_PATH . '/*') ?: array() as $dataFile) {
            if (strpos((string)$dataFile, $slotKey) !== false && strpos((string)$dataFile, 'slot') !== false) {
                @unlink($dataFile);
            }
        }
    }
}

exit($pass ? 0 : 1);
