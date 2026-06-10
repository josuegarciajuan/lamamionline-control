# 05 · Mobile Responsive Redesign V2 — Bloque A

## Objetivo
Rediseñar la experiencia móvil del CRM reestructurando navegación, corrigiendo overflow horizontal, consolidando tokens CSS y mejorando el sistema de card stacks. Esta es la segunda iteración (V2) sobre el trabajo MOBILE-REDESIGN previo.

## Decisiones ratificadas
1. **Navegación**: 4 tabs primarios + hamburguesa "Más" (bottom sheet)
2. **Form sheets**: DOM move (no cloneNode) — pendiente para Bloque B
3. **CSS**: Consolidación incremental → este bloque solo tokens

## Bloque A — Alcance

### F0 — Spec & Auditoría (este documento)
- [ ] Documentar plan Bloque A
- [ ] Auditar todos los elementos con `min-width` > 320px
- [ ] Listar tablas sin `<thead>` o sin soporte card-stack

### F1 — Tokens CSS Consolidation
Archivos: `assets/tokens.css` (NUEVO), `index.php`
- [ ] Extraer bloque `:root` de theme.css a `assets/tokens.css`
- [ ] Cargar tokens.css en index.php ANTES de style.css
- [ ] Mantener compatibilidad: no eliminar :root de theme.css (redundancia segura)

### F3 — Navegación 4 Tabs + Hamburguesa
Archivos: `app/views.php`, `assets/theme.css`, `assets/app.js`
- [ ] Nuevo HTML: 4 tabs (Home, Negocio, Comercial, Bot) + ☰
- [ ] "Más" bottom sheet con secciones secundarias agrupadas
- [ ] CSS: touch targets 44px, labels 11px, gold indicators
- [ ] JS: toggle "Más" sheet con backdrop
- [ ] Eliminar viejo HTML de 8 tabs y popovers

### F4 — Card Stacks & Anti-Overflow
Archivos: `assets/style.css`, `assets/theme.css`
- [ ] Eliminar `min-width:640px` en tablas mobile (style.css L2074)
- [ ] Eliminar negative margins en `.table-wrap` (style.css L2064-2067)
- [ ] Forzar `.card-stack-item` a max-width: 100%
- [ ] Asegurar `* { max-width: 100vw }` consistente
- [ ] FAB: cambiar gradiente rosa a dorado (tema consistente)

## Páginas y su estado mobile (auditoría)

| Página | Tablas | ¿Con `<thead>`? | ¿Overflow? | Acción Bloque A |
|--------|--------|-----------------|------------|-----------------|
| Dashboard | KPIs grid, chart | Sí | No (grid) | OK |
| LaMami | leads table | Sí | Sí (min-width) | Card stack |
| Comercial | agent table, threads | Sí | Sí (900px) | Card stack |
| Publicista | campaign items | Sí | Sí | Card stack |
| Jostal | day grid | N/A | Sí (minmax 220px) | Mobile grid fix |
| Bots | runtime table | Sí | Sí | Card stack |
| Gastos | expenses table | Sí | Sí | Card stack |
| Casawasap | lines table | Sí | Sí | Card stack |
| Josue | N/A | N/A | iframe | OK |
| Avisos | avisos list | No (divs) | No | OK |
| Informes | grid/chart | N/A | No (grid) | OK |
| Bot-Casa | chat | N/A | CSS propio | No tocar |

## Elementos con min-width problemáticos (auditoría)

| Archivo | Línea | Regla | Valor |
|---------|-------|-------|-------|
| style.css | 2074 | `.page-* .table-wrap table` | 640px |
| style.css | 3461 | `.agent-table` | 900px |
| style.css | 2819 | `.commercial-thread-actions-cell` | 260px |
| style.css | 2064-2067 | `.table-wrap` negative margin | -12px / +24px |
| style.css | 362 | `.agent-table thead th.col-advice` | 220px |
| theme.css | 1834 | `canvas` | !important fixed height |

## Dual-panel bot-casa
Sin cambios en este bloque. bot-casa tiene su propio CSS responsive (`chat.css`, `bot-casa/public/assets/style.css`).

## Reglas de cambio seguro aplicables
- Cambios mínimos: solo tocar lo necesario
- Cache-busting: bump `?v=` en index.php tras cada cambio CSS/JS
- No tocar data/ ni lógica de backend
- Si se modifica JS o CSS, actualizar `?v=`
