# ADR-001 · Fase 1 Rendimiento Inmediato

## Estado
Aprobada

## Contexto
El sistema presentaba lentitud global por trabajo pesado en request web y full-scan repetidos de avisos.

## Decisión
1. Sacar compactación de bootstrap a cron de mantenimiento.
2. Eliminar escrituras en GET del panel de avisos.
3. Introducir snapshot derivado de avisos activos para lecturas frecuentes.

## Consecuencias
- Menor latencia en navegación normal.
- Menor contención de disco por escrituras implícitas.
- Complejidad adicional mínima en sincronización de snapshot, mitigada con regeneración automática fallback.
