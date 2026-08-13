# ADR-008: CX2-F8 — Cierre formal del bloque CX2

## Estado
Aprobada (2026-05-11)

## Contexto
Tras completar F1-F7 con cobertura documental completa (estados, señales, scoring, persistencia, escalado, integración en panel y calibración), se requiere formalizar la aceptación del bloque CX2 como paquete especificativo listo para implementación.

## Decisión
Adoptar un cierre formal con:
- métricas cuantitativas de negocio y operación,
- checklist de consistencia documental de 30 ítems F1-F8,
- aprobación con firma dual (comercial + arquitectura),
- manifiesto inmutable (`cx2_closure_manifest.json`) con hash SHA-256 y versiones congeladas.

Se decide que una sola `NC` en el checklist bloquea la firma, y que la reapertura requiere un nuevo `acceptance_id`.

## Consecuencias
### Positivas
- Trazabilidad completa del proceso de aceptación.
- Puente claro entre fase documental y fase de implementación runtime.
- Base de evidencias verificable para auditoría externa.

### Costes
- Proceso formal con requisitos de aprobación dual.
- Mantenimiento del manifiesto de cierre ante cambios futuros.

## Alternativas consideradas
1. Cierre sin checklist formal.
   - Rechazada: riesgo de inconsistencias no detectadas entre fases.
2. Aprobación unilateral.
   - Rechazada: insuficiente segregación de funciones para decisiones de impacto.

## Relación con fases
- Depende de F1-F7.
- Habilita transición a implementación de runtime con cero deuda especificativa.
