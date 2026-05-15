# Tasks por fases

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
