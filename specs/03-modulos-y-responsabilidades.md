# 03 · Módulos y responsabilidades

## Estado actual (observado en código)

## Núcleo de entrada y ciclo HTTP

### `index.php`
- Responsable de:
  - resolver `page`
  - control de sesión/autenticación base
  - ejecutar `handle_post_actions()` para POST
  - delegar renderizado final por página
- Límite: no contener lógica de negocio detallada.

### `app/bootstrap.php`
- Responsable de:
  - inicialización runtime (errores, sesión, include de módulos)
  - preparación de almacenamiento (`bootstrap_storage()`)
- Límite: no implementar reglas de dominio, solo wiring.

## Módulos de infraestructura

### `app/storage.php`
- Responsable de persistencia abstracta (json/dual/mysql), cache de lectura y utilidades de acceso.
- Límite: no decidir reglas de negocio de entidades.

### `app/db.php`
- Responsable de conexión y helpers SQL básicos (PDO, query, metadatos de tabla).
- Límite: no mezclar rendering ni decisiones de flujo UI.

### `app/helpers.php`
- Responsable de utilidades transversales (request helpers, escape, fechas, redirects, etc.).

## Módulos de dominio/negocio

### `app/actions.php`
- Dispatcher central de acciones POST (`action=...`).
- Límite: enrutar y coordinar; no crecer con lógica duplicada no encapsulada.

### `app/views.php`
- Render UI de páginas generales: dashboard, lamami, publicista (parte UI), josue, casawasap, jostal, informes, gastos, avisos, login.
- Límite: evitar cálculos de negocio pesados en plantillas.

### `app/comercial.php`
- Módulo vertical Comercial:
  - configuración, procesos, ejecución de ticks, hilos/conversaciones, leads, logs, colas JSONL.
  - también render de la página Comercial y tabs.
- Límite actual difuso: mezcla dominio + infraestructura + vista en un único archivo.

### `app/publicista.php`
- Módulo vertical Publicista:
  - cuentas/anuncios, plannings, campañas, tasks/runs, automatizaciones y procesos de batch.

### `app/avisos.php`
- Gestión de avisos planificados/activos/histórico y operaciones relacionadas.

### `app/auth.php`
- Login, sesión, auto-login por whitelist y utilidades de estado de autenticación.

### `app/voice.php`
- Contratos de voz/acciones pendientes y respuestas JSON de comandos de voz.

### `app/bot_templates.php`
- Plantillas de texto y utilidades de contenido para bots.

## Límites funcionales recomendados para futuros cambios

## Recomendación (no implementada)
- Mantener la regla: **UI en `views.php`/renderers, negocio en módulo vertical, persistencia en `storage.php`**.
- Evitar añadir nuevas reglas de negocio directamente en `index.php` o en bloques HTML.
- Si una acción POST afecta a más de un módulo, documentar explícitamente el flujo en la spec del cambio.
