# Tasks por fases

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

- [ ] Añadir tab `estados_wasap` en `render_publicista_page()`.
- [ ] Formulario de configuración (on/off, frecuencia, horario, formato, selector de líneas bot casa).
- [ ] Botón "Publicar ahora" para prueba manual.
- [ ] Tabla de log con historial de publicaciones.

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
