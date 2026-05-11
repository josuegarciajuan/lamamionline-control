# ADR-005: CX2-F5 — Reglas de escalado operativo conservadoras

## Estado
Aprobada (2026-05-11)

## Contexto
Tras definir modelo base (F1), señales (F2), scoring (F3) y persistencia/auditoría (F4), faltaba cerrar una política operativa de escalado que evitara ruido y duplicidad antes de integrar en panel (F6).

## Decisión
Adoptar una política de escalado en dos rutas:
1. `standard_hot_stable`: requiere estado caliente, score >= 78 y estabilidad de evaluaciones.
2. `fast_track_explicit_buy`: permite escalado rápido con score >= 90 e intención de compra explícita reciente.

Además:
- hard-stops por bloqueo/opt-out/score insuficiente,
- cooldown por conversación,
- dedupe por huella temporal,
- guardia de contradicción para forzar revisión manual,
- payload mínimo de handoff contractual y trazable.

## Consecuencias
### Positivas
- Menos sobre-escalado por picos o duplicados.
- Handoffs más accionables y auditables.
- Base contractual segura para integración en F6.

### Costes
- Mayor complejidad de reglas operativas.
- Más validaciones antes de crear handoff.

## Alternativas consideradas
1. Escalar solo por score >= 75.
   - Rechazada: más sensible a ruido y oscilaciones.
2. Escalar solo por intención explícita.
   - Rechazada: mayor riesgo de falsos positivos por contexto ambiguo.

## Relación con fases
- Depende de F1-F4.
- Habilita implementación operativa en F6 sin romper contratos previos.
