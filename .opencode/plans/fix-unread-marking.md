# Fix: sistema de no leídos — bugs críticos

## Bugs encontrados

### P0 #1 — `inbox_is_unread()`: comparación de strings con formatos incompatibles
`inbox_api.php:92` — `$updatedAt > $lastRead` compara strings, pero:
- `updated_at` usa `date('Y-m-d H:i:s')` → `"2026-08-09 00:41:50"` (local, espacio separador)
- `lastRead` usa `gmdate('Y-m-d\TH:i:s\Z')` → `"2026-08-08T23:05:38Z"` (UTC, T separador)

Para el hilo 654464023: `'9'` (Aug 09 local) > `'8'` (Aug 08 UTC) → SIEMPRE TRUE → hilo atascado como no leído.
Además, espacio (ASCII 32) < T (ASCII 84) → tras marcar como leído, mensajes nuevos NUNCA reactivan el dot.

### P0 #2 — `inbox_save_read_status()`: fallo silencioso
`inbox_api.php:79` — `@file_put_contents()` nunca comprueba si escribió. Retorna `void`. Si el save falla (permisos, disco lleno), el endpoint `mark_read` responde `{"ok": true}` mintiendo.

## Plan de cambios

### 1. `inbox_api.php` — Fix `inbox_is_unread()` (P0)
Reemplazar comparación de strings por `strtotime()` numérico:

```php
function inbox_is_unread(string $threadId, string $updatedAt, array $readStatus): bool {
    if ($updatedAt === '') return false;
    $updatedUnix = strtotime($updatedAt);
    if ($updatedUnix === false) return false;
    $lastRead = $readStatus[$threadId] ?? '';
    if ($lastRead === '') {
        return (time() - $updatedUnix) < 1800;
    }
    $lastReadUnix = strtotime($lastRead);
    if ($lastReadUnix === false) return false;
    return $updatedUnix > $lastReadUnix;
}
```

### 2. `inbox_api.php` — Fix `inbox_save_read_status()` (P0)
Comprobar retorno de `file_put_contents()` y devolver `bool`:

```php
function inbox_save_read_status(array $data): bool {
    $path = inbox_read_status_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    if (file_exists($path) && !is_writable($path)) {
        @chmod($path, 0664);
        clearstatcache(true, $path);
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log('inbox_save_read_status: json_encode failed: ' . json_last_error_msg());
        return false;
    }
    $written = @file_put_contents($path, $json, LOCK_EX);
    if ($written === false) {
        error_log('inbox_save_read_status: write failed for ' . $path);
        return false;
    }
    return true;
}
```

### 3. `inbox_api.php` — Fix `mark_read` handler (P0)
Verificar el save antes de responder OK:

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mark_read') {
    $threadId = trim((string)($_POST['thread_id'] ?? ''));
    if ($threadId === '') inbox_api_json_err('thread_id required');

    $lastTs = gmdate('Y-m-d\TH:i:s\Z', time() + 10);

    $readStatus = inbox_read_status();
    $readStatus[$threadId] = $lastTs;
    if (!inbox_save_read_status($readStatus)) {
        inbox_api_json_err('Error al guardar estado de lectura', 500);
    }

    inbox_api_json_ok(['thread_id' => $threadId, 'last_read_ts' => $lastTs]);
}
```

### 4. `inbox-chat.js` — Eliminar dead code `_readUnreadSnap`
Quitar línea de declaración y el bloque que lo escribe en `markRead()`.

### 5. `inbox-chat.js` — Extender ventana anti race-condition
En `renderSidebar()`, cambiar `15000` → `120000` (2 minutos en vez de 15 segundos).

### 6. `inbox.php` — Bump cache buster
`$_forceV` → `20260809_4`

## Archivos tocados
| Archivo | Líneas | Cambio |
|---------|--------|--------|
| `inbox_api.php:82-93` | ~15 | Fix `inbox_is_unread()` con `strtotime()` |
| `inbox_api.php:64-80` | ~5 | Fix `inbox_save_read_status()` retorna `bool` |
| `inbox_api.php:255-267` | ~3 | Fix `mark_read` handler comprueba save |
| `inbox-chat.js:18-19` | -2 | Eliminar `_readUnreadSnap` declaración |
| `inbox-chat.js:661-685` | -8 | Eliminar bloque que escribe `_readUnreadSnap` |
| `inbox-chat.js:349` | 1 | `15000` → `120000` |
| `inbox.php:53` | 1 | Bump `$_forceV` |
