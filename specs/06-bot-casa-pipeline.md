# 06 · Pipeline de bot-casa

Documenta el algoritmo completo de procesamiento de mensajes de WhatsApp en
`bot-casa/`, desde que WAHA entrega un webhook hasta que se envía la respuesta
y se ejecutan los side-effects. Cada paso incluye invariantes testeables.

> **Origen:** `bot-casa/public/webhook.php` → `Bot::handleWebhook()` en
> `bot-casa/src/Bot.php`. Gates y procesadores en
> `bot-casa/src/Pipeline/`.

---

## 1. Punto de entrada — `public/webhook.php`

### 1.1 Tenant routing (`lines_map.json` → `userId`)
- Extrae `me.id` del payload WAHA (o `to`).
- Últimos 9 dígitos (`last9`) se usan como clave en `data/lines_map.json`.
- Si la línea no está en el mapa → userId=1 (admin/legacy), warning en log.
- `Bot::bootstrap(rootDir, userId)` construye todas las dependencias aisladas
  por usuario (paths, configs, routing).

**Invariantes testeables:**
- Si `lines_map.json` tiene `"111111111": 5`, un webhook con `me.id="111111111@c.us"`
  produce `userId=5`.
- Si `last9` no está mapeado, `userId=1` y se emite un `WARNING` en el log.
- `Bot::bootstrap()` con `userId > 1` resuelve paths a `data/users/{id}/`.

### 1.2 Dedup de webhook 15s (`.webhook_dedup.json`)
- Antes de escribir cualquier cosa, se verifica si este mismo mensaje
  `(threadId | md5(phone|content))` se procesó en los últimos 15 segundos.
- Si es duplicado → se salta la escritura y todo el pipeline.
- El diccionario se poda a 300 entradas máximo.

**Invariantes testeables:**
- Dos webhooks idénticos con menos de 15s de diferencia: el segundo NO persiste
  ni corre pipeline.
- Tras 16s, el mismo mensaje SÍ se procesa.

### 1.3 `threadId = last9_phone`
- `threadId = (last9 !== '' ? last9 : '000000000') . '_' . senderPhone`.
- Si no hay `senderPhone`, no se persiste ni se corre pipeline.

**Invariantes testeables:**
- `threadId` siempre tiene formato `XXXXXXXXX_XXXXXXXXX` (18 dígitos + `_`).
- `threadId` es determinista: mismo `last9` + mismo `phone` = mismo `threadId`.

### 1.4 Persistencia inmediata en `session_memory.ndjson` con flag `_pending`
- Se escribe un registro inmediato con `_pending: true` (si bot running y
  thread no pausado) o `_pending: false`.
- `_pending=true` activa el typing indicator en el chat UI y señala que el
  pipeline responderá.
- `_pending=false` significa que el bot está stopped o el thread pausado:
  el mensaje es visible pero no habrá respuesta.

**Invariantes testeables:**
- Tras un webhook válido con `botIsRunning=true` y thread no pausado, el
  registro en `session_memory.ndjson` tiene `_pending: true`.
- Con `botIsRunning=false`, el registro tiene `_pending: false` y el pipeline
  NO corre (HTTP 200 con `"message":"Bot is stopped"`).

### 1.5 Cortes tempranos
- **Bot-mode stop:** `Bot::isRunning()` lee `.bot_mode`. Si es `"stop"` → 200,
  no pipeline.
- **Thread pausado:** `data/paused_threads.ndjson`. Si el `threadId` está en la
  lista → 200, no pipeline.

**Invariantes testeables:**
- `BotModeGate` devuelve `null` (pipeline abortado) si `.bot_mode` = `"stop"`.
- `PauseGate` devuelve `null` si `threadId` está en `paused_threads.ndjson`.

---

## 2. Input Gates (7 en orden)

Se ejecutan secuencialmente en `Bot::handleWebhook()`. Cada gate recibe `$ctx`,
lo enriquece o devuelve `null` (aborta pipeline).

### 2.1 BotModeGate
- **Archivo:** `src/Pipeline/BotModeGate.php`
- **Input ctx:** `thread_id` (opcional)
- **Verifica:** `.bot_mode` file → si contiene `"stop"` → `null`.
- **También verifica:** per-thread lead lock (`data/locks/lead_detected/lead_{md5}.lock`).
  Si existe, aborta para no re-disparar alertas tras reinicio del bot.
- **Produce:** `ctx['bot_mode']` = `"start"` o `"stop"`.
- **Propósito en orden actual:** primera compuerta. Si el bot está apagado,
  no gastar recursos.

**Invariantes testeables:**
- Con `.bot_mode = "stop"` → `process()` retorna `null`.
- Con `.bot_mode = "running"` (o cualquier cosa ≠ stop) → retorna `$ctx` con
  `bot_mode = "running"`.
- Archivo inexistente → asume `start` (fail-open).

### 2.2 RoutingGate
- **Archivo:** `src/Pipeline/RoutingGate.php`
- **Input ctx:** `body`, opcionalmente `from_phone`
- **Verifica:**
  - Línea receptora (`me.id` → last9) está en `routing.lines` y `enabled=true`.
  - Remitente NO está en `routing.sender_blacklist`.
- **Produce:** claves WAHA (`waha_base_url`, `waha_chat_id`, `waha_session`,
  `waha_port`, `line_last9`, `line_label`, `line_notas`, `ai_provider`,
  `ai_model`, `sender_lid`).
- **Aborta:** si línea deshabilitada o remitente blacklisteado.

**Invariantes testeables:**
- Línea con `enabled: false` → `null`.
- Remitente en `sender_blacklist` (coincidencia exacta, last9, o sufijo) → `null`.
- Línea encontrada → `ctx['ai_provider']` es `"openai"` o `"deepseek"` según
  `routing.lines[].ai_provider`.
- `ctx['waha_chat_id']` se construye como `phone + @c.us` (o `@lid` si GOWS).

### 2.3 DedupGate
- **Archivo:** `src/Pipeline/DedupGate.php`
- **Input ctx:** `body` (para extraer `messageId`)
- **Verifica:** lock file en `data/locks/event_dedup/{messageId}.lock`.
- **Produce:** `ctx['__dedup_status']` = `"OK"`, `"OK_NOKEY"`, `"OK_NOLOCK"`, o `"DUP"`.
- **Aborta:** si el lock ya existe (mensaje duplicado).

**Invariantes testeables:**
- Dos llamadas con mismo `messageId` → primera OK, segunda `null`.
- Sin `messageId` → pasa (`__dedup_status = "OK_NOKEY"`).
- Locks más viejos que `dedup_file_ttl_minutes` se limpian probabilísticamente
  (1/10 de las invocaciones).

### 2.4 Coalescer
- **Archivo:** `src/Pipeline/Coalescer.php`
- **Input ctx:** `from_phone`, `line_last9` (para key compuesta), `body`
- **Verifica:** si hay un burst activo (ventana `coalesce_window_sec`, default 12s).
- **Comportamiento:**
  - Si no hay burst activo → este mensaje es "leader": duerme `coalesce_sleep_before_send_sec`
    (4s) y recoge todos los mensajes del buffer.
  - Si hay burst activo → apenda al buffer y retorna `null` (el leader lo recogerá).
- **Produce:** `ctx['__coalesced_text']`, `ctx['__is_first'] = true`,
  `ctx['__is_opening_burst']`.
- **Key compuesta:** `fromPhone + '_' + lineLast9` para no mezclar líneas.

**Invariantes testeables:**
- Dos mensajes del mismo phone+line en < 12s → el segundo retorna `null`,
  el primero recoge ambos textos unidos con `" | "`.
- `detectOpeningBurst` detecta patrones como `"he visto tu anuncio ... hola"`.
- Tras 12s sin actividad, el siguiente mensaje inicia nuevo burst.

### 2.5 MessageExtractor
- **Archivo:** `src/Pipeline/MessageExtractor.php`
- **Input ctx:** `body`, `__coalesced_text`
- **Extrae:** `message_text`, `message_type` (`"text"`, `"audio"`, `"image"`),
  `is_audio_i`, `is_image_i`, `from_phone`, `message_id`, `timestamp`.
- **Placeholders:** audio → `[AUDIO]`, sin texto → `[SIN_TEXTO]`.
- **GOWS support:** prefiere `_data.Info.SenderAlt` sobre `from`.

**Invariantes testeables:**
- Mensaje de texto normal → `message_type = "text"`, `is_audio_i = 0`.
- Audio/PTT → `message_type = "audio"`, `is_audio_i = 1`, `message_text = "[AUDIO]"`.
- Imagen sin caption → `is_image_i = 1`.
- `from_phone` solo contiene dígitos (sin `@c.us`, `@lid`).

### 2.6 PauseGate
- **Archivo:** `src/Pipeline/PauseGate.php`
- **Input ctx:** `thread_id`, `line_last9`, `from_phone`
- **Verifica:** `data/paused_threads.ndjson` → si `thread_id` está pausado → `null`.
- **También expone:** `hasCancelRequest(threadId)`, `clearCancelRequest(threadId)`
  para cancelación mid-pipeline.
- **Construye thread_id** si no existe (usando `line_last9 + '_' + from_phone`).

**Invariantes testeables:**
- Thread en `paused_threads.ndjson` → `process()` retorna `null` y setea
  `_pause_halted = true`.
- `isThreadPaused` devuelve `true` para thread pausado.
- `hasCancelRequest` devuelve `true` si existe `data/cancel/{hash}.cancel`.

### 2.7 InflightGate
- **Archivo:** `src/Pipeline/InflightGate.php`
- **Input ctx:** `from_phone`, `line_last9`, `message_text`
- **Verifica:** si ya hay un lock inflight para este `phone + line`.
- **Si hay lock activo** (y no expirado, TTL 60s): encola el mensaje en
  `_pending.json` y retorna `null`.
- **Si no hay lock:** deja pasar.
- **Métodos estáticos** usados por `Bot.php`:
  - `createLock(lockDir, phone, lineLast9)` — crea lock al inicio del pipeline.
  - `drainPending(lockDir, phone, lineLast9)` — lee y borra mensajes encolados.
  - `cleanup(lockDir, phone, lineLast9)` — borra lock y pending.

**Invariantes testeables:**
- Sin lock inflight → `process()` retorna `$ctx`.
- Con lock inflight (edad < 60s) → `process()` retorna `null` y el mensaje
  aparece en `_pending.json`.
- Lock con edad > 60s → se borra y el mensaje pasa.
- `drainPending` retorna los mensajes encolados y borra el archivo.

---

## 3. Procesadores (orden en `Bot.php`)

Ejecutados secuencialmente tras los input gates. Cada uno recibe y devuelve `$ctx`.

### 3.1 ContextAssembler
- **Archivo:** `src/Pipeline/ContextAssembler.php`
- **Input ctx:** `memory_text`, `girls_config`, `message_text`, `thread_id`,
  `line_last9`, `from_phone`, `__coalesced_text`, `last_bot_reply`, `last_user_message`
- **Output ctx (~70 claves), principales:**

| Clave | Tipo | Descripción |
|---|---|---|
| `thread_id` | string | Compuesto `line_last9_phone` |
| `bot_msg_count_recent` | int | Mensajes del bot en historial reciente |
| `__human_msg_count` | int | Mensajes reales del humano (excluye `_pending`) |
| `__is_new_conversation` | bool | Sin historial previo (ni reciente ni total) |
| `__is_opening_burst` | bool | Burst de apertura (auto-msg + greeting) |
| `__is_ad_intro` | bool | Cliente viene de anuncio concreto |
| `topic_actual` | string | Tópico detectado (tarifa, ubicacion, fotos, etc.) |
| `tarifa_elegida` | string/null | Tarifa detectada en historial |
| `hot_curious_chat_current` | bool | Mensaje actual con contenido sexual |
| `wants_more_girls` | bool | Cliente pide ver más chicas (persistente) |
| `last_bot_reply` | string | Última respuesta del bot |
| `last_user_message` | string | Último mensaje del usuario |
| `last_user_meaningful` | string | Último mensaje sustantivo del usuario |
| `ya_enviado` | list | Qué info ya se envió (fotos, precios, ubicacion, etc.) |
| `maps_sent` | bool | Si ya se envió ubicación precisa |
| `speaker_girl_name` | string | Chica que habla (primera mención, NUNCA cambia) |
| `speaker_girl_id` | string | ID de la chica que habla |
| `speaker_mode` | string | `"chica"` o `"encargada"` |
| `selected_girl_name` | string | Chica elegida por el cliente (sticky) |
| `selected_girl_id` | string | ID de la chica elegida |
| `must_choose_girl_now` | bool | Si hay que forzar elección de chica |
| `shown_girls` | list | Nombres de chicas ya mostradas |
| `unshown_girls` | list | Nombres de chicas aún no mostradas |
| `__total_active_girls` | int | Total de chicas activas |
| `photos_sent_recent` | bool | Fotos enviadas en ventana reciente |
| `session_reset` | bool | Si la sesión se reseteó (sin actividad 6h+) |
| `conversation_end_intent` | bool | El cliente indica fin de conversación |
| `interes_fuerte` | bool | Cliente muestra intención fuerte de compra |
| `ubicacion_pedida_fuerte` | bool | Cliente pide ubicación explícitamente |
| `choose_loop_count` | int | Veces que el bot preguntó "cuál prefieres" |
| `photo_insist_count` | int | Veces que el cliente insistió pidiendo fotos |
| `location_insist_count` | int | Veces que el cliente insistió pidiendo ubicación |
| `catalog_count` | int | Veces que se mostró catálogo en esta conversación |
| `photo_rejected` | bool | Cliente rechazó explícitamente las fotos |
| `info_pack_ready` | bool | Chica elegida + precios enviados + ubicación enviada |
| `maps_being_sent_now` | bool | El bot va a enviar mapa en esta respuesta |
| `__filler_loop_count` | int | Conteo de mensajes filler consecutivos |
| `__bot_confusion_count` | int | Veces que el bot dijo "no entiendo" |
| `user_response_time_sec` | float | Tiempo de respuesta del usuario |
| `__is_burst` | bool | Burst de mensajes del usuario |
| `__is_urgent` | bool | Mensaje con tono urgente |
| `preguntas_pendientes` | list | Preguntas del usuario sin responder |
| `__has_greeted` | bool | Si el bot ya saludó en esta conversación |
| `eta_from_user_minutes` | int | ETA en minutos extraído del mensaje |
| `eta_from_user_flag` | bool | Si el usuario dio ETA |
| `location_url` | string | URL de Google Maps desde config |
| `is_image_sent_by_user` | bool | Si el usuario envió una imagen |
| `recent_bot_replies_norm` | list | Últimas 5 replies del bot normalizadas |

**Invariantes testeables:**
- `__is_new_conversation = true` solo cuando `history === []` Y `recent === []`.
- `speaker_girl_name`, una vez asignada, NUNCA cambia en llamadas subsecuentes
  para el mismo `threadId`.
- `selected_girl_name` cambia SOLO cuando hay `girl_selection_intent` en el
  mensaje actual.
- `thread_id` siempre tiene el formato `line_last9_phone`.
- `bot_msg_count_recent` cuenta solo registros `bot_reply` no vacíos y no
  `_pending`.

### 3.2 ResponseScorer
- **Archivo:** `src/SideEffects/ResponseScorer.php` (ejecutado en Bot.php paso 6a)
- **Input ctx:** contexto completo post-ContextAssembler
- **Función:** evalúa qué tan efectiva fue la respuesta ANTERIOR del bot,
  basado en la respuesta actual del cliente.
- **Best-effort:** nunca bloquea el pipeline.
- Alimenta el playbook de aprendizaje.

### 3.3 IntentRouter
- **Archivo:** `src/Pipeline/IntentRouter.php`
- **Input ctx:** `message_text`, `__skip_llm`
- **Función:** router pre-LLM para intents comunes que no necesitan IA.
- **Intents manejados:** `greeting` (solo primer contacto).
- **Produce:** `ctx['output_text']`, `ctx['__skip_llm'] = true`,
  `ctx['__intent']`, `ctx['__conversation_ended']`.

**Invariantes testeables:**
- Si `__is_new_conversation` y `message_text` es solo saludo → `__skip_llm=true`.
- Si `message_text` contiene pregunta de precio/ubicación → NO skipea LLM.

### 3.4 ConversationStateMachine
- **Archivo:** `src/Pipeline/ConversationStateMachine.php`
- **Input:** historial del hilo (de `SessionMemory`) + `$ctx`
- **Estados:** `NEW`, `GREETING_SENT`, `AWAITING_INTEREST`, `CATALOG_SHOWN`,
  `GIRL_SELECTED`, `PRICE_GIVEN`, `MAPS_SENT`, `WAITING_ETA`, `CONFIRMED`,
  `DEAD`, `COMPLETED`.
- **Produce:** `ctx['__conversation_state']`, `ctx['__state_hint']`.
- **Determinista:** sin LLM, basado en flags del historial.

**Invariantes testeables:**
- Historial vacío → estado `NEW`.
- Tras `CATALOG_SHOWN` + chica elegida → transición a `GIRL_SELECTED`.
- Con ETA concreto → `CONFIRMED`.

### 3.5 ToneBuilder
- **Archivo:** `src/Pipeline/ToneBuilder.php`
- **Input ctx:** `sentiment`, `register`, `urgency`, `speaker_girl_name`,
  `speaker_mode`, `selected_girl_name`, `ya_enviado`, `__is_new_conversation`,
  `message_text`, `choose_loop_count`, `tarifa_elegida`, `interes_fuerte`,
  `ubicacion_pedida_fuerte`, `conversation_end_intent`, `__filler_loop_count`,
  `catalog_count`, `photo_rejected`, `hot_curious_chat_current`,
  `is_image_sent_by_user`, `__is_urgent`, `preguntas_pendientes`,
  `recent_bot_replies_norm`, `last_bot_reply`
- **Produce:** `ctx['tone_directives']` — string con directivas de tono
  inyectadas en el system prompt.
- **Incluye:** variación de personalidad (cariñosa, pícara, directa, tímida),
  anti-repetición, identidad de speaker, gestión de regateo, reglas de fotos,
  manejo de filler loops.

**Invariantes testeables:**
- `tone_directives` siempre contiene `"Usa registro {$register}, tono {$sentiment}"`.
- Si `__is_new_conversation`, incluye directiva de personalidad aleatoria.
- Si `ya_enviado` contiene `"fotos"`, incluye `"NO repitas el catálogo"`.

### 3.6 ResponseNormalizer
- **Archivo:** `src/Pipeline/ResponseNormalizer.php`
- **Input ctx:** `openai_raw_response`
- **Función:** parsea la respuesta JSON del LLM y extrae campos estructurados.
- **Produce:** `ctx['output_text']`, `ctx['lead_detected']`,
  `ctx['lead_confidence']`, `ctx['lead_numeric']`, `ctx['eta_minutes']`,
  `ctx['photo_action']`, `ctx['lead_signals']`.
- También extrae campos semánticos para `applyLlmSemanticFields`:
  `__llm_mentioned_girl`, `__llm_girl_selection_intent`,
  `__llm_conversation_health`, `__llm_buying_intent`, `__llm_tarifa_elegida`,
  `__llm_hot_curious`, `__llm_wants_more_girls`.

**Invariantes testeables:**
- JSON con `user_visible_reply` → `output_text` = ese valor.
- `lead_detected` es booleano tras normalización.
- `lead_signals` es array, nunca string.
- `photo_action` ∈ `{"none", "catalog", "selected_all"}`.
- Respuesta no-JSON → `output_text` = raw text, flags en false/0.

### 3.7 CatalogFormatter
- **Archivo:** `src/Pipeline/CatalogFormatter.php`
- **Input ctx:** `girls_config`, `output_text`, `photo_action`, `message_text`,
  `selected_girl_name`, `sent_photo_urls`
- **Función:** adjunta URLs de fotos según `photo_action`.
  - `"none"` → no añade nada.
  - `"catalog"` → 1 foto por chica activa.
  - `"selected_all"` → todas las fotos de `selected_girl`.
- **Produce:** modifica `ctx['output_text']` añadiendo URLs de fotos,
  `ctx['sent_photo_urls']`.

**Invariantes testeables:**
- `photo_action = "none"` → `output_text` no contiene URLs de `compartir.site`.
- `photo_action = "catalog"` con 3 chicas activas → hay exactamente 3 URLs
  (una por chica).
- `photo_action = "selected_all"` con chica seleccionada → URLs solo de esa chica.

### 3.8 DedupeReply
- **Archivo:** `src/Pipeline/DedupeReply.php`
- **Input ctx:** `output_text`, `ya_enviado`, `last_bot_reply`,
  `recent_bot_replies_norm`, `__bot_confusion_count`, `photo_insist_count`,
  `location_insist_count`
- **Función:** evita que el bot repita contenido ya enviado.
  - **Priority 0:** anti-confusion loop (si bot dijo "no entiendo" 1+ veces,
    reescribe como pregunta de clarificación).
  - **Priority 1:** `ya_enviado` category match (fotos, ubicacion, precios).
  - **Priority 2:** exact/semantic duplicate contra `last_bot_reply` y
    `recent_bot_replies_norm`.
- **Produce:** `ctx['output_text']` potencialmente reescrito,
  `ctx['__dedup_applied']`, `ctx['__dedup_reason']`.

**Invariantes testeables:**
- Si `ya_enviado` contiene `"fotos"` y `output_text` tiene URLs de fotos
  y `photo_insist_count < 1` y `photo_action ≠ "selected_all"` → se reescribe
  con variante "ya te las mandé".
- Si `output_text` normalizado = `last_bot_reply` normalizado → se reescribe
  con prefijo humano.
- `photo_action = "selected_all"` → NUNCA se bloquea por `ya_enviado` (es
  petición genuina).

### 3.9 ImageSplitter
- **Archivo:** `src/Pipeline/ImageSplitter.php`
- **Input ctx:** `output_text`
- **Función:** separa URLs de imágenes del texto para envío individual.
  - Primer mensaje: solo texto.
  - Siguientes: una URL por mensaje (solo-link).
- **Produce:** `ctx['splitted_messages']` — array de mensajes con metadata
  (`__is_first`, `__split_index`).

**Invariantes testeables:**
- `output_text` sin URLs → `splitted_messages` tiene 1 entrada con
  `__is_first = true`.
- `output_text` con 3 URLs de imágenes → `splitted_messages` tiene 4 entradas
  (texto + 3 URLs), solo la primera tiene `__is_first = true`.

---

## 4. Fast-paths (atajos sin LLM)

### 4.1 `skip_llm` / `conversation_ended` (IntentRouter)
- **Condición:** `__skip_llm = true` y `output_text` no vacío.
- **Comportamiento:** se salta la llamada al LLM. Se ejecuta CatalogFormatter
  si `photo_action ≠ "none"`, luego `sendMessages` + persistencia + cleanup.
- **Ubicación en código:** `Bot.php` líneas 204-249.

### 4.2 Audio auto-reply
- **Condición:** `is_audio_i === 1`.
- **Comportamiento:** respuesta determinista desde `message_variants.audio_auto_reply`.
  Sin LLM, sin catálogo.
- **Ubicación:** `Bot.php` líneas 258-297.

### 4.3 Primer contacto / `__is_ad_intro`
- **Condición:** `__is_new_conversation = true` Y
  (`speaker_mode === "encargada"` O `__is_ad_intro = true`).
- **Comportamiento:** saludo mínimo (`message_variants.first_contact_greetings`),
  `photo_action = "none"`. Sin LLM, sin catálogo, sin ofrecer chicas.
- **Ubicación:** `Bot.php` líneas 306-357.

---

## 5. Llamada al LLM

### 5.1 `buildSystemPrompt()`
- **Archivo:** `Bot.php` método privado, línea 1018.
- **Modo:** `prompt.mode` determina si usa `template_v2`/`sections_v2` o
  `template`/`sections` legado.
- **Componentes del prompt:**
  1. Template base con placeholders `[rol]`, `[estilo]`, `[tarifas]`, etc.
  2. Sections rellenadas desde config (`prompt.sections_v2`).
  3. Tone directives (`ctx['tone_directives']`).
  4. Playbook (`data/playbook.md`) con limpieza de meta-análisis en inglés.
  5. Campos semánticos adicionales (JSON fields que el LLM debe devolver).
- **Trim de `girls_config`:** si `selected_girl_name ≠ ""` y `wants_more_girls ≠ true`,
  se filtra para incluir SOLO la chica seleccionada.

### 5.2 `buildChatHistory()`
- **Archivo:** `Bot.php` línea 1359.
- **Ventana:** `memory.recent_window_hours` (default 6h).
- **Compresión:** si hay más de `memory.compress_after_turns` (default 10) turnos,
  los más antiguos se resumen en un bloque `[RESUMEN ANTERIOR]`.
- **Filtro "no entiendo":** si el bot dijo frases de confusión en el historial,
  se reemplazan por `"ok"` para romper el loop.
- **Estructura:** `[{role: "user", content: ...}, {role: "assistant", content: ...}]`.

### 5.3 Selección de proveedor
- `ctx['ai_provider']` determina si se usa `OpenAiClient` o `DeepSeekClient`.
- `ctx['ai_model']` permite override del modelo por línea.

---

## 6. Post-LLM

### 6.1 `applyLlmSemanticFields()`
- **Archivo:** `Bot.php` línea 1259.
- **Campos aplicados:**
  - `mentioned_girl` → `selected_girl_name/id` + `speaker_girl_name/id` si no hay.
  - `girl_selection_intent` → trigger para cambiar `selected_girl`.
  - `conversation_health` → `dead` setea `__conversation_ended = true`;
    `fading` se deja que el LLM lo maneje.
  - `tarifa_elegida` → `ctx['tarifa_elegida']`.
  - `buying_intent` → `strong` setea `interes_fuerte = true`.
  - `hot_curious` → `ctx['hot_curious_chat_current']`.

### 6.2 Guards post-AI

#### Anti-catalog gate (Bot.php línea 548)
- Si `catalog_count >= 2` o `photo_rejected = true`, y `photo_action = "catalog"`:
  se eliminan URLs de `compartir.site` del output y se fuerza `photo_action = "none"`.

#### Guard "casita" (Bot.php línea 463)
- Si el cliente NO preguntó sobre independencia de chicas, se eliminan frases
  como "todas comparten casita", "estamos todas en el mismo piso", etc.

#### `injectLocationUrl` (Bot.php línea 1924)
- Si el LLM promete enviar ubicación pero no incluye URL → se inyecta
  `location_url` desde config.
- `maps_solo_chica`: si no hay chica seleccionada, se BLOQUEA el envío de
  mapa (borra la URL si el LLM la incluyó).
- Anti-double-maps: si `ya_enviado` ya contiene `ubicacion_precisa`, solo
  se reenvía si el cliente lo pide explícitamente.

#### `injectPhotoUrls` (Bot.php línea 542)
- Si el LLM promete enviar fotos pero no llegaron URLs → se inyectan
  desde `girls_config`.

### 6.3 Drain pending (anti-metralleta)
- **Archivo:** `Bot.php` método `sendMessages()`, línea 1553.
- Antes de enviar, `InflightGate::drainPending()` recoge mensajes que llegaron
  durante el procesamiento.
- Si hay pending:
  - **Primera vez:** re-procesa con texto coalesced (nueva llamada LLM).
  - **Ya re-procesado:** merge sin LLM (añade mensajes al array de envío).

### 6.4 `WahaApi::sendHumanized()`
- **Archivo:** `src/Services/WahaApi.php`
- **Parámetros:** `humanDelays`, `incomingText`, `turnCount`,
  `userResponseTimeSec`, `isBurst`, `isUrgent`, `isReprocess`.
- **Simula comportamiento humano:**
  - **Habituation:** delay se reduce con cada turno (`start_boost=6.2`,
    `decay=0.92`, `floor=1.25`).
  - **Pace:** adapta velocidad según tiempo de respuesta del usuario
    (`reference_sec=60`, `steepness=0.6`).
  - **Burst:** si el usuario mandó ≥3 mensajes en 30s, delay se reduce `×0.33`.
  - **Urgent:** delay se reduce `×0.25`.
  - **Patterns:** 70% estándar, 20% sin read, 10% read-first.
  - **Correction:** 12% probabilidad de pausa tipográfica (simula errata).
  - **Emoji-only:** si el mensaje es solo emojis, se envía sin typing simulation.
  - **sendSeen:** double-tap simulado (random 1-3s).
  - **Typing:** basado en chars_per_sec (38-85) con chunks y pausas.
  - **Read delay:** base 900-2200ms + 22ms/char, clamp 1.2-22s.

### 6.5 Persistencia final
- `SessionMemory::appendMessage()` — escribe el registro completo
  (user_msg + bot_reply + flags) en `session_memory.ndjson`.
- Solo si `_send_ok = true` y no `_cancelled`.

### 6.6 Side-effects (orden en `Bot.php` `runSideEffects()`)
1. **Lead lock check:** si ya existe `data/locks/lead_detected/lead_{md5}.lock`,
   se saltan todos los side-effects.
2. **AutoOff:** si `bot.auto_off_on_lead = true` y lead detectado → escribe
   `"stop"` en `.bot_mode` + crea per-thread lead lock.
3. **LeadDetector + Gates A/B/C:**
   - **Gate A (first-contact):** conversación nueva + `lead_confidence < 0.98`
     + sin ETA → bloquea lead.
   - **Gate B (no-evidence):** sin `maps_sent` y sin ETA y
     `lead_confidence < 0.98` → bloquea lead.
   - **Gate C (early-conversation):** `bot_msg_count < 2` y sin ETA y
     `lead_confidence < 0.98` → bloquea lead.
   - Si pasa gates → `TelegramService::sendLeadAlert()`.
4. **LeadLogger:** si lead validado → escribe en `leads.ndjson`.
5. **ReminderWriter:** si `eta_minutes > 0` → escribe recordatorio en
   `reminders_pending.ndjson`.

---

## 7. Cleanup

- `InflightGate::cleanup(lockDir, fromPhone, lineLast9)` — borra lock y
  pending files.
- Se ejecuta siempre al final del pipeline (éxito o error).
- También en cada fast-path y en el catch del `handleWebhook()`.
