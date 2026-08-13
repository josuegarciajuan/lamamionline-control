# 00 · Contexto y alcance

## Estado actual (observado en código)

### Contexto del sistema
- Aplicación web PHP monolítica de gestión interna (“LaMami CRM”) con entrada única en `index.php`.
- El bootstrap global carga helpers, autenticación, almacenamiento, módulos de negocio y vistas desde `app/bootstrap.php`.
- El enrutado principal se resuelve por query param `page` (`index.php?page=...`) y en varios módulos por `tab`.

### Objetivo operativo actual
- Centralizar operación diaria de varios frentes del negocio:
  - LaMami (interesadas/clientas/LamamiBot)
  - Publicista (perfiles, estrategias, cuentas, campañas, subida)
  - Comercial (motor de procesos, líneas, conversaciones)
  - Casawasap
  - Jostal
  - Josue (operación interna)
  - Informes, gastos y avisos

### Evidencia y trazabilidad
- Entrada y dispatch de páginas: `index.php` (switch por `page`, manejo POST global, auth).
- Carga de módulos: `app/bootstrap.php`.
- Render de páginas principales: `app/views.php` y `app/comercial.php`.

## Alcance de estas specs
- Documentar **contratos actuales** (rutas, acciones POST críticas y persistencia).
- Definir límites de módulo para cambios futuros seguros.
- Añadir checklist práctico de cambio seguro alineado a reglas del proyecto.

## Fuera de alcance
- Rediseño funcional.
- Refactor profundo de arquitectura.
- Cambios de lógica de negocio o migración completa de almacenamiento.

## Recomendación (no implementada)
- Mantener este bloque como “spec base” y actualizarlo al cierre de cada cambio que altere:
  1) rutas/acciones,
  2) persistencia,
  3) límites entre módulos.
