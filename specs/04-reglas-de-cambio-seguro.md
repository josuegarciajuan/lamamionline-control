# 04 · Reglas de cambio seguro

## Estado actual (observado en proyecto)

Este checklist se alinea a `AGENTS.md` y al runtime actual.

## 1) Antes de cambiar
- Revisar impacto y alcance en módulos afectados.
- Si se tocan varios archivos, dejar plan breve de qué se va a tocar y por qué.
- Identificar si el cambio afecta:
  - rutas (`index.php`, tabs)
  - acciones POST (`app/actions.php`)
  - persistencia (`app/storage.php`, `app/db.php`, `sql/`)

## 2) Durante el cambio
- Cambios mínimos y seguros (sin refactor lateral no pedido).
- Reutilizar componentes/funciones existentes antes de crear nuevas.
- No añadir dependencias nuevas sin permiso.
- No tocar lógica fuera del scope funcional acordado.
- Si se modifica JS o CSS, actualizar cache-busting en `index.php`:
  - `<link rel="stylesheet" href="assets/style.css?v=...">`
  - `<script src="assets/app.js?v=...">`

## 3) Después del cambio
- Validar sintaxis PHP de archivos tocados (`php -l archivo.php`).
- Ejecutar lint/tests relevantes si existen para el área tocada.
- Probar flujo manual mínimo:
  - carga de página principal afectada (`page/tab`)
  - acción POST crítica modificada
  - persistencia esperada (JSON y/o MySQL según modo)

## 4) Trazabilidad obligatoria en cada cambio
- Registrar en la PR/entrega:
  1) archivos tocados,
  2) contrato afectado (ruta/acción/entidad),
  3) validaciones ejecutadas,
  4) riesgos pendientes.

## Riesgos operativos abiertos (estado actual)
- `app/actions.php` concentra alto volumen de acciones y contiene duplicados de `case` (riesgo de mantenimiento/regresión).
- Convivencia `json/dual/mysql` puede generar divergencia de datos si no se valida backend activo por entorno.
- Configuración sensible en código (`app/db.php` y defaults en módulos) requiere control estricto de despliegue.
- Módulos grandes (`app/comercial.php`, `app/views.php`, `app/publicista.php`) con responsabilidades mixtas elevan riesgo de side effects.

## Recomendación (no implementada)
- Añadir plantilla estándar de “impacto + validación” por cambio y hacerla obligatoria.

## Definition of Done (para futuros cambios)
- [ ] Cambio cumple alcance acordado (sin modificaciones colaterales).
- [ ] Contratos afectados (rutas/acciones/datos) actualizados en `specs/`.
- [ ] Validaciones técnicas ejecutadas y reportadas (lint/tests/php -l según aplique).
- [ ] Flujo funcional probado manualmente en la(s) página(s) afectada(s).
- [ ] Si hubo cambios JS/CSS, versionado `?v=` actualizado en `index.php`.
- [ ] Se documentan riesgos abiertos y decisiones pendientes.
