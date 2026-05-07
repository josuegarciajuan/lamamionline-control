# Design · Fase 1 + Fase 2

## Decisiones Fase 1
1. **Compacción fuera de bootstrap**
   - `bootstrap_storage()` ya no ejecuta compactación pesada.
   - Nuevo entrypoint operativo: `cron_mantenimiento.php`.

2. **GET sin efectos laterales en avisos**
   - `render_avisos_panel()` solo renderiza.
   - Marcado de leídos pasa a acción explícita POST.

3. **Snapshot de avisos activos**
   - Nuevo derivado: `data/avisos_active_snapshot.json`.
   - Se refresca en cada escritura de `avisos.json`.
   - Fallback: regeneración automática si falta/corrupto.

## Decisiones Fase 2
1. **Capa DB común (`app/db.php`)**
   - Conexión PDO cacheada a `telefonosbd`, configurable por variables de entorno `CRM_DB_*`.
   - Helpers de consulta (`crm_db_query_all`, `crm_db_query_one`, `crm_db_execute`).
   - Introspección de schema: `crm_db_table_exists`, `crm_db_table_columns`.

2. **Backend de almacenamiento configurable (`app/storage.php`)**
   - Tres modos: `json` (por defecto), `dual` (lectura MySQL→fallback JSON, escritura dual), `mysql`.
   - Modo gobernado por `CRM_STORAGE_BACKEND` > `settings.storage_backend` > `json`.
   - Mapa declarativo de 37 archivos → tablas `crm_*` en `storage_mysql_file_spec()`.
   - Tres patrones de entidad: `rows_by_id`, `singleton`, `scalar_list`.

3. **Idempotencia en escrituras MySQL**
   - Todas las escrituras usan `INSERT ... ON DUPLICATE KEY UPDATE`.
   - Normalización automática de tipos (int, decimal, datetime, date, JSON, teléfono).

4. **Backfill idempotente (`tools/phase2_backfill.php`)**
   - CLI-only. Recorre 37 archivos JSON y escribe MySQL vía `storage_mysql_write`.
   - Registro de ejecuciones en tabla `crm_migration_runs`.
   - Filtro por archivo con `--only=archivo.json`.
   - Correcciones específicas: `telefonos.waha` → 0, `eurekas.descripcion` truncado a 60k.

5. **Validación de paridad (`tools/phase2_parity_check.php`)**
   - CLI-only. Compara conteos e IDs JSON ↔ MySQL.
   - Reporte JSON en `data/phase2_parity_report_*.json`.
   - Exit code distinto de cero si hay divergencia.

6. **Schema Fase 2 (`tools/phase2_apply_schema.php`)**
   - Nueva tabla `crm_comercial_ai_memory`.
   - ALTERs de tipos de columna (LONGTEXT, VARCHAR(64)).
   - 10 índices compuestos adicionales para publicista, comercial y avisos.
   - Tabla `crm_migration_runs` para trazabilidad de backfills.

## Tradeoffs
- Más escritura controlada en mutaciones de avisos (refresco snapshot) para reducir coste de lecturas repetidas (Fase 1).
- Complejidad adicional en `storage.php` (~1100 líneas de funciones `storage_mysql_*`) a cambio de migración sin corte (Fase 2).
- No se imponen foreign keys en MySQL durante Fase 2 para evitar bloqueos operativos durante la convivencia dual.
- JSON sigue siendo backend operativo por defecto; el switch a `dual` o `mysql` es manual y verificable.
