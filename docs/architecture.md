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

### Publiscort — F2 (semilla core completada)
- Se incorpora `publiscort` como proceso comercial nativo (quinto proceso) en `comercial_build_default_processes()`.
- Modelo de origen alineado a colas JSONL (`source_type=jsonl_queue`) con ficheros dedicados `publiscort_1..3.jsonl`.
- Configuración inicial conservadora y desactivada (`enabled=0`) para evitar impacto operativo accidental durante el despliegue.
- Seed con ventana e intervalos propios para arranque controlado (`10:00-19:00`, `5400-7200s`) y contexto IA específico.

### Publiscort — F4 (compatibilidad en instalaciones existentes)
- Se añade una migración no destructiva en `comercial_get_processes()` para asegurar que `publiscort` exista también en `comercial_processes.json` ya creado históricamente.
- La migración sólo inserta si falta el slug y preserva la configuración de los procesos existentes.
- El proceso insertado por migración se fuerza con `enabled=0` como guardrail de operación segura.

### Publiscort — F5 (validación y hardening)
- Se valida técnicamente la presencia de `publiscort` desactivado por defecto y la carga de sus plantillas operativas.
- Se añade hardening de persistencia en `comercial_prepare_process_for_storage()`: `source_mysql_pass` no se guarda en JSON local para evitar exposición de secretos en claro.

### Bloque CX2
- **CX2-F1 (completada):** modelo base de interés real (estados canónicos, score y reglas de escalado documental).
- **CX2-F2 (completada):** catálogo de señales por canal, taxonomía cerrada de clases y contrato de normalización mínima para alimentar scoring en CX2-F3.
- **CX2-F3 (completada):** definición del motor de scoring inicial (ponderaciones, recencia/degradación temporal, tramos e histéresis) con controles anti-ruido y trazabilidad.
- **CX2-F4 (completada):** capa documental de persistencia/auditoría con registro de evaluaciones inmutable lógico, trazabilidad de reglas, retención mínima por conversación y sello de auditoría operativa.
- **CX2-F5 (completada):** reglas operativas de escalado con umbrales estables, anti-ruido/anti-duplicado y payload mínimo de handoff humano, manteniendo enfoque documental sin cambios runtime.
- **CX2-F6 (completada):** integración documental en panel con vista operativa de estado/score/prioridad, acciones humanas controladas y trazabilidad de overrides manuales.
- **CX2-F7 (completada):** ciclo documental de calibración y guardrails con límites de riesgo medibles, versionado de reglas y decisiones de promoción/rollback auditables.
- **CX2-F8 (completada):** cierre documental del bloque CX2 con métricas de aceptación, checklist de consistencia F1-F8, acta de aprobación dual y manifiesto inmutable. Las 8 fases CX2 quedan cerradas como especificación trazable lista para implementación.
- Decisión técnica asociada: introducir una capa lógica de `InterestSignalNormalized` como interfaz estable entre ingestión de eventos y motor de scoring.
