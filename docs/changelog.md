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

## 2026-05-11 · CX2-F1 — Desbloqueo SDD bot comercial

### Cambios
- Se añadió el bloque CX2 al alcance de requisitos para habilitar fases `CX2-F1..CX2-F8`.
- Se incorporó el diseño funcional de `CX2-F1` con estados canónicos, score de interés y reglas de escalado explicables.
- Se definieron contratos de comportamiento para `CX2-F1` (estados válidos, rango de score, transiciones y consistencia).
- Se actualizó el checklist de `spec/tasks.md` con las fases CX2 y se marcó `CX2-F1` como completada a nivel documental SDD.

### Motivo
Desbloquear la ejecución por comando `/fase CX2-F1` con una base SDD trazable antes de entrar en implementación de runtime.

## 2026-05-11 · CX2-F2 — Señales y normalización

### Cambios
- Se documentó catálogo inicial de señales prioritarias por canal (v1 centrada en WhatsApp) en `spec/design.md`.
- Se formalizó taxonomía cerrada de clases (`positiva|neutra|negativa|bloqueo`) y reglas de precedencia.
- Se definió contrato de evento normalizado `InterestSignalNormalized` con campos obligatorios, opcionales y reglas de dedupe/fallback en `spec/contracts.md`.
- Se incorporaron guardrails contractuales de seguridad/privacidad para evitar escalado inducible por texto libre y sobreexposición de PII en trazas.
- Se actualizó arquitectura con impacto del bloque CX2 y se añadió ADR-003.
- Se marcó `CX2-F2` como completada en `spec/tasks.md`.

### Motivo
Establecer una interfaz de señales consistente y auditable para habilitar el scoring de interés en CX2-F3 con menor ambigüedad operativa.

## 2026-05-11 · CX2-F3 — Scoring inicial

### Cambios
- Se definió el modelo de scoring inicial en `spec/design.md` con:
  - ponderaciones base por señal,
  - fórmula de cálculo con factor de confianza y recencia,
  - degradación temporal por inactividad,
  - tramos `bajo/medio/alto` y reglas de histéresis.
- Se formalizaron contratos de F3 en `spec/contracts.md` para cálculo, consistencia tramo↔estado, dominancia de bloqueo y trazabilidad auditable.
- Se añadieron controles contractuales de seguridad para anti-replay, anti-gaming por spam y prevención de escalado por señal ambigua única.
- Se actualizó checklist de `spec/tasks.md` marcando `CX2-F3` como completada.
- Se añadió ADR-004 con la decisión arquitectónica de scoring inicial.

### Motivo
Disponer de un score inicial conservador, explicable y resistente a ruido para soportar fases posteriores de auditoría y escalado operativo.
