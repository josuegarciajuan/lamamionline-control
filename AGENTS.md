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
- **⛔ DUAL-PANEL BOT-CASA: Cualquier cambio en la sección bot-casa (UI, lógica, features, estilos) DEBE aplicarse por igual a los DOS paneles:**
  - `bot-casa/public/panel.php` (Admin, accedido via CRM en `?page=bot-casa`)
  - `bot-casa/public/client.php` (Cliente, accedido via `http://admin.casawasap.com/cliente`)
  - Son la misma herramienta con 2 UIs. Prohibido desarrollar para uno solo.
  - Excepciones: gestión de usuarios y API keys (solo admin), wizard onboarding (solo cliente).

## Terminología de apps (para interpretar prompts)

- **"bot comercial"** (también "botcomercial", "inbox comercial") → el archivo `inbox.php`.
- **"bot-casa"** (también "superwasap", "CasaWasap") → la app en `bot-casa/`.

Ambas son apps/PWA pensadas para instalarse en Android. Cuando se haga referencia a
**una de estas 2 apps**, se refiere a la vez a:
1. el **algoritmo de respuestas automáticas** (backend/engine), y
2. la **interfaz/app** (Android), donde se ve el **listado de todas las líneas
   vinculadas a ese bot y sus conversaciones**.

## Validación
- Ejecuta lint si existe.
- Ejecuta tests relevantes si existen.
- Resume siempre los archivos tocados.

## Done when
No des la tarea por terminada hasta que el comportamiento pedido funcione y los checks relevantes estén revisados.

- **bot-casa gate de tests:** cualquier cambio en `bot-casa/` que afecte al pipeline de respuesta debe pasar `composer test` y `composer phpstan` en verde (ejecutar desde `bot-casa/`). Si no hay tests que cubran el punto afectado, créalos como parte del cambio.

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

## 🌐 Versión de Chrome objetivo

- El WebView del dispositivo Lite (coche) es **Chrome 95.0.4638.74** (Android 8.1).
- Esta versión soporta prácticamente todas las features modernas de JS y CSS.
  Ver `specs/09-lite-device-specs.md` para las restricciones completas (hardware).

## ⛔ LITE CRM — Restricciones de hardware

Cada vez que se modifique código que afecte a la versión Lite del CRM
(`?lite=1`, `.is-lite`, `lite.css`), DEBE consultarse `specs/09-lite-device-specs.md`:

- **RAM**: 2 GB total, WebView ~300-500 MB disponibles
- **SoC**: Rockchip RK3566 / ARM (sin GPU potente)
- **Pantalla**: Táctil 1024×600 landscape (montaje en coche)
- **Rendimiento**: máximo 2 charts Chart.js simultáneos; el dashboard lite NO carga Chart.js
- **CSS**: archivo bajo 10K líneas (lite.css actual ~8K)
- **Animaciones**: solo `opacity` y `transform` (GPU-accelerated); `.is-lite *` fuerza `animation-duration: 0.01ms`
- **GPS**: intervalo 90s, threshold 20m, `enableHighAccuracy: true`

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
```

## 🧵 Cambios en paralelo (worktree + merge a producción)

El árbol principal (`/root/lamamionline-control` = producción viva vía bind mount) queda
**fijo en la rama de producción** (`master`) y los datos vivos (`data/`, `bot-casa/data/`)
viven solo ahí, fuera de git. Cada CAMBIO se trabaja en una copia aislada:

1. **`/git-workflow start`** crea la copia: `git worktree add -b work/<id> /root/.opencode-worktrees/lamamionline-control/<id>`.
   Se edita SOLO en la copia, nunca en el árbol principal.
2. Trabajar con commits atómicos `tipo: descripción` en la copia (el hook bloquea data/ y secretos).
3. **`/git-workflow finish`**: merge `--no-ff` de `work/<id>` → `master` (bajo flock), resolviendo
   conflictos con marcadores de git si dos sesiones tocaron el mismo trozo. El merge actualiza
   producción (bind mount) de inmediato. Si se tocaron css/js, aplicar la convención de caché
   (fecha en `index.php`). Limpia la copia y push solo si hay remoto.
4. `data/` y `bot-casa/data/` están fuera de git: NUNCA commitearlas ni dejarlas mergear
   (el hook las bloquea; se permite solo `git rm --cached` para dejarlas de trackear).
5. **Push**: solo si existe remoto. Sin remoto, el merge ya publicó en producción; reportar la
   acción manual exacta para la nube. Nunca inventar un remoto.
6. Si se pide OTRO cambio en la misma sesión, repetir el ciclo completo (nueva copia).
