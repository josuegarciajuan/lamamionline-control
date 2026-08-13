# 01 · Contratos de rutas y acciones

## Estado actual (observado en código)

## 1) Routing principal (GET)

### Entrada
- `index.php`
  - `page` por defecto: `dashboard`.
  - Si `page=logout`: cierra sesión y redirige a login.
  - Si no hay sesión y `page!=login`: redirige a login.
  - Si `POST`: ejecuta `handle_post_actions()`.

### Pages soportadas (switch actual)
- `lamami`
- `interesadas` (atajo a `lamami&tab=interesadas`)
- `clientas` (atajo a `lamami&tab=clientas`)
- `lamamibot` (atajo a `lamami&tab=lamamibot`)
- `publicista`
- `comercial`
- `bots`
- `informes`
- `gridmensual` (atajo a informes con `view=grid`)
- `josue`
- `casawasap`
- `jostal`
- `gastos`
- `avisos`
- fallback: `dashboard`

Trazabilidad: `index.php`.

## 2) Contratos por tab (GET)

### LaMami
- URL base: `index.php?page=lamami`
- Tabs válidas: `interesadas`, `clientas`, `lamamibot`
- Default/fallback: `interesadas`
- Trazabilidad: `app/views.php` (`render_lamami_page`).

### Publicista
- URL base: `index.php?page=publicista`
- Tabs válidas: `crear_perfiles`, `estrategias`, `cuentas`, `campanas`, `subir_anuncios`, `run_log`
- Default/fallback: `crear_perfiles`
- Alias legado: `calculo_publicidad` → `estrategias`
- `cuentas` protegida por desbloqueo de sesión (`publicista_accounts_unlocked`)
- Trazabilidad: `app/views.php` (`render_publicista_page`).

### Comercial
- URL base: `index.php?page=comercial`
- Tabs válidas: `resumen`, `procesos`, `lineas`, `conversaciones`, `leads`, `blacklist`, `ajustes`, `logs`
- Default/fallback: `resumen`
- Trazabilidad: `app/comercial.php` (`comercial_page_tabs`, `render_comercial_page`).

### Josue
- URL base: `index.php?page=josue`
- Tabs válidas: `publias`, `captacion`, `notas`, `waha`, `telefonos`, `agenda`, `eurekas`, `config`, `configm`
- Default/fallback: `publias`
- Trazabilidad: `app/views.php` (`render_josue_page`).

### Jostal
- URL base: `index.php?page=jostal`
- Tabs válidas: `interesadas`, `clientas`, `ventas`, `informes`
- Default/fallback: `interesadas`
- Trazabilidad: `app/views.php` (`render_jostal_page`).

### Casawasap
- URL base: `index.php?page=casawasap`
- Sin `tab` principal; funciona por pantallas/acciones dentro de la misma vista (`edit`, `cliente_id`, fechas filtro).
- Trazabilidad: `app/views.php` (`render_casawasap_page`).

## 3) Acciones POST críticas (contrato funcional)

Dispatcher: `app/actions.php` (`handle_post_actions`).

### Autenticación
- `action=login`
  - Entrada: `username`, `password`
  - Resultado: login + redirect dashboard o error + redirect login.

### Núcleo LaMami
- `save_interesada`, `delete_interesada`, `set_interesada_estado`, `convert_interesada`
- `save_clienta`, `baja_clienta`, `alta_clienta`
- `quick_lead`, `delete_lead`

### Bots
- `save_bot`, `delete_bot`, `set_bot_runtime_mode`, `generate_bot_assets`
- `save_lamamibot`, `set_lamamibot_runtime_mode`, `generate_lamamibot_assets`

### Casawasap
- `save_casawasap_contacto`, `set_casawasap_estado`, `convert_casawasap_cliente`
- `baja_casawasap_cliente`, `alta_casawasap_cliente`
- `casawasap_add_pago`, `delete_casawasap_pago`

### Jostal
- `save_jostal_interesada`, `discard_jostal_interesada`, `reactivate_jostal_interesada`
- `convert_jostal_clienta`, `save_jostal_clienta`, `jostal_salida_casa`, `jostal_reactivar_casa`
- `jostal_add_lead`, `jostal_add_venta`, `jostal_update_rent_due_weekday`

### Publicista
- `unlock_publicista_accounts`
- `save_publicista_account`, `delete_publicista_account`
- `save_publicista_planning`, `duplicate_publicista_planning`, `delete_publicista_planning`, `set_publicista_planning_status`
- `save_publicista_campaign`, `generate_publicista_campaign`, `execute_publicista_campaign`, `stop_publicista_campaign_run`, `delete_publicista_campaign`, `set_publicista_campaign_status`
- `run_publicista_task`, `set_publicista_task_status`

### Comercial
- `save_comercial_distribution`, `toggle_comercial_process_enabled`, `save_comercial_process`
- `save_comercial_settings`, `save_comercial_blacklist`, `delete_comercial_blacklist`
- `comercial_run_tick`, `comercial_run_test_probe`, `comercial_reset_test_probe`
- `save_comercial_line_state`, `comercial_check_lines_health`
- `comercial_set_thread_stage`, `comercial_send_thread_message`, `comercial_promote_thread`

### Operación general
- `dismiss_aviso`, `create_manual_aviso`, `delete_planned_aviso`
- `save_agenda`, `delete_agenda`
- `save_gasto`/`add_gasto`, `delete_gasto` (según formulario)
- `save_eureka`, `generate_eureka_prompt`, `delete_eureka`, `set_eureka_estado`

## Recomendación (no implementada)
- Consolidar un catálogo de acciones “canónicas” y detectar duplicados/legacy en `app/actions.php` (hay casos repetidos).
- Para cambios futuros: no introducir nuevas acciones sin documentar contrato mínimo (inputs, validaciones, redirects/salida JSON).
