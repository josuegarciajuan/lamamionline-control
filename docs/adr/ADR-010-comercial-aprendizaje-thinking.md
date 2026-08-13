# ADR-010: Auto-aprendizaje y reasoning (thinking) en el bot comercial

**Estado**: Aprobada
**Fecha**: 2026-08-13
**Programa**: COMERCIAL-APRENDIZAJE-THINKING

## Contexto

El bot comercial (inbox.php) respondía a leads/clientas con plantillas y, posteriormente, con un generador LLM (`gpt-4o-mini`) sin razonamiento profundo. Las respuestas humanas que el operador escribía desde el WhatsApp nativo (`fromMe=true`) o desde el panel del CRM se capturaban de forma incompleta y **no alimentaban ningún mecanismo de aprendizaje automático**.

Objetivos:
1. Leer todas las conversaciones y aprender de lo que contesta el operador humano.
2. Automatizar ese aprendizaje.
3. Que el bot entienda bien al remitente y dé la respuesta de venta más adecuada por negocio.

## Decisión

### 1. Pipeline de aprendizaje por proceso
- **Clasificación** (`cron/comercial_classify_outcomes.php`, cada 30 min): lee todos los threads y clasifica las conversaciones inactivas en `lead_probable`, `lead_ghosted`, `mareador`, `no_respuesta`, `muerta`, `hostil`, `descartado`, `indeterminado`. Escribe `data/comercial_conversation_outcomes.jsonl`.
- **Aprendizaje** (`cron/comercial_learn.php`, diario 5:00): muestrea conversaciones por outcome + respuestas humanas, y llama a DeepSeek (`deepseek-v4-pro`, `thinking`) para generar un **playbook por proceso** en `data/comercial_playbooks/{slug}.md`.

### 2. Captura de respuestas humanas con contexto
- `comercial_ai_memory.json` almacena respuestas humanas con un campo `trigger_text` (el mensaje del cliente que la provocó).
- Se capturan desde: WhatsApp nativo (`fromMe`), envío manual del panel y promoción a lead.

### 3. Selección de respuestas por keyword + fase
- `comercial_ai_memory_relevant_examples()` selecciona respuestas humanas pasadas relevantes (overlap de keywords con `trigger_text` + score histórico) y las inyecta como few-shot en el prompt del agente.

### 4. Reasoning en la generación de respuestas
El agente comercial usa DeepSeek (`chat/completions`) con `thinking`:

| Modo | Modelo | Thinking | Motivo |
|---|---|---|---|
| reply | deepseek-v4-pro | enabled, `reasoning_effort: medium` | calidad + latencia equilibrada (~8-10s) |
| opener | deepseek-v4-pro | enabled (configurable, OFF por defecto) | apertura de campaña |
| summary | deepseek-v4-pro | enabled, `high` | no es hot-path |
| classify | deepseek-v4-flash | **disabled** | hot-path, ~1-3s |

- Extracción **solo de `content`** (nunca `reasoning_content`) para evitar contaminar la respuesta con el razonamiento interno en inglés.

### 5. Crítico de DeepSeek activado
- `comercial_agent_critic.php` se carga en `bootstrap.php` (antes era código muerto).
- Corregido: `thinking: disabled` explícito (deepseek-v4-pro activa reasoning por defecto y devolvía `content` vacío con `finish_reason=length`), `timeout` 5→20s, `max_tokens` 300→800.

### 6. Enmascarar latencia
- `startTyping` previo a la llamada LLM (`comercial_start_typing_for_thread`).

## Consecuencias

### Positivas
- El bot aprende del operador automáticamente (sin intervención).
- Respuestas con razonamiento profundo y estilo coherente con el humano.
- El crítico detecta y reescribe respuestas que violan reglas (autoreferencia, tono de email, exceso de emojis).

### Negativas
- Latencia por mensaje ~10-15s (mitigada con `startTyping` + `medium` + flash en classify).
- Coste por mensaje mayor (DeepSeek thinking vs gpt-4o-mini).
- El playbook inyectado por mensaje se limita a 5000 chars (el resto se mantiene en disco).

### Riesgos aceptados
- `deepseek-v4-pro` activa reasoning por defecto: mitigado fijando `thinking` explícito en todos los llamadores.
- El cron de campaña usa plantillas estáticas (no el opener LLM), por lo que el reasoning no ralentiza el tick.
