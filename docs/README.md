# Documentación del CRM (estado actual)

Este directorio reúne la documentación funcional y técnica del CRM en su estado **actual** del código.

> Alcance: documentación basada en el repositorio en `/root/lamamionline-control` (bind mount de `/var/www/html/atupuerta/control`), sin cambios de lógica de negocio.

## Índice maestro

1. [Funcional](./funcional.md)
   - Qué hace el CRM hoy a nivel de negocio.
   - Módulos, flujos operativos y reglas funcionales.

2. [Técnico · Arquitectura](./tecnico-arquitectura.md)
   - Bootstrap, routing por página, capa de persistencia y ejecución.
   - Flujo request/response y piezas principales.

3. [Técnico · Módulos](./tecnico-modulos.md)
   - Resumen por módulo: entradas, datos, acciones, vistas y riesgos.

4. [Operación](./operacion.md)
   - Cron, webhook comercial, tareas de mantenimiento y troubleshooting básico.

5. [Seguridad y riesgos](./seguridad-y-riesgos.md)
   - Riesgos actuales priorizados y medidas recomendadas (sin reescribir negocio).

## Fuentes internas reutilizadas

- `README.txt`
- `docs/mysql_migration_phase1.md`
- `docs/mysql_phase2_setup.md`
- Código PHP relevante (`index.php`, `app/bootstrap.php`, `app/storage.php`, `app/auth.php`, `cron_avisos.php`, `cron_comercial.php`, `comercial_webhook.php`, `app/views.php`, `app/actions.php`)

## Convenciones de mantenimiento de esta documentación

- Evitar suposiciones no verificables.
- Cuando cambie routing, acciones POST o estructuras de `data/*.json*`, actualizar `tecnico-arquitectura.md` y `tecnico-modulos.md`.
- Cuando cambien cron/webhooks/operación, actualizar `operacion.md`.
- Cuando cambien controles de acceso o secretos, actualizar `seguridad-y-riesgos.md`.

## Huecos detectados (para completar en futuras iteraciones)

- No hay catálogo oficial de todos los `action_*` con contrato de campos por formulario.
- No hay inventario de endpoints externos consumidos por módulos como Comercial/Publicista.
- No hay playbook formal de backup/restore de `data/`.
