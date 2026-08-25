# Integración de Autotube en el menú del CRM

## Objetivo

Dar acceso directo a Autotube desde el menú lateral del CRM, inmediatamente antes de Afiliados, y distinguir ambos accesos del resto de módulos sin introducir una etiqueta de grupo que pueda parecer un enlace.

## Diseño aprobado

- Crear la ruta `index.php?page=autotube` que muestra `https://lamami.online/autotube/` en un iframe integrado.
- Colocar Autotube seguido de Afiliados al final del menú funcional, antes de Salir.
- Encerrar exclusivamente esos dos enlaces en un contenedor visual sin texto: separación superior, fondo tenue y borde usando los tokens existentes.
- Mantener el estado activo de cada enlace y la accesibilidad mediante títulos de iframe descriptivos.
- Incluir ambos accesos, en el mismo orden, en el desplegable «Más» de Lite.

## Límites

- No se modifica Autotube ni Afiliados, sus APIs o su autenticación.
- No se cambian los demás módulos o la estructura general de navegación.
- No se añaden dependencias.

## Validación

- Validar sintaxis PHP de los archivos modificados.
- Comprobar que las rutas de Autotube y Afiliados generan sus iframes y que el menú conserva el estado activo correcto.
- Revisar que el CSS no altera los enlaces existentes ni provoca desbordamiento en móvil.
