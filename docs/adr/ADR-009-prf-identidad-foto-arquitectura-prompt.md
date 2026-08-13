# ADR-009: Arquitectura de prompt en capas para generación Publicista con Pollo.ai

**Estado**: Aprobada  
**Fecha**: 2026-05-23  
**Programa**: PRF-IDENTIDAD-FOTO-2026

## Contexto

El sistema Publicista genera imágenes de candidatas mediante Pollo.ai (text-to-image). El baseline (F01) mostraba que el 58.3% de las imágenes finales provenían de candidatas con score < 30, y el score medio era 28.5/100. Los issues reportados incluían: bajo parecido con la original, iluminaciones artificiales rosas, fondos que sustituían la escena en vez de extenderla, y anatomía deficiente.

## Decisión

Se adopta una **arquitectura de prompt en 14 capas etiquetadas** con prioridad explícita, implementada en `publicista_build_pollo_master_prompt()`:

| Capa | Prioridad | Compresible |
|---|---|---|
| CAPA-1-ID (identidad) | Locked | NO |
| CAPA-2-OP (brief operador) | Important | Sí |
| CAPA-3-CPX (complexión) | Locked | NO |
| CAPA-4-OUT a CAPA-NEG | Normal | Sí |

### Mecanismos complementarios
- **Compactación smart** (`publicista_pollo_compact_smart`): locked sections nunca truncadas, important al final.
- **Anti-contradicciones** (`publicista_detect_prompt_contradictions`): 6 reglas.
- **Negativos unificados** (`publicista_pollo_negative_block`): 8 categorías.
- **Extend scene**: directiva en 5 ubicaciones del prompt.
- **Gates de identidad** (`meets_minimum_threshold`): likeness<30 = hard reject.
- **Selección unificada** (`rebuild_finals_from_candidates`): ruta única con gates.

## Consecuencias

### Positivas
- La identidad y complexión son inmunes a la compactación (locked).
- Las contradicciones se detectan antes de generar.
- Ninguna candidata con likeness<30 puede ser final.
- La escena se extiende naturalmente, no se sustituye.

### Negativas
- Mayor longitud de prompt (más tokens en compactación).
- Complejidad añadida: 4 nuevas funciones + 14 capas que mantener.
- Los gates pueden reducir el yield de finales por job (mitigado con auto-regen).

### Riesgos
- Si Pollo.ai cambia su API o límites de prompt, la arquitectura de capas necesitará ajuste.
- La efectividad de las capas depende de que el modelo respete el orden de prioridad.

## Alternativas consideradas

1. **Prompt plano sin capas** (estado anterior): causaba pérdida de identidad en compactación y contradicciones. Descartada por los resultados del baseline.
2. **Generación con referencia visual** (gpt-image-1 /v1/images/edits): añadía efectos artificiales (iluminaciones rosas, piel plastificada). Descartada en F01.
3. **Prompt mínimo ultra-compacto**: sacrificaba variedad de ropa/pose/fondo. Descartada por requisitos de negocio.

## Validación

La efectividad de esta arquitectura se validará mediante los experimentos A/B definidos en F06, comparando contra el baseline de F01 (score medio 28.5 → target ≥50).
