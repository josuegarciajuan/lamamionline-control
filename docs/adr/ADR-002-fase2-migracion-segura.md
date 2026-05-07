# ADR-002 · Fase 2 Migración Segura (Dual-Run)

## Estado
Aprobada

## Contexto
Tras Fase 1 (rendimiento inmediato con JSON optimizado), el sistema requiere migrar a MySQL como backend definitivo. Se necesita una estrategia que permita convivencia JSON/MySQL sin corte, con validación de paridad y capacidad de regresión inmediata.

## Decisión
1. **Backend configurable en 3 modos** (`json`, `dual`, `mysql`) dentro de `storage.php`, gobernado por `CRM_STORAGE_BACKEND` (env) o `settings.json`. Las funciones públicas `storage_read/write/upsert/delete` enrutan automáticamente según el modo activo.
2. **Mapa declarativo JSON→MySQL**: 37 archivos mapeados en `storage_mysql_file_spec()` con patrones `rows_by_id`, `singleton` y `scalar_list`. Cada fila se persiste con columna `raw_json`/`payload_json` para preservación completa del payload.
3. **Backfill idempotente**: `tools/phase2_backfill.php` recorre todos los archivos y ejecuta `INSERT ... ON DUPLICATE KEY UPDATE`. Se registra cada ejecución en `crm_migration_runs` con estado `ok`/`failed` y conteos por archivo.
4. **Validación de paridad**: `tools/phase2_parity_check.php` compara conteos e IDs entre JSON y MySQL, detecta elementos faltantes/sobrantes y genera reporte JSON en `data/`. Exit code 0 solo si hay paridad total.
5. **Modo dual como transición segura**: en modo `dual`, la lectura prefiere MySQL (con fallback a JSON si la tabla no tiene filas), y la escritura va a ambos backends. JSON nunca se borra.
6. **Normalización automática de tipos**: enteros, decimales, datetime, date, JSON y dígitos de teléfono se convierten automáticamente al escribir en MySQL según el tipo de columna detectado en el schema.
7. **Schema Fase 2**: tabla `crm_comercial_ai_memory`, 10 índices compuestos adicionales, ALTERs para ampliar `descripcion` a LONGTEXT y columnas de teléfono normalizado a VARCHAR(64), tabla `crm_migration_runs` para trazabilidad.

## Consecuencias
- El sistema puede operar en `dual` sin riesgo: si MySQL falla, JSON sigue sirviendo lecturas y escrituras.
- El backfill es repetible sin duplicados gracias a `ON DUPLICATE KEY UPDATE`.
- La paridad es medible objetivamente antes de cualquier cambio de modo.
- Se añade complejidad moderada en `storage.php` (~1100 líneas adicionales), aislada en funciones `storage_mysql_*`.
- La tabla `crm_comercial_process_lines` se mantiene sincronizada automáticamente como tabla derivada desde `comercial_processes.json`.
- No se imponen foreign keys duras — se difieren a Fase 3 para no bloquear la operativa durante la migración.
