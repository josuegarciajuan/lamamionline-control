# ADR-003 · CX2-F2 Señales y normalización

## Estado
Aprobada

## Contexto
CX2-F1 definió estados de interés y score, pero faltaba una base homogénea de entrada para que futuras fases de scoring no dependan de eventos ambiguos o semántica inconsistente por canal.

## Decisión
1. Se define una taxonomía cerrada de clases de señal: `positiva|neutra|negativa|bloqueo`.
2. Se adopta convención de tipo de señal `<canal>.<evento>` (v1 centrada en WhatsApp).
3. Se introduce contrato lógico `InterestSignalNormalized` con campos obligatorios de identidad, clase, confianza, tiempo y trazabilidad.
4. Se fijan reglas de precedencia entre clases para resolver conflictos en la misma ventana de evaluación.
5. Se formalizan controles contractuales mínimos de seguridad y privacidad:
   - no-trust input,
   - minimización de PII en trazas,
   - no escalado inducible por texto libre,
   - precondición anti-spoofing/replay en ingestión.

## Consecuencias
- El scoring de CX2-F3 podrá operar sobre una entrada estable y comparable.
- Se reduce el riesgo de falsos positivos por variación de vocabulario de señales.
- La trazabilidad de decisiones mejora al existir metadatos obligatorios en cada señal.
- Se añade disciplina de seguridad temprana sobre eventos conversacionales, antes de automatizar más el escalado.
