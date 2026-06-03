# Tasks por fases

## UNIFICACION-LINEAS — Unificación comercial-lineas ↔ josue-telefonos

### FASE-1-VERIFICACION-CRUZADA

- [x] T1.1 — Inventariar todos los consumidores de `telefonos.json` (20 ocurrencias, 7 archivos, 4 URL-dependientes).
- [x] T1.2 — Verificar `comercial_line_state.json`: 0 dependencias de la UI de Josue, 100% autónomo.
- [x] T1.3 — Analizar voice.php: 3 puntos a modificar (resolver L2425, tab hints L714, AI prompt L822).
- [x] T1.4 — Inventario completo de URLs `page=josue&tab=telefonos`: 5 activas (views.php x3, actions.php x2) + 12 en backups.

### FASE-2-UNIFICAR-CORE

- [x] T2.1 — Añadir formulario CRUD (crear/editar) en Comercial > Líneas (`app/comercial.php` L6413).
- [x] T2.2 — Cambiar redirects de `action_save_telefono()` y `action_delete_telefono()` a `comercial_page_url('lineas')`.
- [x] T2.3 — Actualizar versión de assets en `index.php`.

### FASE-3-ACTUALIZAR-RUTAS

- [x] T3.1 — Reemplazar URLs `page=josue&tab=telefonos` en `views.php` (L1321, L7306, L7407).
- [x] T3.2 — Actualizar voice.php (resolver, tab hints, AI prompt).
- [x] T3.3 — Verificar `data/settings.json` L4 (nota TODO — sin cambios necesarios).

### FASE-4-MIGRAR-JOSUE-TAB

- [x] T4.1 — Sustituir contenido del tab Josue > Telefonos por aviso de migración + botón a Comercial.
- [x] T4.2 — Cambiar etiqueta del subtab en Josue ("Telefonos →").
- [x] T4.3 — Actualizar versión assets.

### FASE-5-VALIDACION-FINAL

- [x] T5.1 — `php -l` en todos los archivos modificados (5/5 OK).
- [x] T5.2 — `git diff` de todos los cambios (+85/-95 líneas netas).
- [x] T5.3 — Actualizar docs/changelog.md con cierre.
- [x] T5.4 — Verificar integridad de `telefonos.json` (14 registros, IDs OK) y `comercial_line_state.json` (14 registros, IDs OK).

**CIERRE TOTAL DEL PROYECTO UNIFICACION-LINEAS — todas las fases completadas.**

---
## PRF-IDENTIDAD-FOTO-2026 — Mejora de parecido y realismo Publicista

### PRF-F01 — BASELINE_Y_METRICAS

- [x] T1.1 — Definir KPIs: identity similarity, silhouette consistency, background coherence, realism artifacts, hand anatomy, composition.
- [x] T1.2 — Seleccionar muestra representativa de jobs (43 jobs, 156 candidatas).
- [x] T1.3 — Medir baseline actual por KPI (score medio=28.5, 58.3% finales de candidatas <30).
- [x] T1.4 — Definir umbrales de "apto final" y "rechazo automático".
- [x] T1.5 — Documentar hallazgos en specs (requirements, design, contracts).
- [x] T1.6 — Actualizar changelog.

### PRF-F02 — REARQUITECTURA_PROMPT

- [x] T2.1 — Prompt por capas (14 capas etiquetadas con prioridad locked/important/normal).
- [x] T2.2 — Bloque anti-contradicciones (6 reglas de detección automática).
- [x] T2.3 — Compactación robusta smart (locked sections nunca truncadas, important al final).
- [x] T2.4 — Negativos anti-look-IA unificados (8 categorías en un solo bloque).
- [x] T2.5 — Métrica de retención de constraints (>85% objetivo).
- [x] T2.6 — Revisión de seguridad (1 MEDIUM corregido: CAPA marker position-locked).
- [x] T2.7 — Documentación actualizada (design, contracts, changelog).

### PRF-F03 — CONTROL_IDENTIDAD_Y_SILUETA

- [x] T3.1 — Umbral de similitud: scoring recalibrado (60% likeness, 20% overall, 20% flags).
- [x] T3.2 — Reglas de complexión: body_proportions_match penaliza -20 (la más severa).
- [x] T3.3 — Gate de rechazo temprano: likeness<30 hard reject, likeness<50+body_mismatch reject.
- [x] T3.4 — Ninguna final < umbral mínimo: filtro en rebuild_finals_from_candidates.
- [x] T3.5 — Seguridad heredada F02: operator_brief (500 chars, CAPA sanitize), restrictions_text (1000 chars).
- [x] T3.6 — Revisión de seguridad: PASS, CAPA sanitization mejorada a regex.
- [x] T3.7 — Documentación actualizada (design, contracts, changelog).

### PRF-F04 — COHERENCIA_1A1_Y_ENTORNO

- [x] T4.1 — Regla "extend scene, not replace": añadida en 5 ubicaciones (CAPA-8-AMB ×2, variantes, environment guard).
- [x] T4.2 — Guardrails composición sujeto-entorno: CAPA-10-CAL con sección "COMPOSICIÓN SUJETO-ENTORNO" + sujeto "PLANTADO".
- [x] T4.3 — Restricciones iluminación realista: CAPA-9-LUZ reforzado con "PROHIBIDO ABSOLUTAMENTE" neones y lights artificiales.
- [x] T4.4 — Revisión de seguridad: PASS, 0 hallazgos.
- [x] T4.5 — Documentación actualizada (design, contracts, changelog).

### PRF-F05 — RERANKING_Y_SELECCION_FINAL

- [x] T5.1 — Unificar selección: batch pipeline ahora usa rebuild_finals_from_candidates() como única ruta.
- [x] T5.2 — Reforzar gates: meets_minimum_threshold() aplicado en TODAS las rutas de selección.
- [x] T5.3 — Política de auto-regeneración: si < 4 finales y auto_regenerate=1, pipeline marcado needs_regen.
- [x] T5.4 — Revisión de seguridad: PASS, corregido bug de status sobrescrito.
- [x] T5.5 — Documentación actualizada (design, contracts, changelog).

### PRF-F06 — EXPERIMENTOS_AB_Y_HARDENING

- [x] T6.1 — Diseñar 6 experimentos A/B (capa ID, compactación, gates, extend scene, anti-neón, auto-regen).
- [x] T6.2 — Definir medición pre-post contra KPIs F01 (baseline 28.5 → target ≥50).
- [x] T6.3 — Fórmula de decisión ponderada para configuración ganadora (≥15 adoptar, 8-14 piloto, <8 descartar).
- [x] T6.4 — Checklist operativo: pre-rollout, rollout, monitorización 48h, rollback, cierre (ADR-009).
- [x] T6.5 — Documentación final: design, contracts, tasks, changelog + cierre de programa.

## Bot Casa — Parametrización del system prompt

### ORION-CORE — FASE 1: Estructura de datos y ensamblado en backend

- [x] T1.1 — Nuevo esquema JSON en config.dist.json (template + sections con 10 secciones).
- [x] T1.2 — Modificar Bot::buildSystemPrompt() para ensamblado dinámico con template + fallback legacy.
- [x] T1.3 — Migrar config.local.json al nuevo formato parametrizado.
- [x] T1.4 — Tests de humo (php -l, JSON validation, prompt assembly).

### NOVA-PROMPT — FASE 2: Mejora integral del system prompt

- [x] T2.1 — Reescribir sección 'rol' (contexto negocio al inicio, unificar reglas n8n↔PHP).
- [x] T2.2 — Reescribir sección 'estilo' (typos controlados, no-menú con ejemplos malos, monosílabos).
- [x] T2.3 — Reescribir sección 'tarifas' (oferta urgencia 90€, anti-regateo 3 niveles, tríos NO).
- [x] T2.4 — Reescribir sección 'servicios' (preservativo por defecto, francés natural solo 1h, griego, drogas).
- [x] T2.5 — Reescribir sección 'ubicacion' (anti-invención reforzada, post-maps ETA con 6 variantes).
- [x] T2.6 — Reescribir sección 'instrucciones_fotos' (reglas claras, no URLs en reply, anti-repetición).
- [x] T2.7 — Reescribir sección 'identidad_chicas' (anti-bot 4 variantes + 2 insistencia, speaker/selected).
- [x] T2.8 — Reescribir sección 'seguridad' (6 variantes off-topic, foto cliente, otro número, agresivo).
- [x] T2.9 — Reescribir sección 'ejemplos' (18 ejemplos: regateo, audio, bot, indeciso, post-maps, foto, ETA).
- [x] T2.10 — Reescribir sección 'formato_respuesta' (no URLs, lead detection recordatorio).

### ORION-UI — FASE 3: Panel de administración

- [x] T3.1 — Layout de 2 columnas (formulario 60% / preview sticky 40%).
- [x] T3.2 — Textarea para el template + chips clickables de etiquetas [seccion].
- [x] T3.3 — Accordion nativo (details/summary) con textarea por cada sección, ordenado por frecuencia de edición.
- [x] T3.4 — Preview en tiempo real con JS vanilla (rebuildPreview on input).
- [x] T3.5 — Guardado de formulario (processValue extiende CRLF→LF a prompt.template y prompt.sections.*).
- [x] T3.6 — Cache-busting verificado (panel.php es standalone, no requiere).

### NOVA-TONEBUILDER — FASE 4: Ajustes en ToneBuilder + ContextAssembler

- [x] T4.1 — ToneBuilder: directiva POST-MAPS ETA rotativa (6 variantes, bot_msg_count_recent % 6).
- [x] T4.2 — ToneBuilder: directiva cierre suave progresivo (info_pack_ready + !eta_from_user).
- [x] T4.3 — ToneBuilder: directiva indecisión + escasez suave (choose_loop_count >= 2, solo 1 vez).
- [x] T4.4 — ContextAssembler: detección ya_enviado['escasez'] en historial de replies.
- [x] T4.5 — ContextAssembler: nuevos flags eta_from_user_minutes, choose_loop_count, info_pack_ready, is_image_sent_by_user.
- [x] Config: eta_request_variants en message_variants.

### QA-VALIDACION — FASE 5: Validación integral

- [x] T5.1 — PHP Lint: 14/14 archivos OK (Bot.php, Config.php, ContextAssembler, ToneBuilder, panel.php, +9 pipeline files).
- [x] T5.2 — JSON Validation: dist (17 keys) + local (20 keys) OK. 10/10 secciones con paridad idéntica dist↔local.
- [x] T5.3 — Prompt Assembly: 14233 chars, 0 tags sin reemplazar, 10/10 headers, 20/20 content checks.
- [x] T5.4 — Panel: 22/23 checks (1 false negative), CSRF protegido, estructura 2 columnas + preview + accordion.
- [x] T5.5 — ToneBuilder retrocompatibilidad: 20/20 tests (4 nuevas directivas + 16 preexistentes OK).
- [x] T5.6 — Git: 7 archivos committeados en 4 fases, config.local.json gitignored, sin fugas.

**CIERRE TOTAL DEL PROYECTO BOT-CASA — todas las fases completadas.**

---

## Publiscort — Nueva rama comercial

### PUBLISCORT-F1 — MAPA_Y_ENCAJE (Mapa y Encaje de Rama)

- [x] Confirmar encaje funcional de `publiscort` dentro del módulo comercial (como proceso paralelo a `plaza`, `lamami`, `publicista`, `casawasap`).
- [x] Definir identidad técnica de rama:
  - `slug`: `publiscort`
  - `id`: `comproc_publiscort`
  - `nombre`: `Publiscort`
  - `source_type`: `jsonl_queue` (elección conservadora y trazable)
- [x] Confirmar criterio operativo para panel: debe visualizarse en procesos con estado inicial apagado (`enabled=0`) una vez ejecutada la semilla/alta técnica de Fase 2 y migración segura de Fase 4 en instalaciones existentes.

### PUBLISCORT-F2 — SEMILLA_CONFIG_CORE

- [x] Añadir `publiscort` al constructor de procesos por defecto.
- [x] Crear seed específico en `comercial_default_process_seed()` con `enabled=0`, ventanas/intervalos y `ia_context_prompt`.
- [x] Añadir colas por defecto para `jsonl_queue` si aplica.

### PUBLISCORT-F3 — COPY_COMERCIAL

- [x] Crear variantes iniciales de `message_templates` para `publiscort`.
- [x] Crear variantes iniciales de `followup_templates` para `publiscort`.
- [x] Alinear copy con briefing: publicista profesional, alta efectividad, portales (`destacamos`, `mundosex`, `nuevapasion`), anuncios top/de pago, precio `50€/semana`.

### PUBLISCORT-F4 — MIGRACION_SEGURA_EXISTENTES

- [x] Implementar alta automática no destructiva si falta `publiscort` en `comercial_processes.json`.
- [x] Garantizar `enabled=0` por defecto para la nueva rama en instalaciones ya iniciadas.
- [x] Mantener intactos los procesos existentes (`plaza`, `lamami`, `publicista`, `casawasap`).

### PUBLISCORT-F5 — VALIDACION_TECNICA_FUNCIONAL

- [x] Verificar aparición en pestaña procesos y estado apagado por defecto.
- [x] Verificar carga de plantillas de mensaje y seguimiento.
- [x] Ejecutar checks relevantes (lint/tests aplicables) y validar no regresión en ramas existentes.

### PUBLISCORT-F6 — CIERRE_ENTREGA

- [x] Resumir archivos tocados.
- [x] Resumir decisiones de copy y parámetros de rama.
- [x] Dejar lista la siguiente iteración de afinado.

## Mundosex — Integración portal MundosexAnuncio en Publicista

### MUNDOSEX-F1 — PORTAL_REGISTRY (Portal Registry)

- [x] Añadir `mundosex` a `publicista_account_portal_options()` en storage.php.
- [x] Cambiar `<input hidden>` de portal en estrategias por `<select>` con onchange.
- [x] Validación allowlist de `portal_code` en `action_save_publicista_planning`.
- [x] Verificación funcional (portal options, validación, planning normalize).
- [x] Auditoría de seguridad (0 críticos, 0 altos, 2 medios corregidos).
- [x] Actualizar changelog.

### MUNDOSEX-F2 — ADAPTER_LOADER (Cablear adaptador)

- [x] Crear `subirPublicidad/mundosex.php` (wrapper PHP → exec node mundosex_browser.js).
- [x] Cablear `publicista_require_automation_adapter()` para `mundosex`.
- [x] Cablear `publicista_campaign_item_ready_for_execution()` para `mundosex`.
- [x] Adapter code en `publicista_campaign_execute_item()`.
- [x] Session management (mundosex no reusa sesiones HTTP).
- [x] Forzar provincia/ciudad para Mundosex.
- [x] Arreglar conflicto de nombres `ejecutarAutomatizacion`.
- [x] Auditoría de seguridad (1 HIGH corregido: credenciales en línea de comandos → fichero temporal).
- [x] Actualizar changelog.

### MUNDOSEX-F3 — CAMPAIGN_ITEMS (Generar y mostrar items)

- [x] Verificar que `publicista_campaign_generate_items()` asigna `external_ad_id` correctamente para mundosex.
- [x] Verificar que la tabla de items en UI muestra correctamente items `mundosex`.
- [x] Verificar que `publicista_campaign_resolve_location()` funciona para `mundosex`.
- [x] Renombrar `publicista_destacamos_resolve_location()` → `publicista_campaign_resolve_location()` + alias.

### MUNDOSEX-F4 — EXECUTION (Ejecutar subida con humanización)

- [x] Verificar que los delays de humanización aplican a items `mundosex` (genéricos, OK).
- [x] Asegurar que el bucle de retry de copy funciona sin hardcodeos (genérico, OK).
- [x] Payload builder: verificar que el mapping de campos ocurre en el adaptador (OK).
- [x] Corregir: no crear free-bump tasks para mundosex (rompían con undefined function).
- [x] Ampliar post-upload sync a girlsconf para incluir mundosex.
- [x] Test E2E real con browser (login OK, rate-limit esperado, pipeline funciona).

### MUNDOSEX-F5 — ROTATION_BLOCK (Excluir de auto-rotación)

- [x] Filtrar items `mundosex` de la auto-rotación (solo Destacamos rota).
- [x] Verificar que free bump solo aplica a `destacamos` (ya filtrado, sin cambios).
- [x] UI: nota informativa sobre que Mundosex no rota.

---

## Fase 1 — Rendimiento Inmediato (Online)

- [x] Sacar compacción de bootstrap y moverla a cron/mantenimiento.
- [x] Quitar escritura en GET del panel de avisos.
- [x] Añadir acción POST explícita para marcar avisos leídos.
- [x] Añadir snapshot/index de avisos activos y usarlo en lectura.
- [x] Añadir controles mínimos de seguridad en acciones de avisos (CSRF).
- [x] Ejecutar validación técnica (lint PHP + regresión funcional relevante).
- [x] Actualizar documentación mínima (changelog, arquitectura, ADR, contratos).

## Fase 2 — Migración Segura (Dual-Run Online)

- [x] Ajustes finales schema/índices MySQL.
- [x] Backfill JSON -> MySQL idempotente.
- [x] Activación `dual` y validación paridad.
- [x] Auditoría de seguridad de cambios Fase 2.
- [x] Documentación de cierre (changelog, arquitectura, ADR, contratos, design).

## Fase 3 — Corte Controlado a MySQL (Ventana corta)

- [ ] Freeze escrituras + delta backfill final.
- [ ] Cambio backend efectivo a `mysql`.
- [ ] Smoke tests críticos + reapertura.
- [ ] Rollback operativo (<10 min) validado.

## EstadosWasap — Publicación automática de estados de WhatsApp

### SetupEstados — Datos y configuración

- [x] Crear `data/publicista_estados_wasap.json` con estructura de config y log.
- [x] Definir defaults: `enabled=0`, `frecuencia_tipo=cada_x_horas`, `frecuencia_valor=6`, `hora_inicio=08:00`, `hora_fin=23:00`, `formato=chicas_de_hoy`, `lineas=[]`.
- [x] Implementar 6 formatos de publicación (`chicas_de_hoy`, `chica_del_dia`, `duo_sexy`, `catalogo_rapido`, `estrella_grupo`, `mix_aleatorio`).
- [x] Implementar 2 modos de frecuencia (`cada_x_horas`, `x_veces_al_dia`).
- [x] Funciones CRUD en `app/publicista.php`: `get_config`, `save_config`, `config_normalize`, `get_log`, `add_log_entry`.
- [x] Función dinámica `get_bot_casa_lines()` para obtener líneas con `uso=bot casa` y `waha_port` configurado.
- [x] Validar sintaxis PHP y pruebas funcionales de lectura/escritura/normalización/dedupe.

### MotorEstados — Lógica de negocio

- [x] Fetch chicas activas desde `girls.json` (con caché local de 15 min).
- [x] Construir texto del estado según formato seleccionado (emojis, enlaces de fotos).
- [x] Publicar vía WAHA `POST /api/{session}/status/text` usando `comercial_waha_post_json()`.
- [x] Registrar resultado en log con rotación (máx 200 entradas).
- [x] Acciones: `action_save_estados_wasap_config`, `action_publicar_estado_manual`.
- [x] Validación funcional de todos los formatos, fetch, publish, log.

### PanelEstados — Interfaz visual

- [x] Añadir tab `estados_wasap` en `render_publicista_page()`.
- [x] Formulario de configuración (on/off, frecuencia, horario, formato, selector de líneas bot casa).
- [x] Botón "Publicar ahora" para prueba manual.
- [x] Tabla de log con historial de publicaciones.

### AutoEstados — Publicación automática

- [ ] Worker que evalúa frecuencia y dispara publicaciones.
- [ ] Integración con sistema de tasks de publicista.

## COM-CLASIFICACION — Clasificación inteligente del bot comercial

### COM-CLASIFICACION-F1 — Corrección de clasificación, auto-responders y greetings

- [x] T1.1 — Reordenar `comercial_classify_reply()`: mover check de negativos ANTES de high-intent-after-followup para evitar que "no me interesa" se clasifique como very_hot.
- [x] T1.2 — Añadir validación de contexto negativo en `comercial_reply_is_high_intent_after_followup()` con pre-check de negación contextual (no, sin, tampoco + keyword).
- [x] T1.3 — Nueva función `comercial_is_likely_autoresponder($text, $thread)`: detectar patrones de auto-responder WhatsApp Business (tarifas estructuradas, mayúsculas, llegada en <30s).
- [x] T1.4 — Integrar auto-responder en `comercial_classify_reply()` y `comercial_handle_inbound_message()`: nuevo stage `autoresponder`, sin followup automático ni notificación.
- [x] T1.5 — Mejorar respuesta a greetings: distinguir saludo-puro de saludo-con-pregunta; pasar contexto a `comercial_pick_followup_or_improvise()` para evitar respuestas tipo "Me alegra que preguntes".
- [x] T1.6 — Bump versión en `index.php` para recarga de assets.

### COM-NOTIFICACIONES-F2 — Notificaciones efectivas

- [x] T2.1 — Implementar `notify_only_after_second_reply`: suprimir avisos hasta que `replies_count >= 2` en `comercial_create_reply_aviso()`.
- [x] T2.2 — Ampliar `comercial_reply_aviso_is_high_value()` para incluir respuestas `qualified` con señales reales de interés (precio, "me interesa").
- [x] T2.3 — Implementar `conversation_max_defers`: si `defer_count >= max_defers`, escalar a humano en lugar de otro defer.
- [x] T2.4 — Suprimir notificaciones para clasificación `autoresponder` en `comercial_create_reply_aviso()`.
- [x] T2.5 — Bump versión en `index.php` para recarga de assets.

### COM-IA-F3 — IA con mayor capacidad de entendimiento y calidad de respuestas

- [x] T3.1 — Mejorar `comercial_build_contextual_followup_prompt()`: añadir clasificación del último mensaje entrante, estrategia activa y reglas reforzadas de preservación de datos al prompt de IA.
- [x] T3.2 — Nueva función `comercial_ai_output_preserves_key_info($original, $aiOutput)`: extraer datos críticos del original (precios €, URLs, porcentajes, CTA) y verificar que aparecen en la salida IA. Si falta alguno, devolver false para que se use el template original.
- [x] T3.3 — Fortalecer prompt de `comercial_ai_generate_followup_variants()`: añadir instrucciones explícitas de no modificar precios/URLs/condiciones, y contexto sobre si el cliente preguntó o solo saludó.
- [x] T3.4 — Integrar `comercial_ai_output_preserves_key_info()` en `comercial_pick_followup_or_improvise()`: validar variante IA antes de usarla; si falla, usar template base como fallback.
- [x] T3.5 — Persistir `_used_followup_indices` en `comercial_pick_followup_or_improvise()`: llamar a `comercial_upsert_thread()` tras modificar los índices para garantizar persistencia.
- [x] T3.6 — Bump versión en `index.php` para recarga de assets.

---

## CX2 — Desbloqueo SDD bot comercial (F1..F8)

### CX2-F1 — Modelo base de interés real
- [x] Definir estados canónicos y rango de score (0..100).
- [x] Definir contrato mínimo de evaluación (`estado`, `score`, `motivo`, `timestamp`).
- [x] Definir reglas mínimas de escalado y no-escalado.
- [x] Revisar consistencia entre requirements/design/contracts.

### CX2-F2 — Señales y normalización
- [x] Inventariar señales de entrada prioritarias por canal.
- [x] Definir taxonomía de señales (positiva, neutra, negativa, bloqueo).
- [x] Definir normalización mínima para consumo del scoring.

### CX2-F3 — Scoring inicial
- [x] Definir ponderaciones base por señal.
- [x] Definir efecto temporal (degradación/recencia) a nivel contractual.
- [x] Definir criterios de cambio de tramo (bajo/medio/alto).

### CX2-F4 — Persistencia y auditoría
- [x] Definir registro de evaluaciones con trazabilidad de reglas.
- [x] Definir retención mínima de historial por conversación.
- [x] Definir campos obligatorios para auditoría operativa.

### CX2-F5 — Escalado operativo
- [x] Definir umbrales definitivos de escalado.
- [x] Definir anti-ruido y anti-duplicado de escalados.
- [x] Definir payload mínimo de handoff a comercial humano.

### CX2-F6 — Integración operativa
- [x] Definir visualización de estado/score en panel.
- [x] Definir acciones de confirmación/corrección humana.
- [x] Definir trazabilidad de overrides manuales.

### CX2-F7 — Calibración y guardrails
- [x] Definir rutina de calibración periódica.
- [x] Definir límites para falsos positivos y sobre-escalado.
- [x] Definir criterios de revisión de reglas por resultados.

### CX2-F8 — Cierre y aceptación
- [x] Definir métricas de éxito (negocio y operación): `escalation_precision`, `hot_lead_recall`, `time_to_handoff`, `perceived_over_escalation`, `blocking_effectiveness`, `doc_consistency_score`, `audit_trail_completeness`, `decision_reproducibility`.
- [x] Formalizar checklist de consistencia documental F1→F8 con 30 ítems verificables (`spec/design.md` + `spec/contracts.md`).
- [x] Definir criterios de aprobación de cierre con evidencias: checklist OK, métricas en umbral, firma dual, manifiesto inmutable.
- [x] Crear ADR-008 y actualizar changelog.

## PUB-FOTOS-REALES — Subida de fotos reales en perfiles de Publicista

### PUB-FOTOS-REALES-F1 — reals-upload

- [x] T1 — Añadir `reals/` al mapa de directorios de assets (`storage.php: build_job_asset_dirs, job_fs_paths, ensure_job_dirs, job_defaults`).
- [x] T2 — Input `file[multiple]` en formulario CREAR perfil (`views.php: render_publicista_crear_perfiles_page`).
- [x] T3 — Input `file[multiple]` en formulario EDITAR perfil (`views.php: config-panel`).
- [x] T4 — Nueva función `publicista_attach_real_photos()` en `publicista.php` (subida múltiple con validación MIME, límite 10 fotos, límite 20 MB por archivo).
- [x] T5 — Manejo de `$_FILES['real_photos']` en `action_create_publicista_job` (`actions.php`).
- [x] T6 — Manejo de `$_FILES['real_photos']` en `action_save_publicista_job` (`actions.php`).
- [x] T7 — Paso 4 "Fotos reales" en la barra visual de navegación (`views.php`).
- [x] T8 — Sección 4 completa: galería de fotos reales con vista previa, metadatos y botón eliminar (`views.php`).
- [x] T9 — Nuevas acciones `action_upload_publicista_real_photos` y `action_delete_publicista_real_photo` con CSRF (`actions.php`, `views.php`).
- [x] T10 — CSS existente cubre `.publicista-visual-*`; sin cambios necesarios.
- [x] T11 — Cache-busting: `style.css?v=20260527_3`, `app.js?v=20260527_1` en `index.php`.
- [x] T12 — Revisión de seguridad: CSRF añadido a formularios, path derivation desde `photoId` en delete, límite 20 MB, MIME validation robusta.
- [x] T13 — Validación técnica: `php -l` OK en 5 archivos. Sin regresión funcional detectada.
- [x] T14 — Documentación: changelog + contracts actualizados.

### PUB-FOTOS-REALES-F2 — reals-blur

- [x] T1 — Nueva función `publicista_apply_manual_blur_to_real_photo()` en `publicista.php` (mismo patrón que blur para finals, opera sobre `real_photos[]`).
- [x] T2 — Nueva acción `action_apply_publicista_manual_blur_real` en `actions.php` (JSON, recibe `photo_id`).
- [x] T3 — Botón "Blur manual" y badge de estado en cada foto real de la sección 4 (`views.php`).
- [x] T4 — JS extendido: `openManualBlurModal` acepta target `'real'`, `submitManualBlur` despacha action correcta, actualiza DOM con IDs `realBlurImg_` / `realBlurStatus_`.
- [x] T5 — Registro en dispatcher: `case 'apply_publicista_manual_blur_real'`.
- [x] T6 — Cache-busting: `style.css?v=20260527_4`, `app.js?v=20260527_4`.
- [x] T7 — Seguridad: validación de parámetros (bx/by/bw/bh clamp 0..1, intensity 1..20), `escapeshellarg` en todos los parámetros al worker Python, sin CSRF en endpoint AJAX (misma política que blur de finals).
- [x] T8 — Validación técnica: `php -l` OK en 3 archivos.
- [x] T9 — Documentación: changelog + contracts + tasks actualizados.

### PUB-FOTOS-REALES-F3 — six-finals

- [x] T1 — `rebuild_finals_from_candidates()`: seleccionar top 6 en lugar de top 4 (`array_slice($eligible, 0, 6)`).
- [x] T2 — UI: badges "X/6", "TOP 6", textos de wizard "top 6" en `views.php` (10 cambios).
- [x] T3 — `publicista_campaign_pick_images()`: límite por defecto 6, actualizadas 4 llamadas explícitas en `storage.php`.
- [x] T4 — `GIRLSCONF_MAX_PHOTOS`: de 4 a 6 en `publicista_girlsconf.php`.
- [x] T5 — `mundosex_browser.js`: slots `#image_4` y `#image_5` añadidos + eliminación de hasta 4 fotos antiguas.
- [x] T6 — Umbrales de auto-regeneración y status: `>= 4` → `>= 6` en `publicista.php` (10 ocurrencias en 6 funciones).
- [x] T7 — Cache-busting: `v=20260527_5`.
- [x] T8 — Validación técnica: `php -l` OK en 4 archivos PHP.
- [x] T9 — Documentación: changelog + contracts + tasks actualizados.

### COM-INTEGRIDAD-F4 — Integridad del sistema

- [x] T4.1 — Fortalecer `comercial_find_open_thread_for_inbound()`: priorizar hilos que pertenezcan a la línea receptora conocida y evitar cruce de procesos distintos para un mismo teléfono.
- [x] T4.2 — Reset de `auto_turn_count` en `comercial_run_tick()`: añadir pasada que resetee el contador en hilos con `last_contact_at > 24h`, sin depender solo de mensajes entrantes.
- [x] T4.3 — Validar `from_me` en webhook logs: cuando un mensaje entrante tiene `from_me=0` pero el `from` pertenece a una línea propia, loguear warning para detectar si WAHA reporta incorrectamente.
- [x] T4.4 — Verificar delays humanos: confirmar que los parámetros de typing delay producen rangos realistas (3-17s) y documentar en comentarios.
- [x] T4.5 — Bump versión en `index.php` para recarga de assets.

### PUB-FOTOS-REALES-F4 — platform-photos

- [x] T1 — Añadir `platform_photos` a `publicista_job_defaults()` y merge en `publicista_jobs_get()` (`storage.php`).
- [x] T2 — Nueva sección "⑤ Fotos por plataforma" en job detail: checkboxes por plataforma (destacamos, mundosex, girlsconf) con miniaturas (`views.php` + paso 5 en barra visual).
- [x] T3 — Nueva acción `save_publicista_platform_photos` con CSRF y registro en dispatcher (`actions.php`).
- [x] T4 — Modificar `publicista_campaign_pick_images()` para aceptar `$portalCode` y resolver fotos desde `platform_photos` (finales + reales) (`storage.php`).
- [x] T5 — Pasar `$planning['portal_code']` a `pick_images()` en `publicista_campaign_generate_items()` (`storage.php`).
- [x] T6 — Añadir `stored_path` como candidato en `publicista_campaign_item_image_paths()` para soportar fotos reales (`storage.php`).
- [x] T7 — Validación en `publicista_campaign_validate_for_generation()`: bloquear productos sin `platform_photos` para el portal del planning (`storage.php`).
- [x] T8 — Cache-busting: `v=20260527_6`.
- [x] T9 — Validación técnica: `php -l` OK en 3 archivos.
- [x] T10 — Documentación: changelog + contracts + tasks actualizados.

---

### MUNDOSEX-F6 — mundosex-fix (revisión y hardening)

- [x] T1 — Revisión completa de `mundosex_browser.js`: login, formulario, fotos, save, Rocket Loader.
- [x] T2 — Revisión completa de `mundosex.php`: mapping de campos, validación de payload, ejecución Node.
- [x] T3 — Fix TinyMCE: selector `#tinymce` con fallback a `body` si no visible.
- [x] T4 — Fix debug: guardar HTML de la página en `/tmp/` cuando falla el submit.
- [x] T5 — Fix provincia: `dispatchEvent('change')` manual tras seleccionar provincia, timeouts separados.
- [x] T6 — Fix warnings: incluir selector y valor en mensajes de error de campos.
- [x] T7 — Validación técnica: `php -l mundosex.php` + `node -c mundosex_browser.js` OK.
- [x] T8 — Documentación: changelog + tasks actualizados.

## COM-BALANCE — Balanceo ponderado de envíos entre líneas comerciales

### COM-BALANCE-F0 — Spec & Design

- [x] T0.1 — Requisitos: definir objetivo, alcance por fases y restricciones en `spec/requirements.md`.
- [x] T0.2 — Diseño: formalizar algoritmo min-deficit-first ponderado, pseudocódigo y edge cases en `spec/design.md`.
- [x] T0.3 — Contratos: definir contratos formales de contador diario, selección de línea y no regresión en `spec/contracts.md`.
- [x] T0.4 — Tasks: crear tracking de subtareas F1-F4 en `spec/tasks.md` §COM-BALANCE.
- [x] T0.5 — Changelog: entrada de cierre de fase F0 en `docs/changelog.md`.
- [x] T0.6 — Validación cruzada: verificar consistencia entre requirements, design y contracts.

### COM-BALANCE-F1 — Data Layer

- [x] T1.1 — Añadir `daily_sent_count` y `daily_sent_date` al normalizer `comercial_normalize_line_state()` (defaults: `0`, `""`).
- [x] T1.2 — Implementar `comercial_line_increment_daily_count($lineId)`: leer, incrementar, guardar estado a disco.
- [x] T1.3 — Implementar `comercial_line_get_daily_count($lineId)`: devolver contador del día, reset si `daily_sent_date != today`.
- [x] T1.4 — Implementar `comercial_reset_daily_counts_if_new_day()`: ejecutar al inicio de `comercial_run_tick()`.
- [x] T1.5 — Implementar `comercial_line_get_daily_counts_map($lineIds)`: versión批量 `[lineId => count]`.

### COM-BALANCE-F2 — Core Algorithm

- [x] T2.1 — Reescribir `comercial_order_lines_for_process()`: déficit normalizado, sort ASC, tiebreaker con rotación legacy.
- [x] T2.2 — Reescribir `comercial_pick_line_for_process()` con misma lógica de déficit.
- [x] T2.3 — Modificar `comercial_register_last_send()` para encadenar `comercial_line_increment_daily_count()`.
- [x] T2.4 — En `comercial_send_process_message_with_fallback()`, incrementar contador de la línea que efectivamente envió.
- [x] T2.5 — Gate: si `effective_power_factor <= 0`, `deficit = PHP_INT_MAX`.

### COM-BALANCE-F3 — Integration & Verification

- [x] T3.1 — Cablear reset diario al inicio de `comercial_run_tick()`.
- [x] T3.2 — Verificar que envío manual desde UI usa mismo algoritmo de balanceo.
- [x] T3.3 — Validar edge cases: 1 línea, 0 líneas, cambio día, power 0, línea nueva.
- [x] T3.4 — Simular reparto: 5 procesos, 2 líneas (power 1.0 y 0.5) → verificar proporción ~2:1.
- [x] T3.5 — Bump versión en `index.php` para recarga de assets.

### COM-BALANCE-F4 — UI & Monitoring (opcional)

- [ ] T4.1 — Mostrar `daily_sent_count` junto a cada línea en panel de procesos comerciales.
- [ ] T4.2 — Indicador visual de déficit/balance en panel comercial.
- [ ] T4.3 — Botón "Reset contadores diarios" con CSRF.

---

## COM-LINEAS-UI — Mejora de la sección Comercial > Líneas

### COM-LINEAS-UI-F0 — Especificación
- [x] T0.1 — Requisitos en `spec/requirements.md` (§COM-LINEAS-UI)
- [x] T0.2 — Diseño en `spec/design.md` (§Diseño COM-LINEAS-UI)
- [x] T0.3 — Contratos en `spec/contracts.md` (§Contratos COM-LINEAS-UI)
- [x] T0.4 — Tasks tracking en `spec/tasks.md` (§COM-LINEAS-UI)

### COM-LINEAS-UI-F1 — Implementación (4 tareas paralelas)
- [x] T1.1 — **PHP**: Reestructurar sección `lineas` en `app/comercial.php` (líneas 6536–6714):
   - Eliminar wrapper `<div class="cards two">` y su cierre `</div>`.
   - Eliminar el `<section class="panel">` del panel izquierdo (formulario CRUD siempre visible).
   - Sustituir el `<section class="panel">` del panel derecho por una única sección con toolbar + tabla unificada.
   - Merge de las dos tablas en una sola `<table class="lineas-unified-table">` con las 8 columnas definidas en el diseño.
   - Mantener un solo `foreach ($lines as $line)`.
   - Añadir HTML del modal (`#lineasModalOverlay`) al final de la sección (fuera del `<section class="panel">`).
   - Añadir botón `#btnNuevaLinea` en la toolbar.
   - Mantener intactos todos los formularios POST existentes (`save_telefono`, `delete_telefono`, `save_comercial_line_state`, `comercial_check_lines_health`).

- [x] T1.2 — **JS**: Refactorizar `assets/app.js`:
   - Eliminar `initLineasSearch()` (L1807–1825).
   - Eliminar `initLineasEdit()` (L1827–1888).
   - Añadir `openLineasModal(lineData)`: rellenar formulario modal, mostrar overlay, añadir clase `modal-open`.
   - Añadir `closeLineasModal()`: ocultar overlay, remover clase `modal-open`.
   - Añadir `initLineasUnifiedSearch()`: listener en `#lineas-unified-search` para filtrar filas de `#lineasUnifiedTableBody`.
   - Añadir event listeners en DOMContentLoaded: overlay click (cierre), Escape keydown (cierre), `.btn-lineas-edit` click (abrir modal con data-line), `#btnNuevaLinea` click (abrir modal vacío), `#btnGuardarLinea` click (submit form), `#btnCancelarLinea` click (cerrar modal), `#btnModalClose` click (cerrar modal).
   - Actualizar llamada en DOMContentLoaded: `initLineasUnifiedSearch()` en lugar de `initLineasSearch()` + `initLineasEdit()`.

- [x] T1.3 — **CSS**: Añadir estilos en `assets/style.css`:
   - `.modal-overlay`: fixed, inset 0, bg `rgba(0,0,0,0.7)`, z-index 1000, display flex, center items, hidden por defecto.
   - `.modal-container`: bg var(--panel-bg), border-radius 8px, max-width 600px, width 95%, max-height 90vh, overflow-y auto, box-shadow.
   - `.modal-header`: flex, space-between, padding, bottom border.
   - `.modal-body`: padding, contiene `form.form-grid`.
   - `.modal-footer`: flex, gap, padding, top border, justify-end.
   - `.modal-close`: botón sin fondo, font-size 24px, cursor pointer.
   - `body.modal-open`: overflow hidden.
   - `.lineas-toolbar`: flex, gap, align-items center, margin-bottom, flex-wrap wrap.
   - `.lineas-unified-table`: width 100%, font-size 13px, column widths (`.col-nombre`, `.col-uso`, `.col-waha`, `.col-check`, `.col-comercial`, `.col-procesos`, `.col-ultimos`, `.col-acciones`).
   - Responsive: en `@media (max-width: 768px)`, `.modal-container` width 98%, `.lineas-toolbar` column direction, `.lineas-unified-table` font-size 11px.

- [x] T1.4 — **CSS Theme**: Añadir overrides tema oscuro en `assets/theme.css`:
   - `.modal-overlay`: confirmar bg oscuro.
   - `.modal-container`: `background: var(--panel-bg)`, `border: 1px solid var(--border-color)`, `color: var(--text-color)`.
   - `.modal-header`: `border-bottom: 1px solid var(--border-color)`.
   - `.modal-footer`: `border-top: 1px solid var(--border-color)`.
   - `.lineas-unified-table th`: `background: var(--th-bg)`, `color: var(--text-muted)`.
   - `.lineas-unified-table td`: `border-bottom: 1px solid var(--border-color)`.
   - `.lineas-unified-table tr:hover td`: `background: var(--tr-hover-bg)`.
   - `.modal-close`: `color: var(--text-muted)`, hover: `color: var(--text-color)`.
   - `#lineas-unified-search`: `background: var(--input-bg)`, `color: var(--text-color)`, `border: 1px solid var(--border-color)`.

### COM-LINEAS-UI-F2 — Verificación
- [x] T2.1 — `php -l` en archivos modificados: `app/comercial.php`, `index.php`.
- [x] T2.2 — Verificar selectores JS no rotos:
  - `#lineasModalOverlay` existe en el DOM.
  - `#lineaForm` existe y contiene todos los campos esperados (`[name="nombre"]`, `[name="tfono"]`, etc.).
  - `.btn-lineas-edit` presente en cada fila.
  - `#lineas-unified-search` y `#lineasUnifiedTableBody` existen.
  - `#btnNuevaLinea`, `#btnGuardarLinea`, `#btnCancelarLinea`, `#btnModalClose` existen.
- [x] T2.3 — Bump versiones cache en `index.php`:
  - `style.css?v=20260528_2` → `style.css?v=20260601_1`
  - `theme.css?v=20260528_1` → `theme.css?v=20260601_1`
  - `app.js?v=20260528_2` → `app.js?v=20260601_1`
- [x] T2.4 — Actualizar `docs/changelog.md` con entrada COM-LINEAS-UI.

**CIERRE TOTAL DEL PROYECTO COM-LINEAS-UI — todas las fases completadas.**

## BOT-CASA-MULTIUSER — Ampliación bot-casa Multi-Usuario

### FASE 1 — Fundación Multi-Tenant + Auth

- [x] T1.1 — Crear `src/Core/UserManager.php`: CRUD usuarios, bcrypt, seed admin por defecto.
- [x] T1.2 — Crear `public/login.php`: formulario login, sesiones PHP, CSRF, rate limit.
- [x] T1.3 — Crear `public/logout.php`: destruir sesión, redirigir.
- [x] T1.4 — Modificar `public/index.php`: añadir rutas /login, /logout, /cliente.
- [x] T1.5 — Modificar `Bot::bootstrap()`: aceptar user_id opcional, resolver paths por usuario.
- [x] T1.6 — Modificar `public/webhook.php`: extraer last9, buscar user_id, pasar a bootstrap.
- [x] T1.7 — Crear `data/lines_map.json` con mapeo de líneas existentes a user_id=1.
- [x] T1.8 — Forward-compat: si no existe data/users/{id}/, usar data/ (legacy).
- [x] T1.9 — Funciones `require_auth()` y `require_admin()` en helpers compartidos.
- [x] T1.10 — Validación: php -l en todos los archivos nuevos/modificados.
- [x] T1.11 — Revisión de seguridad: password hashing, CSRF, session fixation.
- [x] T1.12 — Actualizar docs/changelog.md.

**FASE 1 COMPLETADA.**

### FASE 2 — Panel Admin (user #1)

- [x] T2.1 — Añadir auth gate en panel.php (defensa en profundidad + legacy mode).
- [x] T2.2 — Nueva tab "Usuarios" con CRUD de clientes (crear, editar, desactivar).
- [x] T2.3 — Botón "Suplantar" para ver panel del cliente (/cliente con ficha).
- [x] T2.4 — Externalizar CSS a assets/style.css (353 líneas).
- [x] T2.5 — Actualizar cache busters: style.css?v=20260603_2.

**FASE 2 COMPLETADA.**

### FASE 3 — Panel Cliente + Secciones Principales

- [x] T3.1 — Crear public/client.php con nuevo layout y tabs (Dashboard, Mi Bot, Personalidad).
- [x] T3.2 — Dashboard con stats visuales por usuario (conversaciones, leads, líneas activas).
- [x] T3.3 — Sección "Mi Bot": ON/OFF simplificado, checklist de configuración, guía paso a paso.
- [x] T3.4 — Sección "Personalidad": prompt parametrizado con dropdowns (tono, modo, emojis, longitud) + textareas guiadas + preview.
- [x] T3.5 — Sistema de tooltips reutilizable con icono ? y caja flotante.
- [x] T3.6 — CSS mobile-first responsive (stats grid, prompt layout colapsable).

**FASE 3 COMPLETADA.**

### FASE 4 — Líneas + Chicas + Estados

- [ ] T4.1 — Sección Líneas: UI para añadir/quitar, QR, estado, test.
- [ ] T4.2 — API interna para SSH WAHA (crear/eliminar líneas).
- [ ] T4.3 — Polling periódico de estado de líneas.
- [ ] T4.4 — Sección Chicas: CRUD con upload de fotos.
- [ ] T4.5 — Sección Estados: configuración + publicador + historial.

### FASE 5 — Clientes + Mensajes + Ajustes + Stats + Logs

- [ ] T5.1 — Sección Clientes: leads con arrived marking, guía Telegram.
- [ ] T5.2 — Sección Mensajes: search por teléfono, vista chat, marcar lead.
- [ ] T5.3 — Sección Ajustes: Delays, Variantes, Follow-up, Reminder agrupados.
- [ ] T5.4 — Sección Estadísticas: métricas leads notificados vs reales.
- [ ] T5.5 — Sección Registro: logs sanitizados.
- [ ] T5.6 — Ocultar IA para clientes (solo admin).

### FASE 6 — Onboarding + Cron Jobs + Pulido

- [ ] T6.1 — Wizard de bienvenida al primer login.
- [ ] T6.2 — Modificar crons para multi-usuario (learn, classify, followup, reminder).
- [ ] T6.3 — Playbook por usuario (learn.php).
- [ ] T6.4 — Follow-up respeta leads marcados como llegados.
- [ ] T6.5 — Indicador de progreso de configuración.
- [ ] T6.6 — Testing final end-to-end.
