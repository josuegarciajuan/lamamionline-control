/**
 * pwa.js — CasaWasap Panel
 * - Registra el Service Worker
 * - Maneja el evento beforeinstallprompt
 * - Muestra diálogo de instalación post-login (primera visita)
 * - Lógica de activación de tabs desde el bottom nav
 * - Backdrop y popovers móviles
 */

(function () {
  'use strict';

  // ── Service Worker registration (only on HTTPS) ──
  if ('serviceWorker' in navigator && location.protocol === 'https:') {
    navigator.serviceWorker.register('/sw.js?v=3', { scope: '/' }).catch(function () {
      // Silent fail — app works without SW
    });
  }

  // ── PWA Install Prompt ──
  var deferredPrompt = null;
  var installPromptShown = false;
  var installPromptBlocked = false;

  window.addEventListener('casawasap:tutorial-started', function () {
    installPromptBlocked = true;
  });
  window.addEventListener('casawasap:tutorial-finished', function () {
    installPromptBlocked = false;
    setTimeout(checkInstallPrompt, 300);
  });

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
  });

  // Detect if already installed as PWA
  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches ||
           navigator.standalone ||
           document.referrer.includes('android-app://');
  }

  // Check if prompt was shown in last 7 days
  function wasPromptedRecently() {
    try {
      var ts = localStorage.getItem('pwa_install_prompt_ts');
      if (ts) {
        var age = Date.now() - parseInt(ts, 10);
        return age < 7 * 24 * 60 * 60 * 1000; // 7 days
      }
    } catch (e) {}
    return false;
  }

  // Record that prompt was shown
  function markPrompted() {
    try {
      localStorage.setItem('pwa_install_prompt_ts', String(Date.now()));
    } catch (e) {}
  }

  // Create and show the install modal
  function showInstallModal() {
    if (installPromptShown) return;
    installPromptShown = true;

    var overlay = document.createElement('div');
    overlay.className = 'pwa-install-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');

    overlay.innerHTML =
      '<div class="pwa-install-card">' +
        '<div class="pwa-icon">📲</div>' +
        '<h2>Instalar CasaWasap</h2>' +
        '<p class="pwa-sub">Accede como una app, sin abrir el navegador. Más rápido, siempre a mano.</p>' +
        '<button class="pwa-btn-install" id="pwaInstallBtn">' +
          '<span class="pwa-btn-icon">⬇️</span> Instalar App' +
        '</button>' +
        '<button class="pwa-btn-continue" id="pwaContinueBtn">' +
          'Continuar en el navegador' +
        '</button>' +
        '<p class="pwa-manual-hint">' +
          'También puedes instalarla desde el menú <strong>⋮</strong> → <strong>Añadir a pantalla de inicio</strong>' +
        '</p>' +
      '</div>';

    // Styles for the modal (injected since this is the only place that needs them)
    var style = document.createElement('style');
    style.textContent =
      '.pwa-install-overlay{position:fixed;inset:0;background:rgba(5,5,16,.92);backdrop-filter:blur(12px);' +
      '-webkit-backdrop-filter:blur(12px);z-index:20000;display:flex;align-items:center;justify-content:center;' +
      'padding:20px;animation:pwaFadeIn .3s ease}' +
      '@keyframes pwaFadeIn{from{opacity:0}to{opacity:1}}' +
      '.pwa-install-card{background:linear-gradient(160deg,#141426,#1a1030);border:1px solid rgba(255,59,141,.25);' +
      'border-radius:24px;padding:36px 28px 28px;max-width:380px;width:100%;text-align:center;' +
      'box-shadow:0 20px 60px rgba(0,0,0,.6),0 0 0 1px rgba(255,255,255,.04);' +
      'animation:pwaSlideUp .4s cubic-bezier(.16,1,.3,1)}' +
      '@keyframes pwaSlideUp{from{opacity:0;transform:translateY(30px) scale(.96)}' +
      'to{opacity:1;transform:translateY(0) scale(1)}}' +
      '.pwa-install-card .pwa-icon{font-size:3rem;margin-bottom:8px}' +
      '.pwa-install-card h2{font-size:1.3rem;font-weight:700;color:#f7f7ff;margin:0 0 8px}' +
      '.pwa-install-card .pwa-sub{font-size:.85rem;color:#b5b5cc;line-height:1.5;margin:0 0 24px}' +
      '.pwa-btn-install{display:flex;align-items:center;justify-content:center;gap:8px;' +
      'width:100%;padding:14px;border:none;border-radius:999px;cursor:pointer;' +
      'font-size:.95rem;font-weight:700;font-family:inherit;' +
      'background:linear-gradient(135deg,#ff3b8d,#7c5cff);color:#fff;' +
      'box-shadow:0 4px 20px rgba(255,59,141,.3);' +
      'transition:transform .2s,box-shadow .2s;margin-bottom:10px}' +
      '.pwa-btn-install:hover{transform:translateY(-2px);box-shadow:0 8px 28px rgba(255,59,141,.45)}' +
      '.pwa-btn-continue{display:block;width:100%;padding:10px;border:1px solid rgba(255,255,255,.1);' +
      'border-radius:999px;cursor:pointer;font-size:.82rem;font-weight:500;font-family:inherit;' +
      'background:transparent;color:#b5b5cc;transition:color .2s,background .2s;margin-bottom:14px}' +
      '.pwa-btn-continue:hover{color:#fff;background:rgba(255,255,255,.05)}' +
      '.pwa-manual-hint{font-size:.72rem;color:rgba(255,255,255,.3);line-height:1.5;margin:0}' +
      '.pwa-btn-icon{font-size:1rem}';

    document.head.appendChild(style);
    document.body.appendChild(overlay);

    // Install button handler
    document.getElementById('pwaInstallBtn').addEventListener('click', function () {
      if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function (result) {
          if (result.outcome === 'accepted') {
            // Already installing — remove overlay
            document.body.removeChild(overlay);
            style.parentNode.removeChild(style);
          }
          deferredPrompt = null;
        });
      } else {
        // Fallback: show manual instructions
        var hint = overlay.querySelector('.pwa-manual-hint');
        if (hint) hint.style.color = '#f59e0b';
      }
    });

    // Continue button handler
    document.getElementById('pwaContinueBtn').addEventListener('click', function () {
      markPrompted();
      document.body.removeChild(overlay);
      style.parentNode.removeChild(style);
    });
  }

  // ── Run post-login check (called after page load) ──
  function checkInstallPrompt() {
    if (installPromptBlocked || window.CasaWasapTutorialActive) return;
    if (isStandalone()) return;        // Already installed
    if (!deferredPrompt) return;        // Browser doesn't support install prompt
    if (wasPromptedRecently()) return;  // Already shown recently

    // Show after a short delay so the page renders first
    setTimeout(function () {
      showInstallModal();
    }, 1500);
  }

  // ── Run on page load ──
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', checkInstallPrompt);
  } else {
    checkInstallPrompt();
  }

  // ────────────────────────────────────────────────────────────────────
  //  MOBILE BOTTOM NAV — Popover & Tab Activation Logic
  // ────────────────────────────────────────────────────────────────────

  var activePopId = null;
  var appBackdrop = null;

  function initBottomNav() {
    appBackdrop = document.getElementById('appBackdrop');

    // Backdrop click closes all
    if (appBackdrop) {
      appBackdrop.addEventListener('click', function () {
        closeAllPops();
      });
    }

    // Find all dropdown buttons and wire them
    var dropBtns = document.querySelectorAll('.mobile-nav-drop');
    dropBtns.forEach(function (btn) {
      var popId = btn.getAttribute('aria-controls');
      var pop = document.getElementById(popId);

      if (!pop) return;

      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        if (activePopId === popId) {
          closeAllPops();
        } else {
          closeAllPops();
          pop.hidden = false;
          btn.setAttribute('aria-expanded', 'true');
          activePopId = popId;
          if (appBackdrop) appBackdrop.hidden = false;
        }
      });

      // Links inside popover: close popover + activate tab if applicable
      pop.querySelectorAll('.mobile-nav-popover-link').forEach(function (link) {
        link.addEventListener('click', function (e) {
          var tabId = this.getAttribute('data-activate-tab');
          closeAllPops();
          if (tabId) {
            // Find and click the corresponding tab button
            var tabBtn = document.querySelector('.tab-nav button[data-tab="' + tabId + '"]');
            if (tabBtn) {
              tabBtn.click();
              window.scrollTo({ top: 0, behavior: 'smooth' });
            }
          }
        });
      });
    });

    // Click outside closes popover
    document.addEventListener('click', function (e) {
      if (activePopId && !e.target.closest('.mobile-nav-popover') && !e.target.closest('.mobile-nav-drop')) {
        closeAllPops();
      }
    });

    // Tab activation: bottom nav items that activate tabs
    document.querySelectorAll('.mobile-nav-item[data-activate-tab]').forEach(function (item) {
      item.addEventListener('click', function (e) {
        var tabId = this.getAttribute('data-activate-tab');
        if (!tabId) return;

        // Find and click the corresponding tab button
        var tabBtn = document.querySelector('.tab-nav button[data-tab="' + tabId + '"]');
        if (tabBtn) {
          tabBtn.click();
          // Scroll to tab content
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
      });
    });
  }

  function closeAllPops() {
    document.querySelectorAll('.mobile-nav-popover').forEach(function (pop) {
      pop.hidden = true;
    });
    document.querySelectorAll('.mobile-nav-drop').forEach(function (btn) {
      btn.setAttribute('aria-expanded', 'false');
    });
    if (appBackdrop) appBackdrop.hidden = true;
    activePopId = null;
  }

  // Initialize on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBottomNav);
  } else {
    initBottomNav();
  }

})();
