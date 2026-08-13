# Spec: Rediseño Panel Cliente bot-casa

**Fecha:** 2026-06-10  
**Alcance:** `bot-casa/public/client.php`, `bot-casa/public/assets/style.css`, `bot-casa/public/assets/chat.js`, `bot-casa/public/index.php`
**Commit base:** `21ec2f3` (sync de producción previo al rediseño)

---

## ⚠️ COORDINACIÓN ENTRE AGENTES

Este spec toca **solo estos 4 archivos**. Ningún otro agente debe modificar estos archivos simultáneamente:

| Archivo | Tipo de cambio |
|---------|---------------|
| `bot-casa/public/client.php` | Reestructuración de tabs, nuevo header, nuevo dashboard, eliminación/creación de secciones |
| `bot-casa/public/assets/style.css` | Nuevas clases CSS para header, setup cards, feature cards, wizard |
| `bot-casa/public/assets/chat.js` | Añadir tooltip HTML al sidebar header del chat overlay |
| `bot-casa/public/index.php` | Actualizar `?v=` de cache busters: `style.css`, `chat.css`, `chat.js` |

**Archivos que NO se tocan (seguro para otros agentes):**
- `bot-casa/public/panel.php`
- `bot-casa/public/api/*`
- `bot-casa/src/**`
- `assets/style.css` (CRM, distinto)
- `app/**`

---

## 1. Header Redesign

Nuevo header con gradiente accent, slogan estático, avatar de usuario.

**CSS nuevo en style.css:**
```css
.header-client {
    background: linear-gradient(135deg, var(--accent-dark) 0%, var(--accent2) 100%);
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    padding: 16px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    position: relative;
    overflow: hidden;
}
.header-client::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse at 30% 50%, rgba(255,255,255,0.08) 0%, transparent 60%);
    pointer-events: none;
}
.header-brand { display: flex; align-items: center; gap: 12px; }
.brand-icon {
    width: 42px; height: 42px;
    border-radius: 12px;
    background: rgba(255,255,255,0.18);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
}
.brand-text h1 {
    font-size: 1.3rem; font-weight: 700;
    color: #fff; margin: 0;
}
.brand-text h1 span { font-size: .55em; opacity: .6; font-weight: 400; }
.header-slogan {
    font-size: .75rem; color: rgba(255,255,255,.65);
    font-style: italic; letter-spacing: .3px;
}
.header-user { display: flex; align-items: center; gap: 12px; }
.user-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    background: rgba(255,255,255,.2);
    display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .85rem; color: #fff;
    border: 2px solid rgba(255,255,255,.3);
}
.user-name { color: #fff; font-weight: 600; font-size: .9rem; }
```

**Compatibilidad panel.php:** El `.header` clásico se mantiene para panel.php. `.header-client` es exclusivo de client.php. Ningún conflicto.

---

## 2. Dashboard Unificado

Eliminar tab "🤖 Mi Bot", fusionar sus 2 cards en "📊 Inicio".

**Nuevo layout del Dashboard:**
1. Card "Estado del Bot" (existente)
2. Setup grid con 4 cards: 📱 WhatsApp, 💰 Tarifas, 👩 Chicas, 📬 Avisos — cada una con badge verde ✅ o rojo ❌
3. Stats grid (existente)
4. CTA condicional

**Tab-nav final:**
```
📊 Inicio | 🎭 Personalidad | 📱 Líneas | 👩 Chicas | 📢 Estados |
🔔 Notificaciones | 💬 Mensajes | 📨 Seguimiento | ⚙️ Ajustes | 📈 Estadísticas
```
(10 tabs, eliminados 🤖 Mi Bot y 📋 Registro, añadido 📨 Seguimiento)

---

## 3. Chicas — Mejora Cosmética CCSS

- Hero image: 180→210px, overlay gradiente
- Grid: `minmax(260px, 1fr)`, gap 20px
- Acciones: fade-in al hover
- Hover: `translateY(-4px) scale(1.01)`

---

## 4. Clientes → Notificaciones

Renombrar tab y título de sección. Añadir sección visual con iconos grandes de Telegram/WhatsApp. Leads table sin cambios.

---

## 5. Chat Tooltip

En el sidebar del modal de chat, añadir burbuja `(?)` que al hover muestre:

> Desde aquí puedes:
> - Contestar tú mismo a las conversaciones
> - Ver en directo cómo responde el bot
> - Pausar el bot para una conversación con el toggle 🟢/🟡
> - Intervenir cuando quieras
> - Volver a encender el bot para esa conversación

**Implementación:** Añadir el HTML del tooltip en `chat.js` donde se construye el sidebar header.

---

## 6. Eliminar Registro

Quitar del tab-nav y del DOM.

---

## 7. Nueva pestaña "📨 Seguimiento"

Unifica Follow-up y Recordatorios ETA (extraídos de Ajustes) en una pestaña independiente.

**Estructura:**
```
📨 Seguimiento
├── Card "Recontactar leads antiguos" (followup)
│   ├── Toggle ON/OFF
│   ├── Descripción de qué hace
│   ├── Warning de marcar leads como "llegó"
│   └── Settings (máx leads, horario)
├── Card "Recordatorios de llegada" (ETA)
│   ├── Toggle ON/OFF
│   ├── Descripción de qué hace
│   └── Warning de funcionamiento
└── Botón 💾 Guardar
```

**Backend:** Los campos POST siguen siendo `cron[followup][...]` y `cron[reminder][...]`, ya incluidos en la allowlist. Sin cambios en el backend.

---

## 8. Wizard Mejorado

Mantener estructura, mejorar diseño visual. Las 3 cards de pasos leen el estado real (✅ verde / ❌ rojo).

---

## Checklist de implementación

- [ ] Modificar `style.css`: `.header-client`, `.setup-grid`, `.setup-card`, `.feature-card`, wizard
- [ ] Modificar `client.php`: header, tabs, dashboard, chicas, notificaciones, seguimiento, eliminar registros
- [ ] Modificar `chat.js`: tooltip en sidebar header
- [ ] Actualizar `index.php`: cache busters
- [ ] `php -l` validación
- [ ] Verificar panel.php no roto
- [ ] Commit
