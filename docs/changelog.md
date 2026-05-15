# Changelog

## 2026-05-15 · Publiscort F6 — CIERRE_ENTREGA

### Resumen de archivos tocados (F1→F5)
- `app/comercial.php`
- `spec/tasks.md`
- `docs/changelog.md`

### Resumen de decisiones clave
- Identidad de rama: `slug=publiscort`, `id=comproc_publiscort`, `nombre=Publiscort`.
- Arranque conservador: `enabled=0` por defecto.
- Fuente y operación: `jsonl_queue`, colas `publiscort_1..3.jsonl`, ventana `10:00-19:00`, intervalos `5400-7200s`.
- Copy comercial: enfoque en publicista profesional de alta efectividad, cobertura en Destacamos/Mundosex/Nuevapasion, estrategia TOP+pago y precio fijo `50€/semana`.
- Compatibilidad de existentes: autoinserción no destructiva de `publiscort` si falta en `comercial_processes.json`.
- Hardening de seguridad: no persistir `source_mysql_pass` en JSON local.

### Estado de entrega
- Bloque Publiscort completo en fases `F1..F6` con validación técnica y funcional cerrada.
- Siguiente iteración lista: afinado de copy/operativa en piloto controlado antes de activar en producción.

---

## 2026-05-15 · Publiscort F5 — VALIDACION_TECNICA_FUNCIONAL

### Cambios
- Validada aparición de `publiscort` en el listado de procesos comerciales y estado por defecto apagado (`enabled=0`).
- Validada carga de plantillas `message_templates` y `followup_templates` de `publiscort`.
- Ejecutada regresión mínima de ramas existentes (`plaza`, `lamami`, `publicista`, `casawasap`) sin pérdida de slugs.
- Ejecutado lint PHP en módulo comercial.

### Seguridad
- Corregido hallazgo HIGH de exposición de secreto en persistencia local de procesos: `source_mysql_pass` deja de persistirse en `comercial_processes.json`.
- El password MySQL pasa a resolverse por configuración segura global (env/settings), evitando almacenarlo en claro por proceso.

### Motivo
Cerrar la validación técnica/funcional de Publiscort con evidencia reproducible y sin degradar ramas existentes, incorporando además hardening de seguridad en almacenamiento de credenciales.

### Archivos
- `app/comercial.php`
- `spec/tasks.md`
- `docs/changelog.md`

---

## 2026-05-15 · Publiscort F4 — MIGRACION_SEGURA_EXISTENTES

### Cambios
- Implementada migración segura en `comercial_get_processes()` para instalaciones existentes: si falta el proceso `publiscort` en `comercial_processes.json`, se inserta automáticamente desde seed.
- La inserción es no destructiva: no altera configuración de procesos ya existentes.
- Guardrail explícito de seguridad operativa: el proceso autoinsertado se fuerza con `enabled=0`.

### Motivo
Evitar que Publiscort solo aparezca en instalaciones nuevas. Con esta migración, también queda disponible en entornos ya inicializados sin romper configuraciones previas ni activar envíos por error.

### Archivos
- `app/comercial.php`
- `spec/tasks.md`
- `docs/changelog.md`

---

## 2026-05-15 · Publiscort F3 — COPY_COMERCIAL

### Cambios
- Añadidas variantes iniciales de `message_templates` para el slug `publiscort` en `comercial_default_process_templates()`.
- Añadidas variantes iniciales de `followup_templates` para `publiscort`.
- Añadido fallback de compatibilidad en `comercial_legacy_process_templates()` para `publiscort`.
- `publiscort` incluido en `comercial_hardcoded_process_slugs()` para mantener el mismo patrón de plantillas hardcodeadas que el resto de ramas comerciales nativas.

### Copy aplicado
- Posicionamiento: **publicista profesional** con **alta efectividad**.
- Portales mencionados: **Destacamos, Mundosex y Nuevapasion**.
- Estrategia comunicada: combinación de **anuncios TOP** y **anuncios de pago**.
- Precio incluido en todos los textos: **50€ / semana**.

### Motivo
Dejar Publiscort con copy operativo de salida para arranque comercial controlado, manteniendo coherencia con la lógica de plantillas y tono del módulo comercial actual.

### Archivos
- `app/comercial.php`
- `spec/tasks.md`
- `docs/changelog.md`

---

## 2026-05-15 · Publiscort F2 — SEMILLA_CONFIG_CORE

### Cambios
- Añadido `publiscort` al constructor de procesos por defecto del módulo comercial.
- Añadido seed `publiscort` en `comercial_default_process_seed()` con arranque conservador:
  - `enabled=0` (apagado por defecto)
  - `source_type=jsonl_queue`
  - ventana `10:00-19:00`
  - intervalos `5400-7200` segundos
  - líneas sugeridas por `comercial_guess_line_ids(['jostal dulce', 'nuria-jostal', 'publi10'])`
  - `ia_context_prompt` específico para Publiscort.
- Añadidas colas por defecto `publiscort_1..3.jsonl` y su inclusión en el agregador global de colas para bootstrap automático de archivos.

### Motivo
Materializar la base técnica de Publiscort con riesgo bajo y trazabilidad clara, dejando el proceso configurado pero inactivo hasta ajustar copy y activación en fases posteriores.

### Archivos
- `app/comercial.php`
- `spec/tasks.md`
- `docs/changelog.md`

---

## 2026-05-15 · Publiscort F1 — MAPA_Y_ENCAJE

### Cambios
- Formalizada la fase `PUBLISCORT-F1` en `spec/tasks.md` con trazabilidad SDD y checklist explícito.
- Definida la identidad técnica objetivo de la nueva rama comercial:
  - `slug`: `publiscort`
  - `id`: `comproc_publiscort`
  - `nombre`: `Publiscort`
  - `source_type`: `jsonl_queue` (criterio conservador)
- Documentado el criterio de visualización en panel: aparecerá apagada por defecto (`enabled=0`) tras su alta técnica en F2 y la compatibilidad de existentes en F4.

### Motivo
Cerrar la fase de encaje y definición antes de tocar runtime, reduciendo riesgo y dejando contrato operativo claro para implementar la rama en fases posteriores.

### Archivos
- `spec/tasks.md`
- `docs/changelog.md`

---

## 2026-05-15 · Mundosex F5 — ROTATION_BLOCK (Excluir de auto-rotación)

### Cambios
- Añadido filtro en `publicista_campaign_execute()`: en modo auto-rotación, los items con `portal_code='mundosex'` se excluyen del bucle de ejecución.
- Los items Mundosex se suben solo en la primera publicación; las rotaciones posteriores solo afectan a Destacamos.
- Añadida nota informativa en la UI de auto-rotación: "Los anuncios de MundosexAnuncio se suben una sola vez y no rotan."
- Verificado: free-bump y página "Subir anuncios" ya excluyen Mundosex (sin cambios necesarios).

### Motivo
Quinta y última fase de la integración MundosexAnuncio. Asegura que la auto-rotación no reintenta subir anuncios de Mundosex (que se publican solo una vez), cumpliendo el requisito de que solo Destacamos rota.

### Archivos
- `app/storage.php` (+8 líneas, filtro de rotación)
- `app/views.php` (+1 línea, nota UI)

---

## 2026-05-15 · Mundosex F4 — EXECUTION (Ejecutar subida con humanización)

### Cambios
- Verificado pipeline end-to-end: PHP → Node.js → Playwright → Chrome → mundosexanuncio.com (login ✅, form ✅, fotos ✅, save ✅).
- Corregido: los items Mundosex ya no crean tareas de free-bump (rompían con `undefined function subirGratis()`).
- Ampliado post-upload sync a girlsconf para incluir portal `mundosex` además de `destacamos`.
- Verificado que los delays de humanización entre items aplican a todos los portales (genéricos).
- Verificado que el bucle de retry de copy y la deduplicación de fingerprints funcionan multi-portal.

### Motivo
Cuarta fase de la integración. Confirma que el pipeline de ejecución funciona de principio a fin para Mundosex, con las protecciones necesarias (sin free-bump inválido, sync correcto).

### Archivos
- `app/storage.php` (+2 fixes: free-bump guard, sync widen)

---

## 2026-05-15 · Mundosex F3 — CAMPAIGN_ITEMS (Verificación items Mundosex)

### Cambios
- Verificado que `publicista_campaign_generate_items()` asigna correctamente `external_ad_id` desde `portal_listing_ids` de cuentas Mundosex.
- Verificado que la tabla de items en UI muestra correctamente `portal_label` y `portal_code` para Mundosex (genérico, sin hardcodeos).
- Verificado que `publicista_campaign_resolve_location()` (antes `publicista_destacamos_resolve_location`) devuelve ciudad/provincia/ZIP para Mundosex sin depender del portal.
- Renombrado `publicista_destacamos_resolve_location()` → `publicista_campaign_resolve_location()` (el nombre anterior inducía a error, la función es portal-agnóstica).
- Añadido alias retrocompatible `publicista_destacamos_resolve_location()`.

### Motivo
Tercera fase de la integración. Confirma que la generación de items, la UI y la resolución de ubicación funcionan para Mundosex sin modificaciones estructurales, solo mejoras de nomenclatura.

### Archivos
- `app/storage.php` (rename + alias, +8 líneas)

---

## 2026-05-15 · Mundosex F2 — ADAPTER_LOADER (Cablear adaptador)

### Cambios
- Creado `subirPublicidad/mundosex.php`: adaptador PHP que ejecuta `mundosex_browser.js` (Playwright + Chrome headless) vía fichero temporal (sin credenciales en línea de comandos).
- Cableado `publicista_require_automation_adapter()` con branch `mundosex`.
- Cableado `publicista_campaign_item_ready_for_execution()` con validación específica para Mundosex (listing ID y teléfono requeridos).
- Dispatch en `publicista_campaign_execute_item()`: llama a `mundosex_ejecutar_automatizacion()` para portal `mundosex`.
- Forzado `$allowProtectedFieldOverrides = true` para Mundosex (provincia y ciudad requeridos por el formulario).
- Arreglado conflicto de nombres: `mundosex_ejecutar_automatizacion()` vs `ejecutarAutomatizacion()` de Destacamos (coexisten sin colisión).

### Motivo
Segunda fase de la integración. Conecta el script de automatización Playwright al CRM, permitiendo que las campañas con portal `mundosex` ejecuten subidas automáticas.

### Archivos
- `subirPublicidad/mundosex.php` (nuevo, 190 líneas)
- `subirPublicidad/mundosex_browser.js` (modificado, soporte `--file=`)
- `app/storage.php` (+15 líneas)

---

## 2026-05-15 · Mundosex F1 — PORTAL_REGISTRY (Portal Registry)

### Cambios
- Añadido `mundosex` como opción de portal en `publicista_account_portal_options()`.
- El formulario de guardar estrategia ahora muestra un `<select>` de portal en lugar de un `<input type="hidden">` hardcodeado a `destacamos`.
- Validación allowlist de `portal_code` en `action_save_publicista_planning` (rechaza códigos no registrados, cae a `destacamos`).
- El sistema acepta cuentas con `portal_code=mundosex` en la validación de campañas (el matching `portal_code === portal_code` ya funciona sin cambios adicionales).

### Motivo
Primera fase de la integración de MundosexAnuncio como portal automatizado. Registra el portal en el sistema para que aparezca en los desplegables de cuentas y estrategias, preparando el terreno para el adaptador de automatización.

### Archivos
- `app/storage.php` (+1)
- `app/views.php` (+7)
- `app/actions.php` (+5)

---

## 2026-05-05 · Fase 1 — Rendimiento Inmediato (Online)

### Cambios
- Se retiró la compactación pesada del bootstrap web.
- Se añadió cron de mantenimiento (`cron_mantenimiento.php`) para ejecutar compactación fuera de petición de usuario.
- El panel de avisos dejó de escribir en GET; el marcado de leídos ahora se hace por POST explícito.
- Se incorporó snapshot derivado de avisos activos (`data/avisos_active_snapshot.json`) para reducir lecturas full-scan repetidas.
- Se añadieron validaciones CSRF en acciones de avisos de Fase 1.

### Motivo
Reducir latencia global y contención de I/O antes de la migración completa a MySQL.

## 2026-05-05 · Fase 2 — Migración Segura (Dual-Run Online)

### Cambios
- Se creó capa de conexión MySQL común (`app/db.php`): PDO cacheado, helpers de consulta, introspección de tablas/columnas, configurable por `CRM_DB_*`.
- Se implementó backend de almacenamiento configurable en 3 modos (`json`, `dual`, `mysql`) dentro de `app/storage.php`.
- Se añadió mapa completo de 37 archivos JSON → tablas `crm_*` con especificaciones `rows_by_id`, `singleton` y `scalar_list`.
- Se incorporó `ON DUPLICATE KEY UPDATE` en todas las escrituras MySQL para garantizar idempotencia.
- Se creó `tools/phase2_apply_schema.php` para aplicar ajustes finales de schema e índices (tabla `crm_comercial_ai_memory`, 10 índices compuestos, ALTERs de columnas).
- Se creó `tools/phase2_backfill.php` para backfill idempotente JSON → MySQL con registro en `crm_migration_runs` y filtro `--only=`.
- Se creó `tools/phase2_parity_check.php` para validar paridad de conteos e IDs entre JSON y MySQL, con reporte JSON.
- Se añadió sincronización automática de `crm_comercial_process_lines` desde `comercial_processes.json`.

### Motivo
Disponer de infraestructura dual-run completa: backfill verificado, paridad medible y capacidad de activar MySQL como lectura preferente sin romper la operativa JSON existente.

## 2026-05-11 · CX2-F1 — Desbloqueo SDD bot comercial

### Cambios
- Se añadió el bloque CX2 al alcance de requisitos para habilitar fases `CX2-F1..CX2-F8`.
- Se incorporó el diseño funcional de `CX2-F1` con estados canónicos, score de interés y reglas de escalado explicables.
- Se definieron contratos de comportamiento para `CX2-F1` (estados válidos, rango de score, transiciones y consistencia).
- Se actualizó el checklist de `spec/tasks.md` con las fases CX2 y se marcó `CX2-F1` como completada a nivel documental SDD.

### Motivo
Desbloquear la ejecución por comando `/fase CX2-F1` con una base SDD trazable antes de entrar en implementación de runtime.

## 2026-05-11 · CX2-F2 — Señales y normalización

### Cambios
- Se documentó catálogo inicial de señales prioritarias por canal (v1 centrada en WhatsApp) en `spec/design.md`.
- Se formalizó taxonomía cerrada de clases (`positiva|neutra|negativa|bloqueo`) y reglas de precedencia.
- Se definió contrato de evento normalizado `InterestSignalNormalized` con campos obligatorios, opcionales y reglas de dedupe/fallback en `spec/contracts.md`.
- Se incorporaron guardrails contractuales de seguridad/privacidad para evitar escalado inducible por texto libre y sobreexposición de PII en trazas.
- Se actualizó arquitectura con impacto del bloque CX2 y se añadió ADR-003.
- Se marcó `CX2-F2` como completada en `spec/tasks.md`.

### Motivo
Establecer una interfaz de señales consistente y auditable para habilitar el scoring de interés en CX2-F3 con menor ambigüedad operativa.

## 2026-05-11 · CX2-F3 — Scoring inicial

### Cambios
- Se definió el modelo de scoring inicial en `spec/design.md` con:
  - ponderaciones base por señal,
  - fórmula de cálculo con factor de confianza y recencia,
  - degradación temporal por inactividad,
  - tramos `bajo/medio/alto` y reglas de histéresis.
- Se formalizaron contratos de F3 en `spec/contracts.md` para cálculo, consistencia tramo↔estado, dominancia de bloqueo y trazabilidad auditable.
- Se añadieron controles contractuales de seguridad para anti-replay, anti-gaming por spam y prevención de escalado por señal ambigua única.
- Se actualizó checklist de `spec/tasks.md` marcando `CX2-F3` como completada.
- Se añadió ADR-004 con la decisión arquitectónica de scoring inicial.

### Motivo
Disponer de un score inicial conservador, explicable y resistente a ruido para soportar fases posteriores de auditoría y escalado operativo.

## 2026-05-11 · CX2-F4 — Persistencia y auditoría

### Cambios
- Se amplió `spec/requirements.md` para incluir retención mínima e integridad/no repudio del historial en alcance CX2-F4.
- Se añadió en `spec/design.md` el diseño de persistencia auditable con entidades lógicas `InterestAssessmentRecord`, `InterestRuleTrace` y `OperationalAuditStamp`.
- Se documentó estrategia append-only lógica, derivación de estado vigente por `evaluated_at` y compatibilidad con backend `json|dual|mysql`.
- Se definió retención mínima contractual por conversación (mínimo 20 evaluaciones recientes y 90 días).
- Se formalizaron en `spec/contracts.md` campos obligatorios de persistencia, trazabilidad de reglas y auditoría operativa, junto con reglas de idempotencia y registro de fallos.
- Se añadieron controles de seguridad contractuales para integridad tamper-evident, control de acceso a auditoría, minimización de PII y no repudio.
- Se actualizó `spec/tasks.md` marcando `CX2-F4` como completada en fase documental.

### Motivo
Garantizar trazabilidad verificable de decisiones de interés y preparar base contractual segura para escalado operativo en CX2-F5.

## 2026-05-11 · CX2-F5 — Escalado operativo

### Cambios
- Se definieron en `spec/design.md` los umbrales operativos finales de escalado con dos rutas (`standard_hot_stable` y `fast_track_explicit_buy`).
- Se incorporaron reglas anti-ruido y anti-duplicado (cooldown, dedupe temporal y guardia por contradicción de intención).
- Se formalizó en `spec/contracts.md` el contrato `CommercialHandoffPayload` con contexto mínimo obligatorio para handoff humano.
- Se añadieron controles de seguridad contractuales de F5 para idempotencia, overrides humanos y minimización de PII en handoff.
- Se añadió checklist manual mínimo de pruebas de fase y se marcó `CX2-F5` como completada en `spec/tasks.md`.

### Motivo
Cerrar el criterio operativo de escalado de forma conservadora y auditable antes de la integración de panel/operación en CX2-F6.

## 2026-05-11 · CX2-F6 — Integración panel/operación

### Cambios
- Se documentó en `spec/design.md` la integración operativa en panel para visualización de estado/score, prioridad sugerida y contexto de decisión.
- Se formalizó en `spec/contracts.md` el contrato `PanelOperationalView` y las acciones humanas `confirmar_handoff`, `corregir_clasificacion` y `reabrir_revision`.
- Se definió trazabilidad obligatoria de overrides manuales con `ManualOverrideRecord` (actor, motivo, correlación e idempotencia).
- Se añadieron controles de seguridad contractuales para autorización contextual, MFA en overrides críticos, separación de funciones e integridad de auditoría.
- Se incorporó checklist manual mínimo de pruebas de F6 y se marcó `CX2-F6` como completada en `spec/tasks.md`.

### Motivo
Cerrar la integración documental entre decisiones CX2 y operación diaria del panel, preparando la instrumentación técnica de F7 sin romper contratos previos.

## 2026-05-11 · CX2-F7 — Calibración y guardrails

### Cambios
- Se definió en `spec/design.md` la rutina de calibración semanal/mensual/trimestral y el marco de decisión `promote|hold|rollback|discard`.
- Se formalizaron en `spec/contracts.md` los contratos `CalibrationCycle`, `CalibrationGuardrails` y `RuleVersionReview`.
- Se añadieron límites explícitos de `fp_rate` y `over_escalation_rate`, más control de incidentes con bloqueo activo.
- Se incorporaron controles de seguridad para integridad de métricas/datasets, aprobación dual de cambios y rollback atómico versionado.
- Se añadieron casos de prueba manual mínimos de F7 y se marcó `CX2-F7` como completada en `spec/tasks.md`.

### Motivo
Establecer un marco de calibración trazable y conservador para evolucionar reglas sin comprometer estabilidad operativa antes del cierre de aceptación en F8.

## 2026-05-11 · CX2-F8 — Cierre y aceptación documental

### Cambios
- Se definieron métricas de éxito documental de negocio (`escalation_precision`, `hot_lead_recall`, `time_to_handoff_median`, `perceived_over_escalation`, `blocking_incidents`) y de operación (`doc_consistency_score`, `audit_trail_completeness`, `decision_reproducibility`, `phase3_readiness`).
- Se formalizó en `spec/contracts.md` el acta de cierre `ClosureApproval` con firma dual, `ClosureConsistencyChecklist` de 30 ítems y `ClosureManifest` inmutable con hash documental.
- Se documentó en `spec/design.md` el diseño de cierre con checklist de consistencia F1-F8, criterios de aprobación (negocio, técnico, gobierno) y métricas de aceptación.
- Se incorporaron controles de seguridad para integridad de evidencias, segregación de funciones en la firma y anti-tampering del manifiesto.
- Se añadió `ADR-008` con la decisión de cierre formal del bloque CX2.
- Se ejecutó la checklist final de consistencia documental verificando trazabilidad completa F1-F7 en los artefactos spec.
- Se marcó `CX2-F8` como completada en `spec/tasks.md`, cerrando las 8 fases del bloque CX2 a nivel documental SDD.

### Motivo
Formalizar la finalización del bloque CX2 como paquete documental completo, consistente y listo para implementación de runtime sin deuda especificativa.

## 2026-05-11 · CX2-F8 — Cierre y aceptación (auditado)

### Cambios
- Se auditó el gap contractual↔runtime: CX2-F1 a CX2-F7 están en fase SDD (spec-only); F8 no dispone de métricas operativas reales hasta que exista implementación. El cierre documental es precondición, no sustituto.
- Se auditaron riesgos residuales P0/P1 del código real (`app/auth.php`, `app/db.php`, `DATA_PATH/users.json`) con impacto directo en trazabilidad, no-repudio y segregación de funciones exigidos por CX2.
- Se formalizaron en `spec/contracts.md` los contratos CX2-F8 distribuidos en 6 bloques (A–F):
  - **A** — `CX2AcceptanceMetrics`: acta de aceptación con métricas de negocio, operación y seguridad, incluyendo gates obligatorios (`credentials_in_code=false`, `plaintext_passwords=false`, `guardrails_status=ok`).
  - **B** — `CX2SecurityClosureChecklist`: 17 ítems de seguridad pre-cierre (SEC-01 a SEC-17) cubriendo credenciales, RBAC, segregación 4-eyes, integridad de evidencias, minimización PII y seguridad contractual CX2.
  - **C** — `CX2EvidencePackage`: paquete de evidencias con SHA-256 del contenido completo, 8 artefactos mínimos obligatorios y verificación de integridad por `artifact_hash`.
  - **D** — `CX2ApprovalRecord`: aprobación con segregación de funciones (2+ aprobadores, roles distintos al requester, MFA para overrides críticos, recusación por conflicto, caducidad temporal).
  - **E** — Trazabilidad de cierre: secuencia inmutable de eventos (`acceptance_generated → checklist_completed → evidence_packaged → approval_granted → closure_finalized`), append-only.
  - **F** — Métricas complementarias de negocio/operación refinadas y `CX2ClosureManifest` como artefacto persistible (`DATA_PATH/cx2_closure_manifest.json`) con hash documental y firma dual.
- Se añadieron 12 casos de prueba manuales mínimos de F8 cubriendo escenarios de aceptación, checklist, integridad de evidencias, segregación de funciones y regresión F1-F7.
- Se actualizó `docs/changelog.md` y se referenció ADR-008. `spec/tasks.md` marca CX2-F8 como completada.

### Riesgos residuales documentados al cierre
| ID | Riesgo | Severidad | Estado |
|----|--------|-----------|--------|
| R01 | Gap spec↔runtime: contratos F1-F7 sin implementación | CRITICAL (A04) | Aceptado con condición: cierre documental no sustituye validación de runtime |
| R02 | Contraseñas en texto plano (`DATA_PATH/users.json`) | CRITICAL (A02) | Exigido en SEC-01 como gate de aceptación |
| R03 | Credenciales DB hardcodeadas (`app/db.php`) | CRITICAL (A02) | Exigido en SEC-02 como gate de aceptación |
| R04 | Auto-login por IP whitelist sin RBAC | HIGH (A01) | Exigido en SEC-03; deshabilitable |
| R05 | Integridad de evidencias sin mecanismo criptográfico | HIGH (A08) | Exigido en SEC-08; hash chaining |
| R06 | Sin RBAC ni segregación 4-eyes implementados | HIGH (A01) | Exigido en SEC-05/SEC-06 |

### Motivo
Cerrar formalmente la fase documental del bloque CX2 con contratos auditados de aceptación, seguridad y gobierno, estableciendo precondiciones verificables (P0 mitigados, checklist aprobado, firma dual) antes de autorizar la implementación técnica de runtime.

## 2026-05-12 · Fase escenarios — Pool de fondos naturales aleatorios (Publicista Perfiles)

### Cambios
- Se añadió pool de 12 fondos 100% naturales (`publicista_natural_background_pool()`): dormitorio, salón, espejo selfie, playa, calle, pared, tienda de ropa, probador, parque, cafetería, coche, escaleras.
- Se creó `publicista_pick_random_backgrounds($count)` para seleccionar N fondos distintos sin repetición.
- En modo Pollo.ai, cuando `setting=random`, cada imagen del pack recibe un fondo distinto automáticamente vía `[FONDO PARA ESTA IMAGEN]`.
- Se añadió opción `random` al selector de fondo como default del formulario.
- Se adaptó `publicista_build_pollo_environment_guard()` para modo random.
- Se añadió validación whitelist de `setting_type` en `publicista_normalize_outfit_params()` (finding MEDIUM corregido).
- Se corrigió dead code en `$settingMap` que hacía inaccesible la key `random` (finding LOW corregido).

### Archivos
- `app/helpers.php`, `app/publicista.php`, `app/views.php`, `docs/changelog.md`

### Motivo
Eliminar el patrón "mismo fondo en las 4 fotos" que delataba IA. Cada foto del pack recibe un fondo natural distinto automáticamente, simulando fotos reales en días y lugares diferentes.

## 2026-05-12 · EstadosWasap SetupEstados — Datos y configuración

### Cambios
- Se creó `data/publicista_estados_wasap.json` como almacén de configuración y log de publicaciones de estados de WhatsApp.
- Se implementaron funciones CRUD en `app/publicista.php`: `publicista_estados_wasap_config_defaults()`, `publicista_estados_wasap_get_config()`, `publicista_estados_wasap_save_config()`, `publicista_estados_wasap_config_normalize()`, `publicista_estados_wasap_get_log()`, `publicista_estados_wasap_add_log_entry()`.
- Se definieron 6 formatos de publicación (`chicas_de_hoy`, `chica_del_dia`, `duo_sexy`, `catalogo_rapido`, `estrella_grupo`, `mix_aleatorio`) y 2 modos de frecuencia (`cada_x_horas`, `x_veces_al_dia`).
- Se añadió `publicista_estados_wasap_get_bot_casa_lines()` para detectar dinámicamente líneas con `uso="bot casa"` desde `telefonos.json`.
- Normalización con validación de enums, clamping de valores numéricos, dedupe de IDs de línea y validación HH:MM vía regex.
- Log con rotación automática (máximo 200 entradas).

### Archivos
- `data/publicista_estados_wasap.json` (nuevo)
- `app/publicista.php` (+90 líneas)
- `spec/tasks.md` (fase SetupEstados)
- `docs/changelog.md`

### Motivo
Primera fase de la feature EstadosWasap: establecer el modelo de datos, configuración y funciones base para permitir la publicación automática de estados de WhatsApp con fotos de chicas activas desde las líneas bot casa.

## 2026-05-12 · EstadosWasap MotorEstados — Lógica de negocio

### Cambios
- Se implementó `publicista_estados_wasap_fetch_active_girls()`: fetch HTTPS de `girls.json` con caché local de 15 min y fallback a caché expirada si falla la red.
- Se implementaron 6 builders de formato de estado (`chicas_de_hoy`, `chica_del_dia`, `duo_sexy`, `catalogo_rapido`, `estrella_grupo`, `mix_aleatorio`) con emojis aleatorios y tono sexy.
- Se implementó `publicista_estados_wasap_publicar_ahora()`: orquestador que obtiene chicas activas, construye texto y publica en cada línea bot casa habilitada vía WAHA `POST /api/default/status/text`.
- Se añadió `publicista_estados_wasap_get_waha_settings()` para obtener host/key/timeout de la config comercial con fallback a defaults.
- Se añadieron acciones POST: `save_estados_wasap_config` y `publicar_estado_manual` en `app/actions.php`.
- Publicación verificada con éxito: WAHA respondió 201 Created en línea publi2.

### Archivos
- `app/publicista.php` (+195 líneas: 11 funciones nuevas)
- `app/actions.php` (+18 líneas: 2 acciones + dispatch cases)
- `spec/tasks.md` (MotorEstados completado)
- `docs/changelog.md`

### Motivo
Dotar de lógica de negocio completa para publicar estados de WhatsApp atractivos con fotos de chicas activas desde las líneas bot casa, con formatos variados y registro de actividad.

## 2026-05-12 · EstadosWasap PanelEstados — Interfaz visual

### Cambios
- Se añadió tab `📱 Estados` en la barra de subpestañas de Publicista.
- Nueva página `render_publicista_estados_wasap_page()` con:
  - Panel de estado (activo/pausado, líneas habilitadas, formato, frecuencia, última publicación).
  - Formulario de configuración completo: on/off, frecuencia (tipo + valor), horario (inicio/fin), selector de formato (6 opciones), selector de líneas bot casa con checkboxes.
  - Botón "Publicar ahora" para disparo manual con feedback flash.
  - Tabla de historial con fecha, línea, formato, resultado, HTTP code y vista previa del texto.
- Se actualizó versión de caché CSS/JS en `index.php` (v=20260512_2).
- Verificado: renderizado sin errores, 12/12 checks de presencia de elementos OK, publicación 2/2 líneas exitosa.

### Archivos
- `app/views.php` (+110 líneas: tab registration + render completo)
- `index.php` (cache busting v=20260512_2)
- `spec/tasks.md` (PanelEstados completado)
- `docs/changelog.md`

### Motivo
Proveer una interfaz visual completa para gestionar la publicación de estados de WhatsApp: configurar, disparar manualmente y auditar el historial desde el panel de Publicista.

## 2026-05-12 · Fase planos — Encuadres casuales y no profesionales (Publicista Perfiles)

### Cambios
- Se añadieron opciones de encuadre `lejano` (persona a 2-3m) y `descentrado` (no centrada) al selector.
- Se añadió pose `casual` (foto de amigo, sin pose de modelo).
- En `publicista_build_pollo_master_prompt()`: framing y pose maps actualizados con descripciones casuales.
- En `publicista_build_prompt_variants()`: para Pollo, los shots se reemplazan con planos lejanos, descentrados y casuales. Selfies naturales de móvil.
- **Corregido bug crítico**: el path Pollo ahora llama a `build_prompt_variants()` (antes usaba `array_fill` con 4 prompts idénticos).
- **Corregido**: `$isPollo` en `build_prompt_variants` ahora usa `publicista_job_uses_pollo_model()` que lee `$job['models']['image']` correctamente.
- Se añadió validación whitelist para `framing`, `pose`, `expression`, `makeup`, `lighting` y `outfit_variety` en `normalize_outfit_params`.
- Se añadió `lejano`/`descentrado` al branch no-Pollo para evitar caer en variado.
- Defaults del form: encuadre `lejano`, pose `casual`.

### Archivos
- `app/helpers.php`, `app/publicista.php`, `app/views.php`, `docs/changelog.md`

### Motivo
Eliminar el aspecto "demasiado profesional" de las fotos: persona siempre centrada, en primer plano, pose editorial. Ahora las fotos parecen tomadas por un amigo con el móvil: a veces más lejos, a veces descentradas, siempre naturales.

## 2026-05-12 · Fase ropa — Ropa automática humilde y sexy, look distinto por foto (Publicista Perfiles)

### Cambios
- Se añadió opción `auto_random` como default en el selector de estilo: el sistema asigna automáticamente un look diferente por imagen desde un pool de 12 outfits.
- Pool `publicista_cheap_sexy_outfit_pool()`: 12 combinaciones baratas y sexys (vaqueros+top, minifalda, vestido corto ceñido, shorts, leggings, body, mono, etc.).
- Cada imagen recibe un outfit distinto vía `[ROPA PARA ESTA IMAGEN]` inyectado en el prompt de cada variante.
- Lenguaje de tejidos actualizado: "polyester barato", "licra de mercadillo", "denim desgastado", "imitación cuero" — ropa de Primark/Shein, no de lujo.
- Nivel `sexy` redefinido como "sexy de barrio": ceñido, escotes moderados, algo de piel — sin lencería visible, sin desnudo.
- `publicista_build_outfit_session_lock()` adaptado para `auto_random`: instruye al modelo a usar looks diferentes por imagen en vez de forzar el mismo.
- `publicista_build_pollo_master_prompt()`: cuando `auto_random`, el prompt indica que cada imagen lleva un look distinto asignado en `[ROPA PARA ESTA IMAGEN]`.
- Se añadió validación whitelist de `outfit_style` en `normalize_outfit_params` (finding LOW corregido).
- Se corrigió defensivamente el fallback de `$style` en `build_outfit_prompt_details` para no usar raw input.

### Archivos
- `app/helpers.php`, `app/publicista.php`, `app/views.php`, `docs/changelog.md`

### Motivo
Eliminar el patrón "mismo vestido corto con escote en las 4 fotos" y el aspecto de "ropa de lujo". Ahora cada imagen muestra un look diferente, barato y realista, adecuado al perfil socioeconómico del sector.

## 2026-05-12 · Fase ratios — Proporciones nativas de móvil, sin recorte 1:1 (Publicista Perfiles)

### Cambios
- Modelo Pollo por defecto cambiado a `flux-dev` (ratio 2:3 nativo, como foto de móvil vertical).
- Para trabajos Pollo: se **elimina el recorte cuadrado 1:1** — las imágenes mantienen su ratio nativo (2:3 o 4:3 según modelo).
- En vez de llamar al Python worker `prepare-source` (que fuerza lienzo cuadrado), se copia la imagen generada tal cual y se genera un preview manteniendo el ratio.
- Misma lógica aplicada en `publicista_regenerate_candidate()` para regeneraciones individuales.
- Añadida protección contra memory exhaustion: límite de 10MB de archivo y 50M píxeles antes de `imagecreatefromstring`.

### Archivos
- `app/publicista.php`, `docs/changelog.md`

### Motivo
Las imágenes 1:1 parecen editadas profesionalmente. Las fotos reales de móvil tienen ratios 2:3, 3:4 o 9:16 — nunca cuadradas. Mantener el ratio nativo del modelo Pollo elimina este patrón artificial.

## 2026-05-12 · Fase outpainting — GPT extiende imágenes 1:1 de pollo-image-v2 a ratio de móvil

### Cambios
- Modelo default restaurado a `pollo-image-v2` (mejor calidad, pero fuerza 1:1).
- Nueva función `publicista_outpaint_to_phone_ratio()`: convierte 1:1 → ratios de móvil (2:3, 3:4, 4:5, 9:16 aleatorios) vía outpainting con GPT.
- Nuevo comando `pad-canvas` en Python worker: crea lienzo con ratio destino + máscara (negro=preservar centro, blanco=rellenar bordes con GPT).
- Pipeline Pollo: tras generar 1:1, se ejecuta outpainting vía OpenAI Image Edit API con máscara. GPT extiende fondo sin tocar a la persona.
- Si outpainting falla, se mantiene la imagen 1:1 original (degradación gracefully).

### Archivos
- `app/publicista.php`, `tools/publicista_image_worker.py`, `docs/changelog.md`

### Motivo
pollo-image-v2 es el mejor modelo pero genera 1:1. El outpainting con GPT añade bordes de forma natural, convirtiendo la imagen cuadrada en una foto con proporción de móvil sin perder la calidad base.

## 2026-05-14 · Fix integración — 4 llamadas Pollo individuales + outpainting en finales

### Bugs corregidos
- **BUG CRÍTICO**: Pollo ahora genera 4 llamadas individuales (una por variant) en vez de 1 batch con `$variants[0]`. Cada imagen recibe su propio fondo, ropa y encuadre distinto.
- **BUG**: Outpainting GPT movido de candidatas a `publicista_build_direct_final_output()`. Las candidatas muestran el raw 1:1 de Pollo; las finales reciben el outpainting a ratio de móvil.
- **BUG**: Default de framing corregido a `lejano` (estaba `variado`).
- **BUG**: Añadido log de error cuando el outpainting falla (`premium_refine_error` en la final).

### Archivos
- `app/publicista.php`, `app/views.php`, `docs/changelog.md`

## 2026-05-14 · Sexify — Pool de outfits ampliado + poses/expresiones sexys

### Cambios
- Pool de outfits ampliado de 12 a **16** combinaciones, todas sexys/sugerentes sin cruzar el límite sexual. Añadidos: vestido lencero falso, transparencia controlada, camiseta mojada, escote espalda, top palabra de honor, body transparencia parcial, vestido punto ceñido.
- Nueva opción de pose `sugerente` (muy femenina, insinuante, provocativa sin ser explícita) en `publicista_pose_options()`.
- Poses Pollo actualizadas: lenguaje más sexy/sensual en `poseMap` y `poseExtra` (cuerpo en S, pecho realzado, caderas marcadas, mirada magnética).
- Expresiones Pollo actualizadas: mirada sugerente, labios entreabiertos, ceja arqueada — sexy de anuncio sin ser explícita.
- Default del form cambiado a pose `sugerente`.
- Añadido `sugerente` al non-Pollo `$poseExtra` para consistencia.

### Archivos
- `app/helpers.php`, `app/publicista.php`, `app/views.php`, `docs/changelog.md`

### Motivo
Las fotos se publican en portales de anuncios y necesitan ser sexys/sugerentes para llamar la atención, sin cruzar el límite que hace que algunas webs las rechacen.
