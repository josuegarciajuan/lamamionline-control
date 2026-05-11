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
