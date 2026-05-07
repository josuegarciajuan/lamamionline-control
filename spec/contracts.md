# Contracts

## Contratos funcionales Fase 1

### Avisos
- `GET` de dashboard/avisos **no** debe modificar `data/avisos.json`.
- Nueva acción POST: `action=mark_avisos_read`
  - Inputs:
    - `scope=active_unread` (modo soportado en Fase 1)
    - `redirect` (opcional)
    - `csrf_token` (obligatorio)
  - Output:
    - Redirección con flash de estado.

### Seguridad formulario (avisos)
- Para acciones de avisos en Fase 1 se exige `csrf_token` válido:
  - `dismiss_aviso`
  - `create_manual_aviso`
  - `delete_planned_aviso`
  - `mark_avisos_read`

### Persistencia derivada
- Archivo nuevo: `data/avisos_active_snapshot.json`
  - Campos esperados:
    - `generated_at`
    - `active_rows`
    - `active_ids`
    - `exists_engine_source_any`

### Mantenimiento
- Script nuevo: `cron_mantenimiento.php` (CLI-only)
  - Ejecuta compactación de almacenamiento fuera de petición web.

## Contratos de infraestructura Fase 2

### Conexión MySQL (`app/db.php`)
- `crm_db()`: devuelve `PDO|null` cacheado a `telefonosbd`.
- Configuración por variables de entorno: `CRM_DB_HOST`, `CRM_DB_NAME`, `CRM_DB_USER`, `CRM_DB_PASS`, `CRM_DB_CHARSET`.
- `crm_db_table_exists($table)` y `crm_db_table_columns($table)`: introspección de schema.

### Backend de almacenamiento (`app/storage.php`)
- `storage_backend_mode()`: retorna `json` | `dual` | `mysql`.
  - Fuentes de configuración en orden: `CRM_STORAGE_BACKEND` (env) → `settings.storage_backend` → `json`.
- `storage_read($file)`: según modo, lee de MySQL, JSON, o MySQL con fallback JSON.
- `storage_write($file, $data)`: en `dual`/`mysql` escribe MySQL; en `json`/`dual` siempre escribe también JSON. `settings.json` siempre escribe JSON.
- `storage_upsert($file, $row)`: upsert en MySQL si aplica + upsert JSON directo.
- `storage_delete($file, $id)`: delete en MySQL si aplica + delete JSON directo.
- Mapa de archivos: `storage_mysql_file_spec($file)` devuelve `{kind, table, key_column, json_column, key_value}` para 37 archivos.

### Scripts Fase 2 (CLI-only)

#### `tools/phase2_backfill.php`
- **Propósito**: backfill idempotente JSON → MySQL.
- **Precondición**: tablas `crm_*` existentes.
- **Entrada**: `--only=archivo.json` (opcional).
- **Proceso**: lee JSON con `storage_json_read_direct`, escribe MySQL con `storage_mysql_write`, registra en `crm_migration_runs`.
- **Salida**: `OK archivo.json -> tabla (N)` o `FAIL`. Exit code 0 si todo OK.
- **Correcciones específicas**: `telefonos.waha` → 0, `eurekas.descripcion` truncado a 60k.

#### `tools/phase2_parity_check.php`
- **Propósito**: validar paridad de conteos e IDs entre JSON y MySQL.
- **Salida**: `OK archivo.json | json=N mysql=M` o `FAIL`. Reporte JSON en `data/phase2_parity_report_*.json`.
- **Exit code**: 0 si paridad total, 1 si hay divergencias.

#### `tools/phase2_apply_schema.php`
- **Propósito**: aplicar ajustes finales de schema e índices Fase 2.
- **Operaciones**: tabla `crm_comercial_ai_memory`, ALTERs de columna, 10 índices compuestos, tabla `crm_migration_runs`.
- **Idempotencia**: verifica existencia antes de crear.

### Modo dual — comportamiento esperado
- **Lectura**: preferencia MySQL con fallback JSON automático.
- **Escritura**: escribe MySQL y JSON; si MySQL falla, JSON sigue funcionando.
- **Configuración**: `export CRM_STORAGE_BACKEND=dual` o `storage_backend: "dual"` en `settings.json`.
