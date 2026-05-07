# Changelog

## 2026-05-05 · Fase 1 — Rendimiento Inmediato (Online)

### Cambios
- Se retiró la compactación pesada del bootstrap web.
- Se añadió cron de mantenimiento (`cron_mantenimiento.php`) para ejecutar compactación fuera de petición de usuario.
- El panel de avisos dejó de escribir en GET; el marcado de leídos ahora se hace por POST explícito.
- Se incorporó snapshot derivado de avisos activos (`data/avisos_active_snapshot.json`) para reducir lecturas full-scan repetidas.
- Se añadieron validaciones CSRF en acciones de avisos de Fase 1.

### Motivo
Reducir latencia global y contención de I/O antes de la migración completa a MySQL.

## 2026-05-05 · Fase 2 — Migración Segura (Dual-Run Online)

### Cambios
- Se creó capa de conexión MySQL común (`app/db.php`): PDO cacheado, helpers de consulta, introspección de tablas/columnas, configurable por `CRM_DB_*`.
- Se implementó backend de almacenamiento configurable en 3 modos (`json`, `dual`, `mysql`) dentro de `app/storage.php`.
- Se añadió mapa completo de 37 archivos JSON → tablas `crm_*` con especificaciones `rows_by_id`, `singleton` y `scalar_list`.
- Se incorporó `ON DUPLICATE KEY UPDATE` en todas las escrituras MySQL para garantizar idempotencia.
- Se creó `tools/phase2_apply_schema.php` para aplicar ajustes finales de schema e índices (tabla `crm_comercial_ai_memory`, 10 índices compuestos, ALTERs de columnas).
- Se creó `tools/phase2_backfill.php` para backfill idempotente JSON → MySQL con registro en `crm_migration_runs` y filtro `--only=`.
- Se creó `tools/phase2_parity_check.php` para validar paridad de conteos e IDs entre JSON y MySQL, con reporte JSON.
- Se añadió sincronización automática de `crm_comercial_process_lines` desde `comercial_processes.json`.

### Motivo
Disponer de infraestructura dual-run completa: backfill verificado, paridad medible y capacidad de activar MySQL como lectura preferente sin romper la operativa JSON existente.
