# Plan de implementación: fluidez del inbox comercial móvil

**Diseño:** `docs/superpowers/specs/2026-08-27-comercial-inbox-fluidity-design.md`  
**Objetivo:** mejorar carga, inmediatez, actualización, medios y robustez del inbox comercial sin cambiar su apariencia ni añadir dependencias.

## Guardrails

- Trabajar solo en worktree; no leer, modificar ni versionar `data/`.
- Mantener compatibilidad con Chrome 95 / Android 8.1.
- Mantener el flujo que pausa el bot ante un envío humano.
- No convertir salud WAHA `FAILED` en strike/baneo ni limpiar estados de riesgo automáticamente.
- Cada cambio de JS/CSS actualizará sus versiones de caché en `index.php`.

## 1. Contratos y regresiones primero

**Archivos**
- Crear `tools/test_inbox_api_contracts.php`.
- Crear `tools/test_comercial_media_regressions.php`.
- Extender `tools/test_comercial_ban_classifier.php`.

**Trabajo**
1. Definir fixtures mínimos, sin teléfonos ni textos de producción, para hilo, eventos JSONL, medios WAHA/Evolution y resultado de transporte.
2. Cubrir contrato de historial: `revision`, cursor `before`, `has_more`, tramo reciente y respuesta sin payload si la revisión coincide.
3. Cubrir envío manual: misma clave `client_message_id` devuelve el mismo trabajo/resultado y no invoca el transporte dos veces.
4. Cubrir URL `compartir.site/{codigo}/` a JPG directo, URLs de imagen normales sin modificación y fallback renderizable.
5. Cubrir WAHA `FAILED`/HTTP 422 como fallo de salud, no ban/strike/global slowdown; conservar la cobertura de 401/403 y marcadores de baneo reales.

**Verificar**
```sh
php tools/test_inbox_api_contracts.php
php tools/test_comercial_media_regressions.php
php tools/test_comercial_ban_classifier.php
```

## 2. Separar el arranque de chat y agente

**Archivos**
- `inbox.php`
- `inbox_api.php`
- `app/comercial_agent_table.php`
- `assets/inbox-chat.js`

**Trabajo**
1. En `inbox.php`, calcular y renderizar la tabla de agente solo cuando la vista solicitada sea agente.
2. Exponer una acción específica que devuelva el contenido/datos de agente bajo demanda, reutilizando el renderer actual.
3. Cargar e inicializar el panel agente al entrar por primera vez en esa vista; evitar doble registro de eventos.
4. Corregir la inicialización de `currentView` para que el closure JS use la vista del servidor desde el inicio.
5. Revisar `render_comercial_page()` para que la pestaña iframe de chat no cargue resumen/hilos innecesarios antes de insertar el iframe.

**Verificar**
- `inbox.php?view=chat` no contiene tarjetas de agente ni llama a su renderizador.
- `inbox.php?view=agent` conserva filtros, tarjetas y acciones existentes.
- Cambio chat → agente carga el contenido una vez y mantiene funcionalidad.

## 3. Lectura local, paginada y revisionada del historial

**Archivos**
- `app/comercial.php`
- `inbox_api.php`
- `assets/inbox-chat.js`

**Trabajo**
1. Crear un lector inverso de JSONL que devuelva los últimos N eventos sin cargar el archivo completo; conservar orden cronológico, tolerancia a línea inválida y archivo sin salto final.
2. Crear helpers de revisión/cursor por hilo basados exclusivamente en estado persistido local.
3. Hacer que `action=thread` acepte revisión conocida y cursor `before`; devolver una primera página limitada, `has_more` y solo deltas cuando la revisión no cambie.
4. Retirar de la ruta normal de `thread` el sincronizado remoto nativo. Mantener `comercial_poll_native_replies()` en el tick de fondo y, si se mantiene una sincronización al abrir, limitarla y aplicar timeout corto tanto para WAHA como Evolution.
5. En cliente, cargar mensajes anteriores a demanda, anteponerlos manteniendo posición de scroll y anexar/actualizar solo los mensajes nuevos.

**Verificar**
- Hilo largo: respuesta inicial limitada, carga de anteriores correcta y sin salto visible de scroll.
- Refresco con misma revisión: respuesta sin timeline y sin recrear burbujas.
- Refresco normal: cero llamadas remotas WAHA/Evolution.
- Pruebas del lector: vacío, sin newline final, JSON inválido, límite y orden.

## 4. Cola de envío manual e idempotencia

**Archivos**
- `app/comercial.php`
- `inbox_api.php`
- `assets/inbox-chat.js`

**Trabajo**
1. Añadir almacenamiento duradero de trabajos de envío manual, independiente de datos de producción y protegido por escritura atómica/lock según el patrón existente.
2. Implementar alta, consulta, deduplicación y procesamiento de un trabajo con `client_message_id`.
3. Ejecutar trabajos pendientes desde el tick comercial; conservar pausa de respuesta automática antes de la entrega del mensaje humano.
4. Cambiar el endpoint manual para crear/devolver trabajo en vez de requerir que el navegador espere a la API remota.
5. En navegador, generar clave por pulsación, insertar burbuja `enviando`, encolar envíos en orden, deshabilitar solo duplicados exactos y reconciliar `enviado`/`fallido` desde el historial.
6. Reintentar con la misma clave; mostrar estado accionable de fallo sin duplicar WhatsApps.

**Verificar**
- Dos mensajes distintos seguidos aparecen al instante y conservan orden.
- Doble pulsación o reintento de una misma clave produce un único envío remoto/evento.
- Recarga durante `enviando` recupera el estado correcto.
- Fallo de red/transportista queda visible y reintentable.

## 5. Coordinador de polling móvil

**Archivos**
- `assets/inbox-chat.js`
- `inbox_api.php`

**Trabajo**
1. Integrar `pending` y su revisión en la respuesta ya usada para líneas, eliminando el poll de badge en vista chat.
2. Sustituir los intervalos independientes por un coordinador con una petición en vuelo por recurso.
3. Pausar con `document.hidden`, refrescar al volver visible y aplicar backoff exponencial con máximo tras errores.
4. Usar guardia de generación y cancelación cuando el navegador lo soporte, manteniendo fallback compatible con Chrome 95.
5. Asegurar que un full-chat abierto desde la vista agente recibe actualización igual que desde chat.

**Verificar**
- Chat visible: no hay `pending_count` independiente.
- Red lenta: nunca hay dos peticiones del mismo recurso simultáneas.
- Error repetido: crece la espera hasta el límite; primer éxito devuelve cadencia normal.
- Hilo abierto desde agente recibe entrantes sin recarga manual.

## 6. Medios WAHA/Evolution y estado de línea

**Archivos**
- `assets/inbox-chat.js`
- `app/comercial.php`
- `inbox_api.php`
- `tools/test_comercial_media_regressions.php`
- `tools/test_comercial_ban_classifier.php`

**Trabajo**
1. Añadir helper puro de URL directa para shortlinks `compartir.site`, usado solo como `src`; preservar el shortlink como enlace.
2. Añadir fallback de error: ocultar imagen fallida y presentar el enlace original seguro.
3. Seleccionar la estrategia de carga por origen: proxy para MinIO/Evolution autorizado, render directo para URL pública WAHA permitida, nunca proxy para host externo arbitrario.
4. Persistir y serializar para Evolution `instance`, identificador de mensaje, MIME, tipo, nombre, caption y URL cuando estén disponibles.
5. Revisar clasificación de salud para que `FAILED` de WAHA deje diagnóstico de transporte y no active semántica de baneo; probar expresamente el caso que afecta a `631454098` con fixture anonimizado.

**Verificar**
- Las imágenes con shortlink cargan su JPG directo y conservan destino original.
- URL normal de `.jpg/.png/.webp` no cambia.
- Error de imagen deja un enlace usable, no icono roto.
- Proxy mantiene rechazo de hosts no autorizados.
- `FAILED` queda como `health_status=down`, sin ban marker ni slowdown global.

## 7. Coste de renderizado en móvil y caché

**Archivos**
- `assets/inbox-chat.js`
- `assets/inbox-chat.css`
- `index.php`

**Trabajo**
1. Aplicar actualizaciones puntuales de líneas, hilos y burbujas; evitar `innerHTML` del timeline completo ante un único cambio.
2. Sustituir animaciones que repintan sombras y efectos blur no esenciales por estados estáticos o propiedades `opacity`/`transform`.
3. Aplicar `contain`/`content-visibility` solo a bloques largos que no rompan mediciones, scroll ni accesibilidad en Chrome 95.
4. Actualizar los query strings de caché exigidos en `index.php` para cualquier JS/CSS modificado.

**Verificar**
- Perfil con CPU 4× más lenta: sin tareas repetidas superiores a 50 ms en reposo.
- DOM y heap se estabilizan tras diez minutos con chat abierto.
- Diseño, navegación, controles táctiles y lectura siguen iguales.

## Orden de commits propuesto

1. `test: cubrir contratos de inbox y medios comerciales`
2. `feat: cargar panel agente bajo demanda en inbox`
3. `feat: paginar historial comercial por revision`
4. `feat: encolar envios manuales idempotentes`
5. `feat: coordinar polling del inbox comercial`
6. `fix: mostrar medios WAHA y separar salud de strikes`
7. `perf: reducir renderizado movil del inbox`

## Gate final

```sh
php -l inbox.php
php -l inbox_api.php
php -l app/comercial.php
php -l app/comercial_agent_table.php
php tools/test_comercial_ban_classifier.php
php tools/test_comercial_media_regressions.php
php tools/test_inbox_api_contracts.php
```

Además, realizar prueba manual en Android/Chrome 95 o emulación equivalente: apertura desde CRM iframe y PWA, conversación con historial largo, entrante, dos envíos consecutivos, offline/recuperación, imágenes WAHA y navegación chat/agente.
