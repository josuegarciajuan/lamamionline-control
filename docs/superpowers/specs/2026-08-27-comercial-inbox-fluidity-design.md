# Diseño: fluidez del inbox comercial móvil

**Fecha:** 2026-08-27  
**Estado:** aprobado para especificación; pendiente de revisión del documento y planificación de implementación.

## Objetivo

Hacer que el inbox comercial (`inbox.php`), incluido al abrirse desde el iframe del CRM y como PWA Android, responda de forma inmediata en móviles antiguos sin cambiar su diseño visual. Debe mostrar mensajes manuales al instante, actualizar conversaciones de forma fiable, abrir rápido y representar correctamente medios de WAHA y Evolution.

## Problemas confirmados

- La vista chat carga y genera también la vista de agente completa, aunque permanezca oculta.
- El CRM prepara datos del comercial antes de cargar un iframe que vuelve a cargarlos.
- La conversación puede disparar tres consultas cada cinco segundos y permite que se solapen.
- Cada actualización visual de hilo puede hacer una consulta remota a WAHA o Evolution; WAHA puede esperar hasta dos segundos y Evolution usa un límite y timeout mayores.
- El historial lee el registro completo de eventos para devolver solo su tramo reciente y vuelve a reconstruir el DOM completo cuando cambia.
- El envío manual espera el resultado remoto antes de insertar el mensaje y no tiene cola ni idempotencia.
- Los enlaces WAHA de `compartir.site/{codigo}/` se usan como `img src`, aunque devuelven HTML. La imagen binaria está en `compartir.site/{codigo}/{codigo}.jpg`.
- Medios externos WAHA se dirigen indebidamente al proxy diseñado para MinIO; Evolution no conserva todos los metadatos necesarios para su recuperación bajo demanda.
- En la línea `631454098`, el bloqueo operativo observado es una sesión WAHA `FAILED` y salud `down`. La advertencia de fallos tiene el cooldown vencido; no se tratará como un strike/baneo activo de WhatsApp.

## Enfoque elegido

Aplicar mejoras incrementales y compatibles con Chrome 95 / Android 8.1. Se conserva el aspecto de la interfaz y los registros actuales como fuente de verdad. No se introducen dependencias, WebSockets ni cambios de infraestructura.

## Diseño funcional

### Carga inicial y navegación

`inbox.php?view=chat` renderizará únicamente el chat. La tabla de agente se solicitará y montará solo al entrar en `view=agent`. El tab de chat del CRM omitirá los cálculos previos que el iframe no necesita. Con ello se eliminan procesamiento PHP y DOM oculto de la ruta crítica.

### Actualización de datos

Un coordinador único, visible solo cuando la pestaña está activa, sustituirá los intervalos independientes. Cada recurso tendrá una única petición en vuelo; los errores aplicarán backoff exponencial con límite y el éxito recuperará la cadencia habitual. La respuesta de líneas incorporará el contador pendiente, eliminando la petición de badge duplicada.

El refresco visual de hilo utilizará una revisión local. Si no cambió, no devolverá ni re-renderizará el historial. La sincronización remota WAHA/Evolution dejará de ejecutarse dentro de cada refresco visual: se conservará en el tick de fondo existente y podrá solicitarse de manera acotada al abrir un hilo. Los timeouts y límites de esa consulta serán estrictos.

### Historial y DOM

El backend leerá el final del JSONL hasta reunir el número requerido de eventos, tolerando líneas dañadas y conservando el orden actual. El cliente cargará un tramo reciente limitado y permitirá pedir mensajes anteriores mediante cursor. En cambios posteriores anexará o actualizará únicamente mensajes nuevos, manteniendo el scroll, en vez de sustituir todo el HTML.

### Envíos manuales

Al enviar se insertará una burbuja local con estado `enviando`. Cada acción tendrá una clave de idempotencia generada en cliente y una cola persistente de backend. La misma clave nunca puede provocar dos envíos de WhatsApp. La cola informará de `enviando`, `enviado` o `fallido`, permitirá reintentar de manera segura y reconciliará el estado tras recarga o navegación.

El flujo actual que pausa la respuesta automática antes de una intervención humana se conserva.

### Multimedia

El renderer transformará exclusivamente la URL usada por la etiqueta imagen de los shortlinks `compartir.site`; el enlace original seguirá disponible para abrir/compartir. Cuando una imagen no cargue, se ocultará el icono roto y se mostrará un enlace seguro al recurso original.

Se conservará una estrategia explícita de origen: URLs MinIO/Evolution autorizadas usan el proxy existente; URLs públicas WAHA permitidas se muestran directamente; una URL arbitraria no se proxyará. Los eventos Evolution conservarán identificador de mensaje, instancia, tipo, MIME, nombre y caption para usar la recuperación autenticada ya existente cuando haga falta.

### Salud de líneas

Los estados de salud del transporte y los strikes/baneos se representan por separado. `FAILED` de WAHA excluye una línea por indisponibilidad técnica y acumula diagnóstico, pero no crea una marca de baneo ni activa la ralentización global. El restablecimiento de salud no borrará automáticamente evidencia de fallos ni una sanción real; la reactivación seguirá siendo explícita.

### Rendimiento móvil

Se reducirán repintados continuos de sombras, blur y reemplazos masivos de nodos, usando solo efectos compatibles con el presupuesto del dispositivo. No cambia la jerarquía ni el estilo funcional de la interfaz.

## Contratos y compatibilidad

- Las respuestas de historial añadirán `revision`, cursor `before`, `has_more` y mensajes recientes; durante la migración conservarán los campos que usa el cliente actual.
- El endpoint de envío aceptará `client_message_id` y devolverá el estado persistente de la cola.
- No se modificará el proxy para aceptar hosts externos sin una política explícita.
- No se modificarán datos de producción ni conversaciones como parte de las pruebas.

## Validación y métricas de aceptación

1. Chat inicial: no se calcula ni emite la tabla de agente.
2. Chat visible: no hay `pending_count` duplicado y no hay solicitudes solapadas por recurso.
3. Refresco normal: no genera llamadas remotas a WAHA/Evolution; una conversación abierta actualiza usando revisiones locales.
4. Dos mensajes enviados seguidos aparecen instantáneamente y una repetición con la misma clave genera un solo envío remoto.
5. Historial largo: solo se leen los eventos finales necesarios, los mensajes antiguos se paginan y el scroll se conserva.
6. Conversación WAHA afectada: las imágenes `compartir.site` muestran la URL JPG directa o un enlace funcional, nunca un icono roto sin alternativa.
7. Línea `631454098`: `FAILED` se registra como salud del transporte, no como strike/baneo.
8. En Chrome 95 con CPU/red limitada: no se observan tareas largas recurrentes ni crecimiento continuo de DOM/memoria en reposo.

## Pruebas requeridas

- PHP: contratos del inbox, paginación/revisión, idempotencia de envío y estados de cola.
- PHP: clasificación WAHA `FAILED` frente a ban y extracción de medios WAHA/Evolution.
- Renderer: shortlink a JPG, fallback de error y conservación de URLs directas válidas.
- Navegador/manual: carga de chat, navegación a agente, offline/backoff, dos envíos consecutivos, entrantes, historial largo y Android Chrome 95.
- Lint PHP de los archivos tocados y los tests específicos del comercial.
