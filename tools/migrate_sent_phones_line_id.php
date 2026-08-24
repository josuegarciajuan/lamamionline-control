<?php
/**
 * tools/migrate_sent_phones_line_id.php — one-shot de migración para la nueva
 * deduplicación del bot comercial por (rama, línea de origen).
 *
 * Contexto: antes la dedup era global (1 mensaje por teléfono, para siempre).
 * La nueva regla: un teléfono solo puede recibir 1 mensaje por rama y 1 por
 * línea de origen. Para reenviar, rama y línea deben ser nuevas para el teléfono.
 *
 * Problema: los envíos históricos guardados en comercial_sent_phones_*.json y
 * comercial_sent_phones.json NO tienen line_id (no se sabía desde qué línea se
 * envió). Las conversaciones en comercial_threads.json sí guardan line_id/line_phone.
 *
 * Este script:
 *  1. Backfill de line_id (y line_phone) en cada entrada por-rama usando el
 *     thread correspondiente (process_slug + target_phone).
 *  2. Entradas irrecuperables (sin hilo): se eliminan del archivo por-rama y el
 *     teléfono se añade a la blacklist (nunca más se le envía, evita repeticiones).
 *  3. Limpia del global las entradas huérfanas sin line_id y reconstruye el global
 *     con clave (phone, process_slug, line_id).
 *
 * Idempotente: las entradas ya con line_id se dejan igual; al re-ejecutar no queda
 * trabajo pendiente. Crea un backup de cada archivo antes de modificarlo.
 *
 * Uso:
 *   php tools/migrate_sent_phones_line_id.php            # ejecución real
 *   php tools/migrate_sent_phones_line_id.php --dry-run  # solo informa
 */

declare(strict_types=1);

$dryRun = in_array('--dry-run', $argv, true);

require_once __DIR__ . '/../app/bootstrap.php';

function mig_backup_file(string $path): void {
    $bak = $path . '.bak_dedup_' . date('Ymd_His');
    if (!file_exists($path) || file_exists($bak)) return;
    @copy($path, $bak);
}

/**
 * Resuelve (line_id, line_phone) desde el índice de threads.
 * @param array $idx índice slug|phoneIdentity => lista de threads
 */
function mig_resolve_line_for(array $idx, string $slug, string $phone): array {
    $key = $slug . '|' . $phone;
    if (!isset($idx[$key])) return array('', '');
    foreach ($idx[$key] as $t) {
        $lineId = trim((string)($t['line_id'] ?? ''));
        if ($lineId !== '') {
            return array($lineId, trim((string)($t['line_phone'] ?? '')));
        }
    }
    return array('', '');
}

// ── 1. Índice de threads por (process_slug, teléfono identidad) ──
$threads = storage_read('comercial_threads.json');
if (!is_array($threads)) $threads = array();
$idx = array();
foreach ($threads as $t) {
    if (!is_array($t)) continue;
    $slug = trim((string)($t['process_slug'] ?? ''));
    $phone = comercial_phone_identity((string)($t['target_phone'] ?? ''));
    if ($slug === '' || $phone === '') continue;
    $idx[$slug . '|' . $phone][] = $t;
}

// ── 2. Blacklist existente ──
$blacklistRows = storage_read('comercial_blacklist.json');
if (!is_array($blacklistRows)) $blacklistRows = array();
$blacklistPhones = array();
foreach ($blacklistRows as $b) {
    if (!is_array($b)) continue;
    $blacklistPhones[comercial_only_digits((string)($b['phone'] ?? ''))] = true;
}

$stats = array(
    'entries_por_rama' => 0,
    'ya_con_linea' => 0,
    'backfilled' => 0,
    'irrecuperables' => 0,
    'blacklisted_nuevos' => 0,
    'eliminados' => 0,
    'global_final' => 0,
);

$unrecoverablePhones = array();

$branchFiles = glob(DATA_PATH . '/comercial_sent_phones_*.json');
if (!is_array($branchFiles)) $branchFiles = array();
sort($branchFiles);

foreach ($branchFiles as $file) {
    $basename = basename($file);
    if ($basename === 'comercial_sent_phones.json') continue;
    $slug = preg_replace('/^comercial_sent_phones_|\.json$/', '', $basename);

    $rows = storage_read($basename);
    if (!is_array($rows)) continue;

    $changed = false;
    $newRows = array();
    foreach ($rows as $row) {
        if (!is_array($row)) { $newRows[] = $row; continue; }
        $stats['entries_por_rama']++;
        $phoneDigits = comercial_only_digits((string)($row['phone'] ?? ''));
        if ($phoneDigits === '') { $newRows[] = $row; continue; }

        if (trim((string)($row['line_id'] ?? '')) !== '') {
            $stats['ya_con_linea']++;
            $newRows[] = $row;
            continue;
        }

        $phoneIdentity = comercial_phone_identity($phoneDigits);
        list($lineId, $linePhone) = mig_resolve_line_for($idx, $slug, $phoneIdentity);

        if ($lineId !== '') {
            $row['line_id'] = $lineId;
            if ($linePhone !== '') $row['line_phone'] = $linePhone;
            $row['line_recovered'] = 'thread';
            $stats['backfilled']++;
            $changed = true;
            $newRows[] = $row;
            continue;
        }

        // Irrecuperable: eliminar del por-rama y bloquear el teléfono (nunca más)
        $stats['irrecuperables']++;
        $stats['eliminados']++;
        $changed = true;
        $unrecoverablePhones[$phoneDigits] = true;
        if (!isset($blacklistPhones[$phoneDigits])) {
            $blacklistPhones[$phoneDigits] = true;
            if (!$dryRun) {
                comercial_upsert_blacklist_entry(array(
                    'id' => generate_id('cmblk'),
                    'phone' => $phoneDigits,
                    'notes' => 'Migración dedup (rama,línea): línea de origen irrecuperable (sin hilo en comercial_threads). No enviar nunca más.',
                ));
            }
            $stats['blacklisted_nuevos']++;
        }
    }

    if ($changed && !$dryRun) {
        mig_backup_file($file);
        storage_write($basename, array_values($newRows));
    }
}

// ── 3. Limpiar global de huérfanos sin line_id y reconstruir ──
// Las entradas del modelo antiguo no tienen line_id y no participan en ninguna
// dimensión de la dedup nueva; se purgan todas (la verdad queda en los por-rama).
if (!$dryRun) {
    $global = storage_read('comercial_sent_phones.json');
    if (is_array($global)) {
        $changedGlobal = false;
        $kept = array();
        foreach ($global as $row) {
            if (!is_array($row)) { $kept[] = $row; continue; }
            $lineId = trim((string)($row['line_id'] ?? ''));
            if ($lineId === '') {
                $changedGlobal = true;
                continue;
            }
            $kept[] = $row;
        }
        if ($changedGlobal) {
            mig_backup_file(DATA_PATH . '/comercial_sent_phones.json');
            storage_write('comercial_sent_phones.json', array_values($kept));
        }
    }
    $stats['global_final'] = comercial_rebuild_global_sent_phones();
}

// ── 4. Informe ──
$line = str_repeat('=', 60);
fwrite(STDOUT, $line . "\n");
fwrite(STDOUT, "Migración dedup (rama, línea) — " . ($dryRun ? 'DRY-RUN (sin escribir)' : 'REAL') . "\n");
fwrite(STDOUT, $line . "\n");
foreach ($stats as $k => $v) {
    fwrite(STDOUT, sprintf("  %-20s %d\n", $k . ':', $v));
}
if (!empty($unrecoverablePhones)) {
    fwrite(STDOUT, "  Teléfonos irrecuperables (blacklist):\n");
    foreach (array_keys($unrecoverablePhones) as $p) {
        fwrite(STDOUT, "    - {$p}\n");
    }
}
fwrite(STDOUT, $line . "\n");
if ($dryRun) {
    fwrite(STDOUT, "Ejecuta sin --dry-run para aplicar.\n");
}
