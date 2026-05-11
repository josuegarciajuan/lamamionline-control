# ADR-004 · CX2-F3 Scoring inicial

## Estado
Aprobada

## Contexto
Tras definir estados de interés (CX2-F1) y señales normalizadas (CX2-F2), faltaba un método consistente para convertir señales en un score operativo y estable, evitando oscilaciones y escalados por ruido.

## Decisión
1. Se adopta un scoring aditivo con base fija y contribuciones por señal ponderadas por confianza y recencia.
2. Se define decaimiento temporal por clase de señal mediante semividas distintas.
3. Se incorpora penalización de inactividad para conversaciones sin refuerzo positivo reciente.
4. Se mantiene dominancia contractual de señales de bloqueo (`descartado`, score `0`, sin escalado).
5. Se establecen tramos de score (bajo/medio/alto) e histéresis para reducir flapping entre estados.
6. Se añaden controles contractuales anti-abuso para scoring: dedupe/idempotencia, límite de contribución por repetición y trazabilidad de fórmula/versionado.

## Consecuencias
- El modelo es explicable, auditable y calibrable sin romper contratos previos.
- Disminuye el riesgo de falsos positivos por eventos aislados o spam de señales.
- Queda preparado el terreno para CX2-F4 (persistencia y auditoría) y CX2-F5 (escalado operativo) con un score estable.
