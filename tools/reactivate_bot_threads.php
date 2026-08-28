<?php
/**
 * tools/reactivate_bot_threads.php — Reactivación masiva del bot en conversaciones
 * del inbox comercial que quedaron paradas (human_taken / inbox_paused).
 *
 * Un hilo se muestra como "⏸ Parado" cuando tiene human_taken=1 o inbox_paused=1.
 * Este script limpia ambos flags (y el marcador de cancelación) para que el bot
 * vuelva a contestar automáticamente en esos hilos.
 *
 * USO (CLI):
 *   # Informe de candidatos (dry-run, no toca nada) — comportamiento por defecto
 *   php tools/reactivate_bot_threads.php
 *   # Filtros por línea y por ventana de fecha (última actividad)
 *   php tools/reactivate_bot_threads.php --line=tf_de558a13 --line=tf_f500d7c3
 *   php tools/reactivate_bot_threads.php --since=2026-08-01 --before=2026-08-31
 *   # Aplicar (hace backup automático antes de escribir)
 *   php tools/reactivate_bot_threads.php --apply
 *
 * Seguridad: sin --apply SOLO informa. Con --apply hace copia de
 * comercial_threads.json en data_backup_* antes de modificar.
 */

declare(strict_types=1);

error_reporting(E_ALL);

function tb_cli_args(): array
{
    $line = [];
    $since = '';
    $before = '';
    $apply = false;
    $json = false;
    $quiet = false;

    foreach (array_slice($_SERVER['argv'], 1) as $arg) {
        if ($arg === '--apply') { $apply = true; continue; }
        if ($arg === '--json') { $json = true; continue; }
        if ($arg === '--quiet') { $quiet = true; continue; }
        if (str_starts_with($arg, '--line=')) { $line[] = substr($arg, 7); continue; }
        if (str_starts_with($arg, '--since=')) { $since = substr($arg, 8); continue; }
        if (str_starts_with($arg, '--before=')) { $before = substr($arg, 9); continue; }
    }

    return [
        'line'   => array_values(array_unique(array_filter(array_map('trim', $line)))),
        'since'  => trim($since),
        'before' => trim($before),
        'apply'  => $apply,
        'json'   => $json,
        'quiet'  => $quiet,
    ];
}

function tb_data_dir(): string
{
    $base = dirname(__DIR__); // proyecto real (bind mount) o copia de trabajo
    if (is_dir($base . '/data')) {
        return $base . '/data';
    }
    // Fallback: variable de entorno para señalar el directorio de datos real.
    $env = trim((string)($_ENV['LAMAMI_DATA'] ?? getenv('LAMAMI_DATA')));
    if ($env !== '' && is_dir($env)) {
        return rtrim($env, '/');
    }
    fwrite(STDERR, "ERROR: no se encuentra data/ (usa LAMAMI_DATA=<ruta> si ejecutas desde un worktree).\n");
    exit(2);
}

function tb_threads_path(string $dir): string
{
    return $dir . '/comercial_threads.json';
}

function tb_load_threads(string $path): array
{
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function tb_cancel_path(string $dir, string $threadId): string
{
    $safe = $threadId !== '' ? md5($threadId) : 'unknown';
    return $dir . '/comercial_thread_cancel/' . $safe . '.cancel';
}

function tb_last_activity(array $t): string
{
    foreach (['last_contact_at', 'last_human_reply_at', 'updated_at', 'created_at'] as $k) {
        $v = trim((string)($t[$k] ?? ''));
        if ($v !== '') return $v;
    }
    return '';
}

function tb_in_window(string $ts, string $since, string $before): bool
{
    $day = substr($ts, 0, 10);
    if ($day === '') return true;
    if ($since !== '' && $day < $since) return false;
    if ($before !== '' && $day > $before) return false;
    return true;
}

function tb_reason(array $t): string
{
    $human = !empty($t['human_taken']);
    $paused = !empty($t['inbox_paused']);
    if ($human && $paused) return 'human+paused';
    if ($human) return 'human_taken';
    return 'paused';
}

function main(): void
{
    $opts = tb_cli_args();

    $dir = tb_data_dir();
    $path = tb_threads_path($dir);
    if (!is_file($path)) {
        fwrite(STDERR, "ERROR: no existe $path\n");
        exit(2);
    }

    $threads = tb_load_threads($path);
    $candidates = [];

    foreach ($threads as $t) {
        if (!is_array($t)) continue;
        if (empty($t['human_taken']) && empty($t['inbox_paused'])) continue; // no está parado
        $lineId = (string)($t['line_id'] ?? '');
        if (!empty($opts['line']) && !in_array($lineId, $opts['line'], true)) continue;
        if (!tb_in_window(tb_last_activity($t), $opts['since'], $opts['before'])) continue;
        $candidates[] = $t;
    }

    // Agrupar por línea y motivo para el informe.
    $byLineReason = [];
    $stages = [];
    foreach ($candidates as $t) {
        $lineId = (string)($t['line_id'] ?? '');
        $reason = tb_reason($t);
        $byLineReason[$lineId][$reason] = ($byLineReason[$lineId][$reason] ?? 0) + 1;
        $stage = (string)($t['stage'] ?? '-');
        $stages[$stage] = ($stages[$stage] ?? 0) + 1;
    }

    $total = count($candidates);

    if ($opts['json']) {
        echo json_encode([
            'dir' => $dir,
            'apply' => $opts['apply'],
            'filters' => ['line' => $opts['line'], 'since' => $opts['since'], 'before' => $opts['before']],
            'total' => $total,
            'by_line_reason' => $byLineReason,
            'by_stage' => $stages,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
        if (!$opts['apply']) return;
    } elseif (!$opts['quiet']) {
        echo "Directorio de datos: $dir\n";
        echo "Modo: " . ($opts['apply'] ? "APLICAR (modifica)" : "DRY-RUN (solo informe)") . "\n";
        echo "Filtros: " . ($opts['line'] ? ('line=' . implode(',', $opts['line'])) : 'todas') .
            ($opts['since'] !== '' ? " since={$opts['since']}" : '') .
            ($opts['before'] !== '' ? " before={$opts['before']}" : '') . "\n";
        echo "Total hilos parados (candidatos): $total\n\n";

        if ($total === 0) {
            echo "Nada que hacer.\n";
            return;
        }

        echo "Por línea y motivo:\n";
        foreach ($byLineReason as $lineId => $reasons) {
            $parts = [];
            foreach ($reasons as $reason => $n) {
                $parts[] = "$reason=$n";
            }
            echo "  $lineId: " . implode(', ', $parts) . "\n";
        }
        echo "Por stage:\n";
        foreach ($stages as $stage => $n) {
            echo "  $stage: $n\n";
        }
        echo "\n" . ($opts['apply'] ? "Aplicando..." : "Usa --apply para reactivar. (Este run NO ha modificado nada).") . "\n";
        if (!$opts['apply']) {
            return;
        }
    }

    // ── Aplicar ──
    if (!$opts['apply']) {
        return;
    }

    // Backup atómico del archivo antes de tocar.
    $backupDir = dirname($path) . '/data_backup_' . date('Ymd_His');
    if (!is_dir($backupDir) && !@mkdir($backupDir, 0775, true)) {
        fwrite(STDERR, "ERROR: no se pudo crear $backupDir\n");
        exit(2);
    }
    $backupFile = $backupDir . '/comercial_threads.json';
    if (!@copy($path, $backupFile)) {
        fwrite(STDERR, "ERROR: no se pudo hacer backup a $backupFile\n");
        exit(2);
    }

    $ids = [];
    foreach ($threads as &$t) {
        if (!is_array($t)) continue;
        if (empty($t['human_taken']) && empty($t['inbox_paused'])) continue;
        $lineId = (string)($t['line_id'] ?? '');
        if (!empty($opts['line']) && !in_array($lineId, $opts['line'], true)) continue;
        if (!tb_in_window(tb_last_activity($t), $opts['since'], $opts['before'])) continue;

        $wasPaused = !empty($t['human_taken']) || !empty($t['inbox_paused']);
        $t['human_taken'] = 0;
        $t['inbox_paused'] = 0;
        // Limpiar marcador de cancelación (bot pausado en vuelo).
        if ($wasPaused) {
            @unlink(tb_cancel_path($dir, (string)($t['id'] ?? '')));
        }
        $ids[] = (string)($t['id'] ?? '');
    }
    unset($t);

    $out = json_encode($threads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($out === false) {
        fwrite(STDERR, "ERROR: no se pudo serializar el JSON. NO se escribió. Backup en $backupFile\n");
        exit(2);
    }
    if (@file_put_contents($path, $out, LOCK_EX) === false) {
        fwrite(STDERR, "ERROR: no se pudo escribir $path. Backup en $backupFile\n");
        exit(2);
    }

    $applied = count(array_unique($ids));
    echo "Reactivados $applied hilo(s). Backup en $backupFile\n";
}

main();
