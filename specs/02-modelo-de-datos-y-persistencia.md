# 02 · Modelo de datos y persistencia

## Estado actual (observado en código)

## 1) Estrategia de persistencia (dual)

- Backend configurable por `CRM_STORAGE_BACKEND` con modos: `json`, `dual`, `mysql`.
- Lectura/escritura principal abstraída en `app/storage.php`.
- En `dual`, conviven JSON en `data/` y tablas MySQL `crm_*`.

Trazabilidad:
- Modo backend: `app/storage.php` (`storage_backend_mode`, `storage_backend_allowed_modes`).
- Config DB: `app/db.php`.
- Esquema SQL de referencia: `sql/mysql_phase1_schema.sql`.

## 2) Entidades operativas (archivo JSON ↔ tabla MySQL)

| Entidad funcional | JSON en `data/` | Tabla MySQL (`crm_*`) |
|---|---|---|
| Configuración global | `settings.json` | `crm_settings` |
| Usuarios | `users.json` | `crm_users` |
| Interesadas LaMami | `interesadas.json` | `crm_interesadas` |
| Clientas/Clientes LaMami | `clientes.json` | `crm_clientes` |
| Leads LaMami | `leads.json` | `crm_leads` |
| Bots | `bots.json` | `crm_bots` |
| LamamiBot (singleton) | `lamamibot.json` | `crm_lamamibot` |
| Teléfonos | `telefonos.json` | `crm_telefonos` |
| Agenda | `agenda.json` | `crm_agenda` |
| Gastos | `gastos.json` | `crm_gastos` |
| Eurekas | `eurekas.json` | `crm_eurekas` |
| Casawasap contactos | `casawasap_contactos.json` | `crm_casawasap_contactos` |
| Casawasap pagos | `casawasap_pagos.json` | `crm_casawasap_pagos` |
| Jostal interesadas | `jostal_interesadas.json` | `crm_jostal_interesadas` |
| Jostal clientas | `jostal_clientas.json` | `crm_jostal_clientas` |
| Jostal leads | `jostal_leads.json` | `crm_jostal_leads` |
| Jostal ventas | `jostal_ventas.json` | `crm_jostal_ventas` |
| Avisos | `avisos.json` | `crm_avisos` |
| Runs de avisos | `avisos_runs.json` | `crm_avisos_runs` |
| Comercial settings | `comercial_settings.json` | `crm_comercial_settings` |
| Comercial procesos | `comercial_processes.json` | `crm_comercial_processes` |
| Comercial runtime | `comercial_runtime.json` | `crm_comercial_runtime` |
| Comercial estado líneas | `comercial_line_state.json` | `crm_comercial_line_state` |
| Comercial stats diario | `comercial_daily_stats.json` | `crm_comercial_daily_stats` |
| Comercial hilos | `comercial_threads.json` | `crm_comercial_threads` |
| Comercial leads | `comercial_leads.json` | `crm_comercial_leads` |
| Comercial webhook seen | `comercial_webhook_seen.json` | `crm_comercial_webhook_seen` |
| Publicista cuentas/anuncios | `anuncios.json` | `crm_publicista_accounts` |
| Publicista templates | `publicista_templates.json` | `crm_publicista_templates` |
| Publicista jobs | `publicista_jobs.json` | `crm_publicista_jobs` |
| Publicista plannings | `publicista_plannings.json` | `crm_publicista_plannings` |
| Publicista campañas | `publicista_campaigns.json` | `crm_publicista_campaigns` |
| Publicista campaign items | `publicista_campaign_items.json` | `crm_publicista_campaign_items` |
| Publicista tasks | `publicista_tasks.json` | `crm_publicista_tasks` |
| Publicista runs | `publicista_runs.json` | `crm_publicista_runs` |
| Voice commands log | `voice_commands_log.json` | `crm_voice_commands_log` |
| Voice pending actions | `voice_pending_actions.json` | `crm_voice_pending_actions` |

Fuente de mapeo: `app/storage.php` (`storage_mysql_file_spec`).

## 3) Persistencia JSONL operativa

| Área | Archivo JSONL | Uso funcional |
|---|---|---|
| Comercial | `data/comercial_events.jsonl` | Eventos operativos/auditoría de ejecución |
| Comercial | `data/comercial_webhook_log.jsonl` | Trazas de entradas webhook |
| Comercial colas | `data/comercial_queues/*.jsonl` | Fuentes de mensajes por proceso |
| Publicista | `data/.../batch_images_input.jsonl` y outputs/errors | Lotes para generación/procesado |

Trazabilidad: `app/comercial.php`, `app/publicista.php`.

## 4) Relaciones funcionales clave (operativas)

- `interesadas` → `clientes` (conversión de interesada a clienta).
- `clientes` → `leads` (lead asociado por `cliente_id`).
- `clientes` → `bots` (bot asociado por `cliente_id`).
- `casawasap_contactos` (estado interesado/cliente/baja) → `casawasap_pagos` (`cliente_id`).
- `jostal_interesadas` → `jostal_clientas` → (`jostal_leads`, `jostal_ventas`).
- `telefonos` se usa como inventario de líneas para Comercial y vínculos WAHA/destacamos.
- Publicista enlaza cuentas (`anuncios`), campañas, items, tasks y runs.

## Recomendación (no implementada)
- Mantener un “diccionario de campos críticos” por entidad (ID, estados, fechas, referencias) para evitar drift entre JSON y MySQL.
- Antes de añadir una entidad nueva: definir primero su mapeo en `storage_mysql_file_spec` y su ruta de fallback JSON.
