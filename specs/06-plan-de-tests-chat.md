# 06 · Plan de Tests — Chat de bot-casa

> **Regla:** cualquier cambio en el chat (`chat.js`, `chat.css`, `mensajes.php`) debe pasar sus tests.  
> Los tests son obligatorios: no se da por bueno un cambio sin tests verdes.

## Arquitectura de Tests (3 capas)

| Capa | Framework | Ubicación | Comando | Contra |
|------|-----------|-----------|---------|--------|
| JS Unit + DOM | `node:test` + `jsdom` | `tests/js/` | `npm run test:js` | `chat.js` real |
| PHP API | PHPUnit | `tests/Integration/MensajesApiTest.php` | `composer test` | `mensajes.php` con datos temp |
| E2E Browser | Playwright | `tests/e2e/` | `npx playwright test` | Navegador real + mocks |

## Catálogo de Tests (resumen)

### Capa 1 — JS Unit + DOM (30 tests)

```
✓ Abre/Cierra lifecycle (5 tests)
  - overlay creado, no duplicado, eliminado, Escape, no auto-expand

✓ Line selection (2 tests)
  - expande, colapsa

✓ Conversation loading (6 tests)
  - abre y renderiza, actualiza header, markRead, guard cambio rápido (bug #2),
    typing indicator para pending, placeholder vacía

✓ Message sending (4 tests)
  - no envía sin texto, bubble local, ✓ simple, botón re-habilita a 400ms (bug #4)

✓ Pause/Resume (3 tests)
  - llama API, UI "Pausado", hint visible

✓ Emoji picker (3 tests)
  - abre, cierra, inserta emoji

✓ Search/Filter (1 test)
  - query vacía muestra todo

✓ Mobile navigation (3 tests)
  - sidebar visible, showMain, showSidebar

✓ Error handling (1 test) + Empty states (2 tests)
  - error al cargar líneas, sin líneas, sin conversación
```

### Capa 2 — PHP API (20 tests)

```
✓ threads (5 tests)
  - sorted, unread, filter last9, empty memory, sender_lid

✓ conversation (3 tests)
  - messages, empty, requires thread_id

✓ mark_read (2 tests)
  - updates file, requires thread_id

✓ read_status (2 tests)
  - returns data, empty file

✓ threads_summary (2 tests)
  - aggregates, filter last9

✓ send (2 tests)
  - requires params, requires POST

✓ mark_lead (1 test)
✓ paused_list (1 test)
✓ Error cases (2 tests)
  - unknown action, default action
```

### Capa 3 — E2E Playwright (11 tests planificados)

```
✓ abre y cierra el chat
✓ carga líneas con indicadores de estado
✓ NO auto-expande línea al abrir (bug #1)
✓ expande línea y muestra conversaciones
✓ abre conversación y muestra burbujas
✓ envía mensaje y muestra bubble local
✓ emoji picker abre y cierra
✓ search filtra líneas
✓ Escape cierra el chat
  Mobile: sidebar visible
  Mobile: seleccionar conversación muestra main
```

## Cómo añadir tests

### Nuevo test JS
```bash
# Editar tests/js/chat.test.js
# Seguir el patrón describe/it/beforeEach
# Ejecutar: npm run test:js
```

### Nuevo test PHP API
```bash
# Editar tests/Integration/MensajesApiTest.php
# Usar $this->callApi(['action' => '...'], [...post...])
# Ejecutar: composer test
```

### Nuevo test E2E
```bash
# Editar tests/e2e/chat.spec.js
# Seguir patrón de Playwright test
# Ejecutar: npx playwright test --config tests/e2e/playwright.config.js
```

## Estado actual

| Fecha | Capa | Tests | Pasando |
|-------|------|-------|---------|
| 2026-06-29 | JS | 30 | 30 ✅ |
| 2026-06-29 | PHP API | 20 | 20 ✅ |
| 2026-06-29 | E2E | 11 | Pendiente instalar navegador |

## CI/CD

```bash
# En bot-casa/
npm run test:js && composer test   # Gate mínimo para merge
npx playwright test                 # Opcional, requiere navegador
```
