# Migracion JSON -> MySQL · Fase 1

## Estado auditado
Auditoria rehecha sobre el codigo y los datos reales del proyecto el `2026-04-04`.

Esta fase no cambia el backend activo. Deja cerrados:

- inventario real de JSON / JSONL / NDJSON usados
- relaciones entre entidades
- modulos criticos y cuellos de botella
- esquema MySQL inicial completo
- estrategia operativa `json / dual / mysql`

## Alcance real confirmado

### Runtime activo
El runtime del CRM usa `data/` porque `DATA_PATH` apunta a `BASE_PATH . '/data'` en [app/bootstrap.php](/var/www/html/atupuerta/app/bootstrap.php#L5).

### Duplicado no operativo
Existe un directorio literal `DATA_PATH/` con copias de ficheros JSON, pero no hay referencias de codigo a esa ruta literal. Para la migracion se considera **fuente viva** solo `data/`.

### Observacion clave del storage actual
[app/storage.php](/var/www/html/atupuerta/app/storage.php#L86) sigue leyendo y reescribiendo ficheros completos en `storage_write`, `storage_upsert` y `storage_delete`. Cuando un JSON esta malformado, `json_decode()` falla y el sistema cae a `array()` sin error funcional visible. Esto convierte corrupciones de fichero en "datos vacios" desde la perspectiva del CRM.

## Inventario real de datos usados

### Core CRM
| Recurso | Forma real | Estado actual | Uso | Destino SQL |
| --- | --- | --- | --- | --- |
| `data/settings.json` | objeto | 177 KB | configuracion global y avisos | `crm_settings` |
| `data/users.json` | array | 1 usuario | autenticacion | `crm_users` |
| `data/agenda.json` | array | 3 filas | agenda manual | `crm_agenda` |
| `data/gastos.json` | array | 13 filas | gastos e informes | `crm_gastos` |
| `data/interesadas.json` | array | 25 filas | captacion Lamami | `crm_interesadas` |
| `data/clientes.json` | array | 7 filas | clientas Lamami | `crm_clientes` |
| `data/leads.json` | array malformado | 384 bytes, coma final sobrante | leads Lamami | `crm_leads` |
| `data/eurekas.json` | array | vacio ahora | backlog interno de ideas | `crm_eurekas` |
| `data/telefonos.json` | array | 14 filas | lineas / puertos / WAHA | `crm_telefonos` |
| `data/bots.json` | array | 4 filas, 652 KB | bots de clientes | `crm_bots` |
| `data/lamamibot.json` | objeto | 223 KB | configuracion LamamiBot | `crm_lamamibot` |
| `data/voice_commands_log.json` | array | 44 filas, 103 KB | log de voz | `crm_voice_commands_log` |
| `data/voice_pending_actions.json` | array | 2 filas | pendientes de voz | `crm_voice_pending_actions` |

### Avisos
| Recurso | Forma real | Estado actual | Uso | Destino SQL |
| --- | --- | --- | --- | --- |
| `data/avisos.json` | array | 510 filas, 325 KB | avisos y estados | `crm_avisos` |
| `data/avisos_runs.json` | array | 500 filas, 900 KB | historico de ejecuciones | `crm_avisos_runs` |

### CasaWasap
| Recurso | Forma real | Estado actual | Uso | Destino SQL |
| --- | --- | --- | --- | --- |
| `data/casawasap_contactos.json` | array | 11 filas | interesados / clientes | `crm_casawasap_contactos` |
| `data/casawasap_pagos.json` | fichero vacio | 0 bytes | pagos por cliente | `crm_casawasap_pagos` |

### Jostal
| Recurso | Forma real | Estado actual | Uso | Destino SQL |
| --- | --- | --- | --- | --- |
| `data/jostal_interesadas.json` | array | 36 filas | interesadas Jostal | `crm_jostal_interesadas` |
| `data/jostal_clientas.json` | array | 31 filas | clientas Jostal | `crm_jostal_clientas` |
| `data/jostal_leads.json` | array | 288 filas, 74 KB | leads Jostal | `crm_jostal_leads` |
| `data/jostal_ventas.json` | fichero vacio | 0 bytes | ventas manuales Jostal | `crm_jostal_ventas` |

### Comercial
| Recurso | Forma real | Estado actual | Uso | Destino SQL |
| --- | --- | --- | --- | --- |
| `data/comercial_settings.json` | objeto | 19 claves | config WAHA y autoregulacion | `crm_comercial_settings` |
| `data/comercial_processes.json` | array | 4 procesos | procesos y estrategia por origen | `crm_comercial_processes` + `crm_comercial_process_lines` |
| `data/comercial_threads.json` | array | 146 filas, 215 KB | conversaciones | `crm_comercial_threads` |
| `data/comercial_leads.json` | array | vacio ahora | leads comerciales | `crm_comercial_leads` |
| `data/comercial_line_state.json` | array | 14 filas | salud y potencia de lineas | `crm_comercial_line_state` |
| `data/comercial_daily_stats.json` | objeto | solo `_autoregulation` hoy | meta de autoregulacion | `crm_comercial_daily_stats` |
| `data/comercial_runtime.json` | objeto | singleton | ultimo envio global | `crm_comercial_runtime` |
| `data/comercial_webhook_seen.json` | array de strings | 17 ids | deduplicacion | `crm_comercial_webhook_seen` |
| `data/comercial_events.jsonl` | JSONL | 2.160 lineas, 448 KB | eventos de ejecucion | `crm_comercial_events` |
| `data/comercial_webhook_log.jsonl` | JSONL | 88 lineas, 83 KB | trazas del webhook | `crm_comercial_webhook_logs` |
| `data/comercial_queues/*.jsonl` | JSONL por cola | 0 a 945 lineas por fichero | origen de captacion | `crm_comercial_queue_items` |

### Publicista · entidades canonicas
| Recurso | Forma real | Estado actual | Uso | Destino SQL |
| --- | --- | --- | --- | --- |
| `data/anuncios.json` | array | 8 filas | cuentas/publicadores | `crm_publicista_accounts` |
| `data/publicista_templates.json` | array | vacio ahora | plantillas | `crm_publicista_templates` |
| `data/publicista_jobs.json` | array | 7 filas, 435 KB | productos/trabajos | `crm_publicista_jobs` |
| `data/publicista_plannings.json` | array | 1 fila, 344 KB | estudio y estrategia | `crm_publicista_plannings` |
| `data/publicista_campaigns.json` | array | 14 filas, 19.1 MB | campanas | `crm_publicista_campaigns` |
| `data/publicista_campaign_items.json` | array | 48 filas, 93.7 MB | items por anuncio | `crm_publicista_campaign_items` |
| `data/publicista_tasks.json` | array | 8 filas | automatizaciones | `crm_publicista_tasks` |
| `data/publicista_runs.json` | array | 4 filas, 321 KB | ejecuciones de campana | `crm_publicista_runs` |

### Publicista · artefactos de filesystem que siguen fuera de MySQL
Estos recursos **si se usan**, pero no se modelan como tablas operativas en esta fase. Se mantienen en disco y, cuando convenga, solo se indexa metadato.

| Patron | Forma real | Estado actual | Decision |
| --- | --- | --- | --- |
| `data/publicista/free_bump_logs.ndjson` | NDJSON | 10.3 MB | seguir en filesystem en Fase 1 |
| `data/publicista/jobs/<job_id>/meta/*.json` | JSON | multiples snapshots y resultados | filesystem + metadato opcional en `crm_publicista_job_artifacts` |
| `data/publicista/jobs/<job_id>/meta/*.jsonl` | JSONL | batches y errores OpenAI | filesystem + metadato opcional |
| `data/publicista/jobs/<job_id>/logs/*.json` | JSON | logs detallados por job | filesystem + metadato opcional |
| `data/publicista/tmp/*.json` | JSON | trazas de debug puntuales | filesystem, no tabla |

## Calidad actual de datos y riesgos ya confirmados

- `data/leads.json` esta malformado por una coma final sobrante.
- `data/casawasap_pagos.json` esta vacio.
- `data/jostal_ventas.json` esta vacio.
- `storage_read()` en [app/storage.php](/var/www/html/atupuerta/app/storage.php#L86) convierte JSON invalido a array vacio, asi que el sistema no rompe pero puede ocultar corrupciones.
- `users.json` guarda la clave en texto plano, no hash.
- `comercial_webhook.php` escribe directo en `data/comercial_webhook_log.jsonl` fuera de `storage.php`.
- `comercial` mezcla JSON clasico, JSONL append-only y fichero de bloqueo `comercial_webhook_seen.lock`.
- `publicista` ya tiene un modelo hibrido: entidad canonica en JSON + artefactos pesados en directorios por job.

## Mapa de relaciones entre entidades

### Lamami CRM
- `interesadas.cliente_id` -> `clientes.id`
- `clientes.source_interesada_id` -> `interesadas.id`
- `bots.cliente_id` -> `clientes.id`
- `leads.cliente_id` -> `clientes.id`
- `leads.bot_id` -> `bots.id`
- `lamamibot.clientas_ids[]` -> `clientes.id`
- `lamamibot.telefonos_ids[]` -> `telefonos.id`

### CasaWasap
- `casawasap_pagos.cliente_id` -> `casawasap_contactos.id`

### Jostal
- `jostal_interesadas.clienta_id` -> `jostal_clientas.id`
- `jostal_clientas.source_interesada_id` -> `jostal_interesadas.id`
- `jostal_leads.clienta_id` -> `jostal_clientas.id`
- `jostal_ventas` hoy no guarda FK dura, pero funcionalmente es un modulo hermano de `jostal_clientas`

### Comercial
- `comercial_processes.assigned_line_ids[]` -> `telefonos.id`
- `comercial_threads.process_id` -> `comercial_processes.id`
- `comercial_threads.process_slug` -> `comercial_processes.slug`
- `comercial_threads.line_id` -> `telefonos.id`
- `comercial_threads.lead_id` -> `comercial_leads.id`
- `comercial_leads.thread_id` -> `comercial_threads.id`
- `comercial_leads.process_id` -> `comercial_processes.id`
- `comercial_leads.line_id` -> `telefonos.id`
- `comercial_line_state.line_id` -> `telefonos.id`
- `comercial_events.payload.thread_id` -> `comercial_threads.id` cuando el evento ya esta correlacionado
- `comercial_webhook_seen[]` deduplica `message_id`, no referencia entidad propia

### Avisos
- `avisos.last_run_id` -> `avisos_runs.id`
- `avisos.source_key` referencia eventos de negocio derivados de `clientes`, `leads`, `gastos`, `casawasap_*`, `jostal_*`, `bots`, `telefonos`, `anuncios`

### Publicista
- `publicista_jobs.clienta_id` -> `clientes.id` cuando `clienta_scope = lamami`
- `publicista_jobs.clienta_id` -> `jostal_clientas.id` cuando `clienta_scope = jostal`
- `publicista_campaigns.planning_id` -> `publicista_plannings.id`
- `publicista_campaigns.product_ids[]` -> `publicista_jobs.id`
- `publicista_campaigns.account_ids[]` -> `anuncios.id`
- `publicista_campaigns.selected_listing_refs[]` -> compuesto `account_id::listing_id`
- `publicista_campaign_items.campaign_id` -> `publicista_campaigns.id`
- `publicista_campaign_items.account_id` -> `anuncios.id`
- `publicista_campaign_items.phone_id` -> `telefonos.id`
- `publicista_campaign_items.product_job_id` -> `publicista_jobs.id`
- `publicista_tasks.campaign_id` -> `publicista_campaigns.id`
- `publicista_tasks.campaign_item_id` -> `publicista_campaign_items.id`
- `publicista_tasks.account_id` -> `anuncios.id`
- `publicista_runs.campaign_id` -> `publicista_campaigns.id`
- `publicista_plannings.parent_planning_id` -> `publicista_plannings.id`

## Modulos criticos y pesados

### Prioridad 1
`publicista`

Motivos:

- `publicista_campaign_items.json` pesa ~`93.7 MB`
- `publicista_campaigns.json` pesa ~`19.1 MB`
- mezcla entidad canonica con cientos de artefactos JSON/JSONL por job
- operaciones de guardado reescriben arrays completos
- el modulo ya depende de filtrado, snapshots, ejecuciones y automatizaciones

### Prioridad 1
`comercial`

Motivos:

- mezcla `json`, `jsonl` y lockfiles
- el webhook escribe directo y necesita trazabilidad fuerte
- usa colas append-only y luego guarda estados, hilos, leads y salud de lineas
- ya tiene configuracion MySQL embebida en procesos y un primer uso real de PDO en [app/comercial.php](/var/www/html/atupuerta/app/comercial.php#L3136)

### Prioridad 1
`avisos`

Motivos:

- fan-in de casi todos los modulos
- `avisos_runs.json` ya pesa ~`900 KB`
- cualquier inconsistencia en migracion afecta supervision general del CRM

### Prioridad 2
- `clientes`
- `interesadas`
- `bots`
- `telefonos`
- `lamamibot`
- `jostal_*`
- `casawasap_*`

### Prioridad 3
- `voice_*`
- `eurekas`
- plantillas y auxiliares poco voluminosos

## Criterio de modelado decidido

### 1. Mantener ids actuales
No se regeneran ids. Se conservan como `VARCHAR`:

- `cli_*`
- `int_*`
- `bot_*`
- `comthread_*`
- `pubjob_*`
- `pubcamp_*`
- `pubitem_*`

### 2. Columnas utiles + JSON completo
El patron base del esquema queda asi:

- columnas escalares indexables para filtros, joins y paneles
- columna `raw_json` o `payload_json` para preservar el payload entero

Con esto se consigue:

- migracion sin perdida
- joins por ids existentes
- adaptacion gradual de codigo sin reescritura total

### 3. Objetos singleton o mapas se modelan como tablas singleton / clave-valor
Casos especiales:

- `settings.json` -> `crm_settings`
- `lamamibot.json` -> `crm_lamamibot`
- `comercial_settings.json` -> `crm_comercial_settings`
- `comercial_runtime.json` -> `crm_comercial_runtime`
- `comercial_daily_stats.json` -> `crm_comercial_daily_stats` con fila singleton para `_autoregulation`

### 4. JSONL operativos pasan a tablas append-only
- `comercial_events.jsonl` -> `crm_comercial_events`
- `comercial_webhook_log.jsonl` -> `crm_comercial_webhook_logs`
- `comercial_queues/*.jsonl` -> `crm_comercial_queue_items`

### 5. Artefactos pesados de Publicista siguen en disco
En Fase 1 no se meten en MySQL:

- html de debug
- imagenes
- metadatos intermedios de batch
- logs extensos por job
- `free_bump_logs.ndjson`

Para ellos se deja solo `crm_publicista_job_artifacts` como tabla de metadato opcional.

## Modo de trabajo decidido

### `json`
Estado actual:

- todo lee y escribe JSON / JSONL
- sigue siendo la verdad operativa hasta terminar Fase 2

### `dual`
Modo transitorio obligatorio para Fase 3:

- lectura preferente MySQL en modulos ya migrados
- fallback a JSON en modulos no migrados
- escritura en MySQL como camino principal
- comparadores JSON vs MySQL para conteos y relaciones
- posibilidad de re-exportar a JSON mientras dure la convivencia

### `mysql`
Modo final:

- MySQL como unica fuente operativa
- JSON queda como backup historico o export bajo demanda

## Esquema MySQL cerrado
El esquema de Fase 1 queda en:

- [sql/mysql_phase1_schema.sql](/var/www/html/atupuerta/sql/mysql_phase1_schema.sql)

Familias cubiertas:

- `crm_settings`, `crm_users`
- `crm_agenda`, `crm_gastos`, `crm_interesadas`, `crm_clientes`, `crm_leads`
- `crm_telefonos`, `crm_bots`, `crm_lamamibot`, `crm_eurekas`
- `crm_casawasap_contactos`, `crm_casawasap_pagos`
- `crm_jostal_interesadas`, `crm_jostal_clientas`, `crm_jostal_leads`, `crm_jostal_ventas`
- `crm_avisos`, `crm_avisos_runs`
- `crm_comercial_settings`, `crm_comercial_processes`, `crm_comercial_process_lines`, `crm_comercial_runtime`, `crm_comercial_line_state`, `crm_comercial_daily_stats`, `crm_comercial_threads`, `crm_comercial_leads`, `crm_comercial_events`, `crm_comercial_webhook_logs`, `crm_comercial_webhook_seen`, `crm_comercial_queue_items`
- `crm_publicista_accounts`, `crm_publicista_templates`, `crm_publicista_jobs`, `crm_publicista_job_artifacts`, `crm_publicista_plannings`, `crm_publicista_campaigns`, `crm_publicista_campaign_items`, `crm_publicista_tasks`, `crm_publicista_runs`
- `crm_voice_commands_log`, `crm_voice_pending_actions`

## Estrategia de migracion cerrada para Fase 2 y 3

### Paso tecnico
1. Crear conexion MySQL comun reutilizable desde `bootstrap`.
2. Crear helpers DB agnosticos del backend.
3. Adaptar `storage.php` a backend configurable `json / dual / mysql`.
4. Crear tablas e indices del SQL de esta fase.
5. Preparar migradores idempotentes por familia.

### Paso funcional
1. Migrar `publicista`.
2. Migrar `comercial`.
3. Migrar `avisos`.
4. Migrar el resto de modulos.

### Regla de seguridad
- no borrar JSON durante la migracion
- no imponer foreign keys duras en el primer corte
- comparar conteos JSON vs MySQL antes de cada cambio de modo
- tolerar ficheros vacios, JSON invalido recuperable y JSONL con lineas defectuosas

## Resultado cerrado de Fase 1
Queda decidido y alineado con el sistema real:

- mapa completo de datos vivos en `data/`
- distincion entre entidades canonicas y artefactos de filesystem
- relaciones principales y relaciones polimorficas
- modulos prioritarios por peso y por patron de escritura
- esquema MySQL inicial completo y corregido
- modo operativo `json / dual / mysql`
- estrategia de migracion para Fases 2, 3 y 4

Siguiente fase correcta:

- conexion MySQL comun
- helpers DB
- tablas e indices creados
- `storage.php` con backend configurable
