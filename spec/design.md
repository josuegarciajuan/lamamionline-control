# Design · Fase 1 + Fase 2

## PRF-IDENTIDAD-FOTO-2026 — Diseño por fases

### F01 — Baseline y métricas

#### KPIs definidos
1. **Identity Similarity Score (0-100)** — Parecido percibido con la referencia (likeness_score actual + métricas complementarias).
2. **Silhouette Consistency (0-100)** — Conservación de complexión, volumen y proporciones corporales.
3. **Background Coherence (0-100)** — Coherencia fondo-entorno respecto a la referencia (extensión vs sustitución).
4. **Realism Artifact Rate (%)** — Tasa de artefactos IA (piel plástica, cartoon, CGI, manos mal).
5. **Hand Anatomy Confidence (0-100)** — Calidad anatómica de manos visibles.
6. **Composition Consistency (0-100)** — Relación sujeto-entorno y encuadre coherente.

#### Umbrales de calidad
- **Apto para final**: Identity ≥ 50, Silhouette ≥ 50, Background ≥ 50, Artifact Rate < 15%, Hand ≥ 60.
- **Rechazo automático**: Identity < 30 (independientemente de otras métricas).
- **Warning**: Identity 30-49 → requiere revisión manual antes de pasar a final.

#### Muestra baseline
- 43 jobs totales, 39 con candidatas, 156 candidatas generadas.
- 39 jobs con evaluaciones OpenAI completas.

### F02 — Rearquitectura del prompt

#### Arquitectura de capas (implementada)
El prompt maestro de Pollo ahora tiene 14 capas etiquetadas con prioridad:

| Capa | Etiqueta | Contenido | Compresible |
|---|---|---|---|
| CAPA-1-ID | `[CAPA-1-ID PRIORIDAD#1 PARECIDO A LA ORIGINAL]` | Identidad facial/corporal, rasgos, complexión | **NO** (locked) |
| CAPA-2-OP | `[CAPA-2-OP BRIEF LIBRE]` | Texto del operador | Sí (important) |
| CAPA-3-CPX | `[CAPA-3-CPX COMPLEXIÓN]` | Complexión exacta, anti-adelgazamiento | **NO** (locked) |
| CAPA-4-OUT | `[CAPA-4-OUT ROPA Y ESTILO]` | Outfit, tejidos, estilo | Sí |
| CAPA-5-POSE | `[CAPA-5-POSE POSE Y ACTITUD]` | Pose, expresión | Sí |
| CAPA-6-SELF | `[CAPA-6-SELF SELFIE]` | Modo selfie (si aplica) | Sí |
| CAPA-7-FRM | `[CAPA-7-FRM ENCUADRE]` | Encuadre | Sí |
| CAPA-8-AMB | `[CAPA-8-AMB AMBIENTE]` | Fondo/entorno | Sí |
| CAPA-9-LUZ | `[CAPA-9-LUZ LUZ Y ACABADO]` | Iluminación, realismo | Sí (important) |
| CAPA-10-CAL | `[CAPA-10-CAL CALIDAD Y FONDOS]` | Calidad, anti-artefactos | Sí (important) |
| CAPA-11-SEG | `[CAPA-11-SEG SEGURIDAD]` | Glamour editorial, no explícito | Sí (important) |
| CAPA-12-RES | `[CAPA-12-RES RESTRICCIONES]` | Restricciones adicionales | Sí |
| CAPA-13-VAR | `[CAPA-13-VAR VARIEDAD DE ROPA]` | Variedad entre imágenes | Sí |
| CAPA-NEG | `[CAPA-NEG NEGATIVOS UNIFICADOS]` | Negativos unificados | Sí |

#### Detector de contradicciones
Función `publicista_detect_prompt_contradictions()` detecta 6 conflictos:
1. Selfie + fondo exterior
2. Protagonismo alto + plano lejano
3. Fondo estudio vs doméstico
4. Luz natural vs artificial coloreada
5. Vestido + vaqueros en misma imagen
6. Complexión delgada vs corpulenta

#### Compactación robusta
Función `publicista_pollo_compact_smart()`:
- Las capas locked (CAPA-1-ID, CAPA-3-CPX) nunca se truncan.
- Las capas important (CAPA-2-OP, CAPA-10-CAL, CAPA-11-SEG) se truncan al final.
- El resto se trunca primero.
- Se integra como fallback entre GPT compaction y hard_cap.

#### Negativos unificados
Función `publicista_pollo_negative_block()`: 8 categorías de términos prohibidos unificados.

#### Métrica de retención
Función `publicista_pollo_measure_constraint_retention()`: mide % de constraints preservadas por categoría (capas, identidad, prohibiciones, realismo, iluminación, fondo, seguridad).

#### Seguridad
- CAPA markers solo se detectan al inicio de sección (previene inyección via operator_brief).
- Los hallazgos de seguridad preexistentes (operator_brief sin validación, CSRF ausente en save_job) se documentan para F03.

### Decisiones de diseño F01
- Usar datos históricos reales de `publicista_jobs.json` para baseline.
- No implementar cambios de código en F01 (solo medición).
- Las métricas se extraen de las evaluaciones existentes y se complementan con análisis heurístico.

## Decisiones Fase 1

## Decisiones Fase 1
1. **Compacción fuera de bootstrap**
   - `bootstrap_storage()` ya no ejecuta compactación pesada.
   - Nuevo entrypoint operativo: `cron_mantenimiento.php`.

2. **GET sin efectos laterales en avisos**
   - `render_avisos_panel()` solo renderiza.
   - Marcado de leídos pasa a acción explícita POST.

3. **Snapshot de avisos activos**
   - Nuevo derivado: `data/avisos_active_snapshot.json`.
   - Se refresca en cada escritura de `avisos.json`.
   - Fallback: regeneración automática si falta/corrupto.

## Decisiones Fase 2
1. **Capa DB común (`app/db.php`)**
   - Conexión PDO cacheada a `telefonosbd`, configurable por variables de entorno `CRM_DB_*`.
   - Helpers de consulta (`crm_db_query_all`, `crm_db_query_one`, `crm_db_execute`).
   - Introspección de schema: `crm_db_table_exists`, `crm_db_table_columns`.

2. **Backend de almacenamiento configurable (`app/storage.php`)**
   - Tres modos: `json` (por defecto), `dual` (lectura MySQL→fallback JSON, escritura dual), `mysql`.
   - Modo gobernado por `CRM_STORAGE_BACKEND` > `settings.storage_backend` > `json`.
   - Mapa declarativo de 37 archivos → tablas `crm_*` en `storage_mysql_file_spec()`.
   - Tres patrones de entidad: `rows_by_id`, `singleton`, `scalar_list`.

3. **Idempotencia en escrituras MySQL**
   - Todas las escrituras usan `INSERT ... ON DUPLICATE KEY UPDATE`.
   - Normalización automática de tipos (int, decimal, datetime, date, JSON, teléfono).

4. **Backfill idempotente (`tools/phase2_backfill.php`)**
   - CLI-only. Recorre 37 archivos JSON y escribe MySQL vía `storage_mysql_write`.
   - Registro de ejecuciones en tabla `crm_migration_runs`.
   - Filtro por archivo con `--only=archivo.json`.
   - Correcciones específicas: `telefonos.waha` → 0, `eurekas.descripcion` truncado a 60k.

5. **Validación de paridad (`tools/phase2_parity_check.php`)**
   - CLI-only. Compara conteos e IDs JSON ↔ MySQL.
   - Reporte JSON en `data/phase2_parity_report_*.json`.
   - Exit code distinto de cero si hay divergencia.

6. **Schema Fase 2 (`tools/phase2_apply_schema.php`)**
   - Nueva tabla `crm_comercial_ai_memory`.
   - ALTERs de tipos de columna (LONGTEXT, VARCHAR(64)).
   - 10 índices compuestos adicionales para publicista, comercial y avisos.
   - Tabla `crm_migration_runs` para trazabilidad de backfills.

## Tradeoffs
- Más escritura controlada en mutaciones de avisos (refresco snapshot) para reducir coste de lecturas repetidas (Fase 1).
- Complejidad adicional en `storage.php` (~1100 líneas de funciones `storage_mysql_*`) a cambio de migración sin corte (Fase 2).
- No se imponen foreign keys en MySQL durante Fase 2 para evitar bloqueos operativos durante la convivencia dual.
- JSON sigue siendo backend operativo por defecto; el switch a `dual` o `mysql` es manual y verificable.

## Diseño CX2-F1 — Modelo de interés real para bot comercial

### Objetivo de diseño
Definir un modelo simple, explicable y trazable para clasificar interés comercial real antes de cualquier automatización avanzada, reduciendo ambigüedad en priorización de leads.

### Entidades lógicas (agnóstico de implementación)
1. **LeadConversation**
   - Identificador de conversación/lead.
   - Canal y contexto mínimo operativo.
2. **InterestSignal**
   - Evento observable que aporta evidencia (positiva, neutra o negativa).
   - Incluye tipo de señal, timestamp y metadatos mínimos.
3. **InterestAssessment**
   - Resultado evaluado sobre la conversación:
     - `estado_interes` (canónico)
     - `score_interes` (0-100)
     - `motivo_principal` (razón resumida)
     - `escalado_recomendado` (sí/no)
4. **EscalationDecision**
   - Decisión de escalado derivada de reglas explícitas.
   - Debe conservar el porqué (regla/umbral disparado).

### Estado canónico (CX2-F1)
- `sin_datos`: no hay señales suficientes.
- `frio`: señales débiles o no concluyentes.
- `templado`: interés incipiente, requiere seguimiento.
- `caliente`: interés claro y accionable comercialmente.
- `descartado`: rechazo explícito o no viable.

### Modelo de score (CX2-F1)
- Rango entero: **0..100**.
- Interpretación operativa inicial:
  - 0..19: descartable/ruido.
  - 20..49: bajo interés.
  - 50..74: interés medio.
  - 75..100: interés alto.
- Reglas base:
  - El score siempre debe ser explicable por señales observables.
  - Cambios bruscos requieren `motivo_principal` explícito.
  - Ausencia prolongada de señal positiva puede degradar score (sin valores concretos aún).

### Reglas de escalado (visión F1)
- Escalado recomendado cuando:
  1. Estado `caliente`, o
  2. Score en tramo alto con intención explícita detectada.
- No escalar automáticamente cuando:
  - Estado `descartado`.
  - Señales contradictorias sin confirmación.

### Trazabilidad requerida
- Cada evaluación debe poder responder:
  1. Qué señales entraron.
  2. Qué estado/score resultó.
  3. Qué regla justificó (o bloqueó) el escalado.

### Fuera de alcance en CX2-F1
- Entrenamiento de modelos predictivos.
- Optimización por canal o segmento.
- Automatización completa del handoff humano.

## Diseño CX2-F2 — Señales y normalización

### Objetivo de diseño
Definir un marco único para convertir entradas heterogéneas (mensajes, silencios y eventos de conversación) en señales comparables y trazables para el scoring de CX2-F3.

### Catálogo prioritario de señales (v1)
Canal inicial prioritario: **WhatsApp** con nomenclatura `<canal>.<evento>`.

1. Señales positivas:
   - `wa.intent_buy_explicit`
   - `wa.intent_price_request`
   - `wa.intent_availability_request`
   - `wa.intent_followup_accept`
   - `wa.reply_fast`
2. Señales neutras:
   - `wa.question_generic`
   - `wa.message_ack`
   - `wa.silence_timeout_soft`
   - `wa.intent_postpone`
3. Señales negativas:
   - `wa.intent_not_interested`
   - `wa.price_objection`
   - `wa.competitor_mention`
   - `wa.silence_timeout_hard`
4. Señales de bloqueo:
   - `wa.block_keyword`
   - `wa.opt_out_request`
   - `wa.abusive_content`

### Taxonomía canónica
- Clases permitidas (cerradas): `positiva`, `neutra`, `negativa`, `bloqueo`.
- Reglas de precedencia cuando coinciden señales en misma ventana:
  1. `bloqueo` domina.
  2. `negativa` domina sobre `neutra`.
  3. `positiva` domina sobre `neutra`.
  4. En empate semántico, prevalece la señal más reciente.

### Normalización mínima (agnóstica de runtime)
Contrato lógico de evento normalizado `InterestSignalNormalized`:
- Identidad/contexto: `signal_id`, `lead_id`, `conversation_id`, `channel`.
- Clasificación: `signal_type`, `signal_class`, `confidence`.
- Tiempos: `occurred_at`, `ingested_at` (UTC ISO-8601).
- Trazabilidad: `source`, `version`, `trace_id` (opcional).

Reglas mínimas:
1. Tipo desconocido => `*.unknown` + clase `neutra` + `confidence` acotada baja.
2. Dedupe conservador por `dedupe_key` en ventana corta.
3. Señales de bloqueo marcan la conversación como no auto-escalable hasta revisión humana.

### Seguridad y privacidad por diseño (F2)
1. Texto de usuario tratado como dato no confiable (nunca como instrucción de sistema).
2. Campos de trazas orientados a minimización de PII (sin logs completos por defecto).
3. Metadatos de origen sujetos a verificación de autenticidad en capa webhook (anti-spoofing/replay).

### Resultado esperado de F2
Disponibilidad de un contrato documental único para señales y normalización que permita implementar scoring en F3 sin ambigüedad semántica.

## Diseño CX2-F3 — Scoring inicial

### Objetivo de diseño
Definir un scoring inicial explicable y conservador para transformar señales normalizadas (F2) en `score_interes` (`0..100`) y estado operativo (F1), con degradación temporal y control de oscilaciones.

### Ponderaciones base por señal (v1)
Peso bruto antes de aplicar confianza y recencia:

| Clase | Señal | Peso base |
|---|---|---:|
| positiva | `wa.intent_buy_explicit` | +24 |
| positiva | `wa.intent_price_request` | +14 |
| positiva | `wa.intent_availability_request` | +12 |
| positiva | `wa.intent_followup_accept` | +10 |
| positiva | `wa.reply_fast` | +6 |
| neutra | `wa.question_generic` | +2 |
| neutra | `wa.message_ack` | +1 |
| neutra | `wa.intent_postpone` | -4 |
| neutra | `wa.silence_timeout_soft` | -6 |
| negativa | `wa.price_objection` | -10 |
| negativa | `wa.competitor_mention` | -8 |
| negativa | `wa.intent_not_interested` | -24 |
| negativa | `wa.silence_timeout_hard` | -16 |
| bloqueo | `wa.block_keyword` | dominancia |
| bloqueo | `wa.opt_out_request` | dominancia |
| bloqueo | `wa.abusive_content` | dominancia |
| neutra fallback | `<canal>.unknown` | 0 |

### Fórmula de scoring inicial (contract-friendly)
1. Contribución por señal: `contrib_i = peso_base_i * f_conf_i * f_recencia_i`.
2. Factor confianza: `f_conf_i = clamp(confidence_i, 0.30, 1.00)`.
3. Factor recencia (decaimiento exponencial):
   - `positiva`: semivida 120h,
   - `neutra`: semivida 72h,
   - `negativa`: semivida 240h.
4. Score previo: `score_raw = 35 + Σ(contrib_i) + penalizacion_inactividad`.
5. Penalización por inactividad: sin señales positivas en 72h => `-2` puntos por día (tope `-20`).
6. Score final: `score_interes = round(clamp(score_raw, 0, 100))`.

### Reglas de dominancia y anti-ruido
1. Si existe señal de clase `bloqueo` activa en ventana, forzar `estado_interes=descartado`, `score_interes=0`, `escalado_recomendado=false`.
2. Limitar contribución acumulada por subtipo en ventana para evitar picos por repetición.
3. Aplicar dedupe contractual de F2 antes del cálculo.

### Tramos y criterios de cambio
Tramos base:
- `bajo`: `0..49`
- `medio`: `50..74`
- `alto`: `75..100`

Mapeo de estado:
- `frio` => tramo bajo sin bloqueo activo.
- `templado` => tramo medio.
- `caliente` => tramo alto con al menos una señal de intención explícita reciente.

Histeresis conservadora para reducir flapping:
- subir a medio: `score >= 52`; bajar a bajo: `score <= 47`.
- subir a alto: `score >= 78`; bajar a medio: `score <= 72`.

### Resultado esperado de F3
Queda definido el cálculo base de score, su degradación temporal y el criterio de cambio de tramo de forma auditable y consistente con F1/F2.

## Diseño CX2-F4 — Persistencia y auditoría

### Objetivo de diseño
Añadir persistencia auditable de evaluaciones CX2 para conservar trazabilidad de decisiones sin alterar la lógica de scoring de F3.

### Entidades lógicas (F4)
1. **InterestAssessmentRecord**
   - Snapshot inmutable por evaluación.
   - Incluye estado, score, tramo, motivo, escalado y marcas temporales.
2. **InterestRuleTrace**
   - Evidencia de reglas aplicadas/no aplicadas y señales usadas.
3. **OperationalAuditStamp**
   - Metadatos de auditoría técnica: actor, origen, correlación, versión contractual y resultado de escritura.

### Estrategia de persistencia (agnóstica de backend)
- Registro lógico append-only de evaluaciones.
- El estado más reciente por conversación se deriva por `evaluated_at` más alto.
- Trazabilidad 1:N entre evaluación y reglas aplicadas.
- Compatible con backend `json|dual|mysql` de Fase 2.

### Retención mínima por conversación
- Mantener **mínimo 20 evaluaciones más recientes** por `conversation_id`.
- Mantener **mínimo 90 días de historial** por conversación.
- Si hay conflicto entre límites, prevalece la política que conserva más historial.

### Relación con fases previas
- **F1:** conserva estados canónicos y consistencia de transición.
- **F2:** referencia señales normalizadas como evidencia de decisión.
- **F3:** persiste versión de catálogo de pesos y factores aplicados.

### Seguridad y cumplimiento (F4)
1. Integridad del historial (inmutabilidad lógica y trazabilidad de cambios).
2. Minimización de PII y controles de retención por categoría de dato.
3. Auditoría de accesos y operaciones de lectura/exportación de evidencia.
4. No repudio operativo (actor, momento, acción y resultado verificables).

### Resultado esperado de F4
Disponibilidad de un historial auditable, retenido y consistente que permita investigar decisiones, explicar escalados y preparar controles operativos de F5.

## Diseño CX2-F5 — Escalado operativo

### Objetivo de diseño
Definir reglas operativas conservadoras para escalar a comercial humano con trazabilidad completa, reutilizando F1-F4 y sin cambios de runtime en esta fase.

### Umbrales definitivos de escalado
Se considera `escalado_operativo_candidato=true` si se cumple una de estas vías:

1. **standard_hot_stable**
   - `estado_interes=caliente`.
   - `score_interes >= 78`.
   - Al menos una señal explícita positiva en últimas `24h` (`wa.intent_buy_explicit|wa.intent_price_request|wa.intent_availability_request`).
   - Estabilidad mínima: 2 evaluaciones consecutivas en tramo alto (`score>=75`) separadas por >= `5m` dentro de una ventana de `60m`.

2. **fast_track_explicit_buy**
   - `score_interes >= 90`.
   - Señal `wa.intent_buy_explicit` en últimas `2h` con `confidence >= 0.80`.
   - Sin señal de bloqueo activa.

### Exclusiones absolutas
Nunca escalar automáticamente si:
- `estado_interes=descartado`.
- hay señal de clase `bloqueo` activa.
- hay `wa.opt_out_request` activa.
- `score_interes < 75`.

### Anti-ruido y anti-duplicado
1. **Cooldown**: máximo 1 handoff por `conversation_id` cada `24h`.
2. **Excepción controlada**: se permite nuevo handoff en cooldown solo si `delta_score >= +15` y aparece nueva intención explícita.
3. **Dedupe temporal**: suprimir candidatos repetidos por huella en ventana `30m`.
4. **Guardia de contradicción**: si coexisten `wa.intent_buy_explicit` y `wa.intent_not_interested` en `120m`, bloquear auto-handoff y exigir revisión manual.

### Payload mínimo de handoff a comercial
- Identidad/traza: `handoff_id`, `lead_id`, `conversation_id`, `assessment_id`, `trace_id`, `created_at`.
- Decisión: `estado_interes`, `score_interes`, `score_band`, `escalado_recomendado`, `escalado_operativo_candidato`, `regla_escalado_aplicada`, `motivo_principal`.
- Evidencia: `top_signals` (máx 3), `blocking_flags`, `last_positive_at`, `last_negative_at`.
- Contexto: `channel`, `last_user_message_excerpt` (sanitizado), `suggested_priority` (`alta|media`).

### Resultado esperado de F5
Quedan definidos umbrales, supresión de ruido/duplicados y contrato de handoff mínimo para activar integración operativa en F6 con bajo riesgo.

## Diseño CX2-F6 — Integración operativa en panel

### Objetivo de diseño
Integrar en panel comercial el estado/score de CX2 para priorización y habilitar confirmación/corrección humana con trazabilidad completa, sin recalcular scoring ni cambiar runtime en esta fase.

### Visualización operativa mínima
Cada ítem de conversación debe mostrar:
- `lead_id`, `conversation_id`, `assessment_id`, `trace_id`.
- `estado_interes`, `score_interes`, `score_band`.
- `escalado_recomendado`, `escalado_operativo_candidato`, `regla_escalado_aplicada`.
- `motivo_principal`, `top_signals` (máx 3), `blocking_flags`.
- `evaluated_at`, `last_positive_at`, `last_negative_at`.

Prioridad sugerida (solo lectura operativa):
- `alta`: candidato operativo verdadero y sin bloqueo.
- `media`: tramo alto sin candidato.
- `normal`: resto.

### Acciones humanas permitidas
1. `confirmar_handoff`: confirma recomendación para gestión humana.
2. `corregir_clasificacion`: corrige estado operativo visible en panel con motivo obligatorio.
3. `reabrir_revision`: reabre caso con nueva evidencia y referencia al override padre.

Regla clave: ninguna acción humana en F6 altera el cálculo histórico de `score_interes` ni la semántica de F1-F5.

### Trazabilidad de overrides manuales
Registrar en append-only lógico:
- `override_id`, `action_type`, `action_result`, `reason_code`, `reason_note`.
- `lead_id`, `conversation_id`, `assessment_id`, `parent_override_id`.
- `actor_id`, `actor_role`, `request_id`, `trace_id`, `idempotency_key`, `created_at`.

### Seguridad operativa (F6)
1. Acciones mutantes solo para roles autorizados.
2. Overrides con MFA reciente en acciones críticas.
3. Idempotencia obligatoria para evitar doble ejecución por reintentos.
4. Estados bloqueados deben fallar en cerrado (`BLOCKED|FROZEN|LOCKED`).
5. Notas y vistas con minimización de PII.

### Resultado esperado de F6
Queda definida una interfaz operativa única para panel, con acciones humanas controladas y auditables, lista para implementación técnica/instrumentación en F7.

## Diseño CX2-F7 — Calibración y guardrails

### Objetivo de diseño
Definir un ciclo de calibración periódica y guardrails medibles para reducir falsos positivos y sobre-escalado de CX2, sin introducir cambios de runtime en esta fase.

### Rutina de calibración periódica
1. **Semanal (operativa)**
   - Revisión por ventana UTC de evaluaciones cerradas.
   - Cálculo de métricas por canal y regla de escalado aplicada.
   - Estado de ciclo: `ok|warning|breach`.
2. **Mensual (calibración)**
   - Tendencia de 4 semanas.
   - Propuesta de ajuste de reglas/pesos con comparación contra baseline.
3. **Trimestral (gobierno)**
   - Consolidación de versiones vigentes.
   - Depuración de reglas obsoletas y actualización de objetivos operativos.

### Guardrails de riesgo
- **FP_rate objetivo semanal**: `<= 12%`.
- **FP_rate warning**: `>12%` y `<=18%`.
- **FP_rate breach**: `>18%`.
- **Over_escalation_rate objetivo**: `<=20%`.
- **Over_escalation_rate breach**: `>25%`.
- **Incidente crítico**: cualquier escalado con bloqueo activo u `opt_out_request` (esperado `0`).

Comportamiento:
- `warning`: congelar promoción automática y abrir revisión.
- `breach`: activar contención, análisis causa raíz y propuesta de rollback lógico de versión.

### Criterios de revisión por resultados
- Versiones controladas:
  - `scoring_model_version`
  - `signal_weight_catalog_version`
  - `escalation_policy_version`
- Decisiones permitidas: `promote|hold|rollback|discard`.
- `promote` requiere 2 ciclos semanales consecutivos en `ok` y sin regresión en exclusiones F5.
- `breach` en ventana evaluada invalida promoción.

### Resultado esperado de F7
Queda definido un marco de calibración y guardrails trazable para evolucionar reglas de CX2 con riesgo acotado y preparar cierre de aceptación en F8.

## Diseño CX2-F8 — Cierre y aceptación

### Objetivo de diseño
Cerrar formalmente la fase documental CX2 con métricas de éxito de negocio y operación, un checklist de consistencia documental completo (F1→F8) y criterios de aprobación de cierre con evidencias exigibles. Sin cambios de runtime en esta fase.

### Métricas de éxito de negocio (aceptación comercial)

1. **Tasa de precisión en escalado (`escalation_precision`)**
   - Definición: `handoffs_aceptados_por_comercial / total_handoffs_generados_por_cx2` en ventana de evaluación.
   - Objetivo mínimo: `>= 70%`.
   - Fuente de verdad: trazabilidad F5 (handoff creado) + F6 (confirmación humana `confirmar_handoff`).
   - Umbral de rechazo: `< 50%` sostenido en 2 ciclos de calibración F7.

2. **Cobertura de leads calientes detectados (`hot_lead_recall`)**
   - Definición: `leads_calientes_detectados_por_cx2 / total_leads_calientes_revisados` en ventana, donde "total revisados" incluye los escalados por vía operativa alternativa.
   - Objetivo mínimo: `>= 60%` en revisión mensual F7.
   - Nota: métrica dependiente de disponer de fuente externa de verificación (feedback operativo de F6).

3. **Impacto en tiempo de respuesta comercial (`time_to_handoff`)**
   - Definición: minutos transcurridos entre primera señal explícita positiva y `handoff_id` creado.
   - Objetivo operativo: mediana `<= 120 min` en franja operativa (08:00-20:00 UTC).
   - Fuente de verdad: `last_positive_at` en payload F5 + `created_at` de handoff.

4. **Ratio de sobre-escalado percibido (`perceived_over_escalation`)**
   - Definición: `handoffs_corregidos_como_no_interes / total_handoffs` en ventana, donde "corregidos" proviene de acción humana `corregir_clasificacion` en F6 con corrección a estado no-caliente/descartado.
   - Objetivo: `<= 15%`.
   - Complementa `over_escalation_rate` de F7 con perspectiva operativa humana.

5. **Tasa de bloqueo efectivo (`blocking_effectiveness`)**
   - Definición: ausencia de handoffs generados con señal de bloqueo activa (`signal_class=bloqueo` u `opt_out_request`).
   - Objetivo absoluto: `0 incidentes`.
   - Incumplimiento => `breach` inmediato según F7.

### Métricas de éxito de operación (aceptación técnica)

1. **Consistencia documental completa (`doc_consistency_score`)**
   - Definición: número de reglas contractuales F1-F8 verificadas sin contradicción / total de reglas verificables.
   - Objetivo: `100%` para cierre.
   - Herramienta: checklist F8 de consistencia (ver abajo).

2. **Cobertura de auditoría por evaluación (`audit_trail_completeness`)**
   - Definición: evaluaciones con `assessment_id` que poseen sello de auditoría completo (F4) y trazabilidad de reglas (F4).
   - Objetivo: `100%` de evaluaciones post-cierre documental.

3. **Integridad de versionado de artefactos (`artifact_version_integrity`)**
   - Definición: existencia de versiones canónicas documentadas para:
     - `scoring_model_version`
     - `signal_weight_catalog_version`
     - `escalation_policy_version`
     - `contract_version`
   - Objetivo: todos los artefactos con hash/referencia inmutable en el momento de cierre.

4. **Reproducibilidad de decisión (`decision_reproducibility`)**
   - Definición: capacidad de reconstruir cualquier `assessment_id` de muestra usando mismas señales, pesos y reglas versionadas.
   - Objetivo: `100%` de la muestra aleatoria de validación (mínimo 10 evaluaciones).

5. **Preparación para Fase 3 operativa (`phase3_readiness`)**
   - Definición: se cumplen todas las precondiciones documentales de CX2-F8 para que una futura implementación de runtime pueda realizarse sin ambigüedad.
   - Objetivo: checklist F8 completo al 100%.

### Checklist de consistencia documental final F1 → F8

Cada ítem debe verificarse como `OK`/`NC` (no conformidad) antes del cierre. Una NC bloquea la firma.

| # | Fase | Ítem de verificación | Evidencia requerida |
|---|---|---|---|
| 1 | F1 | Estados canónicos (`sin_datos|frio|templado|caliente|descartado`) son conjunto cerrado en design ↔ contracts. | Coincidencia literal entre `spec/design.md` §Diseño CX2-F1 y `spec/contracts.md` §Contratos CX2-F1. |
| 2 | F1 | Score en rango `0..100`, entero, coexiste con `motivo_principal` obligatorio en cambios de tramo. | Contracts F1 §Contrato de score de interés + §Reglas contractuales de transición. |
| 3 | F1 | Reglas de escalado F1 (`caliente` o `score>=75`+intención) consistentes con F5. | Contracts F1 §Reglas contractuales de escalado vs. Contracts F5 §Contrato de elegibilidad. Sin contradicción. |
| 4 | F2 | Catálogo de señales cerrado (`positiva|neutra|negativa|bloqueo`), mismo en design y contracts. | Design F2 §Taxonomía canónica ↔ Contracts F2 §Taxonomía de señales. |
| 5 | F2 | `InterestSignalNormalized` tiene campos obligatorios idénticos en design y contracts. | Design F2 §Normalización mínima ↔ Contracts F2 §Contrato de señal normalizada. |
| 6 | F2 | Señales de bloqueo (`block_keyword|opt_out_request|abusive_content`) fuerzan `descartado` en F1/F3/F5 consistentemente. | Contracts F2 §Reglas de precedencia + Contracts F3 §Contrato de dominancia de bloqueo + Contracts F5 §Exclusiones contractuales. |
| 7 | F3 | Pesos base por señal de design coinciden con valores en contracts (misma tabla semántica). | Design F3 §Ponderaciones base vs. Contracts F3 §Contrato de ponderaciones base. |
| 8 | F3 | Fórmula de scoring (`contrib_i`, `score_raw`, `score_interes`) sin ambigüedad entre design y contracts. | Design F3 §Fórmula de scoring ↔ Contracts F3 §Contrato de cálculo de `score_interes`. |
| 9 | F3 | Semividas de recencia (`positiva=120h`, `neutra=72h`, `negativa=240h`) iguales en design y contracts. | Comparación literal en ambos documentos. |
| 10 | F3 | Histeresis (`52/47` medio↔bajo, `78/72` alto↔medio) igual en design y contracts. | Design F3 §Tramos + Contracts F3 §Contrato de tramos y transición. |
| 11 | F3 | Dominancia de bloqueo (`estado=descartado`, `score=0`, `escalado=false`) idéntica en design y contracts. | Design F3 §Reglas de dominancia ↔ Contracts F3 §Contrato de dominancia de bloqueo. |
| 12 | F4 | `InterestAssessmentRecord` tiene mismo conjunto de campos obligatorios en design y contracts. | Design F4 §Estrategia de persistencia ↔ Contracts F4 §Contrato de persistencia de evaluación. |
| 13 | F4 | Retención mínima (20 evaluaciones / 90 días) idéntica en design y contracts. | Design F4 §Retención mínima ↔ Contracts F4 §Contrato de retención mínima. |
| 14 | F4 | `OperationalAuditStamp` con campos obligatorios completos en contracts y referenciado en design. | Contracts F4 §Contrato de auditoría operativa. |
| 15 | F5 | `standard_hot_stable` y `fast_track_explicit_buy` con mismos umbrales en design y contracts. | Design F5 §Umbrales definitivos ↔ Contracts F5 §Contrato de elegibilidad. |
| 16 | F5 | Hard-stops (`descartado`, `bloqueo`, `opt_out`, `score<75`) coinciden en design y contracts. | Design F5 §Exclusiones absolutas ↔ Contracts F5 §Exclusiones contractuales. |
| 17 | F5 | Reglas anti-ruido (cooldown 24h, dedupe 30m, delta +15, guardia 120m) idénticas en design y contracts. | Design F5 §Anti-ruido ↔ Contracts F5 §Contrato anti-ruido. |
| 18 | F6 | `PanelOperationalView` con mismos campos obligatorios en design y contracts. | Design F6 §Visualización operativa ↔ Contracts F6 §Contrato de visualización. |
| 19 | F6 | Acciones humanas (`confirmar_handoff|corregir_clasificacion|reabrir_revision`) mismo enum cerrado en ambos documentos. | Design F6 §Acciones humanas ↔ Contracts F6 §Contrato de acciones humanas. |
| 20 | F6 | Overrides no modifican score/estado histórico. Misma regla en design y contracts. | Design F6 (regla clave de no alteración) ↔ Contracts F6 (regla 5 de acciones humanas). |
| 21 | F7 | Métricas de guardrails (`fp_rate`, `over_escalation_rate`, `blocked_escalation_incidents`) con mismos umbrales en design y contracts. | Design F7 §Guardrails de riesgo ↔ Contracts F7 §Contrato de métricas y guardrails. |
| 22 | F7 | Ciclos (semanal/mensual/trimestral) con mismas reglas de decisión (`promote|hold|rollback|discard`) en design y contracts. | Design F7 §Rutina de calibración + §Criterios de revisión ↔ Contracts F7 §Contrato de ciclo + §Contrato de revisión. |
| 23 | F7 | `promote` requiere 2 ciclos semanales en `ok` y `breach` invalida promoción. Coincidencia en design y contracts. | Design F7 §Criterios de revisión ↔ Contracts F7 §Contrato de revisión/versionado. |
| 24 | F4-F7 | Seguridad contractual (no-trust input, minimización PII, no repudio, idempotencia, MFA, 4-eyes, tamper-evident, integridad de artefactos) presente en todas las fases donde aplica. | Cada sección de seguridad en contracts F2-F7. |
| 25 | F8 | Métricas de éxito documentadas en design y referenciadas en contracts. | Esta sección en design ↔ contracts F8. |
| 26 | F8 | Checklist de consistencia documental completo y trazable. | Esta tabla. Cada ítem con evidencia cruzada. |
| 27 | General | `requirements.md` refleja correctamente el alcance de CX2-F8. | `spec/requirements.md` §Bloque CX2 lista F1-F8 con descripción de F8. |
| 28 | General | `tasks.md` refleja todas las tareas CX2-F1..F8 completadas o en estado correcto. | `spec/tasks.md` §CX2-F8. |
| 29 | General | `changelog.md` contiene entradas para F1-F8. | `docs/changelog.md`. |
| 30 | General | ADRs de CX2 (003-008) existen y están en estado `Aprobada`. | `docs/adr/ADR-003` a `ADR-008`. |

### Criterios de aprobación de cierre con evidencias

El cierre de CX2 se aprueba cuando se cumplen **todas** las condiciones siguientes. Cada condición requiere evidencia verificable.

#### A. Aprobación de negocio

| Condición | Umbral | Evidencia |
|---|---|---|
| A1. Métricas de negocio simuladas/revisadas sobre datos históricos | `escalation_precision >= 70%`, `perceived_over_escalation <= 15%`, `blocking_effectiveness = 0 incidentes` en muestra representativa (mínimo 50 conversaciones) | Informe de simulación con trazabilidad de evaluaciones F4 y handoffs F5 sobre datos históricos, firmado por responsable comercial. |
| A2. Alineación con flujo operativo actual | No hay contradicción entre el modelo CX2 y el proceso comercial vigente | Checklist de validación operativa firmado por responsable de operaciones. |
| A3. Criterios de escalado revisados y aceptados por comercial | Umbrales F5 aprobados explícitamente | Acta de revisión con visto bueno de responsable comercial. |

#### B. Aprobación técnica

| Condición | Umbral | Evidencia |
|---|---|---|
| B1. Checklist de consistencia documental | 30/30 ítems en `OK` | Esta tabla completada y firmada. |
| B2. Reproducibilidad de decisión | 10/10 evaluaciones de muestra reconstruibles con mismas señales + reglas | Informe de reproducibilidad con `assessment_id`, señales de entrada, score resultante y hash de versión de reglas. |
| B3. Integridad de versionado | `scoring_model_version`, `signal_weight_catalog_version`, `escalation_policy_version`, `contract_version` fijados con hash inmutable en el momento de cierre | Manifiesto de versiones (`data/cx2_closure_manifest.json`). |
| B4. Precondición Fase 3 operativa | Ningún contrato CX2 bloquea la implementación de runtime | Declaración de readiness firmada por arquitectura. |
| B5. Seguridad contractual validada | Todos los controles de F2-F7 revisados sin hallazgos bloqueantes | Informe de revisión de seguridad contractual. |

#### C. Aprobación de gobierno

| Condición | Umbral | Evidencia |
|---|---|---|
| C1. ADR-008 aprobada | Estado `Aprobada` | ADR-008 en `docs/adr/`. |
| C2. Cierre firmado | Firma dual (comercial + arquitectura) | Acta de cierre CX2 con fecha, firmantes y hash de integridad documental. |
| C3. Trazabilidad de cierre | `closure_id` único generado, inmutable y registrado | Registro en `data/cx2_closure_manifest.json` con `closure_id`, `closed_at`, `approved_by[]`, `document_hash`. |

### Manifiesto de cierre (`cx2_closure_manifest.json`)

Estructura mínima del artefacto de cierre:

- `closure_id`, `closed_at` (ISO-8601 UTC), `approved_by` (array de firmantes).
- `contract_versions`: `scoring_model_version`, `signal_weight_catalog_version`, `escalation_policy_version`, `contract_version`.
- `document_hashes`: SHA-256 de `spec/design.md`, `spec/contracts.md`, `spec/requirements.md`, `spec/tasks.md` en el momento de cierre.
- `checklist_result`: resumen del checklist de 30 ítems (`ok_count`, `nc_count`, `nc_items`).
- `business_metrics_summary`: snapshot de métricas A1-A3 con `sample_size`, `evaluation_window`, `result` y `pass`.
- `technical_approval_summary`: snapshot de métricas B1-B5 con `result` y `pass`.
- `governance_summary`: referencia a ADR-008, `closure_id` y firmas digitales.

### Fuera de alcance en CX2-F8
- Implementación de runtime de CX2 (pertenece a fase posterior, post-cierre documental).
- Ejecución real de calibración F7 sobre datos vivos (F7 es marco; F8 solo exige simulaciones sobre históricos).
- Cambios en código existente del CRM (`index.php`, `app/*.php`, cron, webhooks).
- Migración de datos reales a tablas CX2.

### Resultado esperado de F8
Queda cerrada la fase documental de CX2 con métricas de aceptación claras, un checklist de consistencia verificable y criterios de aprobación con evidencias trazables. El bloque CX2 queda listo para implementación técnica de runtime en fase posterior, con bajo riesgo de ambigüedad contractual.
