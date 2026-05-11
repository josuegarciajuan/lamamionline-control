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
