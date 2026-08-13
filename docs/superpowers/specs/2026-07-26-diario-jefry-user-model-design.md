# Diario + User Model — Jefry como memoria viva

**Fecha**: 2026-07-26
**Estado**: Aprobado, pendiente de planificación
**Depende de**: spec `2026-07-26-jefry-copilot-design.md` (Fase 1 imprescindible)

---

## Resumen

Jefry evoluciona de copiloto reactivo a **memoria viva del usuario**. Cada conversación alimenta automáticamente un diario y un modelo de usuario que Jefry mantiene y consulta. Sin comandos explícitos: Jefry escucha, clasifica, anota y aprende de forma pasiva y continua.

Tres componentes independientes pero interconectados:
1. **Diario** — registro diario compilado automáticamente desde fragmentos de conversación
2. **User Model** — perfil vivo: personalidad, proyectos, preocupaciones, decisiones, estado emocional
3. **Motor semántico** — embeddings + búsqueda por similitud para referencias cruzadas

---

## Arquitectura general

```
     USUARIO HABLA CON JEFRY (cualquier conversación, sin comandos)
                         │
                         ▼
┌──────────────────────────────────────────────────────────────┐
│  CONVERSATION PIPELINE (voice.php, se amplía)                │
│                                                              │
│  1. voice_handle_conversation() — flujo normal existente     │
│  2. voice_diary_classify() — LLM rápido (gpt-4o-mini)       │
│     └─ Clasifica cada utterance como:                        │
│        · personal_reflection (pena, ánimo, estado)           │
│        · project_idea (nueva idea, proyecto, plan)           │
│        · decision (decisión tomada sobre algo)               │
│        · worry (preocupación, problema, estrés)              │
│        · achievement (logro, avance, hito)                   │
│        · factual (dato objetivo, no personal)                │
│        · casual (small talk, saludo — NO se guarda)          │
│  3. voice_diary_extract() — si tipo != casual/factual:       │
│     └─ LLM extrae hecho estructurado:                        │
│        {tipo, titulo, resumen, mood, tags, confidence}       │
│  4. voice_diary_buffer_add() — guarda en buffer del día      │
│  5. voice_user_model_update_hot() — actualiza modelo en      │
│     caliente si confidence > threshold                       │
└──────────────────────┬───────────────────────────────────────┘
                       │
                       ▼ (al final del día o bajo demanda)
┌──────────────────────────────────────────────────────────────┐
│  NIGHTLY / LAZY PIPELINE                                     │
│                                                              │
│  1. voice_diary_compile_daily() — consolida buffer en entrada│
│     └─ LLM genera clean_text (narración coherente del día)   │
│     └─ Calcula mood_resumen, highlights, tags                │
│  2. voice_diary_embed() — genera embedding de clean_text     │
│  3. voice_diary_summarize_week() — si es domingo, resumen    │
│     semanal con embedding propio                             │
│  4. voice_user_model_prune() — consolida modelo, mergea      │
│     duplicados, baja peso a items antiguos                   │
│  5. voice_diary_buffer_clear() — limpia buffer del día       │
└──────────────────────────────────────────────────────────────┘
```

---

## Modelo de datos

### `data/diario.json`

```json
{
  "buffer": [
    {
      "ts": "2026-07-26 18:42:15",
      "tipo": "worry",
      "raw_text": "... transcripción literal ...",
      "clean_text": "Preocupación por el flujo de caja de Casawasap. Los pagos están entrando más lentos de lo esperado.",
      "mood": "preocupado",
      "tags": ["casawasap", "finanzas", "flujo de caja"],
      "confidence": 0.92
    }
  ],
  "entries": [
    {
      "fecha": "2026-07-25",
      "raw_text": "[concatenado de todos los raw_text del buffer del día]",
      "clean_text": "Hoy fue un día intenso. Empecé motivado pero terminé preocupado...",
      "embedding": [0.0123, -0.0456, ..., 0.0891],
      "embedding_model": "text-embedding-3-small",
      "mood": "preocupado",
      "highlights": [
        "Cerrada venta de Jostal por 180€",
        "Problema con WAHA en publi6",
        "Nueva idea: automatizar onboarding Casawasap"
      ],
      "tags": ["jostal", "waha", "ventas", "casawasap"],
      "fragmentos": 7,
      "compiled_at": "2026-07-26 02:15:00"
    }
  ],
  "weekly_summaries": [
    {
      "week": "2026-W30",
      "summary": "Semana enfocada en la expansión de Casawasap. Preocupación recurrente por el flujo de caja...",
      "mood_trend": "oscilante: motivado → preocupado → estable",
      "embedding": [...],
      "embedding_model": "text-embedding-3-small"
    }
  ],
  "meta": {
    "last_compiled": "2026-07-26",
    "last_weekly_summary": "2026-W30",
    "total_entries": 45
  }
}
```

### `voice_memory.json` (ampliado con `user_model`)

Se añade la clave `user_model` al JSON existente:

```json
{
  "conversation_history": [...],
  "long_term": {...},
  "user_model": {
    "personalidad": {
      "rasgos": ["perfeccionista", "practico", "directo", "autoexigente"],
      "estilo_comunicacion": "breve, sin rodeos, pragmático",
      "triggers_emocionales": ["injusticia", "ineficiencia", "falta de compromiso ajeno"],
      "fortalezas": ["resiliencia", "visión de negocio", "ejecución rápida"],
      "updated_at": "2026-07-26 18:42:15"
    },
    "proyectos": [
      {
        "id": "proj_exp_cw_001",
        "nombre": "Expansión Casawasap",
        "estado": "activo",
        "prioridad": "alta",
        "descripcion": "Abrir nueva vertical en Casawasap para captar más leads",
        "origen": "diario",
        "primera_mencion": "2026-07-20",
        "ultima_mencion": "2026-07-26",
        "menciones": 8,
        "hitos": [
          {"fecha": "2026-07-22", "que": "Definidos KPIs iniciales"}
        ]
      }
    ],
    "preocupaciones": [
      {
        "id": "wor_cw_cash_001",
        "tema": "flujo de caja Casawasap",
        "categoria": "finanzas",
        "intensidad": "alta",
        "frecuencia": 5,
        "primera_mencion": "2026-07-20",
        "ultima_mencion": "2026-07-26",
        "resuelta": false
      }
    ],
    "decisiones": [
      {
        "id": "dec_20260715_001",
        "que": "Pausar expansión hasta septiembre",
        "cuando": "2026-07-15",
        "contexto": "Flujo de caja ajustado, priorizar estabilidad",
        "consecuencias": ["Reducir gasto en ads temporalmente"],
        "vigente": true
      }
    ],
    "ideas_pendientes": [
      {
        "id": "idea_20260725_001",
        "que": "Automatizar onboarding de nuevas clientas Casawasap",
        "estado": "pendiente",
        "origen": "diario",
        "fecha": "2026-07-25",
        "tags": ["casawasap", "automation"]
      }
    ],
    "objetivos": [
      {
        "id": "obj_2026_001",
        "que": "Llegar a 100 clientas activas combinadas",
        "plazo": "2026-12",
        "progreso_percibido": 65,
        "metrica": "clientas_activas"
      }
    ],
    "estado_emocional": {
      "actual": {
        "mood": "preocupado",
        "intensidad": "media",
        "causa_probable": "flujo de caja",
        "desde": "2026-07-25"
      },
      "historico": [
        {"fecha": "2026-07-25", "mood": "preocupado"},
        {"fecha": "2026-07-24", "mood": "motivado"},
        {"fecha": "2026-07-23", "mood": "cansado"},
        {"fecha": "2026-07-22", "mood": "motivado"}
      ],
      "patron_semanal": "lunes motivado, miércoles cansado, jueves preocupado, viernes aliviado",
      "tendencia": "oscilante"
    },
    "updated_at": "2026-07-26 18:42:15"
  }
}
```

---

## Componentes — diseño detallado

### 1. Classification pipeline (`voice_diary_classify`)

**Archivo**: `app/voice.php`
**Función**: `voice_diary_classify(string $utterance): ?array`

**Flujo**:
1. Recibe el texto del usuario (post-transcripción limpia).
2. Llama al LLM con un prompt específico de clasificación.
3. Modelo: `gpt-4o-mini` (rápido, barato, suficiente para clasificar).
4. Timeout: 5s. Si falla, no bloquea la conversación — simplemente no se guarda.
5. Devuelve `{tipo, confidence}` o `null` si es `casual`.

**Prompt de clasificación**:
```
Eres un clasificador de intención personal. Analiza el siguiente mensaje del usuario y clasifícalo en UNA de estas categorías:

- personal_reflection: reflexión sobre su estado de ánimo, cómo se siente, cansancio, motivación
- project_idea: nueva idea de proyecto, plan de negocio, mejora de proceso
- decision: tomó una decisión explícita sobre algo
- worry: preocupación, problema, fuente de estrés o ansiedad
- achievement: logro, avance importante, hito, algo bueno que pasó
- factual: dato objetivo, información neutra (no personal)
- casual: saludo, small talk, pregunta genérica, no merece guardarse

Responde SOLO en JSON: {"tipo": "...", "confidence": 0.XX}
```

**Caché**: Si la misma utterance (normalizada) se clasificó en los últimos 5 minutos, usar resultado cacheado.

---

### 2. Extraction pipeline (`voice_diary_extract`)

**Archivo**: `app/voice.php`
**Función**: `voice_diary_extract(string $utterance, string $tipo): ?array`

**Flujo**:
1. Si `tipo` es `casual` o `factual` → no se extrae (return null).
2. Si `confidence` < 0.7 → se descarta (evitar ruido).
3. LLM extrae hecho estructurado.
4. Modelo: `gpt-4o-mini` (rápido).
5. Timeout: 5s. Si falla, se guarda solo raw_text con tipo y confidence=0.5.

**Prompt de extracción**:
```
Extrae la información relevante del siguiente mensaje del usuario como un hecho de diario personal.

Tipo detectado: {tipo}

Devuelve SOLO JSON:
{
  "titulo": "resumen en 8 palabras máximo",
  "resumen": "1-2 frases capturando la esencia",
  "mood": "motivado|preocupado|cansado|feliz|frustrado|neutro|ilusionado|estresado",
  "tags": ["tag1", "tag2"]
}
```

---

### 3. Buffer system (`voice_diary_buffer_add`)

**Archivo**: `app/voice.php`
**Funciones**: `voice_diary_buffer_add(array $item): void`, `voice_diary_buffer_get_today(): array`, `voice_diary_buffer_clear(): void`

Los fragmentos se acumulan en `data/diario.json → buffer`. Máximo 30 fragmentos por día (si se supera, eliminar los de menor confidence).

---

### 4. Daily compilation (`voice_diary_compile_daily`)

**Archivo**: `app/voice.php`
**Función**: `voice_diary_compile_daily(?string $fecha = null): array`

**Trigger**: proceso nocturno (cron o lazy trigger al iniciar conversación del día siguiente).

**Flujo**:
1. Coge todos los fragmentos del buffer del día.
2. Si hay < 2 fragmentos → no compila entrada (día sin suficiente sustancia).
3. Concatena raw_text de todos los fragmentos → `raw_text` de la entrada.
4. LLM (modelo principal, ej. deepseek-v4-pro o gpt-4o) genera `clean_text`:
   - Narración coherente en primera persona del singular.
   - Respeta el tono del usuario.
   - No inventa hechos no mencionados.
   - Estructura: mañana / tarde / noche si hay suficiente material.
5. Calcula `mood` predominante del día (moda de moods de fragmentos, con peso por confidence).
6. Extrae `highlights` (3-5 bullet points de lo más relevante).
7. Consolida `tags`.
8. Guarda en `entries[]`.
9. Dispara `voice_diary_embed()` para esta entrada.

---

### 5. Embedding system (`voice_diary_embed`)

**Archivo**: `app/voice.php`
**Funciones**: `voice_diary_embed(string $text): array`, `voice_diary_search_similar(string $query, int $topK = 3): array`, `voice_diary_embed_all_pending(): void`

**API**: OpenAI `text-embedding-3-small` (1536 dimensiones).
**Coste**: ~$0.02 por millón de tokens. Una entrada típica de diario (500 tokens) cuesta ~$0.00001. Irrisorio.

**Búsqueda semántica** (`voice_diary_search_similar`):
1. Genera embedding del query.
2. Calcula cosine similarity contra todos los embeddings de `entries[]` y `weekly_summaries[]`.
3. Devuelve top-K con score y fragmento relevante.

**Caché de embeddings**: Si un texto ya tiene embedding, no se regenera. Se guarda `embedding_model` junto al embedding para detectar cambios de modelo.

---

### 6. User model — hot update (`voice_user_model_update_hot`)

**Archivo**: `app/voice.php`
**Función**: `voice_user_model_update_hot(array $diaryItem): void`

Se ejecuta en caliente (durante la conversación) solo si `confidence > 0.85`.

**Lógica por tipo**:

| Tipo | Acción en user_model |
|---|---|
| `personal_reflection` | Actualiza `estado_emocional.actual`. Añade a `personalidad.triggers_emocionales` si es nuevo. |
| `project_idea` | Si es nuevo proyecto → añade a `proyectos[]`. Si menciona proyecto existente → actualiza `ultima_mencion`, incrementa `menciones`. |
| `decision` | Añade a `decisiones[]`. Busca si contradice decisión anterior → marca anterior como `vigente: false`. |
| `worry` | Si es nuevo tema → añade a `preocupaciones[]`. Si es existente → incrementa `frecuencia`, actualiza `ultima_mencion`. Si el usuario indica que se resolvió → `resuelta: true`. |
| `achievement` | Si relacionado con proyecto → añade `hito` al proyecto. Si es objetivo → actualiza `progreso_percibido`. |

---

### 7. User model — nightly prune (`voice_user_model_prune`)

**Archivo**: `app/voice.php`
**Función**: `voice_user_model_prune(): void`

**Operaciones**:
1. **Preocupaciones**: si `ultima_mencion > 14 días` y `resuelta: false` → baja `intensidad` un nivel. Si > 30 días sin mención → `resuelta: true`.
2. **Proyectos**: si `ultima_mencion > 30 días` → `estado: "pausado"`. Si > 90 días → `estado: "abandonado"`. Si se menciona de nuevo → reactivar.
3. **Decisiones**: si `vigente: true` pero hay decisión posterior que la contradice → `vigente: false`.
4. **Ideas pendientes**: si > 60 días sin mención → `estado: "archivada"`.
5. **Estado emocional**: consolida `historico` (últimos 30 días), recalcula `tendencia` y `patron_semanal`.
6. **Deduplicación**: mergea proyectos/preocupaciones/ideas con similitud > 80% en título.

---

### 8. Integration con conversation handler

**Archivo**: `app/voice.php`
**Función**: `voice_handle_conversation()` — se modifica

**Antes** de llamar al LLM de conversación, se inyecta contexto del diario y user model:

```
System prompt de Jefry + 
  └─ voice_user_model_inject_context() → resumen compacto del user model
  └─ voice_diary_inject_recent() → highlights de últimos 7 días
  └─ voice_diary_inject_relevant() → si el usuario menciona un tema concreto,
     buscar top-3 entradas similares e inyectar
```

**Después** de la respuesta del LLM:
1. Para cada utterance del usuario en este turno → `voice_diary_classify()`.
2. Si es diarizable → `voice_diary_extract()` → `voice_diary_buffer_add()`.
3. Si confidence > 0.85 → `voice_user_model_update_hot()`.

**Formato del contexto inyectado** en system prompt:

```
[PERFIL DEL USUARIO — ACTUALIZADO HOY]
Personalidad: perfeccionista, práctico, directo.
Estado actual: preocupado (por flujo de caja).
Proyectos activos: Expansión Casawasap (prioridad alta).
Preocupaciones recurrentes: flujo de caja Casawasap (5 días esta semana).
Última decisión importante: Pausar expansión hasta septiembre (15 julio).

[DIARIO — ÚLTIMOS 7 DÍAS]
- 25 jul: Día intenso. Venta Jostal 180€. Problema WAHA. Idea: automatizar onboarding.
- 24 jul: Día motivado. Avances en publicista. 3 leads nuevos.
- 23 jul: Cansado. Mucha carretera. Preocupación por gastos.

[ENTRADAS RELACIONADAS con el tema actual]
(solo si el usuario menciona algo que trigger búsqueda semántica)
- 20 jul: "El flujo de caja de Casawasap me está quitando el sueño..." (score: 0.89)
```

---

### 9. Comportamiento proactivo de Jefry

Jefry usa el diario y user model para ser proactivo en momentos clave:

| Trigger | Comportamiento |
|---|---|
| **Inicio de conversación** | Si hay entrada de ayer: "Ayer fue un día {mood}. ¿Quieres que hablemos de {highlight}?" Si no hay entrada: "Ayer no hablamos mucho. ¿Todo bien?" |
| **Mismo tema 3+ días** | "Llevas 3 días mencionando {preocupación}. ¿Quieres que lo miremos juntos?" |
| **Idea sin resolver** | "El 20 de julio tuviste una idea sobre {idea}. ¿Has avanzado algo?" |
| **Decisión que caduca** | "En julio decidiste pausar {proyecto} hasta septiembre. Ya es septiembre. ¿Lo reactivamos?" |
| **Mood decay** | Si detecta 3+ días seguidos de mood negativo: "Te noto bajo de ánimo estos días. ¿Quieres hablar de ello?" |
| **Aniversario de decisión** | "Hace justo 3 meses decidiste {decision}. ¿Cómo ha ido desde entonces?" |
| **Consulta explícita** | "¿Qué escribí el lunes?" → búsqueda semántica + devuelve entrada. "¿Tenía ideas pendientes?" → lista ideas_pendientes. |

---

### 10. UI — Tab "Diario" en Josue

**Archivos**: `app/views.php`, `assets/app.js`, `assets/lite.css` (si aplica a lite)
**URL**: `?page=josue&tab=diario`

**Endpoints**:
- `action=get_diario_entries` → devuelve lista paginada de entradas
- `action=get_diario_entry` → devuelve una entrada completa (limpia + literal + metadata) por fecha
- `action=search_diario` → búsqueda full-text + semántica

**UI** (solo lectura):

```
┌──────────────────────────────────────────────────┐
│  DIARIO                    🔍 Buscar...          │
│  ─────────────────────────────────────────────── │
│  📅 Julio 2026                          [Filtros]│
│                                                   │
│  ┌─ Vie 25 ──────────────────────────────────┐   │
│  │ 😟 preocupado                              │   │
│  │                                             │   │
│  │ Hoy fue un día intenso. Empecé motivado     │   │
│  │ pero terminé preocupado por el flujo de     │   │
│  │ caja de Casawasap. Por la mañana logré      │   │
│  │ cerrar una venta de Jostal por 180€...      │   │
│  │                                             │   │
│  │ [📝 Ver transcripción literal]              │   │
│  │                                             │   │
│  │ ⚡ Highlights:                              │   │
│  │  • Cerrada venta Jostal 180€                │   │
│  │  • Problema con WAHA en publi6             │   │
│  │  • Idea: automatizar onboarding             │   │
│  │                                             │   │
│  │ 🏷 tags: jostal, waha, ventas, casawasap    │   │
│  └────────────────────────────────────────────┘   │
│                                                   │
│  ┌─ Jue 24 ──────────────────────────────────┐   │
│  │ 😊 motivado                                │   │
│  │ Día motivado. Buenos avances en            │   │
│  │ publicista. 3 leads nuevos...              │   │
│  └────────────────────────────────────────────┘   │
│                                                   │
│                     [Cargar más]                    │
└──────────────────────────────────────────────────┘
```

**Sidebar** (colapsable en móvil):
- Total días registrados
- Mood promedio
- Tags más frecuentes (nube)
- Rachas (días consecutivos con entrada)

**Filtros**:
- Por mood (dropdown con emojis)
- Por tag
- Por tipo de contenido (preocupación / idea / decisión / logro)
- Por rango de fechas

---

## API calls y coste estimado

| Llamada | Modelo | Frecuencia | Coste estimado |
|---|---|---|---|
| Classify utterance | gpt-4o-mini | ~30-50/día | ~$0.005/día |
| Extract hecho | gpt-4o-mini | ~15-25/día | ~$0.005/día |
| Compile daily entry | deepseek-v4-pro / gpt-4o | 1/día | ~$0.01/día |
| Generate embedding | text-embedding-3-small | 1/día (nueva entrada) + 1/semana (resumen) | <$0.001/día |
| Search embedding | text-embedding-3-small | ~3-5/día (bajo demanda) | <$0.001/día |
| **Total mensual estimado** | | | **~$0.50-1.00/mes** |

---

## Archivos modificados

| Archivo | Cambios |
|---|---|
| `app/voice.php` | Nuevas funciones: `voice_diary_classify`, `voice_diary_extract`, `voice_diary_buffer_add/get_today/clear`, `voice_diary_compile_daily`, `voice_diary_embed`, `voice_diary_search_similar`, `voice_diary_summarize_week`, `voice_user_model_update_hot`, `voice_user_model_prune`, `voice_user_model_inject_context`, `voice_diary_inject_recent`, `voice_diary_inject_relevant`. Modificación: `voice_handle_conversation` para integrar pipeline. |
| `app/actions.php` | Nuevas acciones: `voice_proactive_diary` (para triggers proactivos del diario), `get_diario_entries`, `get_diario_entry`, `search_diario` |
| `app/views.php` | Nuevo tab `diario` en `render_josue_page()` — timeline de entradas, filtros, sidebar |
| `assets/app.js` | JS para tab diario: carga lazy, scroll infinito, toggle transcripción literal, búsqueda, filtros |
| `assets/lite.css` | Estilos para `.josue-diary-*` (si se incluye en lite) |
| `index.php` | Actualizar data-attributes si es necesario; `?tab=diario` routing |
| `data/diario.json` | Nuevo archivo de datos |
| `data/voice_memory.json` | Ampliado con clave `user_model` |

**~600-800 líneas totales nuevas. 7 archivos modificados + 1 nuevo. 0 dependencias externas nuevas.**

---

## Lo que NO se implementa (YAGNI)

- Edición de entradas del diario (solo lectura)
- Exportación del diario (PDF, CSV)
- Imágenes o adjuntos en entradas
- Compartición del diario con otros usuarios
- Gamificación del diario (rachas, badges)
- Motor de recomendación complejo (basta con búsqueda semántica + reglas)
- Base de datos vectorial externa (Pinecone, Weaviate) — los embeddings se guardan inline en JSON
- Fine-tuning de modelos con datos del diario
- Análisis de sentimiento avanzado (basta con clasificación por LLM)
- Integración con calendario externo

---

## Dependencias y orden de implementación

```
1. Sistema de buffer + compile (diario.json)          ← base de todo
    ↓
2. Classification + Extraction pipeline               ← alimenta el buffer
    ↓
3. User model (voice_memory.json ampliado)            ← se nutre del pipeline
    ↓
4. Integración con conversation handler               ← Jefry "sabe" cosas
    ↓
5. Embeddings + búsqueda semántica                    ← búsqueda precisa
    ↓
6. Comportamiento proactivo basado en diario/modelo   ← la magia
    ↓
7. UI Tab Diario en Josue                             ← consulta visual
```

---

## Verificación

- [ ] Las llamadas a LLM de clasificación/extracción no bloquean la conversación si fallan (timeout 5s)
- [ ] El buffer del día no supera 30 fragmentos (podar por confidence)
- [ ] Los embeddings se cachean por texto (mismo texto = mismo embedding, no se recalcula)
- [ ] El contexto inyectado en system prompt no supera 500 tokens (~20% del contexto total)
- [ ] `voice_user_model_prune` se ejecuta como máximo 1 vez al día
- [ ] La búsqueda semántica solo se dispara si el usuario menciona un tema concreto (no en cada conversación)
- [ ] El diario es solo lectura desde UI (no hay endpoints de escritura/edición)
- [ ] El tab diario no bloquea la carga de Josue (carga lazy/asíncrona)
- [ ] Compatible con Lite (coche): sin animaciones pesadas, CSS bajo 10K líneas
- [ ] Sin dependencias nuevas de composer/npm
