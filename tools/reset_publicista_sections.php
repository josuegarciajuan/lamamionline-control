<?php

declare(strict_types=1);

/**
 * Resetea el estado persistido de las subsecciones:
 * - Publicista > Perfiles
 * - Publicista > Estrategias
 * - Publicista > Campañas
 *
 * Actúa sobre ambas rutas posibles de runtime detectadas en este proyecto:
 * - /data       (ruta que usa el código actual)
 * - /DATA_PATH  (directorio legado/alternativo encontrado en algunos paquetes)
 *
 * Mantiene intactas otras secciones (cuentas, clientes, avisos, etc.).
 */

$basePath = dirname(__DIR__);
$candidateDataDirs = array_values(array_unique(array_filter(array(
    $basePath . '/data',
    $basePath . '/DATA_PATH',
), static function ($path) {
    return is_string($path) && $path !== '';
})));

$targetJsonFiles = array(
    'publicista_jobs.json',
    'publicista_plannings.json',
    'publicista_campaigns.json',
    'publicista_campaign_items.json',
    'publicista_tasks.json',
    'publicista_runs.json',
);

$results = array();
$foundAnyDataDir = false;

foreach ($candidateDataDirs as $dataDir) {
    if (!is_dir($dataDir)) {
        $results[] = array(
            'data_dir' => $dataDir,
            'status' => 'missing',
            'files_reset' => array(),
            'cleanup' => array(),
        );
        continue;
    }

    $foundAnyDataDir = true;
    $filesReset = array();
    foreach ($targetJsonFiles as $file) {
        $path = $dataDir . '/' . $file;
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear el directorio para ' . $path);
        }
        $payload = "[]\n";
        if (@file_put_contents($path, $payload, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo resetear ' . $path);
        }
        $filesReset[] = $path;
    }

    $cleanup = array();

    $jobsRoot = $dataDir . '/publicista/jobs';
    if (is_dir($jobsRoot)) {
        $deleted = delete_dir_children($jobsRoot);
        $cleanup[] = array(
            'path' => $jobsRoot,
            'action' => 'children_deleted',
            'ok' => $deleted,
        );
        if (!$deleted) {
            throw new RuntimeException('No se pudo limpiar el contenido de ' . $jobsRoot);
        }
    }

    $freeBumpLog = $dataDir . '/publicista/free_bump_logs.ndjson';
    if (is_file($freeBumpLog)) {
        $deleted = @unlink($freeBumpLog);
        $cleanup[] = array(
            'path' => $freeBumpLog,
            'action' => 'file_deleted',
            'ok' => $deleted,
        );
        if (!$deleted) {
            throw new RuntimeException('No se pudo borrar ' . $freeBumpLog);
        }
    }

    $results[] = array(
        'data_dir' => $dataDir,
        'status' => 'reset',
        'files_reset' => $filesReset,
        'cleanup' => $cleanup,
    );
}

if (!$foundAnyDataDir) {
    fwrite(STDERR, "No se encontró ninguna carpeta de runtime compatible (ni /data ni /DATA_PATH).\n");
    exit(1);
}

echo json_encode(array(
    'ok' => true,
    'base_path' => $basePath,
    'results' => $results,
), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
exit(0);

function delete_dir_children(string $dir): bool
{
    $items = @scandir($dir);
    if ($items === false) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            if (!delete_dir_recursive($path)) {
                return false;
            }
            continue;
        }
        if (!@unlink($path)) {
            return false;
        }
    }

    return true;
}

function delete_dir_recursive(string $dir): bool
{
    if (!file_exists($dir)) {
        return true;
    }
    if (!is_dir($dir)) {
        return @unlink($dir);
    }

    $items = @scandir($dir);
    if ($items === false) {
        return false;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            if (!delete_dir_recursive($path)) {
                return false;
            }
            continue;
        }
        if (!@unlink($path)) {
            return false;
        }
    }

    return @rmdir($dir);
}
