# Jefry Copilot — Diseño completo

**Fecha**: 2026-07-26
**Estado**: Aprobado, pendiente de implementación
**Modo**: Lite (coche, RK3566, Chrome 95, Android 8.1)

---

## Resumen

Jefry evoluciona de asistente por voz a copiloto virtual completo. Cuatro fases independientes que comparten un motor de habla proactiva con ducking de música. Todo funciona sobre el sistema de voz existente sin reescribirlo.

---

## Arquitectura compartida

### Motor proactivo (`_voiceProactive`)

Nueva capa en `app.js` que unifica todo el habla proactiva:

```
_voiceProactive.speak(text, { importance, duckMusic })
   ├─ duckMusic=true  → baja música a 20% (Jefry inicia)
   ├─ duckMusic=false → pausa total (usuario inició)
   ├─ TTS
   └─ onend → restaura volumen original
```

**Comportamiento canónico de la música**:

| Quién inicia | Música durante | Al terminar |
|---|---|---|
| Usuario (wake word / botón) | Pausa total | Resume |
| Jefry proactivo (saludo, alerta) | Baja a 20% | Restaura volumen |
| Usuario responde a Jefry proactivo | Pausa total | Resume |

### Endpoint proactivo

`action=voice_proactive` en `actions.php`. Ligero, solo recibe `trigger` y devuelve `{message, tts_text}`. No usa la pipeline de comandos.

### Datos disponibles (sin implementar nuevos)

| Dato | Origen | Estado |
|---|---|---|
| Ingresos hoy/ayer | `data/ingresos.json` | ✅ Existe |
| Clima | `TOOL:weather` en `voice.php` | ✅ Existe |
| GPS KPIs | `gps_kpi_summary()` | ✅ Existe |
| Récord del mes | Cálculo sobre ingresos del mes | ⚠️ Nueva función, ~15 líneas |
| Clientas pendientes | `data/clientas.json` filtro estado | ✅ Existe |
| Zonas frecuentes (casa/trabajo) | Inferidas de GPS: punto más frecuente mañana vs noche | ⚠️ Nueva función, ~20 líneas |
| Velocidad GPS actual | Timestamps + coordenadas de touches consecutivos | ✅ Existe |
| Contador interacciones diarias | Incrementa en cada comando de voz | ⚠️ Nueva variable, ~5 líneas |

---

## Fase 1 — Rituales y Personalidad

### 1.1 Saludo matutino

**Trigger**: página lite carga por primera vez en el día, 6:00-12:00h.
**Deduplicación**: `localStorage.jefry_last_morning` guarda la fecha.

**Flujo**:
1. Frontend verifica condiciones
2. `POST action=voice_proactive trigger=morning_greeting`
3. Backend consulta weather + ingresos ayer + clientas pendientes + récord del mes
4. `_voiceProactive.speak()` con ducking

**Ejemplo**:
> "Buenos días. 14°C en Madrid, máxima de 22°C. Ayer facturaste 230€ con 4 clientas. Hoy tienes 3 pendientes de contacto. Es jueves, ánimo que ya queda poco para el finde."

**Nueva función**: `voice_build_morning_greeting()` en `voice.php`.

### 1.2 Cierre del día

**Trigger**: hora > 20:00 + GPS 30min parado + hubo movimiento hoy.
**Deduplicación**: `localStorage.jefry_last_evening`.

**Ejemplo**:
> "Fin del día. 47 kilómetros, 2 clientas, 180 euros. ¿Algo más que apuntar?"

Si el usuario responde "sí" → entra en Modo Eureka automático.

### 1.3 Modo celebración

**Trigger**: `ingresos_hoy > mejor_día_del_mes_actual` y no celebrado ya hoy.
**Check point**: page load, post-comando voz, GPS touch.

**Ejemplo**:
> "¡Récord del mes! 380 euros hoy. Eres un máquina."

**Visual**: partículas de confeti CSS sobre el reproductor (3 segundos, solo opacity + transform).

### 1.4 Frases ingeniosas

**Trigger**: 30min sin interacción de voz, GPS en movimiento. Máx. 1 por trayecto.

**Pool**: ~20 frases con datos reales del CRM. Tipos:
- Dato curioso: *"¿Sabías que tu clienta más antigua es María, desde marzo 2025?"*
- Estadística: *"Llevas 847 leads creados desde que empezaste."*
- Motivacional: *"+22% de ingresos vs el mes pasado a estas alturas."*
- Efeméride: *"Tal día como hoy hace 3 meses cerraste tu primera venta de Casawasap."*
- Humor: *"Si tus clientas fueran un equipo de fútbol, hoy jugarían en primera."*
- Meta: *"Te quedan 180€ para tu objetivo del mes."*

**Selección**: aleatoria entre frases con datos disponibles. Sin repetir el mismo día (`localStorage`).

### 1.5 Catálogo completo de frases proactivas

Además de las frases ingeniosas (1.4), Jefry lanza frases contextuales según lo que detecta del entorno. **Máximo 1 frase proactiva cada 15 minutos** para no saturar. Las frases del mismo tipo no se repiten el mismo día.

#### 📍 GPS / Ubicación

| # | Trigger | Frase | Prio | Datos |
|---|---|---|---|---|
| 1 | GPS se acerca a zona casa (< 2km) | *"Ya casi llegas a casa. ¿Te esperan?"* | 🥇 | Zona "casa" inferida (punto más frecuente al final del día) |
| 2 | GPS se acerca a zona trabajo (< 2km) | *"Ya casi llegas. ¿Preparado para el día?"* | 🥇 | Zona "trabajo" (punto más frecuente por la mañana) |
| 3 | Ruta actual diverge >500m de ruta habitual | *"Veo que hoy vamos por otro camino. ¿Aventura o recado?"* | 🥇 | Historial rutas misma franja horaria |
| 4 | GPS muestra ETA > 10 min basado en velocidad | *"A este ritmo te quedan unos 15 minutos."* | 🥇 | Velocidad media + distancia a destino frecuente |
| 5 | Velocidad < 10 km/h sostenida >3 min | *"Parece que hay atasco. ¿Pongo las noticias?"* | 🥇 | GPS touches consecutivos con baja velocidad |
| 6 | 1h continua de conducción | *"Llevas una hora al volante. ¿Paramos un momento?"* | 🥇 | Timer desde inicio de ruta |
| 7 | Cruce de límite municipal (si geocoding disponible) | *"Acabas de entrar en [Alcorcón]. ¿Vienes a ver a alguien?"* | 🥉 | Requiere geocoding inverso (API externa opcional) |
| 8 | Velocidad > 100 km/h sostenida | *"Vas a buena marcha hoy. ¿Autovía?"* | 🥇 | GPS touches |

#### 🧠 Check‑ins / Estado de ánimo

| # | Trigger | Frase | Prio | Datos |
|---|---|---|---|---|
| 9 | Primera interacción del día (saludo matutino) | *"¿Cómo te sientes hoy? ¿Energía a tope o café urgente?"* | 🥇 | Hora + flag primera interacción |
| 10 | 5+ min de silencio en ruta activa | *"Te noto callado. ¿Todo bien o prefieres que no te moleste?"* | 🥇 | Timer inactividad de voz |
| 11 | Buenos números CRM (ingresos > media diaria) | *"Parece que hoy ha sido un buen día. ¿Lo celebramos con música?"* | 🥈 | `TOOL:crm` |
| 12 | Malos números CRM (ingresos < media diaria) | *"Hoy ha sido un día tranquilo de ventas. Mañana más y mejor."* | 🥈 | `TOOL:crm` |
| 13 | GPS parado > 10 min en hora de comida (13‑15h) | *"Son las 2. ¿Has comido ya? No trabajes en ayunas."* | 🥈 | Hora + GPS estático |
| 14 | Anochece (hora de ocaso, ~21h verano) | *"Ya es de noche. Conduce con cuidado."* | 🥈 | Hora ocaso calculada con fecha/latitud |
| 15 | 3 días seguidos superando media diaria | *"Llevas 3 días seguidos por encima de tu media. ¡Estás en racha!"* | 🥈 | Historial ingresos 3 días |
| 16 | No se ha registrado gasto hoy y son >18h | *"Hoy no has apuntado ningún gasto todavía. ¿Seguro que no te dejas nada?"* | 🥈 | `TOOL:crm` |

#### 🎵 Música / Entretenimiento

| # | Trigger | Frase | Prio | Datos |
|---|---|---|---|---|
| 17 | Arranque de ruta sin música sonando | *"¿Qué música quieres? ¿Algo tranquilo o marcha?"* | 🥇 | Estado del reproductor YouTube |
| 18 | 30 min con mismo canal/género | *"Llevamos media hora con este estilo. ¿Seguimos o cambiamos?"* | 🥇 | Timer + canal actual |
| 19 | Misma canción 3+ veces en el día | *"Esta canción la has escuchado 3 veces hoy. ¿Tu favorita del momento?"* | 🥇 | Contador plays por videoId |
| 20 | Anochece con música enérgica/movida | *"Está anocheciendo. ¿Pongo algo más tranquilo?"* | 🥈 | Hora ocaso + keywords actuales |
| 21 | Fin de semana + mañana | *"Es sábado. ¿Música de finde o seguimos con lo de siempre?"* | 🥈 | Día de la semana |
| 22 | Letra/datos indican canción triste/lenta | *"Esa era profunda. ¿Subimos el ánimo con algo más alegre?"* | 🥉 | Keywords del título (detección palabras tristeza) |

#### 📅 Efemérides / Agenda

| # | Trigger | Frase | Prio | Datos |
|---|---|---|---|---|
| 23 | Lunes | *"Lunes. Ánimo, que la semana acaba de empezar."* | 🥇 | `new Date().getDay()` |
| 24 | Viernes | *"¡Viernes! Esta noche hay que celebrarlo."* | 🥇 | `new Date().getDay()` |
| 25 | Principio de mes (día 1‑3) | *"Mes nuevo. ¿Algún objetivo para julio?"* | 🥈 | `new Date().getDate()` |
| 26 | Final de mes (día 28‑31) | *"Últimos días del mes. ¿Llegamos al objetivo?"* | 🥈 | `new Date().getDate()` |
| 27 | Aniversario del primer lead registrado | *"Tal día como hoy empezaste con LaMami. Cuánto has crecido."* | 🥉 | `voice_memory.json` → `stats.first_interaction` |
| 28 | Temperatura extrema (>35°C o <5°C) | *"Hace 36 grados fuera. Hidrátate bien."* | 🥈 | `TOOL:weather` |

#### 🎯 Motivación / Negocio

| # | Trigger | Frase | Prio | Datos |
|---|---|---|---|---|
| 29 | Objetivo mensual > 85% alcanzado | *"Estás al 87% de tu objetivo mensual. ¡A por ello!"* | 🥈 | Ingresos mes / objetivo configurado |
| 30 | Objetivo mensual superado | *"¡Objetivo del mes superado! Todo lo que venga ahora es bonus."* | 🥈 | Ingresos mes / objetivo |
| 31 | Rama de negocio > 5 días sin actividad | *"Hace 5 días que no tocas Jostal. ¿Todo bien por allí?"* | 🥈 | Actividad por rama (último ingreso/lead) |
| 32 | Mejor semana del año (comparativa) | *"Esta ha sido tu mejor semana en todo 2026. Enhorabuena."* | 🥈 | Comparativa semanal de ingresos |

#### 💬 Conversación casual / Humor

| # | Trigger | Frase | Prio | Datos |
|---|---|---|---|---|
| 33 | 45 min de silencio en ruta larga | *"Oye, ¿y si me cuentas algo? Un cotilleo, un sueño, lo que sea."* | 🥈 | Timer |
| 34 | Tercera interacción de voz del día | *"Tercera vez que hablamos hoy. Ya somos amigos."* | 🥈 | Contador de interacciones diarias |
| 35 | Usuario dice "gracias Jefry" | *"De nada. Para eso estamos los copilotos."* | 🥇 | Detección de gratitud en comando |
| 36 | Usuario insulta/se queja del tráfico | *"Respira hondo. El tráfico no merece tu energía."* | 🥉 | Detección de frustración en texto |
| 37 | Llueve (weather report) | *"Está lloviendo. Cuidado con el suelo mojado. Y qué bonito suena en el techo."* | 🥈 | `TOOL:weather` |
| 38 | GPS parado en zona nueva + hora comida | *"No te conozco este sitio. ¿Sitio nuevo para comer?"* | 🥈 | GPS estático en coordenada poco frecuente |
| 39 | Velocidad GPS muestra aceleración brusca | *"Con calma, que no hay prisa."* | 🥉 | Delta velocidad entre touches consecutivos |
| 40 | Usuario lleva > 2h sin música ni voz | *"Llevamos un rato en silencio total. ¿Te pongo algo de ambiente?"* | 🥈 | Timer sin interacción + sin música |

**Reglas anti‑spam**:
- Máximo 1 frase proactiva cada 15 minutos.
- No repetir la misma frase (mismo ID) en el mismo día.
- Las frases de prioridad 🥉 son opcionales; si los datos no están disponibles se omiten sin error.
- Si hay música sonando, el ducking baja el volumen al 20% mientras Jefry habla.

---

## Fase 2 — CRM Conversacional

Todas son tools nuevas en el conversation handler de `voice.php`. No tocan la pipeline de comandos.

### 2.1 "¿Cómo va el día?" — `TOOL:crm` (refactor)

**Ya existe**, se enriquece con más datos:

```
💰 Ingresos:  180€ (3 op.)
💸 Gastos:     45€ (1 gasto)
📈 Beneficio:  135€
👥 Leads:       2 (1 LaMami, 1 Jostal)
📞 Contactos:   4 clientas
📊 vs ayer:   +15%
📊 vs media:   +8%
```

### 2.2 "¿A quién llamo hoy?" — `TOOL:prioritize_clients`

**Algoritmo de scoring** (sin ubicaciones):

```
score = días_sin_contacto/30 * 40
      + (en_casa ? 30 : 0)
      + min(ingresos_totales/500, 20)
      + (lead_nuevo ? 25 : 0)
      - (contactada_hoy ? 50 : 0)
```

Devuelve top 3 con justificación.

### 2.3 Alerta campaña parada — `TOOL:campaign_health`

**Trigger**: chequeo proactivo cada ~4h (poller ligero en frontend, se cuelga del ciclo GPS existente).

Detecta campañas activas con `ultima_publicacion > 3 días`.

> "La campaña de Nuria lleva 5 días sin publicarse. ¿La reactivo?"

### 2.4 Predicción del mes — `TOOL:forecast`

**Cálculo**: `ingresos_mes + (media_diaria * días_restantes) - bonus_finde`.

**Ejemplo**:
> "A 5 días de cerrar julio, proyectas 4.050€. Sería tu 2º mejor mes del año."

---

## Fase 3 — GPS Inteligente

Sin ubicaciones de clientas. Centrado en el coche como entidad.

### 3.1 Parking Memory — `TOOL:parking`

**Guardar**: *"Oye Jefry, recuerda dónde aparqué"* → guarda `{lat, lng, ts}` en `localStorage`.

**Recuperar**: *"¿Dónde aparqué?"* → calcula distancia y orientación cardinal desde posición actual (N, NE, E, SE, S, SW, W, NW).

**Auto-limpieza**: si el coche se mueve >500m del parking, se borra.

### 3.2 Registro automático de paradas

**Trigger**: GPS detecta parada de 2-10 min seguida de reanudación de movimiento. No preguntado en los últimos 30 min.

> "Has parado 4 minutos. ¿Apunto algo? Di 'gasolina 30 euros' o 'nada'."

Respuesta se procesa como `add_gasto` normal.

### 3.3 Stats de ruta (integrado en cierre del día, Fase 1)

Usa `gps_kpi_summary()` existente. Ya implementado.

---

## Fase 4 — DJ Jefry con ML simple

### 4.1 Perfil musical

Estructura en `localStorage` (`jefry_music_profile`, ~3KB):

- **canales**: `{name: {score, plays, skips, dislikes, last}}`
- **keywords**: `{word: {score, uses}}`
- **horas**: `{hour: {score, plays}}`
- **moods**: `{mood: {score, uses}}`

Scores entre -1 y 1.

### 4.2 Ciclo de aprendizaje

| Evento | Canal | Keywords | Hora |
|---|---|---|---|
| Like explícito | +0.25 | +0.20 | +0.15 |
| Auto-completa | +0.08 | +0.05 | +0.03 |
| Skip manual | -0.15 | -0.08 | -0.05 |
| Dislike | -0.40 | -0.25 | -0.10 |

Fórmula: exponential moving average con weight adaptativo (1.0 al inicio, 0.5 tras 20 plays).

### 4.3 Selección de query

1. Pool de ~15 queries predefinidas con keywords asociadas
2. Cada query recibe score = media(keywords scores) + preferencia horaria × 0.3 + ruido aleatorio
3. Epsilon-greedy: 85% mejor query, 15% aleatoria (exploración)

### 4.4 Comandos de voz

Control por voz procesado en frontend (sin backend, respuesta instantánea):

| Comando | Acción | Actualiza perfil |
|---|---|---|
| "Pon música" | Búsqueda optimizada | ✗ |
| "Me gusta" / "temazo" | Like | ✅ |
| "No me gusta" / "quita esto" | Dislike | ✅ |
| "Siguiente" / "otra" | Skip | ✅ |
| "Pon música alegre/tranquila/electrónica" | Búsqueda con mood/keyword | ✗ |
| "Pon lo mismo de siempre" | Canal con mejor score | ✗ |
| "Sorpréndeme" | 100% exploración | ✗ |
| "¿Qué gustos tengo?" | Resumen del perfil | ✗ |

### 4.5 Visualización

Barra de confianza (%) en el cassette deck + botones like/dislike/skip sobre la parte inferior del reproductor (visibles solo al tocar o en modo voz).

---

## Lo que NO se implementa (YAGNI)

- ML de recomendación musical pesado (basta con multi-armed bandit + features)
- APIs externas de gasolineras
- Tabla `clienta_locations`
- Reconocimiento de voz del cliente
- Multi-idioma real (inglés TTS no cambia backend)
- Nuevos dashboards de analytics

---

## Archivos modificados

| Archivo | Fases | Qué contiene |
|---|---|---|
| `app/voice.php` | 1, 2 | `voice_handle_proactive()`, `voice_build_morning_greeting()`, `voice_build_evening_wrapup()`, `voice_build_celebration()`, pool de ~60 frases (ingeniosas + proactivas), tools: `TOOL:prioritize_clients`, `TOOL:campaign_health`, `TOOL:forecast`, refactor `TOOL:crm` |
| `app/actions.php` | 1 | `case 'voice_proactive'` |
| `assets/app.js` | 1, 2, 3, 4 | `_voiceProactive` (ducking + speak), detección triggers (matutino, cierre, celebración), frases proactivas con anti-spam 15min, zonas frecuentes (casa/trabajo), ruta divergente, velocidad/atasco, `TOOL:parking`, paradas automáticas, perfil musical ML, `_buildWakeWords`, comandos música |
| `assets/lite.css` | 1, 4 | Confeti CSS, barra confianza DJ |
| `app/views.php` | 4 | Botones like/dislike/skip en reproductor |
| `index.php` | - | Data-attributes wake word (ya implementado) |
| `app/storage.php` | - | Defaults `voice_wake_enabled`, `voice_wake_word` (ya implementado) |

**~950 líneas totales. 7 archivos. 0 dependencias nuevas.**

---

## Dependencias entre fases

```
Fase 1 (rituales)  ← imprescindible, todo se apoya aquí
   ↓
Fase 2 (CRM)       ← independiente, puede ir en paralelo con 3
   ↓
Fase 3 (GPS)       ← usa Fase 1 para notificaciones, usa Fase 2 para datos clientas
   ↓
Fase 4 (DJ)        ← usa Fase 1 para radio matutina, independiente en lo demás
```

---

## Verificación

- [ ] Todas las animaciones CSS usan solo opacity/transform (compatible con `.is-lite * { animation-duration: 0.01ms }`)
- [ ] Wake Word Copilot ya lee `voice_wake_enabled` y `voice_wake_word` de settings
- [ ] Ducking de música funciona con YouTube y Radio en Directo
- [ ] `TOOL:crm`, `TOOL:weather`, `TOOL:date` ya existen y funcionan
- [ ] `gps_kpi_summary()`, `gps_distance_m()` ya existen en `helpers.php`
- [ ] Modo Eureka ya existe para notas rápidas
- [ ] El conversation handler ya soporta tools con `voice_execute_tools_from_response()`
- [ ] Máximo 1 frase proactiva cada 15 minutos (anti-spam)
- [ ] Las frases proactivas no se repiten el mismo día (tracking por ID en localStorage)
- [ ] Las frases de prioridad 🥉 se omiten silenciosamente si los datos no están disponibles
