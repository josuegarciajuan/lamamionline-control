<?php
/**
 * Contratos TDD del historial local y de la cola manual del inbox comercial.
 *
 * Uso: php tools/test_inbox_api_contracts.php
 *
 * Los fixtures son deliberadamente anónimos y viven solo en un directorio
 * temporal. Este script no carga inbox_api.php: el endpoint termina la
 * ejecución al emitir JSON y no ofrece un punto de inyección para sus datos.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

final class InboxContractFailure extends RuntimeException {}

function inbox_contract_assert(bool $condition, string $label): void
{
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$label}" . PHP_EOL);
        throw new InboxContractFailure($label);
    }
    fwrite(STDOUT, "[OK] {$label}" . PHP_EOL);
}

function inbox_contract_fixture_event(string $ts, string $text): array
{
    return [
        'ts' => $ts,
        'type' => 'reply_received',
        'payload' => [
            'thread_id' => 'thread-fixture',
            'target_phone' => '000000000',
            'text' => $text,
        ],
    ];
}

try {
    $fixtureDir = sys_get_temp_dir() . '/lamami-inbox-contracts-' . getmypid() . '-' . uniqid('', true);
    if (!mkdir($fixtureDir, 0700, true) && !is_dir($fixtureDir)) {
        throw new RuntimeException('No se pudo crear el directorio temporal de fixtures.');
    }
    $eventsPath = $fixtureDir . '/events.jsonl';
    $events = [
        inbox_contract_fixture_event('2026-08-27T10:00:00Z', 'primero'),
        inbox_contract_fixture_event('2026-08-27T10:01:00Z', 'segundo'),
        inbox_contract_fixture_event('2026-08-27T10:02:00Z', 'tercero'),
    ];
    file_put_contents($eventsPath, implode(PHP_EOL, [
        json_encode($events[0], JSON_UNESCAPED_SLASHES),
        '{json-invalido}',
        json_encode($events[1], JSON_UNESCAPED_SLASHES),
        json_encode($events[2], JSON_UNESCAPED_SLASHES),
    ])); // sin salto final: debe ser válido

    inbox_contract_assert(
        function_exists('comercial_read_jsonl_tail'),
        'existe comercial_read_jsonl_tail() para leer el final de JSONL sin cargar el archivo completo'
    );

    /** @var array<int,array<string,mixed>> $recent */
    $recent = comercial_read_jsonl_tail($eventsPath, 2);
    inbox_contract_assert(count($recent) === 2, 'el lector limita el tramo reciente a dos eventos válidos');
    inbox_contract_assert(
        array_column(array_map(static fn(array $event): array => (array)($event['payload'] ?? []), $recent), 'text') === ['segundo', 'tercero'],
        'el lector tolera JSON inválido, conserva orden cronológico y la última línea sin newline'
    );
    inbox_contract_assert(comercial_read_jsonl_tail($fixtureDir . '/empty.jsonl', 20) === [], 'el lector devuelve vacío para archivo inexistente o vacío');

    inbox_contract_assert(function_exists('comercial_thread_history_page'), 'existe comercial_thread_history_page() para revisión y cursor before');
    $page = comercial_thread_history_page(['id' => 'thread-fixture'], 2, '', $eventsPath);
    inbox_contract_assert(isset($page['revision']) && is_string($page['revision']) && $page['revision'] !== '', 'la primera página incluye revisión persistida');
    inbox_contract_assert(isset($page['before']), 'la primera página expone cursor before');
    inbox_contract_assert(($page['has_more'] ?? null) === true, 'la primera página limitada informa has_more');
    inbox_contract_assert(count((array)($page['messages'] ?? [])) === 2, 'la primera página devuelve únicamente el tramo reciente');

    inbox_contract_assert(function_exists('comercial_manual_send_job_enqueue'), 'existe comercial_manual_send_job_enqueue() para idempotencia durable');
    $queueFile = basename($fixtureDir) . '-manual-queue.json';
    $first = comercial_manual_send_job_enqueue('thread-fixture', 'mensaje de prueba', 'client-message-fixture', $queueFile);
    $retry = comercial_manual_send_job_enqueue('thread-fixture', 'mensaje de prueba', 'client-message-fixture', $queueFile);
    inbox_contract_assert(($first['id'] ?? '') !== '' && ($first['id'] ?? '') === ($retry['id'] ?? ''), 'la misma client_message_id devuelve el mismo trabajo');

    fwrite(STDOUT, 'TODOS LOS TESTS OK' . PHP_EOL);
} catch (InboxContractFailure $failure) {
    exit(1);
} finally {
    if (isset($fixtureDir) && is_dir($fixtureDir)) {
        foreach (glob($fixtureDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($fixtureDir);
    }
    if (isset($queueFile)) {
        @unlink(DATA_PATH . '/' . $queueFile);
        @unlink(DATA_PATH . '/' . $queueFile . '.lock');
    }
}
