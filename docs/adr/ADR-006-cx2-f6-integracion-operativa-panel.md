# ADR-006: CX2-F6 — Integración operativa en panel con overrides auditables

## Estado
Aprobada (2026-05-11)

## Contexto
Tras F1-F5, el sistema ya define estado, scoring, persistencia auditable y reglas de escalado. Faltaba un contrato de operación humana en panel para priorizar casos y gestionar correcciones sin alterar la lógica del motor.

## Decisión
Adoptar en F6 una integración documental de panel con:
- vista operativa estándar (`PanelOperationalView`),
- acciones humanas permitidas y acotadas (`confirmar_handoff`, `corregir_clasificacion`, `reabrir_revision`),
- trazabilidad append-only de overrides (`ManualOverrideRecord`).

Se decide explícitamente que F6 no recalcula scoring ni modifica reglas F1-F5; solo consume snapshots y registra decisiones humanas auditables.

## Consecuencias
### Positivas
- Reduce ambigüedad operativa en priorización diaria.
- Preserva coherencia entre decisión automática y acción humana.
- Facilita auditoría y no repudio de overrides.

### Costes
- Mayor disciplina operativa (motivo obligatorio, roles, MFA en críticos).
- Más complejidad contractual previa a implementación técnica.

## Alternativas consideradas
1. Permitir correcciones directas sobre score/estado en panel.
   - Rechazada: rompe trazabilidad y semántica de F1-F5.
2. No registrar overrides en estructura dedicada.
   - Rechazada: insuficiente para auditoría y análisis forense.

## Relación con fases
- Depende de F1-F5.
- Habilita F7 para instrumentar UI/servicios/telemetría con bajo riesgo contractual.
