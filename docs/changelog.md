# Changelog

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
