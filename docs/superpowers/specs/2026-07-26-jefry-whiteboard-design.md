# Jefry Whiteboard — Pizarra visual

**Fecha**: 2026-07-26
**Estado**: Aprobado, pendiente de implementación
**Modo**: Ambos (desktop + Lite coche)

---

## Resumen

Jeffry gana una pizarra visual: un overlay a pantalla completa que puede mostrar imágenes, gráficos, HTML o texto. La AI (DeepSeek) decide libremente cuándo usarla. Funciona tanto en desktop/móvil como en el coche (Lite, Chrome 95, 1024x600), sin recargar la página y sin interrumpir la música.

---

## Arquitectura

```
Usuario habla o Jeffry proactivo
        │
        ▼
┌─ Backend (voice.php) ─────────────────────────────────┐
│  • TOOL:whiteboard|modo|tipo|contenido|duracion        │
│  • voice_tool_whiteboard() → genera datos visuales     │
│  • voice_handle_proactive() → incluye whiteboard       │
│  • Respuesta JSON: { message, tts_text, whiteboard }   │
└───────────────────────────────────────────────────────┘
        │
        ▼
┌─ Frontend (app.js) ───────────────────────────────────┐
│  • renderResponse() → si data.whiteboard → overlay     │
│  • _voiceProactiveChecks._fetchAndSpeak() → idem       │
│  • showWhiteboard(data) → modo flash o modal           │
│  • buildWhiteboardContent() → chart / image / html     │
│  • Flash: timer JS → auto-cierre con barra progreso    │
│  • Modal: persistente hasta X / Escape / tap fuera     │
└───────────────────────────────────────────────────────┘
        │
        ▼
┌─ Overlay DOM (views.php) ─────────────────────────────┐
│  #jefryWhiteboardOverlay (position:fixed, z-index:2000)│
│    ├── .jefry-whiteboard-toolbar (título + ✕)          │
│    ├── .jefry-whiteboard-content (chart/img/html/text) │
│    └── .jefry-whiteboard-progress (barra flash)        │
└───────────────────────────────────────────────────────┘
```

---

## Modos de la pizarra

| Modo | Parámetro | Comportamiento | Ejemplos |
|---|---|---|---|
| `flash` | + duración en segundos | Muestra, se cierra solo al acabar el timer | Saludo matutino con ❤️ (5s), alerta de récord (5s) |
| `modal` | sin duración o 0 | Persistente. Se cierra con X, Escape o tap fuera | Gráfico de ventas, imagen generada, explicación visual |

---

## Cuándo usar la pizarra (reglas para la AI)

### SÍ usar cuando:

| Situación | Por qué |
|---|---|
| Gráficos, estadísticas, datos visuales | Lo visual transmite más que 3 frases habladas |
| Explicaciones complejas | Paso a paso + diagrama > solo voz |
| Rituales emocionales (saludo, cierre, récord) | El ❤️ grande + texto crea conexión |
| Imágenes (generadas o encontradas) | No se pueden describir con voz |
| Alertas importantes (campaña parada, objetivo cerca) | Refuerzo visual de algo crítico |

### NO usar cuando:

| Situación | Por qué |
|---|---|
| Órdenes de música ("pon reggaeton") | Va directo al reproductor, estorba |
| Navegación ("abre jostal", "ve a dashboard") | La acción ya te lleva a otra página |
| Parking ("recuerda dónde aparqué") | Voz + toast basta |
| Preguntas simples ("¿qué hora es?") | Respuesta de 1 frase no necesita pantalla |
| Confirmaciones rápidas ("sí", "vale") | Intrusivo abrir overlay para un "ok" |
| Conversación casual ("cuéntame un chiste") | La voz es el canal natural |

### Principio rector para la AI:
> Usa la pizarra solo cuando lo visual aporte algo que la voz NO puede transmitir. Si con hablar basta, no la uses.

---

## Casos de uso (brainstorming)

### Categoría 1: Rituales y personalidad (proactivo, flash)

| # | Disparador | Qué muestra | Duración |
|---|---|---|---|
| 1 | Saludo matutino (6-12h) | "Buenos días ☀️" + ❤️ grande + frase del día | 5s |
| 2 | Cierre del día (>20h) | "Fin del día 🌙" + resumen visual | 6s |
| 3 | Récord del mes | "¡Récord! 🏆" + cifra grande | 5s |
| 4 | Viernes a la tarde | "¡Finde! 🎉" + animación festiva | 4s |
| 5 | Aniversario CRM | "Hace X meses..." + timeline visual | 5s |

### Categoría 2: Datos del CRM (conversación, modal)

| # | El usuario dice... | Qué muestra |
|---|---|---|
| 6 | "¿cómo va el día?" | Dashboard visual: cards de ingresos, gastos, leads |
| 7 | "hazme un gráfico de ventas del mes" | Barras de ingresos diarios del mes |
| 8 | "compara este mes con el anterior" | Gráfico comparativo lado a lado |
| 9 | "¿cómo va cada negocio?" | Tarta por rama (LaMami, Jostal, Casawasap) |
| 10 | "¿a quién llamo hoy?" | Top 3 clientas priorizadas con puntuación |

### Categoría 3: Explicaciones y apoyo visual (conversación, modal)

| # | Contexto | Qué muestra |
|---|---|---|
| 11 | "explícame cómo funciona X" | Diagrama visual / esquema de flujo |
| 12 | "enséñame una imagen de X" | Imagen generada vía Pollinations |
| 13 | Comparación compleja | Tabla o gráfico lado a lado |

### Categoría 4: Alertas (proactivo, flash)

| # | Disparador | Qué muestra |
|---|---|---|
| 14 | Campaña parada >3 días | "Campaña de Nuria parada" + datos |
| 15 | Alerta de objetivo (85%+) | Barra de progreso grande |
| 16 | Temperatura extrema | Icono del tiempo + temperatura |

---

## Backend (`app/voice.php`) — ~150 líneas

### Tool: `TOOL:whiteboard`

Añadir al system prompt de conversación (`voice_conversation_system_prompt`, línea ~4883):

```
• TOOL:whiteboard|modo|tipo|contenido|duracion — mostrar algo visualmente
  modo: flash (auto-cierre) o modal (persistente)
  tipo: chart, image, html, text
  duración: solo en flash, segundos (defecto 5)
```

### `voice_tool_whiteboard($arg)`

Parseo: `modo|tipo|contenido|duracion`

| Tipo | Acción backend | Datos generados para frontend |
|---|---|---|
| `chart` | `voice_whiteboard_generate_chart($desc)` consulta CRM | `{type, title, labels, datasets}` |
| `image` | Genera URL Pollinations.ai | `{src: "https://image.pollinations.ai/prompt/..."}` |
| `html` | Sanitiza (sin script/iframe) | `{html: "<div>...</div>"}` |
| `text` | Pasa el texto | `{text: "..."}` |

### `voice_whiteboard_generate_chart($description)`

Intérprete simple de keywords para v1:

| Descripción | Chart | Consulta |
|---|---|---|
| `ventas del mes`, `ingresos del mes` | Barras diarias del mes actual | `ingresos.json` → `ingresos_total_periodo()` |
| `ventas por rama`, `por negocio` | Tarta por rama | `ingresos.json` → totales por rama |
| `ventas de la semana` | Barras últimos 7 días | `ingresos.json` |

### Recolector de datos visuales

Funciones estáticas para recolectar durante el loop de tools:

```
voice_whiteboard_collect($data)  → acumula en variable estática
voice_whiteboard_clear()         → resetea antes del loop
voice_whiteboard_get()           → devuelve el último whiteboard
```

En `voice_handle_conversation()`, tras el loop de tools, se añade `whiteboard` a la respuesta.

### Sistema proactivo

Las funciones proactivas pueden incluir clave `whiteboard` en su JSON de respuesta. Ejemplo en `voice_build_morning_greeting()`:

```php
'whiteboard' => array(
    'mode' => 'flash',
    'type' => 'html',
    'duration' => 5,
    'html' => '<div style="text-align:center"><span style="font-size:72px">☀️</span><br><span style="font-size:28px;color:#e2c044">Buenos días, Josué</span><br><span style="font-size:64px">❤️</span></div>'
)
```

### `voice_build_response()`

Añadir `'whiteboard' => null` a los defaults.

---

## Frontend (`assets/app.js`) — ~200 líneas

### showWhiteboard(data)

```
showWhiteboard(data):
  1. buildWhiteboardContent(data) → rellena #jefryWhiteboardContent
  2. overlay.classList.add('active') → fade-in CSS
  3. Si mode=flash → timer JS con barra de progreso
     - setInterval cada 100ms, barra se reduce
     - Al terminar → hideWhiteboard()
  4. Si mode=modal → espera acción del usuario
```

### hideWhiteboard()

```
hideWhiteboard():
  overlay.classList.remove('active') → fade-out
  setTimeout 250ms → overlay.hidden = true
  Limpia timer si estaba activo
```

### buildWhiteboardContent(data)

```
switch (data.type):
  chart  → Desktop: <canvas> + Chart.js       Lite: barras CSS puras
  image  → <img src="..." class="jefry-whiteboard-image">
  html   → innerHTML directo (backend sanitizó)
  text   → <div class="jefry-whiteboard-text">
```

### Gráficos en Lite (sin Chart.js)

Barras CSS nativas que funcionan en Chrome 95:

```
┌──────────┬──────────────────────────┬────────┐
│ 1 Jul    │ ██████████████░░░░░░░░░░ │  180€  │
│ 2 Jul    │ ██████████████████░░░░░  │  220€  │
└──────────┴──────────────────────────┴────────┘
```

- `.jefry-whiteboard-bar-row` → flex, gap 10px
- `.jefry-whiteboard-bar-track` → height 14px, bg #1a2a40
- `.jefry-whiteboard-bar-fill` → bg #e2c044, width dinámico

### Integración

- `renderResponse()` (línea ~1784): si `data.whiteboard`, llamar `showWhiteboard()` con delay (200ms flash, 800ms modal para dejar hablar al TTS)
- `_voiceProactiveChecks._fetchAndSpeak()` (línea ~7145): misma integración

### Cierre por gestos

| Acción | Modal | Flash |
|---|---|---|
| Botón X | ✅ | ✅ |
| Escape | ✅ | ✅ |
| Click fuera de la tarjeta | ✅ | ❌ |
| Auto-cierre por timer | ❌ | ✅ |

---

## HTML (`app/views.php`) — ~10 líneas

Añadir al final de `render_global_ui()`, después de `#voiceCommandPanel`:

```html
<div id="jefryWhiteboardOverlay" class="jefry-whiteboard-overlay" hidden aria-hidden="true">
  <div class="jefry-whiteboard-card">
    <div class="jefry-whiteboard-toolbar" id="jefryWhiteboardToolbar">
      <span id="jefryWhiteboardTitle" class="jefry-whiteboard-title"></span>
      <button type="button" class="jefry-whiteboard-close" aria-label="Cerrar pizarra">✕</button>
    </div>
    <div id="jefryWhiteboardContent" class="jefry-whiteboard-content"></div>
    <div class="jefry-whiteboard-progress" id="jefryWhiteboardProgress" hidden>
      <div class="jefry-whiteboard-progress-bar" id="jefryWhiteboardProgressBar"></div>
    </div>
  </div>
</div>
```

---

## CSS

### Desktop (`assets/style.css`) — ~80 líneas

```css
.jefry-whiteboard-overlay {
  position: fixed; inset: 0;
  z-index: 2000;
  background: rgba(0,0,0,.85);
  backdrop-filter: blur(6px);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: opacity .25s;
  padding: 24px;
}
.jefry-whiteboard-overlay.active { opacity: 1; }

.jefry-whiteboard-card {
  position: relative;
  width: min(900px, 100%);
  max-height: 90vh;
  background: #0c1626;
  border: 1px solid #314862;
  border-radius: 18px;
  box-shadow: 0 20px 60px rgba(0,0,0,.5);
  display: flex; flex-direction: column;
  overflow: hidden;
}

.jefry-whiteboard-toolbar {
  display: flex; justify-content: space-between; align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid #314862;
}
.jefry-whiteboard-title { font-size: 15px; font-weight: 600; color: #d9e2ef; }
.jefry-whiteboard-close {
  background: none; border: none; color: #8099b3; font-size: 22px;
  cursor: pointer; padding: 4px 8px; border-radius: 8px; line-height: 1;
}
.jefry-whiteboard-close:hover { color: #fff; background: rgba(255,255,255,.08); }

.jefry-whiteboard-content {
  flex: 1; overflow: auto; padding: 24px;
  display: flex; align-items: center; justify-content: center;
}

.jefry-whiteboard-progress {
  height: 3px; background: #1a2a40;
}
.jefry-whiteboard-progress-bar {
  height: 100%; background: #e2c044;
  width: 100%;
}

/* Content types */
.jefry-whiteboard-image { max-width: 100%; max-height: 70vh; border-radius: 12px; object-fit: contain; }
.jefry-whiteboard-text { font-size: 28px; text-align: center; color: #d9e2ef; line-height: 1.5; padding: 20px; }
.jefry-whiteboard-chart { width: 100%; max-height: 60vh; }
.jefry-whiteboard-chart canvas { width: 100% !important; height: 100% !important; }

/* Barras CSS (Lite fallback, también disponible en desktop) */
.jefry-whiteboard-bar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.jefry-whiteboard-bar-label { width: 70px; font-size: 13px; color: #8099b3; text-align: right; }
.jefry-whiteboard-bar-track { flex: 1; height: 14px; background: #1a2a40; border-radius: 7px; overflow: hidden; }
.jefry-whiteboard-bar-fill { height: 100%; background: #e2c044; border-radius: 7px; }
.jefry-whiteboard-bar-value { width: 60px; font-size: 13px; color: #d9e2ef; }
```

### Lite (`assets/lite.css`) — ~80 líneas

| Desktop | Lite |
|---|---|
| `backdrop-filter: blur(6px)` | Sin backdrop-filter (no soportado en Chrome 95) |
| `border-radius: 18px` | `border-radius: 14px` |
| `padding: 24px` (overlay) | `padding: 12px` |
| `max-height: 90vh` | `max-height: 85vh` |
| `font-size: 15px` (toolbar) | `font-size: 14px` |
| Scroll en content | `-webkit-overflow-scrolling: touch` |

---

## Cache busters (`index.php`)

```html
<link rel="stylesheet" href="assets/style.css?v=20260726_1">
<link rel="stylesheet" href="assets/lite.css?v=20260726_1">
<script src="assets/app.js?v=20260726_1"></script>
```

---

## Resumen de cambios

| Archivo | Qué | Estimación |
|---|---|---|
| `app/voice.php` | + tool system prompt, + `voice_tool_whiteboard()`, + recolector, + `voice_whiteboard_generate_chart()`, + whiteboard en proactive, + campo en `voice_build_response()` | ~150 líneas |
| `assets/app.js` | + `showWhiteboard()`, + `hideWhiteboard()`, + `buildWhiteboardContent()`, + `buildLiteChart()`, + `buildChartJs()`, + integración en `renderResponse()` y proactive | ~200 líneas |
| `app/views.php` | + `#jefryWhiteboardOverlay` HTML | ~10 líneas |
| `assets/style.css` | + estilos overlay, card, toolbar, content types, barras CSS | ~80 líneas |
| `assets/lite.css` | + estilos adaptados (sin backdrop-filter, altura reducida, touch scroll) | ~80 líneas |
| `index.php` | actualizar cache busters | ~3 líneas |

**Total: ~523 líneas. 0 dependencias nuevas. 6 archivos.**

---

## Verificación

- [ ] `php -l app/voice.php` sin errores de sintaxis
- [ ] `php -l app/views.php` sin errores de sintaxis
- [ ] Overlay funciona en ambos modos: flash (auto-cierre) y modal (persistente)
- [ ] Gráficos se renderizan con Chart.js en desktop y barras CSS en Lite
- [ ] Imágenes se cargan desde Pollinations.ai
- [ ] HTML sanitizado (sin `<script>`, sin `<iframe>`)
- [ ] Lite: sin Chart.js, sin backdrop-filter, compatible Chrome 95
- [ ] Lite: música sigue sonando (no se recarga la página)
- [ ] Cierre con X, Escape y tap fuera (solo modal)
- [ ] Proactivo (saludo matutino, cierre día, récord) puede mostrar whiteboard
- [ ] Cache busters actualizados en `index.php`
- [ ] AGENTS.md: si toca `app/voice.php` o `assets/app.js`, actualizar `?v=` en `index.php` ya cumple
