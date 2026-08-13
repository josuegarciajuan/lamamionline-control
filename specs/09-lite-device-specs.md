# 09 · Dispositivo objetivo — Modo Lite

## Hardware objetivo

| Parámetro | Valor |
|-----------|-------|
| **RAM** | 2 GB |
| **Modelo** | evb3561sv_w_65_m0 |
| **SoC** | Rockchip RK3566 / similar (ARM) |
| **Pantalla** | Táctil ~1024x600 (landscape), montaje en coche |
| **Conectividad** | WiFi + GPS (chip hardware) |

## Software objetivo

| Parámetro | Valor |
|-----------|-------|
| **Android** | 8.1 (API 27) |
| **Security patch** | June 1, 2016 |
| **Kernel** | 3.18.19 (root@ubuntu, Mar 28 2019) |
| **Build** | android-trunk-m0-V1.1 |
| **WebView** | Chrome 95.0.4638.74 |

## Compatibilidad de navegador (Chrome 95)

Al ser Chrome 95, el WebView soporta prácticamente todo JavaScript moderno
(ES2022+) y CSS moderno. **Las restricciones de compatibilidad son solo de hardware.**

### JavaScript
- *Soportado*: Todo ES2022+ incluyendo `const`/`let`, arrow functions, `??`,
  `?.`, `Array.prototype.flat()`, `Object.fromEntries()`, `Promise.allSettled()`,
  `globalThis`, `String.prototype.matchAll()`, `fetch()`, `URL`, etc.
- *Regla*: no hay restricciones de lenguaje. Usar JS moderno sin problema.

### CSS
- *Soportado*: CSS Grid, Flexbox (`gap` incluido), custom properties,
  `backdrop-filter` (con y sin prefijo), `dvh`/`lvh`/`svh`,
  `@container` queries, `color-mix()`, `prefers-reduced-motion`, etc.
- *Regla*: no hay restricciones de features CSS. Usar CSS moderno sin problema.

### Media queries
- `prefers-reduced-motion` sí funciona en Chrome 95. Sin embargo, el mecanismo
  principal para desactivar animaciones sigue siendo `.is-lite` (clase aplicada
  server-side), porque el usuario del coche no necesariamente tiene activada
  la preferencia de sistema "reducir movimiento".

### Rendimiento
- **2 GB RAM total** (sistema + WebView + otras apps).
  El WebView tiene ~300-500 MB disponibles.
- Chart.js: **máximo 2 charts simultáneos** en este dispositivo.
  El dashboard lite NO debe cargar Chart.js.
- CSS: evitar archivos > 10K líneas. lite.css actual: ~8170 líneas — vigilar.
- JS: app.js actual: ~3720 líneas — OK.
- Animaciones CSS: preferir `opacity` y `transform` (GPU-accelerated).

### Service Worker
- Soportado en Android 5.0+. Compatible con este dispositivo.
- Precachear assets estáticos versionados (CSS, JS, manifest).
- Mantener versiones de cache sincronizadas con `?v=` en index.php.

### GPS
- `enableHighAccuracy: true` activa el chip GPS (OK en coche).
- Intervalo lite: 90s. Threshold de movimiento: 20m.

## Checklist para nuevos cambios

Antes de mergear cualquier cambio que afecte al frontend, verificar:

- [ ] ¿Añade más de 2 charts en una misma página?
- [ ] ¿Añade más de 500 líneas de CSS sin revisar impacto en lite.css?
- [ ] ¿Rompe la sincronización de versiones entre sw.js e index.php?
- [ ] ¿Usa animaciones pesadas (no `opacity` ni `transform`) sin tener en cuenta el dispositivo lite?
- [ ] ¿La página pesa más de lo razonable para 2 GB RAM (~300-500 MB en WebView)?

## Cómo testear en local

```
# Servir con ?lite=1 desde el dispositivo físico
http://lamami.online/control/index.php?lite=1

# Ver en Chrome DevTools emulando:
# - Device: "Responsive", 1024x600
# - CPU throttling: 4x slowdown
# - NO activar "prefers-reduced-motion" (el usuario del coche típicamente no lo tiene activado, pero Chrome 95 sí lo soporta)
```
