# Arquitectura (resumen operativo)

## Estado actual por fases

### Fase 1 (completada)
- Persistencia principal sigue en JSON.
- Compactación pesada movida a mantenimiento (cron), fuera de requests web.
- Avisos con lectura optimizada mediante snapshot de activos.
- GET de vistas de avisos sin efectos de escritura.

### Fase 2 (completada)
- Capa de conexión MySQL común (`app/db.php`) con PDO cacheado, helpers de consulta e introspección de schema.
- Backend de almacenamiento configurable en 3 modos: `json` (por defecto), `dual` (lectura preferente MySQL con fallback JSON + escritura dual), `mysql` (MySQL como fuente única). El modo se determina por `CRM_STORAGE_BACKEND` → `settings.json` → `json`.
- 37 archivos JSON mapeados a tablas `crm_*` con tres patrones: `rows_by_id`, `singleton` y `scalar_list`.
- Escrituras MySQL idempotentes mediante `ON DUPLICATE KEY UPDATE`.
- Backfill completo con script `tools/phase2_backfill.php`, trazable en `crm_migration_runs`.
- Paridad JSON ↔ MySQL validable con `tools/phase2_parity_check.php` (conteos, IDs, reporte JSON).
- Schema Fase 2 aplicado: tabla `crm_comercial_ai_memory`, índices compuestos para publicista/comercial/avisos, ALTERs de tipos de columna.
- JSON sigue siendo backend operativo por defecto. El modo `dual` está listo para activación bajo supervisión.

### Fase 3 (pendiente)
- Cutover con ventana corta a backend MySQL y rollback rápido.
