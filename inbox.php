<?php
/**
 * inbox.php — Inbox Comercial standalone (estilo SuperWasap).
 *
 * Acceso directo sin login, como bot-casa/public/chat.php.
 * URL: https://lamami.online/control/inbox.php
 *
 * Dos vistas:
 *   - Chat WhatsApp (default): conversaciones de líneas comerciales
 *   - Bandeja (?view=agent): tabla de agente comercial (include del CRM)
 *
 * Toggles globales: Respuestas, Inicio. Toggle por conversación.
 */

declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/comercial_agenda.php';

// ── Auto-auth (mismo patrón que SuperWasap) ──
auth_auto_login_from_whitelist();

if (!is_logged_in()) {
    $_SESSION['logged_in'] = true;
    $_SESSION['username'] = 'inbox';
    $_SESSION['display_name'] = 'Inbox';
}

// ── Vista activa (chat por defecto, ?view=agent para bandeja) ──
$view = trim((string)($_GET['view'] ?? 'chat'));
if (!in_array($view, ['chat', 'agent'], true)) $view = 'chat';

// ── Leer settings para estado inicial de toggles ──
$settings = inbox_get_settings();
$repOn  = !empty($settings['replies_enabled']);
$openOn = !empty($settings['opener_enabled']);

// ── Versiones para cache busters ──
$_chatCssV = is_file(__DIR__ . '/assets/inbox-chat.css') ? filemtime(__DIR__ . '/assets/inbox-chat.css') : time();
$_chatJsV  = is_file(__DIR__ . '/assets/inbox-chat.js')  ? filemtime(__DIR__ . '/assets/inbox-chat.js')  : time();
$_forceV   = '20260827_01'; // chat ligero, polling único e historial incremental

?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#075e54">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Inbox">
<meta name="mobile-web-app-capable" content="yes">
<meta name="description" content="Inbox Comercial — Chat unificado de líneas comerciales">
<link rel="manifest" href="manifest-inbox.json?v=<?= filemtime(__DIR__ . '/manifest-inbox.json') ?>">
<link rel="icon" type="image/svg+xml" href="assets/wa-icon.svg?v=<?= filemtime(__DIR__ . '/assets/wa-icon.svg') ?>">
<link rel="apple-touch-icon" href="assets/wa-icon.svg?v=<?= filemtime(__DIR__ . '/assets/wa-icon.svg') ?>">
<link rel="stylesheet" href="assets/tokens.css?v=<?= filemtime(__DIR__ . '/assets/tokens.css') ?>">
<link rel="stylesheet" href="assets/inbox-chat.css?v=<?= $_chatCssV ?>-<?= $_forceV ?>">
<title>Inbox</title>
<style>
/* Reset standalone — WhatsApp dark theme */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;height:100vh;min-height:0;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;background:#0b141a;color:#e9edef}
html{font-size:16px;line-height:1.5;-webkit-text-size-adjust:100%;touch-action:manipulation}

/* ── Top bar ── */
.inbox-topbar{display:flex;align-items:center;gap:10px;padding:8px 16px;background:#075e54;border-bottom:1px solid rgba(0,0,0,.15);flex-shrink:0;min-height:50px}
.inbox-topbar-title{font-weight:600;font-size:15px;color:#fff;margin-right:auto;letter-spacing:.01em;display:flex;align-items:center;gap:6px;flex-shrink:0}
.inbox-topbar-title-icon{font-size:19px}

/* ── Toggle switches (iOS style) ── */
.inbox-toggles{display:flex;align-items:center;gap:14px}
.inbox-switch{display:inline-flex;align-items:center;gap:8px;cursor:pointer;user-select:none;-webkit-tap-highlight-color:transparent}
.inbox-switch input{display:none}
.inbox-switch__label{font-size:12px;font-weight:600;color:rgba(255,255,255,.75);white-space:nowrap}
.inbox-switch__track{width:42px;height:24px;background:rgba(255,255,255,.2);border-radius:12px;position:relative;transition:background .2s;flex-shrink:0}
.inbox-switch__track::before{content:'';width:20px;height:20px;background:#fff;border-radius:50%;position:absolute;top:2px;left:2px;transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.inbox-switch input:checked + .inbox-switch__track{background:#25d366}
.inbox-switch input:checked + .inbox-switch__track::before{transform:translateX(18px)}
.inbox-switch__state{font-size:10px;font-weight:700;color:rgba(255,255,255,.55);min-width:24px}

/* Inicio switch — más pequeño/subdued */
.inbox-switch--small .inbox-switch__label{font-size:11px;color:rgba(255,255,255,.45)}
.inbox-switch--small .inbox-switch__track{width:34px;height:20px;border-radius:10px;background:rgba(255,255,255,.12)}
.inbox-switch--small .inbox-switch__track::before{width:16px;height:16px;top:2px;left:2px}
.inbox-switch--small input:checked + .inbox-switch__track{background:rgba(37,211,102,.55)}
.inbox-switch--small input:checked + .inbox-switch__track::before{transform:translateX(14px)}

/* ── Panel / Chat toggle button ── */
.inbox-panel-btn{padding:8px 20px;border-radius:8px;border:none;background:linear-gradient(135deg,#25d366,#075e54);color:#fff;font-size:14px;font-weight:700;cursor:pointer;white-space:nowrap;box-shadow:0 2px 12px rgba(37,211,102,.25);transition:all .15s;flex-shrink:0;letter-spacing:.01em}
.inbox-panel-btn:hover{transform:translateY(-1px);box-shadow:0 4px 18px rgba(37,211,102,.4)}
.inbox-panel-btn:active{transform:translateY(0);box-shadow:0 1px 6px rgba(37,211,102,.15)}

/* ── Fullscreen chat overlay ── */
.inbox-fullchat-overlay{position:fixed;inset:0;z-index:10000;background:#0b141a;display:flex;flex-direction:column}
.inbox-fullchat-header{display:flex;align-items:center;gap:12px;padding:10px 16px;background:#075e54;border-bottom:1px solid rgba(0,0,0,.15);flex-shrink:0;min-height:50px}
.inbox-fullchat-back{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);color:#fff;border-radius:6px;padding:6px 14px;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap;transition:background .15s}
.inbox-fullchat-back:hover{background:rgba(255,255,255,.2)}
.inbox-fullchat-header-info{flex:1;min-width:0}
.inbox-fullchat-name{font-size:16px;font-weight:600;color:#fff}
.inbox-fullchat-sub{font-size:12px;color:rgba(255,255,255,.55);margin-top:2px}
.inbox-fullchat-messages{flex:1;min-height:0;overflow-y:auto;padding:10px 20px;display:flex;flex-direction:column;-webkit-overflow-scrolling:touch;overscroll-behavior:contain}
.inbox-fullchat-input-area{padding:8px 12px;background:#111b2e;border-top:1px solid #222d34;display:flex;gap:8px;align-items:flex-end;flex-shrink:0;min-height:56px}
.inbox-fullchat-input{flex:1;resize:none;padding:9px 14px;border-radius:20px;border:none;background:#1f2c33;color:#e9edef;font-size:15px;outline:none;min-height:42px;max-height:120px;font-family:inherit;line-height:1.4}
.inbox-fullchat-input:focus{background:#2a3942}
.inbox-fullchat-input::placeholder{color:#8696a0}
.inbox-fullchat-send-btn{width:42px;height:42px;border-radius:50%;border:none;background:#25d366;color:#000;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;font-size:16px;font-weight:700;transition:background .15s,transform .1s}
.inbox-fullchat-send-btn:hover{background:#20bd5a;transform:scale(1.05)}
.inbox-fullchat-send-btn:active{transform:scale(.95)}
.inbox-fullchat-send-btn:disabled{opacity:.3;cursor:default;transform:none}

/* View containers */
.inbox-chat-shell{flex:1;display:flex;overflow:hidden}
.inbox-agent-shell{flex:1;display:flex;overflow-y:auto;overflow-x:hidden;background:#0b141a;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;touch-action:pan-y}

@media(max-width:768px){
  .inbox-topbar{padding:6px 10px;gap:6px;min-height:42px}
  .inbox-topbar-title{font-size:13px}
  .inbox-switch__label{font-size:11px}
  .inbox-switch--small .inbox-switch__label{display:none}
  .inbox-panel-btn{padding:6px 14px;font-size:13px}
  .inbox-toggles{gap:8px}
}
</style>
</head>
<body style="display:flex;flex-direction:column">

<!-- Top bar -->
<div class="inbox-topbar" id="inboxTopbar">
    <span class="inbox-topbar-title">
        <span class="inbox-topbar-title-icon">💬</span> Inbox
    </span>

    <!-- Toggle switches (siempre visibles en ambas vistas) -->
    <div class="inbox-toggles" id="inboxToggles">
        <label class="inbox-switch" id="inboxToggleReplies" title="Activar/desactivar respuestas automáticas del bot">
            <span class="inbox-switch__label">Respuesta</span>
            <input type="checkbox" <?= $repOn ? 'checked' : '' ?> onchange="InboxChat.toggleGlobal('replies', this)">
            <span class="inbox-switch__track"></span>
            <span class="inbox-switch__state"><?= $repOn ? 'ON' : 'OFF' ?></span>
        </label>
        <label class="inbox-switch inbox-switch--small" id="inboxToggleOpener" title="Activar/desactivar mensajes de inicio">
            <span class="inbox-switch__label">Inicio</span>
            <input type="checkbox" <?= $openOn ? 'checked' : '' ?> onchange="InboxChat.toggleGlobal('opener', this)">
            <span class="inbox-switch__track"></span>
        </label>
    </div>

    <!-- Agenda button -->
    <button class="inbox-agenda-btn" id="inboxAgendaBtn" onclick="InboxChat.openAgenda()" title="Agenda comercial">
        👤
    </button>

    <!-- View toggle — botón único y prominente -->
    <?php if ($view === 'agent'): ?>
        <button class="inbox-panel-btn" id="inboxPanelBtn" onclick="InboxChat.switchView('chat')" title="Ir al Chat de WhatsApp">
            💬 Chat
        </button>
    <?php else: ?>
        <button class="inbox-panel-btn" id="inboxPanelBtn" onclick="InboxChat.switchView('agent')" title="Ir al Panel de Agente">
            📊 Panel
        </button>
    <?php endif; ?>
</div>

<!-- ── Vista Chat ── -->
<div class="inbox-chat-shell" id="inboxChatShell" style="display:<?= $view === 'chat' ? 'flex' : 'none' ?>">
    <!-- Sidebar -->
    <div class="inbox-chat-sidebar" id="inboxSidebar">
        <div class="inbox-sidebar-head">
            <input type="text" class="inbox-sidebar-search" id="inboxSearch" placeholder="🔍 Buscar conversación..." autocomplete="off" oninput="InboxChat.filterSidebar()">
        </div>
        <div class="inbox-lines-list" id="inboxLinesList">
            <div class="inbox-loading">Cargando líneas...</div>
        </div>
    </div>

    <!-- Chat panel -->
    <div class="inbox-chat-main" id="inboxChatMain">
        <div class="inbox-chat-header" id="inboxChatHeader">
            <button class="inbox-mobile-back" onclick="document.getElementById('inboxSidebar').classList.toggle('hidden-on-mobile')">←</button>
            <div class="inbox-chat-header-info">
                <div class="inbox-chat-header-name" id="inboxChatName">Selecciona una conversación</div>
                <div class="inbox-chat-header-sub" id="inboxChatSub"></div>
            </div>
            <div class="inbox-chat-header-actions">
                <span id="inboxStageBadge"></span>
                <button class="inbox-btn-toggle is-active" id="inboxPauseBtn" style="display:none" onclick="InboxChat.togglePause()">🤖 Auto</button>
                <button class="inbox-panel-add-btn" id="inboxPanelAddBtn" style="display:none" onclick="InboxChat.panelAddShow()" title="Añadir al panel de agente">📌 Panel</button>
            </div>
        </div>

        <div class="inbox-chat-messages" id="inboxMessages">
            <div class="inbox-chat-placeholder" id="inboxPlaceholder">
                <div class="inbox-chat-placeholder-icon">💬</div>
                <div>Selecciona una conversación para ver los mensajes</div>
            </div>
        </div>

        <div class="inbox-chat-input-area" id="inboxInputArea" style="display:none">
            <button class="inbox-attach-btn" id="inboxAttachBtn" onclick="InboxChat.openPhotoPicker()" title="Adjuntar fotos de habitaciones">📎</button>
            <textarea id="inboxChatInput" class="inbox-chat-input" placeholder="Escribe un mensaje..." rows="1"></textarea>
            <button class="inbox-chat-send-btn" id="inboxChatSendBtn" onclick="InboxChat.sendMessage()">Enviar</button>
        </div>
    </div>
</div>

<!-- ── Vista Bandeja (Agente Comercial) ── -->
<div class="inbox-agent-shell" id="inboxAgentShell" style="display:<?= $view === 'agent' ? 'flex' : 'none' ?>">
    <div class="inbox-agent-view" id="inboxAgentView">
        <div class="inbox-loading">Cargando panel...</div>
    </div>
</div>

<!-- ── Fullscreen chat overlay (para conversaciones desde la tabla de agente) ── -->
<div class="inbox-fullchat-overlay" id="inboxFullChat" style="display:none">
    <div class="inbox-fullchat-header">
        <button class="inbox-fullchat-back" onclick="InboxChat.closeFullChat()">← Volver</button>
        <div class="inbox-fullchat-header-info">
            <div class="inbox-fullchat-name" id="inboxFullChatName">Conversación</div>
            <div class="inbox-fullchat-sub" id="inboxFullChatSub"></div>
        </div>
        <div class="inbox-fullchat-header-actions">
            <button class="inbox-conv-pause" id="inboxFullChatPauseBtn" style="display:none" onclick="InboxChat.toggleFullChatPause()" title="Parar/reanudar respuestas automáticas del bot en esta conversación">
                <span class="inbox-conv-pause-label" id="inboxFullChatPauseLabel">🤖 Auto</span><span class="pause-pill"></span>
            </button>
        </div>
    </div>
    <div class="inbox-fullchat-messages" id="inboxFullChatMessages">
        <div class="inbox-chat-placeholder">
            <div class="inbox-chat-placeholder-icon">💬</div>
            <div>Cargando conversación...</div>
        </div>
    </div>
    <div class="inbox-fullchat-input-area" id="inboxFullChatInputArea">
        <button class="inbox-attach-btn" id="inboxFullChatAttachBtn" onclick="InboxChat.openPhotoPicker(true)" title="Adjuntar fotos de habitaciones">📎</button>
        <textarea class="inbox-fullchat-input" id="inboxFullChatInput" placeholder="Escribe un mensaje..." rows="1" onkeydown="InboxChat.handleFullChatKey(event)"></textarea>
        <button class="inbox-fullchat-send-btn" id="inboxFullChatSendBtn" onclick="InboxChat.sendFullChatMessage()">▶</button>
    </div>
</div>

<!-- ── Modal Agenda Comercial ── -->
<div class="inbox-agenda-overlay" id="inboxAgendaOverlay" style="display:none">
    <div class="inbox-agenda-panel">
        <div class="inbox-agenda-header">
            <span class="inbox-agenda-title">👤 Agenda Comercial</span>
            <button class="inbox-agenda-close" onclick="InboxChat.closeAgenda()">✕</button>
        </div>

        <!-- Filtro -->
        <div class="inbox-agenda-filters" id="inboxAgendaFilters">
            <select class="inbox-agenda-filter-select" id="inboxAgendaFilterNegocio" onchange="InboxChat.agendaLoad()">
                <option value="">Todos los negocios</option>
            </select>
            <button class="inbox-agenda-add-btn" onclick="InboxChat.agendaShowNewForm()" title="Añadir nuevo contacto">
                ＋ Añadir
            </button>
        </div>

        <!-- Lista / Tabla -->
        <div class="inbox-agenda-list" id="inboxAgendaList">
            <div class="inbox-loading">Cargando agenda...</div>
        </div>

        <!-- Formulario (añadir/editar) -->
        <div class="inbox-agenda-form-container" id="inboxAgendaFormContainer" style="display:none">
            <div class="inbox-agenda-form-header">
                <span id="inboxAgendaFormTitle">Nuevo contacto</span>
                <button class="inbox-agenda-back-btn" onclick="InboxChat.agendaHideForm()">← Volver al listado</button>
            </div>
            <form class="inbox-agenda-form" id="inboxAgendaForm" onsubmit="return InboxChat.agendaSave(event)">
                <input type="hidden" id="agendaFormId" value="">
                <input type="hidden" id="agendaFormThreadId" value="">

                <div class="agenda-field">
                    <label for="agendaFormNombre">Nombre</label>
                    <input type="text" id="agendaFormNombre" placeholder="Nombre del contacto" required autocomplete="off">
                </div>

                <div class="agenda-field">
                    <label for="agendaFormTelefono">Teléfono</label>
                    <input type="tel" id="agendaFormTelefono" placeholder="612345678" required autocomplete="off">
                </div>

                <div class="agenda-field">
                    <label for="agendaFormNegocio">Negocio</label>
                    <select id="agendaFormNegocio" required onchange="InboxChat.agendaToggleSubmode()">
                        <option value="">Seleccionar...</option>
                    </select>
                </div>

                <div class="agenda-field" id="agendaFieldSubmode" style="display:none">
                    <label for="agendaFormSubmode">Modalidad Jostal</label>
                    <select id="agendaFormSubmode">
                        <option value="plaza">Plaza</option>
                        <option value="alquiler">Alquiler</option>
                    </select>
                </div>

                <div class="agenda-field">
                    <label for="agendaFormNotas">Notas</label>
                    <textarea id="agendaFormNotas" rows="3" placeholder="Notas adicionales..."></textarea>
                </div>

                <div class="agenda-form-actions">
                    <button type="submit" class="agenda-btn-save" id="agendaBtnSave">💾 Guardar</button>
                    <button type="button" class="agenda-btn-cancel" onclick="InboxChat.agendaHideForm()">Cancelar</button>
                </div>
            </form>
        </div>

        <!-- Ficha detalle -->
        <div class="inbox-agenda-detail" id="inboxAgendaDetail" style="display:none">
            <div class="inbox-agenda-form-header">
                <span>Ficha Agenda</span>
                <button class="inbox-agenda-back-btn" onclick="InboxChat.agendaHideDetail()">← Volver al listado</button>
            </div>
            <div class="agenda-detail-content" id="inboxAgendaDetailContent"></div>
        </div>
    </div>
</div>

<!-- ── Modal: Añadir al Panel ── -->
<div class="inbox-panel-add-overlay" id="inboxPanelAddOverlay" style="display:none">
    <div class="inbox-panel-add-modal">
        <div class="inbox-panel-add-header">
            <span>📌 Añadir al Panel de Agente</span>
            <button class="inbox-panel-add-close" onclick="InboxChat.panelAddHide()">✕</button>
        </div>
        <form class="inbox-panel-add-form" onsubmit="return InboxChat.panelAddSubmit(event)">
            <input type="hidden" id="panelAddThreadId" value="">
            <div class="panel-add-field">
                <label for="panelAddNegocio">Negocio <span style="color:#f87171">*</span></label>
                <select id="panelAddNegocio" required>
                    <option value="">Seleccionar negocio...</option>
                    <option value="lamami">LaMami</option>
                    <option value="jostal">Jostal</option>
                    <option value="casawasap">CasaWasap</option>
                    <option value="publicista">Publicista</option>
                    <option value="general">General</option>
                </select>
            </div>
            <div class="panel-add-field">
                <label for="panelAddReason">Motivo <span style="color:#f87171">*</span></label>
                <textarea id="panelAddReason" rows="3" placeholder="Ej: La clienta preguntó 2 veces por el precio aunque la IA no lo detectó" required></textarea>
            </div>
            <div class="panel-add-actions">
                <button type="submit" class="panel-add-btn-save" id="panelAddBtnSave">✓ Añadir al Panel</button>
                <button type="button" class="panel-add-btn-cancel" onclick="InboxChat.panelAddHide()">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal: Adjuntar fotos de habitaciones ── -->
<div class="inbox-photo-picker-overlay" id="inboxPhotoPickerOverlay" style="display:none">
    <div class="inbox-photo-picker-modal">
        <div class="inbox-photo-picker-header">
            <span>📎 Fotos de habitaciones</span>
            <button class="inbox-photo-picker-close" onclick="InboxChat.photoPickerHide()">✕</button>
        </div>
        <div class="inbox-photo-picker-grid" id="inboxPhotoPickerGrid">
            <div class="inbox-loading">Cargando fotos...</div>
        </div>
        <div class="inbox-photo-picker-actions">
            <button type="button" class="inbox-photo-picker-send" id="inboxPhotoPickerSendBtn" onclick="InboxChat.photoPickerSend()" disabled>Enviar seleccionadas</button>
            <button type="button" class="inbox-photo-picker-cancel" onclick="InboxChat.photoPickerHide()">Cancelar</button>
        </div>
    </div>
</div>

<script>
// ── Init view ──
window.InboxChatInitialView = <?= json_encode($view) ?>;

// ── Global toggle handler (para switches) ──
InboxChat.toggleGlobal = function(type, checkbox) {
    if (!checkbox) return;
    var wasChecked = checkbox.checked;
    checkbox.disabled = true;

    fetch('inbox_api.php?action=toggle_' + type + '&_=' + Date.now(), {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=toggle_' + type,
        credentials: 'same-origin'
    })
    .then(function(r){ return r.json(); })
    .then(function(d){
        if (d.ok) {
            checkbox.checked = d.enabled;
            updateSwitchState(type, d.enabled);
            InboxChat.loadLines();
        } else {
            checkbox.checked = wasChecked;
        }
    })
    .catch(function(){
        checkbox.checked = wasChecked;
    })
    .finally(function(){ checkbox.disabled = false; });
};

function updateSwitchState(type, enabled) {
    if (type === 'replies') {
        var el = document.getElementById('inboxToggleReplies');
        var st = el ? el.querySelector('.inbox-switch__state') : null;
        if (st) st.textContent = enabled ? 'ON' : 'OFF';
    }
    if (type === 'opener') {
        // Inicio switch no tiene texto ON/OFF, solo track
    }
}
</script>
<script src="assets/inbox-chat.js?v=<?= $_chatJsV ?>-<?= $_forceV ?>"></script>

</body>
</html>
