<?php
/**
 * Repara perfiles de Publicista legacy que tienen >=4 candidatas pero <4 definitivas.
 * Causa: un bug en publicista_rebuild_finals_from_candidates() rechazaba candidatas
 * por umbral de likeness al regenerar una sola, descartando las otras.
 * 
 * Este script reconstruye las definitivas usando la función corregida.
 * 
 * Uso: php tools/fix_publicista_legacy_finals.php
 */

require_once __DIR__ . '/../app/bootstrap.php';

// ── Backup de seguridad ──────────────────────────────────────────────────────
$jsonPath = DATA_PATH . '/publicista_jobs.json';
$backupPath = DATA_PATH . '/publicista_jobs.json.bak_' . date('Ymd_His');
if (!@copy($jsonPath, $backupPath)) {
    echo "ERROR: No se pudo crear backup en {$backupPath}\n";
    exit(1);
}
echo "✓ Backup creado: {$backupPath}\n\n";

// ── IDs de los perfiles afectados ────────────────────────────────────────────
$targetIds = array(
    'pubjob_2b44ef65',  // Iris — 0 definitivas
    'pubjob_8be3407e',  // Pruebas — 0 definitivas
    'pubjob_cb8487ce',  // Carina — 0 definitivas
    'pubjob_c899805b',  // Ana — 0 definitivas
    'pubjob_3f3e8f30',  // Fabiola — 1 definitiva
);

$fixed = 0;
$skipped = 0;
$errors = 0;

foreach ($targetIds as $jobId) {
    echo "Procesando {$jobId}... ";
    
    $job = publicista_job_get($jobId);
    if (!$job) {
        echo "⚠ No encontrado\n";
        $skipped++;
        continue;
    }
    
    $candidates = is_array($job['candidates'] ?? null) ? $job['candidates'] : array();
    $currentFinals = is_array($job['final_images'] ?? null) ? $job['final_images'] : array();
    $candCount = count($candidates);
    $finalCount = count($currentFinals);
    
    echo "{$candCount} candidatas, {$finalCount} definitivas → ";
    
    if ($candCount < 4) {
        echo "⚠ Solo {$candCount} candidatas (<4), se omite\n";
        $skipped++;
        continue;
    }
    
    if ($finalCount >= 4) {
        echo "✓ Ya tiene {$finalCount} definitivas (>=4), se omite\n";
        $skipped++;
        continue;
    }
    
    // ── Reconstruir definitivas ──────────────────────────────────────────
    list($updatedCandidates, $newFinals, $selectedIds) = publicista_rebuild_finals_from_candidates(
        $jobId,
        $candidates,
        $job
    );
    
    $newFinalCount = count($newFinals);
    
    // ── Actualizar el job ────────────────────────────────────────────────
    $job['candidates'] = $updatedCandidates;
    $job['final_images'] = $newFinals;
    $job['estado'] = $newFinalCount >= 4 ? 'done' : 'needs_review';
    
    // Actualizar pipeline
    $pipeline = is_array($job['pipeline'] ?? null) ? $job['pipeline'] : array();
    $pipeline['status'] = $newFinalCount >= 4 ? 'done' : 'needs_review';
    $pipeline['selected_candidate_ids'] = $selectedIds;
    $pipeline['final_candidate_ids'] = array_map(function($f) { return $f['id'] ?? ''; }, $newFinals);
    $pipeline['total_selected'] = count($selectedIds);
    $pipeline['legacy_fix_applied_at'] = now_datetime();
    $job['pipeline'] = $pipeline;
    
    // Actualizar processing
    $processing = is_array($job['processing'] ?? null) ? $job['processing'] : array();
    $processing['last_action'] = 'fix_legacy_finals';
    $processing['last_finished_at'] = now_datetime();
    $job['processing'] = $processing;
    
    list($ok, $result) = publicista_job_save($job);
    
    if ($ok) {
        echo "✓ Reparado: {$newFinalCount} definitivas (estado: {$job['estado']})\n";
        $fixed++;
    } else {
        echo "✗ Error al guardar: " . (is_string($result) ? $result : 'desconocido') . "\n";
        $errors++;
    }
}

echo "\n────────────────────────────────────────────────────\n";
echo "Resumen: {$fixed} reparados, {$skipped} omitidos, {$errors} errores\n";
if ($fixed > 0) {
    echo "Backup original: {$backupPath}\n";
}
echo "Hecho.\n";
