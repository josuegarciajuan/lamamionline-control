<?php
/**
 * comercial_agenda.php — Agenda Comercial (CRUD + utils)
 *
 * Gestiona data/comercial_agenda.json con persistencia a MySQL tabla crm_comercial_agenda.
 * Usa el mismo patrón storage.php (JSON file-based con raw_json en MySQL).
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

// ── Path del archivo JSON ──
function comercial_agenda_path(): string {
    return dirname(__DIR__) . '/data/comercial_agenda.json';
}

// ── Leer todas las entradas ──
function comercial_agenda_list(): array {
    $path = comercial_agenda_path();
    if (!file_exists($path)) return [];
    $raw = @file_get_contents($path);
    if ($raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// ── Guardar todas las entradas ──
function comercial_agenda_save_all(array $entries): bool {
    $path = comercial_agenda_path();
    $dir = dirname($path);
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $json = json_encode(array_values($entries), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        error_log('comercial_agenda_save_all: json_encode failed: ' . json_last_error_msg());
        return false;
    }

    // Asegurar que el archivo sea escribible (puede ser propiedad de root si se creó desde CLI)
    if (file_exists($path) && !is_writable($path)) {
        @chmod($path, 0664);
        clearstatcache(true, $path);
    }

    $written = @file_put_contents($path, $json, LOCK_EX);
    if ($written === false) {
        // Fallback: si falló, intentar borrar + recrear (la carpeta sí tiene permisos)
        @unlink($path);
        clearstatcache(true, $path);
        $written = @file_put_contents($path, $json, LOCK_EX);
        if ($written === false) {
            error_log('comercial_agenda_save_all: write failed for ' . $path);
            return false;
        }
    }

    @chmod($path, 0664);
    return true;
}

// ── Buscar por ID ──
function comercial_agenda_find_by_id(string $id): ?array {
    foreach (comercial_agenda_list() as $entry) {
        if (($entry['id'] ?? '') === $id) return $entry;
    }
    return null;
}

// ── Buscar por teléfono normalizado ──
function comercial_agenda_find_by_phone(string $phone): ?array {
    $norm = comercial_only_digits($phone);
    if ($norm === '') return null;
    foreach (comercial_agenda_list() as $entry) {
        $entryNorm = comercial_only_digits((string)($entry['telefono'] ?? ''));
        if ($entryNorm !== '' && $entryNorm === $norm) return $entry;
    }
    return null;
}

// ── Normalizar entrada ──
function comercial_agenda_normalize(array $entry): array {
    $entry['id']             = trim((string)($entry['id'] ?? ''));
    $entry['nombre']         = trim((string)($entry['nombre'] ?? ''));
    $entry['telefono']       = trim((string)($entry['telefono'] ?? ''));
    $entry['telefono_norm']  = comercial_only_digits($entry['telefono']);
    $entry['negocio']        = trim((string)($entry['negocio'] ?? ''));
    $entry['submode']        = ($entry['negocio'] === 'jostal') ? trim((string)($entry['submode'] ?? '')) : '';
    $entry['notas']          = trim((string)($entry['notas'] ?? ''));
    $entry['thread_id']      = trim((string)($entry['thread_id'] ?? ''));
    $now                     = now_datetime();
    if (empty($entry['created_at'])) $entry['created_at'] = $now;
    $entry['updated_at']     = $now;
    return $entry;
}

// ── Guardar/actualizar una entrada ──
// Retorna null si falla la persistencia (el caller debe verificar)
function comercial_agenda_save(array $entry): ?array {
    $entry = comercial_agenda_normalize($entry);
    if ($entry['id'] === '') {
        $entry['id'] = 'cag_' . substr(bin2hex(random_bytes(4)), 0, 8);
        $entry['created_at'] = now_datetime();
    }

    $entries = comercial_agenda_list();
    $found = false;
    foreach ($entries as $i => $existing) {
        if (($existing['id'] ?? '') === $entry['id']) {
            $entries[$i] = $entry;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $entries[] = $entry;
    }

    if (!comercial_agenda_save_all($entries)) {
        error_log('comercial_agenda_save: save_all failed for id=' . ($entry['id'] ?? ''));
        return null;
    }
    return $entry;
}

// ── Eliminar entrada ──
function comercial_agenda_delete(string $id): bool {
    $entries = comercial_agenda_list();
    $deleted = false;
    $newEntries = [];
    foreach ($entries as $entry) {
        if (($entry['id'] ?? '') === $id) {
            $deleted = true;
            continue;
        }
        $newEntries[] = $entry;
    }
    if ($deleted) {
        if (!comercial_agenda_save_all($newEntries)) {
            error_log('comercial_agenda_delete: save_all failed for id=' . $id);
            return false;
        }
        return true;
    }
    return false;
}

// ── Negocios disponibles ──
function comercial_agenda_negocios(): array {
    return [
        ['slug' => 'lamami',     'nombre' => 'LaMami'],
        ['slug' => 'jostal',     'nombre' => 'Jostal'],
        ['slug' => 'casawasap',  'nombre' => 'CasaWasap'],
        ['slug' => 'publicista', 'nombre' => 'Publicista'],
        ['slug' => 'general',    'nombre' => 'General'],
    ];
}

function comercial_agenda_negocio_name(string $slug): string {
    foreach (comercial_agenda_negocios() as $n) {
        if ($n['slug'] === $slug) return $n['nombre'];
    }
    return ucfirst($slug);
}

function comercial_agenda_negocio_label(string $negocio, string $submode = ''): string {
    $name = comercial_agenda_negocio_name($negocio);
    if ($negocio === 'jostal' && $submode !== '') {
        $name .= ' (' . ($submode === 'alquiler' ? 'Alquiler' : 'Plaza') . ')';
    }
    return $name;
}
