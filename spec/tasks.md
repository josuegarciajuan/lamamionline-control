# Tasks por fases

## Fase 1 — Rendimiento Inmediato (Online)

- [x] Sacar compacción de bootstrap y moverla a cron/mantenimiento.
- [x] Quitar escritura en GET del panel de avisos.
- [x] Añadir acción POST explícita para marcar avisos leídos.
- [x] Añadir snapshot/index de avisos activos y usarlo en lectura.
- [x] Añadir controles mínimos de seguridad en acciones de avisos (CSRF).
- [x] Ejecutar validación técnica (lint PHP + regresión funcional relevante).
- [x] Actualizar documentación mínima (changelog, arquitectura, ADR, contratos).

## Fase 2 — Migración Segura (Dual-Run Online)

- [x] Ajustes finales schema/índices MySQL.
- [x] Backfill JSON -> MySQL idempotente.
- [x] Activación `dual` y validación paridad.
- [x] Auditoría de seguridad de cambios Fase 2.
- [x] Documentación de cierre (changelog, arquitectura, ADR, contratos, design).

## Fase 3 — Corte Controlado a MySQL (Ventana corta)

- [ ] Freeze escrituras + delta backfill final.
- [ ] Cambio backend efectivo a `mysql`.
- [ ] Smoke tests críticos + reapertura.
- [ ] Rollback operativo (<10 min) validado.

## CX2 — Desbloqueo SDD bot comercial (F1..F8)

### CX2-F1 — Modelo base de interés real
- [x] Definir estados canónicos y rango de score (0..100).
- [x] Definir contrato mínimo de evaluación (`estado`, `score`, `motivo`, `timestamp`).
- [x] Definir reglas mínimas de escalado y no-escalado.
- [x] Revisar consistencia entre requirements/design/contracts.

### CX2-F2 — Señales y normalización
- [x] Inventariar señales de entrada prioritarias por canal.
- [x] Definir taxonomía de señales (positiva, neutra, negativa, bloqueo).
- [x] Definir normalización mínima para consumo del scoring.

### CX2-F3 — Scoring inicial
- [x] Definir ponderaciones base por señal.
- [x] Definir efecto temporal (degradación/recencia) a nivel contractual.
- [x] Definir criterios de cambio de tramo (bajo/medio/alto).

### CX2-F4 — Persistencia y auditoría
- [ ] Definir registro de evaluaciones con trazabilidad de reglas.
- [ ] Definir retención mínima de historial por conversación.
- [ ] Definir campos obligatorios para auditoría operativa.

### CX2-F5 — Escalado operativo
- [ ] Definir umbrales definitivos de escalado.
- [ ] Definir anti-ruido y anti-duplicado de escalados.
- [ ] Definir payload mínimo de handoff a comercial humano.

### CX2-F6 — Integración operativa
- [ ] Definir visualización de estado/score en panel.
- [ ] Definir acciones de confirmación/corrección humana.
- [ ] Definir trazabilidad de overrides manuales.

### CX2-F7 — Calibración y guardrails
- [ ] Definir rutina de calibración periódica.
- [ ] Definir límites para falsos positivos y sobre-escalado.
- [ ] Definir criterios de revisión de reglas por resultados.

### CX2-F8 — Cierre y aceptación
- [ ] Definir métricas de éxito (negocio y operación).
- [ ] Ejecutar checklist final de consistencia documental.
- [ ] Aprobar cierre de CX2 con evidencias trazables.
