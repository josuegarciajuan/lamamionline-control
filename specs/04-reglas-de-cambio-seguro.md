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
- **Commit a git**: tras validar que el cambio funciona correctamente, hacer commit con mensaje descriptivo:
  ```bash
  git add <archivos_tocados>
  git commit -m "<tipo>: <descripción breve del cambio>"
  ```
  - Tipos recomendados: `fix`, `feat`, `refactor`, `style`, `chore`, `docs`.
  - **Nunca commitear** `data/`, backups, secretos ni `__pycache__/` (ver `AGENTS.md`).
  - Si antes del cambio había archivos `modified` sin commitear, preservarlos primero (ver protocolo en `AGENTS.md`).

## ⛔ Josué WhatsApp Personal (`?page=josue&tab=wasap`)

- **Queda TOTALMENTE PROHIBIDO** enviar un WhatsApp de test a cualquier conversación de esa línea (teléfono `654464023`) durante o después de corregir bugs.
  - Cada mensaje enviado por WAHA (`action=send` en `personal_wasap_api.php`) sale **de verdad** a los contactos, no es un sandbox.
  - Ya ha ocurrido: al corregir bugs se mandaron mensajes de test a varios contactos de la línea personal.
- Cómo validar cambios en esta sección **sin** enviar nada:
  - Endpoints de solo-lectura: `action=chats`, `action=messages`, `action=status`, `action=unread`.
  - Revisar `data/personal_wasap_data.json` y `data/personal_wasap_webhook_log.jsonl` (payloads crudos).
  - **Nunca** `action=send` como prueba; tampoco `rename_contact` con valores de prueba que sobrescriban nombres reales.
- Toda modificación al webhook (`personal_wasap_webhook.php`) debe validarse con `php -l` y, si aplica, contra logs crudos, no contra envíos reales.

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
- [ ] Cambios commiteados a git con mensaje descriptivo.
- [ ] Se documentan riesgos abiertos y decisiones pendientes.
- [ ] **bot-casa — gate de tests obligatorio:** todo cambio que afecte al algoritmo de respuesta de bot-casa (webhook, gates, procesadores, prompt, side-effects, humanización) DEBE añadir o actualizar sus tests en `bot-casa/tests/`. El cambio no se da por bueno hasta que `composer test` y `composer phpstan` pasen en verde dentro del directorio `bot-casa/`. Los tests corren siempre contra un directorio de datos temporal, nunca contra `data/` de producción.
- [ ] **bot-casa — gate de tests para el chat UI:** todo cambio que afecte al chat (`chat.js`, `chat.css`, `mensajes.php`) o su pipeline de mensajería DEBE:
  - Aportar sus propios tests en `bot-casa/tests/js/` (JS unitarios) o `bot-casa/tests/Integration/` (API PHP).
  - Si el cambio afecta a funcionalidades ya testeadas, modificar las pruebas existentes.
  - Superar `npm run test:js` (tests JS) y `composer test` (tests PHP) en verde.
  - Ver `specs/06-plan-de-tests-chat.md` para el catálogo completo de tests.
