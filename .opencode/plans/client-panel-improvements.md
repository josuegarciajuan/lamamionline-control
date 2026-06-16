# Plan de Mejoras — Panel Cliente (client.php)

## Archivos a modificar
1. `bot-casa/public/client.php` — Principal (todos los cambios de UI)
2. `bot-casa/public/api/estados.php` — Fix URL imagen en `chica_del_dia`
3. `bot-casa/public/assets/chat.js` — Tooltip + fix reapertura

---

## Cambio 1: Menú centrado en desktop

**Dónde:** `<style>` inline en client.php (~línea 464)

Añadir después del bloque `.header-pills`:
```css
@media (min-width: 769px) {
    .tab-nav { justify-content: center; }
}
```

---

## Cambio 2: Reordenar menú + añadir pestaña Aprendizaje

**Dónde:** client.php ~línea 608

**Nuevo orden de botones:**
```
📊 Inicio → 🎭 Personalidad → 📱 Líneas → 👩 Chicas → 🔔 Notificaciones → 💬 Chat → 📢 Estados → 📨 Seguimiento → 🧠 Aprendizaje → ⚙️ Ajustes → 📈 Estadísticas
```

Añadir botón Aprendizaje entre Seguimiento y Ajustes.

---

## Cambio 3: Guías visuales (section-guide → hero cards)

**Dónde:** Cada `.section-guide` en todas las pestañas

**Nuevo estilo inline** para `.section-guide` (reemplazar el existente):
```css
.section-guide {
    display: flex; align-items: center; gap: 16px;
    background: linear-gradient(135deg, rgba(124,92,255,0.08), rgba(255,59,141,0.05));
    border: 1px solid rgba(124,92,255,0.15);
    border-radius: var(--radius-md);
    padding: 16px 20px;
    margin-bottom: 16px;
    font-size: .82rem;
    color: var(--text);
}
.section-guide-icon {
    font-size: 2.2rem; flex-shrink: 0;
}
.section-guide-body strong { color: var(--accent); }
.section-guide-body span { display: block; color: var(--text-muted); font-size: .78rem; margin-top: 2px; }
```

**Formato HTML para cada sección:**
```html
<div class="section-guide">
    <span class="section-guide-icon">📱</span>
    <div class="section-guide-body">
        <strong>Título corto</strong>
        <span>Subtítulo explicativo breve, una línea.</span>
    </div>
</div>
```

**Aplicar a:** Personalidad, Líneas, Chicas, Estados, Notificaciones, Seguimiento, Aprendizaje (nueva), Ajustes, Estadísticas.

---

## Cambio 4: Compactar card "Estado de tu Bot"

**Dónde:** client.php ~líneas 626-633

**Actual:**
```html
<div class="card">
    <h2>Estado de tu Bot</h2>
    <div class="bot-status">
        <span class="bot-indicator status-off"></span>
        <span class="bot-status-text">APAGADO</span>
    </div>
</div>
```

**Nuevo (una sola línea horizontal):**
```html
<div class="card" style="display:flex;align-items:center;gap:14px;padding:16px 20px">
    <span style="font-size:1.4rem">🤖</span>
    <span style="font-weight:600;font-size:.9rem;color:var(--text-muted)">Estado del Bot</span>
    <span class="bot-indicator <?php echo $botStatusClass; ?>" style="margin-left:auto"></span>
    <span class="bot-status-text" style="font-weight:700;font-size:.95rem"><?php echo h($botStatusLabel); ?></span>
</div>
```

---

## Cambio 5: Lógica "¡Todo listo!" con flag `bot_has_been_on`

**Dónde:** 
- Añadir check de archivo marcador en la parte PHP (~líneas 540-563)
- Modificar el handler `toggle_bot` (~líneas 320-387)

**Nueva variable:**
```php
$botEverOnMarker = WASAPBOT_ROOT . '/data/users/' . $clientUserId . '/.bot_has_been_on';
$botEverOn = file_exists($botEverOnMarker);
```

**En toggle_bot (modo START, tras éxito):**
```php
@file_put_contents($botEverOnMarker, date('c'), LOCK_EX);
```

**Condición para mostrar "¡Todo listo!":**
```php
if ($progressPct >= 100 && !$botEverOn)
```

**Nuevo texto:**
```
🚀 ¡Todo listo! Enciende tu bot con el botón ▶ ENCENDER de arriba y empieza a recibir clientes automáticamente.
```

---

## Cambio 6: Texto QR en Líneas

**Dónde:** client.php ~línea 957

**Actual:**
```
Al crear una línea, recibirás un código QR. Escanéalo con tu móvil desde WhatsApp → Ajustes → Vincular dispositivo para que el bot pueda usar ese número.
```

**Nuevo:**
```
Al crear una línea, aparecerá un botón <strong>QR</strong> en la tabla. Púlsalo para ver el código QR. Debes escanearlo <strong>rápido (antes de 1-2 minutos)</strong> desde tu WhatsApp → Ajustes → Vincular dispositivo.
```

---

## Cambio 7: Fix URL imagen en Estados

**Dónde:** `bot-casa/public/api/estados.php` ~línea 170

**Actual:**
```php
case 'chica_del_dia':
    $g = $shuffled[0];
    $foto = !empty($g['fotos'][0]) ? $g['fotos'][0] : '';
    $txt = '🔥 ' . $g['nombre'] . ' 🔥' . ($foto ? "\n" . $foto : '');
    break;
```

**Nuevo (sin URL de foto en texto):**
```php
case 'chica_del_dia':
    $g = $shuffled[0];
    $txt = '🔥 ' . $g['nombre'] . ' 🔥';
    break;
```

---

## Cambio 8: Telegram explicación detallada

**Dónde:** client.php ~líneas 1143-1152

**Actual (texto conciso):**
```
<strong>Telegram (recomendado):</strong><br>
1. Abre Telegram, busca @BotFather, crea un bot con /newbot y copia el token<br>
2. Busca @userinfobot para obtener tu Chat ID personal<br>
3. Pega tu Chat ID abajo (uno por línea si tienes varios)<br>
4. Activa las alertas con el checkbox<br><br>
<strong>WhatsApp:</strong><br>
Puedes poner tu número personal para recibir avisos por WhatsApp. 
<strong>IMPORTANTE:</strong> El número que pongas aquí NO puede ser uno de los que tengas configurados como línea del bot en 📱 Líneas. Usa tu número personal.
```

**Nuevo (para novatos):**
```
<strong>📱 Telegram (recomendado) — paso a paso:</strong><br>
1. Abre la app de Telegram en tu móvil u ordenador<br>
2. En el buscador de Telegram, escribe <strong>@BotFather</strong> (es el bot oficial para crear bots)<br>
3. Escríbele <strong>/newbot</strong> y sigue sus instrucciones: te pedirá un nombre (ej: "Avisos Casa") y un usuario (ej: <code>avisos_casa_bot</code>)<br>
4. @BotFather te dará un <strong>token</strong> (un texto largo). CÓPIALO, lo necesitarás<br>
5. Ahora busca <strong>@userinfobot</strong> en Telegram, inícialo con /start y te dará tu <strong>Chat ID</strong> (un número, ej: 123456789)<br>
6. Pega tu Chat ID en la caja de abajo (uno por línea si tienes varios)<br>
7. Marca el checkbox <strong>Alertas activadas</strong><br>
8. Pulsa el botón 💾 Guardar avisos<br><br>
<strong>WhatsApp:</strong><br>
Alternativa: puedes poner tu número personal para recibir los avisos por WhatsApp (menos recomendado).
<strong>IMPORTANTE:</strong> El número que pongas NO puede ser uno de los que tengas como línea del bot en 📱 Líneas.
```

---

## Cambio 9: Tooltip "?" en Chat (chat.js)

**Dónde:** `bot-casa/public/assets/chat.js` ~líneas 277-281

**Problema:** El `tooltip-box` tiene `style="display:none"` inline, que prevalece sobre el hover CSS de client.php.

**Fix:** Eliminar `display:none` del inline style del tooltip-box y añadir una clase CSS:

```javascript
'<span class="tooltip-box" style="position:absolute;z-index:100;top:100%;left:0;margin-top:6px;background:var(--panel);border:1px solid var(--accent);border-radius:10px;padding:10px 14px;font-size:.75rem;color:var(--text);max-width:280px;box-shadow:0 10px 30px rgba(0,0,0,.6);line-height:1.55;white-space:normal;font-weight:400">' +
'Aquí aparecen todas las conversaciones de WhatsApp. Puedes:<br>• <strong>Ver en directo</strong> cómo responde el bot<br>• <strong>Pausar el bot</strong> en una conversación concreta<br>• <strong>Contestar tú</strong> manualmente<br>• <strong>Reactivar el bot</strong> cuando quieras<br>• <strong>Control total</strong> de tu WhatsApp</span>' +
```

---

## Cambio 10: Fix bug Chat no se reabre

**Dónde:** client.php ~líneas 2071-2085

**Problema:** `loadedTabs` cachea que la pestaña chat ya fue cargada. Tras cerrar el modal, al pulsar Chat de nuevo, no se reabre.

**Fix:** Cambiar el loader de `tab-mensajes` para que no dependa de `loadedTabs`:

```javascript
'tab-mensajes': function() {
    if (typeof ChatApp !== 'undefined') {
        setTimeout(function() { ChatApp.open(); }, 50);
    }
},
```

Y en el event listener, hacer que `tab-mensajes` siempre llame al loader:

```javascript
document.querySelectorAll('#tabNav button[data-tab]').forEach(function(btn){
    btn.addEventListener('click', function(){
        var tabId = btn.getAttribute('data-tab');
        // Chat tab always opens fresh (user may have closed it)
        if (tabId === 'tab-mensajes') {
            if (tabLoaders[tabId]) tabLoaders[tabId]();
            return;
        }
        if (tabLoaders[tabId] && !loadedTabs[tabId]) { loadedTabs[tabId]=true; tabLoaders[tabId](); }
    });
});
```

---

## Cambio 11: Pestaña Aprendizaje (contenido)

**Dónde:** client.php — nueva `<div class="tab-content" id="tab-learning">` entre Seguimiento y Ajustes

**Contenido HTML:**
```html
<div class="tab-content" id="tab-learning">
    <div class="card">
        <h2>🧠 Aprendizaje del Bot
            <span class="tooltip-wrap"><span class="tooltip-icon">?</span>
                <span class="tooltip-box">El bot analiza las conversaciones para aprender tu estilo y detectar qué clientes vinieron de verdad.</span>
            </span>
        </h2>
        <div class="section-guide">
            <span class="section-guide-icon">🧠</span>
            <div class="section-guide-body">
                <strong>El bot aprende de tus conversaciones</strong>
                <span>Coge tu estilo si contestas desde la pestaña Chat. Aquí verás los leads notificados y podrás marcar cuáles vinieron de verdad. Cuantos más marques, más inteligente se hará el bot.</span>
            </div>
        </div>
        
        <!-- Leads table (moved from Notificaciones) -->
        <div style="margin-top:16px">
            <h3 style="margin-bottom:8px">📋 Leads detectados</h3>
            <p style="color:var(--text-muted);font-size:.78rem;margin-bottom:12px">
                Marca los leads como <strong style="color:var(--ok)">✅ Vino</strong> cuando el cliente haya llegado de verdad a la casa. Esto ayuda al bot a priorizar y mejorar sus respuestas.
            </p>
            <div id="clientes-table-container">
                <p style="color:var(--text-muted);text-align:center;padding:20px">No hay leads registrados todavía.</p>
            </div>
        </div>
    </div>
</div>
```

Añadir al `tabLoaders`:
```javascript
'tab-learning': loadClientes,
```

---

## Cambio 12: Actualizar textos warning en Seguimiento

**Dónde:** client.php ~líneas 1207-1231

**Primer warning (línea 1208):**
```html
<div class="alert-warning" style="margin-bottom:12px;font-size:.8rem;padding:10px 14px;border-radius:8px">
    ⚠️ <strong>Importante:</strong> Marca los leads como "llegó" en la pestaña <strong>🧠 Aprendizaje</strong>. Si no los marcas, cuando pase el tiempo que el cliente dijo que iba a tardar, el bot le recordará igual que tiene una cita. Si no tienes tiempo de marcar llegadas, mejor <strong>desactiva esta función</strong>.
</div>
```

**Segundo warning (línea 1230):**
```html
<div class="alert-warning" style="margin-bottom:10px;font-size:.85rem;padding:12px 14px;border-radius:8px;border:2px solid var(--warn);background:rgba(251,191,36,.12)">
    ⚠️ <strong>IMPORTANTE:</strong> Este recordatorio se envía automáticamente aunque no hayas marcado el lead como "llegó" en 🧠 Aprendizaje. El bot se basa solo en lo que el cliente dijo. Si el cliente llega pero no lo marcaste, el bot le enviará el recordatorio igual. Si no tienes tiempo de marcar llegadas, <strong>mejor no uses esta función</strong>.
</div>
```

---

## Cambio 13: Valores Humanización + warning

**Dónde:** client.php ~líneas 1249-1273

**Actualizar labels "Recomendado":**

| Label | Valor actual | Nuevo |
|---|---|---|
| Espera antes de "ver" | 1 | 1 (sin cambio) |
| Aleatoria mín "visto" | 1 | 1 (sin cambio) |
| Aleatoria máx "visto" | 3 | 3 (sin cambio) |
| Tiempo base "escribiendo" | 4 | **5** |
| Caracteres/segundo mín | 38 | **3** |
| Caracteres/segundo máx | 85 | **8** |
| Tiempo lectura mín (ms) | 900 | **500** |
| Tiempo lectura máx (ms) | 2200 | **1200** |
| Espera entre mensajes | 15 | **6** |
| Habituación inicio | 6.2 | **2.5** |
| Habituación decaimiento | 0.92 | **0.82** |
| Habituación suelo | 1.25 | **1.2** |

**Añadir warning al final de la sección Humanización (antes de cerrar `</div>`):**
```html
<div class="alert-warning" style="margin-top:12px;font-size:.8rem;padding:10px 14px;border-radius:8px">
    ⚠️ <strong>Cuidado:</strong> Modifica estos valores con precaución. Si los cambias sin control, el bot puede desajustarse: contestar demasiado rápido (parecerá artificial y espantará clientes) o demasiado lento (el cliente se impacientará y se irá). Los valores recomendados están probados y funcionan bien.
</div>
```

---

## Bump de versiones

En `client.php`:
```
assets/style.css?v=20260610_2  →  ?v=20260610_3
assets/chat.css?v=20260610_2   →  ?v=20260610_3
assets/chat.js?v=20260610_2    →  ?v=20260610_3
```

---

## Resumen de cambios por archivo

### `bot-casa/public/client.php`
- CSS: `.tab-nav` justify-content:center en desktop
- CSS: Nuevo estilo `.section-guide` con flex + icon + body
- Menú: reordenar botones, añadir "🧠 Aprendizaje"
- Inicio: compactar card Estado del Bot
- Inicio: flag `bot_has_been_on` para controlar "¡Todo listo!"
- Líneas: texto QR actualizado
- Notificaciones: texto Telegram detallado paso a paso
- Chat: fix loader para siempre poder reabrir
- Aprendizaje: nueva pestaña con leads table
- Seguimiento: actualizar warnings (referencias a "Aprendizaje")
- Ajustes: actualizar 8 valores recomendados + warning
- Bump versiones CSS/JS

### `bot-casa/public/api/estados.php`
- Eliminar URL de foto del texto en formato `chica_del_dia`

### `bot-casa/public/assets/chat.js`
- Fix tooltip `display:none` inline → usar clase CSS
- Actualizar texto del tooltip Conversaciones
