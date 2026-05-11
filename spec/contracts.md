# Contracts

## Contratos funcionales Fase 1

### Avisos
- `GET` de dashboard/avisos **no** debe modificar `data/avisos.json`.
- Nueva acción POST: `action=mark_avisos_read`
  - Inputs:
    - `scope=active_unread` (modo soportado en Fase 1)
    - `redirect` (opcional)
    - `csrf_token` (obligatorio)
  - Output:
    - Redirección con flash de estado.

### Seguridad formulario (avisos)
- Para acciones de avisos en Fase 1 se exige `csrf_token` válido:
  - `dismiss_aviso`
  - `create_manual_aviso`
  - `delete_planned_aviso`
  - `mark_avisos_read`

### Persistencia derivada
- Archivo nuevo: `data/avisos_active_snapshot.json`
  - Campos esperados:
    - `generated_at`
    - `active_rows`
    - `active_ids`
    - `exists_engine_source_any`

### Mantenimiento
- Script nuevo: `cron_mantenimiento.php` (CLI-only)
  - Ejecuta compactación de almacenamiento fuera de petición web.

## Contratos de infraestructura Fase 2

### Conexión MySQL (`app/db.php`)
- `crm_db()`: devuelve `PDO|null` cacheado a `telefonosbd`.
- Configuración por variables de entorno: `CRM_DB_HOST`, `CRM_DB_NAME`, `CRM_DB_USER`, `CRM_DB_PASS`, `CRM_DB_CHARSET`.
- `crm_db_table_exists($table)` y `crm_db_table_columns($table)`: introspección de schema.

### Backend de almacenamiento (`app/storage.php`)
- `storage_backend_mode()`: retorna `json` | `dual` | `mysql`.
  - Fuentes de configuración en orden: `CRM_STORAGE_BACKEND` (env) → `settings.storage_backend` → `json`.
- `storage_read($file)`: según modo, lee de MySQL, JSON, o MySQL con fallback JSON.
- `storage_write($file, $data)`: en `dual`/`mysql` escribe MySQL; en `json`/`dual` siempre escribe también JSON. `settings.json` siempre escribe JSON.
- `storage_upsert($file, $row)`: upsert en MySQL si aplica + upsert JSON directo.
- `storage_delete($file, $id)`: delete en MySQL si aplica + delete JSON directo.
- Mapa de archivos: `storage_mysql_file_spec($file)` devuelve `{kind, table, key_column, json_column, key_value}` para 37 archivos.

### Scripts Fase 2 (CLI-only)

#### `tools/phase2_backfill.php`
- **Propósito**: backfill idempotente JSON → MySQL.
- **Precondición**: tablas `crm_*` existentes.
- **Entrada**: `--only=archivo.json` (opcional).
- **Proceso**: lee JSON con `storage_json_read_direct`, escribe MySQL con `storage_mysql_write`, registra en `crm_migration_runs`.
- **Salida**: `OK archivo.json -> tabla (N)` o `FAIL`. Exit code 0 si todo OK.
- **Correcciones específicas**: `telefonos.waha` → 0, `eurekas.descripcion` truncado a 60k.

#### `tools/phase2_parity_check.php`
- **Propósito**: validar paridad de conteos e IDs entre JSON y MySQL.
- **Salida**: `OK archivo.json | json=N mysql=M` o `FAIL`. Reporte JSON en `data/phase2_parity_report_*.json`.
- **Exit code**: 0 si paridad total, 1 si hay divergencias.

#### `tools/phase2_apply_schema.php`
- **Propósito**: aplicar ajustes finales de schema e índices Fase 2.
- **Operaciones**: tabla `crm_comercial_ai_memory`, ALTERs de columna, 10 índices compuestos, tabla `crm_migration_runs`.
- **Idempotencia**: verifica existencia antes de crear.

### Modo dual — comportamiento esperado
- **Lectura**: preferencia MySQL con fallback JSON automático.
- **Escritura**: escribe MySQL y JSON; si MySQL falla, JSON sigue funcionando.
- **Configuración**: `export CRM_STORAGE_BACKEND=dual` o `storage_backend: "dual"` en `settings.json`.

## Contratos de comportamiento CX2-F1

### Estados canónicos de interés
- Conjunto cerrado permitido:
  - `sin_datos`
  - `frio`
  - `templado`
  - `caliente`
  - `descartado`
- Cualquier estado fuera de este conjunto se considera inválido.

### Contrato de score de interés
- Campo: `score_interes`.
- Tipo: entero.
- Rango válido: `0..100`.
- Reglas:
  - El score debe coexistir siempre con `estado_interes`.
  - El score nunca puede almacenarse sin `motivo_principal` cuando hay cambio de tramo (bajo/medio/alto).

### Contrato de evaluación mínima
- Una evaluación de CX2-F1 debe incluir, como mínimo:
  - `lead_id` (o identificador equivalente de conversación)
  - `estado_interes`
  - `score_interes`
  - `motivo_principal`
  - `timestamp_evaluacion`

### Reglas contractuales de transición de estado
- Transiciones permitidas:
  - `sin_datos -> frio|templado|caliente|descartado`
  - `frio -> templado|caliente|descartado|frio`
  - `templado -> frio|caliente|descartado|templado`
  - `caliente -> templado|descartado|caliente`
  - `descartado -> descartado` (terminal en F1)
- Restricción:
  - Toda transición entre estados distintos requiere `motivo_principal` no vacío.

### Reglas contractuales de escalado
- Campo derivado: `escalado_recomendado` (booleano).
- Debe evaluarse con estas reglas mínimas:
  1. `true` si `estado_interes = caliente`.
  2. `true` si `score_interes >= 75` y existe intención explícita positiva.
  3. `false` si `estado_interes = descartado`.
  4. `false` por defecto en casos ambiguos no cubiertos.
- Si `escalado_recomendado = true`, debe existir `regla_escalado_aplicada`.

### Reglas de consistencia
- `estado_interes = descartado` no puede convivir con `escalado_recomendado = true`.
- `score_interes < 20` no debe clasificarse como `caliente`.
- `score_interes >= 75` con `estado_interes` no-caliente debe quedar justificado en `motivo_principal` (caso excepcional en F1).

## Contratos de comportamiento CX2-F2

### Taxonomía de señales
- Clases permitidas (cerradas):
  - `positiva`
  - `neutra`
  - `negativa`
  - `bloqueo`
- Convención de tipo de señal: `<canal>.<evento>` (ej. `wa.intent_price_request`).
- Canal permitido mínimo en F2: `whatsapp` (extensible a `instagram|webchat|email|other`).

### Contrato de señal normalizada (`InterestSignalNormalized`)
Campos obligatorios:
- `signal_id` (string único)
- `lead_id` (string)
- `conversation_id` (string)
- `channel` (enum)
- `signal_type` (string)
- `signal_class` (enum cerrado)
- `occurred_at` (ISO-8601 UTC)
- `ingested_at` (ISO-8601 UTC)
- `confidence` (decimal `0..1`)
- `source` (enum: `nlp_rule|keyword|operator|system_timeout`)
- `version` (string)

Campos opcionales:
- `raw_text_excerpt` (string corto, sanitizado)
- `metadata` (objeto JSON pequeño)
- `dedupe_key` (string)
- `trace_id` (string)

### Reglas contractuales de normalización
1. Timestamps deben persistirse en UTC ISO-8601 (`Z`).
2. `signal_type` desconocido debe mapear a `<canal>.unknown` y `signal_class=neutra`.
3. `signal_class` fuera de catálogo invalida el evento.
4. `confidence` debe estar en rango `0..1`; sin valor explícito se aplica valor por defecto documentado de baja confianza.
5. Dedupe mínimo: misma `dedupe_key` en ventana corta => conservar un único evento.

### Reglas de precedencia
1. `bloqueo` prevalece sobre cualquier otra clase en la misma ventana de evaluación.
2. `negativa` prevalece sobre `neutra`.
3. `positiva` prevalece sobre `neutra`.
4. A igualdad, prevalece señal más reciente (`occurred_at`).

### Reglas de seguridad contractuales (obligatorias en F2)
1. **No-trust input:** el texto de usuario se trata como dato y nunca puede sobrescribir reglas de sistema.
2. **PII mínima en trazas:** no registrar texto completo por defecto; usar extracto corto sanitizado y metadatos mínimos.
3. **Escalado no inducible por texto libre:** `escalado_recomendado` no puede activarse por una única instrucción textual sin cumplir reglas de negocio.
4. **Protección anti-spoofing/replay (precondición de ingestión):** eventos deben llegar desde webhook autenticado y con control de duplicados.

## Contratos de comportamiento CX2-F3

### Contrato de ponderaciones base
- Debe existir un catálogo versionado `signal_weight_catalog_version` con pesos por `signal_type`.
- `signal_type` no catalogado debe evaluarse con peso `0` (fallback neutro de F2).

### Contrato de cálculo de `score_interes`
- `score_interes` es entero en rango `0..100`.
- Cálculo contractual mínimo:
  1. `contrib_i = peso_base_i * f_conf_i * f_recencia_i`
  2. `score_raw = 35 + Σ(contrib_i) + penalizacion_inactividad`
  3. `score_interes = round(clamp(score_raw, 0, 100))`

Factores obligatorios:
- `f_conf_i = clamp(confidence_i, 0.30, 1.00)`.
- `f_recencia_i` exponencial por clase:
  - `positiva`: semivida 120h
  - `neutra`: semivida 72h
  - `negativa`: semivida 240h
- Penalización de inactividad: sin señales positivas en 72h => `-2` por día (tope `-20`).

### Contrato de dominancia de bloqueo
Si existe señal `bloqueo` activa en ventana:
1. `estado_interes` debe ser `descartado`.
2. `score_interes` debe ser `0`.
3. `escalado_recomendado` debe ser `false`.
4. `motivo_principal` debe indicar señal/regla dominante.

### Contrato de tramos y transición
Tramos base:
- `bajo`: `0..49`
- `medio`: `50..74`
- `alto`: `75..100`

Histeresis mínima obligatoria:
- subir a `medio` solo con `score >= 52`; bajar a `bajo` con `score <= 47`.
- subir a `alto` solo con `score >= 78`; bajar a `medio` con `score <= 72`.

### Contrato de consistencia tramo ↔ estado
- `frio` solo válido en tramo bajo (sin bloqueo activo).
- `templado` solo válido en tramo medio.
- `caliente` requiere tramo alto + señal de intención explícita reciente.
- `descartado` prevalece por bloqueo activo.

### Seguridad contractual obligatoria en scoring (F3)
1. **Anti-replay e idempotencia:** entradas con `idempotency_key` y dedupe temporal obligatorio.
2. **Anti-gaming por spam:** limitar contribución acumulada por subtipo en ventana.
3. **No escalado por evento único ambiguo:** no activar escalado sin reglas de negocio cumplidas.
4. **Trazabilidad auditable:** registrar versión de pesos, factores aplicados y regla que produjo score/tramo.

## Contratos de comportamiento CX2-F4

### Contrato de persistencia de evaluación (`InterestAssessmentRecord`)
Campos obligatorios:
- `assessment_id` (string único)
- `lead_id` (string)
- `conversation_id` (string)
- `evaluated_at` (ISO-8601 UTC)
- `estado_interes` (enum F1)
- `score_interes` (entero `0..100`)
- `score_band` (enum: `bajo|medio|alto`)
- `motivo_principal` (string no vacío)
- `escalado_recomendado` (boolean)
- `signal_weight_catalog_version` (string)
- `scoring_model_version` (string)
- `trace_id` (string)

Reglas:
1. Cada evaluación debe persistirse como nuevo registro (inmutabilidad lógica).
2. El estado vigente por conversación se obtiene por `evaluated_at` más reciente.
3. Debe respetar consistencia de F1/F3 (`estado_interes`, `score_interes`, `score_band`).

### Contrato de trazabilidad de reglas (`InterestRuleTrace`)
Campos obligatorios por evaluación:
- `assessment_id`
- `trace_generated_at` (ISO-8601 UTC)
- `blocking_rule_applied` (boolean)
- `applied_rules` (array)
- `decision_summary` (string corto)
- `input_signal_refs` (array de `signal_id` de F2)

Reglas:
1. Si `escalado_recomendado=true`, debe existir regla `triggered` que lo justifique.
2. Si `estado_interes=descartado` por bloqueo, debe registrarse regla de dominancia.

### Contrato de retención mínima
Política mínima obligatoria:
1. Conservar al menos **20** evaluaciones más recientes por `conversation_id`.
2. Conservar al menos **90 días** de historial por conversación.
3. Una purga no puede eliminar el registro más reciente de una conversación con historial.

### Contrato de auditoría operativa
Campos obligatorios por escritura de evaluación:
- `audit_event_id` (string único)
- `audit_at` (ISO-8601 UTC)
- `actor_type` (enum: `system|operator|job`)
- `actor_id` (string)
- `origin` (enum: `api|cron|manual|reprocess`)
- `idempotency_key` (string)
- `request_id` (string)
- `trace_id` (string)
- `contract_version` (string)
- `storage_backend_mode` (enum: `json|dual|mysql`)
- `write_result` (enum: `success|rejected|failed`)

Reglas:
1. Toda evaluación persistida debe tener sello de auditoría completo.
2. Operaciones con `idempotency_key` repetida no deben duplicar evaluación lógica.
3. Fallos de escritura deben registrarse con causa técnica resumida.

### Seguridad contractual obligatoria en persistencia/auditoría (F4)
1. Integridad de historial: mecanismo de verificación de integridad de eventos (tamper-evident).
2. Control de acceso: mínimo privilegio para lectura/exportación de auditoría.
3. PII: minimización, masking por defecto y retención alineada a política.
4. No repudio: identificación de actor y correlación completa de la operación.

## Contratos de comportamiento CX2-F5

### Contrato de elegibilidad de escalado operativo
Campo derivado: `escalado_operativo_candidato` (boolean).

`escalado_operativo_candidato=true` si cumple una vía:
1. `standard_hot_stable`:
   - `estado_interes=caliente`
   - `score_interes >= 78`
   - señal explícita positiva en `24h`
   - 2 evaluaciones consecutivas con `score_interes >= 75`, separadas >= `5m`, en `60m`
2. `fast_track_explicit_buy`:
   - `score_interes >= 90`
   - `wa.intent_buy_explicit` en `2h`
   - `confidence >= 0.80`
   - sin bloqueo activo

Si es `true`, `regla_escalado_aplicada` es obligatoria (`standard_hot_stable|fast_track_explicit_buy`).

### Exclusiones contractuales (hard-stop)
`escalado_operativo_candidato=false` cuando:
1. `estado_interes=descartado`
2. existe señal `signal_class=bloqueo` activa
3. existe `wa.opt_out_request` activa
4. `score_interes < 75`

### Contrato anti-ruido y anti-duplicado
1. Cooldown: máximo 1 candidato por `conversation_id` cada `24h`.
2. Excepción de cooldown: solo con `delta_score >= +15` y nueva señal explícita positiva.
3. `handoff_dedupe_key` obligatorio (`conversation_id + score_band + regla_escalado_aplicada + dominant_signal_type + bucket_30m`).
4. Misma `handoff_dedupe_key` en `30m` => suprimir creación de nuevo candidato.
5. Si en `120m` coexisten `wa.intent_buy_explicit` y `wa.intent_not_interested`:
   - `escalado_operativo_candidato=false`
   - `requiere_revision_manual=true`
   - `regla_bloqueo_operativo=conflicting_intent_guard`

### Contrato de payload mínimo de handoff (`CommercialHandoffPayload`)
Campos obligatorios:
- `handoff_id` (string único)
- `lead_id` (string)
- `conversation_id` (string)
- `assessment_id` (string)
- `trace_id` (string)
- `created_at` (ISO-8601 UTC)
- `estado_interes` (enum F1)
- `score_interes` (int 0..100)
- `score_band` (enum `bajo|medio|alto`)
- `escalado_recomendado` (boolean)
- `escalado_operativo_candidato` (boolean)
- `regla_escalado_aplicada` (enum F5)
- `motivo_principal` (string no vacío)
- `top_signals` (array 1..3 con `signal_type`, `signal_class`, `occurred_at`, `confidence`)
- `blocking_flags` (objeto)
- `last_positive_at` (ISO-8601 UTC|null)
- `last_negative_at` (ISO-8601 UTC|null)
- `channel` (enum F2)
- `last_user_message_excerpt` (string corto sanitizado)
- `suggested_priority` (`alta|media`)

### Reglas de seguridad contractuales obligatorias (F5)
1. Toda operación mutante de escalado MUST incluir identidad de actor, `request_id`, `trace_id` e idempotencia por intención.
2. Overrides humanos MUST requerir MFA, mínimo privilegio y caducidad temporal.
3. Overrides de alto impacto MUST exigir aprobación dual (4-eyes).
4. Estados `BLOCKED|FROZEN|LOCKED` MUST denegar operaciones no lectura (fail-closed).
5. Payload de handoff MUST minimizar PII y no incluir datos sensibles completos en logs/trazas.
6. Replays con misma idempotencia MUST no producir doble efecto de escalado.

### Trazabilidad operativa F5
Por candidato creado/suprimido/bloqueado registrar:
- `audit_event_id`
- `conversation_id`
- `assessment_id`
- `handoff_dedupe_key`
- `decision` (`created|suppressed|blocked`)
- `decision_reason`
- `trace_id`
- `audit_at`

### Casos de prueba manuales mínimos F5
1. Umbral por debajo (`score=77`) no escala.
2. Umbral estándar (`score=78`) con estabilidad sí escala.
3. Fast-track (`score>=90` + `intent_buy_explicit`) sí escala.
4. Señal de bloqueo activa impide escalado.
5. Cooldown 24h suprime segundo handoff.
6. Excepción por `delta_score>=+15` permite nuevo handoff.
7. `handoff_dedupe_key` repetida en 30m se suprime.
8. Contradicción (`intent_buy_explicit` + `intent_not_interested`) fuerza revisión manual.
9. Payload sin campo obligatorio falla validación.
10. Regresión F1-F4 mantiene consistencia de estado/score/auditoría.

## Contratos de comportamiento CX2-F6

### Contrato de visualización operativa (`PanelOperationalView`)
Campos obligatorios por ítem:
- `lead_id`, `conversation_id`, `assessment_id`, `trace_id`
- `estado_interes`, `score_interes`, `score_band`
- `escalado_recomendado`, `escalado_operativo_candidato`, `regla_escalado_aplicada`
- `motivo_principal`, `top_signals`, `blocking_flags`
- `evaluated_at`, `last_positive_at`, `last_negative_at`
- `suggested_priority` (`alta|media|normal`)

Reglas:
1. `suggested_priority=alta` requiere `escalado_operativo_candidato=true` y ausencia de bloqueo activo.
2. Panel debe reflejar exactamente el snapshot de `assessment_id` (sin recálculo de score en F6).
3. Si faltan campos obligatorios de F4/F5, el ítem queda `inconsistente` y no habilita acciones mutantes.

### Contrato de acciones humanas (`ManualOperationalAction`)
Acciones permitidas:
- `confirmar_handoff`
- `corregir_clasificacion`
- `reabrir_revision`

Payload mínimo obligatorio:
- `action_id`, `action_type`, `conversation_id`, `lead_id`, `assessment_id`
- `reason_code`, `reason_note`
- `actor_id`, `actor_role`, `request_id`, `trace_id`, `idempotency_key`, `created_at`
- `parent_override_id` (obligatorio en `reabrir_revision`)

Reglas:
1. Acción fuera del enum -> rechazo `422`.
2. Misma `idempotency_key` -> `noop` sin doble efecto.
3. `assessment_id` inexistente/no trazable -> rechazo `409/422`.
4. En estados `BLOCKED|FROZEN|LOCKED` toda acción mutante debe denegarse (`fail-closed`).
5. `confirmar_handoff` no puede modificar `score_interes`, `score_band` ni `estado_interes`.

### Contrato de trazabilidad de overrides (`ManualOverrideRecord`)
Registro obligatorio por acción aceptada/rechazada:
- `override_id`, `action_id`, `conversation_id`, `lead_id`, `assessment_id`
- `action_type`, `action_result` (`confirmed|corrected|reopened|rejected|noop`)
- `previous_operational_state`, `new_operational_state`
- `reason_code`, `reason_note`
- `actor_id`, `actor_role`, `request_id`, `trace_id`, `idempotency_key`, `audit_at`

Reglas:
1. Historial append-only lógico.
2. Override enlazado 1:1 a `assessment_id` de F4.
3. `reabrir_revision` requiere `parent_override_id` válido.
4. `reason_note` y trazas deben minimizar PII.

### Seguridad contractual obligatoria (F6)
1. Overrides MUST aplicar autorización contextual y mínimo privilegio.
2. Overrides críticos MUST requerir MFA reciente (step-up).
3. Solicitud y aprobación de override crítico MUST respetar separación de funciones (4-eyes).
4. Eventos de override MUST auditarse en almacenamiento append-only con integridad verificable.
5. Replays de acción MUST bloquearse por `idempotency_key` + `request_id` + ventana temporal.

### Casos de prueba manuales mínimos F6
1. Prioridad alta visible cuando `escalado_operativo_candidato=true` y sin bloqueo.
2. Ítem con bloqueo activo no habilita acción mutante.
3. `confirmar_handoff` crea override auditado con actor/razón.
4. `corregir_clasificacion` sin `reason_note` falla validación.
5. Reintento con misma `idempotency_key` produce `noop`.
6. `reabrir_revision` sin `parent_override_id` falla validación.
7. Snapshot de score/estado no cambia por acción humana en F6.
8. Acción con rol no autorizado es denegada.
9. Override crítico sin MFA reciente es denegado.
10. Regresión F1-F5 mantiene consistencia contractual.

## Contratos de comportamiento CX2-F7

### Contrato de ciclo de calibración (`CalibrationCycle`)
Campos obligatorios:
- `calibration_cycle_id`
- `cycle_type` (`weekly_operational|monthly_calibration|quarterly_governance`)
- `window_start`, `window_end` (UTC)
- `evaluated_population_size` (int >= 0)
- `baseline_versions` (`scoring_model_version`, `signal_weight_catalog_version`, `escalation_policy_version`)
- `status` (`ok|warning|breach`)
- `generated_at`, `generated_by`, `trace_id`

Reglas:
1. F7 es runtime-neutral: no altera ejecución en tiempo real de F1-F6.
2. Muestra insuficiente => `status=warning` + `insufficient_sample=true`.
3. Salida de ciclo debe ser reproducible con misma ventana + mismas versiones base.

### Contrato de métricas y guardrails (`CalibrationGuardrails`)
Métricas obligatorias:
- `fp_rate` (`0..1`)
- `over_escalation_rate` (`0..1`)
- `blocked_escalation_incidents` (int >= 0)

Límites obligatorios:
1. `fp_rate_target <= 0.12`
2. `fp_rate_warning > 0.12 && <= 0.18`
3. `fp_rate_breach > 0.18`
4. `over_escalation_rate_target <= 0.20`
5. `over_escalation_rate_breach > 0.25`
6. `blocked_escalation_incidents > 0` => `status=breach`

### Contrato de revisión/versionado de reglas (`RuleVersionReview`)
Campos obligatorios:
- `review_id`
- `candidate_versions` (`scoring_model_version`, `signal_weight_catalog_version`, `escalation_policy_version`)
- `baseline_versions`
- `decision` (`promote|hold|rollback|discard`)
- `decision_reason`
- `evidence_refs` (>=1 `calibration_cycle_id`)
- `approved_by`, `approved_at`, `trace_id`

Reglas:
1. `promote` requiere al menos 2 ciclos semanales consecutivos en `ok`.
2. Si existe `breach` en la ventana evaluada, `promote` es inválido.
3. `rollback|discard` debe referenciar guardrail incumplido.
4. Historial de decisiones es append-only lógico.

### Seguridad contractual obligatoria (F7)
1. Métricas de calibración MUST vincularse a `dataset_version_id`, `rule_set_version_id`, `code_commit_sha` y hash de artefacto.
2. Datasets MUST validar integridad (hash/firma) antes de uso.
3. Cambios de reglas MUST usar aprobación dual (4-eyes) con separación de funciones.
4. Rollback MUST ser atómico para reglas+dataset+parámetros versionados.
5. Auditoría de calibración MUST ser append-only con integridad verificable.

### Casos de prueba manuales mínimos F7
1. Ciclo semanal con muestra suficiente y métricas en objetivo => `ok`.
2. `fp_rate` en warning => `warning` y plan de ajuste.
3. `fp_rate` en breach => bloqueo de promoción.
4. `over_escalation_rate` en breach => `hold|rollback`.
5. Escalado con bloqueo activo => incidente y `breach`.
6. Intento de `promote` con un solo ciclo `ok` => rechazo.
7. Trazabilidad permite reconstruir la decisión.
8. Artefacto de dataset con hash inválido => rechazo.
9. Autoaprobación de cambio crítico => denegada.
10. Regresión F1-F6 mantiene consistencia contractual.
