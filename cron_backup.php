<?php
/**
 * Backup automático de data/
 *
 * DOS NIVELES:
 *   - Hourly: cada hora, copia archivos top-level de data/ (.json, .jsonl, .ndjson)
 *   - Daily:  a las 02:00 AM, copia recursiva completa de data/
 *
 * ROTACIÓN AUTOMÁTICA: mantiene últimas 24 copias hourly y 7 copias daily.
 * Las copias viejas se eliminan automáticamente.
 */

require_once __DIR__ . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "FORBIDDEN - solo CLI\n";
    exit(1);
}

const MAX_HOURLY = 24;
const MAX_DAILY  = 7;

$logFile = DATA_PATH . '/cron_backup.log';

function backup_log(string $msg): void {
    global $logFile;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . trim($msg) . "\n";
    echo $line;
    @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function ensure_writable_dir(string $path): bool {
    if (!is_dir($path)) {
        if (!@mkdir($path, 0775, true)) {
            return false;
        }
    }
    return is_writable($path);
}

// ── Copia top-level (hourly): solo archivos planos, sin subdirectorios ──

function backup_top_level(string $destDir): int {
    $count = 0;

    $patterns = ['*.json', '*.jsonl', '*.ndjson'];

    foreach ($patterns as $pattern) {
        $files = glob(DATA_PATH . '/' . $pattern);
        if ($files === false) continue;

        foreach ($files as $file) {
            if (!is_file($file)) continue;
            $name = basename($file);
            if (@copy($file, $destDir . '/' . $name)) {
                $count++;
            } else {
                backup_log("  ⚠ Error copiando: {$name}");
            }
        }
    }

    return $count;
}

// ── Copia completa (daily): recursiva, todo data/ ──

function backup_full(string $destDir): array {
    if (!is_dir(DATA_PATH)) {
        return ['files' => 0, 'dirs' => 0, 'bytes' => 0];
    }

    $dirs  = 0;
    $files = 0;
    $bytes = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(DATA_PATH, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen(DATA_PATH) + 1);
        $target   = $destDir . '/' . $relative;

        if ($item->isDir()) {
            if (!is_dir($target)) {
                @mkdir($target, 0775, true);
            }
            if (is_dir($target)) {
                $dirs++;
            }
        } else {
            // Intentar crear directorio padre si no existe
            $parent = dirname($target);
            if (!is_dir($parent)) {
                @mkdir($parent, 0775, true);
            }
            if (@copy($item->getPathname(), $target)) {
                $files++;
                $bytes += $item->getSize();
            }
        }
    }

    return ['files' => $files, 'dirs' => $dirs, 'bytes' => $bytes];
}

// ── Limpieza de copias viejas ──

function cleanup_old(string $globPattern, int $maxKeep): int {
    $dirs = glob(BASE_PATH . '/' . $globPattern, GLOB_ONLYDIR);
    if ($dirs === false || count($dirs) <= $maxKeep) {
        return 0;
    }

    // Orden alfabético = cronológico (YYYYMMDD_HHMM o YYYYMMDD)
    sort($dirs);
    $toDelete = array_slice($dirs, 0, count($dirs) - $maxKeep);

    $deleted = 0;
    foreach ($toDelete as $dir) {
        delete_recursive($dir);
        $deleted++;
        backup_log("  🗑 Eliminado: " . basename($dir));
    }

    return $deleted;
}

function delete_recursive(string $dir): bool {
    if (!is_dir($dir)) return false;

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $path = $item->getPathname();
        if ($item->isDir()) {
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }

    return @rmdir($dir);
}

function format_bytes(int $bytes): string {
    if ($bytes >= 1_048_576) {
        return round($bytes / 1_048_576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

// ═══════════════════════════════════════════════════════════════════════════
// EJECUCIÓN
// ═══════════════════════════════════════════════════════════════════════════

backup_log("── Backup iniciado ──");

// ── HOURLY ──

$hourlyDir = BASE_PATH . '/data_backup_hourly_' . date('Ymd_Hi');

if (ensure_writable_dir($hourlyDir)) {
    $hStart = microtime(true);
    $hCount = backup_top_level($hourlyDir);
    $hElapsed = round((microtime(true) - $hStart) * 1000);

    backup_log("Hourly: {$hCount} archivos -> " . basename($hourlyDir) . " ({$hElapsed}ms)");

    $hCleaned = cleanup_old('data_backup_hourly_*', MAX_HOURLY);
    if ($hCleaned === 0) {
        // Sin nada que limpiar, no logueamos
    }
} else {
    backup_log("Hourly: ERROR - no se pudo crear " . basename($hourlyDir));
}

// ── DAILY (solo a las 02:xx) ──

$hour = (int) date('H');

if ($hour === 2) {
    $dailyDir = BASE_PATH . '/data_backup_daily_' . date('Ymd');

    if (ensure_writable_dir($dailyDir)) {
        $dStart  = microtime(true);
        $dResult = backup_full($dailyDir);
        $dElapsed = round((microtime(true) - $dStart) * 1000);

        backup_log("Daily: {$dResult['files']} archivos, {$dResult['dirs']} dirs, " .
                   format_bytes($dResult['bytes']) . " -> " . basename($dailyDir) .
                   " ({$dElapsed}ms)");

        $dCleaned = cleanup_old('data_backup_daily_*', MAX_DAILY);
    } else {
        backup_log("Daily: ERROR - no se pudo crear " . basename($dailyDir));
    }
}

backup_log("── Backup finalizado ──");
