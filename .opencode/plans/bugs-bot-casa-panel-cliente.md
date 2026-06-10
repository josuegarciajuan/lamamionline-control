# Plan: Bugs y mejoras bot-casa — panel cliente

> **PLAN MODE** — Documento de planificación. No implementar hasta confirmación.
> **Multi-agente**: Otros agentes pueden modificar los mismos archivos. Coordinar con este spec.

---

## Issue 1: Bot parado no registra conversaciones

### Descripción
Cuando el bot está en STOP, los mensajes entrantes de WhatsApp NO se guardan en `session_memory.ndjson`. El admin no ve las conversaciones nuevas en el chat ni puede responder manualmente.

### Causa raíz
En `bot-casa/public/webhook.php:224-233`, el check `if (!$bot->isRunning())` retorna 200 y hace `exit` ANTES de que se escriba el mensaje entrante en session memory (que ocurre en líneas 241-281).

**Flujo actual (ROTO):**
```
1. webhook recibe mensaje
2. resolve userId, phone, text
3. webhook dedup check
4. ❌ if bot stopped → exit (NO guarda nada)
5. guarda mensaje en session_memory  ← NUNCA se ejecuta si bot parado
6. pipeline...
```

**Flujo corregido:**
```
1. webhook recibe mensaje
2. resolve userId, phone, text
3. webhook dedup check
4. ✅ guarda mensaje en session_memory (siempre, con _pending:false si bot parado)
5. if bot stopped → exit (sin ejecutar pipeline)
6. pipeline...
```

### Archivos a modificar
- `bot-casa/public/webhook.php` — reordenar lógica

### Cambio específico

Mover el bloque de escritura a session_memory (líneas 241-281) ANTES del check `if (!$bot->isRunning())` (línea 224).

El registro inmediato usará `_pending: false` cuando el bot está parado (ya que no va a responder).

### Riesgos
- Ninguno. Solo se añade persistencia, no se modifica comportamiento del pipeline.
- La escritura es `FILE_APPEND | LOCK_EX`, thread-safe.
- Si el path de session_memory no existe, falla silenciosamente (como ahora).

---

## Issue 2: Imágenes en el chat se muestran como enlaces

### Descripción
En el chat del panel cliente (y admin), cuando se envían imágenes (manual o por bot), los enlaces aparecen como texto plano en lugar de mostrarse como imágenes o previews clickeables.

Las URLs de `compartir.site` son shortlinks que WhatsApp convierte en link-previews (OG tags), pero el chat web solo muestra texto escapado.

### Causa raíz
En `bot-casa/public/assets/chat.js:770-776`, la función `renderMessages()` escapa TODO el contenido de `user_msg` y `bot_reply` con `esc()`, sin detectar ni renderizar URLs como enlaces o imágenes.

### Archivos a modificar
- `bot-casa/public/assets/chat.js` — función `renderMessages()`: añadir detección de URLs y renderizado de enlaces/imágenes
- `bot-casa/public/assets/chat.css` — estilos para links e imágenes inline
- `bot-casa/public/client.php` — actualizar versión de cache-busting
- `bot-casa/public/panel.php` — ídem (regla dual-panel)

### Cambio específico

En `renderMessages()`, línea 773 y 784, donde se hace:
```js
'<div class="msg-body">' + esc(userMsg) + '</div>'
```

Reemplazar por una función helper `formatMessageBody(text)` que:
1. Detecte URLs (`https?://...`) con una regex
2. Para URLs de imagen (compartir.site, extensiones .jpg/.png/.webp, ibb.co, etc.): renderizar como imagen inline `<img>` con lazy loading y fallback a enlace clickeable
3. Para otras URLs: renderizar como `<a>` clickeable con `target="_blank" rel="noopener"`
4. El resto del texto: escaparlo normalmente

No usar `innerHTML` sin sanitización — la regex de URL garantiza que solo se inyectan URLs válidas como atributos `src`/`href`.

### Riesgos
- XSS: el helper debe usar `esc()` para todo texto que NO sea URL. Las URLs deben validarse con regex estricta antes de usarse como `src` o `href`.
- Rendimiento: con muchas imágenes inline puede ralentizar el chat. Usar `loading="lazy"`.

---

## Issue 3: Estados vacíos al publicar

### Descripción
Al pulsar "Publicar ahora" en la sección Estados, se ha publicado un estado de WhatsApp con texto vacío.

### Causa raíz
En `bot-casa/public/api/estados.php:170-173`, cuando el formato es `mix_aleatorio`:

```php
case 'mix_aleatorio':
    $k = array_rand($formatOptions, 1);
    $cfg['formato'] = $k;
    break;  // ← $txt se queda como '' (vacío)
```

El `array_rand` sobre `$formatOptions` puede seleccionar `mix_aleatorio` de nuevo, y en cualquier caso, al hacer `break` sin re-ejecutar el switch, `$txt` permanece vacío.

La versión del admin publicista (`app/publicista.php`) no tiene este bug porque:
1. Excluye `mix_aleatorio` del pool de candidatos
2. Reasigna `$formato` para que el switch se ejecute con el formato resuelto

### Archivos a modificar
- `bot-casa/public/api/estados.php` — corregir `case 'mix_aleatorio'`
- `bot-casa/public/panel.php` — si hay JS que maneje el resultado (actualizar cache-busting si toca JS)
- `bot-casa/public/client.php` — ídem (dual-panel)

### Cambio específico

**Recomendado (misma lógica que admin publicista):**
```php
case 'mix_aleatorio':
    // Excluir mix_aleatorio del pool para no caer en bucle vacío
    $noMix = array_values(array_filter(
        array_keys($formatOptions),
        fn($f) => $f !== 'mix_aleatorio'
    ));
    $formato = $noMix[array_rand($noMix)];
    $cfg['formato'] = $formato;
    // NO hacer break — caer al switch con formato resuelto
```

Y añadir validación de texto vacío antes de publicar (línea 176):
```php
if (trim($txt) === '') {
    echo json_encode(['ok' => false, 'error' => 'No se pudo generar el texto del estado']);
    break;
}
```

### Riesgos
- El array `$formatOptions` debe filtrarse para no incluir `mix_aleatorio` en `array_rand`.
- Añadir validación de `$txt` vacío como safety net.

---

## Issue 4: Conversación 672788474 — URL truncada + repetición

### Descripción
En la conversación con teléfono `34672788474` (thread `631349504_34672788474`), se observan dos problemas:

**4a. URL truncada** — seq 2670 (2026-06-10T09:32:54Z):
```
"pues Iris te va a encantar, site/pnb8l/
https://compartir.site/qtmf0/
https://compartir.site/a80l6/"
```
La primera URL aparece como `site/pnb8l/` en lugar de `https://compartir.site/pnb8l/`.

**4b. Patrón repetido** — Se enviaron fotos 3 veces seguidas (seq 2665, 2668, 2670) en la misma conversación, cada vez con combinaciones similares de URLs de chicas.

### Causa raíz

**4a (URL truncada):** El LLM está generando la URL truncada en su respuesta. `site/pnb8l/` es `https://compartir.site/pnb8l/` sin el prefijo `https://compartir.`. Esto puede ocurrir porque:

1. El LLM recibe en el contexto JSON las URLs de fotos (vía `girls_config`) y ocasionalmente "recorta" el dominio al intentar reproducirlas.
2. No hay un post-procesador que detecte y repare URLs truncadas de dominios conocidos (`compartir.site`).

**Evidencia:** Es el único caso en ~2700 registros con URL truncada. Las demás URLs de compartir.site en el mismo archivo están completas. Es un error esporádico del LLM.

**4b (repetición 3x):**
- seq 2665: responde con fotos (modo catálogo — 1 foto por chica)
- seq 2667: responde "todas estan disponibles, dime cual te gusta mas"
- seq 2668: el usuario dice "Esta" → bot responde con más fotos de Iris pero en realidad envía fotos MEZCLADAS de varias chicas (bug de CatalogFormatter: cuando `selected_girl` no está resuelto pero el LLM habla de una chica específica, el CatalogFormatter envía 1 foto de cada chica activa en lugar de solo la chica mencionada)
- seq 2670: el usuario vuelve a decir "Esta" → el bot responde otra vez con texto de Iris + fotos mezcladas

El problema de fondo es: cuando el LLM responde nombrando a una chica pero `ctx['selected_girl_name']` está vacío, el CatalogFormatter entra en modo catálogo (1 foto por chica) en lugar de fotos de la chica nombrada. Esto causa que el bot envíe reiteradamente el catálogo completo aunque el usuario ya ha mostrado interés.

### Archivos a modificar

**Para 4a (URL truncada):**
- `bot-casa/src/Pipeline/ResponseNormalizer.php` — añadir reparación de URLs truncadas de dominios conocidos
- O: `bot-casa/src/Bot.php` — añadir un paso post-AI de normalización de URLs

**Para 4b (repetición / fotos mezcladas):**
- `bot-casa/src/Pipeline/CatalogFormatter.php` — mejorar detección de "chica mencionada" en el texto de salida del LLM
- `bot-casa/src/Pipeline/DedupeReply.php` — el guard `ya_enviado: ["fotos"]` debería bloquear envíos repetidos de fotos (posible bug: verificar si el guard de categoría está funcionando)

### Cambios específicos

**4a — Reparación de URLs truncadas:**

En `ResponseNormalizer.php`, después de extraer `user_visible_reply`, añadir una normalización:

```php
// Reparar URLs truncadas de dominios conocidos
$outputText = preg_replace(
    '/(?<!https?:\/\/)(?<![a-z])site\/([a-z0-9]{5}\/)/i',
    'https://compartir.site/$1',
    $outputText
);
```

O de forma más genérica, en un nuevo paso del pipeline o en Bot.php, detectar patrones `site/XXXXX/` sin protocolo y prefijar `https://compartir.`.

**4b — Mejorar CatalogFormatter:**

Añadir lógica para detectar si el LLM está hablando de una chica específica aunque `selected_girl_name` esté vacío:
1. Extraer nombres de chicas del `output_text` del LLM usando `preg_match` contra los nombres en `girls_config`.
2. Si se detecta exactamente 1 chica nombrada en el texto de salida, tratarla como `selected_girl` para el CatalogFormatter.
3. Esto haría que el bot envíe TODAS las fotos de la chica mencionada (caso "selected_all") en lugar del catálogo mezclado.

**4b — Verificar DedupeReply:**

Revisar si `ya_enviado: ["fotos"]` está bloqueando correctamente. En seq 2668, `ya_enviado` ya contiene `"fotos"` (viene de seq 2665), pero aún así se enviaron más fotos. El guard de categoría debería haber bloqueado esto.

Posible bug: el dedup de categoría en DedupeReply solo reescribe el texto pero NO elimina las URLs de `output_text`. Las URLs se añaden en CatalogFormatter (procesador 4), mientras DedupeReply es procesador 5. Para cuando DedupeReply se ejecuta, las URLs YA están en `output_text`. El guard de categoría reescribe el texto pero las URLs del CatalogFormatter permanecen.

Si esto es cierto, el fix sería: cuando `ya_enviado` contiene `"fotos"`, DedupeReply debe eliminar las líneas de URLs del `output_text` además de reescribir el mensaje.

### Riesgos
- La reparación de URLs truncadas debe ser conservadora para no false-positivar con otras ocurrencias de "site/".
- La detección de chica mencionada en output_text podría false-positivar si el LLM menciona varios nombres. Debe ser precisa (nombre exacto, case-insensitive, unicode-aware).
- El cambio en DedupeReply para eliminar URLs afecta a todo el flujo de fotos. Probar con varios escenarios.

---

## Resumen de archivos tocados

| Archivo | Issues | Tipo de cambio |
|---|---|---|
| `bot-casa/public/webhook.php` | #1 | Reordenar bloques |
| `bot-casa/public/assets/chat.js` | #2 | Añadir `formatMessageBody()` |
| `bot-casa/public/assets/chat.css` | #2 | Estilos para `.chat-img`, `.chat-link` |
| `bot-casa/public/client.php` | #2, #3 | Cache-busting version |
| `bot-casa/public/panel.php` | #2, #3 | Cache-busting version (dual-panel) |
| `bot-casa/public/api/estados.php` | #3 | Fix `mix_aleatorio` + validación |
| `bot-casa/src/Pipeline/CatalogFormatter.php` | #4b | Detectar chica en output_text |
| `bot-casa/src/Pipeline/DedupeReply.php` | #4b | Strip URLs on foto dedup |
| `bot-casa/src/Pipeline/ResponseNormalizer.php` | #4a | Reparar URLs truncadas |

## Orden de implementación recomendado

1. **#3** — Estados vacíos (bug crítico, fix simple, sin dependencias)
2. **#1** — Bot parado chat logging (bug crítico, fix simple, sin dependencias)
3. **#2** — Imágenes en chat (mejora UX, cambios en JS+CSS, requiere coordinación dual-panel)
4. **#4** — URL truncada + repetición (requiere más análisis y testing del pipeline)

## Coordinación multi-agente

- **webhook.php** y **estados.php** son tocados por #1 y #3 respectivamente. Ambos son cambios independientes y pequeños; no deberían causar conflictos.
- **chat.js** y **chat.css** son cambios más extensos (#2). Otros agentes deben saber que `renderMessages()` se va a modificar.
- **CatalogFormatter.php** y **DedupeReply.php** (#4) son cambios delicados en el pipeline. Coordinar antes de tocar.
- **panel.php** y **client.php** necesitan cambio de versión cache-busting cuando se modifiquen JS/CSS.
