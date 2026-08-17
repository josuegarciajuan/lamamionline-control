# Plan: Josue Lite - Fullscreen on video tap + easy exit

## Files to modify: 4

### 1. `app/views.php` — Add close button inside cassette door

**Location:** Line 10693, before `echo '</div>'; // door`

**Insert:**
```php
echo '<button type="button" class="yt-lite-fs-close" id="ytLiteFsClose" title="Salir de pantalla completa">&times;</button>';
```

This places a hidden close button inside `.yt-cassette-door`. CSS will show it only in FS mode.

---

### 2. `assets/lite.css` — FS video maximization + close button

**Location:** Replace the existing FS block at lines 10732-10754 with the following:

```css
/* ══════════════════════════════════════════════════════════════════
   FULLSCREEN — Lite video maximized
   ══════════════════════════════════════════════════════════════════ */
body.is-lite.josue-yt-fs .yt-lite-radio {
  min-height: 100vh;
  min-height: 100dvh;
  padding: 8px;
}

body.is-lite.josue-yt-fs .yt-radio-body {
  display: flex;
  flex-direction: column;
  min-height: 100%;
  gap: 6px;
  background: transparent;
  border: none;
  box-shadow: none;
  padding: 40px 8px 8px;
  border-radius: 0;
}

/* Hide decorative elements in FS to maximize video space */
body.is-lite.josue-yt-fs .yt-radio-display,
body.is-lite.josue-yt-fs .yt-power-led,
body.is-lite.josue-yt-fs .yt-cassette-reel,
body.is-lite.josue-yt-fs .yt-cassette-empty,
body.is-lite.josue-yt-fs .yt-cassette-info {
  display: none !important;
}

/* Deck row: stack vertically so video is above controls */
body.is-lite.josue-yt-fs .yt-radio-deck-row {
  flex: 1;
  flex-direction: column;
  min-height: 0;
  gap: 6px;
  margin-bottom: 0;
}

/* Cassette well: flex grow to fill space */
body.is-lite.josue-yt-fs .yt-cassette-well {
  flex: 1;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

/* Cassette door: fills available space, transparent */
body.is-lite.josue-yt-fs .yt-cassette-door {
  flex: 1;
  height: auto !important;
  background: transparent;
  border: 1px solid rgba(255,255,255,.08);
  border-radius: 8px;
  box-shadow: none;
  overflow: visible;
}

/* Cassette tape: fills door */
body.is-lite.josue-yt-fs .yt-cassette-tape.loaded {
  position: relative;
  width: 100%;
  height: 100%;
  opacity: 1;
  transform: none;
  pointer-events: auto;
  background: transparent;
  display: flex;
  align-items: center;
  justify-content: center;
}

/* Cassette label / video area: BIG */
body.is-lite.josue-yt-fs .yt-cassette-label {
  width: calc(100% - 16px);
  height: calc(100% - 16px);
  max-height: none;
  background: #000;
  border: none;
  border-radius: 6px;
}

body.is-lite.josue-yt-fs .yt-cassette-label .youtube-mini-player {
  height: 100%;
  border-radius: 6px;
}

body.is-lite.josue-yt-fs .yt-cassette-label .youtube-mini-player-placeholder {
  height: 100%;
  aspect-ratio: auto;
}

body.is-lite.josue-yt-fs .yt-cassette-label #youtubePlayerContainer,
body.is-lite.josue-yt-fs .yt-cassette-label #youtubePlayerContainer iframe {
  height: 100% !important;
}

/* Controls column: horizontal row at bottom, compact */
body.is-lite.josue-yt-fs .yt-deck-controls-col {
  flex: 0 0 auto;
  flex-direction: row;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 4px 0;
}

body.is-lite.josue-yt-fs .yt-mech-controls {
  margin-bottom: 0;
  gap: 4px;
}

body.is-lite.josue-yt-fs .yt-mech-btn {
  width: 36px;
  height: 32px;
  font-size: 12px;
}

body.is-lite.josue-yt-fs .yt-mech-btn.yt-mech-play {
  width: 44px;
  height: 38px;
  font-size: 16px;
}

body.is-lite.josue-yt-fs .yt-mech-btn.yt-mech-stop,
body.is-lite.josue-yt-fs .yt-mech-btn.yt-mech-rec {
  width: 32px;
  height: 28px;
  font-size: 10px;
}

body.is-lite.josue-yt-fs .yt-knob-row {
  margin-bottom: 0;
  gap: 8px;
}

body.is-lite.josue-yt-fs .yt-knob {
  width: 36px;
  height: 36px;
}

body.is-lite.josue-yt-fs .yt-knob-label {
  font-size: 8px;
}

body.is-lite.josue-yt-fs .yt-knob-val {
  font-size: 9px;
}

body.is-lite.josue-yt-fs .yt-rec-led {
  display: none;
}

/* Menu bank: compact at bottom */
body.is-lite.josue-yt-fs .yt-menu-bank {
  flex: 0 0 auto;
  gap: 4px;
  padding: 2px 0;
  margin: 0;
}

body.is-lite.josue-yt-fs .yt-menu-btn {
  font-size: 9px;
  padding: 4px 8px;
}

/* Search bar: hidden in FS */
body.is-lite.josue-yt-fs .yt-search-row {
  display: none !important;
}

/* Speed badge: hidden in FS */
body.is-lite.josue-yt-fs .yt-speed-badge {
  display: none !important;
}

/* ══════════════════════════════════════════════════════════════════
   FS CLOSE BUTTON — Vintage knob style, top-right
   ══════════════════════════════════════════════════════════════════ */
.yt-lite-fs-close {
  display: none;
  position: absolute;
  top: 8px;
  right: 8px;
  z-index: 200;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 2px solid var(--radio-border);
  background: radial-gradient(circle at 40% 35%, var(--radio-btn-hl), var(--radio-btn-bg));
  color: var(--radio-accent);
  font-size: 24px;
  font-weight: 700;
  cursor: pointer;
  align-items: center;
  justify-content: center;
  line-height: 1;
  padding: 0;
  box-shadow: 0 2px 10px rgba(0,0,0,.6);
  transition: transform .15s, border-color .15s;
  touch-action: manipulation;
}

.yt-lite-fs-close:active {
  transform: scale(0.9);
  border-color: var(--radio-accent-dim);
}

body.is-lite.josue-yt-fs .yt-lite-fs-close {
  display: flex;
}
```

---

### 3. `assets/app.js` — Video tap handler + close button handler

**Location:** In the existing click listener block (after line 6295, before the closing `});` at line 6296)

**Insert before the closing `});` of the click listener:**
```js
        // Lite: click on video player area → enter fullscreen
        var videoPlayerTap = e.target.closest('#youtubeMiniPlayer');
        if (videoPlayerTap && document.body.classList.contains('is-lite') && !document.body.classList.contains('josue-yt-fs')) {
            toggleJosueFS(true);
            liteEnsureOverlay();
            return;
        }

        // Lite: click close button → exit fullscreen
        var fsCloseBtn = e.target.closest('#ytLiteFsClose');
        if (fsCloseBtn && document.body.classList.contains('is-lite')) {
            toggleJosueFS(false);
            return;
        }
```

---

### 4. `index.php` — Cache bust versions

**Location:** Lines 155 and 274

**Before:**
- Line 155: `assets/lite.css?v=20260725_17`
- Line 274: `assets/app.js?v=20260725_47`

**After:**
- Line 155: `assets/lite.css?v=20260726_1`
- Line 274: `assets/app.js?v=20260726_1`
