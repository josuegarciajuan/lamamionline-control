# AGENTS.md

## Proyecto
Aplicación de gestión.

## Reglas
- Haz cambios mínimos y seguros.
- No añadas dependencias nuevas sin permiso.
- Antes de tocar varios archivos, explica el plan.
- Reutiliza componentes existentes.
- Mantén el estilo visual actual.
- siempre que modifiques un js o un css, ve al index.php y adapta la fecha de modificación, esto se hace para que el navegador refresque estos archivos que tienen almacnados en caché y podamos ver a la primera los cambios efectuados:
<link rel="stylesheet" href="assets/style.css?v=20260331_1">
<script src="assets/app.js?v=20260402"></script>

## Validación
- Ejecuta lint si existe.
- Ejecuta tests relevantes si existen.
- Resume siempre los archivos tocados.

## Done when
No des la tarea por terminada hasta que el comportamiento pedido funcione y los checks relevantes estén revisados.

## Proyecto

Este proyecto se abre en opencode desde:

/root/lamamionline-control

Pero esa ruta es un bind mount hacia el proyecto real:

/var/www/html/atupuerta/control

Ambas rutas apuntan al mismo código.

## Reglas importantes

- No modificar archivos fuera de este proyecto salvo instrucción explícita.
- Antes de cambios importantes, ejecutar `git status`.
- No borrar archivos masivamente sin confirmación explícita.
- No usar `chmod -R 777` salvo instrucción explícita.
- No modificar configuraciones globales del servidor sin confirmación.
- No tocar `/etc`, `/root`, `/var/www/html` fuera de este proyecto ni servicios del sistema salvo que se pida expresamente.

## ⛔ Protección de datos de producción

Los archivos en `data/` contienen los datos de producción (ingresos, leads, clientas, campañas...).
**NO están en git** (están en `.gitignore`). Viven solo en el working tree.

**Operaciones PROHIBIDAS** (machacan datos de producción):
- `git stash` → internamente hace `git reset --hard HEAD`
- `git reset --hard`
- `git checkout -- data/`
- `git restore data/`
- `git clean -fd data/`

**Qué hacer en su lugar:**
- Para guardar trabajo en progreso: commit normal en una rama
- Para limpiar el working tree: solo tocar archivos de código (fuera de `data/`)
- Ante cualquier duda sobre si una operación toca `data/`, consultar primero

Si accidentalmente se pierden datos, el stash de git (`git stash list`) puede contener
una copia de emergencia (el stash se crea automáticamente antes del reset).

## ⛔ Protección contra divergencia git ↔ producción

El código en este repo se sirve directamente en producción vía bind mount.
Cualquier edición hecha directamente en el servidor de producción (fuera de git)
se perderá al sobreescribir desde el repo.

### Riesgo real detectado

Ya ha ocurrido una pérdida de funcionalidad: la llamada automática
`publicista_estados_wasap_run_due()` existía en producción pero no en git.
Al refrescar el working tree, la llamada se perdió y el sistema dejó de
publicar estados de WhatsApp automáticamente.

### Protocolo obligatorio antes de cualquier cambio

**Siempre, sin excepción**, antes de modificar un solo archivo:

```bash
git status          # ¿Hay archivos modificados sin commitear?
git diff            # ¿Qué ha cambiado exactamente?
```

1. **Si `git status` muestra archivos modificados (modified) o untracked:**
   - Esos cambios son código de producción que NO está en git.
   - **Commitéalos PRIMERO** antes de hacer cualquier otra cosa.
   - Pregunta al usuario si hay dudas sobre qué son.

2. **Si `git diff` muestra diferencias en archivos que vas a tocar:**
   - Ese código es funcionalidad viva de producción.
   - **No lo borres ni lo sobreescribas sin confirmar.**
   - Si necesitas modificarlo, hazlo sobre la versión de producción.

3. **Nunca asumas que el código en git refleja la realidad:**
   - La fuente de verdad es el servidor de producción, no git.
   - Si hay divergencia, producción manda. Git se actualiza para reflejar producción.

### Operaciones PROHIBIDAS adicionales

- `git checkout -- <archivo>` — machaca producción con versión de git
- `git restore <archivo>` — igual que arriba
- `git stash` — ya prohibido en sección anterior
- Reescribir un archivo sin haber hecho `git diff` primero

### Cómo preservar código de producción

Si encuentras divergencias (archivos modified):

```bash
git add <archivo_modificado>
git commit -m "sync: preservar cambios de producción (<descripción breve>)"
```

Así la funcionalidad viva queda registrada en git y no se perderá.

### Qué NUNCA commitear

- Archivos con contraseñas, API keys o secretos reales (buscar `password`, `api_key`, `token`, `secret`)
- El directorio `data/` (ya está en .gitignore)
- Backups de `data/` (`data_backup_*`)
- `__pycache__/`
- `contrato_firmado.php` (contiene datos sensibles)

## Comandos útiles

```bash
pwd
git status
git diff
php -l archivo.php
