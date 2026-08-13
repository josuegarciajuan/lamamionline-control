# Técnico · Módulos

Resumen por módulo con foco en entradas, datos, acciones, vistas y riesgos técnicos.

## 1) Núcleo aplicación

### Entrada
- `index.php` (GET/POST principal).

### Datos
- Sesión PHP.
- Ficheros JSON/JSONL vía `app/storage.php`.

### Acciones
- `handle_post_actions()` despacha por `action`.

### Vistas
- `app/views.php` (`render_*`).

### Riesgos
- `handle_post_actions()` muy grande (acoplamiento y complejidad).

---

## 2) Autenticación

### Entrada
- Formulario login (`action=login`).
- Auto-login por IP whitelist.

### Datos
- `data/users.json`
- `data/settings.json` (whitelist)

### Acciones
- `login_user`, `logout_user`, `auth_auto_login_from_whitelist`.

### Vistas
- `render_login_page`.

### Riesgos
- Contraseñas en claro en JSON (sin hash).
- Control de acceso basado en IP para auto-login.

---

## 3) LaMami (interesadas/clientas/leads)

### Entrada
- Formularios de interesadas/clientas/leads.

### Datos
- `interesadas.json`
- `clientes.json`
- `leads.json`

### Acciones clave
- `save_interesada`, `set_interesada_estado`, `convert_interesada`
- `save_clienta`, `baja_clienta`, `alta_clienta`
- `quick_lead`, `delete_lead`

### Vistas
- `render_lamami_page` + tabs (`interesadas`, `clientas`, `lamamibot`).

### Riesgos
- Dependencias cruzadas entre estado de clienta/bot/leads.

---

## 4) Bots y LamamiBot

### Entrada
- Gestión manual en pantalla bots y ficha LamamiBot.

### Datos
- `bots.json`
- `lamamibot.json`
- Relación con `telefonos.json` y `clientes.json`

### Acciones
- `save_bot`, `set_bot_runtime_mode`, `generate_bot_assets`
- `save_lamamibot`, `generate_lamamibot_assets`, `set_lamamibot_runtime_mode`

### Vistas
- `render_bots_page`
- Subtab lamamibot en `render_lamami_page`

### Riesgos
- Gestión de assets generados y sincronías con configuración externa.

---

## 5) Casawasap

### Entrada
- Alta/edición de contacto, conversión, pagos.

### Datos
- `casawasap_contactos.json`
- `casawasap_pagos.json`

### Acciones
- `save_casawasap_contacto`, `convert_casawasap_cliente`
- `casawasap_add_pago`, `delete_casawasap_pago`
- `set_casawasap_estado`, `baja_casawasap_cliente`, `alta_casawasap_cliente`

### Vistas
- `render_casawasap_page`

### Riesgos
- Fichero de pagos puede estar vacío/cambiar de formato en operación real.

---

## 6) Jostal

### Entrada
- Interesadas, clientas, leads, ventas.

### Datos
- `jostal_interesadas.json`
- `jostal_clientas.json`
- `jostal_leads.json`
- `jostal_ventas.json`

### Acciones
- `save_jostal_interesada`, `convert_jostal_clienta`, `save_jostal_clienta`
- `jostal_add_lead`, `jostal_add_venta`
- acciones de descarte/reactivación y control de “en casa”

### Vistas
- `render_jostal_page` + subtabs.

### Riesgos
- Varias reglas de estado temporal/estancia con validaciones de coherencia.

---

## 7) Comercial

### Entrada
- UI de Comercial.
- Webhook HTTP (`comercial_webhook.php`).
- Tick por cron (`cron_comercial.php`).

### Datos
- `comercial_settings.json`, `comercial_processes.json`, `comercial_threads.json`, `comercial_leads.json`
- `comercial_line_state.json`, `comercial_runtime.json`, `comercial_daily_stats.json`
- `comercial_events.jsonl`, `comercial_webhook_log.jsonl`, `comercial_webhook_seen.json`, `comercial_queues/*.jsonl`

### Acciones
- guardar distribución/ajustes/procesos
- run tick / test probe / salud de líneas
- gestión de estado y mensajes de hilos

### Vistas
- `render_comercial_page`

### Riesgos
- Alto volumen y mezcla JSON + JSONL + lockfile.
- Punto crítico de concurrencia (webhook + cron + UI).

---

## 8) Publicista

### Entrada
- UI de planificación, campañas y ejecución.

### Datos
- `anuncios.json`
- `publicista_*.json`
- artefactos filesystem en `data/publicista/**`

### Acciones
- CRUD de cuentas/planificaciones/campañas/jobs
- generación/ejecución de campañas
- tareas y runs

### Vistas
- `render_publicista_page`

### Riesgos
- Módulo de mayor peso en datos.
- Reescrituras de arrays grandes en modo JSON.

---

## 9) Avisos

### Entrada
- UI de avisos.
- cron (`cron_avisos.php`).
- alta manual planificada.

### Datos
- `avisos.json`
- `avisos_runs.json`
- configuración en `avisos_config.php`

### Acciones
- `dismiss_aviso`, `create_manual_aviso`, `delete_planned_aviso`

### Vistas
- panel lateral + página dedicada de avisos.

### Riesgos
- Dependencia transversal de múltiples módulos.

---

## 10) Soporte (Gastos, Agenda, Eurekas, Voz)

### Datos
- `gastos.json`, `agenda.json`, `eurekas.json`
- `voice_commands_log.json`, `voice_pending_actions.json`

### Acciones
- altas/bajas/edición de gastos, agenda y eureka
- comandos de voz (`voice_command`) y configuración asociada

### Riesgos
- Menor criticidad de negocio, pero impactan reportes y operación diaria.
