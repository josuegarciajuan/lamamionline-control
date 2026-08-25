# Spec: Sistema de Captación Comercial V2 — Híbrido State Machine + Dual LLM

**Fecha**: 2026-07-31
**Estado**: Diseño aprobado, pendiente de implementación
**Alcance**: Mejora integral del bot comercial para las 5 líneas de negocio

---

## 1. Objetivo

Sustituir el sistema actual de conversación comercial (LLM monolítico con libertad táctica total) por un sistema híbrido que garantice:

- **Mensajes cortos, concretos y naturales** (no suenan a bot)
- **Sin volcados de información** (un tema por mensaje, conversación paso a paso)
- **Control determinista de la estrategia** (qué decir y cuándo)
- **Calidad garantizada** vía crítico LLM (DeepSeek) antes del envío
- **Sin doble-respuesta** cuando un humano contesta desde WhatsApp nativo

---

## 2. Pre-fix: Human Takeover desde WhatsApp Nativo

### 2.1 Problema

Cuando un humano abre WhatsApp en su móvil y responde a un thread que el bot está manejando, WAHA reporta `fromMe=true`. El webhook actual (`app/comercial.php:3015-3018`) descarta este mensaje silenciosamente y **no marca `human_taken=1`** en el thread. El bot sigue auto-respondiendo al prospecto aunque el humano ya respondió ("double-talking").

### 2.2 Solución

En `app/comercial.php`, dentro de `comercial_handle_webhook_http()`, modificar el bloque que maneja `from_me=true`:

```php
// Actual (línea 3015-3018):
if (!empty($payload['from_me'])) {
    comercial_webhook_log_append('ignored_from_me', $logContext);
    voice_json_response(array('ok' => true, 'ignored' => 'from_me'));
}

// Nuevo:
if (!empty($payload['from_me'])) {
    // Extraer el número de destino (el prospecto a quien se respondió)
    $toPhone = comercial_only_digits((string)($payload['to'] ?? ''));
    if ($toPhone !== '') {
        // Buscar thread activo para este prospecto + línea
        $fromPhoneLine = comercial_only_digits((string)($payload['from'] ?? ''));
        $thread = comercial_find_active_thread_for_phone($toPhone, $fromPhoneLine);
        if ($thread) {
            $thread['human_taken'] = 1;
            $thread['last_human_reply_at'] = now_datetime();
            comercial_upsert_thread($thread);
            comercial_event_append('human_taken_from_native', array(
                'thread_id' => $thread['id'],
                'target_phone' => $toPhone,
            ));
        }
    }
    comercial_webhook_log_append('ignored_from_me', $logContext);
    voice_json_response(array('ok' => true, 'ignored' => 'from_me'));
}
```

### 2.3 Efecto

El bot deja de auto-responder a ese thread durante 30 minutos (tras lo cual el sistema de auto-reapertura en `comercial_handle_inbound_message:6310-6324` restaura `human_taken=0` solo si el humano no ha seguido respondiendo).

---

## 3. Máquina de Estados de Conversación

### 3.1 Estados

| Estado | Propósito | Dispara con |
|---|---|---|
| `SALUDO_INICIAL` | Primer mensaje (opener), lanzar pregunta abierta | Thread nuevo, 0 replies del prospecto |
| `DESCUBRIMIENTO` | Entender qué necesita el prospecto, cualificar | Prospecto responde al opener con interés |
| `PRESENTACION` | Dar precio y datos clave, un paso a la vez | 2+ replies del prospecto, interés sostenido |
| `MANEJO_OBJECIONES` | Responder objeciones una a una | Prospecto objeta (caro, no sé, ya tengo...) |
| `CIERRE` | Mover a compra/alta/demo. Escalar a humano | 2+ buying signals o lead_score >= 70 |
| `DESCARTADO` | Silenciar, dejar de responder | `not_interested`, monosílabos x3, insultos |

### 3.2 Reglas por estado

| Estado | Máx líneas | Temas permitidos | Prohibido | Termina con pregunta |
|---|---|---|---|---|
| `SALUDO_INICIAL` | 4 | Solo hook + pregunta abierta | Precio, web, features, "sin compromiso", autoreferencia | Sí |
| `DESCUBRIMIENTO` | 5 | Responder lo que preguntó + 1 pregunta cualificadora | Precio, web/demo, todas las features | Sí |
| `PRESENTACION` | 5 | Precio + 1 beneficio clave + siguiente paso | Objeciones no dichas, todas las FAQs, "si no te gusta..." | Sí |
| `MANEJO_OBJECIONES` | 4 | Solo la objeción concreta + reenganche | Nuevas features, justificaciones largas, insistir | Sí |
| `CIERRE` | 4 | Confirmar interés + siguiente paso + escalado | Vender más, nuevas features, descuentos | No (escala) |
| `DESCARTADO` | 0 | Nada | Todo | No |

### 3.3 Transiciones

```
SALUDO_INICIAL ──(prospecto responde)──▶ DESCUBRIMIENTO

DESCUBRIMIENTO ──(2+ replies, interés)──▶ PRESENTACION
DESCUBRIMIENTO ──(objeta directamente)──▶ MANEJO_OBJECIONES

PRESENTACION ──(objeta)──▶ MANEJO_OBJECIONES
PRESENTACION ──(buying signals)──▶ CIERRE

MANEJO_OBJECIONES ──(objeción resuelta)──▶ PRESENTACION
MANEJO_OBJECIONES ──(nueva objeción)──▶ MANEJO_OBJECIONES (máx 2 rondas, luego CIERRE)
MANEJO_OBJECIONES ──(no avanza, 2 rondas)──▶ CIERRE

Cualquier estado ──(not_interested, monosílabos x3, insulto)──▶ DESCARTADO
```

### 3.4 Implementación

Nuevo campo en el thread: `conversation_phase` (string, uno de los 6 estados).

Nueva función en `app/comercial.php`:

```php
function comercial_state_machine_determine_phase(array $thread, array $process): string {
    // 1. Si no hay replies del prospecto → SALUDO_INICIAL
    // 2. Si último mensaje contiene objeción conocida → MANEJO_OBJECIONES
    // 3. Si hay 2+ buying signals → CIERRE
    // 4. Si replies_count >= 2 → PRESENTACION
    // 5. Si replies_count == 1 → DESCUBRIMIENTO
    // 6. Si intent === 'not_interested' → DESCARTADO
}
```

Se invoca en `comercial_handle_inbound_message()` justo antes del classification, y el resultado se almacena en `$thread['conversation_phase']` antes de llamar al generator.

---

## 4. Generator LLM — Prompts por Fase con Few-shot

### 4.1 Modelo

GPT-4o-mini (sin cambios). Se sigue usando `publicista_openai_json_request()` → `https://api.openai.com/v1/responses`.

### 4.2 Principio de diseño

El Generator recibe **solo la info de la fase actual** (no toda la knowledge base). Cada fase incluye 2-4 ejemplos concretos de respuestas buenas y malas para guiar al LLM.

### 4.3 Estructura del prompt por fase

#### SALUDO_INICIAL (~400 tokens)

```
[Identidad: 1 línea, sin autoreferencia]
  Ej: "Vendes el servicio de Casa Burriana. NO digas 'somos del equipo' ni 'soy X'."
[Estilos de apertura: 2-3 ejemplos buenos de openers]
  Ej: "TENEMOS HUECO LIBRE YA 🔥 Casa grande y tranquila en Burriana, con limpieza, wifi, todo incluido. Mucha demanda ahora mismo. ¿Te cuento?"
[Reglas duras: máx 4 líneas, 1 emoji, terminar con pregunta]
[Ejemplo malo: "Hola, soy X de Casa Burriana. Ofrecemos habitaciones y plazas. Tenemos wifi, smartTV, limpieza diaria, sábanas incluidas. Dos modalidades: plaza 60/40 y alquiler privado..." → DEMASIADO LARGO, suelta todo]
[Ejemplo malo: "Somos del equipo de..." → NO]
[Historial: vacío]
[Texto entrante: vacío (es opener saliente)]
```

#### DESCUBRIMIENTO (~500 tokens)

```
[Identidad: 1 línea, sin autoreferencia]
[1 FAQ corta que el prospecto podría preguntar]
[Reglas: máx 5 líneas, responder solo a lo preguntado, NUNCA precio ni web, 1 pregunta cualificadora]
[Ejemplo bueno: "Pues mira, funciona simple: yo publico tus anuncios y te aviso cuando hay cliente. Tú solo confirmas y abres. ¿En qué ciudad estás?"]
[Ejemplo malo: "La Mami Online es un nuevo concepto de publicista digital. Te conseguimos clientes extra a tu puerta. Alta única 29€. Solo pagas 10€/30min cuando llega cliente. Sin cuotas. Sin permanencia. Web lamami.online." → SOLTÓ PRECIO Y WEB DEMASIADO PRONTO]
[Historial: últimos 3 mensajes]
[Texto entrante: lo que dijo el prospecto]
```

#### PRESENTACION (~500 tokens)

```
[Identidad: 1 línea]
[Precio exacto]
[1-2 beneficios clave alineados con lo que preguntó]
[Siguiente paso concreto]
[Reglas: máx 5 líneas, precio + 1 beneficio + siguiente paso, NUNCA responder objeciones no dichas]
[Ejemplo bueno: "50€/semana. Y tienes 10 días gratis de prueba sin tarjeta para verlo funcionando en tu número. ¿Te activo la prueba?"]
[Ejemplo malo: "50€/semana. También tenemos líneas extra a 10€, dashboard de estadísticas, recordatorios ETA, anti-regateo, publicación de estados, memoria de clientes..." → SOLTÓ TODAS LAS FEATURES]
[Historial: últimos 3 mensajes]
[Texto entrante]
```

#### MANEJO_OBJECIONES (~400 tokens)

```
[Identidad: 1 línea]
[Objeción detectada]
[Respuesta canónica de la knowledge base]
[Reglas: máx 4 líneas, SOLO responder a esa objeción, nunca añadir features nuevas, terminar con pregunta de reenganche]
[Ejemplo bueno: "Entiendo. Pero con la demanda que hay ahora, en pocos días lo recuperas. Además incluye todo: limpieza, wifi, toallas. ¿Quieres venir a verla sin compromiso?"]
[Ejemplo malo: "No es caro si lo comparas con otras opciones. Además tenemos smartTV, buen ambiente, sábanas incluidas, varios baños..." → AÑADE FEATURES EN VEZ DE MANEJAR LA OBJECIÓN]
[Historial]
[Texto entrante]
```

#### CIERRE (~300 tokens)

```
[Identidad: 1 línea]
[Mensaje de escalado definido en knowledge base]
[Reglas: máx 4 líneas, confirmar interés, dar siguiente paso, NUNCA seguir vendiendo, NO terminar con pregunta]
[Ejemplo bueno: "Perfecto, te paso con mi compañera que te gestiona la visita y te resuelve cualquier duda. Un placer 😊"]
[Ejemplo malo: "Genial, te activo la prueba. Además recuerda que tienes dashboard, soporte 24/7, publicación de estados..." → SIGUE VENDIENDO EN VEZ DE CERRAR]
[Historial]
[Texto entrante]
```

### 4.4 Modo opener (sin texto entrante)

El opener se genera igual que ahora (`comercial_agent_process` en modo `'opener'`), pero usando el prompt de fase `SALUDO_INICIAL` y pasando por el crítico antes del envío.

---

## 5. Crítico LLM — DeepSeek

### 5.1 Propósito

Evaluar cada respuesta generada por GPT-4o-mini antes del envío. Si no pasa la checklist, DeepSeek la reescribe. Si aún falla, se usa un fallback predefinido de esa fase.

### 5.2 Modelo y API

- **Modelo**: `deepseek-v4-pro` (configurable)
- **API**: Chat Completions (`/chat/completions`)
- **Endpoint**: `https://api.deepseek.com`
- **Auth**: `deepseek.api_key` (config existente en `bot-casa/config.dist.json`)
- **Reutiliza**: misma integración que `bot-casa/src/Services/DeepSeekClient.php`

### 5.3 Checklist de evaluación (9 checks)

| Check | Descripción |
|---|---|
| `line_count_ok` | No excede el máximo de líneas de la fase actual |
| `single_topic_ok` | Solo habla de UN tema (sin mezclar precio+features+objeciones) |
| `no_bot_tells_ok` | Sin coletillas: "quedo a tu disposición", "un saludo", "cualquier consulta", "estamos para ayudarte", "para cualquier cosa dime" |
| `no_disclosure_ok` | Sin autoreferencia: "soy del equipo", "somos X", "nuestro servicio es..." |
| `natural_tone_ok` | Suena a WhatsApp real: frases cortas, tono coloquial, sin estructura de email |
| `emoji_ok` | Máximo 1 emoji (0 para CIERRE) |
| `no_premature_info_ok` | No suelta info que no corresponde a esta fase |
| `question_end_ok` | Termina con pregunta (excepto CIERRE) |
| `answers_question_ok` | Responde a lo que el prospecto preguntó realmente |

### 5.4 Puntuación y flujo

```
score = checks_pasados / 9 * 100

score >= 89 → PASA, se envía texto original
score < 89  → DeepSeek reescribe el texto manteniendo info pero corrigiendo checks fallidos
              → Si reescrito score >= 89 → se envía reescrito
              → Si reescrito score < 89  → FALLBACK: respuesta predefinida de la fase
```

### 5.5 Prompt del crítico

```
System: Eres un revisor de mensajes de WhatsApp comercial. Evalúa según reglas estrictas y devuelve JSON.

User:
Evalúa este mensaje para la fase "{fase}" ({max_lines} líneas máx). Devuelve SOLO JSON:

REGLAS:
- Máximo {max_lines} líneas
- Un solo tema
- Prohibido: "quedo a tu disposición", "un saludo", "cualquier consulta", "estamos para ayudarte", "para cualquier cosa", "soy del equipo", "somos"
- Tono WhatsApp real, no email corporativo
- Máximo 1 emoji (0 si es fase CIERRE)
- {terminar_con_pregunta}
- Responder SOLO a lo que preguntó, no añadir info no solicitada
- {info_prohibida_segun_fase}

Mensaje a evaluar:
"{texto_generado}"

Devuelve SOLO este JSON sin markdown:
{"score": 0-100, "checks": {"line_count_ok": bool, "single_topic_ok": bool, ...}, "rewritten": "texto corregido (misma info) o null", "reason": "breve"}
```

### 5.6 Fallback por fase

Si tanto el generator como el crítico fallan, se envía una respuesta predefinida:

| Fase | Fallback |
|---|---|
| `SALUDO_INICIAL` | Hook predefinido del negocio (de la KB v2) |
| `DESCUBRIMIENTO` | "Cuéntame un poco más, ¿qué es lo que más te interesa?" |
| `PRESENTACION` | "[Precio]. ¿Te interesaría [siguiente paso]?" |
| `MANEJO_OBJECIONES` | Respuesta canónica de la objeción desde KB |
| `CIERRE` | Mensaje de escalado del negocio |

---

## 6. Knowledge Base V2 — Compacta y por Fase

### 6.1 Nuevo archivo

`app/comercial_knowledge_v2.php` — reemplaza progresivamente al actual `comercial_knowledge.php`.

### 6.2 Estructura por negocio

Cada negocio devuelve un array con sub-arrays por fase. El Generator solo recibe el sub-array de la fase actual.

```php
function comercial_knowledge_v2_plaza(): array {
    return [
        'product_line' => 'Plaza',
        'tone' => 'Cercano, directo. Como hablar con una compañera. NADA formalismos.',
        'global_rules' => ['Máximo 1 emoji', 'Nunca revelar que eres IA', 'Nunca mencionar otros negocios'],

        'SALUDO_INICIAL' => [
            'hook' => 'Hueco libre en Casa Burriana — alta demanda por vacaciones',
            'openers' => [
                "TENEMOS HUECO LIBRE YA 🔥 Casa grande y tranquila en Burriana, con limpieza diaria, wifi, todo incluido. Se ha ido mucha gente de vacaciones y hay muchísimo curro ahora. ¿Te cuento?",
                "Hueco libre en Burriana 🙌 Casa grande, limpia, todo incluido. Ahora mismo hay mucha demanda porque varias chicas se fueron de vacaciones. ¿Te interesa que te cuente?",
                "Buscamos compi para Casa Burriana 🏠 Casa grande, tranquila, con limpieza diaria y wifi. Hay hueco ya, y mucho curro ahora. ¿Te cuento sin compromiso?",
                "¿Buscas sitio? En Casa Burriana tenemos plaza libre ahora mismo. Casa grande, limpia, bien ubicada. Mucha demanda estos días. ¿Te cuento cómo funciona?",
            ],
        ],
        'DESCUBRIMIENTO' => [
            'qualifying_questions' => [
                '¿En qué zona estás ahora?',
                '¿Buscas algo temporal o más fijo?',
                '¿Has estado antes en Burriana?',
                '¿Cuánto tiempo necesitarías?',
            ],
            'pitch' => 'Casa grande y tranquila, limpieza diaria, todo incluido. Dos opciones según lo que necesites.',
        ],
        'PRESENTACION' => [
            'pricing' => 'Plaza compartida: 60/40. Alquiler habitación privada: precio económico (consultar).',
            'features' => 'Limpieza diaria, wifi, smartTV, sábanas y toallas, buen ambiente.',
            'next_steps' => [
                'Coordinamos visita sin compromiso para que la veas.',
                'Si quieres te enseño la casa cuando te venga bien.',
                'Ven a verla y decides sin presión.',
            ],
        ],
        'MANEJO_OBJECIONES' => [
            'caro' => 'Con la demanda actual lo recuperas en pocos días. Incluye todo sin gastos extra: limpieza, wifi, toallas.',
            'no_conozco_la_zona' => 'Burriana está muy bien, zona tranquila y con movimiento. Ven a verla sin compromiso.',
            'ya_tengo_donde_estar' => 'Si cambias de planes o conoces a alguien que busque, me dices. No pierdes nada por tener el contacto.',
            'y_si_no_me_gusta' => 'Por eso te digo que vengas a verla primero. Sin compromiso. Así te haces una idea real.',
        ],
        'CIERRE' => [
            'escalation' => 'Perfecto, te paso con mi compañera que te gestiona la visita y te resuelve cualquier duda al momento. Un placer 😊',
        ],
    ];
}
```

### 6.3 Líneas de negocio con sus hooks principales

| Negocio | Hook principal |
|---|---|
| **Plaza** | Hueco libre + alta demanda por vacaciones |
| **LaMami** | Publicista digital a resultados, tú solo abres la puerta |
| **CasaWasap** | ¿Cuántos clientes pierdes mientras duermes? / 10 días gratis |
| **Publicista** | Ingresos pasivos sin hacer nada: tú presentas, nosotros cerramos |
| **Publiscort** | Visibilidad en 3 portales con tráfico real, 50€/semana |

---

## 7. Plan de Archivos

### 7.1 Archivos modificados

| Archivo | Cambios |
|---|---|
| `app/comercial.php` | Fix `fromMe` (+10L), state machine `determine_phase()` (+30L), field `conversation_phase` en normalización (+5L), integrar crítico en `generate_reply_wrapper` (+20L) |
| `app/comercial_agent.php` | `build_system_prompt()` acepta `$phase`, carga KB por fase (+20L) |

### 7.2 Archivos nuevos

| Archivo | Contenido |
|---|---|
| `app/comercial_agent_critic.php` | `critic_evaluate()` (llama a DeepSeek), `get_critic_config()`, `fallback_reply()` (~100L) |
| `app/comercial_knowledge_v2.php` | KB compacta por fase, 5 negocios × 5 fases + `inbound` (~350L) |

### 7.3 Sin cambios

- `comercial_webhook.php` (sin tocar)
- `comercial_agent_table.php` (sin tocar)
- `inbox_api.php` / `inbox_shared.php` (sin tocar)
- `comercial_anti_spam.php`, `comercial_humanize.php` (sin tocar)
- Pipeline de cron outbound (`cron_comercial.php` → `comercial_run_tick()`)

---

## 8. Orden de Implementación

| Fase | Descripción | Depende de | ~Esfuerzo |
|---|---|---|---|
| **1. Fix fromMe** | Marcar `human_taken` cuando se detecta respuesta humana nativa | Nada | 15 min |
| **2. KB V2** | Crear `comercial_knowledge_v2.php` con las 5 líneas | Nada | 1h |
| **3. State Machine** | Función `determine_phase()`, campo `conversation_phase` | Fase 2 | 30 min |
| **4. Generator con fase** | Modificar `build_system_prompt()` y `generate_reply_wrapper()` | Fase 2, 3 | 1h |
| **5. Crítico DeepSeek** | `comercial_agent_critic.php`, integrar en el pipe | Fase 4 | 1.5h |
| **6. Ajuste y testing** | Probar tono con cada línea, ajustar thresholds | Fase 5 | 1h |

---

## 9. Riesgos y Mitigaciones

| Riesgo | Mitigación |
|---|---|
| DeepSeek no disponible | El crítico tiene timeout de 5s. Si falla, se usa el texto del generator directamente (bypass del crítico, no bloqueante). |
| GPT-4o-mini ignora reglas de fase | El crítico lo atrapa y reescribe. Si el crítico también falla, fallback determinista. |
| Clasificación errónea de fase | La state machine es determinista basada en reglas (replies_count, keywords de objeción, buying signals). Sin dependencia del LLM para esto. |
| Regresión en conversaciones existentes | El campo `conversation_phase` se inicializa al vuelo. Threads existentes sin el campo se tratan como `DESCUBRIMIENTO` por defecto. |

---

## 10. Métricas de Éxito

| Métrica | Actual | Objetivo |
|---|---|---|
| Líneas medias por respuesta | ~8-12 (estimado) | <= 5 |
| Tasa de "suena a bot" (percepción) | Alta (feedback cualitativo) | Baja |
| Double-talking (bot + humano respondiendo) | Ocurre | 0 |
| Coste API por mensaje | ~$0.0001 (GPT-4o-mini solo) | ~$0.00011 (+10%, aceptable) |
| Latencia extra por crítico | 0ms | < 1s (DeepSeek rápido para tareas cortas) |
