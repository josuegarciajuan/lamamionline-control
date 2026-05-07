LAMAMI CRM - PHP 7.4 + JSON

ACCESO
Usuario: nuria
Contraseña: josue

FLUJO
Interesada -> Atendida -> Convertida -> Clienta

REGLAS
- No se pueden crear clientas directamente
- Las clientas solo se crean desde interesadas
- Los leads se registran en la ficha de la clienta
- Solo se pueden registrar leads si la clienta tiene bot vinculado
- Cada lead guarda también el bot histórico de ese momento
- Las clientas pueden darse de baja

SECCIONES
- Dashboard
- Interesadas
- Clientas
- Bots
- Informes

NOTA
La carpeta /data debe tener permisos de escritura

* * * * * /usr/bin/php /var/www/html/atupuerta/control/cron_avisos.php >/dev/null 2>&1



SISTEMA DE AVISOS
- Aviso de prueba cuando son más de las 10:00
- Aviso cuando los ingresos del mes superan múltiplos de 1000€
- Aviso cuando el beneficio real del mes supera múltiplos de 500€
- Aviso cuando se crea una nueva clienta en LaMami
- Aviso cuando se añade un lead a clienta en LaMami
- Aviso cuando pasan más de 24h sin ingresos en ninguna rama
- Aviso cuando se crea una nueva clienta en Jostal
- Aviso cuando se crea un lead en Jostal
- Aviso cuando se crea una venta en Jostal
- Aviso cuando se da de alta un cliente en Casawasap
- Aviso cuando se registra un pago/ingreso en Casawasap
- Aviso semanal de cobro en Casawasap para clientes en periodicidad de cobro
- Aviso semanal de renovación de publicidad para clientas normales de LaMami
- Aviso cuando una interesada lleva más de 2 días sin convertirse en cliente en cualquiera de las 3 ramas
- Aviso "empieza el beneficio" cuando los ingresos del mes superan la media de gastos de los 3 meses anteriores
- Aviso cuando una interesada nueva de cualquier rama lleva más de 6 horas sin atender/converterse/descartarse
- Aviso cuando una interesada atendida de LaMami lleva más de 24h sin convertirse
- Aviso cuando pasan más de 48h sin ingresos en ninguna rama
- Aviso cuando entra el primer ingreso del día
- Aviso cuando el día anterior terminó sin ningún movimiento
- Aviso cuando hoy vence la renovación semanal de publicidad de una clienta de LaMami
- Aviso cuando hoy vence el cobro semanal de un cliente Casawasap en modo alquiler
- Aviso cuando una clienta activa de LaMami no tiene bot vinculado
- Aviso cuando un bot sigue vinculado a una clienta de baja
- Aviso cuando un bot no tiene accesible su archivo de memoria
- Aviso cuando una clienta activa de LaMami lleva más de 7 días sin generar ningún lead
- Aviso cuando una clienta de Jostal está en casa y lleva más de 7 días sin ingresos desde su última entrada
- Aviso cuando un cliente de Casawasap lleva más de 7 días de alta sin registrar pagos
- Aviso cuando la renovación semanal de publicidad de LaMami ya va con al menos 1 semana adicional de retraso
- Aviso cuando el cobro semanal de Casawasap en modo alquiler ya va con al menos 1 semana adicional de retraso
- Aviso cuando hoy vencen varias renovaciones/cobros a la vez
- Aviso cuando un cliente Casawasap en modo alquiler no tiene fecha base cliente_at
- Aviso cuando una clienta de Jostal tiene varios periodos de estancia abiertos al mismo tiempo
- Aviso cuando una clienta de Jostal tiene un periodo con salida anterior a entrada
- Aviso cuando un teléfono referencia un anuncio inexistente
- Aviso de récord histórico de ingresos diarios
- Aviso de récord histórico de beneficio diario
- Aviso de récord histórico mensual de ingresos
- Aviso cuando cambia la rama líder del mes
- Aviso cuando una sola rama concentra al menos el 70% de los ingresos del mes
- Aviso cuando la proyección del mes actual cae claramente por debajo del cierre del mes anterior
- Aviso cuando el beneficio entra en una tendencia negativa de 3 días
- Aviso cuando se registra un gasto alto (>= 200€)
- Aviso cuando hay demasiados avisos activos acumulados
- Aviso cuando un bot no tiene clienta vinculada
- Aviso cuando un anuncio está incompleto


MOTOR DE AVISOS
- Los avisos activos no se duplican mientras sigan existiendo
- Si un aviso se descarta y la condición sigue cumpliéndose, vuelve a activarse y vuelve a mandar WhatsApp
- Si la condición deja de cumplirse y el aviso es auto-resoluble, el aviso se resuelve solo
- Los avisos nuevos se marcan como leídos la primera vez que se cargan en pantalla
- El cron de avisos es:
  php /var/www/html/atupuerta/control/cron_avisos.php



CONFIGURACIÓN DE AVISOS
Los umbrales y variables parametrizables del motor de avisos se configuran en:
- avisos_config.php

Desde ese archivo se pueden ajustar:
- saltos de hitos de ingresos y beneficio
- horas de inactividad
- horas/días límite para interesadas no trabajadas
- días máximos sin leads/pagos/ingresos
- frecuencia semanal de cobros recurrentes
- semanas extra para considerar retrasos
- mínimo de renovaciones simultáneas para lanzar aviso agregado
- porcentaje de concentración por rama
- mínimos para proyección mensual
- longitud de tendencia negativa
- umbral de gasto alto
- cantidad de avisos activos a partir de la cual se considera ruido




Vamos con algunos cambios:

-el aviso ya son mas de las 10 quitarlo

-en el dasboard hay:
Ingresos · 03/2026
936,00 €
Gastos · 03/2026
736,00 €
En informes mismas cantidades. Pero en el nuevo grid mensual: 
Ingresos del mes
1.016,00 €
Gastos del mes
816,00 €
Revisa cual parte es la incorrecta y solventalo.

-avisos manuales: crear nueva sección avisos.
Se podrán crear avisos manuales para que se activen en una fecha y hora determinada. TEndrán texto y titulo. 
Habrá un listado de los avisos planificados. 
Estos avisos se activarán con el motor de avisos existentes, se podrán descartar cuando aparezcan y todo lo demás que tiene este sistema.
Estos avisos del futuro se podrán borrar.
En el el mismo listado de avisos, se podrńa ver con varios botones:
·Los planificados (serán estos nuevos)
·Los activos, serán los que están ahora en pantalla que se estánmostrando mediante el motor, esperando a ser descartados. Aparecerán todos, si han vemido de este nuevo sistema, o del anterior, se tratarñan los 2 conceptos copmo avisos.
·Los descartados. Un historico de todos los avisos que han habido. Igual que antes, semostrarán los de los 2 conceptos.

Como siempre te paso el zip con el estado actual del proyecto y la data actualizada. Devolverás los cambios que hay que hacer para conseguir estas nuevas funcionalidades de forma detalla, lo más clara posible, devolviendo el código que hay que añadir, sustituir.



-Número mensajes enviados



Vamos con algunos cambios:

-Listado de bots, la columna clienta actual, que sea la 2º, es decir a la derecha de la del bot.
-El estado no funciona bien, he probado a apagar bots y sigue mostrando encendido tras recargar la página.

Como siempre te paso el zip con el estado actual del proyecto y la data actualizada. Devolverás los cambios que hay que hacer para conseguir estas nuevas funcionalidades de forma detalla, lo más clara posible, devolviendo el código que hay que añadir, sustituir.




Vamos con algunos cambios:
-aviso recurrente a 2 veces al dia, cada 12horas, de subir publicidad destacamos. SE podrna elegir las horas en config. Por defecto 00:01 y 12:01 
-aviso recurrente de subir publicidad de mundo sex, cada X horas y desde hora H a hora W del dia. SErá configurable
-en subseccion josue anuncios los botones copiar no funcionan además añadir retro alimentacion de que se ha copiado
Como siempre te paso el zip con el estado actual del proyecto y la data actualizada. Devolverás los cambios que hay que hacer para conseguir estas nuevas funcionalidades de forma detalla, lo más clara posible, devolviendo el código que hay que añadir, sustituir.


Vamos con algunos cambios:

Mejorar el formato de los avisos.
ahora se envian demasiados campos. Los unicos importantes son aviso y detalle. Se enviaran escritos el titulo en mayusulas y luego el detalle. Justo antes del titulo con un emoticonmo qe signifique la urgencia. Dependiendo de la urgencia pondras uno u otro. No hace falta poner la cabcera de la mami crm. 

Como siempre te paso el zip con el estado actual del proyecto y la data actualizada. Devolverás los cambios que hay que hacer para conseguir estas nuevas funcionalidades de forma detalla, lo más clara posible, devolviendo el código que hay que añadir, sustituir.


















Vale para aclarar todo, a ver que te parece. Ajora tenemos planificadas estas 3 fases para aboradar todos los cambios. En cada una de llas te pasaria el zip del proyecto CRM donde se crea el bot.


Estamos en LamamiBot — FASE 1.

Objetivo:
- crear/terminar la entidad propia LamamiBot
- guardar nombre, estado, líneas vinculadas y clientas vinculadas
- sincronizar girlsconf_lamamidef/data/girls.json
- no generar todavía el bot final si no hace falta
- dar feedback claro de altas/bajas/actualizaciones/desactivaciones de clientas

Reglas cerradas:
- LamamiBot es único
- la sección Bots no se toca
- si una clienta se añade al bot y no existe en girlsconf_lamamidef, se crea como desactiva
- si una clienta se quita del bot y estaba activa, se pone en desactiva
- no añadir pending_regeneration
- no tocar todavía el panel girlsconf PHP salvo necesidad absoluta
- descripcion_corta sigue siendo descripcion_corta, no servicios

Te paso el ZIP con el estado actual del proyecto.
Devuélveme:
1) análisis exacto de lo que hay que tocar
2) archivos a modificar
3) código completo a añadir/sustituir por archivo
4) orden de aplicación
5) comprobaciones manuales








Estamos en LamamiBot — FASE 2.

Objetivo:
- generar el JSON real del bot reutilizando la plantilla actual de “Texto1 · JSON del bot”
- adaptar el generador a LamamiBot
- usar líneas dinámicas desde la selección de teléfonos
- usar girlsconf_lamamidef como girls config fija
- regenerar automáticamente el bot cuando cambien líneas o clientas
- mostrar feedback y pintar el JSON generado en pantalla

Reglas cerradas:
- LamamiBot es único
- speaker_girl y selected_girl en LamamiBot acabarán siendo lo mismo
- habrá memoria mixta genérica al inicio
- al resolver identidad, el workflow debe poder saltar a la memoria de la chica
- revisar y eliminar hardcodes ocultos de la plantilla

Te paso el ZIP con el estado actual del proyecto.
Devuélveme:
1) análisis exacto
2) lista de hardcodes/localizaciones detectadas
3) archivos a modificar
4) código completo a añadir/sustituir por archivo
5) orden de aplicación
6) validaciones manuales





Estamos en LamamiBot — FASE 3.

Objetivo:
- modificar el runtime del workflow y el prompt de IA
- si identity_resolved=false, no debe parecer telefonista ni encargada ni centralita
- debe hablar en primera persona como chica de forma natural
- si preguntan por datos propios de una chica sin estar resuelta, debe reconducir con frases naturales
- cuando ya sabe por quién preguntan, debe cargar datos reales de esa chica y seguir como ella
- en LamamiBot speaker_girl == selected_girl
- añadir/usear identity_resolved true/false

Reglas cerradas:
- fallback genérico: sitio discreto, cama grande, buen ambiente, etc.
- no ofrecer más amigas una vez resuelta la chica
- usar de la chica: zona, servicios, tarifas, ubicacion_maps y ficheros de memoria

Te paso el ZIP con el estado actual del proyecto.
Devuélveme:
1) análisis exacto
2) cambios de lógica del workflow
3) cambios del prompt
4) archivos a modificar
5) código completo a añadir/sustituir por archivo
6) validaciones manuales

Pero añadiremos una fase 0, que serña modificacion del panel girls config y añadiremos los cambios necesarios. Te pasaré el estadao actual del panel. Te parece correcto esta forma de aboradar el problema quieres cambiar algo? que en tu respuesta quede pues mjy claro tofdas las fases para el abordo 




Vamos a mejorar la sección:

-Los campos nombre del bot y estado en texto no tienen sentido, ya se sabe que ese será el nombre 
-Poner el contenidoi de la ficha Estado runtime en la parte superior, es importante saber si esta apgado o encendido y poder apagar encender.
De hecho mejora el funcionamiento, que solo haya un boton en forma de interrutor y se está apagado pondrá encedner y viceversa.
-Los textos 2,3,4,5 no se generan. El cuadro estña vacio. 
-El texto4 no es necesario, poner en la cabecera directamente un boton con enlace al panel
-Elpanel 5 sí necesitaŕe los enlaces aunque ya estén los botones en la cabecera, para configurarmelos en el movil. Mantener este texto.
-El boton regenerar pack del bot eliminarlo, que se regenere cada vez que se le de al boton guardar.
-Rehacer todo el diseño de la ficha hacerlo lo más atractivo funcional posible, es el bot nucleo del CRM.

Te paso el ZIP con el estado actual del proyecto.
Devolverás los cambios a realizar de la forna nás detallada posible, pasando el códio que añadir o modificar.





sshpass -p 'P2R6dABhDnta' scp -r /var/www/html/girlsconf/* admin@92.113.151.136:/var/www/html/wasapbot/landing/girlsconf_lamamidef




Ahora al cargar el index da erro 500. Debe haber un error de sintaxis. REvisa a ver donde me he equivocado. Debe estar por los últimos cambios hecho en el proyecto, antes de ellos, sí iba.

Te paso el ZIP con el estado actual del proyecto
Devolverás los cambios a realizar de la forna nás detallada posible, pasando el códio que añadir o modificar.


Ya funciona, pero al darle al boton guardar y regenerar, da este error: 
No se pudo crear la carpeta para el mode file: /data
Que fichero es ese? donde lo intenta crear? por revisar y darle permisos, o crearlo a mano si es uno puntual para el inicio del funcionamientl, si es uno por cada chica por ejemplo, no es funcional hacerlo mano.

Te paso el ZIP con el estado actual del proyecto
REvisa pues, y devolverás los cambios a realizar de la forna nás detallada posible, pasando el códio que añadir o modificar.




Vamos a rehacer los menús. 
-Crear una sección nueva llamada LaMami, que dentro tendrá como subsecciones las secciones actuales:
Interesadas, Clientas, LamamiBot
Te paso el ZIP con el estado actual del proyecto
Devolverás los cambios a realizar de la forna nás detallada posible, pasando el códio que añadir o modificar.




-En la versión movil en lamamibot, se ve en la cabecera entre encendido ruta prinipal /srv... y el boton abrir panel chicas, queda un hueco vacío bastante grande. 
-En la mamaibot sigue apareciendo este error: No se pudo crear la carpeta para el mode file: /data
-En la seccion bots, al intentar encender/apagar cualquiera de ellos, da este error:
No se pudo cambiar el estado runtime del bot. No se pudo escribir el mode file: /srv/n8n_data/.bot_mode_srv3
Sin embargo desde el de la mami, que modifica un archivo del misom directorio, no da este error. Ya me parece que es algod e permisos, por que el archivo de lamamai no se ha hecho nada con él, en el sentido que este archivo se crea desde n8n con el texto2 del bot que es elmode switcher. AL crearse desde ahí, se le asigna otro dueño y otros permisos. Entonces, será posible en realidad cambiar el estado de los bots? 
Solventaremos definoitivamente el error de /data de la mamaibot?
Te paso el ZIP con el estado actual del proyecto
Devolverás los cambios a realizar de la forna nás detallada posible, pasando el códio que añadir o modificar.















Prompt de la Fase 1

Pégalo tal cual:

Vamos a implementar la Fase 1 del sistema de órdenes por voz para este CRM, sobre el ZIP actual que te adjunto.

OBJETIVO DE ESTA FASE:
Dejar montada la base completa del sistema, pero sin meternos todavía en la ejecución avanzada final.

QUIERO QUE IMPLEMENTES O DEJES PREPARADO:
1) Capa de voz en navegador usando SpeechRecognition / webkitSpeechRecognition.
2) Botón de micrófono visible en desktop y móvil.
3) UI mínima pero funcional para:
   - iniciar escucha
   - mostrar texto transcrito
   - mostrar estado: escuchando / procesando / error
   - permitir también escribir el comando manualmente
4) Envío por fetch al backend del comando en texto.
5) Nueva acción backend tipo voice_command.
6) Nuevo módulo central, por ejemplo app/voice.php, o la estructura equivalente más limpia según el proyecto.
7) Contrato JSON fijo de respuesta del sistema, preparado para estos estados:
   - interpreted
   - resolved
   - executed
   - needs_confirmation
   - needs_clarification
   - error
8) Catálogo AMPLIO de intents ya definido en constantes o arrays, aunque todavía algunos queden solo preparados.
9) Estructura interna clara para el pipeline:
   - interpret
   - resolve
   - validate
   - execute
10) Todo debe quedar preparado para en la siguiente fase añadir la IA real sin tener que rehacer la arquitectura.

IMPORTANTE:
- Usa reconocimiento de voz del navegador, no subida de audio.
- No quiero una solución improvisada; debe quedar bien integrada en este proyecto.
- No rompas el funcionamiento actual del CRM.
- Si hay que tocar index.php, app/actions.php, app/views.php, assets/app.js, assets/style.css o crear app/voice.php, hazlo.
- Si detectas que conviene extraer helpers reutilizables, propónlo y dame el código.
- Mantén preparado el sistema para consultas avanzadas futuras.
- La salida backend debe ser segura y estructurada, no texto libre.
- No implementes aún confirmaciones complejas ni logs persistentes si prefieres dejarlos para la Fase 3, pero deja la arquitectura preparada.

QUIERO QUE ME DEVUELVAS:
- análisis breve de encaje en el proyecto actual
- listado exacto de archivos a crear/modificar
- código exacto a añadir o sustituir, fichero por fichero
- explicación clara de dónde va cada bloque
- si hay cambios JS o CSS, completos
- si hay HTML/PHP de UI, completo
- procurando que todo quede copy-pasteable
- revisando que no haya errores de sintaxis

No pases todavía a la Fase 2 ni a la Fase 3.
Prompt de la Fase 2

Cuando tengas aplicada la Fase 1, pegas este:

Vamos a implementar la Fase 2 del sistema de órdenes por voz sobre el estado actual del proyecto que te adjunto.

OBJETIVO DE ESTA FASE:
Conectar la interpretación con IA y hacer que el sistema ya sirva de verdad para navegar, filtrar y ejecutar varias acciones útiles del CRM.

QUIERO QUE IMPLEMENTES:
1) Interpretación con IA del texto transcrito, con salida SOLO en JSON estricto.
2) El prompt de sistema debe limitarse a un catálogo cerrado de intents y parámetros permitidos.
3) La IA no debe ejecutar nada directamente; solo interpretar.
4) Añadir contexto de página actual al comando:
   - page
   - tab
   - edit
   - filtros activos
   - fechas activas si las hay
5) Resolver entidades reales del CRM:
   - clientas
   - interesadas
   - contactos de Casawasap
   - agenda
   - bots si aplica
6) Implementar ejecución real para estas órdenes desde el primer día:
   - abrir páginas principales
   - abrir tabs/subsecciones
   - abrir informes
   - filtrar informes por clienta, rama, tipo o periodo
   - mostrar estadísticas de una clienta
   - abrir agenda
   - abrir LamamiBot
   - abrir bots
   - añadir contacto en agenda
   - añadir interesado/contacto en Casawasap
   - añadir gasto
   - encender/apagar LamamiBot o runtime equivalente si encaja bien
7) Si una orden es ambigua, en vez de ejecutar debe devolver needs_clarification.
8) Si faltan datos esenciales, también debe pedir aclaración.
9) Todo debe validar backend antes de tocar datos.

IMPORTANTE:
- Mantén el catálogo de intents amplio, aunque no todos ejecuten todavía.
- Quiero que el sistema ya entienda lenguaje natural, no solo frases exactas.
- Mantén la arquitectura preparada para consultas avanzadas futuras.
- No metas todavía una capa pesada de confirmaciones persistentes si es mejor dejarla para la Fase 3.
- No quiero que la IA escriba directamente sobre JSON del CRM ni que haga nada fuera de las funciones controladas del backend.

QUIERO QUE ME DEVUELVAS:
- qué archivos exactos cambian respecto a la Fase 1
- código exacto a añadir o sustituir
- prompt exacto de interpretación IA
- estructura JSON exacta esperada de la IA
- resolvedores de entidades y lógica de coincidencia
- ejecución real de las órdenes indicadas
- explicación clara de cómo queda funcionando
- revisión para evitar errores de sintaxis y regresiones

No pases todavía a la Fase 3.
Prompt de la Fase 3

Cuando tengas aplicada la Fase 2, pegas este:

Vamos a implementar la Fase 3 del sistema de órdenes por voz sobre el estado actual del proyecto que te adjunto.

OBJETIVO DE ESTA FASE:
Blindar el sistema, mejorar UX y dejar preparada la expansión a consultas avanzadas.

QUIERO QUE IMPLEMENTES:
1) Confirmaciones para acciones sensibles:
   - bajas
   - borrados
   - cambios de estado importantes
   - cambios runtime delicados
   - cualquier otra acción que veas sensible
2) Sistema de pending action con token temporal o estructura equivalente limpia.
3) Aclaraciones guiadas cuando haya:
   - varios nombres parecidos
   - ninguna coincidencia clara
   - datos incompletos
   - periodos ambiguos
4) Log persistente de órdenes por voz:
   - texto transcrito
   - intent
   - params
   - resultado
   - si hubo aclaración o confirmación
   - timestamp
5) Mejoras UX del panel de voz:
   - mensajes claros
   - errores claros
   - confirmación visual de lo que se va a hacer
6) Dejar implementada una primera base de consultas avanzadas de solo lectura, por ejemplo:
   - comparar ramas
   - resumen de ingresos/gastos de periodo
   - mejor clienta del periodo
   - lectura analítica simple
7) Mantener todo preparado para crecer luego hacia consultas más complejas.

APLICA DESDE ESTA FASE LAS PROTECCIONES:
- ambigüedad de nombres -> aclaración obligatoria
- acciones peligrosas -> confirmación obligatoria
- transcripción imperfecta -> revisión visible
- IA demasiado libre -> JSON cerrado + validación backend
- trazabilidad -> logs

IMPORTANTE:
- No romper lo ya implementado en fases previas.
- Mantener el sistema ordenado y modular.
- Si hace falta crear un fichero de logs o pending actions en data/, hazlo.
- Las consultas avanzadas de esta fase deben ser de solo lectura.

QUIERO QUE ME DEVUELVAS:
- listado exacto de archivos a tocar
- código exacto fichero por fichero
- cambios exactos de backend, frontend y persistencia
- formato exacto del log y de pending actions
- explicación clara del flujo final
- revisión final para evitar errores de sintaxis

