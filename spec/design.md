# Design · Fase 1 + Fase 2

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
