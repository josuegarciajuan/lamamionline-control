# ADR-007: CX2-F7 — Calibración periódica y guardrails versionados

## Estado
Aprobada (2026-05-11)

## Contexto
Tras F1-F6, CX2 necesita un mecanismo de mejora continua que reduzca falsos positivos y sobre-escalado sin introducir cambios directos de runtime.

## Decisión
Adoptar en F7 un ciclo documental de calibración con tres cadencias (semanal, mensual, trimestral), guardrails cuantitativos y decisiones versionadas (`promote|hold|rollback|discard`).

Se exige trazabilidad completa de métricas, versiones y evidencias antes de promover cambios de reglas.

## Consecuencias
### Positivas
- Menor deriva operativa del modelo de decisión.
- Mejor control de riesgo comercial por límites explícitos.
- Base robusta para cierre de aceptación en F8.

### Costes
- Mayor disciplina de gobierno de cambios.
- Más carga de revisión y auditoría periódica.

## Alternativas consideradas
1. Ajuste ad-hoc sin ciclos fijos.
   - Rechazada: baja reproducibilidad y alto riesgo de sesgo.
2. Promoción automática por una única métrica.
   - Rechazada: insuficiente control de regresiones y seguridad.

## Relación con fases
- Depende de F1-F6.
- Habilita F8 con base de métricas y evidencias trazables.
